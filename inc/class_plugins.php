<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
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
	 */
	function load()
	{
		global $cache, $plugins;

		$pluginList = $cache->read("plugins");
		if(!empty($pluginList['active']) && is_array($pluginList['active']))
		{
			foreach($pluginList['active'] as $plugin)
			{
				if($plugin != "" && file_exists(MYBB_ROOT."inc/plugins/{$plugin}.php"))
				{
					require_once MYBB_ROOT."inc/plugins/{$plugin}.php";
				}
			}
		}
	}

	/**
	 * Add a hook onto which a plugin can be attached.
	 *
	 * @param string $hook The hook name.
	 * @param callable $function The function of this hook.
	 * @param int $priority The priority this hook has.
	 * @param string|null $file The optional file belonging to this hook.
	 *
	 * @return boolean Whether the hook was added.
	 */
	function add_hook(string $hook, callable $function, int $priority = 10, ?string $file = ""): bool
	{
		if(!is_callable($function))
		{
			// $function isn't a valid callable, can't add hook
			return false;
		}

		if(is_array($function))
		{
			// Array of class/static function name or object/instance function name
			$methodRepresentation = $this->getMethodRepresentation($function);

			// Check to see if we already have this hook running at this priority
			if(!empty($this->hooks[$hook][$priority][$methodRepresentation]) && is_array($this->hooks[$hook][$priority][$methodRepresentation]))
			{
				return true;
			}

			// Add the hook
			$this->hooks[$hook][$priority][$methodRepresentation] = array(
				'class_method' => $function,
				'file' => $file,
			);
		}
		else if(is_object($function) && $function instanceof \Closure)
		{
			// Closure
			$functionId = spl_object_hash($function);

			// Check to see if we already have this hook running at this priority
			if(!empty($this->hooks[$hook][$priority][$functionId]) && is_array($this->hooks[$hook][$priority][$functionId]))
			{
				return true;
			}

			// Add the hook
			$this->hooks[$hook][$priority][$functionId] = array(
				'function' => $function,
				'file' => $file,
			);
		}
		else
		{
			// Function name string
			// Check to see if we already have this hook running at this priority
			if(!empty($this->hooks[$hook][$priority][$function]) && is_array($this->hooks[$hook][$priority][$function]))
			{
				return true;
			}

			// Add the hook
			$this->hooks[$hook][$priority][$function] = array(
				'function' => $function,
				'file' => $file,
			);
		}

		return true;
	}

	/**
	 * get the method representation for the given callable array.
	 *
	 * @param array $arr A callable array.
	 *
	 * @return string The string representation of the callable array.
	 */
	private function getMethodRepresentation(array $arr): string
	{
		if(is_string($arr[0]))
		{
			// Static class method
			return sprintf('%s::%s', $arr[0], $arr[1]);
		}

		return sprintf('%s->%s', spl_object_hash($arr[0]), $arr[1]);
	}

	/**
	 * Run the hooks that have plugins.
	 *
	 * @param string $hook The name of the hook that is run.
	 * @param mixed $arguments The argument for the hook that is run. The passed value MUST be a variable
	 *
	 * @return mixed The arguments for the hook.
	 */
	function run_hooks(string $hook, &$arguments = "")
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
					if($hook['file'])
					{
						require_once $hook['file'];
					}

					if(array_key_exists('class_method', $hook))
					{
						$returnArgs = call_user_func_array($hook['class_method'], array(&$arguments));
					}
					else
					{
						$func = $hook['function'];

						$returnArgs = $func($arguments);
					}

					if($returnArgs)
					{
						$arguments = $returnArgs;
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
	 * @param string $hook The name of the hook.
	 * @param callable $function The function of the hook.
	 * @param int $priority The priority of the hook.
	 *
	 * @return bool Whether the hook was removed successfully.
	 */
	function remove_hook(string $hook, callable $function, int $priority = 10): bool
	{
		if(!is_callable($function))
		{
			return false;
		}

		if(is_array($function))
		{
			$methodRepresentation = $this->getMethodRepresentation($function);

			if(!isset($this->hooks[$hook][$priority][$methodRepresentation]))
			{
				return true;
			}

			unset($this->hooks[$hook][$priority][$methodRepresentation]);
		}
		else if(is_object($function) && $function instanceof \Closure)
		{
			// Closure
			$functionId = spl_object_hash($function);

			// Check to see if we don't already have this hook running at this priority
			if(!isset($this->hooks[$hook][$priority][$functionId]))
			{
				return true;
			}

			unset($this->hooks[$hook][$priority][$functionId]);
		}
		else
		{
			// Check to see if we don't already have this hook running at this priority
			if(!isset($this->hooks[$hook][$priority][$function]))
			{
				return true;
			}

			unset($this->hooks[$hook][$priority][$function]);
		}

		return true;
	}

	/**
	 * Establishes if a particular plugin is compatible with this version of MyBB.
	 *
	 * @param string $compatibilities The compatibility string of the plugin.
	 *
	 * @return boolean TRUE if compatible, FALSE if incompatible.
	 */
	function is_compatible(string $compatibilities): bool {
		return is_compatible($compatibilities);
	}
}

