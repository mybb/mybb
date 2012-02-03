<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: functions_user.php 5734 2011-12-22 16:50:54Z ralgith $
 */

/**
 * Checks if a user with uid $uid exists in the database.
 *
 * @param int The uid to check for.
 * @return boolean True when exists, false when not.
 */
function user_exists($uid)
{
	global $db;
	
	$query = $db->simple_select("users", "COUNT(*) as user", "uid='".intval($uid)."'", array('limit' => 1));
	if($db->fetch_field($query, 'user') == 1)
	{
		return true;
	}
	else
	{
		return false;
	}
}

/**
 * Checks if $username already exists in the database.
 *
 * @param string The username for check for.
 * @return boolean True when exists, false when not.
 */
function username_exists($username)
{
	global $db;

	$username = $db->escape_string(my_strtolower($username));
	$query = $db->simple_select("users", "COUNT(*) as user", "LOWER(username)='".$username."' OR LOWER(email)='".$username."'", array('limit' => 1));

	if($db->fetch_field($query, 'user') == 1)
	{
		return true;
	}
	else
	{
		return false;
	}
}

/**
 * Checks a password with a supplied username.
 *
 * @param string The username of the user.
 * @param string The plain-text password.
 * @return boolean|array False when no match, array with user info when match.
 */
function validate_password_from_username($username, $password)
{
	global $db, $mybb;

	$username = $db->escape_string(my_strtolower($username));
	switch($mybb->settings['username_method'])
	{
		case 0:
			$query = $db->simple_select("users", "uid,username,password,salt,loginkey,coppauser,usergroup", "LOWER(username)='".$username."'", array('limit' => 1));
			break;
		case 1:
			$query = $db->simple_select("users", "uid,username,password,salt,loginkey,coppauser,usergroup", "LOWER(email)='".$username."'", array('limit' => 1));
			break;
		case 2:
			$query = $db->simple_select("users", "uid,username,password,salt,loginkey,coppauser,usergroup", "LOWER(username)='".$username."' OR LOWER(email)='".$username."'", array('limit' => 1));
			break;
		default:
			$query = $db->simple_select("users", "uid,username,password,salt,loginkey,coppauser,usergroup", "LOWER(username)='".$username."'", array('limit' => 1));
			break;
	}

	$user = $db->fetch_array($query);
	if(!$user['uid'])
	{
		return false;
	}
	else
	{
		return validate_password_from_uid($user['uid'], $password, $user);
	}
}

/**
 * Checks a password with a supplied uid.
 *
 * @param int The user id.
 * @param string The plain-text password.
 * @param string An optional user data array.
 * @return boolean|array False when not valid, user data array when valid.
 */
function validate_password_from_uid($uid, $password, $user = array())
{
	global $db, $mybb;
	if($mybb->user['uid'] == $uid)
	{
		$user = $mybb->user;
	}
	if(!$user['password'])
	{
		$query = $db->simple_select("users", "uid,username,password,salt,loginkey,usergroup", "uid='".intval($uid)."'", array('limit' => 1));
		$user = $db->fetch_array($query);
	}
	if(!$user['salt'])
	{
		// Generate a salt for this user and assume the password stored in db is a plain md5 password
		$user['salt'] = generate_salt();
		$user['password'] = salt_password($user['password'], $user['salt']);
		$sql_array = array(
			"salt" => $user['salt'],
			"password" => $user['password']
		);
		$db->update_query("users", $sql_array, "uid='".$user['uid']."'", 1);
	}

	if(!$user['loginkey'])
	{
		$user['loginkey'] = generate_loginkey();
		$sql_array = array(
			"loginkey" => $user['loginkey']
		);
		$db->update_query("users", $sql_array, "uid = ".$user['uid'], 1);
	}
	if(salt_password(md5($password), $user['salt']) == $user['password'])
	{
		return $user;
	}
	else
	{
		return false;
	}
}

