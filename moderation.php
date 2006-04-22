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

require "./global.php";
require "./inc/functions_post.php";
require "./inc/functions_upload.php";
require "./inc/class_parser.php";
$parser = new postParser;
require "./inc/class_moderation.php";
$moderation = new Moderation;

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
	addnav($parser->parse_badwords($thread['subject']), "showthread.php?tid=$thread[tid]");
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

		$plugins->run_hooks("moderation_openclosethread");

		if($thread['closed'] == "yes")
		{
			$openclose = $lang->opened;
			$redirect = $lang->redirect_openthread;
			$moderation->open_threads($tid);
		}
		else
		{
			$openclose = $lang->closed;
			$redirect = $lang->redirect_closethread;
			$moderation->close_threads($tid);
		}

		$lang->mod_process = sprintf($lang->mod_process, $openclose);

		logmod($modlogdata, $lang->mod_process);

		redirect("showthread.php?tid=$tid", $redirect);
		break;

	// Stick or unstick that post to the top bab!
	case "stick";
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}

		$plugins->run_hooks("moderation_stick");

		if($thread['sticky'] == 1)
		{
			$stuckunstuck = $lang->unstuck;
			$redirect = $lang->redirect_unstickthread;
			$moderation->unstick_threads($tid);
		}
		else
		{
			$stuckunstuck = $lang->stuck;
			$redirect = $lang->redirect_stickthread;
			$moderation->stick_threads($tid);
		}

		$lang->mod_process = sprintf($lang->mod_process, $stuckunstuck);

		logmod($modlogdata, $lang->mod_process);

		redirect("showthread.php?tid=$tid", $redirect);
		break;

	// Remove redirects to a specific thread
	case "removeredirects":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}

		$plugins->run_hooks("moderation_removeredirects");

		$moderation->remove_redirects($tid);

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

		$thread['subject'] = $db->escape_string($thread['subject']);
		$lang->thread_deleted = sprintf($lang->thread_deleted, $thread['subject']);
		logmod($modlogdata, $lang->thread_deleted);

		$moderation->delete_thread($tid);

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
		if(!$mybb->input['delete'])
		{
			redirect("showthread.php?tid=$tid", $lang->redirect_pollnotdeleted);
		}
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

		$moderation->delete_poll($poll['pid']);

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

		$moderation->approve_thread($tid, $fid);

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

		$moderation->unapprove_threads($tid, $fid);

		redirect("showthread.php?tid=$tid", $lang->redirect_threadunapproved);
		break;

	// Delete selective posts in a thread
	case "deleteposts":
		addnav($lang->nav_deleteposts);
		if(ismod($fid, "candeleteposts") != "yes")
		{
			nopermission();
		}
		$posts = "";
		$query = $db->query("SELECT p.*, u.* FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid) WHERE tid='$tid' ORDER BY dateline ASC");
		$altbg = "trow1";
		while($post = $db->fetch_array($query))
		{
			$postdate = mydate($mybb->settings['dateformat'], $post['dateline']);
			$posttime = mydate($mybb->settings['timeformat'], $post['dateline']);

			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode']
			);
			if($post['smilieoff'] == "yes")
			{
				$parser_options['allow_smilies'] = "no";
			}

			$message = $parser->parse_message($message, $parser_options);
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
				$moderation->delete_post($post['pid']);
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
			$moderation->delete_thread($tid);
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
		$posts = "";
		$query = $db->query("SELECT p.*, u.* FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid) WHERE tid='$tid' ORDER BY dateline ASC");
		$altbg = "trow1";
		while($post = $db->fetch_array($query))
		{
			$postdate = mydate($mybb->settings['dateformat'], $post['dateline']);
			$posttime = mydate($mybb->settings['timeformat'], $post['dateline']);
			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode']
			);
			if($post['smilieoff'] == "yes")
			{
				$parser_options['allow_smilies'] = "no";
			}

			$message = $parser->parse_message($post['message'], $parser_options);
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

		$moderation->merge_posts($mergepost, $tid, $mybb->input['sep']);

		markreports($plist, "posts");
		logmod($modlogdata, $lang->merged_selective_posts);
		redirect("showthread.php?tid=$tid", $lang->redirect_mergeposts);
		break;

	// Move a thread
	case "move":
		addnav($lang->nav_move);
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}

		$plugins->run_hooks("moderation_move");

		$forumselect = makeforumjump("", '', 1, '', 0, '', "moveto");
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
		// Check if user has moderator permission to move to destination
		if(ismod($moveto, "canmanagethreads") != "yes" && ismod($fid, "canmovetononmodforum") != "yes")
		{
			nopermission();
		}
		$newperms = forum_permissions($moveto);
		if($newperms['canview'] == "no" && ismod($fid, "canmovetononmodforum") != "yes")
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

		$the_thread = $tid;

		$newtid = $moderation->move_thread($tid, $moveto, $method);

		switch($method)
		{
			case "copy":
				logmod($modlogdata, $lang->thread_copied);
				break;
			default:
			case "move":
			case "redirect":
				logmod($modlogdata, $lang->thread_moved);
				break;
		}

		redirect("showthread.php?tid=$newtid", $lang->redirect_threadmoved);
		break;

	// Thread notes editor
	case "threadnotes":
		addnav($lang->nav_threadnotes);
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$thread['notes'] = htmlspecialchars_uni($parser->parse_badwords($thread['notes']));
		$trow = "trow1";
		$query = $db->query("
			SELECT l.*, u.username, t.subject AS tsubject, f.name AS fname, p.subject AS psubject
			FROM ".TABLE_PREFIX."moderatorlog l
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=l.fid)
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=l.pid)
			WHERE t.tid='$tid'
			ORDER BY l.dateline DESC
			LIMIT  0, 20
		");
		while($modaction = $db->fetch_array($query))
		{
			$modaction['dateline'] = mydate("jS M Y, G:i", $modaction['dateline']);
			$info = '';
			if($modaction['tsubject'])
			{
				$info .= "<strong>$lang->thread</strong> <a href=\"showthread.php?tid=".$modaction['tid']."\">".$modaction['tsubject']."</a><br />";
			}
			if($modaction['fname'])
			{
				$info .= "<strong>$lang->forum</strong> <a href=\"forumdisplay.php?fid=".$modaction['fid']."\">".$modaction['fname']."</a><br />";
			}
			if($modaction['psubject'])
			{
				$info .= "<strong>$lang->post</strong> <a href=\"showthread.php?tid=".$modaction['tid']."&pid=".$modaction['pid']."#pid".$modaction['pid']."\">".$modaction['psubject']."</a>";
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
		$sqlarray = array(
			"notes" => $db->escape_string($mybb->input['threadnotes']),
			);
		$db->update_query(TABLE_PREFIX."threads", $sqlarray, "tid='$tid'");
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

		// Admin options
		$adminoptions = "";
		if($mybb->usergroup['cancp'] == "yes")
		{
			eval("\$adminoptions = \"".$templates->get("moderation_getip_adminoptions")."\";");
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
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE pid='".intval($parameters['pid'])."'");
			$post = $db->fetch_array($query);
			$mergetid = $post['tid'];
		}
		elseif($parameters['tid'])
		{
			$mergetid = $parameters['tid'];
		}
		$mergetid = intval($mergetid);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='".intval($mergetid)."'");
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
		if($mybb->input['subject'])
		{
			$subject = $mybb->input['subject'];
		}
		else
		{
			$subject = $thread['subject'];
		}

		$moderation->merge_threads($mergetid, $tid, $subject);

		logmod($modlogdata, $lang->thread_merged);

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
		$posts = '';
		while($post = $db->fetch_array($query))
		{
			$postdate = mydate($mybb->settings['dateformat'], $post['dateline']);
			$posttime = mydate($mybb->settings['timeformat'], $post['dateline']);
			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode']
			);
			if($post['smilieoff'] == "yes")
			{
				$parser_options['allow_smilies'] = "no";
			}

			$message = $parser->parse_message($message, $parser_options);
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
		$forumselect = makeforumjump("", $fid, 1, '', 0, '', "moveto");

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
		if(!is_array($mybb->input['splitpost']))
		{
			error($lang->error_nosplitposts);
		}
		$query = $db->query("SELECT COUNT(*) AS totalposts FROM ".TABLE_PREFIX."posts WHERE tid='".intval($mybb->input['tid'])."'");
		$count = $db->fetch_array($query);

		if(!is_array($mybb->input['splitpost']))
		{
			error($lang->error_nosplitposts);
		}
		elseif($count['totalposts'] == count($mybb->input['splitpost']))
		{
			error($lang->error_cantsplitall);
		}
		if($mybb->input['moveto'])
		{
			$moveto = intval($mybb->input['moveto']);
		}
		else
		{
			$moveto = $fid;
		}
		$query = $db->query("SELECT fid FROM ".TABLE_PREFIX."forums WHERE fid='$moveto' LIMIT 1");
		if($db->num_rows($query) == 0)
		{
			error($lang->error_invalidforum);
		}

		// move the selected posts over
		$query = $db->query("SELECT pid FROM ".TABLE_PREFIX."posts WHERE tid='$tid'");
		while($post = $db->fetch_array($query))
		{
			if($mybb->input['splitpost'][$post['pid']] == "yes")
			{
				$pids[] = $post['pid'];
			}
			markreports($post['pid'], "post");
		}

		$newtid = $moderation->split_posts($pids, $tid, $moveto, $mybb->input['newsubject']);

		logmod($modlogdata, $lang->thread_split);

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
			$tid = intval($tid);
			$moderation->delete_thread($tid);
			$tlist[] = $tid;
		}
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

		$moderation->open_threads($threads);

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

		$moderation->open_threads($threads);

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

		$moderation->approve_threads($threads, $fid);

		logmod($modlogdata, $lang->multi_approved_threads);
		clearinline($fid, "forum");
		$cache->updatestats();
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

		$moderation->unapprove_threads($threads, $fid);

		logmod($modlogdata, $lang->multi_unapproved_threads);
		clearinline($fid, "forum");
		$cache->updatestats();
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

		$moderation->stick_threads($threads);

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

		$moderation->unstick_threads($threads);

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
		$forumselect = makeforumjump("", '', 1, '', 0, '', "moveto");
		eval("\$movethread = \"".$templates->get("moderation_inline_movethreads")."\";");
		outputpage($movethread);
		break;

	// Actually move the threads in Inline moderation
	case "do_multimovethreads":
		if(ismod($fid, "canmanagethreads") != "yes")
		{
			nopermission();
		}
		$moveto = intval($mybb->input['moveto']);
		$threadlist = explode("|", $mybb->input['threads']);
		foreach($threadlist as $tid)
		{
			$tids[] = $tid;
		}
		if(ismod($moveto, "canmanagethreads") != "yes" && ismod($fid, "canmovetononmodforum") != "yes")
		{
			nopermission();
		}
		$newperms = forum_permissions($moveto);
		if($newperms['canview'] == "no" && ismod($fid, "canmovetononmodforum") != "yes")
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

		$moderation->move_threads($tids, $moveto);

		logmod($modlogdata, $lang->multi_moved_threads);

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
			$pid = intval($pid);
			$moderation->delete_post($pid);
			$plist[] = $pid;
			$deletecount++;
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid'");
		$numposts = $db->num_rows($query);
		if(!$numposts)
		{
			$moderation->delete_thread($tid);
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
			$pid = intval($pid);
			$plist[] = $pid;
		}

		$moderation->merge_posts($plist, $tid, $mybb->input['sep']);

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
		$pidin = '';
		$comma = '';
		foreach($posts as $pid)
		{
			$pid = intval($pid);
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
		$forumselect = makeforumjump("", $fid, 1, '', 0, '', "moveto");
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
			$pid = intval($pid);
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
		$query = $db->query("SELECT fid FROM ".TABLE_PREFIX."forums WHERE fid='$moveto' LIMIT 1");
		if($db->num_rows($query) == 0)
		{
			error($lang->error_invalidforum);
		}
		$newsubject = $mybb->input['newsubject'];

		$newtid = $moderation->split_posts($plist, $tid, $moveto, $newsubject);

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

		$pids = array();
		foreach($posts as $pid)
		{
			$pids[] = intval($pid);
		}

		$moderation->approve_posts($pids, $tid, $fid);

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
		$pids = array();
		foreach($posts as $pid)
		{
			$pids[] = intval($pid);
		}

		$moderation->unapprove_posts($pids, $tid, $fid);

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
		$flist = '';
		if($mybb->usergroup['issupermod'] != "yes")
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."moderators WHERE uid='".$mybb->user['uid']."'");
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
		foreach($mybb->input['reports'] as $rid)
		{
			$reports[] = intval($rid);
		}
		$rids = implode($reports, "','");
		$rids = "'0','$rids'";

		$plugins->run_hooks("moderation_do_reports");

		$sqlarray = array(
			"reportstatus" => 1,
			);
		$db->update_query(TABLE_PREFIX."reportedposts", $sqlarray, "rid IN ($rids)");
		$cache->updatereportedposts();
		redirect("moderation.php?action=reports", $lang->redirect_reportsmarked);
		break;

	// Show a listing of the reported posts
	case "reports":
		if(ismod() != "yes")
		{
			nopermission();
		}

		$query = $db->query("SELECT fid,name FROM ".TABLE_PREFIX."forums");
		while($forum = $db->fetch_array($query))
		{
			$forums[$forum['fid']] = $forum['name'];
		}
		$trow = "trow1";
		$reports = '';
		$query = $db->query("SELECT r.*, u.username, up.username AS postusername, up.uid AS postuid, t.subject AS threadsubject FROM ".TABLE_PREFIX."reportedposts r LEFT JOIN ".TABLE_PREFIX."posts p ON (r.pid=p.pid) LEFT JOIN ".TABLE_PREFIX."threads t ON (p.tid=t.tid) LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid=u.uid) LEFT JOIN ".TABLE_PREFIX."users up ON (p.uid=up.uid) WHERE r.reportstatus ='0' ORDER BY r.dateline ASC");
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
		nopermission();
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
		if($id != '')
		{
			$newids[] = intval($id);
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
	setcookie("inlinemod_$type$id", '', time()+3600);
}

?>