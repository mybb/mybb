<?php

/**
 * Interface for classes that check a registration against a remote database.
 */
interface RegistrationCheckerInterface
{
	/**
	 * Check a user against the 3rd party service to determine whether they are a spammer.
	 *
	 * @param string $username The username of the user to check.
	 * @param string $email The email address of the user to check.
	 * @param string $ip_address The IP address sof the user to check.
	 *
	 * @return bool Whether the user is considered a spammer or not.
	 */
	public function is_user_a_spammer($username = '', $email = '', $ip_address = '');
} 
