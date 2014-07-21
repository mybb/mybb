<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license

 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->spamlog, "index.php?module=tools-spamlog");

$sub_tabs['spam_logs'] = array(
	'title' => $lang->spam_logs,
	'link' => "index.php?module=tools-spamlog",
	'description' => $lang->spam_logs_desc
);
$sub_tabs['prune_spam_logs'] = array(
	'title' => $lang->prune_spam_logs,
	'link' => "index.php?module=tools-spamlog&amp;action=prune",
	'description' => $lang->prune_spam_logs_desc
);

$plugins->run_hooks("admin_tools_spamlog_begin");

if($mybb->input['action'] == 'prune')
{
	if(!is_super_admin($mybb->user['uid']))
	{
		flash_message($lang->cannot_perform_action_super_admin_general, 'error');
		admin_redirect("index.php?module=tools-spamlog");
	}

	$plugins->run_hooks("admin_tools_spamlog_prune");

	if($mybb->request_method == 'post')
	{
		$is_today = false;
		if($mybb->input['older_than'] <= 0)
		{
			$is_today = true;
			$mybb->input['older_than'] = 1;
		}
		$where = 'dateline < '.(TIME_NOW-(intval($mybb->input['older_than'])*86400));

		// Searching for entries in a specific module
		if($mybb->input['filter_username'])
		{
			$where .= " AND username='".$db->escape_string($mybb->input['filter_username'])."'";
		}
		
		// Searching for entries in a specific module
		if($mybb->input['filter_email'])
		{
			$where .= " AND email='".$db->escape_string($mybb->input['filter_email'])."'";
		}

		$query = $db->delete_query("spamlog", $where);
		$num_deleted = $db->affected_rows();

		$plugins->run_hooks("admin_tools_spamlog_prune_commit");

		// Log admin action
		log_admin_action($mybb->input['older_than'], $mybb->input['filter_username'], $mybb->input['filter_email'], $num_deleted);

		$success = $lang->success_pruned_spam_logs;
		if($is_today == true && $num_deleted > 0)
		{
			$success .= ' '.$lang->note_logs_locked;
		}
		elseif($is_today == true && $num_deleted == 0)
		{
			flash_message($lang->note_logs_locked, 'error');
			admin_redirect('index.php?module=tools-spamlog');
		}
		flash_message($success, 'success');
		admin_redirect('index.php?module=tools-spamlog');
	}
	$page->add_breadcrumb_item($lang->prune_spam_logs, 'index.php?module=tools-spamlog&amp;action=prune');
	$page->output_header($lang->prune_spam_logs);
	$page->output_nav_tabs($sub_tabs, 'prune_spam_logs');

	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = 'selected="selected"';
	$ordersel[$mybb->input['order']] = 'selected="selected"';

	$username_options = array();
	$username_options[''] = $lang->all_usernames;
	$username_options['0'] = '----------';
	$query = $db->query("
		SELECT DISTINCT l.username
		FROM ".TABLE_PREFIX."spamlog l
		ORDER BY l.username ASC
	");
	while($username = $db->fetch_array($query))
	{
		$username_options[$username['username']] = htmlspecialchars_uni($username['username']);
	}
	
	$email_options = array();
	$email_options[''] = $lang->all_emails;
	$email_options['0'] = '----------';
	$query = $db->query("
		SELECT DISTINCT l.email
		FROM ".TABLE_PREFIX."spamlog l
		ORDER BY l.email ASC
	");
	while($email = $db->fetch_array($query))
	{
		$email_options[$email['email']] = htmlspecialchars_uni($email['email']);
	}

	$form = new Form("index.php?module=tools-spamlog&amp;action=prune", "post");
	$form_container = new FormContainer($lang->prune_spam_logs);
	$form_container->output_row($lang->spam_username, "", $form->generate_select_box('filter_username', $username_options, $mybb->input['filter_username'], array('id' => 'filter_username')), 'filter_username');
	$form_container->output_row($lang->spam_email, "", $form->generate_select_box('filter_email', $email_options, $mybb->input['filter_email'], array('id' => 'filter_email')), 'filter_email');
	if(!$mybb->input['older_than'])
	{
		$mybb->input['older_than'] = '30';
	}
	$form_container->output_row($lang->date_range, "", $lang->older_than.$form->generate_text_box('older_than', $mybb->input['older_than'], array('id' => 'older_than', 'style' => 'width: 30px'))." {$lang->days}", 'older_than');
	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->prune_spam_logs);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_tools_spamlog_start");

	$page->output_header($lang->spam_logs);

	$sub_tabs['spam_logs'] = array(
		'title'       => $lang->spam_logs,
		'link'        => "index.php?module=tools-spamlog",
		'description' => $lang->spam_logs_desc
	);

	$page->output_nav_tabs($sub_tabs, 'spam_logs');

	// Pagination stuff
	$sql           = "
		SELECT COUNT(sid) as count
		FROM ".TABLE_PREFIX."spamlog;
	";
	$query         = $db->query($sql);
	$total_entries = $db->fetch_field($query, 'count');
	$view_page     = 1;
	if(isset($mybb->input['page']) && intval($mybb->input['page']) > 0)
	{
		$view_page = intval($mybb->input['page']);
	}
	$per_page = 20;
	if(isset($mybb->input['filter']['per_page']) && intval($mybb->input['filter']['per_page']) > 0)
	{
		$per_page = intval($mybb->input['filter']['per_page']);
	}
	$start = ($view_page - 1) * $per_page;
	// Build the base URL for pagination links
	$url = 'index.php?module=tools-spamlog';

	// The actual query
	$sql   = "
		SELECT * FROM ".TABLE_PREFIX."spamlog LIMIT {$start}, {$per_page}
	";
	$query = $db->query($sql);


	$table = new Table;
	$table->construct_header($lang->spam_username, array('width' => '20%'));
	$table->construct_header($lang->spam_email, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->spam_ip, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->spam_date, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->spam_confidence, array("class" => "align_center", 'width' => '20%'));

	while($row = $db->fetch_array($query))
	{
		$username   = htmlspecialchars_uni($row['username']);
		$email      = htmlspecialchars_uni($row['email']);
		$ip_address = htmlspecialchars_uni($row['ipaddress']);

		$dateline = '';
		if($row['dateline'] > 0)
		{
			$dateline = my_date('relative', $row['dateline']);
		}

		$confidence = '0%';
		$data       = @my_unserialize($row['data']);
		if(is_array($data) && !empty($data))
		{
			if(isset($data['confidence']))
			{
				$confidence = (double)$data['confidence'].'%';
			}
		}

		$table->construct_cell($username);
		$table->construct_cell($email);
		$table->construct_cell($ipaddress);
		$table->construct_cell($dateline);
		$table->construct_cell($confidence);
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_spam_logs, array("colspan" => "5"));
		$table->construct_row();
	}

	$table->output($lang->spam_logs);

	// Do we need to construct the pagination?
	if($total_entries > $per_page)
	{
		echo draw_admin_pagination($view_page, $per_page, $total_entries, $url)."<br />";
	}

	$page->output_footer();
}
