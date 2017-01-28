<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * @author    AskAmN
 * @package   MyBB 1.8
 * @since     1.8
 * @updated   22 April, 2014
 */

/**
 * WinCache Cache Handler
 */
class MyBB_WinCache
{
	/**
	 * Unique identifier representing this copy of MyBB
	 */
	public $unique_id;

	function __construct($silent=false)
	{
		global $mybb;

		if(!function_exists("wincache_ucache_get"))
		{
				//$mybb->trigger_generic_error("wincache_load_error");
				die('WinCache Extension not loaded');
		}
		return false;
	}

	/**
	 * Connect and initialize this handler.
	 *
	 * @return boolean True if successful, false on failure
	 */
	function connect()
	{
		global $mybb;

		// Set a unique identifier for all queries in case other forums on this server also use this cache handler
		$this->unique_id = md5(MYBB_ROOT);

		return true;
	}

	/**
	 * Retrieve an item from the cache.
	 *
	 * @param string The name of the cache
	 * @param boolean True if we should do a hard refresh
	 * @return mixed Cache data if successful, false if failure
	 */

	function fetch($name, $hard_refresh=false)
	{
		$data = wincache_ucache_get($this->unique_id."_".$name);
		if($data === false)
		{
			return false;
		}

		return @unserialize($data);
	}

	/**
	 * Write an item to the cache.
	 *
	 * @param string The name of the cache
	 * @param mixed The data to write to the cache item
	 * @return boolean True on success, false on failure
	 */
	function put($name, $contents)
	{
		return wincache_ucache_set($this->unique_id."_".$name, 0);
	}

	/**
	 * Delete a cache
	 *
	 * @param string The name of the cache
	 * @return boolean True on success, false on failure
	 */
	function delete($name)
	{
		return wincache_ucache_delete($this->unique_id."_".$name);
	}

	/**
	 * Disconnect from the cache
	 */
	function disconnect()
	{
		return true;
	}

	function size_of($name)
	{
		global $lang;

		return $lang->na;
	}
}
?>
