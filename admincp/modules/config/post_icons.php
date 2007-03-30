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

$page->add_breadcrumb_item("Post Icons", "index.php?".SID."&amp;module=config/post_icons");

if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = "You did not enter a name for this post icon";
		}

		if(!trim($mybb->input['path']))
		{
			$errors[] = "You did not enter a path to this post icon";
		}

		if(!$errors)
		{
			$new_icon = array(
				'name'	=> $db->escape_string($mybb->input['name']),
				'path'	=> $db->escape_string($mybb->input['path'])
			);

			$db->insert_query("icons", $new_icon);

			$cache->update_posticons();

			flash_message('The post icon has been added successfully.', 'success');
			admin_redirect('index.php?'.SID.'&module=config/post_icons');
		}
	}

	$page->add_breadcrumb_item("Add Post Icon");
	$page->output_header("Post Icons - Add New Post Icon");

	$sub_tabs['add_icon'] = array(
		'title'	=> "Add New Post Icon",
		'link'	=> "index.php?".SID."&amp;module=config/post_icons&amp;action=add",
		'description'	=> "Here you can add a new post icon."
	);

	$sub_tabs['add_multiple'] = array(
		'title' => "Add Multiple Post Icons",
		'link' => "index.php?".SID."&amp;module=config/post_icons&amp;action=add_multiple"
	);

	$page->output_nav_tabs($sub_tabs, 'add_icon');

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['path'] = 'images/icons/';
	}

	$form = new Form("index.php?".SID."&amp;module=config/post_icons&amp;action=add", "post", "add");
	$form_container = new FormContainer("Add New Post Icon");
	$form_container->output_row("Name", "This is a name for the post icon.", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row("Image Path", "This is the path to the post icon image.", $form->generate_text_box('path', $mybb->input['path'], array('id' => 'path')), 'path');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Add Post Icon");

	$form->output_submit_wrapper($buttons);

	$page->output_footer();
}

