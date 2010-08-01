<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: private.php 4864 2010-04-10 09:13:19Z RyanGordon $
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'private.php');

$templatelist = "private_send,private_send_buddyselect,private_read,private_tracking,private_tracking_readmessage,private_tracking_unreadmessage";
$templatelist .= ",private_folders,private_folders_folder,private_folders_folder_unremovable,private,usercp_nav_changename,usercp_nav,private_empty_folder,private_empty,posticons";
$templatelist .= "usercp_nav_messenger,usercp_nav_changename,usercp_nav_profile,usercp_nav_misc,usercp_nav_messenger,multipage_nextpage,multipage_page_current,multipage_page,multipage_start,multipage_end,multipage";
$templatelist .= ",private_messagebit,codebuttons,smilieinsert,posticons,private_send_autocomplete,private_messagebit_denyreceipt,private_read_to, postbit_online,postbit_find,postbit_pm, postbit_email,postbit_reputation,postbit_warninglevel,postbit_author_user,postbit_reply_pm,postbit_forward_pm,postbit_delete_pm,postbit,private_tracking_nomessage";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("private");

if($mybb->settings['enablepms'] == 0)
{
	error($lang->pms_disabled);
}

if($mybb->user['uid'] == '/' || $mybb->user['uid'] == 0 || $mybb->usergroup['canusepms'] == 0)
{
	error_no_permission();
}

if(!$mybb->user['pmfolders'])
{
	$mybb->user['pmfolders'] = "1**$%%$2**$%%$3**$%%$4**";

	$sql_array = array(
		 "pmfolders" => $mybb->user['pmfolders']
	);
	$db->update_query("users", $sql_array, "uid = ".$mybb->user['uid']);
}

// On a random occassion, recount the users pm's just to make sure everything is in sync.
$rand = my_rand(0, 9);
if($rand == 5)
{
	update_pm_count();
}

$folderjump = "<select name=\"jumpto\">\n";
$folderoplist = "<input type=\"hidden\" value=\"".intval($mybb->input['fid'])."\" name=\"fromfid\" />\n<select name=\"fid\">\n";
$folderjump2 = "<select name=\"jumpto2\">\n";

$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
foreach($foldersexploded as $key => $folders)
{
	$folderinfo = explode("**", $folders, 2);
	if($mybb->input['fid'] == $folderinfo[0])
	{
		$sel = ' selected="selected"';
	}
	else
	{
		$sel = '';
	}
	$folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
	$folderjump .= "<option value=\"$folderinfo[0]\"$sel>$folderinfo[1]</option>\n";
	$folderjump2 .= "<option value=\"$folderinfo[0]\"$sel>$folderinfo[1]</option>\n";
	$folderoplist .= "<option value=\"$folderinfo[0]\"$sel>$folderinfo[1]</option>\n";
	$folderlinks .= "&#149;&nbsp;<a href=\"private.php?fid=$folderinfo[0]\">$folderinfo[1]</a><br />\n";
}
$folderjump .= "</select>\n";
$folderjump2 .= "</select>\n";
$folderoplist .= "</select>\n";

usercp_menu();


// Make navigation
add_breadcrumb($lang->nav_pms, "private.php");

switch($mybb->input['action'])
{
	case "send":
		add_breadcrumb($lang->nav_send);
		break;
	case "tracking":
		add_breadcrumb($lang->nav_tracking);
		break;
	case "folders":
		add_breadcrumb($lang->nav_folders);
		break;
	case "empty":
		add_breadcrumb($lang->nav_empty);
		break;
	case "export":
		add_breadcrumb($lang->nav_export);
		break;
}
if($mybb->input['preview'])
{
	$mybb->input['action'] = "send";
}

// Dismissing a new/unread PM notice
if($mybb->input['action'] == "dismiss_notice")
{
	if($mybb->user['pmnotice'] != 2)
	{
		exit;
	}

	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$updated_user = array(
		"pmnotice" => 1
	);
	$db->update_query("users", $updated_user, "uid='{$mybb->user['uid']}'");

	if($mybb->input['ajax'])
	{
		echo 1;
		exit;
	}
	else
	{
		header("Location: index.php");
		exit;
	}
}

$send_errors = '';

