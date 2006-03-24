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

	/**
	 * The hooks to which plugins can be attached.
	 *
	 * @var array
	 */
	var $hooks;

	/**
	 * Load all plugins.
	 *
	 */
	function load()
	{
		global $cache, $plugins;
		$pluginlist = $cache->read("plugins");
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

	/**
	 * Add a hook onto which a plugin can be attached.
	 *
	 * @param string The hook name.
	 * @param string The function of this hook.
	 * @param int The priority this hook has.
	 * @param string The optional file belonging to this hook.
	 * @return boolean Always true.
	 */
	function add_hook($hook, $function, $priority=10, $file="")
	{
		// Check to see if we already have this hook running at this priority
		if(is_array($this->hooks[$hook][$priority][$function]))
		{
			return true;
		}

		// Add the hook
		$this->hooks[$hook][$priority][$function] = array(
			"function" => $function,
			"file" => $file
		);
		return true;
	}

	/**
	 * Run the hooks that have plugins.
	 *
	 * @param string The name of the hook that is run.
	 * @param string The argument for the hook that is run.
	 * @return string The arguments for the hook.
	 */
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
						require_once $hook['file'];
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

	/**
	* Remove a specific hook.
	*
	* @param string The name of the hook.
	* @param string The function of the hook.
	* @param string The filename of the plugin.
	* @param int The priority of the hook.
	*/
	function remove_hook($hook, $function, $file="", $priority=10)
	{
		//@unset($this->hooks[$hook][$priority][$function]);
	}
}
?>