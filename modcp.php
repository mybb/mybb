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
define('THIS_SCRIPT', 'modcp.php');

$templatelist = "modcp_reports,modcp_reports_report,modcp_reports_selectall,modcp_reports_multipage,modcp_reports_allreport,modcp_reports_allreports,modcp_modlogs_multipage,modcp_announcements_delete,modcp_announcements_edit,modcp_awaitingmoderation";
$templatelist .= ",modcp_reports_allnoreports,modcp_reports_noreports,modcp_banning,modcp_banning_ban,modcp_announcements_announcement_global,modcp_no_announcements_forum,modcp_modqueue_threads_thread,modcp_awaitingthreads,preview";
$templatelist .= ",modcp_banning_nobanned,modcp_modqueue_threads_empty,modcp_modqueue_masscontrols,modcp_modqueue_threads,modcp_modqueue_posts_post,modcp_modqueue_posts_empty,modcp_awaitingposts,modcp_nav_editprofile,modcp_nav_banning";
$templatelist .= ",modcp_nav,modcp_modlogs_noresults,modcp_modlogs_nologs,modcp,modcp_modqueue_posts,modcp_modqueue_attachments_attachment,modcp_modqueue_attachments_empty,modcp_modqueue_attachments,modcp_editprofile_suspensions_info";
$templatelist .= ",modcp_no_announcements_global,modcp_announcements_global,modcp_announcements_forum,modcp_announcements,modcp_editprofile_select_option,modcp_editprofile_select,modcp_finduser_noresults, modcp_nav_forums_posts";
$templatelist .= ",codebuttons,modcp_announcements_new,modcp_modqueue_empty,forumjump_bit,forumjump_special,modcp_warninglogs_warning_revoked,modcp_warninglogs_warning,modcp_ipsearch_result,modcp_nav_modqueue,modcp_banuser_liftlist";
$templatelist .= ",modcp_modlogs,modcp_finduser_user,modcp_finduser,usercp_profile_customfield,usercp_profile_profilefields,modcp_ipsearch_noresults,modcp_ipsearch_results,modcp_ipsearch_misc_info,modcp_nav_announcements,modcp_modqueue_post_link";
$templatelist .= ",modcp_editprofile,modcp_ipsearch,modcp_banuser_addusername,modcp_banuser,modcp_warninglogs_nologs,modcp_banuser_editusername,modcp_lastattachment,modcp_lastpost,modcp_lastthread,modcp_nobanned,modcp_modqueue_thread_link";
$templatelist .= ",modcp_warninglogs,modcp_modlogs_result,modcp_editprofile_signature,forumjump_advanced,modcp_announcements_forum_nomod,modcp_announcements_announcement,usercp_profile_away,modcp_modlogs_user,modcp_editprofile_away";
$templatelist .= ",multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start,modcp_awaitingattachments,modcp_modqueue_attachment_link";
$templatelist .= ",postbit_groupimage,postbit_userstar,postbit_online,postbit_offline,postbit_away,postbit_avatar,postbit_find,postbit_pm,postbit_email,postbit_www,postbit_author_user,announcement_edit,announcement_quickdelete";
$templatelist .= ",modcp_awaitingmoderation_none,modcp_banning_edit,modcp_banuser_bangroups_group,modcp_banuser_lift,modcp_modlogs_result_announcement,modcp_modlogs_result_forum,modcp_modlogs_result_post,modcp_modlogs_result_thread";
$templatelist .= ",modcp_nav_warninglogs,modcp_nav_ipsearch,modcp_nav_users,modcp_announcements_day,modcp_announcements_month_start,modcp_announcements_month_end,modcp_announcements_announcement_expired,modcp_announcements_announcement_active";
$templatelist .= ",modcp_modqueue_link_forum,modcp_modqueue_link_thread,usercp_profile_day,modcp_ipsearch_result_regip,modcp_ipsearch_result_lastip,modcp_ipsearch_result_post,modcp_ipsearch_results_information,usercp_profile_profilefields_text";
$templatelist .= ",usercp_profile_profilefields_select_option,usercp_profile_profilefields_multiselect,usercp_profile_profilefields_select,usercp_profile_profilefields_textarea,usercp_profile_profilefields_radio,postbit";
$templatelist .= ",modcp_banning_remaining,postmodcp_nav_announcements,modcp_nav_reportcenter,modcp_nav_modlogs,modcp_latestfivemodactions,modcp_banuser_bangroups_hidden,modcp_banuser_bangroups,usercp_profile_profilefields_checkbox";

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
$lang->load("announcements");

if($mybb->user['uid'] == 0 || $mybb->usergroup['canmodcp'] != 1)
{
	error_no_permission();
}

if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
{
	$mybb->settings['threadsperpage'] = 20;
}

$tflist = $flist = $tflist_queue_threads = $flist_queue_threads = $tflist_queue_posts = $flist_queue_posts = $tflist_queue_attach =
$flist_queue_attach = $wflist_reports = $tflist_reports = $flist_reports = $tflist_modlog = $flist_modlog = $errors = '';
// SQL for fetching items only related to forums this user moderates
$moderated_forums = array();
$numannouncements = $nummodqueuethreads = $nummodqueueposts = $nummodqueueattach = $numreportedposts = $nummodlogs = 0;
if($mybb->usergroup['issupermod'] != 1)
{
	$query = $db->simple_select("moderators", "*", "(id='{$mybb->user['uid']}' AND isgroup = '0') OR (id IN ({$mybb->usergroup['all_usergroups']}) AND isgroup = '1')");
	while($forum = $db->fetch_array($query))
	{
		$moderated_forums[] = $forum['fid'];
		$children = get_child_list($forum['fid']);
		if(is_array($children))
		{
			$moderated_forums = array_merge($moderated_forums, $children);
		}
	}
	$moderated_forums = array_unique($moderated_forums);

	$counters = [
		'announcements' => 0,
		'modqueue' => [
			'threads' => 0,
			'posts' => 0,
			'attachments' => 0
		],
		'reportedposts' => 0,
		'modlogs' => 0
	];
	foreach($moderated_forums as $moderated_forum)
	{
		// For Announcements
		if(is_moderator($moderated_forum, 'canmanageannouncements'))
		{
			++$counters['announcements'];
		}

		// For the Mod Queues
		if(is_moderator($moderated_forum, 'canapproveunapprovethreads'))
		{
			$flist_queue_threads .= ",'{$moderated_forum}'";
			++$counters['modqueue']['threads'];
		}

		if(is_moderator($moderated_forum, 'canapproveunapproveposts'))
		{
			$flist_queue_posts .= ",'{$moderated_forum}'";
			++$counters['modqueue']['posts'];
		}

		if(is_moderator($moderated_forum, 'canapproveunapproveattachs'))
		{
			$flist_queue_attach .= ",'{$moderated_forum}'";
			++$counters['modqueue']['attachments'];
		}

		// For Reported posts
		if(is_moderator($moderated_forum, 'canmanagereportedposts'))
		{
			$flist_reports .= ",'{$moderated_forum}'";
			++$counters['reportedposts'];
		}

		// For the Mod Log
		if(is_moderator($moderated_forum, 'canviewmodlog'))
		{
			$flist_modlog .= ",'{$moderated_forum}'";
			++$counters['modlogs'];
		}

		$flist .= ",'{$moderated_forum}'";
	}
	if($flist_queue_threads)
	{
		$tflist_queue_threads = " AND t.fid IN (0{$flist_queue_threads})";
		$flist_queue_threads = " AND fid IN (0{$flist_queue_threads})";
	}
	if($flist_queue_posts)
	{
		$tflist_queue_posts = " AND t.fid IN (0{$flist_queue_posts})";
		$flist_queue_posts = " AND fid IN (0{$flist_queue_posts})";
	}
	if($flist_queue_attach)
	{
		$tflist_queue_attach = " AND t.fid IN (0{$flist_queue_attach})";
		$flist_queue_attach = " AND fid IN (0{$flist_queue_attach})";
	}
	if($flist_reports)
	{
		$wflist_reports = "WHERE r.id3 IN (0{$flist_reports})";
		$tflist_reports = " AND r.id3 IN (0{$flist_reports})";
		$flist_reports = " AND id3 IN (0{$flist_reports})";
	}
	if($flist_modlog)
	{
		$tflist_modlog = " AND t.fid IN (0{$flist_modlog})";
		$flist_modlog = " AND fid IN (0{$flist_modlog})";
	}
	if($flist)
	{
		$tflist = " AND t.fid IN (0{$flist})";
		$flist = " AND fid IN (0{$flist})";
	}
}

// Retrieve a list of unviewable forums
$unviewableforums = get_unviewable_forums();
$inactiveforums = get_inactive_forums();
$unviewablefids1 = $unviewablefids2 = array();

if($unviewableforums)
{
	$flist .= " AND fid NOT IN ({$unviewableforums})";
	$tflist .= " AND t.fid NOT IN ({$unviewableforums})";

	$unviewablefids1 = explode(',', $unviewableforums);
}

if($inactiveforums)
{
	$flist .= " AND fid NOT IN ({$inactiveforums})";
	$tflist .= " AND t.fid NOT IN ({$inactiveforums})";

	$unviewablefids2 = explode(',', $inactiveforums);
}

$unviewableforums = array_merge($unviewablefids1, $unviewablefids2);

if(!isset($collapsedimg['modcpforums']))
{
	$collapsedimg['modcpforums'] = '';
}

if(!isset($collapsed['modcpforums_e']))
{
	$collapsed['modcpforums_e'] = '';
}

if(!isset($collapsedimg['modcpusers']))
{
	$collapsedimg['modcpusers'] = '';
}

if(!isset($collapsed['modcpusers_e']))
{
	$collapsed['modcpusers_e'] = '';
}

// Fetch the Mod CP menu
$nav_announcements = $nav_modqueue = $nav_reportcenter = $nav_modlogs = $nav_editprofile = $nav_banning = $nav_warninglogs = $nav_ipsearch = $nav_forums_posts = $modcp_nav_users = '';
if(($counters['announcements'] > 0 || $mybb->usergroup['issupermod'] == 1) && $mybb->usergroup['canmanageannounce'] == 1)
{
	eval("\$nav_announcements = \"".$templates->get("modcp_nav_announcements")."\";");
}

if(($counters['modqueue']['threads'] > 0 || $counters['modqueue']['posts'] > 0 || $counters['modqueue']['attachments'] > 0 || $mybb->usergroup['issupermod'] == 1) && $mybb->usergroup['canmanagemodqueue'] == 1)
{
	eval("\$nav_modqueue = \"".$templates->get("modcp_nav_modqueue")."\";");
}

if(($counters['reportedposts'] > 0 || $mybb->usergroup['issupermod'] == 1) && $mybb->usergroup['canmanagereportedcontent'] == 1)
{
	eval("\$nav_reportcenter = \"".$templates->get("modcp_nav_reportcenter")."\";");
}

if(($counters['modlogs'] > 0 || $mybb->usergroup['issupermod'] == 1) && $mybb->usergroup['canviewmodlogs'] == 1)
{
	eval("\$nav_modlogs = \"".$templates->get("modcp_nav_modlogs")."\";");
}

if($mybb->usergroup['caneditprofiles'] == 1)
{
	eval("\$nav_editprofile = \"".$templates->get("modcp_nav_editprofile")."\";");
}

if($mybb->usergroup['canbanusers'] == 1)
{
	eval("\$nav_banning = \"".$templates->get("modcp_nav_banning")."\";");
}

if($mybb->usergroup['canviewwarnlogs'] == 1)
{
	eval("\$nav_warninglogs = \"".$templates->get("modcp_nav_warninglogs")."\";");
}

if($mybb->usergroup['canuseipsearch'] == 1)
{
	eval("\$nav_ipsearch = \"".$templates->get("modcp_nav_ipsearch")."\";");
}

$plugins->run_hooks('modcp_nav');

if(!empty($nav_announcements) || !empty($nav_modqueue) || !empty($nav_reportcenter) || !empty($nav_modlogs))
{
	$expaltext = (in_array("modcpforums", $collapse)) ? $lang->expcol_expand : $lang->expcol_collapse;
	eval("\$modcp_nav_forums_posts = \"".$templates->get("modcp_nav_forums_posts")."\";");
}

if(!empty($nav_editprofile) || !empty($nav_banning) || !empty($nav_warninglogs) || !empty($nav_ipsearch))
{
	$expaltext = (in_array("modcpusers", $collapse)) ? $lang->expcol_expand : $lang->expcol_collapse;
	eval("\$modcp_nav_users = \"".$templates->get("modcp_nav_users")."\";");
}

eval("\$modcp_nav = \"".$templates->get("modcp_nav")."\";");

$plugins->run_hooks('modcp_start');

// Make navigation
add_breadcrumb($lang->nav_modcp, "modcp.php");

$mybb->input['action'] = $mybb->get_input('action');
if($mybb->input['action'] == "do_reports")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$mybb->input['reports'] = $mybb->get_input('reports', MyBB::INPUT_ARRAY);
	if(empty($mybb->input['reports']) && empty($mybb->cookies['inlinereports']))
	{
		error($lang->error_noselected_reports);
	}

	$message = $lang->redirect_reportsmarked;

	if(isset($mybb->cookies['inlinereports']))
	{
		if($mybb->cookies['inlinereports'] == '|ALL|')
		{
			$message = $lang->redirect_allreportsmarked;
			$sql = "1=1";
			if(isset($mybb->cookies['inlinereports_removed']))
			{
				$inlinereportremovedlist = explode("|", $mybb->cookies['inlinereports_removed']);
				$reports = array_map("intval", $inlinereportremovedlist);
				$rids = implode("','", $reports);
				$sql = "rid NOT IN ('0','{$rids}')";
			}
		}
		else
		{
			$inlinereportlist = explode("|", $mybb->cookies['inlinereports']);
			$reports = array_map("intval", $inlinereportlist);

			if(!count($reports))
			{
				error($lang->error_noselected_reports);
			}

			$rids = implode("','", $reports);

			$sql = "rid IN ('0','{$rids}')";
		}
	}
	else
	{
		$mybb->input['reports'] = array_map("intval", $mybb->input['reports']);
		$rids = implode("','", $mybb->input['reports']);

		$sql = "rid IN ('0','{$rids}')";
	}

	$plugins->run_hooks('modcp_do_reports');

	$db->update_query("reportedcontent", array('reportstatus' => 1), "{$sql}{$flist_reports}");
	$cache->update_reportedcontent();

	my_unsetcookie('inlinereports');
	my_unsetcookie('inlinereports_removed');

	$page = $mybb->get_input('page', MyBB::INPUT_INT);

	redirect("modcp.php?action=reports&page={$page}", $message);
}

