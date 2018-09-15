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

$page->add_breadcrumb_item($lang->banning, "index.php?module=config-banning");

$plugins->run_hooks("admin_config_banning_begin");

if($mybb->input['action'] == "add" && $mybb->request_method == "post")
{
	$plugins->run_hooks("admin_config_banning_add");

	if(!trim($mybb->input['filter']))
	{
		$errors[] = $lang->error_missing_ban_input;
	}

	$query = $db->simple_select("banfilters", "fid", "filter = '".$db->escape_string($mybb->input['filter'])."' AND type = '".$mybb->get_input('type', MyBB::INPUT_INT)."'");
	if($db->num_rows($query))
	{
		$errors[] = $lang->error_filter_already_banned;
	}

	if(!$errors)
	{
		$new_filter = array(
			"filter" => $db->escape_string($mybb->input['filter']),
			"type" => $mybb->get_input('type', MyBB::INPUT_INT),
			"dateline" => TIME_NOW
		);
		$fid = $db->insert_query("banfilters", $new_filter);

		$plugins->run_hooks("admin_config_banning_add_commit");

		if($mybb->input['type'] == 1)
		{
			$cache->update_bannedips();
		}
		else if($mybb->input['type'] == 3)
		{
			$cache->update_bannedemails();
		}

		// Log admin action
		log_admin_action($fid, $mybb->input['filter'], (int)$mybb->input['type']);

		if($mybb->input['type'] == 1)
		{
			flash_message($lang->success_ip_banned, 'success');
			admin_redirect("index.php?module=config-banning");
		}
		else if($mybb->input['type'] == 2)
		{
			flash_message($lang->success_username_disallowed, 'success');
			admin_redirect("index.php?module=config-banning&type=usernames");
		}
		else if($mybb->input['type'] == 3)
		{
			flash_message($lang->success_email_disallowed, 'success');
			admin_redirect("index.php?module=config-banning&type=emails");
		}
	}
	else
	{
		if($mybb->input['type'] == 1)
		{
			$mybb->input['type'] = "ips";
		}
		else if($mybb->input['type'] == 2)
		{
			$mybb->input['type'] = "usernames";
		}
		else if($mybb->input['type'] == 3)
		{
			$mybb->input['type'] = "emails";
		}
		$mybb->input['action'] = '';
	}
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("banfilters", "*", "fid='".$mybb->get_input('fid', MyBB::INPUT_INT)."'");
	$filter = $db->fetch_array($query);

	// Does the filter not exist?
	if(!$filter['fid'])
	{
		flash_message($lang->error_invalid_filter, 'error');
		admin_redirect("index.php?module=config-banning");
	}

	$plugins->run_hooks("admin_config_banning_delete");

	if($filter['type'] == 3)
	{
		$type = "emails";
	}
	else if($filter['type'] == 2)
	{
		$type = "usernames";
	}
	else
	{
		$type = "ips";
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=config-banning&type={$type}");
	}

	if($mybb->request_method == "post")
	{
		// Delete the ban filter
		$db->delete_query("banfilters", "fid='{$filter['fid']}'");

		$plugins->run_hooks("admin_config_banning_delete_commit");

		// Log admin action
		log_admin_action($filter['fid'], $filter['filter'], (int)$filter['type']);

		// Banned IP? Rebuild banned IP cache
		if($filter['type'] == 1)
		{
			$cache->update_bannedips();
		}
		else if($filter['type'] == 3)
		{
			$cache->update_bannedemails();
		}

		flash_message($lang->success_ban_deleted, 'success');
		admin_redirect("index.php?module=config-banning&type={$type}");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-banning&amp;action=delete&amp;fid={$filter['fid']}", $lang->confirm_ban_deletion);
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_config_banning_start");

	switch($mybb->input['type'])
	{
		case "emails":
			$type = "3";
			$title = $lang->disallowed_email_addresses;
			break;
		case "usernames":
			$type = "2";
			$title = $lang->disallowed_usernames;
			break;
		default:
			$type = "1";
			$title = $lang->banned_ip_addresses;
			$mybb->input['type'] = "ips";
	}

	$page->output_header($title);

	$sub_tabs['ips'] = array(
		'title' => $lang->banned_ips,
		'link' => "index.php?module=config-banning",
		'description' => $lang->banned_ips_desc
	);

	$sub_tabs['users'] = array(
		'title' => $lang->banned_accounts,
		'link' => "index.php?module=user-banning"
	);

	$sub_tabs['usernames'] = array(
		'title' => $lang->disallowed_usernames,
		'link' => "index.php?module=config-banning&amp;type=usernames",
		'description' => $lang->disallowed_usernames_desc
	);

	$sub_tabs['emails'] = array(
		'title' => $lang->disallowed_email_addresses,
		'link' => "index.php?module=config-banning&amp;type=emails",
		'description' => $lang->disallowed_email_addresses_desc
	);

	$page->output_nav_tabs($sub_tabs, $mybb->input['type']);

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?module=config-banning&amp;action=add", "post", "add");

	if($mybb->input['type'] == "usernames")
	{
		$form_container = new FormContainer($lang->add_disallowed_username);
		$form_container->output_row($lang->username." <em>*</em>", $lang->username_desc, $form->generate_text_box('filter', $mybb->input['filter'], array('id' => 'filter')), 'filter');
		$buttons[] = $form->generate_submit_button($lang->disallow_username);
	}
	else if($mybb->input['type'] == "emails")
	{
		$form_container = new FormContainer($lang->add_disallowed_email_address);
		$form_container->output_row($lang->email_address." <em>*</em>", $lang->email_address_desc, $form->generate_text_box('filter', $mybb->input['filter'], array('id' => 'filter')), 'filter');
		$buttons[] = $form->generate_submit_button($lang->disallow_email_address);
	}
	else
	{
		$form_container = new FormContainer($lang->ban_an_ip_address);
		$form_container->output_row($lang->ip_address." <em>*</em>", $lang->ip_address_desc, $form->generate_text_box('filter', $mybb->input['filter'], array('id' => 'filter')), 'filter');
		$buttons[] = $form->generate_submit_button($lang->ban_ip_address);
	}

	$form_container->end();
	echo $form->generate_hidden_field("type", $type);
	$form->output_submit_wrapper($buttons);
	$form->end();

	echo '<br />';

	$table = new Table;
	if($mybb->input['type'] == "usernames")
	{
		$table->construct_header($lang->username);
		$table->construct_header($lang->date_disallowed, array("class" => "align_center", "width" => 200));
		$table->construct_header($lang->last_attempted_use, array("class" => "align_center", "width" => 200));
	}
	else if($mybb->input['type'] == "emails")
	{
		$table->construct_header($lang->email_address);
		$table->construct_header($lang->date_disallowed, array("class" => "align_center", "width" => 200));
		$table->construct_header($lang->last_attempted_use, array("class" => "align_center", "width" => 200));
	}
	else
	{
		$table->construct_header($lang->ip_address);
		$table->construct_header($lang->ban_date, array("class" => "align_center", "width" => 200));
		$table->construct_header($lang->last_access, array("class" => "align_center", "width" => 200));
	}
	$table->construct_header($lang->controls, array("width" => 1));

	$query = $db->simple_select("banfilters", "*", "type='{$type}'", array("order_by" => "filter", "order_dir" => "asc"));
	while($filter = $db->fetch_array($query))
	{
		$filter['filter'] = htmlspecialchars_uni($filter['filter']);

		if($filter['lastuse'] > 0)
		{
			$last_use = my_date('relative', $filter['lastuse']);
		}
		else
		{
			$last_use = $lang->never;
		}

		if($filter['dateline'] > 0)
		{
			$date = my_date('relative', $filter['dateline']);
		}
		else
		{
			$date = $lang->na;
		}

		$table->construct_cell($filter['filter']);
		$table->construct_cell($date, array("class" => "align_center"));
		$table->construct_cell($last_use, array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=config-banning&amp;action=delete&amp;fid={$filter['fid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_ban_deletion}');\"><img src=\"styles/{$page->style}/images/icons/delete.png\" title=\"{$lang->delete}\" alt=\"{$lang->delete}\" /></a>", array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_bans, array("colspan" => 4));
		$table->construct_row();
	}

	$table->output($title);

	$page->output_footer();
}

