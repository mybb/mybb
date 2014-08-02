<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Execute a scheduled task.
 *
 * @param int The task ID. If none specified, the next task due to be ran is executed
 * @return boolean True if successful, false on failure
 */
function run_task($tid=0)
{
	global $db, $mybb, $cache, $plugins, $task, $lang;

	// Run a specific task
	if($tid > 0)
	{
		$query = $db->simple_select("tasks", "*", "tid='{$tid}'");
		$task = $db->fetch_array($query);
	}

	// Run the next task due to be run
	else
	{
		$query = $db->simple_select("tasks", "*", "enabled=1 AND nextrun<='".TIME_NOW."'", array("order_by" => "nextrun", "order_dir" => "asc", "limit" => 1));
		$task = $db->fetch_array($query);
	}

	// No task? Return
	if(!$task['tid'])
	{
		$cache->update_tasks();
		return false;
	}

	// Is this task still running and locked less than 5 minutes ago? Well don't run it now - clearly it isn't broken!
	if($task['locked'] != 0 && $task['locked'] > TIME_NOW-300)
	{
		$cache->update_tasks();
		return false;
	}
	// Lock it! It' mine, all mine!
	else
	{
		$db->update_query("tasks", array("locked" => TIME_NOW), "tid='{$task['tid']}'");
	}

	// The task file does not exist
	if(!file_exists(MYBB_ROOT."inc/tasks/{$task['file']}.php"))
	{
		if($task['logging'] == 1)
		{
			add_task_log($task, $lang->missing_task);
		}
		$cache->update_tasks();
		return false;
	}
	// Run the task
	else
	{
		// Update the nextrun time now, so if the task causes a fatal error, it doesn't get stuck first in the queue
		$nextrun = fetch_next_run($task);
		$db->update_query("tasks", array("nextrun" => $nextrun), "tid='{$task['tid']}'");

		include_once MYBB_ROOT."inc/tasks/{$task['file']}.php";
		$function = "task_{$task['file']}";
		if(function_exists($function))
		{
			$function($task);
		}
	}

	$updated_task = array(
		"lastrun" => TIME_NOW,
		"locked" => 0
	);
	$db->update_query("tasks", $updated_task, "tid='{$task['tid']}'");

	$cache->update_tasks();

	return true;
}

/**
 * Adds information to the scheduled task log.
 *
 * @param int The task array to create the log entry for
 * @param string The message to log
 */
function add_task_log($task, $message)
{
	global $db;

	if(!$task['logging'])
	{
		return;
	}

	$log_entry = array(
		"tid" => (int)$task['tid'],
		"dateline" => TIME_NOW,
		"data" => $db->escape_string($message)
	);
	$db->insert_query("tasklog", $log_entry);
}

/**
 * Generate the next run time for a particular task.
 *
 * @param array The task array as fetched from the database.
 * @return int The next run time as a UNIX timestamp
 */
