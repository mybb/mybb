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

$page->add_breadcrumb_item($lang->spam_logs, "index.php?module=tools-spamlog");

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
		$mybb->input['older_than'] = $mybb->get_input('older_than', MyBB::INPUT_INT);
		if($mybb->input['older_than'] <= 0)
		{
			$is_today = true;
			$mybb->input['older_than'] = 1;
		}
		$where = 'dateline < '.(TIME_NOW-($mybb->input['older_than']*86400));

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

	$form = new Form("index.php?module=tools-spamlog&amp;action=prune", "post");
	$form_container = new FormContainer($lang->prune_spam_logs);
	$form_container->output_row($lang->spam_username, "", $form->generate_text_box('filter_username', $mybb->input['filter_username'], array('id' => 'filter_username')), 'filter_username');
	$form_container->output_row($lang->spam_email, "", $form->generate_text_box('filter_email', $mybb->input['filter_email'], array('id' => 'filter_email')), 'filter_email');
	if(!$mybb->input['older_than'])
	{
		$mybb->input['older_than'] = '30';
	}
	$form_container->output_row($lang->date_range, "", $lang->older_than.$form->generate_numeric_field('older_than', $mybb->input['older_than'], array('id' => 'older_than', 'style' => 'width: 50px', 'min' => 0))." {$lang->days}", 'older_than');
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

	$page->output_nav_tabs($sub_tabs, 'spam_logs');
	
	$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if(!$perpage)
	{
		$perpage = 20;
	}

	$where = '1=1';

	$additional_criteria = array();

	// Searching for entries witha  specific username
	if($mybb->input['username'])
	{
		$where .= " AND username='".$db->escape_string($mybb->input['username'])."'";
		$additional_criteria[] = "username=".urlencode($mybb->input['username']);
	}

	// Searching for entries with a specific email
	if($mybb->input['email'])
	{
		$where .= " AND email='".$db->escape_string($mybb->input['email'])."'";
		$additional_criteria[] = "email=".urlencode($mybb->input['email']);
	}
	
	// Searching for entries with a specific IP
	if($mybb->input['ipaddress'] > 0)
	{
		$where .= " AND ipaddress=".$db->escape_binary(my_inet_pton($mybb->input['ipaddress']));
		$additional_criteria[] = "ipaddress=".urlencode($mybb->input['ipaddress']);
	}

	if($additional_criteria)
	{
		$additional_criteria = "&amp;".implode("&amp;", $additional_criteria);
	}
	else
	{
		$additional_criteria = '';
	}

	// Order?
	switch($mybb->input['sortby'])
	{
		case "username":
			$sortby = "username";
			break;
		case "email":
			$sortby = "email";
			break;
		case "ipaddress":
			$sortby = "ipaddress";
			break;
		default:
			$sortby = "dateline";
	}
	$order = $mybb->input['order'];
	if($order != "asc")
	{
		$order = "desc";
	}

	$query = $db->simple_select("spamlog", "COUNT(sid) AS count", $where);
	$rescount = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->input['page'] != "last")
	{
		$pagecnt = $mybb->get_input('page', MyBB::INPUT_INT);
	}

	$logcount = (int)$rescount;
	$pages = $logcount / $perpage;
	$pages = ceil($pages);

	if($mybb->input['page'] == "last")
	{
		$pagecnt = $pages;
	}

	if($pagecnt > $pages)
	{
		$pagecnt = 1;
	}

	if($pagecnt)
	{
		$start = ($pagecnt-1) * $perpage;
	}
	else
	{
		$start = 0;
		$pagecnt = 1;
	}

	$table = new Table;
	$table->construct_header($lang->spam_username, array('width' => '20%'));
	$table->construct_header($lang->spam_email, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->spam_ip, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->spam_date, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->spam_confidence, array("class" => "align_center", 'width' => '20%'));

	$query = $db->simple_select("spamlog", "*", $where, array('order_by' => $sortby, 'order_dir' => $order, 'limit_start' => $start, 'limit' => $perpage));
	while($row = $db->fetch_array($query))
	{
		$username   = htmlspecialchars_uni($row['username']);
		$email      = htmlspecialchars_uni($row['email']);
		$ip_address = my_inet_ntop($db->unescape_binary($row['ipaddress']));

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

		$search_sfs = "<div class=\"float_right\"><a href=\"http://www.stopforumspam.com/ipcheck/{$ip_address}\" target=\"_blank\" rel=\"noopener\"><img src=\"styles/{$page->style}/images/icons/find.png\" title=\"{$lang->search_ip_on_sfs}\" alt=\"{$lang->search}\" /></a></div>";

		$table->construct_cell($username);
		$table->construct_cell($email);
		$table->construct_cell("{$search_sfs}<div>{$ip_address}</div>");
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
	if($rescount > $perpage)
	{
		echo draw_admin_pagination($pagecnt, $perpage, $rescount, "index.php?module=tools-spamlog&amp;perpage={$perpage}{$additional_criteria}&amp;sortby={$mybb->input['sortby']}&amp;order={$order}")."<br />";
	}

	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = "selected=\"selected\"";
	$ordersel[$mybb->input['order']] = "selected=\"selected\"";

	$sort_by = array(
		'dateline' => $lang->spam_date,
		'username' => $lang->spam_username,
		'email' => $lang->spam_email,
		'ipaddress' => $lang->spam_ip,
	);

	$order_array = array(
		'asc' => $lang->asc,
		'desc' => $lang->desc
	);

	$form = new Form("index.php?module=tools-spamlog", "post");
	$form_container = new FormContainer($lang->filter_spam_logs);
	$form_container->output_row($lang->spam_username, "", $form->generate_text_box('username', htmlspecialchars_uni($mybb->get_input('username')), array('id' => 'username')), 'suername');
	$form_container->output_row($lang->spam_email, "", $form->generate_text_box('email', $mybb->input['email'], array('id' => 'email')), 'email');
	$form_container->output_row($lang->spam_ip, "", $form->generate_text_box('ipaddress', $mybb->input['ipaddress'], array('id' => 'ipaddress')), 'ipaddress');
	$form_container->output_row($lang->sort_by, "", $form->generate_select_box('sortby', $sort_by, $mybb->input['sortby'], array('id' => 'sortby'))." {$lang->in} ".$form->generate_select_box('order', $order_array, $order, array('id' => 'order'))." {$lang->order}", 'order');
	$form_container->output_row($lang->results_per_page, "", $form->generate_numeric_field('perpage', $perpage, array('id' => 'perpage', 'min' => 1)), 'perpage');

	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->filter_spam_logs);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}
