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

$page->add_breadcrumb_item($lang->post_icons, "index.php?module=config-post_icons");

$plugins->run_hooks("admin_config_post_icons_begin");

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_config_post_icons_add");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}

		if(!trim($mybb->input['path']))
		{
			$errors[] = $lang->error_missing_path;
		}

		if(!$errors)
		{
			$new_icon = array(
				'name' => $db->escape_string($mybb->input['name']),
				'path' => $db->escape_string($mybb->input['path'])
			);

			$iid = $db->insert_query("icons", $new_icon);

			$plugins->run_hooks("admin_config_post_icons_add_commit");

			$cache->update_posticons();

			// Log admin action
			log_admin_action($iid, htmlspecialchars_uni($mybb->input['name']));

			flash_message($lang->success_post_icon_added, 'success');
			admin_redirect('index.php?module=config-post_icons');
		}
	}

	$page->add_breadcrumb_item($lang->add_post_icon);
	$page->output_header($lang->post_icons." - ".$lang->add_post_icon);

	$sub_tabs['manage_icons'] = array(
		'title'	=> $lang->manage_post_icons,
		'link' => "index.php?module=config-post_icons"
	);

	$sub_tabs['add_icon'] = array(
		'title'	=> $lang->add_post_icon,
		'link' => "index.php?module=config-post_icons&amp;action=add",
		'description' => $lang->add_post_icon_desc
	);

	$sub_tabs['add_multiple'] = array(
		'title' => $lang->add_multiple_post_icons,
		'link' => "index.php?module=config-post_icons&amp;action=add_multiple"
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

	$form = new Form("index.php?module=config-post_icons&amp;action=add", "post", "add");
	$form_container = new FormContainer($lang->add_post_icon);
	$form_container->output_row($lang->name." <em>*</em>", $lang->name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->image_path." <em>*</em>", $lang->image_path_desc, $form->generate_text_box('path', $mybb->input['path'], array('id' => 'path')), 'path');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_post_icon);

	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "add_multiple")
{
	$plugins->run_hooks("admin_config_post_icons_add_multiple");

	if($mybb->request_method == "post")
	{
		if($mybb->input['step'] == 1)
		{
			if(!trim($mybb->input['pathfolder']))
			{
				$errors[] = $lang->error_missing_path_multiple;
			}

			$path = $mybb->input['pathfolder'];
			$dir = @opendir(MYBB_ROOT.$path);
			if(!$dir)
			{
				$errors[] = $lang->error_invalid_path;
			}

			if(substr($path, -1, 1) !== "/")
			{
				$path .= "/";
			}

			$query = $db->simple_select("icons");

			$aicons = array();
			while($icon = $db->fetch_array($query))
			{
				$aicons[$icon['path']] = 1;
			}

			$icons = array();
			if(!$errors)
			{
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
					$errors[] = $lang->error_no_images;
				}
			}

			// Check for errors again (from above statement)!
			if(!$errors)
			{
				// We have no errors so let's proceed!
				$page->add_breadcrumb_item($lang->add_multiple_post_icons);
				$page->output_header($lang->post_icons." - ".$lang->add_multiple_post_icons);

				$sub_tabs['manage_icons'] = array(
					'title'	=> $lang->manage_post_icons,
					'link' => "index.php?module=config-post_icons"
				);

				$sub_tabs['add_icon'] = array(
					'title'	=> $lang->add_post_icon,
					'link' => "index.php?module=config-post_icons&amp;action=add"
				);

				$sub_tabs['add_multiple'] = array(
					'title' => $lang->add_multiple_post_icons,
					'link' => "index.php?module=config-post_icons&amp;action=add_multiple",
					'description' => $lang->add_multiple_post_icons_desc
				);

				$page->output_nav_tabs($sub_tabs, 'add_multiple');

				$form = new Form("index.php?module=config-post_icons&amp;action=add_multiple", "post", "add_multiple");
				echo $form->generate_hidden_field("step", "2");
				echo $form->generate_hidden_field("pathfolder", $path);

				$form_container = new FormContainer($lang->add_multiple_post_icons);
				$form_container->output_row_header($lang->image, array("class" => "align_center", 'width' => '10%'));
				$form_container->output_row_header($lang->name);
				$form_container->output_row_header($lang->add, array("class" => "align_center", 'width' => '5%'));

				foreach($icons as $key => $file)
				{
					$ext = get_extension($file);
					$find = str_replace(".".$ext, "", $file);
					$name = ucfirst($find);

					$form_container->output_cell("<img src=\"../".$path.$file."\" alt=\"\" /><br /><small>{$file}</small>", array("class" => "align_center", "width" => 1));
					$form_container->output_cell($form->generate_text_box("name[{$file}]", $name, array('id' => 'name', 'style' => 'width: 98%')));
					$form_container->output_cell($form->generate_check_box("include[{$file}]", 1, "", array('checked' => 1)), array("class" => "align_center"));
					$form_container->construct_row();
				}

				if($form_container->num_rows() == 0)
				{
					flash_message($lang->error_no_images, 'error');
					admin_redirect("index.php?module=config-post_icons&action=add_multiple");
				}

				$form_container->end();

				$buttons[] = $form->generate_submit_button($lang->save_post_icons);
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
				flash_message($lang->error_none_included, 'error');
				admin_redirect("index.php?module=config-post_icons&action=add_multiple");
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

			$plugins->run_hooks("admin_config_post_icons_add_multiple_commit");

			$cache->update_posticons();

			// Log admin action
			log_admin_action();

			flash_message($lang->success_post_icons_added, 'success');
			admin_redirect("index.php?module=config-post_icons");
		}
	}

	$page->add_breadcrumb_item($lang->add_multiple_post_icons);
	$page->output_header($lang->post_icons." - ".$lang->add_multiple_post_icons);

	$sub_tabs['manage_icons'] = array(
		'title'	=> $lang->manage_post_icons,
		'link'	=> "index.php?module=config-post_icons"
	);

	$sub_tabs['add_icon'] = array(
		'title'	=> $lang->add_post_icon,
		'link'	=> "index.php?module=config-post_icons&amp;action=add"
	);

	$sub_tabs['add_multiple'] = array(
		'title' => $lang->add_multiple_post_icons,
		'link' => "index.php?module=config-post_icons&amp;action=add_multiple",
		'description'	=> $lang->add_multiple_post_icons_desc
	);

	$page->output_nav_tabs($sub_tabs, 'add_multiple');

	$form = new Form("index.php?module=config-post_icons&amp;action=add_multiple", "post", "add_multiple");
	echo $form->generate_hidden_field("step", "1");

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form_container = new FormContainer($lang->add_multiple_post_icons);
	$form_container->output_row($lang->path_to_images." <em>*</em>", $lang->path_to_images_desc, $form->generate_text_box('pathfolder', $mybb->input['pathfolder'], array('id' => 'pathfolder')), 'pathfolder');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->show_post_icons);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("icons", "*", "iid='".$mybb->get_input('iid', MyBB::INPUT_INT)."'");
	$icon = $db->fetch_array($query);

	if(!$icon['iid'])
	{
		flash_message($lang->error_invalid_post_icon, 'error');
		admin_redirect("index.php?module=config-post_icons");
	}

	$plugins->run_hooks("admin_config_post_icons_edit");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}

		if(!trim($mybb->input['path']))
		{
			$errors[] = $lang->error_missing_path;
		}

		if(!$errors)
		{
			$updated_icon = array(
				'name'	=> $db->escape_string($mybb->input['name']),
				'path'	=> $db->escape_string($mybb->input['path'])
			);

			$plugins->run_hooks("admin_config_post_icons_edit_commit");

			$db->update_query("icons", $updated_icon, "iid='{$icon['iid']}'");

			$cache->update_posticons();

			// Log admin action
			log_admin_action($icon['iid'], htmlspecialchars_uni($mybb->input['name']));

			flash_message($lang->success_post_icon_updated, 'success');
			admin_redirect('index.php?module=config-post_icons');
		}
	}

	$page->add_breadcrumb_item($lang->edit_post_icon);
	$page->output_header($lang->post_icons." - ".$lang->edit_post_icon);

	$sub_tabs['edit_icon'] = array(
		'title'	=> $lang->edit_post_icon,
		'link'	=> "index.php?module=config-post_icons",
		'description'	=> $lang->edit_post_icon_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_icon');

	$form = new Form("index.php?module=config-post_icons&amp;action=edit", "post", "edit");
	echo $form->generate_hidden_field("iid", $icon['iid']);

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = array_merge($mybb->input, $icon);
	}

	$form_container = new FormContainer($lang->edit_post_icon);
	$form_container->output_row($lang->name." <em>*</em>", $lang->name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->image_path." <em>*</em>", $lang->image_path_desc, $form->generate_text_box('path', $mybb->input['path'], array('id' => 'path')), 'path');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_post_icon);
	$buttons[] = $form->generate_reset_button($lang->reset);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("icons", "*", "iid='".$mybb->get_input('iid', MyBB::INPUT_INT)."'");
	$icon = $db->fetch_array($query);

	if(!$icon['iid'])
	{
		flash_message($lang->error_invalid_post_icon, 'error');
		admin_redirect("index.php?module=config-post_icons");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=config-post_icons");
	}

	$plugins->run_hooks("admin_config_post_icons_delete");

	if($mybb->request_method == "post")
	{
		$db->delete_query("icons", "iid='{$icon['iid']}'");

		$plugins->run_hooks("admin_config_post_icons_delete_commit");

		$cache->update_posticons();

		// Log admin action
		log_admin_action($icon['iid'], htmlspecialchars_uni($icon['name']));

		flash_message($lang->success_post_icon_deleted, 'success');
		admin_redirect("index.php?module=config-post_icons");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-post_icons&amp;action=delete&amp;iid={$icon['iid']}", $lang->confirm_post_icon_deletion);
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_config_post_icons_start");

	$page->output_header($lang->post_icons);

	$sub_tabs['manage_icons'] = array(
		'title'	=> $lang->manage_post_icons,
		'link' => "index.php?module=config-post_icons",
		'description' => $lang->manage_post_icons_desc
	);

	$sub_tabs['add_icon'] = array(
		'title'	=> $lang->add_post_icon,
		'link' => "index.php?module=config-post_icons&amp;action=add"
	);

	$sub_tabs['add_multiple'] = array(
		'title' => $lang->add_multiple_post_icons,
		'link' => "index.php?module=config-post_icons&amp;action=add_multiple"
	);

	$page->output_nav_tabs($sub_tabs, 'manage_icons');

	$pagenum = $mybb->get_input('page', MyBB::INPUT_INT);
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
	$table->construct_header($lang->image, array('class' => "align_center", 'width' => 1));
	$table->construct_header($lang->name, array('width' => "70%"));
	$table->construct_header($lang->controls, array('class' => "align_center", 'colspan' => 2));

	$query = $db->simple_select("icons", "*", "", array('limit_start' => $start, 'limit' => 20, 'order_by' => 'name'));
	while($icon = $db->fetch_array($query))
	{
		$icon['path'] = str_replace("{theme}", "images", $icon['path']);
		if(my_strpos($icon['path'], "p://") || substr($icon['path'], 0, 1) == "/")
		{
			$image = $icon['path'];
		}
		else
		{
			$image = "../".$icon['path'];
		}

		$table->construct_cell("<img src=\"".htmlspecialchars_uni($image)."\" alt=\"\" />", array("class" => "align_center"));
		$table->construct_cell(htmlspecialchars_uni($icon['name']));

		$table->construct_cell("<a href=\"index.php?module=config-post_icons&amp;action=edit&amp;iid={$icon['iid']}\">{$lang->edit}</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=config-post_icons&amp;action=delete&amp;iid={$icon['iid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_post_icon_deletion}')\">{$lang->delete}</a>", array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_post_icons, array('colspan' => 4));
		$table->construct_row();
	}

	$table->output($lang->manage_post_icons);

	$query = $db->simple_select("icons", "COUNT(iid) AS icons");
	$total_rows = $db->fetch_field($query, "icons");

	echo "<br />".draw_admin_pagination($pagenum, "20", $total_rows, "index.php?module=config-post_icons&amp;page={page}");

	$page->output_footer();
}
