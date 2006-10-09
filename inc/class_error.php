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
 
function error_handler($errno, $errmsg, $filename, $linenum, $vars)
{
	global $error_handler;
	
	$error_handler->error($errno, $errmsg, $filename, $linenum, $vars);
} 
 
class errorHandler {
 
 	/**
	 * The friendly version number of MyBB we're running.
	 *
	 * @var string
	 */
	//var $version = "1.2";
	
	function error($errno, $errmsg, $filename, $linenum, $vars)
	{
		global $mybb;
		
		$errortype = array (
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
			E_RECOVERABLE_ERRROR => 'Catchable Fatal Error'
		);
		
		// set of errors for which a var trace will be saved
		$user_errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);
		
		$err = "<errorentry>\n";
		$err .= "\t<datetime>".$dt."</datetime>\n";
		$err .= "\t<errornum>".$errno. "</errornum>\n";
		$err .= "\t<errortype>".$errortype[$errno]. "</errortype>\n";
		$err .= "\t<errormsg>".$errmsg."</errormsg>\n";
		$err .= "\t<scriptname>".$filename."</scriptname>\n";
		$err .= "\t<scriptlinenum>".$linenum."</scriptlinenum>\n";
	
	   	if(in_array($errno, $user_errors)) 
	   	{
	   	   	if(function_exists('wddx_serialize_value'))
			{
				$err .= "\t<vartrace>".wddx_serialize_value($vars, "Variables")."</vartrace>\n";
			}
	   	}
	   	$err .= "</errorentry>\n\n";
	  
	   	// for testing
	   	echo $err;
	   		
	   	// save to the error log, and e-mail me if there is a critical user error
	   	error_log($err, 3, "/usr/local/php4/error.log");
	   	if($errno == E_USER_ERROR) 
	   	{
		   	mail("admin@localhost", "Critical User Error", $err);
	   	}
		
		echo "MyBB has experienced an internal error. Please contact the MyBB Group for support. <a href=\"http://www.mybboard.com\">MyBB Website</a>. If you are the administrator, please check your server logs for further details.";
	}
	
	function trigger_user_error()
	{
	}
}