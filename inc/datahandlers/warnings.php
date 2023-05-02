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

	/**
	 * The stored data for the warning being written.
	 *
	 * @var array
	 */
	public $write_warning_data = array();

	/**
	 * The stored data for the warning being retrieved.
	 *
	 * @var array
	 */
	private $read_warning_data = array();

	/**
	 * Friendly redirect action after inserting a new warning.
	 *
	 * @var string
	 */
	public $friendly_action = '';

	/**
	* Validate a warning user assets.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function validate_user()
	{
		global $mybb;

		$warning = &$this->data;

		$user = get_user($warning['uid']);

		if(!$user['uid'])
		{
			$this->set_error('error_invalid_user');
			return false;
		}

		if($user['uid'] == $mybb->user['uid'])
		{
			$this->set_error('error_cannot_warn_self');
			return false;
		}

		if($user['warningpoints'] >= $mybb->settings['maxwarningpoints'])
		{
			$this->set_error('error_user_reached_max_warning');
			return false;
		}

		return true;
	}

	/**
	* Validate a warning post.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function validate_post()
	{
		$warning = &$this->data;

		$post = get_post($warning['pid']);

		if(!$post['pid'])
		{
			$this->set_error('error_invalid_post');
			return false;
		}

		return true;
	}

	/**
	* Validate a warning notes.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function validate_notes()
	{
		$warning = &$this->data;

		if(!trim($warning['notes']))
		{
			$this->set_error('error_no_note');
			return false;
		}

		return true;
	}

	/**
	* Validate maximum warnings per day for current user.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function validate_maximum()
	{
		global $mybb, $db, $lang;

		if($mybb->usergroup['maxwarningsday'] != 0)
		{
			$timecut = TIME_NOW-60*60*24;
			$query = $db->simple_select("warnings", "COUNT(wid) AS given_today", "issuedby='{$mybb->user['uid']}' AND dateline>'$timecut'");
			$given_today = $db->fetch_field($query, "given_today");
			if($given_today >= $mybb->usergroup['maxwarningsday'])
			{
				$this->set_error('reached_max_warnings_day', array(my_number_format($mybb->usergroup['maxwarningsday'])));
				return false;
			}
		}

		return true;
	}

	/**
	* Validate warnings type.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function validate_type()
	{
		global $mybb, $db;

		$warning = &$this->data;

		// Issuing a custom warning
		if($warning['type'] == 'custom')
		{
			if($mybb->settings['allowcustomwarnings'] == 0)
			{
				$this->set_error('error_cant_custom_warn');
				return false;
			}

			if(!$warning['custom_reason'])
			{
				$this->set_error('error_no_custom_reason');
				return false;
			}

			$warning['title'] = $warning['custom_reason'];

			if(!$warning['custom_points'] || $warning['custom_points'] > $mybb->settings['maxwarningpoints'] || $warning['custom_points'] < 0)
			{
				$this->set_error('error_invalid_custom_points', array(my_number_format($mybb->settings['maxwarningpoints'])));
				return false;
			}

			$warning['points'] = round($warning['custom_points']);

			// Build expiry date				
			if($warning['expires_period'] == "hours")
			{
				$warning['expires'] = $warning['expires']*3600 + TIME_NOW;
			}
			else if($warning['expires_period'] == "days")
			{
				$warning['expires'] = $warning['expires']*86400 + TIME_NOW;
			}
			else if($warning['expires_period'] == "weeks")
			{
				$warning['expires'] = $warning['expires']*604800 + TIME_NOW;
			}
			else if($warning['expires_period'] == "months")
			{
				$warning['expires'] = $warning['expires']*2592000 + TIME_NOW;
			}
			else if($warning['expires_period'] == "never")
			{
				$warning['expires'] = 0;
			}
			else
			{
				// unkown expires_period
				$this->set_error('error_invalid_expires_period');
				return false;
			}
		}
		// Using a predefined warning type
		else
		{
			$query = $db->simple_select("warningtypes", "*", "tid='".(int)$warning['type']."'");
			$this->warning_type = $db->fetch_array($query);

			if(!$this->warning_type)
			{
				$this->set_error('error_invalid_type');
				return false;
			}

			$warning['points'] = $this->warning_type['points'];
			$warning['title'] = '';
			$warning['expires'] = 0;
			
			if($this->warning_type['expirationtime'])
			{
				$warning['expires'] = TIME_NOW+$this->warning_type['expirationtime'];
			}
		}

		return true;
	}

	/**
	 * Validate a warning.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_warning()
	{
		global $plugins;

		$warning = &$this->data;

		// Verify all warning assets.
		$this->validate_user();
		$this->validate_maximum();
		$this->validate_notes();

		if(array_key_exists('pid', $warning))
		{
			$this->validate_post();
		}
		if(array_key_exists('type', $warning))
		{
			$this->validate_type();
		}

		$plugins->run_hooks("datahandler_warnings_validate_warning", $this);

		// We are done validating, return.
		$this->set_validated(true);

		if(count($this->get_errors()) > 0)
		{
			return false;
		}

		return true;
	}

	/**
	* Gets a valid warning from the DB engine.
	*
	* @param int $wid
	* @return array|bool array when valid, boolean false when invalid.
	*/
	function get($wid)
	{
		global $db;

		$wid = (int)$wid;
		if($wid <= 0)
		{
			return false;
		}

		$query = $db->simple_select("warnings", "*", "wid='".$wid."'");
		$this->read_warning_data = $db->fetch_array($query);

		if(!$this->read_warning_data['wid'])
		{
			return false;
		}

		return $this->read_warning_data;
	}

	/**
	* Expire old warnings in the database.
	*
	* @return boolean True when finished.
	*/
	function expire_warnings()
	{
		global $db;

		$users = array();

		$query = $db->query("
			SELECT w.wid, w.uid, w.points, u.warningpoints
			FROM ".TABLE_PREFIX."warnings w
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.uid)
			WHERE expires<".TIME_NOW." AND expires!=0 AND expired!=1
		");
		while($warning = $db->fetch_array($query))
		{
			$updated_warning = array(
				"expired" => 1
			);
			$db->update_query("warnings", $updated_warning, "wid='{$warning['wid']}'");

			if(array_key_exists($warning['uid'], $users))
			{
				$users[$warning['uid']] -= $warning['points'];
			}
			else
			{
				$users[$warning['uid']] = $warning['warningpoints']-$warning['points'];
			}
		}

		foreach($users as $uid => $warningpoints)
		{
			if($warningpoints < 0)
			{
				$warningpoints = 0;
			}

			$updated_user = array(
				"warningpoints" => (int)$warningpoints
			);
			$db->update_query("users", $updated_user, "uid='".(int)$uid."'");
		}

		return true;
	}

	/**
	* Updates an user warning details.
	*
	* @return array Updated user details.
	*/
	function update_user($method='insert')
	{
		global $db, $mybb, $lang, $cache, $groupscache;

		if($mybb->settings['maxwarningpoints'] < 1)
		{
			$mybb->settings['maxwarningpoints'] = 10;
		}

		if(!is_array($groupscache))
		{
			$groupscache = $cache->read("usergroups");
		}

		$warning = &$this->data;

		$user = get_user($warning['uid']);

		if($method == 'insert')
		{
			// Build warning level & ensure it doesn't go over 100.
			$current_level = round($user['warningpoints']/$mybb->settings['maxwarningpoints']*100);
			$this->new_warning_level = round(($user['warningpoints']+$warning['points'])/$mybb->settings['maxwarningpoints']*100);
			if($this->new_warning_level > 100)
			{
				$this->new_warning_level = 100;
			}

			// Update user
			$this->updated_user = array(
				"warningpoints" => $user['warningpoints']+$warning['points']
			);

			// Fetch warning level
			$query = $db->simple_select("warninglevels", "*", "percentage<={$this->new_warning_level}", array("order_by" => "percentage", "order_dir" => "desc"));
			$new_level = $db->fetch_array($query);

			if(!empty($new_level) && $new_level['lid'])
			{
				$expiration = 0;
				$action = my_unserialize($new_level['action']);

				if($action['length'] > 0)
				{
					$expiration = TIME_NOW+$action['length'];
				}

				switch($action['type'])
				{
					// Ban the user for a specified time
					case 1:
						// Fetch any previous bans for this user
						$query = $db->simple_select("banned", "*", "uid='{$user['uid']}' AND gid='{$action['usergroup']}' AND lifted>".TIME_NOW);
						$existing_ban = $db->fetch_array($query);

						// Only perform if no previous ban or new ban expires later than existing ban
						if(($expiration > $existing_ban['lifted'] && $existing_ban['lifted'] != 0) || $expiration == 0 || !$existing_ban['uid'])
						{
							if(!$warning['title'])
							{
								$warning['title'] = $this->warning_type['title'];
							}

							// Never lift the ban?
							if($action['length'] <= 0)
							{
								$bantime = '---';
							}
							else
							{
								$bantimes = fetch_ban_times();
								foreach($bantimes as $date => $string)
								{
									if($date == '---')
									{
										continue;
									}

									$time = 0;
									list($day, $month, $year) = explode('-', $date);
									if($day > 0)
									{
										$time += 60*60*24*$day;
									}

									if($month > 0)
									{
										$time += 60*60*24*30*$month;
									}

									if($year > 0)
									{
										$time += 60*60*24*365*$year;
									}

									if($time == $action['length'])
									{
										$bantime = $date;
										break;
									}
								}
							}

							$new_ban = array(
								"uid" => $user['uid'],
								"gid" => $action['usergroup'],
								"oldgroup" => $user['usergroup'],
								"oldadditionalgroups" => $db->escape_string($user['additionalgroups']),
								"olddisplaygroup" => $user['displaygroup'],
								"admin" => $mybb->user['uid'],
								"dateline" => TIME_NOW,
								"bantime" => $db->escape_string($bantime),
								"lifted" => $expiration,
								"reason" => $db->escape_string($warning['title'])
							);
							// Delete old ban for this user, taking details
							if($existing_ban['uid'])
							{
								$db->delete_query("banned", "uid='{$user['uid']}' AND gid='{$action['usergroup']}'");
								// Override new ban details with old group info
								$new_ban['oldgroup'] = $existing_ban['oldgroup'];
								$new_ban['oldadditionalgroups'] = $db->escape_string($existing_ban['oldadditionalgroups']);
								$new_ban['olddisplaygroup'] = $existing_ban['olddisplaygroup'];
							}

							$period = $lang->expiration_never;
							$ban_length = fetch_friendly_expiration($action['length']);

							if($ban_length['time'])
							{
								$lang_str = "expiration_".$ban_length['period'];
								$period = $lang->sprintf($lang->result_period, $ban_length['time'], $lang->$lang_str);
							}

							$group_name = $groupscache[$action['usergroup']]['title'];
							$this->friendly_action = $lang->sprintf($lang->redirect_warned_banned, $group_name, $period);

							$db->insert_query("banned", $new_ban);
							$this->updated_user['usergroup'] = $action['usergroup'];
							$this->updated_user['additionalgroups'] = '';
							$this->updated_user['displaygroup'] = 0;
						}
						break;
					// Suspend posting privileges
					case 2:
						// Only perform if the expiration time is greater than the users current suspension period
						if($expiration == 0 || $expiration > $user['suspensiontime'])
						{
							if(($user['suspensiontime'] != 0 && $user['suspendposting']) || !$user['suspendposting'])
							{
								$period = $lang->expiration_never;
								$ban_length = fetch_friendly_expiration($action['length']);

								if($ban_length['time'])
								{
									$lang_str = "expiration_".$ban_length['period'];
									$period = $lang->sprintf($lang->result_period, $ban_length['time'], $lang->$lang_str);
								}

								$this->friendly_action = $lang->sprintf($lang->redirect_warned_suspended, $period);

								$this->updated_user['suspensiontime'] = $expiration;
								$this->updated_user['suspendposting'] = 1;
							}
						}
						break;
					// Moderate new posts
					case 3:
						// Only perform if the expiration time is greater than the users current suspension period
						if($expiration == 0 || $expiration > $user['moderationtime'])
						{
							if(($user['moderationtime'] != 0 && $user['moderateposts']) || !$user['suspendposting'])
							{
								$period = $lang->expiration_never;
								$ban_length = fetch_friendly_expiration($action['length']);

								if($ban_length['time'])
								{
									$lang_str = "expiration_".$ban_length['period'];
									$period = $lang->sprintf($lang->result_period, $ban_length['time'], $lang->$lang_str);
								}

								$this->friendly_action = $lang->sprintf($lang->redirect_warned_moderate, $period);

								$this->updated_user['moderationtime'] = $expiration;
								$this->updated_user['moderateposts'] = 1;
							}
						}
						break;
				}
			}
		}
		else
		{
			// Warning is still active, lower users point count
			if($warning['expired'] != 1)
			{
				$new_warning_points = $user['warningpoints']-$warning['points'];
				if($new_warning_points < 0)
				{
					$new_warning_points = 0;
				}

				$this->updated_user = array(
					"warningpoints" => $new_warning_points
				);


				// check if we need to revoke any consequences with this warning
				$current_level = round($user['warningpoints']/$mybb->settings['maxwarningpoints']*100);
				$this->new_warning_level = round($new_warning_points/$mybb->settings['maxwarningpoints']*100);
				$query = $db->simple_select("warninglevels", "action", "percentage>{$this->new_warning_level} AND percentage<=$current_level");
				if($db->num_rows($query))
				{
					// we have some warning levels we need to revoke
					$max_expiration_times = $check_levels = array();
					find_warnlevels_to_check($query, $max_expiration_times, $check_levels);

					// now check warning levels already applied to this user to see if we need to lower any expiration times
					$query = $db->simple_select("warninglevels", "action", "percentage<={$this->new_warning_level}");
					$lower_expiration_times = $lower_levels = array();
					find_warnlevels_to_check($query, $lower_expiration_times, $lower_levels);

					// now that we've got all the info, do necessary stuff
					for($i = 1; $i <= 3; ++$i)
					{
						if($check_levels[$i])
						{
							switch($i)
							{
								case 1: // Ban
									// we'll have to resort to letting the admin/mod remove the ban manually, since there's an issue if stacked bans are in force...
									continue 2;
								case 2: // Revoke posting
									$current_expiry_field = 'suspensiontime';
									$current_inforce_field = 'suspendposting';
									break;
								case 3:
									$current_expiry_field = 'moderationtime';
									$current_inforce_field = 'moderateposts';
									break;
							}

							// if the thing isn't in force, don't bother with trying to update anything
							if(!$user[$current_inforce_field])
							{
								continue;
							}

							if($lower_levels[$i])
							{
								// lessen the expiration time if necessary

								if(!$lower_expiration_times[$i])
								{
									// doesn't expire - enforce this
									$this->updated_user[$current_expiry_field] = 0;
									continue;
								}

								if($max_expiration_times[$i])
								{
									// if the old level did have an expiry time...
									if($max_expiration_times[$i] <= $lower_expiration_times[$i])
									{
										// if the lower expiration time is actually higher than the upper expiration time -> skip
										continue;
									}
									// both new and old max expiry times aren't infinite, so we can take a difference
									$expire_offset = ($lower_expiration_times[$i] - $max_expiration_times[$i]);
								}
								else
								{
									// the old level never expired, not much we can do but try to estimate a new expiry time... which will just happen to be starting from today...
									$expire_offset = TIME_NOW + $lower_expiration_times[$i];
									// if the user's expiry time is already less than what we're going to set it to, skip
									if($user[$current_expiry_field] <= $expire_offset)
									{
										continue;
									}
								}

								$this->updated_user[$current_expiry_field] = $user[$current_expiry_field] + $expire_offset;
								// double-check if it's expired already
								if($this->updated_user[$current_expiry_field] < TIME_NOW)
								{
									$this->updated_user[$current_expiry_field] = 0;
									$this->updated_user[$current_inforce_field] = 0;
								}
							}
							else
							{
								// there's no lower level for this type - remove the consequence entirely
								$this->updated_user[$current_expiry_field] = 0;
								$this->updated_user[$current_inforce_field] = 0;
							}
						}
					}
				}
			}
		}

		// Save updated details
		$db->update_query("users", $this->updated_user, "uid='{$user['uid']}'");

		$mybb->cache->update_moderators();

		return $this->updated_user;
	}

	/**
	* Inserts a warning into the database
	*
	* @return array Warning database details.
	*/
	function insert_warning()
	{
		global $db, $mybb, $plugins;

		$warning = &$this->data;

		$this->write_warning_data = array(
			"uid" => (int)$warning['uid'],
			"tid" => (int)$warning['type'],
			"pid" => isset($warning['pid']) ? (int)$warning['pid'] : 0,
			"title" => $db->escape_string($warning['title']),
			"points" => (int)$warning['points'],
			"dateline" => TIME_NOW,
			"issuedby" => $mybb->user['uid'],
			"expires" => (int)$warning['expires'],
			"expired" => 0,
			"revokereason" => '',
			"notes" => $db->escape_string($warning['notes'])
		);

		$this->write_warning_data['wid'] = $db->insert_query("warnings", $this->write_warning_data);

		$this->update_user();

		$plugins->run_hooks("datahandler_warnings_insert_warning", $this);

		return $this->write_warning_data;
	}

	/**
	* Updates a warning in the database
	*
	* @return array Warning database details.
	*/
	function update_warning()
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

		$plugins->run_hooks("datahandler_warnings_update_warning", $this);

		$db->update_query("warnings", $this->write_warning_data, "wid='{$warning['wid']}'");

		$this->update_user('update');

		return $this->write_warning_data;
	}

}

