<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */


$page->add_breadcrumb_item("Admin Permissions", "index.php?".SID."&amp;module=user/admin_permissions");

if($mybb->input['action'] == "delete")
{
	$uid = $mybb->input['uid'];
	if(is_super_admin($uid) && $mybb->user['uid'] != $uid)
	{
		flash_message('Sorry, but you cannot perform this action on the specified user as they are a super administrator.<br /><br />To be able to perform this action, you need to add your user ID to the list of super administrators in inc/config.php.', 'error');
		admin_redirect("index.php?".SID."&module=user/admin_permissions");
	}
	
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=user/admin_permissions");
	}
	
	if(!trim($mybb->input['uid']))
	{
		flash_message('You did not enter a admin user/usergroup permission id', 'error');
		admin_redirect("index.php?".SID."&module=user/admin_permissions");
	}
	
	$query = $db->simple_select("adminoptions", "COUNT(uid) as adminoptions", "uid = '{$mybb->input['uid']}'");
	if($db->fetch_field($query, 'adminoptions') == 0)
	{
		flash_message('You did not enter a valid admin user/usergroup permission id', 'error');
		admin_redirect("index.php?".SID."&module=user/admin_permissions");
	}
	
	if($mybb->request_method == "post")
	{
		$newperms = array(
			"permsset" => 0
		);
		$db->update_query("adminoptions", $newperms, "uid = '{$mybb->input['uid']}'");
		flash_message('The admin user/usergroup permissions has successfully been revoked.', 'success');
		admin_redirect("index.php?".SID."&module=user/admin_permissions");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=user/admin_permissions&amp;action=delete&amp;uid={$mybb->input['uid']}", "Are you sure you wish to revoked this admin user/usergroup permissions?"); 
	}
}

