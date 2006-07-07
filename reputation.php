<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

$templatesused = '';
require "./global.php";

require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("reputation");

// Check if the reputation system is globally disabled or not.
if($mybb->settings['enablereputation'] != "yes")
{
	error($lang->reputation_disabled);
}

// Does this user have permission to view the board?
if($mybb->usergroup['canview'] != "yes")
{
	error_no_permission();
}

// If we have a specified incoming username, validate it and fetch permissions for it
$user = get_user($mybb->input['uid']);
if(!$user['uid'])
{
	error("invalid_user");
}
$user_permissions = user_permissions(intval($mybb->input['uid']));

$show_back = '0';

// Here we perform our validation when adding a reputation to see if the user
// has permission or not. This is done here to save duplicating the same code.
if($mybb->input['action'] == "add" || $mybb->input['action'] == "do_add")
{
	// This user doesn't have permission to give reputations.
	if($mybb->usergroup['cangivereputations'] != "yes")
	{
		$message = $lang->add_no_permission;
		eval("\$error = \"".$templates->get("reputation_add_error")."\";");
		output_page($error);		
		exit;
	}
	
	// The user we're trying to give a reputation to doesn't have permission to receive reps.
	if($user_permissions['usereputationsystem'] != "yes")
	{
		$message = $lang->add_disabled;
		eval("\$error = \"".$templates->get("reputation_add_error")."\";");
		output_page($error);		
		exit;
	}
	
	// Is this user trying to give themself a reputation?
	if($mybb->input['uid'] == $mybb->user['uid'])
	{
		$message = $lang->add_yours;
		eval("\$error = \"".$templates->get("reputation_add_error")."\";");
		output_page($error);		
		exit;
	}
	
	// Check if this user has reached their "maximum reputations per day" quota
	if($mybb->usergroup['maxreputationsday'] != 0 && ($mybb->input['action'] != "do_add" || ($mybb->input['action'] == "do_add" && !$mybb->input['delete'])))
	{
		$timesearch = time() - (60 * 60 * 24);
		$query = $db->simple_select(TABLE_PREFIX."reputation", "*", "adduid='".$mybb->user['uid']."' AND dateline>'$timesearch'");
		$numtoday = $db->num_rows($query);
		
		// Reached the quota - error.
		if($numtoday >= $mybb->usergroup['maxreputationsday'])
		{
			$message = $lang->add_maxperday;
			eval("\$error = \"".$templates->get("reputation_add_error")."\";");
			output_page($error);		
			exit;
		}
	}
	
	// Fetch the existing reputation for this user given by our current user if there is one.
	$query = $db->simple_select(TABLE_PREFIX."reputation", "*", "adduid='".$mybb->user['uid']."' AND uid='".intval($mybb->input['uid'])."'");
	$existing_reputation = $db->fetch_array($query);			
}

