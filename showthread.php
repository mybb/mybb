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

$templatelist = "showthread,postbit,postbit_author_user,postbit_author_guest,showthread_newthread,showthread_newreply,showthread_newreply_closed,postbit_avatar,postbit_find,postbit_pm,postbit_www,postbit_email,postbit_edit,postbit_quote,postbit_report";
$templatelist .= ",multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start,showthread_inlinemoderation_softdelete,showthread_poll_editpoll";
$templatelist .= ",postbit_editedby,showthread_similarthreads,showthread_similarthreads_bit,postbit_iplogged_show,postbit_iplogged_hiden,postbit_profilefield,showthread_quickreply,showthread_printthread,showthread_add_poll,showthread_send_thread,showthread_inlinemoderation_restore";
$templatelist .= ",forumjump_advanced,forumjump_special,forumjump_bit,postbit_attachments,postbit_attachments_attachment,postbit_attachments_thumbnails,postbit_attachments_images_image,postbit_attachments_images,showthread_quickreply_options_stick,postbit_status";
$templatelist .= ",postbit_inlinecheck,showthread_inlinemoderation,postbit_attachments_thumbnails_thumbnail,postbit_ignored,postbit_multiquote,showthread_moderationoptions_custom_tool,showthread_moderationoptions_custom,showthread_inlinemoderation_custom_tool";
$templatelist .= ",showthread_usersbrowsing,showthread_usersbrowsing_user,showthread_poll_option,showthread_poll,showthread_quickreply_options_signature,showthread_threaded_bitactive,showthread_threaded_bit,postbit_attachments_attachment_unapproved";
$templatelist .= ",showthread_moderationoptions_openclose,showthread_moderationoptions_stickunstick,showthread_moderationoptions_delete,showthread_moderationoptions_threadnotes,showthread_moderationoptions_manage,showthread_moderationoptions_deletepoll";
$templatelist .= ",postbit_userstar,postbit_reputation_formatted_link,postbit_warninglevel_formatted,postbit_quickrestore,forumdisplay_password,forumdisplay_password_wrongpass,postbit_purgespammer,showthread_inlinemoderation_approve,forumdisplay_thread_icon";
$templatelist .= ",showthread_moderationoptions_softdelete,showthread_moderationoptions_restore,post_captcha,post_captcha_recaptcha_invisible,post_captcha_nocaptcha,post_captcha_hcaptcha_invisible,post_captcha_hcaptcha,showthread_moderationoptions,showthread_inlinemoderation_standard,showthread_inlinemoderation_manage";
$templatelist .= ",showthread_ratethread,postbit_posturl,postbit_icon,postbit_editedby_editreason,attachment_icon,global_moderation_notice,showthread_poll_option_multiple,postbit_gotopost,postbit_rep_button,postbit_warninglevel,showthread_threadnoteslink";
$templatelist .= ",showthread_moderationoptions_approve,showthread_moderationoptions_unapprove,showthread_inlinemoderation_delete,showthread_moderationoptions_standard,showthread_quickreply_options_close,showthread_inlinemoderation_custom,showthread_search";
$templatelist .= ",postbit_profilefield_multiselect_value,postbit_profilefield_multiselect,showthread_subscription,postbit_deleted_member,postbit_away,postbit_warn,postbit_classic,postbit_reputation,postbit_deleted,postbit_offline,postbit_online,postbit_signature";
$templatelist .= ",postbit_editreason,postbit_quickdelete,showthread_threadnotes_viewnotes,showthread_threadedbox,showthread_poll_resultbit,showthread_poll_results,showthread_threadnotes,showthread_classic_header,showthread_poll_undovote,postbit_groupimage";

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
	// See if we already have the post information
	if(isset($style) && $style['pid'] == $mybb->get_input('pid', MyBB::INPUT_INT) && $style['tid'])
	{
		$mybb->input['tid'] = $style['tid'];
		unset($style['tid']); // Stop the thread caching code from being tricked
	}
	else
	{
		$options = array(
			"limit" => 1
		);
		$query = $db->simple_select("posts", "fid,tid,visible", "pid=".$mybb->get_input('pid', MyBB::INPUT_INT), $options);
		$post = $db->fetch_array($query);

		if(
			empty($post) ||
			(
				$post['visible'] == 0 && !(
					is_moderator($post['fid'], 'canviewunapprove') ||
					($mybb->user['uid'] && $post['uid'] == $mybb->user['uid'] && $mybb->settings['showownunapproved'])
				)
			) ||
			($post['visible'] == -1 && !is_moderator($post['fid'], 'canviewdeleted'))
		)
		{
			// Post does not exist --> show corresponding error
			error($lang->error_invalidpost);
		}

		$mybb->input['tid'] = $post['tid'];
	}
}

