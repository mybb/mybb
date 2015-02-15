<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'showthread.php');

$templatelist = "showthread,postbit,postbit_author_user,postbit_author_guest,showthread_newthread,showthread_newreply,showthread_newreply_closed,postbit_avatar,postbit_find,postbit_pm,postbit_www,postbit_email,postbit_edit,postbit_quote,postbit_report,postbit_signature,postbit_online,postbit_offline,postbit_away,showthread_ratethread";
$templatelist .= ",multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start,showthread_inlinemoderation_softdelete,showthread_inlinemoderation_restore,showthread_inlinemoderation_delete,postbit_posturl,postbit_icon";
$templatelist .= ",postbit_editedby,showthread_similarthreads,showthread_similarthreads_bit,postbit_iplogged_show,postbit_iplogged_hiden,postbit_profilefield,showthread_quickreply,showthread_add_poll,showthread_send_thread,showthread_poll_editpoll,showthread_poll_undovote,showthread_moderationoptions_standard,postbit_editedby_editreason";
$templatelist .= ",forumjump_advanced,forumjump_special,forumjump_bit,postbit_reputation,postbit_quickdelete,postbit_attachments,postbit_attachments_attachment,postbit_attachments_thumbnails,postbit_attachments_images_image,postbit_attachments_images,showthread_quickreply_options_stick,showthread_quickreply_options_close,attachment_icon";
$templatelist .= ",postbit_inlinecheck,showthread_inlinemoderation,postbit_attachments_thumbnails_thumbnail,postbit_ignored,postbit_groupimage,postbit_multiquote,showthread_search,showthread_moderationoptions_custom_tool,showthread_moderationoptions_custom,showthread_inlinemoderation_custom_tool,showthread_inlinemoderation_custom";
$templatelist .= ",showthread_usersbrowsing,showthread_usersbrowsing_user,showthread_poll_option_multiple,showthread_poll_option,showthread_poll,showthread_quickreply_options_signature,showthread_threaded_bitactive,showthread_threaded_bit,postbit_attachments_attachment_unapproved,showthread_threadnotes,showthread_threadnotes_viewnotes";
$templatelist .= ",showthread_moderationoptions_openclose,showthread_moderationoptions_stickunstick,showthread_moderationoptions_delete,showthread_moderationoptions_threadnotes,showthread_moderationoptions_manage,showthread_moderationoptions_deletepoll,showthread_threadnoteslink,showthread_poll_results,showthread_classic_header,postbit_warn";
$templatelist .= ",postbit_userstar,postbit_reputation_formatted_link,postbit_warninglevel_formatted,postbit_quickrestore,forumdisplay_password,forumdisplay_password_wrongpass,postbit_classic,postbit_purgespammer,showthread_inlinemoderation_approve,forumdisplay_thread_icon,showthread_poll_resultbit,postbit_warninglevel,postbit_gotopost";
$templatelist .= ",showthread_moderationoptions_softdelete,showthread_moderationoptions_restore,post_captcha,post_captcha_recaptcha,post_captcha_nocaptcha,post_captcha_ayah,showthread_moderationoptions,showthread_inlinemoderation_standard,showthread_threadedbox,postbit_rep_button,showthread_inlinemoderation_manage,global_moderation_notice";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_indicators.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("showthread");

// If there is no tid but a pid, trick the system into thinking there was a tid anyway.
if(!empty($mybb->input['pid']) && !isset($mybb->input['tid']))
{
	// see if we already have the post information
	if(isset($style) && $style['pid'] == $mybb->get_input('pid', MyBB::INPUT_INT) && $style['tid'])
	{
		$mybb->input['tid'] = $style['tid'];
		unset($style['tid']); // stop the thread caching code from being tricked
	}
	else
	{
		$options = array(
			"limit" => 1
		);
		$query = $db->simple_select("posts", "tid", "pid=".$mybb->get_input('pid', MyBB::INPUT_INT), $options);
		$post = $db->fetch_array($query);
		
		if(empty($post))
		{
			// post does not exist --> show corresponding error
			error($lang->error_invalidpost);
		}
		
		$mybb->input['tid'] = $post['tid'];
	}
}

// Get the thread details from the database.
$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));

if(!$thread || substr($thread['closed'], 0, 6) == "moved|")
{
	error($lang->error_invalidthread);
}

// Get thread prefix if there is one.
$thread['threadprefix'] = '';
$thread['displayprefix'] = '';
if($thread['prefix'] != 0)
{
	$threadprefix = build_prefixes($thread['prefix']);

	if(!empty($threadprefix['prefix']))
	{
		$thread['threadprefix'] = htmlspecialchars_uni($threadprefix['prefix']).'&nbsp;';
		$thread['displayprefix'] = $threadprefix['displaystyle'].'&nbsp;';
	}
}

$reply_subject = $parser->parse_badwords($thread['subject']);
$thread['subject'] = htmlspecialchars_uni($reply_subject);
// Subject too long? Shorten it to avoid error message
if(my_strlen($reply_subject) > 85)
{
	$reply_subject = my_substr($reply_subject, 0, 82).'...';
}
$reply_subject = htmlspecialchars_uni($reply_subject);
$tid = $thread['tid'];
$fid = $thread['fid'];

if(!$thread['username'])
{
	$thread['username'] = $lang->guest;
}

// Is the currently logged in user a moderator of this forum?
if(is_moderator($fid))
{
	$ismod = true;
	if(is_moderator($fid, "canviewdeleted") == true || is_moderator($fid, "canviewunapprove") == true)
	{
		if(is_moderator($fid, "canviewunapprove") == true && is_moderator($fid, "canviewdeleted") == false)
		{
			$visibleonly = " AND visible IN (0,1)";
			$visibleonly2 = "AND p.visible IN (0,1) AND t.visible IN (0,1)";
		}
		elseif(is_moderator($fid, "canviewdeleted") == true && is_moderator($fid, "canviewunapprove") == false)
		{
			$visibleonly = " AND visible IN (-1,1)";
			$visibleonly2 = "AND p.visible IN (-1,1) AND t.visible IN (-1,1)";
		}
		else
		{
			$visibleonly = " AND visible IN (-1,0,1)";
			$visibleonly2 = "AND p.visible IN (-1,0,1) AND t.visible IN (-1,0,1)";
		}
	}
}
else
{
	$ismod = false;
}

// Make sure we are looking at a real thread here.
if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] == 0 && !is_moderator($fid, "canviewunapprove")) || ($thread['visible'] == -1 && !is_moderator($fid, "canviewdeleted")))
{
	error($lang->error_invalidthread);
}

$forumpermissions = forum_permissions($thread['fid']);

