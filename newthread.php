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
define('THIS_SCRIPT', 'newthread.php');

$templatelist = "newthread,previewpost,loginbox,changeuserbox,newthread_postpoll,posticons,codebuttons,postbit,post_attachments_attachment_unapproved,newreply_modoptions_close,newreply_modoptions_stick";
$templatelist .= ",newthread_disablesmilies,post_attachments_new,post_attachments,post_savedraftbutton,post_subscription_method,post_attachments_attachment_remove,postbit_warninglevel_formatted,postbit_icon";
$templatelist .= ",forumdisplay_rules,forumdisplay_rules_link,post_attachments_attachment_postinsert,post_attachments_attachment,newthread_signature,post_prefixselect_prefix,post_prefixselect_single,posticons_icon";
$templatelist .= ",post_captcha_hidden,post_captcha_recaptcha_invisible,post_captcha_nocaptcha,post_captcha_hcaptcha_invisible,post_captcha_hcaptcha,post_javascript,postbit_gotopost,newthread_postoptions,post_attachments_add,post_attachments_viewlink";
$templatelist .= ",postbit_avatar,postbit_find,postbit_pm,postbit_rep_button,postbit_www,postbit_email,postbit_reputation,postbit_warn,postbit_warninglevel,postbit_author_user,postbit_author_guest,post_captcha";
$templatelist .= ",postbit_signature,postbit_classic,postbit_attachments_thumbnails_thumbnail,postbit_attachments_images_image,postbit_attachments_attachment,postbit_attachments_attachment_unapproved";
$templatelist .= ",postbit_attachments_thumbnails,postbit_attachments_images,postbit_attachments,postbit_reputation_formatted_link,post_attachments_update,postbit_offline,newreply_modoptions,newthread_multiquote_external";
$templatelist .= ",postbit_profilefield_multiselect_value,postbit_profilefield_multiselect,newthread_draftinput,global_moderation_notice,postbit_online,postbit_away,attachment_icon,postbit_userstar,postbit_groupimage";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/functions_upload.php";

// Load global language phrases
$lang->load("newthread");