// Get the thread details from the database.
$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));

if(!$thread || $thread['moved'] != 0)
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
		$thread['threadprefix'] = $threadprefix['prefix'].'&nbsp;';
		$thread['displayprefix'] = $threadprefix['displaystyle'].'&nbsp;';
	}
}

$thread['reply_subject'] = $parser->parse_badwords($thread['subject']);
// Subject too long? Shorten it to avoid error message
if(my_strlen($thread['reply_subject']) > 85)
{
	$thread['reply_subject'] = my_substr($thread['reply_subject'], 0, 82).'...';
}

$tid = $thread['tid'];
$fid = $thread['fid'];

if(!$thread['username'])
{
	$thread['username'] = $lang->guest;
}

$forumpermissions = forum_permissions($thread['fid']);

// Set here to fetch only approved/deleted posts (and then below for a moderator we change this).
$visible_states = array("1");

if($forumpermissions['canviewdeletionnotice'] != 0)
{
	$visible_states[] = "-1";
}

// Is the currently logged in user a moderator of this forum?
if(is_moderator($fid))
{
	// Determine this user's mod permissions
	$modpermissions = [];
	if(is_moderator($fid))
	{
		$modpermissions = [
			'ismod' => true
		];
	}

	$permissionsToCheck = [
		'cansoftdeleteposts',
		'canrestoreposts',
		'candeleteposts',
		'canapproveunapproveposts',
		'canviewdeleted',
		'canviewunapprove',
		'canusecustomtools',
		'canopenclosethreads',
		'canstickunstickthreads',
		'cansoftdeletethreads',
		'canrestorethreads',
		'candeletethreads',
		'canmanagethreads',
		'canmanagepolls',
		'canapproveunapprovethreads'
	];

	foreach($permissionsToCheck as $permission)
	{
		$modpermissions[$permission] = is_moderator($fid, $permission);
	}

	if(is_moderator($fid, "canviewdeleted") == true)
	{
		$visible_states[] = "-1";
	}
	if(is_moderator($fid, "canviewunapprove") == true)
	{
		$visible_states[] = "0";
	}
}
else
{
	$modpermissions['ismod'] = false;
}

$visible_condition = "visible IN (".implode(',', array_unique($visible_states)).")";

// Allow viewing own unapproved threads for logged in users
if($mybb->user['uid'] && $mybb->settings['showownunapproved'])
{
	$own_unapproved = ' AND (%1$s'.$visible_condition.' OR (%1$svisible=0 AND %1$suid='.(int)$mybb->user['uid'].'))';

	$visibleonly = sprintf($own_unapproved, null);
	$visibleonly_p = sprintf($own_unapproved, 'p.');
	$visibleonly_p_t = sprintf($own_unapproved, 'p.').sprintf($own_unapproved, 't.');
}
else
{
	$visibleonly = " AND ".$visible_condition;
	$visibleonly_p = " AND p.".$visible_condition;
	$visibleonly_p_t = "AND p.".$visible_condition." AND t.".$visible_condition;
}

// Make sure we are looking at a real thread here.
if(($thread['visible'] != 1 && $modpermissions['ismod'] == false) || ($thread['visible'] == 0 && !is_moderator($fid, "canviewunapprove")) || ($thread['visible'] == -1 && !is_moderator($fid, "canviewdeleted")))
{
	// Allow viewing own unapproved thread
	if (!($mybb->user['uid'] && $mybb->settings['showownunapproved'] && $thread['visible'] == 0 && ($thread['uid'] == $mybb->user['uid'])))
	{
		error($lang->error_invalidthread);
	}
}

