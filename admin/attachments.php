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

require_once "./global.php";

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
	case "orphans":
		addacpnav($lang->nav_attachment_manager, "attachments.php?".SID."&amp;action=search");
		addacpnav($lang->nav_attachment_results);
		break;
	case "add":
		addacpnav($lang->nav_attachtypes, "attachments.php?".SID);
		addacpnav($lang->nav_add_attachtype);
		break;
	case "delete":
		addacpnav($lang->nav_attachtypes, "attachments.php?".SID);
		addacpnav($lang->nav_delete_attachtype);
	case "edit":
		addacpnav($lang->nav_attachtypes, "attachments.php?".SID);
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

$plugins->run_hooks("admin_attachments_start");

if($mybb->input['action'] == "do_add")
{
	// add new type to database
	if(($mybb->input['extension'] || $mybb->input['mimetype']) && $mybb->input['maxsize'])
	{
		$sqlarray = array(
			"mimetype" => $db->escape_string($mybb->input['mimetype']),
			"extension" => $db->escape_string($mybb->input['extension']),
			"maxsize" => $db->escape_string($mybb->input['maxsize']),
			"icon" => $db->escape_string($mybb->input['icon']),
		);
		$plugins->run_hooks("admin_attachments_do_add");
		$db->insert_query("attachtypes", $sqlarray);
		$cache->update_attachtypes();
		cpredirect("attachments.php?".SID, $lang->type_added);
	}
	else
	{
		cperror($lang->type_add_missing_fields);
	}
}

