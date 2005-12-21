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

function remove_attachment($pid, $posthash, $aid)
{
	global $db, $mybb;
	if($posthash != "" && !$pid)
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE aid='$aid' AND posthash='$posthash'");
		$attachment = $db->fetch_array($query);
	}
	else
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE aid='$aid' AND pid='$pid'");
		$attachment = $db->fetch_array($query);
	}
	$db->query("DELETE FROM ".TABLE_PREFIX."attachments WHERE aid='".$attachment['aid']."'");
	@unlink($mybb->settings['uploadspath']."/".$attachment['attachname']);
	if($attachment['thumbnail'])
	{
		@unlink($mybb->settings['uploadspath']."/".$attachment['thumbnail']);
	}
}

function remove_attachments($pid, $posthash="")
{
	global $db, $mybb;
	if($posthash != "" && !$pid)
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE posthash='$posthash'");
	}
	else
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE pid='$pid'");
	}
	while($attachment = $db->fetch_array($query))
	{
		$db->query("DELETE FROM ".TABLE_PREFIX."attachments WHERE aid='".$attachment['aid']."'");
		@unlink($mybb->settings['uploadspath']."/".$attachment['attachname']);
		if($attachment['thumbnail'])
		{
			@unlink($mybb->settings['uploadspath']."/".$attachment['thumbnail']);
		}
	}
}

function remove_avatars($uid, $exclude="")
{
	global $mybb;
	$dir = opendir($mybb->settings['avataruploadpath']);
	if($dir)
	{
		while($file = @readdir($dir))
		{
			if(preg_match("#avatar_".$uid."\.#", $file) && is_file($mybb->settings['avataruploadpath']."/".$file) && $file != $exclude)
			{
				@unlink($mybb->settings['avataruploadpath']."/".$file);
			}
		}

		@closedir($dir);
	}
}

function upload_avatar()
{
	global $db, $mybb, $lang, $_FILES;
	$avatar = $_FILES['avatarupload'];
	if(!is_uploaded_file($avatar['tmp_name']))
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}
	
	// Check we have a valid extension
	$ext = getextention(strtolower($avatar['name']));
	if(!preg_match("#[gif|jpg|jpeg|jpe|bmp|png]$#i", $ext)) {
		$ret['error'] = $lang->error_avatartype;
		return $ret;
	}

	// Next check the file size
	if($avatar['size'] > ($mybb->settings['avatarsize']*1024) && $mybb->settings['avatarsize'] > 0)
	{
		$ret['error'] = $lang->error_uploadsize;
		return $ret;
	}

	$filename = "avatar_".$mybb->user['uid'].".".$ext;
	$file = upload_file($avatar, $mybb->settings['avataruploadpath'], $filename);
	if($file['error'])
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Lets just double check that it exists
	if(!file_exists($mybb->settings['avataruploadpath']."/".$filename))
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// If we've got this far check dimensions
	if(preg_match("#gif|jpg|jpeg|jpe|bmp|png#i", $ext) && $mybb->settings['maxavatardims'] != "")
	{
		list($width, $height) = @getimagesize($mybb->settings['avataruploadpath']."/".$filename);
		list($maxwidth, $maxheight) = @explode("x", $mybb->settings['maxavatardims']);
		if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight))
		{
			$ret['error'] = sprintf($lang->error_avatartoobig, $maxwidth, $maxheight);
			@unlink($mybb->settings['avataruploadpath']."/".$filename);
			return $ret;
		}
	}

	// Everything is okay so lets delete old avatars for this user
	remove_avatars($mybb->user['uid'], $filename);

	$ret['avatar'] = $mybb->settings['avataruploadpath']."/".$filename;
	return $ret;
}

