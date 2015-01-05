<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'calendar.php');

$templatelist = "calendar_weekdayheader,calendar_weekrow_day,calendar_weekrow,calendar,calendar_addevent,calendar_move,calendar_year,calendar_day,calendar_select,calendar_repeats,calendar_weekview_day_event_time";
$templatelist .= ",calendar_weekview_day,calendar_weekview_day_event,calendar_mini_weekdayheader,calendar_mini_weekrow_day,calendar_mini_weekrow,calendar_mini,calendar_weekview_month,calendar_weekview";
$templatelist .= ",calendar_event_editbutton,calendar_event_modoptions,calendar_dayview_event,calendar_dayview,codebuttons,smilieinsert,smilieinsert_getmore,smilieinsert_smilie,smilieinsert_smilie_empty";
$templatelist .= ",calendar_jump,calendar_jump_option,calendar_editevent,calendar_dayview_birthdays_bday,calendar_dayview_birthdays,calendar_dayview_noevents,calendar_addeventlink,calendar_addevent_calendarselect_hidden";
$templatelist .= ",calendar_weekrow_day_birthdays,calendar_weekview_day_birthdays,calendar_year_sel,calendar_event_userstar,calendar_addevent_calendarselect,calendar_eventbit,calendar_event";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_calendar.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("calendar");

if($mybb->settings['enablecalendar'] == 0)
{
	error($lang->calendar_disabled);
}

if($mybb->usergroup['canviewcalendar'] == 0)
{
	error_no_permission();
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

$plugins->run_hooks("calendar_start");

// Make navigation
add_breadcrumb($lang->nav_calendar, "calendar.php");

$mybb->input['calendar'] = $mybb->get_input('calendar', MyBB::INPUT_INT);
$calendars = cache_calendars();

$calendar_jump = '';
if(count($calendars) > 1)
{
	$calendar_jump = build_calendar_jump($mybb->input['calendar']);
}

$mybb->input['action'] = $mybb->get_input('action');
// Add an event
if($mybb->input['action'] == "do_addevent" && $mybb->request_method == "post")
{
	$query = $db->simple_select("calendars", "*", "cid='{$mybb->input['calendar']}'");
	$calendar = $db->fetch_array($query);

	// Invalid calendar?
	if(!$calendar)
	{
		error($lang->invalid_calendar);
	}

	// Do we have permission to view this calendar or post events?
	$calendar_permissions = get_calendar_permissions($calendar['cid']);
	if($calendar_permissions['canviewcalendar'] != 1 || $calendar_permissions['canaddevents'] != 1)
	{
		error_no_permission();
	}

	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("calendar_do_addevent_start");

	// Set up event handler.
	require_once MYBB_ROOT."inc/datahandler.php";
	require_once MYBB_ROOT."inc/datahandlers/event.php";
	$eventhandler = new EventDataHandler("insert");

	$mybb->input['type'] = $mybb->get_input('type');

	// Prepare an array for the eventhandler.
	$event = array(
		"cid" => $calendar['cid'],
		"uid" => $mybb->user['uid'],
		"name" => $mybb->get_input('name'),
		"description" => $mybb->get_input('description'),
		"private" => $mybb->get_input('private', MyBB::INPUT_INT),
		"type" => $mybb->input['type']
	);

	// Now we add in our date/time info depending on the type of event
	if($mybb->input['type'] == "single")
	{
		$event['start_date'] = array(
			"day" => $mybb->get_input('single_day', MyBB::INPUT_INT),
			"month" => $mybb->get_input('single_month', MyBB::INPUT_INT),
			"year" => $mybb->get_input('single_year', MyBB::INPUT_INT)
		);
		$event['repeats'] = '';
	}
	else if($mybb->input['type'] == "ranged")
	{
		$event['start_date'] = array(
			"day" => $mybb->get_input('start_day', MyBB::INPUT_INT),
			"month" => $mybb->get_input('start_month', MyBB::INPUT_INT),
			"year" => $mybb->get_input('start_year', MyBB::INPUT_INT),
			"time" => $mybb->get_input('start_time')
		);
		$event['end_date'] = array(
			"day" => $mybb->get_input('end_day', MyBB::INPUT_INT),
			"month" => $mybb->get_input('end_month', MyBB::INPUT_INT),
			"year" => $mybb->get_input('end_year', MyBB::INPUT_INT),
			"time" => $mybb->get_input('end_time')
		);
		$event['timezone'] = $mybb->get_input('timezone');
		$event['ignoretimezone'] =	$mybb->get_input('ignoretimezone', MyBB::INPUT_INT);
		$repeats = array();
		switch($mybb->input['repeats'])
		{
			case 1:
				$repeats['repeats'] = 1;
				$repeats['days'] = $mybb->get_input('repeats_1_days', MyBB::INPUT_INT);
				break;
			case 2:
				$repeats['repeats'] = 2;
				break;
			case 3:
				$repeats['repeats'] = 3;
				$repeats['weeks'] = $mybb->get_input('repeats_3_weeks', MyBB::INPUT_INT);
				$mybb->input['repeats_3_days'] = $mybb->get_input('repeats_3_days', MyBB::INPUT_ARRAY);
				ksort($mybb->input['repeats_3_days']);
				$days = array();
				foreach($mybb->input['repeats_3_days'] as $weekday => $value)
				{
					if($value != 1)
					{
						continue;
					}
					$days[] = $weekday;
				}
				$repeats['days'] = $days;
				break;
			case 4:
				$repeats['repeats'] = 4;
				if($mybb->get_input('repeats_4_type', MyBB::INPUT_INT) == 1)
				{
					$repeats['day'] = $mybb->get_input('repeats_4_day', MyBB::INPUT_INT);
					$repeats['months'] = $mybb->get_input('repeats_4_months', MyBB::INPUT_INT);
				}
				else
				{
					$repeats['months'] = $mybb->get_input('repeats_4_months2', MyBB::INPUT_INT);
					$repeats['occurance'] = $mybb->get_input('repeats_4_occurance');
					$repeats['weekday'] = $mybb->get_input('repeats_4_weekday', MyBB::INPUT_INT);
				}
				break;
			case 5:
				$repeats['repeats'] = 5;
				if($mybb->get_input('repeats_5_type', MyBB::INPUT_INT) == 1)
				{
					$repeats['day'] = $mybb->get_input('repeats_5_day', MyBB::INPUT_INT);
					$repeats['month'] = $mybb->get_input('repeats_5_month', MyBB::INPUT_INT);
					$repeats['years'] = $mybb->get_input('repeats_5_years', MyBB::INPUT_INT);
				}
				else
				{
					$repeats['occurance'] = $mybb->get_input('repeats_5_occurance');
					$repeats['weekday'] = $mybb->get_input('repeats_5_weekday', MyBB::INPUT_INT);
					$repeats['month'] = $mybb->get_input('repeats_5_month2', MyBB::INPUT_INT);
					$repeats['years'] = $mybb->get_input('repeats_5_years', MyBB::INPUT_INT);
				}
				break;
			default:
				$repeats['repeats'] = 0;
		}
		$event['repeats'] = $repeats;
	}

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
		if($details['visible'] == 1)
		{
			redirect(get_event_link($details['eid']), $lang->redirect_eventadded);
		}
		else
		{
			redirect(get_calendar_link($event['cid']), $lang->redirect_eventadded_moderation);
		}
	}
}

