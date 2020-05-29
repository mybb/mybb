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
 * APCu Cache Handler
 */
class apcuCacheHandler implements CacheHandlerInterface
{
	/**
	 * Unique identifier representing this copy of MyBB
	 *
	 * @var string
	 */
	public $unique_id;

	function __construct()
	{
		global $mybb;

		if(!function_exists('apcu_fetch'))
		{
			// Check if the APCu extension is loaded
			if(!extension_loaded('apcu'))
			{
				$mybb->trigger_generic_error('apcu_load_error');
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
	 * Connect and initialize this handler.
	 *
	 * @param string $name
	 * @return mixed The cache content if successful, false on failure
	 */
	function fetch($name)
	{
		if(apcu_exists("{$this->unique_id}_{$name}"))
		{
			return apcu_fetch("{$this->unique_id}_{$name}");
		}

		return false;
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
		return apcu_store("{$this->unique_id}_{$name}", $contents);
	}

	/**
	 * Delete a cache
	 *
	 * @param string $name The name of the cache
	 * @return boolean True on success, false on failure
	 */
	function delete($name)
	{
		return apcu_delete("{$this->unique_id}_{$name}");
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
	function size_of($name='')
	{
		if(empty($name))
		{
			// get total size of cache, using an APCUIterator
			$iterator = new APCUIterator("/^{$this->unique_id}_.*/");
			return $iterator->getTotalSize();
		}
		
		global $lang;

		$info = apcu_cache_info();

		if(empty($info['cache_list']))
		{
			return $lang->na;
		}

		$actual_name = "{$this->unique_id}_{$name}";

		foreach($info['cache_list'] as $entry)
		{
			if(isset($entry['info']) && $entry['info'] === $actual_name && isset($entry['mem_size']))
			{
				return $entry['mem_size'];
			}
		}

		return $lang->na;
	}
}
