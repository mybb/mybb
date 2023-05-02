<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Set to 1 if receiving a blank page (template failure).
define("MANUAL_WARNINGS", 0);

// Define Custom MyBB error handler constants with a value not used by php's error handler.
define("MYBB_SQL", 20);
define("MYBB_TEMPLATE", 30);
define("MYBB_GENERAL", 40);
define("MYBB_NOT_INSTALLED", 41);
define("MYBB_NOT_UPGRADED", 42);
define("MYBB_INSTALL_DIR_EXISTS", 43);
define("MYBB_SQL_LOAD_ERROR", 44);
define("MYBB_CACHE_NO_WRITE", 45);
define("MYBB_CACHEHANDLER_LOAD_ERROR", 46);

if(!defined("E_RECOVERABLE_ERROR"))
{
	// This constant has been defined since PHP 5.2.
	define("E_RECOVERABLE_ERROR", 4096);
}

if(!defined("E_DEPRECATED"))
{
	// This constant has been defined since PHP 5.3.
	define("E_DEPRECATED", 8192);
}

if(!defined("E_USER_DEPRECATED"))
{
	// This constant has been defined since PHP 5.3.
	define("E_USER_DEPRECATED", 16384);
}

class errorHandler {

	/**
	 * Array of all of the error types
	 *
	 * @var array
	 */
	public $error_types = array(
		E_ERROR							=> 'Error',
		E_WARNING						=> 'Warning',
		E_PARSE							=> 'Parsing Error',
		E_NOTICE						=> 'Notice',
		E_CORE_ERROR					=> 'Core Error',
		E_CORE_WARNING					=> 'Core Warning',
		E_COMPILE_ERROR					=> 'Compile Error',
		E_COMPILE_WARNING				=> 'Compile Warning',
		E_DEPRECATED					=> 'Deprecated Warning',
		E_USER_ERROR					=> 'User Error',
		E_USER_WARNING					=> 'User Warning',
		E_USER_NOTICE					=> 'User Notice',
		E_USER_DEPRECATED	 			=> 'User Deprecated Warning',
		E_STRICT						=> 'Runtime Notice',
		E_RECOVERABLE_ERROR				=> 'Catchable Fatal Error',
		MYBB_SQL 						=> 'MyBB SQL Error',
		MYBB_TEMPLATE					=> 'MyBB Template Error',
		MYBB_GENERAL					=> 'MyBB Error',
		MYBB_NOT_INSTALLED				=> 'MyBB Error',
		MYBB_NOT_UPGRADED				=> 'MyBB Error',
		MYBB_INSTALL_DIR_EXISTS			=> 'MyBB Error',
		MYBB_SQL_LOAD_ERROR				=> 'MyBB Error',
		MYBB_CACHE_NO_WRITE				=> 'MyBB Error',
		MYBB_CACHEHANDLER_LOAD_ERROR	=> 'MyBB Error',
	);

	/**
	 * Array of MyBB error types
	 *
	 * @var array
	 */
	public $mybb_error_types = array(
		MYBB_SQL,
		MYBB_TEMPLATE,
		MYBB_GENERAL,
		MYBB_NOT_INSTALLED,
		MYBB_NOT_UPGRADED,
		MYBB_INSTALL_DIR_EXISTS,
		MYBB_SQL_LOAD_ERROR,
		MYBB_CACHE_NO_WRITE,
		MYBB_CACHEHANDLER_LOAD_ERROR,
	);

	/**
	 * Array of all of the error types to ignore
	 *
	 * @var array
	 */
	public $ignore_types = array(
		E_DEPRECATED,
		E_NOTICE,
		E_USER_NOTICE,
		E_STRICT
	);

	/**
	 * String of all the warnings collected
	 *
	 * @var string
	 */
	public $warnings = "";

	/**
	 * Is MyBB in an errornous state? (Have we received an error?)
	 *
	 * @var boolean
	 */
	public $has_errors = false;

	/**
	 * Display errors regardless of related settings (useful during initialization stage)
	 *
	 * @var boolean
	 */
	public $force_display_errors = false;

