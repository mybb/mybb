<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: index.php 2992 2007-04-05 14:43:48Z chris $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
require_once MYBB_ROOT."inc/functions_upload.php";


$page->add_breadcrumb_item("Themes", "index.php?".SID."&amp;module=style/themes");

if($mybb->input['action'] == "add" || $mybb->input['action'] == "import" || !$mybb->input['action'])
{
	$sub_tabs['themes'] = array(
		'title' => "Themes",
		'link' => "index.php?".SID."&amp;module=style/themes"
	);

	$sub_tabs['create_theme'] = array(
		'title' => "Create New Theme",
		'link' => "index.php?".SID."&amp;module=stylethemes&amp;action=add"
	);

	$sub_tabs['import_theme'] = array(
		'title' => "Import a User",
		'link' => "index.php?".SID."&amp;module=style/themes&amp;action=import"
	);
}

if($mybb->input['action'] == "import")
{
	if($mybb->request_method == "post")
	{
	}
}

if($mybb->input['action'] == "export")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?".SID."&module=style/themes");
	}

	if($mybb->request_method == "post")
	{
	}
}

if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
	}
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?".SID."&module=style/themes");
	}

	if($mybb->request_method == "post")
	{
	}
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?".SID."&module=style/themes");
	}

	if($mybb->request_method == "post")
	{
	}
}

if($mybb->input['action'] == "edit_stylesheet")
{
	if($mybb->request_method == "post")
	{
	}
}

if($mybb->input['action'] == "delete_stylesheet")
{
	if($mybb->request_method == "post")
	{
	}
}

if($mybb->input['action'] == "add_stylesheet")
{
	if($mybb->request_method == "post")
	{
	}
}

if($mybb->input['action'] == "set_default")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?".SID."&module=style/themes");
	}

	$updated_theme = array(
		"def" => 0
	);
	$db->update_query("themes", $updated_theme);
	$updated_theme['def'] = 1;
	$db->update_query("themes", $updated_theme, "tid='".intval($mybb->input['tid'])."'");

	flash_message("The selected theme has now been marked as the default.", 'success');
	admin_redirect("index.php?".SID."&module=style/themes");
}

if($mybb->input['action'] == "force")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?".SID."&module=style/themes");
	}

	$updated_users = array(
		"style" => $theme['tid']
	);

	flash_message("The selected theme has now been forced as the default to all users.", 'success');
	admin_redirect("index.php?".SID."&module=style/themes");
}

if(!$mybb->input['action'])
{
	$page->output_header("Themes");
	
	$page->output_nav_tabs($sub_tabs, 'themes');

	$table = new Table;
	$table->construct_header("Theme");
	$table->construct_header("# Users", array("class" => "align_center", "width" => 100));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

	build_theme_list();

	$table->output("Themes");
}

function buid_theme_list($parent=0, $depth=0)
{
	global $mybb, $db, $table; // Global $table is bad, but it will have to do for now
	static $theme_cache;

	$padding = $depth*20; // Padding

	if(!is_array($theme_cache))
	{
		$themes = cache_themes();
		$query = $db->query("
			SELECT style, COUNT(uid) AS users
			FROM ".TABLE_PREFIX."users
			GROUP BY style
		");
		while($user_themes = $db->fetch_array($query))
		{
			$themes[$user_themes['style']]['users'] = intval($user_themes['users'];
		}

		// Restrucure the theme array to something we can "loop-de-loop" with
		foreach($themes as $theme)
		{
			$theme_cache[$theme['pid']][$theme['tid']] = $theme;
		}
		unset($theme);
	}

	if(!is_array($theme_cache[$parent]))
	{
		return;
	}

	foreach($theme_cache[$parent] as $theme)
	{
		$popup = new PopupMenu("theme_{$theme['tid']}", $lang->options);
		if($theme['tid'] > 1)
		{
			$popup->add_item("Edit Theme", "");
			$popup->add_item("Delete Theme", "");
			if($theme['def'] != 1)
			{
				$popup->add_item("Set as Default", "");
				$set_default = "<a href=\"#\"><img src=\"\" title=\"Set as Default\" /></a>";
			}
			else
			{
				$set_default = "<img src=\"\" title=\"Default Theme\" />";
			}
			$popup->add_item("Force on Users", "");
		}
		$popup->add_item("Export Theme", "");
		$table->construct_cell("<div class=\"float_right;\">{$set_default}</div><div style=\"margin-left: {$padding}px\"><strong>{$theme['name']}</strong></div>");
		$table->construct_cell(my_number_format($theme['users']) array("class" => "align_center"));
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();

		// Fetch & build any child themes
		build_theme_list($theme['tid'], ++$depth);
	}
}
?>