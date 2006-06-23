<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

require "./global.php";
require MYBB_ROOT."inc/functions_post.php";
require MYBB_ROOT."inc/functions_upload.php";
require MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;
require MYBB_ROOT."inc/class_moderation.php";
$moderation = new Moderation;

// Load global language phrases
$lang->load("moderation");

$plugins->run_hooks("moderation_start");

// Get some navigation if we need it
switch($mybb->input['action'])
{
	case "reports":
		add_breadcrumb($lang->reported_posts);
		break;
	case "allreports":
		add_breadcrumb($lang->all_reported_posts);
		break;
		
}
$tid = intval($mybb->input['tid']);
$pid = intval($mybb->input['pid']);
$fid = intval($mybb->input['fid']);

if($pid)
{
	$post = get_post($pid);
	$tid = $post['tid'];
	if(!$post['pid'])
	{
		error($lang->error_invalidpost);
	}
}

if($tid)
{
	$thread = get_thread($tid);
	$fid = $thread['fid'];
	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
}

if($fid)
{
	$modlogdata['fid'] = $fid;
	$forum = get_forum($fid);

	// Make navigation
	build_forum_breadcrumb($fid);
}

if($tid)
{
	add_breadcrumb($parser->parse_badwords($thread['subject']), "showthread.php?tid=$thread[tid]");
	$modlogdata['tid'] = $tid;
}

// Get our permissions all nice and setup
$permissions = forum_permissions($fid);