if($mybb->input['action'] == "addevent")
{
	$query = $db->simple_select("calendars", "*", "cid='".$mybb->input['calendar']."'");
	$calendar = $db->fetch_array($query);

	// Invalid calendar?
	if(!$calendar['cid'])
	{
		error($lang->invalid_calendar);
	}

	// Do we have permission to view this calendar or post events?
	$calendar_permissions = get_calendar_permissions($calendar['cid']);
	if($calendar_permissions['canviewcalendar']  != 1 || $calendar_permissions['canaddevents']  != 1)
	{
		error_no_permission();
	}

	add_breadcrumb(htmlspecialchars_uni($calendar['name']), get_calendar_link($calendar['cid']));
	add_breadcrumb($lang->nav_addevent);

	$plugins->run_hooks("calendar_addevent_start");

	// If MyCode is on for this forum and the MyCode editor is enabled inthe Admin CP, draw the code buttons and smilie inserter.
	if($mybb->settings['bbcodeinserter'] != 0 && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0) && $calendar['allowmycode'] == 1)
	{
		$codebuttons = build_mycode_inserter("message", $calendar['allowsmilies']);
		if($calendar['allowsmilies'] == 1)
		{
			$smilieinserter = build_clickable_smilies();
		}
	}

	// Previous selections
	$name = $description = '';
	if(isset($mybb->input['name']))
	{
		$name = htmlspecialchars_uni($mybb->get_input('name'));
	}

	if(isset($mybb->input['description']))
	{
		$description = htmlspecialchars_uni($mybb->get_input('description'));
	}

	$single_month = $start_month = $end_month = $repeats_sel = $repeats_3_days = $repeats_4_occurance = $repeats_4_weekday = $repeats_5_month = $repeats_5_occurance = $repeats_5_weekday = $repeats_5_month2 = array();
	foreach(range(1, 12) as $number)
	{
		$single_month[$number] = $start_month[$number] = $end_month[$number] = $repeats_5_month[$number] = $repeats_5_month2[$number] = '';
	}
	foreach(range(1, 5) as $number)
	{
		$repeats_sel[$number] = '';
	}
	foreach(range(0, 6) as $number)
	{
		$repeats_3_days[$number] = $repeats_4_weekday[$number] = $repeats_5_weekday[$number] = '';
	}
	foreach(range(1, 4) as $number)
	{
		$repeats_4_occurance[$number] = $repeats_5_occurance[$number] = '';
	}
	$repeats_4_occurance['last'] = $repeats_5_occurance['last'] = '';
	$repeats_4_type = array(1 => '', 2 => '');
	$repeats_5_type = array(1 => '', 2 => '');

	if($mybb->request_method == "post")
	{
		$single_day = $mybb->get_input('single_day', MyBB::INPUT_INT);
		$single_month[$mybb->get_input('single_month', MyBB::INPUT_INT)] = " selected=\"selected\"";
		$single_year = $mybb->get_input('single_year', MyBB::INPUT_INT);
		$start_day = $mybb->get_input('start_day', MyBB::INPUT_INT);
		$start_month[$mybb->get_input('start_month', MyBB::INPUT_INT)] = " selected=\"selected\"";
		$start_year = $mybb->get_input('start_year', MyBB::INPUT_INT);
		$start_time = htmlspecialchars_uni($mybb->get_input('start_time'));
		$end_day = $mybb->get_input('end_day', MyBB::INPUT_INT);
		$end_month[$mybb->get_input('end_month', MyBB::INPUT_INT)] = " selected=\"selected\"";
		$end_year = $mybb->get_input('end_year', MyBB::INPUT_INT);
		$end_time = htmlspecialchars_uni($mybb->get_input('end_time'));
		if($mybb->get_input('type') == "single")
		{
			$type_single = "checked=\"checked\"";
			$type_ranged = '';
			$type = "single";
		}
		else
		{
			$type_ranged = "checked=\"checked\"";
			$type_single = '';
			$type = "ranged";
		}
		if(!empty($mybb->input['repeats']))
		{
			$repeats_sel[$mybb->get_input('repeats', MyBB::INPUT_INT)] = " selected=\"selected\"";
		}
		$repeats_1_days = $mybb->get_input('repeats_1_days', MyBB::INPUT_INT);
		$repeats_3_weeks = $mybb->get_input('repeats_3_weeks', MyBB::INPUT_INT);
		foreach($mybb->get_input('repeats_3_days', MyBB::INPUT_ARRAY) as $day => $val)
		{
			if($val != 1)
			{
				continue;
			}
			$day = (int)$day;
			$repeats_3_days[$day] = " checked=\"checked\"";
		}
		$repeats_4_type = array();
		if($mybb->get_input('repeats_4_type', MyBB::INPUT_INT) == 1)
		{
			$repeats_4_type[1] = "checked=\"checked\"";
			$repeats_4_type[2] = '';
		}
		else
		{
			$repeats_4_type[2] = "checked=\"checked\"";
			$repeats_4_type[1] = '';
		}
		$repeats_4_day = $mybb->get_input('repeats_4_day', MyBB::INPUT_INT);
		$repeats_4_months = $mybb->get_input('repeats_4_months', MyBB::INPUT_INT);
		$repeats_4_occurance[$mybb->get_input('repeats_4_occurance')] = "selected=\"selected\"";
		$repeats_4_weekday[$mybb->get_input('repeats_4_weekday', MyBB::INPUT_INT)] = "selected=\"selected\"";
		$repeats_4_months2 = $mybb->get_input('repeats_4_months2', MyBB::INPUT_INT);
		if($mybb->get_input('repeats_5_type', MyBB::INPUT_INT) == 1)
		{
			$repeats_5_type[1] = "checked=\"checked\"";
		}
		else
		{
			$repeats_5_type[2] = "checked=\"checked\"";
		}
		$repeats_5_day = $mybb->get_input('repeats_5_day', MyBB::INPUT_INT);
		$repeats_5_month[$mybb->get_input('repeats_5_month', MyBB::INPUT_INT)] = "selected=\"selected\"";
		$repeats_5_years = $mybb->get_input('repeats_5_years', MyBB::INPUT_INT);
		$repeats_5_occurance[$mybb->get_input('repeats_5_occurance')] = "selected=\"selected\"";
		$repeats_5_weekday[$mybb->get_input('repeats_5_weekday', MyBB::INPUT_INT)] = "selected=\"selected\"";
		$repeats_5_month2[$mybb->get_input('repeats_5_month2', MyBB::INPUT_INT)] = "selected=\"selected\"";
		$repeats_5_years2 = $mybb->get_input('repeats_5_years2', MyBB::INPUT_INT);

		$timezone = $mybb->get_input('timezone', MyBB::INPUT_INT);
	}
	else
	{
		if(!empty($mybb->input['day']))
		{
			$single_day = $start_day = $end_day = $mybb->get_input('day', MyBB::INPUT_INT);
		}
		else
		{
			$single_day = $start_day = $end_day = my_date("j");
		}
		if(!empty($mybb->input['month']))
		{
			$month = $mybb->get_input('month', MyBB::INPUT_INT);
		}
		else
		{
			$month = my_date("n");
		}
		$single_month[$month] = $start_month[$month] = $end_month[$month] = "selected=\"selected\"";
		if(!empty($mybb->input['year']))
		{
			$single_year = $start_year = $end_year = $mybb->get_input('year', MyBB::INPUT_INT);
		}
		else
		{
			$single_year = $start_year = $end_year = my_date("Y");
		}
		$start_time = $end_time = "";
		$type_single = "checked=\"checked\"";
		$type_ranged = '';
		$type = "single";
		$repeats_1_days = 1;
		$repeats_3_weeks = 1;
		$repeats_4_type[1] = "checked=\"checked\"";
		$repeats_4_day = 1;
		$repeats_4_months = 1;
		$repeats_4_occurance[1] = "selected=\"selected\"";
		$repeats_4_weekday[0] = "selected=\"selected\"";
		$repeats_4_months2 = 1;
		$repeats_5_type[1] = "checked=\"checked\"";
		$repeats_5_day = 1;
		$repeats_5_month[1] = "selected=\"selected\"";
		$repeats_5_years = 1;
		$repeats_5_occurance[1] = "selected=\"selected\"";
		$repeats_5_weekday[0] = "selected=\"selected\"";
		$repeats_5_month2[1] = "selected=\"selected\"";
		$repeats_5_years2 = 1;
		$timezone = $mybb->user['timezone'];
	}

	$single_years = $start_years = $end_years = '';

	// Construct option list for years
	for($year = my_date('Y'); $year < (my_date('Y') + 5); ++$year)
	{
		if($year == $single_year)
		{
			$selected = "selected=\"selected\"";
			eval("\$single_years .= \"".$templates->get("calendar_year")."\";");
		}
		else
		{
			$selected = "";
			eval("\$single_years .= \"".$templates->get("calendar_year")."\";");
		}

		if($year == $start_year)
		{
			$selected = "selected=\"selected\"";
			eval("\$start_years .= \"".$templates->get("calendar_year")."\";");
		}
		else
		{
			$selected = "";
			eval("\$start_years .= \"".$templates->get("calendar_year")."\";");
		}

		if($year == $end_year)
		{
			$selected = "selected=\"selected\"";
			eval("\$end_years .= \"".$templates->get("calendar_year")."\";");
		}
		else
		{
			$selected = "";
			eval("\$end_years .= \"".$templates->get("calendar_year")."\";");
		}
	}

	$single_days = $start_days = $end_days = '';

	// Construct option list for days
	for($day = 1; $day <= 31; ++$day)
	{
		if($day == $single_day)
		{
			$selected = "selected=\"selected\"";
			eval("\$single_days .= \"".$templates->get("calendar_day")."\";");
		}
		else
		{
			$selected = "";
			eval("\$single_days .= \"".$templates->get("calendar_day")."\";");
		}

		if($day == $start_day)
		{
			$selected = "selected=\"selected\"";
			eval("\$start_days .= \"".$templates->get("calendar_day")."\";");
		}
		else
		{
			$selected = "";
			eval("\$start_days .= \"".$templates->get("calendar_day")."\";");
		}

		if($day == $end_day)
		{
			$selected = "selected=\"selected\"";
			eval("\$end_days .= \"".$templates->get("calendar_day")."\";");
		}
		else
		{
			$selected = "";
			eval("\$end_days .= \"".$templates->get("calendar_day")."\";");
		}
	}

	$timezones = build_timezone_select("timezone", $timezone);

	if($mybb->get_input('ignoretimezone', MyBB::INPUT_INT) == 1)
	{
		$ignore_timezone = "checked=\"checked\"";
	}
	else
	{
		$ignore_timezone = '';
	}

	if($mybb->get_input('private', MyBB::INPUT_INT) == 1)
	{
		$privatecheck = " checked=\"checked\"";
	}
	else
	{
		$privatecheck = '';
	}

	$select_calendar = $calendar_select = '';
	$calendarcount = 0;

	// Build calendar select
	$calendar_permissions = get_calendar_permissions();
	$query = $db->simple_select("calendars", "*", "", array("order_by" => "name", "order_dir" => "asc"));
	while($calendar_option = $db->fetch_array($query))
	{
		if($calendar_permissions[$calendar['cid']]['canviewcalendar'] == 1)
		{
			$calendar_option['name'] = htmlspecialchars_uni($calendar_option['name']);
			if($calendar_option['cid'] == $mybb->input['calendar'])
			{
				$selected = " selected=\"selected\"";
			}
			else
			{
				$selected = "";
			}

			++$calendarcount;
			eval("\$select_calendar .= \"".$templates->get("calendar_select")."\";");
		}
	}

	if($calendarcount > 1)
	{
		eval("\$calendar_select .= \"".$templates->get("calendar_addevent_calendarselect")."\";");
	}
	else
	{
		eval("\$calendar_select .= \"".$templates->get("calendar_addevent_calendarselect_hidden")."\";");
	}

	$event_errors = '';

	$plugins->run_hooks("calendar_addevent_end");

	eval("\$addevent = \"".$templates->get("calendar_addevent")."\";");
	output_page($addevent);
}

