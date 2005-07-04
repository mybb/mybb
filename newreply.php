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

$templatelist = "newreply,previewpost,error_invalidforum,error_invalidthread,redirect_threadposted,loginbox,changeuserbox,posticons,newreply_threadreview,forumrules,attachments,newreply_threadreview_post";
$templatelist .= ",smilieinsert,codebuttons,post_attachments_new,post_attachments,post_savedraftbutton,newreply_modoptions";

require "./global.php";
require "./inc/functions_post.php";

// Load global language phrases
$lang->load("newreply");

if($action == "editdraft" || ($savedraft && $pid) || ($tid && $pid))
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE pid='$pid'");
	$post = $db->fetch_array($query);
	if(!$post['pid'])
	{
		error($lang->invalidpost);
	}
	$tid = $post['tid'];
}
$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
$thread = $db->fetch_array($query);
$fid = $thread['fid'];
$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$fid' AND active!='no'");
$forum = $db->fetch_array($query);

// Make navigation
makeforumnav($fid);
addnav($thread['subject'], "showthread.php?tid=$thread[tid]");
addnav($lang->nav_newreply);

$forumpermissions = forum_permissions($fid);
$thread['subject'] = htmlspecialchars_uni(stripslashes($thread['subject']));

if(!$thread['subject'])
{
	error($lang->error_invalidthread);
}
if($forum['open'] == "no" || $forum['type'] != "f")
{
	error($lang->error_invalidforum);
}
if($forumpermissions['canview'] == "no" || $forumpermissions['canpostreplys'] == "no")
{
	nopermission();
}
// Password protected forums ......... yhummmmy!
checkpwforum($fid, $forum['password']);

if($mybb->settings['bbcodeinserter'] != "off" && $forum['allowmycode'] != "no" && $mybb->user[showcodebuttons] != 0)
{
	$codebuttons = makebbcodeinsert();
}
if($forum['allowsmilies'] != "no")
{
	$smilieinserter = makesmilieinsert();
}

if($mybb->user['uid'] != 0)
{
	eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
}
else
{
	if(!$previewpost && $action != "do_newreply")
	{
		$username = "Guest";
	}
	eval("\$loginbox = \"".$templates->get("loginbox")."\";");
}
// check to see if the threads closed, and if the user is a mod
if(ismod($fid, "caneditposts") != "yes")
{
	if($thread['closed'] == "yes")
	{
		redirect("showthread.php?tid=$tid", $lang->redirect_threadclosed);
	}
}

if($action != "do_newreply" && $action != "editdraft")
{
	$action = "newreply";
}

if($previewpost)
{
	$action = "newreply";
}
if(!$removeattachment && ($newattachment || ($action == "do_newreply" && $submit && $HTTP_POST_FILES['attachment'])))
{
	// If there's an attachment, check it and upload it
	if($HTTP_POST_FILES['attachment']['size'] > 0 && $forumpermissions['canpostattachments'] != "no")
	{
		require_once "./inc/functions_upload.php";
		$attachedfile = upload_attachment($_FILES['attachment']);
	}
	if($attachedfile['error'])
	{
		eval("\$attacherror = \"".$templates->get("error_attacherror")."\";");
		$action = "newreply";
	}
	if(!$submit)
	{
		$action = "newreply";
	}
}
if($removeattachment)
{ // Lets remove the attachmen
	require_once "./inc/functions_upload.php";
	remove_attachment($pid, $posthash, $removeattachment);
	if(!$submit)
	{
		$action = "newreply";
	}
}

// Max images check
if($action == "do_newreply" && !$savedraft)
{
	if($mybb->settings['maxpostimages'] != 0 && $mybb->usergroup['cancp'] != "yes")
	{
		if($postoptions['disablesmilies'] == "yes")
		{
			$allowsmilies = "no";
		}
		else
		{
			$allowsmilies = $forum['allowsmilies'];
		}
		$imagecheck = postify($message, $forum['allowhtml'], $forum['allowmycode'], $allowsmilies, $forum['allowimgcode']);
		if(substr_count($imagecheck, "<img") > $mybb->settings['maxpostimages'])
		{
			eval("\$maximageserror = \"".$templates->get("error_maxpostimages")."\";");
			$action = "newreply";
		}
	}
}