if($mybb->input['action'] == "reports")
{
	if($mybb->usergroup['canmanagereportedcontent'] == 0)
	{
		error_no_permission();
	}

	if($counters['reportedposts'] == 0 && $mybb->usergroup['issupermod'] != 1)
	{
		error($lang->you_cannot_view_reported_posts);
	}

	$lang->load('report');
	add_breadcrumb($lang->mcp_nav_report_center, "modcp.php?action=reports");

	$perpage = $mybb->settings['threadsperpage'];
	if(!$perpage)
	{
		$perpage = 20;
	}

	// Multipage
	if($mybb->usergroup['cancp'] || $mybb->usergroup['issupermod'])
	{
		$query = $db->simple_select("reportedcontent", "COUNT(rid) AS count", "reportstatus ='0'");
		$report_count = $db->fetch_field($query, "count");
	}
	else
	{
		$query = $db->simple_select('reportedcontent', 'id3', "reportstatus='0' AND (type = 'post' OR type = '')");

		$report_count = 0;
		while($fid = $db->fetch_field($query, 'id3'))
		{
			if(is_moderator($fid, "canmanagereportedposts"))
			{
				++$report_count;
			}
		}
		unset($fid);
	}

	$page = $mybb->get_input('page', MyBB::INPUT_INT);

	$postcount = (int)$report_count;
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}

	if($page && $page > 0)
	{
		$start = ($page - 1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$multipage = '';
	if($postcount > $perpage)
	{
		$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=reports");
	}

	$plugins->run_hooks('modcp_reports_start');

	// Reports
	$reports = [];
	$report['selectall'] = false;
	$report['inlinecount'] = 0;

	$reportedcontent = $cache->read("reportedcontent");
	$reportcache = $usercache = $postcache = array();

	$query = $db->query("
        SELECT r.*, u.username, rr.title
        FROM ".TABLE_PREFIX."reportedcontent r
        LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid = u.uid)
        LEFT JOIN ".TABLE_PREFIX."reportreasons rr ON (r.reasonid = rr.rid)
        WHERE r.reportstatus = '0'{$tflist_reports}
        ORDER BY r.reports DESC
        LIMIT {$start}, {$perpage}
    ");

	while($report = $db->fetch_array($query))
	{
		if($report['type'] == 'profile' || $report['type'] == 'reputation')
		{
			// Profile UID is in ID
			if(!isset($usercache[$report['id']]))
			{
				$usercache[$report['id']] = $report['id'];
			}

			// Reputation comment? The offender is the ID2
			if($report['type'] == 'reputation')
			{
				if(!isset($usercache[$report['id2']]))
				{
					$usercache[$report['id2']] = $report['id2'];
				}
				if(!isset($usercache[$report['id3']]))
				{
					// The user who was offended
					$usercache[$report['id3']] = $report['id3'];
				}
			}
		}
		elseif(!$report['type'] || $report['type'] == 'post')
		{
			// This (should) be a post
			$postcache[$report['id']] = $report['id'];
		}

		// Lastpost info - is it missing (pre-1.8)?
		$lastposter = $report['uid'];
		if(!$report['lastreport'])
		{
			// Last reporter is our first reporter
			$report['lastreport'] = $report['dateline'];
		}

		if($report['reporters'])
		{
			$reporters = my_unserialize($report['reporters']);

			if(is_array($reporters))
			{
				$lastposter = end($reporters);
			}
		}

		if(!isset($usercache[$lastposter]))
		{
			$usercache[$lastposter] = $lastposter;
		}

		$report['lastreporter'] = $lastposter;
		$reportcache[] = $report;
	}

	// Report Center gets messy
	// Find information about our users (because we don't log it when they file a report)
	if(!empty($usercache))
	{
		$sql = implode(',', array_keys($usercache));
		$query = $db->simple_select("users", "uid, username", "uid IN ({$sql})");

		while($user = $db->fetch_array($query))
		{
			$usercache[$user['uid']] = $user;
		}
	}

	// Messy * 2
	// Find out post information for our reported posts
	if(!empty($postcache))
	{
		$sql = implode(',', array_keys($postcache));
		$query = $db->query("
            SELECT p.pid, p.uid, p.username, p.tid, t.subject
            FROM ".TABLE_PREFIX."posts p
            LEFT JOIN ".TABLE_PREFIX."threads t ON (p.tid = t.tid)
            WHERE p.pid IN ({$sql})
        ");

		while($post = $db->fetch_array($query))
		{
			$postcache[$post['pid']] = $post;
		}
	}

	$report['reportcache'] = count($reportcache);
	$report['report_count'] = (int)$report_count;
	$report['selectall'] = true;

	$plugins->run_hooks('modcp_reports_intermediate');

	// Now that we have all of the information needed, display the reports
	foreach($reportcache as $report)
	{
		if(!$report['type'])
		{
			// Assume a post
			$report['type'] = 'post';
		}

		switch($report['type'])
		{
			case 'post':
				$report_data['post'] = get_post_link($report['id'])."#pid{$report['id']}";
				$report_data['user'] = build_profile_link($postcache[$report['id']]['username'], $postcache[$report['id']]['uid']);
				$report_data['thread_link'] = get_thread_link($postcache[$report['id']]['tid']);
				$report_data['thread_subject'] = $parser->parse_badwords($postcache[$report['id']]['subject']);
				break;
			case 'profile':
				$report_data['profile_user'] = build_profile_link($usercache[$report['id']]['username'], $usercache[$report['id']]['uid']);
				break;
			case 'reputation':
				$report_data['reputation_link'] = "reputation.php?uid={$usercache[$report['id3']]['uid']}#rid{$report['id']}";
				$report_data['bad_user'] = build_profile_link($usercache[$report['id2']]['username'], $usercache[$report['id2']]['uid']);
				$report_data['good_user'] = build_profile_link($usercache[$report['id3']]['username'], $usercache[$report['id3']]['uid']);
				break;
		}

		$report_data['type'] = $report['type'];

		// Report reason and comment
		if($report['reasonid'] > 0)
		{
			$report_data['reason'] = $lang->parse($report['title']);
			$report_data['comment'] = $report['reason'];
		}
		else
		{
			$report_data['reason'] = $lang->na;
		}

		$report_reports = 1;
		if($report['reports'])
		{
			$report_data['reports'] = my_number_format($report['reports']);
		}

		if($report['lastreporter'])
		{
			if(is_array($usercache[$report['lastreporter']]))
			{
				$report_data['lastreport_user'] = build_profile_link($usercache[$report['lastreporter']]['username'], $report['lastreporter']);
			}
			elseif($usercache[$report['lastreporter']] > 0)
			{
				$report_data['lastreport_user'] = $lang->na_deleted;
			}

			$report_data['lastreport_date'] = $report['lastreport'];
		}

		$report_data['checked'] = false;
		if(isset($mybb->cookies['inlinereports']) && my_strpos($mybb->cookies['inlinereports'], "|{$report['rid']}|") !== false)
		{
			$report_data['checked'] = true;
			++$report['inlinecount'];
		}

		$report_data['rid'] = $report['rid'];

		$plugins->run_hooks('modcp_reports_report');

		$reports[] = $report_data;
	}

	$plugins->run_hooks('modcp_reports_end');

	$report['page'] = $page;

	output_page(\MyBB\template('modcp/reports.twig', [
		'report' => $report,
		'multipage' => $multipage,
		'reports' => $reports,
	]));
}

if($mybb->input['action'] == "allreports")
{
	if($mybb->usergroup['canmanagereportedcontent'] == 0)
	{
		error_no_permission();
	}

	$lang->load('report');

	add_breadcrumb($lang->report_center, "modcp.php?action=reports");
	add_breadcrumb($lang->all_reports, "modcp.php?action=allreports");

	if(!$mybb->settings['threadsperpage'])
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	// Figure out if we need to display multiple pages.
	$perpage = $mybb->settings['threadsperpage'];
	if($mybb->get_input('page') != "last")
	{
		$page = $mybb->get_input('page', MyBB::INPUT_INT);
	}

	if($mybb->usergroup['cancp'] || $mybb->usergroup['issupermod'])
	{
		$query = $db->simple_select("reportedcontent", "COUNT(rid) AS count");
		$report_count = $db->fetch_field($query, "count");
	}
	else
	{
		$query = $db->simple_select('reportedcontent', 'id3', "type = 'post' OR type = ''");

		$report_count = 0;
		while($fid = $db->fetch_field($query, 'id3'))
		{
			if(is_moderator($fid, "canmanagereportedposts"))
			{
				++$report_count;
			}
		}
		unset($fid);
	}

	if(isset($mybb->input['rid']))
	{
		$mybb->input['rid'] = $mybb->get_input('rid', MyBB::INPUT_INT);
		$query = $db->simple_select("reportedcontent", "COUNT(rid) AS count", "rid <= '".$mybb->input['rid']."'");
		$result = $db->fetch_field($query, "count");
		if(($result % $perpage) == 0)
		{
			$page = $result / $perpage;
		}
		else
		{
			$page = (int)$result / $perpage + 1;
		}
	}
	$postcount = (int)$report_count;
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

	$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=allreports");

	$plugins->run_hooks('modcp_allreports_start');

	$allreports = [];
	$query = $db->query("
        SELECT r.*, u.username, p.username AS postusername, up.uid AS postuid, t.subject AS threadsubject, prrep.username AS repusername, pr.username AS profileusername, rr.title, prrep2.username AS goodrepusername
        FROM ".TABLE_PREFIX."reportedcontent r
        LEFT JOIN ".TABLE_PREFIX."posts p ON (r.id=p.pid)
        LEFT JOIN ".TABLE_PREFIX."threads t ON (p.tid=t.tid)
        LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid=u.uid)
        LEFT JOIN ".TABLE_PREFIX."users up ON (p.uid=up.uid)
        LEFT JOIN ".TABLE_PREFIX."users pr ON (pr.uid=r.id)
        LEFT JOIN ".TABLE_PREFIX."users prrep ON (prrep.uid=r.id2)
        LEFT JOIN ".TABLE_PREFIX."users prrep2 ON (prrep2.uid=r.id3)
        LEFT JOIN ".TABLE_PREFIX."reportreasons rr ON (r.reasonid = rr.rid)
        {$wflist_reports}
        ORDER BY r.dateline DESC
        LIMIT {$start}, {$perpage}
    ");

	while($report = $db->fetch_array($query))
	{
		if($report['type'] == 'post')
		{
			$report_data['post'] = get_post_link($report['id'])."#pid{$report['id']}";
			$report_data['user'] = build_profile_link($report['postusername'], $report['postuid']);
			$report_data['thread_link'] = get_thread_link($report['id2']);
			$report_data['thread_subject'] = $parser->parse_badwords($report['threadsubject']);
		}
		elseif($report['type'] == 'profile')
		{
			$report_data['profile_user'] = build_profile_link($report['profileusername'], $report['id']);
		}
		elseif($report['type'] == 'reputation')
		{
			$report_data['bad_user'] = build_profile_link($report['repusername'], $report['id2']);
			$report_data['good_user'] = build_profile_link($report['goodrepusername'], $report['id3']);
			$report_data['reputation_link'] = "reputation.php?uid={$report['id3']}#rid{$report['id']}";
		}

		$report_data['type'] = $report['type'];

		// Report reason and comment
		if($report['reasonid'] > 0)
		{
			$report_data['reason'] = $lang->parse($report['title']);
			$report_data['comment'] = $report['reason'];
		}
		else
		{
			$report_data['reason'] = $lang->na;
		}

		$report_data['reporterlink'] = get_profile_link($report['uid']);
		$report_data['username'] = $report['username'];
		if(!$report['username'])
		{
			$report_data['username'] = $lang->na_deleted;
			$report_data['reporterlink'] = $post;
		}

		$report_data['reports'] = my_number_format($report['reports']);
		$report_data['time'] = my_date('relative', $report['dateline']);

		$plugins->run_hooks('modcp_allreports_report');

		$allreports[] = $report_data;
	}

	$plugins->run_hooks('modcp_allreports_end');

	output_page(\MyBB\template('modcp/allreports.twig', [
		'multipage' => $multipage,
		'allreports' => $allreports,
	]));
}

if($mybb->input['action'] == "modlogs")
{
	if($mybb->usergroup['canviewmodlogs'] == 0)
	{
		error_no_permission();
	}

	if($counters['modlogs'] == 0 && $mybb->usergroup['issupermod'] != 1)
	{
		error($lang->you_cannot_view_mod_logs);
	}

	add_breadcrumb($lang->mcp_nav_modlogs, "modcp.php?action=modlogs");

	$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if(!$perpage || $perpage <= 0)
	{
		$perpage = $mybb->settings['threadsperpage'];
	}

	$where = '';

	// Searching for entries by a particular user
	if($mybb->get_input('uid', MyBB::INPUT_INT))
	{
		$where .= " AND l.uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";
	}

	// Searching for entries in a specific forum
	if($mybb->get_input('fid', MyBB::INPUT_INT))
	{
		$where .= " AND t.fid='".$mybb->get_input('fid', MyBB::INPUT_INT)."'";
	}

	$mybb->input['sortby'] = $mybb->get_input('sortby');

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
	$order = $mybb->get_input('order');
	if($order != "asc")
	{
		$order = "desc";
	}

	$plugins->run_hooks('modcp_modlogs_start');

	$query = $db->query("
        SELECT COUNT(l.dateline) AS count
        FROM ".TABLE_PREFIX."moderatorlog l
        LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
        LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
        WHERE 1=1 {$where}{$tflist_modlog}
    ");
	$rescount = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->get_input('page') != "last")
	{
		$page = $mybb->get_input('page', MyBB::INPUT_INT);
	}

	$postcount = (int)$rescount;
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

	$page_url = 'modcp.php?action=modlogs&amp;perpage='.$perpage;
	foreach(array('uid', 'fid') as $field)
	{
		$mybb->input[$field] = $mybb->get_input($field, MyBB::INPUT_INT);
		if(!empty($mybb->input[$field]))
		{
			$page_url .= "&amp;{$field}=".$mybb->input[$field];
		}
	}
	foreach(array('sortby', 'order') as $field)
	{
		$mybb->input[$field] = htmlspecialchars_uni($mybb->get_input($field));
		if(!empty($mybb->input[$field]))
		{
			$page_url .= "&amp;{$field}=".$mybb->input[$field];
		}
	}

	if($postcount > $perpage)
	{
		$multipage = multipage($postcount, $perpage, $page, $page_url);
	}

	$modlogs = [];
	$query = $db->query("
        SELECT l.*, u.username, u.usergroup, u.displaygroup, t.subject AS tsubject, f.name AS fname, p.subject AS psubject
        FROM ".TABLE_PREFIX."moderatorlog l
        LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
        LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
        LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=l.fid)
        LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=l.pid)
        WHERE 1=1 {$where}{$tflist_modlog}
        ORDER BY {$sortby} {$order}
        LIMIT {$start}, {$perpage}
    ");
	while($logitem = $db->fetch_array($query))
	{

		$logitem['date'] = my_date('relative', $logitem['dateline']);

		if($logitem['username'])
		{
			$username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);
			$logitem['profilelink'] = build_profile_link($username, $logitem['uid']);
		}
		else
		{
			$username = $logitem['profilelink'] = $logitem['username'] = $lang->na_deleted;
		}

		$logitem['ipaddress'] = my_inet_ntop($db->unescape_binary($logitem['ipaddress']));

		if($logitem['tsubject'])
		{
			$logitem['tsubject'] = $parser->parse_badwords($logitem['tsubject']);
			$logitem['threadlink'] = get_thread_link($logitem['tid']);
		}

		if($logitem['fname'])
		{
			$logitem['forumlink'] = get_forum_link($logitem['fid']);
		}

		if($logitem['psubject'])
		{
			$logitem['psubject'] = $parser->parse_badwords($logitem['psubject']);
			$logitem['postlink'] = get_post_link($logitem['pid']);
		}

		// Edited a user or managed announcement?
		if(!$logitem['tsubject'] || !$logitem['fname'] || !$logitem['psubject'])
		{
			$logitem['logdata'] = my_unserialize($logitem['data']);
			if(!empty($logitem['logdata']['uid']))
			{
				$logitem['logdata']['profilelink'] = get_profile_link($logitem['logdata']['uid']);
			}

			if(!empty($logitem['logdata']['aid']))
			{
				$logitem['logdata']['subject'] = $parser->parse_badwords($logitem['logdata']['subject']);
				$logitem['logdata']['announcement'] = get_announcement_link($logitem['logdata']['aid']);
			}
		}

		$plugins->run_hooks('modcp_modlogs_result');

		$modlogs[] = $logitem;
	}

	$plugins->run_hooks('modcp_modlogs_filter');

	// Fetch filter options
	$select['sortby'] = array('username' => '', 'forum' => '', 'thread' => '', 'dateline' => '');
	$select['sortby'][$mybb->input['sortby']] = true;
	$select['order'] = array('asc' => '', 'desc' => '');
	$select['order'][$order] = true;
	$select['perpage'] = $perpage;

	$users = [];
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

		$user['selected'] = false;
		if($mybb->get_input('uid', MyBB::INPUT_INT) == $user['uid'])
		{
			$user['selected'] = true;
		}

		$users[] = $user;
	}

	$forum_select = build_forum_jump("", $mybb->get_input('fid', MyBB::INPUT_INT), 1, '', 0, true, '', "fid");

	output_page(\MyBB\template('modcp/modlogs.twig', [
		'multipage' => $multipage,
		'modlogs' => $modlogs,
		'select' => $select,
		'users' => $users,
		'forum_select' => $forum_select,
	]));
}

