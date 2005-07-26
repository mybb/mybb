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
$lang->load("badwords");

// A temporary permission!
checkadminpermissions("caneditsmilies");
logadmin();

addacpnav($lang->nav_badwords, "badwords.php");
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
		"bid" => '',
		"badword" => addslashes($mybb->input['badword']),
		"replacement" => addslashes($mybb->input['replacement']),
		);
	$db->insert_query(TABLE_PREFIX."badwords", $sqlarray);
	$cache->updatebadwords();
	cpredirect("badwords.php", $lang->badword_added);
}

if($mybb->input['action'] == "do_edit")
{
	$sqlarray = array(
		"bid" => intval($mybb->input['bid']),
		"badword" => addslashes($mybb->input['badword']),
		"replacement" => addslashes($mybb->input['replacement']),
		);
	$db->update_query(TABLE_PREFIX."badwords", $sqlarray, "bid='".intval($mybb->input['bid'])."'");
	$cache->updatebadwords();
	cpredirect("badwords.php", $lang->badword_edited);
}

if($mybb->input['action'] == "edit")
{
	$bid = intval($mybb->input['bid']);
	if($delete)
	{
		$query = $db->query("DELETE FROM ".TABLE_PREFIX."badwords WHERE bid='$bid'");
		cpredirect("badwords.php", $lang->badword_deleted);
		$cache->updatebadwords();
		exit;
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."badwords WHERE bid='$bid'");
	$badword = $db->fetch_array($query);
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
	cpheader();
	$hopto[] = "<input type=\"button\" value=\"$lang->add_badword_filter\" onclick=\"hopto('badwords.php?action=add');\" class=\"hoptobutton\">";
	makehoptolinks($hopto);
	starttable();
	tableheader($lang->badwords, "", 4);
	echo "<tr>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->badword_title</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->replacement_title</td>\n";
	echo "<td class=\"subheader\" align=\"center\" colspan=\"2\">$lang->options</td>\n";
	echo "</tr>\n";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."badwords ORDER BY badword ASC");
	while($badword = $db->fetch_array($query))
	{
		$bgcolor = getaltbg();
		startform("badwords.php", "", "edit");
		makehiddencode("bid", $badword['bid']);
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\" width=\"42%\">".$badword['badword']."</td>\n";
		echo "<td class=\"$bgcolor\" width=\"42%\">".$badword['replacement']."</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"submit\" name=\"edit\" value=\"$lang->edit\" class=\"submitbutton\"></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"submit\" name=\"delete\" value=\"$lang->delete\" class=\"submitbutton\"></td>\n";
		echo "</tr>\n";
		endform();
		$done = 1;
	}
	if(!$done)
	{
		makelabelcode("<center>$lang->no_badwords</center>", "", 4);
	}
	endtable();
	cpfooter();
}
?>