<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

/* Do not change the following line if you wish to receive
   technical support
*/

$mybboard['internalver'] = "1.00";
$mybboard['vercode'] = "100.07";

/**
 * Outputs a page directly to the browser, parsing anything which needs to be parsed.
 *
 * @param string The contents of the page.
 */
function outputpage($contents)
{
	global $db, $lang, $settings, $theme, $plugins, $mybb, $mybbuser, $mybbgroup;
	global $querytime, $debug, $templatecache, $templatelist, $maintimer, $globaltime, $parsetime;
	$ptimer = new timer();
	$contents = parsepage($contents);
	$parsetime = $ptimer->stop();
	$totaltime = $maintimer->stop();
	if($mybbgroup['cancp'] == "yes")
	{
		$phptime = $maintimer->format($maintimer->totaltime - $querytime);
		$querytime = $maintimer->format($querytime);
		$percentphp = number_format((($phptime/$maintimer->totaltime)*100), 2);
		$percentsql = number_format((($querytime/$maintimer->totaltime)*100), 2);
		$phpversion = phpversion();
		$serverload = serverload();
		if(strstr(getenv("REQUEST_URI"), "?"))
		{
			$debuglink = htmlspecialchars(getenv("REQUEST_URI")) . "&debug=1";
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
		$other = "PHP version: $phpversion / Server Load: $serverload / GZip Compression: $gzipen";
		$debugstuff = "Generated in $totaltime seconds ($percentphp% PHP / $percentsql% MySQL)<br />MySQL Queries: $db->query_count / Parsing $parsetime / Global Parsing Time: $globaltime<br />$other<br />[<a href=\"$debuglink\" target=\"_blank\">advanced details</a>]<br />";
		$contents = str_replace("<debugstuff>", $debugstuff, $contents);
		if(isset($mybb->input['debug']))
		{
			debugpage();
		}
	}
	else
	{
		$contents = str_replace("<debugstuff>", "", $contents);
	}
	$contents = $plugins->run_hooks("pre_output_page", $contents);

	if($mybb->settings['gzipoutput'] != "no")
	{
		$contents = gzipencode($contents, $mybb->settings['gziplevel']);
	}
	echo $contents;
	$plugins->run_hooks("post_output_page");
	if(NO_SHUTDOWN)
	{
		run_shutdown();
	}
}

/**
 * Runs the shutdown queries after the page has been sent to the browser.
 *
 */
function run_shutdown()
{
	global $db;
	if(is_array($db->shutdown_queries))
	{
		foreach($db->shutdown_queries as $query)
		{
			$db->query($query);
		}
	}
}

/**
 * Parses the contents of a page before outputting it.
 *
 * @param string The contents of the page.
 * @return string The parsed page.
 */
function parsepage($contents)
{
	global $db, $lang, $settings, $theme, $mybb, $mybbuser, $mybbgroup, $htmldoctype;
	global $loadpmpopup;

	$contents = str_replace("<navigation>", buildnav(1), $contents);
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
		if(substr($_SERVER['PHP_SELF'], -strlen("private.php")) != "private.php")
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
		if(isset($mybb->user['timezone']))
		{
			$offset = $mybb->user['timezone'];
		}
		elseif(defined("IN_ADMINCP"))
		{
			$offset =  $mybbadmin['timezone'];
		}
		else
		{
			$offset = $mybb->settings['timezoneoffset'];
		}
	}
	if($offset == "-")
	{
		$offset = 0;
	}
	$date = gmdate($format, $stamp + ($offset * 3600));
	if($mybb->settings['dateformat'] == $format && $ty)
	{
		$stamp = mktime();
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
 */
function mymail($to, $subject, $message, $from="")
{
	global $db, $mybb;
	// For some reason sendmail/qmail doesn't like \r\n
	$sendmail = @ini_get('sendmail_path');
	if($sendmail)
	{
		$message = preg_replace("#(\r\n|\r|\n)#s", "\n", $message);
	}
	else
	{
		$message = preg_replace("#(\r\n|\r|\n)#s", "\r\n", $message);
	}
	if(strlen(trim($from)) == 0)
	{
		$from = "\"".$mybb->settings['bbname']." Mailer\" <".$mybb->settings['adminemail'].">";
	}
	mail($to, $subject, $message, "From: $from");
}

/**
 * Return a parent list for the specified forum.
 *
 * @param int The forum id to get the parent list for.
 * @return string The comma-separated parent list.
 */
function getparentlist($fid)
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

//
// Generate a parent list suitable for queries
//
function buildparentlist($fid, $column="fid", $joiner="OR", $parentlist="")
{
	$parentlist = (!$parentlist) ? getparentlist($fid) : $parentlist;
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

//
// Cache forums in the memory
//
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

//
// Produce a user friendly error message page
//
function error($error, $title="")
{
	global $header, $footer, $css, $toplinks, $settings, $theme, $headerinclude, $db, $templates, $lang, $mybb;
	$title = (!$title) ? $mybb->settings['bbname'] : $title;
	$timenow = mydate($mybb->settings['dateformat'], time()) . " " . mydate($mybb->settings['timeformat'], time());
	resetnav();
	addnav($lang->error);
	eval("\$errorpage = \"".$templates->get("error")."\";");
	outputpage($errorpage);
	exit;
}

//
// Produce an inline error message
//
function inlineerror($errors, $title="")
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
 *
 */
function nopermission()
{
	global $REQUEST_URI, $mybb, $mybbuser, $theme, $templates, $ipaddress, $db, $lang, $plugins, $session;
	$time = time();
	$plugins->run_hooks("no_permission");
	$noperm_array = array (
		"nopermission" => '1',
		"location1" => 0,
		"location2" => 0
	);
	$db->update_query(TABLE_PREFIX."sessions", $noperm_array, "sid='".$session->sid."'");
	$plate = "error_nopermission".(($mybb->user['uid']!=0)?"_loggedin":"");
	$url = $REQUEST_URI;
	eval("\$errorpage = \"".$templates->get($plate)."\";");
	error($errorpage);
}

//
// Redirect the user to the given url with the given message
//
function redirect($url, $message="You will now be redirected", $title="")
{
	global $header, $footer, $css, $toplinks, $settings, $mybb, $theme, $headerinclude, $templates, $lang, $plugins;
	$timenow = mydate($mybb->settings['dateformat'], time()) . " " . mydate($mybb->settings['timeformat'], time());
	$plugins->run_hooks("redirect");
	if(!$title)
	{
		$title = $mybb->settings['bbname'];
	}
	if($mybb->settings['redirects'] == "on" && $mybb->user['showredirect'] != "no")
	{
		eval("\$redirectpage = \"".$templates->get("redirect")."\";");
		outputpage($redirectpage);
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

//
// Generate the multi page listing
//
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
		for($i=$from;$i<=$to;$i++)
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

//
// Check if a certain forum by id exists
//
function validateforum($fid)
{
	global $db;
	$query = $db->query("SELECT fid FROM ".TABLE_PREFIX."forums WHERE fid='$fid'");
	$validforum = $db->fetch_array($query);
	return (($fid=$validforum['fid'])? true:false);
}

//
// Build the usergroup permissions for a specific user
//
function user_permissions($uid=0)
{
	global $mybb, $cache, $groupscache, $usercache;

	if($uid == 0)
	{
		$uid = $mybb->user['uid'];
	}

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
		$groupperms = $mybb->usergroup;
	}
	return $groupperms;
}

//
// Build the usergroup permissions for a user in group(s)
//
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

//
// Build the display group details for the given group
//
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

//
// Build forum permissions for the specific forum, user or group
//
function forum_permissions($fid=0, $uid=0, $gid=0)
{
	global $db, $cache, $groupscache, $forum_cache, $fpermcache, $mybbgroup, $mybbuser, $mybb, $usercache, $fpermissionscache;
	if(!$uid)
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

//
// Perform inheritance scheme for forum permissions
//
function fetch_forum_permissions($fid, $gid, $groupperms)
{
	global $groupscache, $forum_cache, $fpermcache, $mybb;
	$groups = explode(",", $gid);
	if(!$fpermcache[$fid]) // This forum has no custom or inherited permisssions so lets just return the group permissions
	{
		return $groupperms;
	}
	else // Okay, we'll do it the hard way because this forum must have some custom or inherited permissions
	{
		foreach($groups as $gid)
		{
			if($gid && $groupscache[$gid])
			{
				if(!is_array($fpermcache[$fid][$gid]))
				{
					continue;
				}
				foreach($fpermcache[$fid][$gid] as $perm => $access)
				{
					if($perm != "fid" && $perm != "gid" && $perm != "pid")
					{
						$permbit = $forumpermissions[$perm];
						if($access > $permbit || ($access == "yes" && $permbit == "no") || !$permbit)
						{
							$forumpermissions[$perm] = $access;
						}
					}
				}
			}
		}
	}
	if(!isset($forumpermissions))
	{
		$forumpermissions = $groupperms;
	}
	return $forumpermissions;
}

//
// Check the password given on a certain forum for validity
//
function checkpwforum($fid, $password="")
{
	global $mybb, $mybbuser, $toplinks, $header, $settings, $footer, $css, $headerinclude, $theme, $_SERVER, $breadcrumb, $templates, $lang;
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
		outputpage($pwform);
		exit;
	}
}

//
// Get the permissions for a specific moderator in a certain forum
//
function getmodpermissions($fid, $uid="0", $parentslist="")
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
			$parentslist = getparentlist($fid);
		}
		$sql = buildparentlist($fid, "fid", "OR", $parentslist);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."moderators WHERE uid='$uid' AND $sql");
		$perms = $db->fetch_array($query);
		$modpermscache[$uid][$fid] = $perms;
	}
	else
	{
		$perms = $modpermscache[$uid][$fid];
	}
	return $perms;
}

