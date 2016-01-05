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

require_once MYBB_ROOT."/inc/functions_task.php";

$page->add_breadcrumb_item($lang->task_manager, "index.php?module=tools-tasks");

$plugins->run_hooks("admin_tools_tasks_begin");

/**
 * Validates a string or array of values
 *
 * @param string|array $value Comma-separated list or array of values
 * @param int $min Minimum value
 * @param int $max Maximum value
 * @param string $return_type Set "string" to return in a comma-separated list, or "array" to return in an array
 * @return string|array String or array of valid values OR false if string/array is invalid
 */
function check_time_values($value, $min, $max, $return_type)
{
	// If the values aren't in an array form, make them into an array
	if(!is_array($value))
	{
		// Empty value == *
		if($value === '')
		{
			return ($return_type == 'string') ? '*' : array('*');
		}
		$implode = 1;
		$value = explode(',', $value);
	}
	// If * is in the array, always return with * because it overrides all
	if(in_array('*', $value))
	{
		return ($return_type == 'string') ? '*' : array('*');
	}
	// Validate each value in array
	foreach($value as $time)
	{
		if($time < $min || $time > $max)
		{
			return false;
		}
	}
	// Return based on return type
	if($return_type == 'string')
	{
		$value = implode(',', $value);
	}
	return $value;
}

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_tools_tasks_add");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = $lang->error_missing_description;
		}

		if(!file_exists(MYBB_ROOT."inc/tasks/".$mybb->input['file'].".php"))
		{
			$errors[] = $lang->error_invalid_task_file;
		}

		$mybb->input['minute'] = check_time_values($mybb->input['minute'], 0, 59, 'string');
		if($mybb->input['minute'] === false)
		{
			$errors[] = $lang->error_invalid_minute;
		}

		$mybb->input['hour'] = check_time_values($mybb->input['hour'], 0, 59, 'string');
		if($mybb->input['hour'] === false)
		{
			$errors[] = $lang->error_invalid_hour;
		}

		if($mybb->input['day'] != "*" && $mybb->input['day'] != '')
		{
			$mybb->input['day'] = check_time_values($mybb->input['day'], 1, 31, 'string');
			if($mybb->input['day'] === false)
			{
				$errors[] = $lang->error_invalid_day;
			}
			$mybb->input['weekday'] = array('*');
		}
		else
		{
			$mybb->input['weekday'] = check_time_values($mybb->input['weekday'], 0, 6, 'array');
			if($mybb->input['weekday'] === false)
			{
				$errors[] = $lang->error_invalid_weekday;
			}
			$mybb->input['day'] = '*';
		}

		$mybb->input['month'] = check_time_values($mybb->input['month'], 1, 12, 'array');
		if($mybb->input['month'] === false)
		{
			$errors[] = $lang->error_invalid_month;
		}

		if(!$errors)
		{
			$new_task = array(
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"file" => $db->escape_string($mybb->input['file']),
				"minute" => $db->escape_string($mybb->input['minute']),
				"hour" => $db->escape_string($mybb->input['hour']),
				"day" => $db->escape_string($mybb->input['day']),
				"month" => $db->escape_string(implode(',', $mybb->input['month'])),
				"weekday" => $db->escape_string(implode(',', $mybb->input['weekday'])),
				"enabled" => $mybb->get_input('enabled', MyBB::INPUT_INT),
				"logging" => $mybb->get_input('logging', MyBB::INPUT_INT)
			);

			$new_task['nextrun'] = fetch_next_run($new_task);
			$tid = $db->insert_query("tasks", $new_task);

			$plugins->run_hooks("admin_tools_tasks_add_commit");

			$cache->update_tasks();

			// Log admin action
			log_admin_action($tid, htmlspecialchars_uni($mybb->input['title']));

			flash_message($lang->success_task_created, 'success');
			admin_redirect("index.php?module=tools-tasks");
		}
	}
	$page->add_breadcrumb_item($lang->add_new_task);
	$page->output_header($lang->scheduled_tasks." - ".$lang->add_new_task);


	$sub_tabs['scheduled_tasks'] = array(
		'title' => $lang->scheduled_tasks,
		'link' => "index.php?module=tools-tasks"
	);

	$sub_tabs['add_task'] = array(
		'title' => $lang->add_new_task,
		'link' => "index.php?module=tools-tasks&amp;action=add",
		'description' => $lang->add_new_task_desc
	);

	$sub_tabs['task_logs'] = array(
		'title' => $lang->view_task_logs,
		'link' => "index.php?module=tools-tasks&amp;action=logs"
	);

	$page->output_nav_tabs($sub_tabs, 'add_task');
	$form = new Form("index.php?module=tools-tasks&amp;action=add", "post", "add");
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['minute'] = '*';
		$mybb->input['hour'] = '*';
		$mybb->input['day'] = '*';
		$mybb->input['weekday'] = '*';
		$mybb->input['month'] = '*';
	}
	$form_container = new FormContainer($lang->add_new_task);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');

	$task_list = array();
	$task_files = scandir(MYBB_ROOT."inc/tasks/");
	foreach($task_files as $task_file)
	{
		if(is_file(MYBB_ROOT."inc/tasks/{$task_file}") && get_extension($task_file) == "php")
		{
			$file_id = preg_replace("#\.".get_extension($task_file)."$#i", "$1", $task_file);
			$task_list[$file_id] = $task_file;
		}
	}
	$form_container->output_row($lang->task_file." <em>*</em>", $lang->task_file_desc, $form->generate_select_box("file", $task_list, $mybb->input['file'], array('id' => 'file')), 'file');
	$form_container->output_row($lang->time_minutes, $lang->time_minutes_desc, $form->generate_text_box('minute', $mybb->input['minute'], array('id' => 'minute')), 'minute');
	$form_container->output_row($lang->time_hours, $lang->time_hours_desc, $form->generate_text_box('hour', $mybb->input['hour'], array('id' => 'hour')), 'hour');
	$form_container->output_row($lang->time_days_of_month, $lang->time_days_of_month_desc, $form->generate_text_box('day', $mybb->input['day'], array('id' => 'day')), 'day');

	$options = array(
		"*" => $lang->every_weekday,
		"0" => $lang->sunday,
		"1" => $lang->monday,
		"2" => $lang->tuesday,
		"3" => $lang->wednesday,
		"4" => $lang->thursday,
		"5" => $lang->friday,
		"6" => $lang->saturday
	);
	$form_container->output_row($lang->time_weekdays, $lang->time_weekdays_desc, $form->generate_select_box('weekday[]', $options, $mybb->input['weekday'], array('id' => 'weekday', 'multiple' => true, 'size' => 8)), 'weekday');

	$options = array(
		"*" => $lang->every_month,
		"1" => $lang->january,
		"2" => $lang->february,
		"3" => $lang->march,
		"4" => $lang->april,
		"5" => $lang->may,
		"6" => $lang->june,
		"7" => $lang->july,
		"8" => $lang->august,
		"9" => $lang->september,
		"10" => $lang->october,
		"11" => $lang->november,
		"12" => $lang->december
	);
	$form_container->output_row($lang->time_months, $lang->time_months_desc, $form->generate_select_box('month[]', $options, $mybb->input['month'], array('id' => 'month', 'multiple' => true, 'size' => 13)), 'month');

	$form_container->output_row($lang->enable_logging." <em>*</em>", "", $form->generate_yes_no_radio("logging", $mybb->input['logging'], true));

	$form_container->output_row($lang->enabled." <em>*</em>", "", $form->generate_yes_no_radio("enabled", $mybb->input['enabled'], true));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_task);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("tasks", "*", "tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'");
	$task = $db->fetch_array($query);

	// Does the task not exist?
	if(!$task['tid'])
	{
		flash_message($lang->error_invalid_task, 'error');
		admin_redirect("index.php?module=tools-tasks");
	}

	$plugins->run_hooks("admin_tools_tasks_edit");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = $lang->error_missing_description;
		}

		if(!file_exists(MYBB_ROOT."inc/tasks/".$mybb->input['file'].".php"))
		{
			$errors[] = $lang->error_invalid_task_file;
		}

		$mybb->input['minute'] = check_time_values($mybb->input['minute'], 0, 59, 'string');
		if($mybb->input['minute'] === false)
		{
			$errors[] = $lang->error_invalid_minute;
		}

		$mybb->input['hour'] = check_time_values($mybb->input['hour'], 0, 59, 'string');
		if($mybb->input['hour'] === false)
		{
			$errors[] = $lang->error_invalid_hour;
		}

		if($mybb->input['day'] != "*" && $mybb->input['day'] != '')
		{
			$mybb->input['day'] = check_time_values($mybb->input['day'], 1, 31, 'string');
			if($mybb->input['day'] === false)
			{
				$errors[] = $lang->error_invalid_day;
			}
			$mybb->input['weekday'] = array('*');
		}
		else
		{
			$mybb->input['weekday'] = check_time_values($mybb->input['weekday'], 0, 6, 'array');
			if($mybb->input['weekday'] === false)
			{
				$errors[] = $lang->error_invalid_weekday;
			}
			$mybb->input['day'] = '*';
		}

		$mybb->input['month'] = check_time_values($mybb->input['month'], 1, 12, 'array');
		if($mybb->input['month'] === false)
		{
			$errors[] = $lang->error_invalid_month;
		}

		if(!$errors)
		{
			$enable_confirmation = false;
			// Check if we need to ask the user to confirm turning on the task
			if(($task['file'] == "backupdb" || $task['file'] == "checktables") && $task['enabled'] == 0 && $mybb->input['enabled'] == 1)
			{
				$mybb->input['enabled'] = 0;
				$enable_confirmation = true;
			}

			$updated_task = array(
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"file" => $db->escape_string($mybb->input['file']),
				"minute" => $db->escape_string($mybb->input['minute']),
				"hour" => $db->escape_string($mybb->input['hour']),
				"day" => $db->escape_string($mybb->input['day']),
				"month" => $db->escape_string(implode(',', $mybb->input['month'])),
				"weekday" => $db->escape_string(implode(',', $mybb->input['weekday'])),
				"enabled" => $mybb->get_input('enabled', MyBB::INPUT_INT),
				"logging" => $mybb->get_input('logging', MyBB::INPUT_INT)
			);

			$updated_task['nextrun'] = fetch_next_run($updated_task);

			$plugins->run_hooks("admin_tools_tasks_edit_commit");

			$db->update_query("tasks", $updated_task, "tid='{$task['tid']}'");

			$cache->update_tasks();

			// Log admin action
			log_admin_action($task['tid'], htmlspecialchars_uni($mybb->input['title']));

			flash_message($lang->success_task_updated, 'success');

			if($enable_confirmation == true)
			{
				admin_redirect("index.php?module=tools-tasks&amp;action=enable&amp;tid={$task['tid']}&amp;my_post_key={$mybb->post_code}");
			}
			else
			{
				admin_redirect("index.php?module=tools-tasks");
			}
		}
	}

	$page->add_breadcrumb_item($lang->edit_task);
	$page->output_header($lang->scheduled_tasks." - ".$lang->edit_task);

	$sub_tabs['edit_task'] = array(
		'title' => $lang->edit_task,
		'description' => $lang->edit_task_desc,
		'link' => "index.php?module=tools-tasks&amp;action=edit&amp;tid={$task['tid']}"
	);

	$page->output_nav_tabs($sub_tabs, 'edit_task');

	$form = new Form("index.php?module=tools-tasks&amp;action=edit", "post");

	if($errors)
	{
		$page->output_inline_error($errors);
		$task_data = $mybb->input;
	}
	else
	{
		$task_data = $task;
		$task_data['weekday'] = explode(',', $task['weekday']);
		$task_data['month'] = explode(',', $task['month']);
	}

	$form_container = new FormContainer($lang->edit_task);
	echo $form->generate_hidden_field("tid", $task['tid']);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $task_data['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $task_data['description'], array('id' => 'description')), 'description');

	$task_list = array();
	$task_files = scandir(MYBB_ROOT."inc/tasks/");
	foreach($task_files as $task_file)
	{
		if(is_file(MYBB_ROOT."inc/tasks/{$task_file}") && get_extension($task_file) == "php")
		{
			$file_id = preg_replace("#\.".get_extension($task_file)."$#i", "$1", $task_file);
			$task_list[$file_id] = $task_file;
		}
	}
	$form_container->output_row($lang->task." <em>*</em>", $lang->task_file_desc, $form->generate_select_box("file", $task_list, $task_data['file'], array('id' => 'file')), 'file');
	$form_container->output_row($lang->time_minutes, $lang->time_minutes_desc, $form->generate_text_box('minute', $task_data['minute'], array('id' => 'minute')), 'minute');
	$form_container->output_row($lang->time_hours, $lang->time_hours_desc, $form->generate_text_box('hour', $task_data['hour'], array('id' => 'hour')), 'hour');
	$form_container->output_row($lang->time_days_of_month, $lang->time_days_of_month_desc, $form->generate_text_box('day', $task_data['day'], array('id' => 'day')), 'day');

	$options = array(
		"*" => $lang->every_weekday,
		"0" => $lang->sunday,
		"1" => $lang->monday,
		"2" => $lang->tuesday,
		"3" => $lang->wednesday,
		"4" => $lang->thursday,
		"5" => $lang->friday,
		"6" => $lang->saturday
	);
	$form_container->output_row($lang->time_weekdays, $lang->time_weekdays_desc, $form->generate_select_box('weekday[]', $options, $task_data['weekday'], array('id' => 'weekday', 'multiple' => true)), 'weekday');

	$options = array(
		"*" => $lang->every_month,
		"1" => $lang->january,
		"2" => $lang->february,
		"3" => $lang->march,
		"4" => $lang->april,
		"5" => $lang->may,
		"6" => $lang->june,
		"7" => $lang->july,
		"8" => $lang->august,
		"9" => $lang->september,
		"10" => $lang->october,
		"11" => $lang->november,
		"12" => $lang->december
	);
	$form_container->output_row($lang->time_months, $lang->time_months_desc, $form->generate_select_box('month[]', $options, $task_data['month'], array('id' => 'month', 'multiple' => true)), 'month');

	$form_container->output_row($lang->enable_logging." <em>*</em>", "", $form->generate_yes_no_radio("logging", $task_data['logging'], true));

	$form_container->output_row($lang->enabled." <em>*</em>", "", $form->generate_yes_no_radio("enabled", $task_data['enabled'], true));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_task);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("tasks", "*", "tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'");
	$task = $db->fetch_array($query);

	// Does the task not exist?
	if(!$task['tid'])
	{
		flash_message($lang->error_invalid_task, 'error');
		admin_redirect("index.php?module=tools-tasks");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=tools-tasks");
	}

	$plugins->run_hooks("admin_tools_tasks_delete");

	if($mybb->request_method == "post")
	{
		// Delete the task & any associated task log entries
		$db->delete_query("tasks", "tid='{$task['tid']}'");
		$db->delete_query("tasklog", "tid='{$task['tid']}'");

		// Fetch next task run

		$plugins->run_hooks("admin_tools_tasks_delete_commit");

		$cache->update_tasks();

		// Log admin action
		log_admin_action($task['tid'], htmlspecialchars_uni($task['title']));

		flash_message($lang->success_task_deleted, 'success');
		admin_redirect("index.php?module=tools-tasks");
	}
	else
	{
		$page->output_confirm_action("index.php?module=tools-tasks&amp;action=delete&amp;tid={$task['tid']}", $lang->confirm_task_deletion);
	}
}

