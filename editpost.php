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

$templatelist = "editpost,previewpost,redirect_postedited,loginbox,posticons,changeuserbox,attachment,posticons,codebuttons,smilieinsert,post_attachments_attachment_postinsert,post_attachments_attachment_mod_approve,post_attachments_attachment_unapproved,post_attachments_attachment_mod_unapprove,post_attachments_attachment,post_attachments_new,post_attachments,newthread_postpoll,editpost_disablesmilies";

require "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_upload.php";

// Load global language phrases
$lang->load("editpost");

// No permission for guests
if(!$mybb->user['uid'])
{
	error_no_permission();
}

// Get post info
$pid = intval($mybb->input['pid']);

$query = $db->simple_select(TABLE_PREFIX."posts", "*", "pid='$pid'");
$post = $db->fetch_array($query);

if(!$post['pid'])
{
	error($lang->error_invalidpost);
}

// Get thread info
$tid = $post['tid'];
$thread = get_thread($tid);

if(!$thread['tid'])
{
	error($lang->error_invalidthread);
}

$thread['subject'] = htmlspecialchars_uni($thread['subject']);

// Get forum info
$fid = $post['fid'];
$forum = get_forum($fid);
if(!$forum || $forum['type'] != "f")
{
	error($lang->error_closedinvalidforum);
}
if($forum['open'] == "no")
{
	error_no_permission();
}

// Make navigation
build_forum_breadcrumb($fid);
add_breadcrumb($thread['subject'], "showthread.php?tid=$thread[tid]");
add_breadcrumb($lang->nav_editpost);

$forumpermissions = forum_permissions($fid);


if($mybb->settings['bbcodeinserter'] != "off" && $forum['allowmycode'] != "no" && $mybb->user['showcodebuttons'] != 0)
{
	$codebuttons = build_mycode_inserter();
}
if($mybb->settings['smilieinserter'] != "off")
{
	$smilieinserter = build_clickable_smilies();
}

if(!$mybb->input['action'] || $mybb->input['previewpost'])
{
	$mybb->input['action'] = "editpost";
}

if($mybb->input['action'] == "deletepost" && $mybb->request_method == "post")
{
	if(is_moderator($fid, "candeleteposts") != "yes")
	{
		if($thread['closed'] == "yes")
		{
			redirect("showthread.php?tid=$tid", $lang->redirect_threadclosed);
		}
		if($forumpermissions['candeleteposts'] == "no")
		{
			error_no_permission();
		}
		if($mybb->user['uid'] != $post['uid'])
		{
			error_no_permission();
		}
	}
}
else
{
	if(is_moderator($fid, "caneditposts") != "yes")
	{
		if($thread['closed'] == "yes")
		{
			redirect("showthread.php?tid=$tid", $lang->redirect_threadclosed);
		}
		if($forumpermissions['caneditposts'] == "no")
		{
			error_no_permission();
		}
		if($mybb->user['uid'] != $post['uid'])
		{
			error_no_permission();
		}
		// Edit time limit
		$time = time();
		if($mybb->settings['edittimelimit'] != 0 && $post['dateline'] < ($time-($mybb->settings['edittimelimit']*60)))
		{
			$lang->edit_time_limit = sprintf($lang->edit_time_limit, $mybb->settings['edittimelimit']);
			error($lang->edit_time_limit);
		}
	}
}

// Password protected forums
check_forum_password($fid, $forum['password']);

if(!$mybb->input['attachmentaid'] && ($mybb->input['newattachment'] || ($mybb->input['action'] == "do_editpost" && $mybb->input['submit'] && $_FILES['attachment'])))
{
	// If there's an attachment, check it and upload it
	if($_FILES['attachment']['size'] > 0 && $forumpermissions['canpostattachments'] != "no")
	{
		$attachedfile = upload_attachment($_FILES['attachment']);
	}
	if($attachedfile['error'])
	{
		eval("\$attacherror = \"".$templates->get("error_attacherror")."\";");
		$mybb->input['action'] = "editpost";
	}
	if(!$mybb->input['submit'])
	{
		$mybb->input['action'] = "editpost";
	}
}

