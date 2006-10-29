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
 
// Define Custom MyBB error handler constants with a value not used by php's error handler
define("MYBB_SQL", 20);
define("MYBB_TEMPLATE", 30);
define("MYBB_GENERAL", 40);
if(!defined("E_STRICT"))
{
	// This constant has been defined since PHP 5
	define("E_STRICT", 2048);
}
if(!defined("E_RECOVERABLE_ERROR"))
{
	// This constant has been defined since PHP 5.2 (which hasn't even been released yet)
	define("E_RECOVERABLE_ERROR", 4096);
}
 
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
		MYBB_TEMPLATE		 => 'MyBB Template Error',
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
		
		if(($mybb->settings['errortypemedium'] == "both" || !$mybb->settings['errortypemedium']) || strstr(strtolower($this->error_types[$type]), $mybb->settings['errortypemedium']))
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
				$this->output_error($type, $message, $file, $line);
			}
			else
			{	
				if(!strstr(strtolower($this->error_types[$type]), 'warning'))
				{
					$this->output_error($type, $message, $file, $line);
				}
				else
				{
					$this->warnings .= "<strong>{$this->error_types[$type]}</strong> [$type] $message - Line: $line - File: $file PHP ".PHP_VERSION." (".PHP_OS.")<br />\n";
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
	 * Triggers a user created error 
	 * Example: $error_handler->trigger("Some Warning", E_USER_ERROR);
	 *
	 * @param string Message
	 * @param string Type
	 */
	function trigger($message="", $type=E_USER_ERROR)
	{
		global $lang;
		
		if(!$message)
		{
			$message = $lang->unknown_user_trigger;
		}
		
		if($type == MYBB_SQL || $type == MYBB_TEMPLATE || $type == MYBB_GENERAL)
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

	function output_error($type, $message, $file, $line)
	{
		global $mybb;

		if(!$mybb->settings['bbname'])
		{
			$mybb->settings['bbname'] = "MyBB";
		}

		if($type == MYBB_SQL)
		{
			$title = "MyBB SQL Error";
			$error_message = "<p>MyBB has experienced an internal SQL error and cannot continue.</p>";
			$error_message .= "<dl>\n";
			$error_message .= "<dt>SQL Error:</dt>\n<dd>{$message['error_no']} - {$message['error']}</dd>\n";
			if($message['query'] != "")
			{
				$error_message .= "<dt>Query:</dt>\n<dd>{$message['query']}</dd>\n";
			}
			$error_message .= "</dl>\n";
		}
		else
		{
			$title = "MyBB Internal Error";
			$error_message = "<p>MyBB has experienced an internal error and cannot continue.</p>";
			$error_message .= "<dl>\n";
			$error_message .= "<dt>Error Type:</dt>\n<dd>{$this->error_types[$type]} ($type)</dd>\n";
			$error_message .= "<dt>Error Message:</dt>\n<dd>{$message}</dd>\n";
			if($file)
			{
				$error_message .= "<dt>Location:</dt><dd>File: {$file}<br />Line: {$line}</dd>\n";
			}
			$error_message .= "</dl>\n";
		}

		echo <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >
<head profile="http://gmpg.org/xfn/11">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>{$mybb->settings['bbname']} - Internal Error</title>
	<style type="text/css">
		body { background: #efefef; color: #000; font-family: Verdana; font-size: 12px; text-align: center; line-height: 1.4; }
		a:link { color: #026CB1; text-decoration: none;	}
		a:visited {	color: #026CB1;	text-decoration: none; }
		a:hover, a:active {	color: #000; text-decoration: underline; }
		#container { width: 600px; padding: 20px; background: #fff;	border: 1px solid #e4e4e4; margin: 100px auto; text-align: left; }
		h1 { margin: 0; background: url({$_SERVER['PHP_SELF']}?action=mybb_logo) no-repeat;	height: 82px; width: 248px; }
		#content { border: 1px solid #B60101; background: #fff; }
		h2 { font-size: 12px; padding: 4px; background: #B60101; color: #fff; margin: 0; }
		.invisible { display: none; }
		#error { padding: 6px; }
		#footer { font-size: 11px; border-top: 1px solid #ccc; padding-top: 10px; }
		dt { font-weight: bold; }
	</style>
</head>
<body>
	<div id="container">
		<div id="logo">
			<h1><span class="invisible">MyBB</span></a></h1>
		</div>

		<div id="content">
			<h2>{$title}</h2>

			<div id="error">
				{$error_message}
				<p id="footer">Please contact the <a href="http://www.mybboard.net">MyBB Group</a> for support.</p>
			</div>
		</div>
	</div>
</body>
</html>
EOF;
		exit(1);
	}
}
?>