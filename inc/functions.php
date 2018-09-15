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
 * Outputs a page directly to the browser, parsing anything which needs to be parsed.
 *
 * @param string $contents The contents of the page.
 */
function output_page($contents)
{
	global $db, $lang, $theme, $templates, $plugins, $mybb;
	global $debug, $templatecache, $templatelist, $maintimer, $globaltime, $parsetime;

	$contents = $plugins->run_hooks("pre_parse_page", $contents);
	$contents = parse_page($contents);
	$totaltime = format_time_duration($maintimer->stop());
	$contents = $plugins->run_hooks("pre_output_page", $contents);

	if($mybb->usergroup['cancp'] == 1 || $mybb->dev_mode == 1)
	{
		if($mybb->settings['extraadmininfo'] != 0)
		{
			$phptime = $maintimer->totaltime - $db->query_time;
			$query_time = $db->query_time;

			if($maintimer->totaltime > 0)
			{
				$percentphp = number_format((($phptime/$maintimer->totaltime) * 100), 2);
				$percentsql = number_format((($query_time/$maintimer->totaltime) * 100), 2);
			}
			else
			{
				// if we've got a super fast script...  all we can do is assume something
				$percentphp = 0;
				$percentsql = 0;
			}

			$serverload = get_server_load();

			if(my_strpos(getenv("REQUEST_URI"), "?"))
			{
				$debuglink = htmlspecialchars_uni(getenv("REQUEST_URI")) . "&amp;debug=1";
			}
			else
			{
				$debuglink = htmlspecialchars_uni(getenv("REQUEST_URI")) . "?debug=1";
			}

			$memory_usage = get_memory_usage();

			if($memory_usage)
			{
				$memory_usage = $lang->sprintf($lang->debug_memory_usage, get_friendly_size($memory_usage));
			}
			else
			{
				$memory_usage = '';
			}
			// MySQLi is still MySQL, so present it that way to the user
			$database_server = $db->short_title;

			if($database_server == 'MySQLi')
			{
				$database_server = 'MySQL';
			}
			$generated_in = $lang->sprintf($lang->debug_generated_in, $totaltime);
			$debug_weight = $lang->sprintf($lang->debug_weight, $percentphp, $percentsql, $database_server);
			$sql_queries = $lang->sprintf($lang->debug_sql_queries, $db->query_count);
			$server_load = $lang->sprintf($lang->debug_server_load, $serverload);

			eval("\$debugstuff = \"".$templates->get("debug_summary")."\";");
			$contents = str_replace("<debugstuff>", $debugstuff, $contents);
		}

		if($mybb->debug_mode == true)
		{
			debug_page();
		}
	}

	$contents = str_replace("<debugstuff>", "", $contents);

	if($mybb->settings['gzipoutput'] == 1)
	{
		$contents = gzip_encode($contents, $mybb->settings['gziplevel']);
	}

	@header("Content-type: text/html; charset={$lang->settings['charset']}");

	echo $contents;

	$plugins->run_hooks("post_output_page");
}

/**
 * Adds a function or class to the list of code to run on shutdown.
 *
 * @param string|array $name The name of the function.
 * @param mixed $arguments Either an array of arguments for the function or one argument
 * @return boolean True if function exists, otherwise false.
 */
function add_shutdown($name, $arguments=array())
{
	global $shutdown_functions;

	if(!is_array($shutdown_functions))
	{
		$shutdown_functions = array();
	}

	if(!is_array($arguments))
	{
		$arguments = array($arguments);
	}

	if(is_array($name) && method_exists($name[0], $name[1]))
	{
		$shutdown_functions[] = array('function' => $name, 'arguments' => $arguments);
		return true;
	}
	else if(!is_array($name) && function_exists($name))
	{
		$shutdown_functions[] = array('function' => $name, 'arguments' => $arguments);
		return true;
	}

	return false;
}

/**
 * Runs the shutdown items after the page has been sent to the browser.
 *
 */
function run_shutdown()
{
	global $config, $db, $cache, $plugins, $error_handler, $shutdown_functions, $shutdown_queries, $done_shutdown, $mybb;

	if($done_shutdown == true || !$config || (isset($error_handler) && $error_handler->has_errors))
	{
		return;
	}

	if(empty($shutdown_queries) && empty($shutdown_functions))
	{
		// Nothing to do
		return;
	}

	// Missing the core? Build
	if(!is_object($mybb))
	{
		require_once MYBB_ROOT."inc/class_core.php";
		$mybb = new MyBB;

		// Load the settings
		require MYBB_ROOT."inc/settings.php";
		$mybb->settings = &$settings;
	}

	// If our DB has been deconstructed already (bad PHP 5.2.0), reconstruct
	if(!is_object($db))
	{
		if(!isset($config) || empty($config['database']['type']))
		{
			require MYBB_ROOT."inc/config.php";
		}

		if(isset($config))
		{
			// Load DB interface
			require_once MYBB_ROOT."inc/db_base.php";

			require_once MYBB_ROOT."inc/db_".$config['database']['type'].".php";
			switch($config['database']['type'])
			{
				case "sqlite":
					$db = new DB_SQLite;
					break;
				case "pgsql":
					$db = new DB_PgSQL;
					break;
				case "mysqli":
					$db = new DB_MySQLi;
					break;
				default:
					$db = new DB_MySQL;
			}

			$db->connect($config['database']);
			if(!defined("TABLE_PREFIX"))
			{
				define("TABLE_PREFIX", $config['database']['table_prefix']);
			}
			$db->set_table_prefix(TABLE_PREFIX);
		}
	}

	// Cache object deconstructed? reconstruct
	if(!is_object($cache))
	{
		require_once MYBB_ROOT."inc/class_datacache.php";
		$cache = new datacache;
		$cache->cache();
	}

	// And finally.. plugins
	if(!is_object($plugins) && !defined("NO_PLUGINS") && !($mybb->settings['no_plugins'] == 1))
	{
		require_once MYBB_ROOT."inc/class_plugins.php";
		$plugins = new pluginSystem;
		$plugins->load();
	}

	// We have some shutdown queries needing to be run
	if(is_array($shutdown_queries))
	{
		// Loop through and run them all
		foreach($shutdown_queries as $query)
		{
			$db->query($query);
		}
	}

	// Run any shutdown functions if we have them
	if(is_array($shutdown_functions))
	{
		foreach($shutdown_functions as $function)
		{
			call_user_func_array($function['function'], $function['arguments']);
		}
	}

	$done_shutdown = true;
}

/**
 * Sends a specified amount of messages from the mail queue
 *
 * @param int $count The number of messages to send (Defaults to 10)
 */
function send_mail_queue($count=10)
{
	global $db, $cache, $plugins;

	$plugins->run_hooks("send_mail_queue_start");

	// Check to see if the mail queue has messages needing to be sent
	$mailcache = $cache->read("mailqueue");
	if($mailcache['queue_size'] > 0 && ($mailcache['locked'] == 0 || $mailcache['locked'] < TIME_NOW-300))
	{
		// Lock the queue so no other messages can be sent whilst these are (for popular boards)
		$cache->update_mailqueue(0, TIME_NOW);

		// Fetch emails for this page view - and send them
		$query = $db->simple_select("mailqueue", "*", "", array("order_by" => "mid", "order_dir" => "asc", "limit_start" => 0, "limit" => $count));

		while($email = $db->fetch_array($query))
		{
			// Delete the message from the queue
			$db->delete_query("mailqueue", "mid='{$email['mid']}'");

			if($db->affected_rows() == 1)
			{
				my_mail($email['mailto'], $email['subject'], $email['message'], $email['mailfrom'], "", $email['headers'], true);
			}
		}
		// Update the mailqueue cache and remove the lock
		$cache->update_mailqueue(TIME_NOW, 0);
	}

	$plugins->run_hooks("send_mail_queue_end");
}

/**
 * Parses the contents of a page before outputting it.
 *
 * @param string $contents The contents of the page.
 * @return string The parsed page.
 */
function parse_page($contents)
{
	global $lang, $theme, $mybb, $htmldoctype, $archive_url, $error_handler;

	$contents = str_replace('<navigation>', build_breadcrumb(), $contents);
	$contents = str_replace('<archive_url>', $archive_url, $contents);

	if($htmldoctype)
	{
		$contents = $htmldoctype.$contents;
	}
	else
	{
		$contents = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n".$contents;
	}

	$contents = str_replace("<html", "<html xmlns=\"http://www.w3.org/1999/xhtml\"", $contents);

	if($lang->settings['rtl'] == 1)
	{
		$contents = str_replace("<html", "<html dir=\"rtl\"", $contents);
	}

	if($lang->settings['htmllang'])
	{
		$contents = str_replace("<html", "<html xml:lang=\"".$lang->settings['htmllang']."\" lang=\"".$lang->settings['htmllang']."\"", $contents);
	}

	if($error_handler->warnings)
	{
		$contents = str_replace("<body>", "<body>\n".$error_handler->show_warnings(), $contents);
	}

	return $contents;
}

/**
 * Turn a unix timestamp in to a "friendly" date/time format for the user.
 *
 * @param string $format A date format (either relative, normal or PHP's date() structure).
 * @param int $stamp The unix timestamp the date should be generated for.
 * @param int|string $offset The offset in hours that should be applied to times. (timezones) Or an empty string to determine that automatically
 * @param int $ty Whether or not to use today/yesterday formatting.
 * @param boolean $adodb Whether or not to use the adodb time class for < 1970 or > 2038 times
 * @return string The formatted timestamp.
 */
function my_date($format, $stamp=0, $offset="", $ty=1, $adodb=false)
{
	global $mybb, $lang, $mybbadmin, $plugins;

	// If the stamp isn't set, use TIME_NOW
	if(empty($stamp))
	{
		$stamp = TIME_NOW;
	}

	if(!$offset && $offset != '0')
	{
		if(isset($mybb->user['uid']) && $mybb->user['uid'] != 0 && array_key_exists("timezone", $mybb->user))
		{
			$offset = (float)$mybb->user['timezone'];
			$dstcorrection = $mybb->user['dst'];
		}
		elseif(defined("IN_ADMINCP"))
		{
			$offset = (float)$mybbadmin['timezone'];
			$dstcorrection = $mybbadmin['dst'];
		}
		else
		{
			$offset = (float)$mybb->settings['timezoneoffset'];
			$dstcorrection = $mybb->settings['dstcorrection'];
		}

		// If DST correction is enabled, add an additional hour to the timezone.
		if($dstcorrection == 1)
		{
			++$offset;
			if(my_substr($offset, 0, 1) != "-")
			{
				$offset = "+".$offset;
			}
		}
	}

	if($offset == "-")
	{
		$offset = 0;
	}

	// Using ADOdb?
	if($adodb == true && !function_exists('adodb_date'))
	{
		$adodb = false;
	}

	$todaysdate = $yesterdaysdate = '';
	if($ty && ($format == $mybb->settings['dateformat'] || $format == 'relative' || $format == 'normal'))
	{
		$_stamp = TIME_NOW;
		if($adodb == true)
		{
			$date = adodb_date($mybb->settings['dateformat'], $stamp + ($offset * 3600));
			$todaysdate = adodb_date($mybb->settings['dateformat'], $_stamp + ($offset * 3600));
			$yesterdaysdate = adodb_date($mybb->settings['dateformat'], ($_stamp - 86400) + ($offset * 3600));
		}
		else
		{
			$date = gmdate($mybb->settings['dateformat'], $stamp + ($offset * 3600));
			$todaysdate = gmdate($mybb->settings['dateformat'], $_stamp + ($offset * 3600));
			$yesterdaysdate = gmdate($mybb->settings['dateformat'], ($_stamp - 86400) + ($offset * 3600));
		}
	}

	if($format == 'relative')
	{
		// Relative formats both date and time
		$real_date = $real_time = '';
		if($adodb == true)
		{
			$real_date = adodb_date($mybb->settings['dateformat'], $stamp + ($offset * 3600));
			$real_time = $mybb->settings['datetimesep'];
			$real_time .= adodb_date($mybb->settings['timeformat'], $stamp + ($offset * 3600));
		}
		else
		{
			$real_date = gmdate($mybb->settings['dateformat'], $stamp + ($offset * 3600));
			$real_time = $mybb->settings['datetimesep'];
			$real_time .= gmdate($mybb->settings['timeformat'], $stamp + ($offset * 3600));
		}

		if($ty != 2 && abs(TIME_NOW - $stamp) < 3600)
		{
			$diff = TIME_NOW - $stamp;
			$relative = array('prefix' => '', 'minute' => 0, 'plural' => $lang->rel_minutes_plural, 'suffix' => $lang->rel_ago);

			if($diff < 0)
			{
				$diff = abs($diff);
				$relative['suffix'] = '';
				$relative['prefix'] = $lang->rel_in;
			}

			$relative['minute'] = floor($diff / 60);

			if($relative['minute'] <= 1)
			{
				$relative['minute'] = 1;
				$relative['plural'] = $lang->rel_minutes_single;
			}

			if($diff <= 60)
			{
				// Less than a minute
				$relative['prefix'] = $lang->rel_less_than;
			}

			$date = $lang->sprintf($lang->rel_time, $relative['prefix'], $relative['minute'], $relative['plural'], $relative['suffix'], $real_date, $real_time);
		}
		elseif($ty != 2 && abs(TIME_NOW - $stamp) < 43200)
		{
			$diff = TIME_NOW - $stamp;
			$relative = array('prefix' => '', 'hour' => 0, 'plural' => $lang->rel_hours_plural, 'suffix' => $lang->rel_ago);

			if($diff < 0)
			{
				$diff = abs($diff);
				$relative['suffix'] = '';
				$relative['prefix'] = $lang->rel_in;
			}

			$relative['hour'] = floor($diff / 3600);

			if($relative['hour'] <= 1)
			{
				$relative['hour'] = 1;
				$relative['plural'] = $lang->rel_hours_single;
			}

			$date = $lang->sprintf($lang->rel_time, $relative['prefix'], $relative['hour'], $relative['plural'], $relative['suffix'], $real_date, $real_time);
		}
		else
		{
			if($ty)
			{
				if($todaysdate == $date)
				{
					$date = $lang->sprintf($lang->today_rel, $real_date);
				}
				else if($yesterdaysdate == $date)
				{
					$date = $lang->sprintf($lang->yesterday_rel, $real_date);
				}
			}

			$date .= $mybb->settings['datetimesep'];
			if($adodb == true)
			{
				$date .= adodb_date($mybb->settings['timeformat'], $stamp + ($offset * 3600));
			}
			else
			{
				$date .= gmdate($mybb->settings['timeformat'], $stamp + ($offset * 3600));
			}
		}
	}
	elseif($format == 'normal')
	{
		// Normal format both date and time
		if($ty != 2)
		{
			if($todaysdate == $date)
			{
				$date = $lang->today;
			}
			else if($yesterdaysdate == $date)
			{
				$date = $lang->yesterday;
			}
		}

		$date .= $mybb->settings['datetimesep'];
		if($adodb == true)
		{
			$date .= adodb_date($mybb->settings['timeformat'], $stamp + ($offset * 3600));
		}
		else
		{
			$date .= gmdate($mybb->settings['timeformat'], $stamp + ($offset * 3600));
		}
	}
	else
	{
		if($ty && $format == $mybb->settings['dateformat'])
		{
			if($todaysdate == $date)
			{
				$date = $lang->today;
			}
			else if($yesterdaysdate == $date)
			{
				$date = $lang->yesterday;
			}
		}
		else
		{
			if($adodb == true)
			{
				$date = adodb_date($format, $stamp + ($offset * 3600));
			}
			else
			{
				$date = gmdate($format, $stamp + ($offset * 3600));
			}
		}
	}

	if(is_object($plugins))
	{
		$date = $plugins->run_hooks("my_date", $date);
	}

	return $date;
}

/**
 * Sends an email using PHP's mail function, formatting it appropriately.
 *
 * @param string $to Address the email should be addressed to.
 * @param string $subject The subject of the email being sent.
 * @param string $message The message being sent.
 * @param string $from The from address of the email, if blank, the board name will be used.
 * @param string $charset The chracter set being used to send this email.
 * @param string $headers
 * @param boolean $keep_alive Do we wish to keep the connection to the mail server alive to send more than one message (SMTP only)
 * @param string $format The format of the email to be sent (text or html). text is default
 * @param string $message_text The text message of the email if being sent in html format, for email clients that don't support html
 * @param string $return_email The email address to return to. Defaults to admin return email address.
 * @return bool
 */
function my_mail($to, $subject, $message, $from="", $charset="", $headers="", $keep_alive=false, $format="text", $message_text="", $return_email="")
{
	global $mybb;
	static $mail;

	// Does our object not exist? Create it
	if(!is_object($mail))
	{
		require_once MYBB_ROOT."inc/class_mailhandler.php";

		if($mybb->settings['mail_handler'] == 'smtp')
		{
			require_once MYBB_ROOT."inc/mailhandlers/smtp.php";
			$mail = new SmtpMail();
		}
		else
		{
			require_once MYBB_ROOT."inc/mailhandlers/php.php";
			$mail = new PhpMail();
		}
	}

	// Using SMTP based mail
	if($mybb->settings['mail_handler'] == 'smtp')
	{
		if($keep_alive == true)
		{
			$mail->keep_alive = true;
		}
	}

	// Using PHP based mail()
	else
	{
		if($mybb->settings['mail_parameters'] != '')
		{
			$mail->additional_parameters = $mybb->settings['mail_parameters'];
		}
	}

	// Build and send
	$mail->build_message($to, $subject, $message, $from, $charset, $headers, $format, $message_text, $return_email);
	return $mail->send();
}

/**
 * Generates a unique code for POST requests to prevent XSS/CSRF attacks
 *
 * @return string The generated code
 */
function generate_post_check()
{
	global $mybb, $session;
	if($mybb->user['uid'])
	{
		return md5($mybb->user['loginkey'].$mybb->user['salt'].$mybb->user['regdate']);
	}
	// Guests get a special string
	else
	{
		return md5($session->useragent.$mybb->config['database']['username'].$mybb->settings['internal']['encryption_key']);
	}
}

/**
 * Verifies a POST check code is valid, if not shows an error (silently returns false on silent parameter)
 *
 * @param string $code The incoming POST check code
 * @param boolean $silent Silent mode or not (silent mode will not show the error to the user but returns false)
 * @return bool
 */
function verify_post_check($code, $silent=false)
{
	global $lang;
	if(generate_post_check() !== $code)
	{
		if($silent == true)
		{
			return false;
		}
		else
		{
			if(defined("IN_ADMINCP"))
			{
				return false;
			}
			else
			{
				error($lang->invalid_post_code);
			}
		}
	}
	else
	{
		return true;
	}
}

/**
 * Return a parent list for the specified forum.
 *
 * @param int $fid The forum id to get the parent list for.
 * @return string The comma-separated parent list.
 */
function get_parent_list($fid)
{
	global $forum_cache;
	static $forumarraycache;

	if($forumarraycache[$fid])
	{
		return $forumarraycache[$fid]['parentlist'];
	}
	elseif($forum_cache[$fid])
	{
		return $forum_cache[$fid]['parentlist'];
	}
	else
	{
		cache_forums();
		return $forum_cache[$fid]['parentlist'];
	}
}

/**
 * Build a parent list of a specific forum, suitable for querying
 *
 * @param int $fid The forum ID
 * @param string $column The column name to add to the query
 * @param string $joiner The joiner for each forum for querying (OR | AND | etc)
 * @param string $parentlist The parent list of the forum - if you have it
 * @return string The query string generated
 */
function build_parent_list($fid, $column="fid", $joiner="OR", $parentlist="")
{
	if(!$parentlist)
	{
		$parentlist = get_parent_list($fid);
	}

	$parentsexploded = explode(",", $parentlist);
	$builtlist = "(";
	$sep = '';

	foreach($parentsexploded as $key => $val)
	{
		$builtlist .= "$sep$column='$val'";
		$sep = " $joiner ";
	}

	$builtlist .= ")";

	return $builtlist;
}

/**
 * Load the forum cache in to memory
 *
 * @param boolean $force True to force a reload of the cache
 * @return array The forum cache
 */
function cache_forums($force=false)
{
	global $forum_cache, $cache;

	if($force == true)
	{
		$forum_cache = $cache->read("forums", 1);
		return $forum_cache;
	}

	if(!$forum_cache)
	{
		$forum_cache = $cache->read("forums");
		if(!$forum_cache)
		{
			$cache->update_forums();
			$forum_cache = $cache->read("forums", 1);
		}
	}
	return $forum_cache;
}

/**
 * Generate an array of all child and descendant forums for a specific forum.
 *
 * @param int $fid The forum ID
 * @return Array of descendants
 */
function get_child_list($fid)
{
	static $forums_by_parent;

	$forums = array();
	if(!is_array($forums_by_parent))
	{
		$forum_cache = cache_forums();
		foreach($forum_cache as $forum)
		{
			if($forum['active'] != 0)
			{
				$forums_by_parent[$forum['pid']][$forum['fid']] = $forum;
			}
		}
	}
	if(!is_array($forums_by_parent[$fid]))
	{
		return $forums;
	}

	foreach($forums_by_parent[$fid] as $forum)
	{
		$forums[] = $forum['fid'];
		$children = get_child_list($forum['fid']);
		if(is_array($children))
		{
			$forums = array_merge($forums, $children);
		}
	}
	return $forums;
}

/**
 * Produce a friendly error message page
 *
 * @param string $error The error message to be shown
 * @param string $title The title of the message shown in the title of the page and the error table
 */
function error($error="", $title="")
{
	global $header, $footer, $theme, $headerinclude, $db, $templates, $lang, $mybb, $plugins;

	$error = $plugins->run_hooks("error", $error);
	if(!$error)
	{
		$error = $lang->unknown_error;
	}

	// AJAX error message?
	if($mybb->get_input('ajax', MyBB::INPUT_INT))
	{
		// Send our headers.
		@header("Content-type: application/json; charset={$lang->settings['charset']}");
		echo json_encode(array("errors" => array($error)));
		exit;
	}

	if(!$title)
	{
		$title = $mybb->settings['bbname'];
	}

	$timenow = my_date('relative', TIME_NOW);
	reset_breadcrumb();
	add_breadcrumb($lang->error);

	eval("\$errorpage = \"".$templates->get("error")."\";");
	output_page($errorpage);

	exit;
}

/**
 * Produce an error message for displaying inline on a page
 *
 * @param array $errors Array of errors to be shown
 * @param string $title The title of the error message
 * @param array $json_data JSON data to be encoded (we may want to send more data; e.g. newreply.php uses this for CAPTCHA)
 * @return string The inline error HTML
 */
function inline_error($errors, $title="", $json_data=array())
{
	global $theme, $mybb, $db, $lang, $templates;

	if(!$title)
	{
		$title = $lang->please_correct_errors;
	}

	if(!is_array($errors))
	{
		$errors = array($errors);
	}

	// AJAX error message?
	if($mybb->get_input('ajax', MyBB::INPUT_INT))
	{
		// Send our headers.
		@header("Content-type: application/json; charset={$lang->settings['charset']}");

		if(empty($json_data))
		{
			echo json_encode(array("errors" => $errors));
		}
		else
		{
			echo json_encode(array_merge(array("errors" => $errors), $json_data));
		}
		exit;
	}

	$errorlist = '';

	foreach($errors as $error)
	{
		eval("\$errorlist .= \"".$templates->get("error_inline_item")."\";");
	}

	eval("\$errors = \"".$templates->get("error_inline")."\";");

	return $errors;
}

/**
 * Presents the user with a "no permission" page
 */
function error_no_permission()
{
	global $mybb, $theme, $templates, $db, $lang, $plugins, $session;

	$time = TIME_NOW;
	$plugins->run_hooks("no_permission");

	$noperm_array = array (
		"nopermission" => '1',
		"location1" => 0,
		"location2" => 0
	);

	$db->update_query("sessions", $noperm_array, "sid='{$session->sid}'");

	if($mybb->get_input('ajax', MyBB::INPUT_INT))
	{
		// Send our headers.
		header("Content-type: application/json; charset={$lang->settings['charset']}");
		echo json_encode(array("errors" => array($lang->error_nopermission_user_ajax)));
		exit;
	}

	if($mybb->user['uid'])
	{
		$lang->error_nopermission_user_username = $lang->sprintf($lang->error_nopermission_user_username, htmlspecialchars_uni($mybb->user['username']));
		eval("\$errorpage = \"".$templates->get("error_nopermission_loggedin")."\";");
	}
	else
	{
		// Redirect to where the user came from
		$redirect_url = $_SERVER['PHP_SELF'];
		if($_SERVER['QUERY_STRING'])
		{
			$redirect_url .= '?'.$_SERVER['QUERY_STRING'];
		}

		$redirect_url = htmlspecialchars_uni($redirect_url);

		switch($mybb->settings['username_method'])
		{
			case 0:
				$lang_username = $lang->username;
				break;
			case 1:
				$lang_username = $lang->username1;
				break;
			case 2:
				$lang_username = $lang->username2;
				break;
			default:
				$lang_username = $lang->username;
				break;
		}
		eval("\$errorpage = \"".$templates->get("error_nopermission")."\";");
	}

	error($errorpage);
}

/**
 * Redirect the user to a given URL with a given message
 *
 * @param string $url The URL to redirect the user to
 * @param string $message The redirection message to be shown
 * @param string $title The title of the redirection page
 * @param boolean $force_redirect Force the redirect page regardless of settings
 */
function redirect($url, $message="", $title="", $force_redirect=false)
{
	global $header, $footer, $mybb, $theme, $headerinclude, $templates, $lang, $plugins;

	$redirect_args = array('url' => &$url, 'message' => &$message, 'title' => &$title);

	$plugins->run_hooks("redirect", $redirect_args);

	if($mybb->get_input('ajax', MyBB::INPUT_INT))
	{
		// Send our headers.
		//@header("Content-type: text/html; charset={$lang->settings['charset']}");
		$data = "<script type=\"text/javascript\">\n";
		if($message != "")
		{
			$data .=  'alert("'.addslashes($message).'");';
		}
		$url = str_replace("#", "&#", $url);
		$url = htmlspecialchars_decode($url);
		$url = str_replace(array("\n","\r",";"), "", $url);
		$data .=  'window.location = "'.addslashes($url).'";'."\n";
		$data .= "</script>\n";
		//exit;

		@header("Content-type: application/json; charset={$lang->settings['charset']}");
		echo json_encode(array("data" => $data));
		exit;
	}

	if(!$message)
	{
		$message = $lang->redirect;
	}

	$time = TIME_NOW;
	$timenow = my_date('relative', $time);

	if(!$title)
	{
		$title = $mybb->settings['bbname'];
	}

	// Show redirects only if both ACP and UCP settings are enabled, or ACP is enabled, and user is a guest, or they are forced.
	if($force_redirect == true || ($mybb->settings['redirects'] == 1 && ($mybb->user['showredirect'] == 1 || !$mybb->user['uid'])))
	{
		$url = str_replace("&amp;", "&", $url);
		$url = htmlspecialchars_uni($url);

		eval("\$redirectpage = \"".$templates->get("redirect")."\";");
		output_page($redirectpage);
	}
	else
	{
		$url = htmlspecialchars_decode($url);
		$url = str_replace(array("\n","\r",";"), "", $url);

		run_shutdown();

		if(!my_validate_url($url, true, true))
		{
			header("Location: {$mybb->settings['bburl']}/{$url}");
		}
		else
		{
			header("Location: {$url}");
		}
	}

	exit;
}

/**
 * Generate a listing of page - pagination
 *
 * @param int $count The number of items
 * @param int $perpage The number of items to be shown per page
 * @param int $page The current page number
 * @param string $url The URL to have page numbers tacked on to (If {page} is specified, the value will be replaced with the page #)
 * @param boolean $breadcrumb Whether or not the multipage is being shown in the navigation breadcrumb
 * @return string The generated pagination
 */
