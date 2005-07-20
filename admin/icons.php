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
$lang->load("icons");

$iid = intval($_REQUEST['iid']);

checkadminpermissions("caneditpicons");
logadmin();

addacpnav($lang->nav_posticons, "icons.php");
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

if($mybb->input['action'] == "do_add") {
	$name = addslashes($_POST['name']);
	$path = addslashes($_POST['path']);
	if (empty($name) || empty($path)) {
		cperror($lang->error_fill_form);
	}
	$db->query("INSERT INTO ".TABLE_PREFIX."icons VALUES (NULL,'$name','$path')");
	cpredirect("icons.php", $lang->icon_added);
}
if($mybb->input['action'] == "do_delete") {
	if($deletesubmit) {	
		$db->query("DELETE FROM ".TABLE_PREFIX."icons WHERE iid='$iid'");
		cpredirect("icons.php", $lang->icon_deleted);
	} else {
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "do_edit") {
	$name = addslashes($_POST['name']);
	$path = addslashes($_POST['path']);
	if(empty($name) || empty($path)) {
		cperror($lang->error_fill_form);
	}
	$db->query("UPDATE ".TABLE_PREFIX."icons SET name='$name', path='$path' WHERE iid='$iid'");
	cpredirect("icons.php", $lang->icon_updated);
}

if($mybb->input['action'] == "edit") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."icons WHERE iid='$iid'");
	$icon = $db->fetch_array($query);
	
	if(!$icon['iid']) {
		cperror($lang->invalid_icon);
	}

	if(!$noheader) {
		cpheader();
	}
	$lang->modify_icon = sprintf($lang->modify_icon, $icon[name]);
	startform("icons.php", "", "do_edit");
	makehiddencode("iid", $iid);
	starttable();
	tableheader($lang->modify_icon);
	makeinputcode($lang->name, "name", $icon[name]);
	makeinputcode($lang->image_path, "path", $icon[path]);
	endtable();
	endform($lang->update_icon, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "delete") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."icons WHERE iid='$iid'");
	$icon = $db->fetch_array($query);
	if(!$icon['iid']) {
		cperror($lang->invalid_icon);
	}
	$lang->delete_icon = sprintf($lang->delete_icon, $icon[name]);
	$lang->delete_icon_confirm = sprintf($lang->delete_icon_confirm, $icon[name]);
	cpheader();
	startform("icons.php", "", "do_delete");
	makehiddencode("iid", $iid);
	starttable();
	tableheader($lang->delete_icon, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<center>$lang->delete_icon_confirm<br><br>$yes$no</center>", "");
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "add") {
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

if($mybb->input['action'] == "do_addmultiple") {
	if($page) {
		$mybb->input['action'] = "addmultiple";
	}
	elseif(!is_array($piimport)) {
		cpmessage($lang->no_images_import);
	}
	else {
		reset($piimport);
		while(list($image,$insert) = each($piimport)) {
			if($insert) {
				$name = $piname[$image];
				$imageurl = $path."/".$image;
				$db->query("INSERT INTO ".TABLE_PREFIX."icons (iid,name,path) VALUES (NULL,'$name','$imageurl')");
			}
		}
		cpredirect("icons.php", $lang->icons_added);
	}
}
if($mybb->input['action'] == "addmultiple") {
	$perpage = intval($perpage);
	if(!$perpage) {
		$perpage = 15;
	}
	$dir = @opendir("$path");
	if(!$dir) {
		cperror($lang->invalid_directory);
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."icons");
	while($icon = $db->fetch_array($query)) {
		$aicons[$icon[path]] = 1;
	}
	while($file = readdir($dir)) {
		if($file != ".." && $file != ".") {
			$ext = getextention($file);
			if($ext == "gif" || $ext == "jpg" || $ext == "jpeg" || $ext == "png" || $ext == "bmp") {
				if(!$aicons["$path/$file"]) {
					$icons[] = $file;
				}
			}
		}
	}
	closedir($dir);
	if(!$page) {
		$page = 1;
	}
	$newicons = count($icons);
	if($newicons > $perpage) {
		$pages = $newicons / $perpage;
		$pages = ceil($pages);
		for($i=1;$i<=$pages;$i++) {
			if($i == $page) {
				$pagelist .= " <input type=\"submit\" name=\"page\" value=\"$i\" disabled=\"disabled\"> ";
			} else {
				$pagelist .= " <input type=\"submit\" name=\"page\" value=\"$i\"> ";
			}
		}
		$start = ($page-1) *$perpage;
	} else {
		$start = 0;
		$page = 1;
		$pages = 1;
	}
	$end = $perpage + $start;
	if($end > $newicons) {
		$end = $newicons;
	}

	if(!$newicons) {
		if($finishedmulti) {
			cpredirect("icons.php", $lang->finished_adding);
		} else {
			cpmessage($lang->no_images);
		}
	} else {
		if(!$finishedinsert) {
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
		for($i=$start;$i<$end;$i++) {
			$file = $icons[$i];
			$ext = getextention($icons[$i]);
			$find = str_replace(".".$ext, "", $file);
			$name = ucfirst($find);
			$bgcolor = getaltbg();
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><img src=\"../$path/$file\"><br><small>$file</small></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"text\" name=\"piname[$file]\" value=\"$name\"></td>\n";
			echo "<td class=\"$bgcolor\" align=\"right\"><input type=\"checkbox\" name=\"piimport[$file]\" value=\"1\">\n";
			echo "</tr>\n";
		}
	}
	if($newicons > $perpage) {
		tablesubheader($pagelist, "", 4);
	}
	endtable();
	endform($lang->add_posticons, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "modify" || $mybb->input['action'] == "") {
	if(!$noheader) {
		cpheader();
	}
	starttable();
	tableheader($lang->posticons, "", 5);
	tablesubheader($lang->edit_delete, "", 5);

	$query = $db->query("SELECT COUNT(iid) AS icons FROM ".TABLE_PREFIX."icons");
	$iconcount = $db->result($query, 0);
	$perpage = intval($perpage);
	if(!$perpage) {
		$perpage = 15;
	}
	if($page) {
		$start = ($page-1) *$perpage;
	} else {
		$start = 0;
		$page = 1;
	}
	$listed = 0;
	$altbg = "altbg1";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."icons ORDER BY name ASC LIMIT $start, $perpage");
	while($icon = $db->fetch_array($query)) {
		if($listed == "0") {
			echo "<tr>";
		}
		if(strstr($icon[path], "p://") || substr($icon[path],0,1) == "/") {
			$image = $icon[path];
		} else {
			$image = "../$icon[path]";
		}
		echo "<td class=\"$altbg\" align=\"center\" valign=\"bottom\" nowrap>$icon[name]<br><br><img src=\"$image\"><br><br>";
		echo "<a href=\"icons.php?action=edit&iid=$icon[iid]&page=$page&perpage=$perpage\">$lang->edit</a> <a href=\"icons.php?action=delete&iid=$icon[iid]&page=$page&perpage=$perpage\">$lang->delete</a>";
		echo "</td>";
		$listed++;
		if($listed == 5) {
			echo "</tr>";
			if($altbg == "altbg2") {
				$altbg = "altbg1";
			} else {
				$altbg = "altbg2";
			}
			$listed = 0;
		}
	
	}
	if($listed != "0") {
		while($listed != "0") {
			echo "<td class=\"$altbg\">&nbsp;</td>";
			$listed++;
			if($listed == "5") {
				$listed = 0;
			}
		}
		echo "</tr>";
	}
	if($iconcount > $perpage) {
		$pages = $iconcount / $perpage;
		$pages = ceil($pages);
		if($page > 1) {
			$prev = $page - 1;
			$prevpage = "<a href=\"icons.php?page=$prev&perpage=$perpage\">$lang->prevpage</a>";
		}
		if($page < $pages) {
			$next = $page + 1;
			$nextpage = "<a href=\"icons.php?page=$next&perpage=$perpage\">$lang->nextpage</a>";
		}
		for($i=1;$i<=$pages;$i++) {
			if($i == $page) {
				$pagelist .= "<b>$i</b>";
			} else {
				$pagelist .= "<a href=\"icons.php?page=$i&perpage=$perpage\">$i</a> ";
			}
		}
	}
	if($pagelist || $prevpage  || $nextpage) {
		echo "<tr><td class=\"altbg1\" colspan=\"5\">$prevpage $pagelist $nextpage</td></tr>";
	}
	echo "<form action=\"icons.php?page=$page\" method=\"post\"><tr><td class=\"altbg2\" colspan=\"5\">$lang->icons_per_page <input type=\"text\" name=\"perpage\" value=\"$perpage\"> <input type=\"submit\" name=\"submit\" value=\"Go\"></td></tr></form>";
	endtable();
	cpfooter();
}
?>