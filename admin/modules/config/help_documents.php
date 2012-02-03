<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: help_documents.php 5297 2010-12-28 22:01:14Z Tomm $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->help_documents, "index.php?module=config-help_documents");

$plugins->run_hooks("admin_config_help_documents_begin");

// Add something
if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_config_help_documents_add");
	
	// Add section
	if($mybb->input['type'] == "section")
	{
		$plugins->run_hooks("admin_config_help_documents_add_section");
		
		// Do add?
		if($mybb->request_method == "post")
		{
			if(empty($mybb->input['name']))
			{
				$errors[] = $lang->error_section_missing_name;
			}
			
			if(empty($mybb->input['description']))
			{
				$errors[] = $lang->error_section_missing_description;
			}
			
			if(!isset($mybb->input['enabled']))
			{
				$errors[] = $lang->error_section_missing_enabled;
			}
			
			if($mybb->input['enabled'] != 1)
			{
				$mybb->input['enabled'] = 0;
			}
			
			if(!is_array($errors))
			{
				$sql_array = array(
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"usetranslation" => 0,
					"enabled" => intval($mybb->input['enabled']),
					"disporder" => intval($mybb->input['disporder'])
				);
				
				$sid = $db->insert_query("helpsections", $sql_array);
				
				$plugins->run_hooks("admin_config_help_documents_add_section_commit");
				
				// Log admin action
				log_admin_action($sid, $mybb->input['name'], 'section');

				flash_message($lang->success_help_section_added, 'success');
				admin_redirect('index.php?module=config-help_documents');
			}
		}
	
		$page->add_breadcrumb_item($lang->add_new_section);
		$page->output_header($lang->help_documents." - ".$lang->add_new_section);
		
		$sub_tabs['manage_help_documents'] = array(
			'title'	=> $lang->manage_help_documents,
			'link'	=> "index.php?module=config-help_documents"
		);
	
		$sub_tabs['add_help_document'] = array(
			'title'	=> $lang->add_new_document,
			'link'	=> "index.php?module=config-help_documents&amp;action=add&amp;type=document"
		);
		
		$sub_tabs['add_help_section'] = array(
			'title'	=> $lang->add_new_section,
			'link'	=> "index.php?module=config-help_documents&amp;action=add&amp;type=section",
			'description' => $lang->add_new_section_desc
		);
	
		$page->output_nav_tabs($sub_tabs, 'add_help_section');
	
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$query = $db->simple_select("helpsections", "MAX(disporder) as maxdisp");
			$mybb->input['disporder'] = $db->fetch_field($query, "maxdisp")+1;
			$mybb->input['enabled'] = 1;
			$mybb->input['translation'] = 0;
		}
	
		$form = new Form("index.php?module=config-help_documents&amp;action=add&amp;type=section", "post", "add");
		$form_container = new FormContainer($lang->add_new_section);
		$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
		$form_container->output_row($lang->enabled." <em>*</em>", "", $form->generate_yes_no_radio('enabled', $mybb->input['enabled']));
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button($lang->save_section);
	
		$form->output_submit_wrapper($buttons);
		$form->end();
	}
	
	// Add page
	else
	{
		$plugins->run_hooks("admin_config_help_documents_add_page");
		
		// Do add?
		if($mybb->request_method == "post")
		{
			if(empty($mybb->input['sid']))
			{
				$errors[] = $lang->error_missing_sid;
			}
			
			if(empty($mybb->input['name']))
			{
				$errors[] = $lang->error_document_missing_name;
			}
			
			if(empty($mybb->input['description']))
			{
				$errors[] = $lang->error_document_missing_description;
			}
			
			if(empty($mybb->input['document']))
			{
				$errors[] = $lang->error_document_missing_document;
			}
			
			if(!isset($mybb->input['enabled']))
			{
				$errors[] = $lang->error_document_missing_enabled;
			}
			
			if($mybb->input['enabled'] != 1)
			{
				$mybb->input['enabled'] = 0;
			}
			
			if(!is_array($errors))
			{
				$sql_array = array(
					"sid" => intval($mybb->input['sid']),
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"document" => $db->escape_string($mybb->input['document']),
					"usetranslation" => 0,
					"enabled" => intval($mybb->input['enabled']),
					"disporder" => intval($mybb->input['disporder'])
				);
				
				$hid = $db->insert_query("helpdocs", $sql_array);
				
				$plugins->run_hooks("admin_config_help_documents_add_page_commit");

				// Log admin action
				log_admin_action($hid, $mybb->input['name'], 'document');
				
				flash_message($lang->success_help_document_added, 'success');
				admin_redirect('index.php?module=config-help_documents');
			}
		}
	
		$page->add_breadcrumb_item($lang->add_new_document);
		$page->output_header($lang->help_documents." - ".$lang->add_new_document);		
		
		$sub_tabs['manage_help_documents'] = array(
			'title'	=> $lang->manage_help_documents,
			'link'	=> "index.php?module=config-help_documents"
		);
	
		$sub_tabs['add_help_document'] = array(
			'title'	=> $lang->add_new_document,
			'link'	=> "index.php?module=config-help_documents&amp;action=add&amp;type=document",
			'description' => $lang->add_new_document_desc
		);
		
		$sub_tabs['add_help_section'] = array(
			'title'	=> $lang->add_new_section,
			'link'	=> "index.php?module=config-help_documents&amp;action=add&amp;type=section"
		);
	
		$page->output_nav_tabs($sub_tabs, 'add_help_document');
	
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			// Select the largest existing display order
			$query = $db->simple_select("helpdocs", "MAX(disporder) as maxdisp");
			$mybb->input['disporder'] = $db->fetch_field($query, "maxdisp")+1;
			$mybb->input['enabled'] = 1;
			$mybb->input['translation'] = 0;
		}
	
		$form = new Form("index.php?module=config-help_documents&amp;action=add&amp;type=document", "post", "add");
		$form_container = new FormContainer($lang->add_new_document);
		$query = $db->simple_select("helpsections", "sid, name");
		while($section = $db->fetch_array($query))
		{
			$sections[$section['sid']] = $section['name'];
		}
		$form_container->output_row($lang->section." <em>*</em>", "", $form->generate_select_box("sid", $sections, $mybb->input['sid'], array('id' => 'sid')), 'sid');
		$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->document." <em>*</em>", "", $form->generate_text_area('document', $mybb->input['document'], array('id' => 'document')), 'document');
		$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
		$form_container->output_row($lang->enabled." <em>*</em>", "", $form->generate_yes_no_radio('enabled', $mybb->input['enabled']));
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button($lang->save_document);
	
		$form->output_submit_wrapper($buttons);
		$form->end();
	}

	$page->output_footer();
}

