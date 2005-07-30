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

$mybboard['internalver'] = "1.00 Preview Release 1";
$mybboard['vercode'] = "100.05";

//
// Outputs the contents of a page rendering variables
//
function outputpage($contents)
{
	global $db, $lang, $settings, $theme, $plugins, $mybb, $mybbuser, $mybbgroup;
	global $querytime, $debug, $templatecache, $templatelist, $htmldoctype, $maintimer, $globaltime, $parsetime;
	$ptimer = new timer();
	$contents = parsepage($contents);
	//$contents = stripslashes($contents);
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
			$debuglink = getenv("REQUEST_URI") . "&debug=1";
		}
		else
		{
			$debuglink = getenv("REQUEST_URI") . "?debug=1";
		}
		if($settings['gzipoutput'] != "no")
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
		if($mybb->input['debug'])
		{
			debugpage();
		}
	}
	else
	{
		$contents = str_replace("<debugstuff>", "", $contents);
	}
	$contents = $plugins->run_hooks("pre_output_page", $contents);

	if($settings['gzipoutput'] != "no")
	{
		$contents = gzipencode($contents, $settings['gziplevel']);
	}
	echo $contents;
	$plugins->run_hooks("post_output_page");
	if(NO_SHUTDOWN)
	{
		run_shutdown();
	}
}

//
// Run queries stated for shutdown
//
function run_shutdown()
{
	global $db, $shutdown_queries;
	if(is_array($shutdown_queries))
	{
		foreach($shutdown_queries as $query)
		{
			$db->query($query);
		}
	}
}

//
// Parse the contents of a page before outputting it
//
function parsepage($contents)
{
	global $db, $lang, $settings, $theme, $mybb, $mybbuser, $mybbgroup;
	global $loadpmpopup, $PHP_SELF;

	$contents = str_replace("<navigation>", buildnav(1), $contents);
	$contents = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n".$contents;
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
		if(substr($PHP_SELF, -strlen("private.php")) != "private.php")
		{
			$contents = str_replace("<body", "<body onload=\"Javascript:newPM()\"", $contents);
		}
	}
	return $contents;
}

