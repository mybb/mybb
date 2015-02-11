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

$page->add_breadcrumb_item($lang->system_email_log, "index.php?module=tools-mailerrors");

$plugins->run_hooks("admin_tools_mailerrors_begin");

if($mybb->input['action'] == "prune" && $mybb->request_method == "post")
{
	$plugins->run_hooks("admin_tools_mailerrors_prune");

	if($mybb->input['delete_all'])
	{
		$db->delete_query("mailerrors");
		$num_deleted = $db->affected_rows();

		$plugins->run_hooks("admin_tools_mailerrors_prune_delete_all_commit");

		// Log admin action
		log_admin_action($num_deleted);

		flash_message($lang->all_logs_deleted, 'success');
		admin_redirect("index.php?module=tools-mailerrors");
	}
	else if(is_array($mybb->input['log']))
	{
		$log_ids = implode(",", array_map("intval", $mybb->input['log']));
		if($log_ids)
		{
			$db->delete_query("mailerrors", "eid IN ({$log_ids})");
			$num_deleted = $db->affected_rows();
		}
	}

	$plugins->run_hooks("admin_tools_mailerrors_prune_commit");

	// Log admin action
	log_admin_action($num_deleted);

	flash_message($lang->selected_logs_deleted, 'success');
	admin_redirect("index.php?module=tools-mailerrors");
}

if($mybb->input['action'] == "view")
{
	$query = $db->simple_select("mailerrors", "*", "eid='".$mybb->get_input('eid', MyBB::INPUT_INT)."'");
	$log = $db->fetch_array($query);

	if(!$log['eid'])
	{
		exit;
	}

	$plugins->run_hooks("admin_tools_mailerrors_view");

	$log['toaddress'] = htmlspecialchars_uni($log['toaddress']);
	$log['fromaddress'] = htmlspecialchars_uni($log['fromaddress']);
	$log['subject'] = htmlspecialchars_uni($log['subject']);
	$log['error'] = htmlspecialchars_uni($log['error']);
	$log['smtperror'] = htmlspecialchars_uni($log['smtpcode']);
	$log['dateline'] = date($mybb->settings['dateformat'], $log['dateline']).", ".date($mybb->settings['timeformat'], $log['dateline']);
	$log['message'] = nl2br(htmlspecialchars_uni($log['message']));

	?>
	
<div class="modal">
	<div style="overflow-y: auto; max-height: 400px;">

	<?php
	$table = new Table();

	$table->construct_cell($log['error'], array("colspan" => 2));
	$table->construct_row();

	if($log['smtpcode'])
	{
		$table->construct_cell($lang->smtp_code);
		$table->construct_cell($log['smtpcode']);
		$table->construct_row();
	}

	if($log['smtperror'])
	{
		$table->construct_cell($lang->smtp_server_response);
		$table->construct_cell($log['smtperror']);
		$table->construct_row();
	}
	$table->output($lang->error);

	$table = new Table();

	$table->construct_cell($lang->to.":");
	$table->construct_cell("<a href=\"mailto:{$log['toaddress']}\">{$log['toaddress']}</a>");
	$table->construct_row();

	$table->construct_cell($lang->from.":");
	$table->construct_cell("<a href=\"mailto:{$log['fromaddress']}\">{$log['fromaddress']}</a>");
	$table->construct_row();

	$table->construct_cell($lang->subject.":");
	$table->construct_cell($log['subject']);
	$table->construct_row();

	$table->construct_cell($lang->date.":");
	$table->construct_cell($log['dateline']);
	$table->construct_row();

	$table->construct_cell($log['message'], array("colspan" => 2));
	$table->construct_row();

	$table->output($lang->email);

	?>
</div>
</div>
	<?php
	
	exit;
}

