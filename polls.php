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
define('THIS_SCRIPT', 'polls.php');

$templatelist = "changeuserbox,loginbox,polls_newpoll_option,polls_newpoll,polls_editpoll_option,polls_editpoll,polls_showresults_resultbit,polls_showresults";
require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("polls");

$plugins->run_hooks("polls_start");

if($mybb->user['uid'] != 0)
{
	$mybb->user['username'] = htmlspecialchars_uni($mybb->user['username']);
	eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
}
else
{
	eval("\$loginbox = \"".$templates->get("loginbox")."\";");
}

$mybb->input['action'] = $mybb->get_input('action');
if(!empty($mybb->input['updateoptions']))
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
	$tid = $mybb->get_input('tid', MyBB::INPUT_INT);

	$plugins->run_hooks("polls_newpoll_start");

	$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
	if(!$thread || $thread['visible'] == -1)
	{
		error($lang->error_invalidthread);
	}

	// Is the currently logged in user a moderator of this forum?
	$ismod = is_moderator($thread['fid']);

	// Make sure we are looking at a real thread here.
	if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
	{
		error($lang->error_invalidthread);
	}

	$fid = $thread['fid'];
	$forumpermissions = forum_permissions($fid);

	// Get forum info
	$forum = get_forum($fid);
	if(!$forum)
	{
		error($lang->error_invalidforum);
	}
	else
	{
		// Is our forum closed?
		if($forum['open'] == 0 && !is_moderator($fid, "canmanagepolls"))
		{
			// Doesn't look like it is
			error($lang->error_closedinvalidforum);
		}
	}
	// Make navigation
	build_forum_breadcrumb($fid);
	add_breadcrumb(htmlspecialchars_uni($thread['subject']), get_thread_link($thread['tid']));
	add_breadcrumb($lang->nav_postpoll);

	// No permission if: Not thread author; not moderator; no forum perms to view, post threads, post polls
	if(($thread['uid'] != $mybb->user['uid'] && !is_moderator($fid, "canmanagepolls")) || ($forumpermissions['canview'] == 0 || $forumpermissions['canpostthreads'] == 0 || $forumpermissions['canpostpolls'] == 0))
	{
		error_no_permission();
	}

	if($thread['poll'])
	{
		error($lang->error_pollalready);
	}

	$time = TIME_NOW;
	if($thread['dateline'] < ($time-($mybb->settings['polltimelimit']*60*60)) && $mybb->settings['polltimelimit'] != 0 && $ismod == false)
	{
		$lang->poll_time_limit = $lang->sprintf($lang->poll_time_limit, $mybb->settings['polltimelimit']);
		error($lang->poll_time_limit);
	}

	// Sanitize number of poll options
	if($mybb->get_input('numpolloptions', MyBB::INPUT_INT) > 0)
	{
		$mybb->input['polloptions'] = $mybb->get_input('numpolloptions', MyBB::INPUT_INT);
	}
	if($mybb->settings['maxpolloptions'] && $mybb->get_input('polloptions', MyBB::INPUT_INT) > $mybb->settings['maxpolloptions'])
	{	// Too big
		$polloptions = $mybb->settings['maxpolloptions'];
	}
	elseif($mybb->get_input('polloptions', MyBB::INPUT_INT) < 2)
	{	// Too small
		$polloptions = 2;
	}
	else
	{	// Just right
		$polloptions = $mybb->get_input('polloptions', MyBB::INPUT_INT);
	}

	$question = htmlspecialchars_uni($mybb->get_input('question'));

	$postoptionschecked = array('public' => '', 'multiple' => '');
	$postoptions = $mybb->get_input('postoptions', MyBB::INPUT_INT);
	if(isset($postoptions['multiple']) && $postoptions['multiple'] == 1)
	{
		$postoptionschecked['multiple'] = 'checked="checked"';
	}
	if(isset($postoptions['public']) && $postoptions['public'] == 1)
	{
		$postoptionschecked['public'] = 'checked="checked"';
	}

	$options = $mybb->get_input('options', MyBB::INPUT_ARRAY);
	$optionbits = '';
	for($i = 1; $i <= $polloptions; ++$i)
	{
		if(!isset($options[$i]))
		{
			$options[$i] = '';
		}
		$option = $options[$i];
		$option = htmlspecialchars_uni($option);
		eval("\$optionbits .= \"".$templates->get("polls_newpoll_option")."\";");
		$option = "";
	}

	if($mybb->get_input('timeout', MyBB::INPUT_INT) > 0)
	{
		$timeout = $mybb->get_input('timeout', MyBB::INPUT_INT);
	}
	else
	{
		$timeout = 0;
	}

	if($mybb->get_input('maxoptions', MyBB::INPUT_INT) > 0 && $mybb->get_input('maxoptions', MyBB::INPUT_INT) < $polloptions)
	{
		$maxoptions = $mybb->get_input('maxoptions', MyBB::INPUT_INT);
	}
	else
	{
		$maxoptions = 0;
	}

	$plugins->run_hooks("polls_newpoll_end");

	eval("\$newpoll = \"".$templates->get("polls_newpoll")."\";");
	output_page($newpoll);
}
if($mybb->input['action'] == "do_newpoll" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("polls_do_newpoll_start");

	$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
	if(!$thread)
	{
		error($lang->error_invalidthread);
	}

	$fid = $thread['fid'];
	$forumpermissions = forum_permissions($fid);

	// Get forum info
	$forum = get_forum($fid);
	if(!$forum)
	{
		error($lang->error_invalidforum);
	}
	else
	{
		// Is our forum closed?
		if($forum['open'] == 0 && !is_moderator($fid, "canmanagepolls"))
		{
			// Doesn't look like it is
			error($lang->error_closedinvalidforum);
		}
	}

	// No permission if: Not thread author; not moderator; no forum perms to view, post threads, post polls
	if(($thread['uid'] != $mybb->user['uid'] && !is_moderator($fid, "canmanagepolls")) || ($forumpermissions['canview'] == 0 || $forumpermissions['canpostthreads'] == 0 || $forumpermissions['canpostpolls'] == 0))
	{
		error_no_permission();
	}

	if($thread['poll'])
	{
		error($lang->error_pollalready);
	}

	$polloptions = $mybb->get_input('polloptions', MyBB::INPUT_INT);
	if($mybb->settings['maxpolloptions'] && $polloptions > $mybb->settings['maxpolloptions'])
	{
		$polloptions = $mybb->settings['maxpolloptions'];
	}

	$postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
	if(!isset($postoptions['multiple']) || $postoptions['multiple'] != '1')
	{
		$postoptions['multiple'] = 0;
	}

	if(!isset($postoptions['public']) || $postoptions['public'] != '1')
	{
		$postoptions['public'] = 0;
	}

	if($polloptions < 2)
	{
		$polloptions = "2";
	}
	$optioncount = "0";
	$options = $mybb->get_input('options', MyBB::INPUT_ARRAY);

	for($i = 1; $i <= $polloptions; ++$i)
	{
		if(!isset($options[$i]))
		{
			$options[$i] = '';
		}

		if($mybb->settings['polloptionlimit'] != 0 && my_strlen($options[$i]) > $mybb->settings['polloptionlimit'])
		{
			$lengtherror = 1;
			break;
		}

		if(strpos($options[$i], '||~|~||') !== false)
		{
			$sequenceerror = 1;
			break;
		}
		
		if(trim($options[$i]) != "")
		{
			$optioncount++;
		}
	}

	if(isset($lengtherror))
	{
		error($lang->error_polloptiontoolong);
	}

	if(isset($sequenceerror))
	{
		error($lang->error_polloptionsequence);
	}
	
	$mybb->input['question'] = $mybb->get_input('question');

	if(trim($mybb->input['question']) == '' || $optioncount < 2)
	{
		error($lang->error_noquestionoptions);
	}

	$optionslist = '';
	$voteslist = '';
	for($i = 1; $i <= $polloptions; ++$i)
	{
		if(trim($options[$i]) != '')
		{
			if($optionslist != '')
			{
				$optionslist .= '||~|~||';
				$voteslist .= '||~|~||';
			}
			$optionslist .= trim($options[$i]);
			$voteslist .= '0';
		}
	}

	if($mybb->get_input('timeout', MyBB::INPUT_INT) > 0)
	{
		$timeout = $mybb->get_input('timeout', MyBB::INPUT_INT);
	}
	else
	{
		$timeout = 0;
	}

	if($mybb->get_input('maxoptions', MyBB::INPUT_INT) > 0 && $mybb->get_input('maxoptions', MyBB::INPUT_INT) < $polloptions)
	{
		$maxoptions = $mybb->get_input('maxoptions', MyBB::INPUT_INT);
	}
	else
	{
		$maxoptions = 0;
	}

	$newpoll = array(
		"tid" => $thread['tid'],
		"question" => $db->escape_string($mybb->input['question']),
		"dateline" => TIME_NOW,
		"options" => $db->escape_string($optionslist),
		"votes" => $db->escape_string($voteslist),
		"numoptions" => (int)$optioncount,
		"numvotes" => 0,
		"timeout" => $timeout,
		"closed" => 0,
		"multiple" => $postoptions['multiple'],
		"public" => $postoptions['public'],
		"maxoptions" => $maxoptions
	);

	$plugins->run_hooks("polls_do_newpoll_process");

	$pid = $db->insert_query("polls", $newpoll);

	$db->update_query("threads", array('poll' => $pid), "tid='".$thread['tid']."'");

	$plugins->run_hooks("polls_do_newpoll_end");

	if($thread['visible'] == 1)
	{
		redirect(get_thread_link($thread['tid']), $lang->redirect_pollposted);
	}
	else
	{
		redirect(get_forum_link($thread['fid']), $lang->redirect_pollpostedmoderated);
	}
}

