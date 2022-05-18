<?php

/**
 * MyBB 1.9
 * Copyright 2022 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Gets the hierarchy of themelets present in the filesystem.
 *
 * @return array The themelet hierarchy where the first index is mode ('devdist'
 *               or 'current'), the second is type ('themes' or 'plugins'), with,
 *               for plugins, the value being an array of codenames, and, for
 *               themes, the value being an array of arrayed theme parents
 *               indexed by theme codename.
 */
function get_themelet_hierarchy()
{
	global $cache, $plugins_cache;

	static $themelet_hierarchy;

	if(!empty($themelet_hierarchy))
	{
		return $themelet_hierarchy;
	}

	$themelet_hierarchy = [
		'devdist' => [
			'themes'  => [],
			'plugins' => [],
		],
		'current' => [
			'themes'  => [],
			'plugins' => [],
		],
	];

	// Iterate through theme directories in the filesystem,
	// adding their ancestors to a list unique to each.
	//
	// TODO Optimise this code: currently, all parents are queried for
	// each theme directory, leading to redundant filesystem reads
	// given that we technically only need to query each theme's parent once,
	// from which we can THEN put together the ancestor list for each theme.
	$themes_dir = MYBB_ROOT.'inc/themes/';
	if(is_dir($themes_dir) && ($dh = opendir($themes_dir)) !== false)
	{
		while(($codename = readdir($dh)) !== false)
		{
			if($codename == '.' || $codename == '..')
			{
				continue;
			}
			foreach(['devdist', 'current'] as $mode)
			{
				$themelet_hierarchy[$mode]['themes'][$codename] = [];
				if($codename == 'core.default')
				{
					continue;
				}
				if(is_dir($themes_dir.$codename.'/'.$mode))
				{
					$parent = $codename;
					do
					{
						$termination = false;
						$prop_file = $themes_dir.$parent.'/'.$mode.'/properties.json';
						if(is_readable($prop_file))
						{
							$json = file_get_contents($prop_file);
							$props = json_decode($json, true);
							if(is_array($props) && array_key_exists('parent', $props))
							{
								$parent = $props['parent'];
								if(in_array($parent, $themelet_hierarchy[$mode]['themes'][$codename]) || $parent === 'core.default')
								{
									$termination = true;
									break;
								}
								else
								{
									$themelet_hierarchy[$mode]['themes'][$codename][] = $parent;
								}
							}
							else
							{
								$termination = true;
							}
						}
						else
						{
							$termination = true;
						}
					} while(!$termination);
					$themelet_hierarchy[$mode]['themes'][$codename][] = 'core.default';
				}
			}
		}
		closedir($dh);
	}

	if(empty($plugins_cache) || !is_array($plugins_cache))
	{
		$plugins_cache = $cache->read('plugins');
	}
	$active_plugins = empty($plugins_cache['active']) ? [] : $plugins_cache['active'];

	foreach($active_plugins as $codename)
	{
		foreach(['devdist', 'current'] as $mode)
		{
			$themelet_hierarchy[$mode]['plugins'][] = $codename;
		}
	}

	// Earlier return possible
	return $themelet_hierarchy;
}

/**
 * Gets the correctly ordered (according to resource search priority)
 * directories of themelets in the filesystem.
 *
 * @param boolean $inc_devdist True to include `devdist` directories; otherwise
 *                             only include `current` directories.
 * @return array Indexed by themelet codename, the values are arrays with three
 *               entries: themelet filesystem directory, its codename, and whether
 *               or not it is a plugin themelet (otherwise it belongs to a theme).
 */
function get_themelet_dirs($inc_devdist = false)
{
	$themelet_hierarchy = get_themelet_hierarchy();
	$themelet_dirs = $plugin_themelet_dirs = [];
	$modes = [];
	if($inc_devdist)
	{
		$modes[] = 'devdist';
	}
	$modes[] = 'current';

	foreach($modes as $mode)
	{
		foreach($themelet_hierarchy[$mode]['plugins'] as $codename)
		{
			$themelet_dir = MYBB_ROOT.'inc/plugins/'.$codename.'/interface/'.$mode.'/ext';
			if(is_dir($themelet_dir) && is_readable($themelet_dir))
			{
				$plugin_themelet_dirs[] = [$themelet_dir, 'ext.'.$codename/*namespace*/, true/*is a plugin*/];
			}
		}
	}

	foreach($modes as $mode)
	{
		foreach($themelet_hierarchy[$mode]['themes'] as $codename => $parents)
		{
			$themelet_dir = MYBB_ROOT.'inc/themes/'.$codename.'/'.$mode;
			if(is_dir($themelet_dir) && is_readable($themelet_dir))
			{
				if (empty($themelet_dirs[$codename]))
				{
					$themelet_dirs[$codename] = [];
				}
				$themelet_dirs[$codename][] = [$themelet_dir, ''/*global namespace*/, false/*not a plugin*/];
				foreach($parents as $parent)
				{
					$themelet_dir = MYBB_ROOT.'inc/themes/'.$parent.'/'.$mode;
					if(is_dir($themelet_dir) && is_readable($themelet_dir))
					{
						$themelet_dirs[$codename][] = [$themelet_dir, ''/*global namespace*/, false/*not a plugin*/];
					}
				}
				// Insert plugin themelet directories just prior to the final theme themelet,
				// which should be the core theme.
				array_splice($themelet_dirs[$codename], count($themelet_dirs[$codename]) - 1, 0, $plugin_themelet_dirs);
			}
		}
	}

	return $themelet_dirs;
}

