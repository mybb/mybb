<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

class pluginSystem
{
	var $hooks;

	function load()
	{
		global $cache, $plugins;
		//$pluginlist = $cache->read("plugins");
		$pluginlist['active'][] = "hello";
		if(is_array($pluginlist['active']))
		{
			foreach($pluginlist['active'] as $plugin)
			{
				if($plugin != "" && file_exists("./inc/plugins/".$plugin.".php"))
				{
					require_once "./inc/plugins/".$plugin.".php";
				}
			}
		}
	}

	function add_hook($hook, $function, $file="", $priority=10)
	{
		// Check to see if we already have this hook running at this priority
		if(is_array($this->hooks[$hook][$priority][$function]))
		{
			return true;
		}

		// Add the hook
		$this->hooks[$hook][$priority][] = array(
			"function" => $function,
			"file" => $file
			);
		return true;
	}

	function run_hooks($hook, $arguments="")
	{
		if(!is_array($this->hooks[$hook]))
		{
			return $arguments;
		}

		ksort($this->hooks[$hook]);
		foreach($this->hooks[$hook] as $priority => $hooks)
		{
			if(is_array($hooks))
			{
				foreach($hooks as $hook)
				{
					if($hook['file'])
					{
						require_once $file;
					}
					$oldreturnargs = $returnargs;
					$returnargs = call_user_func($hook['function'], $arguments);
					if($returnargs)
					{
						$arguments = $returnargs;
					}
				}
			}
		}
		return $arguments;
	}

	function remove_hook($hook, $function, $file="", $priority=10)
	{
		//@unset($this->hooks[$hook][$priority][$function]);
	}
}
?>