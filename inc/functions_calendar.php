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
 * Build a mini calendar for a specific month
 *
 * @param array The calendar array for the calendar
 * @param int The month of the year
 * @param int The year
 * @param array Optional events cache for this calendar
 * @return string The built mini calendar
 */
function build_mini_calendar($calendar, $month, $year, &$events_cache)
{
	global $events_cache, $mybb, $templates, $theme, $monthnames;

	// Incoming month/year?
	if(!$year || $year > my_date("Y")+5)
	{
		$year = my_date("Y");
	}

	// Then the month
	if($month < 1 || $month > 12)
	{
		$month = my_date("n");
	}

	$weekdays = fetch_weekday_structure($calendar['startofweek']);

	$calendar_permissions = get_calendar_permissions($calendar['cid']);

	$month_link = get_calendar_link($calendar['cid'], $year, $month);

	$next_month = get_next_month($month, $year);
	$prev_month = get_prev_month($month, $year);

	$month_start_weekday = gmdate("w", gmmktime(0, 0, 0, $month, $calendar['startofweek']+1, $year));
	if($month_start_weekday != $weekdays[0] || $calendar['startofweek'] != 0)
	{
		$day = gmdate("t", gmmktime(0, 0, 0, $prev_month['month'], 1, $prev_month['year']));
		$day -= array_search(($month_start_weekday), $weekdays);
		$day += $calendar['startofweek']+1;
		$calendar_month = $prev_month['month'];
		$calendar_year = $prev_month['year'];

		if($day > 31 && $calendar['startofweek'] == 1 && $prev_month_days == 30)
		{
			// We need to fix it for these days
			$day = 25;
		}
	}
	else
	{
		$day = $calendar['startofweek']+1;
		$calendar_month = $month;
		$calendar_year = $year;
	}

	$prev_month_days = gmdate("t", gmmktime(0, 0, 0, $prev_month['month'], 1, $prev_month['year']));

	// So now we fetch events for this month
	$start_timestamp = gmmktime(0, 0, 0, $calendar_month, $day, $year);
	$num_days = gmdate("t", gmmktime(0, 0, 0, $month, 1, $year));
	$end_timestamp = gmmktime(23, 59, 59, $month, $num_days, $year);

	if(!$events_cache)
	{
		$events_cache = get_events($calendar, $start_timestamp, $end_timestamp, $calendar_permissions['canmoderateevents']);
	}

	$today = my_date("dnY");

	// Build weekday headers
	$weekday_headers = '';
	foreach($weekdays as $weekday)
	{
		$weekday_name = fetch_weekday_name($weekday, true);
		eval("\$weekday_headers .= \"".$templates->get("calendar_mini_weekdayheader")."\";");
	}

	$in_month = 0;
	$day_bits = $calendar_rows = '';
	for($row = 0; $row < 6; ++$row) // Iterate weeks (each week gets a row)
	{
		foreach($weekdays as $weekday_id => $weekday)
		{
			// Current month always starts on 1st row
			if($row == 0 && $day == $calendar['startofweek']+1)
			{
				$in_month = 1;
				$calendar_month = $month;
				$calendar_year = $year;
			}
			else if($calendar_month == $prev_month['month'] && $day > $prev_month_days)
			{
				$day = 1;
				$in_month = 1;
				$calendar_month = $month;
				$calendar_year = $year;
			}
			else if($day > $num_days && $calendar_month != $prev_month['month'])
			{
				$in_month = 0;
				$calendar_month = $next_month['month'];
				$calendar_year = $next_month['year'];
				$day = 1;
				if($calendar_month == $month)
				{
					$in_month = 1;
				}
			}

			if($weekday_id == 0)
			{
				$week_stamp = gmmktime(0, 0, 0, $calendar_month, $day, $calendar_year);
				$week_link = get_calendar_week_link($calendar['cid'], $week_stamp);
			}

			if($weekday_id == 0 && $calendar_month == $next_month['month'])
			{
				break;
			}

			$link_to_day = false;
			// Any events on this specific day?
			if(@count($events_cache["$day-$calendar_month-$calendar_year"]) > 0)
			{
				$link_to_day = true;
			}

			// Is the current day
			if($day.$calendar_month.$year == $today && $month == $calendar_month)
			{
				$day_class = "trow_sep";
			}
			// Not in this month
			else if($in_month == 0)
			{
				$day_class = "trow1";
			}
			// Just a normal day in this month
			else
			{
				$day_class = "trow2";
			}
			if($link_to_day)
			{
				$day_link = "<a href=\"".get_calendar_link($calendar['cid'], $calendar_year, $calendar_month, $day)."\">{$day}</a>";
			}
			else
			{
				$day_link = $day;
			}
			eval("\$day_bits .= \"".$templates->get("calendar_mini_weekrow_day")."\";");
			++$day;
		}
		if($day_bits)
		{
			eval("\$calendar_rows .= \"".$templates->get("calendar_mini_weekrow")."\";");
		}
		$day_bits = "";
	}
	eval("\$mini_calendar = \"".$templates->get("calendar_mini")."\";");
	return $mini_calendar;
}

