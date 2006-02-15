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

$templatelist = "newthread,previewpost,error_invalidforum,redirect_newthread,loginbox,changeuserbox,newthread_postpoll,posticons,attachment,newthread_postpoll,codebuttons,smilieinsert,error_nosubject";
$templatelist .= "posticons";

require "./global.php";
require "./inc/functions_post.php";
require "./inc/functions_user.php";

// Load global language phrases
$lang->load("newthread");

if($mybb->input['action'] == "editdraft" || ($mybb->input['savedraft'] && $mybb->input['tid']) || ($mybb->input['tid'] && $mybb->input['pid']))
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='".intval($mybb->input['tid'])."' AND visible='-2'");
	$thread = $db->fetch_array($query);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='".intval($mybb->input['tid'])."' AND visible='-2' ORDER BY dateline ASC LIMIT 0, 1");
	$post = $db->fetch_array($query);
	if(!$thread['tid'] || !$post['pid'])
	{
		error($lang->invalidthread);
	}
	$pid = $post['pid'];
	$fid = $thread['fid'];
	$tid = $thread['tid'];
}
else
{
	$fid = intval($mybb->input['fid']);
}

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$fid' AND active!='no'");
$forum = $db->fetch_array($query);

// Make navigation
makeforumnav($fid);
addnav($lang->nav_newthread);

$forumpermissions = forum_permissions($fid);

if($forum['open'] == "no" || $forum['type'] != "f")
{
	error($lang->error_closedinvalidforum);
}

if($forumpermissions['canview'] == "no" || $forumpermissions['canpostthreads'] == "no")
{
	nopermission();
}
// Password protected forums ......... yhummmmy!
checkpwforum($fid, $forum['password']);

