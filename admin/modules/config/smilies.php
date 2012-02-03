<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: smilies.php 5676 2011-11-29 12:10:19Z Tomm $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->smilies, "index.php?module=config-smilies");

$plugins->run_hooks("admin_config_smilies_begin");

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_config_smilies_add");
	
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}

		if(!trim($mybb->input['find']))
		{
			$errors[] = $lang->error_missing_text_replacement;
		}

		if(!trim($mybb->input['image']))
		{
			$errors[] = $lang->error_missing_path;
		}

		if(!trim($mybb->input['disporder']))
		{
			$errors[] = $lang->error_missing_order;
		}
		
		if(!$errors)
		{
			$new_smilie = array(
				"name" => $db->escape_string($mybb->input['name']),
				"find" => $db->escape_string($mybb->input['find']),
				"image" => $db->escape_string($mybb->input['image']),
				"disporder" => intval($mybb->input['disporder']),
				"showclickable" => $db->escape_string($mybb->input['showclickable'])
			);
			
			$sid = $db->insert_query("smilies", $new_smilie);

			$cache->update_smilies();
			
			$plugins->run_hooks("admin_config_smilies_add_commit");

			// Log admin action
			log_admin_action($sid, $mybb->input['name']);
			
			flash_message($lang->success_smilie_added, 'success');
			admin_redirect("index.php?module=config-smilies");
		}
	}
	
	$page->add_breadcrumb_item($lang->add_smilie);
	$page->output_header($lang->smilies." - ".$lang->add_smilie);
	
	$sub_tabs['manage_smilies'] = array(
		'title' => $lang->manage_smilies,
		'link' => "index.php?module=config-smilies",
	);
	$sub_tabs['add_smilie'] = array(
		'title' => $lang->add_smilie,
		'link' => "index.php?module=config-smilies&amp;action=add",
		'description' => $lang->add_smilie_desc
	);
	$sub_tabs['add_multiple_smilies'] = array(
		'title' => $lang->add_multiple_smilies,
		'link' => "index.php?module=config-smilies&amp;action=add_multiple",
	);
	$sub_tabs['mass_edit'] = array(
		'title' => $lang->mass_edit,
		'link' => "index.php?module=config-smilies&amp;action=mass_edit"
	);
	
	$page->output_nav_tabs($sub_tabs, 'add_smilie');
	$form = new Form("index.php?module=config-smilies&amp;action=add", "post", "add");
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['image'] = 'images/smilies/';
		$mybb->input['showclickable'] = 1;
	}
	
	if(!$mybb->input['disporder'])
	{
		$query = $db->simple_select("smilies", "max(disporder) as dispordermax");
		$mybb->input['disporder'] = $db->fetch_field($query, "dispordermax")+1;
	}
	
	$form_container = new FormContainer($lang->add_smilie);
	$form_container->output_row($lang->name." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->text_replace." <em>*</em>", "", $form->generate_text_box('find', $mybb->input['find'], array('id' => 'find')), 'find');
	$form_container->output_row($lang->image_path." <em>*</em>", $lang->image_path_desc, $form->generate_text_box('image', $mybb->input['image'], array('id' => 'image')), 'image');
	$form_container->output_row($lang->display_order." <em>*</em>", $lang->display_order_desc, $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
	$form_container->output_row($lang->show_clickable." <em>*</em>", $lang->show_clickable_desc, $form->generate_yes_no_radio('showclickable', $mybb->input['showclickable']));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_smilie);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$plugins->run_hooks("admin_config_smilies_edit");
	
	$query = $db->simple_select("smilies", "*", "sid='".intval($mybb->input['sid'])."'");
	$smilie = $db->fetch_array($query);

	// Does the smilie not exist?
	if(!$smilie['sid'])
	{
		flash_message($lang->error_invalid_smilie, 'error');
		admin_redirect("index.php?module=config-smilies");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}

		if(!trim($mybb->input['find']))
		{
			$errors[] = $lang->error_missing_text_replacement;
		}

		if(!trim($mybb->input['image']))
		{
			$errors[] = $lang->error_missing_path;
		}

		if(!isset($mybb->input['disporder']))
		{
			$errors[] = $lang->error_missing_order;
		}
		
		if(!$errors)
		{
			$updated_smilie = array(
				"name" => $db->escape_string($mybb->input['name']),
				"find" => $db->escape_string($mybb->input['find']),
				"image" => $db->escape_string($mybb->input['image']),
				"disporder" => intval($mybb->input['disporder']),
				"showclickable" => $db->escape_string($mybb->input['showclickable'])
			);
			
			$db->update_query("smilies", $updated_smilie, "sid = '".intval($mybb->input['sid'])."'");
			
			$cache->update_smilies();
			
			$plugins->run_hooks("admin_config_smilies_edit_commit");

			// Log admin action
			log_admin_action($smilie['sid'], $mybb->input['name']);

			flash_message($lang->success_smilie_updated, 'success');
			admin_redirect("index.php?module=config-smilies");
		}
	}
	
	$page->add_breadcrumb_item($lang->edit_smilie);
	$page->output_header($lang->smilies." - ".$lang->edit_smilie);
	
	$sub_tabs['edit_smilie'] = array(
		'title' => $lang->edit_smilie,
		'link' => "index.php?module=config-smilies&amp;action=edit",
		'description' => $lang->edit_smilie_desc
	);
	$sub_tabs['mass_edit'] = array(
		'title' => $lang->mass_edit,
		'link' => "index.php?module=config-smilies&amp;action=mass_edit",
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_smilie');
	$form = new Form("index.php?module=config-smilies&amp;action=edit", "post", "edit");
	
	echo $form->generate_hidden_field("sid", $smilie['sid']);
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = $smilie;
	}

	$form_container = new FormContainer($lang->edit_smilie);
	$form_container->output_row($lang->name." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->text_replace." <em>*</em>", "", $form->generate_text_box('find', $mybb->input['find'], array('id' => 'find')), 'find');
	$form_container->output_row($lang->image_path." <em>*</em>", $lang->image_path_desc, $form->generate_text_box('image', $mybb->input['image'], array('id' => 'image')), 'image');
	$form_container->output_row($lang->display_order." <em>*</em>", $lang->display_order_desc, $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
	$form_container->output_row($lang->show_clickable." <em>*</em>", $lang->show_clickable_desc, $form->generate_yes_no_radio('showclickable', $mybb->input['showclickable']));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_smilie);
	$buttons[] = $form->generate_reset_button($lang->reset);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("admin_config_smilies_delete");
	
	$query = $db->simple_select("smilies", "*", "sid='".intval($mybb->input['sid'])."'");
	$smilie = $db->fetch_array($query);

	// Does the smilie not exist?
	if(!$smilie['sid'])
	{
		flash_message($lang->error_invalid_smilie, 'error');
		admin_redirect("index.php?module=config-smilies");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=config-smilies");
	}

	if($mybb->request_method == "post")
	{
		// Delete the smilie
		$db->delete_query("smilies", "sid='{$smilie['sid']}'");

		$cache->update_smilies();
		
		$plugins->run_hooks("admin_config_smilies_delete_commit");

		// Log admin action
		log_admin_action($smilie['sid'], $smilie['name']);

		flash_message($lang->success_smilie_updated, 'success');
		admin_redirect("index.php?module=config-smilies");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-smilies&amp;action=delete&amp;sid={$smilie['sid']}", $lang->confirm_smilie_deletion);
	}}

