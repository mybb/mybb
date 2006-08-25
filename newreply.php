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

$templatelist = "newreply,previewpost,error_invalidforum,error_invalidthread,redirect_threadposted,loginbox,changeuserbox,posticons,newreply_threadreview,forumrules,attachments,newreply_threadreview_post";
$templatelist .= ",smilieinsert,codebuttons,post_attachments_new,post_attachments,post_savedraftbutton,newreply_modoptions,newreply_threadreview_more,newreply_disablesmilies";

require "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;
// Load global language phrases
$lang->load("newreply");

// Get the pid and tid from the input.
$pid = $mybb->input['pid'];
$tid = $mybb->input['tid'];

// Edit a draft post.
$draft_pid = 0;
$editdraftpid = '';
if($mybb->input['action'] == "editdraft" && $pid)
{
	$options = array(
		"limit" => 1
	);
	$query = $db->simple_select(TABLE_PREFIX."posts", "*", "pid=".$pid, $options);
	$post = $db->fetch_array($query);
	if(!$post['pid'])
	{
		error($lang->error_invalidpost);
	}
	$draft_pid = $post['pid'];
	$tid = $post['tid'];
	$editdraftpid = "<input type=\"hidden\" name=\"pid\" value=\"$draft_pid\" />";
}

// Set up $thread and $forum for later use.
$options = array(
	"limit" => 1
);
$query = $db->simple_select(TABLE_PREFIX."threads", "*", "tid='".$tid."'");
$thread = $db->fetch_array($query);
$fid = $thread['fid'];

// Get forum info
$forum = get_forum($fid);
if(!$forum)
{
	error($lang->error_invalidforum);
}

// Make navigation
build_forum_breadcrumb($fid);
$thread['subject'] = htmlspecialchars_uni($thread['subject']);
add_breadcrumb($thread['subject'], "showthread.php?tid=$thread[tid]");
add_breadcrumb($lang->nav_newreply);

$forumpermissions = forum_permissions($fid);

// See if everything is valid up to here.
if(isset($post) && (($post['visible'] == 0 && is_moderator($fid) != "yes") || $post['visible'] == 0))
{
	error($lang->error_invalidpost);
}
if(!$thread['subject'] || (($thread['visible'] == 0 && is_moderator($fid) != "yes") || $thread['visible'] < 0))
{
	error($lang->error_invalidthread);
}
if($forum['open'] == "no" || $forum['type'] != "f")
{
	error($lang->error_closedinvalidforum);
}
if($forumpermissions['canview'] == "no" || $forumpermissions['canpostreplys'] == "no")
{
	error_no_permission();
}

// Password protected forums ......... yhummmmy!
check_forum_password($fid, $forum['password']);

