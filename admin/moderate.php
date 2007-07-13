<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

define("IN_MYBB", 1);

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_upload.php";

// Load language packs for this section
global $lang;
$lang->load("moderate");

checkadminpermissions("canmodposts");
logadmin();

switch($mybb->input['action'])
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

$plugins->run_hooks("admin_moderate_start");

if($mybb->input['action'] == "do_attachments")
{
	$plugins->run_hooks("admin_moderate_do_attachments");
	if(is_array($mybb->input['attachvalidate']))
	{
		$delete_aids = $approve_aids = array(0);
		foreach($mybb->input['attachvalidate'] as $aid => $val)
		{
			$aid = intval($aid);
			if($attachdelete[$aid] == 'yes')
			{
				$delete_aids[] = $aid;
			}
			elseif($val != 'no')
			{					
				$approve_aids[] = $aid;
			}
		}
		if(count($delete_aids) > 1)
		{
			$aids = implode(',', $delete_aids);
			$db->delete_query("attachments", "aid IN ({$aids})");
		}
		if(count($approve_aids) > 1)
		{
			$aids = implode(',', $approve_aids);
			$sql_array = array(
				"visible" => 1
			);
			$db->update_query("attachments", $sql_array, "aid IN ({$aids})");
		}
	}
	cpredirect("moderate.php?".SID."&action=attachments", $lang->attachments_moderated);
}

