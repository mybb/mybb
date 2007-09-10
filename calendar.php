<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/eula.html
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

$mybb->input['day'] = intval($mybb->input['day']);


/* If we are looking at an event, select the date for that event first. */
if($mybb->input['action'] == "event" || $mybb->input['action'] == "editevent" || $mybb->input['action'] == "do_editevent")
{
	$options = array(
		"limit" => 1
	);
	$query = $db->simple_select(TABLE_PREFIX."events", "date", "eid=".$eid, $options);
	$event_date = $db->fetch_field($query, "date");
	if($event_date == FALSE)
	{
		error($lang->error_invalidevent);
	}
	list($day, $month, $year) = explode("-", $event_date);
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
		SELECT e.eid, e.private, e.author, e.subject, e.description, e.date, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."events e
		LEFT JOIN ".TABLE_PREFIX."users u ON (e.author=u.uid)
		WHERE e.eid='$eid'
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
		$editbutton = "<a href=\"calendar.php?action=editevent&amp;eid=$event[eid]\"><img src=\"$theme[imglangdir]/postbit_edit.gif\" border=\"0\" alt=\"$lang->alt_edit\" /></a>";
		$deletebutton = "<a href=\"javascript:MyBB.deleteEvent($event[eid]);\"><img src=\"$theme[imglangdir]/postbit_delete.gif\" border=\"0\" alt=\"$lang->alt_delete\" /></a>";
	}
	$event['subject'] = $parser->parse_badwords($event['subject']);
	$event['subject'] = htmlspecialchars_uni($event['subject']);
	$event['description'] = $parser->parse_message($event['description'], $event_parser_options);
	if($event['username'])
	{
		$eventposter = "<a href=\"member.php?action=profile&amp;uid=$event[author]\">".format_name($event['username'], $event['usergroup'], $event['displaygroup']) . "</a>";
	}
	else
	{
		$eventposter = $lang->guest;
	}
	$eventdate = explode("-", $event['date']);
	$eventdate = gmmktime(0, 0, 0, $eventdate[1], $eventdate[0], $eventdate[2]);
	$eventdate = my_date($mybb->settings['dateformat'], $eventdate, 0, 0);

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

	$query = $db->simple_select(TABLE_PREFIX."users", "uid, username, birthday, usergroup, displaygroup", $bday_where);

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
	$query = $db->query("
		SELECT e.eid, e.author, e.subject, e.description, e.date, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."events e
		LEFT JOIN ".TABLE_PREFIX."users u ON (e.author=u.uid)
		WHERE date LIKE '$day-$month-$year'
		AND ((author='".$mybb->user['uid']."'
		AND private='yes') OR (private!='yes'))
	");
	while($event = $db->fetch_array($query))
	{
		$plugins->run_hooks("calendar_dayview_event");

		if(($event['author'] == $mybb->user['uid'] && $mybb->user['uid'] != 0) || $mybb->usergroup['cancp'] == "yes")
		{
			$editbutton = "<a href=\"calendar.php?action=editevent&amp;eid=$event[eid]\"><img src=\"$theme[imglangdir]/postbit_edit.gif\" border=\"0\" alt=\"$lang->alt_edit\" /></a>";
			$deletebutton = "<a href=\"javascript:MyBB.deleteEvent($event[eid]);\"><img src=\"$theme[imglangdir]/postbit_delete.gif\" border=\"0\" alt=\"$lang->alt_delete\" /></a>";
		}
		$event['subject'] = $parser->parse_badwords($event['subject']);
		$event['subject'] = htmlspecialchars_uni($event['subject']);
		$event['description'] = $parser->parse_message($event['description'], $event_parser_options);
		if($event['username'])
		{
			$eventposter = "<a href=\"member.php?action=profile&amp;uid=$event[author]\">" . format_name($event['username'], $event['usergroup'], $event['displaygroup']) . "</a>";
		}
		else
		{
			$eventposter = $lang_guest;
		}
		$eventdate = explode("-", $event['date']);
		$eventdate = gmmktime(0, 0, 0, $eventdate[1], $eventdate[0], $eventdate[2]);
		$eventdate = my_date($mybb->settings['dateformat'], $eventdate, 0, 0);
		eval("\$events .= \"".$templates->get("calendar_dayview_event")."\";");
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
		"day" => $mybb->input['day'],
		"month" => $mybb->input['month'],
		"year" => $mybb->input['year'],
		"private" => $mybb->input['private']
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
	$plugins->run_hooks("calendar_addevent_start");


	// If MyCode is on for this forum and the MyCode editor is enabled inthe Admin CP, draw the code buttons and smilie inserter.
	if($mybb->settings['bbcodeinserter'] != "off" && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
	{
		$codebuttons = build_mycode_inserter();
		$smilieinserter = build_clickable_smilies();
	}

	$yearopts = '';

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

	//Construct option list for years
	for($i = my_date("Y"); $i < (my_date("Y") + 5); $i++)
	{
		if($i == $year)
		{
			$yearopts .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$yearopts .= "<option value=\"$i\">$i</option>\n";
		}
	}
	$msel[$month] = " selected=\"selected\"";

	$dayopts = '';

	//Construct option list for days
	for($i = 1; $i <= 31; $i++)
	{
		if($i == $day)
		{
			$dayopts .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$dayopts .= "<option value=\"$i\">$i</option>\n";
		}
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

	$query = $db->simple_select(TABLE_PREFIX."events", "*", "eid='{$eid}'");
	$event = $db->fetch_array($query);

	if(!is_numeric($event['author']))
	{
		error($lang->error_invalidevent);
	}
	elseif(($event['author'] != $mybb->user['uid'] || $mybb->user['uid'] == 0) && $mybb->usergroup['cancp'] != "yes")
	{
		error_no_permission();
	}

	// Are we going to delete this event or just edit it?
	if($mybb->input['delete'] == "yes")
	{
		$db->delete_query(TABLE_PREFIX."events", "eid='{$event['eid']}'");

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
			"day" => $mybb->input['day'],
			"month" => $mybb->input['month'],
			"year" => $mybb->input['year'],
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

	$query = $db->simple_select(TABLE_PREFIX."events", "*", "eid='{$eid}'");
	$event = $db->fetch_array($query);

	if(!$event['eid'])
	{
		error($lang->error_invalidevent);
	}
	elseif(($event['author'] != $mybb->user['uid'] || $mybb->user['uid'] == 0) && $mybb->usergroup['cancp'] != "yes")
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
		$eventdate = array(
			0 => $mybb->input['day'],
			1 => $mybb->input['month'],
			2 => $mybb->input['year']
		);
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
		$eventdate = explode("-", $event['date']);
	}
	$msel[$eventdate[1]] = " selected=\"selected\"";
	$yearopts = '';
	for($i = ($eventdate[2] - 2); $i <= ($eventdate[2] + 2); $i++)
	{
		if($i == $eventdate[2])
		{
			$yearopts .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$yearopts .= "<option value=\"$i\">$i</option>\n";
		}
	}

	$dayopts = '';
	for($i = 1; $i <= 31; $i++)
	{
		if($i == $eventdate[0])
		{
			$dayopts .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$dayopts .= "<option value=\"$i\">$i</option>\n";
		}
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
	$days = my_date("t", $time);
	$bdays = array();

	// Load Birthdays
	// If we have 1st March and this year isn't a leap year, fetch birthdays on the 29th.
	if($month == 3 && my_date("L", mktime(0, 0, 0, $month, 1, $year)) != 1)
	{
		$bday_where = "birthday LIKE '%-$month-%' OR birthday LIKE '29-2%' OR birthday LIKE '%-$month'";
		$feb_fix = 1;
	}
	else // Fetch only for this day
	{
		$bday_where = "birthday LIKE '%-$month-%' OR birthday LIKE '%-$month'";
		$feb_fix = 0;
	}

	$query = $db->simple_select(TABLE_PREFIX."users", "uid, username, birthday, usergroup, displaygroup", $bday_where);
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
	// Load Events
	$query = $db->simple_select(TABLE_PREFIX."events", "subject, private, date, eid", "date LIKE '%-{$month}-{$year}' AND ((author='{$mybb->user['uid']}' AND private='yes') OR (private!='yes'))");
	while($event = $db->fetch_array($query))
	{
		$event['subject'] = htmlspecialchars_uni($event['subject']);
		$event['fullsubject'] = $event['subject'];
		if(my_strlen($event['subject']) > 15)
		{
			$event['subject'] = my_substr($event['subject'], 0, 15) . "...";
		}
		$eventdate = explode("-", $event['date']);
		if($event['private'] == "yes")
		{
			eval("\$events[$eventdate[0]] .= \"".$templates->get("calendar_eventbit_private")."\";");
		}
		else
		{
			eval("\$events[$eventdate[0]] .= \"".$templates->get("calendar_eventbit_public")."\";");
		}
	}
	$daybits = "<tr>\n";
	$count = 0;
	$sblanks = my_date("w", $time);
	// Blank space before first day
	if($sblanks)
	{
		$swidth = $sblanks * 14;
		$daybits .= "<td width=\"$swidth%\" colspan=\"$sblanks\" height=\"90\" class=\"trow2\">&nbsp;</td>\n";
		$count += $sblanks;
	}
	for($i = 1; $i <= $days; $i++)
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
		$count++;

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
	for($i = my_date("Y"); $i < (my_date("Y") + 5); $i++)
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