/**
 * Cache available calendars in to memory or return the cached calendars
 *
 * @return array Cached calendars
 */
function cache_calendars()
{
	global $db;
	static $calendar_cache;

	if(is_array($calendar_cache))
	{
		return $calendar_cache;
	}

	$query = $db->simple_select("calendars", "*", "", array("order_by" => "disporder", "order_dir" => "asc"));
	while($calendar = $db->fetch_array($query))
	{
		$calendar_cache[$calendar['cid']] = $calendar;
	}
	return $calendar_cache;
}

/**
 * Fetch the calendar permissions for the current user for one or more calendars
 *
 * @param int Optional calendar ID. If none specified, permissions for all calendars are returned
 * @return array Array of permissions
 */
function get_calendar_permissions($cid=0)
{
	global $db, $mybb;
	static $calendar_permissions;

	$calendars = cache_calendars();

	$group_permissions = array(
		"canviewcalendar" => $mybb->usergroup['canviewcalendar'],
		"canaddevents" => $mybb->usergroup['canaddevents'],
		"canbypasseventmod" => $mybb->usergroup['canbypasseventmod'],
		"canmoderateevents" => $mybb->usergroup['canmoderateevents']
	);

	if(!is_array($calendars))
	{
		return $group_permissions;
	}

	$gid = $mybb->user['usergroup'];

	if(isset($mybb->user['additionalgroups']))
	{
		$gid .= ",".$mybb->user['additionalgroups'];
	}

	if(!is_array($calendar_permissions))
	{
		$calendar_permissions = array();
		$query = $db->simple_select("calendarpermissions", "*");
		while($permission = $db->fetch_array($query))
		{
			$calendar_permissions[$permission['cid']][$permission['gid']] = $permission;
		}

		// Add in our usergroup permissions (if custom ones are set, these aren't added)
		if(is_array($calendar_permissions))
		{
			foreach($calendar_permissions as $calendar => $permission)
			{
				if(is_array($calendar_permissions[$calendar][$mybb->user['usergroup']]))
				{
					// Already has permissions set
					continue;
				}

				// Use the group permissions!
				$calendar_permissions[$calendar][$mybb->user['usergroup']] = $group_permissions;
				$calendar_permissions[$calendar][$mybb->user['usergroup']]['cid'] = $calendar;
				$calendar_permissions[$calendar][$mybb->user['usergroup']]['gid'] = $mybb->user['usergroup'];
			}
		}
	}

	if($cid > 0)
	{
		if(isset($calendar_permissions[$cid]))
		{
			$permissions = fetch_calendar_permissions($cid, $gid, $calendar_permissions[$cid]);
		}
		if(empty($permissions))
		{
			$permissions = $group_permissions;
		}
	}
	else
	{
		foreach($calendars as $calendar)
		{
			if(isset($calendar_permissions[$calendar['cid']]))
			{
				$permissions[$calendar['cid']] = fetch_calendar_permissions($calendar['cid'], $gid, $calendar_permissions[$calendar['cid']]);
			}
			if(empty($permissions[$calendar['cid']]))
			{
				$permissions[$calendar['cid']] = $group_permissions;
			}
		}
	}
	return $permissions;
}

/**
 * Fetch the calendar permissions
 *
 * @param int Calendar ID
 * @param mixed User group ID
 * @return array Array of permissions for this calendar and group
 * @return array Array of current permissions
 */
