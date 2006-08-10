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

require "./global.php";

// Load language packs for this section
global $lang;
$lang->load("smilies");

addacpnav($lang->nav_smilies, "smilies.php?".SID);
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

$plugins->run_hooks("admin_smilies_start");

checkadminpermissions("caneditsmilies");
logadmin();

if($mybb->input['action'] == "do_add")
{
	if(empty($mybb->input['find']) || empty($mybb->input['path']) || empty($mybb->input['name']))
	{
		cperror($lang->error_fill_form);
	}
	$newsmilie = array(
		"name" => $db->escape_string($mybb->input['name']),
		"find" => $db->escape_string($mybb->input['find']),
		"image" => $db->escape_string($mybb->input['path']),
		"disporder" => intval($mybb->input['disporder']),
		"showclickable" => $db->escape_string($mybb->input['showclickable'])
	);
	$plugins->run_hooks("admin_smilies_do_add");
	$db->insert_query(TABLE_PREFIX."smilies", $newsmilie);
	$cache->updatesmilies();
	cpredirect("smilies.php?".SID, $lang->smilie_added);
}

if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{
		$plugins->run_hooks("admin_smilies_do_delete");
		$db->query("DELETE FROM ".TABLE_PREFIX."smilies WHERE sid='".$mybb->input['sid']."'");
		$cache->updatesmilies();
		cpredirect("smilies.php?".SID, $lang->smilie_deleted);
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
		"name" => $db->escape_string($mybb->input['name']),
		"find" => $db->escape_string($mybb->input['find']),
		"image" => $db->escape_string($mybb->input['path']),
		"disporder" => intval($mybb->input['disporder']),
		"showclickable" => $db->escape_string($mybb->input['showclickable'])
	);
	$plugins->run_hooks("admin_smilies_do_edit");
	$db->update_query(TABLE_PREFIX."smilies", $smilie, "sid='".intval($mybb->input['sid'])."'");
	$cache->updatesmilies();
	cpredirect("smilies.php?".SID, $lang->smilie_updated);
}