function multipage($count, $perpage, $page, $url, $breadcrumb=false)
{
	global $theme, $templates, $lang, $mybb;

	if($count <= $perpage)
	{
		return '';
	}

	$page = (int)$page;

	$url = str_replace("&amp;", "&", $url);
	$url = htmlspecialchars_uni($url);

	$pages = ceil($count / $perpage);

	$prevpage = '';
	if($page > 1)
	{
		$prev = $page-1;
		$page_url = fetch_page_url($url, $prev);
		eval("\$prevpage = \"".$templates->get("multipage_prevpage")."\";");
	}

	// Maximum number of "page bits" to show
	if(!$mybb->settings['maxmultipagelinks'])
	{
		$mybb->settings['maxmultipagelinks'] = 5;
	}

	$from = $page-floor($mybb->settings['maxmultipagelinks']/2);
	$to = $page+floor($mybb->settings['maxmultipagelinks']/2);

	if($from <= 0)
	{
		$from = 1;
		$to = $from+$mybb->settings['maxmultipagelinks']-1;
	}

	if($to > $pages)
	{
		$to = $pages;
		$from = $pages-$mybb->settings['maxmultipagelinks']+1;
		if($from <= 0)
		{
			$from = 1;
		}
	}

	if($to == 0)
	{
		$to = $pages;
	}

	$start = '';
	if($from > 1)
	{
		if($from-1 == 1)
		{
			$lang->multipage_link_start = '';
		}

		$page_url = fetch_page_url($url, 1);
		eval("\$start = \"".$templates->get("multipage_start")."\";");
	}

	$mppage = '';
	for($i = $from; $i <= $to; ++$i)
	{
		$page_url = fetch_page_url($url, $i);
		if($page == $i)
		{
			if($breadcrumb == true)
			{
				eval("\$mppage .= \"".$templates->get("multipage_page_link_current")."\";");
			}
			else
			{
				eval("\$mppage .= \"".$templates->get("multipage_page_current")."\";");
			}
		}
		else
		{
			eval("\$mppage .= \"".$templates->get("multipage_page")."\";");
		}
	}

	$end = '';
	if($to < $pages)
	{
		if($to+1 == $pages)
		{
			$lang->multipage_link_end = '';
		}

		$page_url = fetch_page_url($url, $pages);
		eval("\$end = \"".$templates->get("multipage_end")."\";");
	}

	$nextpage = '';
	if($page < $pages)
	{
		$next = $page+1;
		$page_url = fetch_page_url($url, $next);
		eval("\$nextpage = \"".$templates->get("multipage_nextpage")."\";");
	}

	$jumptopage = '';
	if($pages > ($mybb->settings['maxmultipagelinks']+1) && $mybb->settings['jumptopagemultipage'] == 1)
	{
		// When the second parameter is set to 1, fetch_page_url thinks it's the first page and removes it from the URL as it's unnecessary
		$jump_url = fetch_page_url($url, 1);
		eval("\$jumptopage = \"".$templates->get("multipage_jump_page")."\";");
	}

	$multipage_pages = $lang->sprintf($lang->multipage_pages, $pages);

	if($breadcrumb == true)
	{
		eval("\$multipage = \"".$templates->get("multipage_breadcrumb")."\";");
	}
	else
	{
		eval("\$multipage = \"".$templates->get("multipage")."\";");
	}

	return $multipage;
}

/**
 * Generate a page URL for use by the multipage function
 *
 * @param string $url The URL being passed
 * @param int $page The page number
 * @return string
 */
function fetch_page_url($url, $page)
{
	if($page <= 1)
	{
		$find = array(
			"-page-{page}",
			"&amp;page={page}",
			"{page}"
		);

		// Remove "Page 1" to the defacto URL
		$url = str_replace($find, array("", "", $page), $url);
		return $url;
	}
	else if(strpos($url, "{page}") === false)
	{
		// If no page identifier is specified we tack it on to the end of the URL
		if(strpos($url, "?") === false)
		{
			$url .= "?";
		}
		else
		{
			$url .= "&amp;";
		}

		$url .= "page=$page";
	}
	else
	{
		$url = str_replace("{page}", $page, $url);
	}

	return $url;
}

/**
 * Fetch the permissions for a specific user
 *
 * @param int $uid The user ID, if no user ID is provided then current user's ID will be considered.
 * @return array Array of user permissions for the specified user
 */
function user_permissions($uid=null)
{
	global $mybb, $cache, $groupscache, $user_cache;

	// If no user id is specified, assume it is the current user
	if($uid === null)
	{
		$uid = $mybb->user['uid'];
	}

	// Its a guest. Return the group permissions directly from cache
	if($uid == 0)
	{
		return $groupscache[1];
	}

	// User id does not match current user, fetch permissions
	if($uid != $mybb->user['uid'])
	{
		// We've already cached permissions for this user, return them.
		if(!empty($user_cache[$uid]['permissions']))
		{
			return $user_cache[$uid]['permissions'];
		}

		// This user was not already cached, fetch their user information.
		if(empty($user_cache[$uid]))
		{
			$user_cache[$uid] = get_user($uid);
		}

		// Collect group permissions.
		$gid = $user_cache[$uid]['usergroup'].",".$user_cache[$uid]['additionalgroups'];
		$groupperms = usergroup_permissions($gid);

		// Store group permissions in user cache.
		$user_cache[$uid]['permissions'] = $groupperms;
		return $groupperms;
	}
	// This user is the current user, return their permissions
	else
	{
		return $mybb->usergroup;
	}
}

/**
 * Fetch the usergroup permissions for a specific group or series of groups combined
 *
 * @param int|string $gid A list of groups (Can be a single integer, or a list of groups separated by a comma)
 * @return array Array of permissions generated for the groups, containing also a list of comma-separated checked groups under 'all_usergroups' index
 */
function usergroup_permissions($gid=0)
{
	global $cache, $groupscache, $grouppermignore, $groupzerogreater;

	if(!is_array($groupscache))
	{
		$groupscache = $cache->read("usergroups");
	}

	$groups = explode(",", $gid);

	if(count($groups) == 1)
	{
		$groupscache[$gid]['all_usergroups'] = $gid;
		return $groupscache[$gid];
	}

	$usergroup = array();
	$usergroup['all_usergroups'] = $gid;

	foreach($groups as $gid)
	{
		if(trim($gid) == "" || empty($groupscache[$gid]))
		{
			continue;
		}

		foreach($groupscache[$gid] as $perm => $access)
		{
			if(!in_array($perm, $grouppermignore))
			{
				if(isset($usergroup[$perm]))
				{
					$permbit = $usergroup[$perm];
				}
				else
				{
					$permbit = "";
				}

				// 0 represents unlimited for numerical group permissions (i.e. private message limit) so take that into account.
				if(in_array($perm, $groupzerogreater) && ($access == 0 || $permbit === 0))
				{
					$usergroup[$perm] = 0;
					continue;
				}

				if($access > $permbit || ($access == "yes" && $permbit == "no") || !$permbit) // Keep yes/no for compatibility?
				{
					$usergroup[$perm] = $access;
				}
			}
		}
	}

	return $usergroup;
}

/**
 * Fetch the display group properties for a specific display group
 *
 * @param int $gid The group ID to fetch the display properties for
 * @return array Array of display properties for the group
 */
function usergroup_displaygroup($gid)
{
	global $cache, $groupscache, $displaygroupfields;

	if(!is_array($groupscache))
	{
		$groupscache = $cache->read("usergroups");
	}

	$displaygroup = array();
	$group = $groupscache[$gid];

	foreach($displaygroupfields as $field)
	{
		$displaygroup[$field] = $group[$field];
	}

	return $displaygroup;
}

/**
 * Build the forum permissions for a specific forum, user or group
 *
 * @param int $fid The forum ID to build permissions for (0 builds for all forums)
 * @param int $uid The user to build the permissions for (0 will select the uid automatically)
 * @param int $gid The group of the user to build permissions for (0 will fetch it)
 * @return array Forum permissions for the specific forum or forums
 */
function forum_permissions($fid=0, $uid=0, $gid=0)
{
	global $db, $cache, $groupscache, $forum_cache, $fpermcache, $mybb, $cached_forum_permissions_permissions, $cached_forum_permissions;

	if($uid == 0)
	{
		$uid = $mybb->user['uid'];
	}

	if(!$gid || $gid == 0) // If no group, we need to fetch it
	{
		if($uid != 0 && $uid != $mybb->user['uid'])
		{
			$user = get_user($uid);

			$gid = $user['usergroup'].",".$user['additionalgroups'];
			$groupperms = usergroup_permissions($gid);
		}
		else
		{
			$gid = $mybb->user['usergroup'];

			if(isset($mybb->user['additionalgroups']))
			{
				$gid .= ",".$mybb->user['additionalgroups'];
			}

			$groupperms = $mybb->usergroup;
		}
	}

	if(!is_array($forum_cache))
	{
		$forum_cache = cache_forums();

		if(!$forum_cache)
		{
			return false;
		}
	}

	if(!is_array($fpermcache))
	{
		$fpermcache = $cache->read("forumpermissions");
	}

	if($fid) // Fetch the permissions for a single forum
	{
		if(empty($cached_forum_permissions_permissions[$gid][$fid]))
		{
			$cached_forum_permissions_permissions[$gid][$fid] = fetch_forum_permissions($fid, $gid, $groupperms);
		}
		return $cached_forum_permissions_permissions[$gid][$fid];
	}
	else
	{
		if(empty($cached_forum_permissions[$gid]))
		{
			foreach($forum_cache as $forum)
			{
				$cached_forum_permissions[$gid][$forum['fid']] = fetch_forum_permissions($forum['fid'], $gid, $groupperms);
			}
		}
		return $cached_forum_permissions[$gid];
	}
}

/**
 * Fetches the permissions for a specific forum/group applying the inheritance scheme.
 * Called by forum_permissions()
 *
 * @param int $fid The forum ID
 * @param string $gid A comma separated list of usergroups
 * @param array $groupperms Group permissions
 * @return array Permissions for this forum
*/
function fetch_forum_permissions($fid, $gid, $groupperms)
{
	global $groupscache, $forum_cache, $fpermcache, $mybb, $fpermfields;

	$groups = explode(",", $gid);

	if(empty($fpermcache[$fid])) // This forum has no custom or inherited permissions so lets just return the group permissions
	{
		return $groupperms;
	}

	$current_permissions = array();
	$only_view_own_threads = 1;
	$only_reply_own_threads = 1;

	foreach($groups as $gid)
	{
		if(!empty($groupscache[$gid]))
		{
			$level_permissions = $fpermcache[$fid][$gid];

			// If our permissions arn't inherited we need to figure them out
			if(empty($fpermcache[$fid][$gid]))
			{
				$parents = explode(',', $forum_cache[$fid]['parentlist']);
				rsort($parents);
				if(!empty($parents))
				{
					foreach($parents as $parent_id)
					{
						if(!empty($fpermcache[$parent_id][$gid]))
						{
							$level_permissions = $fpermcache[$parent_id][$gid];
							break;
						}
					}
				}
			}

			// If we STILL don't have forum permissions we use the usergroup itself
			if(empty($level_permissions))
			{
				$level_permissions = $groupscache[$gid];
			}

			foreach($level_permissions as $permission => $access)
			{
				if(empty($current_permissions[$permission]) || $access >= $current_permissions[$permission] || ($access == "yes" && $current_permissions[$permission] == "no"))
				{
					$current_permissions[$permission] = $access;
				}
			}

			if($level_permissions["canview"] && empty($level_permissions["canonlyviewownthreads"]))
			{
				$only_view_own_threads = 0;
			}

			if($level_permissions["canpostreplys"] && empty($level_permissions["canonlyreplyownthreads"]))
			{
				$only_reply_own_threads = 0;
			}
		}
	}

	// Figure out if we can view more than our own threads
	if($only_view_own_threads == 0)
	{
		$current_permissions["canonlyviewownthreads"] = 0;
	}

	// Figure out if we can reply more than our own threads
	if($only_reply_own_threads == 0)
	{
		$current_permissions["canonlyreplyownthreads"] = 0;
	}

	if(count($current_permissions) == 0)
	{
		$current_permissions = $groupperms;
	}
	return $current_permissions;
}

/**
 * Check the password given on a certain forum for validity
 *
 * @param int $fid The forum ID
 * @param int $pid The Parent ID
 * @param bool $return
 * @return bool
 */
function check_forum_password($fid, $pid=0, $return=false)
{
	global $mybb, $header, $footer, $headerinclude, $theme, $templates, $lang, $forum_cache;

	$showform = true;

	if(!is_array($forum_cache))
	{
		$forum_cache = cache_forums();
		if(!$forum_cache)
		{
			return false;
		}
	}

	// Loop through each of parent forums to ensure we have a password for them too
	if(isset($forum_cache[$fid]['parentlist']))
	{
		$parents = explode(',', $forum_cache[$fid]['parentlist']);
		rsort($parents);
	}
	if(!empty($parents))
	{
		foreach($parents as $parent_id)
		{
			if($parent_id == $fid || $parent_id == $pid)
			{
				continue;
			}

			if($forum_cache[$parent_id]['password'] != "")
			{
				check_forum_password($parent_id, $fid);
			}
		}
	}

	if(!empty($forum_cache[$fid]['password']))
	{
		$password = $forum_cache[$fid]['password'];
		if(isset($mybb->input['pwverify']) && $pid == 0)
		{
			if($password === $mybb->get_input('pwverify'))
			{
				my_setcookie("forumpass[$fid]", md5($mybb->user['uid'].$mybb->get_input('pwverify')), null, true);
				$showform = false;
			}
			else
			{
				eval("\$pwnote = \"".$templates->get("forumdisplay_password_wrongpass")."\";");
				$showform = true;
			}
		}
		else
		{
			if(!$mybb->cookies['forumpass'][$fid] || ($mybb->cookies['forumpass'][$fid] && md5($mybb->user['uid'].$password) !== $mybb->cookies['forumpass'][$fid]))
			{
				$showform = true;
			}
			else
			{
				$showform = false;
			}
		}
	}
	else
	{
		$showform = false;
	}

	if($return)
	{
		return $showform;
	}

	if($showform)
	{
		if($pid)
		{
			header("Location: ".$mybb->settings['bburl']."/".get_forum_link($fid));
		}
		else
		{
			$_SERVER['REQUEST_URI'] = htmlspecialchars_uni($_SERVER['REQUEST_URI']);
			eval("\$pwform = \"".$templates->get("forumdisplay_password")."\";");
			output_page($pwform);
		}
		exit;
	}
}

/**
 * Return the permissions for a moderator in a specific forum
 *
 * @param int $fid The forum ID
 * @param int $uid The user ID to fetch permissions for (0 assumes current logged in user)
 * @param string $parentslist The parent list for the forum (if blank, will be fetched)
 * @return array Array of moderator permissions for the specific forum
 */
function get_moderator_permissions($fid, $uid=0, $parentslist="")
{
	global $mybb, $cache, $db;
	static $modpermscache;

	if($uid < 1)
	{
		$uid = $mybb->user['uid'];
	}

	if($uid == 0)
	{
		return false;
	}

	if(isset($modpermscache[$fid][$uid]))
	{
		return $modpermscache[$fid][$uid];
	}

	if(!$parentslist)
	{
		$parentslist = explode(',', get_parent_list($fid));
	}

	// Get user groups
	$perms = array();
	$user = get_user($uid);

	$groups = array($user['usergroup']);

	if(!empty($user['additionalgroups']))
	{
		$extra_groups = explode(",", $user['additionalgroups']);

		foreach($extra_groups as $extra_group)
		{
			$groups[] = $extra_group;
		}
	}

	$mod_cache = $cache->read("moderators");

	foreach($mod_cache as $forumid => $forum)
	{
		if(!is_array($forum) || !in_array($forumid, $parentslist))
		{
			// No perms or we're not after this forum
			continue;
		}

		// User settings override usergroup settings
		if(is_array($forum['users'][$uid]))
		{
			$perm = $forum['users'][$uid];
			foreach($perm as $action => $value)
			{
				if(strpos($action, "can") === false)
				{
					continue;
				}

				// Figure out the user permissions
				if($value == 0)
				{
					// The user doesn't have permission to set this action
					$perms[$action] = 0;
				}
				else
				{
					$perms[$action] = max($perm[$action], $perms[$action]);
				}
			}
		}

		foreach($groups as $group)
		{
			if(!is_array($forum['usergroups'][$group]))
			{
				// There are no permissions set for this group
				continue;
			}

			$perm = $forum['usergroups'][$group];
			foreach($perm as $action => $value)
			{
				if(strpos($action, "can") === false)
				{
					continue;
				}

				$perms[$action] = max($perm[$action], $perms[$action]);
			}
		}
	}

	$modpermscache[$fid][$uid] = $perms;

	return $perms;
}

/**
 * Checks if a moderator has permissions to perform an action in a specific forum
 *
 * @param int $fid The forum ID (0 assumes global)
 * @param string $action The action tyring to be performed. (blank assumes any action at all)
 * @param int $uid The user ID (0 assumes current user)
 * @return bool Returns true if the user has permission, false if they do not
 */
function is_moderator($fid=0, $action="", $uid=0)
{
	global $mybb, $cache;

	if($uid == 0)
	{
		$uid = $mybb->user['uid'];
	}

	if($uid == 0)
	{
		return false;
	}

	$user_perms = user_permissions($uid);
	if($user_perms['issupermod'] == 1)
	{
		if($fid)
		{
			$forumpermissions = forum_permissions($fid);
			if($forumpermissions['canview'] && $forumpermissions['canviewthreads'] && !$forumpermissions['canonlyviewownthreads'])
			{
				return true;
			}
			return false;
		}
		return true;
	}
	else
	{
		if(!$fid)
		{
			$modcache = $cache->read('moderators');
			if(!empty($modcache))
			{
				foreach($modcache as $modusers)
				{
					if(isset($modusers['users'][$uid]) && $modusers['users'][$uid]['mid'] && (!$action || !empty($modusers['users'][$uid][$action])))
					{
						return true;
					}

					$groups = explode(',', $user_perms['all_usergroups']);

					foreach($groups as $group)
					{
						if(trim($group) != '' && isset($modusers['usergroups'][$group]) && (!$action || !empty($modusers['usergroups'][$group][$action])))
						{
							return true;
						}
					}
				}
			}
			return false;
		}
		else
		{
			$modperms = get_moderator_permissions($fid, $uid);

			if(!$action && $modperms)
			{
				return true;
			}
			else
			{
				if(isset($modperms[$action]) && $modperms[$action] == 1)
				{
					return true;
				}
				else
				{
					return false;
				}
			}
		}
	}
}

/**
 * Generate a list of the posticons.
 *
 * @return string The template of posticons.
 */
function get_post_icons()
{
	global $mybb, $cache, $icon, $theme, $templates, $lang;

	if(isset($mybb->input['icon']))
	{
		$icon = $mybb->get_input('icon');
	}

	$iconlist = '';
	$no_icons_checked = " checked=\"checked\"";
	// read post icons from cache, and sort them accordingly
	$posticons_cache = (array)$cache->read("posticons");
	$posticons = array();
	foreach($posticons_cache as $posticon)
	{
		$posticons[$posticon['name']] = $posticon;
	}
	krsort($posticons);

	foreach($posticons as $dbicon)
	{
		$dbicon['path'] = str_replace("{theme}", $theme['imgdir'], $dbicon['path']);
		$dbicon['path'] = htmlspecialchars_uni($mybb->get_asset_url($dbicon['path']));
		$dbicon['name'] = htmlspecialchars_uni($dbicon['name']);

		if($icon == $dbicon['iid'])
		{
			$checked = " checked=\"checked\"";
			$no_icons_checked = '';
		}
		else
		{
			$checked = '';
		}

		eval("\$iconlist .= \"".$templates->get("posticons_icon")."\";");
	}

	if(!empty($iconlist))
	{
		eval("\$posticons = \"".$templates->get("posticons")."\";");
	}
	else
	{
		$posticons = '';
	}

	return $posticons;
}

/**
 * MyBB setcookie() wrapper.
 *
 * @param string $name The cookie identifier.
 * @param string $value The cookie value.
 * @param int|string $expires The timestamp of the expiry date.
 * @param boolean $httponly True if setting a HttpOnly cookie (supported by the majority of web browsers)
 * @param string $samesite The samesite attribute to prevent CSRF.
 */
function my_setcookie($name, $value="", $expires="", $httponly=false, $samesite="")
{
	global $mybb;

	if(!$mybb->settings['cookiepath'])
	{
		$mybb->settings['cookiepath'] = "/";
	}

	if($expires == -1)
	{
		$expires = 0;
	}
	elseif($expires == "" || $expires == null)
	{
		$expires = TIME_NOW + (60*60*24*365); // Make the cookie expire in a years time
	}
	else
	{
		$expires = TIME_NOW + (int)$expires;
	}

	$mybb->settings['cookiepath'] = str_replace(array("\n","\r"), "", $mybb->settings['cookiepath']);
	$mybb->settings['cookiedomain'] = str_replace(array("\n","\r"), "", $mybb->settings['cookiedomain']);
	$mybb->settings['cookieprefix'] = str_replace(array("\n","\r", " "), "", $mybb->settings['cookieprefix']);

	// Versions of PHP prior to 5.2 do not support HttpOnly cookies and IE is buggy when specifying a blank domain so set the cookie manually
	$cookie = "Set-Cookie: {$mybb->settings['cookieprefix']}{$name}=".urlencode($value);

	if($expires > 0)
	{
		$cookie .= "; expires=".@gmdate('D, d-M-Y H:i:s \\G\\M\\T', $expires);
	}

	if(!empty($mybb->settings['cookiepath']))
	{
		$cookie .= "; path={$mybb->settings['cookiepath']}";
	}

	if(!empty($mybb->settings['cookiedomain']))
	{
		$cookie .= "; domain={$mybb->settings['cookiedomain']}";
	}

	if($httponly == true)
	{
		$cookie .= "; HttpOnly";
	}

	if($samesite != "" && $mybb->settings['cookiesamesiteflag'])
	{
		$samesite = strtolower($samesite);

		if($samesite == "lax" || $samesite == "strict")
		{
			$cookie .= "; SameSite=".$samesite;
		}
	}

	if($mybb->settings['cookiesecureflag'])
	{
		$cookie .= "; Secure";
	}

	$mybb->cookies[$name] = $value;

	header($cookie, false);
}

/**
 * Unset a cookie set by MyBB.
 *
 * @param string $name The cookie identifier.
 */
function my_unsetcookie($name)
{
	global $mybb;

	$expires = -3600;
	my_setcookie($name, "", $expires);

	unset($mybb->cookies[$name]);
}

/**
 * Get the contents from a serialised cookie array.
 *
 * @param string $name The cookie identifier.
 * @param int $id The cookie content id.
 * @return array|boolean The cookie id's content array or false when non-existent.
 */
function my_get_array_cookie($name, $id)
{
	global $mybb;

	if(!isset($mybb->cookies['mybb'][$name]))
	{
		return false;
	}

	$cookie = my_unserialize($mybb->cookies['mybb'][$name]);

	if(is_array($cookie) && isset($cookie[$id]))
	{
		return $cookie[$id];
	}
	else
	{
		return 0;
	}
}

/**
 * Set a serialised cookie array.
 *
 * @param string $name The cookie identifier.
 * @param int $id The cookie content id.
 * @param string $value The value to set the cookie to.
 * @param int|string $expires The timestamp of the expiry date.
 */
function my_set_array_cookie($name, $id, $value, $expires="")
{
	global $mybb;

	$cookie = $mybb->cookies['mybb'];
	if(isset($cookie[$name]))
	{
		$newcookie = my_unserialize($cookie[$name]);
	}
	else
	{
		$newcookie = array();
	}

	$newcookie[$id] = $value;
	$newcookie = my_serialize($newcookie);
	my_setcookie("mybb[$name]", addslashes($newcookie), $expires);

	// Make sure our current viarables are up-to-date as well
	$mybb->cookies['mybb'][$name] = $newcookie;
}

/*
 * Arbitrary limits for _safe_unserialize()
 */
define('MAX_SERIALIZED_INPUT_LENGTH', 10240);
define('MAX_SERIALIZED_ARRAY_LENGTH', 256);
define('MAX_SERIALIZED_ARRAY_DEPTH', 5);

/**
 * Credits go to https://github.com/piwik
 * Safe unserialize() replacement
 * - accepts a strict subset of PHP's native my_serialized representation
 * - does not unserialize objects
 *
 * @param string $str
 * @return mixed
 * @throw Exception if $str is malformed or contains unsupported types (e.g., resources, objects)
 */
function _safe_unserialize($str)
{
	if(strlen($str) > MAX_SERIALIZED_INPUT_LENGTH)
	{
		// input exceeds MAX_SERIALIZED_INPUT_LENGTH
		return false;
	}

	if(empty($str) || !is_string($str))
	{
		return false;
	}

	$stack = $list = $expected = array();

	/*
	 * states:
	 *   0 - initial state, expecting a single value or array
	 *   1 - terminal state
	 *   2 - in array, expecting end of array or a key
	 *   3 - in array, expecting value or another array
	 */
	$state = 0;
	while($state != 1)
	{
		$type = isset($str[0]) ? $str[0] : '';

		if($type == '}')
		{
			$str = substr($str, 1);
		}
		else if($type == 'N' && $str[1] == ';')
		{
			$value = null;
			$str = substr($str, 2);
		}
		else if($type == 'b' && preg_match('/^b:([01]);/', $str, $matches))
		{
			$value = $matches[1] == '1' ? true : false;
			$str = substr($str, 4);
		}
		else if($type == 'i' && preg_match('/^i:(-?[0-9]+);(.*)/s', $str, $matches))
		{
			$value = (int)$matches[1];
			$str = $matches[2];
		}
		else if($type == 'd' && preg_match('/^d:(-?[0-9]+\.?[0-9]*(E[+-][0-9]+)?);(.*)/s', $str, $matches))
		{
			$value = (float)$matches[1];
			$str = $matches[3];
		}
		else if($type == 's' && preg_match('/^s:([0-9]+):"(.*)/s', $str, $matches) && substr($matches[2], (int)$matches[1], 2) == '";')
		{
			$value = substr($matches[2], 0, (int)$matches[1]);
			$str = substr($matches[2], (int)$matches[1] + 2);
		}
		else if($type == 'a' && preg_match('/^a:([0-9]+):{(.*)/s', $str, $matches) && $matches[1] < MAX_SERIALIZED_ARRAY_LENGTH)
		{
			$expectedLength = (int)$matches[1];
			$str = $matches[2];
		}
		else
		{
			// object or unknown/malformed type
			return false;
		}

		switch($state)
		{
			case 3: // in array, expecting value or another array
				if($type == 'a')
				{
					if(count($stack) >= MAX_SERIALIZED_ARRAY_DEPTH)
					{
						// array nesting exceeds MAX_SERIALIZED_ARRAY_DEPTH
						return false;
					}

					$stack[] = &$list;
					$list[$key] = array();
					$list = &$list[$key];
					$expected[] = $expectedLength;
					$state = 2;
					break;
				}
				if($type != '}')
				{
					$list[$key] = $value;
					$state = 2;
					break;
				}

				// missing array value
				return false;

			case 2: // in array, expecting end of array or a key
				if($type == '}')
				{
					if(count($list) < end($expected))
					{
						// array size less than expected
						return false;
					}

					unset($list);
					$list = &$stack[count($stack)-1];
					array_pop($stack);

					// go to terminal state if we're at the end of the root array
					array_pop($expected);
					if(count($expected) == 0) {
						$state = 1;
					}
					break;
				}
				if($type == 'i' || $type == 's')
				{
					if(count($list) >= MAX_SERIALIZED_ARRAY_LENGTH)
					{
						// array size exceeds MAX_SERIALIZED_ARRAY_LENGTH
						return false;
					}
					if(count($list) >= end($expected))
					{
						// array size exceeds expected length
						return false;
					}

					$key = $value;
					$state = 3;
					break;
				}

				// illegal array index type
				return false;

			case 0: // expecting array or value
				if($type == 'a')
				{
					if(count($stack) >= MAX_SERIALIZED_ARRAY_DEPTH)
					{
						// array nesting exceeds MAX_SERIALIZED_ARRAY_DEPTH
						return false;
					}

					$data = array();
					$list = &$data;
					$expected[] = $expectedLength;
					$state = 2;
					break;
				}
				if($type != '}')
				{
					$data = $value;
					$state = 1;
					break;
				}

				// not in array
				return false;
		}
	}

	if(!empty($str))
	{
		// trailing data in input
		return false;
	}
	return $data;
}

/**
 * Credits go to https://github.com/piwik
 * Wrapper for _safe_unserialize() that handles exceptions and multibyte encoding issue
 *
 * @param string $str
 * @return mixed
 */
function my_unserialize($str)
{
	// Ensure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
	if(function_exists('mb_internal_encoding') && (((int)ini_get('mbstring.func_overload')) & 2))
	{
		$mbIntEnc = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}

	$out = _safe_unserialize($str);

	if(isset($mbIntEnc))
	{
		mb_internal_encoding($mbIntEnc);
	}

	return $out;
}

/**
 * Credits go to https://github.com/piwik
 * Safe serialize() replacement
 * - output a strict subset of PHP's native serialized representation
 * - does not my_serialize objects
 *
 * @param mixed $value
 * @return string
 * @throw Exception if $value is malformed or contains unsupported types (e.g., resources, objects)
 */
function _safe_serialize( $value )
{
	if(is_null($value))
	{
		return 'N;';
	}

	if(is_bool($value))
	{
		return 'b:'.(int)$value.';';
	}

	if(is_int($value))
	{
		return 'i:'.$value.';';
	}

	if(is_float($value))
	{
		return 'd:'.str_replace(',', '.', $value).';';
	}

	if(is_string($value))
	{
		return 's:'.strlen($value).':"'.$value.'";';
	}

	if(is_array($value))
	{
		$out = '';
		foreach($value as $k => $v)
		{
			$out .= _safe_serialize($k) . _safe_serialize($v);
		}

		return 'a:'.count($value).':{'.$out.'}';
	}

	// safe_serialize cannot my_serialize resources or objects
	return false;
}

/**
 * Credits go to https://github.com/piwik
 * Wrapper for _safe_serialize() that handles exceptions and multibyte encoding issue
 *
 * @param mixed $value
 * @return string
*/
function my_serialize($value)
{
	// ensure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
	if(function_exists('mb_internal_encoding') && (((int)ini_get('mbstring.func_overload')) & 2))
	{
		$mbIntEnc = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}

	$out = _safe_serialize($value);
	if(isset($mbIntEnc))
	{
		mb_internal_encoding($mbIntEnc);
	}

	return $out;
}

/**
 * Returns the serverload of the system.
 *
 * @return int The serverload of the system.
 */
