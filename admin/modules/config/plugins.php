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

$action = $mybb->get_input('action');

$plugins->run_hooks("admin_config_plugins_begin");

if($action == "browse")
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
	$keywords = "";
	if($mybb->get_input('keywords'))
	{
		$keywords = "&keywords=".urlencode($mybb->input['keywords']);
	}

	if($mybb->get_input('page'))
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

	$contents = fetch_remote_file("https://community.mybb.com/xmlbrowse.php?api=2&type=plugins&version={$search_version}{$keywords}{$url_page}");

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

	$parser = create_xml_parser($contents);
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
			$result['author']['url']['value'] = htmlspecialchars_uni($result['author']['url']['value']);
			$result['author']['name']['value'] = htmlspecialchars_uni($result['author']['name']['value']);
			$result['version']['value'] = htmlspecialchars_uni($result['version']['value']);
			$result['download_url']['value'] = htmlspecialchars_uni(html_entity_decode($result['download_url']['value']));

			$table->construct_cell("<strong>{$result['name']['value']}</strong><br /><small>{$result['description']['value']}</small><br /><i><small>{$lang->created_by} <a href=\"{$result['author']['url']['value']}\" target=\"_blank\" rel=\"noopener\">{$result['author']['name']['value']}</a></small></i>");
			$table->construct_cell($result['version']['value'], array("class" => "align_center"));
			$table->construct_cell("<strong><a href=\"https://community.mybb.com/{$result['download_url']['value']}\" target=\"_blank\" rel=\"noopener\">{$lang->download}</a></strong>", array("class" => "align_center"));
			$table->construct_row();
		}
	}

	$no_results = false;
	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->error_no_results_found, array("colspan" => 3));
		$table->construct_row();
		$no_results = true;
	}

	$search = new Form("index.php?module=config-plugins&amp;action=browse", 'post', 'search_form');
	echo "<div style=\"padding-bottom: 3px; margin-top: -9px; text-align: right;\">";
	if($mybb->get_input('keywords'))
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
		form.on('submit', function()
		{
			var search = $(\"#search_keywords\");
			if(search.val() == '' || search.val() == '{$lang->search_for_plugins}')
			{
				search.trigger('focus');
				return false;
			}
		});

		var search = $(\"#search_keywords\");
		search.on('focus', function()
		{
			var searched_focus = $(this);
			if(searched_focus.val() == '{$lang->search_for_plugins}')
			{
				searched_focus.removeClass(\"search_default\");
				searched_focus.val(\"\");
			}
		}).on('blur', function()
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

	if(!$no_results)
	{
		echo "<br />".draw_admin_pagination($mybb->input['page'], 15, $tree['results']['attributes']['total'], "index.php?module=config-plugins&amp;action=browse{$keywords}&amp;page={page}");
	}

	$page->output_footer();
}

if($action == "check")
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
			$plugininfo['guid'] = isset($plugininfo['guid']) ? trim($plugininfo['guid']) : null;
			$plugininfo['codename'] = isset($plugininfo['codename']) ? trim($plugininfo['codename']) : null;

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
	$contents = fetch_remote_file($url);

	if(!$contents)
	{
		flash_message($lang->error_vcheck_communications_problem, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	$contents = trim($contents);

	$parser = create_xml_parser($contents);
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
			$plugin['version']['value'] = htmlspecialchars_uni($plugin['version']['value']);

			if(isset($plugin['vulnerable']['value']))
			{
				$plugin['vulnerable']['value'] = htmlspecialchars_uni($plugin['vulnerable']['value']);
			}

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

$is_upload = ($action == 'upload');
if($is_upload)
{
	if(!class_exists('ZipArchive'))
	{
		flash_message($lang->error_no_ziparchive_for_plugin, 'error');
		admin_redirect('index.php?module=config-plugins');
	}
	else if(empty($_FILES['plugin_upload']['tmp_name']))
	{
		flash_message($lang->error_no_plugin_uploaded, 'error');
		admin_redirect('index.php?module=config-plugins');
	}
	else
	{
		$maxtries = 100;
		$tmpdir = create_temp_dir();
		if (!$tmpdir)
		{
			flash_message($lang->error_plugin_unzip_tmpdir_failed, 'error');
			admin_redirect('index.php?module=config-plugins');
		}
		else if(!mkdir($tmpdir.'/extracted/', 0777, true))
		{
			rmdir_recursive($tmpdir);
			flash_message($lang->error_plugin_unzip_tmpdir_failed, 'error');
			admin_redirect('index.php?module=config-plugins');
		}
		else if(!move_uploaded_file($_FILES['plugin_upload']['tmp_name'], $tmpdir.'/'.$_FILES['plugin_upload']['name']))
		{
			rmdir_recursive($tmpdir);
			flash_message($lang->error_move_uploaded_plugin_failed, 'error');
			admin_redirect('index.php?module=config-plugins');
		}
		else
		{
			$za = new ZipArchive;
			$res = $za->open($tmpdir.'/'.$_FILES['plugin_upload']['name']);
			if($res !== true)
			{
				rmdir_recursive($tmpdir);
				flash_message($lang->sprintf($lang->error_plugin_unzip_open_failed, $res), 'error');
				admin_redirect('index.php?module=config-plugins');
			}
			else
			{
				if(!$za->extractTo($tmpdir.'/extracted/'))
				{
					rmdir_recursive($tmpdir);
					flash_message($lang->error_plugin_unzip_failed, 'error');
					admin_redirect('index.php?module=config-plugins');
				}
				$za->close();
				$top_lvl_files = glob($tmpdir.'/extracted/*');
				if(count($top_lvl_files) != 1)
				{
					rmdir_recursive($tmpdir);
					flash_message($lang->error_plugin_unzip_multi_or_none_root, 'error');
					admin_redirect('index.php?module=config-plugins');

				}
				else
				{
					$do_integrate = false;
					$plugin_code = basename($top_lvl_files[0]);
					$file = basename("{$plugin_code}.php");
					if(file_exists(MYBB_ROOT."inc/plugins/{$plugin_code}/{$file}"))
					{
						$action = 'upgrade';
						$plugininfo    = read_json_file("{$top_lvl_files[0]}/plugin.json");
						$pi_integrated = read_json_file(MYBB_ROOT."inc/plugins/{$plugin_code}/plugin.json");

						if(isset($pi_integrated['version'])
							&&
							isset($plugininfo['version'])
							&&
							version_compare($pi_integrated['version'], $plugininfo['version']) >= 0
							&&
							empty($mybb->input['ignore_vers'])
						)
						{
							rmdir_recursive($tmpdir);
							flash_message($lang->error_plugin_uploaded_less_or_equal_vers, 'error');
							admin_redirect('index.php?module=config-plugins');
						}
						else
						{
							rmdir_recursive(MYBB_ROOT."inc/plugins/{$plugin_code}/{$file}");
							$do_integrate = true;
						}
					}
					else
					{
						$action = 'activate';
						$do_integrate = true;
					}
					if ($do_integrate)
					{
						// For access to the new `_is_installed()` function below
						require_once "{$top_lvl_files[0]}}/{$file}";

						// We use cp_or_mv_recursively() rather than rename() because of this PHP bug:
						// https://bugs.php.net/bug.php?id=54097
						if (!cp_or_mv_recursively($top_lvl_files[0], MYBB_ROOT."inc/plugins/{$plugin_code}", /*$del_source = */true, $error))
						{
							rmdir_recursive($tmpdir);
							flash_message($lang->sprintf($lang->error_plugin_move_fail, $error), 'error');
							admin_redirect('index.php?module=config-plugins');
						}
					}
				}
			}
		}
	}
}

// Upgrades, activates, or deactivates a specific plugin
if($action == 'activate' || $action == 'deactivate' || $action == 'upgrade')
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	$do_upgrade = false;

	if($action == "activate")
	{
		$plugins->run_hooks("admin_config_plugins_activate");
	}
	else if($action == 'upgrade')
	{
		$do_upgrade = true;
		$plugins->run_hooks('admin_config_plugins_upgrade');
	}
	else
	{
		$plugins->run_hooks("admin_config_plugins_deactivate");
	}

	$codename = !empty($plugin_code) ? $plugin_code : $mybb->input['plugin'];
	$codename = str_replace(array(".", "/", "\\"), "", $codename);
	$file = basename($codename.".php");

	$integrated = file_exists(MYBB_ROOT."inc/plugins/{$codename}/{$file}");
	if(!$integrated)
	{
		flash_message($lang->error_invalid_plugin, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	$plugins_cache = $cache->read("plugins");
	$active_plugins = isset($plugins_cache['active']) ? $plugins_cache['active'] : array();

	// Note: The new plugin file is instead included above (i.e., when $do_upgrade is true)
	if(!$do_upgrade)
	{
		require_once MYBB_ROOT."inc/plugins/{$codename}/{$file}";
	}

	$installed_func = "{$codename}_is_installed";
	$installed = true;
	if(function_exists($installed_func) && $installed_func() != true)
	{
		$installed = false;
	}

	$install_uninstall = false;

	if($do_upgrade)
	{
		$plugininfo = read_json_file(MYBB_ROOT."inc/plugins/{$codename}/plugin.json");

		// Check the plugin's compatibility with the current MyBB version
		if($plugins->is_compatible($plugininfo['compatibility']) == false)
		{
			flash_message($lang->sprintf($lang->plugin_incompatible, $mybb->version), 'error');
			admin_redirect('index.php?module=config-plugins');
		}

		// Try to archive plugin's themelet
		require_once MYBB_ROOT.'inc/functions_themes.php';
		if(!archive_themelet($codename, /*$is_plugin_themelet = */true, $err_msg))
		{
			flash_message($err_msg, 'error');
			admin_redirect('index.php?module=config-plugins');
		}

		// Run any custom upgrade function as required
		if($installed && function_exists("{$codename}_upgrade"))
		{
			call_user_func("{$codename}_upgrade");
		}
		$message = $lang->success_plugin_updated;
	}

	if($do_upgrade || $action == "activate")
	{
		if(!$do_upgrade)
		{
			$message = $lang->success_plugin_activated;
		} else {
			// Plugin is compatible with this version?
			$plugininfo = read_json_file(MYBB_ROOT."inc/plugins/{$codename}/plugin.json");
			if (empty($plugininfo['compatibility']) || $plugins->is_compatible($plugininfo['compatibility']) == false) {
				flash_message($lang->sprintf($lang->plugin_incompatible, $mybb->version), 'error');
				admin_redirect("index.php?module=config-plugins");
			}
		}

		// If not installed and there is a custom installation function
		if($installed == false && function_exists("{$codename}_install"))
		{
			call_user_func("{$codename}_install");
			$message = $do_upgrade ? $lang->success_plugin_upgraded_install_activated : $lang->success_plugin_installed;
			$install_uninstall = true;
		}

		if(function_exists("{$codename}_activate"))
		{
			call_user_func("{$codename}_activate");
		}

		$active_plugins[$codename] = $codename;
		$executed[] = 'activate';
	}
	else if($action == "deactivate")
	{
		$message = $lang->success_plugin_deactivated;

		if(function_exists("{$codename}_deactivate"))
		{
			call_user_func("{$codename}_deactivate");
		}

		if($mybb->get_input('uninstall') == 1 && function_exists("{$codename}_uninstall"))
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

	if($action == "activate")
	{
		$plugins->run_hooks("admin_config_plugins_activate_commit");
	}
	else if($do_upgrade)
	{
		$plugins->run_hooks('admin_config_plugins_upgrade_commit');
	}
	else
	{
		$plugins->run_hooks("admin_config_plugins_deactivate_commit");
	}

	flash_message($message, 'success');
	admin_redirect("index.php?module=config-plugins");
}

if(!$action)
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
	$active_plugins = array();
	if(!empty($plugins_cache['active']))
	{
		$active_plugins = $plugins_cache['active'];
	}

	$plugins_list = get_plugins_list();

	$plugins->run_hooks("admin_config_plugins_plugin_list");

	$form = new Form('index.php?module=config-plugins&amp;action=upload', 'post', '', 1);
	$table = new Table;
	$table->construct_header($lang->upload_plugin_desc);
	$table->construct_cell($form->generate_file_upload_box('plugin_upload', array('style' => 'width: 230px;')).
	                       $form->generate_check_box('ignore_vers', 1, $lang->plugin_ignore_vers).
	                       ' '.
	                       $form->generate_submit_button($lang->install_uploaded_plugin));
	$table->construct_row();
	$table->output($lang->upload_plugin);
	$form->end();

	if(!empty($plugins_list))
	{
		$a_plugins = $i_plugins = array();

		if(!empty($plugins_list))
		{
			foreach($plugins_list as $plugin_file)
			{
				$codename = str_replace('.php', '', $plugin_file);
				$plugininfo = read_json_file(MYBB_ROOT."inc/plugins/{$codename}/plugin.json", $err_msg, /*$show_errs = */false);
				if(!$plugininfo)
				{
					continue;
				}
				plugininfo_keys_to_raw($plugininfo, true);
				$plugininfo['codename'] = $codename;
				require_once MYBB_ROOT."inc/plugins/{$codename}/{$codename}.php";
				$dyndescfunc = $codename.'_dyndesc';
				if(function_exists($dyndescfunc))
				{
					$dyndescfunc($plugininfo['description']);
				}

				if(isset($active_plugins[$codename]))
				{
					// This is an active plugin
					$plugininfo['is_active'] = 1;

					$a_plugins[] = $plugininfo;
				}
				else
				{
					// Either installed and not active or completely inactive
					$plugininfo['is_active'] = 0;
					$i_plugins[] = $plugininfo;
				}
			}
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
 * @param array $plugin_list
 */
function build_plugin_list($plugin_list)
{
	global $lang, $mybb, $plugins, $table;

	foreach($plugin_list as $plugininfo)
	{
		if(!empty($plugininfo['website']))
		{
			$plugininfo['name'] = "<a href=\"".$plugininfo['website']."\">".$plugininfo['name']."</a>";
		}

		if(!empty($plugininfo['authorsite']))
		{
			$plugininfo['author'] = "<a href=\"".$plugininfo['authorsite']."\">".$plugininfo['author']."</a>";
		}

		if($plugins->is_compatible($plugininfo['compatibility']) == false)
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

		if(!empty($plugininfo['upgradeable']))
		{
			$table->construct_cell('<a href="index.php?module=config-plugins&amp;action=upgrade&amp;plugin='.$plugininfo['codename'].'&amp;my_post_key='.$mybb->post_code.'">'.($installed ? $lang->upgrade_plugin : $lang->upgrade_install_activate_plugin).'</a>', array("class" => "align_center", "colspan" => 2));
		}
		// Plugin is not installed at all
		else if($installed == false)
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
