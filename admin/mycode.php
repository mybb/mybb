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

require "./global.php";

// Load language packs for this section
global $lang;
$lang->load("mycode");

//checkadminpermissions("caneditmycode");
//logadmin();

addacpnav($lang->nav_mycode, "mycode.php?".SID."&action=modify");

switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_mycode);
		break;
	case "edit":
		addacpnav($lang->nav_edit_mycode);
		break;
	case "delete":
		addacpnav($lang->nav_delete_mycode);
		break;
}

$plugins->run_hooks("admin_mycode_start");

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_mycode_add");
	cpheader();
	startform("mycode.php", "", "do_add");
	starttable();
	tableheader($lang->add_mycode);
	makeinputcode($lang->mycode_title_label, "title");
	maketextareacode($lang->mycode_description_label, "description");
	maketextareacode($lang->mycode_regex_label, "regex", "", "4", "80");
	maketextareacode($lang->mycode_replacement_label, "replacement", "", "4", "80");
	makeyesnocode($lang->mycode_active_label, "active", "yes");
	endtable();
	endform($lang->insert_mycode);
	cpfooter();
}

if($mybb->input['action'] == "do_add")
{
	if(empty($mybb->input['title']) || empty($mybb->input['regex']) || empty($mybb->input['replacement']))
	{
		cperror($lang->error_fill_form);
	}
	$newmycode = array(
		"title" => $db->escape_string($mybb->input['title']),
		"description" => $db->escape_string($mybb->input['description']),
		"regex" => $db->escape_string($mybb->input['regex']),
		"replacement" => $db->escape_string($mybb->input['replacement']),
		"active" => $db->escape_string($mybb->input['active'])
		);
	$plugins->run_hooks("admin_mycode_do_add");
	$db->insert_query(TABLE_PREFIX."mycode", $newmycode);

	$cache->updatemycode();

	cpredirect("mycode.php?".SID, $lang->mycode_added);
}

if($mybb->input['action'] == "edit")
{
	if($mybb->input['delete'])
	{
		header("Location: mycode.php?".SID."&action=delete&cid=".intval($mybb->input['cid']));
		exit;
	}

	$query = $db->simple_select("mycode", "*", "cid='".intval($mybb->input['cid'])."'");
	$mycode = $db->fetch_array($query);
	$plugins->run_hooks("admin_mycode_edit");
	cpheader();
	startform("mycode.php", "", "do_edit");
	makehiddencode("cid", $mycode['cid']);
	starttable();
	tableheader($lang->edit_mycode);
	makeinputcode($lang->mycode_title_label, "title", $mycode['title']);
	maketextareacode($lang->mycode_description_label, "description", $mycode['description']);
	maketextareacode($lang->mycode_regex_label, "regex", $mycode['regex'], "4", "80");
	maketextareacode($lang->mycode_replacement_label, "replacement", $mycode['replacement'], "4", "80");
	makeyesnocode($lang->mycode_active_label, "active", $mycode['active']);
	endtable();
	endform($lang->update_mycode);
	cpfooter();
}

if($mybb->input['action'] == "do_edit")
{
	if(empty($mybb->input['title']) || empty($mybb->input['regex']) || empty($mybb->input['replacement']))
	{
		cperror($lang->error_fill_form);
	}
	$mycode = array(
		"title" => $db->escape_string($mybb->input['title']),
		"description" => $db->escape_string($mybb->input['description']),
		"regex" => $db->escape_string($mybb->input['regex']),
		"replacement" => $db->escape_string($mybb->input['replacement']),
		"active" => $db->escape_string($mybb->input['active'])
		);
	
	$plugins->run_hooks("admin_mycode_do_edit");

	$db->update_query(TABLE_PREFIX."mycode", $mycode, "cid='".intval($mybb->input['cid'])."'");

	$cache->updatemycode();

	cpredirect("mycode.php?".SID, $lang->mycode_updated);
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("mycode", "*", "cid='".intval($mybb->input['cid'])."'");
	$mycode = $db->fetch_array($query);
	if(!$mycode['cid'])
	{
		cperror($lang->invalid_mycode);
	}
	$plugins->run_hooks("admin_mycode_delete");
	cpheader();
	startform("mycode.php", "", "do_delete");
	makehiddencode("cid", $mycode['cid']);
	starttable();
	tableheader($lang->delete_mycode, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<div align=\"center\">$lang->delete_confirm<br /><br />$yes$no</div>", "");
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{
		$plugins->run_hooks("admin_mycode_do_delete");
		$db->delete_query(TABLE_PREFIX."mycode", "cid='".intval($mybb->input['cid'])."'");
		$cache->updatemycode();
		cpredirect("mycode.php?".SID, $lang->mycode_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "modify" || !$mybb->input['action'])
{
	$plugins->run_hooks("admin_mycode_modify");
	cpheader();
	$hopto[] = "<input type=\"button\" value=\"$lang->add_mycode\" onclick=\"hopto('mycode.php?".SID."&action=add');\" class=\"hoptobutton\">";
	makehoptolinks($hopto);
	starttable();
	tableheader($lang->custom_mycode, "", 4);
	echo "<tr>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->mycode_title</td>\n";
	echo "<td class=\"subheader\" align=\"center\" colspan=\"2\">$lang->options</td>\n";
	echo "</tr>\n";
	$options = array(
		"order_by" => "title",
		"order_dir" => "ASC"
	);
	$query = $db->simple_select("mycode", "*", "", $options);
	while($mycode = $db->fetch_array($query))
	{
		$bgcolor = getaltbg();
		startform("mycode.php", "", "edit");
		makehiddencode("cid", $mycode['cid']);
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\" width=\"42%\">".$mycode['title']."<br /><small>".$mycode['description']."</small></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"submit\" name=\"edit\" value=\"$lang->edit\" class=\"submitbutton\"></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"submit\" name=\"delete\" value=\"$lang->delete\" class=\"submitbutton\"></td>\n";
		echo "</tr>\n";
		endform();
		$done = 1;
	}
	if(!$done)
	{
		makelabelcode("<div align=\"center\">$lang->no_custom_mycode</div>", "", 4);
	}
	endtable();
	cpfooter();
}

?>