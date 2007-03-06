<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/license.php
 *
 * $Id$
 */

// TODO: (Chris)
// -- Task logs

require_once MYBB_ROOT."/inc/functions_task.php";

$page->add_breadcrumb_item("Task Manager", "index.php?".SID."&amp;module=tools/tasks");

function check_time_values($value, $min, $max)
{
	if(!is_array($value))
	{
		if($value === '')
		{
			return '*';
		}
		$implode = 1;
		$value = explode(",", $value);
	}
	if(in_array('*', $value))
	{
		return '*';
	}
	foreach($value as $time)
	{
		if($time < $min || $time > $max)
		{
			return false;
		}
	}
	if($implode == 1)
	{
		$value = implode(",", $value);
	}
	return $value;
}

if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this scheduled task";
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = "You did not enter a description for this scheduled task";
		}

		if(!file_exists(MYBB_ROOT."inc/tasks/".$mybb->input['file'].".php"))
		{
			$errors[] = "The task file you selected does not exist.";
		}

		$mybb->input['minute'] = check_time_values($mybb->input['minute'], 0, 59);
		if($mybb->input['minute'] === false)
		{
			$errors[] = "The value you've entered for the run minute is invalid";
		}

		$mybb->input['hour'] = check_time_values($mybb->input['hour'], 0, 59);
		if($mybb->input['hour'] === false)
		{
			$errors[] = "The value you've entered for the run hour is invalid";
		}

		if($mybb->input['day'] != "*" && $mybb->input['day'] != '')
		{
			$mybb->input['day'] = check_time_values($mybb->input['day'], 1, 31);
			if($mybb->input['day'] === false)
			{
				$errors[] = "The value you've entered for the run day is invalid";
			}
			$mybb->input['weekday'] = array('*');
		}
		else
		{
			$mybb->input['weekday'] = check_time_values($mybb->input['weekday'], 0, 6);
			if($mybb->input['weekday'] === false)
			{
				$errors[] = "The value you've selected for the run weekday is invalid";
			}
			$mybb->input['day'] = '*';
		}

		$mybb->input['month'] = check_time_values($mybb->input['month'], 1, 12);
		if($mybb->input['month'] === false)
		{
			$errors[] = "The value you've entered for the run month is invalid";
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
				"month" => $db->escape_string(implode(",", $mybb->input['month'])),
				"weekday" => $db->escape_string(implode(",", $mybb->input['weekday'])),
				"enabled" => intval($mybb->input['enabled']),
				"logging" => intval($mybb->input['logging'])
			);

			$new_task['nextrun'] = fetch_next_run($new_task);
			$db->insert_query("tasks", $new_task);
			$cache->update_tasks();
			flash_message('The task has successfully been created.', 'success');
			admin_redirect("index.php?".SID."&module=tools/tasks");
		}
	}
	$page->add_breadcrumb_item("Add New Task");
	$page->output_header("Scheduled Tasks - Add New Task");

	$sub_tabs['add_task'] = array(
		'title' => "Add New Task",
		'link' => "index.php?".SID."&amp;module=tools/tasks&amp;action=add",
		'description' => "Here you can create new scheduled tasks which are automatically run on your board."
	);

	$page->output_nav_tabs($sub_tabs, 'add_task');
	$form = new Form("index.php?".SID."&amp;module=tools/tasks&amp;action=add", "post", "add");
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
	$form_container = new FormContainer("Add New Task");
	$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Short Description <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');

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
	$form_container->output_row("Task File <em>*</em>", "Select the task file you wish this task to run.", $form->generate_select_box("file", $task_list, $mybb->input['file'], array('id' => 'file')), 'file');
	$form_container->output_row("Time: Minutes", "Enter a comma separated list of minutes (0-59) for which this task should run on. Enter '*' if this task should run on every minute.", $form->generate_text_box('minute', $mybb->input['minute'], array('id' => 'minute')), 'minute');
	$form_container->output_row("Time: Hours", "Enter a comma separated list of hours (0-23) for which this task should run on. Enter '*' if this task should run on every hour.", $form->generate_text_box('hour', $mybb->input['hour'], array('id' => 'hour')), 'hour');
	$form_container->output_row("Time: Days of Month", "Enter a comma separated list of days (1-31) for which this task should run on. Enter '*' if this task should run on every day or you wish to specify a weekday below.", $form->generate_text_box('day', $mybb->input['day'], array('id' => 'day')), 'day');

	$options = array(
		"*" => "Every Weekday",
		"0" => "Sunday",
		"1" => "Monday",
		"2" => "Tuesday",
		"3" => "Wednesday",
		"4" => "Thursday",
		"5" => "Friday",
		"6" => "Saturday"
	);
	$form_container->output_row("Time: Weekdays", "Select which weekdays this task should run on. Holding down CTRL selects multiple weekdays. Select 'Every weekday' if you want this task to run each weekday or you have entered a predefined day above.", $form->generate_select_box('weekday', $options, $mybb->input['weekday'], array('id' => 'weekday', 'multiple' => true)), 'weekday');

	$options = array(
		"*" => "Every Month",
		"1" => "January",
		"2" => "February",
		"3" => "March",
		"4" => "April",
		"5" => "May",
		"6" => "June",
		"7" => "July",
		"8" => "August",
		"9" => "September",
		"10" => "October",
		"11" => "November",
		"12" => "December"
	);
	$form_container->output_row("Time: Months", "Select which months this task should run on. Holding down CTRL selects multiple months. Select 'Every month' if you want this task to run each month.", $form->generate_select_box('month', $options, $mybb->input['month'], array('id' => 'month', 'multiple' => true)), 'month');

	$form_container->output_row("Enabled? <em>*</em>", "", $form->generate_yes_no_radio("enabled", $mybb->input['enabled'], true));

	$form_container->output_row("Enable Logging? <em>*</em>", "", $form->generate_yes_no_radio("logging", $mybb->input['logging'], true));
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save New Task");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("tasks", "*", "tid='".intval($mybb->input['tid'])."'");
	$task = $db->fetch_array($query);

	// Does the task not exist?
	if(!$task['tid'])
	{
		flash_message('The specified task does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=tools/tasks");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this scheduled task";
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = "You did not enter a description for this scheduled task";
		}

		if(!file_exists(MYBB_ROOT."inc/tasks/".$mybb->input['file'].".php"))
		{
			$errors[] = "The task file you selected does not exist.";
		}

		$mybb->input['minute'] = check_time_values($mybb->input['minute'], 0, 59);
		if($mybb->input['minute'] === false)
		{
			$errors[] = "The value you've entered for the run minute is invalid";
		}

		$mybb->input['hour'] = check_time_values($mybb->input['hour'], 0, 59);
		if($mybb->input['hour'] === false)
		{
			$errors[] = "The value you've entered for the run hour is invalid";
		}

		if($mybb->input['day'] != "*" && $mybb->input['day'] != '')
		{
			$mybb->input['day'] = check_time_values($mybb->input['day'], 1, 31);
			if($mybb->input['day'] === false)
			{
				$errors[] = "The value you've entered for the run day is invalid";
			}
			$mybb->input['weekday'] = array('*');
		}
		else
		{
			$mybb->input['weekday'] = check_time_values($mybb->input['weekday'], 1, 31);
			if($mybb->input['weekday'] === false)
			{
				$errors[] = "The value you've selected for the run weekday is invalid";
			}
			$mybb->input['day'] = '*';
		}

		$mybb->input['month'] = check_time_values($mybb->input['month'], 1, 12);
		if($mybb->input['month'] === false)
		{
			$errors[] = "The value you've entered for the run month is invalid";
		}
		if(!$errors)
		{
			$updated_task = array(
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"file" => $db->escape_string($mybb->input['file']),
				"minute" => $db->escape_string($mybb->input['minute']),
				"hour" => $db->escape_string($mybb->input['hour']),
				"day" => $db->escape_string($mybb->input['day']),
				"month" => $db->escape_string($mybb->input['month']),
				"weekday" => $db->escape_string($mybb->input['weekday']),
				"enabled" => intval($mybb->input['enabled']),
				"logging" => intval($mybb->input['logging'])
			);

			$updated_task['nextrun'] = fetch_next_run($updated_task);
			$db->update_query("tasks", $updated_task, "tid='{$task['tid']}'");
			$cache->update_tasks();
			flash_message('The task has successfully been updated.', 'success');
			admin_redirect("index.php?".SID."&module=tools/tasks");
		}
	}

	$page->add_breadcrumb_item("Edit Task");
	$page->output_header("Scheduled Tasks - Edit Task");
	
	$sub_tabs['edit_task'] = array(
		'title' => "Edit Task",
		'description' => "Below you can edit the various settings for this scheduled task."
	);

	$page->output_nav_tabs($sub_tabs, 'edit_task');

	$form = new Form("index.php?".SID."&amp;module=tools/tasks&amp;action=edit", "post");

	if($errors)
	{
		$page->output_inline_error($errors);
		$task_data = $mybb->input;
	}
	else
	{
		$task_data = array(
			'title' => $task['title'],
			'description' => $task['description'],
			'minute' => $task['minute'],
			'hour' => $task['hour'],
			'day' => $task['day'],
			'weekday' => explode(",", $task['weekday']),
			'month' => explode(",", $task['month']),
			'enabled' => $task['enabled'],
			'logging' => $task['logging']
		);
	}

	$form_container = new FormContainer("Edit Task");
	echo $form->generate_hidden_field("tid", $task['tid']);
	$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('title', $task_data['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Short Description", "", $form->generate_text_box('description', $task_data['description'], array('id' => 'description')), 'description');

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
	$form_container->output_row("Task File <em>*</em>", "Select the task file you wish this task to run.", $form->generate_select_box("file", $task_list, $task_data['file'], array('id' => 'file')), 'file');
	$form_container->output_row("Time: Minutes", "Enter a comma separated list of minutes (0-59) for which this task should run on. Enter '*' if this task should run on every minute.", $form->generate_text_box('minute', $task_data['minute'], array('id' => 'minute')), 'minute');
	$form_container->output_row("Time: Hours", "Enter a comma separated list of hours (0-23) for which this task should run on. Enter '*' if this task should run on every hour.", $form->generate_text_box('hour', $task_data['hour'], array('id' => 'hour')), 'hour');
	$form_container->output_row("Time: Days of Month", "Enter a comma separated list of days (1-31) for which this task should run on. Enter '*' if this task should run on every day or you wish to specify a weekday below.", $form->generate_text_box('day', $task_data['day'], array('id' => 'day')), 'day');

	$options = array(
		"*" => "Every Weekday",
		"0" => "Sunday",
		"1" => "Monday",
		"2" => "Tuesday",
		"3" => "Wednesday",
		"4" => "Thursday",
		"5" => "Friday",
		"6" => "Saturday"
	);
	$form_container->output_row("Time: Weekdays", "Select which weekdays this task should run on. Holding down CTRL selects multiple weekdays. Select 'Every weekday' if you want this task to run each weekday or you have entered a predefined day above.", $form->generate_select_box('weekday', $options, $task_data['weekday'], array('id' => 'weekday', 'multiple' => true)), 'weekday');

	$options = array(
		"*" => "Every Month",
		"1" => "January",
		"2" => "February",
		"3" => "March",
		"4" => "April",
		"5" => "May",
		"6" => "June",
		"7" => "July",
		"8" => "August",
		"9" => "September",
		"10" => "October",
		"11" => "November",
		"12" => "December"
	);
	$form_container->output_row("Time: Months", "Select which months this task should run on. Holding down CTRL selects multiple months. Select 'Every month' if you want this task to run each month.", $form->generate_select_box('month', $options, $task_data['month'], array('id' => 'month', 'multiple' => true)), 'month');

	$form_container->output_row("Enabled? <em>*</em>", "", $form->generate_yes_no_radio("enabled", $task_data['enabled']));

	$form_container->output_row("Enable Logging? <em>*</em>", "", $form->generate_yes_no_radio("logging", $task_data['logging']));
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Save Task");

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("tasks", "*", "tid='".intval($mybb->input['tid'])."'");
	$task = $db->fetch_array($query);

	// Does the task not exist?
	if(!$task['tid'])
	{
		flash_message('The specified task does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=tools/tasks");
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=tools/tasks");
	}

	if($mybb->request_method == "post")
	{
		// Delete the task & any associated task log entries
		$db->delete_query("tasks", "tid='{$task['tid']}'");
		$db->delete_query("tasklog", "tid='{$task['tid']}'");

		// Fetch next task run
		$cache->update_tasks();

		flash_message('The specified task has been deleted.', 'error');
		admin_redirect("index.php?".SID."&module=tools/tasks");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=tools/tasks&amp;action=delete&amp;tid={$task['tid']}", "Are you sure you wish to delete this scheduled task?");
	}
}

if($mybb->input['action'] == "enable" || $mybb->input['action'] == "disable")
{
	$query = $db->simple_select("tasks", "*", "tid='".intval($mybb->input['tid'])."'");
	$task = $db->fetch_array($query);

	// Does the task not exist?
	if(!$task['tid'])
	{
		flash_message('The specified task does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=tools/tasks");
	}

	if($mybb->input['action'] == "enable")
	{
		$nextrun = fetch_next_run($task);
		$db->update_query("tasks", array("nextrun" => $nextrun, "enabled" => 1), "tid='{$task['tid']}'");
		$cache->update_tasks();
		flash_message('The speicified task has now been enabled.', 'success');
		admin_redirect("index.php?".SID."&module=tools/tasks");
	}
	else
	{
		$db->update_query("tasks", array("enabled" => 0), "tid='{$task['tid']}'");
		$cache->update_tasks();
		flash_message('The speicified task has now been disabled.', 'success');
		admin_redirect("index.php?".SID."&module=tools/tasks");
	}
}

if($mybb->input['action'] == "run")
{
	$query = $db->simple_select("tasks", "*", "tid='".intval($mybb->input['tid'])."'");
	$task = $db->fetch_array($query);

	// Does the task not exist?
	if(!$task['tid'])
	{
		flash_message('The specified task does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=tools/tasks");
	}

	run_task($task['tid']);

	flash_message('The speicified task has been run.', 'success');
	admin_redirect("index.php?".SID."&module=tools/tasks");
}

if($mybb->input['action'] == "logs")
{
	$page->output_header("Task Logs");

	$sub_tabs['scheduled_tasks'] = array(
	'title' => "Scheduled Tasks",
	'link' => "index.php?".SID."&amp;module=tools/tasks"
);

	$sub_tabs['add_task'] = array(
		'title' => "Add New Task",
		'link' => "index.php?".SID."&amp;module=tools/tasks&amp;action=add"
	);

	$sub_tabs['task_logs'] = array(
		'title' => "View Task Logs",
		'link' => "index.php?".SID."&amp;module=tools/tasks&amp;action=logs",
		'description' => "When a task is run and logging is enabled, any results or errors will be listed below. Entries older than 30 days are automatically deleted."
	);

	$page->output_nav_tabs($sub_tabs, 'task_logs');

	$table = new Table;
	$table->construct_header("Task");
	$table->construct_header("Date", array("class" => "align_center", "width" => 200));
	$table->construct_header("Data", array("width" => "60%"));

	$query = $db->simple_select("tasklog", "COUNT(*) AS log_count");
	$log_count = $db->fetch_field($query, "log_count");

	$per_page = 20;

	if($mybb->input['page'] > 0)
	{
		$current_page = intval($mybb->input['page']);
		$start = ($current_page-1)*$per_page;
		$pages = $log_count / $per_page;
		$pages = ceil($pages);
		if($current_page > $pages)
		{
			$start = 0;
			$current_page = 1;
		}
	}
	else
	{
		$start = 0;
		$current_page = 1;
	}

	$pagination = draw_admin_pagination($current_page, $per_page, $log_count, "index.php?".SID."&amp;module=tools/tasks&amp;action=logs&amp;page={page}");

	$query = $db->query("
		SELECT l.*, t.title
		FROM ".TABLE_PREFIX."tasklog l
		LEFT JOIN ".TABLE_PREFIX."tasks t ON (t.tid=l.lid)
		ORDER BY l.dateline DESC
		LIMIT {$start}, {$per_page}
	");
	while($log_entry = $db->fetch_array($query))
	{
		$log_entry['title'] = htmlspecialchars_uni($log_entry['title']);
		$log_entry['data'] = htmlspecialchars_uni($log_entry['data']);
		$date = my_date($mybb->settings['dateformat'], $log_entry['dateline']).", ".my_date($mybb->settings['timeformat'], $log_entry['dateline']);
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=tools/tasks&amp;action=edit&amp;tid={$log_entry['tid']}\">{$log_entry['title']}</a>");
		$table->construct_cell($date, array("class" => "align_center"));
		$table->construct_cell($log_entry['data']);
		$table->construct_row();
	}

	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are currently no log entries for any of the scheduled tasks.", array("colspan" => "3"));
		$table->construct_row();
	}
	$table->output("Task Log");
	echo $pagination;

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header("Task Manager");

	$sub_tabs['scheduled_tasks'] = array(
		'title' => "Scheduled Tasks",
		'link' => "index.php?".SID."&amp;module=tools/tasks",
		'description' => "Here you can manage tasks which are automatically run on your board. To run a task now click the icon to the right of the task."
	);

	$sub_tabs['add_task'] = array(
		'title' => "Add New Task",
		'link' => "index.php?".SID."&amp;module=tools/tasks&amp;action=add"
	);

	$sub_tabs['task_logs'] = array(
		'title' => "View Task Logs",
		'link' => "index.php?".SID."&amp;module=tools/tasks&amp;action=logs"
	);

	$page->output_nav_tabs($sub_tabs, 'scheduled_tasks');

	$table = new Table;
	$table->construct_header("Task");
	$table->construct_header("Next Run", array("class" => "align_center", "width" => 200));
	$table->construct_header("Controls", array("class" => "align_center", "width" => 150));

	$query = $db->simple_select("tasks", "*", "", array("order_by" => "title", "order_dir" => "asc"));
	while($task = $db->fetch_array($query))
	{
		$task['title'] = htmlspecialchars_uni($task['title']);
		$task['description'] = htmlspecialchars_uni($task['description']);
		$next_run = date($mybb->settings['dateformat'], $task['nextrun']).", ".date($mybb->settings['timeformat'], $task['nextrun']);
		$table->construct_cell("<div class=\"float_right\"><a href=\"index.php?".SID."&amp;module=tools/tasks&amp;action=run&amp;tid={$task['tid']}\"><img src=\"styles/{$page->style}/images/icons/run_task.gif\" title=\"Run this task now\" alt=\"Run task\" /></a></div><div><strong><a href=\"index.php?".SID."&amp;module=tools/tasks&amp;action=edit&amp;tid={$task['tid']}\">{$task['title']}</a></strong><br /><small>{$task['description']}</small></div>");
		$table->construct_cell($next_run, array("class" => "align_center"));

		$popup = new PopupMenu("task_{$task['tid']}", "Options");
		$popup->add_item("Edit Task", "index.php?".SID."&amp;module=tools/tasks&amp;action=edit&amp;tid={$task['tid']}");
		if($task['enabled'] == 1)
		{
			$popup->add_item("Disable Task", "index.php?".SID."&amp;module=tools/tasks&amp;action=disable&amp;tid={$task['tid']}");
		}
		else
		{
			$popup->add_item("Enable Task", "index.php?".SID."&amp;module=tools/tasks&amp;action=enable&amp;tid={$task['tid']}");
		}
		$popup->add_item("Delete Task", "index.php?".SID."&amp;module=tools/tasks&amp;action=delete&amp;tid={$task['tid']}", "return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this scheduled task?')");
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();
	}
	$table->output("Scheduled Tasks");

	$page->output_footer();
}
?>
