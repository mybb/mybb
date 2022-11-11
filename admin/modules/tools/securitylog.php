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

$page->add_breadcrumb_item($lang->security_log, "index.php?module=tools-securitylog");

$sub_tabs['security_log'] = array(
	'title' => $lang->security_log,
	'link' => "index.php?module=tools-securitylog",
	'description' => $lang->security_log_desc
);
$sub_tabs['prune_security_log'] = array(
	'title' => $lang->prune_security_log,
	'link' => "index.php?module=tools-securitylog&amp;action=prune",
	'description' => $lang->prune_security_log_desc
);

if($mybb->input['action'] == 'prune')
{
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

		// Searching for entries by a particular user
		if($mybb->input['uid'])
		{
			$where .= " AND uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";
		}

		$db->delete_query("securitylog", $where);
		$num_deleted = $db->affected_rows();

		// Log admin action
		log_admin_action($mybb->input['older_than'], $mybb->input['uid'], $num_deleted);

		$success = $lang->success_pruned_security_logs;
		if($is_today == true && $num_deleted > 0)
		{
			$success .= ' '.$lang->note_logs_locked;
		}
		elseif($is_today == true && $num_deleted == 0)
		{
			flash_message($lang->note_logs_locked, 'error');
			admin_redirect("index.php?module=tools-securitylog");
		}
		flash_message($success, 'success');
		admin_redirect("index.php?module=tools-securitylog");
	}
	$page->add_breadcrumb_item($lang->prune_security_log, "index.php?module=tools-securitylog&amp;action=prune");
	$page->output_header($lang->prune_security_log);
	$page->output_nav_tabs($sub_tabs, 'prune_security_log');

	// Fetch filter options
	$sortbysel[$mybb->get_input('sortby')] = "selected=\"selected\"";
	$ordersel[$mybb->get_input('order')] = "selected=\"selected\"";

	$user_options[''] = $lang->all_users;
	$user_options['0'] = '----------';

	$query = $db->query("
		SELECT DISTINCT l.uid, u.username
		FROM ".TABLE_PREFIX."securitylog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (l.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		// Deleted Users
		if(!$user['username'])
		{
			$user['username'] = htmlspecialchars_uni($lang->na_deleted);
		}

		$user_options[$user['uid']] = htmlspecialchars_uni($user['username']);
	}

	$form = new Form("index.php?module=tools-securitylog&amp;action=prune", "post");
	$form_container = new FormContainer($lang->prune_security_log);
	$form_container->output_row($lang->username_colon, "", $form->generate_select_box('uid', $user_options, $mybb->get_input('uid'), array('id' => 'uid')), 'uid');
	if(!$mybb->get_input('older_than'))
	{
		$mybb->input['older_than'] = '60';
	}
	$form_container->output_row($lang->date_range, "", $lang->older_than.$form->generate_numeric_field('older_than', $mybb->get_input('older_than'), array('id' => 'older_than', 'style' => 'width: 50px', 'min' => 0)).' '.$lang->days, 'older_than');
	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->prune_security_log);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->security_log);

	$page->output_nav_tabs($sub_tabs, 'security_log');

	$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if(!$perpage)
	{
		if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
		{
			$mybb->settings['threadsperpage'] = 20;
		}
		
		$perpage = $mybb->settings['threadsperpage'];
	}

	$where = 'WHERE 1=1';

	// Searching for entries by a particular user
	if($mybb->get_input('uid') > 0)
	{
		$where .= " AND l.uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";
	}

	// Order?
	switch($mybb->get_input('sortby'))
	{
		case "username":
			$sortby = "u.username";
			break;
		default:
			$sortby = "l.dateline";
	}
	$order = $mybb->get_input('order');
	if($order != "asc")
	{
		$order = "desc";
	}

	$query = $db->query("
		SELECT COUNT(l.dateline) AS count
		FROM ".TABLE_PREFIX."securitylog l
		{$where}
	");
	$rescount = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->get_input('page') != "last")
	{
		$pagecnt = $mybb->get_input('page', MyBB::INPUT_INT);
	}

	$postcount = (int)$rescount;
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->get_input('page') == "last")
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
	$table->construct_header($lang->username, array('width' => '30%'));
	$table->construct_header($lang->date, array("class" => "align_center", 'width' => '35%'));
	$table->construct_header($lang->ipaddress, array("class" => "align_center", 'width' => '20%'));

	$query = $db->query("
		SELECT l.*, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."securitylog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		{$where}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($logitem = $db->fetch_array($query))
	{
		$logitem['dateline'] = my_date('relative', $logitem['dateline']);
		$trow = alt_trow();

		if($logitem['username'])
		{
			$username = format_name(htmlspecialchars_uni($logitem['username']), $logitem['usergroup'], $logitem['displaygroup']);
			$logitem['profilelink'] = build_profile_link($username, $logitem['uid'], "_blank");
		}
		else
		{
			$username = $logitem['profilelink'] = $logitem['username'] = htmlspecialchars_uni($lang->na_deleted);
		}

		$table->construct_cell($logitem['profilelink']);
		$table->construct_cell($logitem['dateline'], array("class" => "align_center"));
		$table->construct_cell(my_inet_ntop($db->unescape_binary($logitem['ipaddress'])), array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_security_logs, array("colspan" => "3"));
		$table->construct_row();
	}

	$table->output($lang->security_log);

	// Do we need to construct the pagination?
	if($rescount > $perpage)
	{
		echo draw_admin_pagination($pagecnt, $perpage, $rescount, "index.php?module=tools-securitylog&amp;perpage=$perpage&amp;uid={$mybb->input['uid']}&amp;sortby={$mybb->input['sortby']}&amp;order={$order}")."<br />";
	}

	// Fetch filter options
	$sortbysel[$mybb->get_input('sortby')] = "selected=\"selected\"";
	$ordersel[$mybb->get_input('order')] = "selected=\"selected\"";

	$user_options[''] = $lang->all_users;
	$user_options['0'] = '----------';

	$query = $db->query("
		SELECT DISTINCT l.uid, u.username
		FROM ".TABLE_PREFIX."securitylog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (l.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		// Deleted Users
		if(!$user['username'])
		{
			$user['username'] = htmlspecialchars_uni($lang->na_deleted);
		}

		$selected = '';
		if($mybb->get_input('uid') == $user['uid'])
		{
			$selected = "selected=\"selected\"";
		}
		$user_options[$user['uid']] = htmlspecialchars_uni($user['username']);
	}

	$sort_by = array(
		'dateline' => $lang->date,
		'username' => $lang->username
	);

	$order_array = array(
		'asc' => $lang->asc,
		'desc' => $lang->desc
	);

	$form = new Form("index.php?module=tools-securitylog", "post");
	$form_container = new FormContainer($lang->filter_security_logs);
	$form_container->output_row($lang->username_colon, "", $form->generate_select_box('uid', $user_options, $mybb->get_input('uid'), array('id' => 'uid')), 'uid');
	$form_container->output_row($lang->sort_by, "", $form->generate_select_box('sortby', $sort_by, $mybb->get_input('sortby'), array('id' => 'sortby'))." {$lang->in} ".$form->generate_select_box('order', $order_array, $order, array('id' => 'order'))." {$lang->order}", 'order');
	$form_container->output_row($lang->results_per_page, "", $form->generate_numeric_field('perpage', $perpage, array('id' => 'perpage', 'min' => 1)), 'perpage');

	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->filter_security_logs);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}
