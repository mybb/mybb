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

$templatelist = "search,forumdisplay_thread_gotounread,search_results_threads_thread,search_results_threads,search_results_posts,search_results_posts_post";
$templatelist .= ",multipage_nextpage,multipage_page_current,multipage_page,multipage_start,multipage_end,multipage,forumdisplay_thread_multipage_more,forumdisplay_thread_multipage_page,forumdisplay_thread_multipage";
require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_search.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("search");

add_breadcrumb($lang->nav_search, "search.php");

switch($mybb->input['action'])
{
	case "results":
		add_breadcrumb($lang->nav_results);
		break;
	default:
		break;
}

if($mybb->usergroup['cansearch'] == 0)
{
	error_no_permission();
}

$now = TIME_NOW;

if($mybb->input['action'] == "results")
{
	$sid = $db->escape_string($mybb->input['sid']);
	$query = $db->simple_select("searchlog", "*", "sid='$sid'");
	$search = $db->fetch_array($query);

	if(!$search['sid'])
	{
		error($lang->error_invalidsearch);
	}

	$plugins->run_hooks("search_results_start");

	// Decide on our sorting fields and sorting order.
	$order = my_strtolower(htmlspecialchars($mybb->input['order']));
	$sortby = my_strtolower(htmlspecialchars($mybb->input['sortby']));

	switch($sortby)
	{
		case "replies":
			$sortfield = "t.replies";
			break;
		case "views":
			$sortfield = "t.views";
			break;
		case "subject":
			if($search['resulttype'] == "threads")
			{
				$sortfield = "t.subject";
			}
			else
			{
				$sortfield = "p.subject";
			}
			break;
		case "forum":
			$sortfield = "t.fid";
			break;
		case "starter":
			if($search['resulttype'] == "threads")
			{
				$sortfield = "t.username";
			}
			else
			{
				$sortfield = "p.username";
			}
			break;
		case "lastpost":
		default:
			if($search['resulttype'] == "threads")
			{
				$sortfield = "t.lastpost";
				$sortby = "lastpost";
			}
			else
			{
				$sortfield = "p.dateline";
				$sortby = "dateline";
			}
			break;
	}
	
	if($order != "asc")
	{
		$order = "desc";
		$oppsortnext = "asc";
		$oppsort = $lang->asc;
	}
	else
	{
		$oppsortnext = "desc";
		$oppsort = $lang->desc;		
	}
	
	if(!$mybb->settings['threadsperpage'])
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	// Work out pagination, which page we're at, as well as the limits.
	$perpage = $mybb->settings['threadsperpage'];
	$page = intval($mybb->input['page']);
	if($page > 0)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;
	
	// Work out if we have terms to highlight
	if($search['keywords'])
	{
		if($mybb->settings['seourls'] == "yes" || ($mybb->settings['seourls'] == "auto" && $_SERVER['SEO_SUPPORT'] == 1))
		{
			$highlight = "?highlight=".urlencode($search['keywords']);
		}
		else
		{
			$highlight = "&amp;highlight=".urlencode($search['keywords']);
		}
	}

	$sorturl = "search.php?action=results&amp;sid={$sid}";
	$thread_url = "";
	$post_url = "";
	
	eval("\$orderarrow['$sortby'] = \"".$templates->get("search_orderarrow")."\";");

	// Read some caches we will be using
	$forumcache = $cache->read("forums");
	$icon_cache = $cache->read("posticons");

	$threads = array();
	
	$limitsql = "";
	if(intval($mybb->settings['searchhardlimit']) > 0)
	{
		$limitsql = "LIMIT ".intval($mybb->settings['searchhardlimit']);
	}

	$fpermissions = forum_permissions();

	// Show search results as 'threads'
	if($search['resulttype'] == "threads")
	{
		$threadcount = 0;
		// If we have saved WHERE conditions, execute them
		if($search['querycache'] != "")
		{
			$where_conditions = $search['querycache'];
			$query = $db->simple_select("threads t", "t.tid", $where_conditions. " AND t.visible>0 AND t.closed NOT LIKE 'moved|%' {$limitsql}");
			while($thread = $db->fetch_array($query))
			{
				$threads[$thread['tid']] = $thread['tid'];
				$threadcount++;
			}
			// Build our list of threads.
			if($threadcount > 0)
			{
				$search['threads'] = implode(",", $threads);
			}
			// No results.
			else
			{
				error($lang->error_nosearchresults);
			}
			$where_conditions = "t.tid IN (".$search['threads'].")";
		}
		// This search doesn't use a query cache, results stored in search table.
		else
		{
			$where_conditions = "t.tid IN (".$search['threads'].")";
			$query = $db->simple_select("threads t", "COUNT(t.tid) AS resultcount", $where_conditions. " AND t.visible>0 AND t.closed NOT LIKE 'moved|%' {$limitsql}");
			$count = $db->fetch_array($query);

			if(!$count['resultcount'])
			{
				error($lang->error_nosearchresults);
			}
			$threadcount = $count['resultcount'];
		}
		// Begin selecting matching threads, cache them.
		$sqlarray = array(
			'order_by' => $sortfield,
			'order_dir' => $order,
			'limit_start' => $start,
			'limit' => $perpage
		);
		$query = $db->query("
			SELECT t.*, u.username AS userusername
			FROM ".TABLE_PREFIX."threads t
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
			WHERE $where_conditions AND t.visible>0 AND t.closed NOT LIKE 'moved|%'
			ORDER BY $sortfield $order
			LIMIT $start, $perpage
		");
		$thread_cache = array();
		while($thread = $db->fetch_array($query))
		{
			$thread_cache[$thread['tid']] = $thread;
		}
		$thread_ids = implode(",", array_keys($thread_cache));


		// Fetch dot icons if enabled
		if($mybb->settings['dotfolders'] != 0 && $mybb->user['uid'] && $thread_cache)
		{
			$query = $db->simple_select("posts", "DISTINCT tid,uid", "uid='".$mybb->user['uid']."' AND tid IN(".$thread_ids.")");
			while($post = $db->fetch_array($query))
			{
				$thread_cache[$post['tid']]['dot_icon'] = 1;
			}
		}

		// Fetch the read threads.
		if($mybb->user['uid'] && $mybb->settings['threadreadcut'] > 0)
		{
			$query = $db->simple_select("threadsread", "tid,dateline", "uid='".$mybb->user['uid']."' AND tid IN(".$thread_ids.")");
			while($readthread = $db->fetch_array($query))
			{
				$thread_cache[$readthread['tid']]['lastread'] = $readthread['dateline'];
			}
		}

		foreach($thread_cache as $thread)
		{
			$bgcolor = alt_trow();
			$folder = '';
			$prefix = '';

			if($thread['userusername'])
			{
				$thread['username'] = $thread['userusername'];
			}
			$thread['profilelink'] = build_profile_link($thread['username'], $thread['uid']);

			$thread['subject'] = $parser->parse_badwords($thread['subject']);
			$thread['subject'] = htmlspecialchars_uni($thread['subject']);

			if($icon_cache[$thread['icon']])
			{
				$posticon = $icon_cache[$thread['icon']];
				$icon = "<img src=\"".$posticon['path']."\" alt=\"".$posticon['name']."\" />";
			}
			else
			{
				$icon = "&nbsp;";
			}
			if($thread['poll'])
			{
				$prefix = $lang->poll_prefix;
			}
				
			// Determine the folder
			$folder = '';
			$folder_label = '';
			if($thread['dot_icon'])
			{
				$folder = "dot_";
				$folder_label .= $lang->icon_dot;
			}
			$gotounread = '';
			$isnew = 0;
			$donenew = 0;
			$lastread = 0;

			if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'] && $thread['lastpost'] > $forumread)
			{
				$cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
				if($thread['lastpost'] > $cutoff)
				{
					if($thread['lastread'])
					{
						$lastread = $thread['lastread'];
					}
					else
					{
						$lastread = 1;
					}
				}
			}
			$lastread = $thread['lastread'];
			if(!$lastread)
			{
				$readcookie = $threadread = my_get_array_cookie("threadread", $thread['tid']);
				if($readcookie > $forumread)
				{
					$lastread = $readcookie;
				}
				elseif($forumread > $mybb->user['lastvisit'])
				{
					$lastread = $forumread;
				}
				else
				{
					$lastread = $mybb->user['lastvisit'];
				}
			}

			if($thread['lastpost'] > $lastread && $lastread)
			{
				$folder .= "new";
				$new_class = "subject_new";
				$folder_label .= $lang->icon_new;
				$thread['newpostlink'] = get_thread_link($thread['tid'], 0, "newpost");
				eval("\$gotounread = \"".$templates->get("forumdisplay_thread_gotounread")."\";");
				$unreadpost = 1;
			}
			else
			{
				$new_class = '';
				$folder_label .= $lang->icon_no_new;
			}

			if($thread['replies'] >= $mybb->settings['hottopic'] || $thread['views'] >= $mybb->settings['hottopicviews'])
			{
				$folder .= "hot";
				$folder_label .= $lang->icon_hot;
			}
			if($thread['closed'] == 1)
			{
				$folder .= "lock";
				$folder_label .= $lang->icon_lock;
			}
			$folder .= "folder";
			
			if(!$mybb->settings['postsperpage'])
			{
				$mybb->settings['postperpage'] = 20;
			}

			$thread['pages'] = 0;
			$thread['multipage'] = '';
			$threadpages = '';
			$morelink = '';
			$thread['posts'] = $thread['replies'] + 1;
			if($thread['posts'] > $mybb->settings['postsperpage'])
			{
				$thread['pages'] = $thread['posts'] / $mybb->settings['postsperpage'];
				$thread['pages'] = ceil($thread['pages']);
				if($thread['pages'] > 4)
				{
					$pagesstop = 4;
					$page_link = get_thread_link($thread['tid'], $thread['pages']);
					eval("\$morelink = \"".$templates->get("forumdisplay_thread_multipage_more")."\";");
				}
				else
				{
					$pagesstop = $thread['pages'];
				}
				for($i = 1; $i <= $pagesstop; ++$i)
				{
					$page_link = get_thread_link($thread['tid'], $i);
					eval("\$threadpages .= \"".$templates->get("forumdisplay_thread_multipage_page")."\";");
				}
				eval("\$thread[multipage] = \"".$templates->get("forumdisplay_thread_multipage")."\";");
			}
			else
			{
				$threadpages = '';
				$morelink = '';
				$thread['multipage'] = '';
			}
			$lastpostdate = my_date($mybb->settings['dateformat'], $thread['lastpost']);
			$lastposttime = my_date($mybb->settings['timeformat'], $thread['lastpost']);
			$lastposter = $thread['lastposter'];
			$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");
			$lastposteruid = $thread['lastposteruid'];
			$thread_link = get_thread_link($thread['tid']);

			// Don't link to guest's profiles (they have no profile).
			if($lastposteruid == 0)
			{
				$lastposterlink = $lastposter;
			}
			else
			{
				$lastposterlink = build_profile_link($lastposter, $lastposteruid);
			}

			$thread['replies'] = my_number_format($thread['replies']);
			$thread['views'] = my_number_format($thread['views']);

			if($forumcache[$thread['fid']])
			{
				$thread['forumlink'] = "<a href=\"".get_forum_link($thread['fid'])."\">".$forumcache[$thread['fid']]['name']."</a>";
			}
			else
			{
				$thread['forumlink'] = "";
			}

			// If this user is the author of the thread and it is not closed or they are a moderator, they can edit
			if(($thread['uid'] == $mybb->user['uid'] && $thread['closed'] != 1 && $mybb->user['uid'] != 0 && $fpermissions[$thread['fid']]['caneditposts'] == 1) || is_moderator($fid, "caneditposts"))
			{
				$inline_edit_class = "subject_editable";
			}
			else
			{
				$inline_edit_class = "";
			}
			$load_inline_edit_js = 1;

			// If this thread has 1 or more attachments show the papperclip
			if($thread['attachmentcount'] > 0)
			{
				if($thread['attachmentcount'] > 1)
				{
					$attachment_count = sprintf($lang->attachment_count_multiple, $thread['attachmentcount']);
				}
				else
				{
					$attachment_count = $lang->attachment_count;
				}

				eval("\$attachment_count = \"".$templates->get("forumdisplay_thread_attachment_count")."\";");
			}
			else
			{
				$attachment_count = '';
			}

			$inline_edit_tid = $thread['tid'];

			$plugins->run_hooks("search_results_thread");
			eval("\$results .= \"".$templates->get("search_results_threads_thread")."\";");
		}
		if(!$results)
		{
			error($lang->error_nosearchresults);
		}
		else
		{
			if($load_inline_edit_js == 1)
			{
				eval("\$inline_edit_js = \"".$templates->get("forumdisplay_threadlist_inlineedit_js")."\";");
			}
		}
		$multipage = multipage($threadcount, $perpage, $page, "search.php?action=results&amp;sid=$sid&amp;sortby=$sortby&amp;order=$order&amp;uid=".$mybb->input['uid']);
		if($upper > $threadcount)
		{
			$upper = $threadcount;
		}
		eval("\$searchresults = \"".$templates->get("search_results_threads")."\";");
		$plugins->run_hooks("search_results_end");
		output_page($searchresults);
	}
	else // Displaying results as posts
	{
		$postcount = 0;
		if($search['querycache'] != "")
		{
			$where_conditions = $search['querycache'];
		}
		else
		{
			if(!$search['posts'])
			{
				error($lang->error_nosearchresults);
			}
			$where_conditions = "p.pid IN (".$search['posts'].")";
		}
		$query = $db->query("
			SELECT COUNT(p.pid) AS resultcount
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE $where_conditions  AND p.visible>0 AND t.visible>0 AND t.closed NOT LIKE 'moved|%'
			{$limitsql}
		");
		$count = $db->fetch_array($query);

		if(!$count['resultcount'])
		{
			error($lang->error_nosearchresults);
		}
		$postcount = $count['resultcount'];

		$tids = array();
		$query = $db->query("
			SELECT p.tid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE $where_conditions AND p.visible>0 AND t.visible>0 AND t.closed NOT LIKE 'moved|%' 
			ORDER BY $sortfield $order
			LIMIT $start, $perpage
		");
		while($post = $db->fetch_array($query))
		{
			$tids[$post['tid']] = $post['tid'];
		}
		$tids = implode(",", $tids);

		// Read threads
		if($mybb->user['uid'] && $mybb->settings['threadreadcut'] > 0)
		{
			$query = $db->simple_select("threadsread", "tid, dateline", "uid='".$mybb->user['uid']."' AND tid IN(".$tids.")");
			while($readthread = $db->fetch_array($query))
			{
				$readthreads[$readthread['tid']] = $readthread['dateline'];
			}
		}

		$dot_icon = array();
		if($mybb->settings['dotfolders'] != 0 && $mybb->user['uid'] != 0)
		{
			$query = $db->simple_select("posts", "DISTINCT tid,uid", "uid='".$mybb->user['uid']."' AND tid IN(".$tids.")");
			while($post = $db->fetch_array($query))
			{
				$dot_icon[$post['tid']] = true;
			}
		}

		$query = $db->query("
			SELECT p.*, u.username AS userusername, t.subject AS thread_subject, t.replies AS thread_replies, t.views AS thread_views, t.lastpost AS thread_lastpost, t.closed AS thread_closed
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE $where_conditions AND p.visible>0 AND t.visible>0 AND t.closed NOT LIKE 'moved|%'
			ORDER BY $sortfield $order
			LIMIT $start, $perpage
		");
		while($post = $db->fetch_array($query))
		{
			$bgcolor = alt_trow();
			if($post['userusername'])
			{
				$post['username'] = $post['userusername'];
			}
			$post['profilelink'] = build_profile_link($post['username'], $post['uid']);
			$post['subject'] = $parser->parse_badwords($post['subject']);
			$post['subject'] = htmlspecialchars_uni($post['subject']);
			$post['thread_subject'] = $parser->parse_badwords($post['thread_subject']);
			$post['thread_subject'] = htmlspecialchars_uni($post['thread_subject']);

			if($icon_cache[$post['icon']])
			{
				$posticon = $icon_cache[$post['icon']];
				$icon = "<img src=\"".$posticon['path']."\" alt=\"".$posticon['name']."\" />";
			}
			else
			{
				$icon = "&nbsp;";
			}

			if($forumcache[$thread['fid']])
			{
				$post['forumlink'] = "<a href=\"".get_forum_link($post['fid'])."\">".$forumcache[$post['fid']]['name']."</a>";
			}
			else
			{
				$post['forumlink'] = "";
			}
			// Determine the folder
			$folder = '';
			$folder_label = '';
			$gotounread = '';
			$isnew = 0;
			$donenew = 0;
			$lastread = 0;
			$post['thread_lastread'] = $readthreads[$post['tid']];
			if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'] && $post['thread_lastpost'] > $forumread)
			{
				$cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
				if($post['thread_lastpost'] > $cutoff)
				{
					if($post['thread_lastread'])
					{
						$lastread = $post['thread_lastread'];
					}
					else
					{
						$lastread = 1;
					}
				}
			}

			if($dot_icon[$post['tid']])
			{
				$folder = "dot_";
				$folder_label .= $lang->icon_dot;
			}

			if(!$lastread)
			{
				$readcookie = $threadread = my_get_array_cookie("threadread", $post['tid']);
				if($readcookie > $forumread)
				{
					$lastread = $readcookie;
				}
				elseif($forumread > $mybb->user['lastvisit'])
				{
					$lastread = $forumread;
				}
				else
				{
					$lastread = $mybb->user['lastvisit'];
				}
			}

			if($post['thread_lastpost'] > $lastread && $lastread)
			{
				$folder .= "new";
				$folder_label .= $lang->icon_new;
				eval("\$gotounread = \"".$templates->get("forumdisplay_thread_gotounread")."\";");
				$unreadpost = 1;
			}
			else
			{
				$folder_label .= $lang->icon_no_new;
			}

			if($post['thread_replies'] >= $mybb->settings['hottopic'] || $post['thread_views'] >= $mybb->settings['hottopicviews'])
			{
				$folder .= "hot";
				$folder_label .= $lang->icon_hot;
			}
			if($thread['thread_closed'] == 1)
			{
				$folder .= "lock";
				$folder_label .= $lang->icon_lock;
			}
			$folder .= "folder";

			$post['thread_replies'] = my_number_format($post['thread_replies']);
			$post['thread_views'] = my_number_format($post['thread_views']);

			if($forumcache[$post['fid']])
			{
				$post['forumlink'] = "<a href=\"".get_forum_link($post['fid'])."\">".$forumcache[$post['fid']]['name']."</a>";
			}
			else
			{
				$post['forumlink'] = "";
			}

			if(!$post['subject'])
			{
				$post['subject'] = $post['message'];
			}
			if(my_strlen($post['subject']) > 50)
			{
				$post['subject'] = my_substr($post['subject'], 0, 50)."...";
			}
			else
			{
				$post['subject'] = $post['subject'];
			}
			// What we do here is parse the post using our post parser, then strip the tags from it
			$parser_options = array(
				'allow_html' => 0,
				'filter_badwords' => 1,
				'allow_mycode' => 1,
				'allow_smilies' => 0,
				'allow_imgcode' => 0
			);
			$post['message'] = strip_tags($parser->parse_message($post['message'], $parser_options));
			if(my_strlen($post['message']) > 200)
			{
				$prev = htmlspecialchars_uni(my_substr($post['message'], 0, 200)."...");
			}
			else
			{
				$prev = htmlspecialchars_uni($post['message']);
			}
			$posted = my_date($mybb->settings['dateformat'], $post['dateline']).", ".my_date($mybb->settings['timeformat'], $post['dateline']);
			
			$thread_url = get_thread_link($post['tid']);
			$post_url = get_post_link($post['pid'], $post['tid']);

			$plugins->run_hooks("search_results_post");
			eval("\$results .= \"".$templates->get("search_results_posts_post")."\";");
		}
		if(!$results)
		{
			error($lang->error_nosearchresults);
		}
		$multipage = multipage($postcount, $perpage, $page, "search.php?action=results&amp;sid=$sid&amp;sortby=$sortby&amp;order=$order&amp;uid=".$mybb->input['uid']);
		if($upper > $postcount)
		{
			$upper = $postcount;
		}

		eval("\$searchresults = \"".$templates->get("search_results_posts")."\";");
		$plugins->run_hooks("search_results_end");
		output_page($searchresults);
	}
}
elseif($mybb->input['action'] == "findguest")
{
	$where_sql = "p.uid='0'";

	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums)
	{
		$where_sql .= " AND t.fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums)
	{
		$where_sql .= " AND t.fid NOT IN ($inactiveforums)";
	}

	$sid = md5(uniqid(microtime(), 1));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $mybb->user['uid'],
		"dateline" => TIME_NOW,
		"ipaddress" => $db->escape_string($session->ipaddress),
		"threads" => '',
		"posts" => '',
		"searchtype" => "titles",
		"resulttype" => "posts",
		"querycache" => $db->escape_string($where_sql),
		"keywords" => ''
	);
	$plugins->run_hooks("search_do_search_process");
	$db->insert_query("searchlog", $searcharray);
	redirect("search.php?action=results&sid=".$sid, $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "finduser")
{
	$where_sql = "p.uid='".intval($mybb->input['uid'])."'";
	
	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums)
	{
		$where_sql .= " AND t.fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums)
	{
		$where_sql .= " AND t.fid NOT IN ($inactiveforums)";
	}

	$sid = md5(uniqid(microtime(), 1));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $mybb->user['uid'],
		"dateline" => TIME_NOW,
		"ipaddress" => $db->escape_string($session->ipaddress),
		"threads" => '',
		"posts" => '',
		"searchtype" => "titles",
		"resulttype" => "posts",
		"querycache" => $db->escape_string($where_sql),
		"keywords" => ''
	);
	$plugins->run_hooks("search_do_search_process");
	$db->insert_query("searchlog", $searcharray);
	redirect("search.php?action=results&sid=".$sid, $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "finduserthreads")
{
	$where_sql = "t.uid='".intval($mybb->input['uid'])."'";

	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums)
	{
		$where_sql .= " AND t.fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums)
	{
		$where_sql .= " AND t.fid NOT IN ($inactiveforums)";
	}

	$sid = md5(uniqid(microtime(), 1));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $mybb->user['uid'],
		"dateline" => TIME_NOW,
		"ipaddress" => $db->escape_string($session->ipaddress),
		"threads" => '',
		"posts" => '',
		"searchtype" => "titles",
		"resulttype" => "threads",
		"querycache" => $db->escape_string($where_sql),
		"keywords" => ''
	);
	$plugins->run_hooks("search_do_search_process");
	$db->insert_query("searchlog", $searcharray);
	redirect("search.php?action=results&sid=".$sid, $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "getnew")
{
	
	$where_sql = "t.lastpost >= '".$mybb->user['lastvisit']."'";

	if($mybb->input['fid'])
	{
		$where_sql .= " AND t.fid='".intval($mybb->input['fid'])."'";
	}
	else if($mybb->input['fids'])
	{
		$fids = explode(',', $mybb->input['fids']);
		foreach($fids as $key => $fid)
		{
			$fids[$key] = intval($fid);
		}
		
		if(!empty($fids))
		{
			$where_sql .= " AND t.fid IN (".implode(',', $fids).")";
		}
	}
	
	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums)
	{
		$where_sql .= " AND t.fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums)
	{
		$where_sql .= " AND t.fid NOT IN ($inactiveforums)";
	}

	$sid = md5(uniqid(microtime(), 1));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $mybb->user['uid'],
		"dateline" => TIME_NOW,
		"ipaddress" => $db->escape_string($session->ipaddress),
		"threads" => '',
		"posts" => '',
		"searchtype" => "titles",
		"resulttype" => "threads",
		"querycache" => $db->escape_string($where_sql),
		"keywords" => ''
	);

	$plugins->run_hooks("search_do_search_process");
	$db->insert_query("searchlog", $searcharray);
	redirect("search.php?action=results&sid=".$sid, $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "getdaily")
{
	if($mybb->input['days'] < 1)
	{
		$days = 1;
	}
	else
	{
		$days = intval($mybb->input['days']);
	}
	$datecut = TIME_NOW-(86400*$days);

	$where_sql = "t.lastpost >='".$datecut."'";

	if($mybb->input['fid'])
	{
		$where_sql .= " AND t.fid='".intval($mybb->input['fid'])."'";
	}
	else if($mybb->input['fids'])
	{
		$fids = explode(',', $mybb->input['fids']);
		foreach($fids as $key => $fid)
		{
			$fids[$key] = intval($fid);
		}
		
		if(!empty($fids))
		{
			$where_sql .= " AND t.fid IN (".implode(',', $fids).")";
		}
	}
	
	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums)
	{
		$where_sql .= " AND t.fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums)
	{
		$where_sql .= " AND t.fid NOT IN ($inactiveforums)";
	}


	$sid = md5(uniqid(microtime(), 1));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $mybb->user['uid'],
		"dateline" => TIME_NOW,
		"ipaddress" => $db->escape_string($session->ipaddress),
		"threads" => '',
		"posts" => '',
		"searchtype" => "titles",
		"resulttype" => "threads",
		"querycache" => $db->escape_string($where_sql),
		"keywords" => ''
	);

	$plugins->run_hooks("search_do_search_process");
	$db->insert_query("searchlog", $searcharray);
	redirect("search.php?action=results&sid=".$sid, $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "do_search" && $mybb->request_method == "post")
{
	$plugins->run_hooks("search_do_search_start");

	// Check if search flood checking is enabled and user is not admin
	if($mybb->settings['searchfloodtime'] > 0 && $mybb->usergroup['cancp'] != 1)
	{
		// Fetch the time this user last searched
		if($mybb->user['uid'])
		{
			$conditions = "uid='{$mybb->user['uid']}'";
		}
		else
		{
			$conditions = "uid='0' AND ipaddress='".$db->escape_string($session->ipaddress)."'";
		}
		$timecut = TIME_NOW-$mybb->settings['searchfloodtime'];
		$query = $db->simple_select("searchlog", "*", "$conditions AND dateline >= '$timecut'", array('order_by' => "dateline", 'order_dir' => "DESC"));
		$last_search = $db->fetch_array($query);
		// Users last search was within the flood time, show the error
		if($last_search['sid'])
		{
			$remaining_time = $mybb->settings['searchfloodtime']-(TIME_NOW-$last_search['dateline']);
			if($remaining_time == 1)
			{
				$lang->error_searchflooding = sprintf($lang->error_searchflooding_1, $mybb->settings['searchfloodtime']);
			}
			else
			{
				$lang->error_searchflooding = sprintf($lang->error_searchflooding, $mybb->settings['searchfloodtime'], $remaining_time);
			}
			error($lang->error_searchflooding);
		}
	}
	if($mybb->input['showresults'] == "threads")
	{
		$resulttype = "threads";
	}
	else
	{
		$resulttype = "posts";
	}

	$search_data = array(
		"keywords" => $mybb->input['keywords'],
		"author" => $mybb->input['author'],
		"postthread" => $mybb->input['postthread'],
		"matchusername" => $mybb->input['matchusername'],
		"postdate" => $mybb->input['postdate'],
		"pddir" => $mybb->input['pddir'],
		"forums" => $mybb->input['forums'],
		"findthreadst" => $mybb->input['findthreadst'],
		"numreplies" => $mybb->input['numreplies']
	);

	if($db->type == "mysql" || $db->type == "mysqli")
	{
		if($mybb->settings['searchtype'] == "fulltext" && $db->supports_fulltext_boolean("posts") && $db->is_fulltext("posts"))
		{
			$search_results = perform_search_mysql_ft($search_data);
		}
		else
		{
			$search_results = perform_search_mysql($search_data);
		}
	}
	else
	{
		error($lang->error_no_search_support);
	}
	$sid = md5(uniqid(microtime(), 1));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $mybb->user['uid'],
		"dateline" => $now,
		"ipaddress" => $db->escape_string($session->ipaddress),
		"threads" => $search_results['threads'],
		"posts" => $search_results['posts'],
		"searchtype" => $search_results['searchtype'],
		"resulttype" => $resulttype,
		"querycache" => $search_results['querycache'],
		"keywords" => $db->escape_string($mybb->input['keywords']),
	);
	$plugins->run_hooks("search_do_search_process");

	$db->insert_query("searchlog", $searcharray);

	if(my_strtolower($mybb->input['sortordr']) == "asc" || my_strtolower($mybb->input['sortordr'] == "desc"))
	{
		$sortorder = $mybb->input['sortordr'];
	}
	else
	{
		$sortorder = "desc";
	}
	$sortby = htmlspecialchars($mybb->input['sortby']);
	$plugins->run_hooks("search_do_search_end");
	redirect("search.php?action=results&sid=".$sid."&sortby=".$sortby."&order=".$sortorder, $lang->redirect_searchresults);
}
else if($mybb->input['action'] == "thread")
{
	// Fetch thread info
	$thread = get_thread($mybb->input['tid']);
	if(!$thread['tid'] || (($thread['visible'] == 0 && !is_moderator($thread['fid'])) || $thread['visible'] < 0))
	{
		error($lang->error_invalidthread);
	}

	// Get forum info
	$forum = get_forum($thread['fid']);
	if(!$forum)
	{
		error($lang->error_invalidforum);
	}

	$forum_permissions = forum_permissions($forum['fid']);

	if($forum['open'] == 0 || $forum['type'] != "f")
	{
		error($lang->error_closedinvalidforum);
	}
	if($forum_permissions['canview'] == 0 || $forum_permissions['canviewthreads'] != 1)
	{
		error_no_permission();
	}

	$plugins->run_hooks("search_thread_start");

	// Check if search flood checking is enabled and user is not admin
	if($mybb->settings['searchfloodtime'] > 0 && $mybb->usergroup['cancp'] != 1)
	{
		// Fetch the time this user last searched
		if($mybb->user['uid'])
		{
			$conditions = "uid='{$mybb->user['uid']}'";
		}
		else
		{
			$conditions = "uid='0' AND ipaddress='".$db->escape_string($session->ipaddress)."'";
		}
		$timecut = TIME_NOW-$mybb->settings['searchfloodtime'];
		$query = $db->simple_select("searchlog", "*", "$conditions AND dateline >= '$timecut'", array('order_by' => "dateline", 'order_dir' => "DESC"));
		$last_search = $db->fetch_array($query);

		// We shouldn't show remaining time if time is 0 or under.
		$remaining_time = $mybb->settings['searchfloodtime']-(TIME_NOW-$last_search['dateline']);
		// Users last search was within the flood time, show the error.
		if($last_search['sid'] && $remaining_time > 0)
		{
			if($remaining_time == 1)
			{
				$lang->error_searchflooding = sprintf($lang->error_searchflooding_1, $mybb->settings['searchfloodtime']);
			}
			else
			{
				$lang->error_searchflooding = sprintf($lang->error_searchflooding, $mybb->settings['searchfloodtime'], $remaining_time);
			}
			error($lang->error_searchflooding);
		}
	}

	$search_data = array(
		"keywords" => $mybb->input['keywords'],
		"postthread" => 1,
		"tid" => $mybb->input['tid']
	);

	if($db->type == "mysql" || $db->type == "mysqli")
	{
		if($mybb->settings['searchtype'] == "fulltext" && $db->supports_fulltext_boolean("posts") && $db->is_fulltext("posts"))
		{
			$search_results = perform_search_mysql_ft($search_data);
		}
		else
		{
			$search_results = perform_search_mysql($search_data);
		}
	}
	else
	{
		error($lang->error_no_search_support);
	}
	$sid = md5(uniqid(microtime(), 1));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $mybb->user['uid'],
		"dateline" => $now,
		"ipaddress" => $db->escape_string($session->ipaddress),
		"threads" => $search_results['threads'],
		"posts" => $search_results['posts'],
		"searchtype" => 'posts',
		"resulttype" => 'posts',
		"querycache" => $search_results['querycache'],
		"keywords" => $db->escape_string($mybb->input['keywords'])
	);
	$plugins->run_hooks("search_thread_process");

	$db->insert_query("searchlog", $searcharray);

	$plugins->run_hooks("search_do_search_end");
	redirect("search.php?action=results&sid=".$sid, $lang->redirect_searchresults);
}
else
{
	$plugins->run_hooks("search_start");
	$srchlist = make_searchable_forums("", $fid);
	eval("\$search = \"".$templates->get("search")."\";");
	$plugins->run_hooks("search_end");
	output_page($search);
}

?>