<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

$templatelist = "private_send,private_send_buddyselect,private_read,private_tracking,private_tracking_readmessage,private_tracking_unreadmessage";
$templatelist .= ",private_folders,private_folders_folder,private_folders_folder_unremovable,private,usercp_nav_changename,usercp_nav,private_empty_folder,private_empty,posticons";
$templatelist .= "usercp_nav_messenger,usercp_nav_changename,usercp_nav_profile,usercp_nav_misc";

require "./global.php";
require_once "./inc/functions_post.php";
require_once "./inc/functions_user.php";
require_once "./inc/class_parser.php";
$parser = new postParser;

$autocomplete = "on";
// Autocomplete for buddy list when composing PM's
if($mybb->input['action'] == "getbuddies" && $mybb->user['uid'])
{
	if($mybb->user['buddylist'])
	{
		header("Content-Type: text/xml");
		$users = "";
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username LIKE '".addslashes($mybb->input['query'])."%' AND uid IN (".$mybb->user[buddylist].") ORDER BY username ASC");
		while($user = $db->fetch_array($query))
		{
			$users .= "<li>".$user['username']."</li>";
		}
		if($users)
		{
			echo "<ul>$users</ul>";
		}
	}
	exit;
}

// Load global language phrases
$lang->load("private");

if($mybb->user['uid'] == "/" || $mybb->user['uid'] == "0" || $mybb->usergroup['canusepms'] == "no")
{
	nopermission();
}
if($mybb->user['receivepms'] == "no")
{
	error($lang->error_pmsturnedoff);
}

if(!$mybb->user['pmfolders'])
{
	$mybb->user['pmfolders'] = "1**Inbox$%%$2**Sent Items$%%$3**Drafts$%%$4**Trash Can";
	
	$sql_array = array(
		 "pmfolders" => $mybb->user['pmfolders']
	);
	$db->update_query(TABLE_PREFIX."users", $sql_array, "uid = ".$mybb->user['uid']);
}

// On a random occassion, recount the users pm's just to make sure everything is in sync.
if($rand == 5)
{
	update_pm_count();
}

$timecut = time()-(60*60*24*7);
$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE dateline<=$timecut AND folder='4' AND uid='".$mybb->user['uid']."'");


$folderjump = "<select name=\"jumpto\">\n";
$folderoplist = "<select name=\"fid\">\n";
$folderjump2 = "<select name=\"jumpto2\">\n";

$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
while(list($key, $folders) = each($foldersexploded))
{
	$folderinfo = explode("**", $folders, 2);
	if($fid == $folderinfo[0])
	{
		$sel = "selected";
	}
	else
	{
		$sel = "";
	}
	$folderjump .= "<option value=\"$folderinfo[0]\" $sel>$folderinfo[1]</option>\n";
	$folderjump2 .= "<option value=\"$folderinfo[0]\" $sel>$folderinfo[1]</option>\n";
	$folderoplist .= "<option value=\"$folderinfo[0]\" $sel>$folderinfo[1]</option>\n";
	$folderlinks .= "&#149;&nbsp;<a href=\"private.php?fid=$folderinfo[0]\">$folderinfo[1]</a><br />\n";
}
$folderjump .= "</select>\n";
$folderjump2 .= "</select>\n";
$folderoplist .= "</select>\n";

usercp_menu();


// Make navigation
addnav($lang->nav_pms, "private.php");