// Edit an event
if($mybb->input['action'] == "do_editevent" && $mybb->request_method == "post")
{
	$query = $db->simple_select("events", "*", "eid='{$mybb->input['eid']}'");
	$event = $db->fetch_array($query);

	if(!$event)
	{
		error($lang->error_invalidevent);
	}

	$query = $db->simple_select("calendars", "*", "cid='{$event['cid']}'");
	$calendar = $db->fetch_array($query);

	// Invalid calendar?
	if(!$calendar)
	{
		error($lang->invalid_calendar);
	}

	// Do we have permission to view this calendar or post events?
	$calendar_permissions = get_calendar_permissions($calendar['cid']);
	if($calendar_permissions['canviewcalendar'] != 1 || $calendar_permissions['canaddevents'] != 1)
	{
		error_no_permission();
	}

	if(($event['uid'] != $mybb->user['uid'] || $mybb->user['uid'] == 0) && $calendar_permissions['canmoderateevents'] != 1)
	{
		error_no_permission();
	}

	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	// Are we going to delete this event or just edit it?
	if($mybb->get_input('delete', MyBB::INPUT_INT) == 1)
	{
		$db->delete_query("events", "eid='{$event['eid']}'");

		// Redirect back to the main calendar view.
		redirect("calendar.php", $lang->redirect_eventdeleted);
	}

	$plugins->run_hooks("calendar_do_editevent_start");

	// Set up event handler.
	require_once MYBB_ROOT."inc/datahandler.php";
	require_once MYBB_ROOT."inc/datahandlers/event.php";
	$eventhandler = new EventDataHandler("update");
	$mybb->input['type'] = $mybb->get_input('type');

	// Prepare an array for the eventhandler.
	$event = array(
		"eid" => $event['eid'],
		"name" => $mybb->get_input('name'),
		"description" => $mybb->get_input('description'),
		"private" => $mybb->get_input('private', MyBB::INPUT_INT),
		"type" => $mybb->input['type']
	);

	// Now we add in our date/time info depending on the type of event
	if($mybb->input['type'] == "single")
	{
		$event['start_date'] = array(
			"day" => $mybb->get_input('single_day', MyBB::INPUT_INT),
			"month" => $mybb->get_input('single_month', MyBB::INPUT_INT),
			"year" => $mybb->get_input('single_year', MyBB::INPUT_INT)
		);
		$event['repeats'] = '';
	}
	else if($mybb->input['type'] == "ranged")
	{
		$event['start_date'] = array(
			"day" => $mybb->get_input('start_day', MyBB::INPUT_INT),
			"month" => $mybb->get_input('start_month', MyBB::INPUT_INT),
			"year" => $mybb->get_input('start_year', MyBB::INPUT_INT),
			"time" => $mybb->get_input('start_time')
		);
		$event['end_date'] = array(
			"day" => $mybb->get_input('end_day', MyBB::INPUT_INT),
			"month" => $mybb->get_input('end_month', MyBB::INPUT_INT),
			"year" => $mybb->get_input('end_year', MyBB::INPUT_INT),
			"time" => $mybb->get_input('end_time')
		);
		$event['timezone'] = $mybb->get_input('timezone');
		$event['ignoretimezone'] =	$mybb->get_input('ignoretimezone', MyBB::INPUT_INT);
		$repeats = array();
		switch($mybb->input['repeats'])
		{
			case 1:
				$repeats['repeats'] = 1;
				$repeats['days'] = $mybb->get_input('repeats_1_days', MyBB::INPUT_INT);
				break;
			case 2:
				$repeats['repeats'] = 2;
				break;
			case 3:
				$repeats['repeats'] = 3;
				$repeats['weeks'] = $mybb->get_input('repeats_3_weeks', MyBB::INPUT_INT);
				$mybb->input['repeats_3_days'] = $mybb->get_input('repeats_3_days', MyBB::INPUT_ARRAY);
				ksort($mybb->input['repeats_3_days']);
				$days = array();
				foreach($mybb->input['repeats_3_days'] as $weekday => $value)
				{
					if($value != 1)
					{
						continue;
					}
					$days[] = $weekday;
				}
				$repeats['days'] = $days;
				break;
			case 4:
				$repeats['repeats'] = 4;
				if($mybb->get_input('repeats_4_type', MyBB::INPUT_INT) == 1)
				{
					$repeats['day'] = $mybb->get_input('repeats_4_day', MyBB::INPUT_INT);
					$repeats['months'] = $mybb->get_input('repeats_4_months', MyBB::INPUT_INT);
				}
				else
				{
					$repeats['months'] = $mybb->get_input('repeats_4_months2', MyBB::INPUT_INT);
					$repeats['occurance'] = $mybb->get_input('repeats_4_occurance');
					$repeats['weekday'] = $mybb->get_input('repeats_4_weekday', MyBB::INPUT_INT);
				}
				break;
			case 5:
				$repeats['repeats'] = 5;
				if($mybb->get_input('repeats_5_type', MyBB::INPUT_INT) == 1)
				{
					$repeats['day'] = $mybb->get_input('repeats_5_day', MyBB::INPUT_INT);
					$repeats['month'] = $mybb->get_input('repeats_5_month', MyBB::INPUT_INT);
					$repeats['years'] = $mybb->get_input('repeats_5_years', MyBB::INPUT_INT);
				}
				else
				{
					$repeats['occurance'] = $mybb->get_input('repeats_5_occurance');
					$repeats['weekday'] = $mybb->get_input('repeats_5_weekday', MyBB::INPUT_INT);
					$repeats['month'] = $mybb->get_input('repeats_5_month2', MyBB::INPUT_INT);
					$repeats['years'] = $mybb->get_input('repeats_5_years', MyBB::INPUT_INT);
				}
				break;
			default:
				$repeats['repeats'] = 0;
		}
		$event['repeats'] = $repeats;
	}

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
		$details = $eventhandler->update_event();
		$plugins->run_hooks("calendar_do_editevent_end");
		redirect(get_event_link($event['eid']), $lang->redirect_eventupdated);
	}
}