if($mybb->input['action'] == "do_delete_announcement")
{
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['canmanageannounce'] == 0)
	{
		error_no_permission();
	}

	$aid = $mybb->get_input('aid');
	$query = $db->simple_select("announcements", "aid, subject, fid", "aid='{$aid}'");
	$announcement = $db->fetch_array($query);

	if(!$announcement)
	{
		error($lang->error_invalid_announcement);
	}
	if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'], "canmanageannouncements")) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	$plugins->run_hooks('modcp_do_delete_announcement');

	$db->delete_query("announcements", "aid='{$aid}'");
	log_moderator_action(array("aid" => $announcement['aid'], "subject" => $announcement['subject']), $lang->announcement_deleted);
	$cache->update_forumsdisplay();

	redirect("modcp.php?action=announcements", $lang->redirect_delete_announcement);
}

if($mybb->input['action'] == "delete_announcement")
{
	if($mybb->usergroup['canmanageannounce'] == 0)
	{
		error_no_permission();
	}

	$aid = $mybb->get_input('aid');
	$query = $db->simple_select("announcements", "aid, subject, fid", "aid='{$aid}'");

	$announcement = $db->fetch_array($query);
	$announcement['subject'] = htmlspecialchars_uni($parser->parse_badwords($announcement['subject']));

	if(!$announcement)
	{
		error($lang->error_invalid_announcement);
	}

	if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'], "canmanageannouncements")) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	$plugins->run_hooks('modcp_delete_announcement');

	output_page(\MyBB\template('modcp/announcements_delete.twig', [
		'announcement' => $announcement
	]));
}

if($mybb->input['action'] == "do_new_announcement")
{
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['canmanageannounce'] == 0)
	{
		error_no_permission();
	}

	$announcement_fid = $mybb->get_input('fid', MyBB::INPUT_INT);
	if(($mybb->usergroup['issupermod'] != 1 && $announcement_fid == -1) || ($announcement_fid != -1 && !is_moderator($announcement_fid, "canmanageannouncements")) || ($unviewableforums && in_array($announcement_fid, $unviewableforums)))
	{
		error_no_permission();
	}

	$errors = array();

	$mybb->input['title'] = $mybb->get_input('title');
	if(!trim($mybb->input['title']))
	{
		$errors[] = $lang->error_missing_title;
	}

	$mybb->input['message'] = $mybb->get_input('message');
	if(!trim($mybb->input['message']))
	{
		$errors[] = $lang->error_missing_message;
	}

	if(!$announcement_fid)
	{
		$errors[] = $lang->error_missing_forum;
	}

	$mybb->input['starttime_time'] = $mybb->get_input('starttime_time');
	$mybb->input['endtime_time'] = $mybb->get_input('endtime_time');
	$startdate = @explode(" ", $mybb->input['starttime_time']);
	$startdate = @explode(":", $startdate[0]);
	$enddate = @explode(" ", $mybb->input['endtime_time']);
	$enddate = @explode(":", $enddate[0]);

	if(stristr($mybb->input['starttime_time'], "pm"))
	{
		$startdate[0] = 12 + $startdate[0];
		if($startdate[0] >= 24)
		{
			$startdate[0] = "00";
		}
	}

	if(stristr($mybb->input['endtime_time'], "pm"))
	{
		$enddate[0] = 12 + $enddate[0];
		if($enddate[0] >= 24)
		{
			$enddate[0] = "00";
		}
	}

	$mybb->input['starttime_month'] = $mybb->get_input('starttime_month');
	$months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');
	if(!in_array($mybb->input['starttime_month'], $months))
	{
		$mybb->input['starttime_month'] = '01';
	}

	$localized_time_offset = (float)$mybb->user['timezone'] * 3600 + $mybb->user['dst'] * 3600;

	$startdate = gmmktime((int)$startdate[0], (int)$startdate[1], 0, $mybb->get_input('starttime_month', MyBB::INPUT_INT), $mybb->get_input('starttime_day', MyBB::INPUT_INT), $mybb->get_input('starttime_year', MyBB::INPUT_INT)) - $localized_time_offset;
	if(!checkdate($mybb->get_input('starttime_month', MyBB::INPUT_INT), $mybb->get_input('starttime_day', MyBB::INPUT_INT), $mybb->get_input('starttime_year', MyBB::INPUT_INT)) || $startdate < 0 || $startdate == false)
	{
		$errors[] = $lang->error_invalid_start_date;
	}

	if($mybb->get_input('endtime_type', MyBB::INPUT_INT) == 2)
	{
		$enddate = '0';
		$mybb->input['endtime_month'] = '01';
	}
	else
	{
		$mybb->input['endtime_month'] = $mybb->get_input('endtime_month');
		if(!in_array($mybb->input['endtime_month'], $months))
		{
			$mybb->input['endtime_month'] = '01';
		}
		$enddate = gmmktime((int)$enddate[0], (int)$enddate[1], 0, $mybb->get_input('endtime_month', MyBB::INPUT_INT), $mybb->get_input('endtime_day', MyBB::INPUT_INT), $mybb->get_input('endtime_year', MyBB::INPUT_INT)) - $localized_time_offset;
		if(!checkdate($mybb->get_input('endtime_month', MyBB::INPUT_INT), $mybb->get_input('endtime_day', MyBB::INPUT_INT), $mybb->get_input('endtime_year', MyBB::INPUT_INT)) || $enddate < 0 || $enddate == false)
		{
			$errors[] = $lang->error_invalid_end_date;
		}

		if($enddate <= $startdate)
		{
			$errors[] = $lang->error_end_before_start;
		}
	}

	if($mybb->settings['announcementshtml'] && $mybb->get_input('allowhtml', MyBB::INPUT_INT) == 1)
	{
		$allowhtml = 1;
	}
	else
	{
		$allowhtml = 0;
	}
	if($mybb->get_input('allowmycode', MyBB::INPUT_INT) == 1)
	{
		$allowmycode = 1;
	}
	else
	{
		$allowmycode = 0;
	}
	if($mybb->get_input('allowsmilies', MyBB::INPUT_INT) == 1)
	{
		$allowsmilies = 1;
	}
	else
	{
		$allowsmilies = 0;
	}

	$plugins->run_hooks('modcp_do_new_announcement_start');

	if(!$errors)
	{
		if(isset($mybb->input['preview']))
		{
			$preview = array();
			$mybb->input['action'] = 'new_announcement';
		}
		else
		{
			$insert_announcement = array(
				'fid' => $announcement_fid,
				'uid' => $mybb->user['uid'],
				'subject' => $db->escape_string($mybb->input['title']),
				'message' => $db->escape_string($mybb->input['message']),
				'startdate' => $startdate,
				'enddate' => $enddate,
				'allowhtml' => $allowhtml,
				'allowmycode' => $allowmycode,
				'allowsmilies' => $allowsmilies
			);
			$aid = $db->insert_query("announcements", $insert_announcement);

			log_moderator_action(array("aid" => $aid, "subject" => $mybb->input['title']), $lang->announcement_added);

			$plugins->run_hooks('modcp_do_new_announcement_end');

			$cache->update_forumsdisplay();
			redirect("modcp.php?action=announcements", $lang->redirect_add_announcement);
		}
	}
	else
	{
		$mybb->input['action'] = 'new_announcement';
	}
}

if($mybb->input['action'] == "new_announcement")
{
	if($mybb->usergroup['canmanageannounce'] == 0)
	{
		error_no_permission();
	}

	add_breadcrumb($lang->mcp_nav_announcements, "modcp.php?action=announcements");
	add_breadcrumb($lang->add_announcement, "modcp.php?action=new_announcements");

	$announcement_fid = $mybb->get_input('fid', MyBB::INPUT_INT);

	if(($mybb->usergroup['issupermod'] != 1 && $announcement_fid == -1) || ($announcement_fid != -1 && !is_moderator($announcement_fid, "canmanageannouncements")) || ($unviewableforums && in_array($announcement_fid, $unviewableforums)))
	{
		error_no_permission();
	}

	$dates = [];

	// Deal with inline errors
	if(!empty($errors) || isset($preview))
	{
		if(!empty($errors))
		{
			$errors = inline_error($errors);
		}
		else
		{
			$errors = '';
		}

		// Set $announcement to input stuff
		$announcement['subject'] = $mybb->input['title'];
		$announcement['message'] = $mybb->input['message'];
		$announcement['allowhtml'] = $allowhtml;
		$announcement['allowmycode'] = $allowmycode;
		$announcement['allowsmilies'] = $allowsmilies;
		$announcement['fid'] = $announcement_fid;

		$dates['start']['month'] = $mybb->input['starttime_month'];
		$dates['start']['year'] = $mybb->input['starttime_year'];
		$dates['start']['day'] = $mybb->get_input('starttime_day', MyBB::INPUT_INT);
		$dates['start']['time'] = $mybb->input['starttime_time'];
		$dates['end']['month'] = $mybb->input['endtime_month'];
		$dates['end']['year'] = $mybb->input['endtime_year'];
		$dates['end']['day'] = $mybb->get_input('endtime_day', MyBB::INPUT_INT);
		$dates['end']['time'] = $mybb->input['endtime_time'];
	}
	else
	{
		$localized_time = TIME_NOW + (float)$mybb->user['timezone'] * 3600 + $mybb->user['dst'] * 3600;

		$dates['start']['time'] = gmdate($mybb->settings['timeformat'], $localized_time);
		$dates['end']['time'] = gmdate($mybb->settings['timeformat'], $localized_time);
		$dates['start']['day'] = $dates['end']['day'] = gmdate("j", $localized_time);
		$dates['start']['month'] = $dates['start']['month'] = gmdate("m", $localized_time);
		$dates['start']['year'] = gmdate("Y", $localized_time);

		$announcement = array(
			'subject' => '',
			'message' => '',
			'allowhtml' => 0,
			'allowmycode' => 1,
			'allowsmilies' => 1,
			'fid' => $announcement_fid
		);

		$dates['end']['year'] = $dates['start']['year'] + 1;
	}

	// MyCode editor
	$codebuttons = build_mycode_inserter();
	$smilieinserter = build_clickable_smilies();

	$postbit = '';
	if(isset($preview))
	{
		$announcementarray = array(
			'aid' => 0,
			'fid' => $announcement_fid,
			'uid' => $mybb->user['uid'],
			'subject' => $mybb->input['title'],
			'message' => $mybb->input['message'],
			'allowhtml' => $mybb->settings['announcementshtml'] && $mybb->get_input('allowhtml', MyBB::INPUT_INT),
			'allowmycode' => $mybb->get_input('allowmycode', MyBB::INPUT_INT),
			'allowsmilies' => $mybb->get_input('allowsmilies', MyBB::INPUT_INT),
			'dateline' => TIME_NOW,
			'userusername' => $mybb->user['username'],
		);

		$array = $mybb->user;
		foreach($array as $key => $element)
		{
			$announcementarray[$key] = $element;
		}

		// Gather usergroup data from the cache
		// Field => Array Key
		$data_key = array(
			'title' => 'grouptitle',
			'usertitle' => 'groupusertitle',
			'stars' => 'groupstars',
			'starimage' => 'groupstarimage',
			'image' => 'groupimage',
			'namestyle' => 'namestyle',
			'usereputationsystem' => 'usereputationsystem'
		);

		foreach($data_key as $field => $key)
		{
			$announcementarray[$key] = $groupscache[$announcementarray['usergroup']][$field];
		}

		require_once MYBB_ROOT."inc/functions_post.php";
		$postbit = build_postbit($announcementarray, 3);
	}

	$plugins->run_hooks('modcp_new_announcement');

	output_page(\MyBB\template('modcp/announcements_add.twig', [
		'announcement' => $announcement,
		'preview' => $preview,
		'codebuttons' => $codebuttons,
		'smilieinserter' => $smilieinserter,
		'dates' => $dates,
		'errors' => $errors,
		'postbit' => $postbit
	]));
}

