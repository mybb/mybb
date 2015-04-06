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

$page->add_breadcrumb_item($lang->admin_permissions, "index.php?module=user-admin_permissions");

if(($mybb->input['action'] == "edit" && $mybb->input['uid'] == 0) || $mybb->input['action'] == "group" || !$mybb->input['action'])
{
	$sub_tabs['user_permissions'] = array(
		'title' => $lang->user_permissions,
		'link' => "index.php?module=user-admin_permissions",
		'description' => $lang->user_permissions_desc
	);

	$sub_tabs['group_permissions'] = array(
		'title' => $lang->group_permissions,
		'link' => "index.php?module=user-admin_permissions&amp;action=group",
		'description' => $lang->group_permissions_desc
	);

	$sub_tabs['default_permissions'] = array(
		'title' => $lang->default_permissions,
		'link' => "index.php?module=user-admin_permissions&amp;action=edit&amp;uid=0",
		'description' => $lang->default_permissions_desc
	);
}

$uid = $mybb->get_input('uid', MyBB::INPUT_INT);

$plugins->run_hooks("admin_user_admin_permissions_begin");

if($mybb->input['action'] == "delete")
{
	if(is_super_admin($uid))
	{
		flash_message($lang->error_super_admin, 'error');
		admin_redirect("index.php?module=user-admin_permissions");
	}

	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=user-admin_permissions");
	}

	if(!trim($mybb->input['uid']))
	{
		flash_message($lang->error_delete_no_uid, 'error');
		admin_redirect("index.php?module=user-admin_permissions");
	}

	$query = $db->simple_select("adminoptions", "COUNT(uid) as adminoptions", "uid = '{$mybb->input['uid']}'");
	if($db->fetch_field($query, 'adminoptions') == 0)
	{
		flash_message($lang->error_delete_invalid_uid, 'error');
		admin_redirect("index.php?module=user-admin_permissions");
	}

	$plugins->run_hooks("admin_user_admin_permissions_delete");

	if($mybb->request_method == "post")
	{
		$newperms = array(
			"permissions" => ''
		);

		$plugins->run_hooks("admin_user_admin_permissions_delete_commit");

		$db->update_query("adminoptions", $newperms, "uid = '{$uid}'");

		// Log admin action
		if($uid < 0)
		{
			$gid = abs($uid);
			$query = $db->simple_select("usergroups", "title", "gid='{$gid}'");
			$group = $db->fetch_array($query);
			log_admin_action($uid, $group['title']);

		}
		elseif($uid == 0)
		{
			// Default
			log_admin_action(0, $lang->default);
		}
		else
		{
			$user = get_user($uid);
			log_admin_action($uid, $user['username']);
		}

		flash_message($lang->success_perms_deleted, 'success');
		admin_redirect("index.php?module=user-admin_permissions");
	}
	else
	{
		$page->output_confirm_action("index.php?module=user-admin_permissions&amp;action=delete&amp;uid={$mybb->input['uid']}", $lang->confirm_perms_deletion);
	}
}

