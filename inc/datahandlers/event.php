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
	 * Get an event from the database by event id.
	 *
	 * @param int The id of the event to be retrieved.
	 * @return array An array of event data.
	 */
	function get_event_by_eid($eid)
	{
		global $db;
		
		$eid = intval($eid);		
		$query = $db->query("
			SELECT subject, author, date, description, private
			FROM ".TABLE_PREFIX."events
			WHERE eid = ".$eid."
			LIMIT 1
		");
		$event = $db->fetch_array($query);
		
		return $event;
	}
	
	/**
	 * Insert an event into the database.
	 *
	 * @param array The array of event data.
	 */
	function insert_event($event)
	{
		global $db;
		
		if($this->get_validated !== true)
		{
			die("The event needs to be validated before inserting it into the DB.");
		}
		if(!empty($this->get_errors()))
		{
			die("The event is not valid.");
		}
		
		
		
		$db->insert_query(TABLE_PREFIX."events", $event);
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
		
		if($this->get_validated !== true)
		{
			die("The event needs to be validated before inserting it into the DB.");
		}
		if(!empty($this->get_errors()))
		{
			die("The event is not valid.");
		}
		
		$db->update_query(TABLE_PREFIX."events", $event, "eid = ".$eid);
	}
	
	/**
	 * Delete an event from the database.
	 *
	 * @param int The event id of the even that is to be deleted.
	 */
	function delete_event($eid)
	{
		global $db;
		
		$db->delete_query(TABLE_PREFIX."events", "eid = ".$eid, 1);
	}
	
	/**
	 * Validate an event.
	 *
	 * @param array The event data array.
	 */
	function validate_event($event)
	{
		if(!$event['name'])
		{
			$this->set_error("no_event_name");
		}
		
		/* We are done validating, return. */
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