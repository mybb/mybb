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

define("KILL_GLOBALS", 1);

$templatelist = "editpost,previewpost,redirect_postedited,loginbox,posticons,changeuserbox,attachment";
$templatelist .= "posticons";

require "./global.php";
require "./inc/functions_post.php";
require "./inc/functions_upload.php";

// Load global language phrases
$lang->load("editpost");

$pid = intval($mybb->input['pid']);

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE pid='$pid'");
$post = $db->fetch_array($query);
$tid = $post['tid'];

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
$thread = $db->fetch_array($query);
$thread['subject'] = htmlspecialchars_uni($thread['subject']);

$fid = $thread['fid'];

// Make navigation
makeforumnav($fid);
addnav($thread['subject'], "showthread.php?tid=$thread[tid]");
addnav($lang->nav_editpost);

$forumpermissions = forum_permissions($fid);

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$fid'");
$forum = $db->fetch_array($query);

if(!$post['pid'])
{
	error($lang->error_invalidpost);
}
if(!$thread['tid'])
{
	error($lang->error_invalidthread);
}
if($mybb->settings['bbcodeinserter'] != "off" && $forum['allowmycode'] != "no" && $mybb->user[showcodebuttons] != 0)
{
	$codebuttons = makebbcodeinsert();
}
if($mybb->settings['smilieinserter'] != "off")
{
	$smilieinserter = makesmilieinsert();
}

if(!$mybb->input['action'] || $mybb->input['previewpost'])
{
	$mybb->input['action'] = "editpost";
}
if($forum['open'] == "no")
{
	nopermission();
}

if(!$mybb->user['uid'])
{
	nopermission();
}
if($mybb->input['action'] == "deletepost" && $mybb->request_method == "post")
{
	if(ismod($fid, "candeleteposts") != "yes")
	{
		if($thread['closed'] == "yes")
		{
			redirect("showthread.php?tid=$tid", $lang->redirect_threadclosed);
		}
		if($forumpermissions['candeleteposts'] == "no")
		{
			nopermission();
		}
		if($mybb->user['uid'] != $post['uid'])
		{
			nopermission();
		}
	}
}
else
{
	if(ismod($fid, "caneditposts") != "yes") {
		if($thread['closed'] == "yes") {
			redirect("showthread.php?tid=$tid", $lang->redirect_threadclosed);
		}
		if($forumpermissions['caneditposts'] == "no") {
			nopermission();
		}
		if($mybb->user['uid'] != $post['uid']) {
			nopermission();
		}
		// Edit time limit
		$time = time();
		if($mybb->settings['edittimelimit'] != 0 && $post['dateline'] < ($time-($mybb->settings['edittimelimit']*60)))
		{
			$lang->edit_time_limit = sprintf($lang->edit_time_limit, $mybb->settings['edtitimelimit']);
			error($lang->edit_time_limit);
		}
	}
}

// Password protected forums ......... yhummmmy!
checkpwforum($fid, $forum['password']);

// Max images check
if($mybb->input['action'] == "do_editpost") {
	if($mybb->settings['maxpostimages'] != 0 && $mybb->usergroup['cancp'] != "yes") {
		if($postoptions['disablesmilies'] == "yes") {
			$allowsmilies = "no";
		} else {
			$allowsmilies = $forum['allowsmilies'];
		}
		$imagecheck = postify($mybb->input['message'], $forum['allowhtml'], $forum['allowmycode'], $allowsmilies, $forum['allowimgcode']);
		if(substr_count($imagecheck, "<img") > $mybb->settings['maxpostimages']) {
			eval("\$maximageserror = \"".$templates->get("error_maxpostimages")."\";");
			$mybb->input['action'] = "editpost";
		}
	}
}
if(!$mybb->input['removeattachment'] && ($mybb->input['newattachment'] || ($mybb->input['action'] == "do_editpost" && $mybb->input['submit'] && $_FILES['attachment']))) {
	// If there's an attachment, check it and upload it
	if($_FILES['attachment']['size'] > 0 && $forumpermissions['canpostattachments'] != "no") {
		$attachedfile = upload_attachment($_FILES['attachment']);
	}
	if($attachedfile['error']) {
		eval("\$attacherror = \"".$templates->get("error_attacherror")."\";");
		$mybb->input['action'] = "editpost";
	}
	if(!$mybb->input['submit']) {
		$mybb->input['action'] = "editpost";
	}
}
if($mybb->input['removeattachment']) { // Lets remove the attachmen
	remove_attachment($pid, $mybb->input['posthash'], $mybb->input['removeattachment']);
	if(!$mybb->input['submit']) {
		$mybb->input['action'] = "editpost";
	}
//	die($removeattachment);
}

