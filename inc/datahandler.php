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
 * Base data handler class.
 *
 */
class DataHandler
{
	/**
	 * Whether or not the data has been validated. Note: "validated" != "valid".
	 *
	 * @var boolean True when validated, false when not validated.
	 */
	var $is_validated;
	
	/**
	 * The errors that occurred when handling data.
	 *
	 * @var array
	 */
	var $errors;
	
	/**
	 * Add an error to the error array.
	 *
	 * @param string The error name.
	 */
	function set_error($error)
	{
		$this->errors[] = $error;
	}
	
	/**
	 * Returns the error that occurred when handling data.
	 *
	 * @return string|array An error string or an array of errors.
	 */
	function get_errors()
	{
		return $this->errors;
	}
	
	/**
	 * Sets whether or not we are done validating.
	 *
	 * @param boolean True when done, false when not done.
	 */
	function set_validated($validated = true)
	{
		$this->is_validated = $validated;
	}
	
	/**
	 * Returns whether or not we are done validating.
	 *
	 * @return boolean True when done, false when not done.
	 */
	function get_validated()
	{
		if($this->is_validated === true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}

?>