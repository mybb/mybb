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
 define("KILL_GLOBALS", 1);


$templatelist = "showthread,postbit,showthread_newthread,showthread_newreply,showthread_newreply_closed,showthread_newpoll,postbit_sig,showthread_newpoll,postbit_avatar,postbit_profile,postbit_find,postbit_pm,postbit_www,postbit_email,postbit_edit,postbit_quote,postbit_report,postbit_signature, postbit_online,postbit_offline,postbit_away,showthread_ratingdisplay,showthread_ratethread,showthread_moderationoptions";
$templatelist .= ",multipage_prevpage,multipage_nextpage,multipage_page_current,multipage_page,multipage_start,multipage_end,multipage";
$templatelist .= ",postbit_editedby,showthread_similarthreads,showthread_similarthreads_bit,showthread_moderationoptions.postbit_iplogged_show,postbit_iplogged_hiden,showthread_quickreply";
$templatelist .= ",forumjump_advanced,forumjump_special,forumjump_bit,showthread_multipage,postbit_reputation,postbit_quickdelete,postbit_attachments,thumbnails_thumbnail,postbit_attachments_attachment,postbit_attachments,postbit_attachments_thumbnails,postbit_attachments_images_image,postbit_attachments_images,postbit_posturl";
$templatelist .= ",postbit_inlinecheck,showthread_inlinemoderation,postbit_attachments_thumbnails_thumbnail";

require "./global.php";
require "./inc/functions_post.php";

// Load global language phrases
$lang->load("showthread");

if($mybb->input['pid'] && !$mybb->input['tid']) {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE pid='".intval($mybb->input['pid'])."'");
	$post = $db->fetch_array($query);
	$mybb->input['tid'] = $post['tid'];
}

$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE tid='".intval($mybb->input['tid'])."' AND closed NOT LIKE 'moved|%'");
$thread = $db->fetch_array($query);
$thread['subject'] = htmlspecialchars_uni(dobadwords($thread['subject']));
$tid = $thread['tid'];
$fid = $thread['fid'];


if(ismod($fid) == "yes") {
	$ismod = true;
}
else
{
	$ismod = false;
}

if(!$thread['tid'] || ($thread['visible'] == 0 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true)) {
	error($lang->error_invalidthread);
}

// Make navigation
makeforumnav($fid);
addnav($thread['subject'], "showthread.php?tid=$tid");


$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='".$thread[fid]."' AND active!='no'");
$forum = $db->fetch_array($query);

$forumpermissions = forum_permissions($forum['fid']);

if($forum['type'] != "f") {
	error($lang->error_invalidforum);
}
if($forumpermissions['canview'] != "yes") {
	nopermission();
}

// Password protected forums
checkpwforum($forum['fid'], $forum['password']);

if(!$mybb->input['action']) {
	$mybb->input['action'] = "thread";
}

if($mybb->input['action'] == "lastpost") {
	if(strstr($thread['closed'], "moved|")) {
		$query = $db->query("SELECT p.pid FROM ".TABLE_PREFIX."posts p, ".TABLE_PREFIX."threads t WHERE t.fid='".$thread[fid]."' AND t.closed NOT LIKE 'moved|%' AND p.tid=t.tid ORDER BY p.dateline DESC LIMIT 0, 1");
		$pid = $db->result($query, 0);
	} else {
		$query = $db->query("SELECT pid FROM ".TABLE_PREFIX."posts WHERE tid='$tid' ORDER BY dateline DESC LIMIT 0, 1");
		$pid = $db->result($query, 0);
	}
	header("Location:showthread.php?tid=$tid&pid=$pid#pid$pid");
	exit;
}
if($mybb->input['action'] == "nextnewest") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE fid='".$thread[fid]."' AND lastpost > '".$thread[lastpost]."' AND visible='1' AND closed NOT LIKE 'moved|%' ORDER BY lastpost LIMIT 0, 1");
	$nextthread = $db->fetch_array($query);
	if(!$nextthread['tid']) {
		error($lang->error_nonextnewest);
	}
	$query = $db->query("SELECT pid FROM ".TABLE_PREFIX."posts WHERE tid='".$nextthread[tid]."' ORDER BY dateline DESC LIMIT 0, 1");
	$pid = $db->result($query, 0);
	header("Location:showthread.php?tid=$nextthread[tid]&pid=$pid#pid$pid");
}
if($mybb->input['action'] == "nextoldest") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE fid='".$thread[fid]."' AND lastpost < '".$thread[lastpost]."' AND visible='1' AND closed NOT LIKE 'moved|%' ORDER BY lastpost DESC LIMIT 0, 1");
	$nextthread = $db->fetch_array($query);
	if(!$nextthread['tid']) {
		error($lang->error_nonextoldest);
	}
	$query = $db->query("SELECT pid FROM ".TABLE_PREFIX."posts WHERE tid='".$nextthread[tid]."' ORDER BY dateline DESC LIMIT 0, 1");
	$pid = $db->result($query, 0);
	header("Location:showthread.php?tid=$nextthread[tid]&pid=$pid#pid$pid");
}

