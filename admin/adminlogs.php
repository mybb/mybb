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

require_once "./global.php"

// Load language packs for this section
global $lang;
$lang->load("adminlogs");

logadmin();

addacpnav($lang->nav_admin_logs, "adminlogs.php?".SID);

switch($mybb->input['action'])
{
	case "view":
		addacpnav($lang->nav_search_results);
		break;
}

$plugins->run_hooks("admin_adminlogs_start");

if($mybb->input['action'] == "do_prune")
{
	$time = time();
	$days = intval($mybb->input['days']);
	$fromscript = $db->escape_string($mybb->input['fromscript']);
	$fromadmin = intval($mybb->input['fromadmin']);

	$timecut = $time-($days*60*60*24);
	$thequery = "";
	if($timecut)
	{
		$thequery .= "dateline<$timecut";
		if($fromscript || $fromadmin)
		{
			$thequery .= " AND ";
		}
	}
	if($mybb->input['fromscript'])
	{
		$thequery .= "scriptname='$fromscript'";
		if($fromadmin)
		{
			$thequery .= " AND ";
		}
	}
	if($mybb->input['fromadmin'])
	{
		$thequery .= "uid='$fromadmin'";
	}

	$db->delete_query(TABLE_PREFIX."adminlog", $thequery);
	$plugins->run_hooks("admin_adminlogs_do_prune");
	cpredirect("adminlogs.php?".SID, $lang->log_pruned);
}
else if($mybb->input['action'] == "view")
{
	
	$plugins->run_hooks("admin_adminlogs_view");
		
	$perpage = intval($mybb->input['perpage']);
	$fromscript = $db->escape_string($mybb->input['fromscript']);
	$fromadmin = intval($mybb->input['fromadmin']);
	$orderby = $mybb->input['orderby'];
	$page = $mybb->input['page'];

	if(!$perpage)
	{
		$perpage = 20;
	}
	$squery = "";
	if($fromscript)
	{
		$squery .= "scriptname='$fromscript'";
		if($fromadmin)
		{
			$squery .= " AND ";
		}
	}
	if($fromadmin)
	{
		$squery .= "l.uid='$fromadmin'";
	}
	if($squery)
	{
		$squery = "WHERE $squery";
	}
	if($orderby == "nameasc")
	{
		$order = "u.username ASC";
	}
	elseif($orderby == "scriptasc")
	{
		$order = "l.scriptname ASC";
	}
	else
	{
		$order = "l.dateline DESC";
	}

	$query = $db->query("
		SELECT COUNT(dateline) AS count 
		FROM ".TABLE_PREFIX."adminlog l 
		LEFT JOIN ".TABLE_PREFIX."users u 
		ON (u.uid=l.uid) ".
		$squery
	);
	$rescount = $db->fetch_field($query, "count");
	if(!$rescount)
	{
		cpmessage($lang->no_results);
	}
	if(!$perpage) 
	{
		$perpage = 15;
	}
	if($page)
	{
		$start = ($page-1) *$perpage;
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
	if($page != $pages)
	{
		$npage = $page+1;
		$nextpage = "<input type=\"button\" value=\"$lang->nextpage\" onClick=\"hopto('adminlogs.php?".SID."&amp;action=view&amp;perpage=$perpage&amp;fromscript=$fromscript&amp;fromadmin=$fromadmin&amp;orderby=$orderby&amp;page=$npage')\" />&nbsp;";
		$lastpage = "<input type=\"button\" value=\"$lang->lastpage\" onClick=\"hopto('adminlogs.php?".SID."&amp;action=view&amp;perpage=$perpage&amp;fromscript=$fromscript&amp;fromadmin=$fromadmin&amp;orderby=$orderby&amp;page=$pages')\" />&nbsp;";
	}
	if($page != 1)
	{
		$ppage = $page-1;
		$prevpage = "<input type=\"button\" value=\"$lang->prevpage\" onClick=\"hopto('adminlogs.php?".SID."&amp;action=view&amp;perpage=$perpage&amp;fromscript=$fromscript&amp;fromadmin=$fromadmin&amp;orderby=$orderby&amp;page=$ppage')\" />&nbsp;";
		$firstpage = "<input type=\"button\" value=\"$lang->firstpage\" onClick=\"hopto('adminlogs.php?".SID."&amp;action=view&amp;perpage=$perpage&amp;fromscript=$fromscript&amp;fromadmin=$fromadmin&amp;orderby=$orderby&amp;page=1')\" />&nbsp;";
	}
	
	$lang->log_results_header = sprintf($lang->log_results_header, $page, $pages, $rescount);

	cpheader();
	starttable();
	tableheader($lang->log_results_header, "", 6);
	echo "<tr>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->username</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->date</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->scriptname</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->action</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->queryinfo</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->ipaddress</td>\n";
	echo "</tr>\n";
	$query = $db->query("
		SELECT l.*, u.username 
		FROM ".TABLE_PREFIX."adminlog l 
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid) 
		$squery 
		ORDER BY $order 
		LIMIT $start, $perpage
	");
	while($logitem = $db->fetch_array($query))
	{
		$logitem['dateline'] = date("jS M Y, G:i", $logitem['dateline']);
		$bgcolor = getaltbg();
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\" align=\"center\"><a href=\"users.php?".SID."&amp;action=edit&amp;uid=$logitem[uid]\">$logitem[username]</a></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\">$logitem[dateline]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\">$logitem[scriptname]</td>";
		echo "<td class=\"$bgcolor\" align=\"center\">$logitem[action]</td>";
		echo "<td class=\"$bgcolor\" align=\"center\">$logitem[querystring]</td>";
		echo "<td class=\"$bgcolor\" align=\"center\">$logitem[ipaddress]</td>";
		echo "</tr>\n";
	}
	if($prevpage || $nextpage)
	{
		tablesubheader("<div align=\"center\">$firstpage$prevpage$nextpage$lastpage</div>", "", 6);
	}
	endtable();
	cpfooter();
}
else
{
	$plugins->run_hooks("admin_adminlogs_search");
		
	$query = $db->query("
		SELECT DISTINCT scriptname 
		FROM ".TABLE_PREFIX."adminlog 
		ORDER BY scriptname ASC
	");
	while($script = $db->fetch_array($query))
	{
		$soptions .= "<option value=\"$script[scriptname]\">$script[scriptname]</option>\n";
	}
	$query = $db->query("
		SELECT DISTINCT l.uid, u.username 
		FROM ".TABLE_PREFIX."adminlog l 
		LEFT JOIN ".TABLE_PREFIX."users u 
		ON (l.uid=u.uid) 
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$uoptions .= "<option value=\"$user[uid]\">$user[username]</option>\n";
	}

	cpheader();
	startform("adminlogs.php", "", "view");
	starttable();
	tableheader($lang->view_admin_logs);
	makeinputcode($lang->entries_pp, "perpage", 20, 4);
	makelabelcode($lang->entries_script, "<select name=\"fromscript\">\n<option value=\"\">$lang->all_scripts</option>\n<option value=\"\">----------</option>\n$soptions</select>");
	makelabelcode($lang->entries_admin, "<select name=\"fromadmin\">\n<option value=\"\">$lang->all_admins</option>\n<option value=\"\">----------</option>\n$uoptions</select>");
	makelabelcode($lang->order_by, "<select name=\"orderby\"><option value=\"datedesc\">$lang->date_desc</option>\n<option value=\"nameasc\">$lang->username_asc</option><option value=\"scriptasc\">$lang->script_asc</option>\n</select>");
	endtable();
	endform($lang->view_log, $lang->reset_button);
	echo "<br />\n";
	echo "<br />\n";
	startform("adminlogs.php", "", "do_prune");
	starttable();
	tableheader($lang->prune_admin_logs);
	makelabelcode($lang->entries_script, "<select name=\"fromscript\">\n<option value=\"\">$lang->all_scripts</option>\n<option value=\"\">----------</option>\n$soptions</select>");
	makelabelcode($lang->entries_admin, "<select name=\"fromadmin\">\n<option value=\"\">$lang->all_admins</option>\n<option value=\"\">----------</option>\n$uoptions</select>");
	makeinputcode($lang->entries_older, "days", 30, 4);
	endtable();
	endform($lang->prune_log, $lang->reset_button);
}
?>