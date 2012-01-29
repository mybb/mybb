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

$page->add_breadcrumb_item($lang->mods_projects, 'index.php?module=mods-projects');

$page->output_header($lang->mods_projects);

	
$sub_tabs['projects_listing'] = array(
	'title'			=> $lang->mods_projects_listing,
	'link'			=> 'index.php?module=mods-projects',
	'description'	=> $lang->mods_projects_listing_desc
);

$sub_tabs['projects_manage'] = array(
	'title'			=> $lang->mods_projects_manage,
	'link'			=> 'index.php?module=mods-projects&amp;action=manage',
	'description'	=> $lang->mods_projects_manage_desc
);
	
switch ($mybb->input['action'])
{
	case 'manage':
		$page->output_nav_tabs($sub_tabs, 'projects_manage');
	break;
	default:
		$page->output_nav_tabs($sub_tabs, 'projects_listing');
}

if (!$mybb->input['action']) // No action, view entries
{
	$per_page = 10;
	if($mybb->input['page'] && $mybb->input['page'] > 1)
	{
		$mybb->input['page'] = intval($mybb->input['page']);
		$start = ($mybb->input['page']*$per_page)-$per_page;
	}
	else
	{
		$mybb->input['page'] = 1;
		$start = 0;
	}
	
	if ($mybb->input['category'])
	{
		$category = $mybb->input['category'];
		
		// We're browsing a sub category
		if ((int)$category > 0)
		{
			// Get category so we can build the filter
			$cat = $mods->categories->getByID((int)$category);
			
			switch ($cat['parent'])
			{
				case 'plugins':
					$parent = $lang->mods_plugins;
					break;
				case 'themes':
					$parent = $lang->mods_themes;
					break;
				case 'resources':
					$parent = $lang->mods_resources;
					break;
				case 'graphics':
					$parent = $lang->mods_graphics;
					break;
			}
			
			$cat['name'] = '<a href="index.php?module=mods-projects&amp;category='.htmlspecialchars_uni($cat['parent']).'">'.$parent.'</a> / <a href="index.php?module=mods-projects&amp;category='.intval($cat['cid']).'">'.htmlspecialchars_uni($cat['name']).'</a>';
		}
		else {
			// Main categories
			switch ($category)
			{
				case 'plugins':
					$cat['name'] = $lang->mods_plugins;
					break;
				case 'themes':
					$cat['name'] = $lang->mods_themes;
					break;
				case 'resources':
					$cat['name'] = $lang->mods_resources;
					break;
				case 'graphics':
					$cat['name'] = $lang->mods_graphics;
					break;
			}
			
			$cat['name'] = '<a href="index.php?module=mods-projects&amp;category='.htmlspecialchars_uni($cat['parent']).'">'.$cat['name'].'</a>';
		}
		
		$lang->mods_projects_cat_notice = $lang->sprintf($lang->mods_projects_cat_notice, $cat['name']);
		
		echo "<p class=\"notice\">{$lang->mods_projects_cat_notice}</p>";
		
		unset($cat);
	}
	
	$query = $db->simple_select("mods_projects", "COUNT(pid) as projects");
	$total_rows = $db->fetch_field($query, "projects");

	if ($total_rows > $per_page)
		echo "<br />".draw_admin_pagination($mybb->input['page'], $per_page, $total_rows, "index.php?module=mods-projects&amp;page={page}");
		
	// get all categories
	$cats = $mods->categories->getAll();

	// table
	$table = new Table;
	$table->construct_header($lang->mods_name);
	$table->construct_header($lang->mods_category);
	$table->construct_header($lang->mods_options, array('width' => '20%', 'class' => 'align_center'));
		
	$query = $db->query("
		SELECT u.*, p.*
		FROM ".TABLE_PREFIX."mods_projects p
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		ORDER BY p.uid ASC LIMIT {$per_page}
	");
	
	while ($project = $db->fetch_array($query))
	{
		if ($project['approved'] == 0)
			$table->construct_cell(htmlspecialchars_uni($project['name'])." <small>".$lang->mods_unapproved_notice."</small>");
		else
			$table->construct_cell(htmlspecialchars_uni($project['name']));
		
		$cat = $cats[$project['cid']];
		
		switch ($cat['parent'])
		{
			case 'plugins':
				$parent = $lang->mods_plugins;
				break;
			case 'themes':
				$parent = $lang->mods_themes;
				break;
			case 'resources':
				$parent = $lang->mods_resources;
				break;
			case 'graphics':
				$parent = $lang->mods_graphics;
				break;
		}
		
		$table->construct_cell('<a href="index.php?module=mods-projects&amp;category='.htmlspecialchars_uni($cat['parent']).'">'.$parent.'</a> / <a href="index.php?module=mods-projects&amp;category='.intval($cat['cid']).'">'.htmlspecialchars_uni($cat['name']).'</a>');
		
		$popup = new PopupMenu("project_{$project['pid']}", $lang->options);

		if ($project['approved'] == 1)
		{
			$popup->add_item($lang->mods_projects_unapprove, "index.php?module=mods-projects&amp;my_post_key={$mybb->post_code}&amp;action=unapprove&amp;pid={$project['pid']}");
		}
		else {
			$popup->add_item($lang->mods_projects_approve, "index.php?module=mods-projects&amp;my_post_key={$mybb->post_code}&amp;action=approve&amp;pid={$project['pid']}");
		}
			
		$popup->add_item($lang->mods_delete, "index.php?module=mods-projects&amp;action=delete&amp;pid={$project['pid']}&amp;my_post_key={$mybb->post_code}");
		$popup->add_item($lang->mods_projects_manage, "index.php?module=mods-projects&amp;action=manage&amp;pid={$project['pid']}&amp;my_post_key={$mybb->post_code}");
		
		$table->construct_cell($popup->fetch(), array('class' => 'align_center'));
		
		$table->construct_row();
	}
	
	if ($table->num_rows() == 0)
	{
		$table->construct_cell($lang->mods_no_data, array('colspan' => 3));
		
		$table->construct_row();
	}
	
	$table->output($lang->mods_projects);
}
elseif ($mybb->input['action'] == 'manage') // Add entry
{
	if ($mybb->request_method == "post") // submit
	{
		// Get user ID
		if (!($uid = $db->fetch_field($db->simple_select('users', 'uid', 'username=\''.trim($mybb->input['username']).'\'', array('limit' => 1)), 'uid')))
		{
			flash_message($lang->mods_user_invalid, 'error');
			admin_redirect('index.php?module=mods-projects');
		}
		
		// Get Project
		$pid = (int)$mybb->input['project'];
		$project = $mods->projects->getByID($pid);
		if (empty($project))
		{
			flash_message($lang->mods_invalid_pid, 'error');
			admin_redirect('index.php?module=mods-projects');
		}
		
		// Is the owner of the project the same one? If so, error out
		if ($project['uid'] == $uid)
		{
			flash_message($lang->mods_manage_already_owner, 'error');
			admin_redirect('index.php?module=mods-projects');
		}
		
		$update_array = array(
			'uid' => $uid,
		);
		
		$db->update_query('mods_projects', $update_array, 'pid=\''.$pid.'\'');
		
		flash_message($lang->mods_approved_owner_changed, 'success');
		admin_redirect("index.php?module=mods-projects");
	}
	else {
	
		$pid = (int)$mybb->input['pid'];
		
		$form = new Form("index.php?module=mods-projects&amp;action=manage", "post");

		$form_container = new FormContainer($lang->mods_manage);
		$form_container->output_row($lang->mods_manage_new_owner." <em>*</em>", $lang->mods_manage_new_owner_desc, $form->generate_text_box('username', $mybb->input['username'], array('id' => 'username')), 'username');
		$form_container->output_row($lang->mods_manage_project_id." <em>*</em>", $lang->mods_manage_project_id_desc, $form->generate_text_box('project', $mybb->input['pid'], array('id' => 'project')), 'project');
		$form_container->end();

		// Autocompletion for usernames
		echo '
		<script type="text/javascript" src="../jscripts/autocomplete.js?ver=140"></script>
		<script type="text/javascript">
		<!--
			new autoComplete("username", "../xmlhttp.php?action=get_users", {valueSpan: "username"});
		// -->
		</script>';

		$buttons[] = $form->generate_submit_button($lang->mods_alter_owner);
		$form->output_submit_wrapper($buttons);
		$form->end();
	}
}
elseif ($mybb->input['action'] == 'delete')
{
	if($mybb->input['no']) // user clicked no
	{
		admin_redirect("index.php?module=mods-projects");
	}

	if($mybb->request_method == "post")
	{
		if(!isset($mybb->input['my_post_key']) || $mybb->post_code != $mybb->input['my_post_key'])
		{
			$mybb->request_method = "get";
			flash_message($lang->mods_error, 'error');
			admin_redirect("index.php?module=mods-projects");
		}
		
		$pid = (int)$mybb->input['pid'];
		$project = $mods->projects->getByID($pid);
		if (empty($project))
		{
			flash_message($lang->mods_invalid_pid, 'error');
			admin_redirect('index.php?module=mods-projects');
		}
		
		// delete all builds
		$mods->projects->builds->deleteAll($pid);
		
		// delete the project
		$mods->projects->deleteByID($pid);
		
		// TODO: Delete suggestions and bugs
		/*
		$mods->projects->bugs->deleteAll($pid);
		$mods->projects->suggestions->deleteAll($pid);
		*/
			
		flash_message($lang->mods_projects_deleted, 'success');
		admin_redirect('index.php?module=mods-projects');
	}
	else
	{
		$pid = (int)$mybb->input['pid'];
		
		$form = new Form("index.php?module=mods-projects&amp;action=delete&amp;pid={$pid}&amp;my_post_key={$mybb->post_code}", 'post');
		echo "<div class=\"confirm_action\">\n";
		echo "<p>{$lang->mods_projects_delete_confirm}</p>\n";
		echo "<br />\n";
		echo "<p class=\"buttons\">\n";
		echo $form->generate_submit_button($lang->yes, array('class' => 'button_yes'));
		echo $form->generate_submit_button($lang->no, array("name" => "no", 'class' => 'button_no'));
		echo "</p>\n";
		echo "</div>\n";
		$form->end();
	}
}
elseif ($mybb->input['action'] == 'approve')
{
	if($mybb->input['no']) // user clicked no
	{
		admin_redirect("index.php?module=mods-projects");
	}

	if($mybb->request_method == "post")
	{
		if(!isset($mybb->input['my_post_key']) || $mybb->post_code != $mybb->input['my_post_key'])
		{
			$mybb->request_method = "get";
			flash_message($lang->pokepedia_error, 'error');
			admin_redirect("index.php?module=mods-projects");
		}
		
		$pid = (int)$mybb->input['pid'];
		$project = $mods->projects->getByID($pid);
		if (empty($project))
		{
			flash_message($lang->mods_invalid_pid, 'error');
			admin_redirect('index.php?module=mods-projects');
		}
		
		$update_array = array('approved' => 1);
		
		// Update the project
		$mods->projects->updateByID($update_array, $pid);
		
		// Get Category and update counter
		$cat = $mods->categories->getByID($project['cid']);
		if (!empty($cat))
			$mods->categories->updateByID(array('counter' => ++$cat['counter']), $cat['cid']);
			
		flash_message($lang->mods_projects_approved, 'success');
		admin_redirect('index.php?module=mods-projects');
	}
	else
	{
		$pid = (int)$mybb->input['pid'];
		
		$form = new Form("index.php?module=mods-projects&amp;action=approve&amp;pid={$pid}&amp;my_post_key={$mybb->post_code}", 'post');
		echo "<div class=\"confirm_action\">\n";
		echo "<p>{$lang->mods_projects_approve_confirm}</p>\n";
		echo "<br />\n";
		echo "<p class=\"buttons\">\n";
		echo $form->generate_submit_button($lang->yes, array('class' => 'button_yes'));
		echo $form->generate_submit_button($lang->no, array("name" => "no", 'class' => 'button_no'));
		echo "</p>\n";
		echo "</div>\n";
		$form->end();
	}
}
elseif ($mybb->input['action'] == 'unapprove')
{
	if($mybb->input['no']) // user clicked no
	{
		admin_redirect("index.php?module=mods-projects");
	}

	if($mybb->request_method == "post")
	{
		if(!isset($mybb->input['my_post_key']) || $mybb->post_code != $mybb->input['my_post_key'])
		{
			$mybb->request_method = "get";
			flash_message($lang->pokepedia_error, 'error');
			admin_redirect("index.php?module=mods-projects");
		}
		
		$pid = (int)$mybb->input['pid'];
		$project = $mods->projects->getByID($pid);
		if (empty($project))
		{
			flash_message($lang->mods_invalid_pid, 'error');
			admin_redirect('index.php?module=mods-projects');
		}
		
		$update_array = array('approved' => 0);
		
		// Update the project
		$mods->projects->updateByID($update_array, $pid);
		
		// Get Category and update counter
		$cat = $mods->categories->getByID($project['cid']);
		if (!empty($cat))
			$mods->categories->updateByID(array('counter' => --$cat['counter']), $cat['cid']);
			
		flash_message($lang->mods_projects_unapproved, 'success');
		admin_redirect('index.php?module=mods-projects');
	}
	else
	{
		$pid = (int)$mybb->input['pid'];
		
		$form = new Form("index.php?module=mods-projects&amp;action=unapprove&amp;pid={$pid}&amp;my_post_key={$mybb->post_code}", 'post');
		echo "<div class=\"confirm_action\">\n";
		echo "<p>{$lang->mods_projects_unapprove_confirm}</p>\n";
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
