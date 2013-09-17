<?php
/**
 * MyBB 1.8
 * Copyright 2013 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
 * User handling class, provides common structure to handle user data.
 *
 */
class UserDataHandler extends DataHandler
{
	/**
	* The language file used in the data handler.
	*
	* @var string
	*/
	public $language_file = 'datahandler_user';

	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	public $language_prefix = 'userdata';

	/**
	 * Array of data inserted in to a user.
	 *
	 * @var array
	 */
	public $user_insert_data = array();

	/**
	 * Array of data used to update a user.
	 *
	 * @var array
	 */
	public $user_update_data = array();

	/**
	 * User ID currently being manipulated by the datahandlers.
	 *
	 * @var int
	 */
	public $uid = 0;

	/**
	 * Verifies if a username is valid or invalid.
	 *
	 * @param boolean True when valid, false when invalid.
	 */
	function verify_username()
	{
		global $mybb;

		$username = &$this->data['username'];
		require_once MYBB_ROOT.'inc/functions_user.php';

		// Fix bad characters
		$username = trim_blank_chrs($username);
		$username = str_replace(array(unichr(160), unichr(173), unichr(0xCA), dec_to_utf8(8238), dec_to_utf8(8237), dec_to_utf8(8203)), array(" ", "-", "", "", "", ""), $username);

		// Remove multiple spaces from the username
		$username = preg_replace("#\s{2,}#", " ", $username);

		// Check if the username is not empty.
		if($username == '')
		{
			$this->set_error('missing_username');
			return false;
		}

		// Check if the username belongs to the list of banned usernames.
		if(is_banned_username($username, true))
		{
			$this->set_error('banned_username');
			return false;
		}

		// Check for certain characters in username (<, >, &, commas and slashes)
		if(strpos($username, "<") !== false || strpos($username, ">") !== false || strpos($username, "&") !== false || my_strpos($username, "\\") !== false || strpos($username, ";") !== false || strpos($username, ",") !== false)
		{
			$this->set_error("bad_characters_username");
			return false;
		}

		// Check if the username is of the correct length.
		if(($mybb->settings['maxnamelength'] != 0 && my_strlen($username) > $mybb->settings['maxnamelength']) || ($mybb->settings['minnamelength'] != 0 && my_strlen($username) < $mybb->settings['minnamelength']))
		{
			$this->set_error('invalid_username_length', array($mybb->settings['minnamelength'], $mybb->settings['maxnamelength']));
			return false;
		}

		return true;
	}

	/**
	 * Verifies if a usertitle is valid or invalid.
	 *
	 * @param boolean True when valid, false when invalid.
	 */
	function verify_usertitle()
	{
		global $mybb;

		$usertitle = &$this->data['usertitle'];

		// Check if the usertitle is of the correct length.
		if($mybb->settings['customtitlemaxlength'] != 0 && my_strlen($usertitle) > $mybb->settings['customtitlemaxlength'])
		{
			$this->set_error('invalid_usertitle_length', $mybb->settings['customtitlemaxlength']);
			return false;
		}

		return true;
	}

