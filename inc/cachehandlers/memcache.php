<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: memcache.php 4852 2010-04-04 17:38:33Z RyanGordon $
 */

/**
 * Memcache Cache Handler
 */
class memcacheCacheHandler
{
	/**
	 * The memcache server resource
	 */
	var $memcache;

	/**
	 * Unique identifier representing this copy of MyBB
	 */
	var $unique_id;
	
	function memcacheCacheHandler($silent=false)
	{
		if(!function_exists("memcache_connect"))
		{
			// Check if our DB engine is loaded
			if(!extension_loaded("Memcache"))
			{
				// Throw our super awesome cache loading error
				die("Your server does not have memcache support enabled.");
				$mybb->trigger_generic_error("sql_load_error");
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
		global $mybb;

		if(!$mybb->config['memcache_host'])
		{
			die("Please configure the memcache settings in inc/config.php before attempting to use this cache handler");
		}

		if(!$mybb->config['memcache_port'])
		{
			$mybb->config['memcache_port'] = "11211";
		}

		$this->memcache = @memcache_connect($mybb->config['memcache_host'], $mybb->config['memcache_port']);

		if(!$this->memcache)
		{
			die("Unable to connect to the memcache server on {$mybb->config['memcache_host']}:{$mybb->config['memcache_port']}. Are you sure it is running?");
		}

		// Set a unique identifier for all queries in case other forums are using the same memcache server
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
		$data = memcache_get($this->memcache, $this->unique_id."_".$name);

		if($data === false)
		{
			return false;
		}
		else
		{
			return $data;
		}
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
		return memcache_set($this->memcache, $this->unique_id."_".$name, $contents, MEMCACHE_COMPRESSED);
	}
	
	/**
	 * Delete a cache
	 *
	 * @param string The name of the cache
	 * @return boolean True on success, false on failure
	 */
	function delete($name)
	{
		return memcache_delete($this->memcache, $this->unique_id."_".$name);
	}
	
	/**
	 * Disconnect from the cache
	 */
	function disconnect()
	{
		@memcache_close($this->memcache);
	}
	
	function size_of($name)
	{
		global $lang;
		
		return $lang->na;
	}
}
?>