if($mybb->input['action'] == "editevent")
{
	// Event already fetched in do_editevent?
	if(!isset($event))
	{
		$query = $db->simple_select("events", "*", "eid='{$mybb->input['eid']}'");
		$event = $db->fetch_array($query);

		if(!$event)
		{
			error($lang->error_invalidevent);
		}

		$query = $db->simple_select("calendars", "*", "cid='{$event['cid']}'");
		$calendar = $db->fetch_array($query);

		// Invalid calendar?
		if(!$calendar['cid'])
		{
			error($lang->invalid_calendar);
		}

		// Do we have permission to view this calendar or post events?
		$calendar_permissions = get_calendar_permissions($calendar['cid']);
		if($calendar_permissions['canviewcalendar'] != 1 || $calendar_permissions['canaddevents'] != 1)
		{
			error_no_permission();
		}

		if(($event['uid'] != $mybb->user['uid'] || $mybb->user['uid'] == 0) && $calendar_permissions['canmoderateevents'] != 1)
		{
			error_no_permission();
		}
	}

	$event['name'] = htmlspecialchars_uni($event['name']);

	add_breadcrumb(htmlspecialchars_uni($calendar['name']), get_calendar_link($calendar['cid']));
	add_breadcrumb($event['name'], get_event_link($event['eid']));
	add_breadcrumb($lang->nav_editevent);

	$plugins->run_hooks("calendar_editevent_start");

	// If MyCode is on for this forum and the MyCode editor is enabled inthe Admin CP, draw the code buttons and smilie inserter.
	if($mybb->settings['bbcodeinserter'] != 0 && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0) && $calendar['allowmycode'] == 1)
	{
		$codebuttons = build_mycode_inserter("message", $calendar['allowsmilies']);
		if($calendar['allowsmilies'] == 1)
		{
			$smilieinserter = build_clickable_smilies();
		}
	}

	$single_month = $start_month = $end_month = $repeats_sel = $repeats_3_days = $repeats_4_occurance = $repeats_4_weekday = $repeats_5_month = $repeats_5_occurance = $repeats_5_weekday = $repeats_5_month2 = array();
	foreach(range(1, 12) as $number)
	{
		$single_month[$number] = $start_month[$number] = $end_month[$number] = $repeats_5_month[$number] = $repeats_5_month2[$number] = '';
	}
	foreach(range(1, 5) as $number)
	{
		$repeats_sel[$number] = '';
	}
	foreach(range(0, 6) as $number)
	{
		$repeats_3_days[$number] = $repeats_4_weekday[$number] = $repeats_5_weekday[$number] = '';
	}
	foreach(range(1, 4) as $number)
	{
		$repeats_4_occurance[$number] = $repeats_5_occurance[$number] = '';
	}
	$repeats_4_occurance['last'] = $repeats_5_occurance['last'] = '';
	$repeats_4_type = array(1 => '', 2 => '');
	$repeats_5_type = array(1 => '', 2 => '');

	// Previous selections
	if(isset($event_errors))
	{
		$name = htmlspecialchars_uni($mybb->get_input('name'));
		$description = htmlspecialchars_uni($mybb->get_input('description'));
		$single_day = $mybb->get_input('single_day', MyBB::INPUT_INT);
		$single_month[$mybb->get_input('single_month', MyBB::INPUT_INT)] = " selected=\"selected\"";
		$single_year = $mybb->get_input('single_year', MyBB::INPUT_INT);
		$start_day = $mybb->get_input('start_day', MyBB::INPUT_INT);
		$start_month[$mybb->get_input('start_month', MyBB::INPUT_INT)] = " selected=\"selected\"";
		$start_year = $mybb->get_input('start_year', MyBB::INPUT_INT);
		$start_time = htmlspecialchars_uni($mybb->get_input('start_time'));
		$end_day = $mybb->get_input('end_day', MyBB::INPUT_INT);
		$end_month[$mybb->get_input('end_month', MyBB::INPUT_INT)] = " selected=\"selected\"";
		$end_year = $mybb->get_input('end_year', MyBB::INPUT_INT);
		$end_time = htmlspecialchars_uni($mybb->get_input('end_time'));
		if($mybb->get_input('type') == "single")
		{
			$type_single = "checked=\"checked\"";
			$type_ranged = '';
			$type = "single";
		}
		else
		{
			$type_ranged = "checked=\"checked\"";
			$type_single = '';
			$type = "ranged";
		}
		if(!empty($mybb->input['repeats']))
		{
			$repeats_sel[$mybb->get_input('repeats', MyBB::INPUT_INT)] = " selected=\"selected\"";
		}
		$repeats_1_days = $mybb->get_input('repeats_1_days', MyBB::INPUT_INT);
		$repeats_3_weeks = $mybb->get_input('repeats_3_weeks', MyBB::INPUT_INT);
		foreach($mybb->get_input('repeats_3_days', MyBB::INPUT_ARRAY) as $day => $val)
		{
			if($val != 1)
			{
				continue;
			}
			$day = (int)$day;
			$repeats_3_days[$day] = " checked=\"checked\"";
		}
		$repeats_4_type = array();
		if($mybb->get_input('repeats_4_type', MyBB::INPUT_INT) == 1)
		{
			$repeats_4_type[1] = "checked=\"checked\"";
			$repeats_4_type[2] = '';
		}
		else
		{
			$repeats_4_type[2] = "checked=\"checked\"";
			$repeats_4_type[1] = '';
		}
		$repeats_4_day = $mybb->get_input('repeats_4_day', MyBB::INPUT_INT);
		$repeats_4_months = $mybb->get_input('repeats_4_months', MyBB::INPUT_INT);
		$repeats_4_occurance[$mybb->get_input('repeats_4_occurance')] = "selected=\"selected\"";
		$repeats_4_weekday[$mybb->get_input('repeats_4_weekday', MyBB::INPUT_INT)] = "selected=\"selected\"";
		$repeats_4_months2 = $mybb->get_input('repeats_4_months2', MyBB::INPUT_INT);
		if($mybb->get_input('repeats_5_type', MyBB::INPUT_INT) == 1)
		{
			$repeats_5_type[1] = "checked=\"checked\"";
		}
		else
		{
			$repeats_5_type[2] = "checked=\"checked\"";
		}
		$repeats_5_day = $mybb->get_input('repeats_5_day', MyBB::INPUT_INT);
		$repeats_5_month[$mybb->get_input('repeats_5_month', MyBB::INPUT_INT)] = "selected=\"selected\"";
		$repeats_5_years = $mybb->get_input('repeats_5_years', MyBB::INPUT_INT);
		$repeats_5_occurance[$mybb->get_input('repeats_5_occurance')] = "selected=\"selected\"";
		$repeats_5_weekday[$mybb->get_input('repeats_5_weekday', MyBB::INPUT_INT)] = "selected=\"selected\"";
		$repeats_5_month2[$mybb->get_input('repeats_5_month2', MyBB::INPUT_INT)] = "selected=\"selected\"";
		$repeats_5_years2 = $mybb->get_input('repeats_5_years2', MyBB::INPUT_INT);

		if($mybb->get_input('private', MyBB::INPUT_INT) == 1)
		{
			$privatecheck = " checked=\"checked\"";
		}
		else
		{
			$privatecheck = '';
		}

		if($mybb->get_input('ignoretimezone', MyBB::INPUT_INT) == 1)
		{
			$ignore_timezone = "checked=\"checked\"";
		}
		else
		{
			$ignore_timezone = '';
		}

		$timezone = $mybb->get_input('timezone');
	}
	else
	{
		$event_errors = '';
		$mybb->input['calendar'] = $event['cid'];
		$name = htmlspecialchars_uni($event['name']);
		$description = htmlspecialchars_uni($event['description']);
		if($event['private'] == 1)
		{
			$privatecheck = " checked=\"checked\"";
		}
		else
		{
			$privatecheck = '';
		}
		$start_date = explode("-", gmdate("j-n-Y-g:i A", $event['starttime']+$event['timezone']*3600));
		$single_day = $start_date[0];
		$single_month[$start_date[1]] = " selected=\"selected\"";
		$single_year = $start_date[2];
		$start_day = $start_date[0];
		$start_month[$start_date[1]] = " selected=\"selected\"";
		$start_year = $start_date[2];
		if($event['usingtime'])
		{
			$start_time = gmdate($mybb->settings['timeformat'], $event['starttime']+$event['timezone']*3600);
		}
		else
		{
			$start_time = '';
		}
		if($event['endtime'])
		{
			$end_date = explode("-", gmdate("j-n-Y-g:i A", $event['endtime']+$event['timezone']*3600));
			$end_day = $end_date[0];
			$end_month[$end_date[1]] = " selected=\"selected\"";
			$end_year = $end_date[2];
			if($event['usingtime'])
			{
				$end_time = gmdate($mybb->settings['timeformat'], $event['endtime']+$event['timezone']*3600);
			}
			else
			{
				$end_time = '';
			}
			$type_ranged = "checked=\"checked\"";
			$type_single = '';
			$type = "ranged";
			$repeats = my_unserialize($event['repeats']);
			if($repeats['repeats'] >= 0)
			{
				$repeats_sel[$repeats['repeats']] = " selected=\"selected\"";
				switch($repeats['repeats'])
				{
					case 1:
						$repeats_1_days = $repeats['days'];
						$repeats_3_weeks = 1;
						$repeats_4_type[1] = "checked=\"checked\"";
						$repeats_4_day = 1;
						$repeats_4_months = 1;
						$repeats_4_months2 = 1;
						$repeats_5_type[1] = "checked=\"checked\"";
						$repeats_5_day = 1;
						$repeats_5_years = $repeats_5_years2 = 1;
						break;
					case 3:
						$repeats_1_days = 1;
						$repeats_3_weeks = $repeats['weeks'];
						if(is_array($repeats['days']))
						{
							foreach($repeats['days'] as $weekday)
							{
								$repeats_3_days[$weekday] = " checked=\"checked\"";
							}
						}
						$repeats_4_type[1] = "checked=\"checked\"";
						$repeats_4_day = 1;
						$repeats_4_months = 1;
						$repeats_4_months2 = 1;
						$repeats_5_type[1] = "checked=\"checked\"";
						$repeats_5_day = 1;
						$repeats_5_years = $repeats_5_years2 = 1;
						break;
					case 4:
						$repeats_1_days = 1;
						$repeats_3_weeks = 1;
						if($repeats['day'])
						{
							$repeats_4_type[1] = "checked=\"checked\"";
							$repeats_4_day = $repeats['day'];
							$repeats_4_months = $repeats_4_months2 = $repeats['months'];
						}
						else
						{
							$repeats_4_type[2] = "checked=\"checked\"";
							$repeats_4_day = 1;
							$repeats_4_months2 = $repeats_4_months = $repeats['months'];
							$repeats_4_occurance[$repeats['occurance']] = "selected=\"selected\"";
							$repeats_4_weekday[$repeats['weekday']] = "selected=\"selected\"";
						}
						$repeats_5_type[1] = "checked=\"checked\"";
						$repeats_5_day = 1;
						$repeats_5_years = $repeats_5_years2 = 1;
						break;
					case 5:
						$repeats_1_days = 1;
						$repeats_3_weeks = 1;
						$repeats_4_type[1] = "checked=\"checked\"";
						$repeats_4_day = 1;
						$repeats_4_months = 1;
						$repeats_4_months2 = 1;
						if($repeats['day'])
						{
							$repeats_5_type[1] = "checked=\"checked\"";
							$repeats_5_day = $repeats['day'];
							$repeats_5_month[$repeats['month']] = $repeats_5_month2[$repeats['month']] = "selected=\"selected\"";
							$repeats_5_years = $repeats_5_years2 = $repeats['years'];
						}
						else
						{
							$repeats_5_type[2] = "checked=\"checked\"";
							$repeats_5_occurance[$repeats['occurance']] = "selected=\"selected\"";
							$repeats_5_weekday[$repeats['weekday']] = "selected=\"selected\"";
							$repeats_5_month[$repeats['month']] = $repeats_5_month2[$repeats['month']] = "selected=\"selected\"";
							$repeats_5_years = $repeats_5_years2 = $repeats['years'];
						}
						break;
				}
			}
			if($event['ignoretimezone'])
			{
				$timezone = 0;
				$ignore_timezone = "checked=\"checked\"";
			}
			else
			{
				$timezone = $event['timezone'];
				$ignore_timezone = '';
			}
		}
		else
		{
			$type_single = "checked=\"checked\"";
			$type_ranged = $ignore_timezone = $repeats_1_days = $repeats_3_weeks = $repeats_4_day = $repeats_4_months = $repeats_4_months2 = $repeats_5_day = $repeats_5_years = $timezone = $end_time = '';
			$type = "single";
			// set some defaults if the user wants to make a ranged event
			$end_day = $start_day;
			$end_month = $start_month;
			$end_year = $start_year;
		}
	}

	$single_years = $start_years = $end_years = '';

	// Construct option list for years
	for($year = my_date('Y'); $year < (my_date('Y') + 5); ++$year)
	{
		if($year == $single_year)
		{
			$selected = "selected=\"selected\"";
			eval("\$single_years .= \"".$templates->get("calendar_year")."\";");
		}
		else
		{
			$selected = "";
			eval("\$single_years .= \"".$templates->get("calendar_year")."\";");
		}

		if($year == $start_year)
		{
			$selected = "selected=\"selected\"";
			eval("\$start_years .= \"".$templates->get("calendar_year")."\";");
		}
		else
		{
			$selected = "";
			eval("\$start_years .= \"".$templates->get("calendar_year")."\";");
		}

		if($year == $end_year)
		{
			$selected = "selected=\"selected\"";
			eval("\$end_years .= \"".$templates->get("calendar_year")."\";");
		}
		else
		{
			$selected = "";
			eval("\$end_years .= \"".$templates->get("calendar_year")."\";");
		}
	}

	$single_days = $start_days = $end_days = '';

	// Construct option list for days
	for($day = 1; $day <= 31; ++$day)
	{
		if($day == $single_day)
		{
			$selected = "selected=\"selected\"";
			eval("\$single_days .= \"".$templates->get("calendar_day")."\";");
		}
		else
		{
			$selected = "";
			eval("\$single_days .= \"".$templates->get("calendar_day")."\";");
		}

		if($day == $start_day)
		{
			$selected = "selected=\"selected\"";
			eval("\$start_days .= \"".$templates->get("calendar_day")."\";");
		}
		else
		{
			$selected = "";
			eval("\$start_days .= \"".$templates->get("calendar_day")."\";");
		}

		if($day == $end_day)
		{
			$selected = "selected=\"selected\"";
			eval("\$end_days .= \"".$templates->get("calendar_day")."\";");
		}
		else
		{
			$selected = "";
			eval("\$end_days .= \"".$templates->get("calendar_day")."\";");
		}
	}

	$timezones = build_timezone_select("timezone", $timezone);

	$plugins->run_hooks("calendar_editevent_end");

	eval("\$editevent = \"".$templates->get("calendar_editevent")."\";");
	output_page($editevent);
}

