<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->plugins, "index.php?module=config-plugins");

$plugins->run_hooks("admin_config_plugins_begin");

if($mybb->input['action'] == "browse")
{
	$page->add_breadcrumb_item($lang->browse_plugins);

	$page->output_header($lang->browse_plugins);

	$sub_tabs['plugins'] = array(
		'title' => $lang->plugins,
		'link' => "index.php?module=config-plugins",
		'description' => $lang->plugins_desc
	);
	$sub_tabs['update_plugins'] = array(
		'title' => $lang->plugin_updates,
		'link' => "index.php?module=config-plugins&amp;action=check",
		'description' => $lang->plugin_updates_desc
	);

	$sub_tabs['browse_plugins'] = array(
		'title' => $lang->browse_plugins,
		'link' => "index.php?module=config-plugins&amp;action=browse",
		'description' => $lang->browse_plugins_desc
	);

	$page->output_nav_tabs($sub_tabs, 'browse_plugins');

	// Process search requests
	require_once MYBB_ROOT."inc/class_xml.php";

	$keywords = "";
	if($mybb->input['keywords'])
	{
		$keywords = "&keywords=".urlencode($mybb->input['keywords']);
	}

	if($mybb->input['page'])
	{
		$url_page = "&page=".$mybb->get_input('page', MyBB::INPUT_INT);
	}
	else
	{
		$mybb->input['page'] = 1;
		$url_page = "";
	}

	// Gets the major version code. i.e. 1410 -> 1400 or 121 -> 1200
	$major_version_code = round($mybb->version_code/100, 0)*100;
	// Convert to mods site version codes
	$search_version = ($major_version_code/100).'x';

	$contents = fetch_remote_file("https://community.mybb.com/xmlbrowse.php?type=plugins&version={$search_version}{$keywords}{$url_page}", $post_data);

	if(!$contents)
	{
		$page->output_inline_error($lang->error_communication_problem);
		$page->output_footer();
		exit;
	}

	$table = new Table;
	$table->construct_header($lang->plugin);
	$table->construct_header($lang->latest_version, array("class" => "align_center", 'width' => 125));
	$table->construct_header($lang->controls, array("class" => "align_center", 'width' => 125));

	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();

	if(!is_array($tree) || !isset($tree['results']))
	{
		$page->output_inline_error($lang->error_communication_problem);
		$page->output_footer();
		exit;
	}

	if(!empty($tree['results']['result']))
	{
		if(array_key_exists("tag", $tree['results']['result']))
		{
			$only_plugin = $tree['results']['result'];
			unset($tree['results']['result']);
			$tree['results']['result'][0] = $only_plugin;
		}

		require_once MYBB_ROOT . '/inc/class_parser.php';
		$post_parser = new postParser();

		foreach($tree['results']['result'] as $result)
		{
			$result['name']['value'] = htmlspecialchars_uni($result['name']['value']);
			$result['description']['value'] = htmlspecialchars_uni($result['description']['value']);
			$result['author']['value'] = $post_parser->parse_message($result['author']['value'], array(
					'allow_html' => true
				)
			);
			$result['version']['value'] = htmlspecialchars_uni($result['version']['value']);
			$result['download_url']['value'] = htmlspecialchars_uni(html_entity_decode($result['download_url']['value']));

			$table->construct_cell("<strong>{$result['name']['value']}</strong><br /><small>{$result['description']['value']}</small><br /><i><small>{$lang->created_by} {$result['author']['value']}</small></i>");
			$table->construct_cell($result['version']['value'], array("class" => "align_center"));
			$table->construct_cell("<strong><a href=\"https://community.mybb.com/{$result['download_url']['value']}\" target=\"_blank\" rel=\"noopener\">{$lang->download}</a></strong>", array("class" => "align_center"));
			$table->construct_row();
		}
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->error_no_results_found, array("colspan" => 3));
		$table->construct_row();
	}

	$search = new Form("index.php?module=config-plugins&amp;action=browse", 'post', 'search_form');
	echo "<div style=\"padding-bottom: 3px; margin-top: -9px; text-align: right;\">";
	if($mybb->input['keywords'])
	{
		$default_class = '';
		$value = htmlspecialchars_uni($mybb->input['keywords']);
	}
	else
	{
		$default_class = "search_default";
		$value = $lang->search_for_plugins;
	}
	echo $search->generate_text_box('keywords', $value, array('id' => 'search_keywords', 'class' => "{$default_class} field150 field_small"))."\n";
	echo "<input type=\"submit\" class=\"search_button\" value=\"{$lang->search}\" />\n";
	echo "<script type=\"text/javascript\">
		var form = $(\"#search_form\");
		form.submit(function()
		{
			var search = $(\"#search_keywords\");
			if(search.val() == '' || search.val() == '{$lang->search_for_plugins}')
			{
				search.focus();
				return false;
			}
		});

		var search = $(\"#search_keywords\");
		search.focus(function()
		{
			var searched_focus = $(this);
			if(searched_focus.val() == '{$lang->search_for_plugins}')
			{
				searched_focus.removeClass(\"search_default\");
				searched_focus.val(\"\");
			}
		});

		search.blur(function()
		{
			var searched_blur = $(this);
			if(searched_blur.val() == \"\")
			{
				searched_blur.addClass('search_default');
				searched_blur.val('{$lang->search_for_plugins}');
			}
		});

		// fix the styling used if we have a different default value
        if(search.val() != '{$lang->search_for_plugins}')
        {
            search.removeClass('search_default');
        }
		</script>\n";
	echo "</div>\n";
	echo $search->end();

	// Recommended plugins = Default; Otherwise search results & pagination
	if($mybb->request_method == "post")
	{
		$table->output("<span style=\"float: right;\"><small><a href=\"https://community.mybb.com/mods.php?action=browse&category=plugins\" target=\"_blank\" rel=\"noopener\">{$lang->browse_all_plugins}</a></small></span>".$lang->sprintf($lang->browse_results_for_mybb, $mybb->version));
	}
	else
	{
		$table->output("<span style=\"float: right;\"><small><a href=\"https://community.mybb.com/mods.php?action=browse&category=plugins\" target=\"_blank\" rel=\"noopener\">{$lang->browse_all_plugins}</a></small></span>".$lang->sprintf($lang->recommended_plugins_for_mybb, $mybb->version));
	}

	echo "<br />".draw_admin_pagination($mybb->input['page'], 15, $tree['results']['attributes']['total'], "index.php?module=config-plugins&amp;action=browse{$keywords}&amp;page={page}");

	$page->output_footer();
}

if($mybb->input['action'] == "check")
{
	$plugins_list = get_plugins_list();

	$plugins->run_hooks("admin_config_plugins_check");

	$info = array();

	if($plugins_list)
	{
		$active_hooks = $plugins->hooks;
		foreach($plugins_list as $plugin_file)
		{
			require_once MYBB_ROOT."inc/plugins/".$plugin_file;
			$codename = str_replace(".php", "", $plugin_file);
			$infofunc = $codename."_info";
			if(!function_exists($infofunc))
			{
				continue;
			}
			$plugininfo = $infofunc();
			$plugininfo['guid'] = trim($plugininfo['guid']);
			$plugininfo['codename'] = trim($plugininfo['codename']);

			if($plugininfo['codename'] != "")
			{
				$info[]	= $plugininfo['codename'];
				$names[$plugininfo['codename']] = array('name' => $plugininfo['name'], 'version' => $plugininfo['version']);
			}
			elseif($plugininfo['guid'] != "")
			{
				$info[] =  $plugininfo['guid'];
				$names[$plugininfo['guid']] = array('name' => $plugininfo['name'], 'version' => $plugininfo['version']);
			}
		}
		$plugins->hooks = $active_hooks;
	}

	if(empty($info))
	{
		flash_message($lang->error_vcheck_no_supported_plugins, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	$url = "https://community.mybb.com/version_check.php?";
	$url .= http_build_query(array("info" => $info))."&";
	require_once MYBB_ROOT."inc/class_xml.php";
	$contents = fetch_remote_file($url);

	if(!$contents)
	{
		flash_message($lang->error_vcheck_communications_problem, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();

	if(!is_array($tree) || !isset($tree['plugins']))
	{
		flash_message($lang->error_communication_problem, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	if(array_key_exists('error', $tree['plugins']))
	{
		switch($tree['plugins'][0]['error'])
		{
			case "1":
				$error_msg = $lang->error_no_input;
				break;
			case "2":
				$error_msg = $lang->error_no_pids;
				break;
			default:
				$error_msg = "";
		}
		flash_message($lang->error_communication_problem.$error_msg, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	$table = new Table;
	$table->construct_header($lang->plugin);
	$table->construct_header($lang->your_version, array("class" => "align_center", 'width' => 125));
	$table->construct_header($lang->latest_version, array("class" => "align_center", 'width' => 125));
	$table->construct_header($lang->controls, array("class" => "align_center", 'width' => 125));

	if(!is_array($tree['plugins']['plugin']))
	{
		flash_message($lang->success_plugins_up_to_date, 'success');
		admin_redirect("index.php?module=config-plugins");
	}

	if(array_key_exists("tag", $tree['plugins']['plugin']))
	{
		$only_plugin = $tree['plugins']['plugin'];
		unset($tree['plugins']['plugin']);
		$tree['plugins']['plugin'][0] = $only_plugin;
	}

	foreach($tree['plugins']['plugin'] as $plugin)
	{
		$compare_by = array_key_exists("codename", $plugin['attributes']) ? "codename" : "guid";
		$is_vulnerable = array_key_exists("vulnerable", $plugin) ? true : false;

		if(version_compare($names[$plugin['attributes'][$compare_by]]['version'], $plugin['version']['value'], "<"))
		{
			$plugin['download_url']['value'] = htmlspecialchars_uni($plugin['download_url']['value']);
			$plugin['vulnerable']['value'] = htmlspecialchars_uni($plugin['vulnerable']['value']);
			$plugin['version']['value'] = htmlspecialchars_uni($plugin['version']['value']);

			if($is_vulnerable)
			{
				$table->construct_cell("<div class=\"error\" id=\"flash_message\">
										{$lang->error_vcheck_vulnerable} {$names[$plugin['attributes'][$compare_by]]['name']}
										</div>
										<p>	<b>{$lang->error_vcheck_vulnerable_notes}</b> <br /><br /> {$plugin['vulnerable']['value']}</p>");
			}
			else
			{
				$table->construct_cell("<strong>{$names[$plugin['attributes'][$compare_by]]['name']}</strong>");
			}
			$table->construct_cell("{$names[$plugin['attributes'][$compare_by]]['version']}", array("class" => "align_center"));
			$table->construct_cell("<strong><span style=\"color: #C00\">{$plugin['version']['value']}</span></strong>", array("class" => "align_center"));
			if($is_vulnerable)
			{
				$table->construct_cell("<a href=\"index.php?module=config-plugins\"><b>{$lang->deactivate}</b></a>", array("class" => "align_center", "width" => 150));
			}
			else
			{
				$table->construct_cell("<strong><a href=\"https://community.mybb.com/{$plugin['download_url']['value']}\" target=\"_blank\" rel=\"noopener\">{$lang->download}</a></strong>", array("class" => "align_center"));
			}
			$table->construct_row();
		}
	}

	if($table->num_rows() == 0)
	{
		flash_message($lang->success_plugins_up_to_date, 'success');
		admin_redirect("index.php?module=config-plugins");
	}

	$page->add_breadcrumb_item($lang->plugin_updates);

	$page->output_header($lang->plugin_updates);

	$sub_tabs['plugins'] = array(
		'title' => $lang->plugins,
		'link' => "index.php?module=config-plugins",
	);

	$sub_tabs['update_plugins'] = array(
		'title' => $lang->plugin_updates,
		'link' => "index.php?module=config-plugins&amp;action=check",
		'description' => $lang->plugin_updates_desc
	);

	$sub_tabs['browse_plugins'] = array(
		'title' => $lang->browse_plugins,
		'link' => "index.php?module=config-plugins&amp;action=browse",
		'description' => $lang->browse_plugins_desc
	);

	$page->output_nav_tabs($sub_tabs, 'update_plugins');

	$table->output($lang->plugin_updates);

	$page->output_footer();
}

// Activates or deactivates a specific plugin
if($mybb->input['action'] == "activate" || $mybb->input['action'] == "deactivate")
{
	if(!verify_post_check($mybb->input['my_post_key']))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	if($mybb->input['action'] == "activate")
	{
		$plugins->run_hooks("admin_config_plugins_activate");
	}
	else
	{
		$plugins->run_hooks("admin_config_plugins_deactivate");
	}

	$codename = $mybb->input['plugin'];
	$codename = str_replace(array(".", "/", "\\"), "", $codename);
	$file = basename($codename.".php");

	// Check if the file exists and throw an error if it doesn't
	if(!file_exists(MYBB_ROOT."inc/plugins/$file"))
	{
		flash_message($lang->error_invalid_plugin, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	$plugins_cache = $cache->read("plugins");
	$active_plugins = $plugins_cache['active'];

	require_once MYBB_ROOT."inc/plugins/$file";

	$installed_func = "{$codename}_is_installed";
	$installed = true;
	if(function_exists($installed_func) && $installed_func() != true)
	{
		$installed = false;
	}

	$install_uninstall = false;

	if($mybb->input['action'] == "activate")
	{
		$message = $lang->success_plugin_activated;

		// Plugin is compatible with this version?
		if($plugins->is_compatible($codename) == false)
		{
			flash_message($lang->sprintf($lang->plugin_incompatible, $mybb->version), 'error');
			admin_redirect("index.php?module=config-plugins");
		}

		// If not installed and there is a custom installation function
		if($installed == false && function_exists("{$codename}_install"))
		{
			call_user_func("{$codename}_install");
			$message = $lang->success_plugin_installed;
			$install_uninstall = true;
		}

		if(function_exists("{$codename}_activate"))
		{
			call_user_func("{$codename}_activate");
		}

		$active_plugins[$codename] = $codename;
		$executed[] = 'activate';
	}
	else if($mybb->input['action'] == "deactivate")
	{
		$message = $lang->success_plugin_deactivated;

		if(function_exists("{$codename}_deactivate"))
		{
			call_user_func("{$codename}_deactivate");
		}

		if($mybb->input['uninstall'] == 1 && function_exists("{$codename}_uninstall"))
		{
			call_user_func("{$codename}_uninstall");
			$message = $lang->success_plugin_uninstalled;
			$install_uninstall = true;
		}

		unset($active_plugins[$codename]);
	}

	// Update plugin cache
	$plugins_cache['active'] = $active_plugins;
	$cache->update("plugins", $plugins_cache);

	// Log admin action
	log_admin_action($codename, $install_uninstall);

	if($mybb->input['action'] == "activate")
	{
		$plugins->run_hooks("admin_config_plugins_activate_commit");
	}
	else
	{
		$plugins->run_hooks("admin_config_plugins_deactivate_commit");
	}

	flash_message($message, 'success');
	admin_redirect("index.php?module=config-plugins");
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->plugins);

	$sub_tabs['plugins'] = array(
		'title' => $lang->plugins,
		'link' => "index.php?module=config-plugins",
		'description' => $lang->plugins_desc
	);
	$sub_tabs['update_plugins'] = array(
		'title' => $lang->plugin_updates,
		'link' => "index.php?module=config-plugins&amp;action=check",
		'description' => $lang->plugin_updates_desc
	);

	$sub_tabs['browse_plugins'] = array(
		'title' => $lang->browse_plugins,
		'link' => "index.php?module=config-plugins&amp;action=browse",
		'description' => $lang->browse_plugins_desc
	);

	$page->output_nav_tabs($sub_tabs, 'plugins');

	// Let's make things easier for our user - show them active
	// and inactive plugins in different lists
	$plugins_cache = $cache->read("plugins");
	$active_plugins = $plugins_cache['active'];

	$plugins_list = get_plugins_list();

	$plugins->run_hooks("admin_config_plugins_plugin_list");

	if(!empty($plugins_list))
	{
		$a_plugins = $i_plugins = array();

		foreach($plugins_list as $plugin_file)
		{
			require_once MYBB_ROOT."inc/plugins/".$plugin_file;
			$codename = str_replace(".php", "", $plugin_file);
			$infofunc = $codename."_info";

			if(!function_exists($infofunc))
			{
				continue;
			}

			$plugininfo = $infofunc();
			$plugininfo['codename'] = $codename;

			if($active_plugins[$codename])
			{
				// This is an active plugin
				$plugininfo['is_active'] = 1;

				$a_plugins[] = $plugininfo;
				continue;
			}

			// Either installed and not active or completely inactive
			$i_plugins[] = $plugininfo;
		}

		$table = new Table;
		$table->construct_header($lang->plugin);
		$table->construct_header($lang->controls, array("colspan" => 2, "class" => "align_center", "width" => 300));

		if(empty($a_plugins))
		{
			$table->construct_cell($lang->no_active_plugins, array('colspan' => 3));
			$table->construct_row();
		}
		else
		{
			build_plugin_list($a_plugins);
		}

		$table->output($lang->active_plugin);

		$table = new Table;
		$table->construct_header($lang->plugin);
		$table->construct_header($lang->controls, array("colspan" => 2, "class" => "align_center", "width" => 300));

		if(empty($i_plugins))
		{
			$table->construct_cell($lang->no_inactive_plugins, array('colspan' => 3));
			$table->construct_row();
		}
		else
		{
			build_plugin_list($i_plugins);
		}

		$table->output($lang->inactive_plugin);
	}
	else
	{
		// No plugins
		$table = new Table;
		$table->construct_header($lang->plugin);
		$table->construct_header($lang->controls, array("colspan" => 2, "class" => "align_center", "width" => 300));

		$table->construct_cell($lang->no_plugins, array('colspan' => 3));
		$table->construct_row();

		$table->output($lang->plugins);
	}

	$page->output_footer();
}

/**
 * @return array
 */
function get_plugins_list()
{
	// Get a list of the plugin files which exist in the plugins directory
	$dir = @opendir(MYBB_ROOT."inc/plugins/");
	if($dir)
	{
		while($file = readdir($dir))
		{
			$ext = get_extension($file);
			if($ext == "php")
			{
				$plugins_list[] = $file;
			}
		}
		@sort($plugins_list);
	}
	@closedir($dir);

	return $plugins_list;
}

/**
 * @param array $plugin_list
 */
function build_plugin_list($plugin_list)
{
	global $lang, $mybb, $plugins, $table;

	foreach($plugin_list as $plugininfo)
	{
		if($plugininfo['website'])
		{
			$plugininfo['name'] = "<a href=\"".$plugininfo['website']."\">".$plugininfo['name']."</a>";
		}

		if($plugininfo['authorsite'])
		{
			$plugininfo['author'] = "<a href=\"".$plugininfo['authorsite']."\">".$plugininfo['author']."</a>";
		}

		if($plugins->is_compatible($plugininfo['codename']) == false)
		{
			$compatibility_warning = "<span style=\"color: red;\">".$lang->sprintf($lang->plugin_incompatible, $mybb->version)."</span>";
		}
		else
		{
			$compatibility_warning = "";
		}

		$installed_func = "{$plugininfo['codename']}_is_installed";
		$install_func = "{$plugininfo['codename']}_install";
		$uninstall_func = "{$plugininfo['codename']}_uninstall";

		$installed = true;
		$install_button = false;
		$uninstall_button = false;

		if(function_exists($installed_func) && $installed_func() != true)
		{
			$installed = false;
		}

		if(function_exists($install_func))
		{
			$install_button = true;
		}

		if(function_exists($uninstall_func))
		{
			$uninstall_button = true;
		}

		$table->construct_cell("<strong>{$plugininfo['name']}</strong> ({$plugininfo['version']})<br /><small>{$plugininfo['description']}</small><br /><i><small>{$lang->created_by} {$plugininfo['author']}</small></i>");

		// Plugin is not installed at all
		if($installed == false)
		{
			if($compatibility_warning)
			{
				$table->construct_cell("{$compatibility_warning}", array("class" => "align_center", "colspan" => 2));
			}
			else
			{
				$table->construct_cell("<a href=\"index.php?module=config-plugins&amp;action=activate&amp;plugin={$plugininfo['codename']}&amp;my_post_key={$mybb->post_code}\">{$lang->install_and_activate}</a>", array("class" => "align_center", "colspan" => 2));
			}
		}
		// Plugin is activated and installed
		else if($plugininfo['is_active'])
		{
			$table->construct_cell("<a href=\"index.php?module=config-plugins&amp;action=deactivate&amp;plugin={$plugininfo['codename']}&amp;my_post_key={$mybb->post_code}\">{$lang->deactivate}</a>", array("class" => "align_center", "width" => 150));
			if($uninstall_button)
			{
				$table->construct_cell("<a href=\"index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin={$plugininfo['codename']}&amp;my_post_key={$mybb->post_code}\">{$lang->uninstall}</a>", array("class" => "align_center", "width" => 150));
			}
			else
			{
				$table->construct_cell("&nbsp;", array("class" => "align_center", "width" => 150));
			}
		}
		// Plugin is installed but not active
		else if($installed == true)
		{
			if($compatibility_warning && !$uninstall_button)
			{
				$table->construct_cell("{$compatibility_warning}", array("class" => "align_center", "colspan" => 2));
			}
			else
			{
				$table->construct_cell("<a href=\"index.php?module=config-plugins&amp;action=activate&amp;plugin={$plugininfo['codename']}&amp;my_post_key={$mybb->post_code}\">{$lang->activate}</a>", array("class" => "align_center", "width" => 150));
				if($uninstall_button)
				{
					$table->construct_cell("<a href=\"index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin={$plugininfo['codename']}&amp;my_post_key={$mybb->post_code}\">{$lang->uninstall}</a>", array("class" => "align_center", "width" => 150));
				}
				else
				{
					$table->construct_cell("&nbsp;", array("class" => "align_center", "width" => 150));
				}
			}
		}
		$table->construct_row();
	}
}