function fetch_calendar_permissions($cid, $gid, $calendar_permissions)
{
	$groups = explode(",", $gid);

	if(!is_array($calendar_permissions))
	{
		return;
	}

	$current_permissions = array();

	foreach($groups as $gid)
	{
		// If this calendar has permissions set for this group
		if($calendar_permissions[$gid])
		{
			$level_permissions = $calendar_permissions[$gid];
			foreach($level_permissions as $permission => $access)
			{
				if($access >= $current_permissions[$permission] || ($access == "yes" && $current_permissions[$permission] == "no") || !$current_permissions[$permission])
				{
					$current_permissions[$permission] = $access;
				}
			}
		}
	}

	if(count($current_permissions) == 0)
	{
		return;
	}
	return $current_permissions;
}

/**
 * Build a calendar select list to jump between calendars
 *
 * @param int The selected calendar ID
 * @return string The calendar select
 */
function build_calendar_jump($selected=0)
{
	global $db, $mybb, $templates, $lang, $gobutton;

	$calendar_permissions = get_calendar_permissions();

	$calendars = cache_calendars();

	if(!is_array($calendars))
	{
		return;
	}

	$jump_options = '';

	foreach($calendars as $calendar)
	{
		if($calendar_permissions[$calendar['cid']]['canviewcalendar'] == 0)
		{
			continue;
		}
		$calendar['name'] = htmlspecialchars_uni($calendar['name']);
		$sel = "";
		if($selected == $calendar['cid'] || ($selected == 0 && $calendar['disporder'] == 1))
		{
			$sel = "selected=\"selected\"";
		}

		eval("\$jump_options .= \"".$templates->get("calendar_jump_option")."\";");
	}

	eval("\$calendar_jump = \"".$templates->get("calendar_jump")."\";");
	return $calendar_jump;
}

/**
 * Fetch the next calendar month from a specified month/year
 *
 * @param int The month
 * @param int The year
 * @return array Array of the next month and next year
 */
function get_next_month($month, $year)
{
	global $monthnames;

	if($month == 12)
	{
		$nextmonth = 1;
		$nextyear = $year+1;
	}
	else
	{
		$nextmonth = $month+1;
		$nextyear = $year;
	}

	return array("month" => $nextmonth, "year" => $nextyear, "name" => $monthnames[$nextmonth]);
}

/**
 * Fetch the previous calendar month from a specified month/year
 *
 * @param int The month
 * @param int The year
 * @return array Array of the previous month and previous year
 */
function get_prev_month($month, $year)
{
	global $monthnames;

	if($month == 1)
	{
		$prevmonth = 12;
		$prevyear = $year-1;
	}
	else
	{
		$prevmonth = $month-1;
		$prevyear = $year;
	}

	return array("month" => $prevmonth, "year" => $prevyear, "name" => $monthnames[$prevmonth]);
}

/**
 * Fetch the events for a specific calendar and date range
 *
 * @param int The calendar ID
 * @param int Start time stamp
 * @param int End time stmap
 * @param int 1 to fetch unapproved events too
 * @param int The user ID to fetch private events for (0 fetches none)
 * @return array Array of events
 */
