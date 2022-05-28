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
	$parents = [
		'devdist' => [],
		'current' => [],
	];

	// Iterate through theme directories in the filesystem,
	// determining the parent of each.
	$themes_dir = MYBB_ROOT.'inc/themes/';
	if(is_dir($themes_dir) && ($dh = opendir($themes_dir)) !== false)
	{
		while(($theme_code = readdir($dh)) !== false)
		{
			if($theme_code == '.' || $theme_code == '..')
			{
				continue;
			}
			foreach(['devdist', 'current'] as $mode)
			{
				$prop_file = $themes_dir.$theme_code.'/'.$mode.'/properties.json';
				if(is_readable($prop_file))
				{
					$json = file_get_contents($prop_file);
					$props = json_decode($json, true);
					if(is_array($props) && array_key_exists('parent', $props))
					{
						$parents[$mode][$theme_code] = $props['parent'];
					}
					else if($theme_code == 'core.default')
					{
						$parents[$mode][$theme_code] = '';
					}
				}
			}
		}
		closedir($dh);
	}

	// Generate a list of ancestors for each filesystem theme.
	foreach(['devdist', 'current'] as $mode)
	{
		foreach($parents[$mode] as $child => $parent)
		{
			$themelet_hierarchy[$mode]['themes'][$child] = [];
			if($child !== 'core.default')
			{
				while($parent && !in_array($parent, $themelet_hierarchy[$mode]['themes'][$child]))
				{
					$themelet_hierarchy[$mode]['themes'][$child][] = $parent;
					$parent = isset($parents[$parent]) ? $parents[$parent] : null;
				}
				$themelet_hierarchy[$mode]['themes'][$child][] = 'core.default';
			}
		}
	}

	if(empty($plugins_cache) || !is_array($plugins_cache))
	{
		$plugins_cache = $cache->read('plugins');
	}
	$active_plugins = empty($plugins_cache['active']) ? [] : $plugins_cache['active'];

	// Generate a list of unique active plugins which might or might not have themelets.
	foreach($active_plugins as $plugin_code)
	{
		foreach(['devdist', 'current'] as $mode)
		{
			$themelet_hierarchy[$mode]['plugins'][] = $plugin_code;
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
		foreach($themelet_hierarchy[$mode]['plugins'] as $plugin_code)
		{
			$themelet_dir = MYBB_ROOT.'inc/plugins/'.$plugin_code.'/interface/'.$mode.'/ext';
			if(is_dir($themelet_dir) && is_readable($themelet_dir))
			{
				$plugin_themelet_dirs[] = [$themelet_dir, 'ext.'.$plugin_code/*namespace*/, true/*is a plugin*/];
			}
		}
	}

	foreach($modes as $mode)
	{
		foreach($themelet_hierarchy[$mode]['themes'] as $theme_code => $parents)
		{
			$themelet_dir = MYBB_ROOT.'inc/themes/'.$theme_code.'/'.$mode;
			if(is_dir($themelet_dir) && is_readable($themelet_dir))
			{
				if (empty($themelet_dirs[$theme_code]))
				{
					$themelet_dirs[$theme_code] = [];
				}
				$themelet_dirs[$theme_code][] = [$themelet_dir, ''/*global namespace*/, false/*not a plugin*/];
				foreach($parents as $parent)
				{
					$themelet_dir = MYBB_ROOT.'inc/themes/'.$parent.'/'.$mode;
					if(is_dir($themelet_dir) && is_readable($themelet_dir))
					{
						$themelet_dirs[$theme_code][] = [$themelet_dir, ''/*global namespace*/, false/*not a plugin*/];
					}
				}
				// Insert plugin themelet directories just prior to the final theme themelet,
				// which should be the core theme.
				array_splice($themelet_dirs[$theme_code], count($themelet_dirs[$theme_code]) - 1, 0, $plugin_themelet_dirs);
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
							$twig_dir = $theme_dir.'/'.$filename.'/templates/';
							if(is_dir($twig_dir) && is_readable($twig_dir))
							{
								$twig_dirs[] = [$twig_dir, $filename];
							}
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
 * Determines via its manifest file the version of the current theme with name $theme_code.
 *
 * @param string $theme_code The codename (directory) of the theme for which to find the version.
 * @param string $err_msg Stores the messages for any errors encountered.
 * @return Mixed Boolean false if an error was encountered (in which case $err_msg will be set),
 *               and the version number as a string on success.
 */
function get_theme_version($theme_code, &$err_msg = '')
{
	$err_msg = '';
	$version = false;
	$themes_base = MYBB_ROOT.'inc/themes/';
	$manifest_file = $themes_base.$theme_code.'/current/manifest.json';
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
 * Archives the current theme with codename $theme_code.
 *
 * @param string $theme_code The codename (directory) of the theme to archive.
 * @param string $err_msg Stores the messages for any errors encountered.
 * @return boolean False if an error was encountered (in which case $err_msg will be set),
 *                 and true on success.
 */
function archive_theme($theme_code, &$err_msg = '')
{
	$err_msg = '';
	$version = get_theme_version($theme_code, $err_msg);
	if($version !== false)
	{
		$archive_base = MYBB_ROOT.'storage/themelets/'.$theme_code;
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
			$theme_dir = MYBB_ROOT.'inc/themes/'.$theme_code.'/current';
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

/**
 * Retrieve the stylesheets for the given themelet from its `properties.json` file.
 *
 * @param string $codename   The codename (directory) of the theme or plugin whose stylesheets should
 *                           be retrieved.
 * @param boolean $is_plugin If true, $codename represents a plugin codename; else a theme.
 * @param boolean $devdist   If true, try to use the properties.json file in the `devdist` directory
 *                           before trying that in the `current` directory.
 * @return array The first index is the name of a script to which to attach a stylesheet; the second is
 *               the action which conditionally triggers attachment ("global" indicates any action
 *               including none), and the third is an array of stylesheet filenames.
 */
function get_themelet_stylesheets($codename, $is_plugin = false, $devdist = false)
{
	$stylesheets = [];
	$modes = [];
	if($devdist)
	{
		$modes[] = 'devdist';
	}
	$modes[] = 'current';

	foreach($modes as $mode)
	{
		if($is_plugin)
		{
			$prop_file = MYBB_ROOT.'inc/plugins/'.$codename.'/interface/'.$mode.'/properties.json';
		}
		else
		{
			$prop_file = MYBB_ROOT.'inc/themes/'.$codename.'/'.$mode.'/properties.json';
		}
		if(is_readable($prop_file))
		{
			$json = file_get_contents($prop_file);
			$props = json_decode($json, true);
			if(is_array($props) && array_key_exists('stylesheets', $props))
			{
				foreach($props['stylesheets'] as $sheet => $arr)
				{
					foreach($arr as $script_actions)
					{
						$actions = $script_actions['actions'];
						$script  = $script_actions['script' ];
						if(empty($actions))
						{
							$actions = ['global'];
						}
						foreach($actions as $action)
						{
							if(empty($stylesheets[$script]))
							{
								$stylesheets[$script] = [];
							}
							if(empty($stylesheets[$script][$action]))
							{
								$stylesheets[$script][$action] = [];
							}
							$stylesheets[$script][$action][] = $sheet;
						}
					}
				}
			}
			break;
		}
	}

	return $stylesheets;
}

/**
 * Resolves a themelet resource for the current theme, checking up the hierarchy if it does not
 * exist in the themelet directory itself, and supporting resources in `ext.[pluginname]` theme
 * directories. When appropriate, caches the resource under `cache/themes/themeid` where `themeid`
 * is the database ID of the current theme. First minifies the resource if it is a stylesheet and
 * the core setting to minify CSS is enabled.
 *
 * @param string $specifier Stipulates which resource to load in the current theme.
 *                          Resources for the current theme are specified in the format:
 *                          "~component:directory:filename", where "component" is, e.g., "frontend",
 *                          "acp", or "parser", "directory" is, e.g., "styles" or "images", and
 *                          "filename" is, e.g., "main.css" or "logo.png".
 *                          Resources for plugins are specified in the format:
 *                          "!plugin_codename:directory:filename", where "directory" and "filename"
 *                          are as above, and "plugin_codename is self-explanatory.
 * @param boolean $use_themelet_cache True to try to get the list of themelet directories out of
 *                                    cache; otherwise rebuild it manually.
 * @param boolean $return_resource True to return the resource's contents; false to return a path to
 *                                 the resource relative to the MyBB root directory.
 * @return string If $return_resource is true, this is the resource's contents, generated
 *                dynamically, avoiding the cache. Otherwise, if development mode is off, it is a
 *                path to the cached file, relative to the MyBB root directory. Otherwise, it is
 *                the relative path `resource.php?specifier=[specifier]`.
 */
function resolve_themelet_resource($specifier, $use_themelet_cache = true, $return_resource = false)
{
	global $mybb, $cache, $theme, $plugins;

	if(!$mybb->settings['themelet_dev_mode'] && $use_themelet_cache)
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
		$themelet_dirs = get_themelet_dirs($mybb->settings['themelet_dev_mode']);
	}

	if(my_strtolower(substr($specifier, -8)) === '.min.css')
	{
		$specifier = my_strtolower(substr($specifier, 0, -7)).'css';
		$minify = true;
	}
	else
	{
		$minify = (!empty($mybb->settings['minifycss'])
		           &&
		           my_strtolower(get_extension($resource_path)) === 'css'
		);
	}

	$resource_path = false;
	$theme_code = $theme['package'];

	if($specifier[0] === '~' && substr_count($specifier, ':') == 2)
	{
		// We have a theme resource which requires resolution. Resolve it.
		list($res_comp, $res_dir, $res_name) = explode(':', substr($specifier, 1));

		if(!empty($themelet_dirs[$theme_code]))
		{
			foreach($themelet_dirs[$theme_code] as $entry)
			{
				list($theme_dir, $namespace1, $is_plugin) = $entry;
				if(!$is_plugin)
				{
					$path_to_test = $theme_dir.'/'.$res_comp.'/'.$res_dir.'/'.$res_name;
					if(is_readable($path_to_test))
					{
						$resource_path = $path_to_test;
						break;
					}
				}
			}
		}
	}
	else if($specifier[0] === '!' && substr_count($specifier, ':') == 2)
	{
		// We have a plugin's themelet resource which requires resolution. Resolve it.
		list($plugin_code, $res_dir, $res_name) = explode(':', substr($specifier, 1));

		if(!empty($themelet_dirs[$theme_code]))
		{
			foreach([false, true] as $check_plugin)
			{
				foreach($themelet_dirs[$theme_code] as $entry)
				{
					$scss_path = false;
					list($theme_dir, $namespace1, $is_plugin) = $entry;
					if($check_plugin === $is_plugin)
					{
						if(!$is_plugin)
						{
							$path_to_test = $theme_dir.'/ext.'.$plugin_code.'/'.$res_dir.'/'.$res_name;
							if(is_readable($path_to_test))
							{
								$resource_path = $path_to_test;
								break;
							}
							else if(my_strtolower(substr($path_to_test, -4)) === '.css')
							{
								$scss_path = substr($path_to_test, 0, -3).'scss';
								if(is_readable($scss_path))
								{
									$resource_path = $path_to_test;
									break;
								}
							}
						}
						else
						{
							$path_to_test = $theme_dir.'/'.$res_dir.'/'.$res_name;
							if(is_readable($path_to_test))
							{
								$resource_path = $path_to_test;
								break;
							}
							else if(my_strtolower(substr($path_to_test, -4)) === '.css')
							{
								$scss_path = substr($path_to_test, 0, -3).'scss';
								if(is_readable($scss_path))
								{
									$resource_path = $path_to_test;
									break;
								}
							}
						}
					}
				}
				if($resource_path)
				{
					break;
				}
			}
		}
	}

	// TODO Compile SCSS into CSS if required, in which case $scss_path will be set to the full
	// path to the root SCSS file to compile into CSS). In that case, update the check for a
	// stale cache by comparing cached file (if any) timestamp to the SCSS file(s) (there may be
	// includes) rather than the CSS file.


	$use_cache = !$return_resource;
	$needs_cache = false;
	if($mybb->settings['themelet_dev_mode'] == false)
	{
		$cache_dir = MYBB_ROOT.'cache/themes/'.$theme['tid'];
		if(!is_dir($cache_dir))
		{
			mkdir($cache_dir, 0777, true);
		}
		$cache_file = $cache_dir.'/'.$specifier;
		if($minify)
		{
			$cache_file = substr($cache_file, 0, -3).'min.css';
		}
		if(!is_file($cache_file))
		{
			$needs_cache = true;
		}
		else
		{
			$cache_time = filemtime($cache_file);
			$source_time = filemtime($resource_path);
			if($source_time > $cache_time)
			{
				$needs_cache = true;
			}
		}
	}
	else
	{
		$use_cache = false;
	}

	$resource = '';

	if(($needs_cache || $return_resource) && $minify)
	{
		$stylesheet = file_get_contents($resource_path);
		$plugins->run_hooks('css_start', $stylesheet);

		if(!empty($mybb->settings['minifycss']))
		{
			global $config;
			require_once MYBB_ROOT.$config['admin_dir'].'/inc/functions_themes.php';
			$stylesheet = minify_stylesheet($stylesheet);
			$minified = true;
		}

		$plugins->run_hooks('css_end', $stylesheet);
	}

	if($needs_cache)
	{
		if(!$minify)
		{
			$use_cache = copy($resource_path, $cache_file);
		}
		else
		{
			$use_cache = file_put_contents($cache_file, $stylesheet);
		}
	}

	if($return_resource)
	{
		if($minify)
		{
			$resource = $stylesheet;
		}
		else
		{
			$resource = file_get_contents($resource_path);
		}
	}

	return $return_resource ? $resource : ($use_cache ? substr($cache_file, strlen(MYBB_ROOT)) : 'resource.php?specifier='.urlencode($specifier));
}

/**
 * Installs the core 1.9 theme to the database. For use within installation/upgrade routines.
 *
 * @param string $theme_code The codename (directory) of the core 1.9 theme to install. At time of
 *                           writing, the only valid value for this parameter is 'core.default'.
 * @param boolean $devdist   If true, use the theme's `devdist` directory; otherwise, use its
 *                           `current` directory.
 */
function install_core_19_theme_to_db($theme_code, $devdist = false)
{
	global $cache, $db;

	$mode = $devdist ? 'devdist' : 'current';
	$core_theme_title = 'Default';
	$manifest_file = MYBB_ROOT.'inc/themes/core.default/'.$mode.'/manifest.json';
	if(is_readable($manifest_file))
	{
		$json = file_get_contents($manifest_file);
		$manifest = json_decode($json, true);
		if(is_array($manifest) && !empty($manifest['extra']['title']))
		{
			$core_theme_title = $manifest['extra']['title'];
		}
	}

	$stylesheets     = get_themelet_stylesheets($theme_code, /* $is_plugin = */false, $devdist);
	$stylesheets_ser = my_serialize($stylesheets);
	$stylesheets_esc = $db->escape_string($stylesheets_ser);

	$disporder = [];
	$order = 1;
	$seen = [];
	foreach($stylesheets as $script => $arr)
	{
		foreach($arr as $action => $sheets)
		{
			foreach($sheets as $sheet)
			{
				if(!in_array($sheet, $seen))
				{
					$disporder[$sheet] = $order++;
					$seen[] = $sheet;
				}
			}
		}
	}
	$properties = my_serialize(['disporder' => $disporder]);
	$properties_esc = $db->escape_string($properties);

	$core_theme_title_esc = $db->escape_string($core_theme_title);

	// An empty `version` field indicates to use `current` (or `devdist` if in development mode and that directory exists).
	$theme = ['package' => $theme_code, 'version' => '', 'title' => $core_theme_title, 'properties' => $properties, 'stylesheets' => $stylesheets_ser, 'allowedgroups' => 'all'];
	$theme_esc = $theme;
	$theme_esc['title'      ] = $core_theme_title_esc;
	$theme_esc['properties' ] = $properties_esc;
	$theme_esc['stylesheets'] = $stylesheets_esc;
	$tid = $db->insert_query('themes', $theme_esc);
	$theme['tid'] = $tid;
	$cache->update_default_theme($theme);
	$cache->update_themelet_dirs();
}
