<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

$templatelist = "calendar,calendar_eventbit_public,calendar_eventbit_private,calendar_addpublicevent,calendar_addprivateevent,calendar_addevent,calendar_event,calendar_daybit,calendar_daybit_today";
require "./global.php";
require "./inc/functions_post.php";
require "./inc/class_parser.php";
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
	nopermission();
}

// Make $eid an easy-to-use variable.
$eid = $mybb->input['eid'];

/* If we are looking at an event, select the date for that event first. */
if($mybb->input['action'] == "event")
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
	if($mybb->input['year'])
	{
		$year = intval($mybb->input['year']);
	}
	else
	{
		$year = date("Y");
	}
	// Then the month
	if($mybb->input['month'] >=1 && $mybb->input['month'] <= 12)
	{
		$month = intval($mybb->input['month']);
	}
	else
	{
		$month = date("n");
	}
	// Find the number of days in that month
	$time = mktime(0, 0, 0, $month, 1, $year);
	$days = date("t", $time);
	// Now the specific day
	if($mybb->input['day'] >= 1 && $mybb->input['day'] <= $days)
	{
		$day = $mybb->input['day'];
	}
	else
	{
		// Make the day the last day of the month, if the user overshot the number of days in the month
		if($mybb->input['day'] > $days)
		{
			$day = $days;
		}
		// Make the day the first day of the month, if the user undershot the number of days in the month
		elseif($mybb->input['day'] < 1)
		{
			$day = 1;
		}
		// This shouldn't be needed, but just in case if someone falls into the hole...
		else
		{
			$day = date("j");
		}
	}
}

// Make sure there's no leading zeros
$stamp = mktime(0, 0, 0, $month, $day, $year);
$day = date("j", $stamp);
$month = date("n", $stamp);
$year = date("Y", $stamp);

$monthnames = array("offset", $lang->alt_month_1, $lang->alt_month_2, $lang->alt_month_3, $lang->alt_month_4, $lang->alt_month_5, $lang->alt_month_6, $lang->alt_month_7, $lang->alt_month_8, $lang->alt_month_9, $lang->alt_month_10, $lang->alt_month_11, $lang->alt_month_12);

// Make navigation
addnav($lang->nav_calendar, "calendar.php");

if($month && $year)
{
	addnav("$monthnames[$month] $year", "calendar.php?month=$month&year=$year");
}

// No weird actions allowed.
if(	$mybb->input['action'] != "event" &&
	$mybb->input['action'] != "addevent" &&
	$mybb->input['action'] != "do_addevent" &&
	$mybb->input['action'] != "editevent" &&
	$mybb->input['action'] != "do_editevent" &&
	$mybb->input['action'] != "dayview")
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
		nopermission();
	}

	if(($event['author'] == $mybb->user['uid'] && $mybb->user['uid'] != 0) || $mybb->usergroup['cancp'] == "yes")
	{
		$editbutton = "<a href=\"calendar.php?action=editevent&amp;eid=$event[eid]\"><img src=\"$theme[imglangdir]/postbit_edit.gif\" border=\"0\" alt=\"$lang->alt_edit\" /></a>";
		$deletebutton = "<a href=\"javascript:deleteEvent($event[eid]);\"><img src=\"$theme[imglangdir]/postbit_delete.gif\" border=\"0\" alt=\"$lang->alt_delete\" /></a>";
	}
	$event['subject'] = $parser->parse_badwords($event['subject']);
	$event['subject'] = htmlspecialchars_uni($event['subject']);
	$event['description'] = $parser->parse_message($event['description'], $event_parser_options);
	if($event['username'])
	{
		$eventposter = "<a href=\"member.php?action=profile&amp;uid=$event[author]\">" . formatname($event['username'], $event['usergroup'], $event['displaygroup']) . "</a>";
	}
	else
	{
		$eventposter = $lang->guest;
	}
	$eventdate = explode("-", $event['date']);
	$eventdate = mktime(0, 0, 0, $eventdate[1], $eventdate[0], $eventdate[2]);
	$eventdate = mydate($mybb->settings['dateformat'], $eventdate);

	addnav($lang->nav_viewevent);

	$plugins->run_hooks("calendar_event_end");

	eval("\$eventpage = \"".$templates->get("calendar_event")."\";");
	outputpage($eventpage);
}

