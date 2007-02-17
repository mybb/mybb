<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/license.php
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
	global $querytime, $debug, $templatecache, $templatelist, $maintimer, $globaltime, $parsetime;

	$contents = parse_page($contents);
	$totaltime = $maintimer->stop();

	if($mybb->usergroup['cancp'] == "yes")
	{
		$phptime = $maintimer->format($maintimer->totaltime - $querytime);
		$querytime = $maintimer->format($querytime);

		$percentphp = number_format((($phptime/$maintimer->totaltime) * 100), 2);
		$percentsql = number_format((($querytime/$maintimer->totaltime) * 100), 2);

		$phpversion = phpversion();

		$serverload = get_server_load();

		if(my_strpos(getenv("REQUEST_URI"), "?"))
		{
			$debuglink = htmlspecialchars(getenv("REQUEST_URI")) . "&amp;debug=1";
		}
		else
		{
			$debuglink = htmlspecialchars(getenv("REQUEST_URI")) . "?debug=1";
		}

		if($mybb->settings['gzipoutput'] != "no")
		{
			$gzipen = "Enabled";
		}
		else
		{
			$gzipen = "Disabled";
		}

		if($mybb->settings['extraadmininfo'] != "no")
		{
			if(function_exists("memory_get_usage"))
			{
				$memory_usage = " / Memory Usage: ".get_friendly_size(memory_get_usage());
			}

			$other = "PHP version: $phpversion / Server Load: $serverload / GZip Compression: $gzipen";
			$debugstuff = "Generated in $totaltime seconds ($percentphp% PHP / $percentsql% MySQL)<br />MySQL Queries: $db->query_count /  Global Parsing Time: $globaltime$memory_usage<br />$other<br />[<a href=\"$debuglink\" target=\"_blank\">advanced details</a>]<br />";
			$contents = str_replace("<debugstuff>", $debugstuff, $contents);
		}

		if(isset($mybb->input['debug']))
		{
			debug_page();
		}
	}

	$contents = str_replace("<debugstuff>", "", $contents);
	$contents = $plugins->run_hooks("pre_output_page", $contents);

	if($mybb->settings['gzipoutput'] != "no")
	{
		if(version_compare(PHP_VERSION, '4.2.0', '>='))
		{
			$contents = gzip_encode($contents, $mybb->settings['gziplevel']);
		}
		else
		{
			$contents = gzip_encode($contents);
		}
	}
	echo $contents;

	$plugins->run_hooks("post_output_page");

	// If the use shutdown functionality is turned off, run any shutdown related items now.
	if(($mybb->settings['useshutdownfunc'] == "no" || phpversion() >= '5.0.5') && $mybb->use_shutdown != true)
	{
		run_shutdown();
	}
}

/**
 * Adds a function to the list of functions to run on shutdown.
 *
 * @param string The name of the function.
 * @return boolean True if function exists, otherwise false.
 */
