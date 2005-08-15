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

require "./global.php";
require "./inc/functions_post.php";

// Load global language phrases
$lang->load("moderation");

$plugins->run_hooks("moderation_start");

// Get some navigation if we need it
switch($mybb->input['action'])
{
	case "reports":
//		addnav($lang->moderator_cp, "moderation.php");
		addnav($lang->reported_posts);
		break;
}
$tid = intval($mybb->input['tid']);
$pid = intval($mybb->input['pid']);
$fid = intval($mybb->input['fid']);

if($pid)
{
	$post = getpost($pid);
	$tid = $post['tid'];
	if(!$post['pid'])
	{
		error($lang->error_invalidpost);
	}
}

if($tid)
{
	$thread = getthread($tid);
	$fid = $thread['fid'];
	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
}

if($fid)
{
	$modlogdata['fid'] = $fid;
	$forum = getforum($fid);

	// Make navigation
	makeforumnav($fid);
}

if($tid)
{
	addnav(dobadwords($thread['subject']), "showthread.php?tid=$thread[tid]");
	$modlogdata['tid'] = $tid;
}

// Get our permissions all nice and setup
$permissions = forum_permissions($fid);

if($fid)
{
	// Password protected forums ......... yhummmmy!
	checkpwforum($fid, $forum['password']);
}

if($mybb->user['uid'] != 0)
{
	eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
}
else
{
	eval("\$loginbox = \"".$templates->get("loginbox")."\";");
}

