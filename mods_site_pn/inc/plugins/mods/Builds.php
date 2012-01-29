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

class Builds implements ModsInterface  
{
	private $cache;
	
	/*
	 * Gets all builds.
	 * @param $where string Contains the WHERE condition (optional).
	 * @param $cache boolean Whether to cache the retreived list of builds or not.
	 * Note: If param $where is not empty, the list will not be cached regardless of the parameter $cache
	*/
	public function getAll($where='',$cache=true)
	{
		global $db;
		
		if (!empty($where) && !is_string($where))
			return false;
		
		$entries = array();
		
		$query = $db->simple_select('mods_builds', '*', $where);
		while ($e = $db->fetch_array($query))
			$entries[$e['bid']] = $e;
		
		// Only cache if we're actually query ALL of them.
		if ($cache !== false && empty($where))
			$this->cache = $entries;
			
		return $entries;
	}
	
	/*
	 * Get cached builds.
	*/
	public function getCached()
	{
		return $this->cache;
	}
	
	/*
	 * Get a single build by its ID.
	*/
	public function getByID($id,$cache=true)
	{
		global $db;
		
		if (empty($id))
			return false;
			
		// Does the entry exist in our cache?
		if (!empty($this->cache[$id]))
			return $this->cache[$id];
		
		$query = $db->simple_select('mods_builds', '*', 'bid=\''.intval($id).'\'');
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
	 * Creates a new build
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
		
		// Update the Project lastupdated field if we've got a valid pid
		if ((int)$array['pid'] > 0)
		{
			$mods->projects->updateByID(array('lastupdated' => TIME_NOW), $array['pid']);
		}
		
		return $db->insert_query('mods_builds', $array);
	}
	
	// Delete a specific build
	public function deleteByID($id)
	{
		$id = (int)$id;
		if ($id <= 0)
			return false;
			
		global $db;
			
		return $db->delete_query('mods_builds', 'bid=\''.intval($id).'\'');
	}
	
	// Delete all builds of a certain project
	public function deleteAll($id)
	{
		$id = (int)$id;
		if ($id <= 0)
			return false;
			
		global $db;
			
		return $db->delete_query('mods_builds', 'pid=\''.intval($id).'\'');
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
		
		return $db->update_query('mods_builds', $array, 'bid=\''.intval($id).'\'');
	}
	
	/**
	 * Upload a build in to the file system
	 *
	 * @param array Build data (as fed by PHPs $_FILE)
	 * @return array Array of build data if successful, otherwise array of error data
	 */
	public function upload($build)
	{
		global $db, $mybb, $lang;

		if(isset($build['error']) && $build['error'] != 0)
		{
			$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_detail;
			switch($build['error'])
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
					$ret['error'] .= $lang->sprintf($lang->error_uploadfailed_phpx, $build['error']);
					break;
			}
			return $ret;
		}
		
		if(!is_uploaded_file($build['tmp_name']) || empty($build['tmp_name']))
		{
			$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_php4;
			return $ret;
		}
		
		$ext = get_extension($preview['name']);
		
		// Check if we have a valid extension
		if ($ext != 'zip')
		{
			$ret['error'] = $lang->mods_not_zip;
			return $ret;
		}
		
		// Check the size
		if($build['size'] > $mybb->settings['mods_buildsize']*1024)
		{
			$ret['error'] = $lang->sprintf($lang->error_attachsize, $mybb->settings['mods_buildsize']);
			return $ret;
		}
		
		// All seems to be good, lets move the build!
		$filename = "build_".$mybb->user['uid']."_".TIME_NOW."_".md5(random_str()).".attach";
		
		require_once MYBB_ROOT."inc/functions_upload.php";
		$file = upload_file($build, $mybb->settings['uploadspath']."/mods", $filename);
		
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
		if(!file_exists($mybb->settings['uploadspath']."/mods/".$filename))
		{
			$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_detail.$lang->error_uploadfailed_lost;
			return $ret;
		}

		// Figure out Project ID
		if (intval($build['pid']) > 0)
		{
			$pid = intval($build['pid']);
			
			// Get latest build
			$latestbuild = $db->fetch_field($db->simple_select('mods_builds', 'number', 'pid=\''.$pid.'\'', array('limit' => 1, 'order_by' => 'dateuploaded', 'order_dir' => 'desc')), 'number');
		}
		else {
			// 0 for now...we will update it if the project is created successfully
			$pid = 0;
			$latestbuild = 0;
		}
		
		// Generate the array for the insert_query
		$buildarray = array(
			"pid" => $pid, 
			"number" => $latestbuild+1,
			"uid" => $mybb->user['uid'],
			"filename" => $db->escape_string($filename),
			"filetype" => $db->escape_string($file['type']),
			"filesize" => intval($file['size']),
			"downloads" => 0,
			"md5" => md5_file($mybb->settings['uploadspath']."/mods/".$filename),
			"dateuploaded" => TIME_NOW,
			"versions" => $build['versions'],
			"changes" => $build['changes']
		);
		
		$bid = $this->create($buildarray);
		
		$ret['bid'] = $bid;
		return $ret;
	}
	
	public function setStable($id, $devstatus=0)
	{
		global $db, $mybb, $lang;
		
		$id = (int)$id;
		if ($id <= 0)
			return false;
		
		// If the developer is a trusted developer
		if ($devstatus == 1)
		{
			// Update build to stable immediatly
			$this->updateByID(array('status' => 'stable'), $id);
		}
		else 
		{
			// Put it under awaiting approval so it can be approved from the Mod CP
			$this->updateByID(array('waitingstable' => '1'), $id);
			
			// Meanwhile it remains dev
		}
	}
}