if($mybb->input['action'] == "do_send" && $mybb->request_method == "post")
{
	if($mybb->usergroup['cansendpms'] == 0)
	{
		error_no_permission();
	}

	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("private_send_do_send");

	// Attempt to see if this PM is a duplicate or not
	$time_cutoff = TIME_NOW - (5 * 60 * 60);
	$query = $db->query("
		SELECT pm.pmid
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users u ON(u.uid=pm.toid)
		WHERE u.username='".$db->escape_string($mybb->input['to'])."' AND pm.dateline > {$time_cutoff} AND pm.fromid='{$mybb->user['uid']}' AND pm.subject='".$db->escape_string($mybb->input['subject'])."' AND pm.message='".$db->escape_string($mybb->input['message'])."' AND pm.folder!='3'
	");
	$duplicate_check = $db->fetch_field($query, "pmid");
	if($duplicate_check)
	{
		error($lang->error_pm_already_submitted);
	}

	require_once MYBB_ROOT."inc/datahandlers/pm.php";
	$pmhandler = new PMDataHandler();

	$pm = array(
		"subject" => $mybb->input['subject'],
		"message" => $mybb->input['message'],
		"icon" => $mybb->input['icon'],
		"fromid" => $mybb->user['uid'],
		"do" => $mybb->input['do'],
		"pmid" => $mybb->input['pmid']
	);

	// Split up any recipients we have
	$pm['to'] = explode(",", $mybb->input['to']);
	$pm['to'] = array_map("trim", $pm['to']);
	if(!empty($mybb->input['bcc']))
	{
		$pm['bcc'] = explode(",", $mybb->input['bcc']);
		$pm['bcc'] = array_map("trim", $pm['bcc']);
	}

	$pm['options'] = array(
		"signature" => $mybb->input['options']['signature'],
		"disablesmilies" => $mybb->input['options']['disablesmilies'],
		"savecopy" => $mybb->input['options']['savecopy'],
		"readreceipt" => $mybb->input['options']['readreceipt']
	);

	if($mybb->input['saveasdraft'])
	{
		$pm['saveasdraft'] = 1;
	}
	$pmhandler->set_data($pm);

	// Now let the pm handler do all the hard work.
	if(!$pmhandler->validate_pm())
	{
		$pm_errors = $pmhandler->get_friendly_errors();
		$send_errors = inline_error($pm_errors);
		$mybb->input['action'] = "send";
	}
	else
	{
		$pminfo = $pmhandler->insert_pm();
		$plugins->run_hooks("private_do_send_end");

		if(isset($pminfo['draftsaved']))
		{
			redirect("private.php", $lang->redirect_pmsaved);
		}
		else
		{
			redirect("private.php", $lang->redirect_pmsent);
		}
	}
}

if($mybb->input['action'] == "send")
{
	if($mybb->usergroup['cansendpms'] == 0)
	{
		error_no_permission();
	}

	$plugins->run_hooks("private_send_start");

	$smilieinserter = $codebuttons = '';
	
	if($mybb->settings['bbcodeinserter'] != 0 && $mybb->settings['pmsallowmycode'] != 0 && $mybb->user['showcodebuttons'] != 0)
	{
		$codebuttons = build_mycode_inserter();
		if($mybb->settings['pmsallowsmilies'] != 0)
		{
			$smilieinserter = build_clickable_smilies();
		}
	}

	$posticons = get_post_icons();
	$previewmessage = $mybb->input['message'];
	$message = htmlspecialchars_uni($mybb->input['message']);
	$subject = $previewsubject = htmlspecialchars_uni($mybb->input['subject']);

	if($mybb->input['preview'] || $send_errors)
	{
		$options = $mybb->input['options'];
		if($options['signature'] == 1)
		{
			$optionschecked['signature'] = 'checked="checked"';
		}
		if($options['disablesmilies'] == 1)
		{
			$optionschecked['disablesmilies'] = 'checked="checked"';
		}
		if($options['savecopy'] != 0)
		{
			$optionschecked['savecopy'] = 'checked="checked"';
		}
		if($options['readreceipt'] != 0)
		{
			$optionschecked['readreceipt'] = 'checked="checked"';
		}
		$to = htmlspecialchars_uni($mybb->input['to']);
		$bcc = htmlspecialchars_uni($mybb->input['bcc']);
	}

	// Preview
	if($mybb->input['preview'])
	{
		$options = $mybb->input['options'];
		$query = $db->query("
			SELECT u.username AS userusername, u.*, f.*, g.title AS grouptitle, g.usertitle AS groupusertitle, g.namestyle, g.stars AS groupstars, g.starimage AS groupstarimage, g.image AS groupimage, g.usereputationsystem
			FROM ".TABLE_PREFIX."users u
			LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup)
			WHERE u.uid='".$mybb->user['uid']."'
		");
		$post = $db->fetch_array($query);
		$post['userusername'] = $mybb->user['username'];
		$post['postusername'] = $mybb->user['username'];
		$post['message'] = $previewmessage;
		$post['subject'] = $previewsubject;
		$post['icon'] = $mybb->input['icon'];
		$post['smilieoff'] = $options['disablesmilies'];
		$post['dateline'] = TIME_NOW;
		if(!$options['signature'])
		{
			$post['includesig'] = 0;
		}
		else
		{
			$post['includesig'] = 1;
		}
		$postbit = build_postbit($post, 2);
		eval("\$preview = \"".$templates->get("previewpost")."\";");
	}
	else if(!$send_errors)
	{
		// New PM, so load default settings
		if($mybb->user['signature'] != '')
		{
			$optionschecked['signature'] = 'checked="checked"';
		}
		if($mybb->usergroup['cantrackpms'] == 1)
		{
			$optionschecked['readreceipt'] = 'checked="checked"';
		}
		$optionschecked['savecopy'] = 'checked="checked"';
	}
	
	// Draft, reply, forward
	if($mybb->input['pmid'] && !$mybb->input['preview'] && !$send_errors)
	{
		$query = $db->query("
			SELECT pm.*, u.username AS quotename
			FROM ".TABLE_PREFIX."privatemessages pm
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.fromid)
			WHERE pm.pmid='".intval($mybb->input['pmid'])."' AND pm.uid='".$mybb->user['uid']."'
		");
		$pm = $db->fetch_array($query);

		$message = htmlspecialchars_uni($pm['message']);
		$subject = htmlspecialchars_uni($pm['subject']);

		if($pm['folder'] == "3")
		{ // message saved in drafts
			$mybb->input['uid'] = $pm['toid'];

			if($pm['includesig'] == 1)
			{
				$optionschecked['signature'] = 'checked="checked"';
			}
			if($pm['smilieoff'] == 1)
			{
				$optionschecked['disablesmilies'] = 'checked="checked"';
			}
			if($pm['receipt'])
			{
				$optionschecked['readreceipt'] = 'checked="checked"';
			}

			// Get list of recipients
			$recipients = unserialize($pm['recipients']);
			$comma = '';
			if(isset($recipients['to']) && is_array($recipients['to']))
			{
				foreach($recipients['to'] as $recipient)
				{
					$recipient_list['to'][] = $recipient;
					$recipientids .= $comma.$recipient;
					$comma = ',';
				}
			}
			
			if(isset($recipients['bcc']) && is_array($recipients['bcc']))
			{
				foreach($recipients['bcc'] as $recipient)
				{
					$recipient_list['bcc'][] = $recipient;
					$recipientids .= $comma.$recipient;
					$comma = ',';
				}	
			}
			
			if(!empty($recipientids))
			{
				$query = $db->simple_select("users", "uid, username", "uid IN ({$recipientids})");
				while($user = $db->fetch_array($query))
				{
					if(isset($recipients['bcc']) && is_array($recipients['bcc']) && in_array($user['uid'], $recipient_list['bcc']))
					{
						$bcc .= htmlspecialchars_uni($user['username']).', ';
					}
					else
					{
						$to .= htmlspecialchars_uni($user['username']).', ';
					}
				}
			}
		}
		else
		{ // forward/reply
			$subject = preg_replace("#(FW|RE):( *)#is", '', $subject);
			$postdate = my_date($mybb->settings['dateformat'], $pm['dateline']);
			$posttime = my_date($mybb->settings['timeformat'], $pm['dateline']);
			$message = "[quote={$pm['quotename']}]\n$message\n[/quote]";
			$message = preg_replace('#^/me (.*)$#im', "* ".$pm['quotename']." \\1", $message);

			if($mybb->input['do'] == 'forward')
			{
				$subject = "Fw: $subject";
			}
			elseif($mybb->input['do'] == 'reply')
			{
				$subject = "Re: $subject";
				$uid = $pm['fromid'];
				if($mybb->user['uid'] == $uid)
				{
					$to = $mybb->user['username'];
				}
				else
				{
					$query = $db->simple_select('users', 'username', "uid='{$uid}'");
					$to = $db->fetch_field($query, 'username');
				}
				$to = htmlspecialchars_uni($to);
			}
			else if($mybb->input['do'] == 'replyall')
			{
				$subject = "Re: $subject";

				// Get list of recipients
				$recipients = unserialize($pm['recipients']);
				$recipientids = $pm['fromid'];
				if(isset($recipients['to']) && is_array($recipients['to']))
				{
					foreach($recipients['to'] as $recipient)
					{
						if($recipient == $mybb->user['uid'])
						{
							continue;
						}
						$recipientids .= ','.$recipient;
					}
				}
				$comma = '';
				$query = $db->simple_select('users', 'uid, username', "uid IN ({$recipientids})");
				while($user = $db->fetch_array($query))
				{
					$to .= $comma.htmlspecialchars($user['username']);
					$comma = ', ';
				}
			}
		}
	}

	// New PM with recipient preset
	if($mybb->input['uid'] && !$mybb->input['preview'])
	{
		$query = $db->simple_select('users', 'username', "uid='".$db->escape_string($mybb->input['uid'])."'");
		$to = htmlspecialchars_uni($db->fetch_field($query, 'username')).', ';
	}

	$max_recipients = '';
	if($mybb->usergroup['maxpmrecipients'] > 0)
	{
		$max_recipients = $lang->sprintf($lang->max_recipients, $mybb->usergroup['maxpmrecipients']);
	}

	if($send_errors)
	{
		$to = htmlspecialchars_uni($mybb->input['to']);
		$bcc = htmlspecialchars_uni($mybb->input['bcc']); 
	}

	// Load the auto complete javascript if it is enabled.
	eval("\$autocompletejs = \"".$templates->get("private_send_autocomplete")."\";");

	$pmid = $mybb->input['pmid'];
	$do = $mybb->input['do'];
	if($do != "forward" && $do != "reply" && $do != "replyall")
	{
		$do = '';
	}
	
	// See if it's actually worth showing the buddylist icon.
	if($mybb->user['buddylist'] != '')
	{
		$buddy_select = 'to';
		eval("\$buddy_select_to = \"".$templates->get("private_send_buddyselect")."\";");
		$buddy_select = 'bcc';
		eval("\$buddy_select_bcc = \"".$templates->get("private_send_buddyselect")."\";");
	}

	eval("\$send = \"".$templates->get("private_send")."\";");
	$plugins->run_hooks("private_send_end");
	output_page($send);
}


if($mybb->input['action'] == "read")
{
	$plugins->run_hooks("private_read");

	$pmid = intval($mybb->input['pmid']);

	$query = $db->query("
		SELECT pm.*, u.*, f.*, g.title AS grouptitle, g.usertitle AS groupusertitle, g.stars AS groupstars, g.starimage AS groupstarimage, g.image AS groupimage, g.namestyle
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.fromid)
		LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
		LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup)
		WHERE pm.pmid='".intval($mybb->input['pmid'])."' AND pm.uid='".$mybb->user['uid']."'
	");
	$pm = $db->fetch_array($query);
	if($pm['folder'] == 3)
	{
		header("Location: private.php?action=send&pmid={$pm['pmid']}");
		exit;
	}

	if(!$pm['pmid'])
	{
		error($lang->error_invalidpm);
	}

	if($pm['receipt'] == 1)
	{
		if($mybb->usergroup['cantrackpms'] == 1 && $mybb->usergroup['candenypmreceipts'] == 1 && $mybb->input['denyreceipt'] == 1)
		{
			$receiptadd = 0;
		}
		else
		{
			$receiptadd = 2;
		}
	}

	if($pm['status'] == 0)
	{
		$time = TIME_NOW;
		$updatearray = array(
			'status' => 1,
			'readtime' => $time
		);

		if(isset($receiptadd))
		{
			$updatearray['receipt'] = $receiptadd;
		}

		$db->update_query('privatemessages', $updatearray, "pmid='{$pmid}'");

		// Update the unread count - it has now changed.
		update_pm_count($mybb->user['uid'], 6);

		// Update PM notice value if this is our last unread PM
		if($mybb->user['unreadpms']-1 <= 0 && $mybb->user['pmnotice'] == 2)
		{
			$updated_user = array(
				"pmnotice" => 1
			);
			$db->update_query("users", $updated_user, "uid='{$mybb->user['uid']}'");
		}
	}
	// Replied PM?
	else if($pm['status'] == 3 && $pm['statustime'])
	{
		$reply_date = my_date($mybb->settings['dateformat'], $pm['statustime']);
		
		if($reply_date == $lang->today || $reply_date == $lang->yesterday)
		{
			$reply_date .= ', '.my_date($mybb->settings['timeformat'], $pm['statustime']);
			$actioned_on = $lang->sprintf($lang->you_replied, $reply_date);
		}
		else
		{
			$reply_date .= ', '.my_date($mybb->settings['timeformat'], $pm['statustime']);
			$actioned_on = $lang->sprintf($lang->you_replied_on, $reply_date);
		}
		
		eval("\$action_time = \"".$templates->get("private_read_action")."\";");
	}
	else if($pm['status'] == 4 && $pm['statustime'])
	{
		$forward_date = my_date($mybb->settings['dateformat'], $pm['statustime']);
		
		if(strpos($forward_date, $lang->today) !== false || strpos($forward_date, $lang->yesterday) !== false)
		{
			$forward_date .= ', '.my_date($mybb->settings['timeformat'], $pm['statustime']);
			$actioned_on = $lang->sprintf($lang->you_forwarded, $forward_date);
		}
		else
		{
			$forward_date .= ', '.my_date($mybb->settings['timeformat'], $pm['statustime']);
			$actioned_on = $lang->sprintf($lang->you_forwarded_on, $forward_date);
		}
		
		eval("\$action_time = \"".$templates->get("private_read_action")."\";");
	}

	$pm['userusername'] = $pm['username'];
	$pm['subject'] = htmlspecialchars_uni($parser->parse_badwords($pm['subject']));
	if($pm['fromid'] == 0)
	{
		$pm['username'] = 'MyBB Engine';
	}

	// Fetch the recipients for this message
	$pm['recipients'] = @unserialize($pm['recipients']);

	if(is_array($pm['recipients']['to']))
	{
		$uid_sql = implode(',', $pm['recipients']['to']);
	}
	else
	{
		$uid_sql = $pm['toid'];
		$pm['recipients']['to'] = array($pm['toid']);
	}

	$show_bcc = 0;

	// If we have any BCC recipients and this user is an Administrator, add them on to the query
	if(count($pm['recipients']['bcc']) > 0 && $mybb->usergroup['cancp'] == 1)
	{
		$show_bcc = 1;
		$uid_sql .= ','.implode(',', $pm['recipients']['bcc']);
	}
	
	// Fetch recipient names from the database
	$bcc_recipients = $to_recipients = array();
	$query = $db->simple_select('users', 'uid, username', "uid IN ({$uid_sql})");
	while($recipient = $db->fetch_array($query))
	{
		// User is a BCC recipient
		if($show_bcc && in_array($recipient['uid'], $pm['recipients']['bcc']))
		{
			$bcc_recipients[] = build_profile_link($recipient['username'], $recipient['uid']);
		}
		// User is a normal recipient
		else if(in_array($recipient['uid'], $pm['recipients']['to']))
		{
			$to_recipients[] = build_profile_link($recipient['username'], $recipient['uid']);
		}
	}

	if(count($bcc_recipients) > 0)
	{
		$bcc_recipients = implode(', ', $bcc_recipients);
		eval("\$bcc = \"".$templates->get("private_read_bcc")."\";");
	}

	$replyall = false;
	if(count($to_recipients) > 1)
	{
		$replyall = true;
	}
	
	if(count($to_recipients) > 0)
	{
		$to_recipients = implode(", ", $to_recipients);
	}
	else
	{
		$to_recipients = $lang->nobody;
	}

	eval("\$pm['subject_extra'] = \"".$templates->get("private_read_to")."\";");
	
	add_breadcrumb($pm['subject']);
	$message = build_postbit($pm, 2);
	eval("\$read = \"".$templates->get("private_read")."\";");
	$plugins->run_hooks("private_read_end");
	output_page($read);
}

