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

require_once "./global.php";

// Load language packs for this section
global $lang;
$lang->load("badwords");

// A temporary permission!
checkadminpermissions("caneditsmilies");
logadmin();

addacpnav($lang->nav_badwords, "badwords.php?".SID);
switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_badword);
		break;
	case "edit":
		addacpnav($lang->nav_edit_badword);
		break;
	case "delete":
		addacpnav($lang->nav_delete_badword);
		break;
}

if($mybb->input['action'] == "do_add")
{
	$sqlarray = array(
		"badword" => $db->escape_string($mybb->input['badword']),
		"replacement" => $db->escape_string($mybb->input['replacement']),
	);
	$plugins->run_hooks("admin_badwords_do_add");
	$db->insert_query("badwords", $sqlarray);
	$cache->update_badwords();
	cpredirect("badwords.php?".SID, $lang->badword_added);
}

if($mybb->input['action'] == "do_edit")
{
	$sqlarray = array(
		"bid" => intval($mybb->input['bid']),
		"badword" => $db->escape_string($mybb->input['badword']),
		"replacement" => $db->escape_string($mybb->input['replacement']),
	);
	$plugins->run_hooks("admin_badwords_do_edit");
	$db->update_query("badwords", $sqlarray, "bid='".$sqlarray['bid']."'");
	$cache->update_badwords();
	cpredirect("badwords.php?".SID, $lang->badword_edited);
}

if($mybb->input['action'] == "edit")
{
	$bid = intval($mybb->input['bid']);
	if($mybb->input['delete'])
	{
		$plugins->run_hooks("admin_badwords_delete");
		$db->delete_query("badwords", "bid='$bid'");
		cpredirect("badwords.php?".SID, $lang->badword_deleted);
		$cache->update_badwords();
		exit;
	}
	$query = $db->simple_select("badwords", "*", "bid='$bid'");
	$badword = $db->fetch_array($query);
	$plugins->run_hooks("admin_badwords_edit");
	cpheader();
	startform("badwords.php", "", "do_edit");
	makehiddencode("bid", $bid);
	starttable();
	tableheader($lang->modify_badword);
	makeinputcode($lang->badword_label, "badword", $badword['badword']);
	makeinputcode($lang->replacement_label, "replacement", $badword['replacement']);
	endtable();
	endform($lang->update_badword, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_badwords_add");
	cpheader();
	startform("badwords.php", "", "do_add");
	makehiddencode("bid", $bid);
	starttable();
	tableheader($lang->add_badword);
	makeinputcode($lang->badword_label, "badword");
	makeinputcode($lang->replacement_label, "replacement");
	endtable();
	endform($lang->insert_badword, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "modify" || $mybb->input['action'] == "")
{
	$plugins->run_hooks("admin_badwords_modify");
	cpheader();
	$hopto[] = "<input type=\"button\" value=\"$lang->add_badword_filter\" onclick=\"hopto('badwords.php?".SID."&amp;action=add');\" class=\"hoptobutton\" />";
	makehoptolinks($hopto);
	starttable();
	tableheader($lang->badwords, "", 4);
	echo "<tr>\n";
	echo "<td class=\"subheader\">$lang->badword_title</td>\n";
	echo "<td class=\"subheader\">$lang->replacement_title</td>\n";
	echo "<td class=\"subheader\" colspan=\"2\" align=\"center\">$lang->options</td>\n";
	echo "</tr>\n";
	$options = array(
		"order_by" => "badword",
		"order_dir" => "ASC"
	);
	$query = $db->simple_select("badwords", "*", "", $options);
	while($badword = $db->fetch_array($query))
	{
		$bgcolor = getaltbg();
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\" width=\"42%\">".$badword['badword']."</td>\n";
		echo "<td class=\"$bgcolor\" width=\"42%\">".$badword['replacement']."</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\">";
		startform("badwords.php", "", "edit");
		makehiddencode("bid", $badword['bid']);
    echo "<input type=\"submit\" name=\"edit\" value=\"$lang->edit\" class=\"submitbutton\" />";
    endform();
    echo "</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\">";
		startform("badwords.php", "", "edit");
		makehiddencode("bid", $badword['bid']);
    echo "<input type=\"submit\" name=\"delete\" value=\"$lang->delete\" class=\"submitbutton\" />";
    endform();
    echo "</td>\n";		
    echo "</tr>\n";
		$done = 1;
	}
	if(!$done)
	{
		makelabelcode("<div align=\"center\">$lang->no_badwords</div>", "", 4);
	}
	endtable();
	cpfooter();
}
?>