/**
 * Returns for the given theme those directories - in the correctly-ordered list
 * of Twig directories through which to search for resources - which actually
 * exist in the filesystem.
 *
 * @param string $theme The codename of the filesystem theme.
 * @param boolean $inc_devdist True to include `devdist` directories; otherwise
 *                             only include `current` directories.
 * @param boolean $use_themelet_cache True to try to get the list of themelet
 *                                    directories out of cache; otherwise
 *                                    rebuild it manually.
 */
function get_twig_dirs($theme, $inc_devdist = false, $use_themelet_cache = true)
{
	global $cache;

	// A list of valid theme components (other than `ext.[plugin-name]`)
	// within theme directories. The keys are the components. The values
	// are each component's Twig namespace. If the value is empty, then
	// the component has no namespace: it is in the global namespace.
	static $valid_comps = [
		'parser' => 'parser',
		'frontend' => '',
		'acp' => 'acp',
	];

	$twig_dirs = [];

	if(!$inc_devdist && $use_themelet_cache)
	{
		$themelet_dirs = $cache->read('themelet_dirs');
		if(empty($themelet_dirs))
		{
			$cache->update_themelet_dirs();
			$themelet_dirs = $cache->read('themelet_dirs');
		}
	}
	else
	{
		$themelet_dirs = get_themelet_dirs($inc_devdist);
	}

	if(!empty($themelet_dirs[$theme]))
	{
		foreach($themelet_dirs[$theme] as $entry)
		{
			list($theme_dir, $namespace1, $is_plugin) = $entry;
			if($is_plugin)
			{
				$twig_dir = $theme_dir.'/templates/';
				if(is_dir($twig_dir) && is_readable($twig_dir))
				{
					$twig_dirs[] = [$twig_dir, $namespace1];;
				}
			}
			else
			{
				foreach($valid_comps as $comp => $namespace2)
				{
					$twig_dir = $theme_dir.'/'.$comp.'/templates/';
					if(is_dir($twig_dir) && is_readable($twig_dir))
					{
						if(!empty($namespace2))
						{
							$twig_dirs[] = [$twig_dir, $namespace2];
						}
						else if(!empty($namespace1))
						{
							$twig_dirs[] = [$twig_dir, $namespace1];;
						}
						else
						{
							$twig_dirs[] = $twig_dir;
						}
					}
				}
				if(is_dir($theme_dir) && ($dh = opendir($theme_dir)) !== false)
				{
					while(($filename = readdir($dh)) !== false)
					{
						if(substr($filename, 0, 4) === 'ext.')
						{
							$pluginname = substr($filename, 4);
							$twig_dirs[] = [$theme_dir.'/'.$filename.'/templates/', 'ext.'.$pluginname];
						}
					}
					closedir($dh);
				}
			}
		}
	}

	return $twig_dirs;
}

/**
 * Determines via its manifest file the version of the current theme with name $codename.
 *
 * @param string $codename The codename (directory) of the theme for which to find the version.
 * @param string $err_msg Stores the messages for any errors encountered.
 * @return Mixed Boolean false if an error was encountered (in which case $err_msg will be set),
 *               and the version number as a string on success.
 */
function get_theme_version($codename, &$err_msg = '')
{
	$err_msg = '';
	$version = false;
	$themes_base = MYBB_ROOT.'inc/themes/';
	$manifest_file = $themes_base.$codename.'/current/manifest.json';
	if(is_readable($manifest_file))
	{
		$json = file_get_contents($manifest_file);
		$manifest = json_decode($json, true);
		if(is_array($manifest) && !empty($manifest['version']))
		{
			$version = $manifest['version'];
		}
	}
	if($version === false)
	{
		$err_msg = 'The manifest file at "'.htmlspecialchars_uni($manifest_file).'" either does not exist, is not readable, or is corrupt.';
	}

	return $version;
}

/**
 * Archives the current theme with name $codename.
 *
 * @param string $codename The codename (directory) of the theme to archive.
 * @param string $err_msg Stores the messages for any errors encountered.
 * @return boolean False if an error was encountered (in which case $err_msg will be set),
 *                 and true on success.
 */
function archive_theme($codename, &$err_msg = '')
{
	$err_msg = '';
	$version = get_theme_version($codename, $err_msg);
	if($version !== false)
	{
		$archive_base = MYBB_ROOT.'storage/themelets/'.$codename;
		if(!is_dir($archive_base))
		{
			mkdir($archive_base, 0777, true);
		}
		if(!is_readable($archive_base))
		{
			$err_msg = 'The archival directory "'.htmlspecialchars_uni($archive_base).'" either does not exist (and could not be created) or is not readable.';
		}
		else
		{
			$theme_dir = MYBB_ROOT.'inc/themes/'.$codename.'current';
			$archival_dir = $archive_base.'/'.$version;
			if(file_exists($archival_dir))
			{
				$err_msg = 'The archival directory "'.htmlspecialchars_uni($archival_dir).'" already exists.';
			}
			else
			{
				if(!rename($theme_dir, $archival_dir))
				{
					$err_msg = 'Failed to move "'.htmlspecialchars_uni($theme_dir).'" to "'.htmlspecialchars_uni($archival_dir).'".';
				}
			}
		}
	}

	return $err_msg ? false : true;
}
