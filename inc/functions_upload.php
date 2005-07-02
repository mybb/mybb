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
	global $settings, $db, $mybb;
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE aid='$aid' AND (posthash='$posthash' OR (pid='$pid' AND pid!='0'))");
	$attachment = $db->fetch_array($query);
	$db->query("DELETE FROM ".TABLE_PREFIX."attachments WHERE aid='".$attachment['aid']."'");
	@unlink($settings['uploadspath']."/".$attachment['attachname']);
	if($attachment['thumbnail'])
	{
		@unlink($settings['uploadspath']."/".$attachment['thumbnail']);
	}
}

function remove_avatars($uid, $exclude="")
{
	global $settings;
	$dir = opendir($settings['avataruploadpath']);
	if($dir)
	{
		while($file = @readdir($dir))
		{
			if(preg_match("#avatar_".$uid."#", $file) && is_file($settings['avataruploadpath']."/".$file) && $file != $exclude)
			{
				@unlink($settings['avataruploadpath']."/".$file);
			}
		}

		@closedir($dir);
	}
}

function upload_avatar()
{
	global $db, $settings, $mybbuser, $mybbgroup, $lang, $_FILES;
	$avatar = $_FILES['avatarupload'];
	if(!is_uploaded_file($avatar['tmp_name']))
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}
	
	// Check we have a valid extension
	$ext = getextention(strtolower($avatar['name']));
	if(!eregi("gif|jpeg|png|jpe|jpg", $ext)) {
		$ret['error'] = $lang->error_avatartype;
		return $ret;
	}

	// Next check the file size
	if($avatat['ize'] > ($settings['avatarsize']*1024) && $settings['avatarsize'] > 0)
	{
		$ret['error'] = $lang->error_uploadsize;
		return $ret;
	}

	$filename = "avatar_".$mybbuser['uid'].".".$ext;
	$file = upload_file($avatar, $settings['avataruploadpath'], $filename);
	if($file['error'])
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Lets just double check that it exists
	if(!file_exists($settings['avataruploadpath']."/".$filename))
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// If we've got this far check dimensions
	if(preg_match("#gif|jpg|jpeg|jpe|bmp|png#i", $ext) && $settings['maxavatardims'] != "")
	{
		list($width, $height) = getimagesize($settings['avataruploadpath']."/".$filename);
		list($maxwidth, $maxheight) = explode("x", $settings['maxavatardims']);
		if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight))
		{
			$ret['error'] = sprintf($lang->error_avatartoobig, $maxwidth, $maxheight);
			@unlink($settings['avataruploadpath']."/".$filename);
			return $ret;
		}
	}

	// Everything is okay so lets delete old avatars for this user
	remove_avatars($mybbuser['uid'], $filename);

	$ret['avatar'] = $settings['avataruploadpath']."/".$filename;
	return $ret;
}

function upload_attachment($attachment)
{
	global $db, $settings, $theme, $templates, $posthash, $pid, $tid, $forum, $mybb, $mybbuser, $mybbgroup, $lang;
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
	if($mybbgroup['attachquota'] > 0)
	{
		$query = $db->query("SELECT SUM(filesize) AS ausage FROM ".TABLE_PREFIX."attachments WHERE uid='".$mybb['uid']."'");
		$usage = $db->fetch_array($query);
		$usage = $usage['ausage']+$attachment['size'];
		if($usage > ($mybbgroup['attachquota']*1000))
		{
			$friendlyquota = getfriendlysize($mybbgroup['attachquota']*1000);
			$ret['error'] = sprintf($lang->error_reachedattachquota, $friendlyquota);
			return $ret;
		}
	}

	// Check if an attachment with this name is already in the post
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE filename='".$attachment['name']."' AND (posthash='$posthash' OR (pid='$pid' AND pid!='0'))");
	$prevattach = $db->fetch_array($query);
	if($prevattach['aid'])
	{
		$ret['error'] = $lang->error_alreadyuploaded;
		return $ret;
	}

	// All seems to be good, lets move the attachment!
	$filename = "post_".$mybb['uid']."_".time().".attach";
	$file = upload_file($attachment, $settings['uploadspath'], $filename);
	if($file['error'])
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Lets just double check that it exists
	if(!file_exists($settings['uploadspath']."/".$filename))
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Alls well that ends well? Lets generate a thumbnail (if image) and insert it all in to the database
	if($ext == "gif" || $ext == "png" || $ext == "jpg" || $ext == "jpeg" || $ext == "jpe")
	{
		require "functions_image.php";
		$thumbname = str_replace(".attach", "_thumb.$ext", $filename);
		$thumbnail = generate_thumbnail($settings['uploadspath']."/".$filename, $settings['uploadspath'], $thumbname, $settings['attachthumbh'], $settings['attachthumbw']);
		if($thumbnail['filename'])
		{
			$thumbadd = ",thumbnail";
			$thumbadd2 = ",'".$thumbnail['filename']."'";
		}
	}
	if($forum['modattachments'] == "yes" && $mybbgroup['cancp'] != "yes")
	{
		$attvisible = 0;
	}
	else
	{
		$attvisible = 1;
	}
	$db->query("INSERT INTO ".TABLE_PREFIX."attachments (aid,pid,posthash,uid,filename,filetype,filesize,attachname,downloads,visible$thumbadd) VALUES ('','$pid','$posthash','".$mybb['uid']."','".$file['original_filename']."','".$file['type']."','".$file['size']."','$filename','0','$attvisible'$thumbadd2)");
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
	$moved = @move_uploaded_file($file['tmp_name'], $path."/".$filename);
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