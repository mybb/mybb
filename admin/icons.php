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
$lang->load("icons");

$iid = intval($mybb->input['iid']);

checkadminpermissions("caneditpicons");
logadmin();

addacpnav($lang->nav_posticons, "icons.php?".SID);
switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_posticon);
		break;
	case "edit":
		addacpnav($lang->nav_edit_posticon);
		break;
	case "delete":
		addacpnav($lang->nav_delete_posticon);
		break;
}

$plugins->run_hooks("admin_icons_start");

if($mybb->input['action'] == "do_add")
{
	$sqlarray = array(
		"name" => $db->escape_string($mybb->input['name']),
		"path" => $db->escape_string($mybb->input['path']),
	);
	if(empty($sqlarray['name']) || empty($sqlarray['path']))
	{
		cperror($lang->error_fill_form);
	}
	$plugins->run_hooks("admin_icons_do_add");
	$db->insert_query(TABLE_PREFIX."icons", $sqlarray);
	cpredirect("icons.php?".SID, $lang->icon_added);
}

if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{	
		$plugins->run_hooks("admin_icons_do_delete");
		$db->delete_query(TABLE_PREFIX."icons", "iid='$iid'");
		cpredirect("icons.php?".SID, $lang->icon_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "do_edit")
{
	$sqlarray = array(
		"name" => $db->escape_string($mybb->input['name']),
		"path" => $db->escape_string($mybb->input['path']),
	);
	if(empty($sqlarray['name']) || empty($sqlarray['path']))
	{
		cperror($lang->error_fill_form);
	}
	$plugins->run_hooks("admin_icons_do_edit");
	$db->update_query(TABLE_PREFIX."icons", $sqlarray, "iid='$iid'");
	cpredirect("icons.php?".SID, $lang->icon_updated);
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select(TABLE_PREFIX."icons", "*", "iid='$iid'");
	$icon = $db->fetch_array($query);
	$plugins->run_hooks("admin_icons_edit");
	if(!$icon['iid'])
	{
		cperror($lang->invalid_icon);
	}

	if(!$noheader)
	{
		cpheader();
	}
	$lang->modify_icon = sprintf($lang->modify_icon, $icon['name']);
	startform("icons.php", "", "do_edit");
	makehiddencode("iid", $iid);
	starttable();
	tableheader($lang->modify_icon);
	makeinputcode($lang->name, "name", $icon['name']);
	makeinputcode($lang->image_path, "path", $icon['path']);
	endtable();
	endform($lang->update_icon, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select(TABLE_PREFIX."icons", "*", "iid='$iid'");
	$icon = $db->fetch_array($query);
	$plugins->run_hooks("admin_icons_delete");
	if(!$icon['iid'])
	{
		cperror($lang->invalid_icon);
	}
	$lang->delete_icon = sprintf($lang->delete_icon, $icon['name']);
	$lang->delete_icon_confirm = sprintf($lang->delete_icon_confirm, $icon['name']);
	cpheader();
	startform("icons.php", "", "do_delete");
	makehiddencode("iid", $iid);
	starttable();
	tableheader($lang->delete_icon, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<div align=\"center\">$lang->delete_icon_confirm<br /><br />$yes$no</div>", "");
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_icons_add");
	cpheader();
	startform("icons.php", "", "do_add");
	starttable();
	tableheader($lang->add_icon);
	makeinputcode($lang->name, "name");
	makeinputcode($lang->image_path, "path", "images/icons");
	endtable();
	endform($lang->add_icon, $lang->reset_button);
	echo "<br />\n";
	echo "<br />\n";
	startform("icons.php", "", "addmultiple");
	starttable();
	tableheader($lang->add_multiple);
	makeinputcode($lang->path, "path", "images/icons");
	makeinputcode($lang->per_page, "perpage", "15");
	endtable();
	endform($lang->browse, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "do_addmultiple")
{
	$plugins->run_hooks("admin_icons_do_addmultiple");
	if($mybb->input['page'])
	{
		$mybb->input['action'] = "addmultiple";
	}
	elseif(!is_array($mybb->input['piimport']))
	{
		cpmessage($lang->no_images_import);
	}
	else
	{
		reset($mybb->input['piimport']);
		while(list($image,$insert) = each($mybb->input['piimport']))
		{
			if($insert)
			{
				$sqlarray = array(
					"name" => $db->escape_string($mybb->input['piname'][$image]),
					"path" => $db->escape_string($path."/".$image),
					);
				$db->insert_query(TABLE_PREFIX."icons", $sqlarray);
			}
		}
		cpredirect("icons.php?".SID, $lang->icons_added);
	}
}

if($mybb->input['action'] == "addmultiple")
{
	$plugins->run_hooks("admin_icons_addmultiple");
	$perpage = intval($mybb->input['perpage']);
	if(!$perpage)
	{
		$perpage = 15;
	}
	$dir = @opendir($mybb->input['path']);
	if(!$dir)
	{
		cperror($lang->invalid_directory);
	}
	$query = $db->simple_select(TABLE_PREFIX."icons");
	while($icon = $db->fetch_array($query))
	{
		$aicons[$icon['path']] = 1;
	}
	while($file = readdir($dir))
	{
		if($file != ".." && $file != ".")
		{
			$ext = get_extension($file);
			if($ext == "gif" || $ext == "jpg" || $ext == "jpeg" || $ext == "png" || $ext == "bmp")
			{
				if(!isset($aicons[$mybb->input['path'].'/'.$file])) {
					$icons[] = $file;
				}
			}
		}
	}
	closedir($dir);
	if(!isset($mybb->input['page']))
	{
		$page = 1;
	}
	else
	{
		$page = intval($mybb->input['page']);
	}
	$newicons = count($icons);
	if($newicons > $perpage)
	{
		$pages = $newicons / $perpage;
		$pages = ceil($pages);
		for($i=1;$i<=$pages;$i++)
		{
			if($i == $page)
			{
				$pagelist .= " <input type=\"submit\" name=\"page\" value=\"$i\" disabled=\"disabled\"> ";
			}
			else
			{
				$pagelist .= " <input type=\"submit\" name=\"page\" value=\"$i\"> ";
			}
		}
		$start = ($page-1) *$perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
		$pages = 1;
	}
	$end = $perpage + $start;
	if($end > $newicons)
	{
		$end = $newicons;
	}

	if(!$newicons)
	{
		if($finishedmulti)
		{
			cpredirect("icons.php?".SID, $lang->finished_adding);
		}
		else
		{
			cpmessage($lang->no_images);
		}
	}
	else
	{
		if(!$finishedinsert)
		{
			cpheader();
		}
		startform("icons.php", "", "do_addmultiple");
		makehiddencode("perpage", $perpage);
		makehiddencode("path", $path);
		starttable();
		$lang->add_multiple2 = sprintf($lang->add_multiple2, $page, $pages);
		tableheader($lang->add_multiple2, "", 3);
		echo "<tr>\n";
		echo "<td class=\"subheader\" align=\"center\">$lang->image</td>\n";
		echo "<td class=\"subheader\" align=\"center\">$lang->name</td>\n";
		echo "<td class=\"subheader\" align=\"center\">$lang->del</td>\n";
		echo "</tr>\n";
		for($i = $start; $i < $end; $i++)
		{
			$file = $icons[$i];
			$ext = get_extension($icons[$i]);
			$find = str_replace(".".$ext, "", $file);
			$name = ucfirst($find);
			$bgcolor = getaltbg();
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><img src=\"../$path/$file\"><br /><small>$file</small></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"text\" name=\"piname[$file]\" value=\"$name\"></td>\n";
			echo "<td class=\"$bgcolor\" align=\"right\"><input type=\"checkbox\" name=\"piimport[$file]\" value=\"1\">\n";
			echo "</tr>\n";
		}
	}
	if($newicons > $perpage)
	{
		tablesubheader($pagelist, "", 4);
	}
	endtable();
	endform($lang->add_posticons, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "modify" || $mybb->input['action'] == "")
{
	$plugins->run_hooks("admin_icons_modify");
	if(!$noheader)
	{
		cpheader();
	}
	starttable();
	tableheader($lang->posticons, "", 5);
	tablesubheader($lang->edit_delete, "", 5);

	$query = $db->simple_select(TABLE_PREFIX."icons", "COUNT(iid) AS icons");
	$iconcount = $db->fetch_field($query, "icons");
	$perpage = intval($mybb->input['perpage']);
	$page = intval($mybb->input['page']);
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
	$listed = 0;
	$altbg = "altbg1";
	$options = array(
		"order_by" => "name",
		"order_dir" => "ASC",
		"limit_start" => $start,
		"limit" => $perpage
	);
	$query = $db->simple_select(TABLE_PREFIX."icons", "*", "", $options);
	while($icon = $db->fetch_array($query))
	{
		if($listed == "0")
		{
			echo "<tr>";
		}
		if(strstr($icon['path'], "p://") || substr($icon[path],0,1) == "/")
		{
			$image = $icon['path'];
		}
		else
		{
			$image = "../$icon[path]";
		}
		echo "<td class=\"$altbg\" align=\"center\" valign=\"bottom\" nowrap>$icon[name]<br /><br /><img src=\"$image\"><br /><br />";
		echo "<a href=\"icons.php?".SID."&action=edit&iid=$icon[iid]&page=$page&perpage=$perpage\">$lang->edit</a> <a href=\"icons.php?".SID."&action=delete&iid=$icon[iid]&page=$page&perpage=$perpage\">$lang->delete</a>";
		echo "</td>";
		$listed++;
		if($listed == 5)
		{
			echo "</tr>";
			if($altbg == "altbg2")
			{
				$altbg = "altbg1";
			}
			else
			{
				$altbg = "altbg2";
			}
			$listed = 0;
		}
	}
	if($listed != "0")
	{
		while($listed != "0")
		{
			echo "<td class=\"$altbg\">&nbsp;</td>";
			$listed++;
			if($listed == "5")
			{
				$listed = 0;
			}
		}
		echo "</tr>";
	}
	if($iconcount > $perpage)
	{
		$pages = $iconcount / $perpage;
		$pages = ceil($pages);
		if($page > 1)
		{
			$prev = $page - 1;
			$prevpage = "<a href=\"icons.php?".SID."&page=$prev&perpage=$perpage\">$lang->prevpage</a>";
		}
		if($page < $pages)
		{
			$next = $page + 1;
			$nextpage = "<a href=\"icons.php?".SID."&page=$next&perpage=$perpage\">$lang->nextpage</a>";
		}
		for($i = 1; $i <= $pages; $i++)
		{
			if($i == $page)
			{
				$pagelist .= "<b>$i</b>";
			}
			else
			{
				$pagelist .= "<a href=\"icons.php?".SID."&page=$i&perpage=$perpage\">$i</a> ";
			}
		}
	}
	if($pagelist || $prevpage  || $nextpage)
	{
		echo "<tr><td class=\"altbg1\" colspan=\"5\">$prevpage $pagelist $nextpage</td></tr>";
	}
	echo "<form action=\"icons.php?".SID."&page=$page\" method=\"post\"><tr><td class=\"altbg2\" colspan=\"5\">$lang->icons_per_page <input type=\"text\" name=\"perpage\" value=\"$perpage\"> <input type=\"submit\" name=\"submit\" value=\"Go\"></td></tr></form>";
	endtable();
	cpfooter();
}
?>