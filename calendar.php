<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

define("IN_MYBB", 1);

$templatelist = "calendar,calendar_eventbit_public,calendar_eventbit_private,calendar_addpublicevent,calendar_addprivateevent,calendar_addevent,calendar_event,calendar_daybit,calendar_daybit_today";
require_once "./global.php";

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

$event_parser_options = array(
	"allow_html" => "no",
	"allow_mycode" => "yes",
	"allow_smilies" => "yes",
	"allow_imgcode" => "yes"
);


// Load global language phrases
$lang->load("calendar");

if($mybb->settings['enablecalendar'] == "no")
{
	error($lang->calendar_disabled);
}

if($mybb->usergroup['canviewcalendar'] == "no")
{
	error_no_permission();
}

// Make $eid an easy-to-use variable.
$eid = intval($mybb->input['eid']);

/* If we are looking at an event, select the date for that event first. */
if($mybb->input['action'] == "event")
{
	$options = array(
		"limit" => 1
	);
	
	$query = $db->simple_select("events", "*", "eid=".$eid, $options);
	$event = $db->fetch_array($query);
	
	if(!$event)
	{
		error($lang->error_invalidevent);
	}
	
	$day = $event['start_day'];
	$month = $event['start_month'];
	$year = $event['start_year'];
	$end_day = $event['end_day'];
	$end_month = $event['end_month'];
	$end_year = $event['end_year'];

	$start_time_hour = $event['start_time_hours'];
	$start_time_min = $event['start_time_mins'];
	$end_time_hour = $event['end_time_hours'];
	$end_time_min = $event['end_time_mins'];
}
else
{
	// Let's find the date we're looking at.
	// First of all, the year
	if($mybb->input['year'] && $mybb->input['year'] <= my_date("Y")+5)
	{
		$year = intval($mybb->input['year']);
	}
	else
	{
		$year = my_date("Y");
	}
	
	// Then the month
	if($mybb->input['month'] >=1 && $mybb->input['month'] <= 12)
	{
		$month = intval($mybb->input['month']);
	}
	else
	{
		$month = my_date("n");
	}

	// Find the number of days in that month
	$time = gmmktime(0, 0, 0, $month, 1, $year);
	$days = my_date("t", $time);
	
	// Now the specific day
	if(isset($mybb->input['day']) && $mybb->input['day'] >= 1 && $mybb->input['day'] <= $days)
	{
		$day = $mybb->input['day'];
	}
	else
	{
		// Make the day the last day of the month, if the user overshot the number of days in the month
		if(isset($mybb->input['day']) && $mybb->input['day'] > $days)
		{
			$day = $days;
		}
		// Make the day the first day of the month, if the user undershot the number of days in the month
		elseif(isset($mybb->input['day']) && $mybb->input['day'] < 1)
		{
			$day = 1;
		}
		// This shouldn't be needed, but just in case if someone falls into the hole...
		else
		{
			$day = my_date("j");
		}
	}

	// Sort out any end dates
	if($mybb->input['end_year'] && $mybb->input['end_year'] <= my_date("Y")+5)
	{
		$end_year = intval($mybb->input['end_year']);
	}
	else
	{
		$end_year = '';
	}
	
	if($mybb->input['end_month'] >=1 && $mybb->input['end_month'] <= 12)
	{
		$end_month = intval($mybb->input['end_month']);
	}
	else
	{
		$end_month = '';
	}

	$time = gmmktime(0, 0, 0, $month, 1, $year);
	$days = my_date("t", $time);
	
	if(isset($mybb->input['end_day']) && $mybb->input['end_day'] >= 1 && $mybb->input['end_day'] <= $days)
	{
		$end_day = $mybb->input['end_day'];
	}
	else
	{
		$end_day = '';
	}

	$start_time_hour = '';
	if(isset($mybb->input['start_time_hours']) && $mybb->input['start_time_hours'] !== '')
	{
		$start_time_hour = intval($mybb->input['start_time_hours']);
		if($start_time_hour > 23 || $start_time_hour <= 0)
		{
			$start_time_hour = '00';
		}
	}

	$start_time_min = '';
	if(isset($mybb->input['start_time_mins']) && $mybb->input['start_time_mins'] !== '')
	{
		$start_time_min = intval($mybb->input['start_time_mins']);
		if($start_time_min > 59 || $start_time_min <= 0)
		{
			$start_time_min = '00';
		}
	}

	$end_time_hour = '';
	if(isset($mybb->input['end_time_hours']) && $mybb->input['end_time_hours'] !== '')
	{
		$end_time_hour = intval($mybb->input['end_time_hours']);
		if($end_time_hour > 23 || $end_time_hour <= 0)
		{
			$end_time_hour = '00';
		}
	}

	$end_time_min = '';
	if(isset($mybb->input['end_time_mins']) && $mybb->input['end_time_mins'] !== '')
	{
		$end_time_min = intval($mybb->input['end_time_mins']);
		if($end_time_min > 59 || $end_time_min <= 0)
		{
			$end_time_min = '00';
		}
	}
}

