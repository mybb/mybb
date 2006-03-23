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

$templatelist = "newthread,previewpost,error_invalidforum,redirect_newthread,loginbox,changeuserbox,newthread_postpoll,posticons,attachment,newthread_postpoll,codebuttons,smilieinsert,error_nosubject";
$templatelist .= "posticons";

require "./global.php";
require "./inc/functions_post.php";
require "./inc/functions_user.php";

// Load global language phrases
$lang->load("newthread");

$tid = $pid = "";
if($mybb->input['action'] == "editdraft" || ($mybb->input['savedraft'] && $mybb->input['tid']) || ($mybb->input['tid'] && $mybb->input['pid']))
{
	$thread = get_thread($mybb->input['tid']);
	
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='".intval($mybb->input['tid'])."' AND visible='-2' ORDER BY dateline ASC LIMIT 0, 1");
	$post = $db->fetch_array($query);

	if(!$thread['tid'] || !$post['pid'] || $thread['visible'] != -2)
	{
		error($lang->invalidthread);
	}
	
	$pid = $post['pid'];
	$fid = $thread['fid'];
	$tid = $thread['tid'];
}
else
{
	$fid = intval($mybb->input['fid']);
}

// Fetch forum information.
$forum = get_forum($fid);
if(!$forum)
{
	error($lang->error_invalidforum);
}

// Draw the navigation
makeforumnav($fid);
addnav($lang->nav_newthread);

$forumpermissions = forum_permissions($fid);

if($forum['open'] == "no" || $forum['type'] != "f")
{
	error($lang->error_closedinvalidforum);
}

if($forumpermissions['canview'] == "no" || $forumpermissions['canpostthreads'] == "no")
{
	nopermission();
}
// Check if this forum is password protected and if we've got the right password to access it.
checkpwforum($fid, $forum['password']);

