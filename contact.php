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
define('THIS_SCRIPT', 'contact.php');

$templatelist = "contact,post_captcha";

require_once "./global.php";
require_once MYBB_ROOT.'inc/class_captcha.php';

// Load global language phrases
$lang->load("contact");

$plugins->run_hooks('contact_start');

// Make navigation
add_breadcrumb($lang->contact, "contact.php");

if($mybb->settings['contact'] != 1 || (!$mybb->user['uid'] && $mybb->settings['contact_guests'] == 1))
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

$mybb->input['message'] = trim_blank_chrs($mybb->get_input('message'));
$mybb->input['subject'] = trim_blank_chrs($mybb->get_input('subject'));
$mybb->input['email'] = trim_blank_chrs($mybb->get_input('email'));

if($mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks('contact_do_start');

	// Validate input
	if(empty($mybb->input['subject']))
	{
		$errors[] = $lang->contact_no_subject;
	}

	if(strlen($mybb->input['subject']) > $mybb->settings['contact_maxsubjectlength'] && $mybb->settings['contact_maxsubjectlength'] > 0)
	{
		$errors[] = $lang->sprintf($lang->subject_too_long, $mybb->settings['contact_maxsubjectlength'], strlen($mybb->input['subject']));
	}

	if(empty($mybb->input['message']))
	{
		$errors[] = $lang->contact_no_message;
	}

	if(strlen($mybb->input['message']) > $mybb->settings['contact_maxmessagelength'] && $mybb->settings['contact_maxmessagelength'] > 0)
	{
		$errors[] = $lang->sprintf($lang->message_too_long, $mybb->settings['contact_maxmessagelength'], strlen($mybb->input['message']));
	}

	if(strlen($mybb->input['message']) < $mybb->settings['contact_minmessagelength'] && $mybb->settings['contact_minmessagelength'] > 0)
	{
		$errors[] = $lang->sprintf($lang->message_too_short, $mybb->settings['contact_minmessagelength'], strlen($mybb->input['message']));
	}

	if(empty($mybb->input['email']))
	{
		$errors[] = $lang->contact_no_email;
	}
	else
	{
		// Validate email
		if(!validate_email_format($mybb->input['email']))
		{
			$errors[] = $lang->contact_no_email;
		}
	}

	// Should we have a CAPTCHA? Perhaps yes...
	if($mybb->settings['captchaimage'])
	{
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

	if(empty($errors))
	{
		if($mybb->settings['contact_badwords'] == 1)
		{
			// Load the post parser
			require_once MYBB_ROOT."inc/class_parser.php";
			$parser = new postParser;

			$parser_options = array(
				'filter_badwords' => 1
			);

			$mybb->input['subject'] = $parser->parse_message($mybb->input['subject'], $parser_options);
			$mybb->input['message'] = $parser->parse_message($mybb->input['message'], $parser_options);
		}

		$user = $lang->na;
		if($mybb->user['uid'])
		{
			$user = $mybb->user['username'].' - '.$mybb->settings['bburl'].'/'.get_profile_link($mybb->user['uid']);
		}

		$subject = $lang->sprintf($lang->email_contact_subject, $mybb->input['subject']);
		$message = $lang->sprintf($lang->email_contact, $user, $session->ipaddress, $mybb->input['message']);

		$plugins->run_hooks('contact_do_end');

		// Email the administrator
		my_mail($mybb->settings['adminemail'], $subject, $message, $mybb->input['email']);

		if($mybb->settings['mail_logging'] > 0)
		{
			// Log the message
			$log_entry = array(
				"subject" => $db->escape_string($subject),
				"message" => $db->escape_string($message),
				"dateline" => TIME_NOW,
				"fromuid" => $mybb->user['uid'],
				"fromemail" => $db->escape_string($mybb->input['email']),
				"touid" => 0,
				"toemail" => $db->escape_string($mybb->settings['adminemail']),
				"tid" => 0,
				"ipaddress" => $db->escape_binary($session->packedip),
				"type" => 3
			);
			$db->insert_query("maillogs", $log_entry);
		}

		// Redirect
		redirect('contact.php', $lang->contact_success_message);
	}
	else
	{
		$errors = inline_error($errors);
	}
}

if(empty($errors))
{
	$errors = '';
}

// Generate CAPTCHA?
if($mybb->settings['captchaimage'])
{
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

$mybb->input['subject'] = htmlspecialchars_uni($mybb->input['subject']);
$mybb->input['message'] = htmlspecialchars_uni($mybb->input['message']);
$mybb->input['email'] = htmlspecialchars_uni($mybb->input['email']);

$plugins->run_hooks('contact_end');

eval("\$page = \"".$templates->get("contact")."\";");
output_page($page);
?>