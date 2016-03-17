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
 * Disk Cache Handler
 */
class diskCacheHandler implements CacheHandlerInterface
{
	/**
	 * Connect and initialize this handler.
	 *
	 * @return boolean True if successful, false on failure
	 */
	function connect()
	{
		if(!@is_writable(MYBB_ROOT."cache"))
		{
			return false;
		}

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
		if(!@file_exists(MYBB_ROOT."/cache/{$name}.php"))
		{
			return false;
		}

		@include(MYBB_ROOT."/cache/{$name}.php");

		// Return data
		return $$name;
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
		global $mybb;
		if(!is_writable(MYBB_ROOT."cache"))
		{
			$mybb->trigger_generic_error("cache_no_write");
			return false;
		}

		$cache_file = fopen(MYBB_ROOT."cache/{$name}.php", "w") or $mybb->trigger_generic_error("cache_no_write");
		flock($cache_file, LOCK_EX);
		$cache_contents = "<?php\ndeclare(encoding='UTF-8');\n\n/** MyBB Generated Cache - Do Not Alter\n * Cache Name: $name\n * Generated: ".gmdate("r")."\n*/\n\n";
		$cache_contents .= "\$$name = ".var_export($contents, true).";\n\n?>";
		fwrite($cache_file, $cache_contents);
		flock($cache_file, LOCK_UN);
		fclose($cache_file);

		return true;
	}

	/**
	 * Delete a cache
	 *
	 * @param string $name The name of the cache
	 * @return boolean True on success, false on failure
	 */
	function delete($name)
	{
		return @unlink(MYBB_ROOT."/cache/{$name}.php");
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
	 * Select the size of the disk cache
	 *
	 * @param string $name The name of the cache
	 * @return integer the size of the disk cache
	 */
	function size_of($name='')
	{
		if($name != '')
		{
			return @filesize(MYBB_ROOT."/cache/{$name}.php");
		}
		else
		{
			$total = 0;
			$dir = opendir(MYBB_ROOT."/cache");
			while(($file = readdir($dir)) !== false)
			{
				if($file == "." || $file == ".." || $file == ".svn" || !is_file(MYBB_ROOT."/cache/{$file}"))
				{
					continue;
				}

				$total += filesize(MYBB_ROOT."/cache/{$file}");
			}
			return $total;
		}
	}
}
