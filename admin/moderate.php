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

require "./global.php";

// Load language packs for this section
global $lang;
$lang->load("moderate");

checkadminpermissions("canmodposts");
logadmin();

switch($action)
{
	case "threadsposts":
		addacpnav($lang->nav_moderate_threadsposts);
		break;
	case "threads":
		addacpnav($lang->nav_moderate_threads);
		break;
	case "posts":
		addacpnav($lang->nav_moderate_posts);
		break;
	case "attachments":
		addacpnav($lang->nav_moderate_attachments);
		break;
}

if($action == "do_attachments") {
	if(is_array($attachvalidate)) {
		while(list($aid, $val) = each($attachvalidate)) {
			if($attachdelete[$aid] == "yes") {
				$db->query("DELETE FROM ".TABLE_PREFIX."attachments WHERE aid='$aid'");
			} else {
				if($val != "no") {
					$db->query("UPDATE ".TABLE_PREFIX."attachments SET visible='1' WHERE aid='$aid'");
				}
			}
		}
	}
	cpmessage($lang->attachments_moderated);
}
if($action == "do_threads" || $action == "do_posts" || $action == "do_threadsposts") {
	if(is_array($threadvalidate) && $action != "do_posts") {
		while(list($tid, $val) = each($threadvalidate)) {
			$query = $db->query("SELECT subject, fid FROM ".TABLE_PREFIX."threads WHERE tid='$tid'");
			$thread = $db->fetch_array($query);
			if($threaddelete[$tid] == "yes") {
				deletethread($tid);
				$updateforumcount[$thread[fid]] = 1;
			} else {
				if($val != "no") {
					$subject = addslashes($threadsubject[$tid]);
					$message = addslashes($threadmessage[$tid]);
					$db->query("UPDATE ".TABLE_PREFIX."threads SET visible='1', subject='$subject' WHERE tid='$tid'");
					$db->query("UPDATE ".TABLE_PREFIX."posts SET message='$message', subject='$subject', visible='1' WHERE tid='$tid'");
					$updateforumcount[$thread[fid]] = 1;
				}
			}
		}
	}
	if(is_array($postvalidate) && $action != "do_threads") {
		while(list($pid, $val) = each($postvalidate)) {
			$query = $db->query("SELECT tid FROM ".TABLE_PREFIX."posts WHERE pid='$pid'");
			$post = $db->fetch_array($query);
			$query = $db->query("SELECT fid FROM ".TABLE_PREFIX."threads WHERE tid='$thread[tid]'");
			$thread = $db->fetch_array($query);
			if($postdelete[$pid] == "yes") {
				deletepost($pid);
				$updatethreadcount[$post[tid]] = 1;
				$updateforumcount[$thread[fid]] = 1;
			} else {
				if($val != "no") {
					$message = addslashes($postmessage[$pid]);
					$subject = addslashes($postsubject[$pid]);
					$db->query("UPDATE ".TABLE_PREFIX."posts SET visible=1, message='$message', subject='$subject' WHERE pid='$pid'");
					$updatethreadcount[$post[tid]] = 1;
					$updateforumcount[$thread[fid]] = 1;
				}
			}
		}
	}
	if(is_array($updatethreadcount)) {
		while(list($tid, $val) = each($updatethreadcount)) {
			updatethreadcount($tid);
		}
	}
	if(is_array($updateforumcount)) {
		while(list($fid, $val) = each($updateforumcount)) {
			updateforumcount($fid);
		}
	}
	cpmessage($lang->threadsposts_moderated);
}
if($action == "attachments") {
	$query = $db->query("SELECT a.*, p.subject AS postsubject, p.pid AS postpid, p.tid, p.username AS postusername, p.uid AS postuid, t.subject AS threadsubject, f.name AS forumname, p.fid FROM ".TABLE_PREFIX."attachments a, ".TABLE_PREFIX."posts p, ".TABLE_PREFIX."threads t LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid) WHERE a.pid=p.pid AND t.tid=p.tid AND a.visible!='1' ORDER BY p.dateline DESC");
	$count = $db->num_rows($query);
	if(!$count) {
		cperror($lang->no_attachments);
	}
	cpheader();
	startform("moderate.php", "" , "do_attachments");
	starttable();
	tableheader($lang->attachments_awaiting);
	$done = 0;
	while($attachment = $db->fetch_array($query)) {
		$done = 1;
		if($attachment[filesize] >= 1073741824) {
			$attachment[filesize] = round(($attachment[filesize] / 1073741824), 2) . " GB";
		} elseif($attachment[filesize] >= 1048576) {
			$attachment[filesize] = round(($attachment[filesize] / 1048576), 2) . " MB";
		} elseif($attachment[filesize] >= 1024)	{
			$attachment[filesize] = round(($attachment[filesize] / 1024), 2) . " KB";
		} else {
			$attachment[filesize] = $attachment[filesize] . " bytes";
		}
		if(!$attachment[postsubject]) {
			$attachment[postsubject] = "[no subject]";
		}
		makelabelcode($lang->attachment, "$attachment[filename] ($lang->size $attachment[filesize]) ".makelinkcode($lang->view, "../attachment.php?tid=$attachment[tid]&pid=$attachment[pid]", 1));
		makelabelcode($lang->post, "<a href=\"../showthread.php?tid=$attachment[tid]&pid=$attachment[postpid]#pid$attachment[postpid]\" target=\"_blank\">$attachment[postsubject]</a>");
		makelabelcode($lang->thread, "<a href=\"../showthread.php?tid=$attachment[tid]\" target=\"_blank\">$attachment[threadsubject]</a>");
		makelabelcode($lang->posted_by, "<a href=\"../member.php?action=profile&uid=$attachment[postuid]\" target=\"_blank\">$attachment[postusername]</a>");
		makelabelcode($lang->forum, "<a href=\"../forumdisplay.php?fid=$attachment[fid]\" target=\"_blank\">$attachment[forumname]</a>");
		makeyesnocode($lang->validate_attachment, "attachvalidate[$attachment[aid]]");
		makeyesnocode($lang->delete_attachment, "attachdelete[$attachment[aid]]", "no");
		makehiddencode("attachpid[$attachment[aid]]", "$attachment[postpid]");
		echo "<tr>\n<td class=\"subheader\" align=\"center\" colspan=\"2\" height=\"2\"><img src=\"pixel.gif\" width=\"1\" height=\"1\"></td>\n</tr>\n";
	}
	endtable();
	endform($lang->moderate_attachments, $lang->reset_button);
	cpfooter();
}
if($action == "threads" || $action == "threadsposts") {
	$done = 0;
	$query = $db->query("SELECT t.*, f.name AS forumname, u.username AS username, p.message AS postmessage, p.pid AS postpid FROM ".TABLE_PREFIX."threads t, ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid) WHERE t.visible=0 AND p.tid=t.tid ORDER BY t.lastpost DESC");
	$tcount = $db->num_rows($query);

	if($tcount < 1 && $action != "threadsposts")
	{
		cperror($lang->no_threads);
	}
	if($tcount)
	{
		cpheader();
		startform("moderate.php", "" , "do_".$action);
		starttable();
		tableheader($lang->threads_awaiting);
	}
	while($thread = $db->fetch_array($query)) {
		$thread['subject'] = htmlspecialchars_uni(stripslashes($thread['subject']));
		$done = 1;
		makeinputcode($lang->thread_subject, "threadsubject[$thread[tid]]", "$thread[subject]");		
		makelabelcode($lang->posted_by, "<a href=\"../member.php?action=profile&uid=$thread[uid]\" target=\"_blank\">$thread[username]</a>");
		makelabelcode($lang->forum, "<a href=\"../forumdisplay.php?fid=$thread[fid]\" target=\"_blank\">$thread[forumname]</a>");
		maketextareacode($lang->message, "threadmessage[$thread[tid]]", $thread[postmessage], 5);
		makeyesnocode($lang->validate_thread, "threadvalidate[$thread[tid]]");
		makeyesnocode($lang->delete_thread, "threaddelete[$thread[tid]]", "no");
		$donepid[$thread[postpid]] = 1;
		echo "<tr>\n<td class=\"subheader\" align=\"center\" colspan=\"2\" height=\"2\"><img src=\"pixel.gif\" width=\"1\" height=\"1\"></td>\n</tr>\n";
	}
	endtable();
	if($action != "threadsposts") {
		endform($lang->moderate_threads, $lang->reset_button);
		cpfooter();
	}
}
if($action == "posts" || $action == "threadsposts") {
	$done = 0;
	$query = $db->query("SELECT p.pid, p.subject, p.message, t.subject AS threadsubject, f.name AS forumname, u.username AS username FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE p.visible=0 ORDER BY p.dateline DESC");
	$count = $db->num_rows($query);
	if(!$tcount && !$count && $action == "threadsposts")
	{
		cperror($lang->no_threadsposts);
	}

	if($count < 1 && $action != "threadsposts")
	{
		cperror($lang->no_posts);
	}
	if($action != "threadsposts") {
		cpheader();
		startform("moderate.php", "" , "do_posts");
	}
	starttable();
	tableheader($lang->posts_awaiting);

	while($post = $db->fetch_array($query)) {
		if(!$donepid[$post[pid]]) { // so we dont show new threads main post again
			$done = 1;
			$thread['subject'] = htmlspecialchars_uni(stripslashes($thread['subject']));
			makeinputcode($lang->post_subject, "postsubject[$post[pid]]", "$post[subject]");	
			makelabelcode($lang->thread, "<a href=\"../showthread.php?tid=$post[tid]\" target=\"_blank\">$post[threadsubject]</a>");	
			makelabelcode($lang->posted_by, "<a href=\"../member.php?action=profile&uid=$post[uid]\" target=\"_blank\">$post[username]</a>");
			makelabelcode($lang->forum, "<a href=\"../forumdisplay.php?fid=$post[fid]\" target=\"_blank\">$post[forumname]</a>");
			maketextareacode($lang->message, "postmessage[$post[pid]]", $post[message], 5);
			makeyesnocode($lang->validate_post, "postvalidate[$post[pid]]");
			makeyesnocode($lang->delete_post, "postdelete[$post[pid]]", "no");
			echo "<tr>\n<td class=\"subheader\" align=\"center\" colspan=\"2\" height=\"2\"><img src=\"pixel.gif\" width=\"1\" height=\"1\"></td>\n</tr>\n";
		}
	}
	endtable();
	if($action != "threadsposts") {
		endform($lang->moderate_posts, $lang->reset_button);
		cpfooter();
	} else {
		endform($lang->moderate_threads_posts, $lang->reset_button);
		cpfooter();
	}
}
?>