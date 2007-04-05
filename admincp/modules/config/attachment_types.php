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

$page->add_breadcrumb_item("Attachment Types", "index.php?".SID."&amp;module=config/attachment_types");


if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['mimetype']))
		{
			$errors[] = "You did not enter a MIME type for this attachment type";
		}

		if(!trim($mybb->input['extension']))
		{
			$errors[] = "You did not enter a file extension for this attachment type";
		}

		if(!$errors)
		{
			if($mybb->input['mimetype'] == "images/attachtypes/")
			{
				$mybb->input['mimetype'] = '';
			}

			$new_type = array(
				"mimetype" => $db->escape_string($mybb->input['mimetype']),
				"extension" => $db->escape_string($mybb->input['extension']),
				"maxsize" => intval($mybb->input['maxsize']),
				"icon" => $db->escape_string($mybb->input['icon'])
			);

			$db->insert_query("attachtypes", $new_type);

			$cache->update_attachtypes();

			flash_message("The attachment type has successfully been created.", 'success');
			admin_redirect("index.php?".SID."&module=config/attachment_types");
		}
	}

	
	$page->add_breadcrumb_item("Add Attachment Type");
	$page->output_header("Attachment Types - Add Attachment Type");
	
	$sub_tabs['add_attachment_type'] = array(
		'title' => "Add Attachment Type",
		'description' => 'Adding a new attachment type will allow members to attach files of this type to their posts. You have the ability to control the extension, MIME type, maximum size and show a small icon for each attachment type.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'add_attachment_type');

	$form = new Form("index.php?".SID."&amp;module=config/attachment_types&amp;action=add", "post", "add");
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['maxsize'] = '1024';
		$mybb->input['mimetype'] = "images/attachtypes/";
	}
	
	$form_container = new FormContainer("Add New Attachment Type");
	$form_container->output_row("File Extension <em>*</em>", "Enter the file extension you wish to allow uploads for here (Do not include the period before the extension) (Example: txt)", $form->generate_text_box('extension', $mybb->input['extension'], array('id' => 'extension')), 'extension');
	$form_container->output_row("MIME Type <em>*</em>", "Enter the MIME type sent by the server when downloading files of this type (<a href=\"http://www.webmaster-toolkit.com/mime-types.shtml\">See a list here</a>)", $form->generate_text_box('mimetype', $mybb->input['mimetype'], array('id' => 'mimetype')), 'mimetype');
	$form_container->output_row("Maximum File Size (Kilobytes)", "The maximum file size for uploads of this attachment type in Kilobytes (1 MB = 1024 KB)", $form->generate_text_box('maxsize', $mybb->input['maxsize'], array('id' => 'maxsize')), 'maxsize');
	$form_container->output_row("Attachment Icon", "If you wish to show a small attachment icon for attachments of this type then enter the path to it here", $form->generate_text_box('icon', $mybb->input['icon'], array('id' => 'icon')), 'icon');

	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save Attachment Type");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("attachtypes", "*", "atid='".intval($mybb->input['atid'])."'");
	$attachment_type = $db->fetch_array($query);

	if(!$attachment_type['atid'])
	{
		flash_message("You have selected an invalid attachment type.", 'error');
		admin_redirect("index.php?".SID."&module=config/attachment_types");
	}
		
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['mimetype']))
		{
			$errors[] = "You did not enter a MIME type for this attachment type";
		}

		if(!trim($mybb->input['extension']))
		{
			$errors[] = "You did not enter a file extension for this attachment type";
		}

		if(!$errors)
		{
			if($mybb->input['mimetype'] == "images/attachtypes/")
			{
				$mybb->input['mimetype'] = '';
			}

			$updated_type = array(
				"mimetype" => $db->escape_string($mybb->input['mimetype']),
				"extension" => $db->escape_string($mybb->input['extension']),
				"maxsize" => intval($mybb->input['maxsize']),
				"icon" => $db->escape_string($mybb->input['icon'])
			);

			$db->update_query("attachtypes", $updated_type, "atid='{$attachment_type['atid']}'");

			$cache->update_attachtypes();

			flash_message("The attachment type has successfully been updated.", 'success');
			admin_redirect("index.php?".SID."&module=config/attachment_types");
		}
	}
	
	$page->add_breadcrumb_item("Edit Attachment Type");
	$page->output_header("Attachment Types - Edit Attachment Type");
	
	$sub_tabs['edit_attachment_type'] = array(
		'title' => "Edit Attachment Type",
		'description' => 'You have the ability to control the extension, MIME type, maximum size and show a small mimetype for this attachment type.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_attachment_type');

	$form = new Form("index.php?".SID."&amp;module=config/attachment_types&amp;action=edit&amp;atid={$attachment_type['atid']}", "post", "add");

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = $attachment_type;
	}
	
	$form_container = new FormContainer("Edit Attachment Type");
	$form_container->output_row("File Extension <em>*</em>", "Enter the file extension you wish to allow uploads for here (Do not include the period before the extension) (Example: txt)", $form->generate_text_box('extension', $mybb->input['extension'], array('id' => 'extension')), 'extension');
	$form_container->output_row("MIME Type <em>*</em>", "Enter the MIME type sent by the server when downloading files of this type (<a href=\"http://www.webmaster-toolkit.com/mime-types.shtml\">See a list here</a>)", $form->generate_text_box('mimetype', $mybb->input['mimetype'], array('id' => 'mimetype')), 'mimetype');
	$form_container->output_row("Maximum File Size (Kilobytes)", "The maximum file size for uploads of this attachment type in Kilobytes (1 MB = 1024 KB)", $form->generate_text_box('maxsize', $mybb->input['maxsize'], array('id' => 'maxsize')), 'maxsize');
	$form_container->output_row("Attachment Icon", "If you wish to show a small attachment icon for attachments of this type then enter the path to it here", $form->generate_text_box('icon', $mybb->input['icon'], array('id' => 'icon')), 'icon');

	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save Attachment Type");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	if($mybb->input['no']) 
	{ 
		admin_redirect("index.php?".SID."&module=config/attachment_types"); 
	}
	
	$query = $db->simple_select("attachtypes", "atid", "atid='".intval($mybb->input['atid'])."'");
	$atid = $db->fetch_field($query, "atid");

	if(!$atid)
	{
		flash_message("You have selected an invalid attachment type.", 'error');
		admin_redirect("index.php?".SID."&module=config/attachment_types");
	}
	
	if($mybb->request_method == "post")
	{
		$db->delete_query("attachtypes", "atid='{$atid}'");

		$cache->update_attachtypes();

		flash_message("The attachment type has successfully been deleted.", 'success');
		admin_redirect("index.php?".SID."&module=config/attachment_types");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=config/attachment_types&amp;action=delete&amp;atid={$mybb->input['atid']}", "Are you sure you wish to delete this attachment type?"); 
	}
}

