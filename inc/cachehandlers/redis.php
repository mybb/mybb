<?php
/**
 * MyBB 1.8
 * Copyright 2020 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Redis Cache Handler
 */
class redisCacheHandler implements CacheHandlerInterface
{
	/**
	 * The redis server resource
	 *
	 * @var Redis
	 */
	public $redis;

	/**
	 * Unique identifier representing this copy of MyBB
	 *
	 * @var string
	 */
	public $unique_id;

	function __construct()
	{
		global $mybb;

		// Check if extension is loaded
		if(!extension_loaded("Redis"))
		{
			// Throw our super awesome cache loading error
			$mybb->trigger_generic_error("redis_load_error");
			die;
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

		$this->redis = new Redis;

		if(!$mybb->config['redis']['host'])
		{
			$message = "Please configure the redis settings in inc/config.php before attempting to use this cache handler";
			$error_handler->trigger($message, MYBB_CACHEHANDLER_LOAD_ERROR);
			die;
		}
		if($mybb->config['redis']['port'])
		{
			$ret = $this->redis->pconnect($mybb->config['redis']['host'], $mybb->config['redis']['port']);
		}
		else
		{
			$ret = $this->redis->pconnect($mybb->config['redis']['host']);
		}


		if(!$ret)
		{
			$message = "Unable to connect to the redis server on {$mybb->config['redis']['host']}:{$mybb->config['redis']['port']}. Are you sure it is running?";
			$error_handler->trigger($message, MYBB_CACHEHANDLER_LOAD_ERROR);
			die;
		}

		// Set a unique identifier for all queries in case other forums are using the same redis server
		$this->unique_id = md5(MYBB_ROOT);

		return true;
	}

	/**
	 * Retrieve an item from the cache.
	 *
	 * @param string $name The name of the cache
	 * @return mixed Cache data if successful, false if failure
	 */
	function fetch($name)
	{
		$data = $this->redis->get($this->unique_id."_".$name);

		if($data === false)
		{
			return false;
		}

		// use PHP's own unserialize() for performance reasons
		return unserialize($data, array('allowed_classes' => false));
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
		return $this->redis->set($this->unique_id."_".$name, serialize($contents));
	}

	/**
	 * Delete a cache
	 *
	 * @param string $name The name of the cache
	 * @return boolean True on success, false on failure
	 */
	function delete($name)
	{
		return $this->redis->del($this->unique_id."_".$name);
	}

	/**
	 * Disconnect from the cache
	 */
	function disconnect()
	{
		@$this->redis->close();
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	function size_of($name='')
	{
		global $lang;

		return $lang->na;
	}
}