// Edit something
if($mybb->input['action'] == "edit")
{
	$plugins->run_hooks("admin_config_help_documents_edit");
	
	// Edit a section
	if($mybb->input['sid'] && !$mybb->input['hid'])
	{
		$query = $db->simple_select("helpsections", "*", "sid = '".intval($mybb->input['sid'])."'");
		$section = $db->fetch_array($query);

		$plugins->run_hooks("admin_config_help_documents_edit_section");
		
		// Do edit?
		if($mybb->request_method == "post")
		{
			$sid = intval($mybb->input['sid']);
			
			if(empty($sid))
			{
				$errors[] = $lang->error_invalid_sid;
			}
			
			if(empty($mybb->input['name']))
			{
				$errors[] = $lang->error_section_missing_name;
			}
			
			if(empty($mybb->input['description']))
			{
				$errors[] = $lang->error_section_missing_description;
			}
			
			if(!isset($mybb->input['enabled']))
			{
				$errors[] = $lang->error_section_missing_enabled;
			}
			
			if($mybb->input['enabled'] != 1)
			{
				$mybb->input['enabled'] = 0;
			}
			
			if(!is_array($errors))
			{
				$sql_array = array(
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"usetranslation" => intval($mybb->input['usetranslation']),
					"enabled" => intval($mybb->input['enabled']),
					"disporder" => intval($mybb->input['disporder'])
				);
				
				$db->update_query("helpsections", $sql_array, "sid = '{$sid}'");
				
				$plugins->run_hooks("admin_config_help_documents_edit_section_commit");

				// Log admin action
				log_admin_action($sid, $mybb->input['name'], 'section');
				
				flash_message($lang->success_help_section_updated, 'success');
				admin_redirect('index.php?module=config-help_documents');
			}
		}
	
		$page->add_breadcrumb_item($lang->edit_section);
		$page->output_header($lang->help_documents." - ".$lang->edit_section);
		
		
		$sub_tabs['edit_help_section'] = array(
			'title'	=> $lang->edit_section,
			'link'	=> "index.php?module=config-help_documents&amp;action=edit&amp;sid=".intval($mybb->input['sid']),
			'description' => $lang->edit_section_desc
		);
	
		$page->output_nav_tabs($sub_tabs, 'edit_help_section');
	
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$mybb->input['sid'] = $section['sid'];
			$mybb->input['name'] = $section['name'];
			$mybb->input['description'] = $section['description'];
			$mybb->input['disporder'] = $section['disporder'];
			$mybb->input['enabled'] = $section['enabled'];
			$mybb->input['usetranslation'] = $section['usetranslation'];
		}
	
		$form = new Form("index.php?module=config-help_documents&amp;action=edit", "post", "edit");
		
		echo $form->generate_hidden_field("sid", $mybb->input['sid']);
		echo $form->generate_hidden_field("usetranslation", $mybb->input['usetranslation']);
		
		$form_container = new FormContainer($lang->edit_section." ({$lang->id} ".intval($mybb->input['sid']).")");
		$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
		$form_container->output_row($lang->enabled." <em>*</em>", "", $form->generate_yes_no_radio('enabled', $mybb->input['enabled']));
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button($lang->edit_section);
	
		$form->output_submit_wrapper($buttons);
		$form->end();
	}
	
	// Edit document
	else
	{
		$plugins->run_hooks("admin_config_help_documents_edit_page");
		
		// Do edit?
		if($mybb->request_method == "post")
		{
			$hid = intval($mybb->input['hid']);
			
			if(empty($hid))
			{
				$errors[] = $lang->error_invalid_sid;
			}
			
			if(empty($mybb->input['name']))
			{
				$errors[] = $lang->error_document_missing_name;
			}
			
			if(empty($mybb->input['description']))
			{
				$errors[] = $lang->error_document_missing_description;
			}
			
			if(empty($mybb->input['document']))
			{
				$errors[] = $lang->error_document_missing_document;
			}
			
			if(!isset($mybb->input['enabled']))
			{
				$errors[] = $lang->error_document_missing_enabled;
			}
			
			if($mybb->input['enabled'] != 1)
			{
				$mybb->input['enabled'] = 0;
			}
			
			if(!is_array($errors))
			{
				$sql_array = array(
					"sid" => intval($mybb->input['sid']),
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"document" => $db->escape_string($mybb->input['document']),
					"usetranslation" => 0,
					"enabled" => intval($mybb->input['enabled']),
					"disporder" => intval($mybb->input['disporder'])
				);
				
				$db->update_query("helpdocs", $sql_array, "hid = '{$hid}'");
				
				$plugins->run_hooks("admin_config_help_documents_edit_page_commit");
				
				// Log admin action
				log_admin_action($hid, $mybb->input['name'], 'document');

				flash_message($lang->success_help_document_updated, 'success');
				admin_redirect('index.php?module=config-help_documents');
			}
		}
	
		$page->add_breadcrumb_item($lang->edit_document);
		$page->output_header($lang->help_documents." - ".$lang->edit_document);
		
		
		$sub_tabs['edit_help_document'] = array(
			'title'	=> $lang->edit_document,
			'link'	=> "index.php?module=config-help_documents&amp;action=edit&amp;hid=".intval($mybb->input['hid']),
			'description' => $lang->edit_document_desc
		);
	
		$page->output_nav_tabs($sub_tabs, 'edit_help_document');
	
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$query = $db->simple_select("helpdocs", "*", "hid = '".intval($mybb->input['hid'])."'");
			$doc = $db->fetch_array($query);
			$mybb->input['hid'] = $doc['hid'];
			$mybb->input['sid'] = $doc['sid'];
			$mybb->input['name'] = $doc['name'];
			$mybb->input['description'] = $doc['description'];
			$mybb->input['document'] = $doc['document'];
			$mybb->input['disporder'] = $doc['disporder'];
			$mybb->input['enabled'] = $doc['enabled'];
		}
	
		$form = new Form("index.php?module=config-help_documents&amp;action=edit", "post", "edit");
		
		echo $form->generate_hidden_field("hid", $mybb->input['hid']);
				
		$form_container = new FormContainer($lang->edit_document." ({$lang->id} ".intval($mybb->input['hid']).")");
		
		$query = $db->simple_select("helpsections", "sid, name");
		while($section = $db->fetch_array($query))
		{
			$sections[$section['sid']] = $section['name'];
		}
		$form_container->output_row($lang->section." <em>*</em>", "", $form->generate_select_box("sid", $sections, $mybb->input['sid']), 'sid');
		$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->document." <em>*</em>", "", $form->generate_text_area('document', $mybb->input['document'], array('id' => 'document')), 'document');
		$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
		$form_container->output_row($lang->enabled." <em>*</em>", "", $form->generate_yes_no_radio('enabled', $mybb->input['enabled']));
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button($lang->edit_document);
		
		$form->output_submit_wrapper($buttons);
		$form->end();
	}

	$page->output_footer();
}