function get_server_load()
{
	global $mybb, $lang;

	$serverload = array();

	// DIRECTORY_SEPARATOR checks if running windows
	if(DIRECTORY_SEPARATOR != '\\')
	{
		if(function_exists("sys_getloadavg"))
		{
			// sys_getloadavg() will return an array with [0] being load within the last minute.
			$serverload = sys_getloadavg();
			$serverload[0] = round($serverload[0], 4);
		}
		else if(@file_exists("/proc/loadavg") && $load = @file_get_contents("/proc/loadavg"))
		{
			$serverload = explode(" ", $load);
			$serverload[0] = round($serverload[0], 4);
		}
		if(!is_numeric($serverload[0]))
		{
			if($mybb->safemode)
			{
				return $lang->unknown;
			}

			// Suhosin likes to throw a warning if exec is disabled then die - weird
			if($func_blacklist = @ini_get('suhosin.executor.func.blacklist'))
			{
				if(strpos(",".$func_blacklist.",", 'exec') !== false)
				{
					return $lang->unknown;
				}
			}
			// PHP disabled functions?
			if($func_blacklist = @ini_get('disable_functions'))
			{
				if(strpos(",".$func_blacklist.",", 'exec') !== false)
				{
					return $lang->unknown;
				}
			}

			$load = @exec("uptime");
			$load = explode("load average: ", $load);
			$serverload = explode(",", $load[1]);
			if(!is_array($serverload))
			{
				return $lang->unknown;
			}
		}
	}
	else
	{
		return $lang->unknown;
	}

	$returnload = trim($serverload[0]);

	return $returnload;
}

/**
 * Returns the amount of memory allocated to the script.
 *
 * @return int The amount of memory allocated to the script.
 */
function get_memory_usage()
{
	if(function_exists('memory_get_peak_usage'))
	{
		return memory_get_peak_usage(true);
	}
	elseif(function_exists('memory_get_usage'))
	{
		return memory_get_usage(true);
	}
	return false;
}

/**
 * Updates the forum statistics with specific values (or addition/subtraction of the previous value)
 *
 * @param array $changes Array of items being updated (numthreads,numposts,numusers,numunapprovedthreads,numunapprovedposts,numdeletedposts,numdeletedthreads)
 * @param boolean $force Force stats update?
 */
function update_stats($changes=array(), $force=false)
{
	global $cache, $db;
	static $stats_changes;

	if(empty($stats_changes))
	{
		// Update stats after all changes are done
		add_shutdown('update_stats', array(array(), true));
	}

	if(empty($stats_changes) || $stats_changes['inserted'])
	{
		$stats_changes = array(
			'numthreads' => '+0',
			'numposts' => '+0',
			'numusers' => '+0',
			'numunapprovedthreads' => '+0',
			'numunapprovedposts' => '+0',
			'numdeletedposts' => '+0',
			'numdeletedthreads' => '+0',
			'inserted' => false // Reset after changes are inserted into cache
		);
		$stats = $stats_changes;
	}

	if($force) // Force writing to cache?
	{
		if(!empty($changes))
		{
			// Calculate before writing to cache
			update_stats($changes);
		}
		$stats = $cache->read("stats");
		$changes = $stats_changes;
	}
	else
	{
		$stats = $stats_changes;
	}

	$new_stats = array();
	$counters = array('numthreads', 'numunapprovedthreads', 'numposts', 'numunapprovedposts', 'numusers', 'numdeletedposts', 'numdeletedthreads');
	foreach($counters as $counter)
	{
		if(array_key_exists($counter, $changes))
		{
			if(substr($changes[$counter], 0, 2) == "+-")
			{
				$changes[$counter] = substr($changes[$counter], 1);
			}
			// Adding or subtracting from previous value?
			if(substr($changes[$counter], 0, 1) == "+" || substr($changes[$counter], 0, 1) == "-")
			{
				if((int)$changes[$counter] != 0)
				{
					$new_stats[$counter] = $stats[$counter] + $changes[$counter];
					if(!$force && (substr($stats[$counter], 0, 1) == "+" || substr($stats[$counter], 0, 1) == "-"))
					{
						// We had relative values? Then it is still relative
						if($new_stats[$counter] >= 0)
						{
							$new_stats[$counter] = "+{$new_stats[$counter]}";
						}
					}
					// Less than 0? That's bad
					elseif($new_stats[$counter] < 0)
					{
						$new_stats[$counter] = 0;
					}
				}
			}
			else
			{
				$new_stats[$counter] = $changes[$counter];
				// Less than 0? That's bad
				if($new_stats[$counter] < 0)
				{
					$new_stats[$counter] = 0;
				}
			}
		}
	}

	if(!$force)
	{
		$stats_changes = array_merge($stats, $new_stats); // Overwrite changed values
		return;
	}

	// Fetch latest user if the user count is changing
	if(array_key_exists('numusers', $changes))
	{
		$query = $db->simple_select("users", "uid, username", "", array('order_by' => 'regdate', 'order_dir' => 'DESC', 'limit' => 1));
		$lastmember = $db->fetch_array($query);
		$new_stats['lastuid'] = $lastmember['uid'];
		$new_stats['lastusername'] = $lastmember['username'] = htmlspecialchars_uni($lastmember['username']);
	}

	if(!empty($new_stats))
	{
		if(is_array($stats))
		{
			$stats = array_merge($stats, $new_stats); // Overwrite changed values
		}
		else
		{
			$stats = $new_stats;
		}
	}

	// Update stats row for today in the database
	$todays_stats = array(
		"dateline" => mktime(0, 0, 0, date("m"), date("j"), date("Y")),
		"numusers" => (int)$stats['numusers'],
		"numthreads" => (int)$stats['numthreads'],
		"numposts" => (int)$stats['numposts']
	);
	$db->replace_query("stats", $todays_stats, "dateline");

	$cache->update("stats", $stats, "dateline");
	$stats_changes['inserted'] = true;
}

/**
 * Updates the forum counters with a specific value (or addition/subtraction of the previous value)
 *
 * @param int $fid The forum ID
 * @param array $changes Array of items being updated (threads, posts, unapprovedthreads, unapprovedposts, deletedposts, deletedthreads) and their value (ex, 1, +1, -1)
 */
function update_forum_counters($fid, $changes=array())
{
	global $db;

	$update_query = array();

	$counters = array('threads', 'unapprovedthreads', 'posts', 'unapprovedposts', 'deletedposts', 'deletedthreads');

	// Fetch above counters for this forum
	$query = $db->simple_select("forums", implode(",", $counters), "fid='{$fid}'");
	$forum = $db->fetch_array($query);

	foreach($counters as $counter)
	{
		if(array_key_exists($counter, $changes))
		{
			if(substr($changes[$counter], 0, 2) == "+-")
			{
				$changes[$counter] = substr($changes[$counter], 1);
			}
			// Adding or subtracting from previous value?
			if(substr($changes[$counter], 0, 1) == "+" || substr($changes[$counter], 0, 1) == "-")
			{
				if((int)$changes[$counter] != 0)
				{
					$update_query[$counter] = $forum[$counter] + $changes[$counter];
				}
			}
			else
			{
				$update_query[$counter] = $changes[$counter];
			}

			// Less than 0? That's bad
			if(isset($update_query[$counter]) && $update_query[$counter] < 0)
			{
				$update_query[$counter] = 0;
			}
		}
	}

	// Only update if we're actually doing something
	if(count($update_query) > 0)
	{
		$db->update_query("forums", $update_query, "fid='".(int)$fid."'");
	}

	// Guess we should update the statistics too?
	$new_stats = array();
	if(array_key_exists('threads', $update_query))
	{
		$threads_diff = $update_query['threads'] - $forum['threads'];
		if($threads_diff > -1)
		{
			$new_stats['numthreads'] = "+{$threads_diff}";
		}
		else
		{
			$new_stats['numthreads'] = "{$threads_diff}";
		}
	}

	if(array_key_exists('unapprovedthreads', $update_query))
	{
		$unapprovedthreads_diff = $update_query['unapprovedthreads'] - $forum['unapprovedthreads'];
		if($unapprovedthreads_diff > -1)
		{
			$new_stats['numunapprovedthreads'] = "+{$unapprovedthreads_diff}";
		}
		else
		{
			$new_stats['numunapprovedthreads'] = "{$unapprovedthreads_diff}";
		}
	}

	if(array_key_exists('posts', $update_query))
	{
		$posts_diff = $update_query['posts'] - $forum['posts'];
		if($posts_diff > -1)
		{
			$new_stats['numposts'] = "+{$posts_diff}";
		}
		else
		{
			$new_stats['numposts'] = "{$posts_diff}";
		}
	}

	if(array_key_exists('unapprovedposts', $update_query))
	{
		$unapprovedposts_diff = $update_query['unapprovedposts'] - $forum['unapprovedposts'];
		if($unapprovedposts_diff > -1)
		{
			$new_stats['numunapprovedposts'] = "+{$unapprovedposts_diff}";
		}
		else
		{
			$new_stats['numunapprovedposts'] = "{$unapprovedposts_diff}";
		}
	}

	if(array_key_exists('deletedposts', $update_query))
	{
		$deletedposts_diff = $update_query['deletedposts'] - $forum['deletedposts'];
		if($deletedposts_diff > -1)
		{
			$new_stats['numdeletedposts'] = "+{$deletedposts_diff}";
		}
		else
		{
			$new_stats['numdeletedposts'] = "{$deletedposts_diff}";
		}
	}

	if(array_key_exists('deletedthreads', $update_query))
	{
		$deletedthreads_diff = $update_query['deletedthreads'] - $forum['deletedthreads'];
		if($deletedthreads_diff > -1)
		{
			$new_stats['numdeletedthreads'] = "+{$deletedthreads_diff}";
		}
		else
		{
			$new_stats['numdeletedthreads'] = "{$deletedthreads_diff}";
		}
	}

	if(!empty($new_stats))
	{
		update_stats($new_stats);
	}
}

/**
 * Update the last post information for a specific forum
 *
 * @param int $fid The forum ID
 */
function update_forum_lastpost($fid)
{
	global $db;

	// Fetch the last post for this forum
	$query = $db->query("
		SELECT tid, lastpost, lastposter, lastposteruid, subject
		FROM ".TABLE_PREFIX."threads
		WHERE fid='{$fid}' AND visible='1' AND closed NOT LIKE 'moved|%'
		ORDER BY lastpost DESC
		LIMIT 0, 1
	");
	$lastpost = $db->fetch_array($query);

	$updated_forum = array(
		"lastpost" => (int)$lastpost['lastpost'],
		"lastposter" => $db->escape_string($lastpost['lastposter']),
		"lastposteruid" => (int)$lastpost['lastposteruid'],
		"lastposttid" => (int)$lastpost['tid'],
		"lastpostsubject" => $db->escape_string($lastpost['subject'])
	);

	$db->update_query("forums", $updated_forum, "fid='{$fid}'");
}

/**
 * Updates the thread counters with a specific value (or addition/subtraction of the previous value)
 *
 * @param int $tid The thread ID
 * @param array $changes Array of items being updated (replies, unapprovedposts, deletedposts, attachmentcount) and their value (ex, 1, +1, -1)
 */
function update_thread_counters($tid, $changes=array())
{
	global $db;

	$update_query = array();
	$tid = (int)$tid;

	$counters = array('replies', 'unapprovedposts', 'attachmentcount', 'deletedposts', 'attachmentcount');

	// Fetch above counters for this thread
	$query = $db->simple_select("threads", implode(",", $counters), "tid='{$tid}'");
	$thread = $db->fetch_array($query);

	foreach($counters as $counter)
	{
		if(array_key_exists($counter, $changes))
		{
			if(substr($changes[$counter], 0, 2) == "+-")
			{
				$changes[$counter] = substr($changes[$counter], 1);
			}
			// Adding or subtracting from previous value?
			if(substr($changes[$counter], 0, 1) == "+" || substr($changes[$counter], 0, 1) == "-")
			{
				if((int)$changes[$counter] != 0)
				{
					$update_query[$counter] = $thread[$counter] + $changes[$counter];
				}
			}
			else
			{
				$update_query[$counter] = $changes[$counter];
			}

			// Less than 0? That's bad
			if(isset($update_query[$counter]) && $update_query[$counter] < 0)
			{
				$update_query[$counter] = 0;
			}
		}
	}

	$db->free_result($query);

	// Only update if we're actually doing something
	if(count($update_query) > 0)
	{
		$db->update_query("threads", $update_query, "tid='{$tid}'");
	}
}

/**
 * Update the first post and lastpost data for a specific thread
 *
 * @param int $tid The thread ID
 */
function update_thread_data($tid)
{
	global $db;

	$thread = get_thread($tid);

	// If this is a moved thread marker, don't update it - we need it to stay as it is
	if(strpos($thread['closed'], 'moved|') !== false)
	{
		return;
	}

	$query = $db->query("
		SELECT u.uid, u.username, p.username AS postusername, p.dateline
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE p.tid='$tid' AND p.visible='1'
		ORDER BY p.dateline DESC
		LIMIT 1"
	);
	$lastpost = $db->fetch_array($query);

	$db->free_result($query);

	$query = $db->query("
		SELECT u.uid, u.username, p.pid, p.username AS postusername, p.dateline
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE p.tid='$tid'
		ORDER BY p.dateline ASC
		LIMIT 1
	");
	$firstpost = $db->fetch_array($query);

	$db->free_result($query);

	if(empty($firstpost['username']))
	{
		$firstpost['username'] = $firstpost['postusername'];
	}

	if(empty($lastpost['username']))
	{
		$lastpost['username'] = $lastpost['postusername'];
	}

	if(empty($lastpost['dateline']))
	{
		$lastpost['username'] = $firstpost['username'];
		$lastpost['uid'] = $firstpost['uid'];
		$lastpost['dateline'] = $firstpost['dateline'];
	}

	$lastpost['username'] = $db->escape_string($lastpost['username']);
	$firstpost['username'] = $db->escape_string($firstpost['username']);

	$update_array = array(
		'firstpost' => (int)$firstpost['pid'],
		'username' => $firstpost['username'],
		'uid' => (int)$firstpost['uid'],
		'dateline' => (int)$firstpost['dateline'],
		'lastpost' => (int)$lastpost['dateline'],
		'lastposter' => $lastpost['username'],
		'lastposteruid' => (int)$lastpost['uid'],
	);
	$db->update_query("threads", $update_array, "tid='{$tid}'");
}

/**
 * Updates the user counters with a specific value (or addition/subtraction of the previous value)
 *
 * @param int $uid The user ID
 * @param array $changes Array of items being updated (postnum, threadnum) and their value (ex, 1, +1, -1)
 */
function update_user_counters($uid, $changes=array())
{
	global $db;

	$update_query = array();

	$counters = array('postnum', 'threadnum');
	$uid = (int)$uid;

	// Fetch above counters for this user
	$query = $db->simple_select("users", implode(",", $counters), "uid='{$uid}'");
	$user = $db->fetch_array($query);

	foreach($counters as $counter)
	{
		if(array_key_exists($counter, $changes))
		{
			if(substr($changes[$counter], 0, 2) == "+-")
			{
				$changes[$counter] = substr($changes[$counter], 1);
			}
			// Adding or subtracting from previous value?
			if(substr($changes[$counter], 0, 1) == "+" || substr($changes[$counter], 0, 1) == "-")
			{
				if((int)$changes[$counter] != 0)
				{
					$update_query[$counter] = $user[$counter] + $changes[$counter];
				}
			}
			else
			{
				$update_query[$counter] = $changes[$counter];
			}

			// Less than 0? That's bad
			if(isset($update_query[$counter]) && $update_query[$counter] < 0)
			{
				$update_query[$counter] = 0;
			}
		}
	}

	$db->free_result($query);

	// Only update if we're actually doing something
	if(count($update_query) > 0)
	{
		$db->update_query("users", $update_query, "uid='{$uid}'");
	}
}

/**
 * Deletes a thread from the database
 *
 * @param int $tid The thread ID
 * @return bool
 */
function delete_thread($tid)
{
	global $moderation;

	if(!is_object($moderation))
	{
		require_once MYBB_ROOT."inc/class_moderation.php";
		$moderation = new Moderation;
	}

	return $moderation->delete_thread($tid);
}

/**
 * Deletes a post from the database
 *
 * @param int $pid The thread ID
 * @return bool
 */
function delete_post($pid)
{
	global $moderation;

	if(!is_object($moderation))
	{
		require_once MYBB_ROOT."inc/class_moderation.php";
		$moderation = new Moderation;
	}

	return $moderation->delete_post($pid);
}

/**
 * Builds a forum jump menu
 *
 * @param int $pid The parent forum to start with
 * @param int $selitem The selected item ID
 * @param int $addselect If we need to add select boxes to this cal or not
 * @param string $depth The current depth of forums we're at
 * @param int $showextras Whether or not to show extra items such as User CP, Forum home
 * @param boolean $showall Ignore the showinjump setting and show all forums (for moderation pages)
 * @param mixed $permissions deprecated
 * @param string $name The name of the forum jump
 * @return string Forum jump items
 */
function build_forum_jump($pid=0, $selitem=0, $addselect=1, $depth="", $showextras=1, $showall=false, $permissions="", $name="fid")
{
	global $forum_cache, $jumpfcache, $permissioncache, $mybb, $forumjump, $forumjumpbits, $gobutton, $theme, $templates, $lang;

	$pid = (int)$pid;

	if(!is_array($jumpfcache))
	{
		if(!is_array($forum_cache))
		{
			cache_forums();
		}

		foreach($forum_cache as $fid => $forum)
		{
			if($forum['active'] != 0)
			{
				$jumpfcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
			}
		}
	}

	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}

	if(isset($jumpfcache[$pid]) && is_array($jumpfcache[$pid]))
	{
		foreach($jumpfcache[$pid] as $main)
		{
			foreach($main as $forum)
			{
				$perms = $permissioncache[$forum['fid']];

				if($forum['fid'] != "0" && ($perms['canview'] != 0 || $mybb->settings['hideprivateforums'] == 0) && $forum['linkto'] == '' && ($forum['showinjump'] != 0 || $showall == true))
				{
					$optionselected = "";

					if($selitem == $forum['fid'])
					{
						$optionselected = 'selected="selected"';
					}

					$forum['name'] = htmlspecialchars_uni(strip_tags($forum['name']));

					eval("\$forumjumpbits .= \"".$templates->get("forumjump_bit")."\";");

					if($forum_cache[$forum['fid']])
					{
						$newdepth = $depth."--";
						$forumjumpbits .= build_forum_jump($forum['fid'], $selitem, 0, $newdepth, $showextras, $showall);
					}
				}
			}
		}
	}

	if($addselect)
	{
		if($showextras == 0)
		{
			$template = "special";
		}
		else
		{
			$template = "advanced";

			if(strpos(FORUM_URL, '.html') !== false)
			{
				$forum_link = "'".str_replace('{fid}', "'+option+'", FORUM_URL)."'";
			}
			else
			{
				$forum_link = "'".str_replace('{fid}', "'+option", FORUM_URL);
			}
		}

		eval("\$forumjump = \"".$templates->get("forumjump_".$template)."\";");
	}

	return $forumjump;
}

/**
 * Returns the extension of a file.
 *
 * @param string $file The filename.
 * @return string The extension of the file.
 */
function get_extension($file)
{
	return my_strtolower(my_substr(strrchr($file, "."), 1));
}

/**
 * Generates a random string.
 *
 * @param int $length The length of the string to generate.
 * @param bool $complex Whether to return complex string. Defaults to false
 * @return string The random string.
 */
function random_str($length=8, $complex=false)
{
	$set = array_merge(range(0, 9), range('A', 'Z'), range('a', 'z'));
	$str = array();

	// Complex strings have always at least 3 characters, even if $length < 3
	if($complex == true)
	{
		// At least one number
		$str[] = $set[my_rand(0, 9)];

		// At least one big letter
		$str[] = $set[my_rand(10, 35)];

		// At least one small letter
		$str[] = $set[my_rand(36, 61)];

		$length -= 3;
	}

	for($i = 0; $i < $length; ++$i)
	{
		$str[] = $set[my_rand(0, 61)];
	}

	// Make sure they're in random order and convert them to a string
	shuffle($str);

	return implode($str);
}

/**
 * Formats a username based on their display group
 *
 * @param string $username The username
 * @param int $usergroup The usergroup for the user
 * @param int $displaygroup The display group for the user
 * @return string The formatted username
 */
function format_name($username, $usergroup, $displaygroup=0)
{
	global $groupscache, $cache, $plugins;

	static $formattednames = array();

	if(!isset($formattednames[$username]))
	{
		if(!is_array($groupscache))
		{
			$groupscache = $cache->read("usergroups");
		}

		if($displaygroup != 0)
		{
			$usergroup = $displaygroup;
		}

		$format = "{username}";

		if(isset($groupscache[$usergroup]))
		{
			$ugroup = $groupscache[$usergroup];

			if(strpos($ugroup['namestyle'], "{username}") !== false)
			{
				$format = $ugroup['namestyle'];
			}
		}

		$format = stripslashes($format);

		$parameters = compact('username', 'usergroup', 'displaygroup', 'format');

		$parameters = $plugins->run_hooks('format_name', $parameters);

		$format = $parameters['format'];

		$formattednames[$username] = str_replace("{username}", $username, $format);
	}

	return $formattednames[$username];
}

/**
 * Formats an avatar to a certain dimension
 *
 * @param string $avatar The avatar file name
 * @param string $dimensions Dimensions of the avatar, width x height (e.g. 44|44)
 * @param string $max_dimensions The maximum dimensions of the formatted avatar
 * @return array Information for the formatted avatar
 */
function format_avatar($avatar, $dimensions = '', $max_dimensions = '')
{
	global $mybb, $theme;
	static $avatars;

	if(!isset($avatars))
	{
		$avatars = array();
	}

	if(my_strpos($avatar, '://') !== false && !$mybb->settings['allowremoteavatars'])
	{
		// Remote avatar, but remote avatars are disallowed.
		$avatar = null;
	}

	if(!$avatar)
	{
		// Default avatar
		if(defined('IN_ADMINCP'))
		{
			$theme['imgdir'] = '../images';
		}

		$avatar = str_replace('{theme}', $theme['imgdir'], $mybb->settings['useravatar']);
		$dimensions = $mybb->settings['useravatardims'];
	}

	if(!$max_dimensions)
	{
		$max_dimensions = $mybb->settings['maxavatardims'];
	}

	// An empty key wouldn't work so we need to add a fall back
	$key = $dimensions;
	if(empty($key))
	{
		$key = 'default';
	}
	$key2 = $max_dimensions;
	if(empty($key2))
	{
		$key2 = 'default';
	}

	if(isset($avatars[$avatar][$key][$key2]))
	{
		return $avatars[$avatar][$key][$key2];
	}

	$avatar_width_height = '';

	if($dimensions)
	{
		$dimensions = preg_split('/[|x]/', $dimensions);

		if($dimensions[0] && $dimensions[1])
		{
			list($max_width, $max_height) = preg_split('/[|x]/', $max_dimensions);

			if(!empty($max_dimensions) && ($dimensions[0] > $max_width || $dimensions[1] > $max_height))
			{
				require_once MYBB_ROOT."inc/functions_image.php";
				$scaled_dimensions = scale_image($dimensions[0], $dimensions[1], $max_width, $max_height);
				$avatar_width_height = "width=\"{$scaled_dimensions['width']}\" height=\"{$scaled_dimensions['height']}\"";
			}
			else
			{
				$avatar_width_height = "width=\"{$dimensions[0]}\" height=\"{$dimensions[1]}\"";
			}
		}
	}

	$avatars[$avatar][$key][$key2] = array(
		'image' => htmlspecialchars_uni($mybb->get_asset_url($avatar)),
		'width_height' => $avatar_width_height
	);

	return $avatars[$avatar][$key][$key2];
}

/**
 * Build the javascript based MyCode inserter.
 *
 * @param string $bind The ID of the textarea to bind to. Defaults to "message".
 * @param bool $smilies Whether to include smilies. Defaults to true.
 *
 * @return string The MyCode inserter
 */
function build_mycode_inserter($bind="message", $smilies = true)
{
	global $db, $mybb, $theme, $templates, $lang, $plugins, $smiliecache, $cache;

	if($mybb->settings['bbcodeinserter'] != 0)
	{
		$editor_lang_strings = array(
			"editor_bold" => "Bold",
			"editor_italic" => "Italic",
			"editor_underline" => "Underline",
			"editor_strikethrough" => "Strikethrough",
			"editor_subscript" => "Subscript",
			"editor_superscript" => "Superscript",
			"editor_alignleft" => "Align left",
			"editor_center" => "Center",
			"editor_alignright" => "Align right",
			"editor_justify" => "Justify",
			"editor_fontname" => "Font Name",
			"editor_fontsize" => "Font Size",
			"editor_fontcolor" => "Font Color",
			"editor_removeformatting" => "Remove Formatting",
			"editor_cut" => "Cut",
			"editor_cutnosupport" => "Your browser does not allow the cut command. Please use the keyboard shortcut Ctrl/Cmd-X",
			"editor_copy" => "Copy",
			"editor_copynosupport" => "Your browser does not allow the copy command. Please use the keyboard shortcut Ctrl/Cmd-C",
			"editor_paste" => "Paste",
			"editor_pastenosupport" => "Your browser does not allow the paste command. Please use the keyboard shortcut Ctrl/Cmd-V",
			"editor_pasteentertext" => "Paste your text inside the following box:",
			"editor_pastetext" => "PasteText",
			"editor_numlist" => "Numbered list",
			"editor_bullist" => "Bullet list",
			"editor_undo" => "Undo",
			"editor_redo" => "Redo",
			"editor_rows" => "Rows:",
			"editor_cols" => "Cols:",
			"editor_inserttable" => "Insert a table",
			"editor_inserthr" => "Insert a horizontal rule",
			"editor_code" => "Code",
			"editor_width" => "Width (optional):",
			"editor_height" => "Height (optional):",
			"editor_insertimg" => "Insert an image",
			"editor_email" => "E-mail:",
			"editor_insertemail" => "Insert an email",
			"editor_url" => "URL:",
			"editor_insertlink" => "Insert a link",
			"editor_unlink" => "Unlink",
			"editor_more" => "More",
			"editor_insertemoticon" => "Insert an emoticon",
			"editor_videourl" => "Video URL:",
			"editor_videotype" => "Video Type:",
			"editor_insert" => "Insert",
			"editor_insertyoutubevideo" => "Insert a YouTube video",
			"editor_currentdate" => "Insert current date",
			"editor_currenttime" => "Insert current time",
			"editor_print" => "Print",
			"editor_viewsource" => "View source",
			"editor_description" => "Description (optional):",
			"editor_enterimgurl" => "Enter the image URL:",
			"editor_enteremail" => "Enter the e-mail address:",
			"editor_enterdisplayedtext" => "Enter the displayed text:",
			"editor_enterurl" => "Enter URL:",
			"editor_enteryoutubeurl" => "Enter the YouTube video URL or ID:",
			"editor_insertquote" => "Insert a Quote",
			"editor_invalidyoutube" => "Invalid YouTube video",
			"editor_dailymotion" => "Dailymotion",
			"editor_metacafe" => "MetaCafe",
			"editor_mixer" => "Mixer",
			"editor_vimeo" => "Vimeo",
			"editor_youtube" => "Youtube",
			"editor_facebook" => "Facebook",
			"editor_liveleak" => "LiveLeak",
			"editor_insertvideo" => "Insert a video",
			"editor_php" => "PHP",
			"editor_maximize" => "Maximize"
		);
		$editor_language = "(function ($) {\n$.sceditor.locale[\"mybblang\"] = {\n";

		$editor_lang_strings = $plugins->run_hooks("mycode_add_codebuttons", $editor_lang_strings);

		$editor_languages_count = count($editor_lang_strings);
		$i = 0;
		foreach($editor_lang_strings as $lang_string => $key)
		{
			$i++;
			$js_lang_string = str_replace("\"", "\\\"", $key);
			$string = str_replace("\"", "\\\"", $lang->$lang_string);
			$editor_language .= "\t\"{$js_lang_string}\": \"{$string}\"";

			if($i < $editor_languages_count)
			{
				$editor_language .= ",";
			}

			$editor_language .= "\n";
		}

		$editor_language .= "}})(jQuery);";

		if(defined("IN_ADMINCP"))
		{
			global $page;
			$codeinsert = $page->build_codebuttons_editor($bind, $editor_language, $smilies);
		}
		else
		{
			// Smilies
			$emoticon = "";
			$emoticons_enabled = "false";
			if($smilies)
			{
				if(!$smiliecache)
				{
					if(!isset($smilie_cache) || !is_array($smilie_cache))
					{
						$smilie_cache = $cache->read("smilies");
					}
					foreach($smilie_cache as $smilie)
					{
						$smilie['image'] = str_replace("{theme}", $theme['imgdir'], $smilie['image']);
						$smiliecache[$smilie['sid']] = $smilie;
					}
				}

				if($mybb->settings['smilieinserter'] && $mybb->settings['smilieinsertercols'] && $mybb->settings['smilieinsertertot'] && !empty($smiliecache))
				{
					$emoticon = ",emoticon";
				}
				$emoticons_enabled = "true";

				unset($smilie);

				if(is_array($smiliecache))
				{
					reset($smiliecache);

					$dropdownsmilies = $moresmilies = $hiddensmilies = "";
					$i = 0;

					foreach($smiliecache as $smilie)
					{
						$finds = explode("\n", $smilie['find']);
						$finds_count = count($finds);

						// Only show the first text to replace in the box
						$smilie['find'] = $finds[0];

						$find = str_replace(array('\\', '"'), array('\\\\', '\"'), htmlspecialchars_uni($smilie['find']));
						$image = htmlspecialchars_uni($mybb->get_asset_url($smilie['image']));
						$image = str_replace(array('\\', '"'), array('\\\\', '\"'), $image);

						if(!$mybb->settings['smilieinserter'] || !$mybb->settings['smilieinsertercols'] || !$mybb->settings['smilieinsertertot'] || !$smilie['showclickable'])
						{
							$hiddensmilies .= '"'.$find.'": "'.$image.'",';
						}
						elseif($i < $mybb->settings['smilieinsertertot'])
						{
							$dropdownsmilies .= '"'.$find.'": "'.$image.'",';
							++$i;
						}
						else
						{
							$moresmilies .= '"'.$find.'": "'.$image.'",';
						}

						for($j = 1; $j < $finds_count; ++$j)
						{
							$find = str_replace(array('\\', '"'), array('\\\\', '\"'), htmlspecialchars_uni($finds[$j]));
							$hiddensmilies .= '"'.$find.'": "'.$image.'",';
						}
					}
				}
			}

			$basic1 = $basic2 = $align = $font = $size = $color = $removeformat = $email = $link = $list = $code = $sourcemode = "";

			if($mybb->settings['allowbasicmycode'] == 1)
			{
				$basic1 = "bold,italic,underline,strike|";
				$basic2 = "horizontalrule,";
			}

			if($mybb->settings['allowalignmycode'] == 1)
			{
				$align = "left,center,right,justify|";
			}

			if($mybb->settings['allowfontmycode'] == 1)
			{
				$font = "font,";
			}

			if($mybb->settings['allowsizemycode'] == 1)
			{
				$size = "size,";
			}

			if($mybb->settings['allowcolormycode'] == 1)
			{
				$color = "color,";
			}

			if($mybb->settings['allowfontmycode'] == 1 || $mybb->settings['allowsizemycode'] == 1 || $mybb->settings['allowcolormycode'] == 1)
			{
				$removeformat = "removeformat|";
			}

			if($mybb->settings['allowemailmycode'] == 1)
			{
				$email = "email,";
			}

			if($mybb->settings['allowlinkmycode'] == 1)
			{
				$link = "link,unlink";
			}

			if($mybb->settings['allowlistmycode'] == 1)
			{
				$list = "bulletlist,orderedlist|";
			}

			if($mybb->settings['allowcodemycode'] == 1)
			{
				$code = "code,php,";
			}

			if($mybb->user['sourceeditor'] == 1)
			{
				$sourcemode = "MyBBEditor.sourceMode(true);";
			}

			eval("\$codeinsert = \"".$templates->get("codebuttons")."\";");
		}
	}

	return $codeinsert;
}

/**
 * @param int $tid
 * @param array $postoptions The options carried with form submit
 *
 * @return string Predefined / updated subscription method of the thread for the user
 */
function get_subscription_method($tid = 0, $postoptions = array())
{
	global $mybb;

	$subscription_methods = array('', 'none', 'email', 'pm'); // Define methods
	$subscription_method = (int)$mybb->user['subscriptionmethod']; // Set user default

	// If no user default method available then reset method
	if(!$subscription_method)
	{
		$subscription_method = 0;
	}

	// Return user default if no thread id available, in case
	if(!(int)$tid || (int)$tid <= 0)
	{
		return $subscription_methods[$subscription_method];
	}

	// If method not predefined set using data from database
	if(isset($postoptions['subscriptionmethod']))
	{
		$method = trim($postoptions['subscriptionmethod']);
		return (in_array($method, $subscription_methods)) ? $method : $subscription_methods[0];
	}
	else
	{
		global $db;

		$query = $db->simple_select("threadsubscriptions", "tid, notification", "tid='".(int)$tid."' AND uid='".$mybb->user['uid']."'", array('limit' => 1));
		$subscription = $db->fetch_array($query);

		if($subscription['tid'])
		{
			$subscription_method = (int)$subscription['notification'] + 1;
		}
	}
	
	return $subscription_methods[$subscription_method];
}

/**
 * Build the javascript clickable smilie inserter
 *
 * @return string The clickable smilies list
 */
function build_clickable_smilies()
{
	global $cache, $smiliecache, $theme, $templates, $lang, $mybb, $smiliecount;

	if($mybb->settings['smilieinserter'] != 0 && $mybb->settings['smilieinsertercols'] && $mybb->settings['smilieinsertertot'])
	{
		if(!$smiliecount)
		{
			$smilie_cache = $cache->read("smilies");
			$smiliecount = count($smilie_cache);
		}

		if(!$smiliecache)
		{
			if(!is_array($smilie_cache))
			{
				$smilie_cache = $cache->read("smilies");
			}
			foreach($smilie_cache as $smilie)
			{
				$smilie['image'] = str_replace("{theme}", $theme['imgdir'], $smilie['image']);
				$smiliecache[$smilie['sid']] = $smilie;
			}
		}

		unset($smilie);

		if(is_array($smiliecache))
		{
			reset($smiliecache);

			$getmore = '';
			if($mybb->settings['smilieinsertertot'] >= $smiliecount)
			{
				$mybb->settings['smilieinsertertot'] = $smiliecount;
			}
			else if($mybb->settings['smilieinsertertot'] < $smiliecount)
			{
				$smiliecount = $mybb->settings['smilieinsertertot'];
				eval("\$getmore = \"".$templates->get("smilieinsert_getmore")."\";");
			}

			$smilies = '';
			$counter = 0;
			$i = 0;

			$extra_class = '';
			foreach($smiliecache as $smilie)
			{
				if($i < $mybb->settings['smilieinsertertot'] && $smilie['showclickable'] != 0)
				{
					$smilie['image'] = str_replace("{theme}", $theme['imgdir'], $smilie['image']);
					$smilie['image'] = htmlspecialchars_uni($mybb->get_asset_url($smilie['image']));
					$smilie['name'] = htmlspecialchars_uni($smilie['name']);

					// Only show the first text to replace in the box
					$temp = explode("\n", $smilie['find']); // assign to temporary variable for php 5.3 compatibility
					$smilie['find'] = $temp[0];

					$find = str_replace(array('\\', "'"), array('\\\\', "\'"), htmlspecialchars_uni($smilie['find']));

					$onclick = " onclick=\"MyBBEditor.insertText(' $find ');\"";
					$extra_class = ' smilie_pointer';
					eval('$smilie = "'.$templates->get('smilie', 1, 0).'";');
					eval("\$smilie_icons .= \"".$templates->get("smilieinsert_smilie")."\";");
					++$i;
					++$counter;

					if($counter == $mybb->settings['smilieinsertercols'])
					{
						$counter = 0;
						eval("\$smilies .= \"".$templates->get("smilieinsert_row")."\";");
						$smilie_icons = '';
					}
				}
			}

			if($counter != 0)
			{
				$colspan = $mybb->settings['smilieinsertercols'] - $counter;
				eval("\$smilies .= \"".$templates->get("smilieinsert_row_empty")."\";");
			}

			eval("\$clickablesmilies = \"".$templates->get("smilieinsert")."\";");
		}
		else
		{
			$clickablesmilies = "";
		}
	}
	else
	{
		$clickablesmilies = "";
	}

	return $clickablesmilies;
}

/**
 * Builds thread prefixes and returns a selected prefix (or all)
 *
 *  @param int $pid The prefix ID (0 to return all)
 *  @return array The thread prefix's values (or all thread prefixes)
 */
function build_prefixes($pid=0)
{
	global $cache;
	static $prefixes_cache;

	if(is_array($prefixes_cache))
	{
		if($pid > 0 && is_array($prefixes_cache[$pid]))
		{
			return $prefixes_cache[$pid];
		}

		return $prefixes_cache;
	}

	$prefix_cache = $cache->read("threadprefixes");

	if(!is_array($prefix_cache))
	{
		// No cache
		$prefix_cache = $cache->read("threadprefixes", true);

		if(!is_array($prefix_cache))
		{
			return array();
		}
	}

	$prefixes_cache = array();
	foreach($prefix_cache as $prefix)
	{
		$prefixes_cache[$prefix['pid']] = $prefix;
	}

	if($pid != 0 && is_array($prefixes_cache[$pid]))
	{
		return $prefixes_cache[$pid];
	}
	else if(!empty($prefixes_cache))
	{
		return $prefixes_cache;
	}

	return false;
}

/**
 * Build the thread prefix selection menu for the current user
 *
 *  @param int|string $fid The forum ID (integer ID or string all)
 *  @param int|string $selected_pid The selected prefix ID (integer ID or string any)
 *  @param int $multiple Allow multiple prefix selection
 *  @param int $previous_pid The previously selected prefix ID
 *  @return string The thread prefix selection menu
 */
function build_prefix_select($fid, $selected_pid=0, $multiple=0, $previous_pid=0)
{
	global $cache, $db, $lang, $mybb, $templates;

	if($fid != 'all')
	{
		$fid = (int)$fid;
	}

	$prefix_cache = build_prefixes(0);
	if(empty($prefix_cache))
	{
		// We've got no prefixes to show
		return '';
	}

	// Go through each of our prefixes and decide which ones we can use
	$prefixes = array();
	foreach($prefix_cache as $prefix)
	{
		if($fid != "all" && $prefix['forums'] != "-1")
		{
			// Decide whether this prefix can be used in our forum
			$forums = explode(",", $prefix['forums']);

			if(!in_array($fid, $forums) && $prefix['pid'] != $previous_pid)
			{
				// This prefix is not in our forum list
				continue;
			}
		}

		if(is_member($prefix['groups']) || $prefix['pid'] == $previous_pid)
		{
			// The current user can use this prefix
			$prefixes[$prefix['pid']] = $prefix;
		}
	}

	if(empty($prefixes))
	{
		return '';
	}

	$prefixselect = $prefixselect_prefix = '';

	if($multiple == 1)
	{
		$any_selected = "";
		if($selected_pid == 'any')
		{
			$any_selected = " selected=\"selected\"";
		}
	}

	$default_selected = "";
	if(((int)$selected_pid == 0) && $selected_pid != 'any')
	{
		$default_selected = " selected=\"selected\"";
	}

	foreach($prefixes as $prefix)
	{
		$selected = "";
		if($prefix['pid'] == $selected_pid)
		{
			$selected = " selected=\"selected\"";
		}

		$prefix['prefix'] = htmlspecialchars_uni($prefix['prefix']);
		eval("\$prefixselect_prefix .= \"".$templates->get("post_prefixselect_prefix")."\";");
	}

	if($multiple != 0)
	{
		eval("\$prefixselect = \"".$templates->get("post_prefixselect_multiple")."\";");
	}
	else
	{
		eval("\$prefixselect = \"".$templates->get("post_prefixselect_single")."\";");
	}

	return $prefixselect;
}

/**
 * Build the thread prefix selection menu for a forum without group permission checks
 *
 *  @param int $fid The forum ID (integer ID)
 *  @param int $selected_pid The selected prefix ID (integer ID)
 *  @return string The thread prefix selection menu
 */
function build_forum_prefix_select($fid, $selected_pid=0)
{
	global $cache, $db, $lang, $mybb, $templates;

	$fid = (int)$fid;

	$prefix_cache = build_prefixes(0);
	if(empty($prefix_cache))
	{
		// We've got no prefixes to show
		return '';
	}

	// Go through each of our prefixes and decide which ones we can use
	$prefixes = array();
	foreach($prefix_cache as $prefix)
	{
		if($prefix['forums'] != "-1")
		{
			// Decide whether this prefix can be used in our forum
			$forums = explode(",", $prefix['forums']);

			if(in_array($fid, $forums))
			{
				// This forum can use this prefix!
				$prefixes[$prefix['pid']] = $prefix;
			}
		}
		else
		{
			// This prefix is for anybody to use...
			$prefixes[$prefix['pid']] = $prefix;
		}
	}

	if(empty($prefixes))
	{
		return '';
	}

	$default_selected = array();
	$selected_pid = (int)$selected_pid;

	if($selected_pid == 0)
	{
		$default_selected['all'] = ' selected="selected"';
	}
	else if($selected_pid == -1)
	{
		$default_selected['none'] = ' selected="selected"';
	}
	else if($selected_pid == -2)
	{
		$default_selected['any'] = ' selected="selected"';
	}

	foreach($prefixes as $prefix)
	{
		$selected = '';
		if($prefix['pid'] == $selected_pid)
		{
			$selected = ' selected="selected"';
		}

		$prefix['prefix'] = htmlspecialchars_uni($prefix['prefix']);
		eval('$prefixselect_prefix .= "'.$templates->get("forumdisplay_threadlist_prefixes_prefix").'";');
	}

	eval('$prefixselect = "'.$templates->get("forumdisplay_threadlist_prefixes").'";');
	return $prefixselect;
}

/**
 * Gzip encodes text to a specified level
 *
 * @param string $contents The string to encode
 * @param int $level The level (1-9) to encode at
 * @return string The encoded string
 */
function gzip_encode($contents, $level=1)
{
	if(function_exists("gzcompress") && function_exists("crc32") && !headers_sent() && !(ini_get('output_buffering') && my_strpos(' '.ini_get('output_handler'), 'ob_gzhandler')))
	{
		$httpaccept_encoding = '';

		if(isset($_SERVER['HTTP_ACCEPT_ENCODING']))
		{
			$httpaccept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'];
		}

		if(my_strpos(" ".$httpaccept_encoding, "x-gzip"))
		{
			$encoding = "x-gzip";
		}

		if(my_strpos(" ".$httpaccept_encoding, "gzip"))
		{
			$encoding = "gzip";
		}

		if(isset($encoding))
		{
			header("Content-Encoding: $encoding");

			if(function_exists("gzencode"))
			{
				$contents = gzencode($contents, $level);
			}
			else
			{
				$size = strlen($contents);
				$crc = crc32($contents);
				$gzdata = "\x1f\x8b\x08\x00\x00\x00\x00\x00\x00\xff";
				$gzdata .= my_substr(gzcompress($contents, $level), 2, -4);
				$gzdata .= pack("V", $crc);
				$gzdata .= pack("V", $size);
				$contents = $gzdata;
			}
		}
	}

	return $contents;
}

/**
 * Log the actions of a moderator.
 *
 * @param array $data The data of the moderator's action.
 * @param string $action The message to enter for the action the moderator performed.
 */
function log_moderator_action($data, $action="")
{
	global $mybb, $db, $session;

	$fid = 0;
	if(isset($data['fid']))
	{
		$fid = (int)$data['fid'];
		unset($data['fid']);
	}

	$tid = 0;
	if(isset($data['tid']))
	{
		$tid = (int)$data['tid'];
		unset($data['tid']);
	}

	$pid = 0;
	if(isset($data['pid']))
	{
		$pid = (int)$data['pid'];
		unset($data['pid']);
	}

	$tids = array();
	if(isset($data['tids']))
	{
		$tids = (array)$data['tids'];
		unset($data['tids']);
	}

	// Any remaining extra data - we my_serialize and insert in to its own column
	if(is_array($data))
	{
		$data = my_serialize($data);
	}

	$sql_array = array(
		"uid" => (int)$mybb->user['uid'],
		"dateline" => TIME_NOW,
		"fid" => (int)$fid,
		"tid" => $tid,
		"pid" => $pid,
		"action" => $db->escape_string($action),
		"data" => $db->escape_string($data),
		"ipaddress" => $db->escape_binary($session->packedip)
	);

	if($tids)
	{
		$multiple_sql_array = array();

		foreach($tids as $tid)
		{
			$sql_array['tid'] = (int)$tid;
			$multiple_sql_array[] = $sql_array;
		}

		$db->insert_query_multiple("moderatorlog", $multiple_sql_array);
	}
	else
	{
		$db->insert_query("moderatorlog", $sql_array);
	}
}

/**
 * Get the formatted reputation for a user.
 *
 * @param int $reputation The reputation value
 * @param int $uid The user ID (if not specified, the generated reputation will not be a link)
 * @return string The formatted repuation
 */
function get_reputation($reputation, $uid=0)
{
	global $theme, $templates;

	$display_reputation = $reputation_class = '';
	if($reputation < 0)
	{
		$reputation_class = "reputation_negative";
	}
	elseif($reputation > 0)
	{
		$reputation_class = "reputation_positive";
	}
	else
	{
		$reputation_class = "reputation_neutral";
	}

	$reputation = my_number_format($reputation);

	if($uid != 0)
	{
		eval("\$display_reputation = \"".$templates->get("postbit_reputation_formatted_link")."\";");
	}
	else
	{
		eval("\$display_reputation = \"".$templates->get("postbit_reputation_formatted")."\";");
	}

	return $display_reputation;
}

/**
 * Fetch a color coded version of a warning level (based on it's percentage)
 *
 * @param int $level The warning level (percentage of 100)
 * @return string Formatted warning level
 */
function get_colored_warning_level($level)
{
	global $templates;

	$warning_class = '';
	if($level >= 80)
	{
		$warning_class = "high_warning";
	}
	else if($level >= 50)
	{
		$warning_class = "moderate_warning";
	}
	else if($level >= 25)
	{
		$warning_class = "low_warning";
	}
	else
	{
		$warning_class = "normal_warning";
	}

	eval("\$level = \"".$templates->get("postbit_warninglevel_formatted")."\";");
	return $level;
}

/**
 * Fetch the IP address of the current user.
 *
 * @return string The IP address.
 */
function get_ip()
{
	global $mybb, $plugins;

	$ip = strtolower($_SERVER['REMOTE_ADDR']);

	if($mybb->settings['ip_forwarded_check'])
	{
		$addresses = array();

		if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
			$addresses = explode(',', strtolower($_SERVER['HTTP_X_FORWARDED_FOR']));
		}
		elseif(isset($_SERVER['HTTP_X_REAL_IP']))
		{
			$addresses = explode(',', strtolower($_SERVER['HTTP_X_REAL_IP']));
		}

		if(is_array($addresses))
		{
			foreach($addresses as $val)
			{
				$val = trim($val);
				// Validate IP address and exclude private addresses
				if(my_inet_ntop(my_inet_pton($val)) == $val && !preg_match("#^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.|fe80:|fe[c-f][0-f]:|f[c-d][0-f]{2}:)#", $val))
				{
					$ip = $val;
					break;
				}
			}
		}
	}

	if(!$ip)
	{
		if(isset($_SERVER['HTTP_CLIENT_IP']))
		{
			$ip = strtolower($_SERVER['HTTP_CLIENT_IP']);
		}
	}

	if($plugins)
	{
		$ip_array = array("ip" => &$ip); // Used for backwards compatibility on this hook with the updated run_hooks() function.
		$plugins->run_hooks("get_ip", $ip_array);
	}

	return $ip;
}