if($mybb->input['attachmentaid'] && isset($mybb->input['attachmentact'])) // Lets remove/approve/unapprove the attachment
{ 
	$mybb->input['attachmentaid'] = intval($mybb->input['attachmentaid']);
	if($mybb->input['attachmentact'] == "remove")
	{
		remove_attachment($pid, $mybb->input['posthash'], $mybb->input['attachmentaid']);
	}
	elseif($mybb->input['attachmentact'] == "approve")
	{
		$update_sql = array("visible" => 1);
		$db->update_query(TABLE_PREFIX."attachments", $update_sql, "aid='{$mybb->input['attachmentaid']}'");
	}
	elseif($mybb->input['attachmentact'] == "unapprove")
	{
		$update_sql = array("visible" => 0);
		$db->update_query(TABLE_PREFIX."attachments", $update_sql, "aid='{$mybb->input['attachmentaid']}'");
	}
	if(!$mybb->input['submit'])
	{
		$mybb->input['action'] = "editpost";
	}
}

if($mybb->input['action'] == "deletepost" && $mybb->request_method == "post")
{
	$plugins->run_hooks("editpost_deletepost");

	if($mybb->input['delete'] == "yes")
	{
		$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid='{$tid}'", array("limit" => 1, "order_by" => "dateline", "order_dir" => "asc"));
		$firstcheck = $db->fetch_array($query);
		if($firstcheck['pid'] == $pid)
		{
			$firstpost = 1;
		}
		else
		{
			$firstpost = 0;
		}
		$modlogdata['fid'] = $fid;
		$modlogdata['tid'] = $tid;
		if($firstpost)
		{
			if($forumpermissions['candeletethreads'] == "yes")
			{
				delete_thread($tid);
				update_forum_count($fid);
				mark_reports($tid, "thread");
				if(is_moderator($fid, "candeleteposts") != "yes")
				{
					log_moderator_action($modlogdata, "Deleted Thread");
				}
				redirect("forumdisplay.php?fid=$fid", $lang->redirect_threaddeleted);
			}
			else
			{
				error_no_permission();
			}
		}
		else
		{
			if($forumpermissions['candeleteposts'] == "yes")
			{
				// Select the first post before this
				delete_post($pid, $tid);
				update_thread_count($tid);
				update_forum_count($fid);
				mark_reports($pid, "post");
				if(is_moderator($fid, "candeleteposts") != "yes")
				{
					log_moderator_action($modlogdata, "Deleted Post");
				}
				$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid='{$tid}' AND dateline <= '{$post['dateline']}'", array("limit" => 1, "order_by" => "dateline", "order_dir" => "desc"));
				$next_post = $db->fetch_array($query);
				if($next_post['pid'])
				{
					$redir = "showthread.php?tid={$tid}&pid={$next_post['pid']}#pid{$next_post['pid']}";
				}
				else
				{
					$redir = "showthread.php?tid={$tid}";
				}
				redirect($redir, $lang->redirect_postdeleted);
			}
			else
			{
				error_no_permission();
			}
		}
	}
	else
	{
		redirect("showthread.php?tid={$tid}", $lang->redirect_nodelete);
	}
}

if($mybb->input['action'] == "do_editpost" && $mybb->request_method == "post")
{

	$plugins->run_hooks("editpost_do_editpost_start");

	// Set up posthandler.
	require_once MYBB_ROOT."inc/datahandlers/post.php";
	$posthandler = new PostDataHandler("update");
	$posthandler->action = "post";

	// Set the post data that came from the input to the $post array.
	$post = array(
		"pid" => $mybb->input['pid'],
		"subject" => $mybb->input['subject'],
		"icon" => $mybb->input['icon'],
		"uid" => $mybb->user['uid'],
		"username" => $mybb->user['username'],
		"edit_uid" => $mybb->user['uid'],
		"message" => $mybb->input['message'],
	);

	// Set up the post options from the input.
	$post['options'] = array(
		"signature" => $mybb->input['postoptions']['signature'],
		"emailnotify" => $mybb->input['postoptions']['emailnotify'],
		"disablesmilies" => $mybb->input['postoptions']['disablesmilies']
	);

	$posthandler->set_data($post);

	// Now let the post handler do all the hard work.
	if(!$posthandler->validate_post())
	{
		$post_errors = $posthandler->get_friendly_errors();
		$post_errors = inline_error($post_errors);
		$mybb->input['action'] = "editpost";
	}
	// No errors were found, we can call the update method.
	else
	{
		$posthandler->update_post();

		// Help keep our attachments table clean.
		$db->delete_query(TABLE_PREFIX."attachments", "filename='' OR filesize<1");

		// Did the user choose to post a poll? Redirect them to the poll posting page.
		if($mybb->input['postpoll'] && $forumpermissions['canpostpolls'])
		{
			$url = "polls.php?action=newpoll&tid=$tid&polloptions=".$mybb->input['numpolloptions'];
			$redirect = $lang->redirect_postedited_poll;
		}
		// Otherwise, send them back to their post
		else
		{
			$url = "showthread.php?tid=$tid&pid=$pid#pid$pid";
			$redirect = $lang->redirect_postedited;
		}
		$plugins->run_hooks("editpost_do_editpost_end");

		redirect($url, $redirect);
	}
}