switch($mybb->input['action']) {
	case "send":
		addnav($lang->nav_send);
		break;
	case "tracking":
		addnav($lang->nav_tracking);
		break;
	case "folders":
		addnav($lang->nav_folders);
		break;
	case "empty":
		addnav($lang->nav_empty);
		break;
	case "export":
		addnav($lang->nav_export);
		break;
}
if($mybb->input['preview'])
{
	$mybb->input['action'] = "send";
}
if($mybb->input['action'] == "send")
{

	$plugins->run_hooks("private_send_start");

	if($mybb->settings['bbcodeinserter'] != "off" && $mybb->settings['pmsallowmycode'] != "no" && $mybb->user['showcodebuttons'] != 0)
	{
		$codebuttons = makebbcodeinsert();
	}
	if($mybb->settings['pmsallowsmilies'] != "no")
	{
		$smilieinserter = makesmilieinsert();
	}

	$posticons = getposticons();
	$previewmessage = $mybb->input['message'];
	$message = htmlspecialchars_uni($mybb->input['message']);

	if($mybb->input['preview'])
	{
		$query = $db->query("SELECT u.username AS userusername, u.*, f.*, i.path as iconpath, i.name as iconname, g.title AS grouptitle, g.usertitle AS groupusertitle, g.namestyle, g.stars AS groupstars, g.starimage AS groupstarimage, g.image AS groupimage, g.usereputationsystem FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid='".intval($mybb->input['icon'])."') LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup) WHERE u.uid='".$mybb->user[uid]."'");
		$post = $db->fetch_array($query);
		$post['userusername'] = $mybb->user['username'];
		$post['postusername'] = $mybb->user['username'];
		$post['message'] = $previewmessage;
		$post['subject'] = $mybb->input['subject'];
		$post['icon'] = $mybb->input['icon'];
		$post['dateline'] = time();
		$postbit = makepostbit($post, 2);
		eval("\$preview = \"".$templates->get("previewpost")."\";");

		$options = $mybb->input['options'];
		if($options['signature'] == "yes")
		{
			$optionschecked['signature'] = "checked";
		}
		if($options['disablesmilies'] == "yes")
		{
			$optionschecked['disablesmilies'] = "checked";
		}
		if($options['savecopy'] != "no")
		{
			$optionschecked['savecopy'] = "checked";
		}
		if($options['readreceipt'] != "no")
		{
			$optionschecked['readreceipt'] = "checked";
		}
		$to = htmlspecialchars_uni($mybb->input['to']);
		$subject = htmlspecialchars_uni($mybb->input['subject']);
	}
	else
	{
		if($mybb->user['signature'] != "")
		{
			$optionschecked['signature'] = "checked";
		}
		if($mybb->usergroup['cantrackpms'] == "yes")
		{
			$optionschecked['readreceipt'] = "checked";
		}
		$optionschecked['savecopy'] = "checked";
	}
	if($mybb->input['pmid'] && !$mybb->input['preview'])
	{
		$query = $db->query("SELECT pm.*, u.username AS quotename FROM ".TABLE_PREFIX."privatemessages pm LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.fromid) WHERE pm.pmid='".intval($mybb->input['pmid'])."' AND pm.uid='".$mybb->user[uid]."'");
		$pm = $db->fetch_array($query);
		$message = $pm['message'];
		$message = htmlspecialchars_uni($message);
		$subject = $pm['subject'];
		$subject = htmlspecialchars_uni($subject);
		if($pm['folder'] == "3")
		{ // message saved in drafts
			$mybb->input['uid'] = $pm['toid'];
			if($pm['includesig'] == "yes")
			{
				$optionschecked['signature'] = "checked";
			}
			if($pm['smilieoff'] == "yes")
			{
				$optionschecked['disablesmilies'] = "checked";
			}
			if($pm['receipt'])
			{
				$optionschecked['readreceipt'] = "checked";
			}
		}
		else
		{
			$subject = preg_replace("#(FW|RE):( *)#is", "", $subject);
			$postdate = mydate($mybb->settings['dateformat'], $pm['dateline']);
			$posttime = mydate($mybb->settings['timeformat'], $pm['dateline']);
			$message = "[quote=$pm[quotename]]\n$message\n[/quote]";
			$quoted['message'] = preg_replace('#^/me (.*)$#im', "* ".$pm['quotename']." \\1", $quoted['message']);

			if($mybb->input['do'] == "forward")
			{
				$subject = "Fw: $subject";
			}
			elseif($mybb->input['do'] == "reply")
			{
				$subject = "Re: $subject";
				$uid = $pm['fromid'];
				$query = $db->query("SELECT username FROM ".TABLE_PREFIX."users WHERE uid='".$uid."'");
				$user = $db->fetch_array($query);
				$to = $user['username'];
			}
		}
	}
	if($mybb->input['uid'] && !$mybb->input['preview'])
	{
	$query = $db->query("SELECT username FROM ".TABLE_PREFIX."users WHERE uid='".intval($mybb->input['uid'])."'");
	$user = $db->fetch_array($query);
	$to = $user['username'];
	}
	if($autocomplete)
	{
	}
	// Load Buddys
	$buddies = $mybb->user['buddylist'];
	$namesarray = explode(",",$buddies);
	if(is_array($namesarray))
	{
		while(list($key, $buddyid) = each($namesarray))
		{
			$sql .= "$comma'$buddyid'";
			$comma = ",";
		}
		$timecut = time() - $mybb->settings['wolcutoff'];
		$query = $db->query("SELECT u.*, g.canusepms FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup) WHERE u.uid IN ($sql)");
		$buddies = "";
		while($buddy = $db->fetch_array($query))
		{
			if($mybb->user['receivepms'] != "no" && $buddy['receivepms'] != "no" && $buddy['canusepms'] != "no")
			{
				$buddies .= "<option value=\"$buddy[username]\">$buddy[username]</option>\n";
			}
		}
		if($buddies)
		{
			eval("\$buddyselect = \"".$templates->get("private_send_buddyselect")."\";");
		}
	}
	$pmid = $mybb->input['pmid'];
	$do = $mybb->input['do'];
	eval("\$send = \"".$templates->get("private_send")."\";");
	$plugins->run_hooks("private_send_end");
	outputpage($send);
}
elseif($mybb->input['action'] == "do_send" && $mybb->request_method == "post")
{
	$plugins->run_hooks("private_send_do_send");

	if($mybb->input['subject'] == "")
	{
		$mybb->input['subject'] = "[no subject]";
	}
	if(strlen($mybb->input['subject']) > 85)
	{
		error($lang->error_subjecttolong);
	}
	if(trim($mybb->input['message']) == "")
	{
		error($lang->error_pmnomessage);
	}
	$query = $db->query("SELECT u.uid, u.username, u.email, u.usergroup, u.pmnotify, u.pmpopup, u.receivepms, u.ignorelist, u.lastactive, u.language, COUNT(pms.pmid) AS pms_total, g.canusepms, g.pmquota, g.cancp  FROM (".TABLE_PREFIX."users u, ".TABLE_PREFIX."usergroups g) LEFT JOIN ".TABLE_PREFIX."privatemessages pms ON (pms.uid=u.uid) WHERE u.username='".addslashes($mybb->input['to'])."' AND g.gid=u.usergroup GROUP BY u.uid");
	$touser = $db->fetch_array($query);

	if(!$touser['uid'] && !$mybb->input['saveasdraft'])
	{
		error($lang->error_invalidpmrecipient);
	}
	$ignorelist = explode(",", $touser['ignorelist']);
	while(list($key, $uid) = each($ignorelist))
	{
		if($uid == $mybb->user['uid'] && $mybb->usergroup['cancp'] != "yes")
		{
			$nosend = true;
			break;
		}
	}
	$lang->error_recipientpmturnedoff = sprintf($lang->error_recipientpmturnedoff, $mybb->input['to']);
	$lang->error_recipientignoring = sprintf($lang->error_recipientignoring, $mybb->input['to']);
	if($nosend)
	{
		error($lang->error_recipientignoring);
	}
	if($touser['receivepms'] == "no" || $touser['canusepms'] == "no" && !$mybb->input['saveasdraft'])
	{
		error($lang->error_recipientpmturnedoff);
	}
	$options = $mybb->input['options'];
	if($options['signature'] != "yes")
	{
		$options['signature'] = "no";
	}
	if($options['disablesmilies'] != "yes")
	{
		$options['disablesmilies'] = "no";
	}
	if($options['savecopy'] != "yes")
	{
		$options['savecopy'] = "no";
	}
	if($options['readreceipt'] != "yes")
	{
		$options['readreceipt'] = "0";
	}
	else
	{
		$options['readreceipt'] = "1";
	}
	if($touser['pmquota'] != "0" && $touser['pms_total'] >= $touser['pmquota'] && $touser['cancp'] != "yes" && $mybb->usergroup['cancp'] != "yes" && !$mybb->input['saveasdraft'])
	{
		if(trim($touser['language']) != "" && $lang->languageExists($touser['language']))
		{
			$uselang = trim($touser['language']);
		}
		elseif($mybb->settings['bblanguage'])
		{
			$uselang = $mybb->settings['language'];
		}
		else
		{
			$uselang = "english";
		}
		if($uselang == $mybb->settings['bblanguage'] || !$uselang)
		{
			$emailsubject = $lang->emailsubject_reachedpmquota;
			$emailmessage = $lang->email_reachedpmquota;
		}
		else
		{
			$userlang = new MyLanguage;
			$userlang->setPath("./inc/languages");
			$userlang->setLanguage($uselang);
			$userlang->load("messages");
			$emailsubject = $userlang->emailsubject_reachedpmquota;
			$emailmessage = $userlang->email_reachedpmquota;
		}
		$emailmessage = sprintf($emailmessage, $touser['username'], $mybb->settings['bbname'], $mybb->settings['bburl']);
		$emailsubject = sprintf($emailsubject, $mybb->settings['bbname']);
		mymail($touser['email'], $emailsubject, $emailmessage);
		error(sprintf($lang->error_pmrecipientreachedquota, $touser['username']));
	}
	$query = $db->query("SELECT dateline FROM ".TABLE_PREFIX."privatemessages WHERE uid='".$touser['uid']."' AND folder='1' ORDER BY dateline DESC LIMIT 1");
	$lastpm = $db->fetch_array($query);
	if($touser['pmnotify'] == "yes" && $touser['lastactive'] > $lastpm['dateline'] && !$mybb->input['saveasdraft'])
	{
		if(trim($touser['language']) != "" && $lang->languageExists($touser['language']))
		{
			$uselang = trim($touser['language']);
		}
		elseif($mybb->settings['bblanguage'])
		{
			$uselang = $mybb->settings['language'];
		}
		else
		{
			$uselang = "english";
		}
		if($uselang == $mybb->settings['bblanguage'] || !$uselang)
		{
			$emailsubject = $lang->emailsubject_newpm;
			$emailmessage = $lang->email_newpm;
		}
		else
		{
			$userlang = new MyLanguage;
			$userlang->setPath("./inc/languages");
			$userlang->setLanguage($uselang);
			$userlang->load("messages");
			$emailsubject = $userlang->emailsubject_newpm;
			$emailmessage = $userlang->email_newpm;
		}
		$emailmessage = sprintf($emailmessage, $touser['username'], $mybb->user['username'], $mybb->settings['bbname'], $mybb->settings['bburl']);
		$emailsubject = sprintf($emailsubject, $mybb->settings['bbname']);
		mymail($touser['email'], $emailsubject, $emailmessage);
	}

	$now = time();
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."privatemessages WHERE folder='3' AND uid='".$mybb->user['uid']."' AND pmid='".addslashes($mybb->input['pmid'])."'");
	$draftcheck = $db->fetch_array($query);
	if($mybb->input['saveasdraft'])
	{
		$sendfolder = "3";
	}
	else
	{
		$sendfolder = "1";
	}
	if($draftcheck['pmid'])
	{

		$updateddraft = array(
			"toid" => $touser['uid'],
			"fromid" => $mybb->user['uid'],
			"folder" => $sendfolder,
			"subject" => addslashes($mybb->input['subject']),
			"icon" => intval($mybb->input['icon']),
			"message" => addslashes($mybb->input['message']),
			"dateline" => time(),
			"status" => 0,
			"includesig" => $options['signature'],
			"smilieoff" => $options['disablesmilies'],
			"receipt" => $options['readreceipt'],
			"readtime" => 0
			);
			
		if($mybb->input['saveasdraft'])
		{
			$updateddraft['uid'] = $mybb->user['uid'];
		}
		else
		{
			$updateddraft['uid'] = $touser['uid'];
		}
		$plugins->run_hooks("private_do_send_draft");

		$db->update_query(TABLE_PREFIX."privatemessages", $updateddraft, "pmid='".intval($mybb->input['pmid'])."' AND uid='".$mybb->user['uid']."'");
	}
	else
	{
		$newpm = array(
			"uid" => $touser['uid'],
			"toid" => $touser['uid'],
			"fromid" => $mybb->user['uid'],
			"folder" => $sendfolder,
			"subject" => addslashes($mybb->input['subject']),
			"icon" => intval($mybb->input['icon']),
			"message" => addslashes($mybb->input['message']),
			"dateline" => time(),
			"status" => 0,
			"includesig" => $options['signature'],
			"smilieoff" => $options['disablesmilies'],
			"receipt" => $options['readreceipt'],
			"readtime" => 0
		);

		if($mybb->input['saveasdraft'])
		{
			$newpm['uid'] = $mybb->user['uid'];
		}

		$plugins->run_hooks("private_do_send_process");

		$db->insert_query(TABLE_PREFIX."privatemessages", $newpm);

		// Update private message count (total, new and unread) for recipient
		update_pm_count($touser['uid']);

	}
	if($mybb->input['pmid'] && !$mybb->input['saveasdraft'])
	{
		if($mybb->input['do'] == "reply")
		{
			$sql_array = array(
				"status" => 3
			);
			$db->update_query(TABLE_PREFIX."privatemessages", $sql_array, "pmid=".intval($mybb->input['pmid'])." AND uid=".$mybb->user['uid']);
		}
		elseif($mybb->input['do'] == "forward")
		{
			$sql_array = array(
				"status" => 4
			);
			$db->update_query(TABLE_PREFIX."privatemessages", $sql_array, "pmid=".intval($mybb->input['pmid'])." AND uid=".$mybb->user['uid']);
		}
	}
	if($options['savecopy'] != "no" && !$mybb->input['saveasdraft'])
	{
		$query = $db->query("SELECT COUNT(*) AS total FROM ".TABLE_PREFIX."privatemessages WHERE uid='".$mybb->user['uid']."'");
		$pmscount = $db->fetch_array($query);
		if($mybb->usergroup['pmquota'] == "0" || $pmscount['total'] < $mybb->usergroup['pmquota'])
		{
			$savedcopy = array(
				"uid" => $mybb->user['uid'],
				"toid" => $touser['uid'],
				"fromid" => $mybb->user['uid'],
				"folder" => 2,
				"subject" => addslashes($mybb->input['subject']),
				"icon" => intval($mybb->input['icon']),
				"message" => addslashes($mybb->input['message']),
				"dateline" => time(),
				"status" => 1,
				"includesig" => $options['signature'],
				"smilieoff" => $options['disablesmilies']
				);
			$plugins->run_hooks("private_do_send_savecopy");
			$db->insert_query(TABLE_PREFIX."privatemessages", $savedcopy);

			// Because the sender saved a copy, update their total pm count
			if($touser['uid'] != $mybb->user['uid'])
			{
				update_pm_count("", 4);
			}
		}
	}
	if($touser['pmpopup'] != "no" && !$mybb->input['saveasdraft'])
	{
		$sql_array = array(
			"pmpopup" => "new"
		);
		$db->update_query(TABLE_PREFIX."users", $sql_array, "uid=".$touser['uid']);
	}
	$plugins->run_hooks("private_do_send_end");
	if($mybb->input['saveasdraft'])
	{
		redirect("private.php", $lang->redirect_pmsaved);
	}
	else
	{
		redirect("private.php", $lang->redirect_pmsent);
	}
}
elseif($mybb->input['action'] == "read")
{
	$plugins->run_hooks("private_read");

	$pmid = intval($mybb->input['pmid']);

	$query = $db->query("SELECT pm.*, u.*, f.*, i.path as iconpath, i.name as iconname, g.title AS grouptitle, g.usertitle AS groupusertitle, g.stars AS groupstars, g.starimage AS groupstarimage, g.image AS groupimage, g.namestyle FROM ".TABLE_PREFIX."privatemessages pm LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.fromid) LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid=pm.icon) LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup) WHERE pm.pmid='".intval($mybb->input['pmid'])."' AND pm.uid='".$mybb->user[uid]."'");
	$pm = $db->fetch_array($query);
	if($pm['folder'] == 3)
	{
		header("Location: private.php?action=send&pmid=$pm[pmid]");
		exit;
	}
	if(!$pm['pmid'])
	{
		error($lang->error_invalidpm);
	}
	if($pm['receipt'] == "1")
	{
		if($mybb->usergroup['cantrackpms'] && $mybb->usergroup['candenypmreceipts'] && $mybb->input['denyreceipt'] == "yes")
		{
			$receiptadd = ", receipt='0'";
		}
		else
		{
			$receiptadd = ", receipt='2'";
		}
	}
	if($pm['status'] == "0")
	{
		$time = time();
		/* Do not convert to update_query() as $receiptadd will break. */
		$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET status='1'$receiptadd, readtime='$time' WHERE pmid='$pmid'");

		// Update the unread count - it has now changed.
		update_pm_count("", 4);
	}
	$pm['userusername'] = $pm['username'];
	$pm['subject'] = htmlspecialchars_uni($pm['subject']);
	if($pm['fromid'] == -2)
	{
		$pm['username'] = "MyBB Engine";
	}
	
	addnav($pm['subject']);
	$message = makepostbit($pm, "2");
	eval("\$read = \"".$templates->get("private_read")."\";");
	$plugins->run_hooks("private_read_end");
	outputpage($read);
}	
elseif($mybb->input['action'] == "tracking")
{
	$plugins->run_hooks("private_tracking_start");

	$query = $db->query("SELECT pm.*, u.username as tousername FROM ".TABLE_PREFIX."privatemessages pm LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.toid) WHERE receipt='2' AND status!='0' AND fromid='".$mybb->user[uid]."'");
	while($readmessage = $db->fetch_array($query))
	{
		$readmessage['subject'] = htmlspecialchars_uni($readmessage['subject']);
		$readdate = mydate($mybb->settings['dateformat'], $readmessage['readtime']);
		$readtime = mydate($mybb->settings['timeformat'], $readmessage['readtime']);
		eval("\$readmessages .= \"".$templates->get("private_tracking_readmessage")."\";");
	}
	$query = $db->query("SELECT pm.*, u.username AS tousername FROM ".TABLE_PREFIX."privatemessages pm LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.toid) WHERE receipt='1' AND status='0' AND fromid='".$mybb->user[uid]."'");
	while($unreadmessage = $db->fetch_array($query))
	{
		$unreadmessage['subject'] = htmlspecialchars_uni($unreadmessage['subject']);
		$senddate = mydate($mybb->settings['dateformat'], $unreadmessage['dateline']);
		$sendtime = mydate($mybb->settings['timeformat'], $unreadmessage['dateline']);
		eval("\$unreadmessages .= \"".$templates->get("private_tracking_unreadmessage")."\";");
	}
	eval("\$tracking = \"".$templates->get("private_tracking")."\";");
	$plugins->run_hooks("private_tracking_end");
	outputpage($tracking);
}
elseif($mybb->input['action'] == "do_tracking" && $mybb->request_method == "post")
{
	$plugins->run_hooks("private_do_tracking_start");
	if($mybb->input['stoptracking'])
	{
		if(is_array($mybb->input['readcheck']))
		{
			while(list($key, $val) = each($mybb->input['readcheck']))
			{
				$sql_array = array(
					"receipt" => 0
				);
				$db->update_query(TABLE_PREFIX."privatemessages", $sql_array, "pmid=".intval($key)." AND fromid=".$mybb->user['uid']);
			}
		}
		$plugins->run_hooks("private_do_tracking_end");
		redirect("private.php", $lang->redirect_pmstrackingstopped);
	}
	elseif($mybb->input['stoptrackingunread'])
	{
		if(is_array($mybb->input['unreadcheck']))
		{
			while(list($key, $val) = each($mybb->input['unreadcheck']))
			{
				$sql_array = array(
					"receipt" => 0
				);
				$db->update_query(TABLE_PREFIX."privatemessages", $sql_array, "pmid=".intval($key)." AND fromid=".$mybb->user['uid']);
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
			$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."privatemessages WHERE pmid IN ($pmids) AND fromid='".$mybb->user['uid']."'");
			while($pm = $db->fetch_array($query))
			{
				$pmuids[$pm['uid']] = $pm['uid'];
			}
			$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE pmid IN ($pmids) AND fromid='".$mybb->user['uid']."'");
			foreach($pmuids as $uid)
			{
				// Message is cancelled, update PM count for this user
				update_pm_count($pm['uid']);
			}
		}
		$plugins->run_hooks("private_do_tracking_end");
		redirect("private.php", $lang->redirect_pmstrackingcancelled);
	}
}
elseif($mybb->input['action'] == "folders")
{
	$plugins->run_hooks("private_folders_start");
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	while(list($key, $folders) = each($foldersexploded))
	{
		$folderinfo = explode("**", $folders, 2);
		$foldername = $folderinfo[1];
		$fid = $folderinfo[0];
		if($folderinfo[0] == "1" || $folderinfo[0] == "2" || $folderinfo[0] == "3" || $folderinfo[0] == "4")
		{
			$name = "folder".$folderinfo[0];
			$foldername2 = $lang->$name;
			eval("\$folderlist .= \"".$templates->get("private_folders_folder_unremovable")."\";");
			unset($name);
		}
		else
		{
			eval("\$folderlist .= \"".$templates->get("private_folders_folder")."\";");
		}
	}
	for($i=1;$i<=5;$i++)
	{
		$fid = "new$i";
		$foldername = "";
		eval("\$newfolders .= \"".$templates->get("private_folders_folder")."\";");
	}
	eval("\$folders = \"".$templates->get("private_folders")."\";");
	$plugins->run_hooks("private_folders_end");
	outputpage($folders);
}
elseif($mybb->input['action'] == "do_folders" && $mybb->request_method == "post")
{
	$plugins->run_hooks("private_do_folders_start");
	$highestid = 2;
	$folders = "";
	@reset($mybb->input['folder']);
	while(list($key, $val) = each($mybb->input['folder']))
	{
		if(!$donefolders[$val])
		{
			if(substr($key, 0, 3) == "new")
			{
				$highestid++;
				$fid = $highestid;
			}
			else
			{
				if($key > $highestid)
				{
					$highestid = $key;
				}
				if($key == "1" && $val == "")
				{
					$val = "Inbox";
				}
				if($key == "2" && $val == "")
				{
					$val = "Sent Items";
				}
				if($key == "3" && $val == "")
				{
					$val = "Drafts";
				}
				if($key == "4" && $val == "")
				{
					$val = "Trash Can";
				}
				$fid = $key;
			}
			if($val != "")
			{
				$foldername = $val;
				$foldername = addslashes(htmlspecialchars_uni($foldername));
				if(strpos($foldername, "$%%$") === false)
				{
					if($folders != "")
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
				$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE folder='$fid' AND uid='".$mybb->user['uid']."'");
			}
		}
	}
	
	$sql_array = array(
		"pmfolders" => $folders
	);
	$db->update_query(TABLE_PREFIX."users", $sql_array, "uid='".$mybb->user['uid']);
	$plugins->run_hooks("private_do_folders_end");
	redirect("private.php", $lang->redirect_pmfoldersupdated);
}
elseif($mybb->input['action'] == "empty")
{
	$plugins->run_hooks("private_empty_start");
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	while(list($key, $folders) = each($foldersexploded))
	{
		$folderinfo = explode("**", $folders, 2);
		$fid = $folderinfo[0];
		$foldername = $folderinfo[1];
		$query = $db->query("SELECT COUNT(*) AS pmsinfolder FROM ".TABLE_PREFIX."privatemessages WHERE folder='$fid' AND uid='".$mybb->user[uid]."'");
		$thing = $db->fetch_array($query);
		$foldercount = mynumberformat($thing['pmsinfolder']);
		eval("\$folderlist .= \"".$templates->get("private_empty_folder")."\";");
	}
	eval("\$folders = \"".$templates->get("private_empty")."\";");
	$plugins->run_hooks("private_empty_end");
	outputpage($folders);
}
elseif($mybb->input['action'] == "do_empty" && $mybb->request_method == "post")
{
	$plugins->run_hooks("private_do_empty_start");
	$emptyq = "";
	if(is_array($mybb->input['empty']))
	{
		while(list($key, $val) = each($mybb->input['empty']))
		{
			if($val == "yes")
			{
				$key = intval($key);
				if($emptyq)
				{
					$emptyq .= " OR ";
				}
				$emptyq .= "folder='$key'";
			}
		}
		if($mybb->input['keepunread'] == "yes")
		{
			$keepunreadq = " AND status!='0'";
		}
		$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE ($emptyq) AND uid='".$mybb->user[uid]."' $keepunreadq");
	}
	// Update PM count
	update_pm_count();

	$plugins->run_hooks("private_do_empty_end");
	redirect("private.php", $lang->redirect_pmfoldersemptied);
}
elseif($mybb->input['action'] == "do_stuff" && $mybb->request_method == "post")
{
	$plugins->run_hooks("private_do_stuff");
	if($mybb->input['hop'])
	{
		header("Location: private.php?fid=".$mybb->input['jumpto']);
	}
	elseif($mybb->input['moveto'])
	{
		if(is_array($mybb->input['check']))
		{
			while(list($key, $val) = each($mybb->input['check']))
			{
				$sql_array = array(
					"folder" => intval($mybb->input['fid'])
				);
				$db->update_query(TABLE_PREFIX."privatemessages", $sql_array, "pmid=".intval($key)." AND uid=".$mybb->user['uid']);
			}
		}
		// Update PM count
		update_pm_count();

		redirect("private.php?fid=".$mybb->input['fid'], $lang->redirect_pmsmoved);
	}
	elseif($mybb->input['delete'])
	{
		if(is_array($mybb->input['check']))
		{
			$pmssql = "";
			while(list($key, $val) = each($mybb->input['check']))
			{
				if($pmssql)
				{
					$pmssql .= ",";
				}
				$pmssql .= "'".intval($key)."'";
			}
			$query = $db->query("SELECT pmid, folder FROM ".TABLE_PREFIX."privatemessages WHERE pmid IN ($pmssql) AND uid='".$mybb->user['uid']."' AND folder='4' ORDER BY pmid");
			while($delpm = $db->fetch_array($query))
			{
				$deletepms[$delpm['pmid']] = 1;
			}
			reset($mybb->input['check']);
			while(list($key, $val) = each($mybb->input['check']))
			{
				if($deletepms[$key])
				{
					$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE pmid='$key' AND uid='".$mybb->user['uid']."'");
				}
				else
				{
					$sql_array = array(
						"folder" => 4,
					);
					$db->update_query(TABLE_PREFIX."privatemessages", $sql_array, "pmid='".$key."' AND uid='".$mybb->user['uid']."'");
				}
			}
		}
		// Update PM count
		update_pm_count();

		redirect("private.php", $lang->redirect_pmsdeleted);
	}
}
elseif($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("private_delete_start");
	
	$sql_array = array(
		"folder" => 4
	);
	$db->update_query(TABLE_PREFIX."privatemessages", $sql_array, "pmid='".intval($mybb->input['pmid'])."' AND uid='".$mybb->user[uid]."'");

	// Update PM count
	update_pm_count();

	$plugins->run_hooks("private_delete_end");
	redirect("private.php", $lang->redirect_pmsdeleted);
}
elseif($mybb->input['action'] == "export")
{
	$plugins->run_hooks("private_export_start");
	$folderlist = "<select name=\"exportfolders[]\" multiple>\n";
	$folderlist .= "<option value=\"all\" selected>$lang->all_folders</option>";
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	while(list($key, $folders) = each($foldersexploded))
	{
		$folderinfo = explode("**", $folders, 2);
		$folderlist .= "<option value=\"$folderinfo[0]\">$folderinfo[1]</option>\n";
	}
	$folderlist .= "</select>\n";
	eval("\$archive = \"".$templates->get("private_archive")."\";");
	$plugins->run_hooks("private_export_end");
	outputpage($archive);
}
elseif($mybb->input['action'] == "do_export" && $mybb->request_method == "post")
{
	$plugins->run_hooks("private_do_export_start");
	$lang->private_messages_for = sprintf($lang->private_messages_for, $mybb->user['username']);
	$exdate = mydate($mybb->settings['dateformat'], time(), 0, 0);
	$extime = mydate($mybb->settings['timeformat'], time(), 0, 0);
	$lang->exported_date = sprintf($lang->exported_date, $exdate, $extime);
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	if($mybb->input['pmid'])
	{
		$wsql = "pmid='".intval($mybb->input['pmid'])."' AND uid='".$mybb->user[uid]."'";
	}
	else
	{
		if($mybb->input['daycut'] && ($mybb->input['dayway'] != "disregard"))
		{
			$datecut = time()-($mybb->input['daycut'] * 86400);
			$wsql = "pm.dateline";
			if($mybb->input['dayway'] == "older")
			{
				$wsql .= "<=";
			}
			elseif($mybb->input['dayway'] == "newer")
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
			reset($mybb->input['exportfolders']);
			while(list($key, $val) = each($mybb->input['exportfolders']))
			{
				$val = addslashes($val);
				if($val == "all")
				{
					$folderlst = "";
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
		if($mybb->input['exportunread'] != "yes")
		{
			$wsql .= " AND pm.status!='0'";
		}
	}
	$query = $db->query("SELECT pm.*, fu.username AS fromusername, tu.username AS tousername FROM ".TABLE_PREFIX."privatemessages pm LEFT JOIN ".TABLE_PREFIX."users fu ON (fu.uid=pm.fromid) LEFT JOIN ".TABLE_PREFIX."users tu ON (tu.uid=pm.toid) WHERE $wsql AND pm.uid='".$mybb->user['uid']."' ORDER BY pm.folder ASC, pm.dateline DESC");
	$numpms = $db->num_rows($query);
	if(!$numpms)
	{
		error($lang->error_nopmsarchive);
	}
	while($message = $db->fetch_array($query))
	{
		if($message['folder'] == 2 || $message['folder'] == 3)
		{ // Sent Items or Drafts Folder Check
			if($message['toid'])
			{
				$tofromuid = $message['toid'];
				$tofromusername = "<a href=\"member.php?action=profile&uid=$tofromuid\">$message[tousername]</a>";
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
			$tofromusername = "<a href=\"member.php?action=profile&uid=$tofromuid\">$message[fromusername]</a>";
			if($tofromuid == -2)
			{
				$tofromusername = "MyBB Engine";
			}
			$tofrom = $lang->from;
		}
		if($tofromuid == -2)
		{
			$message['fromusername'] = "MyBB Engine";
		}
		if(!$message['toid'])
		{
			$message['tousername'] = $lang->not_sent;
		}

		$message['subject'] = $message['subject'];
		if($message['folder'] != "3")
		{
			$senddate = mydate($mybb->settings['dateformat'], $message['dateline'], 0, 0);
			$sendtime = mydate($mybb->settings['timeformat'], $message['dateline'], 0, 0);
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
				"allow_smilies" => "no",
				"allow_imgcode" => $mybb->settings['pmsallowimgcode']
			);

			$message['message'] = $parser->parse_message($message['message'], $parser_options);
			// do me code
			if($mybb->settings['pmsallowmycode'] != "no")
			{
				$message['message'] = domecode($message['message'], $message['username']);
			}
		}
		if($mybb->input['exporttype'] == "txt" || $mybb->input['exporttype'] == "csv")
		{
			$message['message'] = str_replace("\r\n", "\n", $message['message']);
			$message['message'] = str_replace("\n", "\r\n", $message['message']);
		}
		if(!$donefolder[$message['folder']])
		{
			reset($foldersexploded);
			while(list($key, $val) = each($foldersexploded))
			{
				$folderinfo = explode("**", $val, 2);
				if($folderinfo[0] == $message['folder'])
				{
					$foldername = $folderinfo[1];
					if($mybb->input['exporttype'] != "csv")
					{
						eval("\$pmsdownload .= \"".$templates->get("private_archive_".$nmybb->input['exporttype']."_folderhead", 1, 0)."\";");
					}
					$donefolder[$message['folder']] = 1;				
				}
			}
		}
		eval("\$pmsdownload .= \"".$templates->get("private_archive_".$mybb->input['exporttype']."_message", 1, 0)."\";");
		$ids .= ",'$message[pmid]'";
	}
	$query = $db->query("SELECT css FROM ".TABLE_PREFIX."themes WHERE tid='$theme[tid]'");
	$css = $db->result($query, 0);

	eval("\$archived = \"".$templates->get("private_archive_".$mybb->input['exporttype'], 1, 0)."\";");
	if($mybb->input['deletepms'] == "yes")
	{ // delete the archived pms
		$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE pmid IN (''$ids)");
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
	$archived = ereg_replace("\\\'","'",$archived);
	header("Content-disposition: filename=$filename");
	header("Content-type: ".$contenttype);
	$plugins->run_hooks("private_do_export_end");
	if($mybb->input['exporttype'] == "html")
	{
		outputpage($archived);
	}
	else
	{
		echo $archived;
	}
}
else
{
	$plugins->run_hooks("private_start");
	if(!$mybb->input['fid'])
	{
		$mybb->input['fid'] = 1;
	}
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	while(list($key, $folders) = each($foldersexploded))
	{
		$folderinfo = explode("**", $folders, 2);
		if($folderinfo[0] == $mybb->input['fid'])
		{
			$foldername = $folderinfo[1];
			$folder = $folderinfo[0];
		}
	}
	if(!$folder)
	{
		$folder = "1";
		$foldername = $lang->inbox;
	}
	$lang->pms_in_folder = sprintf($lang->pms_in_folder, $foldername);
	if($folder == 2 || $folder == 3)
	{ // Sent Items Folder
		$sender = $lang->sentto;
	}
	else
	{
		$sender = $lang->sender;
	}
	$doneunread = 0;
	$doneread = 0;
	// get total messages
	$query = $db->query("SELECT COUNT(*) AS total FROM ".TABLE_PREFIX."privatemessages WHERE uid='".$mybb->user[uid]."'");
	$pmscount = $db->fetch_array($query);
	if($mybb->usergroup['pmquota'] != "0" && $pmscount['total'] >= $mybb->usergroup['pmquota'] && $mybb->usergroup['cancp'] != "yes")
	{
		eval("\$limitwarning = \"".$templates->get("private_limitwarning")."\";");
	}

	// Do Multi Pages
	$query = $db->query("SELECT COUNT(*) AS total FROM ".TABLE_PREFIX."privatemessages WHERE uid='".$mybb->user[uid]."' AND folder='$folder'");
	$pmscount = $db->fetch_array($query);

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

	$query = $db->query("SELECT pm.*, fu.username AS fromusername, tu.username AS tousername, i.path as iconpath, i.name as iconname FROM ".TABLE_PREFIX."privatemessages pm LEFT JOIN ".TABLE_PREFIX."users fu ON (fu.uid=pm.fromid) LEFT JOIN ".TABLE_PREFIX."users tu ON (tu.uid=pm.toid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid=pm.icon) WHERE pm.folder='$folder' AND pm.uid='".$mybb->user[uid]."' ORDER BY pm.dateline DESC LIMIT $start, $perpage");
	if($db->num_rows($query) > 0)
	{
		while($message = $db->fetch_array($query))
		{
			// Determine Folder Icon
			if($message['status'] == 0)
			{
				$msgfolder = "new_pm.gif";
				$doneunread = 1;
			}
			elseif($message['status'] == 1)
			{
				$msgfolder = "old_pm.gif";
				$doneread = 1;
			}
			elseif($message['status'] == 3)
			{
				$msgfolder = "re_pm.gif";
				$doneread = 1;
			}
			elseif($message['status'] == 4)
			{
				$msgfolder = "fw_pm.gif";
				$doneread = 1;
			}
			if($folder == 2 || $folder == 3)
			{ // Sent Items or Drafts Folder Check
				if($message['toid'])
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
				if($tofromuid == -2)
				{
					$tofromusername = "MyBB Engine";
				}
			}
			if($mybb->usergroup['cantrackpms'] && $mybb->usergroup['candenypmreceipts'] && $message['receipt'] == "1" && $message['folder'] != "3")
			{
				eval("\$denyreceipt = \"".$templates->get("private_messagebit_denyreceipt")."\";");
			}
			else
			{
				$denyreceipt = "";
			}
			if($message['iconpath'])
			{
				$icon = "<img src=\"$message[iconpath]\" alt=\"$message[iconname]\">&nbsp;";
			}
			else
			{
				$icon = "";
			}
			$message['subject'] = htmlspecialchars_uni($message['subject']);
			if($message['folder'] != "3")
			{
				$sendpmdate = mydate($mybb->settings['dateformat'], $message['dateline']);
				$sendpmtime = mydate($mybb->settings['timeformat'], $message['dateline']);
				$senddate = $sendpmdate.", ".$sendpmtime;
			}
			else
			{
				$senddate = $lang->not_sent;
			}
			if($doneunread && $doneread)
			{
				eval("\$messagelist .= \"".$templates->get("private_messagebit_sep")."\";");
				$doneunread = 0;
				$doneread = 0;
			}
			eval("\$messagelist .= \"".$templates->get("private_messagebit")."\";");
		}
	}
	else
	{
		eval("\$messagelist .= \"".$templates->get("private_nomessages")."\";");
	}

	if($mybb->usergroup['pmquota'] != "0")
	{
		$query = $db->query("SELECT COUNT(*) AS total FROM ".TABLE_PREFIX."privatemessages WHERE uid='".$mybb->user['uid']."'");
		$pmscount = $db->fetch_array($query);
		$spaceused = $pmscount['total'] / $mybb->usergroup['pmquota'] * 100;
		$spaceused2 = 100 - $spaceused;
		if($spaceused <= "50")
		{
			$belowhalf = round($spaceused, 0)."%";
		}
		else
		{
			$overhalf = round($spaceused, 0)."%";
		}
		eval("\$pmspacebar = \"".$templates->get("private_pmspace")."\";");
	}
	eval("\$folder = \"".$templates->get("private")."\";");
	$plugins->run_hooks("private_end");
	outputpage($folder);
}
?>