if($mybb->input['action'] == "editpoll")
{
	$pid = $mybb->get_input('pid', MyBB::INPUT_INT);

	$plugins->run_hooks("polls_editpoll_start");

	$query = $db->simple_select("polls", "*", "pid='$pid'");
	$poll = $db->fetch_array($query);

	if(!$poll)
	{
		error($lang->error_invalidpoll);
	}

	$query = $db->simple_select("threads", "*", "poll='$pid'");
	$thread = $db->fetch_array($query);
	if(!$thread)
	{
		error($lang->error_invalidthread);
	}

	$tid = $thread['tid'];
	$fid = $thread['fid'];

	// Make navigation
	build_forum_breadcrumb($fid);
	add_breadcrumb(htmlspecialchars_uni($thread['subject']), get_thread_link($thread['tid']));
	add_breadcrumb($lang->nav_editpoll);

	$forumpermissions = forum_permissions($fid);

	// Get forum info
	$forum = get_forum($fid);
	if(!$forum)
	{
		error($lang->error_invalidforum);
	}
	else
	{
		// Is our forum closed?
		if($forum['open'] == 0 && !is_moderator($fid, "canmanagepolls"))
		{
			// Doesn't look like it is
			error($lang->error_closedinvalidforum);
		}
	}

	if(!is_moderator($fid, "canmanagepolls"))
	{
		error_no_permission();
	}

	$postoptionschecked = array('closed' => '', 'multiple' => '', 'public' => '');

	$polldate = my_date($mybb->settings['dateformat'], $poll['dateline']);
	if(empty($mybb->input['updateoptions']))
	{
		if($poll['closed'] == 1)
		{
			$postoptionschecked['closed'] = 'checked="checked"';
		}

		if($poll['multiple'] == 1)
		{
			$postoptionschecked['multiple'] = 'checked="checked"';
		}

		if($poll['public'] == 1)
		{
			$postoptionschecked['public'] = 'checked="checked"';
		}

		$optionsarray = explode("||~|~||", $poll['options']);
		$votesarray = explode("||~|~||", $poll['votes']);

		$poll['totvotes'] = 0;
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
			$optionvotes = (int)$votesarray[$i];

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

		if(!$poll['maxoptions'])
		{
			$maxoptions = 0;
		}
		else
		{
			$maxoptions = $poll['maxoptions'];
		}
	}
	else
	{
		if($mybb->settings['maxpolloptions'] && $mybb->get_input('numoptions', MyBB::INPUT_INT) > $mybb->settings['maxpolloptions'])
		{
			$numoptions = $mybb->settings['maxpolloptions'];
		}
		elseif($mybb->get_input('numoptions', MyBB::INPUT_INT) < 2)
		{
			$numoptions = 2;
		}
		else
		{
			$numoptions = $mybb->get_input('numoptions', MyBB::INPUT_INT);
		}
		$question = htmlspecialchars_uni($mybb->input['question']);

		$postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
		if(isset($postoptions['multiple']) && $postoptions['multiple'] == 1)
		{
			$postoptionschecked['multiple'] = 'checked="checked"';
		}

		if(isset($postoptions['public']) && $postoptions['public'] == 1)
		{
			$postoptionschecked['public'] = 'checked="checked"';
		}

		if(isset($postoptions['closed']) && $postoptions['closed'] == 1)
		{
			$postoptionschecked['closed'] = 'checked="checked"';
		}

		$options = $mybb->get_input('options', MyBB::INPUT_ARRAY);
		$votes = $mybb->get_input('votes', MyBB::INPUT_ARRAY);
		$optionbits = '';
		for($i = 1; $i <= $numoptions; ++$i)
		{
			$counter = $i;
			if(!isset($options[$i]))
			{
				$options[$i] = '';
			}
			$option = htmlspecialchars_uni($options[$i]);
			if(!isset($votes[$i]))
			{
				$votes[$i] = 0;
			}
			$optionvotes = (int)$votes[$i];

			if(!$optionvotes)
			{
				$optionvotes = 0;
			}

			eval("\$optionbits .= \"".$templates->get("polls_editpoll_option")."\";");
			$option = "";
		}

		if($mybb->get_input('timeout', MyBB::INPUT_INT) > 0)
		{
			$timeout = $mybb->get_input('timeout', MyBB::INPUT_INT);
		}
		else
		{
			$timeout = 0;
		}

		if(!$poll['maxoptions'])
		{
			$maxoptions = 0;
		}
		else
		{
			$maxoptions = $poll['maxoptions'];
		}
	}

	$plugins->run_hooks("polls_editpoll_end");

	eval("\$editpoll = \"".$templates->get("polls_editpoll")."\";");
	output_page($editpoll);
}

