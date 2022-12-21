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

$page->add_breadcrumb_item($lang->user_email_log, "index.php?module=tools-maillogs");

$plugins->run_hooks("admin_tools_maillogs_begin");

if($mybb->input['action'] == "prune" && $mybb->request_method == "post")
{
	$plugins->run_hooks("admin_tools_maillogs_prune");

	if($mybb->input['delete_all'])
	{
		$db->delete_query("maillogs");
		$num_deleted = $db->affected_rows();

		$plugins->run_hooks("admin_tools_maillogs_prune_delete_all_commit");

		// Log admin action
		log_admin_action($num_deleted);

		flash_message($lang->all_logs_deleted, 'success');
		admin_redirect("index.php?module=tools-maillogs");
	}
	else if(is_array($mybb->input['log']))
	{
		$log_ids = implode(",", array_map("intval", $mybb->input['log']));
		if($log_ids)
		{
			$db->delete_query("maillogs", "mid IN ({$log_ids})");
			$num_deleted = $db->affected_rows();
		}

		// Log admin action
		log_admin_action($num_deleted);
	}

	$plugins->run_hooks("admin_tools_maillogs_prune_commit");

	flash_message($lang->selected_logs_deleted, 'success');
	admin_redirect("index.php?module=tools-maillogs");
}

if($mybb->input['action'] == "view")
{
	$query = $db->simple_select("maillogs", "*", "mid='".$mybb->get_input('mid', MyBB::INPUT_INT)."'");
	$log = $db->fetch_array($query);

	if(!$log['mid'])
	{
		exit;
	}

	$plugins->run_hooks("admin_tools_maillogs_view");

	$log['toemail'] = htmlspecialchars_uni($log['toemail']);
	$log['fromemail'] = htmlspecialchars_uni($log['fromemail']);
	$log['subject'] = htmlspecialchars_uni($log['subject']);
	$log['dateline'] = my_date('relative', $log['dateline']);
	if($mybb->settings['mail_logging'] == 1)
	{
		$log['message'] = $lang->na;
	}
	else
	{
		$log['message'] = nl2br(htmlspecialchars_uni($log['message']));
	}

	?>
	<div class="modal">
	<div style="overflow-y: auto; max-height: 400px;">

	<?php
	$table = new Table();

	$table->construct_cell($lang->to.":");
	$table->construct_cell("<a href=\"mailto:{$log['toemail']}\">{$log['toemail']}</a>");
	$table->construct_row();

	$table->construct_cell($lang->from.":");
	$table->construct_cell("<a href=\"mailto:{$log['fromemail']}\">{$log['fromemail']}</a>");
	$table->construct_row();

	$table->construct_cell($lang->ip_address.":");
	$table->construct_cell(my_inet_ntop($db->unescape_binary($log['ipaddress'])));
	$table->construct_row();

	$table->construct_cell($lang->subject.":");
	$table->construct_cell($log['subject']);
	$table->construct_row();

	$table->construct_cell($lang->date.":");
	$table->construct_cell($log['dateline']);
	$table->construct_row();

	$table->construct_cell($log['message'], array("colspan" => 2));
	$table->construct_row();

	$table->output($lang->user_email_log_viewer);

	?>
</div>
</div>
	<?php
}

