<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

/**
 * Base data handler class.
 *
 */
class DataHandler
{
	/**
	 * The data being managed by the data handler
	 *
	 * @var array Data being handled by the data handler.
	 */
	var $data = array();

	/**
	 * Whether or not the data has been validated. Note: "validated" != "valid".
	 *
	 * @var boolean True when validated, false when not validated.
	 */
	var $is_validated = false;

	/**
	 * The errors that occurred when handling data.
	 *
	 * @var array
	 */
	var $errors = array();

	/**
	 * The status of administrator override powers.
	 *
	 * @var boolean
	 */
	var $admin_override = false;

	/**
	 * Sets the data to be used for the data handler
	 *
	 * @param array The data.
	 */
	function set_data($data)
	{
		if(!is_array($data))
		{
			return false;
		}
		$this->data = $data;
		return true;
	}

	/**
	 * Add an error to the error array.
	 *
	 * @param string The error name.
	 */
	function set_error($error, $data='')
	{
		$this->errors[] = array(
			"error_code" => $error,
			"data" => $data
		);	
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
		if($this->is_validated == true)
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