/**
 * Fetch the friendly size (GB, MB, KB, B) for a specified file size.
 *
 * @param int $size The size in bytes
 * @return string The friendly file size
 */
function get_friendly_size($size)
{
	global $lang;

	if(!is_numeric($size))
	{
		return $lang->na;
	}

	// Yottabyte (1024 Zettabytes)
	if($size >= 1208925819614629174706176)
	{
		$size = my_number_format(round(($size / 1208925819614629174706176), 2))." ".$lang->size_yb;
	}
	// Zetabyte (1024 Exabytes)
	elseif($size >= 1180591620717411303424)
	{
		$size = my_number_format(round(($size / 1180591620717411303424), 2))." ".$lang->size_zb;
	}
	// Exabyte (1024 Petabytes)
	elseif($size >= 1152921504606846976)
	{
		$size = my_number_format(round(($size / 1152921504606846976), 2))." ".$lang->size_eb;
	}
	// Petabyte (1024 Terabytes)
	elseif($size >= 1125899906842624)
	{
		$size = my_number_format(round(($size / 1125899906842624), 2))." ".$lang->size_pb;
	}
	// Terabyte (1024 Gigabytes)
	elseif($size >= 1099511627776)
	{
		$size = my_number_format(round(($size / 1099511627776), 2))." ".$lang->size_tb;
	}
	// Gigabyte (1024 Megabytes)
	elseif($size >= 1073741824)
	{
		$size = my_number_format(round(($size / 1073741824), 2))." ".$lang->size_gb;
	}
	// Megabyte (1024 Kilobytes)
	elseif($size >= 1048576)
	{
		$size = my_number_format(round(($size / 1048576), 2))." ".$lang->size_mb;
	}
	// Kilobyte (1024 bytes)
	elseif($size >= 1024)
	{
		$size = my_number_format(round(($size / 1024), 2))." ".$lang->size_kb;
	}
	elseif($size == 0)
	{
		$size = "0 ".$lang->size_bytes;
	}
	else
	{
		$size = my_number_format($size)." ".$lang->size_bytes;
	}

	return $size;
}

/**
 * Format a decimal number in to microseconds, milliseconds, or seconds.
 *
 * @param int $time The time in microseconds
 * @return string The friendly time duration
 */
function format_time_duration($time)
{
	global $lang;

	if(!is_numeric($time))
	{
		return $lang->na;
	}

	if(round(1000000 * $time, 2) < 1000)
	{
		$time = number_format(round(1000000 * $time, 2))." μs";
	}
	elseif(round(1000000 * $time, 2) >= 1000 && round(1000000 * $time, 2) < 1000000)
	{
		$time = number_format(round((1000 * $time), 2))." ms";
	}
	else
	{
		$time = round($time, 3)." seconds";
	}

	return $time;
}

/**
 * Get the attachment icon for a specific file extension
 *
 * @param string $ext The file extension
 * @return string The attachment icon
 */
function get_attachment_icon($ext)
{
	global $cache, $attachtypes, $theme, $templates, $lang, $mybb;

	if(!$attachtypes)
	{
		$attachtypes = $cache->read("attachtypes");
	}

	$ext = my_strtolower($ext);

	if($attachtypes[$ext]['icon'])
	{
		static $attach_icons_schemes = array();
		if(!isset($attach_icons_schemes[$ext]))
		{
			$attach_icons_schemes[$ext] = parse_url($attachtypes[$ext]['icon']);
			if(!empty($attach_icons_schemes[$ext]['scheme']))
			{
				$attach_icons_schemes[$ext] = $attachtypes[$ext]['icon'];
			}
			elseif(defined("IN_ADMINCP"))
			{
				$attach_icons_schemes[$ext] = str_replace("{theme}", "", $attachtypes[$ext]['icon']);
				if(my_substr($attach_icons_schemes[$ext], 0, 1) != "/")
				{
					$attach_icons_schemes[$ext] = "../".$attach_icons_schemes[$ext];
				}
			}
			elseif(defined("IN_PORTAL"))
			{
				global $change_dir;
				$attach_icons_schemes[$ext] = $change_dir."/".str_replace("{theme}", $theme['imgdir'], $attachtypes[$ext]['icon']);
				$attach_icons_schemes[$ext] = $mybb->get_asset_url($attach_icons_schemes[$ext]);
			}
			else
			{
				$attach_icons_schemes[$ext] = str_replace("{theme}", $theme['imgdir'], $attachtypes[$ext]['icon']);
				$attach_icons_schemes[$ext] = $mybb->get_asset_url($attach_icons_schemes[$ext]);
			}
		}

		$icon = $attach_icons_schemes[$ext];

		$name = htmlspecialchars_uni($attachtypes[$ext]['name']);
	}
	else
	{
		if(defined("IN_ADMINCP"))
		{
			$theme['imgdir'] = "../images";
		}
		else if(defined("IN_PORTAL"))
		{
			global $change_dir;
			$theme['imgdir'] = "{$change_dir}/images";
		}

		$icon = "{$theme['imgdir']}/attachtypes/unknown.png";

		$name = $lang->unknown;
	}

	$icon = htmlspecialchars_uni($icon);
	eval("\$attachment_icon = \"".$templates->get("attachment_icon")."\";");
	return $attachment_icon;
}

/**
 * Get a list of the unviewable forums for the current user
 *
 * @param boolean $only_readable_threads Set to true to only fetch those forums for which users can actually read a thread in.
 * @return string Comma separated values list of the forum IDs which the user cannot view
 */
function get_unviewable_forums($only_readable_threads=false)
{
	global $forum_cache, $permissioncache, $mybb;

	if(!is_array($forum_cache))
	{
		cache_forums();
	}

	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}

	$password_forums = $unviewable = array();
	foreach($forum_cache as $fid => $forum)
	{
		if($permissioncache[$forum['fid']])
		{
			$perms = $permissioncache[$forum['fid']];
		}
		else
		{
			$perms = $mybb->usergroup;
		}

		$pwverified = 1;

		if($forum['password'] != "")
		{
			if($mybb->cookies['forumpass'][$forum['fid']] !== md5($mybb->user['uid'].$forum['password']))
			{
				$pwverified = 0;
			}

			$password_forums[$forum['fid']] = $forum['password'];
		}
		else
		{
			// Check parents for passwords
			$parents = explode(",", $forum['parentlist']);
			foreach($parents as $parent)
			{
				if(isset($password_forums[$parent]) && $mybb->cookies['forumpass'][$parent] !== md5($mybb->user['uid'].$password_forums[$parent]))
				{
					$pwverified = 0;
				}
			}
		}

		if($perms['canview'] == 0 || $pwverified == 0 || ($only_readable_threads == true && $perms['canviewthreads'] == 0))
		{
			$unviewable[] = $forum['fid'];
		}
	}

	$unviewableforums = implode(',', $unviewable);

	return $unviewableforums;
}

/**
 * Fixes mktime for dates earlier than 1970
 *
 * @param string $format The date format to use
 * @param int $year The year of the date
 * @return string The correct date format
 */
function fix_mktime($format, $year)
{
	// Our little work around for the date < 1970 thing.
	// -2 idea provided by Matt Light (http://www.mephex.com)
	$format = str_replace("Y", $year, $format);
	$format = str_replace("y", my_substr($year, -2), $format);

	return $format;
}

/**
 * Build the breadcrumb navigation trail from the specified items
 *
 * @return string The formatted breadcrumb navigation trail
 */
function build_breadcrumb()
{
	global $nav, $navbits, $templates, $theme, $lang, $mybb;

	eval("\$navsep = \"".$templates->get("nav_sep")."\";");

	$i = 0;
	$activesep = '';

	if(is_array($navbits))
	{
		reset($navbits);
		foreach($navbits as $key => $navbit)
		{
			if(isset($navbits[$key+1]))
			{
				if(isset($navbits[$key+2]))
				{
					$sep = $navsep;
				}
				else
				{
					$sep = "";
				}

				$multipage = null;
				$multipage_dropdown = null;
				if(!empty($navbit['multipage']))
				{
					if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
					{
						$mybb->settings['threadsperpage'] = 20;
					}

					$multipage = multipage($navbit['multipage']['num_threads'], $mybb->settings['threadsperpage'], $navbit['multipage']['current_page'], $navbit['multipage']['url'], true);
					if($multipage)
					{
						++$i;
						eval("\$multipage_dropdown = \"".$templates->get("nav_dropdown")."\";");
						$sep = $multipage_dropdown.$sep;
					}
				}

				// Replace page 1 URLs
				$navbit['url'] = str_replace("-page-1.html", ".html", $navbit['url']);
				$navbit['url'] = preg_replace("/&amp;page=1$/", "", $navbit['url']);

				eval("\$nav .= \"".$templates->get("nav_bit")."\";");
			}
		}
		$navsize = count($navbits);
		$navbit = $navbits[$navsize-1];
	}

	if($nav)
	{
		eval("\$activesep = \"".$templates->get("nav_sep_active")."\";");
	}

	eval("\$activebit = \"".$templates->get("nav_bit_active")."\";");
	eval("\$donenav = \"".$templates->get("nav")."\";");

	return $donenav;
}

/**
 * Add a breadcrumb menu item to the list.
 *
 * @param string $name The name of the item to add
 * @param string $url The URL of the item to add
 */
function add_breadcrumb($name, $url="")
{
	global $navbits;

	$navsize = count($navbits);
	$navbits[$navsize]['name'] = $name;
	$navbits[$navsize]['url'] = $url;
}

/**
 * Build the forum breadcrumb nagiation (the navigation to a specific forum including all parent forums)
 *
 * @param int $fid The forum ID to build the navigation for
 * @param array $multipage The multipage drop down array of information
 * @return int Returns 1 in every case. Kept for compatibility
 */
function build_forum_breadcrumb($fid, $multipage=array())
{
	global $pforumcache, $currentitem, $forum_cache, $navbits, $lang, $base_url, $archiveurl;

	if(!$pforumcache)
	{
		if(!is_array($forum_cache))
		{
			cache_forums();
		}

		foreach($forum_cache as $key => $val)
		{
			$pforumcache[$val['fid']][$val['pid']] = $val;
		}
	}

	if(is_array($pforumcache[$fid]))
	{
		foreach($pforumcache[$fid] as $key => $forumnav)
		{
			if($fid == $forumnav['fid'])
			{
				if(!empty($pforumcache[$forumnav['pid']]))
				{
					build_forum_breadcrumb($forumnav['pid']);
				}

				$navsize = count($navbits);
				// Convert & to &amp;
				$navbits[$navsize]['name'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forumnav['name']);

				if(defined("IN_ARCHIVE"))
				{
					// Set up link to forum in breadcrumb.
					if($pforumcache[$fid][$forumnav['pid']]['type'] == 'f' || $pforumcache[$fid][$forumnav['pid']]['type'] == 'c')
					{
						$navbits[$navsize]['url'] = "{$base_url}forum-".$forumnav['fid'].".html";
					}
					else
					{
						$navbits[$navsize]['url'] = $archiveurl."/index.php";
					}
				}
				elseif(!empty($multipage))
				{
					$navbits[$navsize]['url'] = get_forum_link($forumnav['fid'], $multipage['current_page']);

					$navbits[$navsize]['multipage'] = $multipage;
					$navbits[$navsize]['multipage']['url'] = str_replace('{fid}', $forumnav['fid'], FORUM_URL_PAGED);
				}
				else
				{
					$navbits[$navsize]['url'] = get_forum_link($forumnav['fid']);
				}
			}
		}
	}

	return 1;
}

