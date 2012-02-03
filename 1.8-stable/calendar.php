<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: calendar.php 5746 2012-02-03 10:03:25Z Tomm $
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'calendar.php');

$templatelist = "calendar_weekdayheader,calendar_weekrow_day,calendar_weekrow,calendar_eventbit_public,calendar_eventbit_private,calendar";
$templatelist .= ",calendar_weekview_day,calendar_weekview_day_event,calendar_mini_weekdayheader,calendar_mini_weekrow_day,calendar_mini_weekrow,calendar_mini,calendar_weekview_month,calendar_weekview,calendar_eventbit,calendar_addeventlink";
$templatelist .= ",calendar_event_editbutton,calendar_event_modoptions,calendar_event,calendar_dayview_event,calendar_dayview,codebuttons,smilieinsert,calendar_editevent,calendar_dayview_birthdays_bday,calendar_dayview_birthdays,calendar_dayview_noevents,calendar_dayview_noevents";

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

// Make navigation
add_breadcrumb($lang->nav_calendar, "calendar.php");

$calendar_jump = build_calendar_jump($mybb->input['calendar']);

// Add an event
if($mybb->input['action'] == "do_addevent" && $mybb->request_method == "post")
{
	$query = $db->simple_select("calendars", "*", "cid='".intval($mybb->input['calendar'])."'");
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

	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("calendar_do_addevent_start");

	// Set up event handler.
	require_once MYBB_ROOT."inc/datahandler.php";
	require_once MYBB_ROOT."inc/datahandlers/event.php";
	$eventhandler = new EventDataHandler("insert");

	// Prepare an array for the eventhandler.
	$event = array(
		"cid" => $calendar['cid'],
		"uid" => $mybb->user['uid'],
		"name" => $mybb->input['name'],
		"description" => $mybb->input['description'],
		"private" => $mybb->input['private'],
		"type" => $mybb->input['type']
	);

	// Now we add in our date/time info depending on the type of event
	if($mybb->input['type'] == "single")
	{
		$event['start_date'] = array(
			"day" => $mybb->input['single_day'],
			"month" => $mybb->input['single_month'],
			"year" => $mybb->input['single_year']
		);
	}
	else if($mybb->input['type'] == "ranged")
	{
		$event['start_date'] = array(
			"day" => $mybb->input['start_day'],
			"month" => $mybb->input['start_month'],
			"year" => $mybb->input['start_year'],
			"time" => $mybb->input['start_time']
		);
		$event['end_date'] = array(
			"day" => $mybb->input['end_day'],
			"month" => $mybb->input['end_month'],
			"year" => $mybb->input['end_year'],
			"time" => $mybb->input['end_time']
		);
		$event['timezone'] = intval($mybb->input['timezone']);
		$event['ignoretimezone'] =	intval($mybb->input['ignoretimezone']);
		$repeats = array();
		switch($mybb->input['repeats'])
		{
			case 1:
				$repeats['repeats'] = 1;
				$repeats['days'] = $mybb->input['repeats_1_days'];
				break;
			case 2:
				$repeats['repeats'] = 2;
				break;
			case 3:
				$repeats['repeats'] = 3;
				$repeats['weeks'] = $mybb->input['repeats_3_weeks'];
				if(!is_array($mybb->input['repeats_3_days']))
				{
					$mybb->input['repeats_3_days'] = array();
				}
				ksort($mybb->input['repeats_3_days']);
				$days = array();
				foreach($mybb->input['repeats_3_days'] as $weekday => $value)
				{
					if($value != 1) continue;
					$days[] = $weekday;
				}
				$repeats['days'] = $days;
				break;
			case 4:
				$repeats['repeats'] = 4;
				if($mybb->input['repeats_4_type'] == 1)
				{
					$repeats['day'] = $mybb->input['repeats_4_day'];
					$repeats['months'] = $mybb->input['repeats_4_months'];
				}
				else
				{
					$repeats['months'] = $mybb->input['repeats_4_months2'];
					$repeats['occurance'] = $mybb->input['repeats_4_occurance'];
					$repeats['weekday'] = $mybb->input['repeats_4_weekday'];
				}
				break;
			case 5:
				$repeats['repeats'] = 5;
				if($mybb->input['repeats_5_type'] == 1)
				{
					$repeats['day'] = $mybb->input['repeats_5_day'];
					$repeats['month'] = $mybb->input['repeats_5_month'];
					$repeats['years'] = $mybb->input['repeats_5_years'];
				}
				else
				{
					$repeats['occurance'] = $mybb->input['repeats_5_occurance'];
					$repeats['weekday'] = $mybb->input['repeats_5_weekday'];
					$repeats['month'] = $mybb->input['repeats_5_month2'];
					$repeats['years'] = $mybb->input['repeats_5_years'];
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
	$query = $db->simple_select("calendars", "*", "cid='".intval($mybb->input['calendar'])."'");
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
		$codebuttons = build_mycode_inserter();
		if($calendar['allowsmilies'] == 1)
		{
			$smilieinserter = build_clickable_smilies();
		}
	}

	// Previous selections
	$name = $description = '';
	if(isset($mybb->input['name']))
	{
		$name = htmlspecialchars_uni($mybb->input['name']);
	}
	
	if(isset($mybb->input['description']))
	{
		$description = htmlspecialchars_uni($mybb->input['description']);
	}
	
	if($mybb->request_method == "post")
	{
		$single_day = $mybb->input['single_day'];
		$single_month[$mybb->input['single_month']] = " selected=\"selected\"";
		$single_year = $mybb->input['single_year'];
		$start_day = $mybb->input['start_day'];
		$start_month[$mybb->input['start_month']] = " selected=\"selected\"";
		$start_year = $mybb->input['start_year'];
		$start_time = htmlspecialchars_uni($mybb->input['start_time']);
		$end_day = $mybb->input['end_day'];
		$end_month[$mybb->input['end_month']] = " selected=\"selected\"";
		$end_year = $mybb->input['end_year'];
		$end_time = htmlspecialchars_uni($mybb->input['end_time']);
		if($mybb->input['type'] == "single")
		{
			$type_single = "checked=\"checked\"";
			$type = "single";
		}
		else
		{
			$type_ranged = "checked=\"checked\"";
			$type = "ranged";
		}
		if($mybb->input['repeats'])
		{
			$repeats_sel[$mybb->input['repeats']] = " selected=\"selected\"";
		}
		$repeats_1_days = intval($mybb->input['repeats_1_days']);
		$repeats_3_weeks = intval($mybb->input['repeats_3_weeks']);
		if(is_array($mybb->input['repeats_3_days']))
		{
			foreach($mybb->input['repeats_3_days'] as $day => $val)
			{
				if($val != 1)
				{
					continue;
				}
				$day = intval($day);
				$repeats_3_days[$day] = " checked=\"checked\"";
			}
		}
		if($mybb->input['repeats_4_type'] == 1)
		{
			$repeats_4_type[1] = "checked=\"checked\"";
		}
		else
		{
			$repeats_4_type[2] = "checked=\"checked\"";
		}
		$repeats_4_day = intval($mybb->input['repeats_4_day']);
		$repeats_4_months = intval($mybb->input['repeats_4_months']);
		$repeats_4_occurance[$mybb->input['repeats_4_occurance']] = "selected=\"selected\"";
		$repeats_4_weekday[$mybb->input['repeats_4_weekday']] = "selected=\"selected\"";
		$repeats_4_months2 = intval($mybb->input['repeats_4_months2']);
		if($mybb->input['repeats_5_type'] == 1)
		{
			$repeats_5_type[1] = "checked=\"checked\"";
		}
		else
		{
			$repeats_5_type[2] = "checked=\"checked\"";
		}
		$repeats_5_day = intval($mybb->input['repeats_5_day']);
		$repeats_5_month[$mybb->input['repeats_5_month']] = "selected=\"selected\"";
		$repeats_5_years = intval($mybb->input['repeats_5_years']);
		$repeats_5_occurance[$mybb->input['repeats_5_occurance']] = "selected=\"selected\"";
		$repeats_5_weekday[$mybb->input['repeats_5_weekday']] = "selected=\"selected\"";
		$repeats_5_month2[$mybb->input['repeats_5_month2']] = "selected=\"selected\"";
		$repeats_5_years2 = intval($mybb->input['repeats_5_years2']);

		$timezone = $mybb->input['timezone'];
	}
	else
	{
		if($mybb->input['day'])
		{
			$single_day = $start_day = $end_day = intval($mybb->input['day']);
		}
		else
		{
			$single_day = $start_day = $end_day = my_date("j");
		}
		if($mybb->input['month'])
		{
			$month = intval($mybb->input['month']);
		}
		else
		{
			$month = my_date("n");
		}
		$single_month[$month] = $start_month[$month] = $end_month[$month] = "selected=\"selected\"";
		if($mybb->input['year'])
		{
			$single_year = $start_year = $end_year = intval($mybb->input['year']);
		}
		else
		{
			$single_year = $start_year = $end_year = my_date("Y");
		}
		$start_time = $end_time = "";
		$type_single = "checked=\"checked\"";
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
		$repeats_5_months2[1] = "selected=\"selected\"";
		$repeats_5_years2 = 1;
		$timezone = $mybb->user['timezone'];
	}

	// Construct option list for years
	for($i = my_date('Y'); $i < (my_date('Y') + 5); ++$i)
	{
		if($i == $single_year)
		{
			$single_years .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$single_years .= "<option value=\"{$i}\">{$i}</option>\n";
		}
		if($i == $start_year)
		{
			$start_years .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$start_years .= "<option value=\"{$i}\">{$i}</option>\n";
		}
		if($i == $end_year)
		{
			$end_years .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$end_years .= "<option value=\"{$i}\">{$i}</option>\n";
		}
	}

	// Construct option list for days
	for($i = 1; $i <= 31; ++$i)
	{
		if($i == $single_day)
		{
			$single_days .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$single_days .= "<option value=\"{$i}\">{$i}</option>\n";
		}
		if($i == $start_day)
		{
			$start_days .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$start_days .= "<option value=\"{$i}\">{$i}</option>\n";
		}
		if($i == $end_day)
		{
			$end_days .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$end_days .= "<option value=\"{$i}\">{$i}</option>\n";
		}
	}

	$timezones = build_timezone_select("timezone", $timezone);

	if($mybb->input['ignoretimezone'] == 1)
	{
		$ignore_timezone = "checked=\"checked\"";
	}

	if($mybb->input['private'] == 1)
	{
		$privatecheck = " checked=\"checked\"";
	}

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
				$calendar_select .= "<option value=\"{$calendar_option['cid']}\" selected=\"selected\">{$calendar_option['name']}</option>\n";
			}
			else
			{
				$calendar_select .= "<option value=\"{$calendar_option['cid']}\">{$calendar_option['name']}</option>\n";
			}
		}
	}

	$plugins->run_hooks("calendar_addevent_end");

	eval("\$addevent = \"".$templates->get("calendar_addevent")."\";");
	output_page($addevent);
}

