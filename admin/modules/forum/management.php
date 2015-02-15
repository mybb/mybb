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

$page->add_breadcrumb_item($lang->forum_management, "index.php?module=forum-management");

if($mybb->input['action'] == "add" || $mybb->input['action'] == "edit" || $mybb->input['action'] == "copy" || $mybb->input['action'] == "permissions" || !$mybb->input['action'])
{
	if(!empty($mybb->input['fid']) && ($mybb->input['action'] == "management" || $mybb->input['action'] == "edit" || $mybb->input['action'] == "copy" || !$mybb->input['action']))
	{
		$sub_tabs['view_forum'] = array(
			'title' => $lang->view_forum,
			'link' => "index.php?module=forum-management&amp;fid=".$mybb->input['fid'],
			'description' => $lang->view_forum_desc
		);

		$sub_tabs['add_child_forum'] = array(
			'title' => $lang->add_child_forum,
			'link' => "index.php?module=forum-management&amp;action=add&amp;pid=".$mybb->input['fid'],
			'description' => $lang->view_forum_desc
		);

		$sub_tabs['edit_forum_settings'] = array(
			'title' => $lang->edit_forum_settings,
			'link' => "index.php?module=forum-management&amp;action=edit&amp;fid=".$mybb->input['fid'],
			'description' => $lang->edit_forum_settings_desc
		);

		$sub_tabs['copy_forum'] = array(
			'title' => $lang->copy_forum,
			'link' => "index.php?module=forum-management&amp;action=copy&amp;fid=".$mybb->input['fid'],
			'description' => $lang->copy_forum_desc
		);
	}
	else
	{
		$sub_tabs['forum_management'] = array(
			'title' => $lang->forum_management,
			'link' => "index.php?module=forum-management",
			'description' => $lang->forum_management_desc
		);

		$sub_tabs['add_forum'] = array(
			'title' => $lang->add_forum,
			'link' => "index.php?module=forum-management&amp;action=add",
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
		$from = $mybb->get_input('from', MyBB::INPUT_INT);
		$to = $mybb->get_input('to', MyBB::INPUT_INT);

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
				$new_forum['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
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
					$groups[] = (int)$gid;
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

			$plugins->run_hooks("admin_forum_management_copy_commit");

			$cache->update_forums();
			$cache->update_forumpermissions();

			flash_message($lang->success_forum_copied, 'success');
			admin_redirect("index.php?module=forum-management&action=edit&fid={$to}");
		}
	}

	$page->add_breadcrumb_item($lang->copy_forum);
	$page->output_header($lang->copy_forum);
	$page->output_nav_tabs($sub_tabs, 'copy_forum');

	$form = new Form("index.php?module=forum-management&amp;action=copy", "post");

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
			$copy_data['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
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
		$usergroups[$usergroup['gid']] = htmlspecialchars_uni($usergroup['title']);
	}

	$form_container = new FormContainer($lang->copy_forum);
	$form_container->output_row($lang->source_forum." <em>*</em>", $lang->source_forum_desc, $form->generate_forum_select('from', $copy_data['from'], array('id' => 'from')), 'from');
	$form_container->output_row($lang->destination_forum." <em>*</em>", $lang->destination_forum_desc, $form->generate_forum_select('to', $copy_data['to'], array('id' => 'to', 'main_option' => $lang->copy_to_new_forum)), 'to');
	$form_container->output_row($lang->copy_settings_and_properties, $lang->copy_settings_and_properties_desc, $form->generate_yes_no_radio('copyforumsettings', $copy_data['copyforumsettings']));
	$form_container->output_row($lang->copy_user_group_permissions, $lang->copy_user_group_permissions_desc, $form->generate_select_box('copygroups[]', $usergroups, $mybb->input['copygroups'], array('id' => 'copygroups', 'multiple' => true, 'size' => 5)), 'copygroups');

	$form_container->end();

	$form_container = new FormContainer($lang->new_forum_settings);
	$form_container->output_row($lang->forum_type, $lang->forum_type_desc, $form->generate_radio_button('type', 'f', $lang->forum, $create_a_options_f)."<br />\n".$form->generate_radio_button('type', 'c', $lang->category, $create_a_options_c));
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
	$query = $db->simple_select("moderators", "*", "mid='".$mybb->get_input('mid', MyBB::INPUT_INT)."'");
	$mod_data = $db->fetch_array($query);

	if(!$mod_data['id'])
	{
		flash_message($lang->error_incorrect_moderator, 'error');
		admin_redirect("index.php?module=forum-management");
	}

	$plugins->run_hooks("admin_forum_management_editmod");

	if($mod_data['isgroup'])
	{
		$fieldname = "title";
	}
	else
	{
		$fieldname = "username";
	}

	if($mybb->request_method == "post")
	{
		$mid = $mybb->get_input('mid', MyBB::INPUT_INT);
		if(!$mid)
		{
			flash_message($lang->error_incorrect_moderator, 'error');
			admin_redirect("index.php?module=forum-management");
		}

		if(!$errors)
		{
			$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
			$forum = get_forum($fid);
			if($mod_data['isgroup'])
			{
				$mod = $groupscache[$mod_data['id']];
			}
			else
			{
				$mod = get_user($mod_data['id']);
			}
			$update_array = array(
				'fid' => (int)$fid,
				'caneditposts' => $mybb->get_input('caneditposts', MyBB::INPUT_INT),
				'cansoftdeleteposts' => $mybb->get_input('cansoftdeleteposts', MyBB::INPUT_INT),
				'canrestoreposts' => $mybb->get_input('canrestoreposts', MyBB::INPUT_INT),
				'candeleteposts' => $mybb->get_input('candeleteposts', MyBB::INPUT_INT),
				'cansoftdeletethreads' => $mybb->get_input('cansoftdeletethreads', MyBB::INPUT_INT),
				'canrestorethreads' => $mybb->get_input('canrestorethreads', MyBB::INPUT_INT),
				'candeletethreads' => $mybb->get_input('candeletethreads', MyBB::INPUT_INT),
				'canviewips' => $mybb->get_input('canviewips', MyBB::INPUT_INT),
				'canviewunapprove' => $mybb->get_input('canviewunapprove', MyBB::INPUT_INT),
				'canviewdeleted' => $mybb->get_input('canviewdeleted', MyBB::INPUT_INT),
				'canopenclosethreads' => $mybb->get_input('canopenclosethreads', MyBB::INPUT_INT),
				'canstickunstickthreads' => $mybb->get_input('canstickunstickthreads', MyBB::INPUT_INT),
				'canapproveunapprovethreads' => $mybb->get_input('canapproveunapprovethreads', MyBB::INPUT_INT),
				'canapproveunapproveposts' => $mybb->get_input('canapproveunapproveposts', MyBB::INPUT_INT),
				'canapproveunapproveattachs' => $mybb->get_input('canapproveunapproveattachs', MyBB::INPUT_INT),
				'canmanagethreads' => $mybb->get_input('canmanagethreads', MyBB::INPUT_INT),
				'canmanagepolls' => $mybb->get_input('canmanagepolls', MyBB::INPUT_INT),
				'canpostclosedthreads' => $mybb->get_input('canpostclosedthreads', MyBB::INPUT_INT),
				'canmovetononmodforum' => $mybb->get_input('canmovetononmodforum', MyBB::INPUT_INT),
				'canusecustomtools' => $mybb->get_input('canusecustomtools', MyBB::INPUT_INT),
				'canmanageannouncements' => $mybb->get_input('canmanageannouncements', MyBB::INPUT_INT),
				'canmanagereportedposts' => $mybb->get_input('canmanagereportedposts', MyBB::INPUT_INT),
				'canviewmodlog' => $mybb->get_input('canviewmodlog', MyBB::INPUT_INT)
			);

			$plugins->run_hooks("admin_forum_management_editmod_commit");

			$db->update_query("moderators", $update_array, "mid='".$mybb->get_input('mid', MyBB::INPUT_INT)."'");

			$cache->update_moderators();

			// Log admin action
			log_admin_action($fid, $forum['name'], $mid, $mod[$fieldname]);

			flash_message($lang->success_moderator_updated, 'success');
			admin_redirect("index.php?module=forum-management&fid=".$mybb->get_input('fid', MyBB::INPUT_INT)."#tab_moderators");
		}
	}

	if($mod_data['isgroup'])
	{
		$query = $db->simple_select("usergroups", "title", "gid='{$mod_data['id']}'");
		$mod_data[$fieldname] = $db->fetch_field($query, 'title');
	}
	else
	{
		$query = $db->simple_select("users", "username", "uid='{$mod_data['id']}'");
		$mod_data[$fieldname] = $db->fetch_field($query, 'username');
	}

	$sub_tabs = array();

	$sub_tabs['edit_mod'] = array(
		'title' => $lang->edit_mod,
		'link' => "index.php?module=forum-management&amp;action=editmod&amp;mid=".$mybb->input['mid'],
		'description' => $lang->edit_mod_desc
	);

	$page->add_breadcrumb_item($lang->forum_moderators, "index.php?module=forum-management&amp;fid={$mod_data['fid']}#tab_moderators");
	$page->add_breadcrumb_item($lang->edit_forum);
	$page->output_header($lang->edit_mod);
	$page->output_nav_tabs($sub_tabs, 'edit_mod');

	$form = new Form("index.php?module=forum-management&amp;action=editmod", "post");
	echo $form->generate_hidden_field("mid", $mod_data['mid']);

	if($errors)
	{
		$page->output_inline_error($errors);
		$mod_data = $mybb->input;
	}

	$form_container = new FormContainer($lang->sprintf($lang->edit_mod_for, $mod_data[$fieldname]));
	$form_container->output_row($lang->forum, $lang->forum_desc, $form->generate_forum_select('fid', $mod_data['fid'], array('id' => 'fid')), 'fid');

	$moderator_permissions = array(
		$form->generate_check_box('caneditposts', 1, $lang->can_edit_posts, array('checked' => $mod_data['caneditposts'], 'id' => 'caneditposts')),
		$form->generate_check_box('cansoftdeleteposts', 1, $lang->can_soft_delete_posts, array('checked' => $mod_data['cansoftdeleteposts'], 'id' => 'cansoftdeleteposts')),
		$form->generate_check_box('canrestoreposts', 1, $lang->can_restore_posts, array('checked' => $mod_data['canrestoreposts'], 'id' => 'canrestoreposts')),
		$form->generate_check_box('candeleteposts', 1, $lang->can_delete_posts, array('checked' => $mod_data['candeleteposts'], 'id' => 'candeleteposts')),
		$form->generate_check_box('cansoftdeletethreads', 1, $lang->can_soft_delete_threads, array('checked' => $mod_data['cansoftdeletethreads'], 'id' => 'cansoftdeletethreads')),
		$form->generate_check_box('canrestorethreads', 1, $lang->can_restore_threads, array('checked' => $mod_data['canrestorethreads'], 'id' => 'canrestorethreads')),
		$form->generate_check_box('candeletethreads', 1, $lang->can_delete_threads, array('checked' => $mod_data['candeletethreads'], 'id' => 'candeletethreads')),
		$form->generate_check_box('canviewips', 1, $lang->can_view_ips, array('checked' => $mod_data['canviewips'], 'id' => 'canviewips')),
		$form->generate_check_box('canviewunapprove', 1, $lang->can_view_unapprove, array('checked' => $mod_data['canviewunapprove'], 'id' => 'canviewunapprove')),
		$form->generate_check_box('canviewdeleted', 1, $lang->can_view_deleted, array('checked' => $mod_data['canviewdeleted'], 'id' => 'canviewdeleted')),
		$form->generate_check_box('canopenclosethreads', 1, $lang->can_open_close_threads, array('checked' => $mod_data['canopenclosethreads'], 'id' => 'canopenclosethreads')),
		$form->generate_check_box('canstickunstickthreads', 1, $lang->can_stick_unstick_threads, array('checked' => $mod_data['canstickunstickthreads'], 'id' => 'canstickunstickthreads')),
		$form->generate_check_box('canapproveunapprovethreads', 1, $lang->can_approve_unapprove_threads, array('checked' => $mod_data['canapproveunapprovethreads'], 'id' => 'canapproveunapprovethreads')),
		$form->generate_check_box('canapproveunapproveposts', 1, $lang->can_approve_unapprove_posts, array('checked' => $mod_data['canapproveunapproveposts'], 'id' => 'canapproveunapproveposts')),
		$form->generate_check_box('canapproveunapproveattachs', 1, $lang->can_approve_unapprove_attachments, array('checked' => $mod_data['canapproveunapproveattachs'], 'id' => 'canapproveunapproveattachs')),
		$form->generate_check_box('canmanagethreads', 1, $lang->can_manage_threads, array('checked' => $mod_data['canmanagethreads'], 'id' => 'canmanagethreads')),
		$form->generate_check_box('canmanagepolls', 1, $lang->can_manage_polls, array('checked' => $mod_data['canmanagepolls'], 'id' => 'canmanagepolls')),
		$form->generate_check_box('canpostclosedthreads', 1, $lang->can_post_closed_threads, array('checked' => $mod_data['canpostclosedthreads'], 'id' => 'canpostclosedthreads')),
		$form->generate_check_box('canmovetononmodforum', 1, $lang->can_move_to_other_forums, array('checked' => $mod_data['canmovetononmodforum'], 'id' => 'canmovetononmodforum')),
		$form->generate_check_box('canusecustomtools', 1, $lang->can_use_custom_tools, array('checked' => $mod_data['canusecustomtools'], 'id' => 'canusecustomtools'))
	);
	$form_container->output_row($lang->moderator_permissions, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $moderator_permissions)."</div>");

	$moderator_cp_permissions = array(
		$form->generate_check_box('canmanageannouncements', 1, $lang->can_manage_announcements, array('checked' => $mod_data['canmanageannouncements'], 'id' => 'canmanageannouncements')),
		$form->generate_check_box('canmanagereportedposts', 1, $lang->can_manage_reported_posts, array('checked' => $mod_data['canmanagereportedposts'], 'id' => 'canmanagereportedposts')),
		$form->generate_check_box('canviewmodlog', 1, $lang->can_view_mod_log, array('checked' => $mod_data['canviewmodlog'], 'id' => 'canviewmodlog'))
	);
	$form_container->output_row($lang->moderator_cp_permissions, $lang->moderator_cp_permissions_desc, "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $moderator_cp_permissions)."</div>");

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_mod);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "clear_permission")
{
	$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=forum-management&fid={$fid}");
	}

	$plugins->run_hooks("admin_forum_management_clear_permission");

	if($mybb->request_method == "post")
	{
		if((!$fid || !$gid) && $pid)
		{
			$query = $db->simple_select("forumpermissions", "fid, gid", "pid='{$pid}'");
			$result = $db->fetch_array($query);
			$fid = $result['fid'];
			$gid = $result['gid'];
		}

		if($pid)
		{
			$db->delete_query("forumpermissions", "pid='{$pid}'");
		}
		else
		{
			$db->delete_query("forumpermissions", "gid='{$gid}' AND fid='{$fid}'");
		}

		$plugins->run_hooks('admin_forum_management_clear_permission_commit');

		$cache->update_forumpermissions();

		flash_message($lang->success_custom_permission_cleared, 'success');
		admin_redirect("index.php?module=forum-management&fid={$fid}#tab_permissions");
	}
	else
	{
		$page->output_confirm_action("index.php?module=forum-management&amp;action=clear_permission&amp;pid={$pid}&amp;gid={$gid}&amp;fid={$fid}", $lang->confirm_clear_custom_permission);
	}
}

