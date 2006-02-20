<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

$templatelist = "search,redirect,redirect_searchnomore,redirect_searchnotfound,search_results,search_showresults,search_showcalres,search_showhlpres";
$templatelist .= "";
require "./global.php";
require "./inc/functions_post.php";
require "./inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("search");

addnav($lang->nav_search, "search.php");

switch($mybb->input['action'])
{
	case "results":
		addnav($lang->nav_results);
		break;
}

if($mybb->usergroup['cansearch'] == "no")
{
	nopermission();
}

$now = time();

if($mybb->input['action'] == "results")
{
	$sid = addslashes($mybb->input['sid']);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."searchlog WHERE sid='$sid'");
	$search = $db->fetch_array($query);

	if(!$search['sid'])
	{
		error($lang->error_invalidsearch);
	}
	$plugins->run_hooks("search_results_start");

	$order = strtolower($mybb->input['order']);
	$sortby = $mybb->input['sortby'];

	if($order != "asc")
	{
		$order = "desc";
	}

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

	if($search['resulttype'] == "threads")
	{
		switch($sortby)
		{
			case "replies":
				$sortfield = "t.replies";
				break;
			case "views":
				$sortfield = "t.views";
				break;
			case "dateline":
				$sortfield = "t.dateline";
				break;
			case "forum":
				$sortfield = "t.fid";
				break;
			case "start":
				$sortfield = "t.username";
				break;
			default:
				$sortfield = "t.lastpost";
				break;
		}

		$threadcount = 0;
		if($search['querycache'] != "")
		{
			$query = $search['querycache'];
			$query .= " ORDER BY $sortfield $order";
			$query .= " LIMIT $start, $perpage";

			$query = $db->query($query);
		}
		else
		{
			if(!$search['threads'])
			{
				error($lang->error_nosearchresults);
			}
			$query = $db->query("
				SELECT t.*
				FROM ".TABLE_PREFIX."threads t
				WHERE t.tid IN (".$search['threads'].")
				ORDER BY $sortfield $order
				LIMIT $start, $perpage
			");
		}

		while($thread = $db->fetch_array($query))
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
				$icon = "<img src=\"$result[iconpath]\" alt=\"$result[iconname]\">";
			}
			else
			{
				$icon = "&nbsp;";
			}
			// Determine the folder
			$folder = '';
			$folder_label = '';
			if($thread['doticon'])
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
			if(!$lastread)
			{
				$readcookie = $threadread = mygetarraycookie("threadread", $thread['tid']);
				if($readcookie > $forumread)
				{
					$lastread = $readcookie;
				}
				else
				{
					$lastread = $forumread;
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
			eval("\$lastpost = \"".$templates->get("forumdisplay_thread_lastpost")."\";");
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
			eval("\$results .= \"".$templates->get("search_results_thread")."\";");	
			$threadcount++;
		}
		if(!$results)
		{
			error($lang->error_nosearchresults);
		}
		$multipage = multipage($threadcount, $perpage, $page, "search.php?action=results&sid=$sid&sortby=$sortby&order=$order&uid=".$mybb->input['uid']);
		eval("\$searchresultsbar = \"".$templates->get("search_results_barthreads")."\";");
		eval("\$searchresults = \"".$templates->get("search_results")."\";");
		$plugins->run_hooks("search_results_end");
		outputpage($searchresults);
	}
/*


	if($sortby == "subject")
	{
		$sort
	if($order == "asc")
	{
		$sortorder = "ASC";
	}
	else
	{
		$sortorder = "DESC";
		$order = "desc";
	}
	if($sortby == "subject")
	{
		$sortfield = "subject";
	}
	elseif($sortby == "replies")
	{
		$sortfield = "replies";
	}
	elseif($sortby == "views")
	{
		$sortfield = "views";
	}
	elseif($sortby == "starter")
	{
		$sortfield = "username";
	}
	elseif($sortby == "lastposter")
	{
		$sortfield = "t.lastposter";
	}
	elseif($sortby == "dateline")
	{
		$sortfield = "p.dateline";
	}
	elseif($sortby == "forum")
	{
		$sortfield = "f.name";
	}
	else
	{
		if($search['showposts'] == "2")
		{
			$sortby = "dateline";
			$sortfield = "p.dateline";
		}
		else
		{
			$sortby = "lastpost";
			$sortfield = "t.lastpost";
		}
	}
	
	$dotadd1 = "";
	$dotadd2 = "";
	if($mybb->settings['dotfolders'] != "no" && $mybb->user['uid'])
	{
		$dotadd1 = "DISTINCT p.uid AS dotuid, ";
		$dotadd2 = "LEFT JOIN ".TABLE_PREFIX."posts p ON (t.tid = p.tid AND p.uid='".$mybb->user['uid']."')";
	}
	$unsearchforums = getunsearchableforums();
	if($unsearchforums)
	{
		$search['wheresql'] .= " AND t.fid NOT IN ($unsearchforums)";
	}

	// Start getting the results..
	if($search['showposts'] == "2")
	{
		$sql = "SELECT p.pid, p.tid, p.fid, p.subject, p.message, p.uid, t.subject AS tsubject, t.lastposter AS tlastposter, t.replies AS treplies, t.views AS tviews, t.lastpost AS tlastpost, t.closed, p.dateline, i.name as iconname, i.path as iconpath, p.username AS postusername, u.username, f.name AS forumname FROM (".TABLE_PREFIX."posts p, ".TABLE_PREFIX."threads t) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid = p.icon) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = p.uid) LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid) WHERE $search[wheresql] AND f.active!='no' AND t.closed NOT LIKE 'moved|%' AND t.tid=p.tid AND t.visible='1' AND p.visible='1' ORDER BY $sortfield $sortorder";
	}
	else
	{
		$sql = "SELECT DISTINCT(p.tid), p.pid, p.fid, ".$search['lookin'].", t.subject AS tsubject, t.uid, t.lastposter AS tlastposter, t.replies AS treplies, t.views AS tviews, t.lastpost, t.closed, p.dateline, i.name as iconname, i.path as iconpath, t.username AS threadusername, u.username, f.name AS forumname FROM (".TABLE_PREFIX."posts p, ".TABLE_PREFIX."threads t) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid = t.icon) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid) LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid) WHERE $search[wheresql] AND f.active!='no' AND t.closed NOT LIKE 'moved|%' AND t.tid=p.tid AND t.visible='1' GROUP BY p.tid ORDER BY $sortfield $sortorder";
	}
	$query = $db->query($sql);
	$resultcount = $db->num_rows($query);

	$query = $db->query($sql);
	$donecount = 0;
	$bgcolor= "trow1";
	while($result = $db->fetch_array($query))
	{
		if($search['showposts'] == 2)
		{
			$resultcache[$result['pid']] = $result;
		}
		else
		{
			$resultcache[$result['tid']] = $result;
		}
		$tids[$result['tid']] = $result['tid'];
	}

	$tids = implode(",", $tids);

	// Read threads
	if($mybb->user['uid'] && $mybb->settings['threadreadcut'] > 0)
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threadsread WHERE uid='".$mybb->user[uid]."' AND tid IN($tids)");
		while($readthread = $db->fetch_array($query))
		{
			$readthreads[$readthread['tid']] = $readthread['dateline'];
		}
	}
	$results = '';
	foreach($resultcache as $result)
	{
		$thread = $result;
		if($result['iconpath'])
		{
			$icon = "<img src=\"$result[iconpath]\" alt=\"$result[iconname]\">";
		}
		else
		{
			$icon = "&nbsp;";
		}
		$folder = "";
		if($mybb->settings['dotfolders'] == "yes" && $result['dotuid'] == $mybb->user['uid'] && $mybb->user['uid'])
		{
			$folder .= "dot_";
		}

		$isnew = 0;
		$forumread = mygetarraycookie("forumread", $result['fid']);
		if($result['lastpost'] > $forumread && $result['lastpost'] > $mybb->user['lastvisit'])
		{
			$readcut = $mybb->settings['threadreadcut']*60*60*24;
			if($mybb->user['uid'] && $readcut)
			{
				$cutoff = time()-$readcut;
				if($result['lastpost'] > $cutoff)
				{
					if($readthreads[$result['tid']] < $result['lastpost'])
					{
						$isnew = 1;
						$donenew = 1;
					}
					else
					{
						$donenew = 1;
					}
				}
			}
			if(!$donenew)
			{
				$tread = mygetarraycookie("threadread", $result['tid']);
				if($result['lastpost'] > $tread)
				{
					$isnew = 1;
				}
			}
		}
		if($isnew)
		{
			$folder .= "new";
			eval("\$gotounread = \"".$templates->get("forumdisplay_thread_gotounread")."\";");
			$unreadpost = 1;
		}
		if($result['treplies'] >= $mybb->settings['hottopic'] || $result['tviews'] >= $mybb->settings['hottopicviews'])
		{
			$folder .= "hot";
		}
		if($result['closed'] == "yes")
		{
			$folder .= "lock";
		}
		$folder .= "folder";

		if($search['showposts'] == 2)
		{
			$result['tsubject'] = htmlspecialchars_uni($parser->parse_badwords($result['tsubject']));
			$result['subject'] = htmlspecialchars_uni($parser->parse_badwords($result['subject']));
			$result['message'] = htmlspecialchars_uni($parser->parse_badwords($result['message']));
			if(!$result['subject'])
			{
				$result['subject'] = $result['message'];
			}
			if(strlen($result['subject']) > 50)
			{
				$title = substr($result['subject'], 0, 50)."...";
			}
			else
			{
				$title = $result['subject'];
			}
			if(strlen($result['message']) > 200)
			{
				$prev = substr($result['message'], 0, 200)."...";
			}
			else
			{
				$prev = $result['message'];
			}
			$posted = mydate($mybb->settings['dateformat'], $result['dateline']).", ".mydate($mybb->settings['timeformat'], $result['dateline']);
			$plugins->run_hooks("search_results_post");
			eval("\$results .= \"".$templates->get("search_results_post")."\";");		
		}
		else
		{
			$lastpostdate = mydate($mybb->settings['dateformat'], $result['lastpost']);
			$lastposttime = mydate($mybb->settings['timeformat'], $result['lastpost']);
			$lastposter = $result['lastposter'];
			$lastposteruid = $result['lastposter'];
	
			if(!$result['username'])
			{
				$result['username'] = $result['threadusername'];
			}
			$result['subject'] = htmlspecialchars_uni($result['subject']);

			$result['pages'] = 0;
			$result['multipage'] = "";
			$threadpages = "";
			$morelink = "";
			$result['posts'] = $result['replies'] + 1;
			if($result['posts'] > $mybb->settings['postsperpage'])
			{
				$result['pages'] = $result['posts'] / $mybb->settings['postsperpage'];
				$result['pages'] = ceil($result['pages']);
				$thread['pages'] = $result['pages'];
				if($result['pages'] > 4)
				{
					$pagesstop = 4;
					eval("\$morelink = \"".$templates->get("forumdisplay_thread_multipage_more")."\";");
				}
				else
				{
					$pagesstop = $result['pages'];
				}
				for($i=1;$i<=$pagesstop;$i++)
				{
					eval("\$threadpages .= \"".$templates->get("forumdisplay_thread_multipage_page")."\";");
				}
				eval("\$result[multipage] = \"".$templates->get("forumdisplay_thread_multipage")."\";");
			}
			else
			{
				$threadpages = "";
				$morelink = "";
				$result['multipage'] = "";
			}
			$result['replies'] = mynumberformat($result['replies']);
			$result['views'] = mynumberformat($result['views']);
			$plugins->run_hooks("search_results_thread");
			eval("\$results .= \"".$templates->get("search_results_thread")."\";");		
		}
		if($bgcolor == "trow2")
		{
			$bgcolor = "trow1";
		}
		else
		{
			$bgcolor = "trow2";
		}
	}
	$multipage = multipage($resultcount, $perpage, $page, "search.php?action=results&sid=$sid&sortby=$sortby&order=$order&uid=".$mybb->input['uid']."&dateline=".$mybb->input['dateline']);
	if($search['showposts'] == 2)
	{
		eval("\$searchresultsbar = \"".$templates->get("search_results_barposts")."\";");
	}
	else
	{
		eval("\$searchresultsbar = \"".$templates->get("search_results_barthreads")."\";");
	}
	eval("\$searchresults = \"".$templates->get("search_results")."\";");
	$plugins->run_hooks("search_results_end");
	outputpage($searchresults); */
}
elseif($mybb->input['action'] == "findguest")
{
	$wheresql = " AND p.uid < 1";
	
	$searcharray = array(
		"uid" => $mybb->user['uid'],
		"dateline" => $now,
		"ipaddress" => $ipaddress,
		"wheresql" => $wheresql,
		"lookin" => "p.message",
		"showposts" => 2
		);
	$plugins->run_hooks("search_do_search_process");
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);
	$sid = $db->insert_id();

	redirect("search.php?action=results&sid=$sid&uid=".$mybb->user['uid']."&dateline=$now", $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "finduser")
{
	$wheresql = "p.uid='".intval($mybb->input['uid'])."'";
	
	$searcharray = array(
		"uid" => $mybb->user['uid'],
		"dateline" => $now,
		"ipaddress" => $ipaddress,
		"wheresql" => addslashes($wheresql),
		"lookin" => "p.message",
		"showposts" => 2
		);
	$plugins->run_hooks("search_do_search_process");
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);
	$sid = $db->insert_id();
	redirect("search.php?action=results&sid=$sid&uid=".$mybb->user['uid']."&dateline=$now", $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "finduserthreads")
{
	$query = "
		SELECT t.*
		FROM ".TABLE_PREFIX."threads t
		WHERE t.uid='".intval($mybb->input['tid'])."'
	";

	$sid = md5(uniqid(microtime(), 1));
	$searcharray = array(
		"sid" => addslashes($sid),
		"uid" => $mybb->user['uid'],
		"dateline" => time(),
		"ipaddress" => $ipaddress,
		"threads" => '',
		"posts" => '',
		"searchtype" => "titles",
		"resulttype" => "threads",
		"querycache" => addslashes($query),
	);
	$plugins->run_hooks("search_do_search_process");
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);
	redirect("search.php?action=results&sid=$sid", $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "getnew")
{
	$query = "
		SELECT t.*
		FROM ".TABLE_PREFIX."threads t
		WHERE t.lastpost >= '".$mybb->user['lastvisit']."'
	";

	$sid = md5(uniqid(microtime(), 1));
	$searcharray = array(
		"sid" => addslashes($sid),
		"uid" => $mybb->user['uid'],
		"dateline" => time(),
		"ipaddress" => $ipaddress,
		"threads" => '',
		"posts" => '',
		"searchtype" => "titles",
		"resulttype" => "threads",
		"querycache" => addslashes($query),
	);

	$plugins->run_hooks("search_do_search_process");
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);

	eval("\$redirect = \"".$templates->get("redirect_searchresults")."\";");
	redirect("search.php?action=results&sid=$sid", $lang->redirect_searchresults);
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

	$query = "
		SELECT t.*
		FROM ".TABLE_PREFIX."threads t
		WHERE t.lastpost >= '".$datecut."'
	";

	$sid = md5(uniqid(microtime(), 1));
	$searcharray = array(
		"sid" => addslashes($sid),
		"uid" => $mybb->user['uid'],
		"dateline" => time(),
		"ipaddress" => $ipaddress,
		"threads" => '',
		"posts" => '',
		"searchtype" => "titles",
		"resulttype" => "threads",
		"querycache" => addslashes($query),
	);

	$plugins->run_hooks("search_do_search_process");
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);

	eval("\$redirect = \"".$templates->get("redirect_searchresults")."\";");
	redirect("search.php?action=results&sid=$sid", $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "do_search")
{
	$keywords = clean_keywords($mybb->input['keywords']);
	if(!$keywords && !$mybb->input['author'])
	{
		error($lang->error_nosearchterms);
	}

	$plugins->run_hooks("search_do_search_start");

	if($mybb->settings['minsearchword'] < 1)
	{
		$mybb->settings['minsearchword'] = 4;
	}
	if($keywords)
	{
		// Complex search
		if(preg_match("# and|or #", $keywords))
		{
			$subject_lookin = "(";
			$message_lookin = "(";
			$matches = preg_split("#\s{1,}(and|or)\s{1,}#", $keywords, -1, PREG_SPLIT_DELIM_CAPTURE);
			$count_matches = count($matches);
			for($i=0;$i<$count_matches;$i++)
			{
				$word = trim($matches[$i]);
				if($word == "")
				{
					continue;
				}
				if($i % 2 && ($word == "and" || $word == "or"))
				{
					$boolean = $word;
				}
				else
				{
					if(strlen($word) < $mybb->settings['minsearchword'])
					{
						$lang->error_minsearchlength = sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
						error($lang->error_minsearchlength);
					}
					$subject_lookin .= " $boolean LOWER(t.subject) LIKE '%".$word."%'";
					if($mybb->input['postthread'] == 1)
					{
						$message_lookin .= " $boolean LOWER(p.message) LIKE '%".trim($word)."%'";
					}
				}
			}
			$subject_lookin .= ")";
			$message_lookin .= ")";
		}
		else
		{
			if(strlen($keywords) < $mybb->settings['minsearchword'])
			{
				$lang->error_minsearchlength = sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
				error($lang->error_minsearchlength);
			}
			$subject_lookin = " LOWER(t.subject) LIKE '%".trim($keywords)."%'";
			if($mybb->input['postthread'] == 1)
			{
				$message_lookin = " LOWER(p.message) LIKE '%".trim($keywords)."%'";
			}
		}
	}
	$post_usersql = "";
	$thread_usersql = "";
	if($mybb->input['author'])
	{
		$userids = array();
		if($mybb->input['matchusername'])
		{
			$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['author'])."'");
		}
		else
		{
			$mybb->input['author'] = strtolower($mybb->input['author']);
			$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE LOWER(username) LIKE '%".addslashes($mybb->input['author'])."%'");
		}
		while($user = $db->fetch_array($query))
		{
			$userids[] = $user['uid'];
		}
		if(count($userids) < 1)
		{
			error($lang->error_nosearchresults);
		}
		else
		{
			$userids = implode(",", $userids);
			$post_usersql = " AND p.uid IN (".$userids.")";
			$thread_usersql = " AND t.uid IN (".$userids.")";
		}
	}
	$datecut = "";
	if($mybb->input['postdate'])
	{
		if($mybb->input['pddir'] == 0)
		{
			$datecut = "<=";
		}
		else
		{
			$datecut = ">=";
		}
		$datelimit = $now-(86400 * $mybb->input['postdate']);
		$datecut .= "'$datelimit'";
		$post_datecut = "p.dateline $datecut";
		$thread_datecut = "t.dateline $datecut";
	}

	$forumin = "";
	$fidlist = array();
	if($mybb->input['forums'] != "all")
	{
		if(!is_array($mybb->input['forums']))
		{
			$mybb->input['forums'] = array(intval($mybb->input['forums']));
		}
		foreach($mybb->input['forums'] as $forum)
		{
			if(!$searchin[$forum])
			{
				$query = $db->query("SELECT f.fid FROM ".TABLE_PREFIX."forums f LEFT JOIN ".TABLE_PREFIX."forumpermissions p ON (f.fid=p.fid AND p.gid='".$mybb->user[usergroup]."') WHERE INSTR(CONCAT(',',parentlist,','),',$forum,') > 0 AND active!='no' AND (ISNULL(p.fid) OR p.cansearch='yes')");
				if($db->num_rows($query) == 1)
				{
					$forumin .= " AND t.fid='$forum' ";
					$searchin[$fid] = 1;
				}
				else
				{
					while($sforum = $db->fetch_array($query))
					{
						$fidlist[] = $sforum['sid'];
					}
					if(count($fidlist) > 1)
					{
						$forumin = " AND t.fid IN (".implode(",", $fidlist).")";
					}
				}
			}
		}
	}
	$unsearchforums = getunsearchableforums();
	if($unsearchforums)
	{
		$permsql = " AND t.fid NOT IN ($unsearchforums)";
	}

	// Searching both posts and thread titles
	$threads = array();
	$posts = array();
	$firstposts = array();

	if($mybb->input['postthread'] == 1)
	{
		$searchtype = "titles";
		$query = $db->query("
			SELECT t.tid, t.firstpost
			FROM ".TABLE_PREFIX."threads t
			WHERE 1=1 $thread_datecut $forumin $thread_usersql AND t.visible>0 AND t.closed NOT LIKE 'moved|%' AND ($subject_lookin)
		");
		while($thread = $db->fetch_array($query))
		{
			$threads[$thread['tid']] = $thread['tid'];
			if($thread['firstpost'])
			{
				$posts[$thread['tid']] = $thread['firstpost'];
			}
		}
		$query = $db->query("
			SELECT p.pid, p.tid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE 1=1 $post_datecut $forumin $post_usersql AND p.visible>0 AND t.visible>0 AND t.closed NOT LIKE 'moved|%' AND ($message_lookin)
		");
		while($post = $db->fetch_array($query))
		{
			$posts[$post['pid']] = $post['pid'];
			$threads[$post['tid']] = $post['tid'];
		}
		if(count($posts) < 1 && count($threads) < 1)
		{
			error($lang->error_nosearchresults);
		}
		$threads = implode(",", $threads);
		$posts = implode(",", $posts);

	}
	// Searching only thread titles
	else
	{
		$searchtype = "posts";
		$query = $db->query("
			SELECT t.tid, t.firstpost
			FROM ".TABLE_PREFIX."threads t
			WHERE 1=1 $thread_datecut $forumin $thread_usersql AND t.visible>0 AND ($subject_lookin)
		");
		while($thread = $db->fetch_array($query))
		{
			$threads[$thread['tid']] = $thread['tid'];
			if($thread['firstpost'])
			{
				$firstposts[$thread['tid']] = $thread['firstpost'];
			}
		}
		if(count($threads) < 1)
		{
			error($lang->error_nosearchresults);
		}

		$threads = implode(",", $threads);
		$firstposts = implode(",", $firstposts);
		if($firstposts)
		{
			$query = $db->query("
				SELECT p.pid
				FROM ".TABLE_PREFIX."posts p
				WHERE p.pid IN ($firstposts) AND p.visible>0
			");
			while($post = $db->fetch_array($query))
			{
				$posts[$post['pid']] = $post['pid'];
			}
			$posts = implode(",", $posts);
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
	$sid = md5(uniqid(microtime(), 1));
	$searcharray = array(
		"sid" => addslashes($sid),
		"uid" => $mybb->user['uid'],
		"dateline" => $now,
		"ipaddress" => $ipaddress,
		"threads" => $threads,
		"posts" => $posts,
		"searchtype" => $searchtype,
		"resulttype" => $resulttype,
		"querycache" => "",
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
	redirect("search.php?action=results&sid=$sid&sortby=".$sortby."&order=".$sortorder."&uid=".$mybb->user['uid']."&dateline=$now", $lang->redirect_searchresults);
}
else
{
	$plugins->run_hooks("search_start");
	$srchlist = makesearchforums("", "$fid");
	eval("\$search = \"".$templates->get("search")."\";");
	$plugins->run_hooks("search_end");
	outputpage($search);
}

function makesearchforums($pid="0", $selitem="", $addselect="1", $depth="", $permissions="")
{
	global $db, $pforumcache, $permissioncache, $settings, $mybb, $mybbuser, $selecteddone, $forumlist, $forumlistbits, $theme, $templates, $mybbgroup, $lang, $forumpass;
	$pid = intval($pid);
	if(!$permissions)
	{
		$permissions = $mybb->usergroup;
	}
	if(!is_array($pforumcache))
	{
		// Get Forums
		$query = $db->query("SELECT f.* FROM ".TABLE_PREFIX."forums f WHERE linkto='' ORDER BY f.pid, f.disporder");
		while($forum = $db->fetch_array($query))
		{
			$pforumcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
	}
	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}
	if(is_array($pforumcache[$pid]))
	{
		while(list($key, $main) = each($pforumcache[$pid]))
		{
			while(list($key, $forum) = each($main))
			{
				$perms = $permissioncache[$forum['fid']];
				if(($perms['canview'] == "yes" || $mybb->settings['hideprivateforums'] == "no") && $perms['cansearch'] != "no")
				{
					if($selitem == $forum['fid'])
					{
						$optionselected = "selected";
						$selecteddone = "1";
					}
					else
					{
						$optionselected = "";
						$selecteddone = "0";
					}
					if($forum['password'] != "")
					{
						if($forumpass[$forum['fid']] == md5($mybb->user['uid'].$forum['password']))
						{
							$pwverified = 1;
						}
						else
						{
							$pwverified = 0;
						}
					}
					if($forum['password'] == "" || $pwverified == 1)
					{
						$forumlistbits .= "<option value=\"$forum[fid]\">$depth $forum[name]</option>\n";
					}
					if($pforumcache[$forum['fid']])
					{
						$newdepth = $depth."&nbsp;&nbsp;&nbsp;&nbsp;";
						$forumlistbits .= makesearchforums($forum['fid'], $selitem, 0, $newdepth, $perms);
					}
				}
			}
		}
	}
	if($addselect)
	{
		$forumlist = "<select name=\"forums\" size=\"15\" multiple=\"multiple\">\n<option value=\"all\" selected>$lang->search_all_forums</option>\n<option value=\"all\">----------------------</option>\n$forumlistbits\n</select>";
	}
	return $forumlist;
}

function getunsearchableforums($pid="0", $first=1)
{
	global $db, $forumcache, $permissioncache, $settings, $mybb, $mybbuser, $mybbgroup, $unsearchableforums, $unsearchable, $templates, $forumpass;
	$pid = intval($pid);
	if(!$permissions)
	{
		$permissions = $mybb->usergroup;
	}
	if(!is_array($forumcache))
	{
		// Get Forums
		$query = $db->query("SELECT f.* FROM ".TABLE_PREFIX."forums f WHERE active!='no' ORDER BY f.pid, f.disporder");
		while($forum = $db->fetch_array($query))
		{
			if($pid != "0")
			{
				$forumcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
			}
			else
			{
				$forumcache[$forum['fid']] = $forum;
			}
		}
	}
	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}
	foreach($forumcache as $fid => $forum)
	{
		if($permissioncache[$forum['fid']])
		{
			$perms = $permissioncache[$forum['fid']];
		}
		else
		{
			$perms = $mybb->usergroup;
		}

		$pwverified = 1;
		if($forum['password'] != "")
		{
			if($forumpass[$forum['fid']] != md5($mybb->user['uid'].$forum['password']))
			{
				$pwverified = 0;
			}
		}
		
		if($perms['canview'] == "no" || $perms['cansearch'] == "no" || $pwverified == 0)
		{
			if($unsearchableforums)
			{
				$unsearchableforums .= ",";
			}
			$unsearchableforums .= "'$forum[fid]'";
		}
	}
	$unsearchable = $unsearchableforums;
	return $unsearchable;
}

function clean_keywords($keywords)
{
	static $stopwords;
	$keywords = strtolower($keywords);
	$keywords = str_replace("%", "\\%", $keywords);
	$keywords = str_replace("*", "%", $keywords);
	$keywords = preg_replace("#([\[\]\|\.\,:\"'])#s", " ", $keywords);
	$keywords = preg_replace("#\s+#s", " ", $keywords);
	return trim($keywords);
}

?>