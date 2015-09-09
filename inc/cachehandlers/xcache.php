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
 * Xcache Cache Handler
 */
class xcacheCacheHandler
{
	/**
	 * Unique identifier representing this copy of MyBB
	 *
	 * @var string
	 */
	public $unique_id;

	/**
	 * @param bool $silent ignored
	 */
	function xcacheCacheHandler($silent=false)
	{
		global $mybb;

		if(!function_exists("xcache_get"))
		{
			// Check if our DB engine is loaded
			if(!extension_loaded("XCache"))
			{
				// Throw our super awesome cache loading error
				$mybb->trigger_generic_error("xcache_load_error");
				die;
			}
		}
	}

	/**
	 * Connect and initialize this handler.
	 *
	 * @return boolean True if successful, false on failure
	 */
	function connect()
	{
		// Set a unique identifier for all queries in case other forums on this server also use this cache handler
		$this->unique_id = md5(MYBB_ROOT);

		return true;
	}

	/**
	 * Retrieve an item from the cache.
	 *
	 * @param string $name The name of the cache
	 * @param boolean $hard_refresh True if we should do a hard refresh
	 * @return mixed Cache data if successful, false if failure
	 */
	function fetch($name, $hard_refresh=false)
	{
		if(!xcache_isset($this->unique_id."_".$name))
		{
			return false;
		}
		return xcache_get($this->unique_id."_".$name);
	}

	/**
	 * Write an item to the cache.
	 *
	 * @param string $name The name of the cache
	 * @param mixed $contents The data to write to the cache item
	 * @return boolean True on success, false on failure
	 */
	function put($name, $contents)
	{
		return xcache_set($this->unique_id."_".$name, $contents);
	}

	/**
	 * Delete a cache
	 *
	 * @param string $name The name of the cache
	 * @return boolean True on success, false on failure
	 */
	function delete($name)
	{
		return xcache_set($this->unique_id."_".$name, "", 1);
	}

	/**
	 * Disconnect from the cache
	 *
	 * @return bool
	 */
	function disconnect()
	{
		return true;
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	function size_of($name)
	{
		global $lang;

		return $lang->na;
	}
}