if($mybb->input['action'] == "do_editpoll" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("polls_do_editpoll_start");

	$query = $db->simple_select("polls", "*", "pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'");
	$poll = $db->fetch_array($query);

	if(!$poll)
	{
		error($lang->error_invalidpoll);
	}

	$query = $db->simple_select("threads", "*", "poll='".$mybb->get_input('pid', MyBB::INPUT_INT)."'");
	$thread = $db->fetch_array($query);
	if(!$thread)
	{
		error($lang->error_invalidthread);
	}

	$forumpermissions = forum_permissions($thread['fid']);

	// Get forum info
	$forum = get_forum($thread['fid']);
	$fid = $thread['fid'];
	if(!$forum)
	{
		error($lang->error_invalidforum);
	}
	else
	{
		// Is our forum closed?
		if($forum['open'] == 0 && !is_moderator($fid, "canmanagepolls"))
		{
			// Doesn't look like it is
			error($lang->error_closedinvalidforum);
		}
	}

	if(!is_moderator($thread['fid'], "canmanagepolls"))
	{
		error_no_permission();
	}

	if($mybb->settings['maxpolloptions'] && $mybb->get_input('numoptions', MyBB::INPUT_INT) > $mybb->settings['maxpolloptions'])
	{
		$numoptions = $mybb->settings['maxpolloptions'];
	}
	elseif($mybb->get_input('numoptions', MyBB::INPUT_INT) < 2)
	{
		$numoptions = 2;
	}
	else
	{
		$numoptions = $mybb->get_input('numoptions', MyBB::INPUT_INT);
	}

	$postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
	if(!isset($postoptions['multiple']) || $postoptions['multiple'] != '1')
	{
		$postoptions['multiple'] = 0;
	}

	if(!isset($postoptions['public']) || $postoptions['public'] != '1')
	{
		$postoptions['public'] = 0;
	}

	if(!isset($postoptions['closed']) || $postoptions['closed'] != '1')
	{
		$postoptions['closed'] = 0;
	}
	$optioncount = "0";
	$options = $mybb->input['options'];

	for($i = 1; $i <= $numoptions; ++$i)
	{
		if(!isset($options[$i]))
		{
			$options[$i] = '';
		}

		if($mybb->settings['polloptionlimit'] != 0 && my_strlen($options[$i]) > $mybb->settings['polloptionlimit'])
		{
			$lengtherror = 1;
			break;
		}

		if(strpos($options[$i], '||~|~||') !== false)
		{
			$sequenceerror = 1;
			break;
		}
		
		if(trim($options[$i]) != "")
		{
			$optioncount++;
		}
	}

	if(isset($lengtherror))
	{
		error($lang->error_polloptiontoolong);
	}
	
	if(isset($sequenceerror))
	{
		error($lang->error_polloptionsequence);
	}

	$mybb->input['question'] = $mybb->get_input('question');
	if(trim($mybb->input['question']) == '' || $optioncount < 2)
	{
		error($lang->error_noquestionoptions);
	}

	$optionslist = '';
	$voteslist = '';
	$numvotes = '';
	$votes = $mybb->input['votes'];
	for($i = 1; $i <= $numoptions; ++$i)
	{
		if(trim($options[$i]) != '')
		{
			if($optionslist != '')
			{
				$optionslist .= "||~|~||";
				$voteslist .= "||~|~||";
			}

			$optionslist .= trim($options[$i]);
			if(!isset($votes[$i]) || (int)$votes[$i] <= 0)
			{
				$votes[$i] = "0";
			}
			$voteslist .= $votes[$i];
			$numvotes = (int)$numvotes + (int)$votes[$i];
		}
	}

	if($mybb->get_input('timeout', MyBB::INPUT_INT) > 0)
	{
		$timeout = $mybb->get_input('timeout', MyBB::INPUT_INT);
	}
	else
	{
		$timeout = 0;
	}

	if($mybb->get_input('maxoptions', MyBB::INPUT_INT) > 0 && $mybb->get_input('maxoptions', MyBB::INPUT_INT) < $numoptions)
	{
		$maxoptions = $mybb->get_input('maxoptions', MyBB::INPUT_INT);
	}
	else
	{
		$maxoptions = 0;
	}

	$updatedpoll = array(
		"question" => $db->escape_string($mybb->input['question']),
		"options" => $db->escape_string($optionslist),
		"votes" => $db->escape_string($voteslist),
		"numoptions" => (int)$optioncount,
		"numvotes" => $numvotes,
		"timeout" => $timeout,
		"closed" => $postoptions['closed'],
		"multiple" => $postoptions['multiple'],
		"public" => $postoptions['public'],
		"maxoptions" => $maxoptions
	);

	$plugins->run_hooks("polls_do_editpoll_process");

	$db->update_query("polls", $updatedpoll, "pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'");

	$plugins->run_hooks("polls_do_editpoll_end");

	$modlogdata['fid'] = $thread['fid'];
	$modlogdata['tid'] = $thread['tid'];
	log_moderator_action($modlogdata, $lang->poll_edited);

	redirect(get_thread_link($thread['tid']), $lang->redirect_pollupdated);
}

