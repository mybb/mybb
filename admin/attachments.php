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

switch($mybb->input['action'])
{
	case "search":
		addacpnav($lang->nav_attachment_manager);
		break;
	case "do_search":
	case "do_orphan_search":
		addacpnav($lang->nav_attachment_manager, "attachments.php?action=search");
		addacpnav($lang->nav_attachment_results);
		break;
	case "add":
		addacpnav($lang->nav_attachtypes, "attachments.php");
		addacpnav($lang->nav_add_attachtype);
		break;
	case "delete":
		addacpnav($lang->nav_attachtypes, "attachments.php");
		addacpnav($lang->nav_delete_attachtype);
	case "edit":
		addacpnav($lang->nav_attachtypes, "attachments.php");
		addacpnav($lang->nav_edit_attachtype);
		break;
	case "stats":
		addacpnav($lang->nav_attachment_stats);
		break;
	case "modify":
	case "":
		addacpnav($lang->nav_attachtypes);
		break;
}

if($mybb->input['action'] == "do_add")
{
	// add new type to database
	if(($mybb->input['extension'] || $mybb->input['mimetype']) && $mybb->input['maxsize'])
	{
		$sqlarray = array(
			"mimetype" => addslashes($mybb->input['mimetype']),
			"extension" => addslashes($mybb->input['extension']),
			"maxsize" => addslashes($mybb->input['maxsize']),
			"icon" => addslashes($mybb->input['icon']),
			);
		$db->insert_query(TABLE_PREFIX."attachtypes", $sqlarray);
		$cache->updateattachtypes();
		cpredirect("attachments.php", $lang->type_added);
	}
	else
	{
		cperror($lang->type_add_missing_fields);
	}
}

