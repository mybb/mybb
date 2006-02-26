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
			$this->set_error("no_event_name");
		}
		
		// EVENT VALIDATION CODE GOES HERE
		
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
		
		// EVENT INSERT CODE GOES HERE
		
		$plugins->run_hooks("datahandler_thread_insert");
		
		// Return the event's eid and whether or not it is private.
		return array(
			"eid" => $eid,
			"private" => $private
		);
	}
	
	/**
	 * Updates an event that is already in the database.
	 *
	 * @param array The event data array.
	 * @param int The event id of the event to update.
	 */
	function update_event($event, $eid)
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
		
		// EVENT UPDATE CODE GOES HERE
		
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
		
		// EVENT DELETE CODE GOES HERE
		
		$plugins->run_hooks("datahandler_event_delete");
	}
	
	/**
	 * Get the event poster.
	 *
	 * @param array The event data array.
	 * @return string The link to the event poster.
	 */
	function get_event_poster($event)
	{
		if($event['username'])
		{
			$event_poster = "<a href=\"member.php?action=profile&amp;uid=".$event['author']."\">" . formatname($event['username'], $event['usergroup'], $event['displaygroup']) . "</a>";
		}
		else
		{
			$event_poster = $lang->guest;
		}
		
		return $event_poster;
	}
	
	/**
	 * Get the event date.
	 *
	 * @param array The event data array.
	 * @return string The event date.
	 */
	function get_event_date($event)
	{
		$event_date = explode("-", $event['date']);
		$event_date = mktime(0, 0, 0, $event_date[1], $event_date[0], $event_date[2]);
		$event_date = mydate($mybb->settings['dateformat'], $event_date);
		
		return $event_date;
	}
}

?>