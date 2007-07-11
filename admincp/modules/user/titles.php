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

$page->add_breadcrumb_item("User Titles", "index.php?".SID."&amp;module=user/titles");

if($mybb->input['action'] == "add" || !$mybb->input['action'])
{
	$sub_tabs['manage_titles'] = array(
		'title' => "User Titles",
		'link' => "index.php?".SID."&amp;module=user/titles",
		'description' => "This section allows management of user titles. User titles are assigned to users based on the number of posts they make and also allow a custom 'Star' image to be shown based on the number of posts the user has."
	);
	$sub_tabs['add_title'] = array(
		'title' => "Add New User Title",
		'link' => "index.php?".SID."&amp;module=user/titles&amp;action=add",
		'description' => "This section allows you to add a new user title. <i>Note: This is <strong>not</strong> not the <u><a href=\"index.php?".SID."&amp;module=user/group_promotions\">promotion system.</a></u><i>"
	);
}

if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this new user title";
		}

		if(!isset($mybb->input['posts']))
		{
			$errors[] = "You did not enter the minimum number of posts for this user title";
		}

		if(!$errors)
		{
			$new_title = array(
				"title" => $db->escape_string($mybb->input['title']),
				"posts" => intval($mybb->input['posts']),
				"stars" => intval($mybb->input['stars']),
				"starimage" => $db->escape_string($mybb->input['starimage'])
			);
			
			$db->insert_query("usertitles", $new_title);
			
			flash_message("The new user title has successfully been created", 'success');
			admin_redirect("index.php?".SID."&module=user/titles");
		}
	}
	else
	{
		$mybb->input = array(
			"stars" => "1",
			"starimage" => "star.gif",
		);
	}
	
	$page->add_breadcrumb_item("Add New User Title");
	$page->output_header("User Titles - Add User Title");
	
	$page->output_nav_tabs($sub_tabs, 'add_title');
	$form = new Form("index.php?".SID."&amp;module=user/titles&amp;action=add", "post");
	
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form_container = new FormContainer("Add New User Title");
	$form_container->output_row("Title to Assign<em>*</em>", "This title will be shown for users underneith their name if they do not have a custom title set.", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Minimum Posts", "The minimum number of posts for a user to have before they're assigned this user title.", $form->generate_text_box('posts', $mybb->input['posts'], array('id' => 'posts')), 'posts');
	$form_container->output_row("Number of Stars", "Enter the number of stars to be shown under this user title. Set to 0 to show no stars.", $form->generate_text_box('stars', $mybb->input['stars'], array('id' => 'stars')), 'stars');
	$form_container->output_row("Star Image", "If this user title should show stars, enter the path to the star image here. If empty, the user group star image will be shown. Use {theme} to specify the image directory for the viewers current theme.", $form->generate_text_box('starimage', $mybb->input['starimage'], array('id' => 'starimage')), 'starimage');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save User Title");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("usertitles", "*", "utid='".intval($mybb->input['utid'])."'");
	$usertitle = $db->fetch_array($query);

	if(!$usertitle['utid'])
	{
		flash_message("You have specified an invalid user title", 'error');
		admin_redirect("index.php?".SID."&module=user/titles");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this user title";
		}

		if(!isset($mybb->input['posts']))
		{
			$errors[] = "You did not enter the minimum number of posts for this user title";
		}

		if(!$errors)
		{
			$updated_title = array(
				"title" => $db->escape_string($mybb->input['title']),
				"posts" => intval($mybb->input['posts']),
				"stars" => intval($mybb->input['stars']),
				"starimage" => $db->escape_string($mybb->input['starimage'])
			);
			
			$db->update_query("usertitles", $updated_title, "utid='{$usertitle['utid']}'");
			
			flash_message("The user title has successfully been updated.", 'success');
			admin_redirect("index.php?".SID."&module=user/titles");
		}
	}

	$page->add_breadcrumb_item("Edit User Title");
	$page->output_header("User Titles - Edit User Title");
	
	$sub_tabs['edit_title'] = array(
		'title' => "Edit User Title",
		'link' => "index.php?".SID."&amp;module=user/titles&amp;action=edit&amp;uid=".$mybb->input['uid'],
		'description' => "This section allows you to edit a user title."
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_title');
	$form = new Form("index.php?".SID."&amp;module=user/titles&amp;action=edit&amp;utid={$usertitle['utid']}", "post");
	
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = $usertitle;
	}

	$form_container = new FormContainer("Edit User Title");
	$form_container->output_row("Title to Assign<em>*</em>", "This title will be shown for users underneith their name if they do not have a custom title set.", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Minimum Posts", "The minimum number of posts for a user to have before they're assigned this user title.", $form->generate_text_box('posts', $mybb->input['posts'], array('id' => 'posts')), 'posts');
	$form_container->output_row("Number of Stars", "Enter the number of stars to be shown under this user title. Set to 0 to show no stars.", $form->generate_text_box('stars', $mybb->input['stars'], array('id' => 'stars')), 'stars');
	$form_container->output_row("Star Image", "If this user title should show stars, enter the path to the star image here. If empty, the user group star image will be shown. Use {theme} to specify the image directory for the viewers current theme.", $form->generate_text_box('starimage', $mybb->input['starimage'], array('id' => 'starimage')), 'starimage');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save User Title");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();

}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("usertitles", "*", "utid='".intval($mybb->input['utid'])."'");
	$usertitle = $db->fetch_array($query);

	if(!$usertitle['utid'])
	{
		flash_message("You have specified an invalid user title", 'error');
		admin_redirect("index.php?".SID."&module=user/titles");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=user/titles");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query("usertitles", "utid='{$usertitle['utid']}'");

		flash_message("The specified user title has successfully been deleted.", 'success');
		admin_redirect("index.php?".SID."&module=user/titles");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=user/titles&amp;action=delete&amp;utid={$usertitle['utid']}", "Are you sure you want to delete this user title?");
	}
}

if(!$mybb->input['action'])
{
	$page->output_header("Manage User Titles");

	$page->output_nav_tabs($sub_tabs, 'manage_titles');

	$table = new Table;
	$table->construct_header("User Title");
	$table->construct_header("Minimum Posts", array('width' => '130', 'class' => 'align_center'));
	$table->construct_header($lang->controls, array("class" => "align_center", "colspan" => 2, "width" => 200));
	
	$query = $db->simple_select("usertitles", "*", "", array('order_by' => 'posts'));
	while($usertitle = $db->fetch_array($query))
	{
		$usertitle['title'] = htmlspecialchars_uni($usertitle['title']);
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=user/titles&amp;action=edit&amp;utid={$usertitle['utid']}\">{$usertitle['title']}</a>");
		$table->construct_cell($usertitle['posts'], array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=user/titles&amp;action=edit&amp;utid={$usertitle['utid']}\">Edit</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=user/titles&amp;action=delete&amp;utid={$usertitle['utid']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this user title?')\">{$lang->delete}</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell("You do not have any user titles defined at the moment", array('colspan' => 4));
		$table->construct_row();
		$no_results = true;
	}
	
	$table->output("Manage User Titles");

	$page->output_footer();
}
?>
