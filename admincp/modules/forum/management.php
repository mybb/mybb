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

$page->add_breadcrumb_item($lang->forum_management, "index.php?".SID."&amp;module=forum/management");

if($mybb->input['action'] == "add" || $mybb->input['action'] == "copy" || $mybb->input['action'] == "permissions" || !$mybb->input['action'])
{
	if($mybb->input['fid'] && ($mybb->input['action'] == "management" || !$mybb->input['action']))
	{
		$sub_tabs['view_forum'] = array(
			'title' => $lang->view_forum,
			'link' => "index.php?".SID."&amp;module=forum/management&amp;fid=".$mybb->input['fid'],
			'description' => $lang->view_forum_desc
		);
	
		$sub_tabs['add_child_forum'] = array(
			'title' => $lang->add_child_forum,
			'link' => "index.php?".SID."&amp;module=forum/management&amp;action=add&amp;fid=".$mybb->input['fid'],
			'description' => $lang->add_child_forum_desc
		);
		
		$sub_tabs['edit_forum_settings'] = array(
			'title' => $lang->edit_forum_settings,
			'link' => "index.php?".SID."&amp;module=forum/management&amp;action=edit&amp;fid=".$mybb->input['fid'],
			'description' => $lang->edit_forum_settings_desc
		);
	
		$sub_tabs['copy_forum'] = array(
			'title' => $lang->copy_forum,
			'link' => "index.php?".SID."&amp;module=forum/management&amp;action=copy&amp;fid=".$mybb->input['fid'],
			'description' => $lang->copy_forum_desc
		);
	}
	else
	{
		$sub_tabs['forum_management'] = array(
			'title' => $lang->forum_management,
			'link' => "index.php?".SID."&amp;module=forum/management",
			'description' => $lang->forum_management_desc
		);
	
		$sub_tabs['add_forum'] = array(
			'title' => $lang->add_forum,
			'link' => "index.php?".SID."&amp;module=forum/management&amp;action=add",
			'description' => $lang->add_forum_desc
		);
	}
}

