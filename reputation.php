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
define('THIS_SCRIPT', 'reputation.php');

require_once "./global.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("reputation");

$plugins->run_hooks("reputation_start");

// Check if the reputation system is globally disabled or not.
if($mybb->settings['enablereputation'] != 1)
{
	error($lang->reputation_disabled);
}

// Does this user have permission to view the board?
if($mybb->usergroup['canview'] != 1)
{
	error_no_permission();
}

// If we have a specified incoming username, validate it and fetch permissions for it
$uid = $mybb->get_input('uid', MyBB::INPUT_INT);
$user = get_user($uid);
if(!$user)
{
	error($lang->add_no_uid);
}
$user_permissions = user_permissions($uid);

// Fetch display group properties.
$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");

if(!$user['displaygroup'])
{
	$user['displaygroup'] = $user['usergroup'];
}

$display_group = usergroup_displaygroup($user['displaygroup']);
if(is_array($display_group))
{
	$user_permissions = array_merge($user_permissions, $display_group);
}

$mybb->input['action'] = $mybb->get_input('action');

// Here we perform our validation when adding a reputation to see if the user
// has permission or not. This is done here to save duplicating the same code.
if($mybb->input['action'] == "add" || $mybb->input['action'] == "do_add")
{
	// This user doesn't have permission to give reputations.
	if($mybb->usergroup['cangivereputations'] != 1)
	{
		$message = $lang->add_no_permission;
	}

	// The user we're trying to give a reputation to doesn't have permission to receive reps.
	if($user_permissions['usereputationsystem'] != 1)
	{
		$message = $lang->add_disabled;
	}

	// Is this user trying to give themself a reputation?
	if($uid == $mybb->user['uid'])
	{
		$message = $lang->add_yours;
	}

	if(!empty($message))
	{
		if($mybb->input['nomodal'])
		{
			output_page(\MyBB\template('reputation/add_error_nomodal.twig', [
				'message' => $message,
			]));
			exit;
		}
		else
		{
			output_page(\MyBB\template('reputation/add_error.twig', [
				'message' => $message,
			]));
			exit;
		}
	}

	// If a post has been given but post ratings have been disabled, set the post to 0. This will mean all subsequent code will think no post was given.
	if($mybb->settings['postrep'] != 1)
	{
		$mybb->input['pid'] = 0;
	}

	if($mybb->get_input('pid', MyBB::INPUT_INT))
	{
		// Make sure that this post exists, and that the author of the post we're giving this reputation for corresponds with the user the rep is being given to.
		$post = get_post($mybb->get_input('pid', MyBB::INPUT_INT));
		if($post)
		{
			$thread = get_thread($post['tid']);
			$forum = get_forum($thread['fid']);
			$forumpermissions = forum_permissions($forum['fid']);

			// Post doesn't belong to that user or isn't visible
			if($uid != $post['uid'] || $post['visible'] != 1)
			{
				$mybb->input['pid'] = 0;
			}

			// Thread isn't visible
			elseif($thread['visible'] != 1)
			{
				$mybb->input['pid'] = 0;
			}

			// Current user can't see the forum
			elseif($forumpermissions['canview'] == 0 || $forumpermissions['canpostreplys'] == 0 || $mybb->user['suspendposting'] == 1)
			{
				$mybb->input['pid'] = 0;
			}

			// Current user can't see that thread
			elseif(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
			{
				$mybb->input['pid'] = 0;
			}
		}
		else
		{
			$mybb->input['pid'] = 0;
		}
	}

	$rid = 0;

	// Fetch the existing reputation for this user given by our current user if there is one.
	// If multiple reputations is allowed, then this isn't needed
	if($mybb->settings['multirep'] != 1 && $mybb->get_input('pid', MyBB::INPUT_INT) == 0)
	{
		$query = $db->simple_select("reputation", "*", "adduid='".$mybb->user['uid']."' AND uid='{$uid}' AND pid='0'");
		$existing_reputation = $db->fetch_array($query);
		$rid = $existing_reputation['rid'];
		$was_post = false;
	}
	if($mybb->get_input('pid', MyBB::INPUT_INT) != 0)
	{
		$query = $db->simple_select("reputation", "*", "adduid='".$mybb->user['uid']."' AND uid='{$uid}' AND pid = '".$mybb->get_input('pid', MyBB::INPUT_INT)."'");
		$existing_reputation = $db->fetch_array($query);

		if($existing_reputation)
		{
			$rid = $existing_reputation['rid'];
		}
		else
		{
			$rid = 0;
		}

		$was_post = true;
	}

	if($rid == 0 && ($mybb->input['action'] != "do_add" || ($mybb->input['action'] == "do_add" && empty($mybb->input['delete']))))
	{
		$message = '';

		// Check if this user has reached their "maximum reputations per day" quota
		if($mybb->usergroup['maxreputationsday'] != 0)
		{
			$timesearch = TIME_NOW - (60 * 60 * 24);
			$query = $db->simple_select("reputation", "*", "adduid='{$mybb->user['uid']}' AND dateline>'$timesearch'");
			$numtoday = $db->num_rows($query);

			// Reached the quota - error.
			if($numtoday >= $mybb->usergroup['maxreputationsday'])
			{
				$message = $lang->add_maxperday;
			}
		}

		// Is the user giving too much reputation to another?
		if(!$message && $mybb->usergroup['maxreputationsperuser'] != 0)
		{
			$timesearch = TIME_NOW - (60 * 60 * 24);
			$query = $db->simple_select("reputation", "*", "uid='{$uid}' AND adduid='{$mybb->user['uid']}' AND dateline>'$timesearch'");
			$numtoday = $db->num_rows($query);

			if($numtoday >= $mybb->usergroup['maxreputationsperuser'])
			{
				$message = $lang->add_maxperuser;
			}
		}

		// We have the correct post, but has the user given too much reputation to another in the same thread?
		if(!$message && !empty($was_post) && $mybb->usergroup['maxreputationsperthread'] != 0)
		{
			$timesearch = TIME_NOW - (60 * 60 * 24);
			$query = $db->query("
				SELECT COUNT(p.pid) AS posts
				FROM ".TABLE_PREFIX."reputation r
				LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid = r.pid)
				WHERE r.uid = '{$uid}' AND r.adduid = '{$mybb->user['uid']}' AND p.tid = '{$post['tid']}' AND r.dateline > '{$timesearch}'
			");

			$numtoday = $db->fetch_field($query, 'posts');

			if($numtoday >= $mybb->usergroup['maxreputationsperthread'])
			{
				$message = $lang->add_maxperthread;
			}
		}

		if(!empty($message))
		{
			if($mybb->input['nomodal'])
			{
				output_page(\MyBB\template('reputation/add_error_nomodal.twig', [
					'message' => $message,
				]));
				exit;
			}
			else
			{
				output_page(\MyBB\template('reputation/add_error.twig', [
					'message' => $message,
				]));
				exit;
			}
		}
	}
}

// Saving the new reputation
if($mybb->input['action'] == "do_add" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("reputation_do_add_start");

	// Check if the reputation power they're trying to give is within their "power limit"
	$reputation = abs($mybb->get_input('reputation', MyBB::INPUT_INT));

	// Deleting our current reputation of this user.
	if(!empty($mybb->input['delete']))
	{
		// Only administrators, super moderators, as well as users who gave a specifc vote can delete one.
		if($mybb->usergroup['issupermod'] != 1 && ($mybb->usergroup['candeletereputations'] != 1 || $existing_reputation['adduid'] != $mybb->user['uid'] || $mybb->user['uid'] == 0))
		{
			error_no_permission();
		}

		if($mybb->get_input('pid', MyBB::INPUT_INT) != 0)
		{
			$db->delete_query("reputation", "uid='{$uid}' AND adduid='".$mybb->user['uid']."' AND pid = '".$mybb->get_input('pid', MyBB::INPUT_INT)."'");
		}
		else
		{
			$db->delete_query("reputation", "rid='{$rid}' AND uid='{$uid}' AND adduid='".$mybb->user['uid']."'");
		}

		// Recount the reputation of this user - keep it in sync.
		$query = $db->simple_select("reputation", "SUM(reputation) AS reputation_count", "uid='{$uid}'");
		$reputation_value = $db->fetch_field($query, "reputation_count");

		$db->update_query("users", array('reputation' => (int)$reputation_value), "uid='{$uid}'");

		output_page(\MyBB\template('reputation/deleted.twig'));
		exit;
	}

	$mybb->input['comments'] = trim($mybb->get_input('comments')); // Trim whitespace to check for length
	if(my_strlen($mybb->input['comments']) < $mybb->settings['minreplength'] && $mybb->get_input('pid', MyBB::INPUT_INT) == 0)
	{
		$message = $lang->add_no_comment;
	}

	// The power for the reputation they specified was invalid.
	if($reputation > $mybb->usergroup['reputationpower'])
	{
		$message = $lang->add_invalidpower;
	}

	// The user is trying to give a negative reputation, but negative reps have been disabled.
	if($mybb->get_input('reputation', MyBB::INPUT_INT) < 0 && $mybb->settings['negrep'] != 1)
	{
		$message = $lang->add_negative_disabled;
	}

	// This user is trying to give a neutral reputation, but neutral reps have been disabled.
	if($mybb->get_input('reputation', MyBB::INPUT_INT) == 0 && $mybb->settings['neurep'] != 1)
	{
		$message = $lang->add_neutral_disabled;
	}

	// This user is trying to give a positive reputation, but positive reps have been disabled.
	if($mybb->get_input('reputation', MyBB::INPUT_INT) > 0 && $mybb->settings['posrep'] != 1)
	{
		$message = $lang->add_positive_disabled;
	}

	// The length of the comment is too long
	if(my_strlen($mybb->input['comments']) > $mybb->settings['maxreplength'])
	{
		$message = $lang->sprintf($lang->add_toolong, $mybb->settings['maxreplength']);
	}

	if(!empty($message))
	{
		if($mybb->input['nomodal'])
		{
			output_page(\MyBB\template('reputation/add_error_nomodal.twig', [
				'message' => $message,
			]));
			exit;
		}
		else
		{
			output_page(\MyBB\template('reputation/add_error.twig', [
				'message' => $message,
			]));
			exit;
		}
	}

	// Build array of reputation data.
	$reputation = array(
		"uid" => $uid,
		"adduid" => $mybb->user['uid'],
		"pid" => $mybb->get_input('pid', MyBB::INPUT_INT),
		"reputation" => $mybb->get_input('reputation', MyBB::INPUT_INT),
		"dateline" => TIME_NOW,
		"comments" => $db->escape_string($mybb->input['comments'])
	);

	$plugins->run_hooks("reputation_do_add_process");

	// Updating an existing reputation
	if(!empty($existing_reputation['uid']))
	{
		$db->update_query("reputation", $reputation, "rid='".$existing_reputation['rid']."'");

		// Recount the reputation of this user - keep it in sync.
		$query = $db->simple_select("reputation", "SUM(reputation) AS reputation_count", "uid='{$uid}'");
		$reputation_value = $db->fetch_field($query, "reputation_count");

		$db->update_query("users", array('reputation' => (int)$reputation_value), "uid='{$uid}'");

		$lang->vote_added = $lang->vote_updated;
		$lang->vote_added_message = $lang->vote_updated_message;
	}
	// Insert a new reputation
	else
	{
		$db->insert_query("reputation", $reputation);

		// Recount the reputation of this user - keep it in sync.
		$query = $db->simple_select("reputation", "SUM(reputation) AS reputation_count", "uid='{$uid}'");
		$reputation_value = $db->fetch_field($query, "reputation_count");

		$db->update_query("users", array('reputation' => (int)$reputation_value), "uid='{$uid}'");
	}

	$plugins->run_hooks("reputation_do_add_end");

	output_page(\MyBB\template('reputation/added.twig'));
	exit;
}

// Adding a new reputation
if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("reputation_add_start");

	// If we have an existing reputation for this user, the user can modify or delete it.
	if(!empty($existing_reputation['uid']))
	{
		$existing_reputation['show_delete'] = false;
		if($mybb->usergroup['issupermod'] == 1 || ($mybb->usergroup['candeletereputations'] == 1 && $existing_reputation['adduid'] == $mybb->user['uid'] && $mybb->user['uid'] != 0))
		{
			$existing_reputation['show_delete'] = true;
		}
	}

	// Draw the "power" options
	if($mybb->settings['negrep'] || $mybb->settings['neurep'] || $mybb->settings['posrep'])
	{
		$vote_check = array();

		foreach(range(-$mybb->usergroup['reputationpower'], $mybb->usergroup['reputationpower']) as $value)
		{
			$vote_check[$value] = false;
		}

		if(!empty($existing_reputation['uid']) && !$was_post)
		{
			$vote_check[$existing_reputation['reputation']] = true;
		}

		$vote_check['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
		$vote_check['rid'] = $rid;

		$plugins->run_hooks("reputation_add_end");

		output_page(\MyBB\template('reputation/add.twig', [
			'user' => $user,
			'existing_reputation' => $existing_reputation,
			'vote_check' => $vote_check,
		]));
		exit;
	}
	else
	{
		$message = $lang->add_all_rep_disabled;

		$plugins->run_hooks("reputation_add_end_error");

		if(!empty($message))
		{
			if($mybb->input['nomodal'])
			{
				output_page(\MyBB\template('reputation/add_error_nomodal.twig', [
					'message' => $message,
				]));
				exit;
			}
			else
			{
				output_page(\MyBB\template('reputation/add_error.twig', [
					'message' => $message,
				]));
				exit;
			}
		}
	}
}

// Delete a specific reputation from a user.
if($mybb->input['action'] == "delete")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$rid = $mybb->get_input('rid', MyBB::INPUT_INT);
	
	$plugins->run_hooks("reputation_delete_start");

	// Fetch the existing reputation for this user given by our current user if there is one.
	$query = $db->query("
		SELECT r.*, u.username
		FROM ".TABLE_PREFIX."reputation r
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=r.adduid)
		WHERE r.rid = '{$rid}' AND r.uid = '{$uid}'
	");
	$existing_reputation = $db->fetch_array($query);

	// Only administrators, super moderators, as well as users who gave a specifc vote can delete one.
	if($mybb->usergroup['issupermod'] != 1 && ($mybb->usergroup['candeletereputations'] != 1 || $existing_reputation['adduid'] != $mybb->user['uid'] || $mybb->user['uid'] == 0))
	{
		error_no_permission();
	}
	
	$plugins->run_hooks("reputation_delete_end");

	// Delete the specified reputation
	$db->delete_query("reputation", "uid='{$uid}' AND rid='{$rid}'");

	// Recount the reputation of this user - keep it in sync.
	$query = $db->simple_select("reputation", "SUM(reputation) AS reputation_count", "uid='{$uid}'");
	$reputation_value = $db->fetch_field($query, "reputation_count");

	// Create moderator log
	log_moderator_action(array("uid" => $user['uid'], "username" => $user['username']), $lang->sprintf($lang->delete_reputation_log, $existing_reputation['username'], $existing_reputation['adduid']));

	$db->update_query("users", array('reputation' => (int)$reputation_value), "uid='{$uid}'");

	redirect("reputation.php?uid={$uid}", $lang->vote_deleted_message);
}

