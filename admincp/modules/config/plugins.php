<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->plugins, "index.php?".SID."&amp;module=config/plugins");

if($mybb->input['action'] == "check")
{		
	$plugins_list = get_plugins_list();
	
	$info = array();
	
	if($plugins_list)
	{
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
			
			if(trim($plugininfo['guid']) != "")
			{
				$info[] = $plugininfo['guid'];
				$names[$plugininfo['guid']] = array('name' => $plugininfo['name'], 'version' => $plugininfo['version']);
			}
		}
	}
	
	if(empty($info))
	{
		flash_message($lang->error_vcheck_no_supported_plugins, 'error');
		admin_redirect("index.php?".SID."&module=config/plugins");
	}
	
	require_once MYBB_ROOT."inc/class_xml.php";
	$contents = fetch_remote_file("http://mods.mybboard.com/version_check.php?info=".serialize($info));
	
	if(!$contents)
	{
		flash_message($lang->error_vcheck_communications_problem, 'error');
		admin_redirect("index.php?".SID."&module=config/plugins");
	}
	
	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();
	
	if(array_key_exists('error', $tree['plugins'][0]))
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
		admin_redirect("index.php?".SID."&module=config/plugins");
	}
	
	$table = new Table;
	$table->construct_header($lang->plugin);
	$table->construct_header($lang->your_version, array("class" => "align_center", 'width' => 125));
	$table->construct_header($lang->latest_version, array("class" => "align_center", 'width' => 125));
	$table->construct_header($lang->controls, array("class" => "align_center", 'width' => 125));
	
	if(array_key_exists("tag", $tree['plugins']['plugin']))
	{
		$only_plugin = $tree['plugins']['plugin'];
	 	unset($tree['plugins']['plugin']);
	 	$tree['plugins']['plugin'][0] = $only_plugin;
	}

	foreach($tree['plugins']['plugin'] as $plugin)
	{
		if(version_compare($names[$plugin['attributes']['guid']]['version'], $plugin['version'][0]['value'], ">="))
		{
			$table->construct_cell("<strong>{$names[$plugin['attributes']['guid']]['name']}</strong>");
			$table->construct_cell("{$names[$plugin['attributes']['guid']]['version']}", array("class" => "align_center"));
			$table->construct_cell("<strong><span style=\"color: #C00\">{$plugin['version']['value']}</span></strong>", array("class" => "align_center"));
			$table->construct_cell("<strong><a href=\"http://mods.mybboard.net/view.php?did={$plugin['download_url']['value']}\" target=\"_blank\">{$lang->download}</a></strong>", array("class" => "align_center"));
			$table->construct_row();
		}
	}
	
	if($table->num_rows() == 0)
	{
		flash_message($lang->success_plugins_up_to_date, 'success');
		admin_redirect("index.php?".SID."&module=config/plugins");
	}
	
	$page->add_breadcrumb_item($lang->plugin_updates);
	
	$page->output_header($lang->plugin_updates);
	
	$sub_tabs['update_plugins'] = array(
		'title' => $lang->plugin_updates,
		'link' => "index.php?".SID."&amp;module=config/plugin&amp;action=check",
		'description' => $lang->plugin_updates_desc
	);
	
	$page->output_nav_tabs($sub_tabs, 'update_plugins');
	
	$table->output($lang->plugin_updates);
	
	$page->output_footer();
}