/**
 * Updates a user's password.
 *
 * @param int The user's id.
 * @param string The md5()'ed password.
 * @param string (Optional) The salt of the user.
 * @return array The new password.
 */
function update_password($uid, $password, $salt="")
{
	global $db, $plugins;

	$newpassword = array();

	// If no salt was specified, check in database first, if still doesn't exist, create one
	if(!$salt)
	{
		$query = $db->simple_select("users", "salt", "uid='$uid'", array('limit' => 1));
		$user = $db->fetch_array($query);
		if($user['salt'])
		{
			$salt = $user['salt'];
		}
		else
		{
			$salt = generate_salt();
		}
		$newpassword['salt'] = $salt;
	}

	// Create new password based on salt
	$saltedpw = salt_password($password, $salt);

	// Generate new login key
	$loginkey = generate_loginkey();

	// Update password and login key in database
	$newpassword['password'] = $saltedpw;
	$newpassword['loginkey'] = $loginkey;
	$db->update_query("users", $newpassword, "uid='$uid'", 1);

	$plugins->run_hooks("password_changed");

	return $newpassword;
}

/**
 * Salts a password based on a supplied salt.
 *
 * @param string The md5()'ed password.
 * @param string The salt.
 * @return string The password hash.
 */
function salt_password($password, $salt)
{
	return md5(md5($salt).$password);
}

/**
 * Generates a random salt
 *
 * @return string The salt.
 */
function generate_salt()
{
	return random_str(8);
}

/**
 * Generates a 50 character random login key.
 *
 * @return string The login key.
 */
function generate_loginkey()
{
	return random_str(50);
}

/**
 * Updates a user's salt in the database (does not update a password).
 *
 * @param int The uid of the user to update.
 * @return string The new salt.
 */
function update_salt($uid)
{
	global $db;
	
	$salt = generate_salt();
	$sql_array = array(
		"salt" => $salt
	);
	$db->update_query("users", $sql_array, "uid='{$uid}'", 1);
	
	return $salt;
}

/**
 * Generates a new login key for a user.
 *
 * @param int The uid of the user to update.
 * @return string The new login key.
 */
function update_loginkey($uid)
{
	global $db;
	
	$loginkey = generate_loginkey();
	$sql_array = array(
		"loginkey" => $loginkey
	);
	$db->update_query("users", $sql_array, "uid='{$uid}'", 1);
	
	return $loginkey;

}

/**
 * Adds a thread to a user's thread subscription list.
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int The tid of the thread to add to the list.
 * @param int (Optional) The type of notification to receive for replies (0=none, 1=instant)
 * @param int (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function add_subscribed_thread($tid, $notification=1, $uid="")
{
	global $mybb, $db;
	
	if(!$uid)
	{
		$uid = $mybb->user['uid'];
	}
	
	if(!$uid)
	{
		return;
	}
	
	$query = $db->simple_select("threadsubscriptions", "*", "tid='".intval($tid)."' AND uid='".intval($uid)."'", array('limit' => 1));
	$subscription = $db->fetch_array($query);
	if(!$subscription['tid'])
	{
		$insert_array = array(
			'uid' => intval($uid),
			'tid' => intval($tid),
			'notification' => intval($notification),
			'dateline' => TIME_NOW,
			'subscriptionkey' => md5(TIME_NOW.$uid.$tid)

		);
		$db->insert_query("threadsubscriptions", $insert_array);
	}
	else
	{
		// Subscription exists - simply update notification
		$update_array = array(
			"notification" => intval($notification)
		);
		$db->update_query("threadsubscriptions", $update_array, "uid='{$uid}' AND tid='{$tid}'");
	}
	return true;
}

/**
 * Remove a thread from a user's thread subscription list.
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int The tid of the thread to remove from the list.
 * @param int (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function remove_subscribed_thread($tid, $uid="")
{
	global $mybb, $db;
	
	if(!$uid)
	{
		$uid = $mybb->user['uid'];
	}
	
	if(!$uid)
	{
		return;
	}
	$db->delete_query("threadsubscriptions", "tid='".$tid."' AND uid='{$uid}'");
	
	return true;
}

/**
 * Adds a forum to a user's forum subscription list.
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int The fid of the forum to add to the list.
 * @param int (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function add_subscribed_forum($fid, $uid="")
{
	global $mybb, $db;
	
	if(!$uid)
	{
		$uid = $mybb->user['uid'];
	}
	
	if(!$uid)
	{
		return;
	}
	
	$fid = intval($fid);
	$uid = intval($uid);
	
	$query = $db->simple_select("forumsubscriptions", "*", "fid='".$fid."' AND uid='{$uid}'", array('limit' => 1));
	$fsubscription = $db->fetch_array($query);
	if(!$fsubscription['fid'])
	{
		$insert_array = array(
			'fid' => $fid,
			'uid' => $uid
		);
		$db->insert_query("forumsubscriptions", $insert_array);
	}
	
	return true;
}

/**
 * Removes a forum from a user's forum subscription list.
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int The fid of the forum to remove from the list.
 * @param int (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function remove_subscribed_forum($fid, $uid="")
{
	global $mybb, $db;
	
	if(!$uid)
	{
		$uid = $mybb->user['uid'];
	}
	
	if(!$uid)
	{
		return;
	}
	$db->delete_query("forumsubscriptions", "fid='".$fid."' AND uid='{$uid}'");
	
	return true;
}

/**
 * Constructs the usercp navigation menu.
 *
 */