if($mybb->input['action'] == "do_edit_announcement")
{
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['canmanageannounce'] == 0)
	{
		error_no_permission();
	}

	// Get the announcement
	$aid = $mybb->get_input('aid', MyBB::INPUT_INT);
	$query = $db->simple_select("announcements", "*", "aid='{$aid}'");
	$announcement = $db->fetch_array($query);

	// Check that it exists
	if(!$announcement)
	{
		error($lang->error_invalid_announcement);
	}

	// Mod has permissions to edit this announcement
	if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'], "canmanageannouncements")) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	$errors = array();

	// Basic error checking
	$mybb->input['title'] = $mybb->get_input('title');
	if(!trim($mybb->input['title']))
	{
		$errors[] = $lang->error_missing_title;
	}

	$mybb->input['message'] = $mybb->get_input('message');
	if(!trim($mybb->input['message']))
	{
		$errors[] = $lang->error_missing_message;
	}

	$mybb->input['starttime_time'] = $mybb->get_input('starttime_time');
	$mybb->input['endtime_time'] = $mybb->get_input('endtime_time');
	$startdate = @explode(" ", $mybb->input['starttime_time']);
	$startdate = @explode(":", $startdate[0]);
	$enddate = @explode(" ", $mybb->input['endtime_time']);
	$enddate = @explode(":", $enddate[0]);

	if(stristr($mybb->input['starttime_time'], "pm"))
	{
		$startdate[0] = 12 + $startdate[0];
		if($startdate[0] >= 24)
		{
			$startdate[0] = "00";
		}
	}

	if(stristr($mybb->input['endtime_time'], "pm"))
	{
		$enddate[0] = 12 + $enddate[0];
		if($enddate[0] >= 24)
		{
			$enddate[0] = "00";
		}
	}

	$mybb->input['starttime_month'] = $mybb->get_input('starttime_month');
	$months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');
	if(!in_array($mybb->input['starttime_month'], $months))
	{
		$mybb->input['starttime_month'] = '01';
	}

	$localized_time_offset = (float)$mybb->user['timezone'] * 3600 + $mybb->user['dst'] * 3600;

	$startdate = gmmktime((int)$startdate[0], (int)$startdate[1], 0, $mybb->get_input('starttime_month', MyBB::INPUT_INT), $mybb->get_input('starttime_day', MyBB::INPUT_INT), $mybb->get_input('starttime_year', MyBB::INPUT_INT)) - $localized_time_offset;
	if(!checkdate($mybb->get_input('starttime_month', MyBB::INPUT_INT), $mybb->get_input('starttime_day', MyBB::INPUT_INT), $mybb->get_input('starttime_year', MyBB::INPUT_INT)) || $startdate < 0 || $startdate == false)
	{
		$errors[] = $lang->error_invalid_start_date;
	}

	if($mybb->get_input('endtime_type', MyBB::INPUT_INT) == "2")
	{
		$enddate = '0';
		$mybb->input['endtime_month'] = '01';
	}
	else
	{
		$mybb->input['endtime_month'] = $mybb->get_input('endtime_month');
		if(!in_array($mybb->input['endtime_month'], $months))
		{
			$mybb->input['endtime_month'] = '01';
		}
		$enddate = gmmktime((int)$enddate[0], (int)$enddate[1], 0, $mybb->get_input('endtime_month', MyBB::INPUT_INT), $mybb->get_input('endtime_day', MyBB::INPUT_INT), $mybb->get_input('endtime_year', MyBB::INPUT_INT)) - $localized_time_offset;
		if(!checkdate($mybb->get_input('endtime_month', MyBB::INPUT_INT), $mybb->get_input('endtime_day', MyBB::INPUT_INT), $mybb->get_input('endtime_year', MyBB::INPUT_INT)) || $enddate < 0 || $enddate == false)
		{
			$errors[] = $lang->error_invalid_end_date;
		}
		elseif($enddate <= $startdate)
		{
			$errors[] = $lang->error_end_before_start;
		}
	}

	if($mybb->settings['announcementshtml'] && $mybb->get_input('allowhtml', MyBB::INPUT_INT) == 1)
	{
		$allowhtml = 1;
	}
	else
	{
		$allowhtml = 0;
	}
	if($mybb->get_input('allowmycode', MyBB::INPUT_INT) == 1)
	{
		$allowmycode = 1;
	}
	else
	{
		$allowmycode = 0;
	}
	if($mybb->get_input('allowsmilies', MyBB::INPUT_INT) == 1)
	{
		$allowsmilies = 1;
	}
	else
	{
		$allowsmilies = 0;
	}

	$plugins->run_hooks('modcp_do_edit_announcement_start');

	// Proceed to update if no errors
	if(!$errors)
	{
		if(isset($mybb->input['preview']))
		{
			$preview = array();
			$mybb->input['action'] = 'edit_announcement';
		}
		else
		{
			$update_announcement = array(
				'uid' => $mybb->user['uid'],
				'subject' => $db->escape_string($mybb->input['title']),
				'message' => $db->escape_string($mybb->input['message']),
				'startdate' => $startdate,
				'enddate' => $enddate,
				'allowhtml' => $allowhtml,
				'allowmycode' => $allowmycode,
				'allowsmilies' => $allowsmilies
			);
			$db->update_query("announcements", $update_announcement, "aid='{$aid}'");

			log_moderator_action(array("aid" => $announcement['aid'], "subject" => $mybb->input['title']), $lang->announcement_edited);

			$plugins->run_hooks('modcp_do_edit_announcement_end');

			$cache->update_forumsdisplay();
			redirect("modcp.php?action=announcements", $lang->redirect_edit_announcement);
		}
	}
	else
	{
		$mybb->input['action'] = 'edit_announcement';
	}
}

if($mybb->input['action'] == "edit_announcement")
{
	if($mybb->usergroup['canmanageannounce'] == 0)
	{
		error_no_permission();
	}

	$aid = $mybb->get_input('aid', MyBB::INPUT_INT);

	add_breadcrumb($lang->mcp_nav_announcements, "modcp.php?action=announcements");
	add_breadcrumb($lang->edit_announcement, "modcp.php?action=edit_announcements&amp;aid={$aid}");

	// Get announcement
	if(!isset($announcement) || $mybb->request_method != 'post')
	{
		$query = $db->simple_select("announcements", "*", "aid='{$aid}'");
		$announcement = $db->fetch_array($query);
	}

	if(!$announcement)
	{
		error($lang->error_invalid_announcement);
	}
	if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'], "canmanageannouncements")) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	if(!$announcement['startdate'])
	{
		// No start date? Make it now.
		$announcement['startdate'] = TIME_NOW;
	}

	$announcement['endtime_type'] = 1;
	if(!$announcement['enddate'])
	{
		$announcement['endtime_type'] = 2;
		$makeshift_time = TIME_NOW;
		if($announcement['startdate'])
		{
			$makeshift_time = $announcement['startdate'];
		}

		// No end date? Make it a year from now.
		$announcement['enddate'] = $makeshift_time + (60 * 60 * 24 * 366);
	}

	$dates = [];

	// Deal with inline errors
	if(!empty($errors) || isset($preview))
	{
		if(!empty($errors))
		{
			$errors = inline_error($errors);
		}
		else
		{
			$errors = '';
		}

		// Set $announcement to input stuff
		$announcement['subject'] = $mybb->input['title'];
		$announcement['message'] = $mybb->input['message'];
		$announcement['allowhtml'] = $allowhtml;
		$announcement['allowmycode'] = $allowmycode;
		$announcement['allowsmilies'] = $allowsmilies;

		$dates['start']['month'] = $mybb->input['starttime_month'];
		$dates['start']['year'] = $mybb->input['starttime_year'];
		$dates['start']['day'] = $mybb->get_input('starttime_day', MyBB::INPUT_INT);
		$dates['start']['time'] = $mybb->input['starttime_time'];
		$dates['end']['month'] = $mybb->input['endtime_month'];
		$dates['end']['year'] = $mybb->input['endtime_year'];
		$dates['end']['day'] = $mybb->get_input('endtime_day', MyBB::INPUT_INT);
		$dates['end']['time'] = $mybb->input['endtime_time'];
	}
	else
	{
		$localized_time_startdate = $announcement['startdate'] + (float)$mybb->user['timezone'] * 3600 + $mybb->user['dst'] * 3600;
		$localized_time_enddate = $announcement['enddate'] + (float)$mybb->user['timezone'] * 3600 + $mybb->user['dst'] * 3600;

		$dates['start']['time'] = gmdate($mybb->settings['timeformat'], $localized_time_startdate);
		$dates['end']['time'] = gmdate($mybb->settings['timeformat'], $localized_time_enddate);

		$dates['start']['day'] = gmdate('j', $localized_time_startdate);
		$dates['start']['month'] = gmdate('m', $localized_time_startdate);
		$dates['start']['year'] = gmdate('Y', $localized_time_startdate);
		$dates['end']['day'] = gmdate('j', $localized_time_enddate);
		$dates['end']['month'] = gmdate('m', $localized_time_enddate);
		$dates['end']['year'] = gmdate('Y', $localized_time_enddate);
	}

	// MyCode editor
	$codebuttons = build_mycode_inserter();
	$smilieinserter = build_clickable_smilies();
	$postbit = '';

	if(isset($preview))
	{
		$announcementarray = array(
			'aid' => $announcement['aid'],
			'fid' => $announcement['fid'],
			'uid' => $mybb->user['uid'],
			'subject' => $mybb->input['title'],
			'message' => $mybb->input['message'],
			'allowhtml' => $mybb->settings['announcementshtml'] && $mybb->get_input('allowhtml', MyBB::INPUT_INT),
			'allowmycode' => $mybb->get_input('allowmycode', MyBB::INPUT_INT),
			'allowsmilies' => $mybb->get_input('allowsmilies', MyBB::INPUT_INT),
			'dateline' => TIME_NOW,
			'userusername' => $mybb->user['username'],
		);

		$array = $mybb->user;
		foreach($array as $key => $element)
		{
			$announcementarray[$key] = $element;
		}

		// Gather usergroup data from the cache
		// Field => Array Key
		$data_key = array(
			'title' => 'grouptitle',
			'usertitle' => 'groupusertitle',
			'stars' => 'groupstars',
			'starimage' => 'groupstarimage',
			'image' => 'groupimage',
			'namestyle' => 'namestyle',
			'usereputationsystem' => 'usereputationsystem'
		);

		foreach($data_key as $field => $key)
		{
			$announcementarray[$key] = $groupscache[$announcementarray['usergroup']][$field];
		}

		require_once MYBB_ROOT."inc/functions_post.php";
		$postbit = build_postbit($announcementarray, 3);
	}

	$plugins->run_hooks('modcp_edit_announcement');

	output_page(\MyBB\template('modcp/announcements_edit.twig', [
		'announcement' => $announcement,
		'preview' => $preview,
		'codebuttons' => $codebuttons,
		'smilieinserter' => $smilieinserter,
		'dates' => $dates,
		'errors' => $errors,
		'postbit' => $postbit
	]));
}

if($mybb->input['action'] == "announcements")
{
	if($mybb->usergroup['canmanageannounce'] == 0)
	{
		error_no_permission();
	}

	if($counters['announcements'] == 0 && $mybb->usergroup['issupermod'] != 1)
	{
		error($lang->you_cannot_manage_announcements);
	}

	add_breadcrumb($lang->mcp_nav_announcements, "modcp.php?action=announcements");

	// Fetch announcements into their proper arrays
	$query = $db->simple_select("announcements", "aid, fid, subject, enddate");
	$announcements = $global_announcements = [];
	while($announcement = $db->fetch_array($query))
	{
		if($announcement['fid'] == -1)
		{
			$announcement['subject'] = $parser->parse_badwords($announcement['subject']);
			$global_announcements[$announcement['aid']] = $announcement;
			continue;
		}
		$announcements[$announcement['fid']][$announcement['aid']] = $announcement;
	}

	$announcements_forum = '';
	fetch_forum_announcements();

	$plugins->run_hooks('modcp_announcements');

	output_page(\MyBB\template('modcp/announcements.twig', [
		'globalAnnouncements' => $global_announcements,
		'forumAnnouncements' => $announcements_forum
	]));
}

if($mybb->input['action'] == "do_modqueue")
{
	require_once MYBB_ROOT."inc/class_moderation.php";
	$moderation = new Moderation;

	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['canmanagemodqueue'] == 0)
	{
		error_no_permission();
	}

	$plugins->run_hooks('modcp_do_modqueue_start');

	$mybb->input['threads'] = $mybb->get_input('threads', MyBB::INPUT_ARRAY);
	$mybb->input['posts'] = $mybb->get_input('posts', MyBB::INPUT_ARRAY);
	$mybb->input['attachments'] = $mybb->get_input('attachments', MyBB::INPUT_ARRAY);
	if(!empty($mybb->input['threads']))
	{
		$threads = array_map("intval", array_keys($mybb->input['threads']));
		$threads_to_approve = $threads_to_delete = array();
		// Fetch threads
		$query = $db->simple_select("threads", "tid", "tid IN (".implode(",", $threads)."){$flist_queue_threads}");
		while($thread = $db->fetch_array($query))
		{
			if(!isset($mybb->input['threads'][$thread['tid']]))
			{
				continue;
			}
			$action = $mybb->input['threads'][$thread['tid']];
			if($action == "approve")
			{
				$threads_to_approve[] = $thread['tid'];
			}
			elseif($action == "delete")
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
			if($mybb->settings['soft_delete'] == 1)
			{
				$moderation->soft_delete_threads($threads_to_delete);
				log_moderator_action(array('tids' => $threads_to_delete), $lang->multi_soft_delete_threads);
			}
			else
			{
				foreach($threads_to_delete as $tid)
				{
					$moderation->delete_thread($tid);
				}
				log_moderator_action(array('tids' => $threads_to_delete), $lang->multi_delete_threads);
			}
		}

		$plugins->run_hooks('modcp_do_modqueue_end');

		redirect("modcp.php?action=modqueue", $lang->redirect_threadsmoderated);
	}
	elseif(!empty($mybb->input['posts']))
	{
		$posts = array_map("intval", array_keys($mybb->input['posts']));
		// Fetch posts
		$posts_to_approve = $posts_to_delete = array();
		$query = $db->simple_select("posts", "pid", "pid IN (".implode(",", $posts)."){$flist_queue_posts}");
		while($post = $db->fetch_array($query))
		{
			if(!isset($mybb->input['posts'][$post['pid']]))
			{
				continue;
			}
			$action = $mybb->input['posts'][$post['pid']];
			if($action == "approve")
			{
				$posts_to_approve[] = $post['pid'];
			}
			elseif($action == "delete" && $mybb->settings['soft_delete'] != 1)
			{
				$moderation->delete_post($post['pid']);
			}
			elseif($action == "delete")
			{
				$posts_to_delete[] = $post['pid'];
			}
		}
		if(!empty($posts_to_approve))
		{
			$moderation->approve_posts($posts_to_approve);
			log_moderator_action(array('pids' => $posts_to_approve), $lang->multi_approve_posts);
		}
		if(!empty($posts_to_delete))
		{
			if($mybb->settings['soft_delete'] == 1)
			{
				$moderation->soft_delete_posts($posts_to_delete);
				log_moderator_action(array('pids' => $posts_to_delete), $lang->multi_soft_delete_posts);
			}
			else
			{
				log_moderator_action(array('pids' => $posts_to_delete), $lang->multi_delete_posts);
			}
		}

		$plugins->run_hooks('modcp_do_modqueue_end');

		redirect("modcp.php?action=modqueue&type=posts", $lang->redirect_postsmoderated);
	}
	elseif(!empty($mybb->input['attachments']))
	{
		$attachments = array_map("intval", array_keys($mybb->input['attachments']));
		$query = $db->query("
            SELECT a.pid, a.aid
            FROM  ".TABLE_PREFIX."attachments a
            LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
            LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
            WHERE aid IN (".implode(",", $attachments)."){$tflist_queue_attach}
        ");
		while($attachment = $db->fetch_array($query))
		{
			if(!isset($mybb->input['attachments'][$attachment['aid']]))
			{
				continue;
			}
			$action = $mybb->input['attachments'][$attachment['aid']];
			if($action == "approve")
			{
				$db->update_query("attachments", array("visible" => 1), "aid='{$attachment['aid']}'");
			}
			elseif($action == "delete")
			{
				remove_attachment($attachment['pid'], '', $attachment['aid']);
			}
		}

		$plugins->run_hooks('modcp_do_modqueue_end');

		redirect("modcp.php?action=modqueue&type=attachments", $lang->redirect_attachmentsmoderated);
	}
}

