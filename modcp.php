<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

define("IN_MYBB", 1);

$templatelist = "";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_user.php";

// Load global language phrases
$lang->load("modcp");

if($mybb->user['uid'] == 0)
{
	error_no_permission();
}

$errors = '';

// Fetch the Mod CP menu
eval("\$modcp_nav = \"".$templates->get("modcp_nav")."\";");

$plugins->run_hooks("modcp_start");



if($mybb->input['action'] == "do_reports")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	if(!is_moderator())
	{
		error_no_permission();
	}
	$flist = '';
	if($mybb->usergroup['issupermod'] != "yes")
	{
		$query = $db->simple_select("moderators", "*", "uid='".$mybb->user['uid']."'");
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

	$plugins->run_hooks("modcp_do_reports");

	$sqlarray = array(
		"reportstatus" => 1,
		);
	$db->update_query("reportedposts", $sqlarray, "rid IN ($rids)");
	$cache->update_reportedposts();
	redirect("moderation.php?action=reports", $lang->redirect_reportsmarked);
}

if($mybb->input['action'] == "reports")
{
	if(!is_moderator())
	{
		error_no_permission();
	}

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
		$query = $db->simple_select("reportedposts", "COUNT(r.rid) AS count", "r.rid <= '".$mybb->input['rid']."'");
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

	$multipage = multipage($postcount, $perpage, $page, "moderation.php?action=reports");
	if($postcount > $perpage)
	{
		eval("\$reportspages = \"".$templates->get("modcp_reports_multipage")."\";");
	}

	$query = $db->simple_select("forums", "fid,name");
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

if($mybb->input['action'] == "all_reports")
{
	if(!is_moderator())
	{
		error_no_permission();
	}
	
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
		$query = $db->simple_select("reportedposts", "COUNT(rid) AS count", "rid <= '".$mybb->input['rid']."'");
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
		eval("\$allreportspages = \"".$templates->get("modcp_allreports_multipage")."\";");
	}

	$query = $db->simple_select("forums", "fid,name");
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

	eval("\$allreportedposts = \"".$templates->get("modcp_allreports")."\";");
	output_page($allreportedposts);
	break;
}

?>