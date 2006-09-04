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

define("IN_MYBB", 1);

require_once "./global.php";

// Load language packs for this section
global $lang;
$lang->load("maintenance");

checkadminpermissions("canrunmaint");
logadmin();

$plugins->run_hooks("admin_maintenance_start");

switch($mybb->input['action'])
{
	case "cache":
		addacpnav($lang->nav_cache_manager, "maintenance.php?".SID."&action=cache");
		break;
	case "do_cache":
		if($view)
		{
			addacpnav($lang->cache_manager, "maintenance.php?".SID."&action=cache");
			addacpnav($lang->nav_view_cache);
		}
		break;
	case "do_rebuildforums":
	case "rebuildforums":
		addacpnav($lang->rebuild_forum_counters);
		break;
	case "rebuildstats":
		addacpnav($lang->rebuildstats);
		break;
}

if($mybb->input['action'] == "do_cache")
{
	$cacheitem = $mybb->input['cacheitem'];
	$plugins->run_hooks("admin_maintenance_do_cache");
	if($mybb->input['view'])
	{
		cpheader();
		starttable();
		$query = $db->simple_select(TABLE_PREFIX."datacache", "*", "title='".$db->escape_string($cacheitem)."'");
		$cacheitem = $db->fetch_array($query);
		$cachecontents = unserialize($cacheitem['cache']);
		if(empty($cachecontents))
		{
			$cachecontents = $lang->cache_empty;
		}
		ob_start();
		print_r($cachecontents);
		$data = htmlspecialchars_uni(ob_get_contents());
		ob_end_clean();
		makelabelcode("<pre>$data</pre>", "");
		endtable();
		cpfooter();
	}
	if($mybb->input['refresh'])
	{
		if(method_exists($cache, "update$cacheitem"))
		{
			$func = "update$cacheitem";
			$cache->$func();
			cpredirect("maintenance.php?".SID."&action=cache", $lang->cache_updated);
		}
		else
		{
			cpmessage($lang->nocache_update);
		}
	}
}

if($mybb->input['action'] == "cache")
{
	$plugins->run_hooks("admin_maintenance_cache");
	cpheader();
	starttable();
	tableheader($lang->cache_manager, "", "4");
	echo "<tr>\n";
	echo "<td class=\"subheader\">$lang->name</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->size</td>\n";
	echo "<td class=\"subheader\" align=\"center\" colspan=\"2\">$lang->options</td>\n";
	echo "</tr>\n";
	$query = $db->simple_select(TABLE_PREFIX."datacache", "title,cache");
	while($cacheitem = $db->fetch_array($query))
	{
		$size = get_friendly_size(my_strlen($cacheitem['cache']));
		$bgcolor = getaltbg();
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\" width=\"50%\">$cacheitem[title]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"15%\">$size</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\">";
		startform("maintenance.php", "", "do_cache");
		makehiddencode("cacheitem", $cacheitem['title']);
		echo "<input type=\"submit\" name=\"view\" value=\"$lang->view_contents\" class=\"submitbutton\" />";
		endform();
		echo "</td>";
		if(method_exists($cache, "update".$cacheitem['title']))
		{
			echo "<td class=\"$bgcolor\" align=\"center\">";
			startform("maintenance.php", "", "do_cache");
			makehiddencode("cacheitem", $cacheitem['title']);
			echo "<input type=\"submit\" name=\"refresh\" value=\"$lang->refresh_cache\" class=\"submitbutton\" />";
			endform();
			echo "</td>";
		}
		else
		{
			echo "<td class=\"$bgcolor\">&nbsp;</td>";
		}		
		echo "</tr>\n";		
	}
	endtable();
	cpfooter();
}

if($mybb->input['action'] == "do_rebuildstats")
{
	$plugins->run_hooks("admin_maintenance_do_rebuildstats");
	$cache->updatestats();
	cpredirect("maintenance.php?".SID."&action=rebuild", $lang->stats_rebuilt);
}

