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

class Projects implements ModsInterface  
{
	private $cache;
	public $builds;
	public $previews;
	
	public function __construct()
	{
		require_once MYBB_ROOT."inc/plugins/mods/Builds.php";
		$this->builds = new Builds();
		
		require_once MYBB_ROOT."inc/plugins/mods/Previews.php";
		$this->previews = new Previews();
	}
	
	/*
	 * Gets all projects.
	 * @param $where string Contains the WHERE condition (optional).
	 * @param $cache boolean Whether to cache the retreived list of projects or not.
	 * Note: If param $where is not empty, the list will not be cached regardless of the parameter $cache
	*/
	public function getAll($where='',$cache=true)
	{
		global $db;
		
		if (!empty($where) && !is_string($where))
			return false;
		
		$entries = array();
		
		$query = $db->simple_select('mods_projects', '*', $where, array('order_by' => 'name', 'order_dir' => 'asc'));
		while ($e = $db->fetch_array($query))
			$entries[$e['pid']] = $e;
		
		// Only cache if we're actually query ALL of them.
		if ($cache !== false && empty($where))
			$this->cache = $entries;
			
		return $entries;
	}
	
	/*
	 * Get cached projects.
	*/
	public function getCached()
	{
		return $this->cache;
	}
	
	/*
	 * Get a single project by its ID.
	*/
	public function getByID($id,$cache=true,$approved=false)
	{
		global $db;
		
		if (empty($id))
			return false;
			
		// Does the entry exist in our cache?
		if (!empty($this->cache[$id]))
			return $this->cache[$id];
			
		if ($approved === true)
			$sql = ' AND approved=1';
		else
			$sql = '';
		
		$query = $db->simple_select('mods_projects', '*', 'pid=\''.intval($id).'\''.$sql);
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
	 * Validates data to be inserted in a project.
	 * @param $array array Contains the data to be validated.
	 * @return an empty array if no errors, else it contains the list of errors.
	*/
	public function validate($array)
	{
		global $lang, $mybb;
		
		$errors = array();
		
		// Validate name
		if (!isset($array['name']) || my_strlen(trim_blank_chrs($array['name'])) == 0)
		{
			$errors[] = $lang->mods_create_project_missing_name;
		}
		
		// Validate codename
		if (!isset($array['codename']) || my_strlen(trim_blank_chrs($array['codename'])) == 0)
		{
			$errors[] = $lang->mods_create_project_missing_codename;
		}
		
		// Validate description
		if (!isset($array['description']) || my_strlen(trim_blank_chrs($array['description'])) == 0)
		{
			$errors[] = $lang->mods_create_project_missing_desc;
		}
		
		// Validate information
		if (!isset($array['information']) || my_strlen(trim_blank_chrs($array['information'])) == 0)
		{
			$errors[] = $lang->mods_create_project_missing_info;
		}
		
		// Validate licence
		if (!isset($array['licence']) || my_strlen(trim_blank_chrs($array['licence'])) == 0)
		{
			$errors[] = $lang->mods_create_project_missing_licence;
		}
		
		// Validate licence name
		if (!isset($array['licence_name']) || my_strlen(trim_blank_chrs($array['licence_name'])) == 0)
		{
			$errors[] = $lang->mods_create_project_missing_licence_name;
		}
		
		// Validate versions
		$vs = explode(',', MYBB_VERSIONS);
		if (!empty($array['versions']))
		{
			$array['versions'] = explode(',', $array['versions']);
			foreach ($array['versions'] as $version)
			{
				if (!in_array($version, $vs))
				{
					$errors[] = $lang->mods_create_project_invalid_version;
					break;
				}
			}
		}
		else
		{
			$errors[] = $lang->mods_create_project_invalid_version;
		}
		
		// Validate category
		$mods = Mods::getInstance();
		$category = $mods->categories->getByID($array['cid']);
		if (empty($category))
		{
			$errors[] = $lang->mods_create_project_missing_subcat;
		}
		
		// Validate PayPal email
		// Check if an email address has actually been entered.
		if ($mybb->input['paypal'] != '')
		{
			if(trim_blank_chrs($mybb->input['paypal']) == '')
			{
				$errors[] = $lang->mods_paypal_invalid_email;
			}
			else {
				// Check if this is a proper email address.
				if(!validate_email_format($mybb->input['paypal']))
				{
					$errors[] = $lang->mods_paypal_invalid_email;
				}
			}
		}
		
		// Is the bug tracker link valid?
		if ($mybb->input['bugtracker_link'] != '')
		{
			// Yumi's regex for validating URL's, I (Pirata Nervo) take no credit for this.
			if (!preg_match('~'.str_replace('~', '\\~', '^(https?)\://([a-z.\-_]+)(/[^\r\n"<>&]*)?$').'~si', $mybb->input['bugtracker_link']))
			{
				$errors[] = $lang->mods_bugtracking_invalid_link;
			}
		}
		
		// Is the support link valid?
		if ($mybb->input['support_link'] != '')
		{
			// Yumi's regex for validating URL's, I (Pirata Nervo) take no credit for this.
			if (!preg_match('~'.str_replace('~', '\\~', '^(https?)\://([a-z.\-_]+)(/[^\r\n"<>&]*)?$').'~si', $mybb->input['support_link']))
			{
				$errors[] = $lang->mods_support_invalid_link;
			}
		}
		
		// Validate notifications
		if ($mybb->input['notifications'] != 1 && $mybb->input['notifications'] != 2 && $mybb->input['notifications'] != 3)
		{
			$errors[] = $lang->mods_notifications_invalid;
		}
		
		return $errors;
	}
	
	/*
	 * Creates a new project
	 * @param $array array Contains the data to be inserted into the database. Format: field => value [...]
	 * @param $autoapprove boolean Auto-approves projects if set to true, otherwise (default) they will be set to awaiting approval.
	 * @return int The ID of the created row.
	 * NOTE: This function should only be run after the validation has run.
	*/
	public function create($array,$autoapprove=false)
	{
		if (empty($array) || !is_array($array))
			return false;
			
		global $db, $mybb;
			
		// Escape everything
		$array = array_map(array($db, 'escape_string'), $array);
		
		if ($autoapprove)
			$array['approved'] = 1;
			
		$array['uid'] = (int)$mybb->user['uid'];
		$array['submitted'] = TIME_NOW;
		
		$id = $db->insert_query('mods_projects', $array);
		
		// update category counters IF our mod was auto approved
		if ($autoapprove === true)
		{
			$mods = Mods::getInstance();
			
			// Get cached categories because if we've run the validate function then the category has been cached already
			$cachedcats = $mods->categories->getCached();
			if (!empty($cachedcats[$array['cid']]))
			{
				$cat = $cachedcats[$array['cid']];
			}
			else {
				$cat = $mods->categories->getByID($array['cid']);
				if (empty($cat))
				{
					return $id; // we've just created a download in a category which does not exist - next time you better validate it using the validate function
				}
			}
			
			$mods->categories->updateByID(array('counter' => $cat['counter']+1), $array['cid']);
		}
		
		return $id;
	}
	
	/*
	 * Deletes a Project by its ID.
	 * @param $id int The ID of the project to be deleted.
	*/
	// TODO: Delete child Bugs, Suggestions and Builds
	public function deleteByID($id)
	{
		$id = (int)$id;
		if ($id <= 0)
			return false;
			
		global $db;
			
		return $db->delete_query('mods_projects', 'pid=\''.intval($id).'\'');
	}
	
	/*
	 * Updates a Project by its ID.
	 * @param $array array Contains the data to be updated.
	 * @param $id int The ID of the project to be updated.
	*/
	public function updateByID($array, $id)
	{
		if (empty($array) || !is_array($array))
			return false;
			
		$id = (int)$id;
		if ($id <= 0)
			return false;
		
		global $db;
			
		// Escape everything
		$array = array_map(array($db, 'escape_string'), $array);
		
		// Update the cache if it contains the entry we're updating
		if (!empty($this->cache[$id]))
		{
			foreach ($array as $key => $element)
			{
				$this->cache[$id][$key] = $element;
			}
		}
		
		return $db->update_query('mods_projects', $array, 'pid=\''.intval($id).'\'');
	}
	
	/*
	 * Get Projects created by a certain user.
	 * @param $uid int The user ID.
	 * @param $count boolean (optional) If set to true it will only count the number of projects created.
	 * @return int if $count is set to true or array if $count is set to false.
	*/
	public function getCreatedBy($uid,$count=false)
	{
		global $db;
		
		$uid = (int)$uid;
		if ($uid <= 0)
			return false;
			
		if ($count===true)
			$sql = 'COUNT(*) as projects';
		else
			$sql = '*';
			
		$query = $db->simple_select('mods_projects', $sql, 'uid=\''.intval($uid).'\'');
		
		if ($count===true)
			return (int)$db->fetch_field($query, 'projects');
		else
		{
			$projects = array();
			while ($project = $db->fetch_array($query))
				$projects[] = $project;
			
			return $projects;
		}
	}
	
	/*
	 * Get Projects created in a certain category.
	 * @param $uid int The category ID.
	 * @param $count boolean (optional) If set to true it will only count the number of projects created.
	 * @return int if $count is set to true or array if $count is set to false.
	*/
	public function getCreatedIn($id,$count=false)
	{
		global $db;
		
		$id = (int)$id;
		if ($id <= 0)
			return false;
			
		if ($count===true)
			$sql = 'COUNT(*) as projects';
		else
			$sql = '*';
			
		$query = $db->simple_select('mods_projects', $sql, 'cid=\''.intval($id).'\'');
		
		if ($count===true)
			return (int)$db->fetch_field($query, 'projects');
		else
		{
			$projects = array();
			while ($project = $db->fetch_array($query))
				$projects[] = $project;
			
			return $projects;
		}
	}
	
	/*
	 * Get Projects in which the user is collaborating
	 * @param $uid int The user ID.
	 * @param $count boolean (optional) If set to true it will only count the number of projects the user is collaborating in.
	 * @return int if $count is set to true or array if $count is set to false.
	*/
	public function getCollaboratedBy($uid,$count=false)
	{
		global $db;
		
		$uid = (int)$uid;
		if ($uid <= 0)
			return false;
			
		if ($count===true)
			$sql = 'COUNT(*) as projects';
		else
			$sql = '*';
			
		$uid = (int)$uid;
			
		$query = $db->simple_select('mods_projects', $sql, "CONCAT(',',collaborators,',') LIKE '%,{$uid},%' OR (CONCAT(',',testers,',') LIKE '%,{$uid},%' AND bugtracker_link='' AND bugtracking=1)");
		
		if ($count===true)
			return (int)$db->fetch_field($query, 'projects');
		else
		{
			$projects = array();
			while ($project = $db->fetch_array($query))
				$projects[] = $project;
			
			return $projects;
		}
	}
	
	public function getBuildsOf($id)
	{
		$id = (int)$id;
		if ($id <= 0)
			return false;
			
		return $this->builds->getByID(intval($id));
	}
	
	public function approve($id)
	{
		$id = (int)$id;
		if ($id <= 0)
			return false;
			
		$this->updateByID(array('approved' => 1), 'pid=\''.intval($id).'\'');
	}
	
	public function unapprove($id)
	{
		$id = (int)$id;
		if ($id <= 0)
			return false;
			
		$this->updateByID(array('approved' => 0), 'pid=\''.intval($id).'\'');
	}
	
	// NOTE: should not work properly yet. Needs an update somewhere in the code (either or here or in the Builds class) to make sure it's the latest one.
	public function getStableBuildOf($id)
	{
		$id = (int)$id;
		if ($id <= 0)
			return false;
			
		$builds = $this->builds->getAll('id=\''.intval($id).'\' AND status=\'1\'');
		if (empty($builds))
			return false;
			
		$build = array_shift($builds);
		return $build;
	}
	
	// Generates a unique GUID
	public function generateGUID($codename)
	{
		// This should return a unique md5...
		return md5($codename);
	}
}