// Delete something
if($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("admin_config_help_documents_delete");
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=config-help_documents");
	}

	// Do delete something?
	if($mybb->request_method == "post")
	{
		// Delete section
		if(isset($mybb->input['sid']))
		{
			$sid = intval($mybb->input['sid']);
			
			$query = $db->simple_select("helpsections", "*", "sid='{$sid}'");
			$section = $db->fetch_array($query);
			
			// Invalid section?
			if(!$section['sid'])
			{
				flash_message($lang->error_missing_section_id, 'error');
				admin_redirect("index.php?module=config-help_documents");
			}
			
			// Default section?
			if($sid <= 2)
			{
				flash_message($lang->error_cannot_delete_section, 'error');
				admin_redirect("index.php?module=config-help_documents");
			}
			
			// Delete section and its documents
			$db->delete_query("helpsections", "sid = '{$sid}'", 1);
			$db->delete_query("helpdocs", "sid = '{$sid}'");
			
			$plugins->run_hooks("admin_config_help_documents_delete_section_commit");

			// Log admin action
			log_admin_action($section['sid'], $section['name'], 'section');

			flash_message($lang->success_section_deleted, 'success');
			admin_redirect("index.php?module=config-help_documents");
		}
		
		// Delete document
		else
		{
			$hid = intval($mybb->input['hid']);
			
			$query = $db->simple_select("helpdocs", "*", "hid='{$hid}'");
			$doc = $db->fetch_array($query);
			
			// Invalid document?
			if(!$doc['hid'])
			{
				flash_message($lang->error_missing_hid, 'error');
				admin_redirect("index.php?module=config-help_documents");
			}			
			
			// Default document?
			if($hid <= 7)
			{
				flash_message($lang->error_cannot_delete_document, 'error');
				admin_redirect("index.php?module=config-help_documents");
			}
			
			$db->delete_query("helpdocs", "hid = '{$hid}'", 1);
			
			$plugins->run_hooks("admin_config_help_documents_delete_page_commit");

			// Log admin action
			log_admin_action($doc['hid'], $doc['name'], 'document');
			
			flash_message($lang->success_document_deleted, 'success');
			admin_redirect("index.php?module=config-help_documents");
		}
	}
	// Show form for deletion
	else
	{
		// Section
		if(isset($mybb->input['sid']))
		{
			$sid = intval($mybb->input['sid']);
			$page->output_confirm_action("index.php?module=config-help_documents&amp;action=delete&amp;sid={$sid}", $lang->confirm_section_deletion);
		}
		// Document
		else
		{
			$hid = intval($mybb->input['hid']);
			$page->output_confirm_action("index.php?module=config-help_documents&amp;action=delete&amp;hid={$hid}", $lang->confirm_document_deletion);
		}
	}
}

