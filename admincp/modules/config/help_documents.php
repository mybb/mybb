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

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item("Help Documents", "index.php?".SID."&amp;module=config/help_documents");

if($mybb->input['action'] == "add")
{
	if($mybb->input['type'] == "section")
	{
		if($mybb->request_method == "post")
		{
			if(empty($mybb->input['name']))
			{
				$errors[] = "You must specify a name for the section.";
			}
			
			if(empty($mybb->input['description']))
			{
				$errors[] = "You must specify a short description for the section.";
			}
			
			if(!isset($mybb->input['enabled']))
			{
				$errors[] = "You must specify yes or no for \"Enabled?\"\.";
			}
			
			if(!isset($mybb->input['translation']))
			{
				$errors[] = "You must specify yes or no for \"Use Translation?\".";
			}
			
			if($mybb->input['enabled'] == 1)
			{
				$mybb->input['enabled'] = "yes";
			}
			else
			{
				$mybb->input['enabled'] = "no";
			}
			
			if($mybb->input['translation'] == 1)
			{
				$mybb->input['translation'] = "yes";
			}
			else
			{
				$mybb->input['translation'] = "no";
			}
			
			if(!is_array($errors))
			{
				$sql_array = array(
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"usetranslation" => $db->escape_string($mybb->input['translation']),
					"enabled" => $db->escape_string($mybb->input['enabled']),
					"disporder" => intval($mybb->input['disporder'])
				);
				
				$db->insert_query("helpsections", $sql_array);
				
				flash_message('The help section has been added successfully.', 'success');
				admin_redirect('index.php?'.SID.'&module=config/help_documents');
			}
		}
	
		$page->add_breadcrumb_item("Add Section");
		$page->output_header("Help Documents - Add New Section");
		
		
		$sub_tabs['add_help_section'] = array(
			'title'	=> "Add New Section",
			'link'	=> "index.php?".SID."&amp;module=config/help_documents&amp;action=add&amp;type=section",
			'description' => "Here you can add a new help section."
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
	
		$form = new Form("index.php?".SID."&amp;module=config/help_documents&amp;action=add&amp;type=section", "post", "add");
		$form_container = new FormContainer("Add New Section");
		$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row("Short Description <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row("Display Order", "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
		$form_container->output_row("Enabled? <em>*</em>", "", $form->generate_yes_no_radio('enabled', $mybb->input['enabled']), 'enabled');
		$form_container->output_row("Use Translation? <em>*</em>", "", $form->generate_yes_no_radio('translation', $mybb->input['translation']), 'translation');
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button("Add Section");
	
		$form->output_submit_wrapper($buttons);
		$form->end();
	}
	else
	{
		if($mybb->request_method == "post")
		{
			if(empty($mybb->input['sid']))
			{
				$errors[] = "You must specify a section for the document.";
			}
			
			if(empty($mybb->input['name']))
			{
				$errors[] = "You must specify a name for the document.";
			}
			
			if(empty($mybb->input['description']))
			{
				$errors[] = "You must specify a short description for the document.";
			}
			
			if(empty($mybb->input['document']))
			{
				$errors[] = "You must specify a document for the document.";
			}
			
			if(!isset($mybb->input['enabled']))
			{
				$errors[] = "You must specify yes or no for \"Enabled?\"\.";
			}
			
			if(!isset($mybb->input['translation']))
			{
				$errors[] = "You must specify yes or no for \"Use Translation?\".";
			}
			
			if($mybb->input['enabled'] == 1)
			{
				$mybb->input['enabled'] = "yes";
			}
			else
			{
				$mybb->input['enabled'] = "no";
			}
			
			if($mybb->input['translation'] == 1)
			{
				$mybb->input['translation'] = "yes";
			}
			else
			{
				$mybb->input['translation'] = "no";
			}
			
			if(!is_array($errors))
			{
				$sql_array = array(
					"sid" => intval($mybb->input['sid']),
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"document" => $db->escape_string($mybb->input['document']),
					"usetranslation" => $db->escape_string($mybb->input['translation']),
					"enabled" => $db->escape_string($mybb->input['enabled']),
					"disporder" => intval($mybb->input['disporder'])
				);
				
				$db->insert_query("helpdocs", $sql_array);
				
				flash_message('The help document has been added successfully.', 'success');
				admin_redirect('index.php?'.SID.'&module=config/help_documents');
			}
		}
	
		$page->add_breadcrumb_item("Add Document");
		$page->output_header("Help Documents - Add New Document");
		
		
		$sub_tabs['add_help_document'] = array(
			'title'	=> "Add New Document",
			'link'	=> "index.php?".SID."&amp;module=config/help_documents&amp;action=add&amp;type=document",
			'description' => "Here you can add a new help document."
		);
	
		$page->output_nav_tabs($sub_tabs, 'add_help_document');
	
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$query = $db->simple_select("helpdocs", "MAX(disporder) as maxdisp");
			$mybb->input['disporder'] = $db->fetch_field($query, "maxdisp")+1;
			$mybb->input['enabled'] = 1;
			$mybb->input['translation'] = 0;
		}
	
		$form = new Form("index.php?".SID."&amp;module=config/help_documents&amp;action=add&amp;type=document", "post", "add");
		$form_container = new FormContainer("Add New Document");
		$query = $db->simple_select("helpsections", "sid, name");
		while($section = $db->fetch_array($query))
		{
			$sections[$section['sid']] = $section['name'];
		}
		$form_container->output_row("Section <em>*</em>", "", $form->generate_select_box("sid", $sections, $mybb->input['sid']), 'sid');
		$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row("Short Description <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row("Document <em>*</em>", "", $form->generate_text_area('document', $mybb->input['document'], array('id' => 'document')), 'document');
		$form_container->output_row("Display Order", "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
		$form_container->output_row("Enabled? <em>*</em>", "", $form->generate_yes_no_radio('enabled', $mybb->input['enabled']), 'enabled');
		$form_container->output_row("Use Translation? <em>*</em>", "", $form->generate_yes_no_radio('translation', $mybb->input['translation']), 'translation');
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button("Add Document");
	
		$form->output_submit_wrapper($buttons);
		$form->end();
	}

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	if($mybb->input['sid'] && !$mybb->input['hid'])
	{
		if($mybb->request_method == "post")
		{
			$sid = intval($mybb->input['sid']);
			
			if(empty($sid))
			{
				$errors[] = "Invalid document id specified.";
			}
			
			if(empty($mybb->input['name']))
			{
				$errors[] = "You must specify a name for the section.";
			}
			
			if(empty($mybb->input['description']))
			{
				$errors[] = "You must specify a short description for the section.";
			}
			
			if(!isset($mybb->input['enabled']))
			{
				$errors[] = "You must specify yes or no for \"Enabled?\"\.";
			}
			
			if(!isset($mybb->input['translation']))
			{
				$errors[] = "You must specify yes or no for \"Use Translation?\".";
			}
			
			if($mybb->input['enabled'] == 1)
			{
				$mybb->input['enabled'] = "yes";
			}
			else
			{
				$mybb->input['enabled'] = "no";
			}
			
			if($mybb->input['translation'] == 1)
			{
				$mybb->input['translation'] = "yes";
			}
			else
			{
				$mybb->input['translation'] = "no";
			}
			
			if(!is_array($errors))
			{
				$sql_array = array(
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"usetranslation" => $db->escape_string($mybb->input['translation']),
					"enabled" => $db->escape_string($mybb->input['enabled']),
					"disporder" => intval($mybb->input['disporder'])
				);
				
				$db->update_query("helpsections", $sql_array, "sid = '{$sid}'");
				
				flash_message('The help section has been edited successfully.', 'success');
				admin_redirect('index.php?'.SID.'&module=config/help_documents');
			}
		}
	
		$page->add_breadcrumb_item("Edit Section");
		$page->output_header("Help Documents - Edit Section");
		
		
		$sub_tabs['edit_help_section'] = array(
			'title'	=> "Edit Section",
			'link'	=> "index.php?".SID."&amp;module=config/help_documents&amp;action=edit&amp;sid=".intval($mybb->input['sid']),
			'description' => "Here you can edit a help section."
		);
	
		$page->output_nav_tabs($sub_tabs, 'edit_help_section');
	
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$query = $db->simple_select("helpsections", "*", "sid = '".intval($mybb->input['sid'])."'");
			$section = $db->fetch_array($query);
			$mybb->input['name'] = $section['name'];
			$mybb->input['description'] = $section['description'];
			$mybb->input['disporder'] = $section['disporder'];
			$mybb->input['enabled'] = $section['enabled'];
			$mybb->input['translation'] = $section['usetranslation'];
		}
	
		$form = new Form("index.php?".SID."&amp;module=config/help_documents&amp;action=edit", "post", "edit");
		
		echo $form->generate_hidden_field("sid", $section['sid']);
		
		$form_container = new FormContainer("Edit Section");
		$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row("Short Description <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row("Display Order", "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
		$form_container->output_row("Enabled? <em>*</em>", "", $form->generate_yes_no_radio('enabled', $mybb->input['enabled']), 'enabled');
		$form_container->output_row("Use Translation? <em>*</em>", "", $form->generate_yes_no_radio('translation', $mybb->input['translation']), 'translation');
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button("Edit Section");
	
		$form->output_submit_wrapper($buttons);
		$form->end();
	}
	else
	{
		if($mybb->request_method == "post")
		{
			$hid = intval($mybb->input['hid']);
			
			if(empty($hid))
			{
				$errors[] = "Invalid document id specified.";
			}
			
			if(empty($mybb->input['sid']))
			{
				$errors[] = "You must specify a section for the document.";
			}
			
			if(empty($mybb->input['name']))
			{
				$errors[] = "You must specify a name for the document.";
			}
			
			if(empty($mybb->input['description']))
			{
				$errors[] = "You must specify a short description for the document.";
			}
			
			if(empty($mybb->input['document']))
			{
				$errors[] = "You must specify a document for the document.";
			}
			
			if(!isset($mybb->input['enabled']))
			{
				$errors[] = "You must specify yes or no for \"Enabled?\"\.";
			}
			
			if(!isset($mybb->input['translation']))
			{
				$errors[] = "You must specify yes or no for \"Use Translation?\".";
			}
			
			if($mybb->input['enabled'] == 1)
			{
				$mybb->input['enabled'] = "yes";
			}
			else
			{
				$mybb->input['enabled'] = "no";
			}
			
			if($mybb->input['translation'] == 1)
			{
				$mybb->input['translation'] = "yes";
			}
			else
			{
				$mybb->input['translation'] = "no";
			}
			
			if(!is_array($errors))
			{
				$sql_array = array(
					"sid" => intval($mybb->input['sid']),
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"document" => $db->escape_string($mybb->input['document']),
					"usetranslation" => $db->escape_string($mybb->input['translation']),
					"enabled" => $db->escape_string($mybb->input['enabled']),
					"disporder" => intval($mybb->input['disporder'])
				);
				
				$db->update_query("helpdocs", $sql_array, "hid = '{$hid}'");
				
				flash_message('The help document has been edited successfully.', 'success');
				admin_redirect('index.php?'.SID.'&module=config/help_documents');
			}
		}
	
		$page->add_breadcrumb_item("Edit Document");
		$page->output_header("Help Documents - Edit Document");
		
		
		$sub_tabs['edit_help_document'] = array(
			'title'	=> "Edit Document",
			'link'	=> "index.php?".SID."&amp;module=config/help_documents&amp;action=edit&amp;hid=".intval($mybb->input['hid']),
			'description' => "Here you can edit a help document."
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
			$mybb->input['sid'] = $doc['sid'];
			$mybb->input['name'] = $doc['name'];
			$mybb->input['description'] = $doc['description'];
			$mybb->input['document'] = $doc['document'];
			$mybb->input['disporder'] = $doc['disporder'];
			$mybb->input['enabled'] = $doc['enabled'];
			$mybb->input['translation'] = $doc['usetranslation'];
		}
	
		$form = new Form("index.php?".SID."&amp;module=config/help_documents&amp;action=edit", "post", "edit");
		
		echo $form->generate_hidden_field("hid", $doc['hid']);
				
		$form_container = new FormContainer("Edit Document");
		
		$query = $db->simple_select("helpsections", "sid, name");
		while($section = $db->fetch_array($query))
		{
			$sections[$section['sid']] = $section['name'];
		}
		$form_container->output_row("Section <em>*</em>", "", $form->generate_select_box("sid", $sections, $mybb->input['sid']), 'sid');
		$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row("Short Description <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row("Document <em>*</em>", "", $form->generate_text_area('document', $mybb->input['document'], array('id' => 'document')), 'document');
		$form_container->output_row("Display Order", "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
		$form_container->output_row("Enabled? <em>*</em>", "", $form->generate_yes_no_radio('enabled', $mybb->input['enabled']), 'enabled');
		$form_container->output_row("Use Translation? <em>*</em>", "", $form->generate_yes_no_radio('translation', $mybb->input['translation']), 'translation');
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button("Edit Document");
		
		$form->output_submit_wrapper($buttons);
		$form->end();
	}

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=config/help_documents");
	}

	if($mybb->request_method == "post")
	{
		if(isset($mybb->input['sid']))
		{
			$sid = intval($mybb->input['sid']);
			
			$query = $db->simple_select("helpsections", "*", "sid='{$sid}'");
			$section = $db->fetch_array($query);
	
			if(!$section['sid'])
			{
				flash_message('The specified section does not exist.', 'error');
				admin_redirect("index.php?".SID."&module=config/help_documents");
			}
			
			if($sid <= 2)
			{
				flash_message('Deleting a default help section is not allowed.', 'error');
				admin_redirect("index.php?".SID."&module=config/help_documents");
			}
			
			$db->delete_query("helpsections", "sid = '{$sid}'", 1);
			$db->delete_query("helpdocs", "sid = '{$sid}'");
			
			flash_message('The specified section has been deleted.', 'success');
			admin_redirect("index.php?".SID."&module=config/help_documents");
		}
		else
		{
			$hid = intval($mybb->input['hid']);
			
			$query = $db->simple_select("helpdocs", "*", "hid='{$hid}'");
			$doc = $db->fetch_array($query);
	
			if(!$doc['hid'])
			{
				flash_message('The specified document does not exist.', 'error');
				admin_redirect("index.php?".SID."&module=config/help_documents");
			}			
			
			if($hid <= 7)
			{
				flash_message('Deleting a default help document is not allowed.', 'error');
				admin_redirect("index.php?".SID."&module=config/help_documents");
			}
			
			$db->delete_query("helpdocs", "hid = '{$hid}'", 1);
			
			flash_message('The specified document has been deleted.', 'success');
			admin_redirect("index.php?".SID."&module=config/help_documents");
		}
	}
	else
	{
		if(isset($mybb->input['sid']))
		{
			$sid = intval($mybb->input['sid']);
			$page->output_confirm_action("index.php?".SID."&amp;module=config/help_documents&amp;action=delete&amp;sid={$sid}", "Are you sure you wish to delete this section?");
		}
		else
		{
			$hid = intval($mybb->input['hid']);
			$page->output_confirm_action("index.php?".SID."&amp;module=config/help_documents&amp;action=delete&amp;hid={$hid}", "Are you sure you wish to delete this document?");
		}
	}
}

if(!$mybb->input['action'])
{
	$page->output_header("Help Documents");

	$sub_tabs['manage_help_documents'] = array(
		'title'	=> "Manage Help Documents",
		'link'	=> "index.php?".SID."&amp;module=config/help_documents",
		'description'	=> "This section allows you to edit and delete and manage your help documents."
	);

	$sub_tabs['add_help_document'] = array(
		'title'	=> "Add New Document",
		'link'	=> "index.php?".SID."&amp;module=config/help_documents&amp;action=add&amp;type=document",
		'description'	=> "Here you can add a new help document."
	);
	
	$sub_tabs['add_help_section'] = array(
		'title'	=> "Add New Section",
		'link'	=> "index.php?".SID."&amp;module=config/help_documents&amp;action=add&amp;type=section",
		'description'	=> "Here you can add a new help section."
	);

	$page->output_nav_tabs($sub_tabs, 'manage_help_documents');

	$table = new Table;
	$table->construct_header("Section / Document");
	$table->construct_header("Controls", array('class' => "align_center", 'colspan' => 2, "width" => "150"));

	$query = $db->simple_select("helpsections", "*", "", array('order_by' => "disporder"));
	while($section = $db->fetch_array($query))
	{
		if($section['sid'] > 2)
		{
			$icon = '<img src="styles/default/images/icons/custom.gif" alt="Custom Document/Section" style="vertical-align: middle;" />';
		}
		else
		{
			$icon = '<img src="styles/default/images/icons/default.gif" alt="Default Document/Section" style="vertical-align: middle;" />';
		}
		$table->construct_cell("<div class=\"float_right\">{$icon}</div><div><strong>{$section['name']}</strong><br /><small>{$section['description']}</small></div>");
 
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/help_documents&amp;action=edit&amp;sid={$section['sid']}\">Edit</a>", array("class" => "align_center", "width" => '60'));
		if($section['sid'] > 2)
		{
			$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/help_documents&amp;action=delete&amp;sid={$section['sid']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this section?')\">Delete</a>", array("class" => "align_center", "width" => '90'));
		}
		else
		{
			$table->construct_cell("&nbsp;", array("width" => '90'));
		}
		$table->construct_row();
			
		$query2 = $db->simple_select("helpdocs", "*", "sid='{$section['sid']}'", array('order_by' => "disporder"));
		while($doc = $db->fetch_array($query2))
		{
			if($doc['hid'] > 7)
			{
				$icon = '<img src="styles/default/images/icons/custom.gif" alt="Custom Document/Section" style="vertical-align: middle;" />';
			}
			else
			{
				$icon = '<img src="styles/default/images/icons/default.gif" alt="Default Document/Section" style="vertical-align: middle;" />';
			}
			$table->construct_cell("<div class=\"float_right\">{$icon}</div><div>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>{$doc['name']}</strong><br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<small>{$doc['description']}</small></div>");

			$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/help_documents&amp;action=edit&amp;hid={$doc['hid']}\">Edit</a>", array("class" => "align_center", "width" => '60'));
			
			if($doc['hid'] > 7)
			{
				$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/help_documents&amp;action=delete&amp;hid={$doc['hid']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this document?')\">Delete</a>", array("class" => "align_center", "width" => '90'));
			}
			else
			{
				$table->construct_cell("&nbsp;", array("width" => '90'));
			}
			$table->construct_row();
		}
	}

	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are no help documents on your forum at this time.", array('colspan' => 3));
		$table->construct_row();
	}

	$table->output("Help Documents");
	
	echo <<<LEGEND
	<fieldset>
<legend>Legend</legend>
<img src="styles/default/images/icons/custom.gif" alt="Custom Document/Section" style="vertical-align: middle;" /> Custom Document/Section<br />
<img src="styles/default/images/icons/default.gif" alt="Default Document/Section" style="vertical-align: middle;" /> Default Document/Section
</fieldset>
LEGEND;

	$page->output_footer();
}
?>