if($mybb->input['action'] == "edit")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."smilies WHERE sid='".intval($mybb->input['sid'])."'");
	$smilie = $db->fetch_array($query);
	if(!$smilie['sid'])
	{
		cperror($lang->invalid_smilie);
	}
	$plugins->run_hooks("admin_smilies_edit");
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
	$plugins->run_hooks("admin_smilies_delete");
	cpheader();
	startform("smilies.php", "", "do_delete");
	makehiddencode("sid", $mybb->input['sid']);
	starttable();
	tableheader($lang->delete_smilie, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<div align=\"center\">$lang->delete_confirm<br /><br />$yes$no</div>", "");
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_smilies_add");
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
  $path = $mybb->input['path'];
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
		$plugins->run_hooks("admin_smilies_do_addmultiple");
		reset($mybb->input['smimport']);
    $find = $mybb->input['smcode'];
		$name = $mybb->input['smname'];
		foreach($mybb->input['smimport'] as $image => $insert)
		{
			if($insert)
			{
				$imageurl = $path."/".$image;
				$newsmilie = array(
					"name" => $db->escape_string($name[$image]),
					"find" => $db->escape_string($find[$image]),
					"image" => $db->escape_string($imageurl),
					"showclickable" => "yes"
				);
				$db->insert_query(TABLE_PREFIX."smilies", $newsmilie);
			}
		}
		$cache->updatesmilies();
		cpredirect("smilies.php?".SID, $lang->all_sel_added);
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
	$path = $mybb->input['path'];
	$dir = @opendir(MYBB_ROOT.$path);
	if(!$dir)
	{
		cperror($lang->bad_directory);
	}
	$plugins->run_hooks("admin_smilies_addmultiple");
	$query = $db->simple_select(TABLE_PREFIX."smilies");
	while($smilie = $db->fetch_array($query))
	{
		$asmilies[$smilie[image]] = 1;
	}
	while($file = readdir($dir))
	{
		if($file != ".." && $file != ".")
		{
			$ext = get_extension($file);
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
		for($i = 1; $i <= $pages; $i++)
		{
			if($i == $page)
			{
				$pagelist .= " <input type=\"submit\" name=\"page\" value=\"$i\" disabled=\"disabled\" /> ";
			}
			else
			{
				$pagelist .= " <input type=\"submit\" name=\"page\" value=\"$i\" /> ";
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
			cpredirect("smilies.php?".SID, $lang->all_sel_added);
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
		for($i = $start; $i < $end; $i++)
		{
			$file = $smilies[$i];
			$ext = get_extension($smilies[$i]);
			$find = str_replace(".".$ext, "", $file);
			$name = ucfirst($find);
			$bgcolor = getaltbg();
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><img src=\"../$path/$file\" alt=\":$find:\" /><br /><small>$file</small></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"text\" name=\"smname[$file]\" value=\"$name\" /></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"text\" name=\"smcode[$file]\" value=\":$find:\" /></td>\n";
			echo "<td class=\"$bgcolor\" align=\"right\"><input type=\"checkbox\" name=\"smimport[$file]\" value=\"1\" />\n";
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
	$plugins->run_hooks("admin_smilies_modify");
	if(!$noheader)
	{
		cpheader();
	}
	$hopto[] = "<input type=\"button\" value=\"$lang->add_new_smilie\" onclick=\"hopto('smilies.php?".SID."&amp;action=add');\" class=\"hoptobutton\" />";
	$hopto[] = "<input type=\"button\" value=\"$lang->add_multiple_smilies\" onclick=\"hopto('smilies.php?".SID."&amp;action=add&amp;multi=1');\" class=\"hoptobutton\" />";
	makehoptolinks($hopto);
	starttable();
	tableheader($lang->smilies, "", 5);
	tablesubheader($lang->edit_delete, "", 5);
	$theme[imgdir] = "images";
	$query = $db->query("SELECT COUNT(sid) AS smilies FROM ".TABLE_PREFIX."smilies");
	$smiliecount = $db->fetch_field($query, "smilies");
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
			echo "<tr>\n";
		}
		$smilie['image'] = str_replace("{theme:imgdir}", $theme['imgdir'], $smilie['image']);
		if(strstr($smilie['image'], "p://") || substr($smilie['image'],0,1) == "/") 
    {
			$image = $smilie['image'];
		}
		else
		{
			$image = "../$smilie[image]";
		}
		if($smilies['find'])
		{
      $smilies['find'] = "<b>".stripslashes($smilie['find'])."</b>";
    }
		echo "<td class=\"$altbg\" align=\"center\" valign=\"bottom\" nowrap>{$smilie['name']}<br /><br /><img src=\"$image\" alt=\"\" />\n&nbsp;&nbsp;{$smilie['find']}\n<br />\n<br />\n";
		echo "<a href=\"smilies.php?".SID."&amp;action=edit&amp;sid={$smilie['sid']}&amp;page=$page&amp;perpage=$perpage\">$lang->edit</a>\n <a href=\"smilies.php?".SID."&amp;action=delete&amp;sid={$smilie['sid']}&amp;page=$page&amp;perpage=$perpage\">$lang->delete</a>\n";
		echo "</td>\n";
		$listed++;
		if($listed == 5)
		{
			echo "</tr>\n";
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
			echo "<td class=\"$altbg\">&nbsp;</td>\n";
			$listed++;
			if($listed == "5")
			{
				$listed = 0;
			}
		}
		echo "</tr>\n";
	}
	if($smiliecount > $perpage)
	{
		$pages = $smiliecount / $perpage;
		$pages = ceil($pages);
		if($page > 1)
		{
			$prev = $page - 1;
			$prevpage = "<a href=\"smilies.php?".SID."&amp;page=$prev&amp;perpage=$perpage\">$lang->prevpage</a>\n";
		}
		if($page < $pages)
		{
			$next = $page + 1;
			$nextpage = "<a href=\"smilies.php?".SID."&amp;page=$next&amp;perpage=$perpage\">$lang->nextpage</a>\n";
		}
		for($i = 1; $i <= $pages; $i++)
		{
			if($i == $page)
			{
				$pagelist .= "<b>$i</b>";
			}
			else
			{
				$pagelist .= "<a href=\"smilies.php?".SID."&amp;page=$i&amp;perpage=$perpage\">$i</a>\n";
			}
		}
	}
	if($pagelist || $prevpage  || $nextpage)
	{
		echo "<tr>\n<td class=\"altbg1\" colspan=\"5\">\n$prevpage $pagelist $nextpage\n</td>\n</tr>\n";
	}
	echo "<tr><td class=\"altbg2\" colspan=\"5\">\n<form action=\"smilies.php?".SID."&amp;page=$page\" method=\"post\">\n$lang->smilies_per_page \n<input type=\"text\" name=\"perpage\" value=\"$perpage\" />\n <input type=\"submit\" name=\"submit\" value=\"$lang->go\" />\n</form>\n</td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n</tr>\n</table>\n";
	cpfooter();
}
?>
