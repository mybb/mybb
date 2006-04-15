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

		// Check if the username is of the correct length.
		if(($mybb->settings['maxnamelength'] != 0 && my_strlen($username) > $mybb->settings['maxnamelength']) || ($mybb->settings['minnamelength'] != 0 && my_strlen($username) < $mybb->settings['minnamelength']) && !$bannedusername && !$missingname)
		{
			$this->set_error('invalid_username_length');
			return false;
		}

		// Check if the username already exists or not.
		if(username_exists($username))
		{
			$this->set_error('username_exists');
			return false;
		}

		return true;
	}

	/**
	* Verifies if a new password is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_new_password()
	{
		global $mybb;

		$password = &$this->data['password'];

		// Always check for the length of the password.
		if(my_strlen($password) < 6)
		{
			$this->set_error('invalid_password_length');
			return false;
		}

		// See if the board has "require complex passwords" enabled.
		if($mybb->settings['requirecomplexpasswords'] == "yes")
		{
			// Complex passwords required, do some extra checks.
			// First, see if there is one or more complex character(s) in the password.
			if(!preg_match('#[\W]+#', $password))
			{
				$this->set_error('no_complex_characters');
				return false;
			}
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
		$email = &$this->data['email'];

		// Check if an email address has actually been entered.
		if(trim($email) == '')
		{
			$this->set_error('empty_email');
			return false;
		}

		// Check if this is a proper email address.
		if(validate_email_format($email) === false)
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
					if(strstr($email, $bannedemail) != '')
					{
						$this->set_error('banned_email');
						return false;
					}
				}
			}
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
	* Verifies if a user entered the correct code from the registration image.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_reg_image()
	{
		// Verify reg image.
		if($mybb->settings['regimage'] == "on" && function_exists("imagecreatefrompng"))
		{
			$imagehash = $db->escape_string($mybb->input['imagehash']);
			$imagestring = $db->escape_string($mybb->input['imagestring']);
			$options = array(
				'limit' => 1
			);
			$query = $db->simple_select(TABLE_PREFIX.'regimages', 'dateline', "imagehash={$imagehash} AND imagestring={$imagestring}", $options);
			$imgcheck = $db->fetch_array($query);
			if(!$imgcheck['dateline'])
			{
				$this->set_error('regimage_invalid');
				return false;
			}
			$db->delete_query(TABLE_PREFIX.'regimages', "imagehash={$imagehash}", 1);
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

	}

	/**
	* Inserts a user into the database.
	*/
	function insert_user()
	{

	}

	/**
	* Updates a user in the database.
	*/
	function update_user()
	{

	}

?>