if(!$mybb->input['action'] || $mybb->input['action'] == "editpost")
{
	$plugins->run_hooks("editpost_start");

	if(!$mybb->input['previewpost'])
	{
		$icon = $post['icon'];
	}

	if($forum['allowpicons'] != "no")
	{
		$posticons = get_post_icons();
	}

	if($mybb->user['uid'] != 0)
	{
		eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
	}
	else
	{
		eval("\$loginbox = \"".$templates->get("loginbox")."\";");
	}

	// Setup a unique posthash for attachment management
	$query = $db->simple_select(TABLE_PREFIX."posts", "posthash", "pid='{$pid}'");
	$posthash = $db->fetch_field($query, "posthash");

	$bgcolor = "trow2";
	if($forumpermissions['canpostattachments'] != "no")
	{ // Get a listing of the current attachments, if there are any
		$attachcount = 0;
		if($mybb->input['posthash'])
		{
			$posthash = "posthash='{$posthash}' OR ";
		}
		else
		{
			$posthash = "";
		}
		$query = $db->simple_select(TABLE_PREFIX."attachments", "*", "{$posthash}pid='{$pid}'");
		$attachments = '';
		while($attachment = $db->fetch_array($query))
		{
			$attachment['size'] = get_friendly_size($attachment['filesize']);
			$attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
			if($forum['allowmycode'] != "no")
			{
				eval("\$postinsert = \"".$templates->get("post_attachments_attachment_postinsert")."\";");
			}
			// Moderating options
			$attach_mod_options = '';
			if(is_moderator($fid) == "yes")
			{
				if($attachment['visible'] == 1)
				{
					eval("\$attach_mod_options = \"".$templates->get("post_attachments_attachment_mod_unapprove")."\";");
				}
				else
				{
					eval("\$attach_mod_options = \"".$templates->get("post_attachments_attachment_mod_approve")."\";");
				}
			}
			if($attachment['visible'] != 1)
			{
				eval("\$attachments .= \"".$templates->get("post_attachments_attachment_unapproved")."\";");
			}
			else
			{
				eval("\$attachments .= \"".$templates->get("post_attachments_attachment")."\";");
			}
			$attachcount++;
		}
		$query = $db->query("SELECT SUM(filesize) AS ausage FROM ".TABLE_PREFIX."attachments WHERE uid='".$mybb->user['uid']."'");
		$usage = $db->fetch_array($query);
		if($usage['ausage'] > ($mybb->usergroup['attachquota']*1000) && $mybb->usergroup['attachquota'] != 0)
		{
			$noshowattach = 1;
		}
		if($mybb->usergroup['attachquota'] == 0)
		{
			$friendlyquota = $lang->unlimited;
		}
		else
		{
			$friendlyquota = get_friendly_size($mybb->usergroup['attachquota']*1000);
		}
		$friendlyusage = get_friendly_size($usage['ausage']);
		$lang->attach_quota = sprintf($lang->attach_quota, $friendlyusage, $friendlyquota);
		if($mybb->settings['maxattachments'] == 0 || ($mybb->settings['maxattachments'] != 0 && $attachcount <= $mybb->settings['maxattachments']) && !$noshowattach)
		{
			eval("\$newattach = \"".$templates->get("post_attachments_new")."\";");
		}
		eval("\$attachbox = \"".$templates->get("post_attachments")."\";");
	}
	if(!$mybb->input['attachmentaid'] && !$mybb->input['newattachment'] && !$mybb->input['previewpost'] && !$maximageserror)
	{
		$message = $post['message'];
		$subject = $post['subject'];
	}
	else
	{
		$message = $mybb->input['message'];
		$subject = $mybb->input['subject'];
	}

	$query = $db->simple_select(TABLE_PREFIX."posts", "*", "tid='{$tid}'", array("limit" => 1, "order_by" => "dateline", "order_dir" => "asc"));
	$firstcheck = $db->fetch_array($query);
	if($firstcheck['pid'] == $pid && $forumpermissions['canpostpolls'] != "no" && $thread['poll'] < 1)
	{
		$lang->max_options = sprintf($lang->max_options, $mybb->settings['maxpolloptions']);
		$numpolloptions = "2";
		eval("\$pollbox = \"".$templates->get("newthread_postpoll")."\";");
	}

	if($mybb->input['previewpost'] || $post_errors)
	{
		$previewmessage = $message;
		$message = htmlspecialchars_uni($message);
		$subject = htmlspecialchars_uni($subject);

		$postoptions = $mybb->input['postoptions'];

		if($postoptions['signature'] == "yes")
		{
			$postoptionschecked['signature'] = "checked=\"checked\"";
		}
		if($postoptions['emailnotify'] == "yes")
		{
			$postoptionschecked['emailnotify'] = "checked=\"checked\"";
		}
		if($postoptions['disablesmilies'] == "yes")
		{
			$postoptionschecked['disablesmilies'] = "checked=\"checked\"";
		}

		$pid = intval($mybb->input['pid']);
	}

	if($mybb->input['previewpost'])
	{
		// Figure out the poster's other information.
		$query = $db->query("
			SELECT u.*, f.*
			FROM ".TABLE_PREFIX."users u
			LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
			WHERE u.uid='".$post['uid']."'
			LIMIT 1
		");
		$postinfo = $db->fetch_array($query);

		$query = $db->simple_select(TABLE_PREFIX."attachments", "*", "pid='".intval($mybb->input['pid'])."'");
		while($attachment = $db->fetch_array($query))
		{
			$attachcache[0][$attachment['aid']] = $attachment;
		}

		// Set the values of the post info array.
		$postinfo['username'] = $postinfo['username'];
		$postinfo['userusername'] = $postinfo['username'];
		$postinfo['uid'] = $postinfo['uid'];
		$postinfo['message'] = $previewmessage;
		$postinfo['subject'] = $subject;
		$postinfo['icon'] = $icon;
		$postinfo['smilieoff'] = $postoptions['disablesmilies'];
		$postinfo['dateline'] = time();

		$postbit = build_postbit($postinfo, 1);
		eval("\$preview = \"".$templates->get("previewpost")."\";");
	}
	elseif(!$post_errors)
	{
		$message = htmlspecialchars_uni($message);
		$subject = htmlspecialchars_uni($subject);

		if($post['includesig'] != "no")
		{
			$postoptionschecked['signature'] = "checked=\"checked\"";
		}
		if($post['smilieoff'] == "yes")
		{
			$postoptionschecked['disablesmilies'] = "checked=\"checked\"";
		}
		// Can we disable smilies or are they disabled already?
		if($forum['allowsmilies'] != "no")
		{
			eval("\$disablesmilies = \"".$templates->get("editpost_disablesmilies")."\";");
		}
		else
		{
			$disablesmilies = "<input type=\"hidden\" name=\"postoptions[disablesmilies]\" value=\"no\" />";
		}
		$query = $db->simple_select(TABLE_PREFIX."favorites", "*", "type='s' AND tid='{$tid}' AND uid='{$mybb->user['uid']}'");
		$subcheck = $db->fetch_array($query);
		if($subcheck['tid'])
		{
			$postoptionschecked['emailnotify'] = "checked=\"checked\"";
		}
	}

	$plugins->run_hooks("editpost_end");

	eval("\$editpost = \"".$templates->get("editpost")."\";");
	output_page($editpost);
}
?>