// Does the user have permission to view this thread?
if($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
{
	error_no_permission();
}

if(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
{
	error_no_permission();
}

$archive_url = build_archive_link("thread", $tid);

// Does the thread belong to a valid forum?
$forum = get_forum($fid);
if(!$forum || $forum['type'] != "f")
{
	error($lang->error_invalidforum);
}

$threadnoteslink = '';
if(is_moderator($fid, "canmanagethreads") && !empty($thread['notes']))
{
	eval('$threadnoteslink = "'.$templates->get('showthread_threadnoteslink').'";');
}

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);

// If there is no specific action, we must be looking at the thread.
if(!$mybb->get_input('action'))
{
	$mybb->input['action'] = "thread";
}

// Jump to the unread posts.
if($mybb->input['action'] == "newpost")
{
	// First, figure out what time the thread or forum were last read
	$query = $db->simple_select("threadsread", "dateline", "uid='{$mybb->user['uid']}' AND tid='{$thread['tid']}'");
	$thread_read = $db->fetch_field($query, "dateline");

	if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'])
	{
		$query = $db->simple_select("forumsread", "dateline", "fid='{$fid}' AND uid='{$mybb->user['uid']}'");
		$forum_read = $db->fetch_field($query, "dateline");

		$read_cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
		if($forum_read == 0 || $forum_read < $read_cutoff)
		{
			$forum_read = $read_cutoff;
		}
	}
	else
	{
		$forum_read = (int)my_get_array_cookie("forumread", $fid);
	}

	if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'] && $thread['lastpost'] > $forum_read)
	{
		$cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
		if($thread['lastpost'] > $cutoff)
		{
			if($thread_read)
			{
				$lastread = $thread_read;
			}
			else
			{
				// Set $lastread to zero to make sure 'lastpost' is invoked in the last IF
				$lastread = 0;
			}
		}
	}

	if(!$lastread)
	{
		$readcookie = $threadread = (int)my_get_array_cookie("threadread", $thread['tid']);
		if($readcookie > $forum_read)
		{
			$lastread = $readcookie;
		}
		else
		{
			$lastread = $forum_read;
		}
	}

   if($cutoff && $lastread < $cutoff)
   {
		  $lastread = $cutoff;
   }

	// Next, find the proper pid to link to.
	$options = array(
		"limit_start" => 0,
		"limit" => 1,
		"order_by" => "dateline",
		"order_dir" => "asc"
	);

	$lastread = (int)$lastread;
	$query = $db->simple_select("posts", "pid", "tid='{$tid}' AND dateline > '{$lastread}' {$visibleonly}", $options);
	$newpost = $db->fetch_array($query);

	if($newpost['pid'] && $lastread)
	{
		$highlight = '';
		if($mybb->get_input('highlight'))
		{
			$string = "&";
			if($mybb->seo_support == true)
			{
				$string = "?";
			}

			$highlight = $string."highlight=".$mybb->get_input('highlight');
		}

		header("Location: ".htmlspecialchars_decode(get_post_link($newpost['pid'], $tid)).$highlight."#pid{$newpost['pid']}");
	}
	else
	{
		// show them to the last post
		$mybb->input['action'] = "lastpost";
	}
}

// Jump to the last post.
if($mybb->input['action'] == "lastpost")
{
	if(my_strpos($thread['closed'], "moved|"))
	{
		$query = $db->query("
			SELECT p.pid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON(p.tid=t.tid)
			WHERE t.fid='".$thread['fid']."' AND t.closed NOT LIKE 'moved|%' {$visibleonly2}
			ORDER BY p.dateline DESC
			LIMIT 1
		");
		$pid = $db->fetch_field($query, "pid");
	}
	else
	{
		$options = array(
			'order_by' => 'dateline',
			'order_dir' => 'desc',
			'limit_start' => 0,
			'limit' => 1
		);
		$query = $db->simple_select('posts', 'pid', "tid={$tid} {$visibleonly}", $options);
		$pid = $db->fetch_field($query, "pid");
	}
	header("Location: ".htmlspecialchars_decode(get_post_link($pid, $tid))."#pid{$pid}");
	exit;
}

// Jump to the next newest posts.
if($mybb->input['action'] == "nextnewest")
{
	$options = array(
		"limit_start" => 0,
		"limit" => 1,
		"order_by" => "lastpost"
	);
	$query = $db->simple_select('threads', '*', "fid={$thread['fid']} AND lastpost > {$thread['lastpost']} {$visibleonly} AND closed NOT LIKE 'moved|%'", $options);
	$nextthread = $db->fetch_array($query);

	// Are there actually next newest posts?
	if(!$nextthread['tid'])
	{
		error($lang->error_nonextnewest);
	}
	$options = array(
		"limit_start" => 0,
		"limit" => 1,
		"order_by" => "dateline",
		"order_dir" => "desc"
	);
	$query = $db->simple_select('posts', 'pid', "tid='{$nextthread['tid']}'", $options);

	// Redirect to the proper page.
	$pid = $db->fetch_field($query, "pid");
	header("Location: ".htmlspecialchars_decode(get_post_link($pid, $nextthread['tid']))."#pid{$pid}");
	exit;
}

// Jump to the next oldest posts.
if($mybb->input['action'] == "nextoldest")
{
	$options = array(
		"limit" => 1,
		"limit_start" => 0,
		"order_by" => "lastpost",
		"order_dir" => "desc"
	);
	$query = $db->simple_select("threads", "*", "fid=".$thread['fid']." AND lastpost < ".$thread['lastpost']." {$visibleonly} AND closed NOT LIKE 'moved|%'", $options);
	$nextthread = $db->fetch_array($query);

	// Are there actually next oldest posts?
	if(!$nextthread['tid'])
	{
		error($lang->error_nonextoldest);
	}
	$options = array(
		"limit_start" => 0,
		"limit" => 1,
		"order_by" => "dateline",
		"order_dir" => "desc"
	);
	$query = $db->simple_select("posts", "pid", "tid='".$nextthread['tid']."'", $options);

	// Redirect to the proper page.
	$pid = $db->fetch_field($query, "pid");
	header("Location: ".htmlspecialchars_decode(get_post_link($pid, $nextthread['tid']))."#pid{$pid}");
	exit;
}

$pid = $mybb->input['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);

// Forumdisplay cache
$forum_stats = $cache->read("forumsdisplay");

$breadcrumb_multipage = array();
if($mybb->settings['showforumpagesbreadcrumb'])
{
	// How many pages are there?
	if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	$query = $db->simple_select("forums", "threads, unapprovedthreads", "fid = '{$fid}'", array('limit' => 1));
	$forum_threads = $db->fetch_array($query);
	$threadcount = $forum_threads['threads'];

	if(is_moderator($fid, "canviewunapprove") == true)
	{
		$threadcount += $forum_threads['unapprovedthreads'];
	}

	// Limit to only our own threads
	$uid_only = '';
	if(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] == 1)
	{
		$uid_only = " AND uid = '".$mybb->user['uid']."'";

		$query = $db->simple_select("threads", "COUNT(tid) AS threads", "fid = '$fid' $visibleonly $uid_only", array('limit' => 1));
		$threadcount = $db->fetch_field($query, "threads");
	}

	// If we have 0 threads double check there aren't any "moved" threads
	if($threadcount == 0)
	{
		$query = $db->simple_select("threads", "COUNT(tid) AS threads", "fid = '$fid' $visibleonly $uid_only", array('limit' => 1));
		$threadcount = $db->fetch_field($query, "threads");
	}

	$stickybit = " OR sticky=1";
	if($thread['sticky'] == 1)
	{
		$stickybit = " AND sticky=1";
	}

	// Figure out what page the thread is actually on
	switch($db->type)
	{
		case "pgsql":
			$query = $db->query("
				SELECT COUNT(tid) as threads
				FROM ".TABLE_PREFIX."threads
				WHERE fid = '$fid' AND (lastpost >= '".(int)$thread['lastpost']."'{$stickybit}) {$visibleonly} {$uid_only}
				GROUP BY lastpost
				ORDER BY lastpost DESC
			");
			break;
		default:
			$query = $db->simple_select("threads", "COUNT(tid) as threads", "fid = '$fid' AND (lastpost >= '".(int)$thread['lastpost']."'{$stickybit}) {$visibleonly} {$uid_only}", array('order_by' => 'lastpost', 'order_dir' => 'desc'));
	}

	$thread_position = $db->fetch_field($query, "threads");
	$thread_page = ceil(($thread_position/$mybb->settings['threadsperpage']));

	$breadcrumb_multipage = array(
		"num_threads" => $threadcount,
		"current_page" => $thread_page
	);
}

// Build the navigation.
build_forum_breadcrumb($fid, $breadcrumb_multipage);
add_breadcrumb($thread['displayprefix'].$thread['subject'], get_thread_link($thread['tid']));

$plugins->run_hooks("showthread_start");

// Show the entire thread (taking into account pagination).
if($mybb->input['action'] == "thread")
{
	if($thread['firstpost'] == 0)
	{
		update_first_post($tid);
	}

	// Does this thread have a poll?
	if($thread['poll'])
	{
		$options = array(
			"limit" => 1
		);
		$query = $db->simple_select("polls", "*", "pid='".$thread['poll']."'", $options);
		$poll = $db->fetch_array($query);
		$poll['timeout'] = $poll['timeout']*60*60*24;
		$expiretime = $poll['dateline'] + $poll['timeout'];
		$now = TIME_NOW;

		// If the poll or the thread is closed or if the poll is expired, show the results.
		if($poll['closed'] == 1 || $thread['closed'] == 1 || ($expiretime < $now && $poll['timeout'] > 0))
		{
			$showresults = 1;
		}

		// If the user is not a guest, check if he already voted.
		if($mybb->user['uid'] != 0)
		{
			$query = $db->simple_select("pollvotes", "*", "uid='".$mybb->user['uid']."' AND pid='".$poll['pid']."'");
			while($votecheck = $db->fetch_array($query))
			{
				$alreadyvoted = 1;
				$votedfor[$votecheck['voteoption']] = 1;
			}
		}
		else
		{
			if(isset($mybb->cookies['pollvotes'][$poll['pid']]) && $mybb->cookies['pollvotes'][$poll['pid']] !== "")
			{
				$alreadyvoted = 1;
			}
		}
		$optionsarray = explode("||~|~||", $poll['options']);
		$votesarray = explode("||~|~||", $poll['votes']);
		$poll['question'] = htmlspecialchars_uni($poll['question']);
		$polloptions = '';
		$totalvotes = 0;
		$poll['totvotes'] = 0;

		for($i = 1; $i <= $poll['numoptions']; ++$i)
		{
			$poll['totvotes'] = $poll['totvotes'] + $votesarray[$i-1];
		}

		// Loop through the poll options.
		for($i = 1; $i <= $poll['numoptions']; ++$i)
		{
			// Set up the parser options.
			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode'],
				"allow_videocode" => $forum['allowvideocode'],
				"filter_badwords" => 1
			);

			if($mybb->user['showimages'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestimages'] != 1 && $mybb->user['uid'] == 0)
			{
				$parser_options['allow_imgcode'] = 0;
			}

			if($mybb->user['showvideos'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestvideos'] != 1 && $mybb->user['uid'] == 0)
			{
				$parser_options['allow_videocode'] = 0;
			}

			$option = $parser->parse_message($optionsarray[$i-1], $parser_options);
			$votes = $votesarray[$i-1];
			$totalvotes += $votes;
			$number = $i;

			// Mark the option the user voted for.
			if(!empty($votedfor[$number]))
			{
				$optionbg = "trow2";
				$votestar = "*";
			}
			else
			{
				$optionbg = "trow1";
				$votestar = "";
			}

			// If the user already voted or if the results need to be shown, do so; else show voting screen.
			if(isset($alreadyvoted) || isset($showresults))
			{
				if((int)$votes == "0")
				{
					$percent = "0";
				}
				else
				{
					$percent = number_format($votes / $poll['totvotes'] * 100, 2);
				}
				$imagewidth = round($percent);
				eval("\$polloptions .= \"".$templates->get("showthread_poll_resultbit")."\";");
			}
			else
			{
				if($poll['multiple'] == 1)
				{
					eval("\$polloptions .= \"".$templates->get("showthread_poll_option_multiple")."\";");
				}
				else
				{
					eval("\$polloptions .= \"".$templates->get("showthread_poll_option")."\";");
				}
			}
		}

		// If there are any votes at all, all votes together will be 100%; if there are no votes, all votes together will be 0%.
		if($poll['totvotes'])
		{
			$totpercent = "100%";
		}
		else
		{
			$totpercent = "0%";
		}

		// Check if user is allowed to edit posts; if so, show "edit poll" link.
		$edit_poll = '';
		if(is_moderator($fid, 'canmanagepolls'))
		{
			eval("\$edit_poll = \"".$templates->get("showthread_poll_editpoll")."\";");
		}

		// Decide what poll status to show depending on the status of the poll and whether or not the user voted already.
		if(isset($alreadyvoted) || isset($showresults))
		{
			if($alreadyvoted)
			{
				$pollstatus = $lang->already_voted;

				if($mybb->usergroup['canundovotes'] == 1)
				{
					eval("\$pollstatus .= \"".$templates->get("showthread_poll_undovote")."\";");
				}
			}
			else
			{
				$pollstatus = $lang->poll_closed;
			}
			$lang->total_votes = $lang->sprintf($lang->total_votes, $totalvotes);
			eval("\$pollbox = \"".$templates->get("showthread_poll_results")."\";");
			$plugins->run_hooks("showthread_poll_results");
		}
		else
		{
			$closeon = '&nbsp;';
			if($poll['timeout'] != 0)
			{
				$closeon = $lang->sprintf($lang->poll_closes, my_date($mybb->settings['dateformat'], $expiretime));
			}

			$publicnote = '&nbsp;';
			if($poll['public'] == 1)
			{
				$publicnote = $lang->public_note;
			}

			eval("\$pollbox = \"".$templates->get("showthread_poll")."\";");
			$plugins->run_hooks("showthread_poll");
		}

	}
	else
	{
		$pollbox = "";
	}

	// Create the forum jump dropdown box.
	if($mybb->settings['enableforumjump'] != 0)
	{
		$forumjump = build_forum_jump("", $fid, 1);
	}

	// Fetch some links
	$next_oldest_link = get_thread_link($tid, 0, "nextoldest");
	$next_newest_link = get_thread_link($tid, 0, "nextnewest");

	// Mark this thread as read
	mark_thread_read($tid, $fid);

	// If the forum is not open, show closed newreply button unless the user is a moderator of this forum.
	$newthread = $newreply = '';
	if($forum['open'] != 0 && $forum['type'] == "f")
	{
		if($forumpermissions['canpostthreads'] != 0 && $mybb->user['suspendposting'] != 1)
		{
			eval("\$newthread = \"".$templates->get("showthread_newthread")."\";");
		}

		// Show the appropriate reply button if this thread is open or closed
		if($forumpermissions['canpostreplys'] != 0 && $mybb->user['suspendposting'] != 1 && ($thread['closed'] != 1 || is_moderator($fid, "canpostclosedthreads")) && ($thread['uid'] == $mybb->user['uid'] || $forumpermissions['canonlyreplyownthreads'] != 1))
		{
			eval("\$newreply = \"".$templates->get("showthread_newreply")."\";");
		}
		elseif($thread['closed'] == 1)
		{
			eval("\$newreply = \"".$templates->get("showthread_newreply_closed")."\";");
		}
	}

	// Create the admin tools dropdown box.
	if($ismod == true)
	{
		$closelinkch = $stickch = '';

		if($thread['closed'] == 1)
		{
			$closelinkch = ' checked="checked"';
		}

		if($thread['sticky'])
		{
			$stickch = ' checked="checked"';
		}

		if(is_moderator($thread['fid'], "canopenclosethreads"))
		{
			eval("\$closeoption .= \"".$templates->get("showthread_quickreply_options_close")."\";");
		}

		if(is_moderator($thread['fid'], "canstickunstickthreads"))
		{
			eval("\$closeoption .= \"".$templates->get("showthread_quickreply_options_stick")."\";");
		}

		$inlinecount = "0";
		$inlinecookie = "inlinemod_thread".$tid;

		$plugins->run_hooks("showthread_ismod");
	}
	else
	{
		$modoptions = "&nbsp;";
		$inlinemod = $closeoption = '';
	}

	// Increment the thread view.
	if($mybb->settings['delayedthreadviews'] == 1)
	{
		$db->shutdown_query("INSERT INTO ".TABLE_PREFIX."threadviews (tid) VALUES('{$tid}')");
	}
	else
	{
		$db->shutdown_query("UPDATE ".TABLE_PREFIX."threads SET views=views+1 WHERE tid='{$tid}'");
	}
	++$thread['views'];

	// Work out the thread rating for this thread.
	$rating = '';
	if($mybb->settings['allowthreadratings'] != 0 && $forum['allowtratings'] != 0)
	{
		$rated = 0;
		$lang->load("ratethread");
		if($thread['numratings'] <= 0)
		{
			$thread['width'] = 0;
			$thread['averagerating'] = 0;
			$thread['numratings'] = 0;
		}
		else
		{
			$thread['averagerating'] = (float)round($thread['totalratings']/$thread['numratings'], 2);
			$thread['width'] = (int)round($thread['averagerating'])*20;
			$thread['numratings'] = (int)$thread['numratings'];
		}

		if($thread['numratings'])
		{
			// At least >someone< has rated this thread, was it me?
			// Check if we have already voted on this thread - it won't show hover effect then.
			$query = $db->simple_select("threadratings", "uid", "tid='{$tid}' AND uid='{$mybb->user['uid']}'");
			$rated = $db->fetch_field($query, 'uid');
		}

		$not_rated = '';
		if(!$rated)
		{
			$not_rated = ' star_rating_notrated';
		}

		$ratingvotesav = $lang->sprintf($lang->rating_average, $thread['numratings'], $thread['averagerating']);
		eval("\$ratethread = \"".$templates->get("showthread_ratethread")."\";");
	}
	// Work out if we are showing unapproved posts as well (if the user is a moderator etc.)
	if($ismod && is_moderator($fid, "canviewdeleted") == true && is_moderator($fid, "canviewunapprove") == false)
	{
		$visible = "AND p.visible IN (-1,1)";
	}
	elseif($ismod && is_moderator($fid, "canviewdeleted") == false && is_moderator($fid, "canviewunapprove") == true)
	{
		$visible = "AND p.visible IN (0,1)";
	}
	elseif($ismod && is_moderator($fid, "canviewdeleted") == true && is_moderator($fid, "canviewunapprove") == true)
	{
		$visible = "AND p.visible IN (-1,0,1)";
	}
	else
	{
		$visible = "AND p.visible='1'";
	}

	// Can this user perform searches? If so, we can show them the "Search thread" form
	if($forumpermissions['cansearch'] != 0)
	{
		eval("\$search_thread = \"".$templates->get("showthread_search")."\";");
	}

	// Fetch the ignore list for the current user if they have one
	$ignored_users = array();
	if($mybb->user['uid'] > 0 && $mybb->user['ignorelist'] != "")
	{
		$ignore_list = explode(',', $mybb->user['ignorelist']);
		foreach($ignore_list as $uid)
		{
			$ignored_users[$uid] = 1;
		}
	}

	// Fetch profile fields to display on postbit
	$pfcache = $cache->read('profilefields');

	if(is_array($pfcache))
	{
		foreach($pfcache as $profilefield)
		{
			if($profilefield['postbit'] != 1)
			{
				continue;
			}

			$profile_fields[$profilefield['fid']] = $profilefield;
		}
	}

	// Which thread mode is our user using by default?
	if(!empty($mybb->user['threadmode']))
	{
		$defaultmode = $mybb->user['threadmode'];
	}
	else if($mybb->settings['threadusenetstyle'] == 1)
	{
		$defaultmode = 'threaded';
	}
	else
	{
		$defaultmode = 'linear';
	}

	// If mode is unset, set the default mode
	if(!isset($mybb->input['mode']))
	{
		$mybb->input['mode'] = $defaultmode;
	}

	// Threaded or linear display?
	$threadexbox = '';
	if($mybb->get_input('mode') == 'threaded')
	{
		$isfirst = 1;

		// Are we linked to a specific pid?
		if($mybb->input['pid'])
		{
			$where = "AND p.pid='".$mybb->input['pid']."'";
		}
		else
		{
			$where = " ORDER BY dateline LIMIT 0, 1";
		}
		$query = $db->query("
			SELECT u.*, u.username AS userusername, p.*, f.*, eu.username AS editusername
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."users eu ON (eu.uid=p.edituid)
			WHERE p.tid='$tid' $visible $where
		");
		$showpost = $db->fetch_array($query);

		// Choose what pid to display.
		if(!$mybb->input['pid'])
		{
			$mybb->input['pid'] = $showpost['pid'];
		}

		// Is there actually a pid to display?
		if(!$showpost['pid'])
		{
			error($lang->error_invalidpost);
		}

		$attachcache = array();
		if($mybb->settings['enableattachments'] == 1 && $thread['attachmentcount'] > 0 || is_moderator($fid, 'caneditposts'))
		{
			// Get the attachments for this post.
			$query = $db->simple_select("attachments", "*", "pid=".$mybb->input['pid']);
			while($attachment = $db->fetch_array($query))
			{
				$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
			}
		}

		// Build the threaded post display tree.
		$query = $db->query("
            SELECT p.username, p.uid, p.pid, p.replyto, p.subject, p.dateline
            FROM ".TABLE_PREFIX."posts p
            WHERE p.tid='$tid'
            $visible
            ORDER BY p.dateline
        ");
        while($post = $db->fetch_array($query))
        {
            if(!$postsdone[$post['pid']])
            {
                if($post['pid'] == $mybb->input['pid'] || ($isfirst && !$mybb->input['pid']))
                {
					$postcounter = count($postsdone);
                    $isfirst = 0;
                }
                $tree[$post['replyto']][$post['pid']] = $post;
                $postsdone[$post['pid']] = 1;
            }
        }

		$threadedbits = buildtree();
		$posts = build_postbit($showpost);
		eval("\$threadexbox = \"".$templates->get("showthread_threadedbox")."\";");
		$plugins->run_hooks("showthread_threaded");
	}
	else // Linear display
	{
		$threadexbox = '';
		if(!$mybb->settings['postsperpage'] || (int)$mybb->settings['postsperpage'] < 1)
		{
			$mybb->settings['postsperpage'] = 20;
		}

		// Figure out if we need to display multiple pages.
		$page = 1;
		$perpage = $mybb->settings['postsperpage'];
		if($mybb->get_input('page', MyBB::INPUT_INT) && $mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', MyBB::INPUT_INT);
		}

		if(!empty($mybb->input['pid']))
		{
			$post = get_post($mybb->input['pid']);
			if($post)
			{
				$query = $db->query("
					SELECT COUNT(p.dateline) AS count FROM ".TABLE_PREFIX."posts p
					WHERE p.tid = '{$tid}'
					AND p.dateline <= '{$post['dateline']}'
					{$visible}
				");
				$result = $db->fetch_field($query, "count");
				if(($result % $perpage) == 0)
				{
					$page = $result / $perpage;
				}
				else
				{
					$page = (int)($result / $perpage) + 1;
				}
			}
		}

		// Recount replies if user is a moderator to take into account unapproved posts.
		if($ismod)
		{
			$query = $db->simple_select("posts p", "COUNT(*) AS replies", "p.tid='$tid' $visible");
			$cached_replies = $thread['replies']+$thread['unapprovedposts']+$thread['deletedposts'];
			$thread['replies'] = $db->fetch_field($query, 'replies')-1;

			// The counters are wrong? Rebuild them
			// This doesn't cover all cases however it is a good addition to the manual rebuild function
			if($thread['replies'] != $cached_replies)
			{
				require_once MYBB_ROOT."/inc/functions_rebuild.php";
				rebuild_thread_counters($thread['tid']);
			}
		}

		$postcount = (int)$thread['replies']+1;
		$pages = $postcount / $perpage;
		$pages = ceil($pages);

		if($mybb->get_input('page') == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
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

		// Work out if we have terms to highlight
        $highlight = "";
        $threadmode = "";
        if($mybb->seo_support == true)
        {
            if($mybb->get_input('highlight'))
            {
                $highlight = "?highlight=".urlencode($mybb->get_input('highlight'));
            }

			if($defaultmode != "linear")
			{
	            if($mybb->get_input('highlight'))
	            {
	                $threadmode = "&amp;mode=linear";
	            }
	            else
	            {
	                $threadmode = "?mode=linear";
	            }
			}
        }
        else
        {
			if(!empty($mybb->input['highlight']))
			{
				if(is_array($mybb->input['highlight']))
				{
					foreach($mybb->input['highlight'] as $highlight_word)
					{
						$highlight .= "&amp;highlight[]=".urlencode($highlight_word);
					}
				}
				else
				{
					$highlight = "&amp;highlight=".urlencode($mybb->get_input('highlight'));
				}
			}

            if($defaultmode != "linear")
            {
                $threadmode = "&amp;mode=linear";
            }
        }

        $multipage = multipage($postcount, $perpage, $page, str_replace("{tid}", $tid, THREAD_URL_PAGED.$highlight.$threadmode));

		// Lets get the pids of the posts on this page.
		$pids = "";
		$comma = '';
		$query = $db->simple_select("posts p", "p.pid", "p.tid='$tid' $visible", array('order_by' => 'p.dateline', 'limit_start' => $start, 'limit' => $perpage));
		while($getid = $db->fetch_array($query))
		{
			// Set the ID of the first post on page to $pid if it doesn't hold any value
			// to allow this value to be used for Thread Mode/Linear Mode links
			// and ensure the user lands on the correct page after changing view mode
			if(empty($pid))
			{
				$pid = $getid['pid'];
			}
			// Gather a comma separated list of post IDs
			$pids .= "$comma'{$getid['pid']}'";
			$comma = ",";
		}
		if($pids)
		{
			$pids = "pid IN($pids)";

			$attachcache = array();
			if($mybb->settings['enableattachments'] == 1 && $thread['attachmentcount'] > 0 || is_moderator($fid, 'caneditposts'))
			{
				// Now lets fetch all of the attachments for these posts.
				$query = $db->simple_select("attachments", "*", $pids);
				while($attachment = $db->fetch_array($query))
				{
					$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
				}
			}
		}
		else
		{
			// If there are no pid's the thread is probably awaiting approval.
			error($lang->error_invalidthread);
		}

		// Get the actual posts from the database here.
		$posts = '';
		$query = $db->query("
			SELECT u.*, u.username AS userusername, p.*, f.*, eu.username AS editusername
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."users eu ON (eu.uid=p.edituid)
			WHERE $pids
			ORDER BY p.dateline
		");
		while($post = $db->fetch_array($query))
		{
			if($thread['firstpost'] == $post['pid'] && $thread['visible'] == 0)
			{
				$post['visible'] = 0;
			}
			$posts .= build_postbit($post);
			$post = '';
		}
		$plugins->run_hooks("showthread_linear");
	}

	// Show the similar threads table if wanted.
	$similarthreads = '';
	if($mybb->settings['showsimilarthreads'] != 0)
	{
		$own_perm = '';
		if($forumpermissions['canonlyviewownthreads'] == 1)
		{
			$own_perm = " AND t.uid={$mybb->user['uid']}";
		}

		switch($db->type)
		{
			case "pgsql":
				$query = $db->query("
					SELECT t.*, t.username AS threadusername, u.username
					FROM ".TABLE_PREFIX."threads t
					LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid), plainto_tsquery ('".$db->escape_string($thread['subject'])."') AS query
					WHERE t.fid='{$thread['fid']}' AND t.tid!='{$thread['tid']}' AND t.visible='1' AND t.closed NOT LIKE 'moved|%' AND t.subject @@ query{$own_perm}
					ORDER BY t.lastpost DESC
					OFFSET 0 LIMIT {$mybb->settings['similarlimit']}
				");
				break;
			default:
				$query = $db->query("
					SELECT t.*, t.username AS threadusername, u.username, MATCH (t.subject) AGAINST ('".$db->escape_string($thread['subject'])."') AS relevance
					FROM ".TABLE_PREFIX."threads t
					LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
					WHERE t.fid='{$thread['fid']}' AND t.tid!='{$thread['tid']}' AND t.visible='1' AND t.closed NOT LIKE 'moved|%'{$own_perm} AND MATCH (t.subject) AGAINST ('".$db->escape_string($thread['subject'])."') >= '{$mybb->settings['similarityrating']}'
					ORDER BY t.lastpost DESC
					LIMIT 0, {$mybb->settings['similarlimit']}
				");
		}

		$count = 0;
		$similarthreadbits = '';
		$icon_cache = $cache->read("posticons");
		while($similar_thread = $db->fetch_array($query))
		{
			++$count;
			$trow = alt_trow();
			if($similar_thread['icon'] > 0 && $icon_cache[$similar_thread['icon']])
			{
				$icon = $icon_cache[$similar_thread['icon']];
				$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
				$icon['path'] = htmlspecialchars_uni($icon['path']);
				$icon['name'] = htmlspecialchars_uni($icon['name']);
				eval("\$icon = \"".$templates->get("forumdisplay_thread_icon")."\";");
			}
			else
			{
				$icon = "&nbsp;";
			}
			if(!$similar_thread['username'])
			{
				$similar_thread['username'] = $similar_thread['threadusername'];
				$similar_thread['profilelink'] = $similar_thread['threadusername'];
			}
			else
			{
				$similar_thread['profilelink'] = build_profile_link($similar_thread['username'], $similar_thread['uid']);
			}

			// If this thread has a prefix, insert a space between prefix and subject
			if($similar_thread['prefix'] != 0)
			{
				$prefix = build_prefixes($similar_thread['prefix']);
				if(!empty($prefix))
				{
					$similar_thread['threadprefix'] = $prefix['displaystyle'].'&nbsp;';
				}
			}

			$similar_thread['subject'] = $parser->parse_badwords($similar_thread['subject']);
			$similar_thread['subject'] = htmlspecialchars_uni($similar_thread['subject']);
			$similar_thread['threadlink'] = get_thread_link($similar_thread['tid']);
			$similar_thread['lastpostlink'] = get_thread_link($similar_thread['tid'], 0, "lastpost");

			$lastpostdate = my_date('relative', $similar_thread['lastpost']);
			$lastposter = $similar_thread['lastposter'];
			$lastposteruid = $similar_thread['lastposteruid'];

			// Don't link to guest's profiles (they have no profile).
			if($lastposteruid == 0)
			{
				$lastposterlink = $lastposter;
			}
			else
			{
				$lastposterlink = build_profile_link($lastposter, $lastposteruid);
			}
			$similar_thread['replies'] = my_number_format($similar_thread['replies']);
			$similar_thread['views'] = my_number_format($similar_thread['views']);
			eval("\$similarthreadbits .= \"".$templates->get("showthread_similarthreads_bit")."\";");
		}
		if($count)
		{
			eval("\$similarthreads = \"".$templates->get("showthread_similarthreads")."\";");
		}
	}

	// Decide whether or not to show quick reply.
	$quickreply = '';
	if($forumpermissions['canpostreplys'] != 0 && $mybb->user['suspendposting'] != 1 && ($thread['closed'] != 1 || is_moderator($fid, "canpostclosedthreads")) && $mybb->settings['quickreply'] != 0 && $mybb->user['showquickreply'] != '0' && $forum['open'] != 0 && ($thread['uid'] == $mybb->user['uid'] || $forumpermissions['canonlyreplyownthreads'] != 1))
	{
		$query = $db->simple_select("posts", "pid", "tid='{$tid}'", array("order_by" => "pid", "order_dir" => "desc", "limit" => 1));
		$last_pid = $db->fetch_field($query, "pid");

		// Show captcha image for guests if enabled
		$captcha = '';
		if($mybb->settings['captchaimage'] && !$mybb->user['uid'])
		{
			require_once MYBB_ROOT.'inc/class_captcha.php';
			$post_captcha = new captcha(true, "post_captcha");

			if($post_captcha->html)
			{
				$captcha = $post_captcha->html;
			}
		}

		$postoptionschecked = array('signature' => '', 'emailnotify' => '');
		if($mybb->user['signature'])
		{
			$postoptionschecked['signature'] = 'checked="checked"';
		}

		// Hide signature option if no permission
		$option_signature = '';
		if($mybb->usergroup['canusesig'] && !$mybb->user['suspendsignature'])
		{
			eval("\$option_signature = \"".$templates->get('showthread_quickreply_options_signature')."\";");
		}

		if(isset($mybb->user['emailnotify']) && $mybb->user['emailnotify'] == 1)
		{
			$postoptionschecked['emailnotify'] = 'checked="checked"';
		}

		$trow = alt_trow();
		if($thread['closed'] == 1)
		{
			$trow = 'trow_shaded';
		}

		$moderation_notice = '';
		if(!is_moderator($forum['fid'], "canapproveunapproveposts"))
		{
			if($forumpermissions['modposts'] == 1)
			{
				$moderation_text = $lang->moderation_forum_posts;
				eval('$moderation_notice = "'.$templates->get('global_moderation_notice').'";');
			}
			
			if($mybb->user['moderateposts'] == 1)
			{
				$moderation_text = $lang->moderation_user_posts;
				eval('$moderation_notice = "'.$templates->get('global_moderation_notice').'";');
			}
		}

	    $posthash = md5($mybb->user['uid'].random_str());
		eval("\$quickreply = \"".$templates->get("showthread_quickreply")."\";");
	}

	$moderationoptions = '';

	// If the user is a moderator, show the moderation tools.
	if($ismod)
	{
		$customthreadtools = $customposttools = $standardthreadtools = $standardposttools = '';

		$threadnotesbox = $viewnotes = '';
		if(!empty($thread['notes']))
		{
			$thread['notes'] = nl2br(htmlspecialchars_uni($thread['notes']));

			if(strlen($thread['notes']) > 200)
			{
				eval("\$viewnotes = \"".$templates->get("showthread_threadnotes_viewnotes")."\";");
				$thread['notes'] = my_substr($thread['notes'], 0, 200)."... {$viewnotes}";
			}

			eval("\$threadnotesbox = \"".$templates->get("showthread_threadnotes")."\";");
		}

		if(is_moderator($forum['fid'], "canusecustomtools") && (!empty($forum_stats[-1]['modtools']) || !empty($forum_stats[$forum['fid']]['modtools'])))
		{
			$gids = explode(',', $mybb->user['additionalgroups']);
			$gids[] = $mybb->user['usergroup'];
			$gids = array_filter(array_unique($gids));
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					foreach($gids as $gid)
					{
						$gid = (int)$gid;
						$gidswhere .= " OR ','||groups||',' LIKE '%,{$gid},%'";
					}
					$query = $db->simple_select("modtools", 'tid, name, type', "(','||forums||',' LIKE '%,$fid,%' OR ','||forums||',' LIKE '%,-1,%' OR forums='') AND (groups='' OR ','||groups||',' LIKE '%,-1,%'{$gidswhere})");
					break;
				default:
					foreach($gids as $gid)
					{
						$gid = (int)$gid;
						$gidswhere .= " OR CONCAT(',',groups,',') LIKE '%,{$gid},%'";
					}
					$query = $db->simple_select("modtools", 'tid, name, type', "(CONCAT(',',forums,',') LIKE '%,$fid,%' OR CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='') AND (groups='' OR CONCAT(',',groups,',') LIKE '%,-1,%'{$gidswhere})");
					break;
			}

			while($tool = $db->fetch_array($query))
			{
				if($tool['type'] == 'p')
				{
					eval("\$customposttools .= \"".$templates->get("showthread_inlinemoderation_custom_tool")."\";");
				}
				else
				{
					eval("\$customthreadtools .= \"".$templates->get("showthread_moderationoptions_custom_tool")."\";");
				}
			}

			// Build inline moderation dropdown
			if(!empty($customposttools))
			{
				eval("\$customposttools = \"".$templates->get("showthread_inlinemoderation_custom")."\";");
			}
		}

		$inlinemodsoftdelete = $inlinemodrestore = $inlinemoddelete = $inlinemodmanage = $inlinemodapprove = '';

		if(is_moderator($forum['fid'], "cansoftdeleteposts"))
		{
			eval("\$inlinemodsoftdelete = \"".$templates->get("showthread_inlinemoderation_softdelete")."\";");
		}

		if(is_moderator($forum['fid'], "canrestoreposts"))
		{
			eval("\$inlinemodrestore = \"".$templates->get("showthread_inlinemoderation_restore")."\";");
		}

		if(is_moderator($forum['fid'], "candeleteposts"))
		{
			eval("\$inlinemoddelete = \"".$templates->get("showthread_inlinemoderation_delete")."\";");
		}

		if(is_moderator($forum['fid'], "canmanagethreads"))
		{
			eval("\$inlinemodmanage = \"".$templates->get("showthread_inlinemoderation_manage")."\";");
		}

		if(is_moderator($forum['fid'], "canapproveunapproveposts"))
		{
			eval("\$inlinemodapprove = \"".$templates->get("showthread_inlinemoderation_approve")."\";");
		}

		if(!empty($inlinemodsoftdelete) || !empty($inlinemodrestore) || !empty($inlinemoddelete) || !empty($inlinemodmanage) || !empty($inlinemodapprove))
		{
			eval("\$standardposttools = \"".$templates->get("showthread_inlinemoderation_standard")."\";");
		}

		// Only show inline mod menu if there's options to show
		if(!empty($standardposttools) || !empty($customposttools))
		{
			eval("\$inlinemod = \"".$templates->get("showthread_inlinemoderation")."\";");
		}

		// Build thread moderation dropdown
		if(!empty($customthreadtools))
		{
			eval("\$customthreadtools = \"".$templates->get("showthread_moderationoptions_custom")."\";");
		}

		$openclosethread = $stickunstickthread = $deletethread = $threadnotes = $managethread = $adminpolloptions = $approveunapprovethread = $softdeletethread = '';

		if(is_moderator($forum['fid'], "canopenclosethreads"))
		{
			eval("\$openclosethread = \"".$templates->get("showthread_moderationoptions_openclose")."\";");
		}

		if(is_moderator($forum['fid'], "canstickunstickthreads"))
		{
			eval("\$stickunstickthread = \"".$templates->get("showthread_moderationoptions_stickunstick")."\";");
		}

		if(is_moderator($forum['fid'], "candeletethreads"))
		{
			eval("\$deletethread = \"".$templates->get("showthread_moderationoptions_delete")."\";");
		}

		if(is_moderator($forum['fid'], "canmanagethreads"))
		{
			eval("\$threadnotes = \"".$templates->get("showthread_moderationoptions_threadnotes")."\";");
			eval("\$managethread = \"".$templates->get("showthread_moderationoptions_manage")."\";");
		}

		if($pollbox && is_moderator($forum['fid'], "canmanagepolls"))
		{
			eval("\$adminpolloptions = \"".$templates->get("showthread_moderationoptions_deletepoll")."\";");
		}

		if(is_moderator($forum['fid'], "canapproveunapprovethreads"))
		{
			if($thread['visible'] == 0)
			{
				$approveunapprovethread = "<option value=\"approvethread\">".$lang->approve_thread."</option>";
			}
			else
			{
				$approveunapprovethread = "<option value=\"unapprovethread\">".$lang->unapprove_thread."</option>";
			}
		}

		if(is_moderator($forum['fid'], "cansoftdeletethreads") && $thread['visible'] != -1)
		{
			eval("\$softdeletethread = \"".$templates->get("showthread_moderationoptions_softdelete")."\";");
		}
		elseif(is_moderator($forum['fid'], "canrestorethreads") && $thread['visible'] == -1)
		{
			eval("\$softdeletethread = \"".$templates->get("showthread_moderationoptions_restore")."\";");
		}

		if(!empty($openclosethread) || !empty($stickunstickthread) || !empty($deletethread) || !empty($managethread) || !empty($adminpolloptions) || !empty($approveunapprovethread) || !empty($softdeletethread))
		{
			eval("\$standardthreadtools = \"".$templates->get("showthread_moderationoptions_standard")."\";");
		}

		// Only show mod menu if there's any options to show
		if(!empty($standardthreadtools) || !empty($customthreadtools))
		{
			eval("\$moderationoptions = \"".$templates->get("showthread_moderationoptions")."\";");
		}
	}

	// Display 'send thread' link if permissions allow
	$sendthread = '';
	if($mybb->usergroup['cansendemail'] == 1)
	{
		eval("\$sendthread = \"".$templates->get("showthread_send_thread")."\";");
	}

	// Display 'add poll' link to thread creator (or mods) if thread doesn't have a poll already
	$addpoll = '';
	$time = TIME_NOW;
	if(!$thread['poll'] && ($thread['uid'] == $mybb->user['uid'] || $ismod == true) && $forumpermissions['canpostpolls'] == 1 && $forum['open'] != 0 && $thread['closed'] != 1 && ($ismod == true || $thread['dateline'] > ($time-($mybb->settings['polltimelimit']*60*60)) || $mybb->settings['polltimelimit'] == 0))
	{
		eval("\$addpoll = \"".$templates->get("showthread_add_poll")."\";");
	}

	// Subscription status
	$add_remove_subscription = 'add';
	$add_remove_subscription_text = $lang->subscribe_thread;

	if($mybb->user['uid'])
	{
		$query = $db->simple_select("threadsubscriptions", "tid", "tid='".(int)$tid."' AND uid='".(int)$mybb->user['uid']."'", array('limit' => 1));

		if($db->fetch_field($query, 'tid'))
		{
			$add_remove_subscription = 'remove';
			$add_remove_subscription_text = $lang->unsubscribe_thread;
		}
	}

	$classic_header = '';
	if($mybb->settings['postlayout'] == "classic")
	{
		eval("\$classic_header = \"".$templates->get("showthread_classic_header")."\";");
	}

	// Get users viewing this thread
	if($mybb->settings['browsingthisthread'] != 0)
	{
		$timecut = TIME_NOW - $mybb->settings['wolcutoff'];

		$comma = '';
		$guestcount = 0;
		$membercount = 0;
		$inviscount = 0;
		$onlinemembers = '';
		$doneusers = array();

		$query = $db->query("
			SELECT s.ip, s.uid, s.time, u.username, u.invisible, u.usergroup, u.displaygroup
			FROM ".TABLE_PREFIX."sessions s
			LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
			WHERE s.time > '$timecut' AND location2='$tid' AND nopermission != 1
			ORDER BY u.username ASC, s.time DESC
		");

		while($user = $db->fetch_array($query))
		{
			if($user['uid'] == 0)
			{
				++$guestcount;
			}
			else if(empty($doneusers[$user['uid']]) || $doneusers[$user['uid']] < $user['time'])
			{
				++$membercount;
				$doneusers[$user['uid']] = $user['time'];

				$invisiblemark = '';
				if($user['invisible'] == 1)
				{
					$invisiblemark = "*";
					++$inviscount;
				}

				if($user['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $user['uid'] == $mybb->user['uid'])
				{
					$user['profilelink'] = get_profile_link($user['uid']);
					$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
					$user['reading'] = my_date($mybb->settings['timeformat'], $user['time']);

					eval("\$onlinemembers .= \"".$templates->get("showthread_usersbrowsing_user", 1, 0)."\";");
					$comma = $lang->comma;
				}
			}
		}

		$guestsonline = '';
		if($guestcount)
		{
			$guestsonline = $lang->sprintf($lang->users_browsing_thread_guests, $guestcount);
		}

		$invisonline = '';
		if($mybb->user['invisible'] == 1)
		{
			// the user was counted as invisible user --> correct the inviscount
			$inviscount -= 1;
		}
		if($inviscount && $mybb->usergroup['canviewwolinvis'] != 1)
		{
			$invisonline = $lang->sprintf($lang->users_browsing_forum_invis, $inviscount);
		}

		$onlinesep = '';
		if($invisonline != '' && $onlinemembers)
		{
			$onlinesep = $lang->comma;
		}
		
		$onlinesep2 = '';
		if($invisonline != '' && $guestcount || $onlinemembers && $guestcount)
		{
			$onlinesep2 = $lang->comma;
		}

		eval("\$usersbrowsing = \"".$templates->get("showthread_usersbrowsing")."\";");
	}

	$plugins->run_hooks("showthread_end");

	eval("\$showthread = \"".$templates->get("showthread")."\";");
	output_page($showthread);
}

/**
 * Build a navigation tree for threaded display.
 *
 * @param unknown_type $replyto
 * @param unknown_type $indent
 * @return unknown
 */
function buildtree($replyto="0", $indent="0")
{
	global $tree, $mybb, $theme, $mybb, $pid, $tid, $templates, $parser;

	if($indent)
	{
		$indentsize = 13 * $indent;
	}
	else
	{
		$indentsize = 0;
	}

	++$indent;
	if(is_array($tree[$replyto]))
	{
		foreach($tree[$replyto] as $key => $post)
		{
			$postdate = my_date('relative', $post['dateline']);
			$post['subject'] = htmlspecialchars_uni($parser->parse_badwords($post['subject']));

			if(!$post['subject'])
			{
				$post['subject'] = "[".$lang->no_subject."]";
			}

			$post['profilelink'] = build_profile_link($post['username'], $post['uid']);

			if($mybb->input['pid'] == $post['pid'])
			{
				eval("\$posts .= \"".$templates->get("showthread_threaded_bitactive")."\";");
			}
			else
			{
				eval("\$posts .= \"".$templates->get("showthread_threaded_bit")."\";");
			}

			if($tree[$post['pid']])
			{
				$posts .= buildtree($post['pid'], $indent);
			}
		}
		--$indent;
	}
	return $posts;
}