function fetch_next_run($task)
{
	$time = TIME_NOW;
	$next_minute = $current_minute = date("i", $time);
	$next_hour = $current_hour = date("H", $time);
	$next_day = $current_day = date("d", $time);
	$next_weekday = $current_weekday = date("w", $time);
	$next_month = $current_month = date("m", $time);
	$next_year = $current_year = date("Y", $time);
	$reset_day = $reset_hour = $reset_month = $reset_year = 0;

	if($task['minute'] == "*")
	{
		++$next_minute;
		if($next_minute > 59)
		{
			$reset_hour = 1;
			$next_minute = 0;
		}
	}
	else
	{
		if(build_next_run_bit($task['minute'], $current_minute) != false)
		{
			$next_minute = build_next_run_bit($task['minute'], $current_minute);
		}
		else
		{
			$next_minute = fetch_first_run_time($task['minute']);
		}
		if($next_minute <= $current_minute)
		{
			$reset_hour = 1;
		}
	}

	if($reset_hour || !run_time_exists($task['hour'], $current_hour))
	{
		if($task['hour'] == "*")
		{
			++$next_hour;
			if($next_hour > 23)
			{
				$reset_day = 1;
				$next_hour = 0;
			}
		}
		else
		{
			if(build_next_run_bit($task['hour'], $current_hour) != false)
			{
				$next_hour = build_next_run_bit($task['hour'], $current_hour);
			}
			else
			{
				$next_hour = fetch_first_run_time($task['hour']);
				$reset_day = 1;
			}
			if($next_hour < $current_hour)
			{
				$reset_day = 1;
			}
		}
		$next_minute = fetch_first_run_time($task['minute']);
	}

	if($reset_day || ($task['weekday'] == "*" && !run_time_exists($task['day'], $current_day) || $task['day'] == "*" && !run_time_exists($task['weekday'], $current_weekday)))
	{
		if($task['weekday'] == "*")
		{
			if($task['day'] == "*")
			{
				++$next_day;
				if($next_day > date("t", $time))
				{
					$reset_month = 1;
					$next_day = 1;
				}
			}
			else
			{
				if(build_next_run_bit($task['day'], $current_day) != false)
				{
					$next_day = build_next_run_bit($task['day'], $current_day);
				}
				else
				{
					$next_day = fetch_first_run_time($task['day']);
					$reset_month = 1;
				}
				if($next_day < $current_day)
				{
					$reset_month = 1;
				}
			}
		}
		else
		{
			if(build_next_run_bit($task['weekday'], $current_weekday) != false)
			{
				$next_weekday = build_next_run_bit($task['weekday'], $current_weekday);
			}
			else
			{
				$next_weekday = fetch_first_run_time($task['weekday']);
			}
			$next_day = $current_day + ($next_weekday-$current_weekday);
			if($next_day <= $current_day)
			{
				$next_day += 7;
			}

			if($next_day > date("t", $time))
			{
				$reset_month = 1;
			}
		}
		$next_minute = fetch_first_run_time($task['minute']);
		$next_hour = fetch_first_run_time($task['hour']);
		if($next_day == $current_day && $next_hour < $current_hour)
		{
			$reset_month = 1;
		}
	}

	if($reset_month || !run_time_exists($task['month'], $current_month))
	{
		if($task['month'] == "*")
		{
			$next_month++;
			if($next_month > 12)
			{
				$reset_year = 1;
				$next_month = 1;
			}
		}
		else
		{
			if(build_next_run_bit($task['month'], $current_month) != false)
			{
				$next_month = build_next_run_bit($task['month'], $current_month);
			}
			else
			{
				$next_month = fetch_first_run_time($task['month']);
				$reset_year = 1;
			}
			if($next_month < $current_month)
			{
				$reset_year = 1;
			}
		}
		$next_minute = fetch_first_run_time($task['minute']);
		$next_hour = fetch_first_run_time($task['hour']);
		if($task['weekday'] == "*")
		{
			$next_day = fetch_first_run_time($task['day']);
			if($next_day == 0) $next_day = 1;
		}
		else
		{
			$next_weekday = fetch_first_run_time($task['weekday']);
			$new_weekday = date("w", mktime($next_hour, $next_minute, 0, $next_month, 1, $next_year));
			$next_day = 1 + ($next_weekday-$new_weekday);
			if($next_weekday < $new_weekday)
			{
				$next_day += 7;
			}
		}
		if($next_month == $current_month && $next_day == $current_day && $next_hour < $current_hour)
		{
			$reset_year = 1;
		}
	}

	if($reset_year)
	{
		$next_year++;
		$next_minute = fetch_first_run_time($task['minute']);
		$next_hour = fetch_first_run_time($task['hour']);
		$next_month = fetch_first_run_time($task['month']);
		if($next_month == 0) $next_month = 1;
		if($task['weekday'] == "*")
		{
			$next_day = fetch_first_run_time($task['day']);
			if($next_day == 0) $next_day = 1;
		}
		else
		{
			$next_weekday = fetch_first_run_time($task['weekday']);
			$new_weekday = date("w", mktime($next_hour, $next_minute, 0, $next_month, 1, $next_year));
			$next_day = 1 + ($next_weekday-$new_weekday);
			if($next_weekday < $new_weekday)
			{
				$next_day += 7;
			}
		}
	}
	return mktime($next_hour, $next_minute, 0, $next_month, $next_day, $next_year);
}

/**
 * Builds the next run time bit for a particular item (day, hour, month etc). Used by fetch_next_run().
 *
 * @param string A string containing the run timse for this particular item
 * @param int The current value (be it current day etc)
 * @return int The new or found value
 */
function build_next_run_bit($data, $bit)
{
	if($data == "*") return $bit;
	$data = explode(",", $data);
	foreach($data as $thing)
	{
		if($thing > $bit)
		{
			return $thing;
		}
	}
	return false;
}

/**
 * Fetches the fist run bit for a particular item (day, hour, month etc). Used by fetch_next_run().
 *
 * @param string A string containing the run times for this particular item
 * @return int The first run time
 */
function fetch_first_run_time($data)
{
	if($data == "*") return "0";
	$data = explode(",", $data);
	return $data[0];
}

/**
 * Checks if a specific run time exists for a particular item (day, hour, month etc). Used by fetch_next_run().
 *
 * @param string A string containing the run times for this particular item
 * @param int The bit we're checking for
 * @return boolean True if it exists, false if it does not
 */
function run_time_exists($data, $bit)
{
	if($data == "*") return true;
	$data = explode(",", $data);
	if(in_array($bit, $data))
	{
		return true;
	}
	return false;
}