if(!$mybb->input['action'])
{
	$page->output_header("Attachment Types");

	$sub_tabs['attachment_types'] = array(
		'title' => "Attachment Types",
		'link' => "index.php?".SID."&amp;module=config/attachment_types",
		'description' => "Here you can create and manage attachment types which define which types of files users can attach to posts."
	);
	$sub_tabs['add_attachment_type'] = array(
		'title' => "Add New Attachment Type",
		'link' => "index.php?".SID."&amp;module=config/attachment_types&amp;action=add",
	);

	$page->output_nav_tabs($sub_tabs, 'attachment_types');
	
	$table = new Table;
	$table->construct_header("Extension", array("colspan" => 2));
	$table->construct_header("MIME Type");
	$table->construct_header("Maximum Size", array("class" => "align_center"));
	$table->construct_header("Controls", array("class" => "align_center", "colspan" => 2));
	
	$query = $db->simple_select("attachtypes", "*", "", array('order_by' => 'extension'));
	while($attachment_type = $db->fetch_array($query))
	{
		if(!$attachment_type['icon'] || $attachment_type['icon'] == "images/attachtypes/")
		{
			$attachment_type['icon'] = "&nbsp;";
		}
		else
		{
			$attachment_type['icon'] = "<img src=\"../{$attachment_type['icon']}\" alt=\"\" />";
		}
		$table->construct_cell($attachment_type['icon'], array("width" => 1));
		$table->construct_cell("<strong>.{$attachment_type['extension']}</strong>");
		$table->construct_cell($attachment_type['mimetype']);
		$table->construct_cell(get_friendly_size($attachment_type['maxsize']), array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/attachment_types&amp;action=edit&amp;atid={$attachment_type['atid']}\">Edit</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/attachment_types&amp;action=delete&amp;atid={$attachment_type['atid']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this attachment type?')\">Delete</a>", array("class" => "align_center"));
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are no attachment types on your forum at this time.", array('colspan' => 6));
		$table->construct_row();
	}
	
	$table->output("Attachment Types");
	
	$page->output_footer();
}
?>