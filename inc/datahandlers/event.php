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
	 * Validate an event.
	 *
	 * @param array The event data array.
	 */
	function validate_event($event)
	{
		// Every event needs a name.
		if(!$event['name'])
		{
			$this->set_error("no_name");
		}
		
		if(!$event['day'] || !$event['month'] || !$event['year'])
		{
			$this->set_error("invalid_date");
		}

		// Check if the day actually exists.
		if($event['day'] > date("t", mktime(0, 0, 0, $event['month'], 1, $event['year'])))
		{
			$this->set_error("incorrect_day");
		}

		if(!trim($event['description']))
		{
			$this->set_error("no_description");
		}

		// Decide what type of event this is.
		if($event['private'] == "yes")
		{
			// Check if the user is allowed to add private events if he is trying to.
			if($mybb->user['uid'] == 0 || $mybb->usergroup['canaddprivateevents'] == "no")
			{
				$this->set_error("no_permission_private_event");
			}
		}
		else
		{
			// Check if the user is allowed to add public events if he is trying to.
			if($mybb->usergroup['canaddpublicevents'] == "no")
			{
				$this->set_error("no_permission_public_event");
			}
		}
		
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
	 * Get the options for an event.
	 *
	 * @param array The event data array.
	 * @return array The cleaned event data array.
	 */
	function get_options($event)
	{
		// Check if the event is private or public.
		if($event['private'] == "yes")
		{
			$event['options']['private'] = "yes";
		}
		else
		{
			$event['options']['private'] = "no";
		}
		
		// Figure out the event date.
		$event['date'] = intval($event['day'])."-".intval($event['month'])."-".intval($event['year']);
		return $event;
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
			"private" => $event['options']['private']
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