//
// Returns the permissions a moderator has to perform a specific function
//
function ismod($fid="0", $action="", $uid="0")
{
	global $mybb, $mybbuser, $db, $mybbgroup;

	if(!$uid)
	{
		$uid = $mybb->user['uid'];
	}

	if($mybbgroup['issupermod'] == "yes")
	{
		return "yes";
	} else {
		if(!$fid)
		{
			$query = $db->query("SELECT mid FROM ".TABLE_PREFIX."moderators WHERE uid='$uid'");
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
			$modperms = getmodpermissions($fid, $uid);
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
function getposticons()
{
	global $mybb, $db, $icon, $settings, $theme, $templates, $lang;
	$listed = 0;
	$no_icons_checked = " checked=\"checked\"";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."icons ORDER BY name DESC");
	while($dbicon = $db->fetch_array($query))
	{
		if($mybb->input['icon'] == $dbicon['iid'])
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
function serverload()
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

function updateforumcount($fid) {
	global $db, $cache;
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE CONCAT(',',parentlist,',') LIKE '%,$fid,%'");
	while($childforum = $db->fetch_array($query))
	{
		if($fid == $childforum['fid'])
		{
			$parentlist = $childforum['parentlist'];
			$flastpost = $childforum['lastpost'];
		}
		elseif($fid == $childforum['pid'])
		{
			$threads = $threads + $childforum['threads'];
			$posts = $posts + $childforum['posts'];
		}
		$childforums .= ",'".$childforum['fid']."'";
	}
	$query = $db->query("SELECT MAX(lastpost) AS lastpost FROM ".TABLE_PREFIX."threads WHERE fid IN (0$childforums) AND visible='1' AND closed NOT LIKE 'moved|%'");
	$lastpost = $db->fetch_array($query);
	if($lastpost['lastpost'] != $flastpost)
	{ // Lastpost has changed, lets update
		$query = $db->query("SELECT lastpost, lastposter, tid FROM ".TABLE_PREFIX."threads WHERE lastpost='".$lastpost['lastpost']."' AND visible='1' AND closed NOT LIKE 'moved|%'");
		$lp = $db->fetch_array($query);
		$lp['lastposter'] = addslashes($lp['lastposter']);
		$lpadd = ",lastpost='".$lp['lastpost']."', lastposter='".$lp['lastposter']."', lastposttid='".$lp['tid']."'";
	}

	// Get the post counters for this forum and its children
	$query = $db->query("SELECT COUNT(*) AS totthreads, SUM(replies) AS totreplies FROM ".TABLE_PREFIX."threads WHERE fid='$fid' AND visible='1' AND closed NOT LIKE 'moved|%'");
	$posts2 = $db->fetch_array($query);
	if($posts2)
	{
		$nothreads = $posts2['totthreads'] + $threads;
		$noposts = $posts2['totthreads'] + $posts2['totreplies'] + $posts;
	}
	else
	{
		$nothreads = 0;
		$noposts = 0;
	}
	$db->query("UPDATE ".TABLE_PREFIX."forums SET posts='$noposts', threads='$nothreads' $lpadd WHERE fid='$fid'");
	if($parentlist && $db->affected_rows())
	{
		$parentsexploded = explode(",", $parentlist);
		foreach($parentsexploded as $key => $val)
		{
			if($val && $val != $fid)
			{
				updateforumcount($val);
			}
		}
	}
}

function updatethreadcount($tid)
{
	global $db, $cache;
	$query = $db->query("SELECT COUNT(*) AS replies FROM ".TABLE_PREFIX."posts WHERE tid='$tid' AND visible='1'");
	$replies = $db->fetch_array($query);
	$treplies = $replies['replies'] - 1;
	if($treplies < 0)
	{
		$treplies = 0;
	}
	$query = $db->query("SELECT u.uid, u.username, p.username AS postusername, p.dateline FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE p.tid='$tid' AND p.visible='1' ORDER BY p.dateline DESC LIMIT 1");
	$lastpost = $db->fetch_array($query);

	$query = $db->query("SELECT u.uid, u.username, p.username AS postusername, p.dateline FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE p.tid='$tid' AND p.visible='1' ORDER BY p.dateline ASC LIMIT 0,1");
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
	$lastpost['username'] = addslashes($lastpost['username']);
	$firstpost['username'] = addslashes($firstpost['username']);
	$db->query("UPDATE ".TABLE_PREFIX."threads SET username='".$firstpost['username']."', uid='".$firstpost['uid']."', lastpost='".$lastpost['dateline']."', lastposter='".$lastpost['username']."', replies='$treplies' WHERE tid='$tid'");
}

function deletethread($tid)
{
	global $db, $cache, $plugins;
	$query = $db->query("SELECT p.pid, p.uid, p.visible, f.usepostcounts FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid) WHERE p.tid='$tid'");
	$num_unapproved_posts = 0;
	while($post = $db->fetch_array($query))
	{
		if($userposts[$post['uid']])
		{
			$userposts[$post['uid']]--;
		}
		else
		{
			$userposts[$post['uid']] = -1;
		}
		$pids .= $post['pid'].",";
		$usepostcounts = $post['usepostcounts'];
		remove_attachments($post['pid']);

		// If the post is unapproved, count it!
		if($post['visible'] == 0)
		{
			$num_unapproved_posts++;
		}
	}
	if($usepostcounts != "no")
	{
		if(is_array($userposts))
		{
			foreach($userposts as $uid => $subtract)
			{
				$db->query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum$subtract WHERE uid='$uid'");
			}
		}
	}
	if($pids)
	{
		$pids .= "0";
		$db->query("DELETE FROM ".TABLE_PREFIX."posts WHERE pid IN ($pids)");
		$db->query("DELETE FROM ".TABLE_PREFIX."attachments WHERE pid IN ($pids)");
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
	$thread = $db->fetch_array($query);

	// Update unapproved post/thread numbers
	$update_unapproved = "";
	if($thread['visible'] == 0)
	{
		$update_unapproved .= "unapprovedthreads=unapprovedthreads-1";
	}
	if($num_unapproved_posts > 0)
	{
		if(!empty($update_unapproved))
		{
			$update_unapproved .= ", ";
		}
		$update_unapproved .= "unapprovedposts=unapprovedposts-".$num_unapproved_posts;
	}
	if(!empty($update_unapproved))
	{
		$db->query("UPDATE ".TABLE_PREFIX."forums SET $update_unapproved WHERE fid='$thread[fid]'");
	}

	$db->query("DELETE FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
	$db->query("DELETE FROM ".TABLE_PREFIX."threads WHERE closed='moved|$tid'");
	$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE tid='$tid'");
	$db->query("DELETE FROM ".TABLE_PREFIX."polls WHERE tid='$tid'");
	$db->query("DELETE FROM ".TABLE_PREFIX."pollvotes WHERE pid='".$thread['poll']."'");
	$cache->updatestats();
	$plugins->run_hooks("delete_thread", $tid);
}

function deletepost($pid, $tid="")
{
	global $db, $cache, $plugins;
	$query = $db->query("SELECT p.pid, p.uid, p.fid, p.tid, p.visible, f.usepostcounts FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid) WHERE p.pid='$pid'");
	$post = $db->fetch_array($query);
	if($post['usepostcounts'] != "no")
	{
		$db->query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum-1 WHERE uid='".$post['uid']."'");
	}
	$db->query("DELETE FROM ".TABLE_PREFIX."posts WHERE pid='$pid'");
	remove_attachments($pid);

	// Update unapproved post count
	if($post['visible'] == 0)
	{
		$db->query("UPDATE ".TABLE_PREFIX."forums SET unapprovedposts=unapprovedposts-1 WHERE fid='$post[fid]'");
		$db->query("UPDATE ".TABLE_PREFIX."threads SET unapprovedposts=unapprovedposts-1 WHERE tid='$post[tid]'");
	}
	$plugins->run_hooks("delete_post", $tid);
	$cache->updatestats();
}


function makeforumjump($pid="0", $selitem="", $addselect="1", $depth="", $showextras="1", $permissions="", $name="fid")
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
						$forumjumpbits .= makeforumjump($forum['fid'], $selitem, 0, $newdepth, $showextras);
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
function getextension($file)
{
	return strtolower(substr(strrchr($file, "."), 1));
}

/**
 * Deprecated function that returns the extension of a file.
 *
 * @param string The filename.
 * @return string The extension of the file.
 */
function getextention($file)
{
	return getextension($file);
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
	for($i=1;$i<=$length;$i++)
	{
		$ch = rand(0, count($set)-1);
		$str .= $set[$ch];
	}
	return $str;
}

function formatname($username, $usergroup, $displaygroup="")
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

function makebbcodeinsert()
{
	global $db, $mybb, $settings, $theme, $templates, $lang;
	if($mybb->settings['bbcodeinserter'] != "off")
	{
		eval("\$codeinsert = \"".$templates->get("codebuttons")."\";");
	}
	return $codeinsert;
}
function makesmilieinsert()
{
	global $db, $smiliecache, $settings, $theme, $templates, $lang, $mybb;
	if($mybb->settings['smilieinserter'] != "off" && $mybb->settings['smilieinsertercols'] && $mybb->settings['smilieinsertertot'])
	{
		$smiliecount = 0;
		if(!$smiliecache)
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."smilies WHERE showclickable!='no' ORDER BY disporder");

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
						$smilies .=  "<tr>";
					}
					$find = addslashes(htmlspecialchars($find));
					$smilies .= "<td><img src=\"$image\" border=\"0\" class=\"smilie\" alt=\"$find\"></td>";
					$i++;
					$counter++;
					if($counter == $mybb->settings['smilieinsertercols'])
					{
						$counter = 0;
						$smilies .= "</tr><tr>";
					}
				}
			}
			$colspan = $mybb->settings['smilieinsertercols'] - $counter;
			if($colspan > 0)
			{
				$smilies .= "<td colspan=\"$colspan\">&nbsp;</td></tr>";
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

function gzipencode($contents, $level=1)
{
	global $_SERVER;
	if(function_exists("gzcompress") && function_exists("crc32") && !headers_sent())
	{
		if(strpos(" ".$_SERVER['HTTP_ACCEPT_ENCODING'], "x-gzip"))
		{
			$encoding = "x-gzip";
		}
		if(strpos(" ".$_SERVER['HTTP_ACCEPT_ENCODING'], "gzip"))
		{
			$encoding = "gzip";
		}
		if($encoding)
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
				$gzdata .= substr(gzcompress($contents, $level), 2, -4);
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
function logmod($data, $action="")
{
	global $mybb, $mybbuser, $db, $session;

	/* If the fid or tid is not set, set it at 0 so MySQL doesn't choke on it. */
	if($data['fid'] == '')
	{
		$data['fid'] = 0;
	}
	if($data['tid'] == '')
	{
		$data['tid'] = 0;
	}

	$time = time();

	$sql_array = array(
		"uid" => $mybb->user['uid'],
		"dateline" => $time,
		"fid" => $data['fid'],
		"tid" => $data['tid'],
		"action" => $action,
		"ipaddress" => $session->ipaddress
	);
	$db->insert_query(TABLE_PREFIX."moderatorlog", $sql_array);
}

function getreputation($reputation)
{
	global $theme;

	if(strpos(" ".$reputation, "-"))
	{
		return "<span style=\"color: red;\">".$reputation."</span>";
	}
	else if($reputation > 0)
	{
		return "<span style=\"color: green;\">".$reputation."</span>";
	}
	else if($reputation == 0)
	{
		return "0";
	}
}

function getip() {
	global $_SERVER;
	if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
	{
		if(preg_match_all("#[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}#s", $_SERVER['HTTP_X_FORWARDED_FOR'], $addresses))
		{
			while(list($key, $val) = each($addresses[0]))
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

function getfriendlysize($size)
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

function getattachicon($ext)
{
	global $cache, $attachtypes;
	if(!$attachtypes) $attachtypes = $cache->read("attachtypes");
	$ext = strtolower($ext);
	if($attachtypes[$ext])
	{
		return "<img src=\"".$attachtypes[$ext]['icon']."\" border=\"0\" alt=\".$ext File\" />";
	}
	else
	{
		return "<img src=\"images/attachtypes/unknown.gif\" border=\"0\" alt=\".$ext File\" />";
	}
}

function getunviewableforums()
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

if(!function_exists("stripos"))
{
	function stripos($haystack, $needle, $offset=0)
	{
		return strpos(strtoupper($haystack), strtoupper($needle), $offset);
	}
}

function fixmktime($format, $year)
{
	// Our little work around for the date < 1970 thing.
	// -2 idea provided by Matt Light (http://www.mephex.com)
	$format = str_replace("Y", $year, $format);
	$format = str_replace("y", substr($year, -2), $format);
	return $format;
}

function buildnav($finished=1)
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
	if($nav) {
		eval("\$activesep = \"".$templates->get("nav_sep_active")."\";");
	}
	eval("\$activebit = \"".$templates->get("nav_bit_active")."\";");
	eval("\$donenav = \"".$templates->get("nav")."\";");
	return $donenav;
}

function addnav($name, $url="") {
	global $navbits;
	$navsize = count($navbits);
	$navbits[$navsize]['name'] = $name;
	$navbits[$navsize]['url'] = $url;
}

function makeforumnav($fid, $archive=0)
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
					makeforumnav($forumnav['pid'], $archive);
				}
				$navsize = count($navbits);
				$navbits[$navsize]['name'] = $forumnav['name'];
				if($archive == 1)
				{
					if($pforumcache[$fid][$forumnav['pid']]['type'] == "f")
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

function resetnav()
{
	global $navbits, $_GLOBAL;
	$newnav[0]['name'] = $navbits[0]['name'];
	$newnav[0]['url'] = $navbits[0]['url'];
	unset($GLOBALS['navbits']);
	$GLOBALS['navbits'] = $newnav;
}

function debugpage() {
	global $db, $querytime, $debug, $templatecache, $templatelist, $htmldoctype, $mybb, $mybbuser, $maintimer, $globaltime, $settings, $mybbgroup, $lang, $ptimer, $parsetime;
	$totaltime = $maintimer->totaltime;
	$phptime = $maintimer->format($maintimer->totaltime - $querytime);
	$querytime = $maintimer->format($querytime);
	$percentphp = number_format((($phptime/$maintimer->totaltime)*100), 2);
	$percentsql = number_format((($querytime/$maintimer->totaltime)*100), 2);
	$phpversion = phpversion();
	$serverload = serverload();
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
		while(list($key, $val) = each($templates->cache))
		{
			echo "$comma$key";
			$comma = ", ";
		}
	}
	exit;
}

/**
 * Outputs the correct page headers.
 *
 */
function pageheaders()
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

function getthread($tid)
{
	global $tcache, $db;
	if(!$tcache[$tid])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
		$tcache[$tid] = $db->fetch_array($query);
	}
	return $tcache[$tid];
}

function getpost($pid)
{
	global $pcache, $db;
	if(!$pcache[$pid])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE pid='$pid'");
		$pcache[$pid] = $db->fetch_array($query);
	}
	return $pcache[$pid];
}
function getforum($fid)
{
	global $fcache, $db;
	if(!$fcache[$fid])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$fid'");
		$fcache[$fid] = $db->fetch_array($query);
	}
	return $fcache[$fid];
}

function markreports($id, $type="post")
{
	global $db, $cache, $plugins;
	switch($type)
	{
		case "posts":
			if(is_array($id))
			{
				$rids = implode($id, "','");
				$rids = "'0','$rids'";
				$db->query("UPDATE ".TABLE_PREFIX."reportedposts SET reportstatus='1' WHERE pid IN($rids) AND reportstatus='0'");
			}
			break;
		case "post":
			$db->query("UPDATE ".TABLE_PREFIX."reportedposts SET reportstatus='1' WHERE pid='$id' AND reportstatus='0'");
			break;
		case "threads":
			if(is_array($id))
			{
				$rids = implode($id, "','");
				$rids = "'0','$rids'";
				$db->query("UPDATE ".TABLE_PREFIX."reportedposts SET reportstatus='1' WHERE tid IN($rids) AND reportstatus='0'");
			}
			break;
		case "thread":
			$db->query("UPDATE ".TABLE_PREFIX."reportedposts SET reportstatus='1' WHERE tid='$id' AND reportstatus='0'");
			break;
		case "forum":
			$db->query("UPDATE ".TABLE_PREFIX."reportedposts SET reportstatus='1' WHERE fid='$id' AND reportstatus='0'");
			break;
		case "all":
			$db->query("UPDATE ".TABLE_PREFIX."reportedposts SET reportstatus='1' WHERE reportstatus='0'");
			break;
	}
	$plugins->run_hooks("mark_reports");
	$cache->updatereportedposts();
}

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
		return $false;
	}
}

function alt_trow($reset=0)
{
	global $alttrow;
	if($alttrow == "trow1" || $reset)
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

function join_usergroup($uid, $joingroup)
{
	global $db, $mybbuser;
	if($uid == $mybbuser['uid'])
	{
		$user = $mybbuser;
	}
	else
	{
		$query = $db->query("SELECT additionalgroups, usergroup FROM ".TABLE_PREFIX."users WHERE uid='$uid'");
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
	$db->query("UPDATE ".TABLE_PREFIX."users SET additionalgroups='$groupslist' WHERE uid='$uid'");
}

function leave_usergroup($uid, $leavegroup)
{
	global $db, $mybbuser;
	if($uid == $mybbuser['uid'])
	{
		$user = $mybbuser;
	}
	else
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='$uid'");
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
	$db->query("UPDATE ".TABLE_PREFIX."users SET additionalgroups='$groupslist' $dispupdate WHERE uid='$uid'");
}

function get_current_location()
{
	global $_ENV, $_SERVER, $_POST;
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
		if(isset($_POST['action']))
		{
			$addloc[] = "action=".$_POST['action'];
		}
		if(isset($_POST['fid']))
		{
			$addloc[] = "fid=".$_POST['fid'];
		}
		if(isset($_POST['tid']))
		{
			$addloc[] = "tid=".$_POST['tid'];
		}
		if(isset($_POST['pid']))
		{
			$addloc[] = "pid=".$_POST['pid'];
		}
		if(isset($_POST['uid']))
		{
			$addloc[] ="uid=".$_POST['uid'];
		}
		if(isset($_POST['eid']))
		{
			$addloc[] = "eid=".$_POST['eid'];
		}
		if(isset($addlock) && is_array($addloc))
		{
			$location .= "?".implode("&", $addloc);
		}
	}
	return $location;
}

function themeselect($name, $selected="", $tid=0, $depth="")
{
	global $db, $themeselect, $tcache, $lang;
	if(!$tid)
	{
		$themeselect = "<select name=\"$name\">";
		$themeselect .= "<option value=\"0\">".$lang->use_default."</option>\n";
		$themeselect .= "<option value=\"0\">-----------</option>\n";
	}
	if(!is_array($tcache))
	{
		$query = $db->query("SELECT name,pid,tid FROM ".TABLE_PREFIX."themes ORDER BY pid, name");
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
				themeselect($name, $selected, $theme['tid'], $depthit);
			}
		}
	}
	if(!$tid)
	{
		$themeselect .= "</select>";
	}
	return $themeselect;
}

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
			$decimals = strlen($parts[1]);
		}
		else
		{
			$decimals = 0;
		}
		return number_format($number, $decimals, $mybb->settings['decpoint'], $mybb->settings['thousandssep']);
	}
}