if($mybb->input['action'] == "enable" || $mybb->input['action'] == "disable")
{
	if(!verify_post_check($mybb->input['my_post_key']))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=tools-tasks");
	}

	$query = $db->simple_select("tasks", "*", "tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'");
	$task = $db->fetch_array($query);

	// Does the task not exist?
	if(!$task['tid'])
	{
		flash_message($lang->error_invalid_task, 'error');
		admin_redirect("index.php?module=tools-tasks");
	}

	if($mybb->input['action'] == "enable")
	{
		$plugins->run_hooks("admin_tools_tasks_enable");
	}
	else
	{
		$plugins->run_hooks("admin_tools_tasks_disable");
	}

	if($mybb->input['action'] == "enable")
	{
		if($task['file'] == "backupdb" || $task['file'] == "checktables")
		{
			// User clicked no
			if($mybb->input['no'])
			{
				admin_redirect("index.php?module=tools-tasks");
			}

			if($mybb->request_method == "post")
			{
				$nextrun = fetch_next_run($task);
				$db->update_query("tasks", array("nextrun" => $nextrun, "enabled" => 1), "tid='{$task['tid']}'");

				$plugins->run_hooks("admin_tools_tasks_enable_commit");

				$cache->update_tasks();

				// Log admin action
				log_admin_action($task['tid'], htmlspecialchars_uni($task['title']), $mybb->input['action']);

				flash_message($lang->success_task_enabled, 'success');
				admin_redirect("index.php?module=tools-tasks");
			}
			else
			{
				$page->output_confirm_action("index.php?module=tools-tasks&amp;action=enable&amp;tid={$task['tid']}", $lang->confirm_task_enable);
			}
		}
		else
		{
			$nextrun = fetch_next_run($task);
			$db->update_query("tasks", array("nextrun" => $nextrun, "enabled" => 1), "tid='{$task['tid']}'");

			$plugins->run_hooks("admin_tools_tasks_enable_commit");

			$cache->update_tasks();

			// Log admin action
			log_admin_action($task['tid'], htmlspecialchars_uni($task['title']), $mybb->input['action']);

			flash_message($lang->success_task_enabled, 'success');
			admin_redirect("index.php?module=tools-tasks");
		}
	}
	else
	{
		$db->update_query("tasks", array("enabled" => 0), "tid='{$task['tid']}'");

		$plugins->run_hooks("admin_tools_tasks_disable_commit");

		$cache->update_tasks();

		// Log admin action
		log_admin_action($task['tid'], htmlspecialchars_uni($task['title']), htmlspecialchars_uni($mybb->input['action']));

		flash_message($lang->success_task_disabled, 'success');
		admin_redirect("index.php?module=tools-tasks");
	}
}

