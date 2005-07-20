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

require "./global.php";

// Load language packs for this section
global $lang;
$lang->load("modlogs");

addacpnav($lang->nav_mod_logs, "modlogs.php");

switch($mybb->input['action'])
{
	case "view":
		addacpnav($lang->nav_search_results);
		break;
}

logadmin();

if($mybb->input['action'] == "do_prune") {
	$time = time();
	$timecut = $time-($days*60*60*24);
	$thequery = "";
	if($timecut) {
		$thequery .= "dateline<'$timecut'";
		if($fromscript || $fromadmin) {
			$thequery .= " AND ";
		}
	}
	if($frommod) {
		$thequery .= " uid='$frommod'";
	}
	if($thequery) {
		$thequery = "WHERE $thequery";
	}
	$db->query("DELETE FROM ".TABLE_PREFIX."moderatorlog $thequery");
	cpredirect("modlogs.php", $lang->modlog_pruned);
}
if($mybb->input['action'] == "view") {
	if(!$perpage) {
		$perpage = 20;
	}
	$squery = "";
	if($frommod) {
		$squery .= "l.uid='$frommod'";
	}
	if($squery) {
		$squery = "WHERE $squery";
	}
	if($orderby == "nameasc") {
		$order = "u.username ASC";
	}
	else {
		$order = "l.dateline DESC";
	}
	$query = $db->query("SELECT COUNT(dateline) FROM ".TABLE_PREFIX."moderatorlog l LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid) $squery");
	$rescount = $db->result($query, 0);
	if(!$rescount) {
		cpmessage($lang->no_results);
	}
	if(!$perpage) {
		$perpage = 15;
	}
	if($page) {
		$start = ($page-1) *$perpage;
	} else {
		$start = 0;
		$page = 1;
	}
	if($rescount > $perpage) {
		$pages = $rescount / $perpage;
		$pages = ceil($pages);
	} else {
		$pages = 1;
	}
	if($page != $pages) {
		$npage = $page+1;
		$nextpage = "<input type=\"button\" value=\"$lang->nextpage\" onClick=\"hopto('modlogs.php?action=view&perpage=$perpage&frommod=$frommod&orderby=$orderby&page=$npage')\">&nbsp;";
		$lastpage = "<input type=\"button\" value=\"$lang->lastpage\" onClick=\"hopto('modlogs.php?action=view&perpage=$perpage&frommod=$frommod&orderby=$orderby&page=$pages')\">&nbsp;";
	}
	if($page != 1) {
		$ppage = $page-1;
		$prevpage = "<input type=\"button\" value=\"$lang->prevpage\" onClick=\"hopto('modlogs.php?action=view&perpage=$perpage&frommod=$frommod&orderby=$orderby&page=$ppage')\">&nbsp;";
		$firstpage = "<input type=\"button\" value=\"$lang->firstpage\" onClick=\"hopto('modlogs.php?action=view&perpage=$perpage&frommod=$frommod&orderby=$orderby&page=1')\">&nbsp;";
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
	$query = $db->query("SELECT l.*, u.username, t.subject AS tsubject, f.name AS fname, p.subject AS psubject FROM ".TABLE_PREFIX."moderatorlog l LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid) LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid) LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=l.fid) LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=l.pid) $squery ORDER BY $order LIMIT $start, $perpage");
	while($logitem = $db->fetch_array($query)) {
		$logitem[dateline] = date("jS M Y, G:i", $logitem[dateline]);
		$bgcolor = getaltbg();
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" valign=\"top\"><a href=\"users.php?action=edit&uid=$logitem[uid]\">$logitem[username]</a></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" valign=\"top\">$logitem[dateline]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" valign=\"top\">$logitem[action]</td>";
		echo "<td class=\"$bgcolor\">";
		if($logitem[tsubject]) {
			echo "<b>$lang->thread</b> <a href=\"../showthread.php?tid=$logitem[tid]\" target=\"_blank\">$logitem[tsubject]</a><br>";
		}
		if($logitem[fname]) {
			echo "<b>$lang->forum</b> <a href=\"../forumdisplay.php?fid=$logitem[fid]\" target=\"_blank\">$logitem[fname]</a><br>";
		}
		if($logitem[psubject]) {
			echo "<b>$lang->post</b> <a href=\"../showthread.php?tid=$logitem[tid]&pid=$logitem[pid]#pid$logitem[pid]\">$logitem[psubject]</a>";
		}
		echo "</td>";
		echo "<td class=\"$bgcolor\" align=\"center\" valign=\"top\">$logitem[ipaddress]</td>";
		echo "</tr>\n";
	}
	if($prevpage || $nextpage) {
		tablesubheader("<center>$firstpage$prevpage$nextpage$lastpage</center>", "", 6);
	}
	endtable();
	cpfooter();
		
}
if($mybb->input['action'] == "") {
	$query = $db->query("SELECT DISTINCT l.uid, u.username FROM ".TABLE_PREFIX."moderatorlog l LEFT JOIN ".TABLE_PREFIX."users u ON (l.uid=u.uid) ORDER BY u.username ASC");
	while($user = $db->fetch_array($query)) {
		$uoptions .= "<option value=\"$user[uid]\">$user[username]</option>\n";
	}

	cpheader();
	startform("modlogs.php", "", "view");
	starttable();
	tableheader($lang->view_modlogs);
	makeinputcode($lang->entries_per_page, "perpage", 20, 4);
	makelabelcode($lang->entries_from_mod, "<select name=\"frommod\">\n<option value=\"\">$lang->all_mods</option>\n<option value=\"\">----------</option>\n$uoptions</select>");
	makelabelcode($lang->order_by, "<select name=\"orderby\"><option value=\"datedesc\">$lang->order_date_desc</option>\n<option value=\"nameasc\">$lang->order_name_asc</option>");
	endtable();
	endform($lang->search_log, $lang->reset_button);
	echo "<br>\n";
	echo "<br>\n";
	startform("modlogs.php", "", "do_prune");
	starttable();
	tableheader($lang->prune_modlogs);
	makelabelcode($lang->entries_from_mod, "<select name=\"frommod\">\n<option value=\"\">$lang->all_mods</option>\n<option value=\"\">----------</option>\n$uoptions</select>");
	makeinputcode($lang->prune_days, "days", 30, 4);
	endtable();
	endform($lang->prune_log, $lang->reset_button);

}
?>