<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: management.php 4770 2010-02-05 12:10:08Z Huji $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->forum_management, "index.php?module=forum/management");

if($mybb->input['action'] == "add" || $mybb->input['action'] == "edit" || $mybb->input['action'] == "copy" || $mybb->input['action'] == "permissions" || !$mybb->input['action'])
{
	if($mybb->input['fid'] && ($mybb->input['action'] == "management" || $mybb->input['action'] == "edit" || $mybb->input['action'] == "copy" || !$mybb->input['action']))
	{
		$sub_tabs['view_forum'] = array(
			'title' => $lang->view_forum,
			'link' => "index.php?module=forum/management&amp;fid=".$mybb->input['fid'],
			'description' => $lang->view_forum_desc
		);
	
		$sub_tabs['add_child_forum'] = array(
			'title' => $lang->add_child_forum,
			'link' => "index.php?module=forum/management&amp;action=add&amp;pid=".$mybb->input['fid'],
			'description' => $lang->add_child_forum_desc
		);
		
		$sub_tabs['edit_forum_settings'] = array(
			'title' => $lang->edit_forum_settings,
			'link' => "index.php?module=forum/management&amp;action=edit&amp;fid=".$mybb->input['fid'],
			'description' => $lang->edit_forum_settings_desc
		);
	
		$sub_tabs['copy_forum'] = array(
			'title' => $lang->copy_forum,
			'link' => "index.php?module=forum/management&amp;action=copy&amp;fid=".$mybb->input['fid'],
			'description' => $lang->copy_forum_desc
		);
	}
	else
	{
		$sub_tabs['forum_management'] = array(
			'title' => $lang->forum_management,
			'link' => "index.php?module=forum/management",
			'description' => $lang->forum_management_desc
		);
	
		$sub_tabs['add_forum'] = array(
			'title' => $lang->add_forum,
			'link' => "index.php?module=forum/management&amp;action=add",
			'description' => $lang->add_forum_desc
		);
	}
}

$plugins->run_hooks("admin_forum_management_begin");

if($mybb->input['action'] == "copy")
{
	$plugins->run_hooks("admin_forum_management_copy");
	
	if($mybb->request_method == "post")
	{
		$from = intval($mybb->input['from']);
		$to = intval($mybb->input['to']);
	
		// Find the source forum
		$query = $db->simple_select("forums", '*', "fid='{$from}'");
		$from_forum = $db->fetch_array($query);
		if(!$db->num_rows($query))
		{
			$errors[] = $lang->error_invalid_source_forum;
		}
	
		if($to == -1)
		{
			// Create a new forum
			if(empty($mybb->input['title']))
			{
				$errors[] = $lang->error_new_forum_needs_name;
			}
			
			if($mybb->input['pid'] == -1 && $mybb->input['type'] == 'f')
			{
				$errors[] = $lang->error_no_parent;
			}
	
			if(!$errors)
			{
				$new_forum = $from_forum;
				unset($new_forum['fid'], $new_forum['threads'], $new_forum['posts'], $new_forum['lastpost'], $new_forum['lastposter'], $new_forum['lastposteruid'], $new_forum['lastposttid'], $new_forum['lastpostsubject'], $new_forum['unapprovedthreads'], $new_forum['unapprovedposts']);
				$new_forum['name'] = $db->escape_string($mybb->input['title']);
				$new_forum['description'] = $db->escape_string($mybb->input['description']);
				$new_forum['type'] = $db->escape_string($mybb->input['type']);
				$new_forum['pid'] = intval($mybb->input['pid']);
				$new_forum['rulestitle'] = $db->escape_string($new_forum['rulestitle']);
				$new_forum['rules'] = $db->escape_string($new_forum['rules']);
				$new_forum['parentlist'] = '';
						
				$to = $db->insert_query("forums", $new_forum);
		
				// Generate parent list
				$parentlist = make_parent_list($to);
				$updatearray = array(
					'parentlist' => $parentlist
				);
				$db->update_query("forums", $updatearray, "fid='{$to}'");
			}
		}
		elseif($mybb->input['copyforumsettings'] == 1)
		{
			// Copy settings to existing forum
			$query = $db->simple_select("forums", '*', "fid='{$to}'");
			$to_forum = $db->fetch_array($query);
			if(!$db->num_rows($query))
			{
				$errors[] = $lang->error_invalid_destination_forum;
			}
			
			if(!$errors)
			{
				$new_forum = $from_forum;
				unset($new_forum['fid'], $new_forum['threads'], $new_forum['posts'], $new_forum['lastpost'], $new_forum['lastposter'], $new_forum['lastposteruid'], $new_forum['lastposttid'], $new_forum['lastpostsubject'], $new_forum['unapprovedthreads'], $new_forum['unapprovedposts']);
				$new_forum['name'] = $db->escape_string($to_forum['name']);
				$new_forum['description'] = $db->escape_string($to_forum['description']);
				$new_forum['pid'] = $db->escape_string($to_forum['pid']);
				$new_forum['parentlist'] = $db->escape_string($to_forum['parentlist']);
				$new_forum['rulestitle'] = $db->escape_string($new_forum['rulestitle']);
				$new_forum['rules'] = $db->escape_string($new_forum['rules']);
				
				$db->update_query("forums", $new_forum, "fid='{$to}'");
			}
		}
		
		if(!$errors)
		{
			// Copy permissions
			if(is_array($mybb->input['copygroups']) && count($mybb->input['copygroups'] > 0))
			{
				foreach($mybb->input['copygroups'] as $gid)
				{
					$groups[] = intval($gid);
				}
				$groups = implode(',', $groups);
				$query = $db->simple_select("forumpermissions", '*', "fid='{$from}' AND gid IN ({$groups})");
				$db->delete_query("forumpermissions", "fid='{$to}' AND gid IN ({$groups})", 1);
				while($permissions = $db->fetch_array($query))
				{
					unset($permissions['pid']);
					$permissions['fid'] = $to;
		
					$db->insert_query("forumpermissions", $permissions);
				}
				
				// Log admin action
				log_admin_action($from, $from_forum['name'], $to, $new_forum['name'], $groups);
			}
			else
			{
				// Log admin action (no group permissions)
				log_admin_action($from, $from_forum['name'], $to, $new_forum['name']);
			}
			$cache->update_forums();
			$cache->update_forumpermissions();
			
			$plugins->run_hooks("admin_forum_management_copy_commit");
		
			flash_message($lang->success_forum_copied, 'success');
			admin_redirect("index.php?module=forum/management&action=edit&fid={$to}");
		}
	}
	
	$page->add_breadcrumb_item($lang->copy_forum);
	$page->output_header($lang->copy_forum);	
	$page->output_nav_tabs($sub_tabs, 'copy_forum');
	
	$form = new Form("index.php?module=forum/management&amp;action=copy", "post");

	if($errors)
	{
		$page->output_inline_error($errors);
		$copy_data = $mybb->input;
	}
	else
	{		
		$copy_data['type'] = "f";
		$copy_data['title'] = "";
		$copy_data['description'] = "";
		
		if(!$mybb->input['pid'])
		{
			$copy_data['pid'] = "-1";
		}
		else
		{
			$copy_data['pid'] = intval($mybb->input['pid']);
		}
		$copy_data['disporder'] = "1";
		$copy_data['from'] = $mybb->input['fid'];
		$copy_data['copyforumsettings'] = 0;
		$copy_data['pid'] = 0;
	}
	
	$types = array(
		'f' => $lang->forum,
		'c' => $lang->category
	);
	
	$create_a_options_f = array(
		'id' => 'forum'
	);
	
	$create_a_options_c = array(
		'id' => 'category'
	);
	
	if($copy_data['type'] == "f")
	{
		$create_a_options_f['checked'] = true;
	}
	else
	{
		$create_a_options_c['checked'] = true;
	}
	
	$usergroups = array();
	
	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
	while($usergroup = $db->fetch_array($query))
	{
		$usergroups[$usergroup['gid']] = $usergroup['title'];
	}
	
	$form_container = new FormContainer($lang->copy_forum);
	$form_container->output_row($lang->source_forum." <em>*</em>", $lang->source_forum_desc, $form->generate_forum_select('from', $copy_data['from'], array('id' => 'from')), 'from');
	$form_container->output_row($lang->destination_forum." <em>*</em>", $lang->destination_forum_desc, $form->generate_forum_select('to', $copy_data['to'], array('id' => 'to', 'main_option' => $lang->copy_to_new_forum)), 'to');
	$form_container->output_row($lang->copy_settings_and_properties, $lang->copy_settings_and_properties_desc, $form->generate_yes_no_radio('copyforumsettings', $copy_data['copyforumsettings']));
	$form_container->output_row($lang->copy_user_group_permissions, $lang->copy_user_group_permissions_desc, $form->generate_select_box('copygroups[]', $usergroups, $mybb->input['copygroups'], array('id' => 'copygroups', 'multiple' => true, 'size' => 5)), 'copygroups');
	
	$form_container->end();

	$form_container = new FormContainer($lang->new_forum_settings);
	$form_container->output_row($lang->create_a, $lang->create_a_desc, $form->generate_radio_button('type', 'f', $lang->forum, $create_a_options_f)."<br />\n".$form->generate_radio_button('type', 'c', $lang->category, $create_a_options_c));
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $copy_data['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->description, "", $form->generate_text_area('description', $copy_data['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->parent_forum." <em>*</em>", $lang->parent_forum_desc, $form->generate_forum_select('pid', $copy_data['pid'], array('id' => 'pid', 'main_option' => $lang->none)), 'pid');
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->copy_forum);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();	
}

