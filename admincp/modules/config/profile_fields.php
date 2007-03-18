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


$page->add_breadcrumb_item("Custom Profile fields", "index.php?".SID."&amp;module=config/profile_fields");


if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this custom profile field";
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = "You did not enter a description for this custom profile field";
		}

		if(!trim($mybb->input['disporder']))
		{
			$errors[] = "You did not enter a display order for this custom profile field";
		}

		if(!trim($mybb->input['fieldtype']))
		{
			$errors[] = "You did not enter a field type for this custom profile field";
		}
		
		if(!trim($mybb->input['required']))
		{
			$errors[] = "You did not select Yes or No for the \"Required?\" option";
		}
		
		if(!trim($mybb->input['editable']))
		{
			$errors[] = "You did not select Yes or No for the \"Editable by user?\" option";
		}
		
		if(!trim($mybb->input['hidden']))
		{
			$errors[] = "You did not select Yes or No for the \"Hide on profile?\" option";
		}

		if(!$errors)
		{
			$type = $mybb->input['type'];
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
				"name" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"disporder" => intval($mybb->input['disporder']),
				"type" => $db->escape_string($thing),
				"length" => intval($mybb->input['length']),
				"maxlength" => intval($mybb->input['maxlength']),
				"required" => $db->escape_string($mybb->input['required']),
				"editable" => $db->escape_string($mybb->input['editable']),
				"hidden" => $db->escape_string($mybb->input['hidden']),
			);
			
			$db->insert_query("profilefields", $new_profile_field);
			
			$fid = $db->insert_id();
			
			$db->query("ALTER TABLE ".TABLE_PREFIX."userfields ADD fid{$fid} TEXT");
					
			flash_message("The custom profile field has been added successfully.", 'success');
			admin_redirect("index.php?".SID."&module=config/profile_fields");
		}
	}
	
	$page->add_breadcrumb_item("Add Custom Profile Field");
	$page->output_header("Custom Profile Field - Add Custom Profile Field");
	
	$sub_tabs['add_custom_profile_field'] = array(
		'title' => "Add New Field",
		'link' => "index.php?".SID."&amp;module=config/profile_fields&amp;action=add",
		'description' => 'Here you can add a new custom profile field.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'add_custom_profile_field');
	$form = new Form("index.php?".SID."&amp;module=config/profile_fields&amp;action=add", "post", "add");
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['fieldtype'] = 'textbox';
		$mybb->input['required'] = 'no';
		$mybb->input['editable'] = 'yes';
		$mybb->input['hidden'] = 'no';
	}
	
	$form_container = new FormContainer("Add New Custom Profile Field");
	$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Short Description <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$form_container->output_row("Maximum Length", "This maximum number of characters that can be entered. This only applies to textboxes and textareas.", $form->generate_text_box('maxlength', $mybb->input['maxlength'], array('id' => 'maxlength')), 'maxlength');
	$form_container->output_row("Field Length", "This length of the field. This only applies to single and multiple select boxes.", $form->generate_text_box('length', $mybb->input['length'], array('id' => 'length')), 'length');
	$form_container->output_row("Display Order <em>*</em>", "This is the order of custom profile fields in relation to other custom profile fields. This number should not be the same as another field.", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
	
	$select_list = array(
		"text" => "Textbox",
		"textarea" => "Textarea",
		"select" => "Select Box",
		"multiselect" => "Multiple Option Selection Box",
		"radio" => "Radio Buttons",
		"checkbox" => "Check Boxes"
	);
	$form_container->output_row("Field Type <em>*</em>", "This is the field type that will be shown.", $form->generate_select_box('fieldtype', $select_list, $mybb->input['fieldtype'], array('id' => 'fieldtype')), 'fieldtype');
	$form_container->output_row("Selectable Options?", "Please enter each option on a seperate line. This only applies to the select boxes, check boxes, and radio buttons types.", $form->generate_text_area('options', $mybb->input['options']), 'options');
	$form_container->output_row("Required? <em>*</em>", "Is this field required to be filled in during registration or profile editing? Note that this does not apply if the field is hidden.", $form->generate_yes_no_radio('required', $mybb->input['required']), 'required');
	$form_container->output_row("Editable by user? <em>*</em>", "Should this field be editable by the user? If not, administrators/moderators can still edit the field.", $form->generate_yes_no_radio('editable', $mybb->input['editable']), 'editable');
	$form_container->output_row("Hide on profile? <em>*</em>", "Should this field be hidden on the user's profile? If it is hidden, it can only be viewed by administrators/moderators.", $form->generate_yes_no_radio('hidden', $mybb->input['hidden']), 'hidden');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save New Profile Field");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	if(!trim($mybb->input['fid']))
	{
		flash_message("You have input an invalid profile field.", 'error');
		admin_redirect("index.php?".SID."&module=config/profile_fields");
	}
		
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this custom profile field";
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = "You did not enter a description for this custom profile field";
		}

		if(!trim($mybb->input['disporder']))
		{
			$errors[] = "You did not enter a display order for this custom profile field";
		}

		if(!trim($mybb->input['fieldtype']))
		{
			$errors[] = "You did not enter a field type for this custom profile field";
		}
		
		if(!trim($mybb->input['required']))
		{
			$errors[] = "You did not select Yes or No for the \"Required?\" option";
		}
		
		if(!trim($mybb->input['editable']))
		{
			$errors[] = "You did not select Yes or No for the \"Editable by user?\" option";
		}
		
		if(!trim($mybb->input['hidden']))
		{
			$errors[] = "You did not select Yes or No for the \"Hide on profile?\" option";
		}

		if(!$errors)
		{
			$profile_field = array(
				"name" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"disporder" => intval($mybb->input['disporder']),
				"type" => $db->escape_string($thing),
				"length" => intval($mybb->input['length']),
				"maxlength" => intval($mybb->input['maxlength']),
				"required" => $db->escape_string($mybb->input['required']),
				"editable" => $db->escape_string($mybb->input['editable']),
				"hidden" => $db->escape_string($mybb->input['hidden']),
			);
			
			$db->update_query("profilefields", $profile_field, "fid = '".intval($mybb->input['fid'])."'");
			
			flash_message("The custom profile field has been saved successfully.", 'success');
			admin_redirect("index.php?".SID."&module=config/profile_fields");
		}
	}
	
	$page->add_breadcrumb_item("Edit Field");
	$page->output_header("Custom Profile Fields - Edit Field");
	
	$sub_tabs['edit_custom_profile_field'] = array(
		'title' => "Edit Field",
		'link' => "index.php?".SID."&amp;module=config/&amp;action=edit&amp;fid=".intval($mybb->input['fid']),
		'description' => 'Here you can edit a custom profile field.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_custom_profile_field');
	$form = new Form("index.php?".SID."&amp;module=config/profile_fields&amp;action=edit", "post", "edit");
	
	$query = $db->simple_select("profilefields", "*", "fid = '".intval($mybb->input['fid'])."'");
	$field = $db->fetch_array($query);
	
	if(empty($field))
	{
		flash_message("You have input an invalid profile field.", 'error');
		admin_redirect("index.php?".SID."&module=config/profile_fields");
	}
	
	echo $form->generate_hidden_field("fid", $field['fid']);
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$type = explode("\n", $field['type'], "2");
	
		$mybb->input['title'] = $field['name'];
		$mybb->input['description'] = $field['description'];
		$mybb->input['maxlength'] = $field['maxlength'];
		$mybb->input['length'] = $field['length'];
		$mybb->input['disporder'] = $field['disporder'];
		$mybb->input['fieldtype'] = $type[0];
		$mybb->input['options'] = $type[1];
		$mybb->input['required'] = $field['required'];
		$mybb->input['editable'] = $field['editable'];
		$mybb->input['hidden'] = $field['hidden'];
	}
	
	$form_container = new FormContainer("Edit Custom Profile Field");
	$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Short Description <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$form_container->output_row("Maximum Length", "This maximum number of characters that can be entered. This only applies to textboxes and textareas.", $form->generate_text_box('maxlength', $mybb->input['maxlength'], array('id' => 'maxlength')), 'maxlength');
	$form_container->output_row("Field Length", "This length of the field. This only applies to single and multiple select boxes.", $form->generate_text_box('length', $mybb->input['length'], array('id' => 'length')), 'length');
	$form_container->output_row("Display Order <em>*</em>", "This is the order of custom profile fields in relation to other custom profile fields. This number should not be the same as another field.", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
	
	$select_list = array(
		"text" => "Textbox",
		"textarea" => "Textarea",
		"select" => "Select Box",
		"multiselect" => "Multiple Option Selection Box",
		"radio" => "Radio Buttons",
		"checkbox" => "Check Boxes"
	);
	$form_container->output_row("Field Type <em>*</em>", "This is the field type that will be shown.", $form->generate_select_box('fieldtype', $select_list, $mybb->input['fieldtype'], array('id' => 'fieldtype')), 'fieldtype');
	$form_container->output_row("Selectable Options?", "Please enter each option on a seperate line. This only applies to the select boxes, check boxes, and radio buttons types.", $form->generate_text_area('options', $mybb->input['options']), 'options');
	$form_container->output_row("Required? <em>*</em>", "Is this field required to be filled in during registration or profile editing? Note that this does not apply if the field is hidden.", $form->generate_yes_no_radio('required', $mybb->input['required']), 'required');
	$form_container->output_row("Editable by user? <em>*</em>", "Should this field be editable by the user? If not, administrators/moderators can still edit the field.", $form->generate_yes_no_radio('editable', $mybb->input['editable']), 'editable');
	$form_container->output_row("Hide on profile? <em>*</em>", "Should this field be hidden on the user's profile? If it is hidden, it can only be viewed by administrators/moderators.", $form->generate_yes_no_radio('hidden', $mybb->input['hidden']), 'hidden');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save Profile Field");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	if($mybb->input['no']) 
	{ 
		admin_redirect("index.php?".SID."&module=config/profile_fields"); 
	}
	
	if(!trim($mybb->input['fid']))
	{
		flash_message('You did not enter a profile field to delete', 'error');
		admin_redirect("index.php?".SID."&module=config/profile_fields");
	}
	
	$fid = intval($mybb->input['fid']);
	
	$query = $db->simple_select("profilefields", "COUNT(fid) as profilefields", "fid = '{$fid}'");
	if($db->fetch_field($query, "profilefields") == 0)
	{
		flash_message('You did not enter a valid profile field', 'error');
		admin_redirect("index.php?".SID."&module=config/profile_fields");
	}
	
	if($mybb->request_method == "post")
	{
		$db->delete_query("profilefields", "fid = '{$fid}'", 1);
		flash_message('Custom Profile Field Delete Successfully', 'success');
		admin_redirect("index.php?".SID."&module=config/profile_fields");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=config/profile_fields&amp;action=delete&amp;fid={$mybb->input['fid']}", "Are you sure you wish to delete this profile field?"); 
	}
}