$tid = $pid = 0;
$mybb->input['action'] = $mybb->get_input('action');
$mybb->input['tid'] = $mybb->get_input('tid', MyBB::INPUT_INT);
$mybb->input['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
if($mybb->input['action'] == "editdraft" || ($mybb->get_input('savedraft') && $mybb->input['tid']) || ($mybb->input['tid'] && $mybb->input['pid']))
{
	$thread = get_thread($mybb->input['tid']);

	$query = $db->simple_select("posts", "*", "tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."' AND visible='-2'", array('order_by' => 'dateline, pid', 'limit' => 1));
	$post = $db->fetch_array($query);

	if(!$thread['tid'] || !$post['pid'] || $thread['visible'] != -2 || $thread['uid'] != $mybb->user['uid'])
	{
		error($lang->invalidthread);
	}

	$pid = $post['pid'];
	$fid = $thread['fid'];
	$tid = $thread['tid'];
	eval("\$editdraftpid = \"".$templates->get("newthread_draftinput")."\";");
}
else
{
	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
	$editdraftpid = '';
}

// Fetch forum information.
$forum = get_forum($fid);
if(!$forum)
{
	error($lang->error_invalidforum);
}

// Draw the navigation
build_forum_breadcrumb($fid);
add_breadcrumb($lang->nav_newthread);

$forumpermissions = forum_permissions($fid);

if($forum['open'] == 0 || $forum['type'] != "f" || $forum['linkto'] != "")
{
	error($lang->error_closedinvalidforum);
}

if($forumpermissions['canview'] == 0 || $forumpermissions['canpostthreads'] == 0)
{
	error_no_permission();
}

if($mybb->user['suspendposting'] == 1)
{
	$suspendedpostingtype = $lang->error_suspendedposting_permanent;
	if($mybb->user['suspensiontime'])
	{
		$suspendedpostingtype = $lang->sprintf($lang->error_suspendedposting_temporal, my_date($mybb->settings['dateformat'], $mybb->user['suspensiontime']));
	}

	$lang->error_suspendedposting = $lang->sprintf($lang->error_suspendedposting, $suspendedpostingtype, my_date($mybb->settings['timeformat'], $mybb->user['suspensiontime']));

	error($lang->error_suspendedposting);
}

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);

// If MyCode is on for this forum and the MyCode editor is enabled in the Admin CP, draw the code buttons and smilie inserter.
$codebuttons = '';
$smilieinserter = '';
if($mybb->settings['bbcodeinserter'] != 0 && $forum['allowmycode'] != 0 && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
{
	$codebuttons = build_mycode_inserter("message", $forum['allowsmilies']);
	if($forum['allowsmilies'] != 0)
	{
		$smilieinserter = build_clickable_smilies();
	}
}

// Does this forum allow post icons? If so, fetch the post icons.
if($forum['allowpicons'] != 0)
{
	$posticons = get_post_icons();
}

// If we have a currently logged in user then fetch the change user box.
if($mybb->user['uid'] != 0)
{
	$mybb->user['username'] = htmlspecialchars_uni($mybb->user['username']);
	eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
}

// Otherwise we have a guest, determine the "username" and get the login box.
else
{
	if(!isset($mybb->input['previewpost']) && $mybb->input['action'] != "do_newthread")
	{
		$username = '';
	}
	else
	{
		$username = htmlspecialchars_uni($mybb->get_input('username'));
	}
	eval("\$loginbox = \"".$templates->get("loginbox")."\";");
}

// If we're not performing a new thread insert and not editing a draft then we're posting a new thread.
if($mybb->input['action'] != "do_newthread" && $mybb->input['action'] != "editdraft")
{
	$mybb->input['action'] = "newthread";
}

// Previewing a post, overwrite the action to the new thread action.
if(!empty($mybb->input['previewpost']))
{
	$mybb->input['action'] = "newthread";
}

// Setup a unique posthash for attachment management
if(!$mybb->get_input('posthash') && !$pid)
{
	$mybb->input['posthash'] = md5($mybb->user['uid'].random_str());
}

if((empty($_POST) && empty($_FILES)) && $mybb->get_input('processed', MyBB::INPUT_INT) == 1)
{
	error($lang->error_empty_post_input);
}

$errors = array();
$maximageserror = $attacherror = '';

// Handle attachments if we've got any.
if($mybb->settings['enableattachments'] == 1 && ($mybb->get_input('newattachment') || $mybb->get_input('updateattachment') || ((($mybb->input['action'] == "do_newthread" && $mybb->get_input('submit')) || ($mybb->input['action'] == "newthread" && isset($mybb->input['previewpost'])) || isset($mybb->input['savedraft'])) && $_FILES['attachments'])))
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->input['action'] == "editdraft" || ($mybb->input['tid'] && $mybb->input['pid']))
	{
		$attachwhere = "pid='{$pid}'";
	}
	else
	{
		$attachwhere = "posthash='".$db->escape_string($mybb->get_input('posthash'))."'";
	}

	$ret = add_attachments($pid, $forumpermissions, $attachwhere, "newthread");

	if($mybb->get_input('ajax', MyBB::INPUT_INT) == 1)
	{
		if(isset($ret['success']))
		{
			$attachment = array('aid'=>'{1}', 'icon'=>'{2}', 'filename'=>'{3}', 'size'=>'{4}');
			if($mybb->settings['bbcodeinserter'] != 0 && $forum['allowmycode'] != 0 && $mybb->user['showcodebuttons'] != 0)
			{
				eval("\$postinsert = \"".$templates->get("post_attachments_attachment_postinsert")."\";");
			}
			eval("\$attach_rem_options = \"".$templates->get("post_attachments_attachment_remove")."\";");
			$attach_mod_options = '';
			eval("\$attemplate = \"".$templates->get("post_attachments_attachment")."\";");
			$ret['template'] = $attemplate;

			$query = $db->simple_select("attachments", "SUM(filesize) AS ausage", "uid='".$mybb->user['uid']."'");
			$usage = $db->fetch_array($query);
			$ret['usage'] = get_friendly_size($usage['ausage']);
		}
		
		header("Content-type: application/json; charset={$lang->settings['charset']}");
		echo json_encode($ret);
		exit();
	}

	if(!empty($ret['errors']))
	{
		$errors = $ret['errors'];
	}

	// If we were dealing with an attachment but didn't click 'Post Thread' or 'Save as Draft', force the new thread page again.
	if(!$mybb->get_input('submit') && !$mybb->get_input('savedraft'))
	{
		$mybb->input['action'] = "newthread";
	}
}

detect_attachmentact();

// Are we removing an attachment from the thread?
if($mybb->settings['enableattachments'] == 1 && $mybb->get_input('attachmentaid', MyBB::INPUT_INT) && $mybb->get_input('attachmentact') == "remove")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	remove_attachment($pid, $mybb->get_input('posthash'), $mybb->get_input('attachmentaid', MyBB::INPUT_INT));

	if(!$mybb->get_input('submit'))
	{
		$mybb->input['action'] = "newthread";
	}

	if($mybb->get_input('ajax', MyBB::INPUT_INT) == 1)
	{
		$query = $db->simple_select("attachments", "SUM(filesize) AS ausage", "uid='".$mybb->user['uid']."'");
		$usage = $db->fetch_array($query);

		header("Content-type: application/json; charset={$lang->settings['charset']}");
		echo json_encode(array("success" => true, "usage" => get_friendly_size($usage['ausage'])));
		exit();
	}
}

$thread_errors = "";
$hide_captcha = false;

// Check the maximum posts per day for this user
if($mybb->usergroup['maxposts'] > 0)
{
	$daycut = TIME_NOW-60*60*24;
	$query = $db->simple_select("posts", "COUNT(*) AS posts_today", "uid='{$mybb->user['uid']}' AND visible !='-1' AND dateline>{$daycut}");
	$post_count = $db->fetch_field($query, "posts_today");
	if($post_count >= $mybb->usergroup['maxposts'])
	{
		$lang->error_maxposts = $lang->sprintf($lang->error_maxposts, $mybb->usergroup['maxposts']);
		error($lang->error_maxposts);
	}
}