if($fid)
{
	// Password protected forums ......... yhummmmy!
	check_forum_password($fid, $forum['password']);
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
		if(is_moderator($fid, "canopenclosethreads") != "yes")
		{
			error_no_permission();
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

		log_moderator_action($modlogdata, $lang->mod_process);

		redirect("showthread.php?tid=$tid", $redirect);
		break;

	// Stick or unstick that post to the top bab!
	case "stick";
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
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

		log_moderator_action($modlogdata, $lang->mod_process);

		redirect("showthread.php?tid=$tid", $redirect);
		break;

	// Remove redirects to a specific thread
	case "removeredirects":
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_removeredirects");

		$moderation->remove_redirects($tid);

		log_moderator_action($modlogdata, $lang->redirects_removed);
		redirect("showthread.php?tid=$tid", $lang->redirect_redirectsremoved);
		break;

	// Delete thread confirmation page
	case "deletethread":
		add_breadcrumb($lang->nav_deletethread);

		if(is_moderator($fid, "candeleteposts") != "yes")
		{
			if($permissions['candeletethreads'] != "yes" || $mybb->user['uid'] != $thread['uid'])
			{
				error_no_permission();
			}
		}

		$plugins->run_hooks("moderation_deletethread");

		eval("\$deletethread = \"".$templates->get("moderation_deletethread")."\";");
		output_page($deletethread);
		break;

	// Delete the actual thread here
	case "do_deletethread":
		if(is_moderator($fid, "candeleteposts") != "yes")
		{
			if($permissions['candeletethreads'] != "yes" || $mybb->user['uid'] != $thread['uid'])
			{
				error_no_permission();
			}
		}

		$plugins->run_hooks("moderation_do_deletethread");
		
		// Log the subject of the deleted thread
		$modlogdata['thread_subject'] = $thread['subject'];

		$thread['subject'] = $db->escape_string($thread['subject']);
		$lang->thread_deleted = sprintf($lang->thread_deleted, $thread['subject']);
		log_moderator_action($modlogdata, $lang->thread_deleted);

		$moderation->delete_thread($tid);

		mark_reports($tid, "thread");
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_threaddeleted);
		break;

	// Delete the poll from a thread confirmation page
	case "deletepoll":
		add_breadcrumb($lang->nav_deletepoll);

		if(is_moderator($fid, "candeleteposts") != "yes")
		{
			if($permissions['candeletethreads'] != "yes" || $mybb->user['uid'] != $thread['uid'])
			{
				error_no_permission();
			}
		}

		$plugins->run_hooks("moderation_deletepoll");

		$query = $db->simple_select(TABLE_PREFIX."polls", "*", "tid='$tid'");
		$poll = $db->fetch_array($query);
		if(!$poll['pid'])
		{
			error($lang->error_invalidpoll);
		}

		eval("\$deletepoll = \"".$templates->get("moderation_deletepoll")."\";");
		output_page($deletepoll);
		break;

	// Delete the actual poll here!
	case "do_deletepoll":
		if(!$mybb->input['delete'])
		{
			redirect("showthread.php?tid=$tid", $lang->redirect_pollnotdeleted);
		}
		if(is_moderator($fid, "candeleteposts") != "yes")
		{
			if($permissions['candeletethreads'] != "yes" || $mybb->user['uid'] != $thread['uid'])
			{
				error_no_permission();
			}
		}
		$query = $db->simple_select(TABLE_PREFIX."polls", "*", "tid='$tid'");
		$poll = $db->fetch_array($query);
		if(!$poll['pid'])
		{
			error($lang->error_invalidpoll);
		}

		$plugins->run_hooks("moderation_do_deletepoll");

		$lang->poll_deleted = sprintf($lang->poll_deleted, $thread['subject']);
		log_moderator_action($modlogdata, $lang->poll_deleted);

		$moderation->delete_poll($poll['pid']);

		redirect("showthread.php?tid=$tid", $lang->redirect_polldeleted);
		break;

	// Approve a thread
	case "approvethread":
		if(is_moderator($fid, "canopenclosethreads") != "yes")
		{
			error_no_permission();
		}
		$query = $db->simple_select(TABLE_PREFIX."threads", "*", "tid='$tid'");
		$thread = $db->fetch_array($query);

		$plugins->run_hooks("moderation_approvethread");

		$lang->thread_approved = sprintf($lang->thread_approved, $thread['subject']);
		log_moderator_action($modlogdata, $lang->thread_approved);

		$moderation->approve_threads($tid, $fid);

		redirect("showthread.php?tid=$tid", $lang->redirect_threadapproved);
		break;

	// Unapprove a thread
	case "unapprovethread":
		if(is_moderator($fid, "canopenclosethreads") != "yes")
		{
			error_no_permission();
		}
		$query = $db->simple_select(TABLE_PREFIX."threads", "*", "tid='$tid'");
		$thread = $db->fetch_array($query);

		$plugins->run_hooks("moderation_unapprovethread");

		$lang->thread_unapproved = sprintf($lang->thread_unapproved, $thread['subject']);
		log_moderator_action($modlogdata, $lang->thread_unapproved);

		$moderation->unapprove_threads($tid, $fid);

		redirect("showthread.php?tid=$tid", $lang->redirect_threadunapproved);
		break;

	// Delete selective posts in a thread
	case "deleteposts":
		add_breadcrumb($lang->nav_deleteposts);
		if(is_moderator($fid, "candeleteposts") != "yes")
		{
			error_no_permission();
		}
		$posts = "";
		$query = $db->query("
			SELECT p.*, u.*
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid)
			WHERE tid='$tid'
			ORDER BY dateline ASC
		");
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
			$altbg = alt_trow();
		}

		$plugins->run_hooks("moderation_deleteposts");

		eval("\$deleteposts = \"".$templates->get("moderation_deleteposts")."\";");
		output_page($deleteposts);
		break;

	// Lets delete those selected posts!
	case "do_deleteposts":
		if(is_moderator($fid, "candeleteposts") != "yes")
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_do_deleteposts");

		$deletethread = "1";
		$deletepost = $mybb->input['deletepost'];
		$query = $db->simple_select(TABLE_PREFIX."posts", "*", "tid='$tid'");
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
			mark_reports($plist, "posts");
		}
		else
		{
			update_thread_count($tid);
			$url = "showthread.php?tid=$tid";
			mark_reports($tid, "thread");
		}
		$lang->deleted_selective_posts = sprintf($lang->deleted_selective_posts, $deletecount);
		log_moderator_action($modlogdata, $lang->deleted_selective_posts);
		update_forum_count($fid);
		redirect($url, $lang->redirect_postsdeleted);
		break;

	// Merge selected posts selection screen
	case "mergeposts":
		add_breadcrumb($lang->nav_mergeposts);

		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}
		$posts = "";
		$query = $db->query("
			SELECT p.*, u.*
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid)
			WHERE tid='$tid' 
			ORDER BY dateline ASC
		");
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
			$altbg = alt_trow();
		}

		$plugins->run_hooks("moderation_mergeposts");

		eval("\$mergeposts = \"".$templates->get("moderation_mergeposts")."\";");
		output_page($mergeposts);
		break;

	// Lets merge those selected posts!
	case "do_mergeposts":
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_do_mergeposts");

		$mergepost = $mybb->input['mergepost'];
		if(count($mergepost) <= 1)
		{
			error($lang->error_nomergeposts);
		}

		$moderation->merge_posts($mergepost, $tid, $mybb->input['sep']);

		mark_reports($plist, "posts");
		log_moderator_action($modlogdata, $lang->merged_selective_posts);
		redirect("showthread.php?tid=$tid", $lang->redirect_mergeposts);
		break;

	// Move a thread
	case "move":
		add_breadcrumb($lang->nav_move);
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_move");

		$forumselect = build_forum_jump("", '', 1, '', 0, '', "moveto");
		eval("\$movethread = \"".$templates->get("moderation_move")."\";");
		output_page($movethread);
		break;

	// Lets get this thing moving!
	case "do_move":
		$moveto = intval($mybb->input['moveto']);
		$method = $mybb->input['method'];

		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}
		// Check if user has moderator permission to move to destination
		if(is_moderator($moveto, "canmanagethreads") != "yes" && is_moderator($fid, "canmovetononmodforum") != "yes")
		{
			error_no_permission();
		}
		$newperms = forum_permissions($moveto);
		if($newperms['canview'] == "no" && is_moderator($fid, "canmovetononmodforum") != "yes")
		{
			error_no_permission();
		}

		$query = $db->simple_select(TABLE_PREFIX."forums", "*", "fid='$moveto'");
		$newforum = $db->fetch_array($query);
		if($newforum['type'] != "f")
		{
			error($lang->error_invalidforum);
		}
		if($method != "copy" && $thread['fid'] == $moveto)
		{
			error($lang->error_movetosameforum);
		}

		$expire = 0;
		if(intval($mybb->input['redirect_expire']) > 0)
		{
			$expire = time() + (intval($mybb->input['redirect_expire']) * 86400);
		}

		$the_thread = $tid;

		$newtid = $moderation->move_thread($tid, $moveto, $method, $expire);

		switch($method)
		{
			case "copy":
				log_moderator_action($modlogdata, $lang->thread_copied);
				break;
			default:
			case "move":
			case "redirect":
				log_moderator_action($modlogdata, $lang->thread_moved);
				break;
		}

		redirect("showthread.php?tid=$newtid", $lang->redirect_threadmoved);
		break;

	// Thread notes editor
	case "threadnotes":
		add_breadcrumb($lang->nav_threadnotes);
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
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
			$trow = alt_trow();
		}
		if(!$modactions)
		{
			$modactions = "<tr><td class=\"trow1\" colspan=\"4\">$lang->no_mod_options</td></tr>";
		}

		$plugins->run_hooks("moderation_threadnotes");

		eval("\$threadnotes = \"".$templates->get("moderation_threadnotes")."\";");
		output_page($threadnotes);
		break;

	// Update the thread notes!
	case "do_threadnotes":
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_do_threadnotes");

		log_moderator_action($modlogdata, $lang->thread_notes_edited);
		$sqlarray = array(
			"notes" => $db->escape_string($mybb->input['threadnotes']),
			);
		$db->update_query(TABLE_PREFIX."threads", $sqlarray, "tid='$tid'");
		redirect("showthread.php?tid=$tid", $lang->redirect_threadnotesupdated);
		break;

	// Lets look up the ip address of a post
	case "getip":
		add_breadcrumb($lang->nav_getip);
		if(is_moderator($fid, "canviewips") != "yes")
		{
			error_no_permission();
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
		output_page($getip);
		break;

	// Merge threads
	case "merge":
		add_breadcrumb($lang->nav_merge);
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_merge");

		eval("\$merge = \"".$templates->get("moderation_merge")."\";");
		output_page($merge);
		break;

	// Lets get those threads together baby! (Merge threads)
	case "do_merge":
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_do_merge");

		// get thread to merge's tid
		$splitloc = explode(".php", $mybb->input['threadurl']);
		$temp = explode("&", my_substr($splitloc[1], 1));
		for($i = 0; $i < count($temp); $i++)
		{
			$temp2 = explode("=", $temp[$i], 2);
			$parameters[$temp2[0]] = $temp2[1];
		}
		if($parameters['pid'] && !$parameters['tid'])
		{
			$query = $db->simple_select(TABLE_PREFIX."posts", "*", "pid='".intval($parameters['pid'])."'");
			$post = $db->fetch_array($query);
			$mergetid = $post['tid'];
		}
		elseif($parameters['tid'])
		{
			$mergetid = $parameters['tid'];
		}
		$mergetid = intval($mergetid);
		$query = $db->simple_select(TABLE_PREFIX."threads", "*", "tid='".intval($mergetid)."'");
		$mergethread = $db->fetch_array($query);
		if(!$mergethread['tid'])
		{
			error($lang->error_badmergeurl);
		}
		if($mergetid == $tid)
		{ // sanity check
			error($lang->error_mergewithself);
		}
		if(is_moderator($mergethread['fid'], "canmanagethreads") != "yes")
		{
			error_no_permission();
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

		log_moderator_action($modlogdata, $lang->thread_merged);

		redirect("showthread.php?tid=$tid", $lang->redirect_threadsmerged);
		break;

	// Divorce the posts in this thread (Split!)
	case "split":
		add_breadcrumb($lang->nav_split);
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}
		$query = $db->query("
			SELECT p.*, u.*
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid)
			WHERE tid='$tid'
			ORDER BY dateline ASC
		");
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

			$message = $parser->parse_message($post['message'], $parser_options);
			eval("\$posts .= \"".$templates->get("moderation_split_post")."\";");
			$altbg = alt_trow();
		}
		$forumselect = build_forum_jump("", $fid, 1, '', 0, '', "moveto");

		$plugins->run_hooks("moderation_split");

		eval("\$split = \"".$templates->get("moderation_split")."\";");
		output_page($split);
		break;

	// Lets break them up buddy! (Do the split)
	case "do_split":
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_do_split");

		if(!is_array($mybb->input['splitpost']))
		{
			error($lang->error_nosplitposts);
		}
		$query = $db->simple_select(TABLE_PREFIX."posts", "COUNT(*) AS totalposts", "tid='".intval($mybb->input['tid'])."'");
		$count = $db->fetch_array($query);

		if($count['totalposts'] == count($mybb->input['splitpost']))
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
		$query = $db->simple_select(TABLE_PREFIX."forums", "fid", "fid='$moveto'", array('limit' => 1));
		if($db->num_rows($query) == 0)
		{
			error($lang->error_invalidforum);
		}

		// move the selected posts over
		$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid='$tid'");
		while($post = $db->fetch_array($query))
		{
			if($mybb->input['splitpost'][$post['pid']] == "yes")
			{
				$pids[] = $post['pid'];
			}
			mark_reports($post['pid'], "post");
		}

		$newtid = $moderation->split_posts($pids, $tid, $moveto, $mybb->input['newsubject']);

		log_moderator_action($modlogdata, $lang->thread_split);

		redirect("showthread.php?tid=$newtid", $lang->redirect_threadsplit);
		break;

	// Delete Threads - Inline moderation
	case "multideletethreads":
		add_breadcrumb($lang->nav_multi_deletethreads);
		if(is_moderator($fid, "candeleteposts") != "yes")
		{
			if($permissions['candeletethreads'] != "yes" || $mybb->user['uid'] != $thread['uid'])
			{
				error_no_permission();
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
		output_page($multidelete);
		break;

	// Actually delete the threads - Inline moderation
	case "do_multideletethreads":
		if(is_moderator($fid, "candeleteposts") != "yes")
		{
			if($permissions['candeletethreads'] != "yes" || $mybb->user['uid'] != $thread['uid'])
			{
				error_no_permission();
			}
		}
		$threadlist = explode("|", $mybb->input['threads']);
		foreach($threadlist as $tid)
		{
			$tid = intval($tid);
			$moderation->delete_thread($tid);
			$tlist[] = $tid;
		}
		log_moderator_action($modlogdata, $lang->multi_deleted_threads);
		clearinline($fid, "forum");
		mark_reports($tlist, "threads");
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsdeleted);
		break;

	// Open threads - Inline moderation
	case "multiopenthreads":
		if(is_moderator($fid, "canopenclosethreads") != "yes")
		{
			error_no_permission();
		}
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}

		$moderation->open_threads($threads);

		log_moderator_action($modlogdata, $lang->multi_opened_threads);
		clearinline($fid, "forum");
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsopened);
		break;

	// Close threads - Inline moderation
	case "multiclosethreads":
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}

		$moderation->close_threads($threads);

		log_moderator_action($modlogdata, $lang->multi_closed_threads);
		clearinline($fid, "forum");
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsclosed);
		break;

	// Approve threads - Inline moderation
	case "multiapprovethreads":
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}

		$moderation->approve_threads($threads, $fid);

		log_moderator_action($modlogdata, $lang->multi_approved_threads);
		clearinline($fid, "forum");
		$cache->updatestats();
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsapproved);
		break;

	// Unapprove threads - Inline moderation
	case "multiunapprovethreads":
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}

		$moderation->unapprove_threads($threads, $fid);

		log_moderator_action($modlogdata, $lang->multi_unapproved_threads);
		clearinline($fid, "forum");
		$cache->updatestats();
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsunapproved);
		break;

	// Stick threads - Inline moderation
	case "multistickthreads":
		if(is_moderator($fid, "canopenclosethreads") != "yes")
		{
			error_no_permission();
		}
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}

		$moderation->stick_threads($threads);

		log_moderator_action($modlogdata, $lang->multi_stuck_threads);
		clearinline($fid, "forum");
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsstuck);
		break;

	// Unstick threads - Inline moderaton
	case "multiunstickthreads":
		if(is_moderator($fid, "canopenclosethreads") != "yes")
		{
			error_no_permission();
		}
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}

		$moderation->unstick_threads($threads);

		log_moderator_action($modlogdata, $lang->multi_unstuck_threads);
		clearinline($fid, "forum");
		redirect("forumdisplay.php?fid=$fid", $lang->redirect_inline_threadsunstuck);
		break;

	// Move threads - Inline moderation
	case "multimovethreads":
		add_breadcrumb($lang->nav_multi_movethreads);
		$threads = getids($fid, "forum");
		if(!is_array($threads))
		{
			error($lang->error_inline_nothreadsselected);
		}
		$inlineids = implode("|", $threads);
		clearinline($fid, "forum");

		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}
		$forumselect = build_forum_jump("", '', 1, '', 0, '', "moveto");
		eval("\$movethread = \"".$templates->get("moderation_inline_movethreads")."\";");
		output_page($movethread);
		break;

	// Actually move the threads in Inline moderation
	case "do_multimovethreads":
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}
		$moveto = intval($mybb->input['moveto']);
		$threadlist = explode("|", $mybb->input['threads']);
		foreach($threadlist as $tid)
		{
			$tids[] = $tid;
		}
		if(is_moderator($moveto, "canmanagethreads") != "yes" && is_moderator($fid, "canmovetononmodforum") != "yes")
		{
			error_no_permission();
		}
		$newperms = forum_permissions($moveto);
		if($newperms['canview'] == "no" && is_moderator($fid, "canmovetononmodforum") != "yes")
		{
			error_no_permission();
		}
		$query = $db->simple_select(TABLE_PREFIX."forums", "*", "fid='$moveto'");
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

		log_moderator_action($modlogdata, $lang->multi_moved_threads);

		redirect("forumdisplay.php?fid=$moveto", $lang->redirect_inline_threadsmoved);
		break;

	// Delete posts - Inline moderation
	case "multideleteposts":
		add_breadcrumb($lang->nav_multi_deleteposts);
		if(is_moderator($fid, "candeleteposts") != "yes")
		{
			error_no_permission();
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
		output_page($multidelete);
		break;

	// Actually delete the posts in inline moderation
	case "do_multideleteposts":
		if(is_moderator($fid, "candeleteposts") != "yes")
		{
			error_no_permission();
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
		$query = $db->simple_select(TABLE_PREFIX."posts", "*", "tid='$tid'");
		$numposts = $db->num_rows($query);
		if(!$numposts)
		{
			$moderation->delete_thread($tid);
			mark_reports($tid, "thread");
			$url = "forumdisplay.php?fid=$fid";
		}
		else
		{
			update_thread_count($tid);
			mark_reports($plist, "posts");
			$url = "showthread.php?tid=$tid";
		}
		$lang->deleted_selective_posts = sprintf($lang->deleted_selective_posts, $deletecount);
		log_moderator_action($modlogdata, $lang->deleted_selective_posts);
		update_forum_count($fid);
		redirect($url, $lang->redirect_postsdeleted);
		break;

	// Merge posts - Inline moderation
	case "multimergeposts":
		add_breadcrumb($lang->nav_multi_mergeposts);
		if(is_moderator($fid, "candeleteposts") != "yes")
		{
			error_no_permission();
		}
		$posts = getids($tid, "thread");
		if(!is_array($posts))
		{
			error($lang->error_inline_nopostsselected);
		}
		$inlineids = implode("|", $posts);
		clearinline($tid, "thread");

		eval("\$multimerge = \"".$templates->get("moderation_inline_mergeposts")."\";");
		output_page($multimerge);
		break;

	// Actually merge the posts - Inline moderation
	case "do_multimergeposts":
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}
		$postlist = explode("|", $mybb->input['posts']);
		foreach($postlist as $pid)
		{
			$pid = intval($pid);
			$plist[] = $pid;
		}

		$moderation->merge_posts($plist, $tid, $mybb->input['sep']);

		mark_reports($plist, "posts");
		log_moderator_action($modlogdata, $lang->merged_selective_posts);
		redirect("showthread.php?tid=$tid", $lang->redirect_inline_postsmerged);
		break;

	// Split posts - Inline moderation
	case "multisplitposts":
		add_breadcrumb($lang->nav_multi_splitposts);
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
		}
		$query = $db->query("
			SELECT p.*, u.*
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid)
			WHERE tid='$tid'
			ORDER BY dateline ASC
		");
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
		$query = $db->simple_select(TABLE_PREFIX."posts", "*", "pid NOT IN($pidin) AND tid='$tid'");
		$num = $db->num_rows($query);
		if(!$num)
		{
			error($lang->error_cantsplitall);
		}
		$inlineids = implode("|", $posts);
		clearinline($tid, "thread");
		$forumselect = build_forum_jump("", $fid, 1, '', 0, '', "moveto");
		eval("\$splitposts = \"".$templates->get("moderation_inline_splitposts")."\";");
		output_page($splitposts);
		break;

	// Actually split the posts - Inline moderation
	case "do_multisplitposts":
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
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
		$query = $db->simple_select(TABLE_PREFIX."forums", "fid", "fid='$moveto'");
		if($db->num_rows($query) == 0)
		{
			error($lang->error_invalidforum);
		}
		$newsubject = $mybb->input['newsubject'];

		$newtid = $moderation->split_posts($plist, $tid, $moveto, $newsubject);

		$pid_list = implode(', ', $plist);
		$lang->split_selective_posts = sprintf($lang->split_selective_posts, $pid_list, $newtid);
		log_moderator_action($modlogdata, $lang->split_selective_posts);

		redirect("showthread.php?tid=$newtid", $lang->redirect_threadsplit);
		break;

	// Approve posts - Inline moderation
	case "multiapproveposts":
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
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

		log_moderator_action($modlogdata, $lang->multi_approve_posts);
		clearinline($tid, "thread");
		redirect("showthread.php?tid=$tid", $lang->redirect_inline_postsapproved);
		break;

	// Unapprove posts - Inline moderation
	case "multiunapproveposts":
		if(is_moderator($fid, "canmanagethreads") != "yes")
		{
			error_no_permission();
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

		log_moderator_action($modlogdata, $lang->multi_unapprove_posts);
		clearinline($tid, "thread");
		redirect("showthread.php?tid=$tid", $lang->redirect_inline_postsunapproved);
		break;

	// Manage selected reported posts
	case "do_reports":
		if(is_moderator() != "yes")
		{
			error_no_permission();
		}
		$flist = '';
		if($mybb->usergroup['issupermod'] != "yes")
		{
			$query = $db->simple_select(TABLE_PREFIX."moderators", "*", "uid='".$mybb->user['uid']."'");
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
		if(is_moderator() != "yes")
		{
			error_no_permission();
		}
		
		// Figure out if we need to display multiple pages.
		//$perpage = $mybb->settings['postsperpage'];
		$perpage = 5;
		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}
		
		$query = $db->simple_select(TABLE_PREFIX."reportedposts", "COUNT(rid) AS count");
		$warnings = $db->fetch_field($query, "count");
		
		if($mybb->input['rid'])
		{
			$query = $db->simple_select(TABLE_PREFIX."reportedposts", "COUNT(r.rid) AS count", "r.rid <= '".$mybb->input['rid']."'");
			$result = $db->fetch_field($query, "count");
			if(($result % $perpage) == 0)
			{
				$page = $result / $perpage;
			}
			else
			{
				$page = intval($result / $perpage) + 1;
			}
		}
		$postcount = intval($warnings)+1;
		$pages = $postcount / $perpage;
		$pages = ceil($pages);

		if($mybb->input['page'] == "last")
		{
			$page = $pages;
		}

		if($page > $pages)
		{
			$page = 1;
		}

		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}
		$upper = $start+$perpage;

		$multipage = multipage($postcount, $perpage, $page, "moderation.php?action=reports");
		if($postcount > $perpage)
		{
			eval("\$reportspages = \"".$templates->get("moderation_reports_multipage")."\";");
		}

		$query = $db->simple_select(TABLE_PREFIX."forums", "fid,name");
		while($forum = $db->fetch_array($query))
		{
			$forums[$forum['fid']] = $forum['name'];
		}
		$reports = '';
		$query = $db->query("
			SELECT r.*, u.username, up.username AS postusername, up.uid AS postuid, t.subject AS threadsubject
			FROM ".TABLE_PREFIX."reportedposts r
			LEFT JOIN ".TABLE_PREFIX."posts p ON (r.pid=p.pid)
			LEFT JOIN ".TABLE_PREFIX."threads t ON (p.tid=t.tid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."users up ON (p.uid=up.uid)
			WHERE r.reportstatus ='0'
			ORDER BY r.dateline ASC
			LIMIT $start, $perpage
		");
		while($report = $db->fetch_array($query))
		{
			$trow = alt_trow();
			$reportdate = mydate($mybb->settings['dateformat'], $report['dateline']);
			$reporttime = mydate($mybb->settings['timeformat'], $report['dateline']);
			eval("\$reports .= \"".$templates->get("moderation_reports_report")."\";");
		}
		if(!$reports)
		{
			eval("\$reports = \"".$templates->get("moderation_reports_noreports")."\";");
		}

		$plugins->run_hooks("moderation_reports");

		eval("\$reportedposts = \"".$templates->get("moderation_reports")."\";");
		output_page($reportedposts);
		break;
	case "allreports":
		if(is_moderator() != "yes")
		{
			error_no_permission();
		}
		
		// Figure out if we need to display multiple pages.
		//$perpage = $mybb->settings['postsperpage'];
		$perpage = 10;
		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}
		
		$query = $db->simple_select(TABLE_PREFIX."reportedposts", "COUNT(rid) AS count");
		$warnings = $db->fetch_field($query, "count");
		
		if($mybb->input['rid'])
		{
			$query = $db->simple_select(TABLE_PREFIX."reportedposts", "COUNT(rid) AS count", "rid <= '".$mybb->input['rid']."'");
			$result = $db->fetch_field($query, "count");
			if(($result % $perpage) == 0)
			{
				$page = $result / $perpage;
			}
			else
			{
				$page = intval($result / $perpage) + 1;
			}
		}
		$postcount = intval($warnings)+1;
		$pages = $postcount / $perpage;
		$pages = ceil($pages);

		if($mybb->input['page'] == "last")
		{
			$page = $pages;
		}

		if($page > $pages)
		{
			$page = 1;
		}

		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}
		$upper = $start+$perpage;

		$multipage = multipage($postcount, $perpage, $page, "moderation.php?action=allreports");
		if($postcount > $perpage)
		{
			eval("\$allreportspages = \"".$templates->get("moderation_allreports_multipage")."\";");
		}

		$query = $db->simple_select(TABLE_PREFIX."forums", "fid,name");
		while($forum = $db->fetch_array($query))
		{
			$forums[$forum['fid']] = $forum['name'];
		}
		$reports = '';
		$query = $db->query("
			SELECT r.*, u.username, up.username AS postusername, up.uid AS postuid, t.subject AS threadsubject
			FROM ".TABLE_PREFIX."reportedposts r
			LEFT JOIN ".TABLE_PREFIX."posts p ON (r.pid=p.pid)
			LEFT JOIN ".TABLE_PREFIX."threads t ON (p.tid=t.tid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."users up ON (p.uid=up.uid)
			ORDER BY r.dateline ASC
			LIMIT $start, $perpage
		");
		while($report = $db->fetch_array($query))
		{
			$trow = alt_trow();
			$reportdate = mydate($mybb->settings['dateformat'], $report['dateline']);
			$reporttime = mydate($mybb->settings['timeformat'], $report['dateline']);
			if($report['reportstatus'] == 0)
			{
				$report['read'] = $lang->no;
			}
			else
			{
				$report['read'] = $lang->yes;
			}
			eval("\$allreports .= \"".$templates->get("moderation_reports_allreport")."\";");
			
		}
		if(!$allreports)
		{
			eval("\$allreports = \"".$templates->get("moderation_reports_allnoreports")."\";");
		}

		$plugins->run_hooks("moderation_reports");

		eval("\$allreportedposts = \"".$templates->get("moderation_allreports")."\";");
		output_page($allreportedposts);
		break;
	default:
		require MYBB_ROOT."inc/class_custommoderation.php";
		$custommod = new CustomModeration;
		$tool = $custommod->tool_info(intval($mybb->input['action']));
		if($tool !== false)
		{
			if($tool['type'] == 't' && $mybb->input['modtype'] == 'inlinethread')
			{
				$tids = getids($fid, "forum");
				$custommod->execute(intval($mybb->input['action']), $tids);
 				$lang->custom_tool = sprintf($lang->custom_tool, $tool['name']);
				log_moderator_action($modlogdata, $lang->custom_tool);
				clearinline($fid, "forum");
				$lang->redirect_customtool_forum = sprintf($lang->redirect_customtool_forum, $tool['name']);
				redirect("forumdisplay.php?fid=$fid", $lang->redirect_customtool_forum);
				break;
			}
			elseif($tool['type'] == 't' && $mybb->input['modtype'] == 'thread')
			{
				$ret = $custommod->execute(intval($mybb->input['action']), $tid);
 				$lang->custom_tool = sprintf($lang->custom_tool, $tool['name']);
				log_moderator_action($modlogdata, $lang->custom_tool);
				if($ret == 'forum')
				{
					$lang->redirect_customtool_forum = sprintf($lang->redirect_customtool_forum, $tool['name']);
					redirect("forumdisplay.php?fid=$fid", $lang->redirect_customtool_forum);
				}
				else
				{
					$lang->redirect_customtool_thread = sprintf($lang->redirect_customtool_thread, $tool['name']);
					redirect("showthread.php?tid=$tid", $lang->redirect_customtool_thread);
				}
				break;
			}
			elseif($tool['type'] == 'p' && $mybb->input['modtype'] == 'inlinepost')
			{
				$pids = getids($tid, "thread");
				$ret = $custommod->execute(intval($mybb->input['action']), $tid, $pids);
 				$lang->custom_tool = sprintf($lang->custom_tool, $tool['name']);
				log_moderator_action($modlogdata, $lang->custom_tool);
				clearinline($tid, "thread");
				if($ret == 'forum')
				{
					$lang->redirect_customtool_forum = sprintf($lang->redirect_customtool_forum, $tool['name']);
					redirect("forumdisplay.php?fid=$fid", $lang->redirect_customtool_forum);
				}
				else
				{
					$lang->redirect_customtool_thread = sprintf($lang->redirect_customtool_thread, $tool['name']);
					redirect("showthread.php?tid=$tid", $lang->redirect_customtool_thread);
				}
				break;
			}
		}
		error_no_permission();
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