//
// Turn a unix timestamp in to a "friendly" date/time format for the user
//
function mydate($format, $stamp, $offset="", $ty=1)
{
	global $mybbuser, $settings, $lang, $mybbadmin;
	if(!$offset)
	{
		if(isset($mybbuser['timezone']))
		{
			$offset = $mybbuser['timezone'];
		}
		elseif(isset($mybbadmin['timezone']))
		{
			$offset =  $mybbadmin['timezone'];
		}
		else
		{
			$offset = $settings['timezoneoffset'];
		}
	}
	if($offset == "-")
	{
		$offset = 0;
	}
	$date = gmdate($format, $stamp + ($offset * 3600));
	if($settings['dateformat'] == $format && $ty && $settings['todayyesterday'] != "no")
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

//
// MyBB mail wrapper (soon to be a class)
//
function mymail($to, $subject, $message, $from="")
{
	global $db, $mybb, $settings;
	if(strlen(trim($from)) == 0)
	{
		$from = "\"".$settings['bbname']." Mailer\" <".$settings['adminemail'].">";
	}
	mail($to, $subject, $message, "From: $from");
}

//
// Return a parent list for the given forum
//
function getparentlist($fid)
{
	global $db, $forumcache;
	static $forumarraycache;

	if($forumarraycache[$fid])
	{
		return $forumarraycache[$fid]['parentlist'];
	}
	elseif($forumcache[$fid])
	{
		return $forumcache[$fid]['parentlist'];
	}
	else
	{
		$query = $db->query("SELECT parentlist FROM ".TABLE_PREFIX."forums WHERE fid='$fid'");
		$forum = $db->fetch_array($query);
		$forumarraycache[$fid]['parentlist'] = $forum['parentlist'];
		return $forum['parentlist'];
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
	while(list($key, $val) = each($parentsexploded))
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
function cacheforums()
{
	global $forumcache, $db, $cache;
	if(!$forumcache)
	{
		$forumcache = $cache->read("forums");
		if(!$forumcache)
		{
			$cache->updateforums();
			$forumcache = $cache->read("forums", 1);
		}
	}
	return $forumcache;
}

//
// Produce a user friendly error message page
//
function error($error, $title="")
{
	global $header, $footer, $css, $toplinks, $settings, $theme, $headerinclude, $db, $templates, $lang;
	$title = (!$title) ? $settings['bbname'] : $title;
	$timenow = mydate($settings['dateformat'], time()) . " " . mydate($settings['timeformat'], time());
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
	global $theme, $settings, $db, $lang, $templates;
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

//
// Generate a "no permission" error message page
//
function nopermission()
{
	global $REQUEST_URI, $mybb, $mybbuser, $theme, $templates, $ipaddress, $db, $lang, $plugins;
	$time = time();
	$plugins->run_hooks("no_permission");
	$db->query("UPDATE ".TABLE_PREFIX."online SET uid='".$mybb->user['uid']."', time='$time', location='nopermission', ip='$ipaddress' WHERE ip='$ipaddress' OR uid='".$mybb->user['uid']."'");
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
	global $header, $footer, $css, $toplinks, $settings, $theme, $headerinclude, $templates, $lang, $plugins;
	$timenow = mydate($settings['dateformat'], time()) . " " . mydate($settings['timeformat'], time());
	$plugins->run_hooks("redirect");
	if(!$title)
	{
		$title = $settings['bbname'];
	}
	if($settings['redirects'] == "on")
	{	
		eval("\$redirectpage = \"".$templates->get("redirect")."\";");
		outputpage($redirectpage);
	}
	else
	{
		$url = str_replace("#", "&#", $url);
		header("Location: $url");
	}
	exit;
}

//
// Generate the multi page listing
//
function multipage($count, $perpage, $page, $url)
{
	global $settings, $theme, $templates, $lang;
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
				if(in_array($perm, $groupzerogreater) && $access == 0)
				{
					$usergroup[$perm] = 0;
				}
				elseif($access > $permbit)
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
	global $db, $cache, $groupscache, $forumcache, $fpermcache, $mybbgroup, $mybbuser, $mybb, $usercache, $fpermissionscache;
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
			$gid = $mybb->user['usergroup'].",".$mybbuser['additionalgroups'];
			$groupperms = $mybbgroup;
		}
	}
	if(!is_array($forumcache))
	{
		cacheforums();
	}
	if(!is_array($forumcache))
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
		foreach($forumcache as $forum)
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
	global $groupscache, $forumcache, $fpermcache;
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
					$fpermcache[$fid][$gid] = $groupscache[$gid];
				}
				foreach($fpermcache[$fid][$gid] as $perm => $access)
				{
					if($perm != "fid" && $perm != "gid" && $perm != "pid")
					{
						$permbit = $forumpermissions[$perm];
						if($access > $permbit)
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
// REDUNDANT CODE - TO BE REMOVED
//
function getpermissions($fid=0, $uid=0, $gid="", $parentslist="")
{
	die("USING OLD CODE - getpmerissions");
	global $db, $mybb, $mybbuser, $usercache, $permscache, $usergroups, $mybbgroup, $groupscache, $cache;
	if(!$groupscache)
	{
		$groupscache = $cache->read("usergroups");
	}
	$uid = (!$uid) ? $mybb->user['uid'] : $uid;
	if(!$gid)
	{
		if($uid == 0)
		{
			$gid = 1;
		}
		else
		{
			$usercache[$uid]=($uid==$mybb->user['uid'])?$mybbuser:$uid;
			$usercache[$uid]=($uid==$mybb->user['uid'])?$mybb:$uid;
			if(!$usercache[$uid])
			{
				$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='$uid'");
				$user = $db->fetch_array($query);
				$usercache[$user['uid']] = $user;
				$gid = $user['usergroup'];
			}
			$gid = ($usercache[$uid]) ? $usercache[$uid]['usergroup'] : $gid;
		}
	}
	if(!$permissioncache[$gid][$fid])
	{
		if(!$fid)
		{
			if(!$usergroups[$gid])
			{
				if($mybb->user['uid'] && $gid == $mybbuser['usergroup'])
				{
					$usergroups[$gid] = $mybbgroup;
				}
				else
				{
					$usergroups[$gid] = $groupscache[$gid];
				}
			}
			return $usergroups[$gid];
		}
		else
		{
			$parentslist=(!$parentslist)?getparentlist($fid):$parentslist;
			$sql = buildparentlist($fid, "fid", "OR", $parentslist);
			$query = $db->query("SELECT *, INSTR(',$parentslist,', CONCAT(',', fid, ',') ) AS useperm FROM ".TABLE_PREFIX."forumpermissions WHERE gid='$gid' AND $sql ORDER BY useperm LIMIT 1");
			$perms = $db->fetch_array($query);
			if(!$perms['pid'])
			{
				if(!$usergroups[$gid])
				{
					if($gid == $mybbuser['usergroup'])
					{
						$usergroups[$gid] = $mybbgroup;
					}
					else
					{
						//$perms=$db->fetch_array($db->query("SELECT * FROM usergroups WHERE gid='$gid'"));
						$usergroups[$gid] = $groupscache[$gid];	
					}
					$perms = $usergroups[$gid];

				}
				else
				{
					$perms = $usergroups[$gid];
				}
			}
		}
		$permscache[$gid][$fid] = $perms;
	}
	else
	{
	    return $permscache[$gid][$fid];
	}
	return $perms;
}

//
// REDUNDANT CODE - TO BE REMOVED
//
function getuserpermissions($uid="", $gid="")
{
	global $mybb, $mybbuser, $usergroups, $usercache, $db, $groupscache;
	if($uid == $mybb->user['uid'])
	{
		$gid = $mybb->user['usergroup'];
	}
	else
	{
		if($uid)
		{
			if($usercache[$uid])
			{
				$gid = $usercache[$uid]['usergroup'];
			}
			else
			{
				$user = $db->fetch_array($db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='$uid'"));
				$usercache[$user['uid']] = $user;
				$gid = $user['usergroup'];
			}
		}
	}
	return $groupscache[$gid];
}

//
// Check the password given on a certain forum for validity
//
function checkpwforum($fid, $password="")
{
	global $mybbuser, $pwverify, $toplinks, $header, $settings, $footer, $css, $headerinclude, $theme, $_SERVER, $breadcrumb, $forumpass, $templates, $lang;
	$showform = 1;
	if($password)
	{
		if($pwverify)
		{
			if($password == $pwverify)
			{
				mysetcookie("forumpass[$fid]", md5($mybbuser['uid'].$pwverify));
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
			if(!$forumpass[$fid] || ($forumpass[$fid] && md5($mybbuser['uid'].$password) != $forumpass[$fid]))
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
	$uid=(!$uid)?$mybb->user[$uid]:$uid;
	if(!$modpermscache[$uid][$fid])
	{
		$parentslist=(!$parentslist)?getparentlist($fid):$parentslist;
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
			if($ismod['mid'])
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

//
// Generate a list of post icons
//
function getposticons()
{
	global $db, $icon, $settings, $theme, $templates, $lang;
	$listed = 0;
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."icons ORDER BY name DESC");
	while($dbicon = $db->fetch_array($query))
	{
		if($icon == $dbicon['iid'])
		{
			$iconlist .= "<input type=\"radio\" name=\"icon\" value=\"".$dbicon['iid']."\" checked> <img src=\"".$dbicon['path']."\" alt=\"".$dbicon['name']."\">";
		}
		else
		{
			$iconlist .= "<input type=\"radio\" name=\"icon\" value=\"".$dbicon['iid']."\"> <img src=\"".$dbicon['path']."\" alt=\"".$dbicon['name']."\">";
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

//
// MyBB setcookie() wrapper
//
function mysetcookie($name, $value="", $expires="")
{
	global $settings, $mybbuser;
	if(!$settings['cookiepath'])
	{
		$settings['cookiepath'] = "/";
	}
	if($expires == -1)
	{
		$expires = 0;
	}
	else
	{
		if($mybbuser['rememberme'] == "no")
		{
			$expires = "";
		}
		else
		{
			$expires = time() + (60*60*24*365); // Make the cookie expire in a years time
		}
	}
	if($settings['cookiedomain'])
	{
		setcookie($name, $value, $expires, $settings['cookiepath'], $settings['cookiedomain']);
	}
	else
	{
		setcookie($name, $value, $expires, $settings['cookiepath']);
	}
}

function myunsetcookie($name)
{
	global $settings, $mybbuser;
	$expires = time()-3600;
	if(!$settings['cookiepath'])
	{
		$settings['cookiepath'] = "/";
	}

	if($settings['cookiedomain'])
	{
		@setcookie($name, "", $expires, $settings['cookiepath'], $settings['cookiedomain']);
	}
	else
	{
		@setcookie($name, "", $expires, $settings['cookiepath']);
	}
}

function mygetarraycookie($name, $id)
{
	// Many minutes were used to perfect this function
	// With the wonderful debugging help of Matt Light
	global $_COOKIE, $test;
	$my = $_COOKIE['mybb'];
	$cookie = unserialize($my[$name]);
	if($cookie[$id])
	{
		return $cookie[$id];
	}
	else
	{
		return 0;
	}
}

function mysetarraycookie($name, $id, $value) {
	global $_COOKIE;
	$my = $_COOKIE['mybb'];
	$newcookie = unserialize($my[$name]);
	$newcookie[$id] = $value;
	$newcookie = addslashes(serialize($newcookie));
	mysetcookie("mybb[$name]", $newcookie);
}


function serverload()
{
	global $lang;
	if(strtolower(substr(PHP_OS, 0, 3)) === 'win')
	{
		return $lang->unknown;
	}
	elseif(@file_exists("/proc/loadavg"))
	{
		$file = @fopen("/proc/loadavg", "r");
		$load = @fread($file, 6);
		@fclose($file);
		$serverload = explode(" ", $load);
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
		while(list($key, $val) = each($parentsexploded))
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
	$query = $db->query("SELECT COUNT(*) AS replies FROM ".TABLE_PREFIX."posts WHERE tid='$tid'");
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
	$query = $db->query("SELECT p.pid, p.uid, f.usepostcounts FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid) WHERE p.tid='$tid'");
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
	}
	if($usepostcounts != "no")
	{
		if(is_array($userposts))
		{
			while(list($uid, $subtract) = each($userposts))
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
	$query = $db->query("SELECT p.pid, p.uid, f.usepostcounts FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid) WHERE p.pid='$pid'");
	$post = $db->fetch_array($query);
	if($post['usepostcounts'] != "no")
	{
		$db->query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum-1 WHERE uid='".$post['uid']."'");
	}
	$db->query("DELETE FROM ".TABLE_PREFIX."posts WHERE pid='$pid'");
	$db->query("DELETE FROM ".TABLE_PREFIX."attachments WHERE pid='$pid'");
	$plugins->run_hooks("delete_post", $tid);
	$cache->updatestats();
}


function makeforumjump($pid="0", $selitem="", $addselect="1", $depth="", $showextras="1", $permissions="", $name="fid")
{
	global $db, $forumcache, $fjumpcache, $permissioncache, $settings, $mybb, $mybbuser, $selecteddone, $forumjump, $forumjumpbits, $gobutton, $theme, $templates, $lang, $mybbgroup;
	$pid = intval($pid);
	if($permissions)
	{
		$permissions = $mybbgroup;
	}
	if(!is_array($jumpfcache))
	{
		if(!is_array($forumcache))
		{
			cacheforums();
		}
		reset($forumcache);
		while(list($key, $val) = each($forumcache))
		{
			$jumpfcache[$val['pid']][$val['disporder']][$val['fid']] = $val;
		}
	}
	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}
	if(is_array($jumpfcache[$pid]))
	{
		while(list($key, $main) = each($jumpfcache[$pid]))
		{
			while(list($key, $forum) = each($main))
			{
				if($forum['fid'] != "0")
				{
					$perms=(!$permissioncache[$forum['fid']])?$permissions:$permissioncache[$forum['fid']];
					if(($perms['canview'] != "no" || $settings['hideprivateforums'] == "no") && $forum['showinjump'] != "no")
					{
						$optionselected = ($selitem==$forum['fid']) ? "selected=\"selected\"" : "";
						$selecteddone = ($selitem==$forum['fid']) ? 1:0;
						eval("\$forumjumpbits .= \"".$templates->get("forumjump_bit")."\";");
						if($forumcache[$forum['fid']])
						{
							$newdepth = $depth."--";
							$forumjumpbits .= makeforumjump($forum['fid'], $selitem, 0, $newdepth, $showextras, $perms);
						}
					}
				}
			}
		}
	}
	if($addselect)
	{
		if(!$selecteddone)
		{
			$selitem = (!$selitem) ? "default" : $selitem;
			$jumpsel[$selitem] = "selected";
		}
		$plate = ($showextras=="0") ? "special":"advanced";
		eval("\$forumjump = \"".$templates->get("forumjump_".$plate)."\";");
	}
	return $forumjump;
}

function checkattachment($attachment)
{

	global $db, $settings, $theme, $templates, $posthash, $pid, $tid, $forum, $mybbuser, $mybbgroup, $lang;
	die("USING OLD FUNCTION checkatachment");
}
function getextention($file)
{
	return strtolower(substr(strrchr($file, "."), 1));
}

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
	global $db, $settings, $theme, $templates, $lang;
	if($settings['bbcodeinserter'] != "off")
	{
		eval("\$codeinsert = \"".$templates->get("codebuttons")."\";");
	}
	return $codeinsert;
}
function makesmilieinsert()
{
	global $db, $smiliecache, $settings, $theme, $templates, $lang;
	if($settings['smilieinserter'] != "off" && $settings['smilieinsertercols'] && $settings['smilieinsertertot'])
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
			if($settings['smilieinsertertot'] >= $smiliecount)
			{
				$settings['smilieinsertertot'] = $smiliecount;
			}
			elseif ($settings['smilieinsertertot'] < $smiliecount)
			{
				$smiliecount = $settings['smilieinsertertot'];
				eval("\$getmore = \"".$templates->get("smilieinsert_getmore")."\";");
			}
			$smilies = "";
			$counter = 0;
			$i = 0;
			while(list($find, $image) = each($smiliecache))
			{
				if($i < $settings['smilieinsertertot'])
				{
					if($counter == 0)
					{
						$smilies .=  "<tr>";
					}
					$find = addslashes($find);
					$smilies .= "<td><a href=\"javascript:addsmilie('$find');\"><img src=\"$image\" border=\"0\"></a></td>";
					$i++;
					$counter++;
					if($counter == $settings['smilieinsertercols'])
					{
						$counter = 0;
						$smilies .= "</tr><tr>";
					}
				}
			}
			$colspan = $settings['smilieinsertercols'] - $counter;
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

function logmod($data, $action="")
{
	global $mybb, $mybbuser, $db, $ipaddress;
	$time = time();
	$db->query("INSERT INTO ".TABLE_PREFIX."moderatorlog (uid,dateline,fid,tid,action,ipaddress) VALUES ('".$mybb->user['uid']."','$time','".$data['fid']."','".$data['tid']."','$action','$ipaddress')");
}

function getreputation($reputation, $alt="")
{
	global $theme;
	if(strpos(" ".$reputation, "-"))
	{ // negative
		$img = "repbit_neg.gif";
		$reputation = str_replace("-", "", $reputation);
	}
	elseif($reputation == 0)
	{ // balanced
		$img = "repbit_bal.gif";
	}
	else
	{
		$img = "repbit_pos.gif"; // positive
	}
	$numimages = intval($reputation/10); // 10 points = 1 image
	if($numimages > 10)
	{
		$numimages = 10;
	}
	if(!$numimages)
	{
		$numimages = 1;
	}
	for($i=1;$i<=$numimages;$i++)
	{
		$rep .= "<img src=\"".$theme['imgdir']."/$img\" alt=\"$alt\" />";
	}
	return $rep;
}

function getip() {
	global $_SERVER;
	if($_SERVER['HTTP_X_FORWARDED_FOR'])
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
	if(!$ip)
	{
		if($_SERVER['HTTP_CLIENT_IP'])
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
		return "<img src=\"images/attachtypes/unkown.gif\" border=\"0\" alt=\".$ext File\" />";
	}
}

function getunviewableforums()
{
	global $db, $forumcache, $permissioncache, $settings, $mybb, $mybbuser, $unviewableforums, $unviewable, $templates, $mybbgroup, $forumpass;
	$pid = intval($pid);

	if(!$permissions)
	{
		$permissions = $mybbgroup;
	}
	if(!is_array($forumcache))
	{
		// Get Forums
		$query = $db->query("SELECT f.* FROM ".TABLE_PREFIX."forums f WHERE active!='no' ORDER BY f.pid, f.disporder");
		while($forum = $db->fetch_array($query))
		{
			$forumcache[$forum['fid']] = $forum;
		}
	}
	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}
	foreach($forumcache as $fid => $forum)
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
			if($forumpass[$forum['fid']] != md5($mybbuser['uid'].$forum['password']))
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
			if($navbits[$key+1])
			{
				if($navbits[$key+2]) { $sep = $navsep; } else { $sep = ""; }
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
	global $pforumcache, $db, $currentitem, $forumcache, $navbits, $lang, $archiveurl;
	if(!$pforumcache)
	{
		if(!is_array($forumcache))
		{
			cacheforums();
		}
		@reset($forumcache);
		while(list($key, $val) = @each($forumcache))
		{
			$pforumcache[$val['fid']][$val['pid']] = $val;
		}
	}
	if(is_array($pforumcache[$fid]))
	{
		while(list($key, $forumnav) = each($pforumcache[$fid]))
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
	if($settings['gzipoutput'] != "no")
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
	echo "<b>Cached templates:</b> ";
	$comma = "";
	while(list($key, $val) = each($templatecache))
	{
		echo "$comma$key";
		$comma = ", ";
	}
	exit;
}

function pageheaders() {
	global $settings;
	if($settings['nocacheheaders'] == "yes" && $settings['sendheaders'] != "yes")
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
	return implode(", ", $nicetime);
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
	$user['additionalgroups'] .= ",".$joingroup;
	$groupslist = "";
	$groups = explode(",", $user['additionalgroups']);
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
	$user['additionalgroups'] .= ",";
	$groups = explode(",", $user['additionalgroups']);
	if(is_array($groups))
	{
		foreach($groups as $gid)
		{
			if(trim($gid) != "" && $leavegroup != $gid && !$donegroup[$gid])
			{
				$groupslist .= $comma.$gid;
				$comma  = ",";
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
	if($_SERVER['REQUEST_URI'])
	{
		$location = $_SERVER['REQUEST_URI'];
	}
	elseif($ENV_['REQUEST_URI'])
	{
		$location = $ENV['REQUEST_URI'];
	}
	else
	{
		if($_SERVER['PATH_INFO'])
		{
			$location = $_SERVER['PATH_INFO'];
		}
		elseif($_ENV['PATH_INFO'])
		{
			$location = $_SERVER['PATH_INFO'];
		}
		elseif($_ENV['PHP_SELF'])
		{
			$location = $_ENV['PHP_SELF'];
		}
		else
		{
			$location = $_SERVER['PHP_SELF'];
		}
			if($_SERVER['QUERY_STRING'])
		{
			$location .= "?".$_SERVER['QUERY_STRING'];
		}
		elseif($_ENV['QUERY_STRING'])
		{
			$location = "?".$_ENV['QUERY_STRING'];
		}
	}
	
	if($_SERVER['REQUEST_METHOD'] == "POST" || $_ENV['REQUEST_METHOD'] == "POST")
	{
		if($_POST['action'])
		{
			$addloc[] = "action=".$_POST['action'];
		}
		if($_POST['fid'])
		{
			$addloc[] = "fid=".$_POST['fid'];
		}
		if($_POST['tid'])
		{
			$addloc[] = "tid=".$_POST['tid'];
		}
		if($_POST['pid'])
		{
			$addloc[] = "pid=".$_POST['pid'];
		}
		if($_POST['uid'])
		{
			$addloc[] ="uid=".$_POST['uid'];
		}
		if($_POST['eid'])
		{
			$addloc[] = "eid=".$_POST['eid'];
		}
		if(is_array($addloc))
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
	$message = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $message); // fix & but allow unicide
	$message = str_replace("<","&lt;",$message);
	$message = str_replace(">","&gt;",$message);
	$message = str_replace("\"","&quot;",$message);
	$message = str_replace("  ", "&nbsp;&nbsp;", $message);
	return $message;
}

function mynumberformat($number)
{
	global $mybb;
	if(is_int($number))
	{
		return number_format($number, 0, $mybb->settings['decpoint'], $mybb->settings['thousandssep']);
	}
	else
	{
		$parts = explode('.', $number);
		$decimals = strlen($parts[1]);
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


function win_years($bm, $bd, $by)
{
	$age = '';
	$nd = date('j');
	$nm = date('n');
	$ty = (date('Y') - $by);
	if($nm > $bm)
	{
		$age = $ty;
	}
	elseif($nm < $bm)
	{
		$age = ($ty - 1);
	}
	else
	{
		if($nd >= $bd)
		{
			$age = $ty;
		}
		else
		{
			$age = ($ty - 1);
		}
	}
	return($age);
}

function update_first_post($tid)
{
	global $db;
	$query = $db->query("SELECT pid FROM ".TABLE_PREFIX."posts WHERE tid='$tid' ORDER BY dateline ASC LIMIT 0,1");
	$post = $db->fetch_array($query);
	$firstpostup = array("firstpost" => $post['pid']);
	$db->update_query(TABLE_PREFIX."threads", $firstpostup, "tid='$tid'");
}
?>