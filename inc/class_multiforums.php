<?php

class MultiForums {

	/**
	 * true if multiforums is enabled, false if disabled
	 *
	 * @var boolean
	 */
	private $enabled;

	/**
	 * true if error reporting enabled, false if disabled.
	 *
	 * @var boolean
	 */
	public $error_reporting = true;

	function __construct($config) {

		//attempt to detect if multiforums should be enabled
		if(array_key_exists('enabled', $config))
		{
			$this->enabled = $config['enabled'];
		} else {
			//multifourums key doesn't exist, disable multiforums functionality
			$this->enabled = false;
		}

	}

	/**
	 * true if multiforums is enabled, false otherwise
	 * @return bool
	 */
	function isEnabled() {
		return $this->enabled;
	}

	/**
	 * Output a database error.
	 *
	 * @param string $string The string to present as an error.
	 * @param mixed the type of error being thrown
	 * @return bool Whether error reporting is enabled or not
	 */
	function error($string="", $type = MYBB_MULTIFORUMS_GENERAL)
	{
		if($this->error_reporting)
		{
			if(class_exists("errorHandler"))
			{
				global $error_handler;

				if(!is_object($error_handler))
				{
					require_once MYBB_ROOT."inc/class_error.php";
					$error_handler = new errorHandler();
				}

				$error_handler->error($type, $string);
			}
			else
			{
				trigger_error("<strong>[MultiForums] {$string}</strong>", E_USER_ERROR);
			}

			return true;
		}
		else
		{
			return false;
		}
	}

}