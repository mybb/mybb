<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Get maximum upload filesize limit set in PHP
 * @since MyBB 1.8.27
 * @return int maximum allowed filesize
 */
function get_php_upload_limit()
{	
	$maxsize = array(return_bytes(ini_get('upload_max_filesize')), return_bytes(ini_get('post_max_size')));
	$maxsize = array_filter($maxsize); // Remove empty values

	if(empty($maxsize))
	{
		return 0;
	}
	else
	{
		return (int)min($maxsize);
	}
}

/**
 * Remove an attachment from a specific post
 *
 * @param int $pid The post ID
 * @param string $posthash The posthash if available
 * @param int $aid The attachment ID
 */
function remove_attachment($pid, $posthash, $aid)
{
	global $db, $mybb, $plugins;
	$aid = (int)$aid;
	$posthash = $db->escape_string($posthash);
	if(!empty($posthash))
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

	if($attachment === false)
	{
		// no attachment found with the given details
		return;
	}

	$db->delete_query("attachments", "aid='{$attachment['aid']}'");

	$uploadspath_abs = mk_path_abs($mybb->settings['uploadspath']);

	// Check if this attachment is referenced in any other posts. If it isn't, then we are safe to delete the actual file.
	$query = $db->simple_select("attachments", "COUNT(aid) as numreferences", "attachname='".$db->escape_string($attachment['attachname'])."'");
	if($db->fetch_field($query, "numreferences") == 0)
	{
		delete_uploaded_file($uploadspath_abs."/".$attachment['attachname']);
		if($attachment['thumbnail'] && $attachment['thumbnail'] !== 'SMALL')
		{
			delete_uploaded_file($uploadspath_abs."/".$attachment['thumbnail']);
		}

		$date_directory = explode('/', $attachment['attachname']);
		$query_indir = $db->simple_select("attachments", "COUNT(aid) as indir", "attachname LIKE '".$db->escape_string_like($date_directory[0])."/%'");
		if($db->fetch_field($query_indir, 'indir') == 0 && @is_dir($uploadspath_abs."/".$date_directory[0]))
		{
			delete_upload_directory($uploadspath_abs."/".$date_directory[0]);
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
 * @param int $pid The post ID
 * @param string $posthash The posthash if available
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

	$uploadspath_abs = mk_path_abs($mybb->settings['uploadspath']);

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
			delete_uploaded_file($uploadspath_abs."/".$attachment['attachname']);
			if($attachment['thumbnail'])
			{
				delete_uploaded_file($uploadspath_abs."/".$attachment['thumbnail']);
			}

			$date_directory = explode('/', $attachment['attachname']);
			$query_indir = $db->simple_select("attachments", "COUNT(aid) as indir", "attachname LIKE '".$db->escape_string_like($date_directory[0])."/%'");
			if($db->fetch_field($query_indir, 'indir') == 0 && @is_dir($uploadspath_abs."/".$date_directory[0]))
			{
				delete_upload_directory($uploadspath_abs."/".$date_directory[0]);
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
 * @param int $uid The user ID
 * @param string $exclude A file name to be excluded from the removal
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
				delete_uploaded_file($avatarpath."/".$file);
			}
		}

		@closedir($dir);
	}
}

/**
 * Create the attachment directory index file.
 * 
 * @param string $path The path to the attachment directory to create the file in.
 */
function create_attachment_index($path)
{
	$index = @fopen(rtrim($path, '/').'/index.html', 'w');
	@fwrite($index, '<html>\n<head>\n<title></title>\n</head>\n<body>\n&nbsp;\n</body>\n</html>');
	@fclose($index);
}

/**
 * Upload a new avatar in to the file system
 *
 * @param array $avatar Incoming FILE array, if we have one - otherwise takes $_FILES['avatarupload']
 * @param int $uid User ID this avatar is being uploaded for, if not the current user
 * @return array Array of errors if any, otherwise filename of successful.
 */
