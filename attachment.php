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

// Find the AID we're looking for
if($mybb->input['thumbnail'])
{
	$aid = intval($mybb->input['thumbnail']);
}
else
{
	$aid = intval($mybb->input['aid']);
}

$plugins->run_hooks("attachment_start");

$pid = intval($mybb->input['pid']);

// Select attachment data from database
if($aid)
{
	$query = $db->simple_select(TABLE_PREFIX."attachments", "*", "aid='{$aid}'");
}
else
{
	$query = $db->simple_select(TABLE_PREFIX."attachments", "*", "pid='{$pid}'");
}
$attachment = $db->fetch_array($query);
$pid = $attachment['pid'];

$post = get_post($pid);
$thread = get_thread($post['tid']);

if(!$thread['tid'] && !$mybb->input['thumbnail'])
{
	error($lang->error_invalidthread);
}
$fid = $thread['fid'];

// Get forum info
$forum = get_forum($fid);

// Permissions
$forumpermissions = forum_permissions($fid);

// No Permission page if user cannot view or download attachments in this forum (if not calling the thumbnail)
if(($forumpermissions['canview'] == "no" || $forumpermissions['candlattachments'] == "no") && !$mybb->input['thumbnail'])
{
	error_no_permission();
}

// Error if attachment is invalid or not visible
if(!$attachment['aid'] || !$attachment['attachname'] || (is_moderator($fid) == 'no' && $attachment['visible'] != 1))
{
	error($lang->error_invalidattachment);
}

if(!$mybb->input['thumbnail']) // Only increment the download count if this is not a thumbnail
{
	$attachupdate = array(
		"downloads" => $attachment['downloads']+1,
	);
	$db->update_query(TABLE_PREFIX."attachments", $attachupdate, "aid='{$attachment['aid']}'");
}
$attachment['filename'] = rawurlencode($attachment['filename']);

$plugins->run_hooks("attachment_end");

if($mybb->input['thumbnail'])
{
	$ext = get_extension($attachment['thumbnail']);
	switch($ext)
	{
		case "gif":
			$type = "image/gif";
			break;
		case "bmp":
			$type = "image/bmp";
			break;
		case "png":
			$type = "image/png";
			break;
		case "jpg":
		case "jpeg":
		case "jpe":
			$type = "image/jpeg";
			break;
		default:
			$type = "image/unknown";
			break;
	}
	header("Content-disposition: filename=$attachment[filename]");
	header("Content-type: ".$type);
	$thumb = $mybb->settings['uploadspath']."/".$attachment['thumbnail'];
	header("Content-length: ".@filesize($thumb));
	echo file_get_contents($thumb);
}
else
{
	$ext = get_extension($attachment['filename']);
	if($ext == "txt" || $ext == "htm" || $ext == "html" || $ext == "pdf")
	{
		header("Content-disposition: attachment; filename=$attachment[filename]");
	}
	else
	{
		header("Content-disposition: inline; filename=$attachment[filename]");
	}	
	header("Content-type: $attachment[filetype]");
	header("Content-length: $attachment[filesize]");
	echo file_get_contents($mybb->settings['uploadspath']."/".$attachment['attachname']);
}
?>
