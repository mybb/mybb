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

$page->add_breadcrumb_item($lang->mods_approved_devs, 'index.php?module=mods-approved');

$page->output_header($lang->mods_approved_devs);

	
$sub_tabs['approved_view'] = array(
	'title'			=> $lang->mods_approved_devs_view,
	'link'			=> 'index.php?module=mods-approved',
	'description'	=> $lang->mods_approved_devs_view_desc
);

$sub_tabs['approved_add'] = array(
	'title'			=> $lang->mods_approved_devs_add,
	'link'			=> 'index.php?module=mods-approved&amp;action=add',
	'description'	=> $lang->mods_approved_devs_add_desc
);
	
switch ($mybb->input['action'])
{
	case 'add':
		$page->output_nav_tabs($sub_tabs, 'approved_add');
	break;
	default:
		$page->output_nav_tabs($sub_tabs, 'approved_view');
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
	
	$query = $db->simple_select("mods_approved", "COUNT(uid) as approved_users");
	$total_rows = $db->fetch_field($query, "approved_users");

	if ($total_rows > $per_page)
		echo "<br />".draw_admin_pagination($mybb->input['page'], $per_page, $total_rows, "index.php?module=mods-approved&amp;page={page}");

	// table
	$table = new Table;
	$table->construct_header($lang->mods_username);
	$table->construct_header($lang->mods_options, array('width' => '20%', 'class' => 'align_center'));
		
	$query = $db->query("
		SELECT u.*, a.*
		FROM ".TABLE_PREFIX."mods_approved a
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
		ORDER BY a.uid ASC LIMIT {$per_page}
	");
	
	while ($user = $db->fetch_array($query))
	{
		$table->construct_cell(build_profile_link(htmlspecialchars_uni($user['username']), $user['uid']));
		$table->construct_cell("<a href=\"index.php?module=mods-approved&amp;action=delete&amp;uid={$user['uid']}&amp;my_post_key={$mybb->post_code}\" target=\"_self\">{$lang->mods_delete}</a>", array('style' => 'text-align: center')); // delete button
		$table->construct_row();
	}
	
	if ($table->num_rows() == 0)
	{
		$table->construct_cell($lang->mods_no_data, array('colspan' => 2));
		
		$table->construct_row();
	}
	
	$table->output($lang->mods_approved_devs);
}
elseif ($mybb->input['action'] == 'add') // Add entry
{
	if ($mybb->request_method == "post") // submit
	{
		if (!($uid = $db->fetch_field($db->simple_select('users', 'uid', 'username=\''.trim($mybb->input['username']).'\'', array('limit' => 1)), 'uid')))
		{
			flash_message($lang->mods_user_invalid, 'error');
			admin_redirect('index.php?module=mods-approved');
		}
		
		if ($db->fetch_field($db->simple_select('mods_approved', 'uid', 'uid='.intval($mybb->input['uid']), array('limit' => 1)), 'uid'))
		{
			flash_message($lang->mods_user_already_approved, 'error');
			admin_redirect('index.php?module=mods-approved');
		}
		
		$insert_array = array(
			'uid' => $uid,
		);
		
		$db->insert_query('mods_approved', $insert_array);
		
		flash_message($lang->mods_approved_dev_added, 'success');
		admin_redirect("index.php?module=mods-approved");
	}
	else {
		
		$form = new Form("index.php?module=mods-approved&amp;action=add", "post", "mods");
		
		$form_container = new FormContainer($lang->mods_add_approved_dev);
		$form_container->output_row($lang->mods_username."<em>*</em>", $lang->mods_username_desc, $form->generate_text_box('username', '', array('id' => 'username')), 'username');
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
		admin_redirect("index.php?module=mods-approved");
	}

	if($mybb->request_method == "post")
	{
		if(!isset($mybb->input['my_post_key']) || $mybb->post_code != $mybb->input['my_post_key'])
		{
			$mybb->request_method = "get";
			flash_message($lang->mods_error, 'error');
			admin_redirect("index.php?module=mods-approved");
		}
		
		if (!$db->fetch_field($db->simple_select('mods_approved', 'uid', 'uid='.intval($mybb->input['uid']), array('limit' => 1)), 'uid'))
		{
			flash_message($lang->mods_user_invalid, 'error');
			admin_redirect('index.php?module=mods-approved');
		}
		else {					
			// Delete approved dev
			$db->delete_query('mods_approved', 'uid=\''.intval($mybb->input['uid']).'\'');
			
			flash_message($lang->mods_user_regular_dev, 'success');
			admin_redirect('index.php?module=mods-approved');
		}
	}
	else
	{
		$mybb->input['uid'] = intval($mybb->input['uid']);
		$form = new Form("index.php?module=mods-approved&amp;action=delete&amp;uid={$mybb->input['uid']}&amp;my_post_key={$mybb->post_code}", 'post');
		echo "<div class=\"confirm_action\">\n";
		echo "<p>{$lang->mods_approved_deleteconfirm}</p>\n";
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