// Move an event to another calendar
if($mybb->input['action'] == "move")
{
	$query = $db->simple_select("events", "*", "eid='{$mybb->input['eid']}'");
	$event = $db->fetch_array($query);

	if(!$event)
	{
		error($lang->error_invalidevent);
	}

	$query = $db->simple_select("calendars", "*", "cid='{$event['cid']}'");
	$calendar = $db->fetch_array($query);

	// Invalid calendar?
	if(!$calendar)
	{
		error($lang->invalid_calendar);
	}

	// Do we have permission to view this calendar or post events?
	$calendar_permissions = get_calendar_permissions();
	if($calendar_permissions[$calendar['cid']]['canviewcalendar'] != 1)
	{
		error_no_permission();
	}

	if($calendar_permissions[$calendar['cid']]['canmoderateevents'] != 1)
	{
		error_no_permission();
	}

	$event['name'] = htmlspecialchars_uni($event['name']);

	add_breadcrumb(htmlspecialchars_uni($calendar['name']), get_calendar_link($calendar['cid']));
	add_breadcrumb($event['name'], get_event_link($event['eid']));
	add_breadcrumb($lang->nav_move_event);

	$plugins->run_hooks("calendar_move_start");

	$calendar_select = $selected = '';

	// Build calendar select
	$query = $db->simple_select("calendars", "*", "", array("order_by" => "name", "order_dir" => "asc"));
	while($calendar_option = $db->fetch_array($query))
	{
		if($calendar_permissions[$calendar['cid']]['canviewcalendar'] == 1)
		{
			$calendar_option['name'] = htmlspecialchars_uni($calendar_option['name']);
			eval("\$calendar_select .= \"".$templates->get("calendar_select")."\";");
		}
	}

	$plugins->run_hooks("calendar_move_end");

	eval("\$moveevent = \"".$templates->get("calendar_move")."\";");
	output_page($moveevent);
}

// Actually move the event
if($mybb->input['action'] == "do_move" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$query = $db->simple_select("events", "*", "eid='{$mybb->input['eid']}'");
	$event = $db->fetch_array($query);

	if(!$event)
	{
		error($lang->error_invalidevent);
	}

	$query = $db->simple_select("calendars", "*", "cid='{$event['cid']}'");
	$calendar = $db->fetch_array($query);

	// Invalid calendar?
	if(!$calendar)
	{
		error($lang->invalid_calendar);
	}

	// Do we have permission to view this calendar?
	$calendar_permissions = get_calendar_permissions();
	if($calendar_permissions[$calendar['cid']]['canviewcalendar'] != 1)
	{
		error_no_permission();
	}

	if($calendar_permissions[$calendar['cid']]['canmoderateevents'] != 1)
	{
		error_no_permission();
	}

	$query = $db->simple_select("calendars", "*", "cid='".$mybb->get_input('new_calendar', MyBB::INPUT_INT)."'");
	$new_calendar = $db->fetch_array($query);

	if(!$new_calendar)
	{
		error($lang->invalid_calendar);
	}

	if($calendar_permissions[$mybb->input['new_calendar']]['canviewcalendar'] != 1)
	{
		error_no_permission();
	}

	$updated_event = array(
		"cid" => $new_calendar['cid']
	);

	$plugins->run_hooks("calendar_do_move_start");

	$db->update_query("events", $updated_event, "eid='{$event['eid']}'");

	$plugins->run_hooks("calendar_do_move_end");

	redirect(get_event_link($event['eid']), $lang->redirect_eventmoved);
}

// Approve an event
if($mybb->input['action'] == "approve")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$query = $db->simple_select("events", "*", "eid='{$mybb->input['eid']}'");
	$event = $db->fetch_array($query);

	if(!$event)
	{
		error($lang->error_invalidevent);
	}

	$query = $db->simple_select("calendars", "*", "cid='{$event['cid']}'");
	$calendar = $db->fetch_array($query);

	// Invalid calendar?
	if(!$calendar)
	{
		error($lang->invalid_calendar);
	}

	// Do we have permission to view this calendar?
	$calendar_permissions = get_calendar_permissions($calendar['cid']);
	if($calendar_permissions['canviewcalendar'] != 1)
	{
		error_no_permission();
	}

	if($calendar_permissions['canmoderateevents'] != 1)
	{
		error_no_permission();
	}

	$updated_event = array(
		"visible" => 1
	);

	$plugins->run_hooks("calendar_approve_start");

	$db->update_query("events", $updated_event, "eid='{$event['eid']}'");

	$plugins->run_hooks("calendar_approve_end");

	redirect(get_event_link($event['eid']), $lang->redirect_eventapproved);
}

// Unapprove an event
if($mybb->input['action'] == "unapprove")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$query = $db->simple_select("events", "*", "eid='{$mybb->input['eid']}'");
	$event = $db->fetch_array($query);

	if(!$event)
	{
		error($lang->error_invalidevent);
	}

	$query = $db->simple_select("calendars", "*", "cid='{$event['cid']}'");
	$calendar = $db->fetch_array($query);

	// Invalid calendar?
	if(!$calendar)
	{
		error($lang->invalid_calendar);
	}

	// Do we have permission to view this calendar?
	$calendar_permissions = get_calendar_permissions($calendar['cid']);
	if($calendar_permissions['canviewcalendar'] != 1)
	{
		error_no_permission();
	}

	if($calendar_permissions['canmoderateevents'] != 1)
	{
		error_no_permission();
	}

	$updated_event = array(
		"visible" => 0
	);

	$plugins->run_hooks("calendar_unapprove_start");

	$db->update_query("events", $updated_event, "eid='{$event['eid']}'");

	$plugins->run_hooks("calendar_unapprove_end");

	redirect(get_event_link($event['eid']), $lang->redirect_eventunapproved);
}

