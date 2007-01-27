<?php

class diskCacheHandler
{
	/**
	 * Connect and initialize this handler.
	 *
	 * @return boolean True if successful, false on failure
	 */
	function connect()
	{
		if(!@is_writable(MYBB_ROOT."inc/cache"))
		{
			return false;
		}
		
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
		if(!@file_exists(MYBB_ROOT."/inc/cache/{$name}.php"))
		{
			return false;
		}
		
		if($hard_refresh == true)
		{
			@include(MYBB_ROOT."/inc/cache/{$name}.php");
		}
		else
		{
			@include_once(MYBB_ROOT."/inc/cache/{$name}.php");
		}
		
		// Return data
		return $$name;
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
		if(!is_writable(MYBB_ROOT."inc/cache"))
		{
			$mybb->trigger_generic_error("cache_no_write");
			return false;
		}

		$cache_file = fopen(MYBB_ROOT."inc/cache/{$name}.php", "w") or $mybb->trigger_generic_error("cache_no_write");
		
		$cache_contents = "<?php\n\n/** MyBB Generated Cache - Do Not Alter\n * Cache Name: $name\n * Generated: ".gmdate("r")."\n*/\n\n";
		$cache_contents .= "\$$name = ".var_export($contents, true).";\n\n ?>";
		
		fwrite($cache_file, $cache_contents);
		fclose($cache_file);
		
		return true;
	}
	
	/**
	 * Delete a cache
	 *
	 * @param string The name of the cache
	 * @return boolean True on success, false on failure
	 */
	function delete($name)
	{
		return @unlink(MYBB_ROOT."/inc/cache{$name}.php");
	}
	
	/**
	 * Disconnect from the cache
	 */
	function disconnect()
	{
		return true;
	}
}