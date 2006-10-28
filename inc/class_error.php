<?php
/**
 * MyBB 1.2
 * Copyright � 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */
 
define("MYBB_SQL", 20); // some digit here not in use by php's error numbers
define("MYBB_TEMPLATE", 30); // some other digit here not in use by php's error numbers);
 
class errorHandler {

	/**
	 * Array of all of the error types
	 *
	 * @var array
	 */
	var $error_types = array( 
		E_ERROR              => 'Error',
		E_WARNING            => 'Warning',
		E_PARSE              => 'Parsing Error',
		E_NOTICE             => 'Notice',
		E_CORE_ERROR         => 'Core Error',
		E_CORE_WARNING       => 'Core Warning',
		E_COMPILE_ERROR      => 'Compile Error',
		E_COMPILE_WARNING    => 'Compile Warning',
		E_USER_ERROR         => 'User Error',
		E_USER_WARNING       => 'User Warning',
		E_USER_NOTICE        => 'User Notice',
		E_STRICT             => 'Runtime Notice',
		E_RECOVERABLE_ERROR  => 'Catchable Fatal Error',
		MYBB_SQL 			 => 'MyBB SQL Error', 
		MYBB_TEMPLATE		 => 'MyBB Template Error'
		MYBB_GENERAL		 => 'MyBB Error',
	);
	
	/**
	 * Array of all of the error types to ignore
	 *
	 * @var array
	 */
	var $ignore_types = array(
		E_NOTICE,
		E_USER_NOTICE,
		E_STRICT
	);
	
	/**
	 * String of all the warnings collected
	 *
	 * @var string
	 */
	var $warnings = "";
	
	/**
	 * Initializes the error handler
	 *
	 */
	function errorHandler()
	{
		// Lets set the error handler in here so we can just do $handler = new errorHandler() and be all set up
		if(version_compare(PHP_VERSION, ">=", "5"))
		{
			set_error_handler(array(&$this, "error"), array_diff($this->error_types, $this->ignore_types));
		}
		else
		{
			set_error_handler(array(&$this, "error"));
		}
	}
 	
	/**
	 * Parses a error for processing.
	 *
	 * @param string The error type (i.e. E_ERROR, E_FATAL)
	 * @param string The error message
	 * @param string The error file
	 * @param integer The error line
	 * @return boolean True if parsing was a success, otherwise assume a error
	 */			
	function error($type, $message, $file=null, $line=0)
	{
		global $mybb;

		if(in_array($type, $this->ignore_types))
		{
			return;
		}
		
		if($mybb->settings['errortypemedium'] == "both" || strstr(strtolower($this->error_types[$type]), $mybb->settings['errortypemedium']))
		{
			// Saving error to log file
			if($mybb->settings['errorlogmedium'] == "log" || $mybb->settings['errorlogmedium'] == "both")
			{
				$this->log_error($type, $message, $file, $line);
			}

			// Are we emailing the Admin a copy?
			if($mybb->settings['errorlogmedium'] == "mail" || $mybb->settings['errorlogmedium'] == "both")
			{
				$this->email_error($type, $message, $file, $line);
			}
			
			if($type == MYBB_SQL) 
			{
				echo "MyBB has experienced an internal SQL error and cannot continue.<br />\n";
				if($mybb->usergroup['cancp'] == "yes")
				{
					echo "SQL Error: {$message['error_no']} - {$message['error']}<br />Query: {$message['query']}";
				}
				exit(1);
			}
			else
			{	
				if(!strstr(strtolower($this->error_types[$type]), 'warning'))
				{
					echo "MyBB has experienced an internal error. Please contact the MyBB Group for support. <a href=\"http://www.mybboard.com\">MyBB Website</a>. If you are the administrator, please check your error logs for further details.<br /><br />\n\n";
					echo "<b>{$this->error_types[$type]}</b> [$type] $message<br />\n";
					echo "  Fatal error in line $line of file $file PHP ".PHP_VERSION." (".PHP_OS.")<br />\n";
					echo "Aborting...";
					exit(1);
				}
				else
				{
					$this->warnings .= "<b>{$this->error_types[$type]}</b> [$type] $message - Line: $line - File: $file PHP ".PHP_VERSION." (".PHP_OS.")<br />\n";
				}
			}
		}
		
		return true;
	}
	
	/**
	 * Returns all the warnings
	 *
	 * @return string The warnings
	 */
	function show_warnings()
	{
		global $lang, $templates;
		
		if($this->warnings != "")
		{
			if(!$lang->warnings)
			{
				$lang->warnings = "The following warnings occured:";
			}
			
			if(defined("IN_ADMINCP"))
			{
				$warning = makeacpphpwarning($this->warnings);
			}
			else
			{
				eval("\$warning = \"".$templates->get("warnings")."\";");
			}
			
			return $warning;
		}
	}
	
	/**
	 * Trigers a user created error Example: $error_handler->trigger("Some Warning", E_USER_WARNING);
	 *
	 * @param string Warning message
	 * @param string Warning type
	 */
	function trigger($message="", $type='E_USER_WARNING')
	{
		global $lang;
		
		if(!$message)
		{
			$message = $lang->unknown_user_trigger;
		}
		
		if($type == MYBB_SQL)
		{
			$this->error($type, $message);
		}
		else
		{
			trigger_error($message, $type);		
		}
	}

	/**
	 * Logs the error in the specified error log file.
	 *
	 * @param string Warning type
	 * @param string Warning message
	 * @param string Warning file
	 * @param integer Warning line
	 */
	function log_error($type, $message, $file, $line)
	{
		global $mybb;

		if($type == MYBB_SQL) 
		{
			$message = "SQL Error: {$message['error_no']} - {$message['error']}\nQuery: {$message['query']}";
		}
		$error_data = "<error>\n";
		$error_data .= "\t<dateline>".time()."</dateline>\n";
		$error_data .= "\t<script>".$file."</script>\n";
		$error_data .= "\t<line>".$line."</line>\n";
		$error_data .= "\t<type>".$type."</type>\n";
		$error_data .= "\t<friendly_type>".$this->error_types[$type]."</friendly_type>\n";
		$error_data .= "\t<message>".$message."</message>\n";
		$error_data .= "</error>\n\n";

		if(trim($mybb->settings['errorloglogaction']) != "")
		{
			error_log($error_data, 3, $mybb->settings['errorloglocation']);
		}
		else
		{
			error_log($error_data, 0);
		}
	}

	/**
	 * Emails the error in the specified error log file.
	 *
	 * @param string Warning type
	 * @param string Warning message
	 * @param string Warning file
	 * @param integer Warning line
	 */
	function email_error($type, $message, $file, $line)
	{
		global $mybb;

		if(!$mybb->settings['adminemail'])
		{
			return false;
		}

		if($type == MYBB_SQL) 
		{
			$message = "SQL Error: {$message['error_no']} - {$message['error']}\nQuery: {$message['query']}";
		}
		
		$message = "Your copy of MyBB running on {$mybb->settings['bbname']} ({$mybb->settings['bburl']}) has experienced an error. Details of the error include:\n---\nType: $type\nFile: $file (Line no. $line)\nMessage\n$message";
		@my_mail($mybb->settings['adminemail'], "MyBB error on {$mybb->settings['bbname']}", $message, $mybb->settings['adminemail']);
	}

}
?>