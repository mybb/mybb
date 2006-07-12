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

/* Do not change the following line if you wish to receive
   technical support
*/

$mybboard['internalver'] = "1.2.0";
$mybboard['vercode'] = "120";

/**
 * Outputs a page directly to the browser, parsing anything which needs to be parsed.
 *
 * @param string The contents of the page.
 */
function output_page($contents)
{
	global $db, $lang, $settings, $theme, $plugins, $mybb, $mybbuser, $mybbgroup;
	global $querytime, $debug, $templatecache, $templatelist, $maintimer, $globaltime, $parsetime;

	$ptimer = new timer();
	$contents = parse_page($contents);
	$parsetime = $ptimer->stop();
	$totaltime = $maintimer->stop();
	if($mybbgroup['cancp'] == "yes")
	{
		$phptime = $maintimer->format($maintimer->totaltime - $querytime);
		$querytime = $maintimer->format($querytime);
		$percentphp = number_format((($phptime/$maintimer->totaltime)*100), 2);
		$percentsql = number_format((($querytime/$maintimer->totaltime)*100), 2);
		$phpversion = phpversion();
		$serverload = get_server_load();
		if(strstr(getenv("REQUEST_URI"), "?"))
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
			$other = "PHP version: $phpversion / Server Load: $serverload / GZip Compression: $gzipen";
			$debugstuff = "Generated in $totaltime seconds ($percentphp% PHP / $percentsql% MySQL)<br />MySQL Queries: $db->query_count / Parsing $parsetime / Global Parsing Time: $globaltime<br />$other<br />[<a href=\"$debuglink\" target=\"_blank\">advanced details</a>]<br />";
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
	if($mybb->settings['useshutdownfunc'] == "no" && $mybb->use_shutdown == true)
	{
		run_shutdown();
	}
}

/**
 * Adds a function to the list of functions to run on shutdown.
 *
 * @param string The name of the function.
 */
function add_shutdown($name)
{
	global $shutdown_functions;

	if(function_exists($name))
	{
		$shutdown_functions[$name] = $name;
	}
}

/**
 * Runs the shutdown items after the page has been sent to the browser.
 *
 */
function run_shutdown()
{
	global $db, $cache, $shutdown_functions;

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
}

/**
 * Sends a specified amount of messages from the mail queue
 *
 * @param int The number of messages to send (Defaults to 20)
 */
function send_mail_queue($count=10)
{
	global $db, $cache;

	// Check to see if the mail queue has messages needing to be sent
	$mailcache = $cache->read("mailqueue");
	if($mailcache['queue_size'] > 0 && ($mailcache['locked'] == 0 || $mailcache['locked'] < time()-300))
	{
		// Lock the queue so no other messages can be sent whilst these are (for popular boards)
		$cache->updatemailqueue(0, time());
		
		// Fetch emails for this page view - and send them
		$query = $db->simple_select(TABLE_PREFIX."mailqueue", "*", "", array("order_by" => "mid", "order_dir" => "asc", "limit_start" => 0, "limit" => $count));
		while($email = $db->fetch_array($query))
		{
			// Delete the message from the queue
			$db->delete_query(TABLE_PREFIX."mailqueue", "mid='{$email['mid']}'");

			mymail($email['mailto'], $email['subject'], $email['message'], $email['mailfrom']);
		}
		// Update the mailqueue cache and remove the lock
		$cache->updatemailqueue(time(), 0);
	}
}

/**
 * Parses the contents of a page before outputting it.
 *
 * @param string The contents of the page.
 * @return string The parsed page.
 */
function parse_page($contents)
{
	global $db, $lang, $settings, $theme, $mybb, $mybbuser, $mybbgroup, $htmldoctype, $loadpmpopup, $archive_url;

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

	if($loadpmpopup)
	{
		if(my_substr($_SERVER['PHP_SELF'], -strlen("private.php")) != "private.php")
		{
			$contents = str_replace("<body", "<body onload=\"Javascript:MyBB.newPM()\"", $contents);
		}
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
 * @return string The formatted timestamp.
 */
function mydate($format, $stamp, $offset="", $ty=1)
{
	global $mybb, $lang, $mybbadmin;

	if(!$offset)
	{
		if($mybb->user['timezone'])
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
			$offset++;
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
	$date = gmdate($format, $stamp + ($offset * 3600));
	if($mybb->settings['dateformat'] == $format && $ty)
	{
		$stamp = time();
		$todaysdate = gmdate($format, $stamp + ($offset * 3600));
		$yesterdaysdate = gmdate($format, ($stamp - 86400) + ($offset * 3600));
		if($todaysdate == $date)
		{
			$date = $lang->today;
		}
		elseif($yesterdaysdate == $date)
		{
			$date = $lang->yesterday;
		}
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
 */
function mymail($to, $subject, $message, $from="", $charset="")
{
	global $db, $mybb, $lang;

	if($charset == "")
	{
		//$charset = "ISO-8859-1";
		$charset = $lang->settings['charset'];
	}

	if(function_exists('mb_language') && function_exists('mb_encode_mimeheader'))
	{
		mb_language($lang->settings['htmllang']);
  		$subject = str_replace('ISO-8859-1', $charset, mb_encode_mimeheader($subject));
 		$from = str_replace('ISO-8859-1', $charset, mb_encode_mimeheader($from));
	}

	// Build mail headers
	if(my_strlen(trim($from)) == 0)
	{
		$from = "\"".$mybb->settings['bbname']." Mailer\" <".$mybb->settings['adminemail'].">";
	}
	$headers = "From: {$from}\n";
	$headers .= "Return-Path: {$mybb->settings['adminemail']}\n";
	if($_SERVER['SERVER_NAME'])
	{
		$http_host = $_SERVER['SERVER_NAME'];
	}
	else if($_SERVER['HTTP_HOST'])
	{
		$http_host = $_SERVER['HTTP_HOST'];
	}
	else
	{
		$http_host = "unknown.local";
	}
	$headers .= "Message-ID: <". md5(uniqid(time()))."@{$http_host}>\n";
	$headers .= "MIME-Version: 1.0\n";
	$headers .= "Content-Type: text/plain; charset=\"{$charset}\"\n";
	$headers .= "Content-Transfer-Encoding: 8bit\n";
	$headers .= "X-Priority: 3\n";
	$headers .= "X-MSMail-Priority: Normal\n";
	$headers .= "X-Mailer: MyBB\n";

	// For some reason sendmail/qmail doesn't like \r\n
	$sendmail = @ini_get('sendmail_path');
	if($sendmail)
	{
		$headers = preg_replace("#(\r\n|\r|\n)#s", "\n", $headers);
		$message = preg_replace("#(\r\n|\r|\n)#s", "\n", $message);
	}
	else
	{
		$headers = preg_replace("#(\r\n|\r|\n)#s", "\r\n", $headers);
		$message = preg_replace("#(\r\n|\r|\n)#s", "\r\n", $message);
	}

	mail($to, $subject, $message, $headers);
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
 */
function cache_forums()
{
	global $forum_cache, $db, $cache;

	if(!$forum_cache)
	{
		$forum_cache = $cache->read("forums");
		if(!$forum_cache)
		{
			$cache->updateforums();
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
	global $header, $footer, $css, $toplinks, $settings, $theme, $headerinclude, $db, $templates, $lang, $mybb;

	if(!$error)
	{
		$error = $lang->unknown_error;
	}
	if(!$title)
	{
		$title = $mybb->settings['bbname'];
	}
	$timenow = mydate($mybb->settings['dateformat'], time()) . " " . mydate($mybb->settings['timeformat'], time());
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
	global $theme, $mybb, $db, $lang, $templates, $settings;
	if(!$title)
	{
		$title = $lang->please_correct_errors;
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
	global $mybb, $mybbuser, $theme, $templates, $ipaddress, $db, $lang, $plugins, $session;

	$time = time();
	$plugins->run_hooks("no_permission");
	$noperm_array = array (
		"nopermission" => '1',
		"location1" => 0,
		"location2" => 0
	);
	$db->update_query(TABLE_PREFIX."sessions", $noperm_array, "sid='".$session->sid."'");
	$url = $_SERVER['REQUEST_URI'];
	if($mybb->user['uid'])
	{
		$lang->error_nopermission_user_5 = sprintf($lang->error_nopermission_user_5, $mybb->user['username']);
		eval("\$errorpage = \"error_nopermission_loggedin\";");		
	}
	else
	{
		eval("\$errorpage = \"error_nopermission\";");				
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
	global $header, $footer, $css, $toplinks, $settings, $mybb, $theme, $headerinclude, $templates, $lang, $plugins;

	if(!$message)
	{
		$message = $lang->redirect;
	}
	$timenow = mydate($mybb->settings['dateformat'], time()) . " " . mydate($mybb->settings['timeformat'], time());
	$plugins->run_hooks("redirect");
	if(!$title)
	{
		$title = $mybb->settings['bbname'];
	}
	if($mybb->settings['redirects'] == "on" && $mybb->user['showredirect'] != "no")
	{
		eval("\$redirectpage = \"".$templates->get("redirect")."\";");
		output_page($redirectpage);
	}
	else
	{
		$url = str_replace("#", "&#", $url);
		$url = str_replace("&amp;", "&", $url);
		$url = str_replace(array("\n","\r",";"), "", $url);
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
 * @param string The URL to have page numbers tacked on to
 * @return string The generated pagination
 */
function multipage($count, $perpage, $page, $url)
{
	global $settings, $theme, $templates, $lang, $mybb;

	if($count > $perpage)
	{
		$pages = $count / $perpage;
		$pages = ceil($pages);

		if($page > 1)
		{
			$prev = $page - 1;
			eval("\$prevpage = \"".$templates->get("multipage_prevpage")."\";");
		}
		if($page < $pages)
		{
			$next = $page + 1;
			eval("\$nextpage = \"".$templates->get("multipage_nextpage")."\";");
		}
		$from = ($page>4) ? ($page-4):1;
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
		for($i = $from; $i <= $to; $i++)
		{
			$plate = "multipage_page".(($i==$page) ? "_current":"");
			eval("\$mppage .= \"".$templates->get($plate)."\";");
		}
		$lang->multipage_pages = sprintf($lang->multipage_pages, $pages);
		eval("\$start = \"".$templates->get("multipage_start")."\";");
		eval("\$end = \"".$templates->get("multipage_end")."\";");
		eval("\$multipage = \"".$templates->get("multipage")."\";");
		return $multipage;
	}
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
	global $db, $cache, $groupscache, $forum_cache, $fpermcache, $mybbgroup, $mybbuser, $mybb, $usercache, $fpermissionscache;

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
				$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='$uid'");
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
			$groupperms = $mybbgroup;
		}
	}
	if(!is_array($forum_cache))
	{
		cache_forums();
	}
	if(!is_array($forum_cache))
	{
		return false;
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
	// The fix here for better working inheritance was provided by tinywizard - http://windizupdate.com/
	// Many thanks.
	foreach($fpermfields as $perm)
	{
		$forumpermissions[$perm] = "no";
	}

	foreach($groups as $gid)
	{
		if($gid && $groupscache[$gid])
		{
			$p = is_array($fpermcache[$fid][$gid]) ? $fpermcache[$fid][$gid] : $groupperms;
			if($p == NULL)
			{
				foreach($forumpermissions as $k => $v)
				{
					$forumpermissions[$k] = 'yes';        // no inherited group, assume one has access
				}
			}
			else
			{
				foreach($p as $perm => $access)
				{
					if(isset($forumpermissions[$perm]) && $access == 'yes')
					{
						$forumpermissions[$perm] = $access;
					}
				}
			}
		}
	}
	return $forumpermissions;
}

/**
 * Check the password given on a certain forum for validity
 *
 * @param int The forum ID
 * @param string The plain text password for the forum
 */
function check_forum_password($fid, $password="")
{
	global $mybb, $mybbuser, $toplinks, $header, $settings, $footer, $css, $headerinclude, $theme, $breadcrumb, $templates, $lang;
	$showform = 1;

	if($password)
	{
		if($mybb->input['pwverify'])
		{
			if($password == $mybb->input['pwverify'])
			{
				mysetcookie("forumpass[$fid]", md5($mybb->user['uid'].$mybb->input['pwverify']));
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
	global $mybb, $mybbuser, $db;
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
		$query = $db->query("
			SELECT *
			FROM ".TABLE_PREFIX."moderators
			WHERE uid='$uid'
			AND $sql
		");
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
	global $mybb, $mybbuser, $db, $mybbgroup;

	if($uid == 0)
	{
		$uid = $mybb->user['uid'];
	}

	if($mybbgroup['issupermod'] == "yes")
	{
		return "yes";
	}
	else
	{
		if(!$fid)
		{
			$query = $db->query("
				SELECT mid
				FROM ".TABLE_PREFIX."moderators
				WHERE uid='$uid'
			");
			$modcheck = $db->fetch_array($query);
			if($modcheck['mid'])
			{
				return "yes";
			}
			else
			{
				return "no";
			}
		}
		else
		{
			$modperms = get_moderator_permissions($fid, $uid);
			if(!$action && $modperms)
			{
				return "yes";
			}
			else
			{
				if($modperms[$action] == "yes")
				{
					return "yes";
				}
				else
				{
					return "no";
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
	global $mybb, $db, $icon, $settings, $theme, $templates, $lang;

	$listed = 0;
	if($mybb->input['icon'])
	{
		$icon = $mybb->input['icon'];
	}
	$no_icons_checked = " checked=\"checked\"";
	$query = $db->query("
		SELECT *
		FROM ".TABLE_PREFIX."icons
		ORDER BY name DESC
	");
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
		$listed++;
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
 */
function mysetcookie($name, $value="", $expires="")
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
	else
	{
		if(isset($mybb->user['remember']) == "no")
		{
			$expires = 0;
		}
		else
		{
			$expires = time() + (60*60*24*365); // Make the cookie expire in a years time
		}
	}
	if($mybb->settings['cookiedomain'])
	{
		setcookie($name, $value, $expires, $mybb->settings['cookiepath'], $mybb->settings['cookiedomain']);
	}
	else
	{
		setcookie($name, $value, $expires, $mybb->settings['cookiepath']);
	}
}

/**
 * Unset a cookie set by MyBB.
 *
 * @param string The cookie identifier.
 */
function myunsetcookie($name)
{
	global $mybb;

	$expires = time()-3600;
	if(!$mybb->settings['cookiepath'])
	{
		$mybb->settings['cookiepath'] = "/";
	}

	if($mybb->settings['cookiedomain'])
	{
		@setcookie($name, "", $expires, $mybb->settings['cookiepath'], $mybb->settings['cookiedomain']);
	}
	else
	{
		@setcookie($name, "", $expires, $mybb->settings['cookiepath']);
	}
}

/**
 * Get the contents from a serialised cookie array.
 *
 * @param string The cookie identifier.
 * @param int The cookie content id.
 * @return array|boolean The cookie id's content array or false when non-existent.
 */
function mygetarraycookie($name, $id)
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
function mysetarraycookie($name, $id, $value)
{
	$cookie = $_COOKIE['mybb'];
	$newcookie = unserialize($cookie[$name]);
	$newcookie[$id] = $value;
	$newcookie = addslashes(serialize($newcookie));
	mysetcookie("mybb[$name]", $newcookie);
}

/**
 * Returns the serverload of the system.
 *
 * @return int The serverload of the system.
 */
function get_server_load()
{
	global $lang;
	if(strtolower(substr(PHP_OS, 0, 3)) === 'win')
	{
		return $lang->unknown;
	}
	elseif(@file_exists("/proc/loadavg"))
	{
		$load = @file_get_contents("/proc/loadavg");
		$serverload = explode(" ", $load);
		$serverload[0] = round($serverload[0], 4);
		if(!$serverload)
		{
			$load = @exec("uptime");
			$load = split("load averages?: ", $load);
			$serverload = explode(",", $load[1]);
		}
	}
	else
	{
		$load = @exec("uptime");
		$load = split("load averages?: ", $load);
		$serverload = explode(",", $load[1]);
	}
	$returnload = trim($serverload[0]);
	if(!$returnload)
	{
		$returnload = $lang->unknown;
	}
	return $returnload;
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

	$db->update_query(TABLE_PREFIX."forums", $update_count, "fid='{$fid}'");
}

/**
 * Update the thread counters for a specific thread
 *
 * @param int The thread ID
 */
function update_thread_count($tid)
{
	global $db, $cache;
	$query = $db->query("
		SELECT COUNT(*) AS replies
		FROM ".TABLE_PREFIX."posts
		WHERE tid='$tid'
		AND visible='1'
	");
	$replies = $db->fetch_array($query);
	$treplies = $replies['replies'] - 1;
	if($treplies < 0)
	{
		$treplies = 0;
	}
	$query = $db->query("
		SELECT u.uid, u.username, p.username AS postusername, p.dateline
		FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE p.tid='$tid' AND p.visible='1'
		ORDER BY p.dateline DESC
		LIMIT 1"
	);
	$lastpost = $db->fetch_array($query);

	$query = $db->query("
		SELECT u.uid, u.username, p.username AS postusername, p.dateline
		FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE p.tid='$tid'
		ORDER BY p.dateline ASC
		LIMIT 0,1
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

	if(!$lastpost['postusername'] || !$lastpost['dateline'])
	{
		$lastpost['username'] = $firstpost['username'];
		$lastpost['uid'] = $firstpost['uid'];
		$lastpost['dateline'] = $firstpost['dateline'];
	}
	$lastpost['username'] = $db->escape_string($lastpost['username']);
	$firstpost['username'] = $db->escape_string($firstpost['username']);
	// Unapproved posts
	$query = $db->query("
		SELECT COUNT(*) AS totunposts
		FROM ".TABLE_PREFIX."posts
		WHERE tid='$tid' AND visible='0'
	");
	$nounposts = $db->fetch_field($query, "totunposts");

	// Update the attachment count for this thread
	update_thread_attachment_count($tid);
	$db->query("
		UPDATE ".TABLE_PREFIX."threads
		SET username='".$firstpost['username']."', uid='".intval($firstpost['uid'])."', lastpost='".$lastpost['dateline']."', lastposter='".$lastpost['username']."', lastposteruid='".intval($lastpost['uid'])."', replies='$treplies', unapprovedposts='$nounposts'
		WHERE tid='$tid'
	");
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
		FROM ".TABLE_PREFIX."attachments a LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
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
	global $db, $forum_cache, $fjumpcache, $permissioncache, $settings, $mybb, $mybbuser, $selecteddone, $forumjump, $forumjumpbits, $gobutton, $theme, $templates, $lang, $mybbgroup;

	$pid = intval($pid);
	if($permissions)
	{
		$permissions = $mybbgroup;
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
				if($forum['fid'] != "0" && ($perms['canview'] != "no" || $mybb->settings['hideprivateforums'] == "no") && $forum['showinjump'] != "no")
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
	return strtolower(my_substr(strrchr($file, "."), 1));
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
	$str;
	for($i = 1; $i <= $length; $i++)
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
function build_mycode_inserter()
{
	global $db, $mybb, $settings, $theme, $templates, $lang;

	if($mybb->settings['bbcodeinserter'] != "off")
	{
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
	global $db, $smiliecache, $settings, $theme, $templates, $lang, $mybb;

	if($mybb->settings['smilieinserter'] != "off" && $mybb->settings['smilieinsertercols'] && $mybb->settings['smilieinsertertot'])
	{
		$smiliecount = 0;
		if(!$smiliecache)
		{
			$query = $db->query("
				SELECT *
				FROM ".TABLE_PREFIX."smilies
				WHERE showclickable != 'no'
				ORDER BY disporder
			");

			while($smilie = $db->fetch_array($query))
			{
				$smiliecache[$smilie['find']] = $smilie['image'];
				$smiliecount++;
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
			elseif ($mybb->settings['smilieinsertertot'] < $smiliecount)
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
					$find = $db->escape_string(htmlspecialchars($find));
					$smilies .= "<td><img src=\"{$image}\" border=\"0\" class=\"smilie\" alt=\"{$find}\" /></td>\n";
					$i++;
					$counter++;
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
	if(function_exists("gzcompress") && function_exists("crc32") && !headers_sent() && !(ini_get('output_buffering') && strpos(' '.ini_get('output_handler'), 'ob_gzhandler')))
	{
		$httpaccept_encoding = '';
		if(isset($_SERVER['HTTP_ACCEPT_ENCODING']))
		{
			$httpaccept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'];
		}
		if(strpos(" ".$httpaccept_encoding, "x-gzip"))
		{
			$encoding = "x-gzip";
		}
		if(strpos(" ".$httpaccept_encoding, "gzip"))
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
				$size = my_strlen($contents);
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
	global $mybb, $mybbuser, $db, $session;

	/* If the fid or tid is not set, set it at 0 so MySQL doesn't choke on it. */
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
		"ipaddress" => $session->ipaddress
	);
	$db->insert_query(TABLE_PREFIX."moderatorlog", $sql_array);
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
	else if($reputation > 0)
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
		$size = round(($size / 1073741824), 2) . " " . $lang->size_gb;
	}
	elseif($size >= 1048576)
	{
		$size = round(($size / 1048576), 2) . " " . $lang->size_mb;
	}
	elseif($size >= 1024)
	{
		$size = round(($size / 1024), 2) . " " . $lang->size_kb;
	}
	elseif($size == 0)
	{
		$size = "0 ".$lang->size_bytes;
	}
	else
	{
		$size = $size . " " . $lang->size_bytes;
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
	$ext = strtolower($ext);
	if($attachtypes[$ext]['icon'])
	{
		return "<img src=\"".$attachtypes[$ext]['icon']."\" border=\"0\" alt=\".$ext File\" />";
	}
	else
	{
		return "<img src=\"images/attachtypes/unknown.gif\" border=\"0\" alt=\".$ext File\" />";
	}
}

/**
 * Get a list of the unviewable forums for the current user
 *
 * @return string Comma separated values list of the forum IDs which the user cannot view
 */
function get_unviewable_forums()
{
	global $db, $forum_cache, $permissioncache, $settings, $mybb, $mybbuser, $unviewableforums, $unviewable, $templates, $mybbgroup, $forumpass;

	$pid = intval($pid);

	if(!$permissions)
	{
		$permissions = $mybbgroup;
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
			$perms = $mybbgroup;
		}
		$pwverified = 1;
		if($forum['password'] != "")
		{
			if($_COOKIE['forumpass'][$forum['fid']] != md5($mybbuser['uid'].$forum['password']))
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

function fixmktime($format, $year)
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
	global $nav, $navbits, $templates, $settings, $theme, $lang;

	eval("\$navsep = \"".$templates->get("nav_sep")."\";");

	if(is_array($navbits))
	{
		reset($navbits);
		foreach($navbits as $key => $navbit)
		{
			if(isset($navbits[$key+1]))
			{
				if(isset($navbits[$key+2])) { $sep = $navsep; } else { $sep = ""; }
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
 * @param int 1 if we're in archive mode, 0 if not
 */
function build_forum_breadcrumb($fid, $archive=0)
{
	global $pforumcache, $db, $currentitem, $forum_cache, $navbits, $lang, $archiveurl;

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
				if($archive == 1)
				{
					// Set up link to forum in breadcrumb.
					if($pforumcache[$fid][$forumnav['pid']]['type'] == 'f' || $pforumcache[$fid][$forumnav['pid']]['type'] == 'c')
					{
						$navbits[$navsize]['url'] = $archiveurl."/index.php/forum-".$forumnav['fid'].".html";
					}
					else
					{
						$navbits[$navsize]['url'] = $archiveurl."/index.php";
					}
				}
				else
				{
					$navbits[$navsize]['url'] = "forumdisplay.php?fid=".$forumnav['fid'];
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
 * Prints a debug information page
 */
function debug_page()
{
	global $db, $querytime, $debug, $templatelist, $htmldoctype, $mybb, $maintimer, $globaltime, $ptimer, $parsetime;

	$totaltime = $maintimer->totaltime;
	$phptime = $maintimer->format($maintimer->totaltime - $querytime);
	$querytime = $maintimer->format($querytime);
	$percentphp = number_format((($phptime/$maintimer->totaltime)*100), 2);
	$percentsql = number_format((($querytime/$maintimer->totaltime)*100), 2);
	$phpversion = phpversion();
	$serverload = get_server_load();
	if(strstr(getenv("REQUEST_URI"), "?"))
	{
		$debuglink = getenv("REQUEST_URI") . "&debug=1";
	}
	else
	{
		$debuglink = getenv("REQUEST_URI") . "?debug=1";
	}
	if($mybb->settings['gzipoutput'] != "no")
	{
		$gzipen = "Enabled";
	}
	else
	{
		$gzipen = "Disabled";
	}
	echo "<h1>MyBB Debug Information</h1>";
	echo "<h2>Page Generation</h2>";
	echo "<table bgcolor=\"#666666\" width=\"95%\" cellpadding=\"4\" cellspacing=\"1\" align=\"center\">";
	echo "<tr>";
	echo "<td bgcolor=\"#CCCCCC\" colspan=\"4\"><b><font size=\"2\" face=\"Tahoma\">Page Generation Statistics</font></b></td>";
	echo "</tr>";
	echo "<tr>";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">Page Generation Time:</font></b></td>";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$totaltime seconds</font></b></td>";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">No. MySQL Queries:</font></b></td>";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$db->query_count</font></b></td>";
	echo "</tr>";
	echo "<tr>";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">PHP Proccessing Time:</font></b></td>";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$phptime seconds ($percentphp%)</font></b></td>";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">MySQL Processing Time:</font></b></td>";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$querytime seconds ($percentsql%)</font></b></td>";
	echo "</tr>";
	echo "<tr>";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">Page Parsing Time:</font></b></td>";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$parsetime seconds</font></b></td>";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">Global.php Processing Time:</font></b></td>";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$globaltime seconds</font></b></td>";
	echo "</tr>";
	echo "<tr>";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">PHP Version:</font></b></td>";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$phpversion</font></b></td>";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">Server Load:</font></b></td>";
	echo "<td bgcolor=\"#FEFEFE\" width=\"25%\"><font face=\"Tahoma\" size=\"2\">$serverload</font></b></td>";
	echo "</tr>";
	echo "<tr>";
	echo "<td bgcolor=\"#EFEFEF\" width=\"25%\"><b><font face=\"Tahoma\" size=\"2\">GZip Encoding Status:</font></b></td>";
	echo "<td bgcolor=\"#FEFEFE\" width=\"75%\" colspan=\"3\"><font face=\"Tahoma\" size=\"2\">$gzipen</font></b></td>";
	echo "</tr>";
	echo "</table>";
	echo "<h2>Database Queries (".$db->query_count." Total) </h2>";
	echo $db->explain;
	echo "<h2>Template Statistics</h2>";
	echo "<b>Templates loaded at startup:</b> $templatelist<br />";
	$cached = count($templates->cache);
	echo "<b>No of templates cached:</b> $cached<br />";
	if($cached > 0)
	{
		echo "<b>Cached templates:</b> ";
		$comma = "";
		foreach($templates->cache as $key => $val)
		{
			echo "$comma$key";
			$comma = ", ";
		}
	}
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
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
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
				$db->update_query(TABLE_PREFIX."reportedposts", array('reportstatus' => 1), "pid IN($rids) AND reportstatus='0'");
			}
			break;
		case "post":
			$db->update_query(TABLE_PREFIX."reportedposts", array('reportstatus' => 1), "pid='$id' AND reportstatus='0'");
			break;
		case "threads":
			if(is_array($id))
			{
				$rids = implode($id, "','");
				$rids = "'0','$rids'";
				$db->update_query(TABLE_PREFIX."reportedposts", array('reportstatus' => 1), "tid IN($rids) AND reportstatus='0'");
			}
			break;
		case "thread":
			$db->update_query(TABLE_PREFIX."reportedposts", array('reportstatus' => 1), "tid='$id' AND reportstatus='0'");
			break;
		case "forum":
			$db->update_query(TABLE_PREFIX."reportedposts", array('reportstatus' => 1), "fid='$id' AND reportstatus='0'");
			break;
		case "all":
			$db->update_query(TABLE_PREFIX."reportedposts", array('reportstatus' => 1), "reportstatus='0'");
			break;
	}
	$plugins->run_hooks("mark_reports");
	$cache->updatereportedposts();
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
	elseif($years > 1)
	{
		$nicetime['years'] = $years." ".$lang->years;
	}

	if($months == 1)
	{
		$nicetime['months'] = "1 ".$lang->month;
	}
	elseif($months > 1)
	{
		$nicetime['months'] = $months." ".$lang->months;
	}

	if($weeks == 1)
	{
		$nicetime['weeks'] = "1 ".$lang->week;
	}
	elseif($weeks > 1)
	{
		$nicetime['weeks'] = $weeks." ".$lang->weeks;
	}

	if($days == 1)
	{
		$nicetime['days'] = "1 ".$lang->day;
	}
	elseif($days > 1)
	{
		$nicetime['days'] = $days." ".$lang->days;
	}

	if($hours == 1)
	{
		$nicetime['hours'] = "1 ".$lang->hour;
	}
	elseif($hours > 1)
	{
		$nicetime['hours'] = $hours." ".$lang->hours;
	}

	if($minutes == 1)
	{
		$nicetime['minutes'] = "1 ".$lang->minute;
	}
	elseif($minutes > 1)
	{
		$nicetime['minutes'] = $minutes." ".$lang->minutes;
	}

	if($seconds == 1)
	{
		$nicetime['seconds'] = "1 ".$lang->seconds;
	}
	elseif($seconds > 1)
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
		$query = $db->query("
			SELECT additionalgroups, usergroup
			FROM ".TABLE_PREFIX."users
			WHERE uid='$uid'
		");
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
		$query = $db->query("
			SELECT *
			FROM ".TABLE_PREFIX."users
			WHERE uid='$uid'
		");
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
	if(isset($_SERVER['REQUEST_URI']))
	{
		$location = $_SERVER['REQUEST_URI'];
	}
	elseif(isset($ENV_['REQUEST_URI']))
	{
		$location = $ENV['REQUEST_URI'];
	}
	else
	{
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
		elseif(isset($_ENV['QUERY_STRING']))
		{
			$location = "?".$_ENV['QUERY_STRING'];
		}
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
	global $db, $themeselect, $tcache, $lang, $mybb;

	if($tid == 0)
	{
		$themeselect = "<select name=\"$name\">";
		$themeselect .= "<option value=\"0\">".$lang->use_default."</option>\n";
		$themeselect .= "<option value=\"0\">-----------</option>\n";
	}
	if(!is_array($tcache))
	{
		$query = $db->query("
			SELECT name, pid, tid, allowedgroups
			FROM ".TABLE_PREFIX."themes
			ORDER BY pid, name
		");
		while($theme = $db->fetch_array($query))
		{
			$tcache[$theme['pid']][$theme['tid']] = $theme;
		}
	}
	if(is_array($tcache[$tid]))
	{
		// Figure out what groups this user is in
		$in_groups = explode(",", $mybb->user['additionalgroups']);
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
			if($is_allowed || $theme['allowedgroups'] == "all" || $usergroup_override == 1)
			{
				if($theme['tid'] == $selected)
				{
					$sel = "selected=\"selected\"";
				}
				if($theme['pid'] != 0)
				{
					$themeselect .= "<option value=\"".$theme['tid']."\" $sel>".$depth.$theme['name']."</option>";
					$depthit = $depth."--";
				}
				if(is_array($tcache[$theme['tid']]))
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
	$message = str_replace("<","&lt;",$message);
	$message = str_replace(">","&gt;",$message);
	$message = str_replace("\"","&quot;",$message);
	$message = str_replace("  ", "&nbsp;&nbsp;", $message);
	return $message;
}

/**
 * Custom function for formatting numbers.
 *
 * @param int The number to format.
 * @return int The formatted number.
 */
function mynumberformat($number)
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
					return($h);
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
 * @param int The yar.
 * @return array The number of days in each month of that year
 */
function get_bdays($in)
{
	return(array(31, ($in % 4 == 0 && ($in % 100 > 0 || $in % 400 == 0) ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31));
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

	$bdays = array($lang->sunday, $lang->monday, $lang->tuesday, $lang->wednesday, $lang->thursday, $lang->friday, $lang->saturday);
	$bmonth = array($lang->month_1, $lang->month_2, $lang->month_3, $lang->month_4, $lang->month_5, $lang->month_6, $lang->month_7, $lang->month_8, $lang->month_9, $lang->month_10, $lang->month_11, $lang->month_12);
	$find = array('m', 'd', 'y', 'Y', 'j', 'S', 'F', 'l');
	$replace = array((sprintf('%02s', $bm)), (sprintf('%02s', $bd)), (my_substr($by, 2)), $by, ($bd[0] == 0 ? my_substr($bd, 1) : $bd), ($db == 1 || $db == 21 || $db == 31 ? 'st' : ($db == 2 || $db == 22 ? 'nd' : ($db == 3 || $db == 23 ? 'rd' : 'th'))), $bmonth[$bm-1], $bdays[$wd]);
	return(str_replace($find, $replace, $display));
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
                $years = 1970-$bday[2];
                $year = $bday[2]+($years*2);
                $stamp = mktime(0, 0, 0, $bday[1], $bday[0], $year)-($years*31556926*2);
        }
        else
        {
                $stamp = mktime(0, 0, 0, $bday[1], $bday[0], $bday[2]);
        }
        $age = floor((time()-$stamp)/31556926);
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

	$query = $db->query("
		SELECT pid
		FROM ".TABLE_PREFIX."posts
		WHERE tid='$tid'
		ORDER BY dateline ASC
		LIMIT 0,1
	");
	$post = $db->fetch_array($query);
	$firstpostup = array("firstpost" => $post['pid']);
	$db->update_query(TABLE_PREFIX."threads", $firstpostup, "tid='$tid'");
}

/**
 * Checks for the length of a string, mb strings accounted for
 *
 * @param string The string to check the length of.
 * @return int The length of the string.
 */
function my_strlen($string)
{
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
 * Returns any html entities to their original character
 *
 * @param string The string to un-htmlentitize.
 * @return int The un-htmlentitied' string.
 */
function unhtmlentities($string)
{
   // replace numeric entities
   $string = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $string);
   $string = preg_replace('~&#([0-9]+);~e', 'chr(\\1)', $string);
   // replace literal entities
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
	if($event['username'])
	{
		$event_poster = "<a href=\"member.php?action=profile&amp;uid=".$event['author']."\">" . format_name($event['username'], $event['usergroup'], $event['displaygroup']) . "</a>";
	}
	else
	{
		$event_poster = $lang->guest;
	}

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
	$event_date = mydate($mybb->settings['dateformat'], $event_date);

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
	return $link;
}

/**
 * Build the profile link.
 *
 * @param string The Username of the profile.
 * @param int The user id of the profile.
 * @param string The target frame
 * @return string The url to the profile.
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
		return "<a href=\"".get_profile_link($uid)."\"{$target}>{$username}</a>";
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
		$forum_link = str_replace("{fid}", $fid, FORUM_URL_PAGED);
		return str_replace("{page}", $page, $forum_link);
	}
	else
	{
		return str_replace("{fid}", $fid, FORUM_URL);
	}
}

/**
 * Build the thread link.
 *
 * @param int The thread id of the thread.
 * @param int (Optional) The page number of the thread.
 * @return string The url to the thread.
 */
function get_thread_link($tid, $page=0)
{
	if($page > 0)
	{
		$thread_link = str_replace("{tid}", $tid, THREAD_URL_PAGED);
		return str_replace("{page}", $page, $thread_link);
	}
	else
	{
		return str_replace("{tid}", $tid, THREAD_URL);
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
		$query = $db->query("
			SELECT *
			FROM ".TABLE_PREFIX."users
			WHERE uid='".intval($uid)."'
		");
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
		$query = $db->simple_select(TABLE_PREFIX."threads", "*", "tid='".intval($tid)."'");
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
		$query = $db->simple_select(TABLE_PREFIX."posts", "*", "pid='".intval($pid)."'");
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
				if(strpos(",".$forum1['parentlist'].",", ",".$fid.",") !== false)
				{
					$inactive[] = $fid;
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
	//Note: Number of logins is defaulted to 1, because using 0 seems to clear cookie data. Not really a problem as long as we account for 1 being default.

	//Use cookie if possible, otherwise use session
	//Session stops user clearing cookies to bypass the login
	//Also use the greater of the two numbers present, stops people using scripts with altered cookie data to stay the same
	$cookielogins = intval($_COOKIE['loginattempts']);
	$cookietime = $_COOKIE['failedlogin'];
	$loginattempts = empty($cookielogins) ? $session->logins : ($cookielogins < $session->logins ? $session->logins : $cookielogins);
	$failedlogin = empty($cookietime) ? $session->failedlogin : ($cookietime < $session->failedlogin ? $session->failedlogin : $cookietime);

	//Work out if the user has had more than the allowed number of login attempts
	if($loginattempts > $mybb->settings['failedlogincount'])
	{
		//If so, then we need to work out if they can try to login again
		//Some maths to work out how long they have left and display it to them
		$now = time();
		$secondsleft = ($mybb->settings['failedlogintime'] * 3600 + (empty($_COOKIE['failedlogin']) ? $now : $_COOKIE['failedlogin'])) - $now;
		$hoursleft = floor($secondsleft / 3600);
		$minsleft = floor(($secondsleft / 60) % 60);
		$secsleft = floor($secondsleft % 60);
		//This value will be empty the first time the user doesn't login in, set it
		if(empty($failedlogin))
		{
			mysetcookie('failedlogin', $now);
			if($fatal)
			{
				error(sprintf($lang->failed_login_wait, $hoursleft, $minsleft, $secsleft));
			}
			return false;
		}
		//Work out if the user has waited long enough before letting them login again
		if($_COOKIE['failedlogin'] < $now - $mybb->settings['failedlogintime'] * 3600)
		{
			mysetcookie('loginattempts', 1);
			myunsetcookie('failedlogin');
			$db->query("UPDATE ".TABLE_PREFIX."sessions SET loginattempts = 1 WHERE sid = '{$session->sid}'");
			return 1;
		}
		//Not waited long enough
		else
		{
			if($fatal)
			{
				error(sprintf($lang->failed_login_wait, $hoursleft, $minsleft, $secsleft));
			}
			return false;
		}
	}
	//User can attempt another login
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
	if(!preg_match("/^(.+)@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/si", $email))
	{
		return false;
	}
	else
	{
		return true;
	}
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
		return strpos(strtoupper($haystack), strtoupper($needle), $offset);
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
?>