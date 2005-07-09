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

$templatelist = "showteam,showteam_row,showteam_row_mod";
require "./global.php";

// Load global language phrases
$lang->load("showteam");

addnav($lang->nav_showteam);

$teamquery = "";
$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE showforumteam='yes' AND gid!='6' ORDER BY title ASC");
while($usergroup = $db->fetch_array($query)) {
	$teams[$usergroup['gid']] = $usergroup;
	$teamquery .= "$comma'$usergroup[gid]'";
	$comma = ",";
}
$query = $db->query("SELECT u.*, u.usergroup, u.displaygroup FROM ".TABLE_PREFIX."users u WHERE u.usergroup IN ($teamquery) ORDER BY u.username ASC");
while($user = $db->fetch_array($query)) {
	$users[$user['usergroup']][$user['uid']] = $user;
}
if(!is_array($users)) {
	error($lang->error_noteamstoshow);
}
while(list($gid, $usergroup) = each($teams)) {
	if(is_array($users[$gid])) {
		$bgcolor = "trow1";
		while(list($uid, $user) = each($users[$gid])) {
			$post['uid'] = $user['uid'];
			$user['location'] = stripslashes($user['location']);
			$user['username'] = formatname($user['username'], $user['usergroup'], $user['displaygroup']);
			if($memprofile['hideemail'] == "yes")
			{
				eval("\$emailcode = \"".$templates->get("postbit_email")."\";");
			}
			else {
				$emailcode = "";
			}
			eval("\$usergrouprows .= \"".$templates->get("showteam_usergroup_user")."\";");
			if($bgcolor == "trow1") {
				$bgcolor = "trow2";
			} else {
				$bgcolor = "trow1";
			}
		}
		eval("\$usergroups .= \"".$templates->get("showteam_usergroup")."\";");
	}
	$usergrouprows = "";
}
unset($user);
$query = $db->query("SELECT m.fid, m.uid, u.username, u.usergroup, u.displaygroup, u.hideemail, f.name FROM ".TABLE_PREFIX."moderators m LEFT JOIN ".TABLE_PREFIX."users u ON (m.uid=u.uid) LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=m.fid) ORDER BY u.username, f.name");
while($mod = $db->fetch_array($query)) {
	$modsarray[$mod['uid']] = $mod;
	if($modforums[$mod['uid']]) {
		$modforums[$mod['uid']] .= "<br>";
	}
	$modforums[$mod['uid']] .= "<a href=\"forumdisplay.php?fid=$mod[fid]\">$mod[name]</a>";
}
if(is_array($modsarray)) {
	$bgcolor = "trow1";
	while(list($uid, $user) = each($modsarray)) {
		$forumslist = $modforums[$uid];
		$uid = $user['uid'];
		$post['uid'] = $user['uid'];
		if($memprofile['hideemail'] == "yes")
		{
			eval("\$emailcode = \"".$templates->get("postbit_email")."\";");
		}
		else {
			$emailcode = "";
		}
		$username = formatname($user['username'], $user['usergroup'], $user['displaygroup']);
		$location = $user['location'];
		eval("\$modrows .= \"".$templates->get("showteam_moderators_mod")."\";");
		if($bgcolor == "trow1") {
			$bgcolor = "trow2";
		} else {
			$bgcolor = "trow1";
		}
	}
	eval("\$usergroups .= \"".$templates->get("showteam_moderators")."\";");
}

eval("\$showteam = \"".$templates->get("showteam")."\";");
outputpage($showteam);
?>