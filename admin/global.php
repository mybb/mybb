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

// Lets pretend we're a level higher
define("IN_ADMINCP", 1);

// Here you can change how much of an Admin CP IP address must match in a previous session for the user is validated (defaults to 3 which matches a.b.c)
define("ADMIN_IP_SEGMENTS", 3);

if(!isset($config['admin_dir']))
{
	$config['admin_dir'] = "admin";
}

require dirname(dirname(__FILE__))."/inc/init.php";

define('MYBB_ADMIN_DIR', MYBB_ROOT.$config['admin_dir'].'/');

require MYBB_ADMIN_DIR."adminfunctions.php";
require MYBB_ROOT."inc/functions_user.php";

$style = "styles/".$mybb->settings['cpstyle']."/stylesheet.css";
if(!file_exists(MYBB_ADMIN_DIR.$style))
{
	$style = "./styles/Axiom/stylesheet.css";
}

if($mybb->user['language'])
{
	$lang->set_language($mybb->user['language'], "admin");
}
else
{
	$lang->set_language($settings['cplanguage'], "admin");
}

// Load global language phrases
$lang->load("global");

// Remove slashes from bbname
$mybb->settings['bbname'] = stripslashes($mybb->settings['bbname']);

$time = time();

if(is_dir(MYBB_ROOT."install") && !file_exists(MYBB_ROOT."install/lock"))
{
	$mybb->trigger_generic_error("install_directory");
}

$plugins->run_hooks("admin_global_start");

$showlogin = 1;
$ipaddress = get_ip();

unset($user);
if($mybb->input['do'] == "login")
{
	$user = validate_password_from_username($mybb->input['username'], $mybb->input['password']);
	if($user['uid'])
	{
		$query = $db->simple_select(TABLE_PREFIX."users", "*", "uid='".$user['uid']."'");
		$user = $db->fetch_array($query);
	}
	$failcheck = 1;
	
	if($user['uid'])
	{
		// Create a new admin session for this user
		$admin_session = array(
			"sid" => md5(uniqid(microtime())),
			"uid" => $user['uid'],
			"loginkey" => $user['loginkey'],
			"ip" => $db->escape_string(get_ip()),
			"dateline" => time(),
			"lastactive" => time()
		);
		$db->insert_query(TABLE_PREFIX."adminsessions", $admin_session);
	}
}
else if($mybb->input['action'] == "logout")
{
	$lang->invalid_admin = $lang->logged_out_admin;
	// Delete session from the database
	$db->delete_query(TABLE_PREFIX."adminsessions", "sid='".$db->escape_string($mybb->input['adminsid'])."'");
}
else
{
	// No admin session - show message on the login screen
	if(!$mybb->input['adminsid'])
	{
		$lang->invalid_admin = $lang->no_admin_session;
	}
	// Otherwise, check admin session
	else
	{
		$query = $db->simple_select(TABLE_PREFIX."adminsessions", "*", "sid='".$db->escape_string($mybb->input['adminsid'])."'");
		$admin_session = $db->fetch_array($query);
		
		// No matching admin session found - show message on login screen
		if(!$admin_session['sid'])
		{
			$lang->invalid_admin = $lang->invalid_admin_session;
		}
		else
		{
			// Fetch the user from the admin session
			$query = $db->simple_select(TABLE_PREFIX."users", "*", "uid='{$admin_session['uid']}'");
			$user = $db->fetch_array($query);

			// Login key has changed - force logout
			if(!$user['uid'] && $user['loginkey'] != $admin_session['loginkey'])
			{
				unset($user);
			}
			else
			{
				// Admin CP sessions 2 hours old are expired
				if($admin_session['lastactive'] < time()-7200)
				{
					$lang->invalid_admin = $lang->admin_session_expired;
					$db->delete_query(TABLE_PREFIX."adminsessions", "sid='".$db->escape_string($mybb->input['adminsid'])."'");
					unset($user);
				}
				// If IP matching is set - check IP address against the session IP
				else if(ADMIN_IP_SEGMENTS > 0)
				{
					$exploded_ip = explode(".", $ipaddress);
					$exploded_admin_ip = explode(".", $admin_session['ip']);
					$matches = 0;
					$valid_ip = false;
					for($i = 0; $i < ADMIN_IP_SEGMENTS; $i++)
					{
						if($exploded_ip[$i] == $exploded_admin_ip[$i])
						{
							++$matches;
						}
						if($matches == ADMIN_IP_SEGMENTS)
						{
							$valid_ip = true;
							break;
						}
					}
					// IP doesn't match properly - show message on logon screen
					if(!$valid_ip)
					{
						$lang->invalid_admin = $lang->invalid_admin_ip;
						unset($user);
					}	
				}
			}
		}
	}
}

$mybbgroups = $user['usergroup'].",".$user['additionalgroups'];

