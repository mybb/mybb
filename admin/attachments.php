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
$lang->load("attachments");

checkadminpermissions("caneditattach");
logadmin();

switch($action)
{
	case "search":
		addacpnav($lang->nav_attachment_manager);
		break;
	case "do_search";
		addacpnav($lang->nav_attachment_manager, "attachments.php?action=search");
		addacpnav($lang->nav_attachment_results);
		break;
	case "add":
		addacpnav($lang->nav_attachtypes, "attachments.php");
		addacpnav($lang->nav_add_attachtype);
		break;
	case "delete";
		addacpnav($lang->nav_attachtypes, "attachments.php");
		addacpnav($lang->nav_delete_attachtype);
	case "edit":
		addacpnav($lang->nav_attachtypes, "attachments.php");
		addacpnav($lang->nav_edit_attachtype);
		break;
	case "modify":
	case "":
		addacpnav($lang->nav_attachtypes);
		break;
}

if($action == "do_add")
{
	// add new type to database
	if($extension)
	{
		$mimetype = addslashes($_POST['mimetype']);
		$extension = addslashes($_POST['extension']);
		$maxsize = addslashes($_POST['maxsize']);
		$icon = addslashes($_POST['icon']);
		$db->query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid,mimetype,extension,maxsize,icon) VALUES ('','$mimetype','$extension','$maxsize','$icon')");
		$cache->updateattachtypes();
		cpredirect("attachments.php", $lang->type_added);
	} else
	{
		cpredirect("attachments.php", $lang->type_add_missing_fields);
	}
}

if($action == "do_delete")
{
	// remove type from database
	if($deletesubmit)
	{
		$db->query("DELETE FROM ".TABLE_PREFIX."attachtypes WHERE atid='$atid'");
		$cache->updateattachtypes();
		cpredirect("attachments.php", $lang->type_deleted);
	}
	else
	{
		$action = "modify";
	}
}

if($action == "do_edit")
{
	// update database with new type settings
	if($extension)
	{
		$mimetype = addslashes($_POST['mimetype']);
		$extension = addslashes($_POST['extension']);
		$maxsize = addslashes($_POST['maxsize']);
		$icon = addslashes($_POST['icon']);
		$db->query("UPDATE ".TABLE_PREFIX."attachtypes SET mimetype='$mimetype', extension='$extension', maxsize='$maxsize', icon='$icon' WHERE atid='$atid'");
		$cache->updateattachtypes();
		cpredirect("attachments.php", $lang->type_updated);
	}
	else
	{
		cpredirect("attachments.php", $lang->type_edit_missing_fields);
	}
}

