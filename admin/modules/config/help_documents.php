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
					"usetranslation" => $mybb->get_input('usetranslation', MyBB::INPUT_INT),
					"enabled" => $mybb->get_input('enabled', MyBB::INPUT_INT),
					"disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT)
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
			$mybb->input['usetranslation'] = 1;
		}

		$form = new Form("index.php?module=config-help_documents&amp;action=add&amp;type=section", "post", "add");
		echo $form->generate_hidden_field("usetranslation", $mybb->input['usetranslation']);

		$form_container = new FormContainer($lang->add_new_section);
		$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->display_order, "", $form->generate_numeric_field('disporder', $mybb->input['disporder'], array('id' => 'disporder', 'min' => 0)), 'disporder');
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
					"sid" => $mybb->get_input('sid', MyBB::INPUT_INT),
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"document" => $db->escape_string($mybb->input['document']),
					"usetranslation" => $mybb->get_input('usetranslation', MyBB::INPUT_INT),
					"enabled" => $mybb->get_input('enabled', MyBB::INPUT_INT),
					"disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT)
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
			$mybb->input['usetranslation'] = 1;
		}

		$form = new Form("index.php?module=config-help_documents&amp;action=add&amp;type=document", "post", "add");
		echo $form->generate_hidden_field("usetranslation", $mybb->input['usetranslation']);

		$form_container = new FormContainer($lang->add_new_document);
		$query = $db->simple_select("helpsections", "sid, name");

		$sections = array();
		while($section = $db->fetch_array($query))
		{
			$sections[$section['sid']] = $section['name'];
		}
		$form_container->output_row($lang->section." <em>*</em>", "", $form->generate_select_box("sid", $sections, $mybb->input['sid'], array('id' => 'sid')), 'sid');
		$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->document." <em>*</em>", "", $form->generate_text_area('document', $mybb->input['document'], array('id' => 'document')), 'document');
		$form_container->output_row($lang->display_order, "", $form->generate_numeric_field('disporder', $mybb->input['disporder'], array('id' => 'disporder', 'min' => 0)), 'disporder');
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
		$query = $db->simple_select("helpsections", "*", "sid = '".$mybb->get_input('sid', MyBB::INPUT_INT)."'");
		$section = $db->fetch_array($query);

		$plugins->run_hooks("admin_config_help_documents_edit_section");

		// Do edit?
		if($mybb->request_method == "post")
		{
			$sid = $mybb->get_input('sid', MyBB::INPUT_INT);

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
					"usetranslation" => $mybb->get_input('usetranslation', MyBB::INPUT_INT),
					"enabled" => $mybb->get_input('enabled', MyBB::INPUT_INT),
					"disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT)
				);

				$plugins->run_hooks("admin_config_help_documents_edit_section_commit");

				$db->update_query("helpsections", $sql_array, "sid = '{$sid}'");

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
			'link'	=> "index.php?module=config-help_documents&amp;action=edit&amp;sid=".$mybb->get_input('sid', MyBB::INPUT_INT),
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

		$form_container = new FormContainer($lang->edit_section." ({$lang->id} ".$mybb->get_input('sid', MyBB::INPUT_INT).")");
		$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->display_order, "", $form->generate_numeric_field('disporder', $mybb->input['disporder'], array('id' => 'disporder', 'min' => 0)), 'disporder');
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
			$hid = $mybb->get_input('hid', MyBB::INPUT_INT);

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
					"sid" => $mybb->get_input('sid', MyBB::INPUT_INT),
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"document" => $db->escape_string($mybb->input['document']),
					"usetranslation" => $mybb->get_input('usetranslation', MyBB::INPUT_INT),
					"enabled" => $mybb->get_input('enabled', MyBB::INPUT_INT),
					"disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT)
				);

				$plugins->run_hooks("admin_config_help_documents_edit_page_commit");

				$db->update_query("helpdocs", $sql_array, "hid = '{$hid}'");

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
			'link'	=> "index.php?module=config-help_documents&amp;action=edit&amp;hid=".$mybb->get_input('hid', MyBB::INPUT_INT),
			'description' => $lang->edit_document_desc
		);

		$page->output_nav_tabs($sub_tabs, 'edit_help_document');

		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$query = $db->simple_select("helpdocs", "*", "hid = '".$mybb->get_input('hid', MyBB::INPUT_INT)."'");
			$doc = $db->fetch_array($query);
			$mybb->input['hid'] = $doc['hid'];
			$mybb->input['sid'] = $doc['sid'];
			$mybb->input['name'] = $doc['name'];
			$mybb->input['description'] = $doc['description'];
			$mybb->input['document'] = $doc['document'];
			$mybb->input['disporder'] = $doc['disporder'];
			$mybb->input['enabled'] = $doc['enabled'];
			$mybb->input['usetranslation'] = $doc['usetranslation'];
		}

		$form = new Form("index.php?module=config-help_documents&amp;action=edit", "post", "edit");

		echo $form->generate_hidden_field("hid", $mybb->input['hid']);
		echo $form->generate_hidden_field("usetranslation", $mybb->input['usetranslation']);

		$form_container = new FormContainer($lang->edit_document." ({$lang->id} ".$mybb->get_input('hid', MyBB::INPUT_INT).")");

		$sections = array();
		$query = $db->simple_select("helpsections", "sid, name");
		while($section = $db->fetch_array($query))
		{
			$sections[$section['sid']] = $section['name'];
		}
		$form_container->output_row($lang->section." <em>*</em>", "", $form->generate_select_box("sid", $sections, $mybb->input['sid']), 'sid');
		$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->document." <em>*</em>", "", $form->generate_text_area('document', $mybb->input['document'], array('id' => 'document')), 'document');
		$form_container->output_row($lang->display_order, "", $form->generate_numeric_field('disporder', $mybb->input['disporder'], array('id' => 'disporder', 'min' => 0)), 'disporder');
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
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=config-help_documents");
	}

	$plugins->run_hooks("admin_config_help_documents_delete");

	// Do delete something?
	if($mybb->request_method == "post")
	{
		// Delete section
		if(isset($mybb->input['sid']))
		{
			$sid = $mybb->get_input('sid', MyBB::INPUT_INT);

			$query = $db->simple_select("helpsections", "*", "sid='{$sid}'");
			$section = $db->fetch_array($query);

			// Invalid section?
			if(!$section['sid'])
			{
				flash_message($lang->error_missing_section_id, 'error');
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
			$hid = $mybb->get_input('hid', MyBB::INPUT_INT);

			$query = $db->simple_select("helpdocs", "*", "hid='{$hid}'");
			$doc = $db->fetch_array($query);

			// Invalid document?
			if(!$doc['hid'])
			{
				flash_message($lang->error_missing_hid, 'error');
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
			$sid = $mybb->get_input('sid', MyBB::INPUT_INT);
			$page->output_confirm_action("index.php?module=config-help_documents&amp;action=delete&amp;sid={$sid}", $lang->confirm_section_deletion);
		}
		// Document
		else
		{
			$hid = $mybb->get_input('hid', MyBB::INPUT_INT);
			$page->output_confirm_action("index.php?module=config-help_documents&amp;action=delete&amp;hid={$hid}", $lang->confirm_document_deletion);
		}
	}
}

// List document and sections
if(!$mybb->input['action'])
{
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

	$plugins->run_hooks("admin_config_help_documents_start");

	$page->output_nav_tabs($sub_tabs, 'manage_help_documents');

	$table = new Table;
	$table->construct_header($lang->section_document);
	$table->construct_header($lang->controls, array('class' => "align_center", 'colspan' => 2, "width" => "150"));

	$query = $db->simple_select("helpsections", "*", "", array('order_by' => "disporder"));
	while($section = $db->fetch_array($query))
	{
		$table->construct_cell("<div><strong><a href=\"index.php?module=config-help_documents&amp;action=edit&amp;sid={$section['sid']}\">{$section['name']}</a></strong><br /><small>{$section['description']}</small></div>");
		$table->construct_cell("<a href=\"index.php?module=config-help_documents&amp;action=edit&amp;sid={$section['sid']}\">{$lang->edit}</a>", array("class" => "align_center", "width" => '60'));
		$table->construct_cell("<a href=\"index.php?module=config-help_documents&amp;action=delete&amp;sid={$section['sid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_section_deletion}')\">{$lang->delete}</a>", array("class" => "align_center", "width" => '90'));
		$table->construct_row();

		$query2 = $db->simple_select("helpdocs", "*", "sid='{$section['sid']}'", array('order_by' => "disporder"));
		while($doc = $db->fetch_array($query2))
		{
			$table->construct_cell("<div style=\"padding-left: 40px;\"><div><strong><a href=\"index.php?module=config-help_documents&amp;action=edit&amp;hid={$doc['hid']}\">{$doc['name']}</a></strong><br /><small>{$doc['description']}</small></div></div>");
			$table->construct_cell("<a href=\"index.php?module=config-help_documents&amp;action=edit&amp;hid={$doc['hid']}\">{$lang->edit}</a>", array("class" => "align_center", "width" => '60'));
			$table->construct_cell("<a href=\"index.php?module=config-help_documents&amp;action=delete&amp;hid={$doc['hid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_document_deletion}')\">{$lang->delete}</a>", array("class" => "align_center", "width" => '90'));
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
	$page->output_footer();
}
