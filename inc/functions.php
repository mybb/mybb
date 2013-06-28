<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */

/**
 * Outputs a page directly to the browser, parsing anything which needs to be parsed.
 *
 * @param string The contents of the page.
 */
function output_page($contents)
{
	global $db, $lang, $theme, $plugins, $mybb;
	global $debug, $templatecache, $templatelist, $maintimer, $globaltime, $parsetime;

	$contents = parse_page($contents);
	$totaltime = $maintimer->stop();

	if($mybb->usergroup['cancp'] == 1)
	{
		if($mybb->settings['extraadmininfo'] != 0)
		{
			$phptime = $maintimer->format($maintimer->totaltime - $db->query_time);
			$query_time = $maintimer->format($db->query_time);

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

			$phpversion = PHP_VERSION;

			$serverload = get_server_load();

			if(my_strpos(getenv("REQUEST_URI"), "?"))
			{
				$debuglink = htmlspecialchars_uni(getenv("REQUEST_URI")) . "&amp;debug=1";
			}
			else
			{
				$debuglink = htmlspecialchars_uni(getenv("REQUEST_URI")) . "?debug=1";
			}

			if($mybb->settings['gzipoutput'] != 0)
			{
				$gzipen = "Enabled";
			}
			else
			{
				$gzipen = "Disabled";
			}

			$memory_usage = get_memory_usage();

			if($memory_usage)
			{
				$memory_usage = " / Memory Usage: ".get_friendly_size($memory_usage);
			}
			else
			{
				$memory_usage = '';
			}

			$other = "PHP version: $phpversion / Server Load: $serverload / GZip Compression: $gzipen";
			$debugstuff = "Generated in $totaltime seconds ($percentphp% PHP / $percentsql% ".$mybb->config['database']['type'].")<br />SQL Queries: $db->query_count /  Global Parsing Time: $globaltime$memory_usage<br />$other<br />[<a href=\"$debuglink\" target=\"_blank\">advanced details</a>]<br />";
			$contents = str_replace("<debugstuff>", $debugstuff, $contents);
		}

		if($mybb->debug_mode == true)
		{
			debug_page();
		}
	}

	$contents = str_replace("<debugstuff>", "", $contents);
	$contents = $plugins->run_hooks("pre_output_page", $contents);

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
 * @param mixed The name of the function.
 * @param mixed An array of arguments for the function
 * @return boolean True if function exists, otherwise false.
 */
function add_shutdown($name, $arguments=array())
{
	global $shutdown_functions;
	
	if(!is_array($arguments))
	{
		$arguments = array($arguments);
	}

	if(is_array($name) && method_exists($name[0], $name[1]))
	{
		$shutdown_functions["class_".get_class($name[0])."_".$name[1]] = array('function' => $name, 'arguments' => $arguments);
		return true;
	}
	else if(!is_array($name) && function_exists($name))
	{
		$shutdown_functions[$name] = array('function' => $name, 'arguments' => $arguments);
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

	if($done_shutdown == true || !$config || $error_handler->has_errors)
	{
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
			define("TABLE_PREFIX", $config['database']['table_prefix']);
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
 * @param int The number of messages to send (Defaults to 10)
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
				my_mail($email['mailto'], $email['subject'], $email['message'], $email['mailfrom'], "", $email['headers']);
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
 * @param string The contents of the page.
 * @return string The parsed page.
 */
function parse_page($contents)
{
	global $lang, $theme, $mybb, $htmldoctype, $archive_url, $error_handler;

	$contents = str_replace('<navigation>', build_breadcrumb(1), $contents);
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
 * @param string A date format according to PHP's date structure.
 * @param int The unix timestamp the date should be generated for.
 * @param int The offset in hours that should be applied to times. (timezones)
 * @param int Whether or not to use today/yesterday formatting.
 * @param boolean Whether or not to use the adodb time class for < 1970 or > 2038 times
 * @return string The formatted timestamp.
 */
function my_date($format, $stamp="", $offset="", $ty=1, $adodb=false)
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
			$offset = $mybb->user['timezone'];
			$dstcorrection = $mybb->user['dst'];
		}
		elseif(defined("IN_ADMINCP"))
		{
			$offset =  $mybbadmin['timezone'];
			$dstcorrection = $mybbadmin['dst'];
		}
		else
		{
			$offset = $mybb->settings['timezoneoffset'];
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
	
	if($adodb == true && function_exists('adodb_date'))
	{
		$date = adodb_date($format, $stamp + ($offset * 3600));
	}
	else
	{
		$date = gmdate($format, $stamp + ($offset * 3600));
	}
	
	if($mybb->settings['dateformat'] == $format && $ty)
	{
		$stamp = TIME_NOW;
		
		if($adodb == true && function_exists('adodb_date'))
		{
			$todaysdate = adodb_date($format, $stamp + ($offset * 3600));
			$yesterdaysdate = adodb_date($format, ($stamp - 86400) + ($offset * 3600));
		}
		else
		{
			$todaysdate = gmdate($format, $stamp + ($offset * 3600));
			$yesterdaysdate = gmdate($format, ($stamp - 86400) + ($offset * 3600));
		}

		if($todaysdate == $date)
		{
			$date = $lang->today;
		}
		else if($yesterdaysdate == $date)
		{
			$date = $lang->yesterday;
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
 * @param string Address the email should be addressed to.
 * @param string The subject of the email being sent.
 * @param string The message being sent.
 * @param string The from address of the email, if blank, the board name will be used.
 * @param string The chracter set being used to send this email.
 * @param boolean Do we wish to keep the connection to the mail server alive to send more than one message (SMTP only)
 * @param string The format of the email to be sent (text or html). text is default
 * @param string The text message of the email if being sent in html format, for email clients that don't support html
 * @param string The email address to return to. Defaults to admin return email address.
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
	global $mybb;
	if($mybb->user['uid'])
	{
		return md5($mybb->user['loginkey'].$mybb->user['salt'].$mybb->user['regdate']);
	}
	// Guests get a special string
	else
	{
		return md5($mybb->settings['bburl'].$mybb->config['database']['username'].$mybb->settings['internal']['encryption_key']);
	}
}

/**
 * Verifies a POST check code is valid, if not shows an error (silently returns false on silent parameter)
 *
 * @param string The incoming POST check code
 * @param boolean Silent mode or not (silent mode will not show the error to the user but returns false)
 */
function verify_post_check($code, $silent=false)
{
	global $lang;
	if(generate_post_check() != $code)
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
 * @param int The forum id to get the parent list for.
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
 * @param int The forum ID
 * @param string The column name to add to the query
 * @param string The joiner for each forum for querying (OR | AND | etc)
 * @param string The parent list of the forum - if you have it
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
 * @param boolean True to force a reload of the cache
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
 * @param int The forum ID
 * @param return Array of descendants
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
		return;
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
 * @param string The error message to be shown
 * @param string The title of the message shown in the title of the page and the error table
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
	if($mybb->input['ajax'])
	{
		// Send our headers.
		@header("Content-type: text/html; charset={$lang->settings['charset']}");
		echo "<error>{$error}</error>\n";
		exit;
	}

	if(!$title)
	{
		$title = $mybb->settings['bbname'];
	}

	$timenow = my_date($mybb->settings['dateformat'], TIME_NOW) . " " . my_date($mybb->settings['timeformat'], TIME_NOW);
	reset_breadcrumb();
	add_breadcrumb($lang->error);

	eval("\$errorpage = \"".$templates->get("error")."\";");
	output_page($errorpage);

	exit;
}

/**
 * Produce an error message for displaying inline on a page
 *
 * @param array Array of errors to be shown
 * @param string The title of the error message
 * @return string The inline error HTML
 */
function inline_error($errors, $title="")
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
	if($mybb->input['ajax'])
	{
		$error = implode("\n\n", $errors);
		// Send our headers.
		@header("Content-type: text/html; charset={$lang->settings['charset']}");
		echo "<error>{$error}</error>\n";
		exit;
	}

	foreach($errors as $error)
	{
		$errorlist .= "<li>".$error."</li>\n";
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

	$db->update_query("sessions", $noperm_array, "sid='{$session->sid}'", 1);

	if($mybb->input['ajax'])
	{
		// Send our headers.
		header("Content-type: text/html; charset={$lang->settings['charset']}");
		echo "<error>{$lang->error_nopermission_user_ajax}</error>\n";
		exit;
	}

	if($mybb->user['uid'])
	{
		$lang->error_nopermission_user_username = $lang->sprintf($lang->error_nopermission_user_username, $mybb->user['username']);
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
 * @param string The URL to redirect the user to
 * @param string The redirection message to be shown
 */
function redirect($url, $message="", $title="")
{
	global $header, $footer, $mybb, $theme, $headerinclude, $templates, $lang, $plugins;

	$redirect_args = array('url' => &$url, 'message' => &$message, 'title' => &$title);
	
	$plugins->run_hooks("redirect", $redirect_args);

	if($mybb->input['ajax'])
	{
		// Send our headers.
		@header("Content-type: text/html; charset={$lang->settings['charset']}");
		echo "<script type=\"text/javascript\">\n";
		if($message != "")
		{
			echo 'alert("'.addslashes($message).'");';
		}
		$url = str_replace("#", "&#", $url);
		$url = htmlspecialchars_decode($url);
		$url = str_replace(array("\n","\r",";"), "", $url);
		echo 'window.location = "'.addslashes($url).'";'."\n";
		echo "</script>\n";
		exit;
	}

	if(!$message)
	{
		$message = $lang->redirect;
	}

	$time = TIME_NOW;
	$timenow = my_date($mybb->settings['dateformat'], $time) . " " . my_date($mybb->settings['timeformat'], $time);

	if(!$title)
	{
		$title = $mybb->settings['bbname'];
	}
	
	// Show redirects only if both ACP and UCP settings are enabled, or ACP is enabled, and user is a guest.
	if($mybb->settings['redirects'] == 1 && ($mybb->user['showredirect'] != 0 || !$mybb->user['uid']))
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
		
		if(my_substr($url, 0, 7) !== 'http://' && my_substr($url, 0, 8) !== 'https://' && my_substr($url, 0, 1) !== '/')
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
 * @param int The number of items
 * @param int The number of items to be shown per page
 * @param int The current page number
 * @param string The URL to have page numbers tacked on to (If {page} is specified, the value will be replaced with the page #)
 * @return string The generated pagination
 */
function multipage($count, $perpage, $page, $url, $breadcrumb=false)
{
	global $theme, $templates, $lang, $mybb;

	if($count <= $perpage)
	{
		return;
	}
	
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

	$lang->multipage_pages = $lang->sprintf($lang->multipage_pages, $pages);
	
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
 * @param string The URL being passed
 * @param int The page number
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
 * @param int The user ID
 * @return array Array of user permissions for the specified user
 */
function user_permissions($uid=0)
{
	global $mybb, $cache, $groupscache, $user_cache;

	// If no user id is specified, assume it is the current user
	if($uid == 0)
	{
		$uid = $mybb->user['uid'];
	}

	// User id does not match current user, fetch permissions
	if($uid != $mybb->user['uid'])
	{
		// We've already cached permissions for this user, return them.
		if($user_cache[$uid]['permissions'])
		{
			return $user_cache[$uid]['permissions'];
		}

		// This user was not already cached, fetch their user information.
		if(!$user_cache[$uid])
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
 * Fetch the usergroup permissions for a specic group or series of groups combined
 *
 * @param mixed A list of groups (Can be a single integer, or a list of groups separated by a comma)
 * @return array Array of permissions generated for the groups
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
		return $groupscache[$gid];
	}

	foreach($groups as $gid)
	{
		if(trim($gid) == "" || !$groupscache[$gid])
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
 * @param int The group ID to fetch the display properties for
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
 * @param int The forum ID to build permissions for (0 builds for all forums)
 * @param int The user to build the permissions for (0 will select the uid automatically)
 * @param int The group of the user to build permissions for (0 will fetch it)
 * @return array Forum permissions for the specific forum or forums
 */
function forum_permissions($fid=0, $uid=0, $gid=0)
{
	global $db, $cache, $groupscache, $forum_cache, $fpermcache, $mybb, $usercache, $cached_forum_permissions_permissions, $cached_forum_permissions;

	if($uid == 0)
	{
		$uid = $mybb->user['uid'];
	}

	if(!$gid || $gid == 0) // If no group, we need to fetch it
	{
		if($uid != 0 && $uid != $mybb->user['uid'])
		{
			if(!$usercache[$uid])
			{
				$query = $db->simple_select("users", "*", "uid='$uid'");
				$usercache[$uid] = $db->fetch_array($query);
			}

			$gid = $usercache[$uid]['usergroup'].",".$usercache[$uid]['additionalgroups'];
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
		if(!$cached_forum_permissions_permissions[$gid][$fid])
		{
			$cached_forum_permissions_permissions[$gid][$fid] = fetch_forum_permissions($fid, $gid, $groupperms);
		}
		return $cached_forum_permissions_permissions[$gid][$fid];
	}
	else
	{
		if(!$cached_forum_permissions[$gid])
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
 * @param int The forum ID
 * @param string A comma separated list of usergroups
 * @param array Group permissions
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
	
	foreach($groups as $gid)
	{
		if($groupscache[$gid])
		{
			$level_permissions = $fpermcache[$fid][$gid];
			
			// If our permissions arn't inherited we need to figure them out
			if(empty($level_permissions))
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
					
					// If we STILL don't have forum permissions we use the usergroup itself
					if(empty($level_permissions))
					{
						$level_permissions = $groupscache[$gid];
					}					
				}
			}
			
			foreach($level_permissions as $permission => $access)
			{
				if($access >= $current_permissions[$permission] || ($access == "yes" && $current_permissions[$permission] == "no") || !$current_permissions[$permission])
				{
					$current_permissions[$permission] = $access;
				}
			}

			if(!$level_permissions["canonlyviewownthreads"])
			{
				$only_view_own_threads = 0;
			}
		}
	}

	// Figure out if we can view more than our own threads
	if($only_view_own_threads == 0)
	{
		$current_permissions["canonlyviewownthreads"] = 0;
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
 * @param int The forum ID
 * @param boolean The Parent ID
 */
function check_forum_password($fid, $pid=0)
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
	$parents = explode(',', $forum_cache[$fid]['parentlist']);
	rsort($parents);
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
	
	$password = $forum_cache[$fid]['password'];
	if($password)
	{
		if($mybb->input['pwverify'] && $pid == 0)
		{
			if($password == $mybb->input['pwverify'])
			{
				my_setcookie("forumpass[$fid]", md5($mybb->user['uid'].$mybb->input['pwverify']), null, true);
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
			if(!$mybb->cookies['forumpass'][$fid] || ($mybb->cookies['forumpass'][$fid] && md5($mybb->user['uid'].$password) != $mybb->cookies['forumpass'][$fid]))
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
 * @param fid The forum ID
 * @param uid The user ID to fetch permissions for (0 assumes current logged in user)
 * @param string The parent list for the forum (if blank, will be fetched)
 * @return array Array of moderator permissions for the specific forum
 */
function get_moderator_permissions($fid, $uid="0", $parentslist="")
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

	foreach($mod_cache as $fid => $forum)
	{
		if(!is_array($forum) || !in_array($fid, $parentslist))
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
 * @param int The forum ID (0 assumes global)
 * @param string The action tyring to be performed. (blank assumes any action at all)
 * @param int The user ID (0 assumes current user)
 * @return bool Returns true if the user has permission, false if they do not
 */
function is_moderator($fid="0", $action="", $uid="0")
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
					if(isset($modusers['users'][$uid]) && $modusers['users'][$uid]['mid'])
					{
						return true;
					}
					elseif(isset($modusers['usergroups'][$user_perms['gid']]))
					{
						// Moderating usergroup
						return true;
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
				if($modperms[$action] == 1)
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

	$listed = 0;
	if($mybb->input['icon'])
	{
		$icon = $mybb->input['icon'];
	}

	$iconlist = '';
	$no_icons_checked = " checked=\"checked\"";
	// read post icons from cache, and sort them accordingly
	$posticons_cache = $cache->read("posticons");
	$posticons = array();
	foreach($posticons_cache as $posticon)
	{
		$posticons[$posticon['name']] = $posticon;
	}
	krsort($posticons);
	
	foreach($posticons as $dbicon)
	{
		$dbicon['path'] = htmlspecialchars_uni($dbicon['path']);
		$dbicon['name'] = htmlspecialchars_uni($dbicon['name']);

		if($icon == $dbicon['iid'])
		{
			$iconlist .= "<label><input type=\"radio\" name=\"icon\" value=\"".$dbicon['iid']."\" checked=\"checked\" /> <img src=\"".$dbicon['path']."\" alt=\"".$dbicon['name']."\" /></label>";
			$no_icons_checked = "";
		}
		else
		{
			$iconlist .= "<label><input type=\"radio\" name=\"icon\" value=\"".$dbicon['iid']."\" /> <img src=\"".$dbicon['path']."\" alt=\"".$dbicon['name']."\" /></label>";
		}

		++$listed;
		if($listed == 10)
		{
			$iconlist .= "<br />";
			$listed = 0;
		}
	}

	eval("\$posticons = \"".$templates->get("posticons")."\";");

	return $posticons;
}

/**
 * MyBB setcookie() wrapper.
 *
 * @param string The cookie identifier.
 * @param string The cookie value.
 * @param int The timestamp of the expiry date.
 * @param boolean True if setting a HttpOnly cookie (supported by IE, Opera 9, Konqueror)
 */
function my_setcookie($name, $value="", $expires="", $httponly=false)
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
		$expires = TIME_NOW + intval($expires);
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
	
	$mybb->cookies[$name] = $value;

	header($cookie, false);
}

/**
 * Unset a cookie set by MyBB.
 *
 * @param string The cookie identifier.
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
 * @param string The cookie identifier.
 * @param int The cookie content id.
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
 * @param string The cookie identifier.
 * @param int The cookie content id.
 * @param string The value to set the cookie to.
 */
function my_set_array_cookie($name, $id, $value, $expires="")
{
	global $mybb;
	
	$cookie = $mybb->cookies['mybb'];
	$newcookie = my_unserialize($cookie[$name]);

	$newcookie[$id] = $value;
	$newcookie = serialize($newcookie);
	my_setcookie("mybb[$name]", addslashes($newcookie), $expires);

	// Make sure our current viarables are up-to-date as well
	$mybb->cookies['mybb'][$name] = $newcookie;
}

/**
 * Verifies that data passed is an array
 *
 * @param array Data to unserialize
 * @return array Unserialized data array
 */
function my_unserialize($data)
{
	$array = unserialize($data);

	if(!is_array($array))
	{
		$array = array();
	}

	return $array;
} 

/**
 * Returns the serverload of the system.
 *
 * @return int The serverload of the system.
 */
function get_server_load()
{
	global $lang;

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
			if(@ini_get('safe_mode') == 'On')
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
 * @param array Array of items being updated (numthreads,numposts,numusers)
 */
function update_stats($changes=array())
{
	global $cache, $db;

	$stats = $cache->read("stats");

	$counters = array('numthreads','numunapprovedthreads','numposts','numunapprovedposts','numusers');
	$update = array();
	foreach($counters as $counter)
	{
		if(array_key_exists($counter, $changes))
		{
			// Adding or subtracting from previous value?
			if(substr($changes[$counter], 0, 1) == "+" || substr($changes[$counter], 0, 1) == "-")
			{
				if(intval($changes[$counter]) != 0)
                {
					$new_stats[$counter] = $stats[$counter] + $changes[$counter];
				}
			}
			else
			{
				$new_stats[$counter] = $changes[$counter];
			}
			// Less than 0? That's bad
			if($new_stats[$counter] < 0)
			{
				$new_stats[$counter] = 0;
			}
		}
	}

	// Fetch latest user if the user count is changing
	if(array_key_exists('numusers', $changes))
	{
		$query = $db->simple_select("users", "uid, username", "", array('order_by' => 'uid', 'order_dir' => 'DESC', 'limit' => 1));
		$lastmember = $db->fetch_array($query);
		$new_stats['lastuid'] = $lastmember['uid'];
		$new_stats['lastusername'] = $lastmember['username'];
	}
	
	if(empty($new_stats))
	{
		return;
	}
	
	if(is_array($stats))
	{
		$stats = array_merge($stats, $new_stats);
	}
	else
	{
		$stats = $new_stats;
	}

	// Update stats row for today in the database
	$todays_stats = array(
		"dateline" => mktime(0, 0, 0, date("m"), date("j"), date("Y")),
		"numusers" => $stats['numusers'],
		"numthreads" => $stats['numthreads'],
		"numposts" => $stats['numposts']
	);
	$db->replace_query("stats", $todays_stats, "dateline");

	$cache->update("stats", $stats, "dateline");
}

/**
 * Updates the forum counters with a specific value (or addition/subtraction of the previous value)
 *
 * @param int The forum ID
 * @param array Array of items being updated (threads, posts, unapprovedthreads, unapprovedposts) and their value (ex, 1, +1, -1)
 */
function update_forum_counters($fid, $changes=array())
{
	global $db, $cache;

	$update_query = array();

	$counters = array('threads','unapprovedthreads','posts','unapprovedposts');

	// Fetch above counters for this forum
	$query = $db->simple_select("forums", implode(",", $counters), "fid='{$fid}'");
	$forum = $db->fetch_array($query);

	foreach($counters as $counter)
	{
		if(array_key_exists($counter, $changes))
		{
			// Adding or subtracting from previous value?
			if(substr($changes[$counter], 0, 1) == "+" || substr($changes[$counter], 0, 1) == "-")
			{
				$update_query[$counter] = $forum[$counter] + $changes[$counter];
			}
			else
			{
				$update_query[$counter] = $changes[$counter];
			}
			
			// Less than 0? That's bad
			if(!$update_query[$counter])
			{
				$update_query[$counter] = 0;
			}
		}
	}

	// Only update if we're actually doing something
	if(count($update_query) > 0)
	{
		$db->update_query("forums", $update_query, "fid='".intval($fid)."'");
	}

	// Guess we should update the statistics too?
	if(isset($update_query['threads']) || isset($update_query['posts']) || isset($update_query['unapprovedthreads']) || isset($update_query['unapprovedposts']))
	{
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
		update_stats($new_stats);
	}

	// Update last post info
	update_forum_lastpost($fid);
	
	$cache->update_forums();
}

/**
 * Update the last post information for a specific forum
 *
 * @param int The forum ID
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
		"lastpost" => intval($lastpost['lastpost']),
		"lastposter" => $db->escape_string($lastpost['lastposter']),
		"lastposteruid" => intval($lastpost['lastposteruid']),
		"lastposttid" => intval($lastpost['tid']),
		"lastpostsubject" => $db->escape_string($lastpost['subject'])
	);

	$db->update_query("forums", $updated_forum, "fid='{$fid}'");
}

/**
 * Updates the thread counters with a specific value (or addition/subtraction of the previous value)
 *
 * @param int The thread ID
 * @param array Array of items being updated (replies, unapprovedposts, attachmentcount) and their value (ex, 1, +1, -1)
 */
function update_thread_counters($tid, $changes=array())
{
	global $db;

	$update_query = array();
	
	$counters = array('replies','unapprovedposts','attachmentcount', 'attachmentcount');
	
	// Fetch above counters for this thread
	$query = $db->simple_select("threads", implode(",", $counters), "tid='{$tid}'");
	$thread = $db->fetch_array($query);
	
	foreach($counters as $counter)
	{
		if(array_key_exists($counter, $changes))
		{
			// Adding or subtracting from previous value?
			if(substr($changes[$counter], 0, 1) == "+" || substr($changes[$counter], 0, 1) == "-")
			{
				$update_query[$counter] = $thread[$counter] + $changes[$counter];
			}
			else
			{
				$update_query[$counter] = $changes[$counter];
			}
			
			// Less than 0? That's bad
			if($update_query[$counter] < 0)
			{
				$update_query[$counter] = 0;
			}
		}
	}
	
	$db->free_result($query);

	// Only update if we're actually doing something
	if(count($update_query) > 0)
	{
		$db->update_query("threads", $update_query, "tid='".intval($tid)."'");
	}
	
	unset($update_query, $thread);

	update_thread_data($tid);
}

/**
 * Update the first post and lastpost data for a specific thread
 *
 * @param int The thread ID
 */
function update_thread_data($tid)
{
	global $db;

	$thread = get_thread($tid);

	// If this is a moved thread marker, don't update it - we need it to stay as it is
	if(strpos($thread['closed'], 'moved|') !== false)
	{
		return false;
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
		SELECT u.uid, u.username, p.username AS postusername, p.dateline
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE p.tid='$tid'
		ORDER BY p.dateline ASC
		LIMIT 1
	");
	$firstpost = $db->fetch_array($query);
	
	$db->free_result($query);

	if(!$firstpost['username'])
	{
		$firstpost['username'] = $firstpost['postusername'];
	}

	if(!$lastpost['username'])
	{
		$lastpost['username'] = $lastpost['postusername'];
	}

	if(!$lastpost['dateline'])
	{
		$lastpost['username'] = $firstpost['username'];
		$lastpost['uid'] = $firstpost['uid'];
		$lastpost['dateline'] = $firstpost['dateline'];
	}

	$lastpost['username'] = $db->escape_string($lastpost['username']);
	$firstpost['username'] = $db->escape_string($firstpost['username']);

	$update_array = array(
		'username' => $firstpost['username'],
		'uid' => intval($firstpost['uid']),
		'dateline' => intval($firstpost['dateline']),
		'lastpost' => intval($lastpost['dateline']),
		'lastposter' => $lastpost['username'],
		'lastposteruid' => intval($lastpost['uid']),
	);
	$db->update_query("threads", $update_array, "tid='{$tid}'");
	
	unset($firstpost, $lastpost, $update_array);
}

function update_forum_count($fid)
{
	die("Deprecated function call: update_forum_count");
}
function update_thread_count($tid)
{
	die("Deprecated function call: update_thread_count");
}
function update_thread_attachment_count($tid)
{
	die("Deprecated function call: update_thread_attachment_count");
}

/**
 * Deletes a thread from the database
 *
 * @param int The thread ID
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
 * @param int The thread ID
 */
function delete_post($pid, $tid="")
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
 * @param int The parent forum to start with
 * @param int The selected item ID
 * @param int If we need to add select boxes to this cal or not
 * @param int The current depth of forums we're at
 * @param int Whether or not to show extra items such as User CP, Forum home
 * @param boolean Ignore the showinjump setting and show all forums (for moderation pages)
 * @param array Array of permissions
 * @param string The name of the forum jump
 * @return string Forum jump items
 */
function build_forum_jump($pid="0", $selitem="", $addselect="1", $depth="", $showextras="1", $showall=false, $permissions="", $name="fid")
{
	global $forum_cache, $jumpfcache, $permissioncache, $mybb, $selecteddone, $forumjump, $forumjumpbits, $gobutton, $theme, $templates, $lang;

	$pid = intval($pid);
	$jumpsel['default'] = '';

	if($permissions)
	{
		$permissions = $mybb->usergroup;
	}

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
						$optionselected = "selected=\"selected\"";
						$selecteddone = 1;
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
		if(!$selecteddone)
		{
			if(!$selitem)
			{
				$selitem = "default";
			}

			$jumpsel[$selitem] = 'selected="selected"';
		}

		if($showextras == 0)
		{
			$template = "special";
		}
		else
		{
			$template = "advanced";

			if(strpos(FORUM_URL, '.html') !== false)
			{
				$forum_link = "'".str_replace('{fid}', "'+this.options[this.selectedIndex].value+'", FORUM_URL)."'";
			}
			else
			{
				$forum_link = "'".str_replace('{fid}', "'+this.options[this.selectedIndex].value", FORUM_URL);
			}
		}

		eval("\$forumjump = \"".$templates->get("forumjump_".$template)."\";");
	}

	return $forumjump;
}

/**
 * Returns the extension of a file.
 *
 * @param string The filename.
 * @return string The extension of the file.
 */
function get_extension($file)
{
	return my_strtolower(my_substr(strrchr($file, "."), 1));
}

/**
 * Generates a random string.
 *
 * @param int The length of the string to generate.
 * @return string The random string.
 */
function random_str($length="8")
{
	$set = array("a","A","b","B","c","C","d","D","e","E","f","F","g","G","h","H","i","I","j","J","k","K","l","L","m","M","n","N","o","O","p","P","q","Q","r","R","s","S","t","T","u","U","v","V","w","W","x","X","y","Y","z","Z","1","2","3","4","5","6","7","8","9");
	$str = '';

	for($i = 1; $i <= $length; ++$i)
	{
		$ch = my_rand(0, count($set)-1);
		$str .= $set[$ch];
	}

	return $str;
}

/**
 * Formats a username based on their display group
 *
 * @param string The username
 * @param int The usergroup for the user (if not specified, will be fetched)
 * @param int The display group for the user (if not specified, will be fetched)
 * @return string The formatted username
 */
function format_name($username, $usergroup, $displaygroup="")
{
	global $groupscache, $cache;

	if(!is_array($groupscache))
	{
		$groupscache = $cache->read("usergroups");
	}

	if($displaygroup != 0)
	{
		$usergroup = $displaygroup;
	}

	$ugroup = $groupscache[$usergroup];
	$format = $ugroup['namestyle'];
	$userin = substr_count($format, "{username}");

	if($userin == 0)
	{
		$format = "{username}";
	}

	$format = stripslashes($format);

	return str_replace("{username}", $username, $format);
}

/**
 * Build the javascript based MyCode inserter
 *
 * @return string The MyCode inserter
 */
function build_mycode_inserter($bind="message")
{
	global $db, $mybb, $theme, $templates, $lang, $plugins;

	if($mybb->settings['bbcodeinserter'] != 0)
	{
		$editor_lang_strings = array(
			"editor_title_bold",
			"editor_title_italic",
			"editor_title_underline",
			"editor_title_left",
			"editor_title_center",
			"editor_title_right",
			"editor_title_justify",
			"editor_title_numlist",
			"editor_title_bulletlist",
			"editor_title_image",
			"editor_title_hyperlink",
			"editor_title_email",
			"editor_title_quote",
			"editor_title_code",
			"editor_title_php",
			"editor_title_close_tags",
			"editor_enter_list_item",
			"editor_enter_url",
			"editor_enter_url_title",
			"editor_enter_email",
			"editor_enter_email_title",
			"editor_enter_image",
			"editor_enter_video_url",
			"editor_video_dailymotion",
			"editor_video_metacafe",
			"editor_video_myspacetv",
			"editor_video_vimeo",
			"editor_video_yahoo",
			"editor_video_youtube",
			"editor_size_xx_small",
			"editor_size_x_small",
			"editor_size_small",
			"editor_size_medium",
			"editor_size_large",
			"editor_size_x_large",
			"editor_size_xx_large",
			"editor_font",
			"editor_size",
			"editor_color"
		);
		$editor_language = "var editor_language = {\n";
		
		$editor_lang_strings = $plugins->run_hooks("mycode_add_codebuttons", $editor_lang_strings);

		foreach($editor_lang_strings as $key => $lang_string)
		{
			// Strip initial editor_ off language string if it exists - ensure case sensitivity does not matter.
			$js_lang_string = preg_replace("#^editor_#i", "", $lang_string);
			$string = str_replace("\"", "\\\"", $lang->$lang_string);
			$editor_language .= "\t{$js_lang_string}: \"{$string}\"";

			if(isset($editor_lang_strings[$key+1]))
			{
				$editor_language .= ",";
			}

			$editor_language .= "\n";
		}

		$editor_language .= "};";

		if(defined("IN_ADMINCP"))
		{
			global $page;
			$codeinsert = $page->build_codebuttons_editor($bind, $editor_language);
		}
		else
		{
			eval("\$codeinsert = \"".$templates->get("codebuttons")."\";");
		}
	}

	return $codeinsert;
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
				if($smilie['showclickable'] != 0)
				{
					$smiliecache[$smilie['find']] = $smilie['image'];
				}
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

			$smilies = "";
			$counter = 0;
			$i = 0;

			foreach($smiliecache as $find => $image)
			{
				if($i < $mybb->settings['smilieinsertertot'])
				{
					if($counter == 0)
					{
						$smilies .=  "<tr>\n";
					}

					$find = htmlspecialchars_uni($find);
					$smilies .= "<td style=\"text-align: center\"><img src=\"{$image}\" border=\"0\" class=\"smilie\" alt=\"{$find}\" /></td>\n";
					++$i;
					++$counter;

					if($counter == $mybb->settings['smilieinsertercols'])
					{
						$counter = 0;
						$smilies .= "</tr>\n";
					}
				}
			}

			if($counter != 0)
			{
				$colspan = $mybb->settings['smilieinsertercols'] - $counter;
				$smilies .= "<td colspan=\"{$colspan}\">&nbsp;</td>\n</tr>\n";
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
 *  @param int The prefix ID (0 to return all)
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
 * Build the thread prefix selection menu
 * 
 *  @param mixed The forum ID (integer ID or string all)
 *  @param mixed The selected prefix ID (integer ID or string any)
 *  @return string The thread prefix selection menu
 */
function build_prefix_select($fid, $selected_pid=0, $multiple=0)
{
	global $cache, $db, $lang, $mybb;
	
	if($fid != 'all')
	{
		$fid = intval($fid);
	}

	$prefix_cache = build_prefixes(0);
	if(!$prefix_cache)
	{
		return false; // We've got no prefixes to show
	}

	$groups = array($mybb->user['usergroup']);
	if($mybb->user['additionalgroups'])
	{
		$exp = explode(",", $mybb->user['additionalgroups']);

		foreach($exp as $group)
		{
			$groups[] = $group;
		}
	}

	// Go through each of our prefixes and decide which ones we can use
	$prefixes = array();
	foreach($prefix_cache as $prefix)
	{
		if($fid != "all" && $prefix['forums'] != "-1")
		{
			// Decide whether this prefix can be used in our forum
			$forums = explode(",", $prefix['forums']);

			if(!in_array($fid, $forums))
			{
				// This prefix is not in our forum list
				continue;
			}
		}

		if($prefix['groups'] != "-1")
		{
			$prefix_groups = explode(",", $prefix['groups']);

			foreach($groups as $group)
			{
				if(in_array($group, $prefix_groups) && !isset($prefixes[$prefix['pid']]))
				{
					// Our group can use this prefix!
					$prefixes[$prefix['pid']] = $prefix;
				}
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
		return false;
	}

	$prefixselect = "";
	$multipleselect = "";
	if($multiple != 0)
	{
		$multipleselect = " multiple=\"multiple\" size=\"5\"";
	}

	$prefixselect = "<select name=\"threadprefix\"{$multipleselect}>\n";

	if($multiple == 1)
	{
		$any_selected = "";
		if($selected_pid == 'any')
		{
			$any_selected = " selected=\"selected\"";
		}

		$prefixselect .= "<option value=\"any\"".$any_selected.">".$lang->any_prefix."</option>\n";
	}

	$default_selected = "";
	if((intval($selected_pid) == 0) && $selected_pid != 'any')
	{
		$default_selected = " selected=\"selected\"";
	}

	$prefixselect .= "<option value=\"0\"".$default_selected.">".$lang->no_prefix."</option>\n";

	foreach($prefixes as $prefix)
	{
		$selected = "";
		if($prefix['pid'] == $selected_pid)
		{
			$selected = " selected=\"selected\"";
		}

		$prefixselect .= "<option value=\"".$prefix['pid']."\"".$selected.">".htmlspecialchars_uni($prefix['prefix'])."</option>\n";
	}

	$prefixselect .= "</select>\n&nbsp;";

	return $prefixselect;
}

/**
 * Gzip encodes text to a specified level
 *
 * @param string The string to encode
 * @param int The level (1-9) to encode at
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
 * @param array The data of the moderator's action.
 * @param string The message to enter for the action the moderator performed.
 */
function log_moderator_action($data, $action="")
{
	global $mybb, $db, $session;

	// If the fid or tid is not set, set it at 0 so MySQL doesn't choke on it.
	if($data['fid'] == '')
	{
		$fid = 0;
	}
	else
	{
		$fid = $data['fid'];
		unset($data['fid']);
	}

	if($data['tid'] == '')
	{
		$tid = 0;
	}
	else
	{
		$tid = $data['tid'];
		unset($data['tid']);
	}

	// Any remaining extra data - we serialize and insert in to its own column
	if(is_array($data))
	{
		$data = serialize($data);
	}

	$time = TIME_NOW;

	$sql_array = array(
		"uid" => $mybb->user['uid'],
		"dateline" => $time,
		"fid" => $fid,
		"tid" => $tid,
		"action" => $db->escape_string($action),
		"data" => $db->escape_string($data),
		"ipaddress" => $db->escape_string($session->ipaddress)
	);
	$db->insert_query("moderatorlog", $sql_array);
}

/**
 * Get the formatted reputation for a user.
 *
 * @param int The reputation value
 * @param int The user ID (if not specified, the generated reputation will not be a link)
 * @return string The formatted repuation
 */
function get_reputation($reputation, $uid=0)
{
	global $theme;

	$display_reputation = '';

	if($uid != 0)
	{
		$display_reputation = "<a href=\"reputation.php?uid={$uid}\">";
	}

	$display_reputation .= "<strong class=\"";

	if($reputation < 0)
	{
		$display_reputation .= "reputation_negative";
	}
	elseif($reputation > 0)
	{
		$display_reputation .= "reputation_positive";
	}
	else
	{
		$display_reputation .= "reputation_neutral";
	}

	$display_reputation .= "\">{$reputation}</strong>";

	if($uid != 0)
	{
		$display_reputation .= "</a>";
	}

	return $display_reputation;
}

/**
 * Fetch a color coded version of a warning level (based on it's percentage)
 *
 * @param int The warning level (percentage of 100)
 * @return string Formatted warning level
 */
function get_colored_warning_level($level)
{
	if($level >= 80)
	{
		return "<span class=\"high_warning\">{$level}%</span>";
	}
	else if($level >= 50)
	{
		return "<span class=\"moderate_warning\">{$level}%</span>";
	}
	else if($level >= 25)
	{
		return "<span class=\"low_warning\">{$level}%</span>";
	}
	else
	{
		return $level."%";
	}
}

/**
 * Fetch the IP address of the current user.
 *
 * @return string The IP address.
 */
function get_ip()
{
    global $mybb, $plugins;

    $ip = 0;

    if(!preg_match("#^(10|172\.16|192\.168)\.#", $_SERVER['REMOTE_ADDR']))
    {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    if($mybb->settings['ip_forwarded_check'])
    {
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            preg_match_all("#[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}#s", $_SERVER['HTTP_X_FORWARDED_FOR'], $addresses);
        }
        elseif(isset($_SERVER['HTTP_X_REAL_IP']))
        {
            preg_match_all("#[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}#s", $_SERVER['HTTP_X_REAL_IP'], $addresses);
        }

		if(is_array($addresses[0]))
		{
			foreach($addresses[0] as $key => $val)
			{
				if(!preg_match("#^(10|172\.16|192\.168)\.#", $val))
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
            $ip = $_SERVER['HTTP_CLIENT_IP'];
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
 * @param int The size in bytes
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
 * Get the attachment icon for a specific file extension
 *
 * @param string The file extension
 * @return string The attachment icon
 */
function get_attachment_icon($ext)
{
	global $cache, $attachtypes, $theme;

	if(!$attachtypes)
	{
		$attachtypes = $cache->read("attachtypes");
	}

	$ext = my_strtolower($ext);

	if($attachtypes[$ext]['icon'])
	{
		if(defined("IN_ADMINCP"))
		{
			$icon = str_replace("{theme}", "", $attachtypes[$ext]['icon']);
			if(my_substr($icon, 0, 1) != "/" && my_substr($icon, 0, 7) != "http://")
			{
				$icon = "../".$icon;
			}
		}
		elseif(defined("IN_PORTAL"))
		{
			global $change_dir;
			$icon = $change_dir."/".str_replace("{theme}", $theme['imgdir'], $attachtypes[$ext]['icon']);
		}
		else
		{
			$icon = str_replace("{theme}", $theme['imgdir'], $attachtypes[$ext]['icon']);
		}
		return "<img src=\"{$icon}\" border=\"0\" alt=\".{$ext}\" />";
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
		
		return "<img src=\"{$theme['imgdir']}/attachtypes/unknown.gif\" border=\"0\" alt=\".{$ext}\" />";
	}
}

/**
 * Get a list of the unviewable forums for the current user
 *
 * @param boolean Set to true to only fetch those forums for which users can actually read a thread in.
 * @return string Comma separated values list of the forum IDs which the user cannot view
 */
function get_unviewable_forums($only_readable_threads=false)
{
	global $forum_cache, $permissioncache, $mybb, $unviewable, $templates, $forumpass;

	if(!is_array($forum_cache))
	{
		cache_forums();
	}

	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}

	$password_forums = array();
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
			if($mybb->cookies['forumpass'][$forum['fid']] != md5($mybb->user['uid'].$forum['password']))
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
				if(isset($password_forums[$parent]) && $mybb->cookies['forumpass'][$parent] != md5($mybb->user['uid'].$password_forums[$parent]))
				{
					$pwverified = 0;
				}
			}
		}

		if($perms['canview'] == 0 || $pwverified == 0 || ($only_readable_threads == true && $perms['canviewthreads'] == 0))
		{
			if($unviewableforums)
			{
				$unviewableforums .= ",";
			}

			$unviewableforums .= "'".$forum['fid']."'";
		}
	}

	if(isset($unviewableforums))
	{
		return $unviewableforums;
	}
}

/**
 * Fixes mktime for dates earlier than 1970
 *
 * @param string The date format to use
 * @param int The year of the date
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
 * @return The formatted breadcrumb navigation trail
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
					$multipage = multipage($navbit['multipage']['num_threads'], $mybb->settings['threadsperpage'], $navbit['multipage']['current_page'], $navbit['multipage']['url'], true);
					if($multipage)
					{
						++$i;
						$multipage_dropdown = " <img src=\"{$theme['imgdir']}/arrow_down.gif\" alt=\"v\" title=\"\" class=\"pagination_breadcrumb_link\" id=\"breadcrumb_multipage\" />{$multipage}";
						$sep = $multipage_dropdown.$sep;
					}
				}

				// Replace page 1 URLs
				$navbit['url'] = str_replace("-page-1.html", ".html", $navbit['url']);
				$navbit['url'] = preg_replace("/&amp;page=1$/", "", $navbit['url']);

				eval("\$nav .= \"".$templates->get("nav_bit")."\";");
			}
		}
	}

	$navsize = count($navbits);
	$navbit = $navbits[$navsize-1];

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
 * @param string The name of the item to add
 * @param string The URL of the item to add
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
 * @param int The forum ID to build the navigation for
 * @param array The multipage drop down array of information
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
	$newnav[0]['options'] = $navbits[0]['options'];

	unset($GLOBALS['navbits']);
	$GLOBALS['navbits'] = $newnav;
}

/**
 * Builds a URL to an archive mode page
 *
 * @param string The type of page (thread|announcement|forum)
 * @param int The ID of the item
 * @return string The URL
 */
function build_archive_link($type, $id="")
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
	global $db, $debug, $templates, $templatelist, $mybb, $maintimer, $globaltime, $ptimer, $parsetime, $lang;

	$totaltime = $maintimer->totaltime;
	$phptime = $maintimer->format($maintimer->totaltime - $db->query_time);
	$query_time = $maintimer->format($db->query_time);

	$percentphp = number_format((($phptime/$maintimer->totaltime)*100), 2);
	$percentsql = number_format((($query_time/$maintimer->totaltime)*100), 2);

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
	echo "<title>MyBB Debug Information</title>";
	echo "</head>";
	echo "<body>";
	echo "<h1>MyBB Debug Information</h1>\n";
	echo "<h2>Page Generation</h2>\n";
	echo "<table bgcolor=\"#666666\" width=\"95%\" cellpadding=\"4\" cellspacing=\"1\" align=\"center\">\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#CCCCCC\" colspan=\"4\"><b><span style=\"size:2;\">Page Generation Statistics</span></b></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">Page Generation Time:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$totaltime seconds</font></td>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">No. DB Queries:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$db->query_count</font></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">PHP Processing Time:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$phptime seconds ($percentphp%)</font></td>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">DB Processing Time:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$query_time seconds ($percentsql%)</font></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">Extensions Used:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">{$mybb->config['database']['type']}, xml</font></td>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">Global.php Processing Time:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$globaltime seconds</font></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">PHP Version:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$phpversion</font></td>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">Server Load:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$serverload</font></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">GZip Encoding Status:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$gzipen</font></td>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">No. Templates Used:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">".count($templates->cache)." (".intval(count(explode(",", $templatelist)))." Cached / ".intval(count($templates->uncached_templates))." Manually Loaded)</font></td>\n";
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
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">Memory Usage:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">{$memory_usage}</font></td>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">Memory Limit:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">{$memory_limit}</font></td>\n";
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

	if(count($templates->uncached_templates > 0))
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
 * @param mixed An array or int of the ID numbers you're marking as dealt with
 * @param string The type of item the above IDs are for - post, posts, thread, threads, forum, all
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
				$db->update_query("reportedposts", array('reportstatus' => 1), "pid IN($rids) AND reportstatus='0'");
			}
			break;
		case "post":
			$db->update_query("reportedposts", array('reportstatus' => 1), "pid='$id' AND reportstatus='0'");
			break;
		case "threads":
			if(is_array($id))
			{
				$rids = implode($id, "','");
				$rids = "'0','$rids'";
				$db->update_query("reportedposts", array('reportstatus' => 1), "tid IN($rids) AND reportstatus='0'");
			}
			break;
		case "thread":
			$db->update_query("reportedposts", array('reportstatus' => 1), "tid='$id' AND reportstatus='0'");
			break;
		case "forum":
			$db->update_query("reportedposts", array('reportstatus' => 1), "fid='$id' AND reportstatus='0'");
			break;
		case "all":
			$db->update_query("reportedposts", array('reportstatus' => 1), "reportstatus='0'");
			break;
	}

	$arguments = array('id' => $id, 'type' => $type);
	$plugins->run_hooks("mark_reports", $arguments);
	$cache->update_reportedposts();
}

/**
 * Fetch a friendly x days, y months etc date stamp from a timestamp
 *
 * @param int The timestamp
 * @param array Array of options
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

	if($years == 1)
	{
		$nicetime['years'] = "1".$lang_year;
	}
	else if($years > 1)
	{
		$nicetime['years'] = $years.$lang_years;
	}

	if($months == 1)
	{
		$nicetime['months'] = "1".$lang_month;
	}
	else if($months > 1)
	{
		$nicetime['months'] = $months.$lang_months;
	}

	if($weeks == 1)
	{
		$nicetime['weeks'] = "1".$lang_week;
	}
	else if($weeks > 1)
	{
		$nicetime['weeks'] = $weeks.$lang_weeks;
	}

	if($days == 1)
	{
		$nicetime['days'] = "1".$lang_day;
	}
	else if($days > 1)
	{
		$nicetime['days'] = $days.$lang_days;
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
 * @param int 1 to reset the row to trow1.
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
 * @param int The user ID
 * @param int The user group ID to join
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
		$query = $db->simple_select("users", "additionalgroups, usergroup", "uid='".intval($uid)."'");
		$user = $db->fetch_array($query);
	}

	// Build the new list of additional groups for this user and make sure they're in the right format
	$usergroups = "";
	$usergroups = $user['additionalgroups'].",".$joingroup;
	$groupslist = "";
	$groups = explode(",", $usergroups);

	if(is_array($groups))
	{
		foreach($groups as $gid)
		{
			if(trim($gid) != "" && $gid != $user['usergroup'] && !$donegroup[$gid])
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
		$db->update_query("users", array('additionalgroups' => $groupslist), "uid='".intval($uid)."'");
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
 * @param int The user ID
 * @param int The user group ID
 */
function leave_usergroup($uid, $leavegroup)
{
	global $db, $mybb, $cache;

	if($uid == $mybb->user['uid'])
	{
		$user = $mybb->user;
	}
	else
	{
		$query = $db->simple_select("users", "*", "uid='".intval($uid)."'");
		$user = $db->fetch_array($query);
	}

	$groupslist = "";
	$usergroups = "";
	$usergroups = $user['additionalgroups'].",";

	$groups = explode(",", $user['additionalgroups']);

	if(is_array($groups))
	{
		foreach($groups as $gid)
		{
			if(trim($gid) != "" && $leavegroup != $gid && !$donegroup[$gid])
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
		WHERE uid='".intval($uid)."'
	");
	
	$cache->update_moderators();
}

/**
 * Get the current location taking in to account different web serves and systems
 *
 * @param boolean True to return as "hidden" fields
 * @param array Array of fields to ignore if first argument is true
 * @return string The current URL being accessed
 */
function get_current_location($fields=false, $ignore=array())
{
	if(defined("MYBB_LOCATION"))
	{
		return MYBB_LOCATION;
	}

	if(!empty($_SERVER['PATH_INFO']))
	{
		$location = htmlspecialchars_uni($_SERVER['PATH_INFO']);
	}
	elseif(!empty($_ENV['PATH_INFO']))
	{
		$location = htmlspecialchars_uni($_ENV['PATH_INFO']);
	}
	elseif(!empty($_ENV['PHP_SELF']))
	{
		$location = htmlspecialchars_uni($_ENV['PHP_SELF']);
	}
	else
	{
		$location = htmlspecialchars_uni($_SERVER['PHP_SELF']);
	}

	if($fields == true)
	{
		global $mybb;
		
		if(!is_array($ignore))
		{
			$ignore = array($ignore);
		}

		$form_html = "";
		if(!empty($mybb->input))
		{
			foreach($mybb->input as $name => $value)
			{
				if(in_array($name, $ignore))
				{
					continue;
				}
				
				$form_html .= "<input type=\"hidden\" name=\"".htmlspecialchars_uni((string)$name)."\" value=\"".htmlspecialchars_uni((string)$value)."\" />\n";
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

		if(strlen($location) > 150)
		{
			$location = substr($location, 0, 150);
		}

		return $location;
	}
}

/**
 * Build a theme selection menu
 *
 * @param string The name of the menu
 * @param int The ID of the selected theme
 * @param int The ID of the parent theme to select from
 * @param int The current selection depth
 * @param boolean Whether or not to override usergroup permissions (true to override)
 * @return string The theme selection list
 */
function build_theme_select($name, $selected="", $tid=0, $depth="", $usergroup_override=false)
{
	global $db, $themeselect, $tcache, $lang, $mybb, $limit;

	if($tid == 0)
	{
		$themeselect = "<select name=\"$name\">";
		$themeselect .= "<option value=\"0\">".$lang->use_default."</option>\n";
		$themeselect .= "<option value=\"0\">-----------</option>\n";
		$tid = 1;
	}

	if(!is_array($tcache))
	{
		$query = $db->simple_select("themes", "name, pid, tid, allowedgroups", "pid != '0'", array('order_by' => 'pid, name'));

		while($theme = $db->fetch_array($query))
		{
			$tcache[$theme['pid']][$theme['tid']] = $theme;
		}
	}

	if(is_array($tcache[$tid]))
	{
		// Figure out what groups this user is in
		if($mybb->user['additionalgroups'])
		{
			$in_groups = explode(",", $mybb->user['additionalgroups']);
		}
		$in_groups[] = $mybb->user['usergroup'];

		foreach($tcache[$tid] as $theme)
		{
			$sel = "";
			// Make theme allowed groups into array
			$is_allowed = false;
			if($theme['allowedgroups'] != "all")
			{
				$allowed_groups = explode(",", $theme['allowedgroups']);
				// See if groups user is in is allowed
				foreach($allowed_groups as $agid)
				{
					if(in_array($agid, $in_groups))
					{
						$is_allowed = true;
						break;
					}
				}
			}

			// Show theme if allowed, or if override is on
			if($is_allowed || $theme['allowedgroups'] == "all" || $usergroup_override == true)
			{
				if($theme['tid'] == $selected)
				{
					$sel = " selected=\"selected\"";
				}

				if($theme['pid'] != 0)
				{
					$themeselect .= "<option value=\"".$theme['tid']."\"$sel>".$depth.htmlspecialchars_uni($theme['name'])."</option>";
					$depthit = $depth."--";
				}

				if(array_key_exists($theme['tid'], $tcache))
				{
					build_theme_select($name, $selected, $theme['tid'], $depthit, $usergroup_override);
				}
			}
		}
	}

	if($tid == 1)
	{
		$themeselect .= "</select>";
	}

	return $themeselect;
}

/**
 * Custom function for htmlspecialchars which takes in to account unicode
 *
 * @param string The string to format
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
 * @param int The number to format.
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
 * Replacement function for PHP's wordwrap(). This version does not break up HTML tags, URLs or unicode references.
 *
 * @param string The string to be word wrapped
 * @return string The word wraped string
 */
function my_wordwrap($message)
{
	global $mybb;

	if($mybb->settings['wordwrap'] > 0)
	{
		$message = convert_through_utf8($message);
		
		if(!($new_message = @preg_replace("#(((?>[^\s&/<>\"\\-\[\]])|(&\#[a-z0-9]{1,10};)){{$mybb->settings['wordwrap']}})#u", "$0&#8203;", $message)))
		{
			$new_message = preg_replace("#(((?>[^\s&/<>\"\\-\[\]])|(&\#[a-z0-9]{1,10};)){{$mybb->settings['wordwrap']}})#", "$0&#8203;", $message);	
		}
		
		$new_message = convert_through_utf8($new_message, false);
		
		return $new_message;
	}

	return $message;
}

/**
 * Workaround for date limitation in PHP to establish the day of a birthday (Provided by meme)
 *
 * @param int The month of the birthday
 * @param int The day of the birthday
 * @param int The year of the bithday
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
 * @param int The year.
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
 * Formats a birthday appropriately
 *
 * @param string The PHP date format string
 * @param int The month of the birthday
 * @param int The day of the birthday
 * @param int The year of the birthday
 * @param int The weekday of the birthday
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
 * @param string The birthday of a user.
 * @return float The age of a user with that birthday.
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
 * @param int The thread id for which to update the first post id.
 */
function update_first_post($tid)
{
	global $db;

	$query = $db->simple_select("posts", "pid,replyto", "tid='{$tid}'", array('order_by' => 'dateline', 'limit' => 1));
	$post = $db->fetch_array($query);

	if($post['replyto'] != 0)
	{
		$replyto_update = array(
			"replyto" => 0
		);
		$db->update_query("posts", $replyto_update, "pid='{$post['pid']}'");
	}

	$firstpostup = array(
		"firstpost" => $post['pid']
	);
	$db->update_query("threads", $firstpostup, "tid='$tid'");
}

/**
 * Checks for the length of a string, mb strings accounted for
 *
 * @param string The string to check the length of.
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
 * @param string The string to cut.
 * @param int Where to cut
 * @param int (optional) How much to cut
 * @param bool (optional) Properly handle HTML entities?
 * @return int The cut part of the string.
 */
function my_substr($string, $start, $length="", $handle_entities = false)
{
	if($handle_entities)
	{
		$string = unhtmlentities($string);
	}
	if(function_exists("mb_substr"))
	{
		if($length != "")
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
		if($length != "")
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
 * lowers the case of a string, mb strings accounted for
 *
 * @param string The string to lower.
 * @return int The lowered string.
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
 * @param string String to look in (haystack)
 * @param string What to look for (needle)
 * @param int (optional) How much to offset
 * @return int false on needle not found, integer position if found
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
 * ups the case of a string, mb strings accounted for
 *
 * @param string The string to up.
 * @return int The uped string.
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
 * @param string The string to un-htmlentitize.
 * @return int The un-htmlentitied' string.
 */
function unhtmlentities($string)
{	
	// Replace numeric entities
	$string = preg_replace('~&#x([0-9a-f]+);~ei', 'unichr(hexdec("\\1"))', $string);
	$string = preg_replace('~&#([0-9]+);~e', 'unichr("\\1")', $string);
	
	// Replace literal entities
	$trans_tbl = get_html_translation_table(HTML_ENTITIES);
	$trans_tbl = array_flip($trans_tbl);
	
	return strtr($string, $trans_tbl);
}

/**
 * Returns any ascii to it's character (utf-8 safe).
 *
 * @param string The ascii to characterize.
 * @return int The characterized ascii.
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
 * Get the event poster.
 *
 * @param array The event data array.
 * @return string The link to the event poster.
 */
function get_event_poster($event)
{
	$event['username'] = format_name($event['username'], $event['usergroup'], $event['displaygroup']);
	$event_poster = build_profile_link($event['username'], $event['author']);
	return $event_poster;
}

/**
 * Get the event date.
 *
 * @param array The event data array.
 * @return string The event date.
 */
function get_event_date($event)
{
	global $mybb;
	
	$event_date = explode("-", $event['date']);
	$event_date = mktime(0, 0, 0, $event_date[1], $event_date[0], $event_date[2]);
	$event_date = my_date($mybb->settings['dateformat'], $event_date);

	return $event_date;
}

/**
 * Get the profile link.
 *
 * @param int The user id of the profile.
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
 * @param int The announement id of the announcement.
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
 * @param string The Username of the profile.
 * @param int The user id of the profile.
 * @param string The target frame
 * @param string Any onclick javascript.
 * @return string The complete profile link.
 */
function build_profile_link($username="", $uid=0, $target="", $onclick="")
{
	global $mybb, $lang;

	if(!$username && $uid == 0)
	{
		// Return Guest phrase for no UID, no guest nickname
		return $lang->guest;
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
 * @param int The forum id of the forum.
 * @param int (Optional) The page number of the forum.
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
 * @param int The thread id of the thread.
 * @param int (Optional) The page number of the thread.
 * @param string (Optional) The action we're performing (ex, lastpost, newpost, etc)
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
 * @param int The post ID of the post
 * @param int The thread id of the post.
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
 * @param int The event ID of the event
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
 * @param int The ID of the calendar
 * @param int The year
 * @param int The month
 * @param int The day (optional)
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
	else if($year > 0)
	{
		$link = str_replace("{year}", $year, CALENDAR_URL_YEAR);
		$link = str_replace("{calendar}", $calendar, $link);
		return htmlspecialchars_uni($link);
	}
	else
	{
		$link = str_replace("{calendar}", $calendar, CALENDAR_URL);
		return htmlspecialchars_uni($link);
	}
}

/**
 * Build the link to a specified week on the calendar
 *
 * @param int The ID of the calendar
 * @param int The year
 * @param int The week
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
 * Get the user data of a user id.
 *
 * @param int The user id of the user.
 * @return array The users data
 */
function get_user($uid)
{
	global $mybb, $db;
	static $user_cache;

	$uid = intval($uid);

	if($uid == $mybb->user['uid'])
	{
		return $mybb->user;
	}
	elseif(isset($user_cache[$uid]))
	{
		return $user_cache[$uid];
	}
	else
	{
		$query = $db->simple_select("users", "*", "uid='{$uid}'");
		$user_cache[$uid] = $db->fetch_array($query);

		return $user_cache[$uid];
	}
}

/**
 * Get the forum of a specific forum id.
 *
 * @param int The forum id of the forum.
 * @param int (Optional) If set to 1, will override the active forum status
 * @return array The database row of a forum.
 */
function get_forum($fid, $active_override=0)
{
	global $cache;
	static $forum_cache;

	if(!isset($forum_cache) || is_array($forum_cache))
	{
		$forum_cache = $cache->read("forums");
	}

	if(!$forum_cache[$fid])
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
 * @param int The thread id of the thread.
 * @param boolean Whether or not to recache the thread.
 * @return string The database row of the thread.
 */
function get_thread($tid, $recache = false)
{
	global $db;
	static $thread_cache;

	if(isset($thread_cache[$tid]) && !$recache)
	{
		return $thread_cache[$tid];
	}
	else
	{
		$query = $db->simple_select("threads", "*", "tid='".intval($tid)."'");
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
 * @param int The post id of the post.
 * @return string The database row of the post.
 */
function get_post($pid)
{
	global $db;
	static $post_cache;

	if(isset($post_cache[$pid]))
	{
		return $post_cache[$pid];
	}
	else
	{
		$query = $db->simple_select("posts", "*", "pid='".intval($pid)."'");
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
	global $forum_cache, $cache, $inactiveforums;

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
 * Will stop execution with call to error() unless
 *
 * @param bool (Optional) The function will stop execution if it finds an error with the login. Default is True
 * @return bool Number of logins when success, false if failed.
 */
function login_attempt_check($fatal = true)
{
	global $mybb, $lang, $session, $db;

	if($mybb->settings['failedlogincount'] == 0)
	{
		return 1;
	}
	// Note: Number of logins is defaulted to 1, because using 0 seems to clear cookie data. Not really a problem as long as we account for 1 being default.

	// Use cookie if possible, otherwise use session
	// Find better solution to prevent clearing cookies
	$loginattempts = 0;
	$failedlogin = 0;

	if(!empty($mybb->cookies['loginattempts']))
	{
		$loginattempts = $mybb->cookies['loginattempts'];
	}

	if(!empty($mybb->cookies['failedlogin']))
	{
		$failedlogin = $mybb->cookies['failedlogin'];
	}

	// Work out if the user has had more than the allowed number of login attempts
	if($loginattempts > $mybb->settings['failedlogincount'])
	{
		// If so, then we need to work out if they can try to login again
		// Some maths to work out how long they have left and display it to them
		$now = TIME_NOW;

		if(empty($mybb->cookies['failedlogin']))
		{
			$failedtime = $now;
		}
		else
		{
			$failedtime = $mybb->cookies['failedlogin'];
		}

		$secondsleft = $mybb->settings['failedlogintime'] * 60 + $failedtime - $now;
		$hoursleft = floor($secondsleft / 3600);
		$minsleft = floor(($secondsleft / 60) % 60);
		$secsleft = floor($secondsleft % 60);

		// This value will be empty the first time the user doesn't login in, set it
		if(empty($failedlogin))
		{
			my_setcookie('failedlogin', $now);
			if($fatal)
			{
				error($lang->sprintf($lang->failed_login_wait, $hoursleft, $minsleft, $secsleft));
			}

			return false;
		}

		// Work out if the user has waited long enough before letting them login again
		if($mybb->cookies['failedlogin'] < ($now - $mybb->settings['failedlogintime'] * 60))
		{
			my_setcookie('loginattempts', 1);
			my_unsetcookie('failedlogin');
			if($mybb->user['uid'] != 0)
			{
				$update_array = array(
					'loginattempts' => 1
				);
				$db->update_query("users", $update_array, "uid = '{$mybb->user['uid']}'");
			}
			return 1;
		}
		// Not waited long enough
		else if($mybb->cookies['failedlogin'] > ($now - $mybb->settings['failedlogintime'] * 60))
		{
			if($fatal)
			{
				error($lang->sprintf($lang->failed_login_wait, $hoursleft, $minsleft, $secsleft));
			}

			return false;
		}
	}

	// User can attempt another login
	return $loginattempts;
}

/**
 * Validates the format of an email address.
 *
 * @param string The string to check.
 * @return boolean True when valid, false when invalid.
 */
function validate_email_format($email)
{
	if(strpos($email, ' ') !== false)
	{
		return false;
	}
	// Valid local characters for email addresses: http://www.remote.org/jochen/mail/info/chars.html
	return preg_match("/^[a-zA-Z0-9&*+\-_.{}~^\?=\/]+@[a-zA-Z0-9-]+\.([a-zA-Z0-9-]+\.)*[a-zA-Z0-9-]{2,}$/si", $email);
}

/**
 * Checks to see if the email is already in use by another
 *
 * @param string The email to check.
 * @param string User ID of the user (updating only)
 * @return boolean True when in use, false when not.
 */
function email_already_in_use($email, $uid="")
{
	global $db;
	
	$uid_string = "";
	if($uid)
	{
		$uid_string = " AND uid != '".intval($uid)."'";
	}
	$query = $db->simple_select("users", "COUNT(email) as emails", "email = '".$db->escape_string($email)."'{$uid_string}");
	
	if($db->fetch_field($query, "emails") > 0)
	{
		return true;
	}
	
	return false;
}

/*
 * DEPRECATED! ONLY INCLUDED FOR COMPATIBILITY PURPOSES.
 */
function rebuildsettings()
{
	rebuild_settings();
}

/**
 * Rebuilds settings.php
 *
 */
function rebuild_settings()
{
	global $db, $mybb;

	if(!file_exists(MYBB_ROOT."inc/settings.php"))
	{
		$mode = "x";
	}
	else
	{
		$mode = "w";
	}

	$options = array(
		"order_by" => "title",
		"order_dir" => "ASC"
	);
	$query = $db->simple_select("settings", "value, name", "", $options);

	while($setting = $db->fetch_array($query))
	{
		$mybb->settings[$setting['name']] = $setting['value'];
		$setting['value'] = addcslashes($setting['value'], '\\"$');
		$settings .= "\$settings['{$setting['name']}'] = \"{$setting['value']}\";\n";
	}
	
	$settings = "<"."?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n$settings\n?".">";
	$file = @fopen(MYBB_ROOT."inc/settings.php", $mode);
	@fwrite($file, $settings);
	@fclose($file);

	$GLOBALS['settings'] = &$mybb->settings;
}

/**
 * Build a PREG compatible array of search highlight terms to replace in posts.
 *
 * @param string Incoming terms to highlight
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
	usort($words, create_function('$a,$b', 'return strlen($b) - strlen($a);'));

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
 * Converts a decimal reference of a character to its UTF-8 equivalent
 * (Code by Anne van Kesteren, http://annevankesteren.nl/2005/05/character-references)
 *
 * @param string Decimal value of a character reference
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
 * @param string The username
 * @param boolean True if the 'last used' dateline should be updated if a match is found.
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
 * @param string The email address.
 * @param boolean True if the 'last used' dateline should be updated if a match is found.
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
 * @param string The IP address.
 * @param boolean True if the 'last used' dateline should be updated if a match is found.
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
	
	foreach($banned_ips as $banned_ip)
	{
		if(!$banned_ip['filter'])
		{
			continue;
		}
		
		// Make regular expression * match
		$banned_ip['filter'] = str_replace('\*', '(.*)', preg_quote($banned_ip['filter'], '#'));
		if(preg_match("#^{$banned_ip['filter']}$#i", $ip_address))
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
 * Build a time zone selection list.
 *
 * @param string The name of the select
 * @param int The selected time zone (defaults to GMT)
 * @param boolean True to generate a "short" list with just timezone and current time
 */
function build_timezone_select($name, $selected=0, $short=false)
{
	global $mybb, $lang;

	$timezones = array(
		"-12" => $lang->timezone_gmt_minus_1200,
		"-11" => $lang->timezone_gmt_minus_1100,
		"-10" => $lang->timezone_gmt_minus_1000,
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
		"6" => $lang->timezone_gmt_600,
		"7" => $lang->timezone_gmt_700,
		"8" => $lang->timezone_gmt_800,
		"9" => $lang->timezone_gmt_900,
		"9.5" => $lang->timezone_gmt_950,
		"10" => $lang->timezone_gmt_1000,
		"11" => $lang->timezone_gmt_1100,
		"12" => $lang->timezone_gmt_1200
	);

	$selected = str_replace("+", "", $selected);
	$select = "<select name=\"{$name}\" id=\"{$name}\">\n";
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
				}
				else
				{
					$label .= ":00";
				}
			}
			$time_in_zone = my_date($mybb->settings['timeformat'], TIME_NOW, $timezone);
			$label = $lang->sprintf($lang->timezone_gmt_short, $label." ", $time_in_zone);
		}
		$select .= "<option value=\"{$timezone}\"{$selected_add}>{$label}</option>\n";
	}
	$select .= "</select>";
	return $select;
}

/**
 * Fetch the contents of a remote fle.
 *
 * @param string The URL of the remote file
 * @return string The remote file contents.
 */
function fetch_remote_file($url, $post_data=array())
{
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
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if(!empty($post_body))
		{
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_body);
		}
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}
 	else if(function_exists("fsockopen"))
	{
		$url = @parse_url($url);
		if(!$url['host'])
		{
			return false;
		}
		if(!$url['port'])
		{
			$url['port'] = 80;
		}
		if(!$url['path'])
		{
			$url['path'] = "/";
		}
		if($url['query'])
		{
			$url['path'] .= "?{$url['query']}";
		}
		$fp = @fsockopen($url['host'], $url['port'], $error_no, $error, 10);
		@stream_set_timeout($fp, 10);
		if(!$fp)
		{
			return false;
		}
		$headers = array();
		if(!empty($post_body))
		{
			$headers[] = "POST {$url['path']} HTTP/1.0";
			$headers[] = "Content-Length: ".strlen($post_body);
			$headers[] = "Content-Type: application/x-www-form-urlencoded";
		}
		else
		{
			$headers[] = "GET {$url['path']} HTTP/1.0";
		}
		
		$headers[] = "Host: {$url['host']}";
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
		while(!feof($fp))
		{
			$data .= fgets($fp, 12800);
		}
		fclose($fp);
		$data = explode("\r\n\r\n", $data, 2);
		return $data[1];
	}
	else if(empty($post_data))
	{
		return @implode("", @file($url));
	}
	else
	{
		return false;
	}
}

/**
 * Checks if a particular user is a super administrator.
 *
 * @param int The user ID to check against the list of super admins
 * @return boolean True if a super admin, false if not
 */
function is_super_admin($uid)
{
	global $mybb;
	
	$mybb->config['super_admins'] = str_replace(" ", "", $mybb->config['super_admins']);
	if(my_strpos(",{$mybb->config['super_admins']},", ",{$uid},") === false)
	{
		return false;
	}
	else
	{
		return true;
	}
}

/**
 * Split a string based on the specified delimeter, ignoring said delimeter in escaped strings.
 * Ex: the "quick brown fox" jumped, could return 1 => the, 2 => quick brown fox, 3 => jumped
 *
 * @param string The delimeter to split by
 * @param string The string to split
 * @param string The escape character or string if we have one.
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
 * Fetch an IPv4 long formatted range for searching IPv4 IP addresses.
 *
 * @param string The IP address to convert to a range based LONG
 * @rturn mixed If a full IP address is provided, the ip2long equivalent, otherwise an array of the upper & lower extremities of the IP
 */
function fetch_longipv4_range($ip)
{
	$ip_bits = explode(".", $ip);
	$ip_string1 = $ip_string2 = "";

	if($ip == "*")
	{
		return array(my_ip2long('128.0.0.0'), my_ip2long('127.255.255.255'));
	}

	if(strpos($ip, ".*") === false)
	{
		$ip = str_replace("*", "", $ip);
		if(count($ip_bits) == 4)
		{
			return my_ip2long($ip);
		}
		else
		{
			return array(my_ip2long($ip.".0"), my_ip2long($ip.".255"));
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
		return array(my_ip2long($ip_string1), my_ip2long($ip_string2));
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
 * @param string The ban length string
 * @param int The optional UNIX timestamp, if 0, current time is used.
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
	return mktime(date("G"), date("i"), 0, $n[2], $n[1], $n[3]);
}

/**
 * Expire old warnings in the database.
 *
 */
function expire_warnings()
{
	global $db;
	
	$users = array();
	
	$query = $db->query("
		SELECT w.wid, w.uid, w.points, u.warningpoints
		FROM ".TABLE_PREFIX."warnings w
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.uid)
		WHERE expires<".TIME_NOW." AND expires!=0 AND expired!=1
	");
	while($warning = $db->fetch_array($query))
	{
		$updated_warning = array(
			"expired" => 1
		);
		$db->update_query("warnings", $updated_warning, "wid='{$warning['wid']}'");
		
		if(array_key_exists($warning['uid'], $users))
		{
			$users[$warning['uid']] -= $warning['points'];
		}
		else
		{
			$users[$warning['uid']] = $warning['warningpoints']-$warning['points'];
		}
	}
	
	foreach($users as $uid => $warningpoints)
	{
		if($warningpoints < 0)
		{
			$warningpoints = 0;
		}
		
		$updated_user = array(
			"warningpoints" => intval($warningpoints)
		);
		$db->update_query("users", $updated_user, "uid='".intval($uid)."'");
	}
}

/**
 * Custom chmod function to fix problems with hosts who's server configurations screw up umasks
 *
 * @param string The file to chmod
 * @param string The mode to chmod(i.e. 0666)
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
 * @param string The path to the directory
 * @param array Any files you wish to ignore (optional)
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
 * @param array The array of forums
 * @return integer The number of sub forums
 */
function subforums_count($array)
{
	$count = 0;
	foreach($array as $array2)
	{
		$count += count($array2);
	}
	
	return $count;
}

/**
 * Fix for PHP's ip2long to guarantee a 32-bit signed integer value is produced (this is aimed
 * at 64-bit versions of PHP)
 *
 * @param string The IP to convert
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
 * As above, fix for PHP's long2ip on 64-bit versions
 *
 * @param integer The IP to convert (will accept 64-bit IPs as well)
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
 * Processes a checksum list on MyBB files and returns a result set
 *
 * @param array The array of checksums and their corresponding files
 * @return array The bad files
 */
function verify_files($path=MYBB_ROOT, $count=0)
{
	global $mybb, $checksums, $bad_verify_files;
	
	// We don't need to check these types of files
	$ignore = array(".", "..", ".svn", "config.php", "settings.php", "Thumb.db", "config.default.php", "lock", "htaccess.txt", "logo.gif");
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
					$contents = '';
					while(!feof($handle))
					{
						$contents .= fread($handle, 8192);
					}
					fclose($handle);
					
					$md5 = md5($contents);
					
					// Does it match any of our hashes (unix/windows new lines taken into consideration with the hashes)
					if(!in_array($md5, $checksums[$file_path]))
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
 * @param int The integer
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
 * Returns a securely generated seed for PHP's RNG (Random Number Generator)
 *
 * @param int Length of the seed bytes (8 is default. Provides good cryptographic variance)
 * @return int An integer equivalent of a secure hexadecimal seed
 */
function secure_seed_rng($count=8)
{
	$output = '';
	
	// Try the unix/linux method
	if(@is_readable('/dev/urandom') && ($handle = @fopen('/dev/urandom', 'rb')))
	{
		$output = @fread($handle, $count);
		@fclose($handle);
	}
	
	// Didn't work? Do we still not have enough bytes? Use our own (less secure) rng generator
	if(strlen($output) < $count)
	{
		$output = '';
		
		// Close to what PHP basically uses internally to seed, but not quite.
		$unique_state = microtime().@getmypid();
		
		for($i = 0; $i < $count; $i += 16)
		{
			$unique_state = md5(microtime().$unique_state);
			$output .= pack('H*', md5($unique_state));
		}
	}
	
	// /dev/urandom and openssl will always be twice as long as $count. base64_encode will roughly take up 33% more space but crc32 will put it to 32 characters
	$output = hexdec(substr(dechex(crc32(base64_encode($output))), 0, $count));
	
	return $output;
}

/**
 * Wrapper function for mt_rand. Automatically seeds using a secure seed once.
 *
 * @param int Optional lowest value to be returned (default: 0) 
 * @param int Optional highest value to be returned (default: mt_getrandmax()) 
 * @param boolean True forces it to reseed the RNG first
 * @return int An integer equivalent of a secure hexadecimal seed
 */
function my_rand($min=null, $max=null, $force_seed=false)
{
	static $seeded = false;
	static $obfuscator = 0;

	if($seeded == false || $force_seed == true)
	{
		mt_srand(secure_seed_rng());
		$seeded = true;

		$obfuscator = abs((int) secure_seed_rng());
		
		// Ensure that $obfuscator is <= mt_getrandmax() for 64 bit systems.
		if($obfuscator > mt_getrandmax())
		{
			$obfuscator -= mt_getrandmax();
		}
	}

	if($min !== null && $max !== null)
	{
		$distance = $max - $min;
		if ($distance > 0)
		{
			return $min + (int)((float)($distance + 1) * (float)(mt_rand() ^ $obfuscator) / (mt_getrandmax() + 1));
		}
		else
		{
			return mt_rand($min, $max);
		}
	}
	else
	{
		$val = mt_rand() ^ $obfuscator;
		return $val;
	}
}

/**
 * More robust version of PHP's trim() function. It includes a list of UTF-16 blank characters
 * from http://kb.mozillazine.org/Network.IDN.blacklist_chars
 *
 * @param string The string to trim from
 * @param string Optional. The stripped characters can also be specified using the charlist parameter
 * @return string The trimmed string
 */
function trim_blank_chrs($string, $charlist=false)
{
	$hex_chrs = array(
		0x20 => 1,
		0x09 => 1,
		0x0A => 1,
		0x0D => 1,
		0x0B => 1,
		0xAD => 1,
		0xA0 => 1,
		0xAD => 1,
		0xBF => 1,
		0x81 => 1,
		0x8D => 1,
		0x90 => 1,
		0x9D => 1,
		0xCC => array(0xB7 => 1, 0xB8 => 1), // \x{0337} or \x{0338}
		0xE1 => array(0x85 => array(0x9F => 1, 0xA0 => 1)), // \x{115F} or \x{1160}
		0xE2 => array(0x80 => array(0x80 => 1, 0x81 => 1, 0x82 => 1, 0x83 => 1, 0x84 => 1, 0x85 => 1, 0x86 => 1, 0x87 => 1, 0x88 => 1, 0x89 => 1, 0x8A => 1, 0x8B => 1, // \x{2000} to \x{200B}
									0xA8 => 1, 0xA9 => 1, 0xAA => 1, 0xAB => 1, 0xAC => 1, 0xAD => 1, 0xAE => 1, 0xAF => 1), // \x{2028} to \x{202F}
					  0x81 => array(0x9F => 1)), // \x{205F}
		0xE3 => array(0x80 => array(0x80 => 1), // \x{3000}
					  0x85 => array(0xA4 => 1)), // \x{3164}
		0xEF => array(0xBB => array(0xBF => 1), // \x{FEFF}
					  0xBE => array(0xA0 => 1), // \x{FFA0}
					  0xBF => array(0xB9 => 1, 0xBA => 1, 0xBB => 1)), // \x{FFF9} to \x{FFFB}
	);
	
	$hex_chrs_rev = array(
		0x20 => 1,
		0x09 => 1,
		0x0A => 1,
		0x0D => 1,
		0x0B => 1,
		0xA0 => array(0xC2 => 1),
		0xAD => array(0xC2 => 1),
		0xBF => array(0xC2 => 1),
		0x81 => array(0xC2 => 1),
		0x8D => array(0xC2 => 1),
		0x90 => array(0xC2 => 1),
		0x9D => array(0xC2 => 1),
		0xB8 => array(0xCC => 1), // \x{0338}
		0xB7 => array(0xCC => 1), // \x{0337}
		0xA0 => array(0x85 => array(0xE1 => 1)), // \x{1160}
		0x9F => array(0x85 => array(0xE1 => 1), // \x{115F}
					  0x81 => array(0xE2 => 1)), // \x{205F}
		0x80 => array(0x80 => array(0xE3 => 1, 0xE2 => 1)), // \x{3000}, \x{2000}
		0x81 => array(0x80 => array(0xE2 => 1)), // \x{2001}
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
		0xA8 => array(0x80 => array(0xE2 => 1)), // \x{2028}
		0xA9 => array(0x80 => array(0xE2 => 1)), // \x{2029}
		0xAA => array(0x80 => array(0xE2 => 1)), // \x{202A}
		0xAB => array(0x80 => array(0xE2 => 1)), // \x{202B}
		0xAC => array(0x80 => array(0xE2 => 1)), // \x{202C}
		0xAD => array(0x80 => array(0xE2 => 1)), // \x{202D}
		0xAE => array(0x80 => array(0xE2 => 1)), // \x{202E}
		0xAF => array(0x80 => array(0xE2 => 1)), // \x{202F}
		0xA4 => array(0x85 => array(0xE3 => 1)), // \x{3164}
		0xBF => array(0xBB => array(0xEF => 1)), // \x{FEFF}
		0xA0 => array(0xBE => array(0xEF => 1)), // \x{FFA0}
		0xB9 => array(0xBF => array(0xEF => 1)), // \x{FFF9}
		0xBA => array(0xBF => array(0xEF => 1)), // \x{FFFA}
		0xBB => array(0xBF => array(0xEF => 1)), // \x{FFFB}
	);
	
	// Start from the beginning and work our way in
	do
	{
		// Check to see if we have matched a first character in our utf-16 array
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
		// Check to see if we have matched a first character in our utf-16 array
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
	
	if($charlist !== false)
	{
		$string = trim($string, $charlist);
	}
	else
	{
		$string = trim($string);
	}
	
	return $string;
}

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

?>