if($action == "do_search")
{
	// search for the attachments
	$sql = "";
	if($username)
	{
		$username = addslashes($username);
		$sql .= " AND u.username LIKE '%$username%'";
	}
	if($filename)
	{
		$filename = addslashes($filename);
		$sql .= " AND a.filename LIKE '%$filename%'";
	}
	if($mimetype)
	{
		$mimetype = addslashes($mimetype);
		$sql .= " AND a.filetype LIKE '%$mimetype%'";
	}
	if($forum)
	{
		$sql .= " AND p.fid='$forum'";
	}
	if($postdate)
	{
		$postdate = intval($postdate);
		$postdate = time() - ($postdate * 86400);
		$sql .= " AND p.dateline >= '$postdate'";
	}
	if($sizeless)
	{
		$sizeless = addslashes($sizeless);
		$sizeless *= 1024;
		$sql .= " AND a.filesize < '$sizeless'";
	}
	if($sizemore)
	{
		$sizemore = addslashes($sizemore);
		$sizemore *= 1024;
		$sql .= " AND a.filesize > '$sizemore'";
	}
	if($downloadsless)
	{
		$downloadsless = intval($downloadsless);
		$sql .= " AND a.downloads < '$downloadsless'";
	}
	if($downloadsmore)
	{
		$downloadsmore = intval($downloadsmore);
		$sql .= " AND a.downloads > '$downloadsmore'";
	}
	if($sql)
	{
		$sql = substr_replace($sql, "WHERE", 0, 4);
	}
	$query = $db->query("SELECT a.*, p.tid, p.fid, t.subject, f.name, u.uid, u.username FROM ".TABLE_PREFIX."attachments a LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid) LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) LEFT JOIN ".TABLE_PREFIX."forums f ON (p.fid=f.fid) LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid) $sql ORDER BY a.filename");
	$num_results = $db->num_rows($query);

	if($num_results < 1)
	{
		cpmessage($lang->no_attachments);
	}
	cpheader();
	startform("attachments.php", "", "do_search_delete");
	starttable();
	tableheader($lang->attach_search_results, "", "7");
	echo "<tr>\n";
	echo "<td class=\"subheader\">$lang->delete</td>\n";
	echo "<td class=\"subheader\">$lang->filename</td>\n";
	echo "<td class=\"subheader\">$lang->author</td>\n";
	echo "<td class=\"subheader\">$lang->location</td>\n";
	echo "<td class=\"subheader\">$lang->filetype</td>\n";
	echo "<td class=\"subheader\">$lang->filesize</td>\n";
	echo "<td class=\"subheader\">$lang->downloads</td>\n";
	echo "</tr>\n";

	$altbg = "altbg1";
	while($result = $db->fetch_array($query))
	{
		$filename = stripslashes($result[filename]);
		$filesize = $result[filesize];
		if($filesize >= 1073741824)
		{
			$filesize = round($filesize / 1073741824 * 100) / 100 . " GB";
		}
		elseif($filesize >= 1048576)
		{
			$filesize = round($filesize / 1048576 * 100) / 100 . " MB";
		}
		elseif($filesize >= 1024)
		{
			$filesize = round($filesize / 1024 * 100) / 100 . " KB";
		}
		else
		{
			$filesize = $filesize . " bytes";
		}

		echo "<tr>\n";
		echo "<td class=\"$altbg\" align=\"center\"><input type=\"checkbox\" name=\"check[$result[aid]]\" value=\"$result[aid]\"></td>\n";
		echo "<td class=\"$altbg\"><a href=\"../attachment.php?aid=$result[aid]\">$filename</td>\n";
		echo "<td class=\"$altbg\"><a href=\"../member.php?action=profile&uid=$result[uid]\">$result[username]</a></td>\n";
		echo "<td class=\"$altbg\"><a href=\"../forumdisplay.php?fid=$result[fid]\">$result[name]</a> &raquo; <a href=\"../showthread.php?tid=$result[tid]&pid=$result[pid]#pid$result[pid]\">$result[subject]</a></td>\n";
		echo "<td class=\"$altbg\">$result[filetype]</td>\n";
		echo "<td class=\"$altbg\">$filesize</td>\n";
		echo "<td class=\"$altbg\">$result[downloads]</td>\n";
		echo "</tr>\n";

		if($altbg == "altbg1")
		{
			$altbg = "altbg2";
		}
		else
		{
			$altbg = "altbg1";
		}
	}
	endtable();
	endform($lang->delete_selected, $lang->clear_checks);
	cpfooter();
}

if($action == "do_search_delete")
{
	// delete selected attachments from database
	if(is_array($check) && !empty($check))
	{
		foreach($check as $aid)
		{
			$db->query("DELETE FROM ".TABLE_PREFIX."attachments WHERE aid='$aid'");
		}
		cpredirect("attachments.php?action=search", $lang->attachs_deleted);
	}
	else
	{
		cpredirect("attachments.php?action=search", $lang->attachs_noneselected);
	}
}

if($action == "add")
{
	// form for adding new attachment type
	cpheader();
	startform("attachments.php", "", "do_add");
	starttable();
	tableheader($lang->new_attach_type);
	makeinputcode($lang->extension, "extension");
	makeinputcode($lang->mimetype, "mimetype");
	makeinputcode($lang->max_size, "maxsize");
	makeinputcode($lang->type_icon, "icon", "images/attachtypes/");
	endtable();
	endform($lang->add_type, $lang->reset_button);
	cpfooter();
}