// List document and sections
if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_config_help_documents_start");
	
	$page->output_header($lang->help_documents);

	$sub_tabs['manage_help_documents'] = array(
		'title'	=> $lang->manage_help_documents,
		'link'	=> "index.php?module=config-help_documents",
		'description'=> $lang->manage_help_documents_desc
	);

	$sub_tabs['add_help_document'] = array(
		'title'	=> $lang->add_new_document,
		'link'	=> "index.php?module=config-help_documents&amp;action=add&amp;type=document"
	);
	
	$sub_tabs['add_help_section'] = array(
		'title'	=> $lang->add_new_section,
		'link'	=> "index.php?module=config-help_documents&amp;action=add&amp;type=section"
	);

	$page->output_nav_tabs($sub_tabs, 'manage_help_documents');

	$table = new Table;
	$table->construct_header($lang->section_document);
	$table->construct_header($lang->controls, array('class' => "align_center", 'colspan' => 2, "width" => "150"));

	$query = $db->simple_select("helpsections", "*", "", array('order_by' => "disporder"));
	while($section = $db->fetch_array($query))
	{
		// Icon to differentiate section type
		if($section['sid'] > 2)
		{
			$icon = "<img src=\"styles/default/images/icons/custom.gif\" title=\"{$lang->custom_doc_sec}\" alt=\"{$lang->custom_doc_sec}\" style=\"vertical-align: middle;\" />";
		}
		else
		{
			$icon = "<img src=\"styles/default/images/icons/default.gif\" title=\"{$lang->default_doc_sec}\" alt=\"{$lang->default_doc_sec}\" style=\"vertical-align: middle;\" />";
		}
		$table->construct_cell("<div class=\"float_right\">{$icon}</div><div><strong><a href=\"index.php?module=config-help_documents&amp;action=edit&amp;sid={$section['sid']}\">{$section['name']}</a></strong><br /><small>{$section['description']}</small></div>");
 
		$table->construct_cell("<a href=\"index.php?module=config-help_documents&amp;action=edit&amp;sid={$section['sid']}\">{$lang->edit}</a>", array("class" => "align_center", "width" => '60'));
		
		// Show delete only if not a default section
		if($section['sid'] > 2)
		{
			$table->construct_cell("<a href=\"index.php?module=config-help_documents&amp;action=delete&amp;sid={$section['sid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_section_deletion}')\">{$lang->delete}</a>", array("class" => "align_center", "width" => '90'));
		}
		else
		{
			$table->construct_cell("&nbsp;", array("width" => '90'));
		}
		$table->construct_row();
			
		$query2 = $db->simple_select("helpdocs", "*", "sid='{$section['sid']}'", array('order_by' => "disporder"));
		while($doc = $db->fetch_array($query2))
		{
			// Icon to differentiate document type
			if($doc['hid'] > 7)
			{
				$icon = "<img src=\"styles/default/images/icons/custom.gif\" title=\"{$lang->custom_doc_sec}\" alt=\"{$lang->custom_doc_sec}\" style=\"vertical-align: middle;\" />";
			}
			else
			{
				$icon = "<img src=\"styles/default/images/icons/default.gif\" title=\"{$lang->default_doc_sec}\" alt=\"{$lang->default_doc_sec}\" style=\"vertical-align: middle;\" />";
			}
			$table->construct_cell("<div style=\"padding-left: 40px;\"><div class=\"float_right\">{$icon}</div><div><strong><a href=\"index.php?module=config-help_documents&amp;action=edit&amp;hid={$doc['hid']}\">{$doc['name']}</a></strong><br /><small>{$doc['description']}</small></div></div>");

			$table->construct_cell("<a href=\"index.php?module=config-help_documents&amp;action=edit&amp;hid={$doc['hid']}\">{$lang->edit}</a>", array("class" => "align_center", "width" => '60'));
			
			// Only show delete if not a default document
			if($doc['hid'] > 7)
			{
				$table->construct_cell("<a href=\"index.php?module=config-help_documents&amp;action=delete&amp;hid={$doc['hid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_document_deletion}')\">{$lang->delete}</a>", array("class" => "align_center", "width" => '90'));
			}
			else
			{
				$table->construct_cell("&nbsp;", array("width" => '90'));
			}
			$table->construct_row();
		}
	}
	
	// No documents message
	if($table->num_rows()  == 0)
	{
		$table->construct_cell($lang->no_help_documents, array('colspan' => 3));
		$table->construct_row();
	}

	$table->output($lang->help_documents);
	
	echo <<<LEGEND
	<fieldset>
<legend>{$lang->legend}</legend>
<img src="styles/default/images/icons/custom.gif" alt="{$lang->custom_doc_sec}" style="vertical-align: middle;" /> {$lang->custom_doc_sec}<br />
<img src="styles/default/images/icons/default.gif" alt="{$lang->default_doc_sec}" style="vertical-align: middle;" /> {$lang->default_doc_sec}
</fieldset>
LEGEND;

	$page->output_footer();
}

?>