if($mybb->input['action'] == "tracking")
{
	$plugins->run_hooks("private_tracking_start");
	$readmessages = '';
	$unreadmessages = '';
	
	$query = $db->query("
		SELECT pm.*, u.username as tousername
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.toid)
		WHERE receipt='2' AND folder!='3'  AND status!='0' AND fromid='".$mybb->user['uid']."'
		ORDER BY pm.readtime DESC
	");
	while($readmessage = $db->fetch_array($query))
	{
		$readmessage['subject'] = htmlspecialchars_uni($parser->parse_badwords($readmessage['subject']));
		$readmessage['profilelink'] = build_profile_link($readmessage['tousername'], $readmessage['toid']);
		$readdate = my_date($mybb->settings['dateformat'], $readmessage['readtime']);
		$readtime = my_date($mybb->settings['timeformat'], $readmessage['readtime']);
		eval("\$readmessages .= \"".$templates->get("private_tracking_readmessage")."\";");
	}
	
	if(!$readmessages)
	{
		eval("\$readmessages = \"".$templates->get("private_tracking_nomessage")."\";");
	}
	
	$query = $db->query("
		SELECT pm.*, u.username AS tousername
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.toid)
		WHERE receipt='1' AND folder!='3' AND status='0' AND fromid='".$mybb->user['uid']."'
		ORDER BY pm.dateline DESC
	");
	while($unreadmessage = $db->fetch_array($query))
	{
		$unreadmessage['subject'] = htmlspecialchars_uni($parser->parse_badwords($unreadmessage['subject']));
		$unreadmessage['profilelink'] = build_profile_link($unreadmessage['tousername'], $unreadmessage['toid']);		
		$senddate = my_date($mybb->settings['dateformat'], $unreadmessage['dateline']);
		$sendtime = my_date($mybb->settings['timeformat'], $unreadmessage['dateline']);
		eval("\$unreadmessages .= \"".$templates->get("private_tracking_unreadmessage")."\";");
	}
	
	if(!$unreadmessages)
	{
		$lang->no_readmessages = $lang->no_unreadmessages;
		eval("\$unreadmessages = \"".$templates->get("private_tracking_nomessage")."\";");
	}
	
	eval("\$tracking = \"".$templates->get("private_tracking")."\";");
	$plugins->run_hooks("private_tracking_end");
	output_page($tracking);
}
if($mybb->input['action'] == "do_tracking" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("private_do_tracking_start");
	
	if($mybb->input['stoptracking'])
	{
		if(is_array($mybb->input['readcheck']))
		{
			foreach($mybb->input['readcheck'] as $key => $val)
			{
				$sql_array = array(
					"receipt" => 0
				);
				$db->update_query("privatemessages", $sql_array, "pmid=".intval($key)." AND fromid=".$mybb->user['uid']);
			}
		}
		$plugins->run_hooks("private_do_tracking_end");
		redirect("private.php", $lang->redirect_pmstrackingstopped);
	}
	elseif($mybb->input['stoptrackingunread'])
	{
		if(is_array($mybb->input['unreadcheck']))
		{
			foreach($mybb->input['unreadcheck'] as $key => $val)
			{
				$sql_array = array(
					"receipt" => 0
				);
				$db->update_query("privatemessages", $sql_array, "pmid=".intval($key)." AND fromid=".$mybb->user['uid']);
			}
		}
		$plugins->run_hooks("private_do_tracking_end");
		redirect("private.php", $lang->redirect_pmstrackingstopped);
	}
	elseif($mybb->input['cancel'])
	{
		if(is_array($mybb->input['unreadcheck']))
		{
			foreach($mybb->input['unreadcheck'] as $pmid => $val)
			{
				$pmids[$pmid] = intval($pmid);
			}
			
			$pmids = implode(",", $pmids);
			$query = $db->simple_select("privatemessages", "uid", "pmid IN ($pmids) AND fromid='".$mybb->user['uid']."'");
			while($pm = $db->fetch_array($query))
			{
				$pmuids[$pm['uid']] = $pm['uid'];
			}
			
			$db->delete_query("privatemessages", "pmid IN ($pmids) AND receipt='1' AND status='0' AND fromid='".$mybb->user['uid']."'");
			foreach($pmuids as $uid)
			{
				// Message is canceled, update PM count for this user
				update_pm_count($uid);
			}
		}
		$plugins->run_hooks("private_do_tracking_end");
		redirect("private.php", $lang->redirect_pmstrackingcanceled);
	}
}

