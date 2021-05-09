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

$templatelist = "calendar_weekdayheader,calendar_weekrow_day,calendar_weekrow,calendar,calendar_addevent,calendar_year,calendar_day,calendar_select,calendar_repeats,calendar_weekview_day_event_time,calendar_weekview_nextlink";
$templatelist .= ",calendar_weekview_day,calendar_weekview_day_event,calendar_mini_weekdayheader,calendar_mini_weekrow_day,calendar_mini_weekrow,calendar_mini,calendar_mini_weekrow_day_link,calendar_weekview_prevlink";
$templatelist .= ",calendar_event_editbutton,calendar_event_modoptions,calendar_dayview_event,calendar_dayview,codebuttons,calendar_weekrow_day_events,calendar_weekview_month,calendar_addeventlink,calendar_weekview";
$templatelist .= ",calendar_jump,calendar_jump_option,calendar_editevent,calendar_dayview_birthdays_bday,calendar_dayview_birthdays,calendar_dayview_noevents,calendar_addevent_calendarselect_hidden,calendar_nextlink";
$templatelist .= ",calendar_weekrow_day_birthdays,calendar_weekview_day_birthdays,calendar_year_sel,calendar_event_userstar,calendar_addevent_calendarselect,calendar_eventbit,calendar_event,calendar_move,calendar_prevlink";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_calendar.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_time.php";
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
		$event['ignoretimezone'] = $mybb->get_input('ignoretimezone', MyBB::INPUT_INT);
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
	if($calendar_permissions['canviewcalendar'] != 1 || $calendar_permissions['canaddevents'] != 1)
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
	if(isset($mybb->input['name']))
	{
		$select['name'] = $mybb->get_input('name');
	}

	if(isset($mybb->input['description']))
	{
		$select['description'] = $mybb->get_input('description');
	}

	$select['single_month'] = $select['start_month'] = $select['end_month'] = $select['repeats_sel'] = $select['repeats_3_days'] = $select['repeats_4_occurance'] = $select['repeats_4_weekday'] = $select['repeats_5_month'] = $select['repeats_5_occurance'] = $select['repeats_5_weekday'] = $select['repeats_5_month2'] = array();
	foreach(range(1, 12) as $number)
	{
		$select['single_month'][$number] = $select['start_month'][$number] = $select['end_month'][$number] = $select['repeats_5_month'][$number] = $select['repeats_5_month2'][$number] = '';
	}
	foreach(range(1, 5) as $number)
	{
		$select['repeats_sel'][$number] = '';
	}
	foreach(range(0, 6) as $number)
	{
		$select['repeats_3_days'][$number] = $select['repeats_4_weekday'][$number] = $select['repeats_5_weekday'][$number] = '';
	}
	foreach(range(1, 4) as $number)
	{
		$select['repeats_4_occurance'][$number] = $select['repeats_5_occurance'][$number] = '';
	}

	$select['repeats_4_occurance']['last'] = $select['repeats_5_occurance']['last'] = '';
	$select['repeats_4_type'] = array(1 => '', 2 => '');
	$select['repeats_5_type'] = array(1 => '', 2 => '');

	if($mybb->request_method == "post")
	{
		$select['single_day'][$mybb->get_input('single_day', MyBB::INPUT_INT)] = true;
		$select['single_month'][$mybb->get_input('single_month', MyBB::INPUT_INT)] = true;
		$select['single_year'][$mybb->get_input('single_year', MyBB::INPUT_INT)] = true;
		$select['start_day'][$mybb->get_input('start_day', MyBB::INPUT_INT)] = true;
		$select['start_month'][$mybb->get_input('start_month', MyBB::INPUT_INT)] = true;
		$select['start_year'][$mybb->get_input('start_year', MyBB::INPUT_INT)] = true;
		$select['start_time'] = $mybb->get_input('start_time');
		$select['end_day'][$mybb->get_input('end_day', MyBB::INPUT_INT)] = true;
		$select['end_month'][$mybb->get_input('end_month', MyBB::INPUT_INT)] = true;
		$select['end_year'][$mybb->get_input('end_year', MyBB::INPUT_INT)] = true;
		$select['end_time'] = $mybb->get_input('end_time');

		if($mybb->get_input('type') == "single")
		{
			$select['type_single'] = true;
			$select['type_ranged'] = '';
			$select['type'] = "single";
		}
		else
		{
			$select['type_ranged'] = true;
			$select['type_single'] = '';
			$select['type'] = "ranged";
		}

		if(!empty($mybb->input['repeats']))
		{
			$select['repeats_sel'][$mybb->get_input('repeats', MyBB::INPUT_INT)] = true;
		}

		$select['repeats_1_days'] = $mybb->get_input('repeats_1_days', MyBB::INPUT_INT);
		$select['repeats_3_weeks'] = $mybb->get_input('repeats_3_weeks', MyBB::INPUT_INT);
		foreach($mybb->get_input('repeats_3_days', MyBB::INPUT_ARRAY) as $day => $val)
		{
			if($val != 1)
			{
				continue;
			}
			$day = (int)$day;
			$select['repeats_3_days'][$day] = true;
		}

		$select['repeats_4_type'] = array();
		if($mybb->get_input('repeats_4_type', MyBB::INPUT_INT) == 1)
		{
			$select['repeats_4_type'][1] = true;
			$select['repeats_4_type'][2] = '';
		}
		else
		{
			$select['repeats_4_type'][2] = true;
			$select['repeats_4_type'][1] = '';
		}

		$select['repeats_4_day'] = $mybb->get_input('repeats_4_day', MyBB::INPUT_INT);
		$select['repeats_4_months'] = $mybb->get_input('repeats_4_months', MyBB::INPUT_INT);
		$select['repeats_4_occurance'][$mybb->get_input('repeats_4_occurance')] = true;
		$select['repeats_4_weekday'][$mybb->get_input('repeats_4_weekday', MyBB::INPUT_INT)] = true;
		$select['repeats_4_months2'] = $mybb->get_input('repeats_4_months2', MyBB::INPUT_INT);

		if($mybb->get_input('repeats_5_type', MyBB::INPUT_INT) == 1)
		{
			$select['repeats_5_type'][1] = true;
		}
		else
		{
			$select['repeats_5_type'][2] = true;
		}

		$select['repeats_5_day'] = $mybb->get_input('repeats_5_day', MyBB::INPUT_INT);
		$select['repeats_5_month'][$mybb->get_input('repeats_5_month', MyBB::INPUT_INT)] = true;
		$select['repeats_5_years'] = $mybb->get_input('repeats_5_years', MyBB::INPUT_INT);
		$select['repeats_5_occurance'][$mybb->get_input('repeats_5_occurance')] = true;
		$select['repeats_5_weekday'][$mybb->get_input('repeats_5_weekday', MyBB::INPUT_INT)] = true;
		$select['repeats_5_month2'][$mybb->get_input('repeats_5_month2', MyBB::INPUT_INT)] = true;
		$select['repeats_5_years2'] = $mybb->get_input('repeats_5_years2', MyBB::INPUT_INT);

		$timezone = $mybb->get_input('timezone', MyBB::INPUT_INT);
	}
	else
	{
		if(!empty($mybb->input['day']))
		{
			$day = $mybb->get_input('day', MyBB::INPUT_INT);
		}
		else
		{
			$day = my_date("j");
		}
		$select['single_day'][$day] = $select['start_day'][$day] = $select['end_day'][$day] = true;

		if(!empty($mybb->input['month']))
		{
			$month = $mybb->get_input('month', MyBB::INPUT_INT);
		}
		else
		{
			$month = my_date("n");
		}
		$select['single_month'][$month] = $select['start_month'][$month] = $select['end_month'][$month] = true;

		if(!empty($mybb->input['year']))
		{
			$year = $mybb->get_input('year', MyBB::INPUT_INT);
		}
		else
		{
			$year = my_date("Y");
		}
		$select['single_year'][$year] = $select['start_year'][$year] = $select['end_year'][$year] = true;

		$select['start_time'] = $select['end_time'] = '';
		$select['type_single'] = true;
		$select['type_ranged'] = '';
		$select['type'] = "single";
		$select['repeats_1_days'] = 1;
		$select['repeats_3_weeks'] = 1;
		$select['repeats_4_type'][1] = true;
		$select['repeats_4_day'] = 1;
		$select['repeats_4_months'] = 1;
		$select['repeats_4_occurance'][1] = true;
		$select['repeats_4_weekday'][0] = true;
		$select['repeats_4_months2'] = 1;
		$select['repeats_5_type'][1] = true;
		$select['repeats_5_day'] = 1;
		$select['repeats_5_month'][1] = true;
		$select['repeats_5_years'] = 1;
		$select['repeats_5_occurance'][1] = true;
		$select['repeats_5_weekday'][0] = true;
		$select['repeats_5_month2'][1] = true;
		$select['repeats_5_years2'] = 1;
		$timezone = $mybb->user['timezone'];
	}

	$days = [];

	// Construct option list for days
	for($day_count = 1; $day_count <= 31; ++$day_count)
	{
		$day_sel['day'] = $day_count;

		if($day_count == $select['single_day'][$day_count])
		{
			$day_sel['single_day'] = true;
		}
		else
		{
			$day_sel['single_day'] = '';
		}

		if($day_count == $select['start_day'][$day_count])
		{
			$day_sel['start_day'] = true;
		}
		else
		{
			$day_sel['start_day'] = '';
		}

		if($day_count == $select['end_day'][$day_count])
		{
			$day_sel['end_day'] = true;
		}
		else
		{
			$day_sel['end_day'] = '';
		}

		$days[] = $day_sel;
	}

	$months = [];

	// Construct option list for months
	for($month_count = 1; $month_count <= 12; ++$month_count)
	{
		$month_sel['month'] = $month_count;

		$lang_string = 'month_'.$month_count;
		$month_sel['name'] = $lang->{$lang_string};

		if($month_count == $select['single_month'][$month_count])
		{
			$month_sel['single_month'] = true;
		}
		else
		{
			$month_sel['single_month'] = '';
		}

		if($month_count == $select['start_month'][$month_count])
		{
			$month_sel['start_month'] = true;
		}
		else
		{
			$month_sel['start_month'] = '';
		}

		if($month_count == $select['end_month'][$month_count])
		{
			$month_sel['end_month'] = true;
		}
		else
		{
			$month_sel['end_month'] = '';
		}

		if($month_count == $select['repeats_5_month'][$month_count])
		{
			$month_sel['repeats_5_month'] = true;
		}
		else
		{
			$month_sel['repeats_5_month'] = '';
		}

		if($month_count == $select['repeats_5_month2'][$month_count])
		{
			$month_sel['repeats_5_month2'] = true;
		}
		else
		{
			$month_sel['repeats_5_month2'] = '';
		}
		$months[] = $month_sel;
	}

	$years = [];

	// Construct option list for years
	for($year_count = my_date('Y'); $year_count < (my_date('Y') + 5); ++$year_count)
	{
		$year_sel['year'] = $year_count;

		if($year_count == $select['single_year'][$year_count])
		{
			$year_sel['single_year'] = true;
		}
		else
		{
			$year_sel['single_year'] = '';
		}

		if($year_count == $select['start_year'][$year_count])
		{
			$year_sel['start_year'] = true;
		}
		else
		{
			$year_sel['start_year'] = '';
		}

		if($year_count == $select['end_year'][$year_count])
		{
			$year_sel['end_year'] = true;
		}
		else
		{
			$year_sel['end_year'] = '';
		}

		$years[] = $year_sel;
	}

	$timezones = build_timezone_select("timezone", $timezone);

	if($mybb->get_input('ignoretimezone', MyBB::INPUT_INT) == 1)
	{
		$select['ignore_timezone'] = true;
	}
	else
	{
		$select['ignore_timezone'] = '';
	}

	if($mybb->get_input('private', MyBB::INPUT_INT) == 1)
	{
		$select['privatecheck'] = true;
	}
	else
	{
		$select['privatecheck'] = '';
	}

	$calendar_select = [];
	$calendarcount = 0;

	// Build calendar select
	$calendar_permissions = get_calendar_permissions();
	$query = $db->simple_select("calendars", "*", "", array("order_by" => "name", "order_dir" => "asc"));
	while($calendar_option = $db->fetch_array($query))
	{
		if($calendar_permissions[$calendar['cid']]['canviewcalendar'] == 1)
		{
			if($calendar_option['cid'] == $mybb->input['calendar'])
			{
				$calendar_option['selected'] = true;
			}
			else
			{
				$calendar_option['selected'] = '';
			}

			++$calendarcount;
			$calendar_select[] = $calendar_option;
		}
	}

	if(!isset($event_errors))
	{
		$event_errors = '';
	}

	$plugins->run_hooks("calendar_addevent_end");

	output_page(\MyBB\template('calendar/addevent.twig', [
		'calendar' => $calendar,
		'codebuttons' => $codebuttons,
		'smilieinserter' => $smilieinserter,
		'select' => $select,
		'event_errors' => $event_errors,
		'calendarcount' => $calendarcount,
		'calendar_select' => $calendar_select,
		'days' => $days,
		'months' => $months,
		'years' => $years,
		'timezones' => $timezones,
	]));
}

