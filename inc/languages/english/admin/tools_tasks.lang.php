<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */

$l['task_manager'] = "Task Manager";
$l['add_new_task'] = "Add New Task";
$l['add_new_task_desc'] = "Here you can create new scheduled tasks which are automatically run on your board.";
$l['edit_task'] = "Edit Task";
$l['edit_task_desc'] = "Below you can edit the various settings for this scheduled task.";
$l['task_logs'] = "Task Logs";
$l['view_task_logs'] = "View Task Logs";
$l['view_task_logs_desc'] = "When a task is run and logging is enabled, any results or errors will be listed below. Entries older than 30 days are automatically deleted.";
$l['scheduled_tasks'] = "Scheduled Tasks";
$l['scheduled_tasks_desc'] = "Here you can manage tasks which are automatically run on your board. To run a task now click the icon to the right of the task.";

$l['title'] = "Title";
$l['short_description'] = "Short Description";
$l['task_file'] = "Task File";
$l['task_file_desc'] = "Select the task file you wish this task to run.";
$l['time_minutes'] = "Time: Minutes";
$l['time_minutes_desc'] = "Enter a comma separated list of minutes (0-59) for which this task should run on. Enter '*' if this task should run on every minute.";
$l['time_hours'] = "Time: Hours";
$l['time_hours_desc'] = "Enter a comma separated list of hours (0-23) for which this task should run on. Enter '*' if this task should run on every hour.";
$l['time_days_of_month'] = "Time: Days of Month";
$l['time_days_of_month_desc'] = "Enter a comma separated list of days (1-31) for which this task should run on. Enter '*' if this task should run on every day or you wish to specify a weekday below.";
$l['every_weekday'] = "Every Weekday";
$l['sunday'] = "Sunday";
$l['monday'] = "Monday";
$l['tuesday'] = "Tuesday";
$l['wednesday'] = "Wednesday";
$l['thursday'] = "Thursday";
$l['friday'] = "Friday";
$l['saturday'] = "Saturday";
$l['time_weekdays'] = "Time: Weekdays";
$l['time_weekdays_desc'] = "Select which weekdays this task should run on. Holding down CTRL selects multiple weekdays. Select 'Every weekday' if you want this task to run each weekday or you have entered a predefined day above.";
$l['every_month'] = "Every Month";
$l['time_months'] = "Time: Months";
$l['time_months_desc'] = "Select which months this task should run on. Holding down CTRL selects multiple months. Select 'Every month' if you want this task to run each month.";
$l['enabled'] = "Task enabled?";
$l['enable_logging'] = "Enable Logging?";
$l['save_task'] = "Save Task";
$l['task'] = "Task";
$l['date'] = "Date";
$l['data'] = "Data";
$l['no_task_logs'] = "There are currently no log entries for any of the scheduled tasks.";
$l['next_run'] = "Next Run";
$l['run_task_now'] = "Run this task now";
$l['disable_task'] = "Disable Task";
$l['run_task'] = "Run Task";
$l['enable_task'] = "Enable Task";
$l['delete_task'] = "Delete Task";

$l['error_invalid_task'] = "The specified task does not exist.";
$l['error_missing_title'] = "You did not enter a title for this scheduled task";
$l['error_missing_description'] = "You did not enter a description for this scheduled task";
$l['error_invalid_task_file'] = "The task file you selected does not exist.";
$l['error_invalid_minute'] = "The minute you've entered is invalid.";
$l['error_invalid_hour'] = "The hour you've entered is invalid.";
$l['error_invalid_day'] = "The day you've entered is invalid.";
$l['error_invalid_weekday'] = "The weekday you've selected is invalid.";
$l['error_invalid_month'] = "The month you've selected is invalid.";

$l['success_task_created'] = "The task has been created successfully.";
$l['success_task_updated'] = "The selected task has been updated successfully.";
$l['success_task_deleted'] = "The selected task has been deleted successfully.";
$l['success_task_enabled'] = "The selected task has been enabled successfully.";
$l['success_task_disabled'] = "The selected task has been disabled successfully.";
$l['success_task_run'] = "The selected task has been run successfully.";

$l['confirm_task_deletion'] = "Are you sure you wish to delete this scheduled task?";
$l['confirm_task_enable'] = "<strong>WARNING:</strong> You are about to enable a task that is only meant to be run via cron (Please see the <a href=\"https://docs.mybb.com/1.8/administration/task-manager\" target=\"_blank\" rel=\"noopener\">MyBB Docs</a> for more information). Continue?";
$l['no_tasks'] = "There are no tasks on your forum at this time.";