// Saving the new reputation
if($mybb->input['action'] == "do_add" && $mybb->request_method == "post")
{
	$plugins->run_hooks("reputation_do_add_start");
	
	// Check if the reputation power they're trying to give is within their "power limit"
	$reputation = $mybb->input['reputation'];
	$reputation = intval(str_replace("-", "", $reputation));
	if($mybb->input['reputation'] == "neutral")
	{
		$mybb->input['reputation'] = 0;
	}

	// Deleting our current reputation of this user.
	if($mybb->input['delete'])
	{
		$db->delete_query(TABLE_PREFIX."reputation", "uid='".intval($mybb->input['uid'])."' AND adduid='".$mybb->user['uid']."'");
		
		// Recount the reputation of this user - keep it in sync.
		$query = $db->simple_select(TABLE_PREFIX."reputation", "SUM(reputation) AS reputation_count", "uid='".intval($mybb->input['uid'])."'");
		$reputation_value = $db->fetch_field($query, "reputation_count");

		$db->update_query(TABLE_PREFIX."users", array('reputation' => intval($reputation_value)), "uid='".intval($mybb->input['uid'])."'");
		eval("\$error = \"".$templates->get("reputation_deleted")."\";");
		output_page($error);
		exit;
	}

	if(trim($mybb->input['comments']) == "" || my_strlen($mybb->input['comments']) < 10)
	{
		$show_back = 1;
		$message = $lang->add_no_comment;
		eval("\$error = \"".$templates->get("reputation_add_error")."\";");
		output_page($error);
		exit;
	}
	
	// The power for the reputation they specified was invalid.
	if($reputation > $mybb->usergroup['reputationpower'] || !is_numeric($mybb->input['reputation']))
	{
		$show_back = 1;
		$message = $lang->add_invalidpower;
		eval("\$error = \"".$templates->get("reputation_add_error")."\";");
		output_page($error);	
		exit;
	}
	
	// Build array of reputation data.
	$reputation = array(
		"uid" => intval($mybb->input['uid']),
		"adduid" => $mybb->user['uid'],
		"reputation" => $db->escape_string($mybb->input['reputation']),
		"dateline" => time(),
		"comments" => $db->escape_string($mybb->input['comments'])
	);
	
	$plugins->run_hooks("reputation_do_add_process");
		
	// Updating an existing reputation
	if($existing_reputation['uid'])
	{
		$db->update_query(TABLE_PREFIX."reputation", $reputation, "rid='".$existing_reputation['rid']."'");

		// Recount the reputation of this user - keep it in sync.
		$query = $db->simple_select(TABLE_PREFIX."reputation", "SUM(reputation) AS reputation_count", "uid='".intval($mybb->input['uid'])."'");
		$reputation_value = $db->fetch_field($query, "reputation_count");

		$db->update_query(TABLE_PREFIX."users", array('reputation' => intval($reputation_value)), "uid='".intval($mybb->input['uid'])."'");
		
		$lang->vote_added = $lang->vote_updated;
		$lang->vote_added_message = $lang->vote_updated_message;
	}
	// Insert a new reputation
	else
	{
		$db->insert_query(TABLE_PREFIX."reputation", $reputation);
		
		// Recount the reputation of this user - keep it in sync.
		$query = $db->simple_select(TABLE_PREFIX."reputation", "SUM(reputation) AS reputation_count", "uid='".intval($mybb->input['uid'])."'");
		$reputation_value = $db->fetch_field($query, "reputation_count");

		$db->update_query(TABLE_PREFIX."users", array('reputation' => intval($reputation_value)), "uid='".intval($mybb->input['uid'])."'");
	}
	
	$plugins->run_hooks("reputation_do_add_end");


	eval("\$reputation = \"".$templates->get("reputation_added")."\";");
	output_page($reputation);
}

// Adding a new reputation
if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("reputation_add_start");
	
	// If we have an existing reputation for this user, the user can modify or delete it.
	if($existing_reputation['uid'])
	{
		$vote_title = sprintf($lang->update_reputation_vote, $user['username']);
		$vote_button = $lang->update_vote;
		$comments = htmlspecialchars_uni($existing_reputation['comments']);
		$delete_button = "<input type=\"submit\" name=\"delete\" value=\"{$lang->delete_vote}\" />";
	}
	// Otherwise we're adding an entirely new reputation for this user.
	else
	{
		$vote_title = sprintf($lang->add_reputation_vote, $user['username']);
		$vote_button = $lang->add_vote;
		$comments = '';
		$delete_button = '';
	}
	$lang->user_comments = sprintf($lang->user_comments, $user['username']);

	// Draw the "power" options
	$positive_power = '';
	$negative_power = '';
	$vote_check = '';
	if($existing_reputation['uid'])
	{
		$vote_check[$existing_reputation['reputation']] = "checked=\"checked\"";
	}
	$neutral_power = "&nbsp;&nbsp;<input type=\"radio\" name=\"reputation\" value=\"neutral\" id=\"neutral\" {$vote_check[0]} /> <label for=\"neutral\">{$lang->power_neutral}</label><br />";
	$reputationpower = $mybb->usergroup['reputationpower'];
	for($i = 1; $i <= $reputationpower; $i++)
	{
		$positive_title = sprintf($lang->power_positive, "+".$i);
		$positive_power = "&nbsp;&nbsp;<input type=\"radio\" name=\"reputation\" value=\"+{$i}\" id=\"pos{$i}\" {$vote_check[+$i]} /> <label for=\"pos{$i}\">{$positive_title}</label><br />".$positive_power;
		$negative_title = sprintf($lang->power_negative, "-".$i);
		$negative_power .= "&nbsp;&nbsp;<input type=\"radio\" name=\"reputation\" value=\"-{$i}\" id=\"neg{$i}\" {$vote_check[-$i]} /> <label for=\"neg{$i}\">{$negative_title}</label><br />";
	}
	
	eval("\$reputation_add = \"".$templates->get("reputation_add")."\";");
	$plugins->run_hooks("reputation_add_end");
	output_page($reputation_add);
}

