<?php

$page->add_breadcrumb_item("Plugins", "index.php?".SID."&module=config/plugins");


// Activates or deactivates a specific plugin
if($mybb->input['action'] == "activate" || $mybb->input['action'] == "deactivate")
{
	$codename = $mybb->input['plugin'];
	$codename = str_replace(array(".", "/", "\\"), "", $codename);
	$file = basename($codename.".php");
	if($mybb->input['action'] == "activate")
	{
		$active_plugins[$codename] = $codename;
		$userfunc = $codename."_activate";
		$message = "The plugin has been successfully activated.";
	}
	else if($mybb->input['action'] == "deactivate")
	{
		unset($active_plugins[$codename]);
		$userfunc = $codename."_deactivate";
		$message = "The plugin has been successfully deactivated.";
	}

	// Check if the file exists and throw an error if it doesn't
	if(!file_exists(MYBB_ROOT."inc/plugins/$file"))
	{
		flash_message('The specified plugin does not exist', 'error');
		admin_redirect("index.php?".SID."&module=config/plugins");
	}

	require_once MYBB_ROOT."inc/plugins/$file";

	// If this plugin has an activate/deactivate function then run it
	if(function_exists($userfunc))
	{
		$userfunc();
	}

	// Update plugin cache
	$plugins_cache['active'] = $active_plugins;
	$cache->update("plugins", $plugins_cache);

	flash_message($message, 'success');
	admin_redirect("index.php?".SID."&module=config/plugins");
}

if(!$mybb->input['action'])
{
	$page->output_header("Plugins");
	if($message)
	{
		$page->output_inline_message($message);
	}

	$sub_tabs['change_plugins'] = array(
		'title' => "Change Plugins",
		'link' => "index.php?".SID."&amp;module=config/plugins",
		'description' => "This section allows you to activate, deactivate, and manage the plugins that you have uploaded to your forum's <strong>inc/plugins</strong> directory."
	);
	$sub_tabs['update_plugins'] = array(
		'title' => "Plugin Updates",
		'link' => "index.php?".SID."&amp;module=config/plugin&amp;action=check",
		'description' => "This section allows you to check for updates on all your plugins."
	);
	
	$page->output_nav_tabs($sub_tabs, 'change_plugins');
	
	$plugins_cache = $cache->read("plugins");
	$active_plugins = $plugins_cache['active'];
	
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

	$table = new Table;
	$table->construct_header("Plugin");
	$table->construct_header("Controls", array("class" => "align_center"));
	
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
			if($plugininfo['website'])
			{
				$plugininfo['name'] = "<a href=\"".$plugininfo['website']."\">".$plugininfo['name']."</a>";
			}
			if($plugininfo['authorsite'])
			{
				$plugininfo['author'] = "<a href=\"".$plugininfo['authorsite']."\">".$plugininfo['author']."</a>";
			}
			if(isset($active_plugins[$codename]))
			{
				$pluginbuttons = "<a href=\"index.php?".SID."&amp;module=config/plugins&amp;action=deactivate&amp;plugin={$codename}\">Deactivate</a>";
			}
			else
			{
				$pluginbuttons = "<a href=\"index.php?".SID."&amp;module=config/plugins&amp;action=activate&amp;plugin={$codename}\">Activate</a>";
			}
			
			$table->construct_cell("<strong>{$plugininfo['name']}</strong> ({$plugininfo['version']})<br /><small>{$plugininfo['description']}</small><br /><i><small>Created by {$plugininfo['author']}</small></i>");
			$table->construct_cell($pluginbuttons, array("class" => "align_center"));
			$table->construct_row();
		}
	}
	else
	{
		$table->contruct_cell("There are no plugins on your forum at this time.");
		$table->contruct_cell("");
		$table->construct_row();
	}
	$table->output("Plugins");

	$page->output_footer();
}
?>