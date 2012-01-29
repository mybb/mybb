<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

require_once MYBB_ROOT."inc/plugins/mods/Mods.php";
$mods = Mods::getInstance();

$lang->load('mods');

$page->add_breadcrumb_item($lang->mods_categories, 'index.php?module=mods-categories');

$page->output_header($lang->mods_categories);

	
$sub_tabs['categories_view'] = array(
	'title'			=> $lang->mods_categories_view,
	'link'			=> 'index.php?module=mods-categories',
	'description'	=> $lang->mods_categories_view_desc
);

$sub_tabs['categories_add'] = array(
	'title'			=> $lang->mods_categories_add,
	'link'			=> 'index.php?module=mods-categories&amp;action=add',
	'description'	=> $lang->mods_categories_add_desc
);

$sub_tabs['categories_edit'] = array(
	'title'			=> $lang->mods_categories_edit,
	'link'			=> 'index.php?module=mods-categories&amp;action=edit',
	'description'	=> $lang->mods_categories_edit_desc
);
	
switch ($mybb->input['action'])
{
	case 'add':
		$page->output_nav_tabs($sub_tabs, 'categories_add');
	break;
	case 'edit':
		$page->output_nav_tabs($sub_tabs, 'categories_edit');
	break;
	default:
		$page->output_nav_tabs($sub_tabs, 'categories_view');
}