function usercp_menu()
{
	global $mybb, $templates, $theme, $plugins, $lang, $usercpnav, $usercpmenu;

	$lang->load("usercpnav");

	// Add the default items as plugins with separated priorities of 10
	if($mybb->settings['enablepms'] != 0)
	{
		$plugins->add_hook("usercp_menu", "usercp_menu_messenger", 10);
	}
	
	$plugins->add_hook("usercp_menu", "usercp_menu_profile", 20);
	$plugins->add_hook("usercp_menu", "usercp_menu_misc", 30);

	// Run the plugin hooks
	$plugins->run_hooks("usercp_menu");
	global $usercpmenu;

	eval("\$usercpnav = \"".$templates->get("usercp_nav")."\";");

	$plugins->run_hooks("usercp_menu_built");
}

/**
 * Constructs the usercp messenger menu.
 *
 */
function usercp_menu_messenger()
{
	global $db, $mybb, $templates, $theme, $usercpmenu, $lang, $collapsed, $collapsedimg;

	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
		if($folderinfo[0] == 4)
		{
			$class = "usercp_nav_trash_pmfolder";
		}
		else if($folderlinks)
		{
			$class = "usercp_nav_sub_pmfolder";
		}
		else
		{
			$class = "usercp_nav_pmfolder";
		}

		$folderlinks .= "<div><a href=\"private.php?fid=$folderinfo[0]\" class=\"usercp_nav_item {$class}\">$folderinfo[1]</a></div>\n";
	}
	
	eval("\$usercpmenu .= \"".$templates->get("usercp_nav_messenger")."\";");
}

/**
 * Constructs the usercp profile menu.
 *
 */
function usercp_menu_profile()
{
	global $db, $mybb, $templates, $theme, $usercpmenu, $lang, $collapsed, $collapsedimg;

	if($mybb->usergroup['canchangename'] != 0)
	{
		eval("\$changenameop = \"".$templates->get("usercp_nav_changename")."\";");
	}

	if($mybb->usergroup['canusesig'] == 1 && ($mybb->usergroup['canusesigxposts'] == 0 || $mybb->usergroup['canusesigxposts'] > 0 && $mybb->user['postnum'] > $mybb->usergroup['canusesigxposts']))
	{
		if($mybb->user['suspendsignature'] == 0 || $mybb->user['suspendsignature'] == 1 && $mybb->user['suspendsigtime'] > 0 && $mybb->user['suspendsigtime'] < TIME_NOW)
		{
			eval("\$changesigop = \"".$templates->get("usercp_nav_editsignature")."\";");
		}
	}

	eval("\$usercpmenu .= \"".$templates->get("usercp_nav_profile")."\";");
}

