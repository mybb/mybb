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

$page->add_breadcrumb_item($lang->attachment_types, "index.php?module=config-attachment_types");

$plugins->run_hooks("admin_config_attachment_types_begin");

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_config_attachment_types_add");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['mimetype']) && !trim($mybb->input['extension']))
		{
			$errors[] = $lang->error_missing_mime_type;
		}

		if(!trim($mybb->input['extension']) && !trim($mybb->input['mimetype']))
		{
			$errors[] = $lang->error_missing_extension;
		}

		if(!$errors)
		{
			if($mybb->input['mimetype'] == "images/attachtypes/")
			{
				$mybb->input['mimetype'] = '';
			}

			if(substr($mybb->input['extension'], 0, 1) == '.')
			{
				$mybb->input['extension'] = substr($mybb->input['extension'], 1);
			}

			foreach(array('groups', 'forums') as $key)
			{
				if($mybb->input[$key] == 'all')
				{
					$mybb->input[$key] = -1;
				}
				elseif($mybb->input[$key] == 'custom')
				{
					if(isset($mybb->input['select'][$key]) && is_array($mybb->input['select'][$key]))
					{
						foreach($mybb->input['select'][$key] as &$val)
						{
							$val = (int)$val;
						}
						unset($val);

						$mybb->input[$key] = implode(',', (array)$mybb->input['select'][$key]);
					}
					else
					{
						$mybb->input[$key] = '';
					}
				}
				else
				{
					$mybb->input[$key] = '';
				}
			}

			$maxsize = $mybb->get_input('maxsize', MyBB::INPUT_INT);

			if($maxsize == 0)
			{
				$maxsize = "";
			}

			$new_type = array(
				"name" => $db->escape_string($mybb->input['name']),
				"mimetype" => $db->escape_string($mybb->input['mimetype']),
				"extension" => $db->escape_string($mybb->input['extension']),
				"maxsize" => $maxsize,
				"icon" => $db->escape_string($mybb->input['icon']),
				'enabled' => $mybb->get_input('enabled', MyBB::INPUT_INT),
				'groups' => $db->escape_string($mybb->get_input('groups')),
				'forums' => $db->escape_string($mybb->get_input('forums')),
				'avatarfile' => $mybb->get_input('avatarfile', MyBB::INPUT_INT)
			);

			$atid = $db->insert_query("attachtypes", $new_type);

			$plugins->run_hooks("admin_config_attachment_types_add_commit");

			// Log admin action
			log_admin_action($atid, $mybb->input['extension']);

			$cache->update_attachtypes();

			flash_message($lang->success_attachment_type_created, 'success');
			admin_redirect("index.php?module=config-attachment_types");
		}
	}

	$page->add_breadcrumb_item($lang->add_new_attachment_type);
	$page->output_header($lang->attachment_types." - ".$lang->add_new_attachment_type);

	$sub_tabs['attachment_types'] = array(
		'title' => $lang->attachment_types,
		'link' => "index.php?module=config-attachment_types"
	);

	$sub_tabs['add_attachment_type'] = array(
		'title' => $lang->add_new_attachment_type,
		'link' => "index.php?module=config-attachment_types&amp;action=add",
		'description' => $lang->add_attachment_type_desc
	);

	$page->output_nav_tabs($sub_tabs, 'add_attachment_type');

	$form = new Form("index.php?module=config-attachment_types&amp;action=add", "post", "add");

	if($errors)
	{
		switch($mybb->input['groups'])
		{
			case 'all':
				$mybb->input['groups'] = -1;
				break;
			case 'custom':
				$mybb->input['groups'] = implode(',', (array)$mybb->input['select']['groups']);
				break;
			default:
				$mybb->input['groups'] = '';
				break;
		}

		switch($mybb->input['forums'])
		{
			case 'all':
				$mybb->input['forums'] = -1;
				break;
			case 'custom':
				$mybb->input['forums'] = implode(',', (array)$mybb->input['select']['forums']);
				break;
			default:
				$mybb->input['forums'] = '';
				break;
		}

		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['maxsize'] = '1024';
		$mybb->input['icon'] = "images/attachtypes/";
	}

	if(empty($mybb->input['groups']))
	{
		$mybb->input['groups'] = '';
	}

	if(empty($mybb->input['forums']))
	{
		$mybb->input['forums'] = '';
	}

	// PHP settings
	$upload_max_filesize = @ini_get('upload_max_filesize');
	$post_max_size = @ini_get('post_max_size');
	$limit_string = '';
	if($upload_max_filesize || $post_max_size)
	{
		$limit_string = '<br /><br />'.$lang->limit_intro;
		if($upload_max_filesize)
		{
			$limit_string .= '<br />'.$lang->sprintf($lang->limit_upload_max_filesize, $upload_max_filesize);
		}
		if($post_max_size)
		{
			$limit_string .= '<br />'.$lang->sprintf($lang->limit_post_max_size, $post_max_size);
		}
	}

	$selected_values = '';
	if($mybb->input['groups'] != '' && $mybb->input['groups'] != -1)
	{
		$selected_values = explode(',', $mybb->get_input('groups'));

		foreach($selected_values as &$value)
		{
			$value = (int)$value;
		}
		unset($value);
	}

	$group_checked = array('all' => '', 'custom' => '', 'none' => '');
	if($mybb->input['groups'] == -1)
	{
		$group_checked['all'] = 'checked="checked"';
	}
	elseif($mybb->input['groups'] != '')
	{
		$group_checked['custom'] = 'checked="checked"';
	}
	else
	{
		$group_checked['none'] = 'checked="checked"';
	}

	print_selection_javascript();

	$groups_select_code = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups\" value=\"all\" {$group_checked['all']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_groups}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups\" value=\"custom\" {$group_checked['custom']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_groups}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"groups_forums_groups_custom\" class=\"groups_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->groups_colon}</small></td>
					<td>".$form->generate_group_select('select[groups][]', $selected_values, array('id' => 'groups', 'multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups\" value=\"none\" {$group_checked['none']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('groups');
	</script>";

	$selected_values = '';
	if($mybb->input['forums'] != '' && $mybb->input['forums'] != -1)
	{
		$selected_values = explode(',', $mybb->get_input('forums'));

		foreach($selected_values as &$value)
		{
			$value = (int)$value;
		}
		unset($value);
	}

	$forum_checked = array('all' => '', 'custom' => '', 'none' => '');
	if($mybb->input['forums'] == -1)
	{
		$forum_checked['all'] = 'checked="checked"';
	}
	elseif($mybb->input['forums'] != '')
	{
		$forum_checked['custom'] = 'checked="checked"';
	}
	else
	{
		$forum_checked['none'] = 'checked="checked"';
	}

	$forums_select_code = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"all\" {$forum_checked['all']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_forums}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"custom\" {$forum_checked['custom']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_forums}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"forums_forums_groups_custom\" class=\"forums_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->forums_colon}</small></td>
					<td>".$form->generate_forum_select('select[forums][]', $selected_values, array('id' => 'forums', 'multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"none\" {$forum_checked['none']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('forums');
	</script>";

	$form_container = new FormContainer($lang->add_new_attachment_type);
	$form_container->output_row($lang->name, $lang->name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->file_extension." <em>*</em>", $lang->file_extension_desc, $form->generate_text_box('extension', $mybb->input['extension'], array('id' => 'extension')), 'extension');
	$form_container->output_row($lang->mime_type." <em>*</em>", $lang->mime_type_desc, $form->generate_text_box('mimetype', $mybb->input['mimetype'], array('id' => 'mimetype')), 'mimetype');
	$form_container->output_row($lang->maximum_file_size, $lang->maximum_file_size_desc.$limit_string, $form->generate_numeric_field('maxsize', $mybb->input['maxsize'], array('id' => 'maxsize', 'min' => 0)), 'maxsize');
	$form_container->output_row($lang->attachment_icon, $lang->attachment_icon_desc, $form->generate_text_box('icon', $mybb->input['icon'], array('id' => 'icon')), 'icon');
	$form_container->output_row($lang->enabled, '', $form->generate_yes_no_radio('enabled', $mybb->input['enabled']), 'enabled');
	$form_container->output_row($lang->available_to_groups, '', $groups_select_code, '', array(), array('id' => 'row_groups'));
	$form_container->output_row($lang->available_in_forums, '', $forums_select_code, '', array(), array('id' => 'row_forums'));
	$form_container->output_row($lang->avatar_file, $lang->avatar_file_desc, $form->generate_yes_no_radio('avatarfile', $mybb->input['avatarfile']), 'avatarfile');

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_attachment_type);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("attachtypes", "*", "atid='".$mybb->get_input('atid', MyBB::INPUT_INT)."'");
	$attachment_type = $db->fetch_array($query);

	if(!$attachment_type['atid'])
	{
		flash_message($lang->error_invalid_attachment_type, 'error');
		admin_redirect("index.php?module=config-attachment_types");
	}

	$plugins->run_hooks("admin_config_attachment_types_edit");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['mimetype']) && !trim($mybb->input['extension']))
		{
			$errors[] = $lang->error_missing_mime_type;
		}

		if(!trim($mybb->input['extension']) && !trim($mybb->input['mimetype']))
		{
			$errors[] = $lang->error_missing_extension;
		}

		if(!$errors)
		{
			if($mybb->input['mimetype'] == "images/attachtypes/")
			{
				$mybb->input['mimetype'] = '';
			}

			if(substr($mybb->input['extension'], 0, 1) == '.')
			{
				$mybb->input['extension'] = substr($mybb->input['extension'], 1);
			}

			foreach(array('groups', 'forums') as $key)
			{
				if($mybb->input[$key] == 'all')
				{
					$mybb->input[$key] = -1;
				}
				elseif($mybb->input[$key] == 'custom')
				{
					if(isset($mybb->input['select'][$key]) && is_array($mybb->input['select'][$key]))
					{
						foreach($mybb->input['select'][$key] as &$val)
						{
							$val = (int)$val;
						}
						unset($val);

						$mybb->input[$key] = implode(',', (array)$mybb->input['select'][$key]);
					}
					else
					{
						$mybb->input[$key] = '';
					}
				}
				else
				{
					$mybb->input[$key] = '';
				}
			}

			$updated_type = array(
				"name" => $db->escape_string($mybb->input['name']),
				"mimetype" => $db->escape_string($mybb->input['mimetype']),
				"extension" => $db->escape_string($mybb->input['extension']),
				"maxsize" => $mybb->get_input('maxsize', MyBB::INPUT_INT),
				"icon" => $db->escape_string($mybb->input['icon']),
				'enabled' => $mybb->get_input('enabled', MyBB::INPUT_INT),
				'groups' => $db->escape_string($mybb->get_input('groups')),
				'forums' => $db->escape_string($mybb->get_input('forums')),
				'avatarfile' => $mybb->get_input('avatarfile', MyBB::INPUT_INT)
			);

			$plugins->run_hooks("admin_config_attachment_types_edit_commit");

			$db->update_query("attachtypes", $updated_type, "atid='{$attachment_type['atid']}'");

			// Log admin action
			log_admin_action($attachment_type['atid'], $mybb->input['extension']);

			$cache->update_attachtypes();

			flash_message($lang->success_attachment_type_updated, 'success');
			admin_redirect("index.php?module=config-attachment_types");
		}
	}

	$page->add_breadcrumb_item($lang->edit_attachment_type);
	$page->output_header($lang->attachment_types." - ".$lang->edit_attachment_type);

	$sub_tabs['edit_attachment_type'] = array(
		'title' => $lang->edit_attachment_type,
		'link' => "index.php?module=config-attachment_types&amp;action=edit&amp;atid={$attachment_type['atid']}",
		'description' => $lang->edit_attachment_type_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_attachment_type');

	$form = new Form("index.php?module=config-attachment_types&amp;action=edit&amp;atid={$attachment_type['atid']}", "post", "add");

	if($errors)
	{
		switch($mybb->input['groups'])
		{
			case 'all':
				$mybb->input['groups'] = -1;
				break;
			case 'custom':
				$mybb->input['groups'] = implode(',', (array)$mybb->input['select']['groups']);
				break;
			default:
				$mybb->input['groups'] = '';
				break;
		}

		switch($mybb->input['forums'])
		{
			case 'all':
				$mybb->input['forums'] = -1;
				break;
			case 'custom':
				$mybb->input['forums'] = implode(',', (array)$mybb->input['select']['forums']);
				break;
			default:
				$mybb->input['forums'] = '';
				break;
		}
	
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = array_merge($mybb->input, $attachment_type);
	}

	if(empty($mybb->input['groups']))
	{
		$mybb->input['groups'] = '';
	}

	if(empty($mybb->input['forums']))
	{
		$mybb->input['forums'] = '';
	}

	// PHP settings
	$upload_max_filesize = @ini_get('upload_max_filesize');
	$post_max_size = @ini_get('post_max_size');
	$limit_string = '';
	if($upload_max_filesize || $post_max_size)
	{
		$limit_string = '<br /><br />'.$lang->limit_intro;
		if($upload_max_filesize)
		{
			$limit_string .= '<br />'.$lang->sprintf($lang->limit_upload_max_filesize, $upload_max_filesize);
		}
		if($post_max_size)
		{
			$limit_string .= '<br />'.$lang->sprintf($lang->limit_post_max_size, $post_max_size);
		}
	}

	$selected_values = '';
	if($mybb->input['groups'] != '' && $mybb->input['groups'] != -1)
	{
		$selected_values = explode(',', $mybb->get_input('groups'));

		foreach($selected_values as &$value)
		{
			$value = (int)$value;
		}
		unset($value);
	}

	$group_checked = array('all' => '', 'custom' => '', 'none' => '');
	if($mybb->input['groups'] == -1)
	{
		$group_checked['all'] = 'checked="checked"';
	}
	elseif($mybb->input['groups'] != '')
	{
		$group_checked['custom'] = 'checked="checked"';
	}
	else
	{
		$group_checked['none'] = 'checked="checked"';
	}

	print_selection_javascript();

	$groups_select_code = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups\" value=\"all\" {$group_checked['all']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_groups}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups\" value=\"custom\" {$group_checked['custom']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_groups}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"groups_forums_groups_custom\" class=\"groups_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->groups_colon}</small></td>
					<td>".$form->generate_group_select('select[groups][]', $selected_values, array('id' => 'groups', 'multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups\" value=\"none\" {$group_checked['none']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('groups');
	</script>";

	$selected_values = '';
	if($mybb->input['forums'] != '' && $mybb->input['forums'] != -1)
	{
		$selected_values = explode(',', $mybb->get_input('forums'));

		foreach($selected_values as &$value)
		{
			$value = (int)$value;
		}
		unset($value);
	}

	$forum_checked = array('all' => '', 'custom' => '', 'none' => '');
	if($mybb->input['forums'] == -1)
	{
		$forum_checked['all'] = 'checked="checked"';
	}
	elseif($mybb->input['forums'] != '')
	{
		$forum_checked['custom'] = 'checked="checked"';
	}
	else
	{
		$forum_checked['none'] = 'checked="checked"';
	}

	$forums_select_code = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"all\" {$forum_checked['all']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_forums}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"custom\" {$forum_checked['custom']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_forums}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"forums_forums_groups_custom\" class=\"forums_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->forums_colon}</small></td>
					<td>".$form->generate_forum_select('select[forums][]', $selected_values, array('id' => 'forums', 'multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"none\" {$forum_checked['none']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('forums');
	</script>";

	$form_container = new FormContainer($lang->edit_attachment_type);
	$form_container->output_row($lang->name, $lang->name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->file_extension." <em>*</em>", $lang->file_extension_desc, $form->generate_text_box('extension', $mybb->input['extension'], array('id' => 'extension')), 'extension');
	$form_container->output_row($lang->mime_type." <em>*</em>", $lang->mime_type_desc, $form->generate_text_box('mimetype', $mybb->input['mimetype'], array('id' => 'mimetype')), 'mimetype');
	$form_container->output_row($lang->maximum_file_size, $lang->maximum_file_size_desc.$limit_string, $form->generate_numeric_field('maxsize', $mybb->input['maxsize'], array('id' => 'maxsize', 'min' => 0)), 'maxsize');
	$form_container->output_row($lang->attachment_icon, $lang->attachment_icon_desc, $form->generate_text_box('icon', $mybb->input['icon'], array('id' => 'icon')), 'icon');
	$form_container->output_row($lang->enabled, '', $form->generate_yes_no_radio('enabled', $mybb->input['enabled']), 'enabled');
	$form_container->output_row($lang->available_to_groups, '', $groups_select_code, '', array(), array('id' => 'row_groups'));
	$form_container->output_row($lang->available_in_forums, '', $forums_select_code, '', array(), array('id' => 'row_forums'));
	$form_container->output_row($lang->avatar_file, $lang->avatar_file_desc, $form->generate_yes_no_radio('avatarfile', $mybb->input['avatarfile']), 'avatarfile');

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_attachment_type);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=config-attachment_types");
	}

	$query = $db->simple_select("attachtypes", "*", "atid='".$mybb->get_input('atid', MyBB::INPUT_INT)."'");
	$attachment_type = $db->fetch_array($query);

	if(!$attachment_type['atid'])
	{
		flash_message($lang->error_invalid_attachment_type, 'error');
		admin_redirect("index.php?module=config-attachment_types");
	}

	$plugins->run_hooks("admin_config_attachment_types_delete");

	if($mybb->request_method == "post")
	{
		$db->delete_query("attachtypes", "atid='{$attachment_type['atid']}'");

		$plugins->run_hooks("admin_config_attachment_types_delete_commit");

		$cache->update_attachtypes();

		// Log admin action
		log_admin_action($attachment_type['atid'], $attachment_type['extension']);

		flash_message($lang->success_attachment_type_deleted, 'success');
		admin_redirect("index.php?module=config-attachment_types");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-attachment_types&amp;action=delete&amp;atid={$attachment_type['atid']}", $lang->confirm_attachment_type_deletion);
	}
}

