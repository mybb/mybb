<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/eula.html
 *
 * $Id$
 */

define("IN_MYBB", 1);

require_once "./global.php";

// Load language packs for this section
global $lang;
$lang->load("usertitles");

checkadminpermissions("caneditutitles");
logadmin();

addacpnav($lang->nav_usertitles, "usertitles.php?".SID);
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

$plugins->run_hooks("admin_usertitles_start");

if($mybb->input['action'] == "do_add")
{
	if($mybb->input['stars'] < 1)
	{
		$mybb->input['stars'] = $mybb->input['stars'];
	}

	$usertitle = array(
		"posts" => intval($mybb->input['posts']),
		"title" => $db->escape_string($mybb->input['title']),
		"stars" => intval($mybb->input['stars']),
		"starimage" => $db->escape_string($mybb->input['starimage'])
		);
	$plugins->run_hooks("admin_usertitles_do_add");
	$db->insert_query(TABLE_PREFIX."usertitles", $usertitle);
	cpredirect("usertitles.php?".SID, $lang->title_added);
}
if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{	
		$plugins->run_hooks("admin_usertitles_do_delete");
		$db->delete_query(TABLE_PREFIX."usertitles", "utid='".intval($mybb->input['utid'])."'");
		cpredirect("usertitles.php?".SID, $lang->title_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "do_edit")
{
	if($mybb->input['stars'] < 1)
	{
		$mybb->input['stars'] = $mybb->input['stars'];
	}

	$usertitle = array(
		"posts" => intval($mybb->input['posts']),
		"title" => $db->escape_string($mybb->input['title']),
		"stars" => intval($mybb->input['stars']),
		"starimage" => $db->escape_string($mybb->input['starimage'])
		);
	$plugins->run_hooks("admin_usertitles_do_edit");
	$db->update_query(TABLE_PREFIX."usertitles", $usertitle, "utid='".intval($mybb->input['utid'])."'");
	cpredirect("usertitles.php?".SID, $lang->title_updated);
}
if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_usertitles_add");
	cpheader();
	startform("usertitles.php", "" , "do_add");
	starttable();
	tableheader($lang->new_title);
	makeinputcode($lang->title, "title");
	makeinputcode($lang->minimum_posts, "posts", "", "4");
	makeinputcode($lang->stars."<br /><small>$lang->stars_description</small>", "stars");
	makeinputcode($lang->star_image."<br /><small>$lang->star_image_description</small>", "starimage");
	endtable();
	endform($lang->add_title, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("admin_usertitles_delete");
	$query = $db->simple_select(TABLE_PREFIX."usertitles", "*", "utid='".intval($mybb->input['utid'])."'");
	$title = $db->fetch_array($query);
	$lang->delete_title = sprintf($lang->delete_title, $title['title']);
	$lang->delete_title_confirm = sprintf($lang->delete_title_confirm, $title['title']);
	cpheader();
	startform("usertitles.php", "", "do_delete");
	makehiddencode("utid", $mybb->input['utid']);
	starttable();
	tableheader($lang->delete_title, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<div align=\"center\">$lang->delete_title_confirm<br /><br />$yes$no</div>", "");
	endtable();
	endform();
	cpfooter();
}
if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select(TABLE_PREFIX."usertitles", "*", "utid='".intval($mybb->input['utid'])."'");
	$title = $db->fetch_array($query);
	$plugins->run_hooks("admin_usertitles_edit");
	$lang->edit_title = sprintf($lang->edit_title, $title['title']);
	cpheader();
	startform("usertitles.php", "" , "do_edit");
	makehiddencode("utid", $mybb->input['utid']);
	starttable();
	tableheader($lang->edit_title);
	makeinputcode($lang->title, "title", $title[title]);
	makeinputcode($lang->minimum_posts, "posts", "$title[posts]", "4");
	makeinputcode($lang->stars."<br /><small>$lang->stars_description</small>", "stars", $title[stars]);
	makeinputcode($lang->star_image."<br /><small>$lang->star_image_description</small>", "starimage", $title[starimage]);
	endtable();
	endform($lang->update_title, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "modify" || $mybb->input['action'] == "")
{
	$plugins->run_hooks("admin_usertitles_modify");
	if(!$noheader)
	{
		cpheader();
	}
	$hopto[] = "<input type=\"button\" value=\"$lang->new_usertitle\" onclick=\"hopto('usertitles.php?".SID."&amp;action=add');\" class=\"hoptobutton\" />";
	makehoptolinks($hopto);

	starttable();
	tableheader($lang->usertitles);
	tablesubheader($lang->select_edit_delete);
	$options = array(
		"order_by" => "posts"
	);
	$query = $db->simple_select(TABLE_PREFIX."usertitles", "*", "", $options);
	while($title = $db->fetch_array($query))
	{
		$usertitles .= "\n<li><b>$title[title]</b> ($lang->minimum_posts $title[posts]) ".
			makelinkcode($lang->edit, "usertitles.php?".SID."&amp;action=edit&amp;utid=$title[utid]").
			makelinkcode($lang->delete, "usertitles.php?".SID."&amp;action=delete&amp;utid=$title[utid]")."\n";
	}
	makelabelcode("<ul>\n$usertitles\n</ul>", "");
	endtable();
	cpfooter();
}

?>