if($action == "search")
{
	// display form for searching for attachments
	$query = $db->query("SELECT COUNT(aid) AS Total, SUM(filesize) AS Sum FROM ".TABLE_PREFIX."attachments");
	$stats = $db->fetch_array($query);
	if(!$stats[Sum])
	{
		$stats[Sum] = 0;
	}
	if($stats[Sum] >= 1073741824)
	{
		$stats[Sum] = round($stats[Sum] / 1073741824 * 100) / 100 . " gigabytes";
	}
	elseif($stats[Sum] >= 1048576)
	{
		$stats[Sum] = round($stats[Sum] / 1048576 * 100) / 100 . " megabytes";
	}
	elseif($stats[Sum] >= 1024)
	{
		$stats[Sum] = round($stats[Sum] / 1024 * 100) / 100 . " kilobytes";
	}
	else
	{
		$stats[Sum] = $stats[Sum] . " bytes";
	}

	if(!$noheader)
	{
		cpheader();
	}
	startform("attachments.php", "", "do_search");
	starttable();
	tableheader($lang->attach_management);
	tablesubheader($lang->attach_stats);

	$lang->attach_stats2 = sprintf($lang->attach_stats2, $stats[Total], $stats[Sum]);

	makelabelcode("<center>$lang->attach_stats2</center>", "", 2);
	tablesubheader($lang->search_attachments);
	makeinputcode($lang->filename_contains, "filename");
	makeinputcode($lang->filetype_contains, "mimetype");
	makeinputcode($lang->poster_contains, "username");
	makelabelcode($lang->forum_is, forumselect("forum"));
	makeinputcode($lang->posted_in_last, "postdate", "", 5);
	makeinputcode($lang->size_less, "sizeless", "", 5);
	makeinputcode($lang->size_greater, "sizemore", "", 5);
	makeinputcode($lang->downloads_less, "downloadsless", "", 5);
	makeinputcode($lang->downloads_greater, "downloadsmore", "", 5);
	endtable();
	endform($lang->search, $lang->reset_button);
	cpfooter();
}

if($action == "edit")
{
	if($delete)
	{
		$action = "delete";
	}
	else
	{
		// form for editing an attachment type
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachtypes WHERE atid='$atid'");
		$type = $db->fetch_array($query);
		$type['name'] = htmlspecialchars_uni(stripslashes($type['name']));
		cpheader();
		startform("attachments.php", "", "do_edit");
		makehiddencode("atid", $atid);
		starttable();
		$lang->edit_attach_type = sprintf($lang->edit_attach_type, $type[name]);
		tableheader($lang->edit_attach_type);
		makeinputcode($lang->extension, "extension", $type['extension']);
		makeinputcode($lang->mimetype, "mimetype", $type['mimetype']);
		makeinputcode($lang->max_size, "maxsize", $type['maxsize']);
		makeinputcode($lang->type_icon, "icon", $type['icon']);
		endtable();
		endform($lang->update_type, $lang->reset_button);
		cpfooter();
	}
}

if($action == "delete")
{
	// confirmation page for deleting an attachment type
	$query = $db->query("SELECT name FROM ".TABLE_PREFIX."attachtypes WHERE atid='$atid'");
	$name = stripslashes($db->result($query, 0));
	cpheader();
	startform("attachments.php", "", "do_delete");
	makehiddencode("atid", $atid);
	starttable();
	$lang->delete_attach_type = sprintf($lang->delete_attach_type, $name);
	tableheader($lang->delete_attach_type);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	$lang->delete_confirm = sprintf($lang->delete_confirm, $name);
	makelabelcode("<center>$lang->delete_confirm<br><br>$yes$no</center>", "");
	endtable();
	endform();
	cpfooter();
}

if($action == "modify" || !$action)
{
	// list all attachment types so user can pick one to edit/delete
	cpheader();
	$hopto[] = "<input type=\"button\" value=\"$lang->add_attach_type\" onclick=\"hopto('attachments.php?action=add');\" class=\"hoptobutton\">";
	makehoptolinks($hopto);
	starttable();
	tableheader($lang->attachment_types, "", "6");
	echo "<tr>\n";
	echo "<td class=\"subheader\" colspan=\"2\">$lang->type_extension</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->type_mimetype</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->type_max_size</td>\n";
	echo "<td class=\"subheader\" align=\"center\" colspan=\"2\">$lang->controls</td>\n";
	echo "</tr>\n";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachtypes ORDER BY name");
	while($type = $db->fetch_array($query))
	{
		$type[name] = stripslashes($type[name]);
		$size = getfriendlysize($type['maxsize']*1024);
		if($type['icon'])
		{
			$icon = "<img src=\"../$type[icon]\">";
		}
		else
		{
			$icon = "&nbsp;";
		}
		$bgcolor = getaltbg();
		startform("attachments.php", "", "edit");
		makehiddencode("atid", $type['atid']);
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"1\">$icon</td>";
		echo "<td class=\"$bgcolor\" width=\"25%\"><b>.$type[extension]</b></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"25%\">$type[mimetype]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"25%\">$size</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"1\"><input type=\"submit\" name=\"edit\" value=\"$lang->type_edit\" class=\"submitbutton\"></td>";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"1\"><input type=\"submit\" name=\"delete\" value=\"$lang->type_delete\" class=\"submitbutton\"></td>";
		echo "</tr>\n";
		endform();
	}
	endtable();
	cpfooter();
}
?>