// Delete a specific reputation from a user.
if($mybb->input['action'] == "delete")
{
	// Only administrators as well as users who gave a specifc vote can delete one.
	if($mybb->usergroup['cancp'] != "yes" && $existing_reputation['adduid'] != $mybb->user['uid'])
	{
		error_no_permission();
	}

	// Delete the specified reputation
	$db->delete_query(TABLE_PREFIX."reputation", "uid='".intval($mybb->input['uid'])."'AND rid='".intval($mybb->input['rid'])."'");
	
	// Recount the reputation of this user - keep it in sync.
	$query = $db->simple_select(TABLE_PREFIX."reputation", "SUM(reputation) AS reputation_count", "uid='".intval($mybb->input['uid'])."'");
	$reputation_value = $db->fetch_field($query, "reputation_count");

	$db->update_query(TABLE_PREFIX."users", array('reputation' => intval($reputation_value)), "uid='".intval($mybb->input['uid'])."'");

	redirect("reputation.php?uid=".intval($mybb->input['uid']), $lang->vote_deleted_message);
}

// Otherwise, show a listing of reputations for the given user.
if(!$mybb->input['action'])
{
	$lang->nav_profile = sprintf($lang->nav_profile, $user['username']);
	$lang->reputation_report = sprintf($lang->reputation_report, $user['username']);
	
	// Format the user name using the group username style
	$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);

	// Set display group to their user group if they don't have a display group.
	if(!$user['displaygroup'])
	{
		$user['displaygroup'] = $user['usergroup'];
	}
	
	// Fetch display group properties.
	$display_group = usergroup_displaygroup($user['displaygroup']);

	// This user has a custom user title
	if($user['usertitle'] != '')
	{
		$usertitle = $user['usertitle'];
	}
	// Using our display group's user title
	else if($display_group['usertitle'] != '')
	{
		$usertitle = $display_group['usertitle'];
	}
	// Otherwise, fetch it from our titles table for the number of posts this user has
	else
	{
		$query = $db->simple_select(TABLE_PREFIX."usertitles", "*", "posts<='{$user['postnum']}'", array('order_by' => 'posts', 'order_dir' => 'DESC'));
		$title = $db->fetch_array($query);
		$usertitle = $title['title'];
	}	

	// If the user has permission to add reputations - show the image
	if($mybb->usergroup['cangivereputations'] == "yes")
	{
		eval("\$add_reputation = \"".$templates->get("reputation_addlink")."\";");
	}
	else
	{
		$add_reputation = '';
	}
	
	// Build navigation menu
	add_breadcrumb($lang->nav_profile, "member.php?action=profile&uid={$user['uid']}");
	add_breadcrumb($lang->nav_reputation);
	
	// Check our specified conditionals for what type of reputations to show
	$show_select = '';
	switch($mybb->input['show'])
	{
		case "positive":
			$conditions = 'AND r.reputation>0';
			$show_selected['positive'] = 'selected="selected"';
			break;
		case "neutral":
			$conditions = 'AND r.reputation=0';
			$show_selected['neutral'] = 'selected="selected"';
			break;
		case "negative":
			$conditions = 'AND r.reputation<0';
			$show_selected['negative'] = 'selected="selected"';
			break;
		default:
			$conditions = '';
			$show_select['all'] = 'selected="selected"';
			break;
	}
	
	// Check the sorting options for the reputation list
	$sort_select = '';
	switch($mybb->input['sort'])
	{
		case "username":
			$order = "u.username ASC";
			$sort_selected['username'] = 'selected="selected"';
			break;
		default:
			$order = "r.dateline DESC";
			$sort_selected['last_updated'] = 'selected="selected"';
			break;
	}
	// Fetch the total number of reputations for this user
	$query = $db->simple_select(TABLE_PREFIX."reputation r", "COUNT(r.rid) AS reputation_count", "r.uid='{$user['uid']}' $conditions");
	$reputation_count = $db->fetch_field($query, "reputation_count");
	
	// Set default count variables to 0
	$positive_count = $negative_count = $neutral_count = 0;
	$positive_week = $negative_week = $neutral_week = 0;
	$positive_month = $negative_month = $neutral_month = 0;
	$positive_6months = $negative_6months = $neutral_6months = 0;
	
	// Unix timestamps for when this week, month and last 6 months started
	$last_week = time()-604800;
	$last_month = time()-2678400;
	$last_6months = time()-16070400;

	// Query reputations for the "reputation card" 
	$query = $db->simple_select(TABLE_PREFIX."reputation", "reputation, dateline", "uid='{$user['uid']}'");
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
		// Negative reputation given
		else if($reputation_vote['reputation'] < 0)
		{
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
		// Neutral reputation given
		else
		{
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

	// Check if we're browsing a specific page of results
	if(intval($mybb->input['page']) > 0)
	{
		$page = $mybb->input['page'];
		$start = ($page-1) *$mybb->settings['repsperpage'];
		$pages = $reputation_count / $mybb->settings['repsperpage'];
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
	
	// Build out multipage navigation
	if($reputation_count > 0)
	{
		$multipage = multipage($reputation_count, $mybb->settings['repsperpage'], $page, "reputation.php?uid={$user['uid']}");
	}
	
	// Fetch the reputations which will be displayed on this page
	$query = $db->query("
		SELECT r.*, r.uid AS rated_uid, u.uid, u.username, u.reputation AS user_reputation, u.usergroup AS user_usergroup, u.displaygroup AS user_displaygroup
		FROM ".TABLE_PREFIX."reputation r
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=r.adduid)
		WHERE r.uid='{$user['uid']}' $conditions
		ORDER BY $order
		LIMIT $start, {$mybb->settings['repsperpage']}
	");
	while($reputation_vote = $db->fetch_array($query))
	{
		// Format the username of this poster
		$reputation_vote['username'] = format_name($reputation_vote['username'], $reputation_vote['user_usergroup'], $reputation_vote['user_displaygroup']);
		$reputation_vote['username'] = build_profile_link($reputation_vote['username'], $reputation_vote['uid']);
		
		// This is a negative reputation
		if($reputation_vote['reputation'] < 0)
		{
			$status_class = "trow_reputation_negative";
			$vote_type_class = "reputation_negative";
			$vote_type = $lang->negative;
		}
		// This is a neutral reputation
		else if($reputation_vote['reputation'] == 0)
		{
			$status_class = "trow_reputation_neutral";
			$vote_type_class = "reputation_neutral";
			$vote_type = $lang->neutral;
		}
		// Otherwise, this is a positive reputation
		else
		{
			$status_class = "trow_reputation_positive";
			$vote_type_class = "reputation_positive";
			$vote_type = $lang->positive;
		}
		// Get the reputation for the user who posted this comment
		$reputation_vote['user_reputation'] = get_reputation($reputation_vote['user_reputation'], $reputation_vote['adduid']);
		
		// Format the date this reputation was last modified
		$last_updated_date = mydate($settings['dateformat'], $reputation_vote['dateline']);
		$last_updated_time = mydate($settings['timeformat'], $reputation_vote['dateline']);
		$last_updated = sprintf($lang->last_updated, $last_updated_date, $last_updated_time);
		
		// Does the current user have permission to delete this reputation? Show delete link
		if($mybb->usergroup['cancp'] == "yes" || ($mybb->usergroup['cangivereputations'] == "yes" && $reputation['adduid'] == $mybb->user['uid']))
		{
			$delete_link = "[<a href=\"javascript:MyBB.deleteReputation({$reputation_vote['rated_uid']}, {$reputation_vote['rid']});\">{$lang->delete_vote}</a>]";
		}
		else
		{
			$delete_link = '';
		}

		// Parse smilies in the reputation vote
		$reputation_parser = array(
			"allow_html" => "no",
			"allow_mycode" => "no",
			"allow_smilies" => "yes",
			"allow_imgcode" => "no"
		);

		$reputation_vote['comments'] = $parser->parse_message($reputation_vote['comments'], $reputation_parser);
		eval("\$reputation_votes .= \"".$templates->get("reputation_vote")."\";");
	}
	
	// If we don't have any reputations display a nice message.
	if(!$reputation_votes)
	{
		eval("\$reputation_votes = \"".$templates->get("reputation_no_votes")."\";");
	}
	
	eval("\$reputation = \"".$templates->get("reputation")."\";");
	$plugins->run_hooks("reputation_end");
	output_page($reputation);
}
?>