if($mybb->settings['bbcodeinserter'] != "off" && $forum['allowmycode'] != "no" && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
{
	$codebuttons = build_mycode_inserter();
	if($forum['allowsmilies'] != "no")
	{
		$smilieinserter = build_clickable_smilies();
	}
}

// Display a login box or change user box?
if($mybb->user['uid'] != 0)
{
	eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
}
else
{
	if(!$mybb->input['previewpost'] && $mybb->input['action'] != "do_newreply")
	{
		$username = $lang->guest;
	}
	elseif($mybb->input['previewpost'])
	{
		$username = $mybb->input['username'];
	}
	eval("\$loginbox = \"".$templates->get("loginbox")."\";");
}

// Check to see if the thread is closed, and if the user is a mod.
if(is_moderator($fid, "caneditposts") != "yes")
{
	if($thread['closed'] == "yes")
	{
		redirect("showthread.php?tid=$tid", $lang->redirect_threadclosed);
	}
}

// No weird actions allowed, show new reply form if no regular action.
if($mybb->input['action'] != "do_newreply" && $mybb->input['action'] != "editdraft")
{
	$mybb->input['action'] = "newreply";
}

// Even if we are previewing, still show the new reply form.
if($mybb->input['previewpost'])
{
	$mybb->input['action'] = "newreply";
}

if(!$mybb->input['attachmentaid'] && ($mybb->input['newattachment'] || ($mybb->input['action'] == "do_newreply" && $mybb->input['submit'] && $_FILES['attachment'])))
{
	// If there's an attachment, check it and upload it.
	if($_FILES['attachment']['size'] > 0 && $forumpermissions['canpostattachments'] != "no")
	{
		require_once MYBB_ROOT."inc/functions_upload.php";
		$attachedfile = upload_attachment($_FILES['attachment']);
	}
	if($attachedfile['error'])
	{
		eval("\$attacherror = \"".$templates->get("error_attacherror")."\";");
		$mybb->input['action'] = "newreply";
	}
	if(!$mybb->input['submit'])
	{
		$mybb->input['action'] = "newreply";
	}
}

// Remove an attachment.
if($mybb->input['attachmentaid'] && $mybb->input['posthash'])
{
	require_once MYBB_ROOT."inc/functions_upload.php";
	remove_attachment(0, $mybb->input['posthash'], $mybb->input['attachmentaid']);
	if(!$mybb->input['submit'])
	{
		$mybb->input['action'] = "newreply";
	}
}

// Setup our posthash for managing attachments.
if(!$mybb->input['posthash'] && $mybb->input['action'] != "editdraft")
{
	mt_srand ((double) microtime() * 1000000);
	$mybb->input['posthash'] = md5($thread['tid'].$mybb->user['uid'].mt_rand());
}

$reply_errors = "";
$hide_captcha = false;
if($mybb->input['action'] == "do_newreply" && $mybb->request_method == "post")
{
	$plugins->run_hooks("newreply_do_newreply_start");

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
			
			//Checks to make sure the user can login; they haven't had too many tries at logging in.
			//Is a fatal call if user has had too many tries
			$logins = login_attempt_check();		

			// If the user specified a password but it is wrong, throw back invalid password.
			$mybb->user = validate_password_from_username($mybb->input['username'], $mybb->input['password']);
			if(!$mybb->user['uid'])
			{
				mysetcookie('loginattempts', $logins + 1);
				$db->query("UPDATE ".TABLE_PREFIX."sessions SET loginattempts=loginattempts+1 WHERE sid = '{$session->sid}'");
				if($mybb->settings['failedlogintext'] == "yes")
				{
					$login_text = sprintf($lang->failed_login_again, $mybb->settings['failedlogincount'] - $logins);
				}				
				error($lang->error_invalidpassword.$login_text);
			}
			// Otherwise they've logged in successfully.

			$mybb->input['username'] = $username = $mybb->user['username'];
			mysetcookie("mybbuser", $mybb->user['uid']."_".$mybb->user['loginkey']);
			mysetcookie('loginattempts', 1);
			
			// Update the session to contain their user ID
			$updated_session = array(
				"uid" => $mybb->user['uid'],
				"loginattempts" => 0
			);
			$db->update_query(TABLE_PREFIX."sessions", $updated_session, "sid='{$session->sid}'");

			// Set uid and username
			$uid = $mybb->user['uid'];
			$username = $mybb->user['username'];
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
				$username = htmlspecialchars($mybb->input['username']);
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

	// Attempt to see if this post is a duplicate or not
	if($uid > 0)
	{
		$user_check = "p.uid='{$uid}'";
	}
	else
	{
		$user_check = "p.ipaddress='{$session->ipaddress}'";
	}
	if(!$mybb->input['savedraft'])
	{
		$query = $db->simple_select(TABLE_PREFIX."posts p", "p.pid", "{$user_check} AND p.tid='{$thread['tid']}' AND p.subject='".$db->escape_string($mybb->input['subject'])."' AND p.message='".$db->escape_string($mybb->input['message'])."' AND p.posthash='".$db->escape_string($mybb->input['posthash'])."'");
		$duplicate_check = $db->fetch_field($query, "pid");
		if($duplicate_check)
		{
			error($lang->error_post_already_submitted);
		}
	}
	
	// Set up posthandler.
	require_once MYBB_ROOT."inc/datahandlers/post.php";
	$posthandler = new PostDataHandler("insert");

	// Set the post data that came from the input to the $post array.
	$post = array(
		"tid" => $mybb->input['tid'],
		"replyto" => $mybb->input['replyto'],
		"fid" => $thread['fid'],
		"subject" => $mybb->input['subject'],
		"icon" => $mybb->input['icon'],
		"uid" => $uid,
		"username" => $username,
		"message" => $mybb->input['message'],
		"ipaddress" => get_ip(),
		"posthash" => $mybb->input['posthash']
	);

	if($mybb->input['pid'])
	{
		$post['pid'] = $mybb->input['pid'];
	}

	// Are we saving a draft post?
	if($mybb->input['savedraft'] && $mybb->user['uid'])
	{
		$post['savedraft'] = 1;
	}
	else
	{
		$post['savedraft'] = 0;
	}

	// Set up the post options from the input.
	$post['options'] = array(
		"signature" => $mybb->input['postoptions']['signature'],
		"emailnotify" => $mybb->input['postoptions']['emailnotify'],
		"disablesmilies" => $mybb->input['postoptions']['disablesmilies']
	);

	// Apply moderation options if we have them
	$post['modoptions'] = $mybb->input['modoptions'];

	$posthandler->set_data($post);

	// Now let the post handler do all the hard work.
	$valid_post = $posthandler->validate_post();

	$post_errors = array();
	// Fetch friendly error messages if this is an invalid post
	if(!$valid_post)
	{
		$post_errors = $posthandler->get_friendly_errors();
	}

	// Check captcha image
	if($mybb->settings['captchaimage'] == "on" && function_exists("imagepng") && !$mybb->user['uid'])
	{
		$imagehash = $db->escape_string($mybb->input['imagehash']);
		$imagestring = $db->escape_string($mybb->input['imagestring']);
		$query = $db->simple_select(TABLE_PREFIX."captcha", "*", "imagehash='$imagehash'");
		$imgcheck = $db->fetch_array($query);
		if(strtolower($imgcheck['imagestring']) != strtolower($imagestring) || !$imgcheck['imagehash'])
		{
			$post_errors[] = $lang->invalid_captcha;
		}
		else
		{
			$db->delete_query(TABLE_PREFIX."captcha", "imagehash='$imagehash'");
			$hide_captcha = true;
		}
	}

	// One or more erors returned, fetch error list and throw to newreply page
	if(count($post_errors) > 0)
	{
		$reply_errors = inline_error($post_errors);
		$mybb->input['action'] = "newreply";
	}
	else
	{
		$postinfo = $posthandler->insert_post();
		$pid = $postinfo['pid'];
		$visible = $postinfo['visible'];

		// Deciding the fate
		if($visible == -2)
		{
			// Draft post
			$lang->redirect_newreply = $lang->draft_saved;
			$url = "usercp.php?action=drafts";
		}
		elseif($visible == 1)
		{
			// Visible post
			$lang->redirect_newreply .= $lang->redirect_newreply_post;
			$url = "showthread.php?tid=$tid&pid=$pid#pid$pid";
		}
		else
		{
			// Moderated post
			$lang->redirect_newreply .= $lang->redirect_newreply_moderation;
			$url = "showthread.php?tid=$tid";
		}

		// Mark any quoted posts so they're no longer selected - attempts to maintain those which weren't selected
		if($mybb->input['quoted_ids'] && $_COOKIE['multiquote'] && $mybb->settings['multiquote'] != "off")
		{
			// We quoted all posts - remove the entire cookie
			if($mybb->input['quoted_ids'] == "all")
			{
				myunsetcookie("multiquote");
			}
			// Only quoted a few - attempt to remove them from the cookie
			else
			{
				$quoted_ids = explode("|", $mybb->input['quoted_ids']);
				$multiquote = explode("|", $_COOKIE['multiquote']);
				if(is_array($multiquote) && is_array($quoted_ids))
				{
					foreach($multiquote as $key => $quoteid)
					{
						// If this ID was quoted, remove it from the multiquote list
						if(in_array($quoteid, $quoted_ids))
						{
							unset($multiquote[$key]);
						}
					}
					// Still have an array - set the new cookie
					if(is_array($multiquote))
					{
						$new_multiquote = implode(",", $multiquote);
						mysetcookie("multiquote", $new_multiquote);
					}
					// Otherwise, unset it
					else
					{
						myunsetcookie("multiquote");
					}
				}
			}
		}

		$plugins->run_hooks("newreply_do_newreply_end");

		$lang->redirect_newreply .= sprintf($lang->redirect_return_forum, $fid);
		redirect($url, $lang->redirect_newreply);
	}
}

// Show the newreply form.
if($mybb->input['action'] == "newreply" || $mybb->input['action'] == "editdraft")
{
	$plugins->run_hooks("newreply_start");

	$quote_ids = '';
	// If this isn't a preview and we're not editing a draft, then handle quoted posts
	if(!$mybb->input['previewpost'] && !$reply_errors && $mybb->input['action'] != "editdraft")
	{
		$message = '';
		$quoted_posts = array();
		// Handle multiquote
		if($_COOKIE['multiquote'] && $mybb->settings['multiquote'] != "off")
		{
			$multiquoted = explode("|", $_COOKIE['multiquote']);
			foreach($multiquoted as $post)
			{
				$quoted_posts[$post] = intval($post);
			}
		}
		// Handle incoming 'quote' button
		if($mybb->input['pid'])
		{
			$quoted_posts[$mybb->input['pid']] = $mybb->input['pid'];
		}

		// Quoting more than one post - fetch them
		if(count($quoted_posts) > 0)
		{
			$external_quotes = 0;
			$quoted_posts = implode(",", $quoted_posts);
			$unviewable_forums = get_unviewable_forums();
			if($unviewable_forums)
			{
				$unviewable_forums = "AND t.fid NOT IN ({$unviewable_forums})";
			}
			if(is_moderator($fid))
			{
				$visible_where = "AND p.visible != 2";
			}
			else
			{
				$visible_where = "AND p.visible > 0";
			}
			$query = $db->query("
				SELECT p.subject, p.message, p.pid, p.tid, p.username, u.username AS userusername
				FROM ".TABLE_PREFIX."posts p
				LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
				WHERE p.pid IN ($quoted_posts) {$unviewable_forums} {$visible_where}
			");
			while($quoted_post = $db->fetch_array($query))
			{
				// Only show messages for the current thread
				if($quoted_post['tid'] == $tid)
				{
					// If this post was the post for which a quote button was clicked, set the subject
					if($pid == $quoted_post['pid'])
					{
						$subject = preg_replace('#RE:\s?#i', '', $quoted_post['subject']);
						$subject = "RE: ".$subject;
					}
					if($quoted_post['userusername'])
					{
						$quoted_post['username'] = $quoted_post['userusername'];
					}
					$quoted_post['message'] = preg_replace('#(^|\r|\n)/me ([^\r\n<]*)#i', "\\1* {$quoted_post['username']} \\2</span>", $quoted_post['message']);
					$quoted_post['message'] = preg_replace('#(^|\r|\n)/slap ([^\r\n<]*)#i', "\\1* {$quoted_post['username']} {$lang->slaps} \\2 {$lang->with_trout}</span>", $quoted_post['message']);
					$quoted_post['message'] = preg_replace("#\[attachment=([0-9]+?)\]#i", '', $quoted_post['message']);
					$message .= "[quote={$quoted_post['username']}]\n{$quoted_post['message']}\n[/quote]\n\n";
					$quoted_ids[] = $quoted_post['pid'];
				}
				// Count the rest
				else
				{
					++$external_quotes;
				}
			}
			if($external_quotes > 0)
			{
				if($external_quotes == 1)
				{
					$multiquote_text = $lang->multiquote_external_one;
					$multiquote_deselect = $lang->multiquote_external_one_deselect;
					$multiquote_quote = $lang->multiquote_external_one_quote;
				}
				else
				{
					$multiquote_text = sprintf($lang->multiquote_external, $external_quotes);
					$multiquote_deselect = $lang->multiquote_external_deselect;
					$multiquote_quote = $lang->multiquote_external_quote;
				}
				eval("\$multiquote_external = \"".$templates->get("newreply_multiquote_external")."\";");
			}
			if(count($quoted_ids) > 0)
			{
				$quoted_ids = implode("|", $quoted_ids);
			}
		}
	}


	if($mybb->input['quoted_ids'])
	{
		$quoted_ids = htmlspecialchars_uni($mybb->input['quoted_ids']);
	}

	if($mybb->input['previewpost'])
	{
		$previewmessage = $mybb->input['message'];
	}
	if(!$message)
	{
		$message = $mybb->input['message'];
	}
	$message = htmlspecialchars_uni($message);

	// Set up the post options.
	if($mybb->input['previewpost'] || $maximageserror || $reply_errors != '')
	{
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
		$subject = $mybb->input['subject'];
	}
	elseif($mybb->input['action'] == "editdraft" && $mybb->user['uid'])
	{
		$message = htmlspecialchars_uni($post['message']);
		$subject = $post['subject'];
		if($post['includesig'] != "no")
		{
			$postoptionschecked['signature'] = "checked=\"checked\"";
		}
		if($post['smilieoff'] == "yes")
		{
			$postoptionschecked['disablesmilies'] = "checked=\"checked\"";
		}
		$mybb->input['icon'] = $post['icon'];
	}
	else
	{
		if($mybb->user['signature'] != '')
		{
			$postoptionschecked['signature'] = "checked=\"checked\"";
		}
		if($mybb->user['emailnotify'] == "yes")
		{
			$postoptionschecked['emailnotify'] = "checked=\"checked\"";
		}
	}
	if($forum['allowpicons'] != "no")
	{
		$posticons = get_post_icons();
	}

	// Preview a post that was written.
	if($mybb->input['previewpost'])
	{
		$quote_ids = htmlspecialchars_uni($mybb->input['quote_ids']);
		if(!$mybb->input['username'])
		{
			$mybb->input['username'] = $lang->guest;
		}
		if($mybb->input['username'] && !$mybb->user['uid'])
		{
			$mybb->user = validate_password_from_username($mybb->input['username'], $mybb->input['password']);
		}
		$mybb->input['icon'] = intval($mybb->input['icon']);
		$query = $db->query("
			SELECT u.*, f.*
			FROM ".TABLE_PREFIX."users u
			LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
			WHERE u.uid='".$mybb->user['uid']."'
		");
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
		$post['message'] = $previewmessage;
		$post['subject'] = $subject;
		$post['icon'] = $mybb->input['icon'];
		$post['smilieoff'] = $postoptions['disablesmilies'];
		$post['dateline'] = time();
		$post['includesig'] = $mybb->input['postoptions']['signature'];
		if($post['includesig'] != "yes")
		{
			$post['includesig'] = "no";
		}

		// Fetch attachments assigned to this post.
		if($mybb->input['pid'])
		{
			$attachwhere = "pid='".intval($mybb->input['pid'])."'";
		}
		else
		{
			$attachwhere = "posthash='".$db->escape_string($mybb->input['posthash'])."'";
		}

		$query = $db->simple_select(TABLE_PREFIX."attachments", "*", $attachwhere);
		while($attachment = $db->fetch_array($query))
		{
			$attachcache[0][$attachment['aid']] = $attachment;
		}

		$postbit = build_postbit($post, 1);
		eval("\$preview = \"".$templates->get("previewpost")."\";");
	}
	$subject = htmlspecialchars_uni($subject);

	if(!$pid && !$mybb->input['previewpost'])
	{
		$subject = "RE: " . $thread['subject'];
	}
	// Setup a unique posthash for attachment management
	if(!$mybb->input['posthash'] && $mybb->input['action'] != "editdraft")
	{
	    mt_srand ((double) microtime() * 1000000);
	    $posthash = md5($mybb->user['uid'].mt_rand());
	}
	else
	{
		$posthash = $mybb->input['posthash'];
	}

	// Get a listing of the current attachments.
	$bgcolor = "trow2";
	if($forumpermissions['canpostattachments'] != "no")
	{
		$attachcount = 0;
		if($mybb->input['action'] == "editdraft")
		{
			$attachwhere = "pid='$pid'";
		}
		else
		{
			$attachwhere = "posthash='".$db->escape_string($posthash)."'";
		}
		$attachments = '';
		$query = $db->simple_select(TABLE_PREFIX."attachments", "*", $attachwhere);
		while($attachment = $db->fetch_array($query))
		{
			$attachment['size'] = get_friendly_size($attachment['filesize']);
			$attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
			if($forum['allowmycode'] != "no")
			{
				eval("\$postinsert = \"".$templates->get("post_attachments_attachment_postinsert")."\";");
			}
			$attach_mod_options = '';
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
		$query = $db->simple_select(TABLE_PREFIX."attachments", "SUM(filesize) AS ausage", "uid='".$mybb->user['uid']."'");
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
		if($mybb->settings['maxattachments'] == 0 || ($mybb->settings['maxattachments'] != 0 && $attachcount < $mybb->settings['maxattachments']) && !$noshowattach)
		{
			eval("\$newattach = \"".$templates->get("post_attachments_new")."\";");
		}
		eval("\$attachbox = \"".$templates->get("post_attachments")."\";");
		$bgcolor = "trow1";
	}

	// If the user is logged in, provide a save draft button.
	if($mybb->user['uid'])
	{
		eval("\$savedraftbutton = \"".$templates->get("post_savedraftbutton")."\";");
	}

	// Show captcha image for guests if enabled
	if($mybb->settings['captchaimage'] == "on" && function_exists("imagepng") && !$mybb->user['uid'])
	{
		$correct = false;
		// If previewing a post - check their current captcha input - if correct, hide the captcha input area
		if($mybb->input['previewpost'] || $hide_captcha == true)
		{
			$imagehash = $db->escape_string($mybb->input['imagehash']);
			$imagestring = $db->escape_string($mybb->input['imagestring']);
			$query = $db->simple_select(TABLE_PREFIX."captcha", "*", "imagehash='$imagehash' AND imagestring='$imagestring'");
			$imgcheck = $db->fetch_array($query);
			if($imgcheck['dateline'] > 0)
			{
				eval("\$captcha = \"".$templates->get("post_captcha_hidden")."\";");
				$correct = true;
			}
			else
			{
				$db->delete_query(TABLE_PREFIX."captcha", "imagehash='$imagehash'");
			}
		}
		if(!$correct)
		{
			$randomstr = random_str(5);
			$imagehash = md5($randomstr);
			$imagearray = array(
				"imagehash" => $imagehash,
				"imagestring" => $randomstr,
				"dateline" => time()
				);
			$db->insert_query(TABLE_PREFIX."captcha", $imagearray);
			eval("\$captcha = \"".$templates->get("post_captcha")."\";");
		}
	}

	if($mybb->settings['threadreview'] != "off")
	{
		if(is_moderator($fid) == "yes")
		{
			$visibility = "(visible='1' OR visible='0')";
		}
		else
		{
			$visibility = "visible='1'";
		}
		$query = $db->simple_select(TABLE_PREFIX."posts", "COUNT(pid) AS post_count", "tid='{$tid}' AND {$visibility}");
		$numposts = $db->fetch_field($query, "post_count");

		if($numposts > $mybb->settings['postsperpage'])
		{
			$numposts = $mybb->settings['postsperpage'];
			$lang->thread_review_more = sprintf($lang->thread_review_more, $mybb->settings['postsperpage'], $tid);
			eval("\$reviewmore = \"".$templates->get("newreply_threadreview_more")."\";");
		}

		$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid='{$tid}' AND {$visibility}", array("order_by" => "dateline", "order_dir" => "desc", "limit" => $mybb->settings['postsperpage']));
		while($post = $db->fetch_array($query))
		{
			$pidin[] = $post['pid'];
		}

		$pidin = implode(",", $pidin);

		// Fetch attachments
		$query = $db->simple_select(TABLE_PREFIX."attachments", "*", "pid IN ($pidin)");
		while($attachment = $db->fetch_array($query))
		{
			$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
		}
		$query = $db->query("
			SELECT p.*, u.username AS userusername
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid)
			WHERE pid IN ($pidin)
			ORDER BY dateline DESC
		");
		$postsdone = 0;
		$altbg = "trow1";
		$reviewbits = '';
		while($post = $db->fetch_array($query))
		{
			if($post['userusername'])
			{
				$post['username'] = $post['userusername'];
			}
			$reviewpostdate = mydate($mybb->settings['dateformat'], $post['dateline']);
			$reviewposttime = mydate($mybb->settings['timeformat'], $post['dateline']);
			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode'],
				"me_username" => $post['username']
			);
			if($post['smilieoff'] == "yes")
			{
				$parser_options['allow_smilies'] = "no";
			}

			if($post['visible'] != 1)
			{
				$altbg = "trow_shaded";
			}

			$post['message'] = $parser->parse_message($post['message'], $parser_options);
			get_post_attachments($post['pid'], $post);
			$reviewmessage = $post['message'];
			eval("\$reviewbits .= \"".$templates->get("newreply_threadreview_post")."\";");
			if($altbg == "trow1")
			{
				$altbg = "trow2";
			}
			else
			{
				$altbg = "trow1";
			}
		}
		eval("\$threadreview = \"".$templates->get("newreply_threadreview")."\";");
	}
	// Can we disable smilies or are they disabled already?
	if($forum['allowsmilies'] != "no")
	{
		eval("\$disablesmilies = \"".$templates->get("newreply_disablesmilies")."\";");
	}
	else
	{
		$disablesmilies = "<input type=\"hidden\" name=\"postoptions[disablesmilies]\" value=\"no\" />";
	}
	// Show the moderator options.
	if(is_moderator($fid) == "yes")
	{
		if($thread['closed'] == "yes")
		{
			$closecheck = "checked";
		}
		else
		{
			$closecheck = '';
		}
		if($thread['sticky'])
		{
			$stickycheck = "checked";
		}
		else
		{
			$stickycheck = '';
		}
		eval("\$modoptions = \"".$templates->get("newreply_modoptions")."\";");
	}
	$lang->post_reply_to = sprintf($lang->post_reply_to, $thread['subject']);
	$lang->reply_to = sprintf($lang->reply_to, $thread['subject']);

	$plugins->run_hooks("newreply_end");

	eval("\$newreply = \"".$templates->get("newreply")."\";");
	output_page($newreply);
}
?>
