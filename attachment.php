<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'attachment.php');

require_once "./global.php";

if($mybb->settings['enableattachments'] != 1)
{
	error($lang->attachments_disabled);
}

// Find the AID we're looking for
if(isset($mybb->input['thumbnail']))
{
	$aid = $mybb->get_input('thumbnail', MyBB::INPUT_INT);
}
else
{
	$aid = $mybb->get_input('aid', MyBB::INPUT_INT);
}

$pid = $mybb->get_input('pid', MyBB::INPUT_INT);

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

$plugins->run_hooks("attachment_start");

if(!$attachment)
{
	error($lang->error_invalidattachment);
}

if($attachment['thumbnail'] == '' && isset($mybb->input['thumbnail']))
{
	error($lang->error_invalidattachment);
}

$pid = $attachment['pid'];

// Don't check the permissions on preview
if($pid || $attachment['uid'] != $mybb->user['uid'])
{
	$post = get_post($pid);
	$thread = get_thread($post['tid']);

	if(!$thread && !isset($mybb->input['thumbnail']))
	{
		error($lang->error_invalidthread);
	}
	$fid = $thread['fid'];

	// Get forum info
	$forum = get_forum($fid);

	// Permissions
	$forumpermissions = forum_permissions($fid);

	if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']) || ($forumpermissions['candlattachments'] == 0 && !$mybb->input['thumbnail']))
	{
		error_no_permission();
	}

	// Error if attachment is invalid or not visible
	if(!$attachment['attachname'] || (!is_moderator($fid, "canviewunapprove") && ($attachment['visible'] != 1 || $thread['visible'] != 1 || $post['visible'] != 1)))
	{
		error($lang->error_invalidattachment);
	}
}

if(!isset($mybb->input['thumbnail'])) // Only increment the download count if this is not a thumbnail
{
	$attachupdate = array(
		"downloads" => $attachment['downloads']+1,
	);
	$db->update_query("attachments", $attachupdate, "aid='{$attachment['aid']}'");
}

// basename isn't UTF-8 safe. This is a workaround.
$attachment['filename'] = ltrim(basename(' '.$attachment['filename']));

$plugins->run_hooks("attachment_end");

if(isset($mybb->input['thumbnail']))
{
	if(!file_exists($mybb->settings['uploadspath']."/".$attachment['thumbnail']))
	{
		error($lang->error_invalidattachment);
	}

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
	$handle = fopen($thumb, 'rb');
	while(!feof($handle))
	{
		echo fread($handle, 8192);
	}
	fclose($handle);
}
else
{
	if(!file_exists($mybb->settings['uploadspath']."/".$attachment['attachname']))
	{
		error($lang->error_invalidattachment);
	}

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
	$handle = fopen($mybb->settings['uploadspath']."/".$attachment['attachname'], 'rb');
	while(!feof($handle))
	{
		echo fread($handle, 8192);
	}
	fclose($handle);
}