if($mybb->input['action'] == "modqueue")
{
	$navsep = '';

	if($mybb->usergroup['canmanagemodqueue'] == 0)
	{
		error_no_permission();
	}

	if($counters['modqueue']['threads'] == 0 && $counters['modqueue']['posts'] == 0 && $counters['modqueue']['attachments'] == 0 && $mybb->usergroup['issupermod'] != 1)
	{
		error($lang->you_cannot_use_mod_queue);
	}

	$mybb->input['type'] = $mybb->get_input('type');
	$threadqueue = $postqueue = $attachmentqueue = '';
	if($mybb->input['type'] == "threads" || !$mybb->input['type'] && ($counters['modqueue']['threads'] > 0 || $mybb->usergroup['issupermod'] == 1))
	{
		if($counters['modqueue']['threads'] == 0 && $mybb->usergroup['issupermod'] != 1)
		{
			error($lang->you_cannot_moderate_threads);
		}

		$forum_cache = $cache->read("forums");

		$query = $db->simple_select("threads", "COUNT(tid) AS unapprovedthreads", "visible='0' {$flist_queue_threads}");
		$unapproved_threads = $db->fetch_field($query, "unapprovedthreads");

		// Figure out if we need to display multiple pages.
		if($mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', MyBB::INPUT_INT);
		}

		$perpage = $mybb->settings['threadsperpage'];
		$pages = $unapproved_threads / $perpage;
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

		$multipage = multipage($unapproved_threads, $perpage, $page, "modcp.php?action=modqueue&type=threads");

		$threads = [];
		$query = $db->query("
            SELECT t.tid, t.dateline, t.fid, t.subject, t.username AS threadusername, p.message AS postmessage, u.username AS username, t.uid
            FROM ".TABLE_PREFIX."threads t
            LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=t.firstpost)
            LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
            WHERE t.visible='0' {$tflist_queue_threads}
            ORDER BY t.lastpost DESC
            LIMIT {$start}, {$perpage}
        ");
		while($thread = $db->fetch_array($query))
		{
			$thread['subject'] = $parser->parse_badwords($thread['subject']);
			$thread['threadlink'] = get_thread_link($thread['tid']);
			$thread['forum_link'] = get_forum_link($thread['fid']);
			$thread['forum_name'] = $forum_cache[$thread['fid']]['name'];
			$thread['threaddate'] = my_date('relative', $thread['dateline']);

			if($thread['username'] == "")
			{
				if($thread['threadusername'] != "")
				{
					$thread['profile_link'] = $thread['threadusername'];
				}
				else
				{
					$thread['profile_link'] = $lang->guest;
				}
			}
			else
			{
				$thread['profile_link'] = build_profile_link($thread['username'], $thread['uid']);
			}

			$threads[] = $thread;
		}

		if(!$threads && $mybb->input['type'] == "threads")
		{
			$threads = true;
		}

		if($threads)
		{
			add_breadcrumb($lang->mcp_nav_modqueue_threads, "modcp.php?action=modqueue&amp;type=threads");

			$plugins->run_hooks('modcp_modqueue_threads_end');

			$navlink['post'] = false;
			if($counters['modqueue']['posts'] > 0 || $mybb->usergroup['issupermod'] == 1)
			{
				$navlink['post'] = true;
			}

			$navlink['attachment'] = false;
			if($mybb->settings['enableattachments'] == 1 && ($counters['modqueue']['attachments'] > 0 || $mybb->usergroup['issupermod'] == 1))
			{
				$navlink['attachment'] = true;
			}

			$threadqueue = true;
			output_page(\MyBB\template('modcp/modqueue_threads.twig', [
				'threadqueue' => $threadqueue,
				'threads' => $threads,
				'multipage' => $multipage,
				'navlink' => $navlink,
			]));
		}
	}

	if($mybb->input['type'] == "posts" || (!$mybb->input['type'] && !$threadqueue && ($counters['modqueue']['posts'] > 0 || $mybb->usergroup['issupermod'] == 1)))
	{
		if($counters['modqueue']['posts'] == 0 && $mybb->usergroup['issupermod'] != 1)
		{
			error($lang->you_cannot_moderate_posts);
		}

		$forum_cache = $cache->read("forums");

		$query = $db->query("
            SELECT COUNT(pid) AS unapprovedposts
            FROM  ".TABLE_PREFIX."posts p
            LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
            WHERE p.visible='0' {$tflist_queue_posts} AND t.firstpost != p.pid
        ");
		$unapproved_posts = $db->fetch_field($query, "unapprovedposts");

		// Figure out if we need to display multiple pages.
		if($mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', MyBB::INPUT_INT);
		}

		$perpage = $mybb->settings['postsperpage'];
		$pages = $unapproved_posts / $perpage;
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

		$multipage = multipage($unapproved_posts, $perpage, $page, "modcp.php?action=modqueue&amp;type=posts");

		$posts = [];
		$query = $db->query("
			SELECT p.pid, p.subject, p.message, p.username AS postusername, t.subject AS threadsubject, t.tid, u.username, p.uid, t.fid, p.dateline
			FROM  ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE p.visible='0' {$tflist_queue_posts} AND t.firstpost != p.pid
			ORDER BY p.dateline DESC, p.pid DESC
			LIMIT {$start}, {$perpage}
		");
		while($post = $db->fetch_array($query))
		{
			$post['threadsubject'] = $parser->parse_badwords($post['threadsubject']);
			$post['subject'] = $parser->parse_badwords($post['subject']);
			$post['threadlink'] = get_thread_link($post['tid']);
			$post['postlink'] = get_post_link($post['pid'], $post['tid']);
			$post['forum_link'] = get_forum_link($post['fid']);
			$post['forum_name'] = $forum_cache[$post['fid']]['name'];
			$post['postdate'] = my_date('relative', $post['dateline']);

			if($post['username'] == "")
			{
				if($post['postusername'] != "")
				{
					$post['profile_link'] = $post['postusername'];
				}
				else
				{
					$post['profile_link'] = $lang->guest;
				}
			}
			else
			{
				$post['profile_link'] = build_profile_link($post['username'], $post['uid']);
			}

			$posts[] = $post;
		}

		if(!$posts && $mybb->input['type'] == "posts")
		{
			$posts = true;
		}

		if($posts)
		{
			add_breadcrumb($lang->mcp_nav_modqueue_posts, "modcp.php?action=modqueue&amp;type=posts");

			$plugins->run_hooks('modcp_modqueue_posts_end');

			$navlink['thread'] = false;
			if($counters['modqueue']['threads'] > 0 || $mybb->usergroup['issupermod'] == 1)
			{
				$navlink['thread'] = true;
			}

			$navlink['attachment'] = false;
			if($mybb->settings['enableattachments'] == 1 && ($counters['modqueue']['attachments'] > 0 || $mybb->usergroup['issupermod'] == 1))
			{
				$navlink['attachment'] = true;
			}

			$postqueue = true;
			output_page(\MyBB\template('modcp/modqueue_posts.twig', [
				'postqueue' => $postqueue,
				'posts' => $posts,
				'multipage' => $multipage,
				'navlink' => $navlink,
			]));
		}
	}

	if($mybb->input['type'] == "attachments" || (!$mybb->input['type'] && !$postqueue && !$threadqueue && $mybb->settings['enableattachments'] == 1 && ($counters['modqueue']['attachments'] > 0 || $mybb->usergroup['issupermod'] == 1)))
	{
		if($mybb->settings['enableattachments'] == 0)
		{
			error($lang->attachments_disabled);
		}

		if($counters['modqueue']['attachments'] == 0 && $mybb->usergroup['issupermod'] != 1)
		{
			error($lang->you_cannot_moderate_attachments);
		}

		$query = $db->query("
            SELECT COUNT(aid) AS unapprovedattachments
            FROM  ".TABLE_PREFIX."attachments a
            LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
            LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
            WHERE a.visible='0'{$tflist_queue_attach}
        ");
		$unapproved_attachments = $db->fetch_field($query, "unapprovedattachments");

		// Figure out if we need to display multiple pages.
		if($mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', MyBB::INPUT_INT);
		}

		$perpage = $mybb->settings['postsperpage'];
		$pages = $unapproved_attachments / $perpage;
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

		$multipage = multipage($unapproved_attachments, $perpage, $page, "modcp.php?action=modqueue&amp;type=attachments");

		$attachments = [];
		$query = $db->query("
            SELECT a.*, p.subject AS postsubject, p.dateline, p.uid, u.username, t.tid, t.subject AS threadsubject
            FROM  ".TABLE_PREFIX."attachments a
            LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
            LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
            LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
            WHERE a.visible='0'{$tflist_queue_attach}
            ORDER BY a.dateuploaded DESC
            LIMIT {$start}, {$perpage}
        ");
		while($attachment = $db->fetch_array($query))
		{
			if(!$attachment['dateuploaded'])
			{
				$attachment['dateuploaded'] = $attachment['dateline'];
			}

			$attachment['attachdate'] = my_date('relative', $attachment['dateuploaded']);

			$attachment['postsubject'] = $parser->parse_badwords($attachment['postsubject']);
			$attachment['threadsubject'] = $parser->parse_badwords($attachment['threadsubject']);
			$attachment['filesize'] = get_friendly_size($attachment['filesize']);

			$attachment['link'] = get_post_link($attachment['pid'], $attachment['tid'])."#pid{$attachment['pid']}";
			$attachment['thread_link'] = get_thread_link($attachment['tid']);
			$attachment['profile_link'] = build_profile_link($attachment['username'], $attachment['uid']);

			$attachments[] = $attachment;
		}

		if(!$attachments && $mybb->input['type'] == "attachments")
		{
			$attachments = true;
		}

		if($attachments)
		{
			add_breadcrumb($lang->mcp_nav_modqueue_attachments, "modcp.php?action=modqueue&amp;type=attachments");

			$plugins->run_hooks('modcp_modqueue_attachments_end');

			$navlink['thread'] = false;
			if($counters['modqueue']['threads'] > 0 || $mybb->usergroup['issupermod'] == 1)
			{
				$navlink['thread'] = true;
			}

			$navlink['post'] = false;
			if($counters['modqueue']['posts'] > 0 || $mybb->usergroup['issupermod'] == 1)
			{
				$navlink['post'] = true;
			}

			$attachmentqueue = true;
			output_page(\MyBB\template('modcp/modqueue_attachments.twig', [
				'attachmentqueue' => $attachmentqueue,
				'attachments' => $attachments,
				'multipage' => $multipage,
				'navlink' => $navlink,
			]));
		}
	}

	// Still nothing? All queues are empty! :-D
	if(!$threadqueue && !$postqueue && !$attachmentqueue)
	{
		add_breadcrumb($lang->mcp_nav_modqueue, "modcp.php?action=modqueue");

		$plugins->run_hooks('modcp_modqueue_end');

		output_page(\MyBB\template('modcp/modqueue_empty.twig'));
	}
}

if($mybb->input['action'] == "do_editprofile")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['caneditprofiles'] == 0)
	{
		error_no_permission();
	}

	$user = get_user($mybb->input['uid']);
	if(!$user)
	{
		error($lang->error_nomember);
	}

	// Check if the current user has permission to edit this user
	if(!modcp_can_manage_user($user['uid']))
	{
		error_no_permission();
	}

	$plugins->run_hooks('modcp_do_editprofile_start');

	if($mybb->get_input('away', MyBB::INPUT_INT) == 1 && $mybb->settings['allowaway'] != 0)
	{
		$awaydate = TIME_NOW;
		if(!empty($mybb->input['awayday']))
		{
			// If the user has indicated that they will return on a specific day, but not month or year, assume it is current month and year
			if(!$mybb->get_input('awaymonth', MyBB::INPUT_INT))
			{
				$mybb->input['awaymonth'] = my_date('n', $awaydate);
			}
			if(!$mybb->get_input('awayyear', MyBB::INPUT_INT))
			{
				$mybb->input['awayyear'] = my_date('Y', $awaydate);
			}

			$return_month = (int)substr($mybb->get_input('awaymonth'), 0, 2);
			$return_day = (int)substr($mybb->get_input('awayday'), 0, 2);
			$return_year = min((int)$mybb->get_input('awayyear'), 9999);

			// Check if return date is after the away date.
			$returntimestamp = gmmktime(0, 0, 0, $return_month, $return_day, $return_year);
			$awaytimestamp = gmmktime(0, 0, 0, my_date('n', $awaydate), my_date('j', $awaydate), my_date('Y', $awaydate));
			if($return_year < my_date('Y', $awaydate) || ($returntimestamp < $awaytimestamp && $return_year == my_date('Y', $awaydate)))
			{
				error($lang->error_modcp_return_date_past);
			}

			$returndate = "{$return_day}-{$return_month}-{$return_year}";
		}
		else
		{
			$returndate = "";
		}
		$away = array(
			"away" => 1,
			"date" => $awaydate,
			"returndate" => $returndate,
			"awayreason" => $mybb->get_input('awayreason')
		);
	}
	else
	{
		$away = array(
			"away" => 0,
			"date" => '',
			"returndate" => '',
			"awayreason" => ''
		);
	}

	// Set up user handler.
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler('update');

	// Set the data for the new user.
	$updated_user = array(
		"uid" => $user['uid'],
		"profile_fields" => $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY),
		"profile_fields_editable" => true,
		"website" => $mybb->get_input('website'),
		"signature" => $mybb->get_input('signature'),
		"usernotes" => $mybb->get_input('usernotes'),
		"away" => $away
	);

	$updated_user['birthday'] = array(
		"day" => $mybb->get_input('bday1', MyBB::INPUT_INT),
		"month" => $mybb->get_input('bday2', MyBB::INPUT_INT),
		"year" => $mybb->get_input('bday3', MyBB::INPUT_INT)
	);

	if(!empty($mybb->input['usertitle']))
	{
		$updated_user['usertitle'] = $mybb->get_input('usertitle');
	}
	elseif(!empty($mybb->input['reverttitle']))
	{
		$updated_user['usertitle'] = '';
	}

	if(!empty($mybb->input['remove_avatar']))
	{
		$updated_user['avatarurl'] = '';
	}

	// Set the data of the user in the datahandler.
	$userhandler->set_data($updated_user);
	$errors = array();

	// Validate the user and get any errors that might have occurred.
	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
		$mybb->input['action'] = "editprofile";
	}
	else
	{
		// Are we removing an avatar from this user?
		if(!empty($mybb->input['remove_avatar']))
		{
			$extra_user_updates = array(
				"avatar" => "",
				"avatardimensions" => "",
				"avatartype" => ""
			);
			remove_avatars($user['uid']);
		}

		// Moderator "Options" (suspend signature, suspend/moderate posting)
		$modoptions = array(
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
		foreach($modoptions as $option)
		{
			$mybb->input[$option['time']] = $mybb->get_input($option['time'], MyBB::INPUT_INT);
			$mybb->input[$option['period']] = $mybb->get_input($option['period']);
			if(empty($mybb->input[$option['action']]))
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
			else
			{
				if($mybb->input[$option['time']] == 0 && $mybb->input[$option['period']] != "never" && $user[$option['update_field']] != 1)
				{
					// User has selected a type of ban, but not entered a valid time frame
					$string = $option['action']."_error";
					$errors[] = $lang->$string;
				}
				else
				{
					$suspend_length = fetch_time_length((int)$mybb->input[$option['time']], $mybb->input[$option['period']]);

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
		if(isset($extra_user_updates) && !empty($extra_user_updates['moderateposts']) && !empty($extra_user_updates['suspendposting']))
		{
			$errors[] = $lang->suspendmoderate_error;
		}

		if(is_array($errors) && !empty($errors))
		{
			$mybb->input['action'] = "editprofile";
		}
		else
		{
			$plugins->run_hooks('modcp_do_editprofile_update');

			// Continue with the update if there is no errors
			$user_info = $userhandler->update_user();
			if(!empty($extra_user_updates))
			{
				$db->update_query("users", $extra_user_updates, "uid='{$user['uid']}'");
			}
			log_moderator_action(array("uid" => $user['uid'], "username" => $user['username']), $lang->edited_user);

			$plugins->run_hooks('modcp_do_editprofile_end');

			redirect("modcp.php?action=finduser", $lang->redirect_user_updated);
		}
	}
}

if($mybb->input['action'] == "editprofile")
{
	if($mybb->usergroup['caneditprofiles'] == 0)
	{
		error_no_permission();
	}

	add_breadcrumb($lang->mcp_nav_editprofile, "modcp.php?action=editprofile");

	$user = get_user($mybb->get_input('uid', MyBB::INPUT_INT));
	if(!$user)
	{
		error($lang->error_nomember);
	}

	// Check if the current user has permission to edit this user
	if(!modcp_can_manage_user($user['uid']))
	{
		error_no_permission();
	}

	if(!my_validate_url($user['website']))
	{
		$user['website'] = '';
	}

	if(!$errors)
	{
		$mybb->input = array_merge($user, $mybb->input);
		$bday = explode("-", $user['birthday']);
		if(!isset($bday[1]))
		{
			$bday[1] = 0;
		}
		if(!isset($bday[2]))
		{
			$bday[2] = '';
		}
		list($mybb->input['bday1'], $mybb->input['bday2'], $mybb->input['bday3']) = $bday;
	}
	else
	{
		$errors = inline_error($errors);

		$user = $mybb->input;
		$bday = [];
		$bday[0] = $mybb->get_input('bday1', MyBB::INPUT_INT);
		$bday[1] = $mybb->get_input('bday2', MyBB::INPUT_INT);
		$bday[2] = $mybb->get_input('bday3', MyBB::INPUT_INT);

		$returndate = [];
		$returndate[0] = $mybb->get_input('awayday', MyBB::INPUT_INT);
		$returndate[1] = $mybb->get_input('awaymonth', MyBB::INPUT_INT);
		$returndate[2] = $mybb->get_input('awayyear', MyBB::INPUT_INT);
		$user['awayreason'] = htmlspecialchars_uni($mybb->get_input('awayreason'));
	}

	// Sanitize all input
	foreach(array('usertitle', 'website', 'signature', 'birthday_day', 'birthday_month', 'birthday_year') as $field)
	{
		$mybb->input[$field] = htmlspecialchars_uni($mybb->get_input($field));
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
		$user['defaulttitle'] = htmlspecialchars_uni($display_group['usertitle']);
	}
	else
	{
		// Go for post count title if a group default isn't set
		$usertitles = $cache->read('usertitles');

		foreach($usertitles as $title)
		{
			if($title['posts'] <= $user['postnum'])
			{
				$user['defaulttitle'] = $title['title'];
				break;
			}
		}
	}

	$user['usertitle'] = htmlspecialchars_uni($user['usertitle']);

	if(empty($user['usertitle']))
	{
		$lang->current_custom_usertitle = '';
	}

	if($mybb->settings['allowaway'] != 0)
	{
		if(!$returndate)
		{
			$returndate = explode("-", $mybb->user['returndate']);
		}
		if(!isset($returndate[1]))
		{
			$returndate[1] = 0;
		}
		if(!isset($returndate[2]))
		{
			$returndate[2] = '';
		}
	}

	$plugins->run_hooks('modcp_editprofile_start');

	// Fetch profile fields
	$query = $db->simple_select("userfields", "*", "ufid='{$user['uid']}'");
	$user_fields = $db->fetch_array($query);
	if(count($user_fields) > 0)
	{
		$user = array_merge($user, $user_fields);
	}

	$requiredfields = $customfields = $contactfields = [];
	$mybb->input['profile_fields'] = $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY);

	$pfcache = $cache->read('profilefields');

	if(is_array($pfcache))
	{
		foreach($pfcache as $profilefield)
		{
			$thing = explode("\n", $profilefield['type'], "2");
			$profilefield['attributes']['type'] = $thing[0];
			$profilefield['attributes']['options'] = [];
			if(isset($thing[1]))
			{
				$profilefield['attributes']['options'] = $thing[1];
			}

			if($profilefield['required'] == 1)
			{
				$requiredfields[] = $profilefield;
			}
			elseif($profilefield['contact'] == 1)
			{
				$contactfields[] = $profilefield;
			}
			else
			{
				$customfields[] = $profilefield;
			}
		}
	}

	$user['username'] = htmlspecialchars_uni($user['username']);
	$lang->edit_profile = $lang->sprintf($lang->edit_profile, $user['username']);
	$user['profilelink'] = build_profile_link(format_name($user['username'], $user['usergroup'], $user['displaygroup']), $user['uid']);

	$user['signature'] = htmlspecialchars_uni($user['signature']);
	$codebuttons = build_mycode_inserter("signature");

	$modoptions = array(
		array(
			"action" => "moderateposting", // The input action for this option
			"option" => "moderateposts", // The field in the database that this option relates to
			"time" => "modpost_time", // The time we've entered
			"length" => "moderationtime", // The length of suspension field in the database
			"select_option" => "modpost", // The name of the select box of this option
			"lang" => [
				"title" => "moderate_posts",
				"length" => "modpost_length"
			]
		),
		array(
			"action" => "suspendposting",
			"option" => "suspendposting",
			"time" => "suspost_time",
			"length" => "suspensiontime",
			"select_option" => "suspost",
			"lang" => [
				"title" => "suspend_posts",
				"length" => "suspend_length"
			]
		)
	);

	$periods = array(
		"hours" => $lang->expire_hours,
		"days" => $lang->expire_days,
		"weeks" => $lang->expire_weeks,
		"months" => $lang->expire_months,
		"never" => $lang->expire_permanent
	);

	$user['usernotes'] = htmlspecialchars_uni($user['usernotes']);

	if(!isset($newtitle))
	{
		$newtitle = '';
	}

	$plugins->run_hooks('modcp_editprofile_end');

	output_page(\MyBB\template('modcp/editprofile.twig', [
		'user' => $user,
		'customFields' => $customfields,
		'requiredFields' => $requiredfields,
		'contactFields' => $contactfields,
		'periods' => $periods,
		'modOptions' => $modoptions,
		'codebuttons' => $codebuttons
	]));
}

if($mybb->input['action'] == "finduser")
{
	if($mybb->usergroup['caneditprofiles'] == 0)
	{
		error_no_permission();
	}

	add_breadcrumb($lang->mcp_nav_users, "modcp.php?action=finduser");

	$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if(!$perpage || $perpage <= 0)
	{
		$perpage = $mybb->settings['threadsperpage'];
	}
	$where = '';

	if(isset($mybb->input['username']))
	{
		switch($db->type)
		{
			case 'mysql':
			case 'mysqli':
				$field = 'username';
				break;
			default:
				$field = 'LOWER(username)';
				break;
		}
		$where = " AND {$field} LIKE '%".my_strtolower($db->escape_string_like($mybb->get_input('username')))."%'";
	}

	// Sort order & direction
	switch($mybb->get_input('sortby'))
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
	$order = $mybb->get_input('order');
	if($order != "asc")
	{
		$order = "desc";
	}

	$query = $db->simple_select("users", "COUNT(uid) AS count", "1=1 {$where}");
	$user_count = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->get_input('page') != "last")
	{
		$page = $mybb->get_input('page');
	}

	$pages = $user_count / $perpage;
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

	$page_url = 'modcp.php?action=finduser';
	foreach(array('username', 'sortby', 'order') as $field)
	{
		if(!empty($mybb->input[$field]))
		{
			$page_url .= "&amp;{$field}=".$mybb->input[$field];
		}
	}

	$multipage = multipage($user_count, $perpage, $page, $page_url);

	$usergroups_cache = $cache->read("usergroups");

	$plugins->run_hooks('modcp_finduser_start');

	// Fetch out results
	$query = $db->simple_select("users", "*", "1=1 {$where}", array("order_by" => $sortby, "order_dir" => $order, "limit" => $perpage, "limit_start" => $start));
	$users = [];
	while($user = $db->fetch_array($query))
	{
		$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
		$user['postnum'] = my_number_format($user['postnum']);

		$user['ugroup'] = $usergroups_cache[$user['usergroup']]['title'];
		$users[] = $user;
	}

	$plugins->run_hooks('modcp_finduser_end');

	output_page(\MyBB\template('modcp/finduser.twig', [
		'users' => $users,
		'multipage' => $multipage
	]));
}

if($mybb->input['action'] == "warninglogs")
{
	if($mybb->usergroup['canviewwarnlogs'] == 0)
	{
		error_no_permission();
	}

	add_breadcrumb($lang->mcp_nav_warninglogs, "modcp.php?action=warninglogs");

	// Filter options
	$where_sql = '';
	$mybb->input['filter'] = $mybb->get_input('filter', MyBB::INPUT_ARRAY);
	$mybb->input['search'] = $mybb->get_input('search', MyBB::INPUT_ARRAY);
	if(!empty($mybb->input['filter']['username']))
	{
		$search_user = get_user_by_username($mybb->input['filter']['username']);

		$mybb->input['filter']['uid'] = (int)$search_user['uid'];
		$mybb->input['filter']['username'] = htmlspecialchars_uni($mybb->input['filter']['username']);
	}
	else
	{
		$mybb->input['filter']['username'] = '';
	}

	if(!empty($mybb->input['filter']['uid']))
	{
		$search['uid'] = (int)$mybb->input['filter']['uid'];
		$where_sql .= " AND w.uid='{$search['uid']}'";
		if(!isset($mybb->input['search']['username']))
		{
			$user = get_user($mybb->input['search']['uid']);
			$mybb->input['search']['username'] = htmlspecialchars_uni($user['username']);
		}
	}
	else
	{
		$mybb->input['filter']['uid'] = '';
	}

	if(!empty($mybb->input['filter']['mod_username']))
	{
		$mod_user = get_user_by_username($mybb->input['filter']['mod_username']);

		$mybb->input['filter']['mod_uid'] = (int)$mod_user['uid'];
		$mybb->input['filter']['mod_username'] = htmlspecialchars_uni($mybb->input['filter']['mod_username']);
	}
	else
	{
		$mybb->input['filter']['mod_username'] = '';
	}

	if(!empty($mybb->input['filter']['mod_uid']))
	{
		$search['mod_uid'] = (int)$mybb->input['filter']['mod_uid'];
		$where_sql .= " AND w.issuedby='{$search['mod_uid']}'";
		if(!isset($mybb->input['search']['mod_username']))
		{
			$mod_user = get_user($mybb->input['search']['uid']);
			$mybb->input['search']['mod_username'] = htmlspecialchars_uni($mod_user['username']);
		}
	}
	else
	{
		$mybb->input['filter']['mod_uid'] = '';
	}

	if(!empty($mybb->input['filter']['reason']))
	{
		$search['reason'] = $db->escape_string_like($mybb->input['filter']['reason']);
		$where_sql .= " AND (w.notes LIKE '%{$search['reason']}%' OR t.title LIKE '%{$search['reason']}%' OR w.title LIKE '%{$search['reason']}%')";
	}
	else
	{
		$mybb->input['filter']['reason'] = '';
	}

	$select['sortby'] = array('username' => false, 'expires' => false, 'issuedby' => false, 'dateline' => false);
	if(!isset($mybb->input['filter']['sortby']))
	{
		$mybb->input['filter']['sortby'] = '';
	}
	switch($mybb->input['filter']['sortby'])
	{
		case "username":
			$sortby = "u.username";
			$select['sortby']['username'] = true;
			break;
		case "expires":
			$sortby = "w.expires";
			$select['sortby']['expires'] = true;
			break;
		case "issuedby":
			$sortby = "i.username";
			$select['sortby']['issuedby'] = true;
			break;
		default: // "dateline"
			$sortby = "w.dateline";
			$select['sortby']['dateline'] = true;
	}

	if(!isset($mybb->input['filter']['order']))
	{
		$mybb->input['filter']['order'] = '';
	}
	$order = $mybb->input['filter']['order'];
	$select['order'] = array('asc' => false, 'desc' => false);
	if($order != "asc")
	{
		$order = "desc";
		$select['order']['desc'] = true;
	}
	else
	{
		$select['order']['asc'] = true;
	}

	$plugins->run_hooks('modcp_warninglogs_start');

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
	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	if($page <= 0)
	{
		$page = 1;
	}
	$per_page = 20;
	if(isset($mybb->input['filter']['per_page']) && (int)$mybb->input['filter']['per_page'] > 0)
	{
		$per_page = (int)$mybb->input['filter']['per_page'];
	}
	$start = ($page-1) * $per_page;
	$pages = ceil($total_warnings / $per_page);
	if($page > $pages)
	{
		$start = 0;
		$page = 1;
	}
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
	$warning_list = [];
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

	while($row = $db->fetch_array($query))
	{
		$username = format_name($row['username'], $row['usergroup'], $row['displaygroup']);
		$row['username_link'] = build_profile_link($username, $row['uid']);

		$mod_username = format_name($row['mod_username'], $row['mod_usergroup'], $row['mod_displaygroup']);
		$row['mod_username_link'] = build_profile_link($mod_username, $row['mod_uid']);

		$row['issued_date'] = my_date('normal', $row['dateline']);

		if($row['daterevoked'] > 0)
		{
			$row['revoked_date'] = my_date('relative', $row['daterevoked']);
		}

		if($row['expired'] == 1)
		{
			$row['expire_date'] = $lang->expired;
		}
		elseif($row['expires'] > 0)
		{
			$row['expire_date'] = nice_time($row['expires'] - TIME_NOW);
		}
		else
		{
			$row['expire_date'] = $lang->never;
		}

		if(empty($row['title']))
		{
			$row['title'] = $row['custom_title'];
		}

		if($row['points'] >= 0)
		{
			$row['points'] = '+'.$row['points'];
		}

		$warning_list[] = $row;
	}

	$plugins->run_hooks('modcp_warninglogs_end');

	$select['username'] = $mybb->input['filter']['username'];
	$select['mod_username'] = $mybb->input['filter']['mod_username'];
	$select['reason'] = $mybb->input['filter']['reason'];
	$select['per_page'] = $per_page;

	output_page(\MyBB\template('modcp/warninglogs.twig', [
		'multipage' => $multipage,
		'select' => $select,
		'warning_list' => $warning_list,
	]));
}

if($mybb->input['action'] == "ipsearch")
{
	if($mybb->usergroup['canuseipsearch'] == 0)
	{
		error_no_permission();
	}

	add_breadcrumb($lang->mcp_nav_ipsearch, "modcp.php?action=ipsearch");

	$ipsearch['results'] = false;
	$mybb->input['ipaddress'] = $mybb->get_input('ipaddress');
	if($mybb->input['ipaddress'])
	{
		$ipsearch['results'] = true;
		if(!is_array($groupscache))
		{
			$groupscache = $cache->read("usergroups");
		}

		$ipsearch['ipaddress'] = $mybb->input['ipaddress'];

		$ip_range = fetch_ip_range($mybb->input['ipaddress']);

		$post_results = $user_results = 0;

		// Searching post IP addresses
		if(isset($mybb->input['search_posts']))
		{
			if($ip_range)
			{
				if(!is_array($ip_range))
				{
					$post_ip_sql = "p.ipaddress=".$db->escape_binary($ip_range);
				}
				else
				{
					$post_ip_sql = "p.ipaddress BETWEEN ".$db->escape_binary($ip_range[0])." AND ".$db->escape_binary($ip_range[1]);
				}
			}

			$plugins->run_hooks('modcp_ipsearch_posts_start');

			if($post_ip_sql)
			{
				$where_sql = '';

				$unviewable_forums = get_unviewable_forums(true);

				if($unviewable_forums)
				{
					$where_sql .= " AND p.fid NOT IN ({$unviewable_forums})";
				}

				if($inactiveforums)
				{
					$where_sql .= " AND p.fid NOT IN ({$inactiveforums})";
				}

				// Check group permissions if we can't view threads not started by us
				$onlyusfids = array();
				$group_permissions = forum_permissions();
				foreach($group_permissions as $fid => $forumpermissions)
				{
					if(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] == 1)
					{
						$onlyusfids[] = $fid;
					}
				}

				if(!empty($onlyusfids))
				{
					$where_sql .= " AND ((t.fid IN(".implode(',', $onlyusfids).") AND t.uid='{$mybb->user['uid']}') OR t.fid NOT IN(".implode(',', $onlyusfids)."))";
				}

				// Moderators can view unapproved/deleted posts
				if($mybb->usergroup['issupermod'] != 1)
				{
					$unapprove_forums = array();
					$deleted_forums = array();
					$visible_sql = " AND (p.visible = 1 AND t.visible = 1)";
					$query = $db->simple_select("moderators", "fid, canviewunapprove, canviewdeleted", "(id IN ({$mybb->usergroup['all_usergroups']}) AND isgroup='0') OR (id='{$mybb->user['usergroup']}' AND isgroup='1')");
					while($moderator = $db->fetch_array($query))
					{
						if($moderator['canviewunapprove'] == 1)
						{
							$unapprove_forums[] = $moderator['fid'];
						}

						if($moderator['canviewdeleted'] == 1)
						{
							$deleted_forums[] = $moderator['fid'];
						}
					}

					if(!empty($unapprove_forums))
					{
						$visible_sql .= " OR (p.visible = 0 AND p.fid IN(".implode(',', $unapprove_forums).")) OR (t.visible = 0 AND t.fid IN(".implode(',', $unapprove_forums)."))";
					}
					if(!empty($deleted_forums))
					{
						$visible_sql .= " OR (p.visible = -1 AND p.fid IN(".implode(',', $deleted_forums).")) OR (t.visible = -1 AND t.fid IN(".implode(',', $deleted_forums)."))";
					}
				}
				else
				{
					// Super moderators (and admins)
					$visible_sql = " AND p.visible >= -1";
				}

				$query = $db->query("
                    SELECT COUNT(p.pid) AS count
                    FROM ".TABLE_PREFIX."posts p
                    LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = p.tid)
                    WHERE {$post_ip_sql}{$where_sql}{$visible_sql}
                ");
				$post_results = $db->fetch_field($query, "count");
			}
		}

		// Searching user IP addresses
		if(isset($mybb->input['search_users']))
		{
			if($ip_range)
			{
				if(!is_array($ip_range))
				{
					$user_ip_sql = "regip=".$db->escape_binary($ip_range)." OR lastip=".$db->escape_binary($ip_range);
				}
				else
				{
					$user_ip_sql = "regip BETWEEN ".$db->escape_binary($ip_range[0])." AND ".$db->escape_binary($ip_range[1])." OR lastip BETWEEN ".$db->escape_binary($ip_range[0])." AND ".$db->escape_binary($ip_range[1]);
				}
			}

			$plugins->run_hooks('modcp_ipsearch_users_start');

			if($user_ip_sql)
			{
				$query = $db->simple_select('users', 'COUNT(uid) AS count', $user_ip_sql);

				$user_results = $db->fetch_field($query, "count");
			}
		}

		$total_results = $post_results + $user_results;

		if(!$total_results)
		{
			$total_results = 1;
		}

		// Now we have the result counts, paginate
		$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
		if(!$perpage || $perpage <= 0)
		{
			$perpage = $mybb->settings['threadsperpage'];
		}

		// Figure out if we need to display multiple pages.
		if($mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', MyBB::INPUT_INT);
		}

		$pages = $total_results / $perpage;
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

		$page_url = "modcp.php?action=ipsearch&amp;perpage={$perpage}";
		foreach(array('ipaddress', 'search_users', 'search_posts') as $input)
		{
			if(!empty($mybb->input[$input]))
			{
				$page_url .= "&amp;{$input}=".urlencode($mybb->input[$input]);
			}
		}
		$multipage = multipage($total_results, $perpage, $page, $page_url);

		$post_limit = $perpage;
		$ipresults = [];
		if(isset($mybb->input['search_users']) && $user_results && $start <= $user_results)
		{
			$query = $db->simple_select('users', 'username, uid, regip, lastip', $user_ip_sql,
				array('order_by' => 'regdate', 'order_dir' => 'DESC', 'limit_start' => $start, 'limit' => $perpage));

			while($ipaddress = $db->fetch_array($query))
			{
				$result = false;
				$ipaddress['profile_link'] = build_profile_link($ipaddress['username'], $ipaddress['uid']);
				$ipaddress['type'] = $ipaddress['ip'] = false;
				if(is_array($ip_range))
				{
					if(strcmp($ip_range[0], $ipaddress['regip']) <= 0 && strcmp($ip_range[1], $ipaddress['regip']) >= 0)
					{
						$ipaddress['type'] = 'regip';
						$ipaddress['ip'] = my_inet_ntop($db->unescape_binary($ipaddress['regip']));
					}
					elseif(strcmp($ip_range[0], $ipaddress['lastip']) <= 0 && strcmp($ip_range[1], $ipaddress['lastip']) >= 0)
					{
						$ipaddress['type'] = 'lastip';
						$ipaddress['ip'] = my_inet_ntop($db->unescape_binary($ipaddress['lastip']));
					}
				}
				elseif($ipaddress['regip'] == $ip_range)
				{
					$ipaddress['type'] = 'regip';
					$ipaddress['ip'] = my_inet_ntop($db->unescape_binary($ipaddress['regip']));
				}
				elseif($ipaddress['lastip'] == $ip_range)
				{
					$ipaddress['type'] = 'lastip';
					$ipaddress['ip'] = my_inet_ntop($db->unescape_binary($ipaddress['lastip']));
				}

				if($ipaddress['ip'])
				{
					$ipresults[] = $ipaddress;
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
			$post_start = $start - $user_results;
			if($post_start < 0)
			{
				$post_start = 0;
			}
		}
		if(isset($mybb->input['search_posts']) && $post_results && (!isset($mybb->input['search_users']) || (isset($mybb->input['search_users']) && $post_limit > 0)))
		{
			$ipaddresses = $tids = $uids = array();

			$query = $db->query("
				SELECT p.username AS postusername, p.uid, p.subject, p.pid, p.tid, p.ipaddress
				FROM ".TABLE_PREFIX."posts p
				LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = p.tid)
				WHERE {$post_ip_sql}{$where_sql}{$visible_sql}
				ORDER BY p.dateline DESC, p.pid DESC
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
					$ipaddress['ip'] = my_inet_ntop($db->unescape_binary($ipaddress['ipaddress']));
					if(!$ipaddress['username'])
					{
						$ipaddress['username'] = $ipaddress['postusername']; // Guest username support
					}

					if(!$ipaddress['subject'])
					{
						$ipaddress['subject'] = "RE: {$ipaddress['threadsubject']}";
					}

					$ipaddress['postlink'] = get_post_link($ipaddress['pid'], $ipaddress['tid']);
					$ipaddress['subject'] = $parser->parse_badwords($ipaddress['subject']);
					$ipaddress['profilelink'] = build_profile_link($ipaddress['username'], $ipaddress['uid']);

					$ipresults[] = $ipaddress;
				}
			}
		}

		$ipsearch['info_link'] = false;
		if(!strstr($mybb->input['ipaddress'], "*") && !strstr($mybb->input['ipaddress'], "/"))
		{
			$ipsearch['info_link'] = true;
			$ipsearch['ipaddress_url'] = urlencode($mybb->input['ipaddress']);
		}
	}

	// Fetch filter options
	if(!$mybb->input['ipaddress'])
	{
		$mybb->input['search_posts'] = 1;
		$mybb->input['search_users'] = 1;
	}

	$ipsearch['usersearch'] = $ipsearch['postsearch'] = false;
	if(isset($mybb->input['search_posts']))
	{
		$ipsearch['postsearch'] = true;
	}
	if(isset($mybb->input['search_users']))
	{
		$ipsearch['usersearch'] = true;
	}

	$plugins->run_hooks('modcp_ipsearch_end');

	output_page(\MyBB\template('modcp/ipsearch.twig', [
		'ipsearch' => $ipsearch,
		'multipage' => $multipage,
		'ipresults' => $ipresults,
	]));
}

if($mybb->input['action'] == "iplookup")
{
	if($mybb->usergroup['canuseipsearch'] == 0)
	{
		error_no_permission();
	}

	$modal = $mybb->get_input('modal', MyBB::INPUT_INT);
	$ipaddress['ipaddress'] = $mybb->get_input('ipaddress');
	$ipaddress['location'] = $ipaddress['host_name'] = $lang->na;

	if(!$ipaddress['ipaddress'])
	{
		error($lang->error_missing_ipaddress);
	}

	if(!strstr($ipaddress['ipaddress'], "*"))
	{
		// Return GeoIP information if it is available to us
		if(function_exists('geoip_record_by_name'))
		{
			$ip_record = @geoip_record_by_name($ipaddress['ipaddress']);
			if($ip_record)
			{
				$ipaddress['location'] = utf8_encode($ip_record['country_name']);
				if($ip_record['city'])
				{
					$ipaddress['location'] .= $lang->comma.utf8_encode($ip_record['city']);
				}
			}
		}

		$ipaddress['host_name'] = @gethostbyaddr($ipaddress['ipaddress']);

		// gethostbyaddr returns the same ip on failure
		if($ipaddress['host_name'] == $ipaddress['ipaddress'])
		{
			$ipaddress['host_name'] = $lang->na;
		}
	}

	$plugins->run_hooks('modcp_iplookup_end');

	if($modal)
	{
		output_page(\MyBB\template('modcp/iplookup_modal.twig', [
			'ipaddress' => $ipaddress,
		]));
		exit;
	}
	else
	{
		output_page(\MyBB\template('modcp/iplookup.twig', [
			'ipaddress' => $ipaddress,
		]));
	}
}

if($mybb->input['action'] == "banning")
{
	if($mybb->usergroup['canbanusers'] == 0)
	{
		error_no_permission();
	}

	add_breadcrumb($lang->mcp_nav_banning, "modcp.php?action=banning");

	if(!$mybb->settings['threadsperpage'])
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	// Figure out if we need to display multiple pages.
	$perpage = $mybb->settings['threadsperpage'];
	if($mybb->get_input('page') != "last")
	{
		$page = $mybb->get_input('page', MyBB::INPUT_INT);
	}

	$query = $db->simple_select("banned", "COUNT(uid) AS count");
	$banned_count = $db->fetch_field($query, "count");

	$postcount = (int)$banned_count;
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

	$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=banning");

	$plugins->run_hooks('modcp_banning_start');

	$query = $db->query("
        SELECT b.*, a.username AS adminuser, u.username
        FROM ".TABLE_PREFIX."banned b
        LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid)
        LEFT JOIN ".TABLE_PREFIX."users a ON (b.admin=a.uid)
        ORDER BY dateline DESC
        LIMIT {$start}, {$perpage}
    ");

	// Get the banned users
	$bannedusers = [];
	while($banned = $db->fetch_array($query))
	{
		$banned['profile_link'] = build_profile_link($banned['username'], $banned['uid']);

		// Only show the edit & lift links if current user created ban, or is super mod/admin
		$banned['show_edit_link'] = false;
		if($mybb->user['uid'] == $banned['admin'] || !$banned['adminuser'] || $mybb->usergroup['issupermod'] == 1 || $mybb->usergroup['cancp'] == 1)
		{
			$banned['show_edit_link'] = true;
		}

		$banned['admin_profile'] = build_profile_link($banned['adminuser'], $banned['admin']);

		if($banned['reason'])
		{
			$banned['reason'] = $parser->parse_badwords($banned['reason']);
		}
		else
		{
			$banned['reason'] = $lang->na;
		}

		if($banned['lifted'] == 'perm' || $banned['lifted'] == '' || $banned['bantime'] == 'perm' || $banned['bantime'] == '---')
		{
			$banned['banlength'] = $lang->permanent;
			$banned['ban_remaining'] = $lang->na;
			$banned['banned_class'] = "normal";
		}
		else
		{
			$banned['banlength'] = $bantimes[$banned['bantime']];
			$banned['remaining'] = $banned['lifted'] - TIME_NOW;

			$banned['timeremaining'] = nice_time($banned['remaining'], array('short' => 1, 'seconds' => false))."";

			$banned['ban_remaining'] = "{$banned['timeremaining']} {$lang->ban_remaining}";

			if($banned['remaining'] <= 0)
			{
				$banned['banned_class'] = "imminent";
				$banned['ban_remaining'] = $lang->ban_ending_imminently;
			}
			if($banned['remaining'] < 3600)
			{
				$banned['banned_class'] = "high";
			}
			elseif($banned['remaining'] < 86400)
			{
				$banned['banned_class'] = "moderate";
			}
			elseif($banned['remaining'] < 604800)
			{
				$banned['banned_class'] = "low";
			}
			else
			{
				$banned['banned_class'] = "normal";
			}
		}

		$bannedusers[] = $banned;
	}

	$plugins->run_hooks('modcp_banning');

	output_page(\MyBB\template('modcp/banning.twig', [
		'bannedusers' => $bannedusers,
		'multipage' => $multipage,
	]));
}

if($mybb->input['action'] == "liftban")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['canbanusers'] == 0)
	{
		error_no_permission();
	}

	$query = $db->simple_select("banned", "*", "uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'");
	$ban = $db->fetch_array($query);

	if(!$ban)
	{
		error($lang->error_invalidban);
	}

	// Permission to edit this ban?
	if($mybb->user['uid'] != $ban['admin'] && $mybb->usergroup['issupermod'] != 1 && $mybb->usergroup['cancp'] != 1)
	{
		error_no_permission();
	}

	$plugins->run_hooks('modcp_liftban_start');

	$query = $db->simple_select("users", "username", "uid = '{$ban['uid']}'");
	$username = $db->fetch_field($query, "username");

	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler("update");

	$userhandler->set_data($ban);

	$userhandler->lift_ban();

	log_moderator_action(array("uid" => $ban['uid'], "username" => $username), $lang->lifted_ban);

	$plugins->run_hooks('modcp_liftban_end');

	redirect("modcp.php?action=banning", $lang->redirect_banlifted);
}