if($mybb->input['action'] == "do_rebuildforums")
{
	$plugins->run_hooks("admin_maintenance_do_rebuildforums");

	$query = $db->simple_select(TABLE_PREFIX."forums", "COUNT(*) as num_forums");
	$num_forums = $db->fetch_field($query, 'num_forums');
	
	if(!isset($mybb->input['page']) || intval($mybb->input['page']) < 1)
	{
		$mybb->input['page'] = 1;
	}
	$page = intval($mybb->input['page']);
	if(!isset($mybb->input['perpage']) || intval($mybb->input['perpage']) < 1)
	{
		$mybb->input['perpage'] = 50;
	}
	$per_page = intval($mybb->input['perpage']);
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select(TABLE_PREFIX."forums", "fid", '', array('order_by' => 'fid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($forum = $db->fetch_array($query))
	{
		$update['parentlist'] = makeparentlist($forum['fid']);
		$db->update_query(TABLE_PREFIX."forums", $update, "fid='{$forum['fid']}'");
		update_forum_count($forum['fid']);
	}

	if($end >= $num_forums)
	{
		cpredirect("maintenance.php?".SID."&action=rebuild", $lang->forums_rebuilt);
	}
	else
	{
		cpheader();
		startform("maintenance.php", "" , "do_rebuildforums");
		starttable();
		tableheader($lang->rebuild_forum_counters);
		makelabelcode($lang->click_next_continue, '', 2);
		makehiddencode('page', ++$page);
		makehiddencode('perpage', $per_page);
		endtable();
		endform($lang->proceed);
		cpfooter();
	}
}

if($mybb->input['action'] == "do_rebuildthreads")
{
	$plugins->run_hooks("admin_maintenance_do_rebuildthreads");

	$query = $db->simple_select(TABLE_PREFIX."threads", "COUNT(*) as num_threads");
	$num_threads = $db->fetch_field($query, 'num_threads');
	
	if(!isset($mybb->input['page']) || intval($mybb->input['page']) < 1)
	{
		$mybb->input['page'] = 1;
	}
	$page = intval($mybb->input['page']);
	if(!isset($mybb->input['perpage']) || intval($mybb->input['perpage']) < 1)
	{
		$mybb->input['perpage'] = 50;
	}
	$per_page = intval($mybb->input['perpage']);
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select(TABLE_PREFIX."threads", "tid", '', array('order_by' => 'tid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($thread = $db->fetch_array($query))
	{
		update_thread_count($thread['tid']);
	}

	if($end >= $num_threads)
	{
		cpredirect("maintenance.php?".SID."&action=rebuild", $lang->threads_rebuilt);
	}
	else
	{
		cpheader();
		startform("maintenance.php", "" , "do_rebuildthreads");
		starttable();
		tableheader($lang->rebuild_thread_counters);
		makelabelcode($lang->click_next_continue, '', 2);
		makehiddencode('page', ++$page);
		makehiddencode('perpage', $per_page);
		endtable();
		endform($lang->proceed);
		cpfooter();
	}
}

if($mybb->input['action'] == "do_recountpostcounts")
{
	$plugins->run_hooks("admin_maintenance_do_recountpostcounts");

	$query = $db->simple_select(TABLE_PREFIX."users", "COUNT(uid) as num_users");
	$num_users = $db->fetch_field($query, 'num_users');
	
	if(!isset($mybb->input['page']) || intval($mybb->input['page']) < 1)
	{
		$mybb->input['page'] = 1;
	}
	$page = intval($mybb->input['page']);
	if(!isset($mybb->input['perpage']) || intval($mybb->input['perpage']) < 1)
	{
		$mybb->input['perpage'] = 50;
	}
	$per_page = intval($mybb->input['perpage']);
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	$query = $db->simple_select(TABLE_PREFIX."users", "uid", '', array('order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($user = $db->fetch_array($query))
	{
		$query2 = $db->simple_select(TABLE_PREFIX."posts", "COUNT(pid) AS post_count", "uid='{$user['uid']}' AND visible>0");
		$num_posts = $db->fetch_field($query2, "post_count");
		$db->update_query(TABLE_PREFIX."users", array("postnum" => intval($num_posts)), "uid='{$user['uid']}'");
	}

	if($end >= $num_users)
	{
		cpredirect("maintenance.php?".SID."&action=rebuild", $lang->user_post_counts_rebuilt);
	}
	else
	{
		cpheader();
		startform("maintenance.php", "" , "do_recountpostcounts");
		starttable();
		tableheader($lang->recount_user_post_counts);
		makelabelcode($lang->click_next_continue, '', 2);
		makehiddencode('page', ++$page);
		makehiddencode('perpage', $per_page);
		endtable();
		endform($lang->proceed);
		cpfooter();
	}
}

if($mybb->input['action'] == "do_rebuildthumbnails")
{
	$plugins->run_hooks("admin_maintenance_do_rebuild_thumbnails");

	$query = $db->simple_select(TABLE_PREFIX."attachments", "COUNT(aid) as num_attachments");
	$num_attachments = $db->fetch_field($query, 'num_attachments');
	
	if(!isset($mybb->input['page']) || intval($mybb->input['page']) < 1)
	{
		$mybb->input['page'] = 1;
	}
	$page = intval($mybb->input['page']);
	if(!isset($mybb->input['perpage']) || intval($mybb->input['perpage']) < 1)
	{
		$mybb->input['perpage'] = 50;
	}
	$per_page = intval($mybb->input['perpage']);
	$start = ($page-1) * $per_page;
	$end = $start + $per_page;

	require_once MYBB_ROOT."/inc/functions_image.php";
	
	$query = $db->simple_select(TABLE_PREFIX."attachments", "*", '', array('order_by' => 'aid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page));
	while($attachment = $db->fetch_array($query))
	{
		$ext = strtolower(my_substr(strrchr($attachment['filename'], "."), 1));
		if($ext == "gif" || $ext == "png" || $ext == "jpg" || $ext == "jpeg" || $ext == "jpe")
		{
			$thumbname = str_replace(".attach", "_thumb.$ext", $attachment['attachname']);
			$thumbnail = generate_thumbnail("../uploads/".$attachment['attachname'], "../uploads", $thumbname, $mybb->settings['attachthumbh'], $mybb->settings['attachthumbw']);
			if($thumbnail['code'] == 4)
			{
				$thumbnail['filename'] = "SMALL";
			}
			$db->update_query(TABLE_PREFIX."attachments", array("thumbnail" => $thumbnail['filename']), "aid='{$attachment['aid']}'");
		}
	}

	if($end >= $num_attachments)
	{
		cpredirect("maintenance.php?".SID."&action=rebuild", $lang->thumbnails_rebuilt);
	}
	else
	{
		cpheader();
		startform("maintenance.php", "" , "do_rebuildthumbnails");
		starttable();
		tableheader($lang->rebuild_thumbnails);	
		makelabelcode($lang->click_next_continue, '', 2);
		makehiddencode('page', ++$page);
		makehiddencode('perpage', $per_page);
		endtable();
		endform($lang->proceed);
		cpfooter();
	}
}

if($mybb->input['action'] == "rebuild")
{
	$plugins->run_hooks("admin_maintenance_rebuild");
	cpheader();
	
	startform("maintenance.php", "" , "do_rebuildstats");
	starttable();
	tableheader($lang->rebuildstats);
	makelabelcode("<div align=\"center\">$lang->rebuildstats_notice</div>");
	endtable();
	endform($lang->proceed);
	
	startform("maintenance.php", "" , "do_rebuildforums");
	starttable();
	tableheader($lang->rebuild_forum_counters);
	makelabelcode("<div align=\"center\">{$lang->rebuild_forum_counters_note}</div>", '', 2);
	makeinputcode($lang->forums_per_page, 'perpage', 50);
	makehiddencode('page', 1);
	endtable();
	endform($lang->proceed);
	
	startform("maintenance.php", "" , "do_rebuildthreads");
	starttable();
	tableheader($lang->rebuild_thread_counters);
	makelabelcode("<div align=\"center\">{$lang->rebuild_thread_counters_note}</div>", '', 2);
	makeinputcode($lang->threads_per_page, 'perpage', 500);
	makehiddencode('page', 1);
	endtable();
	endform($lang->proceed);
	
	startform("maintenance.php", "" , "do_recountpostcounts");
	starttable();
	tableheader($lang->recount_user_post_counts);
	makelabelcode("<div align=\"center\">{$lang->recount_user_post_counts_note}</div>", '', 2);
	makeinputcode($lang->users_per_page, 'perpage', 500);
	makehiddencode('page', 1);
	endtable();
	endform($lang->proceed);
	
	startform("maintenance.php", "" , "do_rebuildthumbnails");
	starttable();
	tableheader($lang->rebuild_thumbnails);
	makelabelcode("<div align=\"center\">{$lang->rebuild_thumbnails_note}</div>", '', 2);
	makeinputcode($lang->thumbnails_per_page, 'perpage', 20);
	makehiddencode('page', 1);
	endtable();
	endform($lang->proceed);
	cpfooter();
}
?>