function get_events($calendar, $start, $end, $unapproved=0, $private=1)
{
	global $db, $mybb;

	// We take in to account timezones here - we add/subtract 12 hours from our GMT time ranges
	$start -= 12*3600;
	$end += 12*3600;

	$visible_where = '';
	if($unapproved != 1)
	{
		$visible_where = " AND e.visible='1'";
	}

	$events_cache = array();
	$query = $db->query("
		SELECT u.*, e.*
		FROM ".TABLE_PREFIX."events e
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=e.uid)
		WHERE e.cid='{$calendar['cid']}' {$visible_where} AND ((e.endtime>={$start} AND e.starttime<={$end}) OR (e.endtime=0 AND e.starttime>={$start} AND e.starttime<={$end})) AND ((e.uid='{$mybb->user['uid']}' AND private='1') OR private!='1')
		ORDER BY endtime DESC
	");
	while($event = $db->fetch_array($query))
	{
		if($event['ignoretimezone'] == 0)
		{
			$offset = $event['timezone'];
		}
		else
		{
			$offset = $mybb->user['timezone'];
		}
		$event['starttime_user'] = $event['starttime']+($offset*3600);

		// Single day event
		if($event['endtime'] == 0)
		{
			$event_date = gmdate("j-n-Y", $event['starttime_user']);
			$events_cache[$event_date][] = $event;
		}
		// Ranged event
		else
		{
			$event_date = explode("-", gmdate("j-n-Y", $event['starttime_user']));
			$event['endtime_user'] = $event['endtime']+($offset*3600);
			$event['weekday_start'] = $calendar['startofweek'];

			$start_day = gmmktime(0, 0, 0, $event_date[1], $event_date[0], $event_date[2]);

			$event['repeats'] = my_unserialize($event['repeats']);

			// Event does not repeat - just goes over a few days
			if($event['repeats']['repeats'] == 0)
			{
				if($start_day < $start)
				{
					$range_start = gmmktime(0, 0, 0, gmdate("n", $start), gmdate("j", $start), gmdate("Y", $start));
				}
				else
				{
					$range_start = $start_day;
				}
			}
			else
			{
				$range_start = fetch_next_occurance($event, array("start" => $start, "end" => $end), $start_day, true);
			}
			$first = "";
			$event_date = explode("-", gmdate("j-n-Y", $range_start));

			// Get rid of hour/minutes because sometimes they cause the events to stretch into the next day
			$range_end = gmmktime(23, 59, 59, gmdate("n", $event['endtime_user']), gmdate("j", $event['endtime_user']), gmdate("Y", $event['endtime_user']));
			while($range_start < $range_end)
			{
				// Outside the dates we care about, break! (No unnecessary looping here!)
				if($range_start > $end || !$range_start)
				{
					break;
				}
				if($range_start >= $start)
				{
					$day_date = gmdate("j-n-Y", $range_start);
					if($first && $day_date != "{$first}")
					{
						$events_cache[$day_date][] = &$events_cache["{$first}"][$count];
					}
					else if(!$first)
					{
						if(!isset($events_cache[$day_date]))
						{
							$events_cache[$day_date] = array();
						}
						$count = count($events_cache[$day_date]);
						$first = $day_date;
						$events_cache[$day_date][] = $event;
					}
				}
				if($event['repeats']['repeats'] == 0)
				{
					$range_start += 86400;
				}
				else
				{
					$range_start = fetch_next_occurance($event, array("start" => $start, "end" => $end), $range_start);
				}
			}
		}
	}
	return $events_cache;
}

/**
 * Fetch the birthdays for one or more months or a specific day
 *
 * @param mixed Integer of the month or array of months
 * @param int Day of the specific month (if only one month specified above)
 * @return array Array of birthdays
 */
function get_birthdays($months, $day="")
{
	global $db;

	$year = my_date("Y");

	if(!is_array($months))
	{
		$months = array($months);
	}

	foreach($months as $month)
	{
		if($day)
		{
			$day_where = "{$day}-{$month}";
		}
		else
		{
			$day_where = "%-{$month}";
		}
		if($month == 3 && ($day == 1 || !$day) && my_date("L", gmmktime(0, 0, 0, $month, 1, $year)) != 1)
		{
			$where[] = "birthday LIKE '29-2%' OR birthday='29-2'";
			$feb_fix = 1;
		}
		$where[] = "birthday LIKE '{$day_where}-%' OR birthday LIKE '{$day_where}'";
	}

	$where = implode(" OR ", $where);

	$bdays = array();

	$query = $db->simple_select("users", "uid, username, birthday, birthdayprivacy, usergroup, displaygroup", $where);
	while($user = $db->fetch_array($query))
	{
		$bday = explode("-", $user['birthday']);
		if($bday[2] && $bday[2] < $year)
		{
			$user['age'] = $year - $bday[2];
		}
		if($feb_fix == 1 && $bday[0] == 29 && $bday[1] == 2)
		{
			$bdays["1-3"][] = $user;
		}
		else
		{
			$bdays["$bday[0]-$bday[1]"][] = $user;
		}
	}
	if($day)
	{
		if(!isset($bdays["$day-$month"]))
		{
			return array();
		}
		return $bdays["$day-$month"];
	}
	return $bdays;
}

/**
 * Fetch an ordered list of weekdays depended on a specified starting day
 *
 * @param int The weekday we want to start the week with
 * @return array Ordered list of weekdays dependant on start of week
 */
