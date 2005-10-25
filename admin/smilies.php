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
$lang->load("smilies");

addacpnav($lang->nav_smilies, "smilies.php");
switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_smilie);
		break;
	case "edit":
		addacpnav($lang->nav_edit_smilie);
		break;
	case "delete":
		addacpnav($lang->nav_delete_smilie);
		break;
}

checkadminpermissions("caneditsmilies");
logadmin();

if($mybb->input['action'] == "do_add")
{
	if(empty($mybb->input['find']) || empty($mybb->input['path']) || empty($mybb->input['name']))
	{
		cperror($lang->error_fill_form);
	}
	$newsmilie = array(
		"name" => addslashes($mybb->input['name']),
		"find" => addslashes($mybb->input['find']),
		"image" => addslashes($mybb->input['path']),
		"disporder" => intval($mybb->input['disporder']),
		"showclickable" => $mybb->input['showclickable']
		);

	$db->insert_query(TABLE_PREFIX."smilies", $newsmilie);
	$cache->updatesmilies();
	cpredirect("smilies.php", $lang->smilie_added);
}

if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{
		$db->query("DELETE FROM ".TABLE_PREFIX."smilies WHERE sid='".$mybb->input['sid']."'");
		$cache->updatesmilies();
		cpredirect("smilies.php", $lang->smilie_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "do_edit")
{
	if(empty($mybb->input['find']) || empty($mybb->input['path']) || empty($mybb->input['name']))
	{
		cperror($lang->error_fill_form);
	}
	$smilie = array(
		"name" => addslashes($mybb->input['name']),
		"find" => addslashes($mybb->input['find']),
		"image" => addslashes($mybb->input['path']),
		"disporder" => intval($mybb->input['disporder']),
		"showclickable" => $mybb->input['showclickable']
		);

	$db->update_query(TABLE_PREFIX."smilies", $smilie, "sid='".intval($mybb->input['sid'])."'");
	$cache->updatesmilies();
	cpredirect("smilies.php", $lang->smilie_updated);
}

if($mybb->input['action'] == "edit")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."smilies WHERE sid='".intval($mybb->input['sid'])."'");
	$smilie = $db->fetch_array($query);
	if(!$smilie['sid'])
	{
		cperror($lang->invalid_smilie);
	}
	$theme['imgdir'] = "images";
	cpheader();
	startform("smilies.php", "", "do_edit");
	makehiddencode("sid", $mybb->input['sid']);
	starttable();
	tableheader($lang->modify_smilie);
	makeinputcode($lang->name, "name", $smilie['name']);
	makeinputcode($lang->text_to_replace, "find", $smilie['find']);
	makeinputcode($lang->image_path, "path", $smilie['image']);
	makeinputcode($lang->disp_order, "disporder", $smilie['disporder']);
	makeyesnocode($lang->show_clickable, "showclickable", $smilie['showclickable']);
	endtable();
	endform($lang->update_smilie, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."smilies WHERE sid='".intval($mybb->input['sid'])."'");
	$smilie = $db->fetch_array($query);
	if(!$smilie['sid'])
	{
		cperror($lang->invalid_smilie);
	}
	cpheader();
	startform("smilies.php", "", "do_delete");
	makehiddencode("sid", $mybb->input['sid']);
	starttable();
	tableheader($lang->delete_smilie, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<center>$lang->delete_confirm<br><br>$yes$no</center>", "");
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "add")
{
	cpheader();
	if(!$mybb->input['multi'])
	{
		startform("smilies.php", "", "do_add");
		starttable();
		tableheader($lang->add_smilie);
		makeinputcode($lang->name, "name");
		makeinputcode($lang->text_to_replace, "find");
		makeinputcode($lang->image_path, "path", "images/smilies/");
		makeinputcode($lang->disp_order, "disporder");
		makeyesnocode($lang->show_clickable, "showclickable");
		endtable();
		endform($lang->add_smilie, $lang->reset_button);
	}
	else
	{
		startform("smilies.php", "", "addmultiple");
		starttable();
		tableheader($lang->add_multiple);
		makeinputcode($lang->path, "path", "images/smilies");
		makeinputcode($lang->per_page, "perpage", "15");
		endtable();
		endform($lang->browse_for, $lang->reset_button);
	}
	cpfooter();
}

if($mybb->input['action'] == "do_addmultiple")
{
	if($mybb->input['page'])
	{
		$mybb->input['action'] = "addmultiple";
	}
	elseif(!is_array($mybb->input['smimport']))
	{
		cpmessage($lang->sel_no_images);
	}
	else
	{
		reset($mybb->input['smimport']);
		while(list($image,$insert) = each($mybb->input['smimport']))
		{
			if($insert)
			{
				$find = $smcode[$image];
				$name = $smname[$image];
				$imageurl = $path."/".$image;
				$newsmilie = array(
					"name" => addslashes($name),
					"find" => addslashes($find),
					"image" => addslashes($imageurl),
					"showclickable" => "yes"
				);
				$db->insert_query(TABLE_PREFIX."smilies", $newsmilie);
			}
		}
		$cache->updatesmilies();
		cpheader();
		starttable();
		makelabelcode($lang->all_sel_added);
		endtable();
		echo "<br>";
		$finishedinsert = 1;
		$mybb->input['action'] = "add";
	}
}