// Activates or deactivates a specific plugin
if($mybb->input['action'] == "activate" || $mybb->input['action'] == "deactivate")
{
	$codename = $mybb->input['plugin'];
	$codename = str_replace(array(".", "/", "\\"), "", $codename);
	$file = basename($codename.".php");

	// Check if the file exists and throw an error if it doesn't
	if(!file_exists(MYBB_ROOT."inc/plugins/$file"))
	{
		flash_message($lang->error_invalid_plugin, 'error');
		admin_redirect("index.php?".SID."&module=config/plugins");
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

	if($mybb->input['action'] == "activate")
	{
		$message = $lang->success_plugin_activated;

		// Plugin is compatible with this version?
		if($plugins->is_compatible($codename) == false)
		{
			flash_message(sprintf($lang->plugin_incompatible, $mybb->version_code), 'error');
			admin_redirect("index.php?".SID."&module=config/plugins");
		}

		// If not installed and there is a custom installation function
		if($installed == false && function_exists("{$codename}_install"))
		{
			call_user_func("{$codename}_install");
			$message = $lang->success_plugin_installed;
		}

		if(function_exists("{$codename}_activate"))
		{
			call_user_func("{$codename}_activate");
		}

		$active_plugins[$codename] = $codename;
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
		}

		unset($active_plugins[$codename]);
	}

	// Update plugin cache
	$plugins_cache['active'] = $active_plugins;
	$cache->update("plugins", $plugins_cache);

	// Log admin action
	log_admin_action($mybb->input['action'], $codename);

	flash_message($message, 'success');
	admin_redirect("index.php?".SID."&module=config/plugins");
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->plugins);

	$sub_tabs['plugins'] = array(
		'title' => $lang->plugins,
		'link' => "index.php?".SID."&amp;module=config/plugins",
		'description' => $lang->plugins_desc
	);
	$sub_tabs['update_plugins'] = array(
		'title' => $lang->plugin_updates,
		'link' => "index.php?".SID."&amp;module=config/plugins&amp;action=check",
		'description' => $lang->plugin_updates_desc
	);
	
	$page->output_nav_tabs($sub_tabs, 'plugins');
	
	$plugins_cache = $cache->read("plugins");
	$active_plugins = $plugins_cache['active'];
	
	$plugins_list = get_plugins_list();

	$table = new Table;
	$table->construct_header($lang->plugin);
	$table->construct_header($lang->controls, array("colspan" => 2, "class" => "align_center", "width" => 300));
	
	if(!empty($plugins_list))
	{
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
			if($plugininfo['website'])
			{
				$plugininfo['name'] = "<a href=\"".$plugininfo['website']."\">".$plugininfo['name']."</a>";
			}
			
			if($plugininfo['authorsite'])
			{
				$plugininfo['author'] = "<a href=\"".$plugininfo['authorsite']."\">".$plugininfo['author']."</a>";
			}

			if($plugins->is_compatible($codename) == false)
			{
				$compatibility_warning = "<br /><span style=\"color: red;\">".sprintf($lang->plugin_incompatible, $mybb->version_code)."</span>";
			}

			$installed_func = "{$codename}_is_installed";
			$install_func = "{$codename}_install";
			$uninstall_func = "{$codename}_uninstall";

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
					$table->construct_cell("&nbsp;", array("class" => "align_center", "colspan" => 2));
				}
				else
				{
					$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/plugins&amp;action=activate&amp;plugin={$codename}\">{$lang->install_and_activate}</a>", array("class" => "align_center", "colspan" => 2));
				}
			}
			// Plugin is activated and installed
			else if($active_plugins[$codename])
			{
				$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/plugins&amp;action=deactivate&amp;plugin={$codename}\">{$lang->deactivate}</a>", array("class" => "align_center", "width" => 150));
				if($uninstall_button)
				{
					$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin={$codename}\">{$lang->uninstall}</a>", array("class" => "align_center", "width" => 150));
				}
				else
				{
					$table->construct_cell("&nbsp;", array("class" => "align_center", "width" => 150));
				}
			}
			// Plugin is installed but not active
			else if($installed == true)
			{
				$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/plugins&amp;action=activate&amp;plugin={$codename}\">{$lang->activate}</a>", array("class" => "align_center", "width" => 150));
				if($uninstall_button)
				{
					$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin={$codename}\">{$lang->uninstall}</a>", array("class" => "align_center", "width" => 150));
				}
				else
				{
					$table->construct_cell("&nbsp;", array("class" => "align_center", "width" => 150));
				}
			}
			$table->construct_row();
		}
	}
	
	if($table->num_rows() == 0)
	{
		$table->contruct_cell($lang->no_plugins, array('colspan' => 2));
		$table->construct_row();
	}
	$table->output($lang->plugins);

	$page->output_footer();
}

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
?>