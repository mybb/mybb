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
	public $data = array();

	/**
	 * Whether or not the data has been validated. Note: "validated" != "valid".
	 *
	 * @var boolean True when validated, false when not validated.
	 */
	public $is_validated = false;

	/**
	 * The errors that occurred when handling data.
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * The status of administrator override powers.
	 *
	 * @var boolean
	 */
	public $admin_override = false;

	/**
	 * Defines if we're performing an update or an insert.
	 *
	 * @var string
	 */
	public $method;

	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	public $language_prefix = '';


	/**
	 * Constructor for the data handler.
	 *
	 * @param string $method The method we're performing with this object.
	 */
	function __construct($method="insert")
	{
		if($method != "update" && $method != "insert" && $method != "get" && $method != "delete")
		{
			die("A valid method was not supplied to the data handler.");
		}
		$this->method = $method;
	}

	/**
	 * Sets the data to be used for the data handler
	 *
	 * @param array $data The data.
	 * @return bool
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
	 * @param string $error The error name.
	 * @param string $data
	 */
	function set_error($error, $data='')
	{
		$this->errors[$error] = array(
			"error_code" => $error,
			"data" => $data
		);
	}

	/**
	 * Returns the error(s) that occurred when handling data.
	 *
	 * @return array An array of errors.
	 */
	function get_errors()
	{
		return $this->errors;
	}

	/**
	 * Returns the error(s) that occurred when handling data
	 * in a format that MyBB can handle.
	 *
	 * @return array An array of errors in a MyBB format.
	 */
	function get_friendly_errors()
	{
		global $lang;

		// Load the language pack we need
		if($this->language_file)
		{
			$lang->load($this->language_file, true);
		}
		// Prefix all the error codes with the language prefix.
		$errors = array();
		foreach($this->errors as $error)
		{
			$lang_string = $this->language_prefix.'_'.$error['error_code'];
			if(!$lang->$lang_string)
			{
				$errors[] = $error['error_code'];
				continue;
			}

			if(!empty($error['data']) && !is_array($error['data']))
			{
				$error['data'] = array($error['data']);
			}

			if(is_array($error['data']))
			{
				array_unshift($error['data'], $lang->$lang_string);
				$errors[] = call_user_func_array(array($lang, "sprintf"), $error['data']);
			}
			else
			{
				$errors[] = $lang->$lang_string;
			}
		}
		return $errors;
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

	/**
	* Verifies if yes/no options haven't been modified.
	*
	* @param array $options The user options array.
	* @param string $option The specific option to check.
	* @param int|bool $default Optionally specify if the default should be used.
	*/
	function verify_yesno_option(&$options, $option, $default=1)
	{
		if($this->method == "insert" || array_key_exists($option, $options))
		{
			if(isset($options[$option]) && $options[$option] != $default && $options[$option] != "")
			{
				if($default == 1)
				{
					$options[$option] = 0;
				}
				else
				{
					$options[$option] = 1;
				}
			}
			else if(@array_key_exists($option, $options) && $options[$option] == '')
			{
				$options[$option] = 0;
			}
			else
			{
				$options[$option] = $default;
			}
		}
	}
}
