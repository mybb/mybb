<?php

/**
 * Checks if a user with uid $uid exists in the database.
 *
 * @param int The uid to check for.
 * @return boolean True when exists, false when not.
 */
function user_exists($uid)
{
	global $db;
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".intval($uid)."' LIMIT 1");
	if($db->fetch_array($query))
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
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='".addslashes($username)."' LIMIT 1");
	if($db->fetch_array($query))
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
 * @param string The md5()'ed password.
 * @return boolean|array False when no match, array with user info when match.
 */
function validate_password_from_username($username, $password)
{
	global $db;
	$query = $db->query("SELECT uid,username,password,salt,loginkey FROM ".TABLE_PREFIX."users WHERE username='".addslashes($username)."' LIMIT 1");
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
 * @param string The md5()'ed password.
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
		$query = $db->query("SELECT uid,username,password,salt,loginkey FROM ".TABLE_PREFIX."users WHERE uid='".intval($uid)."' LIMIT 1");
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
		$db->update_query(TABLE_PREFIX."users", $sql_array, "uid = ".$user['uid'], 1);
	}

	if(!$user['loginkey'])
	{
		$user['loginkey'] = generate_loginkey();
		$sql_array = array(
			"loginkey" => $user['loginkey']
		);
		$db->update_query(TABLE_PREFIX."users", $sql_array, "uid = ".$user['uid'], 1);
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

	// 
	// If no salt was specified, check in database first, if still doesn't exist, create one
	//
	if(!$salt)
	{
		$query = $db->query("SELECT salt FROM ".TABLE_PREFIX."users WHERE uid='$uid' LIMIT 1");
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

	//
	// Create new password based on salt
	//
	$saltedpw = salt_password($password, $salt);

	//
	// Generate new login key
	//
	$loginkey = generate_loginkey();

	//
	// Update password and login key in database
	//
	$newpassword['password'] = $saltedpw;
	$newpassword['loginkey'] = $loginkey;
	$db->update_query(TABLE_PREFIX."users", $newpassword, "uid='$uid'", 1);

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
	$db->update_query(TABLE_PREFIX."users", $sql_array, "uid = ".$uid, 1);
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
	$db->update_query(TABLE_PREFIX."users", $sql_array, "uid = ".$uid, 1);
	return $loginkey;

}

/**
 * Adds a thread to a user's favorite thread list. 
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int The tid of the thread to add to the list.
 * @param int (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function add_favorite_thread($tid, $uid="")
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
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."favorites WHERE tid='".intval($tid)."' AND type='f' AND uid='".intval($uid)."' LIMIT 1");
	$favorite = $db->fetch_array($query);
	if(!$favorite['tid'])
	{
		$db->query("INSERT INTO ".TABLE_PREFIX."favorites (uid,tid,type) VALUES ('".intval($uid)."','".intval($tid)."','f')");
	}
	return true;
}

/**
 * Removes a thread from a user's favorite thread list. 
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int The tid of the thread to remove from the list.
 * @param int (Optional)The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function remove_favorite_thread($tid, $uid="")
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
	$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE tid='".intval($tid)."' AND type='f' AND uid='".intval($uid)."'");
	return true;
}

/**
 * Adds a thread to a user's thread subscription list. 
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int The tid of the thread to add to the list.
 * @param int (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function add_subscribed_thread($tid, $uid="")
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
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."favorites WHERE tid='".intval($tid)."' AND type='s' AND uid='".intval($uid)."' LIMIT 1");
	$favorite = $db->fetch_array($query);
	if(!$favorite['tid'])
	{
		$db->query("INSERT INTO ".TABLE_PREFIX."favorites (uid,tid,type) VALUES ('".intval($uid)."','".intval($tid)."','s')");
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
	$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE tid='".$tid."' AND type='s' AND uid='".$uid."'");
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
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forumsubscriptions WHERE fid='".$fid."' AND uid='".$uid."' LIMIT 1");
	$fsubscription = $db->fetch_array($query);
	if(!$fsubscription['fid'])
	{
		$db->query("INSERT INTO ".TABLE_PREFIX."forumsubscriptions (fid,uid) VALUES ('".$fid."','".$uid."')");
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
	$db->query("DELETE FROM ".TABLE_PREFIX."forumsubscriptions WHERE fid='".$fid."' AND uid='".$uid."'");
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

	//
	// Add the default items as plugins with separated priorities of 10
	//
	if($mybb->settings['enablepms'] != "no")
	{
		$plugins->add_hook("usercp_menu", "usercp_menu_messenger", 10);
	}
	$plugins->add_hook("usercp_menu", "usercp_menu_profile", 20);
	$plugins->add_hook("usercp_menu", "usercp_menu_misc", 30);

	//
	// Run the plugin hooks
	//
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
	global $db, $mybb, $templates, $theme, $usercpmenu, $lang;

	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	while(list($key, $folders) = each($foldersexploded))
	{
		$folderinfo = explode("**", $folders, 2);
		$folderlinks .= "<li class=\"pmfolders\"><a href=\"private.php?fid=$folderinfo[0]\">$folderinfo[1]</a></li>\n";
	}
	eval("\$usercpmenu .= \"".$templates->get("usercp_nav_messenger")."\";");
}

/**
 * Constructs the usercp profile menu.
 *
 */
function usercp_menu_profile()
{
	global $db, $mybb, $templates, $theme, $usercpmenu, $lang, $mybbuser;

	if($mybb->usergroup['canchangename'] != "no")
	{
		eval("\$changenameop = \"".$templates->get("usercp_nav_changename")."\";");
	}
	eval("\$usercpmenu .= \"".$templates->get("usercp_nav_profile")."\";");
}

/**
 * Constructs the usercp misc menu.
 *
 */
function usercp_menu_misc()
{
	global $db, $mybb, $templates, $theme, $usercpmenu, $lang, $mybbuser;

	$query = $db->query("SELECT COUNT(*) AS draftcount FROM ".TABLE_PREFIX."posts WHERE visible='-2' AND uid='".$mybb->user['uid']."'");
	$count = $db->fetch_array($query);
	$draftcount = "(".mynumberformat($count['draftcount']).")";
	if($count['draftcount'] > 0)
	{
		$draftstart = "<strong>";
		$draftend = "</strong>";
	}
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
		$query = $db->query("SELECT usertitle,postnum FROM ".TABLE_PREFIX."users WHERE uid='$uid' LIMIT 1");
		$user = $db->fetch_array($query);
	}
	if($user['usertitle'])
	{
		return $user['usertitle'];
	}
	else
	{
		$query = $db->query("SELECT title FROM ".TABLE_PREFIX."usertitles WHERE posts<='".$user['postnum']."' ORDER BY posts DESC");
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

function update_pm_count($uid=0, $count_to_update=7, $lastvisit=0)
{
	global $db, $mybb;
	static $pm_lastvisit_cache;

	// If no user id, assume that we mean the current logged in user.
	if(intval($uid) == 0)
	{
		$uid = $mybb->user['uid'];
	}

	// If using logged in user, use the last visit
	if($uid == $mybb->user['uid'])
	{
		$lastvisit = $mybb->user['lastvisit'];
	}
	// Else, if no last visit is specified, query for it.
	elseif(intval($lastvisit) < 1) 
	{
		if(!$pm_lastvisit_cache[$uid])
		{
			$query = $db->query("SELECT lastvisit FROM ".TABLE_PREFIX."users WHERE uid='".intval($uid)."'");
			$user = $db->fetch_array($query);
			$pm_lastvisit_cache[$uid] = $user['lastvisit'];
		}
		$lastvisit = $pm_lastvisit_cache[$uid];
	}
	// Update total number of messages.
	if($count_to_update & 1)
	{
		$query = $db->query("SELECT COUNT(pmid) AS pms_total FROM ".TABLE_PREFIX."privatemessages WHERE uid='".$uid."'");
		$total = $db->fetch_array($query);
		$pmcount['totalpms'] = $total['pms_total'];
	}
	// Update number of new messages.
	if($count_to_update & 2)
	{
		$query = $db->query("SELECT COUNT(pmid) AS pms_new FROM ".TABLE_PREFIX."privatemessages WHERE uid='".$uid."' AND dateline>'".$mybb->user['lastvisit']."' AND folder=1");
		$new = $db->fetch_array($query);
		$pmcount['newpms'] = $new['pms_new'];
	}
	// Update number of unread messages.
	if($count_to_update & 4)
	{
		$query = $db->query("SELECT COUNT(pmid) AS pms_unread FROM ".TABLE_PREFIX."privatemessages WHERE uid='".$uid."' AND status=0 AND folder='1'");
		$unread = $db->fetch_array($query);
		$pmcount['unreadpms'] = $unread['pms_unread'];
	}
	if(is_array($pmcount))
	{
		$db->update_query(TABLE_PREFIX."users", $pmcount, "uid='".intval($uid)."'");
	}
	return $pmcount;
}
?>