function fetch_weekday_structure($week_start)
{
	switch($week_start)
	{
		case "1":
			$weekdays = array(1,2,3,4,5,6,0);
			break;
		case "2":
			$weekdays = array(2,3,4,5,6,0,1);
			break;
		case "3":
			$weekdays = array(3,4,5,6,0,1,2);
			break;
		case "4":
			$weekdays = array(4,5,6,0,1,2,3);
			break;
		case "5":
			$weekdays = array(5,6,0,1,2,3,4);
			break;
		case "6":
			$weekdays = array(6,0,1,2,3,4,5);
			break;
		default:
			$weekdays = array(0,1,2,3,4,5,6);
			break;
	}
	return $weekdays;
}

/**
 * Fetch a weekday name based on a number
 *
 * @param int The weekday number
 * @param boolean True to fetch the short name ('S'), false to fetch full name
 * @return string The weekday name
 */
function fetch_weekday_name($weekday, $short=false)
{
	global $lang;
	switch($weekday)
	{
		case 1:
			$weekday_name = $lang->monday;
			$short_weekday_name = $lang->short_monday;
			break;
		case 2:
			$weekday_name = $lang->tuesday;
			$short_weekday_name = $lang->short_tuesday;
			break;
		case 3:
			$weekday_name = $lang->wednesday;
			$short_weekday_name = $lang->short_wednesday;
			break;
		case 4:
			$weekday_name = $lang->thursday;
			$short_weekday_name = $lang->short_thursday;
			break;
		case 5:
			$weekday_name = $lang->friday;
			$short_weekday_name = $lang->short_friday;
			break;
		case 6:
			$weekday_name = $lang->saturday;
			$short_weekday_name = $lang->short_saturday;
			break;
		case 0:
			$weekday_name = $lang->sunday;
			$short_weekday_name = $lang->short_sunday;
			break;
	}

	if($short == true)
	{
		return $short_weekday_name;
	}
	else
	{
		return $weekday_name;
	}
}

/**
 * Fetches the next occurance for a repeating event.
 *
 * @param array The event array
 * @param array The range of start/end timestamps
 * @param int The last occurance of this event
 * @param boolean True if this is our first iteration of this function (Does some special optimised calculations on false)
 * @return int The next occurance timestamp
 */
