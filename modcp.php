<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

define("IN_MYBB", 1);

$templatelist .= "modcp_reports,modcp_reports_report,modcp_reports_multipage,modcp_reports_allreport";
$templatelist .= ",modcp_reports_allnoreports,modcp_reports_noreports,modcp_banning,modcp_banning_banned";
$templatelist .= ",modcp_banning_multipage,modcp_banning_nobanned,modcp_banning_auser,modcp_banning_error";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/functions_modcp.php";
require_once MYBB_ROOT."inc/class_parser.php";

$parser = new postParser;

// Load global language phrases
$lang->load("modcp");

if($mybb->user['uid'] == 0 || $mybb->usergroup['canmodcp'] != "yes")
{
	error_no_permission();
}

$errors = '';

// SQL for fetching items only related to forums this user moderates
if($mybb->usergroup['issupermod'] != "yes")
{
	$query = $db->simple_select("moderators", "*", "uid='{$mybb->user['uid']}'");
	while($forum = $db->fetch_array($query))
	{
		$flist .= ",'{$forum['fid']}'";
	}
	if($flist)
	{
		$flist = " AND fid IN (0{$flist})";
	}
}
else
{
	$flist = "";
}

// Fetch the Mod CP menu
eval("\$modcp_nav = \"".$templates->get("modcp_nav")."\";");

$plugins->run_hooks("modcp_start");

// Make navigation
add_breadcrumb($lang->nav_modcp, "modcp.php");

if($mybb->input['action'] == "do_reports")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$flist = '';
	if($mybb->usergroup['issupermod'] != "yes")
	{
		$query = $db->simple_select("moderators", "*", "uid='{$mybb->user['uid']}'");
		while($forum = $db->fetch_array($query))
		{
			$flist .= ",'{$forum['fid']}'";
		}
	}
	
	if($flist)
	{
		$flist = " AND fid IN (0{$flist})";
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
	$rids = "'0','{$rids}'";

	$plugins->run_hooks("modcp_do_reports");

	$db->update_query("reportedposts", array('reportstatus' => 1), "rid IN ({$rids}){$flist}");
	$cache->update_reportedposts();
	redirect("modcp.php?action=reports", $lang->redirect_reportsmarked);
}

