<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'index.php');

$templatelist = "forumbit_depth1_cat,forumbit_depth2_cat,forumbit_depth2_forum,forumbit_depth1_forum_lastpost,forumbit_depth2_forum_lastpost,forumbit_moderators";
$templatelist .= ",forumbit_depth3,forumbit_depth3_statusicon,forumbit_depth2_forum_lastpost_never,forumbit_depth2_forum_viewers";
$templatelist .= ",forumbit_moderators_group,forumbit_moderators_user,forumbit_depth2_forum_lastpost_hidden,forumbit_subforums,forumbit_depth2_forum_unapproved_posts,forumbit_depth2_forum_unapproved_threads";

require_once './global.php';
require_once MYBB_ROOT.'inc/functions_forumlist.php';
require_once MYBB_ROOT.'inc/class_parser.php';
$parser = new postParser;

$plugins->run_hooks('index_start');

// Load global language phrases
$lang->load('index');

$whosonline = '';
if ($mybb->settings['showwol'] != 0 && $mybb->usergroup['canviewonline'] != 0) {

    // Get the online users.
    if ($mybb->settings['wolorder'] == 'username') {
        $order_by = 'u.username ASC';
        $order_by2 = 's.time DESC';
    } else {
        $order_by = 's.time DESC';
        $order_by2 = 'u.username ASC';
    }

    $timesearch = TIME_NOW - (int)$mybb->settings['wolcutoff'];
    $comma = '';
    $query = $db->query("
        SELECT s.sid, s.ip, s.uid, s.time, s.location, s.location1, u.username, u.invisible, u.usergroup, u.displaygroup
        FROM ".TABLE_PREFIX."sessions s
        LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
        WHERE s.time > '".$timesearch."'
        ORDER BY {$order_by}, {$order_by2}
    ");

    $forum_viewers = $doneusers = array();
    $membercount = $guestcount = $anoncount = $botcount = 0;
    $onlinemembers = $comma = '';

    // Fetch spiders
    $spiders = $cache->read('spiders');

    // Loop through all users.
    while ($user = $db->fetch_array($query)) {

        // Create a key to test if this user is a search bot.
        $botkey = my_strtolower(str_replace('bot=', '', $user['sid']));

        // Decide what type of user we are dealing with.
        if ($user['uid'] > 0) {
            // The user is registered.
            if (empty($doneusers[$user['uid']]) || $doneusers[$user['uid']]['time'] < $user['time']) {

                ++$membercount;
                // If the user is logged in anonymously, update the count for that.
                if ($user['invisible'] == 1) {
                    ++$anoncount;
                }

                if ($user['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $user['uid'] == $mybb->user['uid']) {

                    // Properly format the username and assign the template.
                    $user['username'] = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
                    $user['profilelink'] = build_profile_link($user['username'], $user['uid']);
                }
                // This user has been handled.
                $doneusers[$user['uid']] = $user;
            }
        } elseif (my_strpos($user['sid'], 'bot=') !== false && $spiders[$botkey]) {
            // The user is a search bot.
            $doneusers[$botkey] = format_name($spiders[$botkey]['name'], $spiders[$botkey]['usergroup']);
            ++$botcount;
        } else {
            // The user is a guest.
            ++$guestcount;
        }

        if ($user['location1']) {
            ++$forum_viewers[$user['location1']];
        }
    }

    // Build the who's online bit on the index page.
    $onlinecount = $membercount + $guestcount + $botcount;

    if ($onlinecount != 1) {
        $onlinebit = $lang->online_online_plural;
    } else {
        $onlinebit = $lang->online_online_singular;
    }

    if ($membercount != 1) {
        $memberbit = $lang->online_member_plural;
    } else {
        $memberbit = $lang->online_member_singular;
    }

    if ($anoncount != 1) {
        $anonbit = $lang->online_anon_plural;
    } else {
        $anonbit = $lang->online_anon_singular;
    }

    if ($guestcount != 1) {
        $guestbit = $lang->online_guest_plural;
    }
    else {
        $guestbit = $lang->online_guest_singular;
    }

    $lang->online_note = $lang->sprintf($lang->online_note, my_number_format($onlinecount), $onlinebit, $mybb->settings['wolcutoffmins'], my_number_format($membercount), $memberbit, my_number_format($anoncount), $anonbit, my_number_format($guestcount), $guestbit);

}

// Build the birthdays for to show on the index page.
$bdays = '';
$birthdays = [];
if ($mybb->settings['showbirthdays'] != 0) {

    // First, see what day this is.
    $bdaycount = $bdayhidden = 0;
    $bdaydate = my_date('j-n', TIME_NOW, '', 0);
    $year = my_date('Y', TIME_NOW, '', 0);

    $bdaycache = $cache->read('birthdays');

    if (!is_array($bdaycache)) {
        $cache->update_birthdays();
        $bdaycache = $cache->read('birthdays');
    }

    $hiddencount = 0;
    if (isset($bdaycache[$bdaydate])) {
        $hiddencount = $bdaycache[$bdaydate]['hiddencount'];
        $birthdays = $bdaycache[$bdaydate]['users'];
    }

    if (!empty($birthdays)) {

        if ((int)$mybb->settings['showbirthdayspostlimit'] > 0) {

            $bdayusers = [];
            foreach($birthdays as $key => $bdayuser_pc) {
                $bdayusers[$bdayuser_pc['uid']] = $key;
            }

            if (!empty($bdayusers)) {
                // Find out if our users have enough posts to be seen on our birthday list
                $bday_sql = implode(',', array_keys($bdayusers));
                $query = $db->simple_select('users', 'uid, postnum', "uid IN ({$bday_sql})");

                while ($bdayuser = $db->fetch_array($query)) {
                    if ($bdayuser['postnum'] < $mybb->settings['showbirthdayspostlimit']) {
                        unset($birthdays[$bdayusers[$bdayuser['uid']]]);
                    }
                }

            }

        }

        // We still have birthdays - display them in our list!
        if (!empty($birthdays)) {

            foreach($birthdays as $key => $bdayuser) {

                if ($bdayuser['displaygroup'] == 0) {
                    $birthdays[$key]['displaygroup'] = $bdayuser['usergroup'];
                }

                // If this user's display group can't be seen in the birthday list, skip it
                if ($groupscache[$bdayuser['displaygroup']] && $groupscache[$bdayuser['displaygroup']]['showinbirthdaylist'] != 1) {
                    continue;
                }

                $bday = explode('-', $bdayuser['birthday']);
                if ($year > $bday['2'] && $bday['2'] != '') {
                    $birthdays[$key]['age'] = $year - $bday['2'];
                }

                $birthdays[$key]['username'] = format_name(htmlspecialchars_uni($bdayuser['username']), $bdayuser['usergroup'], $bdayuser['displaygroup']);
                $birthdays[$key]['profilelink'] = build_profile_link($bdayuser['username'], $bdayuser['uid']);
                ++$bdaycount;

            }

        }

    }

}

// Build the forum statistics to show on the index page.
$forumstats = '';
if ($mybb->settings['showindexstats'] != 0) {
    // First, load the stats cache.
    $stats = $cache->read('stats');

    // Check who's the newest member.
    if (!$stats['lastusername']) {
        $newestmember = $lang->nobody;;
    } else {
        $newestmember = build_profile_link($stats['lastusername'], $stats['lastuid']);
    }

    // Format the stats language.
    $lang->stats_posts_threads = $lang->sprintf($lang->stats_posts_threads, my_number_format($stats['numposts']), my_number_format($stats['numthreads']));
    $lang->stats_numusers = $lang->sprintf($lang->stats_numusers, my_number_format($stats['numusers']));
    $lang->stats_newestuser = $lang->sprintf($lang->stats_newestuser, $newestmember);

    // Find out what the highest users online count is.
    $mostonline = $cache->read('mostonline');
    if ($onlinecount > $mostonline['numusers']) {
        $time = TIME_NOW;
        $mostonline['numusers'] = $onlinecount;
        $mostonline['time'] = $time;
        $cache->update('mostonline', $mostonline);
    }
    $recordcount = $mostonline['numusers'];
    $recorddate = my_date($mybb->settings['dateformat'], $mostonline['time']);
    $recordtime = my_date($mybb->settings['timeformat'], $mostonline['time']);

    // Then format that language string.
    $lang->stats_mostonline = $lang->sprintf($lang->stats_mostonline, my_number_format($recordcount), $recorddate, $recordtime);
}

// Load the stats cache.
if (!isset($stats) || isset($stats) && !is_array($stats)) {
    $stats = $cache->read('stats');
}

if ($mybb->user['uid'] == 0) {
    // Build a forum cache.
    $query = $db->simple_select('forums', '*', 'active!=0', array('order_by' => 'pid, disporder'));

    $forumsread = array();
    if (isset($mybb->cookies['mybb']['forumread'])) {
        $forumsread = my_unserialize($mybb->cookies['mybb']['forumread']);
    }
}
else {
    // Build a forum cache.
    $query = $db->query("
        SELECT f.*, fr.dateline AS lastread
        FROM ".TABLE_PREFIX."forums f
        LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid = f.fid AND fr.uid = '{$mybb->user['uid']}')
        WHERE f.active != 0
        ORDER BY pid, disporder
    ");
}

while($forum = $db->fetch_array($query)) {
    if ($mybb->user['uid'] == 0) {
        if (!empty($forumsread[$forum['fid']])) {
            $forum['lastread'] = $forumsread[$forum['fid']];
        }
    }

    $fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
}

$forumpermissions = forum_permissions();

// Get the forum moderators if the setting is enabled.
$moderatorcache = array();
if ($mybb->settings['modlist'] != 0 && $mybb->settings['modlist'] != 'off') {
    $moderatorcache = $cache->read('moderators');
}

$excols = 'index';
$permissioncache['-1'] = '1';
$bgcolor = 'trow1';

// Decide if we're showing first-level subforums on the index page.
$showdepth = 2;
if ($mybb->settings['subforumsindex'] != 0) {
    $showdepth = 3;
}

$forum_list = build_forumbits();
$forums = $forum_list['forum_list'];

$plugins->run_hooks('index_end');

output_page(\MyBB\template('index/index.twig', [
    'forums' => $forums,
    'users' => $doneusers,
    'birthdays' => $birthdays,
    'hiddencount' => $hiddencount
]));