// If MyCode is on for this forum and the MyCode editor is enabled inthe Admin CP, draw the code buttons and smilie inserter.
if($mybb->settings['bbcodeinserter'] != "off" && $forum['allowmycode'] != "no" && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
{
	$codebuttons = makebbcodeinsert();
	if($forum['allowsmilies'] != "no")
	{
		$smilieinserter = makesmilieinsert();
	}
}

// Does this forum allow post icons? If so, fetch the post icons.
if($forum['allowpicons'] != "no")
{
	$posticons = getposticons();
}

// If we have a currently logged in user then fetch the change user box.
if($mybb->user['uid'] != 0)
{
	eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
}

// Otherwise we have a guest, determine the "username" and get the login box.
else
{
	if(!$mybb->input['previewpost'] && $mybb->input['action'] != "do_newthread")
	{
		$username = $lang->guest;
	}
	else
	{
		$username = $mybb->input['username'];
	}
	eval("\$loginbox = \"".$templates->get("loginbox")."\";");
}

// If we're not performing a new thread insert and not editing a draft then we're posting a new thread.
if($mybb->input['action'] != "do_newthread" && $mybb->input['action'] != "editdraft")
{
	$mybb->input['action'] = "newthread";
}

// Previewing a post, overwrite the action to the new thread action.
if($mybb->input['previewpost'])
{
	$mybb->input['action'] = "newthread";
}

// Handle attachments if we've got any.
if(!$mybb->input['removeattachment'] && ($mybb->input['newattachment'] || ($mybb->input['action'] == "do_newthread" && $mybb->input['submit'] && $_FILES['attachment'])))
{
	// If there's an attachment, check it and upload it
	if($_FILES['attachment']['size'] > 0 && $forumpermissions['canpostattachments'] != "no")
	{
		require "./inc/functions_upload.php";
		$attachedfile = upload_attachment($_FILES['attachment']);
	}
	
	// Error with attachments - should use new inline errors?
	if($attachedfile['error'])
	{
		eval("\$attacherror = \"".$templates->get("error_attacherror")."\";");
		$mybb->input['action'] = "newthread";
	}
	
	// If we were dealing with an attachment but didn't click 'Post Thread', force the new thread page again.
	if(!$mybb->input['submit'])
	{
		$mybb->input['action'] = "newthread";
	}
}

// Are we removing an attachment from the thread?
if($mybb->input['removeattachment'])
{
	require_once "./inc/functions_upload.php";
	remove_attachment(0, $mybb->input['posthash'], $mybb->input['removeattachment']);
	if(!$mybb->input['submit'])
	{
		$mybb->input['action'] = "newthread";
	}
}

$thread_errors = "";

// Performing the posting of a new thread.
if($mybb->input['action'] == "do_newthread" && $mybb->request_method == "post")
{
	$plugins->run_hooks("newthread_do_newthread_start");

	// If this isn't a logged in user, then we need to do some special validation.
	if($mybb->user['uid'] == 0)
	{
		$username = htmlspecialchars_uni($mybb->input['username']);
	
		// Check if username exists.
		if(username_exists($mybb->input['username']))
		{
			// If it does and no password is given throw back "username is taken"
			if(!$mybb->input['password'])
			{
				error($lang->error_usernametaken);
			}
			
			// If the user specified a password but it is wrong, throw back invalid password.
			$mybb->user = validate_password_from_username($mybb->input['username'], $mybb->input['password']);
			if(!$mybb->user['uid'])
			{
				error($lang->error_invalidpassword);
			}
			// Otherwise they've logged in successfully.
			
			$mybb->input['username'] = $username = $mybb->user['username'];
			mysetcookie("mybbuser", $mybb->user['uid']."_".$mybb->user['loginkey']);
		}
		// This username does not exist.
		else
		{
			// If they didn't specify a username then give them "Guest"
			if(!$mybb->input['username'])
			{
				$username = $lang->guest;
			}
			// Otherwise use the name they specified.
			else
			{
				$username = $mybb->input['username'];
			}
			$uid = 0;
		}
	}
	// This user is logged in.
	else
	{
		$username = $mybb->user['username'];
		$uid = $mybb->user['uid'];
	}
	
	// Set up posthandler.
	require_once "inc/datahandlers/post.php";
	$posthandler = new PostDataHandler();
	$posthandler->action = "thread";

	// Set the thread data that came from the input to the $thread array.
	$new_thread = array(
		"fid" => $forum['fid'],
		"subject" => $mybb->input['subject'],
		"icon" => $mybb->input['icon'],
		"uid" => $mybb->user['uid'],
		"username" => $mybb->user['username'],
		"message" => $mybb->input['message'],
		"ipaddress" => getip(),
		"posthash" => $mybb->input['posthash']
	);

	// Are we saving a draft thread?
	if($mybb->input['savedraft'] && $mybb->user['uid'])
	{
		$new_thread['savedraft'] = 1;
	}
	else
	{
		$new_thread['savedraft'] = 0;
	}
	
	// Is this thread already a draft and we're updating it?
	if(isset($thread['tid']) && $thread['visible'] == -2)
	{
		$new_thread['tid'] = $thread['tid'];
	}

	// Set up the thread options from the input.
	$new_thread['options'] = array(
		"signature" => $mybb->input['postoptions']['signature'],
		"emailnotify" => $mybb->input['postoptions']['emailnotify'],
		"disablesmilies" => $mybb->input['postoptions']['disablesmilies']
	);
	
	// Apply moderation options if we have them
	$new_thread['modoptions'] = $mybb->input['modoptions'];

	$posthandler->set_data($new_thread);
	
	// Now let the post handler do all the hard work.
	if(!$posthandler->validate_thread())
	{
		$errors = $posthandler->get_errors();
		foreach($errors as $error)
		{
			//
			// MYBB 1.2 DATA HANDLER ERROR HANDLING DEBUG/TESTING CODE (REMOVE BEFORE PUBLIC FINAL)
			// Used to determine any missing language variables from the datahandlers
			//
			if($lang->$error['error_code'])
			{
				$post_errors[] = $lang->$error['error_code'];
			}
			else
			{
				$post_errors[] = "Missing language var: ".$error['error_code'];
			}
			//
			// END TESTING CODE
			//
			/*
				$post_errors[] =$lang->$error['error_code'];
			*/
		}
		$thread_errors = inlineerror($post_errors);
		$mybb->input['action'] = "newthread";
	}
	
	// No errors were found, it is safe to insert the thread.
	else
	{
		$thread_info = $posthandler->insert_thread();
		$tid = $thread_info['tid'];
		$visible = $thread_info['visible'];
		
		// We were updating a draft thread, send them back to the draft listing.
		if($new_thread['savedraft'] == 1)
		{
			$lang->redirect_newthread = $lang->draft_saved;
			$url = "usercp.php?action=drafts";
		}
		
		// A poll was being posted with this thread, throw them to poll posting page.
		else if($mybb->input['postpoll'] && $forumpermissions['canpostpolls'])
		{
			$url = "polls.php?action=newpoll&tid=$tid&polloptions=".intval($mybb->input['numpolloptions']);
			$lang->redirect_newthread .= $lang->redirect_newthread_poll;
			$cache->updatestats();
			updatethreadcount($tid);
			updateforumcount($fid);
		}
		
		// This thread is stuck in the moderation queue, send them back to the forum.
		else if(!$visible)
		{
			// Moderated thread
			$lang->redirect_newthread .= $lang->redirect_newthread_moderation;
			$url = "forumdisplay.php?fid=$fid";
			// Update the unapproved posts and threads count for the current thread and current forum
			$db->query("UPDATE ".TABLE_PREFIX."threads SET unapprovedposts=unapprovedposts+1 WHERE tid='$tid'");
			$db->query("UPDATE ".TABLE_PREFIX."forums SET unapprovedthreads=unapprovedthreads+1, unapprovedposts=unapprovedposts+1 WHERE fid='$fid'");
		}

		// This is just a normal thread - send them to it.
		else
		{
			// Visible thread
			$lang->redirect_newthread .= $lang->redirect_newthread_thread;
			$url = "showthread.php?tid=$tid";
			$cache->updatestats();
			updatethreadcount($tid);
			updateforumcount($fid);
		}

		$plugins->run_hooks("newthread_do_newthread_end");
		
		// Hop to it! Send them to the next page.
		$lang->redirect_newthread .= sprintf($lang->redirect_return_forum, $fid);
		redirect($url, $lang->redirect_newthread);
	}
}

if($mybb->input['action'] == "newthread" || $mybb->input['action'] == "editdraft")
{

	$plugins->run_hooks("newthread_start");

	// Check the various post options if we're
	// a -> previewing a post
	// b -> removing an attachment
	// c -> adding a new attachment
	// d -> have errors from posting
	
	if($mybb->input['previewpost'] || $mybb->input['removeattachment'] || $mybb->input['newattachment'] || $thread_errors)
	{
		$postoptions = $mybb->input['postoptions'];
		if($postoptions['signature'] == "yes")
		{
			$postoptionschecked['signature'] = "checked";
		}
		if($postoptions['emailnotify'] == "yes")
		{
			$postoptionschecked['emailnotify'] = "checked";
		}
		if($postoptions['disablesmilies'] == "yes")
		{
			$postoptionschecked['disablesmilies'] = "checked";
		}
		if($postpoll == "yes")
		{
			$postpollchecked = "checked";
		}
		$numpolloptions = intval($mybb->input['numpolloptions']);
	}
	
	// Editing a draft thread
	else if($mybb->input['action'] == "editdraft" && $mybb->user['uid'])
	{
		$message = htmlspecialchars_uni($post['message']);
		$subject = htmlspecialchars_uni($post['subject']);
		if($post['includesig'] != "no")
		{
			$postoptionschecked['signature'] = "checked";
		}
		if($post['smilieoff'] == "yes")
		{
			$postoptionschecked['disablesmilies'] = "checked";
		}
		$editdraftpid = "<input type=\"hidden\" name=\"pid\" value=\"$pid\" /><input type=\"hidden\" name=\"tid\" value=\"$tid\" />";
		$icon = $post['icon'];
	}
	
	// Otherwise, this is our initial visit to this page.
	else
	{
		if($mybb->user['signature'] != '')
		{
			$postoptionschecked['signature'] = "checked";
		}
		if($mybb->user['emailnotify'] == "yes")
		{
			$postoptionschecked['emailnotify'] = "checked";
		}
		$numpolloptions = "2";
	}
	
	// If we're preving a post then generate the preview.
	if($mybb->input['previewpost'])
	{
		if(!$mybb->input['username'])
		{
			$mybb->input['username'] = $lang->guest;
		}
		if($mybb->input['username'] && !$mybb->user['uid'])
		{
			$mybb->user = validate_password_from_username($mybb->input['username'], $mybb->input['password']);
		}
		$query = $db->query("SELECT u.*, f.*, i.path as iconpath, i.name as iconname FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid='".intval($mybb->input['icon'])."') WHERE u.uid='".$mybb->user[uid]."'");
		$post = $db->fetch_array($query);
		if(!$mybb->user['uid'] || !$post['username'])
		{
			$post['username'] = $mybb->input['username'];
		}
		else
		{
			$post['userusername'] = $mybb->user['username'];
			$post['username'] = $mybb->user['username'];
		}
		$previewmessage = $mybb->input['message'];
		$post['message'] = $previewmessage;
		$post['subject'] = $mybb->input['subject'];
		$post['icon'] = $mybb->input['icon'];
		$post['smilieoff'] = $postoptions['disablesmilies'];
		$post['dateline'] = time();

		// Fetch attachments assigned to this post
		if($mybb->input['pid'])
		{
			$attachwhere = "pid='".intval($mybb->input['pid'])."'";
		}
		else
		{
			$attachwhere = "posthash='".addslashes($mybb->input['posthash'])."'";
		}

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE $attachwhere");
		while($attachment = $db->fetch_array($query)) {
			$attachcache[0][$attachment['aid']] = $attachment;
		}

		$postbit = makepostbit($post, 1);
		eval("\$preview = \"".$templates->get("previewpost")."\";");
		$message = htmlspecialchars_uni($mybb->input['message']);
		$subject = htmlspecialchars_uni($mybb->input['subject']);
	}
	
	// Removing an attachment or adding a new one, or showting thread errors.
	else if($mybb->input['removeattachment'] || $mybb->input['newattachment'] || $thread_errors) {
		$message = htmlspecialchars_uni($mybb->input['message']);
		$subject = htmlspecialchars_uni($mybb->input['subject']);
	}

	// Setup a unique posthash for attachment management
	if(!$mybb->input['posthash'] && $mybb->input['action'] != "editdraft")
	{
	    mt_srand ((double) microtime() * 1000000);
	    $posthash = md5($mybb->user['uid'].mt_rand());
	}
	else
	{
		$posthash = htmlspecialchars($mybb->input['posthash']);
	}

	// Can we disable smilies or are they disabled already?
	if($forum['allowsmilies'] != "no")
	{
		eval("\$disablesmilies = \"".$templates->get("newthread_disablesmilies")."\";");
	}
	else
	{
		$disablesmilies = "<input type=\"hidden\" name=\"postoptions[disablesmilies]\" value=\"no\" />";
	}

	// Show the moderator options
	if(ismod($fid) == "yes")
	{
		$modoptions = $mybb->input['modoptions'];
		if($modoptions['closethread'] == "yes")
		{
			$closecheck = "checked=\"checked\"";
		}
		else
		{
			$closecheck = '';
		}
		if($modoptions['stickthread'] == "yes")
		{
			$stickycheck = "checked=\"checked\"";
		}
		else
		{
			$stickycheck = '';
		}
		unset($modoptions);
		$bgcolor = "trow2";
		eval("\$modoptions = \"".$templates->get("newreply_modoptions")."\";");
		$bgcolor = "trow1";
	}
	else
	{
		$bgcolor = "trow2";
	}

	if($forumpermissions['canpostattachments'] != "no")
	{ // Get a listing of the current attachments, if there are any
		$attachcount = 0;
		if($mybb->input['action'] == "editdraft")
		{
			$attachwhere = "pid='$pid'";
		}
		else
		{
			$attachwhere = "posthash='".addslashes($posthash)."'";
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE $attachwhere");
		$attachments = '';
		while($attachment = $db->fetch_array($query))
		{
			$attachment['size'] = getfriendlysize($attachment['filesize']);
			$attachment['icon'] = getattachicon(getextention($attachment['filename']));
			if($forum['allowmycode'] != "no")
			{
				eval("\$postinsert = \"".$templates->get("post_attachments_attachment_postinsert")."\";");
			}
			eval("\$attachments .= \"".$templates->get("post_attachments_attachment")."\";");
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
			$friendlyquota = getfriendlysize($mybb->usergroup['attachquota']*1000);
		}
		$friendlyusage = getfriendlysize($usage['ausage']);
		$lang->attach_quota = sprintf($lang->attach_quota, $friendlyusage, $friendlyquota);
		if($mybb->settings['maxattachments'] == 0 || ($mybb->settings['maxattachments'] != 0 && $attachcount < $mybb->settings['maxattachments']) && !$noshowattach)
		{
			eval("\$newattach = \"".$templates->get("post_attachments_new")."\";");
		}
		eval("\$attachbox = \"".$templates->get("post_attachments")."\";");

		if($bgcolor == "trow1")
		{
			$bgcolor = "trow2";
		}
		else
		{
			$bgcolor = "trow1";
		}
	}

	if($mybb->user['uid'])
	{
		eval("\$savedraftbutton = \"".$templates->get("post_savedraftbutton")."\";");
	}

	if($forumpermissions['canpostpolls'] != "no")
	{
		$lang->max_options = sprintf($lang->max_options, $mybb->settings['maxpolloptions']);
		eval("\$pollbox = \"".$templates->get("newthread_postpoll")."\";");
	}
	$lang->newthread_in = sprintf($lang->newthread_in, $thread['subject']);

	$plugins->run_hooks("newthread_end");

	eval("\$newthread = \"".$templates->get("newthread")."\";");
	outputpage($newthread);

}
?>
