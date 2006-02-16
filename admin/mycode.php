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
$lang->load("mycode");

//checkadminpermissions("caneditmycode");
//logadmin();

addacpnav($lang->nav_mycode, "mycode.php?action=modify");

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

if($mybb->input['action'] == "add")
{
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
		"title" => addslashes($mybb->input['title']),
		"description" => addslashes($mybb->input['description']),
		"regex" => addslashes($mybb->input['regex']),
		"replacement" => addslashes($mybb->input['replacement']),
		"active" => addslashes($mybb->input['active'])
		);

	$db->insert_query(TABLE_PREFIX."mycode", $newmycode);

	$cache->updatemycode();

	cpredirect("mycode.php", $lang->mycode_added);
}

if($mybb->input['action'] == "edit")
{
	if($mybb->input['delete'])
	{
		header("Location: mycode.php?action=delete&cid=".intval($mybb->input['cid']));
		exit;
	}

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."mycode WHERE cid='".intval($mybb->input['cid'])."'");
	$mycode = $db->fetch_array($query);

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
		"title" => addslashes($mybb->input['title']),
		"description" => addslashes($mybb->input['description']),
		"regex" => addslashes($mybb->input['regex']),
		"replacement" => addslashes($mybb->input['replacement']),
		"active" => addslashes($mybb->input['active'])
		);

	$db->update_query(TABLE_PREFIX."mycode", $mycode, "cid='".intval($mybb->input['cid'])."'");

	$cache->updatemycode();

	cpredirect("mycode.php", $lang->mycode_updated);
}

if($mybb->input['action'] == "delete")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."mycode WHERE cid='".intval($mybb->input['cid'])."'");
	$mycode = $db->fetch_array($query);
	if(!$mycode['cid'])
	{
		cperror($lang->invalid_mycode);
	}
	cpheader();
	startform("mycode.php", "", "do_delete");
	makehiddencode("cid", $mycode['cid']);
	starttable();
	tableheader($lang->delete_mycode, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<center>$lang->delete_confirm<br><br>$yes$no</center>", "");
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{
		$db->query("DELETE FROM ".TABLE_PREFIX."mycode WHERE cid='".intval($mybb->input['cid'])."'");
		$cache->updatemycode();
		cpredirect("mycode.php", $lang->mycode_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "modify" || !$mybb->input['action'])
{
	cpheader();
	$hopto[] = "<input type=\"button\" value=\"$lang->add_mycode\" onclick=\"hopto('mycode.php?action=add');\" class=\"hoptobutton\">";
	makehoptolinks($hopto);
	starttable();
	tableheader($lang->custom_mycode, "", 4);
	echo "<tr>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->mycode_title</td>\n";
	echo "<td class=\"subheader\" align=\"center\" colspan=\"2\">$lang->options</td>\n";
	echo "</tr>\n";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."mycode ORDER BY title ASC");
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
		makelabelcode("<center>$lang->no_custom_mycode</center>", "", 4);
	}
	endtable();
	cpfooter();
}

?>