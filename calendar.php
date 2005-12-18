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

define("KILL_GLOBALS", 1); 
$templatelist = "calendar,calendar_eventbit_public,calendar_eventbit_private,calendar_addpublicevent,calendar_addprivateevent,calendar_addevent,calendar_event,calendar_daybit,calendar_daybit_today";
require "./global.php";
require "./inc/functions_post.php";

// Load global language phrases
$lang->load("calendar");

if($mybb->usergroup['canviewcalendar'] == "no")
{
	nopermission();
}

if($mybb->input['action'] == "event")
{
	$query = $db->query("SELECT date FROM ".TABLE_PREFIX."events WHERE eid = ".intval($mybb->input['eid']));
	$event_date = $db->result($query, 0);
	list($day, $month, $year) = explode("-", $event_date);
}
else
{
	if($mybb->input['year'])
	{
		$year = intval($mybb->input['year']);
	}
	else
	{
		$year = date("Y");
	}
	
	if($mybb->input['month'] >=1 && $mybb->input['month'] <= 12)
	{
		$month = $mybb->input['month'];
	}
	else
	{
		$month = date("n");
	}
	
	$time = mktime(0, 0, 0, $month, 1, $year);
	$days = date("t", $time);
	
	if($mybb->input['day'] >= 1 && $mybb->input['day'] <= $days)
	{
		$day = $mybb->input['day'];
	}
	else
	{
		$day = ((date("j") > $days) ? $days : date("j"));
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

if($mybb->input['action'] == "event")
{
	$plugins->run_hooks("calendar_event_start");

	$eid = intval($mybb->input['eid']);

	$query = $db->query("SELECT e.*, u.username, u.usergroup, u.displaygroup FROM ".TABLE_PREFIX."events e LEFT JOIN ".TABLE_PREFIX."users u ON (e.author=u.uid) WHERE e.eid='$eid'");
	$event = $db->fetch_array($query);
	
	if(!$event['eid'])
	{
		error($lang->error_invalidevent);
	}
	if(($event['author'] == $mybb->user['uid'] && $mybb->user['uid'] != 0) || $mybb->usergroup['cancp'] == "yes")
	{
		$editbutton = "<a href=\"calendar.php?action=editevent&amp;eid=$event[eid]\"><img src=\"$theme[imglangdir]/postbit_edit.gif\" border=\"0\" alt=\"$lang->alt_edit\" /></a>";
		$deletebutton = "<a href=\"javascript:deleteEvent($event[eid]);\"><img src=\"$theme[imglangdir]/postbit_delete.gif\" border=\"0\" alt=\"$lang->alt_delete\" /></a>";
	}
	$event['subject'] = htmlspecialchars_uni(stripslashes($event['subject']));
	$event['description'] = postify($event['description'], "no", "yes", "yes", "yes");
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
elseif($mybb->input['action'] == "dayview")
{
	$plugins->run_hooks("calendar_dayview_start");

	// Load Birthdays
	$query = $db->query("SELECT u.uid, u.username, u.birthday, u.usergroup, u.displaygroup FROM ".TABLE_PREFIX."users u WHERE u.birthday LIKE '$day-$month-%'");
	$alterbg = $theme['trow1'];
	$comma = "";
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
			$age = "";
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
	// Load Events
	$query = $db->query("SELECT e.*, u.username, u.usergroup, u.displaygroup FROM ".TABLE_PREFIX."events e LEFT JOIN ".TABLE_PREFIX."users u ON (e.author=u.uid) WHERE date LIKE '$day-$month-$year' AND ((author='".$mybb->user[uid]."' AND private='yes') OR (private!='yes'))");
	while($event = $db->fetch_array($query))
	{
		$plugins->run_hooks("calendar_dayview_event");

		if(($event['author'] == $mybb->user['uid'] && $mybb->user['uid'] != 0) || $mybb->usergroup['cancp'] == "yes")
		{
			$editbutton = "<a href=\"calendar.php?action=editevent&amp;eid=$event[eid]\"><img src=\"$theme[imglangdir]/postbit_edit.gif\" border=\"0\" alt=\"$lang->alt_edit\" /></a>";
			$deletebutton = "<a href=\"javascript:deleteEvent($event[eid]);\"><img src=\"$theme[imglangdir]/postbit_delete.gif\" border=\"0\" alt=\"$lang->alt_delete\" /></a>";
		}
		$event['subject'] = htmlspecialchars_uni(stripslashes($event['subject']));
		$event['description'] = postify(stripslashes($event['description']), "no", "yes", "yes", "yes");
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
		eval("\$events = \"".$templates->get("calendar_dayview_noevents")."\";");
	}
	if($birthdays)
	{
		$eventdate = mktime(0, 0, 0, $month, $day, $year);
		$bdaydate = mydate($mybb->settings['dateformat'], $eventdate);
		$lang->birthdays_on_day = sprintf($lang->birthdays_on_day, $bdaydate);
		eval("\$bdaylist = \"".$templates->get("calendar_dayview_birthdays")."\";");
	}
	addnav($lang->nav_dayview);

	$plugins->run_hooks("calendar_dayview_end");

	eval("\$dayview = \"".$templates->get("calendar_dayview")."\";");
	outputpage($dayview);
}
elseif($mybb->input['action'] == "addevent")
{
	$plugins->run_hooks("calendar_addevent_start");

	$yearopts = '';
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
	for($i=1;$i<=31;$i++)
	{
		$dayopts .= "<option value=\"$i\">$i</option>\n";
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
elseif($mybb->input['action'] == "do_addevent")
{
	$plugins->run_hooks("calendar_do_addevent_start");

	$day = intval($mybb->input['day']);
	$month = intval($mybb->input['month']);
	$year = intval($mybb->input['year']);

	if(!$mybb->input['subject'] || !$mybb->input['description'] || !$mybb->input['day'] || !$mybb->input['month'] || !$mybb->input['year'])
	{
		error($lang->error_incompletefields);
	}
	/* The following bit doesn't seem to work due to the gmdate("t") always returning 31 */
	if($day > gmdate("t", mktime(0, 0, 0, $month, 1, $year)))
	{
		error($lang->error_incorrectday);
	}
	if($mybb->input['private'] == "yes")
	{
		if($mybb->user['uid'] == 0 || $mybb->usergroup['canaddprivateevents'] == "no")
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
		$mybb->input['private'] = "no";
	}
	$eventdate = $day."-".$month."-".$year;

	$newevent = array(
		"subject" => addslashes($mybb->input['subject']),
		"author" => $mybb->user['uid'],
		"date" => $eventdate,
		"description" => addslashes($mybb->input['description']),
		"private" => $mybb->input['private']
		);

	$plugins->run_hooks("calendar_do_addevent_process");

	$db->insert_query(TABLE_PREFIX."events", $newevent);
	$eid = $db->insert_id();

	$plugins->run_hooks("calendar_do_addevent_end");

	redirect("calendar.php?action=event&eid=$eid", $lang->redirect_eventadded);
}
elseif($mybb->input['action'] == "editevent")
{
	$plugins->run_hooks("calendar_editevent_start");
	
	$eid = intval($mybb->input['eid']);

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."events WHERE eid='$eid'");
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
elseif($mybb->input['action'] == "do_editevent")
{
	$eid = intval($mybb->input['eid']);

	$plugins->run_hooks("calendar_do_editevent_start");

	$query = $db->query("SELECT author FROM ".TABLE_PREFIX."events WHERE eid='$eid'");
	$event = $db->fetch_array($query);
	
	if(!is_numeric($event['author']))
	{
		error($lang->error_invalidevent);
	}
	elseif(($event['author'] != $mybb->user['uid'] || $mybb->user['uid'] == 0) && $mybb->usergroup['cancp'] != "yes")
	{
		nopermission();
	}
	if($mybb->input['delete'] == "yes")
	{
		$db->query("DELETE FROM ".TABLE_PREFIX."events WHERE eid='$eid'");
		redirect("calendar.php", $lang->redirect_eventdeleted);
	}
	else
	{
		if($mybb->input['private'] == "yes")
		{
			if($mybb->user['uid'] == 0 || $mybb->usergroup['canaddprivateevents'] == "no")
			{
				nopermission();
			}
		}
		else
		{
			$mybb->input['private'] = "no";
		}

		$eventdate = $day."-".$month."-".$year;

		$newevent = array(
			"subject" => addslashes($mybb->input['subject']),
			"description" => addslashes($mybb->input['description']),
			"date" => $eventdate,
			"private" => $mybb->input['private']
			);

		$plugins->run_hooks("calendar_do_editevent_process");
	
		$db->update_query(TABLE_PREFIX."events", $newevent, "eid=$eid");
		
		$plugins->run_hooks("calendar_do_editevent_end");

		redirect("calendar.php?action=event&eid=$eid", $lang->redirect_eventupdated);
	}
}
else
{

	$plugins->run_hooks("calendar_start");

	$time = mktime(0, 0, 0, $month, 1, $year);
	$days = date("t", $time);
	$bdays = array();

	// Load Birthdays
	$query = $db->query("SELECT birthday FROM ".TABLE_PREFIX."users WHERE birthday LIKE '%-$month-%'");
	while($user = $db->fetch_array($query))
	{
		$bday = explode("-", $user['birthday']);
		$bdays[$bday[0]]++;
	}

	// Load Events
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."events WHERE date LIKE '%-$month-$year' AND ((author='".$mybb->user[uid]."' AND private='yes') OR (private!='yes'))");
	while($event = $db->fetch_array($query))
	{
		$event['subject'] = htmlspecialchars_uni(stripslashes($event['subject']));
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
			$birthdays = "";
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