// Birthday code fix's provided by meme
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

function get_bdays($in)
{
	return(array(31, ($in % 4 == 0 && ($in % 100 > 0 || $in % 400 == 0) ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31));
}

function format_bdays($display, $bm, $bd, $by, $wd)
{
	global $lang;
	$bdays = array($lang->sunday, $lang->monday, $lang->tuesday, $lang->wednesday, $lang->thursday, $lang->friday, $lang->saturday);
	$bmonth = array($lang->month_1, $lang->month_2, $lang->month_3, $lang->month_4, $lang->month_5, $lang->month_6, $lang->month_7, $lang->month_8, $lang->month_9, $lang->month_10, $lang->month_11, $lang->month_12);
	$find = array('m', 'd', 'y', 'Y', 'j', 'S', 'F', 'l');
	$replace = array((sprintf('%02s', $bm)), (sprintf('%02s', $bd)), (substr($by, 2)), $by, ($bd[0] == 0 ? substr($bd, 1) : $bd), ($db == 1 || $db == 21 || $db == 31 ? 'st' : ($db == 2 || $db == 22 ? 'nd' : ($db == 3 || $db == 23 ? 'rd' : 'th'))), $bmonth[$bm-1], $bdays[$wd]);
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
	$query = $db->query("SELECT pid FROM ".TABLE_PREFIX."posts WHERE tid='$tid' ORDER BY dateline ASC LIMIT 0,1");
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

// From PHP Manual...
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
		$event_poster = "<a href=\"member.php?action=profile&amp;uid=".$event['author']."\">" . formatname($event['username'], $event['usergroup'], $event['displaygroup']) . "</a>";
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

function build_profile_link($username="", $uid=0)
{
	global $lang;

	if(!$username)
	{
		return $lang->guest;
	}
	else if($uid == 0)
	{
		return $username;
	}
	else
	{
		return "<a href=\"".str_replace("{uid}", $uid, PROFILE_URL)."\">".$htmlspecialchars."</a>";
	}
}

function build_forum_link($fid, $title, $page=0)
{
	if($page > 0)
	{
		$forum_link = str_replace("{fid}", $fid, FORUM_URL_PAGED);
		$forum_link = str_replace("{page}", $page, FORUM_URL_PAGED);
	}
	else
	{
		$forum_link = str_replace("{fid}", $fid, FORUM_URL);
	}
	return "<a href=\"".$forum_link."\">".$title."</a>";
}
function build_thread_link($tid, $subject, $page=0)
{
	if($page > 0)
	{
		$thread_link = str_replace("{tid}", $tid, THREAD_URL_PAGED);
		$thread_link = str_replace("{page}", $page, THREAD_URL_PAGED);
	}
	else
	{
		$thread_link = str_replace("{tid}", $fid, THREAD_URL);
	}
	return "<a href=\"".$thread_link."\">".$subject."</a>";
}

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
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".intval($uid)."'");
		$user_cache[$uid] = $db->fetch_array($query);
		return $user_cache[$uid];
	}
}

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

function get_thread($tid)
{
}

function get_post($pid)
{
}

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

?>