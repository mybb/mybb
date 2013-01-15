<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: sendthread.php 5297 2010-12-28 22:01:14Z Tomm $
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'sendthread.php');

$templatelist = "sendthread";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("sendthread");

// Get thread info
$tid = intval($mybb->input['tid']);
$thread = get_thread($tid);

// Get thread prefix
$breadcrumbprefix = '';
if($thread['prefix'])
{
	$threadprefixes = $cache->read('threadprefixes');
	if(isset($threadprefixes[$thread['prefix']]))
	{
		$breadcrumbprefix = $threadprefixes[$thread['prefix']]['displaystyle'].'&nbsp;';
	}
}

$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

// Invalid thread
if(!$thread['tid'])
{
	error($lang->error_invalidthread);
}

// Guests cannot use this feature
if(!$mybb->user['uid'])
{
	error_no_permission();
}
$fid = $thread['fid'];


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
if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || ($forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
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
	$query = $db->simple_select("maillogs", "COUNT(*) AS sent_count", "fromuid='{$mybb->user['uid']}' AND dateline >= '".(TIME_NOW - (60*60*24))."'");
	$sent_count = $db->fetch_field($query, "sent_count");
	if($sent_count >= $mybb->usergroup['maxemails'])
	{
		$lang->error_max_emails_day = $lang->sprintf($lang->error_max_emails_day, $mybb->usergroup['maxemails']);
		error($lang->error_max_emails_day);
	}
}

if($mybb->input['action'] == "do_sendtofriend" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("sendthread_do_sendtofriend_start");
	
	if(!validate_email_format($mybb->input['email']))
	{
		$errors[] = $lang->error_invalidemail;
	}
	
	if(empty($mybb->input['subject']))
	{
		$errors[] = $lang->error_nosubject;
	}	
	
	if(empty($mybb->input['message']))
	{
		$errors[] = $lang->error_nomessage;
	}

	// No errors detected
	if(count($errors) == 0)
	{
		if($mybb->settings['mail_handler'] == 'smtp')
		{
			$from = $mybb->user['email'];
		}
		else
		{
			$from = "{$mybb->user['username']} <{$mybb->user['email']}>";
		}
		
		$threadlink = get_thread_link($thread['tid']);
		
		$message = $lang->sprintf($lang->email_sendtofriend, $mybb->user['username'], $mybb->settings['bbname'], $mybb->settings['bburl']."/".$threadlink, $mybb->input['message']);
		
		// Send the actual message
		my_mail($mybb->input['email'], $mybb->input['subject'], $message, $from, "", "", false, "text", "", $mybb->user['email']);
		
		if($mybb->settings['mail_logging'] > 0)
		{
			// Log the message
			$log_entry = array(
				"subject" => $db->escape_string($mybb->input['subject']),
				"message" => $db->escape_string($message),
				"dateline" => TIME_NOW,
				"fromuid" => $mybb->user['uid'],
				"fromemail" => $db->escape_string($mybb->user['email']),
				"touid" => 0,
				"toemail" => $db->escape_string($mybb->input['email']),
				"tid" => $thread['tid'],
				"ipaddress" => $db->escape_string($session->ipaddress)
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
		$subject = htmlspecialchars_uni($mybb->input['subject']);
		$message = htmlspecialchars_uni($mybb->input['message']);
	}
	else
	{
		$errors = '';
		$email = '';
		$subject = $lang->sprintf($lang->emailsubject_sendtofriend, $mybb->settings['bbname']);
		$message = '';
	}
	
	$plugins->run_hooks("sendthread_end");

	eval("\$sendtofriend = \"".$templates->get("sendthread")."\";");
	output_page($sendtofriend);
}
?>