if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
	}
	
	$page->output_header($lang->add_forum);	
	$page->output_nav_tabs($sub_tabs, 'add_forum');
	
	$form = new Form("index.php?".SID."&amp;module=forum/management&amp;action=add", "post");

	if($errors)
	{
		$page->output_inline_error($errors);
		$forum_data = $mybb->input;
	}
	else
	{
		$forum_data['type'] = "f";
		$forum_data['title'] = "";
		$forum_data['description'] = "";
		$forum_data['pid'] = "-1";
		$forum_data['disporder'] = "";
		$forum_data['linkto'] = "";
		$forum_data['password'] = "";
	}
	
	$types = array(
		'f' => $lang->forum,
		'c' => $lang->category
	);
	
	$create_a_options_f = array(
		'id' => 'type'
	);
	
	$create_a_options_c = array(
		'id' => 'type'
	);
	
	if($forum_data['type'] == "f")
	{
		$create_a_options_f['checked'] = true;
	}
	else
	{
		$create_a_options_c['checked'] = true;
	}

	$form_container = new FormContainer($lang->add_forum);
	$form_container->output_row($lang->create_a, $lang->create_a_desc, $form->generate_radio_button('type', 'f', $lang->forum, $create_a_options_f)."<br />\n".$form->generate_radio_button('type', 'c', $lang->category, $create_a_options_c), 'type');
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $forum_data['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_area('description', $forum_data['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->parent_forum." <em>*</em>", $lang->parent_forum_desc, $form->generate_forum_select('pid', $forum_data['pid'], array('id' => 'pid', 'main_option' => $lang->none)), 'pid');
	$form_container->output_row($lang->display_order." <em>*</em>", "", $form->generate_text_box('disporder', $forum_data['disporder'], array('id' => 'disporder')), 'disporder');
	$form_container->end();
	
	$form_container = new FormContainer($lang->additional_forum_options);
	$form_container->output_row($lang->forum_link, $lang->forum_link_desc, $form->generate_text_box('linkto', $lang->forum, array('id' => 'linkto')), 'linkto');
	$form_container->output_row($lang->forum_password, $lang->forum_password_desc, $form->generate_text_box('password', $forum_data['password'], array('id' => 'password')), 'password');
	$form_container->output_row($lang->access_options, "", $form->generate_check_box('active', $forum_data['active'], $lang->forum_is_active."<br />\n<span class=\"smalltext\">{$lang->forum_is_active_desc}</span>", array('id' => 'active'))."<br />\n".$form->generate_check_box('open', $forum_data['open'], $lang->forum_is_open."<br />\n<span class=\"smalltext\">\t{$lang->forum_is_open_desc}</span>", array('id' => 'open')));
	$form_container->output_row($lang->parent_forum, $lang->parent_forum_desc, $form->generate_forum_select('pid', $forum_data['pid'], array('id' => 'pid', 'main_option' => $lang->none)), 'pid');
	$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $forum_data['disporder'], array('id' => 'disporder')), 'disporder');
	$form_container->end();

	$query = $db->simple_select("usergroups", "*", "", array("order_dir" => "name"));
	while($usergroup = $db->fetch_array($query))
	{
		$usergroups[$usergroup['gid']] = $usergroup;
	}
	
	$field_list = array('canview','canpostthreads','canpostreplys','canpostpolls','candlattachments');
	
	$form_container = new FormContainer($lang->forum_permissions);
	$form_container->output_row_header($lang->permissions_group);
	$form_container->output_row_header($lang->permissions_canview, array("class" => "align_center", "width" => "10%"));
	$form_container->output_row_header($lang->permissions_canpostthreads, array("class" => "align_center", "width" => "10%"));
	$form_container->output_row_header($lang->permissions_canpostreplys, array("class" => "align_center", "width" => "10%"));
	$form_container->output_row_header($lang->permissions_canpostpolls, array("class" => "align_center", "width" => "10%"));
	$form_container->output_row_header($lang->permissions_candlattachments, array("class" => "align_center", "width" => "11%"));
	$form_container->output_row_header($lang->permissions_all, array("class" => "align_center", "width" => "10%"));
	foreach($usergroups as $usergroup)
	{
		$perms = $usergroup;
		$default_checked = true;
		
		$perm_check = "";
		$all_checked = true;
		foreach($field_list as $forum_permission)
		{
			if($usergroup[$forum_permission] == 1)
			{
				$value = "true";
			}
			else
			{
				$value = "false";
			}
			
			if($mybb->input['permissions'][$usergroup['gid']][$forum_permission])
			{
				$value = $mybb->input['permissions'][$usergroup['gid']][$forum_permission];
			}
			
			if($perms[$forum_permission] != 1)
			{
				$all_checked = false;
			}
			if($perms[$forum_permission] == 1)
			{
				$perms_checked[$forum_permission] = 1;
			}
			else
			{
				$perms_checked[$forum_permission] = 0;
			}
			$all_check .= "\$('permissions_{$usergroup['gid']}_{$forum_permission}').checked = \$('permissions_{$usergroup['gid']}_all').checked;\n";
			$perm_check .= "\$('permissions_{$usergroup['gid']}_{$forum_permission}').checked = $value;\n";
		}
		$default_click = "if(this.checked == true) { $perm_check }";
		$reset_default = "\$('default_permissions_{$usergroup['gid']}').checked = false; if(this.checked == false) { \$('permissions_{$usergroup['gid']}_all').checked = false; }\n";
		$usergroup['title'] = htmlspecialchars_uni($usergroup['title']);
		$form_container->output_cell("<strong>{$usergroup['title']}</strong><br /><small style=\"vertical-align: middle;\">".$form->generate_check_box("default_permissions[{$usergroup['gid']}];", 1, "", array("id" => "default_permissions_{$usergroup['gid']}", "checked" => $default_checked, "onclick" => $default_click))." <label for=\"default_permissions_{$usergroup['gid']}\">{$lang->permissions_use_group_default}</label></small>");
		foreach($field_list as $forum_permission)
		{
			$form_container->output_cell($form->generate_check_box("permissions[{$usergroup['gid']}][{$forum_permission}]", 1, "", array("id" => "permissions_{$usergroup['gid']}_{$forum_permission}", "checked" => $perms_checked[$forum_permission], "onclick" => $reset_default)), array('class' => 'align_center'));
		}
		$form_container->output_cell($form->generate_check_box("permissions[{$usergroup['gid']}][all]", 1, "", array("id" => "permissions_{$usergroup['gid']}_all", "checked" => $all_checked, "onclick" => $all_check)), array('class' => 'align_center'));
		$form_container->construct_row();
	}
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->save_forum);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();	
}

