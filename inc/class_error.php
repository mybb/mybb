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

		$this->has_errors = true;

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
				$warning = "<strong>{$this->error_types[$type]}</strong> [$type] $message - Line: $line - File: $file PHP ".PHP_VERSION." (".PHP_OS.")<br />\n";
				echo "<div class=\"php_warning\">{$warning}".$this->generate_backtrace()."</div>";
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
		global $lang;

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

		return \MyBB\template('misc/php_warnings.twig', [
			'warnings' => $this->warnings
		]);
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

		$generic_message = 'The software behind this site has experienced a problem and cannot continue. Please try again later.';
		$details = '';

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

			$details .= <<<HTML
			<p>If this problem persists, please <a href="{$mybb->settings['contactlink']}">contact the site owner</a>.</p>
			HTML;
		}

		if($type == MYBB_SQL)
		{
			$title = "MyBB SQL Error";
			if($show_details)
			{
				$message['query'] = htmlspecialchars_uni($message['query']);
				$message['error'] = htmlspecialchars_uni($message['error']);

				$details = "<h3>Technical Details</h3>";
				$details .= "<dl>\n";
				$details .= "<dt>SQL Error:</dt>\n<dd>{$message['error_no']} - {$message['error']}</dd>\n";
				if($message['query'] != "")
				{
					$details .= "<dt>Query:</dt>\n<dd>{$message['query']}</dd>\n";
				}
				$details .= "</dl>\n";
			}
		}
		else
		{
			$title = "MyBB Internal Error";
			if($show_details)
			{
				$details = "<h3>Technical Details</h3>";
				$details .= "<dl>\n";
				$details .= "<dt>Error Type</dt>\n<dd>{$this->error_types[$type]} ($type)</dd>\n";
				$details .= "<dt>Error Message</dt>\n<dd>{$message}</dd>\n";
				if(!empty($file))
				{
					$details .= "<dt>Location</dt><dd>File: {$file}<br />Line: {$line}</dd>\n";
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

						$details .= "<dt>Code</dt><dd><pre>{$code}</pre></dd>\n";
					}
				}
				$backtrace = $this->generate_backtrace();
				if($backtrace && !in_array($type, $this->mybb_error_types))
				{
					$details .= "<dt>Backtrace</dt><dd>{$backtrace}</dd>\n";
				}
				$details .= "</dl>\n";
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

		$support_extra = '';
		if(isset($lang->settings['support_link'], $lang->settings['support_name']))
		{
			$support_link = htmlspecialchars_uni($lang->settings['support_link']);
			$support_name = htmlspecialchars_uni($lang->settings['support_name']);

			$support_extra = <<<HTML
			or <a href="{$support_link}" target="_blank" rel="noopener">{$support_name}</a>
			HTML;
		}

		$html = <<<HTML
		<main>
			<section>
				<h2>{$title}</h2>
				<p>{$generic_message}</p>
				{$details}
			</section>
		</main>
		<section class="footnote">
			<p>If you own this board, visit <a href="https://mybb.com/support" target="_blank" rel="noopener">mybb.com/support</a> {$support_extra} for documentation and technical support.</p>
		</section>
		HTML;

		if(!headers_sent() && !defined("IN_INSTALL") && !defined("IN_UPGRADE"))
		{
			// full-page error message

			@header('HTTP/1.1 503 Service Temporarily Unavailable');
			@header('Status: 503 Service Temporarily Unavailable');
			@header('Retry-After: 1800');
			@header("Content-type: text/html; charset={$charset}");

			try
			{
				// attempt to render using Twig

				require_once MYBB_ROOT . 'inc/src/Maintenance/functions_http.php';

				\MyBB\Maintenance\httpOutputError(
					$title,
					$generic_message,
					[
						'details' => $details,
						'support_extra' => $support_extra,
					],
				);
			}
			catch(Throwable)
			{
				// render with static version of the `maintenance/error.twig` template

				$logo = file_get_contents(MYBB_ROOT . 'inc/views/logo.svg');

				echo <<<HTML
				<html lang="en">
				<head>
					<meta charset="UTF-8">
					<meta http-equiv="X-UA-Compatible" content="ie=edge">
					<meta name="robots" content="noindex">

					<title>{$title}</title>

					<link rel="stylesheet" href="{$mybb->asset_url}/jscripts/maintenance/main.css" />
				</head>
				<body class="maintenance maintenance--minimal maintenance--error">
					<div class="container">
						<div class="page">
							{$html}
						</div>

						<footer>
							<div class="powered-by powered-by--logo">
								<a href="https://mybb.com" title="Forum software by MyBB" target="_blank" rel="noopener">
									{$logo}
								</a>
							</div>
						</footer>
					</div>
				</body>
				</html>
				HTML;
			}
		}
		else
		{
			// embedded error message

			echo <<<HTML
			<link rel="stylesheet" href="{$mybb->asset_url}/jscripts/maintenance/error.css" />
			<div class="mybb_error">
				{$html}
			</div>
			HTML;
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