if($mybb->input['action'] == "newpost") {
	$threadread = mygetarraycookie("threadread", $tid);
	if($threadread > $mybb->user['lastvisit']) {
		$mybb->user['lastvisit'] = $threadread;
	}
	$query = $db->query("SELECT pid FROM ".TABLE_PREFIX."posts WHERE tid='$tid' AND dateline>'".$mybb->user[lastvisit]."' ORDER BY dateline ASC LIMIT 0, 1");
	$newpost = $db->fetch_array($query);
	if($newpost['pid']) {
		header("Location:showthread.php?tid=$tid&pid=$newpost[pid]#pid$newpost[pid]");
	} else {
		header("Location:showthread.php?action=lastpost&tid=$tid");
	}
}

$plugins->run_hooks("showthread_start");

if($mybb->input['action'] == "thread") {
	if($thread['firstpost'] == 0)
	{
		update_first_post($tid);
	}
	// Thread has a poll?
	if($thread['poll']) {
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."polls WHERE pid='".$thread[poll]."'");
		$poll = $db->fetch_array($query);
		$poll['timeout'] = $poll['timeout']*60*60*24;
		$expiretime = $poll['dateline'] + $poll['timeout'];
		$now = time();
		if($poll['closed'] == "yes" || $thread['closed'] == "yes" || ($expiretime < $now && $poll['timeout'] > 0)) {
			$showresults = 1;
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."pollvotes WHERE uid='".$mybb->user[uid]."' AND pid='".$poll[pid]."'");
		while($votecheck = $db->fetch_array($query)) {
			$alreadyvoted = 1;
			if($mybb->user['uid']) {
				$votedfor[$votecheck['voteoption']] = 1;
			}
		}
		if($pollvotes[$poll['pid']]) {
			$alreadyvoted = 1;
		}
		$optionsarray = explode("||~|~||", $poll['options']);
		$votesarray = explode("||~|~||", $poll['votes']);
		for($i=1;$i<=$poll['numoptions'];$i++) {
			$poll['totvotes'] = $poll['totvotes'] + $votesarray[$i-1];
		}
		$poll['question'] = htmlspecialchars_uni($poll['question']);
		for($i=1;$i<=$poll['numoptions'];$i++) {
			$option = postify(stripslashes($optionsarray[$i-1]), $forum['allowhtml'], $forum['allowmycode'], $forum['allowsmilies'], $forum['allowimgcode']);
			$votes = $votesarray[$i-1];
			$number = $i;
			if($votedfor[$number]) {
				$optionbg = "trow2";
				$votestar = "*";
			} else {
				$optionbg = "trow1";
				$votestar = "";
			}
			if($alreadyvoted || $showresults) {
				if (intval($votes) == "0"){
					$percent = "0";
				} else{
					$percent = number_format($votes / $poll['totvotes'] * 100, 2);
				}
				$imagewidth = (round($percent)/3) * 5;
				eval("\$polloptions .= \"".$templates->get("showthread_poll_resultbit")."\";");
			} else {
				if($poll['multiple'] == "yes") {
					eval("\$polloptions .= \"".$templates->get("showthread_poll_option_multiple")."\";");
				} else {
					eval("\$polloptions .= \"".$templates->get("showthread_poll_option")."\";");
				}
			}
		}
		if($poll['totvotes'])
		{
			$totpercent = "100%";
		}
		else
		{
			$totpercent = "0%";
		}
		if($alreadyvoted || $showresults) {
			if($alreadyvoted) {
				$pollstatus = $lang->already_voted;
				eval("\$pollstatus = \"".$templates->get("showthread_poll_results_voted")."\";");
			} else {
				$pollstatus = $lang->poll_closed;
				eval("\$pollstatus = \"".$templates->get("showthread_poll_results_closed")."\";");
			}
			$lang->total_votes = sprintf($lang->total_votes, $poll['numvotes']);
			eval("\$pollbox = \"".$templates->get("showthread_poll_results")."\";");
			$plugins->run_hooks("showthread_poll_results");
		} else {
			if($poll['public'] == "yes") {
				$publicnote = $lang->public_note;
			}
			eval("\$pollbox = \"".$templates->get("showthread_poll")."\";");
			$plugins->run_hooks("showthread_poll");
		}

	} else {
		$pollbox = "";
	}
	
	// Make forum jump...
	$forumjump = makeforumjump("", $fid, 1);

	// Update Thread Last Read
	if($mybb->settings['threadreadcut'] && $mybb->user['uid'])
	{
		$db->shutdown_query("REPLACE INTO ".TABLE_PREFIX."threadsread SET tid='$tid', uid='".$mybb->user[uid]."', dateline='".time()."'");
	}
	else
	{
		mysetarraycookie("threadread", $tid, time());
	}

	if($$forum['open'] != "no") {
		eval("\$newthread = \"".$templates->get("showthread_newthread")."\";");
	}
	if($forum['open'] != "no") {
		if($thread['closed'] != "yes" || ismod($fid) == "yes") {
			eval("\$newreply = \"".$templates->get("showthread_newreply")."\";");
		} else {
			eval("\$newreply = \"".$templates->get("showthread_newreply_closed")."\";");
		}
	}

	// Admin Tools jump...
	if($ismod == true)
	{
		if($pollbox) {
			$adminpolloptions = "<option value=\"deletepoll\">".$lang->delete_poll."</option>";
		}
		if($thread['visible'] != 1)
		{
			$approveunapprovethread = "<option value=\"approvethread\">".$lang->approve_thread."</option>";
		}
		else
		{
			$approveunapprovethread = "<option value=\"unapprovethread\">".$lang->unapprove_thread."</option>";
		}
		if($thread['closed'] == "yes") {
			$closelinkch = "checked";
		}
		if($thread['sticky']) {
			$stickch = "checked";
		}
		$closeoption = "<br /><input type=\"checkbox\" name=\"modoptions[closethread]\" value=\"yes\" $closelinkch />&nbsp;<b>".$lang->close_thread."</b>";
		$closeoption .= "<br /><input type=\"checkbox\" name=\"modoptions[stickthread]\" value=\"yes\" $stickch />&nbsp;<b>".$lang->stick_thread."</b>";
		$inlinecount = "0";
		$inlinecookie = "inlinemod_thread".$tid;
		$plugins->run_hooks("showthread_ismod");
	} else {
		$adminoptions = "&nbsp;";
		$inlinemod = "";
	}

	if($forumpermissions['canpostreplys'] != "no" && ($thread['closed'] != "yes" || ismod($fid) == "yes") && $mybb->settings['quickreply'] != "off" && $mybb->user['showquickreply'] != "no" && $forum['open'] != "no") {
		if($mybb->user['signature']) {
			$postoptionschecked['signature'] = "checked";
		}
		if($mybb->user['emailnotify'] == "yes") {
			$postoptionschecked['emailnotify'] = "checked";
		}
		eval("\$quickreply = \"".$templates->get("showthread_quickreply")."\";");
	} else {
		$quickreply = "";
	}

	$db->shutdown_query("UPDATE ".TABLE_PREFIX."threads SET views=views+1 WHERE tid='$tid'");
	$thread['views']++;
	
	// Work out the threads rating
	if($forum['allowtratings'] != "no" && $thread['numratings'] > 0) {
		$thread['averagerating'] = round(($thread['totalratings']/$thread['numratings']), 2);
		$rateimg = intval(round($thread['averagerating']));
		$thread['rating'] = $rateimg."stars.gif";
		$thread['numratings'] = intval($thread['numratings']);
		eval("\$rating = \"".$templates->get("showthread_ratingdisplay")."\";");
	} else {
		$rating = "";
	}
	if($forum['allowtratings'] != "no") {
		eval("\$ratethread = \"".$templates->get("showthread_ratethread")."\";");
	}
	// Work out if we're showing both approved and unapproved threads or just approved..
	if($ismod)
	{
		$visible = "AND (visible='0' OR visible='1')";
	}
	else
	{
		$visible = "AND visible='1'";
	}
	// Threaded or Linear?
	if($mybb->input['mode'] == "threaded") { // Threaded
		$isfirst = 1;
		if($mybb->input['pid'])
		{
			$where = "AND p.pid='".intval($mybb->input['pid'])."'";
		}
		else
		{
			$where = " ORDER BY dateline ASC LIMIT 0, 1";
		}
		$query = $db->query("SELECT u.*, u.username AS userusername, p.*, f.*, i.path as iconpath, i.name as iconnamee, eu.username AS editusername FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid=p.icon) LEFT JOIN ".TABLE_PREFIX."users eu ON (eu.uid=p.edituid) WHERE p.tid='$tid' $visible $where");
		$showpost = $db->fetch_array($query);
		if(!$mybb->input['pid']) {
			$mybb->input['pid'] = $showpost['pid'];
		}


		// Get the attachments for this post
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE pid='".intval($mybb->input['pid'])."'");
		while($attachment = $db->fetch_array($query)) {
			$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
		}

		// Build the threaded tree
		$query = $db->query("SELECT u.*, u.username AS userusername, p.*, i.path as iconpath, i.name as iconname FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid=p.icon) WHERE p.tid='$tid' AND p.visible='1' ORDER BY p.dateline");
		while($post = $db->fetch_array($query)) {
			if(!$postsdone[$post['pid']]) {
				$tree[$post['replyto']][$post['pid']] = $post;
				if($post['pid'] == $mybb->input['pid'] || ($isfirst && !$mybb->input['pid'])) {
					$isfirst = 0;
				}
				$postsdone[$post['pid']] = 1;
			}
		}
		$threadedbits = buildtree();
		$posts = makepostbit($showpost);
		eval("\$threadexbox = \"".$templates->get("showthread_threadedbox")."\";");
		$plugins->run_hooks("showthread_threaded");
	} else { // Linear
		// Do Multi Pages
		$perpage = $mybb->settings['postsperpage'];
		if($mybb->input['pid']) {
			$query = $db->query("SELECT COUNT(pid) FROM ".TABLE_PREFIX."posts WHERE tid='$tid' AND pid <= '".intval($mybb->input['pid'])."' $visible");
			$result = $db->result($query, 0);
			if(($result % $perpage) == 0) {
				$page = $result / $perpage;
			} else {
				$page = intval($result / $perpage) + 1;
			}
		}
		$postcount = intval($thread['replies'])+1;
		$pages = $postcount / $perpage;
		$pages = ceil($pages);

		$page = intval($mybb->input['page']);
		if($page > $pages)
		{
			$page = 1;
		}
		if($page) {
			$start = ($page-1) * $perpage;
		} else {
			$start = 0;
			$page = 1;
		}
		$multipage = multipage($postcount, $perpage, $page, "showthread.php?tid=$tid");
		if($postcount > $perpage) {
			eval("\$threadpages = \"".$templates->get("showthread_multipage")."\";");
		}

		// Lets get the pid's of the posts on this page
		$pids = "";
		$query = $db->query("SELECT pid FROM ".TABLE_PREFIX."posts WHERE tid='$tid' $visible ORDER BY dateline LIMIT $start, $perpage");
		while($getid = $db->fetch_array($query)) {
			$pids .= "$comma'$getid[pid]'";
			$comma = ",";
		}
		if($pids)
		{
			$pids = "pid IN($pids)";
			// Now lets fetch all of the attachments for these posts
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE $pids");
			while($attachment = $db->fetch_array($query)) {
				$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
			}
		}
		else
		{
			// If there are no pid's the thread is probably awaiting approval
			error($lang->error_invalidthread);
		}

		// Lets get the actual posts
		$pfirst = true;
		$query = $db->query("SELECT u.*, u.username AS userusername, p.*, f.*, i.path as iconpath, i.name as iconname, eu.username AS editusername FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid=p.icon) LEFT JOIN ".TABLE_PREFIX."users eu ON (eu.uid=p.edituid) WHERE $pids ORDER BY p.dateline");
		while($post = $db->fetch_array($query))
		{
			if($pfirst && $thread['visible'] == 0)
			{
				$post['visible'] = 0;
			}
			$posts .= makepostbit($post);
			$post = "";
			$pfirst = false;
		}
		$plugins->run_hooks("showthread_linear");
	}
	if($mybb->settings['showsimilarthreads'] != "no") {
		$query = $db->query("SELECT subject, tid, lastpost, username, replies FROM ".TABLE_PREFIX."threads WHERE fid='$thread[fid]' AND tid!='$thread[tid]' AND visible='1' AND  MATCH (subject) AGAINST ('".addslashes($thread[subject])."') >= ".$mybb->settings[similarityrating]." ORDER BY dateline DESC LIMIT 0, ".$mybb->settings[similarlimit]);
		$count = 0;
		while($similarthread = $db->fetch_array($query)) {
			$count++;
			$similarthreaddate = mydate($mybb->settings['dateformat'], $similarthread['lastpost']);
			$similarthreadtime = mydate($mybb->settings['timeformat'], $similarthread['lastpost']);
			$similarthread['subject'] = htmlspecialchars_uni(stripslashes($similarthread['subject']));
			eval("\$similarthreadbits .= \"".$templates->get("showthread_similarthreads_bit")."\";");
		}
		if($count) {
			eval("\$similarthreads = \"".$templates->get("showthread_similarthreads")."\";");
		}
	}
	if($ismod)
	{
		eval("\$inlinemod = \"".$templates->get("showthread_inlinemoderation")."\";");
		eval("\$moderationoptions = \"".$templates->get("showthread_moderationoptions")."\";");
	}
	$thread['subject'] = dobadwords($thread['subject']);
	eval("\$showthread = \"".$templates->get("showthread")."\";");
	$plugins->run_hooks("showthread_end");
	outputpage($showthread);
}
function buildtree($replyto="0", $indent="0") {
	global $tree, $settings, $theme, $mybb, $pid, $tid, $templates;
	if($indent) {
		$indentsize = 13 * $indent;
	} else {
		$indentsize = 0;
	}
	$indent++;
	if(is_array($tree[$replyto])) {
		while(list($key, $post) = each($tree[$replyto])) {
			$postdate = mydate($mybb->settings['dateformat'], $post['dateline']);
			$posttime = mydate($mybb->settings['timeformat'], $post['dateline']);
			$post['subject'] = htmlspecialchars_uni(stripslashes(dobadwords($post['subject'])));
			if(!$post['subject']) {
				$post['subject'] = "[no subject]";
			}
			if($mybb->input['pid'] == $post['pid']) {
				eval("\$posts .= \"".$templates->get("showthread_threaded_bitactive")."\";");
			} else {
				eval("\$posts .= \"".$templates->get("showthread_threaded_bit")."\";");
			}
			if($tree[$post['pid']]) {
				$posts .= buildtree($post['pid'], $indent);
			}
		}
		$indent--;
	}
	return $posts;
}

?>