if($mybb->input['action'] == "addmultiple")
{
	$perpage = intval($mybb->input['perpage']);
	$page = intval($mybb->input['page']);
	if(!$perpage)
	{
		$perpage = 15;
	}
	$dir = @opendir($mybb->input['path']);
	if(!$dir)
	{
		cperror($lang->bad_directory);
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."smilies");
	while($smilie = $db->fetch_array($query))
	{
		$asmilies[$smilie[image]] = 1;
	}
	while($file = readdir($dir))
	{
		if($file != ".." && $file != ".")
		{
			$ext = getextention($file);
			if($ext == "gif" || $ext == "jpg" || $ext == "jpeg" || $ext == "png" || $ext == "bmp")
			{
				if(!$asmilies["$path/$file"])
				{
					$smilies[] = $file;
				}
			}
		}
	}
	closedir($dir);
	if(!$page)
	{
		$page = 1;
	}
	$newsmilies = count($smilies);
	if($newsmilies > $perpage)
	{
		$pages = $newsmilies / $perpage;
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
	if($end > $newsmilies)
	{
		$end = $newsmilies;
	}

	if(!$newsmilies)
	{
		if($finishedmulti)
		{
			cpredirect("smilies.php", $lang->all_sel_added);
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
		startform("smilies.php", "", "do_addmultiple");
		makehiddencode("perpage", $perpage);
		makehiddencode("path", $path);
		starttable();
		tableheader($lang->add_multiple, "", "4");
		echo "<tr>\n";
		echo "<td class=\"subheader\" align=\"center\">$lang->image</td>\n";
		echo "<td class=\"subheader\" align=\"center\">$lang->name</td>\n";
		echo "<td class=\"subheader\" align=\"center\">$lang->text_to_replace</td>\n";
		echo "<td class=\"subheader\" align=\"center\">$lang->add</td>\n";
		echo "</tr>\n";
		for($i=$start;$i<$end;$i++)
		{
			$file = $smilies[$i];
			$ext = getextention($smilies[$i]);
			$find = str_replace(".".$ext, "", $file);
			$name = ucfirst($find);
			$bgcolor = getaltbg();
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><img src=\"../$path/$file\"><br><small>$file</small></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"text\" name=\"smname[$file]\" value=\"$name\"></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"text\" name=\"smcode[$file]\" value=\":$find:\"></td>\n";
			echo "<td class=\"$bgcolor\" align=\"right\"><input type=\"checkbox\" name=\"smimport[$file]\" value=\"1\">\n";
			echo "</tr>\n";
		}
	}
	if($newsmilies > $perpage)
	{
		tablesubheader($pagelist, "", 4);
	}
	endtable();
	endform($lang->add_multiple, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "modify" || $mybb->input['action'] == "")
{
	if(!$noheader)
	{
		cpheader();
	}
	$hopto[] = "<input type=\"button\" value=\"$lang->add_new_smilie\" onclick=\"hopto('smilies.php?action=add');\" class=\"hoptobutton\">";
	$hopto[] = "<input type=\"button\" value=\"$lang->add_multiple_smilies\" onclick=\"hopto('smilies.php?action=add&multi=1');\" class=\"hoptobutton\">";
	makehoptolinks($hopto);
	starttable();
	tableheader($lang->smilies, "", 5);
	tablesubheader($lang->edit_delete, "", 5);
	$theme[imgdir] = "images";
	$query = $db->query("SELECT COUNT(sid) AS smilies FROM ".TABLE_PREFIX."smilies");
	$smiliecount = $db->result($query, 0);
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
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."smilies ORDER BY disporder ASC LIMIT $start, $perpage");
	while($smilie = $db->fetch_array($query))
	{
		if($listed == "0")
		{
			echo "<tr>";
		}
		$smilie[image] = str_replace("{theme:imgdir}", $theme[imgdir], $smilie[image]);
		if(strstr($smilie[image], "p://") || substr($smilie[image],0,1) == "/") {
			$image = $smilie[image];
		}
		else
		{
			$image = "../$smilie[image]";
		}
		echo "<td class=\"$altbg\" align=\"center\" valign=\"bottom\" nowrap>$smilie[name]<br><br><img src=\"$image\">&nbsp;&nbsp;<b>".stripslashes($smilie[find])."</b><br><br>";
		echo "<a href=\"smilies.php?action=edit&sid=$smilie[sid]&page=$page&perpage=$perpage\">$lang->edit</a> <a href=\"smilies.php?action=delete&sid=$smilie[sid]&page=$page&perpage=$perpage\">$lang->delete</a>";
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
	if($smiliecount > $perpage)
	{
		$pages = $smiliecount / $perpage;
		$pages = ceil($pages);
		if($page > 1)
		{
			$prev = $page - 1;
			$prevpage = "<a href=\"smilies.php?page=$prev&perpage=$perpage\">$lang->prevpage</a>";
		}
		if($page < $pages)
		{
			$next = $page + 1;
			$nextpage = "<a href=\"smilies.php?page=$next&perpage=$perpage\">$lang->nextpage</a>";
		}
		for($i=1;$i<=$pages;$i++)
		{
			if($i == $page)
			{
				$pagelist .= "<b>$i</b>";
			}
			else
			{
				$pagelist .= "<a href=\"smilies.php?page=$i&perpage=$perpage\">$i</a>";
			}
		}
	}
	if($pagelist || $prevpage  || $nextpage)
	{
		echo "<tr><td class=\"altbg1\" colspan=\"5\">$prevpage $pagelist $nextpage</td></tr>";
	}
	echo "<form action=\"smilies.php?page=$page\" method=\"post\"><tr><td class=\"altbg2\" colspan=\"5\">$lang->smilies_per_page <input type=\"text\" name=\"perpage\" value=\"$perpage\"> <input type=\"submit\" name=\"submit\" value=\"$lang->go\"></td></tr></form>";
	echo "</table>\n";
	echo "</td></tr></table>";
	cpfooter();
}
?>
