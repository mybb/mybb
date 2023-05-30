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

$page->add_breadcrumb_item($lang->cache_manager, "index.php?module=tools-cache");

$plugins->run_hooks("admin_tools_cache_begin");

if($mybb->input['action'] == 'view')
{
	if(!trim($mybb->input['title']))
	{
		flash_message($lang->error_no_cache_specified, 'error');
		admin_redirect("index.php?module=tools-cache");
	}

	$plugins->run_hooks("admin_tools_cache_view");

	// Rebuilds forum settings
	if($mybb->input['title'] == 'settings')
	{
		$cachedsettings = (array)$mybb->settings;
		if(isset($cachedsettings['internal']))
		{
			unset($cachedsettings['internal']);
		}

		$cacheitem = array(
			'title'	=> 'settings',
			'cache'	=> my_serialize($cachedsettings)
		);
	}
	else
	{
		$query = $db->simple_select("datacache", "*", "title = '".$db->escape_string($mybb->input['title'])."'");
		$cacheitem = $db->fetch_array($query);
	}

	if(!$cacheitem)
	{
		flash_message($lang->error_incorrect_cache, 'error');
		admin_redirect("index.php?module=tools-cache");
	}

	// use PHP's own unserialize() for performance reasons
	$cachecontents = unserialize($cacheitem['cache'], array('allowed_classes' => false));

	if(empty($cachecontents))
	{
		$cachecontents = $lang->error_empty_cache;
	}
	ob_start();
	print_r($cachecontents);
	$cachecontents = htmlspecialchars_uni(ob_get_contents());
	ob_end_clean();

	$page->add_breadcrumb_item($lang->view);
	$page->output_header($lang->cache_manager);

	$table = new Table;

	$table->construct_cell("<pre>\n{$cachecontents}\n</pre>");
	$table->construct_row();
	$table->output($lang->cache." {$cacheitem['title']}");

	$page->output_footer();

}

if($mybb->input['action'] == "rebuild" || $mybb->input['action'] == "reload")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=tools-cache");
	}

	$plugins->run_hooks("admin_tools_cache_rebuild");

	// Rebuilds forum settings
	if($mybb->input['title'] == 'settings')
	{
		rebuild_settings();

		$plugins->run_hooks("admin_tools_cache_rebuild_commit");

		// Log admin action
		log_admin_action($mybb->input['title']);

		flash_message($lang->success_cache_reloaded, 'success');
		admin_redirect("index.php?module=tools-cache");
	}

	if(method_exists($cache, "update_{$mybb->input['title']}"))
	{
		$func = "update_{$mybb->input['title']}";
		$cache->$func();

		$plugins->run_hooks("admin_tools_cache_rebuild_commit");

		// Log admin action
		log_admin_action($mybb->input['title']);

		flash_message($lang->success_cache_rebuilt, 'success');
		admin_redirect("index.php?module=tools-cache");
	}
	elseif(method_exists($cache, "reload_{$mybb->input['title']}"))
	{
		$func = "reload_{$mybb->input['title']}";
		$cache->$func();

		$plugins->run_hooks("admin_tools_cache_rebuild_commit");

		// Log admin action
		log_admin_action($mybb->input['title']);

		flash_message($lang->success_cache_reloaded, 'success');
		admin_redirect("index.php?module=tools-cache");
	}
	elseif(function_exists("update_{$mybb->input['title']}"))
	{
		$func = "update_{$mybb->input['title']}";
		$func();

		$plugins->run_hooks("admin_tools_cache_rebuild_commit");

		// Log admin action
		log_admin_action($mybb->input['title']);

		flash_message($lang->success_cache_rebuilt, 'success');
		admin_redirect("index.php?module=tools-cache");
	}
	elseif(function_exists("reload_{$mybb->input['title']}"))
	{
		$func = "reload_{$mybb->input['title']}";
		$func();

		$plugins->run_hooks("admin_tools_cache_rebuild_commit");

		// Log admin action
		log_admin_action($mybb->input['title']);

		flash_message($lang->success_cache_reloaded, 'success');
		admin_redirect("index.php?module=tools-cache");
	}
	else
	{
		flash_message($lang->error_cannot_rebuild, 'error');
		admin_redirect("index.php?module=tools-cache");
	}
}