if($mybb->input['action'] == "add_multiple")
{
	if($mybb->request_method == "post")
	{
		if($mybb->input['step'] == 1)
		{
			if(!trim($mybb->input['pathfolder']))
			{
				$errors[] = "You did not enter a path";
			}

			$path = $mybb->input['pathfolder'];
			$dir = @opendir(MYBB_ROOT.$path);
			if(!$dir)
			{
				$errors[] = "You did not enter a valid path";
			}

			if(substr($path, -1, 1) !== "/")
			{
				$path .= "/";
			}

			$query = $db->simple_select("icons");
			while($icon = $db->fetch_array($query))
			{
				$aicons[$icon['path']] = 1;
			}

			while($file = readdir($dir))
			{
				if($file != ".." && $file != ".")
				{
					$ext = get_extension($file);
					if($ext == "gif" || $ext == "jpg" || $ext == "jpeg" || $ext == "png" || $ext == "bmp")
					{
						if(!$aicons[$path.$file])
						{
							$icons[] = $file;
						}
					}
				}
			}
			closedir($dir);

			if(count($icons) == 0)
			{
				$errors[] = "There are no post icons in the specified directory, or all post icons in the directory have already been added.";
			}

			if(!$errors)
			{
				// We have no errors so let's proceed!
				$page->add_breadcrumb_item("Add Post Icon");
				$page->output_header("Post Icons - Add New Post Icon");

				$sub_tabs['add_icon'] = array(
					'title'	=> "Add New Post Icon",
					'link'	=> "index.php?".SID."&amp;module=config/post_icons&amp;action=add"
				);

				$sub_tabs['add_multiple'] = array(
					'title' => "Add Multiple Post Icons",
					'link' => "index.php?".SID."&amp;module=config/post_icons&amp;action=add_multiple",
					'description'	=> "Here you can add multiple new post icons."
				);

				$page->output_nav_tabs($sub_tabs, 'add_multiple');

				$form = new Form("index.php?".SID."&amp;module=config/post_icons&amp;action=add_multiple", "post", "add_multiple");
				echo $form->generate_hidden_field("step", "2");
				echo $form->generate_hidden_field("pathfolder", $path);

				$form_container = new FormContainer("Add Multiple Post Icons");
				$form_container->output_row_header("Image", array("class" => "align_center", 'width' => '10%'));
				$form_container->output_row_header("Name");
				$form_container->output_row_header("Include?", array("class" => "align_center", 'width' => '5%'));

				foreach($icons as $key => $file)
				{
					$ext = get_extension($file);
					$find = str_replace(".".$ext, "", $file);
					$name = ucfirst($find);

					$form_container->output_cell("<img src=\"../".$path.$file."\" alt=\"\" /><br /><small>{$file}</small>", array("class" => "align_center", "width" => 1));
					$form_container->output_cell($form->generate_text_box("name[{$file}]", $name, array('id' => 'name', 'style' => 'width: 98%')));
					$form_container->output_cell($form->generate_check_box("include[{$file}]", "yes", "", array('checked' => 1)), array("class" => "align_center"));
					$form_container->construct_row();
				}

				if(count($form_container->container->rows) == 0)
				{
					flash_message('There are no post icons in the specified directory, or all post icons in the directory have already been added.', 'error');
					admin_redirect("index.php?".SID."&module=config/post_icons&action=add_multiple");
				}

				$form_container->end();

				$buttons[] = $form->generate_submit_button("Add Post Icons");
				$form->output_submit_wrapper($buttons);

				$form->end();

				$page->output_footer();
				exit;
			}
		}
		else
		{
			$path = $mybb->input['pathfolder'];
			reset($mybb->input['include']);
			$name = $mybb->input['name'];

			if(empty($mybb->input['include']))
			{
				flash_message('You did not select any post icons to include.', 'error');
				admin_redirect("index.php?".SID."&module=config/post_icons&action=add_multiple");
			}

			foreach($mybb->input['include'] as $image => $insert)
			{
				if($insert)
				{
					$new_icon = array(
						'name' => $db->escape_string($name[$image]),
						'path' => $db->escape_string($path.$image)
					);

					$db->insert_query("icons", $new_icon);
				}
			}

			$cache->update_posticons();

			flash_message('The selected post icons have successfully been added.', 'success');
			admin_redirect("index.php?".SID."&module=config/post_icons");
		}
	}

	$page->add_breadcrumb_item("Add Post Icon");
	$page->output_header("Post Icons - Add New Post Icon");

	$sub_tabs['add_icon'] = array(
		'title'	=> "Add New Post Icon",
		'link'	=> "index.php?".SID."&amp;module=config/post_icons&amp;action=add"
	);

	$sub_tabs['add_multiple'] = array(
		'title' => "Add Multiple Post Icons",
		'link' => "index.php?".SID."&amp;module=config/post_icons&amp;action=add_multiple",
		'description'	=> "Here you can add multiple new post icons."
	);

	$page->output_nav_tabs($sub_tabs, 'add_multiple');

	$form = new Form("index.php?".SID."&amp;module=config/post_icons&amp;action=add_multiple", "post", "add_multiple");
	echo $form->generate_hidden_field("step", "1");

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form_container = new FormContainer("Add Multiple Post Icons");
	$form_container->output_row("Path to Images", "This is the path to the folder that the images are in.", $form->generate_text_box('pathfolder', $mybb->input['pathfolder'], array('id' => 'pathfolder')), 'pathfolder');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Show Post Icons");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("icons", "*", "iid='".intval($mybb->input['iid'])."'");
	$icon = $db->fetch_array($query);

	if(!$icon['iid'])
	{
		flash_message('The specified post icon does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=config/post_icons");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = "You did not enter a name for this post icon";
		}

		if(!trim($mybb->input['path']))
		{
			$errors[] = "You did not enter a path to this post icon";
		}

		if(!$errors)
		{
			$icon = array(
				'name'	=> $db->escape_string($mybb->input['name']),
				'path'	=> $db->escape_string($mybb->input['path'])
			);

			$db->update_query("icons", $icon, "iid='".intval($mybb->input['iid'])."'");

			$cache->update_posticons();

			flash_message('The post icon has been added successfully.', 'success');
			admin_redirect('index.php?'.SID.'&module=config/post_icons');
		}
	}

	$page->output_header("Post Icons - Edit");

	$sub_tabs['edit_icon'] = array(
		'title'	=> "Edit Post Icon",
		'link'	=> "index.php?'.SID.'&amp;module=config/post_icons",
		'description'	=> "Here you can edit a post icon."
	);

	$page->output_nav_tabs($sub_tabs, 'edit_icon');

	$form = new Form("index.php?".SID."&amp;module=config/post_icons&amp;action=edit", "post", "edit");
	echo $form->generate_hidden_field("iid", $icon['iid']);

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = $icon;
	}

	$form_container = new FormContainer("Edit Post Icon");
	$form_container->output_row("Name", "This is a name for the post icon.", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row("Image Path", "This is the path to the post icon image.", $form->generate_text_box('path', $mybb->input['path'], array('id' => 'path')), 'path');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save Post Icon");
	$buttons[] = $form->generate_reset_button("Reset");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("icons", "*", "iid='".intval($mybb->input['iid'])."'");
	$icon = $db->fetch_array($query);

	if(!$icon['iid'])
	{
		flash_message('The specified post icon does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=config/post_icons");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=config/post_icons");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query("icons", "iid='{$icon['iid']}'");

		$cache->update_posticons();

		flash_message('The specified post icon has been deleted.', 'success');
		admin_redirect("index.php?".SID."&module=config/post_icons");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=config/post_icons&amp;action=delete&amp;iid={$icon['iid']}", "Are you sure you wish to delete this post icon?");
	}
}

if(!$mybb->input['action'])
{
	$page->output_header("Post Icons");

	$sub_tabs['manage_icons'] = array(
		'title'	=> "Manage Post Icons",
		'link'	=> "index.php?".SID."&amp;module=config/post_icons",
		'description'	=> "This section allows you to edit and delete and manage your post icons."
	);

	$sub_tabs['add_icon'] = array(
		'title'	=> "Add New Post Icon",
		'link'	=> "index.php?".SID."&amp;module=config/post_icons&amp;action=add"
	);

	$sub_tabs['add_multiple'] = array(
		'title' => "Add Multiple Post Icons",
		'link' => "index.php?".SID."&amp;module=config/post_icons&amp;action=add_multiple"
	);

	$page->output_nav_tabs($sub_tabs, 'manage_icons');

	$pagenum = intval($mybb->input['page']);
	if($pagenum)
	{
		$start = ($pagenum - 1) * 20;
	}
	else
	{
		$start = 0;
		$pagenum = 1;
	}

	$table = new Table;
	$table->construct_header("Image", array('class' => "align_center", 'width' => 1));
	$table->construct_header("Name", array('width' => "80%"));
	$table->construct_header("Controls", array('class' => "align_center", 'colspan' => 2));

	$query = $db->simple_select("icons", "*", "", array('limit_start' => $start, 'limit' => 20, 'order_by' => 'name'));
	while($icon = $db->fetch_array($query))
	{
		if(my_strpos($icon['path'], "p://") || substr($icon['path'], 0, 1) == "/")
		{
			$image = $icon['path'];
		}
		else
		{
			$image = "../".$icon['path'];
		}

		$table->construct_cell("<img src=\"{$image}\" alt=\"\" />", array("class" => "align_center"));
		$table->construct_cell("{$icon['name']}");

		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/post_icons&amp;action=edit&amp;iid={$icon['iid']}\">Edit</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/post_icons&amp;action=delete&amp;iid={$icon['iid']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this icon?')\">Delete</a>", array("class" => "align_center"));
		$table->construct_row();
	}

	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are no post icons on your forum at this time.", array('colspan' => 4));
		$table->construct_row();
	}

	$table->output("Manage Post Icons");

	$query = $db->simple_select("icons", "COUNT(iid) AS icons");
	$total_rows = $db->fetch_field($query, "icons");

	echo "<br />".draw_admin_pagination($pagenum, "20", $total_rows, "index.php?".SID."&amp;module=config/post_icons&amp;page={page}");

	$page->output_footer();
}
?>