if($mybb->input['action'] == "do_delete")
{
	// remove type from database
	if($deletesubmit)
	{
		$db->query("DELETE FROM ".TABLE_PREFIX."attachtypes WHERE atid='".intval($mybb->input['atid'])."'");
		$cache->updateattachtypes();
		cpredirect("attachments.php", $lang->type_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "do_edit")
{
	// update database with new type settings
	if(($mybb->input['extension'] || $mybb->input['mimetype']) && $mybb->input['maxsize'])
	{
		$sqlarray = array(
			"atid" => intval($mybb->input['atid']),
			"mimetype" => addslashes($mybb->input['mimetype']),
			"extension" => addslashes($mybb->input['extension']),
			"maxsize" => addslashes($mybb->input['maxsize']),
			"icon" => addslashes($mybb->input['icon']),
			);
		$db->update_query(TABLE_PREFIX."attachtypes", $sqlarray, "atid='".$sqlarray['atid']."'");
		$cache->updateattachtypes();
		cpredirect("attachments.php", $lang->type_updated);
	}
	else
	{
		cperror($lang->type_edit_missing_fields);
	}
}

if($mybb->input['action'] == "do_search")
{
	// search for the attachments
	$sql = "";
	if($mybb->input['username'])
	{
		$username = addslashes($mybb->input['username']);
		$sql .= " AND u.username LIKE '%$username%'";
	}
	if($mybb->input['filename'])
	{
		$filename = addslashes($mybb->input['filename']);
		$sql .= " AND a.filename LIKE '%$filename%'";
	}
	if($mybb->input['mimetype'])
	{
		$mimetype = addslashes($mybb->input['mimetype']);
		$sql .= " AND a.filetype LIKE '%$mimetype%'";
	}
	if($mybb->input['forum'])
	{
		$sql .= " AND p.fid='".intval($mybb->input['forum'])."'";
	}
	if($mybb->input['postdate'])
	{
		$postdate = intval($mybb->input['postdate']);
		$postdate = time() - ($postdate * 86400);
		$sql .= " AND p.dateline >= '$postdate'";
	}
	if($mybb->input['sizeless'])
	{
		$sizeless = addslashes($mybb->input['sizeless']);
		$sizeless *= 1024;
		$sql .= " AND a.filesize < '$sizeless'";
	}
	if($mybb->input['sizemore'])
	{
		$sizemore = addslashes($mybb->input['sizemore']);
		$sizemore *= 1024;
		$sql .= " AND a.filesize > '$sizemore'";
	}
	if($mybb->input['downloadsless'])
	{
		$downloadsless = intval($mybb->input['downloadsless']);
		$sql .= " AND a.downloads < '$downloadsless'";
	}
	if($mybb->input['downloadsmore'])
	{
		$downloadsmore = intval($mybb->input['downloadsmore']);
		$sql .= " AND a.downloads > '$downloadsmore'";
	}
	if($sql)
	{
		$sql = substr_replace($sql, "WHERE", 0, 4);
	}
	// Get attachments from database list
	$query = $db->query("SELECT a.*, p.tid, p.fid, t.subject, f.name, u.uid, u.username FROM ".TABLE_PREFIX."attachments a LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid) LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) LEFT JOIN ".TABLE_PREFIX."forums f ON (p.fid=f.fid) LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid) $sql ORDER BY a.filename");
	$num_results = $db->num_rows($query);

	// Get attachments filenames from filesystem
	if ($uploads = opendir($mybb->settings['uploadspath']))
	{
		while (false !== ($file = readdir($uploads)))
		{
			if (substr($file, -7, 7) == ".attach")
			{
				$uploaded_files[] = $file;
			}
		}
		closedir($uploads);
	}

	if($num_results < 1)
	{
		cpmessage($lang->no_attachments);
	}
	cpheader();
	startform("attachments.php", "", "do_search_delete");
	starttable();
	tableheader($lang->attach_search_results, "", "7");
	makelabelcode($lang->attach_search_results_note, "", "7");
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
		// Check if file exists on the server
		$key = array_search($result['attachname'], $uploaded_files);
		if($key !== false)
		{
			unset($uploaded_files[$key]);
			$filename = stripslashes($result['filename']);
		}
		else
		{
			$filename = "<span class=\"highlight1\">".stripslashes($result['filename'])."</span>";
		}
		if(!$result['visible'] && $result['pid'])
		{
			$filename = "<em>".$filename."</em>";
		}
		
		$filesize = getfriendlysize($result['filesize']);

		echo "<tr>\n";
		echo "<td class=\"$altbg\" align=\"center\"><input type=\"checkbox\" name=\"check[$result[aid]]\" value=\"$result[aid]\"></td>\n";
		echo "<td class=\"$altbg\"><a href=\"../attachment.php?aid=$result[aid]\">$filename</a></td>\n";
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

if($mybb->input['action'] == "do_search_delete")
{
	// delete selected attachments from database
	if(is_array($mybb->input['check']) && !empty($mybb->input['check']))
	{
		foreach($mybb->input['check'] as $aid)
		{
			$db->query("DELETE FROM ".TABLE_PREFIX."attachments WHERE aid='".intval($aid)."'");
		}
		cpredirect("attachments.php?action=search", $lang->attachs_deleted);
	}
	else
	{
		cperror($lang->attachs_noneselected);
	}
}

if($mybb->input['action'] == "orphans")
{
	// Search files that do not exist in the database
	// Get attachments from database list
	$query = $db->simple_select(TABLE_PREFIX."attachments", "attachname");
	$db_list = array();
	while($file = $db->fetch_array($query))
	{
		$db_list[] = $file['attachname'];
	}
	// Get attachments filenames from filesystem
	$orphan_files = array();
	if ($uploads = opendir($mybb->settings['uploadspath']))
	{
		while (false !== ($file = readdir($uploads)))
		{
			if (substr($file, -7, 7) == ".attach" && !in_array($file, $db_list))
			{
				$orphan_files[] = $file;
			}
		}
		closedir($uploads);
	}

	cpheader();
	startform("attachments.php", "", "do_orphan_delete");
	starttable();
	tableheader($lang->orphan_search_results, "", "3");
	echo "<tr>\n";
	echo "<td class=\"subheader\">$lang->delete</td>\n";
	echo "<td class=\"subheader\">$lang->filename</td>\n";
	echo "<td class=\"subheader\">$lang->filesize</td>\n";
	echo "</tr>\n";

	$altbg = "altbg1";
	foreach($orphan_files as $filename)
	{
		$filesize = getfriendlysize(filesize($mybb->settings['uploadspath']."/".$filename));

		echo "<tr>\n";
		echo "<td class=\"$altbg\" align=\"center\"><input type=\"checkbox\" name=\"check[]\" value=\"$filename\"></td>\n";
		echo "<td class=\"$altbg\">$filename</td>\n";
		echo "<td class=\"$altbg\">$filesize</td>\n";
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
if($mybb->input['action'] == "do_orphan_delete")
{
	// delete selected orphans from filesystem
	if(is_array($mybb->input['check']) && !empty($mybb->input['check']))
	{
		$error = false;
		foreach($mybb->input['check'] as $filename)
		{
			if(file_exists($mybb->settings['uploadspath']."/".basename($filename)))
			{
				if(!@unlink($mybb->settings['uploadspath']."/".basename($filename)))
				{
					$error = true;
				}
			}
		}
		if($error)
		{
			cpredirect("attachments.php?action=orphans", $lang->problem_deleting);
		}
		else
		{
			cpredirect("attachments.php?action=orphans", $lang->attachs_deleted);
		}
	}
	else
	{
		cperror($lang->attachs_noneselected);
	}
}

if($mybb->input['action'] == "add")
{
	// form for adding new attachment type
	cpheader();
	startform("attachments.php", "", "do_add");
	starttable();
	tableheader($lang->new_attach_type);
	makeinputcode($lang->extension, "extension");
	makeinputcode($lang->mimetype, "mimetype");
	makelabelcode($lang->control_note, '', 2);
	makeinputcode($lang->max_size, "maxsize");
	makeinputcode($lang->type_icon, "icon", "images/attachtypes/");
	endtable();
	endform($lang->add_type, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "search")
{
	cpheader();
	startform("attachments.php", "", "do_search");
	starttable();
	tableheader($lang->attach_management);
	tablesubheader($lang->search_attachments);
	makeinputcode($lang->filename_contains, "filename");
	makeinputcode($lang->filetype_contains, "mimetype");
	makeinputcode($lang->poster_contains, "username");
	makelabelcode($lang->forum_is, forumselect("forum"));
	makeinputcode($lang->posted_in_last, "postdate", "", 5, ' ('.$lang->days.')');
	makeinputcode($lang->size_less, "sizeless", "", 5, ' ('.$lang->size_kb.')');
	makeinputcode($lang->size_greater, "sizemore", "", 5, ' ('.$lang->size_kb.')');
	makeinputcode($lang->downloads_less, "downloadsless", "", 5);
	makeinputcode($lang->downloads_greater, "downloadsmore", "", 5);
	endtable();
	endform($lang->search, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "edit")
{
	if($delete)
	{
		$mybb->input['action'] = "delete";
	}
	else
	{
		// form for editing an attachment type
		$atid = intval($mybb->input['atid']);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachtypes WHERE atid='".$atid."' LIMIT 1");
		$type = $db->fetch_array($query);
		$type['name'] = htmlspecialchars_uni(stripslashes($type['name']));
		cpheader();
		startform("attachments.php", "", "do_edit");
		makehiddencode("atid", $atid);
		starttable();
		$lang->edit_attach_type = sprintf($lang->edit_attach_type, $type['name']);
		tableheader($lang->edit_attach_type);
		makeinputcode($lang->extension, "extension", $type['extension']);
		makeinputcode($lang->mimetype, "mimetype", $type['mimetype']);
		makelabelcode($lang->control_note, '', 2);
		makeinputcode($lang->max_size, "maxsize", $type['maxsize']);
		makeinputcode($lang->type_icon, "icon", $type['icon']);
		endtable();
		endform($lang->update_type, $lang->reset_button);
		cpfooter();
	}
}

if($mybb->input['action'] == "delete")
{
	// confirmation page for deleting an attachment type
	$atid = intval($mybb->input['atid']);
	$query = $db->query("SELECT name FROM ".TABLE_PREFIX."attachtypes WHERE atid='".$atid."'");
	$name = stripslashes($db->fetch_field($query, "name"));
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
if($mybb->input['action'] == "stats")
{
	cpheader();
	
	$query = $db->query("SELECT COUNT(*) AS attachments, SUM(filesize) AS totalsize, SUM(downloads) AS downloads FROM ".TABLE_PREFIX."attachments");
	$stats = $db->fetch_array($query);
	if(!$stats['downloads'])
	{
		$stats['downloads'] = "0";
	}
	starttable();
	tableheader($lang->overall_attachment_statistics, "");
	makelabelcode($lang->total_attachments, $stats['attachments']);
	makelabelcode($lang->total_size, getfriendlysize($stats['totalsize']));
	makelabelcode($lang->total_downloads, $stats['downloads']);
	endtable();

	if($stats['attachments'] > 0)
	{
		starttable();
		tableheader($lang->most_popular_attachments, "", 4);
		echo "<tr>\n";
		echo "<td class=\"subheader\" width=\"30%\">$lang->file_name</td>\n";
		echo "<td class=\"subheader\" align=\"center\" width=\"40%\">$lang->post</td>\n";
		echo "<td class=\"subheader\" align=\"center\" width=\"20%\">$lang->username</td>\n";
		echo "<td class=\"subheader\" align=\"center\" width=\"10%\">$lang->downloads</td>\n";
		echo "</tr>\n";
		$query = $db->query("SELECT a.*, p.tid, p.subject, u.username FROM ".TABLE_PREFIX."attachments a LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid) ORDER BY downloads DESC LIMIT 0, 5");
		while($attachment = $db->fetch_array($query))
		{
			$bgcolor = getaltbg();
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\"><a href=\"../attachment.php?aid=".$attachment['aid']."\">".$attachment['filename']."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><a href=\"../showthread.php?tid=".$attachment['tid']."&pid=".$attachment['pid']."#pid".$attachment['pid']."\">".$attachment['subject']."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><a href=\"../member.php?action=proflile&uid=".$attachment['uid']."\">".$attachment['username']."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".$attachment['downloads']."</a></td>\n";
			echo "</tr>\n";
		}
		endtable();

		starttable();
		tableheader($lang->largest_attachments, "", 4);
		echo "<tr>\n";
		echo "<td class=\"subheader\" width=\"30%\">$lang->file_name</td>\n";
		echo "<td class=\"subheader\" align=\"center\" width=\"40%\">$lang->post</td>\n";
		echo "<td class=\"subheader\" align=\"center\" width=\"20%\">$lang->username</td>\n";
		echo "<td class=\"subheader\" align=\"center\" width=\"10%\">$lang->filesize</td>\n";
		echo "</tr>\n";
		echo "</tr>\n";
		$query = $db->query("SELECT a.*, p.tid, p.subject, u.username FROM ".TABLE_PREFIX."attachments a LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid) ORDER BY filesize DESC LIMIT 0, 5");
		while($attachment = $db->fetch_array($query))
		{
			$bgcolor = getaltbg();
			$attachment['filesize'] = getfriendlysize($attachment['filesize']);
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\"><a href=\"../attachment.php?aid=".$attachment['aid']."\">".$attachment['filename']."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><a href=\"../showthread.php?tid=".$attachment['tid']."&pid=".$attachment['pid']."#pid".$attachment['pid']."\">".$attachment['subject']."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><a href=\"../member.php?action=proflile&uid=".$attachment['uid']."\">".$attachment['username']."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".$attachment['filesize']."</a></td>\n";
			echo "</tr>\n";
		}
		endtable();
	}
	cpfooter();
}


if($mybb->input['action'] == "modify" || !$mybb->input['action'])
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
		$type['name'] = stripslashes($type['name']);
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