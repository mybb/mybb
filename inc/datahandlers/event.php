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

/**
 * Event handling class, provides common structure to handle event data.
 *
 */
class EventDataHandler extends Handler
{
	/**
	 * Verifies if an event name is valid or not and attempts to fix it
	 *
	 * @param string The name of the event.
	 * @return boolean True if valid, false if invalid.
	 */
	function verify_name(&$name)
	{
		$name = trim($name);
		if(!$name)
		{
			$this->set_error("no_name");
			return false;
		}
		return true;
	}

	/**
	 * Verifies if an event description is valid or not and attempts to fix it
	 *
	 * @param string The description of the event.
	 * @return boolean True if valid, false if invalid.
	 */
	function verify_description(&$description)
	{
		$description = trim($description);
		if(!$description)
		{
			$this->set_error("no_description");
			return false;
		}
		return true;
	}

	/**
	 * Verifies if an event date is valid or not and attempts to fix it
	 *
	 * @param integer The day of the month the event lies on.
	 * @param integer The month of the year the event lies on.
	 * @param integer The year that the event lies on.
	 * @return boolean True if valid, false if invalid.
	 */
	function verify_date(&$day, &$month, &$year)
	{
		if(!$event['day'] || !$event['month'] || !$event['year'])
		{
			$this->set_error("invalid_date");
			return false;
		}
		// Check if the day actually exists.
		if($day > date("t", mktime(0, 0, 0, $month, 1, $year)))
		{
			$this->set_error("incorrect_day");
			return false;
		}
		return intval($day)."-".intval($month)."-".intval($year);
	}

	/**
	 * Verifies if the user is allowed to add public or private events.
	 *
	 * @param string If the event is private (yes) or not (no).
	 * @return boolean True or false depending on their permission.
	 */
	function verify_scope(&$private="no")
	{
		global $mybb;

		// If a private event
		if($private == "yes")
		{
			// Can the user add private events?
			if($mybb->user['uid'] == 0 || $mybb->usergroup['canaddprivateevents'] == "no")
			{
				$this->set_error("no_permission_private_event");
				return false;
			}
		}
		else
		{
			// Public event, got permission?
			if($mybb->usergroup['canaddpublicevents'] == "no")
			{
				$this->set_error("no_permission_public_event");
				return false;
			}
			// Default value
			$private = "no";
		}
		return true;
	}


	/**
	 * Validate an event.
	 *
	 * @param array The event data array.
	 */
	function validate_event($event)
	{
		// Every event needs a name.
		$this->verify_name($event['name']);

		// Check for event description.
		$this->verify_description($event['description']);

		// Check valid date & return formatted date.
		$event['date'] = $this->verify_date($event['day'], $event['month'], $event['year']);

		// Public event or private event?
		$this->verify_scope($event['private']);

		$plugins->run_hooks("datahandler_event_validate");
		
		// We are done validating, return.
		$this->set_validated(true);
		if(empty($this->get_errors()))
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Insert an event into the database.
	 *
	 * @param array The array of event data.
	 * @return array Array of new event details, eid and private.
	 */
	function insert_event($event)
	{
		global $db, $mybb, $plugins;
		
		// Yes, validating is required.
		if(!$this->get_validated)
		{
			die("The event needs to be validated before inserting it into the DB.");
		}
		if(!empty($this->get_errors()))
		{
			die("The event is not valid.");
		}
		
		// Prepare an array for insertion into the database.
		$newevent = array(
			"subject" => $db->escape_string($event['subject']),
			"author" => $mybb->user['uid'],
			"date" => $event['date'],
			"description" => $db->escape_string($event['description']),
			"private" => $event['private']
		);
		$db->insert_query(TABLE_PREFIX."events", $newevent);
		$eid = $db->insert_id();
		
		$plugins->run_hooks("datahandler_event_insert");
		
		// Return the event's eid and whether or not it is private.
		return array(
			"eid" => $eid,
			"private" => $event['options']['private']
		);
	}
	
	/**
	 * Updates an event that is already in the database.
	 *
	 * @param array The event data array.
	 */
	function update_event($event)
	{
		global $db;
		
		// Yes, validating is required.
		if(!$this->get_validated)
		{
			die("The event needs to be validated before inserting it into the DB.");
		}
		if(!empty($this->get_errors()))
		{
			die("The event is not valid.");
		}
		
		// Prepare an array for insertion into the database.
		$updateevent = array(
			"eid" => $event['eid'],
			"subject" => $db->escape_string($event['subject']),
			"date" => $event['date'],
			"description" => $db->escape_string($event['description']),
			"private" => $event['options']['private']
		);
		$db->insert_query(TABLE_PREFIX."events", $updateevent);
		
		$plugins->run_hooks("datahandler_event_update");
	}
	
	/**
	 * Delete an event from the database.
	 *
	 * @param int The event id of the even that is to be deleted.
	 */
	function delete_by_eid($eid)
	{
		global $db;
		
		$db->delete_query(TABLE_PREFIX."events", "eid=".$eid, 1);
		
		$plugins->run_hooks("datahandler_event_delete");
	}
}

?>