// Delete an event
if($mybb->input['action'] == "do_deleteevent" && $mybb->request_method == "post")
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

	$plugins->run_hooks("calendar_do_deleteevent_start");

	// Is the checkbox set?
	if($mybb->get_input('delete', MyBB::INPUT_INT) == 1)
	{
		$db->delete_query("events", "eid='{$event['eid']}'");
		$plugins->run_hooks("calendar_do_deleteevent_end");

		// Redirect back to the main calendar view.
		redirect("calendar.php", $lang->redirect_eventdeleted);
	}
	else
	{
		error($lang->delete_no_checkbox);
	}
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
		$event['ignoretimezone'] = $mybb->get_input('ignoretimezone', MyBB::INPUT_INT);
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
	$event['timezone'] = (float)$event['timezone'];

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

	$select['single_month'] = $select['start_month'] = $select['end_month'] = $select['repeats_sel'] = $select['repeats_3_days'] = $select['repeats_4_occurance'] = $select['repeats_4_weekday'] = $select['repeats_5_month'] = $select['repeats_5_occurance'] = $select['repeats_5_weekday'] = $select['repeats_5_month2'] = array();
	foreach(range(1, 12) as $number)
	{
		$select['single_month'][$number] = $select['start_month'][$number] = $select['end_month'][$number] = $select['repeats_5_month'][$number] = $select['repeats_5_month2'][$number] = '';
	}
	foreach(range(1, 5) as $number)
	{
		$select['repeats_sel'][$number] = '';
	}
	foreach(range(0, 6) as $number)
	{
		$select['repeats_3_days'][$number] = $select['repeats_4_weekday'][$number] = $select['repeats_5_weekday'][$number] = '';
	}
	foreach(range(1, 4) as $number)
	{
		$select['repeats_4_occurance'][$number] = $select['repeats_5_occurance'][$number] = '';
	}
	$select['repeats_4_occurance']['last'] = $select['repeats_5_occurance']['last'] = '';
	$select['repeats_4_type'] = array(1 => '', 2 => '');
	$select['repeats_5_type'] = array(1 => '', 2 => '');

	// Previous selections
	if(isset($event_errors))
	{
		$select['name'] = $mybb->get_input('name');
		$select['description'] = $mybb->get_input('description');
		$select['single_day'][$mybb->get_input('single_day', MyBB::INPUT_INT)] = true;
		$select['single_month'][$mybb->get_input('single_month', MyBB::INPUT_INT)] = true;
		$select['single_year'][$mybb->get_input('single_year', MyBB::INPUT_INT)] = true;
		$select['start_day'][$mybb->get_input('start_day', MyBB::INPUT_INT)] = true;
		$select['start_month'][$mybb->get_input('start_month', MyBB::INPUT_INT)] = true;
		$select['start_year'][$mybb->get_input('start_year', MyBB::INPUT_INT)] = true;
		$select['start_time'] = $mybb->get_input('start_time');
		$select['end_day'][$mybb->get_input('end_day', MyBB::INPUT_INT)] = true;
		$select['end_month'][$mybb->get_input('end_month', MyBB::INPUT_INT)] = true;
		$select['end_year'][$mybb->get_input('end_year', MyBB::INPUT_INT)] = true;
		$select['end_time'] = $mybb->get_input('end_time');

		if($mybb->get_input('type') == "single")
		{
			$select['type_single'] = true;
			$select['type_ranged'] = '';
			$select['type'] = "single";
		}
		else
		{
			$select['type_ranged'] = true;
			$select['type_single'] = '';
			$select['type'] = "ranged";
		}

		if(!empty($mybb->input['repeats']))
		{
			$select['repeats_sel'][$mybb->get_input('repeats', MyBB::INPUT_INT)] = true;
		}

		$select['repeats_1_days'] = $mybb->get_input('repeats_1_days', MyBB::INPUT_INT);
		$select['repeats_3_weeks'] = $mybb->get_input('repeats_3_weeks', MyBB::INPUT_INT);

		foreach($mybb->get_input('repeats_3_days', MyBB::INPUT_ARRAY) as $day => $val)
		{
			if($val != 1)
			{
				continue;
			}
			$day = (int)$day;
			$select['repeats_3_days'][$day] = true;
		}

		$select['repeats_4_type'] = array();
		if($mybb->get_input('repeats_4_type', MyBB::INPUT_INT) == 1)
		{
			$select['repeats_4_type'][1] = true;
			$select['repeats_4_type'][2] = '';
		}
		else
		{
			$select['repeats_4_type'][2] = true;
			$select['repeats_4_type'][1] = '';
		}

		$select['repeats_4_day'] = $mybb->get_input('repeats_4_day', MyBB::INPUT_INT);
		$select['repeats_4_months'] = $mybb->get_input('repeats_4_months', MyBB::INPUT_INT);
		$select['repeats_4_occurance'][$mybb->get_input('repeats_4_occurance')] = true;
		$select['repeats_4_weekday'][$mybb->get_input('repeats_4_weekday', MyBB::INPUT_INT)] = true;
		$select['repeats_4_months2'] = $mybb->get_input('repeats_4_months2', MyBB::INPUT_INT);

		if($mybb->get_input('repeats_5_type', MyBB::INPUT_INT) == 1)
		{
			$select['repeats_5_type'][1] = true;
		}
		else
		{
			$select['repeats_5_type'][2] = true;
		}

		$select['repeats_5_day'] = $mybb->get_input('repeats_5_day', MyBB::INPUT_INT);
		$select['repeats_5_month'][$mybb->get_input('repeats_5_month', MyBB::INPUT_INT)] = true;
		$select['repeats_5_years'] = $mybb->get_input('repeats_5_years', MyBB::INPUT_INT);
		$select['repeats_5_occurance'][$mybb->get_input('repeats_5_occurance')] = true;
		$select['repeats_5_weekday'][$mybb->get_input('repeats_5_weekday', MyBB::INPUT_INT)] = true;
		$select['repeats_5_month2'][$mybb->get_input('repeats_5_month2', MyBB::INPUT_INT)] = true;
		$select['repeats_5_years2'] = $mybb->get_input('repeats_5_years2', MyBB::INPUT_INT);

		if($mybb->get_input('private', MyBB::INPUT_INT) == 1)
		{
			$select['privatecheck'] = true;
		}
		else
		{
			$select['privatecheck'] = '';
		}

		if($mybb->get_input('ignoretimezone', MyBB::INPUT_INT) == 1)
		{
			$select['ignore_timezone'] = true;
		}
		else
		{
			$select['ignore_timezone'] = '';
		}

		$timezone = $mybb->get_input('timezone');
	}
	else
	{
		$event_errors = '';
		$mybb->input['calendar'] = $event['cid'];
		$select['name'] = $event['name'];
		$select['description'] = $event['description'];

		if($event['private'] == 1)
		{
			$select['privatecheck'] = true;
		}
		else
		{
			$select['privatecheck'] = '';
		}

		$start_date = explode("-", gmdate("j-n-Y", $event['starttime'] + $event['timezone'] * 3600));
		$select['single_day'][$start_date[0]] = true;
		$select['single_month'][$start_date[1]] = true;
		$select['single_year'][$start_date[2]] = true;
		$select['start_day'][$start_date[0]] = true;
		$select['start_month'][$start_date[1]] = true;
		$select['start_year'][$start_date[2]] = true;

		if($event['usingtime'])
		{
			$select['start_time'] = gmdate($mybb->settings['timeformat'], $event['starttime'] + $event['timezone'] * 3600);
		}
		else
		{
			$select['start_time'] = '';
		}

		if($event['endtime'])
		{
			$end_date = explode("-", gmdate("j-n-Y", $event['endtime'] + $event['timezone'] * 3600));
			$select['end_day'][$end_date[0]] = true;
			$select['end_month'][$end_date[1]] = true;
			$select['end_year'][$end_date[2]] = true;

			if($event['usingtime'])
			{
				$select['end_time'] = gmdate($mybb->settings['timeformat'], $event['endtime'] + $event['timezone'] * 3600);
			}
			else
			{
				$select['end_time'] = '';
			}

			$select['type_ranged'] = true;
			$select['type_single'] = '';
			$select['type'] = "ranged";
			$repeats = my_unserialize($event['repeats']);

			if($repeats['repeats'] >= 0)
			{
				$select['repeats_sel'][$repeats['repeats']] = true;
				switch($repeats['repeats'])
				{
					case 1:
						$select['repeats_1_days'] = $repeats['days'];
						$select['repeats_3_weeks'] = 1;
						$select['repeats_4_type'][1] = true;
						$select['repeats_4_day'] = 1;
						$select['repeats_4_months'] = 1;
						$select['repeats_4_months2'] = 1;
						$select['repeats_5_type'][1] = true;
						$select['repeats_5_day'] = 1;
						$select['repeats_5_years'] = $select['repeats_5_years2'] = 1;
						break;
					case 3:
						$select['repeats_1_days'] = 1;
						$select['repeats_3_weeks'] = $repeats['weeks'];
						if(is_array($repeats['days']))
						{
							foreach($repeats['days'] as $weekday)
							{
								$select['repeats_3_days'][$weekday] = true;
							}
						}
						$select['repeats_4_type'][1] = true;
						$select['repeats_4_day'] = 1;
						$select['repeats_4_months'] = 1;
						$select['repeats_4_months2'] = 1;
						$select['repeats_5_type'][1] = true;
						$select['repeats_5_day'] = 1;
						$select['repeats_5_years'] = $select['repeats_5_years2'] = 1;
						break;
					case 4:
						$select['repeats_1_days'] = 1;
						$select['repeats_3_weeks'] = 1;
						if($repeats['day'])
						{
							$select['repeats_4_type'][1] = true;
							$select['repeats_4_day'] = $repeats['day'];
							$select['repeats_4_months'] = $select['repeats_4_months2'] = $repeats['months'];
						}
						else
						{
							$select['repeats_4_type'][2] = true;
							$select['repeats_4_day'] = 1;
							$select['repeats_4_months2'] = $select['repeats_4_months'] = $repeats['months'];
							$select['repeats_4_occurance'][$repeats['occurance']] = true;
							$select['repeats_4_weekday'][$repeats['weekday']] = true;
						}
						$select['repeats_5_type'][1] = true;
						$select['repeats_5_day'] = 1;
						$select['repeats_5_years'] = $select['repeats_5_years2'] = 1;
						break;
					case 5:
						$select['repeats_1_days'] = 1;
						$select['repeats_3_weeks'] = 1;
						$select['repeats_4_type'][1] = true;
						$select['repeats_4_day'] = 1;
						$select['repeats_4_months'] = 1;
						$select['repeats_4_months2'] = 1;
						if($repeats['day'])
						{
							$select['repeats_5_type'][1] = true;
							$select['repeats_5_day'] = $repeats['day'];
							$select['repeats_5_month'][$repeats['month']] = $select['repeats_5_month2'][$repeats['month']] = true;
							$select['repeats_5_years'] = $select['repeats_5_years2'] = $repeats['years'];
						}
						else
						{
							$select['repeats_5_type'][2] = true;
							$select['repeats_5_occurance'][$repeats['occurance']] = true;
							$select['repeats_5_weekday'][$repeats['weekday']] = true;
							$select['repeats_5_month'][$repeats['month']] = $select['repeats_5_month2'][$repeats['month']] = true;
							$select['repeats_5_years'] = $select['repeats_5_years2'] = $repeats['years'];
						}
						break;
				}
			}

			if($event['ignoretimezone'])
			{
				$timezone = 0;
				$select['ignore_timezone'] = true;
			}
			else
			{
				$timezone = $event['timezone'];
				$select['ignore_timezone'] = '';
			}
		}
		else
		{
			$select['type_single'] = true;
			$select['type_ranged'] = $select['ignore_timezone'] = $select['repeats_1_days'] = $select['repeats_3_weeks'] = $select['repeats_4_day'] = $select['repeats_4_months'] = $select['repeats_4_months2'] = $select['repeats_5_day'] = $select['repeats_5_years'] = $timezone = $select['end_time'] = '';
			$select['type'] = "single";
			// set some defaults if the user wants to make a ranged event
			$select['end_day'] = $select['start_day'];
			$select['end_month'] = $select['start_month'];
			$select['end_year'] = $select['start_year'];
		}
	}

	$days = [];

	// Construct option list for days
	for($day_count = 1; $day_count <= 31; ++$day_count)
	{
		$day_sel['day'] = $day_count;

		if($day_count == $select['single_day'][$day_count])
		{
			$day_sel['single_day'] = true;
		}
		else
		{
			$day_sel['single_day'] = '';
		}

		if($day_count == $select['start_day'][$day_count])
		{
			$day_sel['start_day'] = true;
		}
		else
		{
			$day_sel['start_day'] = '';
		}

		if($day_count == $select['end_day'][$day_count])
		{
			$day_sel['end_day'] = true;
		}
		else
		{
			$day_sel['end_day'] = '';
		}

		$days[] = $day_sel;
	}
	$months = [];

	// Construct option list for months
	for($month_count = 1; $month_count <= 12; ++$month_count)
	{
		$month_sel['month'] = $month_count;

		$lang_string = 'month_'.$month_count;
		$month_sel['name'] = $lang->{$lang_string};

		if($month_count == $select['single_month'][$month_count])
		{
			$month_sel['single_month'] = true;
		}
		else
		{
			$month_sel['single_month'] = '';
		}

		if($month_count == $select['start_month'][$month_count])
		{
			$month_sel['start_month'] = true;
		}
		else
		{
			$month_sel['start_month'] = '';
		}

		if($month_count == $select['end_month'][$month_count])
		{
			$month_sel['end_month'] = true;
		}
		else
		{
			$month_sel['end_month'] = '';
		}

		if($month_count == $select['repeats_5_month'][$month_count])
		{
			$month_sel['repeats_5_month'] = true;
		}
		else
		{
			$month_sel['repeats_5_month'] = '';
		}

		if($month_count == $select['repeats_5_month2'][$month_count])
		{
			$month_sel['repeats_5_month2'] = true;
		}
		else
		{
			$month_sel['repeats_5_month2'] = '';
		}
		$months[] = $month_sel;
	}

	$years = [];

	// Construct option list for years
	for($year_count = my_date('Y'); $year_count < (my_date('Y') + 5); ++$year_count)
	{
		$year_sel['year'] = $year_count;

		if($year_count == $select['single_year'][$year_count])
		{
			$year_sel['single_year'] = true;
		}
		else
		{
			$year_sel['single_year'] = '';
		}

		if($year_count == $select['start_year'][$year_count])
		{
			$year_sel['start_year'] = true;
		}
		else
		{
			$year_sel['start_year'] = '';
		}

		if($year_count == $select['end_year'][$year_count])
		{
			$year_sel['end_year'] = true;
		}
		else
		{
			$year_sel['end_year'] = '';
		}

		$years[] = $year_sel;
	}

	$timezones = build_timezone_select("timezone", $timezone);

	$plugins->run_hooks("calendar_editevent_end");

	output_page(\MyBB\template('calendar/editevent.twig', [
		'event' => $event,
		'codebuttons' => $codebuttons,
		'smilieinserter' => $smilieinserter,
		'select' => $select,
		'event_errors' => $event_errors,
		'calendarcount' => $calendarcount,
		'calendar_select' => $calendar_select,
		'days' => $days,
		'months' => $months,
		'years' => $years,
		'timezones' => $timezones,
	]));
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

	add_breadcrumb(htmlspecialchars_uni($calendar['name']), get_calendar_link($calendar['cid']));
	add_breadcrumb($event['name'], get_event_link($event['eid']));
	add_breadcrumb($lang->nav_move_event);

	$plugins->run_hooks("calendar_move_start");

	$calendar_select = [];

	// Build calendar select
	$query = $db->simple_select("calendars", "*", "", array("order_by" => "name", "order_dir" => "asc"));
	while($calendar_option = $db->fetch_array($query))
	{
		if($calendar_permissions[$calendar['cid']]['canviewcalendar'] == 1)
		{
			$calendar_select[] = $calendar_option;
		}
	}

	$plugins->run_hooks("calendar_move_end");

	output_page(\MyBB\template('calendar/move.twig', [
		'event' => $event,
		'calendar_select' => $calendar_select,
	]));
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

	if($mybb->user['uid'] != 0 && $mybb->user['showimages'] != 1 || $mybb->settings['guestimages'] != 1 && $mybb->user['uid'] == 0)
	{
		$event_parser_options['allow_imgcode'] = 0;
	}

	if($mybb->user['uid'] != 0 && $mybb->user['showvideos'] != 1 || $mybb->settings['guestvideos'] != 1 && $mybb->user['uid'] == 0)
	{
		$event_parser_options['allow_videocode'] = 0;
	}

	$event['description'] = $parser->parse_message($event['description'], $event_parser_options);

	// Get the usergroup
	if($event['usergroup'])
	{
		$user_usergroup = usergroup_permissions($event['usergroup']);
	}
	else
	{
		$user_usergroup = usergroup_permissions(1);
	}
	$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
	if(!$event['displaygroup'])
	{
		$event['displaygroup'] = $event['usergroup'];
	}
	$display_group = usergroup_displaygroup($event['displaygroup']);
	if(is_array($display_group))
	{
		$user_usergroup = array_merge($user_usergroup, $display_group);
	}

	$titles_cache = $cache->read("usertitles");

	// Event made by registered user
	if($event['uid'] > 0 && $event['username'])
	{
		$event['username'] = htmlspecialchars_uni($event['username']);
		$event['profilelink'] = build_profile_link(format_name($event['username'], $event['usergroup'], $event['displaygroup']), $event['uid']);

		if(trim($event['usertitle']) != "")
		{
			// Do nothing, no need for an extra variable..
		}
		elseif($user_usergroup['usertitle'] != "")
		{
			$event['usertitle'] = $user_usergroup['usertitle'];
		}
		elseif(is_array($titles_cache) && !$user_usergroup['usertitle'])
		{
			reset($titles_cache);
			foreach($titles_cache as $title)
			{
				if($event['postnum'] >= $title['posts'])
				{
					$event['usertitle'] = $title['title'];
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
	}
	else
	{
		// Created by a guest or an unknown user
		if(!$event['username'])
		{
			$event['username'] = $lang->guest;
		}

		$event['username'] = htmlspecialchars_uni($event['username']);
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
		$offset = (float)$event['timezone'];
	}
	else
	{
		$offset = (float)$mybb->user['timezone'];
	}

	$event['starttime_user'] = $event['starttime'] + $offset * 3600;

	// Events over more than one day
	if($event['endtime'] > 0 && $event['endtime'] != $event['starttime'])
	{
		$event['endtime_user'] = $event['endtime'] + $offset * 3600;
		$start_day = adodb_gmmktime(0, 0, 0, gmdate("n", $event['starttime_user']), gmdate("j", $event['starttime_user']), gmdate("Y", $event['starttime_user']));
		$end_day = adodb_gmmktime(0, 0, 0, gmdate("n", $event['endtime_user']), gmdate("j", $event['endtime_user']), gmdate("Y", $event['endtime_user']));
		$start_time = gmdate("Hi", $event['starttime_user']);
		$end_time = gmdate("Hi", $event['endtime_user']);

		$event['repeats'] = my_unserialize($event['repeats']);

		// Event only runs over one day
		if($start_day == $end_day && $event['repeats']['repeats'] == 0)
		{
			$event['time_period'] = gmdate($mybb->settings['dateformat'], $event['starttime_user']);
			// Event runs all day
			if($start_time != 0000 && $end_time != 2359)
			{
				$event['time_period'] .= $lang->comma.gmdate($mybb->settings['timeformat'], $event['starttime_user'])." - ".gmdate($mybb->settings['timeformat'], $event['endtime_user']);
			}
			else
			{
				$event['time_period'] .= $lang->comma.$lang->all_day;
			}
		}
		else
		{
			$event['time_period'] = gmdate($mybb->settings['dateformat'], $event['starttime_user']).", ".gmdate($mybb->settings['timeformat'], $event['starttime_user']);
			$event['time_period'] .= " - ";
			$event['time_period'] .= gmdate($mybb->settings['dateformat'], $event['endtime_user']).", ".gmdate($mybb->settings['timeformat'], $event['endtime_user']);
		}
	}
	else
	{
		$event['time_period'] = gmdate($mybb->settings['dateformat'], $event['starttime_user']);
	}

	$event['repeats'] = fetch_friendly_repetition($event);

	if($calendar_permissions['canmoderateevents'] == 1 || ($mybb->user['uid'] > 0 && $mybb->user['uid'] == $event['uid']))
	{
		$event['can_edit'] = true;

		if($calendar_permissions['canmoderateevents'] == 1)
		{
			if($event['visible'] == 1)
			{
				$event['approve'] = $lang->unapprove_event;
				$event['approve_value'] = "unapprove";
			}
			else
			{
				$event['approve'] = $lang->approve_event;
				$event['approve_value'] = "approve";
			}
		}

		if($event['visible'] == 0)
		{
			$event['event_class'] = " trow_shaded";
		}
	}

	$calendar['month'] = my_date("n");
	$calendar['year'] = my_date("Y");
	$calendar['currentmonth'] = $monthnames[$calendar['month']];

	$years = [];

	for($year = my_date("Y"); $year < (my_date("Y") + 5); ++$year)
	{
		$years[] = $year;
	}

	// Now output the page
	$plugins->run_hooks("calendar_event_end");

	output_page(\MyBB\template('calendar/event.twig', [
		'calendar_jump' => $calendar_jump,
		'calendar' => $calendar,
		'event' => $event,
		'calendar_permissions' => $calendar_permissions,
		'years' => $years,
	]));
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
	if($calendar_permissions['canviewcalendar'] != 1)
	{
		error_no_permission();
	}

	// Incoming year?
	if(isset($mybb->input['year']) && $mybb->get_input('year', MyBB::INPUT_INT) <= my_date("Y") + 5 && $mybb->get_input('year', MyBB::INPUT_INT) >= 1901)
	{
		$year = $mybb->get_input('year', MyBB::INPUT_INT);
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
	if($mybb->input['day'] && $mybb->input['day'] <= gmdate("t", adodb_gmmktime(0, 0, 0, $month, 1, $year)))
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
	$birthdays = [];
	$calendar['birthdaycount'] = 0;

	if($calendar['showbirthdays'])
	{
		$birthdays2 = get_birthdays($month, $day);
		$calendar['bdayhidden'] = 0;
		if(is_array($birthdays2))
		{
			foreach($birthdays2 as $birthday)
			{
				if($birthday['birthdayprivacy'] == 'all')
				{
					$bday = explode("-", $birthday['birthday']);
					if($bday[2] && $bday[2] < $year)
					{
						$birthday['age'] = $year - $bday[2];
						$birthday['age'] = " (".$lang->sprintf($lang->years_old, $birthday['age']).")";
					}
					else
					{
						$birthday['age'] = '';
					}

					$birthday['username'] = format_name(htmlspecialchars_uni($birthday['username']), $birthday['usergroup'], $birthday['displaygroup']);
					$birthday['profilelink'] = build_profile_link($birthday['username'], $birthday['uid']);

					$birthdays[] = $birthday;
					++$calendar['birthdaycount'];
				}
				else
				{
					++$calendar['bdayhidden'];
					++$calendar['birthdaycount'];
				}
			}
		}

		if($calendar['bdayhidden'] > 0)
		{
			if($birthdays)
			{
				$calendar['hiddendash'] = " - ";
			}
			else
			{
				$calendar['hiddendash'] = '';
			}
		}

		$calendar['bdaydate'] = my_date($mybb->settings['dateformat'], adodb_gmmktime(0, 0, 0, $month, $day, $year), 0, 0);
	}

	// So now we fetch events for this month
	$start_timestamp = adodb_gmmktime(0, 0, 0, $month, $day, $year);
	$end_timestamp = adodb_gmmktime(23, 59, 59, $month, $day, $year);

	$events_cache = get_events($calendar, $start_timestamp, $end_timestamp, $calendar_permissions['canmoderateevents']);

	$events = [];
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

			if($mybb->user['uid'] != 0 && $mybb->user['showimages'] != 1 || $mybb->settings['guestimages'] != 1 && $mybb->user['uid'] == 0)
			{
				$event_parser_options['allow_imgcode'] = 0;
			}

			if($mybb->user['uid'] != 0 && $mybb->user['showvideos'] != 1 || $mybb->settings['guestvideos'] != 1 && $mybb->user['uid'] == 0)
			{
				$event_parser_options['allow_videocode'] = 0;
			}

			$event['description'] = $parser->parse_message($event['description'], $event_parser_options);

			// Get the usergroup
			if($event['usergroup'])
			{
				$user_usergroup = usergroup_permissions($event['usergroup']);
			}
			else
			{
				$user_usergroup = usergroup_permissions(1);
			}
			$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
			if(!$event['displaygroup'])
			{
				$event['displaygroup'] = $event['usergroup'];
			}
			$display_group = usergroup_displaygroup($event['displaygroup']);
			if(is_array($display_group))
			{
				$user_usergroup = array_merge($user_usergroup, $display_group);
			}
			$titles_cache = $cache->read("usertitles");

			// Event made by registered user
			if($event['uid'] > 0 && $event['username'])
			{
				$event['username'] = htmlspecialchars_uni($event['username']);
				$event['profilelink'] = build_profile_link(format_name($event['username'], $event['usergroup'], $event['displaygroup']), $event['uid']);

				if(trim($event['usertitle']) != "")
				{
					// Do nothing, no need for an extra variable..
				}
				elseif($user_usergroup['usertitle'] != "")
				{
					$event['usertitle'] = $user_usergroup['usertitle'];
				}
				elseif(is_array($titles_cache) && !$user_usergroup['usertitle'])
				{
					reset($titles_cache);
					foreach($titles_cache as $title)
					{
						if($event['postnum'] >= $title['posts'])
						{
							$event['usertitle'] = $title['title'];
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
			}
			else
			{
				// Created by a guest or an unknown user
				if(!$event['username'])
				{
					$event['username'] = $lang->guest;
				}

				$event['username'] = htmlspecialchars_uni($event['username']);
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
				$offset = (float)$event['timezone'];
			}
			else
			{
				$offset = (float)$mybb->user['timezone'];
			}

			$event['starttime_user'] = $event['starttime'] + $offset * 3600;

			// Events over more than one day
			if($event['endtime'] > 0 && $event['endtime'] != $event['starttime'])
			{
				$event['endtime_user'] = $event['endtime'] + $offset * 3600;
				$start_day = adodb_gmmktime(0, 0, 0, gmdate("n", $event['starttime_user']), gmdate("j", $event['starttime_user']), gmdate("Y", $event['starttime_user']));
				$end_day = adodb_gmmktime(0, 0, 0, gmdate("n", $event['endtime_user']), gmdate("j", $event['endtime_user']), gmdate("Y", $event['endtime_user']));
				$start_time = gmdate("Hi", $event['starttime_user']);
				$end_time = gmdate("Hi", $event['endtime_user']);

				// Event only runs over one day
				if($start_day == $end_day && $event['repeats']['repeats'] == 0)
				{
					$event['time_period'] = gmdate($mybb->settings['dateformat'], $event['starttime_user']);
					// Event runs all day
					if($start_time != 0000 && $end_time != 2359)
					{
						$event['time_period'] .= $lang->comma.gmdate($mybb->settings['timeformat'], $event['starttime_user'])." - ".gmdate($mybb->settings['timeformat'], $event['endtime_user']);
					}
					else
					{
						$event['time_period'] .= $lang->comma.$lang->all_day;
					}
				}
				else
				{
					$event['time_period'] = gmdate($mybb->settings['dateformat'], $event['starttime_user']).", ".gmdate($mybb->settings['timeformat'], $event['starttime_user']);
					$event['time_period'] .= " - ";
					$event['time_period'] .= gmdate($mybb->settings['dateformat'], $event['endtime_user']).", ".gmdate($mybb->settings['timeformat'], $event['endtime_user']);
				}
			}
			else
			{
				$event['time_period'] = gmdate($mybb->settings['dateformat'], $event['starttime_user']);
			}

			$event['repeats'] = fetch_friendly_repetition($event);

			$event['event_class'] = '';
			if($calendar_permissions['canmoderateevents'] == 1 || ($mybb->user['uid'] > 0 && $mybb->user['uid'] == $event['uid']))
			{
				$event['can_edit'] = true;

				if($calendar_permissions['canmoderateevents'] == 1)
				{
					if($event['visible'] == 1)
					{
						$event['approve'] = $lang->unapprove_event;
						$event['approve_value'] = "unapprove";
					}
					else
					{
						$event['approve'] = $lang->approve_event;
						$event['approve_value'] = "approve";
					}
				}

				if($event['visible'] == 0)
				{
					$event['event_class'] = " trow_shaded";
				}
			}

			$events[] = $event;
		}
	}

	$years = [];

	for($year_sel = my_date("Y"); $year_sel < (my_date("Y") + 5); ++$year_sel)
	{
		$years[] = $year_sel;
	}

	$calendar['year'] = $year;
	$calendar['month'] = $month;
	$calendar['day'] = $day;
	$calendar['currentmonth'] = $monthnames[$month];

	// Now output the page
	$plugins->run_hooks("calendar_dayview_end");

	output_page(\MyBB\template('calendar/dayview.twig', [
		'birthdays' => $birthdays,
		'calendar_jump' => $calendar_jump,
		'events' => $events,
		'calendar_permissions' => $calendar_permissions,
		'calendar' => $calendar,
		'years' => $years,
	]));
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
	if($calendar_permissions['canviewcalendar'] != 1)
	{
		error_no_permission();
	}

	$weekdays = fetch_weekday_structure($calendar['startofweek']);

	// No incoming week, show THIS week
	if(empty($mybb->input['week']))
	{
		list($day, $month, $year) = explode("-", my_date("j-n-Y"));
		$php_weekday = gmdate("w", adodb_gmmktime(0, 0, 0, $month, $day, $year));
		$my_weekday = array_search($php_weekday, $weekdays);
		// So now we have the start day of this week to show
		$start_day = $day - $my_weekday;
		$mybb->input['week'] = adodb_gmmktime(0, 0, 0, $month, $start_day, $year);
	}
	else
	{
		$mybb->input['week'] = (int)str_replace("n", "-", $mybb->get_input('week'));
		// Nothing before 1901 please ;)
		if($mybb->input['week'] < -2177625600)
		{
			$mybb->input['week'] = -2177625600;
		}
	}

	// This is where we've come from and where we're headed
	$week_from = explode("-", gmdate("j-n-Y", $mybb->input['week']));
	$week_from_one = $week_from[1];
	$calendar['friendly_week_from'] = gmdate($mybb->settings['dateformat'], $mybb->input['week']);
	$week_to_stamp = adodb_gmmktime(0, 0, 0, $week_from[1], $week_from[0] + 6, $week_from[2]);
	$week_to = explode("-", gmdate("j-n-Y-t", $week_to_stamp));
	$calendar['friendly_week_to'] = gmdate($mybb->settings['dateformat'], $week_to_stamp);

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
	$events_from = adodb_gmmktime(0, 0, 0, $week_from[1], 1, $week_from[2]);
	$events_to = adodb_gmmktime(0, 0, 0, $week_to[1], $week_to[3], $week_to[2]);

	$events_cache = get_events($calendar, $events_from, $events_to, $calendar_permissions['canmoderateevents']);

	$today = my_date("dnY");

	$prev_week = $mybb->input['week'] - 604800;

	if(my_date("Y", $prev_week) >= 1901)
	{
		$calendar['prev_week_link'] = get_calendar_week_link($calendar['cid'], $prev_week);
	}

	$next_week = $mybb->input['week'] + 604800;

	if(my_date("Y", $next_week) + 1 <= my_date("Y") + 5)
	{
		$calendar['next_week_link'] = get_calendar_week_link($calendar['cid'], $next_week);
	}

	if(!empty($calendar['prev_week_link']) && !empty($calendar['next_week_link']))
	{
		$calendar['sep'] = true;
	}

	$weekday_date = $mybb->input['week'];

	$days = [];
	while($weekday_date <= $week_to_stamp)
	{
		$weekday = gmdate("w", $weekday_date);
		$day['weekday_month'] = $weekday_month = gmdate("n", $weekday_date);
		$weekday_year = gmdate("Y", $weekday_date);
		$day['weekday_name'] = fetch_weekday_name($weekday);
		$day['weekday_day'] = $weekday_day = gmdate("j", $weekday_date);

		// Special shading for today
		if(gmdate("dnY", $weekday_date) == $today)
		{
			$day['day_shaded'] = ' trow_shaded';
		}

		$day['events'] = [];

		// Any events on this specific day?
		if(is_array($events_cache) && array_key_exists("{$weekday_day}-{$weekday_month}-{$weekday_year}", $events_cache))
		{
			foreach($events_cache["$weekday_day-$weekday_month-$weekday_year"] as $event)
			{
				$event['eventlink'] = get_event_link($event['eid']);
				$event['fullname'] = $event['name'];
				if(my_strlen($event['name']) > 50)
				{
					$event['name'] = my_substr($event['name'], 0, 50)."...";
				}

				// Events over more than one day
				if($event['endtime'] > 0 && $event['endtime'] != $event['starttime'])
				{
					$start_day = adodb_gmmktime(0, 0, 0, gmdate("n", $event['starttime_user']), gmdate("j", $event['starttime_user']), gmdate("Y", $event['starttime_user']));
					$end_day = adodb_gmmktime(0, 0, 0, gmdate("n", $event['endtime_user']), gmdate("j", $event['endtime_user']), gmdate("Y", $event['endtime_user']));
					$start_time = gmdate("Hi", $event['starttime_user']);
					$end_time = gmdate("Hi", $event['endtime_user']);

					// Event only runs over one day
					if($start_day == $end_day || $event['repeats'] > 0)
					{
						// Event runs all day
						if($start_time == 0000 && $end_time == 2359)
						{
							$event['time_period'] = $lang->all_day;
						}
						else
						{
							$event['time_period'] = gmdate($mybb->settings['timeformat'], $event['starttime_user'])." - ".gmdate($mybb->settings['timeformat'], $event['endtime_user']);
						}
					}
					// Event starts on this day
					else if($start_day == $weekday_date)
					{
						// Event runs all day
						if($start_time == 0000)
						{
							$event['time_period'] = $lang->all_day;
						}
						else
						{
							$event['time_period'] = $lang->starts.gmdate($mybb->settings['timeformat'], $event['starttime_user']);
						}
					}
					// Event finishes on this day
					else if($end_day == $weekday_date)
					{
						// Event runs all day
						if($end_time == 2359)
						{
							$event['time_period'] = $lang->all_day;
						}
						else
						{
							$event['time_period'] = $lang->finishes.gmdate($mybb->settings['timeformat'], $event['endtime_user']);
						}
					}
					else
					{
						// Event is in the middle
						$event['time_period'] = $lang->all_day;
					}
				}

				if($event['private'] == 1)
				{
					$event['event_class'] = " private_event";
				}
				else
				{
					$event['event_class'] = " public_event";
				}

				if($event['visible'] == 0)
				{
					$event['event_class'] .= " trow_shaded";
				}

				$day['events'][] = $event;
			}
		}

		// Birthdays on this day?
		$day['bday_count'] = 0;
		$day['birthday_lang'] = $day['calendar_link'] = '';
		if($calendar['showbirthdays'] && is_array($birthdays) && array_key_exists("{$weekday_day}-{$weekday_month}", $birthdays))
		{
			$day['bday_count'] = count($birthdays["$weekday_day-$weekday_month"]);
			if($day['bday_count'] > 1)
			{
				$day['birthday_lang'] = $lang->birthdays;
			}
			else
			{
				$day['birthday_lang'] = $lang->birthday;
			}

			$day['calendar_link'] = get_calendar_link($calendar['cid'], $weekday_year, $weekday_month, $weekday_day);
		}

		$day_link = get_calendar_link($calendar['cid'], $weekday_year, $weekday_month, $weekday_day);

		$days[] = $day;
		$weekday_date = adodb_gmmktime(0, 0, 0, $weekday_month, $weekday_day + 1, $weekday_year);
	}

	// Now we build our month headers
	$weekdays = $mini_calendars = [];
	foreach($week_months as $month)
	{
		$month['monthnum'] = $month[0];
		$month['month'] = $monthnames[$month[0]];
		$month['year'] = $month[1];

		// Fetch mini calendar for each month in this week
		$mini_calendars[] = build_mini_calendar($calendar, $month[0], $weekday_year, $events_cache);

		// Fetch out the days for this month
		$month['days'] = $days;

		$weekdays[] = $month;
	}

	$calendar['month'] = $week_from[1];
	$calendar['year'] = $week_from[2];
	$calendar['currentmonth'] = $monthnames[$week_from_one];

	$years = [];

	for($year = my_date("Y"); $year < (my_date("Y") + 5); ++$year)
	{
		$years[] = $year;
	}

	// Now output the page
	$plugins->run_hooks("calendar_weekview_end");

	output_page(\MyBB\template('calendar/weekview.twig', [
		'calendar_permissions' => $calendar_permissions,
		'weekdays' => $weekdays,
		'calendar_jump' => $calendar_jump,
		'mini_calendars' => $mini_calendars,
		'calendar' => $calendar,
		'years' => $years,
	]));
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
	if(isset($mybb->input['year']) && $mybb->get_input('year', MyBB::INPUT_INT) <= my_date("Y") + 5 && $mybb->get_input('year', MyBB::INPUT_INT) >= 1901)
	{
		$year = $mybb->get_input('year', MyBB::INPUT_INT);
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

	$prev_month = get_prev_month($month, $year);

	if($prev_month['year'] >= 1901)
	{
		$calendar['prev_month_name'] = $prev_month['name'];
		$calendar['prev_month_year'] = $prev_month['year'];
		$calendar['prev_link'] = get_calendar_link($calendar['cid'], $prev_month['year'], $prev_month['month']);
	}

	$next_month = get_next_month($month, $year);

	if($next_month['year'] <= my_date("Y") + 5)
	{
		$calendar['next_month_name'] = $next_month['name'];
		$calendar['next_month_year'] = $next_month['year'];
		$calendar['next_link'] = get_calendar_link($calendar['cid'], $next_month['year'], $next_month['month']);
	}

	if(!empty($calendar['prev_link']) && !empty($calendar['next_link']))
	{
		$calendar['sep'] = true;
	}

	// Start constructing the calendar

	$weekdays = fetch_weekday_structure($calendar['startofweek']);

	$month_start_weekday = gmdate("w", adodb_gmmktime(0, 0, 0, $month, $calendar['startofweek'] + 1, $year));

	$prev_month_days = gmdate("t", adodb_gmmktime(0, 0, 0, $prev_month['month'], 1, $prev_month['year']));

	// This is if we have days in the previous month to show
	if($month_start_weekday != $weekdays[0] || $calendar['startofweek'] != 0)
	{
		$prev_days = $day = gmdate("t", adodb_gmmktime(0, 0, 0, $prev_month['month'], 1, $prev_month['year']));
		$day -= array_search(($month_start_weekday), $weekdays);
		$day += $calendar['startofweek'] + 1;
		if($day > $prev_month_days + 1)
		{
			// Go one week back
			$day -= 7;
		}
		$calendar_month = $prev_month['month'];
		$calendar_year = $prev_month['year'];
	}
	else
	{
		$day = $calendar['startofweek'] + 1;
		$calendar_month = $month;
		$calendar_year = $year;
	}

	// So now we fetch events for this month (nb, cache events for past month, current month and next month for mini calendars too)
	$start_timestamp = adodb_gmmktime(0, 0, 0, $calendar_month, $day, $calendar_year);
	$num_days = gmdate("t", adodb_gmmktime(0, 0, 0, $month, 1, $year));

	$month_end_weekday = gmdate("w", adodb_gmmktime(0, 0, 0, $month, $num_days, $year));
	$next_days = 6 - $month_end_weekday + $calendar['startofweek'];

	// More than a week? Go one week back
	if($next_days >= 7)
	{
		$next_days -= 7;
	}

	if($next_days > 0)
	{
		$end_timestamp = adodb_gmmktime(23, 59, 59, $next_month['month'], $next_days, $next_month['year']);
	}
	else
	{
		// We don't need days from the next month
		$end_timestamp = adodb_gmmktime(23, 59, 59, $month, $num_days, $year);
	}

	$events_cache = get_events($calendar, $start_timestamp, $end_timestamp, $calendar_permissions['canmoderateevents']);

	// Fetch birthdays
	if($calendar['showbirthdays'])
	{
		$bday_months = array($month, $prev_month['month'], $next_month['month']);
		$birthdays = get_birthdays($bday_months);
	}

	$today = my_date("dnY");

	$weekday_headers = [];

	// Build weekday headers
	foreach($weekdays as $weekday)
	{
		$weekday = fetch_weekday_name($weekday);
		$weekday_headers[] = $weekday;
	}

	$weeks = [];
	$in_month = 0;

	// Iterate weeks (each week gets a row)
	$week = [];
	$day_bit = [];
	for($row = 0; $row < 6; ++$row)
	{
		$days = [];
		foreach($weekdays as $weekday_id => $weekday)
		{
			// Current month always starts on 1st row
			if($row == 0 && $day == $calendar['startofweek'] + 1)
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
				$week_stamp = adodb_gmmktime(0, 0, 0, $calendar_month, $day, $calendar_year);
				$week['week_link'] = get_calendar_week_link($calendar['cid'], $week_stamp);
			}

			if($weekday_id == 0 && $calendar_month == $next_month['month'])
			{
				break;
			}

			// Any events on this specific day?
			$day_bit['total_events'] = 0;
			$day_bit['eventlimit'] = false;
			$day_bit['events'] = $day_bit['event_lang'] = '';

			if(is_array($events_cache) && array_key_exists("{$day}-{$calendar_month}-{$calendar_year}", $events_cache))
			{
				$day_bit['total_events'] = count($events_cache["$day-$calendar_month-$calendar_year"]);
				if($day_bit['total_events'] > $calendar['eventlimit'] && $calendar['eventlimit'] != 0)
				{
					$day_bit['eventlimit'] = true;
					if($day_bit['total_events'] > 1)
					{
						$day_bit['event_lang'] = $lang->events;
					}
					else
					{
						$day_bit['event_lang'] = $lang->event;
					}

					$calendar['link'] = get_calendar_link($calendar['cid'], $calendar_year, $calendar_month, $day);
				}
				else
				{
					$events = [];
					foreach($events_cache["$day-$calendar_month-$calendar_year"] as $event)
					{
						$event['eventlink'] = get_event_link($event['eid']);
						$event['fullname'] = $event['name'];

						if(my_strlen($event['name']) > 15)
						{
							$event['name'] = my_substr($event['name'], 0, 15)."...";
						}

						if($event['private'] == 1)
						{
							$event['event_class'] = " private_event";
						}
						else
						{
							$event['event_class'] = " public_event";
						}

						if($event['visible'] == 0)
						{
							$event['event_class'] .= " trow_shaded";
						}

						$events[] = $event;
					}

					$day_bit['events'] = $events;
				}
			}

			// Birthdays on this day?
			$day_bit['bday_count'] = 0;
			$day_bit['birthday_lang'] = $day_bit['calendar_link'] = '';
			if($calendar['showbirthdays'] && is_array($birthdays) && array_key_exists("$day-$calendar_month", $birthdays))
			{
				$day_bit['bday_count'] = count($birthdays["$day-$calendar_month"]);
				if($day_bit['bday_count'] > 1)
				{
					$day_bit['birthday_lang'] = $lang->birthdays;
				}
				else
				{
					$day_bit['birthday_lang'] = $lang->birthday;
				}

				$day_bit['calendar_link'] = get_calendar_link($calendar['cid'], $calendar_year, $calendar_month, $day);
			}

			$day_bit['day_link'] = get_calendar_link($calendar['cid'], $calendar_year, $calendar_month, $day);

			// Is the current day
			if($day.$calendar_month.$year == $today && $month == $calendar_month)
			{
				$day_bit['day_class'] = "trow_sep";
			}
			// Not in this month
			else if($in_month == 0)
			{
				$day_bit['day_class'] = "trow1";
			}
			else
			{
				// Just a normal day in this month
				$day_bit['day_class'] = "trow2";
			}

			$day_bit['day'] = $day;
			$day_bit['month'] = $monthnames[$calendar_month];
			$days[] = $day_bit;
			++$day;
		}

		$week['days'] = $days;
		if(!empty($week['days']))
		{
			$weeks[] = $week;
		}
	}

	$years = [];

	for($year_sel = my_date("Y"); $year_sel < (my_date("Y") + 5); ++$year_sel)
	{
		$years[] = $year_sel;
	}

	$calendar['month'] = $month;
	$calendar['year'] = $year;
	$calendar['currentmonth'] = $monthnames[$month];

	$plugins->run_hooks("calendar_end");

	output_page(\MyBB\template('calendar/calendar.twig', [
		'calendar_permissions' => $calendar_permissions,
		'years' => $years,
		'calendar_jump' => $calendar_jump,
		'calendar' => $calendar,
		'weekday_headers' => $weekday_headers,
		'weeks' => $weeks,
	]));
}