if(!$mybb->input['action'])
{
	$per_page = 20;

	if($mybb->input['page'] && $mybb->input['page'] > 1)
	{
		$mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);
		$start = ($mybb->input['page']*$per_page)-$per_page;
	}
	else
	{
		$mybb->input['page'] = 1;
		$start = 0;
	}

	$additional_criteria = array();

	$plugins->run_hooks("admin_tools_mailerrors_start");

	$page->output_header($lang->system_email_log);

	$sub_tabs['mailerrors'] = array(
		'title' => $lang->system_email_log,
		'link' => "index.php?module=tools-mailerrors",
		'description' => $lang->system_email_log_desc
	);

	$page->output_nav_tabs($sub_tabs, 'mailerrors');

	$form = new Form("index.php?module=tools-mailerrors&amp;action=prune", "post");

	// Begin criteria filtering
	if($mybb->input['subject'])
	{
		$additional_sql_criteria .= " AND subject LIKE '%".$db->escape_string_like($mybb->input['subject'])."%'";
		$additional_criteria[] = "subject='".htmlspecialchars_uni($mybb->input['subject'])."'";
		$form->generate_hidden_field("subject", $mybb->input['subject']);
	}

	if($mybb->input['fromaddress'])
	{
		$additional_sql_criteria .= " AND fromaddress LIKE '%".$db->escape_string_like($mybb->input['fromaddress'])."%'";
		$additional_criteria[] = "fromaddress='".urlencode($mybb->input['fromaddress'])."'";
		$form->generate_hidden_field("fromaddress", $mybb->input['fromaddress']);
	}

	if($mybb->input['toaddress'])
	{
		$additional_sql_criteria .= " AND toaddress LIKE '%".$db->escape_string_like($mybb->input['toaddress'])."%'";
		$additional_criteria[] = "toaddress='".urlencode($mybb->input['toaddress'])."'";
		$form->generate_hidden_field("toaddress", $mybb->input['toaddress']);
	}

	if($mybb->input['error'])
	{
		$additional_sql_criteria .= " AND error LIKE '%".$db->escape_string_like($mybb->input['error'])."%'";
		$additional_criteria[] = "error='".urlencode($mybb->input['error'])."'";
		$form->generate_hidden_field("error", $mybb->input['error']);
	}

	if($additional_criteria)
	{
		$additional_criteria = "&amp;".implode("&amp;", $additional_criteria);
	}
	else
	{
		$additional_criteria = '';
	}

	$table = new Table;
	$table->construct_header($form->generate_check_box("allbox", 1, '', array('class' => 'checkall')));
	$table->construct_header($lang->subject);
	$table->construct_header($lang->to, array("class" => "align_center", "width" => "20%"));
	$table->construct_header($lang->error_message, array("class" => "align_center", "width" => "30%"));
	$table->construct_header($lang->date_sent, array("class" => "align_center", "width" => "20%"));
	
	$query = $db->simple_select('mailerrors', '*', "1=1 $additional_sql_criteria", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit_start' => $start, 'limit' => $per_page));
	
	while($log = $db->fetch_array($query))
	{
		$log['subject'] = htmlspecialchars_uni($log['subject']);
		$log['toemail'] = htmlspecialchars_uni($log['toemail']);
		$log['error'] = htmlspecialchars_uni($log['error']);
		$log['dateline'] = date($mybb->settings['dateformat'], $log['dateline']).", ".date($mybb->settings['timeformat'], $log['dateline']);

		$table->construct_cell($form->generate_check_box("log[{$log['eid']}]", $log['eid'], ''));
		$table->construct_cell("<a href=\"javascript:MyBB.popupWindow('index.php?module=tools-mailerrors&amp;action=view&amp;eid={$log['eid']}', null, true);\">{$log['subject']}</a>");
		$find_from = "<div class=\"float_right\"><a href=\"index.php?module=tools-mailerrors&amp;toaddress={$log['toaddress']}\"><img src=\"styles/{$page->style}/images/icons/find.png\" title=\"{$lang->fine_emails_to_addr}\" alt=\"{$lang->find}\" /></a></div>";
		$table->construct_cell("{$find_from}<div>{$log['toaddress']}</div>");
		$table->construct_cell($log['error']);
		$table->construct_cell($log['dateline'], array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_logs, array("colspan" => 5));
		$table->construct_row();
		$table->output($lang->system_email_log);
	}
	else
	{
		$table->output($lang->system_email_log);
		$buttons[] = $form->generate_submit_button($lang->delete_selected, array('onclick' => "return confirm('{$lang->confirm_delete_logs}');"));
		$buttons[] = $form->generate_submit_button($lang->delete_all, array('name' => 'delete_all', 'onclick' => "return confirm('{$lang->confirm_delete_all_logs}');"));
		$form->output_submit_wrapper($buttons);
	}

	$form->end();

	$query = $db->simple_select("mailerrors l", "COUNT(eid) AS logs", "1=1 {$additional_sql_criteria}");
	$total_rows = $db->fetch_field($query, "logs");

	echo "<br />".draw_admin_pagination($mybb->input['page'], $per_page, $total_rows, "index.php?module=tools-mailerrors&amp;page={page}{$additional_criteria}");

	$form = new Form("index.php?module=tools-mailerrors", "post");
	$form_container = new FormContainer($lang->filter_system_email_log);
	$form_container->output_row($lang->subject_contains, "", $form->generate_text_box('subject', $mybb->input['subject'], array('id' => 'subject')), 'subject');
	$form_container->output_row($lang->error_message_contains, "", $form->generate_text_box('error', $mybb->input['error'], array('id' => 'error')), 'error');
	$form_container->output_row($lang->to_address_contains, "", $form->generate_text_box('toaddress', $mybb->input['toaddress'], array('id' => 'toaddress')), 'toaddress');
	$form_container->output_row($lang->from_address_contains, "", $form->generate_text_box('fromaddress', $mybb->input['fromaddress'], array('id' => 'fromaddress')), 'fromaddress');

	$form_container->end();
	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->filter_system_email_log);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}