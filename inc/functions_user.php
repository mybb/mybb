<?php
//
// Checks if $uid exists in the database
//
function user_exists($uid)
{
	global $db;
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".intval($uid)."'");
	if($db->fetch_array($query))
	{
		return true;
	}
	else
	{
		return false;
	}
}

//
// Check's if $username is a username already in use in the database
//
function username_exists($username)
{
	global $db;
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='".addslashes($username)."'");
	if($db->fetch_array($query))
	{
		return true;
	}
	else
	{
		return false;
	}
}

//
// Check's a password with a supplied username (expects password to be raw)
//
function validate_password_from_username($username, $password)
{
	global $db;
	$query = $db->query("SELECT uid,username,password,salt,loginkey FROM ".TABLE_PREFIX."users WHERE username='".addslashes($username)."'");
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

//
// Check's a password with a supplied user id (expects password to be raw)
//
function validate_password_from_uid($uid, $password, $user="")
{
	global $db;
	if(!$user['password'])
	{
		$query = $db->query("SELECT uid,username,password,salt,loginkey FROM ".TABLE_PREFIX."users WHERE uid='".intval($uid)."'");
		$user = $db->fetch_array($query);
	}
	if(!$user['salt'])
	{
		// Generate a salt for this user
		$user['salt'] = generate_salt();
		$db->query("UPDATE ".TABLE_PREFIX."users SET salt='$salt' WHERE uid='".$user['uid']."'");
	}

	if(!$user['loginkey'])
	{
		$user['loginkey'] = generate_loginkey();
		$db->query("UPDATE ".TABLE_PREFIX."users SET loginkey='".$user['loginkey']."' WHERE uid='".$user['uid']."'");
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

//
// Used to update a password for particular user id in the database (expects password to be md5'd once)
//
function update_password($uid, $password, $salt="")
{
	global $db, $plugins;

	$newpassword = array();

	// 
	// If no salt was specified, check in database first, if still doesn't exist, create one
	//
	if(!$salt)
	{
		$query = $db->query("SELECT salt FROM ".TABLE_PREFIX."users WHERE uid='$uid'");
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
	$db->update_query(TABLE_PREFIX."users", $newpassword, "uid='$uid'");

	$plugins->run_hooks("password_changed");

	return $newpassword;
}

//
// Salt's $password based on $salt (expects $password to be md5'd once)
//
function salt_password($password, $salt)
{
	return md5(md5($salt).$password);
}

//
// Generates an 8 character string for the password salt
//
function generate_salt()
{
	return random_str(8);
}

//
// Generates a 50 character random login key
//
function generate_loginkey()
{
	return random_str(50);
}

//
// Updates a users salt in the database (however it is not possible to update a password)
//
function update_salt($uid)
{
	global $db;
	$salt = generate_salt();
	$db->query("UPDATE ".TABLE_PREFIX."users SET salt='$salt' WHERE uid='$uid'");
	return $salt;
}

//
// Generates a new loginkey for the specified user id
//
function update_loginkey($uid)
{
	global $db;
	$loginkey = generate_loginkey();
	$db->query("UPDATE ".TABLE_PREFIX."users SET loginkey='$loginkey' WHERE uid='$uid'");
	return $loginkey;

}
?>