// Showing specific event
if($mybb->input['action'] == "event")
{
	$query = $db->query("
		SELECT u.*, e.*
		FROM ".TABLE_PREFIX."events e
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=e.uid)
		WHERE e.eid='{$mybb->input['eid']}'
	");
	$event = $db->fetch_array($query);

	if(!$event || ($event['private'] == 1 && $event['uid'] != $mybb->user['uid']))
	{
		error($lang->error_invalidevent);
	}

	$query = $db->simple_select("calendars", "*", "cid='{$event['cid']}'");
	$calendar = $db->fetch_array($query);

	// Invalid calendar?
	if(!$calendar)
	{
		error($lang->invalid_calendar);
	}

	// Do we have permission to view this calendar?
	$calendar_permissions = get_calendar_permissions($calendar['cid']);
	if($calendar_permissions['canviewcalendar'] != 1 || ($calendar_permissions['canmoderateevents'] != 1 && $event['visible'] == 0))
	{
		error_no_permission();
	}

	$event['name'] = htmlspecialchars_uni($event['name']);

	add_breadcrumb(htmlspecialchars_uni($calendar['name']), get_calendar_link($calendar['cid']));
	add_breadcrumb($event['name'], get_event_link($event['eid']));

	$plugins->run_hooks("calendar_event_start");

	$event_parser_options = array(
		"allow_html" => $calendar['allowhtml'],
		"allow_mycode" => $calendar['allowmycode'],
		"allow_smilies" => $calendar['allowsmilies'],
		"allow_imgcode" => $calendar['allowimgcode'],
		"allow_videocode" => $calendar['allowvideocode']
	);

	if($mybb->user['showimages'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestimages'] != 1 && $mybb->user['uid'] == 0)
	{
		$event_parser_options['allow_imgcode'] = 0;
	}

	if($mybb->user['showvideos'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestvideos'] != 1 && $mybb->user['uid'] == 0)
	{
		$event_parser_options['allow_videocode'] = 0;
	}

	$event['description'] = $parser->parse_message($event['description'], $event_parser_options);

	// Get the usergroup
	if($event['username'])
	{
		if(!$event['displaygroup'])
		{
			$event['displaygroup'] = $event['usergroup'];
		}
		$user_usergroup = $groupscache[$event['displaygroup']];
	}
	else
	{
		$user_usergroup = $groupscache[1];
	}

	$titles_cache = $cache->read("usertitles");

	// Event made by registered user
	if($event['uid'] > 0 && $event['username'])
	{
		$event['profilelink'] = build_profile_link(format_name($event['username'], $event['usergroup'], $event['displaygroup']), $event['uid']);

		$hascustomtitle = 0;
		if(trim($event['usertitle']) != "")
		{
			$hascustomtitle = 1;
		}

		if($user_usergroup['usertitle'] != "" && !$hascustomtitle)
		{
			$event['usertitle'] = $user_usergroup['usertitle'];
		}
		elseif(is_array($titles_cache) && !$user_usergroup['usertitle'])
		{
			reset($titles_cache);
			foreach($titles_cache as $key => $title)
			{
				if($event['postnum'] >= $key)
				{
					if(!$hascustomtitle)
					{
						$event['usertitle'] = $title['title'];
					}
					$event['stars'] = $title['stars'];
					$event['starimage'] = $title['starimage'];
					break;
				}
			}
		}

		if($user_usergroup['stars'])
		{
			$event['stars'] = $user_usergroup['stars'];
		}

		if(empty($event['starimage']))
		{
			$event['starimage'] = $user_usergroup['starimage'];
		}
		$event['starimage'] = str_replace("{theme}", $theme['imgdir'], $event['starimage']);

		$event['userstars'] = '';
		for($i = 0; $i < $event['stars']; ++$i)
		{
			eval("\$event['userstars'] .= \"".$templates->get("calendar_event_userstar", 1, 0)."\";");
		}

		if($event['userstars'] && $event['starimage'] && $event['stars'])
		{
			$event['userstars'] .= "<br />";
		}
	}
	// Created by a guest or an unknown user
	else
	{
		if(!$event['username'])
		{
			$event['username'] = $lang->guest;
		}

		$event['profilelink'] = format_name($event['username'], 1);

		if($user_usergroup['usertitle'])
		{
			$event['usertitle'] = $user_usergroup['usertitle'];
		}
		else
		{
			$event['usertitle'] = $lang->guest;
		}
		$event['userstars'] = '';
	}

	$event['usertitle'] = htmlspecialchars_uni($event['usertitle']); 

	if($event['ignoretimezone'] == 0)
	{
		$offset = $event['timezone'];
	}
	else
	{
		$offset = $mybb->user['timezone'];
	}

	$event['starttime_user'] = $event['starttime']+$offset*3600;

	// Events over more than one day
	$time_period = '';
	if($event['endtime'] > 0 && $event['endtime'] != $event['starttime'])
	{
		$event['endtime_user'] = $event['endtime']+$offset*3600;
		$start_day = gmmktime(0, 0, 0, gmdate("n", $event['starttime_user']), gmdate("j", $event['starttime_user']), gmdate("Y", $event['starttime_user']));
		$end_day = gmmktime(0, 0, 0, gmdate("n", $event['endtime_user']), gmdate("j", $event['endtime_user']), gmdate("Y", $event['endtime_user']));
		$start_time = gmdate("Hi", $event['starttime_user']);
		$end_time = gmdate("Hi", $event['endtime_user']);

		$event['repeats'] = my_unserialize($event['repeats']);

		// Event only runs over one day
		if($start_day == $end_day && $event['repeats']['repeats'] == 0)
		{
			$time_period = gmdate($mybb->settings['dateformat'], $event['starttime_user']);
			// Event runs all day
			if($start_time != 0000 && $end_time != 2359)
			{
				$time_period .= $lang->comma.gmdate($mybb->settings['timeformat'], $event['starttime_user'])." - ".gmdate($mybb->settings['timeformat'], $event['endtime_user']);
			}
			else
			{
				$time_period .= $lang->comma.$lang->all_day;
			}
		}
		else
		{
			$time_period = gmdate($mybb->settings['dateformat'], $event['starttime_user']).", ".gmdate($mybb->settings['timeformat'], $event['starttime_user']);
			$time_period .= " - ";
			$time_period .= gmdate($mybb->settings['dateformat'], $event['endtime_user']).", ".gmdate($mybb->settings['timeformat'], $event['endtime_user']);
		}
	}
	else
	{
		$time_period = gmdate($mybb->settings['dateformat'], $event['starttime_user']);
	}

	$repeats = fetch_friendly_repetition($event);
	if($repeats)
	{
		eval("\$repeats = \"".$templates->get("calendar_repeats")."\";");
	}

	$event_class = '';
	if($calendar_permissions['canmoderateevents'] == 1 || ($mybb->user['uid'] > 0 && $mybb->user['uid'] == $event['uid']))
	{
		eval("\$edit_event = \"".$templates->get("calendar_event_editbutton")."\";");
		if($calendar_permissions['canmoderateevents'] == 1)
		{
			if($event['visible'] == 1)
			{
				$approve = $lang->unapprove_event;
				$approve_value = "unapprove";
			}
			else
			{
				$approve = $lang->approve_event;
				$approve_value = "approve";
			}
			eval("\$moderator_options = \"".$templates->get("calendar_event_modoptions")."\";");
		}

		if($event['visible'] == 0)
		{
			$event_class = " trow_shaded";
		}
	}

	$month = my_date("n");

	$yearsel = '';
	for($year_sel = my_date("Y"); $year_sel < (my_date("Y") + 5); ++$year_sel)
	{
		eval("\$yearsel .= \"".$templates->get("calendar_year_sel")."\";");
	}

	$addevent = '';
	if($mybb->usergroup['canaddevents'] == 1)
	{
		eval("\$addevent = \"".$templates->get("calendar_addeventlink")."\";");
	}

	// Now output the page
	$plugins->run_hooks("calendar_event_end");
	eval("\$event = \"".$templates->get("calendar_event")."\";");
	output_page($event);
}

// View all events on a specific day.
if($mybb->input['action'] == "dayview")
{
	// Showing a particular calendar
	if($mybb->input['calendar'])
	{
		$query = $db->simple_select("calendars", "*", "cid='{$mybb->input['calendar']}'");
		$calendar = $db->fetch_array($query);
	}
	// Showing the default calendar
	else
	{
		$query = $db->simple_select("calendars", "*", "disporder='1'");
		$calendar = $db->fetch_array($query);
	}

	// Invalid calendar?
	if(!$calendar)
	{
		error($lang->invalid_calendar);
	}

	// Do we have permission to view this calendar?
	$calendar_permissions = get_calendar_permissions($calendar['cid']);
	if($calendar_permissions['canviewcalendar']  != 1)
	{
		error_no_permission();
	}

	// Incoming year?
	$mybb->input['year'] = $mybb->get_input('year', MyBB::INPUT_INT);
	if($mybb->input['year'] && $mybb->input['year'] <= my_date("Y")+5)
	{
		$year = $mybb->input['year'];
	}
	else
	{
		$year = my_date("Y");
	}

	// Then the month
	$mybb->input['month'] = $mybb->get_input('month', MyBB::INPUT_INT);
	if($mybb->input['month'] >= 1 && $mybb->input['month'] <= 12)
	{
		$month = $mybb->input['month'];
	}
	else
	{
		$month = my_date("n");
	}

	// And day?
	$mybb->input['day'] = $mybb->get_input('day', MyBB::INPUT_INT);
	if($mybb->input['day'] && $mybb->input['day'] <= gmdate("t", gmmktime(0, 0, 0, $month, 1, $year)))
	{
		$day = $mybb->input['day'];
	}
	else
	{
		$day = my_date("j");
	}

	add_breadcrumb(htmlspecialchars_uni($calendar['name']), get_calendar_link($calendar['cid']));
	add_breadcrumb("$day $monthnames[$month] $year", get_calendar_link($calendar['cid'], $year, $month, $day));

	$plugins->run_hooks("calendar_dayview_start");

	// Load Birthdays for this day
	$birthday_list = $birthdays = '';
	if($calendar['showbirthdays'])
	{
		$birthdays2 = get_birthdays($month, $day);
		$bdayhidden = 0;
		if(is_array($birthdays2))
		{
			foreach($birthdays2 as $birthday)
			{
				if($birthday['birthdayprivacy'] == 'all')
				{
					$bday = explode("-", $birthday['birthday']);
					if($bday[2] && $bday[2] < $year)
					{
						$age = $year - $bday[2];
						$age = " (".$lang->sprintf($lang->years_old, $age).")";
					}
					else
					{
						$age = '';
					}

					$birthday['username'] = format_name($birthday['username'], $birthday['usergroup'], $birthday['displaygroup']);
					$birthday['profilelink'] = build_profile_link($birthday['username'], $birthday['uid']);
					eval("\$birthday_list .= \"".$templates->get("calendar_dayview_birthdays_bday", 1, 0)."\";");
					$comma = $lang->comma;
				}
				else
				{
					++$bdayhidden;
				}
			}
		}
		if($bdayhidden > 0)
		{
			if($birthday_list)
			{
				$birthday_list .= " - ";
			}
			$birthday_list .= "{$bdayhidden} {$lang->birthdayhidden}";
		}
		if($birthday_list)
		{
			$bdaydate = my_date($mybb->settings['dateformat'], gmmktime(0, 0, 0, $month, $day, $year), 0, 0);
			$lang->birthdays_on_day = $lang->sprintf($lang->birthdays_on_day, $bdaydate);
			eval("\$birthdays = \"".$templates->get("calendar_dayview_birthdays", 1, 0)."\";");
		}
	}

	// So now we fetch events for this month
	$start_timestamp = gmmktime(0, 0, 0, $month, $day, $year);
	$end_timestamp = gmmktime(23, 59, 59, $month, $day, $year);

	$events_cache = get_events($calendar, $start_timestamp, $end_timestamp, $calendar_permissions['canmoderateevents']);

	$events = '';
	if(isset($events_cache["$day-$month-$year"]) && is_array($events_cache["$day-$month-$year"]))
	{
		foreach($events_cache["$day-$month-$year"] as $event)
		{
			$event['name'] = htmlspecialchars_uni($event['name']);

			$event_parser_options = array(
				"allow_html" => $calendar['allowhtml'],
				"allow_mycode" => $calendar['allowmycode'],
				"allow_smilies" => $calendar['allowsmilies'],
				"allow_imgcode" => $calendar['allowimgcode'],
				"allow_videocode" => $calendar['allowvideocode']
			);

			if($mybb->user['showimages'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestimages'] != 1 && $mybb->user['uid'] == 0)
			{
				$event_parser_options['allow_imgcode'] = 0;
			}

			if($mybb->user['showvideos'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestvideos'] != 1 && $mybb->user['uid'] == 0)
			{
				$event_parser_options['allow_videocode'] = 0;
			}

			$event['description'] = $parser->parse_message($event['description'], $event_parser_options);

			// Get the usergroup
			if($event['username'])
			{
				if(!$event['displaygroup'])
				{
					$event['displaygroup'] = $event['usergroup'];
				}
				$user_usergroup = $groupscache[$event['displaygroup']];
			}
			else
			{
				$user_usergroup = $groupscache[1];
			}

			$titles_cache = $cache->read("usertitles");

			// Event made by registered user
			if($event['uid'] > 0 && $event['username'])
			{
				$event['profilelink'] = build_profile_link(format_name($event['username'], $event['usergroup'], $event['displaygroup']), $event['uid']);

				$hascustomtitle = 0;
				if(trim($event['usertitle']) != "")
				{
					$hascustomtitle = 1;
				}

				if($user_usergroup['usertitle'] != "" && !$hascustomtitle)
				{
					$event['usertitle'] = $user_usergroup['usertitle'];
				}
				elseif(is_array($titles_cache) && !$user_usergroup['usertitle'])
				{
					reset($titles_cache);
					foreach($titles_cache as $key => $title)
					{
						if($event['postnum'] >= $key)
						{
							if(!$hascustomtitle)
							{
								$event['usertitle'] = $title['title'];
							}
							$event['stars'] = $title['stars'];
							$event['starimage'] = $title['starimage'];
							break;
						}
					}
				}

				if($user_usergroup['stars'])
				{
					$event['stars'] = $user_usergroup['stars'];
				}

				if(empty($event['starimage']))
				{
					$event['starimage'] = $user_usergroup['starimage'];
				}

				$event['userstars'] = '';
				for($i = 0; $i < $event['stars']; ++$i)
				{
					eval("\$event['userstars'] .= \"".$templates->get("calendar_event_userstar", 1, 0)."\";");
				}

				if($event['userstars'] && $event['starimage'] && $event['stars'])
				{
					$event['userstars'] .= "<br />";
				}
			}
			// Created by a guest or an unknown user
			else
			{
				if(!$event['username'])
				{
					$event['username'] = $lang->guest;
				}

				$event['username'] = $event['username'];
				$event['profilelink'] = format_name($event['username'], 1);

				if($user_usergroup['usertitle'])
				{
					$event['usertitle'] = $user_usergroup['usertitle'];
				}
				else
				{
					$event['usertitle'] = $lang->guest;
				}
				$event['userstars'] = '';
			}

			$event['usertitle'] = htmlspecialchars_uni($event['usertitle']); 

			if($event['ignoretimezone'] == 0)
			{
				$offset = $event['timezone'];
			}
			else
			{
				$offset = $mybb->user['timezone'];
			}

			$event['starttime_user'] = $event['starttime']+$offset*3600;

			// Events over more than one day
			$time_period = '';
			if($event['endtime'] > 0 && $event['endtime'] != $event['starttime'])
			{
				$event['endtime_user'] = $event['endtime']+$offset*3600;
				$start_day = gmmktime(0, 0, 0, gmdate("n", $event['starttime_user']), gmdate("j", $event['starttime_user']), gmdate("Y", $event['starttime_user']));
				$end_day = gmmktime(0, 0, 0, gmdate("n", $event['endtime_user']), gmdate("j", $event['endtime_user']), gmdate("Y", $event['endtime_user']));
				$start_time = gmdate("Hi", $event['starttime_user']);
				$end_time = gmdate("Hi", $event['endtime_user']);

				// Event only runs over one day
				if($start_day == $end_day && $event['repeats']['repeats'] == 0)
				{
					$time_period = gmdate($mybb->settings['dateformat'], $event['starttime_user']);
					// Event runs all day
					if($start_time != 0000 && $end_time != 2359)
					{
						$time_period .= $lang->comma.gmdate($mybb->settings['timeformat'], $event['starttime_user'])." - ".gmdate($mybb->settings['timeformat'], $event['endtime_user']);
					}
					else
					{
						$time_period .= $lang->comma.$lang->all_day;
					}
				}
				else
				{
					$time_period = gmdate($mybb->settings['dateformat'], $event['starttime_user']).", ".gmdate($mybb->settings['timeformat'], $event['starttime_user']);
					$time_period .= " - ";
					$time_period .= gmdate($mybb->settings['dateformat'], $event['endtime_user']).", ".gmdate($mybb->settings['timeformat'], $event['endtime_user']);
				}
			}
			else
			{
				$time_period = gmdate($mybb->settings['dateformat'], $event['starttime_user']);
			}

			$repeats = fetch_friendly_repetition($event);
			if($repeats)
			{
				eval("\$repeats = \"".$templates->get("calendar_repeats")."\";");
			}

			$edit_event = $moderator_options = $event_class = "";
			if($calendar_permissions['canmoderateevents'] == 1 || ($mybb->user['uid'] > 0 && $mybb->user['uid'] == $event['uid']))
			{
				eval("\$edit_event = \"".$templates->get("calendar_event_editbutton")."\";");
				if($calendar_permissions['canmoderateevents'] == 1)
				{
					if($event['visible'] == 1)
					{
						$approve = $lang->unapprove_event;
						$approve_value = "unapprove";
					}
					else
					{
						$approve = $lang->approve_event;
						$approve_value = "approve";
					}
					eval("\$moderator_options = \"".$templates->get("calendar_event_modoptions")."\";");
				}
				if($event['visible'] == 0)
				{
					$event_class = " trow_shaded";
				}
			}
			eval("\$events .= \"".$templates->get("calendar_dayview_event")."\";");
		}
	}

	$yearsel = '';
	for($year_sel = my_date("Y"); $year_sel < (my_date("Y") + 5); ++$year_sel)
	{
		eval("\$yearsel .= \"".$templates->get("calendar_year_sel")."\";");
	}

	$addevent = '';
	if($mybb->usergroup['canaddevents'] == 1)
	{
		eval("\$addevent = \"".$templates->get("calendar_addeventlink")."\";");
	}

	if(!$events)
	{
		$lang->no_events = $lang->sprintf($lang->no_events, $calendar['cid'], $day, $month, $year);
		eval("\$events = \"".$templates->get("calendar_dayview_noevents")."\";");
	}

	// Now output the page
	$plugins->run_hooks("calendar_dayview_end");

	eval("\$day_view = \"".$templates->get("calendar_dayview")."\";");
	output_page($day_view);
}

// View all events for a specific week
if($mybb->input['action'] == "weekview")
{
	// Showing a particular calendar
	if($mybb->input['calendar'])
	{
		$query = $db->simple_select("calendars", "*", "cid='{$mybb->input['calendar']}'");
		$calendar = $db->fetch_array($query);
	}
	// Showing the default calendar
	else
	{
		$query = $db->simple_select("calendars", "*", "disporder='1'");
		$calendar = $db->fetch_array($query);
	}

	// Invalid calendar?
	if(!$calendar)
	{
		error($lang->invalid_calendar);
	}

	// Do we have permission to view this calendar?
	$calendar_permissions = get_calendar_permissions($calendar['cid']);
	if($calendar_permissions['canviewcalendar']  != 1)
	{
		error_no_permission();
	}

	$weekdays = fetch_weekday_structure($calendar['startofweek']);

	$yearsel = '';
	for($year_sel = my_date("Y"); $year_sel < (my_date("Y") + 5); ++$year_sel)
	{
		eval("\$yearsel .= \"".$templates->get("calendar_year_sel")."\";");
	}

	// No incoming week, show THIS week
	if(empty($mybb->input['week']))
	{
		list($day, $month, $year) = explode("-", my_date("j-n-Y"));
		$php_weekday = gmdate("w", gmmktime(0, 0, 0, $month, $day, $year));
		$my_weekday = array_search($php_weekday, $weekdays);
		// So now we have the start day of this week to show
		$start_day = $day-$my_weekday;
		$mybb->input['week'] = gmmktime(0, 0, 0, $month, $start_day, $year);
	}
	else
	{
		$mybb->input['week'] = (int)str_replace("n", "-", $mybb->get_input('week'));
		// No negative years please ;)
		if($mybb->input['week'] < -62167219200)
		{
			$mybb->input['week'] = -62167219200;
		}
	}

	// This is where we've come from and where we're headed
	$week_from = explode("-", gmdate("j-n-Y", $mybb->input['week']));
	$week_from_one = $week_from[1];
	$friendly_week_from = gmdate($mybb->settings['dateformat'], $mybb->input['week']);
	$week_to_stamp = gmmktime(0, 0, 0, $week_from[1], $week_from[0]+6, $week_from[2]);
	$week_to = explode("-", gmdate("j-n-Y-t", $week_to_stamp));
	$friendly_week_to = gmdate($mybb->settings['dateformat'], $week_to_stamp);

	add_breadcrumb(htmlspecialchars_uni($calendar['name']), get_calendar_link($calendar['cid']));
	add_breadcrumb("{$monthnames[$week_from[1]]} {$week_from[2]}", get_calendar_link($calendar['cid'], $week_from[2], $week_from[1]));
	add_breadcrumb($lang->weekly_overview);

	$plugins->run_hooks("calendar_weekview_start");

	// Establish if we have a month ending in this week
	if($week_from[1] != $week_to[1])
	{
		$different_months = true;
		$week_months = array(array($week_from[1], $week_from[2]), array($week_to[1], $week_to[2]));
		$bday_months = array($week_from[1], $week_to[1]);
	}
	else
	{
		$week_months = array(array($week_from[1], $week_from[2]));
		$bday_months = array($week_from[1]);
	}

	// Load Birthdays for this month
	if($calendar['showbirthdays'] == 1)
	{
		$birthdays = get_birthdays($bday_months);
	}

	// We load events for the entire month date range - for our mini calendars too
	$events_from = gmmktime(0, 0, 0, $week_from[1], 1, $week_from[2]);
	$events_to = gmmktime(0, 0, 0, $week_to[1], $week_to[3], $week_to[2]);

	$events_cache = get_events($calendar, $events_from, $events_to, $calendar_permissions['canmoderateevents']);

	$today = my_date("dnY");

	$next_week = $mybb->input['week'] + 604800;
	$next_link = get_calendar_week_link($calendar['cid'], $next_week);
	$prev_week = $mybb->input['week'] - 604800;
	$prev_link = get_calendar_week_link($calendar['cid'], $prev_week);

	$weekday_date = $mybb->input['week'];

	while($weekday_date <= $week_to_stamp)
	{
		$weekday = gmdate("w", $weekday_date);
		$weekday_name = fetch_weekday_name($weekday);
		$weekday_month = gmdate("n", $weekday_date);
		$weekday_year = gmdate("Y", $weekday_date);
		$weekday_day = gmdate("j", $weekday_date);

		// Special shading for today
		$day_shaded = '';
		if(gmdate("dnY", $weekday_date) == $today)
		{
			$day_shaded = ' trow_shaded';
		}

		$day_events = '';

		// Any events on this specific day?
		if(is_array($events_cache) && array_key_exists("{$weekday_day}-{$weekday_month}-{$weekday_year}", $events_cache))
		{
			foreach($events_cache["$weekday_day-$weekday_month-$weekday_year"] as $event)
			{
				$event['eventlink'] = get_event_link($event['eid']);
				$event['name'] = htmlspecialchars_uni($event['name']);
				$event['fullname'] = $event['name'];
				if(my_strlen($event['name']) > 50)
				{
					$event['name'] = my_substr($event['name'], 0, 50) . "...";
				}
				// Events over more than one day
				$time_period = '';
				if($event['endtime'] > 0 && $event['endtime'] != $event['starttime'])
				{
					$start_day = gmmktime(0, 0, 0, gmdate("n", $event['starttime_user']), gmdate("j", $event['starttime_user']), gmdate("Y", $event['starttime_user']));
					$end_day = gmmktime(0, 0, 0, gmdate("n", $event['endtime_user']), gmdate("j", $event['endtime_user']), gmdate("Y", $event['endtime_user']));
					$start_time = gmdate("Hi", $event['starttime_user']);
					$end_time = gmdate("Hi", $event['endtime_user']);
					// Event only runs over one day
					if($start_day == $end_day || $event['repeats'] > 0)
					{
						// Event runs all day
						if($start_time == 0000 && $end_time == 2359)
						{
							$time_period = $lang->all_day;
						}
						else
						{
							$time_period = gmdate($mybb->settings['timeformat'], $event['starttime_user'])." - ".gmdate($mybb->settings['timeformat'], $event['endtime_user']);
						}
					}
					// Event starts on this day
					else if($start_day == $weekday_date)
					{
						// Event runs all day
						if($start_time == 0000)
						{
							$time_period = $lang->all_day;
						}
						else
						{
							$time_period = $lang->starts.gmdate($mybb->settings['timeformat'], $event['starttime_user']);
						}
					}
					// Event finishes on this day
					else if($end_day == $weekday_date)
					{
						// Event runs all day
						if($end_time == 2359)
						{
							$time_period = $lang->all_day;
						}
						else
						{
							$time_period = $lang->finishes.gmdate($mybb->settings['timeformat'], $event['endtime_user']);
						}
					}
					// Event is in the middle
					else
					{
						$time_period = $lang->all_day;
					}
				}
				$event_time = '';
				if($time_period)
				{
					eval("\$event_time = \"".$templates->get("calendar_weekview_day_event_time")."\";");
				}
				if($event['private'] == 1)
				{
					$event_class = " private_event";
				}
				else
				{
					$event_class = " public_event";
				}
				if($event['visible'] == 0)
				{
					$event_class .= " trow_shaded";
				}
				eval("\$day_events .= \"".$templates->get("calendar_weekview_day_event")."\";");
			}
		}

		// Birthdays on this day?
		$day_birthdays = $calendar_link = $birthday_lang = '';
		if($calendar['showbirthdays'] && is_array($birthdays) && array_key_exists("{$weekday_day}-{$weekday_month}", $birthdays))
		{
			$bday_count = count($birthdays["$weekday_day-$weekday_month"]);
			if($bday_count > 1)
			{
				$birthday_lang = $lang->birthdays;
			}
			else
			{
				$birthday_lang = $lang->birthday;
			}

			$calendar_link = get_calendar_link($calendar['cid'], $weekday_year, $weekday_month, $weekday_day);
			eval("\$day_birthdays = \"".$templates->get("calendar_weekview_day_birthdays")."\";");
		}

		$day_link = get_calendar_link($calendar['cid'], $weekday_year, $weekday_month, $weekday_day);
		if(!isset($day_bits[$weekday_month]))
		{
			$day_bits[$weekday_month] = '';
		}
		eval("\$day_bits[$weekday_month] .= \"".$templates->get("calendar_weekview_day")."\";");
		$day_events = $day_birthdays = "";
		$weekday_date = gmmktime(0, 0, 0, $weekday_month, $weekday_day+1, $weekday_year);
	}

	// Now we build our month headers
	$mini_calendars = $weekday_bits = '';
	foreach($week_months as $month)
	{
		$weekday_month = $monthnames[$month[0]];
		$weekday_year = $month[1];

		// Fetch mini calendar for each month in this week
		$mini_calendars .= build_mini_calendar($calendar, $month[0], $weekday_year, $events_cache)."<br />";

		// Fetch out the days for this month
		$days = $day_bits[$month[0]];

		eval("\$weekday_bits .= \"".$templates->get("calendar_weekview_month")."\";");
	}

	$addevent = '';
	if($mybb->usergroup['canaddevents'] == 1)
	{
		eval("\$addevent = \"".$templates->get("calendar_addeventlink")."\";");
	}

	// Now output the page
	$plugins->run_hooks("calendar_weekview_end");

	eval("\$weekview = \"".$templates->get("calendar_weekview")."\";");
	output_page($weekview);
}

// Showing a calendar
if(!$mybb->input['action'])
{
	// Showing a particular calendar
	if($mybb->input['calendar'])
	{
		$query = $db->simple_select("calendars", "*", "cid='{$mybb->input['calendar']}'");
		$calendar = $db->fetch_array($query);
	}
	// Showing the default calendar
	else
	{
		$query = $db->simple_select("calendars", "*", "", array('order_by' => 'disporder', 'limit' => 1));
		$calendar = $db->fetch_array($query);
	}

	// Invalid calendar?
	if(!$calendar['cid'])
	{
		error($lang->invalid_calendar);
	}

	// Do we have permission to view this calendar?
	$calendar_permissions = get_calendar_permissions($calendar['cid']);

	if($calendar_permissions['canviewcalendar'] != 1)
	{
		error_no_permission();
	}

	$plugins->run_hooks("calendar_main_view");

	// Incoming year?
	$mybb->input['year'] = $mybb->get_input('year', MyBB::INPUT_INT);
	if($mybb->input['year'] && $mybb->input['year'] <= my_date("Y")+5)
	{
		$year = $mybb->input['year'];
	}
	else
	{
		$year = my_date("Y");
	}

	// Then the month
	$mybb->input['month'] = $mybb->get_input('month', MyBB::INPUT_INT);
	if($mybb->input['month'] >= 1 && $mybb->input['month'] <= 12)
	{
		$month = $mybb->input['month'];
	}
	else
	{
		$month = my_date("n");
	}

	add_breadcrumb(htmlspecialchars_uni($calendar['name']), get_calendar_link($calendar['cid']));
	add_breadcrumb("$monthnames[$month] $year", get_calendar_link($calendar['cid'], $year, $month));

	$next_month = get_next_month($month, $year);
	$prev_month = get_prev_month($month, $year);

	$prev_link = get_calendar_link($calendar['cid'], $prev_month['year'], $prev_month['month']);
	$next_link = get_calendar_link($calendar['cid'], $next_month['year'], $next_month['month']);

	// Start constructing the calendar

	$weekdays = fetch_weekday_structure($calendar['startofweek']);

	$month_start_weekday = gmdate("w", gmmktime(0, 0, 0, $month, $calendar['startofweek']+1, $year));

	$prev_month_days = gmdate("t", gmmktime(0, 0, 0, $prev_month['month'], 1, $prev_month['year']));

	// This is if we have days in the previous month to show
	if($month_start_weekday != $weekdays[0] || $calendar['startofweek'] != 0)
	{
		$prev_days = $day = gmdate("t", gmmktime(0, 0, 0, $prev_month['month'], 1, $prev_month['year']));
		$day -= array_search(($month_start_weekday), $weekdays);
		$day += $calendar['startofweek']+1;
		if($day > $prev_month_days+1)
		{
			// Go one week back
			$day -= 7;
		}
		$calendar_month = $prev_month['month'];
		$calendar_year = $prev_month['year'];
	}
	else
	{
		$day = $calendar['startofweek']+1;
		$calendar_month = $month;
		$calendar_year = $year;
	}

	// So now we fetch events for this month (nb, cache events for past month, current month and next month for mini calendars too)
	$start_timestamp = gmmktime(0, 0, 0, $calendar_month, $day, $calendar_year);
	$num_days = gmdate("t", gmmktime(0, 0, 0, $month, 1, $year));

	$month_end_weekday = gmdate("w", gmmktime(0, 0, 0, $month, $num_days, $year));
	$next_days = 6-$month_end_weekday+$calendar['startofweek'];

	// More than a week? Go one week back
	if($next_days >= 7)
	{
		$next_days -= 7;
	}
	if($next_days > 0)
	{
		$end_timestamp = gmmktime(23, 59, 59, $next_month['month'], $next_days, $next_month['year']);
	}
	else
	{
		// We don't need days from the next month
		$end_timestamp = gmmktime(23, 59, 59, $month, $num_days, $year);
	}

	$events_cache = get_events($calendar, $start_timestamp, $end_timestamp, $calendar_permissions['canmoderateevents']);

	// Fetch birthdays
	if($calendar['showbirthdays'])
	{
		$bday_months = array($month, $prev_month['month'], $next_month['month']);
		$birthdays = get_birthdays($bday_months);
	}

	$today = my_date("dnY");
	$weekday_headers = '';

	// Build weekday headers
	foreach($weekdays as $weekday)
	{
		$weekday_name = fetch_weekday_name($weekday);
		eval("\$weekday_headers .= \"".$templates->get("calendar_weekdayheader")."\";");
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

			$day_events = '';

			// Any events on this specific day?
			if(is_array($events_cache) && array_key_exists("{$day}-{$calendar_month}-{$calendar_year}", $events_cache))
			{
				$total_events = count($events_cache["$day-$calendar_month-$calendar_year"]);
				if($total_events > $calendar['eventlimit'] && $calendar['eventlimit'] != 0)
				{
					if($total_events > 1)
					{
						$day_events = "<div style=\"margin-bottom: 4px;\"><a href=\"".get_calendar_link($calendar['cid'], $calendar_year, $calendar_month, $day)."\" class=\"smalltext\">{$total_events} {$lang->events}</a></div>\n";
					}
					else
					{
						$day_events = "<div style=\"margin-bottom: 4px;\"><a href=\"".get_calendar_link($calendar['cid'], $calendar_year, $calendar_month, $day)."\" class=\"smalltext\">1 {$lang->event}</a></div>\n";
					}
				}
				else
				{
					foreach($events_cache["$day-$calendar_month-$calendar_year"] as $event)
					{
						$event['eventlink'] = get_event_link($event['eid']);
						$event['fullname'] = htmlspecialchars_uni($event['name']);
						if(my_strlen($event['name']) > 15)
						{
							$event['name'] = my_substr($event['name'], 0, 15) . "...";
						}
						$event['name'] = htmlspecialchars_uni($event['name']);
						if($event['private'] == 1)
						{
							$event_class = " private_event";
						}
						else
						{
							$event_class = " public_event";
						}
						if($event['visible'] == 0)
						{
							$event_class .= " trow_shaded";
						}
						eval("\$day_events .= \"".$templates->get("calendar_eventbit")."\";");
					}
				}
			}

			// Birthdays on this day?
			$day_birthdays = $birthday_lang = '';
			if($calendar['showbirthdays'] && is_array($birthdays) && array_key_exists("$day-$calendar_month", $birthdays))
			{
				$bday_count = count($birthdays["$day-$calendar_month"]);
				if($bday_count > 1)
				{
					$birthday_lang = $lang->birthdays;
				}
				else
				{
					$birthday_lang = $lang->birthday;
				}

				$calendar['link'] = get_calendar_link($calendar['cid'], $calendar_year, $calendar_month, $day);
				eval("\$day_birthdays = \"".$templates->get("calendar_weekrow_day_birthdays")."\";");
			}

			$day_link = get_calendar_link($calendar['cid'], $calendar_year, $calendar_month, $day);

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
			eval("\$day_bits .= \"".$templates->get("calendar_weekrow_day")."\";");
			$day_birthdays = $day_events = "";
			++$day;
		}
		if($day_bits)
		{
			eval("\$calendar_rows .= \"".$templates->get("calendar_weekrow")."\";");
		}
		$day_bits = "";
	}

	$yearsel = '';
	for($year_sel = my_date("Y"); $year_sel < (my_date("Y") + 5); ++$year_sel)
	{
		eval("\$yearsel .= \"".$templates->get("calendar_year_sel")."\";");
	}

	$addevent = '';
	if($mybb->usergroup['canaddevents'] == 1)
	{
		eval("\$addevent = \"".$templates->get("calendar_addeventlink")."\";");
	}

	$plugins->run_hooks("calendar_end");

	eval("\$calendar = \"".$templates->get("calendar")."\";");
	output_page($calendar);
}