if($mybb->input['action'] == "deletepost")
{

	$plugins->run_hooks("editpost_deletepost");
	
	if($mybb->input['delete'] == "yes")
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid' ORDER BY dateline ASC LIMIT 0,1");
		$firstcheck = $db->fetch_array($query);
		if($firstcheck['pid'] == $pid)
		{
			$firstpost = 1;
		}
		else
		{
			$firstpost = 0;
		}
		$modlogdata['fid'] = $fid;
		$modlogdata['tid'] = $tid;
		if($firstpost)
		{
			if($forumpermissions['candeletethreads'] == "yes")
			{
				deletethread($tid);
				updateforumcount($fid);
				markreports($tid, "thread");
				if(ismod($fid, "candeleteposts") != "yes")
				{
					logmod($modlogdata, "Deleted Thread");
				}
				redirect("forumdisplay.php?fid=$fid", $lang->redirect_threaddeleted);
			}
			else
			{
				nopermission();
			}
		}
		else
		{
			if($forumpermissions['candeleteposts'] == "yes")
			{
				// Select the first post before this
				deletepost($pid, $tid);
				updatethreadcount($tid);
				updateforumcount($fid);
				markreports($pid, "post");
				if(ismod($fid, "candeleteposts") != "yes")
				{
					logmod($modlogdata, "Deleted Post");
				}
				$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid' AND dateline <= '$post[dateline]' ORDER BY dateline DESC LIMIT 0, 1");
				$p = $db->fetch_array($query);
				if($p['pid']) {
					$redir = "showthread.php?tid=$tid&pid=$p[pid]#pid$p[pid]";
				} else {
					$redir = "showthread.php?tid=$tid";
				}
				redirect($redir, $lang->redirect_postdeleted);
			}
			else
			{
				nopermission();
			}
		}
	}
	else
	{
		redirect("showthread.php?tid=$tid", $lang->redirect_nodelete);
	}
}
elseif($mybb->input['action'] == "do_editpost")
{

	$plugins->run_hooks("editpost_do_editpost_start");
	
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid' ORDER BY dateline ASC LIMIT 0,1");
	$firstcheck = $db->fetch_array($query);
	if($firstcheck['pid'] == $pid)
	{
		$firstpost = 1;
	}
	else
	{
		$firstpost = 0;
	}

	if(strlen(trim($mybb->input['subject'])) == 0 && $firstpost)
	{
		error($lang->error_nosubject);
	}
	elseif(strlen(trim($mybb->input['subject'])) == 0)
	{
		$mybb->input['subject'] = "RE: " . $thread['subject'];
	}

	if (strlen(trim($mybb->input['message'])) == 0)
	{
		error($lang->error_nomessage);
	}

	if(strlen(trim($mybb->input['message'])) > $mybb->settings['messagelength'] && $mybb->settings['messagelength'] != 0)
	{
		error($lang->error_messagelength);
	}

	$db->query("DELETE FROM ".TABLE_PREFIX."attachments WHERE filename='' AND filesize < 1");

	if(!$mybb->input['icon'] || $mybb->input['icon'] == -1)
	{
		$mybb->input['icon'] = "0";
	}

	if($firstpost)
	{
		$newpost = array(
			"subject" => addslashes($mybb->input['subject']),
			"icon" => intval($mybb->input['icon']),
			);
		$db->update_query(TABLE_PREFIX."threads", $newpost, "tid='$tid'");
	}

	$now = time();
	
	$postoptions = $mybb->input['postoptions'];

	if($postoptions['signature'] != "yes")
	{
		$postoptions['signature'] = "no";
	}
	if($postoptions['emailnotify'] != "yes")
	{
		$postoptions['emailnotify'] = "no";
	}
	if($postoptions['disablesmilies'] != "yes")
	{
		$postoptions['disablesmilies'] = "no";
	}

	// Start Auto Subscribe
	if($postoptions['emailnotify'] != "no") {
		$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."favorites WHERE type='s' AND tid='$tid' AND uid='".$mybb->user[uid]."'");
		$subcheck = $db->fetch_array($query);
		if(!$subcheck['uid']) {
			$subscriptionarray = array(
				"uid" => $mybb->user['uid'],
				"tid" => $tid,
				"type" => "s"
			);
			$db->insert_query(TABLE_PREFIX."favorites", $subscriptionarray);
		}
	} else {
		$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE type='s' AND uid='".$mybb->user[uid]."' AND tid='$tid'");
	}
	
	if($mybb->input['postpoll'] && $forumpermissions['canpostpolls'])
	{
		$url = "polls.php?action=newpoll&tid=$tid&polloptions=".$mybb->input['numpolloptions'];
		$redirect = $lang->redirect_postedited_poll;
	}
	else
	{
		$url = "showthread.php?tid=$tid&pid=$pid#pid$pid";
		$redirect = $lang->redirect_postedited;
	}
	$newpost = array(
		"subject" => addslashes($mybb->input['subject']),
		"message" => addslashes($mybb->input['message']),
		"icon" => intval($mybb->input['icon']),
		"smilieoff" => $postoptions['disablesmilies'],
		"includesig" => $postoptions['signature']
		);

	$plugins->run_hooks("editpost_do_editpost_process");

	if(($mybb->settings['showeditedby'] == "yes" && ismod($fid, "caneditposts") != "yes") || ($mybb->settings['showeditedbyadmin'] == "yes" && ismod($fid, "caneditposts") == "yes")) {
		$newpost['edituid'] = $mybb->user['uid'];
		$newpost['edittime'] = $now;
	}

	$db->update_query(TABLE_PREFIX."posts", $newpost, "pid=$pid");

	$plugins->run_hooks("editpost_do_editpost_end");

	redirect($url, $redirect);
} else {

	$plugins->run_hooks("editpost_start");
	
	if(!$mybb->input['previewpost']) {
		$icon = $post['icon'];
	}
	
	if($forum['allowpicons'] != "no")
	{
		$posticons = getposticons();
	}
	
	if($mybb->user['uid'] != 0) {
		eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
	} else {
		eval("\$loginbox = \"".$templates->get("loginbox")."\";");
	}

	// Setup a unique posthash for attachment management
	if(!$mybb->input['posthash']) {
	    mt_srand ((double) microtime() * 1000000);
	    $posthash = md5($post['pid'].$mybb->user['uid'].mt_rand());
	}
	else
	{
		$posthash = addslashes($mybb->input['posthash']);
	}

	$bgcolor = "trow2";
	if($forumpermissions['canpostattachments'] != "no") { // Get a listing of the current attachments, if there are any
		$attachcount = 0;
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE posthash='$posthash' OR pid='$pid'");
		while($attachment = $db->fetch_array($query)) {
			$attachment['size'] = getfriendlysize($attachment['filesize']);
			$attachment['icon'] = getattachicon(getextention($attachment['filename']));
			if($forum['allowmycode'] != "no") {
				eval("\$postinsert = \"".$templates->get("post_attachments_attachment_postinsert")."\";");
			}
			eval("\$attachments .= \"".$templates->get("post_attachments_attachment")."\";");
			$attachcount++;
		}
		$query = $db->query("SELECT SUM(filesize) AS ausage FROM ".TABLE_PREFIX."attachments WHERE uid='".$mybb->user['uid']."'");
		$usage = $db->fetch_array($query);
		if($usage['ausage'] > ($mybb->usergroup['attachquota']*1000) && $mybb->usergroup['attachquota'] != 0)
		{
			$noshowattach = 1;
		}
		if($mybb->usergroup['attachquota'] == 0)
		{
			$friendlyquota = $lang->unlimited;
		}
		else
		{
			$friendlyquota = getfriendlysize($mybb->usergroup['attachquota']*1000);
		}
		$friendlyusage = getfriendlysize($usage['ausage']);
		$lang->attach_quota = sprintf($lang->attach_quota, $friendlyusage, $friendlyquota);
		if($mybb->settings['maxattachments'] == 0 || ($mybb->settings['maxattachments'] != 0 && $attachcount <= $mybb->settings['maxattachments']) && !$noshowattach)
		{
			eval("\$newattach = \"".$templates->get("post_attachments_new")."\";");
		}
		eval("\$attachbox = \"".$templates->get("post_attachments")."\";");
	}
	if(!$mybb->input['removeattachment'] && !$mybb->input['newattachment'] && !$mybb->input['previewpost'] && !$maximageserror) {
		$message = $post['message'];
		$subject = $post['subject'];
	}
	else
	{
		$message = $mybb->input['message'];
		$subject = $mybb->input['subject'];
	}

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid' ORDER BY dateline ASC LIMIT 0,1");
	$firstcheck = $db->fetch_array($query);
	if($firstcheck['pid'] == $pid && $forumpermissions['canpostpolls'] != "no" && $thread['poll'] < 1) {
		$lang->max_options = sprintf($lang->max_options, $mybb->settings['maxpolloptions']);
		$numpolloptions = "2";
		eval("\$pollbox = \"".$templates->get("newthread_postpoll")."\";");
	}
		
	if($mybb->input['previewpost']) {
		$previewmessage = $message;
		$message = htmlspecialchars_uni($message);
		$subject = htmlspecialchars_uni($subject);

		$postoptions = $mybb->input['postoptions'];

		if($postoptions['signature'] == "yes") {
			$postoptionschecked['signature'] = "checked";
		}
		if($postoptions['emailnotify'] == "yes") {
			$postoptionschecked['emailnotify'] = "checked";
		}
		if($postoptions['disablesmilies'] == "yes") {
			$postoptionschecked['disablesmilies'] = "checked";
		}
		
		if(!$mybb->input['username']) {
			$username = "Guest";
		}
		else
		{
			$username = htmlspecialchars_uni($mybb->input['username']);
		}

		if($username && !$mybb->user['uid']) {
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='$username'");
			$user = $db->fetch_array($query);
			if($user['password'] == md5($password) && $user['username']) {
				$mybb->user['username'] = $user['username'];
				$mybb->user['uid'] = $user['uid'];
			}
		}

		$query = $db->query("SELECT u.*, f.*, i.path as iconpath, i.name as iconname FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid='$icon') WHERE u.uid='".$mybb->user[uid]."'");
		$postinfo = $db->fetch_array($query);

		if(!$mybb->user['uid'] || !$postinfo['username']) {
			$postinfo['username'] = $username;
		} else {
			$postinfo['userusername'] = $mybb->user['username'];
			$postinfo['username'] = $mybb->user['username'];
		}
		$postinfo['message'] = $previewmessage;
		$postinfo['subject'] = $subject;
		$postinfo['icon'] = $icon;
		$postinfo['smilieoff'] = $postoptions['disablesmilies'];
		$postinfo['dateline'] = time();

		$postbit = makepostbit($postinfo, 1);
		eval("\$preview = \"".$templates->get("previewpost")."\";");
	} else {
		$message = htmlspecialchars_uni($message);
		$subject = htmlspecialchars_uni($subject);

		if($post['includesig'] != "no") {
			$postoptionschecked['signature'] = "checked";
		}
		if($post['smilieoff'] == "yes") {
			$postoptionschecked['disablesmilies'] = "checked";
		}
		// Can we disable smilies or are they disabled already?
		if($forum['allowsmilies'] != "no")
		{
			eval("\$disablesmilies = \"".$templates->get("editpost_disablesmilies")."\";");
		}
		else
		{
			$disablesmilies = "<input type=\"hidden\" name=\"postoptions[disablesmilies]\" value=\"no\" />";
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."favorites WHERE type='s' AND tid='$tid' AND uid='".$mybb->user[uid]."'");
		$subcheck = $db->fetch_array($query);
		if($subcheck['tid']) {
			$postoptionschecked['emailnotify'] = "checked";
		}
	}

	$plugins->run_hooks("editpost_end");

	eval("\$editpost = \"".$templates->get("editpost")."\";");
	outputpage($editpost);
}
?>