function upload_attachment($attachment)
{
	global $db, $theme, $templates, $posthash, $pid, $tid, $forum, $mybb, $lang;
	
	$posthash = addslashes($mybb->input['posthash']);

	if(!is_uploaded_file($attachment['tmp_name']))
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}
	$ext = getextention($attachment['name']);
	// Check if we have a valid extension
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachtypes WHERE extension='$ext'");
	$attachtype = $db->fetch_array($query);
	if(!$attachtype['atid'])
	{
		$ret['error'] = $lang->error_attachtype;
		return $ret;
	}
	// Check the size
	if($attachment['size'] > $attachtype['maxsize']*1024 && $attachtype['maxsize'] != "")
	{
		$ret['error'] = sprintf($lang->error_attachsize, $attachtype['maxsize']);
		return $ret;
	}

	// Double check attachment space usage
	if($mybb->usergroup['attachquota'] > 0)
	{
		$query = $db->query("SELECT SUM(filesize) AS ausage FROM ".TABLE_PREFIX."attachments WHERE uid='".$mybb->user['uid']."'");
		$usage = $db->fetch_array($query);
		$usage = $usage['ausage']+$attachment['size'];
		if($usage > ($mybb->usergroup['attachquota']*1000))
		{
			$friendlyquota = getfriendlysize($mybb->usergroup['attachquota']*1000);
			$ret['error'] = sprintf($lang->error_reachedattachquota, $friendlyquota);
			return $ret;
		}
	}

	// Check if an attachment with this name is already in the post
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE filename='".addslashes($attachment['name'])."' AND (posthash='$posthash' OR (pid='$pid' AND pid!='0'))");
	$prevattach = $db->fetch_array($query);
	if($prevattach['aid'])
	{
		$ret['error'] = $lang->error_alreadyuploaded;
		return $ret;
	}

	// All seems to be good, lets move the attachment!
	$filename = "post_".$mybb->user['uid']."_".time().".attach";
	$file = upload_file($attachment, $mybb->settings['uploadspath'], $filename);
	if($file['error'])
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Lets just double check that it exists
	if(!file_exists($mybb->settings['uploadspath']."/".$filename))
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Generate the array for the insert_query
	$attacharray = array(
		"pid" => $pid,
		"posthash" => $posthash,
		"uid" => $mybb->user['uid'],
		"filename" => addslashes($file['original_filename']),
		"filetype" => $file['type'],
		"filesize" => $file['size'],
		"attachname" => $filename,
		"downloads" => 0,
		);

	// Alls well that ends well? Lets generate a thumbnail (if image) and insert it all in to the database
	if($ext == "gif" || $ext == "png" || $ext == "jpg" || $ext == "jpeg" || $ext == "jpe")
	{
		require "functions_image.php";
		$thumbname = str_replace(".attach", "_thumb.$ext", $filename);
		$thumbnail = generate_thumbnail($mybb->settings['uploadspath']."/".$filename, $mybb->settings['uploadspath'], $thumbname, $mybb->settings['attachthumbh'], $mybb->settings['attachthumbw']);
		if($thumbnail['filename'])
		{
			$attacharray['thumbnail'] = $thumbnail['filename'];
		}
		elseif($thumbnail['code'] == 4)
		{
			$attacharray['thumbnail'] = "SMALL";
		}
	}
	if($forum['modattachments'] == "yes" && $mybb->usergroup['cancp'] != "yes")
	{
		$attacharray['visible'] = 0;
	}
	else
	{
		$attacharray['visible'] = 1;
	}
	
	$db->insert_query(TABLE_PREFIX."attachments", $attacharray);
	
	$aid = $db->insert_id();
	$ret['aid'] = $aid;
	return $ret;
}

function upload_file($file, $path, $filename="")
{
	if($file['name'] == "" || $file['name'] == "none" || $file['size'] < 1)
	{
		$upload['error'] = 1;
		return $upload;
	}

	if(!$filename)
	{
		$filename = $file['name'];
	}
	$upload['original_filename'] = preg_replace("#/$#", "", $file['name']); // Make the filename safe
	$filename = preg_replace("#/$#", "", $filename); // Make the filename safe
	$moved = move_uploaded_file($file['tmp_name'], $path."/".$filename);
	if(!$moved)
	{
		$upload['error'] = 2;
		return;
	}
	@chmod($path."/".$filename, 0777);
	$upload['filename'] = $filename;
	$upload['path'] = $path;
	$upload['type'] = $file['type'];
	$upload['size'] = $file['size'];
	return $upload;
}
?>