function fetch_next_occurance($event, $range, $last_occurance, $first=false)
{
	$new_time = $last_occurance;

	$repeats = $event['repeats'];

	$start_day = explode("-", gmdate("j-n-Y", $event['starttime_user']));
	$start_date = gmmktime(0, 0, 0, $start_day[1], $start_day[0], $start_day[2]);

	if($repeats['repeats'] == 0)
	{
		$new_time += 86400;
	}
	// Repeats daily
	else if($repeats['repeats'] == 1)
	{
		// If this isn't the first time we've called this function then we can just tack on the time since $last_occurance
		if($first == false)
		{
			$new_time += 86400*$repeats['days'];
		}
		else
		{
			// Need to count it out
			if($range['start'] > $event['starttime'])
			{
				$days_since = ceil(($range['start']-$start_date)/86400);
				$occurances = floor($days_since/$repeats['days']);
				$next_date = $occurances*$repeats['days'];
				$new_time = $event['starttime']+(86400*$next_date);
			}
			else
			{
				$new_time = $start_date;
			}
		}
	}
	// Repeats on weekdays only
	else if($repeats['repeats'] == 2)
	{
		if($first == false)
		{
			$last_dow = gmdate("w", $last_occurance);
			// Last day of week = friday, +3 gives monday
			if($last_dow == 5)
			{
				$new_time += 86400*3;
			}
			// Still in week, add a day
			else
			{
				$new_time += 86400;
			}
		}
		// First loop with start date
		else
		{
			if($range['start'] < $event['starttime'])
			{
				$start = $event['starttime'];
			}
			else
			{
				$start = $range['start'];
			}
			$first_dow = gmdate("w", $start);
			if($first_dow == 6)
			{
				$new_time = $start + (86400*2);
			}
			else if($first_dow == 0)
			{
				$new_time = $start + 86400;
			}
			else
			{
				$new_time = $start;
			}
		}
	}
	// Repeats weekly
	else if($repeats['repeats'] == 3)
	{
		$weekdays = fetch_weekday_structure($event['weekday_start']);
		$last_dow = gmdate("w", $last_occurance);
		if($first == true)
		{
			$last_dow = -1;
			$start_day = gmdate('w', $last_occurance);
			if(in_array($start_day, $weekdays))
			{
				$next_dow = 0;
			}
		}
		else
		{
			foreach($repeats['days'] as $weekday)
			{
				if($weekday > $last_dow)
				{
					$next_dow = $weekday;
					break;
				}
			}
		}
		if(!isset($next_dow))
		{
			// Fetch first weekday
			$first = $repeats['days'][0]*86400;
			$new_time += $first;
			// Increase x weeks
			$new_time += (7-$last_dow)*86400;
			$new_time += (($repeats['weeks']-1)*604800);
		}
		else
		{
			// Next day of week exists
			if($last_dow > 0)
			{
				$day_diff = $next_dow-$last_dow;
			}
			else
			{
				$day_diff = $next_dow;
			}
			$new_time += $day_diff*86400;
		}
	}
	// Repeats monthly
	else if($repeats['repeats'] == 4)
	{
		$last_month = gmdate("n", $last_occurance);
		$last_year = gmdate("Y", $last_occurance);
		$last_day = gmdate("j", $last_occurance);
		$last_num_days = gmdate("t", $last_occurance);

		// X of every Y months
		if($repeats['day'])
		{
			if($first == true)
			{
				if($last_day <= $repeats['day'])
				{
					$new_time = gmmktime(0, 0, 0, $last_month, $repeats['day'], $last_year);
				}
				else
				{
					$new_time = gmmktime(0, 0, 0, $last_month+1, $repeats['day'], $last_year);
					if($new_time > $event['endtime'])
					{
						return false;
					}
				}
			}
			else
			{
				$new_time = gmmktime(0, 0, 0, $last_month+$repeats['months'], $repeats['day'], $last_year);
			}
		}
		// The 1st/etc (weekday) of every X months
		else
		{
			if($first == true)
			{
				$new_time = fetch_weekday_monthly_repetition($repeats, $last_month, $last_year);
				if($new_time < $last_occurance)
				{
					$new_time = fetch_weekday_monthly_repetition($repeats, $last_month+1, $last_year);
				}
			}
			else
			{
				$new_time = fetch_weekday_monthly_repetition($repeats, $last_month+$repeats['months'], $last_year);
			}
		}
	}
	// Repeats yearly
	else if($repeats['repeats'] == 5)
	{
		$last_year = gmdate("Y", $last_occurance);

		// Repeats on (day) of (month) every (years)
		if($repeats['day'])
		{
			if($first == true)
			{
				$new_time = gmmktime(0, 0, 0, $repeats['month'], $repeats['day'], $last_year);
				if($new_time < $last_occurance)
				{
					$new_time = gmmktime(0, 0, 0, $repeats['month'], $repeats['day'], $last_year+1);
				}
			}
			else
			{
				$new_time = gmmktime(0, 0, 0, $repeats['month'], $repeats['day'], $last_year+$repeats['years']);
			}
		}
		// The 1st/etc (weekday) of (month) every (years)
		else
		{
			if($first == true)
			{
				$new_time = fetch_weekday_monthly_repetition($repeats, $repeats['month'], $last_year);
				if($new_time < $last_occurance)
				{
					$new_time = fetch_weekday_monthly_repetition($repeats, $repeats['month'], $last_year+1);
				}
			}
			else
			{
				$new_time = fetch_weekday_monthly_repetition($repeats, $repeats['month'], $last_year+$repeats['years']);
			}
		}
	}
	return $new_time;
}

/**
 * Fetch a friendly repetition value for a specific event (Repeats every x months etc)
 *
 * @param array The array of the event
 * @return string The friendly repetition string
 */
