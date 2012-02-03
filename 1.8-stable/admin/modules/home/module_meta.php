<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: module_meta.php 5620 2011-09-26 18:23:52Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function home_meta()
{
	global $page, $lang, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "dashboard", "title" => $lang->dashboard, "link" => "index.php?module=home-dashboard");
	$sub_menu['20'] = array("id" => "preferences", "title" => $lang->preferences, "link" => "index.php?module=home-preferences");
	$sub_menu['30'] = array("id" => "version_check", "title" => $lang->version_check, "link" => "index.php?module=home-version_check");
	$sub_menu['40'] = array("id" => "credits", "title" => $lang->mybb_credits, "link" => "index.php?module=home-credits");
	$sub_menu = $plugins->run_hooks("admin_home_menu", $sub_menu);
	
	$page->add_menu_item($lang->home, "home", "index.php", 1, $sub_menu);
	
	return true;
}

function home_action_handler($action)
{
	global $page, $db, $lang, $plugins;
	
	$page->active_module = "home";
	
	$actions = array(
		'preferences' => array('active' => 'preferences', 'file' => 'preferences.php'),
		'credits' => array('active' => 'credits', 'file' => 'credits.php'),
		'version_check' => array('active' => 'version_check', 'file' => 'version_check.php'),
		'dashboard' => array('active' => 'dashboard', 'file' => 'index.php')
	);
	
	if(!isset($actions[$action]))
	{
		$page->active_action = "dashboard";
	}
	else
	{
		$page->active_action = $actions[$action]['active'];
	}
	
	$actions = $plugins->run_hooks("admin_home_action_handler", $actions);
	
	if($page->active_action == "dashboard")
	{
		// Quick Access
		$sub_menu = array();
		$sub_menu['10'] = array("id" => "add_forum", "title" => $lang->add_new_forum, "link" => "index.php?module=forum-management&action=add");
		$sub_menu['20'] = array("id" => "search", "title" => $lang->search_for_users, "link" => "index.php?module=user-users&action=search");
		$sub_menu['30'] = array("id" => "themes", "title" => $lang->themes, "link" => "index.php?module=style-themes");
		$sub_menu['40'] = array("id" => "templates", "title" => $lang->templates, "link" => "index.php?module=style-templates");
		$sub_menu['50'] = array("id" => "plugins", "title" => $lang->plugins, "link" => "index.php?module=config-plugins");
		$sub_menu['60'] = array("id" => "backupdb", "title" => $lang->database_backups, "link" => "index.php?module=tools-backupdb");
		
		$sub_menu = $plugins->run_hooks("admin_home_menu_quick_access", $sub_menu);
		
		$sidebar = new SidebarItem($lang->quick_access);
		$sidebar->add_menu_items($sub_menu, $page->active_action);
		
		$page->sidebar .= $sidebar->get_markup();

		// Online Administrators in the last 30 minutes
		$timecut = TIME_NOW-60*30;
		$query = $db->simple_select("adminsessions", "uid, ip", "lastactive > {$timecut}");
		$online_users = "<ul class=\"menu online_admins\">";
		$online_admins = array();
		
		// If there's only 1 user online, it has to be us.
		if($db->num_rows($query) == 1)
		{
			global $mybb;
			
			$online_admins[$mybb->user['username']] = array(
				"uid" => $mybb->user['uid'],
				"username" => $mybb->user['username'],
				"ip" => $db->fetch_field($query, "ip")
			);
		}
		else
		{
			$uid_in = array();
			while($user = $db->fetch_array($query))
			{
				$uid_in[] = $user['uid'];
				$online_admins[$user['uid']] = array(
					"uid" => $user['uid'],
					"username" => "",
					"ip" => $user['ip']
				);
			}
			
			$query = $db->simple_select("users", "uid, username", "uid IN(".implode(',', $uid_in).")", array('order_by' => 'username'));
			while($user = $db->fetch_array($query))
			{
				$online_admins[$user['username']] = array(
					"uid" => $user['uid'],
					"username" => $user['username'],
					"ip" => $online_admins[$user['uid']]['ip']
				);
				unset($online_admins[$user['uid']]);
			}
		}
		
		$done_users = array();
		
		asort($online_admins);
		
		foreach($online_admins as $user)
		{
			if(!$done_users["{$user['uid']}.{$user['ip']}"])
			{
				if($user['type'] == "mobile")
				{
					$class = " class=\"mobile_user\"";
				}
				else
				{
					$class = "";
				}
				$online_users .= "<li{$class}>".build_profile_link($user['username'], $user['uid'], "_blank")."</li>";
				$done_users["{$user['uid']}.{$user['ip']}"] = 1;
			}
		}
		$online_users .= "</ul>";
		$sidebar = new SidebarItem($lang->online_admins);
		$sidebar->set_contents($online_users);

		$page->sidebar .= $sidebar->get_markup();
	}

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}
	else
	{
		$page->active_action = "dashboard";
		return "index.php";
	}
}

?>