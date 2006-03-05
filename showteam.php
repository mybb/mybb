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

$plugins->run_hooks("showteam_start");

$teamquery = "";
$comma = '';

$options = array(
	'order_by' => 'u.title',
	'order_dir' => 'ASC',
);
//Get all visible usergroups that aren't moderators
$query = $db->simple_select(TABLE_PREFIX."usergroups u", "u.*", "u.showforumteam='yes' AND u.gid!='6'", $options);
while($usergroup = $db->fetch_array($query))
{
	$teams[$usergroup['gid']] = $usergroup;
	$teamquery .= "$comma'$usergroup[gid]'";
	$comma = ",";
}
//If we have some groups to display then if statement
//If no groups then proceed to moderators
if(!empty($teamquery))
{
	$options = array(
		'order_by' => 'u.username',
		'order_dir' => 'ASC',
	);
	//Get the users in those groups
	$query = $db->simple_select(TABLE_PREFIX."users u", "u.*", "u.displaygroup IN ($teamquery) OR (u.displaygroup = 0 AND u.usergroup IN ($teamquery))", $options);
	while($user = $db->fetch_array($query))
	{
		if($user['displaygroup'] == 0)
		{
			$users[$user['usergroup']][$user['uid']] = $user;
		}
		else
		{
			$users[$user['displaygroup']][$user['uid']] = $user;
		}
	}
	//If there are users then start to display them
	//Else carry on to moderators
	if(is_array($users))
	{
		$usergrouprows = '';
		$usergroups = '';
		//For each team
		while(list($gid, $usergroup) = each($teams))
		{
			//If there are users in this group
			if(is_array($users[$gid]))
			{
				$bgcolor = "trow1";
				//For each user in this group
				while(list($uid, $user) = each($users[$gid]))
				{
					$post['uid'] = $user['uid'];
					$user['username'] = formatname($user['username'], $user['usergroup'], $user['displaygroup']);
					if($user['hideemail'] != "yes")
					{
						eval("\$emailcode = \"".$templates->get("postbit_email")."\";");
					}
					else
					{
						$emailcode = '';
					}
					if($user['receivepms'] != "no" && $mybb->settings['enablepms'] != "no")
					{
						eval("\$pmcode = \"".$templates->get("postbit_pm")."\";");
					}
					else
					{
						$pmcode = '';
					}
					eval("\$usergrouprows .= \"".$templates->get("showteam_usergroup_user")."\";");
					if($bgcolor == "trow1")
					{
						$bgcolor = "trow2";
					}
					else
					{
						$bgcolor = "trow1";
					}
				}//End for each user in group
				eval("\$usergroups .= \"".$templates->get("showteam_usergroup")."\";");
			}//End for each group
			$usergrouprows = '';
		}//For each team
	}//End if users
}//End if displayable groups

unset($user);
//Get list of moderators
$query = $db->query("
	SELECT m.fid, m.uid, u.username, u.usergroup, u.displaygroup, u.hideemail, u.receivepms, f.name
	FROM ".TABLE_PREFIX."moderators m
	LEFT JOIN ".TABLE_PREFIX."users u ON (m.uid=u.uid)
	LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=m.fid)
	ORDER BY u.username, f.name
");
while($mod = $db->fetch_array($query))
{
	$modsarray[$mod['uid']] = $mod;
	if($modforums[$mod['uid']])
	{
		$modforums[$mod['uid']] .= "<br>";
	}
	$modforums[$mod['uid']] .= "<a href=\"forumdisplay.php?fid=$mod[fid]\">$mod[name]</a>";
}
$modrows = '';

//If there are no moderators then display error message
//Otherwise, display users
if(!is_array($modsarray))
{
	error($lang->error_noteamstoshow);
}
else
{
	$bgcolor = "trow1";
	while(list($uid, $user) = each($modsarray))
	{
		$forumslist = $modforums[$uid];
		$uid = $user['uid'];
		$post['uid'] = $user['uid'];
		if($user['hideemail'] != "yes")
		{
			eval("\$emailcode = \"".$templates->get("postbit_email")."\";");
		}
		else
		{
			$emailcode = '';
		}
		if($user['receivepms'] != "no")
		{
			eval("\$pmcode = \"".$templates->get("postbit_pm")."\";");
		}
		else
		{
			$pmcode = '';
		}
		$username = formatname($user['username'], $user['usergroup'], $user['displaygroup']);
		$location = $user['location'];
		eval("\$modrows .= \"".$templates->get("showteam_moderators_mod")."\";");
		if($bgcolor == "trow1")
		{
			$bgcolor = "trow2";
		}
		else
		{
			$bgcolor = "trow1";
		}
	}
	eval("\$usergroups .= \"".$templates->get("showteam_moderators")."\";");
}

eval("\$showteam = \"".$templates->get("showteam")."\";");
$plugins->run_hooks("showteam_end");
outputpage($showteam);
?>