if($mybb->input['action'] == "folders")
{
	$plugins->run_hooks("private_folders_start");
	
	$folderlist = '';	
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$foldername = $folderinfo[1];
		$fid = $folderinfo[0];
		$foldername = get_pm_folder_name($fid, $foldername);
		
		if($folderinfo[0] == "1" || $folderinfo[0] == "2" || $folderinfo[0] == "3" || $folderinfo[0] == "4")
		{
			$foldername2 = get_pm_folder_name($fid);
			eval("\$folderlist .= \"".$templates->get("private_folders_folder_unremovable")."\";");
			unset($name);
		}
		else
		{
			eval("\$folderlist .= \"".$templates->get("private_folders_folder")."\";");
		}
	}
	
	$newfolders = '';
	for($i = 1; $i <= 5; ++$i)
	{
		$fid = "new$i";
		$foldername = '';
		eval("\$newfolders .= \"".$templates->get("private_folders_folder")."\";");
	}
	
	eval("\$folders = \"".$templates->get("private_folders")."\";");
	$plugins->run_hooks("private_folders_end");
	output_page($folders);
}

if($mybb->input['action'] == "do_folders" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("private_do_folders_start");
	
	$highestid = 2;
	$folders = '';
	@reset($mybb->input['folder']);
	foreach($mybb->input['folder'] as $key => $val)
	{
		if(!$donefolders[$val]) // Probably was a check for duplicate folder names, but doesn't seem to be used now 
		{
			if(my_substr($key, 0, 3) == "new") // Create a new folder
			{
				++$highestid;
				$fid = intval($highestid);
			}
			else // Editing an existing folder
			{
				if($key > $highestid)
				{
					$highestid = $key;
				}
				
				$fid = intval($key);
				// Use default language strings if empty or value is language string
				switch($fid)
				{
					case 1:
						if($val == $lang->folder_inbox || trim($val) == '')
						{
							$val = '';
						}
						break;
					case 2:
						if($val == $lang->folder_sent_items || trim($val) == '')
						{
							$val = '';
						}
						break;
					case 3:
						if($val == $lang->folder_drafts || trim($val) == '')
						{
							$val = '';
						}
						break;
					case 4:
						if($val == $lang->folder_trash || trim($val) == '')
						{
							$val = '';
						}
						break;
				}
			}
			
			if($val != '' && trim($val) == '' && !($key >= 1 && $key <= 4))
			{
				// If the name only contains whitespace and it's not a default folder, print an error
				error($lang->error_emptypmfoldername);
			}
			
			
			if($val != '' || ($key >= 1 && $key <= 4))
			{
				// If there is a name or if this is a default folder, save it 
				$foldername = $val;
				$foldername = $db->escape_string(htmlspecialchars_uni($foldername));
				
				if(my_strpos($foldername, "$%%$") === false)
				{
					if($folders != '')
					{
						$folders .= "$%%$";
					}
					$folders .= "$fid**$foldername";
				}
				else
				{
					error($lang->error_invalidpmfoldername);
				}
			}
			else
			{
				// Delete PMs from the folder
				$db->delete_query("privatemessages", "folder='$fid' AND uid='".$mybb->user['uid']."'");
			}
		}
	}

	$sql_array = array(
		"pmfolders" => $folders
	);	
	$db->update_query("users", $sql_array, "uid='".$mybb->user['uid']."'");
	
	// Update PM count
	update_pm_count();
	
	$plugins->run_hooks("private_do_folders_end");
	
	redirect("private.php", $lang->redirect_pmfoldersupdated);
}

