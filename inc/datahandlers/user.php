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
	* Verifies the complexity of a password.
	*
	* @param string The password to verify.
	* @return boolean True when complex enough, false when not.
	*/
	function verify_password_complexity($password)
	{
		global $mybb;

		// Always check for the length of the password.
		if(my_strlen($password) < 6 || my_strlen($password) > 25)
		{
			$this->set_error("invalid_password_length");
			return false;
		}

		// See if the board has "require complex passwords" enabled.
		if($mybb->settings['require_complex_passwords'] === false)
		{
			// No complex passwords required, flag this password valid.
			return true;
		}
		else
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

?>