/**
 * Resets the breadcrumb navigation to the first item, and clears the rest
 */
function reset_breadcrumb()
{
	global $navbits;

	$newnav[0]['name'] = $navbits[0]['name'];
	$newnav[0]['url'] = $navbits[0]['url'];
	if(!empty($navbits[0]['options']))
	{
		$newnav[0]['options'] = $navbits[0]['options'];
	}

	unset($GLOBALS['navbits']);
	$GLOBALS['navbits'] = $newnav;
}

/**
 * Builds a URL to an archive mode page
 *
 * @param string $type The type of page (thread|announcement|forum)
 * @param int $id The ID of the item
 * @return string The URL
 */
function build_archive_link($type="", $id=0)
{
	global $mybb;

	// If the server OS is not Windows and not Apache or the PHP is running as a CGI or we have defined ARCHIVE_QUERY_STRINGS, use query strings - DIRECTORY_SEPARATOR checks if running windows
	//if((DIRECTORY_SEPARATOR == '\\' && is_numeric(stripos($_SERVER['SERVER_SOFTWARE'], "apache")) == false) || is_numeric(stripos(SAPI_NAME, "cgi")) !== false || defined("ARCHIVE_QUERY_STRINGS"))
	if($mybb->settings['seourls_archive'] == 1)
	{
		$base_url = $mybb->settings['bburl']."/archive/index.php/";
	}
	else
	{
		$base_url = $mybb->settings['bburl']."/archive/index.php?";
	}

	switch($type)
	{
		case "thread":
			$url = "{$base_url}thread-{$id}.html";
			break;
		case "announcement":
			$url = "{$base_url}announcement-{$id}.html";
			break;
		case "forum":
			$url = "{$base_url}forum-{$id}.html";
			break;
		default:
			$url = $mybb->settings['bburl']."/archive/index.php";
	}

	return $url;
}

/**
 * Prints a debug information page
 */
function debug_page()
{
	global $db, $debug, $templates, $templatelist, $mybb, $maintimer, $globaltime, $ptimer, $parsetime, $lang, $cache;

	$totaltime = format_time_duration($maintimer->totaltime);
	$phptime = $maintimer->totaltime - $db->query_time;
	$query_time = $db->query_time;
	$globaltime = format_time_duration($globaltime);

	$percentphp = number_format((($phptime/$maintimer->totaltime)*100), 2);
	$percentsql = number_format((($query_time/$maintimer->totaltime)*100), 2);

	$phptime = format_time_duration($maintimer->totaltime - $db->query_time);
	$query_time = format_time_duration($db->query_time);

	$call_time = format_time_duration($cache->call_time);

	$phpversion = PHP_VERSION;

	$serverload = get_server_load();

	if($mybb->settings['gzipoutput'] != 0)
	{
		$gzipen = "Enabled";
	}
	else
	{
		$gzipen = "Disabled";
	}

	echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
	echo "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\">";
	echo "<head>";
	echo "<meta name=\"robots\" content=\"noindex\" />";
	echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />";
	echo "<title>MyBB Debug Information</title>";
	echo "</head>";
	echo "<body>";
	echo "<h1>MyBB Debug Information</h1>\n";
	echo "<h2>Page Generation</h2>\n";
	echo "<table bgcolor=\"#666666\" width=\"95%\" cellpadding=\"4\" cellspacing=\"1\" align=\"center\">\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#cccccc\" colspan=\"4\"><b><span style=\"size:2;\">Page Generation Statistics</span></b></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#efefef\" width=\"25%\"><b><span style=\"font-family: tahoma; font-size: 12px;\">Page Generation Time:</span></b></td>\n";
	echo "<td bgcolor=\"#fefefe\" width=\"25%\"><span style=\"font-family: tahoma; font-size: 12px;\">$totaltime</span></td>\n";
	echo "<td bgcolor=\"#efefef\" width=\"25%\"><b><span style=\"font-family: tahoma; font-size: 12px;\">No. DB Queries:</span></b></td>\n";
	echo "<td bgcolor=\"#fefefe\" width=\"25%\"><span style=\"font-family: tahoma; font-size: 12px;\">$db->query_count</span></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#efefef\" width=\"25%\"><b><span style=\"font-family: tahoma; font-size: 12px;\">PHP Processing Time:</span></b></td>\n";
	echo "<td bgcolor=\"#fefefe\" width=\"25%\"><span style=\"font-family: tahoma; font-size: 12px;\">$phptime ($percentphp%)</span></td>\n";
	echo "<td bgcolor=\"#efefef\" width=\"25%\"><b><span style=\"font-family: tahoma; font-size: 12px;\">DB Processing Time:</span></b></td>\n";
	echo "<td bgcolor=\"#fefefe\" width=\"25%\"><span style=\"font-family: tahoma; font-size: 12px;\">$query_time ($percentsql%)</span></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#efefef\" width=\"25%\"><b><span style=\"font-family: tahoma; font-size: 12px;\">Extensions Used:</span></b></td>\n";
	echo "<td bgcolor=\"#fefefe\" width=\"25%\"><span style=\"font-family: tahoma; font-size: 12px;\">{$mybb->config['database']['type']}, xml</span></td>\n";
	echo "<td bgcolor=\"#efefef\" width=\"25%\"><b><span style=\"font-family: tahoma; font-size: 12px;\">Global.php Processing Time:</span></b></td>\n";
	echo "<td bgcolor=\"#fefefe\" width=\"25%\"><span style=\"font-family: tahoma; font-size: 12px;\">$globaltime</span></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#efefef\" width=\"25%\"><b><span style=\"font-family: tahoma; font-size: 12px;\">PHP Version:</span></b></td>\n";
	echo "<td bgcolor=\"#fefefe\" width=\"25%\"><span style=\"font-family: tahoma; font-size: 12px;\">$phpversion</span></td>\n";
	echo "<td bgcolor=\"#efefef\" width=\"25%\"><b><span style=\"font-family: tahoma; font-size: 12px;\">Server Load:</span></b></td>\n";
	echo "<td bgcolor=\"#fefefe\" width=\"25%\"><span style=\"font-family: tahoma; font-size: 12px;\">$serverload</span></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#efefef\" width=\"25%\"><b><span style=\"font-family: tahoma; font-size: 12px;\">GZip Encoding Status:</span></b></td>\n";
	echo "<td bgcolor=\"#fefefe\" width=\"25%\"><span style=\"font-family: tahoma; font-size: 12px;\">$gzipen</span></td>\n";
	echo "<td bgcolor=\"#efefef\" width=\"25%\"><b><span style=\"font-family: tahoma; font-size: 12px;\">No. Templates Used:</span></b></td>\n";
	echo "<td bgcolor=\"#fefefe\" width=\"25%\"><span style=\"font-family: tahoma; font-size: 12px;\">".count($templates->cache)." (".(int)count(explode(",", $templatelist))." Cached / ".(int)count($templates->uncached_templates)." Manually Loaded)</span></td>\n";
	echo "</tr>\n";

	$memory_usage = get_memory_usage();
	if(!$memory_usage)
	{
		$memory_usage = $lang->unknown;
	}
	else
	{
		$memory_usage = get_friendly_size($memory_usage)." ({$memory_usage} bytes)";
	}
	$memory_limit = @ini_get("memory_limit");
	echo "<tr>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><span style=\"font-family: tahoma; font-size: 12px;\">Memory Usage:</span></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><span style=\"font-family: tahoma; font-size: 12px;\">{$memory_usage}</span></td>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><span style=\"font-family: tahoma; font-size: 12px;\">Memory Limit:</span></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><span style=\"font-family: tahoma; font-size: 12px;\">{$memory_limit}</span></td>\n";
	echo "</tr>\n";

	echo "</table>\n";

	echo "<h2>Database Connections (".count($db->connections)." Total) </h2>\n";
	echo "<table style=\"background-color: #666;\" width=\"95%\" cellpadding=\"4\" cellspacing=\"1\" align=\"center\">\n";
	echo "<tr>\n";
	echo "<td style=\"background: #fff;\">".implode("<br />", $db->connections)."</td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "<br />\n";

	echo "<h2>Database Queries (".$db->query_count." Total) </h2>\n";
	echo $db->explain;

	if($cache->call_count > 0)
	{
		echo "<h2>Cache Calls (".$cache->call_count." Total, ".$call_time.") </h2>\n";
		echo $cache->cache_debug;
	}

	echo "<h2>Template Statistics</h2>\n";

	if(count($templates->cache) > 0)
	{
		echo "<table style=\"background-color: #666;\" width=\"95%\" cellpadding=\"4\" cellspacing=\"1\" align=\"center\">\n";
		echo "<tr>\n";
		echo "<td style=\"background-color: #ccc;\"><strong>Templates Used (Loaded for this Page) - ".count($templates->cache)." Total</strong></td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td style=\"background: #fff;\">".implode(", ", array_keys($templates->cache))."</td>\n";
		echo "</tr>\n";
		echo "</table>\n";
		echo "<br />\n";
	}

	if(count($templates->uncached_templates) > 0)
	{
		echo "<table style=\"background-color: #666;\" width=\"95%\" cellpadding=\"4\" cellspacing=\"1\" align=\"center\">\n";
		echo "<tr>\n";
		echo "<td style=\"background-color: #ccc;\"><strong>Templates Requiring Additional Calls (Not Cached at Startup) - ".count($templates->uncached_templates)." Total</strong></td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td style=\"background: #fff;\">".implode(", ", $templates->uncached_templates)."</td>\n";
		echo "</tr>\n";
		echo "</table>\n";
		echo "<br />\n";
	}
	echo "</body>";
	echo "</html>";
	exit;
}

/**
 * Outputs the correct page headers.
 */
function send_page_headers()
{
	global $mybb;

	if($mybb->settings['nocacheheaders'] == 1)
	{
		header("Expires: Sat, 1 Jan 2000 01:00:00 GMT");
		header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
	}
}

/**
 * Mark specific reported posts of a certain type as dealt with
 *
 * @param array|int $id An array or int of the ID numbers you're marking as dealt with
 * @param string $type The type of item the above IDs are for - post, posts, thread, threads, forum, all
 */
function mark_reports($id, $type="post")
{
	global $db, $cache, $plugins;

	switch($type)
	{
		case "posts":
			if(is_array($id))
			{
				$rids = implode($id, "','");
				$rids = "'0','$rids'";
				$db->update_query("reportedcontent", array('reportstatus' => 1), "id IN($rids) AND reportstatus='0' AND (type = 'post' OR type = '')");
			}
			break;
		case "post":
			$db->update_query("reportedcontent", array('reportstatus' => 1), "id='$id' AND reportstatus='0' AND (type = 'post' OR type = '')");
			break;
		case "threads":
			if(is_array($id))
			{
				$rids = implode($id, "','");
				$rids = "'0','$rids'";
				$db->update_query("reportedcontent", array('reportstatus' => 1), "id2 IN($rids) AND reportstatus='0' AND (type = 'post' OR type = '')");
			}
			break;
		case "thread":
			$db->update_query("reportedcontent", array('reportstatus' => 1), "id2='$id' AND reportstatus='0' AND (type = 'post' OR type = '')");
			break;
		case "forum":
			$db->update_query("reportedcontent", array('reportstatus' => 1), "id3='$id' AND reportstatus='0' AND (type = 'post' OR type = '')");
			break;
		case "all":
			$db->update_query("reportedcontent", array('reportstatus' => 1), "reportstatus='0' AND (type = 'post' OR type = '')");
			break;
	}

	$arguments = array('id' => $id, 'type' => $type);
	$plugins->run_hooks("mark_reports", $arguments);
	$cache->update_reportedcontent();
}

/**
 * Fetch a friendly x days, y months etc date stamp from a timestamp
 *
 * @param int $stamp The timestamp
 * @param array $options Array of options
 * @return string The friendly formatted timestamp
 */
function nice_time($stamp, $options=array())
{
	global $lang;

	$ysecs = 365*24*60*60;
	$mosecs = 31*24*60*60;
	$wsecs = 7*24*60*60;
	$dsecs = 24*60*60;
	$hsecs = 60*60;
	$msecs = 60;

	if(isset($options['short']))
	{
		$lang_year = $lang->year_short;
		$lang_years = $lang->years_short;
		$lang_month = $lang->month_short;
		$lang_months = $lang->months_short;
		$lang_week = $lang->week_short;
		$lang_weeks = $lang->weeks_short;
		$lang_day = $lang->day_short;
		$lang_days = $lang->days_short;
		$lang_hour = $lang->hour_short;
		$lang_hours = $lang->hours_short;
		$lang_minute = $lang->minute_short;
		$lang_minutes = $lang->minutes_short;
		$lang_second = $lang->second_short;
		$lang_seconds = $lang->seconds_short;
	}
	else
	{
		$lang_year = " ".$lang->year;
		$lang_years = " ".$lang->years;
		$lang_month = " ".$lang->month;
		$lang_months = " ".$lang->months;
		$lang_week = " ".$lang->week;
		$lang_weeks = " ".$lang->weeks;
		$lang_day = " ".$lang->day;
		$lang_days = " ".$lang->days;
		$lang_hour = " ".$lang->hour;
		$lang_hours = " ".$lang->hours;
		$lang_minute = " ".$lang->minute;
		$lang_minutes = " ".$lang->minutes;
		$lang_second = " ".$lang->second;
		$lang_seconds = " ".$lang->seconds;
	}

	$years = floor($stamp/$ysecs);
	$stamp %= $ysecs;
	$months = floor($stamp/$mosecs);
	$stamp %= $mosecs;
	$weeks = floor($stamp/$wsecs);
	$stamp %= $wsecs;
	$days = floor($stamp/$dsecs);
	$stamp %= $dsecs;
	$hours = floor($stamp/$hsecs);
	$stamp %= $hsecs;
	$minutes = floor($stamp/$msecs);
	$stamp %= $msecs;
	$seconds = $stamp;

	// Prevent gross over accuracy ($options parameter will override these)
	if($years > 0)
	{
		$options = array_merge(array(
			'days' => false,
			'hours' => false,
			'minutes' => false,
			'seconds' => false
		), $options);
	}
	elseif($months > 0)
	{
		$options = array_merge(array(
			'hours' => false,
			'minutes' => false,
			'seconds' => false
		), $options);
	}
	elseif($weeks > 0)
	{
		$options = array_merge(array(
			'minutes' => false,
			'seconds' => false
		), $options);
	}
	elseif($days > 0)
	{
		$options = array_merge(array(
			'seconds' => false
		), $options);
	}

	if(!isset($options['years']) || $options['years'] !== false)
	{
		if($years == 1)
		{
			$nicetime['years'] = "1".$lang_year;
		}
		else if($years > 1)
		{
			$nicetime['years'] = $years.$lang_years;
		}
	}

	if(!isset($options['months']) || $options['months'] !== false)
	{
		if($months == 1)
		{
			$nicetime['months'] = "1".$lang_month;
		}
		else if($months > 1)
		{
			$nicetime['months'] = $months.$lang_months;
		}
	}

	if(!isset($options['weeks']) || $options['weeks'] !== false)
	{
		if($weeks == 1)
		{
			$nicetime['weeks'] = "1".$lang_week;
		}
		else if($weeks > 1)
		{
			$nicetime['weeks'] = $weeks.$lang_weeks;
		}
	}

	if(!isset($options['days']) || $options['days'] !== false)
	{
		if($days == 1)
		{
			$nicetime['days'] = "1".$lang_day;
		}
		else if($days > 1)
		{
			$nicetime['days'] = $days.$lang_days;
		}
	}

	if(!isset($options['hours']) || $options['hours'] !== false)
	{
		if($hours == 1)
		{
			$nicetime['hours'] = "1".$lang_hour;
		}
		else if($hours > 1)
		{
			$nicetime['hours'] = $hours.$lang_hours;
		}
	}

	if(!isset($options['minutes']) || $options['minutes'] !== false)
	{
		if($minutes == 1)
		{
			$nicetime['minutes'] = "1".$lang_minute;
		}
		else if($minutes > 1)
		{
			$nicetime['minutes'] = $minutes.$lang_minutes;
		}
	}

	if(!isset($options['seconds']) || $options['seconds'] !== false)
	{
		if($seconds == 1)
		{
			$nicetime['seconds'] = "1".$lang_second;
		}
		else if($seconds > 1)
		{
			$nicetime['seconds'] = $seconds.$lang_seconds;
		}
	}

	if(is_array($nicetime))
	{
		return implode(", ", $nicetime);
	}
}

/**
 * Select an alternating row colour based on the previous call to this function
 *
 * @param int $reset 1 to reset the row to trow1.
 * @return string trow1 or trow2 depending on the previous call
 */
function alt_trow($reset=0)
{
	global $alttrow;

	if($alttrow == "trow1" && !$reset)
	{
		$trow = "trow2";
	}
	else
	{
		$trow = "trow1";
	}

	$alttrow = $trow;

	return $trow;
}

/**
 * Add a user to a specific additional user group.
 *
 * @param int $uid The user ID
 * @param int $joingroup The user group ID to join
 * @return bool
 */
function join_usergroup($uid, $joingroup)
{
	global $db, $mybb;

	if($uid == $mybb->user['uid'])
	{
		$user = $mybb->user;
	}
	else
	{
		$query = $db->simple_select("users", "additionalgroups, usergroup", "uid='".(int)$uid."'");
		$user = $db->fetch_array($query);
	}

	// Build the new list of additional groups for this user and make sure they're in the right format
	$usergroups = "";
	$usergroups = $user['additionalgroups'].",".$joingroup;
	$groupslist = "";
	$groups = explode(",", $usergroups);

	if(is_array($groups))
	{
		$comma = '';
		foreach($groups as $gid)
		{
			if(trim($gid) != "" && $gid != $user['usergroup'] && !isset($donegroup[$gid]))
			{
				$groupslist .= $comma.$gid;
				$comma = ",";
				$donegroup[$gid] = 1;
			}
		}
	}

	// What's the point in updating if they're the same?
	if($groupslist != $user['additionalgroups'])
	{
		$db->update_query("users", array('additionalgroups' => $groupslist), "uid='".(int)$uid."'");
		return true;
	}
	else
	{
		return false;
	}
}

/**
 * Remove a user from a specific additional user group
 *
 * @param int $uid The user ID
 * @param int $leavegroup The user group ID
 */
function leave_usergroup($uid, $leavegroup)
{
	global $db, $mybb, $cache;

	$user = get_user($uid);

	$groupslist = $comma = '';
	$usergroups = $user['additionalgroups'].",";
	$donegroup = array();

	$groups = explode(",", $user['additionalgroups']);

	if(is_array($groups))
	{
		foreach($groups as $gid)
		{
			if(trim($gid) != "" && $leavegroup != $gid && empty($donegroup[$gid]))
			{
				$groupslist .= $comma.$gid;
				$comma = ",";
				$donegroup[$gid] = 1;
			}
		}
	}

	$dispupdate = "";
	if($leavegroup == $user['displaygroup'])
	{
		$dispupdate = ", displaygroup=usergroup";
	}

	$db->write_query("
		UPDATE ".TABLE_PREFIX."users
		SET additionalgroups='$groupslist' $dispupdate
		WHERE uid='".(int)$uid."'
	");

	$cache->update_moderators();
}

/**
 * Get the current location taking in to account different web serves and systems
 *
 * @param boolean $fields True to return as "hidden" fields
 * @param array $ignore Array of fields to ignore if first argument is true
 * @param boolean $quick True to skip all inputs and return only the file path part of the URL
 * @return string The current URL being accessed
 */
function get_current_location($fields=false, $ignore=array(), $quick=false)
{
	if(defined("MYBB_LOCATION"))
	{
		return MYBB_LOCATION;
	}

	if(!empty($_SERVER['SCRIPT_NAME']))
	{
		$location = htmlspecialchars_uni($_SERVER['SCRIPT_NAME']);
	}
	elseif(!empty($_SERVER['PHP_SELF']))
	{
		$location = htmlspecialchars_uni($_SERVER['PHP_SELF']);
	}
	elseif(!empty($_ENV['PHP_SELF']))
	{
		$location = htmlspecialchars_uni($_ENV['PHP_SELF']);
	}
	elseif(!empty($_SERVER['PATH_INFO']))
	{
		$location = htmlspecialchars_uni($_SERVER['PATH_INFO']);
	}
	else
	{
		$location = htmlspecialchars_uni($_ENV['PATH_INFO']);
	}

	if($quick)
	{
		return $location;
	}

	if($fields == true)
	{
		global $mybb;

		if(!is_array($ignore))
		{
			$ignore = array($ignore);
		}

		$form_html = '';
		if(!empty($mybb->input))
		{
			foreach($mybb->input as $name => $value)
			{
				if(in_array($name, $ignore) || is_array($name) || is_array($value))
				{
					continue;
				}

				$form_html .= "<input type=\"hidden\" name=\"".htmlspecialchars_uni($name)."\" value=\"".htmlspecialchars_uni($value)."\" />\n";
			}
		}

		return array('location' => $location, 'form_html' => $form_html, 'form_method' => $mybb->request_method);
	}
	else
	{
		if(isset($_SERVER['QUERY_STRING']))
		{
			$location .= "?".htmlspecialchars_uni($_SERVER['QUERY_STRING']);
		}
		else if(isset($_ENV['QUERY_STRING']))
		{
			$location .= "?".htmlspecialchars_uni($_ENV['QUERY_STRING']);
		}

		if((isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == "POST") || (isset($_ENV['REQUEST_METHOD']) && $_ENV['REQUEST_METHOD'] == "POST"))
		{
			$post_array = array('action', 'fid', 'pid', 'tid', 'uid', 'eid');

			foreach($post_array as $var)
			{
				if(isset($_POST[$var]))
				{
					$addloc[] = urlencode($var).'='.urlencode($_POST[$var]);
				}
			}

			if(isset($addloc) && is_array($addloc))
			{
				if(strpos($location, "?") === false)
				{
					$location .= "?";
				}
				else
				{
					$location .= "&amp;";
				}
				$location .= implode("&amp;", $addloc);
			}
		}

		return $location;
	}
}

/**
 * Build a theme selection menu
 *
 * @param string $name The name of the menu
 * @param int $selected The ID of the selected theme
 * @param int $tid The ID of the parent theme to select from
 * @param string $depth The current selection depth
 * @param boolean $usergroup_override Whether or not to override usergroup permissions (true to override)
 * @param boolean $footer Whether or not theme select is in the footer (true if it is)
 * @param boolean $count_override Whether or not to override output based on theme count (true to override)
 * @return string The theme selection list
 */
function build_theme_select($name, $selected=-1, $tid=0, $depth="", $usergroup_override=false, $footer=false, $count_override=false)
{
	global $db, $themeselect, $tcache, $lang, $mybb, $limit, $templates, $num_themes, $themeselect_option;

	if($tid == 0)
	{
		$tid = 1;
		$num_themes = 0;
		$themeselect_option = '';

		if(!isset($lang->use_default))
		{
			$lang->use_default = $lang->lang_select_default;
		}
	}

	if(!is_array($tcache))
	{
		$query = $db->simple_select('themes', 'tid, name, pid, allowedgroups', "pid!='0'");

		while($theme = $db->fetch_array($query))
		{
			$tcache[$theme['pid']][$theme['tid']] = $theme;
		}
	}

	if(is_array($tcache[$tid]))
	{
		foreach($tcache[$tid] as $theme)
		{
			$sel = "";
			// Show theme if allowed, or if override is on
			if(is_member($theme['allowedgroups']) || $theme['allowedgroups'] == "all" || $usergroup_override == true)
			{
				if($theme['tid'] == $selected)
				{
					$sel = " selected=\"selected\"";
				}

				if($theme['pid'] != 0)
				{
					$theme['name'] = htmlspecialchars_uni($theme['name']);
					eval("\$themeselect_option .= \"".$templates->get("usercp_themeselector_option")."\";");
					++$num_themes;
					$depthit = $depth."--";
				}

				if(array_key_exists($theme['tid'], $tcache))
				{
					build_theme_select($name, $selected, $theme['tid'], $depthit, $usergroup_override, $footer, $count_override);
				}
			}
		}
	}

	if($tid == 1 && ($num_themes > 1 || $count_override == true))
	{
		if($footer == true)
		{
			eval("\$themeselect = \"".$templates->get("footer_themeselector")."\";");
		}
		else
		{
			eval("\$themeselect = \"".$templates->get("usercp_themeselector")."\";");
		}

		return $themeselect;
	}
	else
	{
		return false;
	}
}

/**
 * Get the theme data of a theme id.
 *
 * @param int $tid The theme id of the theme.
 * @return boolean|array False if no valid theme, Array with the theme data otherwise
 */
function get_theme($tid)
{
	global $tcache, $db;

	if(!is_array($tcache))
	{
		$query = $db->simple_select('themes', 'tid, name, pid, allowedgroups', "pid!='0'");

		while($theme = $db->fetch_array($query))
		{
			$tcache[$theme['pid']][$theme['tid']] = $theme;
		}
	}

	$s_theme = false;

	foreach($tcache as $themes)
	{
		foreach($themes as $theme)
		{
			if($tid == $theme['tid'])
			{
				$s_theme = $theme;
				break 2;
			}
		}
	}

	return $s_theme;
}

/**
 * Custom function for htmlspecialchars which takes in to account unicode
 *
 * @param string $message The string to format
 * @return string The string with htmlspecialchars applied
 */
function htmlspecialchars_uni($message)
{
	$message = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $message); // Fix & but allow unicode
	$message = str_replace("<", "&lt;", $message);
	$message = str_replace(">", "&gt;", $message);
	$message = str_replace("\"", "&quot;", $message);
	return $message;
}

/**
 * Custom function for formatting numbers.
 *
 * @param int $number The number to format.
 * @return int The formatted number.
 */
function my_number_format($number)
{
	global $mybb;

	if($number == "-")
	{
		return $number;
	}

	if(is_int($number))
	{
		return number_format($number, 0, $mybb->settings['decpoint'], $mybb->settings['thousandssep']);
	}
	else
	{
		$parts = explode('.', $number);

		if(isset($parts[1]))
		{
			$decimals = my_strlen($parts[1]);
		}
		else
		{
			$decimals = 0;
		}

		return number_format((double)$number, $decimals, $mybb->settings['decpoint'], $mybb->settings['thousandssep']);
	}
}

/**
 * Converts a string of text to or from UTF-8.
 *
 * @param string $str The string of text to convert
 * @param boolean $to Whether or not the string is being converted to or from UTF-8 (true if converting to)
 * @return string The converted string
 */
function convert_through_utf8($str, $to=true)
{
	global $lang;
	static $charset;
	static $use_mb;
	static $use_iconv;

	if(!isset($charset))
	{
		$charset = my_strtolower($lang->settings['charset']);
	}

	if($charset == "utf-8")
	{
		return $str;
	}

	if(!isset($use_iconv))
	{
		$use_iconv = function_exists("iconv");
	}

	if(!isset($use_mb))
	{
		$use_mb = function_exists("mb_convert_encoding");
	}

	if($use_iconv || $use_mb)
	{
		if($to)
		{
			$from_charset = $lang->settings['charset'];
			$to_charset = "UTF-8";
		}
		else
		{
			$from_charset = "UTF-8";
			$to_charset = $lang->settings['charset'];
		}
		if($use_iconv)
		{
			return iconv($from_charset, $to_charset."//IGNORE", $str);
		}
		else
		{
			return @mb_convert_encoding($str, $to_charset, $from_charset);
		}
	}
	elseif($charset == "iso-8859-1" && function_exists("utf8_encode"))
	{
		if($to)
		{
			return utf8_encode($str);
		}
		else
		{
			return utf8_decode($str);
		}
	}
	else
	{
		return $str;
	}
}

/**
 * DEPRECATED! Please use other alternatives.
 *
 * @deprecated
 * @param string $message
 *
 * @return string
 */
function my_wordwrap($message)
{
	return $message;
}

/**
 * Workaround for date limitation in PHP to establish the day of a birthday (Provided by meme)
 *
 * @param int $month The month of the birthday
 * @param int $day The day of the birthday
 * @param int $year The year of the bithday
 * @return int The numeric day of the week for the birthday
 */
function get_weekday($month, $day, $year)
{
	$h = 4;

	for($i = 1969; $i >= $year; $i--)
	{
		$j = get_bdays($i);

		for($k = 11; $k >= 0; $k--)
		{
			$l = ($k + 1);

			for($m = $j[$k]; $m >= 1; $m--)
			{
				$h--;

				if($i == $year && $l == $month && $m == $day)
				{
					return $h;
				}

				if($h == 0)
				{
					$h = 7;
				}
			}
		}
	}
}

/**
 * Workaround for date limitation in PHP to establish the day of a birthday (Provided by meme)
 *
 * @param int $in The year.
 * @return array The number of days in each month of that year
 */
function get_bdays($in)
{
	return array(
		31,
		($in % 4 == 0 && ($in % 100 > 0 || $in % 400 == 0) ? 29 : 28),
		31,
		30,
		31,
		30,
		31,
		31,
		30,
		31,
		30,
		31
	);
}