function upload_avatar($avatar=array(), $uid=0)
{
	global $db, $mybb, $lang, $plugins, $cache;

	$ret = array();

	if(!$uid)
	{
		$uid = $mybb->user['uid'];
	}

	if(empty($avatar['name']) || empty($avatar['tmp_name']))
	{
		$avatar = $_FILES['avatarupload'];
	}

	if(!is_uploaded_file($avatar['tmp_name']))
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Check we have a valid extension
    	$ext = get_extension(my_strtolower($avatar['name']));
    	if(!preg_match("#^(gif|jpg|jpeg|jpe|bmp|png)$#i", $ext))
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
	if(!empty($file['error']))
	{
		delete_uploaded_file($avatarpath."/".$filename);
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Lets just double check that it exists
	if(!file_exists($avatarpath."/".$filename))
	{
		$ret['error'] = $lang->error_uploadfailed;
		delete_uploaded_file($avatarpath."/".$filename);
		return $ret;
	}

	// Check if this is a valid image or not
	$img_dimensions = @getimagesize($avatarpath."/".$filename);
	if(!is_array($img_dimensions))
	{
		delete_uploaded_file($avatarpath."/".$filename);
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Check avatar dimensions
	if($mybb->settings['maxavatardims'] != '')
	{
		list($maxwidth, $maxheight) = @preg_split('/[|x]/', $mybb->settings['maxavatardims']);
		if(($maxwidth && $img_dimensions[0] > $maxwidth) || ($maxheight && $img_dimensions[1] > $maxheight))
		{
			// Automatic resizing enabled?
			if($mybb->settings['avatarresizing'] == "auto" || ($mybb->settings['avatarresizing'] == "user" && $mybb->input['auto_resize'] == 1))
			{
				require_once MYBB_ROOT."inc/functions_image.php";
				$thumbnail = generate_thumbnail($avatarpath."/".$filename, $avatarpath, $filename, $maxheight, $maxwidth);
				if(empty($thumbnail['filename']))
				{
					$ret['error'] = $lang->sprintf($lang->error_avatartoobig, $maxwidth, $maxheight);
					$ret['error'] .= "<br /><br />".$lang->error_avatarresizefailed;
					delete_uploaded_file($avatarpath."/".$filename);
					return $ret;
				}
				else
				{
					// Copy scaled image to CDN
					copy_file_to_cdn($avatarpath . '/' . $thumbnail['filename']);
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
				delete_uploaded_file($avatarpath."/".$filename);
				return $ret;
			}
		}
	}

	// Check a list of known MIME types to establish what kind of avatar we're uploading
	$attachtypes = (array)$cache->read('attachtypes');

	$allowed_mime_types = array();
	foreach($attachtypes as $attachtype)
	{
		if(defined('IN_ADMINCP') || is_member($attachtype['groups']) && $attachtype['avatarfile'])
		{
			$allowed_mime_types[$attachtype['mimetype']] = $attachtype['maxsize'];
		}
	}

	$avatar['type'] = my_strtolower($avatar['type']);

	switch($avatar['type'])
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
		case "image/bmp":
		case "image/x-bmp":
		case "image/x-windows-bmp":
			$img_type = 6;
			break;
		default:
			$img_type = 0;
	}

	// Check if the uploaded file type matches the correct image type (returned by getimagesize)
	if(empty($allowed_mime_types[$avatar['type']]) || $img_dimensions[2] != $img_type || $img_type == 0)
	{
		$ret['error'] = $lang->error_uploadfailed;
		delete_uploaded_file($avatarpath."/".$filename);
		return $ret;
	}

	// Next check the file size
	if(($mybb->settings['avatarsize'] > 0 && $avatar['size'] > ($mybb->settings['avatarsize']*1024)) || $avatar['size'] > ($allowed_mime_types[$avatar['type']]*1024))
	{
		delete_uploaded_file($avatarpath."/".$filename);
		$ret['error'] = $lang->error_uploadsize;
		return $ret;
	}

	// Everything is okay so lets delete old avatars for this user
	remove_avatars($uid, $filename);

	$ret = array(
		"avatar" => $mybb->settings['avataruploadpath']."/".$filename,
		"width" => (int)$img_dimensions[0],
		"height" => (int)$img_dimensions[1]
	);
	$ret = $plugins->run_hooks("upload_avatar_end", $ret);
	return $ret;
}

/**
 * Upload an attachment in to the file system
 *
 * @param array $attachment Attachment data (as fed by PHPs $_FILE)
 * @param boolean $update_attachment Whether or not we are updating a current attachment or inserting a new one
 * @return array Array of attachment data if successful, otherwise array of error data
 */
function upload_attachment($attachment, $update_attachment=false)
{
	global $mybb, $db, $theme, $templates, $posthash, $pid, $tid, $forum, $mybb, $lang, $plugins, $cache;

	$posthash = $db->escape_string($mybb->get_input('posthash'));
	$pid = (int)$pid;

	if(!is_uploaded_file($attachment['tmp_name']) || empty($attachment['tmp_name']))
	{
		$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_php4;
		return $ret;
	}

    $attachtypes = (array)$cache->read('attachtypes');
    $attachment = $plugins->run_hooks("upload_attachment_start", $attachment);

	$allowed_mime_types = array();
	foreach($attachtypes as $ext => $attachtype)
	{
		if(!is_member($attachtype['groups']) || ($attachtype['forums'] != -1 && strpos(','.$attachtype['forums'].',', ','.$forum['fid'].',') === false))
		{
			unset($attachtypes[$ext]);
		}
	}

    $ext = get_extension($attachment['name']);
    // Check if we have a valid extension
    if(!isset($attachtypes[$ext]))
    {
    	$ret['error'] = $lang->error_attachtype;
		return $ret;
	}
	else
	{
		$attachtype = $attachtypes[$ext];
	}

	// check the length of the filename
	$maxFileNameLength = 255;
	if(my_strlen($attachment['name']) > $maxFileNameLength)
	{
		$ret['error'] = $lang->sprintf($lang->error_attach_filename_length, htmlspecialchars_uni($attachment['name']), $maxFileNameLength);
		return $ret;
	}

	// Check the size
	if($attachment['size'] > $attachtype['maxsize']*1024 && $attachtype['maxsize'] != "")
	{
		$ret['error'] = $lang->sprintf($lang->error_attachsize, htmlspecialchars_uni($attachment['name']), $attachtype['maxsize']);
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
	if(!empty($prevattach) && $prevattach['aid'] && $update_attachment == false)
	{
		if(!$mybb->usergroup['caneditattachments'] && !$forumpermissions['caneditattachments'])
		{
			$ret['error'] = $lang->error_alreadyuploaded_perm;
			return $ret;
		}

		$ret['error'] = $lang->sprintf($lang->error_alreadyuploaded, htmlspecialchars_uni($attachment['name']));
		return $ret;
	}

	// Check to see how many attachments exist for this post already
	if($mybb->settings['maxattachments'] > 0 && $update_attachment == false)
	{
		$query = $db->simple_select("attachments", "COUNT(aid) AS numattachs", $uploaded_query);
		$attachcount = $db->fetch_field($query, "numattachs");
		if($attachcount >= $mybb->settings['maxattachments'])
		{
			$ret['error'] = $lang->sprintf($lang->error_maxattachpost, $mybb->settings['maxattachments']);
			return $ret;
		}
	}

	$uploadspath_abs = mk_path_abs($mybb->settings['uploadspath']);
	$month_dir = '';
	if($mybb->safemode == false)
	{
		// Check if the attachment directory (YYYYMM) exists, if not, create it
		$month_dir = gmdate("Ym");
		if(!@is_dir($uploadspath_abs."/".$month_dir))
		{
			@mkdir($uploadspath_abs."/".$month_dir);
			// Still doesn't exist - oh well, throw it in the main directory
			if(!@is_dir($uploadspath_abs."/".$month_dir))
			{
				$month_dir = '';
			}
			else
			{
				create_attachment_index($uploadspath_abs."/".$month_dir);
			}
		}
	}

	// All seems to be good, lets move the attachment!
	$filename = "post_".$mybb->user['uid']."_".TIME_NOW."_".md5(random_str()).".attach";

	$file = upload_file($attachment, $uploadspath_abs."/".$month_dir, $filename);

	// Failed to create the attachment in the monthly directory, just throw it in the main directory
	if(!empty($file['error']) && $month_dir)
	{
		$file = upload_file($attachment, $uploadspath_abs.'/', $filename);
	}
	elseif($month_dir)
	{
		$filename = $month_dir."/".$filename;
	}

	if(!empty($file['error']))
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
	if(!file_exists($uploadspath_abs."/".$filename))
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
		"filesize" => (int)$file['size'],
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
		$img_dimensions = @getimagesize($uploadspath_abs."/".$filename);

		$mime = "";
		$file_path = $uploadspath_abs."/".$filename;
		if(function_exists("finfo_open"))
		{
			$file_info = finfo_open(FILEINFO_MIME);
			list($mime, ) = explode(';', finfo_file($file_info, $file_path), 1);
			finfo_close($file_info);
		}
		else if(function_exists("mime_content_type"))
		{
			$mime = mime_content_type($file_path);
		}

		if(!is_array($img_dimensions) || ($img_dimensions[2] != $img_type && !in_array($mime, $supported_mimes)))
		{
			delete_uploaded_file($uploadspath_abs."/".$filename);
			$ret['error'] = $lang->error_uploadfailed;
			return $ret;
		}
		require_once MYBB_ROOT."inc/functions_image.php";
		$thumbname = str_replace(".attach", "_thumb.$ext", $filename);

		$attacharray = $plugins->run_hooks("upload_attachment_thumb_start", $attacharray);

		$thumbnail = generate_thumbnail($uploadspath_abs."/".$filename, $uploadspath_abs, $thumbname, $mybb->settings['attachthumbh'], $mybb->settings['attachthumbw']);

		if(!empty($thumbnail['filename']))
		{
			$attacharray['thumbnail'] = $thumbnail['filename'];
		}
		elseif($thumbnail['code'] == 4)
		{
			$attacharray['thumbnail'] = "SMALL";
		}
	}
	if($forumpermissions['modattachments'] == 1 && !is_moderator($forum['fid'], "canapproveunapproveattachs"))
	{
		$attacharray['visible'] = 0;
	}
	else
	{
		$attacharray['visible'] = 1;
	}

	$attacharray = $plugins->run_hooks("upload_attachment_do_insert", $attacharray);

	if(!empty($prevattach) && $prevattach['aid'] && $update_attachment == true)
	{
		unset($attacharray['downloads']); // Keep our download count if we're updating an attachment
		$db->update_query("attachments", $attacharray, "aid='".$db->escape_string($prevattach['aid'])."'");

		// Remove old attachment file
		// Check if this attachment is referenced in any other posts. If it isn't, then we are safe to delete the actual file.
		$query = $db->simple_select("attachments", "COUNT(aid) as numreferences", "attachname='".$db->escape_string($prevattach['attachname'])."'");
		if($db->fetch_field($query, "numreferences") == 0)
		{
			delete_uploaded_file($uploadspath_abs."/".$prevattach['attachname']);
			if($prevattach['thumbnail'])
			{
				delete_uploaded_file($uploadspath_abs."/".$prevattach['thumbnail']);
			}

			$date_directory = explode('/', $prevattach['attachname']);
			$query_indir = $db->simple_select("attachments", "COUNT(aid) as indir", "attachname LIKE '".$db->escape_string_like($date_directory[0])."/%'");
			if($db->fetch_field($query_indir, 'indir') == 0 && @is_dir($uploadspath_abs."/".$date_directory[0]))
			{
				delete_upload_directory($uploadspath_abs."/".$date_directory[0]);
			}
		}

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
 * Check whether the input $FILE variable indicates a PHP file upload error,
 * and if so, return an appropriate user-friendly error message.
 *
 * @param array $FILE File data (as fed by PHP's $_FILE).
 *
 * @return string Error message or empty if no error detected.
 */
function check_parse_php_upload_err($FILE)
{
	global $lang;

	$err = '';

	if(isset($FILE['error']) && $FILE['error'] != 0 && ($FILE['error'] != UPLOAD_ERR_NO_FILE || $FILE['name']))
	{
		$err = $lang->error_uploadfailed.$lang->error_uploadfailed_detail;
		switch($FILE['error'])
		{
			case 1: // UPLOAD_ERR_INI_SIZE
				$err .= $lang->error_uploadfailed_php1;
				break;
			case 2: // UPLOAD_ERR_FORM_SIZE
				$err .= $lang->error_uploadfailed_php2;
				break;
			case 3: // UPLOAD_ERR_PARTIAL
				$err .= $lang->error_uploadfailed_php3;
				break;
			case 4: // UPLOAD_ERR_NO_FILE
				$err .= $lang->error_uploadfailed_php4;
				break;
			case 6: // UPLOAD_ERR_NO_TMP_DIR
				$err .= $lang->error_uploadfailed_php6;
				break;
			case 7: // UPLOAD_ERR_CANT_WRITE
				$err .= $lang->error_uploadfailed_php7;
				break;
			default:
				$err .= $lang->sprintf($lang->error_uploadfailed_phpx, $FILE['error']);
				break;
		}
	}

	return $err;
}

/**
 * Process adding attachment(s) when the "Add Attachment" button is pressed.
 *
 * @param int $pid The ID of the post.
 * @param array $forumpermission The permissions for the forum.
 * @param string $attachwhere Search string "pid='$pid'" or "posthash='".$db->escape_string($mybb->get_input('posthash'))."'"
 * @param string $action Where called from: "newthread", "newreply", or "editpost"
 *
 * @return array Array of errors if any, empty array otherwise
 */
function add_attachments($pid, $forumpermissions, $attachwhere, $action=false)
{
	global $db, $mybb, $editdraftpid, $lang;

	$ret = array();

	if($forumpermissions['canpostattachments'])
	{
		$attachments = array();
		$fields = array ('name', 'type', 'tmp_name', 'error', 'size');
		$aid = array();

		$total = isset($_FILES['attachments']['name']) ? count($_FILES['attachments']['name']) : 0;
		$filenames = "";
		$delim = "";
		for($i=0; $i<$total; ++$i)
		{
			foreach($fields as $field)
			{
				$attachments[$i][$field] = $_FILES['attachments'][$field][$i];
			}

			$FILE = $attachments[$i];
			if(!empty($FILE['name']) && !empty($FILE['type']) && $FILE['size'] > 0)
			{
				$filenames .= $delim . "'" . $db->escape_string($FILE['name']) . "'";
				$delim = ",";
			}
		}

		if ($filenames != '')
		{
			$query = $db->simple_select("attachments", "filename", "{$attachwhere} AND filename IN (".$filenames.")");

			while ($row = $db->fetch_array($query))
			{
				$aid[$row['filename']] = true;
			}
		}

		foreach($attachments as $FILE)
		{
			if($err = check_parse_php_upload_err($FILE))
			{
				$ret['errors'][] = $err;
				$mybb->input['action'] = $action;
			}
			else if(!empty($FILE['name']) && !empty($FILE['type']))
			{
				if($FILE['size'] > 0)
				{
					$filename = $db->escape_string($FILE['name']);
					$exists = !empty($aid[$filename]);

					$update_attachment = false;
					if($action == "editpost")
					{
						if($exists && $mybb->get_input('updateattachment') && ($mybb->usergroup['caneditattachments'] || $forumpermissions['caneditattachments']))
						{
							$update_attachment = true;
						}
					}
					else
					{
						if($exists && $mybb->get_input('updateattachment'))
						{
							$update_attachment = true;
						}
					}
					
					if(!$exists && $mybb->get_input('updateattachment') && $mybb->get_input('updateconfirmed', MyBB::INPUT_INT) != 1)
					{
						$ret['errors'][] = $lang->sprintf($lang->error_updatefailed, $filename);
					}
					else
					{
						$attachedfile = upload_attachment($FILE, $update_attachment);

						if(!empty($attachedfile['error']))
						{
							$ret['errors'][] = $attachedfile['error'];
							$mybb->input['action'] = $action;
						}
						else if(isset($attachedfile['aid']) && $mybb->get_input('ajax', MyBB::INPUT_INT) == 1)
						{
							$ret['success'][] = array($attachedfile['aid'], get_attachment_icon(get_extension($filename)), htmlspecialchars_uni($filename), get_friendly_size($FILE['size']));
						}
					}
				}
				else
				{
					$ret['errors'][] = $lang->sprintf($lang->error_uploadempty, htmlspecialchars_uni($FILE['name']));
					$mybb->input['action'] = $action;
				}
			}
		}
	}

	return $ret;
}

/**
 * Delete an uploaded file both from the relative path and the CDN path if a CDN is in use.
 *
 * @param string $path The relative path to the uploaded file.
 *
 * @return bool Whether the file was deleted successfully.
 */
function delete_uploaded_file($path = '')
{
	global $mybb, $plugins;

	$deleted = false;

	$deleted = @unlink($path);

	$cdn_base_path = rtrim($mybb->settings['cdnpath'], '/');
	$path = ltrim($path, '/');
	$cdn_path = realpath($cdn_base_path . '/' . $path);

	if(!empty($mybb->settings['usecdn']) && !empty($cdn_base_path))
	{
		$deleted = $deleted && @unlink($cdn_path);
	}

	$hook_params = array(
		'path' => &$path,
		'deleted' => &$deleted,
	);

	$plugins->run_hooks('delete_uploaded_file', $hook_params);

	return $deleted;
}

/**
 * Delete an upload directory on both the local filesystem and the CDN filesystem.
 *
 * @param string $path The directory to delete.
 *
 * @return bool Whether the directory was deleted.
 */
function delete_upload_directory($path = '')
{
	global $mybb, $plugins;

	$deleted = false;

	$deleted_index = @unlink(rtrim($path, '/').'/index.html');

	$deleted = @rmdir($path);

	$cdn_base_path = rtrim($mybb->settings['cdnpath'], '/');
	$path = ltrim($path, '/');
	$cdn_path = rtrim(realpath($cdn_base_path . '/' . $path), '/');

	if(!empty($mybb->settings['usecdn']) && !empty($cdn_base_path))
	{
		$deleted = $deleted && @rmdir($cdn_path);
	}

	$hook_params = array(
		'path' => &$path,
		'deleted' => &$deleted,
	);

	$plugins->run_hooks('delete_upload_directory', $hook_params);

	// If not successfully deleted then reinstante the index file
	if(!$deleted && $deleted_index)
	{
		create_attachment_index($path);
	}

	return $deleted;
}

/**
 * Actually move a file to the uploads directory
 *
 * @param array $file The PHP $_FILE array for the file
 * @param string $path The path to save the file in
 * @param string $filename The filename for the file (if blank, current is used)
 * @return array The uploaded file
 */
function upload_file($file, $path, $filename="")
{
	global $plugins, $mybb;

	$upload = array();

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

	$cdn_path = '';

	$moved_cdn = copy_file_to_cdn($path."/".$filename, $cdn_path);

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

	if($moved_cdn)
	{
		$upload['cdn_path'] = $cdn_path;
	}

	return $upload;
}
