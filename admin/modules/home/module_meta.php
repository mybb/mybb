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

/**
 * @return bool true
 */
function home_meta()
{
	global $page, $lang, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "dashboard", "title" => $lang->dashboard, "link" => "index.php?module=home-dashboard");
	$sub_menu['20'] = array("id" => "preferences", "title" => $lang->preferences, "link" => "index.php?module=home-preferences");
	$sub_menu['30'] = array("id" => "docs", "title" => $lang->mybb_documentation, "link" => "https://docs.mybb.com");
	$sub_menu['40'] = array("id" => "credits", "title" => $lang->mybb_credits, "link" => "https://mybb.com/credits");
	$sub_menu = $plugins->run_hooks("admin_home_menu", $sub_menu);

	$page->add_menu_item($lang->home, "home", "index.php", 1, $sub_menu);

	return true;
}

/**
 * @param string $action
 *
 * @return string
 */
function home_action_handler($action)
{
	global $page, $db, $lang, $plugins;

	$page->active_module = "home";

	$actions = array(
		'preferences' => array('active' => 'preferences', 'file' => 'preferences.php'),
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
		$sub_menu['10'] = array("id" => "add_forum", "title" => $lang->add_new_forum, "link" => "index.php?module=forum-management&action=add", "module" => "forum", "action" => "management");
		$sub_menu['20'] = array("id" => "search", "title" => $lang->search_for_users, "link" => "index.php?module=user-users&action=search", "module" => "user", "action" => "users");
		$sub_menu['30'] = array("id" => "themes", "title" => $lang->themes, "link" => "index.php?module=style-themes", "module" => "style", "action" => "themes");
		$sub_menu['40'] = array("id" => "templates", "title" => $lang->templates, "link" => "index.php?module=style-templates", "module" => "style", "action" => "templates");
		$sub_menu['50'] = array("id" => "plugins", "title" => $lang->plugins, "link" => "index.php?module=config-plugins", "module" => "config", "action" => "plugins");
		$sub_menu['60'] = array("id" => "backupdb", "title" => $lang->database_backups, "link" => "index.php?module=tools-backupdb", "module" => "tools", "action" => "backupdb");

		foreach($sub_menu as $id => $sub)
		{
			if(!check_admin_permissions(array("module" => $sub['module'], "action" => $sub['action']), false))
			{
				unset($sub_menu[$id]);
			}
		}

		$sub_menu = $plugins->run_hooks("admin_home_menu_quick_access", $sub_menu);

		if(!empty($sub_menu))
		{
			$sidebar = new SidebarItem($lang->quick_access);
			$sidebar->add_menu_items($sub_menu, $page->active_action);
			$page->sidebar .= $sidebar->get_markup();
		}

		// Online Administrators in the last 30 minutes
		$timecut = TIME_NOW-60*30;
		$query = $db->simple_select("adminsessions", "uid, ip, useragent", "lastactive > {$timecut}");
		$online_users = "<ul class=\"menu online_admins\">";
		$online_admins = array();

		// If there's only 1 user online, it has to be us.
		if($db->num_rows($query) == 1)
		{
			$user = $db->fetch_array($query);
			global $mybb;

			// Are we on a mobile device?
			// Stolen from http://stackoverflow.com/a/10989424
			$user_type = "desktop";
			if(is_mobile($user["useragent"]))
			{
				$user_type = "mobile";
			}

			$online_admins[$mybb->user['username']] = array(
				"uid" => $mybb->user['uid'],
				"username" => $mybb->user['username'],
				"ip" => $user["ip"],
				"type" => $user_type
			);
		}
		else
		{
			$uid_in = array();
			while($user = $db->fetch_array($query))
			{
				$uid_in[] = $user['uid'];

				$user_type = "desktop";
				if(is_mobile($user['useragent']))
				{
					$user_type = "mobile";
				}

				$online_admins[$user['uid']] = array(
					"ip" => $user['ip'],
					"type" => $user_type
				);
			}

			$query = $db->simple_select("users", "uid, username", "uid IN(".implode(',', $uid_in).")", array('order_by' => 'username'));
			while($user = $db->fetch_array($query))
			{
				$online_admins[$user['username']] = array(
					"uid" => $user['uid'],
					"username" => $user['username'],
					"ip" => $online_admins[$user['uid']]['ip'],
					"type" => $online_admins[$user['uid']]['type']
				);
				unset($online_admins[$user['uid']]);
			}
		}

		$done_users = array();

		asort($online_admins);

		foreach($online_admins as $user)
		{
			if(!isset($done_users["{$user['uid']}.{$user['ip']}"]))
			{
				if($user['type'] == "mobile")
				{
					$class = " class=\"mobile_user\"";
				}
				else
				{
					$class = "";
				}
				$ip_address = my_inet_ntop($db->unescape_binary($user['ip']));
				$online_users .= "<li title=\"{$lang->ipaddress} {$ip_address}\"{$class}>".build_profile_link(htmlspecialchars_uni($user['username']).' ('.$ip_address.')', $user['uid'], "_blank")."</li>";
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

