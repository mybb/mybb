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

$page->add_breadcrumb_item($lang->warning_logs, "index.php?module=tools-warninglog");

$plugins->run_hooks("admin_tools_warninglog_begin");

// Revoke a warning
if($mybb->input['action'] == "do_revoke" && $mybb->request_method == "post")
{
	$query = $db->simple_select("warnings", "*", "wid='".$mybb->get_input('wid', MyBB::INPUT_INT)."'");
	$warning = $db->fetch_array($query);

	if(!$warning['wid'])
	{
		flash_message($lang->error_invalid_warning, 'error');
		admin_redirect("index.php?module=tools-warninglog");
	}
	else if($warning['daterevoked'])
	{
		flash_message($lang->error_already_revoked, 'error');
		admin_redirect("index.php?module=tools-warninglog&amp;action=view&amp;wid={$warning['wid']}");
	}

	$user = get_user($warning['uid']);

	$plugins->run_hooks("admin_tools_warninglog_do_revoke");

	if(!trim($mybb->input['reason']))
	{
		$warn_errors[] = $lang->error_no_revoke_reason;
		$mybb->input['action'] = "view";
	}
	else
	{
		// Warning is still active, lower users point count
		if($warning['expired'] != 1)
		{
			$new_warning_points = $user['warningpoints']-$warning['points'];
			if($new_warning_points < 0)
			{
				$new_warning_points = 0;
			}

			// Update user
			$updated_user = array(
				"warningpoints" => $new_warning_points
			);
		}

		// Update warning
		$updated_warning = array(
			"expired" => 1,
			"daterevoked" => TIME_NOW,
			"revokedby" => $mybb->user['uid'],
			"revokereason" => $db->escape_string($mybb->input['reason'])
		);

		$plugins->run_hooks("admin_tools_warninglog_do_revoke_commit");

		if($warning['expired'] != 1)
		{
			$db->update_query("users", $updated_user, "uid='{$warning['uid']}'");
		}

		$db->update_query("warnings", $updated_warning, "wid='{$warning['wid']}'");

		flash_message($lang->redirect_warning_revoked, 'success');
		admin_redirect("index.php?module=tools-warninglog&amp;action=view&amp;wid={$warning['wid']}");
	}
}

