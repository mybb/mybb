<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

$page->add_breadcrumb_item("System Health", "index.php?".SID."&amp;module=tools/index");

$page->output_header("System Health");

$sub_tabs['system_health'] = array(
	'title' => "System Health",
	'link' => "index.php?".SID."&amp;module=tools/stats",
	'description' => 'Here you can view information on your systems health.'
);

$page->output_nav_tabs($sub_tabs, 'system_health');

$table = new Table;
$table->construct_header("Totals", array("colspan" => 2));
$table->construct_header("Attachments", array("colspan" => 2));

$query = $db->simple_select("attachments", "COUNT(*) AS numattachs, SUM(filesize) as spaceused, SUM(downloads) as downloadsused", "visible='1' AND pid > '0'");
$attachs = $db->fetch_array($query);

$table->construct_cell("<strong>Total Database Size</strong>", array('width' => '25%'));
$table->construct_cell(get_friendly_size($db->fetch_size()), array('width' => '25%'));
$table->construct_cell("<strong>Attachment Space used</strong>", array('width' => '200'));
$table->construct_cell(get_friendly_size($attachs['spaceused']), array('width' => '200'));
$table->construct_row();

$table->construct_cell("<strong>Total Cache Size</strong>", array('width' => '25%'));
$table->construct_cell(get_friendly_size($cache->size_of()), array('width' => '25%'));
$table->construct_cell("<strong>Estimated Attachment Bandwidth Usage</strong>", array('width' => '25%'));
$table->construct_cell(get_friendly_size(round($attachs['spaceused']*$attachs['downloadsused'])), array('width' => '25%'));
$table->construct_row();

$table->construct_cell("<strong>Max Upload / POST Size</strong>", array('width' => '200'));
$table->construct_cell(@ini_get('upload_max_filesize').' / '.@ini_get('post_max_size'), array('width' => '200'));
$table->construct_cell("<strong>Average Attachment Size</strong>", array('width' => '25%'));
$table->construct_cell(get_friendly_size(round($attachs['spaceused']/$attachs['numattachs'])), array('width' => '25%'));
$table->construct_row();

$table->output("Stats");

$table->construct_header("Task");
$table->construct_header("Run Time", array("width" => 200, "class" => "align_center"));

$task_cache = $cache->read("tasks");
$nextrun = $task_cache['nextrun'];

$query = $db->simple_select("tasks", "*", "nextrun >= '{$nextrun}'", array("order_by" => "title", "order_dir" => "asc", 'limit' => 3));
while($task = $db->fetch_array($query))
{
	$task['title'] = htmlspecialchars_uni($task['title']);
	$next_run = date($mybb->settings['dateformat'], $task['nextrun']).", ".date($mybb->settings['timeformat'], $task['nextrun']);
	$table->construct_cell("<strong>{$task['title']}</strong>");
	$table->construct_cell($next_run, array("class" => "align_center"));

	$table->construct_row();
}

$table->output("Next 3 Tasks");

$backups = array();
$dir = MYBB_ADMIN_DIR.'backups/';
$handle = opendir($dir);
while(($file = readdir($handle)) !== false)
{
	if(filetype(MYBB_ADMIN_DIR.'backups/'.$file) == 'file')
	{
		$ext = get_extension($file);
		if($ext == 'gz' || $ext == 'sql')
		{
			$backups[@filemtime(MYBB_ADMIN_DIR.'backups/'.$file)] = array(
				"file" => $file,
				"time" => @filemtime(MYBB_ADMIN_DIR.'backups/'.$file),
				"type" => $ext
			);
		}
	}
}

$count = count($backups);
krsort($backups);

$table = new Table;
$table->construct_header("Name");
$table->construct_header("Backup Time", array("width" => 200, "class" => "align_center"));