$monthnames = array(
	"offset",
	$lang->alt_month_1,
	$lang->alt_month_2,
	$lang->alt_month_3,
	$lang->alt_month_4,
	$lang->alt_month_5,
	$lang->alt_month_6,
	$lang->alt_month_7,
	$lang->alt_month_8,
	$lang->alt_month_9,
	$lang->alt_month_10,
	$lang->alt_month_11,
	$lang->alt_month_12
);

// Make navigation
add_breadcrumb($lang->nav_calendar, "calendar.php");

if($month && $year)
{
	add_breadcrumb("$monthnames[$month] $year", "calendar.php?month={$month}&amp;year={$year}");
}

// No weird actions allowed.
$accepted_actions = array("event", "addevent", "do_addevent", "editevent", "do_editevent", "dayview");
if(!in_array($mybb->input['action'], $accepted_actions))
{
	$mybb->input['action'] = "calendar_main";
}

// View a specific event.
if($mybb->input['action'] == "event")
{
	$plugins->run_hooks("calendar_event_start");

	$query = $db->query("
		SELECT e.*, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."events e
		LEFT JOIN ".TABLE_PREFIX."users u ON (e.author=u.uid)
		WHERE e.eid='{$eid}'
	");
	$event = $db->fetch_array($query);

	if(!$event['eid'])
	{
		error($lang->error_invalidevent);
	}

	if($event['private'] == "yes" && $event['username'] != $mybb->user['username'])
	{
		error_no_permission();
	}

	if(($event['author'] == $mybb->user['uid'] && $mybb->user['uid'] != 0) || $mybb->usergroup['cancp'] == "yes")
	{
		$editbutton = "<a href=\"calendar.php?action=editevent&amp;eid={$event['eid']}\"><img src=\"{$theme['imglangdir']}/postbit_edit.gif\" border=\"0\" alt=\"$lang->alt_edit\" /></a>";
		$deletebutton = "<a href=\"javascript:MyBB.deleteEvent({$event['eid']});\"><img src=\"{$theme['imglangdir']}/postbit_delete.gif\" border=\"0\" alt=\"$lang->alt_delete\" /></a>";
	}
	
	$event['subject'] = $parser->parse_badwords($event['subject']);
	$event['subject'] = htmlspecialchars_uni($event['subject']);
	$event['description'] = $parser->parse_message($event['description'], $event_parser_options);
	
	if($event['username'])
	{
		$eventposter = "<a href=\"member.php?action=profile&amp;uid={$event['author']}\">".format_name($event['username'], $event['usergroup'], $event['displaygroup']) . "</a>";
	}
	else
	{
		$eventposter = $lang->guest;
	}

	$eventdate = gmmktime(0, 0, 0, $event['start_month'], $event['start_day'], $event['start_year']);
	$eventdate = my_date($mybb->settings['dateformat'], $eventdate, 0, 0);
	
	if(empty($event['repeat_days']))
	{
		eval("\$event_dates = \"".$templates->get("calendar_eventbit_dates")."\";");

	}
	else
	{
		$repeat_days = $comma = '';

		$eventdate_end = gmmktime(0, 0, 0, $event['end_month'], $event['end_day'], $event['end_year']);
		$eventdate_end = my_date($mybb->settings['dateformat'], $eventdate_end, 0, 0);

		$event_days = explode(',', $event['repeat_days']);
		$day_names = array($lang->sunday, $lang->monday, $lang->tuesday, $lang->wednesday, $lang->thursday, $lang->friday, $lang->saturday);

		foreach($event_days as $value)
		{
			$repeat_days .= $comma . $day_names[$value];
			$comma = ', ';
		}
		eval("\$event_dates = \"".$templates->get("calendar_eventbit_dates_recurring")."\";");
	}

	if($event['start_time_hours'] !== '' || $event['start_time_mins'] !== '' || $event['end_time_hours'] !== '' || $event['end_time_mins'] !== '')
	{
		eval("\$event_times = \"".$templates->get("calendar_eventbit_times")."\";");
	}

	add_breadcrumb($lang->nav_viewevent);

	$plugins->run_hooks("calendar_event_end");

	eval("\$eventpage = \"".$templates->get("calendar_event")."\";");
	output_page($eventpage);
}

// View all events on a specific day.
if($mybb->input['action'] == "dayview")
{
	$plugins->run_hooks("calendar_dayview_start");

	// Load Birthdays
	// If we have 1st March and this year isn't a leap year, fetch birthdays on the 29th.
	if($day == 1 && $month == 3 && my_date("L", mktime(0, 0, 0, $month, 1, $year)) != 1)
	{
		$bday_where = "birthday LIKE '$day-$month-%' OR birthday LIKE '29-2%' OR birthday LIKE '$day-$month'";
		$feb_fix = 1;
	}
	else // Fetch only for this day
	{
		$bday_where = "birthday LIKE '$day-$month-%' OR birthday LIKE '$day-$month'";
		$feb_fix = 0;
	}
	$query = $db->simple_select("users", "uid, username, birthday, usergroup, displaygroup", $bday_where);

	$alterbg = $theme['trow1'];
	$comma = '';
	$birthdays = '';
	
	while($bdays = $db->fetch_array($query))
	{
		$bday = explode("-", $bdays['birthday']);
		if($bday[2] && $bday[2] < $year)
		{
			$age = $year - $bday[2];
			$age = " ($age $lang->years_old)";
		}
		else
		{
			$age = '';
		}
		
		$bdays['username'] = format_name($bdays['username'], $bdays['usergroup'], $bdays['displaygroup']);
		$bday['profilelink'] = build_profile_link($bdays['username'], $bdays['uid']);
		eval("\$birthdays .= \"".$templates->get("calendar_dayview_birthdays_bday", 1, 0)."\";");

		if($alterbg == $theme['trow1'])
		{
			$alterbg = $theme['trow2'];
		}
		else
		{
			$alterbg = $theme['trow1'];
		}
		$comma = ", ";
	}
	$events = '';
	
	// Load Events
	$this_year = $year;
	$this_month = $month;
	$where = "
		(start_day = '{$day}' AND start_month = '{$month}' AND start_year = '{$year}' AND repeat_days = '')
		OR(
			repeat_days != ''
				AND ( ( start_year < '{$this_year}' ) OR ( start_year = '{$this_year}' AND start_month <= '{$this_month}' ) )
				AND ( ( end_year > '{$this_year}' ) OR ( end_year = '{$this_year}' AND end_month >= '{$this_month}' ) )
		)
	";
	
	$query = $db->query("
		SELECT e.*, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."events e
		LEFT JOIN ".TABLE_PREFIX."users u ON (e.author=u.uid)
		WHERE {$where}
		AND ((author='".$mybb->user['uid']."'
		AND private='yes') OR (private!='yes'))
	");	
	while($event = $db->fetch_array($query))
	{
		$event_times = $event_dates = '';
		$event_ok = false;

		$plugins->run_hooks("calendar_dayview_event");

		if(($event['author'] == $mybb->user['uid'] && $mybb->user['uid'] != 0) || $mybb->usergroup['cancp'] == "yes")
		{
			$editbutton = "<a href=\"calendar.php?action=editevent&amp;eid={$event['eid']}\"><img src=\"{$theme['imglangdir']}/postbit_edit.gif\" border=\"0\" alt=\"$lang->alt_edit\" /></a>";
			$deletebutton = "<a href=\"javascript:MyBB.deleteEvent({$event['eid']});\"><img src=\"{$theme['imglangdir']}/postbit_delete.gif\" border=\"0\" alt=\"$lang->alt_delete\" /></a>";
		}
		
		$event['subject'] = $parser->parse_badwords($event['subject']);
		$event['subject'] = htmlspecialchars_uni($event['subject']);
		$event['description'] = $parser->parse_message($event['description'], $event_parser_options);
		
		if($event['username'])
		{
			$eventposter = "<a href=\"member.php?action=profile&amp;uid={$event['author']}\">" . format_name($event['username'], $event['usergroup'], $event['displaygroup']) . "</a>";
		}
		else
		{
			$eventposter = $lang->guest;
		}

		$eventdate = gmmktime(0, 0, 0, $event['start_month'], $event['start_day'], $event['start_year']);
		$eventdate = my_date($mybb->settings['dateformat'], $eventdate, 0, 0);
		
		if($event['start_time_hours'] !== '' && $event['start_time_mins'] !== '' && $event['end_time_hours'] !== '' && $event['end_time_mins'] !== '')
		{
			eval("\$event_times = \"".$templates->get("calendar_dayview_event_times")."\";");
		}

		if(empty($event['repeat_days']))
		{
			eval("\$event_dates = \"".$templates->get("calendar_dayview_event_normal")."\";");
			$event_ok = true;
		}
		else
		{
			$day_names = array($lang->sunday, $lang->monday, $lang->tuesday, $lang->wednesday, $lang->thursday, $lang->friday, $lang->saturday);
			$comma = $repeat_days = '';

			$eventdate_end = gmmktime(0, 0, 0, $event['end_month'], $event['end_day'], $event['end_year']);
			$eventdate_end = my_date($mybb->settings['dateformat'], $eventdate_end, 0, 0);

			$repeats = explode(',', $event['repeat_days']);
			
			// Get the textual list of days
			foreach($repeats as $repeat_day)
			{
				$repeat_days .= $comma . $day_names[$repeat_day];
				$comma = ', ';
			}

			// Find out if the repeats fall on this day
			foreach($repeats as $repeat_day)
			{
				// Find out the first day of the month this particular day falls on
				for($starts_on = 1; $starts_on <= 7; ++$starts_on)
				{
					if(date('w', gmmktime(0, 0, 0, $this_month, $starts_on, $this_year)) == $repeat_day)
					{
						break;
					}
				}

				// Approx 4 weeks in the month
				for($week_no = 0; $week_no < 4; ++$week_no)
				{
					$cur_date = $starts_on + (7 * $week_no);
					if(mktime(0, 0, 0, $this_month, $cur_date, $this_year))
					{
						// If start date is less than selected date
						if(($event['start_year'] < $this_year) || ($event['start_year'] == $this_year && $event['start_month'] < $this_month) || ($event['start_year'] == $this_year && $event['start_month'] == $this_month && $cur_date >= $event['start_day']))
						{
							// If end date is greater than selected date
							if(($event['end_year'] > $this_year) || ($event['end_year'] == $this_year && $event['end_month'] > $this_month) || ($event['end_year'] == $this_year && $event['end_month'] == $this_month && $cur_date < $event['end_day']))
							{
								// If this day is equal to current date
								if($cur_date == $day)
								{
									eval("\$event_dates = \"".$templates->get("calendar_dayview_event_recurring")."\";");
									$event_ok = true;
								}
							}
						}
					} // End if time is ok (perhaps overshot the end of the month?)
				} // End for 4 weeks in the month
			}
		}
		
		if($event_ok)
		{
			eval("\$events .= \"".$templates->get("calendar_dayview_event")."\";");
		}
	}
	
	if(!$events)
	{
		$lang->no_events = sprintf($lang->no_events, $day, $month, $year);
		eval("\$events = \"".$templates->get("calendar_dayview_noevents")."\";");
	}
	
	if($birthdays)
	{
		$eventdate = gmmktime(0, 0, 0, $month, $day, $year);
		$bdaydate = my_date($mybb->settings['dateformat'], $eventdate, 0, 0);
		$lang->birthdays_on_day = sprintf($lang->birthdays_on_day, $bdaydate);
		eval("\$bdaylist = \"".$templates->get("calendar_dayview_birthdays")."\";");
	}
	
	add_breadcrumb($lang->nav_dayview);

	$plugins->run_hooks("calendar_dayview_end");

	eval("\$dayview = \"".$templates->get("calendar_dayview")."\";");
	output_page($dayview);
}

// Process the adding of an event.
if($mybb->input['action'] == "do_addevent" && $mybb->request_method == "post")
{
	$plugins->run_hooks("calendar_do_addevent_start");

	// Set up eventhandler.
	require_once MYBB_ROOT."inc/datahandler.php";
	require_once MYBB_ROOT."inc/datahandlers/event.php";
	$eventhandler = new EventDataHandler("insert");

	// Prepare an array for the eventhandler.
	$event = array(
		"subject" => $mybb->input['subject'],
		"uid" => $mybb->user['uid'],
		"description" => $mybb->input['description'],
		"start_day" => $day,
		"start_month" => $month,
		"start_year" => $year,
		"end_day" => $end_day,
		"end_month" => $end_month,
		"end_year" => $end_year,
		"repeat_days" => $mybb->input['repeat_days'],
		"start_time_hours" => $start_time_hour,
		"start_time_mins" => $start_time_min,
		"end_time_hours" => $end_time_hour,
		"end_time_mins" => $end_time_min,
		"private" => $mybb->input['private'],
	);

	$eventhandler->set_data($event);

	// Now let the eventhandler do all the hard work.
	if(!$eventhandler->validate_event())
	{
		$event_errors = $eventhandler->get_friendly_errors();
		$event_errors = inline_error($event_errors);
		$mybb->input['action'] = "addevent";
	}
	else
	{
		$details = $eventhandler->insert_event();
		$plugins->run_hooks("calendar_do_addevent_end");
		redirect("calendar.php?action=event&eid=".$details['eid'], $lang->redirect_eventadded);
	}
}


// Show the form for adding an event.
if($mybb->input['action'] == "addevent")
{
	$month_start = $month_end = $day_repeat = array();
	$recurring_event = $start_yearopts = $start_dayopts = $start_time = $end_time = '';

	$plugins->run_hooks("calendar_addevent_start");

	// If MyCode is on for this forum and the MyCode editor is enabled inthe Admin CP, draw the code buttons and smilie inserter.
	if($mybb->settings['bbcodeinserter'] != "off" && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
	{
		$codebuttons = build_mycode_inserter();
		$smilieinserter = build_clickable_smilies();
	}

	// Previous selections
	$subject = $description = '';
	if(isset($mybb->input['subject']))
	{
		$subject = htmlspecialchars_uni($mybb->input['subject']);
	}
	
	if(isset($mybb->input['description']))
	{
		$description = htmlspecialchars_uni($mybb->input['description']);
	}

	$month_start[$month] = " selected=\"selected\"";

	// Construct option list for years
	for($i = my_date('Y'); $i < (my_date('Y') + 5); ++$i)
	{
		if($i == $year)
		{
			$start_yearopts .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$start_yearopts .= "<option value=\"{$i}\">{$i}</option>\n";
		}
	}

	// Construct option list for days
	for($i = 1; $i <= 31; ++$i)
	{
		if($i == $day)
		{
			$start_dayopts .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$start_dayopts .= "<option value=\"{$i}\">{$i}</option>\n";
		}
	}

	// Make hour dropdown menu
	$start_time_hours = "<option value=\"\">---</option>\n";
	for($i = 0; $i <= 23; ++$i)
	{
		// Pad the hours with leading 0s
		$j = str_pad($i, 2, '0', STR_PAD_LEFT);
		
		if($start_time_hour !== '' && $start_time_hour == $i)
		{
			$start_time_hours .= "<option value=\"{$i}\" selected=\"selected\">{$j}</option>\n";
		}
		else
		{
			$start_time_hours .= "<option value=\"{$i}\">{$j}</option>\n";
		}
	}
	
	$end_time_hours = "<option value=\"\">---</option>\n";
	for($i = 0; $i <= 23; ++$i)
	{
		// Pad the hours with leading 0s
		$j = str_pad($i, 2, '0', STR_PAD_LEFT);
		
		if($end_time_hour !== '' && $end_time_hour == $i)
		{
			$end_time_hours .= "<option value=\"{$i}\" selected=\"selected\">{$j}</option>\n";
		}
		else
		{
			$end_time_hours .= "<option value=\"{$i}\">{$j}</option>\n";
		}
	}

	// Make minute dropdown menu, spaced every 15 minutes
	$start_time_mins = "<option value=\"\">---</option>\n";
	for($i = 0; $i < 60; $i += 15)
	{
		// Pad the mins with leading 0s
		$j = str_pad($i, 2, '0', STR_PAD_LEFT);
		
		if($start_time_min !== '' && $start_time_min == $i)
		{
			$start_time_mins .= "<option value=\"{$i}\" selected=\"selected\">{$j}</option>\n";
		}
		else
		{
			$start_time_mins .= "<option value=\"{$i}\">{$j}</option>\n";
		}
	}
	
	$end_time_mins = "<option value=\"\">---</option>\n";
	for($i = 0; $i < 60; $i += 15)
	{
		// Pad the mins with leading 0s
		$j = str_pad($i, 2, '0', STR_PAD_LEFT);
		
		if($end_time_min !== '' && $end_time_min == $i)
		{
			$end_time_mins .= "<option value=\"{$i}\" selected=\"selected\">{$j}</option>\n";
		}
		else
		{
			$end_time_mins .= "<option value=\"{$i}\">{$j}</option>\n";
		}
	}

	$event['recurring'] = $mybb->input['recurring'] == 'yes' || ($mybb->input['recurring'] == '' && !empty($end_day)) ? 1 : 0;

	if($event['recurring'])
	{
		if(is_array($mybb->input['repeat_days']))
		{
			foreach($mybb->input['repeat_days'] as $day=>$value)
			{
				if($value == 1)
				{
					$day_repeat[$day] = ' checked="checked"';
				}
			}
		}
		
		// Construct option list for years
		for($i = my_date('Y'); $i < (my_date('Y') + 5); ++$i)
		{
			if($i == $end_year || (empty($end_year) && $i == $year))
			{
				$end_yearopts .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
			}
			else
			{
				$end_yearopts .= "<option value=\"{$i}\">{$i}</option>\n";
			}
		}

		if(empty($end_month))
		{
			$month_end[$month] = " selected=\"selected\"";
		}
		else
		{
			$month_end[$end_month] = " selected=\"selected\"";
		}

		// Construct option list for days
		for($i = 1; $i <= 31; ++$i)
		{
			if($i == $end_day || ($i == $day + 1))
			{
				$end_dayopts .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
			}
			else
			{
				$end_dayopts .= "<option value=\"{$i}\">{$i}</option>\n";
			}
		}
		
		eval("\$event_dates = \"".$templates->get("calendar_addevent_recurring")."\";");
	}
	else
	{
		eval("\$event_dates = \"".$templates->get("calendar_addevent_normal")."\";");
	}

	if($mybb->input['type'] == 'private' || $mybb->input['private'] == 'yes')
	{
		$privatecheck = " checked=\"checked\"";
		if($mybb->usergroup['canaddprivateevents'] == "no")
		{
			error_no_permission();
		}
	}
	else
	{
		if($mybb->usergroup['canaddpublicevents'] == "no")
		{
			error_no_permission();
		}
	}
	add_breadcrumb($lang->nav_addevent);

	$plugins->run_hooks("calendar_addevent_end");

	eval("\$addevent = \"".$templates->get("calendar_addevent")."\";");
	output_page($addevent);
}

// Process the editing of an event.
if($mybb->input['action'] == "do_editevent" && $mybb->request_method == "post")
{
	$plugins->run_hooks("calendar_do_editevent_start");

	$query = $db->simple_select("events", "*", "eid='{$eid}'");
	$event = $db->fetch_array($query);

	if(!is_numeric($event['author']))
	{
		error($lang->error_invalidevent);
	}
	else if(($event['author'] != $mybb->user['uid'] || $mybb->user['uid'] == 0) && $mybb->usergroup['cancp'] != "yes")
	{
		error_no_permission();
	}

	// Are we going to delete this event or just edit it?
	if($mybb->input['delete'] == "yes")
	{
		$db->delete_query("events", "eid='{$event['eid']}'");

		// Redirect back to the main calendar view.
		redirect("calendar.php", $lang->redirect_eventdeleted);
	}
	else
	{
		// Set up eventhandler.
		require_once MYBB_ROOT."inc/datahandlers/event.php";
		$eventhandler = new EventDataHandler("update");

		// Prepare an array for the eventhandler.
		$event = array(
			"eid" => $eid,
			"subject" => $mybb->input['subject'],
			"uid" => $event['author'],
			"description" => $mybb->input['description'],
			"start_day" => $mybb->input['day'],
			"start_month" => $mybb->input['month'],
			"start_year" => $mybb->input['year'],
			"end_day" => $mybb->input['end_day'],
			"end_month" => $mybb->input['end_month'],
			"end_year" => $mybb->input['end_year'],
			"repeat_days" => $mybb->input['repeat_days'],
			"start_time_hours" => $mybb->input['start_time_hours'],
			"start_time_mins" => $mybb->input['start_time_mins'],
			"end_time_hours" => $mybb->input['end_time_hours'],
			"end_time_mins" => $mybb->input['end_time_mins'],
			"private" => $mybb->input['private']
		);

		$eventhandler->set_data($event);

		// Now let the eventhandler do all the hard work.
		if(!$eventhandler->validate_event())
		{
			$event_errors = $eventhandler->get_friendly_errors();
			$event_errors = inline_error($event_errors);
			$mybb->input['action'] = "editevent";
		}
		else
		{
			$eventhandler->update_event();
			$plugins->run_hooks("calendar_do_editevent_end");
			redirect("calendar.php?action=event&eid=$eid", $lang->redirect_eventupdated);
		}
	}
}

// Show the form for editing an event.
if($mybb->input['action'] == "editevent")
{
	$plugins->run_hooks("calendar_editevent_start");


	// If MyCode is on for this forum and the MyCode editor is enabled inthe Admin CP, draw the code buttons and smilie inserter.
	if($mybb->settings['bbcodeinserter'] != "off" && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
	{
		$codebuttons = build_mycode_inserter();
		$smilieinserter = build_clickable_smilies();
	}

	$eid = intval($mybb->input['eid']);

	$query = $db->simple_select("events", "*", "eid='{$eid}'");
	$event = $db->fetch_array($query);

	if(!$event['eid'])
	{
		error($lang->error_invalidevent);
	}
	else if(($event['author'] != $mybb->user['uid'] || $mybb->user['uid'] == 0) && $mybb->usergroup['cancp'] != "yes")
	{
		error_no_permission();
	}
	
	if($event_errors)
	{
		$event['subject'] = htmlspecialchars_uni($mybb->input['subject']);
		$event['description'] = htmlspecialchars($mybb->input['description']);

		if($mybb->input['private'] == "yes")
		{
			$privatecheck = " checked=\"checked\"";
		}

		$event['repeat_days'] = $mybb->input['repeat_days'];
		$event['start_time_hours'] = $mybb->input['start_time_hours'];
		$event['start_time_mins'] = $mybb->input['start_time_mins'];
		$event['end_time_hours'] = $mybb->input['end_time_hours'];
		$event['end_time_mins'] = $mybb->input['end_time_mins'];
		$event['start_day'] = $mybb->input['day'];
		$event['start_month'] = $mybb->input['month'];
		$event['start_year'] = $mybb->input['year'];
		$event['end_day'] = $mybb->input['end_day'];
		$event['end_month'] = $mybb->input['end_month'];
		$event['end_year'] = $mybb->input['end_year'];
	}
	else
	{
		$event['subject'] = htmlspecialchars_uni($event['subject']);
		$event['description'] = htmlspecialchars_uni($event['description']);

		$privatecheck = '';
		if($event['private'] == "yes")
		{
			$privatecheck = " checked=\"checked\"";
		}
	}
	
	$month_start[$event['start_month']] = " selected=\"selected\"";
	$start_yearopts = '';
	for($i = ($event['start_year'] - 2); $i <= ($event['start_year'] + 2); ++$i)
	{
		if($i == $event['start_year'])
		{
			$start_yearopts .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$start_yearopts .= "<option value=\"{$i}\">{$i}</option>\n";
		}
	}

	$start_dayopts = '';
	for($i = 1; $i <= 31; ++$i)
	{
		if($i == $event['start_day'])
		{
			$start_dayopts .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$start_dayopts .= "<option value=\"{$i}\">{$i}</option>\n";
		}
	}

	// Make hour dropdown menu
	$start_time_hours = "<option value=\"\">---</option>\n";
	for($i = 0; $i <= 23; ++$i)
	{
		// Pad the hours with leading 0s
		$j = str_pad($i, 2, '0', STR_PAD_LEFT);
		if($event['start_time_hours'] !== '' && $event['start_time_hours'] == $j)
		{
			$start_time_hours .= "<option value=\"{$j}\" selected=\"selected\">{$j}</option>\n";
		}
		else
		{
			$start_time_hours .= "<option value=\"{$j}\">{$j}</option>\n";
		}
	}
	$end_time_hours = "<option value=\"\">---</option>\n";
	for($i = 0; $i <= 23; ++$i)
	{
		// Pad the hours with leading 0s
		$j = str_pad($i, 2, '0', STR_PAD_LEFT);
		if($event['end_time_hours'] !== '' && $event['end_time_hours'] == $j)
		{
			$end_time_hours .= "<option value=\"{$j}\" selected=\"selected\">{$j}</option>\n";
		}
		else
		{
			$end_time_hours .= "<option value=\"{$j}\">{$j}</option>\n";
		}
	}

	// Make minute dropdown menu, spaced every 15 minutes
	$start_time_mins = "<option value=\"\">---</option>\n";
	for($i = 0; $i < 60; $i+=15)
	{
		// Pad the mins with leading 0s
		$j = str_pad($i, 2, '0', STR_PAD_LEFT);
		if($event['start_time_mins'] !== '' && $event['start_time_mins'] == $j)
		{
			$start_time_mins .= "<option value=\"{$j}\" selected=\"selected\">{$j}</option>\n";
		}
		else
		{
			$start_time_mins .= "<option value=\"{$j}\">{$j}</option>\n";
		}
	}
	
	$end_time_mins = "<option value=\"\">---</option>\n";
	for($i = 0; $i < 60; $i+=15)
	{
		// Pad the mins with leading 0s
		$j = str_pad($i, 2, '0', STR_PAD_LEFT);
		if($event['end_time_mins'] !== '' && $event['end_time_mins'] == $j)
		{
			$end_time_mins .= "<option value=\"{$j}\" selected=\"selected\">{$j}</option>\n";
		}
		else
		{
			$end_time_mins .= "<option value=\"{$j}\">{$j}</option>\n";
		}
	}

	$event['recurring'] = $mybb->input['recurring'] == 'yes' || ($mybb->input['recurring'] == '' && !empty($event['end_day'])) ? 1 : 0;

	if($event['recurring'])
	{
		$lang->event_edit_make_normal = sprintf($lang->event_edit_make_normal, $eid);
		if(is_array($event['repeat_days']))
		{
			foreach($event['repeat_days'] as $day=>$value)
			{
				$day_repeat[$day] = ' checked="checked"';
			}
		}
		else
		{
			$temp_days = @explode(',', $event['repeat_days']);
			foreach($temp_days as $day=>$value)
			{
				$day_repeat[$value] = ' checked="checked"';
			}
		}
		
		// Construct option list for years
		for($i = my_date('Y'); $i < (my_date('Y') + 5); ++$i)
		{
			if($i == $event['end_year'] || $i == $event['start_year'])
			{
				$end_yearopts .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
			}
			else
			{
				$end_yearopts .= "<option value=\"{$i}\">{$i}</option>\n";
			}
		}

		if(empty($event['end_month']))
		{
			$month_end[$event['start_month']] = " selected=\"selected\"";
		}
		else
		{
			$month_end[$event['end_month']] = " selected=\"selected\"";
		}

		// Construct option list for days
		for($i = 1; $i <= 31; ++$i)
		{
			if($i == $event['end_day'] || ($i == $event['start_day'] + 1))
			{
				$end_dayopts .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
			}
			else
			{
				$end_dayopts .= "<option value=\"{$i}\">{$i}</option>\n";
			}
		}
		
		eval("\$event_dates = \"".$templates->get("calendar_editevent_recurring")."\";");
	}
	else
	{
		$lang->event_edit_make_recurring = sprintf($lang->event_edit_make_recurring, $eid);
		eval("\$event_dates = \"".$templates->get("calendar_editevent_normal")."\";");
	}

	add_breadcrumb($lang->nav_editevent);

	$plugins->run_hooks("calendar_editevent_end");

	eval("\$editevent = \"".$templates->get("calendar_editevent")."\";");
	output_page($editevent);
}


// Show the main calendar view.
if($mybb->input['action'] == "calendar_main")
{
	$plugins->run_hooks("calendar_start");

	$time = mktime(0, 0, 0, $month, 1, $year);
	$days = date("t", $time);
	$bdays = array();

	// Load Birthdays
	// If we have 1st March and this year isn't a leap year, fetch birthdays on the 29th.
	if($month == 3 && my_date("L", gmmktime(0, 0, 0, $month, 1, $year)) != 1)
	{
		$bday_where = "birthday LIKE '%-$month-%' OR birthday LIKE '29-2%' OR birthday LIKE '%-$month'";
		$feb_fix = 1;
	}
	else // Fetch only for this day
	{
		$bday_where = "birthday LIKE '%-$month-%' OR birthday LIKE '%-$month'";
		$feb_fix = 0;
	}

	$query = $db->simple_select("users", "uid, username, birthday, usergroup, displaygroup", $bday_where);
	while($user = $db->fetch_array($query))
	{
		$bday = explode("-", $user['birthday']);
		if($feb_fix == 1 && $bday[0] == 29 && $bday[1] == 2)
		{
			$bdays[1]++;
		}
		else
		{
			$bdays[$bday[0]]++;
		}
	}
	$events = array();
	$this_year = $year;
	$this_month = $month;
	
	// Load Events
	$where = "
		(start_month = '{$month}' AND start_year = '{$year}' AND repeat_days = '')
		OR(
			repeat_days != ''
				AND ( ( start_year < '{$this_year}' ) OR ( start_year = '{$this_year}' AND start_month <= '{$this_month}' ) )
				AND ( ( end_year > '{$this_year}' ) OR ( end_year = '{$this_year}' AND end_month >= '{$this_month}' ) )
		)
	";
	
	$query = $db->simple_select("events", "*", "{$where} AND ((author='{$mybb->user['uid']}' AND private='yes') OR (private!='yes'))");
	while($event = $db->fetch_array($query))
	{
		$event['subject'] = htmlspecialchars_uni($event['subject']);
		$event['fullsubject'] = $event['subject'];
		
		if(my_strlen($event['subject']) > 15)
		{
			$event['subject'] = my_substr($event['subject'], 0, 15) . "...";
		}
		
		if($event['start_time_hours'] !== '' && $event['start_time_mins'] !== '' && $event['end_time_hours'] !== '' && $event['end_time_mins'] !== '')
		{
			$event['subject'] .= " {$event['start_time_hours']}:{$event['start_time_mins']} - {$event['end_time_hours']}:{$event['end_time_mins']}";
		}

		if(empty($event['repeat_days']))
		{
			if($event['private'] == "yes")
			{
				eval("\$events[{$event['start_day']}] .= \"".$templates->get("calendar_eventbit_private")."\";");
			}
			else
			{
				eval("\$events[{$event['start_day']}] .= \"".$templates->get("calendar_eventbit_public")."\";");
			}
		}
		else
		{
			$repeats = explode(',', $event['repeat_days']);
			foreach($repeats as $repeat_day)
			{
				// Find out the first day of the month this particular day falls on
				for($starts_on = 1; $starts_on <= 7; ++$starts_on)
				{
					if(date('w', gmmktime(0, 0, 0, $this_month, $starts_on, $this_year)) == $repeat_day)
					{
						break;
					}
				}

				// Approx 4 weeks in the month
				for($week_no = 0; $week_no < 4; ++$week_no)
				{
					$cur_date = $starts_on + (7 * $week_no);
					if(gmmktime(0, 0, 0, $this_month, $cur_date, $this_year))
					{
						if(($event['start_year'] < $this_year) || ($event['start_year'] == $this_year && $event['start_month'] < $this_month) || ($event['start_year'] == $this_year && $event['start_month'] == $this_month && $cur_date >= $event['start_day']))
						{
							if(($event['end_year'] > $this_year) || ($event['end_year'] == $this_year && $event['end_month'] > $this_month) || ($event['end_year'] == $this_year && $event['end_month'] == $this_month && $cur_date < $event['end_day']))
							{
								if($event['private'] == "yes")
								{
									eval("\$events[{$cur_date}] .= \"".$templates->get("calendar_eventbit_private")."\";");
								}
								else
								{
									eval("\$events[{$cur_date}] .= \"".$templates->get("calendar_eventbit_public")."\";");
								}
							}
						}
					}// End if time is ok (perhaps overshot the end of the month?)
				}// End for 4 weeks in the month
			}
		}
	}
	
	$daybits = "<tr>\n";
	$count = 0;
	$sblanks = date("w", $time);
	
	// Blank space before first day
	if($sblanks)
	{
		$swidth = $sblanks * 14;
		$daybits .= "<td width=\"$swidth%\" colspan=\"$sblanks\" height=\"90\" class=\"trow2\">&nbsp;</td>\n";
		$count += $sblanks;
	}
	
	for($i = 1; $i <= $days; ++$i)
	{
		if(isset($bdays[$i]))
		{
			if($bdays[$i] > 1)
			{
				$birthdays = "<a href=\"calendar.php?action=dayview&amp;year={$year}&amp;month={$month}&amp;day={$i}\">{$bdays[$i]} {$lang->birthdays}</a><br />\n";
			}
			else
			{
				$birthdays = "<a href=\"calendar.php?action=dayview&amp;year={$year}&amp;month={$month}&amp;day={$i}\">{$bdays[$i]} {$lang->birthday}</a><br />\n";
			}
		}
		else
		{
			$birthdays = '';
		}
		
		if(!$events[$i] && !$birthdays)
		{
			$events[$i] = "&nbsp;";
		}
		
		if((my_date("d") == $i) && (my_date("n") == $month) && (my_date("Y") == $year))
		{
			eval("\$daybits .= \"".$templates->get("calendar_daybit_today")."\";");
		}
		else
		{
			eval("\$daybits .= \"".$templates->get("calendar_daybit")."\";");
		}
		++$count;

		if($count == 7)
		{
			if($i != $days)
			{
				$daybits .= "</tr>\n<tr>\n";
			}
			else
			{
				$daybits .= "</tr>\n";
			}
			$count = 0;
		}
		else
		{
			$left = $count + 7;
		}

	}

	// Blank space after last day
	if($count != 0)
	{
		$eblanks = 7 - $count;
	}
	
	if($eblanks)
	{
		$ewidth = $eblanks * 14;
		$daybits .= "<td width=\"$ewidth%\" colspan=\"$eblanks\" height=\"90\" class=\"trow2\" valign=\"top\">&nbsp;</td>\n";
		$daybits .= "</tr>\n";
	}

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


	$yearsel = '';
	for($i = my_date("Y"); $i < (my_date("Y") + 5); ++$i)
	{
		$yearsel .= "<option value=\"$i\">$i</option>\n";
	}
	
	if($mybb->usergroup['canaddpublicevents'] != "no")
	{
		eval("\$addpublicevent = \"".$templates->get("calendar_addpublicevent")."\";");
	}
	
	if($mybb->usergroup['canaddprivateevents'] != "no")
	{
		eval("\$addprivateevent = \"".$templates->get("calendar_addprivateevent")."\";");
	}
	
	if($addpublicevent && $addprivateevent)
	{
		$neweventsep = " | ";
	}

	$plugins->run_hooks("calendar_end");

	eval("\$calendar = \"".$templates->get("calendar")."\";");
	output_page($calendar);
}
?>