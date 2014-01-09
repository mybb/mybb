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
 * Login handling class, provides common structure to handle login events.
 *
 */
class WarningsHandler extends DataHandler
{
	/**
	 * The language file used in the data handler.
	 *
	 * @var string
	 */
	public $language_file = 'datahandler_warnings';

	/**
	 * The prefix for the language variables used in the data handler.
	 *
	 * @var string
	 */
	public $language_prefix = 'warnings';

	private $write_warning_data;
	private $read_warning_data;

	public function validate_maximum()
	{
		global $mybb, $db, $lang;

		if($mybb->usergroup['maxwarningsday'] != 0)
		{
			$timecut = TIME_NOW-60*60*24;
			$query = $db->simple_select("warnings", "COUNT(wid) AS given_today", "issuedby='{$mybb->user['uid']}' AND dateline>'$timecut'");
			$given_today = $db->fetch_field($query, "given_today");
			if($given_today >= $mybb->usergroup['maxwarningsday'])
			{
				$this->set_error('reached_max_warnings_day', array($mybb->usergroup['maxwarningsday']));
				return false;
			}
		}
	}

	public function get($wid)
	{
		$wid = (int)$wid;
		if($wid <= 0)
		{
			return false;
		}

		$query = $db->simple_select("warnings", "*", "wid='".$wid."'");
		$this->read_warning_data = $db->fetch_array($query);

		return $this->read_warning_data;
	}

	public function update_warning()
	{
		global $db, $mybb, $plugins;

		$warning = &$this->data;

		$warning['wid'] = (int)$warning['wid'];
		if($warning['wid'] <= 0)
		{
			return false;
		}

		$this->write_warning_data = array(
			"expired" => 1,
			"daterevoked" => TIME_NOW,
			"revokedby" => $mybb->user['uid'],
			"revokereason" => $db->escape_string($warning['reason'])
		);

		$db->update_query("warnings", $this->write_warning_data, "wid='{$warning['wid']}'");

		return $this->write_warning_data;
	}

	public function insert_warning()
	{
		global $db, $mybb, $plugins;

		$warning = &$this->data;

		$this->write_warning_data = array(
			"uid" => (int)$warning['uid'],
			"tid" => (int)$warning['tid'],
			"pid" => (int)$warning['pid'],
			"title" => $db->escape_string($warning['title']),
			"points" => (int)$warning['points'],
			"dateline" => TIME_NOW,
			"issuedby" => (int)$mybb->user['uid'],
			"expires" => $db->escape_string($warning['expires']),
			"expired" => 0,
			"revokereason" => '',
			"notes" => $db->escape_string($warning['notes'])
		);

		$this->write_warning_data['wid'] = $db->insert_query("warnings", $this->write_warning_data);

		return $this->write_warning_data;
	}
}
?>