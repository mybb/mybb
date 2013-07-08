<?php
/**
 * MyBB 1.8
 * Copyright 2013 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
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
	global $db, $mybb, $plugins;
	$aid = intval($aid);
	$posthash = $db->escape_string($posthash);
	if($posthash != "")
	{
		$query = $db->simple_select("attachments", "aid, attachname, thumbnail, visible", "aid='{$aid}' AND posthash='{$posthash}'");
		$attachment = $db->fetch_array($query);
	}
	else
	{
		$query = $db->simple_select("attachments", "aid, attachname, thumbnail, visible", "aid='{$aid}' AND pid='{$pid}'");
		$attachment = $db->fetch_array($query);
	}

	$plugins->run_hooks("remove_attachment_do_delete", $attachment);

	$db->delete_query("attachments", "aid='{$attachment['aid']}'");

	if(defined('IN_ADMINCP'))
	{
	    $uploadpath = '../'.$mybb->settings['uploadspath'];
	}
	else
	{
	    $uploadpath = $mybb->settings['uploadspath'];
	}

	// Check if this attachment is referenced in any other posts. If it isn't, then we are safe to delete the actual file.
	$query = $db->simple_select("attachments", "COUNT(aid) as numreferences", "attachname='".$db->escape_string($attachment['attachname'])."'");
	if($db->fetch_field($query, "numreferences") == 0)
	{
		@unlink($uploadpath."/".$attachment['attachname']);
		if($attachment['thumbnail'])
		{
			@unlink($uploadpath."/".$attachment['thumbnail']);
		}

		$date_directory = explode('/', $attachment['attachname']);
		if(@is_dir($uploadpath."/".$date_directory[0]))
		{
			@rmdir($uploadpath."/".$date_directory[0]);
		}
	}

	if($attachment['visible'] == 1 && $pid)
	{
		$post = get_post($pid);
		update_thread_counters($post['tid'], array("attachmentcount" => "-1"));
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
	global $db, $mybb, $plugins;

	if($pid)
	{
		$post = get_post($pid);
	}
	$posthash = $db->escape_string($posthash);
	if($posthash != "" && !$pid)
	{
		$query = $db->simple_select("attachments", "*", "posthash='$posthash'");
	}
	else
	{
		$query = $db->simple_select("attachments", "*", "pid='$pid'");
	}

	if(defined('IN_ADMINCP'))
	{
	    $uploadpath = '../'.$mybb->settings['uploadspath'];
	}
	else
	{
	    $uploadpath = $mybb->settings['uploadspath'];
	}

	$num_attachments = 0;
	while($attachment = $db->fetch_array($query))
	{
		if($attachment['visible'] == 1)
		{
			$num_attachments++;
		}

		$plugins->run_hooks("remove_attachments_do_delete", $attachment);

		$db->delete_query("attachments", "aid='".$attachment['aid']."'");

		// Check if this attachment is referenced in any other posts. If it isn't, then we are safe to delete the actual file.
		$query2 = $db->simple_select("attachments", "COUNT(aid) as numreferences", "attachname='".$db->escape_string($attachment['attachname'])."'");
		if($db->fetch_field($query2, "numreferences") == 0)
		{
			@unlink($uploadpath."/".$attachment['attachname']);
			if($attachment['thumbnail'])
			{
				@unlink($uploadpath."/".$attachment['thumbnail']);
			}

			$date_directory = explode('/', $attachment['attachname']);
			if(@is_dir($uploadpath."/".$date_directory[0]))
			{
				@rmdir($uploadpath."/".$date_directory[0]);
			}
		}
	}

	if($post['tid'])
	{
		update_thread_counters($post['tid'], array("attachmentcount" => "-{$num_attachments}"));
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
	global $mybb, $plugins;

	if(defined('IN_ADMINCP'))
	{
		$avatarpath = '../'.$mybb->settings['avataruploadpath'];
	}
	else
	{
		$avatarpath = $mybb->settings['avataruploadpath'];
	}

	$dir = opendir($avatarpath);
	if($dir)
	{
		while($file = @readdir($dir))
		{
			$plugins->run_hooks("remove_avatars_do_delete", $file);

			if(preg_match("#avatar_".$uid."\.#", $file) && is_file($avatarpath."/".$file) && $file != $exclude)
			{
				@unlink($avatarpath."/".$file);
			}
		}

		@closedir($dir);
	}
}

/**
 * Upload a new avatar in to the file system
 *
 * @param srray incoming FILE array, if we have one - otherwise takes $_FILES['avatarupload']
 * @param string User ID this avatar is being uploaded for, if not the current user
 * @return array Array of errors if any, otherwise filename of successful.
 */