// Performing the posting of a new thread.
if($mybb->input['action'] == "do_newthread" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("newthread_do_newthread_start");

	// If this isn't a logged in user, then we need to do some special validation.
	if($mybb->user['uid'] == 0)
	{
		// If they didn't specify a username leave blank so $lang->guest can be used on output
		if(!$mybb->get_input('username'))
		{
			$username = '';
		}
		// Otherwise use the name they specified.
		else
		{
			$username = $mybb->get_input('username');
		}
		$uid = 0;

		if(!$mybb->user['uid'] && $mybb->settings['stopforumspam_on_newthread'])
		{
			require_once MYBB_ROOT . '/inc/class_stopforumspamchecker.php';

			$stop_forum_spam_checker = new StopForumSpamChecker(
				$plugins,
				$mybb->settings['stopforumspam_min_weighting_before_spam'],
				$mybb->settings['stopforumspam_check_usernames'],
				$mybb->settings['stopforumspam_check_emails'],
				$mybb->settings['stopforumspam_check_ips'],
				$mybb->settings['stopforumspam_log_blocks']
			);

			try {
				if($stop_forum_spam_checker->is_user_a_spammer($mybb->get_input('username'), '', get_ip()))
				{
					$errors[] = $lang->sprintf($lang->error_stop_forum_spam_spammer,
						$stop_forum_spam_checker->getErrorText(array(
							'stopforumspam_check_usernames',
							'stopforumspam_check_ips'
							)));
				}
			}
			catch (Exception $e)
			{
				if($mybb->settings['stopforumspam_block_on_error'])
				{
					$errors[] = $lang->error_stop_forum_spam_fetching;
				}
			}
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
		$user_check = "p.ipaddress=".$db->escape_binary($session->packedip);
	}
	if(!$mybb->get_input('savedraft') && !$pid)
	{
		$query = $db->simple_select("posts p", "p.pid", "$user_check AND p.fid='{$forum['fid']}' AND p.subject='".$db->escape_string($mybb->get_input('subject'))."' AND p.message='".$db->escape_string($mybb->get_input('message'))."' AND p.dateline>".(TIME_NOW-600));
		if($db->num_rows($query) > 0)
		{
			error($lang->error_post_already_submitted);
		}
	}

	// Set up posthandler.
	require_once MYBB_ROOT."inc/datahandlers/post.php";
	$posthandler = new PostDataHandler("insert");
	$posthandler->action = "thread";

	// Set the thread data that came from the input to the $thread array.
	$new_thread = array(
		"fid" => $forum['fid'],
		"subject" => $mybb->get_input('subject'),
		"prefix" => $mybb->get_input('threadprefix', MyBB::INPUT_INT),
		"icon" => $mybb->get_input('icon', MyBB::INPUT_INT),
		"uid" => $uid,
		"username" => $username,
		"message" => $mybb->get_input('message'),
		"ipaddress" => $session->packedip,
		"posthash" => $mybb->get_input('posthash')
	);

	if($pid != '')
	{
		$new_thread['pid'] = $pid;
	}

	// Are we saving a draft thread?
	if($mybb->get_input('savedraft') && $mybb->user['uid'])
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

	$postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
	if(!isset($postoptions['signature']))
	{
		$postoptions['signature'] = 0;
	}
	if(!isset($postoptions['subscriptionmethod']))
	{
		$postoptions['subscriptionmethod'] = 0;
	}
	if(!isset($postoptions['disablesmilies']))
	{
		$postoptions['disablesmilies'] = 0;
	}

	// Set up the thread options from the input.
	$new_thread['options'] = array(
		"signature" => $postoptions['signature'],
		"subscriptionmethod" => $postoptions['subscriptionmethod'],
		"disablesmilies" => $postoptions['disablesmilies']
	);

	// Apply moderation options if we have them
	$new_thread['modoptions'] = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);

	$posthandler->set_data($new_thread);

	// Now let the post handler do all the hard work.
	$valid_thread = $posthandler->validate_thread();

	$post_errors = array();
	// Fetch friendly error messages if this is an invalid thread
	if(!$valid_thread)
	{
		$post_errors = $posthandler->get_friendly_errors();
	}

	// Check captcha image
	if($mybb->settings['captchaimage'] && !$mybb->user['uid'])
	{
		require_once MYBB_ROOT.'inc/class_captcha.php';
		$post_captcha = new captcha;

		if($post_captcha->validate_captcha() == false)
		{
			// CAPTCHA validation failed
			foreach($post_captcha->get_errors() as $error)
			{
				$post_errors[] = $error;
			}
		}
		else
		{
			$hide_captcha = true;
		}
	}

	// One or more errors returned, fetch error list and throw to newthread page
	if(count($post_errors) > 0)
	{
		$thread_errors = inline_error($post_errors);
		$mybb->input['action'] = "newthread";
	}
	// No errors were found, it is safe to insert the thread.
	else
	{
		$thread_info = $posthandler->insert_thread();
		$tid = $thread_info['tid'];
		$visible = $thread_info['visible'];

		// Invalidate solved captcha
		if($mybb->settings['captchaimage'] && !$mybb->user['uid'])
		{
			$post_captcha->invalidate_captcha();
		}

		$force_redirect = false;

		// Mark thread as read
		require_once MYBB_ROOT."inc/functions_indicators.php";
		mark_thread_read($tid, $fid);

		// We were updating a draft thread, send them back to the draft listing.
		if($new_thread['savedraft'] == 1)
		{
			$lang->redirect_newthread = $lang->draft_saved;
			$url = "usercp.php?action=drafts";
		}

		// A poll was being posted with this thread, throw them to poll posting page.
		else if($mybb->get_input('postpoll', MyBB::INPUT_INT) && $forumpermissions['canpostpolls'])
		{
			$url = "polls.php?action=newpoll&tid=$tid&polloptions=".$mybb->get_input('numpolloptions', MyBB::INPUT_INT);
			$lang->redirect_newthread .= $lang->redirect_newthread_poll;
		}

		// This thread is stuck in the moderation queue, send them back to the forum.
		else if(!$visible)
		{
			// Moderated thread
			$lang->redirect_newthread .= $lang->redirect_newthread_moderation;
			$url = get_forum_link($fid);

			// User must see moderation notice, regardless of redirect settings
			$force_redirect = true;
		}

		// The thread is being made in a forum the user cannot see threads in, send them back to the forum.
		else if($visible == 1 && $forumpermissions['canviewthreads'] != 1)
		{
			$lang->redirect_newthread .= $lang->redirect_newthread_unviewable;
			$url = get_forum_link($fid);

			// User must see permission notice, regardless of redirect settings
			$force_redirect = true;
		}

		// This is just a normal thread - send them to it.
		else
		{
			// Visible thread
			$lang->redirect_newthread .= $lang->redirect_newthread_thread;
			$url = get_thread_link($tid);
		}

		// Mark any quoted posts so they're no longer selected - attempts to maintain those which weren't selected
		if(isset($mybb->input['quoted_ids']) && isset($mybb->cookies['multiquote']) && $mybb->settings['multiquote'] != 0)
		{
			// We quoted all posts - remove the entire cookie
			if($mybb->get_input('quoted_ids') == "all")
			{
				my_unsetcookie("multiquote");
			}
		}

		$plugins->run_hooks("newthread_do_newthread_end");

		// Hop to it! Send them to the next page.
		if(!$mybb->get_input('postpoll', MyBB::INPUT_INT))
		{
			$lang->redirect_newthread .= $lang->sprintf($lang->redirect_return_forum, get_forum_link($fid));
		}
		redirect($url, $lang->redirect_newthread, "", $force_redirect);
	}
}

