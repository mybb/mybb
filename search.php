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

$templatelist = "search,forumdisplay_thread_gotounread,search_results_threads_thread,search_results_threads,search_results_posts,search_results_posts_post";
$templatelist .= ",multipage_nextpage,multipage_page_current,multipage_page,multipage_start,multipage_end,multipage";
require "./global.php";
require MYBB_ROOT."inc/functions_post.php";
require MYBB_ROOT."inc/functions_search.php";
require MYBB_ROOT."inc/class_parser.php";
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

if($mybb->usergroup['cansearch'] == "no")
{
	error_no_permission();
}

$now = time();

if($mybb->input['action'] == "results")
{
	$sid = $db->escape_string($mybb->input['sid']);
	$query = $db->query("
		SELECT * 
		FROM ".TABLE_PREFIX."searchlog 
		WHERE sid='$sid'
	");
	$search = $db->fetch_array($query);

	if(!$search['sid'])
	{
		error($lang->error_invalidsearch);
	}

	$plugins->run_hooks("search_results_start");

	// Decide on our sorting fields and sorting order.
	$order = strtolower($mybb->input['order']);
	$sortby = $mybb->input['sortby'];

	switch($sortby)
	{
		case "replies":
			$sortfield = "t.replies";
			break;
		case "views":
			$sortfield = "t.views";
			break;
		case "dateline":
			if($search['resulttype'] == "threads")
			{
				$sortfield = "t.dateline";
			}
			else
			{
				$sortfield = "p.dateline";
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
		default:
			$sortfield = "t.lastpost";
			break;
	}


	if($order != "asc")
	{
		$order = "desc";
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

	$sorturl = "search.php?action=results&sid=$sid";

	// Read some caches we will be using
	$forumcache = $cache->read("forums");
	$iconcache = $cache->read("posticons");

	$threads = array();

	// Show search results as 'threads'
	if($search['resulttype'] == "threads")
	{
		$threadcount = 0;
		// If we have saved WHERE conditions, execute them
		if($search['querycache'] != "")
		{
			$where_conditions = $search['querycache'];
			$query = $db->query("
				SELECT t.tid
				FROM ".TABLE_PREFIX."threads t
				WHERE $where_conditions
			");
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
			$query = $db->query("
				SELECT COUNT(t.tid) AS resultcount
				FROM ".TABLE_PREFIX."threads t
				WHERE $where_conditions
			");
			$count = $db->fetch_array($query);

			if(!$count['resultcount'])
			{
				error($lang->nosearchresults);
			}
			$threadcount = $count['resultcount'];
		}
		// Begin selecting matching threads, cache them.
		$query = $db->query("
			SELECT t.*
			FROM ".TABLE_PREFIX."threads t
			WHERE $where_conditions
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
		if($mybb->settings['dotfolders'] != "no" && $mybb->user['uid'] && $thread_cache)
		{
			$query = $db->query("
				SELECT DISTINCT tid,uid
				FROM ".TABLE_PREFIX."posts
				WHERE uid='".$mybb->user['uid']."'
				AND tid IN(".$thread_ids.")
			");
			while($post = $db->fetch_array($query))
			{
				$thread_cache[$post['tid']]['dot_icon'] = 1;
			}
		}

		// Fetch the read threads.
		if($mybb->user['uid'] && $mybb->settings['threadreadcut'] > 0)
		{
			$query = $db->query("
				SELECT tid,dateline
				FROM ".TABLE_PREFIX."threadsread
				WHERE uid='".$mybb->user['uid']."'
				AND tid IN(".$thread_ids.")
			");
			while($readthread = $db->fetch_array($query))
			{
				$thread_cache[$readthread['tid']]['last_read'] = $readthread['dateline'];
			}
		}

		// Fetch thread 'dots'
		foreach($thread_cache as $thread)
		{
			if($bgcolor == "trow1")
			{
				$bgcolor = "trow2";
			}
			else
			{
				$bgcolor = "trow1";
			}
			$folder = '';
			$prefix = '';
			
			if(!$thread['username'])
			{
				$thread['username'] = $thread['threadusername'];
				$thread['profilelink'] = $thread['threadusername'];
			}
			else
			{
				$thread['profilelink'] = "<a href=\"".str_replace("{uid}", $thread['uid'], PROFILE_URL)."\">".$thread['username']."</a>";
			}
			$thread['subject'] = $parser->parse_badwords($thread['subject']);
			$thread['subject'] = htmlspecialchars_uni($thread['subject']);

			if($iconcache[$thread['iid']])
			{
				$posticon = $iconcache[$thread['iid']];
				$icon = "<img src=\"".$posticon['path']."\" alt=\"".$posticon['name']."\" />";
			}
			else
			{
				$icon = "&nbsp;";
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
				$cutoff = time()-$mybb->settings['threadreadcut']*60*60*24;
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
				$readcookie = $threadread = mygetarraycookie("threadread", $thread['tid']);
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
				$folder_label .= $lang->icon_new;
				eval("\$gotounread = \"".$templates->get("forumdisplay_thread_gotounread")."\";");
				$unreadpost = 1;
			}
			else
			{
				$folder_label .= $lang->icon_no_new;
			}

			if($thread['replies'] >= $mybb->settings['hottopic'] || $thread['views'] >= $mybb->settings['hottopicviews'])
			{
				$folder .= "hot";
				$folder_label .= $lang->icon_hot;
			}
			if($thread['closed'] == "yes")
			{
				$folder .= "lock";
				$folder_label .= $lang->icon_lock;
			}
			$folder .= "folder";

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
					eval("\$morelink = \"".$templates->get("forumdisplay_thread_multipage_more")."\";");
				}
				else
				{
					$pagesstop = $thread['pages'];
				}
				for($i=1; $i<=$pagesstop; ++$i)
				{
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
			$lastpostdate = mydate($mybb->settings['dateformat'], $thread['lastpost']);
			$lastposttime = mydate($mybb->settings['timeformat'], $thread['lastpost']);
			$lastposter = $thread['lastposter'];
			$lastposteruid = $thread['lastposter'];
			$thread['replies'] = mynumberformat($thread['replies']);
			$thread['views'] = mynumberformat($thread['views']);

			if($forumcache[$thread['fid']])
			{
				$thread['forumlink'] = "<a href=\"".str_replace("{fid}", $thread['fid'], FORUM_URL)."\">".$forumcache[$thread['fid']]['name']."</a>";
			}
			else
			{
				$thread['forumlink'] = "";
			}
			$plugins->run_hooks("search_results_thread");
			eval("\$results .= \"".$templates->get("search_results_threads_thread")."\";");	
		}
		if(!$results)
		{
			error($lang->error_nosearchresults);
		}
		$multipage = multipage($threadcount, $perpage, $page, "search.php?action=results&sid=$sid&sortby=$sortby&order=$order&uid=".$mybb->input['uid']);
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
			WHERE $where_conditions
		");
		$count = $db->fetch_array($query);

		if(!$count['resultcount'])
		{
			error($lang->nosearchresults);
		}
		$postcount = $count['resultcount'];
		
		$tids = array();
		$query = $db->query("
			SELECT p.tid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE $where_conditions
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
			$query = $db->query("
				SELECT tid, dateline 
				FROM ".TABLE_PREFIX."threadsread 
				WHERE uid='".$mybb->user['uid']."' 
				AND tid IN(".$tids.")
			");
			while($readthread = $db->fetch_array($query))
			{
				$readthreads[$readthread['tid']] = $readthread['dateline'];
			}
		}
		$query = $db->query("
			SELECT p.*, t.subject AS thread_subject, t.replies AS thread_replies, t.views AS thread_views, t.lastpost AS thread_lastpost, t.closed AS thread_closed
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE $where_conditions
			ORDER BY $sortfield $order
			LIMIT $start, $perpage
		");
		while($post = $db->fetch_array($query))
		{
			if($bgcolor == "trow1")
			{
				$bgcolor = "trow2";
			}
			else
			{
				$bgcolor = "trow1";
			}
			if(!$post['username'])
			{
				$post['username'] = $post['postusername'];
				$post['profilelink'] = $post['postusername'];
			}
			else
			{
				$post['profilelink'] = "<a href=\"".str_replace("{uid}", $post['uid'], PROFILE_URL)."\">".$post['username']."</a>";
			}
			$post['subject'] = $parser->parse_badwords($post['subject']);
			$post['subject'] = htmlspecialchars_uni($post['subject']);
			$post['thread_subject'] = $parser->parse_badwords($post['thread_subject']);
			$post['thread_subject'] = htmlspecialchars_uni($post['thread_subject']);

			if($iconcache[$post['iid']])
			{
				$posticon = $iconcache[$post['iid']];
				$icon = "<img src=\"".$posticon['path']."\" alt=\"".$posticon['name']."\" />";
			}
			else
			{
				$icon = "&nbsp;";
			}

			if($forumcache[$thread['fid']])
			{
				$post['forumlink'] = "<a href=\"".str_replace("{fid}", $post['fid'], FORUM_URL)."\">".$forumcache[$post['fid']]['name']."</a>";
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
				$cutoff = time()-$mybb->settings['threadreadcut']*60*60*24;
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
			if(!$lastread)
			{
				$readcookie = $threadread = mygetarraycookie("threadread", $oist['tid']);
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
			if($thread['thread_closed'] == "yes")
			{
				$folder .= "lock";
				$folder_label .= $lang->icon_lock;
			}
			$folder .= "folder";

			$post['thread_replies'] = mynumberformat($post['thread_replies']);
			$post['thread_views'] = mynumberformat($post['thread_views']);

			if($forumcache[$post['fid']])
			{
				$post['forumlink'] = "<a href=\"".str_replace("{fid}", $post['fid'], FORUM_URL)."\">".$forumcache[$post['fid']]['name']."</a>";
			}
			else
			{
				$post['forumlink'] = "";
			}

			if(!$post['subject'])
			{
				$post['subject'] = $post['message'];
			}
			if(strlen($post['subject']) > 50)
			{
				$post['subject'] = substr($post['subject'], 0, 50)."...";
			}
			else
			{
				$post['subject'] = $post['subject'];
			}
			if(strlen($post['message']) > 200)
			{
				$prev = htmlspecialchars_uni(substr($post['message'], 0, 200)."...");
			}
			else
			{
				$prev = htmlspecialchars_uni($post['message']);
			}
			$posted = mydate($mybb->settings['dateformat'], $post['dateline']).", ".mydate($mybb->settings['timeformat'], $post['dateline']);

			$plugins->run_hooks("search_results_post");
			eval("\$results .= \"".$templates->get("search_results_posts_post")."\";");	
		}
		if(!$results)
		{
			error($lang->error_nosearchresults);
		}
		$multipage = multipage($postcount, $perpage, $page, "search.php?action=results&sid=$sid&sortby=$sortby&order=$order&uid=".$mybb->input['uid']);
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
		"dateline" => time(),
		"ipaddress" => $ipaddress,
		"threads" => '',
		"posts" => '',
		"searchtype" => "titles",
		"resulttype" => "posts",
		"querycache" => $db->escape_string($where_sql),
	);
	$plugins->run_hooks("search_do_search_process");
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);
	redirect("search.php?action=results&sid=".$sid, $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "finduser")
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
		"dateline" => time(),
		"ipaddress" => $ipaddress,
		"threads" => '',
		"posts" => '',
		"searchtype" => "titles",
		"resulttype" => "posts",
		"querycache" => $db->escape_string($where_sql),
	);
	$plugins->run_hooks("search_do_search_process");
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);
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
		"dateline" => time(),
		"ipaddress" => $ipaddress,
		"threads" => '',
		"posts" => '',
		"searchtype" => "titles",
		"resulttype" => "threads",
		"querycache" => $db->escape_string($where_sql),
	);
	$plugins->run_hooks("search_do_search_process");
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);
	redirect("search.php?action=results&sid=".$sid, $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "getnew")
{

	$where_sql = "t.lastpost >= '".$mybb->user['lastvisit']."'";

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
		"dateline" => time(),
		"ipaddress" => $ipaddress,
		"threads" => '',
		"posts" => '',
		"searchtype" => "titles",
		"resulttype" => "threads",
		"querycache" => $db->escape_string($where_sql),
	);

	$plugins->run_hooks("search_do_search_process");
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);
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
	$datecut = time()-(68400*$days);

	$where_sql = "t.lastpost >='".$datecut."'";

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
		"dateline" => time(),
		"ipaddress" => $ipaddress,
		"threads" => '',
		"posts" => '',
		"searchtype" => "titles",
		"resulttype" => "threads",
		"querycache" => $db->escape_string($where_sql),
	);

	$plugins->run_hooks("search_do_search_process");
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);
	redirect("search.php?action=results&sid=".$sid, $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "do_search")
{
	$plugins->run_hooks("search_do_search_start");

	// Check if search flood  checking is enabled
	if($mybb->settings['searchfloodtime'] > 0)
	{
		// Fetch the time this user last searched
		if($mybb->user['uid'])
		{
			$conditions = "uid='{$mybb->user['uid']}'";
		}
		else
		{
			$conditions = "uid='0' AND ipaddress='{$ipaddress}'";
		}
		$timecut = time()-$mybb->settings['searchfloodtime'];
		$query = $db->query("
			SELECT * 
			FROM ".TABLE_PREFIX."searchlog 
			WHERE $conditions 
			AND dateline >= '$timecut' 
			ORDER BY dateline DESC
		");
		$last_search = $db->fetch_array($query);
		// Users last search was within the flood time, show the error
		if($last_search['sid'])
		{
			$lang->error_searchflooding = sprintf($lang->error_searchflooding, $mybb->settings['searchfloodtime']);
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
		"forums" => $mybb->input['forums']
	);

	if($config['dbtype'] == "mysql" || $config['dbtype'] == "mysqli")
	{
		if($settings['searchtype'] == "fulltext" && $db->supports_fulltext_boolean(TABLE_PREFIX."posts") && $db->is_fulltext(TABLE_PREFIX."posts"))
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
		"ipaddress" => $ipaddress,
		"threads" => $search_results['threads'],
		"posts" => $search_results['posts'],
		"searchtype" => $search_results['searchtype'],
		"resulttype" => $resulttype,
		"querycache" => $search_results['querycache'],
		);
	$plugins->run_hooks("search_do_search_process");

	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);

	if(strtolower($mybb->input['sortordr']) == "asc" || strtolower($mybb->input['sortordr'] == "desc"))
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
else
{
	$plugins->run_hooks("search_start");
	$srchlist = make_searchable_forums("", "$fid");
	eval("\$search = \"".$templates->get("search")."\";");
	$plugins->run_hooks("search_end");
	output_page($search);
}

?>