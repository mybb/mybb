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
 * Cache Handler Interface
 */
interface CacheHandlerInterface
{
	/**
	 * Connect and initialize this handler.
	 *
	 * @return boolean True if successful, false on failure
	 */
	function connect();

	/**
	 * Connect and initialize this handler.
	 *
	 * @param string $name
	 * @return boolean True if successful, false on failure
	 */
	function fetch($name);

	/**
	 * Write an item to the cache.
	 *
	 * @param string $name The name of the cache
	 * @param mixed $contents The data to write to the cache item
	 * @return boolean True on success, false on failure
	 */
	function put($name, $contents);

	/**
	 * Delete a cache
	 *
	 * @param string $name The name of the cache
	 * @return boolean True on success, false on failure
	 */
	function delete($name);

	/**
	 * Disconnect from the cache
	 *
	 * @return bool
	 */
	function disconnect();

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	function size_of($name='');
}