	/**
	 * Verifies if a username is already in use or not.
	 *
	 * @return boolean False when the username is not in use, true when it is.
	 */
	function verify_username_exists()
	{
		global $db;

		$username = &$this->data['username'];

		$uid_check = "";
		if(!empty($this->data['uid']))
		{
			$uid_check = " AND uid!='{$this->data['uid']}'";
		}

		$query = $db->simple_select("users", "COUNT(uid) AS count", "LOWER(username)='".$db->escape_string(strtolower(trim($username)))."'{$uid_check}");

		$user_count = $db->fetch_field($query, "count");
		if($user_count > 0)
		{
			$this->set_error("username_exists", array($username));
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Verifies if a new password is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_password()
	{
		global $mybb;

		$user = &$this->data;

		// Always check for the length of the password.
		if(my_strlen($user['password']) < $mybb->settings['minpasswordlength'] || my_strlen($user['password']) > $mybb->settings['maxpasswordlength'])
		{
			$this->set_error('invalid_password_length', array($mybb->settings['minpasswordlength'], $mybb->settings['maxpasswordlength']));
			return false;
		}

		// See if the board has "require complex passwords" enabled.
		if($mybb->settings['requirecomplexpasswords'] == 1)
		{
			// Complex passwords required, do some extra checks.
			// First, see if there is one or more complex character(s) in the password.
			if(!preg_match("/^.*(?=.{".$mybb->settings['minpasswordlength'].",})(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$/", $user['password']))
			{
				$this->set_error('no_complex_characters', array($mybb->settings['minpasswordlength']));
				return false;
			}
		}

		// If we have a "password2" check if they both match
		if(isset($user['password2']) && $user['password'] != $user['password2'])
		{
			$this->set_error("passwords_dont_match");
			return false;
		}

		// MD5 the password
		$user['md5password'] = md5($user['password']);

		// Generate our salt
		$user['salt'] = generate_salt();

		// Combine the password and salt
		$user['saltedpw'] = salt_password($user['md5password'], $user['salt']);

		// Generate the user login key
		$user['loginkey'] = generate_loginkey();

		return true;
	}

	/**
	* Verifies usergroup selections and other group details.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_usergroup()
	{
		$user = &$this->data;
		return true;
	}
	/**
	* Verifies if an email address is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_email()
	{
		global $mybb;

		$user = &$this->data;

		// Check if an email address has actually been entered.
		if(trim_blank_chrs($user['email']) == '')
		{
			$this->set_error('missing_email');
			return false;
		}

		// Check if this is a proper email address.
		if(!validate_email_format($user['email']))
		{
			$this->set_error('invalid_email_format');
			return false;
		}

		// Check banned emails
		if(is_banned_email($user['email'], true))
		{
			$this->set_error('banned_email');
			return false;
		}

		// Check signed up emails
		// Ignore the ACP because the Merge System sometimes produces users with duplicate email addresses (Not A Bug)
		if($mybb->settings['allowmultipleemails'] == 0 && !defined("IN_ADMINCP"))
		{
			$uid = 0;
			if(isset($user['uid']))
			{
				$uid = $user['uid'];
			}
			if(email_already_in_use($user['email'], $uid))
			{
				$this->set_error('email_already_in_use');
				return false;
			}
		}

		// If we have an "email2", verify it matches the existing email
		if(isset($user['email2']) && $user['email'] != $user['email2'])
		{
			$this->set_error("emails_dont_match");
			return false;
		}

		return true;
	}

	/**
	* Verifies if a website is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_website()
	{
		$website = &$this->data['website'];

		if(empty($website) || my_strtolower($website) == 'http://' || my_strtolower($website) == 'https://')
		{
			$website = '';
			return true;
		}

		// Does the website start with http(s)://?
		if(my_strtolower(substr($website, 0, 4)) != "http")
		{
			// Website does not start with http://, let's see if the user forgot.
			$website = "http://".$website;
		}

		return true;
	}

	/**
	 * Verifies if an ICQ number is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_icq()
	{
		$icq = &$this->data['icq'];

		if($icq != '' && !is_numeric($icq))
		{
			$this->set_error("invalid_icq_number");
			return false;
		}
		$icq = intval($icq);
		return true;
	}

	/**
	 * Verifies if an MSN Messenger address is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_msn()
	{
		$msn = &$this->data['msn'];

		if($msn != '' && validate_email_format($msn) == false)
		{
			$this->set_error("invalid_msn_address");
			return false;
		}
		return true;
	}

	/**
	* Verifies if a birthday is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_birthday()
	{
		global $mybb;

		$user = &$this->data;
		$birthday = &$user['birthday'];

		if(!is_array($birthday))
		{
			return true;
		}

		// Sanitize any input we have
		$birthday['day'] = intval($birthday['day']);
		$birthday['month'] = intval($birthday['month']);
		$birthday['year'] = intval($birthday['year']);

		// Error if a day and month exists, and the birthday day and range is not in range
		if($birthday['day'] != 0 || $birthday['month'] != 0)
		{
			if($birthday['day'] < 1 || $birthday['day'] > 31 || $birthday['month'] < 1 || $birthday['month'] > 12 || ($birthday['month'] == 2 && $birthday['day'] > 29))
			{
				$this->set_error("invalid_birthday");
				return false;
			}
		}

		// Check if the day actually exists.
		$months = get_bdays($birthday['year']);
		if($birthday['month'] != 0 && $birthday['day'] > $months[$birthday['month']-1])
		{
			$this->set_error("invalid_birthday");
			return false;
		}

		// Error if a year exists and the year is out of range
		if($birthday['year'] != 0 && ($birthday['year'] < (date("Y")-100)) || $birthday['year'] > date("Y"))
		{
			$this->set_error("invalid_birthday");
			return false;
		}
		else if($birthday['year'] == date("Y"))
		{
			// Error if birth date is in future
			if($birthday['month'] > date("m") || ($birthday['month'] == date("m") && $birthday['day'] > date("d")))
			{
				$this->set_error("invalid_birthday");
				return false;
			}
		}

		// Error if COPPA is on, and the user hasn't verified their age / under 13
		if($mybb->settings['coppa'] == "enabled" && ($birthday['year'] == 0 || !$birthday['year']))
		{
			$this->set_error("invalid_birthday_coppa");
			return false;
		}
		elseif($mybb->settings['coppa'] == "deny" && $birthday['year'] > (date("Y")-13))
		{
			$this->set_error("invalid_birthday_coppa2");
			return false;
		}

		// Make the user's birthday field
		if($birthday['year'] != 0)
		{
			// If the year is specified, put together a d-m-y string
			$user['bday'] = $birthday['day']."-".$birthday['month']."-".$birthday['year'];
		}
		elseif($birthday['day'] && $birthday['month'])
		{
			// If only a day and month are specified, put together a d-m string
			$user['bday'] = $birthday['day']."-".$birthday['month']."-";
		}
		else
		{
			// No field is specified, so return an empty string for an unknown birthday
			$user['bday'] = '';
		}
		return true;
	}

	/**
	 * Verifies if the birthday privacy option is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_birthday_privacy()
	{
		$birthdayprivacy = &$this->data['birthdayprivacy'];
		$accepted = array(
					'none',
					'age',
					'all');

		if(!in_array($birthdayprivacy, $accepted))
		{
			$this->set_error("invalid_birthday_privacy");
			return false;
		}
		return true;
	}

	/**
	* Verifies if the post count field is filled in correctly.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_postnum()
	{
		$user = &$this->data;

		if(isset($user['postnum']) && $user['postnum'] < 0)
		{
			$this->set_error("invalid_postnum");
			return false;
		}

		return true;
	}

	/**
	* Verifies if a profile fields are filled in correctly.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_profile_fields()
	{
		global $db;

		$user = &$this->data;
		$profile_fields = &$this->data['profile_fields'];

		// Loop through profile fields checking if they exist or not and are filled in.
		$userfields = array();
		$comma = '';
		$editable = '';

		if(empty($this->data['profile_fields_editable']))
		{
			$editable = "editable=1";
		}

		// Fetch all profile fields first.
		$options = array(
			'order_by' => 'disporder'
		);
		$query = $db->simple_select('profilefields', 'name, type, fid, required, maxlength', $editable, $options);

		// Then loop through the profile fields.
		while($profilefield = $db->fetch_array($query))
		{
			$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
			$thing = explode("\n", $profilefield['type'], "2");
			$type = trim($thing[0]);
			$field = "fid{$profilefield['fid']}";
			
			if(!isset($profile_fields[$field]))
			{
				$profile_fields[$field] = '';
			}

			// If the profile field is required, but not filled in, present error.
			if($type != "multiselect" && $type != "checkbox")
			{
				if(trim($profile_fields[$field]) == "" && $profilefield['required'] == 1 && !defined('IN_ADMINCP') && THIS_SCRIPT != "modcp.php")
				{
					$this->set_error('missing_required_profile_field', array($profilefield['name']));
				}
			}
			elseif(($type == "multiselect" || $type == "checkbox") && $profile_fields[$field] == "" && $profilefield['required'] == 1 && !defined('IN_ADMINCP') && THIS_SCRIPT != "modcp.php")
			{
				$this->set_error('missing_required_profile_field', array($profilefield['name']));
			}

			// Sort out multiselect/checkbox profile fields.
			$options = '';
			if(($type == "multiselect" || $type == "checkbox") && is_array($profile_fields[$field]))
			{
				$expoptions = explode("\n", $thing[1]);
				$expoptions = array_map('trim', $expoptions);
				foreach($profile_fields[$field] as $value)
				{
					if(!in_array(htmlspecialchars_uni($value), $expoptions))
					{
						$this->set_error('bad_profile_field_values', array($profilefield['name']));
					}
					if($options)
					{
						$options .= "\n";
					}
					$options .= $db->escape_string($value);
				}
			}
			elseif($type == "select" || $type == "radio")
			{
				$expoptions = explode("\n", $thing[1]);
				$expoptions = array_map('trim', $expoptions);
				if(!in_array(htmlspecialchars_uni($profile_fields[$field]), $expoptions) && trim($profile_fields[$field]) != "")
				{
					$this->set_error('bad_profile_field_values', array($profilefield['name']));
				}
				$options = $db->escape_string($profile_fields[$field]);
			}
			elseif($type == "textarea")
			{
				if($profilefield['maxlength'] > 0 && my_strlen($profile_fields[$field]) > $profilefield['maxlength'])
				{
					$this->set_error('max_limit_reached', array($profilefield['name'], $profilefield['maxlength']));
				}

				$options = $db->escape_string($profile_fields[$field]);
			}
			else
			{
				if($profilefield['maxlength'] > 0 && my_strlen($profile_fields[$field]) > $profilefield['maxlength'])
				{
					$this->set_error('max_limit_reached', array($profilefield['name'], $profilefield['maxlength']));
				}

				$options = $db->escape_string($profile_fields[$field]);
			}
			$user['user_fields'][$field] = $options;
		}

		return true;
	}

	/**
	* Verifies if an optionally entered referrer exists or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_referrer()
	{
		global $db, $mybb;

		$user = &$this->data;

		// Does the referrer exist or not?
		if($mybb->settings['usereferrals'] == 1 && $user['referrer'] != '')
		{
			$query = $db->simple_select('users', 'uid', "username='".$db->escape_string($user['referrer'])."'", array('limit' => 1));
			$referrer = $db->fetch_array($query);
			if(!$referrer['uid'])
			{
				$this->set_error('invalid_referrer', array($user['referrer']));
				return false;
			}
			$user['referrer_uid'] = $referrer['uid'];
		}
		else
		{
			$user['referrer_uid'] = 0;
		}

		return true;
	}

	/**
	* Verifies user options.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_options()
	{
		global $mybb;

		$options = &$this->data['options'];

		// Verify yes/no options.
		$this->verify_yesno_option($options, 'allownotices', 1);
		$this->verify_yesno_option($options, 'hideemail', 0);
		$this->verify_yesno_option($options, 'emailpmnotify', 0);
		$this->verify_yesno_option($options, 'receivepms', 1);
		$this->verify_yesno_option($options, 'receivefrombuddy', 0);
		$this->verify_yesno_option($options, 'pmnotice', 1);
		$this->verify_yesno_option($options, 'pmnotify', 1);
		$this->verify_yesno_option($options, 'invisible', 0);
		$this->verify_yesno_option($options, 'showsigs', 1);
		$this->verify_yesno_option($options, 'showavatars', 1);
		$this->verify_yesno_option($options, 'showquickreply', 1);
		$this->verify_yesno_option($options, 'showredirect', 1);

		if($mybb->settings['postlayout'] == 'classic')
		{
			$this->verify_yesno_option($options, 'classicpostbit', 1);
		}
		else
		{
			$this->verify_yesno_option($options, 'classicpostbit', 0);
		}

		if(array_key_exists('subscriptionmethod', $options))
		{
			// Value out of range
			$options['subscriptionmethod'] = intval($options['subscriptionmethod']);
			if($options['subscriptionmethod'] < 0 || $options['subscriptionmethod'] > 2)
			{
				$options['subscriptionmethod'] = 0;
			}
		}

		if(array_key_exists('dstcorrection', $options))
		{
			// Value out of range
			$options['dstcorrection'] = intval($options['dstcorrection']);
			if($options['dstcorrection'] < 0 || $options['dstcorrection'] > 2)
			{
				$options['dstcorrection'] = 0;
			}
		}

		if($options['dstcorrection'] == 1)
		{
			$options['dst'] = 1;
		}
		else if($options['dstcorrection'] == 0)
		{
			$options['dst'] = 0;
		}

		if(isset($options['showcodebuttons']))
        {
            $options['showcodebuttons'] = intval($options['showcodebuttons']);
            if($options['showcodebuttons'] != 0)
            {
                $options['showcodebuttons'] = 1;
            }
        }
        else if($this->method == "insert")
        {
            $options['showcodebuttons'] = 1;
        }

		if($this->method == "insert" || (isset($options['threadmode']) && $options['threadmode'] != "linear" && $options['threadmode'] != "threaded"))
		{
			if($mybb->settings['threadusenetstyle'])
			{
				$options['threadmode'] = 'threaded';
			}
			else
			{
				$options['threadmode'] = 'linear';
			}
		}

		// Verify the "threads per page" option.
		if($this->method == "insert" || (array_key_exists('tpp', $options) && $mybb->settings['usertppoptions']))
		{
			if(!isset($options['tpp']))
			{
				$options['tpp'] = 0;
			}
			$explodedtpp = explode(",", $mybb->settings['usertppoptions']);
			if(is_array($explodedtpp))
			{
				@asort($explodedtpp);
				$biggest = $explodedtpp[count($explodedtpp)-1];
				// Is the selected option greater than the allowed options?
				if($options['tpp'] > $biggest)
				{
					$options['tpp'] = $biggest;
				}
			}
			$options['tpp'] = intval($options['tpp']);
		}
		// Verify the "posts per page" option.
		if($this->method == "insert" || (array_key_exists('ppp', $options) && $mybb->settings['userpppoptions']))
		{
			if(!isset($options['ppp']))
			{
				$options['ppp'] = 0;
			}
			$explodedppp = explode(",", $mybb->settings['userpppoptions']);
			if(is_array($explodedppp))
			{
				@asort($explodedppp);
				$biggest = $explodedppp[count($explodedppp)-1];
				// Is the selected option greater than the allowed options?
				if($options['ppp'] > $biggest)
				{
					$options['ppp'] = $biggest;
				}
			}
			$options['ppp'] = intval($options['ppp']);
		}
		// Is our selected "days prune" option valid or not?
		if($this->method == "insert" || array_key_exists('daysprune', $options))
		{
			if(!isset($options['daysprune']))
			{
				$options['daysprune'] = 0;
			}
			$options['daysprune'] = intval($options['daysprune']);
			if($options['daysprune'] < 0)
			{
				$options['daysprune'] = 0;
			}
		}
		$this->data['options'] = $options;
	}

	/**
	 * Verifies if a registration date is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_regdate()
	{
		$regdate = &$this->data['regdate'];

		$regdate = intval($regdate);
		// If the timestamp is below 0, set it to the current time.
		if($regdate <= 0)
		{
			$regdate = TIME_NOW;
		}
		return true;
	}

	/**
	 * Verifies if a last visit date is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_lastvisit()
	{
		$lastvisit = &$this->data['lastvisit'];

		$lastvisit = intval($lastvisit);
		// If the timestamp is below 0, set it to the current time.
		if($lastvisit <= 0)
		{
			$lastvisit = TIME_NOW;
		}
		return true;

	}

	/**
	 * Verifies if a last active date is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_lastactive()
	{
		$lastactive = &$this->data['lastactive'];

		$lastactive = intval($lastactive);
		// If the timestamp is below 0, set it to the current time.
		if($lastactive <= 0)
		{
			$lastactive = TIME_NOW;
		}
		return true;

	}

	/**
	 * Verifies if an away mode status is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_away()
	{
		global $mybb;

		$user = &$this->data;
		// If the board does not allow "away mode" or the user is marking as not away, set defaults.
		if($mybb->settings['allowaway'] == 0 || !isset($user['away']['away']) || $user['away']['away'] != 1)
		{
			$user['away']['away'] = 0;
			$user['away']['date'] = 0;
			$user['away']['returndate'] = 0;
			$user['away']['awayreason'] = '';
			return true;
		}
		else if($user['away']['returndate'])
		{
			list($returnday, $returnmonth, $returnyear) = explode('-', $user['away']['returndate']);
			if(!$returnday || !$returnmonth || !$returnyear)
			{
				$this->set_error("missing_returndate");
				return false;
			}

			// Validate the return date lengths
			$user['away']['returndate'] = substr($returnday, 0, 2).'-'.substr($returnmonth, 0, 2).'-'.substr($returnyear, 0, 4);
		}
		return true;
	}

	/**
	 * Verifies if a langage is valid for this user or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_language()
	{
		global $lang;

		$language = &$this->data['language'];

		// An invalid language has been specified?
		if($language != '' && !$lang->language_exists($language))
		{
			$this->set_error("invalid_language");
			return false;
		}
		return true;
	}

	/**
	 * Verifies if this is coming from a spam bot or not
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_checkfields()
	{
		$user = &$this->data;

		// An invalid language has been specified?
		if($user['regcheck1'] !== "" || $user['regcheck2'] !== "true")
		{
			$this->set_error("invalid_checkfield");
			return false;
		}
		return true;
	}

	/**
	* Validate all user assets.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function validate_user()
	{
		global $mybb, $plugins;

		$user = &$this->data;

		// First, grab the old user details if this user exists
		if(!empty($user['uid']))
		{
			$old_user = get_user($user['uid']);
		}

		if($this->method == "insert" || array_key_exists('username', $user))
		{
			// If the username is the same - no need to verify
			if(!isset($old_user['username']) || $user['username'] != $old_user['username'])
			{
				$this->verify_username();
				$this->verify_username_exists();
			}
			else
			{
				unset($user['username']);
			}
		}
		if($this->method == "insert" || array_key_exists('usertitle', $user))
		{
			$this->verify_usertitle();
		}
		if($this->method == "insert" || array_key_exists('password', $user))
		{
			$this->verify_password();
		}
		if($this->method == "insert" || array_key_exists('usergroup', $user))
		{
			$this->verify_usergroup();
		}
		if($this->method == "insert" || array_key_exists('email', $user))
		{
			$this->verify_email();
		}
		if($this->method == "insert" || array_key_exists('website', $user))
		{
			$this->verify_website();
		}
		if($this->method == "insert" || array_key_exists('icq', $user))
		{
			$this->verify_icq();
		}
		if($this->method == "insert" || array_key_exists('msn', $user))
		{
			$this->verify_msn();
		}
		if($this->method == "insert" || (isset($user['birthday']) && is_array($user['birthday'])))
		{
			$this->verify_birthday();
		}
		if($this->method == "insert" || array_key_exists('postnum', $user))
		{
			$this->verify_postnum();
		}
		if($this->method == "insert" || array_key_exists('profile_fields', $user))
		{
			$this->verify_profile_fields();
		}
		if($this->method == "insert" || array_key_exists('referrer', $user))
		{
			$this->verify_referrer();
		}
		if($this->method == "insert" || array_key_exists('options', $user))
		{
			$this->verify_options();
		}
		if($this->method == "insert" || array_key_exists('regdate', $user))
		{
			$this->verify_regdate();
		}
		if($this->method == "insert" || array_key_exists('lastvisit', $user))
		{
			$this->verify_lastvisit();
		}
		if($this->method == "insert" || array_key_exists('lastactive', $user))
		{
			$this->verify_lastactive();
		}
		if($this->method == "insert" || array_key_exists('away', $user))
		{
			$this->verify_away();
		}
		if($this->method == "insert" || array_key_exists('language', $user))
		{
			$this->verify_language();
		}
		if($this->method == "insert" && array_key_exists('regcheck1', $user) && array_key_exists('regcheck2', $user))
		{
			$this->verify_checkfields();
		}
		if(array_key_exists('birthdayprivacy', $user))
		{
			$this->verify_birthday_privacy();
		}

		$plugins->run_hooks("datahandler_user_validate", $this);

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
	* Inserts a user into the database.
	*/
	function insert_user()
	{
		global $db, $cache, $plugins;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The user needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The user is not valid.");
		}

		$user = &$this->data;
		
		$array = array('postnum', 'avatar', 'avatartype', 'additionalgroups', 'displaygroup', 'icq', 'aim',
			'yahoo', 'msn', 'bday', 'signature', 'style', 'dateformat', 'timeformat', 'notepad');
		foreach($array as $value)
		{
			if(!isset($user[$value]))
			{
				$user[$value] = '';
			}
		}

		$this->user_insert_data = array(
			"username" => $db->escape_string($user['username']),
			"password" => $user['saltedpw'],
			"salt" => $user['salt'],
			"loginkey" => $user['loginkey'],
			"email" => $db->escape_string($user['email']),
			"postnum" => intval($user['postnum']),
			"avatar" => $db->escape_string($user['avatar']),
			"avatartype" => $db->escape_string($user['avatartype']),
			"usergroup" => intval($user['usergroup']),
			"additionalgroups" => $db->escape_string($user['additionalgroups']),
			"displaygroup" => intval($user['displaygroup']),
			"usertitle" => $db->escape_string(htmlspecialchars_uni($user['usertitle'])),
			"regdate" => intval($user['regdate']),
			"lastactive" => intval($user['lastactive']),
			"lastvisit" => intval($user['lastvisit']),
			"website" => $db->escape_string(htmlspecialchars($user['website'])),
			"icq" => intval($user['icq']),
			"aim" => $db->escape_string(htmlspecialchars($user['aim'])),
			"yahoo" => $db->escape_string(htmlspecialchars($user['yahoo'])),
			"msn" => $db->escape_string(htmlspecialchars($user['msn'])),
			"birthday" => $user['bday'],
			"signature" => $db->escape_string($user['signature']),
			"allownotices" => $user['options']['allownotices'],
			"hideemail" => $user['options']['hideemail'],
			"subscriptionmethod" => intval($user['options']['subscriptionmethod']),
			"receivepms" => $user['options']['receivepms'],
			"receivefrombuddy" => $user['options']['receivefrombuddy'],
			"pmnotice" => $user['options']['pmnotice'],
			"pmnotify" => $user['options']['emailpmnotify'],
			"showsigs" => $user['options']['showsigs'],
			"showavatars" => $user['options']['showavatars'],
			"showquickreply" => $user['options']['showquickreply'],
			"showredirect" => $user['options']['showredirect'],
			"tpp" => intval($user['options']['tpp']),
			"ppp" => intval($user['options']['ppp']),
			"invisible" => $user['options']['invisible'],
			"style" => intval($user['style']),
			"timezone" => $db->escape_string($user['timezone']),
			"dstcorrection" => intval($user['options']['dstcorrection']),
			"threadmode" => $user['options']['threadmode'],
			"daysprune" => intval($user['options']['daysprune']),
			"dateformat" => $db->escape_string($user['dateformat']),
			"timeformat" => $db->escape_string($user['timeformat']),
			"regip" => $db->escape_binary($user['regip']),
			"language" => $db->escape_string($user['language']),
			"showcodebuttons" => $user['options']['showcodebuttons'],
			"away" => $user['away']['away'],
			"awaydate" => $user['away']['date'],
			"returndate" => $user['away']['returndate'],
			"awayreason" => $db->escape_string($user['away']['awayreason']),
			"notepad" => $db->escape_string($user['notepad']),
			"referrer" => intval($user['referrer_uid']),
			"referrals" => 0,
			"buddylist" => '',
			"ignorelist" => '',
			"pmfolders" => '',
			"notepad" => '',
			"warningpoints" => 0,
			"moderateposts" => 0,
			"moderationtime" => 0,
			"suspendposting" => 0,
			"suspensiontime" => 0,
			"coppauser" => intval($user['coppa_user']),
			"classicpostbit" => $user['options']['classicpostbit'],
			"usernotes" => ''
		);

		if($user['options']['dstcorrection'] == 1)
		{
			$this->user_insert_data['dst'] = 1;
		}
		else if($user['options']['dstcorrection'] == 0)
		{
			$this->user_insert_data['dst'] = 0;
		}

		$plugins->run_hooks("datahandler_user_insert", $this);

		$this->uid = $db->insert_query("users", $this->user_insert_data);

		$user['user_fields']['ufid'] = $this->uid;

		$query = $db->simple_select("profilefields", "fid");
		while($profile_field = $db->fetch_array($query))
		{
			if(array_key_exists("fid{$profile_field['fid']}", $user['user_fields']))
			{
				continue;
			}
			$user['user_fields']["fid{$profile_field['fid']}"] = '';
		}

		$db->insert_query("userfields", $user['user_fields'], false);

		if($this->user_insert_data['referrer'] != 0)
		{
			$db->write_query("
				UPDATE ".TABLE_PREFIX."users
				SET referrals=referrals+1
				WHERE uid='{$this->user_insert_data['referrer']}'
			");
		}

		// Update forum stats
		update_stats(array('numusers' => '+1'));

		return array(
			"uid" => $this->uid,
			"username" => $user['username'],
			"loginkey" => $user['loginkey'],
			"email" => $user['email'],
			"password" => $user['password'],
			"usergroup" => $user['usergroup']
		);
	}

	/**
	* Updates a user in the database.
	*/
	function update_user()
	{
		global $db, $plugins, $cache;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The user needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The user is not valid.");
		}

		$user = &$this->data;
		$user['uid'] = intval($user['uid']);
		$this->uid = $user['uid'];

		// Set up the update data.
		if(isset($user['username']))
		{
			$this->user_update_data['username'] = $db->escape_string($user['username']);
		}
		if(isset($user['saltedpw']))
		{
			$this->user_update_data['password'] = $user['saltedpw'];
			$this->user_update_data['salt'] = $user['salt'];
			$this->user_update_data['loginkey'] = $user['loginkey'];
		}
		if(isset($user['email']))
		{
			$this->user_update_data['email'] = $user['email'];
		}
		if(isset($user['postnum']))
		{
			$this->user_update_data['postnum'] = intval($user['postnum']);
		}
		if(isset($user['avatar']))
		{
			$this->user_update_data['avatar'] = $db->escape_string($user['avatar']);
			$this->user_update_data['avatartype'] = $db->escape_string($user['avatartype']);
		}
		if(isset($user['usergroup']))
		{
			$this->user_update_data['usergroup'] = intval($user['usergroup']);
		}
		if(isset($user['additionalgroups']))
		{
			$this->user_update_data['additionalgroups'] = $db->escape_string($user['additionalgroups']);
		}
		if(isset($user['displaygroup']))
		{
			$this->user_update_data['displaygroup'] = intval($user['displaygroup']);
		}
		if(isset($user['usertitle']))
		{
			$this->user_update_data['usertitle'] = $db->escape_string(htmlspecialchars_uni($user['usertitle']));
		}
		if(isset($user['regdate']))
		{
			$this->user_update_data['regdate'] = intval($user['regdate']);
		}
		if(isset($user['lastactive']))
		{
			$this->user_update_data['lastactive'] = intval($user['lastactive']);
		}
		if(isset($user['lastvisit']))
		{
			$this->user_update_data['lastvisit'] = intval($user['lastvisit']);
		}
		if(isset($user['signature']))
		{
			$this->user_update_data['signature'] = $db->escape_string($user['signature']);
		}
		if(isset($user['website']))
		{
			$this->user_update_data['website'] = $db->escape_string(htmlspecialchars_uni($user['website']));
		}
		if(isset($user['icq']))
		{
			$this->user_update_data['icq'] = intval($user['icq']);
		}
		if(isset($user['aim']))
		{
			$this->user_update_data['aim'] = $db->escape_string(htmlspecialchars_uni($user['aim']));
		}
		if(isset($user['yahoo']))
		{
			$this->user_update_data['yahoo'] = $db->escape_string(htmlspecialchars_uni($user['yahoo']));
		}
		if(isset($user['msn']))
		{
			$this->user_update_data['msn'] = $db->escape_string(htmlspecialchars_uni($user['msn']));
		}
		if(isset($user['bday']))
		{
			$this->user_update_data['birthday'] = $user['bday'];
		}
		if(isset($user['birthdayprivacy']))
		{
			$this->user_update_data['birthdayprivacy'] = $db->escape_string($user['birthdayprivacy']);
		}
		if(isset($user['style']))
		{
			$this->user_update_data['style'] = intval($user['style']);
		}
		if(isset($user['timezone']))
		{
			$this->user_update_data['timezone'] = $db->escape_string($user['timezone']);
		}
		if(isset($user['dateformat']))
		{
			$this->user_update_data['dateformat'] = $db->escape_string($user['dateformat']);
		}
		if(isset($user['timeformat']))
		{
			$this->user_update_data['timeformat'] = $db->escape_string($user['timeformat']);
		}
		if(isset($user['regip']))
		{
			$this->user_update_data['regip'] = $db->escape_string($user['regip']);
		}
		if(isset($user['language']))
		{
			$this->user_update_data['language'] = $db->escape_string($user['language']);
		}
		if(isset($user['away']))
		{
			$this->user_update_data['away'] = $user['away']['away'];
			$this->user_update_data['awaydate'] = $db->escape_string($user['away']['date']);
			$this->user_update_data['returndate'] = $db->escape_string($user['away']['returndate']);
			$this->user_update_data['awayreason'] = $db->escape_string($user['away']['awayreason']);
		}
		if(isset($user['notepad']))
		{
			$this->user_update_data['notepad'] = $db->escape_string($user['notepad']);
		}
		if(isset($user['usernotes']))
		{
			$this->user_update_data['usernotes'] = $db->escape_string($user['usernotes']);
		}
		if(isset($user['options']) && is_array($user['options']))
		{
			foreach($user['options'] as $option => $value)
			{
				$this->user_update_data[$option] = $value;
			}
		}
		if(array_key_exists('coppa_user', $user))
		{
			$this->user_update_data['coppauser'] = intval($user['coppa_user']);
		}
		// First, grab the old user details for later use.
		$old_user = get_user($user['uid']);

		// If old user has new pmnotice and new user has = yes, keep old value
		if($old_user['pmnotice'] == "2" && $this->user_update_data['pmnotice'] == 1)
		{
			unset($this->user_update_data['pmnotice']);
		}

		$plugins->run_hooks("datahandler_user_update", $this);

		if(count($this->user_update_data) < 1 && empty($user['user_fields']))
		{
			return false;
		}

		if(count($this->user_update_data) > 0)
		{
			// Actual updating happens here.
			$db->update_query("users", $this->user_update_data, "uid='{$user['uid']}'");
		}

		$cache->update_moderators();
		if(isset($user['bday']) || isset($user['username']))
		{
			$cache->update_birthdays();
		}

		// Maybe some userfields need to be updated?
		if(isset($user['user_fields']) && is_array($user['user_fields']))
		{
			$query = $db->simple_select("userfields", "*", "ufid='{$user['uid']}'");
			$fields = $db->fetch_array($query);
			if(!$fields['ufid'])
			{
				$user_fields = array(
					'ufid' => $user['uid']
				);

				$fields_array = $db->show_fields_from("userfields");
				foreach($fields_array as $field)
				{
					if($field['Field'] == 'ufid')
					{
						continue;
					}
					$user_fields[$field['Field']] = '';
				}
				$db->insert_query("userfields", $user_fields);
			}
			$db->update_query("userfields", $user['user_fields'], "ufid='{$user['uid']}'", false);
		}

		// Let's make sure the user's name gets changed everywhere in the db if it changed.
		if(!empty($this->user_update_data['username']) && $this->user_update_data['username'] != $old_user['username'])
		{
			$username_update = array(
				"username" => $this->user_update_data['username']
			);
			$lastposter_update = array(
				"lastposter" => $this->user_update_data['username']
			);

			$db->update_query("posts", $username_update, "uid='{$user['uid']}'");
			$db->update_query("threads", $username_update, "uid='{$user['uid']}'");
			$db->update_query("threads", $lastposter_update, "lastposteruid='{$user['uid']}'");
			$db->update_query("forums", $lastposter_update, "lastposteruid='{$user['uid']}'");

			$stats = $cache->read("stats");
			if($stats['lastuid'] == $user['uid'])
			{
				// User was latest to register, update stats
				update_stats(array("numusers" => "+0"));
			}
		}
	}
}
?>