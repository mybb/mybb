<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: functions_warnings.php 5297 2010-12-28 22:01:14Z Tomm $
 */

/**
 * Returns a friendly expiration time of a suspension/warning
 *
 * @param int The time period of the suspension/warning
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
 * @param int The amount of time to calculate the length of suspension/warning
 * @param string The period of time to calculate the length of suspension/warning
 * @return int Length of the suspension/warning (in seconds)
 */
function fetch_time_length($time, $period)
{
	$time = intval($time);		

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
?>