// Edit an event
if($mybb->input['action'] == "do_editevent" && $mybb->request_method == "post")
{
	$query = $db->simple_select("events", "*", "eid='".intval($mybb->input['eid'])."'");
	$event = $db->fetch_array($query);

	if(!is_numeric($event['eid']))
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

	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	// Are we going to delete this event or just edit it?
	if($mybb->input['delete'] == 1)
	{
		$db->delete_query("events", "eid='{$event['eid']}'");

		// Redirect back to the main calendar view.
		redirect("calendar.php", $lang->redirect_eventdeleted);
	}

	// Have we made a private event public?
	if(!$mybb->input['private'])
	{
		$mybb->input['private'] = 0;
	}

	$plugins->run_hooks("calendar_do_editevent_start");

	// Set up event handler.
	require_once MYBB_ROOT."inc/datahandler.php";
	require_once MYBB_ROOT."inc/datahandlers/event.php";
	$eventhandler = new EventDataHandler("update");

	// Prepare an array for the eventhandler.
	$event = array(
		"eid" => $event['eid'],
		"name" => $mybb->input['name'],
		"description" => $mybb->input['description'],
		"private" => $mybb->input['private'],
		"type" => $mybb->input['type']
	);
	
	// Now we add in our date/time info depending on the type of event
	if($mybb->input['type'] == "single")
	{
		$event['start_date'] = array(
			"day" => $mybb->input['single_day'],
			"month" => $mybb->input['single_month'],
			"year" => $mybb->input['single_year']
		);
		$event['repeats'] = '';
	}
	else if($mybb->input['type'] == "ranged")
	{
		$event['start_date'] = array(
			"day" => $mybb->input['start_day'],
			"month" => $mybb->input['start_month'],
			"year" => $mybb->input['start_year'],
			"time" => $mybb->input['start_time']
		);
		$event['end_date'] = array(
			"day" => $mybb->input['end_day'],
			"month" => $mybb->input['end_month'],
			"year" => $mybb->input['end_year'],
			"time" => $mybb->input['end_time']
		);
		$event['timezone'] = $mybb->input['timezone'];
		$event['ignoretimezone'] = intval($mybb->input['ignoretimezone']);
		$repeats = array();
		switch($mybb->input['repeats'])
		{
			case 1:
				$repeats['repeats'] = 1;
				$repeats['days'] = $mybb->input['repeats_1_days'];
				break;
			case 2:
				$repeats['repeats'] = 2;
				break;
			case 3:
				$repeats['repeats'] = 3;
				$repeats['weeks'] = $mybb->input['repeats_3_weeks'];
				if(!is_array($mybb->input['repeats_3_days']))
				{
					$mybb->input['repeats_3_days'] = array();
				}
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
				if($mybb->input['repeats_4_type'] == 1)
				{
					$repeats['day'] = $mybb->input['repeats_4_day'];
					$repeats['months'] = $mybb->input['repeats_4_months'];
				}
				else
				{
					$repeats['months'] = $mybb->input['repeats_4_months2'];
					$repeats['occurance'] = $mybb->input['repeats_4_occurance'];
					$repeats['weekday'] = $mybb->input['repeats_4_weekday'];
				}
				break;
			case 5:
				$repeats['repeats'] = 5;
				if($mybb->input['repeats_5_type'] == 1)
				{
					$repeats['day'] = $mybb->input['repeats_5_day'];
					$repeats['month'] = $mybb->input['repeats_5_month'];
					$repeats['years'] = $mybb->input['repeats_5_years'];
				}
				else
				{
					$repeats['occurance'] = $mybb->input['repeats_5_occurance'];
					$repeats['weekday'] = $mybb->input['repeats_5_weekday'];
					$repeats['month'] = $mybb->input['repeats_5_month2'];
					$repeats['years'] = $mybb->input['repeats_5_years'];
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
	$query = $db->simple_select("events", "*", "eid='".intval($mybb->input['eid'])."'");
	$event = $db->fetch_array($query);

	if(!is_numeric($event['eid']))
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

	$event['name'] = htmlspecialchars_uni($event['name']);
	
	add_breadcrumb(htmlspecialchars_uni($calendar['name']), get_calendar_link($calendar['cid']));
	add_breadcrumb($event['name'], get_event_link($event['eid']));
	add_breadcrumb($lang->nav_editevent);

	$plugins->run_hooks("calendar_editevent_start");

	// If MyCode is on for this forum and the MyCode editor is enabled inthe Admin CP, draw the code buttons and smilie inserter.
	if($mybb->settings['bbcodeinserter'] != 0 && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0) && $calendar['allowmycode'] == 1)
	{
		$codebuttons = build_mycode_inserter();
		if($calendar['allowsmilies'] == 1)
		{
			$smilieinserter = build_clickable_smilies();
		}
	}

	// Previous selections
	if($event_errors)
	{
		$name = htmlspecialchars_uni($mybb->input['name']);
		$description = htmlspecialchars_uni($mybb->input['description']);
		$single_day = $mybb->input['single_day'];
		$single_month[$mybb->input['single_month']] = " selected=\"selected\"";
		$single_year = $mybb->input['single_year'];
		$start_day = $mybb->input['start_day'];
		$start_month[$mybb->input['start_month']] = " selected=\"selected\"";
		$start_year = $mybb->input['start_year'];
		$start_time = htmlspecialchars_uni($mybb->input['start_time']);
		$end_day = $mybb->input['end_day'];
		$end_month[$mybb->input['end_month']] = " selected=\"selected\"";
		$end_year = $mybb->input['end_year'];
		$end_time = htmlspecialchars_uni($mybb->input['end_time']);
		if($mybb->input['type'] == "single")
		{
			$type_single = "checked=\"checked\"";
			$type = "single";
		}
		else
		{
			$type_ranged = "checked=\"checked\"";
			$type = "ranged";
		}
		if($mybb->input['repeats'])
		{
			$repeats_sel[$mybb->input['repeats']] = " selected=\"selected\"";
		}
		$repeats_1_days = intval($mybb->input['repeats_1_days']);
		$repeats_3_weeks = intval($mybb->input['repeats_3_weeks']);
		if(is_array($mybb->input['repeats_3_days']))
		{
			foreach($mybb->input['repeats_3_days'] as $day => $val)
			{
				if($val != 1) continue;
				$day = intval($day);
				$repeats_3_days[$day] = " checked=\"checked\"";
			}
		}
		if($mybb->input['repeats_4_type'] == 1)
		{
			$repeats_4_type[1] = "checked=\"checked\"";
		}
		else
		{
			$repeats_4_type[2] = "checked=\"checked\"";
		}
		$repeats_4_day = intval($mybb->input['repeats_4_day']);
		$repeats_4_months = intval($mybb->input['repeats_4_months']);
		$repeats_4_occurance[$mybb->input['repeats_4_occurance']] = "selected=\"selected\"";
		$repeats_4_weekday[$mybb->input['repeats_4_weekday']] = "selected=\"selected\"";
		$repeats_4_months2 = intval($mybb->input['repeats_4_months2']);
		if($mybb->input['repeats_5_type'] == 1)
		{
			$repeats_5_type[1] = "checked=\"checked\"";
		}
		else
		{
			$repeats_5_type[2] = "checked=\"checked\"";
		}
		$repeats_5_day = intval($mybb->input['repeats_5_day']);
		$repeats_5_month[$mybb->input['repeats_5_month']] = "selected=\"selected\"";
		$repeats_5_years = intval($mybb->input['repeats_5_years']);
		$repeats_5_occurance[$mybb->input['repeats_5_occurance']] = "selected=\"selected\"";
		$repeats_5_weekday[$mybb->input['repeats_5_weekday']] = "selected=\"selected\"";
		$repeats_5_month2[$mybb->input['repeats_5_month2']] = "selected=\"selected\"";
		$repeats_5_years2 = intval($mybb->input['repeats_5_years2']);

		if($mybb->input['private'] == 1)
		{
			$privatecheck = " checked=\"checked\"";
		}
		
		if($mybb->input['ignoretimezone'] == 1)
		{
			$ignore_timezone = "checked=\"checked\"";
		}
		
		$timezone = $mybb->input['timezone'];
	}
	else
	{
		$mybb->input['calendar'] = $event['cid'];
		$name = htmlspecialchars_uni($event['name']);
		$description = htmlspecialchars_uni($event['description']);
		if($event['private'] == 1)
		{
			$privatecheck = " checked=\"checked\"";
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
			$type_ranged = "checked=\"checked\"";
			$type = "ranged";
			$repeats = unserialize($event['repeats']);
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
			}
		}
		else
		{
			$type_single = "checked=\"checked\"";
			$type = "single";
			// set some defaults if the user wants to make a ranged event
			$end_day = $start_day;
			$end_month = $start_month;
			$end_year = $start_year;
		}
	}

	// Construct option list for years
	for($i = my_date('Y'); $i < (my_date('Y') + 5); ++$i)
	{
		if($i == $single_year)
		{
			$single_years .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$single_years .= "<option value=\"{$i}\">{$i}</option>\n";
		}
		if($i == $start_year)
		{
			$start_years .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$start_years .= "<option value=\"{$i}\">{$i}</option>\n";
		}
		if($i == $end_year)
		{
			$end_years .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$end_years .= "<option value=\"{$i}\">{$i}</option>\n";
		}
	}

	// Construct option list for days
	for($i = 1; $i <= 31; ++$i)
	{
		if($i == $single_day)
		{
			$single_days .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$single_days .= "<option value=\"{$i}\">{$i}</option>\n";
		}
		if($i == $start_day)
		{
			$start_days .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$start_days .= "<option value=\"{$i}\">{$i}</option>\n";
		}
		if($i == $end_day)
		{
			$end_days .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$end_days .= "<option value=\"{$i}\">{$i}</option>\n";
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
	$query = $db->simple_select("events", "*", "eid='".intval($mybb->input['eid'])."'");
	$event = $db->fetch_array($query);

	if(!is_numeric($event['eid']))
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

	// Build calendar select
	$query = $db->simple_select("calendars", "*", "", array("order_by" => "name", "order_dir" => "asc"));
	while($calendar_option = $db->fetch_array($query))
	{
		if($calendar_permissions[$calendar['cid']]['canviewcalendar'] == 1)
		{
			$calendar_option['name'] = htmlspecialchars_uni($calendar_option['name']);
			$calendar_select .= "<option value=\"{$calendar_option['cid']}\">{$calendar_option['name']}</option>\n";
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
	verify_post_check($mybb->input['my_post_key']);

	$query = $db->simple_select("events", "*", "eid='".intval($mybb->input['eid'])."'");
	$event = $db->fetch_array($query);

	if(!is_numeric($event['eid']))
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


	$query = $db->simple_select("calendars", "*", "cid='".intval($mybb->input['new_calendar'])."'");
	$new_calendar = $db->fetch_array($query);

	if(!$new_calendar['cid'])
	{
		error($lang->invalid_calendar);
	}

	if($calendar_permissions[$mybb->input['new_calendar']]['canviewcalendar'] != 1)
	{
		error_no_permission();
	}

	$plugins->run_hooks("calendar_do_move_start");

	$updated_event = array(
		"cid" => $new_calendar['cid']
	);
	$db->update_query("events", $updated_event, "eid='{$event['eid']}'");

	$plugins->run_hooks("calendar_do_move_end");

	redirect(get_event_link($event['eid']), $lang->redirect_eventmoved);
}

// Approve an event
if($mybb->input['action'] == "approve")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$query = $db->simple_select("events", "*", "eid='".intval($mybb->input['eid'])."'");
	$event = $db->fetch_array($query);

	if(!is_numeric($event['eid']))
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

	$plugins->run_hooks("calendar_approve_start");

	$updated_event = array(
		"visible" => 1
	);
	$db->update_query("events", $updated_event, "eid='{$event['eid']}'");

	$plugins->run_hooks("calendar_approve_end");

	redirect(get_event_link($event['eid']), $lang->redirect_eventapproved);
}

// Unapprove an event
if($mybb->input['action'] == "unapprove")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$query = $db->simple_select("events", "*", "eid='".intval($mybb->input['eid'])."'");
	$event = $db->fetch_array($query);

	if(!is_numeric($event['eid']))
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

	$plugins->run_hooks("calendar_unapprove_start");

	$updated_event = array(
		"visible" => 0
	);
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
		WHERE e.eid='".intval($mybb->input['eid'])."'
	");
	$event = $db->fetch_array($query);

	if(!is_numeric($event['eid']) || ($event['private'] == 1 && $event['uid'] != $mybb->user['uid']))
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

	if(!is_array($titles_cache))
	{
		// Get user titles (i guess we should improve this, maybe in version3.
		$query = $db->simple_select("usertitles", "*", "", array('order_by' => 'posts', 'order_dir' => 'DESC'));
		while($usertitle = $db->fetch_array($query))
		{
			$titles_cache[$usertitle['posts']] = $usertitle;
		}
		unset($usertitle);
	}

	// Event made by registered user
	if($event['uid'] > 0 && $event['username'])
	{
		$event['profilelink'] = build_profile_link(format_name($event['username'], $event['usergroup'], $event['displaygroup']), $event['uid']);
		
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

		if(!$event['starimage'])
		{
			$event['starimage'] = $user_usergroup['starimage'];
		}
		$event['starimage'] = str_replace("{theme}", $theme['imgdir'], $event['starimage']);
		
		for($i = 0; $i < $post['stars']; ++$i)
		{
			$event['userstars'] .= "<img src=\"".$event['starimage']."\" border=\"0\" alt=\"*\" />";
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
	}

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
	
		$event['repeats'] = unserialize($event['repeats']);
		
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
		$repeats = "<span class=\"smalltext\"><strong>{$lang->repeats}</strong><br />{$repeats}</span>";
	}

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
	for($i = my_date("Y"); $i < (my_date("Y") + 5); ++$i)
	{
		$yearsel .= "<option value=\"$i\">$i</option>\n";
	}

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
		$query = $db->simple_select("calendars", "*", "cid='".intval($mybb->input['calendar'])."'");
		$calendar = $db->fetch_array($query);
	}
	// Showing the default calendar
	else
	{
		$query = $db->simple_select("calendars", "*", "disporder='1'");
		$calendar = $db->fetch_array($query);
	}

	// Invalid calendar?
	if(!$calendar['cid'])
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

	// And day?
	if($mybb->input['day'] && $mybb->input['day'] <= gmdate("t", gmmktime(0, 0, 0, $month, 1, $year)))
	{
		$day = intval($mybb->input['day']);
	}
	else
	{
		$day = my_date("j");
	}

	add_breadcrumb(htmlspecialchars_uni($calendar['name']), get_calendar_link($calendar['cid']));
	add_breadcrumb("$day $monthnames[$month] $year", get_calendar_link($calendar['cid'], $year, $month, $day));

	$plugins->run_hooks("calendar_dayview_start");

	// Load Birthdays for this day
	if($calendar['showbirthdays'])
	{
		$birthdays = get_birthdays($month, $day);
		$bdayhidden = 0;
		if(is_array($birthdays))
		{
			foreach($birthdays as $birthday)
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

	$events_cache = get_events($calendar['cid'], $start_timestamp, $end_timestamp, $calendar_permissions['canmoderateevents']);

	if(is_array($events_cache["$day-$month-$year"]))
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

			if(!is_array($titles_cache))
			{
				// Get user titles (i guess we should improve this, maybe in version3.
				$query = $db->simple_select("usertitles", "*", "", array('order_by' => 'posts', 'order_dir' => 'DESC'));
				while($usertitle = $db->fetch_array($query))
				{
					$titles_cache[$usertitle['posts']] = $usertitle;
				}
				unset($usertitle);
			}

			// Event made by registered user
			if($event['uid'] > 0 && $event['username'])
			{
				$event['profilelink'] = build_profile_link(format_name($event['username'], $event['usergroup'], $event['displaygroup']), $event['uid']);
				
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

				if(!$event['starimage'])
				{
					$event['starimage'] = $user_usergroup['starimage'];
				}
				
				for($i = 0; $i < $post['stars']; ++$i)
				{
					$event['userstars'] .= "<img src=\"".$event['starimage']."\" border=\"0\" alt=\"*\" />";
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
			}

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
				$repeats = "<span class=\"smalltext\"><strong>{$lang->repeats}</strong><br />{$repeats}</span>";
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
	for($i = my_date("Y"); $i < (my_date("Y") + 5); ++$i)
	{
		$yearsel .= "<option value=\"$i\">$i</option>\n";
	}

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
		$query = $db->simple_select("calendars", "*", "cid='".intval($mybb->input['calendar'])."'");
		$calendar = $db->fetch_array($query);
	}
	// Showing the default calendar
	else
	{
		$query = $db->simple_select("calendars", "*", "disporder='1'");
		$calendar = $db->fetch_array($query);
	}

	// Invalid calendar?
	if(!$calendar['cid'])
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
	for($i = my_date("Y"); $i < (my_date("Y") + 5); ++$i)
	{
		$yearsel .= "<option value=\"$i\">$i</option>\n";
	}

	// No incoming week, show THIS week
	if(!$mybb->input['week'])
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
		$mybb->input['week'] = (int)str_replace("n", "-", $mybb->input['week']);
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

	$events_cache = get_events($calendar['cid'], $events_from, $events_to, $calendar_permissions['canmoderateevents']);

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
					$event_time = "<span class=\"smalltext\"> ({$time_period})</span>";
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
		$day_birthdays = "";
		if($calendar['showbirthdays'] && is_array($birthdays) && array_key_exists("{$weekday_day}-{$weekday_month}", $birthdays))
		{
			$bday_count = count($birthdays["$weekday_day-$weekday_month"]);
			if($bday_count > 1)
			{
				$day_birthdays = "<a href=\"".get_calendar_link($calendar['cid'], $weekday_year, $weekday_month, $weekday_day)."\">{$bday_count} {$lang->birthdays}</a><br />\n";
			}
			else
			{
				$day_birthdays = "<a href=\"".get_calendar_link($calendar['cid'], $weekday_year, $weekday_month, $weekday_day)."\">1 {$lang->birthday}</a><br />\n";
			}
		}

		$day_link = get_calendar_link($calendar['cid'], $weekday_year, $weekday_month, $weekday_day);
		eval("\$day_bits[$weekday_month] .= \"".$templates->get("calendar_weekview_day")."\";");
		$day_events = $day_birthdays = "";
		$weekday_date = gmmktime(0, 0, 0, $weekday_month, $weekday_day+1, $weekday_year);
	}

	// Now we build our month headers
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

	if($mybb->usergroup['canaddevents'] == 1)
	{
		eval("\$addevent = \"".$templates->get("calendar_addeventlink")."\";");
	}

	// Now output the page
	$plugins->run_hooks("calendar_weekview_end");

	eval("\$weekview = \"".$templates->get("calendar_weekview")."\";");
	output_page($weekview);
}

// View yearly calendar
if($mybb->input['action'] == "yearview")
{
}

// Showing a calendar
if(!$mybb->input['action'])
{
	// Showing a particular calendar
	if($mybb->input['calendar'])
	{
		$query = $db->simple_select("calendars", "*", "cid='".intval($mybb->input['calendar'])."'");
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

	// Incoming month/year?
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

	add_breadcrumb(htmlspecialchars_uni($calendar['name']), get_calendar_link($calendar['cid']));
	add_breadcrumb("$monthnames[$month] $year", get_calendar_link($calendar['cid'], $year, $month));

	$next_month = get_next_month($month, $year);
	$prev_month = get_prev_month($month, $year);

	$prev_link = get_calendar_link($calendar['cid'], $prev_month['year'], $prev_month['month']);
	$next_link = get_calendar_link($calendar['cid'], $next_month['year'], $next_month['month']);

	// Start constructing the calendar

	$weekdays = fetch_weekday_structure($calendar['startofweek']);

	$month_start_weekday = gmdate("w", gmmktime(0, 0, 0, $month, $calendar['startofweek']+1, $year));
	
	// This is if we have days in the previous month to show
	if($month_start_weekday != $weekdays[0] || $calendar['startofweek'] != 0)
	{
		$day = gmdate("t", gmmktime(0, 0, 0, $prev_month['month'], 1, $prev_month['year']));
		$day -= array_search(($month_start_weekday), $weekdays);
		$day += $calendar['startofweek']+1;
		$calendar_month = $prev_month['month'];
		$calendar_year = $prev_month['year'];
	}
	else
	{
		$day = $calendar['startofweek']+1;
		$calendar_month = $month;
		$calendar_year = $year;
	}

	$prev_month_days = gmdate("t", gmmktime(0, 0, 0, $prev_month['month'], 1, $prev_month['year']));
	
	// So now we fetch events for this month (nb, cache events for past month, current month and next month for mini calendars too)
	$start_timestamp = gmmktime(0, 0, 0, $prev_month['month'], $day, $prev_month['year']);
	$num_days = gmdate("t", gmmktime(0, 0, 0, $next_month['month'], 1, $next_month['year']));
	$end_timestamp = gmmktime(23, 59, 59, $next_month['month'], $num_days, $next_month['year']);

	$num_days = gmdate("t", gmmktime(0, 0, 0, $month, 1, $year));

	if($day > 31 && in_array($next_month['month'], array(4, 6, 11, 9)))
	{
		// If we're a day over a 30 day month, gather the events from a week before too.
		// Otherwise it will start on events for the 2nd - not the 'start' date for the month.
		$start_timestamp -= (86400 * 7);
	}

	$events_cache = get_events($calendar['cid'], $start_timestamp, $end_timestamp, $calendar_permissions['canmoderateevents']);
	
	// Fetch birthdays
	if($calendar['showbirthdays'])
	{
		$bday_months = array($month, $prev_month['month'], $next_month['month']);
		$birthdays = get_birthdays($bday_months);
	}

	$today = my_date("dnY");

	// Build weekday headers
	foreach($weekdays as $weekday)
	{
		$weekday_name = fetch_weekday_name($weekday);
		eval("\$weekday_headers .= \"".$templates->get("calendar_weekdayheader")."\";");
	}
	
	// Fix offset for Start Of Week being Saturday
	if($calendar_month == $prev_month['month'] && $calendar['startofweek'] > 0)
	{
		$day -= 7;
		
		// Lets make sure we don't have a whole extra column for the last month
		if($prev_month_days-7 >= ($day-1))
		{
			$day += 7;
		}
	}

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
			$day_birthdays = "";
			if($calendar['showbirthdays'] && is_array($birthdays) && array_key_exists("$day-$calendar_month", $birthdays))
			{
				$bday_count = count($birthdays["$day-$calendar_month"]);
				if($bday_count > 1)
				{
					$day_birthdays = "<div style=\"margin-bottom: 4px;\"><a href=\"".get_calendar_link($calendar['cid'], $calendar_year, $calendar_month, $day)."\" class=\"smalltext\">{$bday_count} {$lang->birthdays}</a></div>\n";
				}
				else
				{
					$day_birthdays = "<div style=\"margin-bottom: 4px;\"><a href=\"".get_calendar_link($calendar['cid'], $calendar_year, $calendar_month, $day)."\" class=\"smalltext\">1 {$lang->birthday}</a></div>\n";
				}
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
	for($i = my_date("Y"); $i < (my_date("Y") + 5); ++$i)
	{
		$yearsel .= "<option value=\"$i\">$i</option>\n";
	}
	
	if($mybb->usergroup['canaddevents'] == 1)
	{
		eval("\$addevent = \"".$templates->get("calendar_addeventlink")."\";");
	}

	$plugins->run_hooks("calendar_end");

	eval("\$calendar = \"".$templates->get("calendar")."\";");
	output_page($calendar);
}
?>