/**
 * Constructs the usercp misc menu.
 *
 */
function usercp_menu_misc()
{
	global $db, $mybb, $templates, $theme, $usercpmenu, $lang, $collapsed, $collapsedimg;

	$query = $db->simple_select("posts", "COUNT(*) AS draftcount", "visible='-2' AND uid='".$mybb->user['uid']."'");
	$count = $db->fetch_array($query);	

	if($count['draftcount'] > 0)
	{
		$draftstart = "<strong>";
		$draftend = "</strong>";
		$draftcount = "(".my_number_format($count['draftcount']).")";
	}

	$profile_link = get_profile_link($mybb->user['uid']);
	eval("\$usercpmenu .= \"".$templates->get("usercp_nav_misc")."\";");
}

/**
 * Gets the usertitle for a specific uid.
 *
 * @param int The uid of the user to get the usertitle of.
 * @return string The usertitle of the user.
 */
function get_usertitle($uid="")
{
	global $db, $mybb;
	
	if($mybb->user['uid'] == $uid)
	{
		$user = $mybb->user;
	}
	else
	{
		$query = $db->simple_select("users", "usertitle,postnum", "uid='$uid'", array('limit' => 1));
		$user = $db->fetch_array($query);
	}
	
	if($user['usertitle'])
	{
		return $user['usertitle'];
	}
	else
	{
		$query = $db->simple_select("usertitles", "title", "posts<='".$user['postnum']."'", array('order_by' => 'posts', 'order_dir' => 'desc'));
		$usertitle = $db->fetch_array($query);
		
		return $usertitle['title'];
	}
}

/**
 * Updates a users private message count in the users table with the number of pms they have.
 *
 * @param int The user id to update the count for. If none, assumes currently logged in user.
 * @param int Bitwise value for what to update. 1 = total, 2 = new, 4 = unread. Combinations accepted.
 * @param int The unix timestamp the user with uid last visited. If not specified, will be queried.
 */
function update_pm_count($uid=0, $count_to_update=7)
{
	global $db, $mybb;
	static $pm_lastvisit_cache;

	// If no user id, assume that we mean the current logged in user.
	if(intval($uid) == 0)
	{
		$uid = $mybb->user['uid'];
	}

	// Update total number of messages.
	if($count_to_update & 1)
	{
		$query = $db->simple_select("privatemessages", "COUNT(pmid) AS pms_total", "uid='".$uid."'");
		$total = $db->fetch_array($query);
		$pmcount['totalpms'] = $total['pms_total'];
	}
	
	// Update number of unread messages.
	if($count_to_update & 2 && $db->field_exists("unreadpms", "users") == true)
	{
		$query = $db->simple_select("privatemessages", "COUNT(pmid) AS pms_unread", "uid='".$uid."' AND status='0' AND folder='1'");
		$unread = $db->fetch_array($query);
		$pmcount['unreadpms'] = $unread['pms_unread'];
	}
	
	if(is_array($pmcount))
	{
		$db->update_query("users", $pmcount, "uid='".intval($uid)."'");
	}
	return $pmcount;
}

/**
 * Return the language specific name for a PM folder.
 *
 * @param int The ID of the folder.
 * @param string The folder name - can be blank, will use language default.
 * @return string The name of the folder.
 */
function get_pm_folder_name($fid, $name="")
{
	global $lang;

	if($name != '')
	{
		return $name;
	}

	switch($fid)
	{
		case 1;
			return $lang->folder_inbox;
			break;
		case 2:
			return $lang->folder_sent_items;
			break;
		case 3:
			return $lang->folder_drafts;
			break;
		case 4:
			return $lang->folder_trash;
			break;
		default:
			return $lang->folder_untitled;
	}
}
?>