if($action == "newreply" || $action == "editdraft")
{
	if($pid && !$previewpost && $action != "editdraft")
	{
		$query = $db->query("SELECT p.*, u.username FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE p.pid='$pid' AND p.tid='$tid' AND p.visible='1'");
		$quoted = $db->fetch_array($query);
		$quoted['subject'] = preg_replace("#RE:#i", "", stripslashes($quoted['subject']));
		$subject = "RE: " . htmlspecialchars_uni($quoted['subject']);
		$quoted['message'] = preg_replace('#^/me (.*)$#im', "* $quoted[username] \\1", $quoted['message']);
		if($quoted['username'])
		{
			$message = "[quote=$quoted[username]]\n$quoted[message]\n[/quote]";
		}
		else
		{
			$message = "[quote]\n$quoted[message]\n[/quote]";
		}
	}
	if(!$pid && !$previewpost)
	{
		$subject = "RE: " . $thread['subject'];
	}

	$previewmessage = $message;
	$message = htmlspecialchars_uni($message);
	$editdraftpid = "";

	if($previewpost || $maximageserror)
	{
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
	}
	elseif($action == "editdraft" && $mybb->user['uid'])
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
		$editdraftpid = "<input type=\"hidden\" name=\"pid\" value=\"$pid\" />";
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
	}
	if($forum['allowpicons'] != "no")
	{
		$posticons = getposticons();
	}

	if($previewpost)
	{
		if(!$username)
		{
			$username = "Guest";
		}
		if($username && !$mybb->user['uid'])
		{
			$query = $db->query("SELECT * FROM users WHERE username='$username'");
			$user = $db->fetch_array($query);
			if($user['password'] == md5($password) && $user['username'])
			{
				$mybb->user['username'] = $user['username'];
				$mybb->user['uid'] = $user['uid'];
			}
		}
		$query = $db->query("SELECT u.*, f.*, i.path as iconpath, i.name as iconname FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid='$icon') WHERE u.uid='".$mybb->user[uid]."'");
		$post = $db->fetch_array($query);
		if(!$mybb->user['uid'] || !$post['username'])
		{
			$post['username'] = $username;
		}
		else
		{
			$post['userusername'] = $mybb->user['username'];
			$post['username'] = $mybb->user['username'];
		}
		$post['message'] = $previewmessage;
		$post['subject'] = $subject;
		$post['icon'] = $icon;
		$post['smilieoff'] = $postoptions['disablesmilies'];
		$post['dateline'] = time();
		$postbit = makepostbit($post, 1);
		eval("\$preview = \"".$templates->get("previewpost")."\";");
	}

	// Setup a unique posthash for attachment management
	if(!$posthash && $action != "editdraft")
	{
	    mt_srand ((double) microtime() * 1000000);
	    $posthash = md5($thread['tid'].$mybb->user['uid'].mt_rand());
	}

	if($forumpermissions['canpostattachments'] != "no")
	{ // Get a listing of the current attachments, if there are any
		$attachcount = 0;
		if($action == "editdraft")
		{
			$attachwhere = "pid='$pid'";
		}
		else
		{
			$attachwhere = "posthash='$posthash'";
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
	}
	if($mybb->user['uid'])
	{
		eval("\$savedraftbutton = \"".$templates->get("post_savedraftbutton")."\";");
	}
	if($mybb->settings['threadreview'] != "off")
	{
		$query = $db->query("SELECT p.*, u.* FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid) WHERE tid='$tid' AND visible='1' ORDER BY dateline DESC");
		$numposts = $db->num_rows($query);
		if($numposts > $mybb->settings['postsperpage'])
		{
			$numposts = $mybb->settings['postsperpage'];
			$lang->thread_review_more = sprintf($lang->thread_review_more, $mybb->settings['postsperpage'], $tid);
			eval("\$reviewmore = \"".$templates->get("newreply_threadreview_more")."\";");
		}
		$postsdone = 0;
		$altbg = "trow1";
		while($post = $db->fetch_array($query))
		{
			$postsdone++;
			if($postsdone > $numposts)
			{
				continue;
			}
			else
			{
				$reviewpostdate = mydate($mybb->settings['dateformat'], $post['dateline']);
				$reviewposttime = mydate($mybb->settings['timeformat'], $post['dateline']);
				$reviewmessage = stripslashes($post['message']);
				if($post['smilieoff'] == "yes")
				{
					$allowsmilies = "no";
				}
				else
				{
					$allowsmilies = $forum['allowsmilies'];
				}
				$reviewmessage = postify($reviewmessage, $forum['allowhtml'], $forum['allowmycode'], $allowsmilies, $forum['allowimgcode']);
				eval("\$reviewbits .= \"".$templates->get("newreply_threadreview_post")."\";");
				if($altbg == "trow1")
				{
					$altbg = "trow2";
				}
				else
				{
					$altbg = "trow1";
				}
			}
			eval("\$threadreview = \"".$templates->get("newreply_threadreview")."\";");
		}
	}
	// Can we disable smilies or are they disabled already?
	if($forum['allowsmilies'] != "no")
	{
		eval("\$disablesmilies = \"".$templates->get("newreply_disablesmilies")."\";");
	}
	else
	{
		$disablesmilies = "<input type=\"hidden\" name=\"postoptions[disablesmilies]\" value=\"no\" />";
	}
	// Show the moderator options
	if(ismod($fid) == "yes")
	{
		if($thread['closed'] == "yes")
		{
			$closecheck = "checked";
		}
		else
		{
			$closecheck = "";
		}
		if($thread['sticky'])
		{
			$stickycheck = "checked";
		}
		else
		{
			$stickycheck = "";
		}
		eval("\$modoptions = \"".$templates->get("newreply_modoptions")."\";");
	}
	$lang->post_reply_to = sprintf($lang->post_reply_to, $thread['subject']);
	$lang->reply_to = sprintf($lang->reply_to, $thread['subject']);
	eval("\$newreply = \"".$templates->get("newreply")."\";");
	outputpage($newreply);
}
if($action == "do_newreply" )
{
	if($mybb->user['uid'] == 0)
	{
		$username = htmlspecialchars_uni($username);
		$username = addslashes($username);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='$username'");
		$member = $db->fetch_array($query);
		if($member['uid'])
		{
			if(!$password)
			{
				error($lang->error_usernametaken);
			}
			elseif($member['password'] != md5($password))
			{
				error($lang->error_invalidpass);
			}
			$author = $member['uid'];
			$username = $member['username'];
			$mybb = $member;
			$mybb->user = $member;
			mysetcookie("mybb[uid]", $member['uid']);
			mysetcookie("mybb[password]", $member['password']);
		}
		else
		{
			if(!$username)
			{
				$username = "Guest";
			}
			$author = 0;
		}
	}
	else
	{
		$author = $mybb->user['uid'];
		$username = $mybb->user['username'];
	}
	$updatepost = 0;
	if(!$savedraft || !$mybb->user['uid'])
	{
		if (strlen(trim($message)) == 0)
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
		if(strlen($message) > $mybb->settings['messagelength'] && $mybb->settings['messagelength'] > 0 && ismod($fid) != "yes")
		{
			error($lang->error_messagelength);
		}
		$savedraft = 0;
	}
	elseif($savedraft && $mybb->user['uid'])
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
		

	if(!$icon)
	{
		$icon = "0";
	}

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
	
	// Start Subscriptions
	if(!$savedraft)
	{
		$subject = dobadwords($thread['subject']);
		$excerpt = substr(dobadwords(stripslashes($message)), 0, $mybb->settings['subscribeexcerpt'])."... (visit the thread to read more..)";
		$query = $db->query("SELECT dateline FROM ".TABLE_PREFIX."posts WHERE tid='$tid' ORDER BY dateline DESC LIMIT 1");
		$lastpost = $db->fetch_array($query);
		$query = $db->query("SELECT u.username, u.email, u.uid FROM ".TABLE_PREFIX."favorites f, ".TABLE_PREFIX."users u WHERE f.type='s' AND f.tid='$tid' AND u.uid=f.uid AND f.uid!='".$mybb->user[uid]."' AND u.lastactive>'$lastpost[dateline]'");
		while($subscribedmember = $db->fetch_array($query))
		{
			$emailsubject = sprintf($lang->emailsubject_subscription, $subject);
			$emailmessage = sprintf($lang->email_subscription, $subscribedmember['username'], $username, $mybb->settings['bbname'], $subject, $excerpt, $mybb->settings['bburl'], $tid);
			mymail($subscribedmember['email'], $emailsubject, $emailmessage);
		}
		// Start Auto Subscribe
		if($postoptions['emailnotify'] != "no")
		{
			$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."favorites WHERE type='s' AND tid='$tid' AND uid='".$mybb->user[uid]."'");
			$subcheck = $db->fetch_array($query);
			if(!$subcheck['uid'])
			{
				$db->query("INSERT INTO ".TABLE_PREFIX."favorites (fid,uid,tid,type) VALUES (NULL,'".$mybb->user[uid]."','$tid','s')");
			}
		}
	}
	
	if(!$replyto)
	{ // If we dont have a post to reply to, lets make it the first one :)
		$query = $db->query("SELECT pid FROM ".TABLE_PREFIX."posts WHERE tid='$tid' ORDER BY dateline ASC LIMIT 0,1");
		$repto = $db->fetch_array($query);
		$replyto = $repto['pid'];
	}
	$message = addslashes($_POST['message']);
	$subject = addslashes($_POST['subject']);

	$username = addslashes($username);
	// Do moderator options
	if(ismod($fid) == "yes" && !$savedraft)
	{
		$modlogdata['fid'] = $thread['fid'];
		$modlogdata['tid'] = $thread['tid'];
		if($modoptions['closethread'] == "yes" && $thread['closed'] != "yes")
		{
			$newclosed = "closed='yes'";
			logmod($modlogdata, "Thread closed");
		}
		if($modoptions['closethread'] != "yes" && $thread['closed'] == "yes")
		{
			$newclosed = "closed='no'";
			logmod($modlogdata, "Thread opened");
		}
		if($modoptions['stickthread'] == "yes" && $thread['sticky'] != 1)
		{
			$newstick = "sticky='1'";
			logmod($modlogdata, "Thread stuck");
		}
		if($modoptions['stickthread'] != "yes" && $thread['sticky'])
		{
			$newstick = "sticky='0'";
			logmod($modlogdata, "Thread unstuck");
		}
		if($newstick && $newclosed) { $sep = ","; }
		if($newstick || $newclosed)
		{
			$db->query("UPDATE ".TABLE_PREFIX."threads SET $newclosed$sep$newstick WHERE tid='$tid'");
		}
	}
	if($savedraft)
	{
		$visible = -2;
	}
	elseif($forum['modposts'] == "yes" && $mybb->usergroup['cancp'] != "yes")
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
		$db->query("UPDATE ".TABLE_PREFIX."posts SET subject='$subject', icon='$icon', username='$username', dateline='$now', message='$message', ipaddress='$ipaddress', includesig='$postoptions[signature]', smilieoff='$postoptions[disablesmilies]', visible='$visible' WHERE pid='$pid'");
	}
	else
	{
		$db->query("INSERT INTO ".TABLE_PREFIX."posts (pid,tid,replyto,fid,subject,icon,uid,username,dateline,message,ipaddress,includesig,smilieoff,visible) VALUES (NULL,'$tid','$replyto','$fid','$subject','$icon','$author','$username','$now','$message','$ipaddress','$postoptions[signature]','$postoptions[disablesmilies]','$visible')");
		$pid = $db->insert_id();
	}
	
	if($visible == -2)
	{
		$lang->redirect_newreply = $lang->draft_saved;
		$url = "usercp.php?action=drafts";
	}
	elseif($visible == 1)
	{
		$lang->redirect_newreply .= $lang->redirect_newreply_post;
		$url = "showthread.php?tid=$tid&pid=$pid#pid$pid";
		updatethreadcount($tid);
		updateforumcount($fid);
		$cache->updatestats();
	}
	else
	{
		$lang->redirect_newreply .= $lang->redirect_newreply_moderation;
		$url = "showthread.php?tid=$tid";
	}
	
	if(!$savedraft)
	{
		$now = time();
		if($forum['usepostcounts'] != "no")
		{
				$queryadd = ",postnum=postnum+1";
		}
		else
		{
			$queryadd = "";
		}
		$db->query("UPDATE ".TABLE_PREFIX."users SET lastpost='$now' $queryadd WHERE uid='$author'");

		if(function_exists("replyPosted"))
		{
			replyPosted($pid);
		}
	}
	// Setup the correct ownership of the attachments
	if($posthash)
	{
		$db->query("UPDATE ".TABLE_PREFIX."attachments SET pid='$pid' WHERE posthash='$posthash'");
	}
	redirect($url, $lang->redirect_newreply);
}
?>