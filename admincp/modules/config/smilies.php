<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/license.php
 *
 * $Id: plugins.php 2918 2007-03-05 00:50:41Z Tikitiki $
 */

// TODO:
// - Add multiple smilies
// - Finish Mass Edit (Testing, updating)


$page->add_breadcrumb_item("Smilies", "index.php?".SID."&amp;module=config/smilies");


// Activates or deactivates a specific plugin
if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = "You did not enter a name for this smilie";
		}

		if(!trim($mybb->input['replacement']))
		{
			$errors[] = "You did not enter a text replacement for this smilie";
		}

		if(!trim($mybb->input['path']))
		{
			$errors[] = "You did not enter a path for this smilie";
		}

		if(!trim($mybb->input['disporder']))
		{
			$errors[] = "You did not enter a display order for this smilie";
		}
		
		if(!trim($mybb->input['showclickable']))
		{
			$errors[] = "You did not select Yes or No for the \"Show Clickable\" option";
		}

		if(!$errors)
		{
			$new_smilie = array(
				"name" => $db->escape_string($mybb->input['name']),
				"find" => $db->escape_string($mybb->input['replacement']),
				"image" => $db->escape_string($mybb->input['path']),
				"disporder" => intval($mybb->input['disporder']),
				"showclickable" => $db->escape_string($mybb->input['showclickable'])
			);
			
			$db->insert_query("smilies", $new_smilie);
			
			flash_message("The smilie has been added successfully.", 'success');
			admin_redirect("index.php?".SID."&module=config/smilies");
		}
	}
	
	$page->add_breadcrumb_item("Add Smilie");
	$page->output_header("Smilies - Add Smilie");
	
	$sub_tabs['add_smilie'] = array(
		'title' => "Add Smilie",
		'link' => "index.php?".SID."&amp;module=config/smilies&amp;action=add",
		'description' => 'Here you can add a new smilie.'
	);
	$sub_tabs['add_multiple_smilies'] = array(
		'title' => "Add Multiple Smilies",
		'link' => "index.php?".SID."&amp;module=config/smilies&amp;action=add_multiple",
	);
	
	$page->output_nav_tabs($sub_tabs, 'add_smilie');
	$form = new Form("index.php?".SID."&amp;module=config/smilies&amp;action=add", "post", "add");
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['path'] = 'images/smilies/';
		$mybb->input['showclickable'] = 'yes';
	}
	
	if(!$mybb->input['disporder'])
	{
		$query = $db->simple_select("smilies", "max(disporder) as dispordermax");
		$mybb->input['disporder'] = $db->fetch_field($query, "dispordermax")+1;
	}
	
	$form_container = new FormContainer("Add Smilie");
	$form_container->output_row("Name", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row("Text to Replace", "", $form->generate_text_box('replacement', $mybb->input['replacement'], array('id' => 'replacement')), 'replacement');
	$form_container->output_row("Image Path", "This is the path to the smilie image.", $form->generate_text_box('path', $mybb->input['path'], array('id' => 'path')), 'path');
	$form_container->output_row("Display Order", "The order on the smilies list that this will appear. This number should not be the same as another smilie's.", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
	$form_container->output_row("Show on clickable list?", "Do you want this smilie to show on the clickable smilie list on the post editor?", $form->generate_yes_no_radio('showclickable', $mybb->input['showclickable']), 'showclickable');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save Smilie");
	$buttons[] = $form->generate_reset_button("Reset");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	if($mybb->input['no']) 
	{ 
		admin_redirect("index.php?".SID."&module=config/smilies"); 
	}
	
	if(!trim($mybb->input['sid']))
	{
		flash_message('You did not enter a smilie to delete', 'error');
		admin_redirect("index.php?".SID."&module=config/smilies");
	}
	
	$sid = intval($mybb->input['sid']);
	
	$query = $db->simple_select("smilies", "COUNT(sid) as smilies", "sid = '{$sid}'");
	if($db->fetch_field($query, "smilies") == 0)
	{
		flash_message('You did not enter a valid smilie', 'error');
		admin_redirect("index.php?".SID."&module=config/smilies");
	}
	
	if($mybb->request_method == "post")
	{
		$db->delete_query("smilies", "sid = '{$sid}'", 1);
		flash_message('Smilie Delete Successfully', 'success');
		admin_redirect("index.php?".SID."&module=config/smilies");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=config/smilies&amp;action=delete&amp;sid={$mybb->input['sid']}", "Are you sure you wish to delete this smilie?"); 
	}
}

if($mybb->input['action'] == "mass_edit")
{
	$page->output_header("Smilies - Mass Edit");

	$sub_tabs['mass_edit'] = array(
		'title' => "Mass Edit",
		'link' => "index.php?".SID."&amp;module=config/smilies&amp;action=mass_edit",
		'description' => 'Here you can easily edit all your smilies in one go.'
	);
	
	$page->output_nav_tabs($sub_tabs, 'mass_edit');
	
	$form = new Form("index.php?".SID."&amp;module=config/smilies&amp;action=add", "post", "add");
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['path'] = 'images/smilies/';
		$mybb->input['showclickable'] = 'yes';
	}
	
	if(!$mybb->input['disporder'])
	{
		$query = $db->simple_select("smilies", "max(disporder) as dispordermax");
		$mybb->input['disporder'] = $db->fetch_field($query, "dispordermax")+1;
	}
	
	$form_container = new FormContainer("Manage Smilie");
	$form_container->output_row_header("Image", array("class" => "align_center", 'width' => '10%'));
	$form_container->output_row_header("Name", array('width' => '40%'));
	$form_container->output_row_header("Text to Replace", array('width' => '20%'));
	$form_container->output_row_header("Order", array('width' => '5%'));
	$form_container->output_row_header("Show on clickable list?", array('width' => '20%'));
	$form_container->output_row_header("Delete?", array("class" => "align_center", 'width' => '5%'));
	
	$query = $db->simple_select("smilies");
	while($smilie = $db->fetch_array($query))
	{
		$smilie['image'] = str_replace("{theme:imgdir}", $theme['imgdir'], $smilie['image']);
		if(my_strpos($smilie['image'], "p://") || substr($smilie['image'], 0, 1) == "/") 
		{
			$image = $smilie['image'];
		}
		else
		{
			$image = "../".$smilie['image'];
		}
		
		$form_container->output_cell("<img src=\"{$image}\" alt=\"\" />", array("class" => "align_center", 'width' => '10%'));
		$form_container->output_cell($form->generate_text_box('name', $smilie['name'], array('id' => 'name', 'style' => 'width: 98%')), array('width' => '50%'));
		$form_container->output_cell($form->generate_text_box('replacement', $smilie['find'], array('id' => 'replacement', 'style' => 'width: 95%')), array('width' => '10%'));
		$form_container->output_cell($form->generate_text_box('disporder', $smilie['disporder'], array('id' => 'disporder', 'style' => 'width: 80%')), array('width' => '5%'));
		$form_container->output_cell($form->generate_yes_no_radio('showclickable', $smilie['showclickable']), array("class" => "align_center", 'width' => '10%'));
		$form_container->output_cell($form->generate_check_box('delete', $mybb->input['delete']), array("class" => "align_center", 'width' => '5%'));
		$form_container->construct_row();
	}
	
	if(count($form_container->container->rows) == 0)
	{
		$form_container->output_cell("There are no smilies on your forum at this time.", array('colspan' => 6));
		$form_container->construct_row();
	}
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button("Save Smilie");
	$buttons[] = $form->generate_reset_button("Reset");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header("Manage Smilies");

	$sub_tabs['manage_smilies'] = array(
		'title' => "Manage Smilies",
		'link' => "index.php?".SID."&amp;module=config/smilies",
		'description' => "This section allows you to edit, delete, and manage your smilies."
	);
	$sub_tabs['add_smilie'] = array(
		'title' => "Add Smilie",
		'link' => "index.php?".SID."&amp;module=config/smilies&amp;action=add",
	);
	$sub_tabs['add_multiple_smilies'] = array(
		'title' => "Add Multiple Smilies",
		'link' => "index.php?".SID."&amp;module=config/smilies&amp;action=add_multiple",
	);
	$sub_tabs['mass_edit'] = array(
		'title' => "Mass Edit",
		'link' => "index.php?".SID."&amp;module=config/smilies&amp;action=mass_edit",
	);
	
	$page->output_nav_tabs($sub_tabs, 'manage_smilies');
	
	$pagenum = intval($mybb->input['page']);	
	if($pagenum)
	{
		$start = ($pagenum-1) * 20;
	}
	else
	{
		$start = 0;
		$pagenum = 1;
	}
	
	
	$table = new Table;
	$table->construct_header("Image", array("class" => "align_center"));
	$table->construct_header("Name");
	$table->construct_header("Text to Replace");
	$table->construct_header("Controls", array("class" => "align_center"));
	
	$query = $db->simple_select("smilies", "*", "", array('limit_start' => $start, 'limit' => 20, 'order_by' => 'disporder'));
	while($smilie = $db->fetch_array($query))
	{
		$smilie['image'] = str_replace("{theme:imgdir}", $theme['imgdir'], $smilie['image']);
		if(my_strpos($smilie['image'], "p://") || substr($smilie['image'], 0, 1) == "/") 
		{
			$image = $smilie['image'];
		}
		else
		{
			$image = "../".$smilie['image'];
		}
		
		$table->construct_cell("<img src=\"{$image}\" alt=\"\" />", array("class" => "align_center", 'width' => '10%'));
		$table->construct_cell("{$smilie['name']}", array('width' => '60%'));
		$table->construct_cell($smilie['find'], array('width' => '20%'));
		
		$popup = new PopupMenu("smilie_{$smilie['sid']}", "Options");
		$popup->add_item("Edit Smilie", "index.php?".SID."&amp;module=config/smilies&amp;action=edit&amp;sid={$smilie['sid']}");
		$popup->add_item("Delete Smilie", "index.php?".SID."&amp;module=config/smilies&amp;action=delete&amp;sid={$smilie['sid']}", "return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this smilie?')");
		$table->construct_cell($popup->fetch(), array("class" => "align_center", 'width' => '10%'));
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->contruct_cell("There are no smilies on your forum at this time.", array('colspan' => 2));
		$table->construct_row();
	}
	
	$table->output("Manage Smilies");
	
	$query = $db->simple_select("smilies", "COUNT(sid) as smilies");
	$total_rows = $db->fetch_field($query, "smilies");
	
	echo "<br />".draw_admin_pagination($pagenum, "20", $total_rows, "index.php?".SID."&amp;module=config/smilies&amp;page={page}");

	$page->output_footer();
}
?>