if($mybb->input['action'] == "empty")
{
	$plugins->run_hooks("private_empty_start");
	
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	$folderlist = '';
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$fid = $folderinfo[0];
		$foldername = get_pm_folder_name($fid, $folderinfo[1]);
		$query = $db->simple_select("privatemessages", "COUNT(*) AS pmsinfolder", " folder='$fid' AND uid='".$mybb->user['uid']."'");
		$thing = $db->fetch_array($query);
		$foldercount = my_number_format($thing['pmsinfolder']);
		eval("\$folderlist .= \"".$templates->get("private_empty_folder")."\";");
	}
	
	eval("\$folders = \"".$templates->get("private_empty")."\";");
	$plugins->run_hooks("private_empty_end");
	output_page($folders);
}

if($mybb->input['action'] == "do_empty" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("private_do_empty_start");
	
	$emptyq = '';
	if(is_array($mybb->input['empty']))
	{
		foreach($mybb->input['empty'] as $key => $val)
		{
			if($val == 1)
			{
				$key = intval($key);
				if($emptyq)
				{
					$emptyq .= " OR ";
				}
				$emptyq .= "folder='$key'";
			}
		}
		
		if($emptyq != '')
		{
			if($mybb->input['keepunread'] == 1)
			{
				$keepunreadq = " AND status!='0'";
			}
			$db->delete_query("privatemessages", "($emptyq) AND uid='".$mybb->user['uid']."' $keepunreadq");
		}
	}
	
	// Update PM count
	update_pm_count();

	$plugins->run_hooks("private_do_empty_end");
	redirect("private.php", $lang->redirect_pmfoldersemptied);
}

