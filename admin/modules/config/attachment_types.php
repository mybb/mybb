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
				"icon" => $db->escape_string($mybb->input['icon'])
			);

			$atid = $db->insert_query("attachtypes", $new_type);

			$plugins->run_hooks("admin_config_attachment_types_add_commit");

			// Log admin action
			log_admin_action($atid, htmlspecialchars_uni($mybb->input['extension']));

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
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['maxsize'] = '1024';
		$mybb->input['icon'] = "images/attachtypes/";
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

	$form_container = new FormContainer($lang->add_new_attachment_type);
	$form_container->output_row($lang->name, $lang->name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->file_extension." <em>*</em>", $lang->file_extension_desc, $form->generate_text_box('extension', $mybb->input['extension'], array('id' => 'extension')), 'extension');
	$form_container->output_row($lang->mime_type." <em>*</em>", $lang->mime_type_desc, $form->generate_text_box('mimetype', $mybb->input['mimetype'], array('id' => 'mimetype')), 'mimetype');
	$form_container->output_row($lang->maximum_file_size, $lang->maximum_file_size_desc.$limit_string, $form->generate_numeric_field('maxsize', $mybb->input['maxsize'], array('id' => 'maxsize', 'min' => 0)), 'maxsize');
	$form_container->output_row($lang->attachment_icon, $lang->attachment_icon_desc, $form->generate_text_box('icon', $mybb->input['icon'], array('id' => 'icon')), 'icon');

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

			$updated_type = array(
				"name" => $db->escape_string($mybb->input['name']),
				"mimetype" => $db->escape_string($mybb->input['mimetype']),
				"extension" => $db->escape_string($mybb->input['extension']),
				"maxsize" => $mybb->get_input('maxsize', MyBB::INPUT_INT),
				"icon" => $db->escape_string($mybb->input['icon'])
			);

			$plugins->run_hooks("admin_config_attachment_types_edit_commit");

			$db->update_query("attachtypes", $updated_type, "atid='{$attachment_type['atid']}'");

			// Log admin action
			log_admin_action($attachment_type['atid'], htmlspecialchars_uni($mybb->input['extension']));

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
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = array_merge($mybb->input, $attachment_type);
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

	$form_container = new FormContainer($lang->edit_attachment_type);
	$form_container->output_row($lang->name, $lang->name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->file_extension." <em>*</em>", $lang->file_extension_desc, $form->generate_text_box('extension', $mybb->input['extension'], array('id' => 'extension')), 'extension');
	$form_container->output_row($lang->mime_type." <em>*</em>", $lang->mime_type_desc, $form->generate_text_box('mimetype', $mybb->input['mimetype'], array('id' => 'mimetype')), 'mimetype');
	$form_container->output_row($lang->maximum_file_size, $lang->maximum_file_size_desc.$limit_string, $form->generate_numeric_field('maxsize', $mybb->input['maxsize'], array('id' => 'maxsize', 'min' => 0)), 'maxsize');
	$form_container->output_row($lang->attachment_icon, $lang->attachment_icon_desc, $form->generate_text_box('icon', $mybb->input['icon'], array('id' => 'icon')), 'icon');

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
		log_admin_action($attachment_type['atid'], htmlspecialchars_uni($attachment_type['extension']));

		flash_message($lang->success_attachment_type_deleted, 'success');
		admin_redirect("index.php?module=config-attachment_types");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-attachment_types&amp;action=delete&amp;atid={$attachment_type['atid']}", $lang->confirm_attachment_type_deletion);
	}
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
	$table->construct_header($lang->maximum_size, array("class" => "align_center"));
	$table->construct_header($lang->controls, array("class" => "align_center", "colspan" => 2));

	$query = $db->simple_select("attachtypes", "*", "", array('order_by' => 'extension'));
	while($attachment_type = $db->fetch_array($query))
	{
		// Just show default icons in ACP
		$attachment_type['icon'] = htmlspecialchars_uni(str_replace("{theme}", "images", $attachment_type['icon']));
		if(my_strpos($attachment_type['icon'], "p://") || substr($attachment_type['icon'], 0, 1) == "/")
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

		$table->construct_cell($attachment_type['icon'], array("width" => 1));
		$table->construct_cell("<strong>.{$attachment_type['extension']}</strong>");
		$table->construct_cell(htmlspecialchars_uni($attachment_type['mimetype']));
		$table->construct_cell(get_friendly_size(($attachment_type['maxsize']*1024)), array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=config-attachment_types&amp;action=edit&amp;atid={$attachment_type['atid']}\">{$lang->edit}</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=config-attachment_types&amp;action=delete&amp;atid={$attachment_type['atid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_attachment_type_deletion}')\">{$lang->delete}</a>", array("class" => "align_center"));
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