/**
 * DEPRECATED! Please use mktime()!
 * Formats a birthday appropriately
 *
 * @deprecated
 * @param string $display The PHP date format string
 * @param int $bm The month of the birthday
 * @param int $bd The day of the birthday
 * @param int $by The year of the birthday
 * @param int $wd The weekday of the birthday
 * @return string The formatted birthday
 */
function format_bdays($display, $bm, $bd, $by, $wd)
{
	global $lang;

	$bdays = array(
		$lang->sunday,
		$lang->monday,
		$lang->tuesday,
		$lang->wednesday,
		$lang->thursday,
		$lang->friday,
		$lang->saturday
	);

	$bmonth = array(
		$lang->month_1,
		$lang->month_2,
		$lang->month_3,
		$lang->month_4,
		$lang->month_5,
		$lang->month_6,
		$lang->month_7,
		$lang->month_8,
		$lang->month_9,
		$lang->month_10,
		$lang->month_11,
		$lang->month_12
	);

	// This needs to be in this specific order
	$find = array(
		'm',
		'n',
		'd',
		'D',
		'y',
		'Y',
		'j',
		'S',
		'F',
		'l',
		'M',
	);

	$html = array(
		'&#109;',
		'&#110;',
		'&#99;',
		'&#68;',
		'&#121;',
		'&#89;',
		'&#106;',
		'&#83;',
		'&#70;',
		'&#108;',
		'&#77;',
	);

	$bdays = str_replace($find, $html, $bdays);
	$bmonth = str_replace($find, $html, $bmonth);

	$replace = array(
		sprintf('%02s', $bm),
		$bm,
		sprintf('%02s', $bd),
		($wd == 2 ? my_substr($bdays[$wd], 0, 4) : ($wd == 4 ? my_substr($bdays[$wd], 0, 5) : my_substr($bdays[$wd], 0, 3))),
		my_substr($by, 2),
		$by,
		($bd[0] == 0 ? my_substr($bd, 1) : $bd),
		($bd == 1 || $bd == 21 || $bd == 31 ? 'st' : ($bd == 2 || $bd == 22 ? 'nd' : ($bd == 3 || $bd == 23 ? 'rd' : 'th'))),
		$bmonth[$bm-1],
		$wd,
		($bm == 9 ? my_substr($bmonth[$bm-1], 0, 4) :  my_substr($bmonth[$bm-1], 0, 3)),
	);

	// Do we have the full month in our output?
	// If so there's no need for the short month
	if(strpos($display, 'F') !== false)
	{
		array_pop($find);
		array_pop($replace);
	}

	return str_replace($find, $replace, $display);
}

/**
 * Returns the age of a user with specified birthday.
 *
 * @param string $birthday The birthday of a user.
 * @return int The age of a user with that birthday.
 */
function get_age($birthday)
{
	$bday = explode("-", $birthday);
	if(!$bday[2])
	{
		return;
	}

	list($day, $month, $year) = explode("-", my_date("j-n-Y", TIME_NOW, 0, 0));

	$age = $year-$bday[2];

	if(($month == $bday[1] && $day < $bday[0]) || $month < $bday[1])
	{
		--$age;
	}
	return $age;
}

/**
 * Updates the first posts in a thread.
 *
 * @param int $tid The thread id for which to update the first post id.
 */