	/**
	 * Initializes the error handler
	 *
	 */
	function __construct()
	{
		// Lets set the error handler in here so we can just do $handler = new errorHandler() and be all set up.
		$error_types = E_ALL;
		foreach($this->ignore_types as $bit)
		{
			$error_types = $error_types & ~$bit;
		}
		error_reporting($error_types);
		set_error_handler(array(&$this, "error_callback"), $error_types);
	}

	/**
	 * Passes relevant arguments for error processing.
	 *
	 * @param string $type The error type (i.e. E_ERROR, E_FATAL)
	 * @param string $message The error message
	 * @param string $file The error file
	 * @param integer $line The error line
	 */
	function error_callback($type, $message, $file=null, $line=0)
	{
		return $this->error($type, $message, $file, $line);
	}

	/**
	 * Processes an error.
	 *
	 * @param string $type The error type (i.e. E_ERROR, E_FATAL)
	 * @param string $message The error message
	 * @param string $file The error file
	 * @param integer $line The error line
	 * @param boolean $allow_output Whether or not output is permitted
	 * @return boolean True if parsing was a success, otherwise assume a error
	 */
	function error($type, $message, $file=null, $line=0, $allow_output=true)
	{
		global $mybb;

		// Error reporting turned off for this type
		if((error_reporting() & $type) == 0)
		{
			return true;
		}

		if(in_array($type, $this->ignore_types))
		{
			return true;
		}

		$file = str_replace(MYBB_ROOT, "", $file);

		if($type == MYBB_SQL || strpos(strtolower($this->error_types[$type]), 'warning') === false)
		{
			$this->has_errors = true;
		}

		// For some reason in the installer this setting is set to "<"
		$accepted_error_types = array('both', 'error', 'warning', 'none');
		if(isset($mybb->settings['errortypemedium']) && in_array($mybb->settings['errortypemedium'], $accepted_error_types))
		{
			$errortypemedium = $mybb->settings['errortypemedium'];
		}
		else
		{
			$errortypemedium = "none";
		}

		if(isset($mybb->settings['errorlogmedium']))
		{
			$errorlogmedium = $mybb->settings['errorlogmedium'];
		}
		else
		{
			$errorlogmedium = 'none';
		}

		if(defined("IN_TASK"))
		{
			global $task;

			require_once MYBB_ROOT."inc/functions_task.php";

			$filestr = '';
			if($file)
			{
				$filestr = " - Line: $line - File: $file";
			}

			add_task_log($task, "{$this->error_types[$type]} - [$type] ".var_export($message, true)."{$filestr}");
		}

		// Saving error to log file.
		if($errorlogmedium == "log" || $errorlogmedium == "both")
		{
			$this->log_error($type, $message, $file, $line);
		}

		// Are we emailing the Admin a copy?
		if($errorlogmedium == "mail" || $errorlogmedium == "both")
		{
			$this->email_error($type, $message, $file, $line);
		}

		if($allow_output === true)
		{
			// SQL Error
			if($type == MYBB_SQL)
			{
				$this->output_error($type, $message, $file, $line);
			}
			// PHP Error
			elseif(strpos(strtolower($this->error_types[$type]), 'warning') === false)
			{
				$this->output_error($type, $message, $file, $line);
			}
			// PHP Warning
			elseif(in_array($errortypemedium, array('warning', 'both')))
			{
				global $templates;

				$warning = "<strong>{$this->error_types[$type]}</strong> [$type] $message - Line: $line - File: $file PHP ".PHP_VERSION." (".PHP_OS.")<br />\n";
				if(is_object($templates) && method_exists($templates, "get") && !defined("IN_ADMINCP"))
				{
					$this->warnings .= $warning;
					$this->warnings .= $this->generate_backtrace();
				}
				else
				{
					echo "<div class=\"php_warning\">{$warning}".$this->generate_backtrace()."</div>";
				}
			}
		}

		return true;
	}