if($mybb->input['action'] == "do_banuser" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['canbanusers'] == 0)
	{
		error_no_permission();
	}

	$errors = array();

	// Creating a new ban
	$options = array(
		'fields' => array('username', 'usergroup', 'additionalgroups', 'displaygroup')
	);

	$user = get_user_by_username($mybb->input['username'], $options);

	// Set up user handler.
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler("insert");

	if($mybb->get_input('liftafter') == '---')
	{
		$lifted = 0;
	}
	else
	{
		if(!isset($user['dateline']))
		{
			$user['dateline'] = 0;
		}
		$lifted = ban_date2timestamp($mybb->get_input('liftafter'), $user['dateline']);
	}

	$userdata = array(
		'uid' => $user['uid'],
		'gid' => $mybb->get_input('usergroup', MyBB::INPUT_INT),
		'usergroup' => $user['usergroup'],
		'additionalgroups' => $user['additionalgroups'],
		'displaygroup' => $user['displaygroup'],
		'bantime' => $db->escape_string($mybb->input['liftafter']),
		'lifted' => $db->escape_string($lifted),
		'reason' => $db->escape_string($mybb->input['banreason']),
	);

	$userhandler->set_data($userdata);

	if(!$userhandler->validate_ban())
	{
		$errors = $userhandler->get_friendly_errors();
	}

	$plugins->run_hooks('modcp_do_banuser_start');

	// Still no errors? Ban the user
	if(!$errors)
	{
		$userhandler->insert_ban();

		// Log ban
		log_moderator_action(array("uid" => $user['uid'], "username" => $user['username']), $lang->banned_user);

		$plugins->run_hooks('modcp_do_banuser_end');

		redirect("modcp.php?action=banning", $lang->redirect_banuser);
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
	add_breadcrumb($lang->mcp_nav_ban_user);

	if($mybb->usergroup['canbanusers'] == 0)
	{
		error_no_permission();
	}

	$plugins->run_hooks('modcp_banuser_start');

	// Coming back to this page from an error?
	if($errors)
	{
		$errors = inline_error($errors);
		$banned = array(
			"username" => $mybb->get_input('username'),
			"bantime" => $mybb->get_input('liftafter'),
			"reason" => $mybb->get_input('banreason'),
			"usergroup" => $mybb->get_input('usergroup', MyBB::INPUT_INT)
		);
	}

	// Generate the banned times dropdown
	$liftlist = [];
	foreach($bantimes as $time => $title)
	{
		$lifttime['selected'] = false;
		if(isset($banned['bantime']) && $banned['bantime'] == $time)
		{
			$lifttime['selected'] = true;
		}

		$lifttime['thattime'] = '';
		if($time != '---')
		{
			$dateline = TIME_NOW;
			if(isset($banned['dateline']))
			{
				$dateline = $banned['dateline'];
			}

			$thatime = my_date("D, jS M Y @ {$mybb->settings['timeformat']}", ban_date2timestamp($time, $dateline));
			$lifttime['thattime'] = $thatime;
		}

		$lifttime['time'] = $time;
		$lifttime['title'] = $title;

		$liftlist[] = $lifttime;
	}

	$bangroups = [];
	$banned['numgroups'] = $banned['banned_group'] = 0;
	$groupscache = $cache->read("usergroups");

	foreach($groupscache as $key => $group)
	{
		if($group['isbannedgroup'])
		{
			$group['selected'] = false;
			if(isset($banned['usergroup']) && $banned['usergroup'] == $group['gid'])
			{
				$group['selected'] = true;
			}

			$bangroups[] = $group;
			$banned['banned_group'] = $group['gid'];
			++$banned['numgroups'];
		}
	}

	if($banned['numgroups'] == 0)
	{
		error($lang->no_banned_group);
	}

	$plugins->run_hooks('modcp_banuser_end');

	output_page(\MyBB\template('modcp/banuser.twig', [
		'banned' => $banned,
		'errors' => $errors,
		'liftlist' => $liftlist,
		'bangroups' => $bangroups,
	]));
}