// Begin!
switch($mybb->input['action'])
{
	// Open or close a thread
	case "openclosethread":
		if(ismod($fid, "canopenclosethreads") != "yes")
		{
			nopermission();
		}
		if($thread['closed'] == "yes")
		{
			$openclose = "opened";
			$thread['closed'] = "no";
			$redirect = $lang->redirect_openthread;
		}
		else
		{
			$openclose = "closed";
			$thread['closed'] = "yes";
			$redirect = $lang->redirect_closethread;
		}
		if($openclose == "opened")
		{
			$openclose = $lang->opened;
		}
		else
		{
			$openclose = $lang->closed;
		}
		$lang->mod_process = sprintf($lang->mod_process, $openclose);

		$plugins->run_hooks("moderation_openclosethread");

		logmod($modlogdata, $lang->mod_process);
		$db->query("UPDATE ".TABLE_PREFIX."threads SET closed='$thread[closed]' WHERE tid='$tid'");
		redirect("showthread.php?tid=$tid", $redirect);
		break;
	
	// Stick or unstick that post to the top bab!
	case "stick";
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		if($thread['sticky'] == 1)
		{
			$stuckunstuck = "unstuck";
			$thread['sticky'] = "0";
			$redirect = $lang->redirect_unstickthread;
		}
		else
		{
			$stuckunstuck = "stuck";
			$thread['sticky'] = "1";
			$redirect = $lang->redirect_stickthread;
		}
		if($stuckunstuck == "unstuck")
		{
			$stuckunstuck = $lang->unstuck;
		}
		else
		{
			$stuckunstuck = $lang->stuck;
		}
		$lang->mod_process = sprintf($lang->mod_process, $stuckunstuck);

		$plugins->run_hooks("moderation_stick");

		logmod($modlogdata, $lang->mod_process);
		$db->query("UPDATE ".TABLE_PREFIX."threads SET sticky='$thread[sticky]' WHERE tid='$tid'");
		redirect("showthread.php?tid=$tid", $redirect);
		break;

	// Remove redirects to a specific thread
	case "removeredirects":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}

		$plugins->run_hooks("moderation_removeredirects");

		$db->query("DELETE FROM ".TABLE_PREFIX."threads WHERE closed='moved|$tid'");
	
		updateforumcount($fid);
		logmod($modlogdata, $lang->redirects_removed);
		redirect("showthread.php?tid=$tid", $lang->redirect_redirectsremoved);
		break;

	// Delete thread confirmation page
	case "deletethread":
		addnav($lang->nav_deletethread);

		if(ismod($fid, "candeleteposts") != "yes")
		{
			if($permissions['candeletethreads'] != "yes" || $mybb->user['uid'] != $thread['uid'])
			{
				nopermission();
			}
		}

		$plugins->run_hooks("moderation_deletethread");

		eval("\$deletethread = \"".$templates->get("moderation_deletethread")."\";");
		outputpage($deletethread);
		break;

	// Delete the actual thread here
	case "do_deletethread":
		if(ismod($fid, "candeleteposts") != "yes")
		{
			if($permissions['candeletethreads'] != "yes" || $mybb->user['uid'] != $thread['uid'])
			{
				nopermission();
			}
		}

		$plugins->run_hooks("moderation_do_deletethread");

		$thread['subject'] = addslashes($thread['subject']);
		$lang->thread_deleted = sprintf($lang->thread_deleted, $thread['subject']);
		logmod($modlogdata, $lang->thread_deleted);
		deletethread($tid);
		updateforumcount($fid);
		markreports($tid, "thread");
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_threaddeleted);
		break;

	// Delete the poll from a thread confirmation page
	case "deletepoll":
		addnav($lang->nav_deletepoll);

		if(ismod($fid, "candeleteposts") != "yes")
		{
			if($permissions['candeletethreads'] != "yes" || $mybb->user['uid'] != $thread['uid'])
			{
				nopermission();
			}
		}

		$plugins->run_hooks("moderation_deletepoll");

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."polls WHERE tid='$tid'");
		$poll = $db->fetch_array($query);
		if(!$poll['pid'])
		{
			error($lang->error_invalidpoll);
		}

		eval("\$deletepoll = \"".$templates->get("moderation_deletepoll")."\";");
		outputpage($deletepoll);
		break;

	// Delete the actual poll here!
	case "do_deletepoll":
		if(ismod($fid, "candeleteposts") != "yes")
		{
			if($permissions['candeletethreads'] != "yes" || $mybb->user['uid'] != $thread['uid'])
			{
				nopermission();
			}
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."polls WHERE tid='$tid'");
		$poll = $db->fetch_array($query);
		if(!$poll['pid'])
		{
			error($lang->error_invalidpoll);
		}

		$plugins->run_hooks("moderation_do_deletepoll");

		$lang->poll_deleted = sprintf($lang->poll_deleted, $thread['subject']);
		logmod($modlogdata, $lang->poll_deleted);
		$db->query("DELETE FROM ".TABLE_PREFIX."polls WHERE pid='$poll[pid]'");
		$db->query("DELETE FROM ".TABLE_PREFIX."pollvotes WHERE pid='$poll[pid]'");
		$db->query("UPDATE ".TABLE_PREFIX."threads SET poll='' WHERE poll='$poll[pid]'");
		redirect("showthread.php?tid=$tid", $lang->redirect_polldeleted);
		break;

	// Approve a thread
	case "approvethread":
		if(ismod($fid, "canopenclosethreads") != "yes")
		{
			nopermission();
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
		$thread = $db->fetch_array($query);

		$plugins->run_hooks("moderation_approvethread");

		$lang->thread_approved = sprintf($lang->thread_approved, $thread['subject']);
		logmod($modlogdata, $lang->thread_approved);
		$db->query("UPDATE ".TABLE_PREFIX."threads SET visible='1' WHERE tid='$tid'");
		$db->query("UPDATE ".TABLE_PREFIX."posts SET visible='1' WHERE tid='$tid' AND replyto='0'");
		$cache->updatestats();
		updateforumcount($fid);
		redirect("showthread.php?tid=$tid", $lang->redirect_threadapproved);
		break;
	
	// Unapprove a thread
	case "unapprovethread":
		if(ismod($fid, "canopenclosethreads") != "yes")
		{
			nopermission();
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='tid'");
		$thread = $db->fetch_array($query);

		$plugins->run_hooks("moderation_unapprovethread");

		$lang->thread_unapproved = sprintf($lang->thread_unapproved, $thread['subject']);
		logmod($modlogdata, $lang->thread_unapproved);
		$db->query("UPDATE ".TABLE_PREFIX."threads SET visible='0' WHERE tid='$tid'");
		$db->query("UPDATE ".TABLE_PREFIX."posts SET visible='0' WHERE tid='$tid' AND replyto='0'");
		updateforumcount($fid);
		redirect("showthread.php?tid=$tid", $lang->redirect_threadunapproved);
		break;

	// Delete selective posts in a thread
	case "deleteposts":
		addnav($lang->nav_deleteposts);
		if(ismod($fid, "candeleteposts") != "yes")
		{
			nopermission();
		}
		$query = $db->query("SELECT p.*, u.* FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid) WHERE tid='$tid' ORDER BY dateline ASC");	
		$altbg = "trow1";
		while($post = $db->fetch_array($query))
		{
			$postdate = mydate($mybb->settings['dateformat'], $post['dateline']);
			$posttime = mydate($mybb->settings['timeformat'], $post['dateline']);
			$message = stripslashes($post['message']);
			if($post['smilieoff'] == "yes")
			{
				$allowsmilies = "no";
			}
			else
			{
				$allowsmilies = $forum['allowsmilies'];
			}
			$message = postify($message, $forum['allowhtml'], $forum['allowmycode'], $allowsmilies, $forum['allowimgcode']);
			eval("\$posts .= \"".$templates->get("moderation_deleteposts_post")."\";");
			if($altbg == "trow1")
			{
				$altbg = "trow2";
			}
			else
			{
				$altbg = "trow1";
			}
		}

		$plugins->run_hooks("moderation_deleteposts");

		eval("\$deleteposts = \"".$templates->get("moderation_deleteposts")."\";");
		outputpage($deleteposts);
		break;

	// Lets delete those selected posts!
	case "do_deleteposts":
		if(ismod($fid, "candeleteposts") != "yes")
		{
			nopermission();
		}

		$plugins->run_hooks("moderation_do_deleteposts");

		$deletethread = "1";
		$deletepost = $mybb->input['deletepost'];
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid'");
		while($post = $db->fetch_array($query))
		{
			if($deletepost[$post['pid']] == "yes")
			{
				deletepost($post['pid']);
				$deletecount++;
				$plist[] = $post['pid'];
			}
		else
			{
				$deletethread = "0";
			}
		}
		if($deletethread)
		{
			deletethread($tid);
			$url = "forumdisplay.php?fid=$fid";
			markreports($plist, "posts");
		}
		else
		{
			updatethreadcount($tid);
			$url = "showthread.php?tid=$tid";
			markreports($tid, "thread");
		}
		$lang->deleted_selective_posts = sprintf($lang->deleted_selective_posts, $deletecount);
		logmod($modlogdata, $lang->deleted_selective_posts);
		updateforumcount($fid);
		redirect($url, $lang->redirect_postsdeleted);
		break;
	
	// Merge selected posts selection screen
	case "mergeposts":
		addnav($lang->nav_mergeposts);

		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$query = $db->query("SELECT p.*, u.* FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid) WHERE tid='$tid' ORDER BY dateline ASC");	
		$altbg = "trow1";
		while($post = $db->fetch_array($query))
		{
			$postdate = mydate($mybb->settings['dateformat'], $post['dateline']);
			$posttime = mydate($mybb->settings['timeformat'], $post['dateline']);
			$message = stripslashes($post['message']);
			if($post['smilieoff'] == "yes")
			{
				$allowsmilies = "no";
			}
			else
			{
				$allowsmilies = $forum['allowsmilies'];
			}
			$message = postify($message, $forum['allowhtml'], $forum['allowmycode'], $allowsmilies, $forum['allowimgcode']);
			eval("\$posts .= \"".$templates->get("moderation_mergeposts_post")."\";");
			if($altbg == "trow1")
			{
				$altbg = "trow2";
			}
			else
			{
				$altbg = "trow1";
			}
		}

		$plugins->run_hooks("moderation_mergeposts");

		eval("\$mergeposts = \"".$templates->get("moderation_mergeposts")."\";");
		outputpage($mergeposts);
		break;

	// Lets merge those selected posts!
	case "do_mergeposts":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}

		$plugins->run_hooks("moderation_do_mergeposts");

		$mergepost = $mybb->input['mergepost'];
		if(count($mergepost) <= 1)
		{
			error($lang->error_nomergeposts);
		}
		$comma = "";
		while(list($pid, $yes) = @each($mergepost))
		{
			if($yes == "yes")
			{
				$pidin .= "$comma'$pid'";
				$comma = ",";
				$plist[] = $pid;
			}
		}
		$first = 1;
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid' AND pid IN($pidin) ORDER BY dateline ASC");
		while($post = $db->fetch_array($query))
		{
			if($first == 1)
			{ // all posts will be merged into this one
				$masterpid = $post['pid'];
				$message = $post['message'];
			}
			else
			{ // these are the selected posts
				$message .= "[hr]$post[message]";
			}
			$first = 0;
		}
		$message = addslashes($message);
		$db->query("UPDATE ".TABLE_PREFIX."posts SET message='$message' WHERE pid='$masterpid'");
		$db->query("DELETE FROM ".TABLE_PREFIX."posts WHERE pid IN($pidin) AND pid!='$masterpid'");
		$db->query("UPDATE ".TABLE_PREFIX."posts SET pid='$masterpid' WHERE pid IN($pidin)");
		$db->query("UPDATE ".TABLE_PREFIX."attachments SET pid='$pid' WHERE pid IN($pidin)");
		updatethreadcount($tid);
		updateforumcount($fid);
		markreports($plist, "posts");
		logmod($modlogdata, $lang->merged_selective_posts);
		redirect("showthread.php?tid=$tid", $lang->redirect_mergepsots);
		break;
	
	// Move a thread
	case "move":
		addnav($lang->nav_move);
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}

		$plugins->run_hooks("moderation_move");

		$forumselect = makeforumjump("", "", 1, "", 0, "", "moveto");
		eval("\$movethread = \"".$templates->get("moderation_move")."\";");
		outputpage($movethread);
		break;

	// Lets get this thing moving!
	case "do_move":
		$moveto = intval($mybb->input['moveto']);
		$method = $mybb->input['method'];

		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		if(ismod($moveto, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$newperms = forum_permissions($moveto);
		if($newperms['canview'] == "no")
		{
			nopermission();
		}
		$db->query("DELETE FROM ".TABLE_PREFIX."threads WHERE closed='moved|$tid' AND fid='$moveto'");
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$moveto'");
		$newforum = $db->fetch_array($query);
		if($newforum['type'] != "f")
		{
			error($lang->error_invalidforum);
		}
		if($method != "copy" && $thread['fid'] == $moveto)
		{
			error($lang->error_movetosameforum);
		}
		if($method == "move")
		{ // plain move thread

			$plugins->run_hooks("moderation_do_move_simple");

			$db->query("UPDATE ".TABLE_PREFIX."threads SET fid='$moveto' WHERE tid='$tid'");
			$db->query("UPDATE ".TABLE_PREFIX."posts SET fid='$moveto' WHERE tid='$tid'");
			logmod($modlogdata, $lang->thread_moved);
		}
		elseif($method == "redirect")
		{ // move (and leave redirect) thread

			$plugins->run_hooks("moderation_do_move_redirect");

			$db->query("UPDATE ".TABLE_PREFIX."threads SET fid='$moveto' WHERE tid='$tid'");
			$db->query("UPDATE ".TABLE_PREFIX."posts SET fid='$moveto' WHERE tid='$tid'");
			$threadarray = array(
				"tid" => "NULL",
				"fid" => $thread['fid'],
				"subject" => addslashes($thread['subject']),
				"icon" => $thread['icon'],
				"uid" => $thread['uid'],
				"username" => addslashes($thread['username']),
				"dateline" => $thread['dateline'],
				"lastpost" => $thread['lastpost'],
				"lastposter" => addslashes($thread['lastposter']),
				"views" => $thread['views'],
				"replies" => $thread['replies'],
				"closed" => "moved|$tid",
				"sticky" => $thread['sticky'],
				"visible" => 1
				);
			$db->insert_query(TABLE_PREFIX."threads", $threadarray);
			logmod($modlogdata, $lang->thread_moved);
		}
		else
		{ // copy thread
		// we need to add code to copy attachments(?), polls, etc etc here
			$threadarray = array(
				"tid" => "NULL",
				"fid" => $thread['fid'],
				"subject" => addslashes($thread['subject']),
				"icon" => $thread['icon'],
				"uid" => $thread['uid'],
				"username" => addslashes($thread['username']),
				"dateline" => $thread['dateline'],
				"lastpost" => $thread['lastpost'],
				"lastposter" => addslashes($thread['lastposter']),
				"views" => $thread['views'],
				"replies" => $thread['replies'],
				"closed" => $thread['closed'],
				"sticky" => $thread['sticky'],
				"visible" => $thread['visible']
				);
			$plugins->run_hooks("moderation_do_move_copy");
			$db->insert_query(TABLE_PREFIX."threads", $threadarray);
			$newtid = $db->insert_id();
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid'");
			while($post = $db->fetch_array($query))
			{
				if($postssql)
				{
					$postssql .= ", ";
				}
				$post['message'] = addslashes($post['message']);
				$postssql .= "(NULL,'$newtid','$moveto','$post[subject]','$post[icon]','$post[uid]','$post[username]','$post[dateline]','$post[message]','$post[ipaddress]','$post[includesig]','$post[smilieoff]','$post[edituid]','$post[edittime]','1')";
			}
			$db->query("INSERT INTO ".TABLE_PREFIX."posts (pid,tid,fid,subject,icon,uid,username,dateline,message,ipaddress,includesig,smilieoff,edituid,edittime,visible) VALUES $postssql");
			logmod($modlogdata, $lang->thread_copied);

			update_first_post($newtid);
		}
		$query = $db->query("SELECT COUNT(p.pid) AS posts, u.uid FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE tid='$tid' GROUP BY u.uid ORDER BY posts DESC");
		while($posters = $db->fetch_array($query))
		{
			if($method == "copy" && $newforum['usepostcounts'] != "no")
			{
				$pcount = "+$posters[posts]";
			}
			if($method != "copy" && ($newforum['usepostcounts'] != "no" && $forum['usepostcounts'] == "no"))
			{
				$pcount = "+$posters[posts]";
			}
			if($method != "copy" && ($newforum['usepostcounts'] == "no" && $forum['usepostcounts'] != "no"))
			{
				$pcount = "-$posters[posts]";
			}
			$db->query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum$pcount WHERE uid='$posters[uid]'");
		}
		updateforumcount($moveto);
		updateforumcount($fid);
		redirect("showthread.php?tid=$tid", $lang->redirect_threadmoved);
		break;
	
	// Thread notes editor
	case "threadnotes":
		addnav($lang->nav_threadnotes);
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$thread['notes'] = htmlspecialchars_uni(dobadwords($thread['notes']));
		$trow = "trow1";
		$query = $db->query("SELECT l.*, u.username, t.subject AS tsubject, f.name AS fname, p.subject AS psubject FROM ".TABLE_PREFIX."moderatorlog l LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid) LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid) LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=l.fid) LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=l.pid) WHERE t.tid='$tid' ORDER BY l.dateline DESC LIMIT  0, 20");
		while($modaction = $db->fetch_array($query))
		{
			$modaction['dateline'] = mydate("jS M Y, G:i", $modaction['dateline']);
			$info = "";
			if($modaction['tsubject'])
			{
				$info .= "<b>$lang->thread</b> <a href=\"showthread.php?tid=$modaction[tid]\" target=\"_blank\">$modaction[tsubject]</a><br>";
			}
			if($modaction['fname'])
			{
				$info .= "<b>$lang->forum</b> <a href=\"forumdisplay.php?fid=$modaction[fid]\" target=\"_blank\">$modaction[fname]</a><br>";
			}
			if($modaction['psubject'])
			{
				$info .= "<b>$lang->post</b> <a href=\"showthread.php?tid=$modaction[tid]&pid=$modaction[pid]#pid$modaction[pid]\">$modaction[psubject]</a>";
			}
	
			eval("\$modactions .= \"".$templates->get("moderation_threadnotes_modaction")."\";");
			if($trow == "trow2")
			{
				$trow = "trow1";
			}
			else
			{
				$trow = "trow2";
			}
		}
		if(!$modactions)
		{
			$modactions = "<tr><td class=\"trow1\" colspan=\"4\">$lang->no_mod_options</td></tr>";
		}

		$plugins->run_hooks("moderation_threadnotes");

		eval("\$threadnotes = \"".$templates->get("moderation_threadnotes")."\";");
		outputpage($threadnotes);
		break;
	
	// Update the thread notes!
	case "do_threadnotes":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}

		$plugins->run_hooks("moderation_do_threadnotes");

		logmod($modlogdata, $lang->thread_notes_edited);
		$thread['notes'] = addslashes($mybb->input['threadnotes']);
		$db->query("UPDATE ".TABLE_PREFIX."threads SET notes='".$thread['notes']."' WHERE tid='$tid'");
		redirect("showthread.php?tid=$tid", $lang->redirect_threadnotesupdated);
		break;

	// Lets look up the ip address of a post
	case "getip":
		addnav($lang->nav_getip);
		if(ismod($fid, "canviewips") != "yes")
		{
			nopermission();
		}
	
		$hostname = @gethostbyaddr($post['ipaddress']);
		if(!$hostname || $hostname == $post['ipaddress'])
		{
			$hostname = $lang->resolve_fail;
		}
		eval("\$getip = \"".$templates->get("moderation_getip")."\";");
		outputpage($getip);
		break;

	// Merge threads
	case "merge":
		addnav($lang->nav_merge);
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}

		$plugins->run_hooks("moderation_merge");

		eval("\$merge = \"".$templates->get("moderation_merge")."\";");
		outputpage($merge);
		break;

	// Lets get those threads together baby! (Merge threads)
	case "do_merge":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}

		$plugins->run_hooks("moderation_do_merge");
	
		// get thread to merge's tid
		$splitloc = explode(".php", $mybb->input['threadurl']);
		$temp = explode("&", substr($splitloc[1], 1));
		for ($i = 0; $i < count($temp); $i++)
		{
			$temp2 = explode("=", $temp[$i], 2);
			$parameters[$temp2[0]] = $temp2[1];
		}
		if($parameters['pid'] && !$parameters['tid'])
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE pid='$pid'");
			$post = $db->fetch_array($query);
			$mergetid = $post['tid'];
		}
		elseif($parameters['tid'])
		{
			$mergetid = $parameters['tid'];
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='$mergetid'");
		$mergethread = $db->fetch_array($query);
		if(!$mergethread['tid'])
		{
			error($lang->error_badmergeurl);
		}
		if($mergetid == $tid)
		{ // sanity check
			error($lang->error_mergewithself);
		}
		if(ismod($mergethread['fid'], "canmanagethreads") != "yes")
		{
			nopermission();
		}
	
		$pollsql = "";
		if($mergethread['poll'])
		{
			$pollsql = ", poll='$mergethread[poll]'";
			$db->query("UPDATE ".TABLE_PREFIX."polls SET tid='$tid' WHERE tid='$mergethread[tid]'");
		}
		else
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE poll='$mergethread[poll]' AND tid!='$mergethread[tid]'");
			$pollcheck = $db->fetch_array($query);
			if(!$pollcheck['poll'])
			{
				$db->query("DELETE FROM ".TABLE_PREFIX."polls WHERE pid='$mergethread[poll]'");
				$db->query("DELETE FROM ".TABLE_PREFIX."pollvotes WHERE pid='$mergethread[poll]'");
			}
		}
		if($subject)
		{
			$subject = $mybb->input['subject'];
		}
		else
		{
			$subject = $thread['subject'];
		}
		$subject = addslashes($subject);
		$db->query("UPDATE ".TABLE_PREFIX."posts SET tid='$tid', fid='$fid' WHERE tid='$mergetid'");
		$db->query("UPDATE ".TABLE_PREFIX."threads SET subject='$subject' $pollcode WHERE tid='$tid'");
		$db->query("UPDATE ".TABLE_PREFIX."threads SET closed='moved|$tid' WHERE closed='moved|$mergetid'");
		$db->query("UPDATE ".TABLE_PREFIX."favorites SET tid='$tid' WHERE tid='$mergetid'");
		update_first_post($tid);
		logmod($modlogdata, $lang->thread_merged);
		deletethread($mergetid);
		updatethreadcount($tid);
		if($fid != $mergethread['fid'])
		{
			updateforumcount($mergethread['fid']);
		}
		updateforumcount($fid);
		redirect("showthread.php?tid=$tid", $lang->redirect_threadsmerged);
		break;

	// Divorce the posts in this thread (Split!)
	case "split":
		addnav($lang->nav_split);
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$query = $db->query("SELECT p.*, u.* FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid) WHERE tid='$tid' ORDER BY dateline ASC");	
		$numposts = $db->num_rows($query);
		if($numposts <= "1")
		{
			error($lang->error_cantsplitonepost);
		}
	
		$altbg = "trow1";
		while($post = $db->fetch_array($query))
		{
			$postdate = mydate($mybb->settings['dateformat'], $post['dateline']);
			$posttime = mydate($mybb->settings['timeformat'], $post['dateline']);
			$message = stripslashes($post['message']);
			if($post['smilieoff'] == "yes")
			{
				$allowsmilies = "no";
			}
			else
			{
				$allowsmilies = $forum['allowsmilies'];
			}
			$message = postify($message, $forum['allowhtml'], $forum['allowmycode'], $allowsmilies, $forum['allowimgcode']);
			eval("\$posts .= \"".$templates->get("moderation_split_post")."\";");
			if($altbg == "trow1")
			{
				$altbg = "trow2";
			}
			else
			{
				$altbg = "trow1";
			}
		}
		$forumselect = makeforumjump("", $fid, 1, "", 0, "", "moveto");

		$plugins->run_hooks("moderation_split");

		eval("\$split = \"".$templates->get("moderation_split")."\";");
		outputpage($split);
		break;

	// Lets break them up buddy! (Do the split)
	case "do_split":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}

		$plugins->run_hooks("moderation_do_split");

		$numyes = "0";
		$numno = "0";
		while(list($key, $val) = each($mybb->input['splitpost']))
		{
			if($val == "yes")
			{
				$numyes++;
			}
			else
			{
				$numno++;
			}
		}
		if(!$numyes && $numno)
		{
			error($lang->error_nosplitposts);
		}
		if($numyess && !$numno)
		{
			error($lang->error_cantsplitall);
		}
		if($mybb->input['moveto'])
		{
			$moveto = $mybb->input['moveto'];
		} 
		else
		{
			$moveto = $fid;
		}
		$newsubject = addslashes($mybb->input['newsubject']);
		$query = array(
			"tid" => "NULL",
			"fid" => $moveto,
			"subject" => $newsubject,
			"icon" => $thread['icon'],
			"uid" => $thread['uid'],
			"username" => $thread['username'],
			"dateline" => $thread['dateline'],
			"lastpost" => $thread['lastpost'],
			"lastposter" => $thread['lastposter'],
			"replies" => $numyes,
			"visible" => "1",
		);
		$db->insert_query(TABLE_PREFIX."threads", $query);
		$newtid = $db->insert_id();
		
		// move the selected posts over
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid'");
		while($post = $db->fetch_array($query))
		{
			if($mybb->input['splitpost'][$post['pid']] == "yes")
			{
				$db->query("UPDATE ".TABLE_PREFIX."posts SET tid='$newtid', fid='$moveto' WHERE pid='$post[pid]'");
			}
			markreports($post['pid'], "post");
		}
		update_first_post($newtid);
		update_first_post($tid);
		logmod($modlogdata, $lang->thread_split);
		updatethreadcount($tid);
		updatethreadcount($newtid);
		if($moveto != $fid)
		{
			updateforumcount($moveto);
		}
		updateforumcount($fid);
		redirect("showthread.php?tid=$newtid", $lang->redirect_threadsplit);
		break;
	
	// Delete Threads - Inline moderation
	case "multideletethreads":
		addnav($lang->nav_multi_deletethreads);
		if(ismod($fid, "candeleteposts") != "yes")
		{
			if($permissions['candeletethreads'] != "yes" || $mybb->user['uid'] != $thread['uid'])
			{
				nopermission();
			}
		}
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}
		$inlineids = implode("|", $threads);
		clearinline($fid, "forum");
		eval("\$multidelete = \"".$templates->get("moderation_inline_deletethreads")."\";");
		outputpage($multidelete);
		break;

	// Actually delete the threads - Inline moderation
	case "do_multideletethreads":
		if(ismod($fid, "candeleteposts") != "yes")
		{
			if($permissions['candeletethreads'] != "yes" || $mybb->user['uid'] != $thread['uid'])
			{
				nopermission();
			}
		}
		$threadlist = explode("|", $mybb->input['threads']);
		foreach($threadlist as $tid)
		{
			deletethread($tid);
			$tlist[] = $tid;
		}
		updateforumcount($fid);
		logmod($modlogdata, $lang->multi_deleted_threads);
		clearinline($fid, "forum");
		markreports($tlist, "threads");
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsdeleted);
		break;

	// Open threads - Inline moderation
	case "multiopenthreads":
		if(ismod($fid, "canopenclosethreads") != "yes")
		{
			nopermission();
		}
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}
		$q = "tid='-1'";
		foreach($threads as $tid)
		{
			$q .= " OR tid='$tid'";
		}
		$db->query("UPDATE ".TABLE_PREFIX."threads SET closed='no' WHERE $q");
		logmod($modlogdata, $lang->multi_opened_threads);
		clearinline($fid, "forum");
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsopened);
		break;

	// Close threads - Inline moderation
	case "multiclosethreads":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}
		$q = "tid='-1'";
		foreach($threads as $tid)
		{
			$q .= " OR tid='$tid'";
		}
		$db->query("UPDATE ".TABLE_PREFIX."threads SET closed='yes' WHERE $q");
		logmod($modlogdata, $lang->multi_closed_threads);
		clearinline($fid, "forum");
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsclosed);
		break;

	// Approve threads - Inline moderation
	case "multiapprovethreads":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}
		$q = "tid='-1'";
		foreach($threads as $tid)
		{
			$q .= " OR tid='$tid'";
		}
		$db->query("UPDATE ".TABLE_PREFIX."threads SET visible='1' WHERE $q");
		$db->query("UPDATE ".TABLE_PREFIX."posts SET visible='1' WHERE $q AND replyto='0'");
		logmod($modlogdata, $lang->multi_approved_threads);
		clearinline($fid, "forum");
		$cache->updatestats();
		updateforumcount($fid);
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsapproved);
		break;

	// Unapprove threads - Inline moderation
	case "multiunapprovethreads":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}
		$q = "tid='-1'";
		foreach($threads as $tid)
		{
			$q .= " OR tid='$tid'";
		}
		$db->query("UPDATE ".TABLE_PREFIX."threads SET visible='0' WHERE $q");
		$db->query("UPDATE ".TABLE_PREFIX."posts SET visible='0' WHERE $q AND replyto='0'");
		logmod($modlogdata, $lang->multi_unapproved_threads);
		clearinline($fid, "forum");
		$cache->updatestats();
		updateforumcount($fid);
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsunapproved);
		break;
	
	// Stick threads - Inline moderation
	case "multistickthreads":
		if(ismod($fid, "canopenclosethreads") != "yes")
		{
			nopermission();
		}
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}
		$q = "tid='-1'";
		foreach($threads as $tid)
		{
			$q .= " OR tid='$tid'";
		}
		$db->query("UPDATE ".TABLE_PREFIX."threads SET sticky='1' WHERE $q");
		logmod($modlogdata, $lang->multi_stuck_threads);
		clearinline($fid, "forum");
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsstuck);
		break;

	// Unstick threads - Inline moderaton
	case "multiunstickthreads":
		if(ismod($fid, "canopenclosethreads") != "yes")
		{
			nopermission();
		}
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}
		$q = "tid='-1'";
		foreach($threads as $tid)
		{
			$q .= " OR tid='$tid'";
		}
		$db->query("UPDATE ".TABLE_PREFIX."threads SET sticky='0' WHERE $q");
		logmod($modlogdata, $lang->multi_unstuck_threads);
		clearinline($fid, "forum");
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsunstuck);
		break;

	// Move threads - Inline moderation
	case "multimovethreads":
		addnav($lang->nav_multi_movethreads);
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}
		$inlineids = implode("|", $threads);
		clearinline($fid, "forum");

		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$forumselect = makeforumjump("", "", 1, "", 0, "", "moveto");
		eval("\$movethread = \"".$templates->get("moderation_inline_movethreads")."\";");
		outputpage($movethread);
		break;

	// Actually move the threads in Inline moderation
	case "do_multimovethreads":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$q = "tid='-1'";
		$moveto = intval($mybb->input['moveto']);
		$threadlist = explode("|", $mybb->input['threads']);
		foreach($threadlist as $tid)
		{
			$q .= " OR tid='$tid'";
		}
		if(ismod($moveto, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$newperms = forum_permissions($moveto);
		if($newperms['canview'] == "no")
		{
			nopermission();
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$moveto'");
		$newforum = $db->fetch_array($query);
		if($newforum['type'] != "f")
		{
			error($lang->error_invalidforum);
		}
		if($thread['fid'] == $moveto)
		{
			error($lang->error_movetosameforum);
		}
		$db->query("UPDATE ".TABLE_PREFIX."threads SET fid='$moveto' WHERE $q");
		$db->query("UPDATE ".TABLE_PREFIX."posts SET fid='$moveto' WHERE $q");
		logmod($modlogdata, $lang->multi_moved_threads);
		$query = $db->query("SELECT COUNT(p.pid) AS posts, u.uid FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE $q GROUP BY u.uid ORDER BY posts DESC");
		while($posters = $db->fetch_array($query))
		{
			if($method == "copy" && $newforum['usepostcounts'] != "no")
			{
				$pcount = "+$posters[posts]";
			}
			if($method != "copy" && ($newforum['usepostcounts'] != "no" && $forum['usepostcounts'] == "no"))
			{
				$pcount = "+$posters[posts]";
			}
			if($method != "copy" && ($newforum['usepostcounts'] == "no" && $forum['usepostcounts'] != "no"))
			{
				$pcount = "-$posters[posts]";
			}
			$db->query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum$pcount WHERE uid='$posters[uid]'");
		}
		updateforumcount($moveto);
		updateforumcount($fid);
		redirect("forumdisplay.php?fid=$moveto", $lang->redirect_inline_threadsmoved);
		break;

	// Delete posts - Inline moderation
	case "multideleteposts":
		addnav($lang->nav_multi_deleteposts);
		if(ismod($fid, "candeleteposts") != "yes")
		{
			nopermission();
		}
		$posts = getids($tid, "thread");
		if(!is_array($posts))
		{
			error($lang->error_inline_nopostsselected);
		}
		$inlineids = implode("|", $posts);
		//clearinline($pid, "post");
		clearinline($tid, "thread");

		if(!is_array($posts))
		{
			error($lang->error_inline_nopostsselected);
		}
		eval("\$multidelete = \"".$templates->get("moderation_inline_deleteposts")."\";");
		outputpage($multidelete);
		break;

	// Actually delete the posts in inline moderation
	case "do_multideleteposts":
		if(ismod($fid, "candeleteposts") != "yes")
		{
			nopermission();
		}
		$postlist = explode("|", $mybb->input['posts']);
		$deletecount = 0;
		foreach($postlist as $pid)
		{
			deletepost($pid);
			$plist[] = $pid;
			$deletecount++;
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid'");
		$numposts = $db->num_rows($query);
		if(!$numposts)
		{
			deletethread($tid);
			markreports($tid, "thread");
			$url = "forumdisplay.php?fid=$fid";
		}
		else
		{
			updatethreadcount($tid);
			markreports($plist, "posts");
			$url = "showthread.php?tid=$tid";
		}
		$lang->deleted_selective_posts = sprintf($lang->deleted_selective_posts, $deletecount);
		logmod($modlogdata, $lang->deleted_selective_posts);
		updateforumcount($fid);
		redirect($url, $lang->redirect_postsdeleted);
		break;

	// Merge posts - Inline moderation
	case "multimergeposts":
		addnav($lang->nav_multi_mergeposts);
		if(ismod($fid, "candeleteposts") != "yes")
		{
			nopermission();
		}
		$posts = getids($tid, "thread");
		if(!is_array($posts))
		{
			error($lang->error_inline_nopostsselected);
		}
		$inlineids = implode("|", $posts);
		clearinline($tid, "thread");

		eval("\$multimerge = \"".$templates->get("moderation_inline_mergeposts")."\";");
		outputpage($multimerge);
		break;

	// Actually merge the posts - Inline moderation
	case "do_multimergeposts":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$postlist = explode("|", $mybb->input['posts']);
		foreach($postlist as $pid)
		{
				$pidin .= "$comma'$pid'";
				$comma = ",";
				$plist[] = $pid;
		}
		$first = 1;
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid' AND pid IN($pidin) ORDER BY dateline ASC");
		while($post = $db->fetch_array($query))
		{
			if($first == 1)
			{ // all posts will be merged into this one
				$masterpid = $post['pid'];
				$message = $post['message'];
			}
			else
			{ // these are the selected posts
				if($mybb->input['sep'] == "new_line")
				{
					$message .= "\n\n$post[message]";
				}
				else
				{
					$message .= "[hr]$post[message]";
				}

				deletepost($post['pid']);
			}
			$first = 0;
		}
		$message = addslashes($message);
		$db->query("UPDATE ".TABLE_PREFIX."posts SET message='$message' WHERE pid='$masterpid'");
		$db->query("UPDATE ".TABLE_PREFIX."attachments SET pid='$masterpid' WHERE pid IN($pidin)");
		updatethreadcount($tid);
		updateforumcount($fid);
		markreports($plist, "posts");
		logmod($modlogdata, $lang->merged_selective_posts);
		redirect("showthread.php?tid=$tid", $lang->redirect_inline_postsmerged);
		break;

	// Split posts - Inline moderation
	case "multisplitposts":
		addnav($lang->nav_multi_splitposts);
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$query = $db->query("SELECT p.*, u.* FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid) WHERE tid='$tid' ORDER BY dateline ASC");	
		$numposts = $db->num_rows($query);
		if($numposts <= "1")
		{
			error($lang->error_cantsplitonepost);
		}
		$posts = getids($tid, "thread");
		if(!is_array($posts))
		{
			error($lang->error_inline_nopostsselected);
		}
		foreach($posts as $pid)
		{
				$pidin .= "$comma'$pid'";
				$comma = ",";
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE pid NOT IN($pidin) AND tid='$tid'");
		$num = $db->num_rows($query);
		if(!$num)
		{
			error($lang->error_cantsplitall);
		}
		$inlineids = implode("|", $posts);
		clearinline($tid, "thread");
		$forumselect = makeforumjump("", "", 1, "", 0, "", "moveto");
		eval("\$splitposts = \"".$templates->get("moderation_inline_splitposts")."\";");
		outputpage($splitposts);
		break;

	// Actually split the posts - Inline moderation
	case "do_multisplitposts":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$postlist = explode("|", $mybb->input['posts']);
		foreach($postlist as $pid)
		{
			$pidin .= "$comma'$pid'";
			$comma = ",";
			$plist[] = $pid;
		}
		if($mybb->input['moveto'])
		{
			$moveto = intval($mybb->input['moveto']);
		}
		else
		{
			$moveto = $fid;
		}
		$newsubject = addslashes($mybb->input['newsubject']);
		$db->query("INSERT INTO ".TABLE_PREFIX."threads (tid,fid,subject,icon,uid,username,dateline,lastpost,lastposter,replies,visible) VALUES (NULL,'$moveto','$newsubject','$thread[icon]','$thread[uid]','$thread[username]','$thread[dateline]','$thread[lastpost]','$thread[lastposter]','$numyes','1')");
		$newtid = $db->insert_id();
		// move the selected posts over
		$db->query("UPDATE ".TABLE_PREFIX."posts SET tid='$newtid', fid='$moveto' WHERE pid IN($pidin)");
		// adjust user post counts accordingly
		$query = $db->query("SELECT usepostcounts FROM ".TABLE_PREFIX."forums WHERE fid='$fid'");
		$oldusepcounts = $db->result($query, 0);
		$query = $db->query("SELECT usepostcounts FROM ".TABLE_PREFIX."forums WHERE fid='$moveto'");
		$newusepcounts = $db->result($query, 0);
		$query = $db->query("SELECT COUNT(p.pid) AS posts, u.uid FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE tid='$tid' GROUP BY u.uid ORDER BY posts DESC");
		while($posters = $db->fetch_array($query))
		{
			if($oldusepcounts == "yes" && $newusepcounts == "no")
			{
				$pcount = "-$posters[posts]";
			}
			if($oldusepcounts == "no" && $newusepcounts == "yes")
			{
				$pcount = "+$posters[posts]";
			}
			$db->query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum$pcount WHERE uid='$posters[uid]'");
		}
		logmod($modlogdata, $lang->thread_split);
		markreports($plist, "posts");
		updatethreadcount($tid);
		updatethreadcount($newtid);
		if($moveto != $fid)
		{
			updateforumcount($moveto);
		}
		updateforumcount($fid);
		redirect("showthread.php?tid=$newtid", $lang->redirect_threadsplit);
		break;

	// Approve posts - Inline moderation
	case "multiapproveposts":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$posts = getids($tid, "thread");
		if(!is_array($posts))
		{
			error($lang->error_inline_nopostsselected);
		}
		$q = "pid='-1'";
		foreach($posts as $pid)
		{
			$q .= " OR pid='$pid'";
		}
		$db->query("UPDATE ".TABLE_PREFIX."posts SET visible='1' WHERE $q");
		// If this is the first post of the thread, also approve the thread
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE $q");
		while($post = $db->fetch_array($query))
		{
			if($post['replyto'] == 0)
			{
				$db->query("UPDATE ".TABLE_PREFIX."threads SET visible='1' WHERE tid='$post[tid]'");
				$cache->updatestats();
				updateforumcount($fid);
			}
		}
		logmod($modlogdata, $lang->multi_approve_posts);
		clearinline($tid, "thread");
		redirect("showthread.php?tid=$tid", $lang->redirect_inline_postsapproved);
		break;

	// Unapprove posts - Inline moderation
	case "multiunapproveposts":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$posts = getids($tid, "thread");
		if(!is_array($posts))
		{
			error($lang->error_inline_nopostsselected);
		}
		$q = "pid='-1'";
		foreach($posts as $pid)
		{
			$q .= " OR pid='$pid'";
		}
		$db->query("UPDATE ".TABLE_PREFIX."posts SET visible='0' WHERE $q");
		// If this is the first post of the thread, also unapprove the thread
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE $q");
		while($post = $db->fetch_array($query))
		{
			if($post['replyto'] == 0)
			{
				$db->query("UPDATE ".TABLE_PREFIX."threads SET visible='0' WHERE tid='$post[tid]'");
				$cache->updatestats();
				updateforumcount($fid);
			}
		}
		logmod($modlogdata, $lang->multi_unapprove_posts);
		clearinline($tid, "thread");
		redirect("showthread.php?tid=$tid", $lang->redirect_inline_postsunapproved);
		break;

	// Manage selected reported posts
	case "do_reports":
		if(ismod() != "yes")
		{
			nopermission();
		}
		$flist = "";
		if($mybb->usergroup['issupermod'] != "yes")
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."moderators WHERE uid='".$mybb->user[uid]."'");
			while($forum = $db->fetch_array($query))
			{
				$flist .= ",'".$forum['fid']."'";
			}
		}
		if($flist)
		{
			$flist = "AND fid IN (0$flist)";
		}
		if(!is_array($mybb->input['reports']))
		{
			error($lang->error_noselected_reports);
		}
		$rids = implode($mybb->input['reports'], "','");
		$rids = "'0','$rids'";

		$plugins->run_hooks("moderation_do_reports");
		
		$db->query("UPDATE ".TABLE_PREFIX."reportedposts SET reportstatus='1' WHERE rid IN ($rids)");
		$cache->updatereportedposts();
		redirect("moderation.php?action=reports", $lang->redirect_reportsmarked);
		break;

	// Show a listing of the reported posts
	case "reports":
		if(ismod() != "yes")
		{
			nopermission();
		}
		$flist = "";
		if($mybb->usergroup['issupermod'] != "yes")
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."moderators WHERE uid='".$mybb->user[uid]."'");
			while($forum = $db->fetch_array($query))
			{
				$flist .= ",'".$forum['fid']."'";
			}
		}
		if($flist)
		{
			$flist2 = "WHERE fid IN (0$flist)";
			$flistr = "AND r.fid IN (0$flist)";
		}
		$query = $db->query("SELECT fid,name FROM ".TABLE_PREFIX."forums $flist2");
		while($forum = $db->fetch_array($query))
		{
			$forums[$forum['fid']] = $forum['name'];
		}
		$trow = "trow1";
		$query = $db->query("SELECT r.*, u.username, up.username AS postusername, up.uid AS postuid, t.subject AS threadsubject FROM ".TABLE_PREFIX."reportedposts r LEFT JOIN ".TABLE_PREFIX."posts p ON (r.pid=p.pid) LEFT JOIN ".TABLE_PREFIX."threads t ON (p.tid=t.tid) LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid=u.uid) LEFT JOIN ".TABLE_PREFIX."users up ON (p.uid=up.uid) WHERE r.reportstatus ='0' $flistr ORDER BY r.dateline ASC");
		while($report = $db->fetch_array($query))
		{
			$reportdate = mydate($mybb->settings['dateformat'], $report['dateline']);
			$reporttime = mydate($mybb->settings['timeformat'], $report['dateline']);
			eval("\$reports .= \"".$templates->get("moderation_reports_report")."\";");
			if($trow == "trow2")
			{
				$trow = "trow1";
			}
			else
			{
				$trow = "trow1";
			}
		}
		if(!$reports)
		{
			eval("\$reports = \"".$templates->get("moderation_reports_noreports")."\";");
		}

		$plugins->run_hooks("moderation_reports");

		eval("\$reportedposts = \"".$templates->get("moderation_reports")."\";");
		outputpage($reportedposts);
		break;
	default:
		break;
}

// Some little handy functions for our inline moderation
function getids($id, $type)
{
	global $_COOKIE;
	$cookie = "inlinemod_".$type.$id;
	$ids = explode("|", $_COOKIE[$cookie]);
	foreach($ids as $id)
	{
		if($id != "")
		{
			$newids[] = $id;
		}
	}
	return $newids;
}


function clearinline($id, $type)
{
	myunsetcookie("inlinemod_".$type.$id);
}

function extendinline($id, $type)
{
	global $_COOKIE;
	setcookie("inlinemod_$type$id", "", time()+3600);
}
