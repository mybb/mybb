<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'modcp.php');

$templatelist = "modcp_reports,modcp_reports_report,modcp_reports_multipage,modcp_reports_allreport,modcp_reports_allreports,modcp_modlogs_multipage,modcp_announcements_delete,modcp_announcements_edit";
$templatelist .= ",modcp_reports_allnoreports,modcp_reports_noreports,modcp_banning,modcp_banning_ban,modcp_announcements_announcement_global,modcp_no_announcements_forum,modcp_modqueue_threads_thread";
$templatelist .= ",modcp_banning_multipage,modcp_banning_nobanned,modcp_modqueue_threads_empty,modcp_modqueue_masscontrols,modcp_modqueue_threads,modcp_modqueue_posts_post,modcp_modqueue_posts_empty";
$templatelist .= ",modcp_nav,modcp_modlogs_noresults,modcp,modcp_modqueue_posts,modcp_modqueue_attachments_attachment,modcp_modqueue_attachments_empty,modcp_modqueue_attachments,modcp_editprofile_suspensions_info";
$templatelist .= ",modcp_no_announcements_global,modcp_announcements_global,modcp_announcements_forum,modcp_announcements,modcp_editprofile_select_option,modcp_editprofile_select,modcp_finduser_noresults";
$templatelist .= ",codebuttons,smilieinsert,modcp_announcements_new,modcp_modqueue_empty,forumjump_bit,forumjump_special,modcp_warninglogs_warning_revoked,modcp_warninglogs_warning,modcp_ipsearch_result";
$templatelist .= ",modcp_modlogs,modcp_finduser_user,modcp_finduser,usercp_profile_customfield,usercp_profile_profilefields,modcp_ipsearch_noresults,modcp_ipsearch_results,modcp_ipsearch_misc_info";
$templatelist .= ",modcp_editprofile,modcp_ipsearch,modcp_banuser_addusername,modcp_banuser,modcp_warninglogs_nologs,modcp_banuser_editusername,modcp_lastattachment,modcp_lastpost,modcp_lastthread";
$templatelist .= ",modcp_warninglogs,modcp_modlogs_result,modcp_editprofile_signature,forumjump_advanced,smilieinsert_getmore,modcp_announcements_forum_nomod,modcp_announcements_announcement,multipage_prevpage";
$templatelist .= ",multipage_start,multipage_page_current,multipage_page,multipage_end,multipage_nextpage,multipage";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/functions_upload.php";
require_once MYBB_ROOT."inc/functions_modcp.php";
require_once MYBB_ROOT."inc/class_parser.php";

$parser = new postParser;

// Set up the array of ban times.
$bantimes = fetch_ban_times();

// Load global language phrases
$lang->load("modcp");

if($mybb->user['uid'] == 0 || $mybb->usergroup['canmodcp'] != 1)
{
	error_no_permission();
}

$errors = '';
// SQL for fetching items only related to forums this user moderates
$moderated_forums = array();
if($mybb->usergroup['issupermod'] != 1)
{
	$query = $db->simple_select("moderators", "*", "(id='{$mybb->user['uid']}' AND isgroup = '0') OR (id='{$mybb->user['usergroup']}' AND isgroup = '1')");
	while($forum = $db->fetch_array($query))
	{
		$flist .= ",'{$forum['fid']}'";
		
		$children = get_child_list($forum['fid']);
		if(!empty($children))
		{
			$flist .= ",'".implode("','", $children)."'";
		}
		$moderated_forums[] = $forum['fid'];
	}
	if($flist)
	{
		$tflist = " AND t.fid IN (0{$flist})";
		$flist = " AND fid IN (0{$flist})";
	}
}
else
{
	$flist = $tflist = '';
}

// Retrieve a list of unviewable forums
$unviewableforums = get_unviewable_forums();

if($unviewableforums && !is_super_admin($mybb->user['uid']))
{
	$flist .= " AND fid NOT IN ({$unviewableforums})";
	$tflist .= " AND t.fid NOT IN ({$unviewableforums})";

	$unviewableforums = str_replace("'", '', $unviewableforums);
	$unviewableforums = explode(',', $unviewableforums);
}
else
{
	$unviewableforums = array();
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

	if(!is_array($mybb->input['reports']))
	{
		error($lang->error_noselected_reports);
	}

	$sql = '1=1';
	if(!$mybb->input['allbox'])
	{
		$mybb->input['reports'] = array_map("intval", $mybb->input['reports']);
		$rids = implode($mybb->input['reports'], "','");
		$rids = "'0','{$rids}'";

		$sql = "rid IN ({$rids})";
	}

	$plugins->run_hooks("modcp_do_reports");

	$db->update_query("reportedposts", array('reportstatus' => 1), "{$sql}{$flist}");
	$cache->update_reportedposts();
	
	$page = intval($mybb->input['page']);
	
	redirect("modcp.php?action=reports&page={$page}", $lang->redirect_reportsmarked);
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

	$mybb->input['rid'] = intval($mybb->input['rid']);

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
	$postcount = intval($report_count);
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->input['page'] == "last")
	{
		$page = $pages;
	}

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}

	if($page && $page > 0)
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
	
	$plugins->run_hooks("modcp_reports_start");

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

	if(!$db->num_rows($query))
	{
		eval("\$reports = \"".$templates->get("modcp_reports_noreports")."\";");
	}
	else
	{
		while($report = $db->fetch_array($query))
		{
			$trow = alt_trow();
			if(is_moderator($report['fid']))
			{
				$trow = 'trow_shaded';
			}

			$report['postlink'] = get_post_link($report['pid'], $report['tid']);
			$report['threadlink'] = get_thread_link($report['tid']);
			$report['posterlink'] = get_profile_link($report['postuid']);
			$report['reporterlink'] = get_profile_link($report['uid']);
			$reportdate = my_date($mybb->settings['dateformat'], $report['dateline']);
			$reporttime = my_date($mybb->settings['timeformat'], $report['dateline']);
			$report['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($report['threadsubject']));

			eval("\$reports .= \"".$templates->get("modcp_reports_report")."\";");
		}
	}

	$plugins->run_hooks("modcp_reports_end");

	eval("\$reportedposts = \"".$templates->get("modcp_reports")."\";");
	output_page($reportedposts);
}