function upload_avatar($avatar=array(), $uid=0)
{
	global $db, $mybb, $lang, $plugins, $cache;

	if(!$uid)
	{
		$uid = $mybb->user['uid'];
	}

	if(!$avatar['name'] || !$avatar['tmp_name'])
	{
		$avatar = $_FILES['avatarupload'];
	}

	if(!is_uploaded_file($avatar['tmp_name']))
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Check we have a valid extension
	// This is attached to the attachment types allowed to be uploaded (set in the ACP)
	$valid_extensions = array();
	$extensions = $cache->read("attachtypes");

	foreach($extensions as $ext => $type)
	{
		if(substr($type['mimetype'], 0, 5) == 'image')
		{
			$valid_extensions[$ext] = 1;
		}
	}

	$ext = get_extension(my_strtolower($avatar['name']));

	if(!isset($valid_extensions[$ext]))
	{
		$ret['error'] = $lang->error_avatartype;
		return $ret;
	}

	if(defined('IN_ADMINCP'))
	{
		$avatarpath = '../'.$mybb->settings['avataruploadpath'];
		$lang->load("messages", true);
	}
	else
	{
		$avatarpath = $mybb->settings['avataruploadpath'];
	}

	$filename = "avatar_".$uid.".".$ext;
	$file = upload_file($avatar, $avatarpath, $filename);
	if($file['error'])
	{
		@unlink($avatarpath."/".$filename);
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}


	// Lets just double check that it exists
	if(!file_exists($avatarpath."/".$filename))
	{
		$ret['error'] = $lang->error_uploadfailed;
		@unlink($avatarpath."/".$filename);
		return $ret;
	}

	// Check if this is a valid image or not
	$img_dimensions = @getimagesize($avatarpath."/".$filename);
	if(!is_array($img_dimensions))
	{
		@unlink($avatarpath."/".$filename);
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Check avatar dimensions
	if($mybb->settings['maxavatardims'] != '')
	{
		list($maxwidth, $maxheight) = @explode("x", $mybb->settings['maxavatardims']);
		if(($maxwidth && $img_dimensions[0] > $maxwidth) || ($maxheight && $img_dimensions[1] > $maxheight))
		{
			// Automatic resizing enabled?
			if($mybb->settings['avatarresizing'] == "auto" || ($mybb->settings['avatarresizing'] == "user" && $mybb->input['auto_resize'] == 1))
			{
				require_once MYBB_ROOT."inc/functions_image.php";
				$thumbnail = generate_thumbnail($avatarpath."/".$filename, $avatarpath, $filename, $maxheight, $maxwidth);
				if(!$thumbnail['filename'])
				{
					$ret['error'] = $lang->sprintf($lang->error_avatartoobig, $maxwidth, $maxheight);
					$ret['error'] .= "<br /><br />".$lang->error_avatarresizefailed;
					@unlink($avatarpath."/".$filename);
					return $ret;
				}
				else
				{
					// Reset filesize
					$avatar['size'] = filesize($avatarpath."/".$filename);
					// Reset dimensions
					$img_dimensions = @getimagesize($avatarpath."/".$filename);
				}
			}
			else
			{
				$ret['error'] = $lang->sprintf($lang->error_avatartoobig, $maxwidth, $maxheight);
				if($mybb->settings['avatarresizing'] == "user")
				{
					$ret['error'] .= "<br /><br />".$lang->error_avataruserresize;
				}
				@unlink($avatarpath."/".$filename);
				return $ret;
			}
		}
	}

	// Next check the file size
	if($avatar['size'] > ($mybb->settings['avatarsize']*1024) && $mybb->settings['avatarsize'] > 0)
	{
		@unlink($avatarpath."/".$filename);
		$ret['error'] = $lang->error_uploadsize;
		return $ret;
	}

	// Check a list of known MIME types to establish what kind of avatar we're uploading
	switch(my_strtolower($avatar['type']))
	{
		case "image/gif":
			$img_type =  1;
			break;
		case "image/jpeg":
		case "image/x-jpg":
		case "image/x-jpeg":
		case "image/pjpeg":
		case "image/jpg":
			$img_type = 2;
			break;
		case "image/png":
		case "image/x-png":
			$img_type = 3;
			break;
		default:
			$img_type = 0;
	}

	// Check if the uploaded file type matches the correct image type (returned by getimagesize)
	if($img_dimensions[2] != $img_type || $img_type == 0)
	{
		$ret['error'] = $lang->error_uploadfailed;
		@unlink($avatarpath."/".$filename);
		return $ret;
	}
	// Everything is okay so lets delete old avatars for this user
	remove_avatars($uid, $filename);

	$ret = array(
		"avatar" => $mybb->settings['avataruploadpath']."/".$filename,
		"width" => intval($img_dimensions[0]),
		"height" => intval($img_dimensions[1])
	);
	$ret = $plugins->run_hooks("upload_avatar_end", $ret);
	return $ret;
}

/**
 * Upload an attachment in to the file system
 *
 * @param array Attachment data (as fed by PHPs $_FILE)
 * @param boolean Whether or not we are updating a current attachment or inserting a new one
 * @return array Array of attachment data if successful, otherwise array of error data
 */
function upload_attachment($attachment, $update_attachment=false)
{
	global $db, $theme, $templates, $posthash, $pid, $tid, $forum, $mybb, $lang, $plugins, $cache;

	$posthash = $db->escape_string($mybb->input['posthash']);
	$pid = intval($pid);

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
				$ret['error'] .= $lang->sprintf($lang->error_uploadfailed_phpx, $attachment['error']);
				break;
		}
		return $ret;
	}

	if(!is_uploaded_file($attachment['tmp_name']) || empty($attachment['tmp_name']))
	{
		$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_php4;
		return $ret;
	}

    $attachtypes = $cache->read('attachtypes');
    $attachment = $plugins->run_hooks("upload_attachment_start", $attachment);

    $ext = get_extension($attachment['name']);
    // Check if we have a valid extension
    if(!isset($attachtypes[$ext]))
    {
    	$ret['error'] = $lang->error_attachtype;
		return $ret;
	}

	// Check the size
	if($attachment['size'] > $attachtype['maxsize']*1024 && $attachtype['maxsize'] != "")
	{
		$ret['error'] = $lang->sprintf($lang->error_attachsize, $attachtype['maxsize']);
		return $ret;
	}

	// Double check attachment space usage
	if($mybb->usergroup['attachquota'] > 0)
	{
		$query = $db->simple_select("attachments", "SUM(filesize) AS ausage", "uid='".$mybb->user['uid']."'");
		$usage = $db->fetch_array($query);
		$usage = $usage['ausage']+$attachment['size'];
		if($usage > ($mybb->usergroup['attachquota']*1024))
		{
			$friendlyquota = get_friendly_size($mybb->usergroup['attachquota']*1024);
			$ret['error'] = $lang->sprintf($lang->error_reachedattachquota, $friendlyquota);
			return $ret;
		}
	}

	// Gather forum permissions
	$forumpermissions = forum_permissions($forum['fid']);

	// Check if an attachment with this name is already in the post
	if($pid != 0)
	{
		$uploaded_query = "pid='{$pid}'";
	}
	else
	{
		$uploaded_query = "posthash='{$posthash}'";
	}
	$query = $db->simple_select("attachments", "*", "filename='".$db->escape_string($attachment['name'])."' AND ".$uploaded_query);
	$prevattach = $db->fetch_array($query);
	if($prevattach['aid'] && $update_attachment == false)
	{
		if(!$mybb->usergroup['caneditattachments'] && !$forumpermissions['caneditattachments'])
		{
			$ret['error'] = $lang->error_alreadyuploaded_perm;
			return $ret;
		}

		$ret['error'] = $lang->error_alreadyuploaded;
		return $ret;
	}

	$month_dir = '';
	if(ini_get('safe_mode') != 1 && strtolower(ini_get('safe_mode')) != 'on')
	{
		// Check if the attachment directory (YYYYMM) exists, if not, create it
		$month_dir = gmdate("Ym");
		if(!@is_dir($mybb->settings['uploadspath']."/".$month_dir))
		{
			@mkdir($mybb->settings['uploadspath']."/".$month_dir);
			// Still doesn't exist - oh well, throw it in the main directory
			if(!@is_dir($mybb->settings['uploadspath']."/".$month_dir))
			{
				$month_dir = '';
			}
		}
	}

	// All seems to be good, lets move the attachment!
	$filename = "post_".$mybb->user['uid']."_".TIME_NOW."_".md5(random_str()).".attach";

	$file = upload_file($attachment, $mybb->settings['uploadspath']."/".$month_dir, $filename);

	// Failed to create the attachment in the monthly directory, just throw it in the main directory
	if($file['error'] && $month_dir)
	{
		$file = upload_file($attachment, $mybb->settings['uploadspath'].'/', $filename);
	}

	if($month_dir)
	{
		$filename = $month_dir."/".$filename;
	}

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
		"pid" => $pid,
		"posthash" => $posthash,
		"uid" => $mybb->user['uid'],
		"filename" => $db->escape_string($file['original_filename']),
		"filetype" => $db->escape_string($file['type']),
		"filesize" => intval($file['size']),
		"attachname" => $filename,
		"downloads" => 0,
		"dateuploaded" => TIME_NOW
	);

	// If we're uploading an image, check the MIME type compared to the image type and attempt to generate a thumbnail
	if($ext == "gif" || $ext == "png" || $ext == "jpg" || $ext == "jpeg" || $ext == "jpe")
	{
		// Check a list of known MIME types to establish what kind of image we're uploading
		switch(my_strtolower($file['type']))
		{
			case "image/gif":
				$img_type =  1;
				break;
			case "image/jpeg":
			case "image/x-jpg":
			case "image/x-jpeg":
			case "image/pjpeg":
			case "image/jpg":
				$img_type = 2;
				break;
			case "image/png":
			case "image/x-png":
				$img_type = 3;
				break;
			default:
				$img_type = 0;
		}

		$supported_mimes = array();
		foreach($attachtypes as $attachtype)
		{
			if(!empty($attachtype['mimetype']))
			{
				$supported_mimes[] = $attachtype['mimetype'];
			}
		}

		// Check if the uploaded file type matches the correct image type (returned by getimagesize)
		$img_dimensions = @getimagesize($mybb->settings['uploadspath']."/".$filename);

		$mime = "";
		$file_path = $mybb->settings['uploadspath']."/".$filename;
		if(function_exists("finfo_open"))
		{
			$file_info = finfo_open(FILEINFO_MIME);
			list($mime, ) = explode(';', finfo_file($file_info, MYBB_ROOT.$file_path), 1);
			finfo_close($file_info);
		}
		else if(function_exists("mime_content_type"))
		{
			$mime = mime_content_type(MYBB_ROOT.$file_path);
		}

		if(!is_array($img_dimensions) || ($img_dimensions[2] != $img_type && !in_array($mime, $supported_mimes)))
		{
			@unlink($mybb->settings['uploadspath']."/".$filename);
			$ret['error'] = $lang->error_uploadfailed;
			return $ret;
		}
		require_once MYBB_ROOT."inc/functions_image.php";
		$thumbname = str_replace(".attach", "_thumb.$ext", $filename);

		$attacharray = $plugins->run_hooks("upload_attachment_thumb_start", $attacharray);

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
	if($forum['modattachments'] == 1 && !is_moderator($forum['fid'], "", $mybb->user['uid']))
	{
		$attacharray['visible'] = 0;
	}
	else
	{
		$attacharray['visible'] = 1;
	}

	$attacharray = $plugins->run_hooks("upload_attachment_do_insert", $attacharray);

	if($prevattach['aid'] && $update_attachment == true)
	{
		unset($attacharray['downloads']); // Keep our download count if we're updating an attachment
		$db->update_query("attachments", $attacharray, "aid='".$db->escape_string($prevattach['aid'])."'");
		$aid = $prevattach['aid'];
	}
	else
	{
		$aid = $db->insert_query("attachments", $attacharray);
		if($pid)
		{
			update_thread_counters($tid, array("attachmentcount" => "+1"));
		}
	}
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
	global $plugins;

	if(empty($file['name']) || $file['name'] == "none" || $file['size'] < 1)
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
	@my_chmod($path."/".$filename, '0644');
	$upload['filename'] = $filename;
	$upload['path'] = $path;
	$upload['type'] = $file['type'];
	$upload['size'] = $file['size'];
	$upload = $plugins->run_hooks("upload_file_end", $upload);
	return $upload;
}
?>
