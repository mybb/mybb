<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'showteam.php');

$templatelist = 'showteam,postbit_email,postbit_pm,showteam_usergroup_user,showteam_usergroup,showteam_moderators_mod,showteam_moderators,showteam_moderators_forum';
require_once "./global.php";

// Load global language phrases
$lang->load('showteam');

add_breadcrumb($lang->nav_showteam);

$plugins->run_hooks('showteam_start');

$timecut = TIME_NOW - $mybb->settings['wolcutoff'];

$usergroups = array();
$moderators = array();
$users = array();

// Fetch the list of groups which are to be shown on the page
$query = $db->simple_select("usergroups", "gid, title, usertitle", "showforumteam=1", array('order_by' => 'disporder'));
while($usergroup = $db->fetch_array($query))
{
	$usergroups[$usergroup['gid']] = $usergroup;
}

if(empty($usergroups))
{
	error($lang->error_noteamstoshow);
}

// Fetch specific forum moderator details
if($usergroups[6]['gid'])
{
	$query = $db->query("
		SELECT m.*, f.name
		FROM ".TABLE_PREFIX."moderators m
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=m.id)
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=m.fid)
		WHERE f.active = 1 AND m.isgroup = 0
		ORDER BY u.username
	");
	while($moderator = $db->fetch_array($query))
	{
		$moderators[$moderator['id']][] = $moderator;
	}
}

// Now query the users of those specific groups
$groups_in = implode(",", array_keys($usergroups));
$users_in = implode(",", array_keys($moderators));
if(!$groups_in)
{
	$groups_in = 0;
}
if(!$users_in)
{
	$users_in = 0;
}
$forum_permissions = forum_permissions();

$query = $db->simple_select("users", "uid, username, displaygroup, usergroup, ignorelist, hideemail, receivepms, lastactive, lastvisit, invisible, away", "displaygroup IN ($groups_in) OR (displaygroup='0' AND usergroup IN ($groups_in)) OR uid IN ($users_in)", array('order_by' => 'username'));
while ($user = $db->fetch_array($query)) {
    // If this user is a moderator
    if (isset($moderators[$user['uid']])) {
        $forumlist = [];
        foreach ($moderators[$user['uid']] as $forum) {
            if ($forum_permissions[$forum['fid']]['canview'] == 1) {
                $forum['url'] = get_forum_link($forum['fid']);

                $forumlist[] = $forum;
            }
        }

        $user['forumlist'] = $forumlist;
        $usergroups[6]['user_list'][$user['uid']] = $user;
    }

    if ($user['displaygroup'] == '6' || $user['usergroup'] == '6') {
        $usergroups[6]['user_list'][$user['uid']] = $user;
    }

    // Are they also in another group which is being shown on the list?
    if ($user['displaygroup'] != 0) {
        $group = $user['displaygroup'];
    } else {
        $group = $user['usergroup'];
    }

    if ($usergroups[$group] && $group != 6) {
        $usergroups[$group]['user_list'][$user['uid']] = $user;
    }
}

// Now we have all of our user details we can display them.
$usergrouplist = [];
foreach ($usergroups as $usergroup) {
    // If we have no users - don't show this group
    if (!isset($usergroup['user_list'])) {
        continue;
    }

    foreach ($usergroup['user_list'] as $user) {
        $user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
        $user['profilelink'] = get_profile_link($user['uid']);

        $user['showemail'] = false;
        if ($user['hideemail'] != 1 && $mybb->usergroup['canviewwolinvis'] == 1 && my_strpos(",".$user['ignorelist'].",", ",".$mybb->user['uid'].",") === false) {
            $user['showemail'] = true;
        }

        $user['showpm'] = false;
        if ($user['receivepms'] != 0 && $mybb->settings['enablepms'] != 0 && $mybb->usergroup['canusepms'] != 0 && my_strpos(",".$user['ignorelist'].",", ",".$mybb->user['uid'].",") === false) {
            $user['showpm'] = true;
        }

        // For the online image
        if ($user['lastactive'] > $timecut && ($user['invisible'] == 0 || $mybb->usergroup['canviewwolinvis'] == 1) && $user['lastvisit'] != $user['lastactive']) {
            $user['status'] = "online";
            $user['statuslang'] = $lang->online;
        } else if ($user['away'] == 1 && $mybb->settings['allowaway'] != 0) {
            $user['status'] = "away";
            $user['statuslang'] = $lang->away;
        } else {
            $user['status'] = "offline";
            $user['statuslang'] = $lang->offline;
        }

        if ($user['invisible'] == 1 && $mybb->usergroup['canviewwolinvis'] != 1 && $user['uid'] != $mybb->user['uid']) {
            if ($user['lastactive']) {
                $user['lastvisit'] = $lang->lastvisit_hidden;
            } else {
                $user['lastvisit'] = $lang->lastvisit_never;
            }
        } else {
            $user['lastvisit'] = my_date('relative', $user['lastactive']);
        }

        $plugins->run_hooks('showteam_user');

        $usergroup['users'][] = $user;
    }

    if ($usergroup['users']) {
        $usergrouplist[] = $usergroup;
    }
}

if (empty($usergrouplist)) {
    error($lang->error_noteamstoshow);
}

$plugins->run_hooks("showteam_end");

output_page(\MyBB\template('showteam/showteam.twig', [
    'usergrouplist' => $usergrouplist,
]));
