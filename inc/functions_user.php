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
 * Checks if a user with uid $uid exists in the database.
 *
 * @param int $uid The uid to check for.
 * @return boolean True when exists, false when not.
 */
function user_exists($uid)
{
	global $db;

	$query = $db->simple_select("users", "COUNT(*) as user", "uid='".(int)$uid."'", array('limit' => 1));
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
 * @param string $username The username for check for.
 * @return boolean True when exists, false when not.
 */
function username_exists($username)
{
	$options = array(
		'username_method' => 2
	);

	return (bool)get_user_by_username($username, $options);
}

/**
 * Checks a password with a supplied username.
 *
 * @param string $username The username of the user.
 * @param string $password The plain-text password.
 * @return boolean|array False when no match, array with user info when match.
 */
function validate_password_from_username($username, $password)
{
	global $mybb;

	$options = array(
		'fields' => '*',
		'username_method' => $mybb->settings['username_method'],
	);

	$user = get_user_by_username($username, $options);

	if(!$user['uid'])
	{
		return false;
	}

	return validate_password_from_uid($user['uid'], $password, $user);
}

/**
 * Checks a password with a supplied uid.
 *
 * @param int $uid The user id.
 * @param string $password The plain-text password.
 * @param array $user An optional user data array.
 * @return boolean|array False when not valid, user data array when valid.
 */
function validate_password_from_uid($uid, $password, $user = array())
{
	global $db, $mybb;
	if(isset($mybb->user['uid']) && $mybb->user['uid'] == $uid)
	{
		$user = $mybb->user;
	}
	if(!$user['password'])
	{
		$user = get_user($uid);
	}

	if(!$user['loginkey'])
	{
		$user['loginkey'] = generate_loginkey();
		$sql_array = array(
			"loginkey" => $user['loginkey']
		);
		$db->update_query("users", $sql_array, "uid = ".$user['uid']);
	}
	if(verify_user_password($user, $password))
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
 * @param int $uid The user's id.
 * @param string $password The md5()'ed password.
 * @param string $salt (Optional) The salt of the user.
 * @return array The new password.
 * @deprecated deprecated since version 1.8.6 Please use other alternatives.
 */
function update_password($uid, $password, $salt="")
{
	global $db, $plugins;

	$newpassword = array();

	// If no salt was specified, check in database first, if still doesn't exist, create one
	if(!$salt)
	{
		$query = $db->simple_select("users", "salt", "uid='$uid'");
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
	$db->update_query("users", $newpassword, "uid='$uid'");

	$plugins->run_hooks("password_changed");

	return $newpassword;
}

/**
 * Salts a password based on a supplied salt.
 *
 * @param string $password The md5()'ed password.
 * @param string $salt The salt.
 * @return string The password hash.
 * @deprecated deprecated since version 1.8.9 Please use other alternatives.
 */
function salt_password($password, $salt)
{
	return md5(md5($salt).$password);
}

/**
 * Salts a password based on a supplied salt.
 *
 * @param string $password The input password.
 * @param string $salt (Optional) The salt used by the MyBB algorithm.
 * @param string $user (Optional) An array containing password-related data.
 * @return array Password-related fields.
 */
function create_password($password, $salt = false, $user = false)
{
	global $plugins;

	$fields = null;

	$parameters = compact('password', 'salt', 'user', 'fields');

	if(!defined('IN_INSTALL') && !defined('IN_UPGRADE'))
	{
		$plugins->run_hooks('create_password', $parameters);
	}

	if(!is_null($parameters['fields']))
	{
		$fields = $parameters['fields'];
	}
	else
	{
		if(!$salt)
		{
			$salt = generate_salt();
		}

		$hash = md5(md5($salt).md5($password));

		$fields = array(
			'salt' => $salt,
			'password' => $hash,
		);
	}

	return $fields;
}

/**
 * Compares user's password data against provided input.
 *
 * @param array $user An array containing password-related data.
 * @param string $password The plain-text input password.
 * @return bool Result of the comparison.
 */
function verify_user_password($user, $password)
{
	global $plugins;

	$result = null;

	$parameters = compact('user', 'password', 'result');

	if(!defined('IN_INSTALL') && !defined('IN_UPGRADE'))
	{
		$plugins->run_hooks('verify_user_password', $parameters);
	}

	if(!is_null($parameters['result']))
	{
		return $parameters['result'];
	}
	else
	{
		$password_fields = create_password($password, $user['salt'], $user);

		return my_hash_equals($user['password'], $password_fields['password']);
	}
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
 * @param int $uid The uid of the user to update.
 * @return string The new salt.
 */
function update_salt($uid)
{
	global $db;

	$salt = generate_salt();
	$sql_array = array(
		"salt" => $salt
	);
	$db->update_query("users", $sql_array, "uid='{$uid}'");

	return $salt;
}

/**
 * Generates a new login key for a user.
 *
 * @param int $uid The uid of the user to update.
 * @return string The new login key.
 */
function update_loginkey($uid)
{
	global $db;

	$loginkey = generate_loginkey();
	$sql_array = array(
		"loginkey" => $loginkey
	);
	$db->update_query("users", $sql_array, "uid='{$uid}'");

	return $loginkey;

}

/**
 * Adds a thread to a user's thread subscription list.
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int $tid The tid of the thread to add to the list.
 * @param int $notification (Optional) The type of notification to receive for replies (0=none, 1=email, 2=pm)
 * @param int $uid (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function add_subscribed_thread($tid, $notification=1, $uid=0)
{
	global $mybb, $db;

	if(!$uid)
	{
		$uid = $mybb->user['uid'];
	}

	if(!$uid)
	{
		return false;
	}

	$query = $db->simple_select("threadsubscriptions", "*", "tid='".(int)$tid."' AND uid='".(int)$uid."'");
	$subscription = $db->fetch_array($query);
	if(empty($subscription) || !$subscription['tid'])
	{
		$insert_array = array(
			'uid' => (int)$uid,
			'tid' => (int)$tid,
			'notification' => (int)$notification,
			'dateline' => TIME_NOW
		);
		$db->insert_query("threadsubscriptions", $insert_array);
	}
	else
	{
		// Subscription exists - simply update notification
		$update_array = array(
			"notification" => (int)$notification
		);
		$db->update_query("threadsubscriptions", $update_array, "uid='{$uid}' AND tid='{$tid}'");
	}
	return true;
}

/**
 * Remove a thread from a user's thread subscription list.
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int $tid The tid of the thread to remove from the list.
 * @param int $uid (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function remove_subscribed_thread($tid, $uid=0)
{
	global $mybb, $db;

	if(!$uid)
	{
		$uid = $mybb->user['uid'];
	}

	if(!$uid)
	{
		return false;
	}
	$db->delete_query("threadsubscriptions", "tid='".$tid."' AND uid='{$uid}'");

	return true;
}

/**
 * Adds a forum to a user's forum subscription list.
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int $fid The fid of the forum to add to the list.
 * @param int $uid (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function add_subscribed_forum($fid, $uid=0)
{
	global $mybb, $db;

	if(!$uid)
	{
		$uid = $mybb->user['uid'];
	}

	if(!$uid)
	{
		return false;
	}

	$fid = (int)$fid;
	$uid = (int)$uid;

	$query = $db->simple_select("forumsubscriptions", "*", "fid='".$fid."' AND uid='{$uid}'", array('limit' => 1));
	$fsubscription = $db->fetch_array($query);
	if(empty($fsubscription) || !$fsubscription['fid'])
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
 * @param int $fid The fid of the forum to remove from the list.
 * @param int $uid (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function remove_subscribed_forum($fid, $uid=0)
{
	global $mybb, $db;

	if(!$uid)
	{
		$uid = $mybb->user['uid'];
	}

	if(!$uid)
	{
		return false;
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
	if($mybb->settings['enablepms'] != 0 && $mybb->usergroup['canusepms'] == 1)
	{
		$plugins->add_hook("usercp_menu", "usercp_menu_messenger", 10);
	}

	if($mybb->usergroup['canusercp'] == 1)
	{
		$plugins->add_hook("usercp_menu", "usercp_menu_profile", 20);
		$plugins->add_hook("usercp_menu", "usercp_menu_misc", 30);
	}

	// Run the plugin hooks
	$plugins->run_hooks("usercp_menu");
	global $usercpmenu;

	if($mybb->usergroup['canusercp'] == 1)
	{
		eval("\$ucp_nav_home = \"".$templates->get("usercp_nav_home")."\";");
	}

	eval("\$usercpnav = \"".$templates->get("usercp_nav")."\";");

	$plugins->run_hooks("usercp_menu_built");
}

/**
 * Constructs the usercp messenger menu.
 *
 */
function usercp_menu_messenger()
{
	global $db, $mybb, $templates, $theme, $usercpmenu, $lang, $collapse, $collapsed, $collapsedimg;

	$expaltext = (in_array("usercppms", $collapse)) ? $lang->expcol_expand : $lang->expcol_collapse;
	$usercp_nav_messenger = $templates->get("usercp_nav_messenger");
	// Hide tracking link if no permission
	$tracking = '';
	if($mybb->usergroup['cantrackpms'])
	{
		$tracking = $templates->get("usercp_nav_messenger_tracking");
	}
	eval("\$ucp_nav_tracking = \"". $tracking ."\";");

	// Hide compose link if no permission
	$ucp_nav_compose = '';
	if($mybb->usergroup['cansendpms'] == 1)
	{
		eval("\$ucp_nav_compose = \"".$templates->get("usercp_nav_messenger_compose")."\";");
	}

	$folderlinks = $folder_id = $folder_name = '';
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

		$folder_id = $folderinfo[0];
		$folder_name = $folderinfo[1];

		eval("\$folderlinks .= \"".$templates->get("usercp_nav_messenger_folder")."\";");
	}

	if(!isset($collapsedimg['usercppms']))
	{
		$collapsedimg['usercppms'] = '';
	}

	if(!isset($collapsed['usercppms_e']))
	{
		$collapsed['usercppms_e'] = '';
	}

	eval("\$usercpmenu .= \"".$usercp_nav_messenger."\";");
}

/**
 * Constructs the usercp profile menu.
 *
 */
function usercp_menu_profile()
{
	global $db, $mybb, $templates, $theme, $usercpmenu, $lang, $collapse, $collapsed, $collapsedimg;

	$changenameop = '';
	if($mybb->usergroup['canchangename'] != 0)
	{
		eval("\$changenameop = \"".$templates->get("usercp_nav_changename")."\";");
	}

	$changesigop = '';
	if($mybb->usergroup['canusesig'] == 1 && ($mybb->usergroup['canusesigxposts'] == 0 || $mybb->usergroup['canusesigxposts'] > 0 && $mybb->user['postnum'] > $mybb->usergroup['canusesigxposts']))
	{
		if($mybb->user['suspendsignature'] == 0 || $mybb->user['suspendsignature'] == 1 && $mybb->user['suspendsigtime'] > 0 && $mybb->user['suspendsigtime'] < TIME_NOW)
		{
			eval("\$changesigop = \"".$templates->get("usercp_nav_editsignature")."\";");
		}
	}

	if(!isset($collapsedimg['usercpprofile']))
	{
		$collapsedimg['usercpprofile'] = '';
	}

	if(!isset($collapsed['usercpprofile_e']))
	{
		$collapsed['usercpprofile_e'] = '';
	}

	$expaltext = (in_array("usercpprofile", $collapse)) ? $lang->expcol_expand : $lang->expcol_collapse;
	eval("\$usercpmenu .= \"".$templates->get("usercp_nav_profile")."\";");
}

/**
 * Constructs the usercp misc menu.
 *
 */
function usercp_menu_misc()
{
	global $db, $mybb, $templates, $theme, $usercpmenu, $lang, $collapse, $collapsed, $collapsedimg;

	$draftstart = $draftend = '';
	$draftcount = $lang->ucp_nav_drafts;

	$query = $db->simple_select("posts", "COUNT(pid) AS draftcount", "visible = '-2' AND uid = '{$mybb->user['uid']}'");
	$count = $db->fetch_field($query, 'draftcount');

	if($count > 0)
	{
		$draftcount = $lang->sprintf($lang->ucp_nav_drafts_active, my_number_format($count));
	}

	if($mybb->settings['enableattachments'] != 0)
	{
		eval("\$attachmentop = \"".$templates->get("usercp_nav_attachments")."\";");
	}

	if(!isset($collapsedimg['usercpmisc']))
	{
		$collapsedimg['usercpmisc'] = '';
	}

	if(!isset($collapsed['usercpmisc_e']))
	{
		$collapsed['usercpmisc_e'] = '';
	}

	$profile_link = get_profile_link($mybb->user['uid']);
	$expaltext = (in_array("usercpmisc", $collapse)) ? $lang->expcol_expand : $lang->expcol_collapse;
	eval("\$usercpmenu .= \"".$templates->get("usercp_nav_misc")."\";");
}

/**
 * Gets the usertitle for a specific uid.
 *
 * @param int $uid The uid of the user to get the usertitle of.
 * @return string The usertitle of the user.
 */
function get_usertitle($uid=0)
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
		$usertitles = $mybb->cache->read('usertitles');
		foreach($usertitles as $title)
		{
			if($title['posts'] <= $user['postnum'])
			{
				$usertitle = $title;
				break;
			}
		}

		return $usertitle['title'];
	}
}

/**
 * Updates a users private message count in the users table with the number of pms they have.
 *
 * @param int $uid The user id to update the count for. If none, assumes currently logged in user.
 * @param int $count_to_update Bitwise value for what to update. 1 = total, 2 = new, 4 = unread. Combinations accepted.
 * @return array The updated counters
 */
function update_pm_count($uid=0, $count_to_update=7)
{
	global $db, $mybb;

	// If no user id, assume that we mean the current logged in user.
	if((int)$uid == 0)
	{
		$uid = $mybb->user['uid'];
	}

	$uid = (int)$uid;
	$pmcount = array();
	if($uid == 0)
	{
		return $pmcount;
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

	if(!empty($pmcount))
	{
		$db->update_query("users", $pmcount, "uid='".$uid."'");
	}
	return $pmcount;
}

/**
 * Return the language specific name for a PM folder.
 *
 * @param int $fid The ID of the folder.
 * @param string $name The folder name - can be blank, will use language default.
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
		case 0:
			return $lang->folder_inbox;
			break;
		case 1:
			return $lang->folder_unread;
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

/**
 * Generates a security question for registration.
 *
 * @param int $old_qid Optional ID of the old question.
 * @return string The question session id.
 */
function generate_question($old_qid=0)
{
	global $db;

	if($db->type == 'pgsql' || $db->type == 'sqlite')
	{
		$order_by = 'RANDOM()';
	}
	else
	{
		$order_by = 'RAND()';
	}

	$excl_old = '';
	if($old_qid)
	{
		$excl_old = ' AND qid != '.(int)$old_qid;
	}

	$query = $db->simple_select('questions', 'qid, shown', "active=1{$excl_old}", array('limit' => 1, 'order_by' => $order_by));
	$question = $db->fetch_array($query);

	if(!$db->num_rows($query))
	{
		// No active questions exist
		return false;
	}
	else
	{
		$sessionid = random_str(32);

		$sql_array = array(
			"sid" => $sessionid,
			"qid" => $question['qid'],
			"dateline" => TIME_NOW
		);
		$db->insert_query("questionsessions", $sql_array);

		$update_question = array(
			"shown" => $question['shown'] + 1
		);
		$db->update_query("questions", $update_question, "qid = '{$question['qid']}'");

		return $sessionid;
	}
}

/**
 * Check whether we can show the Purge Spammer Feature
 *
 * @param int $post_count The users post count
 * @param int $usergroup The usergroup of our user
 * @param int $uid The uid of our user
 * @return boolean Whether or not to show the feature
 */
function purgespammer_show($post_count, $usergroup, $uid)
{
		global $mybb, $cache;

		// only show this if the current user has permission to use it and the user has less than the post limit for using this tool
		$bangroup = $mybb->settings['purgespammerbangroup'];
		$usergroups = $cache->read('usergroups');

		return ($mybb->user['uid'] != $uid && is_member($mybb->settings['purgespammergroups']) && !is_super_admin($uid)
			&& !$usergroups[$usergroup]['cancp'] && !$usergroups[$usergroup]['canmodcp'] && !$usergroups[$usergroup]['issupermod']
			&& (str_replace($mybb->settings['thousandssep'], '', $post_count) <= $mybb->settings['purgespammerpostlimit'] || $mybb->settings['purgespammerpostlimit'] == 0)
			&& !is_member($bangroup, $uid) && !$usergroups[$usergroup]['isbannedgroup']);
}
