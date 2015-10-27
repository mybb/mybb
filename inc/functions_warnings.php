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
 * @param resource|PDOStatement|mysqli_result $query The query to be run. Needs to select the "action" column of the "warninglevels" table
 * @param array $max_expiration_times Return variable. The maximum expiration time
 * @param array $check_levels Return variable. Whether those "levels" were checked
 */
function find_warnlevels_to_check($query, &$max_expiration_times, &$check_levels)
{
	global $db;

	// we have some warning levels we need to revoke
	$max_expiration_times = array(
		1 => -1,	// Ban
		2 => -1,	// Revoke posting
		3 => -1		// Moderate posting
	);
	$check_levels = array(
		1 => false,	// Ban
		2 => false,	// Revoke posting
		3 => false	// Moderate posting
	);
	while($warn_level = $db->fetch_array($query))
	{
		// revoke actions taken at this warning level
		$action = my_unserialize($warn_level['action']);
		if($action['type'] < 1 || $action['type'] > 3)	// prevent any freak-ish cases
		{
			continue;
		}

		$check_levels[$action['type']] = true;

		$max_exp_time = &$max_expiration_times[$action['type']];
		if($action['length'] && $max_exp_time != 0)
		{
			$expiration = $action['length'];
			if($expiration > $max_exp_time)
			{
				$max_exp_time = $expiration;
			}
		}
		else
		{
			$max_exp_time = 0;
		}
	}
}

/**
 * Returns a friendly expiration time of a suspension/warning
 *
 * @param int $time The time period of the suspension/warning
 * @return array An array of the time/period remaining
 */
function fetch_friendly_expiration($time)
{
	if($time == 0 || $time == -1)
	{
		return array("period" => "never");
	}
	else if($time % 2592000 == 0)
	{
		return array("time" => $time/2592000, "period" => "months");
	}
	else if($time % 604800 == 0)
	{
		return array("time" => $time/604800, "period" => "weeks");
	}
	else if($time % 86400 == 0)
	{
		return array("time" => $time/86400, "period" => "days");
	}
	else
	{
		return array("time" => ceil($time/3600), "period" => "hours");
	}
}

/**
 * Figures out the length of a suspension/warning
 *
 * @param int $time The amount of time to calculate the length of suspension/warning
 * @param string $period The period of time to calculate the length of suspension/warning
 * @return int Length of the suspension/warning (in seconds)
 */
function fetch_time_length($time, $period)
{
	$time = (int)$time;

	if($period == "hours")
	{
		$time = $time*3600;
	}
	else if($period == "days")
	{
		$time = $time*86400;
	}
	else if($period == "weeks")
	{
		$time = $time*604800;
	}
	else if($period == "months")
	{
		$time = $time*2592000;
	}
	else if($period == "never" && $time == 0)
	{
		// User is permanentely banned
		$time = "-1";
	}
	else
	{
		$time = 0;
	}
	return $time;
}