if($mybb->input['action'] == "permissions")
{
	$plugins->run_hooks("admin_forum_management_permissions");

	if($mybb->request_method == "post")
	{
		$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
		$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
		$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
		$forum = get_forum($fid);

		if((!$fid || !$gid) && $pid)
		{
			$query = $db->simple_select("forumpermissions", "fid, gid", "pid='{$pid}'");
			$result = $db->fetch_array($query);
			$fid = $result['fid'];
			$gid = $result['gid'];
			$forum = get_forum($fid);
		}

		$field_list = array();
		$fields_array = $db->show_fields_from("forumpermissions");
		if(is_array($mybb->input['permissions']))
		{
			// User has set permissions for this group...
			foreach($fields_array as $field)
			{
				if(strpos($field['Field'], 'can') !== false || strpos($field['Field'], 'mod') !== false)
				{
					if(array_key_exists($field['Field'], $mybb->input['permissions']))
					{
						$update_array[$db->escape_string($field['Field'])] = (int)$mybb->input['permissions'][$field['Field']];
					}
					else
					{
						$update_array[$db->escape_string($field['Field'])] = 0;
					}
				}
			}
		}
		else
		{
			// Else, we assume that the group has no permissions...
			foreach($fields_array as $field)
			{
				if(strpos($field['Field'], 'can') !== false || strpos($field['Field'], 'mod') !== false)
				{
					$update_array[$db->escape_string($field['Field'])] = 0;
				}
			}
		}

		if($fid && !$pid)
		{
			$update_array['fid'] = $fid;
			$update_array['gid'] = $mybb->get_input('gid', MyBB::INPUT_INT);
			$db->insert_query("forumpermissions", $update_array);
		}

		$plugins->run_hooks("admin_forum_management_permissions_commit");

		if(!($fid && !$pid))
		{
			$db->update_query("forumpermissions", $update_array, "pid='{$pid}'");
		}

		$cache->update_forumpermissions();

		// Log admin action
		log_admin_action($fid, $forum['name']);

		if($mybb->input['ajax'] == 1)
		{
			echo json_encode("<script type=\"text/javascript\">$('#row_{$gid}').html('".str_replace(array("'", "\t", "\n"), array("\\'", "", ""), retrieve_single_permissions_row($gid, $fid))."'); QuickPermEditor.init({$gid});</script>");
			die;
		}
		else
		{
			flash_message($lang->success_forum_permissions_saved, 'success');
			admin_redirect("index.php?module=forum-management&fid={$fid}#tab_permissions");
		}
	}

	if($mybb->input['ajax'] != 1)
	{
		$sub_tabs = array();

		if($mybb->input['fid'] && $mybb->input['gid'])
		{
			$sub_tabs['edit_permissions'] = array(
				'title' => $lang->forum_permissions,
				'link' => "index.php?module=forum-management&amp;action=permissions&amp;fid=".$mybb->input['fid']."&amp;gid=".$mybb->input['gid'],
				'description' => $lang->forum_permissions_desc
			);

			$page->add_breadcrumb_item($lang->forum_permissions2, "index.php?module=forum-management&amp;fid=".$mybb->input['fid']."#tab_permissions");
		}
		else
		{
			$query = $db->simple_select("forumpermissions", "fid", "pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'");
			$mybb->input['fid'] = $db->fetch_field($query, "fid");

			$sub_tabs['edit_permissions'] = array(
				'title' => $lang->forum_permissions,
				'link' => "index.php?module=forum-management&amp;action=permissions&amp;pid=".$mybb->get_input('pid', MyBB::INPUT_INT),
				'description' => $lang->forum_permissions_desc
			);

			$page->add_breadcrumb_item($lang->forum_permissions2, "index.php?module=forum-management&amp;fid=".$mybb->input['fid']."#tab_permissions");
		}

		$page->add_breadcrumb_item($lang->forum_permissions);
		$page->output_header($lang->forum_permissions);
		$page->output_nav_tabs($sub_tabs, 'edit_permissions');
	}
	else
	{
		echo "
		<div class=\"modal\" style=\"width: auto\">
		<script src=\"jscripts/tabs.js\" type=\"text/javascript\"></script>\n
		<script type=\"text/javascript\">
<!--
$(document).ready(function() {
	$(\"#modal_form\").on(\"click\", \"#savePermissions\", function(e) {
		e.preventDefault();
		
		var datastring = $(\"#modal_form\").serialize();
		$.ajax({
			type: \"POST\",
			url: $(\"#modal_form\").attr('action'),
			data: datastring,
			dataType: \"json\",
			success: function(data) {
				$(data).filter(\"script\").each(function(e) {
					eval($(this).text());
				});
				$.modal.close();
			},
			error: function(){
			}
		});
	});
});
// -->
		</script>
		<div style=\"overflow-y: auto; max-height: 400px\">";
	}

	if($mybb->input['pid'] || ($mybb->input['gid'] && $mybb->input['fid']))
	{
		if($mybb->input['ajax'] != 1)
		{
			$form = new Form("index.php?module=forum-management&amp;action=permissions", "post");
		}
		else
		{
			$form = new Form("index.php?module=forum-management&amp;action=permissions&amp;ajax=1&amp;pid=".$mybb->get_input('pid', MyBB::INPUT_INT)."&amp;gid=".$mybb->get_input('gid', MyBB::INPUT_INT)."&amp;fid=".$mybb->get_input('gid', MyBB::INPUT_INT), "post", "modal_form");
		}
		echo $form->generate_hidden_field("usecustom", "1");

		if($errors)
		{
			$page->output_inline_error($errors);
			$permission_data = $mybb->input;

			$query = $db->simple_select("usergroups", "*", "gid='{$permission_data['gid']}'");
			$usergroup = $db->fetch_array($query);

			$query = $db->simple_select("forums", "*", "fid='{$permission_data['fid']}'");
			$forum = $db->fetch_array($query);
		}
		else
		{
			$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
			$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
			$fid = $mybb->get_input('fid', MyBB::INPUT_INT);

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
			}
		}

		$groups = array(
			'canviewthreads' => 'viewing',
			'canview' => 'viewing',
			'canonlyviewownthreads' => 'viewing',
			'candlattachments' => 'viewing',

			'canpostthreads' => 'posting_rating',
			'canpostreplys' => 'posting_rating',
			'canonlyreplyownthreads' => 'posting_rating',
			'canpostattachments' => 'posting_rating',
			'canratethreads' => 'posting_rating',

			'caneditposts' => 'editing',
			'candeleteposts' => 'editing',
			'candeletethreads' => 'editing',
			'caneditattachments' => 'editing',

			'modposts' => 'moderate',
			'modthreads' => 'moderate',
			'modattachments' => 'moderate',
			'mod_edit_posts' => 'moderate',

			'canpostpolls' => 'polls',
			'canvotepolls' => 'polls',
			'cansearch' => 'misc',
		);

		$groups = $plugins->run_hooks("admin_forum_management_permission_groups", $groups);

		$tabs = array();
		foreach(array_unique(array_values($groups)) as $group)
		{
			$lang_group = "group_".$group;
			$tabs[$group] = $lang->$lang_group;
		}

		if($mybb->input['ajax'] == 1)
		{
			$page->output_tab_control($tabs, false, "tabs2");
		}
		else
		{
			$page->output_tab_control($tabs);
		}

		$field_list = array();
		$fields_array = $db->show_fields_from("forumpermissions");
		foreach($fields_array as $field)
		{
			if(strpos($field['Field'], 'can') !== false || strpos($field['Field'], 'mod') !== false)
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
			echo "<div id=\"tab_".$group."\">\n";
			$form_container = new FormContainer("\"".htmlspecialchars_uni($usergroup['title'])."\" {$lang->custom_permissions_for} \"".htmlspecialchars_uni($forum['name'])."\"");
			$fields = array();
			foreach($field_list[$group] as $field)
			{
				$lang_field = $group."_field_".$field;
				$fields[] = $form->generate_check_box("permissions[{$field}]", 1, $lang->$lang_field, array('checked' => $permission_data[$field], 'id' => $field));
			}
			$form_container->output_row("", "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $fields)."</div>");
			$form_container->end();
			echo "</div>";
		}

		if($mybb->input['ajax'] == 1)
		{
			$buttons[] = $form->generate_submit_button($lang->cancel, array('onclick' => '$.modal.close(); return false;'));
			$buttons[] = $form->generate_submit_button($lang->save_permissions, array('id' => 'savePermissions'));
			$form->output_submit_wrapper($buttons);
			$form->end();
			echo "</div>";
			echo "</div>";
		}
		else
		{
			$buttons[] = $form->generate_submit_button($lang->save_permissions);
			$form->output_submit_wrapper($buttons);

			$form->end();
		}
	}

	if($mybb->input['ajax'] != 1)
	{
		$page->output_footer();
	}
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

		$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
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
				"disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT),
				"active" => $mybb->get_input('active', MyBB::INPUT_INT),
				"open" => $mybb->get_input('open', MyBB::INPUT_INT),
				"allowhtml" => $mybb->get_input('allowhtml', MyBB::INPUT_INT),
				"allowmycode" => $mybb->get_input('allowmycode', MyBB::INPUT_INT),
				"allowsmilies" => $mybb->get_input('allowsmilies', MyBB::INPUT_INT),
				"allowimgcode" => $mybb->get_input('allowimgcode', MyBB::INPUT_INT),
				"allowvideocode" => $mybb->get_input('allowvideocode', MyBB::INPUT_INT),
				"allowpicons" => $mybb->get_input('allowpicons', MyBB::INPUT_INT),
				"allowtratings" => $mybb->get_input('allowtratings', MyBB::INPUT_INT),
				"usepostcounts" => $mybb->get_input('usepostcounts', MyBB::INPUT_INT),
				"usethreadcounts" => $mybb->get_input('usethreadcounts', MyBB::INPUT_INT),
				"requireprefix" => $mybb->get_input('requireprefix', MyBB::INPUT_INT),
				"password" => $db->escape_string($mybb->input['password']),
				"showinjump" => $mybb->get_input('showinjump', MyBB::INPUT_INT),
				"style" => $mybb->get_input('style', MyBB::INPUT_INT),
				"overridestyle" => $mybb->get_input('overridestyle', MyBB::INPUT_INT),
				"rulestype" => $mybb->get_input('rulestype', MyBB::INPUT_INT),
				"rulestitle" => $db->escape_string($mybb->input['rulestitle']),
				"rules" => $db->escape_string($mybb->input['rules']),
				"defaultdatecut" => $mybb->get_input('defaultdatecut', MyBB::INPUT_INT),
				"defaultsortby" => $db->escape_string($mybb->input['defaultsortby']),
				"defaultsortorder" => $db->escape_string($mybb->input['defaultsortorder']),
			);
			$fid = $db->insert_query("forums", $insert_array);

			$parentlist = make_parent_list($fid);
			$db->update_query("forums", array("parentlist" => $parentlist), "fid='$fid'");

			$inherit = $mybb->input['default_permissions'];

			foreach($mybb->input as $id => $permission)
			{
				if(strpos($id, 'fields_') === false)
				{
					continue;
				}

				list(, $gid) = explode('fields_', $id);

				// If it isn't an array then it came from the javascript form
				if(!is_array($permission))
				{
					$permission = explode(',', $permission);
					$permission = array_flip($permission);
					foreach($permission as $name => $value)
					{
						$permission[$name] = 1;
					}
				}

				foreach(array('canview','canpostthreads','canpostreplys','canpostpolls') as $name)
				{
					if(in_array($name, $permission)  || $permission[$name])
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
			$canpostpolls = $permissions['canpostpolls'];
			$canpostattachments = $permissions['canpostattachments'];
			$canpostreplies = $permissions['canpostreplys'];
			save_quick_perms($fid);

			$plugins->run_hooks("admin_forum_management_add_commit");

			$cache->update_forums();

			// Log admin action
			log_admin_action($fid, $insert_array['name']);

			flash_message($lang->success_forum_added, 'success');
			admin_redirect("index.php?module=forum-management");
		}
	}

	$page->extra_header .=  "<script src=\"jscripts/quick_perm_editor.js\" type=\"text/javascript\"></script>\n";

	$page->add_breadcrumb_item($lang->add_forum);
	$page->output_header($lang->add_forum);
	$page->output_nav_tabs($sub_tabs, 'add_forum');

	$form = new Form("index.php?module=forum-management&amp;action=add", "post");

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
			$forum_data['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
		}
		$forum_data['disporder'] = "1";
		$forum_data['linkto'] = "";
		$forum_data['password'] = "";
		$forum_data['active'] = 1;
		$forum_data['open'] = 1;
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
		$forum_data['allowvideocode'] = 1;
		$forum_data['allowpicons'] = 1;
		$forum_data['allowtratings'] = 1;
		$forum_data['showinjump'] = 1;
		$forum_data['usepostcounts'] = 1;
		$forum_data['usethreadcounts'] = 1;
		$forum_data['requireprefix'] = 0;
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
	$form_container->output_row($lang->forum_type, $lang->forum_type_desc, $form->generate_radio_button('type', 'f', $lang->forum, $create_a_options_f)."<br />\n".$form->generate_radio_button('type', 'c', $lang->category, $create_a_options_c));
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $forum_data['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->description, "", $form->generate_text_area('description', $forum_data['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->parent_forum." <em>*</em>", $lang->parent_forum_desc, $form->generate_forum_select('pid', $forum_data['pid'], array('id' => 'pid', 'main_option' => $lang->none)), 'pid');
	$form_container->output_row($lang->display_order, "", $form->generate_numeric_field('disporder', $forum_data['disporder'], array('id' => 'disporder', 'min' => 0)), 'disporder');
	$form_container->end();

	echo "<div id=\"additional_options_link\"><strong><a href=\"#\" onclick=\"$('#additional_options_link').toggle(); $('#additional_options').fadeToggle('fast'); return false;\">{$lang->show_additional_options}</a></strong><br /><br /></div>";
	echo "<div id=\"additional_options\" style=\"display: none;\">";
	$form_container = new FormContainer("<div class=\"float_right\" style=\"font-weight: normal;\"><a href=\"#\" onclick=\"$('#additional_options_link').toggle(); $('#additional_options').fadeToggle('fast'); return false;\">{$lang->hide_additional_options}</a></div>".$lang->additional_forum_options);
	$form_container->output_row($lang->forum_link, $lang->forum_link_desc, $form->generate_text_box('linkto', $forum_data['linkto'], array('id' => 'linkto')), 'linkto');
	$form_container->output_row($lang->forum_password, $lang->forum_password_desc, $form->generate_text_box('password', $forum_data['password'], array('id' => 'password')), 'password');

	$access_options = array(
		$form->generate_check_box('active', 1, $lang->forum_is_active."<br />\n<small>{$lang->forum_is_active_desc}</small>", array('checked' => $forum_data['active'], 'id' => 'active')),
		$form->generate_check_box('open', 1, $lang->forum_is_open."<br />\n<small>{$lang->forum_is_open_desc}</small>", array('checked' => $forum_data['open'], 'id' => 'open'))
	);

	$form_container->output_row($lang->access_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $access_options)."</div>");

	$styles = array(
		'0' => $lang->use_default
	);

	$query = $db->simple_select("themes", "tid,name", "name!='((master))' AND name!='((master-backup))'", array('order_by' => 'name'));
	while($style = $db->fetch_array($query))
	{
		$styles[$style['tid']] = htmlspecialchars_uni($style['name']);
	}

	$style_options = array(
		$form->generate_check_box('overridestyle', 1, $lang->override_user_style, array('checked' => $forum_data['overridestyle'], 'id' => 'overridestyle')),
		$lang->forum_specific_style."<br />\n".$form->generate_select_box('style', $styles, $forum_data['style'], array('id' => 'style'))
	);

	$form_container->output_row($lang->style_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $style_options)."</div>");

	$display_methods = array(
		'0' => $lang->dont_display_rules,
		'1' => $lang->display_rules_inline,
		'3' => $lang->display_rules_inline_new,
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
		$form->generate_check_box('allowvideocode', 1, $lang->allow_video_code, array('checked' => $forum_data['allowvideocode'], 'id' => 'allowvideocode')),
		$form->generate_check_box('allowpicons', 1, $lang->allow_post_icons, array('checked' => $forum_data['allowpicons'], 'id' => 'allowpicons')),
		$form->generate_check_box('allowtratings', 1, $lang->allow_thread_ratings, array('checked' => $forum_data['allowtratings'], 'id' => 'allowtratings')),
		$form->generate_check_box('showinjump', 1, $lang->show_forum_jump, array('checked' => $forum_data['showinjump'], 'id' => 'showinjump')),
		$form->generate_check_box('usepostcounts', 1, $lang->use_postcounts, array('checked' => $forum_data['usepostcounts'], 'id' => 'usepostcounts')),
		$form->generate_check_box('usethreadcounts', 1, $lang->use_threadcounts, array('checked' => $forum_data['usethreadcounts'], 'id' => 'usethreadcounts')),
		$form->generate_check_box('requireprefix', 1, $lang->require_thread_prefix, array('checked' => $forum_data['requireprefix'], 'id' => 'requireprefix'))
	);

	$form_container->output_row($lang->misc_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $misc_options)."</div>");
	$form_container->end();
	echo "</div>";

	$query = $db->simple_select("usergroups", "*", "", array("order" => "name"));
	while($usergroup = $db->fetch_array($query))
	{
		$usergroups[$usergroup['gid']] = $usergroup;
	}

	$cached_forum_perms = $cache->read("forumpermissions");
	$field_list = array(
		'canview' => $lang->permissions_canview,
		'canpostthreads' => $lang->permissions_canpostthreads,
		'canpostreplys' => $lang->permissions_canpostreplys,
		'canpostpolls' => $lang->permissions_canpostpolls,
	);

	$field_list2 = array(
		'canview' => $lang->perm_drag_canview,
		'canpostthreads' => $lang->perm_drag_canpostthreads,
		'canpostreplys' => $lang->perm_drag_canpostreplys,
		'canpostpolls' => $lang->perm_drag_canpostpolls,
	);

	$ids = array();

	$form_container = new FormContainer($lang->forum_permissions);
	$form_container->output_row_header($lang->permissions_group, array("class" => "align_center", 'style' => 'width: 40%'));
	$form_container->output_row_header($lang->overview_allowed_actions, array("class" => "align_center"));
	$form_container->output_row_header($lang->overview_disallowed_actions, array("class" => "align_center"));

	if($mybb->request_method == "post")
	{
		foreach($usergroups as $usergroup)
		{
			if(isset($mybb->input['fields_'.$usergroup['gid']]))
			{
				$input_permissions = $mybb->input['fields_'.$usergroup['gid']];
				if(!is_array($input_permissions))
				{
					// Convering the comma separated list from Javascript form into a variable
					$input_permissions = explode(',' , $input_permissions);
				}
				foreach($input_permissions as $input_permission)
				{
					$mybb->input['permissions'][$usergroup['gid']][$input_permission] = 1;
				}
			}
		}
	}

	foreach($usergroups as $usergroup)
	{
		$perms = array();
		if(isset($mybb->input['default_permissions']) && $mybb->input['default_permissions'][$usergroup['gid']])
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

		foreach($field_list as $forum_permission => $forum_perm_title)
		{
			if(isset($mybb->input['permissions']))
			{
				if($mybb->input['default_permissions'][$usergroup['gid']])
				{
					$default_checked = true;
				}
				else
				{
					$default_checked = false;
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
				if($perms[$forum_permission] == 1)
				{
					$perms_checked[$forum_permission] = 1;
				}
				else
				{
					$perms_checked[$forum_permission] = 0;
				}
			}
		}
		$usergroup['title'] = htmlspecialchars_uni($usergroup['title']);

		if($default_checked)
		{
			$inherited_text = $lang->inherited_permission;
		}
		else
		{
			$inherited_text = $lang->custom_permission;
		}

		$form_container->output_cell("<strong>{$usergroup['title']}</strong><br />".$form->generate_check_box("default_permissions[{$usergroup['gid']}]", 1, "", array("id" => "default_permissions_{$usergroup['gid']}", "checked" => $default_checked, "onclick" => $default_click))." <small><label for=\"default_permissions_{$usergroup['gid']}\">{$lang->permissions_use_group_default}</label></small>");

		$field_select = "<div class=\"quick_perm_fields\">\n";
		$field_select .= "<div class=\"enabled\"><ul id=\"fields_enabled_{$usergroup['gid']}\">\n";
		foreach($perms_checked as $perm => $value)
		{
			if($value == 1)
			{
				$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
			}
		}
		$field_select .= "</ul></div>\n";
		$field_select .= "<div class=\"disabled\"><ul id=\"fields_disabled_{$usergroup['gid']}\">\n";
		foreach($perms_checked as $perm => $value)
		{
			if($value == 0)
			{
				$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
			}
		}
		$field_select .= "</ul></div></div>\n";
		$field_select .= $form->generate_hidden_field("fields_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, '1')), array('id' => 'fields_'.$usergroup['gid']));
		$field_select = str_replace("'", "\\'", $field_select);
		$field_select = str_replace("\n", "", $field_select);

		$field_select = "<script type=\"text/javascript\">
//<![CDATA[
document.write('".str_replace("/", "\/", $field_select)."');
//]]>
</script>\n";

		$field_selected = array();
		foreach($field_list as $forum_permission => $permission_title)
		{
			$field_options[$forum_permission] = $permission_title;
			if($perms_checked[$forum_permission])
			{
				$field_selected[] = $forum_permission;
			}
		}

		$field_select .= "<noscript>".$form->generate_select_box('fields_'.$usergroup['gid'].'[]', $field_options, $field_selected, array('id' => 'fields_'.$usergroup['gid'].'[]', 'multiple' => true))."</noscript>\n";
		$form_container->output_cell($field_select, array('colspan' => 2));

		$form_container->construct_row();

		$ids[] = $usergroup['gid'];
	}
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_forum);
	$form->output_submit_wrapper($buttons);
	$form->end();

	// Write in our JS based field selector
	echo "<script type=\"text/javascript\">\n<!--\n";
	foreach($ids as $id)
	{
		echo "$(function() { QuickPermEditor.init(".$id.") });\n";
	}
	echo "// -->\n</script>\n";

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	if(!$mybb->input['fid'])
	{
		flash_message($lang->error_invalid_fid, 'error');
		admin_redirect("index.php?module=forum-management");
	}

	$query = $db->simple_select("forums", "*", "fid='{$mybb->input['fid']}'");
	$forum_data = $db->fetch_array($query);
	if(!$forum_data)
	{
		flash_message($lang->error_invalid_fid, 'error');
		admin_redirect("index.php?module=forum-management");
	}

	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);

	$plugins->run_hooks("admin_forum_management_edit");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		$pid = $mybb->get_input('pid', MyBB::INPUT_INT);

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

		if(!empty($mybb->input['linkto']) && empty($forum_data['linkto']))
		{
			$query = $db->simple_select('threads', 'COUNT(tid) as num_threads', "fid = '{$fid}'", array("limit" => 1));
			if($db->fetch_field($query, "num_threads") > 0)
			{
				$errors[] = $lang->error_forum_link_not_empty;
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
				"disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT),
				"active" => $mybb->get_input('active', MyBB::INPUT_INT),
				"open" => $mybb->get_input('open', MyBB::INPUT_INT),
				"allowhtml" => $mybb->get_input('allowhtml', MyBB::INPUT_INT),
				"allowmycode" => $mybb->get_input('allowmycode', MyBB::INPUT_INT),
				"allowsmilies" => $mybb->get_input('allowsmilies', MyBB::INPUT_INT),
				"allowimgcode" => $mybb->get_input('allowimgcode', MyBB::INPUT_INT),
				"allowvideocode" => $mybb->get_input('allowvideocode', MyBB::INPUT_INT),
				"allowpicons" => $mybb->get_input('allowpicons', MyBB::INPUT_INT),
				"allowtratings" => $mybb->get_input('allowtratings', MyBB::INPUT_INT),
				"usepostcounts" => $mybb->get_input('usepostcounts', MyBB::INPUT_INT),
				"usethreadcounts" => $mybb->get_input('usethreadcounts', MyBB::INPUT_INT),
				"requireprefix" => $mybb->get_input('requireprefix', MyBB::INPUT_INT),
				"password" => $db->escape_string($mybb->input['password']),
				"showinjump" => $mybb->get_input('showinjump', MyBB::INPUT_INT),
				"style" => $mybb->get_input('style', MyBB::INPUT_INT),
				"overridestyle" => $mybb->get_input('overridestyle', MyBB::INPUT_INT),
				"rulestype" => $mybb->get_input('rulestype', MyBB::INPUT_INT),
				"rulestitle" => $db->escape_string($mybb->input['rulestitle']),
				"rules" => $db->escape_string($mybb->input['rules']),
				"defaultdatecut" => $mybb->get_input('defaultdatecut', MyBB::INPUT_INT),
				"defaultsortby" => $db->escape_string($mybb->input['defaultsortby']),
				"defaultsortorder" => $db->escape_string($mybb->input['defaultsortorder']),
			);
			$db->update_query("forums", $update_array, "fid='{$fid}'");
			if($pid != $forum_data['pid'])
			{
				// Update the parentlist of this forum.
				$db->update_query("forums", array("parentlist" => make_parent_list($fid)), "fid='{$fid}'");

				// Rebuild the parentlist of all of the subforums of this forum
				switch($db->type)
				{
					case "sqlite":
					case "pgsql":
						$query = $db->simple_select("forums", "fid", "','||parentlist||',' LIKE '%,$fid,%'");
						break;
					default:
						$query = $db->simple_select("forums", "fid", "CONCAT(',',parentlist,',') LIKE '%,$fid,%'");
				}

				while($child = $db->fetch_array($query))
				{
					$db->update_query("forums", array("parentlist" => make_parent_list($child['fid'])), "fid='{$child['fid']}'");
				}
			}

			$inherit = $mybb->input['default_permissions'];

			foreach($mybb->input as $id => $permission)
			{
				// Make sure we're only skipping inputs that don't start with "fields_" and aren't fields_default_ or fields_inherit_
				if(strpos($id, 'fields_') === false || (strpos($id, 'fields_default_') !== false || strpos($id, 'fields_inherit_') !== false))
				{
					continue;
				}

				list(, $gid) = explode('fields_', $id);

				if($mybb->input['fields_default_'.$gid] == $permission && $mybb->input['fields_inherit_'.$gid] == 1)
				{
					$inherit[$gid] = 1;
					continue;
				}
				$inherit[$gid] = 0;

				// If it isn't an array then it came from the javascript form
				if(!is_array($permission))
				{
					$permission = explode(',', $permission);
					$permission = array_flip($permission);
					foreach($permission as $name => $value)
					{
						$permission[$name] = 1;
					}
				}

				foreach(array('canview','canpostthreads','canpostreplys','canpostpolls') as $name)
				{
					if(in_array($name, $permission) || $permission[$name])
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
			admin_redirect("index.php?module=forum-management&fid={$fid}");
		}
	}

	$page->extra_header .=  "<script src=\"jscripts/quick_perm_editor.js\" type=\"text/javascript\"></script>\n";

	$page->add_breadcrumb_item($lang->edit_forum);
	$page->output_header($lang->edit_forum);

	$page->output_nav_tabs($sub_tabs, 'edit_forum_settings');

	$form = new Form("index.php?module=forum-management&amp;action=edit", "post");
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
	$form_container->output_row($lang->forum_type, $lang->forum_type_desc, $form->generate_radio_button('type', 'f', $lang->forum, $create_a_options_f)."<br />\n".$form->generate_radio_button('type', 'c', $lang->category, $create_a_options_c));
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $forum_data['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->description, "", $form->generate_text_area('description', $forum_data['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->parent_forum." <em>*</em>", $lang->parent_forum_desc, $form->generate_forum_select('pid', $forum_data['pid'], array('id' => 'pid', 'main_option' => $lang->none)), 'pid');
	$form_container->output_row($lang->display_order, "", $form->generate_numeric_field('disporder', $forum_data['disporder'], array('id' => 'disporder', 'min' => 0)), 'disporder');
	$form_container->end();

	$form_container = new FormContainer($lang->additional_forum_options);
	$form_container->output_row($lang->forum_link, $lang->forum_link_desc, $form->generate_text_box('linkto', $forum_data['linkto'], array('id' => 'linkto')), 'linkto');
	$form_container->output_row($lang->forum_password, $lang->forum_password_desc, $form->generate_text_box('password', $forum_data['password'], array('id' => 'password')), 'password');

	$access_options = array(
		$form->generate_check_box('active', 1, $lang->forum_is_active."<br />\n<small>{$lang->forum_is_active_desc}</small>", array('checked' => $forum_data['active'], 'id' => 'active')),
		$form->generate_check_box('open', 1, $lang->forum_is_open."<br />\n<small>{$lang->forum_is_open_desc}</small>", array('checked' => $forum_data['open'], 'id' => 'open'))
	);

	$form_container->output_row($lang->access_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $access_options)."</div>");

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
		'3' => $lang->display_rules_inline_new,
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
		$form->generate_check_box('allowvideocode', 1, $lang->allow_video_code, array('checked' => $forum_data['allowvideocode'], 'id' => 'allowvideocode')),
		$form->generate_check_box('allowpicons', 1, $lang->allow_post_icons, array('checked' => $forum_data['allowpicons'], 'id' => 'allowpicons')),
		$form->generate_check_box('allowtratings', 1, $lang->allow_thread_ratings, array('checked' => $forum_data['allowtratings'], 'id' => 'allowtratings')),
		$form->generate_check_box('showinjump', 1, $lang->show_forum_jump, array('checked' => $forum_data['showinjump'], 'id' => 'showinjump')),
		$form->generate_check_box('usepostcounts', 1, $lang->use_postcounts, array('checked' => $forum_data['usepostcounts'], 'id' => 'usepostcounts')),
		$form->generate_check_box('usethreadcounts', 1, $lang->use_threadcounts, array('checked' => $forum_data['usethreadcounts'], 'id' => 'usethreadcounts')),
		$form->generate_check_box('requireprefix', 1, $lang->require_thread_prefix, array('checked' => $forum_data['requireprefix'], 'id' => 'requireprefix'))
	);

	$form_container->output_row($lang->misc_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $misc_options)."</div>");
	$form_container->end();

	$cached_forum_perms = $cache->read("forumpermissions");
	$field_list = array(
		'canview' => $lang->permissions_canview,
		'canpostthreads' => $lang->permissions_canpostthreads,
		'canpostreplys' => $lang->permissions_canpostreplys,
		'canpostpolls' => $lang->permissions_canpostpolls,
	);

	$field_list2 = array(
		'canview' => $lang->perm_drag_canview,
		'canpostthreads' => $lang->perm_drag_canpostthreads,
		'canpostreplys' => $lang->perm_drag_canpostreplys,
		'canpostpolls' => $lang->perm_drag_canpostpolls,
	);

	$ids = array();

	$form_container = new FormContainer($lang->sprintf($lang->forum_permissions_in, $forum_data['name']));
	$form_container->output_row_header($lang->permissions_group, array("class" => "align_center", 'style' => 'width: 30%'));
	$form_container->output_row_header($lang->overview_allowed_actions, array("class" => "align_center"));
	$form_container->output_row_header($lang->overview_disallowed_actions, array("class" => "align_center"));
	$form_container->output_row_header($lang->controls, array("class" => "align_center", 'style' => 'width: 120px', 'colspan' => 2));

	if($mybb->request_method == "post")
	{
		foreach($usergroups as $usergroup)
		{
			if(isset($mybb->input['fields_'.$usergroup['gid']]))
			{
				$input_permissions = $mybb->input['fields_'.$usergroup['gid']];
				if(!is_array($input_permissions))
				{
					// Convering the comma separated list from Javascript form into a variable
					$input_permissions = explode(',' , $input_permissions);
				}
				foreach($input_permissions as $input_permission)
				{
					$mybb->input['permissions'][$usergroup['gid']][$input_permission] = 1;
				}
			}
		}
	}

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

		foreach($field_list as $forum_permission => $forum_perm_title)
		{
			if(isset($mybb->input['permissions']))
			{
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
				if($perms[$forum_permission] == 1)
				{
					$perms_checked[$forum_permission] = 1;
				}
				else
				{
					$perms_checked[$forum_permission] = 0;
				}
			}
		}
		$usergroup['title'] = htmlspecialchars_uni($usergroup['title']);

		if($default_checked)
		{
			$inherited_text = $lang->inherited_permission;
		}
		else
		{
			$inherited_text = $lang->custom_permission;
		}

		$form_container->output_cell("<strong>{$usergroup['title']}</strong> <small style=\"vertical-align: middle;\">({$inherited_text})</small>");

		$field_select = "<div class=\"quick_perm_fields\">\n";
		$field_select .= "<div class=\"enabled\"><div class=\"fields_title\">{$lang->enabled}</div><ul id=\"fields_enabled_{$usergroup['gid']}\">\n";
		foreach($perms_checked as $perm => $value)
		{
			if($value == 1)
			{
				$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
			}
		}
		$field_select .= "</ul></div>\n";
		$field_select .= "<div class=\"disabled\"><div class=\"fields_title\">{$lang->disabled}</div><ul id=\"fields_disabled_{$usergroup['gid']}\">\n";
		foreach($perms_checked as $perm => $value)
		{
			if($value == 0)
			{
				$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
			}
		}
		$field_select .= "</ul></div></div>\n";
		$field_select .= $form->generate_hidden_field("fields_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, '1')), array('id' => 'fields_'.$usergroup['gid']));
		$field_select .= $form->generate_hidden_field("fields_inherit_".$usergroup['gid'], (int)$default_checked, array('id' => 'fields_inherit_'.$usergroup['gid']));
		$field_select .= $form->generate_hidden_field("fields_default_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, '1')), array('id' => 'fields_default_'.$usergroup['gid']));
		$field_select = str_replace("'", "\\'", $field_select);
		$field_select = str_replace("\n", "", $field_select);

		$field_select = "<script type=\"text/javascript\">
//<![CDATA[
document.write('".str_replace("/", "\/", $field_select)."');
//]]>
</script>\n";

		$field_selected = array();
		foreach($field_list as $forum_permission => $permission_title)
		{
			$field_options[$forum_permission] = $permission_title;
			if($perms_checked[$forum_permission])
			{
				$field_selected[] = $forum_permission;
			}
		}

		$field_select .= "<noscript>".$form->generate_select_box('fields_'.$usergroup['gid'].'[]', $field_options, $field_selected, array('id' => 'fields_'.$usergroup['gid'].'[]', 'multiple' => true))."</noscript>\n";
		$form_container->output_cell($field_select, array('colspan' => 2));
		
		if(!$default_checked)
		{
			$form_container->output_cell("<a href=\"index.php?module=forum-management&amp;action=permissions&amp;pid={$perms['pid']}\" onclick=\"MyBB.popupWindow('index.php?module=forum-management&action=permissions&pid={$perms['pid']}&ajax=1', null, true); return false;\">{$lang->edit_permissions}</a>", array("class" => "align_center"));
			$form_container->output_cell("<a href=\"index.php?module=forum-management&amp;action=clear_permission&amp;pid={$perms['pid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_clear_custom_permission}')\">{$lang->clear_custom_perms}</a>", array("class" => "align_center"));
		}
		else
		{
			$form_container->output_cell("<a href=\"index.php?module=forum-management&amp;action=permissions&amp;gid={$usergroup['gid']}&amp;fid={$fid}\" onclick=\"MyBB.popupWindow('index.php?module=forum-management&action=permissions&gid={$usergroup['gid']}&fid={$fid}&ajax=1', null, true); return false;\">{$lang->set_custom_perms}</a>", array("class" => "align_center", "colspan" => 2));
		}
		
		$form_container->construct_row(array('id' => 'row_'.$usergroup['gid']));

		$ids[] = $usergroup['gid'];
	}
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_forum);
	$form->output_submit_wrapper($buttons);
	$form->end();

	// Write in our JS based field selector
	echo "<script type=\"text/javascript\">\n<!--\n";
	foreach($ids as $id)
	{
		echo "$(function() { QuickPermEditor.init(".$id."); });\n";
	}
	echo "// -->\n</script>\n";

	$page->output_footer();
}

