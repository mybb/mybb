<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
 * Event handling class, provides common structure to handle event data.
 *
 */
class EventDataHandler extends DataHandler
{
	/**
	 * The language file used in the data handler.
	 *
	 * @var string
	 */
	public $language_file = 'datahandler_event';

	/**
	 * The prefix for the language variables used in the data handler.
	 *
	 * @var string
	 */
	public $language_prefix = 'eventdata';

	/**
	 * Array of data inserted in to an event.
	 *
	 * @var array
	 */
	public $event_insert_data = array();

	/**
	 * Array of data used to update an event.
	 *
	 * @var array
	 */
	public $event_update_data = array();

	/**
	 * Event ID currently being manipulated by the datahandlers.
	 *
	 * @var int
	 */
	public $eid = 0;

	/**
	 * Values to be returned after inserting/updating an event.
	 *
	 * @var array
	 */
	public $return_values = array();

	/**
	 * Verifies if an event name is valid or not and attempts to fix it
	 *
	 * @return boolean True if valid, false if invalid.
	 */
	function verify_name()
	{
		$name = &$this->data['name'];
		$name = trim($name);
		if(!$name)
		{
			$this->set_error("missing_name");
			return false;
		}
		return true;
	}

	/**
	 * Verifies if an event description is valid or not and attempts to fix it
	 *
	 * @return boolean True if valid, false if invalid.
	 */
	function verify_description()
	{
		$description = &$this->data['description'];
		$description = trim($description);
		if(!$description)
		{
			$this->set_error("missing_description");
			return false;
		}
		return true;
	}

	/**
	 * Verifies if an event date is valid or not and attempts to fix it
	 *
	 * @return boolean True if valid, false if invalid.
	 */
	function verify_date()
	{
		$event = &$this->data;

		// All types of events require a start date
		if(!$event['start_date']['day'] || !$event['start_date']['month'] || !$event['start_date']['year'])
		{
			$this->set_error("invalid_start_date");
			return false;
		}

		$event['start_date']['day'] = (int)$event['start_date']['day'];
		$event['start_date']['month'] = (int)$event['start_date']['month'];
		$event['start_date']['year'] = (int)$event['start_date']['year'];

		if($event['start_date']['day'] > date("t", mktime(0, 0, 0, $event['start_date']['month'], 1, $event['start_date']['year'])))
		{
			$this->set_error("invalid_start_date");
			return false;
		}

		// Calendar events can only be within the next 5 years
		if($event['start_date']['year'] > date("Y") + 5)
		{
			$this->set_error("invalid_start_year");
			return false;
		}

		//Check to see if the month is within 1 and 12
		if($event['start_date']['month'] > 12 || $event['start_date']['month'] < 1)
		{
			$this->set_error("invalid_start_month");
			return false;
		}

		// For ranged events, we check the end date & times too
		if($event['type'] == "ranged")
		{
			if(!$event['end_date']['day'] || !$event['end_date']['month'] || !$event['end_date']['year'])
			{
				$this->set_error("invalid_end_date");
				return false;
			}

			$event['end_date']['day'] = (int)$event['end_date']['day'];
			$event['end_date']['month'] = (int)$event['end_date']['month'];
			$event['end_date']['year'] = (int)$event['end_date']['year'];

			if($event['end_date']['day'] > date("t", mktime(0, 0, 0, $event['end_date']['month'], 1, $event['end_date']['year'])))
			{
				$this->set_error("invalid_end_date");
				return false;
			}

			// Calendar events can only be within the next 5 years
			if($event['end_date']['year'] > date("Y") + 5)
			{
				$this->set_error("invalid_end_year");
				return false;
			}

			//Check to see if the month is within 1 and 12
			if($event['end_date']['month'] > 12 || $event['end_date']['month'] < 1)
			{
				$this->set_error("invalid_end_month");
				return false;
			}

			// Validate time input
			if($event['start_date']['time'] || $event['end_date']['time'])
			{
				if(($event['start_date']['time'] && !$event['end_date']['time']) || ($event['end_date']['time'] && !$event['start_date']['time']))
				{
					$this->set_error("cant_specify_one_time");
					return false;
				}

				// Begin start time validation
				$start_time = $this->verify_time($event['start_date']['time']);
				if(!is_array($start_time))
				{
					$this->set_error("start_time_invalid");
					return false;
				}

				// End time validation
				$end_time = $this->verify_time($event['end_date']['time']);
				if(!is_array($end_time))
				{
					$this->set_error("end_time_invalid");
					return false;
				}
				$event['usingtime'] = 1;
			}
			else
			{
				$start_time = array("hour" => 0, "min" => 0);
				$end_time = array("hour" => 23, "min" => 59);
				$event['usingtime'] = 0;
			}
		}

		if(array_key_exists('timezone', $event))
		{
			$event['timezone'] = (float)$event['timezone'];
			if($event['timezone'] > 12 || $event['timezone'] < -12)
			{
				$this->set_error("invalid_timezone");
				return false;
			}
			$start_time['hour'] -= $event['timezone'];
			$end_time['hour'] -= $event['timezone'];
		}

		if(!isset($start_time))
		{
			$start_time = array("hour" => 0, "min" => 0);
		}

		$start_timestamp = gmmktime($start_time['hour'], $start_time['min'], 0, $event['start_date']['month'], $event['start_date']['day'], $event['start_date']['year']);

		if($event['type'] == "ranged")
		{
			$end_timestamp = gmmktime($end_time['hour'], $end_time['min'], 0, $event['end_date']['month'], $event['end_date']['day'], $event['end_date']['year']);

			if($end_timestamp <= $start_timestamp)
			{
				$this->set_error("end_in_past");
				return false;
			}
		}

		if(!isset($end_timestamp))
		{
			$end_timestamp = 0;
		}

		// Save our time stamps for saving
		$event['starttime'] = $start_timestamp;
		$event['endtime'] = $end_timestamp;

		return true;
	}

