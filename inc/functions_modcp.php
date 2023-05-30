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
 * Check if the current user has permission to perform a ModCP action on another user
 *
 * @param int $uid The user ID to perform the action on.
 * @return boolean True if the user has necessary permissions
 */
function modcp_can_manage_user($uid)
{
	global $mybb;

	$user_permissions = user_permissions($uid);

	// Current user is only a local moderator or use with ModCP permissions, cannot manage super mods or admins
	if($mybb->usergroup['issupermod'] == 0 && ($user_permissions['issupermod'] == 1 || $user_permissions['cancp'] == 1))
	{
		return false;
	}
	// Current user is a super mod or is an administrator
	else if($user_permissions['cancp'] == 1 && ($mybb->usergroup['cancp'] != 1 || (is_super_admin($uid) && !is_super_admin($mybb->user['uid']))))
	{
		return false;
	}
	return true;
}

/**
 * Fetch forums the moderator can manage announcements to
 *
 * @param int $pid (Optional) The parent forum ID
 * @param int $depth (Optional) The depth from parent forum the moderator can manage to
 */
function fetch_forum_announcements($pid=0, $depth=1)
{
	global $mybb, $db, $lang, $theme, $announcements, $templates, $announcements_forum, $moderated_forums, $unviewableforums, $parser;
	static $forums_by_parent, $forum_cache, $parent_forums;

	if(!is_array($forum_cache))
	{
		$forum_cache = cache_forums();
	}
	if(!is_array($parent_forums) && $mybb->usergroup['issupermod'] != 1)
	{
		// Get a list of parentforums to show for normal moderators
		$parent_forums = array();
		foreach($moderated_forums as $mfid)
		{
			$parent_forums = array_merge($parent_forums, explode(',', $forum_cache[$mfid]['parentlist']));
		}
	}
	if(!is_array($forums_by_parent))
	{
		foreach($forum_cache as $forum)
		{
			$forums_by_parent[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
	}

	if(!is_array($forums_by_parent[$pid]))
	{
		return;
	}

	foreach($forums_by_parent[$pid] as $children)
	{
		foreach($children as $forum)
		{
			if($forum['linkto'] || (is_array($unviewableforums) && in_array($forum['fid'], $unviewableforums)))
			{
				continue;
			}

			if($forum['active'] == 0 || !is_moderator($forum['fid'], "canmanageannouncements"))
			{
				// Check if this forum is a parent of a moderated forum
				if(is_array($parent_forums) && in_array($forum['fid'], $parent_forums))
				{
					// A child is moderated, so print out this forum's title.  RECURSE!
					$trow = alt_trow();
					eval("\$announcements_forum .= \"".$templates->get("modcp_announcements_forum_nomod")."\";");
				}
				else
				{
					// No subforum is moderated by this mod, so safely continue
					continue;
				}
			}
			else
			{
				// This forum is moderated by the user, so print out the forum's title, and its announcements
				$trow = alt_trow();

				$padding = 40*($depth-1);

				eval("\$announcements_forum .= \"".$templates->get("modcp_announcements_forum")."\";");

				if(isset($announcements[$forum['fid']]))
				{
					foreach($announcements[$forum['fid']] as $aid => $announcement)
					{
						$trow = alt_trow();

						if($announcement['enddate'] < TIME_NOW && $announcement['enddate'] != 0)
						{
							eval("\$icon = \"".$templates->get("modcp_announcements_announcement_expired")."\";");
						}
						else
						{
							eval("\$icon = \"".$templates->get("modcp_announcements_announcement_active")."\";");
						}

						$subject = htmlspecialchars_uni($parser->parse_badwords($announcement['subject']));

						eval("\$announcements_forum .= \"".$templates->get("modcp_announcements_announcement")."\";");
					}
				}
			}

			// Build the list for any sub forums of this forum
			if(isset($forums_by_parent[$forum['fid']]))
			{
				fetch_forum_announcements($forum['fid'], $depth+1);
			}
		}
	}
}

/**
 * Send reported content to moderators
 *
 * @param array $report Array of reported content
 * @param string $report_type Type of content being reported
 * @return bool|array PM Information or false
 */
function send_report($report, $report_type='post')
{
	global $db, $lang, $forum, $mybb, $post, $thread, $reputation, $user, $plugins;

	$report_reason = '';
	if($report['reasonid'])
	{
		$query = $db->simple_select("reportreasons", "title", "rid = '".(int)$report['reasonid']."'", array('limit' => 1));
		$reason = $db->fetch_array($query);

		$lang->load('report');

		$report_reason = $lang->parse($reason['title']);
	}

	if($report['reason'])
	{
		$report_reason = $lang->sprintf($lang->email_report_comment_extra, $report_reason, $report['reason']);
	}

	$modsjoin = $modswhere = '';
	if(!empty($forum['parentlist']))
	{
		$modswhere = "m.fid IN ({$forum['parentlist']}) OR ";

		if($db->type == 'pgsql' || $db->type == 'sqlite')
		{
			$modsjoin = "LEFT JOIN {$db->table_prefix}moderators m ON (m.id = u.uid AND m.isgroup = 0) OR ((m.id = u.usergroup OR ',' || u.additionalgroups || ',' LIKE '%,' || m.id || ',%') AND m.isgroup = 1)";
		}
		else
		{
			$modsjoin = "LEFT JOIN {$db->table_prefix}moderators m ON (m.id = u.uid AND m.isgroup = 0) OR ((m.id = u.usergroup OR CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', m.id, ',%')) AND m.isgroup = 1)";
		}
	}

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$query = $db->query("
				SELECT DISTINCT u.username, u.email, u.receivepms, u.uid
				FROM {$db->table_prefix}users u
				{$modsjoin}
				LEFT JOIN {$db->table_prefix}usergroups g ON (',' || u.additionalgroups || ',' LIKE '%,' || g.gid || ',%' OR g.gid = u.usergroup)
				WHERE {$modswhere}g.cancp = 1 OR g.issupermod = 1
			");
			break;
		default:
			$query = $db->query("
				SELECT DISTINCT u.username, u.email, u.receivepms, u.uid
				FROM {$db->table_prefix}users u
				{$modsjoin}
				LEFT JOIN {$db->table_prefix}usergroups g ON (CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%') OR g.gid = u.usergroup)
				WHERE {$modswhere}g.cancp = 1 OR g.issupermod = 1
			");
	}

	$lang_string_subject = "emailsubject_report{$report_type}";
	$lang_string_message = "email_report{$report_type}";

	if(empty($lang->$lang_string_subject) || empty($lang->$lang_string_message))
	{
		return false;
	}

	global $send_report_subject, $send_report_url;

	switch($report_type)
	{
		case 'post':
			$send_report_subject = $post['subject'];
			$send_report_url = str_replace('&amp;', '&', get_post_link($post['pid'], $thread['tid'])."#pid".$post['pid']);
			break;
		case 'profile':
			$send_report_subject = $user['username'];
			$send_report_url = str_replace('&amp;', '&', get_profile_link($user['uid']));
			break;
		case 'reputation':
			$from_user = get_user($reputation['adduid']);
			$send_report_subject = $from_user['username'];
			$send_report_url = "reputation.php?uid={$reputation['uid']}#rid{$reputation['rid']}";
			break;
	}

	$plugins->run_hooks("send_report_report_type");

	$emailsubject = $lang->sprintf($lang->$lang_string_subject, $mybb->settings['bbname']);
	$emailmessage = $lang->sprintf($lang->$lang_string_message, $mybb->user['username'], $mybb->settings['bbname'], $send_report_subject, $mybb->settings['bburl'], $send_report_url, $report_reason);
	$pm_recipients = array();
	
	while($mod = $db->fetch_array($query))
	{
		if($mybb->settings['reportmethod'] == "pms" && $mod['receivepms'] != 0 && $mybb->settings['enablepms'] != 0)
		{
			$pm_recipients[] = $mod['uid'];
		}
		else
		{
			my_mail($mod['email'], $emailsubject, $emailmessage);
		}
	}

	if(count($pm_recipients) > 0)
	{
		require_once MYBB_ROOT."inc/datahandlers/pm.php";
		$pmhandler = new PMDataHandler();

		$pm = array(
			"subject" => $emailsubject,
			"message" => $emailmessage,
			"icon" => 0,
			"fromid" => $mybb->user['uid'],
			"toid" => $pm_recipients,
			"ipaddress" => $mybb->session->packedip
		);

		$pm['options'] = array(
			"signature" => 0,
			"disablesmilies" => 0,
			"savecopy" => 0,
			"readreceipt" => 0
		);
		$pm['saveasdraft'] = 0;

		$pmhandler->admin_override = true;
		$pmhandler->set_data($pm);

		// Now let the pm handler do all the hard work.
		if(!$pmhandler->validate_pm())
		{
			// Force it to valid to just get it out of here
			$pmhandler->is_validated = true;
			$pmhandler->errors = array();
		}

		$pminfo = $pmhandler->insert_pm();
		return $pminfo;
	}

	return false;
}

/**
 * Add a report
 *
 * @param array $report Array of reported content
 * @param string $type Type of content being reported
 * @return int Report ID
 */
function add_report($report, $type = 'post')
{
	global $cache, $db, $mybb;

	$insert_array = array(
		'id' => (int)$report['id'],
		'id2' => (int)$report['id2'],
		'id3' => (int)$report['id3'],
		'uid' => (int)$report['uid'],
		'reportstatus' => 0,
		'reasonid' => (int)$report['reasonid'],
		'reason' => $db->escape_string($report['reason']),
		'type' => $db->escape_string($type),
		'reports' => 1,
		'dateline' => TIME_NOW,
		'lastreport' => TIME_NOW,
		'reporters' => $db->escape_string(my_serialize(array($report['uid'])))
	);

	if($mybb->settings['reportmethod'] == "email" || $mybb->settings['reportmethod'] == "pms")
	{
		send_report($report, $type);
	}

	$rid = $db->insert_query("reportedcontent", $insert_array);
	$cache->update_reportedcontent();

	return $rid;
}

/**
 * Update an existing report
 *
 * @param array $report Array of reported content
 * @return bool true
 */
function update_report($report)
{
	global $db;

	$update_array = array(
		'reports' => ++$report['reports'],
		'lastreport' => TIME_NOW,
		'reporters' => $db->escape_string(my_serialize($report['reporters']))
	);

	$db->update_query("reportedcontent", $update_array, "rid = '{$report['rid']}'");
	return true;
}
