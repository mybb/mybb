<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'attachment.php');

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
	$query = $db->simple_select("attachments", "*", "aid='{$aid}'");
}
else
{
	$query = $db->simple_select("attachments", "*", "pid='{$pid}'");
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

if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || ($forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']) || ($forumpermissions['candlattachments'] == 0 && !$mybb->input['thumbnail']))
{
	error_no_permission();
}

// Error if attachment is invalid or not visible
if(!$attachment['aid'] || !$attachment['attachname'] || (!is_moderator($fid) && $attachment['visible'] != 1))
{
	error($lang->error_invalidattachment);
}

if(!$mybb->input['thumbnail']) // Only increment the download count if this is not a thumbnail
{
	$attachupdate = array(
		"downloads" => $attachment['downloads']+1,
	);
	$db->update_query("attachments", $attachupdate, "aid='{$attachment['aid']}'");
}

// basename isn't UTF-8 safe. This is a workaround.
$attachment['filename'] = ltrim(basename(' '.$attachment['filename']));

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
	
	header("Content-disposition: filename=\"{$attachment['filename']}\"");
	header("Content-type: ".$type);
	$thumb = $mybb->settings['uploadspath']."/".$attachment['thumbnail'];
	header("Content-length: ".@filesize($thumb));
	echo file_get_contents($thumb);
}
else
{
	$ext = get_extension($attachment['filename']);
	
	switch($attachment['filetype'])
	{
		case "application/pdf":
		case "image/bmp":
		case "image/gif":
		case "image/jpeg":
		case "image/pjpeg":
		case "image/png":
		case "text/plain":
			header("Content-type: {$attachment['filetype']}");
			$disposition = "inline";
			break;

		default:
			$filetype = $attachment['filetype'];

			if(!$filetype)
			{
				$filetype = 'application/force-download';
			}

			header("Content-type: {$filetype}");
			$disposition = "attachment";
	}

	if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "msie") !== false)
	{
		header("Content-disposition: attachment; filename=\"{$attachment['filename']}\"");
	}
	else
	{
		header("Content-disposition: {$disposition}; filename=\"{$attachment['filename']}\"");
	}
	
	if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "msie 6.0") !== false)
	{
		header("Expires: -1");
	}
	
	header("Content-length: {$attachment['filesize']}");
	header("Content-range: bytes=0-".($attachment['filesize']-1)."/".$attachment['filesize']); 
	echo file_get_contents($mybb->settings['uploadspath']."/".$attachment['attachname']);
}
?>