if($mybb->input['action'] == "reports")
{
	add_breadcrumb($lang->mcp_nav_reported_posts, "modcp.php?action=reports");

	if(!$mybb->settings['threadsperpage'])
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	// Figure out if we need to display multiple pages.
	$perpage = $mybb->settings['threadsperpage'];
	if($mybb->input['page'] != "last")
	{
		$page = intval($mybb->input['page']);
	}

	$query = $db->simple_select("reportedposts", "COUNT(rid) AS count", "reportstatus ='0'");
	$report_count = $db->fetch_field($query, "count");

	if($mybb->input['rid'])
	{
		$query = $db->simple_select("reportedposts", "COUNT(rid) AS count", "rid <= '".intval($mybb->input['rid'])."'");
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
	$postcount = intval($report_count)+1;
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

	$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=reports");
	if($postcount > $perpage)
	{
		eval("\$reportspages = \"".$templates->get("modcp_reports_multipage")."\";");
	}

	$query = $db->simple_select("forums", "fid, name");
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
		WHERE r.reportstatus='0'
		ORDER BY r.dateline DESC
		LIMIT {$start}, {$perpage}
	");
	while($report = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$report['postlink'] = get_post_link($report['pid'], $report['tid']);
		$report['threadlink'] = get_thread_link($report['tid']);
		$report['posterlink'] = get_profile_link($report['postuid']);
		$report['reporterlink'] = get_profile_link($report['uid']);
		$reportdate = my_date($mybb->settings['dateformat'], $report['dateline']);
		$reporttime = my_date($mybb->settings['timeformat'], $report['dateline']);
		$report['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($report['threadsubject']));
		eval("\$reports .= \"".$templates->get("modcp_reports_report")."\";");
	}
	if(!$reports)
	{
		eval("\$reports = \"".$templates->get("modcp_reports_noreports")."\";");
	}

	$plugins->run_hooks("modcp_reports");

	eval("\$reportedposts = \"".$templates->get("modcp_reports")."\";");
	output_page($reportedposts);
}

if($mybb->input['action'] == "allreports")
{
	add_breadcrumb($lang->nav_all_reported_posts, "modcp.php?action=allreports");
	
	if(!$mybb->settings['threadsperpage'])
	{
		$mybb->settings['threadsperpage'] = 20;
	}
	
	// Figure out if we need to display multiple pages.
	$perpage = $mybb->settings['threadsperpage'];
	if($mybb->input['page'] != "last")
	{
		$page = intval($mybb->input['page']);
	}
	
	$query = $db->simple_select("reportedposts", "COUNT(rid) AS count");
	$warnings = $db->fetch_field($query, "count");
	
	if($mybb->input['rid'])
	{
		$query = $db->simple_select("reportedposts", "COUNT(rid) AS count", "rid <= '".intval($mybb->input['rid'])."'");
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

	$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=allreports");
	if($postcount > $perpage)
	{
		eval("\$allreportspages = \"".$templates->get("modcp_reports_multipage")."\";");
	}

	$query = $db->simple_select("forums", "fid, name");
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
		ORDER BY r.dateline DESC
		LIMIT $start, $perpage
	");
	while($report = $db->fetch_array($query))
	{
		$report['postlink'] = get_post_link($report['pid'], $report['tid']);
		$report['threadlink'] = get_thread_link($report['tid']);
		$report['posterlink'] = get_profile_link($report['postuid']);
		$report['reporterlink'] = get_profile_link($report['uid']);

		$reportdate = my_date($mybb->settings['dateformat'], $report['dateline']);
		$reporttime = my_date($mybb->settings['timeformat'], $report['dateline']);
		
		if($report['reportstatus'] == 0)
		{
			$trow = "trow_shaded";
		}
		else
		{
			$trow = alt_trow();
		}
		
		$report['postusername'] = build_profile_link($report['postusername'], $report['postuid']);

		if($report['threadsubject'])
		{
			$report['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($report['threadsubject']));
			$report['threadsubject'] = "<a href=\"".get_thread_link($report['tid'])."\" target=\"_blank\">{$report['threadsubject']}</a>";
		}
		else
		{
			$report['threadsubject'] = $lang->na;
		}
		
		eval("\$allreports .= \"".$templates->get("modcp_reports_allreport")."\";");		
	}
	
	if(!$allreports)
	{
		eval("\$allreports = \"".$templates->get("modcp_reports_allnoreports")."\";");
	}

	$plugins->run_hooks("modcp_reports");

	eval("\$allreportedposts = \"".$templates->get("modcp_reports_allreports")."\";");
	output_page($allreportedposts);
}

if($mybb->input['action'] == "modlogs")
{
	add_breadcrumb($lang->mcp_nav_modlogs, "modcp.php?action=modlogs");

	$perpage = intval($mybb->input['perpage']);
	if(!$perpage)
	{
		$perpage = $mybb->settings['threadsperpage'];
	}

	$where = '';

	// Searching for entries by a particular user
	if($mybb->input['uid'])
	{
		$where .= " AND l.uid='".intval($mybb->input['uid'])."'";
	}

	// Searching for entries in a specific forum
	if($mybb->input['fid'])
	{
		$where .= " AND t.fid='".intval($mybb->input['fid'])."'";
	}

	// Order?
	switch($mybb->input['sortby'])
	{
		case "username":
			$sortby = "u.username";
			break;
		case "forum":
			$sortby = "f.name";
			break;
		case "thread":
			$sortby = "t.subject";
			break;
		default:
			$sortby = "l.dateline";
	}
	$order = $mybb->input['order'];
	if($order != "asc")
	{
		$order = "desc";
	}

	$query = $db->query("
		SELECT COUNT(l.dateline) AS count
		FROM ".TABLE_PREFIX."moderatorlog l 
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
		WHERE 1=1 {$where}
	");
	$rescount = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->input['page'] != "last")
	{
		$page = intval($mybb->input['page']);
	}

	$postcount = intval($rescount);
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

	$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=modlogs&amp;perpage=$perpage&amp;uid={$mybb->input['uid']}&amp;fid={$mybb->input['fid']}&amp;orderby=$mybb->input['sortby']&amp;order={$mybb->input['order']}");
	if($postcount > $perpage)
	{
		eval("\$resultspages = \"".$templates->get("modcp_modlogs_multipage")."\";");
	}
	$query = $db->query("
		SELECT l.*, u.username, t.subject AS tsubject, f.name AS fname, p.subject AS psubject
		FROM ".TABLE_PREFIX."moderatorlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=l.fid)
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=l.pid)
		{$squery}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($logitem = $db->fetch_array($query))
	{
		$logitem['dateline'] = date("jS M Y, G:i", $logitem['dateline']);
		$trow = alt_trow();
		$logitem['profilelink'] = build_profile_link($logitem['username'], $logitem['uid']);
		if($logitem['tsubject'])
		{
			$information = "<strong>{$lang->thread}</strong> <a href=\"".get_thread_link($logitem['tid'])."\" target=\"_blank\">".htmlspecialchars_uni($logitem['tsubject'])."</a><br />";
		}
		if($logitem['fname'])
		{
			$information .= "<strong>{$lang->forum}</strong> <a href=\"".get_forum_link($logitem['fid'])."\" target=\"_blank\">".htmlspecialchars_uni($logitem['fname'])."</a><br />";
		}
		if($logitem['psubject'])
		{
			$information .= "<strong>{$lang->post}</strong> <a href=\"".get_post_link($logitem['pid'])."#pid$logitem[pid]\">".htmlspecialchars_uni($logitem['psubject'])."</a>";
		}
		
		eval("\$results .= \"".$templates->get("modcp_modlogs_result")."\";");		
	}
	
	if(!$results)
	{
		eval("\$results = \"".$templates->get("modcp_modlogs_noresults")."\";");		
	}

	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = "selected=\"selected\"";
	$ordersel[$mybb->input['order']] = "selected=\"selected\"";
	$query = $db->query("
		SELECT DISTINCT l.uid, u.username
		FROM ".TABLE_PREFIX."moderatorlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (l.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$selected = '';
		if($mybb->input['uid'] == $user['uid']) $selected = "selected=\"selected\"";
		$user_options .= "<option value=\"{$user['uid']}\"{$selected}>{$user['username']}</option>\n";
	}

	$forum_select = build_forum_jump("", '', 1, '', 0, '', "fid");

	eval("\$modlogs = \"".$templates->get("modcp_modlogs")."\";");
	output_page($modlogs);	
}

if($mybb->input['action'] == "do_new_announcement")
{
}

if($mybb->input['action'] == "new_announcement")
{
}

if($mybb->input['action'] == "do_edit_announcement")
{
}

if($mybb->input['action'] == "edit_announcement")
{
}

if($mybb->input['action'] == "announcements")
{
}

if($mybb->input['action'] == "do_modqueue")
{
	require_once MYBB_ROOT."inc/class_moderation.php";
	$moderation = new Moderation;

	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	if(is_array($mybb->input['threads']))
	{
		// Fetch threads
		$query = $db->simple_select("threads", "tid", "tid IN (".implode(",", array_map("intval", array_keys($mybb->input['threads']))).") {$flist}");
		while($thread = $db->fetch_array($query))
		{
			$action = $mybb->input['threads'][$thread['tid']];
			if($action == "approve")
			{
				$threads_to_approve[] = $thread['tid'];
			}
			else if($action == "delete")
			{
				$moderation->delete_thread($thread['tid']);
			}
		}
		if(is_array($threads_to_approve))
		{
			$moderation->approve_threads($threads_to_approve);
		}
		redirect("modcp.php?action=modqueue", $lang->redirect_threadsmoderated);
	}

	else if(is_array($mybb->input['posts']))
	{
		// Fetch threads
		$query = $db->simple_select("posts", "pid", "pid IN (".implode(",", array_map("intval", array_keys($mybb->input['posts']))).") {$flist}");
		while($post = $db->fetch_array($query))
		{
			$action = $mybb->input['posts'][$post['tid']];
			if($action == "approve")
			{
				$posts_to_approve[] = $post['pid'];
			}
			else if($action == "delete")
			{
				$moderation->delete_post($post['pid']);
			}
		}
		if(is_array($posts_to_approve))
		{
			$moderation->approve_posts($posts_to_approve);
		}
		redirect("modcp.php?action=modqueue&type=posts", $lang->redirect_postsmoderated);
	}

	else if(is_array($mybb->input['attachments']))
	{
		redirect("modcp.php?action=modqueue&type=attachments", $lang->redirect_attachmentsmoderated);
	}
}

if($mybb->input['action'] == "modqueue")
{
	if($mybb->input['type'] == "threads" || !$mybb->input['type'])
	{
		$forum_cache = $cache->read("forums");

		$query = $db->simple_select("threads", "COUNT(tid) AS unapprovedthreads", "visible=0 {$flist}");
		$unapproved_threads = $db->fetch_field($query, "unapprovedthreads");

		// Figure out if we need to display multiple pages.
		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}

		$perpage = $mybb->settings['postsperpage'];
		$pages = $unapprovedthreads / $perpage;
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

		$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=modqueue&amp;type=threads");

		$query = $db->query("
			SELECT t.tid, t.dateline, t.fid, t.subject, p.message AS postmessage, u.username AS username, t.uid
			FROM ".TABLE_PREFIX."threads t
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=t.firstpost)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
			WHERE t.visible='0' {$flist}
			ORDER BY t.lastpost DESC
			LIMIT {$start}, {$perpage}
		");
		while($thread = $db->fetch_array($query))
		{
			$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
			$thread['threadlink'] = get_thread_link($thread['tid']);
			$thread['forumlink'] = get_forum_link($thread['fid']);
			$forum_name = $forum_cache[$thread['fid']]['name'];
			$threaddate = my_date($mybb->settings['dateformat'], $thread['dateline']);
			$threadtime = my_date($mybb->settings['timeformat'], $thread['dateline']);
			$profile_link = build_profile_link($thread['username'], $thread['uid']);
			$thread['postmessage'] = nl2br($thread['postmessage']);
			eval("\$threads .= \"".$templates->get("modcp_modqueue_threads_thread")."\";");
		}
		if(!$threads)
		{
			eval("\$threads = \"".$templates->get("modcp_modqueue_noresults")."\";");
		}
		eval("\$threadqueue = \"".$templates->get("modcp_modqueue_threads")."\";");
		output_page($threadqueue);	
	}

	if($mybb->input['type'] == "posts" || !$threadqueue)
	{
		$forum_cache = $cache->read("forums");

		$query = $db->query("
			SELECT COUNT(pid) AS unapprovedposts
			FROM  ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.visible='0' {$flist} AND t.firstpost != p.pid
		");
		$unapproved_posts = $db->fetch_field($query, "unapprovedposts");

		// Figure out if we need to display multiple pages.
		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}

		$perpage = $mybb->settings['postsperpage'];
		$pages = $unapprovedthreads / $perpage;
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

		$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=modqueue&amp;type=posts");

		$query = $db->query("
			SELECT p.pid, p.subject, p.message, t.subject AS threadsubject, t.tid, u.username AS username, p.uid
			FROM  ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE p.visible='0' {$flist} AND t.firstpost != p.pid
			ORDER BY p.dateline DESC
		");
		while($post = $db->fetch_array($query))
		{
			$post['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($post['threadsubject']));
			$post['threadlink'] = get_thread_link($post['tid']);
			$post['forumlink'] = get_forum_link($post['fid']);
			$forum_name = $forum_cache[$post['fid']]['name'];
			$postdate = my_date($mybb->settings['dateformat'], $post['dateline']);
			$posttime = my_date($mybb->settings['timeformat'], $post['dateline']);
			$profile_link = build_profile_link($post['username'], $post['uid']);
			$post['message'] = nl2br($post['message']);
			eval("\$posts .= \"".$templates->get("modcp_modqueue_posts_post")."\";");
		}
		if(!$posts)
		{
			eval("\$posts = \"".$templates->get("modcp_modqueue_noresults")."\";");
		}
		eval("\$postqueue = \"".$templates->get("modcp_modqueue_posts")."\";");
		output_page($postqueue);	
	}

	if($mybb->input['type'] == "attachments" || (!$threadqeue && !$postqueue))
	{
	}

	// Still nothing? All queues are empty! :-D
	if(!$threadqueue && !$postqueue && !$attachqueue)
	{
		eval("\$queue = \"".$templates->get("modcp_modqueue_empty")."\";");
		output_page($queue);	
	}
}

if($mybb->input['action'] == "do_editprofile")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$user = get_user($mybb->input['uid']);
	if(!$user['uid'])
	{
		error($lang->invalid_user);
	}

	// Check if the current user has permission to edit this user
	$user_permissions = user_permissions($user['uid']);

	// Current user is only a local moderator, cannot edit super mods or admins
	if($mybb->user['usergroup'] == 6 && ($user_permissions['issupermod'] == "yes" || $user_permissions['canadmincp'] == "yes"))
	{
		error_no_permission();
	}
	// Current user is a super mod or is an administrator and the user we are editing is a super admin, cannot edit admins
	else if($mybb->usergroup['issupermod'] == "yes" && $user_permissions['canadmincp'] == "yes" || (is_super_admin($user['uid']) && !is_super_admin($user['uid'])))
	{
		error_no_permission();
	}
	// Otherwise, free to edit

	// Set up user handler.
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler('update');

	// Set the data for the new user.
	$updated_user = array(
		"uid" => $mybb->input['uid'],
		"usertitle" => $mybb->input['usertitle'],
		"profile_fields" => $mybb->input['profile_fields'],
		"profile_fields_editable" => true,
		"website" => $mybb->input['website'],
		"icq" => $mybb->input['icq'],
		"aim" => $mybb->input['aim'],
		"yahoo" => $mybb->input['yahoo'],
		"msn" => $mybb->input['msn'],
		"signature" => $mybb->input['signature'],
	);

	$updated_user['birthday'] = array(
		"day" => $mybb->input['birthday_day'],
		"month" => $mybb->input['birthday_month'],
		"year" => $mybb->input['birthday_year']
	);

	if($mybb->input['usertitle'] != '')
	{
		$user['usertitle'] = $mybb->input['usertitle'];
	}
	else if($mybb->input['reverttitle'])
	{
		$user['usertitle'] = '';
	}

	if($mybb->input['remove_avatar'])
	{
		$user['avatarurl'] = '';
	}

	// Set the data of the user in the datahandler.
	$userhandler->set_data($updated_user);
	$errors = '';

	// Validate the user and get any errors that might have occurred.
	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
		$mybb->input['action'] = "editprofile";
	}
	else
	{
		// Are we removing an avatar from this user?
		if($mybb->input['remove_avatar'])
		{
			$extra_user_updates = array(
				"avatar" => "",
				"avatardimensions" => "",
				"avatartype" => ""
			);
			remove_avatars($mybb->user['uid']);
		}

		$user_info = $userhandler->update_user();
		$db->update_query("users", $extra_user_updates, "uid='{$user['uid']}'");
		redirect("modcp.php?action=finduser", $lang->redirect_user_updated);
	}
}

if($mybb->input['action'] == "editprofile")
{
	$user = get_user($mybb->input['uid']);
	if(!$user['uid'])
	{
		error($lang->invalid_user);
	}

	// Check if the current user has permission to edit this user
	$user_permissions = user_permissions($user['uid']);

	// Current user is only a local moderator, cannot edit super mods or admins
	if($mybb->user['usergroup'] == 6 && ($user_permissions['issupermod'] == "yes" || $user_permissions['canadmincp'] == "yes"))
	{
		error_no_permission();
	}
	// Current user is a super mod or is an administrator and the user we are editing is a super admin, cannot edit admins
	else if($mybb->usergroup['issupermod'] == "yes" && $user_permissions['canadmincp'] == "yes" || (is_super_admin($user['uid']) && !is_super_admin($user['uid'])))
	{
		error_no_permission();
	}
	// Otherwise, free to edit

	if($user['website'] == "" || $user['website'] == "http://")
	{
		$user['website'] = "http://";
	}

	if($user['icq'] != "0")
	{
		$user['icq'] = intval($user['icq']);
	}
	if($user['icq'] == 0)
	{
		$user['icq'] = "";
	}

	if(!$errors)
	{
		$mybb->input = $user;
		list($mybb->input['birthday_day'], $mybb->input['birthday_month'], $mybb->input['birthday_year']) = explode("-", $user['birthday']);
	}
	else
	{
		$errors = inline_error($errors);
	}

	// Sanitize all input
	foreach(array('usertitle', 'website', 'icq', 'aim', 'yahoo', 'msn', 'signature', 'birthday_day', 'birthday_month', 'birthday_year') as $field)
	{
		$mybb->input[$field] = htmlspecialchars_uni($mybb->input[$field]);
	}

	if($mybb->usergroup['usertitle'] == "")
	{
		$query = $db->simple_select("usertitles", "*", "posts <='".$user['postnum']."'", array('order_by' => 'posts', 'order_dir' => 'DESC', 'limit' => 1));
		$utitle = $db->fetch_array($query);
		$defaulttitle = $utitle['title'];
	}
	else
	{
		$display_group = usergroup_displaygroup($user['displaygroup']);
		$defaulttitle = $display_group['usertitle'];
	}
	if(empty($user['usertitle']))
	{
		$lang->current_custom_usertitle = '';
	}

	$bdaysel = '';
	for($i = 1; $i <= 31; ++$i)
	{
		if($mybb->input['birthday_day'] == $i)
		{
			$bdaydaysel .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$bdaydaysel .= "<option value=\"$i\">$i</option>\n";
		}
	}
	$bdaymonthsel[$mybb->input['birthday_month']] = "selected";


	// Fetch profile fields
	$query = $db->simple_select("userfields", "*", "ufid='{$user['uid']}'");
	$user_fields = $db->fetch_array($query);

	$requiredfields = '';
	$customfields = '';
	$query = $db->simple_select("profilefields", "*", "editable='yes'", array('order_by' => 'disporder'));
	while($profilefield = $db->fetch_array($query))
	{
		$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
		$profilefield['description'] = htmlspecialchars_uni($profilefield['description']);
		$thing = explode("\n", $profilefield['type'], "2");
		$type = $thing[0];
		$options = $thing[1];
		$field = "fid{$profilefield['fid']}";
		$select = '';
		if($errors)
		{
			$userfield = $mybb->input['profile_fields'][$field];
		}
		else
		{
			$userfield = $user_fields[$field];
		}
		if($type == "multiselect")
		{
			if($errors)
			{
				$useropts = $userfield;
			}
			else
			{
				$useropts = explode("\n", $userfield);
			}
			if(is_array($useropts))
			{
				foreach($useropts as $key => $val)
				{
					$seloptions[$val] = $val;
				}
			}
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					$val = trim($val);
					$val = str_replace("\n", "\\n", $val);

					$sel = "";
					if($val == $seloptions[$val])
					{
						$sel = " selected=\"selected\"";
					}
					$select .= "<option value=\"$val\"$sel>$val</option>\n";
				}
				if(!$profilefield['length'])
				{
					$profilefield['length'] = 3;
				}
				$code = "<select name=\"profile_fields[$field][]\" size=\"{$profilefield['length']}\" multiple=\"multiple\">$select</select>";
			}
		}
		elseif($type == "select")
		{
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					$val = trim($val);
					$val = str_replace("\n", "\\n", $val);
					$sel = "";
					if($val == $userfield)
					{
						$sel = " selected=\"selected\"";
					}
					$select .= "<option value=\"$val\"$sel>$val</option>";
				}
				if(!$profilefield['length'])
				{
					$profilefield['length'] = 1;
				}
				$code = "<select name=\"profile_fields[$field]\" size=\"{$profilefield['length']}\">$select</select>";
			}
		}
		elseif($type == "radio")
		{
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					$checked = "";
					if($val == $userfield)
					{
						$checked = " checked=\"checked\"";
					}
					$code .= "<input type=\"radio\" class=\"radio\" name=\"profile_fields[$field]\" value=\"$val\"$checked /> <span class=\"smalltext\">$val</span><br />";
				}
			}
		}
		elseif($type == "checkbox")
		{
			if($errors)
			{
				$useropts = $userfield;
			}
			else
			{
				$useropts = explode("\n", $userfield);
			}
			if(is_array($useropts))
			{
				foreach($useropts as $key => $val)
				{
					$seloptions[$val] = $val;
				}
			}
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					$checked = "";
					if($val == $seloptions[$val])
					{
						$checked = " checked=\"checked\"";
					}
					$code .= "<input type=\"checkbox\" class=\"checkbox\" name=\"profile_fields[$field][]\" value=\"$val\"$checked /> <span class=\"smalltext\">$val</span><br />";
				}
			}
		}
		elseif($type == "textarea")
		{
			$value = htmlspecialchars_uni($userfield);
			$code = "<textarea name=\"profile_fields[$field]\" rows=\"6\" cols=\"30\" style=\"width: 95%\">$value</textarea>";
		}
		else
		{
			$value = htmlspecialchars_uni($userfield);
			$code = "<input type=\"text\" name=\"profile_fields[$field]\" class=\"textbox\" size=\"{$profilefield['length']}\" maxlength=\"{$profilefield['maxlength']}\" value=\"$value\" />";
		}
		if($profilefield['required'] == "yes")
		{
			eval("\$requiredfields .= \"".$templates->get("usercp_profile_customfield")."\";");
		}
		else
		{
			eval("\$customfields .= \"".$templates->get("usercp_profile_customfield")."\";");
		}
		$altbg = alt_trow();
		$code = "";
		$select = "";
		$val = "";
		$options = "";
		$expoptions = "";
		$useropts = "";
		$seloptions = "";
	}
	if($customfields)
	{
		eval("\$customfields = \"".$templates->get("usercp_profile_profilefields")."\";");
	}

	$lang->edit_profile = sprintf($lang->edit_profile, $user['username']);
	$profile_link = build_profile_link(format_name($user['username'], $user['usergroup'], $user['displaygroup']), $user['uid']);

	eval("\$edituser = \"".$templates->get("modcp_editprofile")."\";");
	output_page($edituser);
}