if(!$mybb->input['action'])
{
	$page->output_header("Manage Custom Profile Fields");

	$sub_tabs['manage_custom_profile_fields'] = array(
		'title' => "Manage Custom Profile Fields",
		'link' => "index.php?".SID."&amp;module=config/profile_fields",
		'description' => "This section allows you to edit, delete, and manage your custom profile fields."
	);
	$sub_tabs['add_profile_field'] = array(
		'title' => "Add New Field",
		'link' => "index.php?".SID."&amp;module=config/profile_fields&amp;action=add",
	);

	
	$page->output_nav_tabs($sub_tabs, 'manage_custom_profile_fields');
	
	$table = new Table;
	$table->construct_header("Name");
	$table->construct_header("ID", array("class" => "align_center"));
	$table->construct_header("Required?", array("class" => "align_center"));
	$table->construct_header("Editable?", array("class" => "align_center"));
	$table->construct_header("Hidden?", array("class" => "align_center"));
	$table->construct_header("Controls", array("class" => "align_center"));
	
	$query = $db->simple_select("profilefields", "*", "", array('order_by' => 'disporder'));
	while($field = $db->fetch_array($query))
	{
		$table->construct_cell("<strong><a href=\"index.php?".SID."&amp;module=config/profile_fields&amp;action=edit&amp;fid={$field['fid']}\">{$field['name']}</a></strong><br /><small>{$field['description']}</small>", array('width' => '45%'));
		$table->construct_cell($field['fid'], array("class" => "align_center", 'width' => '5%'));
		$table->construct_cell(ucfirst($field['required']), array("class" => "align_center", 'width' => '10%'));
		$table->construct_cell(ucfirst($field['editable']), array("class" => "align_center", 'width' => '10%'));		
		$table->construct_cell(ucfirst($field['hidden']), array("class" => "align_center", 'width' => '10%'));
		
		$popup = new PopupMenu("field_{$field['fid']}", "Options");
		$popup->add_item("Edit Field", "index.php?".SID."&amp;module=config/profile_fields&amp;action=edit&amp;fid={$field['fid']}");
		$popup->add_item("Delete Field", "index.php?".SID."&amp;module=config/profile_fields&amp;action=delete&amp;fid={$field['fid']}", "return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this custom profile field?')");
		$table->construct_cell($popup->fetch(), array("class" => "align_center", 'width' => '20%'));
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are no custom profile fields on your forum at this time.", array('colspan' => 6));
		$table->construct_row();
	}
	
	$table->output("Custom Profile Fields");
	
	$page->output_footer();
}
?>