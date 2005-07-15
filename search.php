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
 define("KILL_GLOBALS", 1);

$templatelist = "search,redirect,redirect_searchnomore,redirect_searchnotfound,search_results,search_showresults,search_showcalres,search_showhlpres";
$templatelist .= "";
require "./global.php";
require "./inc/functions_post.php";

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

if($mybb->input['action'] == "results")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."searchlog WHERE sid='".intval($mybb->input['sid'])."'");
	$search = $db->fetch_array($query);
	if(!$search['sid'])
	{
		error($lang->error_invalidsearch);
	}
	$order = $mybb->input['order'];
	$sortby = $mybb->input['sortby'];

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
		$dotadd2 = "LEFT JOIN ".TABLE_PREFIX."posts p ON (t.tid = p.tid AND p.uid='".$mybb->user[uid]."')";
	}
	$unsearchforums = getunsearchableforums();
	if($unsearchforums)
	{
		$search['wheresql'] .= " AND t.fid NOT IN ($unsearchforums)";
	}

	// Start getting the results..
	if($search['showposts'] == "2")
	{
		$sql = "SELECT p.pid, p.tid, p.fid, p.subject, p.message, p.uid, t.subject AS tsubject, t.lastposter AS tlastposter, t.replies AS treplies, t.views AS tviews, t.lastpost AS tlastpost, p.dateline, i.name as iconname, i.path as iconpath, p.username AS postusername, u.username, f.name AS forumname FROM ".TABLE_PREFIX."posts p, ".TABLE_PREFIX."threads t LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid = p.icon) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = p.uid) LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid) WHERE $search[wheresql] AND f.active!='no' AND t.closed NOT LIKE 'moved|%' AND t.tid=p.tid AND t.visible='1' AND p.visible='1' ORDER BY $sortfield $sortorder";
	}
	else
	{
		$sql = "SELECT DISTINCT(p.tid), p.pid, p.fid, ".$search[lookin].", t.subject, t.uid, t.lastposter, t.replies, t.views, t.lastpost, p.dateline, i.name as iconname, i.path as iconpath, t.username AS threadusername, u.username, f.name AS forumname FROM ".TABLE_PREFIX."posts p, ".TABLE_PREFIX."threads t LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid = t.icon) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid) LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid) WHERE $search[wheresql] AND f.active!='no' AND t.closed NOT LIKE 'moved|%' AND t.tid=p.tid AND t.visible='1' GROUP BY p.tid ORDER BY $sortfield $sortorder";
	}
	$query = $db->query($sql);
	$resultcount = $db->num_rows($query);

	if($search['limitto'] && ($resultcount > $search['limitto']))
	{
		$resultcount = $search['limitto'];
	}
	$perpage = $mybb->settings['threadsperpage'];
	$page = $mybb->input['page'];
	if($page)
	{
		$start = ($page-1) *$perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;
	if($upper > $resultcount)
	{
		$upper = $resultcount;
	}
	if($resultcount == 0)
	{
			error($lang->error_nosearchresults);
	}
	$sorturl = "search.php?action=results&sid=$sid";

	$sql .= " LIMIT $start, $perpage";
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
			$result['tsubject'] = htmlspecialchars_uni(stripslashes(dobadwords($result['tsubject'])));
			$result['subject'] = htmlspecialchars_uni(stripslashes(dobadwords($result['subject'])));
			$result['message'] = htmlspecialchars_uni(stripslashes(dobadwords($result['message'])));
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
	$multipage = multipage($resultcount, $perpage, $page, "search.php?action=results&sid=$sid&sortby=$sortby&order=$order");
	if($search['showposts'] == 2)
	{
		eval("\$searchresultsbar = \"".$templates->get("search_results_barposts")."\";");
	}
	else
	{
		eval("\$searchresultsbar = \"".$templates->get("search_results_barthreads")."\";");
	}
	eval("\$searchresults = \"".$templates->get("search_results")."\";");
	outputpage($searchresults);
}
elseif($mybb->input['action'] == "findguest")
{
	$wheresql = " AND p.uid < 1";
	$now = time();
	$searcharray = array(
		"sid" => "NULL",
		"uid" => $mybb->user['uid'],
		"dateline" => time(),
		"ipaddress" => $ipaddress,
		"wheresql" => $wheresql,
		"lookin" => "p.message",
		"showposts" => 2
		);
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);
	$sid = $db->insert_id();

	redirect("search.php?action=results&sid=$sid", $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "finduser")
{
	$wheresql = "1=1";
	$wheresql .= " AND p.uid='".intval($mybb->input['uid'])."'";
	$searcharray = array(
		"sid" => "NULL",
		"uid" => $mybb->user['uid'],
		"dateline" => time(),
		"ipaddress" => $ipaddress,
		"wheresql" => addslashes($wheresql),
		"lookin" => "p.message",
		"showposts" => 2
		);
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);
	$sid = $db->insert_id();
	redirect("search.php?action=results&sid=$sid", $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "getnew")
{
	if(!$mybb->input['days'] < 1)
	{
		$days = 1;
	}
	else
	{
		$days = intval($mybb->input['days'];
	}

	$wheresql = "1=1";
	if($mybb->input['fid'])
	{
		$query = $db->query("SELECT f.fid FROM ".TABLE_PREFIX."forums f LEFT JOIN ".TABLE_PREFIX."forumpermissions p ON (f.fid=p.fid AND p.gid='".$mybb->user[usergroup]."') WHERE INSTR(CONCAT(',',parentlist,','),',".intval($mybb->input['fid']).",') > 0 AND (ISNULL(p.fid) OR (p.cansearch='yes' AND p.canview='yes')");
		if($db->num_rows($query) == 1)
		{
			$wheresql .= " AND t.fid='".intval($mybb->input['fid'])."' ";
		}
		else
		{
			$wheresql .= " AND t.fid IN ('".intval($mybb->input['fid'])."'";
			while($sforum = $db->fetch_array($query))
			{
				$wheresql .= ",'$sforum[fid]'";
			}
			$wheresql .= ")";
		}
	}
	$wheresql .= " AND t.lastpost >= '".$mybb->user[lastvisit]."'";
	$searcharray = array(
		"sid" => "NULL",
		"uid" => $mybb->user['uid'],
		"dateline" => time(),
		"ipaddress" => $ipaddress,
		"wheresql" => addslashes($wheresql),
		"lookin" => "p.message",
		"showposts" => 1
		);
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);
	$sid = $db->insert_id();

	eval("\$redirect = \"".$templates->get("redirect_searchresults")."\";");
	redirect("search.php?action=results&sid=$sid", $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "getdaily")
{
	if(!$mybb->input['days'] < 1)
	{
		$days = 1;
	}
	else
	{
		$days = intval($mybb->input['days'];
	}

	$wheresql = "1=1";
	if($mybb->input['fid'])
	{
		$query = $db->query("SELECT f.fid FROM ".TABLE_PREFIX."forums f LEFT JOIN ".TABLE_PREFIX."forumpermissions p ON (f.fid=p.fid AND p.gid='".$mybb->user[usergroup]."') WHERE INSTR(CONCAT(',',parentlist,','),',".intval($mybb->input['fid']).",') > 0 AND (ISNULL(p.fid) OR (p.cansearch='yes' AND p.canview='yes')");
		if($db->num_rows($query) == 1)
		{
			$wheresql .= " AND t.fid='".intval($mybb->input['fid'])."' ";
		}
		else
		{
			$wheresql .= " AND t.fid IN ('".intval($mybb->input['fid'])."'";
			while($sforum = $db->fetch_array($query))
			{
				$wheresql .= ",'$sforum[fid]'";
			}
			$wheresql .= ")";
		}
	}	$now = time();
	$thing = 68400*$days;
	$datecut = $now-$thing;
	$wheresql .= " AND t.lastpost >= '$datecut'";
	$searcharray = array(
		"sid" => "NULL",
		"uid" => $mybb->user['uid'],
		"dateline" => time(),
		"ipaddress" => $ipaddress,
		"wheresql" => addslashes($wheresql),
		"lookin" => "p.message",
		"showposts" => 2
		);
	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);
	$sid = $db->insert_id();
	eval("\$redirect = \"".$templates->get("redirect_searchresults")."\";");
	redirect("search.php?action=results&sid=$sid", $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "do_search")
{
	if(!$mybb->input['keyword'])
	{
		if(!$mybb->input['author'])
		{
			error($lang->error_nosearchterms);
		}
	}
	if($mybb->input['postthread'] == 1)
	{
		$lookin = "p.message";
		$lookin2 = "p.subject";
	}
	else
	{
		$lookin = "p.subject";
	}
	if($mybb->input['srchtype'] == 1)
	{
		$op = "AND";
	}
	elseif($mybb->input['srchtype'] == 3)
	{
		$op = "||";
	}
	else
	{
		$op = "";
	}
	if($mybb->input['keyword']) {
		$wheresql = "(1=0 ";
		if($mybb->input['srchtype'] != 2)
		{
			$words = explode(" ", $mybb->input['keyword']);
			$wordcount = count($words);
			for($i=0;$i<$wordcount;$i++)
			{
				$wheresql .= "OR $op $lookin LIKE '%".addslashes($words[$i])."%'";
				if($lookin2)
				{
					$wheresql .= "OR $op $lookin2 LIKE '%".addslashes($words[$i])."%'";
				}
			}
		}
		else
		{
			$wheresql .=  " AND $lookin LIKE '%".addslashes($mybb->input['keyword'])."%'";
		}
	}
	else
	{
		$wheresql = "(1=1";
	}
	$wheresql .= ")";

	if($mybb->input['author'])
	{
		$usersql = " AND (1=0";
		if($mybb->input['matchusername'])
		{
			$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['author'])."'");
		}
		else
		{
			$mybb->input['author'] = strtolower($mybb->input['author']);
			$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE LCASE(username) LIKE '%".addslashes($mybb->input['author'])."%'");
		}
		if($db->num_rows($query) > 0)
		{
			while($user = $db->fetch_array($query))
			{
				$usersql .= " OR p.uid='$user[uid]'";
			}
		}
		else
		{
			error($lang->error_nosearchresults);
		}
		$usersql .= ")";
	}
	
	$now = time();
	if($mybb->input['postdate'])
	{
		$wheresql .= " AND p.dateline ";
		if($mybb->input['pddir'] == 0)
		{
			$wheresql .= "<=";
		}
		else
		{
			$wheresql .= ">=";
		}
		$datelimit = $now-(86400 * $mybb->input['postdate']);
		$wheresql .= "'$datelimit'";
	}
	if($mybb->input['forums'] != "all")
	{
		foreach($mybb->input['forums'] as $forum)
		{
			if(!$searchin[$forum])
			{
				$query = $db->query("SELECT f.fid FROM ".TABLE_PREFIX."forums f LEFT JOIN ".TABLE_PREFIX."forumpermissions p ON (f.fid=p.fid AND p.gid='".$mybb->user[usergroup]."') WHERE INSTR(CONCAT(',',parentlist,','),',$forum,') > 0 AND active!='no' AND (ISNULL(p.fid) OR p.cansearch='yes')");
				if($db->num_rows($query) == 1)
				{
					$wheresql .= " AND t.fid='$forum' ";
					$searchin[$fid] = 1;
				}
				else
				{
					$wheresql .= " AND t.fid IN ('$forum'";
					while($sforum = $db->fetch_array($query))
					{
						$wheresql .= ",'$sforum[fid]'";
						$searchin[$sforum['fid']] = 1;
					}
					$wheresql .= ")";
				}
			}
		}
	}
	if($mybb->input['findthreadst'] && $mybb->input['numreplies'])
	{
		if($mybb->input['findthreadst'] == "1")
		{
			$wheresql .= " AND t.replies>=".intval($mybb->input['numreplies']);
		}
		elseif($findthreadst == "2")
		{
			$wheresql .= " AND t.replies<=".intval($mybb->input['numreplies']);
		}
	}
		
	$wheresql .= $usersql;

	$unsearchforums = getunsearchableforums();
	if($unsearchforums)
	{
		$permsql = " AND t.fid NOT IN ($unsearchforums)";
	}

	$query = $db->query("SELECT p.tid FROM ".TABLE_PREFIX."posts p, ".TABLE_PREFIX."threads t WHERE $wheresql $permsql");
	$results = $db->num_rows($query);

	if(!$results)
	{
		error($lang->error_nosearchresults);
	}
	if($mybb->input['showresults'] == "threads")
	{
		$showposts = 1;
	}
	else
	{
		$showposts = 2;
	}
	$wheresql = addslashes($wheresql);
	$searcharray = array(
		"sid" => "NULL",
		"uid" => $mybb->user['uid'],
		"dateline" => time(),
		"ipaddress" => $ipaddress,
		"wheresql" => $wheresql,
		"lookin" => $lookin,
		"showposts" => $showposts,
		"limitto" => intval($mybb->input['numrecs'])
		);

	$db->insert_query(TABLE_PREFIX."searchlog", $searcharray);
	$sid = $db->insert_id();
	eval("\$redirect = \"".$templates->get("redirect_searchresults")."\";");
	redirect("search.php?action=results&sid=$sid&sortby=".$mybb->input['sortby']."&order=".$mybb->input['sortordr'], $lang->redirect_searchresults);
}
else
{
	$srchlist = makesearchforums("", "$fid");
	eval("\$search = \"".$templates->get("search")."\";");
	outputpage($search);
}

