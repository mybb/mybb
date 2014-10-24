<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Registration checker to check registrations against the StopForumSpam.com database.
 */
class StopForumSpamChecker
{
	/**
	 * The base URL format to the stop forum spam API.
	 *
	 * @var string
	 */
	const STOP_FORUM_SPAM_API_URL_FORMAT = 'http://www.stopforumspam.com/api?username=%s&email=%s&ip=%s&f=json&confidence';
	/**
	 * @var pluginSystem
	 */
	private $plugins = null;
	/**
	 * The minimum weighting before a user is considered to be a spammer.
	 *
	 * @var double
	 */
	private $min_weighting_before_spam = null;
	/**
	 * Whether to check usernames against StopForumSPam. If set to false, the username weighting won't be used.
	 *
	 * @var bool
	 */
	private $check_usernames = false;
	/**
	 * Whether to check email addresses against StopForumSPam. If set to false, the username weighting won't be used.
	 *
	 * @var bool
	 */
	private $check_emails = true;
	/**
	 * Whether to check IP addresses against StopForumSPam. If set to false, the username weighting won't be used.
	 *
	 * @var bool
	 */
	private $check_ips = true;
	/**
	 * Whether to log whenever a user is found to be a spammer.
	 *
	 * @var bool
	 */
	private $log_blocks;

	/**
	 * Create a new instance of the StopForumSpam.com checker.
	 *
	 * @param pluginSystem $plugins                   An instance of the plugin system.
	 * @param double       $min_weighting_before_spam The minimum confidence rating before a user is considered definitely spam.
	 * @param bool         $check_usernames           Whether to check usernames against StopForumSpam.
	 * @param bool         $check_emails              Whether to check email address against StopForumSpam.
	 * @param bool         $check_ips                 Whether to check IP addresses against StopForumSpam.
	 */
	public function __construct(&$plugins, $min_weighting_before_spam = 50.00, $check_usernames = false, $check_emails = true, $check_ips = true, $log_blocks = true)
	{
		$this->plugins                   = $plugins;
		$this->min_weighting_before_spam = (double)$min_weighting_before_spam;
		$this->check_usernames           = (bool)$check_usernames;
		$this->check_emails              = (bool)$check_emails;
		$this->check_ips                 = (bool)$check_ips;
		$this->log_blocks                = (bool)$log_blocks;
	}

	/**
	 * Check a user against the 3rd party service to determine whether they are a spammer.
	 *
	 * @param string $username   The username of the user to check.
	 * @param string $email      The email address of the user to check.
	 * @param string $ip_address The IP address sof the user to check.
	 * @return bool Whether the user is considered a spammer or not.
	 * @throws Exception Thrown when there's an error fetching from the StopForumSpam API or when the data cannot be decoded.
	 */
	public function is_user_a_spammer($username = '', $email = '', $ip_address = '')
	{
		$is_spammer = false;
		$confidence = 0;

		if(filter_var($email, FILTER_VALIDATE_EMAIL) && filter_var($ip_address, FILTER_VALIDATE_IP)) // Calls to the API with invalid email/ip formats cause issues
		{
			$username_encoded = urlencode($username);
			$email_encoded    = urlencode($email);

			$check_url = sprintf(self::STOP_FORUM_SPAM_API_URL_FORMAT, $username_encoded, $email_encoded, $ip_address);

			$result = fetch_remote_file($check_url);

			if($result !== false)
			{
				$result_json = @json_decode($result);

				if($result_json != null && !isset($result_json->error))
				{
					if($this->check_usernames && $result_json->username->appears)
					{
						$confidence += $result_json->username->confidence;
					}

					if($this->check_emails && $result_json->email->appears)
					{
						$confidence += $result_json->email->confidence;
					}

					if($this->check_ips && $result_json->ip->appears)
					{
						$confidence += $result_json->ip->confidence;
					}

					if($confidence > $this->min_weighting_before_spam)
					{
						$is_spammer = true;
					}
				}
				else
				{
					throw new Exception('stopforumspam_error_decoding');
				}
			}
			else
			{
				throw new Exception('stopforumspam_error_retrieving');
			}
		}

		if($this->plugins)
		{
			$params = array(
				'username'   => &$username,
				'email'      => &$email,
				'ip_address' => &$ip_address,
				'is_spammer' => &$is_spammer,
				'confidence' => &$confidence,
			);

			$this->plugins->run_hooks('stopforumspam_check_spammer_pre_return', $params);
		}

		if($this->log_blocks && $is_spammer)
		{
			log_spam_block(
				$username, $email, $ip_address, array(
					'confidence' => (double)$confidence,
				)
			);
		}

		return $is_spammer;
	}

	public function getErrorText($sfsSettingsEnabled)
	{
		global $mybb, $lang;

		foreach($sfsSettingsEnabled as $setting)
		{
			if($setting == 'stopforumspam_check_usernames' && $mybb->settings[$setting])
			{
				$settingsenabled[] = $lang->sfs_error_username;
				continue;
			}

			if($setting == 'stopforumspam_check_emails' && $mybb->settings[$setting])
			{
				$settingsenabled[] = $lang->sfs_error_email;
				continue;
			}

			if($setting = 'stopforumspam_check_ips' && $mybb->settings[$setting])
			{
				$settingsenabled[] = $lang->sfs_error_ip;
				continue;
			}
		}

		if(sizeof($settingsenabled) > 1)
		{
			$lastsetting = $settingsenabled[sizeof($settingsenabled)-1];
			unset($settingsenabled[sizeof($settingsenabled)-1]);

			$stopforumspamerror = implode($lang->comma, $settingsenabled) . " {$lang->sfs_error_or} " . $lastsetting;
		}
		else
		{
			$stopforumspamerror = $settingsenabled[0];
		}
		return $stopforumspamerror;
	}
}