if($mybb->input['action'] == "edit")
{
}

if($mybb->input['action'] == "deletemod")
{
	$query = $db->simple_select("moderators", "*", "uid='{$mybb->input['uid']}' AND fid='{$mybb->input['fid']}'");
	$mod = $db->fetch_array($query);
	
	// Does the forum not exist?
	if(!$mod['mid'])
	{
		flash_message($lang->error_invalid_moderator, 'error');
		admin_redirect("index.php?".SID."&module=forum/management&fid=".$mybb->input['fid']);
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=forum/management&fid=".$mybb->input['fid']);
	}
	
	if($mybb->request_method == "post")
	{
		$mid = $mod['mid'];
		$query = $db->query("
			SELECT m.*, u.usergroup
			FROM ".TABLE_PREFIX."moderators m 
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=m.uid)
			WHERE m.mid='{$mid}'
		");
		$mod = $db->fetch_array($query);
		
		$db->delete_query("moderators", "mid='{$mid}'");
		$query = $db->simple_select("moderators", "*", "uid='{$mod['uid']}'");
		if($db->fetch_array($query))
		{
			$updatequery = array(
				"usergroup" => "2"
			);
			$db->update_query("users", $updatequery, "uid='{$mod['uid']}' AND usergroup != '4' AND usergroup != '3'");
		}
		$cache->update_moderators();
		flash_message($lang->success_moderator_deleted, 'success');
		admin_redirect("index.php?".SID."&module=forum/management&fid=".$mybb->input['fid']);
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=forum/management&amp;action=deletemod&amp;fid={$mod['fid']}&amp;uid={$mod['uid']}", $lang->confirm_moderator_deletion);
	}
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("forums", "*", "fid='{$mybb->input['fid']}'");
	$forum = $db->fetch_array($query);
	
	// Does the forum not exist?
	if(!$forum['fid'])
	{
		flash_message($lang->error_invalid_forum, 'error');
		admin_redirect("index.php?".SID."&module=forum/management");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=forum/management");
	}

	if($mybb->request_method == "post")
	{
		$fid = $mybb->input['fid'];
		// Delete the forum
		$db->delete_query("forums", "fid='$fid'");
		switch($db->type)
		{
			case "sqlite3":
			case "sqlite2":
				$query = $db->simple_select("forums", "*", "','|| parentlist|| ',' LIKE '%,$fid,%'");
				break;
			default:
				$query = $db->simple_select("forums", "*", "CONCAT(',', parentlist, ',') LIKE '%,$fid,%'");
		}		
		while($forum = $db->fetch_array($query))
		{
			$fids[$forum['fid']] = $fid;
			$delquery .= " OR fid='{$forum['fid']}'";
		}

		/**
		 * This slab of code pulls out the moderators for this forum,
		 * checks if they moderate any other forums, and if they don't
		 * it moves them back to the registered usergroup
		 */

		$query = $db->simple_select("moderators", "*", "fid='$fid'");
		while($mod = $db->fetch_array($query))
		{
			$moderators[$mod['uid']] = $mod['uid'];
		}
		
		if(is_array($moderators))
		{
			$mod_list = implode(",", $moderators);
			$query = $db->simple_select("moderators", "*", "fid != '$fid' AND uid IN ($mod_list)");
			while($mod = $db->fetch_array($query))
			{
				unset($moderators[$mod['uid']]);
			}
		}
		
		if(is_array($moderators))
		{
			$mod_list = implode(",", $moderators);
			if($mod_list)
			{
				$updatequery = array(
					"usergroup" => "2"
				);
				$db->update_query("users", $updatequery, "uid IN ($mod_list) AND usergroup='6'");
			}
		}
		
		switch($db->type)
		{
			case "sqlite3":
			case "sqlite2":
				$db->delete_query("forums", "','||parentlist||',' LIKE '%,$fid,%'");
				break;
			default:
				$db->delete_query("forums", "CONCAT(',',parentlist,',') LIKE '%,$fid,%'");
		}
		
		$db->delete_query("threads", "fid='{$fid}' {$delquery}");
		$db->delete_query("posts", "fid='{$fid}' {$delquery}");
		$db->delete_query("moderators", "fid='{$fid}' {$delquery}");

		$cache->update_forums();
		$cache->update_moderators();
		$cache->update_forumpermissions();
		
		// Log admin action
		log_admin_action($forum['name']);

		flash_message($lang->success_forum_deleted, 'success');
		admin_redirect("index.php?".SID."&module=forum/management");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=forum/management&amp;action=delete&amp;fid={$forum['fid']}", $lang->confirm_forum_deletion);
	}
}