function fetch_friendly_repetition($event)
{
	global $lang;

	$monthnames = array(
		"offset",
		$lang->month_1,
		$lang->month_2,
		$lang->month_3,
		$lang->month_4,
		$lang->month_5,
		$lang->month_6,
		$lang->month_7,
		$lang->month_8,
		$lang->month_9,
		$lang->month_10,
		$lang->month_11,
		$lang->month_12
	);

	if(!is_array($event['repeats']))
	{
		$event['repeats'] = my_unserialize($event['repeats']);
		if(!is_array($event['repeats']))
		{
			return false;
		}
	}

	$repeats = $event['repeats'];

	switch($repeats)
	{
		case 1:
			if($repeats['days'] <= 1)
			{
				return $lang->repeats_every_day;
			}
			return $lang->sprintf($lang->repeats_every_x_days, $event['repeats']['days']);
			break;
		case 2:
			return $lang->repeats_on_weekdays;
			break;
		case 3:
			if($event['repeats']['days'] || count($event['repeats']['days']) == 7)
			{
				$weekdays  = null;
				foreach($event['repeats']['days'] as $id => $weekday)
				{
					$weekday_name = fetch_weekday_name($weekday);
					if($event['repeats']['days'][$id+1] && $weekday)
					{
						$weekdays .= $lang->comma;
					}
					else if(!$event['repeats']['days'][$id+1] && $weekday)
					{
						$weekdays .= " {$lang->and} ";
					}
					$weekdays .= $weekday_name;
				}
			}
			if($event['repeats']['weeks'] == 1)
			{
				if($weekdays)
				{
					return $lang->sprintf($lang->every_week_on_days, $weekdays);
				}
				else
				{
					return $lang->sprintf($lang->every_week);
				}
			}
			else
			{
				if($weekdays)
				{
					return $lang->sprintf($lang->every_x_weeks_on_days, $event['repeats']['weeks'], $weekdays);
				}
				else
				{
					return $lang->sprintf($lang->every_x_weeks, $event['repeats']['weeks']);
				}
			}
			break;
		case 4:
			if($event['repeats']['day'])
			{
				if($event['repeats']['months'] == 1)
				{
					return $lang->sprintf($lang->every_month_on_day, $event['repeats']['day']);
				}
				else
				{
					return $lang->sprintf($lang->every_x_months_on_day, $event['repeats']['day'], $event['repeats']['months']);
				}
			}
			else
			{
				$weekday_name = fetch_weekday_name($event['repeats']['weekday']);
				$occurance = "weekday_occurance_".$event['repeats']['occurance'];
				$occurance = $lang->$occurance;
				if($event['repeats']['months'] == 1)
				{
					return $lang->sprintf($lang->every_month_on_weekday, $occurance, $weekday_name);
				}
				else
				{
					return $lang->sprintf($lang->every_x_months_on_weekday, $occurance, $weekday_name, $event['repeats']['months']);
				}
			}
			break;
		case 5:
			$month = $monthnames[$event['repeats']['month']];
			if($event['repeats']['day'])
			{
				if($event['repeats']['years'] == 1)
				{
					return $lang->sprintf($lang->every_year_on_day, $event['repeats']['day'], $month);
				}
				else
				{
					return $lang->sprintf($lang->every_x_years_on_day, $event['repeats']['day'], $month, $event['repeats']['years']);
				}
			}
			else
			{
				$weekday_name = fetch_weekday_name($event['repeats']['weekday']);
				$occurance = "weekday_occurance_".$event['repeats']['occurance'];
				$occurance = $lang->$occurance;
				if($event['repeats']['years'] == 1)
				{
					return $lang->sprintf($lang->every_year_on_weekday, $occurance, $weekday_name, $month);
				}
				else
				{
					return $lang->sprintf($lang->every_x_year_on_weekday, $occurance, $weekday_name, $month, $event['repeats']['years']);
				}
			}
			break;
	}
}

/**
 * Fetch a timestamp for "the first/second etc weekday" for a month.
 *
 * @param array The repetition array from the event
 * @param int The month of the year
 * @param int The year
 * @return int The UNIX timestamp
 */
function fetch_weekday_monthly_repetition($repeats, $month, $year)
{
	$first_last = gmmktime(0, 0, 0, $month, 1, $year);
	$first_dow = gmdate("w", $first_last);
	$day = 1+($repeats['weekday']-$first_dow);
	if($day < 1)
	{
		$day += 7;
	}
	if($repeats['occurance'] != "last")
	{
		$day += ($repeats['occurance']-1)*7;
	}
	else
	{
		$last_dow = gmdate("w", gmmktime(0, 0, 0, $month, gmdate("t", $first_last), $year));
		$day = (gmdate("t", $first_last)-$last_dow)+$repeats['weekday'];
		if($day > gmdate("t", $first_dow))
		{
			$day -= 7;
		}
	}
	return gmmktime(0, 0, 0, $month, $day, $year);
}