if($mybb->input['action'] == "newthread" || $mybb->input['action'] == "editdraft")
{
	$plugins->run_hooks("newthread_start");

	// Do we have attachment errors?
	if(count($errors) > 0)
	{
		$thread_errors = inline_error($errors);
	}

	$multiquote_external = $quoted_ids = '';

	$subject = $message = '';
	// If this isn't a preview and we're not editing a draft, then handle quoted posts
	if(empty($mybb->input['previewpost']) && !$thread_errors && $mybb->input['action'] != "editdraft")
	{
		$quoted_posts = array();
		// Handle multiquote
		if(isset($mybb->cookies['multiquote']) && $mybb->settings['multiquote'] != 0)
		{
			$multiquoted = explode("|", $mybb->cookies['multiquote']);
			foreach($multiquoted as $post)
			{
				$quoted_posts[$post] = (int)$post;
			}
		}

		// Quoting more than one post - fetch them
		if(count($quoted_posts) > 0)
		{
			$external_quotes = 0;
			$quoted_posts = implode(",", $quoted_posts);
			$unviewable_forums = get_unviewable_forums();
			$inactiveforums = get_inactive_forums();
			if($unviewable_forums)
			{
				$unviewable_forums = "AND t.fid NOT IN ({$unviewable_forums})";
			}
			if($inactiveforums)
			{
				$inactiveforums = "AND t.fid NOT IN ({$inactiveforums})";
			}

			if(is_moderator($fid))
			{
				$visible_where = "AND p.visible != 2";
			}
			else
			{
				$visible_where = "AND p.visible > 0";
			}

			// Check group permissions if we can't view threads not started by us
			$group_permissions = forum_permissions();
			$onlyusfids = array();
			$onlyusforums = '';
			foreach($group_permissions as $gpfid => $forum_permissions)
			{
				if(isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1)
				{
					$onlyusfids[] = $gpfid;
				}
			}
			if(!empty($onlyusfids))
			{
				$onlyusforums = "AND ((t.fid IN(".implode(',', $onlyusfids).") AND t.uid='{$mybb->user['uid']}') OR t.fid NOT IN(".implode(',', $onlyusfids)."))";
			}

			if($mybb->get_input('load_all_quotes', MyBB::INPUT_INT) == 1)
			{
				$query = $db->query("
					SELECT p.subject, p.message, p.pid, p.tid, p.username, p.dateline, u.username AS userusername
					FROM ".TABLE_PREFIX."posts p
					LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
					LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
					WHERE p.pid IN ({$quoted_posts}) {$unviewable_forums} {$inactiveforums} {$onlyusforums} {$visible_where}
					ORDER BY p.dateline, p.pid
				");
				while($quoted_post = $db->fetch_array($query))
				{
					if($quoted_post['userusername'])
					{
						$quoted_post['username'] = $quoted_post['userusername'];
					}
					$quoted_post['message'] = preg_replace('#(^|\r|\n)/me ([^\r\n<]*)#i', "\\1* {$quoted_post['username']} \\2", $quoted_post['message']);
					$quoted_post['message'] = preg_replace('#(^|\r|\n)/slap ([^\r\n<]*)#i', "\\1* {$quoted_post['username']} {$lang->slaps} \\2 {$lang->with_trout}", $quoted_post['message']);
					$quoted_post['message'] = preg_replace("#\[attachment=([0-9]+?)\]#i", '', $quoted_post['message']);
					$message .= "[quote='{$quoted_post['username']}' pid='{$quoted_post['pid']}' dateline='{$quoted_post['dateline']}']\n{$quoted_post['message']}\n[/quote]\n\n";
				}

				$quoted_ids = "all";
			}
			else
			{
				$query = $db->query("
					SELECT COUNT(*) AS quotes
					FROM ".TABLE_PREFIX."posts p
					LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
					WHERE p.pid IN ({$quoted_posts}) {$unviewable_forums} {$inactiveforums} {$onlyusforums} {$visible_where}
				");
				$external_quotes = $db->fetch_field($query, 'quotes');

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
						$multiquote_text = $lang->sprintf($lang->multiquote_external, $external_quotes);
						$multiquote_deselect = $lang->multiquote_external_deselect;
						$multiquote_quote = $lang->multiquote_external_quote;
					}
					eval("\$multiquote_external = \"".$templates->get("newthread_multiquote_external")."\";");
				}
			}
		}
	}

	if(isset($mybb->input['quoted_ids']))
	{
		$quoted_ids = htmlspecialchars_uni($mybb->get_input('quoted_ids'));
	}

	$postoptionschecked = array('signature' => '', 'disablesmilies' => '');
	$subscribe = $nonesubscribe = $emailsubscribe = $pmsubscribe = '';
	$postpollchecked = '';

	// Check the various post options if we're
	// a -> previewing a post
	// b -> removing an attachment
	// c -> adding a new attachment
	// d -> have errors from posting

	if(!empty($mybb->input['previewpost']) || $mybb->get_input('attachmentaid', MyBB::INPUT_INT) || $mybb->get_input('newattachment') || $mybb->get_input('updateattachment') || $thread_errors)
	{
		$postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
		if(isset($postoptions['signature']) && $postoptions['signature'] == 1)
		{
			$postoptionschecked['signature'] = " checked=\"checked\"";
		}
		if(isset($postoptions['disablesmilies']) && $postoptions['disablesmilies'] == 1)
		{
			$postoptionschecked['disablesmilies'] = " checked=\"checked\"";
		}
		if($mybb->get_input('postpoll', MyBB::INPUT_INT) == 1)
		{
			$postpollchecked = "checked=\"checked\"";
		}
		$subscription_method = get_subscription_method($tid, $postoptions);
		$numpolloptions = $mybb->get_input('numpolloptions', MyBB::INPUT_INT);
	}

	// Editing a draft thread
	else if($mybb->input['action'] == "editdraft" && $mybb->user['uid'])
	{
		$mybb->input['threadprefix'] = $thread['prefix'];
		$message = htmlspecialchars_uni($post['message']);
		$subject = htmlspecialchars_uni($post['subject']);
		if($post['includesig'] != 0)
		{
			$postoptionschecked['signature'] = " checked=\"checked\"";
		}
		if($post['smilieoff'] == 1)
		{
			$postoptionschecked['disablesmilies'] = " checked=\"checked\"";
		}
		$icon = $post['icon'];
		if($forum['allowpicons'] != 0)
		{
			$posticons = get_post_icons();
		}
		$subscription_method = get_subscription_method($tid); // Subscription method doesn't get saved in drafts
		$numpolloptions = "2";
	}

	// Otherwise, this is our initial visit to this page.
	else
	{
		if($mybb->user['signature'] != '')
		{
			$postoptionschecked['signature'] = " checked=\"checked\"";
		}
		$subscription_method = get_subscription_method($tid); // Fresh thread, let the function set the appropriate method
		$numpolloptions = "2";
	}

	${$subscription_method.'subscribe'} = "checked=\"checked\" ";
	$preview = '';

	// If we're previewing a post then generate the preview.
	if(!empty($mybb->input['previewpost']))
	{
		// If this isn't a logged in user, then we need to do some special validation.
		if($mybb->user['uid'] == 0)
		{
			// If they didn't specify a username leave blank so $lang->guest can be used on output
			if(!$mybb->get_input('username'))
			{
				$username = '';
			}
			// Otherwise use the name they specified.
			else
			{
				$username = $mybb->get_input('username');
			}
			$uid = 0;
		}
		// This user is logged in.
		else
		{
			$username = $mybb->user['username'];
			$uid = $mybb->user['uid'];
		}

		// Set up posthandler.
		require_once MYBB_ROOT."inc/datahandlers/post.php";
		$posthandler = new PostDataHandler("insert");
		$posthandler->action = "thread";

		// Set the thread data that came from the input to the $thread array.
		$new_thread = array(
			"fid" => $forum['fid'],
			"prefix" => $mybb->get_input('threadprefix', MyBB::INPUT_INT),
			"subject" => $mybb->get_input('subject'),
			"icon" => $mybb->get_input('icon'),
			"uid" => $uid,
			"username" => $username,
			"message" => $mybb->get_input('message'),
			"ipaddress" => $session->packedip,
			"posthash" => $mybb->get_input('posthash')
		);

		if($pid != '')
		{
			$new_thread['pid'] = $pid;
		}

		$posthandler->set_data($new_thread);

		// Now let the post handler do all the hard work.
		$valid_thread = $posthandler->verify_message();
		$valid_subject = $posthandler->verify_subject();

		// guest post --> verify author
		if($new_thread['uid'] == 0)
		{
			$valid_username = $posthandler->verify_author();
		}
		else
		{
			$valid_username = true;
		}

		$post_errors = array();
		// Fetch friendly error messages if this is an invalid post
		if(!$valid_thread || !$valid_subject || !$valid_username)
		{
			$post_errors = $posthandler->get_friendly_errors();
		}

		// One or more errors returned, fetch error list and throw to newreply page
		if(count($post_errors) > 0)
		{
			$thread_errors = inline_error($post_errors);
		}
		else
		{
			$query = $db->query("
				SELECT u.*, f.*
				FROM ".TABLE_PREFIX."users u
				LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
				WHERE u.uid='".$mybb->user['uid']."'
			");
			$post = $db->fetch_array($query);
			$post['username'] = $username;
			if($mybb->user['uid'])
			{
				$post['userusername'] = $mybb->user['username'];
			}
			$previewmessage = $mybb->get_input('message');
			$post['message'] = $previewmessage;
			$post['subject'] = $mybb->get_input('subject');
			$post['icon'] = $mybb->get_input('icon', MyBB::INPUT_INT);
			$mybb->input['postoptions'] = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
			if(isset($mybb->input['postoptions']['disablesmilies']))
			{
				$post['smilieoff'] = $mybb->input['postoptions']['disablesmilies'];
			}
			$post['dateline'] = TIME_NOW;
			if(isset($mybb->input['postoptions']['signature']))
			{
				$post['includesig'] = $mybb->input['postoptions']['signature'];
			}
			if(!isset($post['includesig']) || $post['includesig'] != 1)
			{
				$post['includesig'] = 0;
			}

			// Fetch attachments assigned to this post
			if($mybb->get_input('pid', MyBB::INPUT_INT))
			{
				$attachwhere = "pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'";
			}
			else
			{
				$attachwhere = "posthash='".$db->escape_string($mybb->get_input('posthash'))."'";
			}

			$query = $db->simple_select("attachments", "*", $attachwhere);
			while($attachment = $db->fetch_array($query))
			{
				$attachcache[0][$attachment['aid']] = $attachment;
			}

			$postbit = build_postbit($post, 1);
			eval("\$preview = \"".$templates->get("previewpost")."\";");
		}
		$message = htmlspecialchars_uni($mybb->get_input('message'));
		$subject = htmlspecialchars_uni($mybb->get_input('subject'));
	}

	// Removing an attachment or adding a new one, or showing thread errors.
	else if($mybb->get_input('attachmentaid', MyBB::INPUT_INT) || $mybb->get_input('newattachment') || $mybb->get_input('updateattachment') || $thread_errors)
	{
		$message = htmlspecialchars_uni($mybb->get_input('message'));
		$subject = htmlspecialchars_uni($mybb->get_input('subject'));
	}

	// Generate thread prefix selector
	if(!$mybb->get_input('threadprefix', MyBB::INPUT_INT))
	{
		$mybb->input['threadprefix'] = 0;
	}

	$prefixselect = build_prefix_select($forum['fid'], $mybb->get_input('threadprefix', MyBB::INPUT_INT));

	$posthash = htmlspecialchars_uni($mybb->get_input('posthash'));

	// Hide signature option if no permission
	$signature = '';
	if($mybb->usergroup['canusesig'] == 1 && !$mybb->user['suspendsignature'])
	{
		eval("\$signature = \"".$templates->get('newthread_signature')."\";");
	}

	// Can we disable smilies or are they disabled already?
	$disablesmilies = '';
	if($forum['allowsmilies'] != 0)
	{
		eval("\$disablesmilies = \"".$templates->get("newthread_disablesmilies")."\";");
	}

	$postoptions = '';
	if(!empty($signature) || !empty($disablesmilies))
	{
		eval("\$postoptions = \"".$templates->get("newthread_postoptions")."\";");
		$bgcolor = "trow2";
		$bgcolor2 = "trow1";
	}
	else
	{
		$bgcolor = "trow1";
		$bgcolor2 = "trow2";
	}

	$modoptions = '';
	// Show the moderator options
	if(is_moderator($fid))
	{
		$modoptions = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);
		if(isset($modoptions['closethread']) && $modoptions['closethread'] == 1)
		{
			$closecheck = "checked=\"checked\"";
		}
		else
		{
			$closecheck = '';
		}
		if(isset($modoptions['stickthread']) && $modoptions['stickthread'] == 1)
		{
			$stickycheck = "checked=\"checked\"";
		}
		else
		{
			$stickycheck = '';
		}

		$closeoption = '';
		if(is_moderator($fid, "canopenclosethreads"))
		{
			eval("\$closeoption = \"".$templates->get("newreply_modoptions_close")."\";");
		}

		$stickoption = '';
		if(is_moderator($fid, "canstickunstickthreads"))
		{
			eval("\$stickoption = \"".$templates->get("newreply_modoptions_stick")."\";");
		}

		if(!empty($closeoption) || !empty($stickoption))
		{
			eval("\$modoptions = \"".$templates->get("newreply_modoptions")."\";");
			$bgcolor = "trow1";
			$bgcolor2 = "trow2";
		}
		else
		{
			$bgcolor = "trow2";
			$bgcolor2 = "trow1";
		}
	}
	else
	{
		$bgcolor = "trow2";
		$bgcolor2 = "trow1";
	}

	// Fetch subscription select box
	eval("\$subscriptionmethod = \"".$templates->get("post_subscription_method")."\";");

	if($mybb->settings['enableattachments'] != 0 && $forumpermissions['canpostattachments'] != 0)
	{ // Get a listing of the current attachments, if there are any
		$attachcount = 0;
		if($mybb->input['action'] == "editdraft" || ($mybb->input['tid'] && $mybb->input['pid']))
		{
			$attachwhere = "pid='$pid'";
		}
		else
		{
			$attachwhere = "posthash='".$db->escape_string($posthash)."'";
		}
		$query = $db->simple_select("attachments", "*", $attachwhere);
		$attachments = '';
		while($attachment = $db->fetch_array($query))
		{
			$attachment['size'] = get_friendly_size($attachment['filesize']);
			$attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
			$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);

			if($mybb->settings['bbcodeinserter'] != 0 && $forum['allowmycode'] != 0 && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
			{
				eval("\$postinsert = \"".$templates->get("post_attachments_attachment_postinsert")."\";");
			}

			eval("\$attach_rem_options = \"".$templates->get("post_attachments_attachment_remove")."\";");

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
		$query = $db->simple_select("attachments", "SUM(filesize) AS ausage", "uid='".$mybb->user['uid']."'");
		$usage = $db->fetch_array($query);
		if($usage['ausage'] > ($mybb->usergroup['attachquota']*1024) && $mybb->usergroup['attachquota'] != 0)
		{
			$noshowattach = 1;
		}
		if($mybb->usergroup['attachquota'] == 0)
		{
			$friendlyquota = $lang->unlimited;
		}
		else
		{
			$friendlyquota = get_friendly_size($mybb->usergroup['attachquota']*1024);
		}
		$lang->attach_quota = $lang->sprintf($lang->attach_quota, $friendlyquota);

		$link_viewattachments = '';
		if($usage['ausage'] !== NULL)
		{
			$friendlyusage = get_friendly_size($usage['ausage']);
			$lang->attach_usage = $lang->sprintf($lang->attach_usage, $friendlyusage);
			eval("\$link_viewattachments = \"".$templates->get("post_attachments_viewlink")."\";");
		}
		else
		{
			$lang->attach_usage = "";
		}

		$attach_add_options = '';
		if($mybb->settings['maxattachments'] == 0 || ($mybb->settings['maxattachments'] != 0 && $attachcount < $mybb->settings['maxattachments']) && !isset($noshowattach))
		{
			eval("\$attach_add_options = \"".$templates->get("post_attachments_add")."\";");
		}

		$attach_update_options = '';
		if(($mybb->usergroup['caneditattachments'] || $forumpermissions['caneditattachments']) && $attachcount > 0)
		{
			eval("\$attach_update_options = \"".$templates->get("post_attachments_update")."\";");
		}

		if($attach_add_options || $attach_update_options)
		{
			eval("\$newattach = \"".$templates->get("post_attachments_new")."\";");
		}
		eval("\$attachbox = \"".$templates->get("post_attachments")."\";");

		$bgcolor = alt_trow();
	}
	else
	{
		$attachbox = '';
	}

	if($mybb->user['uid'])
	{
		eval("\$savedraftbutton = \"".$templates->get("post_savedraftbutton", 1, 0)."\";");
	}

	$captcha = '';

	// Show captcha image for guests if enabled
	if($mybb->settings['captchaimage'] && !$mybb->user['uid'])
	{
		$correct = false;
		require_once MYBB_ROOT.'inc/class_captcha.php';
		$post_captcha = new captcha(false, "post_captcha");

		if((!empty($mybb->input['previewpost']) || $hide_captcha == true) && $post_captcha->type == 1)
		{
			// If previewing a post - check their current captcha input - if correct, hide the captcha input area
			// ... but only if it's a default one, reCAPTCHA and Are You a Human must be filled in every time due to draconian limits
			if($post_captcha->validate_captcha() == true)
			{
				$correct = true;

				// Generate a hidden list of items for our captcha
				$captcha = $post_captcha->build_hidden_captcha();
			}
		}

		if(!$correct)
		{
 			if($post_captcha->type == captcha::DEFAULT_CAPTCHA)
			{
				$post_captcha->build_captcha();
			}
			elseif(in_array($post_captcha->type, array(captcha::NOCAPTCHA_RECAPTCHA, captcha::RECAPTCHA_INVISIBLE, captcha::RECAPTCHA_V3)))
			{
				$post_captcha->build_recaptcha();
			}
			elseif(in_array($post_captcha->type, array(captcha::HCAPTCHA, captcha::HCAPTCHA_INVISIBLE)))
			{
				$post_captcha->build_hcaptcha();
			}
		}
		else if($correct && (in_array($post_captcha->type, array(captcha::NOCAPTCHA_RECAPTCHA, captcha::RECAPTCHA_INVISIBLE, captcha::RECAPTCHA_V3))))
		{
			$post_captcha->build_recaptcha();
		}
		else if($correct && (in_array($post_captcha->type, array(captcha::HCAPTCHA, captcha::HCAPTCHA_INVISIBLE))))
		{
			$post_captcha->build_hcaptcha();
		}

		if($post_captcha->html)
		{
			$captcha = $post_captcha->html;
		}
	}

	if($forumpermissions['canpostpolls'] != 0)
	{
		$lang->max_options = $lang->sprintf($lang->max_options, $mybb->settings['maxpolloptions']);
		eval("\$pollbox = \"".$templates->get("newthread_postpoll")."\";");
	}

	// Do we have any forum rules to show for this forum?
	$forumrules = '';
	if($forum['rulestype'] >= 2 && $forum['rules'])
	{
		if(!$forum['rulestitle'])
		{
			$forum['rulestitle'] = $lang->sprintf($lang->forum_rules, $forum['name']);
		}

		if(empty($parser))
		{
			require_once MYBB_ROOT.'inc/class_parser.php';
			$parser = new postParser;
		}

		$rules_parser = array(
			"allow_html" => 1,
			"allow_mycode" => 1,
			"allow_smilies" => 1,
			"allow_imgcode" => 1
		);

		$forum['rules'] = $parser->parse_message($forum['rules'], $rules_parser);
		$foruminfo = $forum;

		if($forum['rulestype'] == 3)
		{
			eval("\$forumrules = \"".$templates->get("forumdisplay_rules")."\";");
		}
		else if($forum['rulestype'] == 2)
		{
			eval("\$forumrules = \"".$templates->get("forumdisplay_rules_link")."\";");
		}
	}

	$moderation_notice = '';
	if(!is_moderator($forum['fid'], "canapproveunapproveattachs"))
	{
		if($forumpermissions['modattachments'] == 1  && $forumpermissions['canpostattachments'] != 0)
		{
			$moderation_text = $lang->moderation_forum_attachments;
			eval('$moderation_notice = "'.$templates->get('global_moderation_notice').'";');
		}
	}

	if(!is_moderator($forum['fid'], "canapproveunapprovethreads"))
	{
		if($forumpermissions['modthreads'] == 1)
		{
			$moderation_text = $lang->moderation_forum_thread;
			eval('$moderation_notice = "'.$templates->get('global_moderation_notice').'";');
		}
	}

	if(!is_moderator($forum['fid'], "canapproveunapproveposts"))
	{
		if($mybb->user['moderateposts'] == 1)
		{
			$moderation_text = $lang->moderation_user_posts;
			eval('$moderation_notice = "'.$templates->get('global_moderation_notice').'";');
		}
	}

	$php_max_upload_size = get_php_upload_limit();
	$php_max_file_uploads = (int)ini_get('max_file_uploads');
	eval("\$post_javascript = \"".$templates->get("post_javascript")."\";");

	$plugins->run_hooks("newthread_end");

	$forum['name'] = strip_tags($forum['name']);
	$lang->newthread_in = $lang->sprintf($lang->newthread_in, $forum['name']);

	eval("\$newthread = \"".$templates->get("newthread")."\";");
	output_page($newthread);
}
