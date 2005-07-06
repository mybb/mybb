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

// Load global language phrases
$lang->load("newthread");

if($mybb->input['action'] == "editdraft" || ($mybb->inputp['savedraft'] && $mybb->input['tid']) || ($mybb->input['tid'] && $mybb->input['pid']))
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
	error($lang->error_invalidforum);
}

if($forumpermissions['canview'] == "no" || $forumpermissions['canpostthreads'] == "no")
{
	nopermission();
}
// Password protected forums ......... yhummmmy!
checkpwforum($fid, $forum['password']);

if($mybb->settings['bbcodeinserter'] != "off" && $forum['allowmycode'] != "no" && $mybb->user['showcodebuttons'] != 0)
{
	$codebuttons = makebbcodeinsert();
}
if($forum['allowsmilies'] != "no")
{
	$smilieinserter = makesmilieinsert();
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
		$username = "Guest";
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
	remove_attachment(0, $posthash, $mybb->input['removeattachment']);
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
		if($postoptions['disablesmilies'] == "yes")
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
	if($mybb->input['previewpost'] || $maximageserror)
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

	if($mybb->input['previewpost'])
	{
		if(!$mybb->input['username'])
		{
			$mybb->input['username'] = "Guest";
		}
		if($mybb->input['username'] && !$mybb->user['uid'])
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['username'])."'");
			$user = $db->fetch_array($query);
			if($user['password'] == md5($mybb->input['password']) && $user['username'])
			{
				$mybb->user['username'] = $user['username'];
				$mybb->user['uid'] = $user['uid'];
			}
		}
		$query = $db->query("SELECT u.*, f.*, i.path as iconpath, i.name as iconname FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid='".$mybb->input['icon']."') WHERE u.uid='".$mybb->user[uid]."'");
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
		$post['subject'] = $subject;
		$post['icon'] = $icon;
		$post['smilieoff'] = $postoptions['disablesmilies'];
		$post['dateline'] = time();
		$postbit = makepostbit($post, 1);
		eval("\$preview = \"".$templates->get("previewpost")."\";");
		$message = htmlspecialchars_uni($mybb->input['message']);
		$subject = htmlspecialchars_uni($mybb->input['subject']);
	}

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

	// Can we disable smilies or are they disabled already?
	if($forum['allowsmilies'] != "no")
	{
		eval("\$disablesmilies = \"".$templates->get("newthread_disablesmilies")."\";");
	}
	else
	{
		$disablesmilies = "<input type=\"hidden\" name=\"postoptions[disablesmilies]\" value=\"no\" />";
	}
	
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
		eval("\$modoptions = \"".$templates->get("newreply_modoptions")."\";");
	}

	if($forumpermissions['canpostattachments'] != "no")
	{ // Get a listing of the current attachments, if there are any
		$attachcount = 0;
		if($mybb->input['action'] == "editdraft")
		{
			$attachwhere = "pid='$pid'";
		}
		else
		{
			$attachwhere = "posthash='".addslashes($mybb->input['posthash'])."'";
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

	if($forumpermissions['canpostpolls'] != "no")
	{
		$lang->max_options = sprintf($lang->max_options, $mybb->settings['maxpolloptions']);
		eval("\$pollbox = \"".$templates->get("newthread_postpoll")."\";");
	}
	$lang->newthread_in = sprintf($lang->newthread_in, $thread['subject']);
	eval("\$newthread = \"".$templates->get("newthread")."\";");
	outputpage($newthread);
		
}
if($mybb->input['action'] == "do_newthread")
{
	if($mybb->user['uid'] == 0)
	{
		$username = htmlspecialchars_uni($mybb->input['username']);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['username'])."'");
		$member = $db->fetch_array($query);
		if($member['uid'])
		{
			if(!$mybb->input['password'])
			{
				error($lang->error_usernametaken);
			}
			elseif($member['password'] != md5($mybb->input['password']))
			{
				error($lang->error_invalidpass);
			}
			$username = $member['username'];
			$uid = $member['uid'];
			$mybb->user = $member;
			mysetcookie("mybb[uid]", $member['uid']);
			mysetcookie("mybb[password]", $member['password']);
		}
		else
		{
			if(!$mybb->input['username'])
			{
				$username = "Guest";
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
		if(strlen(trim($mybb->input['subject'])) > 85)
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
			"username" => addslashes(htmlspecialchars_uni($mybb->user['username'])),
			"dateline" => time(),
			"lastpost" => time(),
			"lastposter" => addslashes(htmlspecialchars_uni($mybb->user['username'])),
			"visible" => $visible
			);
		$db->update_query(TABLE_PREFIX."threads", $newthread, "tid='$tid'");
		
		$newpost = array(
			"subject" => addslashes($mybb->input['subject']),
			"icon" => intval($mybb->input['icon']),
			"username" => addslashes(htmlspecialchars_uni($mybb->user['username'])),
			"dateline" => time(),
			"message" => addslashes($mybb->input['message']),
			"ipaddress" => $ipaddress,
			"includesig" => $postoptions['signature'],
			"smilieoff" => $postoptions['disablesmilies'],
			"visible" => $visible
			);

		$db->update_query(TABLE_PREFIX."posts", $newpost, "pid='$pid'");
	}
	else
	{
		$newthread = array(
			"tid" => "NULL",
			"fid" => $fid,
			"subject" => addslashes($mybb->input['subject']),
			"icon" => intval($mybb->input['icon']),
			"uid" => $mybb->user['uid'],
			"username" => addslashes(htmlspecialchars_uni($mybb->user['username'])),
			"dateline" => time(),
			"lastpost" => time(),
			"lastposter" => addslashes(htmlspecialchars_uni($mybb->user['username'])),
			"views" => 0,
			"replies" => 0,
			"visible" => $visible
			);
		$db->insert_query(TABLE_PREFIX."threads", $newthread);
		$tid = $db->insert_id();

		$newpost = array(
			"pid" => "NULL",
			"tid" => $tid,
			"fid" => $fid,
			"subject" => addslashes($mybb->input['subject']),
			"icon" => intval($mybb->input['icon']),
			"uid" => $mybb->user['uid'],
			"username" => addslashes(htmlspecialchars_uni($mybb->user['username'])),
			"dateline" => time(),
			"message" => addslashes($mybb->input['message']),
			"ipaddress" => $ipaddress,
			"includesig" => $postoptions['signature'],
			"smilieoff" => $postoptions['disablesmilies'],
			"visible" => $visible
			);
		$db->insert_query(TABLE_PREFIX."posts", $newpost);
		$pid = $db->insert_id();
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
	if(!$savedraft)
	{
		$excerpt = substr($mybb->input['message'], 0, $mybb->settings['subscribeexcerpt'])."... (visit the thread to read more..)";
		$query = $db->query("SELECT dateline FROM ".TABLE_PREFIX."threads WHERE fid='$fid' ORDER BY dateline DESC LIMIT 1");
		$lastpost = $db->fetch_array($query);
		$query = $db->query("SELECT u.username, u.email, u.uid, u.language FROM ".TABLE_PREFIX."forumsubscriptions fs, ".TABLE_PREFIX."users u WHERE fs.fid='$fid' AND u.uid=fs.uid AND fs.uid!='".$mybb->user[uid]."' AND u.lastactive>'$lastpost[dateline]'");
		while($subscribedmember = $db->fetch_array($query))
		{
			if(empty($subscribedmember['language']) && !empty($mybb->user['language']))
			{
				$subscribedmember['language'] = $mybb->settings['bblanguage'];
			}
			if($subscribedmember['language'] == $mybb->user['language'])
			{
				$userlang = &$lang;
			}
			else
			{
				$userlang = new MyLanguage;
				$userlang->setPath("./inc/languages");
				$userlang->setLanguage($subscribedmember['language']);
				$userlang->load("messages");
			}
			$emailsubject = sprintf($userlang->emailsubject_forumsubscription, $forum['name']);
			$emailmessage = sprintf($userlang->email_forumsubcription, $subscribedmember['username'], $mybb->user['username'], $forum['name'], $mybb->settings['bbname'], $mybb->input['subject'], $excerpt, $mybb->settings['bburl'], $tid);
			mymail($subscribedmember['email'], $emailsubject, $emailmessage);
			unset($userlang);
		}

		// Start Auto Subscribe
		if($postoptions['emailnotify'] != "no")
		{
			$db->query("INSERT INTO ".TABLE_PREFIX."favorites (fid,uid,tid,type) VALUES (NULL,'".$mybb->user['uid']."','$tid','s')");
		}
		if(!$mybb->input['postpoll'])
		{
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
	}

	if($savedraft)
	{
		$lang->redirect_newthread = $lang->draft_saved;
		$url = "usercp.php?action=drafts";
	}
	elseif($mybb->input['postpoll'] && $forumpermissions['canpostpolls'])
	{
		$url = "polls.php?action=newpoll&tid=$tid&polloptions=".intval($mybb->input['numpolloptions']);
		$db->query("UPDATE ".TABLE_PREFIX."threads SET visible='-1' WHERE tid='$tid'");
		$lang->redirect_newthread .= $lang->redirect_newthread_poll;
	}
	elseif(!$visible)
	{
		$lang->redirect_newthread .= $lang->redirect_newthread_moderation;
		$url = "forumdisplay.php?fid=$fid";
	}
	else
	{
		$lang->redirect_newthread .= $lang->redirect_newthread_thread;
		$url = "showthread.php?tid=$tid";
		$cache->updatestats();
		updatethreadcount($tid);
		updateforumcount($fid);
	}

	if(function_exists("threadPosted") && !$savedraft)
	{
		threadPosted($tid);
	}

	redirect($url, $lang->redirect_newthread);

}
?>