if($mybb->input['action'] == "run")
{
	if(!verify_post_check($mybb->input['my_post_key']))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=tools-tasks");
	}

	ignore_user_abort(true);
	@set_time_limit(0);

	$plugins->run_hooks("admin_tools_tasks_run");

	$query = $db->simple_select("tasks", "*", "tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'");
	$task = $db->fetch_array($query);

	// Does the task not exist?
	if(!$task['tid'])
	{
		flash_message($lang->error_invalid_task, 'error');
		admin_redirect("index.php?module=tools-tasks");
	}

	run_task($task['tid']);

	$plugins->run_hooks("admin_tools_tasks_run_commit");

	// Log admin action
	log_admin_action($task['tid'], htmlspecialchars_uni($task['title']));

	flash_message($lang->success_task_run, 'success');
	admin_redirect("index.php?module=tools-tasks");
}

if($mybb->input['action'] == "logs")
{
	$plugins->run_hooks("admin_tools_tasks_logs");

	$page->output_header($lang->task_logs);

	$sub_tabs['scheduled_tasks'] = array(
		'title' => $lang->scheduled_tasks,
		'link' => "index.php?module=tools-tasks"
	);

	$sub_tabs['add_task'] = array(
		'title' => $lang->add_new_task,
		'link' => "index.php?module=tools-tasks&amp;action=add"
	);

	$sub_tabs['task_logs'] = array(
		'title' => $lang->view_task_logs,
		'link' => "index.php?module=tools-tasks&amp;action=logs",
		'description' => $lang->view_task_logs_desc
	);

	$page->output_nav_tabs($sub_tabs, 'task_logs');

	$table = new Table;
	$table->construct_header($lang->task);
	$table->construct_header($lang->date, array("class" => "align_center", "width" => 200));
	$table->construct_header($lang->data, array("width" => "60%"));

	$query = $db->simple_select("tasklog", "COUNT(*) AS log_count");
	$log_count = $db->fetch_field($query, "log_count");

	$start = 0;
	$per_page = 50;
	$current_page = 1;

	if($mybb->input['page'] > 0)
	{
		$current_page = $mybb->get_input('page', MyBB::INPUT_INT);
		$start = ($current_page-1)*$per_page;
		$pages = $log_count / $per_page;
		$pages = ceil($pages);
		if($current_page > $pages)
		{
			$start = 0;
			$current_page = 1;
		}
	}

	$pagination = draw_admin_pagination($current_page, $per_page, $log_count, "index.php?module=tools-tasks&amp;action=logs&amp;page={page}");

	$query = $db->query("
		SELECT l.*, t.title
		FROM ".TABLE_PREFIX."tasklog l
		LEFT JOIN ".TABLE_PREFIX."tasks t ON (t.tid=l.tid)
		ORDER BY l.dateline DESC
		LIMIT {$start}, {$per_page}
	");
	while($log_entry = $db->fetch_array($query))
	{
		$log_entry['title'] = htmlspecialchars_uni($log_entry['title']);
		$log_entry['data'] = htmlspecialchars_uni($log_entry['data']);

		$date = my_date('relative', $log_entry['dateline']);
		$table->construct_cell("<a href=\"index.php?module=tools-tasks&amp;action=edit&amp;tid={$log_entry['tid']}\">{$log_entry['title']}</a>");
		$table->construct_cell($date, array("class" => "align_center"));
		$table->construct_cell($log_entry['data']);
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_task_logs, array("colspan" => "3"));
		$table->construct_row();
	}

	$table->output($lang->task_logs);
	echo $pagination;

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->task_manager);

	$sub_tabs['scheduled_tasks'] = array(
		'title' => $lang->scheduled_tasks,
		'link' => "index.php?module=tools-tasks",
		'description' => $lang->scheduled_tasks_desc
	);

	$sub_tabs['add_task'] = array(
		'title' => $lang->add_new_task,
		'link' => "index.php?module=tools-tasks&amp;action=add"
	);

	$sub_tabs['task_logs'] = array(
		'title' => $lang->view_task_logs,
		'link' => "index.php?module=tools-tasks&amp;action=logs"
	);

	$plugins->run_hooks("admin_tools_tasks_start");

	$page->output_nav_tabs($sub_tabs, 'scheduled_tasks');

	$table = new Table;
	$table->construct_header($lang->task);
	$table->construct_header($lang->next_run, array("class" => "align_center", "width" => 200));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

	$query = $db->simple_select("tasks", "*", "", array("order_by" => "title", "order_dir" => "asc"));
	while($task = $db->fetch_array($query))
	{
		$task['title'] = htmlspecialchars_uni($task['title']);
		$task['description'] = htmlspecialchars_uni($task['description']);
		$next_run = date($mybb->settings['dateformat'], $task['nextrun']).", ".date($mybb->settings['timeformat'], $task['nextrun']);
		if($task['enabled'] == 1)
		{
			$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_on.png\" alt=\"({$lang->alt_enabled})\" title=\"{$lang->alt_enabled}\"  style=\"vertical-align: middle;\" /> ";
		}
		else
		{
			$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_off.png\" alt=\"({$lang->alt_disabled})\" title=\"{$lang->alt_disabled}\"  style=\"vertical-align: middle;\" /> ";
		}
		$table->construct_cell("<div class=\"float_right\"><a href=\"index.php?module=tools-tasks&amp;action=run&amp;tid={$task['tid']}&amp;my_post_key={$mybb->post_code}\"><img src=\"styles/{$page->style}/images/icons/run_task.png\" title=\"{$lang->run_task_now}\" alt=\"{$lang->run_task}\" /></a></div><div>{$icon}<strong><a href=\"index.php?module=tools-tasks&amp;action=edit&amp;tid={$task['tid']}\">{$task['title']}</a></strong><br /><small>{$task['description']}</small></div>");
		$table->construct_cell($next_run, array("class" => "align_center"));

		$popup = new PopupMenu("task_{$task['tid']}", $lang->options);
		$popup->add_item($lang->edit_task, "index.php?module=tools-tasks&amp;action=edit&amp;tid={$task['tid']}");
		if($task['enabled'] == 1)
		{
			$popup->add_item($lang->run_task, "index.php?module=tools-tasks&amp;action=run&amp;tid={$task['tid']}&amp;my_post_key={$mybb->post_code}");
			$popup->add_item($lang->disable_task, "index.php?module=tools-tasks&amp;action=disable&amp;tid={$task['tid']}&amp;my_post_key={$mybb->post_code}");
		}
		else
		{
			$popup->add_item($lang->enable_task, "index.php?module=tools-tasks&amp;action=enable&amp;tid={$task['tid']}&amp;my_post_key={$mybb->post_code}");
		}
		$popup->add_item($lang->delete_task, "index.php?module=tools-tasks&amp;action=delete&amp;tid={$task['tid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_task_deletion}')");
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_tasks, array('colspan' => 3));
		$table->construct_row();
	}

	$table->output($lang->scheduled_tasks);

	$page->output_footer();
}