if($mybb->input['action'] == "showresults")
{
	$query = $db->simple_select("polls", "*", "pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'");
	$poll = $db->fetch_array($query);

	if(!$poll)
	{
		error($lang->error_invalidpoll);
	}

	$tid = $poll['tid'];
	$thread = get_thread($tid);
	if(!$thread || ($thread['visible'] != 1 && ($thread['visible'] == 0 && !is_moderator($thread['fid'], "canviewunapprove")) || ($thread['visible'] == -1 && !is_moderator($thread['fid'], "canviewdeleted"))))
	{
		error($lang->error_invalidthread);
	}

	$fid = $thread['fid'];

	// Get forum info
	$forum = get_forum($fid);
	if(!$forum)
	{
		error($lang->error_invalidforum);
	}

	$forumpermissions = forum_permissions($forum['fid']);

	$plugins->run_hooks("polls_showresults_start");

	if($forumpermissions['canviewthreads'] == 0 || $forumpermissions['canview'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
	{
		error_no_permission();
	}

	// Make navigation
	build_forum_breadcrumb($fid);
	add_breadcrumb(htmlspecialchars_uni($thread['subject']), get_thread_link($thread['tid']));
	add_breadcrumb($lang->nav_pollresults);

	$voters = $votedfor = array();

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
			$voters[$voter['voteoption']][$voter['uid']] = htmlspecialchars_uni($voter['username']);
		}
	}

	$optionsarray = explode("||~|~||", $poll['options']);
	$votesarray = explode("||~|~||", $poll['votes']);
	$poll['totvotes'] = 0;
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
			"allow_imgcode" => $forum['allowimgcode'],
			"allow_videocode" => $forum['allowvideocode'],
			"filter_badwords" => 1
		);
		$option = $parser->parse_message($optionsarray[$i-1], $parser_options);

		$votes = $votesarray[$i-1];
		$number = $i;
		// Make the mark for current user's voted option
		if(!empty($votedfor[$number]))
		{
			$optionbg = 'trow2';
			$votestar = '*';
		}
		else
		{
			$optionbg = 'trow1';
			$votestar = '';
		}

		if($votes == 0)
		{
			$percent = 0;
		}
		else
		{
			$percent = number_format($votes / $poll['totvotes'] * 100, 2);
		}

		$imagewidth = round($percent);
		$comma = '';
		$guest_comma = '';
		$userlist = '';
		$guest_count = 0;
		if($poll['public'] == 1 || is_moderator($fid, "canmanagepolls"))
		{
			if(isset($voters[$number]) && is_array($voters[$number]))
			{
				foreach($voters[$number] as $uid => $username)
				{
					$userlist .= $comma.build_profile_link($username, $uid);
					$comma = $guest_comma = $lang->comma;
				}
			}

			if(isset($guest_voters[$number]) && $guest_voters[$number] > 0)
			{
				if($guest_voters[$number] == 1)
				{
					$userlist .= $guest_comma.$lang->guest_count;
				}
				else
				{
					$userlist .= $guest_comma.$lang->sprintf($lang->guest_count_multiple, $guest_voters[$number]);
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

	$poll['question'] = htmlspecialchars_uni($poll['question']);
	eval("\$showresults = \"".$templates->get("polls_showresults")."\";");
	output_page($showresults);
}

if($mybb->input['action'] == "vote" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$query = $db->simple_select("polls", "*", "pid='".$mybb->get_input('pid')."'");
	$poll = $db->fetch_array($query);

	if(!$poll)
	{
		error($lang->error_invalidpoll);
	}

	$plugins->run_hooks("polls_vote_start");

	$poll['timeout'] = $poll['timeout']*60*60*24;

	$query = $db->simple_select("threads", "*", "poll='".(int)$poll['pid']."'");
	$thread = $db->fetch_array($query);

	if(!$thread || ($thread['visible'] != 1 && ($thread['visible'] == 0 && !is_moderator($thread['fid'], "canviewunapprove")) || ($thread['visible'] == -1 && !is_moderator($thread['fid'], "canviewdeleted"))))
	{
		error($lang->error_invalidthread);
	}

	$fid = $thread['fid'];
	$forumpermissions = forum_permissions($fid);
	if($forumpermissions['canvotepolls'] == 0)
	{
		error_no_permission();
	}

	// Get forum info
	$forum = get_forum($fid);
	if(!$forum)
	{
		error($lang->error_invalidforum);
	}
	else
	{
		// Is our forum closed?
		if($forum['open'] == 0)
		{
			// Doesn't look like it is
			error($lang->error_closedinvalidforum);
		}
	}

	$expiretime = $poll['dateline'] + $poll['timeout'];
	$now = TIME_NOW;
	if($poll['closed'] == 1 || $thread['closed'] == 1 || ($expiretime < $now && $poll['timeout']))
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
		$user_check = "uid='{$mybb->user['uid']}'";
	}
	else
	{
		$user_check = "ipaddress=".$db->escape_binary($session->packedip);
	}

	$query = $db->simple_select("pollvotes", "*", "{$user_check} AND pid='".$poll['pid']."'");
	$votecheck = $db->fetch_array($query);

	if($votecheck['vid'])
	{
		error($lang->error_alreadyvoted);
	}

	$votesql = array();
	$votesarray = explode("||~|~||", $poll['votes']);
	$option = $mybb->input['option'];
	$numvotes = (int)$poll['numvotes'];
	if($poll['multiple'] == 1)
	{
		if(is_array($option))
		{
			$total_options = 0;

			foreach($option as $voteoption => $vote)
			{
				if($vote == 1 && isset($votesarray[$voteoption-1]))
				{
					$votesql[] = array(
						"pid" => $poll['pid'],
						"uid" => (int)$mybb->user['uid'],
						"voteoption" => $db->escape_string($voteoption),
						"dateline" => TIME_NOW,
						"ipaddress" => $db->escape_binary($session->packedip)
					);

					$votesarray[$voteoption-1]++;
					$numvotes = $numvotes+1;
					$total_options++;
				}
			}

			if($total_options > $poll['maxoptions'] && $poll['maxoptions'] != 0)
			{
				error($lang->sprintf($lang->error_maxpolloptions, $poll['maxoptions']));
			}
		}
	}
	else
	{
		if(is_array($option) || !isset($votesarray[$option-1]))
		{
			error($lang->error_nopolloptions);
		}

		$votesql = array(
			"pid" => $poll['pid'],
			"uid" => (int)$mybb->user['uid'],
			"voteoption" => $db->escape_string($option),
			"dateline" => TIME_NOW,
			"ipaddress" => $db->escape_binary($session->packedip)
		);

		$votesarray[$option-1]++;
		$numvotes = $numvotes+1;
	}

	if(!$votesql)
	{
		error($lang->error_nopolloptions);
	}

	if($poll['multiple'] == 1)
	{
		$db->insert_query_multiple("pollvotes", $votesql);
	}
	else
	{
		$db->insert_query("pollvotes", $votesql);
	}

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
		"numvotes" => (int)$numvotes,
	);

	$plugins->run_hooks("polls_vote_process");

	$db->update_query("polls", $updatedpoll, "pid='".$poll['pid']."'");

	$plugins->run_hooks("polls_vote_end");

	redirect(get_thread_link($poll['tid']), $lang->redirect_votethanks);
}

