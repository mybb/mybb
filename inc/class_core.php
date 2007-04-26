<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/eula.html
 *
 * $Id$
 */

class MyBB {
	/**
	 * The friendly version number of MyBB we're running.
	 *
	 * @var string
	 */
	var $version = "1.2.7";
	
	/**
	 * The version code of MyBB we're running.
	 *
	 * @var integer
	 */
	var $version_code = 127;
	
	/**
	 * The current working directory.
	 *
	 * @var string
	 */
	var $cwd = ".";
	
	/**
	 * Input variables received from the outer world.
	 *
	 * @var array
	 */
	var $input = array();
	
	/**
	 * Information about the current user.
	 *
	 * @var array
	 */
	var $user = array();
	
	/**
	 * Information about the current usergroup.
	 *
	 * @var array
	 */
	var $usergroup = array();
	
	/**
	 * MyBB settings.
	 *
	 * @var array
	 */
	var $settings = array();
	
	/**
	 * Whether or not magic quotes are enabled.
	 *
	 * @var unknown_type
	 */
	var $magicquotes = 0;
	
	/**
	 * MyBB configuration.
	 *
	 * @var array
	 */
	var $config = array();
	
	/**
	 * The debug information.
	 *
	 * @var unknown_type
	 */
	var $debug;
	
	/**
	 * The request method that called this page.
	 *
	 * @var string.
	 */
	var $request_method = "";

	/**
	 * Variables that need to be cleaned.
	 *
	 * @var array
	 */
	var $clean_variables = array(
		"int" => array("tid", "pid", "uid", "eid", "pmid", "fid", "aid")
	);

	/** 
	 * Variables that are to be ignored from cleansing proccess 
	 * 
	 * @var array 
	 */ 
	var $ignore_clean_variables = array();
	
	/**
	 * Using built in shutdown functionality provided by register_shutdown_function for < PHP 5?
	 */
	var $use_shutdown = false;
	
	/**
	 * Constructor of class.
	 *
	 * @return MyBB
	 */
	function MyBB()
	{
		// Set up MyBB
		$protected = array("_GET", "_POST", "_SERVER", "_COOKIE", "_FILES", "_SERVER", "_ENV", "GLOBALS");
		foreach($protected as $var)
		{
			if($_REQUEST[$var] || $_FILES[$var] || $_COOKIE[$var])
			{
				die("Hacking attempt");
			}
		}
		
		if(defined("IGNORE_CLEAN_VARS"))
		{
			if(!is_array(IGNORE_CLEAN_VARS))
			{
				$this->ignore_clean_variables = array(IGNORE_CLEAN_VARS);
			}
			else
			{
				$this->ignore_clean_variables = IGNORE_CLEAN_VARS;
			}
		}

		// Determine Magic Quotes Status
		if(get_magic_quotes_gpc())
		{
			$this->magicquotes = 1;
			$this->strip_slashes_array($_POST);
			$this->strip_slashes_array($_GET);
			$this->strip_slashes_array($_COOKIE);
		}
		set_magic_quotes_runtime(0);
		@ini_set("magic_quotes_gpc", 0);
		@ini_set("magic_quotes_runtime", 0); 
		
		// Determine input
		$this->parse_incoming($_GET);
		$this->parse_incoming($_POST);

		if($_SERVER['REQUEST_METHOD'] == "POST")
		{
			$this->request_method = "post";
		}
		elseif($_SERVER['REQUEST_METHOD'] == "GET")
		{
			$this->request_method = "get";
		}
		// If we've got register globals on, then kill them too
		if (@ini_get("register_globals") == 1)
		{
			$this->unset_globals($_POST);
			$this->unset_globals($_GET);
			$this->unset_globals($_FILES);
			$this->unset_globals($_COOKIE);
		}
		if(isset($this->input['debug']))
		{
			$this->debug = 1;
		}
		$this->clean_input();
		
		// Old version of PHP, need to register_shutdown_function
		if(phpversion() < '5.0.5')
		{
			$this->use_shutdown = true;
			register_shutdown_function(array(&$this, "__destruct"));
		}
		
		if(isset($this->input['intcheck']) && $this->input['intcheck'] == 1)
		{
			die("&#077;&#089;&#066;&#066;");
		}
	}

	/**
	 * Parses the incoming variables.
	 *
	 * @param array The array of incoming variables.
	 */
	function parse_incoming($array)
	{
		if(!is_array($array))
		{
			return;
		}

		foreach($array as $key => $val)
		{
			$this->input[$key] = $val;
		}
	}

	/**
	 * Strips slashes out of a given array.
	 *
	 * @param array The array to strip.
	 */
	function strip_slashes_array(&$array)
	{
		foreach($array as $key => $val)
		{
			if(is_array($array[$key]))
			{
				$this->strip_slashes_array($array[$key]);
			}
			else
			{
				$array[$key] = stripslashes($array[$key]);
			}
		}
	}

	/**
	 * Unsets globals from a specific array.
	 *
	 * @param array The array to unset from.
	 */
	function unset_globals($array)
	{
		if(!is_array($array))
		{
			return;
		}

		foreach(array_keys($array) as $key)
		{
			unset($GLOBALS[$key]);
			global $$key;
			unset($$key);
		}
	}

	/**
	 * Cleans predefined input variables.
	 *
	 */
	function clean_input()
	{
		foreach($this->clean_variables as $type => $variables)
		{
			foreach($variables as $var)
			{
				// If this variable is in the ignored array, skip and move to next
				if(in_array($var, $this->ignore_clean_variables))
				{
					continue;
				}
				if($type == "int" && isset($this->input[$var]) && $this->input[$var] != "lastposter")
				{
					$this->input[$var] = intval($this->input[$var]);
				}
			}
		}
	}

	/**
	 * Logs a message.
	 *
	 * @param string The file to log to.
	 * @param string The message to log.
	 */
	function log_message($file, $message)
	{
		$handle = fopen($file, 'a');
		fwrite($handle, $message);
		fclose($handle);
	}

	/**
	 * Triggers a generic error.
	 *
	 * @param string The error code.
	 * @param boolean Halt code execution, true for halt.
	 */
	function trigger_generic_error($code, $halt=true)
	{
		switch($code)
		{
			case "cache_no_write":
				$message = "The data cache directory (inc/cache/) needs to exist and be writable by the web server. Change its permissions so that it is writable (777 on Unix based servers).";
				break;
			case "install_directory":
				$message = "The install directory (install/) still exists on your server and is not locked. To access MyBB please either remove this directory or create an empty file in it called 'lock'.";
				break;
			case "board_not_installed":
				$message = "Your board has not yet been installed and configured. Please do so before attempting to browse it.";
				break;
			default:
				$message = "MyBB has experienced an internal error. Please contact the MyBB Group for support. <a href=\"http://www.mybboard.net\">MyBB Website</a>";
		}
		include MYBB_ROOT."inc/generic_error.php";
		if($halt)
		{
			exit;
		}
	}
	
	function __destruct()
	{
		// Run shutdown function
		if(function_exists("run_shutdown"))
		{
			run_shutdown();
		}
	}
}
?>