if($mybb->input['action'] == "deletemod")
{
	$modid = $mybb->get_input('id', MyBB::INPUT_INT);
	$isgroup = $mybb->get_input('isgroup', MyBB::INPUT_INT);
	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);

	$query = $db->simple_select("moderators", "*", "id='{$modid}' AND isgroup = '{$isgroup}' AND fid='{$fid}'");
	$mod = $db->fetch_array($query);

	// Does the forum not exist?
	if(!$mod['mid'])
	{
		flash_message($lang->error_invalid_moderator, 'error');
		admin_redirect("index.php?module=forum-management&fid={$fid}");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=forum-management&fid={$fid}");
	}

	$plugins->run_hooks("admin_forum_management_deletemod");

	if($mybb->request_method == "post")
	{
		$mid = $mod['mid'];
		if($mybb->input['isgroup'])
		{
			$query = $db->query("
				SELECT m.*, g.title
				FROM ".TABLE_PREFIX."moderators m
				LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=m.id)
				WHERE m.mid='{$mid}'
			");
		}
		else
		{
			$query = $db->query("
				SELECT m.*, u.username, u.usergroup
				FROM ".TABLE_PREFIX."moderators m
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=m.id)
				WHERE m.mid='{$mid}'
			");
		}
		$mod = $db->fetch_array($query);

		$db->delete_query("moderators", "mid='{$mid}'");

		$plugins->run_hooks("admin_forum_management_deletemod_commit");

		$cache->update_moderators();

		$forum = get_forum($fid);

		// Log admin action
		if($isgroup)
		{
			log_admin_action($mid, $mod['title'], $forum['fid'], $forum['name']);
		}
		else
		{
			log_admin_action($mid, $mod['username'], $forum['fid'], $forum['name']);
		}

		flash_message($lang->success_moderator_deleted, 'success');
		admin_redirect("index.php?module=forum-management&fid={$fid}#tab_moderators");
	}
	else
	{
		$page->output_confirm_action("index.php?module=forum-management&amp;action=deletemod&amp;fid={$mod['fid']}&amp;uid={$mod['uid']}", $lang->confirm_moderator_deletion);
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
		admin_redirect("index.php?module=forum-management");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=forum-management");
	}

	$plugins->run_hooks("admin_forum_management_delete");

	if($mybb->request_method == "post")
	{
		$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
		$forum_info = get_forum($fid);

		$query = $db->simple_select("forums", "posts,unapprovedposts,threads,unapprovedthreads", "fid='{$fid}'");
		$stats = $db->fetch_array($query);

		// Delete the forum
		$db->delete_query("forums", "fid='$fid'");

		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$query = $db->simple_select("forums", "*", "','|| parentlist|| ',' LIKE '%,$fid,%'");
				break;
			default:
				$query = $db->simple_select("forums", "*", "CONCAT(',', parentlist, ',') LIKE '%,$fid,%'");
		}
		while($forum = $db->fetch_array($query))
		{
			$fids[$forum['fid']] = $fid;
			$delquery .= " OR fid='{$forum['fid']}'";

			$stats['posts'] += $forum['posts'];
			$stats['unapprovedposts'] += $forum['unapprovedposts'];
			$stats['threads'] += $forum['threads'];
			$stats['unapprovedthreads'] += $forum['unapprovedthreads'];
		}

		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$db->delete_query("forums", "','||parentlist||',' LIKE '%,$fid,%'");
				break;
			default:
				$db->delete_query("forums", "CONCAT(',',parentlist,',') LIKE '%,$fid,%'");
		}

		$db->delete_query("threads", "fid='{$fid}' {$delquery}");
		$db->delete_query("posts", "fid='{$fid}' {$delquery}");
		$db->delete_query("moderators", "fid='{$fid}' {$delquery}");
		$db->delete_query("forumsubscriptions", "fid='{$fid}' {$delquery}");
		$db->delete_query("forumpermissions", "fid='{$fid}' {$delquery}");

		$update_stats = array(
			'numthreads' => "-".$stats['threads'],
			'numunapprovedthreads' => "-".$stats['unapprovedthreads'],
			'numposts' => "-".$stats['posts'],
			'numunapprovedposts' => "-".$stats['unapprovedposts']
		);
		update_stats($update_stats);

		$plugins->run_hooks("admin_forum_management_delete_commit");

		$cache->update_forums();
		$cache->update_moderators();
		$cache->update_forumpermissions();

		// Log admin action
		log_admin_action($forum_info['fid'], $forum_info['name']);

		flash_message($lang->success_forum_deleted, 'success');
		admin_redirect("index.php?module=forum-management");
	}
	else
	{
		$page->output_confirm_action("index.php?module=forum-management&amp;action=delete&amp;fid={$forum['fid']}", $lang->confirm_forum_deletion);
	}
}

