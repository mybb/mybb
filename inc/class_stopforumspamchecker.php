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
	 * The minimum weighting before a user is considered to be a spammer.
	 * @var double
	 */
	private $min_weighting_before_spam = null;

	/**
	 * Create a new instance of the StopForumSpam.com checker.
	 *
	 * @param double $min_weighting_before_spam The minimum confidence rating before a user is considered definitely spam.
	 */
	public function __construct($min_weighting_before_spam = 50.00)
	{
		$this->min_weighting_before_spam = (double)$min_weighting_before_spam;
	}

	/**
	 * Check a user against the 3rd party service to determine whether they are a spammer.
	 *
	 * @param string $username   The username of the user to check.
	 * @param string $email      The email address of the user to check.
	 * @param string $ip_address The IP address sof the user to check.
	 * @return bool Whether the user is considered a spammer or not.
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

					if($result_json->username->appears)
					{
						$confidence += $result_json->username->confidence;
					}

					if($result_json->email->appears)
					{
						$confidence += $result_json->email->confidence;
					}

					if($result_json->ip->appears)
					{
						$confidence += $result_json->ip->confidence;
					}

					if($confidence > $this->min_weighting_before_spam)
					{
						$is_spammer = true;
					}
				}
			}
		}

		return $is_spammer;
	}
}
