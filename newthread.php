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

$newthread['isdraft'] = false;
if($mybb->input['action'] == "editdraft" || ($mybb->get_input('savedraft') && $mybb->input['tid']) || ($mybb->input['tid'] && $mybb->input['pid']))
{
	$newthread['isdraft'] = true;
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
}
else
{
	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
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
	$loginbox = \MyBB\template('misc/changeuserbox.twig');
}

// Otherwise we have a guest, determine the "username" and get the login box.
else
{
	$loginbox = \MyBB\template('misc/loginbox.twig');
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
			$attachment = [
				'aid'=>'{1}',
				'icon'=>'{2}',
				'filename'=>'{3}',
				'size'=>'{4}',
				'visible' => true,
				'showmodapproval' => false,
				'showinsert' => ($mybb->settings['bbcodeinserter'] != 0 && $forum['allowmycode'] != 0 && $mybb->user['showcodebuttons'] != 0)
			];
			$ret['template'] = \MyBB\template('misc/attachments_attachment.twig', [
				'attachment' => $attachment,
			]);

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
			require_once MYBB_ROOT.'/inc/class_stopforumspamchecker.php';

			$stop_forum_spam_checker = new StopForumSpamChecker(
				$plugins,
				$mybb->settings['stopforumspam_min_weighting_before_spam'],
				$mybb->settings['stopforumspam_check_usernames'],
				$mybb->settings['stopforumspam_check_emails'],
				$mybb->settings['stopforumspam_check_ips'],
				$mybb->settings['stopforumspam_log_blocks']
			);

			try
			{
				if($stop_forum_spam_checker->is_user_a_spammer($mybb->get_input('username'), '', get_ip()))
				{
					$errors[] = $lang->sprintf($lang->error_stop_forum_spam_spammer,
						$stop_forum_spam_checker->getErrorText(array(
							'stopforumspam_check_usernames',
							'stopforumspam_check_ips'
						)));
				}
			}
			catch(Exception $e)
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
		$query = $db->simple_select("posts p", "p.pid", "$user_check AND p.fid='{$forum['fid']}' AND p.subject='".$db->escape_string($mybb->get_input('subject'))."' AND p.message='".$db->escape_string($mybb->get_input('message'))."' AND p.dateline>".(TIME_NOW - 600));
		$duplicate_check = $db->fetch_field($query, "pid");
		if($duplicate_check)
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

	$newthread['subject'] = $newthread['message'] = '';

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
					$newthread['message'] .= "[quote='{$quoted_post['username']}' pid='{$quoted_post['pid']}' dateline='{$quoted_post['dateline']}']\n{$quoted_post['message']}\n[/quote]\n\n";
				}

				$newthread['quoted_ids'] = "all";
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

				$newthread['multiquote'] = false;
				if($external_quotes > 0)
				{
					$newthread['multiquote'] = true;
					if($external_quotes == 1)
					{
						$newthread['multiquote_text'] = $lang->multiquote_external_one;
						$newthread['multiquote_deselect'] = $lang->multiquote_external_one_deselect;
						$newthread['multiquote_quote'] = $lang->multiquote_external_one_quote;
					}
					else
					{
						$newthread['multiquote_text'] = $lang->sprintf($lang->multiquote_external, $external_quotes);
						$newthread['multiquote_deselect'] = $lang->multiquote_external_deselect;
						$newthread['multiquote_quote'] = $lang->multiquote_external_quote;
					}
				}
			}
		}
	}

	if(isset($mybb->input['quoted_ids']))
	{
		$newthread['quoted_ids'] = $mybb->get_input('quoted_ids');
	}

	$newthread['postoptions'] = array('signature' => false, 'disablesmilies' => false);
	$newthread['subscriptionmethod'] = array('dont' => false, 'none' => false, 'email' => false, 'pm' => false);
	$newthread['postpollchecked'] = false;

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
			$newthread['postoptions']['signature'] = true;
		}

		if(isset($postoptions['disablesmilies']) && $postoptions['disablesmilies'] == 1)
		{
			$newthread['postoptions']['disablesmilies'] = true;
		}

		$subscription_method = get_subscription_method($tid, $postoptions);
		$newthread['subscriptionmethod'][$subscription_method] = true;

		if($mybb->get_input('postpoll', MyBB::INPUT_INT) == 1)
		{
			$newthread['postpollchecked'] = true;
		}

		$newthread['numpolloptions'] = $mybb->get_input('numpolloptions', MyBB::INPUT_INT);
	}
	else if($mybb->input['action'] == "editdraft" && $mybb->user['uid'])
	{
		// Editing a draft thread
		$mybb->input['threadprefix'] = $thread['prefix'];
		$newthread['message'] = $post['message'];
		$newthread['subject'] = $post['subject'];

		if($post['includesig'] != 0)
		{
			$newthread['postoptions']['signature'] = true;
		}

		if($post['smilieoff'] == 1)
		{
			$newthread['postoptions']['disablesmilies'] = true;
		}

		$icon = $post['icon'];
		if($forum['allowpicons'] != 0)
		{
			$posticons = get_post_icons();
		}

		$subscription_method = get_subscription_method($tid); // Subscription method doesn't get saved in drafts
		$newthread['subscriptionmethod'][$subscription_method] = true;
	}
	else
	{
		// Otherwise, this is our initial visit to this page.
		if($mybb->user['signature'] != '')
		{
			$newthread['postoptions']['signature'] = true;
		}

		$subscription_method = get_subscription_method($tid); // Fresh thread, let the function set the appropriate method
		$newthread['subscriptionmethod'][$subscription_method] = true;

		$newthread['numpolloptions'] = 2;
	}

	// If we're previewing a post then generate the preview.
	$newthread['preview'] = false;
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
			else
			{
				// Otherwise use the name they specified.
				$username = $mybb->get_input('username');
			}

			$uid = 0;
		}
		else
		{
			// This user is logged in.
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

			$newthread['preview'] = true;
			$postbit = build_postbit($post, 1);
		}

		$newthread['message'] = $mybb->get_input('message');
		$newthread['subject'] = $mybb->get_input('subject');
	}

	// Removing an attachment or adding a new one, or showing thread errors.
	else if($mybb->get_input('attachmentaid', MyBB::INPUT_INT) || $mybb->get_input('newattachment') || $mybb->get_input('updateattachment') || $thread_errors)
	{
		$newthread['message'] = $mybb->get_input('message');
		$newthread['subject'] = $mybb->get_input('subject');
	}

	// Generate thread prefix selector
	if(!$mybb->get_input('threadprefix', MyBB::INPUT_INT))
	{
		$mybb->input['threadprefix'] = 0;
	}

	$prefixes = build_prefix_select($forum['fid'], $mybb->get_input('threadprefix', MyBB::INPUT_INT));

	$newthread['posthash'] = $mybb->get_input('posthash');

	$newthread['showpostoptions'] = false;

	// Hide signature option if no permission
	$newthread['showsignature'] = false;
	if($mybb->usergroup['canusesig'] == 1 && !$mybb->user['suspendsignature'])
	{
		$newthread['showpostoptions'] = true;
		$newthread['showsignature'] = true;
	}

	// Can we disable smilies or are they disabled already?
	$newthread['showdisablesmilies'] = false;
	if($forum['allowsmilies'] != 0)
	{
		$newthread['showpostoptions'] = true;
		$newthread['showdisablesmilies'] = true;
	}

	$newthread['showmodoptions'] = false;
	$newthread['modoptions'] = array('closethread' => false, 'stickthread' => false);
	// Show the moderator options
	if(is_moderator($fid))
	{
		$modoptions = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);
		if(isset($modoptions['closethread']) && $modoptions['closethread'] == 1)
		{
			$newthread['modoptions']['closethread'] = true;
		}

		if(isset($modoptions['stickthread']) && $modoptions['stickthread'] == 1)
		{
			$newthread['modoptions']['stickthread'] = true;
		}

		$newthread['showcloseoption'] = false;
		if(is_moderator($thread['fid'], "canopenclosethreads"))
		{
			$newthread['showmodoptions'] = true;
			$newthread['showcloseoption'] = true;
		}

		$newthread['showstickoption'] = false;
		if(is_moderator($thread['fid'], "canstickunstickthreads"))
		{
			$newthread['showmodoptions'] = true;
			$newthread['showstickoption'] = true;
		}
	}

	$newthread['showattachments'] = false;
	if($mybb->settings['enableattachments'] != 0 && $forumpermissions['canpostattachments'] != 0)
	{
		// Get a listing of the current attachments, if there are any
		$newthread['showattachments'] = true;
		$attachcount = 0;

		if($mybb->input['action'] == "editdraft" || ($mybb->input['tid'] && $mybb->input['pid']))
		{
			$attachwhere = "pid='$pid'";
		}
		else
		{
			$attachwhere = "posthash='".$db->escape_string($newthread['posthash'])."'";
		}

		$attachments = [];
		$query = $db->simple_select("attachments", "*", $attachwhere);
		while($attachment = $db->fetch_array($query))
		{
			$attachment['size'] = get_friendly_size($attachment['filesize']);
			$attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
			$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);

			$attachment['showinsert'] = false;
			if($mybb->settings['bbcodeinserter'] != 0 && $forum['allowmycode'] != 0 && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
			{
				$attachment['showinsert'] = true;
			}

			$attachcount++;
			$attachments[] = $attachment;
		}

		$query = $db->simple_select("attachments", "SUM(filesize) AS ausage", "uid='".$mybb->user['uid']."'");
		$usage = $db->fetch_array($query);
		if($usage['ausage'] > ($mybb->usergroup['attachquota'] * 1024) && $mybb->usergroup['attachquota'] != 0)
		{
			$noshowattach = 1;
		}

		if($mybb->usergroup['attachquota'] == 0)
		{
			$newthread['friendlyquota'] = $lang->unlimited;
		}
		else
		{
			$newthread['friendlyquota'] = get_friendly_size($mybb->usergroup['attachquota'] * 1024);
		}

		$newthread['friendlyusage'] = get_friendly_size($usage['ausage']);

		$lang->attach_quota = $lang->sprintf($lang->attach_quota, $newthread['friendlyquota']);

		$newthread['showattachoptions'] = false;
		$newthread['showattachadd'] = false;

		if($usage['ausage'] !== NULL)
		{
			$lang->attach_usage = $lang->sprintf($lang->attach_usage, $newthread['friendlyusage']);
		}
		else
		{
			$lang->attach_usage = "";
		}

		if($mybb->settings['maxattachments'] == 0 || ($mybb->settings['maxattachments'] != 0 && $attachcount < $mybb->settings['maxattachments']) && !isset($noshowattach))
		{
			$newthread['showattachoptions'] = true;
			$newthread['showattachadd'] = true;
		}

		$newthread['showattachupdate'] = false;
		if(($mybb->usergroup['caneditattachments'] || $forumpermissions['caneditattachments']) && $attachcount > 0)
		{
			$newthread['showattachoptions'] = true;
			$newthread['showattachupdate'] = true;
		}
	}

	$captcha = '';
	// Show captcha image for guests if enabled
	if($mybb->settings['captchaimage'] && !$mybb->user['uid'])
	{
		$correct = false;
		require_once MYBB_ROOT.'inc/class_captcha.php';
		$post_captcha = new captcha(false, "post");

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

	$newthread['showpollbox'] = false;
	if($forumpermissions['canpostpolls'] != 0)
	{
		$newthread['showpollbox'] = true;
	}

	// Do we have any forum rules to show for this forum?
	$newthread['showforumrules'] = false;
	if($forum['rulestype'] >= 2 && $forum['rules'])
	{
		$newthread['showforumrules'] = true;

		if(!$forum['rulestitle'])
		{
			$forum['rulestitle'] = $lang->sprintf($lang->forum_rules, $forum['name']);
		}

		if(!$parser)
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
	}

	$newthread['showmodnotice'] = false;
	if(!is_moderator($forum['fid'], "canapproveunapproveattachs"))
	{
		if($forumpermissions['modattachments'] == 1 && $forumpermissions['canpostattachments'] != 0)
		{
			$newthread['showmodnotice'] = true;
			$newthread['moderation_text'] = $lang->moderation_forum_attachments;
		}
	}

	if(!is_moderator($forum['fid'], "canapproveunapprovethreads"))
	{
		if($forumpermissions['modthreads'] == 1)
		{
			$newthread['showmodnotice'] = true;
			$newthread['moderation_text'] = $lang->moderation_forum_thread;
		}
	}

	if(!is_moderator($forum['fid'], "canapproveunapproveposts"))
	{
		if($mybb->user['moderateposts'] == 1)
		{
			$newthread['showmodnotice'] = true;
			$newthread['moderation_text'] = $lang->moderation_user_posts;
		}
	}

	$php_max_upload_size = get_php_upload_limit();
	$php_max_file_uploads = (int)ini_get('max_file_uploads');
	$post_javascript = \MyBB\template('misc/post_javascript.twig', [
		'php_max_upload_size' => $php_max_upload_size,
		'php_max_file_uploads' => $php_max_file_uploads,
	]);

	$plugins->run_hooks("newthread_end");

	$forum['name'] = strip_tags($forum['name']);

	$newthread['tid'] = $tid;
	$newthread['pid'] = $pid;

	$newthread['showprefixes'] = false;
	if(is_array($prefixes))
	{
		$newthread['showprefixes'] = true;
	}

	$newthread['showposticons'] = false;
	if(is_array($posticons))
	{
		$newthread['showposticons'] = true;
	}

	$newthread['emptyiconcheck'] = false;
	if(empty($mybb->input['icon']))
	{
		$newthread['emptyiconcheck'] = true;
	}

	output_page(\MyBB\template('newthread/newthread.twig', [
		'newthread' => $newthread,
		'thread_errors' => $thread_errors,
		'loginbox' => $loginbox,
		'forum' => $forum,
		'smilieinserter' => $smilieinserter,
		'codebuttons' => $codebuttons,
		'postbit' => $postbit,
		'attachments' => $attachments,
		'captcha' => $captcha,
		'prefixes' => $prefixes,
		'posticons' => $posticons,
        'post_javascript' => $post_javascript,
	]));
}