function makesearchforums($pid="0", $selitem="", $addselect="1", $depth="")
{
	global $db, $forumcache, $permissioncache, $settings, $mybb, $mybbuser, $selecteddone, $forumlist, $forumlistbits, $theme, $templates, $mybbgroup, $lang, $forumpass;
	$pid = intval($pid);
	if(!is_array($forumcache))
	{
		// Get Forums
		$query = $db->query("SELECT f.* FROM ".TABLE_PREFIX."forums f ORDER BY f.pid, f.disporder");
		while($forum = $db->fetch_array($query))
		{
			$forumcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
	}
	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}
	if(is_array($forumcache[$pid]))
	{
		while(list($key, $main) = each($forumcache[$pid]))
		{
			while(list($key, $forum) = each($main))
			{
				if(!$permissioncache[$forum['fid']])
				{
					$perms = $mybb->usergroup;
				}
				else
				{
					$perms = $permissioncache[$forum['fid']];
				}
				if(($perms['canview'] != "no" || $mybb->settings['hideprivateforums'] == "no") && $perms['cansearch'] != "no")
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
					if($forumcache[$forum['fid']])
					{
						$newdepth = $depth."&nbsp;&nbsp;&nbsp;&nbsp;";
						$forumlistbits .= makesearchforums($forum['fid'], $selitem, 0, $newdepth);
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
?>