if($mybb->input['action'] == "edit")
{
	if(is_super_admin($uid))
	{
		flash_message($lang->error_super_admin, 'error');
		admin_redirect("index.php?module=user-admin_permissions");
	}

	$plugins->run_hooks("admin_user_admin_permissions_edit");

	if($mybb->request_method == "post")
	{
		foreach($mybb->input['permissions'] as $module => $actions)
		{
			$no_access = 0;
			foreach($actions as $action => $access)
			{
				if($access == 0)
				{
					++$no_access;
				}
			}
			// User can't access any actions in this module - just disallow it completely
			if($no_access == count($actions))
			{
				unset($mybb->input['permissions'][$module]);
			}
		}

		// Does an options row exist for this admin already?
		$query = $db->simple_select("adminoptions", "COUNT(uid) AS existing_options", "uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'");
		$existing_options = $db->fetch_field($query, "existing_options");
		if($existing_options > 0)
		{
			$db->update_query("adminoptions", array('permissions' => $db->escape_string(my_serialize($mybb->input['permissions']))), "uid = '".$mybb->get_input('uid', MyBB::INPUT_INT)."'");
		}
		else
		{
			$insert_array = array(
				"uid" => $mybb->get_input('uid', MyBB::INPUT_INT),
				"permissions" => $db->escape_string(my_serialize($mybb->input['permissions'])),
				"notes" => '',
				"defaultviews" => ''
			);
			$db->insert_query("adminoptions", $insert_array);
		}

		$plugins->run_hooks("admin_user_admin_permissions_edit_commit");

		// Log admin action
		if($uid > 0)
		{
			// Users
			$user = get_user($uid);
			log_admin_action($uid, $user['username']);
		}
		elseif($uid < 0)
		{
			// Groups
			$gid = abs($uid);
			$query = $db->simple_select("usergroups", "title", "gid='{$gid}'");
			$group = $db->fetch_array($query);
			log_admin_action($uid, $group['title']);
		}
		else
		{
			// Default
			log_admin_action(0);
		}

		flash_message($lang->admin_permissions_updated, 'success');
		admin_redirect("index.php?module=user-admin_permissions");
	}

	if($uid > 0)
	{
		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$query = $db->query("
					SELECT u.uid, u.username, g.cancp, g.gid
					FROM ".TABLE_PREFIX."users u
					LEFT JOIN ".TABLE_PREFIX."usergroups g ON (((','|| u.additionalgroups|| ',' LIKE '%,'|| g.gid|| ',%') OR u.usergroup = g.gid))
					WHERE u.uid='$uid'
					AND g.cancp=1
					LIMIT 1
				");
				break;
			default:
			$query = $db->query("
				SELECT u.uid, u.username, g.cancp, g.gid
				FROM ".TABLE_PREFIX."users u
				LEFT JOIN ".TABLE_PREFIX."usergroups g ON (((CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%')) OR u.usergroup = g.gid))
				WHERE u.uid='$uid'
				AND g.cancp=1
				LIMIT 1
			");
		}

		$admin = $db->fetch_array($query);
		$permission_data = get_admin_permissions($uid, $admin['gid']);
		$title = $admin['username'];
		$page->add_breadcrumb_item($lang->user_permissions, "index.php?module=user-admin_permissions");
	}
	elseif($uid < 0)
	{
		$gid = abs($uid);
		$query = $db->simple_select("usergroups", "title", "gid='$gid'");
		$group = $db->fetch_array($query);
		$permission_data = get_admin_permissions("", $gid);
		$title = $group['title'];
		$page->add_breadcrumb_item($lang->group_permissions, "index.php?module=user-admin_permissions&amp;action=group");
	}
	else
	{
		$query = $db->simple_select("adminoptions", "permissions", "uid='0'");
		$permission_data = my_unserialize($db->fetch_field($query, "permissions"));
		$page->add_breadcrumb_item($lang->default_permissions);
		$title = $lang->default;
	}

	if($uid != 0)
	{
		$page->add_breadcrumb_item($lang->edit_permissions.": {$title}");
	}

	$page->output_header($lang->edit_permissions);

	if($uid != 0)
	{
		$sub_tabs['edit_permissions'] = array(
			'title' => $lang->edit_permissions,
			'link' => "index.php?module=user-admin_permissions&amp;action=edit&amp;uid={$uid}",
			'description' => $lang->edit_permissions_desc
		);

		$page->output_nav_tabs($sub_tabs, 'edit_permissions');
	}

	$form = new Form("index.php?module=user-admin_permissions&amp;action=edit", "post", "edit");

	echo $form->generate_hidden_field("uid", $uid);

	// Fetch all of the modules we have
	$modules_dir = MYBB_ADMIN_DIR."modules";
	$dir = opendir($modules_dir);
	$modules = array();
	while(($module = readdir($dir)) !== false)
	{
		if(is_dir($modules_dir."/".$module) && !in_array($module, array(".", "..")) && file_exists($modules_dir."/".$module."/module_meta.php"))
		{
			require_once $modules_dir."/".$module."/module_meta.php";
			$meta_function = $module."_admin_permissions";

			// Module has no permissions, skip it
			if(function_exists($meta_function) && is_array($meta_function()))
			{
				$permission_modules[$module] = $meta_function();
				$modules[$permission_modules[$module]['disporder']][] = $module;
			}
		}
	}
	closedir($dir);

	ksort($modules);
	foreach($modules as $disp_order => $mod)
	{
		if(!is_array($mod))
		{
			continue;
		}

		foreach($mod as $module)
		{
			$module_tabs[$module] = $permission_modules[$module]['name'];
		}
	}
	$page->output_tab_control($module_tabs);

	foreach($permission_modules as $key => $module)
	{
		echo "<div id=\"tab_{$key}\">\n";
		$form_container = new FormContainer("{$module['name']}");
		foreach($module['permissions'] as $action => $title)
		{
			$form_container->output_row($title, "", $form->generate_yes_no_radio('permissions['.$key.']['.$action.']', (int)$permission_data[$key][$action], array('yes' => 1, 'no' => 0)), 'permissions['.$key.']['.$action.']');
		}
		$form_container->end();
		echo "</div>\n";
	}

	$buttons[] = $form->generate_submit_button($lang->update_permissions);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "group")
{
	$plugins->run_hooks("admin_user_admin_permissions_group");

	$page->add_breadcrumb_item($lang->group_permissions);
	$page->output_header($lang->group_permissions);

	$page->output_nav_tabs($sub_tabs, 'group_permissions');

	$table = new Table;
	$table->construct_header($lang->group);
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

	// Get usergroups with ACP access
	$query = $db->query("
		SELECT g.title, g.cancp, a.permissions, g.gid
		FROM ".TABLE_PREFIX."usergroups g
		LEFT JOIN ".TABLE_PREFIX."adminoptions a ON (a.uid = -g.gid)
		WHERE g.cancp = 1
		ORDER BY g.title ASC
	");
	while($group = $db->fetch_array($query))
	{
		if($group['permissions'] != "")
		{
			$perm_type = "group";
		}
		else
		{
			$perm_type = "default";
		}
		$uid = -$group['gid'];
		$table->construct_cell("<div class=\"float_right\"><img src=\"styles/{$page->style}/images/icons/{$perm_type}.png\" title=\"{$lang->permissions_type_group}\" alt=\"{$perm_type}\" /></div><div><strong><a href=\"index.php?module=user-admin_permissions&amp;action=edit&amp;uid={$uid}\" title=\"{$lang->edit_group}\">{$group['title']}</a></strong><br /></div>");

		if($group['permissions'] != "")
		{
			$popup = new PopupMenu("groupperm_{$uid}", $lang->options);
			$popup->add_item($lang->edit_permissions, "index.php?module=user-admin_permissions&amp;action=edit&amp;uid={$uid}");

			// Check permissions for Revoke
			$popup->add_item($lang->revoke_permissions, "index.php?module=user-admin_permissions&amp;action=delete&amp;uid={$uid}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, 'Are you sure you wish to revoke this group\'s permissions?')");
			$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		}
		else
		{
			$table->construct_cell("<a href=\"index.php?module=user-admin_permissions&amp;action=edit&amp;uid={$uid}\">{$lang->set_permissions}</a>", array("class" => "align_center"));
		}
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_group_perms, array("colspan" => "3"));
		$table->construct_row();
	}

	$table->output($lang->group_permissions);

	echo <<<LEGEND
<br />
<fieldset>
<legend>{$lang->legend}</legend>
<img src="styles/{$page->style}/images/icons/group.png" alt="{$lang->using_custom_perms}" style="vertical-align: middle;" /> {$lang->using_custom_perms}<br />
<img src="styles/{$page->style}/images/icons/default.png" alt="{$lang->using_default_perms}" style="vertical-align: middle;" /> {$lang->using_default_perms}</fieldset>
LEGEND;

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_user_admin_permissions_start");

	$page->add_breadcrumb_item($lang->user_permissions);
	$page->output_header($lang->user_permissions);

	$page->output_nav_tabs($sub_tabs, 'user_permissions');

	$table = new Table;
	$table->construct_header($lang->user);
	$table->construct_header($lang->last_active, array("class" => "align_center", "width" => 200));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

	// Get usergroups with ACP access
	$usergroups = array();
	$query = $db->simple_select("usergroups", "*", "cancp = 1");
	while($usergroup = $db->fetch_array($query))
	{
		$usergroups[$usergroup['gid']] = $usergroup;
	}

	// Get users whose primary or secondary usergroup has ACP access
	$comma = $primary_group_list = $secondary_group_list = '';
	foreach($usergroups as $gid => $group_info)
	{
		$primary_group_list .= $comma.$gid;
		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$secondary_group_list .= " OR ','|| u.additionalgroups||',' LIKE '%,{$gid},%'";
				break;
			default:
				$secondary_group_list .= " OR CONCAT(',', u.additionalgroups,',') LIKE '%,{$gid},%'";
		}

		$comma = ',';
	}

	$group_list = implode(',', array_keys($usergroups));
	$secondary_groups = ','.$group_list.',';

	// Get usergroups with ACP access
	$query = $db->query("
		SELECT g.title, g.cancp, a.permissions, g.gid
		FROM ".TABLE_PREFIX."usergroups g
		LEFT JOIN ".TABLE_PREFIX."adminoptions a ON (a.uid = -g.gid)
		WHERE g.cancp = 1
		ORDER BY g.title ASC
	");
	while($group = $db->fetch_array($query))
	{
		$group_permissions[$group['gid']] = $group['permissions'];
	}

	$query = $db->query("
		SELECT u.uid, u.username, u.lastactive, u.usergroup, u.additionalgroups, a.permissions
		FROM ".TABLE_PREFIX."users u
		LEFT JOIN ".TABLE_PREFIX."adminoptions a ON (a.uid=u.uid)
		WHERE u.usergroup IN ({$primary_group_list}) {$secondary_group_list}
		ORDER BY u.username ASC
	");
	while($admin = $db->fetch_array($query))
	{
		if($admin['permissions'] != "")
		{
			$perm_type = "user";
		}
		else
		{
			$groups = explode(",", $admin['additionalgroups'].",".$admin['usergroup']);
			foreach($groups as $group)
			{
				if($group == "") continue;
				if($group_permissions[$group] != "")
				{
					$perm_type = "group";
					break;
				}
			}

			if(!$group_permissions)
			{
				$perm_type = "default";
			}
		}

		$usergroup_list = array();

		// Build a list of group memberships that have access to the Admin CP
		// Primary usergroup?
		if($usergroups[$admin['usergroup']]['cancp'] == 1)
		{
			$usergroup_list[] = "<i>".$usergroups[$admin['usergroup']]['title']."</i>";
		}

		// Secondary usergroups?
		$additional_groups = explode(',', $admin['additionalgroups']);
		if(is_array($additional_groups))
		{
			foreach($additional_groups as $gid)
			{
				if($usergroups[$gid]['cancp'] == 1)
				{
					$usergroup_list[] = $usergroups[$gid]['title'];
				}
			}
		}
		$usergroup_list = implode($lang->comma, $usergroup_list);

		$table->construct_cell("<div class=\"float_right\"><img src=\"styles/{$page->style}/images/icons/{$perm_type}.png\" title=\"{$lang->perms_type_user}\" alt=\"{$perm_type}\" /></div><div><strong><a href=\"index.php?module=user-admin_permissions&amp;action=edit&amp;uid={$admin['uid']}\" title=\"{$lang->edit_user}\">{$admin['username']}</a></strong><br /><small>{$usergroup_list}</small></div>");

		$table->construct_cell(my_date('relative', $admin['lastactive']), array("class" => "align_center"));

		$popup = new PopupMenu("adminperm_{$admin['uid']}", $lang->options);
		if(!is_super_admin($admin['uid']))
		{
			if($admin['permissions'] != "")
			{
				$popup->add_item($lang->edit_permissions, "index.php?module=user-admin_permissions&amp;action=edit&amp;uid={$admin['uid']}");
				$popup->add_item($lang->revoke_permissions, "index.php?module=user-admin_permissions&amp;action=delete&amp;uid={$admin['uid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_perms_deletion2}')");
			}
			else
			{
				$popup->add_item($lang->set_permissions, "index.php?module=user-admin_permissions&amp;action=edit&amp;uid={$admin['uid']}");
			}
		}
		$popup->add_item($lang->view_log, "index.php?module=tools-adminlog&amp;uid={$admin['uid']}");
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_user_perms, array("colspan" => "3"));
		$table->construct_row();
	}

	$table->output($lang->user_permissions);

	echo <<<LEGEND
<br />
<fieldset>
<legend>{$lang->legend}</legend>
<img src="styles/{$page->style}/images/icons/user.png" alt="{$lang->using_individual_perms}" style="vertical-align: middle;" /> {$lang->using_individual_perms}<br />
<img src="styles/{$page->style}/images/icons/group.png" alt="{$lang->using_group_perms}" style="vertical-align: middle;" /> {$lang->using_group_perms}<br />
<img src="styles/{$page->style}/images/icons/default.png" alt="{$lang->using_default_perms}" style="vertical-align: middle;" /> {$lang->using_default_perms}</fieldset>
LEGEND;
	$page->output_footer();
}

