<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id:$
 */

/**
 * User handling class, provides common structure to handle user data.
 *
 */
class UserDataHandler extends DataHandler
{
	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	var $language_prefix = 'userdata_';

	/**
	 * Verifies if a username is valid or invalid.
	 *
	 * @param boolean True when valid, false when invalid.
	 */
	function verify_username()
	{
		$username = &$this->data['username'];
		require_once './inc/functions_user.php';

		// Fix bad characters
		$username = str_replace(array(chr(160), chr(173)), array(" ", "-"), $username);

		// Remove multiple spaces from the username
		$username = preg_replace("#\s{2,}#", " ", $username);
		
		// Check if the username is not empty.
		if(trim($username) == '')
		{
			$this->set_error('empty_username');
			return false;
		}

		// Check if the username belongs to the list of banned usernames.
		$bannedusernames = get_banned_usernames();
		if(in_array($username, $bannedusernames))
		{
			$this->set_error('banned_username');
			return false;
		}

		// Check for certain characters in username (<, >, &, and slashes)
		if(eregi("<", $username) || eregi(">", $username) || eregi("&", $username) || eregi("\\", $username) || eregi(";", $username))
		{
			$this->set_error("bad_characters_username");
			return false;
		}

		// Check if the username is of the correct length.
		if(($mybb->settings['maxnamelength'] != 0 && my_strlen($username) > $mybb->settings['maxnamelength']) || ($mybb->settings['minnamelength'] != 0 && my_strlen($username) < $mybb->settings['minnamelength']) && !$bannedusername && !$missingname)
		{
			$this->set_error('invalid_username_length');
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
		$username = &$this->data['username'];
		
		$query = $db->query("SELECT COUNT(uid) AS count FROM ".TABLE_PREFIX."users WHERE username='".$db->escape_string($username)."'");
		$user_count = $db->fetch_field($query, "count");
		if($user_count > 0)
		{
			$this->set_error("username_exists")
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
	function verify_new_password()
	{
		global $mybb;

		$user = *&$this->data;

		// Always check for the length of the password.
		if(my_strlen($user['password']) < 6)
		{
			$this->set_error('invalid_password_length');
			return false;
		}

		// See if the board has "require complex passwords" enabled.
		if($mybb->settings['requirecomplexpasswords'] == "yes")
		{
			// Complex passwords required, do some extra checks.
			// First, see if there is one or more complex character(s) in the password.
			if(!preg_match('#[\W]+#', $user['password']))
			{
				$this->set_error('no_complex_characters');
				return false;
			}
		}
		
		// If we have a "password2" check if they both match
		if(isset($user['password2']) && $user['password'] != $user['password2'])
		{
			$this->set_error("passwords_dont_match");
			return false;
		}
		
		return true;
	}

	/**
	* Verifies if an email address is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_email()
	{
		$user = &$this->data;

		// Check if an email address has actually been entered.
		if(trim($user['email']) == '')
		{
			$this->set_error('empty_email');
			return false;
		}

		// Check if this is a proper email address.
		if(validate_email_format($user['email']) === false)
		{
			$this->set_error('invalid_email_format');
			return false;
		}

		// Check banned emails
		$bannedemails = explode(" ", $mybb->settings['bannedemails']);
		if(is_array($bannedemails))
		{
			foreach($bannedemails as $bannedemail)
			{
				$bannedemail = strtolower(trim($bannedemail));
				if($bannedemail != '')
				{
					if(strstr($user['email'], $bannedemail) != '')
					{
						$this->set_error('banned_email');
						return false;
					}
				}
			}
		}
		
		// If we have an "email2", verify it matches the existing email
		if(isset($user['email2']) && $user['email'] != $user['email2'])
		{
			$this->set_error("emails_dont_match");
			return false;
		}
	}

	/**
	* Verifies if a website is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_website()
	{
		$website = &$this->data['website'];

		// Does the website start with http://?
		if(substr_count($website, 'http://') == 0)
		{
			// Website does not start with http://, let's see if the user forgot.
			$website = 'http://'.$website;
			if(substr_count($website, 'http://') == 0)
			{
				$this->set_error('invalid_website');
				return false;
			}
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
		// Check user isn't over 100 years - if they are strip back to just date/month.
		$birthday = &$this->data['birthday'];
	}

	/**
	* Verifies if a profile fields are filled in correctly.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_profile_fields()
	{
		// Loop through profile fields checking if they exist or not and are filled in.
		$userfields = array();
		$comma = '';

		// Fetch all profile fields first.
		$options = array(
			'order_by' => 'disporder'
		);
		$query = $db->simple_select(TABLE_PREFIX.'profilefields', 'type, fid, required', "editable='yes'", $options);

		// Then loop through the profile fields.
		while($profilefield = $db->fetch_array($query))
		{
			$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
			$thing = explode("\n", $profilefield['type'], "2");
			$type = trim($thing[0]);
			$field = "fid$profilefield[fid]";

			// If the profile field is required, but not filled in, present error.
			if(!$mybb->input[$field] && $profilefield['required'] == "yes" && !$proferror)
			{
				$this->set_error('error_missingrequiredfield');
				return false;
			}

			// Sort out multiselect/checkbox profile fields.
			$options = '';
			if($type == "multiselect" || $type == "checkbox")
			{
				if(is_array($mybb->input[$field]))
				{
					while(list($key, $val) = each($mybb->input[$field]))
					{
						if($options)
						{
							$options .= "\n";
						}
						$options .= "$val";
					}
				}
			}
			else
			{
				$options = $mybb->input[$field];
			}
			$userfields[$field] = $options;
			$comma = ",";
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
		global $db;

		// Does the referrer exist or not?
		if($mybb->settings['usereferrals'] == "yes" && $mybb->user['uid'] == 0)
		{
			if($mybb->input['referrername'])
			{
				$referrername = $db->escape_string($mybb->input['referrername']);
				$options = array(
					'limit' => 1
				);
				$query = $db->simple_select(TABLE_PREFIX.'users', 'uid', "username={$referrername}", $options);
				$referrer = $db->fetch_array($query);
				if(!$referrer['uid'])
				{
					$this->set_error('error_badreferrer');
					return false;
				}
			}
		}

		return true;
	}

	/**
	* Verifies if yes/no options haven't been modified.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_options()
	{
		$options = &$this->data['options'];

		// Verify yes/no options.
		if($options['allownotices'] != "yes")
		{
			$options['allownotices'] = "no";
		}
		if($options['hideemail'] != "yes")
		{
			$options['hideemail'] = "no";
		}
		if($options['emailnotify'] != "yes")
		{
			$options['emailnotify'] = "no";
		}
		if($options['receivepms'] != "yes")
		{
			$options['receivepms'] = "no";
		}
		if($options['pmpopup'] != "yes")
		{
			$options['pmpopup'] = "no";
		}
		if($options['emailpmnotify'] != "yes")
		{
			$options['emailpmnotify'] = "no";
		}
		if($options['invisible'] != "yes")
		{
			$options['invisible'] = "no";
		}
		if($options['enabledst'] != "yes")
		{
			$options['enabledst'] = "no";
		}
	}

	/**
	* Validate all user assets.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function validate_user()
	{
		$this->verify_options();
		$this->verify_birthday();
		$this->verify_email();
		$this->verify_profile_fields();
		$this->verify_referrer();
		$this->verify_reg_image();
		$this->verify_username();
		$this->verify_website();

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
		global $db;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The post is not valid.");
		}

		$user = &$this->data;
	}

	/**
	* Updates a user in the database.
	*/
	function update_user()
	{
		global $db;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The post is not valid.");
		}

		$user = &$this->data;
	}

?>