if($mybb->input['action'] == "do_editban" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['canbanusers'] == 0)
	{
		error_no_permission();
	}

	// Editing an existing ban
	$query = $db->query("
        SELECT b.*, u.uid, u.username, u.usergroup, u.additionalgroups, u.displaygroup
        FROM ".TABLE_PREFIX."banned b
        LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid)
        WHERE b.uid='{$mybb->input['uid']}'
    ");
	$user = $db->fetch_array($query);

	// Permission to edit this ban?
	if($mybb->user['uid'] != $user['admin'] && $mybb->usergroup['issupermod'] != 1 && $mybb->usergroup['cancp'] != 1)
	{
		error_no_permission();
	}

	$errors = array();

	// Set up user handler.
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler("update");

	if($mybb->get_input('liftafter') == '---')
	{
		$lifted = 0;
	}
	else
	{
		if(!isset($user['dateline']))
		{
			$user['dateline'] = 0;
		}
		$lifted = ban_date2timestamp($mybb->get_input('liftafter'), $user['dateline']);
	}

	$userdata = array(
		'uid' => $user['uid'],
		'gid' => $mybb->get_input('usergroup', MyBB::INPUT_INT),
		'dateline' => TIME_NOW,
		'bantime' => $db->escape_string($mybb->input['liftafter']),
		'lifted' => $db->escape_string($lifted),
		'reason' => $db->escape_string($mybb->input['banreason']),
	);

	$userhandler->set_data($userdata);

	if(!$userhandler->validate_ban())
	{
		$errors = $userhandler->get_friendly_errors();
	}

	$plugins->run_hooks('modcp_do_editban_start');

	// Still no errors? Edit the ban
	if(!$errors)
	{
		$userhandler->update_ban();

		// Log edit
		log_moderator_action(array("uid" => $user['uid'], "username" => $user['username']), $lang->edited_user_ban);

		$plugins->run_hooks('modcp_do_editban_end');

		redirect("modcp.php?action=banning", $lang->redirect_banuser_updated);
	}
	// Otherwise has errors, throw back to edit ban page
	else
	{
		$mybb->input['action'] = "editban";
	}
}

