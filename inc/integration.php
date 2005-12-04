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

/**
 * The following file allows you to run custom routines
 * and code on various events raised in MyBB, such as
 * account creation, logins etc. This allows you to
 * integrate MyBB along side other products easily.
 *
 * For more information please visit the support forums:
 * http://community.mybboard.com
 */


function accountCreated($uid)
{
	// Called after an account is created
	global $db;
}

function accountActivated($uid)
{
	// Called when an account has been activated
	global $db;
}

function loggedIn($uid)
{
	// Called when a user successfully logs into MyBB
	global $db;
}

function loggedOut($uid)
{
	// Called when a user successfully logs out of MyBB
	global $db;
}

function profileUpdated($uid)
{
	// Called when a user profile is updated
	global $db;
}

function passwordChanged($uid, $password)
{
	// Called when a password is changed
	global $db;
}

function emailChanged($uid, $email)
{
	// Called when an email address is changed
	global $db;
}

function threadPosted($tid)
{
	// Called when a thread is posted
	global $db;
}

function replyPosted($pid)
{
	// Called when a reply is posted
	global $db;
}
?>