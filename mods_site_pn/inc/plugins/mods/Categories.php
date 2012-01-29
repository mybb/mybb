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

class Categories implements ModsInterface  
{
	private $cache = array();
	private $parents = array('plugins' => 'plugins', 'themes' => 'themes', 'Graphics' => 'graphics', 'resources' => 'resources');
	
	/*
	 * Gets all categories.
	 * @param $where string Contains the WHERE condition (optional).
	 * @param $cache boolean Whether to cache the retreived list of categories or not.
	 * Note: If param $where is not empty, the list will not be cached regardless of the parameter $cache
	*/
	public function getAll($where='',$cache=true)
	{
		global $db;
		
		if (!empty($where) && !is_string($where))
			return false;
		
		$entries = array();
		
		$query = $db->simple_select('mods_categories', '*', $where, array('order_by' => 'disporder', 'order_dir' => 'asc'));
		while ($e = $db->fetch_array($query))
			$entries[$e['cid']] = $e;
		
		// Only cache if we're actually query ALL of them.
		if ($cache !== false && empty($where))
			$this->cache = $entries;
			
		return $entries;
	}
	
	/*
	 * Get cached categories.
	*/
	public function getCached()
	{
		return $this->cache;
	}
	
	/*
	 * Get a single category by its ID.
	*/
	public function getByID($id,$cache=true)
	{
		global $db;
		
		if (empty($id))
			return false;
			
		// Does the entry exist in our cache?
		if (!empty($this->cache[$id]))
			return $this->cache[$id];
		
		$query = $db->simple_select('mods_categories', '*', 'cid=\''.intval($id).'\'');
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
	 * Validates the parent category.
	*/
	public function validateParent($parent)
	{
		if (!in_array($parent, $this->parents))
			return false;
		else
			return true;
	}
	
	/*
	 * Gets the parent categories list.
	*/
	public function getParents()
	{
		return $this->parents;
	}
	
	/*
	 * Creates a new category. 
	*/
	public function create($array)
	{
		if (empty($array) || !is_array($array))
			return false;
			
		global $db;
			
		// Escape everything
		$array = array_map(array($db, 'escape_string'), $array);
		
		return $db->insert_query('mods_categories', $array);
	}
	
	/*
	 * Deletes an existing category.
	*/
	// TODO: Delete child Projects
	public function deleteByID($id)
	{
		$id = (int)$id;
		if ($id <= 0)
			return false;
			
		global $db;
			
		return $db->delete_query('mods_categories', 'cid=\''.intval($id).'\'');
	}
	
	/*
	 * Updates an existing category.
	*/
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
		
		return $db->update_query('mods_categories', $array, 'cid=\''.intval($id).'\'');
	}
}