if($mybb->input['action'] == "do_threads" || $mybb->input['action'] == "do_posts" || $mybb->input['action'] == "do_threadsposts")
{
	$plugins->run_hooks("admin_moderate_do_threadsposts");
	if(is_array($mybb->input['threadvalidate']) && $mybb->input['action'] != "do_posts")
	{
		foreach($mybb->input['threadvalidate'] as $tid => $val)
		{
			$tid = intval($tid);
			$query = $db->simple_select("threads", "subject, fid", "tid='$tid'");
			$thread = $db->fetch_array($query);
			if($mybb->input['threaddelete'][$tid] == "yes")
			{
				delete_thread($tid);
			}
			else
			{
				if($val != "no")
				{
					$subject = $db->escape_string($mybb->input['threadsubject'][$tid]);
					$message = $db->escape_string($mybb->input['threadmessage'][$tid]);
					
					$db->write_query("UPDATE ".TABLE_PREFIX."threads SET visible='1', subject='{$subject}', unapprovedposts=unapprovedposts-1 WHERE tid = '".$tid."'");
					
					$sql_array = array(
						"message" => $message,
						"subject" => $subject,
						"visible" => 1
					);
					$db->update_query("posts", $sql_array, "tid = '".$tid."'");

					// Update unapproved thread count
					$db->write_query("UPDATE ".TABLE_PREFIX."forums SET unapprovedthreads=unapprovedthreads-1,unapprovedposts=unapprovedposts-1,threads=threads+1, posts=posts+1 WHERE fid='{$thread['fid']}'");
				}
			}
		}
	}

	if(is_array($mybb->input['postvalidate']) && $mybb->input['action'] != "do_threads")
	{
		foreach($mybb->input['postvalidate'] as $pid => $val)
		{
			$pid = intval($pid);
			$query = $db->simple_select("posts", "tid", "pid='$pid'");
			$post = $db->fetch_array($query);
			$query = $db->simple_select("threads", "fid", "tid='$post[tid]'");
			$thread = $db->fetch_array($query);
			if($mybb->input['postdelete'][$pid] == "yes")
			{
				delete_post($pid);
			}
			else
			{
				if($val != "no")
				{
					$message = $db->escape_string($mybb->input['postmessage'][$pid]);
					$subject = $db->escape_string($mybb->input['postsubject'][$pid]);
					
					$sql_array = array(
						"visible" => 1,
						"message" => $message,
						"subject" => $subject
					);
					$db->update_query("posts", $sql_array, "pid = '".$pid."'");
					// Update unapproved thread count
					$db->write_query("UPDATE ".TABLE_PREFIX."threads SET unapprovedposts=unapprovedposts-1, replies=replies+1 WHERE tid='$post[tid]'");
					$db->write_query("UPDATE ".TABLE_PREFIX."forums SET posts=posts+1 WHERE fid='$post[fid]'");
				}
			}
		}
	}
	cpredirect("moderate.php?".SID."&action=".str_replace('do_', '', $mybb->input['action']), $lang->threadsposts_moderated);
}
if($mybb->input['action'] == "attachments")
{
	$query = $db->query("
		SELECT a.*, p.subject AS postsubject, p.pid AS postpid, p.tid, p.username AS postusername, p.uid AS postuid, t.subject AS threadsubject, f.name AS forumname, p.fid
		FROM ".TABLE_PREFIX."attachments a
		LEFT JOIN ".TABLE_PREFIX."posts p ON(a.pid=p.pid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON(t.tid=p.tid)
		LEFT JOIN ".TABLE_PREFIX."forums f ON(f.fid=t.fid)
		WHERE a.visible != '1'
		ORDER BY p.dateline DESC
	");
	$count = $db->num_rows($query);
	if(!$count)
	{
		cperror($lang->no_attachments);
	}
	$plugins->run_hooks("admin_moderate_attachments");
	cpheader();
	startform("moderate.php", "" , "do_attachments");
	starttable();
	tableheader($lang->attachments_awaiting);
	$done = 0;
	while($attachment = $db->fetch_array($query))
	{
		$done = 1;
		if($attachment['filesize'] >= 1073741824)
		{
			$attachment['filesize'] = round(($attachment['filesize'] / 1073741824), 2). " ".$lang->size_gb;
		}
		elseif($attachment['filesize'] >= 1048576)
		{
			$attachment['filesize'] = round(($attachment['filesize'] / 1048576), 2)." ".$lang->size_mb;
		}
		elseif($attachment['filesize'] >= 1024)
		{
			$attachment['filesize'] = round(($attachment['filesize'] / 1024), 2)." ".$lang->size_kb;
		}
		else
		{
			$attachment['filesize'] = $attachment['filesize']." ".$lang->size_bytes;
		}
		if(empty($attachment['postsubject']))
		{
			$attachment['postsubject'] = $lang->no_subject;
		}
		makelabelcode($lang->attachment, "$attachment[filename] ($lang->size $attachment[filesize]) ".makelinkcode($lang->view, "../attachment.php?tid=$attachment[tid]&amp;pid=$attachment[pid]", 1));
		makelabelcode($lang->post, "<a href=\"../".get_post_link($attachment['postpid'])."#pid$attachment[postpid]\" target=\"_blank\">$attachment[postsubject]</a>");
		makelabelcode($lang->thread, "<a href=\"../".get_thread_link($attachment['tid'])."\" target=\"_blank\">$attachment[threadsubject]</a>");
		makelabelcode($lang->posted_by, build_profile_link($attachment['postusername'], $attachment['postuid']));
		makelabelcode($lang->forum, "<a href=\"../".get_forum_link($attachment['fid'])."\" target=\"_blank\">$attachment[forumname]</a>");
		makeyesnocode($lang->validate_attachment, "attachvalidate[$attachment[aid]]");
		makeyesnocode($lang->delete_attachment, "attachdelete[$attachment[aid]]", "no");
		makehiddencode("attachpid[$attachment[aid]]", "$attachment[postpid]");
		echo "<tr>\n<td class=\"subheader\" align=\"center\" colspan=\"2\" height=\"2\"><img src=\"pixel.gif\" width=\"1\" height=\"1\" alt=\"\" /></td>\n</tr>\n";
	}
	endtable();
	endform($lang->moderate_attachments, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "threads" || $mybb->input['action'] == "threadsposts")
{
	$done = 0;

	$query = $db->query("
		SELECT t.tid, t.fid, t.subject, p.message AS postmessage, p.pid AS postpid, f.name AS forumname, u.username AS username
		FROM ".TABLE_PREFIX."threads t
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.tid=t.tid)
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
		WHERE t.visible='0'
		ORDER BY t.lastpost DESC
	");
	$tcount = $db->num_rows($query);

	if($tcount < 1 && $mybb->input['action'] != "threadsposts")
	{
		cpmessage($lang->no_threads);
	}
	$plugins->run_hooks("admin_moderate_threads");
	if($tcount >= 1 || $mybb->input['action'] == "threadsposts")
	{
		cpheader();
		startform("moderate.php", "" , "do_".$mybb->input['action']);
	}
	if($tcount >= 1)
	{
		starttable();
		tableheader($lang->threads_awaiting);
	}
	while($thread = $db->fetch_array($query))
	{
		$thread['subject'] = htmlspecialchars_uni(stripslashes($thread['subject']));
		$done = 1;
		makeinputcode($lang->thread_subject, "threadsubject[$thread[tid]]", "$thread[subject]");		
		makelabelcode($lang->posted_by, build_profile_link($thread['username'], $thread['uid']));
		makelabelcode($lang->forum, "<a href=\"../".get_forum_link($attachment['fid'])."\" target=\"_blank\">$thread[forumname]</a>");
		maketextareacode($lang->message, "threadmessage[$thread[tid]]", $thread['postmessage'], 5);
		makeyesnocode($lang->validate_thread, "threadvalidate[$thread[tid]]");
		makeyesnocode($lang->delete_thread, "threaddelete[$thread[tid]]", "no");
		$donepid[$thread['postpid']] = 1;
		echo "<tr>\n<td class=\"subheader\" align=\"center\" colspan=\"2\" height=\"2\"><img src=\"pixel.gif\" width=\"1\" height=\"1\" alt=\"\" /></td>\n</tr>\n";
	}
	if($tcount >= 1)
	{
		endtable();
	}
	if($mybb->input['action'] != "threadsposts") 
	{
		endform($lang->moderate_threads, $lang->reset_button);
		cpfooter();
	}
}
if($mybb->input['action'] == "posts" || $mybb->input['action'] == "threadsposts") 
{
	$done = 0;
	$options = array(
		"order_by" => "p.dateline",
		"order_dir" => "DESC"
	);
	$query = $db->query("
		SELECT p.pid, p.subject, p.message, t.subject AS threadsubject, t.tid, f.name AS forumname, u.username AS username
		FROM  ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE p.visible='0'
		ORDER BY p.dateline DESC
	");
	$count = $db->num_rows($query);
	if(!$tcount && !$count && $mybb->input['action'] == "threadsposts")
	{
	  	endform();
		cpmessage($lang->no_threadsposts);
	}

	if($count < 1 && $mybb->input['action'] != "threadsposts")
	{
		cpmessage($lang->no_posts);
	}
	$plugins->run_hooks("admin_moderate_posts");
	if($count >= 1 && $mybb->input['action'] != "threadsposts") 
	{
		cpheader();
		startform("moderate.php", "" , "do_posts");
	}
	
	if($count >= 1)
	{
	  starttable();
	  tableheader($lang->posts_awaiting);
	}

	while($post = $db->fetch_array($query))
	{
		if(!$donepid[$post['pid']])
		{ 
			// so we dont show new threads main post again
			$done = 1;
			$thread['subject'] = htmlspecialchars_uni(stripslashes($thread['subject']));
			makeinputcode($lang->post_subject, "postsubject[$post[pid]]", "$post[subject]");	
			makelabelcode($lang->thread, "<a href=\"../".get_thread_link($post['tid'])."\" target=\"_blank\">$post[threadsubject]</a>");	
			makelabelcode($lang->posted_by, build_profile_link($post['username'], $post['uid']));
			makelabelcode($lang->forum, "<a href=\"../".get_forum_link($post['fid'])."\" target=\"_blank\">$post[forumname]</a>");
			maketextareacode($lang->message, "postmessage[$post[pid]]", $post[message], 5);
			makeyesnocode($lang->validate_post, "postvalidate[$post[pid]]");
			makeyesnocode($lang->delete_post, "postdelete[$post[pid]]", "no");
			echo "<tr>\n<td class=\"subheader\" align=\"center\" colspan=\"2\" height=\"2\"><img src=\"pixel.gif\" width=\"1\" height=\"1\" alt=\"\" /></td>\n</tr>\n";
		  	++$postscount;
		}
	}
	if($postscount < 1)
	{
    	echo "<tr>\n";
		echo "<td class=\"$bgcolor\" colspan=\"1\">".$lang->no_posts."</td>\n";
		echo "</tr>\n";
  	}
	if($count >= 1)
	{
	  endtable();
	}
	if($mybb->input['action'] != "threadsposts")
	{
		endform($lang->moderate_posts, $lang->reset_button);
		cpfooter();
	}
	else
	{
		endform($lang->moderate_threads_posts, $lang->reset_button);
		cpfooter();
	}
}
?>
