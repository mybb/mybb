<?php

/**
 * MyBB 1.9
 * Copyright 2022 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

use ScssPhp\ScssPhp\Compiler;

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

    if (!empty($themelet_hierarchy)) {
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
    if (is_dir($themes_dir) && ($dh = opendir($themes_dir)) !== false) {
        while (($theme_code = readdir($dh)) !== false) {
            if ($theme_code == '.' || $theme_code == '..') {
                continue;
            }
            foreach (['devdist', 'current'] as $mode) {
                if ($theme_code == 'core.default') {
                    $parents[$mode][$theme_code] = '';
                } else {
                    $prop_file = $themes_dir.$theme_code.'/'.$mode.'/properties.json';
                    if (is_readable($prop_file)) {
                        $json = file_get_contents($prop_file);
                        $props = json_decode($json, true);
                        if (is_array($props) && array_key_exists('parent', $props)) {
                            $parents[$mode][$theme_code] = $props['parent'];
                        }
                    }
                }
            }
        }
        closedir($dh);
    }

    // Generate a list of ancestors for each filesystem theme.
    foreach (['devdist', 'current'] as $mode) {
        foreach ($parents[$mode] as $child => $parent) {
            $themelet_hierarchy[$mode]['themes'][$child] = [];
            if ($child !== 'core.default') {
                while ($parent && !in_array($parent, $themelet_hierarchy[$mode]['themes'][$child])) {
                    $themelet_hierarchy[$mode]['themes'][$child][] = $parent;
                    $parent = isset($parents[$mode][$parent]) ? $parents[$mode][$parent] : null;
                }
            }
        }
    }

    if (empty($plugins_cache) || !is_array($plugins_cache)) {
        $plugins_cache = $cache->read('plugins');
    }
    $active_plugins = empty($plugins_cache['active']) ? [] : $plugins_cache['active'];

    // Generate a list of unique active plugins which might or might not have themelets.
    foreach ($active_plugins as $plugin_code) {
        foreach (['devdist', 'current'] as $mode) {
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
    if ($inc_devdist) {
        $modes[] = 'devdist';
    }
    $modes[] = 'current';

    foreach ($modes as $mode) {
        foreach ($themelet_hierarchy[$mode]['plugins'] as $plugin_code) {
            $themelet_dir = MYBB_ROOT.'inc/plugins/'.$plugin_code.'/interface/'.$mode.'/ext';
            if (is_dir($themelet_dir) && is_readable($themelet_dir)) {
                $plugin_themelet_dirs[] = [$themelet_dir, 'ext.'.$plugin_code/*namespace*/, true/*is a plugin*/];
            }
        }
    }

    foreach ($modes as $mode) {
        foreach ($themelet_hierarchy[$mode]['themes'] as $theme_code => $parents) {
            $themelet_dir = MYBB_ROOT.'inc/themes/'.$theme_code.'/'.$mode;
            if (is_dir($themelet_dir) && is_readable($themelet_dir)) {
                if (empty($themelet_dirs[$theme_code])) {
                    $themelet_dirs[$theme_code] = [];
                }
                $themelet_dirs[$theme_code][] = [$themelet_dir, ''/*global namespace*/, false/*not a plugin*/];
                foreach ($parents as $parent) {
                    $themelet_dir = MYBB_ROOT.'inc/themes/'.$parent.'/'.$mode;
                    if (is_dir($themelet_dir) && is_readable($themelet_dir)) {
                        $themelet_dirs[$theme_code][] = [$themelet_dir, ''/*global namespace*/, false/*not a plugin*/];
                    }
                }
                // Insert plugin themelet directories just prior to the final theme themelet,
                // which should be the core theme.
                array_splice(
                    $themelet_dirs[$theme_code],
                    count($themelet_dirs[$theme_code]) - 1,
                    0,
                    $plugin_themelet_dirs
                );
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

    if (!$inc_devdist && $use_themelet_cache) {
        $themelet_dirs = $cache->read('themelet_dirs');
        if (empty($themelet_dirs)) {
            $cache->update_themelet_dirs();
            $themelet_dirs = $cache->read('themelet_dirs');
        }
    } else {
        $themelet_dirs = get_themelet_dirs($inc_devdist);
    }

    if (!empty($themelet_dirs[$theme])) {
        foreach ($themelet_dirs[$theme] as $entry) {
            list($theme_dir, $namespace1, $is_plugin) = $entry;
            if ($is_plugin) {
                $twig_dir = $theme_dir.'/templates/';
                if (is_dir($twig_dir) && is_readable($twig_dir)) {
                    $twig_dirs[] = [$twig_dir, $namespace1];
                    ;
                }
            } else {
                foreach ($valid_comps as $comp => $namespace2) {
                    $twig_dir = $theme_dir.'/'.$comp.'/templates/';
                    if (is_dir($twig_dir) && is_readable($twig_dir)) {
                        if (!empty($namespace2)) {
                            $twig_dirs[] = [$twig_dir, $namespace2];
                        } elseif (!empty($namespace1)) {
                            $twig_dirs[] = [$twig_dir, $namespace1];
                            ;
                        } else {
                            $twig_dirs[] = $twig_dir;
                        }
                    }
                }
                if (is_dir($theme_dir) && ($dh = opendir($theme_dir)) !== false) {
                    while (($filename = readdir($dh)) !== false) {
                        if (substr($filename, 0, 4) === 'ext.') {
                            $twig_dir = $theme_dir.'/'.$filename.'/templates/';
                            if (is_dir($twig_dir) && is_readable($twig_dir)) {
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
 * Determine via its manifest file the version of the current theme with name $theme_code.
 *
 * @param string $theme_code The codename (directory) of the theme for which to find the version.
 * @param string $err_msg Stores the messages for any errors encountered.
 * @return Mixed Boolean false if an error was encountered (in which case $err_msg will be set),
 *               and the version number as a string on success.
 */
function get_theme_version($theme_code, &$err_msg = '')
{
    $err_msg = '';
    $manifest_file = MYBB_ROOT."inc/themes/$theme_code/current/manifest.json";
    $manifest = read_json_file($manifest_file, $err_msg, false);
    if (!isset($manifest['version'])) {
        if (!$err_msg) {
            $err_msg = 'The manifest file at "'.htmlspecialchars_uni($manifest_file).
            '" does not supply a valid `version` property (or is non-existent, unreadable, or corrupt.';
        }
        return false;
    } else {
        $version = $manifest['version'];
        return $version;
    }
}

/**
 * Archives the `current` version of the themelet with (plugin) codename $codename.
 *
 * @param string $codename The codename (directory) of the theme or plugin to archive.
 * @param boolean $is_plugin_themelet True indicates to interpret $codename as a plugin codename, and to archive
 *                                    that plugin's `current` themelet; otherwise, $codename represents the codename
 *                                    of a theme proper.
 * @param string $err_msg Stores the messages for any errors encountered.
 * @return boolean False if an error was encountered (in which case $err_msg will be set),
 *                 and true on success.
 */
function archive_themelet($codename, $is_plugin_themelet = false, &$err_msg = '')
{
    $err_msg = '';
    if ($is_plugin_themelet) {
        $plugininfo = read_json_file(MYBB_ROOT."inc/plugins/$codename/plugin.json", $err_msg, false);
        $version = isset($plugininfo['version']) ? $plugininfo['version'] : false;
    } else {
        $version = get_theme_version($codename, $err_msg);
    }
    if ($version !== false) {
        $archive_base = MYBB_ROOT.'storage/themelets/'.($is_plugin_themelet ? 'ext.' : '').$codename;
        if (!is_dir($archive_base)) {
            mkdir($archive_base, 0777, true);
        }
        if (!is_readable($archive_base)) {
            $err_msg = 'The archival directory "'.htmlspecialchars_uni($archive_base).
              '" either does not exist (and could not be created) or is not readable.';
        } else {
            if ($is_plugin_themelet) {
                $themelet_dir = MYBB_ROOT.'inc/plugins/'.$codename.'/interface/current';
            } else {
                $themelet_dir = MYBB_ROOT.'inc/themes/'.$codename.'/current';
            }
            $archival_dir = $archive_base.'/'.$version;
            if (file_exists($archival_dir)) {
                // Don't delete an existing archive of this themelet version unless we can't make a backup copy of it.
                $max_tries = 100;
                for ($i = 1; $i <= $max_tries; $i++) {
                    if (rename($archival_dir, $archival_dir.' (copy '.$i.')')) {
                        break;
                    }
                }
                if ($i > $max_tries) {
                    if (!rmdir_recursive($archival_dir)) {;
                        $err_msg = 'The archival directory "'.htmlspecialchars_uni($archival_dir).
                                   '" already exists and could neither be renamed nor deleted.';
                        return false;
                    }
                }
            }
            if (!rename($themelet_dir, $archival_dir)) {
                $err_msg = 'Failed to move "'.htmlspecialchars_uni($themelet_dir).
                    '" to "'.htmlspecialchars_uni($archival_dir).'".';
            }
        }
    }

    // Earlier return possible
    return $err_msg ? false : true;
}

/**
 * Retrieve the stylesheets for the given themelet from its `resources.json` file.
 *
 * @param string $codename   The codename (directory) of the theme or plugin whose stylesheets should
 *                           be retrieved.
 * @param boolean $is_plugin If true, $codename represents a plugin codename; else a theme.
 * @param boolean $devdist   If true, try to use the resources.json file in the `devdist` directory
 *                           before trying that in the `current` directory.
 * @return array The first index is the name of a script to which to attach a stylesheet; the second is
 *               the action which conditionally triggers attachment ("global" indicates any action
 *               including none), and the third is an array of stylesheet filenames.
 */
function get_themelet_stylesheets($codename, $is_plugin = false, $devdist = false)
{
    $stylesheets = [];
    $modes = [];
    if ($devdist) {
        $modes[] = 'devdist';
    }
    $modes[] = 'current';

    foreach ($modes as $mode) {
        if ($is_plugin) {
            $res_file = MYBB_ROOT.'inc/plugins/'.$codename.'/interface/'.$mode.'/resources.json';
        } else {
            $res_file = MYBB_ROOT.'inc/themes/'.$codename.'/'.$mode.'/resources.json';
        }
        if (is_readable($res_file)) {
            $json = file_get_contents($res_file);
            $res_arr = json_decode($json, true);
            if (is_array($res_arr) && array_key_exists('stylesheets', $res_arr)) {
                foreach ($res_arr['stylesheets'] as $sheet => $arr) {
                    foreach ($arr as $script_actions) {
                        $actions = $script_actions['actions'];
                        $script  = $script_actions['script' ];
                        if (empty($actions)) {
                            $actions = ['global'];
                        }
                        foreach ($actions as $action) {
                            if (empty($stylesheets[$script])) {
                                $stylesheets[$script] = [];
                            }
                            if (empty($stylesheets[$script][$action])) {
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
 * subdirectories.
 *
 * When appropriate, caches the resource under `cache/themes/themeid` where `themeid`
 * is the database ID of the current theme.
 *
 * If a CSS resource is requested and it does not exist but a SCSS file with the same name other
 * than extension exists, then it auto-compiles the SCSS into CSS.
 *
 * If a resource ending in `.min.css` is specified, or the core setting to minify CSS is enabled
 * then it minifies the CSS.
 *
 * @param string $specifier Stipulates which resource to load in the current theme.
 *                          Resources for the current theme are specified in the format:
 *                          "~t~component:directory:filename", where "component" is, e.g., "frontend",
 *                          "acp", or "parser", "directory" is, e.g., "styles" or "images", and
 *                          "filename" is, e.g., "main.css" or "logo.png".
 *                          Resources for plugins are specified in the format:
 *                          "~p~plugin_codename:directory:filename", where "directory" and "filename"
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

    if (!function_exists('test_set_path')) {
        function test_set_path($path_to_test, &$resource_path, &$scss_path)
        {
            if (is_readable($path_to_test)) {
                $resource_path = $path_to_test;
                return true;
            } elseif (my_strtolower(substr($path_to_test, -4)) === '.css') {
                $scss_path_test = substr($path_to_test, 0, -3).'scss';
                if (is_readable($scss_path_test)) {
                    $resource_path = $path_to_test;
                    $scss_path = $scss_path_test;
                    return true;
                }
            }

            // Earlier returns possible
            return false;
        }
    }

    if (!$mybb->settings['themelet_dev_mode'] && $use_themelet_cache) {
        $themelet_dirs = $cache->read('themelet_dirs');
        if (empty($themelet_dirs)) {
            $cache->update_themelet_dirs();
            $themelet_dirs = $cache->read('themelet_dirs');
        }
    } else {
        $themelet_dirs = get_themelet_dirs($mybb->settings['themelet_dev_mode']);
    }

    if (my_strtolower(substr($specifier, -8)) === '.min.css') {
        $specifier = my_strtolower(substr($specifier, 0, -7)).'css';
        $minify = true;
    } else {
        $minify = (!empty($mybb->settings['minifycss'])
                   &&
                   my_strtolower(get_extension($specifier)) === 'css'
        );
    }

    $resource_path = $scss_path = false;
    $theme_code = $theme['package'];

    $spec_type_len = 3;
    if (my_strtolower(substr($specifier, 0, $spec_type_len)) === '~t~' && substr_count($specifier, ':') == 2) {
        // We have a theme resource which requires resolution. Resolve it.
        list($res_comp, $res_dir, $res_name) = explode(':', substr($specifier, $spec_type_len));

        if (!empty($themelet_dirs[$theme_code])) {
            foreach ($themelet_dirs[$theme_code] as $entry) {
                list($theme_dir, $codename, $is_plugin) = $entry;
                if (!$is_plugin) {
                    $path_to_test = $theme_dir.'/'.$res_comp.'/'.$res_dir.'/'.$res_name;
                    if (test_set_path($path_to_test, $resource_path, $scss_path)) {
                        break;
                    }
                }
            }
        }
    } elseif (my_strtolower(substr($specifier, 0, $spec_type_len)) === '~p~' && substr_count($specifier, ':') == 2) {
        // We have a plugin's themelet resource which requires resolution. Resolve it.
        list($plugin_code, $res_dir, $res_name) = explode(':', substr($specifier, $spec_type_len));

        if (!empty($themelet_dirs[$theme_code])) {
            // The plugin resource might be overridden in a theme.
            // We test this possibility first, then...
            foreach ($themelet_dirs[$theme_code] as $entry) {
                list($theme_dir, $codename, $is_plugin) = $entry;
                if (!$is_plugin) {
                    $path_to_test = $theme_dir.'/ext.'.$plugin_code.'/'.$res_dir.'/'.$res_name;
                    if (test_set_path($path_to_test, $resource_path, $scss_path)) {
                        break;
                    }
                }
            }
        }

        // ...look for the resource in the plugin's own themelet.
        // TODO: decide whether we should then (or first) check the directories of
        // other plugins, i.e., decide whether plugins should be able to override
        // the resources of other plugins.
        if (!$resource_path) {
            $path_to_test = MYBB_ROOT.'inc/plugins/'.$plugin_code.'/interface/current/ext/'.$res_dir.'/'.$res_name;
            test_set_path($path_to_test, $resource_path, $scss_path);
        }
    }

    if (!$resource_path) {
        return false;
    }

    $use_cache = !$return_resource && !$mybb->settings['themelet_dev_mode'];
    $needs_cache = $deps_file = false;

    if ($use_cache) {
        $cache_dir = MYBB_ROOT.'cache/themes/'.$theme['tid'];
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0777, true);
        }
        $cache_file = $minify? substr($cache_file, 0, -3).'min.css' : $cache_dir.'/'.$specifier;
        $deps_file = $cache_dir.'/'.$specifier.'.deps';
        $deps_existing = is_readable($deps_file) ? file($deps_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES): [];

        // The first entry in the .deps file is the primary resource...
        $deps_new = [$scss_path ? $scss_path : $resource_path];

        // ...which we check in case the resource now resolves to a different themelet than
        // when it was cached...
        if (!is_readable($cache_file) || !$deps_existing || /* ...here: */$deps_existing[0] != $resource_path) {
            $needs_cache = true;
        } else {
            $cache_time = filemtime($cache_file);
            foreach ($deps_existing as $dep_file) {
                $dep_time = filemtime($dep_file);
                if ($dep_time > $cache_time) {
                    $needs_cache = true;
                    break;
                }
            }
        }
    }

    if (($needs_cache || $return_resource) && ($minify || $scss_path)) {
        if ($scss_path) {
            $compiler = new Compiler();
            $result = $compiler->compileString(file_get_contents($scss_path), /*$path = */$scss_path);
            $stylesheet = $result->getCss();
            if ($needs_cache) {
                // Entries other than the first in the .deps file are for secondary
                // dependencies - in this case, SCSS @import files.
                $deps_new = array_merge($deps_new, $result->getIncludedFiles());
            }
        } else {
            $stylesheet = file_get_contents($resource_path);
        }

        $plugins->run_hooks('css_start', $stylesheet);

        if ($minify) {
            global $config;
            require_once MYBB_ROOT.$config['admin_dir'].'/inc/functions_themes.php';
            $stylesheet = minify_stylesheet($stylesheet);
            $minified = true;
        }

        $plugins->run_hooks('css_end', $stylesheet);
    }

    if ($needs_cache) {
        if (!$minify && !$scss_path) {
            copy($resource_path, $cache_file);
        } else {
            file_put_contents($cache_file, $stylesheet);
        }
        file_put_contents($deps_file, implode("\n", $deps_new));
    }

    $resource = '';

    if ($return_resource) {
        if ($minify || $scss_path) {
            $resource = $stylesheet;
        } elseif ($resource_path) {
            $resource = file_get_contents($resource_path);
        }
    }

    // Early return possible
    return $return_resource
             ? $resource
             : ($use_cache
                  ? substr($cache_file, strlen(MYBB_ROOT))
                  : 'resource.php?specifier='.urlencode($specifier)
               );
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
    if (is_readable($manifest_file)) {
        $json = file_get_contents($manifest_file);
        $manifest = json_decode($json, true);
        if (is_array($manifest) && !empty($manifest['extra']['title'])) {
            $core_theme_title = $manifest['extra']['title'];
        }
    }

    $stylesheets     = get_themelet_stylesheets($theme_code, /* $is_plugin = */false, $devdist);
    $stylesheets_ser = my_serialize($stylesheets);
    $stylesheets_esc = $db->escape_string($stylesheets_ser);

    $disporder = [];
    $order = 1;
    $seen = [];
    foreach ($stylesheets as $script => $arr) {
        foreach ($arr as $action => $sheets) {
            foreach ($sheets as $sheet) {
                if (!in_array($sheet, $seen)) {
                    $disporder[$sheet] = $order++;
                    $seen[] = $sheet;
                }
            }
        }
    }
    $properties = my_serialize(['disporder' => $disporder]);
    $properties_esc = $db->escape_string($properties);

    $core_theme_title_esc = $db->escape_string($core_theme_title);

    // An empty `version` field indicates to use `current` (or `devdist` if in development mode and that directory
    // exists).
    $theme = [
        'package' => $theme_code,
        'version' => '',
        'title' => $core_theme_title,
        'properties' => $properties,
        'stylesheets' => $stylesheets_ser,
        'allowedgroups' => 'all'
    ];
    $theme_esc = $theme;
    $theme_esc['title'      ] = $core_theme_title_esc;
    $theme_esc['properties' ] = $properties_esc;
    $theme_esc['stylesheets'] = $stylesheets_esc;
    $tid = $db->insert_query('themes', $theme_esc);
    $theme['tid'] = $tid;
    $cache->update_default_theme($theme);
    $cache->update_themelet_dirs();
}
