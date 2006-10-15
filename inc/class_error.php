<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
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
		E_RECOVERABLE_ERRROR => 'Catchable Fatal Error',
		MYBB_SQL 			 => 'MyBB SQL Error', 
		MYBB_TEMPALTE		 => 'MyBB Template Error'
	);
	
	/**
	 * Array of all of the error types to ignore
	 *
	 * @var array
	 */
	var $ignore_types = array(
		E_NOTICE,
		E_USER_NOTICE,
	);
	
	/**
	 * String of all the warnings collected
	 *
	 * @var string
	 */
	var $warnings = "";
	
	/**
	 * Initalizes the error handler
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
	 * Parses a error for proccessing.
	 *
	 * @param string The error type (i.e. E_ERROR, E_FATAL)
	 * @param string The error message
	 * @param string The error file
	 * @param integer The error line
	 * @return boolean True if parsing was a success, otherwise assume a error
	 */			
	function error($type, $message, $file=null, $line=0)
	{
		if(in_array($type, $this->ignore_types))
		{
			return;
		}
		
		global $mybb;
		
		// set of errors for which a var trace will be saved
		$user_errors = array(E_USER_ERROR, E_USER_WARNING);
		
		$err = "<errorentry>\n";
		$err .= "\t<datetime>".time()."</datetime>\n";
		$err .= "\t<errornum>".$type."</errornum>\n";
		$err .= "\t<errortype>".$this->error_types[$type]."</errortype>\n";
		$err .= "\t<errormsg>".$message."</errormsg>\n";
		$err .= "\t<scriptname>".$file."</scriptname>\n";
		$err .= "\t<scriptlinenum>".$line."</scriptlinenum>\n";
	
		if(in_array($type, $user_errors) && function_exists('wddx_serialize_value')) 
		{
			$err .= "\t<vartrace>".wddx_serialize_value($vars, "Variables")."</vartrace>\n";
		}
		$err .= "</errorentry>\n\n";
	  
		if($mybb->settings['errortypemedium'] == "both" || strstr(strtolower($this->error_types[$type]), $mybb->settings['errortypemedium']))
		{	
			// save to the error log, and e-mail me if there is a critical error
			if($mybb->settings['errorlogmedium'] == 'both')
			{
				if(trim($mybb->settings['errorloglocation']) != "")
				{
					error_log($err, 3, $mybb->settings['errorloglocation']);
				}
				else
				{
					error_log($err, 0);
				}
				
				if(trim($mybb->settings['errorhandlingemail']) != "")
				{
					@my_mail($mybb->settings['errorhandlingemail'], 'Your forum had a error', $err, $mybb->settings['adminemail']);
				}
			}
			elseif($mybb->settings['errorlogmedium'] == 'mail')
			{
				if(trim($mybb->settings['errorhandlingemail']) != "")
				{
					@my_mail($mybb->settings['errorhandlingemail'], 'Your forum had a error', $err, $mybb->settings['adminemail']);
				}
			}
			else if($mybb->Settings['errorlogmedium'] == 'log')
			{
				if(trim($mybb->settings['errorloglocation']) != "")
				{
					error_log($err, 3, $mybb->settings['errorloglocation']);
				}
				else
				{
					error_log($err, 0);
				}
			}
	
			if($type == MYBB_SQL) 
			{
				if($this->warnings)
				{
					echo $this->show_warnings()."<br /><br />";
				}
				echo "MyBB has experienced an internal SQL error. Please contact the MyBB Group for support. <a href=\"http://www.mybboard.com\">MyBB Website</a>. If you are the administrator, please check your error logs for further details.<br />";
				exit(1);
			}
			else
			{	
				if(!strstr(strtolower($this->error_types[$type]), 'warning'))
				{
					if($this->warnings)
					{
						echo $this->show_warnings()."<br /><br />";
					}
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
}
?>