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
 * Gets the hierarchy of themelets present in the filesystem, including their properties.
 *
 * @return array The themelet hierarchy where the first index is mode ('devdist' or 'current'), the
 *               second is type ('themes' or 'plugins'). For plugins, the value then is an array of
 *               codenames. For themes, the third index is the codename of a theme, and the fourth
 *               is either 'ancestors', 'children', or 'properties', with the value of each being
 *               the appropriate array (of codenames of ancestors/children, ordered for ancestors
 *               from nearest to furtherest, or of theme properties pulled out of its
 *               `theme.json` file).
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
    $props_a = [
        'devdist' => [],
        'current' => [],
    ];

    // Iterate through each theme directory in the filesystem, pulling its properties from its
    // `theme.json` file, and determining its parent.
    $themes_dir = MYBB_ROOT.'inc/themes/';
    if (is_dir($themes_dir) && ($dh = opendir($themes_dir)) !== false) {
        while (($theme_code = readdir($dh)) !== false) {
            if ($theme_code == '.' || $theme_code == '..') {
                continue;
            }
            foreach (['devdist', 'current'] as $mode) {
                if (!file_exists($themes_dir.$theme_code.'/'.$mode)) {
                    continue;
                }
                $theme_file = $themes_dir.$theme_code.'/'.$mode.'/theme.json';
                $props = [];
                if (is_readable($theme_file)) {
                    $json = file_get_contents($theme_file);
                    $props = json_decode($json, true);
                }
                $props_a[$mode][$theme_code] = $props;
                if ($theme_code == 'core.default' || !is_readable($theme_file)) {
                    $parents[$mode][$theme_code] = '';
                } else {
                    if (is_array($props) && array_key_exists('parent', $props)) {
                        $parents[$mode][$theme_code] = $props['parent'];
                    }
                }
            }
        }
        closedir($dh);
    }

    // Generate a list of ancestors and (direct) children for each filesystem theme.
    foreach (['devdist', 'current'] as $mode) {
        foreach ($parents[$mode] as $child => $parent) {
            $themelet_hierarchy[$mode]['themes'][$child] = ['ancestors' => [], 'children' => [], 'properties' => $props_a[$mode][$child]];
            if ($child !== 'core.default') {
                while ($parent && !in_array($parent, $themelet_hierarchy[$mode]['themes'][$child]['ancestors'])) {
                    $themelet_hierarchy[$mode]['themes'][$child]['ancestors'][] = $parent;
                    $parent = isset($parents[$mode][$parent]) ? $parents[$mode][$parent] : null;
                }
            }
            foreach ($parents[$mode] as $child2 => $parent2) {
                if ($parent2 === $child) {
                    $themelet_hierarchy[$mode]['themes'][$child]['children'][] = $child2;
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
 * @param boolean $inc_devdist True to include `devdist` directories; otherwise only include
 *                             `current` directories.
 * @return array Indexed by themelet codename, the values are arrays with four entries: themelet
 *               filesystem directory, its namespace, whether or not it is a plugin themelet
 *               (otherwise it belongs to a theme), and its codename.
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
                $plugin_themelet_dirs[] = [$themelet_dir, 'ext.'.$plugin_code/*namespace*/, true/*is a plugin*/, $plugin_code];
            }
        }
    }

    foreach ($modes as $mode) {
        foreach ($themelet_hierarchy[$mode]['themes'] as $theme_code => $relatives) {
            $parents = $relatives['ancestors'];
            $themelet_dir = MYBB_ROOT.'inc/themes/'.$theme_code.'/'.$mode;
            if (is_dir($themelet_dir) && is_readable($themelet_dir)) {
                if (empty($themelet_dirs[$theme_code])) {
                    $themelet_dirs[$theme_code] = [];
                }
                $themelet_dirs[$theme_code][] = [$themelet_dir, ''/*global namespace*/, false/*not a plugin*/, $theme_code];
                foreach ($parents as $parent) {
                    $themelet_dir = MYBB_ROOT.'inc/themes/'.$parent.'/'.$mode;
                    if (is_dir($themelet_dir) && is_readable($themelet_dir)) {
                        $themelet_dirs[$theme_code][] = [$themelet_dir, ''/*global namespace*/, false/*not a plugin*/, $parent];
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
 * Get the contents of the theme file of the theme with codename $theme_code.
 *
 * @param string $theme_code The codename (directory) of the theme.
 * @param string $err_msg Stores the messages for any errors encountered.
 *
 * @return array Empty on error, in which case $err_msg is set.
 */
function get_theme_properties($theme_code, &$err_msg = '')
{
    global $mybb;

    $mode = $mybb->settings['themelet_dev_mode'] ? 'devdist' : 'current';

    $err_msg = '';
    $theme_file = MYBB_ROOT."inc/themes/$theme_code/$mode/theme.json";
    $theme_data = read_json_file($theme_file, $err_msg, false);

    return $theme_data;
}

/**
 * Determine via its theme file the version of the theme with name $theme_code.
 *
 * @param string $theme_code The codename (directory) of the theme for which to find the version.
 * @param string $err_msg Stores the messages for any errors encountered.
 * @return Mixed Boolean false if an error was encountered (in which case $err_msg will be set),
 *               and the version number as a string on success.
 */
function get_theme_version($theme_code, &$err_msg = '')
{
    static $version = [];

    if (isset($version[$theme_code])) {
        return $version[$theme_code];
    }

    $theme_data = get_theme_properties($theme_code, $err_msg);
    $version[$theme_code] = empty($theme_data['version']) ? false : $theme_data['version'];

    return $version[$theme_code];
}

/**
 * Determine via its theme file the name of the theme with codename $theme_code.
 *
 * @param string $theme_code The codename (directory) of the theme for which to find the title.
 * @param string $err_msg Stores the messages for any errors encountered.
 * @return Mixed Boolean false if an error was encountered (in which case $err_msg will be set),
 *               and the version number as a string on success.
 */
function get_theme_name($theme_code, &$err_msg = '')
{
    static $name = [];

    if (isset($name[$theme_code])) {
        return $name[$theme_code];
    }

    $theme_data = get_theme_properties($theme_code, $err_msg);
    $name[$theme_code] = empty($theme_data['name']) ? false : $theme_data['name'];

    return $name[$theme_code];
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
 * @param string  $codename The codename (directory) of the theme or plugin whose stylesheets should
 *                          be retrieved.
 * @param string  $theme_color The selected theme color, if any, for which to retrieve stylesheets.
 * @param boolean $raw       If true, the raw stylesheet structure from the resources.json will be
 *                           returned. If false, a reformed structure will be returned.
 * @param boolean $is_plugin If true, $codename represents a plugin codename; else a theme.
 * @param boolean $inc_placeholders If true, include any empty placeholder entries for plugin
 *                                  stylesheets (these are for the purposes of ordering when *not*
 *                                  overriding the plugin stylesheet's properties).
 * @return array When $raw is false, the return is structured as follows:
 *                The first index is the name of a script to which to attach a stylesheet; the second is
 *                the action which conditionally triggers attachment ("global" indicates any action
 *                including none), and the third is an array of stylesheet filenames.
 *               When $raw is true, the return is structured as follows:
 *                The first index is the stylesheet filename; the second is an array of items, each
 *                of which contains two entries, one of which is "script", referencing the script to
 *                which to attach the stylesheet, and the other, "actions", being an array of
 *                actions which conditionally trigger the attachment for the script.
 */
function get_themelet_stylesheets($codename, $theme_color, $raw = false, $is_plugin = false, $inc_placeholders = false)
{
    global $mybb;

    $stylesheets = [];
    $modes = [];
    if ($mybb->settings['themelet_dev_mode'] == 'devdist') {
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
                if ($raw) {
                    $ret = [];
                    foreach ($res_arr['stylesheets'] as $sheet => $arr) {
                        if ($arr || $inc_placeholders) {
                            $norm_spec = $is_plugin ? "@ext.{$codename}/styles/$sheet" : normalise_res_spec1($sheet);
                            $ret[$norm_spec] = $arr;
                        }
                    }

                    return $ret;
                }
                foreach ($res_arr['stylesheets'] as $sheet => $arr) {
                    if (!$arr) { // Because empty, this is a plugin's stylesheet included merely for purposes of ordering.
                        continue;
                    }
                    foreach ($arr as $script_actions) {
                        $actions = $script_actions['actions'];
                        $script  = $script_actions['script' ];
                        if (empty($actions)) {
                            $actions = ['global'];
                        }
                        if (is_array($script)) {
                            // Script(s) is/are colours
                            foreach ($script as $colour) {
                                if ($colour == $theme_color) {
                                    if (empty($stylesheets['global'])) {
                                        $stylesheets['global'] = [];
                                    }
                                    if (empty($stylesheets['global']['global'])) {
                                        $stylesheets['global']['global'] = [];
                                    }
                                    $stylesheets['global']['global'][] = $is_plugin ? "@ext.{$codename}/styles/{$sheet}" : normalise_res_spec1($sheet);
                                }
                            }
                        } else {
                            foreach ($actions as $action) {
                                if (empty($stylesheets[$script])) {
                                    $stylesheets[$script] = [];
                                }
                                if (empty($stylesheets[$script][$action])) {
                                    $stylesheets[$script][$action] = [];
                                }
                                $stylesheets[$script][$action][] = $is_plugin ? "@ext.{$codename}/styles/{$sheet}" : normalise_res_spec1($sheet);
                            }
                        }
                    }
                }
            }
            break;
        }
    }

    // Previous return possible.
    return $stylesheets;
}

/**
 * Retrieves from the filesystem all stylesheets for a theme, including those for any installed
 * plugins, and including their display order.
 *
 * @param string $codename The codename (directory) of the theme whose stylesheets should be
 *                         retrieved.
 *
 * @return array The first array entry is the array of stylesheets, in "raw" format as described
 *               for get_themelet_stylesheets(), with the addition that index -1 into the stylesheet
 *               array contains inheritance data as described for resolve_themelet_resource() with a
 *               $return_type of RTR_RETURN_INHERITANCE.
 *               The second array entry is an array of display orders for the stylesheets, indexed
 *               by order number with each entry being an array of three entries: plugin name (an
 *               empty string if not a plugin but rather the theme itself), namespace (e.g.,
 *               'frontend' or 'acp'), and stylesheet name (e.g., "main.css").
 */
function get_theme_stylesheets($theme_code) {
    global $mybb;

    $disporders = $stylesheets_a = [];
    $order_num = 1;

    $theme = get_theme_properties($theme_code);

    // Fetch the list of all of the stylesheets and their inheritance information for this theme...
    $stylesheets_a[''] = get_themelet_stylesheets(
        $theme_code,
        $theme['color'],
        /*$raw =*/true,
        /*$is_plugin = */false,
        /*$inc_placeholders = */true,
    );
    foreach ($stylesheets_a[''] as $ss_name => &$ss_arr) {
        list($plugin_code, $namespace, $component, $filename) = parse_res_spec1($ss_name);
        if ($component != 'styles') {
            continue;
        }
        if ($plugin_code) {
            // Note #1: when $ss_arr is non-empty, this theme is overriding a plugin stylesheet's
            // properties. When it is empty, this is just a placeholder for the purpose of ordering
            // the plugin's stylesheet within this theme.
            //
            // TODO: Support stylesheet property inheritance such that we check any parent themes'
            // resources.json files for the plugin stylesheet's properties before pulling them from
            // the plugin's resources.json file itself. Support inheritance for the properties of
            // ordinary theme stylesheets too.
            $disporders[$order_num++] = [$plugin_code, /*namespace*/'', $component, $filename];
            $stylesheets_a[$plugin_code]["@ext.{$plugin_code}/{$component}/{$filename}"] = $ss_arr;
        } else {
            $ss_arr[-1] = resolve_themelet_resource(
                "~t~{$theme_code}:{$namespace}:{$component}:{$filename}",
                /*$use_themelet_cache = */false,
                /*$return_type = */RTR_RETURN_INHERITANCE
            );
            $disporders[$order_num++] = [/*plugin_code*/'', $namespace, $component, $filename];
        }
    }
    unset($ss_arr);
    // ...including for installed plugins.
    foreach (get_plugins_list() as $plugin_file) {
        global $plugins;

        require_once MYBB_ROOT."inc/plugins/{$plugin_file}";
        $plugin_code = str_replace('.php', '', $plugin_file);
        $installed_func = "{$plugin_code}_is_installed";
        $installed = true;
        if (function_exists($installed_func) && $installed_func() != true) {
            $installed = false;
        }
        if ($installed) {
            $plugin_ss = get_themelet_stylesheets(
                $plugin_code,
                $theme['color'],
                /*$raw =*/true,
                /*$is_plugin = */true
            );
            foreach ($plugin_ss as $ss_name => $ss_arr) {
                // Will be non-empty when overridden by the theme at note #1 above.
                if (empty($stylesheets_a[$plugin_code][$ss_name])) {
                    $stylesheets_a[$plugin_code][$ss_name] = $ss_arr;
                }
            }
            foreach ($stylesheets_a[$plugin_code] as $ss_name => &$ss_arr) {
                list($plugin_code, $namespace, $component, $filename) = parse_res_spec1($ss_name);
                $ss_arr[-1] = resolve_themelet_resource(
                    "~p~{$theme_code}:{$plugin_code}:styles:{$filename}",
                    /*$use_themelet_cache = */false,
                    /*$return_type = */RTR_RETURN_INHERITANCE
                );
                $disp_a = [$plugin_code, /*namespace*/'', 'styles', $filename];
                if (!in_array($disp_a, $disporders)) {
                    $disporders[$order_num++] = $disp_a;
                }
            }
            unset($ss_arr);
        }
    }

    return [$stylesheets_a, $disporders];
}

/**
 * Parses a normative resource specifier, such as "@ext.plugin_code/styles/main.css".
 * (Non-normative resource specifiers are those used by resolve_themelet_resource().)
 *
 * @param string $specifier The resource specifier.
 *
 * @return Array The returned array contains four string entries.
 *               The first is the plugin code (empty if the specified resource is not from a plugin).
 *               The second is the theme namespace, such as "frontend" (default), "parser", or "acp"
 *                 (empty if the specified resource is from a plugin).
 *               The third is the component, such as "styles", "templates", or "images".
 *               The fourth is the filename, such as "main.css". This may be a relative path,
 *                 thus prefixed with one or more directories, such as "subdir1/subdir2/main.css".
 */
function parse_res_spec1($specifier)
{
    $plugin_code = $namespace = $component = $filename = '';
    $pfx = '@ext.';
    $plen = strlen($pfx);

    // First, check if this specifies a plugin resource
    if (substr($specifier, 0, $plen) == $pfx) {
        // It does. Now parse it.
        $remainder = substr($specifier, $plen);
        $a = explode('/', $remainder, 3);
        $plugin_code = $a[0];
        if (!empty($a[1])) {
            $component = $a[1];
        }
        if (!empty($a[2])) {
            $filename = $a[2];
        }
    // Next, check whether it specifies a namespaced theme resource
    } else if ($specifier[0] == '@') {
        // It does. Now parse it.
        $remainder = substr($specifier, 1);
        $a = explode('/', $remainder, 3);
        $namespace = $a[0];
        if (!empty($a[1])) {
            $component = $a[1];
        }
        if (!empty($a[2])) {
            $filename = $a[2];
        }
    // If neither of the above apply, then it must specify a non-namespaced theme resource
    // (i.e., in the default "frontend" namespace).
    } else {
        // Parse it.
        $namespace = 'frontend';
        $a = explode('/', $specifier, 2);
        $component = $a[0];
        if (!empty($a[1])) {
            $filename = $a[1];
        }
    }

    return [$plugin_code, $namespace, $component, $filename];
}

/**
 * Normalises a normative resource specifier, such as "@ext.plugin_code/styles/main.css".
 * (Non-normative resource specifiers are those used by resolve_themelet_resource().)
 * Currently, all this normalisation does is ensure that an empty theme namespace is converted into
 * an explicit "frontend" namespace.
 *
 * @param string $specifier The resource specifier.
 *
 * @return string The normalised resource specifier.
 */
function normalise_res_spec1($specifier)
{
    list($plugin_code, $namespace, $component, $filename) = parse_res_spec1($specifier);

    return '@'.($plugin_code ? 'ext.'.$plugin_code : $namespace)."/{$component}/{$filename}";
}

/**
 * Resolves a themelet resource for the current theme, checking up the hierarchy if it does not
 * exist in the themelet directory itself, and supporting the overriding of plugin resources in
 * `ext.[pluginname]` theme subdirectories.
 *
 * When appropriate, caches the resource under `cache/themes/theme_code` where `theme_code`
 * is the codename of the current theme.
 *
 * If a CSS resource is requested and it does not exist but a SCSS file with the same name other
 * than extension exists, then it auto-compiles the SCSS into CSS.
 *
 * If a resource ending in `.min.css` is specified, then the CSS is minified. Note that this
 * function does not consult the core setting to minify CSS ('minifycss'); it is up to calling
 * functions to supply a resource ending (or not) in `.min.css` as consistent with that core
 * setting.
 *
 * @param string $specifier Stipulates which resource to load in the given theme.
 *                          Resources for the current theme are specified in the format:
 *                            "~ct~namespace:directory:filename", where "namespace" is, e.g.,
 *                              "frontend", "acp", or "parser", "directory" is, e.g., "styles" or
 *                               "images", and "filename" is, e.g., "main.css" or "logo.png".
 *                          Resources for some other named theme are specified in the format:
 *                            "~t~theme_code:namespace:directory:filename", where theme_code is the
 *                            theme's codename and the other entities are as described above.
 *                          Resources for plugins in the current theme are specified in the format:
 *                            "~cp~plugin_code:directory:filename", where "directory" and
 *                            "filename" are as above, and "plugin_code" is the plugin's codename.
 *                          Resources for plugins for some other named theme are specified in the format:
 *                            "~p~theme_code:plugin_code:directory:filename", where theme_code is the
 *                            theme's codename and the other entities are as described above.
 * @param boolean $use_themelet_cache True to try to get the list of themelet directories out of
 *                                    cache; otherwise rebuild it manually.
 * @param integer $return_type One of the constants RTR_RETURN_RESOURCE, RTR_RETURN_PATH, or
 *                             RTR_RETURN_INHERITANCE.
 * @param boolean $min_override If true, then do not minify CSS for stylesheets.
 * @param boolean $scss_override If true, then do not convert SCSS to CSS for stylesheets.
 * @param boolean $is_scss (Output only; ignored as input) If true, then the resource is a
 *                         stylesheet that is or was originally in SCSS format.
 *
 * @return Mixed  If the resource could not be located, this return is false. Otherwise:
 *                If $return_type is RTR_RETURN_RESOURCE, this return is the resource's contents,
 *                  generated dynamically, avoiding the cache.
 *                If $return_type is RTR_RETURN_PATH, then, if development mode is off, this return
 *                  is a path to the cached file, relative to the MyBB root directory, else it is
 *                  the relative path `resource.php?specifier=$specifier`.
 *                If $return_type is RTR_RETURN_INHERITANCE, then this return is the resource's
 *                  inheritance data as an array, indexed by:
 *                    `orig_plugin` => string, indicating the plugin which originates the resource
 *                                             (empty if not a plugin resource).
 *                    `is_scss` => boolean, indicating for a stylesheet resource whether the
 *                                          resolved resource is in the form of SCSS (when true) or
 *                                          CSS (when false).
 *                    `inheritance_chain` => an array of themelets in ascendant order of inheritance,
 *                                           terminating in the themelet to which the resource
 *                                           resolves. Each entry in the array is an array indexed
 *                                           by:
 *                      `codename`  => string, the codename of the theme/plugin.
 *                      `is_plugin` => boolean, indicating whether or not the codename refers to a
 *                                              plugin (otherwise, it resolves to a theme).
 */
define('RTR_RETURN_PATH'       , 1);
define('RTR_RETURN_RESOURCE'   , 2);
define('RTR_RETURN_INHERITANCE', 3);
function resolve_themelet_resource($specifier, $use_themelet_cache = true, $return_type = RTR_RETURN_PATH, $min_override = false, $scss_override = false, &$is_scss = false)
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

    if ($return_type == RTR_RETURN_INHERITANCE) {
        $ret = [
            'orig_plugin'       => '',
            'is_scss'           => false,
            'inheritance_chain' => [],
        ];
        $is_scss = false;
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
        $specifier_new = my_strtolower(substr($specifier, 0, -7)).'css';
        $minify = true;
    } else {
        $specifier_new = $specifier;
        $minify = false;
    }

    $theme_code = $theme['codename'];
    $is_plugin_spec = $is_theme_spec = $resource_path = $scss_path = false;

    if (my_strtolower(substr($specifier_new, 0, 4)) === '~ct~' && substr_count($specifier_new, ':') == 2) {
        $is_theme_spec = true;
        list($res_comp, $res_dir, $res_name) = explode(':', substr($specifier_new, 4));
    } else if (my_strtolower(substr($specifier_new, 0, 3)) === '~t~' && substr_count($specifier_new, ':') == 3) {
        $is_theme_spec = true;
        list($theme_code, $res_comp, $res_dir, $res_name) = explode(':', substr($specifier_new, 3));
    }
    if ($is_theme_spec) {
        // We have a theme resource which requires resolution. Resolve it.
        if ($return_type == RTR_RETURN_INHERITANCE) {
            $ret['orig_plugin'] = '';
        }
        if (!empty($themelet_dirs[$theme_code])) {
            foreach ($themelet_dirs[$theme_code] as $entry) {
                list($theme_dir, $namespace, $is_plugin, $codename) = $entry;
                if (!$is_plugin) {
                    if ($return_type == RTR_RETURN_INHERITANCE) {
                        $ret['inheritance_chain'][] = [
                            'codename'  => $codename,
                            'is_plugin' => false    ,
                        ];
                    }
                    $path_to_test = $theme_dir.'/'.$res_comp.'/'.$res_dir.'/'.$res_name;
                    if (test_set_path($path_to_test, $resource_path, $scss_path)) {
                        if ($return_type == RTR_RETURN_INHERITANCE) {
                            $ret['is_scss'] = !empty($scss_path);
                            $is_scss = $ret['is_scss'];
                            return $ret;
                        }
                        break;
                    }
                }
            }
        }
    } else {
        if (my_strtolower(substr($specifier_new, 0, 4)) === '~cp~' && substr_count($specifier_new, ':') == 2) {
            $is_plugin_spec = true;
            list($plugin_code, $res_dir, $res_name) = explode(':', substr($specifier_new, 4));
        } else if (my_strtolower(substr($specifier_new, 0, 3)) === '~p~' && substr_count($specifier_new, ':') == 3) {
            $is_plugin_spec = true;
            list($theme_code, $plugin_code, $res_dir, $res_name) = explode(':', substr($specifier_new, 3));
        }

        if ($is_plugin_spec) {
            // We have a plugin's themelet resource which requires resolution. Resolve it.

            if ($return_type == RTR_RETURN_INHERITANCE) {
                $ret['orig_plugin'] = $plugin_code;
            }

            if (!empty($themelet_dirs[$theme_code])) {
                // The plugin resource might be overridden in a theme.
                // We test this possibility first, then...
                foreach ($themelet_dirs[$theme_code] as $entry) {
                    list($theme_dir, $namespace, $is_plugin, $codename) = $entry;
                    if (!$is_plugin) {
                        if ($return_type == RTR_RETURN_INHERITANCE) {
                            $ret['inheritance_chain'][] = [
                                'codename'  => $codename,
                                'is_plugin' => false    ,
                            ];
                        }
                        $path_to_test = $theme_dir.'/ext.'.$plugin_code.'/'.$res_dir.'/'.$res_name;
                        if (test_set_path($path_to_test, $resource_path, $scss_path)) {
                            if ($return_type == RTR_RETURN_INHERITANCE) {
                                $ret['is_scss'] = !empty($scss_path);
                                $is_scss = $ret['is_scss'];
                                return $ret;
                            }
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
                $path_to_test = MYBB_ROOT.'inc/plugins/'.$plugin_code.'/interface/'.
                                ($mybb->settings['themelet_dev_mode'] ? 'devdist' : 'current').'/ext/'.$res_dir.'/'.
                                $res_name;
                if (test_set_path($path_to_test, $resource_path, $scss_path) && $return_type == RTR_RETURN_INHERITANCE) {
                    $ret['is_scss'] = !empty($scss_path);
                    $is_scss = $ret['is_scss'];
                    $ret['inheritance_chain'][] = [
                        'codename'  => $plugin_code,
                        'is_plugin' => true        ,
                    ];
                    return $ret;
                }
            }
        }
    }

    if (!$resource_path) {
        return false;
    }

    $use_cache = $return_type == RTR_RETURN_PATH && !$mybb->settings['themelet_dev_mode'];
    $needs_cache = $deps_file = false;

    if ($use_cache) {
        $cache_dir = MYBB_ROOT.'cache/themes/'.$theme['codename'];
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0777, true);
        }
        $cache_file = $cache_dir.'/'.$specifier;
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

    if (($needs_cache || $return_type == RTR_RETURN_RESOURCE) && (($minify && !$min_override) || $scss_path)) {
        if ($scss_path) {
            if ($scss_override) {
                $stylesheet = file_get_contents($scss_path);
            } else {
                $compiler = new Compiler();
                $result = $compiler->compileString(file_get_contents($scss_path), /*$path = */$scss_path);
                $stylesheet = $result->getCss();
                if ($needs_cache) {
                    // Entries other than the first in the .deps file are for secondary
                    // dependencies - in this case, SCSS @import files.
                    $deps_new = array_merge($deps_new, $result->getIncludedFiles());
                }
            }
        } else {
            $stylesheet = file_get_contents($resource_path);
        }

        $plugins->run_hooks('css_start', $stylesheet);

        if ($minify && !$min_override) {
            global $config;
            require_once MYBB_ROOT.$config['admin_dir'].'/inc/functions_themes.php';
            $stylesheet = minify_stylesheet($stylesheet);
        }

        $plugins->run_hooks('css_end', $stylesheet);
    }

    if ($needs_cache) {
        if ((!$minify || $min_override) && !$scss_path) {
            copy($resource_path, $cache_file);
        } else {
            file_put_contents($cache_file, $stylesheet);
        }
        file_put_contents($deps_file, implode("\n", $deps_new));
    }

    $resource = '';

    if ($return_type == RTR_RETURN_RESOURCE) {
        if ($minify && !$min_override || $scss_path) {
            $resource = $stylesheet;
        } elseif ($resource_path) {
            $resource = file_get_contents($resource_path);
        }
    }

    $is_scss = !empty($scss_path);

    // Earlier returns possible
    return $return_type == RTR_RETURN_RESOURCE
             ? $resource
             : ($use_cache
                  ? substr($cache_file, strlen(MYBB_ROOT))
                  : 'resource.php?specifier='.urlencode($specifier)
               );
}

function is_mutable_theme($theme_code, $mode)
{
    $a = explode('.', $theme_code, 2);
    return $mode == 'devdist' || !(count($a) == 2 && $a[0] == 'core' || count($a) == 1);
}

function is_valid_theme_code($theme_code)
{
    foreach (['board.', 'core.'] as $prefix) {
        $len = strlen($prefix);
        if (substr($theme_code, 0, $len) == $prefix) {
            $theme_code = substr($theme_code, $len);
            break;
        }
    }

    return preg_replace('([a-z_])', '', $theme_code) == '';
}

function is_valid_namespace($namespace)
{
    $a = explode('.', $namespace);
    if (count($a) == 2 && $a[0] != 'ext' || count($a) > 2) {
        return false;
    }
    $ns_dots_rem = implode('', $a);
    return preg_replace('([a-z_])', '', $ns_dots_rem) == '';
}

function is_valid_resource_path($path)
{
    $a = explode('/', trim($path));

    $first = $a[0];
    $last = array_pop($a);

    // A resource path must not start or end with a directory separator
    if ($first == '' || $last == '') {
        return false;
    // Nor must its final component have more than one dot in it
    } else if (substr_count($last, '.') > 1) {
        return false;
    } else {
        // Nor must its final component begin or end with a dot
        $x = explode('.', $last);
        if (count($x) == 2 && ($x[0] == '' || $x[1] == '')) {
            return false;
        }
        // Now, put the last path component back together without any dot and return it to the array
        $a[] = implode('', $x);
        foreach ($a as $b) {
            // A resource path should not contain any doubled directory separators
            if ($b == '') {
                return false;
            }
            // Nor should it otherwise contain any characters other than lowercase a-z and underscore
            if (preg_replace('([a-z_])', '', $b) != '') {
                return false;
            }
        }
    }

    return true;
}

function get_theme_name_apx($codename) {
    global $lang;

    if (substr($codename, 0, 5) == 'core.') {
        return '<span class="core_theme">'.$lang->sqbr_append_core.'</span>';
    } else if (strpos($codename, '.') === false) {
        return '<span class="orig_theme">'.$lang->sqbr_append_orig.'</span>';
    } else {
        return '';
    }
}