if(!$mybb->input['action'])
{
	if(!isset($mybb->input['fid']))
	{
		$mybb->input['fid'] = 0;
	}

	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
	if($fid)
	{
		$forum = get_forum($fid);
	}

	$plugins->run_hooks("admin_forum_management_start");

	if($mybb->request_method == "post")
	{
		if($mybb->input['update'] == "permissions")
		{
			$inherit = array();
			foreach($mybb->input as $id => $permission)
			{
				// Make sure we're only skipping inputs that don't start with "fields_" and aren't fields_default_ or fields_inherit_
				if(strpos($id, 'fields_') === false || (strpos($id, 'fields_default_') !== false || strpos($id, 'fields_inherit_') !== false))
				{
					continue;
				}

				list(, $gid) = explode('fields_', $id);

				if($mybb->input['fields_default_'.$gid] == $permission && $mybb->input['fields_inherit_'.$gid] == 1)
				{
					$inherit[$gid] = 1;
					continue;
				}
				$inherit[$gid] = 0;

				// If it isn't an array then it came from the javascript form
				if(!is_array($permission))
				{
					$permission = explode(',', $permission);
					$permission = array_flip($permission);
					foreach($permission as $name => $value)
					{
						$permission[$name] = 1;
					}
				}
				foreach(array('canview','canpostthreads','canpostreplys','canpostpolls') as $name)
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
			$canpostpolls = $permissions['canpostpolls'];
			$canpostattachments = $permissions['canpostattachments'];
			$canpostreplies = $permissions['canpostreplys'];

			save_quick_perms($fid);

			$plugins->run_hooks("admin_forum_management_start_permissions_commit");

			$cache->update_forums();

			// Log admin action
			log_admin_action('quickpermissions', $fid, $forum['name']);

			flash_message($lang->success_forum_permissions_updated, 'success');
			admin_redirect("index.php?module=forum-management&fid={$fid}#tab_permissions");
		}
		elseif($mybb->input['add'] == "moderators")
		{
			$forum = get_forum($fid);
			if(!$forum)
			{
				flash_message($lang->error_invalid_forum, 'error');
				admin_redirect("index.php?module=forum-management&fid={$fid}#tab_moderators");
			}
			if(!empty($mybb->input['usergroup']))
			{
				$isgroup = 1;
				$gid = $mybb->get_input('usergroup', MyBB::INPUT_INT);

				if(!$groupscache[$gid])
 				{
 					// Didn't select a valid moderator
 					flash_message($lang->error_moderator_not_found, 'error');
 					admin_redirect("index.php?module=forum-management&fid={$fid}#tab_moderators");
 				}

				$newmod = array(
					"id" => $gid,
					"name" => $groupscache[$gid]['title']
				);
			}
			else
			{
				$options = array(
					'fields' => array('uid AS id', 'username AS name')
				);
				$newmod = get_user_by_username($mybb->input['username'], $options);

				if(empty($newmod['id']))
				{
					flash_message($lang->error_moderator_not_found, 'error');
					admin_redirect("index.php?module=forum-management&fid={$fid}#tab_moderators");
				}

				$isgroup = 0;
			}

			if($newmod['id'])
			{
				$query = $db->simple_select("moderators", "id", "id='".$newmod['id']."' AND fid='".$fid."' AND isgroup='{$isgroup}'", array('limit' => 1));

				if(!$db->num_rows($query))
				{
					$new_mod = array(
						"fid" => $fid,
						"id" => $newmod['id'],
						"isgroup" => $isgroup,
						"caneditposts" => 1,
						"cansoftdeleteposts" => 1,
						"canrestoreposts" => 1,
						"candeleteposts" => 1,
						"cansoftdeletethreads" => 1,
						"canrestorethreads" => 1,
						"candeletethreads" => 1,
						"canviewips" => 1,
						"canviewunapprove" => 1,
						"canviewdeleted" => 1,
						"canopenclosethreads" => 1,
						"canstickunstickthreads" => 1,
						"canapproveunapprovethreads" => 1,
						"canapproveunapproveposts" => 1,
						"canapproveunapproveattachs" => 1,
						"canmanagethreads" => 1,
						"canmanagepolls" => 1,
						"canpostclosedthreads" => 1,
						"canmovetononmodforum" => 1,
						"canusecustomtools" => 1,
						"canmanageannouncements" => 1,
						"canmanagereportedposts" => 1,
						"canviewmodlog" => 1
					);

					$mid = $db->insert_query("moderators", $new_mod);

					if(!$isgroup)
					{
						$db->update_query("users", array('usergroup' => 6), "uid='{$newmod['id']}' AND usergroup='2'");
					}

					$plugins->run_hooks("admin_forum_management_start_moderators_commit");

					$cache->update_moderators();

					// Log admin action
					log_admin_action('addmod', $mid, $newmod['name'], $fid, $forum['name']);

					flash_message($lang->success_moderator_added, 'success');
					admin_redirect("index.php?module=forum-management&action=editmod&mid={$mid}");
				}
				else
				{
					flash_message($lang->error_moderator_already_added, 'error');
					admin_redirect("index.php?module=forum-management&fid={$fid}#tab_moderators");
				}
			}
			else
			{
				flash_message($lang->error_moderator_not_found, 'error');
				admin_redirect("index.php?module=forum-management&fid={$fid}#tab_moderators");
			}
		}
		else
		{
			if(!empty($mybb->input['disporder']) && is_array($mybb->input['disporder']))
			{
				foreach($mybb->input['disporder'] as $update_fid => $order)
				{
					$db->update_query("forums", array('disporder' => (int)$order), "fid='".(int)$update_fid."'");
				}

				$plugins->run_hooks("admin_forum_management_start_disporder_commit");

				$cache->update_forums();

				// Log admin action
				log_admin_action('orders', $forum['fid'], $forum['name']);

				flash_message($lang->success_forum_disporder_updated, 'success');
				admin_redirect("index.php?module=forum-management&fid=".$mybb->input['fid']);
			}
		}
	}

	$page->extra_header .=  "<script src=\"jscripts/quick_perm_editor.js\" type=\"text/javascript\"></script>\n";

	if($fid)
	{
		$page->add_breadcrumb_item($lang->view_forum, "index.php?module=forum-management");
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

	$form = new Form("index.php?module=forum-management", "post", "management");
	echo $form->generate_hidden_field("fid", $mybb->input['fid']);

	if($fid)
	{
		$tabs = array(
			'subforums' => $lang->subforums,
			'permissions' => $lang->forum_permissions,
			'moderators' => $lang->moderators,
		);
		$tabs = $plugins->run_hooks("admin_forum_management_start_graph_tabs", $tabs);
		$page->output_tab_control($tabs);

		echo "<div id=\"tab_subforums\">\n";
		if(!isset($forum_cache) || !is_array($forum_cache))
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

		$query = $db->simple_select("usergroups", "*", "", array("order" => "name"));
		while($usergroup = $db->fetch_array($query))
		{
			$usergroups[$usergroup['gid']] = $usergroup;
		}

		$query = $db->simple_select("forumpermissions", "*", "fid='{$fid}'");
		while($existing = $db->fetch_array($query))
		{
			$existing_permissions[$existing['gid']] = $existing;
		}

		$cached_forum_perms = $cache->read("forumpermissions");
		$field_list = array(
			'canview' => $lang->permissions_canview,
			'canpostthreads' => $lang->permissions_canpostthreads,
			'canpostreplys' => $lang->permissions_canpostreplys,
			'canpostpolls' => $lang->permissions_canpostpolls,
		);

		$field_list2 = array(
			'canview' => $lang->perm_drag_canview,
			'canpostthreads' => $lang->perm_drag_canpostthreads,
			'canpostreplys' => $lang->perm_drag_canpostreplys,
			'canpostpolls' => $lang->perm_drag_canpostpolls,
		);

		$ids = array();

		$form = new Form("index.php?module=forum-management", "post", "management");
		echo $form->generate_hidden_field("fid", $mybb->input['fid']);
		echo $form->generate_hidden_field("update", "permissions");

		echo "<div id=\"tab_permissions\">\n";

		$form_container = new FormContainer($lang->sprintf($lang->forum_permissions_in, $forum_cache[$fid]['name']));
		$form_container->output_row_header($lang->permissions_group, array("class" => "align_center", 'style' => 'width: 30%'));
		$form_container->output_row_header($lang->overview_allowed_actions, array("class" => "align_center"));
		$form_container->output_row_header($lang->overview_disallowed_actions, array("class" => "align_center"));
		$form_container->output_row_header($lang->controls, array("class" => "align_center", 'style' => 'width: 120px', 'colspan' => 2));
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
					elseif(is_array($cached_forum_perms) && $cached_forum_perms[$forum['fid']][$usergroup['gid']])
					{
						$perms = $cached_forum_perms[$forum['fid']][$usergroup['gid']];
						$default_checked = true;
					}
					else if(is_array($cached_forum_perms) && $cached_forum_perms[$forum['pid']][$usergroup['gid']])
					{
						$perms = $cached_forum_perms[$forum['pid']][$usergroup['gid']];
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
				if(isset($existing_permissions) && is_array($existing_permissions) && $existing_permissions[$usergroup['gid']])
				{
					$perms = $existing_permissions[$usergroup['gid']];
					$default_checked = false;
				}
				elseif(is_array($cached_forum_perms) && isset($cached_forum_perms[$forum['fid']]) && $cached_forum_perms[$forum['fid']][$usergroup['gid']])
				{
					$perms = $cached_forum_perms[$forum['fid']][$usergroup['gid']];
					$default_checked = true;
				}
				else if(is_array($cached_forum_perms) && $cached_forum_perms[$forum['pid']][$usergroup['gid']])
				{
					$perms = $cached_forum_perms[$forum['pid']][$usergroup['gid']];
					$default_checked = true;
				}

				if(!$perms)
				{
					$perms = $usergroup;
					$default_checked = true;
				}
			}
			foreach($field_list as $forum_permission => $forum_perm_title)
			{
				if(isset($mybb->input['permissions']))
				{
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
					if($perms[$forum_permission] == 1)
					{
						$perms_checked[$forum_permission] = 1;
					}
					else
					{
						$perms_checked[$forum_permission] = 0;
					}
				}
			}
			$usergroup['title'] = htmlspecialchars_uni($usergroup['title']);

			if($default_checked == 1)
			{
				$inherited_text = $lang->inherited_permission;
			}
			else
			{
				$inherited_text = $lang->custom_permission;
			}

			$form_container->output_cell("<strong>{$usergroup['title']}</strong> <small style=\"vertical-align: middle;\">({$inherited_text})</small>");

			$field_select = "<div class=\"quick_perm_fields\">\n";
			$field_select .= "<div class=\"enabled\"><ul id=\"fields_enabled_{$usergroup['gid']}\">\n";
			foreach($perms_checked as $perm => $value)
			{
				if($value == 1)
				{
					$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
				}
			}
			$field_select .= "</ul></div>\n";
			$field_select .= "<div class=\"disabled\"><ul id=\"fields_disabled_{$usergroup['gid']}\">\n";
			foreach($perms_checked as $perm => $value)
			{
				if($value == 0)
				{
					$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
				}
			}
			$field_select .= "</ul></div></div>\n";
			$field_select .= $form->generate_hidden_field("fields_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, '1')), array('id' => 'fields_'.$usergroup['gid']));
			$field_select .= $form->generate_hidden_field("fields_inherit_".$usergroup['gid'], (int)$default_checked, array('id' => 'fields_inherit_'.$usergroup['gid']));
			$field_select .= $form->generate_hidden_field("fields_default_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, '1')), array('id' => 'fields_default_'.$usergroup['gid']));
			$field_select = str_replace("'", "\\'", $field_select);
			$field_select = str_replace("\n", "", $field_select);

			$field_select = "<script type=\"text/javascript\">
//<![CDATA[
document.write('".str_replace("/", "\/", $field_select)."');
//]]>
</script>\n";

			$field_selected = array();
			foreach($field_list as $forum_permission => $permission_title)
			{
				$field_options[$forum_permission] = $permission_title;
				if($perms_checked[$forum_permission])
				{
					$field_selected[] = $forum_permission;
				}
			}

			$field_select .= "<noscript>".$form->generate_select_box('fields_'.$usergroup['gid'].'[]', $field_options, $field_selected, array('id' => 'fields_'.$usergroup['gid'].'[]', 'multiple' => true))."</noscript>\n";
			$form_container->output_cell($field_select, array('colspan' => 2));

			if(!$default_checked)
			{
				$form_container->output_cell("<a href=\"index.php?module=forum-management&amp;action=permissions&amp;pid={$perms['pid']}\" onclick=\"MyBB.popupWindow('index.php?module=forum-management&action=permissions&pid={$perms['pid']}&ajax=1', null, true); return false;\">{$lang->edit_permissions}</a>", array("class" => "align_center"));
				$form_container->output_cell("<a href=\"index.php?module=forum-management&amp;action=clear_permission&amp;pid={$perms['pid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_clear_custom_permission}')\">{$lang->clear_custom_perms}</a>", array("class" => "align_center"));
			}
			else
			{
				$form_container->output_cell("<a href=\"index.php?module=forum-management&amp;action=permissions&amp;gid={$usergroup['gid']}&amp;fid={$fid}\"  onclick=\"MyBB.popupWindow('index.php?module=forum-management&action=permissions&gid={$usergroup['gid']}&fid={$fid}&ajax=1', null, true); return false;\">{$lang->set_custom_perms}</a>", array("class" => "align_center", "colspan" => 2));
			}
			$form_container->construct_row(array('id' => 'row_'.$usergroup['gid']));

			$ids[] = $usergroup['gid'];
		}
		$form_container->end();

		$buttons = array();
		$buttons[] = $form->generate_submit_button($lang->update_forum_permissions);
		$buttons[] = $form->generate_reset_button($lang->reset);

		$form->output_submit_wrapper($buttons);

		// Write in our JS based field selector
		echo "<script type=\"text/javascript\">\n<!--\n";
		foreach($ids as $id)
		{
			echo "$(function() { QuickPermEditor.init(".$id.") });\n";
		}
		echo "// -->\n</script>\n";

		echo "</div>\n";
		$form->end();
		echo "<div id=\"tab_moderators\">\n";
		$form_container = new FormContainer($lang->sprintf($lang->moderators_assigned_to, $forum_cache[$fid]['name']));
		$form_container->output_row_header($lang->name, array('width' => '75%'));
		$form_container->output_row_header($lang->controls, array("class" => "align_center", 'style' => 'width: 200px', 'colspan' => 2));
		$query = $db->query("
			SELECT m.mid, m.id, m.isgroup, u.username, g.title
			FROM ".TABLE_PREFIX."moderators m
			LEFT JOIN ".TABLE_PREFIX."users u ON (m.isgroup='0' AND m.id=u.uid)
			LEFT JOIN ".TABLE_PREFIX."usergroups g ON (m.isgroup='1' AND m.id=g.gid)
			WHERE fid='{$fid}'
			ORDER BY m.isgroup DESC, u.username, g.title
		");
		while($moderator = $db->fetch_array($query))
		{
			if($moderator['isgroup'])
			{
				$moderator['img'] = "<img src=\"styles/{$page->style}/images/icons/group.png\" alt=\"{$lang->group}\" title=\"{$lang->group}\" />";
				$form_container->output_cell("{$moderator['img']} <a href=\"index.php?module=user-groups&amp;action=edit&amp;gid={$moderator['id']}\">".htmlspecialchars_uni($moderator['title'])." ({$lang->usergroup} {$moderator['id']})</a>");
				$form_container->output_cell("<a href=\"index.php?module=forum-management&amp;action=editmod&amp;mid={$moderator['mid']}\">{$lang->edit}</a>", array("class" => "align_center"));
				$form_container->output_cell("<a href=\"index.php?module=forum-management&amp;action=deletemod&amp;id={$moderator['id']}&amp;isgroup=1&amp;fid={$fid}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_moderator_deletion}')\">{$lang->delete}</a>", array("class" => "align_center"));
			}
			else
			{
				$moderator['img'] = "<img src=\"styles/{$page->style}/images/icons/user.png\" alt=\"{$lang->user}\" title=\"{$lang->user}\" />";
				$form_container->output_cell("{$moderator['img']} <a href=\"index.php?module=user-users&amp;action=edit&amp;uid={$moderator['id']}\">".htmlspecialchars_uni($moderator['username'])."</a>");
				$form_container->output_cell("<a href=\"index.php?module=forum-management&amp;action=editmod&amp;mid={$moderator['mid']}\">{$lang->edit}</a>", array("class" => "align_center"));
				$form_container->output_cell("<a href=\"index.php?module=forum-management&amp;action=deletemod&amp;id={$moderator['id']}&amp;isgroup=0&amp;fid={$fid}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_moderator_deletion}')\">{$lang->delete}</a>", array("class" => "align_center"));
			}
			$form_container->construct_row();
		}

		if($form_container->num_rows() == 0)
		{
			$form_container->output_cell($lang->no_moderators, array('colspan' => 3));
			$form_container->construct_row();
		}
		$form_container->end();

		// Users
		$buttons = array();
		$form = new Form("index.php?module=forum-management", "post", "management");
		echo $form->generate_hidden_field("fid", $mybb->input['fid']);
		echo $form->generate_hidden_field("add", "moderators");

		// Usergroup Moderator
		if(!is_array($usergroups))
		{
			$usergroups = $groupscache;
		}

		foreach($usergroups as $group)
		{
			$modgroups[$group['gid']] = $lang->usergroup." ".$group['gid'].": ".htmlspecialchars_uni($group['title']);
		}

		if(!isset($mybb->input['usergroup']))
		{
			$mybb->input['usergroup'] = '';
		}

		if(!isset($mybb->input['username']))
		{
			$mybb->input['username'] = '';
		}

		$form_container = new FormContainer($lang->add_usergroup_as_moderator);
		$form_container->output_row($lang->usergroup." <em>*</em>", $lang->moderator_usergroup_desc, $form->generate_select_box('usergroup', $modgroups, $mybb->input['usergroup'], array('id' => 'usergroup')), 'usergroup');
		$form_container->end();

		$buttons[] = $form->generate_submit_button($lang->add_usergroup_moderator);
		$form->output_submit_wrapper($buttons);
		$form->end();
		echo "<br />";

		$form = new Form("index.php?module=forum-management", "post", "management");
		echo $form->generate_hidden_field("fid", $mybb->input['fid']);
		echo $form->generate_hidden_field("add", "moderators");
		$form_container = new FormContainer($lang->add_user_as_moderator);
		$form_container->output_row($lang->username." <em>*</em>", $lang->moderator_username_desc, $form->generate_text_box('username', $mybb->input['username'], array('id' => 'username')), 'username');
		$form_container->end();

		// Autocompletion for usernames
		echo '
		<link rel="stylesheet" href="../jscripts/select2/select2.css">
		<script type="text/javascript" src="../jscripts/select2/select2.min.js?ver=1804"></script>
		<script type="text/javascript">
		<!--
		$("#username").select2({
			placeholder: "'.$lang->search_user.'",
			minimumInputLength: 3,
			maximumSelectionSize: 3,
			multiple: false,
			ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
				url: "../xmlhttp.php?action=get_users",
				dataType: \'json\',
				data: function (term, page) {
					return {
						query: term, // search term
					};
				},
				results: function (data, page) { // parse the results into the format expected by Select2.
					// since we are using custom formatting functions we do not need to alter remote JSON data
					return {results: data};
				}
			},
			initSelection: function(element, callback) {
				var query = $(element).val();
				if (query !== "") {
					$.ajax("../xmlhttp.php?action=get_users&getone=1", {
						data: {
							query: query
						},
						dataType: "json"
					}).done(function(data) { callback(data); });
				}
			},
		});

		$(\'[for=username]\').click(function(){
			$("#username").select2(\'open\');
			return false;
		});
		// -->
		</script>';

		$buttons = array($form->generate_submit_button($lang->add_user_moderator));
		$form->output_submit_wrapper($buttons);
		$form->end();

		echo "</div>\n";

		$plugins->run_hooks("admin_forum_management_start_graph");
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
			$forum['name'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forum['name']); // Fix & but allow unicode

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

				$form_container->output_cell("<div style=\"padding-left: ".(40*($depth-1))."px;\"><a href=\"index.php?module=forum-management&amp;fid={$forum['fid']}\"><strong>{$forum['name']}</strong></a>{$sub_forums}</div>");

				$form_container->output_cell("<input type=\"text\" name=\"disporder[".$forum['fid']."]\" value=\"".$forum['disporder']."\" class=\"text_input align_center\" style=\"width: 80%; font-weight: bold;\" />", array("class" => "align_center"));

				$popup = new PopupMenu("forum_{$forum['fid']}", $lang->options);
				$popup->add_item($lang->edit_forum, "index.php?module=forum-management&amp;action=edit&amp;fid={$forum['fid']}");
				$popup->add_item($lang->subforums, "index.php?module=forum-management&amp;fid={$forum['fid']}");
				$popup->add_item($lang->moderators, "index.php?module=forum-management&amp;fid={$forum['fid']}#tab_moderators");
				$popup->add_item($lang->permissions, "index.php?module=forum-management&amp;fid={$forum['fid']}#tab_permissions");
				$popup->add_item($lang->add_child_forum, "index.php?module=forum-management&amp;action=add&amp;pid={$forum['fid']}");
				$popup->add_item($lang->copy_forum, "index.php?module=forum-management&amp;action=copy&amp;fid={$forum['fid']}");
				$popup->add_item($lang->delete_forum, "index.php?module=forum-management&amp;action=delete&amp;fid={$forum['fid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_forum_deletion}')");

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

				$form_container->output_cell("<div style=\"padding-left: ".(40*($depth-1))."px;\"><a href=\"index.php?module=forum-management&amp;fid={$forum['fid']}\">{$forum['name']}</a>{$forum['description']}{$sub_forums}</div>");

				$form_container->output_cell("<input type=\"text\" name=\"disporder[".$forum['fid']."]\" value=\"".$forum['disporder']."\" class=\"text_input align_center\" style=\"width: 80%;\" />", array("class" => "align_center"));

				$popup = new PopupMenu("forum_{$forum['fid']}", $lang->options);
				$popup->add_item($lang->edit_forum, "index.php?module=forum-management&amp;action=edit&amp;fid={$forum['fid']}");
				$popup->add_item($lang->subforums, "index.php?module=forum-management&amp;fid={$forum['fid']}");
				$popup->add_item($lang->moderators, "index.php?module=forum-management&amp;fid={$forum['fid']}#tab_moderators");
				$popup->add_item($lang->permissions, "index.php?module=forum-management&amp;fid={$forum['fid']}#tab_permissions");
				$popup->add_item($lang->add_child_forum, "index.php?module=forum-management&amp;action=add&amp;pid={$forum['fid']}");
				$popup->add_item($lang->copy_forum, "index.php?module=forum-management&amp;action=copy&amp;fid={$forum['fid']}");
				$popup->add_item($lang->delete_forum, "index.php?module=forum-management&amp;action=delete&amp;fid={$forum['fid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_forum_deletion}')");

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
					$sub_forums .= "{$comma} <a href=\"index.php?module=forum-management&amp;fid={$forum['fid']}\">{$forum['name']}</a>";
					$comma = $lang->comma;
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

function retrieve_single_permissions_row($gid, $fid)
{
	global $mybb, $lang, $cache, $db;

	$query = $db->simple_select("usergroups", "*", "gid='{$gid}'");
	$usergroup = $db->fetch_array($query);

	$query = $db->simple_select("forums", "*", "fid='{$fid}'");
	$forum_data = $db->fetch_array($query);

	$query = $db->simple_select("forumpermissions", "*", "fid='{$fid}'");
	while($existing = $db->fetch_array($query))
	{
		$existing_permissions[$existing['gid']] = $existing;
	}

	$cached_forum_perms = $cache->read("forumpermissions");
	$field_list = array(
		'canview' => $lang->permissions_canview,
		'canpostthreads' => $lang->permissions_canpostthreads,
		'canpostreplys' => $lang->permissions_canpostreplys,
		'canpostpolls' => $lang->permissions_canpostpolls,
	);

	$field_list2 = array(
		'canview' => $lang->perm_drag_canview,
		'canpostthreads' => $lang->perm_drag_canpostthreads,
		'canpostreplys' => $lang->perm_drag_canpostreplys,
		'canpostpolls' => $lang->perm_drag_canpostpolls,
	);

	$form = new Form('', '', "", 0, "", true);
	$form_container = new FormContainer();

	$perms = array();

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

	foreach($field_list as $forum_permission => $forum_perm_title)
	{
		if($perms[$forum_permission] == 1)
		{
			$perms_checked[$forum_permission] = 1;
		}
		else
		{
			$perms_checked[$forum_permission] = 0;
		}
	}

	$usergroup['title'] = htmlspecialchars_uni($usergroup['title']);

	if($default_checked == 1)
	{
		$inherited_text = $lang->inherited_permission;
	}
	else
	{
		$inherited_text = $lang->custom_permission;
	}

	$form_container->output_cell("<strong>{$usergroup['title']}</strong> <small style=\"vertical-align: middle;\">({$inherited_text})</small>");

	$field_select = "<div class=\"quick_perm_fields\">\n";
	$field_select .= "<div class=\"enabled\"><div class=\"fields_title\">{$lang->enabled}</div><ul id=\"fields_enabled_{$usergroup['gid']}\">\n";
	foreach($perms_checked as $perm => $value)
	{
		if($value == 1)
		{
			$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
		}
	}
	$field_select .= "</ul></div>\n";
	$field_select .= "<div class=\"disabled\"><div class=\"fields_title\">{$lang->disabled}</div><ul id=\"fields_disabled_{$usergroup['gid']}\">\n";
	foreach($perms_checked as $perm => $value)
	{
		if($value == 0)
		{
			$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
		}
	}
	$field_select .= "</ul></div></div>\n";
	$field_select .= $form->generate_hidden_field("fields_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, 1)), array('id' => 'fields_'.$usergroup['gid']));
	$field_select = str_replace("\n", "", $field_select);

	foreach($field_list as $forum_permission => $permission_title)
	{
		$field_options[$forum_permission] = $permission_title;
	}
	$form_container->output_cell($field_select, array('colspan' => 2));

	if(!$default_checked)
	{
		$form_container->output_cell("<a href=\"index.php?module=forum-management&amp;action=permissions&amp;pid={$perms['pid']}\" onclick=\"MyBB.popupWindow('index.php?module=forum-management&action=permissions&pid={$perms['pid']}&ajax=1', null, true); return false;\">{$lang->edit_permissions}</a>", array("class" => "align_center"));
		$form_container->output_cell("<a href=\"index.php?module=forum-management&amp;action=clear_permission&amp;pid={$perms['pid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_clear_custom_permission}')\">{$lang->clear_custom_perms}</a>", array("class" => "align_center"));
	}
	else
	{
		$form_container->output_cell("<a href=\"index.php?module=forum-management&amp;action=permissions&amp;gid={$usergroup['gid']}&amp;fid={$fid}\"  onclick=\"MyBB.popupWindow('index.php?module=forum-management&action=permissions&gid={$usergroup['gid']}&fid={$fid}&ajax=1', null, true); return false;\">{$lang->set_custom_perms}</a>", array("class" => "align_center", "colspan" => 2));
	}
	$form_container->construct_row();
	return $form_container->output_row_cells(0, true);
}

