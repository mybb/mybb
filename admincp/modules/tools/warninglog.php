<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->warning_logs, "index.php?".SID."&amp;module=tools/warninglog");

if(!$mybb->input['action'])
{
	$page->output_header($lang->warning_logs);
	
	$sub_tabs['warning_logs'] = array(
		'title' => $lang->warning_logs,
		'link' => "index.php?".SID."&amp;module=tools/warninglog",
		'description' => $lang->warning_logs_desc
	);
	
	$page->output_nav_tabs($sub_tabs, 'warning_logs');
	
	// Filter options
	$where_sql = '';
	if($mybb->input['filter']['username'])
	{
		$search['username'] = $db->escape_string($mybb->input['filter']['username']);
		$query = $db->simple_select("users", "uid", "username='{$search['username']}'");
		$mybb->input['filter']['uid'] = $db->fetch_field($query, "uid");
	}
	if($mybb->input['filter']['uid'])
	{
		$search['uid'] = intval($mybb->input['filter']['uid']);
		$where_sql .= " AND w.uid='{$search['uid']}'";
		if(!isset($mybb->input['search']['username']))
		{
			$user = get_user($mybb->input['search']['uid']);
			$mybb->input['search']['username'] = $user['username'];
		}
	}
	if($mybb->input['filter']['mod_username'])
	{
		$search['mod_username'] = $db->escape_string($mybb->input['filter']['mod_username']);
		$query = $db->simple_select("users", "uid", "username='{$search['mod_username']}'");
		$mybb->input['filter']['mod_uid'] = $db->fetch_field($query, "uid");
	}
	if($mybb->input['filter']['mod_uid'])
	{
		$search['mod_uid'] = intval($mybb->input['filter']['mod_uid']);
		$where_sql .= " AND w.issuedby='{$search['mod_uid']}'";
		if(!isset($mybb->input['search']['mod_username']))
		{
			$mod_user = get_user($mybb->input['search']['uid']);
			$mybb->input['search']['mod_username'] = $mod_user['username'];
		}
	}
	if($mybb->input['filter']['reason'])
	{
		$search['reason'] = $db->escape_string($mybb->input['filter']['reason']);
		$where_sql .= " AND (w.notes LIKE '%{$search['reason']}%' OR t.title LIKE '%{$search['reason']}%' OR w.title LIKE '%{$search['reason']}%')";
	}
	$sortbysel = array();
	switch($mybb->input['filter']['sortby'])
	{
		case "username":
			$sortby = "u.username";
			$sortbysel['username'] = ' selected="selected"';
			break;
		case "expires":
			$sortby = "w.expires";
			$sortbysel['expires'] = ' selected="selected"';
			break;
		case "issuedby":
			$sortby = "i.username";
			$sortbysel['issuedby'] = ' selected="selected"';
			break;
		default: // "dateline"
			$sortby = "w.dateline";
			$sortbysel['dateline'] = ' selected="selected"';
	}
	$order = $mybb->input['filter']['order'];
	$ordersel = array();
	if($order != "asc")
	{
		$order = "desc";
		$ordersel['desc'] = ' selected="selected"';
	}
	else
	{
		$ordersel['asc'] = ' selected="selected"';
	}
	
	// Pagination stuff
	$sql = "
		SELECT COUNT(wid) as count
		FROM
			".TABLE_PREFIX."warnings w
			LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (w.tid=t.tid)
		WHERE 1=1
			{$where_sql}
	";
	$query = $db->query($sql);
	$total_warnings = $db->fetch_field($query, 'count');
	$view_page = 1;
	if(isset($mybb->input['page']) && intval($mybb->input['page']) > 0)
	{
		$view_page = intval($mybb->input['page']);
	}
	$per_page = 20;
	if(isset($mybb->input['filter']['per_page']) && intval($mybb->input['filter']['per_page']) > 0)
	{
		$per_page = intval($mybb->input['filter']['per_page']);
	}
	$start = ($view_page-1) * $per_page;
	// Build the base URL for pagination links
	$url = 'modcp.php?action=warninglogs';
	if(is_array($mybb->input['filter']) && count($mybb->input['filter']))
	{
		foreach($mybb->input['filter'] as $field => $value)
		{
			$value = urlencode($value);
			$url .= "&amp;filter[{$field}]={$value}";
		}		
	}
	$multipage = multipage($total_warnings, $per_page, $view_page, $url);

	// The actual query
	$sql = "
		SELECT
			w.wid, w.title as custom_title, w.points, w.dateline, w.issuedby, w.expires, w.expired, w.daterevoked, w.revokedby,
			t.title,
			u.uid, u.username, u.usergroup, u.displaygroup,
			i.uid as mod_uid, i.username as mod_username, i.usergroup as mod_usergroup, i.displaygroup as mod_displaygroup
		FROM
			(".TABLE_PREFIX."warnings w, ".TABLE_PREFIX."users u)
			LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (w.tid=t.tid)
			LEFT JOIN ".TABLE_PREFIX."users i ON (i.uid=w.issuedby)
		WHERE u.uid=w.uid
			{$where_sql}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$per_page}
	";
	$query = $db->query($sql);
	

	$table = new Table;
	$table->construct_header($lang->warned_user, array('width' => '15%'));
	$table->construct_header($lang->warning, array("class" => "align_center", 'width' => '25%'));
	$table->construct_header($lang->date_issued, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->expires, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->issued_by, array("class" => "align_center", 'width' => '15%'));
	$table->construct_header($lang->options, array("class" => "align_center", 'width' => '5%'));
	
	while($row = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$username = format_name($row['username'], $row['usergroup'], $row['displaygroup']);
		$username_link = build_profile_link($username, $row['uid']);
		$mod_username = format_name($row['mod_username'], $row['mod_usergroup'], $row['mod_displaygroup']);
		$mod_username_link = build_profile_link($mod_username, $row['mod_uid']);
		$issued_date = my_date($mybb->settings['dateformat'], $row['dateline']).' '.my_date($mybb->settings['timeformat'], $row['dateline']);
		$revoked_text = '';
		if($row['daterevoked'] > 0)
		{
			$revoked_date = my_date($mybb->settings['dateformat'], $row['daterevoked']).' '.my_date($mybb->settings['timeformat'], $row['daterevoked']);
			eval("\$revoked_text = \"".$templates->get("modcp_warninglogs_warning_revoked")."\";");
		}
		if($row['expires'] > 0)
		{
			$expire_date = my_date($mybb->settings['dateformat'], $row['expires']).' '.my_date($mybb->settings['timeformat'], $row['expires']);
		}
		else
		{
			$expire_date = $lang->never;
		}
		$title = $row['title'];
		if(empty($row['title']))
		{
			$title = $row['custom_title'];
		}
		$title = htmlspecialchars_uni($title);
		if($row['points'] > 0)
		{
			$points = '+'.$row['points'];
		}
		 
		$table->construct_cell($username_link);
		$table->construct_cell("{$title} ({$points})");
		$table->construct_cell($issued_date, array("class" => "align_center"));
		$table->construct_cell($expire_date.$revoked_text, array("class" => "align_center"));
		$table->construct_cell($mod_username_link);
		$table->construct_cell("<a href=\"warnings.php?action=view&amp;wid={$row['wid']}\">{$lang->view}</a>", array("class" => "align_center"));
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell($lang->no_warning_logs, array("colspan" => "5"));
		$table->construct_row();
	}
	
	$table->output($lang->warning_logs);
	
	// Do we need to construct the pagination?
	if($rescount > $perpage)
	{
		echo draw_admin_pagination($pagecnt, $perpage, $rescount, "index.php?".SID."&amp;module=tools/modlog&amp;perpage=$perpage&amp;uid={$mybb->input['uid']}&amp;fid={$mybb->input['fid']}&amp;sortby={$mybb->input['sortby']}&amp;order={$order}")."<br />";
	}
	
	$sort_by = array(
		'expires' => $lang->expiry_date,
		'dateline' => $lang->issued_date,
		'username' => $lang->warned_user,
		'issuedby' => $lang->issued_by
	);
	
	$order_array = array(
		'asc' => $lang->asc,
		'desc' => $lang->desc
	);

	$form = new Form("index.php?".SID."&amp;module=tools/modlog", "post");
	$form_container = new FormContainer($lang->filter_warning_logs);
	$form_container->output_row($lang->filter_warned_user, "", $form->generate_text_box('filter[username]', $mybb->input['filter']['username'], array('id' => 'filter_username')), 'filter_username');	
	$form_container->output_row($lang->filter_issued_by, "", $form->generate_text_box('filter[mod_username]', $mybb->input['filter']['mod_username'], array('id' => 'filter_mod_username')), 'filter_mod_username');
	$form_container->output_row($lang->filter_reason, "", $form->generate_text_box('filter[reason]', $mybb->input['filter']['reason'], array('id' => 'filter_reason')), 'filter_reason');
	$form_container->output_row($lang->sort_by, "", $form->generate_select_box('filter[sortby]', $sort_by, $mybb->input['filter']['sortby'], array('id' => 'filter_sortby'))." {$lang->in} ".$form->generate_select_box('order', $order_array, $order, array('id' => 'order'))." {$lang->order}", 'order');	
	$form_container->output_row($lang->results_per_page, "", $form->generate_text_box('filter[per_page]', $per_page, array('id' => 'filter_per_page')), 'filter_per_page');	

	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->filter_warning_logs);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}
?>