function update_first_post($tid)
{
	global $db;

	$query = $db->query("
		SELECT u.uid, u.username, p.pid, p.username AS postusername, p.dateline
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE p.tid='$tid'
		ORDER BY p.dateline ASC
		LIMIT 1
	");
	$firstpost = $db->fetch_array($query);

	if(empty($firstpost['username']))
	{
		$firstpost['username'] = $firstpost['postusername'];
	}
	$firstpost['username'] = $db->escape_string($firstpost['username']);

	$update_array = array(
		'firstpost' => (int)$firstpost['pid'],
		'username' => $firstpost['username'],
		'uid' => (int)$firstpost['uid'],
		'dateline' => (int)$firstpost['dateline']
	);
	$db->update_query("threads", $update_array, "tid='{$tid}'");
}

/**
 * Updates the last posts in a thread.
 *
 * @param int $tid The thread id for which to update the last post id.
 */
function update_last_post($tid)
{
	global $db;

	$query = $db->query("
		SELECT u.uid, u.username, p.username AS postusername, p.dateline
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE p.tid='$tid' AND p.visible='1'
		ORDER BY p.dateline DESC
		LIMIT 1"
	);
	$lastpost = $db->fetch_array($query);

	if(empty($lastpost['username']))
	{
		$lastpost['username'] = $lastpost['postusername'];
	}

	if(empty($lastpost['dateline']))
	{
		$query = $db->query("
			SELECT u.uid, u.username, p.pid, p.username AS postusername, p.dateline
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE p.tid='$tid'
			ORDER BY p.dateline ASC
			LIMIT 1
		");
		$firstpost = $db->fetch_array($query);

		$lastpost['username'] = $firstpost['username'];
		$lastpost['uid'] = $firstpost['uid'];
		$lastpost['dateline'] = $firstpost['dateline'];
	}

	$lastpost['username'] = $db->escape_string($lastpost['username']);

	$update_array = array(
		'lastpost' => (int)$lastpost['dateline'],
		'lastposter' => $lastpost['username'],
		'lastposteruid' => (int)$lastpost['uid']
	);
	$db->update_query("threads", $update_array, "tid='{$tid}'");
}

/**
 * Checks for the length of a string, mb strings accounted for
 *
 * @param string $string The string to check the length of.
 * @return int The length of the string.
 */
function my_strlen($string)
{
	global $lang;

	$string = preg_replace("#&\#([0-9]+);#", "-", $string);

	if(strtolower($lang->settings['charset']) == "utf-8")
	{
		// Get rid of any excess RTL and LTR override for they are the workings of the devil
		$string = str_replace(dec_to_utf8(8238), "", $string);
		$string = str_replace(dec_to_utf8(8237), "", $string);

		// Remove dodgy whitespaces
		$string = str_replace(chr(0xCA), "", $string);
	}
	$string = trim($string);

	if(function_exists("mb_strlen"))
	{
		$string_length = mb_strlen($string);
	}
	else
	{
		$string_length = strlen($string);
	}

	return $string_length;
}

/**
 * Cuts a string at a specified point, mb strings accounted for
 *
 * @param string $string The string to cut.
 * @param int $start Where to cut
 * @param int $length (optional) How much to cut
 * @param bool $handle_entities (optional) Properly handle HTML entities?
 * @return string The cut part of the string.
 */
function my_substr($string, $start, $length=null, $handle_entities = false)
{
	if($handle_entities)
	{
		$string = unhtmlentities($string);
	}
	if(function_exists("mb_substr"))
	{
		if($length != null)
		{
			$cut_string = mb_substr($string, $start, $length);
		}
		else
		{
			$cut_string = mb_substr($string, $start);
		}
	}
	else
	{
		if($length != null)
		{
			$cut_string = substr($string, $start, $length);
		}
		else
		{
			$cut_string = substr($string, $start);
		}
	}

	if($handle_entities)
	{
		$cut_string = htmlspecialchars_uni($cut_string);
	}
	return $cut_string;
}

/**
 * Lowers the case of a string, mb strings accounted for
 *
 * @param string $string The string to lower.
 * @return string The lowered string.
 */
function my_strtolower($string)
{
	if(function_exists("mb_strtolower"))
	{
		$string = mb_strtolower($string);
	}
	else
	{
		$string = strtolower($string);
	}

	return $string;
}

/**
 * Finds a needle in a haystack and returns it position, mb strings accounted for
 *
 * @param string $haystack String to look in (haystack)
 * @param string $needle What to look for (needle)
 * @param int $offset (optional) How much to offset
 * @return int|bool false on needle not found, integer position if found
 */
function my_strpos($haystack, $needle, $offset=0)
{
	if($needle == '')
	{
		return false;
	}

	if(function_exists("mb_strpos"))
	{
		$position = mb_strpos($haystack, $needle, $offset);
	}
	else
	{
		$position = strpos($haystack, $needle, $offset);
	}

	return $position;
}

/**
 * Ups the case of a string, mb strings accounted for
 *
 * @param string $string The string to up.
 * @return string The uped string.
 */
function my_strtoupper($string)
{
	if(function_exists("mb_strtoupper"))
	{
		$string = mb_strtoupper($string);
	}
	else
	{
		$string = strtoupper($string);
	}

	return $string;
}

/**
 * Returns any html entities to their original character
 *
 * @param string $string The string to un-htmlentitize.
 * @return string The un-htmlentitied' string.
 */
function unhtmlentities($string)
{
	// Replace numeric entities
	$string = preg_replace_callback('~&#x([0-9a-f]+);~i', 'unichr_callback1', $string);
	$string = preg_replace_callback('~&#([0-9]+);~', 'unichr_callback2', $string);

	// Replace literal entities
	$trans_tbl = get_html_translation_table(HTML_ENTITIES);
	$trans_tbl = array_flip($trans_tbl);

	return strtr($string, $trans_tbl);
}

/**
 * Returns any ascii to it's character (utf-8 safe).
 *
 * @param int $c The ascii to characterize.
 * @return string|bool The characterized ascii. False on failure
 */
function unichr($c)
{
	if($c <= 0x7F)
	{
		return chr($c);
	}
	else if($c <= 0x7FF)
	{
		return chr(0xC0 | $c >> 6) . chr(0x80 | $c & 0x3F);
	}
	else if($c <= 0xFFFF)
	{
		return chr(0xE0 | $c >> 12) . chr(0x80 | $c >> 6 & 0x3F)
									. chr(0x80 | $c & 0x3F);
	}
	else if($c <= 0x10FFFF)
	{
		return chr(0xF0 | $c >> 18) . chr(0x80 | $c >> 12 & 0x3F)
									. chr(0x80 | $c >> 6 & 0x3F)
									. chr(0x80 | $c & 0x3F);
	}
	else
	{
		return false;
	}
}

/**
 * Returns any ascii to it's character (utf-8 safe).
 *
 * @param array $matches Matches.
 * @return string|bool The characterized ascii. False on failure
 */
function unichr_callback1($matches)
{
	return unichr(hexdec($matches[1]));
}

/**
 * Returns any ascii to it's character (utf-8 safe).
 *
 * @param array $matches Matches.
 * @return string|bool The characterized ascii. False on failure
 */
function unichr_callback2($matches)
{
	return unichr($matches[1]);
}

/**
 * Get the event poster.
 *
 * @param array $event The event data array.
 * @return string The link to the event poster.
 */
function get_event_poster($event)
{
	$event['username'] = htmlspecialchars_uni($event['username']);
	$event['username'] = format_name($event['username'], $event['usergroup'], $event['displaygroup']);
	$event_poster = build_profile_link($event['username'], $event['author']);
	return $event_poster;
}

/**
 * Get the event date.
 *
 * @param array $event The event data array.
 * @return string The event date.
 */
function get_event_date($event)
{
	global $mybb;

	$event_date = explode("-", $event['date']);
	$event_date = gmmktime(0, 0, 0, $event_date[1], $event_date[0], $event_date[2]);
	$event_date = my_date($mybb->settings['dateformat'], $event_date);

	return $event_date;
}

/**
 * Get the profile link.
 *
 * @param int $uid The user id of the profile.
 * @return string The url to the profile.
 */
function get_profile_link($uid=0)
{
	$link = str_replace("{uid}", $uid, PROFILE_URL);
	return htmlspecialchars_uni($link);
}

/**
 * Get the announcement link.
 *
 * @param int $aid The announement id of the announcement.
 * @return string The url to the announcement.
 */
function get_announcement_link($aid=0)
{
	$link = str_replace("{aid}", $aid, ANNOUNCEMENT_URL);
	return htmlspecialchars_uni($link);
}

/**
 * Build the profile link.
 *
 * @param string $username The Username of the profile.
 * @param int $uid The user id of the profile.
 * @param string $target The target frame
 * @param string $onclick Any onclick javascript.
 * @return string The complete profile link.
 */
function build_profile_link($username="", $uid=0, $target="", $onclick="")
{
	global $mybb, $lang;

	if(!$username && $uid == 0)
	{
		// Return Guest phrase for no UID, no guest nickname
		return htmlspecialchars_uni($lang->guest);
	}
	elseif($uid == 0)
	{
		// Return the guest's nickname if user is a guest but has a nickname
		return $username;
	}
	else
	{
		// Build the profile link for the registered user
		if(!empty($target))
		{
			$target = " target=\"{$target}\"";
		}

		if(!empty($onclick))
		{
			$onclick = " onclick=\"{$onclick}\"";
		}

		return "<a href=\"{$mybb->settings['bburl']}/".get_profile_link($uid)."\"{$target}{$onclick}>{$username}</a>";
	}
}

/**
 * Build the forum link.
 *
 * @param int $fid The forum id of the forum.
 * @param int $page (Optional) The page number of the forum.
 * @return string The url to the forum.
 */
function get_forum_link($fid, $page=0)
{
	if($page > 0)
	{
		$link = str_replace("{fid}", $fid, FORUM_URL_PAGED);
		$link = str_replace("{page}", $page, $link);
		return htmlspecialchars_uni($link);
	}
	else
	{
		$link = str_replace("{fid}", $fid, FORUM_URL);
		return htmlspecialchars_uni($link);
	}
}

/**
 * Build the thread link.
 *
 * @param int $tid The thread id of the thread.
 * @param int $page (Optional) The page number of the thread.
 * @param string $action (Optional) The action we're performing (ex, lastpost, newpost, etc)
 * @return string The url to the thread.
 */
function get_thread_link($tid, $page=0, $action='')
{
	if($page > 1)
	{
		if($action)
		{
			$link = THREAD_URL_ACTION;
			$link = str_replace("{action}", $action, $link);
		}
		else
		{
			$link = THREAD_URL_PAGED;
		}
		$link = str_replace("{tid}", $tid, $link);
		$link = str_replace("{page}", $page, $link);
		return htmlspecialchars_uni($link);
	}
	else
	{
		if($action)
		{
			$link = THREAD_URL_ACTION;
			$link = str_replace("{action}", $action, $link);
		}
		else
		{
			$link = THREAD_URL;
		}
		$link = str_replace("{tid}", $tid, $link);
		return htmlspecialchars_uni($link);
	}
}

/**
 * Build the post link.
 *
 * @param int $pid The post ID of the post
 * @param int $tid The thread id of the post.
 * @return string The url to the post.
 */
function get_post_link($pid, $tid=0)
{
	if($tid > 0)
	{
		$link = str_replace("{tid}", $tid, THREAD_URL_POST);
		$link = str_replace("{pid}", $pid, $link);
		return htmlspecialchars_uni($link);
	}
	else
	{
		$link = str_replace("{pid}", $pid, POST_URL);
		return htmlspecialchars_uni($link);
	}
}

/**
 * Build the event link.
 *
 * @param int $eid The event ID of the event
 * @return string The URL of the event
 */
function get_event_link($eid)
{
	$link = str_replace("{eid}", $eid, EVENT_URL);
	return htmlspecialchars_uni($link);
}

/**
 * Build the link to a specified date on the calendar
 *
 * @param int $calendar The ID of the calendar
 * @param int $year The year
 * @param int $month The month
 * @param int $day The day (optional)
 * @return string The URL of the calendar
 */
function get_calendar_link($calendar, $year=0, $month=0, $day=0)
{
	if($day > 0)
	{
		$link = str_replace("{month}", $month, CALENDAR_URL_DAY);
		$link = str_replace("{year}", $year, $link);
		$link = str_replace("{day}", $day, $link);
		$link = str_replace("{calendar}", $calendar, $link);
		return htmlspecialchars_uni($link);
	}
	else if($month > 0)
	{
		$link = str_replace("{month}", $month, CALENDAR_URL_MONTH);
		$link = str_replace("{year}", $year, $link);
		$link = str_replace("{calendar}", $calendar, $link);
		return htmlspecialchars_uni($link);
	}
	/* Not implemented
	else if($year > 0)
	{
	}*/
	else
	{
		$link = str_replace("{calendar}", $calendar, CALENDAR_URL);
		return htmlspecialchars_uni($link);
	}
}

/**
 * Build the link to a specified week on the calendar
 *
 * @param int $calendar The ID of the calendar
 * @param int $week The week
 * @return string The URL of the calendar
 */
function get_calendar_week_link($calendar, $week)
{
	if($week < 0)
	{
		$week = str_replace('-', "n", $week);
	}
	$link = str_replace("{week}", $week, CALENDAR_URL_WEEK);
	$link = str_replace("{calendar}", $calendar, $link);
	return htmlspecialchars_uni($link);
}

/**
 * Get the user data of an user id.
 *
 * @param int $uid The user id of the user.
 * @return array The users data
 */
function get_user($uid)
{
	global $mybb, $db;
	static $user_cache;

	$uid = (int)$uid;

	if(!empty($mybb->user) && $uid == $mybb->user['uid'])
	{
		return $mybb->user;
	}
	elseif(isset($user_cache[$uid]))
	{
		return $user_cache[$uid];
	}
	elseif($uid > 0)
	{
		$query = $db->simple_select("users", "*", "uid = '{$uid}'");
		$user_cache[$uid] = $db->fetch_array($query);

		return $user_cache[$uid];
	}
	return array();
}

/**
 * Get the user data of an user username.
 *
 * @param string $username The user username of the user.
 * @param array $options
 * @return array The users data
 */
function get_user_by_username($username, $options=array())
{
	global $mybb, $db;

	$username = $db->escape_string(my_strtolower($username));

	if(!isset($options['username_method']))
	{
		$options['username_method'] = 0;
	}

	switch($db->type)
	{
		case 'mysql':
		case 'mysqli':
			$field = 'username';
			$efield = 'email';
			break;
		default:
			$field = 'LOWER(username)';
			$efield = 'LOWER(email)';
			break;
	}

	switch($options['username_method'])
	{
		case 1:
			$sqlwhere = "{$efield}='{$username}'";
			break;
		case 2:
			$sqlwhere = "{$field}='{$username}' OR {$efield}='{$username}'";
			break;
		default:
			$sqlwhere = "{$field}='{$username}'";
			break;
	}

	$fields = array('uid');
	if(isset($options['fields']))
	{
		$fields = array_merge((array)$options['fields'], $fields);
	}

	$query = $db->simple_select('users', implode(',', array_unique($fields)), $sqlwhere, array('limit' => 1));

	if(isset($options['exists']))
	{
		return (bool)$db->num_rows($query);
	}

	return $db->fetch_array($query);
}

/**
 * Get the forum of a specific forum id.
 *
 * @param int $fid The forum id of the forum.
 * @param int $active_override (Optional) If set to 1, will override the active forum status
 * @return array|bool The database row of a forum. False on failure
 */
function get_forum($fid, $active_override=0)
{
	global $cache;
	static $forum_cache;

	if(!isset($forum_cache) || is_array($forum_cache))
	{
		$forum_cache = $cache->read("forums");
	}

	if(empty($forum_cache[$fid]))
	{
		return false;
	}

	if($active_override != 1)
	{
		$parents = explode(",", $forum_cache[$fid]['parentlist']);
		if(is_array($parents))
		{
			foreach($parents as $parent)
			{
				if($forum_cache[$parent]['active'] == 0)
				{
					return false;
				}
			}
		}
	}

	return $forum_cache[$fid];
}

/**
 * Get the thread of a thread id.
 *
 * @param int $tid The thread id of the thread.
 * @param boolean $recache Whether or not to recache the thread.
 * @return array|bool The database row of the thread. False on failure
 */
function get_thread($tid, $recache = false)
{
	global $db;
	static $thread_cache;

	$tid = (int)$tid;

	if(isset($thread_cache[$tid]) && !$recache)
	{
		return $thread_cache[$tid];
	}
	else
	{
		$query = $db->simple_select("threads", "*", "tid = '{$tid}'");
		$thread = $db->fetch_array($query);

		if($thread)
		{
			$thread_cache[$tid] = $thread;
			return $thread;
		}
		else
		{
			$thread_cache[$tid] = false;
			return false;
		}
	}
}

/**
 * Get the post of a post id.
 *
 * @param int $pid The post id of the post.
 * @return array|bool The database row of the post. False on failure
 */
function get_post($pid)
{
	global $db;
	static $post_cache;

	$pid = (int)$pid;

	if(isset($post_cache[$pid]))
	{
		return $post_cache[$pid];
	}
	else
	{
		$query = $db->simple_select("posts", "*", "pid = '{$pid}'");
		$post = $db->fetch_array($query);

		if($post)
		{
			$post_cache[$pid] = $post;
			return $post;
		}
		else
		{
			$post_cache[$pid] = false;
			return false;
		}
	}
}

/**
 * Get inactivate forums.
 *
 * @return string The comma separated values of the inactivate forum.
 */
function get_inactive_forums()
{
	global $forum_cache, $cache;

	if(!$forum_cache)
	{
		cache_forums();
	}

	$inactive = array();

	foreach($forum_cache as $fid => $forum)
	{
		if($forum['active'] == 0)
		{
			$inactive[] = $fid;
			foreach($forum_cache as $fid1 => $forum1)
			{
				if(my_strpos(",".$forum1['parentlist'].",", ",".$fid.",") !== false && !in_array($fid1, $inactive))
				{
					$inactive[] = $fid1;
				}
			}
		}
	}

	$inactiveforums = implode(",", $inactive);

	return $inactiveforums;
}

/**
 * Checks to make sure a user has not tried to login more times than permitted
 *
 * @param bool $fatal (Optional) Stop execution if it finds an error with the login. Default is True
 * @return bool|int Number of logins when success, false if failed.
 */
function login_attempt_check($uid = 0, $fatal = true)
{
	global $mybb, $lang, $db;

	$attempts = array();
	$uid = (int)$uid;
	$now = TIME_NOW;

	// Get this user's login attempts and eventual lockout, if a uid is provided
	if($uid > 0)
	{
		$query = $db->simple_select("users", "loginattempts, loginlockoutexpiry", "uid='{$uid}'", 1);
		$attempts = $db->fetch_array($query);

		if($attempts['loginattempts'] <= 0)
		{
			return 0;
		}
	}
	// This user has a cookie lockout, show waiting time
	elseif($mybb->cookies['lockoutexpiry'] && $mybb->cookies['lockoutexpiry'] > $now)
	{	
		if($fatal)
		{
			$secsleft = (int)($mybb->cookies['lockoutexpiry'] - $now);
			$hoursleft = floor($secsleft / 3600);
			$minsleft = floor(($secsleft / 60) % 60);
			$secsleft = floor($secsleft % 60);

			error($lang->sprintf($lang->failed_login_wait, $hoursleft, $minsleft, $secsleft));
		}

		return false;
	}

	if($mybb->settings['failedlogincount'] > 0 && $attempts['loginattempts'] >= $mybb->settings['failedlogincount'])
	{
		// Set the expiry dateline if not set yet
		if($attempts['loginlockoutexpiry'] == 0)
		{
			$attempts['loginlockoutexpiry'] = $now + ((int)$mybb->settings['failedlogintime'] * 60);

			// Add a cookie lockout. This is used to prevent access to the login page immediately.
			// A deep lockout is issued if he tries to login into a locked out account
			my_setcookie('lockoutexpiry', $attempts['loginlockoutexpiry']);

			$db->update_query("users", array(
				"loginlockoutexpiry" => $attempts['loginlockoutexpiry']
			), "uid='{$uid}'");
		}

		if(empty($mybb->cookies['lockoutexpiry']))
		{
			$failedtime = $attempts['loginlockoutexpiry'];
		}
		else
		{
			$failedtime = $mybb->cookies['lockoutexpiry'];
		}

		// Are we still locked out?
		if($attempts['loginlockoutexpiry'] > $now)
		{	
			if($fatal)
			{
				$secsleft = (int)($attempts['loginlockoutexpiry'] - $now);
				$hoursleft = floor($secsleft / 3600);
				$minsleft = floor(($secsleft / 60) % 60);
				$secsleft = floor($secsleft % 60);

				error($lang->sprintf($lang->failed_login_wait, $hoursleft, $minsleft, $secsleft));
			}

			return false;
		}
		// Unlock if enough time has passed
		else {

			if($uid > 0)
			{
				$db->update_query("users", array(
					"loginattempts" => 0,
					"loginlockoutexpiry" => 0
				), "uid='{$uid}'");
			}

			// Wipe the cookie, no matter if a guest or a member
			my_unsetcookie('lockoutexpiry');

			return 0;
		}
	}

	// User can attempt another login
	return $attempts['loginattempts'];
}

/**
 * Validates the format of an email address.
 *
 * @param string $email The string to check.
 * @return boolean True when valid, false when invalid.
 */
function validate_email_format($email)
{
	return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Checks to see if the email is already in use by another
 *
 * @param string $email The email to check.
 * @param int $uid User ID of the user (updating only)
 * @return boolean True when in use, false when not.
 */
function email_already_in_use($email, $uid=0)
{
	global $db;

	$uid_string = "";
	if($uid)
	{
		$uid_string = " AND uid != '".(int)$uid."'";
	}
	$query = $db->simple_select("users", "COUNT(email) as emails", "email = '".$db->escape_string($email)."'{$uid_string}");

	if($db->fetch_field($query, "emails") > 0)
	{
		return true;
	}

	return false;
}

/**
 * Rebuilds settings.php
 *
 */
function rebuild_settings()
{
	global $db, $mybb;

	$query = $db->simple_select("settings", "value, name", "", array(
		'order_by' => 'title',
		'order_dir' => 'ASC',
	));

	$settings = '';
	while($setting = $db->fetch_array($query))
	{
		$mybb->settings[$setting['name']] = $setting['value'];
		$setting['value'] = addcslashes($setting['value'], '\\"$');
		$settings .= "\$settings['{$setting['name']}'] = \"{$setting['value']}\";\n";
	}

	$settings = "<"."?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n$settings\n";

	file_put_contents(MYBB_ROOT.'inc/settings.php', $settings, LOCK_EX);

	$GLOBALS['settings'] = &$mybb->settings;
}

/**
 * Build a PREG compatible array of search highlight terms to replace in posts.
 *
 * @param string $terms Incoming terms to highlight
 * @return array PREG compatible array of terms
 */
function build_highlight_array($terms)
{
	global $mybb;

	if($mybb->settings['minsearchword'] < 1)
	{
		$mybb->settings['minsearchword'] = 3;
	}

	if(is_array($terms))
	{
		$terms = implode(' ', $terms);
	}

	// Strip out any characters that shouldn't be included
	$bad_characters = array(
		"(",
		")",
		"+",
		"-",
		"~"
	);
	$terms = str_replace($bad_characters, '', $terms);

	// Check if this is a "series of words" - should be treated as an EXACT match
	if(my_strpos($terms, "\"") !== false)
	{
		$inquote = false;
		$terms = explode("\"", $terms);
		$words = array();
		foreach($terms as $phrase)
		{
			$phrase = htmlspecialchars_uni($phrase);
			if($phrase != "")
			{
				if($inquote)
				{
					$words[] = trim($phrase);
				}
				else
				{
					$split_words = preg_split("#\s{1,}#", $phrase, -1);
					if(!is_array($split_words))
					{
						continue;
					}
					foreach($split_words as $word)
					{
						if(!$word || strlen($word) < $mybb->settings['minsearchword'])
						{
							continue;
						}
						$words[] = trim($word);
					}
				}
			}
			$inquote = !$inquote;
		}
	}
	// Otherwise just a simple search query with no phrases
	else
	{
		$terms = htmlspecialchars_uni($terms);
		$split_words = preg_split("#\s{1,}#", $terms, -1);
		if(is_array($split_words))
		{
			foreach($split_words as $word)
			{
				if(!$word || strlen($word) < $mybb->settings['minsearchword'])
				{
					continue;
				}
				$words[] = trim($word);
			}
		}
	}

	if(!is_array($words))
	{
		return false;
	}

	// Sort the word array by length. Largest terms go first and work their way down to the smallest term.
	// This resolves problems like "test tes" where "tes" will be highlighted first, then "test" can't be highlighted because of the changed html
	usort($words, 'build_highlight_array_sort');

	// Loop through our words to build the PREG compatible strings
	foreach($words as $word)
	{
		$word = trim($word);

		$word = my_strtolower($word);

		// Special boolean operators should be stripped
		if($word == "" || $word == "or" || $word == "not" || $word == "and")
		{
			continue;
		}

		// Now make PREG compatible
		$find = "#(?!<.*?)(".preg_quote($word, "#").")(?![^<>]*?>)#ui";
		$replacement = "<span class=\"highlight\" style=\"padding-left: 0px; padding-right: 0px;\">$1</span>";
		$highlight_cache[$find] = $replacement;
	}

	return $highlight_cache;
}

/**
 * Sort the word array by length. Largest terms go first and work their way down to the smallest term.
 *
 * @param string $a First word.
 * @param string $b Second word.
 * @return integer Result of comparison function.
 */
function build_highlight_array_sort($a, $b)
{
	return strlen($b) - strlen($a);
}

/**
 * Converts a decimal reference of a character to its UTF-8 equivalent
 * (Code by Anne van Kesteren, http://annevankesteren.nl/2005/05/character-references)
 *
 * @param int $src Decimal value of a character reference
 * @return string|bool
 */
function dec_to_utf8($src)
{
	$dest = '';

	if($src < 0)
	{
		return false;
	}
	elseif($src <= 0x007f)
	{
		$dest .= chr($src);
	}
	elseif($src <= 0x07ff)
	{
		$dest .= chr(0xc0 | ($src >> 6));
		$dest .= chr(0x80 | ($src & 0x003f));
	}
	elseif($src <= 0xffff)
	{
		$dest .= chr(0xe0 | ($src >> 12));
		$dest .= chr(0x80 | (($src >> 6) & 0x003f));
		$dest .= chr(0x80 | ($src & 0x003f));
	}
	elseif($src <= 0x10ffff)
	{
		$dest .= chr(0xf0 | ($src >> 18));
		$dest .= chr(0x80 | (($src >> 12) & 0x3f));
		$dest .= chr(0x80 | (($src >> 6) & 0x3f));
		$dest .= chr(0x80 | ($src & 0x3f));
	}
	else
	{
		// Out of range
		return false;
	}

	return $dest;
}

/**
 * Checks if a username has been disallowed for registration/use.
 *
 * @param string $username The username
 * @param boolean $update_lastuse True if the 'last used' dateline should be updated if a match is found.
 * @return boolean True if banned, false if not banned
 */
function is_banned_username($username, $update_lastuse=false)
{
	global $db;
	$query = $db->simple_select('banfilters', 'filter, fid', "type='2'");
	while($banned_username = $db->fetch_array($query))
	{
		// Make regular expression * match
		$banned_username['filter'] = str_replace('\*', '(.*)', preg_quote($banned_username['filter'], '#'));
		if(preg_match("#(^|\b){$banned_username['filter']}($|\b)#i", $username))
		{
			// Updating last use
			if($update_lastuse == true)
			{
				$db->update_query("banfilters", array("lastuse" => TIME_NOW), "fid='{$banned_username['fid']}'");
			}
			return true;
		}
	}
	// Still here - good username
	return false;
}

/**
 * Check if a specific email address has been banned.
 *
 * @param string $email The email address.
 * @param boolean $update_lastuse True if the 'last used' dateline should be updated if a match is found.
 * @return boolean True if banned, false if not banned
 */
function is_banned_email($email, $update_lastuse=false)
{
	global $cache, $db;

	$banned_cache = $cache->read("bannedemails");

	if($banned_cache === false)
	{
		// Failed to read cache, see if we can rebuild it
		$cache->update_bannedemails();
		$banned_cache = $cache->read("bannedemails");
	}

	if(is_array($banned_cache) && !empty($banned_cache))
	{
		foreach($banned_cache as $banned_email)
		{
			// Make regular expression * match
			$banned_email['filter'] = str_replace('\*', '(.*)', preg_quote($banned_email['filter'], '#'));

			if(preg_match("#{$banned_email['filter']}#i", $email))
			{
				// Updating last use
				if($update_lastuse == true)
				{
					$db->update_query("banfilters", array("lastuse" => TIME_NOW), "fid='{$banned_email['fid']}'");
				}
				return true;
			}
		}
	}

	// Still here - good email
	return false;
}

/**
 * Checks if a specific IP address has been banned.
 *
 * @param string $ip_address The IP address.
 * @param boolean $update_lastuse True if the 'last used' dateline should be updated if a match is found.
 * @return boolean True if banned, false if not banned.
 */
function is_banned_ip($ip_address, $update_lastuse=false)
{
	global $db, $cache;

	$banned_ips = $cache->read("bannedips");
	if(!is_array($banned_ips))
	{
		return false;
	}

	$ip_address = my_inet_pton($ip_address);
	foreach($banned_ips as $banned_ip)
	{
		if(!$banned_ip['filter'])
		{
			continue;
		}

		$banned = false;

		$ip_range = fetch_ip_range($banned_ip['filter']);
		if(is_array($ip_range))
		{
			if(strcmp($ip_range[0], $ip_address) <= 0 && strcmp($ip_range[1], $ip_address) >= 0)
			{
				$banned = true;
			}
		}
		elseif($ip_address == $ip_range)
		{
			$banned = true;
		}
		if($banned)
		{
			// Updating last use
			if($update_lastuse == true)
			{
				$db->update_query("banfilters", array("lastuse" => TIME_NOW), "fid='{$banned_ip['fid']}'");
			}
			return true;
		}
	}

	// Still here - good ip
	return false;
}

/**
 * Returns an array of supported timezones
 *
 * @return string[] Key is timezone offset, Value the language description
 */
function get_supported_timezones()
{
	global $lang;
	$timezones = array(
		"-12" => $lang->timezone_gmt_minus_1200,
		"-11" => $lang->timezone_gmt_minus_1100,
		"-10" => $lang->timezone_gmt_minus_1000,
		"-9.5" => $lang->timezone_gmt_minus_950,
		"-9" => $lang->timezone_gmt_minus_900,
		"-8" => $lang->timezone_gmt_minus_800,
		"-7" => $lang->timezone_gmt_minus_700,
		"-6" => $lang->timezone_gmt_minus_600,
		"-5" => $lang->timezone_gmt_minus_500,
		"-4.5" => $lang->timezone_gmt_minus_450,
		"-4" => $lang->timezone_gmt_minus_400,
		"-3.5" => $lang->timezone_gmt_minus_350,
		"-3" => $lang->timezone_gmt_minus_300,
		"-2" => $lang->timezone_gmt_minus_200,
		"-1" => $lang->timezone_gmt_minus_100,
		"0" => $lang->timezone_gmt,
		"1" => $lang->timezone_gmt_100,
		"2" => $lang->timezone_gmt_200,
		"3" => $lang->timezone_gmt_300,
		"3.5" => $lang->timezone_gmt_350,
		"4" => $lang->timezone_gmt_400,
		"4.5" => $lang->timezone_gmt_450,
		"5" => $lang->timezone_gmt_500,
		"5.5" => $lang->timezone_gmt_550,
		"5.75" => $lang->timezone_gmt_575,
		"6" => $lang->timezone_gmt_600,
		"6.5" => $lang->timezone_gmt_650,
		"7" => $lang->timezone_gmt_700,
		"8" => $lang->timezone_gmt_800,
		"8.5" => $lang->timezone_gmt_850,
		"8.75" => $lang->timezone_gmt_875,
		"9" => $lang->timezone_gmt_900,
		"9.5" => $lang->timezone_gmt_950,
		"10" => $lang->timezone_gmt_1000,
		"10.5" => $lang->timezone_gmt_1050,
		"11" => $lang->timezone_gmt_1100,
		"11.5" => $lang->timezone_gmt_1150,
		"12" => $lang->timezone_gmt_1200,
		"12.75" => $lang->timezone_gmt_1275,
		"13" => $lang->timezone_gmt_1300,
		"14" => $lang->timezone_gmt_1400
	);
	return $timezones;
}

/**
 * Build a time zone selection list.
 *
 * @param string $name The name of the select
 * @param int $selected The selected time zone (defaults to GMT)
 * @param boolean $short True to generate a "short" list with just timezone and current time
 * @return string
 */
function build_timezone_select($name, $selected=0, $short=false)
{
	global $mybb, $lang, $templates;

	$timezones = get_supported_timezones();

	$selected = str_replace("+", "", $selected);
	foreach($timezones as $timezone => $label)
	{
		$selected_add = "";
		if($selected == $timezone)
		{
			$selected_add = " selected=\"selected\"";
		}
		if($short == true)
		{
			$label = '';
			if($timezone != 0)
			{
				$label = $timezone;
				if($timezone > 0)
				{
					$label = "+{$label}";
				}
				if(strpos($timezone, ".") !== false)
				{
					$label = str_replace(".", ":", $label);
					$label = str_replace(":5", ":30", $label);
					$label = str_replace(":75", ":45", $label);
				}
				else
				{
					$label .= ":00";
				}
			}
			$time_in_zone = my_date($mybb->settings['timeformat'], TIME_NOW, $timezone);
			$label = $lang->sprintf($lang->timezone_gmt_short, $label." ", $time_in_zone);
		}

		eval("\$timezone_option .= \"".$templates->get("usercp_options_timezone_option")."\";");
	}

	eval("\$select = \"".$templates->get("usercp_options_timezone")."\";");
	return $select;
}

/**
 * Fetch the contents of a remote file.
 *
 * @param string $url The URL of the remote file
 * @param array $post_data The array of post data
 * @param int $max_redirects Number of maximum redirects
 * @return string|bool The remote file contents. False on failure
 */
function fetch_remote_file($url, $post_data=array(), $max_redirects=20)
{
	global $mybb, $config;

	if(!my_validate_url($url, true))
	{
		return false;
	}

	$url_components = @parse_url($url);

	if(!isset($url_components['scheme']))
	{
		$url_components['scheme'] = 'https';
	}
	if(!isset($url_components['port']))
	{
		$url_components['port'] = $url_components['scheme'] == 'https' ? 443 : 80;
	}

	if(
		!$url_components ||
		empty($url_components['host']) ||
		(!empty($url_components['scheme']) && !in_array($url_components['scheme'], array('http', 'https'))) ||
		(!in_array($url_components['port'], array(80, 8080, 443))) ||
		(!empty($config['disallowed_remote_hosts']) && in_array($url_components['host'], $config['disallowed_remote_hosts']))
	)
	{
		return false;
	}

	$addresses = get_ip_by_hostname($url_components['host']);
	$destination_address = $addresses[0];

	if(!empty($config['disallowed_remote_addresses']))
	{
		foreach($config['disallowed_remote_addresses'] as $disallowed_address)
		{
			$ip_range = fetch_ip_range($disallowed_address);

			$packed_address = my_inet_pton($destination_address);

			if(is_array($ip_range))
			{
				if(strcmp($ip_range[0], $packed_address) <= 0 && strcmp($ip_range[1], $packed_address) >= 0)
				{
					return false;
				}
			}
			elseif($destination_address == $disallowed_address)
			{
				return false;
			}
		}
	}

	$post_body = '';
	if(!empty($post_data))
	{
		foreach($post_data as $key => $val)
		{
			$post_body .= '&'.urlencode($key).'='.urlencode($val);
		}
		$post_body = ltrim($post_body, '&');
	}

	if(function_exists("curl_init"))
	{
		$fetch_header = $max_redirects > 0;

		$ch = curl_init();

		$curlopt = array(
			CURLOPT_URL => $url,
			CURLOPT_HEADER => $fetch_header,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FOLLOWLOCATION => 0,
		);

		if($ca_bundle_path = get_ca_bundle_path())
		{
			$curlopt[CURLOPT_SSL_VERIFYPEER] = 1;
			$curlopt[CURLOPT_CAINFO] = $ca_bundle_path;
		}
		else
		{
			$curlopt[CURLOPT_SSL_VERIFYPEER] = 0;
		}

		$curl_version_info = curl_version();
		$curl_version = $curl_version_info['version'];

		if(version_compare(PHP_VERSION, '7.0.7', '>=') && version_compare($curl_version, '7.49', '>='))
		{
			// CURLOPT_CONNECT_TO
			$curlopt[10243] = array(
				$url_components['host'].':'.$url_components['port'].':'.$destination_address
			);
		}
		elseif(version_compare(PHP_VERSION, '5.5', '>=') && version_compare($curl_version, '7.21.3', '>='))
		{
			// CURLOPT_RESOLVE
			$curlopt[10203] = array(
				$url_components['host'].':'.$url_components['port'].':'.$destination_address
			);
		}

		if(!empty($post_body))
		{
			$curlopt[CURLOPT_POST] = 1;
			$curlopt[CURLOPT_POSTFIELDS] = $post_body;
		}

		curl_setopt_array($ch, $curlopt);

		$response = curl_exec($ch);

		if($fetch_header)
		{
			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$header = substr($response, 0, $header_size);
			$body = substr($response, $header_size);

			if(in_array(curl_getinfo($ch, CURLINFO_HTTP_CODE), array(301, 302)))
			{
				preg_match('/Location:(.*?)(?:\n|$)/', $header, $matches);

				if($matches)
				{
					$data = fetch_remote_file(trim(array_pop($matches)), $post_data, --$max_redirects);
				}
			}
			else
			{
				$data = $body;
			}
		}
		else
		{
			$data = $response;
		}

		curl_close($ch);
		return $data;
	}
	else if(function_exists("fsockopen"))
	{
		if(!isset($url_components['path']))
		{
			$url_components['path'] = "/";
		}
		if(isset($url_components['query']))
		{
			$url_components['path'] .= "?{$url_components['query']}";
		}

		$scheme = '';

		if($url_components['scheme'] == 'https')
		{
			$scheme = 'ssl://';
			if($url_components['port'] == 80)
			{
				$url_components['port'] = 443;
			}
		}

		if(function_exists('stream_context_create'))
		{
			if($url_components['scheme'] == 'https' && $ca_bundle_path = get_ca_bundle_path())
			{
				$context = stream_context_create(array(
					'ssl' => array(
						'verify_peer' => true,
						'verify_peer_name' => true,
						'peer_name' => $url_components['host'],
						'cafile' => $ca_bundle_path,
					),
				));
			}
			else
			{
				$context = stream_context_create(array(
					'ssl' => array(
						'verify_peer' => false,
						'verify_peer_name' => false,
					),
				));
			}

			$fp = @stream_socket_client($scheme.$destination_address.':'.(int)$url_components['port'], $error_no, $error, 10, STREAM_CLIENT_CONNECT, $context);
		}
		else
		{
			$fp = @fsockopen($scheme.$url_components['host'], (int)$url_components['port'], $error_no, $error, 10);
		}

		@stream_set_timeout($fp, 10);
		if(!$fp)
		{
			return false;
		}
		$headers = array();
		if(!empty($post_body))
		{
			$headers[] = "POST {$url_components['path']} HTTP/1.0";
			$headers[] = "Content-Length: ".strlen($post_body);
			$headers[] = "Content-Type: application/x-www-form-urlencoded";
		}
		else
		{
			$headers[] = "GET {$url_components['path']} HTTP/1.0";
		}

		$headers[] = "Host: {$url_components['host']}";
		$headers[] = "Connection: Close";
		$headers[] = '';

		if(!empty($post_body))
		{
			$headers[] = $post_body;
		}
		else
		{
			// If we have no post body, we need to add an empty element to make sure we've got \r\n\r\n before the (non-existent) body starts
			$headers[] = '';
		}

		$headers = implode("\r\n", $headers);
		if(!@fwrite($fp, $headers))
		{
			return false;
		}

		$data = null;

		while(!feof($fp))
		{
			$data .= fgets($fp, 12800);
		}
		fclose($fp);

		$data = explode("\r\n\r\n", $data, 2);

		$header = $data[0];
		$status_line = current(explode("\n\n", $header, 1));
		$body = $data[1];

		if($max_redirects > 0 && (strstr($status_line, ' 301 ') || strstr($status_line, ' 302 ')))
		{
			preg_match('/Location:(.*?)(?:\n|$)/', $header, $matches);

			if($matches)
			{
				$data = fetch_remote_file(trim(array_pop($matches)), $post_data, --$max_redirects);
			}
		}
		else
		{
			$data = $body;
		}

		return $data;
	}
	else
	{
		return false;
	}
}

/**
 * Resolves a hostname into a set of IP addresses.
 *
 * @param string $hostname The hostname to be resolved
 * @return array|bool The resulting IP addresses. False on failure
 */
function get_ip_by_hostname($hostname)
{
	$addresses = @gethostbynamel($hostname);

	if(!$addresses)
	{
		$result_set = @dns_get_record($hostname, DNS_A | DNS_AAAA);

		if($result_set)
		{
			$addresses = array_column($result_set, 'ip');
		}
		else
		{
			return false;
		}
	}

	return $addresses;
}

/**
 * Returns the location of the CA bundle defined in the PHP configuration.
 *
 * @return string|bool The location of the CA bundle, false if not set
 */
function get_ca_bundle_path()
{
	if($path = ini_get('openssl.cafile'))
	{
		return $path;
	}
	if($path = ini_get('curl.cainfo'))
	{
		return $path;
	}

	return false;
}

/**
 * Checks if a particular user is a super administrator.
 *
 * @param int $uid The user ID to check against the list of super admins
 * @return boolean True if a super admin, false if not
 */
function is_super_admin($uid)
{
	static $super_admins;

	if(!isset($super_admins))
	{
		global $mybb;
		$super_admins = str_replace(" ", "", $mybb->config['super_admins']);
	}

	if(my_strpos(",{$super_admins},", ",{$uid},") === false)
	{
		return false;
	}
	else
	{
		return true;
	}
}

/**
 * Checks if a user is a member of a particular group
 * Originates from frostschutz's PluginLibrary
 * github.com/frostschutz
 *
 * @param array|int|string A selection of groups (as array or comma seperated) to check or -1 for any group
 * @param bool|array|int False assumes the current user. Otherwise an user array or an id can be passed
 * @return array Array of groups specified in the first param to which the user belongs
 */
function is_member($groups, $user = false)
{
	global $mybb;

	if(empty($groups))
	{
		return array();
	}

	if($user == false)
	{
		$user = $mybb->user;
	}
	else if(!is_array($user))
	{
		// Assume it's a UID
		$user = get_user($user);
	}

	$memberships = array_map('intval', explode(',', $user['additionalgroups']));
	$memberships[] = $user['usergroup'];

	if(!is_array($groups))
	{
		if((int)$groups == -1)
		{
			return $memberships;
		}
		else
		{
			if(is_string($groups))
			{
				$groups = explode(',', $groups);
			}
			else
			{
				$groups = (array)$groups;
			}
		}
	}

	$groups = array_filter(array_map('intval', $groups));

	return array_intersect($groups, $memberships);
}

/**
 * Split a string based on the specified delimeter, ignoring said delimeter in escaped strings.
 * Ex: the "quick brown fox" jumped, could return 1 => the, 2 => quick brown fox, 3 => jumped
 *
 * @param string $delimeter The delimeter to split by
 * @param string $string The string to split
 * @param string $escape The escape character or string if we have one.
 * @return array Array of split string
 */
function escaped_explode($delimeter, $string, $escape="")
{
	$strings = array();
	$original = $string;
	$in_escape = false;
	if($escape)
	{
		if(is_array($escape))
		{
			function escaped_explode_escape($string)
			{
				return preg_quote($string, "#");
			}
			$escape_preg = "(".implode("|", array_map("escaped_explode_escape", $escape)).")";
		}
		else
		{
			$escape_preg = preg_quote($escape, "#");
		}
		$quoted_strings = preg_split("#(?<!\\\){$escape_preg}#", $string);
	}
	else
	{
		$quoted_strings = array($string);
	}
	foreach($quoted_strings as $string)
	{
		if($string != "")
		{
			if($in_escape)
			{
				$strings[] = trim($string);
			}
			else
			{
				$split_strings = explode($delimeter, $string);
				foreach($split_strings as $string)
				{
					if($string == "") continue;
					$strings[] = trim($string);
				}
			}
		}
		$in_escape = !$in_escape;
	}
	if(!count($strings))
	{
		return $original;
	}
	return $strings;
}

/**
 * DEPRECATED! Please use IPv6 compatible fetch_ip_range!
 * Fetch an IPv4 long formatted range for searching IPv4 IP addresses.
 *
 * @deprecated
 * @param string $ip The IP address to convert to a range based LONG
 * @return string|array If a full IP address is provided, the ip2long equivalent, otherwise an array of the upper & lower extremities of the IP
 */
function fetch_longipv4_range($ip)
{
	$ip_bits = explode(".", $ip);
	$ip_string1 = $ip_string2 = "";

	if($ip == "*")
	{
		return array(ip2long('0.0.0.0'), ip2long('255.255.255.255'));
	}

	if(strpos($ip, ".*") === false)
	{
		$ip = str_replace("*", "", $ip);
		if(count($ip_bits) == 4)
		{
			return ip2long($ip);
		}
		else
		{
			return array(ip2long($ip.".0"), ip2long($ip.".255"));
		}
	}
	// Wildcard based IP provided
	else
	{
		$sep = "";
		foreach($ip_bits as $piece)
		{
			if($piece == "*")
			{
				$ip_string1 .= $sep."0";
				$ip_string2 .= $sep."255";
			}
			else
			{
				$ip_string1 .= $sep.$piece;
				$ip_string2 .= $sep.$piece;
			}
			$sep = ".";
		}
		return array(ip2long($ip_string1), ip2long($ip_string2));
	}
}

/**
 * Fetch a list of ban times for a user account.
 *
 * @return array Array of ban times
 */
function fetch_ban_times()
{
	global $plugins, $lang;

	// Days-Months-Years
	$ban_times = array(
		"1-0-0" => "1 {$lang->day}",
		"2-0-0" => "2 {$lang->days}",
		"3-0-0" => "3 {$lang->days}",
		"4-0-0" => "4 {$lang->days}",
		"5-0-0" => "5 {$lang->days}",
		"6-0-0" => "6 {$lang->days}",
		"7-0-0" => "1 {$lang->week}",
		"14-0-0" => "2 {$lang->weeks}",
		"21-0-0" => "3 {$lang->weeks}",
		"0-1-0" => "1 {$lang->month}",
		"0-2-0" => "2 {$lang->months}",
		"0-3-0" => "3 {$lang->months}",
		"0-4-0" => "4 {$lang->months}",
		"0-5-0" => "5 {$lang->months}",
		"0-6-0" => "6 {$lang->months}",
		"0-0-1" => "1 {$lang->year}",
		"0-0-2" => "2 {$lang->years}"
	);

	$ban_times = $plugins->run_hooks("functions_fetch_ban_times", $ban_times);

	$ban_times['---'] = $lang->permanent;
	return $ban_times;
}

/**
 * Format a ban length in to a UNIX timestamp.
 *
 * @param string $date The ban length string
 * @param int $stamp The optional UNIX timestamp, if 0, current time is used.
 * @return int The UNIX timestamp when the ban will be lifted
 */
function ban_date2timestamp($date, $stamp=0)
{
	if($stamp == 0)
	{
		$stamp = TIME_NOW;
	}
	$d = explode('-', $date);
	$nowdate = date("H-j-n-Y", $stamp);
	$n = explode('-', $nowdate);
	$n[1] += $d[0];
	$n[2] += $d[1];
	$n[3] += $d[2];
	return mktime(date("G", $stamp), date("i", $stamp), 0, $n[2], $n[1], $n[3]);
}

/**
 * Expire old warnings in the database.
 *
 * @return bool
 */
function expire_warnings()
{
	global $warningshandler;

	if(!is_object($warningshandler))
	{
		require_once MYBB_ROOT.'inc/datahandlers/warnings.php';
		$warningshandler = new WarningsHandler('update');
	}

	return $warningshandler->expire_warnings();
}

/**
 * Custom chmod function to fix problems with hosts who's server configurations screw up umasks
 *
 * @param string $file The file to chmod
 * @param string $mode The mode to chmod(i.e. 0666)
 * @return bool
 */
function my_chmod($file, $mode)
{
	// Passing $mode as an octal number causes strlen and substr to return incorrect values. Instead pass as a string
	if(substr($mode, 0, 1) != '0' || strlen($mode) !== 4)
	{
		return false;
	}
	$old_umask = umask(0);

	// We convert the octal string to a decimal number because passing a octal string doesn't work with chmod
	// and type casting subsequently removes the prepended 0 which is needed for octal numbers
	$result = chmod($file, octdec($mode));
	umask($old_umask);
	return $result;
}

/**
 * Custom rmdir function to loop through an entire directory and delete all files/folders within
 *
 * @param string $path The path to the directory
 * @param array $ignore Any files you wish to ignore (optional)
 * @return bool
 */
function my_rmdir_recursive($path, $ignore=array())
{
	global $orig_dir;

	if(!isset($orig_dir))
	{
		$orig_dir = $path;
	}

	if(@is_dir($path) && !@is_link($path))
	{
		if($dh = @opendir($path))
		{
			while(($file = @readdir($dh)) !== false)
			{
				if($file == '.' || $file == '..' || $file == '.svn' || in_array($path.'/'.$file, $ignore) || !my_rmdir_recursive($path.'/'.$file))
				{
					continue;
				}
			}
		   @closedir($dh);
		}

		// Are we done? Don't delete the main folder too and return true
		if($path == $orig_dir)
		{
			return true;
		}

		return @rmdir($path);
	}

	return @unlink($path);
}

/**
 * Counts the number of subforums in a array([pid][disporder][fid]) starting from the pid
 *
 * @param array $array The array of forums
 * @return integer The number of sub forums
 */
function subforums_count($array=array())
{
	$count = 0;
	foreach($array as $array2)
	{
		$count += count($array2);
	}

	return $count;
}

/**
 * DEPRECATED! Please use IPv6 compatible my_inet_pton!
 * Fix for PHP's ip2long to guarantee a 32-bit signed integer value is produced (this is aimed
 * at 64-bit versions of PHP)
 *
 * @deprecated
 * @param string $ip The IP to convert
 * @return integer IP in 32-bit signed format
 */
function my_ip2long($ip)
{
	$ip_long = ip2long($ip);

	if(!$ip_long)
	{
		$ip_long = sprintf("%u", ip2long($ip));

		if(!$ip_long)
		{
			return 0;
		}
	}

	if($ip_long >= 2147483648) // Won't occur on 32-bit PHP
	{
		$ip_long -= 4294967296;
	}

	return $ip_long;
}

/**
 * DEPRECATED! Please use IPv6 compatible my_inet_ntop!
 * As above, fix for PHP's long2ip on 64-bit versions
 *
 * @deprecated
 * @param integer $long The IP to convert (will accept 64-bit IPs as well)
 * @return string IP in IPv4 format
 */
function my_long2ip($long)
{
	// On 64-bit machines is_int will return true. On 32-bit it will return false
	if($long < 0 && is_int(2147483648))
	{
		// We have a 64-bit system
		$long += 4294967296;
	}
	return long2ip($long);
}

/**
 * Converts a human readable IP address to its packed in_addr representation
 *
 * @param string $ip The IP to convert
 * @return string IP in 32bit or 128bit binary format
 */
function my_inet_pton($ip)
{
	if(function_exists('inet_pton'))
	{
		return @inet_pton($ip);
	}
	else
	{
		/**
		 * Replace inet_pton()
		 *
		 * @category    PHP
		 * @package     PHP_Compat
		 * @license     LGPL - http://www.gnu.org/licenses/lgpl.html
		 * @copyright   2004-2007 Aidan Lister <aidan@php.net>, Arpad Ray <arpad@php.net>
		 * @link        http://php.net/inet_pton
		 * @author      Arpad Ray <arpad@php.net>
		 * @version     $Revision: 269597 $
		 */
		$r = ip2long($ip);
		if($r !== false && $r != -1)
		{
			return pack('N', $r);
		}

		$delim_count = substr_count($ip, ':');
		if($delim_count < 1 || $delim_count > 7)
		{
			return false;
		}

		$r = explode(':', $ip);
		$rcount = count($r);
		if(($doub = array_search('', $r, 1)) !== false)
		{
			$length = (!$doub || $doub == $rcount - 1 ? 2 : 1);
			array_splice($r, $doub, $length, array_fill(0, 8 + $length - $rcount, 0));
		}

		$r = array_map('hexdec', $r);
		array_unshift($r, 'n*');
		$r = call_user_func_array('pack', $r);

		return $r;
	}
}

/**
 * Converts a packed internet address to a human readable representation
 *
 * @param string $ip IP in 32bit or 128bit binary format
 * @return string IP in human readable format
 */
function my_inet_ntop($ip)
{
	if(function_exists('inet_ntop'))
	{
		return @inet_ntop($ip);
	}
	else
	{
		/**
		 * Replace inet_ntop()
		 *
		 * @category    PHP
		 * @package     PHP_Compat
		 * @license     LGPL - http://www.gnu.org/licenses/lgpl.html
		 * @copyright   2004-2007 Aidan Lister <aidan@php.net>, Arpad Ray <arpad@php.net>
		 * @link        http://php.net/inet_ntop
		 * @author      Arpad Ray <arpad@php.net>
		 * @version     $Revision: 269597 $
		 */
		switch(strlen($ip))
		{
			case 4:
				list(,$r) = unpack('N', $ip);
				return long2ip($r);
			case 16:
				$r = substr(chunk_split(bin2hex($ip), 4, ':'), 0, -1);
				$r = preg_replace(
					array('/(?::?\b0+\b:?){2,}/', '/\b0+([^0])/e'),
					array('::', '(int)"$1"?"$1":"0$1"'),
					$r);
				return $r;
		}
		return false;
	}
}

/**
 * Fetch an binary formatted range for searching IPv4 and IPv6 IP addresses.
 *
 * @param string $ipaddress The IP address to convert to a range
 * @return string|array|bool If a full IP address is provided, the in_addr representation, otherwise an array of the upper & lower extremities of the IP. False on failure
 */
function fetch_ip_range($ipaddress)
{
	// Wildcard
	if(strpos($ipaddress, '*') !== false)
	{
		if(strpos($ipaddress, ':') !== false)
		{
			// IPv6
			$upper = str_replace('*', 'ffff', $ipaddress);
			$lower = str_replace('*', '0', $ipaddress);
		}
		else
		{
			// IPv4
			$ip_bits = count(explode('.', $ipaddress));
			if($ip_bits < 4)
			{
				// Support for 127.0.*
				$replacement = str_repeat('.*', 4-$ip_bits);
				$ipaddress = substr_replace($ipaddress, $replacement, strrpos($ipaddress, '*')+1, 0);
			}
			$upper = str_replace('*', '255', $ipaddress);
			$lower = str_replace('*', '0', $ipaddress);
		}
		$upper = my_inet_pton($upper);
		$lower = my_inet_pton($lower);
		if($upper === false || $lower === false)
		{
			return false;
		}
		return array($lower, $upper);
	}
	// CIDR notation
	elseif(strpos($ipaddress, '/') !== false)
	{
		$ipaddress = explode('/', $ipaddress);
		$ip_address = $ipaddress[0];
		$ip_range = (int)$ipaddress[1];

		if(empty($ip_address) || empty($ip_range))
		{
			// Invalid input
			return false;
		}
		else
		{
			$ip_address = my_inet_pton($ip_address);

			if(!$ip_address)
			{
				// Invalid IP address
				return false;
			}
		}

		/**
		 * Taken from: https://github.com/NewEraCracker/php_work/blob/master/ipRangeCalculate.php
		 * Author: NewEraCracker
		 * License: Public Domain
		 */

		// Pack IP, Set some vars
		$ip_pack = $ip_address;
		$ip_pack_size = strlen($ip_pack);
		$ip_bits_size = $ip_pack_size*8;

		// IP bits (lots of 0's and 1's)
		$ip_bits = '';
		for($i = 0; $i < $ip_pack_size; $i = $i+1)
		{
			$bit = decbin(ord($ip_pack[$i]));
			$bit = str_pad($bit, 8, '0', STR_PAD_LEFT);
			$ip_bits .= $bit;
		}

		// Significative bits (from the ip range)
		$ip_bits = substr($ip_bits, 0, $ip_range);

		// Some calculations
		$ip_lower_bits = str_pad($ip_bits, $ip_bits_size, '0', STR_PAD_RIGHT);
		$ip_higher_bits = str_pad($ip_bits, $ip_bits_size, '1', STR_PAD_RIGHT);

		// Lower IP
		$ip_lower_pack = '';
		for($i=0; $i < $ip_bits_size; $i=$i+8)
		{
			$chr = substr($ip_lower_bits, $i, 8);
			$chr = chr(bindec($chr));
			$ip_lower_pack .= $chr;
		}

		// Higher IP
		$ip_higher_pack = '';
		for($i=0; $i < $ip_bits_size; $i=$i+8)
		{
			$chr = substr($ip_higher_bits, $i, 8);
			$chr = chr( bindec($chr) );
			$ip_higher_pack .= $chr;
		}

		return array($ip_lower_pack, $ip_higher_pack);
	}
	// Just on IP address
	else
	{
		return my_inet_pton($ipaddress);
	}
}

/**
 * Time how long it takes for a particular piece of code to run. Place calls above & below the block of code.
 *
 * @return float The time taken
 */
function get_execution_time()
{
	static $time_start;

	$time = microtime(true);

	// Just starting timer, init and return
	if(!$time_start)
	{
		$time_start = $time;
		return;
	}
	// Timer has run, return execution time
	else
	{
		$total = $time-$time_start;
		if($total < 0) $total = 0;
		$time_start = 0;
		return $total;
	}
}

/**
 * Processes a checksum list on MyBB files and returns a result set
 *
 * @param string $path The base path
 * @param int $count The count of files
 * @return array The bad files
 */
function verify_files($path=MYBB_ROOT, $count=0)
{
	global $mybb, $checksums, $bad_verify_files;

	// We don't need to check these types of files
	$ignore = array(".", "..", ".svn", "config.php", "settings.php", "Thumb.db", "config.default.php", "lock", "htaccess.txt", "htaccess-nginx.txt", "logo.gif", "logo.png");
	$ignore_ext = array("attach");

	if(substr($path, -1, 1) == "/")
	{
		$path = substr($path, 0, -1);
	}

	if(!is_array($bad_verify_files))
	{
		$bad_verify_files = array();
	}

	// Make sure that we're in a directory and it's not a symbolic link
	if(@is_dir($path) && !@is_link($path))
	{
		if($dh = @opendir($path))
		{
			// Loop through all the files/directories in this directory
			while(($file = @readdir($dh)) !== false)
			{
				if(in_array($file, $ignore) || in_array(get_extension($file), $ignore_ext))
				{
					continue;
				}

				// Recurse through the directory tree
				if(is_dir($path."/".$file))
				{
					verify_files($path."/".$file, ($count+1));
					continue;
				}

				// We only need the last part of the path (from the MyBB directory to the file. i.e. inc/functions.php)
				$file_path = ".".str_replace(substr(MYBB_ROOT, 0, -1), "", $path)."/".$file;

				// Does this file even exist in our official list? Perhaps it's a plugin
				if(array_key_exists($file_path, $checksums))
				{
					$filename = $path."/".$file;
					$handle = fopen($filename, "rb");
					$hashingContext = hash_init('sha512');
					while(!feof($handle))
					{
						hash_update($hashingContext, fread($handle, 8192));
					}
					fclose($handle);

					$checksum = hash_final($hashingContext);

					// Does it match any of our hashes (unix/windows new lines taken into consideration with the hashes)
					if(!in_array($checksum, $checksums[$file_path]))
					{
						$bad_verify_files[] = array("status" => "changed", "path" => $file_path);
					}
				}
				unset($checksums[$file_path]);
			}
		   @closedir($dh);
		}
	}

	if($count == 0)
	{
		if(!empty($checksums))
		{
			foreach($checksums as $file_path => $hashes)
			{
				if(in_array(basename($file_path), $ignore))
				{
					continue;
				}
				$bad_verify_files[] = array("status" => "missing", "path" => $file_path);
			}
		}
	}

	// uh oh
	if($count == 0)
	{
		return $bad_verify_files;
	}
}

/**
 * Returns a signed value equal to an integer
 *
 * @param int $int The integer
 * @return string The signed equivalent
 */
function signed($int)
{
	if($int < 0)
	{
		return "$int";
	}
	else
	{
		return "+$int";
	}
}

/**
 * Returns a securely generated seed
 *
 * @return string A secure binary seed
 */
function secure_binary_seed_rng($bytes)
{
	$output = null;

	if(version_compare(PHP_VERSION, '7.0', '>='))
	{
		try
		{
			$output = random_bytes($bytes);
		} catch (Exception $e) {
		}
	}

	if(strlen($output) < $bytes)
	{
		if(@is_readable('/dev/urandom') && ($handle = @fopen('/dev/urandom', 'rb')))
		{
			$output = @fread($handle, $bytes);
			@fclose($handle);
		}
	}
	else
	{
		return $output;
	}

	if(strlen($output) < $bytes)
	{
		if(function_exists('mcrypt_create_iv'))
		{
			if (DIRECTORY_SEPARATOR == '/')
			{
				$source = MCRYPT_DEV_URANDOM;
			}
			else
			{
				$source = MCRYPT_RAND;
			}

			$output = @mcrypt_create_iv($bytes, $source);
		}
	}
	else
	{
		return $output;
	}

	if(strlen($output) < $bytes)
	{
		if(function_exists('openssl_random_pseudo_bytes'))
		{
			// PHP <5.3.4 had a bug which makes that function unusable on Windows
			if ((DIRECTORY_SEPARATOR == '/') || version_compare(PHP_VERSION, '5.3.4', '>='))
			{
				$output = openssl_random_pseudo_bytes($bytes, $crypto_strong);
				if ($crypto_strong == false)
				{
					$output = null;
				}
			}
		}
	}
	else
	{
		return $output;
	}

	if(strlen($output) < $bytes)
	{
		if(class_exists('COM'))
		{
			try
			{
				$CAPI_Util = new COM('CAPICOM.Utilities.1');
				if(is_callable(array($CAPI_Util, 'GetRandom')))
				{
					$output = $CAPI_Util->GetRandom($bytes, 0);
				}
			} catch (Exception $e) {
			}
		}
	}
	else
	{
		return $output;
	}

	if(strlen($output) < $bytes)
	{
		// Close to what PHP basically uses internally to seed, but not quite.
		$unique_state = microtime().@getmypid();

		$rounds = ceil($bytes / 16);

		for($i = 0; $i < $rounds; $i++)
		{
			$unique_state = md5(microtime().$unique_state);
			$output .= md5($unique_state);
		}

		$output = substr($output, 0, ($bytes * 2));

		$output = pack('H*', $output);

		return $output;
	}
	else
	{
		return $output;
	}
}

/**
 * Returns a securely generated seed integer
 *
 * @return int An integer equivalent of a secure hexadecimal seed
 */
function secure_seed_rng()
{
	$bytes = PHP_INT_SIZE;

	do
	{

		$output = secure_binary_seed_rng($bytes);

		// convert binary data to a decimal number
		if ($bytes == 4)
		{
			$elements = unpack('i', $output);
			$output = abs($elements[1]);
		}
		else
		{
			$elements = unpack('N2', $output);
			$output = abs($elements[1] << 32 | $elements[2]);
		}

	} while($output > PHP_INT_MAX);

	return $output;
}

/**
 * Generates a cryptographically secure random number.
 *
 * @param int $min Optional lowest value to be returned (default: 0)
 * @param int $max Optional highest value to be returned (default: PHP_INT_MAX)
 */
function my_rand($min=0, $max=PHP_INT_MAX)
{
	// backward compatibility
	if($min === null || $max === null || $max < $min)
	{
		$min = 0;
		$max = PHP_INT_MAX;
	}

	if(version_compare(PHP_VERSION, '7.0', '>='))
	{
		try
		{
			$result = random_int($min, $max);
		} catch (Exception $e) {
		}

		if(isset($result))
		{
			return $result;
		}
	}

	$seed = secure_seed_rng();

	$distance = $max - $min;
	return $min + floor($distance * ($seed / PHP_INT_MAX) );
}

/**
 * More robust version of PHP's trim() function. It includes a list of UTF-8 blank characters
 * from http://kb.mozillazine.org/Network.IDN.blacklist_chars
 *
 * @param string $string The string to trim from
 * @param string $charlist Optional. The stripped characters can also be specified using the charlist parameter
 * @return string The trimmed string
 */
function trim_blank_chrs($string, $charlist="")
{
	$hex_chrs = array(
		0x09 => 1, // \x{0009}
		0x0A => 1, // \x{000A}
		0x0B => 1, // \x{000B}
		0x0D => 1, // \x{000D}
		0x20 => 1, // \x{0020}
		0xC2 => array(0x81 => 1, 0x8D => 1, 0x90 => 1, 0x9D => 1, 0xA0 => 1, 0xAD => 1), // \x{0081}, \x{008D}, \x{0090}, \x{009D}, \x{00A0}, \x{00AD}
		0xCC => array(0xB7 => 1, 0xB8 => 1), // \x{0337}, \x{0338}
		0xE1 => array(0x85 => array(0x9F => 1, 0xA0 => 1), 0x9A => array(0x80 => 1), 0xA0 => array(0x8E => 1)), // \x{115F}, \x{1160}, \x{1680}, \x{180E}
		0xE2 => array(0x80 => array(0x80 => 1, 0x81 => 1, 0x82 => 1, 0x83 => 1, 0x84 => 1, 0x85 => 1, 0x86 => 1, 0x87 => 1, 0x88 => 1, 0x89 => 1, 0x8A => 1, 0x8B => 1, 0x8C => 1, 0x8D => 1, 0x8E => 1, 0x8F => 1, // \x{2000} - \x{200F}
			0xA8 => 1, 0xA9 => 1, 0xAA => 1, 0xAB => 1, 0xAC => 1, 0xAD => 1, 0xAE => 1, 0xAF => 1), // \x{2028} - \x{202F}
			0x81 => array(0x9F => 1)), // \x{205F}
		0xE3 => array(0x80 => array(0x80 => 1), // \x{3000}
			0x85 => array(0xA4 => 1)), // \x{3164}
		0xEF => array(0xBB => array(0xBF => 1), // \x{FEFF}
			0xBE => array(0xA0 => 1), // \x{FFA0}
			0xBF => array(0xB9 => 1, 0xBA => 1, 0xBB => 1)), // \x{FFF9} - \x{FFFB}
	);

	$hex_chrs_rev = array(
		0x09 => 1, // \x{0009}
		0x0A => 1, // \x{000A}
		0x0B => 1, // \x{000B}
		0x0D => 1, // \x{000D}
		0x20 => 1, // \x{0020}
		0x81 => array(0xC2 => 1, 0x80 => array(0xE2 => 1)), // \x{0081}, \x{2001}
		0x8D => array(0xC2 => 1, 0x80 => array(0xE2 => 1)), // \x{008D}, \x{200D}
		0x90 => array(0xC2 => 1), // \x{0090}
		0x9D => array(0xC2 => 1), // \x{009D}
		0xA0 => array(0xC2 => 1, 0x85 => array(0xE1 => 1), 0x81 => array(0xE2 => 1), 0xBE => array(0xEF => 1)), // \x{00A0}, \x{1160}, \x{2060}, \x{FFA0}
		0xAD => array(0xC2 => 1, 0x80 => array(0xE2 => 1)), // \x{00AD}, \x{202D}
		0xB8 => array(0xCC => 1), // \x{0338}
		0xB7 => array(0xCC => 1), // \x{0337}
		0x9F => array(0x85 => array(0xE1 => 1), 0x81 => array(0xE2 => 1)), // \x{115F}, \x{205F}
		0x80 => array(0x9A => array(0xE1 => 1), 0x80 => array(0xE2 => 1, 0xE3 => 1)), // \x{1680}, \x{2000}, \x{3000}
		0x8E => array(0xA0 => array(0xE1 => 1), 0x80 => array(0xE2 => 1)), // \x{180E}, \x{200E}
		0x82 => array(0x80 => array(0xE2 => 1)), // \x{2002}
		0x83 => array(0x80 => array(0xE2 => 1)), // \x{2003}
		0x84 => array(0x80 => array(0xE2 => 1)), // \x{2004}
		0x85 => array(0x80 => array(0xE2 => 1)), // \x{2005}
		0x86 => array(0x80 => array(0xE2 => 1)), // \x{2006}
		0x87 => array(0x80 => array(0xE2 => 1)), // \x{2007}
		0x88 => array(0x80 => array(0xE2 => 1)), // \x{2008}
		0x89 => array(0x80 => array(0xE2 => 1)), // \x{2009}
		0x8A => array(0x80 => array(0xE2 => 1)), // \x{200A}
		0x8B => array(0x80 => array(0xE2 => 1)), // \x{200B}
		0x8C => array(0x80 => array(0xE2 => 1)), // \x{200C}
		0x8F => array(0x80 => array(0xE2 => 1)), // \x{200F}
		0xA8 => array(0x80 => array(0xE2 => 1)), // \x{2028}
		0xA9 => array(0x80 => array(0xE2 => 1)), // \x{2029}
		0xAA => array(0x80 => array(0xE2 => 1)), // \x{202A}
		0xAB => array(0x80 => array(0xE2 => 1)), // \x{202B}
		0xAC => array(0x80 => array(0xE2 => 1)), // \x{202C}
		0xAE => array(0x80 => array(0xE2 => 1)), // \x{202E}
		0xAF => array(0x80 => array(0xE2 => 1)), // \x{202F}
		0xA4 => array(0x85 => array(0xE3 => 1)), // \x{3164}
		0xBF => array(0xBB => array(0xEF => 1)), // \x{FEFF}
		0xB9 => array(0xBF => array(0xEF => 1)), // \x{FFF9}
		0xBA => array(0xBF => array(0xEF => 1)), // \x{FFFA}
		0xBB => array(0xBF => array(0xEF => 1)), // \x{FFFB}
	);

	// Start from the beginning and work our way in
	do
	{
		// Check to see if we have matched a first character in our utf-8 array
		$offset = match_sequence($string, $hex_chrs);
		if(!$offset)
		{
			// If not, then we must have a "good" character and we don't need to do anymore processing
			break;
		}
		$string = substr($string, $offset);
	}
	while(++$i);

	// Start from the end and work our way in
	$string = strrev($string);
	do
	{
		// Check to see if we have matched a first character in our utf-8 array
		$offset = match_sequence($string, $hex_chrs_rev);
		if(!$offset)
		{
			// If not, then we must have a "good" character and we don't need to do anymore processing
			break;
		}
		$string = substr($string, $offset);
	}
	while(++$i);
	$string = strrev($string);

	if($charlist)
	{
		$string = trim($string, $charlist);
	}
	else
	{
		$string = trim($string);
	}

	return $string;
}

/**
 * Match a sequence
 *
 * @param string $string The string to match from
 * @param array $array The array to match from
 * @param int $i Number in the string
 * @param int $n Number of matches
 * @return int The number matched
 */
function match_sequence($string, $array, $i=0, $n=0)
{
	if($string === "")
	{
		return 0;
	}

	$ord = ord($string[$i]);
	if(array_key_exists($ord, $array))
	{
		$level = $array[$ord];
		++$n;
		if(is_array($level))
		{
			++$i;
			return match_sequence($string, $level, $i, $n);
		}
		return $n;
	}

	return 0;
}

/**
 * Obtain the version of GD installed.
 *
 * @return float Version of GD
 */
function gd_version()
{
	static $gd_version;

	if($gd_version)
	{
		return $gd_version;
	}
	if(!extension_loaded('gd'))
	{
		return;
	}

	if(function_exists("gd_info"))
	{
		$gd_info = gd_info();
		preg_match('/\d/', $gd_info['GD Version'], $gd);
		$gd_version = $gd[0];
	}
	else
	{
		ob_start();
		phpinfo(8);
		$info = ob_get_contents();
		ob_end_clean();
		$info = stristr($info, 'gd version');
		preg_match('/\d/', $info, $gd);
		$gd_version = $gd[0];
	}

	return $gd_version;
}

/*
 * Validates an UTF-8 string.
 *
 * @param string $input The string to be checked
 * @param boolean $allow_mb4 Allow 4 byte UTF-8 characters?
 * @param boolean $return Return the cleaned string?
 * @return string|boolean Cleaned string or boolean
 */
function validate_utf8_string($input, $allow_mb4=true, $return=true)
{
	// Valid UTF-8 sequence?
	if(!preg_match('##u', $input))
	{
		$string = '';
		$len = strlen($input);
		for($i = 0; $i < $len; $i++)
		{
			$c = ord($input[$i]);
			if($c > 128)
			{
				if($c > 247 || $c <= 191)
				{
					if($return)
					{
						$string .= '?';
						continue;
					}
					else
					{
						return false;
					}
				}
				elseif($c > 239)
				{
					$bytes = 4;
				}
				elseif($c > 223)
				{
					$bytes = 3;
				}
				elseif($c > 191)
				{
					$bytes = 2;
				}
				if(($i + $bytes) > $len)
				{
					if($return)
					{
						$string .= '?';
						break;
					}
					else
					{
						return false;
					}
				}
				$valid = true;
				$multibytes = $input[$i];
				while($bytes > 1)
				{
					$i++;
					$b = ord($input[$i]);
					if($b < 128 || $b > 191)
					{
						if($return)
						{
							$valid = false;
							$string .= '?';
							break;
						}
						else
						{
							return false;
						}
					}
					else
					{
						$multibytes .= $input[$i];
					}
					$bytes--;
				}
				if($valid)
				{
					$string .= $multibytes;
				}
			}
			else
			{
				$string .= $input[$i];
			}
		}
		$input = $string;
	}
	if($return)
	{
		if($allow_mb4)
		{
			return $input;
		}
		else
		{
			return preg_replace("#[^\\x00-\\x7F][\\x80-\\xBF]{3,}#", '?', $input);
		}
	}
	else
	{
		if($allow_mb4)
		{
			return true;
		}
		else
		{
			return !preg_match("#[^\\x00-\\x7F][\\x80-\\xBF]{3,}#", $input);
		}
	}
}

/**
 * Send a Private Message to a user.
 *
 * @param array $pm Array containing: 'subject', 'message', 'touid' and 'receivepms' (the latter should reflect the value found in the users table: receivepms and receivefrombuddy)
 * @param int $fromid Sender UID (0 if you want to use $mybb->user['uid'] or -1 to use MyBB Engine)
 * @param bool $admin_override Whether or not do override user defined options for receiving PMs
 * @return bool True if PM sent
 */
function send_pm($pm, $fromid = 0, $admin_override=false)
{
	global $lang, $mybb, $db, $session;

	if($mybb->settings['enablepms'] == 0)
	{
		return false;
	}

	if(!is_array($pm))
	{
		return false;
	}

	if(isset($pm['language']))
	{
		if($pm['language'] != $mybb->user['language'] && $lang->language_exists($pm['language']))
		{
			// Load user language
			$lang->set_language($pm['language']);
			$lang->load($pm['language_file']);

			$revert = true;
		}

		foreach(array('subject', 'message') as $key)
		{
			if(is_array($pm[$key]))
			{
				$lang_string = $lang->{$pm[$key][0]};
				$num_args = count($pm[$key]);

				for($i = 1; $i < $num_args; $i++)
				{
					$lang_string = str_replace('{'.$i.'}', $pm[$key][$i], $lang_string);
				}
			}
			else
			{
				$lang_string = $lang->{$pm[$key]};
			}

			$pm[$key] = $lang_string;
		}

		if(isset($revert))
		{
			// Revert language
			$lang->set_language($mybb->user['language']);
			$lang->load($pm['language_file']);
		}
	}

	if(!$pm['subject'] ||!$pm['message'] || !$pm['touid'] || (!$pm['receivepms'] && !$admin_override))
	{
		return false;
	}

	require_once MYBB_ROOT."inc/datahandlers/pm.php";

	$pmhandler = new PMDataHandler();

	$subject = $pm['subject'];
	$message = $pm['message'];
	$toid = $pm['touid'];

	// Our recipients
	if(is_array($toid))
	{
		$recipients_to = $toid;
	}
	else
	{
		$recipients_to = array($toid);
	}

	$recipients_bcc = array();

	// Determine user ID
	if((int)$fromid == 0)
	{
		$fromid = (int)$mybb->user['uid'];
	}
	elseif((int)$fromid < 0)
	{
		$fromid = 0;
	}

	// Build our final PM array
	$pm = array(
		"subject" => $subject,
		"message" => $message,
		"icon" => -1,
		"fromid" => $fromid,
		"toid" => $recipients_to,
		"bccid" => $recipients_bcc,
		"do" => '',
		"pmid" => ''
	);

	if(isset($session))
	{
		$pm['ipaddress'] = $session->packedip;
	}

	$pm['options'] = array(
		"disablesmilies" => 0,
		"savecopy" => 0,
		"readreceipt" => 0
	);

	$pm['saveasdraft'] = 0;

	// Admin override
	$pmhandler->admin_override = (int)$admin_override;

	$pmhandler->set_data($pm);

	if($pmhandler->validate_pm())
	{
		$pmhandler->insert_pm();
		return true;
	}

	return false;
}

/**
 * Log a user spam block from StopForumSpam (or other spam service providers...)
 *
 * @param string $username The username that the user was using.
 * @param string $email    The email address the user was using.
 * @param string $ip_address The IP addres of the user.
 * @param array  $data     An array of extra data to go with the block (eg: confidence rating).
 * @return bool Whether the action was logged successfully.
 */
function log_spam_block($username = '', $email = '', $ip_address = '', $data = array())
{
	global $db, $session;

	if(!is_array($data))
	{
		$data = array($data);
	}

	if(!$ip_address)
	{
		$ip_address = get_ip();
	}

	$ip_address = my_inet_pton($ip_address);

	$insert_array = array(
		'username'  => $db->escape_string($username),
		'email'     => $db->escape_string($email),
		'ipaddress' => $db->escape_binary($ip_address),
		'dateline'  => (int)TIME_NOW,
		'data'      => $db->escape_string(@my_serialize($data)),
	);

	return (bool)$db->insert_query('spamlog', $insert_array);
}

/**
 * Copy a file to the CDN.
 *
 * @param string $file_path     The path to the file to upload to the CDN.
 *
 * @param string $uploaded_path The path the file was uploaded to, reference parameter for when this may be needed.
 *
 * @return bool Whether the file was copied successfully.
 */
function copy_file_to_cdn($file_path = '', &$uploaded_path = null)
{
	global $mybb, $plugins;

	$success = false;

	$file_path = (string)$file_path;

	$real_file_path = realpath($file_path);

	$file_dir_path = dirname($real_file_path);
	$file_dir_path = str_replace(MYBB_ROOT, '', $file_dir_path);
	$file_dir_path = ltrim($file_dir_path, './\\');

	$file_name = basename($real_file_path);

	if(file_exists($file_path))
	{
		if($mybb->settings['usecdn'] && !empty($mybb->settings['cdnpath']))
		{
			$cdn_path = rtrim($mybb->settings['cdnpath'], '/\\');

			if(substr($file_dir_path, 0, my_strlen(MYBB_ROOT)) == MYBB_ROOT)
			{
				$file_dir_path = str_replace(MYBB_ROOT, '', $file_dir_path);
			}

			$cdn_upload_path = $cdn_path . DIRECTORY_SEPARATOR . $file_dir_path;

			if(!($dir_exists = is_dir($cdn_upload_path)))
			{
				$dir_exists = @mkdir($cdn_upload_path, 0777, true);
			}

			if($dir_exists)
			{
				if(($cdn_upload_path = realpath($cdn_upload_path)) !== false)
				{
					$success = @copy($file_path, $cdn_upload_path.DIRECTORY_SEPARATOR.$file_name);

					if($success)
					{
						$uploaded_path = $cdn_upload_path;
					}
				}
			}
		}

		if(is_object($plugins))
		{
			$hook_args = array(
				'file_path' => &$file_path,
				'real_file_path' => &$real_file_path,
				'file_name' => &$file_name,
				'uploaded_path' => &$uploaded_path,
				'success' => &$success,
			);

			$plugins->run_hooks('copy_file_to_cdn_end', $hook_args);
		}
	}

	return $success;
}

/**
 * Validate an url
 *
 * @param string $url The url to validate.
 * @param bool $relative_path Whether or not the url could be a relative path.
 * @param bool $allow_local Whether or not the url could be pointing to local networks.
 *
 * @return bool Whether this is a valid url.
 */
function my_validate_url($url, $relative_path=false, $allow_local=false)
{
	if($allow_local)
	{
		$regex = '_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?(?:(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:localhost|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,}))\.?))(?::\d{2,5})?(?:[/?#]\S*)?$_iuS';
	}
	else
	{
		$regex = '_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,}))\.?)(?::\d{2,5})?(?:[/?#]\S*)?$_iuS';
	}

	if($relative_path && my_substr($url, 0, 1) == '/' || preg_match($regex, $url))
	{
		return true;
	}
	return false;
}

/**
 * Strip html tags from string, also removes <script> and <style> contents.
 *
 * @deprecated
 * @param  string $string         String to stripe
 * @param  string $allowable_tags Allowed html tags
 *
 * @return string                 Striped string
 */
function my_strip_tags($string, $allowable_tags = '')
{
	$pattern = array(
		'@(&lt;)style[^(&gt;)]*?(&gt;).*?(&lt;)/style(&gt;)@siu',
		'@(&lt;)script[^(&gt;)]*?.*?(&lt;)/script(&gt;)@siu',
		'@<style[^>]*?>.*?</style>@siu',
		'@<script[^>]*?.*?</script>@siu',
	);
	$string = preg_replace($pattern, '', $string);
	return strip_tags($string, $allowable_tags);
}

/**
 * Escapes a RFC 4180-compliant CSV string.
 * Based on https://github.com/Automattic/camptix/blob/f80725094440bf09861383b8f11e96c177c45789/camptix.php#L2867
 *
 * @param string $string The string to be escaped
 * @param boolean $escape_active_content Whether or not to escape active content trigger characters
 * @return string The escaped string
 */
function my_escape_csv($string, $escape_active_content=true)
{
	if($escape_active_content)
	{
		$active_content_triggers = array('=', '+', '-', '@');
		$delimiters = array(',', ';', ':', '|', '^', "\n", "\t", " ");

		$first_character = mb_substr($string, 0, 1);

		if(
			in_array($first_character, $active_content_triggers, true) ||
			in_array($first_character, $delimiters, true)
		)
		{
			$string = "'".$string;
		}

		foreach($delimiters as $delimiter)
		{
			foreach($active_content_triggers as $trigger)
			{
				$string = str_replace($delimiter.$trigger, $delimiter."'".$trigger, $string);
			}
		}
	}

	$string = str_replace('"', '""', $string);

	return $string;
}

// Fallback function for 'array_column', PHP < 5.5.0 compatibility
if(!function_exists('array_column'))
{
	function array_column($input, $column_key)
	{
		$values = array();
 		if(!is_array($input))
		{
			$input = array($input);
		}
 		foreach($input as $val)
		{
			if(is_array($val) && isset($val[$column_key]))
			{
				$values[] = $val[$column_key];
			}
			elseif(is_object($val) && isset($val->$column_key))
			{
				$values[] = $val->$column_key;
			}
		}
 		return $values;
	}
}

/**
 * Performs a timing attack safe string comparison.
 *
 * @param string $known_string The first string to be compared.
 * @param string $user_string The second, user-supplied string to be compared.
 * @return bool Result of the comparison.
 */
function my_hash_equals($known_string, $user_string)
{
	if(version_compare(PHP_VERSION, '5.6.0', '>='))
	{
		return hash_equals($known_string, $user_string);
	}
	else
	{
		$known_string_length = my_strlen($known_string);
		$user_string_length = my_strlen($user_string);

		if($user_string_length != $known_string_length)
		{
			return false;
		}

		$result = 0;

		for($i = 0; $i < $known_string_length; $i++)
		{
			$result |= ord($known_string[$i]) ^ ord($user_string[$i]);
		}

		return $result === 0;
	}
}