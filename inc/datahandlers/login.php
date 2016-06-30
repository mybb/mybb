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
class LoginDataHandler extends DataHandler
{
	/**
	 * The language file used in the data handler.
	 *
	 * @var string
	 */
	public $language_file = 'datahandler_login';

	/**
	 * The prefix for the language variables used in the data handler.
	 *
	 * @var string
	 */
	public $language_prefix = 'logindata';

	/**
	 * Array of data used via login events.
	 *
	 * @var array
	 */
	public $login_data = array();

	/**
	 * @var bool
	 */
	public $captcha_verified = true;

	/**
	 * @var bool|captcha
	 */
	private $captcha = false;

	/**
	 * @var int
	 */
	public $username_method = null;

	/**
	 * @param int $check_captcha
	 */
	function verify_attempts($check_captcha = 0)
	{
		global $db, $mybb;

		$user = &$this->data;

		if($check_captcha)
		{
			if(!isset($mybb->cookies['loginattempts']))
			{
				$mybb->cookies['loginattempts'] = 0;
			}
			if($mybb->settings['failedcaptchalogincount'] > 0 && ($user['loginattempts'] > $mybb->settings['failedcaptchalogincount'] || (int)$mybb->cookies['loginattempts'] > $mybb->settings['failedcaptchalogincount']))
			{
				$this->captcha_verified = false;
				$this->verify_captcha();
			}
		}
	}

	/**
	 * @return bool
	 */
	function verify_captcha()
	{
		global $db, $mybb;

		$user = &$this->data;

		if($user['imagestring'] || $mybb->settings['captchaimage'] != 1)
		{
			// Check their current captcha input - if correct, hide the captcha input area
			require_once MYBB_ROOT.'inc/class_captcha.php';
			$this->captcha = new captcha;

			if($this->captcha->validate_captcha() == false)
			{
				// CAPTCHA validation failed
				foreach($this->captcha->get_errors() as $error)
				{
					$this->set_error($error);
				}
				return false;
			}
			else
			{
				$this->captcha_verified = true;
				return true;
			}
		}
		else if($mybb->input['quick_login'] == 1 && $mybb->input['quick_password'] && $mybb->input['quick_username'])
		{
			$this->set_error('regimagerequired');
			return false;
		}
		else
		{
			$this->set_error('regimageinvalid');
			return false;
		}
	}

	/**
	 * @return bool
	 */
	function verify_username()
	{
		$this->get_login_data();

		if(!$this->login_data['uid'])
		{
			$this->invalid_combination();
			return false;
		}

		return true;
	}

	/**
	 * @param bool $strict
	 *
	 * @return bool
	 */
	function verify_password($strict = true)
	{
		global $db, $mybb, $plugins;

		$this->get_login_data();

		if(empty($this->login_data['username']))
		{
			// Username must be validated to apply a password to
			$this->invalid_combination();
			return false;
		}

		$args = array(
			'this' => &$this,
			'strict' => &$strict,
		);

		$plugins->run_hooks('datahandler_login_verify_password_start', $args);

		$user = &$this->data;

		$password = md5($user['password']);

		if(!$this->login_data['uid'] || $this->login_data['uid'] && !$this->login_data['salt'] && $strict == false)
		{
			$this->invalid_combination();
		}

		if($strict == true)
		{
			if(!$this->login_data['salt'])
			{
				// Generate a salt for this user and assume the password stored in db is a plain md5 password
				$this->login_data['salt'] = generate_salt();
				$this->login_data['password'] = salt_password($this->login_data['password'], $this->login_data['salt']);

				$sql_array = array(
					"salt" => $this->login_data['salt'],
					"password" => $this->login_data['password']
				);

				$db->update_query("users", $sql_array, "uid = '{$this->login_data['uid']}'");
			}

			if(!$this->login_data['loginkey'])
			{
				$this->login_data['loginkey'] = generate_loginkey();

				$sql_array = array(
					"loginkey" => $this->login_data['loginkey']
				);

				$db->update_query("users", $sql_array, "uid = '{$this->login_data['uid']}'");
			}
		}

		$salted_password = md5(md5($this->login_data['salt']).$password);

		$plugins->run_hooks('datahandler_login_verify_password_end', $args);

		if($salted_password !== $this->login_data['password'])
		{
			$this->invalid_combination(true);
			return false;
		}

		return true;
	}

	/**
	 * @param bool $show_login_attempts
	 */
	function invalid_combination($show_login_attempts = false)
	{
		global $db, $lang, $mybb;

		// Don't show an error when the captcha was wrong!
		if(!$this->captcha_verified)
		{
			return;
		}

		$login_text = '';
		if($show_login_attempts)
		{
			if($mybb->settings['failedlogincount'] != 0 && $mybb->settings['failedlogintext'] == 1)
			{
				$logins = login_attempt_check(false) + 1;
				$login_text = $lang->sprintf($lang->failed_login_again, $mybb->settings['failedlogincount'] - $logins);
			}
		}

		switch($mybb->settings['username_method'])
		{
			case 1:
				$this->set_error('invalidpwordusernameemail', $login_text);
				break;
			case 2:
				$this->set_error('invalidpwordusernamecombo', $login_text);
				break;
			default:
				$this->set_error('invalidpwordusername', $login_text);
				break;
		}
	}

	function get_login_data()
	{
		global $db, $settings;

		$user = &$this->data;

		$options = array(
			'fields' => array('uid', 'username', 'password', 'salt', 'loginkey', 'coppauser', 'usergroup', 'loginattempts'),
			'username_method' => (int)$settings['username_method']
		);

		if($this->username_method !== null)
		{
			$options['username_method'] = (int)$this->username_method;
		}

		$this->login_data = get_user_by_username($user['username'], $options);
	}

	/**
	 * @return bool
	 */
	function validate_login()
	{
		global $plugins, $mybb;

		$user = &$this->data;

		$plugins->run_hooks('datahandler_login_validate_start', $this);

		if(!defined('IN_ADMINCP'))
		{
			$this->verify_attempts($mybb->settings['captchaimage']);
		}

		if(array_key_exists('username', $user))
		{
			$this->verify_username();
		}

		if(array_key_exists('password', $user))
		{
			$this->verify_password();
		}

		$plugins->run_hooks('datahandler_login_validate_end', $this);

		$this->set_validated(true);
		if(count($this->get_errors()) > 0)
		{
			return false;
		}

		return true;
	}

	/**
	 * @return bool true
	 */
	function complete_login()
	{
		global $plugins, $db, $mybb, $session;

		$user = &$this->login_data;

		$plugins->run_hooks('datahandler_login_complete_start', $this);

		// Login to MyBB
		my_setcookie('loginattempts', 1);
		my_setcookie("sid", $session->sid, -1, true);

		$ip_address = $db->escape_binary($session->packedip);
		$db->delete_query("sessions", "ip = {$ip_address} AND sid != '{$session->sid}'");

		$newsession = array(
			"uid" => $user['uid'],
		);

		$db->update_query("sessions", $newsession, "sid = '{$session->sid}'");
		$db->update_query("users", array("loginattempts" => 1), "uid = '{$user['uid']}'");

		$remember = null;
		if(!isset($mybb->input['remember']) || $mybb->input['remember'] != "yes")
		{
			$remember = -1;
		}

		my_setcookie("mybbuser", $user['uid']."_".$user['loginkey'], $remember, true);
		
		if($this->captcha !== false)
		{
			$this->captcha->invalidate_captcha();
		}

		$plugins->run_hooks('datahandler_login_complete_end', $this);

		return true;
	}
}