if(!$mybb->input['action'])
{
	if($mybb->request_method == "post")
	{
		foreach($mybb->input['disporder'] as $fid => $order)
		{
			$db->update_query("forums", array('disporder' => intval($order)), "fid='".intval($fid)."'");
		}
		
		$cache->update_forums();
		
		flash_message($lang->success_forum_disporder_updated, 'success');
		admin_redirect("index.php?".SID."&module=forum/management");
	}
	
	$fid = intval($mybb->input['fid']);
	
	$page->add_breadcrumb_item($lang->view_forum, "index.php?".SID."&amp;module=forum/management");
	
	$page->output_header($lang->forum_management);
	
	if($fid)
	{
		$page->output_nav_tabs($sub_tabs, 'view_forum');
	}
	else
	{
		$page->output_nav_tabs($sub_tabs, 'forum_management');
	}

	$form = new Form("index.php?".SID."&amp;module=forum/management", "post", "management");
	
	if($fid)
	{
		$tabs = array(
			'subforums' => $lang->subforums,
			'permissions' => $lang->forum_permissions,
			'moderators' => $lang->moderators,
		);
		
		$page->output_tab_control($tabs);
	
		echo "<div id=\"tab_subforums\">\n";
		if(!is_array($forum_cache))
		{
			cache_forums();
		}
		$form_container = new FormContainer(sprintf($lang->in_forums, $forum_cache[$fid]['name']));
	}
	else
	{
		$form_container = new FormContainer($lang->manage_forums);
	}
	$form_container->output_row_header($lang->forum);
	$form_container->output_row_header($lang->order, array("class" => "align_center", 'width' => '5%'));
	$form_container->output_row_header($lang->controls, array("class" => "align_center", 'width' => '200px'));
	
	build_admincp_forums_list($form_container, $fid);
	
	if(count($form_container->container->rows) == 0)
	{
		$form_container->output_cell($lang->no_forums, array('colspan' => 3));
		$form_container->construct_row();
	}
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->update_forum_orders);
	$buttons[] = $form->generate_reset_button($lang->reset);	
	
	$form->output_submit_wrapper($buttons);
	
	if($fid)
	{
		echo "</div>\n";
		
		$query = $db->simple_select("usergroups", "*", "", array("order_dir" => "name"));
		while($usergroup = $db->fetch_array($query))
		{
			$usergroups[$usergroup['gid']] = $usergroup;
		}
		
		$query = $db->simple_select("forumpermissions", "*", "fid='{$fid}'");
		while($existing = $db->fetch_array($query))
		{
			$existing_permissions[$existing['gid']] = $existing;
		}
		
		$field_list = array('canview','canpostthreads','canpostreplys','canpostpolls','candlattachments');
				
		echo "<div id=\"tab_permissions\">\n";
		$form_container = new FormContainer(sprintf($lang->forum_permissions_in, $forum_cache[$fid]['name']));
		$form_container->output_row_header($lang->permissions_group);
		$form_container->output_row_header($lang->permissions_canview, array("class" => "align_center", "width" => "10%"));
		$form_container->output_row_header($lang->permissions_canpostthreads, array("class" => "align_center", "width" => "10%"));
		$form_container->output_row_header($lang->permissions_canpostreplys, array("class" => "align_center", "width" => "10%"));
		$form_container->output_row_header($lang->permissions_canpostpolls, array("class" => "align_center", "width" => "10%"));
		$form_container->output_row_header($lang->permissions_candlattachments, array("class" => "align_center", "width" => "11%"));
		$form_container->output_row_header($lang->permissions_all, array("class" => "align_center", "width" => "10%"));
		$form_container->output_row_header($lang->controls, array("class" => "align_center", 'width' => '150px'));
		foreach($usergroups as $usergroup)
		{
			if($existing_permissions[$usergroup['gid']])
			{
				$perms = $existing_permissions[$usergroup['gid']];
				$default_checked = false;
			}
			else
			{
				$perms = $usergroup;
				$default_checked = true;
			}
			$perm_check = "";
			$all_checked = true;
			foreach($field_list as $forum_permission)
			{
				if($usergroup[$forum_permission] == 1)
				{
					$value = "true";
				}
				else
				{
					$value = "false";
				}
				if($perms[$forum_permission] != 1)
				{
					$all_checked = false;
				}
				if($perms[$forum_permission] == 1)
				{
					$perms_checked[$forum_permission] = 1;
				}
				else
				{
					$perms_checked[$forum_permission] = 0;
				}
				$all_check .= "\$('permissions_{$usergroup['gid']}_{$forum_permission}').checked = \$('permissions_{$usergroup['gid']}_all').checked;\n";
				$perm_check .= "\$('permissions_{$usergroup['gid']}_{$forum_permission}').checked = $value;\n";
			}
			$default_click = "if(this.checked == true) { $perm_check }";
			$reset_default = "\$('default_permissions_{$usergroup['gid']}').checked = false; if(this.checked == false) { \$('permissions_{$usergroup['gid']}_all').checked = false; }\n";
			$usergroup['title'] = htmlspecialchars_uni($usergroup['title']);
			$form_container->output_cell("<strong>{$usergroup['title']}</strong><br /><small style=\"vertical-align: middle;\">".$form->generate_check_box("default_permissions[{$usergroup['gid']}];", 1, "", array("id" => "default_permissions_{$usergroup['gid']}", "checked" => $default_checked, "onclick" => $default_click))." <label for=\"default_permissions_{$usergroup['gid']}\">{$lang->permissions_use_group_default}</label></small>");
			foreach($field_list as $forum_permission)
			{
				$form_container->output_cell($form->generate_check_box("permissions[{$usergroup['gid']}][{$forum_permission}]", 1, "", array("id" => "permissions_{$usergroup['gid']}_{$forum_permission}", "checked" => $perms_checked[$forum_permission], "onclick" => $reset_default)), array('class' => 'align_center'));
			}
			$form_container->output_cell($form->generate_check_box("permissions[{$usergroup['gid']}][all]", 1, "", array("id" => "permissions_{$usergroup['gid']}_all", "checked" => $all_checked, "onclick" => $all_check)), array('class' => 'align_center'));
			
			if(!$default_checked)
			{
				$form_container->output_cell("<a href=\"index.php?".SID."&amp;action=permissions&amp;gid={$group['gid']}&amp;fid={$fid}\">{$lang->edit_permissions}</a>");
			}
			else
			{
				$form_container->output_cell("<a href=\"index.php?".SID."&amp;action=permissions&amp;gid={$group['gid']}&amp;fid={$fid}\">{$lang->set_permissions}</a>", array("class" => "align_center"));
			}
			$form_container->construct_row();
		}
		$form_container->end();
		
		$buttons = array();
		$buttons[] = $form->generate_submit_button($lang->update_forum_permissions);
		$buttons[] = $form->generate_reset_button($lang->reset);	
	
		$form->output_submit_wrapper($buttons);
		
		echo "</div>\n";
		echo "<div id=\"tab_moderators\">\n";
		$form_container = new FormContainer(sprintf($lang->moderators_assigned_to, $forum_cache[$fid]['name']));
		$form_container->output_row_header($lang->add_moderator, array('width' => '75%'));
		$form_container->output_row_header($lang->controls, array("class" => "align_center", 'width' => '200px', 'colspan' => 2));
		$query = $db->query("
			SELECT m.uid, u.username
			FROM ".TABLE_PREFIX."moderators m
			LEFT JOIN ".TABLE_PREFIX."users u ON (m.uid=u.uid)
			WHERE fid='{$fid}'
		");
		while($moderator = $db->fetch_array($query))
		{
			$form_container->output_cell("<a href=\"index.php?".SID."&amp;module=user/users&amp;action=edit&amp;uid={$moderator['uid']}\">{$moderator['username']}</a>");
			$form_container->output_cell("<a href=\"index.php?".SID."&amp;module=forum/management&amp;action=editmod&amp;uid={$moderator['uid']}\">{$lang->edit}</a>", array("class" => "align_center"));
			$form_container->output_cell("<a href=\"index.php?".SID."&amp;module=forum/management&amp;action=deletemod&amp;uid={$moderator['uid']}&amp;fid={$fid}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_moderator_deletion}')\">{$lang->delete}</a>", array("class" => "align_center"));
			$form_container->construct_row();
		}
		
		if(count($form_container->container->rows) == 0)
		{
			$form_container->output_cell($lang->no_moderators, array('colspan' => 3));
			$form_container->construct_row();
		}
		
		$form_container->end();
		echo "</div>\n";
	}
	
	$form->end();
	
	$page->output_footer();
}