if($mybb->input['action'] == 'toggle_status')
{
	if(!verify_post_check($mybb->input['my_post_key']))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect('index.php?module=config-attachment_types');
	}

	$atid = $mybb->get_input('atid', MyBB::INPUT_INT);

	$query = $db->simple_select('attachtypes', '*', "atid='{$atid}'");
	$attachment_type = $db->fetch_array($query);

	if(!$attachment_type['atid'])
	{
		flash_message($lang->error_invalid_mycode, 'error');
		admin_redirect('index.php?module=config-attachment_types');
	}

	$plugins->run_hooks('admin_config_attachment_types_toggle_status');

	$update_array = array('enabled' => 1);
	$phrase = $lang->success_activated_attachment_type;
	if($attachment_type['enabled'] == 1)
	{
		$update_array['enabled'] = 0;
		$phrase = $lang->success_activated_attachment_type;
	}

	$plugins->run_hooks('admin_config_attachment_types_toggle_status_commit');

	$db->update_query('attachtypes', $update_array, "atid='{$atid}'");

	$cache->update_attachtypes();

	// Log admin action
	log_admin_action($atid, $attachment_type['extension'], $update_array['enabled']);

	flash_message($phrase, 'success');
	admin_redirect('index.php?module=config-attachment_types');
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->attachment_types);

	$sub_tabs['attachment_types'] = array(
		'title' => $lang->attachment_types,
		'link' => "index.php?module=config-attachment_types",
		'description' => $lang->attachment_types_desc
	);
	$sub_tabs['add_attachment_type'] = array(
		'title' => $lang->add_new_attachment_type,
		'link' => "index.php?module=config-attachment_types&amp;action=add",
	);

	$plugins->run_hooks("admin_config_attachment_types_start");

	$page->output_nav_tabs($sub_tabs, 'attachment_types');

	$table = new Table;
	$table->construct_header($lang->extension, array("colspan" => 2));
	$table->construct_header($lang->mime_type);
	$table->construct_header($lang->alt_enabled, array('class' => 'align_center'));
	$table->construct_header($lang->maximum_size, array("class" => "align_center"));
	$table->construct_header($lang->controls, array("class" => "align_center"));

	$query = $db->simple_select("attachtypes", "*", "", array('order_by' => 'extension'));
	while($attachment_type = $db->fetch_array($query))
	{
		// Just show default icons in ACP
		$attachment_type['icon'] = htmlspecialchars_uni(str_replace("{theme}", "images", $attachment_type['icon']));
		if(my_validate_url($attachment_type['icon'], true))
		{
			$image = $attachment_type['icon'];
		}
		else
		{
			$image = "../".$attachment_type['icon'];
		}

		if(!$attachment_type['icon'] || $attachment_type['icon'] == "images/attachtypes/")
		{
			$attachment_type['icon'] = "&nbsp;";
		}
		else
		{
			$attachment_type['name'] = htmlspecialchars_uni($attachment_type['name']);
			$attachment_type['icon'] = "<img src=\"{$image}\" title=\"{$attachment_type['name']}\" alt=\"\" />";
		}

		if($attachment_type['enabled'])
		{
			$phrase = $lang->disable;
			$icon = "on.png\" alt=\"({$lang->alt_enabled})\" title=\"{$lang->alt_enabled}";
		}
		else
		{
			$phrase = $lang->enable;
			$icon = "off.png\" alt=\"({$lang->alt_disabled})\" title=\"{$lang->alt_disabled}";
		}

		$attachment_type['extension'] = htmlspecialchars_uni($attachment_type['extension']);

		$table->construct_cell($attachment_type['icon'], array("width" => 1));
		$table->construct_cell("<strong>.{$attachment_type['extension']}</strong>");
		$table->construct_cell(htmlspecialchars_uni($attachment_type['mimetype']));
		$table->construct_cell("<img src=\"styles/{$page->style}/images/icons/bullet_{$icon}\" style=\"vertical-align: middle;\" />", array("class" => "align_center"));
		$table->construct_cell(get_friendly_size(($attachment_type['maxsize']*1024)), array("class" => "align_center"));

		$popup = new PopupMenu("attachment_type_{$attachment_type['atid']}", $lang->options);
		$popup->add_item($lang->edit, "index.php?module=config-attachment_types&amp;action=edit&amp;atid={$attachment_type['atid']}");
		$popup->add_item($phrase, "index.php?module=config-attachment_types&amp;action=toggle_status&amp;atid={$attachment_type['atid']}&amp;my_post_key={$mybb->post_code}");
		$popup->add_item($lang->delete, "index.php?module=config-attachment_types&amp;action=delete&amp;atid={$attachment_type['atid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_attachment_type_deletion}')");
		$table->construct_cell($popup->fetch(), array('class' => 'align_center'));

		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_attachment_types, array('colspan' => 6));
		$table->construct_row();
	}

	$table->output($lang->attachment_types);

	$page->output_footer();
}