if($mybb->input['action'] == "editmod")
{
	$query = $db->simple_select("moderators", "*", "mid='".intval($mybb->input['mid'])."'");
	$mod_data = $db->fetch_array($query);

	$plugins->run_hooks("admin_forum_management_editmod");
	
	if(!$mod_data['uid'])
	{
		flash_message($lang->error_incorrect_moderator, 'error');
		admin_redirect("index.php?module=forum/management");
	}
	
	if($mybb->request_method == "post")
	{
		$mid = intval($mybb->input['mid']);
		if(!$mid)
		{
			flash_message($lang->error_incorrect_moderator, 'error');
			admin_redirect("index.php?module=forum/management");
		}
	
		if(!$errors)
		{
			$fid = intval($mybb->input['fid']);	
			$forum = get_forum($fid);
			$mod = get_user($mod_data['uid']);
			$update_array = array(
				'fid' => intval($fid),
				'caneditposts' => intval($mybb->input['caneditposts']),
				'candeleteposts' => intval($mybb->input['candeleteposts']),
				'canviewips' => intval($mybb->input['canviewips']),
				'canopenclosethreads' => intval($mybb->input['canopenclosethreads']),
				'canmanagethreads' => intval($mybb->input['canmanagethreads']),
				'canmovetononmodforum' => intval($mybb->input['canmovetononmodforum'])
			);
			$db->update_query("moderators", $update_array, "mid='".intval($mybb->input['mid'])."'");
			
			$cache->update_moderators();
			
			$plugins->run_hooks("admin_forum_management_editmod_commit");
			
			// Log admin action
			log_admin_action($fid, $forum['name'], $mid, $mod['username']);
			
			flash_message($lang->success_moderator_updated, 'success');
			admin_redirect("index.php?module=forum/management&fid=".intval($mybb->input['fid'])."#tab_moderators");
		}
	}
	
	$query = $db->simple_select("users", "username", "uid='{$mod_data['uid']}'");
	$mod_data['username'] = $db->fetch_field($query, 'username');
		
	$sub_tabs = array();
	
	$sub_tabs['edit_mod'] = array(
		'title' => $lang->edit_mod,
		'link' => "index.php?module=forum/management&amp;action=editmod&amp;mid=".$mybb->input['mid'],
		'description' => $lang->edit_mod_desc
	);
	
	$page->add_breadcrumb_item($lang->forum_moderators, "index.php?module=forum/management&amp;fid={$mod_data['fid']}#tab_moderators");
	$page->add_breadcrumb_item($lang->edit_forum);
	$page->output_header($lang->edit_mod);	
	$page->output_nav_tabs($sub_tabs, 'edit_mod');
	
	$form = new Form("index.php?module=forum/management&amp;action=editmod", "post");
	echo $form->generate_hidden_field("mid", $mod_data['mid']);

	if($errors)
	{
		$page->output_inline_error($errors);
		$mod_data = $mybb->input;
	}	

	$form_container = new FormContainer($lang->sprintf($lang->edit_mod_for, $mod_data['username']));
	$form_container->output_row($lang->forum, $lang->forum_desc, $form->generate_forum_select('fid', $mod_data['fid'], array('id' => 'fid')), 'fid');
	
	$moderator_permissions = array(
		$form->generate_check_box('caneditposts', 1, $lang->can_edit_posts, array('checked' => $mod_data['caneditposts'], 'id' => 'caneditposts')),
		$form->generate_check_box('candeleteposts', 1, $lang->can_delete_posts, array('checked' => $mod_data['candeleteposts'], 'id' => 'candeleteposts')),
		$form->generate_check_box('canviewips', 1, $lang->can_view_ips, array('checked' => $mod_data['canviewips'], 'id' => 'canviewips')),
		$form->generate_check_box('canopenclosethreads', 1, $lang->can_open_close_threads, array('checked' => $mod_data['canopenclosethreads'], 'id' => 'canopenclosethreads')),
		$form->generate_check_box('canmanagethreads', 1, $lang->can_manage_threads, array('checked' => $mod_data['canmanagethreads'], 'id' => 'canmanagethreads')),
		$form->generate_check_box('canmovetononmodforum', 1, $lang->can_move_to_other_forums, array('checked' => $mod_data['canmovetononmodforum'], 'id' => 'canmovetononmodforum'))
	);
	
	$form_container->output_row($lang->moderator_permissions, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $moderator_permissions)."</div>");
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->save_mod);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();	
}