/**
 *
 */
function build_admincp_forums_list(&$form_container, $pid=0, $depth=1)
{
	global $mybb, $lang, $db, $sub_forums;
	static $forums_by_parent;

	if(!is_array($forums_by_parent))
	{
		$forum_cache = cache_forums();

		foreach($forum_cache as $forum)
		{
			$forums_by_parent[$forum['pid']][$val['disporder']][$forum['fid']] = $forum;
		}
	}

	if(!is_array($forums_by_parent[$pid]))
	{
		return;
	}

	foreach($forums_by_parent[$pid] as $children)
	{
		foreach($children as $forum)
		{
			if($forum['active'] == 0)
			{
				$forum['name'] = "<em>".$forum['name']."</em>";
			}
				
			if($forum['type'] == "c" && ($depth == 1 || $depth == 2))
			{
				$form_container->output_cell("<a href=\"index.php?".SID."&amp;module=forum/management&amp;fid={$forum['fid']}\"><strong>{$forum['name']}</strong></a>");

				$form_container->output_cell("<input type=\"textbox\" name=\"disporder[".$forum['fid']."]\" value=\"".$forum['disporder']."\" size=\"2\" />", array("class" => "align_center"));
				
				$popup = new PopupMenu("forum_{$forum['fid']}", $lang->options);
				$popup->add_item($lang->edit_forum, "index.php?".SID."&amp;module=forum/management&amp;action=edit&amp;fid={$forum['fid']}");
				$popup->add_item($lang->subforums, "index.php?".SID."&amp;module=forum/management&amp;fid={$forum['fid']}");
				$popup->add_item($lang->moderators, "index.php?".SID."&amp;module=forum/management&amp;action=moderators&amp;fid={$forum['fid']}");
				$popup->add_item($lang->permissions, "index.php?".SID."&amp;module=forum/management&amp;action=permissions&amp;fid={$forum['fid']}");
				$popup->add_item($lang->add_child_forum, "index.php?".SID."&amp;module=forum/management&amp;action=add&amp;fid={$forum['fid']}");
				$popup->add_item($lang->copy_forum, "index.php?".SID."&amp;module=forum/management&amp;action=copy&amp;fid={$forum['fid']}");
				$popup->add_item($lang->delete_forum, "index.php?".SID."&amp;module=forum/management&amp;action=delete&amp;fid={$forum['fid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_forum_deletion}')");
				
				$form_container->output_cell($popup->fetch(), array("class" => "align_center"));
				
				$form_container->construct_row();
				
				// Does this category have any sub forums?
				if($forums_by_parent[$forum['fid']])
				{
					build_admincp_forums_list($form_container, $forum['fid'], $depth+1);
				}
			}
			elseif($forum['type'] == "f" && ($depth == 1 || $depth == 2))
			{
				if($forum['description'])
				{
					if(my_strlen($forum['description']) > 100)
					{
						$forum['description'] = my_substr($forum['description'], 0, 98)."...";
					}
           			$forum['description'] = "<br /><small>".$forum['description']."</small>";
       			}
			
				$sub_forums = '';
				if(isset($forums_by_parent[$forum['fid']]) && $depth == 2)
				{
					build_admincp_forums_list($form_container, $forum['fid'], $depth+1);
				}
				if($sub_forums)
				{
					$sub_forums = "<br /><small>{$lang->sub_forums}: {$sub_forums}</small>";
				}
					
				if($depth == 2)
				{
					$form_container->output_cell("<div style=\"padding-left: 40px;\"><a href=\"index.php?".SID."&amp;module=forum/management&amp;fid={$forum['fid']}\">{$forum['name']}</a>{$forum['description']}{$sub_forums}</div>");
				}
				else
				{
					$form_container->output_cell("{$forum['name']}{$forum['description']}{$sub_forums}");
				}
					
				$form_container->output_cell("<input type=\"textbox\" name=\"disporder[".$forum['fid']."]\" value=\"".$forum['disporder']."\" size=\"2\" />", array("class" => "align_center"));
					
				$popup = new PopupMenu("forum_{$forum['fid']}", $lang->options);
				$popup->add_item($lang->edit_forum, "index.php?".SID."&amp;module=forum/management&amp;action=edit&amp;fid={$forum['fid']}");
				$popup->add_item($lang->subforums, "index.php?".SID."&amp;module=forum/management&amp;fid={$forum['fid']}");
				$popup->add_item($lang->moderators, "index.php?".SID."&amp;module=forum/management&amp;action=moderators&amp;fid={$forum['fid']}");
				$popup->add_item($lang->permissions, "index.php?".SID."&amp;module=forum/management&amp;action=permissions&amp;fid={$forum['fid']}");
				$popup->add_item($lang->add_child_forum, "index.php?".SID."&amp;module=forum/management&amp;action=add&amp;fid={$forum['fid']}");
				$popup->add_item($lang->copy_forum, "index.php?".SID."&amp;module=forum/management&amp;action=copy&amp;fid={$forum['fid']}");
				$popup->add_item($lang->delete_forum, "index.php?".SID."&amp;module=forum/management&amp;action=delete&amp;fid={$forum['fid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_forum_deletion}')");
				
				$form_container->output_cell($popup->fetch(), array("class" => "align_center"));
				
				$form_container->construct_row();
			}
			else if($depth == 3)
			{
				if($donecount < $mybb->settings['subforumsindex'])
				{
					$sub_forums .= "{$comma} <a href=\"index.php?".SID."&amp;module=forum/management&amp;fid={$forum['fid']}\">{$forum['name']}</a>";
					$comma = ', ';
				}
	
				// Have we reached our max visible subforums? put a nice message and break out of the loop
				++$donecount;
				if($donecount == $mybb->settings['subforumsindex'])
				{
					if(count($children) > $donecount)
					{
						$sub_forums .= $comma.sprintf($lang->more_subforums, (count($children) - $donecount));
						return;
					}
				}
			}
		}
	}
}
?>