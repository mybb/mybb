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
 * Memcached Cache Handler
 */
class memcachedCacheHandler
{
	/**
	 * The memcached server resource
	 */
	public $memcached;

	/**
	 * Unique identifier representing this copy of MyBB
	 */
	public $unique_id;

	function memcachedCacheHandler($silent=false)
	{
		global $mybb;

		if(!function_exists("memcached_connect"))
		{
			// Check if our Memcached extension is loaded
			if(!extension_loaded("Memcached"))
			{
				// Throw our super awesome cache loading error
				$mybb->trigger_generic_error("memcached_load_error");
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
		global $mybb, $error_handler;

		$this->memcached = new Memcached;

		if($mybb->config['memcache']['host'])
		{
			$mybb->config['memcache'][0] = $mybb->config['memcache'];
			unset($mybb->config['memcache']['host']);
			unset($mybb->config['memcache']['port']);
		}

		foreach($mybb->config['memcache'] as $memcached)
		{
			if(!$memcached['host'])
			{
				$message = "Please configure the memcache settings in inc/config.php before attempting to use this cache handler";
				$error_handler->trigger($message, MYBB_CACHEHANDLER_LOAD_ERROR);
				die;
			}

			if(!isset($memcached['port']))
			{
				$memcached['port'] = "11211";
			}

			$this->memcached->addServer($memcached['host'], $memcached['port']);

			if(!$this->memcached)
			{
				$message = "Unable to connect to the memcached server on {$memcached['memcache_host']}:{$memcached['memcache_port']}. Are you sure it is running?";
				$error_handler->trigger($message, MYBB_CACHEHANDLER_LOAD_ERROR);
				die;
			}
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
		$data = $this->memcached->get($this->unique_id."_".$name);

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
		return $this->memcached->set($this->unique_id."_".$name, $contents);
	}

	/**
	 * Delete a cache
	 *
	 * @param string The name of the cache
	 * @return boolean True on success, false on failure
	 */
	function delete($name)
	{
		return $this->memcached->delete($this->unique_id."_".$name);
	}

	/**
	 * Disconnect from the cache
	 */
	function disconnect()
	{
		@$this->memcached->close();
	}

	function size_of($name)
	{
		global $lang;

		return $lang->na;
	}
}