if($mybb->input['action'] == "do_delete")
{
	// remove type from database
	if($mybb->input['deletesubmit'])
	{
		$plugins->run_hooks("admin_attachments_do_delete");
		$db->delete_query("attachtypes", "atid='".intval($mybb->input['atid'])."'");
		$cache->update_attachtypes();
		cpredirect("attachments.php?".SID, $lang->type_deleted);
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
			"mimetype" => $db->escape_string($mybb->input['mimetype']),
			"extension" => $db->escape_string($mybb->input['extension']),
			"maxsize" => $db->escape_string($mybb->input['maxsize']),
			"icon" => $db->escape_string($mybb->input['icon']),
		);
		$plugins->run_hooks("admin_attachments_do_edit");
		$db->update_query("attachtypes", $sqlarray, "atid='".$sqlarray['atid']."'");
		$cache->update_attachtypes();
		cpredirect("attachments.php?".SID, $lang->type_updated);
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
	if($mybb->input['uid'])
	{
		$uid = intval($mybb->input['uid']);
		$sql .= " AND u.uid='$uid'";
	}
	if($mybb->input['username'])
	{
		$username = $db->escape_string($mybb->input['username']);
		$sql .= " AND u.username LIKE '%$username%'";
	}
	if($mybb->input['filename'])
	{
		$filename = $db->escape_string($mybb->input['filename']);
		$sql .= " AND a.filename LIKE '%$filename%'";
	}
	if($mybb->input['mimetype'])
	{
		$mimetype = $db->escape_string($mybb->input['mimetype']);
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
		$sizeless = $db->escape_string($mybb->input['sizeless']);
		$sizeless *= 1024;
		$sql .= " AND a.filesize < '$sizeless'";
	}
	if($mybb->input['sizemore'])
	{
		$sizemore = $db->escape_string($mybb->input['sizemore']);
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
	switch($mybb->input['sortfield'])
	{
		case 'filesize':
			$order_by = 'a.filesize';
			break;
		case 'dateline':
			$order_by = 'p.dateline';
			break;
		case 'username':
			$order_by = 'u.username';
			break;
		case 'forumname':
			$order_by = 'f.name';
			break;
		case 'filename':
		default:
			$order_by = 'a.filename';
			break;
	}
	if($mybb->input['sortdir'] == 'desc')
	{
		$sort_dir = 'DESC';
	}
	else
	{
		$sort_dir = 'ASC';
	}
	$plugins->run_hooks("admin_attachments_do_search");
	// Get attachments from database list
	$query = $db->query("
		SELECT a.*, p.tid, p.fid, t.subject, f.name, u.uid, u.username AS userusername
		FROM ".TABLE_PREFIX."attachments a 
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid) 
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) 
		LEFT JOIN ".TABLE_PREFIX."forums f ON (p.fid=f.fid) 
		LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid) 
		{$sql} 
		ORDER BY {$order_by} {$sort_dir}
	");
	$num_results = $db->num_rows($query);

	// Get attachments filenames from filesystem
	if($uploads = opendir(MYBB_ROOT.$mybb->settings['uploadspath']))
	{
		while(false !== ($file = readdir($uploads)))
		{
			if(my_substr($file, -7, 7) == ".attach")
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
		
		if($result['userusername'])
		{
			$result['username'] = $result['userusername'];
		}
		
		$filesize = get_friendly_size($result['filesize']);

		echo "<tr>\n";
		echo "<td class=\"$altbg\" align=\"center\"><input type=\"checkbox\" name=\"check[$result[aid]]\" value=\"$result[aid]\"></td>\n";
		echo "<td class=\"$altbg\"><a href=\"../attachment.php?aid=$result[aid]\">$filename</a></td>\n";
		echo "<td class=\"$altbg\">".build_profile_link($result['username'], $result['uid'])."</td>\n";
		echo "<td class=\"$altbg\"><a href=\"../".get_forum_link($result['fid'])."\">$result[name]</a> &raquo; <a href=\"../".get_post_link($result['pid'])."#pid$result[pid]\">$result[subject]</a></td>\n";
		echo "<td class=\"$altbg\">$result[filetype]</td>\n";
		echo "<td class=\"$altbg\">$filesize</td>\n";
		echo "<td class=\"$altbg\">$result[downloads]</td>\n";
		echo "</tr>\n";

		$altbg = getaltbg();
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
		$plugins->run_hooks("admin_attachments_do_search_delete");
		foreach($mybb->input['check'] as $aid)
		{
			$db->delete_query("attachments", "aid='".intval($aid)."'");
		}
		cpredirect("attachments.php?".SID."&action=search", $lang->attachs_deleted);
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
	$query = $db->simple_select("attachments", "attachname");
	$db_list = array();
	while($file = $db->fetch_array($query))
	{
		$db_list[] = $file['attachname'];
	}
	// Get attachments filenames from filesystem
	$orphan_files = array();
	if($uploads = opendir(MYBB_ROOT.$mybb->settings['uploadspath']))
	{
		while(false !== ($file = readdir($uploads)))
		{
			if(my_substr($file, -7, 7) == ".attach" && !in_array($file, $db_list))
			{
				$orphan_files[] = $file;
			}
		}
		closedir($uploads);
	}
	$plugins->run_hooks("admin_attachments_orphans");

	if(count($orphan_files) < 1)
	{
		cpmessage($lang->no_orphans);
	}

	cpheader();
	startform("attachments.php", "", "do_orphan_delete");
	starttable();
	tableheader($lang->orphan_search_results, "", "3");
	makelabelcode($lang->orphan_search_results_note, '', '3');
	echo "<tr>\n";
	echo "<td class=\"subheader\">$lang->delete</td>\n";
	echo "<td class=\"subheader\">$lang->filename</td>\n";
	echo "<td class=\"subheader\">$lang->filesize</td>\n";
	echo "</tr>\n";

	$bgcolor = getaltbg();
	foreach($orphan_files as $filename)
	{
		$filesize = get_friendly_size(filesize(MYBB_ROOT.$mybb->settings['uploadspath']."/".$filename));

		echo "<tr>\n";
		echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"checkbox\" name=\"check[]\" value=\"$filename\" /></td>\n";
		echo "<td class=\"$bgcolor\">$filename</td>\n";
		echo "<td class=\"$bgcolor\">$filesize</td>\n";
		echo "</tr>\n";

		$bgcolor = getaltbg();
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
		$plugins->run_hooks("admin_do_orphan_delete");
		foreach($mybb->input['check'] as $filename)
		{
			if(file_exists(MYBB_ROOT.$mybb->settings['uploadspath']."/".basename($filename)))
			{
				if(!@unlink(MYBB_ROOT.$mybb->settings['uploadspath']."/".basename($filename)))
				{
					$error = true;
				}
			}
		}
		if($error)
		{
			cperror($lang->problem_deleting);
		}
		else
		{
			cpredirect("attachments.php?".SID."&action=orphans", $lang->attachs_deleted);
		}
	}
	else
	{
		cperror($lang->attachs_noneselected);
	}
}

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_attacments_add");
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
	$plugins->run_hooks("admin_attachments_search");
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
	tablesubheader($lang->sort_options);
	$sort_field_options = array(
		'filename' => $lang->sort_field_filename,
		'filesize' => $lang->sort_field_filesize,
		'dateline' => $lang->sort_field_dateline,
		'username' => $lang->sort_field_username,
		'forumname' =>  $lang->sort_field_forumname
	);
	makeselectcode_array($lang->sort_field, 'sortfield', $sort_field_options, 'filename');
	$sort_dir_options = array(
		'asc' => $lang->sort_dir_asc,
		'desc' => $lang->sort_dir_desc
	);
	makeselectcode_array($lang->sort_dir, 'sortdir', $sort_dir_options, 'asc');
	endtable();
	endform($lang->search, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "edit")
{
	if($mybb->input['delete'])
	{
		$mybb->input['action'] = "delete";
	}
	else
	{
		// form for editing an attachment type
		$atid = intval($mybb->input['atid']);
		$options = array(
			"limit" => "1"
		);
		$query = $db->simple_select("attachtypes", "*", "atid='".$atid."'", $options);
		$type = $db->fetch_array($query);
		$plugins->run_hooks("admin_attachments_edit");
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
	$query = $db->simple_select("attachtypes", "name", "atid='".$atid."'");
	$plugins->run_hooks("admin_attachments_delete");
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
	makelabelcode("<div align=\"center\">$lang->delete_confirm<br /><br />$yes$no</div>", "");
	endtable();
	endform();
	cpfooter();
}
if($mybb->input['action'] == "stats")
{
	$plugins->run_hooks("admin_attachments_stats");
	cpheader();
	
	$query = $db->simple_select("attachments", "COUNT(*) AS attachments, SUM(filesize) AS totalsize, SUM(downloads) AS downloads");
	$stats = $db->fetch_array($query);
	if(!$stats['downloads'])
	{
		$stats['downloads'] = "0";
	}
	starttable();
	tableheader($lang->overall_attachment_statistics, "");
	makelabelcode($lang->total_attachments, $stats['attachments']);
	makelabelcode($lang->total_size, get_friendly_size($stats['totalsize']));
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
		
		$options = array(
			"order_by" => "downloads",
			"order_dir" => "DESC",
			"limit_start" => "0",
			"limit" => "5"
		);
		
		$query = $db->simple_select("attachments a LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)", "a.*, p.tid, p.subject, p.username, u.username AS userusername", "", $options);
		while($attachment = $db->fetch_array($query))
		{
			if($attachment['userusername'])
			{
				$attachment['username'] = $attachment['userusername'];
			}
			$bgcolor = getaltbg();
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\"><a href=\"../attachment.php?aid=".$attachment['aid']."\">".$attachment['filename']."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><a href=\"../".get_post_link($attachment['pid'])."#pid".$attachment['pid']."\">".$attachment['subject']."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".build_profile_link($attachment['username'], $attachment['uid'])."</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".$attachment['downloads']."</td>\n";
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
		
		$options = array(
			"order_by" => "filesize",
			"order_dir" => "DESC",
			"limit_start" => "0",
			"limit" => "5"
		);
		
		$query = $db->simple_select("attachments a LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)", "a.*, p.tid, p.subject, u.username AS userusername, p.username", "", $options);
		while($attachment = $db->fetch_array($query))
		{
			if($attachment['userusername'])
			{
				$attachment['username'] = $attachment['userusername'];
			}			
			$bgcolor = getaltbg();
			$attachment['filesize'] = get_friendly_size($attachment['filesize']);
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\"><a href=\"../attachment.php?aid=".$attachment['aid']."\">".$attachment['filename']."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><a href=\"../".get_post_link($attachment['pid'])."#pid".$attachment['pid']."\">".$attachment['subject']."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><a href=\"".build_profile_link($attachment['username'], $attachment['uid'])."\">".$attachment['username']."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".$attachment['filesize']."</td>\n";
			echo "</tr>\n";
		}
		endtable();

		// Top uploaders
		starttable();
		tableheader($lang->top_uploaders, "", 3);
		echo "<tr>\n";
		echo "<td class=\"subheader\" width=\"50%\">$lang->username</td>\n";
		echo "<td class=\"subheader\" width=\"50%\">$lang->total_size</td>\n";
		echo "</tr>\n";

		$query = $db->query("
			SELECT a.*, u.uid, SUM(a.filesize) as totalsize
			FROM ".TABLE_PREFIX."attachments a  
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
			GROUP BY a.uid
			ORDER BY totalsize DESC
			LIMIT 0, 5
		");
 
		while($user = $db->fetch_array($query))
		{
			$bgcolor = getaltbg();
			$user['totalsize'] = get_friendly_size($user['totalsize']);
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\">".build_profile_link($user['username'], $user['uid'])."</td>\n";
			echo "<td class=\"$bgcolor\"><a href=\"attachments.php?".SID."&amp;action=do_search&amp;username=".urlencode($user['username'])."\">".$user['totalsize']."</a></td>\n";
			echo "</tr>\n";
		}
		endtable();
	}
	cpfooter();
}

if($mybb->input['action'] == "modify" || !$mybb->input['action'])
{
	$plugins->run_hooks("admin_attachments_modify");
	// list all attachment types so user can pick one to edit/delete
	cpheader();
	$hopto[] = "<input type=\"button\" value=\"$lang->add_attach_type\" onclick=\"hopto('attachments.php?".SID."&amp;action=add');\" class=\"hoptobutton\" />";
	makehoptolinks($hopto);
	starttable();
	tableheader($lang->attachment_types, "", "6");
	echo "<tr>\n";
	echo "<td class=\"subheader\" colspan=\"2\">$lang->type_extension</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->type_mimetype</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->type_max_size</td>\n";
	echo "<td class=\"subheader\" align=\"center\" colspan=\"2\">$lang->controls</td>\n";
	echo "</tr>\n";
	$options = array(
		"order_by" => "name"
	);
	$query = $db->simple_select("attachtypes", "*", "", $options);
	while($type = $db->fetch_array($query))
	{
		$type['name'] = stripslashes($type['name']);
		$size = get_friendly_size($type['maxsize']*1024);
		if($type['icon'])
		{
			$icon = "<img src=\"../$type[icon]\" alt=\"\" />";
		}
		else
		{
			$icon = "&nbsp;";
		}
		$bgcolor = getaltbg();
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"1\">$icon</td>";
		echo "<td class=\"$bgcolor\" width=\"25%\"><b>.$type[extension]</b></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"25%\">$type[mimetype]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"25%\">$size</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"1\">";
		startform("attachments.php", "", "edit");
		makehiddencode("atid", $type['atid']);
    echo "<input type=\"submit\" name=\"edit\" value=\"$lang->type_edit\" class=\"submitbutton\" />";
    endform();
    echo "</td>";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"1\">";
		startform("attachments.php", "", "edit");
		makehiddencode("atid", $type['atid']);
    echo "<input type=\"submit\" name=\"delete\" value=\"$lang->type_delete\" class=\"submitbutton\" />";
    endform();
    echo "</td>";
		echo "</tr>\n";
		
	}
	endtable();
	cpfooter();
}
?>
