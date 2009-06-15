<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

class MyBB {
	/**
	 * The friendly version number of MyBB we're running.
	 *
	 * @var string
	 */
	var $version = "1.4.7";
	
	/**
	 * The version code of MyBB we're running.
	 *
	 * @var integer
	 */
	var $version_code = 1407;
	
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
	 * Cookie variables received from the outer world.
	 *
	 * @var array
	 */
	var $cookies = array();
	
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
	 * The request method that called this page.
	 *
	 * @var string.
	 */
	var $request_method = "";

	/**
	 * Variables that need to be clean.
	 *
	 * @var array
	 */
	var $clean_variables = array(
		"int" => array(
			"tid", "pid", "uid",
			"eid", "pmid", "fid",
			"aid", "rid", "sid",
			"vid", "cid", "bid",
			"pid", "gid", "mid",
			"wid", "lid", "iid",
			"sid"),
		"a-z" => array(
			"sortby", "order"
		)
	);
	
	/**
	 * Variables that are to be ignored from cleansing process
	 *
	 * @var array
	 */
	var $ignore_clean_variables = array();
	
	/**
	 * Using built in shutdown functionality provided by register_shutdown_function for < PHP 5?
	 */
	var $use_shutdown = false;
	
	/**
	 * Debug mode?
	 */
	var $debug_mode = false;

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
			if(isset($_REQUEST[$var]) || isset($_FILES[$var]))
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
		else if($_SERVER['REQUEST_METHOD'] == "GET")
		{
			$this->request_method = "get";
		}
		
		// If we've got register globals on, then kill them too
		if(@ini_get("register_globals") == 1)
		{
			$this->unset_globals($_POST);
			$this->unset_globals($_GET);
			$this->unset_globals($_FILES);
			$this->unset_globals($_COOKIE);
		}
		$this->clean_input();

		if(@ini_get("safe_mode") == 1)
		{
			$this->safemode = true;
		}

		// Are we running in debug mode?
		if(isset($mybb->input['debug']) || preg_match("#[?&]debug=1#", $_SERVER['REQUEST_URI']))
		{
			$this->debug_mode = true;
		}

		// Old version of PHP, need to register_shutdown_function
		$this->use_shutdown = true;
		register_shutdown_function(array(&$this, "__destruct"));

		if(isset($this->input['action']) && $this->input['action'] == "mybb_logo")
		{
			require_once dirname(__FILE__)."/mybb_group.php";
			output_logo();
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
	 * Parses the incoming cookies
	 *
	 */
	function parse_cookies()
	{
		if(!is_array($_COOKIE))
		{
			return;
		}
		
		$prefix_length = strlen($this->settings['cookieprefix']);

		foreach($_COOKIE as $key => $val)
		{
			if($prefix_length && substr($key, 0, $prefix_length) == $this->settings['cookieprefix'])
			{
				$key = substr($key, $prefix_length);
				
				// Fixes conflicts with one board having a prefix and another that doesn't on the same domain
				// Gives priority to our cookies over others (overwrites them)
				if($this->cookies[$key])
				{
					unset($this->cookies[$key]);
				}
			}
			
			if(!$this->cookies[$key])
			{
				$this->cookies[$key] = $val;
			}
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
			unset($GLOBALS[$key]); // Double unset to circumvent the zend_hash_del_key_or_index hole in PHP <4.4.3 and <5.1.4
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
				// If this variable is in the ignored array, skip and move to next.
				if(in_array($var, $this->ignore_clean_variables))
				{
					continue;
				}

				if(isset($this->input[$var]))
				{
					if($type == "int" && $this->input[$var] != "lastposter")
					{
						$this->input[$var] = intval($this->input[$var]);
					}
					else if($type == "a-z")
					{
						$this->input[$var] = preg_replace("#[^a-z\.\-_]#i", "", $this->input[$var]);
					}
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
	 */
	function trigger_generic_error($code)
	{
		global $error_handler;
		
		switch($code)
		{
			case "cache_no_write":
				$message = "The data cache directory (cache/) needs to exist and be writable by the web server. Change its permissions so that it is writable (777 on Unix based servers).";
				break;
			case "install_directory":
				$message = "The install directory (install/) still exists on your server and is not locked. To access MyBB please either remove this directory or create an empty file in it called 'lock'.";
				break;
			case "board_not_installed":
				$message = "Your board has not yet been installed and configured. Please do so before attempting to browse it.";
				break;
			case "board_not_upgraded":
				$message = "Your board has not yet been upgraded. Please do so before attempting to browse it.";
				break;
			case "sql_load_error":
				$message = "MyBB was unable to load the SQL extension. Please contact the MyBB Group for support. <a href=\"http://www.mybboard.net\">MyBB Website</a>";
				break;
			default:
				$message = "MyBB has experienced an internal error. Please contact the MyBB Group for support. <a href=\"http://www.mybboard.net\">MyBB Website</a>";
		}
		$error_handler->trigger($message, MYBB_GENERAL);
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

/**
 * Do this here because the core is used on every MyBB page
 */

$grouppermignore = array("gid", "type", "title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$groupzerogreater = array("pmquota", "maxpmrecipients", "maxreputationsday", "attachquota", "maxemails", "maxwarningsday");
$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");

// These are fields in the usergroups table that are also forum permission specific.
$fpermfields = array(
	'canview',
	'canviewthreads',
	'candlattachments',
	'canpostthreads',
	'canpostreplys',
	'canpostattachments',
	'canratethreads',
	'caneditposts',
	'candeleteposts',
	'candeletethreads',
	'caneditattachments',
	'canpostpolls',
	'canvotepolls',
	'cansearch'
);

?>