// Otherwise, show a listing of reputations for the given user.
if(!$mybb->input['action'])
{
	if($mybb->usergroup['canviewprofiles'] == 0)
	{
		// Reputation page is a part of a profile
		error_no_permission();
	}

	if($user_permissions['usereputationsystem'] != 1)
	{
		// Group has reputation disabled or user has a display group that has reputation disabled
		error($lang->reputations_disabled_group);
	}

	$lang->nav_profile = $lang->sprintf($lang->nav_profile, $user['username']);

	// Format the user name using the group username style
	$user['user_name'] = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);

	// Set display group to their user group if they don't have a display group.
	if(!$user['displaygroup'])
	{
		$user['displaygroup'] = $user['usergroup'];
	}

	// This user has a custom user title
	if(trim($user['usertitle']) != '')
	{
		$user['user_title'] = $user['usertitle'];
	}
	elseif(trim($display_group['usertitle']) != '')
	{
		// Using our display group's user title
		$user['user_title'] = $display_group['usertitle'];
	}
	else
	{
		// Otherwise, fetch it from our titles table for the number of posts this user has
		$usertitles = $cache->read('usertitles');
		foreach($usertitles as $title)
		{
			if($title['posts'] <= $user['postnum'])
			{
				$user['user_title'] = $title['title'];
				break;
			}
		}

		unset($usertitles, $title);
	}

	// If the user has permission to add reputations - show the image
	$user['show_add_reputation'] = false;
	if($mybb->usergroup['cangivereputations'] == 1 && $mybb->user['uid'] != $user['uid'] && ($mybb->settings['posrep'] || $mybb->settings['neurep'] || $mybb->settings['negrep']))
	{
		$user['show_add_reputation'] = true;
	}

	// Build navigation menu
	add_breadcrumb($lang->nav_profile, get_profile_link($user['uid']));
	add_breadcrumb($lang->nav_reputation);

	// Check our specified conditionals for what type of reputations to show
	$select['show'] = array('all' => false, 'positive' => false, 'neutral' => false, 'negative' => false);
	switch($mybb->get_input('show'))
	{
		case "positive":
			$s_url = "&show=positive";
			$conditions = 'AND r.reputation>0';
			$select['show']['positive'] = true;
			break;
		case "neutral":
			$s_url = "&show=neutral";
			$conditions = 'AND r.reputation=0';
			$select['show']['neutral'] = true;
			break;
		case "negative":
			$s_url = "&show=negative";
			$conditions = 'AND r.reputation<0';
			$select['show']['negative'] = true;
			break;
		default:
			$s_url = '&show=all';
			$conditions = '';
			$select['show']['all'] = true;
			break;
	}

	// Check the sorting options for the reputation list
	$select['sort'] = array('username' => false, 'last_updated' => false);
	switch($mybb->get_input('sort'))
	{
		case "username":
			$s_url .= "&sort=username";
			$order = "u.username ASC";
			$select['sort']['username'] = true;
			break;
		default:
			$s_url .= '&sort=dateline';
			$order = "r.dateline DESC";
			$select['sort']['last_updated'] = true;
			break;
	}

	if(empty($mybb->input['show']) && empty($mybb->input['sort']))
	{
		$s_url = '';
	}

	// Fetch the total number of reputations for this user
	$query = $db->simple_select("reputation r", "COUNT(r.rid) AS reputation_count", "r.uid='{$user['uid']}' $conditions");
	$reputation_count = $db->fetch_field($query, "reputation_count");

	// If the user has no reputation, suspect 0...
	if(!$user['reputation'])
	{
		$user['reputation'] = 0;
	}

	// Quickly check to see if we're in sync...
	$query = $db->simple_select("reputation", "SUM(reputation) AS reputation, COUNT(rid) AS total_reputation", "uid = '".$user['uid']."'");
	$reputation = $db->fetch_array($query);

	$sync_reputation = (int)$reputation['reputation'];
	$total_reputation = $reputation['total_reputation'];

	if($sync_reputation != $user['reputation'])
	{
		// We're out of sync! Oh noes!
		$db->update_query("users", array("reputation" => $sync_reputation), "uid = '".$user['uid']."'");
		$user['reputation'] = $sync_reputation;
	}

	// Set default count variables to 0
	$positive_count = $negative_count = $neutral_count = 0;
	$positive_week = $negative_week = $neutral_week = 0;
	$positive_month = $negative_month = $neutral_month = 0;
	$positive_6months = $negative_6months = $neutral_6months = 0;

	// Unix timestamps for when this week, month and last 6 months started
	$last_week = TIME_NOW - 604800;
	$last_month = TIME_NOW - 2678400;
	$last_6months = TIME_NOW - 16070400;

	// Query reputations for the "reputation card"
	$query = $db->simple_select("reputation", "reputation, dateline", "uid='{$user['uid']}'");
	while($reputation_vote = $db->fetch_array($query))
	{
		// This is a positive reputation
		if($reputation_vote['reputation'] > 0)
		{
			$positive_count++;
			if($reputation_vote['dateline'] >= $last_week)
			{
				$positive_week++;
			}

			if($reputation_vote['dateline'] >= $last_month)
			{
				$positive_month++;
			}

			if($reputation_vote['dateline'] >= $last_6months)
			{
				$positive_6months++;
			}
		}
		elseif($reputation_vote['reputation'] < 0)
		{
			// Negative reputation given
			$negative_count++;
			if($reputation_vote['dateline'] >= $last_week)
			{
				$negative_week++;
			}

			if($reputation_vote['dateline'] >= $last_month)
			{
				$negative_month++;
			}

			if($reputation_vote['dateline'] >= $last_6months)
			{
				$negative_6months++;
			}
		}
		else
		{
			// Neutral reputation given
			$neutral_count++;
			if($reputation_vote['dateline'] >= $last_week)
			{
				$neutral_week++;
			}

			if($reputation_vote['dateline'] >= $last_month)
			{
				$neutral_month++;
			}

			if($reputation_vote['dateline'] >= $last_6months)
			{
				$neutral_6months++;
			}
		}
	}

	// Format all reputation numbers
	$user['rep_total'] = my_number_format($user['reputation']);
	$user['positive_count'] = my_number_format($positive_count);
	$user['negative_count'] = my_number_format($negative_count);
	$user['neutral_count'] = my_number_format($neutral_count);
	$user['positive_week'] = my_number_format($positive_week);
	$user['negative_week'] = my_number_format($negative_week);
	$user['neutral_week'] = my_number_format($neutral_week);
	$user['positive_month'] = my_number_format($positive_month);
	$user['negative_month'] = my_number_format($negative_month);
	$user['neutral_month'] = my_number_format($neutral_month);
	$user['positive_6months'] = my_number_format($positive_6months);
	$user['negative_6months'] = my_number_format($negative_6months);
	$user['neutral_6months'] = my_number_format($neutral_6months);

	// Format the user's 'total' reputation
	if($user['reputation'] < 0)
	{
		$user['total_class'] = "_minus";
	}
	elseif($user['reputation'] > 0)
	{
		$user['total_class'] = "_plus";
	}
	else
	{
		$user['total_class'] = "_neutral";
	}

	// Figure out how many reps have come from posts / 'general'
	// Posts
	$query = $db->simple_select("reputation", "COUNT(rid) AS rep_posts", "uid = '".$user['uid']."' AND pid > 0");
	$rep_post_count = $db->fetch_field($query, "rep_posts");
	$user['rep_posts'] = my_number_format($rep_post_count);

	// General
	// We count how many reps in total, then subtract the reps from posts
	$user['rep_members'] = my_number_format($total_reputation - $rep_post_count);

	$perpage = (int)$mybb->settings['repsperpage'];
	if($perpage < 1)
	{
		$perpage = 15;
	}

	// Check if we're browsing a specific page of results
	if($mybb->get_input('page', MyBB::INPUT_INT) > 0)
	{
		$page = $mybb->get_input('page', MyBB::INPUT_INT);
		$start = ($page - 1) * $perpage;
		$pages = $reputation_count / $perpage;
		$pages = ceil($pages);
		if($page > $pages)
		{
			$start = 0;
			$page = 1;
		}
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$multipage = '';

	// Build out multipage navigation
	if($reputation_count > 0)
	{
		$multipage = multipage($reputation_count, $perpage, $page, "reputation.php?uid={$user['uid']}".$s_url);
	}

	// Fetch the reputations which will be displayed on this page
	$query = $db->query("
        SELECT r.*, r.uid AS rated_uid, u.uid, u.username, u.reputation AS user_reputation, u.usergroup AS user_usergroup, u.displaygroup AS user_displaygroup
        FROM ".TABLE_PREFIX."reputation r
        LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=r.adduid)
        WHERE r.uid='{$user['uid']}' {$conditions}
        ORDER BY {$order}
        LIMIT {$start}, {$perpage}
    ");

	// Gather a list of items that have post reputation
	$reputation_cache = $post_cache = $post_reputation = $not_reportable = array();

	while($reputation_vote = $db->fetch_array($query))
	{
		$reputation_cache[] = $reputation_vote;

		// If this is a post, hold it and gather some information about it
		if($reputation_vote['pid'] && !isset($post_cache[$reputation_vote['pid']]))
		{
			$post_cache[$reputation_vote['pid']] = $reputation_vote['pid'];
		}
	}

	$report_link = false;
	if(!empty($post_cache))
	{
		$pids = implode(',', $post_cache);

		$sql = array("p.pid IN ({$pids})");

		// get forums user cannot view
		$unviewable = get_unviewable_forums(true);
		if($unviewable)
		{
			$sql[] = "p.fid NOT IN ({$unviewable})";
		}

		// get inactive forums
		$inactive = get_inactive_forums();
		if($inactive)
		{
			$sql[] = "p.fid NOT IN ({$inactive})";
		}

		if(!$mybb->user['ismoderator'])
		{
			$sql[] = "p.visible='1'";
			$sql[] = "t.visible='1'";
		}

		$sql = implode(' AND ', $sql);

		$query = $db->query("
            SELECT p.pid, p.uid, p.fid, p.visible, p.message, t.tid, t.subject, t.visible AS thread_visible
            FROM ".TABLE_PREFIX."posts p
            LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
            WHERE {$sql}
        ");

		$forumpermissions = array();

		while($post = $db->fetch_array($query))
		{
			if(($post['visible'] == 0 || $post['thread_visible'] == 0) && !is_moderator($post['fid'], 'canviewunapprove'))
			{
				continue;
			}

			if(($post['visible'] == -1 || $post['thread_visible'] == -1) && !is_moderator($post['fid'], 'canviewdeleted'))
			{
				continue;
			}

			if(!isset($forumpermissions[$post['fid']]))
			{
				$forumpermissions[$post['fid']] = forum_permissions($post['fid']);
			}

			// Make sure we can view this post
			if(isset($forumpermissions[$post['fid']]['canonlyviewownthreads']) && $forumpermissions[$post['fid']]['canonlyviewownthreads'] == 1 && $post['uid'] != $mybb->user['uid'])
			{
				continue;
			}

			$post_reputation[$post['pid']] = $post;
		}
	}

	$reputation_votes = [];
	if(!empty($reputation_cache) && $mybb->user['uid'] != 0)
	{
		$reputation_ids = implode(',', array_column($reputation_cache, 'rid'));
		$query = $db->query("
			SELECT id, reporters FROM ".TABLE_PREFIX."reportedcontent WHERE reportstatus != '1' AND id IN (".$reputation_ids.") AND type = 'reputation'
		");
		while($report = $db->fetch_array($query))
		{
			$reporters = my_unserialize($report['reporters']);
			if(is_array($reporters) && in_array($mybb->user['uid'], $reporters))
			{
				$not_reportable[] =  $report['id'];
			}
		}
	}

	foreach($reputation_cache as $reputation_vote)
	{
		// Get the reputation for the user who posted this comment
		if($reputation_vote['adduid'] == 0)
		{
			$reputation_vote['user_reputation'] = 0;
		}

		$reputation_vote['user_reputation'] = get_reputation($reputation_vote['user_reputation'], $reputation_vote['adduid']);

		// Format the username of this poster
		if(!$reputation_vote['username'])
		{
			$reputation_vote['username'] = $lang->na;
			$reputation_vote['user_reputation'] = '';
		}
		else
		{
			$reputation_vote['username'] = format_name(htmlspecialchars_uni($reputation_vote['username']), $reputation_vote['user_usergroup'], $reputation_vote['user_displaygroup']);
			$reputation_vote['username'] = build_profile_link($reputation_vote['username'], $reputation_vote['uid']);
			$reputation_vote['user_reputation'] = "({$reputation_vote['user_reputation']})";
		}

		$reputation_vote['vote_reputation'] = (int)$reputation_vote['reputation'];

		// This is a negative reputation
		if($reputation_vote['vote_reputation'] < 0)
		{
			$reputation_vote['status_class'] = "trow_reputation_negative";
			$reputation_vote['vote_type_class'] = "reputation_negative";
			$reputation_vote['vote_type'] = $lang->negative;
		}
		elseif($reputation_vote['vote_reputation'] == 0)
		{
			// This is a neutral reputation
			$reputation_vote['status_class'] = "trow_reputation_neutral";
			$reputation_vote['vote_type_class'] = "reputation_neutral";
			$reputation_vote['vote_type'] = $lang->neutral;
		}
		else
		{
			// Otherwise, this is a positive reputation
			$reputation_vote['vote_reputation'] = "+{$reputation_vote['vote_reputation']}";
			$reputation_vote['status_class'] = "trow_reputation_positive";
			$reputation_vote['vote_type_class'] = "reputation_positive";
			$reputation_vote['vote_type'] = $lang->positive;
		}

		// Format the date this reputation was last modified
		$reputation_vote['last_updated'] = my_date('relative', $reputation_vote['dateline']);

		// Is this rating specific to a post?
		$reputation_vote['postrep_perm'] = false;
		if($reputation_vote['pid'])
		{
			if(isset($post_reputation[$reputation_vote['pid']]))
			{
				$reputation_vote['postrep_perm'] = true;
				$thread_link = get_thread_link($post_reputation[$reputation_vote['pid']]['tid']);
				$subject = $parser->parse_badwords($post_reputation[$reputation_vote['pid']]['subject']);

				$reputation_vote['thread_link'] = $lang->sprintf($lang->postrep_given_thread, $thread_link, $subject);
				$reputation_vote['post_link'] = get_post_link($reputation_vote['pid'])."#pid{$reputation_vote['pid']}";
			}
		}

		// Does the current user have permission to delete this reputation? Show delete link
		$reputation_vote['can_delete'] = false;
		if($mybb->usergroup['issupermod'] == 1 || ($mybb->usergroup['candeletereputations'] == 1 && $reputation_vote['adduid'] == $mybb->user['uid'] && $mybb->user['uid'] != 0))
		{
			$reputation_vote['can_delete'] = true;
		}

		if($mybb->user['uid'] != 0 && !in_array($reputation_vote['rid'], $not_reportable))
		{
			$report_link = true;
		}

		// Parse smilies in the reputation vote
		$reputation_parser = array(
			"allow_html" => 0,
			"allow_mycode" => 0,
			"allow_smilies" => 1,
			"allow_imgcode" => 0,
			"filter_badwords" => 1
		);

		$reputation_vote['comments'] = $parser->parse_message($reputation_vote['comments'], $reputation_parser);
		if($reputation_vote['comments'] == '')
		{
			$reputation_vote['comments'] = $lang->no_comment;
		}

		$plugins->run_hooks("reputation_vote");

		$reputation_votes[] = $reputation_vote;
	}

	$plugins->run_hooks("reputation_end");

	output_page(\MyBB\template('reputation/reputation.twig', [
		'user' => $user,
		'multipage' => $multipage,
		'reputation_votes' => $reputation_votes,
		'select' => $select,
		'report_link' => $report_link,
	]));
}