	/**
	 * Returns all the warnings
	 *
	 * @return string|bool The warnings or false if no warnings exist
	 */
	function show_warnings()
	{
		global $lang, $templates;

		if(empty($this->warnings))
		{
			return false;
		}

		// Incase a template fails and we're receiving a blank page.
		if(MANUAL_WARNINGS)
		{
			echo $this->warnings."<br />";
		}

		if(!$lang->warnings)
		{
			$lang->warnings = "The following warnings occurred:";
		}

		$template_exists = false;

		if(!is_object($templates) || !method_exists($templates, 'get'))
		{
			if(@file_exists(MYBB_ROOT."inc/class_templates.php"))
			{
				@require_once MYBB_ROOT."inc/class_templates.php";
				$templates = new templates;
				$template_exists = true;
			}
		}
		else
		{
			$template_exists = true;
		}

		$warning = '';
		if($template_exists == true)
		{
			eval("\$warning = \"".$templates->get("php_warnings")."\";");
		}

		return $warning;
	}

	/**
	 * Triggers a user created error
	 * Example: $error_handler->trigger("Some Warning", E_USER_ERROR);
	 *
	 * @param string $message Message
	 * @param string|int $type Type
	 */
	function trigger($message="", $type=E_USER_ERROR)
	{
		global $lang;

		if(!$message)
		{
			if(isset($lang->unknown_user_trigger))
			{
				$message = $lang->unknown_user_trigger;
			}
			else
			{
				$message .= 'An unknown error has been triggered.';
			}
		}

		if(in_array($type, $this->mybb_error_types))
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
	 * @param string $type Warning type
	 * @param string $message Warning message
	 * @param string $file Warning file
	 * @param integer $line Warning line
	 */
	function log_error($type, $message, $file, $line)
	{
		global $mybb;

		if($type == MYBB_SQL)
		{
			$message = "SQL Error: {$message['error_no']} - {$message['error']}\nQuery: {$message['query']}";
		}

		// Do not log something that might be executable
		$message = str_replace('<?', '< ?', $message);

		$back_trace = $this->generate_backtrace(false, 2);

		if($back_trace)
		{
			$back_trace = "\t<back_trace>{$back_trace}</back_trace>\n";
		}

		$error_data = "<error>\n";
		$error_data .= "\t<dateline>".TIME_NOW."</dateline>\n";
		$error_data .= "\t<script>".$file."</script>\n";
		$error_data .= "\t<line>".$line."</line>\n";
		$error_data .= "\t<type>".$type."</type>\n";
		$error_data .= "\t<friendly_type>".$this->error_types[$type]."</friendly_type>\n";
		$error_data .= "\t<message>".$message."</message>\n";
		$error_data .= $back_trace;
		$error_data .= "</error>\n\n";

		if(isset($mybb->settings['errorloglocation']) && trim($mybb->settings['errorloglocation']) != "")
		{
			@error_log($error_data, 3, $mybb->settings['errorloglocation']);
		}
		else
		{
			@error_log($error_data, 0);
		}
	}

	/**
	 * Emails the error in the specified error log file.
	 *
	 * @param string $type Warning type
	 * @param string $message Warning message
	 * @param string $file Warning file
	 * @param integer $line Warning line
	 * @return bool returns false if no admin email is set
	 */
	function email_error($type, $message, $file, $line)
	{
		global $mybb;

		if(empty($mybb->settings['adminemail']))
		{
			return false;
		}

		if($type == MYBB_SQL)
		{
			$message = "SQL Error: {$message['error_no']} - {$message['error']}\nQuery: {$message['query']}";
		}

		if(function_exists('debug_backtrace'))
		{
			ob_start();
			debug_print_backtrace();
			$trace = ob_get_contents();
			ob_end_clean();

			$back_trace = "\nBack Trace: {$trace}";
		}
		else
		{
			$back_trace = '';
		}

		$message = "Your copy of MyBB running on {$mybb->settings['bbname']} ({$mybb->settings['bburl']}) has experienced an error. Details of the error include:\n---\nType: $type\nFile: $file (Line no. $line)\nMessage\n$message{$back_trace}";

		@my_mail($mybb->settings['adminemail'], "MyBB error on {$mybb->settings['bbname']}", $message, $mybb->settings['adminemail']);

		return true;
	}

	/**
	 * @param string $type
	 * @param string $message
	 * @param string $file
	 * @param int $line
	 */
	function output_error($type, $message, $file, $line)
	{
		global $mybb, $parser, $lang;

		if(isset($mybb->settings['bbname']))
		{
			$bbname = $mybb->settings['bbname'];
		}
		else
		{
			$bbname = "MyBB";
		}

		// For some reason in the installer this setting is set to "<"
		$accepted_error_types = array('both', 'error', 'warning', 'none');
		if(isset($mybb->settings['errortypemedium']) && in_array($mybb->settings['errortypemedium'], $accepted_error_types))
		{
			$errortypemedium = $mybb->settings['errortypemedium'];
		}
		else
		{
			$errortypemedium = "none";
		}

		$show_details = (
			$this->force_display_errors ||
			in_array($errortypemedium, array('both', 'error')) ||
			defined("IN_INSTALL") ||
			defined("IN_UPGRADE")
		);

		if($type == MYBB_SQL)
		{
			$title = "MyBB SQL Error";
			$error_message = "<p>MyBB has experienced an internal SQL error and cannot continue.</p>";
			if($show_details)
			{
				$message['query'] = htmlspecialchars_uni($message['query']);
				$message['error'] = htmlspecialchars_uni($message['error']);
				$error_message .= "<dl>\n";
				$error_message .= "<dt>SQL Error:</dt>\n<dd>{$message['error_no']} - {$message['error']}</dd>\n";
				if($message['query'] != "")
				{
					$error_message .= "<dt>Query:</dt>\n<dd>{$message['query']}</dd>\n";
				}
				$error_message .= "</dl>\n";
			}
		}
		else
		{
			$title = "MyBB Internal Error";
			$error_message = "<p>MyBB has experienced an internal error and cannot continue.</p>";
			if($show_details)
			{
				$error_message .= "<dl>\n";
				$error_message .= "<dt>Error Type:</dt>\n<dd>{$this->error_types[$type]} ($type)</dd>\n";
				$error_message .= "<dt>Error Message:</dt>\n<dd>{$message}</dd>\n";
				if(!empty($file))
				{
					$error_message .= "<dt>Location:</dt><dd>File: {$file}<br />Line: {$line}</dd>\n";
					if(!@preg_match('#config\.php|settings\.php#', $file) && @file_exists($file))
					{
						$code_pre = @file($file);

						$code = "";

						if(isset($code_pre[$line-4]))
						{
							$code .= $line-3 . ". ".$code_pre[$line-4];
						}

						if(isset($code_pre[$line-3]))
						{
							$code .= $line-2 . ". ".$code_pre[$line-3];
						}

						if(isset($code_pre[$line-2]))
						{
							$code .= $line-1 . ". ".$code_pre[$line-2];
						}

						$code .= $line . ". ".$code_pre[$line-1]; // The actual line.

						if(isset($code_pre[$line]))
						{
							$code .= $line+1 . ". ".$code_pre[$line];
						}

						if(isset($code_pre[$line+1]))
						{
							$code .= $line+2 . ". ".$code_pre[$line+1];
						}

						if(isset($code_pre[$line+2]))
						{
							$code .= $line+3 . ". ".$code_pre[$line+2];
						}

						unset($code_pre);

						$parser_exists = false;

						if(!is_object($parser) || !method_exists($parser, 'mycode_parse_php'))
						{
							if(@file_exists(MYBB_ROOT."inc/class_parser.php"))
							{
								@require_once MYBB_ROOT."inc/class_parser.php";
								$parser = new postParser;
								$parser_exists = true;
							}
						}
						else
						{
							$parser_exists = true;
						}

						if($parser_exists)
						{
							$code = $parser->mycode_parse_php($code, true);
						}
						else
						{
							$code = @nl2br($code);
						}

						$error_message .= "<dt>Code:</dt><dd>{$code}</dd>\n";
					}
				}
				$backtrace = $this->generate_backtrace();
				if($backtrace && !in_array($type, $this->mybb_error_types))
				{
					$error_message .= "<dt>Backtrace:</dt><dd>{$backtrace}</dd>\n";
				}
				$error_message .= "</dl>\n";
			}
		}

		if(isset($lang->settings['charset']))
		{
			$charset = $lang->settings['charset'];
		}
		else
		{
			$charset = 'UTF-8';
		}

		$contact_site_owner = '';
		$is_in_contact = defined('THIS_SCRIPT') && THIS_SCRIPT === 'contact.php';
		if(
			!empty($mybb->settings['contactlink']) &&
			(
				!empty($mybb->settings['contact']) &&
				!$is_in_contact &&
				(
					$mybb->settings['contactlink'] == "contact.php" &&
					(
						!isset($mybb->user['uid']) ||
						($mybb->settings['contact_guests'] != 1 && $mybb->user['uid'] == 0) ||
						$mybb->user['uid'] > 0
					)
				) ||
				$mybb->settings['contactlink'] != "contact.php"
			)
		)
		{
			if(
				!my_validate_url($mybb->settings['contactlink'], true, true) &&
				my_substr($mybb->settings['contactlink'], 0, 7) != 'mailto:'
			)
			{
				$mybb->settings['contactlink'] = $mybb->settings['bburl'].'/'.$mybb->settings['contactlink'];
			}

			$contact_site_owner = <<<HTML
 If this problem persists, please <a href="{$mybb->settings['contactlink']}">contact the site owner</a>.
HTML;
		}

		$additional_name = '';
		$docs_link = 'https://docs.mybb.com';
		$common_issues_link = 'https://docs.mybb.com/1.8/faq/';
		$support_link = 'https://community.mybb.com/';

		if(isset($lang->settings['docs_link']))
		{
			$docs_link = $lang->settings['docs_link'];
		}

		if(isset($lang->settings['common_issues_link']))
		{
			$common_issues_link = $lang->settings['common_issues_link'];
		}

		if(isset($lang->settings['support_link']))
		{
			$support_link = $lang->settings['support_link'];
		}


		if(isset($lang->settings['additional_name']))
		{
			$additional_name = $lang->settings['additional_name'];
		}

		$contact = <<<HTML
<p>
	<strong>If you're a visitor of this website</strong>, please wait a few minutes and try again.{$contact_site_owner}
</p>

<p>
	<strong>If you are the site owner</strong>, please check the <a href="{$docs_link}">MyBB{$additional_name} Documentation</a> for help resolving <a href="{$common_issues_link}">common issues</a>, or get technical help on the <a href="{$support_link}">MyBB{$additional_name} Community Forums</a>.
</p>
HTML;

		if(!headers_sent() && !defined("IN_INSTALL") && !defined("IN_UPGRADE"))
		{
			@header('HTTP/1.1 503 Service Temporarily Unavailable');
			@header('Status: 503 Service Temporarily Unavailable');
			@header('Retry-After: 1800');
			@header("Content-type: text/html; charset={$charset}");

			$file_name = basename($_SERVER['SCRIPT_FILENAME']);
			if(function_exists('htmlspecialchars_uni'))
			{
				$file_name = htmlspecialchars_uni($file_name);
			}
			else
			{
				$file_name = htmlspecialchars($file_name);
			}

			echo <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head profile="http://gmpg.org/xfn/11">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>{$bbname} - Internal Error</title>
	<style type="text/css">
		body { background: #efefef; color: #000; font-family: Tahoma,Verdana,Arial,Sans-Serif; font-size: 12px; text-align: center; line-height: 1.4; }
		a:link { color: #026CB1; text-decoration: none;	}
		a:visited {	color: #026CB1;	text-decoration: none; }
		a:hover, a:active {	color: #000; text-decoration: underline; }
		#container { width: 600px; padding: 20px; background: #fff;	border: 1px solid #e4e4e4; margin: 100px auto; text-align: left; -moz-border-radius: 6px; -webkit-border-radius: 6px; border-radius: 6px; }
		h1 { margin: 0; background: url({$file_name}?action=mybb_logo) no-repeat;	height: 82px; width: 248px; }
		#content { border: 1px solid #026CB1; background: #fff; -moz-border-radius: 3px; -webkit-border-radius: 3px; border-radius: 3px; }
		h2 { font-size: 12px; padding: 4px; background: #026CB1; color: #fff; margin: 0; }
		.invisible { display: none; }
		#error { padding: 6px; }
		#footer { font-size: 12px; border-top: 1px dotted #DDDDDD; padding-top: 10px; }
		dt { font-weight: bold; }
	</style>
</head>
<body>
	<div id="container">
		<div id="logo">
			<h1><a href="https://mybb.com/" title="MyBB"><span class="invisible">MyBB</span></a></h1>
		</div>

		<div id="content">
			<h2>{$title}</h2>

			<div id="error">
				{$error_message}
				<p id="footer">{$contact}</p>
			</div>
		</div>
	</div>
</body>
</html>
EOF;
		}
		else
		{
			echo <<<EOF
	<style type="text/css">
		#mybb_error_content { border: 1px solid #026CB1; background: #fff; -moz-border-radius: 3px; -webkit-border-radius: 3px; border-radius: 3px; }
		#mybb_error_content a:link { color: #026CB1; text-decoration: none;	}
		#mybb_error_content a:visited {	color: #026CB1;	text-decoration: none; }
		#mybb_error_content a:hover, a:active {	color: #000; text-decoration: underline; }
		#mybb_error_content h2 { font-size: 12px; padding: 4px; background: #026CB1; color: #fff; margin: 0; border-bottom: none; }
		#mybb_error_error { padding: 6px; }
		#mybb_error_footer { font-size: 12px; border-top: 1px dotted #DDDDDD; padding-top: 10px; }
		#mybb_error_content dt { font-weight: bold; }
	</style>
	<div id="mybb_error_content">
		<h2>{$title}</h2>
		<div id="mybb_error_error">
		{$error_message}
			<p id="mybb_error_footer">{$contact}</p>
		</div>
	</div>
EOF;
		}

		exit(1);
	}

	/**
	 * Generates a backtrace if the server supports it.
	 *
	 * @return string The generated backtrace
	 */
	function generate_backtrace($html=true, $strip=1)
	{
		$backtrace = '';
		if(function_exists("debug_backtrace"))
		{
			$trace = debug_backtrace(1<<1 /* DEBUG_BACKTRACE_IGNORE_ARGS */);

			if($html)
			{
				$backtrace = "<table style=\"width: 100%; margin: 10px 0; border: 1px solid #aaa; border-collapse: collapse; border-bottom: 0;\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">\n";
				$backtrace .= "<thead><tr>\n";
				$backtrace .= "<th style=\"border-bottom: 1px solid #aaa; background: #ccc; padding: 4px; text-align: left; font-size: 11px;\">File</th>\n";
				$backtrace .= "<th style=\"border-bottom: 1px solid #aaa; background: #ccc; padding: 4px; text-align: left; font-size: 11px;\">Line</th>\n";
				$backtrace .= "<th style=\"border-bottom: 1px solid #aaa; background: #ccc; padding: 4px; text-align: left; font-size: 11px;\">Function</th>\n";
				$backtrace .= "</tr></thead>\n<tbody>\n";
			}

			// Strip off calls from trace
			$trace = array_slice($trace, $strip);

			$i = 0;

			foreach($trace as $call)
			{
				if(empty($call['file'])) $call['file'] = "[PHP]";
				if(empty($call['line'])) $call['line'] = " ";
				if(!empty($call['class'])) $call['function'] = $call['class'].$call['type'].$call['function'];
				$call['file'] = str_replace(MYBB_ROOT, "/", $call['file']);

				if($html)
				{
					$backtrace .= "<tr>\n";
					$backtrace .= "<td style=\"font-size: 11px; padding: 4px; border-bottom: 1px solid #ccc;\">{$call['file']}</td>\n";
					$backtrace .= "<td style=\"font-size: 11px; padding: 4px; border-bottom: 1px solid #ccc;\">{$call['line']}</td>\n";
					$backtrace .= "<td style=\"font-size: 11px; padding: 4px; border-bottom: 1px solid #ccc;\">{$call['function']}</td>\n";
					$backtrace .= "</tr>\n";
				}
				else
				{
					$backtrace .= "#{$i}  {$call['function']}() called at [{$call['file']}:{$call['line']}]\n";
				}

				$i++;
			}

			if($html)
			{
				$backtrace .= "</tbody></table>\n";
			}
		}
		return $backtrace;
	}
}