$backupscnt = 0;
foreach($backups as $backup)
{
	++$backupscnt;
	
	if($backupscnt == 4)
	{
		break;
	}
	
	if($backup['time'])
	{
		$time = my_date($mybb->settings['dateformat'].", ".$mybb->settings['timeformat'], $backup['time']);
	}
	else
	{
		$time = "-";
	}
	
	$table->construct_cell("<a href=\"index.php?".SID."&amp;module=tools/backupdb&amp;action=dlbackup&amp;file={$backup['file']}\">{$backup['file']}</a>");
	$table->construct_cell($time, array("class" => "align_center"));
	$table->construct_row();
}

if($count == 0)
{
	$table->construct_cell("There are currently no backups made yet.", array('colspan' => 2));
	$table->construct_row();
}


$table->output("Existing Database Backups");

if(is_writable(MYBB_ROOT.'inc/settings.php'))
{
	$message_settings = "<span style=\"color: green;\">Writable</span>";
}
else
{
	$message_settings = "<strong><span style=\"color: #C00\">Not Writable</span></strong><br />Please CHMOD to 777.";
}

if(is_writable('.'.$mybb->settings['uploadspath']))
{
	$message_upload = "<span style=\"color: green;\">Writable</span>";
}
else
{
	$message_upload = "<strong><span style=\"color: #C00\">Not Writable</span></strong><br />Please CHMOD to 777.";
	++$errors;
}

if(is_writable('.'.$mybb->settings['avataruploadpath']))
{
	$message_avatar = "<span style=\"color: green;\">Writable</span>";
}
else
{
	$message_avatar = "<strong><span style=\"color: #C00\">Not Writable</span></strong><br />Please CHMOD to 777.";
	++$errors;
}

if(is_writable(MYBB_ROOT.'inc/languages/'))
{
	$message_language = "<span style=\"color: green;\">Writable</span>";
}
else
{
	$message_language = "<strong><span style=\"color: #C00\">Not Writable</span></strong><br />Please CHMOD to 777.";
	++$errors;
}

if(is_writable(MYBB_ROOT.$config['admin_dir'].'/backups/'))
{
	$message_backup = "<span style=\"color: green;\">Writable</span>";
}
else
{
	$message_backup = "<strong><span style=\"color: #C00\">Not Writable</span></strong><br />Please CHMOD to 777.";
	++$errors;
}


if($errors)
{
	$page->output_error("<strong><span style=\"color: #C00\">{$errors} of the required files and directories do not have proper CHMOD settings.</span></strong> Please change the CHMOD settings to the ones specified with the file below. For more information on CHMODing, see the <a href=\"http://wiki.mybboard.net/index.php/HowTo_Chmod\" target=\"_blank\">MyBB Wiki</a>.");
}
else
{
	$page->output_success("<strong><span style=\"color: green;\">All of the required files and directories have the proper CHMOD settings.</span></strong>");
}

$table = new Table;
$table->construct_header("File");
$table->construct_header("Location", array("colspan" => 2, 'width' => 250));

$table->construct_cell("<strong>Settings File</strong>");
$table->construct_cell("./inc/settings.php");
$table->construct_cell($message_settings);
$table->construct_row();

$table->construct_cell("<strong>File Uploads Directory</strong>");
$table->construct_cell($mybb->settings['uploadspath']);
$table->construct_cell($message_upload);
$table->construct_row();

$table->construct_cell("<strong>Avatar Uploads Directory</strong>");
$table->construct_cell($mybb->settings['avataruploadpath']);
$table->construct_cell($message_avatar);
$table->construct_row();

$table->construct_cell("<strong>Language Files</strong>");
$table->construct_cell("./inc/languages");
$table->construct_cell($message_language);
$table->construct_row();

$table->construct_cell("<strong>Backups Directory</strong>");
$table->construct_cell('./'.$config['admin_dir'].'/backups');
$table->construct_cell($message_backup);
$table->construct_row();

$table->output("CHMOD Files and Directories");

$page->output_footer();
?>