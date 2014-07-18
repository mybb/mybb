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

// Detailed view of a warning
if($mybb->input['action'] == "view")
{
	$query   = $db->query(
		"
		SELECT w.*, t.title AS type_title, u.username, p.subject AS post_subject
		FROM ".TABLE_PREFIX."warnings w
		LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (t.tid=w.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.issuedby)
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=w.pid)
		WHERE w.wid='".intval($mybb->input['wid'])."'
	"
	);
	$warning = $db->fetch_array($query);

	if(!$warning['wid'])
	{
		flash_message($lang->error_invalid_warning, 'error');
		admin_redirect("index.php?module=tools-warninglog");
	}

	$user = get_user(intval($warning['uid']));

	$plugins->run_hooks("admin_tools_warninglog_view");

	$page->add_breadcrumb_item($lang->warning_details, "index.php?module=tools-warninglog&amp;action=view&amp;wid={$warning['wid']}");

	$page->output_header($lang->warning_details);

	$user_link = build_profile_link($user['username'], $user['uid'], "_blank");

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
		$post_link               = get_post_link($warning['pid']);
		$table->construct_cell("<strong>{$lang->warned_user}</strong><br /><br />{$user_link}");
		$table->construct_cell("<strong>{$lang->post}</strong><br /><br /><a href=\"{$mybb->settings['bburl']}/{$post_link}\" target=\"_blank\">{$warning['post_subject']}</a>");
		$table->construct_row();
	}
	else
	{
		$table->construct_cell("<strong>{$lang->warned_user}</strong><br /><br />{$user_link}", array('colspan' => 2));
		$table->construct_row();
	}

	$issuedby = build_profile_link($warning['username'], $warning['issuedby'], "_blank");
	$notes    = nl2br(htmlspecialchars_uni($warning['notes']));

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
		else
		{
			if($warning['expires'])
			{
				$expires = $status = $lang->already_expired;
			}
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
		$form           = new Form("index.php?module=tools-warninglog", "post");
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
		$date_revoked  = my_date('relative', $warning['daterevoked']);
		$revoked_user  = get_user($warning['revokedby']);
		$revoked_by    = build_profile_link($revoked_user['username'], $revoked_user['uid'], "_blank");
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

$plugins->run_hooks("admin_tools_spamlog_end");
?>