if (!$mybb->input['action']) // No action, view entries
{
	// table
	$table = new Table;
	$table->construct_header($lang->mods_name);
	$table->construct_header($lang->mods_disporder, array('width' => '10%', 'class' => 'align_center'));
	$table->construct_header($lang->mods_downloads, array('width' => '10%', 'class' => 'align_center'));
	$table->construct_header($lang->mods_parent, array('width' => '20%', 'class' => 'align_center'));
	$table->construct_header($lang->mods_options, array('width' => '20%', 'class' => 'align_center'));
		
	// $cats = $mods->categories->getAll();
	$query = $db->query("SELECT * FROM `".TABLE_PREFIX."mods_categories` ORDER BY parent ASC, disporder ASC");
	while ($cat = $db->fetch_array($query))
	{	
		$table->construct_cell(htmlspecialchars_uni($cat['name'])); 
		
		$table->construct_cell(intval($cat['disporder']), array('class' => 'align_center'));
		$table->construct_cell(intval($cat['counter']), array('class' => 'align_center'));
		
		$table->construct_cell(htmlspecialchars_uni(ucfirst($cat['parent'])), array('class' => 'align_center'));
		
		$table->construct_cell("<a href=\"index.php?module=mods-categories&amp;action=delete&amp;cid={$cat['cid']}\" target=\"_self\">{$lang->mods_delete}</a> - <a href=\"index.php?module=mods-categories&amp;action=edit&amp;cid={$cat['cid']}\" target=\"_self\">{$lang->mods_edit}</a>", array('class' => 'align_center'));
			
		$table->construct_row();
	}
	
	if ($table->num_rows() == 0)
	{
		$table->construct_cell($lang->mods_no_data, array('colspan' => 5));
		
		$table->construct_row();
	}
	
	unset($catcache);
	
	$table->output($lang->mods_categories);
}
elseif ($mybb->input['action'] == 'add') // Add entry
{
	if ($mybb->request_method == "post") // submit
	{
		if (empty($mybb->input['name']))
		{
			flash_message($lang->mods_no_name, 'error');
			admin_redirect("index.php?module=mods-categories");
		}
		
		$mybb->input['parent'] = strtolower($mybb->input['parent']);
		
		if (!$mods->categories->validateParent($mybb->input['parent']))
		{
			flash_message($lang->mods_no_parent_error, 'error');
			admin_redirect("index.php?module=mods-categories");
		}
		
		$insert_array = array(
			'name' => $mybb->input['name'],
			'description' => $mybb->input['description'],
			'disporder' => intval($mybb->input['disporder']),
			'parent' => $mybb->input['parent'],
		);
		
		$mods->categories->create($insert_array);
		
		flash_message($lang->mods_category_added, 'success');
		admin_redirect("index.php?module=mods-categories");
	}
	else {
		
		$form = new Form("index.php?module=mods-categories&amp;action=add", "post", "mods");
		
		$form_container = new FormContainer($lang->mods_add_category);
		$form_container->output_row($lang->mods_name."<em>*</em>", $lang->mods_name_desc, $form->generate_text_box('name', '', array('id' => 'name')), 'name');
		$form_container->output_row($lang->mods_description, $lang->mods_description_desc, $form->generate_text_area('description', '', array('id' => 'description')), 'description');
		$form_container->output_row($lang->mods_disporder."<em>*</em>", $lang->mods_disporder_desc, $form->generate_text_box('disporder', '1', array('id' => 'disporder')), 'disporder');
		$form_container->output_row($lang->mods_parent, "", $form->generate_select_box('parent', array_map('ucfirst', $mods->categories->getParents()), '', array('id' => 'parent')), 'parent');
		
		$form_container->end();
	
		$buttons = "";
		$buttons[] = $form->generate_submit_button($lang->mods_submit);
		$buttons[] = $form->generate_reset_button($lang->mods_reset);
		$form->output_submit_wrapper($buttons);
		$form->end();
	}
}
elseif ($mybb->input['action'] == 'edit') // Edit entry
{
	$category = $mods->categories->getByID($mybb->input['cid']);
	if (empty($category))
	{
		flash_message($lang->mods_category_invalid, 'error');
		admin_redirect("index.php?module=mods-categories");
	}

	if ($mybb->request_method == "post") // submit
	{
		if (empty($mybb->input['name']))
		{
			flash_message($lang->mods_no_name, 'error');
			admin_redirect("index.php?module=mods-categories");
		}
		
		$mybb->input['parent'] = strtolower($mybb->input['parent']);
		
		if (!$mods->categories->validateParent($mybb->input['parent']))
		{
			flash_message($lang->mods_no_parent_error, 'error');
			admin_redirect("index.php?module=mods-categories");
		}
		
		$update_array = array(
			'name' => $mybb->input['name'],
			'description' => $mybb->input['description'],
			'disporder' => intval($mybb->input['disporder']),
			'parent' => $mybb->input['parent'],
		);
		
		$mods->categories->updateByID($update_array, (int)$category['cid']);
		
		flash_message($lang->mods_category_edited, 'success');
		admin_redirect("index.php?module=mods-categories");
	}
	else {
		
		$form = new Form("index.php?module=mods-categories&amp;action=edit", "post", "categories");
		
		echo $form->generate_hidden_field("cid", $category['cid']);
		
		$form_container = new FormContainer($lang->mods_edit_category);
		$form_container->output_row($lang->mods_name."<em>*</em>", $lang->mods_name_desc, $form->generate_text_box('name', $category['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->mods_description, $lang->mods_description_desc, $form->generate_text_area('description', $category['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->mods_disporder."<em>*</em>", $lang->mods_disporder_desc, $form->generate_text_box('disporder', $category['disporder'], array('id' => 'disporder')), 'disporder');
		$form_container->output_row($lang->mods_parent, "", $form->generate_select_box('parent', array_map('ucfirst', $mods->categories->getParents()), array($category['parent']), array('id' => 'parent')), 'parent');
		
		$form_container->end();
	
		$buttons = "";
		$buttons[] = $form->generate_submit_button($lang->mods_submit);
		$buttons[] = $form->generate_reset_button($lang->mods_reset);
		$form->output_submit_wrapper($buttons);
		$form->end();
	}
}

elseif ($mybb->input['action'] == 'delete')
{
	if($mybb->input['no']) // user clicked no
	{
		admin_redirect("index.php?module=mods-categories");
	}

	if($mybb->request_method == "post")
	{
		if(!isset($mybb->input['my_post_key']) || $mybb->post_code != $mybb->input['my_post_key'])
		{
			$mybb->request_method = "get";
			flash_message($lang->mods_error, 'error');
			admin_redirect("index.php?module=mods-categories");
		}
		
		if (!$db->fetch_field($db->simple_select('mods_categories', 'name', 'cid='.intval($mybb->input['cid']), array('limit' => 1)), 'name'))
		{
			flash_message($lang->mods_category_invalid, 'error');
			admin_redirect('index.php?module=mods-categories');
		}
		else {					
			// Delete category
			$mods->categories->deleteByID((int)$mybb->input['cid']);
			
			flash_message($lang->mods_category_deleted, 'success');
			admin_redirect('index.php?module=mods-categories');
		}
	}
	else
	{
		$mybb->input['cid'] = intval($mybb->input['cid']);
		$form = new Form("index.php?module=mods-categories&amp;action=delete&amp;cid={$mybb->input['cid']}&amp;my_post_key={$mybb->post_code}", 'post');
		echo "<div class=\"confirm_action\">\n";
		echo "<p>{$lang->mods_category_deleteconfirm}</p>\n";
		echo "<br />\n";
		echo "<p class=\"buttons\">\n";
		echo $form->generate_submit_button($lang->yes, array('class' => 'button_yes'));
		echo $form->generate_submit_button($lang->no, array("name" => "no", 'class' => 'button_no'));
		echo "</p>\n";
		echo "</div>\n";
		$form->end();
	}
}

$page->output_footer();

exit;

?>
