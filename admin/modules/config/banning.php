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

$mybb->input['filter'] = $mybb->get_input('filter');
$ban_times = fetch_ban_times();
$bantime = $mybb->get_input('bantime');
if($bantime === '')
{
	$bantime = '---';
}

if($mybb->input['action'] == "add" && $mybb->request_method == "post")
{
	$plugins->run_hooks("admin_config_banning_add");

	if(!trim($mybb->input['filter']))
	{
		$errors[] = $lang->error_missing_ban_input;
	}

	$query = $db->simple_select("banfilters", "fid", "filter = '".$db->escape_string($mybb->input['filter'])."' AND type = '".$mybb->get_input('type', MyBB::INPUT_INT)."' AND (type != 1 OR expires = 0 OR expires > ".TIME_NOW.")");
	if($db->num_rows($query))
	{
		$errors[] = $lang->error_filter_already_banned;
	}

	if($mybb->get_input('type', MyBB::INPUT_INT) === 1 && !isset($ban_times[$bantime]))
	{
		$errors[] = $lang->error_invalid_ban_length;
	}

	if(!$errors && $mybb->input['type'] == 1)
	{
		$ip_address = $db->escape_string($mybb->input['filter']);
		$subnet_mask = "0";
		if(strpos($ip_address, "*") !== false)
		{
			$ip_address = str_replace("*", "0", $ip_address);
		}
		else if(strpos($ip_address, "/") !== false)
		{
			list($ip_address, $subnet_mask) = explode("/", $ip_address);
		}

		$is_valid_v4 = filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $subnet_mask <= 32 && $subnet_mask >= 0;
		$is_valid_v6 = filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && $subnet_mask <= 128 && $subnet_mask >= 0;

		if (!$is_valid_v4 && !$is_valid_v6 || !ctype_digit($subnet_mask))
		{
			$errors[] = $lang->error_invalid_filter;
		}
	}

	if(!$errors)
	{
		$new_filter = array(
			"filter" => $db->escape_string($mybb->input['filter']),
			"type" => $mybb->get_input('type', MyBB::INPUT_INT),
			"dateline" => TIME_NOW
		);
		if($new_filter['type'] === 1)
		{
			// Remove expired IP bans so the same address can be banned again.
			$db->delete_query("banfilters", "type=1 AND expires!=0 AND expires<=".TIME_NOW);
			$new_filter['expires'] = $bantime === '---' ? 0 : ban_date2timestamp($bantime);
			$new_filter['notes'] = $db->escape_string($mybb->get_input('notes'));
		}
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

if($mybb->input['action'] == "edit_notes")
{
	$query = $db->simple_select("banfilters", "*", "fid='".$mybb->get_input('fid', MyBB::INPUT_INT)."' AND type='1'");
	$filter = $db->fetch_array($query);

	if(!$filter)
	{
		flash_message($lang->error_filter_not_found, 'error');
		admin_redirect("index.php?module=config-banning");
	}

	$plugins->run_hooks("admin_config_banning_edit_notes");

	if($mybb->request_method == "post")
	{
		if(!verify_post_check($mybb->get_input('my_post_key')))
		{
			flash_message($lang->invalid_post_verify_key2, 'error');
			admin_redirect("index.php?module=config-banning");
		}

		$db->update_query("banfilters", array('notes' => $db->escape_string($mybb->get_input('notes'))), "fid='{$filter['fid']}' AND type='1'");

		$plugins->run_hooks("admin_config_banning_edit_notes_commit");

		log_admin_action($filter['fid'], $filter['filter']);
		flash_message($lang->success_notes_updated, 'success');
		admin_redirect("index.php?module=config-banning");
	}

	$page->add_breadcrumb_item($lang->edit_notes);
	$page->output_header($lang->banned_ip_addresses." - ".$lang->edit_notes);

	$sub_tabs['ips'] = array(
		'title' => $lang->banned_ips,
		'link' => "index.php?module=config-banning"
	);
	$sub_tabs['edit_notes'] = array(
		'title' => $lang->edit_notes,
		'link' => "index.php?module=config-banning&amp;action=edit_notes&amp;fid={$filter['fid']}",
		'description' => $lang->notes_desc
	);
	$page->output_nav_tabs($sub_tabs, 'edit_notes');

	$form = new Form("index.php?module=config-banning&amp;action=edit_notes&amp;fid={$filter['fid']}", "post");
	$form_container = new FormContainer($lang->edit_notes);
	$form_container->output_row($lang->ip_address, "", htmlspecialchars_uni($filter['filter']));
	$form_container->output_row($lang->notes, $lang->notes_desc, $form->generate_text_area('notes', $filter['notes'] ?? '', array('id' => 'notes')), 'notes');
	$form_container->end();
	$form->output_submit_wrapper(array(
		$form->generate_submit_button($lang->save_notes)
	));
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("banfilters", "*", "fid='".$mybb->get_input('fid', MyBB::INPUT_INT)."'");
	$filter = $db->fetch_array($query);

	// Does the filter not exist?
	if(!$filter)
	{
		flash_message($lang->error_filter_not_found, 'error');
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
	if($mybb->get_input('no'))
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

	switch($mybb->get_input('type'))
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

	$query = $db->simple_select("banfilters", "COUNT(fid) AS filter", "type='{$type}'");
	$total_rows = $db->fetch_field($query, "filter");
	$per_page = 20;

	$pagenum = $mybb->get_input('page', MyBB::INPUT_INT);
	if($pagenum)
	{
		$start = ($pagenum - 1) * $per_page;
		$pages = ceil($total_rows / $per_page);
		if($pagenum > $pages)
		{
			$start = 0;
			$pagenum = 1;
		}
	}
	else
	{
		$start = 0;
		$pagenum = 1;
	}

	// Sorting logic for IP bans
	$allowed_sort_by = ['filter', 'dateline', 'lastuse'];

	$sort_by = $mybb->get_input('sort_by');
	$sort_by = in_array($sort_by, $allowed_sort_by, true) ? $sort_by : 'filter';
	$order = strtolower($mybb->get_input('order'));
	$order = in_array($order, array('asc', 'desc'), true) ? $order : 'asc';

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
		$form_container->output_row($lang->ban_length, "", $form->generate_select_box('bantime', $ban_times, $bantime, array('id' => 'bantime')), 'bantime');
		$form_container->output_row($lang->notes, $lang->notes_desc, $form->generate_text_area('notes', $mybb->get_input('notes'), array('id' => 'notes')), 'notes');
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
		$table->construct_header($lang->lift_date, array("class" => "align_center", "width" => 200));
		$table->construct_header($lang->notes);
	}
	$table->construct_header($lang->controls, array("width" => 1));

	$query = $db->simple_select("banfilters", "*", "type='{$type}'", array('limit_start' => $start, 'limit' => $per_page, "order_by" => $sort_by, "order_dir" => $order));
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
		$controls = '';
		if($type == 1)
		{
			$lift_date = $filter['expires'] == 0 ? $lang->permanent : my_date($mybb->settings['dateformat']." ".$mybb->settings['timeformat'], $filter['expires']);
			$table->construct_cell($lift_date, array("class" => "align_center"));
			$table->construct_cell(nl2br(htmlspecialchars_uni($filter['notes'] ?? '')));
			$controls = "<a href=\"index.php?module=config-banning&amp;action=edit_notes&amp;fid={$filter['fid']}\">{$lang->edit_notes}</a> ";
		}
		$table->construct_cell($controls."<a href=\"index.php?module=config-banning&amp;action=delete&amp;fid={$filter['fid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_ban_deletion}');\"><img src=\"styles/{$page->style}/images/icons/delete.png\" title=\"{$lang->delete}\" alt=\"{$lang->delete}\" /></a>", array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_bans, array("colspan" => $type == 1 ? 6 : 4));
		$table->construct_row();
	}

	$table->output($title);
	echo "<br />".draw_admin_pagination($pagenum, $per_page, $total_rows, "index.php?module=config-banning&amp;type={$mybb->get_input('type')}&amp;sort_by={$sort_by}&amp;order={$order}&amp;page={page}");

	// Only show sort form for IP bans
	if($mybb->get_input('type') == "ips")
	{
		$sort_options = [
			'filter' => $lang->ip_address,
			'dateline' => $lang->ban_date,
			'lastuse' => $lang->last_access
		];

		$order_options = [
			'asc' => $lang->asc,
			'desc' => $lang->desc
		];

		$filter_form = new Form("index.php?module=config-banning", "post");
		$form_container = new FormContainer($lang->filter_results);
		$form_container->output_row($lang->sort_by, "", $filter_form->generate_select_box('sort_by', $sort_options, $sort_by, array('id' => 'sort_by'))." {$lang->in} ".$filter_form->generate_select_box('order', $order_options, $order, array('id' => 'order'))." {$lang->order}", 'order');
		$form_container->end();

		$filter_buttons = array();
		$filter_buttons[] = $filter_form->generate_submit_button($lang->filter_results);

		echo $filter_form->generate_hidden_field("type", $type);

		$filter_form->output_submit_wrapper($filter_buttons);
		$filter_form->end();
	}

	$page->output_footer();
}