if(!$mybb->input['action'])
{
	$query = $db->simple_select("maillogs l", "COUNT(l.mid) as logs");
	$total_rows = $db->fetch_field($query, "logs");

	if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	$per_page = $mybb->settings['threadsperpage'];

	if(!$per_page)
	{
		$per_page = 20;
	}

	$mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);
	if($mybb->input['page'] > 1)
	{
		$start = ($mybb->input['page']*$per_page)-$per_page;
		$pages = ceil($total_rows / $per_page);
		if($mybb->input['page'] > $pages)
		{
			$mybb->input['page'] = 1;
			$start = 0;
		}
	}
	else
	{
		$mybb->input['page'] = 1;
		$start = 0;
	}

	$additional_criteria = array();

	$plugins->run_hooks("admin_tools_maillogs_start");

	// Filter form was submitted - play around with the values
	if($mybb->request_method == "post")
	{
		if($mybb->input['from_type'] == "user")
		{
			$mybb->input['fromname'] = $mybb->input['from_value'];
		}
		else if($mybb->input['from_type'] == "email")
		{
			$mybb->input['fromemail'] = $mybb->input['from_value'];
		}

		if($mybb->input['to_type'] == "user")
		{
			$mybb->input['toname'] = $mybb->input['to_value'];
		}
		else if($mybb->input['to_type'] == "email")
		{
			$mybb->input['toemail'] = $mybb->input['to_value'];
		}
	}

	$touid = $mybb->get_input('touid', MyBB::INPUT_INT);
	$toname = $db->escape_string($mybb->get_input('toname'));
	$toemail = $db->escape_string_like($mybb->get_input('toemail'));

	$fromuid = $mybb->get_input('fromuid', MyBB::INPUT_INT);
	$fromemail = $db->escape_string_like($mybb->get_input('fromemail'));

	$subject = $db->escape_string_like($mybb->get_input('subject'));

	// Begin criteria filtering
	$additional_sql_criteria = '';
	if(!empty($mybb->input['subject']))
	{
		$additional_sql_criteria .= " AND l.subject LIKE '%{$subject}%'";
		$additional_criteria[] = "subject=".urlencode($mybb->input['subject']);
	}

	$from_filter = '';
	if(!empty($mybb->input['fromuid']))
	{
		$query = $db->simple_select("users", "uid, username", "uid = '{$fromuid}'");
		$user = $db->fetch_array($query);
		$from_filter = $user['username'];

		$additional_sql_criteria .= " AND l.fromuid = '{$fromuid}'";
		$additional_criteria[] = "fromuid={$fromuid}";
	}
	else if(!empty($mybb->input['fromname']))
	{
		$user = get_user_by_username($mybb->input['fromname'], array('fields' => 'uid, username'));
		$from_filter = $user['username'];

		if(!$user['uid'])
		{
			flash_message($lang->error_invalid_user, 'error');
			admin_redirect("index.php?module=tools-maillogs");
		}

		$additional_sql_criteria .= "AND l.fromuid = '{$user['uid']}'";
		$additional_criteria[] = "fromuid={$user['uid']}";
	}
	else if(!empty($mybb->input['fromemail']))
	{
		$additional_sql_criteria .= " AND l.fromemail LIKE '%{$fromemail}%'";
		$additional_criteria[] = "fromemail=".urlencode($mybb->input['fromemail']);
		$from_filter = $mybb->input['fromemail'];
	}

	$to_filter = '';
	if(!empty($mybb->input['touid']))
	{
		$query = $db->simple_select("users", "uid, username", "uid = '{$touid}'");
		$user = $db->fetch_array($query);
		$to_filter = $user['username'];

		$additional_sql_criteria .= " AND l.touid = '{$touid}'";
		$additional_criteria[] = "touid={$touid}";
	}
	else if(!empty($mybb->input['toname']))
	{
		$user = get_user_by_username($toname, array('fields' => 'username'));
		$to_filter = $user['username'];

		if(!$user['uid'])
		{
			flash_message($lang->error_invalid_user, 'error');
			admin_redirect("index.php?module=tools-maillogs");
		}

		$additional_sql_criteria .= "AND l.touid='{$user['uid']}'";
		$additional_criteria[] = "touid={$user['uid']}";
	}
	else if(!empty($mybb->input['toemail']))
	{
		$additional_sql_criteria .= " AND l.toemail LIKE '%{$toemail}%'";
		$additional_criteria[] = "toemail=".urlencode($mybb->input['toemail']);
		$to_filter = $mybb->input['toemail'];
	}

	if(!empty($additional_criteria))
	{
		$additional_criteria = "&amp;".implode("&amp;", $additional_criteria);
	}
	else
	{
		$additional_criteria = '';
	}

	$page->output_header($lang->user_email_log);

	$sub_tabs['maillogs'] = array(
		'title' => $lang->user_email_log,
		'link' => "index.php?module=tools-maillogs",
		'description' => $lang->user_email_log_desc
	);

	$page->output_nav_tabs($sub_tabs, 'maillogs');

	$form = new Form("index.php?module=tools-maillogs&amp;action=prune", "post");

	$table = new Table;
	$table->construct_header($form->generate_check_box("allbox", 1, '', array('class' => 'checkall')));
	$table->construct_header($lang->subject, array("colspan" => 2));
	$table->construct_header($lang->from, array("class" => "align_center", "width" => "20%"));
	$table->construct_header($lang->to, array("class" => "align_center", "width" => "20%"));
	$table->construct_header($lang->date_sent, array("class" => "align_center", "width" => "20%"));
	$table->construct_header($lang->ip_address, array("class" => "align_center", 'width' => '10%'));

	$query = $db->query("
		SELECT l.*, r.username AS to_username, f.username AS from_username, t.subject AS thread_subject
		FROM ".TABLE_PREFIX."maillogs l
		LEFT JOIN ".TABLE_PREFIX."users r ON (r.uid=l.touid)
		LEFT JOIN ".TABLE_PREFIX."users f ON (f.uid=l.fromuid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
		WHERE 1=1 {$additional_sql_criteria}
		ORDER BY l.dateline DESC
		LIMIT {$start}, {$per_page}
	");
	while($log = $db->fetch_array($query))
	{
		$table->construct_cell($form->generate_check_box("log[{$log['mid']}]", $log['mid'], ''), array("width" => 1));
		$log['subject'] = htmlspecialchars_uni($log['subject']);
		$log['dateline'] = my_date('relative', $log['dateline']);

		$plugins->run_hooks("admin_tools_maillogs_log");

		if($log['type'] == 1)
		{
			$table->construct_cell("<img src=\"styles/{$page->style}/images/icons/maillogs_user.png\" title=\"{$lang->email_sent_to_user}\" alt=\"\" />", array("width" => 1));
			$table->construct_cell("<a href=\"javascript:MyBB.popupWindow('index.php?module=tools-maillogs&amp;action=view&amp;mid={$log['mid']}', null, true);\">{$log['subject']}</a>");
		}
		elseif($log['type'] == 2)
		{
			if($log['thread_subject'])
			{
				$log['thread_subject'] = htmlspecialchars_uni($log['thread_subject']);
				$thread_link = "<a href=\"../".get_thread_link($log['tid'])."\">".$log['thread_subject']."</a>";
			}
			else
			{
				$thread_link = $lang->deleted;
			}
			$table->construct_cell("<img src=\"styles/{$page->style}/images/icons/maillogs_thread.png\" title=\"{$lang->sent_using_send_thread_feature}\" alt=\"\" />", array("width" => 1));
			$table->construct_cell("<a href=\"javascript:MyBB.popupWindow('index.php?module=tools-maillogs&amp;action=view&amp;mid={$log['mid']}', null, true);\">{$log['subject']}</a><br /><small>{$lang->thread} {$thread_link}</small>");
		}
		elseif($log['type'] == 3)
		{
			$table->construct_cell("<img src=\"styles/{$page->style}/images/icons/maillogs_contact.png\" title=\"{$lang->email_sent_using_contact_form}\" alt=\"\" />", array("width" => 1));
			$table->construct_cell("<a href=\"javascript:MyBB.popupWindow('index.php?module=tools-maillogs&amp;action=view&amp;mid={$log['mid']}', null, true);\">{$log['subject']}</a>");
		}
		else
		{
			$table->construct_cell("<img src=\"styles/{$page->style}/images/icons/default.png\" title=\"{$lang->email}\" alt=\"\" />", array("width" => 1));
			$table->construct_cell("<a href=\"javascript:MyBB.popupWindow('index.php?module=tools-maillogs&amp;action=view&amp;mid={$log['mid']}', null, true);\">{$log['subject']}</a>");
		}

		if($log['fromuid'] > 0)
		{
			$log['find_from'] = "<div class=\"float_right\"><a href=\"index.php?module=tools-maillogs&amp;fromuid={$log['fromuid']}\"><img src=\"styles/{$page->style}/images/icons/find.png\" title=\"{$lang->find_emails_by_user}\" alt=\"{$lang->find}\" /></a></div>";
		}
		else
		{
			$log['find_from'] = '';
		}

		if(!$log['from_username'] && $log['fromuid'] > 0)
		{
			$table->construct_cell("{$log['find_from']}<div>{$lang->deleted_user}</div>");
		}
		elseif($log['fromuid'] == 0)
		{
			$log['fromemail'] = htmlspecialchars_uni($log['fromemail']);
			$table->construct_cell("{$log['find_from']}<div>{$log['fromemail']}</div>");
		}
		else
		{
			$table->construct_cell("{$log['find_from']}<div><a href=\"../".get_profile_link($log['fromuid'])."\">{$log['from_username']}</a></div>");
		}

		if($log['touid'] > 0)
		{
			$log['find_to'] = "<div class=\"float_right\"><a href=\"index.php?module=tools-maillogs&amp;touid={$log['touid']}\"><img src=\"styles/{$page->style}/images/icons/find.png\" title=\"{$lang->find_emails_to_user}\" alt=\"{$lang->find}\" /></a></div>";
		}
		else
		{
			$log['find_to'] = '';
		}

		if(!$log['to_username'] && $log['touid'] > 0)
		{
			$table->construct_cell("{$log['find_to']}<div>{$lang->deleted_user}</div>");
		}
		elseif($log['touid'] == 0)
		{
			$log['toemail'] = htmlspecialchars_uni($log['toemail']);
			$table->construct_cell("{$log['find_to']}<div>{$log['toemail']}</div>");
		}
		else
		{
			$table->construct_cell("{$log['find_to']}<div><a href=\"../".get_profile_link($log['touid'])."\">{$log['to_username']}</a></div>");
		}

		$table->construct_cell($log['dateline'], array("class" => "align_center"));
		$table->construct_cell(my_inet_ntop($db->unescape_binary($log['ipaddress'])), array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_logs, array("colspan" => "7"));
		$table->construct_row();
		$table->output($lang->user_email_log);
	}
	else
	{
		$table->output($lang->user_email_log);
		$buttons[] = $form->generate_submit_button($lang->delete_selected, array('onclick' => "return confirm('{$lang->confirm_delete_logs}');"));
		$buttons[] = $form->generate_submit_button($lang->delete_all, array('name' => 'delete_all', 'onclick' => "return confirm('{$lang->confirm_delete_all_logs}');"));
		$form->output_submit_wrapper($buttons);
	}

	$form->end();

	echo "<br />".draw_admin_pagination($mybb->input['page'], $per_page, $total_rows, "index.php?module=tools-maillogs&amp;page={page}{$additional_criteria}");

	$form = new Form("index.php?module=tools-maillogs", "post");
	$form_container = new FormContainer($lang->filter_user_email_log);
	$user_email = array(
		"user" => $lang->username_is,
		"email" => $lang->email_contains
	);
	$form_container->output_row($lang->subject_contains, "", $form->generate_text_box('subject', $mybb->get_input('subject'), array('id' => 'subject')), 'subject');
	$from_type = '';
	if($mybb->get_input('fromname'))
	{
		$from_type = "user";
	}
	else if($mybb->get_input('fromemail'))
	{
		$from_type = "email";
	}
	$form_container->output_row($lang->from, "", $form->generate_select_box('from_type', $user_email, $from_type)." ".$form->generate_text_box('from_value', htmlspecialchars_uni($from_filter), array('id' => 'from_value')), 'from_value');
	$to_type = '';
	if($mybb->get_input('toname'))
	{
		$to_type = "user";
	}
	else if($mybb->get_input('toemail'))
	{
		$to_type = "email";
	}
	$form_container->output_row($lang->to, "", $form->generate_select_box('to_type', $user_email, $to_type)." ".$form->generate_text_box('to_value', htmlspecialchars_uni($to_filter), array('id' => 'to_value')), 'to_value');
	$form_container->end();
	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->filter_user_email_log);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}