if(!$user['usergroup'])
{
	$mybbgroups = 1;
}

$groupscache = $cache->read("usergroups");
$admingroup = usergroup_permissions($mybbgroups);

if($admingroup['cancp'] != "yes" || !$user['uid'])
{
	unset($user);
}

if($user['uid'])
{
	$mybbadmin = $mybb->user = $user;
	$query = $db->simple_select(TABLE_PREFIX."usergroups", "*", "gid='{$user['usergroup']}'");
	$mybb->usergroup = $db->fetch_array($query);
	$query = $db->simple_select(TABLE_PREFIX."adminoptions", "*", "uid='{$user['uid']}'");
	$adminoptions = $db->fetch_array($query);
	if($adminoptions['cpstyle'] && file_exists(MYBB_ADMIN_DIR."styles/{$adminoptions['cpstyle']}/stylesheet.css"))
	{
		$style = "./styles/{$adminoptions['cpstyle']}/stylesheet.css";
	}
	
	// Update the session information in the DB
	if($admin_session['sid'])
	{
		$updated_session = array(
			"lastactive" => time(),
			"ip" => $ipaddress
		);
		$db->update_query(TABLE_PREFIX."adminsessions", $updated_session, "sid='".$db->escape_string($mybb->input['adminsid'])."'");
	}
	define("SID", "adminsid={$admin_session['sid']}");
}
else
{
	if($failcheck)
	{
		$md5pw = md5($mybb->input['password']);
		$ipaddress = get_ip();
		$iphost = @gethostbyaddr($ipaddress);
		$lang->invalidlogin_message = sprintf($lang->invalidlogin_message, $mybb->settings['bbname'], $mybb->input['username'], $mybb->input['password'], $md5pw, $ipaddress, $iphost);
		$lang->invalidlogin_subject = sprintf($lang->invalidlogin_subject, $mybb->settings['bbname']);
		mymail($settings['adminemail'], $lang->invalidlogin_subject, $lang->invalidlogin_message);
		$plugins->run_hooks("admin_global_invalid_login");
	}

	if(!empty($mybb->input['goto']))
	{
		$goto = htmlspecialchars_uni($mybb->input['goto']);
	}
	elseif(strpos($_SERVER['PHP_SELF'], 'index.php') === false)
	{
		$goto = htmlspecialchars_uni($_SERVER['PHP_SELF']);
		if(!empty($_SERVER['QUERY_STRING']))
		{
			$goto .= '?'.$_SERVER['QUERY_STRING'];
		}
	}
	else
	{
		$goto = '';
	}
	$plugins->run_hooks("admin_global_login");
	cpheader('', 0, 'javascript:document.loginform.username.focus();');
	echo "<br />\n<br />\n<br />";
	echo "<form action=\"index.php\" method=\"post\" name=\"loginform\" target=\"_top\">\n";
	echo "<table cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"450\" align=\"center\">\n";
	echo "<tr><td class=\"bordercolor\">\n";
	echo "<table border=\"0\" cellspacing=\"1\" cellpadding=\"4\" width=\"100%\">\n";
	echo "<tr>\n";
	echo "<td id=\"logo\"><h1><span class=\"hidden\">MyBB</span></h1></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"header\" align=\"center\">".$lang->administration_login."</td>\n";
	echo "</tr>";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" align=\"center\">".$lang->invalid_admin."</td>\n";
	echo "</tr>";
	echo "<tr>\n";
	echo "<td class=\"altbg2\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n";
	echo "<td><b>".$lang->login_username."</b></td>\n";
	echo "<td><input type=\"text\" name=\"username\" /></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td><b>".$lang->login_password."</b></td>\n";
	echo "<td><input type=\"password\" name=\"password\" /></td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "</td>";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg2\" align=\"center\"><input type=\"submit\" value=\"".$lang->login."\" />&nbsp;&nbsp;&nbsp;<input type=\"reset\" value=\"".$lang->reset."\" /></td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "</td></tr></table>\n";
	echo "<input type=\"hidden\" name=\"do\" value=\"login\" />\n";
	echo "<input type=\"hidden\" name=\"goto\" value=\"".$goto."\" />\n";
	echo "</form>\n";
	echo "<p style=\"text-align: center\"><a href=\"../index.php\">".$lang->back_to_forum."</a></p>\n";
	cpfooter(0);
	exit;
}
$navbits[0]['name'] = $mybb->settings['bbname']." ".$lang->control_panel;
$navbits[0]['url'] = "index.php?".SID."&amp;action=home";

if($rand == 2 || $rand == 5)
{
	$stamp = time()-604800;
	$db->delete_query(TABLE_PREFIX."adminsessions", "lastactive<'$stamp'");
}

$plugins->run_hooks("admin_global_end");
?>
