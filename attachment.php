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

if($aid)
{
	$query = $db->query("
		SELECT * FROM ".TABLE_PREFIX."attachments
		WHERE aid='$aid'
	");
}
else
{
	$query = $db->query("
		SELECT * FROM ".TABLE_PREFIX."attachments
		WHERE pid='$pid'
	");
}
$attachment = $db->fetch_array($query);
$pid = $attachment['pid'];

if(!$tid)
{
	$query = $db->query("
		SELECT tid
		FROM ".TABLE_PREFIX."posts
		WHERE pid='$pid'
	");
	$post = $db->fetch_array($query);
	$tid = $post['tid'];
}

$query = $db->query("
	SELECT *
	FROM ".TABLE_PREFIX."threads
	WHERE tid='$tid'
");
$thread = $db->fetch_array($query);
if(!$thread['tid'] && !$mybb->input['thumbnail'])
{
	error($lang->error_invalidthread);
}
$fid = $thread['fid'];

$forumpermissions = forum_permissions($fid);

if(($forumpermissions['canview'] == "no" || $forumpermissions['candlattachments'] == "no") && !$mybb->input['thumbnail'])
{
	nopermission();
}

if(!$attachment['aid'] || !$attachment['attachname'])
{
	error($lang->error_invalidattachment);
}
if(!$mybb->input['thumbnail']) // Only increment the download count if this is not a thumbnail
{
	$db->query("
		UPDATE ".TABLE_PREFIX."attachments
		SET downloads=downloads+1
		WHERE aid='$attachment[aid]'
	");
}
$attachment['filename'] = rawurlencode($attachment['filename']);

$plugins->run_hooks("attachment_end");

header("Content-disposition: filename=$attachment[filename]");
if($mybb->input['thumbnail'])
{
	$ext = getextention($attachment['thumbnail']);
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
	header("Content-type: ".$type);
	$thumb = $settings['uploadspath']."/".$attachment['thumbnail'];
	header("Content-length: ".@filesize($thumb));
	echo file_get_contents($thumb);
}
else
{
	header("Content-type: $attachment[filetype]");
	header("Content-length: $attachment[filesize]");
	echo file_get_contents($settings['uploadspath']."/".$attachment['attachname']);
}
?>