	/**
	 * @param string $time
	 *
	 * @return array|bool
	 */
	function verify_time($time)
	{
		preg_match('#^(0?[1-9]|1[012])\s?([:\.]?)\s?([0-5][0-9])?(\s?[ap]m)|([01][0-9]|2[0-3])\s?([:\.])\s?([0-5][0-9])$#i', $time, $matches);
		if(count($matches) == 0)
		{
			return false;
		}

		// 24h time
		if(count($matches) == 8)
		{
			$hour = $matches[5];
			$min = $matches[7];
		}
		// 12 hour time
		else
		{
			$hour = $matches[1];
			$min = (int)$matches[3];
			$matches[4] = trim($matches[4]);
			if(my_strtolower($matches[4]) == "pm" && $hour != 12)
			{
				$hour += 12;
			}
			else if(my_strtolower($matches[4]) == "am" && $hour == 12)
			{
				$hour = 0;
			}
		}
		return array("hour" => $hour, "min" => $min);
	}

	/**
	 * @return bool
	 */
	function verify_repeats()
	{
		$event = &$this->data;

		if(!is_array($event['repeats']) || !$event['repeats']['repeats'])
		{
			return true;
		}

		if(!$event['endtime'])
		{
			$this->set_error("only_ranged_events_repeat");
			return false;
		}

		switch($event['repeats']['repeats'])
		{
			case 1:
				$event['repeats']['days'] = (int)$event['repeats']['days'];
				if($event['repeats']['days'] <= 0)
				{
					$this->set_error("invalid_repeat_day_interval");
					return false;
				}
			case 2:
				break;
			case 3:
				$event['repeats']['weeks'] = (int)$event['repeats']['weeks'];
				if($event['repeats']['weeks'] <= 0)
				{
					$this->set_error("invalid_repeat_week_interval");
					return false;
				}
				if(count($event['repeats']['days']) == 0)
				{
					$this->set_error("invalid_repeat_weekly_days");
					return false;
				}
				asort($event['repeats']['days']);
				break;
			case 4:
				if($event['repeats']['day'])
				{
					$event['repeats']['day'] = (int)$event['repeats']['day'];
					if($event['repeats']['day'] <= 0 || $event['repeats']['day'] > 31)
					{
						$this->set_error("invalid_repeat_day_interval");
						return false;
					}
				}
				else
				{
					if($event['repeats']['occurance'] != "last")
					{
						$event['repeats']['occurance'] = (int)$event['repeats']['occurance'];
					}
					$event['repeats']['weekday'] = (int)$event['repeats']['weekday'];
				}
				$event['repeats']['months'] = (int)$event['repeats']['months'];
				if($event['repeats']['months'] <= 0 || $event['repeats']['months'] > 12)
				{
					$this->set_error("invalid_repeat_month_interval");
					return false;
				}
				break;
			case 5:
				if($event['repeats']['day'])
				{
					$event['repeats']['day'] = (int)$event['repeats']['day'];
					if($event['repeats']['day'] <= 0 || $event['repeats']['day'] > 31)
					{
						$this->set_error("invalid_repeat_day_interval");
						return false;
					}
				}
				else
				{
					if($event['repeats']['occurance'] != "last")
					{
						$event['repeats']['occurance'] = (int)$event['repeats']['occurance'];
					}
					$event['repeats']['weekday'] = (int)$event['repeats']['weekday'];
				}
				$event['repeats']['month'] = (int)$event['repeats']['month'];
				if($event['repeats']['month'] <= 0 || $event['repeats']['month'] > 12)
				{
					$this->set_error("invalid_repeat_month_interval");
					return false;
				}
				$event['repeats']['years'] = (int)$event['repeats']['years'];
				if($event['repeats']['years'] <= 0 || $event['repeats']['years'] > 4)
				{
					$this->set_error("invalid_repeat_year_interval");
					return false;
				}
				break;
			default:
				$event['repeats'] = array();
		}
		require_once MYBB_ROOT."inc/functions_calendar.php";
		$event['starttime_user'] = $event['starttime'];
		$event['endtime_user'] = $event['endtime'];
		$next_occurance = fetch_next_occurance($event, array('start' => $event['starttime'], 'end' => $event['endtime']), $event['starttime'], true);
		if($next_occurance > $event['endtime'])
		{
			$this->set_error("event_wont_occur");
			return false;
		}
		return true;
	}