if($mybb->input['action'] == "permissions")
{
	$plugins->run_hooks("admin_forum_management_permissions");
	
	if($mybb->request_method == "post")
	{
		$pid = intval($mybb->input['pid']);
		$fid = intval($mybb->input['fid']);
		$gid = intval($mybb->input['gid']);
		$forum = get_forum($fid);
		
		if(!$fid && $pid)
		{
			$query = $db->simple_select("forumpermissions", "fid", "pid='{$pid}'");
			$fid = $db->fetch_field($query, "fid");
		}
		
		if($mybb->input['usecustom'] == 0)
		{
			if($pid)
			{
				$db->delete_query("forumpermissions", "pid='{$pid}'");
			}
			else
			{
				$db->delete_query("forumpermissions", "gid='{$gid}' AND fid='{$fid}'");
			}
		}
		else
		{
			if(is_array($mybb->input['permissions']))
			{
				$update_array = array();
				$fields_array = $db->show_fields_from("forumpermissions");
				foreach($fields_array as $field)
				{
					if(strpos($field['Field'], 'can') !== false)
					{
						if(array_key_exists($field['Field'], $mybb->input['permissions']))
						{
							$update_array[$db->escape_string($field['Field'])] = intval($mybb->input['permissions'][$field['Field']]);
						}
						else
						{
							$update_array[$db->escape_string($field['Field'])] = 0;
						}
					}
				}
				
				if($fid && !$pid)
				{
					$update_array['fid'] = $fid;
					$update_array['gid'] = intval($mybb->input['gid']);
					$db->insert_query("forumpermissions", $update_array);
				}
				else
				{
					$db->update_query("forumpermissions", $update_array, "pid='{$pid}'");
				}
			}
		}
		$cache->update_forumpermissions();
		
		$plugins->run_hooks("admin_forum_management_permissions_commit");
		
		// Log admin action
		log_admin_action($fid, $forum['name']);
		
		flash_message($lang->success_forum_permissions_saved, 'success');
		admin_redirect("index.php?module=forum/management&fid={$fid}#tab_permissions");
	}
	
	$sub_tabs = array();
	$sub_tabs['edit_permissions'] = array(
		'title' => $lang->forum_permissions,
		'link' => "index.php?module=forum/management&amp;action=permissions&amp;fid=".$mybb->input['fid'],
		'description' => $lang->forum_permissions_desc
	);
	
	$page->add_breadcrumb_item($lang->forum_permissions2, "index.php?module=forum/management&amp;fid=".$mybb->input['fid']."#tab_permissions");
	$page->add_breadcrumb_item($lang->forum_permissions);
	$page->output_header($lang->forum_permissions);	
	$page->output_nav_tabs($sub_tabs, 'edit_permissions');
	
	if($mybb->input['pid'] || ($mybb->input['gid'] && $mybb->input['fid']))
	{
		$form = new Form("index.php?module=forum/management&amp;action=permissions", "post");
	
		if($errors)
		{
			$page->output_inline_error($errors);
			$permission_data = $mybb->input;
		}
		else
		{
			$pid = intval($mybb->input['pid']);
			$gid = intval($mybb->input['gid']);
			$fid = intval($mybb->input['fid']);
			
			if($pid)
			{
				$query = $db->simple_select("forumpermissions", "*", "pid='{$pid}'");
			}
			else
			{
				$query = $db->simple_select("forumpermissions", "*", "fid='{$fid}' AND gid='{$gid}'", array('limit' => 1));
			}
			
			$permission_data = $db->fetch_array($query);
			
			if(!$fid)
			{
				$fid = $permission_data['fid'];
			}
			
			if(!$gid)
			{
				$gid = $permission_data['gid'];
			}
			
			if(!$pid)
			{
				$pid = $permission_data['pid'];
			}
			
			$query = $db->simple_select("usergroups", "*", "gid='$gid'");
			$usergroup = $db->fetch_array($query);
			
			$query = $db->simple_select("forums", "*", "fid='$fid'");
			$forum = $db->fetch_array($query);
	
			$sperms = $permission_data;
		
			$sql = build_parent_list($fid);
			$query = $db->simple_select("forumpermissions", "*", "$sql AND gid='$gid'");
			$customperms = $db->fetch_array($query);
		
			if($permission_data['pid'])
			{
				$permission_data['usecustom'] = 1;
				echo $form->generate_hidden_field("pid", $pid);
			}
			else
			{
				echo $form->generate_hidden_field("fid", $fid);
				echo $form->generate_hidden_field("gid", $gid);
				if(!$customperms['pid'])
				{
					$permission_data = usergroup_permissions($gid);
				}
				else
				{
					$permission_data = forum_permissions($fid, 0, $gid);
				}
				$permission_data['usecustom'] = 0;
			}
		}
		
		$use_perms_options_inherit = array(
			'id' => 'inherit'
		);
		
		$use_perms_options_custom = array(
			'id' => 'custom'
		);
		
		if($permission_data['usecustom'] == 1)
		{
			$use_perms_options_custom['checked'] = true;
		}
		else
		{
			$use_perms_options_inherit['checked'] = true;	
		}
		
		$form_container = new FormContainer($lang->forum_permissions);
		$form_container->output_row($lang->use_permissions, $lang->use_permissions_desc, $form->generate_radio_button('usecustom', '0', $lang->inherit_permissions, $use_perms_options_inherit)."<br />\n".$form->generate_radio_button('usecustom', '1', $lang->custom_permissions, $use_perms_options_custom));
		
		$groups = array(
			'canviewthreads' => 'viewing',
			'canview' => 'viewing',
			'candlattachments' => 'viewing',
			
			'canpostthreads' => 'posting_rating',
			'canpostreplys' => 'posting_rating',
			'canpostattachments' => 'posting_rating',
			'canratethreads' => 'posting_rating',
			
			'caneditposts' => 'editing',
			'candeleteposts' => 'editing',
			'candeletethreads' => 'editing',
			'caneditattachments' => 'editing',
			
			'canpostpolls' => 'polls',
			'canvotepolls' => 'polls',
			'cansearch' => 'misc',
		);
		
		$field_list = array();
		$fields_array = $db->show_fields_from("forumpermissions");
		foreach($fields_array as $field)
		{
			if(strpos($field['Field'], 'can') !== false)
			{
				if(array_key_exists($field['Field'], $groups))
				{
					$field_list[$groups[$field['Field']]][] = $field['Field'];
				}
				else
				{
					$field_list['misc'][] = $field['Field'];
				}
			}
		}
		
		foreach(array_unique(array_values($groups)) as $group)
		{
			$lang_group = "group_".$group;
			$fields = array();
			foreach($field_list[$group] as $field)
			{
				$lang_field = $group."_field_".$field;
				$fields[] = $form->generate_check_box("permissions[{$field}]", 1, $lang->$lang_field, array('checked' => $permission_data[$field], 'id' => $field));
			}
			$form_container->output_row($lang->$lang_group, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $fields)."</div>");
		}	
		
		$form_container->end();
		
		$buttons[] = $form->generate_submit_button($lang->save_permissions);
		$form->output_submit_wrapper($buttons);
		$form->end();
	}
	
	$page->output_footer();	
}

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_forum_management_add");
	
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}
		
		$pid = intval($mybb->input['pid']);
		$type = $mybb->input['type'];
		
		if($pid <= 0 && $type == "f")
		{
			$errors[] = $lang->error_no_parent;
		}
		
		if(!$errors)
		{
			if($pid < 0)
			{
				$pid = 0;
			}
			$insert_array = array(
				"name" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"linkto" => $db->escape_string($mybb->input['linkto']),
				"type" => $db->escape_string($type),
				"pid" => $pid,
				"parentlist" => '',
				"disporder" => intval($mybb->input['disporder']),
				"active" => intval($mybb->input['active']),
				"open" => intval($mybb->input['open']),
				"allowhtml" => intval($mybb->input['allowhtml']),
				"allowmycode" => intval($mybb->input['allowmycode']),
				"allowsmilies" => intval($mybb->input['allowsmilies']),
				"allowimgcode" => intval($mybb->input['allowimgcode']),
				"allowpicons" => intval($mybb->input['allowpicons']),
				"allowtratings" => intval($mybb->input['allowtratings']),
				"usepostcounts" => intval($mybb->input['usepostcounts']),
				"password" => $db->escape_string($mybb->input['password']),
				"showinjump" => intval($mybb->input['showinjump']),
				"modposts" => intval($mybb->input['modposts']),
				"modthreads" => intval($mybb->input['modthreads']),
				"mod_edit_posts" => intval($mybb->input['mod_edit_posts']),
				"modattachments" => intval($mybb->input['modattachments']),
				"style" => intval($mybb->input['style']),
				"overridestyle" => intval($mybb->input['overridestyle']),
				"rulestype" => intval($mybb->input['rulestype']),
				"rulestitle" => $db->escape_string($mybb->input['rulestitle']),
				"rules" => $db->escape_string($mybb->input['rules']),
				"defaultdatecut" => intval($mybb->input['defaultdatecut']),
				"defaultsortby" => $db->escape_string($mybb->input['defaultsortby']),
				"defaultsortorder" => $db->escape_string($mybb->input['defaultsortorder']),
			);
			$fid = $db->insert_query("forums", $insert_array);
			
			$parentlist = make_parent_list($fid);
			$db->update_query("forums", array("parentlist" => $parentlist), "fid='$fid'");
			$inherit = $mybb->input['default_permissions'];
			
			foreach($mybb->input['permissions'] as $gid => $permission)
			{
				foreach(array('canview','canpostthreads','canpostreplys','canpostpolls','canpostattachments') as $name)
				{
					if($permission[$name])
					{
						$permissions[$name][$gid] = 1;
					}
					else
					{
						$permissions[$name][$gid] = 0;
					}
				}			
			}
			
			$cache->update_forums();
			
			$canview = $permissions['canview'];
			$canpostthreads = $permissions['canpostthreads'];
			$canpostreplies = $permissions['canpostreplies'];
			$canpostpolls = $permissions['canpostpolls'];
			$canpostattachments = $permissions['canpostattachments'];
			$canpostreplies = $permissions['canpostreplys'];
			save_quick_perms($fid);
			
			$plugins->run_hooks("admin_forum_management_add_commit");
			
			// Log admin action
			log_admin_action($fid, $insert_array['name']);
			
			flash_message($lang->success_forum_added, 'success');
			admin_redirect("index.php?module=forum/management");
		}
	}
	
	$page->add_breadcrumb_item($lang->add_forum);
	$page->output_header($lang->add_forum);	
	$page->output_nav_tabs($sub_tabs, 'add_forum');
	
	$form = new Form("index.php?module=forum/management&amp;action=add", "post");

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
		
		if(!$mybb->input['pid'])
		{
			$forum_data['pid'] = "-1";
		}
		else
		{
			$forum_data['pid'] = intval($mybb->input['pid']);
		}
		$forum_data['disporder'] = "1";
		$forum_data['linkto'] = "";
		$forum_data['password'] = "";
		$forum_data['active'] = 1;
		$forum_data['open'] = 1;
		$forum_data['modposts'] = "";
		$forum_data['modthreads'] = "";
		$forum_data['modattachments'] = "";
		$forum_data['mod_edit_posts'] = "";
		$forum_data['overridestyle'] = "";
		$forum_data['style'] = "";
		$forum_data['rulestype'] = "";
		$forum_data['rulestitle'] = "";
		$forum_data['rules'] = "";
		$forum_data['defaultdatecut'] = "";
		$forum_data['defaultsortby'] = "";
		$forum_data['defaultsortorder'] = "";
		$forum_data['allowhtml'] = "";
		$forum_data['allowmycode'] = 1;
		$forum_data['allowsmilies'] = 1;
		$forum_data['allowimgcode'] = 1;
		$forum_data['allowpicons'] = 1;
		$forum_data['allowtratings'] = 1;
		$forum_data['showinjump'] = 1;
		$forum_data['usepostcounts'] = 1;
	}
	
	$types = array(
		'f' => $lang->forum,
		'c' => $lang->category
	);
	
	$create_a_options_f = array(
		'id' => 'forum'
	);
	
	$create_a_options_c = array(
		'id' => 'category'
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
	$form_container->output_row($lang->create_a, $lang->create_a_desc, $form->generate_radio_button('type', 'f', $lang->forum, $create_a_options_f)."<br />\n".$form->generate_radio_button('type', 'c', $lang->category, $create_a_options_c));
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $forum_data['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->description, "", $form->generate_text_area('description', $forum_data['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->parent_forum." <em>*</em>", $lang->parent_forum_desc, $form->generate_forum_select('pid', $forum_data['pid'], array('id' => 'pid', 'main_option' => $lang->none)), 'pid');
	$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $forum_data['disporder'], array('id' => 'disporder')), 'disporder');
	$form_container->end();
	
	echo "<div id=\"additional_options_link\"><strong><a href=\"#\" onclick=\"$('additional_options_link').toggle(); $('additional_options').toggle(); return false;\">{$lang->show_additional_options}</a></strong><br /><br /></div>";
	echo "<div id=\"additional_options\" style=\"display: none;\">";
	$form_container = new FormContainer("<div class=\"float_right\" style=\"font-weight: normal;\"><a href=\"#\" onclick=\"$('additional_options_link').toggle(); $('additional_options').toggle(); return false;\">{$lang->hide_additional_options}</a></div>".$lang->additional_forum_options);
	$form_container->output_row($lang->forum_link, $lang->forum_link_desc, $form->generate_text_box('linkto', $forum_data['linkto'], array('id' => 'linkto')), 'linkto');
	$form_container->output_row($lang->forum_password, $lang->forum_password_desc, $form->generate_text_box('password', $forum_data['password'], array('id' => 'password')), 'password');
	
	$access_options = array(
		$form->generate_check_box('active', 1, $lang->forum_is_active."<br />\n<small>{$lang->forum_is_active_desc}</small>", array('checked' => $forum_data['active'], 'id' => 'active')),
		$form->generate_check_box('open', 1, $lang->forum_is_open."<br />\n<small>{$lang->forum_is_open_desc}</small>", array('checked' => $forum_data['open'], 'id' => 'open'))
	);
		
	
	$form_container->output_row($lang->access_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $access_options)."</div>");
	
	$moderator_options = array(
		$form->generate_check_box('modposts', 1, $lang->mod_new_posts, array('checked' => $forum_data['modposts'], 'id' => 'modposts')),
		$form->generate_check_box('modthreads', 1, $lang->mod_new_threads, array('checked' => $forum_data['modthreads'], 'id' => 'modthreads')),
		$form->generate_check_box('modattachments', 1, $lang->mod_new_attachments, array('checked' => $forum_data['modattachments'], 'id' => 'modattachments')),
		$form->generate_check_box('mod_edit_posts', 1, $lang->mod_after_edit, array('checked' => $forum_data['mod_edit_posts'], 'id' => 'mod_edit_posts'))
	);
	
	$form_container->output_row($lang->moderation_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $moderator_options)."</div>");
	
	$styles = array(
		'0' => $lang->use_default
	);
	
	$query = $db->simple_select("themes", "tid,name", "name!='((master))' AND name!='((master-backup))'", array('order_by' => 'name'));
	while($style = $db->fetch_array($query))
	{
		$styles[$style['tid']] = $style['name'];
	}
	
	$style_options = array(
		$form->generate_check_box('overridestyle', 1, $lang->override_user_style, array('checked' => $forum_data['overridestyle'], 'id' => 'overridestyle')),
		$lang->forum_specific_style."<br />\n".$form->generate_select_box('style', $styles, $forum_data['style'], array('id' => 'style'))
	);
	
	$form_container->output_row($lang->style_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $style_options)."</div>");
	
	$display_methods = array(
		'0' => $lang->dont_display_rules,
		'1' => $lang->display_rules_inline,
		'2' => $lang->display_rules_link
	);
	
	$forum_rules = array(
		$lang->display_method."<br />\n".$form->generate_select_box('rulestype', $display_methods, $forum_data['rulestype'], array('checked' => $forum_data['rulestype'], 'id' => 'rulestype')),
		$lang->title."<br />\n".$form->generate_text_box('rulestitle', $forum_data['rulestitle'], array('checked' => $forum_data['rulestitle'], 'id' => 'rulestitle')),
		$lang->rules."<br />\n".$form->generate_text_area('rules', $forum_data['rules'], array('checked' => $forum_data['rules'], 'id' => 'rules'))
	);
	
	$form_container->output_row($lang->forum_rules, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $forum_rules)."</div>");
	
	$default_date_cut = array(
		0 => $lang->board_default,
		1 => $lang->datelimit_1day,
		5 => $lang->datelimit_5days,
		10 => $lang->datelimit_10days,
		20 => $lang->datelimit_20days,
		50 => $lang->datelimit_50days,
		75 => $lang->datelimit_75days,
		100 => $lang->datelimit_100days,
		365 => $lang->datelimit_lastyear,
		9999 => $lang->datelimit_beginning,
	);
	
	$default_sort_by = array(
		"" => $lang->board_default,
		"subject" => $lang->sort_by_subject,
		"lastpost" => $lang->sort_by_lastpost,
		"starter" => $lang->sort_by_starter,
		"started" => $lang->sort_by_started,
		"rating" => $lang->sort_by_rating,
		"replies" => $lang->sort_by_replies,
		"views" => $lang->sort_by_views,
	);
	
	$default_sort_order = array(
		"" => $lang->board_default,
		"asc" => $lang->sort_order_asc,
		"desc" => $lang->sort_order_desc,
	);
	
	$view_options = array(
		$lang->default_date_cut."<br />\n".$form->generate_select_box('defaultdatecut', $default_date_cut, $forum_data['defaultdatecut'], array('checked' => $forum_data['defaultdatecut'], 'id' => 'defaultdatecut')),
		$lang->default_sort_by."<br />\n".$form->generate_select_box('defaultsortby', $default_sort_by, $forum_data['defaultsortby'], array('checked' => $forum_data['defaultsortby'], 'id' => 'defaultsortby')),
		$lang->default_sort_order."<br />\n".$form->generate_select_box('defaultsortorder', $default_sort_order, $forum_data['defaultsortorder'], array('checked' => $forum_data['defaultsortorder'], 'id' => 'defaultsortorder')),
	);
	
	$form_container->output_row($lang->default_view_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $view_options)."</div>");
	
	$misc_options = array(
		$form->generate_check_box('allowhtml', 1, $lang->allow_html, array('checked' => $forum_data['allowhtml'], 'id' => 'allowhtml')),
		$form->generate_check_box('allowmycode', 1, $lang->allow_mycode, array('checked' => $forum_data['allowmycode'], 'id' => 'allowmycode')),
		$form->generate_check_box('allowsmilies', 1, $lang->allow_smilies, array('checked' => $forum_data['allowsmilies'], 'id' => 'allowsmilies')),
		$form->generate_check_box('allowimgcode', 1, $lang->allow_img_code, array('checked' => $forum_data['allowimgcode'], 'id' => 'allowimgcode')),
		$form->generate_check_box('allowpicons', 1, $lang->allow_post_icons, array('checked' => $forum_data['allowpicons'], 'id' => 'allowpicons')),
		$form->generate_check_box('allowtratings', 1, $lang->allow_thread_ratings, array('checked' => $forum_data['allowtratings'], 'id' => 'allowtratings')),
		$form->generate_check_box('showinjump', 1, $lang->show_forum_jump, array('checked' => $forum_data['showinjump'], 'id' => 'showinjump')),
		$form->generate_check_box('usepostcounts', 1, $lang->use_postcounts, array('checked' => $forum_data['usepostcounts'], 'id' => 'usepostcounts'))
	);
	
	$form_container->output_row($lang->misc_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $misc_options)."</div>");
	$form_container->end();
	echo "</div>";

	$query = $db->simple_select("usergroups", "*", "", array("order_dir" => "name"));
	while($usergroup = $db->fetch_array($query))
	{
		$usergroups[$usergroup['gid']] = $usergroup;
	}
	
	$cached_forum_perms = $cache->read("forumpermissions");
	$field_list = array('canview', 'canpostthreads', 'canpostreplys', 'canpostpolls', 'canpostattachments');
	
	$form_container = new FormContainer($lang->forum_permissions);
	$form_container->output_row_header($lang->permissions_group);
	$form_container->output_row_header($lang->permissions_canview, array("class" => "align_center", "width" => "10%"));
	$form_container->output_row_header($lang->permissions_canpostthreads, array("class" => "align_center", "width" => "10%"));
	$form_container->output_row_header($lang->permissions_canpostreplys, array("class" => "align_center", "width" => "10%"));
	$form_container->output_row_header($lang->permissions_canpostpolls, array("class" => "align_center", "width" => "10%"));
	$form_container->output_row_header($lang->permissions_canuploadattachments, array("class" => "align_center", "width" => "11%"));
	$form_container->output_row_header($lang->permissions_all, array("class" => "align_center", "width" => "10%"));
	foreach($usergroups as $usergroup)
	{
		$perms = array();
		if(isset($mybb->input['default_permissions']))
		{
			if($mybb->input['default_permissions'][$usergroup['gid']])
			{
				if(is_array($existing_permissions) && $existing_permissions[$usergroup['gid']])
				{
					$perms = $existing_permissions[$usergroup['gid']];
					$default_checked = false;
				}
				else if(is_array($cached_forum_perms) && $cached_forum_perms[$mybb->input['pid']][$usergroup['gid']])
				{
					$perms = $cached_forum_perms[$mybb->input['pid']][$usergroup['gid']];
					$default_checked = true;
				}
			}
			
			if(!$perms)
			{
				$perms = $usergroup;
				$default_checked = true;
			}
		}
		else
		{
			if(is_array($existing_permissions) && $existing_permissions[$usergroup['gid']])
			{
				$perms = $existing_permissions[$usergroup['gid']];
				$default_checked = false;
			}
			else if(is_array($cached_forum_perms) && $cached_forum_perms[$mybb->input['pid']][$usergroup['gid']])
			{
				$perms = $cached_forum_perms[$mybb->input['pid']][$usergroup['gid']];
				$default_checked = true;
			}
			
			if(!$perms)
			{
				$perms = $usergroup;
				$default_checked = true;
			}
		}
		
		$all_check = "";
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
			
			if(isset($mybb->input['permissions']))
			{
				if($mybb->input['permissions'][$usergroup['gid']]['all'])
				{
					$all_checked = false;
				}
				
				if($mybb->input['permissions'][$usergroup['gid']][$forum_permission])
				{
					$perms_checked[$forum_permission] = 1;
				}
				else
				{
					$perms_checked[$forum_permission] = 0;
				}
			}
			else
			{
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
	$plugins->run_hooks("admin_forum_management_edit");
	
	if(!$mybb->input['fid'])
	{
		flash_message($lang->error_invalid_fid, 'error');
		admin_redirect("index.php?module=forum/management");
	}
	
	$query = $db->simple_select("forums", "*", "fid='{$mybb->input['fid']}'");
	$forum_data = $db->fetch_array($query);
	if(!$forum_data)
	{
		flash_message($lang->error_invalid_fid, 'error');
		admin_redirect("index.php?module=forum/management");
	}
	
	$fid = intval($mybb->input['fid']);
	
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}
		
		$pid = intval($mybb->input['pid']);
		
		if($pid == $mybb->input['fid'])
		{
			$errors[] = $lang->error_forum_parent_itself;
		}
		else
		{
			$query = $db->simple_select("forums", "*", "pid='{$mybb->input['fid']}'");
			while($child = $db->fetch_array($query))
			{
				if($child['fid'] == $pid)
				{
					$errors[] = $lang->error_forum_parent_child;
					break;
				}
			}
		}
		
		$type = $mybb->input['type'];
		
		if($pid <= 0 && $type == "f")
		{
			$errors[] = $lang->error_no_parent;
		}		
		
		if($type == 'c' && $forum_data['type'] == 'f')
		{
			$query = $db->simple_select('threads', 'COUNT(tid) as num_threads', "fid = '{$fid}'");
			if($db->fetch_field($query, "num_threads") > 0)
			{
				$errors[] = $lang->error_not_empty;
			}
		}
		
		if(!$errors)
		{
			if($pid < 0)
			{
				$pid = 0;
			}
			$update_array = array(
				"name" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"linkto" => $db->escape_string($mybb->input['linkto']),
				"type" => $db->escape_string($type),
				"pid" => $pid,
				"disporder" => intval($mybb->input['disporder']),
				"active" => intval($mybb->input['active']),
				"open" => intval($mybb->input['open']),
				"allowhtml" => intval($mybb->input['allowhtml']),
				"allowmycode" => intval($mybb->input['allowmycode']),
				"allowsmilies" => intval($mybb->input['allowsmilies']),
				"allowimgcode" => intval($mybb->input['allowimgcode']),
				"allowpicons" => intval($mybb->input['allowpicons']),
				"allowtratings" => intval($mybb->input['allowtratings']),
				"usepostcounts" => intval($mybb->input['usepostcounts']),
				"password" => $db->escape_string($mybb->input['password']),
				"showinjump" => intval($mybb->input['showinjump']),
				"modposts" => intval($mybb->input['modposts']),
				"modthreads" => intval($mybb->input['modthreads']),
				"mod_edit_posts" => intval($mybb->input['mod_edit_posts']),
				"modattachments" => intval($mybb->input['modattachments']),
				"style" => intval($mybb->input['style']),
				"overridestyle" => intval($mybb->input['overridestyle']),
				"rulestype" => intval($mybb->input['rulestype']),
				"rulestitle" => $db->escape_string($mybb->input['rulestitle']),
				"rules" => $db->escape_string($mybb->input['rules']),
				"defaultdatecut" => intval($mybb->input['defaultdatecut']),
				"defaultsortby" => $db->escape_string($mybb->input['defaultsortby']),
				"defaultsortorder" => $db->escape_string($mybb->input['defaultsortorder']),
			);
			$db->update_query("forums", $update_array, "fid='{$fid}'");
			if($pid != $forum_data['pid'])
			{
				// Update the parentlist of this forum.
				$db->update_query("forums", array("parentlist" => make_parent_list($fid)), "fid='{$fid}'", 1);
				
				// Rebuild the parentlist of all of the subforums of this forum
				switch($db->type)
				{
					case "sqlite3":
					case "sqlite2":
					case "pgsql":
						$query = $db->simple_select("forums", "fid", "','||parentlist||',' LIKE '%,$fid,%'");
						break;
					default:
						$query = $db->simple_select("forums", "fid", "CONCAT(',',parentlist,',') LIKE '%,$fid,%'");
				}
				
				while($child = $db->fetch_array($query))
				{
					$db->update_query("forums", array("parentlist" => make_parent_list($child['fid'])), "fid='{$child['fid']}'", 1);
				}
			}
			
			$inherit = $mybb->input['default_permissions'];
			
			foreach($mybb->input['permissions'] as $gid => $permission)
			{
				foreach(array('canview','canpostthreads','canpostreplys','canpostpolls','canpostattachments') as $name)
				{
					if($permission[$name])
					{
						$permissions[$name][$gid] = 1;
					}
					else
					{
						$permissions[$name][$gid] = 0;
					}
				}			
			}
			
			$cache->update_forums();
			
			$canview = $permissions['canview'];
			$canpostthreads = $permissions['canpostthreads'];
			$canpostpolls = $permissions['canpostpolls'];
			$canpostattachments = $permissions['canpostattachments'];
			$canpostreplies = $permissions['canpostreplys'];
			save_quick_perms($fid);
			
			$plugins->run_hooks("admin_forum_management_edit_commit");
			
			// Log admin action
			log_admin_action($fid, $mybb->input['title']);
			
			flash_message($lang->success_forum_updated, 'success');
			admin_redirect("index.php?module=forum/management");
		}
	}
	
	$page->add_breadcrumb_item($lang->edit_forum);
	$page->output_header($lang->edit_forum);
		
	$page->output_nav_tabs($sub_tabs, 'edit_forum_settings');
	
	$form = new Form("index.php?module=forum/management&amp;action=edit", "post");
	echo $form->generate_hidden_field("fid", $fid);

	if($errors)
	{
		$page->output_inline_error($errors);
		$forum_data = $mybb->input;
	}
	else
	{
		$forum_data['title'] = $forum_data['name'];
	}
	
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
	
	$types = array(
		'f' => $lang->forum,
		'c' => $lang->category
	);
	
	$create_a_options_f = array(
		'id' => 'forum'
	);
	
	$create_a_options_c = array(
		'id' => 'category'
	);
	
	if($forum_data['type'] == "f")
	{
		$create_a_options_f['checked'] = true;
	}
	else
	{
		$create_a_options_c['checked'] = true;
	}

	$form_container = new FormContainer($lang->edit_forum);
	$form_container->output_row($lang->create_a, $lang->create_a_desc, $form->generate_radio_button('type', 'f', $lang->forum, $create_a_options_f)."<br />\n".$form->generate_radio_button('type', 'c', $lang->category, $create_a_options_c));
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $forum_data['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->description, "", $form->generate_text_area('description', $forum_data['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->parent_forum." <em>*</em>", $lang->parent_forum_desc, $form->generate_forum_select('pid', $forum_data['pid'], array('id' => 'pid', 'main_option' => $lang->none)), 'pid');
	$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $forum_data['disporder'], array('id' => 'disporder')), 'disporder');
	$form_container->end();
	
	$form_container = new FormContainer($lang->additional_forum_options);
	$form_container->output_row($lang->forum_link, $lang->forum_link_desc, $form->generate_text_box('linkto', $forum_data['linkto'], array('id' => 'linkto')), 'linkto');
	$form_container->output_row($lang->forum_password, $lang->forum_password_desc, $form->generate_text_box('password', $forum_data['password'], array('id' => 'password')), 'password');
	
	$access_options = array(
		$form->generate_check_box('active', 1, $lang->forum_is_active."<br />\n<small>{$lang->forum_is_active_desc}</small>", array('checked' => $forum_data['active'], 'id' => 'active')),
		$form->generate_check_box('open', 1, $lang->forum_is_open."<br />\n<small>{$lang->forum_is_open_desc}</small>", array('checked' => $forum_data['open'], 'id' => 'open'))
	);
		
	$form_container->output_row($lang->access_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $access_options)."</div>");
	
	$moderator_options = array(
		$form->generate_check_box('modposts', 1, $lang->mod_new_posts, array('checked' => $forum_data['modposts'], 'id' => 'modposts')),
		$form->generate_check_box('modthreads', 1, $lang->mod_new_threads, array('checked' => $forum_data['modthreads'], 'id' => 'modthreads')),
		$form->generate_check_box('modattachments', 1, $lang->mod_new_attachments, array('checked' => $forum_data['modattachments'], 'id' => 'modattachments')),
		$form->generate_check_box('mod_edit_posts',1, $lang->mod_after_edit, array('checked' => $forum_data['mod_edit_posts'], 'id' => 'mod_edit_posts'))
	);
	
	$form_container->output_row($lang->moderation_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $moderator_options)."</div>");
	
	$styles = array(
		'0' => $lang->use_default
	);
	
	$query = $db->simple_select("themes", "tid,name", "name!='((master))' AND name!='((master-backup))'", array('order_by' => 'name'));
	while($style = $db->fetch_array($query))
	{
		$styles[$style['tid']] = $style['name'];
	}
	
	$style_options = array(
		$form->generate_check_box('overridestyle', 1, $lang->override_user_style, array('checked' => $forum_data['overridestyle'], 'id' => 'overridestyle')),
		$lang->forum_specific_style."<br />\n".$form->generate_select_box('style', $styles, $forum_data['style'], array('id' => 'style'))
	);
	
	$form_container->output_row($lang->style_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $style_options)."</div>");
	
	$display_methods = array(
		'0' => $lang->dont_display_rules,
		'1' => $lang->display_rules_inline,
		'2' => $lang->display_rules_link
	);
	
	$forum_rules = array(
		$lang->display_method."<br />\n".$form->generate_select_box('rulestype', $display_methods, $forum_data['rulestype'], array('checked' => $forum_data['rulestype'], 'id' => 'rulestype')),
		$lang->title."<br />\n".$form->generate_text_box('rulestitle', $forum_data['rulestitle'], array('checked' => $forum_data['rulestitle'], 'id' => 'rulestitle')),
		$lang->rules."<br />\n".$form->generate_text_area('rules', $forum_data['rules'], array('checked' => $forum_data['rules'], 'id' => 'rules'))
	);
	
	$form_container->output_row($lang->forum_rules, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $forum_rules)."</div>");
	
	$default_date_cut = array(
		0 => $lang->board_default,
		1 => $lang->datelimit_1day,
		5 => $lang->datelimit_5days,
		10 => $lang->datelimit_10days,
		20 => $lang->datelimit_20days,
		50 => $lang->datelimit_50days,
		75 => $lang->datelimit_75days,
		100 => $lang->datelimit_100days,
		365 => $lang->datelimit_lastyear,
		9999 => $lang->datelimit_beginning,
	);
	
	$default_sort_by = array(
		"" => $lang->board_default,
		"subject" => $lang->sort_by_subject,
		"lastpost" => $lang->sort_by_lastpost,
		"starter" => $lang->sort_by_starter,
		"started" => $lang->sort_by_started,
		"rating" => $lang->sort_by_rating,
		"replies" => $lang->sort_by_replies,
		"views" => $lang->sort_by_views,
	);
	
	$default_sort_order = array(
		"" => $lang->board_default,
		"asc" => $lang->sort_order_asc,
		"desc" => $lang->sort_order_desc,
	);
	
	$view_options = array(
		$lang->default_date_cut."<br />\n".$form->generate_select_box('defaultdatecut', $default_date_cut, $forum_data['defaultdatecut'], array('checked' => $forum_data['defaultdatecut'], 'id' => 'defaultdatecut')),
		$lang->default_sort_by."<br />\n".$form->generate_select_box('defaultsortby', $default_sort_by, $forum_data['defaultsortby'], array('checked' => $forum_data['defaultsortby'], 'id' => 'defaultsortby')),
		$lang->default_sort_order."<br />\n".$form->generate_select_box('defaultsortorder', $default_sort_order, $forum_data['defaultsortorder'], array('checked' => $forum_data['defaultsortorder'], 'id' => 'defaultsortorder')),
	);
	
	$form_container->output_row($lang->default_view_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $view_options)."</div>");
	
	$misc_options = array(
		$form->generate_check_box('allowhtml', 1, $lang->allow_html, array('checked' => $forum_data['allowhtml'], 'id' => 'allowhtml')),
		$form->generate_check_box('allowmycode', 1, $lang->allow_mycode, array('checked' => $forum_data['allowmycode'], 'id' => 'allowmycode')),
		$form->generate_check_box('allowsmilies', 1, $lang->allow_smilies, array('checked' => $forum_data['allowsmilies'], 'id' => 'allowsmilies')),
		$form->generate_check_box('allowimgcode', 1, $lang->allow_img_code, array('checked' => $forum_data['allowimgcode'], 'id' => 'allowimgcode')),
		$form->generate_check_box('allowpicons', 1, $lang->allow_post_icons, array('checked' => $forum_data['allowpicons'], 'id' => 'allowpicons')),
		$form->generate_check_box('allowtratings', 1, $lang->allow_thread_ratings, array('checked' => $forum_data['allowtratings'], 'id' => 'allowtratings')),
		$form->generate_check_box('showinjump', 1, $lang->show_forum_jump, array('checked' => $forum_data['showinjump'], 'id' => 'showinjump')),
		$form->generate_check_box('usepostcounts', 1, $lang->use_postcounts, array('checked' => $forum_data['usepostcounts'], 'id' => 'usepostcounts'))
	);
	
	$form_container->output_row($lang->misc_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $misc_options)."</div>");
	$form_container->end();
	
	$cached_forum_perms = $cache->read("forumpermissions");
	$field_list = array('canview','canpostthreads','canpostreplys','canpostpolls','canpostattachments');
				
	$form_container = new FormContainer($lang->sprintf($lang->forum_permissions_in, $forum_data['name']));
	$form_container->output_row_header($lang->permissions_group);
	$form_container->output_row_header($lang->permissions_canview, array("class" => "align_center", "width" => "10%"));
	$form_container->output_row_header($lang->permissions_canpostthreads, array("class" => "align_center", "width" => "10%"));
	$form_container->output_row_header($lang->permissions_canpostreplys, array("class" => "align_center", "width" => "10%"));
	$form_container->output_row_header($lang->permissions_canpostpolls, array("class" => "align_center", "width" => "10%"));
	$form_container->output_row_header($lang->permissions_canuploadattachments, array("class" => "align_center", "width" => "11%"));
	$form_container->output_row_header($lang->permissions_all, array("class" => "align_center", "width" => "10%"));
	$form_container->output_row_header($lang->controls, array("class" => "align_center", 'style' => 'width: 150px'));
	foreach($usergroups as $usergroup)
	{
		$perms = array();
		$all_check = "";
		if(isset($mybb->input['default_permissions']))
		{
			if($mybb->input['default_permissions'][$usergroup['gid']])
			{
				if(is_array($existing_permissions) && $existing_permissions[$usergroup['gid']])
				{
					$perms = $existing_permissions[$usergroup['gid']];
					$default_checked = false;
				}
				elseif(is_array($cached_forum_perms) && $cached_forum_perms[$forum_data['fid']][$usergroup['gid']])
				{
					$perms = $cached_forum_perms[$forum_data['fid']][$usergroup['gid']];
					$default_checked = true;
				}
				else if(is_array($cached_forum_perms) && $cached_forum_perms[$forum_data['pid']][$usergroup['gid']])
				{
					$perms = $cached_forum_perms[$forum_data['pid']][$usergroup['gid']];
					$default_checked = true;
				}
			}
			
			if(!$perms)
			{
				$perms = $usergroup;
				$default_checked = true;
			}
		}
		else
		{
			if(is_array($existing_permissions) && $existing_permissions[$usergroup['gid']])
			{
				$perms = $existing_permissions[$usergroup['gid']];
				$default_checked = false;
			}
			elseif(is_array($cached_forum_perms) && $cached_forum_perms[$forum_data['fid']][$usergroup['gid']])
			{
				$perms = $cached_forum_perms[$forum_data['fid']][$usergroup['gid']];
				$default_checked = true;
			}
			else if(is_array($cached_forum_perms) && $cached_forum_perms[$forum_data['pid']][$usergroup['gid']])
			{
				$perms = $cached_forum_perms[$forum_data['pid']][$usergroup['gid']];
				$default_checked = true;
			}
			
			if(!$perms)
			{
				$perms = $usergroup;
				$default_checked = true;
			}
		}
		$all_check = "";
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
			
			if(isset($mybb->input['permissions']))
			{
				if($mybb->input['permissions'][$usergroup['gid']]['all'])
				{
					$all_checked = false;
				}
				
				if($mybb->input['permissions'][$usergroup['gid']][$forum_permission])
				{
					$perms_checked[$forum_permission] = 1;
				}
				else
				{
					$perms_checked[$forum_permission] = 0;
				}
			}
			else
			{
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
			$form_container->output_cell("<a href=\"index.php?module=forum/management&amp;action=permissions&amp;pid={$perms['pid']}\">{$lang->edit_permissions}</a>", array("class" => "align_center"));
		}
		else
		{
			$form_container->output_cell("<a href=\"index.php?module=forum/management&amp;action=permissions&amp;gid={$usergroup['gid']}&amp;fid={$fid}\">{$lang->set_permissions}</a>", array("class" => "align_center"));
		}
		$form_container->construct_row();
	}
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->save_forum);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == "deletemod")
{
	$plugins->run_hooks("admin_forum_management_deletemod");
	
	$query = $db->simple_select("moderators", "*", "uid='{$mybb->input['uid']}' AND fid='{$mybb->input['fid']}'");
	$mod = $db->fetch_array($query);
	
	// Does the forum not exist?
	if(!$mod['mid'])
	{
		flash_message($lang->error_invalid_moderator, 'error');
		admin_redirect("index.php?module=forum/management&fid=".$mybb->input['fid']);
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=forum/management&fid=".$mybb->input['fid']);
	}
	
	if($mybb->request_method == "post")
	{
		$mid = $mod['mid'];
		$query = $db->query("
			SELECT m.*, u.username, u.usergroup
			FROM ".TABLE_PREFIX."moderators m 
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=m.uid)
			WHERE m.mid='{$mid}'
		");
		$mod = $db->fetch_array($query);
		
		$db->delete_query("moderators", "mid='{$mid}'");
		$query = $db->simple_select("moderators", "*", "uid='{$mod['uid']}'");
		if($db->num_rows($query) == 0)
		{
			$updatequery = array(
				"usergroup" => "2"
			);
			$db->update_query("users", $updatequery, "uid='{$mod['uid']}' AND usergroup != '4' AND usergroup != '3'");
		}
		$cache->update_moderators();
		
		$plugins->run_hooks("admin_forum_management_deletemod_commit");
		
		$forum = get_forum($mybb->input['fid']);
		
		// Log admin action
		log_admin_action($mod['uid'], $mod['username'], $forum['fid'], $forum['name']);
		
		flash_message($lang->success_moderator_deleted, 'success');
		admin_redirect("index.php?module=forum/management&fid=".$mybb->input['fid']."#tab_moderators");
	}
	else
	{
		$page->output_confirm_action("index.php?module=forum/management&amp;action=deletemod&amp;fid={$mod['fid']}&amp;uid={$mod['uid']}", $lang->confirm_moderator_deletion);
	}
}

