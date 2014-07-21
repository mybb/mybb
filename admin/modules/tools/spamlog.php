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

$plugins->run_hooks("admin_tools_spamlog_begin");

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
	if(is_array($mybb->input['filter']) && count($mybb->input['filter']))
	{
		foreach($mybb->input['filter'] as $field => $value)
		{
			$value = urlencode($value);
			$url .= "&amp;filter[{$field}]={$value}";
		}
	}

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