if($mybb->input['action'] == "finduser")
{
	if(!$perpage)
	{
		$perpage = $mybb->settings['threadsperpage'];
	}
	$where = '';

	if($mybb->input['username'])
	{
		$where = " AND LOWER(username) LIKE '%".my_strtolower($db->escape_string_like($mybb->input['username']))."%'";
	}

	// Sort order & direction
	switch($mybb->input['sortby'])
	{
		case "lastvisit":
			$sortby = "lastvisit";
			break;
		case "postnum":
			$sortby = "postnum";
			break;
		case "username":
			$sortby = "username";
			break;
		default:
			$sortby = "regdate";
	}
	$order = $mybb->input['order'];
	if($order != "asc")
	{
		$order = "desc";
	}

	$query = $db->simple_select("users", "COUNT(uid) AS count", "1=1 {$where}");
	$user_count = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->input['page'] != "last")
	{
		$page = intval($mybb->input['page']);
	}

	$pages = $user_count / $perpage;
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

	$page_url = 'modcp.php?action=finduser';
	foreach(array('username', 'sortby', 'order') as $field)
	{
		if($mybb->input[$field])
		{
			$page_url .= "&amp;{$field}=".htmlspecialchars_uni($mybb->input[$field]);
		}
	}

	$multipage = multipage($user_count, $perpage, $page, $page_url);

	$usergroups_cache = $cache->read("usergroups");

	// Fetch out results
	$query = $db->simple_select("users", "*", "1=1 {$where}", array("order_by" => $sortby, "order_dir" => $order, "limit" => $perpage, "limit_start" => $start));
	while($user = $db->fetch_array($query))
	{
		$alt_row = alt_trow();
		$user['postnum'] = my_number_format($user['postnum']);
		$regdate = my_date($mybb->settings['dateformat'], $user['regdate']);
		$regtime = my_date($mybb->settings['timeformat'], $user['regdate']);
		$lastdate = my_date($mybb->settings['dateformat'], $user['lastactive']);
		$lasttime = my_date($mybb->settings['timeformat'], $user['lastactive']);
		$usergroup = $usergroups_cache[$user['usergroup']]['title'];
		eval("\$users .= \"".$templates->get("modcp_finduser_user")."\";");
	}

	// No results?
	if(!$users)
	{
		eval("\$users = \"".$templates->get("modcp_finduser_noresults")."\";");
	}

	eval("\$finduser = \"".$templates->get("modcp_finduser")."\";");
	output_page($finduser);
}