if($mybb->input['action'] == "rebuild_all")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=tools-cache");
	}

	$plugins->run_hooks("admin_tools_cache_rebuild_all");

	$query = $db->simple_select("datacache");
	while($cacheitem = $db->fetch_array($query))
	{
		if(method_exists($cache, "update_{$cacheitem['title']}"))
		{
			$func = "update_{$cacheitem['title']}";
			$cache->$func();
		}
		elseif(method_exists($cache, "reload_{$cacheitem['title']}"))
		{
			$func = "reload_{$cacheitem['title']}";
			$cache->$func();
		}
		elseif(function_exists("update_{$cacheitem['title']}"))
		{
			$func = "update_{$cacheitem['title']}";
			$func();
		}
		elseif(function_exists("reload_{$cacheitem['title']}"))
		{
			$func = "reload_{$cacheitem['title']}";
			$func();
		}
	}

	// Rebuilds forum settings
	rebuild_settings();

	$plugins->run_hooks("admin_tools_cache_rebuild_all_commit");

	// Log admin action
	log_admin_action();

	flash_message($lang->success_cache_reloaded, 'success');
	admin_redirect("index.php?module=tools-cache");
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->cache_manager);

	$sub_tabs['cache_manager'] = array(
		'title' => $lang->cache_manager,
		'link' => "index.php?module=tools-cache",
		'description' => $lang->cache_manager_description
	);

	$plugins->run_hooks("admin_tools_cache_start");

	$page->output_nav_tabs($sub_tabs, 'cache_manager');

	$table = new Table;
	$table->construct_header($lang->name);
	$table->construct_header($lang->size, array("class" => "align_center", "width" => 100));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

	$query = $db->simple_select("datacache", "*", "", array("order_by" => "title"));
	while($cacheitem = $db->fetch_array($query))
	{
		$table->construct_cell("<strong><a href=\"index.php?module=tools-cache&amp;action=view&amp;title=".urlencode($cacheitem['title'])."\">{$cacheitem['title']}</a></strong>");
		$table->construct_cell(get_friendly_size(strlen($cacheitem['cache'])), array("class" => "align_center"));

		if(method_exists($cache, "update_".$cacheitem['title']))
		{
			$table->construct_cell("<a href=\"index.php?module=tools-cache&amp;action=rebuild&amp;title=".urlencode($cacheitem['title'])."&amp;my_post_key={$mybb->post_code}\">".$lang->rebuild_cache."</a>", array("class" => "align_center"));
		}
		elseif(method_exists($cache, "reload_".$cacheitem['title']))
		{
			$table->construct_cell("<a href=\"index.php?module=tools-cache&amp;action=reload&amp;title=".urlencode($cacheitem['title'])."&amp;my_post_key={$mybb->post_code}\">".$lang->reload_cache."</a>", array("class" => "align_center"));
		}
		elseif(function_exists("update_".$cacheitem['title']))
		{
			$table->construct_cell("<a href=\"index.php?module=tools-cache&amp;action=rebuild&amp;title=".urlencode($cacheitem['title'])."&amp;my_post_key={$mybb->post_code}\">".$lang->rebuild_cache."</a>", array("class" => "align_center"));
		}
		elseif(function_exists("reload_".$cacheitem['title']))
		{
			$table->construct_cell("<a href=\"index.php?module=tools-cache&amp;action=reload&amp;title=".urlencode($cacheitem['title'])."&amp;my_post_key={$mybb->post_code}\">".$lang->reload_cache."</a>", array("class" => "align_center"));
		}
		else
		{
			$table->construct_cell("");
		}

		$table->construct_row();
	}

	// Rebuilds forum settings
	$cachedsettings = (array)$mybb->settings;
	if(isset($cachedsettings['internal']))
	{
		unset($cachedsettings['internal']);
	}

	$table->construct_cell("<strong><a href=\"index.php?module=tools-cache&amp;action=view&amp;title=settings\">settings</a></strong>");
	$table->construct_cell(get_friendly_size(strlen(my_serialize($cachedsettings))), array("class" => "align_center"));
	$table->construct_cell("<a href=\"index.php?module=tools-cache&amp;action=reload&amp;title=settings&amp;my_post_key={$mybb->post_code}\">".$lang->reload_cache."</a>", array("class" => "align_center"));

	$table->construct_row();

	$table->output("<div style=\"float: right;\"><small><a href=\"index.php?module=tools-cache&amp;action=rebuild_all&amp;my_post_key={$mybb->post_code}\">".$lang->rebuild_reload_all."</a></small></div>".$lang->cache_manager);

	$page->output_footer();
}

