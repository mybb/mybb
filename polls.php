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

define("IN_MYBB", 1);

$templatelist = "poll_newpoll,redirect_pollposted,redirect_pollupdated,redirect_votethanks";
require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("polls");
//$lang->load("global");

if($mybb->user['uid'] != 0)
{
	eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
}
else
{
	eval("\$loginbox = \"".$templates->get("loginbox")."\";");
}

if($mybb->input['preview'] || $mybb->input['updateoptions'])
{
	if($mybb->input['action'] == "do_editpoll")
	{
		$mybb->input['action'] = "editpoll";
	}
	else
	{
		$mybb->input['action'] = "newpoll";
	}
}
if($mybb->input['action'] == "newpoll")
{
	// Form for new poll
	$tid = intval($mybb->input['tid']);

	$plugins->run_hooks("polls_newpoll_start");

	$query = $db->simple_select("threads", "*", "tid='".intval($mybb->input['tid'])."'");
	$thread = $db->fetch_array($query);
	$fid = $thread['fid'];
	$forumpermissions = forum_permissions($fid);

	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
	// Make navigation
	build_forum_breadcrumb($fid);
	add_breadcrumb(htmlspecialchars_uni($thread['subject']), "showthread.php?tid={$thread['tid']}");
	add_breadcrumb($lang->nav_postpoll);

	// No permission if: Not thread author; not moderator; no forum perms to view, post threads, post polls
	if(($thread['uid'] != $mybb->user['uid'] && is_moderator($fid) != "yes") || ($forumpermissions['canview'] == "no" || $forumpermissions['canpostthreads'] == "no" || $forumpermissions['canpostpolls'] == "no"))
	{
		error_no_permission();
	}

	if($thread['poll'])
	{
		error($lang->error_pollalready);
	}

	// Sanitize number of poll options
	if($mybb->input['numpolloptions'] > 0)
	{
		$mybb->input['polloptions'] = $mybb->input['numpolloptions'];
	}
	if($mybb->settings['maxpolloptions'] && $mybb->input['polloptions'] > $mybb->settings['maxpolloptions'])
	{	// too big
		$polloptions = $mybb->settings['maxpolloptions'];
	}
	elseif($mybb->input['polloptions'] < 2)
	{	// too small
		$polloptions = 2;
	}
	else
	{	// just right
		$polloptions = intval($mybb->input['polloptions']);
	}

	$question = htmlspecialchars_uni($mybb->input['question']);

	$postoptions = $mybb->input['postoptions'];
	if($postoptions['multiple'] == "yes")
	{
		$postoptionschecked['multiple'] = 'checked="checked"';
	}
	if($postoptions['public'] == "yes")
	{
		$postoptionschecked['public'] = 'checked="checked"';
	}

	$options = $mybb->input['options'];
	$optionbits = '';
	for($i = 1; $i <= $polloptions; ++$i)
	{
		$option = $options[$i];
		$option = htmlspecialchars_uni($option);
		eval("\$optionbits .= \"".$templates->get("polls_newpoll_option")."\";");
		$option = "";
	}

	if($mybb->input['timeout'] > 0)
	{
		$timeout = intval($mybb->input['timeout']);
	}
	else
	{
		$timeout = 0;
	}

	$plugins->run_hooks("polls_newpoll_end");

	eval("\$newpoll = \"".$templates->get("polls_newpoll")."\";");
	output_page($newpoll);
}
if($mybb->input['action'] == "do_newpoll" && $mybb->request_method == "post")
{
	$plugins->run_hooks("polls_do_newpoll_start");

	$query = $db->simple_select("threads", "*", "tid='".intval($mybb->input['tid'])."'");
	$thread = $db->fetch_array($query);
	$fid = $thread['fid'];
	$forumpermissions = forum_permissions($fid);

	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}

	// No permission if: Not thread author; not moderator; no forum perms to view, post threads, post polls
	if(($thread['uid'] != $mybb->user['uid'] && is_moderator($fid) != "yes") || ($forumpermissions['canview'] == "no" || $forumpermissions['canpostthreads'] == "no" || $forumpermissions['canpostpolls'] == "no"))
	{
		error_no_permission();
	}

	if($thread['poll'])
	{
		error($lang->error_pollalready);
	}

	$polloptions = $mybb->input['polloptions'];
	if($mybb->settings['maxpolloptions'] && $polloptions > $mybb->settings['maxpolloptions'])
	{
		$polloptions = $mybb->settings['maxpolloptions'];
	}

	$postoptions = $mybb->input['postoptions'];
	if($postoptions['multiple'] != "yes")
	{
		$postoptions['multiple'] = "no";
	}

	if($postoptions['public'] != "yes")
	{
		$postoptions['public'] = "no";
	}
	if($polloptions < 2)
	{
		$polloptions = "2";
	}
	$optioncount = "0";
	$options = $mybb->input['options'];
	for($i = 1; $i <= $polloptions; ++$i)
	{
		if(trim($options[$i]) != "")
		{
			$optioncount++;
		}
		if(my_strlen($options[$i]) > $mybb->settings['polloptionlimit'] && $mybb->settings['polloptionlimit'] != 0)
		{
			$lengtherror = 1;
			break;
		}
	}
	if($lengtherror)
	{
		error($lang->error_polloptiontoolong);
	}
	if(empty($mybb->input['question']) || $optioncount < 2)
	{
		error($lang->error_noquestionoptions);
	}
	$optionslist = '';
	$voteslist = '';
	for($i = 1; $i <= $optioncount; ++$i)
	{
		if(trim($options[$i]) != '')
		{
			if($i > 1)
			{
				$optionslist .= '||~|~||';
				$voteslist .= '||~|~||';
			}
			$optionslist .= $options[$i];
			$voteslist .= '0';
		}
	}
	if($mybb->input['timeout'] > 0)
	{
		$timeout = intval($mybb->input['timeout']);
	}
	else
	{
		$timeout = 0;
	}
	$newpoll = array(
		"tid" => $thread['tid'],
		"question" => $db->escape_string($mybb->input['question']),
		"dateline" => time(),
		"options" => $db->escape_string($optionslist),
		"votes" => $db->escape_string($voteslist),
		"numoptions" => intval($optioncount),
		"numvotes" => 0,
		"timeout" => $timeout,
		"closed" => "no",
		"multiple" => $postoptions['multiple'],
		"public" => $postoptions['public']
		);

	$plugins->run_hooks("polls_do_newpoll_process");

	$db->insert_query("polls", $newpoll);
	$pid = $db->insert_id();

	$db->update_query("threads", array('poll' => $pid), "tid='".$thread['tid']."'");

	$plugins->run_hooks("polls_do_newpoll_end");

	if($thread['visible'] == 1)
	{
		redirect("showthread.php?tid=".$thread['tid'], $lang->redirect_pollposted);
	}
	else
	{
		redirect("forumdisplay.php?fid=".$thread['fid'], $lang->redirect_pollpostedmoderated);
	}
}
if($mybb->input['action'] == "editpoll")
{
	$pid = intval($mybb->input['pid']);

	$plugins->run_hooks("polls_editpoll_start");

	$query = $db->simple_select("polls", "*", "pid='$pid'");
	$poll = $db->fetch_array($query);

	$query = $db->simple_select("threads", "*", "poll='$pid'");
	$thread = $db->fetch_array($query);
	$tid = $thread['tid'];
	$fid = $thread['fid'];

	// Make navigation
	build_forum_breadcrumb($fid);
	add_breadcrumb(htmlspecialchars_uni($thread['subject']), "showthread.php?tid=$tid");
	add_breadcrumb($lang->nav_editpoll);


	$forumpermissions = forum_permissions($fid);

	$query = $db->simple_select("forums", "*", "fid='$fid'");
	$forum = $db->fetch_array($query);


	if($thread['visible'] == "0" || !$tid)
	{
		error($lang->error_invalidthread);
	}
	if(is_moderator($fid, "caneditposts") != "yes")
	{
		error_no_permission();
	}
	$polldate = my_date($mybb->settings['dateformat'], $poll['dateline']);
	if(!$mybb->input['preview'] && !$mybb->input['updateoptions'])
	{
		if($poll['closed'] == 'yes')
		{
			$postoptionschecked['closed'] = 'checked="checked"';
		}
		if($poll['multiple'] == 'yes')
		{
			$postoptionschecked['multiple'] = 'checked="checked"';
		}
		if($poll['public'] == 'yes')
		{
			$postoptionschecked['public'] = 'checked="checked"';
		}

		$optionsarray = explode("||~|~||", $poll['options']);
		$votesarray = explode("||~|~||", $poll['votes']);


		for($i = 1; $i <= $poll['numoptions']; ++$i)
		{
			$poll['totvotes'] = $poll['totvotes'] + $votesarray[$i-1];
		}
		$question = htmlspecialchars_uni($poll['question']);
		$numoptions = $poll['numoptions'];
		$optionbits = "";
		for($i = 0; $i < $numoptions; ++$i)
		{
			$counter = $i + 1;
			$option = $optionsarray[$i];
			$option = htmlspecialchars_uni($option);
			$optionvotes = intval($votesarray[$i]);
			if(!$optionvotes)
			{
				$optionvotes = 0;
			}
			eval("\$optionbits .= \"".$templates->get("polls_editpoll_option")."\";");
			$option = "";
			$optionvotes = "";
		}
		if(!$poll['timeout'])
		{
			$timeout = 0;
		}
		else
		{
			$timeout = $poll['timeout'];
		}
	}
	else
	{
		if($mybb->settings['maxpolloptions'] && $mybb->input['numoptions'] > $mybb->settings['maxpolloptions'])
		{
			$numoptions = $mybb->settings['maxpolloptions'];
		}
		elseif($mybb->input['numoptions'] < 2)
		{
			$numoptions = "2";
		}
		else
		{
			$numoptions = $mybb->input['numoptions'];
		}
		$question = htmlspecialchars_uni($mybb->input['question']);

		$postoptions = $mybb->input['postoptions'];
		if($postoptions['multiple'] == 'yes')
		{
			$postoptionschecked['multiple'] = 'checked="checked"';
		}
		if($postoptions['public'] == 'yes')
		{
			$postoptionschecked['public'] = 'checked="checked"';
		}
		if($postoptions['closed'] == 'yes')
		{
			$postoptionschecked['closed'] = 'checked="checked"';
		}

		$options = $mybb->input['options'];
		$votes = $mybb->input['votes'];
		$optionbits = '';
		for($i = 1; $i <= $numoptions; ++$i)
		{
			$counter = $i;
			$option = $options[$i];
			$option = htmlspecialchars_uni($option);
			$optionvotes = $votes[$i];
			if(!$optionvotes)
			{
				$optionvotes = 0;
			}
			eval("\$optionbits .= \"".$templates->get("polls_editpoll_option")."\";");
			$option = "";
		}

		if($mybb->input['timeout'] > 0)
		{
			$timeout = $mybb->input['timeout'];
		}
		else
		{
			$timeout = 0;
		}
	}

	$plugins->run_hooks("polls_editpoll_end");

	eval("\$editpoll = \"".$templates->get("polls_editpoll")."\";");
	output_page($editpoll);
}
if($mybb->input['action'] == "do_editpoll" && $mybb->request_method == "post")
{
	$plugins->run_hooks("polls_do_editpoll_start");

	$query = $db->simple_select("polls", "*", "pid='".intval($mybb->input['pid'])."'");
	$poll = $db->fetch_array($query);

	$query = $db->simple_select("threads", "*", "poll='".intval($mybb->input['pid'])."'");
	$thread = $db->fetch_array($query);

	$forumpermissions = forum_permissions($thread['fid']);

	$query = $db->simple_select("forums", "*", "fid='".$thread['fid']."'");
	$forum = $db->fetch_array($query);

	if($thread['visible'] == "no" || !$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
	if(is_moderator($thread['fid'], "caneditposts") != "yes")
	{
		error_no_permission();
	}

	if($mybb->settings['maxpolloptions'] && $mybb->input['numoptions'] > $mybb->settings['maxpolloptions'])
	{
		$numoptions = $mybb->settings['maxpolloptions'];
	}
	elseif(!$mybb->input['numoptions'])
	{
		$numoptions = 2;
	}
	else
	{
		$numoptions = $mybb->input['numoptions'];
	}

	$postoptions = $mybb->input['postoptions'];
	if($postoptions['multiple'] != "yes")
	{
		$postoptions['multiple'] = "no";
	}
	if($postoptions['public'] != "yes")
	{
		$postoptions['public'] = "no";
	}
	if($postoptions['closed'] != "yes")
	{
		$postoptions['closed'] = "no";
	}
	$optioncount = "0";
	$options = $mybb->input['options'];

	for($i = 1; $i <= $numoptions; ++$i)
	{
		if(trim($options[$i]) != '')
		{
			$optioncount++;
		}
		if(my_strlen($options[$i]) > $mybb->settings['polloptionlimit'] && $mybb->settings['polloptionlimit'] != 0)
		{
			$lengtherror = 1;
			break;
		}
	}
	if($lengtherror)
	{
		error($lang->error_polloptiontoolong);
	}

	if(trim($mybb->input['question']) == '' || $optioncount < 2)
	{
		error($lang->error_noquestionoptions);
	}
	$optionslist = '';
	$voteslist = '';
	$numvotes = '';
	$votes = $mybb->input['votes'];
	for($i = 1; $i <= $optioncount; ++$i)
	{
		if(trim($options[$i]) != '')
		{
			if($i > 1)
			{
				$optionslist .= "||~|~||";
				$voteslist .= "||~|~||";
			}
			$optionslist .= $options[$i];
			if(intval($votes[$i]) <= 0)
			{
				$votes[$i] = "0";
			}
			$voteslist .= $votes[$i];
			$numvotes = $numvotes + $votes[$i];
		}
	}
	if($mybb->input['timeout'] > 0)
	{
		$timeout = intval($mybb->input['timeout']);
	}
	else
	{
		$timeout = 0;
	}
	$updatedpoll = array(
		"question" => $db->escape_string($mybb->input['question']),
		"options" => $db->escape_string($optionslist),
		"votes" => $db->escape_string($voteslist),
		"numoptions" => intval($numoptions),
		"numvotes" => $numvotes,
		"timeout" => $timeout,
		"closed" => $postoptions['closed'],
		"multiple" => $postoptions['multiple'],
		"public" => $postoptions['public']
	);

	$plugins->run_hooks("polls_do_editpoll_process");

	$db->update_query("polls", $updatedpoll, "pid='".intval($mybb->input['pid'])."'");

	$plugins->run_hooks("polls_do_editpoll_end");

	redirect("showthread.php?tid=".$thread['tid'], $lang->redirect_pollupdated);
}
if($mybb->input['action'] == "showresults")
{
	$query = $db->simple_select("polls", "*", "pid='".intval($mybb->input['pid'])."'");
	$poll = $db->fetch_array($query);
	$tid = $poll['tid'];
	$query = $db->simple_select("threads", "*", "tid='$tid'");
	$thread = $db->fetch_array($query);
	$fid = $thread['fid'];

	// Get forum info
	$forum = get_forum($fid);
	if(!$forum)
	{
		error($lang->error_invalidforum);
	}

	$forumpermissions = forum_permissions($forum['fid']);

	$plugins->run_hooks("polls_showresults_start");

	if($forumpermissions['canviewthreads'] == "no" || $forumpermissions['canview'] == "no")
	{
		error($lang->error_pollpermissions);
	}
	if(!$poll['pid'])
	{
		error($lang->error_invalidpoll);
	}
	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}

	// Make navigation
	build_forum_breadcrumb($fid);
	add_breadcrumb(htmlspecialchars_uni($thread['subject']), "showthread.php?tid={$thread['tid']}");
	add_breadcrumb($lang->nav_pollresults);

	$voters = array();

	// Calculate votes
	$query = $db->query("
		SELECT v.*, u.username 
		FROM ".TABLE_PREFIX."pollvotes v 
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=v.uid) 
		WHERE v.pid='{$poll['pid']}' 
		ORDER BY u.username
	");
	while($voter = $db->fetch_array($query))
	{
		// Mark for current user's vote
		if($mybb->user['uid'] == $voter['uid'] && $mybb->user['uid'])
		{
			$votedfor[$voter['voteoption']] = 1;
		}

		// Count number of guests and users without a username (assumes they've been deleted)
		if($voter['uid'] == 0 || $voter['username'] == '')
		{
			// Add one to the number of voters for guests
			++$guest_voters[$voter['voteoption']];
		}
		else
		{
			$voters[$voter['voteoption']][$voter['uid']] = $voter['username'];
		}
	}
	$optionsarray = explode("||~|~||", $poll['options']);
	$votesarray = explode("||~|~||", $poll['votes']);
	for($i = 1; $i <= $poll['numoptions']; ++$i)
	{
		$poll['totvotes'] = $poll['totvotes'] + $votesarray[$i-1];
	}
	$polloptions = '';
	for($i = 1; $i <= $poll['numoptions']; ++$i)
	{
		$parser_options = array(
			"allow_html" => $forum['allowhtml'],
			"allow_mycode" => $forum['allowmycode'],
			"allow_smilies" => $forum['allowsmilies'],
			"allow_imgcode" => $forum['allowimgcode']
		);
		$option = $parser->parse_message($optionsarray[$i-1], $parser_options);

		$votes = $votesarray[$i-1];
		$number = $i;
		// Make the mark for current user's voted option
		if($votedfor[$number])
		{
			$optionbg = 'trow2';
			$votestar = '*';
		}
		else
		{
			$optionbg = 'trow1';
			$votestar = '';
		}
		if($votes == '0')
		{
			$percent = '0';
		}
		else
		{
			$percent = number_format($votes / $poll['totvotes'] * 100, 2);
		}
		$imagewidth = round($percent/3) * 5;
		$comma = '';
		$guest_comma = '';
		$userlist = '';
		$guest_count = 0;
		if($poll['public'] == 'yes')
		{
			if(is_array($voters[$number]))
			{
				foreach($voters[$number] as $uid => $username)
				{
					$userlist .= $comma.build_profile_link($username, $uid);
					$comma = $guest_comma = ', ';
				}
			}

			if($guest_voters[$number] > 0)
			{
				if($guest_voters[$number] == 1)
				{
					$userlist .= $guest_comma.$lang->guest_count;
				}
				else
				{
					$userlist .= $guest_comma.sprintf($lang->guest_count_multiple, $guest_voters[$number]);
				}
			}
		}
		eval("\$polloptions .= \"".$templates->get("polls_showresults_resultbit")."\";");
	}
	if($poll['totvotes'])
	{
		$totpercent = '100%';
	}
	else
	{
		$totpercent = '0%';
	}

	$plugins->run_hooks("polls_showresults_end");

	eval("\$showresults = \"".$templates->get("polls_showresults")."\";");
	output_page($showresults);
}
if($mybb->input['action'] == "vote")
{
	$query = $db->simple_select("polls", "*", "pid='".intval($mybb->input['pid'])."'");
	$poll = $db->fetch_array($query);
	$poll['timeout'] = $poll['timeout']*60*60*24;

	$plugins->run_hooks("polls_vote_start");

	if(!$poll['pid'])
	{
		error($lang->error_invalidpoll);
	}

	$query = $db->simple_select("threads", "*", "poll='".$poll['pid']."'");
	$thread = $db->fetch_array($query);

	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
	$fid = $thread['fid'];
	$forumpermissions = forum_permissions($fid);
	if($forumpermissions['canvotepolls'] == 'no')
	{
		error_no_permission();
	}

	$expiretime = $poll['dateline'] + $poll['timeout'];
	$now = time();
	if($poll['closed'] == 'yes' || $thread['closed'] == 'yes' || ($expiretime < $now && $poll['timeout']))
	{
		error($lang->error_pollclosed);
	}
	if(!isset($mybb->input['option']))
	{
		error($lang->error_nopolloptions);
	}
	// Check if the user has voted before...
	if($mybb->user['uid'])
	{
		$query = $db->simple_select("pollvotes", "*", "uid='".$mybb->user['uid']."' AND pid='".$poll['pid']."'");
		$votecheck = $db->fetch_array($query);
	}
	if($votecheck['vid'] || $_COOKIE['pollvotes'][$poll['pid']])
	{
		error($lang->error_alreadyvoted);
	}
	elseif(!$mybb->user['uid'])
	{
		// Give a cookie to guests to inhibit revotes
		my_setcookie("pollvotes[{$poll['pid']}]", '1', 'yes');
	}
	$votesql = '';
	$now = time();
	$votesarray = explode("||~|~||", $poll['votes']);
	$option = $mybb->input['option'];
	$numvotes = $poll['numvotes'];
	if($poll['multiple'] == 'yes')
	{
		foreach($option as $voteoption => $vote)
		{
			if($vote == 'yes' && isset($votesarray[$voteoption-1]))
			{
				if($votesql)
				{
					$votesql .= ",";
				}
				$votesql .= "('".$poll['pid']."','".$mybb->user['uid']."','$voteoption','$now')";
				$votesarray[$voteoption-1]++;
				$numvotes = $numvotes+1;
			}
		}
	}
	else
	{
		if(!isset($votesarray[$option-1]))
		{
			error($lang->error_nopolloptions);
		}
		$votesql = "('".$poll['pid']."','".$mybb->user['uid']."','".$db->escape_string($option)."','$now')";
		$votesarray[$option-1]++;
		$numvotes = $numvotes+1;
	}

	$db->query("
		INSERT INTO 
		".TABLE_PREFIX."pollvotes (pid,uid,voteoption,dateline) 
		VALUES $votesql
	");
	$voteslist = '';
	for($i = 1; $i <= $poll['numoptions']; ++$i)
	{
		if($i > 1)
		{
			$voteslist .= "||~|~||";
		}
		$voteslist .= $votesarray[$i-1];
	}
	$updatedpoll = array(
		"votes" => $db->escape_string($voteslist),
		"numvotes" => intval($numvotes),
	);

	$plugins->run_hooks("polls_vote_process");

	$db->update_query("polls", $updatedpoll, "pid='".$poll['pid']."'");

	$plugins->run_hooks("polls_vote_end");

	redirect("showthread.php?tid=".$poll['tid'], $lang->redirect_votethanks);
}

?>
