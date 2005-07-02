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

require "./global.php";
require "./inc/functions_post.php";

$autocomplete = "on";
// Autocomplete for buddy list when composing PM's
if($_GET['action'] == "getbuddies" && $mybb->user['uid'])
{
	if($mybb->user['buddylist'])
	{
		header("Content-Type: text/xml");
		$users = "";
		$query = addslashes($_GET['query']);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username LIKE '$query%' AND uid IN (".$mybb->user[buddylist].") ORDER BY username ASC");
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
$lang->load("usercpnav");

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
	$db->query("UPDATE ".TABLE_PREFIX."users SET pmfolders='".$mybb->user[pmfolders]."' WHERE uid='".$mybb->user[uid]."'");
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

makeucpnav();


// Make navigation
addnav($lang->nav_pms, "private.php");

switch($action) {
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
if($preview)
{
	$action = "send";
}
if($action == "send")
{

	if($mybb->settings['bbcodeinserter'] != "off" && $mybb->settings['pmsallowmycode'] != "no" && $mybb->user['showcodebuttons'] != 0)
	{
		$codebuttons = makebbcodeinsert();
	}
	if($mybb->settings['pmsallowsmilies'] != "no")
	{
		$smilieinserter = makesmilieinsert();
	}

	$posticons = getposticons();
	$previewmessage = $message;
	$message = htmlspecialchars($message);

	if($preview)
	{
		$query = $db->query("SELECT u.username AS userusername, u.*, f.*, i.path as iconpath, i.name as iconname, g.title AS grouptitle, g.usertitle AS groupusertitle, g.namestyle, g.stars AS groupstars, g.starimage AS groupstarimage, g.image AS groupimage, g.usereputationsystem FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid='$icon') LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup) WHERE u.uid='".$mybb->user[uid]."'");
		$post = $db->fetch_array($query);
		$post['userusername'] = $mybb->user['username'];
		$post['postusername'] = $mybb->user['username'];
		$post['message'] = $previewmessage;
		$post['subject'] = $subject;
		$post['icon'] = $icon;
		$post['dateline'] = time();
		$postbit = makepostbit($post, 1);
		eval("\$preview = \"".$templates->get("previewpost")."\";");

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
		$to = htmlspecialchars($_POST['to']);
		$subject = htmlspecialchars($_POST['subject']);
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
	if($pmid && !$preview)
	{
		$query = $db->query("SELECT pm.*, u.username AS quotename FROM ".TABLE_PREFIX."privatemessages pm LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.fromid) WHERE pm.pmid='$pmid' AND pm.uid='".$mybb->user[uid]."'");
		$pm = $db->fetch_array($query);
		$message = stripslashes($pm['message']);
		$message = htmlspecialchars($message);
		$subject = stripslashes($pm['subject']);
		$subject = htmlspecialchars($subject);
		if($pm['folder'] == "3")
		{ // message saved in drafts
			$uid = $pm['toid'];
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
			$subject = str_replace("Re:","",$subject);
			$subject = str_replace("Fw:","",$subject);
			$postdate = mydate($mybb->settings['dateformat'], $pm['dateline']);
			$posttime = mydate($mybb->settings['timeformat'], $pm['dateline']);
			$message = "[quote=$pm[quotename]]\n$message\n[/quote]";
			$quoted['message'] = preg_replace('#^/me (.*)$#im', "* ".$pm['quotename']." \\1", $quoted['message']);

			if($do == "forward")
			{
				$subject = "Fw: $subject";
			}
			elseif($do == "reply")
			{
				$uid = $pm['fromid'];
				$subject = "Re: $subject";
			}
		}
	}
	if($uid && !$preview)
	{
		$uid = intval($uid);
		$query = $db->query("SELECT username FROM ".TABLE_PREFIX."users WHERE uid='$uid'");
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
	eval("\$send = \"".$templates->get("private_send")."\";");
	outputpage($send);
}
elseif($action == "do_send")
{
	if($subject == "")
	{
		$subject = "[no subject]";
	}
	if(strlen($subject) > 85)
	{
		error($lang->error_subjecttolong);
	}
	if($message == "")
	{
		error($lang->error_pmnomessage);
	}
	$to = addslashes($_POST['to']);
	$query = $db->query("SELECT u.uid, u.username, u.email, u.usergroup, u.pmnotify, u.pmpopup, u.receivepms, u.ignorelist, COUNT(pms.pmid) AS pms_total, g.canusepms, g.pmquota, g.cancp  FROM ".TABLE_PREFIX."users u, ".TABLE_PREFIX."usergroups g LEFT JOIN ".TABLE_PREFIX."privatemessages pms ON (pms.uid=u.uid) WHERE u.username='$to' AND g.gid=u.usergroup GROUP BY u.uid");
	$touser = $db->fetch_array($query);

	if(!$touser['uid'] && !$saveasdraft)
	{
		error($lang->error_invalidpmrecipient);
	}
	$ignorelist = explode(",", $touser['ignorelist']);
	while(list($key, $uid) = each($ignorelist))
	{
		if($uid == $mybb->user['uid'] && $mybb->usergroup['cancp'] != "yes")
		{
			$nosend = true;
		}
	}
	$lang->error_recipientpmturnedoff = sprintf($lang->error_recipientpmturnedoff, $to);
	$lang->error_recipientignoring = sprintf($lang->error_recipientignoring, $to);
	if($nosend)
	{
		error($lang->error_recipientignoring);
	}
	if($touser['receivepms'] == "no" || $touser['canusepms'] == "no" && !$saveasdraft)
	{
		error($lang->error_recipientpmturnedoff);
	}
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
	$message = addslashes($message);
	$subject = addslashes($subject);
	if($touser['pmquota'] != "0" && $touser['pms_total'] >= $touser['pmquota'] && $touser['cancp'] != "yes" && $mybb->usergroup['cancp'] != "yes" && !$saveasdraft)
	{
		$lang->email_reachedpmquota = sprintf($lang->email_reachedpmquota, $touser['username'], $mybb->settings['bbname'], $mybb->settings['bburl']);
		$lang->emailsubject_reachedpmquota = sprintf($lang->emailsubject_reachpmquota, $mybb->settings['bbname']);
		mymail($touser['email'], $lang->email_reachedpmquota, $lang->emailsubject_reachedpmquta);
		error($lang->error_pmrecipientreachedquota);
	}
	$query = $db->query("SELECT dateline FROM ".TABLE_PREFIX."privatemessages WHERE uid='$uid' AND folder='1' ORDER BY dateline DESC LIMIT 1");
	$lastpm = $db->fetch_array($query);
	if($touser['pmnotify'] == "yes" && $touser['lastactive'] > $lastpm['dateline'])
	{
		$lang->email_newpm = sprintf($lang->email_newpm, $touser['username'], $mybb->user['username'], $mybb->settings['bbname'], $mybb->settings['bburl']);
		$lang->emailsubject_newpm = sprintf($lang->emailsubject_newpm, $mybb->settings['bbname']);
		mymail($touser['email'], $lang->email_newpm, $lang->emailsubject_newpm);
	}

	$now = time();
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."privatemessages WHERE folder='3' AND uid='".$mybb->user[uid]."' AND pmid='$pmid'");
	$draftcheck = $db->fetch_array($query);
	if($saveasdraft)
	{
		$sendfolder = "3";
	}
	else
	{
		$sendfolder = "1";
	}
	if($draftcheck['pmid'])
	{
		$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET toid='$touser[uid]', fromid='".$mybb->user[uid]."', folder='$sendfolder', subject='$subject', icon='$icon', message='$message', dateline='$now', status='0', includesig='$options[signature]', smilieoff='$options[disablesmilies]', receipt='$options[readreceipt]', readtime='0' WHERE pmid='$pmid' AND uid='".$mybb->user[uid]."'");
	}
	else
	{
		$db->query("INSERT INTO ".TABLE_PREFIX."privatemessages(pmid,uid,toid,fromid,folder,subject,icon,message,dateline,status,includesig,smilieoff,receipt,readtime) VALUES(NULL,'$touser[uid]','$touser[uid]','".$mybb->user[uid]."','$sendfolder','$subject','$icon','$message','$now','0','$options[signature]','$options[disablesmilies]','$options[readreceipt]','0');");
	}
	if($pmid && !$saveasdraft)
	{
		if($do == "reply")
		{
			$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET status='3' WHERE pmid='$pmid' AND uid='".$mybb->user[uid]."'");
		}
		elseif($do == "forward")
		{
			$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET status='4' WHERE pmid='$pmid' AND uid='".$mybb->user[uid]."'");
		}
	}
	if($options['savecopy'] != "no" && !$saveasdraft)
	{
		$db->query("INSERT INTO ".TABLE_PREFIX."privatemessages(pmid,uid,toid,fromid,folder,subject,icon,message,dateline,status,includesig,smilieoff) VALUES (NULL,'".$mybb->user[uid]."','$touser[uid]','".$mybb->user[uid]."','2','$subject','$icon','$message','$now','1','$options[signature]','$options[disablesmilies]');");
	}
	if($touser['pmpopup'] != "no" && !$saveasdraft)
	{
		$db->query("UPDATE ".TABLE_PREFIX."users SET pmpopup='new' WHERE uid='$touser[uid]'");
	}
	if($saveasdraft)
	{
		redirect("private.php", $lang->redirect_pmsaved);
	}
	else
	{
		redirect("private.php", $lang->redirect_pmsent);
	}
}
elseif($action == "read")
{
	$query = $db->query("SELECT pm.*, u.*, f.*, i.path as iconpath, i.name as iconname, g.title AS grouptitle, g.usertitle AS groupusertitle, g.stars AS groupstars, g.starimage AS groupstarimage, g.image AS groupimage, g.namestyle FROM ".TABLE_PREFIX."privatemessages pm LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.fromid) LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid=pm.icon) LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup) WHERE pm.pmid='$pmid' AND pm.uid='".$mybb->user[uid]."'");
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
		$time = time();
		if($mybb->usergroup['cantrackpms'] && $mybb->usergroup['candenypmreceipts'] && $denyreceipt == "yes")
		{
			$receiptadd = ", receipt='0', readtime='$time'";
		}
		else
		{
			$receiptadd = ", receipt='2', readtime='$time'";
		}
	}
	if($pm['status'] == "0")
	{
		$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET status='1' $receiptadd WHERE pmid='$pmid'");
	}
	$pm['userusername'] = $pm['username'];
	$pm['subject'] = htmlspecialchars($pm['subject']);
	if($pm['fromid'] == -2)
	{
		$pm['username'] = "myBB Engine";
	}
	
	addnav($pm['subject']);
	$message = makepostbit($pm, "1");
	eval("\$read = \"".$templates->get("private_read")."\";");
	outputpage($read);
}	
elseif($action == "tracking")
{
	$query = $db->query("SELECT pm.*, u.username as tousername FROM ".TABLE_PREFIX."privatemessages pm LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.toid) WHERE receipt='2' AND status!='0' AND fromid='".$mybb->user[uid]."'");
	while($readmessage = $db->fetch_array($query))
	{
		$readmessage['subject'] = stripslashes($readmessage['subject']);
		$readmessage['subject'] = htmlspecialchars($readmessage['subject']);
		$readdate = mydate($mybb->settings['dateformat'], $readmessage['readtime']);
		$readtime = mydate($mybb->settings['timeformat'], $readmessage['readtime']);
		eval("\$readmessages .= \"".$templates->get("private_tracking_readmessage")."\";");
	}
	$query = $db->query("SELECT pm.*, u.username AS tousername FROM ".TABLE_PREFIX."privatemessages pm LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.toid) WHERE receipt='1' AND status='0' AND fromid='".$mybb->user[uid]."'");
	while($unreadmessage = $db->fetch_array($query))
	{
		$unreadmessage['subject'] = stripslashes($unreadmessage['subject']);
		$unreadmessage['subject'] = htmlspecialchars($unreadmessage['subject']);
		$senddate = mydate($mybb->settings['dateformat'], $unreadmessage['dateline']);
		$sendtime = mydate($mybb->settings['timeformat'], $unreadmessage['dateline']);
		eval("\$unreadmessages .= \"".$templates->get("private_tracking_unreadmessage")."\";");
	}
	eval("\$tracking = \"".$templates->get("private_tracking")."\";");
	outputpage($tracking);
}
elseif($action == "do_tracking")
{
	if($stoptracking)
	{
		if(is_array($readcheck))
		{
			while(list($key, $val) = each($readcheck))
			{
				$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET receipt='0' WHERE pmid='$key'");
			}
		}
		redirect("private.php", $lang->redirect_pmstrackingstopped);
	}
	elseif($cancel)
	{
		if(is_array($unreadcheck))
		{
			while(list($key, $val) = each($unreadcheck))
			{
				$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE pmid='$key'");
			}
		}
		redirect("private.php", $lang->redirect_pmstrackingcancelled);
	}
}
elseif($action == "folders")
{
	// echo $mybb->user['pmfolders'];
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
	outputpage($folders);
}
elseif($action == "do_folders")
{
	$highestid = 2;
	$folders = "";
	@reset($folder);
	while(list($key, $val) = each($folder))
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
				$foldername = addslashes(htmlspecialchars($foldername));
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
				$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE folder='$fid'");
			}
		}
	}
	$db->query("UPDATE ".TABLE_PREFIX."users SET pmfolders='$folders' WHERE uid='".$mybb->user[uid]."'");
	redirect("private.php", $lang->redirect_pmfoldersupdated);
}
elseif($action == "empty")
{
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	while(list($key, $folders) = each($foldersexploded))
	{
		$folderinfo = explode("**", $folders, 2);
		$fid = $folderinfo[0];
		$foldername = $folderinfo[1];
		$query = $db->query("SELECT COUNT(*) AS pmsinfolder FROM ".TABLE_PREFIX."privatemessages WHERE folder='$fid' AND uid='".$mybb->user[uid]."'");
		$thing = $db->fetch_array($query);
		$foldercount = $thing['pmsinfolder'];
		eval("\$folderlist .= \"".$templates->get("private_empty_folder")."\";");
	}
	eval("\$folders = \"".$templates->get("private_empty")."\";");
	outputpage($folders);
}
elseif($action == "do_empty")
{
	$emptyq = "";
	if(is_array($empty))
	{
		while(list($key, $val) = each($empty))
		{
			if($val == "yes")
			{
				if($emptyq)
				{
					$emptyq .= " OR ";
				}
				$emptyq .= "folder='$key'";
			}
		}
		if($keepunread == "yes")
		{
			$keepunreadq = " AND status!='0'";
		}
		$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE ($emptyq) AND uid='".$mybb->user[uid]."' $keepunreadq");
	}
	redirect("private.php", $lang->redirect_pmfoldersemptied);
}
elseif($action == "do_stuff")
{
	if($hop)
	{
		header("Location: private.php?fid=$jumpto");
	}
	elseif($moveto)
	{
		if(is_array($check))
		{
			while(list($key, $val) = each($check))
			{
				$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET folder='$fid' WHERE pmid='$key'");
			}
		}
		redirect("private.php?fid=$fid", $lang->redirect_pmsmoved);
	}
	elseif($delete)
	{
		if(is_array($check))
		{
			$pmssql = "";
			while(list($key, $val) = each($check))
			{
				if($pmssql)
				{
					$pmssql .= ",";
				}
				$pmssql .= "'$key'";
			}
			$query = $db->query("SELECT pmid, folder FROM ".TABLE_PREFIX."privatemessages WHERE pmid IN ($pmssql) AND uid='".$mybb->user[uid]."' AND folder='4' ORDER BY pmid");
			while($delpm = $db->fetch_array($query))
			{
				$deletepms[$delpm['pmid']] = 1;
			}
			reset($check);
			while(list($key, $val) = each($check))
			{
				if($deletepms[$key])
				{
					$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE pmid='$key'");
				}
				else
				{
					$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET folder='4' WHERE pmid='$key'");
				}
			}
		}
		redirect("private.php", $lang->redirect_pmsdeleted);
	}
}
elseif($action == "delete")
{
	$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET folder='4' WHERE pmid='$pmid' AND uid='".$mybb->user[uid]."'");
	redirect("private.php", $lang->redirect_pmsdeleted);
}
elseif($action == "export")
{
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
	outputpage($archive);
}
elseif($action == "do_export")
{
	$lang->private_messages_for = sprintf($lang->private_messages_for, $mybb->user['username']);
	$exdate = mydate($mybb->settings['dateformat'], time(), 0, 0);
	$extime = mydate($mybb->settings['timeformat'], time(), 0, 0);
	$lang->exported_date = sprintf($lang->exported_date, $exdate, $extime);
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	if($pmid)
	{
		$wsql = "pmid='$pmid' AND uid='".$mybb->user[uid]."'";
	}
	else
	{
		if($daycut && ($dayway != "all"))
		{
			$datecut = time()-($daycut * 86400);
			$wsql = "pm.dateline";
			if($dayway == "older")
			{
				$wsql .= "<=";
			}
			elseif($dayway == "newer")
			{
				$wsql .= ">=";
			}
			$wsql .= "'$datecut'";
		}
		else
		{
			$wsql = "1=1";
		}
		if(is_array($exportfolders))
		{
			reset($exportfolders);
			while(list($key, $val) = each($exportfolders))
			{
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
		if($exportunread != "yes")
		{
			$wsql .= " AND pm.status!='0'";
		}
	}
	if($exporttype != "html" && $exporttype != "csv")
	{
		$exporttype = "txt";
	}
	$query = $db->query("SELECT pm.*, fu.username AS fromusername, tu.username AS tousername FROM ".TABLE_PREFIX."privatemessages pm LEFT JOIN ".TABLE_PREFIX."users fu ON (fu.uid=pm.fromid) LEFT JOIN ".TABLE_PREFIX."users tu ON (tu.uid=pm.toid) WHERE $wsql AND pm.uid='".$mybb->user[uid]."' ORDER BY pm.folder ASC, pm.dateline DESC");
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

		$message['subject'] = stripslashes($message['subject']);
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
		if($exporttype == "html")
		{
			$message['message'] = postify($message['message'], $mybb->settings['pmsallowhtml'], $mybb->settings['pmsallowmycode'], "no", $mybb->settings['pmsallowimgcode']);
			// do me code
			if($mybb->settings['pmsallowmycode'] != "no")
			{
				$message['message'] = domecode($message['message'], $message['username']);
			}
		}
		if($exporttype == "txt" || $exporttype == "csv")
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
					if($exporttype != "csv")
					{
						eval("\$pmsdownload .= \"".$templates->get("private_archive_".$exporttype."_folderhead", 1, 0)."\";");
					}
					$donefolder[$message['folder']] = 1;				
				}
			}
		}
		eval("\$pmsdownload .= \"".$templates->get("private_archive_".$exporttype."_message", 1, 0)."\";");
		$ids .= ",'$message[pmid]'";
	}
	$query = $db->query("SELECT css FROM ".TABLE_PREFIX."themes WHERE tid='$theme[tid]'");
	$css = $db->result($query, 0);
	eval("\$archived = \"".$templates->get("private_archive_".$exporttype, 1, 0)."\";");
	if($deletepms == "yes")
	{ // delete the archived pms
		$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE pmid IN (''$ids)");
	}
	if($exporttype == "html")
	{
		$filename = "pm-archive.html";
	}
	elseif($exporttype == "csv")
	{
		$filename = "pm-archive.csv";
	}
	else
	{
		$filename = "pm-archive.txt";
	}
	$archived = ereg_replace("\\\'","'",$archived);
	header("Content-disposition: filename=$filename");
//	header("Content-length: ".strlen($archived)."");
	header("Content-type: unknown/unknown");
	if($exporttype == "html")
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
	if(!$fid)
	{
		$fid = 1;
	}
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	while(list($key, $folders) = each($foldersexploded))
	{
		$folderinfo = explode("**", $folders, 2);
		if($folderinfo[0] == $fid)
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
	if($folder == 2)
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
	if($page)
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
		$message['subject'] = htmlspecialchars($message['subject']);
		if($message['folder'] != "3")
		{
			$senddate = mydate($mybb->settings['dateformat'], $message['dateline']);
			$sendtime = mydate($mybb->settings['timeformat'], $message['dateline']);
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
	eval("\$folder = \"".$templates->get("private")."\";");
	outputpage($folder);
}
?>