// Does the user have permission to view this thread?
if($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
{
	error_no_permission();
}

if(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
{
	error_no_permission();
}

// Does the thread belong to a valid forum?
$forum = get_forum($fid);
if(!$forum || $forum['type'] != "f")
{
	error($lang->error_invalidforum);
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

		$read_cutoff = TIME_NOW - $mybb->settings['threadreadcut'] * 60 * 60 * 24;
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
		$cutoff = TIME_NOW - $mybb->settings['threadreadcut'] * 60 * 60 * 24;
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
		"order_by" => "dateline, pid",
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
	if($thread['moved'] != 0)
	{
		$query = $db->query("
			SELECT p.pid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON(p.tid=t.tid)
			WHERE t.fid='".$thread['fid']."' AND t.moved='0' {$visibleonly_p_t}
			ORDER BY p.dateline DESC, p.pid DESC
			LIMIT 1
		");
		$pid = $db->fetch_field($query, "pid");
	}
	else
	{
		$options = array(
			'order_by' => 'dateline DESC, pid DESC',
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
	$query = $db->simple_select('threads', '*', "fid={$thread['fid']} AND lastpost > {$thread['lastpost']} {$visibleonly} AND moved = 0", $options);
	$nextthread = $db->fetch_array($query);

	// Are there actually next newest posts?
	if(!$nextthread['tid'])
	{
		error($lang->error_nonextnewest);
	}
	$options = array(
		"limit_start" => 0,
		"limit" => 1,
		"order_by" => "dateline DESC, pid DESC",
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
	$query = $db->simple_select("threads", "*", "fid=".$thread['fid']." AND lastpost < ".$thread['lastpost']." {$visibleonly} AND moved = '0'", $options);
	$nextthread = $db->fetch_array($query);

	// Are there actually next oldest posts?
	if(!$nextthread['tid'])
	{
		error($lang->error_nonextoldest);
	}
	$options = array(
		"limit_start" => 0,
		"limit" => 1,
		"order_by" => "dateline DESC, pid DESC",
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

	$query = $db->simple_select("forums", "threads, unapprovedthreads, deletedthreads", "fid = '{$fid}'", array('limit' => 1));
	$forum_threads = $db->fetch_array($query);
	$threadcount = $forum_threads['threads'];


	if(is_moderator($fid, "canviewdeleted") == true || is_moderator($fid, "canviewunapprove") == true)
	{
		if(is_moderator($fid, "canviewdeleted") == true)
		{
			$threadcount += $forum_threads['deletedthreads'];
		}
		if(is_moderator($fid, "canviewunapprove") == true)
		{
			$threadcount += $forum_threads['unapprovedthreads'];
		}
	}
	elseif($forumpermissions['canviewdeletionnotice'] != 0)
	{
		$threadcount += $forum_threads['deletedthreads'];
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
	$thread_page = ceil(($thread_position / $mybb->settings['threadsperpage']));

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
		$poll['timeout'] = $poll['timeout'] * 60 * 60 * 24;
		$expiretime = $poll['dateline'] + $poll['timeout'];
		$now = TIME_NOW;

		// If the poll or the thread is closed or if the poll is expired, show the results.
		if($poll['closed'] == 1 || $thread['closed'] == 1 || ($expiretime < $now && $poll['timeout'] > 0) || $forumpermissions['canvotepolls'] != 1)
		{
			$poll['showresults'] = 1;
		}

		if($forumpermissions['canvotepolls'] != 1)
		{
			$poll['nopermission'] = 1;
		}

		// Check if the user has voted before...
		if($mybb->user['uid'])
		{
			$user_check = "uid='{$mybb->user['uid']}'";
		}
		else
		{
			$user_check = "uid='0' AND ipaddress=".$db->escape_binary($session->packedip);
		}

		$query = $db->simple_select("pollvotes", "*", "{$user_check} AND pid='".$poll['pid']."'");
		while($votecheck = $db->fetch_array($query))
		{
			$poll['alreadyvoted'] = 1;
			$votedfor[$votecheck['voteoption']] = 1;
		}

		$optionsarray = explode("||~|~||", $poll['options']);
		$votesarray = explode("||~|~||", $poll['votes']);

		$poll['polloptions'] = [];
		$poll['totalvotes'] = 0;
		$poll['totvotes'] = 0;

		for($i = 1; $i <= $poll['numoptions']; ++$i)
		{
			$poll['totvotes'] = $poll['totvotes'] + $votesarray[$i - 1];
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

			$option['option'] = $parser->parse_message($optionsarray[$i - 1], $parser_options);
			$option['votes'] = $votesarray[$i - 1];
			$poll['totalvotes'] += $option['votes'];
			$option['number'] = $i;

			// Mark the option the user voted for.
			if(!empty($votedfor[$option['number']]))
			{
				$option['row'] = "trow2";
				$option['votestar'] = "*";
			}
			else
			{
				$option['row'] = "trow1";
				$option['votestar'] = "";
			}

			// If the user already voted or if the results need to be shown, do so; else show voting screen.
			if(isset($poll['alreadyvoted']) || isset($poll['showresults']) || isset($poll['nopermission']))
			{
				if((int)$option['votes'] == "0")
				{
					$option['percent'] = "0";
				}
				else
				{
					$option['percent'] = number_format($option['votes'] / $poll['totvotes'] * 100, 2);
				}

				$option['imagewidth'] = round($option['percent']);
			}

			$poll['polloptions'][] = $option;
		}

		// If there are any votes at all, all votes together will be 100%; if there are no votes, all votes together will be 0%.
		if($poll['totvotes'])
		{
			$poll['totpercent'] = "100%";
		}
		else
		{
			$poll['totpercent'] = "0%";
		}

		// Decide what poll status to show depending on the status of the poll and whether or not the user voted already.
		if(isset($poll['alreadyvoted']) || isset($poll['showresults']) || isset($poll['nopermission']))
		{
			$plugins->run_hooks("showthread_poll_results");
		}
		else
		{
			if($poll['timeout'] != 0)
			{
				$poll['dateformat'] = my_date($mybb->settings['dateformat']);
				$poll['expiretime'] = $expiretime;
			}

			$plugins->run_hooks("showthread_poll");
		}
	}

	// Create the forum jump dropdown box.
	if($mybb->settings['enableforumjump'] != 0)
	{
		$forumjump = build_forum_jump("", $fid, 1);
	}

	// Fetch some links
	$thread['next_oldest_link'] = get_thread_link($tid, 0, "nextoldest");
	$thread['next_newest_link'] = get_thread_link($tid, 0, "nextnewest");

	// Mark this thread as read
	mark_thread_read($tid, $fid);

	// Show the appropriate reply button if this thread is open or closed
	$thread['canfullreply'] = $thread['canmodreply'] = false;
	if($forumpermissions['canpostreplys'] != 0 && $mybb->user['suspendposting'] != 1 && $thread['closed'] != 1 && ($thread['uid'] == $mybb->user['uid'] || $forumpermissions['canonlyreplyownthreads'] != 1))
	{
		$thread['canfullreply'] = true;
	}
	else if($thread['closed'] == 1 && is_moderator($fid, "canpostclosedthreads"))
	{
		$thread['canfullreply'] = false;
		$thread['canmodreply'] = true;
	}

	// Create the admin tools dropdown box.
	if($modpermissions['ismod'] == true)
	{
		$thread['inlinecount'] = "0";
		$inlinecookie = "inlinemod_thread".$tid;

		$plugins->run_hooks("showthread_ismod");
	}

	// Increment the thread view.
	$count_view = true; // By default, we count the view. Only if one of the below conditions is met do we not count it.
	if($mybb->settings['threadviewcountexcludespiders'] == 1 && $session->is_spider == true)
    {
		$count_view = false;
	}
	if($mybb->settings['threadviewcountexcludeguests'] == 1 && $mybb->user['uid'] == 0)
    {
		$count_view = false;
	}
	if($mybb->settings['threadviewcountexcludethreadauthor'] == 1 && $mybb->user['uid'] == $thread['uid'])
	{
		$count_view = false;
	}
	
	if($count_view == true)
	{
		if($mybb->settings['delayedthreadviews'] == 1)
		{
			$db->shutdown_query("INSERT INTO ".TABLE_PREFIX."threadviews (tid) VALUES('{$tid}')");
		}
		else
		{
			$db->shutdown_query("UPDATE ".TABLE_PREFIX."threads SET views=views+1 WHERE tid='{$tid}'");
		}
		++$thread['views'];		
	}

	// Work out the thread rating for this thread.
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
			$thread['averagerating'] = (float)round($thread['totalratings'] / $thread['numratings'], 2);
			$thread['width'] = (int)round($thread['averagerating']) * 20;
			$thread['numratings'] = (int)$thread['numratings'];
		}

		if($thread['numratings'])
		{
			// At least >someone< has rated this thread, was it me?
			// Check if we have already voted on this thread - it won't show hover effect then.
			$query = $db->simple_select("threadratings", "uid", "tid='{$tid}' AND uid='{$mybb->user['uid']}'");
			$rated = $db->fetch_field($query, 'uid');
		}

		$thread['not_rated'] = '';
		if(!$rated)
		{
			$thread['not_rated'] = ' star_rating_notrated';
		}
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
	$thread['showthreaded'] = false;
	if($mybb->get_input('mode') == 'threaded')
	{
		$thread['showthreaded'] = true;
		$isfirst = 1;

		// Are we linked to a specific pid?
		if($mybb->input['pid'])
		{
			$where = "AND p.pid='".$mybb->input['pid']."'";
		}
		else
		{
			$where = " ORDER BY dateline, pid LIMIT 0, 1";
		}

		$query = $db->query("
			SELECT u.*, u.username AS userusername, p.*, f.*, r.reporters, eu.username AS editusername
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."reportedcontent r ON (r.id=p.pid AND r.type='post' AND r.reportstatus != 1)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."users eu ON (eu.uid=p.edituid)
			WHERE p.tid='$tid' $visibleonly_p $where
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
            $visibleonly_p
			ORDER BY p.dateline, p.pid
		");
		if(!is_array($postsdone))
		{
			$postsdone = array();
		}
		while($post = $db->fetch_array($query))
		{
			if(empty($postsdone[$post['pid']]))
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
		$plugins->run_hooks("showthread_threaded");
	}
	else
	{
		// Linear display
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
			if(
				empty($post) ||
				(
					$post['visible'] == 0 && !(
						is_moderator($post['fid'], 'canviewunapprove') ||
						($mybb->user['uid'] && $post['uid'] == $mybb->user['uid'] && $mybb->settings['showownunapproved'])
					)
				) ||
				($post['visible'] == -1 && !is_moderator($post['fid'], 'canviewdeleted') && $forumpermissions['canviewdeletionnotice'] == 0)
			)
			{
				$footer .= '<script type="text/javascript">$(function() { $.jGrowl(\''.$lang->error_invalidpost.'\', {theme: \'jgrowl_error\'}); });</script>';
			}
			else
			{
				$query = $db->query("
                    SELECT COUNT(p.dateline) AS count
                    FROM ".TABLE_PREFIX."posts p
                    WHERE p.tid = '{$tid}'
                    AND p.dateline <= '{$post['dateline']}'
                    {$visibleonly_p}
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

		// Recount replies if user is a moderator or can see the deletion notice to take into account unapproved/deleted posts.
		if($visible_states != array("1"))
		{
			$cached_replies = $thread['replies']+$thread['unapprovedposts']+$thread['deletedposts'];

			$query = $db->simple_select("posts p", "COUNT(*) AS replies", "p.tid='$tid' $visibleonly_p");
			$thread['replies'] = $db->fetch_field($query, 'replies')-1;

			if(in_array('-1', $visible_states) && in_array('0', $visible_states))
			{
				// The counters are wrong? Rebuild them
				// This doesn't cover all cases however it is a good addition to the manual rebuild function
				if($thread['replies'] != $cached_replies)
				{
					require_once MYBB_ROOT."/inc/functions_rebuild.php";
					rebuild_thread_counters($thread['tid']);
				}
			}
		}

		$postcount = (int)$thread['replies'] + 1;
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
			$start = ($page - 1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$upper = $start + $perpage;

		// Work out if we have terms to highlight
		$highlight = '';
		$threadmode = '';
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
		$pids = '';
		$comma = '';
		$query = $db->simple_select("posts p", "p.pid", "p.tid='$tid' $visibleonly_p", array('order_by' => 'p.dateline, p.pid', 'limit_start' => $start, 'limit' => $perpage));
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
			SELECT u.*, u.username AS userusername, p.*, f.*, r.reporters, eu.username AS editusername
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."reportedcontent r ON (r.id=p.pid AND r.type='post' AND r.reportstatus != 1)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."users eu ON (eu.uid=p.edituid)
			WHERE $pids
			ORDER BY p.dateline, p.pid
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
                    WHERE t.fid='{$thread['fid']}' AND t.tid!='{$thread['tid']}' AND t.visible='1' AND t.moved='0' AND t.subject @@ query{$own_perm}
                    ORDER BY t.lastpost DESC
                    OFFSET 0 LIMIT {$mybb->settings['similarlimit']}
                ");
				break;
			default:
				$query = $db->query("
                    SELECT t.*, t.username AS threadusername, u.username, MATCH (t.subject) AGAINST ('".$db->escape_string($thread['subject'])."') AS relevance
                    FROM ".TABLE_PREFIX."threads t
                    LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
                    WHERE t.fid='{$thread['fid']}' AND t.tid!='{$thread['tid']}' AND t.visible='1' AND t.moved='0'{$own_perm} AND MATCH (t.subject) AGAINST ('".$db->escape_string($thread['subject'])."') >= '{$mybb->settings['similarityrating']}'
                    ORDER BY t.lastpost DESC
                    LIMIT 0, {$mybb->settings['similarlimit']}
                ");
		}

		$thread['similarthreads'] = 0;
		$similarthreads = [];
		$icon_cache = $cache->read("posticons");
		while($similar_thread = $db->fetch_array($query))
		{
			++$thread['similarthreads'];
			$similar_thread['hasicon'] = false;
			if($similar_thread['icon'] > 0 && $icon_cache[$similar_thread['icon']])
			{
				$similar_thread['hasicon'] = true;
				$icon = $icon_cache[$similar_thread['icon']];
				$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
				$similar_thread['icon_path'] = $icon['path'];
				$similar_thread['icon_name'] = $icon['name'];
			}

			if(!$similar_thread['username'])
			{
				$similar_thread['username'] = $similar_thread['profilelink'] = $similar_thread['threadusername'];
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
			$similar_thread['threadlink'] = get_thread_link($similar_thread['tid']);
			$similar_thread['lastpostlink'] = get_thread_link($similar_thread['tid'], 0, "lastpost");

			$similar_thread['lastpostdate'] = my_date('relative', $similar_thread['lastpost']);
			$lastposter = $similar_thread['lastposter'];
			$lastposteruid = $similar_thread['lastposteruid'];

			// Don't link to guest's profiles (they have no profile).
			if($lastposteruid == 0)
			{
				$similar_thread['lastposterlink'] = $lastposter;
			}
			else
			{
				$similar_thread['lastposterlink'] = build_profile_link($lastposter, $lastposteruid);
			}

			$similar_thread['replies'] = my_number_format($similar_thread['replies']);
			$similar_thread['views'] = my_number_format($similar_thread['views']);

			$similarthreads[] = $similar_thread;
		}
	}

	// Decide whether or not to show quick reply.
	$thread['showquickreply'] = false;
	if($forumpermissions['canpostreplys'] != 0 && $mybb->user['suspendposting'] != 1 && ($thread['closed'] != 1 || is_moderator($fid, "canpostclosedthreads")) && $mybb->settings['quickreply'] != 0 && $mybb->user['showquickreply'] != '0' && $forum['open'] != 0 && ($thread['uid'] == $mybb->user['uid'] || $forumpermissions['canonlyreplyownthreads'] != 1))
	{
		$thread['showquickreply'] = true;
		$query = $db->simple_select("posts", "pid", "tid='{$tid}'", array("order_by" => "pid", "order_dir" => "desc", "limit" => 1));
		$thread['last_pid'] = $db->fetch_field($query, "pid");

		// Show captcha image for guests if enabled
		$captcha = '';
		if($mybb->settings['captchaimage'] && !$mybb->user['uid'])
		{
			require_once MYBB_ROOT.'inc/class_captcha.php';
			$post_captcha = new captcha(true, "post");

			if($post_captcha->html)
			{
				$captcha = $post_captcha->html;
			}
		}

		$thread['postoptions'] = array('signature' => false, 'disablesmilies' => false);
		if($mybb->user['signature'])
		{
			$thread['postoptions']['signature'] = true;
		}

		if($thread['closed'] == 1)
		{
			$thread['quickreplyrow'] = 'trow_shaded';
		}
		else
		{
			$thread['quickreplyrow'] = 'trow1';
		}

		$thread['showmodnotice'] = false;
		if(!is_moderator($forum['fid'], "canapproveunapproveposts"))
		{
			if($forumpermissions['modposts'] == 1)
			{
				$thread['showmodnotice'] = true;
				$thread['moderation_text'] = $lang->moderation_forum_posts;
			}

			if($mybb->user['moderateposts'] == 1)
			{
				$thread['showmodnotice'] = true;
				$thread['moderation_text'] = $lang->moderation_user_posts;
			}
		}

		$thread['posthash'] = md5($mybb->user['uid'].random_str());
		$thread['page'] = $page;
	}

	// If the user is a moderator, show the moderation tools.
	$thread['showmodoptions'] = false;
	if($modpermissions['ismod'])
	{
		$thread['customthreadtools'] = $thread['customposttools'] = [];

		if(is_moderator($forum['fid'], "canusecustomtools") && (!empty($forum_stats[-1]['modtools']) || !empty($forum_stats[$forum['fid']]['modtools'])))
		{
			$gids = explode(',', $mybb->user['additionalgroups']);
			$gids[] = $mybb->user['usergroup'];
			$gids = array_filter(array_unique($gids));
			$gidswhere = '';
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
						$gidswhere .= " OR CONCAT(',',`groups`,',') LIKE '%,{$gid},%'";
					}
					$query = $db->simple_select("modtools", 'tid, name, type', "(CONCAT(',',forums,',') LIKE '%,$fid,%' OR CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='') AND (`groups`='' OR CONCAT(',',`groups`,',') LIKE '%,-1,%'{$gidswhere})");
					break;
			}

			while($tool = $db->fetch_array($query))
			{
				if($tool['type'] == 'p')
				{
					$thread['customposttools'][] = $tool;
				}
				else
				{
					$thread['customthreadtools'][] = $tool;
				}
			}

			// Build inline moderation dropdown
			$thread['showcustomposttools'] = false;
			if(!empty($thread['customposttools']))
			{
				$thread['showcustomposttools'] = true;
			}
		}

		$thread['showstandardposttools'] = false;

		if($modpermissions['cansoftdeleteposts'] || $modpermissions['canrestoreposts'] || $modpermissions['candeleteposts'] || $modpermissions['canmanagethreads'] || $modpermissions['canapproveunapproveposts'])
		{
			$thread['showstandardposttools'] = true;
		}

		// Only show inline mod menu if there's options to show
		$thread['showinlinemodoptions'] = false;
		if($thread['showstandardposttools'] || $thread['showcustomposttools'])
		{
			$thread['showinlinemodoptions'] = true;
			$thread['showmodoptions'] = true;
		}

		// Build thread moderation dropdown
		$thread['showcustomthreadtools'] = false;
		if(!empty($thread['customthreadtools']))
		{
			$thread['showcustomthreadtools'] = true;
		}

		$thread['showstandardtools'] = false;
		if($modpermissions['canopenclosethreads'] || $modpermissions['canstickunstickthreads'] || $modpermissions['candeletethreads'] || $modpermissions['canmanagethreads'] || $modpermissions['canmanagepolls'] || $modpermissions['canapproveunapprovethreads'] || $modpermissions['cansoftdeletethreads'] || $modpermissions['canrestorethreads'])
		{
			$thread['showstandardtools'] = true;
		}

		// Only show mod menu if there's any options to show
		if($thread['showstandardtools'] || $thread['showcustomthreadtools'])
		{
			$thread['showmodoptions'] = true;
		}

		$thread['threadcount'] = $threadcount;
	}

	// Display 'add poll' link to thread creator (or mods) if thread doesn't have a poll already
	$thread['addpoll'] = false;
	$time = TIME_NOW;
	if(!$thread['poll'] && ($thread['uid'] == $mybb->user['uid'] || $modpermissions['ismod'] == true) && $forumpermissions['canpostpolls'] == 1 && $forum['open'] != 0 && $thread['closed'] != 1 && ($modpermissions['ismod'] == true || $thread['dateline'] > ($time - ($mybb->settings['polltimelimit'] * 60 * 60)) || $mybb->settings['polltimelimit'] == 0))
	{
		$thread['addpoll'] = true;
	}

	// Subscription status
	$thread['issubscribed'] = false;
	if($mybb->user['uid'])
	{
		$query = $db->simple_select("threadsubscriptions", "tid", "tid='".(int)$tid."' AND uid='".(int)$mybb->user['uid']."'", array('limit' => 1));

		if($db->num_rows($query) > 0)
		{
			$thread['issubscribed'] = true;
		}
	}

	// Get users viewing this thread
	if($mybb->settings['browsingthisthread'] != 0)
	{
		$timecut = TIME_NOW - $mybb->settings['wolcutoff'];

		$thread['guestcount'] = 0;
		$thread['membercount'] = 0;
		$thread['inviscount'] = 0;
		$thread['onlinemembers'] = 0;
		$onlinemembers = [];
		$doneusers = array();

		$query = $db->simple_select("sessions", "COUNT(DISTINCT ip) AS guestcount", "uid = 0 AND time > $timecut AND location2 = $tid AND nopermission != 1");
		$guestcount = $db->fetch_field($query, 'guestcount');

		$query = $db->query("
			SELECT
				s.ip, s.uid, s.time, u.username, u.invisible, u.usergroup, u.displaygroup
			FROM
				".TABLE_PREFIX."sessions s
				LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
			WHERE s.uid != 0 AND s.time > '$timecut' AND location2='$tid' AND nopermission != 1
			ORDER BY u.username ASC, s.time DESC
		");

		while($user = $db->fetch_array($query))
		{
			if($user['uid'] == 0)
			{
				++$thread['guestcount'];
			}
			else if(empty($doneusers[$user['uid']]) || $doneusers[$user['uid']] < $user['time'])
			{
				++$thread['membercount'];
				$doneusers[$user['uid']] = $user['time'];

				$user['invisiblemark'] = '';
				if($user['invisible'] == 1 && $mybb->usergroup['canbeinvisible'] == 1)
				{
					$user['invisiblemark'] = "*";
					++$thread['inviscount'];
				}

				if($user['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $user['uid'] == $mybb->user['uid'])
				{
					$user['profilelink'] = get_profile_link($user['uid']);
					$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
					$user['reading'] = my_date($mybb->settings['timeformat'], $user['time']);

					++$thread['onlinemembers'];
					$onlinemembers[] = $user;
				}
			}
		}

		if($mybb->user['invisible'] == 1)
		{
			// The user was counted as invisible user --> correct the inviscount
			$thread['inviscount'] -= 1;
		}
	}

	$thread_deleted = 0;
	if($thread['visible'] == -1)
	{
		$thread_deleted = 1;
	}

	$plugins->run_hooks("showthread_end");

	$thread['pid'] = $pid;

	output_page(\MyBB\template('showthread/showthread.twig', [
		'thread' => $thread,
		'forum' => $forum,
		'poll' => $poll,
		'forumpermissions' => $forumpermissions,
		'modpermissions' => $modpermissions,
		'multipage' => $multipage,
		'similarthreads' => $similarthreads,
		'posts' => $posts,
		'captcha' => $captcha,
		'onlinemembers' => $onlinemembers,
		'forumjump' => $forumjump,
		'threadedbits' => $threadedbits,
		'collapsedthead' => $collapsedthead,
		'collapsedimg' => $collapsedimg,
		'collapsed' => $collapsed,
		'thread_deleted' => $thread_deleted,
	]));
}

/**
 * Build a navigation tree for threaded display.
 *
 * @param int $replyto
 * @param int $indent
 * @return string
 */
function buildtree($replyto = 0, $indent = 0)
{
	global $tree, $mybb, $theme, $mybb, $parser, $lang;

	$indentsize = 13 * $indent;

	++$indent;
	$posts = [];
	$posttree = [];
	if(is_array($tree[$replyto]))
	{
		foreach($tree[$replyto] as $key => $post)
		{
			$post['postdate'] = my_date('relative', $post['dateline']);
			$post['subject'] = $parser->parse_badwords($post['subject']);

			if(!$post['subject'])
			{
				$post['subject'] = "[".$lang->no_subject."]";
			}

			$post['profilelink'] = build_profile_link($post['username'], $post['uid']);

			$post['bitactive'] = false;
			if($mybb->input['pid'] == $post['pid'])
			{
				$post['bitactive'] = true;
			}

			$post['indentsize'] = $indentsize;
			$posttree[] = $post;

			if(!empty($tree[$post['pid']]))
			{
				$posts = buildtree($post['pid'], $indent);
				$posttree = array_merge($posttree, $posts);
			}

		}
		--$indent;
	}

	return $posttree;
}