function add_shutdown($name)
{
	global $shutdown_functions;

	if(function_exists($name))
	{
		$shutdown_functions[$name] = $name;
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
	global $config, $db, $cache, $plugins, $shutdown_functions, $done_shutdown;

	if($done_shutdown == true || !$config)
	{
		return;
	}

	// If our DB has been deconstructed already (bad PHP 5.2.0), reconstruct
	if(!is_object($db))
	{
		require MYBB_ROOT."inc/config.php";
		if(isset($config))
		{
			require_once MYBB_ROOT."inc/db_".$config['dbtype'].".php";
			$db = new databaseEngine;
			$db->connect($config['hostname'], $config['username'], $config['password']);
			$db->select_db($config['database']);
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

	// And finaly.. plugins
	if(!is_object($plugins) && !defined("NO_PLUGINS"))
	{
		require_once MYBB_ROOT."inc/class_plugins.php";
		$plugins = new pluginSystem;
		$plugins->load();
	}

	// We have some shutdown queries needing to be run
	if(is_array($db->shutdown_queries))
	{
		// Loop through and run them all
		foreach($db->shutdown_queries as $query)
		{
			$db->query($query);
		}
	}

	// Run any shutdown functions if we have them
	if(is_array($shutdown_functions))
	{
		foreach($shutdown_functions as $function)
		{
			$function();
		}
	}

	$done_shutdown = true;
}

/**
 * Sends a specified amount of messages from the mail queue
 *
 * @param int The number of messages to send (Defaults to 20)
 */
function send_mail_queue($count=10)
{
	global $db, $cache, $plugins;

	$plugins->run_hooks("send_mail_queue_start");

	// Check to see if the mail queue has messages needing to be sent
	$mailcache = $cache->read("mailqueue");
	if($mailcache['queue_size'] > 0 && ($mailcache['locked'] == 0 || $mailcache['locked'] < time()-300))
	{
		// Lock the queue so no other messages can be sent whilst these are (for popular boards)
		$cache->update_mailqueue(0, time());

		// Fetch emails for this page view - and send them
		$query = $db->simple_select("mailqueue", "*", "", array("order_by" => "mid", "order_dir" => "asc", "limit_start" => 0, "limit" => $count));

		$plugins->run_hooks_by_ref("send_mail_queue_mail", $query);

		while($email = $db->fetch_array($query))
		{
			// Delete the message from the queue
			$db->delete_query("mailqueue", "mid='{$email['mid']}'");
			
			my_mail($email['mailto'], $email['subject'], $email['message'], $email['mailfrom'], "", $email['headers']);
		}
		// Update the mailqueue cache and remove the lock
		$cache->update_mailqueue(time(), 0);
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
	global $db, $lang, $theme, $mybb, $htmldoctype, $archive_url, $error_handler;

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

	if($lang->settings['rtl'] == 1)
	{
		$contents = str_replace("<html", "<html dir=\"rtl\"", $contents);
	}

	if($lang->settings['htmllang'])
	{
		$contents = str_replace("<html", "<html lang=\"".$lang->settings['htmllang']."\"", $contents);
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

	// If the stamp isn't set, use time()
	if(empty($stamp))
	{
		$stamp = time();
	}

	if(!$offset && $offset != '0')
	{
		if($mybb->user['uid'] != 0 && array_key_exists("timezone", $mybb->user))
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
		if($dstcorrection == "yes")
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
		$stamp = time();
		
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

	$plugins->run_hooks_by_ref("my_date", $date);

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
 */
function my_mail($to, $subject, $message, $from="", $charset="", $headers="", $keep_alive=false)
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
	$mail->build_message($to, $subject, $message, $from, $charset, $headers);
	return $mail->send();
}

/**
 * Return a parent list for the specified forum.
 *
 * @param int The forum id to get the parent list for.
 * @return string The comma-separated parent list.
 */
function get_parent_list($fid)
{
	global $db, $forum_cache;
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
	global $forum_cache, $db, $cache;
	
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
 * Produce a friendly error message page
 *
 * @param string The error message to be shown
 * @param string The title of the message shown in the title of the page and the error table
 */
function error($error="", $title="")
{
	global $header, $footer, $theme, $headerinclude, $db, $templates, $lang, $mybb;

	if(!$error)
	{
		$error = $lang->unknown_error;
	}

	// AJAX error message?
	if($mybb->input['ajax'])
	{
		// Send our headers.
		header("Content-type: text/html; charset={$lang->settings['charset']}");
		echo "<error>{$error}</error>\n";
		exit;
	}

	if(!$title)
	{
		$title = $mybb->settings['bbname'];
	}

	$timenow = my_date($mybb->settings['dateformat'], time()) . " " . my_date($mybb->settings['timeformat'], time());
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
		header("Content-type: text/html; charset={$lang->settings['charset']}");
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

	$time = time();
	$plugins->run_hooks("no_permission");

	$noperm_array = array (
		"nopermission" => '1',
		"location1" => 0,
		"location2" => 0
	);

	$db->update_query("sessions", $noperm_array, "sid='".$session->sid."'");
	$url = htmlspecialchars_uni($_SERVER['REQUEST_URI']);

	if($mybb->input['ajax'])
	{
		exit;
	}

	if($mybb->user['uid'])
	{
		$lang->error_nopermission_user_5 = sprintf($lang->error_nopermission_user_5, $mybb->user['username']);
		eval("\$errorpage = \"".$templates->get("error_nopermission_loggedin")."\";");
	}
	else
	{
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

	if($mybb->input['ajax'])
	{
		// Send our headers.
		header("Content-type: text/html; charset={$lang->settings['charset']}");
		echo "<script type=\"text/javascript\">\n";
		if($message != "")
		{
			echo "alert('{$message}');\n";
		}
		echo "window.location = '{$url}';\n";
		echo "</script>\n";
		exit;
	}

	if(!$message)
	{
		$message = $lang->redirect;
	}

	$time = time();
	$timenow = my_date($mybb->settings['dateformat'], $time) . " " . my_date($mybb->settings['timeformat'], $time);
	$plugins->run_hooks("redirect");

	if(!$title)
	{
		$title = $mybb->settings['bbname'];
	}

	if($mybb->settings['redirects'] == "on" && $mybb->user['showredirect'] != "no")
	{
		$url = str_replace("&amp;", "&", $url);
		$url = htmlspecialchars($url);

		eval("\$redirectpage = \"".$templates->get("redirect")."\";");
		output_page($redirectpage);
	}
	else
	{
		$url = str_replace("#", "&#", $url);
		$url = unhtmlspecialchars($url);
		$url = str_replace(array("\n","\r",";"), "", $url);

		run_shutdown();
		header("Location: $url");
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
function multipage($count, $perpage, $page, $url)
{
	global $theme, $templates, $lang, $mybb;

	if($count > $perpage)
	{
		$pages = $count / $perpage;
		$pages = ceil($pages);

		if($page > 1)
		{
			$prev = $page - 1;
			$page_url = fetch_page_url($url, $prev);
			eval("\$prevpage = \"".$templates->get("multipage_prevpage")."\";");
		}

		if($page < $pages)
		{
			$next = $page + 1;
			$page_url = fetch_page_url($url, $next);
			eval("\$nextpage = \"".$templates->get("multipage_nextpage")."\";");
		}

		if($page > 4)
		{
			$from = $page-4;
		}
		else
		{
			$from = $page-1;
		}

		if($from < 1)
		{
			$from = 1;
		}

		if($page == $pages)
		{
			$to = $pages;
		}
		elseif($page == $pages-1)
		{
			$to = $page+1;
		}
		elseif($page == $pages-2)
		{
			$to = $page+2;
		}
		elseif($page == $pages-3)
		{
			$to = $page+3;
		}
		else
		{
			$to = $page+4;
		}

		for($i = $from; $i <= $to; ++$i)
		{
			$page_url = fetch_page_url($url, $i);
			$plate = "multipage_page".(($i==$page) ? "_current":"");
			eval("\$mppage .= \"".$templates->get($plate)."\";");
		}

		$lang->multipage_pages = sprintf($lang->multipage_pages, $pages);
		$page_url = fetch_page_url($url, 1);
		eval("\$start = \"".$templates->get("multipage_start")."\";");
		$page_url = fetch_page_url($url, $pages);
		eval("\$end = \"".$templates->get("multipage_end")."\";");
		eval("\$multipage = \"".$templates->get("multipage")."\";");

		return $multipage;
	}
}

/**
 * Generate a page URL for use by the multipage function
 *
 * @param string The URL being passed
 * @param int The page number
 */
function fetch_page_url($url, $page)
{
	// If no page identifier is specified we tack it on to the end of the URL
	if(strpos($url, "{page}") === false)
	{
		if(strpos($url, "?") === false)
		{
			$url .= "?";
		}
		else
		{
			$url .= "&";
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

				$zerogreater = 0;

				if(in_array($perm, $groupzerogreater))
				{
					if($access == 0)
					{
						$usergroup[$perm] = 0;
						$zerogreater = 1;
					}
				}

				if(($access > $permbit || ($access == "yes" && $permbit == "no") || !$permbit) && $zerogreater != 1)
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
 * @param int The user to build the permissions for (0 assumes current logged in user)
 * @param int The group of the user to build permissions for (0 will fetch it)
 * @return array Forum permissions for the specific forum or forums
 */
function forum_permissions($fid=0, $uid=0, $gid=0)
{
	global $db, $cache, $groupscache, $forum_cache, $fpermcache, $mybb, $usercache, $fpermissionscache;

	if($uid == 0)
	{
		$uid = $mybb->user['uid'];
	}

	if(!$gid || $gid == 0) // If no group, we need to fetch it
	{
		if($uid != $mybb->user['uid'])
		{
			if($usercache[$uid])
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
		$permissions = fetch_forum_permissions($fid, $gid, $groupperms);
	}
	else
	{
		foreach($forum_cache as $forum)
		{
			$permissions[$forum['fid']] = fetch_forum_permissions($forum['fid'], $gid, $groupperms);
		}
	}
	return $permissions;
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

	if(!$fpermcache[$fid]) // This forum has no custom or inherited permisssions so lets just return the group permissions
	{
		return $groupperms;
	}
	
	$current_permissions = array();
	
	foreach($groups as $gid)
	{
		if($groupscache[$gid])
		{
			// If this forum has permissions set
			if($fpermcache[$fid][$gid])
			{
				$level_permissions = $fpermcache[$fid][$gid];
				foreach($level_permissions as $permission => $access)
				{
					if($access >= $current_permissions[$permission] || ($access == "yes" && $current_permissions[$permission] == "no") || !$current_permissions[$permission])
					{
						$current_permissions[$permission] = $access;
					}
				}
			}
		}
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
 * @param string The plain text password for the forum
 */
function check_forum_password($fid, $password="")
{
	global $mybb, $header, $footer, $headerinclude, $theme, $templates, $lang;
	$showform = 1;

	if($password)
	{
		if($mybb->input['pwverify'])
		{
			if($password == $mybb->input['pwverify'])
			{
				my_setcookie("forumpass[$fid]", md5($mybb->user['uid'].$mybb->input['pwverify']), null, true);
				$showform = 0;
			}
			else
			{
				eval("\$pwnote = \"".$templates->get("forumdisplay_password_wrongpass")."\";");
				$showform = 1;
			}
		}
		else
		{
			if(!$_COOKIE['forumpass'][$fid] || ($_COOKIE['forumpass'][$fid] && md5($mybb->user['uid'].$password) != $_COOKIE['forumpass'][$fid]))
			{
				$showform = 1;
			}
			else
			{
				$showform = 0;
			}
		}
	}
	else
	{
		$showform = 0;
	}

	if($showform)
	{
		$_SERVER['REQUEST_URI'] = htmlspecialchars_uni($_SERVER['REQUEST_URI']);
		eval("\$pwform = \"".$templates->get("forumdisplay_password")."\";");
		output_page($pwform);
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
	global $mybb, $db;
	static $modpermscache;

	if($uid < 1)
	{
		$uid = $mybb->user['uid'];
	}

	if(!isset($modpermscache[$uid][$fid]))
	{
		if(!$parentslist)
		{
			$parentslist = get_parent_list($fid);
		}

		$sql = build_parent_list($fid, "fid", "OR", $parentslist);
		$query = $db->simple_select("moderators", "*", "uid='{$uid}' AND {$sql}");
		$perms = $db->fetch_array($query);
		$modpermscache[$uid][$fid] = $perms;
	}
	else
	{
		$perms = $modpermscache[$uid][$fid];
	}

	return $perms;
}

/**
 * Checks if a moderator has permissions to perform an action in a specific forum
 *
 * @param int The forum ID (0 assumes global)
 * @param string The action tyring to be performed. (blank assumes any action at all)
 * @param int The user ID (0 assumes current user)
 * @return yes|no Returns yes if the user has permission, no if they do not
 */
function is_moderator($fid="0", $action="", $uid="0")
{
	global $mybb, $db;

	if($uid == 0)
	{
		$uid = $mybb->user['uid'];
	}

	if($mybb->usergroup['issupermod'] == "yes")
	{
		return true;
	}
	else
	{
		if(!$fid)
		{
			$query = $db->simple_select('moderators', 'mid', "uid={$uid}", array('limit' => 1));
			$modcheck = $db->fetch_array($query);

			if($modcheck['mid'])
			{
				return true;
			}
			else
			{
				return false;
			}
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
				if($modperms[$action] == "yes")
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
	global $mybb, $db, $icon, $theme, $templates, $lang;

	$listed = 0;
	if($mybb->input['icon'])
	{
		$icon = $mybb->input['icon'];
	}

	$no_icons_checked = " checked=\"checked\"";
	$query = $db->simple_select("icons", "*", "", array('order_by' => 'name', 'order_dir' => 'DESC'));
	while($dbicon = $db->fetch_array($query))
	{
		if($icon == $dbicon['iid'])
		{
			$iconlist .= "<input type=\"radio\" name=\"icon\" value=\"".$dbicon['iid']."\" checked=\"checked\" /> <img src=\"".$dbicon['path']."\" alt=\"".$dbicon['name']."\" />";
			$no_icons_checked = "";
		}
		else
		{
			$iconlist .= "<input type=\"radio\" name=\"icon\" value=\"".$dbicon['iid']."\" /> <img src=\"".$dbicon['path']."\" alt=\"".$dbicon['name']."\" />";
		}

		++$listed;
		if($listed == 9)
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
		if($mybb->user['remember'] == "no")
		{
			$expires = 0;
		}
		else
		{
			$expires = time() + (60*60*24*365); // Make the cookie expire in a years time
		}
	}
	else
	{
		$expires = time() + intval($expires);
	}

	$mybb->settings['cookiepath'] = str_replace(array("\n","\r"), "", $mybb->settings['cookiepath']);
	$mybb->settings['cookiedomain'] = str_replace(array("\n","\r"), "", $mybb->settings['cookiedomain']);

	// Versions of PHP prior to 5.2 do not support HttpOnly cookies and IE is buggy when specifying a blank domain so set the cookie manually
	$cookie = "Set-Cookie: {$name}=".urlencode($value);

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
	if(!isset($_COOKIE['mybb'][$name]))
	{
		return false;
	}

	$cookie = unserialize($_COOKIE['mybb'][$name]);

	if(isset($cookie[$id]))
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
function my_set_array_cookie($name, $id, $value)
{
	$cookie = $_COOKIE['mybb'];
	$newcookie = unserialize($cookie[$name]);
	$newcookie[$id] = $value;
	$newcookie = addslashes(serialize($newcookie));
	my_setcookie("mybb[$name]", $newcookie);
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

	if(my_strtolower(substr(PHP_OS, 0, 3)) !== 'win')
	{
		if(@file_exists("/proc/loadavg"))
		{
			$load = @file_get_contents("/proc/loadavg");
			$serverload = explode(" ", $load);
			$serverload[0] = round($serverload[0], 4);
		}

		if(!$serverload)
		{
			$load = @exec("uptime");
			$load = split("load averages?: ", $load);
			$serverload = explode(",", $load[1]);
		}
	}
	else if(class_exists('COM'))
	{
		$wmi = new COM("WinMgmts:\\\\.");
		$cpus = $wmi->InstancesOf("Win32_Processor");

		$cpuload = 0;
		$i = 0;

		if(version_compare(PHP_VERSION, '5.0.0', '>=') == 1)
		{
			// PHP 5
			foreach($cpus as $cpu)
			{
				$serverload[0] += $cpu->LoadPercentage;
				++$i;
			}
		}
		else
		{
			// PHP 4
			while ($cpu = $cpus->Next())
			{
				$serverload[0] += $cpu->LoadPercentage;
				++$i;
			}
		}

		if($i > 1)
		{
			$serverload[0] = round($serverload[0] / $i, 2);
		}
	}
	else
	{
		return $lang->unknown;
	}

	$returnload = trim($serverload[0]);

	if(!$returnload)
	{
		$returnload = $lang->unknown;
	}
	return $returnload . ' %';
}

/**
 * Update the forum counters for a specific forum
 *
 * @param int The forum ID
 */
function update_forum_count($fid)
{
	global $db, $cache;

	// Fetch the last post for this forum
	$query = $db->query("
		SELECT tid, lastpost, lastposter, lastposteruid, subject
		FROM ".TABLE_PREFIX."threads
		WHERE fid='{$fid}' AND visible='1' AND closed NOT LIKE 'moved|%'
		ORDER BY lastpost DESC
		LIMIT 0, 1
	");
	$lastpost = $db->fetch_array($query);

	// Fetch the number of threads and replies in this forum (Approved only)
	$query = $db->query("
		SELECT COUNT(*) AS threads, SUM(replies) AS replies
		FROM ".TABLE_PREFIX."threads
		WHERE fid='$fid' AND visible='1' AND closed	NOT LIKE 'moved|%'
	");
	$count = $db->fetch_array($query);
	$count['posts'] = $count['threads'] + $count['replies'];

	// Fetch the number of threads and replies in this forum (Unapproved only)
	$query = $db->query("
		SELECT COUNT(*) AS threads
		FROM ".TABLE_PREFIX."threads
		WHERE fid='$fid' AND visible='0' AND closed NOT LIKE 'moved|%'
	");
	$unapproved_count['threads'] = $db->fetch_field($query, "threads");

	$query = $db->query("
		SELECT SUM(unapprovedposts) AS posts
		FROM ".TABLE_PREFIX."threads
		WHERE fid='$fid' AND closed NOT LIKE 'moved|%'
	");
	$unapproved_count['posts'] = $db->fetch_field($query, "posts");

	$update_count = array(
		"posts" => intval($count['posts']),
		"threads" => intval($count['threads']),
		"unapprovedposts" => intval($unapproved_count['posts']),
		"unapprovedthreads" => intval($unapproved_count['threads']),
		"lastpost" => intval($lastpost['lastpost']),
		"lastposter" => $db->escape_string($lastpost['lastposter']),
		"lastposteruid" => intval($lastpost['lastposteruid']),
		"lastposttid" => intval($lastpost['tid']),
		"lastpostsubject" => $db->escape_string($lastpost['subject'])
	);

	$db->update_query("forums", $update_count, "fid='{$fid}'");
}

/**
 * Update the thread counters for a specific thread
 *
 * @param int The thread ID
 */
function update_thread_count($tid)
{
	global $db, $cache;

	$query = $db->simple_select("posts", "COUNT(*) AS replies", "tid='{$tid}' AND visible='1'");
	$replies = $db->fetch_array($query);

	$treplies = $replies['replies'] - 1;

	if($treplies < 0)
	{
		$treplies = 0;
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

	$query = $db->query("
		SELECT u.uid, u.username, p.username AS postusername, p.dateline
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE p.tid='$tid'
		ORDER BY p.dateline ASC
		LIMIT 1
	");
	$firstpost = $db->fetch_array($query);

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

	// Unapproved posts
	$query = $db->simple_select("posts", "COUNT(*) AS totunposts", "tid='{$tid}' AND visible='0'");
	$nounposts = $db->fetch_field($query, "totunposts");

	// Update the attachment count for this thread
	update_thread_attachment_count($tid);

	$update_array = array(
		'username' => $firstpost['username'],
		'uid' => intval($firstpost['uid']),
		'lastpost' => intval($lastpost['dateline']),
		'lastposter' => $lastpost['username'],
		'lastposteruid' => intval($lastpost['uid']),
		'replies' => $treplies,
		'unapprovedposts' => $nounposts
	);
	$db->update_query("threads", $update_array, "tid='{$tid}'");
}

/**
 * Updates the number of attachments for a specific thread
 *
 * @param int The thread ID
 */
function update_thread_attachment_count($tid)
{
	global $db;

	$query = $db->query("
		SELECT COUNT(*) AS attachment_count
		FROM ".TABLE_PREFIX."attachments a
		LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
		WHERE p.tid='$tid'
	");
	$attachment_count = $db->fetch_field($query, "attachment_count");

	$db->query("
		UPDATE ".TABLE_PREFIX."threads
		SET attachmentcount='{$attachment_count}'
		WHERE tid='$tid'
	");
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
 * @param array Array of permissions
 * @param string The name of the forum jump
 * @return string Forum jump items
 */
function build_forum_jump($pid="0", $selitem="", $addselect="1", $depth="", $showextras="1", $permissions="", $name="fid")
{
	global $db, $forum_cache, $fjumpcache, $permissioncache, $mybb, $selecteddone, $forumjump, $forumjumpbits, $gobutton, $theme, $templates, $lang;

	$pid = intval($pid);

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
			if($forum['active'] != "no")
			{
				$jumpfcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
			}
		}
	}

	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}

	if(is_array($jumpfcache[$pid]))
	{
		foreach($jumpfcache[$pid] as $main)
		{
			foreach($main as $forum)
			{
				$perms = $permissioncache[$forum['fid']];

				if($forum['fid'] != "0" && ($perms['canview'] != "no" || $mybb->settings['hideprivateforums'] == "no") && $forum['linkto'] == '' && $forum['showinjump'] != "no")
				{
					$optionselected = "";

					if($selitem == $forum['fid'])
					{
						$optionselected = "selected=\"selected\"";
						$selecteddone = 1;
					}

					eval("\$forumjumpbits .= \"".$templates->get("forumjump_bit")."\";");

					if($forum_cache[$forum['fid']])
					{
						$newdepth = $depth."--";
						$forumjumpbits .= build_forum_jump($forum['fid'], $selitem, 0, $newdepth, $showextras);
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

			$jumpsel[$selitem] = "selected";
		}

		if($showextras == 0)
		{
			$template = "special";
		}
		else
		{
			$template = "advanced";
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
		$ch = rand(0, count($set)-1);
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
	global $db, $mybb, $theme, $templates, $lang;

	if($mybb->settings['bbcodeinserter'] != "off")
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
			"editor_size_xx_small",
			"editor_size_x_small",
			"editor_size_small",
			"editor_size_medium",
			"editor_size_x_large",
			"editor_size_xx_large",
			"editor_color_white",
			"editor_color_black",
			"editor_color_red",
			"editor_color_yellow",
			"editor_color_pink",
			"editor_color_green",
			"editor_color_orange",
			"editor_color_purple",
			"editor_color_blue",
			"editor_color_beige",
			"editor_color_brown",
			"editor_color_teal",
			"editor_color_navy",
			"editor_color_maroon",
			"editor_color_limegreen",
			"editor_font",
			"editor_size",
			"editor_color"
		);
		$editor_language = "var editor_language = {\n";

		foreach($editor_lang_strings as $key => $lang_string)
		{
			// Strip initial editor_ off language string if it exists - ensure case sensitivity does not matter.
			$js_lang_string = preg_replace("#^editor_#i", "", $lang_string);
			$string = str_replace("\"", "\\\"", $lang->$lang_string);
			$editor_language .= "\t{$js_lang_string}: \"{$string}\"";

			if($editor_lang_strings[$key+1])
			{
				$editor_language .= ",";
			}

			$editor_language .= "\n";
		}

		$editor_language .= "};";

		eval("\$codeinsert = \"".$templates->get("codebuttons")."\";");
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
	global $db, $smiliecache, $theme, $templates, $lang, $mybb, $smiliecount;

	if($mybb->settings['smilieinserter'] != "off" && $mybb->settings['smilieinsertercols'] && $mybb->settings['smilieinsertertot'])
	{
		if(!$smiliecount)
		{
			$query = $db->simple_select("smilies", "COUNT(*) as smilies");
			$smiliecount = $db->fetch_field($query, "smilies");
		}

		if(!$smiliecache)
		{
			$query = $db->simple_select("smilies", "*", "showclickable != 'no'", array('order_by' => 'disporder'));

			while($smilie = $db->fetch_array($query))
			{
				$smiliecache[$smilie['find']] = $smilie['image'];
			}
		}

		unset($smilie);

		if(is_array($smiliecache))
		{
			reset($smiliecache);

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
					$smilies .= "<td><img src=\"{$image}\" border=\"0\" class=\"smilie\" alt=\"{$find}\" /></td>\n";
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

	$time = time();

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
 * Fetch the IP address of the current user.
 *
 * @return string The IP address.
 */
function get_ip()
{
	if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
	{
		if(preg_match_all("#[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}#s", $_SERVER['HTTP_X_FORWARDED_FOR'], $addresses))
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

	if(!isset($ip))
	{
		if(isset($_SERVER['HTTP_CLIENT_IP']))
		{
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		else
		{
			$ip = $_SERVER['REMOTE_ADDR'];
		}
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

	if($size >= 1073741824)
	{
		$size = round(($size / 1073741824), 2)." ".$lang->size_gb;
	}
	elseif($size >= 1048576)
	{
		$size = round(($size / 1048576), 2)." ".$lang->size_mb;
	}
	elseif($size >= 1024)
	{
		$size = round(($size / 1024), 2)." ".$lang->size_kb;
	}
	elseif($size == 0)
	{
		$size = "0 ".$lang->size_bytes;
	}
	else
	{
		$size = $size." ".$lang->size_bytes;
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
	global $cache, $attachtypes;

	if(!$attachtypes)
	{
		$attachtypes = $cache->read("attachtypes");
	}

	$ext = my_strtolower($ext);

	if($attachtypes[$ext]['icon'])
	{
		return "<img src=\"".$attachtypes[$ext]['icon']."\" border=\"0\" alt=\".{$ext} File\" />";
	}
	else
	{
		return "<img src=\"images/attachtypes/unknown.gif\" border=\"0\" alt=\".{$ext} File\" />";
	}
}

/**
 * Get a list of the unviewable forums for the current user
 *
 * @return string Comma separated values list of the forum IDs which the user cannot view
 */
function get_unviewable_forums()
{
	global $db, $forum_cache, $permissioncache, $mybb, $unviewableforums, $unviewable, $templates, $forumpass;

	$pid = intval($pid);

	if(!$permissions)
	{
		$permissions = $mybb->usergroup;
	}

	if(!is_array($forum_cache))
	{
		cache_forums();
	}

	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}

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
			if($_COOKIE['forumpass'][$forum['fid']] != md5($mybb->user['uid'].$forum['password']))
			{
				$pwverified = 0;
			}
		}

		if($perms['canview'] == "no" || $pwverified == 0)
		{
			if($unviewableforums)
			{
				$unviewableforums .= ",";
			}

			$unviewableforums .= "'".$forum['fid']."'";
		}
	}

	return $unviewableforums;
}

/**
 * Fixes mktime() for dates earlier than 1970
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
	global $nav, $navbits, $templates, $theme, $lang;

	eval("\$navsep = \"".$templates->get("nav_sep")."\";");

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
 */
function build_forum_breadcrumb($fid)
{
	global $pforumcache, $db, $currentitem, $forum_cache, $navbits, $lang, $base_url, $archiveurl;

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
				if($pforumcache[$forumnav['pid']])
				{
					build_forum_breadcrumb($forumnav['pid'], $archive);
				}

				$navsize = count($navbits);
				$navbits[$navsize]['name'] = $forumnav['name'];

				if(IN_ARCHIVE == 1)
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

	// If the server OS is not Windows and not Apache or the PHP is running as a CGI or we have defined ARCHIVE_QUERY_STRINGS, use query strings
	if((preg_match("#win#i", PHP_OS) && is_numeric(stripos($_SERVER['SERVER_SOFTWARE'], "apache")) == false) || is_numeric(stripos(SAPI_NAME, "cgi")) !== false || defined("ARCHIVE_QUERY_STRINGS"))
	{
		$base_url = $mybb->settings['bburl']."/archive/index.php?";
	}
	else
	{
		$base_url = $mybb->settings['bburl']."/archive/index.php/";
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
			$url = $mybb->setings['bburl']."/archive/index.php";
	}

	return $url;
}

/**
 * Prints a debug information page
 */
function debug_page()
{
	global $db, $querytime, $debug, $templates, $templatelist, $mybb, $maintimer, $globaltime, $ptimer, $parsetime;

	$totaltime = $maintimer->totaltime;
	$phptime = $maintimer->format($maintimer->totaltime - $querytime);
	$querytime = $maintimer->format($querytime);

	$percentphp = number_format((($phptime/$maintimer->totaltime)*100), 2);
	$percentsql = number_format((($querytime/$maintimer->totaltime)*100), 2);

	$phpversion = phpversion();

	$serverload = get_server_load();

	if($mybb->settings['gzipoutput'] != "no")
	{
		$gzipen = "Enabled";
	}
	else
	{
		$gzipen = "Disabled";
	}

	echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
	echo "<html lang=\"en\">";
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
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">No. MySQL Queries:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$db->query_count</font></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">PHP Processing Time:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$phptime seconds ($percentphp%)</font></td>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">MySQL Processing Time:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$querytime seconds ($percentsql%)</font></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">Extensions Used:</font></b></td>\n";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">{$mybb->config['dbtype']}, xml</font></td>\n";
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

	if(function_exists("memory_get_usage"))
	{
		$memory_usage = memory_get_usage();
		$memory_limit = @ini_get("memory_limit");
		echo "<tr>\n";
		echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">Memory Usage:</font></b></td>\n";
		echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">".get_friendly_size($memory_usage)." ({$memory_usage} bytes)</font></td>\n";
		echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">Memory Limit:</font></b></td>\n";
		echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">{$memory_limit}</font></td>\n";
		echo "</tr>\n";
	}

	echo "</table>\n";
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

	if($mybb->settings['nocacheheaders'] == "yes" && $mybb->settings['standardheaders'] != "yes")
	{
		header("Expires: Sat, 1 Jan 2000 01:00:00 GMT");
		header("Last-Modified: ".gmdate("D, d M Y H:i:s")."GMT");
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

	$plugins->run_hooks("mark_reports");
	$cache->update_reportedposts();
}

/**
 * Fetch a friendly x days, y months etc date stamp from a timestamp
 *
 * @param int The timestamp
 * @return string The friendly formatted timestamp
 */
function nice_time($stamp)
{
	global $lang;

	$ysecs = 365*24*60*60;
	$mosecs = 31*24*60*60;
	$wsecs = 7*24*60*60;
	$dsecs = 24*60*60;
	$hsecs = 60*60;
	$msecs = 60;

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
		$nicetime['years'] = "1 ".$lang->year;
	}
	else if($years > 1)
	{
		$nicetime['years'] = $years." ".$lang->years;
	}

	if($months == 1)
	{
		$nicetime['months'] = "1 ".$lang->month;
	}
	else if($months > 1)
	{
		$nicetime['months'] = $months." ".$lang->months;
	}

	if($weeks == 1)
	{
		$nicetime['weeks'] = "1 ".$lang->week;
	}
	else if($weeks > 1)
	{
		$nicetime['weeks'] = $weeks." ".$lang->weeks;
	}

	if($days == 1)
	{
		$nicetime['days'] = "1 ".$lang->day;
	}
	else if($days > 1)
	{
		$nicetime['days'] = $days." ".$lang->days;
	}

	if($hours == 1)
	{
		$nicetime['hours'] = "1 ".$lang->hour;
	}
	else if($hours > 1)
	{
		$nicetime['hours'] = $hours." ".$lang->hours;
	}

	if($minutes == 1)
	{
		$nicetime['minutes'] = "1 ".$lang->minute;
	}
	else if($minutes > 1)
	{
		$nicetime['minutes'] = $minutes." ".$lang->minutes;
	}

	if($seconds == 1)
	{
		$nicetime['seconds'] = "1 ".$lang->seconds;
	}
	else if($seconds > 1)
	{
		$nicetime['seconds'] = $seconds." ".$lang->seconds;
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
	global $db;

	if($uid == $mybb->user['uid'])
	{
		$user = $mybb->user;
	}
	else
	{
		$query = $db->simple_select("users", "additionalgroups, usergroup", "uid='{$uid}'");
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

	$db->query("
		UPDATE ".TABLE_PREFIX."users
		SET additionalgroups='$groupslist'
		WHERE uid='$uid'
	");
}

/**
 * Remove a user from a specific additional user group
 *
 * @param int The user ID
 * @param int The user group ID
 */
function leave_usergroup($uid, $leavegroup)
{
	global $db, $mybb;

	if($uid == $mybb->user['uid'])
	{
		$user = $mybb->user;
	}
	else
	{
		$query = $db->simple_select("users", "*", "uid='{$uid}'");
		$user = $db->fetch_array($query);
	}

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

	if($leavegroup == $user['displaygroup'])
	{
		$dispupdate = ", displaygroup=usergroup";
	}

	$db->query("
		UPDATE ".TABLE_PREFIX."users
		SET additionalgroups='$groupslist' $dispupdate
		WHERE uid='$uid'
	");
}

/**
 * Get the current location taking in to account different web serves and systems
 *
 * @return string The current URL being accessed
 */
function get_current_location()
{
	if(defined("MYBB_LOCATION"))
	{
		return MYBB_LOCATION;
	}

	if(isset($_SERVER['PATH_INFO']))
	{
		$location = $_SERVER['PATH_INFO'];
	}
	elseif(isset($_ENV['PATH_INFO']))
	{
		$location = $_SERVER['PATH_INFO'];
	}
	elseif(isset($_ENV['PHP_SELF']))
	{
		$location = $_ENV['PHP_SELF'];
	}
	else
	{
		$location = $_SERVER['PHP_SELF'];
	}

	if(isset($_SERVER['QUERY_STRING']))
	{
		$location .= "?".$_SERVER['QUERY_STRING'];
	}
	else if(isset($_ENV['QUERY_STRING']))
	{
		$location = "?".$_ENV['QUERY_STRING'];
	}

	if((isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == "POST") || (isset($_ENV['REQUEST_METHOD']) && $_ENV['REQUEST_METHOD'] == "POST"))
	{
		$post_array = array('action', 'fid', 'pid', 'tid', 'uid', 'eid');

		foreach($post_array as $var)
		{
			if(isset($_POST[$var]))
			{
				$addloc[] = $var.'='.$_POST[$var];
			}
		}

		if(isset($addlock) && is_array($addloc))
		{
			$location .= "?".implode("&", $addloc);
		}
	}

	return $location;
}

/**
 * Build a theme selection menu
 *
 * @param string The name of the menu
 * @param int The ID of the selected theme
 * @param int The ID of the parent theme to select from
 * @param int The current selection depth
 * @param int Whether or not to override usergroup permissions (1 to override)
 * @return string The theme selection list
 */
function build_theme_select($name, $selected="", $tid=0, $depth="", $usergroup_override=0)
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
			if($theme['allowedgroups'] != "all" && $theme['allowedgroups'] != "")
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
			if($is_allowed || $theme['allowedgroups'] == "all" || $theme['allowedgroups'] == "" || $usergroup_override == 1)
			{
				if($theme['tid'] == $selected)
				{
					$sel = " selected=\"selected\"";
				}

				if($theme['pid'] != 0)
				{
					$themeselect .= "<option value=\"".$theme['tid']."\"$sel>".$depth.$theme['name']."</option>";
					$depthit = $depth."--";
				}

				if(array_key_exists($theme['tid'], $tcache))
				{
					build_theme_select($name, $selected, $theme['tid'], $depthit, $usergroup_override);
				}
			}
		}
	}

	if(!$tid)
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
	$message = str_replace("  ", "&nbsp;&nbsp;", $message);

	return $message;
}

/**
 * Custom function for reverting back htmlspecialchars.
 *
 * @param string The string to format
 * @return string The string with htmlspecialchars reverted
 */
function unhtmlspecialchars($message)
{
	$message = str_replace('&amp;',			'&',	$message);
	$message = str_replace('&#039;',		'\'',	$message);
	$message = str_replace('&quot;',		'"',	$message);
	$message = str_replace('&lt;',			'<',	$message);
	$message = str_replace('&gt;',			'>',	$message);
	$message = str_replace('&nbsp;&nbsp;',	'  ',	$message);

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

		return number_format($number, $decimals, $mybb->settings['decpoint'], $mybb->settings['thousandssep']);
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
		$message = preg_replace("#(?>[^\s&/<>\"\\-\.\[\]]{{$mybb->settings['wordwrap']}})#", "$0 ", $message);
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

	$find = array(
		'm',
		'd',
		'y',
		'Y',
		'j',
		'S',
		'F',
		'l'
	);

	$replace = array(
		sprintf('%02s', $bm),
		sprintf('%02s', $bd),
		my_substr($by, 2),
		$by,
		($bd[0] == 0 ? my_substr($bd, 1) : $bd),
		($db == 1 || $db == 21 || $db == 31 ? 'st' : ($db == 2 || $db == 22 ? 'nd' : ($db == 3 || $db == 23 ? 'rd' : 'th'))),
		$bmonth[$bm-1],
		$bdays[$wd]
	);

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

        if($bday[2] < 1970)
        {
                $years = 1970 - $bday[2];
                $year = $bday[2] + ($years * 2);
                $stamp = mktime(0, 0, 0, $bday[1], $bday[0], $year) - ($years * 31556926 * 2);
        }
        else
        {
                $stamp = mktime(0, 0, 0, $bday[1], $bday[0], $bday[2]);
        }

        $age = floor((time() - $stamp) / 31556926);

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

	$query = $db->simple_select("posts", "pid", "tid='{$tid}'", array('order_by' => 'dateline', 'limit' => 1));
	$post = $db->fetch_array($query);

	if($post['replyto'] != 0)
	{
		$replyto_update = array(
			"replyto" => 0
		);
		$db->update_query("threads", $replyto_update, "pid='{$post['pid']}'");
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

    $string = preg_replace("#&\#(0-9]+);#", "-", $string);

    if(strtolower($lang->settings['charset']) == "utf-8")
    {
        // Get rid of any excess RTL and LTR override for they are the workings of the devil
        $string = str_replace(dec_to_utf8(8238), "", $string);
        $string = str_replace(dec_to_utf8(8237), "", $string);

        // Remove dodgy whitspaces
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
 * @return int The cut part of the string.
 */
function my_substr($string, $start, $length="")
{
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
   $string = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $string);
   $string = preg_replace('~&#([0-9]+);~e', 'chr(\\1)', $string);

   // Replace literal entities
   $trans_tbl = get_html_translation_table(HTML_ENTITIES);
   $trans_tbl = array_flip($trans_tbl);

   return strtr($string, $trans_tbl);
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
 * Build the profile link.
 *
 * @param string The Username of the profile.
 * @param int The user id of the profile.
 * @param string The target frame
 */
function build_profile_link($username="", $uid=0, $target="")
{
	global $lang;

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

		// If we're in the archive, link back a directory
		if(IN_ARCHIVE == 1)
		{
			return "<a href=\"../".get_profile_link($uid)."\"{$target}>{$username}</a>";
		}
		else
		{
			return "<a href=\"".get_profile_link($uid)."\"{$target}>{$username}</a>";
		}
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
			$link = THREAD_URL_PAGED_ACTION;
		}
		else
		{
			$link = THREAD_URL_PAGED;
		}
		$link = str_replace("{tid}", $tid, $link);
		$link = str_replace("{action}", $action, $link);
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
 * @param int The year
 * @param int The month
 * @param int The day (optional)
 * @return string The URL of the calendar
 */
function get_calendar_link($year, $month, $day=0)
{
	if($day > 0)
	{
		$link = str_replace("{month}", $month, CALENDAR_URL_DAY);
		$link = str_replace("{year}", $year, $link);
		$link = str_replace("{day}", $day, $link);
		return htmlspecialchars_uni($link);
	}
	else
	{
		$link = str_replace("{month}", $month, CALENDAR_URL);
		$link = str_replace("{year}", $year, $link);
		return htmlspecialchars_uni($link);
	}
}

/**
 * Get the username of a user id.
 *
 * @param int The user id of the user.
 * @return string The username of the user.
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
				if($forum_cache[$parent]['active'] == "no")
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
 * @return string The database row of the thread.
 */
function get_thread($tid)
{
	global $db;
	static $thread_cache;

	if(isset($thread_cache[$tid]))
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
 * @return string The comma seperated values of the inactivate forum.
 */
function get_inactive_forums()
{
	global $forum_cache, $db, $cache, $inactiveforums;

	if(!$forum_cache)
	{
		cache_forums();
	}

	$inactive = array();

	foreach($forum_cache as $fid => $forum)
	{
		if($forum['active'] == "no")
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
	// Session stops user clearing cookies to bypass the login
	// Also use the greater of the two numbers present, stops people using scripts with altered cookie data to stay the same
	$cookielogins = intval($_COOKIE['loginattempts']);
	$cookietime = $_COOKIE['failedlogin'];

	if(empty($cookielogins) || $cookielogins < $session->logins)
	{
		$loginattempts = $session->logins;
	}
	else
	{
		$loginattempts = $cookielogins;
	}

	if(empty($cookietime) || $cookietime < $session->failedlogin)
	{
		$failedlogin = $session->failedlogin;
	}
	else
	{
		$failedlogin = $cookietime;
	}

	// Work out if the user has had more than the allowed number of login attempts
	if($loginattempts > $mybb->settings['failedlogincount'])
	{
		// If so, then we need to work out if they can try to login again
		// Some maths to work out how long they have left and display it to them
		$now = time();

		if(empty($_COOKIE['failedlogin']))
		{
			$failedtime = $now;
		}
		else
		{
			$failedtime = $_COOKIE['failedlogin'];
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
				error(sprintf($lang->failed_login_wait, $hoursleft, $minsleft, $secsleft));
			}

			return false;
		}

		// Work out if the user has waited long enough before letting them login again
		if($_COOKIE['failedlogin'] < $now - $mybb->settings['failedlogintime'] * 60)
		{
			my_setcookie('loginattempts', 1);
			my_unsetcookie('failedlogin');
			$update_array = array(
				'loginattempts' => 1
			);
			$db->update_query("sessions", $update_array, "sid = '{$session->sid}'");
			return 1;
		}
		// Not waited long enough
		else
		{
			if($fatal)
			{
				error(sprintf($lang->failed_login_wait, $hoursleft, $minsleft, $secsleft));
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
	return preg_match("/^(.+)@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/si", $email);
}

/**
* Rebuilds settings.php
*
*/
function rebuildsettings()
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
		$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
		$settings .= "\$settings['".$setting['name']."'] = \"".$setting['value']."\";\n";
		$mybb->settings[$setting['name']] = $setting['value'];
	}

	$settings = "<"."?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n$settings\n?".">";
	$file = @fopen(MYBB_ROOT."inc/settings.php", $mode);
	@fwrite($file, $settings);
	@fclose($file);

	$GLOBALS['settings'] = &$mybb->settings;
}

function apply_highlight(&$item, $key)
{
	global $highlight_count;

	$item = htmlspecialchars_uni($item);

	if(my_strlen($item) > 1)
	{
		if(stristr("<span class=\"highlight\"></span>", $item))
		{
			unset($item);
		}

		$item = "<span class=\"highlight\">{$item}</span>";
	}
	else
	{
		unset($item);
	}


}

/**
 * Converts a decimal reference of a character to its UTF-8 equivilant
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
 * Return a list of banned usernames.
 *
 * @return array The array of banned usernames.
 */
function get_banned_usernames()
{
	global $mybb;
	$banned_usernames = explode(",", $mybb->settings['bannedusernames']);
	$banned_usernames = array_map("trim", $banned_usernames);
	$banned_usernames = array_map("my_strtolower", $banned_usernames);
	return $banned_usernames;
}

/**
 * Checks if a username has been disallowed for registration/use.
 *
 * @param string The username
 * @return boolean True if banned, false if not banned
 */
function is_banned_username($username)
{
	$banned_usernames = get_banned_usernames();
	if(in_array(my_strtolower($username), $banned_usernames))
	{
		return true;
	}
	else
	{
		return false;
	}
}

/**
 * Return a list of banned email addresses.
 *
 * @return array The array of banned email addresses.
 */
function get_banned_emails()
{
	global $mybb;
	$banned_emails = explode(",", $mybb->settings['bannedemails']);
	$banned_emails = array_map("trim", $banned_emails);
	$banned_emails = array_map("my_strtolower", $banned_emails);
	return $banned_emails;
}

/**
 * Check if a specific email address has been banned.
 *
 * @param string The email address.
 * @return boolean True if banned, false if not banned
 */
function is_banned_email($email)
{
	$banned_emails = get_banned_emails();
	$email = my_strtolower($email);
	foreach($banned_emails as $banned_email)
	{
		if($banned_email != "" && my_strpos($email, $banned_email) !== false)
		{
			return true;
		}
	}
	return false;
}

/**
 * Return a list of banned IP addresses.
 *
 * @return array The array of banned IP addresses.
 */
function get_banned_ips()
{
	global $mybb;
	$banned_ips = explode(",", $mybb->settings['bannedips']);
	$banned_ips = array_map("trim", $banned_ips);
	return $banned_ips;
}

/**
 * Checks if a specific IP address has been banned.
 *
 * @param string The IP address.
 * @return boolean True if banned, false if not banned.
 */
function is_banned_ip($ip_address)
{
	$banned_ips = get_banned_ips();
	foreach($banned_ips as $banned_ip)
	{
		if($banned_ip != "" && my_strpos($ip_address, $banned_ip) !== false)
		{
			return true;
		}
	}
	return false;
}

/**
 * Below are compatibility functions which replicate functions in newer versions of PHP.
 *
 * This allows MyBB to continue working on older installations of PHP without these functions.
 */

if(!function_exists("stripos"))
{
	function stripos($haystack, $needle, $offset=0)
	{
		return my_strpos(my_strtoupper($haystack), my_strtoupper($needle), $offset);
	}
}


if(!function_exists("file_get_contents"))
{
	function file_get_contents($file)
	{
		$handle = @fopen($file, "rb");

		if($handle)
		{
			while(!@feof($handle))
			{
				$contents .= @fread($handle, 8192);
			}
			return $contents;
		}

		return false;
	}
}

if(!function_exists('html_entity_decode'))
{
	function html_entity_decode($string)
	{
	   // replace numeric entities
	   $string = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $string);
	   $string = preg_replace('~&#([0-9]+);~e', 'chr(\\1)', $string);

	   // replace literal entities
	   $trans_tbl = get_html_translation_table(HTML_ENTITIES);
	   $trans_tbl = array_flip($trans_tbl);

	   return strtr($string, $trans_tbl);
	}
}

if(!function_exists('htmlspecialchars_decode'))
{
   function htmlspecialchars_decode($text)
   {
       return strtr($text, array_flip(get_html_translation_table(HTML_SPECIALCHARS)));
   }
}

?>