// Baning actions

if($mybb->input['action'] == "warninglogs")
{
}

if($mybb->input['action'] == "ipsearch")
{
	add_breadcrumb($lang->mcp_nav_ipsearch, "modcp.php?action=ipsearch");
	
	if($mybb->input['ipaddress'])
	{
		$perpage = intval($mybb->input['perpage']);
		if(!$perpage)
		{
			$perpage = $mybb->settings['threadsperpage'];
		}
		
		// Figure out if we need to display multiple pages.
		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}
	
		$postcount = intval($rescount);
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
		
		if(!is_array($groupscache))
		{
			$groupscache = $cache->read("usergroups");
		}
		
		$ipaddressvalue = htmlspecialchars_uni($mybb->input['ipaddress']);
		
		$mybb->input['ipaddress'] = str_replace("*", "%", $mybb->input['ipaddress']);
	
		// Searching for entries in the users table
		if($mybb->input['options'] == "postsearch")
		{			
			// IPv6 IP
			if(strpos($mybb->input['ipaddress'], ":") !== false)
			{
				$ip_sql = "ipaddress LIKE '".$db->escape_string($mybb->input['ipaddress'])."'";
			}
			else
			{
				$ip_range = fetch_longipv4_range($mybb->input['ipaddress']);
				if(!is_array($ip_range))
				{
					$ip_sql = "longipaddress='{$ip_range}'";
				}
				else
				{
					$ip_sql = "longipaddress > '{$ip_range[0]}' AND longipaddress < '{$ip_range[1]}'";
				}
			}
			$query = $db->query("
				SELECT COUNT(pid) AS count
				FROM ".TABLE_PREFIX."posts
				WHERE {$ip_sql}
			");
			$rescount = $db->fetch_field($query, "count");
			
			$query = $db->query("
				SELECT p.username, p.subject, p.pid, p.tid, p.ipaddress, u.usergroup, u.displaygroup
				FROM ".TABLE_PREFIX."posts p
				LEFT JOIN ".TABLE_PREFIX."users u ON(p.uid=u.uid)
				WHERE {$ip_sql}
				LIMIT {$start}, {$perpage}
			");
		}
		else
		{
			// IPv6 IP
			if(strpos($mybb->input['ipaddress'], ":") !== false)
			{
				$ip_sql = "regip LIKE '".$db->escape_string($mybb->input['ipaddress'])."' OR lastip LIKE '".$db->escape_string($mybb->input['ipaddress'])."'";
			}
			else
			{
				$ip_range = fetch_longipv4_range($mybb->input['ipaddress']);
				if(!is_array($ip_range))
				{
					$ip_sql = "longregip='{$ip_range}' OR longlastip='{$ip_range}'";
				}
				else
				{
					$ip_sql = "(longregip > '{$ip_range[0]}' AND longregip < '{$ip_range[1]}') OR (longlastip > '{$ip_range[0]}' AND longlastip < '{$ip_range[1]}')";
				}
			}
			$query = $db->query("
				SELECT COUNT(uid) AS count
				FROM ".TABLE_PREFIX."users
				WHERE {$ip_sql}
			");
			$rescount = $db->fetch_field($query, "count");
			
			$query = $db->query("
				SELECT username, uid, regip, lastip, usergroup, displaygroup
				FROM ".TABLE_PREFIX."users
				WHERE {$ip_sql}
				LIMIT {$start}, {$perpage}
			");
		}
	
		$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=ipsearch&amp;perpage=$perpage&amp;ipaddress={$mybb->input['ipaddress']}");
		if($postcount > $perpage)
		{
			eval("\$resultspages = \"".$templates->get("modcp_ipsearch_multipage")."\";");
		}
		
		while($ipaddress = $db->fetch_array($query))
		{
			$trow = alt_trow();
			$ipaddress['profilelink'] = build_profile_link($ipaddress['username'], $ipaddress['uid']);
			$ipaddress['profilelink'] = format_name($ipaddress['profilelink'], $ipaddress['usergroup'], $ipaddress['displaygroup']);
			
			if($ipaddress['displaygroup'] != 0)
			{
				$ipaddress['usergroup'] = $ipaddress['displaygroup'];
			}
			
			$ipaddress['usergroup'] = $groupscache[$ipaddress['usergroup']]['title'];
			
			if($ipaddress['subject'])
			{
				$subject = "<strong>{$lang->thread}</strong> <a href=\"".get_thread_link($ipaddress['pid'], $ipaddress['tid'])."\" target=\"_blank\">".htmlspecialchars_uni($ipaddress['subject'])."</a><br />";
			}
			else
			{
				$subject = "<div align=\"center\">{$lang->na}</div>";
			}
			
			if($ipaddress['ipaddress'])
			{
				$lang->regip_lastip = $lang->ip_address;
			}
			
			if($ipaddress['regip'])
			{
				$ipaddress['ipaddress'] = $ipaddress['regip'];
			}
			
			if($ipaddress['lastip'])
			{
				if($ipaddress['ipaddress'])
				{
					$ipaddress['ipaddress'] .= " / ";
				}
				
				$ipaddress['ipaddress'] .= $ipaddress['lastip'];
			}
			
			eval("\$results .= \"".$templates->get("modcp_ipsearch_result")."\";");		
		}
	}
	
	if(!$results)
	{
		eval("\$results = \"".$templates->get("modcp_ipsearch_noresults")."\";");		
	}

	// Fetch filter options
	if(!$mybb->input['options'] || $mybb->input['options'] == "usersearch")
	{
		$usersearchselect = "checked=\"checked\"";
		$postsearchselect = "";
	}
	else
	{
		$usersearchselect = "";
		$postsearchselect = "checked=\"checked\"";
	}
	
	$lang->ipsearch_results = sprintf($lang->ipsearch_results, $ipaddressvalue);
	
	eval("\$ipsearch = \"".$templates->get("modcp_ipsearch")."\";");
	output_page($ipsearch);
}

if($mybb->input['action'] == "banning")
{
	add_breadcrumb($lang->mcp_nav_banning, "modcp.php?action=banning");
	
	if(!$mybb->settings['threadsperpage'])
	{
		$mybb->settings['threadsperpage'] = 20;
	}
	
	// Set up the array of ban times.
	$bantimes["1-0-0"] = "1 {$lang->day}";
	$bantimes["2-0-0"] = "2 {$lang->days}";
	$bantimes["3-0-0"] = "3 {$lang->days}";
	$bantimes["4-0-0"] = "4 {$lang->days}";
	$bantimes["5-0-0"] = "5 {$lang->days}";
	$bantimes["6-0-0"] = "6 {$lang->days}";
	$bantimes["7-0-0"] = "1 {$lang->week}";
	$bantimes["14-0-0"] = "2 {$lang->weeks}";
	$bantimes["21-0-0"] = "3 {$lang->weeks}";
	$bantimes["0-1-0"] = "1 {$lang->month}";
	$bantimes["0-2-0"] = "2 {$lang->months}";
	$bantimes["0-3-0"] = "3 {$lang->months}";
	$bantimes["0-4-0"] = "4 {$lang->months}";
	$bantimes["0-5-0"] = "5 {$lang->months}";
	$bantimes["0-6-0"] = "6 {$lang->months}";
	$bantimes["0-0-1"] = "1 {$lang->year}";
	$bantimes["0-0-2"] = "2 {$lang->years}";
	
	// Figure out if we need to display multiple pages.
	$perpage = $mybb->settings['threadsperpage'];
	if($mybb->input['page'] != "last")
	{
		$page = intval($mybb->input['page']);
	}
	
	$query = $db->simple_select("banned", "COUNT(uid) AS count");
	$banned_count = $db->fetch_field($query, "count");
	
	$postcount = intval($banned_count)+1;
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

	$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=banning");
	if($postcount > $perpage)
	{
		eval("\$allbannedpages = \"".$templates->get("modcp_banning_multipage")."\";");
	}

	$query = $db->simple_select("users", "uid, username");
	while($user = $db->fetch_array($query))
	{
		$users[$user['fid']] = $user['name'];
	}

	$query = $db->query("
		SELECT b.*, a.username AS adminuser, u.username
		FROM ".TABLE_PREFIX."banned b
		LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid) 
		LEFT JOIN ".TABLE_PREFIX."users a ON (b.admin=a.uid) 
		ORDER BY lifted ASC
		LIMIT {$start}, {$perpage}
	");
	
	// Get the banned users
	while($banned = $db->fetch_array($query))
	{
		$banned['uidlink'] = "<a href=\"modcp.php?action=banuser_edit&amp;uid={$banned['uid']}\">{$banned['username']}</a>";
		$banned['adminlink'] = get_profile_link($banned['admin']);
		
		$trow = alt_trow();
		
		if($banned['reason'])
		{
			$banned['reason'] = htmlspecialchars_uni($parser->parse_badwords($banned['reason']));
		}
		else
		{
			$banned['reason'] = $lang->na;
		}
		
		if($banned['lifted'] == 'perm' || $banned['lifted'] == '' || $banned['bantime'] == 'perm' || $banned['bantime'] == '---')
		{
			$banlength = $lang->permanent;
			$timeremaining = $lang->na;
		}
		else
		{
			$banlength = $bantimes[$banned['bantime']];
			$timeremaining = modcp_getbanremaining($banned['lifted'])." {$lang->ban_remaining}";
		}
		
		eval("\$allbanned .= \"".$templates->get("modcp_banning_modcp")."\";");
	}
	
	if(!$allbanned)
	{
		eval("\$allbanned = \"".$templates->get("modcp_banning_nobanned")."\";");
	}
	
	eval("\$bannedusers = \"".$templates->get("modcp_banning_banned_users")."\";");
	
	// Generate the banned times dropdown
	foreach($bantimes as $time => $title)
	{
		$liftlist .= "<option value=\"{$time}\"";
		$thatime = date("D, jS M Y @ g:ia", modcp_date2timestamp($time));
		$liftlist .= ">{$title} ({$thatime})</option>\n";
	}
	
	$bangroups = '';
	$query = $db->simple_select("usergroups", "gid, title", "isbannedgroup='yes'");
	while($item = $db->fetch_array($query))
	{
		$bangroups .= "<option value=\"{$item['gid']}\">{$item['title']}</option>\n";
	}
	
	eval("\$banauser = \"".$templates->get("modcp_banning_auser")."\";");
	
	$plugins->run_hooks("modcp_banning");

	eval("\$bannedpage = \"".$templates->get("modcp_banning")."\";");
	output_page($bannedpage);
}

if($mybb->input['action'] == "banuser_edit")
{
	add_breadcrumb($lang->mcp_nav_banning, "modcp.php?action=banning");
	add_breadcrumb($lang->mcp_nav_editing_ban, "modcp.php?action=banning");
	
	if(!$mybb->input['uid'])
	{
		error($lang->banerror_notfound);
	}
	
	// Set up the array of ban times.
	$bantimes["1-0-0"] = "1 {$lang->day}";
	$bantimes["2-0-0"] = "2 {$lang->days}";
	$bantimes["3-0-0"] = "3 {$lang->days}";
	$bantimes["4-0-0"] = "4 {$lang->days}";
	$bantimes["5-0-0"] = "5 {$lang->days}";
	$bantimes["6-0-0"] = "6 {$lang->days}";
	$bantimes["7-0-0"] = "1 {$lang->week}";
	$bantimes["14-0-0"] = "2 {$lang->weeks}";
	$bantimes["21-0-0"] = "3 {$lang->weeks}";
	$bantimes["0-1-0"] = "1 {$lang->month}";
	$bantimes["0-2-0"] = "2 {$lang->months}";
	$bantimes["0-3-0"] = "3 {$lang->months}";
	$bantimes["0-4-0"] = "4 {$lang->months}";
	$bantimes["0-5-0"] = "5 {$lang->months}";
	$bantimes["0-6-0"] = "6 {$lang->months}";
	$bantimes["0-0-1"] = "1 {$lang->year}";
	$bantimes["0-0-2"] = "2 {$lang->years}";
	
	$query = $db->query("
		SELECT b.*, u.username
		FROM ".TABLE_PREFIX."banned b
		LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid) 
		WHERE b.uid='{$mybb->input['uid']}'
		LIMIT 1
	");
	
	$banned = $db->fetch_array($query);
	
	$username = htmlspecialchars_uni($banned['username']);
	$banreason = htmlspecialchars_uni($banned['reason']);
	$uid = $mybb->input['uid'];
	
	// Generate the banned times dropdown
	foreach($bantimes as $time => $title)
	{
		$liftlist .= "<option value=\"{$time}\"";
		if($banned['bantime'] == $time)
		{
			$liftlist .= " selected=\"selected\"";
		}
		$thatime = date("D, jS M Y @ g:ia", modcp_date2timestamp($time));
		$liftlist .= ">{$title} ({$thatime})</option>\n";
	}
	
	$bangroups = '';
	$query = $db->simple_select("usergroups", "gid, title", "isbannedgroup='yes'");
	while($item = $db->fetch_array($query))
	{
		$selected = "";
		if($banned['gid'] == $item['gid'])
		{
			$selected = " selected=\"selected\"";
		}
		$bangroups .= "<option value=\"{$item['gid']}\"{$selected}>{$item['title']}</option>\n";
	}
	
	eval("\$banauser = \"".$templates->get("modcp_banning_edit")."\";");
	output_page($banauser);
}

if($mybb->input['action'] == "do_banuser" && $mybb->request_method == "post")
{	
	// Check the form has been filled in.
	$error = '';
	if(($mybb->input['username'] != '' || $mybb->input['uid']) && $mybb->input['banreason'] != '')
	{
		if($mybb->input['username'])
		{
			// Get the users info from their Username
			$query = $db->simple_select('users', 'uid, usergroup, additionalgroups, displaygroup', "username = '".$db->escape_string($mybb->input['username'])."'");
			$user = $db->fetch_array($query);
		}
		else
		{
			// Get the users info from their uid
			$query = $db->query("
				SELECT b.*, u.uid, u.usergroup, u.additionalgroups, u.displaygroup
				FROM ".TABLE_PREFIX."banned b
				LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid) 
				WHERE b.uid='{$mybb->input['uid']}'
				LIMIT 1
			");
			$user = $db->fetch_array($query);
		}
		
		if($user['uid'] == $mybb->user['uid'])
		{
			$error = $lang->redirect_cannotbanself;
		}
		
		// Figure out if we have enough permissions to ban this user.
		if(is_moderator('', '', $user['uid']) && $mybb->usergroup['cancp'] != "yes")
		{
			$permissions = user_permissions($user['uid']);
			if($permissions['issupermod'] == "yes")
			{
				$error = $lang->redirect_cannotbanuser;
			}
		}
		
		// Check we have a valid user.
		if($user['uid'] != '')
		{
			// Check the user isn't already banned
			$query = $db->simple_select('banned', 'uid', "uid='{$user['uid']}'");
			if($db->num_rows($query) > 0 && !$mybb->input['uid'])
			{
				redirect("modcp.php?action=banning", $lang->redirect_banuseralreadybanned);
			}
			else
			{
				// Ban the user
				if($mybb->input['liftafter'] == '---')
				{
					$lifted = 0;
				}
				else
				{
					$lifted = modcp_date2timestamp($mybb->input['liftafter']);
				}
								
				if($mybb->input['uid'])
				{
					$update_array = array(
						'gid' => intval($mybb->input['usergroup']),
						'oldgroup' => $user['usergroup'],
						'oldadditionalgroups' => $user['additionalgroups'],
						'olddisplaygroup' => $user['displaygroup'],
						'admin' => intval($mybb->user['uid']),
						'dateline' => $db->escape_string(time()),
						'bantime' => $db->escape_string($mybb->input['liftafter']),
						'lifted' => $db->escape_string($lifted),
						'reason' => $db->escape_string($mybb->input['banreason'])
					);
				
					$db->update_query('banned', $update_array, "uid='{$user['uid']}'");
				}
				else
				{
					$insert_array = array(
						'uid' => $user['uid'],
						'gid' => intval($mybb->input['usergroup']),
						'oldgroup' => $user['usergroup'],
						'oldadditionalgroups' => $user['additionalgroups'],
						'olddisplaygroup' => $user['displaygroup'],
						'admin' => intval($mybb->user['uid']),
						'dateline' => $db->escape_string(time()),
						'bantime' => $db->escape_string($mybb->input['liftafter']),
						'lifted' => $db->escape_string($lifted),
						'reason' => $db->escape_string($mybb->input['banreason'])
					);
					
					$db->insert_query('banned', $insert_array);
				}
				
				// Move the user to the banned group
				$update_array = array(
					'usergroup' => intval($mybb->input['usergroup']),
					'displaygroup' => 0
				);
				$db->update_query('users', $update_array, "uid = {$user['uid']}");
				
				if($mybb->input['uid'])
				{
					redirect("modcp.php?action=banning", $lang->redirect_banuser_updated);
				}
				else
				{
					redirect("modcp.php?action=banning", $lang->redirect_banuser);
				}
			}
		}
		else
		{
			$error = $lang->banerror_notfound;
		}
	}
	else
	{
		$error = $lang->banerror_empty;
	}
	
	// If we have an error, we need to let the user fix it.
	if($error != '')
	{
		add_breadcrumb($lang->mcp_nav_banning, "modcp.php?action=banning");
		
		// Set up the array of ban times.
		$bantimes["1-0-0"] = "1 {$lang->day}";
		$bantimes["2-0-0"] = "2 {$lang->days}";
		$bantimes["3-0-0"] = "3 {$lang->days}";
		$bantimes["4-0-0"] = "4 {$lang->days}";
		$bantimes["5-0-0"] = "5 {$lang->days}";
		$bantimes["6-0-0"] = "6 {$lang->days}";
		$bantimes["7-0-0"] = "1 {$lang->week}";
		$bantimes["14-0-0"] = "2 {$lang->weeks}";
		$bantimes["21-0-0"] = "3 {$lang->weeks}";
		$bantimes["0-1-0"] = "1 {$lang->month}";
		$bantimes["0-2-0"] = "2 {$lang->months}";
		$bantimes["0-3-0"] = "3 {$lang->months}";
		$bantimes["0-4-0"] = "4 {$lang->months}";
		$bantimes["0-5-0"] = "5 {$lang->months}";
		$bantimes["0-6-0"] = "6 {$lang->months}";
		$bantimes["0-0-1"] = "1 {$lang->year}";
		$bantimes["0-0-2"] = "2 {$lang->years}";
	
		// Generate the banned times dropdown
		foreach($bantimes as $time => $title)
		{
			$liftlist .= "<option value=\"{$time}\"";
			if($time == $mybb->input['liftafter'])
			{
				$liftlist .= ' selected="selected"';
			}
			$thatime = date("D, jS M Y @ g:ia", modcp_date2timestamp($time));
			$liftlist .= ">{$title} ({$thatime})</option>\n";
		}
		if($mybb->input['liftafter'] == "---")
		{
			$permsel = ' selected="selected"';
		}
		
		$bangroups = '';
		$query = $db->simple_select("usergroups", "gid, title", "isbannedgroup='yes'");
		while($item = $db->fetch_array($query))
		{
			if($mybb->input['usergroup'] == $item['gid'])
			{
				$bangroups .= "<option value=\"{$item['gid']}\" selected=\"selected\">{$item['title']}</option>\n";
			}
			else
			{
				$bangroups .= "<option value=\"{$item['gid']}\">{$item['title']}</option>\n";
			}
		}
		
		eval("\$banerror = \"".$templates->get("modcp_banning_error")."\";");
		
		if($mybb->input['uid'])
		{
			// Get the users info from their uid
			$query = $db->query("
				SELECT b.*, u.uid, u.username, u.usergroup, u.additionalgroups, u.displaygroup
				FROM ".TABLE_PREFIX."banned b
				LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid) 
				WHERE b.uid='{$mybb->input['uid']}'
				LIMIT 1
			");
			$user = $db->fetch_array($query);
			
			$username = htmlspecialchars_uni($user['username']);
			$banreason = htmlspecialchars_uni($user['banreason']);
			$uid = $user['uid'];
			eval("\$banpage = \"".$templates->get("modcp_banning_edit")."\";");
		}
		else
		{
			eval("\$banauser = \"".$templates->get("modcp_banning_auser")."\";");
			eval("\$banpage = \"".$templates->get("modcp_banning")."\";");
		}		
		
		output_page($banpage);
	}
}

if(!$mybb->input['action'])
{
	eval("\$modcp = \"".$templates->get("modcp")."\";");
	output_page($modcp);
}

?>