if($mybb->input['action'] == "add_multiple")
{
	$plugins->run_hooks("admin_config_smilies_add_multiple");
	
	if($mybb->request_method == "post")
	{
		if($mybb->input['step'] == 1)
		{
			$plugins->run_hooks("admin_config_smilies_add_multiple_step1");
			
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
			
			if($path && !is_array($errors))
			{
				if(substr($path, -1, 1) !== "/")
				{
					$path .= "/";
				}
				
				$query = $db->simple_select("smilies");
				while($smilie = $db->fetch_array($query))
				{
					$asmilies[$smilie['image']] = 1;
				}
				
				while($file = readdir($dir))
				{
					if($file != ".." && $file != ".")
					{
						$ext = get_extension($file);
						if($ext == "gif" || $ext == "jpg" || $ext == "jpeg" || $ext == "png" || $ext == "bmp")
						{
							if(!$asmilies[$path.$file])
							{
								$smilies[] = $file;
							}
						}
					}
				}
				closedir($dir);
				
				if(count($smilies) == 0)
				{
					$errors[] = $lang->error_no_smilies;
				}
			}
			
			if(!$errors)
			{
				$page->add_breadcrumb_item($lang->add_multiple_smilies);
				$page->output_header($lang->smilies." - ".$lang->add_multiple_smilies);
				
				$sub_tabs['manage_smilies'] = array(
					'title' => $lang->manage_smilies,
					'link' => "index.php?module=config-smilies",
				);
				$sub_tabs['add_smilie'] = array(
					'title' => $lang->add_smilie,
					'link' => "index.php?module=config-smilies&amp;action=add"
				);
				$sub_tabs['add_multiple_smilies'] = array(
					'title' => $lang->add_multiple_smilies,
					'link' => "index.php?module=config-smilies&amp;action=add_multiple",
					'description' => $lang->add_multiple_smilies_desc
				);
				$sub_tabs['mass_edit'] = array(
					'title' => $lang->mass_edit,
					'link' => "index.php?module=config-smilies&amp;action=mass_edit"
				);
				
				$page->output_nav_tabs($sub_tabs, 'add_multiple_smilies');
				$form = new Form("index.php?module=config-smilies&amp;action=add_multiple", "post", "add_multiple");
				echo $form->generate_hidden_field("step", "2");
				echo $form->generate_hidden_field("pathfolder", $path);
				
				$form_container = new FormContainer($lang->add_multiple_smilies);
				$form_container->output_row_header($lang->image, array("class" => "align_center", 'width' => '10%'));
				$form_container->output_row_header($lang->name);
				$form_container->output_row_header($lang->text_replace, array('width' => '20%'));
				$form_container->output_row_header($lang->include, array("class" => "align_center", 'width' => '5%'));			
		
				foreach($smilies as $key => $file)
				{
					$ext = get_extension($file);
					$find = str_replace(".".$ext, "", $file);
					$name = ucfirst($find);
				
					$form_container->output_cell("<img src=\"../".$path.$file."\" alt=\"\" /><br /><small>{$file}</small>", array("class" => "align_center", "width" => 1));
					$form_container->output_cell($form->generate_text_box("name[{$file}]", $name, array('id' => 'name', 'style' => 'width: 98%')));
					$form_container->output_cell($form->generate_text_box("find[{$file}]", ":".$find.":", array('id' => 'find', 'style' => 'width: 95%')));
					$form_container->output_cell($form->generate_check_box("include[{$file}]", 1, "", array('checked' => 1)), array("class" => "align_center"));
					$form_container->construct_row();
				}
				
				if($form_container->num_rows() == 0)
				{
					flash_message($lang->error_no_images, 'error');
					admin_redirect("index.php?module=config-smilies&action=add_multiple");
				}
				
				$form_container->end();
				
				$buttons[] = $form->generate_submit_button($lang->save_smilies);
			
				$form->output_submit_wrapper($buttons);
				$form->end();
				
				$page->output_footer();
				exit;
			}
		}
		else
		{
			$plugins->run_hooks("admin_config_smilies_add_multiple_step2");
			
			$path = $mybb->input['pathfolder'];
			reset($mybb->input['include']);
			$find = $mybb->input['find'];
			$name = $mybb->input['name'];
			
			if(empty($mybb->input['include']))
			{
				flash_message($lang->error_none_included, 'error');
				admin_redirect("index.php?module=config-smilies&action=add_multiple");
			}
			
			foreach($mybb->input['include'] as $image => $insert)
			{
				if($insert)
				{
					$new_smilie = array(
						"name" => $db->escape_string($name[$image]),
						"find" => $db->escape_string($find[$image]),
						"image" => $db->escape_string($path.$image),
						"showclickable" => 1
					);
					$db->insert_query("smilies", $new_smilie);
				}
			}

			$cache->update_smilies();
			
			$plugins->run_hooks("admin_config_smilies_add_multiple_commit");

			// Log admin action
			log_admin_action();
			
			flash_message($lang->success_multiple_smilies_added, 'success');
			admin_redirect("index.php?module=config-smilies");
		}
	}
	
	$page->add_breadcrumb_item($lang->add_multiple_smilies);
	$page->output_header($lang->smilies." - ".$lang->add_multiple_smilies);
	
	$sub_tabs['manage_smilies'] = array(
		'title' => $lang->manage_smilies,
		'link' => "index.php?module=config-smilies",
	);
	$sub_tabs['add_smilie'] = array(
		'title' => $lang->add_smilie,
		'link' => "index.php?module=config-smilies&amp;action=add"
	);
	$sub_tabs['add_multiple_smilies'] = array(
		'title' => $lang->add_multiple_smilies,
		'link' => "index.php?module=config-smilies&amp;action=add_multiple",
		'description' => $lang->add_multiple_smilies_desc
	);
	$sub_tabs['mass_edit'] = array(
		'title' => $lang->mass_edit,
		'link' => "index.php?module=config-smilies&amp;action=mass_edit"
	);
	
	$page->output_nav_tabs($sub_tabs, 'add_multiple_smilies');
	$form = new Form("index.php?module=config-smilies&amp;action=add_multiple", "post", "add_multiple");
	echo $form->generate_hidden_field("step", "1");
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form_container = new FormContainer($lang->add_multiple_smilies);
	$form_container->output_row($lang->path_to_images, $lang->path_to_images_desc, $form->generate_text_box('pathfolder', $mybb->input['pathfolder'], array('id' => 'pathfolder')), 'pathfolder');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->show_smilies);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "mass_edit")
{
	$plugins->run_hooks("admin_config_smilies_mass_edit");
	
	if($mybb->request_method == "post")
	{
		foreach($mybb->input['name'] as $sid => $name)
		{
			if($mybb->input['delete'][$sid] == 1)
			{
				$db->delete_query("smilies", "sid = '{$sid}'", 1);
			}
			else
			{
				$smilie = array(
					"name" => $db->escape_string($mybb->input['name'][$sid]),
					"find" => $db->escape_string($mybb->input['find'][$sid]),
					"disporder" => intval($mybb->input['disporder'][$sid]),
					"showclickable" => $db->escape_string($mybb->input['showclickable'][$sid])
				);
					
				$db->update_query("smilies", $smilie, "sid = '{$sid}'");
			}
		}
		
		$cache->update_smilies();
		
		$plugins->run_hooks("admin_config_smilies_mass_edit_commit");

		// Log admin action
		log_admin_action();

		flash_message($lang->success_multiple_smilies_updated, 'success');
		admin_redirect("index.php?module=config-smilies");
	}
	
	$page->add_breadcrumb_item($lang->mass_edit);
	$page->output_header($lang->smilies." - ".$lang->mass_edit);

	$sub_tabs['manage_smilies'] = array(
		'title' => $lang->manage_smilies,
		'link' => "index.php?module=config-smilies",
	);
	$sub_tabs['add_smilie'] = array(
		'title' => $lang->add_smilie,
		'link' => "index.php?module=config-smilies&amp;action=add",
	);
	$sub_tabs['add_multiple_smilies'] = array(
		'title' => $lang->add_multiple_smilies,
		'link' => "index.php?module=config-smilies&amp;action=add_multiple",
	);
	$sub_tabs['mass_edit'] = array(
		'title' => $lang->mass_edit,
		'link' => "index.php?module=config-smilies&amp;action=mass_edit",
		'description' => $lang->mass_edit_desc
	);
	
	$page->output_nav_tabs($sub_tabs, 'mass_edit');
	
	$form = new Form("index.php?module=config-smilies&amp;action=mass_edit", "post", "mass_edit");
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['path'] = 'images/smilies/';
		$mybb->input['showclickable'] = 1;
	}
	
	if(!$mybb->input['disporder'])
	{
		$query = $db->simple_select("smilies", "max(disporder) as dispordermax");
		$mybb->input['disporder'] = $db->fetch_field($query, "dispordermax")+1;
	}
	
	$form_container = new FormContainer($lang->manage_smilies);
	$form_container->output_row_header($lang->image, array("class" => "align_center", 'width' => '1'));
	$form_container->output_row_header($lang->name);
	$form_container->output_row_header($lang->text_replace, array('width' => '20%'));
	$form_container->output_row_header($lang->order, array('width' => '5%'));
	$form_container->output_row_header($lang->mass_edit_show_clickable, array("width" => 165));
	$form_container->output_row_header($lang->smilie_delete, array("class" => "align_center", 'width' => '5%'));
	
	$query = $db->simple_select("smilies", "*", "", array('order_by' => 'disporder'));
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
		
		$form_container->output_cell("<img src=\"{$image}\" alt=\"\" />", array("class" => "align_center", "width" => 1));
		$form_container->output_cell($form->generate_text_box("name[{$smilie['sid']}]", $smilie['name'], array('id' => 'name', 'style' => 'width: 98%')));
		$form_container->output_cell($form->generate_text_box("find[{$smilie['sid']}]", $smilie['find'], array('id' => 'find', 'style' => 'width: 95%')));
		$form_container->output_cell($form->generate_text_box("disporder[{$smilie['sid']}]", $smilie['disporder'], array('id' => 'disporder', 'style' => 'width: 80%')));
		$form_container->output_cell($form->generate_yes_no_radio("showclickable[{$smilie['sid']}]", $smilie['showclickable']), array("class" => "align_center"));
		$form_container->output_cell($form->generate_check_box("delete[{$smilie['sid']}]", 1, $mybb->input['delete']), array("class" => "align_center"));
		$form_container->construct_row();
	}
	
	if($form_container->num_rows() == 0)
	{
		$form_container->output_cell($lang->no_smilies, array('colspan' => 6));
		$form_container->construct_row();
	}
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->save_smilies);
	$buttons[] = $form->generate_reset_button($lang->reset);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_config_smilies_start");
	
	$page->output_header($lang->manage_smilies);

	$sub_tabs['manage_smilies'] = array(
		'title' => $lang->manage_smilies,
		'link' => "index.php?module=config-smilies",
		'description' => $lang->manage_smilies_desc
	);
	$sub_tabs['add_smilie'] = array(
		'title' => $lang->add_smilie,
		'link' => "index.php?module=config-smilies&amp;action=add",
	);
	$sub_tabs['add_multiple_smilies'] = array(
		'title' => $lang->add_multiple_smilies,
		'link' => "index.php?module=config-smilies&amp;action=add_multiple",
	);
	$sub_tabs['mass_edit'] = array(
		'title' => $lang->mass_edit,
		'link' => "index.php?module=config-smilies&amp;action=mass_edit",
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
	$table->construct_header($lang->image, array("class" => "align_center", "width" => 1));
	$table->construct_header($lang->name, array("width" => "35%"));
	$table->construct_header($lang->text_replace, array("width" => "35%"));
	$table->construct_header($lang->controls, array("class" => "align_center", "colspan" => 2));
	
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
		
		$table->construct_cell("<img src=\"{$image}\" alt=\"\" />", array("class" => "align_center"));
		$table->construct_cell(htmlspecialchars_uni($smilie['name']));
		$table->construct_cell(htmlspecialchars_uni($smilie['find']));
		
		$table->construct_cell("<a href=\"index.php?module=config-smilies&amp;action=edit&amp;sid={$smilie['sid']}\">{$lang->edit}</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=config-smilies&amp;action=delete&amp;sid={$smilie['sid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_smilie_deletion}')\">{$lang->delete}</a>", array("class" => "align_center"));
		$table->construct_row();
	}
	
	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_smilies, array('colspan' => 5));
		$table->construct_row();
	}
	
	$table->output($lang->manage_smilies);
	
	$query = $db->simple_select("smilies", "COUNT(sid) as smilies");
	$total_rows = $db->fetch_field($query, "smilies");
	
	echo "<br />".draw_admin_pagination($pagenum, "20", $total_rows, "index.php?module=config-smilies&amp;page={page}");

	$page->output_footer();
}
?>