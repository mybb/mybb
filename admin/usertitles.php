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
$lang->load("usertitles");

checkadminpermissions("caneditutitles");
logadmin();

addacpnav($lang->nav_usertitles, "usertitles.php");
switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_title);
		break;
	case "edit":
		addacpnav($lang->edit_title);
		break;
	case "delete":
		addacpnav($lang->nav_delete_title);
		break;
}

if($mybb->input['action'] == "do_add")
{
	$posts = intval($_POST['posts']);
	$title = addslashes($_POST['title']);
	$stars = intval($_POST['stars']);
	$starimage = addslashes($_POST['starimage']);
	if($stars < 1)
	{
		$stars = 0;
	}
	$db->query("INSERT INTO ".TABLE_PREFIX."usertitles VALUES (NULL, '$posts', '$title', '$stars', '$starimage')");
	cpredirect("usertitles.php", $lang->title_added);
}
if($mybb->input['action'] == "do_delete")
{
	if($deletesubmit)
	{	
		$db->query("DELETE FROM ".TABLE_PREFIX."usertitles WHERE utid='$utid'");
		cpredirect("usertitles.php", $lang->title_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "do_edit")
{
	$posts = intval($_POST['posts']);
	$title = addslashes($_POST['title']);
	$stars = intval($_POST['stars']);
	$starimage = addslashes($_POST['starimage']);
	if($stars < 1)
	{
		$stars = 0;
	}
	$db->query("UPDATE ".TABLE_PREFIX."usertitles SET posts='$posts', title='$title', stars='$stars', starimage='$starimage' WHERE utid='$utid'");
	cpredirect("usertitles.php", $lang->title_updated);
}
if($mybb->input['action'] == "add")
{
	cpheader();
	startform("usertitles.php", "" , "do_add");
	starttable();
	tableheader($lang->new_title);
	makeinputcode($lang->title, "title");
	makeinputcode($lang->minimum_posts, "posts", "", "4");
	makeinputcode($lang->stars."<br><small>$lang->stars_description</small>", "stars");
	makeinputcode($lang->star_image."<br><small>$lang->star_image_description</small>", "starimage");
	endtable();
	endform($lang->add_title, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "delete")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usertitles WHERE utid='$utid'");
	$title = $db->fetch_array($query);
	$lang->delete_title = sprintf($lang->delete_title, $title['title']);
	$lang->delete_title_confirm = sprintf($lang->delete_title_confirm, $title['title']);
	cpheader();
	startform("usertitles.php", "", "do_delete");
	makehiddencode("utid", $utid);
	starttable();
	tableheader($lang->delete_title, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<center>$lang->delete_title_confirm<br><br>$yes$no</center>", "");
	endtable();
	endform();
	cpfooter();
}
if($mybb->input['action'] == "edit")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usertitles WHERE utid='$utid'");
	$title = $db->fetch_array($query);
	$lang->edit_title = sprintf($lang->edit_title, $title['title']);
	cpheader();
	startform("usertitles.php", "" , "do_edit");
	makehiddencode("utid", $utid);
	starttable();
	tableheader($lang->edit_title);
	makeinputcode($lang->title, "title", $title[title]);
	makeinputcode($lang->minimum_posts, "posts", "$title[posts]", "4");
	makeinputcode($lang->stars."<br><small>$lang->stars_description</small>", "stars", $title[stars]);
	makeinputcode($lang->star_image."<br><small>$lang->star_image_description</small>", "starimage", $title[starimage]);
	endtable();
	endform($lang->update_title, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "modify" || $mybb->input['action'] == "")
{
	if(!$noheader)
	{
		cpheader();
	}
	$hopto[] = "<input type=\"button\" value=\"$lang->new_usertitle\" onclick=\"hopto('usertitles.php?action=add');\" class=\"hoptobutton\">";
	makehoptolinks($hopto);

	starttable();
	tableheader($lang->usertitles);
	tablesubheader($lang->select_edit_delete);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usertitles ORDER BY posts");
	while($title = $db->fetch_array($query))
	{
		$usertitles .= "\n<li><b>$title[title]</b> ($lang->minimum_posts $title[posts]) ".
			makelinkcode($lang->edit, "usertitles.php?action=edit&utid=$title[utid]").
			makelinkcode($lang->delete, "usertitles.php?action=delete&utid=$title[utid]")."\n";
	}
	makelabelcode("<ul>\n$usertitles\n</ul>", "");
	endtable();
	cpfooter();
}

?>