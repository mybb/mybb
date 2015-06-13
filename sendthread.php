<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'sendthread.php');

$templatelist = "sendthread,sendthread_fromemail,forumdisplay_password_wrongpass,forumdisplay_password,post_captcha,post_captcha_recaptcha,post_captcha_nocaptcha,post_captcha_ayah";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("sendthread");

// Get thread info
$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
$thread = get_thread($tid);

// Invalid thread
if(!$thread || $thread['visible'] != 1)
{
	error($lang->error_invalidthread);
}

// Get thread prefix
$breadcrumbprefix = '';
$threadprefix = array('prefix' => '');
if($thread['prefix'])
{
	$threadprefix = build_prefixes($thread['prefix']);
	if(!empty($threadprefix['displaystyle']))
	{
		$breadcrumbprefix = $threadprefix['displaystyle'].'&nbsp;';
	}
}

$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

// Make navigation
build_forum_breadcrumb($thread['fid']);
add_breadcrumb($breadcrumbprefix.$thread['subject'], get_thread_link($thread['tid']));
add_breadcrumb($lang->nav_sendthread);

// Get forum info
$forum = get_forum($thread['fid']);
$forumpermissions = forum_permissions($forum['fid']);

// Invalid forum?
if(!$forum['fid'] || $forum['type'] != "f")
{
	error($lang->error_invalidforum);
}

// This user can't view this forum or this thread
if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
{
	error_no_permission();
}

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);

if($mybb->usergroup['cansendemail'] == 0)
{
	error_no_permission();
}

// Check group limits
if($mybb->usergroup['maxemails'] > 0)
{
	if($mybb->user['uid'] > 0)
	{
		$user_check = "fromuid='{$mybb->user['uid']}'";
	}
	else
	{
		$user_check = "ipaddress=".$db->escape_binary($session->packedip);
	}

	$query = $db->simple_select("maillogs", "COUNT(*) AS sent_count", "{$user_check} AND dateline >= '".(TIME_NOW - (60*60*24))."'");
	$sent_count = $db->fetch_field($query, "sent_count");
	if($sent_count >= $mybb->usergroup['maxemails'])
	{
		$lang->error_max_emails_day = $lang->sprintf($lang->error_max_emails_day, $mybb->usergroup['maxemails']);
		error($lang->error_max_emails_day);
	}
}

// Check email flood control
if($mybb->usergroup['emailfloodtime'] > 0)
{
	if($mybb->user['uid'] > 0)
	{
		$user_check = "fromuid='{$mybb->user['uid']}'";
	}
	else
	{
		$user_check = "ipaddress=".$db->escape_binary($session->packedip);
	}

	$timecut = TIME_NOW-$mybb->usergroup['emailfloodtime']*60;

	$query = $db->simple_select("maillogs", "mid, dateline", "{$user_check} AND dateline > '{$timecut}'", array('order_by' => "dateline", 'order_dir' => "DESC"));
	$last_email = $db->fetch_array($query);

	// Users last email was within the flood time, show the error
	if($last_email['mid'])
	{
		$remaining_time = ($mybb->usergroup['emailfloodtime']*60)-(TIME_NOW-$last_email['dateline']);

		if($remaining_time == 1)
		{
			$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_1_second, $mybb->usergroup['emailfloodtime']);
		}
		elseif($remaining_time < 60)
		{
			$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_seconds, $mybb->usergroup['emailfloodtime'], $remaining_time);
		}
		elseif($remaining_time > 60 && $remaining_time < 120)
		{
			$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_1_minute, $mybb->usergroup['emailfloodtime']);
		}
		else
		{
			$remaining_time_minutes = ceil($remaining_time/60);
			$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_minutes, $mybb->usergroup['emailfloodtime'], $remaining_time_minutes);
		}

		error($lang->error_emailflooding);
	}
}

$errors = array();

