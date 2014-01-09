<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->custom_profile_fields, "index.php?module=config-profile_fields");

$plugins->run_hooks("admin_config_profile_fields_begin");

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_config_profile_fields_add");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = $lang->error_missing_description;
		}

		if(!trim($mybb->input['fieldtype']))
		{
			$errors[] = $lang->error_missing_fieldtype;
		}

		if(!$errors)
		{
			$type = $mybb->input['fieldtype'];
			$options = preg_replace("#(\r\n|\r|\n)#s", "\n", trim($mybb->input['options']));
			if($type != "text" && $type != "textarea")
			{
				$thing = "$type\n$options";
			}
			else
			{
				$thing = $type;
			}

			$new_profile_field = array(
				"name" => $db->escape_string($mybb->input['name']),
				"description" => $db->escape_string($mybb->input['description']),
				"disporder" => intval($mybb->input['disporder']),
				"type" => $db->escape_string($thing),
				"length" => intval($mybb->input['length']),
				"maxlength" => intval($mybb->input['maxlength']),
				"required" => $db->escape_string($mybb->input['required']),
				"editable" => $db->escape_string($mybb->input['editable']),
				"hidden" => $db->escape_string($mybb->input['hidden']),
				"postbit" => $db->escape_string($mybb->input['postbit']),
				"postnum" => intval($mybb->input['postnum'])
			);

			$fid = $db->insert_query("profilefields", $new_profile_field);

			$db->write_query("ALTER TABLE ".TABLE_PREFIX."userfields ADD fid{$fid} TEXT");

			$plugins->run_hooks("admin_config_profile_fields_add_commit");

			// Log admin action
			log_admin_action($fid, $mybb->input['name']);

			flash_message($lang->success_profile_field_added, 'success');
			admin_redirect("index.php?module=config-profile_fields");
		}
	}

	$page->add_breadcrumb_item($lang->add_new_profile_field);
	$page->output_header($lang->custom_profile_fields." - ".$lang->add_new_profile_field);

	$sub_tabs['custom_profile_fields'] = array(
		'title' => $lang->custom_profile_fields,
		'link' => "index.php?module=config-profile_fields"
	);

	$sub_tabs['add_profile_field'] = array(
		'title' => $lang->add_new_profile_field,
		'link' => "index.php?module=config-profile_fields&amp;action=add",
		'description' => $lang->add_new_profile_field_desc
	);

	$page->output_nav_tabs($sub_tabs, 'add_profile_field');
	$form = new Form("index.php?module=config-profile_fields&amp;action=add", "post", "add");

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['fieldtype'] = 'textbox';
		$mybb->input['required'] = 0;
		$mybb->input['editable'] = 1;
		$mybb->input['hidden'] = 0;
		$mybb->input['postbit'] = 0;
	}

	$form_container = new FormContainer($lang->add_new_profile_field);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$select_list = array(
		"text" => $lang->text,
		"textarea" => $lang->textarea,
		"select" => $lang->select,
		"multiselect" => $lang->multiselect,
		"radio" => $lang->radio,
		"checkbox" => $lang->checkbox
	);
	$form_container->output_row($lang->field_type." <em>*</em>", $lang->field_type_desc, $form->generate_select_box('fieldtype', $select_list, $mybb->input['fieldtype'], array('id' => 'fieldtype')), 'fieldtype');
	$form_container->output_row($lang->maximum_length, $lang->maximum_length_desc, $form->generate_text_box('maxlength', $mybb->input['maxlength'], array('id' => 'maxlength')), 'maxlength', array(), array('id' => 'row_maxlength'));
	$form_container->output_row($lang->field_length, $lang->field_length_desc, $form->generate_text_box('length', $mybb->input['length'], array('id' => 'length')), 'length', array(), array('id' => 'row_fieldlength'));
	$form_container->output_row($lang->selectable_options, $lang->selectable_options_desc, $form->generate_text_area('options', $mybb->input['options'], array('id' => 'options')), 'options', array(), array('id' => 'row_options'));
	$form_container->output_row($lang->display_order." <em>*</em>", $lang->display_order_desc, $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
	$form_container->output_row($lang->required." <em>*</em>", $lang->required_desc, $form->generate_yes_no_radio('required', $mybb->input['required']));
	$form_container->output_row($lang->editable_by_user." <em>*</em>", $lang->editable_by_user_desc, $form->generate_yes_no_radio('editable', $mybb->input['editable']));
	$form_container->output_row($lang->hide_on_profile." <em>*</em>", $lang->hide_on_profile_desc, $form->generate_yes_no_radio('hidden', $mybb->input['hidden']));
	$form_container->output_row($lang->display_on_postbit." <em>*</em>", $lang->display_on_postbit_desc, $form->generate_yes_no_radio('postbit', $mybb->input['postbit']));
	$form_container->output_row($lang->min_posts_enabled, $lang->min_posts_enabled_desc, $form->generate_text_box('postnum', $mybb->input['postnum'], array('id' => 'postnum')), 'postnum');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_profile_field);

	$form->output_submit_wrapper($buttons);
	$form->end();

	echo '<script type="text/javascript" src="./jscripts/peeker.js"></script>
	<script type="text/javascript">
		Event.observe(window, "load", function() {
				var maxlength_peeker = new Peeker($("fieldtype"), $("row_maxlength"), /text|textarea/, false);
				var fieldlength_peeker = new Peeker($("fieldtype"), $("row_fieldlength"), /select|multiselect/, false);
				var options_peeker = new Peeker($("fieldtype"), $("row_options"), /select|radio|checkbox/, false);
				// Add a star to the extra row since the "extra" is required if the box is shown
				add_star("row_maxlength");
				add_star("row_fieldlength");
				add_star("row_options");
		});
	</script>';

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$plugins->run_hooks("admin_config_profile_fields_edit");

	$query = $db->simple_select("profilefields", "*", "fid = '".intval($mybb->input['fid'])."'");
	$profile_field = $db->fetch_array($query);

	if(!$profile_field['fid'])
	{
		flash_message($lang->error_invalid_fid, 'error');
		admin_redirect("index.php?module=config-profile_fields");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = $lang->error_missing_description;
		}

		if(!trim($mybb->input['fieldtype']))
		{
			$errors[] = $lang->error_missing_fieldtype;
		}

		$type = $mybb->input['fieldtype'];
		$options = preg_replace("#(\r\n|\r|\n)#s", "\n", trim($mybb->input['options']));
		if($type != "text" && $type != "textarea")
		{
			$type = "$type\n$options";
		}

		if(!$errors)
		{
			$updated_profile_field = array(
				"name" => $db->escape_string($mybb->input['name']),
				"description" => $db->escape_string($mybb->input['description']),
				"disporder" => intval($mybb->input['disporder']),
				"type" => $db->escape_string($type),
				"length" => intval($mybb->input['length']),
				"maxlength" => intval($mybb->input['maxlength']),
				"required" => $db->escape_string($mybb->input['required']),
				"editable" => $db->escape_string($mybb->input['editable']),
				"hidden" => $db->escape_string($mybb->input['hidden']),
				"postbit" => $db->escape_string($mybb->input['postbit']),
				"postnum" => intval($mybb->input['postnum'])
			);

			$db->update_query("profilefields", $updated_profile_field, "fid = '".intval($mybb->input['fid'])."'");

			$plugins->run_hooks("admin_config_profile_fields_edit_commit");

			// Log admin action
			log_admin_action($profile_field['fid'], $mybb->input['name']);

			flash_message($lang->success_profile_field_saved, 'success');
			admin_redirect("index.php?module=config-profile_fields");
		}
	}

	$page->add_breadcrumb_item($lang->edit_profile_field);
	$page->output_header($lang->custom_profile_fields." - ".$lang->edit_profile_field);

	$sub_tabs['edit_profile_field'] = array(
		'title' => $lang->edit_profile_field,
		'link' => "index.php?module=config-profile_fields&amp;action=edit&amp;fid=".intval($mybb->input['fid']),
		'description' => $lang->edit_profile_field_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_profile_field');
	$form = new Form("index.php?module=config-profile_fields&amp;action=edit", "post", "edit");


	echo $form->generate_hidden_field("fid", $profile_field['fid']);

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$type = explode("\n", $profile_field['type'], "2");

		$mybb->input = $profile_field;
		$mybb->input['fieldtype'] = $type[0];
		$mybb->input['options'] = $type[1];
	}

	$form_container = new FormContainer($lang->edit_profile_field);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$select_list = array(
		"text" => $lang->text,
		"textarea" => $lang->textarea,
		"select" => $lang->select,
		"multiselect" => $lang->multiselect,
		"radio" => $lang->radio,
		"checkbox" => $lang->checkbox
	);
	$form_container->output_row($lang->field_type." <em>*</em>", $lang->field_type_desc, $form->generate_select_box('fieldtype', $select_list, $mybb->input['fieldtype'], array('id' => 'fieldtype')), 'fieldtype');
	$form_container->output_row($lang->maximum_length, $lang->maximum_length_desc, $form->generate_text_box('maxlength', $mybb->input['maxlength'], array('id' => 'maxlength')), 'maxlength', array(), array('id' => 'row_maxlength'));
	$form_container->output_row($lang->field_length, $lang->field_length_desc, $form->generate_text_box('length', $mybb->input['length'], array('id' => 'length')), 'length', array(), array('id' => 'row_fieldlength'));
	$form_container->output_row($lang->selectable_options, $lang->selectable_options_desc, $form->generate_text_area('options', $mybb->input['options'], array('id' => 'options')), 'options', array(), array('id' => 'row_options'));
	$form_container->output_row($lang->display_order." <em>*</em>", $lang->display_order_desc, $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
	$form_container->output_row($lang->required." <em>*</em>", $lang->required_desc, $form->generate_yes_no_radio('required', $mybb->input['required']));
	$form_container->output_row($lang->editable_by_user." <em>*</em>", $lang->editable_by_user_desc, $form->generate_yes_no_radio('editable', $mybb->input['editable']));
	$form_container->output_row($lang->hide_on_profile." <em>*</em>", $lang->hide_on_profile_desc, $form->generate_yes_no_radio('hidden', $mybb->input['hidden']));
	$form_container->output_row($lang->display_on_postbit." <em>*</em>", $lang->display_on_postbit_desc, $form->generate_yes_no_radio('postbit', $mybb->input['postbit']));
	$form_container->output_row($lang->min_posts_enabled, $lang->min_posts_enabled_desc, $form->generate_text_box('postnum', $mybb->input['postnum'], array('id' => 'postnum')), 'postnum');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_profile_field);

	$form->output_submit_wrapper($buttons);
	$form->end();

	echo '<script type="text/javascript" src="./jscripts/peeker.js"></script>
	<script type="text/javascript">
		Event.observe(window, "load", function() {
				var maxlength_peeker = new Peeker("fieldtype", "row_maxlength", /text|textarea/);
				var fieldlength_peeker = new Peeker("fieldtype", "row_fieldlength", /select|multiselect/);
				var options_peeker = new Peeker("fieldtype", "row_options", /select|radio|checkbox/);
				// Add a star to the extra row since the "extra" is required if the box is shown
				add_star("row_maxlength");
				add_star("row_fieldlength");
				add_star("row_options");
		});
	</script>';

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("admin_config_profile_fields_delete");

	$query = $db->simple_select("profilefields", "*", "fid='".intval($mybb->input['fid'])."'");
	$profile_field = $db->fetch_array($query);

	// Does the profile field not exist?
	if(!$profile_field['fid'])
	{
		flash_message($lang->error_invalid_fid, 'error');
		admin_redirect("index.php?module=config-profile_fields");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=config-profile_fields");
	}

	if($mybb->request_method == "post")
	{
		// Delete the profile field
		$db->delete_query("profilefields", "fid='{$profile_field['fid']}'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."userfields DROP fid{$profile_field['fid']}");

		$plugins->run_hooks("admin_config_profile_fields_delete_commit");

		// Log admin action
		log_admin_action($profile_field['fid'], $profile_field['name']);

		flash_message($lang->success_profile_field_deleted, 'success');
		admin_redirect("index.php?module=config-profile_fields");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-profile_fields&amp;action=delete&amp;fid={$profile_field['fid']}", $lang->confirm_profile_field_deletion);
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_config_profile_fields_start");

	$page->output_header($lang->custom_profile_fields);

	$sub_tabs['custom_profile_fields'] = array(
		'title' => $lang->custom_profile_fields,
		'link' => "index.php?module=config-profile_fields",
		'description' => $lang->custom_profile_fields_desc
	);

	$sub_tabs['add_profile_field'] = array(
		'title' => $lang->add_new_profile_field,
		'link' => "index.php?module=config-profile_fields&amp;action=add",
	);


	$page->output_nav_tabs($sub_tabs, 'custom_profile_fields');

	$table = new Table;
	$table->construct_header($lang->name);
	$table->construct_header($lang->id, array("class" => "align_center"));
	$table->construct_header($lang->required, array("class" => "align_center"));
	$table->construct_header($lang->editable, array("class" => "align_center"));
	$table->construct_header($lang->hidden, array("class" => "align_center"));
	$table->construct_header($lang->postbit, array("class" => "align_center"));
	$table->construct_header($lang->controls, array("class" => "align_center"));

	$query = $db->simple_select("profilefields", "*", "", array('order_by' => 'disporder'));
	while($field = $db->fetch_array($query))
	{
		if($field['required'])
		{
			$required = $lang->yes;
		}
		else
		{
			$required = $lang->no;
		}

		if($field['editable'])
		{
			$editable = $lang->yes;
		}
		else
		{
			$editable = $lang->no;
		}

		if($field['hidden'])
		{
			$hidden = $lang->yes;
		}
		else
		{
			$hidden = $lang->no;
		}

		if($field['postbit'])
		{
			$postbit = $lang->yes;
		}
		else
		{
			$postbit = $lang->no;
		}

		$table->construct_cell("<strong><a href=\"index.php?module=config-profile_fields&amp;action=edit&amp;fid={$field['fid']}\">".htmlspecialchars_uni($field['name'])."</a></strong><br /><small>".htmlspecialchars_uni($field['description'])."</small>", array('width' => '45%'));
		$table->construct_cell($field['fid'], array("class" => "align_center", 'width' => '5%'));
		$table->construct_cell($required, array("class" => "align_center", 'width' => '10%'));
		$table->construct_cell($editable, array("class" => "align_center", 'width' => '10%'));
		$table->construct_cell($hidden, array("class" => "align_center", 'width' => '10%'));
		$table->construct_cell($postbit, array("class" => "align_center", 'width' => '10%')); 

		$popup = new PopupMenu("field_{$field['fid']}", $lang->options);
		$popup->add_item($lang->edit_field, "index.php?module=config-profile_fields&amp;action=edit&amp;fid={$field['fid']}");
		$popup->add_item($lang->delete_field, "index.php?module=config-profile_fields&amp;action=delete&amp;fid={$field['fid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_profile_field_deletion}')");
		$table->construct_cell($popup->fetch(), array("class" => "align_center", 'width' => '20%'));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_profile_fields, array('colspan' => 7));
		$table->construct_row();
	}

	$table->output($lang->custom_profile_fields);

	$page->output_footer();
}
?>