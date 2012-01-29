<?php

/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class Previews implements ModsInterface  
{
	private $cache;
	
	/*
	 * Gets all previews.
	 * @param $where string Contains the WHERE condition (optional).
	 * @param $cache boolean Whether to cache the retreived list of previews or not.
	 * Note: If param $where is not empty, the list will not be cached regardless of the parameter $cache
	*/
	public function getAll($where='',$cache=true)
	{
		global $db;
		
		if (!empty($where) && !is_string($where))
			return false;
		
		$entries = array();
		
		$query = $db->simple_select('mods_previews', '*', $where);
		while ($e = $db->fetch_array($query))
			$entries[$e['pid']] = $e;
		
		// Only cache if we're actually query ALL of them.
		if ($cache !== false && empty($where))
			$this->cache = $entries;
			
		return $entries;
	}
	
	/*
	 * Get cached previews.
	*/
	public function getCached()
	{
		return $this->cache;
	}
	
	/*
	 * Get a single preview by its ID.
	*/
	public function getByID($id,$cache=true)
	{
		global $db;
		
		if (empty($id))
			return false;
			
		// Does the entry exist in our cache?
		if (!empty($this->cache[$id]))
			return $this->cache[$id];
		
		$query = $db->simple_select('mods_previews', '*', 'pid=\''.intval($id).'\'');
		$entry = $db->fetch_array($query);
		
		// Cache our item if set to true
		if ($cache === true)
			$this->cache[$id] = $entry;
		
		if (!empty($entry))
			return $entry;
		else
			return false;
	}
	
	/*
	 * Creates a new preview
	 * @param $array Array containing the data to be inserted into the database. Format: field => value [...]
	*/
	public function create($array)
	{
		if (empty($array) || !is_array($array))
			return false;
			
		global $db, $mybb, $mods;
		
		$array['uid'] = (int)$mybb->user['uid'];
			
		// Escape everything
		$array = array_map(array($db, 'escape_string'), $array);
		
		// Update the Project lastupdated field if we've got a valid project
		if ((int)$array['project'] > 0)
		{
			$mods->projects->updateByID(array('lastupdated' => TIME_NOW), $array['project']);
		}
		
		return $db->insert_query('mods_previews', $array);
	}
	
	// Delete a specific preview
	public function deleteByID($id)
	{
		$id = (int)$id;
		if ($id <= 0)
			return false;
			
		global $db;
			
		return $db->delete_query('mods_previews', 'pid=\''.intval($id).'\'');
	}
	
	// Delete all previews of a certain project
	public function deleteAll($id)
	{
		$id = (int)$id;
		if ($id <= 0)
			return false;
			
		global $db;
			
		return $db->delete_query('mods_previews', 'pid=\''.intval($id).'\'');
	}
	
	public function updateByID($array, $id)
	{
		if (empty($array) || !is_array($array))
			return false;
		
		$id = (int)$id;
		if ($id <= 0)
			return false;
		
		global $db;
		
		// Update the cache if it contains the entry we're updating
		if (!empty($this->cache[$id]))
		{
			foreach ($array as $key => $element)
			{
				$this->cache[$id][$key] = $element;
			}
		}
			
		// Escape everything
		$array = array_map(array($db, 'escape_string'), $array);
		
		return $db->update_query('mods_previews', $array, 'pid=\''.intval($id).'\'');
	}
	
	/**
	 * Upload a preview in to the file system
	 *
	 * @param array Preview data (as fed by PHP's $_FILE)
	 * @return array Array of preview data if successful, otherwise array of error data
	 */
	public function upload($preview)
	{
		global $db, $mybb, $lang;

		if(isset($preview['error']) && $preview['error'] != 0)
		{
			$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_detail;
			switch($preview['error'])
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
					$ret['error'] .= $lang->sprintf($lang->error_uploadfailed_phpx, $preview['error']);
					break;
			}
			return $ret;
		}
		
		if(!is_uploaded_file($preview['tmp_name']) || empty($preview['tmp_name']))
		{
			$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_php4;
			return $ret;
		}
		
		$ext = get_extension($preview['name']);
		
		// Check if we have a valid extension
		if ($ext != 'jpg' && $ext != 'jpeg' && $ext != 'png')
		{
			$ret['error'] = $lang->mods_not_valid_image;
			return $ret;
		}
		
		require_once MYBB_ROOT."inc/functions_image.php";
		
		// Check the size
		if($preview['size'] > $mybb->settings['mods_previewsize']*1024)
		{
			$ret['error'] = $lang->sprintf($lang->error_attachsize, $mybb->settings['mods_previewsize']);
			return $ret;
		}
		
		// All seems to be good, lets move the preview!
		$filename = "preview_".$mybb->user['uid']."_".TIME_NOW."_".md5(random_str()).".".$ext;
		
		require_once MYBB_ROOT."inc/functions_upload.php";
		$file = upload_file($preview, $mybb->settings['uploadspath']."/mods/previews", $filename);
		
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
		if(!file_exists($mybb->settings['uploadspath']."/mods/previews/".$filename))
		{
			$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_detail.$lang->error_uploadfailed_lost;
			return $ret;
		}
		
		$preview['filename'] = $filename;
		
		// Generate thumbnail
		$thumb = generate_thumbnail(MYBB_ROOT."uploads/mods/previews/".$preview['filename'], MYBB_ROOT."uploads/mods/previews/", 'thumbnail_'.$preview['filename'], 100, 100);
			
		if ($thumb['code'] == 4) // image is too small already, set thumbnail to the image
		{
			$preview['thumbnail'] = $preview['filename'];
		}
		else
			$preview['thumbnail'] = 'thumbnail_'.$preview['filename'];
		
		// Generate the array for the insert_query
		$previewarray = array(
			"project" => (int)$preview['project'], 
			"uid" => $mybb->user['uid'],
			"date" => TIME_NOW,
			"thumbnail" => $preview['thumbnail'],
			"filename" => $filename
		);
		
		$pid = $this->create($previewarray);
		
		$ret['pid'] = $pid;
		return $ret;
	}
}