$mybb->input['action'] = $mybb->get_input('action');
if($mybb->input['action'] == "do_sendtofriend" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("sendthread_do_sendtofriend_start");

	if(!validate_email_format($mybb->input['email']))
	{
		$errors[] = $lang->error_invalidemail;
	}

	if($mybb->user['uid'])
	{
		$mybb->input['fromemail'] = $mybb->user['email'];
		$mybb->input['fromname'] = $mybb->user['username'];
	}

	if(!validate_email_format($mybb->input['fromemail']))
	{
		$errors[] = $lang->error_invalidfromemail;
	}

	if(empty($mybb->input['fromname']))
	{
		$errors[] = $lang->error_noname;
	}

	if(empty($mybb->input['subject']))
	{
		$errors[] = $lang->error_nosubject;
	}

	if(empty($mybb->input['message']))
	{
		$errors[] = $lang->error_nomessage;
	}

	if($mybb->settings['captchaimage'] && $mybb->user['uid'] == 0)
	{
		require_once MYBB_ROOT.'inc/class_captcha.php';
		$captcha = new captcha;

		if($captcha->validate_captcha() == false)
		{
			// CAPTCHA validation failed
			foreach($captcha->get_errors() as $error)
			{
				$errors[] = $error;
			}
		}
	}

	// No errors detected
	if(count($errors) == 0)
	{
		if($mybb->settings['mail_handler'] == 'smtp')
		{
			$from = $mybb->input['fromemail'];
		}
		else
		{
			$from = "{$mybb->input['fromname']} <{$mybb->input['fromemail']}>";
		}

		$threadlink = get_thread_link($thread['tid']);

		$message = $lang->sprintf($lang->email_sendtofriend, $mybb->input['fromname'], $mybb->settings['bbname'], $mybb->settings['bburl']."/".$threadlink, $mybb->input['message']);

		// Send the actual message
		my_mail($mybb->input['email'], $mybb->input['subject'], $message, $from, "", "", false, "text", "", $mybb->input['fromemail']);

		if($mybb->settings['mail_logging'] > 0)
		{
			// Log the message
			$log_entry = array(
				"subject" => $db->escape_string($mybb->input['subject']),
				"message" => $db->escape_string($message),
				"dateline" => TIME_NOW,
				"fromuid" => $mybb->user['uid'],
				"fromemail" => $db->escape_string($mybb->input['fromemail']),
				"touid" => 0,
				"toemail" => $db->escape_string($mybb->input['email']),
				"tid" => $thread['tid'],
				"ipaddress" => $db->escape_binary($session->packedip),
				"type" => 2
			);
			$db->insert_query("maillogs", $log_entry);
		}

		$plugins->run_hooks("sendthread_do_sendtofriend_end");
		redirect(get_thread_link($thread['tid']), $lang->redirect_emailsent);
	}
	else
	{
		$mybb->input['action'] = '';
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("sendthread_start");

	// Do we have some errors?
	if(count($errors) >= 1)
	{
		$errors = inline_error($errors);
		$email = htmlspecialchars_uni($mybb->input['email']);
		$fromname = htmlspecialchars_uni($mybb->input['fromname']);
		$fromemail = htmlspecialchars_uni($mybb->input['fromemail']);
		$subject = htmlspecialchars_uni($mybb->input['subject']);
		$message = htmlspecialchars_uni($mybb->input['message']);
	}
	else
	{
		$errors = '';
		$email = '';
		$fromname = '';
		$fromemail = '';
		$subject = $lang->sprintf($lang->emailsubject_sendtofriend, $mybb->settings['bbname']);
		$message = '';
	}

	// Generate CAPTCHA?
	if($mybb->settings['captchaimage'] && $mybb->user['uid'] == 0)
	{
		require_once MYBB_ROOT.'inc/class_captcha.php';
		$post_captcha = new captcha(true, "post_captcha");

		if($post_captcha->html)
		{
			$captcha = $post_captcha->html;
		}
	}
	else
	{
		$captcha = '';
	}

	$from_email = '';
	if($mybb->user['uid'] == 0)
	{
		eval("\$from_email = \"".$templates->get("sendthread_fromemail")."\";");
	}

	$plugins->run_hooks("sendthread_end");

	eval("\$sendtofriend = \"".$templates->get("sendthread")."\";");
	output_page($sendtofriend);
}
