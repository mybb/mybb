<?php

/**
 * Registration checker to check registrations against the StopForumSpam.com database.
 */
class StopForumSpamChecker
{
	/**
	 * The base URL format to the stop forum spam API.
	 * @var string
	 */
	const STOP_FORUM_SPAM_API_URL_FORMAT = 'http://www.stopforumspam.com/api?username=%s&email=%s&ip=%s&f=json&confidence';
	/**
	 * @var pluginSystem
	 */
	private $plugins = null;
	/**
	 * The minimum weighting before a user is considered to be a spammer.
	 * @var double
	 */
	private $min_weighting_before_spam = null;
	/**
	 * Whether to check usernames against StopForumSPam. If set to false, the username weighting won't be used.
	 * @var bool
	 */
	private $check_usernames = false;
	/**
	 * Whether to check email addresses against StopForumSPam. If set to false, the username weighting won't be used.
	 * @var bool
	 */
	private $check_emails = true;
	/**
	 * Whether to check IP addresses against StopForumSPam. If set to false, the username weighting won't be used.
	 * @var bool
	 */
	private $check_ips = true;

	/**
	 * Create a new instance of the StopForumSpam.com checker.
	 *
	 * @param pluginSystem $plugins An instance of the plugin system.
	 * @param double $min_weighting_before_spam The minimum confidence rating before a user is considered definitely spam.
	 * @param bool $check_usernames Whether to check usernames against StopForumSpam.
	 * @param bool $check_emails Whether to check email address against StopForumSpam.
	 * @param bool $check_ips Whetehr to check IP addresses against StopForumSpam.
	 */
	public function __construct(&$plugins = null, $min_weighting_before_spam = 50.00, $check_usernames = false, $check_emails = true, $check_ips = true)
	{
		$this->plugins = $plugins;
		$this->min_weighting_before_spam = (double)$min_weighting_before_spam;
		$this->check_usernames = (bool)$check_usernames;
		$this->check_emails = (bool)$check_emails;
		$this->check_ips = (bool)$check_ips;
	}

	/**
	 * Check a user against the 3rd party service to determine whether they are a spammer.
	 *
	 * @param string $username   The username of the user to check.
	 * @param string $email      The email address of the user to check.
	 * @param string $ip_address The IP address sof the user to check.
	 * @return bool Whether the user is considered a spammer or not.
	 *
	 * @throws Exception Thrown when there's an error fetching from the StopForumSpam API or when the data cannot be decoded.
	 */
	public function is_user_a_spammer($username = '', $email = '', $ip_address = '')
	{
		$is_spammer = false;

		if(filter_var($email, FILTER_VALIDATE_EMAIL) && filter_var($ip_address, FILTER_VALIDATE_IP)) // Calls to the API with invalid email/ip formats cause issues
		{
			$username   = urlencode($username);
			$email      = urlencode($email);

			$check_url = sprintf(self::STOP_FORUM_SPAM_API_URL_FORMAT, $username, $email, $ip_address);

			$result = fetch_remote_file($check_url);

			if($result !== false)
			{
				$result_json = @json_decode($check_url);

				if(json_last_error() == JSON_ERROR_NONE && $result_json != null)
				{
					$confidence = 0;

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
					throw new Exception('Error decoding data from StopForumSpam.');
				}
			}
			else
			{
				throw new Exception('Error retrieving data from StopForumSpam.');
			}
		}

		if($this->plugins)
		{
			$this->plugins->run_hooks('stopforumspam_check_spammer_pre_return', array(
					'username' => $username,
					'email' => $email,
					'ip_address' => $ip_address,
					'is_spammer' => $is_spammer,
				)
			);
		}

		return $is_spammer;
	}
}
