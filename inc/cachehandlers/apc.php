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
 * APC Cache Handler
 */
class apcCacheHandler
{
	/**
	 * Unique identifier representing this copy of MyBB
	 */
	public $unique_id;

	function __construct($silent=false)
	{
		global $mybb;

		if(!function_exists("apc_fetch"))
		{
			// Check if our DB engine is loaded
			if(!extension_loaded("apc"))
			{
				// Throw our super awesome cache loading error
				$mybb->trigger_generic_error("apc_load_error");
				die;
			}
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
	 * Connect and initialize this handler.
	 *
	 * @return boolean True if successful, false on failure
	 */
	function fetch($name, $hard_refresh=false)
	{
		if(apc_exists($this->unique_id."_".$name))
		{
			$data = apc_fetch($this->unique_id."_".$name);
			return unserialize($data);
		}

		return false;
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
		$status = apc_store($this->unique_id."_".$name, serialize($contents));
		return $status;
	}

	/**
	 * Delete a cache
	 *
	 * @param string The name of the cache
	 * @return boolean True on success, false on failure
	 */
	function delete($name)
	{
		return apc_delete($this->unique_id."_".$name);
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