if($mybb->input['action'] == "do_stuff" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("private_do_stuff");
	
	if($mybb->input['hop'])
	{
		header("Location: private.php?fid=".intval($mybb->input['jumpto']));
	}
	elseif($mybb->input['moveto'])
	{
		if(is_array($mybb->input['check']))
		{
			foreach($mybb->input['check'] as $key => $val)
			{
				$sql_array = array(
					"folder" => intval($mybb->input['fid'])
				);
				$db->update_query("privatemessages", $sql_array, "pmid='".intval($key)."' AND uid='".$mybb->user['uid']."'");
			}
		}
		// Update PM count
		update_pm_count();

		if(!empty($mybb->input['fromfid']))
		{
			redirect("private.php?fid=".intval($mybb->input['fromfid']), $lang->redirect_pmsmoved);
		}
		else
		{
			redirect("private.php", $lang->redirect_pmsmoved);
		}
	}
	else if($mybb->input['delete'])
	{
		if(is_array($mybb->input['check']))
		{
			$pmssql = '';
			foreach($mybb->input['check'] as $key => $val)
			{
				if($pmssql)
				{
					$pmssql .= ",";
				}
				$pmssql .= "'".intval($key)."'";
			}
			
			$query = $db->simple_select("privatemessages", "pmid, folder", "pmid IN ($pmssql) AND uid='".$mybb->user['uid']."' AND folder='4'", array('order_by' => 'pmid'));
			while($delpm = $db->fetch_array($query))
			{
				$deletepms[$delpm['pmid']] = 1;
			}
			
			reset($mybb->input['check']);
			foreach($mybb->input['check'] as $key => $val)
			{
				$key = intval($key);
				if($deletepms[$key])
				{
					$db->delete_query("privatemessages", "pmid='$key' AND uid='".$mybb->user['uid']."'");
				}
				else
				{
					$sql_array = array(
						"folder" => 4,
						"deletetime" => TIME_NOW
					);
					$db->update_query("privatemessages", $sql_array, "pmid='".$key."' AND uid='".$mybb->user['uid']."'");
				}
			}
		}
		// Update PM count
		update_pm_count();

		redirect("private.php", $lang->redirect_pmsdeleted);
	}
}

if($mybb->input['action'] == "delete")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("private_delete_start");

	$query = $db->simple_select("privatemessages", "*", "pmid='".intval($mybb->input['pmid'])."' AND uid='".$mybb->user['uid']."' AND folder='4'", array('order_by' => 'pmid'));
	if($db->num_rows($query) == 1)
	{
		$db->delete_query("privatemessages", "pmid='".intval($mybb->input['pmid'])."'");
	}
	else
	{
		$sql_array = array(
			"folder" => 4,
			"deletetime" => TIME_NOW
		);
		$db->update_query("privatemessages", $sql_array, "pmid='".intval($mybb->input['pmid'])."' AND uid='".$mybb->user['uid']."'");
	}

	// Update PM count
	update_pm_count();

	$plugins->run_hooks("private_delete_end");
	redirect("private.php", $lang->redirect_pmsdeleted);
}

if($mybb->input['action'] == "export")
{
	$plugins->run_hooks("private_export_start");
	
	$folderlist = "<select name=\"exportfolders[]\" multiple=\"multiple\">\n";
	$folderlist .= "<option value=\"all\" selected=\"selected\">$lang->all_folders</option>";
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
		$folderlist .= "<option value=\"$folderinfo[0]\">$folderinfo[1]</option>\n";
	}
	$folderlist .= "</select>\n";
	eval("\$archive = \"".$templates->get("private_archive")."\";");
	
	$plugins->run_hooks("private_export_end");
	
	output_page($archive);
}