if($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("admin_forum_management_delete");
	
	$query = $db->simple_select("forums", "*", "fid='{$mybb->input['fid']}'");
	$forum = $db->fetch_array($query);
	
	// Does the forum not exist?
	if(!$forum['fid'])
	{
		flash_message($lang->error_invalid_forum, 'error');
		admin_redirect("index.php?module=forum/management");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=forum/management");
	}

	if($mybb->request_method == "post")
	{
		$fid = intval($mybb->input['fid']);
		$forum_info = get_forum($fid);
		// Delete the forum
		$db->delete_query("forums", "fid='$fid'");
		switch($db->type)
		{
			case "pgsql":
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
			case "pgsql":
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
		$db->delete_query("forumsubscriptions", "fid='{$fid}' {$delquery}");

		$cache->update_forums();
		$cache->update_moderators();
		$cache->update_forumpermissions();
		
		$plugins->run_hooks("admin_forum_management_delete_commit");
		
		// Log admin action
		log_admin_action($forum_info['fid'], $forum_info['name']);

		flash_message($lang->success_forum_deleted, 'success');
		admin_redirect("index.php?module=forum/management");
	}
	else
	{
		$page->output_confirm_action("index.php?module=forum/management&amp;action=delete&amp;fid={$forum['fid']}", $lang->confirm_forum_deletion);
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_forum_management_start");
	$fid = intval($mybb->input['fid']);
	if($mybb->request_method == "post")
	{
		if($fid)
		{
			$forum = get_forum($fid);
		}
		if($mybb->input['update'] == "permissions")
		{
			$inherit = $mybb->input['default_permissions'];
			
			if(empty($mybb->input['permissions']))
			{
    			$mybb->input['permissions'] = array();
			}
			
			foreach($mybb->input['permissions'] as $gid => $permission)
			{
				foreach(array('canview','canpostthreads','canpostreplys','canpostpolls','canpostattachments') as $name)
				{
					if($permission[$name])
					{
						$permissions[$name][$gid] = 1;
					}
					else
					{
						$permissions[$name][$gid] = 0;
					}
				}
			}
			
			$canview = $permissions['canview'];
			$canpostthreads = $permissions['canpostthreads'];
			$canpostreplies = $permissions['canpostreplies'];
			$canpostpolls = $permissions['canpostpolls'];
			$canpostattachments = $permissions['canpostattachments'];
			$canpostreplies = $permissions['canpostreplys'];
			save_quick_perms($fid);
			
			$plugins->run_hooks("admin_forum_management_start_permissions_commit");
			
			// Log admin action
			log_admin_action('quickpermissions', $fid, $forum['name']);
			
			flash_message($lang->success_forum_permissions_updated, 'success');
			admin_redirect("index.php?module=forum/management&fid={$fid}#tab_permissions");
		}
		elseif($mybb->input['add'] == "moderators")
		{
			
			$forum = get_forum($fid);
			if(!$forum)
			{
				flash_message($lang->error_invalid_forum, 'error');
				admin_redirect("index.php?module=forum/management&fid={$fid}#tab_moderators");
			}
			$query = $db->simple_select("users", "uid, username", "username='".$db->escape_string($mybb->input['username'])."'", array('limit' => 1));
			$user = $db->fetch_array($query);
			if($user['uid'])
			{
				$query = $db->simple_select("moderators", "uid", "uid='".$user['uid']."' AND fid='".$fid."'", array('limit' => 1));
				$mod = $db->fetch_array($query);
				if(!$mod['uid'])
				{
					$new_mod = array(
						"fid" => $fid,
						"uid" => $user['uid'],
						"caneditposts" => 1,
						"candeleteposts" => 1,
						"canviewips" => 1,
						"canopenclosethreads" => 1,
						"canmanagethreads" => 1,
						"canmovetononmodforum" => 1
					);
					$mid = $db->insert_query("moderators", $new_mod);
					$db->update_query("users", array('usergroup' => 6), "uid='{$user['uid']}' AND usergroup='2'");
					$cache->update_moderators();
					
					$plugins->run_hooks("admin_forum_management_start_moderators_commit");
					
					// Log admin action
					log_admin_action('addmod', $new_mod['fid'], $user['username'], $fid, $forum['name']);
					
					flash_message($lang->success_moderator_added, 'success');
					admin_redirect("index.php?module=forum/management&action=editmod&mid={$mid}");
				}
				else
				{
					flash_message($lang->error_moderator_already_added, 'error');
					admin_redirect("index.php?module=forum/management&fid={$fid}#tab_moderators");
				}
			}
			else
			{
				flash_message($lang->error_moderator_not_found, 'error');
				admin_redirect("index.php?module=forum/management&fid={$fid}#tab_moderators");
			}
		}
		else
		{
			if(!empty($mybb->input['disporder']) && is_array($mybb->input['disporder']))
			{
				foreach($mybb->input['disporder'] as $update_fid => $order)
				{
					$db->update_query("forums", array('disporder' => intval($order)), "fid='".intval($update_fid)."'");
				}
						
				$cache->update_forums();
				
				$plugins->run_hooks("admin_forum_management_start_disporder_commit");
				
				// Log admin action
				log_admin_action('orders', $forum['fid'], $forum['name']);
			
				flash_message($lang->success_forum_disporder_updated, 'success');
				admin_redirect("index.php?module=forum/management&fid=".$mybb->input['fid']);
			}
		}
	}
	
	if($fid)
	{
		$page->add_breadcrumb_item($lang->view_forum, "index.php?module=forum/management");
	}
	
	$page->output_header($lang->forum_management);
	
	if($fid)
	{
		$page->output_nav_tabs($sub_tabs, 'view_forum');
	}
	else
	{
		$page->output_nav_tabs($sub_tabs, 'forum_management');
	}
	
	$form = new Form("index.php?module=forum/management", "post", "management");
	echo $form->generate_hidden_field("fid", $mybb->input['fid']);
	
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
		$form_container = new FormContainer($lang->sprintf($lang->in_forums, $forum_cache[$fid]['name']));
	}
	else
	{
		$form_container = new FormContainer($lang->manage_forums);
	}
	$form_container->output_row_header($lang->forum);
	$form_container->output_row_header($lang->order, array("class" => "align_center", 'width' => '5%'));
	$form_container->output_row_header($lang->controls, array("class" => "align_center", 'style' => 'width: 200px'));
	
	build_admincp_forums_list($form_container, $fid);
	
	$submit_options = array();
	
	if($form_container->num_rows() == 0)
	{
		$form_container->output_cell($lang->no_forums, array('colspan' => 3));
		$form_container->construct_row();
		$submit_options = array('disabled' => true);
	}
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->update_forum_orders, $submit_options);
	$buttons[] = $form->generate_reset_button($lang->reset);	
	
	$form->output_submit_wrapper($buttons);
	
	if(!$fid)
	{
		$form->end();
	}
	
	if($fid)
	{
		echo "</div>\n";
		$form->end();
		
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
		
		$field_list = array('canview','canpostthreads','canpostreplys','canpostpolls','canpostattachments');
		
		$form = new Form("index.php?module=forum/management", "post", "management");
		echo $form->generate_hidden_field("fid", $mybb->input['fid']);
		echo $form->generate_hidden_field("update", "permissions");
				
		echo "<div id=\"tab_permissions\">\n";
		
		$form_container = new FormContainer($lang->sprintf($lang->forum_permissions_in, $forum_cache[$fid]['name']));
		$form_container->output_row_header($lang->permissions_group);
		$form_container->output_row_header($lang->permissions_canview, array("class" => "align_center", "width" => "10%"));
		$form_container->output_row_header($lang->permissions_canpostthreads, array("class" => "align_center", "width" => "10%"));
		$form_container->output_row_header($lang->permissions_canpostreplys, array("class" => "align_center", "width" => "10%"));
		$form_container->output_row_header($lang->permissions_canpostpolls, array("class" => "align_center", "width" => "10%"));
		$form_container->output_row_header($lang->permissions_canuploadattachments, array("class" => "align_center", "width" => "11%"));
		$form_container->output_row_header($lang->permissions_all, array("class" => "align_center", "width" => "10%"));
		$form_container->output_row_header($lang->controls, array("class" => "align_center", 'style' => 'width: 150px'));
		foreach($usergroups as $usergroup)
		{
			$perms = array();
			$all_check = "";
			if(isset($mybb->input['default_permissions']))
			{
				if($mybb->input['default_permissions'][$usergroup['gid']])
				{
					if($existing_permissions[$usergroup['gid']])
					{
						$perms = $existing_permissions[$usergroup['gid']];
						$default_checked = false;
					}
					elseif($cached_forum_perms[$forum_data['fid']][$usergroup['gid']])
					{
						$perms = $cached_forum_perms[$forum_data['fid']][$usergroup['gid']];
						$default_checked = true;
					}
					else if($cached_forum_perms[$forum_data['pid']][$usergroup['gid']])
					{
						$perms = $cached_forum_perms[$forum_data['pid']][$usergroup['gid']];
						$default_checked = true;
					}
				}
				
				if(!$perms)
				{
					$perms = $usergroup;
					$default_checked = true;
				}
			}
			else
			{
				if($existing_permissions[$usergroup['gid']])
				{
					$perms = $existing_permissions[$usergroup['gid']];
					$default_checked = false;
				}
				elseif($cached_forum_perms[$forum_data['fid']][$usergroup['gid']])
				{
					$perms = $cached_forum_perms[$forum_data['fid']][$usergroup['gid']];
					$default_checked = true;
				}
				else if($cached_forum_perms[$forum_data['pid']][$usergroup['gid']])
				{
					$perms = $cached_forum_perms[$forum_data['pid']][$usergroup['gid']];
					$default_checked = true;
				}
				
				if(!$perms)
				{
					$perms = $usergroup;
					$default_checked = true;
				}
			}
			
			$all_check = "";
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
				$form_container->output_cell("<a href=\"index.php?module=forum/management&amp;action=permissions&amp;pid={$perms['pid']}\">{$lang->edit_permissions}</a>", array("class" => "align_center"));
			}
			else
			{
				$form_container->output_cell("<a href=\"index.php?module=forum/management&amp;action=permissions&amp;gid={$usergroup['gid']}&amp;fid={$fid}\">{$lang->set_permissions}</a>", array("class" => "align_center"));
			}
			$form_container->construct_row();
		}
		$form_container->end();
		
		$buttons = array();
		$buttons[] = $form->generate_submit_button($lang->update_forum_permissions);
		$buttons[] = $form->generate_reset_button($lang->reset);	
	
		$form->output_submit_wrapper($buttons);
		
		echo "</div>\n";
		$form->end();
		echo "<div id=\"tab_moderators\">\n";
		$form_container = new FormContainer($lang->sprintf($lang->moderators_assigned_to, $forum_cache[$fid]['name']));
		$form_container->output_row_header($lang->username, array('width' => '75%'));
		$form_container->output_row_header($lang->controls, array("class" => "align_center", 'style' => 'width: 200px', 'colspan' => 2));
		$query = $db->query("
			SELECT m.mid, m.uid, u.username
			FROM ".TABLE_PREFIX."moderators m
			LEFT JOIN ".TABLE_PREFIX."users u ON (m.uid=u.uid)
			WHERE fid='{$fid}'
		");
		while($moderator = $db->fetch_array($query))
		{
			$form_container->output_cell("<a href=\"index.php?module=user/users&amp;action=edit&amp;uid={$moderator['uid']}\">{$moderator['username']}</a>");
			$form_container->output_cell("<a href=\"index.php?module=forum/management&amp;action=editmod&amp;mid={$moderator['mid']}\">{$lang->edit}</a>", array("class" => "align_center"));
			$form_container->output_cell("<a href=\"index.php?module=forum/management&amp;action=deletemod&amp;uid={$moderator['uid']}&amp;fid={$fid}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_moderator_deletion}')\">{$lang->delete}</a>", array("class" => "align_center"));
			$form_container->construct_row();
		}
		
		if($form_container->num_rows() == 0)
		{
			$form_container->output_cell($lang->no_moderators, array('colspan' => 3));
			$form_container->construct_row();
		}
		
		$form_container->end();
		
		$buttons = array();
		$form = new Form("index.php?module=forum/management", "post", "management");
		echo $form->generate_hidden_field("fid", $mybb->input['fid']);
		echo $form->generate_hidden_field("add", "moderators");
		$form_container = new FormContainer($lang->add_moderator);
		$form_container->output_row($lang->username." <em>*</em>", $lang->moderator_username_desc, $form->generate_text_box('username', $mybb->input['username'], array('id' => 'username')), 'username');
		$form_container->end();
		
		// Autocompletion for usernames
		echo '
		<script type="text/javascript" src="../jscripts/autocomplete.js?ver=140"></script>
		<script type="text/javascript">
		<!--
			new autoComplete("username", "../xmlhttp.php?action=get_users", {valueSpan: "username"});
		// -->
		</script>';
		
		$buttons[] = $form->generate_submit_button($lang->add_moderator);
		$form->output_submit_wrapper($buttons);
		$form->end();
		
		echo "</div>\n";
	}
	
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
			$forums_by_parent[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
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
			$forum['name'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forum['name']);

			if($forum['active'] == 0)
			{
				$forum['name'] = "<em>".$forum['name']."</em>";
			}
			
			if($forum['type'] == "c" && ($depth == 1 || $depth == 2))
			{
				$sub_forums = '';
				if(isset($forums_by_parent[$forum['fid']]) && $depth == 2)
				{
					build_admincp_forums_list($form_container, $forum['fid'], $depth+1);
				}
				if($sub_forums)
				{
					$sub_forums = "<br /><small>{$lang->sub_forums}: {$sub_forums}</small>";
				}
				
				$form_container->output_cell("<div style=\"padding-left: ".(40*($depth-1))."px;\"><a href=\"index.php?module=forum/management&amp;fid={$forum['fid']}\"><strong>{$forum['name']}</strong></a>{$sub_forums}</div>");

				$form_container->output_cell("<input type=\"text\" name=\"disporder[".$forum['fid']."]\" value=\"".$forum['disporder']."\" class=\"text_input align_center\" style=\"width: 80%; font-weight: bold;\" />", array("class" => "align_center"));
				
				$popup = new PopupMenu("forum_{$forum['fid']}", $lang->options);
				$popup->add_item($lang->edit_forum, "index.php?module=forum/management&amp;action=edit&amp;fid={$forum['fid']}");
				$popup->add_item($lang->subforums, "index.php?module=forum/management&amp;fid={$forum['fid']}");
				$popup->add_item($lang->moderators, "index.php?module=forum/management&amp;fid={$forum['fid']}#tab_moderators");
				$popup->add_item($lang->permissions, "index.php?module=forum/management&amp;fid={$forum['fid']}#tab_permissions");
				$popup->add_item($lang->add_child_forum, "index.php?module=forum/management&amp;action=add&amp;pid={$forum['fid']}");
				$popup->add_item($lang->copy_forum, "index.php?module=forum/management&amp;action=copy&amp;fid={$forum['fid']}");
				$popup->add_item($lang->delete_forum, "index.php?module=forum/management&amp;action=delete&amp;fid={$forum['fid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_forum_deletion}')");
				
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
					$forum['description'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forum['description']);
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
					
				$form_container->output_cell("<div style=\"padding-left: ".(40*($depth-1))."px;\"><a href=\"index.php?module=forum/management&amp;fid={$forum['fid']}\">{$forum['name']}</a>{$forum['description']}{$sub_forums}</div>");
					
				$form_container->output_cell("<input type=\"text\" name=\"disporder[".$forum['fid']."]\" value=\"".$forum['disporder']."\" class=\"text_input align_center\" style=\"width: 80%;\" />", array("class" => "align_center"));
					
				$popup = new PopupMenu("forum_{$forum['fid']}", $lang->options);
				$popup->add_item($lang->edit_forum, "index.php?module=forum/management&amp;action=edit&amp;fid={$forum['fid']}");
				$popup->add_item($lang->subforums, "index.php?module=forum/management&amp;fid={$forum['fid']}");
				$popup->add_item($lang->moderators, "index.php?module=forum/management&amp;fid={$forum['fid']}#tab_moderators");
				$popup->add_item($lang->permissions, "index.php?module=forum/management&amp;fid={$forum['fid']}#tab_permissions");
				$popup->add_item($lang->add_child_forum, "index.php?module=forum/management&amp;action=add&amp;pid={$forum['fid']}");
				$popup->add_item($lang->copy_forum, "index.php?module=forum/management&amp;action=copy&amp;fid={$forum['fid']}");
				$popup->add_item($lang->delete_forum, "index.php?module=forum/management&amp;action=delete&amp;fid={$forum['fid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_forum_deletion}')");
				
				$form_container->output_cell($popup->fetch(), array("class" => "align_center"));
				
				$form_container->construct_row();
				
				if(isset($forums_by_parent[$forum['fid']]) && $depth == 1)
				{
					build_admincp_forums_list($form_container, $forum['fid'], $depth+1);
				}
			}
			else if($depth == 3)
			{
				if($donecount < $mybb->settings['subforumsindex'])
				{
					$sub_forums .= "{$comma} <a href=\"index.php?module=forum/management&amp;fid={$forum['fid']}\">{$forum['name']}</a>";
					$comma = ', ';
				}
	
				// Have we reached our max visible subforums? put a nice message and break out of the loop
				++$donecount;
				if($donecount == $mybb->settings['subforumsindex'])
				{
					if(subforums_count($forums_by_parent[$pid]) > $donecount)
					{
						$sub_forums .= $comma.$lang->sprintf($lang->more_subforums, (subforums_count($forums_by_parent[$pid]) - $donecount));
						return;
					}
				}
			}
		}
	}
}
?>