if($mybb->settings['bbcodeinserter'] != "off" && $forum['allowmycode'] != "no" && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
{
	$codebuttons = makebbcodeinsert();
	if($forum['allowsmilies'] != "no")
	{
		$smilieinserter = makesmilieinsert();
	}
}
if($forum['allowpicons'] != "no")
{
	$posticons = getposticons();
}


if($mybb->user['uid'] != 0)
{
	eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
}
else
{
	if(!$mybb->input['previewpost'] && $mybb->input['action'] != "do_newthread")
	{
		$username = $lang->guest;
	}
	else
	{
		$username = $mybb->input['username'];
	}
	eval("\$loginbox = \"".$templates->get("loginbox")."\";");
}
if($mybb->input['action'] != "do_newthread" && $mybb->input['action'] != "editdraft")
{
	$mybb->input['action'] = "newthread";
}

if($mybb->input['previewpost'])
{
	$mybb->input['action'] = "newthread";
}

if(!$mybb->input['removeattachment'] && ($mybb->input['newattachment'] || ($mybb->input['action'] == "do_newthread" && $mybb->input['submit'] && $_FILES['attachment'])))
{
	// If there's an attachment, check it and upload it
	if($_FILES['attachment']['size'] > 0 && $forumpermissions['canpostattachments'] != "no")
	{
		require "./inc/functions_upload.php";
		$attachedfile = upload_attachment($_FILES['attachment']);
	}
	if($attachedfile['error'])
	{
		eval("\$attacherror = \"".$templates->get("error_attacherror")."\";");
		$mybb->input['action'] = "newthread";
	}
	if(!$mybb->input['submit'])
	{
		$mybb->input['action'] = "newthread";
	}
}
if($mybb->input['removeattachment'])
{ // Lets remove the attachment
	require_once "./inc/functions_upload.php";
	remove_attachment(0, $mybb->input['posthash'], $mybb->input['removeattachment']);
	if(!$mybb->input['submit'])
	{
		$mybb->input['action'] = "newthread";
	}
}
// Max images check
if($mybb->input['action'] == "do_newthread" && !$mybb->input['savedraft'])
{
	if($mybb->settings['maxpostimages'] != 0 && $mybb->usergroup['cancp'] != "yes")
	{
		if($mybb->input['postoptions']['disablesmilies'] == "yes")
		{
			$allowsmilies = "no";
		}
		else
		{
			$allowsmilies = $forum['allowsmilies'];
		}
		$imagecheck = postify($mybb->input['message'], $forum['allowhtml'], $forum['allowmycode'], $allowsmilies, $forum['allowimgcode']);
		if(substr_count($imagecheck, "<img") > $mybb->settings['maxpostimages'])
		{
			eval("\$maximageserror = \"".$templates->get("error_maxpostimages")."\";");
			$mybb->input['action'] = "newthread";
		}
	}
}

if($mybb->input['action'] == "newthread" || $mybb->input['action'] == "editdraft")
{
	$plugins->run_hooks("newthread_start");
	if($mybb->input['previewpost'] || $mybb->input['removeattachment'] || $mybb->input['newattachment'] || $maximageserror)
	{
		$postoptions = $mybb->input['postoptions'];
		if($postoptions['signature'] == "yes")
		{
			$postoptionschecked['signature'] = "checked";
		}
		if($postoptions['emailnotify'] == "yes")
		{
			$postoptionschecked['emailnotify'] = "checked";
		}
		if($postoptions['disablesmilies'] == "yes")
		{
			$postoptionschecked['disablesmilies'] = "checked";
		}
		if($postpoll == "yes")
		{
			$postpollchecked = "checked";
		}
		$numpolloptions = intval($mybb->input['numpolloptions']);
	}
	elseif($mybb->input['action'] == "editdraft" && $mybb->user['uid'])
	{
		$message = htmlspecialchars_uni($post['message']);
		$subject = htmlspecialchars_uni($post['subject']);
		if($post['includesig'] != "no")
		{
			$postoptionschecked['signature'] = "checked";
		}
		if($post['smilieoff'] == "yes")
		{
			$postoptionschecked['disablesmilies'] = "checked";
		}
		$editdraftpid = "<input type=\"hidden\" name=\"pid\" value=\"$pid\" /><input type=\"hidden\" name=\"tid\" value=\"$tid\" />";
		$icon = $post['icon'];
	}
	else
	{
		if($mybb->user['signature'] != "")
		{
			$postoptionschecked['signature'] = "checked";
		}
		if($mybb->user['emailnotify'] == "yes")
		{
			$postoptionschecked['emailnotify'] = "checked";
		}
		$numpolloptions = "2";
	}
=======
	$plugins->run_hooks("newthread_start");
>>>>>>> .r1142

<<<<<<< .mine
	if($mybb->input['previewpost'])
	{
		if(!$mybb->input['username'])
		{
			$mybb->input['username'] = $lang->guest;
		}
		if($mybb->input['username'] && !$mybb->user['uid'])
		{
			$mybb->user = validate_password_from_username($mybb->input['username'], $mybb->input['password']);
		}
		$query = $db->query("SELECT u.*, f.*, i.path as iconpath, i.name as iconname FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid='".intval($mybb->input['icon'])."') WHERE u.uid='".$mybb->user[uid]."'");
		$post = $db->fetch_array($query);
		if(!$mybb->user['uid'] || !$post['username'])
		{
			$post['username'] = $mybb->input['username'];
		}
		else
		{
			$post['userusername'] = $mybb->user['username'];
			$post['username'] = $mybb->user['username'];
		}
		$previewmessage = $mybb->input['message'];
		$post['message'] = $previewmessage;
		$post['subject'] = $mybb->input['subject'];
		$post['icon'] = $mybb->input['icon'];
		$post['smilieoff'] = $postoptions['disablesmilies'];
		$post['dateline'] = time();
=======
	if($mybb->input['previewpost'] || $mybb->input['removeattachment'] || $mybb->input['newattachment'] || $maximageserror)
	{
		$postoptions = $mybb->input['postoptions'];
		if($postoptions['signature'] == "yes")
		{
			$postoptionschecked['signature'] = "checked";
		}
		if($postoptions['emailnotify'] == "yes")
		{
			$postoptionschecked['emailnotify'] = "checked";
		}
		if($postoptions['disablesmilies'] == "yes")
		{
			$postoptionschecked['disablesmilies'] = "checked";
		}
		if($postpoll == "yes")
		{
			$postpollchecked = "checked";
		}
		$numpolloptions = intval($mybb->input['numpolloptions']);
	}
	elseif($mybb->input['action'] == "editdraft" && $mybb->user['uid'])
	{
		$message = htmlspecialchars_uni($post['message']);
		$subject = htmlspecialchars_uni($post['subject']);
		if($post['includesig'] != "no")
		{
			$postoptionschecked['signature'] = "checked";
		}
		if($post['smilieoff'] == "yes")
		{
			$postoptionschecked['disablesmilies'] = "checked";
		}
		$editdraftpid = "<input type=\"hidden\" name=\"pid\" value=\"$pid\" /><input type=\"hidden\" name=\"tid\" value=\"$tid\" />";
		$icon = $post['icon'];
	}
	else
	{
		if($mybb->user['signature'] != "")
		{
			$postoptionschecked['signature'] = "checked";
		}
		if($mybb->user['emailnotify'] == "yes")
		{
			$postoptionschecked['emailnotify'] = "checked";
		}
		$numpolloptions = "2";
	}
>>>>>>> .r1142

<<<<<<< .mine
		// Fetch attachments assigned to this post
		if($mybb->input['pid'])
		{
			$attachwhere = "pid='".intval($mybb->input['pid'])."'";
		}
		else
		{
			$attachwhere = "posthash='".addslashes($mybb->input['posthash'])."'";
		}
=======
	if($mybb->input['previewpost'])
	{
		if(!$mybb->input['username'])
		{
			$mybb->input['username'] = $lang->guest;
		}
		if($mybb->input['username'] && !$mybb->user['uid'])
		{
			$mybb->user = validate_password_from_username($mybb->input['username'], $mybb->input['password']);
		}
		$query = $db->query("SELECT u.*, f.*, i.path as iconpath, i.name as iconname FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid='".intval($mybb->input['icon'])."') WHERE u.uid='".$mybb->user[uid]."'");
		$post = $db->fetch_array($query);
		if(!$mybb->user['uid'] || !$post['username'])
		{
			$post['username'] = $mybb->input['username'];
		}
		else
		{
			$post['userusername'] = $mybb->user['username'];
			$post['username'] = $mybb->user['username'];
		}
		$previewmessage = $mybb->input['message'];
		$post['message'] = $previewmessage;
		$post['subject'] = $mybb->input['subject'];
		$post['icon'] = $mybb->input['icon'];
		$post['smilieoff'] = $postoptions['disablesmilies'];
		$post['dateline'] = time();
>>>>>>> .r1142

<<<<<<< .mine
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE $attachwhere");
		while($attachment = $db->fetch_array($query)) {
			$attachcache[0][$attachment['aid']] = $attachment;
		}
=======
		// Fetch attachments assigned to this post
		if($mybb->input['pid'])
		{
			$attachwhere = "pid='".intval($mybb->input['pid'])."'";
		}
		else
		{
			$attachwhere = "posthash='".addslashes($mybb->input['posthash'])."'";
		}
>>>>>>> .r1142

<<<<<<< .mine
		$postbit = makepostbit($post, 1);
		eval("\$preview = \"".$templates->get("previewpost")."\";");
		$message = htmlspecialchars_uni($mybb->input['message']);
		$subject = htmlspecialchars_uni($mybb->input['subject']);
	}
	elseif($mybb->input['removeattachment'] || $mybb->input['newattachment'] || $maximageserror) {
		$message = htmlspecialchars_uni($mybb->input['message']);
		$subject = htmlspecialchars_uni($mybb->input['subject']);
	}
=======
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE $attachwhere");
		while($attachment = $db->fetch_array($query)) {
			$attachcache[0][$attachment['aid']] = $attachment;
		}
>>>>>>> .r1142

<<<<<<< .mine
	// Setup a unique posthash for attachment management
	if(!$mybb->input['posthash'] && $mybb->input['action'] != "editdraft")
	{
		 mt_srand ((double) microtime() * 1000000);
		 $posthash = md5($mybb->user['uid'].mt_rand());
	}
	else
	{
		$posthash = htmlspecialchars($mybb->input['posthash']);
	}
=======
		$postbit = makepostbit($post, 1);
		eval("\$preview = \"".$templates->get("previewpost")."\";");
		$message = htmlspecialchars_uni($mybb->input['message']);
		$subject = htmlspecialchars_uni($mybb->input['subject']);
	}
	elseif($mybb->input['removeattachment'] || $mybb->input['newattachment'] || $maximageserror) {
		$message = htmlspecialchars_uni($mybb->input['message']);
		$subject = htmlspecialchars_uni($mybb->input['subject']);
	}
>>>>>>> .r1142

<<<<<<< .mine
	// Can we disable smilies or are they disabled already?
	if($forum['allowsmilies'] != "no")
	{
		eval("\$disablesmilies = \"".$templates->get("newthread_disablesmilies")."\";");
	}
	else
	{
		$disablesmilies = "<input type=\"hidden\" name=\"postoptions[disablesmilies]\" value=\"no\" />";
	}
=======
	// Setup a unique posthash for attachment management
	if(!$mybb->input['posthash'] && $mybb->input['action'] != "editdraft")
	{
	    mt_srand ((double) microtime() * 1000000);
	    $posthash = md5($mybb->user['uid'].mt_rand());
	}
	else
	{
		$posthash = htmlspecialchars($mybb->input['posthash']);
	}
>>>>>>> .r1142

<<<<<<< .mine
	// Show the moderator options
	if(ismod($fid) == "yes")
	{
		$modoptions = $mybb->input['modoptions'];
		if($modoptions['closethread'] == "yes")
		{
			$closecheck = "checked=\"checked\"";
		}
		else
		{
			$closecheck = "";
		}
		if($modoptions['stickthread'] == "yes")
		{
			$stickycheck = "checked=\"checked\"";
		}
		else
		{
			$stickycheck = "";
		}
		unset($modoptions);
		$bgcolor = "trow2";
		eval("\$modoptions = \"".$templates->get("newreply_modoptions")."\";");
		$bgcolor = "trow1";
	}
	else
	{
		$bgcolor = "trow2";
	}
=======
	// Can we disable smilies or are they disabled already?
	if($forum['allowsmilies'] != "no")
	{
		eval("\$disablesmilies = \"".$templates->get("newthread_disablesmilies")."\";");
	}
	else
	{
		$disablesmilies = "<input type=\"hidden\" name=\"postoptions[disablesmilies]\" value=\"no\" />";
	}
>>>>>>> .r1142

<<<<<<< .mine
	if($forumpermissions['canpostattachments'] != "no")
	{ // Get a listing of the current attachments, if there are any
		$attachcount = 0;
		if($mybb->input['action'] == "editdraft")
		{
			$attachwhere = "pid='$pid'";
		}
		else
		{
			$attachwhere = "posthash='".addslashes($posthash)."'";
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE $attachwhere");
		while($attachment = $db->fetch_array($query))
		{
			$attachment['size'] = getfriendlysize($attachment['filesize']);
			$attachment['icon'] = getattachicon(getextention($attachment['filename']));
			if($forum['allowmycode'] != "no")
			{
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
=======
	// Show the moderator options
	if(ismod($fid) == "yes")
	{
		$modoptions = $mybb->input['modoptions'];
		if($modoptions['closethread'] == "yes")
		{
			$closecheck = "checked=\"checked\"";
		}
		else
		{
			$closecheck = "";
		}
		if($modoptions['stickthread'] == "yes")
		{
			$stickycheck = "checked=\"checked\"";
		}
		else
		{
			$stickycheck = "";
		}
		unset($modoptions);
		$bgcolor = "trow2";
		eval("\$modoptions = \"".$templates->get("newreply_modoptions")."\";");
		$bgcolor = "trow1";
	}
	else
	{
		$bgcolor = "trow2";
	}
>>>>>>> .r1142

<<<<<<< .mine
		if($bgcolor == "trow1")
		{
			$bgcolor = "trow2";
		}
		else
		{
			$bgcolor = "trow1";
		}
	}
=======
	if($forumpermissions['canpostattachments'] != "no")
	{ // Get a listing of the current attachments, if there are any
		$attachcount = 0;
		if($mybb->input['action'] == "editdraft")
		{
			$attachwhere = "pid='$pid'";
		}
		else
		{
			$attachwhere = "posthash='".addslashes($posthash)."'";
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE $attachwhere");
		while($attachment = $db->fetch_array($query))
		{
			$attachment['size'] = getfriendlysize($attachment['filesize']);
			$attachment['icon'] = getattachicon(getextention($attachment['filename']));
			if($forum['allowmycode'] != "no")
			{
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
>>>>>>> .r1142

<<<<<<< .mine
	if($mybb->user['uid'])
	{
		eval("\$savedraftbutton = \"".$templates->get("post_savedraftbutton")."\";");
	}
=======
		if($bgcolor == "trow1")
		{
			$bgcolor = "trow2";
		}
		else
		{
			$bgcolor = "trow1";
		}
	}
>>>>>>> .r1142

<<<<<<< .mine
	if($forumpermissions['canpostpolls'] != "no")
	{
		$lang->max_options = sprintf($lang->max_options, $mybb->settings['maxpolloptions']);
		eval("\$pollbox = \"".$templates->get("newthread_postpoll")."\";");
	}
	$lang->newthread_in = sprintf($lang->newthread_in, $thread['subject']);
=======
	if($mybb->user['uid'])
	{
		eval("\$savedraftbutton = \"".$templates->get("post_savedraftbutton")."\";");
	}
>>>>>>> .r1142

<<<<<<< .mine
	$plugins->run_hooks("newthread_end");
=======
	if($forumpermissions['canpostpolls'] != "no")
	{
		$lang->max_options = sprintf($lang->max_options, $mybb->settings['maxpolloptions']);
		eval("\$pollbox = \"".$templates->get("newthread_postpoll")."\";");
	}
	$lang->newthread_in = sprintf($lang->newthread_in, $thread['subject']);
>>>>>>> .r1142

<<<<<<< .mine
	eval("\$newthread = \"".$templates->get("newthread")."\";");
	outputpage($newthread);
=======
	$plugins->run_hooks("newthread_end");
>>>>>>> .r1142

	eval("\$newthread = \"".$templates->get("newthread")."\";");
	outputpage($newthread);

}
if($mybb->input['action'] == "do_newthread" && $mybb->request_method == "post")
{
	$plugins->run_hooks("newthread_do_newthread_start");

	if($mybb->user['uid'] == 0)
	{
		$username = htmlspecialchars_uni($mybb->input['username']);
		if(username_exists($mybb->input['username']))
		{
			if(!$mybb->input['password'])
			{
				error($lang->error_usernametaken);
			}
			$mybb->user = validate_password_from_username($mybb->input['username'], $mybb->input['password']);
			if(!$mybb->user['uid'])
			{
				error($lang->error_invalidpassword);
			}
			$mybb->input['username'] = $username = $mybb->user['username'];
			mysetcookie("mybbuser", $mybb->user['uid']."_".$mybb->user['loginkey']);
		}
		else
		{
			if(!$mybb->input['username'])
			{
				$username = $lang->guest;
			}
			else
			{
				$username = $mybb->input['username'];
			}
			$uid = 0;
		}
	}
	else
	{
		$username = $mybb->user['username'];
		$uid = $mybb->user['uid'];
	}
	$updatepost = 0;
	if(!$mybb->input['savedraft'] || !$mybb->user['uid'])
	{
		if(trim($mybb->input['subject']) == "")
		{
			error($lang->error_nosubject);
		}
		if(my_strlen(trim($mybb->input['subject'])) > 85)
		{
			error($lang->error_subjecttolong);
		}
		if(strlen(trim($mybb->input['message'])) == 0)
		{
			error($lang->error_nomessage);
		}
		$now = time();
		// Flood checking
		if($mybb->settings['postfloodcheck'] == "on")
		{
			if($mybb->user['uid'] != 0 && $now-$mybb->user['lastpost'] <= $mybb->settings['postfloodsecs'] && ismod($fid) != "yes")
			{
				$lang->error_postflooding = sprintf($lang->error_postflooding, $mybb->settings['postfloodsecs']);
				error($lang->error_postflooding);
			}
		}
		if(strlen($mybb->input['message']) > $mybb->settings['messagelength'] && $mybb->settings['messagelength'] > 0 && ismod($fid) != "yes")
		{
			error($lang->error_messagelength);
		}
		$savedraft = 0;
	}
	elseif($mybb->input['savedraft'] && $mybb->user['uid'])
	{
		$savedraft = 1;
	}
	if($post['pid'])
	{
		$updatepost = 1;
	}
	else
	{
		$updatepost = 0;
	}
	if(!$mybb->input['icon'])
	{
		$mybb->input['icon'] = "0";
	}
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

	if($savedraft)
	{
		$visible = -2;
	}
	elseif($forum['modthreads'] == "yes" && $mybb->usergroup['cancp'] != "yes")
	{
		$visible = 0;
	}
	else
	{
		$visible = 1;
	}
	$now = time();
	if($updatepost)
	{
		$newthread = array(
			"subject" => addslashes($mybb->input['subject']),
			"icon" => intval($mybb->input['icon']),
			"username" => addslashes(htmlspecialchars_uni($username)),
			"dateline" => time(),
			"lastpost" => time(),
			"lastposter" => addslashes(htmlspecialchars_uni($mybb->user['username'])),
			"visible" => $visible
			);
		$db->update_query(TABLE_PREFIX."threads", $newthread, "tid='$tid'");

		$newpost = array(
			"subject" => addslashes($mybb->input['subject']),
			"icon" => intval($mybb->input['icon']),
			"username" => addslashes(htmlspecialchars_uni($username)),
			"dateline" => time(),
			"message" => addslashes($mybb->input['message']),
			"ipaddress" => getip(),
			"includesig" => $postoptions['signature'],
			"smilieoff" => $postoptions['disablesmilies'],
			"visible" => $visible
			);

		$db->update_query(TABLE_PREFIX."posts", $newpost, "pid='$pid'");
	}
	else
	{
		$newthread = array(
			"fid" => $fid,
			"subject" => addslashes($mybb->input['subject']),
			"icon" => intval($mybb->input['icon']),
			"uid" => $mybb->user['uid'],
			"username" => addslashes(htmlspecialchars_uni($username)),
			"dateline" => time(),
			"lastpost" => time(),
			"lastposter" => addslashes(htmlspecialchars_uni($username)),
			"views" => 0,
			"replies" => 0,
			"visible" => $visible
			);

		$plugins->run_hooks("newthread_do_newthread_process");

		$db->insert_query(TABLE_PREFIX."threads", $newthread);
		$tid = $db->insert_id();

		$newpost = array(
			"tid" => $tid,
			"fid" => $fid,
			"subject" => addslashes($mybb->input['subject']),
			"icon" => intval($mybb->input['icon']),
			"uid" => $mybb->user['uid'],
			"username" => addslashes(htmlspecialchars_uni($username)),
			"dateline" => time(),
			"message" => addslashes($mybb->input['message']),
			"ipaddress" => getip(),
			"includesig" => $postoptions['signature'],
			"smilieoff" => $postoptions['disablesmilies'],
			"visible" => $visible
			);
		$db->insert_query(TABLE_PREFIX."posts", $newpost);
		$pid = $db->insert_id();

		//
		// Update ye firstpost column
		//
		$firstpostup = array("firstpost" => $pid);
		$db->update_query(TABLE_PREFIX."threads", $firstpostup, "tid='$tid'");
	}

	// Do moderator options
	if(ismod($fid) == "yes" && !$savedraft)
	{
		$modlogdata['fid'] = $thread['fid'];
		$modlogdata['tid'] = $thread['tid'];
		$modoptions = $mybb->input['modoptions'];
		if($modoptions['closethread'] == "yes")
		{
			$newclosed = "closed='yes'";
			logmod($modlogdata, "Thread closed");
		}
		if($modoptions['stickthread'] == "yes")
		{
			$newstick = "sticky='1'";
			logmod($modlogdata, "Thread stuck");
		}
		if($newstick && $newclosed) { $sep = ","; }
		if($newstick || $newclosed)
		{
			$db->query("UPDATE ".TABLE_PREFIX."threads SET $newclosed$sep$newstick WHERE tid='$tid'");
		}
	}

	// Setup the correct ownership of the attachments
	$db->query("UPDATE ".TABLE_PREFIX."attachments SET pid='$pid' WHERE posthash='".addslashes($mybb->input['posthash'])."'");

	// Start Forum Subscriptions
	if($savedraft != 1)
	{
		$excerpt = substr($mybb->input['message'], 0, $mybb->settings['subscribeexcerpt']).$lang->emailbit_viewthread;
		$query = $db->query("SELECT u.username, u.email, u.uid, u.language FROM ".TABLE_PREFIX."forumsubscriptions fs, ".TABLE_PREFIX."users u WHERE fs.fid='$fid' AND u.uid=fs.uid AND fs.uid!='".$mybb->user[uid]."' AND u.lastactive>'".$forum['lastpost']."'");
		while($subscribedmember = $db->fetch_array($query))
		{
			if($subscribedmember['language'] != "" && $lang->languageExists($subscribedmember['language']))
			{
				$uselang = $subscribedmember['language'];
			}
			elseif($mybb->settings['bblanguage'])
			{
				$uselang = $mybb->settings['bblanguage'];
			}
			else
			{
				$uselang = "english";
			}

			if($uselang == $mybb->settings['bblanguage'])
			{
				$emailsubject = $lang->emailsubject_forumsubscription;
				$emailmessage = $lang->email_forumsubscription;
			}
			else
			{
				if(!isset($langcache[$uselang]['emailsubject_forumsubscription']))
				{
					$userlang = new MyLanguage;
					$userlang->setPath("./inc/languages");
					$userlang->setLanguage($uselang);
					$userlang->load("messages");
					$langcache[$uselang]['emailsubject_forumsubscription'] = $userlang->emailsubject_forumsubscription;
					$langcache[$uselang]['email_forumsubscription'] = $userlang->email_forumsubscription;
					unset($userlang);
				}
				$emailsubject = $langcache[$uselang]['emailsubject_forumsubscription'];
				$emailmessage = $langcache[$uselang]['email_forumsubscription'];

			}
			$emailsubject = sprintf($emailsubject, $forum['name']);
			$emailmessage = sprintf($emailmessage, $subscribedmember['username'], $mybb->user['username'], $forum['name'], $mybb->settings['bbname'], $mybb->input['subject'], $excerpt, $mybb->settings['bburl'], $tid, $fid);
			mymail($subscribedmember['email'], $emailsubject, $emailmessage);
			unset($userlang);
		}

		// Start Auto Subscribe
		if($postoptions['emailnotify'] != "no")
		{
			$db->query("INSERT INTO ".TABLE_PREFIX."favorites (uid,tid,type) VALUES ('".$mybb->user['uid']."','$tid','s')");
		}

		// Update user's post count
		if($forum['usepostcounts'] != "no")
		{
			$queryadd = ",postnum=postnum+1";
		}
		else
		{
			$queryadd = "";
		}
		$db->query("UPDATE ".TABLE_PREFIX."users SET lastpost='$now' $queryadd WHERE uid='".$mybb->user['uid']."'");
	}

	// Deciding the fate
	if($savedraft)
	{
		// Draft thread
		$lang->redirect_newthread = $lang->draft_saved;
		$url = "usercp.php?action=drafts";
	}
	elseif($mybb->input['postpoll'] && $forumpermissions['canpostpolls'])
	{
		// Visible thread + poll
		$url = "polls.php?action=newpoll&tid=$tid&polloptions=".intval($mybb->input['numpolloptions']);
		$lang->redirect_newthread .= $lang->redirect_newthread_poll;
		$cache->updatestats();
		updatethreadcount($tid);
		updateforumcount($fid);
	}
	elseif(!$visible)
	{
		// Moderated thread
		$lang->redirect_newthread .= $lang->redirect_newthread_moderation;
		$url = "forumdisplay.php?fid=$fid";
		// Update the unapproved posts and threads count for the current thread and current forum
		$db->query("UPDATE ".TABLE_PREFIX."threads SET unapprovedposts=unapprovedposts+1 WHERE tid='$tid'");
		$db->query("UPDATE ".TABLE_PREFIX."forums SET unapprovedthreads=unapprovedthreads+1, unapprovedposts=unapprovedposts+1 WHERE fid='$fid'");
	}
	else
	{
		// Visible thread
		$lang->redirect_newthread .= $lang->redirect_newthread_thread;
		$url = "showthread.php?tid=$tid";
		$cache->updatestats();
		updatethreadcount($tid);
		updateforumcount($fid);
	}

	$plugins->run_hooks("newthread_do_newthread_end");

	if(function_exists("threadPosted") && !$savedraft)
	{
		threadPosted($tid);
	}

	redirect($url, $lang->redirect_newthread);

}
?>