	/**
	 * Validate an event.
	 *
	 * @return bool
	 */
	function validate_event()
	{
		global $plugins;

		$event = &$this->data;

		if($this->method == "insert" || array_key_exists('name', $event))
		{
			$this->verify_name();
		}

		if($this->method == "insert" || array_key_exists('description', $event))
		{
			$this->verify_description();
		}

		if($this->method == "insert" || array_key_exists('start_date', $event) || array_key_exists('end_date', $event))
		{
			$this->verify_date();
		}

		if(($this->method == "insert" && $event['endtime']) || array_key_exists('repeats', $event))
		{
			$this->verify_repeats();
		}

		$plugins->run_hooks("datahandler_event_validate", $this);

		// We are done validating, return.
		$this->set_validated(true);
		if(count($this->get_errors()) > 0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * Insert an event into the database.
	 *
	 * @return array Array of new event details, eid and private.
	 */
	function insert_event()
	{
		global $db, $mybb, $plugins;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The event needs to be validated before inserting it into the DB.");
		}

		if(count($this->get_errors()) > 0)
		{
			die("The event is not valid.");
		}

		$event = &$this->data;

		$query = $db->simple_select("calendars", "*", "cid='".(int)$event['cid']."'");
		$calendar_moderation = $db->fetch_field($query, "moderation");
		if($calendar_moderation == 1 && (int)$event['private'] != 1)
		{
			$visible = 0;
			if($event['uid'] == $mybb->user['uid'])
			{
				$calendar_permissions = get_calendar_permissions($event['cid']);
				if($calendar_permissions['canbypasseventmod'] == 1)
				{
					$visible = 1;
				}
			}
		}
		else
		{
			$visible = 1;
		}

		// Prepare an array for insertion into the database.
		$this->event_insert_data = array(
			'cid' => (int)$event['cid'],
			'uid' => (int)$event['uid'],
			'name' => $db->escape_string($event['name']),
			'description' => $db->escape_string($event['description']),
			'visible' => $visible,
			'private' => (int)$event['private'],
			'dateline' => TIME_NOW,
			'starttime' => (int)$event['starttime'],
			'endtime' => (int)$event['endtime']
		);

		if(isset($event['timezone']))
		{
			$this->event_insert_data['timezone'] = $db->escape_string((float)$event['timezone']);
		}

		if(isset($event['ignoretimezone']))
		{
			$this->event_insert_data['ignoretimezone'] = (int)$event['ignoretimezone'];
		}

		if(isset($event['usingtime']))
		{
			$this->event_insert_data['usingtime'] = (int)$event['usingtime'];
		}

		if(isset($event['repeats']))
		{
			$this->event_insert_data['repeats'] = $db->escape_string(my_serialize($event['repeats']));
		}
		else
		{
			$this->event_insert_data['repeats'] = '';
		}

		$plugins->run_hooks("datahandler_event_insert", $this);

		$this->eid = $db->insert_query("events", $this->event_insert_data);

		// Return the event's eid and whether or not it is private.
		$this->return_values = array(
			'eid' => $this->eid,
			'private' => $event['private'],
			'visible' => $visible
		);

		$plugins->run_hooks("datahandler_event_insert_end", $this);

		return $this->return_values;
	}

	/**
	 * Updates an event that is already in the database.
	 *
	 * @return array
	 */
	function update_event()
	{
		global $db, $plugins;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The event needs to be validated before inserting it into the DB.");
		}

		if(count($this->get_errors()) > 0)
		{
			die("The event is not valid.");
		}

		$event = &$this->data;

		$this->eid = $event['eid'];

		if(isset($event['cid']))
		{
			$this->event_update_data['cid'] = $db->escape_string($event['cid']);
		}

		if(isset($event['name']))
		{
			$this->event_update_data['name'] = $db->escape_string($event['name']);
		}

		if(isset($event['description']))
		{
			$this->event_update_data['description'] = $db->escape_string($event['description']);
		}

		if(isset($event['starttime']))
		{
			$this->event_update_data['starttime'] = (int)$event['starttime'];
			$this->event_update_data['usingtime'] = (int)$event['usingtime'];
		}

		if(isset($event['endtime']))
		{
			$this->event_update_data['endtime'] = (int)$event['endtime'];
			$this->event_update_data['usingtime'] = (int)$event['usingtime'];
		}
		else
		{
			$this->event_update_data['endtime'] = 0;
			$this->event_update_data['usingtime'] = 0;
		}

		if(isset($event['repeats']))
		{
			if(!empty($event['repeats']))
			{
				$event['repeats'] = my_serialize($event['repeats']);
			}
			$this->event_update_data['repeats'] = $db->escape_string($event['repeats']);
		}

		if(isset($event['timezone']))
		{
			$this->event_update_data['timezone'] = $db->escape_string((float)$event['timezone']);
		}

		if(isset($event['ignoretimezone']))
		{
			$this->event_update_data['ignoretimezone'] = (int)$event['ignoretimezone'];
		}

		if(isset($event['private']))
		{
			$this->event_update_data['private'] = (int)$event['private'];
		}

		if(isset($event['visible']))
		{
			$this->event_update_data['visible'] = $db->escape_string($event['visible']);
		}

		if(isset($event['uid']))
		{
			$this->event_update_data['uid'] = (int)$event['uid'];
		}

		$plugins->run_hooks("datahandler_event_update", $this);

		$db->update_query("events", $this->event_update_data, "eid='".(int)$event['eid']."'");

		// Return the event's eid and whether or not it is private.
		$this->return_values = array(
			'eid' => $event['eid'],
			'private' => $event['private']
		);

		$plugins->run_hooks("datahandler_event_update_end", $this);

		return $this->return_values;
	}
}

