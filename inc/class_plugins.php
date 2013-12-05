<?php
/**
 * MyBB 1.8
 * Copyright 2013 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
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
	public $hooks;
	/**
	 * The current hook which we're in (if any)
	 *
	 * @var string
	 */
	public $current_hook;

	/**
	 * Load all plugins.
	 *
	 */
	function load()
	{
		global $cache, $plugins;

		$pluginlist = $cache->read("plugins");
		if(!empty($pluginlist['active']) && is_array($pluginlist['active']))
		{
			foreach($pluginlist['active'] as $plugin)
			{
				if($plugin != "" && file_exists(MYBB_ROOT . "inc/plugins/" . $plugin . ".php"))
				{
					require_once MYBB_ROOT . "inc/plugins/" . $plugin . ".php";
				}
			}
		}
	}

	/**
	 * Add a hook onto which a plugin can be attached.
	 *
	 * @param string $hook     The hook name.
	 * @param string $function The function of this hook.
	 * @param int    $priority The priority this hook has.
	 * @param string $file     The optional file belonging to this hook.
	 *
	 * @return boolean Always true.
	 */
	function add_hook($hook, $function, $priority = 10, $file = "")
	{
		// Check to see if we already have this hook running at this priority
		if(!empty($this->hooks[$hook][$priority][$function]) && is_array($this->hooks[$hook][$priority][$function]))
		{
			return true;
		}

		if($function instanceof Closure)
		{
			// The hook is a Closure, we will add it to a special array key as they ae handled differently.
			$this->hooks[$hook][$priority]['closures'][] = $function;
		}
		else
		{
			// Add the hook
			$this->hooks[$hook][$priority][$function] = array(
				"function" => $function,
				"file"     => $file
			);
		}

		return true;
	}

	/**
	 * Run the hooks that have plugins.
	 *
	 * @param string $hook      The name of the hook that is run.
	 * @param string $arguments The argument for the hook that is run. The passed value MUST be a variable
	 *
	 * @return string The arguments for the hook.
	 */
	function run_hooks($hook, &$arguments = "")
	{
		if(!isset($this->hooks[$hook]) || !is_array($this->hooks[$hook]))
		{
			return $arguments;
		}
		$this->current_hook = $hook;
		ksort($this->hooks[$hook]);
		foreach($this->hooks[$hook] as $priority => $hooks)
		{
			if(is_array($hooks))
			{
				foreach($hooks as $key => $hook)
				{
					if($key == 'closures')
					{
						foreach($hook as $callable)
						{
							$returnargs = call_user_func($callable, $arguments);

							if($returnargs)
							{
								$arguments = $returnargs;
							}
						}

					}
					else
					{
						if($hook['file'])
						{
							require_once $hook['file'];
						}

						$func = $hook['function'];

						$returnargs = $func($arguments);

						if($returnargs)
						{
							$arguments = $returnargs;
						}
					}
				}
			}
		}
		$this->current_hook = '';

		return $arguments;
	}

	/**
	 * Remove a specific hook.
	 *
	 * @param string The name of the hook.
	 * @param string The function of the hook.
	 * @param string The filename of the plugin.
	 * @param int    The priority of the hook.
	 */
	function remove_hook($hook, $function, $file = "", $priority = 10)
	{
		// Check to see if we don't already have this hook running at this priority
		if(!isset($this->hooks[$hook][$priority][$function]))
		{
			return true;
		}
		unset($this->hooks[$hook][$priority][$function]);
	}

	/**
	 * Establishes if a particular plugin is compatible with this version of MyBB.
	 *
	 * @param string The name of the plugin.
	 *
	 * @return boolean TRUE if compatible, FALSE if incompatible.
	 */
	function is_compatible($plugin)
	{
		global $mybb;

		// Ignore potentially missing plugins.
		if(!file_exists(MYBB_ROOT . "inc/plugins/" . $plugin . ".php"))
		{
			return true;
		}

		require_once MYBB_ROOT . "inc/plugins/" . $plugin . ".php";

		$info_func = "{$plugin}_info";
		if(!function_exists($info_func))
		{
			return false;
		}
		$plugin_info = $info_func();

		// No compatibility set or compatibility = * - assume compatible
		if(!$plugin_info['compatibility'] || $plugin_info['compatibility'] == "*")
		{
			return true;
		}
		$compatibility = explode(",", $plugin_info['compatibility']);
		foreach($compatibility as $version)
		{
			$version = trim($version);
			$version = str_replace("*", ".+", preg_quote($version));
			$version = str_replace("\.+", ".+", $version);
			if(preg_match("#{$version}#i", $mybb->version_code))
			{
				return true;
			}
		}

		// Nothing matches
		return false;
	}
}

?>