if($mybb->input['action'] == "edit")
{
	if($mybb->request_method == "post")
	{
		$db->update_query("adminoptions", array('permsset' => $db->escape_string(serialize($mybb->input['permissions']))), "uid = '".intval($mybb->input['uid'])."'");
				
		flash_message("The admin permissions have been successfully updated.", 'success');
		admin_redirect("index.php?".SID."&module=user/admin_permissions");
	}
	
	$uid = intval($mybb->input['uid']);
	
	if($uid > 0)
	{
		$query = $db->query("
			SELECT u.uid, u.username, g.cancp, g.gid
			FROM ".TABLE_PREFIX."users u
			LEFT JOIN ".TABLE_PREFIX."usergroups g ON (u.usergroup=g.gid)
			WHERE u.uid='$uid'
			AND g.cancp='yes'
			LIMIT 1
		");
		$admin = $db->fetch_array($query);
		$permission_data = get_admin_permissions($uid, $admin['gid']);
		$title = $admin['username'];
		$page->add_breadcrumb_item("User Permissions");
	}
	elseif($uid < 0)
	{
		$gid = abs($uid);
		$query = $db->simple_select("usergroups", "title", "gid='$gid'");
		$group = $db->fetch_array($query);
		$permission_data = get_admin_permissions("", $gid);
		$title = $group['title'];
		$page->add_breadcrumb_item("Group Permissions");
	}
	else
	{
		$query = $db->simple_select("adminoptions", "permsset", "uid='0'");
		$permission_data = $db->fetch_array($query);
		$permission_data['permsset'] = unserialize($permission_data['permsset']);
		$page->add_breadcrumb_item("Default Permissions");
		$title = "Default";
	}
	$permission_data = $permission_data['permsset'];
	
	$page->add_breadcrumb_item("Edit Permissions: {$title}");
	
	$page->output_header("Edit Permissions");
	
	$sub_tabs['edit_permissions'] = array(
		'title' => "Edit Permissions",
		'link' => "index.php?".SID."&amp;module=user/admin_permissions&amp;action=edit&amp;uid={$uid}",
		'description' => "Here you can restrict access to entire tabs or individual pages. Be aware that the \"Home\" tab is accessible to all administrators."
	);

	$page->output_nav_tabs($sub_tabs, 'edit_permissions');
	
	$form = new Form("index.php?".SID."&amp;module=user/admin_permissions&amp;action=edit", "post", "edit");

	echo $form->generate_hidden_field("uid", $uid);

	$modules_dir = MYBB_ADMIN_DIR."modules";
	$dir = opendir($modules_dir);
	while(($module = readdir($dir)) !== false)
	{
		if(is_dir($modules_dir."/".$module) && !in_array($module, array(".", "..")) && file_exists($modules_dir."/".$module."/module_meta.php"))
		{
			require_once $modules_dir."/".$module."/module_meta.php";
			$meta_function = $module."_admin_permissions";
			if(function_exists($meta_function))
			{
				$permissions = $meta_function();
				if(is_array($permissions))
				{		
					$form_container = new FormContainer("Tab: {$permissions['name']}");
					$form_container->output_row("Can Access Tab? <em>*</em>", "If set to \"No,\" all other permissions for this tab are ignored.", $form->generate_yes_no_radio('permissions['.$module.'][tab]', intval($permission_data[$module]['tab']), array('yes' => 1, 'no' => 0)), 'permissions['.$module.'][tab]');
					
					foreach($permissions['permissions'] as $name => $title)
					{
						if($name == "tab")
						{
							continue;
						}
						$form_container->output_row("{$title} <em>*</em>", "", $form->generate_yes_no_radio('permissions['.$module.']['.$name.']', intval($permission_data[$module][$name]), array('yes' => 1, 'no' => 0)), 'permissions['.$module.']['.$name.']');
					}
					
					$form_container->end();
				}
			}
		}
	}
	closedir($dir);

	$buttons[] = $form->generate_submit_button("Update Permissions");
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == "group")
{
	$page->add_breadcrumb_item("Group Permissions");
	$page->output_header("Group Permissions");
	
	$sub_tabs['group_permissions'] = array(
		'title' => "Group Permissions",
		'link' => "index.php?".SID."&amp;module=user/admin_permissions&amp;action=group",
		'description' => "Here you can manage admin permissions for each administrator user group."
	);

	$page->output_nav_tabs($sub_tabs, 'group_permissions');

	$table = new Table;
	$table->construct_header("Group");
	$table->construct_header("Controls", array("class" => "align_center", "width" => 150));
	
	// Get usergroups with ACP access
	$query = $db->query("
		SELECT g.title, g.cancp, a.permsset, g.gid
		FROM ".TABLE_PREFIX."usergroups g
		LEFT JOIN ".TABLE_PREFIX."adminoptions a ON (a.uid = -g.gid)
		WHERE g.cancp = 'yes'
		ORDER BY g.title ASC
	");
	while($group = $db->fetch_array($query))
	{
		if($admin['permsset'])
		{
			$perm_type = "group";
		}
		else
		{
			$perm_type = "default";
		}
		
		$uid = -$group['gid'];
		
		$table->construct_cell("<div class=\"float_right\"><img src=\"styles/{$page->style}/images/icons/{$perm_type}.gif\" title=\"Permission type of the group\" alt=\"{$perm_type}\" /></div><div><strong><a href=\"index.php?".SID."&amp;module=users/groups&amp;action=edit&amp;gid={$group['gid']}\" title=\"Edit Group\">{$group['title']}</a></strong><br /></div>");
		
		
		if($group['permsset'])
		{
			$popup = new PopupMenu("groupperm_{$uid}", "Options");
			$popup->add_item("Edit Permissions", "index.php?".SID."&amp;module=user/admin_permissions&amp;action=edit&amp;uid={$uid}");
			
			// Check permissions for Revoke
			$popup->add_item("Revoke Permissions", "index.php?".SID."&amp;module=user/admin_permissions&amp;action=delete&amp;uid={$uid}", "return AdminCP.deleteConfirmation(this, 'Are you sure you wish to revolke this group\'s permissions?')");
			$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		}
		else
		{
			$table->construct_cell("<a href=\"index.php?".SID."&amp;module=user/admin_permissions&amp;action=edit&amp;uid={$uid}\">Set Permissions</a>", array("class" => "align_center"));
		}
		
		$table->construct_row();
	}
		
	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are currently no set group permissions.", array("colspan" => "2"));
		$table->construct_row();
	}
	
	$table->output("Group Permissions");
	
	echo <<<LEGEND
<br />
<fieldset>
<legend>Legend</legend>
<img src="styles/{$page->style}/images/icons/group.gif" alt="Using Custom Permissions" style="vertical-align: middle;" /> Using Custom Permissions<br />
<img src="styles/{$page->style}/images/icons/default.gif" alt="Using Default Permissions" style="vertical-align: middle;" /> Using Default Permissions</fieldset>
LEGEND;
	
	$page->output_footer();
}

if(!$mybb->input['action'])
{	
	$page->add_breadcrumb_item("User Permissions");
	$page->output_header("User Permissions");
	
	$sub_tabs['user_permissions'] = array(
		'title' => "User Permissions",
		'link' => "index.php?".SID."&amp;module=user/admin_permissions",
		'description' => "Here you can manage admin permissions for individual users."
	);

	$sub_tabs['group_permissions'] = array(
		'title' => "Group Permissions",
		'link' => "index.php?".SID."&amp;module=user/admin_permissions&amp;action=group"
	);

	$sub_tabs['default_permissions'] = array(
		'title' => "Default Permissions",
		'link' => "index.php?".SID."&amp;module=user/admin_permissions&amp;action=edit&amp;uid=0"
	);

	$page->output_nav_tabs($sub_tabs, 'user_permissions');

	$table = new Table;
	$table->construct_header("User");
	$table->construct_header("Last Active", array("class" => "align_center", "width" => 200));
	$table->construct_header("Controls", array("class" => "align_center", "width" => 150));
	
	// Get usergroups with ACP access
	$usergroups = array();
	$query = $db->simple_select("usergroups", "*", "cancp = 'yes'");
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
			case "sqlite3":
			case "sqlite2":
				$secondary_group_list .= " OR ','|| u.additionalgroups||',' LIKE '%,{$gid},%'";
				break;
			default:
				$secondary_group_list .= " OR CONCAT(',', u.additionalgroups,',') LIKE '%,{$gid},%'";
		}
		
		$comma = ',';
	}
	
	$group_list = implode(',', array_keys($usergroups));
	$secondary_groups = ','.$group_list.',';
	$query = $db->query("
		SELECT u.uid, u.username, u.lastactive, u.usergroup, u.additionalgroups, a.permsset
		FROM ".TABLE_PREFIX."users u
		LEFT JOIN ".TABLE_PREFIX."adminoptions a ON (a.uid=u.uid)
		WHERE u.usergroup IN ({$primary_group_list}) {$secondary_group_list}
		ORDER BY u.username ASC
	");
	while($admin = $db->fetch_array($query))
	{
		if($admin['permsset'])
		{
			$perm_type = "user";
		}
		else
		{
			$groups = explode(",", $admin['additionalgroups'].",".$admin['usergroup']);
			foreach($groups as $group)
			{
				if($group == "") continue;
				if($group_permissions[$group])
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
		if($usergroups[$admin['usergroup']]['cancp'] == "yes")
		{
			$usergroup_list[] = "<i>".$usergroups[$admin['usergroup']]['title']."</i>";
		}
		
		// Secondary usergroups?
		$additional_groups = explode(',', $admin['additionalgroups']);
		if(is_array($additional_groups))
		{
			foreach($additional_groups as $gid)
			{
				if($usergroups[$gid]['cancp'] == "yes")
				{
					$usergroup_list[] = $usergroups[$gid]['title'];
				}
			}
		}
		$usergroup_list = implode(", ", $usergroup_list);
		
		$table->construct_cell("<div class=\"float_right\"><img src=\"styles/{$page->style}/images/icons/{$perm_type}.gif\" title=\"Permission type of the user\" alt=\"{$perm_type}\" /></div><div><strong><a href=\"index.php?".SID."&amp;module=users/view&amp;action=edit&amp;uid={$admin['uid']}\" title=\"Edit User Profile\">{$admin['username']}</a></strong><br /><small>{$usergroup_list}</small></div>");
		
		$table->construct_cell(my_date($mybb->settings['dateformat'].", ".$mybb->settings['timeformat'], $admin['lastactive']), array("class" => "align_center"));
		
		$popup = new PopupMenu("adminperm_{$admin['uid']}", "Options");
		if($admin['permsset'])
		{
			$popup->add_item("Edit Permissions", "index.php?".SID."&amp;module=user/admin_permissions&amp;action=edit&amp;uid={$admin['uid']}");
			
			// Check permissions for Revoke
			$popup->add_item("Revoke Permissions", "index.php?".SID."&amp;module=user/admin_permissions&amp;action=delete&amp;uid={$admin['uid']}", "return AdminCP.deleteConfirmation(this, 'Are you sure you wish to revolke this user\'s permissions?')");
		}
		else
		{
			$popup->add_item("Set Permissions", "index.php?".SID."&amp;module=user/admin_permissions&amp;action=edit&amp;uid={$admin['uid']}");
		}
		$popup->add_item("View Log", "index.php?".SID."&amp;module=user/stats_and_logging&amp;uid={$admin['uid']}");
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();
	}
		
	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are currently no set user permissions.", array("colspan" => "2"));
		$table->construct_row();
	}
	
	$table->output("User Permissions");
	
	echo <<<LEGEND
<br />
<fieldset>
<legend>Legend</legend>
<img src="styles/{$page->style}/images/icons/user.gif" alt="Using Individual Permissions" style="vertical-align: middle;" /> Using Individual Permissions<br />
<img src="styles/{$page->style}/images/icons/group.gif" alt="Using Group Permissions" style="vertical-align: middle;" /> Using Group Permissions<br />
<img src="styles/{$page->style}/images/icons/default.gif" alt="Using Default Permissions" style="vertical-align: middle;" /> Using Default Permissions</fieldset>
LEGEND;
	$page->output_footer();
}

?>