// View all events on a specific day.
if($mybb->input['action'] == "dayview")
{
	$plugins->run_hooks("calendar_dayview_start");

	// Load Birthdays
	// If we have 1st March and this year isn't a leap year, fetch birthdays on the 29th.
	if($day == 1 && $month == 3 && date("L", mktime(0, 0, 0, $month, 1, $year)) != 1)
	{
		$bday_where = "u.birthday LIKE '$day-$month-%' OR u.birthday LIKE '29-2-%'";
		$feb_fix = 1;
	}
	else // Fetch only for this day
	{
		$bday_where = "u.birthday LIKE '$day-$month-%'";
		$feb_fix = 0;
	}
	$query = $db->query(
		"SELECT u.uid, u.username, u.birthday, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."users u
		WHERE $bday_where
	");
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
		$bdays['username'] = formatname($bdays['username'], $bdays['usergroup'], $bdays['displaygroup']);
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
		SELECT e.eid, e.author, e.subject, e.description, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."events e
		LEFT JOIN ".TABLE_PREFIX."users u ON (e.author=u.uid)
		WHERE date LIKE '$day-$month-$year'
		AND ((author='".$mybb->user[uid]."'
		AND private='yes') OR (private!='yes'))
	");
	while($event = $db->fetch_array($query))
	{
		$plugins->run_hooks("calendar_dayview_event");

		if(($event['author'] == $mybb->user['uid'] && $mybb->user['uid'] != 0) || $mybb->usergroup['cancp'] == "yes")
		{
			$editbutton = "<a href=\"calendar.php?action=editevent&amp;eid=$event[eid]\"><img src=\"$theme[imglangdir]/postbit_edit.gif\" border=\"0\" alt=\"$lang->alt_edit\" /></a>";
			$deletebutton = "<a href=\"javascript:deleteEvent($event[eid]);\"><img src=\"$theme[imglangdir]/postbit_delete.gif\" border=\"0\" alt=\"$lang->alt_delete\" /></a>";
		}
		$event['subject'] = $parser->parse_badwords($event['subject']);
		$event['subject'] = htmlspecialchars_uni($event['subject']);
		$event['description'] = $parser->parse_message($event['description'], $event_parser_options);
		if($event['username'])
		{
			$eventposter = "<a href=\"member.php?action=profile&amp;uid=$event[author]\">" . formatname($event['username'], $event['usergroup'], $event['displaygroup']) . "</a>";
		}
		else
		{
			$eventposter = $lang_guest;
		}
		$eventdate = explode("-", $event['date']);
		$eventdate = mktime(0, 0, 0, $eventdate[1], $eventdate[0], $eventdate[2]);
		$eventdate = mydate($mybb->settings['dateformat'], $eventdate);
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
		$bdaydate = mydate($mybb->settings['dateformat'], $eventdate);
		$lang->birthdays_on_day = sprintf($lang->birthdays_on_day, $bdaydate);
		eval("\$bdaylist = \"".$templates->get("calendar_dayview_birthdays")."\";");
	}
	addnav($lang->nav_dayview);

	$plugins->run_hooks("calendar_dayview_end");

	eval("\$dayview = \"".$templates->get("calendar_dayview")."\";");
	outputpage($dayview);
}

// Process the adding of an event.
if($mybb->input['action'] == "do_addevent")
{
	$plugins->run_hooks("calendar_do_addevent_start");

	// Set up eventhandler.
	require_once "inc/datahandler.php";
	require_once "inc/datahandlers/event.php";
	$eventhandler = new EventDataHandler();

	// Prepare an array for the eventhandler.
	$event = array(
		"name" => $mybb->input['subject'],
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
		$errors = $eventhandler->get_errors();
		foreach($errors as $error)
		{
			//
			// MYBB 1.2 DATA HANDLER ERROR HANDLING DEBUG/TESTING CODE (REMOVE BEFORE PUBLIC FINAL)
			// Used to determine any missing language variables from the datahandlers
			//
			if($lang->$error['error_code'])
			{
				$event_errors[] = $lang->$error['error_code'];
			}
			else
			{
				$event_errors[] = "Missing language var: ".$error['error_code'];
			}
			//
			// END TESTING CODE
			//
			/*
				$event_errors[] =$lang->$error['error_code'];
			*/
		}
		$event_errors = inlineerror($event_errors);
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

	$yearopts = '';

	//Construct option list for years
	for($i = date("Y"); $i < (date("Y") + 5); $i++)
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

	if($mybb->input['type'] == "private")
	{
		$privatecheck = " checked=\"checked\"";
		if($mybb->usergroup['canaddprivateevents'] == "no")
		{
			nopermission();
		}
	}
	else
	{
		if($mybb->usergroup['canaddpublicevents'] == "no")
		{
			nopermission();
		}
	}
	addnav($lang->nav_addevent);

	$plugins->run_hooks("calendar_addevent_end");

	eval("\$addevent = \"".$templates->get("calendar_addevent")."\";");
	outputpage($addevent);
}

// Process the editing of an event.
if($mybb->input['action'] == "do_editevent")
{
	$plugins->run_hooks("calendar_do_editevent_start");

	$query = $db->query("
		SELECT author
		FROM ".TABLE_PREFIX."events
		WHERE eid='$eid'
	");
	$event = $db->fetch_array($query);

	if(!is_numeric($event['author']))
	{
		error($lang->error_invalidevent);
	}
	elseif(($event['author'] != $mybb->user['uid'] || $mybb->user['uid'] == 0) && $mybb->usergroup['cancp'] != "yes")
	{
		nopermission();
	}

	// Are we going to delete this event or just edit it?
	if($mybb->input['delete'] == "yes")
	{
		// Set up eventhandler.
		require_once "inc/datahandlers/event.php";
		$eventhandler = new EventDataHandler();

		// Make the eventhandler delete the event.
		$eventhandler->delete_by_eid($eid);

		// Redirect back to the main calendar view.
		redirect("calendar.php", $lang->redirect_eventdeleted);
	}
	else
	{
		// Set up eventhandler.
		require_once "inc/datahandlers/event.php";
		$eventhandler = new EventDataHandler();

		// Prepare an array for the eventhandler.
		$event = array(
			"eid" => $eid,
			"name" => $mybb->input['subject'],
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
			$errors = $eventhandler->get_errors();
			foreach($errors as $error)
			{
				//
				// MYBB 1.2 DATA HANDLER ERROR HANDLING DEBUG/TESTING CODE (REMOVE BEFORE PUBLIC FINAL)
				// Used to determine any missing language variables from the datahandlers
				//
				if($lang->$error)
				{
					$event_errors[] = $lang->$error;
				}
				else
				{
					$event_errors[] = "Missing language var: ".$error;
				}
				//
				// END TESTING CODE
				//
				/*
					$event_errors[] =$lang->$error;
				*/
			}
			$event_errors = inlineerror($event_errors);
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

	$eid = intval($mybb->input['eid']);

	$query = $db->query("
		SELECT eid, author, date
		FROM ".TABLE_PREFIX."events
		WHERE eid='$eid'
		LIMIT 1
	");
	$event = $db->fetch_array($query);

	if(!$event['eid'])
	{
		error($lang->error_invalidevent);
	}
	elseif(($event['author'] != $mybb->user['uid'] || $mybb->user['uid'] == 0) && $mybb->usergroup['cancp'] != "yes")
	{
		nopermission();
	}
	$eventdate = explode("-", $event['date']);
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
	for($i=1;$i<=31;$i++)
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

	$event['subject'] = htmlspecialchars_uni($event['subject']);
	$event['description'] = htmlspecialchars_uni($event['description']);

	if($event['private'] == "yes")
	{
		$privatecheck = " checked=\"checked\"";
	}
	addnav($lang->nav_editevent);

	$plugins->run_hooks("calendar_editevent_end");

	eval("\$editevent = \"".$templates->get("calendar_editevent")."\";");
	outputpage($editevent);
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
	if($day == 1 && $month == 3 && date("L", mktime(0, 0, 0, $month, 1, $year)) != 1)
	{
		$bday_where = "u.birthday LIKE '%-$month-%' OR u.birthday LIKE '29-2-%'";
		$feb_fix = 1;
	}
	else // Fetch only for this day
	{
		$bday_where = "u.birthday LIKE '%-$month-%'";
		$feb_fix = 0;
	}
	$query = $db->query(
		"SELECT u.uid, u.username, u.birthday, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."users u
		WHERE $bday_where
	");

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
	$query = $db->query("
		SELECT subject, private, date
		FROM ".TABLE_PREFIX."events
		WHERE date LIKE '%-$month-$year'
		AND ((author='".$mybb->user[uid]."'
		AND private='yes')
		OR (private!='yes'))
	");
	while($event = $db->fetch_array($query))
	{
		$event['subject'] = htmlspecialchars_uni($event['subject']);
		$event['fullsubject'] = $event['subject'];
		if(strlen($event['subject']) > 15)
		{
			$event['subject'] = substr($event['subject'], 0, 15) . "...";
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
	$sblanks = date("w", $time);
	// Blank space before first day
	if($sblanks)
	{
		$swidth = $sblanks * 14;
		$daybits .= "<td width=\"$swidth%\" colspan=\"$sblanks\" height=\"90\" class=\"trow2\">&nbsp;</td>\n";
		$count += $sblanks;
	}
	for($i = 1; $i <= $days; $i++)
	{
		if($bdays[$i])
		{
			if($bdays[$i] > 1)
			{
				$birthdays = "<a href=\"calendar.php?action=dayview&year=$year&month=$month&day=$i\">$bdays[$i] $lang->birthdays</a><br />\n";
			}
			else
			{
				$birthdays = "<a href=\"calendar.php?action=dayview&year=$year&month=$month&day=$i\">$bdays[$i] $lang->birthday</a><br />\n";
			}
		}
		else
		{
			$birthdays = '';
		}
		if ((date("d") == $i) && (date("n") == $month) && (date("Y") == $year))
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

	$prev = mktime(0, 0, 0, date("n", $time) - 1, 1, $year);
	$next = mktime(0, 0, 0, date("n", $time) + 1, 1, $year);
	$prevyear = date("Y", $prev);
	$prevmonth = date("n", $prev);
	$nextyear = date("Y", $next);
	$nextmonth = date("n", $next);

	$yearsel == '';
	for($i = date("Y"); $i < (date("Y") + 5); $i++)
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
	outputpage($calendar);
}
?>