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


/**
 * Remove an attachment from a specific post
 *
 * @param int The post ID
 * @param string The posthash if available
 * @param int The attachment ID
 */
function remove_attachment($pid, $posthash, $aid)
{
	global $db, $mybb;
	$aid = intval($aid);
	$posthash = $db->escape_string($posthash);
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

/**
 * Remove all of the attachments from a specific post
 *
 * @param int The post ID
 * @param string The posthash if available
 */ 
function remove_attachments($pid, $posthash="")
{
	global $db, $mybb;
	$posthash = $db->escape_string($posthash);
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

/**
 * Remove any matching avatars for a specific user ID
 *
 * @param int The user ID
 * @param string A file name to be excluded from the removal
 */
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

/**
 * Upload a new avatar in to the file system
 *
 * @return array Array of errors if any, otherwise filename of successful.
 */
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
	$ext = get_extension(strtolower($avatar['name']));
	if(!preg_match("#(gif|jpg|jpeg|jpe|bmp|png)$#i", $ext)) {
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

	// Check if this is a valid image or not
	$img_dimensions = @getimagesize($mybb->settings['avataruploadpath']."/".$filename);
	if(!is_array($img_dimensions))
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// If we've got this far check dimensions
	if($mybb->settings['maxavatardims'] != "")
	{
		list($maxwidth, $maxheight) = @explode("x", $mybb->settings['maxavatardims']);
		if(($maxwidth && $img_dimensions[0] > $maxwidth) || ($maxheight && $img_dimensions[1] > $maxheight))
		{
			$ret['error'] = sprintf($lang->error_avatartoobig, $maxwidth, $maxheight);
			@unlink($mybb->settings['avataruploadpath']."/".$filename);
			return $ret;
		}
	}
	// Everything is okay so lets delete old avatars for this user
	remove_avatars($mybb->user['uid'], $filename);

	$ret = array(
		"avatar" => $mybb->settings['avataruploadpath']."/".$filename,
		"width" => intval($img_dimensions[0]),
		"height" => intval($img_dimensions[1])
	);
	return $ret;
}

/**
 * Upload an attachment in to the file system
 *
 * @param array Attachment data (as fed by PHPs $_FILE)
 * @return array Array of attachment data if successful, otherwise array of error data
 */
function upload_attachment($attachment)
{
	global $db, $theme, $templates, $posthash, $pid, $tid, $forum, $mybb, $lang;
	
	$posthash = $db->escape_string($mybb->input['posthash']);

	if(isset($attachment['error']) && $attachment['error'] != 0)
	{
		$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_detail;
		switch($attachment['error'])
		{
			case 1: // UPLOAD_ERR_INI_SIZE
				$ret['error'] .= $lang->error_uploadfailed_php1;
				break;
			case 2: // UPLOAD_ERR_FORM_SIZE
				$ret['error'] .= $lang->error_uploadfailed_php2;
				break;
			case 3: // UPLOAD_ERR_PARTIAL
				$ret['error'] .= $lang->error_uploadfailed_php3;
				break;
			case 4: // UPLOAD_ERR_NO_FILE
				$ret['error'] .= $lang->error_uploadfailed_php4;
				break;
			case 6: // UPLOAD_ERR_NO_TMP_DIR
				$ret['error'] .= $lang->error_uploadfailed_php6;
				break;
			case 7: // UPLOAD_ERR_CANT_WRITE
				$ret['error'] .= $lang->error_uploadfailed_php7;
				break;
			default:
				$ret['error'] .= sprintf($lang->error_uploadfailed_phpx, $attachment['error']);
				break;
		}
		return $ret;
	}
	if(!is_uploaded_file($attachment['tmp_name']) || empty($attachment['tmp_name']))
	{
		$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_php4;
		return $ret;
	}
	$ext = get_extension($attachment['name']);
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
			$friendlyquota = get_friendly_size($mybb->usergroup['attachquota']*1000);
			$ret['error'] = sprintf($lang->error_reachedattachquota, $friendlyquota);
			return $ret;
		}
	}

	// Check if an attachment with this name is already in the post
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE filename='".$db->escape_string($attachment['name'])."' AND (posthash='$posthash' OR (pid='$pid' AND pid!='0'))");
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
		$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_detail;
		switch($file['error'])
		{
			case 1:
				$ret['error'] .= $lang->error_uploadfailed_nothingtomove;
				break;
			case 2:
				$ret['error'] .= $lang->error_uploadfailed_movefailed;
				break;
		}
		return $ret;
	}

	// Lets just double check that it exists
	if(!file_exists($mybb->settings['uploadspath']."/".$filename))
	{
		$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_detail.$lang->error_uploadfailed_lost;
		return $ret;
	}

	// Generate the array for the insert_query
	$attacharray = array(
		"pid" => intval($pid),
		"posthash" => $posthash,
		"uid" => $mybb->user['uid'],
		"filename" => $db->escape_string($file['original_filename']),
		"filetype" => $file['type'],
		"filesize" => $file['size'],
		"attachname" => $filename,
		"downloads" => 0,
		);

	// Alls well that ends well? Lets generate a thumbnail (if image) and insert it all in to the database
	if($ext == "gif" || $ext == "png" || $ext == "jpg" || $ext == "jpeg" || $ext == "jpe")
	{
		require MYBB_ROOT."inc/functions_image.php";
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

/**
 * Actually move a file to the uploads directory
 *
 * @param array The PHP $_FILE array for the file
 * @param string The path to save the file in
 * @param string The filename for the file (if blank, current is used)
 */
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
		return $upload;
	}
	@chmod($path."/".$filename, 0777);
	$upload['filename'] = $filename;
	$upload['path'] = $path;
	$upload['type'] = $file['type'];
	$upload['size'] = $file['size'];
	return $upload;
}
?>