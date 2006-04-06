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
	 * Verifies if a username is valid or invalid.
	 *
	 * @param boolean True when valid, false when not.
	 */
	function verify_username()
	{
		$username = &$this->data['username'];
		
		// Username = ''?
		
		// Check banned usernames
		
		// Check for certain characters in username (<, >, &, and slashes)
		
		// Check username length
		
		// Check if username exists or not
	}

	/**
	* Verifies if a new password is valid or not.
	*
	* @return boolean True when valid, false when not.
	*/
	function verify_new_password()
	{
		global $mybb;
		
		$password = &$this->data['password'];

		// Always check for the length of the password.
		if(my_strlen($password) < 6)
		{
			$this->set_error("invalid_password_length");
			return false;
		}

		// See if the board has "require complex passwords" enabled.
		if($mybb->settings['requirecomplexpasswords'] == "yes")
		{
			// Complex passwords required, do some extra checks.
			// First, see if there is one or more complex character(s) in the password.
			if(!preg_match('#[\W]+#', $password))
			{
				$this->set_error("no_complex_characters");
				return false;
			}
		}
		return true;
	}
	
	function verify_email()
	{
		// Email = ''?
		
		// Check email valid or not - regex
		
		// Check banned emails
	}
	
	function verify_website()
	{
		// Website starts with http?
	}
	
	function verify_birthday()
	{
		// Check user isn't over 100 years - if they are strip back to just date/month
	}
	
	function verify_profile_fields()
	{
		// Loop through profile fields checking if they exist or not and are filled in.
	}
	
	function verify_referrer()
	{
		// Referrer exists or not?
	}
	
	function verify_reg_image()
	{
		// Verify reg image.
	}

?>