if($mybb->input['action'] == "do_export" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("private_do_export_start");
	
	$lang->private_messages_for = $lang->sprintf($lang->private_messages_for, $mybb->user['username']);
	$exdate = my_date($mybb->settings['dateformat'], TIME_NOW, 0, 0);
	$extime = my_date($mybb->settings['timeformat'], TIME_NOW, 0, 0);
	$lang->exported_date = $lang->sprintf($lang->exported_date, $exdate, $extime);
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
		$foldersexploded[$key] = implode("**", $folderinfo);
	}
	
	if($mybb->input['pmid'])
	{
		$wsql = "pmid='".intval($mybb->input['pmid'])."' AND uid='".$mybb->user['uid']."'";
	}
	else
	{
		if($mybb->input['daycut'] && ($mybb->input['dayway'] != "disregard"))
		{
			$datecut = TIME_NOW-($mybb->input['daycut'] * 86400);
			$wsql = "pm.dateline";
			if($mybb->input['dayway'] == "older")
			{
				$wsql .= "<=";
			}
			else
			{
				$wsql .= ">=";
			}
			$wsql .= "'$datecut'";
		}
		else
		{
			$wsql = "1=1";
		}
		
		if(is_array($mybb->input['exportfolders']))
		{
			$folderlst = '';
			reset($mybb->input['exportfolders']);
			foreach($mybb->input['exportfolders'] as $key => $val)
			{
				$val = $db->escape_string($val);
				if($val == "all")
				{
					$folderlst = '';
					break;
				}
				else
				{
					if(!$folderlst)
					{
						$folderlst = " AND pm.folder IN ('$val'";
					}
					else
					{
						$folderlst .= ",'$val'";
					}
				}
			}
			if($folderlst)
			{
				$folderlst .= ")";
			}
			$wsql .= "$folderlst";
		}
		else
		{
			error($lang->error_pmnoarchivefolders);
		}
		
		if($mybb->input['exportunread'] != 1)
		{
			$wsql .= " AND pm.status!='0'";
		}
	}
	$query = $db->query("
		SELECT pm.*, fu.username AS fromusername, tu.username AS tousername
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users fu ON (fu.uid=pm.fromid)
		LEFT JOIN ".TABLE_PREFIX."users tu ON (tu.uid=pm.toid)
		WHERE $wsql AND pm.uid='".$mybb->user['uid']."'
		ORDER BY pm.folder ASC, pm.dateline DESC
	");
	$numpms = $db->num_rows($query);
	if(!$numpms)
	{
		error($lang->error_nopmsarchive);
	}
	
	$pmsdownload = '';
	while($message = $db->fetch_array($query))
	{
		if($message['folder'] == 2 || $message['folder'] == 3)
		{ // Sent Items or Drafts Folder Check
			if($message['toid'])
			{
				$tofromuid = $message['toid'];
				if($mybb->input['exporttype'] == "txt")
				{
					$tofromusername = $message['tousername'];
				}
				else
				{
					$tofromusername = build_profile_link($message['tousername'], $tofromuid);
				}
			}
			else
			{
				$tofromusername = $lang->not_sent;
			}
			$tofrom = $lang->to;
		}
		else
		{
			$tofromuid = $message['fromid'];
			if($mybb->input['exporttype'] == "txt")
			{
				$tofromusername = $message['fromusername'];
			}
			else
			{
				$tofromusername = build_profile_link($message['fromusername'], $tofromuid);
			}
			
			if($tofromuid == 0)
			{
				$tofromusername = "MyBB Engine";
			}
			$tofrom = $lang->from;
		}
		
		if($tofromuid == 0)
		{
			$message['fromusername'] = "MyBB Engine";
		}
		
		if(!$message['toid'] && $message['folder'] == 3)
		{
			$message['tousername'] = $lang->not_sent;
		}

		$message['subject'] = $parser->parse_badwords($message['subject']);
		if($message['folder'] != "3")
		{
			$senddate = my_date($mybb->settings['dateformat'], $message['dateline']);
			$sendtime = my_date($mybb->settings['timeformat'], $message['dateline']);
			$senddate .= " $lang->at $sendtime";
		}
		else
		{
			$senddate = $lang->not_sent;
		}
		
		if($mybb->input['exporttype'] == "html")
		{
			$parser_options = array(
				"allow_html" => $mybb->settings['pmsallowhtml'],
				"allow_mycode" => $mybb->settings['pmsallowmycode'],
				"allow_smilies" => 0,
				"allow_imgcode" => $mybb->settings['pmsallowimgcode'],
				"me_username" => $mybb->user['username'],
				"filter_badwords" => 1
			);

			$message['message'] = $parser->parse_message($message['message'], $parser_options);
			$message['subject'] = htmlspecialchars_uni($message['subject']);
		}
		
		if($mybb->input['exporttype'] == "txt" || $mybb->input['exporttype'] == "csv")
		{
			$message['message'] = str_replace("\r\n", "\n", $message['message']);
			$message['message'] = str_replace("\n", "\r\n", $message['message']);
		}
		
		if($mybb->input['exporttype'] == "csv")
		{
			$message['message'] = addslashes($message['message']);
			$message['subject'] = addslashes($message['subject']);
			$message['tousername'] = addslashes($message['tousername']);
			$message['fromusername'] = addslashes($message['fromusername']);
		}
		
		
		if(!$donefolder[$message['folder']])
		{
			reset($foldersexploded);
			foreach($foldersexploded as $key => $val)
			{
				$folderinfo = explode("**", $val, 2);
				if($folderinfo[0] == $message['folder'])
				{
					$foldername = $folderinfo[1];
					if($mybb->input['exporttype'] != "csv")
					{
						if($mybb->input['exporttype'] != "html")
						{
							$mybb->input['exporttype'] == "txt";
						}
						eval("\$pmsdownload .= \"".$templates->get("private_archive_".$mybb->input['exporttype']."_folderhead", 1, 0)."\";");
					}
					else
					{
						$foldername = addslashes($folderinfo[1]);
					}
					$donefolder[$message['folder']] = 1;
				}
			}
		}
		
		eval("\$pmsdownload .= \"".$templates->get("private_archive_".$mybb->input['exporttype']."_message", 1, 0)."\";");
		$ids .= ",'{$message['pmid']}'";
	}
	
	$query = $db->simple_select("themestylesheets", "stylesheet", "sid=1", array('limit' => 1));
	$css = $db->fetch_field($query, "stylesheet");

	eval("\$archived = \"".$templates->get("private_archive_".$mybb->input['exporttype'], 1, 0)."\";");
	if($mybb->input['deletepms'] == 1)
	{ // delete the archived pms
		$db->delete_query("privatemessages", "pmid IN ('0'$ids)");
		// Update PM count
		update_pm_count();
	}
	
	if($mybb->input['exporttype'] == "html")
	{
		$filename = "pm-archive.html";
		$contenttype = "text/html";
	}
	elseif($mybb->input['exporttype'] == "csv")
	{
		$filename = "pm-archive.csv";
		$contenttype = "application/octet-stream";
	}
	else
	{
		$filename = "pm-archive.txt";
		$contenttype = "text/plain";
	}
	
	$archived = str_replace("\\\'","'",$archived);
	header("Content-disposition: filename=$filename");
	header("Content-type: ".$contenttype);
	
	$plugins->run_hooks("private_do_export_end");
	
	if($mybb->input['exporttype'] == "html")
	{
		output_page($archived);
	}
	else
	{
		echo $archived;
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("private_start");
	
	if(!$mybb->input['fid'])
	{
		$mybb->input['fid'] = 1;
	}
	
	$folder = '';
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		if($folderinfo[0] == $mybb->input['fid'])
		{
			$folder = $folderinfo[0];
			$foldername = get_pm_folder_name($folder, $folderinfo[1]);
		}
	}

	$lang->pms_in_folder = $lang->sprintf($lang->pms_in_folder, $foldername);
	if($folder == 2 || $folder == 3)
	{ // Sent Items Folder
		$sender = $lang->sentto;
	}
	else
	{
		$sender = $lang->sender;
	}

	// Do Multi Pages
	$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "uid='".$mybb->user['uid']."' AND folder='$folder'");
	$pmscount = $db->fetch_array($query);

	if(!$mybb->settings['threadsperpage'])
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	$perpage = $mybb->settings['threadsperpage'];
	$page = intval($mybb->input['page']);
	
	if(intval($mybb->input['page']) > 0)
	{
		$start = ($page-1) *$perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	
	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;
	
	if($upper > $threadcount)
	{
		$upper = $threadcount;
	}
	$multipage = multipage($pmscount['total'], $perpage, $page, "private.php?fid=$folder");
	$messagelist = '';
	
	$icon_cache = $cache->read("posticons");
	
	// Cache users in multiple recipients for sent & drafts folder
	if($folder == 2 || $folder == 3)
	{		
		// Get all recipients into an array
		$cached_users = $get_users = array();
		$users_query = $db->simple_select("privatemessages", "recipients", "folder='$folder' AND uid='{$mybb->user['uid']}'", array('limit_start' => $start, 'limit' => $perpage, 'order_by' => 'dateline', 'order_dir' => 'DESC'));
		while($row = $db->fetch_array($users_query))
		{
			$recipients = unserialize($row['recipients']);
			if(is_array($recipients['to']) && count($recipients['to']))
			{
				$get_users = array_merge($get_users, $recipients['to']);
			}
			
			if(is_array($recipients['bcc']) && count($recipients['bcc']))
			{
				$get_users = array_merge($get_users, $recipients['bcc']);
			}
		}
		
		$get_users = implode(',', array_unique($get_users));
		
		// Grab info
		if($get_users)
		{
			$users_query = $db->simple_select("users", "uid, username, usergroup, displaygroup", "uid IN ({$get_users})");
			while($user = $db->fetch_array($users_query))
			{
				$cached_users[$user['uid']] = $user;
			}
		}
	}
	
	$query = $db->query("
		SELECT pm.*, fu.username AS fromusername, tu.username as tousername
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users fu ON (fu.uid=pm.fromid)
		LEFT JOIN ".TABLE_PREFIX."users tu ON (tu.uid=pm.toid)
		WHERE pm.folder='$folder' AND pm.uid='".$mybb->user['uid']."'
		ORDER BY pm.dateline DESC
		LIMIT $start, $perpage
	");
		
	if($db->num_rows($query) > 0)
	{
		while($message = $db->fetch_array($query))
		{
			$msgalt = $msgsuffix = $msgprefix = '';
			// Determine Folder Icon
			if($message['status'] == 0)
			{
				$msgfolder = 'new_pm.gif';
				$msgalt = $lang->new_pm;
				$msgprefix = "<strong>";
				$msgsuffix = "</strong>";
			}
			elseif($message['status'] == 1)
			{
				$msgfolder = 'old_pm.gif';
				$msgalt = $lang->old_pm;
			}
			elseif($message['status'] == 3)
			{
				$msgfolder = 're_pm.gif';
				$msgalt = $lang->reply_pm;
			}
			elseif($message['status'] == 4)
			{
				$msgfolder = 'fw_pm.gif';
				$msgalt = $lang->fwd_pm;
			}
			
			if($folder == 2 || $folder == 3)
			{ // Sent Items or Drafts Folder Check
				$recipients = unserialize($message['recipients']);
				$to_users = $bcc_users = '';
				if(count($recipients['to']) > 1 || (count($recipients['to']) == 1 && count($recipients['bcc']) > 0))
				{
					foreach($recipients['to'] as $uid)
					{
						$profilelink = get_profile_link($uid);
						$user = $cached_users[$uid];
						$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
						eval("\$to_users .= \"".$templates->get("private_multiple_recipients_user")."\";"); 
					}
					if(is_array($recipients['bcc']) && count($recipients['bcc']))
					{
						eval("\$bcc_users = \"".$templates->get("private_multiple_recipients_bcc")."\";");
						foreach($recipients['bcc'] as $uid)
						{
							$profilelink = get_profile_link($uid);
							$user = $cached_users[$uid];
							$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
							eval("\$bcc_users .= \"".$templates->get("private_multiple_recipients_user")."\";"); 
						}
					}
					
					eval("\$tofromusername = \"".$templates->get("private_multiple_recipients")."\";");
				}
				else if($message['toid'])
				{
					$tofromusername = $message['tousername'];
					$tofromuid = $message['toid'];
				}
				else
				{
					$tofromusername = $lang->not_sent;
				}
			}
			else
			{
				$tofromusername = $message['fromusername'];
				$tofromuid = $message['fromid'];
				if($tofromuid == 0)
				{
					$tofromusername = 'MyBB Engine';
				}
			}
			
			$tofromusername = build_profile_link($tofromusername, $tofromuid);
			
			if($mybb->usergroup['cantrackpms'] == 1 && $mybb->usergroup['candenypmreceipts'] == 1 && $message['receipt'] == '1' && $message['folder'] != '3' && $message['folder'] != 2)
			{
				eval("\$denyreceipt = \"".$templates->get("private_messagebit_denyreceipt")."\";");
			}
			else
			{
				$denyreceipt = '';
			}
			
			if($message['icon'] > 0 && $icon_cache[$message['icon']])
			{
				$icon = $icon_cache[$message['icon']];
				$icon = "<img src=\"{$icon['path']}\" alt=\"{$icon['name']}\" align=\"center\" valign=\"middle\" />";
			}
			else
			{
				$icon = '&nbsp;';
			}
			
			if(!trim($message['subject']))
			{
				$message['subject'] = $lang->pm_no_subject;
			}

			$message['subject'] = htmlspecialchars_uni($parser->parse_badwords($message['subject']));
			if($message['folder'] != "3")
			{
				$sendpmdate = my_date($mybb->settings['dateformat'], $message['dateline']);
				$sendpmtime = my_date($mybb->settings['timeformat'], $message['dateline']);
				$senddate = $sendpmdate.", ".$sendpmtime;
			}
			else
			{
				$senddate = $lang->not_sent;
			}
			eval("\$messagelist .= \"".$templates->get("private_messagebit")."\";");
		}
	}
	else
	{
		eval("\$messagelist .= \"".$templates->get("private_nomessages")."\";");
	}

	if($mybb->usergroup['pmquota'] != '0' && $mybb->usergroup['cancp'] != 1)
	{
		$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "uid='".$mybb->user['uid']."'");
		$pmscount = $db->fetch_array($query);
		if($pmscount['total'] == 0)
		{
			$spaceused = 0;
		}
		else
		{
			$spaceused = $pmscount['total'] / $mybb->usergroup['pmquota'] * 100;
		}
		$spaceused2 = 100 - $spaceused;
		if($spaceused <= "50")
		{
			$belowhalf = round($spaceused, 0)."%";
			if(intval($belowhalf) > 100)
			{
				$belowhalf = "100%";
			}
		}
		else
		{
			$overhalf = round($spaceused, 0)."%";
			if(intval($overhalf) > 100)
			{
				$overhalf = "100%";
			}
		}
		
		eval("\$pmspacebar = \"".$templates->get("private_pmspace")."\";");
	}
	
	if($mybb->usergroup['pmquota'] != "0" && $pmscount['total'] >= $mybb->usergroup['pmquota'] && $mybb->usergroup['cancp'] != 1)
	{
		eval("\$limitwarning = \"".$templates->get("private_limitwarning")."\";");
	}
	
	eval("\$folder = \"".$templates->get("private")."\";");
	$plugins->run_hooks("private_end");
	output_page($folder);
}
?>