if($mybb->input['action'] == "editban")
{
	add_breadcrumb($lang->mcp_nav_banning, "modcp.php?action=banning");
	add_breadcrumb($lang->mcp_nav_editing_ban);

	if($mybb->usergroup['canbanusers'] == 0)
	{
		error_no_permission();
	}

	$plugins->run_hooks('modcp_editban_start');

	$query = $db->query("
        SELECT b.*, u.username, u.uid
        FROM ".TABLE_PREFIX."banned b
        LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid)
        WHERE b.uid='{$mybb->input['uid']}'
    ");
	$banned = $db->fetch_array($query);

	if(!$banned)
	{
		error($lang->error_nomember);
	}

	// Permission to edit this ban?
	if($mybb->user['uid'] != $banned['admin'] && $mybb->usergroup['issupermod'] != 1 && $mybb->usergroup['cancp'] != 1)
	{
		error_no_permission();
	}

	// Coming back to this page from an error?
	if($errors)
	{
		$errors = inline_error($errors);
		$banned = array(
			"uid" => $banned['uid'],
			"username" => $banned['username'],
			"bantime" => $mybb->get_input('liftafter'),
			"reason" => $mybb->get_input('banreason'),
			"usergroup" => $mybb->get_input('usergroup', MyBB::INPUT_INT)
		);
	}

	// Generate the banned times dropdown
	$liftlist = [];
	foreach($bantimes as $time => $title)
	{
		$lifttime['selected'] = false;
		if(isset($banned['bantime']) && $banned['bantime'] == $time)
		{
			$lifttime['selected'] = true;
		}

		$lifttime['thattime'] = '';
		if($time != '---')
		{
			$dateline = TIME_NOW;
			if(isset($banned['dateline']))
			{
				$dateline = $banned['dateline'];
			}

			$thatime = my_date("D, jS M Y @ {$mybb->settings['timeformat']}", ban_date2timestamp($time, $dateline));
			$lifttime['thattime'] = $thatime;
		}

		$lifttime['time'] = $time;
		$lifttime['title'] = $title;

		$liftlist[] = $lifttime;
	}

	$bangroups = [];
	$banned['numgroups'] = $banned['banned_group'] = 0;
	$groupscache = $cache->read("usergroups");

	foreach($groupscache as $key => $group)
	{
		if($group['isbannedgroup'])
		{
			$group['selected'] = false;
			if(isset($banned['usergroup']) && $banned['usergroup'] == $group['gid'])
			{
				$group['selected'] = true;
			}

			$bangroups[] = $group;
			$banned['banned_group'] = $group['gid'];
			++$banned['numgroups'];
		}
	}

	if($banned['numgroups'] == 0)
	{
		error($lang->no_banned_group);
	}

	$plugins->run_hooks('modcp_editban_end');

	output_page(\MyBB\template('modcp/editban.twig', [
		'banned' => $banned,
		'errors' => $errors,
		'liftlist' => $liftlist,
		'bangroups' => $bangroups,
	]));
}

if($mybb->input['action'] == "do_modnotes")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks('modcp_do_modnotes_start');

	// Update Moderator Notes cache
	$update_cache = array(
		"modmessage" => $mybb->get_input('modnotes')
	);
	$cache->update("modnotes", $update_cache);

	$plugins->run_hooks('modcp_do_modnotes_end');

	redirect("modcp.php", $lang->redirect_modnotes);
}

if(!$mybb->input['action'])
{

	if($mybb->usergroup['canmanagemodqueue'] == 1)
	{
		if($mybb->settings['enableattachments'] == 1 && ($counters['modqueue']['attachments'] > 0 || $mybb->usergroup['issupermod'] == 1))
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
				$attachment['date'] = my_date('relative', $attachment['dateuploaded']);
				$attachment['username'] = htmlspecialchars_uni($attachment['username']);
				$attachment['profilelink'] = build_profile_link($attachment['username'], $attachment['uid']);
				$attachment['link'] = get_post_link($attachment['pid'], $attachment['tid']);
				$unapproved_attachments = my_number_format($unapproved_attachments);
			}
		}

		if($counters['modqueue']['posts'] > 0 || $mybb->usergroup['issupermod'] == 1)
		{
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
					ORDER BY p.dateline DESC, p.pid DESC
					LIMIT 1
				");
				$post = $db->fetch_array($query);
				$post['date'] = my_date('relative', $post['dateline']);
				$post['username'] = htmlspecialchars_uni($post['username']);
				$post['profilelink'] = build_profile_link($post['username'], $post['uid']);
				$post['link'] = get_post_link($post['pid'], $post['tid']);
				$post['subject'] = $post['fullsubject'] = $parser->parse_badwords($post['subject']);
				if(my_strlen($post['subject']) > 25)
				{
					$post['subject'] = my_substr($post['subject'], 0, 25)."...";
				}
				$post['subject'] = htmlspecialchars_uni($post['subject']);
				$post['fullsubject'] = htmlspecialchars_uni($post['fullsubject']);
				$unapproved_posts = my_number_format($unapproved_posts);
			}
		}

		if($counters['modqueue']['threads'] > 0 || $mybb->usergroup['issupermod'] == 1)
		{
			$query = $db->simple_select("threads", "COUNT(tid) AS unapprovedthreads", "visible='0' {$flist_queue_threads}");
			$unapproved_threads = $db->fetch_field($query, "unapprovedthreads");

			if($unapproved_threads > 0)
			{
				$query = $db->simple_select("threads", "tid, subject, uid, username, dateline", "visible='0' {$flist_queue_threads}", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 1));
				$thread = $db->fetch_array($query);
				$thread['date'] = my_date('relative', $thread['dateline']);
				$thread['username'] = htmlspecialchars_uni($thread['username']);
				$thread['profilelink'] = build_profile_link($thread['username'], $thread['uid']);
				$thread['link'] = get_thread_link($thread['tid']);
				$thread['subject'] = $thread['fullsubject'] = $parser->parse_badwords($thread['subject']);
				if(my_strlen($thread['subject']) > 25)
				{
					$post['subject'] = my_substr($thread['subject'], 0, 25)."...";
				}
				$thread['subject'] = htmlspecialchars_uni($thread['subject']);
				$thread['fullsubject'] = htmlspecialchars_uni($thread['fullsubject']);
				$unapproved_threads = my_number_format($unapproved_threads);
			}
		}
	}

	if(($counters['modlogs'] > 0 || $mybb->usergroup['issupermod'] == 1) && $mybb->usergroup['canviewmodlogs'] == 1)
	{
		$where = '';
		if($tflist_modlog)
		{
			$where = "WHERE (t.fid <> 0 {$tflist_modlog}) OR (l.fid <> 0)";
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

		$modlogs = [];
		while($logitem = $db->fetch_array($query))
		{
			$logitem['date'] = my_date('relative', $logitem['dateline']);
			if($logitem['username'])
			{
				$username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);
				$logitem['profilelink'] = build_profile_link($username, $logitem['uid']);
			}
			else
			{
				$username = $logitem['profilelink'] = $logitem['username'] = $lang->na_deleted;
			}
			$logitem['ipaddress'] = my_inet_ntop($db->unescape_binary($logitem['ipaddress']));
			if($logitem['tsubject'])
			{
				$logitem['tsubject'] = $parser->parse_badwords($logitem['tsubject']);
				$logitem['threadlink'] = get_thread_link($logitem['tid']);
			}
			if($logitem['fname'])
			{
				$logitem['forumlink'] = get_forum_link($logitem['fid']);
			}
			if($logitem['psubject'])
			{
				$logitem['psubject'] = $parser->parse_badwords($logitem['psubject']);
				$logitem['postlink'] = get_post_link($logitem['pid']);
			}
			// Edited a user or managed announcement?
			if(!$logitem['tsubject'] || !$logitem['fname'] || !$logitem['psubject'])
			{
				$logitem['logdata'] = my_unserialize($logitem['data']);
				if(!empty($logitem['logdata']['uid']))
				{
					$logitem['logdata']['profilelink'] = get_profile_link($logitem['logdata']['uid']);
				}
				if(!empty($logitem['logdata']['aid']))
				{
					$logitem['logdata']['subject'] = $parser->parse_badwords($logitem['logdata']['subject']);
					$logitem['logdata']['announcement'] = get_announcement_link($logitem['logdata']['aid']);
				}
			}
			$plugins->run_hooks('modcp_modlogs_result');
			$modlogs[] = $logitem;
		}
	}

	$query = $db->query("
        SELECT b.*, a.username AS adminuser, u.username
        FROM ".TABLE_PREFIX."banned b
        LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid)
        LEFT JOIN ".TABLE_PREFIX."users a ON (b.admin=a.uid)
        WHERE b.bantime != '---' AND b.bantime != 'perm'
        ORDER BY lifted ASC
        LIMIT 5
    ");

	$banned_cache = array();
	while($banned = $db->fetch_array($query))
	{
		$banned['remaining'] = $banned['lifted'] - TIME_NOW;
		$banned_cache[$banned['remaining'].$banned['uid']] = $banned;

		unset($banned);
	}

	// Get the banned users
	$bannedusers = [];
	while($banned = $db->fetch_array($query))
	{
		$banned['profile_link'] = build_profile_link($banned['username'], $banned['uid']);
		// Only show the edit & lift links if current user created ban, or is super mod/admin
		$banned['show_edit_link'] = false;
		if($mybb->user['uid'] == $banned['admin'] || !$banned['adminuser'] || $mybb->usergroup['issupermod'] == 1 || $mybb->usergroup['cancp'] == 1)
		{
			$banned['show_edit_link'] = true;
		}
		$banned['admin_profile'] = build_profile_link($banned['adminuser'], $banned['admin']);
		if($banned['reason'])
		{
			$banned['reason'] = $parser->parse_badwords($banned['reason']);
		}
		else
		{
			$banned['reason'] = $lang->na;
		}
		if($banned['lifted'] == 'perm' || $banned['lifted'] == '' || $banned['bantime'] == 'perm' || $banned['bantime'] == '---')
		{
			$banned['banlength'] = $lang->permanent;
			$banned['ban_remaining'] = $lang->na;
			$banned['banned_class'] = "normal";
		}
		else
		{
			$banned['banlength'] = $bantimes[$banned['bantime']];
			$banned['remaining'] = $banned['lifted'] - TIME_NOW;
			$banned['timeremaining'] = nice_time($banned['remaining'], array('short' => 1, 'seconds' => false))."";
			$banned['ban_remaining'] = "{$banned['timeremaining']} {$lang->ban_remaining}";
			if($banned['remaining'] <= 0)
			{
				$banned['banned_class'] = "imminent";
				$banned['ban_remaining'] = $lang->ban_ending_imminently;
			}
			if($banned['remaining'] < 3600)
			{
				$banned['banned_class'] = "high";
			}
			elseif($banned['remaining'] < 86400)
			{
				$banned['banned_class'] = "moderate";
			}
			elseif($banned['remaining'] < 604800)
			{
				$banned['banned_class'] = "low";
			}
			else
			{
				$banned['banned_class'] = "normal";
			}
		}
		$bannedusers[] = $banned;
	}

	$modnotes = '';
	$modnotes_cache = $cache->read("modnotes");
	if($modnotes_cache !== false)
	{
		$modnotes = htmlspecialchars_uni($modnotes_cache['modmessage']);
	}

	$plugins->run_hooks('modcp_end');

	output_page(\MyBB\template('modcp/home.twig', [
		'counters' => $counters,
		'unapproved_attachments' => $unapproved_attachments,
		'unapproved_posts' => $unapproved_posts,
		'unapproved_threads' => $unapproved_threads,
		'modlogs' => $modlogs,
		'attachment' => $attachment,
		'post' => $post,
		'thread' => $thread,
		'bannedusers' => $bannedusers,
		'modnotes' => $modnotes
	]));
}
