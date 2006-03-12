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

$templatelist = "poll_newpoll,redirect_pollposted,redirect_pollupdated,redirect_votethanks";
require "./global.php";
require "./inc/functions_post.php";
require "./inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("polls");

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
	$tid = intval($mybb->input['tid']);

	$plugins->run_hooks("polls_newpoll_start");

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='".intval($mybb->input['tid'])."'");
	$thread = $db->fetch_array($query);
	$fid = $thread['fid'];
	$forumpermissions = forum_permissions($fid);
	
	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
	// Make navigation
	makeforumnav($fid);
	addnav($thread['subject'], "showthread.php?tid=$thread[tid]");
	addnav($lang->nav_postpoll);

	if($thread['uid'] != $mybb->user['uid'] && ismod($fid) != "yes")
	{
		$db->query("UPDATE threads SET visible='1' WHERE tid='$tid'");
		nopermission();
	}
	if($forumpermissions['canview'] == "no" || $forumpermissions['canpostthreads'] == "no")
	{
		$db->query("UPDATE ".TABLE_PREFIX."threads SET visible='1' WHERE tid='$tid'");
		nopermission();
	}
	if($forumpermissions['canpostpolls'] == "no")
	{
		$db->query("UPDATE ".TABLE_PREFIX."threads SET visible='1' WHERE tid='$tid'");
		nopermission();
	}
	if($thread['poll'])
	{
		error($lang->error_pollalready);
	}

	if($mybb->settings['maxpolloptions'] && $mybb->input['polloptions'] > $mybb->settings['maxpolloptions'])
	{
		$polloptions = $mybb->settings['maxpolloptions'];
	}
	if($mybb->input['numpolloptions'] > 0)
	{
		$mybb->input['polloptions'] = $mybb->input['numpolloptions'];
	}
	elseif(!$mybb->input['polloptions'])
	{
		$polloptions = 2;
	}
	else
	{
		$polloptions = intval($mybb->input['polloptions']);
	}

	$question = htmlspecialchars_uni($mybb->input['question']);

	$postoptions = $mybb->input['postoptions'];
	if($postoptions['multiple'] == "yes")
	{
		$postoptionschecked['multiple'] = "checked";
	}
	if($postoptions['public'] == "yes")
	{
		$postoptionschecked['public'] = "checked";
	}

	$options = $mybb->input['options'];
	$optionbits = '';
	for($i=1;$i<=$polloptions;$i++)
	{
		$option = $options[$i];
		$option = htmlspecialchars_uni($option);
		eval("\$optionbits .= \"".$templates->get("polls_newpoll_option")."\";");
		$option = "";
	}
	if(!$mybb->input['timeout'])
	{
		$timeout = 0;
	}
	else
	{
		$timeout = $mybb->input['timeout'];
	}

	$plugins->run_hooks("polls_newpoll_end");

	eval("\$newpoll = \"".$templates->get("polls_newpoll")."\";");
	outputpage($newpoll);		
}
if($mybb->input['action'] == "do_newpoll" && $mybb->request_method == "post")
{
	$plugins->run_hooks("polls_do_newpoll_start");

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='".intval($mybb->input['tid'])."'");
	$thread = $db->fetch_array($query);
	$fid = $thread['fid'];
	$forumpermissions = forum_permissions($fid);
	
	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}

	if($thread['uid'] != $mybb->user['uid'] && ismod($fid) != "yes")
	{
		$db->query("UPDATE ".TABLE_PREFIX."threads SET visible='1' WHERE tid='".$thread['tid']."'");
		nopermission();
	}
	if($forumpermissions['canview'] == "no" || $forumpermissions['canpostthreads'] == "no")
	{
		$db->query("UPDATE ".TABLE_PREFIX."threads SET visible='1' WHERE tid='".$thread['tid']."'");
		nopermission();
	}
	if($forumpermissions['canpostpolls'] == "no")
	{
		$db->query("UPDATE ".TABLE_PREFIX."threads SET visible='1' WHERE tid='".$thread['tid']."'");
		nopermission();
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
	for($i=1;$i<=$polloptions;$i++)
	{
		if(trim($options[$i]) != "")
		{
			$optioncount++;
		}
		if(strlen($options[$i]) > $mybb->settings['polloptionlimit'] && $mybb->settings['polloptionlimit'] != 0)
		{
			$lengtherror = 1;
			break;
		}
	}
	if($lengtherror)
	{
		error($lang->error_polloptiontoolong);
	}
	if($mybb->input['question'] == "" || $optioncount < 2)
	{
		error($lang->error_noquestionoptions);
	}
	$optionslist = "";
	$voteslist = "";
	for($i=1;$i<=$optioncount;$i++)
	{
		if(trim($options[$i]) != "")
		{
			if($i > 1)
			{
				$optionslist .= "||~|~||";
				$voteslist .= "||~|~||";
			}
			$optionslist .= "$options[$i]";
			$voteslist .= "0";
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
		"question" => addslashes($mybb->input['question']),
		"dateline" => time(),
		"options" => addslashes($optionslist),
		"votes" => addslashes($voteslist),
		"numoptions" => intval($optioncount),
		"numvotes" => 0,
		"timeout" => $timeout,
		"closed" => "no",
		"multiple" => $postoptions['multiple'],
		"public" => $postoptions['public']
		);

	$plugins->run_hooks("polls_do_newpoll_process");

	$db->insert_query(TABLE_PREFIX."polls", $newpoll);
	$pid = $db->insert_id();

	$db->query("UPDATE ".TABLE_PREFIX."threads SET poll='$pid' WHERE tid='".$thread['tid']."'");

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

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."polls WHERE pid='$pid'");
	$poll = $db->fetch_array($query);

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE poll='$pid'");
	$thread = $db->fetch_array($query);
	$tid = $thread['tid'];

	// Make navigation
	makeforumnav($fid);
	addnav($thread['subject'], "showthread.php?tid=$thread[tid]");
	addnav($lang->nav_editpoll);


	$forumpermissions = forum_permissions($thread['fid']);

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$thread[fid]'");
	$forum = $db->fetch_array($query);
	

	if($thread['visible'] == "no" || !$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
	if(ismod($thread['fid'], "caneditposts") != "yes")
	{
		nopermission();
	}
	$polldate = mydate($mybb->settings['dateformat'], $poll['dateline']);	
	if(!$mybb->input['preview'] && !$mybb->input['updateoptions'])
	{
		if($poll['closed'] == "yes")
		{
			$postoptionschecked['closed'] = "checked";
		}
		if($poll['multiple'] == "yes")
		{
			$postoptionschecked['multiple'] = "checked";
		}
		if($poll['public'] == "yes")
		{
			$postoptionschecked['public'] = "checked";
		}
	
		$optionsarray = explode("||~|~||", $poll['options']);
		$votesarray = explode("||~|~||", $poll['votes']);
	

		for($i=1;$i<=$poll['numoptions'];$i++)
		{
			$poll['totvotes'] = $poll['totvotes'] + $votesarray[$i-1];
		}
		$question = htmlspecialchars_uni($poll['question']);
		$numoptions = $poll['numoptions'];
		$optionbits = "";
		for($i=0;$i<$numoptions;$i++)
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
		if($postoptions['multiple'] == "yes")
		{
			$postoptionschecked['multiple'] = "checked";
		}
		if($postoptions['public'] == "yes")
		{
			$postoptionschecked['public'] = "checked";
		}
		if($postoptions['closed'] == "yes")
		{
			$postoptionschecked['closed'] = "checked";
		}

		$options = $mybb->input['options'];
		$votes = $mybb->input['votes'];
		$optionbits = '';
		for($i=1;$i<=$numoptions;$i++)
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

		if(!$mybb->input['timeout'])
		{
			$timeout = 0;
		}
		else
		{
			$timeout = $mybb->input['timeout'];
		}
	}

	$plugins->run_hooks("polls_editpoll_end");

	eval("\$editpoll = \"".$templates->get("polls_editpoll")."\";");
	outputpage($editpoll);
}
if($mybb->input['action'] == "do_editpoll" && $mybb->request_method == "post")
{
	$plugins->run_hooks("polls_do_editpoll_start");

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."polls WHERE pid='".intval($mybb->input['pid'])."'");
	$poll = $db->fetch_array($query);

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE poll='".intval($mybb->input['pid'])."'");
	$thread = $db->fetch_array($query);

	$forumpermissions = forum_permissions($thread['fid']);

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='".$thread['fid']."'");
	$forum = $db->fetch_array($query);
	
	if($thread['visible'] == "no" || !$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
	if(ismod($thread['fid'], "caneditposts") != "yes")
	{
		nopermission();
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

	for($i=1;$i<=$numoptions;$i++)
	{
		if(trim($options[$i]) != "")
		{
			$optioncount++;
		}
		if(strlen($options[$i]) > $mybb->settings['polloptionlimit'] && $mybb->settings['polloptionlimit'] != 0)
		{
			$lengtherror = 1;
			break;
		}
	}
	if($lengtherror)
	{
		error($lang->error_polloptiontoolong);
	}
	
	if(trim($mybb->input['question']) == "" || $optioncount < 2)
	{
		error($lang->error_noquestionoptions);
	}
	$optionslist = "";
	$voteslist = "";
	$numvotes = "";
	$votes = $mybb->input['votes'];
	for($i=1;$i<=$optioncount;$i++)
	{
		if(trim($options[$i]) != "")
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
		"question" => addslashes($mybb->input['question']),
		"options" => addslashes($optionslist),
		"votes" => addslashes($voteslist),
		"numoptions" => intval($numoptions),
		"numvotes" => $numvotes,
		"timeout" => $timeout,
		"closed" => $postoptions['closed'],
		"multiple" => $postoptions['multiple'],
		"public" => $postoptions['public']
		);

	$plugins->run_hooks("polls_do_editpoll_process");

	$db->update_query(TABLE_PREFIX."polls", $updatedpoll, "pid='".intval($mybb->input['pid'])."'");

	$plugins->run_hooks("polls_do_editpoll_end");

	redirect("showthread.php?tid=".$thread['tid'], $lang->redirect_pollupdated);
}
if($mybb->input['action'] == "showresults")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."polls WHERE pid='".intval($mybb->input['pid'])."'");
	$poll = $db->fetch_array($query);
	$tid = $poll['tid'];
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
	$thread = $db->fetch_array($query);
	$fid = $thread['fid'];
	cacheforums();
	if($forumcache[$fid]['active'] != "no")
	{
		$forum = $forumcache[$fid];
	}
	/*$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$fid'");
	$forum = $db->fetch_array($query);*/
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
	// Check if forum and parents are active
	$parents = explode(",", $forum['parentlist']);
	foreach($parents as $chkfid)
	{
		if($forumcache[$chkfid]['active'] == "no")
		{
			error($lang->error_invalidforum);
		}
	}
	// Make navigation
	makeforumnav($fid);
	addnav($thread['subject'], "showthread.php?tid=$thread[tid]");
	addnav($lang->nav_pollresults);

	$query = $db->query("SELECT v.*, u.username FROM ".TABLE_PREFIX."pollvotes v LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=v.uid) WHERE v.pid='$poll[pid]' ORDER BY u.username");
	while($voter = $db->fetch_array($query))
	{
		if($mybb->user['uid'] == $voter['uid'] && $mybb->user['uid'])
		{
			$votedfor[$voter['voteoption']] = 1;
		}
		$voters[$voter['voteoption']][$voter['uid']] = $voter['username'];
	}
	$optionsarray = explode("||~|~||", $poll['options']);
	$votesarray = explode("||~|~||", $poll['votes']);
	for($i=1;$i<=$poll['numoptions'];$i++)
	{
		$poll['totvotes'] = $poll['totvotes'] + $votesarray[$i-1];
	}
	$polloptions = '';
	for($i=1;$i<=$poll['numoptions'];$i++)
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
		if($votedfor[$number])
		{
			$optionbg = "trow2";
			$votestar = "*";
		}
		else
		{
			$optionbg = "trow1";
			$votestar = "";
		}
		if ($votes == "0")
		{
			$percent = "0";
		}
		else
		{
			$percent = number_format($votes / $poll['totvotes'] * 100, 2);
		}
		$imagewidth = (round($percent)/3) * 5;
		$comma = '';
		$userlist = '';
		if($poll['public'] == "yes")
		{
			if(is_array($voters[$number]))
			{
				while(list($uid, $username) = each($voters[$number]))
				{
					$userlist .= "$comma<a href=\"member.php?action=profile&uid=$uid\">$username</a>";
					$comma = ", ";
				}
			}
		}
		eval("\$polloptions .= \"".$templates->get("polls_showresults_resultbit")."\";");
	}
	if($poll['totvotes'])
	{
		$totpercent = "100%";
	}
	else
	{
		$totpercent = "0%";
	}

	$plugins->run_hooks("polls_showresults_end");

	eval("\$showresults = \"".$templates->get("polls_showresults")."\";");
	outputpage($showresults);
}
if($mybb->input['action'] == "vote")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."polls WHERE pid='".intval($mybb->input['pid'])."'");
	$poll = $db->fetch_array($query);
	$poll['timeout'] = $poll['timeout']*60*60*24;

	$plugins->run_hooks("polls_vote_start");

	if(!$poll['pid'])
	{
		error($lang->error_invalidpoll);
	}

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE poll='".$poll['pid']."'");
	$thread = $db->fetch_array($query);

	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
	$fid = $thread['fid'];
	$forumpermissions = forum_permissions($fid);
	if($forumpermissions['canvotepolls'] == "no")
	{
		nopermission();
	}
	
	$expiretime = $poll['dateline'] + $poll['timeout'];
	$now = time();
	if($poll['closed'] == "yes" || $thread['closed'] == "yes" || ($expiretime < $now && $poll['timeout']))
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
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."pollvotes WHERE uid='".$mybb->user['uid']."' AND pid='".$poll['pid']."'");
		$votecheck = $db->fetch_array($query);
	}
	if($votecheck['vid'] || $_COOKIE['pollvotes'][$poll['pid']])
	{
		error($lang->error_alreadyvoted);
	}
	else
	{
		if(!$mybb->user['uid'])
		{
			mysetcookie("pollvotes[$poll[pid]]", "1", "yes");
		}
	}
	$votesql = '';
	$now = time();
	$votesarray = explode("||~|~||", $poll['votes']);
	$option = $mybb->input['option'];
	$numvotes = $poll['numvotes'];
	if($poll['multiple'] == "yes")
	{
		while(list($voteoption, $vote) = each($option))
		{
			if($vote == "yes" && isset($votesarray[$voteoption-1]))
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
		$votesql = "('".$poll['pid']."','".$mybb->user['uid']."','".addslashes($option)."','$now')";
		$votesarray[$option-1]++;
		$numvotes = $numvotes+1;
	}

	$db->query("INSERT INTO ".TABLE_PREFIX."pollvotes (pid,uid,voteoption,dateline) VALUES $votesql");
	$voteslist = '';
	for($i=1;$i<=$poll['numoptions'];$i++)
	{
		if($i > 1)
		{
			$voteslist .= "||~|~||";
		}
		$voteslist .= $votesarray[$i-1];
	}
	$updatedpoll = array(
		"votes" => addslashes($voteslist),
		"numvotes" => intval($numvotes),
	);

	$plugins->run_hooks("polls_vote_process");

	$db->update_query(TABLE_PREFIX."polls", $updatedpoll, "pid='".$poll['pid']."'");

	$plugins->run_hooks("polls_vote_end");

	redirect("showthread.php?tid=".$poll['tid'], $lang->redirect_votethanks);
}

error($lang->error_invalidaction);
?>