if($mybb->input['action'] == "do_undovote")
{
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['canundovotes'] != 1)
	{
		error_no_permission();
	}

	$query = $db->simple_select("polls", "*", "pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'");
	$poll = $db->fetch_array($query);

	if(!$poll['pid'])
	{
		error($lang->error_invalidpoll);
	}

	$plugins->run_hooks("polls_do_undovote_start");

	$poll['numvotes'] = (int)$poll['numvotes'];

	// We do not have $forum_cache available here since no forums permissions are checked in undo vote
	// Get thread ID and then get forum info
	$thread = get_thread($poll['tid']);
	if(!$thread || ($thread['visible'] != 1 && ($thread['visible'] == 0 && !is_moderator($thread['fid'], "canviewunapprove")) || ($thread['visible'] == -1 && !is_moderator($thread['fid'], "canviewdeleted"))))
	{
		error($lang->error_invalidthread);
	}

	$fid = $thread['fid'];

	// Get forum info
	$forum = get_forum($fid);
	if(!$forum)
	{
		error($lang->error_invalidforum);
	}
	else
	{
		// Is our forum closed?
		if($forum['open'] == 0)
		{
			// Doesn't look like it is
			error($lang->error_closedinvalidforum);
		}
	}

	$poll['timeout'] = $poll['timeout']*60*60*24;


	$expiretime = $poll['dateline'] + $poll['timeout'];
	if($poll['closed'] == 1 || $thread['closed'] == 1 || ($expiretime < TIME_NOW && $poll['timeout']))
	{
		error($lang->error_pollclosed);
	}

	// Check if the user has voted before...
	$vote_options = array();

	if($mybb->user['uid'])
	{
		$user_check = "uid='{$mybb->user['uid']}'";
	}
	else
	{
		$user_check = "uid='0' AND ipaddress=".$db->escape_binary($session->packedip);
	}

	$query = $db->simple_select("pollvotes", "vid,voteoption", "{$user_check} AND pid='".$poll['pid']."'");
	while($voteoption = $db->fetch_array($query))
	{
		$vote_options[$voteoption['vid']] = $voteoption['voteoption'];
	}

	if(empty($vote_options))
	{
		error($lang->error_notvoted);
	}

	// Note, this is not thread safe!
	$votesarray = explode("||~|~||", $poll['votes']);
	if(count($votesarray) > $poll['numoptions'])
	{
		$votesarray = array_slice(0, $poll['numoptions']);
	}

	if($poll['multiple'] == 1)
	{
		foreach($vote_options as $vote)
		{
			if(isset($votesarray[$vote-1]))
			{
				--$votesarray[$vote-1];
				--$poll['numvotes'];
			}
		}
	}
	else
	{
		$voteoption = reset($vote_options);
		if(isset($votesarray[$voteoption-1]))
		{
			--$votesarray[$voteoption-1];
			--$poll['numvotes'];
		}
	}

	// check if anything < 0 - possible if Guest vote undoing is allowed (generally Guest unvoting should be disabled >_>)
	if($poll['numvotes'] < 0)
	{
		$poll['numvotes'] = 0;
	}

	foreach($votesarray as $i => $votes)
	{
		if($votes < 0)
		{
			$votesarray[$i] = 0;
		}
	}

	$voteslist = implode("||~|~||", $votesarray);
	$updatedpoll = array(
		"votes" => $db->escape_string($voteslist),
		"numvotes" => (int)$poll['numvotes'],
	);

	$plugins->run_hooks("polls_do_undovote_process");

	$db->delete_query("pollvotes", "{$user_check} AND pid='".$poll['pid']."'");
	$db->update_query("polls", $updatedpoll, "pid='".$poll['pid']."'");

	$plugins->run_hooks("polls_do_undovote_end");

	redirect(get_thread_link($poll['tid']), $lang->redirect_unvoted);
}
