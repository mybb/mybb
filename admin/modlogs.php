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
$lang->load("modlogs");

addacpnav($lang->nav_mod_logs, "modlogs.php?".SID);

switch($mybb->input['action'])
{
	case "view":
		addacpnav($lang->nav_search_results);
		break;
	default:
		break;
}

logadmin();

$plugins->run_hooks("admin_modlogs_start");

if($mybb->input['action'] == "do_prune") 
{
	$time = time();
	$timecut = $time-(intval($mybb->input['days'])*60*60*24);
	$frommod = intval($mybb->input['frommod']);
	$thequery = "";
	if($timecut)
	{
		$thequery .= "dateline<'$timecut'";
		if($frommod)
		{
			$thequery .= " AND ";
		}
	}
	if($frommod)
	{
		$thequery .= " uid='$frommod'";
	}
	$plugins->run_hooks("admin_modlogs_do_prune");
	$db->delete_query("moderatorlog", $thequery);
	cpredirect("modlogs.php?".SID, $lang->modlog_pruned);
}
if($mybb->input['action'] == "view")
{
	$perpage = intval($mybb->input['perpage']);
	$fromscript = $db->escape_string($mybb->input['fromscript']);
	$frommod = intval($mybb->input['frommod']);
	$orderby = $mybb->input['orderby'];
	$page = intval($mybb->input['page']);

	if(!$mybb->input['perpage'])
	{
		$perpage = 20;
	}
	$squery = "";
	if($mybb->input['frommod'])
	{
		$squery .= "l.uid='$frommod'";
	}
	if($orderby == "nameasc")
	{
		$order = "u.username";
		$orderdir = "ASC";
	}
	else
	{
		$order = "l.dateline";
		$orderdir = "DESC";
	}
	$query = $db->simple_select("moderatorlog l LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)", "COUNT(dateline) AS count", $squery);
	$rescount = $db->fetch_field($query, "count");
	if(!$rescount)
	{
		cpmessage($lang->no_results);
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
	if($rescount > $perpage)
	{
		$pages = $rescount / $perpage;
		$pages = ceil($pages);
	}
	else
	{
		$pages = 1;
	}
	$plugins->run_hooks("admin_modlogs_view");
	if($page != $pages)
	{
		$npage = $page+1;
		$nextpage = "<input type=\"button\" value=\"$lang->nextpage\" onClick=\"hopto('modlogs.php?".SID."&amp;action=view&amp;perpage=$perpage&amp;frommod=$frommod&amp;orderby=$orderby&amp;page=$npage')\" />&nbsp;";
		$lastpage = "<input type=\"button\" value=\"$lang->lastpage\" onClick=\"hopto('modlogs.php?".SID."&amp;action=view&amp;perpage=$perpage&amp;frommod=$frommod&amp;orderby=$orderby&amp;page=$pages')\" />&nbsp;";
	}
	if($page != 1)
	{
		$ppage = $page-1;
		$prevpage = "<input type=\"button\" value=\"$lang->prevpage\" onClick=\"hopto('modlogs.php?".SID."&amp;action=view&amp;perpage=$perpage&amp;frommod=$frommod&amp;orderby=$orderby&amp;page=$ppage')\" />&nbsp;";
		$firstpage = "<input type=\"button\" value=\"$lang->firstpage\" onClick=\"hopto('modlogs.php?".SID."&amp;action=view&amp;perpage=$perpage&amp;frommod=$frommod&amp;orderby=$orderby&amp;page=1')\" />&nbsp;";
	}
	cpheader();
	starttable();
	$lang->modlogs_results = sprintf($lang->modlogs_results, $page, $pages, $rescount);
	tableheader($lang->modlogs_results, "", 5);
	echo "<tr>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->username</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->date</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->action</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->information</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->ipaddress</td>\n";
	echo "</tr>\n";
	$options = array(
		"order_by" => $order,
		"order_dir" => $orderdir,
		"limit_start" => $start,
		"limit" => $perpage
	);
	$query = $db->simple_select("moderatorlog l LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid) LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid) LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=l.fid) LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=l.pid)", "l.*, u.username, t.subject AS tsubject, f.name AS fname, p.subject AS psubject", $squery, $options);
	while($logitem = $db->fetch_array($query))
	{
		$logitem['dateline'] = date("jS M Y, G:i", $logitem['dateline']);
		$bgcolor = getaltbg();
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" valign=\"top\"><a href=\"users.php?".SID."&amp;action=edit&amp;uid=$logitem[uid]\">$logitem[username]</a></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" valign=\"top\">$logitem[dateline]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" valign=\"top\">$logitem[action]</td>";
		echo "<td class=\"$bgcolor\">";
		if($logitem['tsubject'])
		{
			echo "<b>$lang->thread</b> <a href=\"../".get_thread_link($logitem['tid'])."\" target=\"_blank\">".htmlspecialchars_uni($logitem['tsubject'])."</a><br />";
		}
		if($logitem['fname'])
		{
			echo "<b>$lang->forum</b> <a href=\"../".get_forum_link($logitem['fid'])."\" target=\"_blank\">".htmlspecialchars_uni($logitem['fname'])."</a><br />";
		}
		if($logitem['psubject'])
		{
			echo "<b>$lang->post</b> <a href=\"../".get_post_link($logitem['pid'])."#pid$logitem[pid]\">".htmlspecialchars_uni($logitem['psubject'])."</a>";
		}
		echo "</td>";
		echo "<td class=\"$bgcolor\" align=\"center\" valign=\"top\">$logitem[ipaddress]</td>";
		echo "</tr>\n";
	}
	if($prevpage || $nextpage)
	{
		tablesubheader("<div align=\"center\">$firstpage$prevpage$nextpage$lastpage</div>", "", 6);
	}
	endtable();
	cpfooter();
		
}
if($mybb->input['action'] == "")
{
	$options = array(
		"order_by" => "u.username",
		"order_dir" => "ASC"
	);
	$query = $db->simple_select("moderatorlog l LEFT JOIN ".TABLE_PREFIX."users u ON (l.uid=u.uid)", "DISTINCT l.uid, u.username", "", $options);
	while($user = $db->fetch_array($query))
	{
		$uoptions .= "<option value=\"$user[uid]\">$user[username]</option>\n";
	}
	
	$plugins->run_hooks("admin_modlogs_view");

	cpheader();
	startform("modlogs.php", "", "view");
	starttable();
	tableheader($lang->view_modlogs);
	makeinputcode($lang->entries_per_page, "perpage", 20, 4);
	makelabelcode($lang->entries_from_mod, "<select name=\"frommod\">\n<option value=\"\">$lang->all_mods</option>\n<option value=\"\">----------</option>\n$uoptions</select>");
	makelabelcode($lang->order_by, "<select name=\"orderby\"><option value=\"datedesc\">$lang->order_date_desc</option>\n<option value=\"nameasc\">$lang->order_name_asc</option></select>");
	endtable();
	endform($lang->search_log, $lang->reset_button);
	echo "<br />\n";
	echo "<br />\n";
	startform("modlogs.php", "", "do_prune");
	starttable();
	tableheader($lang->prune_modlogs);
	makelabelcode($lang->entries_from_mod, "<select name=\"frommod\">\n<option value=\"\">$lang->all_mods</option>\n<option value=\"\">----------</option>\n$uoptions</select>");
	makeinputcode($lang->prune_days, "days", 30, 4);
	endtable();
	endform($lang->prune_log, $lang->reset_button);
}
?>