if($mybb->input['action'] == "allreports")
{
	add_breadcrumb($lang->mcp_nav_all_reported_posts, "modcp.php?action=allreports");

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
		$mybb->input['rid'] = intval($mybb->input['rid']);
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
	$postcount = intval($warnings);
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->input['page'] == "last")
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

	$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=allreports");
	if($postcount > $perpage)
	{
		eval("\$allreportspages = \"".$templates->get("modcp_reports_multipage")."\";");
	}
	
	$plugins->run_hooks("modcp_allreports_start");

	$query = $db->query("
		SELECT r.*, u.username, up.username AS postusername, up.uid AS postuid, t.subject AS threadsubject
		FROM ".TABLE_PREFIX."reportedposts r
		LEFT JOIN ".TABLE_PREFIX."posts p ON (r.pid=p.pid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (p.tid=t.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users up ON (p.uid=up.uid)
		ORDER BY r.dateline DESC
		LIMIT {$start}, {$perpage}
	");

	$allreports = '';
	if(!$db->num_rows($query))
	{
		eval("\$allreports = \"".$templates->get("modcp_reports_allnoreports")."\";");
	}
	else
	{
		while($report = $db->fetch_array($query))
		{
			$trow = alt_trow();
			
			$report['threadlink'] = get_thread_link($report['tid']);

			$report['posterlink'] = get_profile_link($report['postuid']);
			$report['postlink'] = get_post_link($report['pid'], $report['tid']);
			$report['postusername'] = build_profile_link($report['postusername'], $report['postuid']);
			$report['reporterlink'] = get_profile_link($report['uid']);

			$reportdate = my_date($mybb->settings['dateformat'], $report['dateline']);
			$reporttime = my_date($mybb->settings['timeformat'], $report['dateline']);

			if($report['reportstatus'] == 0)
			{
				$trow = "trow_shaded";
			}
			
			// No subject? Set it to N/A
			if($report['threadsubject'] == '')
			{
				$report['threadsubject'] = $lang->na;
			}
			else
			{
				// Only parse bad words and sanitize subject if there is one...
				$report['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($report['threadsubject']));
			}
			
			$report['threadsubject'] = "<a href=\"".get_thread_link($report['tid'])."\" target=\"_blank\">{$report['threadsubject']}</a>";

			eval("\$allreports .= \"".$templates->get("modcp_reports_allreport")."\";");
		}
	}

	$plugins->run_hooks("modcp_allreports_end");

	eval("\$allreportedposts = \"".$templates->get("modcp_reports_allreports")."\";");
	output_page($allreportedposts);
}

if($mybb->input['action'] == "modlogs")
{
	add_breadcrumb($lang->mcp_nav_modlogs, "modcp.php?action=modlogs");

	$perpage = intval($mybb->input['perpage']);
	if(!$perpage || $perpage <= 0)
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
	
	$plugins->run_hooks("modcp_modlogs_start");

	$query = $db->query("
		SELECT COUNT(l.dateline) AS count
		FROM ".TABLE_PREFIX."moderatorlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
		WHERE 1=1 {$where}{$tflist}
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

	$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=modlogs&amp;perpage=$perpage&amp;uid={$mybb->input['uid']}&amp;fid={$mybb->input['fid']}&amp;sortby={$mybb->input['sortby']}&amp;order={$mybb->input['order']}");
	if($postcount > $perpage)
	{
		eval("\$resultspages = \"".$templates->get("modcp_modlogs_multipage")."\";");
	}
	$query = $db->query("
		SELECT l.*, u.username, u.usergroup, u.displaygroup, t.subject AS tsubject, f.name AS fname, p.subject AS psubject
		FROM ".TABLE_PREFIX."moderatorlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=l.fid)
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=l.pid)
		WHERE 1=1 {$where}{$tflist}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($logitem = $db->fetch_array($query))
	{
		$information = '';
		$logitem['action'] = htmlspecialchars_uni($logitem['action']);
		$log_date = my_date($mybb->settings['dateformat'], $logitem['dateline']);
		$log_time = my_date($mybb->settings['timeformat'], $logitem['dateline']);
		$trow = alt_trow();
		$username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);
		$logitem['profilelink'] = build_profile_link($username, $logitem['uid']);
		if($logitem['tsubject'])
		{
			$information = "<strong>{$lang->thread}</strong> <a href=\"".get_thread_link($logitem['tid'])."\" target=\"_blank\">".htmlspecialchars_uni($logitem['tsubject'])."</a><br />";
		}
		if($logitem['fname'])
		{
			$information .= "<strong>{$lang->forum}</strong> <a href=\"".get_forum_link($logitem['fid'])."\" target=\"_blank\">{$logitem['fname']}</a><br />";
		}
		if($logitem['psubject'])
		{
			$information .= "<strong>{$lang->post}</strong> <a href=\"".get_post_link($logitem['pid'])."#pid{$logitem['pid']}\">".htmlspecialchars_uni($logitem['psubject'])."</a>";
		}

		// Edited a user?
		if(!$logitem['tsubject'] || !$logitem['fname'] || !$logitem['psubject'])
		{
			$data = unserialize($logitem['data']);
			if($data['uid'])
			{
				$information = $lang->sprintf($lang->edited_user_info, htmlspecialchars_uni($data['username']), get_profile_link($data['uid']));
			}
		}

		eval("\$results .= \"".$templates->get("modcp_modlogs_result")."\";");
	}

	if(!$results)
	{
		eval("\$results = \"".$templates->get("modcp_modlogs_noresults")."\";");
	}
	
	$plugins->run_hooks("modcp_modlogs_filter");

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
		// Deleted Users
		if(!$user['username'])
		{
			$user['username'] = $lang->na_deleted;
		}
		
		$selected = '';
		if($mybb->input['uid'] == $user['uid'])
		{
			$selected = " selected=\"selected\"";
		}
		$user_options .= "<option value=\"{$user['uid']}\"{$selected}>".htmlspecialchars_uni($user['username'])."</option>\n";
	}

	$forum_select = build_forum_jump("", $mybb->input['fid'], 1, '', 0, true, '', "fid");

	eval("\$modlogs = \"".$templates->get("modcp_modlogs")."\";");
	output_page($modlogs);
}

if($mybb->input['action'] == "do_delete_announcement")
{
	verify_post_check($mybb->input['my_post_key']);

	$aid = intval($mybb->input['aid']);
	$query = $db->simple_select("announcements", "aid, subject, fid", "aid='{$aid}'");
	$announcement = $db->fetch_array($query);

	if(!$announcement['aid'])
	{
		error($lang->error_invalid_announcement);
	}
	if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'])) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}
	
	$plugins->run_hooks("modcp_do_delete_announcement");

	$db->delete_query("announcements", "aid='{$aid}'");
	$cache->update_forumsdisplay();

	redirect("modcp.php?action=announcements", $lang->redirect_delete_announcement);
}

if($mybb->input['action'] == "delete_announcement")
{
	$aid = intval($mybb->input['aid']);
	$query = $db->simple_select("announcements", "aid, subject, fid", "aid='{$aid}'");

	$announcement = $db->fetch_array($query);
	$announcement['subject'] = htmlspecialchars_uni($announcement['subject']);

	if(!$announcement['aid'])
	{
		error($lang->error_invalid_announcement);
	}

	if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'])) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}
	
	$plugins->run_hooks("modcp_delete_announcement");

	eval("\$announcements = \"".$templates->get("modcp_announcements_delete")."\";");
	output_page($announcements);
}

if($mybb->input['action'] == "do_new_announcement")
{
	verify_post_check($mybb->input['my_post_key']);

	$announcement_fid = intval($mybb->input['fid']);
	if(($mybb->usergroup['issupermod'] != 1 && $announcement_fid == -1) || ($announcement_fid != -1 && !is_moderator($announcement_fid)) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	if(!trim($mybb->input['title']))
	{
		$errors[] = $lang->error_missing_title;
	}

	if(!trim($mybb->input['message']))
	{
		$errors[] = $lang->error_missing_message;
	}

	if(!trim($mybb->input['fid']))
	{
		$errors[] = $lang->error_missing_forum;
	}
	
	$startdate = @explode(" ", $mybb->input['starttime_time']);
	$startdate = @explode(":", $startdate[0]);
	$enddate = @explode(" ", $mybb->input['endtime_time']);
	$enddate = @explode(":", $enddate[0]);

	if(stristr($mybb->input['starttime_time'], "pm"))
	{
		$startdate[0] = 12+$startdate[0];
		if($startdate[0] >= 24)
		{
			$startdate[0] = "00";
		}
	}

	if(stristr($mybb->input['endtime_time'], "pm"))
	{
		$enddate[0] = 12+$enddate[0];
		if($enddate[0] >= 24)
		{
			$enddate[0] = "00";
		}
	}
	
	$months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');			
	if(!in_array($mybb->input['starttime_month'], $months))
	{
		$mybb->input['starttime_month'] = 1;
	}

	$startdate = gmmktime(intval($startdate[0]), intval($startdate[1]), 0, (int)$mybb->input['starttime_month'], intval($mybb->input['starttime_day']), intval($mybb->input['starttime_year']));
	if(!checkdate(intval($mybb->input['starttime_month']), intval($mybb->input['starttime_day']), intval($mybb->input['starttime_year'])) || $startdate < 0 || $startdate == false)
	{
		$errors[] = $lang->error_invalid_start_date;
	}

	if($mybb->input['endtime_type'] == "2")
	{
		$enddate = '0';
	}
	else
	{
		if(!in_array($mybb->input['endtime_month'], $months))
		{
			$mybb->input['endtime_month'] = 1;
		}
		$enddate = gmmktime(intval($enddate[0]), intval($enddate[1]), 0, (int)$mybb->input['endtime_month'], intval($mybb->input['endtime_day']), intval($mybb->input['endtime_year']));
		if(!checkdate(intval($mybb->input['endtime_month']), intval($mybb->input['endtime_day']), intval($mybb->input['endtime_year'])) || $enddate < 0 || $enddate == false)
		{
			$errors[] = $lang->error_invalid_end_date;
		}
		if($enddate <= $startdate)
		{
			$errors[] = $lang->error_end_before_start;
		}
	}
	
	$plugins->run_hooks("modcp_do_new_announcement_start");

	if(!$errors)
	{
		$insert_announcement = array(
			'fid' => $announcement_fid,
			'uid' => $mybb->user['uid'],
			'subject' => $db->escape_string($mybb->input['title']),
			'message' => $db->escape_string($mybb->input['message']),
			'startdate' => $startdate,
			'enddate' => $enddate,
			'allowhtml' => $db->escape_string($mybb->input['allowhtml']),
			'allowmycode' => $db->escape_string($mybb->input['allowmycode']),
			'allowsmilies' => $db->escape_string($mybb->input['allowsmilies']),
		);

		$aid = $db->insert_query("announcements", $insert_announcement);
		
		$plugins->run_hooks("modcp_do_new_announcement_end");
		
		$cache->update_forumsdisplay();
		redirect("modcp.php?action=announcements", $lang->redirect_add_announcement);
	}
	else
	{
		$mybb->input['action'] = 'new_announcement';
	}
}

if($mybb->input['action'] == "new_announcement")
{
	add_breadcrumb($lang->mcp_nav_announcements, "modcp.php?action=announcements");
	add_breadcrumb($lang->add_announcement, "modcp.php?action=new_announcements");

	$announcement_fid = intval($mybb->input['fid']);

	if(($mybb->usergroup['issupermod'] != 1 && $announcement_fid == -1) || ($announcement_fid != -1 && !is_moderator($announcement_fid)) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	// Deal with inline errors
	if(is_array($errors))
	{
		$errors = inline_error($errors);
		
		// Set $announcement to input stuff
		$announcement['subject'] = $mybb->input['title'];
		$announcement['message'] = $mybb->input['message'];
		$announcement['allowhtml'] = $mybb->input['allowhtml'];
		$announcement['allowmycode'] = $mybb->input['allowmycode'];
		$announcement['allowsmilies'] = $mybb->input['allowsmilies'];
		
		$months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');			
		if(!in_array($mybb->input['starttime_month'], $months))
		{
			$mybb->input['starttime_month'] = 1;
		}
		
		if(!in_array($mybb->input['endtime_month'], $months))
		{
			$mybb->input['endtime_month'] = 1;
		}
		
		$startmonth = $mybb->input['starttime_month'];
		$startdateyear = htmlspecialchars_uni($mybb->input['starttime_year']);
		$startday = intval($mybb->input['starttime_day']);
		$starttime_time = htmlspecialchars($mybb->input['starttime_time']);
		$endmonth = $mybb->input['endtime_month'];
		$enddateyear = htmlspecialchars_uni($mybb->input['endtime_year']);
		$endday = intval($mybb->input['endtime_day']);
		$endtime_time = htmlspecialchars($mybb->input['endtime_time']);
	}
	else
	{
		// Note: dates are in GMT timezone
		$starttime_time = gmdate("g:i a", TIME_NOW);
		$endtime_time = gmdate("g:i a", TIME_NOW);
		$startday = $endday = gmdate("j", TIME_NOW);
		$startmonth = $endmonth = gmdate("m", TIME_NOW);
		$startdateyear = gmdate("Y", TIME_NOW);

		$enddateyear = $startdateyear+1;
	}

	// Generate form elements
	for($i = 1; $i <= 31; ++$i)
	{
		if($startday == $i)
		{
			$startdateday .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$startdateday .= "<option value=\"$i\">$i</option>\n";
		}

		if($endday == $i)
		{
			$enddateday .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$enddateday .= "<option value=\"$i\">$i</option>\n";
		}
	}

	$startmonthsel = $endmonthsel = array();
	$startmonthsel[$startmonth] = "selected=\"selected\"";
	$endmonthsel[$endmonth] = "selected=\"selected\"";

	$startdatemonth .= "<option value=\"01\" {$startmonthsel['01']}>{$lang->january}</option>\n";
	$enddatemonth .= "<option value=\"01\" {$endmonthsel['01']}>{$lang->january}</option>\n";
	$startdatemonth .= "<option value=\"02\" {$startmonthsel['02']}>{$lang->february}</option>\n";
	$enddatemonth .= "<option value=\"02\" {$endmonthsel['02']}>{$lang->february}</option>\n";
	$startdatemonth .= "<option value=\"03\" {$startmonthsel['03']}>{$lang->march}</option>\n";
	$enddatemonth .= "<option value=\"03\" {$endmonthsel['03']}>{$lang->march}</option>\n";
	$startdatemonth .= "<option value=\"04\" {$startmonthsel['04']}>{$lang->april}</option>\n";
	$enddatemonth .= "<option value=\"04\" {$endmonthsel['04']}>{$lang->april}</option>\n";
	$startdatemonth .= "<option value=\"05\" {$startmonthsel['05']}>{$lang->may}</option>\n";
	$enddatemonth .= "<option value=\"05\" {$endmonthsel['05']}>{$lang->may}</option>\n";
	$startdatemonth .= "<option value=\"06\" {$startmonthsel['06']}>{$lang->june}</option>\n";
	$enddatemonth .= "<option value=\"06\" {$endmonthsel['06']}>{$lang->june}</option>\n";
	$startdatemonth .= "<option value=\"07\" {$startmonthsel['07']}>{$lang->july}</option>\n";
	$enddatemonth .= "<option value=\"07\" {$endmonthsel['07']}>{$lang->july}</option>\n";
	$startdatemonth .= "<option value=\"08\" {$startmonthsel['08']}>{$lang->august}</option>\n";
	$enddatemonth .= "<option value=\"08\" {$endmonthsel['08']}>{$lang->august}</option>\n";
	$startdatemonth .= "<option value=\"09\" {$startmonthsel['09']}>{$lang->september}</option>\n";
	$enddatemonth .= "<option value=\"09\" {$endmonthsel['09']}>{$lang->september}</option>\n";
	$startdatemonth .= "<option value=\"10\" {$startmonthsel['10']}>{$lang->october}</option>\n";
	$enddatemonth .= "<option value=\"10\" {$endmonthsel['10']}>{$lang->october}</option>\n";
	$startdatemonth .= "<option value=\"11\" {$startmonthsel['11']}>{$lang->november}</option>\n";
	$enddatemonth .= "<option value=\"11\" {$endmonthsel['11']}>{$lang->november}</option>\n";
	$startdatemonth .= "<option value=\"12\" {$startmonthsel['12']}>{$lang->december}</option>\n";
	$enddatemonth .= "<option value=\"12\" {$endmonthsel['12']}>{$lang->december}</option>\n";

	$title = htmlspecialchars_uni($announcement['subject']);
	$message = htmlspecialchars_uni($announcement['message']);

	$html_sel = $mycode_sel = $smilies_sel = array();
	if($mybb->input['allowhtml'] || !isset($mybb->input['allowhtml']))
	{
		$html_sel['yes'] = ' checked="checked"';
	}
	else
	{
		$html_sel['no'] = ' checked="checked"';
	}

	if($mybb->input['allowmycode'] || !isset($mybb->input['allowmycode']))
	{
		$mycode_sel['yes'] = ' checked="checked"';
	}
	else
	{
		$mycode_sel['no'] = ' checked="checked"';
	}

	if($mybb->input['allowsmilies'] || !isset($mybb->input['allowsmilies']))
	{
		$smilies_sel['yes'] = ' checked="checked"';
	}
	else
	{
		$smilies_sel['no'] = ' checked="checked"';
	}

	if($mybb->input['endtime_type'] == 2 || !isset($mybb->input['endtime_type']))
	{
		$end_type_sel['infinite'] = ' checked="checked"';
	}
	else
	{
		$end_type_sel['finite'] = ' checked="checked"';
	}

	// MyCode editor
	$codebuttons = build_mycode_inserter();
	$smilieinserter = build_clickable_smilies();
	
	$plugins->run_hooks("modcp_new_announcement");

	eval("\$announcements = \"".$templates->get("modcp_announcements_new")."\";");
	output_page($announcements);
}

if($mybb->input['action'] == "do_edit_announcement")
{
	verify_post_check($mybb->input['my_post_key']);

	// Get the announcement
	$aid = intval($mybb->input['aid']);
	$query = $db->simple_select("announcements", "aid, subject, fid", "aid='{$aid}'");
	$announcement = $db->fetch_array($query);

	// Check that it exists
	if(!$announcement['aid'])
	{
		error($lang->error_invalid_announcement);
	}

	// Mod has permissions to edit this announcement
	if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'])) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	// Basic error checking
	if(!trim($mybb->input['title']))
	{
		$errors[] = $lang->error_missing_title;
	}

	if(!trim($mybb->input['message']))
	{
		$errors[] = $lang->error_missing_message;
	}

	if(!trim($mybb->input['fid']))
	{
		$errors[] = $lang->error_missing_forum;
	}
	
	$startdate = @explode(" ", $mybb->input['starttime_time']);
	$startdate = @explode(":", $startdate[0]);
	$enddate = @explode(" ", $mybb->input['endtime_time']);
	$enddate = @explode(":", $enddate[0]);

	if(stristr($mybb->input['starttime_time'], "pm"))
	{
		$startdate[0] = 12+$startdate[0];
		if($startdate[0] >= 24)
		{
			$startdate[0] = "00";
		}
	}

	if(stristr($mybb->input['endtime_time'], "pm"))
	{
		$enddate[0] = 12+$enddate[0];
		if($enddate[0] >= 24)
		{
			$enddate[0] = "00";
		}
	}

	$months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');			
	if(!in_array($mybb->input['starttime_month'], $months))
	{
		$mybb->input['starttime_month'] = 1;
	}

	$startdate = gmmktime(intval($startdate[0]), intval($startdate[1]), 0, (int)$mybb->input['starttime_month'], intval($mybb->input['starttime_day']), intval($mybb->input['starttime_year']));
	if(!checkdate(intval($mybb->input['starttime_month']), intval($mybb->input['starttime_day']), intval($mybb->input['starttime_year'])) || $startdate < 0 || $startdate == false)
	{
		$errors[] = $lang->error_invalid_start_date;
	}

	if($mybb->input['endtime_type'] == "2")
	{
		$enddate = '0';
	}
	else
	{		
		if(!in_array($mybb->input['endtime_month'], $months))
		{
			$mybb->input['endtime_month'] = 1;
		}
		$enddate = gmmktime(intval($enddate[0]), intval($enddate[1]), 0, (int)$mybb->input['endtime_month'], intval($mybb->input['endtime_day']), intval($mybb->input['endtime_year']));
		if(!checkdate(intval($mybb->input['endtime_month']), intval($mybb->input['endtime_day']), intval($mybb->input['endtime_year'])) || $enddate < 0 || $enddate == false)
		{
			$errors[] = $lang->error_invalid_end_date;
		}
		elseif($enddate <= $startdate)
		{
			$errors[] = $lang->error_end_before_start;
		}
	}
	
	$plugins->run_hooks("modcp_do_edit_announcement_start");

	// Proceed to update if no errors
	if(!$errors)
	{
		$update_announcement = array(
			'uid' => $mybb->user['uid'],
			'subject' => $db->escape_string($mybb->input['title']),
			'message' => $db->escape_string($mybb->input['message']),
			'startdate' => $startdate,
			'enddate' => $enddate,
			'allowhtml' => $db->escape_string($mybb->input['allowhtml']),
			'allowmycode' => $db->escape_string($mybb->input['allowmycode']),
			'allowsmilies' => $db->escape_string($mybb->input['allowsmilies']),
		);

		$db->update_query("announcements", $update_announcement, "aid='{$aid}'");
		
		$plugins->run_hooks("modcp_do_edit_announcement_end");
		
		$cache->update_forumsdisplay();
		redirect("modcp.php?action=announcements", $lang->redirect_edit_announcement);
	}
	else
	{
		$mybb->input['action'] = 'edit_announcement';
	}
}

if($mybb->input['action'] == "edit_announcement")
{
	$announcement_fid = intval($mybb->input['fid']);
	$aid = intval($mybb->input['aid']);

	add_breadcrumb($lang->mcp_nav_announcements, "modcp.php?action=announcements");
	add_breadcrumb($lang->edit_announcement, "modcp.php?action=edit_announcements&amp;aid={$aid}");

	// Get announcement
	$query = $db->simple_select("announcements", "*", "aid='{$aid}'");
	$announcement = $db->fetch_array($query);

	if(!$announcement['fid'])
	{
		error($lang->error_invalid_announcement);
	}
	if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'])) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	if(!$announcement['startdate'])
	{
		// No start date? Make it now.
		$announcement['startdate'] = TIME_NOW;
	}

	$makeshift_end = false;
	if(!$announcement['enddate'])
	{
		$makeshift_end = true;
		$makeshift_time = TIME_NOW;
		if($announcement['startdate'])
		{
			$makeshift_time = $announcement['startdate'];
		}

		// No end date? Make it a year from now.
		$announcement['enddate'] = $makeshift_time + (60 * 60 * 24 * 366);
	}

	// Deal with inline errors
	if(is_array($errors))
	{
		$errors = inline_error($errors);

		// Set $announcement to input stuff
		$announcement['subject'] = $mybb->input['title'];
		$announcement['message'] = $mybb->input['message'];
		$announcement['allowhtml'] = $mybb->input['allowhtml'];
		$announcement['allowmycode'] = $mybb->input['allowmycode'];
		$announcement['allowsmilies'] = $mybb->input['allowsmilies'];
		
		$months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');			
		if(!in_array($mybb->input['starttime_month'], $months))
		{
			$mybb->input['starttime_month'] = 1;
		}
		
		if(!in_array($mybb->input['endtime_month'], $months))
		{
			$mybb->input['endtime_month'] = 1;
		}
		
		$startmonth = $mybb->input['starttime_month'];
		$startdateyear = htmlspecialchars_uni($mybb->input['starttime_year']);
		$startday = intval($mybb->input['starttime_day']);
		$starttime_time = htmlspecialchars($mybb->input['starttime_time']);
		$endmonth = $mybb->input['endtime_month'];
		$enddateyear = htmlspecialchars_uni($mybb->input['endtime_year']);
		$endday = intval($mybb->input['endtime_day']);
		$endtime_time = htmlspecialchars($mybb->input['endtime_time']);

		$errored = true;
	}
	else
	{
		// Note: dates are in GMT timezone
		$starttime_time = gmdate('g:i a', $announcement['startdate']);
		$endtime_time = gmdate('g:i a', $announcement['enddate']);

		$startday = gmdate('j', $announcement['startdate']);
		$endday = gmdate('j', $announcement['enddate']);

		$startmonth = gmdate('m', $announcement['startdate']);
		$endmonth = gmdate('m', $announcement['enddate']);

		$startdateyear = gmdate('Y', $announcement['startdate']);
		$enddateyear = gmdate('Y', $announcement['enddate']);

		$errored = false;
	}

	// Generate form elements
	for($i = 1; $i <= 31; ++$i)
	{
		if($startday == $i)
		{
			$startdateday .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$startdateday .= "<option value=\"$i\">$i</option>\n";
		}

		if($endday == $i)
		{
			$enddateday .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$enddateday .= "<option value=\"$i\">$i</option>\n";
		}
	}

	$startmonthsel = $endmonthsel = array();
	$startmonthsel[$startmonth] = "selected=\"selected\"";
	$endmonthsel[$endmonth] = "selected=\"selected\"";

	$startdatemonth .= "<option value=\"01\" {$startmonthsel['01']}>{$lang->january}</option>\n";
	$enddatemonth .= "<option value=\"01\" {$endmonthsel['01']}>{$lang->january}</option>\n";
	$startdatemonth .= "<option value=\"02\" {$startmonthsel['02']}>{$lang->february}</option>\n";
	$enddatemonth .= "<option value=\"02\" {$endmonthsel['02']}>{$lang->february}</option>\n";
	$startdatemonth .= "<option value=\"03\" {$startmonthsel['03']}>{$lang->march}</option>\n";
	$enddatemonth .= "<option value=\"03\" {$endmonthsel['03']}>{$lang->march}</option>\n";
	$startdatemonth .= "<option value=\"04\" {$startmonthsel['04']}>{$lang->april}</option>\n";
	$enddatemonth .= "<option value=\"04\" {$endmonthsel['04']}>{$lang->april}</option>\n";
	$startdatemonth .= "<option value=\"05\" {$startmonthsel['05']}>{$lang->may}</option>\n";
	$enddatemonth .= "<option value=\"05\" {$endmonthsel['05']}>{$lang->may}</option>\n";
	$startdatemonth .= "<option value=\"06\" {$startmonthsel['06']}>{$lang->june}</option>\n";
	$enddatemonth .= "<option value=\"06\" {$endmonthsel['06']}>{$lang->june}</option>\n";
	$startdatemonth .= "<option value=\"07\" {$startmonthsel['07']}>{$lang->july}</option>\n";
	$enddatemonth .= "<option value=\"07\" {$endmonthsel['07']}>{$lang->july}</option>\n";
	$startdatemonth .= "<option value=\"08\" {$startmonthsel['08']}>{$lang->august}</option>\n";
	$enddatemonth .= "<option value=\"08\" {$endmonthsel['08']}>{$lang->august}</option>\n";
	$startdatemonth .= "<option value=\"09\" {$startmonthsel['09']}>{$lang->september}</option>\n";
	$enddatemonth .= "<option value=\"09\" {$endmonthsel['09']}>{$lang->september}</option>\n";
	$startdatemonth .= "<option value=\"10\" {$startmonthsel['10']}>{$lang->october}</option>\n";
	$enddatemonth .= "<option value=\"10\" {$endmonthsel['10']}>{$lang->october}</option>\n";
	$startdatemonth .= "<option value=\"11\" {$startmonthsel['11']}>{$lang->november}</option>\n";
	$enddatemonth .= "<option value=\"11\" {$endmonthsel['11']}>{$lang->november}</option>\n";
	$startdatemonth .= "<option value=\"12\" {$startmonthsel['12']}>{$lang->december}</option>\n";
	$enddatemonth .= "<option value=\"12\" {$endmonthsel['12']}>{$lang->december}</option>\n";

	$title = htmlspecialchars_uni($announcement['subject']);
	$message = htmlspecialchars_uni($announcement['message']);

	$html_sel = $mycode_sel = $smilies_sel = array();
	if($announcement['allowhtml'])
	{
		$html_sel['yes'] = ' checked="checked"';
	}
	else
	{
		$html_sel['no'] = ' checked="checked"';
	}

	if($announcement['allowmycode'])
	{
		$mycode_sel['yes'] = ' checked="checked"';
	}
	else
	{
		$mycode_sel['no'] = ' checked="checked"';
	}

	if($announcement['allowsmilies'])
	{
		$smilies_sel['yes'] = ' checked="checked"';
	}
	else
	{
		$smilies_sel['no'] = ' checked="checked"';
	}

	if(($errored && $mybb->input['endtime_type'] == 2) || (!$errored && intval($announcement['enddate']) == 0) || $makeshift_end == true)
	{
		$end_type_sel['infinite'] = ' checked="checked"';
	}
	else
	{
		$end_type_sel['finite'] = ' checked="checked"';
	}

	// MyCode editor
	$codebuttons = build_mycode_inserter();
	$smilieinserter = build_clickable_smilies();
	
	$plugins->run_hooks("modcp_edit_announcement");

	eval("\$announcements = \"".$templates->get("modcp_announcements_edit")."\";");
	output_page($announcements);
}

if($mybb->input['action'] == "announcements")
{
	add_breadcrumb($lang->mcp_nav_announcements, "modcp.php?action=announcements");

	// Fetch announcements into their proper arrays
	$query = $db->simple_select("announcements", "aid, fid, subject, enddate");
	while($announcement = $db->fetch_array($query))
	{
		if($announcement['fid'] == -1)
		{
			$global_announcements[$announcement['aid']] = $announcement;
			continue;
		}
		$announcements[$announcement['fid']][$announcement['aid']] = $announcement;
	}

	if($mybb->usergroup['issupermod'] == 1)
	{
		if($global_announcements && $mybb->usergroup['issupermod'] == 1)
		{
			// Get the global announcements
			foreach($global_announcements as $aid => $announcement)
			{
				$trow = alt_trow();
				if($announcement['startdate'] > TIME_NOW || ($announcement['enddate'] < TIME_NOW && $announcement['enddate'] != 0))
				{
					$icon = "<img src=\"{$theme['imgdir']}/minioff.gif\" alt=\"({$lang->expired})\" title=\"{$lang->expired_announcement}\"  style=\"vertical-align: middle;\" /> ";
				}
				else
				{
					$icon = "<img src=\"{$theme['imgdir']}/minion.gif\" alt=\"({$lang->active})\" title=\"{$lang->active_announcement}\"  style=\"vertical-align: middle;\" /> ";
				}

				$subject = htmlspecialchars_uni($announcement['subject']);

				eval("\$announcements_global .= \"".$templates->get("modcp_announcements_announcement_global")."\";");
			}
		}
		else
		{
			// No global announcements
			eval("\$announcements_global = \"".$templates->get("modcp_no_announcements_global")."\";");
		}
		eval("\$announcements_global = \"".$templates->get("modcp_announcements_global")."\";");
	}
	else
	{
		// Moderator is not super, so don't show global annnouncemnets
		$announcements_global = '';
	}

	fetch_forum_announcements();

	if(!$announcements_forum)
	{
		eval("\$announcements_forum = \"".$templates->get("modcp_no_announcements_forum")."\";");
	}
	
	$plugins->run_hooks("modcp_announcements");
	
	eval("\$announcements = \"".$templates->get("modcp_announcements")."\";");
	output_page($announcements);
}

if($mybb->input['action'] == "do_modqueue")
{
	require_once MYBB_ROOT."inc/class_moderation.php";
	$moderation = new Moderation;

	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);
	
	$plugins->run_hooks("modcp_do_modqueue_start");

	if(is_array($mybb->input['threads']))
	{
		// Fetch threads
		$query = $db->simple_select("threads", "tid", "tid IN (".implode(",", array_map("intval", array_keys($mybb->input['threads'])))."){$flist}");
		while($thread = $db->fetch_array($query))
		{
			$action = $mybb->input['threads'][$thread['tid']];
			if($action == "approve")
			{
				$threads_to_approve[] = $thread['tid'];
			}
			else if($action == "delete")
			{
				$threads_to_delete[] = $thread['tid'];
			}
		}
		if(!empty($threads_to_approve))
		{
			$moderation->approve_threads($threads_to_approve);
			log_moderator_action(array('tids' => $threads_to_approve), $lang->multi_approve_threads);
		}
		if(!empty($threads_to_delete))
		{
			foreach($threads_to_delete as $tid)
			{
				$moderation->delete_thread($tid);
			}
			log_moderator_action(array('tids' => $threads_to_delete), $lang->multi_delete_threads);
		}
		
		$plugins->run_hooks("modcp_do_modqueue_end");
		
		redirect("modcp.php?action=modqueue", $lang->redirect_threadsmoderated);
	}
	else if(is_array($mybb->input['posts']))
	{
		// Fetch posts
		$query = $db->simple_select("posts", "pid", "pid IN (".implode(",", array_map("intval", array_keys($mybb->input['posts'])))."){$flist}");
		while($post = $db->fetch_array($query))
		{
			$action = $mybb->input['posts'][$post['pid']];
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
		log_moderator_action(array('pids' => $posts_to_approve), $lang->multi_approve_posts);
		
		$plugins->run_hooks("modcp_do_modqueue_end");
		
		redirect("modcp.php?action=modqueue&type=posts", $lang->redirect_postsmoderated);
	}
	else if(is_array($mybb->input['attachments']))
	{
		$query = $db->query("
			SELECT a.pid, a.aid
			FROM  ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE aid IN (".implode(",", array_map("intval", array_keys($mybb->input['attachments'])))."){$tflist}
		");
		while($attachment = $db->fetch_array($query))
		{
			$action = $mybb->input['attachments'][$attachment['aid']];
			if($action == "approve")
			{
				$db->update_query("attachments", array("visible" => 1), "aid='{$attachment['aid']}'");
			}
			else if($action == "delete")
			{
				remove_attachment($attachment['pid'], '', $attachment['aid']);
			}
		}
		
		$plugins->run_hooks("modcp_do_modqueue_end");
		
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

		$perpage = $mybb->settings['threadsperpage'];
		$pages = $unapproved_threads / $perpage;
		$pages = ceil($pages);

		if($mybb->input['page'] == "last")
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

		$multipage = multipage($unapproved_threads, $perpage, $page, "modcp.php?action=modqueue&type=threads");

		$query = $db->query("
			SELECT t.tid, t.dateline, t.fid, t.subject, p.message AS postmessage, u.username AS username, t.uid
			FROM ".TABLE_PREFIX."threads t
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=t.firstpost)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
			WHERE t.visible='0' {$tflist}
			ORDER BY t.lastpost DESC
			LIMIT {$start}, {$perpage}
		");
		while($thread = $db->fetch_array($query))
		{
			$altbg = alt_trow();
			$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
			$thread['threadlink'] = get_thread_link($thread['tid']);
			$thread['forumlink'] = get_forum_link($thread['fid']);
			$forum_name = $forum_cache[$thread['fid']]['name'];
			$threaddate = my_date($mybb->settings['dateformat'], $thread['dateline']);
			$threadtime = my_date($mybb->settings['timeformat'], $thread['dateline']);
			$profile_link = build_profile_link($thread['username'], $thread['uid']);
			$thread['postmessage'] = nl2br(htmlspecialchars_uni($thread['postmessage']));
			$forum = "<strong>{$lang->meta_forum} <a href=\"{$thread['forumlink']}\">{$forum_name}</a></strong>";
			eval("\$threads .= \"".$templates->get("modcp_modqueue_threads_thread")."\";");
		}

		if(!$threads && $mybb->input['type'] == "threads")
		{
			eval("\$threads = \"".$templates->get("modcp_modqueue_threads_empty")."\";");
		}

		if($threads)
		{
			add_breadcrumb($lang->mcp_nav_modqueue_threads, "modcp.php?action=modqueue&amp;type=threads");
			
			$plugins->run_hooks("modcp_modqueue_threads_end");
			
			eval("\$mass_controls = \"".$templates->get("modcp_modqueue_masscontrols")."\";");
			eval("\$threadqueue = \"".$templates->get("modcp_modqueue_threads")."\";");
			output_page($threadqueue);
		}
		$type = 'threads';
	}

	if($mybb->input['type'] == "posts" || (!$mybb->input['type'] && !$threadqueue))
	{
		$forum_cache = $cache->read("forums");

		$query = $db->query("
			SELECT COUNT(pid) AS unapprovedposts
			FROM  ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.visible='0' {$tflist} AND t.firstpost != p.pid
		");
		$unapproved_posts = $db->fetch_field($query, "unapprovedposts");

		// Figure out if we need to display multiple pages.
		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}

		$perpage = $mybb->settings['postsperpage'];
		$pages = $unapproved_posts / $perpage;
		$pages = ceil($pages);

		if($mybb->input['page'] == "last")
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

		$multipage = multipage($unapproved_posts, $perpage, $page, "modcp.php?action=modqueue&amp;type=posts");

		$query = $db->query("
			SELECT p.pid, p.subject, p.message, t.subject AS threadsubject, t.tid, u.username, p.uid, t.fid, p.dateline
			FROM  ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE p.visible='0' {$tflist} AND t.firstpost != p.pid
			ORDER BY p.dateline DESC
			LIMIT {$start}, {$perpage}
		");
		while($post = $db->fetch_array($query))
		{
			$altbg = alt_trow();
			$post['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($post['threadsubject']));
			$post['threadlink'] = get_thread_link($post['tid']);
			$post['forumlink'] = get_forum_link($post['fid']);
			$post['postlink'] = get_post_link($post['pid'], $post['tid']);
			$forum_name = $forum_cache[$post['fid']]['name'];
			$postdate = my_date($mybb->settings['dateformat'], $post['dateline']);
			$posttime = my_date($mybb->settings['timeformat'], $post['dateline']);
			$profile_link = build_profile_link($post['username'], $post['uid']);
			$thread = "<strong>{$lang->meta_thread} <a href=\"{$post['threadlink']}\">{$post['threadsubject']}</a></strong>";
			$forum = "<strong>{$lang->meta_forum} <a href=\"{$post['forumlink']}\">{$forum_name}</a></strong><br />";
			$post['message'] = nl2br(htmlspecialchars_uni($post['message']));
			eval("\$posts .= \"".$templates->get("modcp_modqueue_posts_post")."\";");
		}

		if(!$posts && $mybb->input['type'] == "posts")
		{
			eval("\$posts = \"".$templates->get("modcp_modqueue_posts_empty")."\";");
		}

		if($posts)
		{
			add_breadcrumb($lang->mcp_nav_modqueue_posts, "modcp.php?action=modqueue&amp;type=posts");
			
			$plugins->run_hooks("modcp_modqueue_posts_end");
			
			eval("\$mass_controls = \"".$templates->get("modcp_modqueue_masscontrols")."\";");
			eval("\$postqueue = \"".$templates->get("modcp_modqueue_posts")."\";");
			output_page($postqueue);
		}
	}

	if($mybb->input['type'] == "attachments" || (!$mybb->input['type'] && !$postqueue && !$threadqueue))
	{
		$query = $db->query("
			SELECT COUNT(aid) AS unapprovedattachments
			FROM  ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE a.visible='0' {$tflist}
		");
		$unapproved_attachments = $db->fetch_field($query, "unapprovedattachments");

		// Figure out if we need to display multiple pages.
		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}

		$perpage = $mybb->settings['postsperpage'];
		$pages = $unapproved_attachments / $perpage;
		$pages = ceil($pages);

		if($mybb->input['page'] == "last")
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

		$multipage = multipage($unapproved_attachments, $perpage, $page, "modcp.php?action=modqueue&amp;type=attachments");

		$query = $db->query("
			SELECT a.*, p.subject AS postsubject, p.dateline, p.uid, u.username, t.tid, t.subject AS threadsubject
			FROM  ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE a.visible='0'
			ORDER BY a.dateuploaded DESC
			LIMIT {$start}, {$perpage}
		");
		while($attachment = $db->fetch_array($query))
		{
			$altbg = alt_trow();

			if(!$attachment['dateuploaded'])
			{
				$attachment['dateuploaded'] = $attachment['dateline'];
			}
			
			$attachdate = my_date($mybb->settings['dateformat'], $attachment['dateuploaded']);
			$attachtime = my_date($mybb->settings['timeformat'], $attachment['dateuploaded']);

			$attachment['postsubject'] = htmlspecialchars_uni($attachment['postsubject']);
			$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
			$attachment['threadsubject'] = htmlspecialchars_uni($attachment['threadsubject']);
			$attachment['filesize'] = get_friendly_size($attachment['filesize']);

			$link = get_post_link($attachment['pid'], $attachment['tid']) . "#pid{$attachment['pid']}";
			$thread_link = get_thread_link($attachment['tid']);
			$profile_link = build_profile_link($attachment['username'], $attachment['uid']);

			eval("\$attachments .= \"".$templates->get("modcp_modqueue_attachments_attachment")."\";");
		}

		if(!$attachments && $mybb->input['type'] == "attachments")
		{
			eval("\$attachments = \"".$templates->get("modcp_modqueue_attachments_empty")."\";");
		}

		if($attachments)
		{
			add_breadcrumb($lang->mcp_nav_modqueue_attachments, "modcp.php?action=modqueue&amp;type=attachments");
			
			$plugins->run_hooks("modcp_modqueue_attachments_end");
			
			eval("\$mass_controls = \"".$templates->get("modcp_modqueue_masscontrols")."\";");
			eval("\$attachmentqueue = \"".$templates->get("modcp_modqueue_attachments")."\";");
			output_page($attachmentqueue);
		}
	}

	// Still nothing? All queues are empty! :-D
	if(!$threadqueue && !$postqueue && !$attachmentqueue)
	{
		add_breadcrumb($lang->mcp_nav_modqueue, "modcp.php?action=modqueue");
		
		$plugins->run_hooks("modcp_modqueue_end");
		
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
	if(!modcp_can_manage_user($user['uid']))
	{
		error_no_permission();
	}
	
	$plugins->run_hooks("modcp_do_editprofile_start");

	// Set up user handler.
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler('update');

	// Set the data for the new user.
	$updated_user = array(
		"uid" => $mybb->input['uid'],
		"profile_fields" => $mybb->input['profile_fields'],
		"profile_fields_editable" => true,
		"website" => $mybb->input['website'],
		"icq" => $mybb->input['icq'],
		"aim" => $mybb->input['aim'],
		"yahoo" => $mybb->input['yahoo'],
		"msn" => $mybb->input['msn'],
		"signature" => $mybb->input['signature'],
		"usernotes" => $mybb->input['usernotes']
	);

	$updated_user['birthday'] = array(
		"day" => $mybb->input['birthday_day'],
		"month" => $mybb->input['birthday_month'],
		"year" => $mybb->input['birthday_year']
	);

	if($mybb->input['usertitle'] != '')
	{
		$updated_user['usertitle'] = $mybb->input['usertitle'];
	}
	else if($mybb->input['reverttitle'])
	{
		$updated_user['usertitle'] = '';
	}

	if($mybb->input['remove_avatar'])
	{
		$updated_user['avatarurl'] = '';
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
			remove_avatars($user['uid']);
		}

		// Moderator "Options" (suspend signature, suspend/moderate posting)
		$moderator_options = array(
			1 => array(
				"action" => "suspendsignature", // The moderator action we're performing
				"period" => "action_period", // The time period we've selected from the dropdown box
				"time" => "action_time", // The time we've entered
				"update_field" => "suspendsignature", // The field in the database to update if true
				"update_length" => "suspendsigtime" // The length of suspension field in the database
			),
			2 => array(
				"action" => "moderateposting",
				"period" => "modpost_period",
				"time" => "modpost_time",
				"update_field" => "moderateposts",
				"update_length" => "moderationtime"
			),
			3 => array(
				"action" => "suspendposting",
				"period" => "suspost_period",
				"time" => "suspost_time",
				"update_field" => "suspendposting",
				"update_length" => "suspensiontime"
			)
		);

		require_once MYBB_ROOT."inc/functions_warnings.php";
		foreach($moderator_options as $option)
		{
			if(!$mybb->input[$option['action']])
			{
				if($user[$option['update_field']] == 1)
				{
					// We're revoking the suspension
					$extra_user_updates[$option['update_field']] = 0;
					$extra_user_updates[$option['update_length']] = 0;
				}

				// Skip this option if we haven't selected it
				continue;
			}

			if($mybb->input[$option['action']])
			{
				if(intval($mybb->input[$option['time']]) == 0 && $mybb->input[$option['period']] != "never" && $user[$option['update_field']] != 1)
				{
					// User has selected a type of ban, but not entered a valid time frame
					$string = $option['action']."_error";
					$errors[] = $lang->$string;
				}

				if(!is_array($errors))
				{
					$suspend_length = fetch_time_length(intval($mybb->input[$option['time']]), $mybb->input[$option['period']]);

					if($user[$option['update_field']] == 1 && ($mybb->input[$option['time']] || $mybb->input[$option['period']] == "never"))
					{
						// We already have a suspension, but entered a new time
						if($suspend_length == "-1")
						{
							// Permanent ban on action
							$extra_user_updates[$option['update_length']] = 0;
						}
						elseif($suspend_length && $suspend_length != "-1")
						{
							// Temporary ban on action
							$extra_user_updates[$option['update_length']] = TIME_NOW + $suspend_length;
						}
					}
					elseif(!$user[$option['update_field']])
					{
						// New suspension for this user... bad user!
						$extra_user_updates[$option['update_field']] = 1;				
						if($suspend_length == "-1")
						{
							$extra_user_updates[$option['update_length']] = 0;
						}
						else
						{
							$extra_user_updates[$option['update_length']] = TIME_NOW + $suspend_length;
						}
					}
				}
			}
		}

		// Those with javascript turned off will be able to select both - cheeky!
		// Check to make sure we're not moderating AND suspending posting
		if($extra_user_updates['moderateposts'] && $extra_user_updates['suspendposting'])
		{
			$errors[] = $lang->suspendmoderate_error;
		}

		if(is_array($errors))
		{
			$mybb->input['action'] = "editprofile";
		}
		else
		{
			$plugins->run_hooks("modcp_do_editprofile_update");
			
			// Continue with the update if there is no errors
			$user_info = $userhandler->update_user();
			$db->update_query("users", $extra_user_updates, "uid='{$user['uid']}'");
			log_moderator_action(array("uid" => $user['uid'], "username" => $user['username']), $lang->edited_user);
			
			$plugins->run_hooks("modcp_do_editprofile_end");
			
			redirect("modcp.php?action=finduser", $lang->redirect_user_updated);
		}
	}
}

if($mybb->input['action'] == "editprofile")
{
	add_breadcrumb($lang->mcp_nav_editprofile, "modcp.php?action=editprofile");

	$user = get_user($mybb->input['uid']);
	if(!$user['uid'])
	{
		error($lang->invalid_user);
	}

	// Check if the current user has permission to edit this user
	if(!modcp_can_manage_user($user['uid']))
	{
		error_no_permission();
	}

	if(validate_website_format($user['website']))
	{
		$user['website'] = htmlspecialchars_uni($user['website']);
	}
	else
	{
		$user['website'] = '';
	}

	$user['icq'] = (int)$user['icq'];
	if(!$user['icq'])
	{
		$user['icq'] = '';
	}

	$user['msn'] = htmlspecialchars_uni($user['msn']);
	$user['aim'] = htmlspecialchars_uni($user['aim']);
	$user['yahoo'] = htmlspecialchars_uni($user['yahoo']);

	if(!$errors)
	{
		$mybb->input = array_merge($user, $mybb->input);
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

	// Custom user title, check to see if we have a default group title
	if(!$user['displaygroup'])
	{
		$user['displaygroup'] = $user['usergroup'];
	}

	$displaygroupfields = array('usertitle');
	$display_group = usergroup_displaygroup($user['displaygroup']);

	if(!empty($display_group['usertitle']))
	{
		$defaulttitle = $display_group['usertitle'];
	}
	else
	{
		// Go for post count title if a group default isn't set
		$usertitles = $cache->read('usertitles');

		foreach($usertitles as $title)
		{
			if($title['posts'] <= $mybb->user['postnum'])
			{
				$defaulttitle = $title['title'];
			}
		}
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
	$bdaymonthsel[$mybb->input['birthday_month']] = 'selected="selected"';
	
	$plugins->run_hooks("modcp_editprofile_start");

	// Fetch profile fields
	$query = $db->simple_select("userfields", "*", "ufid='{$user['uid']}'");
	$user_fields = $db->fetch_array($query);

	$requiredfields = '';
	$customfields = '';
	$query = $db->simple_select("profilefields", "*", "", array('order_by' => 'disporder'));
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
			$maxlength = "";
			if($profilefield['maxlength'] > 0)
			{
				$maxlength = " maxlength=\"{$profilefield['maxlength']}\"";
			}
			$code = "<input type=\"text\" name=\"profile_fields[$field]\" class=\"textbox\" size=\"{$profilefield['length']}\"{$maxlength} value=\"$value\" />";
		}
		if($profilefield['required'] == 1)
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

	$lang->edit_profile = $lang->sprintf($lang->edit_profile, $user['username']);
	$profile_link = build_profile_link(format_name($user['username'], $user['usergroup'], $user['displaygroup']), $user['uid']);

	$codebuttons = build_mycode_inserter("signature");

	// Do we mark the suspend signature box?
	if($user['suspendsignature'] || ($mybb->input['suspendsignature'] && !empty($errors)))
	{
		$checked = 1;
		$checked_item = "checked=\"checked\"";
	}
	else
	{
		$checked = 0;
	}

	// Do we mark the moderate posts box?
	if($user['moderateposts'] || ($mybb->input['moderateposting'] && !empty($errors)))
	{
		$modpost_check = 1;
		$modpost_checked = "checked=\"checked\"";
	}
	else
	{
		$modpost_check = 0;
	}

	// Do we mark the suspend posts box?
	if($user['suspendposting'] || ($mybb->input['suspendposting'] && !empty($errors)))
	{
		$suspost_check = 1;
		$suspost_checked = "checked=\"checked\"";
	}
	else
	{
		$suspost_check = 0;
	}

	$moderator_options = array(
		1 => array(
			"action" => "suspendsignature", // The input action for this option
			"option" => "suspendsignature", // The field in the database that this option relates to
			"length" => "suspendsigtime", // The length of suspension field in the database
			"select_option" => "action" // The name of the select box of this option
		),
		2 => array(
			"action" => "moderateposting",
			"option" => "moderateposts",
			"length" => "moderationtime",
			"select_option" => "modpost"
		),
		3 => array(
			"action" => "suspendposting",
			"option" => "suspendposting",
			"length" => "suspensiontime",
			"select_option" => "suspost"
		)
	);

	$periods = array(
		"hours" => $lang->expire_hours,
		"days" => $lang->expire_days,
		"weeks" => $lang->expire_weeks,
		"months" => $lang->expire_months,
		"never" => $lang->expire_permanent
	);

	foreach($moderator_options as $option)
	{
		// Display the suspension info, if this user has this option suspended
		if($user[$option['option']])
		{
			if($user[$option['length']] == 0)
			{
				// User has a permanent ban
				$string = $option['option']."_perm";
				$suspension_info = $lang->$string;
			}
			else
			{
				// User has a temporary (or limited) ban
				$string = $option['option']."_for";
				$for_date = my_date($mybb->settings['dateformat'], $user[$option['length']]);
				$for_time = my_date($mybb->settings['timeformat'], $user[$option['length']]);
				$suspension_info = $lang->sprintf($lang->$string, $for_date, $for_time);
			}

			switch($option['option'])
			{
				case "suspendsignature":
					eval("\$suspendsignature_info = \"".$templates->get("modcp_editprofile_suspensions_info")."\";");
					break;
				case "moderateposts":
					eval("\$moderateposts_info = \"".$templates->get("modcp_editprofile_suspensions_info")."\";");
					break;
				case "suspendposting":
					eval("\$suspendposting_info = \"".$templates->get("modcp_editprofile_suspensions_info")."\";");
					break;
			}
		}

		// Generate the boxes for this option
		$selection_options = '';
		foreach($periods as $key => $value)
		{
			$string = $option['select_option']."_period";
			if($mybb->input[$string] == $key)
			{
				$selected = "selected=\"selected\"";
			}
			else
			{
				$selected = '';
			}

			eval("\$selection_options .= \"".$templates->get("modcp_editprofile_select_option")."\";");
		}

		$select_name = $option['select_option']."_period";
		switch($option['option'])
		{
			case "suspendsignature":
				eval("\$action_options = \"".$templates->get("modcp_editprofile_select")."\";");
				break;
			case "moderateposts":
				eval("\$modpost_options = \"".$templates->get("modcp_editprofile_select")."\";");
				break;
			case "suspendposting":
				eval("\$suspost_options = \"".$templates->get("modcp_editprofile_select")."\";");
				break;
		}
	}

	eval("\$suspend_signature = \"".$templates->get("modcp_editprofile_signature")."\";");
	
	$plugins->run_hooks("modcp_editprofile_end");

	eval("\$edituser = \"".$templates->get("modcp_editprofile")."\";");
	output_page($edituser);
}

if($mybb->input['action'] == "finduser")
{
	add_breadcrumb($lang->mcp_nav_users, "modcp.php?action=finduser");
	
	$perpage = intval($mybb->input['perpage']);
	if(!$perpage || $perpage <= 0)
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

	$page_url = 'modcp.php?action=finduser';
	foreach(array('username', 'sortby', 'order') as $field)
	{
		if($mybb->input[$field])
		{
			$page_url .= "&amp;{$field}=".htmlspecialchars_uni($mybb->input[$field]);
			$mybb->input[$field] = htmlspecialchars_uni($mybb->input[$field]);
		}
	}

	$multipage = multipage($user_count, $perpage, $page, $page_url);

	$usergroups_cache = $cache->read("usergroups");
	
	$plugins->run_hooks("modcp_finduser_start");

	// Fetch out results
	$query = $db->simple_select("users", "*", "1=1 {$where}", array("order_by" => $sortby, "order_dir" => $order, "limit" => $perpage, "limit_start" => $start));
	while($user = $db->fetch_array($query))
	{
		$alt_row = alt_trow();
		$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
		$user['postnum'] = my_number_format($user['postnum']);
		$regdate = my_date($mybb->settings['dateformat'], $user['regdate']);
		$regtime = my_date($mybb->settings['timeformat'], $user['regdate']);
		$lastdate = my_date($mybb->settings['dateformat'], $user['lastvisit']);
		$lasttime = my_date($mybb->settings['timeformat'], $user['lastvisit']);
		$usergroup = $usergroups_cache[$user['usergroup']]['title'];
		eval("\$users .= \"".$templates->get("modcp_finduser_user")."\";");
	}

	// No results?
	if(!$users)
	{
		eval("\$users = \"".$templates->get("modcp_finduser_noresults")."\";");
	}
	
	$plugins->run_hooks("modcp_finduser_end");

	eval("\$finduser = \"".$templates->get("modcp_finduser")."\";");
	output_page($finduser);
}

if($mybb->input['action'] == "warninglogs")
{
	add_breadcrumb($lang->mcp_nav_warninglogs, "modcp.php?action=warninglogs");

	// Filter options
	$where_sql = '';
	if($mybb->input['filter']['username'])
	{
		$search['username'] = $db->escape_string($mybb->input['filter']['username']);
		$query = $db->simple_select("users", "uid", "username='{$search['username']}'");
		$mybb->input['filter']['uid'] = $db->fetch_field($query, "uid");
		$mybb->input['filter']['username'] = htmlspecialchars_uni($mybb->input['filter']['username']);
	}
	if($mybb->input['filter']['uid'])
	{
		$search['uid'] = intval($mybb->input['filter']['uid']);
		$where_sql .= " AND w.uid='{$search['uid']}'";
		if(!isset($mybb->input['search']['username']))
		{
			$user = get_user($mybb->input['search']['uid']);
			$mybb->input['search']['username'] = htmlspecialchars_uni($user['username']);
		}
	}
	if($mybb->input['filter']['mod_username'])
	{
		$search['mod_username'] = $db->escape_string($mybb->input['filter']['mod_username']);
		$query = $db->simple_select("users", "uid", "username='{$search['mod_username']}'");
		$mybb->input['filter']['mod_uid'] = $db->fetch_field($query, "uid");
		$mybb->input['filter']['mod_username'] = htmlspecialchars_uni($mybb->input['filter']['mod_username']);
	}
	if($mybb->input['filter']['mod_uid'])
	{
		$search['mod_uid'] = intval($mybb->input['filter']['mod_uid']);
		$where_sql .= " AND w.issuedby='{$search['mod_uid']}'";
		if(!isset($mybb->input['search']['mod_username']))
		{
			$mod_user = get_user($mybb->input['search']['uid']);
			$mybb->input['search']['mod_username'] = htmlspecialchars_uni($mod_user['username']);
		}
	}
	if($mybb->input['filter']['reason'])
	{
		$search['reason'] = $db->escape_string($mybb->input['filter']['reason']);
		$where_sql .= " AND (w.notes LIKE '%{$search['reason']}%' OR t.title LIKE '%{$search['reason']}%' OR w.title LIKE '%{$search['reason']}%')";
		$mybb->input['filter']['reason'] = htmlspecialchars_uni($mybb->input['filter']['reason']);
	}
	$sortbysel = array();
	switch($mybb->input['filter']['sortby'])
	{
		case "username":
			$sortby = "u.username";
			$sortbysel['username'] = ' selected="selected"';
			break;
		case "expires":
			$sortby = "w.expires";
			$sortbysel['expires'] = ' selected="selected"';
			break;
		case "issuedby":
			$sortby = "i.username";
			$sortbysel['issuedby'] = ' selected="selected"';
			break;
		default: // "dateline"
			$sortby = "w.dateline";
			$sortbysel['dateline'] = ' selected="selected"';
	}
	$order = $mybb->input['filter']['order'];
	$ordersel = array();
	if($order != "asc")
	{
		$order = "desc";
		$ordersel['desc'] = ' selected="selected"';
	}
	else
	{
		$ordersel['asc'] = ' selected="selected"';
	}
	
	$plugins->run_hooks("modcp_warninglogs_start");

	// Pagination stuff
	$sql = "
		SELECT COUNT(wid) as count
		FROM
			".TABLE_PREFIX."warnings w
			LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (w.tid=t.tid)
		WHERE 1=1
			{$where_sql}
	";
	$query = $db->query($sql);
	$total_warnings = $db->fetch_field($query, 'count');
	$page = 1;
	if(isset($mybb->input['page']) && intval($mybb->input['page']) > 0)
	{
		$page = intval($mybb->input['page']);
	}
	$per_page = 20;
	if(isset($mybb->input['filter']['per_page']) && intval($mybb->input['filter']['per_page']) > 0)
	{
		$per_page = intval($mybb->input['filter']['per_page']);
	}
	$start = ($page-1) * $per_page;
	// Build the base URL for pagination links
	$url = 'modcp.php?action=warninglogs';
	if(is_array($mybb->input['filter']) && count($mybb->input['filter']))
	{
		foreach($mybb->input['filter'] as $field => $value)
		{
			$value = urlencode($value);
			$url .= "&amp;filter[{$field}]={$value}";
		}
	}
	$multipage = multipage($total_warnings, $per_page, $page, $url);

	// The actual query
	$sql = "
		SELECT
			w.wid, w.title as custom_title, w.points, w.dateline, w.issuedby, w.expires, w.expired, w.daterevoked, w.revokedby,
			t.title,
			u.uid, u.username, u.usergroup, u.displaygroup,
			i.uid as mod_uid, i.username as mod_username, i.usergroup as mod_usergroup, i.displaygroup as mod_displaygroup
		FROM ".TABLE_PREFIX."warnings w
			LEFT JOIN ".TABLE_PREFIX."users u ON (w.uid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (w.tid=t.tid)
			LEFT JOIN ".TABLE_PREFIX."users i ON (i.uid=w.issuedby)
		WHERE 1=1
			{$where_sql}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$per_page}
	";
	$query = $db->query($sql);


	$warning_list = '';
	while($row = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$username = format_name($row['username'], $row['usergroup'], $row['displaygroup']);
		$username_link = build_profile_link($username, $row['uid']);
		$mod_username = format_name($row['mod_username'], $row['mod_usergroup'], $row['mod_displaygroup']);
		$mod_username_link = build_profile_link($mod_username, $row['mod_uid']);
		$issued_date = my_date($mybb->settings['dateformat'], $row['dateline']).' '.my_date($mybb->settings['timeformat'], $row['dateline']);
		$revoked_text = '';
		if($row['daterevoked'] > 0)
		{
			$revoked_date = my_date($mybb->settings['dateformat'], $row['daterevoked']).' '.my_date($mybb->settings['timeformat'], $row['daterevoked']);
			eval("\$revoked_text = \"".$templates->get("modcp_warninglogs_warning_revoked")."\";");
		}
		if($row['expires'] > 0)
		{
			$expire_date = my_date($mybb->settings['dateformat'], $row['expires']).' '.my_date($mybb->settings['timeformat'], $row['expires']);
		}
		else
		{
			$expire_date = $lang->never;
		}
		$title = $row['title'];
		if(empty($row['title']))
		{
			$title = $row['custom_title'];
		}
		$title = htmlspecialchars_uni($title);
		if($row['points'] >= 0)
		{
			$points = '+'.$row['points'];
		}

		eval("\$warning_list .= \"".$templates->get("modcp_warninglogs_warning")."\";");
	}

	if(!$warning_list)
	{
		eval("\$warning_list = \"".$templates->get("modcp_warninglogs_nologs")."\";");
	}
	
	$plugins->run_hooks("modcp_warninglogs_end");

	eval("\$warninglogs = \"".$templates->get("modcp_warninglogs")."\";");
	output_page($warninglogs);
}

if($mybb->input['action'] == "ipsearch")
{
	add_breadcrumb($lang->mcp_nav_ipsearch, "modcp.php?action=ipsearch");

	if($mybb->input['ipaddress'])
	{
		if(!is_array($groupscache))
		{
			$groupscache = $cache->read("usergroups");
		}

		$ipaddressvalue = htmlspecialchars_uni($mybb->input['ipaddress']);

		// Searching post IP addresses
		if($mybb->input['search_posts'])
		{
			// IPv6 IP
			if(strpos($mybb->input['ipaddress'], ":") !== false)
			{
				$post_ip_sql = "ipaddress LIKE '".$db->escape_string(str_replace("*", "%", $mybb->input['ipaddress']))."'";
			}
			else
			{
				$ip_range = fetch_longipv4_range($mybb->input['ipaddress']);
				
				if($ip_range)
				{
					if(!is_array($ip_range))
					{
						$post_ip_sql = "longipaddress='{$ip_range}'";
					}
					else
					{
						$post_ip_sql = "longipaddress > '{$ip_range[0]}' AND longipaddress < '{$ip_range[1]}'";
					}
				}
			}

			$plugins->run_hooks("modcp_ipsearch_posts_start");

			if($post_ip_sql)
			{
				$query = $db->query("
					SELECT COUNT(pid) AS count
					FROM ".TABLE_PREFIX."posts
					WHERE {$post_ip_sql}
				");

				$post_results = $db->fetch_field($query, "count");
			}
		}

		// Searching user IP addresses
		if($mybb->input['search_users'])
		{
			// IPv6 IP
			if(strpos($mybb->input['ipaddress'], ":") !== false)
			{
				$user_ip_sql = "regip LIKE '".$db->escape_string(str_replace("*", "%", $mybb->input['ipaddress']))."' OR lastip LIKE '".$db->escape_string(str_replace("*", "%", $mybb->input['ipaddress']))."'";
			}
			else
			{
				$ip_range = fetch_longipv4_range($mybb->input['ipaddress']);

				if($ip_range)
				{
					if(!is_array($ip_range))
					{
						$user_ip_sql = "longregip='{$ip_range}' OR longlastip='{$ip_range}'";
					}
					else
					{
						$user_ip_sql = "(longregip > '{$ip_range[0]}' AND longregip < '{$ip_range[1]}') OR (longlastip > '{$ip_range[0]}' AND longlastip < '{$ip_range[1]}')";
					}
				}
			}

			$plugins->run_hooks("modcp_ipsearch_users_start");

			if($user_ip_sql)
			{
				$query = $db->query("
					SELECT COUNT(uid) AS count
					FROM ".TABLE_PREFIX."users
					WHERE {$user_ip_sql}
				");

				$user_results = $db->fetch_field($query, "count");
			}
		}

		$total_results = $post_results+$user_results;

		if(!$total_results)
		{
			$total_results = 1;
		}

		// Now we have the result counts, paginate
		$perpage = intval($mybb->input['perpage']);
		if(!$perpage || $perpage <= 0)
		{
			$perpage = $mybb->settings['threadsperpage'];
		}

		// Figure out if we need to display multiple pages.
		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}

		$pages = $total_results / $perpage;
		$pages = ceil($pages);

		if($mybb->input['page'] == "last")
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

		$page_url = "modcp.php?action=ipsearch&amp;perpage={$perpage}";
		foreach(array('ipaddress', 'search_users', 'search_posts') as $input)
		{
			if(!$mybb->input[$input]) continue;
			$page_url .= "&amp;{$input}=".htmlspecialchars_uni($mybb->input[$input]);
		}
		$multipage = multipage($total_results, $perpage, $page, $page_url);

		$post_limit = $perpage;
		if($mybb->input['search_users'] && $user_results && $start <= $user_results)
		{
			$query = $db->query("
				SELECT username, uid, regip, lastip
				FROM ".TABLE_PREFIX."users
				WHERE {$user_ip_sql}
				ORDER BY regdate DESC
				LIMIT {$start}, {$perpage}
			");
			while($ipaddress = $db->fetch_array($query))
			{
				$result = false;
				$profile_link = build_profile_link($ipaddress['username'], $ipaddress['uid']);
				$trow = alt_trow();
				$regexp_ip = str_replace("\*", "(.*)", preg_quote($mybb->input['ipaddress'], "#"));
				// Reg IP matches
				if(preg_match("#{$regexp_ip}#i", $ipaddress['regip']))
				{
					$ip = $ipaddress['regip'];
					$subject = "<strong>{$lang->ipresult_regip}</strong> {$profile_link}";
					eval("\$results .= \"".$templates->get("modcp_ipsearch_result")."\";");
					$result = true;
				}
				// Last known IP matches
				if(preg_match("#{$regexp_ip}#i", $ipaddress['lastip']))
				{
					$ip = $ipaddress['lastip'];
					$subject = "<strong>{$lang->ipresult_lastip}</strong> {$profile_link}";
					eval("\$results .= \"".$templates->get("modcp_ipsearch_result")."\";");
					$result = true;
				}

				if($result)
				{
					--$post_limit;
				}
			}
		}
		$post_start = 0;
		if($total_results > $user_results && $post_limit)
		{
			$post_start = $start-$user_results;
			if($post_start < 0)
			{
				$post_start = 0;
			}
		}
		if($mybb->input['search_posts'] && $post_results && (!$mybb->input['search_users'] || ($mybb->input['search_users'] && $post_limit > 0)))
		{
			$ipaddresses = $tids = $uids = array();
			$query = $db->query("
				SELECT username AS postusername, uid, subject, pid, tid, ipaddress
				FROM ".TABLE_PREFIX."posts
				WHERE {$post_ip_sql}
				ORDER BY dateline DESC
				LIMIT {$post_start}, {$post_limit}
			");
			while($ipaddress = $db->fetch_array($query))
			{
				$tids[$ipaddress['tid']] = $ipaddress['pid'];
				$uids[$ipaddress['uid']] = $ipaddress['pid'];
				$ipaddresses[$ipaddress['pid']] = $ipaddress;
			}
			
			if(!empty($ipaddresses))
			{
				$query = $db->simple_select("threads", "subject, tid", "tid IN(".implode(',', array_keys($tids)).")");
				while($thread = $db->fetch_array($query))
				{
					$ipaddresses[$tids[$thread['tid']]]['threadsubject'] = $thread['subject'];
				}
				unset($tids);
				
				$query = $db->simple_select("users", "username, uid", "uid IN(".implode(',', array_keys($uids)).")");
				while($user = $db->fetch_array($query))
				{
					$ipaddresses[$uids[$user['uid']]]['username'] = $user['username'];
				}
				unset($uids);
				
				foreach($ipaddresses as $ipaddress)
				{
					$ip = $ipaddress['ipaddress'];
					if(!$ipaddress['username']) $ipaddress['username'] = $ipaddress['postusername']; // Guest username support
					$trow = alt_trow();
					if(!$ipaddress['subject'])
					{
						$ipaddress['subject'] = "RE: {$ipaddress['threadsubject']}";
					}
					$subject = "<strong>{$lang->ipresult_post}</strong> <a href=\"".get_post_link($ipaddress['pid'], $ipaddress['tid'])."\">".htmlspecialchars_uni($ipaddress['subject'])."</a> {$lang->by} ".build_profile_link($ipaddress['username'], $ipaddress['uid']);
					eval("\$results .= \"".$templates->get("modcp_ipsearch_result")."\";");
				}
			}
		}

		if(!$results)
		{
			eval("\$results = \"".$templates->get("modcp_ipsearch_noresults")."\";");
		}

		if($ipaddressvalue)
		{
			$lang->ipsearch_results = $lang->sprintf($lang->ipsearch_results, $ipaddressvalue);
		}
		else
		{
			$lang->ipsearch_results = $lang->ipsearch;
		}
		
		if(!strstr($mybb->input['ipaddress'], "*") && !strstr($mybb->input['ipaddress'], ":"))
		{
			$misc_info_link = "<div class=\"float_right\">(<a href=\"modcp.php?action=iplookup&ipaddress=".htmlspecialchars_uni($mybb->input['ipaddress'])."\" onclick=\"MyBB.popupWindow('{$mybb->settings['bburl']}/modcp.php?action=iplookup&ipaddress=".urlencode($mybb->input['ipaddress'])."', 'iplookup', 500, 250); return false;\">{$lang->info_on_ip}</a>)</div>";
		}

		eval("\$ipsearch_results = \"".$templates->get("modcp_ipsearch_results")."\";");
	}

	// Fetch filter options
	if(!$mybb->input['ipaddress'])
	{
		$mybb->input['search_posts'] = 1;
		$mybb->input['search_users'] = 1;
	}
	if($mybb->input['search_posts'])
	{
		$postsearchselect = "checked=\"checked\"";
	}
	if($mybb->input['search_users'])
	{
		$usersearchselect = "checked=\"checked\"";
	}
	
	$plugins->run_hooks("modcp_ipsearch_end");

	eval("\$ipsearch = \"".$templates->get("modcp_ipsearch")."\";");
	output_page($ipsearch);
}

if($mybb->input['action'] == "iplookup")
{
	$lang->ipaddress_misc_info = $lang->sprintf($lang->ipaddress_misc_info, htmlspecialchars_uni($mybb->input['ipaddress']));
	$ipaddress_location = $lang->na;
	$ipaddress_host_name = $lang->na;
	$modcp_ipsearch_misc_info = '';
	if(!strstr($mybb->input['ipaddress'], "*") && !strstr($mybb->input['ipaddress'], ":"))
	{
		// Return GeoIP information if it is available to us
		if(function_exists('geoip_record_by_name'))
		{
			$ip_record = @geoip_record_by_name($mybb->input['ipaddress']);
			if($ip_record)
			{
				$ipaddress_location = htmlspecialchars_uni($ip_record['country_name']);
				if($ip_record['city'])
				{
					$ipaddress_location .= $lang->comma.htmlspecialchars_uni($ip_record['city']);
				}
			}
		}
		
		$ipaddress_host_name = htmlspecialchars_uni(@gethostbyaddr($mybb->input['ipaddress']));
		
		// gethostbyaddr returns the same ip on failure
		if($ipaddress_host_name == $mybb->input['ipaddress'])
		{
			$ipaddress_host_name = $lang->na;
		}
	}
	
	$plugins->run_hooks("modcp_iplookup_end");
	
	eval("\$iplookup = \"".$templates->get('modcp_ipsearch_misc_info')."\";");
	output_page($iplookup);
}

if($mybb->input['action'] == "banning")
{
	add_breadcrumb($lang->mcp_nav_banning, "modcp.php?action=banning");

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

	$query = $db->simple_select("banned", "COUNT(uid) AS count");
	$banned_count = $db->fetch_field($query, "count");

	$postcount = intval($banned_count);
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->input['page'] == "last")
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

	$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=banning");
	if($postcount > $perpage)
	{
		eval("\$allbannedpages = \"".$templates->get("modcp_banning_multipage")."\";");
	}
	
	$plugins->run_hooks("modcp_banning_start");

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
		$profile_link = build_profile_link($banned['username'], $banned['uid']);

		// Only show the edit & lift links if current user created ban, or is super mod/admin
		$edit_link = '';
		if($mybb->user['uid'] == $banned['admin'] || !$banned['adminuser'] || $mybb->usergroup['issupermod'] == 1 || $mybb->usergroup['cancp'] == 1)
		{
			$edit_link = "<br /><span class=\"smalltext\"><a href=\"modcp.php?action=banuser&amp;uid={$banned['uid']}\">{$lang->edit_ban}</a> | <a href=\"modcp.php?action=liftban&amp;uid={$banned['uid']}&amp;my_post_key={$mybb->post_code}\">{$lang->lift_ban}</a></span>";
		}

		$admin_profile = build_profile_link($banned['adminuser'], $banned['admin']);

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
			$remaining = $banned['lifted']-TIME_NOW;

			$timeremaining = nice_time($remaining, array('short' => 1, 'seconds' => false))."";

			if($remaining < 3600)
			{
				$timeremaining = "<span style=\"color: red;\">({$timeremaining} {$lang->ban_remaining})</span>";
			}
			else if($remaining < 86400)
			{
				$timeremaining = "<span style=\"color: maroon;\">({$timeremaining} {$lang->ban_remaining})</span>";
			}
			else if($remaining < 604800)
			{
				$timeremaining = "<span style=\"color: green;\">({$timeremaining} {$lang->ban_remaining})</span>";
			}
			else
			{
				$timeremaining = "({$timeremaining} {$lang->ban_remaining})";
			}
		}

		eval("\$bannedusers .= \"".$templates->get("modcp_banning_ban")."\";");
	}

	if(!$bannedusers)
	{
		eval("\$bannedusers = \"".$templates->get("modcp_banning_nobanned")."\";");
	}

	$plugins->run_hooks("modcp_banning");

	eval("\$bannedpage = \"".$templates->get("modcp_banning")."\";");
	output_page($bannedpage);
}

if($mybb->input['action'] == "liftban")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$query = $db->simple_select("banned", "*", "uid='".intval($mybb->input['uid'])."'");
	$ban = $db->fetch_array($query);

	if(!$ban['uid'])
	{
		error($lang->error_invalidban);
	}

	// Permission to edit this ban?
	if($mybb->user['uid'] != $ban['admin'] && $mybb->usergroup['issupermod'] != 1 && $mybb->usergroup['cancp'] != 1)
	{
		error_no_permission();
	}
	
	$plugins->run_hooks("modcp_liftban_start");

	$query = $db->simple_select("users", "username", "uid = '{$ban['uid']}'");
	$username = $db->fetch_field($query, "username");

	$updated_group = array(
		'usergroup' => $ban['oldgroup'],
		'additionalgroups' => $ban['oldadditionalgroups'],
		'displaygroup' => $ban['olddisplaygroup']
	);
	$db->update_query("users", $updated_group, "uid='{$ban['uid']}'");
	$db->delete_query("banned", "uid='{$ban['uid']}'");

	$cache->update_banned();
	$cache->update_moderators();
	log_moderator_action(array("uid" => $ban['uid'], "username" => $username), $lang->lifted_ban);
	
	$plugins->run_hooks("modcp_liftban_end");

	redirect("modcp.php?action=banning", $lang->redirect_banlifted);
}

if($mybb->input['action'] == "do_banuser" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	// Editing an existing ban
	if($mybb->input['uid'])
	{
		// Get the users info from their uid
		$query = $db->query("
			SELECT b.*, u.uid, u.usergroup, u.additionalgroups, u.displaygroup
			FROM ".TABLE_PREFIX."banned b
			LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid)
			WHERE b.uid='{$mybb->input['uid']}'
		");
		$user = $db->fetch_array($query);
		if(!$user['uid'])
		{
			error($lang->error_invalidban);
		}

		// Permission to edit this ban?
		if($mybb->user['uid'] != $user['admin'] && $mybb->usergroup['issupermod'] != 1 && $mybb->usergroup['cancp'] != 1)
		{
			error_no_permission();
		}
	}
	// Creating a new ban
	else
	{
		// Get the users info from their Username
		$query = $db->simple_select("users", "uid, username, usergroup, additionalgroups, displaygroup", "username = '".$db->escape_string($mybb->input['username'])."'", array('limit' => 1));
		$user = $db->fetch_array($query);
		if(!$user['uid'])
		{
			$errors[] = $lang->invalid_username;
		}
	}

	if($user['uid'] == $mybb->user['uid'])
	{
		$errors[] = $lang->error_cannotbanself;
	}

	// Have permissions to ban this user?
	if(!modcp_can_manage_user($user['uid']))
	{
		$errors[] = $lang->error_cannotbanuser;
	}

	// Check for an incoming reason
	if(!$mybb->input['banreason'])
	{
		$errors[] = $lang->error_nobanreason;
	}

	// Check banned group
	$query = $db->simple_select("usergroups", "gid", "isbannedgroup=1 AND gid='".intval($mybb->input['usergroup'])."'");
	if(!$db->fetch_field($query, "gid"))
	{
		$errors[] = $lang->error_nobangroup;
	}

	// If this is a new ban, we check the user isn't already part of a banned group
	if(!$mybb->input['uid'] && $user['uid'])
	{
		$query = $db->simple_select("banned", "uid", "uid='{$user['uid']}'");
		if($db->fetch_field($query, "uid"))
		{
			$errors[] = $lang->error_useralreadybanned;
		}
	}
	
	$plugins->run_hooks("modcp_do_banuser_start");

	// Still no errors? Ban the user
	if(!$errors)
	{
		// Ban the user
		if($mybb->input['liftafter'] == '---')
		{
			$lifted = 0;
		}
		else
		{
			$lifted = ban_date2timestamp($mybb->input['liftafter'], $user['dateline']);
		}

		if($mybb->input['uid'])
		{
			$username_select = $db->simple_select('users', 'username', "uid='" . (int)$mybb->input['uid'] . "'");
			$user['username'] = $db->fetch_field($username_select, 'username');
			$update_array = array(
				'gid' => intval($mybb->input['usergroup']),
				'admin' => intval($mybb->user['uid']),
				'dateline' => TIME_NOW,
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
				'dateline' => TIME_NOW,
				'bantime' => $db->escape_string($mybb->input['liftafter']),
				'lifted' => $db->escape_string($lifted),
				'reason' => $db->escape_string($mybb->input['banreason'])
			);

			$db->insert_query('banned', $insert_array);
		}

		// Move the user to the banned group
		$update_array = array(
			'usergroup' => intval($mybb->input['usergroup']),
			'displaygroup' => 0,
			'additionalgroups' => '',
		);
		$db->update_query('users', $update_array, "uid = {$user['uid']}");

		$cache->update_banned();

		// Log edit or add ban
		if($mybb->input['uid'])
		{
			log_moderator_action(array("uid" => $user['uid'], "username" => $user['username']), $lang->edited_user_ban);
		}
		else
		{
			log_moderator_action(array("uid" => $user['uid'], "username" => $user['username']), $lang->banned_user);
		}
		
		$plugins->run_hooks("modcp_do_banuser_end");

		if($mybb->input['uid'])
		{
			redirect("modcp.php?action=banning", $lang->redirect_banuser_updated);
		}
		else
		{
			redirect("modcp.php?action=banning", $lang->redirect_banuser);
		}
	}
	// Otherwise has errors, throw back to ban page
	else
	{
		$mybb->input['action'] = "banuser";
	}
}

if($mybb->input['action'] == "banuser")
{
	add_breadcrumb($lang->mcp_nav_banning, "modcp.php?action=banning");

	if($mybb->input['uid'])
	{
		add_breadcrumb($lang->mcp_nav_ban_user);
	}
	else
	{
		add_breadcrumb($lang->mcp_nav_editing_ban);
	}
	
	$plugins->run_hooks("modcp_banuser_start");

	// If incoming user ID, we are editing a ban
	if($mybb->input['uid'])
	{
		$query = $db->query("
			SELECT b.*, u.username, u.uid
			FROM ".TABLE_PREFIX."banned b
			LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid)
			WHERE b.uid='{$mybb->input['uid']}'
		");
		$banned = $db->fetch_array($query);
		if($banned['username'])
		{
			$username = htmlspecialchars_uni($banned['username']);
			$banreason = htmlspecialchars_uni($banned['reason']);
			$uid = $mybb->input['uid'];
			$user = get_user($banned['uid']);
			$lang->ban_user = $lang->edit_ban; // Swap over lang variables
			eval("\$banuser_username = \"".$templates->get("modcp_banuser_editusername")."\";");
		}
	}
	
	// New ban!
	if(!$banuser_username)
	{
		if($mybb->input['uid'])
		{
			$user = get_user($mybb->input['uid']);
			$username = $user['username'];
		}
		else
		{
			$username = htmlspecialchars_uni($mybb->input['username']);
		}
		eval("\$banuser_username = \"".$templates->get("modcp_banuser_addusername")."\";");
	}

	// Coming back to this page from an error?
	if($errors)
	{
		$errors = inline_error($errors);
		$banned = array(
			"bantime" => $mybb->input['liftafter'],
			"reason" => $mybb->input['reason'],
			"gid" => $mybb->input['gid']
		);
		$banreason = htmlspecialchars_uni($mybb->input['banreason']);
	}

	// Generate the banned times dropdown
	foreach($bantimes as $time => $title)
	{
		$liftlist .= "<option value=\"{$time}\"";
		if($banned['bantime'] == $time)
		{
			$liftlist .= " selected=\"selected\"";
		}
		$thatime = my_date("D, jS M Y @ g:ia", ban_date2timestamp($time, $banned['dateline']));
		if($time == '---')
		{
			$liftlist .= ">{$title}</option>\n";
		}
		else
		{
			$liftlist .= ">{$title} ({$thatime})</option>\n";
		}
	}
	
	$bangroups = '';
	$query = $db->simple_select("usergroups", "gid, title", "isbannedgroup=1");
	while($item = $db->fetch_array($query))
	{
		$selected = "";
		if($banned['gid'] == $item['gid'])
		{
			$selected = " selected=\"selected\"";
		}
		$bangroups .= "<option value=\"{$item['gid']}\"{$selected}>".htmlspecialchars_uni($item['title'])."</option>\n";
	}
	
	$lift_link = "<div class=\"float_right\"><a href=\"modcp.php?action=liftban&amp;uid={$user['uid']}&amp;my_post_key={$mybb->post_code}\">{$lang->lift_ban}</a></div>";
	
	$plugins->run_hooks("modcp_banuser_end");
	
	eval("\$banuser = \"".$templates->get("modcp_banuser")."\";");
	output_page($banuser);
}

if($mybb->input['action'] == "do_modnotes")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);
	
	$plugins->run_hooks("modcp_do_modnotes_start");
	
	// Update Moderator Notes cache
	$update_cache = array(
		"modmessage" => $mybb->input['modnotes']
	);
	$cache->update("modnotes", $update_cache);
	
	$plugins->run_hooks("modcp_do_modnotes_end");
	
	redirect("modcp.php", $lang->redirect_modnotes);
}

if(!$mybb->input['action'])
{
	$query = $db->query("
		SELECT COUNT(aid) AS unapprovedattachments
		FROM  ".TABLE_PREFIX."attachments a
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		WHERE a.visible='0' {$tflist}
	");
	$unapproved_attachments = $db->fetch_field($query, "unapprovedattachments");

	if($unapproved_attachments > 0)
	{
		$query = $db->query("
			SELECT t.tid, p.pid, p.uid, t.username, a.filename, a.dateuploaded
			FROM  ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE a.visible='0' {$tflist}
			ORDER BY a.dateuploaded DESC
			LIMIT 1
		");
		$attachment = $db->fetch_array($query);
		$attachment['date'] = my_date($mybb->settings['dateformat'], $attachment['dateuploaded']);
		$attachment['time'] = my_date($mybb->settings['timeformat'], $attachment['dateuploaded']);
		$attachment['profilelink'] = build_profile_link($attachment['username'], $attachment['uid']);
		$attachment['link'] = get_post_link($attachment['pid'], $attachment['tid']);
		$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);

		eval("\$latest_attachment = \"".$templates->get("modcp_lastattachment")."\";");
	}
	else
	{
		$latest_attachment = "<span style=\"text-align: center;\">{$lang->lastpost_never}</span>";
	}

	$query = $db->query("
		SELECT COUNT(pid) AS unapprovedposts
		FROM  ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		WHERE p.visible='0' {$tflist} AND t.firstpost != p.pid
	");
	$unapproved_posts = $db->fetch_field($query, "unapprovedposts");

	if($unapproved_posts > 0)
	{
		$query = $db->query("
			SELECT p.pid, p.tid, p.subject, p.uid, p.username, p.dateline
			FROM  ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.visible='0' {$tflist} AND t.firstpost != p.pid
			ORDER BY p.dateline DESC
			LIMIT 1
		");
		$post = $db->fetch_array($query);
		$post['date'] = my_date($mybb->settings['dateformat'], $post['dateline']);
		$post['time'] = my_date($mybb->settings['timeformat'], $post['dateline']);
		$post['profilelink'] = build_profile_link($post['username'], $post['uid']);
		$post['link'] = get_post_link($post['pid'], $post['tid']);
		$post['subject'] = $post['fullsubject'] = $parser->parse_badwords($post['subject']);
		if(my_strlen($post['subject']) > 25)
		{
			$post['subject'] = my_substr($post['subject'], 0, 25)."...";
		}
		$post['subject'] = htmlspecialchars_uni($post['subject']);
		$post['fullsubject'] = htmlspecialchars_uni($post['fullsubject']);

		eval("\$latest_post = \"".$templates->get("modcp_lastpost")."\";");
	}
	else
	{
		$latest_post =  "<span style=\"text-align: center;\">{$lang->lastpost_never}</span>";
	}

	$query = $db->simple_select("threads", "COUNT(tid) AS unapprovedthreads", "visible=0 {$flist}");
	$unapproved_threads = $db->fetch_field($query, "unapprovedthreads");

	if($unapproved_threads > 0)
	{
		$query = $db->simple_select("threads", "tid, subject, uid, username, dateline", "visible=0 {$flist}", array('order_by' =>  'dateline', 'order_dir' => 'DESC', 'limit' => 1));
		$thread = $db->fetch_array($query);
		$thread['date'] = my_date($mybb->settings['dateformat'], $thread['dateline']);
		$thread['time'] = my_date($mybb->settings['timeformat'], $thread['dateline']);
		$thread['profilelink'] = build_profile_link($thread['username'], $thread['uid']);
		$thread['link'] = get_thread_link($thread['tid']);
		$thread['subject'] = $thread['fullsubject'] = $parser->parse_badwords($thread['subject']);
		if(my_strlen($thread['subject']) > 25)
		{
			$post['subject'] = my_substr($thread['subject'], 0, 25)."...";
		}
		$thread['subject'] = htmlspecialchars_uni($thread['subject']);
		$thread['fullsubject'] = htmlspecialchars_uni($thread['fullsubject']);

		eval("\$latest_thread = \"".$templates->get("modcp_lastthread")."\";");
	}
	else
	{
		$latest_thread = "<span style=\"text-align: center;\">{$lang->lastpost_never}</span>";
	}

	$where = '';
	if($tflist)
	{
		$where = "WHERE (t.fid <> 0 {$tflist}) OR (!l.fid)";
	}

	$query = $db->query("
		SELECT l.*, u.username, u.usergroup, u.displaygroup, t.subject AS tsubject, f.name AS fname, p.subject AS psubject
		FROM ".TABLE_PREFIX."moderatorlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=l.fid)
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=l.pid)
		{$where}
		ORDER BY l.dateline DESC
		LIMIT 5
	");

	while($logitem = $db->fetch_array($query))
	{
		$information = '';
		$logitem['action'] = htmlspecialchars_uni($logitem['action']);
		$log_date = my_date($mybb->settings['dateformat'], $logitem['dateline']);
		$log_time = my_date($mybb->settings['timeformat'], $logitem['dateline']);
		$trow = alt_trow();
		$username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);
		$logitem['profilelink'] = build_profile_link($username, $logitem['uid']);
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
			$information .= "<strong>{$lang->post}</strong> <a href=\"".get_post_link($logitem['pid'])."#pid{$logitem['pid']}\">".htmlspecialchars_uni($logitem['psubject'])."</a>";
		}
		
		// Edited a user?
		if(!$logitem['tsubject'] || !$logitem['fname'] || !$logitem['psubject'])
		{
			$data = unserialize($logitem['data']);
			if($data['uid'])
			{
				$information = $lang->sprintf($lang->edited_user_info, htmlspecialchars_uni($data['username']), get_profile_link($data['uid']));
			}
		}

		eval("\$modlogresults .= \"".$templates->get("modcp_modlogs_result")."\";");
	}

	if(!$modlogresults)
	{
		eval("\$modlogresults = \"".$templates->get("modcp_modlogs_noresults")."\";");
	}

	$query = $db->query("
		SELECT b.*, a.username AS adminuser, u.username, (b.lifted-".TIME_NOW.") AS remaining
		FROM ".TABLE_PREFIX."banned b
		LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users a ON (b.admin=a.uid)
		WHERE b.bantime != '---' AND b.bantime != 'perm'
		ORDER BY remaining ASC
		LIMIT 5
	");

	// Get the banned users
	while($banned = $db->fetch_array($query))
	{
		$profile_link = build_profile_link($banned['username'], $banned['uid']);

		// Only show the edit & lift links if current user created ban, or is super mod/admin
		$edit_link = '';
		if($mybb->user['uid'] == $banned['admin'] || !$banned['adminuser'] || $mybb->usergroup['issupermod'] == 1 || $mybb->usergroup['cancp'] == 1)
		{
			$edit_link = "<br /><span class=\"smalltext\"><a href=\"modcp.php?action=banuser&amp;uid={$banned['uid']}\">{$lang->edit_ban}</a> | <a href=\"modcp.php?action=liftban&amp;uid={$banned['uid']}&amp;my_post_key={$mybb->post_code}\">{$lang->lift_ban}</a></span>";
		}

		$admin_profile = build_profile_link($banned['adminuser'], $banned['admin']);

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
			$remaining = $banned['remaining'];

			$timeremaining = nice_time($remaining, array('short' => 1, 'seconds' => false))."";

			if($remaining <= 0)
			{
				$timeremaining = "<span style=\"color: red;\">({$lang->ban_ending_imminently})</span>";
			}
			else if($remaining < 3600)
			{
				$timeremaining = "<span style=\"color: red;\">({$timeremaining} {$lang->ban_remaining})</span>";
			}
			else if($remaining < 86400)
			{
				$timeremaining = "<span style=\"color: maroon;\">({$timeremaining} {$lang->ban_remaining})</span>";
			}
			else if($remaining < 604800)
			{
				$timeremaining = "<span style=\"color: green;\">({$timeremaining} {$lang->ban_remaining})</span>";
			}
			else
			{
				$timeremaining = "({$timeremaining} {$lang->ban_remaining})";
			}
		}

		eval("\$bannedusers .= \"".$templates->get("modcp_banning_ban")."\";");
	}

	if(!$bannedusers)
	{
		eval("\$bannedusers = \"".$templates->get("modcp_banning_nobanned")."\";");
	}

	$modnotes = $cache->read("modnotes");
	$modnotes = htmlspecialchars_uni($modnotes['modmessage']);
	
	$plugins->run_hooks("modcp_end");

	eval("\$modcp = \"".$templates->get("modcp")."\";");
	output_page($modcp);
}
?>