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
$templatelist .= ",modcp_reports_allnoreports,modcp_reports_noreports,";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_user.php";
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
		$flist = "AND fid IN (0{$flist})";
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

	$db->update_query("reportedposts", array('reportstatus' => 1), "rid IN ({$rids})");
	$cache->update_reportedposts();
	redirect("modcp.php?action=reports", $lang->redirect_reportsmarked);
}

if($mybb->input['action'] == "reports")
{
	add_breadcrumb($lang->nav_reported_posts, "modcp.php?action=reports");

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
	add_breadcrumb($lang->nav_modlogs, "modcp.php?action=modlogs");

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

	$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=modlogs_results&amp;perpage=$perpage&amp;frommod=$frommod&amp;orderby=$orderby");
	if($postcount > $perpage)
	{
		eval("\$resultspages = \"".$templates->get("modcp_logs_multipage")."\";");
	}
	
	$lang->modlogs_results = sprintf($lang->modlogs_results, $page, $pages, $rescount);
	
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
}

if($mybb->input['action'] == "modqueue")
{
}

if($mybb->input['action'] == "do_editprofile")
{
}

if($mybb->input['action'] == "editprofile")
{
}

if($mybb->input['action'] == "finduser")
{
}

// Baning actions

if($mybb->input['action'] == "warninglogs")
{
}

if($mybb->input['action'] == "ipsearch")
{
}

if(!$mybb->input['action'])
{
	eval("\$modcp = \"".$templates->get("modcp")."\";");
	output_page($modcp);
}

?>