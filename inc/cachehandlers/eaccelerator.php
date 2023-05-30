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
 * eAccelerator Cache Handler
 */
class eacceleratorCacheHandler implements CacheHandlerInterface
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

		if(!function_exists("eaccelerator_get"))
		{
			// Check if our DB engine is loaded
			if(!extension_loaded("Eaccelerator"))
			{
				// Throw our super awesome cache loading error
				$mybb->trigger_generic_error("eaccelerator_load_error");
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
	 * @return mixed Cache data if successful, false if failure
	 */
	function fetch($name)
	{
		$data = eaccelerator_get($this->unique_id."_".$name);
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
		eaccelerator_lock($this->unique_id."_".$name);
		$status = eaccelerator_put($this->unique_id."_".$name, serialize($contents));
		eaccelerator_unlock($this->unique_id."_".$name);
		return $status;
	}

	/**
	 * Delete a cache
	 *
	 * @param string $name The name of the cache
	 * @return boolean True on success, false on failure
	 */
	function delete($name)
	{
		return eaccelerator_rm($this->unique_id."_".$name);
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
		global $lang;

		return $lang->na;
	}
}