// Detailed view of a warning
if($mybb->input['action'] == "view")
{
	$query = $db->query("
		SELECT w.*, t.title AS type_title, u.username, p.subject AS post_subject
		FROM ".TABLE_PREFIX."warnings w
		LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (t.tid=w.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.issuedby)
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=w.pid)
		WHERE w.wid='".$mybb->get_input('wid', MyBB::INPUT_INT)."'
	");
	$warning = $db->fetch_array($query);

	if(!$warning['wid'])
	{
		flash_message($lang->error_invalid_warning, 'error');
		admin_redirect("index.php?module=tools-warninglog");
	}

	$user = get_user((int)$warning['uid']);

	$plugins->run_hooks("admin_tools_warninglog_view");

	$page->add_breadcrumb_item($lang->warning_details, "index.php?module=tools-warninglog&amp;action=view&amp;wid={$warning['wid']}");

	$page->output_header($lang->warning_details);

	$user_link = build_profile_link(htmlspecialchars_uni($user['username']), $user['uid'], "_blank");

	if(is_array($warn_errors))
	{
		$page->output_inline_error($warn_errors);
		$mybb->input['reason'] = htmlspecialchars_uni($mybb->input['reason']);
	}

	$table = new Table;

	$post_link = "";
	if($warning['post_subject'])
	{
		if(!is_object($parser))
		{
			require_once MYBB_ROOT."inc/class_parser.php";
			$parser = new postParser;
		}

		$warning['post_subject'] = $parser->parse_badwords($warning['post_subject']);
		$warning['post_subject'] = htmlspecialchars_uni($warning['post_subject']);
		$post_link = get_post_link($warning['pid']);
		$table->construct_cell("<strong>{$lang->warned_user}</strong><br /><br />{$user_link}");
		$table->construct_cell("<strong>{$lang->post}</strong><br /><br /><a href=\"{$mybb->settings['bburl']}/{$post_link}\" target=\"_blank\">{$warning['post_subject']}</a>");
		$table->construct_row();
	}
	else
	{
		$table->construct_cell("<strong>{$lang->warned_user}</strong><br /><br />{$user_link}", array('colspan' => 2));
		$table->construct_row();
	}

	$issuedby = build_profile_link(htmlspecialchars_uni($warning['username']), $warning['issuedby'], "_blank");
	$notes = nl2br(htmlspecialchars_uni($warning['notes']));

	$date_issued = my_date('relative', $warning['dateline']);
	if($warning['type_title'])
	{
		$warning_type = $warning['type_title'];
	}
	else
	{
		$warning_type = $warning['title'];
	}
	$warning_type = htmlspecialchars_uni($warning_type);
	if($warning['points'] > 0)
	{
		$warning['points'] = "+{$warning['points']}";
	}

	$points = $lang->sprintf($lang->warning_points, $warning['points']);
	if($warning['expired'] != 1)
	{
		if($warning['expires'] == 0)
		{
			$expires = $lang->never;
		}
		else
		{
			$expires = my_date('relative', $warning['expires']);
		}
		$status = $lang->warning_active;
	}
	else
	{
		if($warning['daterevoked'])
		{
			$expires = $status = $lang->warning_revoked;
		}
		else if($warning['expires'])
		{
			$expires = $status = $lang->already_expired;
		}
	}

	$table->construct_cell("<strong>{$lang->warning}</strong><br /><br />{$warning_type} {$points}", array('width' => '50%'));
	$table->construct_cell("<strong>{$lang->date_issued}</strong><br /><br />{$date_issued}", array('width' => '50%'));
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->issued_by}</strong><br /><br />{$issuedby}", array('width' => '50%'));
	$table->construct_cell("<strong>{$lang->expires}</strong><br /><br />{$expires}", array('width' => '50%'));
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->warning_note}</strong><br /><br />{$notes}", array('colspan' => 2));
	$table->construct_row();

	$table->output("<div class=\"float_right\" style=\"font-weight: normal;\">{$status}</div>".$lang->warning_details);

	if(!$warning['daterevoked'])
	{
		$form = new Form("index.php?module=tools-warninglog", "post");
		$form_container = new FormContainer($lang->revoke_warning);
		echo $form->generate_hidden_field('action', 'do_revoke');
		echo $form->generate_hidden_field('wid', $warning['wid']);
		$form_container->output_row("", $lang->revoke_warning_desc, $form->generate_text_area('reason', $mybb->input['reason'], array('id' => 'reason')), 'reason');

		$form_container->end();
		$buttons[] = $form->generate_submit_button($lang->revoke_warning);
		$form->output_submit_wrapper($buttons);
		$form->end();
	}
	else
	{
		$date_revoked = my_date('relative', $warning['daterevoked']);
		$revoked_user = get_user($warning['revokedby']);
		$revoked_by = build_profile_link(htmlspecialchars_uni($revoked_user['username']), $revoked_user['uid'], "_blank");
		$revoke_reason = nl2br(htmlspecialchars_uni($warning['revokereason']));

		$revoke_table = new Table;
		$revoke_table->construct_cell("<strong>{$lang->revoked_by}</strong><br /><br />{$revoked_by}", array('width' => '50%'));
		$revoke_table->construct_cell("<strong>{$lang->date_revoked}</strong><br /><br />{$date_revoked}", array('width' => '50%'));
		$revoke_table->construct_row();

		$revoke_table->construct_cell("<strong>{$lang->reason}</strong><br /><br />{$revoke_reason}", array('colspan' => 2));
		$revoke_table->construct_row();

		$revoke_table->output($lang->warning_is_revoked);
	}

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_tools_warninglog_start");

	$page->output_header($lang->warning_logs);

	$sub_tabs['warning_logs'] = array(
		'title' => $lang->warning_logs,
		'link' => "index.php?module=tools-warninglog",
		'description' => $lang->warning_logs_desc
	);

	$page->output_nav_tabs($sub_tabs, 'warning_logs');

	// Filter options
	$where_sql = '';
	if(!empty($mybb->input['filter']['username']))
	{
		$search_user = get_user_by_username($mybb->input['filter']['username']);

		$mybb->input['filter']['uid'] = (int)$search_user['uid'];
		$mybb->input['filter']['uid'] = $db->fetch_field($query, "uid");
	}
	if($mybb->input['filter']['uid'])
	{
		$search['uid'] = (int)$mybb->input['filter']['uid'];
		$where_sql .= " AND w.uid='{$search['uid']}'";
		if(!isset($mybb->input['search']['username']))
		{
			$user = get_user($mybb->input['search']['uid']);
			$mybb->input['search']['username'] = $user['username'];
		}
	}
	if(!empty($mybb->input['filter']['mod_username']))
	{
		$mod_user = get_user_by_username($mybb->input['filter']['mod_username']);

		$mybb->input['filter']['mod_uid'] = (int)$mod_user['uid'];
	}
	if($mybb->input['filter']['mod_uid'])
	{
		$search['mod_uid'] = (int)$mybb->input['filter']['mod_uid'];
		$where_sql .= " AND w.issuedby='{$search['mod_uid']}'";
		if(!isset($mybb->input['search']['mod_username']))
		{
			$mod_user = get_user($mybb->input['search']['uid']);
			$mybb->input['search']['mod_username'] = $mod_user['username'];
		}
	}
	if($mybb->input['filter']['reason'])
	{
		$search['reason'] = $db->escape_string_like($mybb->input['filter']['reason']);
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

	// Expire any warnings past their expiration date
	require_once MYBB_ROOT.'inc/datahandlers/warnings.php';
	$warningshandler = new WarningsHandler('update');

	$warningshandler->expire_warnings();

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
	if(isset($mybb->input['page']) && $mybb->get_input('page', MyBB::INPUT_INT) > 0)
	{
		$view_page = $mybb->get_input('page', MyBB::INPUT_INT);
	}
	$per_page = 20;
	if(isset($mybb->input['filter']['per_page']) && (int)$mybb->input['filter']['per_page'] > 0)
	{
		$per_page = (int)$mybb->input['filter']['per_page'];
	}
	$start = ($view_page-1) * $per_page;
	$pages = ceil($total_warnings / $per_page);
	if($view_page > $pages)
	{
		$start = 0;
		$view_page = 1;
	}
	// Build the base URL for pagination links
	$url = 'index.php?module=tools-warninglog';
	if(is_array($mybb->input['filter']) && count($mybb->input['filter']))
	{
		foreach($mybb->input['filter'] as $field => $value)
		{
			$value = urlencode($value);
			$url .= "&amp;filter[{$field}]={$value}";
		}
	}

	// The actual query
	$sql = "
		SELECT
			w.wid, w.title as custom_title, w.points, w.dateline, w.issuedby, w.expires, w.expired, w.daterevoked, w.revokedby,
			t.title,
			u.uid, u.username, u.usergroup, u.displaygroup,
			i.uid as mod_uid, i.username as mod_username, i.usergroup as mod_usergroup, i.displaygroup as mod_displaygroup
		FROM ".TABLE_PREFIX."warnings w
		LEFT JOIN ".TABLE_PREFIX."users u on (w.uid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (w.tid=t.tid)
			LEFT JOIN ".TABLE_PREFIX."users i ON (i.uid=w.issuedby)
		WHERE 1=1
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
		if(!$row['username'])
		{
			$row['username'] = $lang->guest;
		}

		$trow = alt_trow();
		$username = format_name(htmlspecialchars_uni($row['username']), $row['usergroup'], $row['displaygroup']);
		if(!$row['uid'])
		{
			$username_link = $username;
		}
		else
		{
			$username_link = build_profile_link($username, $row['uid'], "_blank");
		}
		$mod_username = format_name(htmlspecialchars_uni($row['mod_username']), $row['mod_usergroup'], $row['mod_displaygroup']);
		$mod_username_link = build_profile_link($mod_username, $row['mod_uid'], "_blank");
		$issued_date = my_date('relative', $row['dateline']);
		$revoked_text = '';
		if($row['daterevoked'] > 0)
		{
			$revoked_date = my_date('relative', $row['daterevoked']);
			$revoked_text = "<br /><small><strong>{$lang->revoked}</strong> {$revoked_date}</small>";
		}
		if($row['expires'] > 0)
		{
			$expire_date = my_date('relative', $row['expires']);
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
		$table->construct_cell("<a href=\"index.php?module=tools-warninglog&amp;action=view&amp;wid={$row['wid']}\">{$lang->view}</a>", array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_warning_logs, array("colspan" => "6"));
		$table->construct_row();
	}

	$table->output($lang->warning_logs);

	// Do we need to construct the pagination?
	if($total_warnings > $per_page)
	{
		echo draw_admin_pagination($view_page, $per_page, $total_warnings, $url)."<br />";
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

	$form = new Form("index.php?module=tools-warninglog", "post");
	$form_container = new FormContainer($lang->filter_warning_logs);
	$form_container->output_row($lang->filter_warned_user, "", $form->generate_text_box('filter[username]', $mybb->input['filter']['username'], array('id' => 'filter_username')), 'filter_username');
	$form_container->output_row($lang->filter_issued_by, "", $form->generate_text_box('filter[mod_username]', $mybb->input['filter']['mod_username'], array('id' => 'filter_mod_username')), 'filter_mod_username');
	$form_container->output_row($lang->filter_reason, "", $form->generate_text_box('filter[reason]', $mybb->input['filter']['reason'], array('id' => 'filter_reason')), 'filter_reason');
	$form_container->output_row($lang->sort_by, "", $form->generate_select_box('filter[sortby]', $sort_by, $mybb->input['filter']['sortby'], array('id' => 'filter_sortby'))." {$lang->in} ".$form->generate_select_box('filter[order]', $order_array, $order, array('id' => 'filter_order'))." {$lang->order}", 'filter_order');
	$form_container->output_row($lang->results_per_page, "", $form->generate_numeric_field('filter[per_page]', $per_page, array('id' => 'filter_per_page', 'min' => 1)), 'filter_per_page');

	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->filter_warning_logs);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}
