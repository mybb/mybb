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
define('THIS_SCRIPT', 'newreply.php');

$templatelist = "newreply,previewpost,loginbox,changeuserbox,posticons,newreply_threadreview,newreply_threadreview_post,forumdisplay_rules_link,newreply_multiquote_external,post_attachments_add,post_subscription_method,postbit_warninglevel_formatted";
$templatelist .= ",smilieinsert,smilieinsert_getmore,smilieinsert_smilie,smilieinsert_smilie_empty,codebuttons,post_attachments_new,post_attachments,post_savedraftbutton,newreply_modoptions,newreply_threadreview_more,newreply_disablesmilies,postbit_online";
$templatelist .= ",postbit_www,postbit_email,postbit_reputation,postbit_warninglevel,postbit_author_user,postbit_edit,postbit_quickdelete,postbit_inlinecheck,postbit_posturl,postbit_quote,postbit_multiquote,postbit_report,postbit_ignored,postbit,postbit_userstar";
$templatelist .= ",post_attachments_attachment_postinsert,post_attachments_attachment_remove,post_attachments_attachment_unapproved,post_attachments_attachment,postbit_attachments_attachment,postbit_attachments,newreply_options_signature,postbit_find";
$templatelist .= ",member_register_regimage,member_register_regimage_recaptcha,member_register_regimage_ayah,post_captcha_hidden,post_captcha,post_captcha_recaptcha,post_captcha_nocaptcha,post_captcha_ayah,postbit_groupimage,postbit_away,postbit_offline,postbit_avatar,postbit_icon";
$templatelist .= ",postbit_rep_button,postbit_warn,postbit_author_guest,postbit_signature,postbit_classic,postbit_attachments_thumbnails_thumbnailpostbit_attachments_images_image,postbit_attachments_attachment_unapproved,postbit_pm,post_attachments_update";
$templatelist .= ",postbit_attachments_thumbnails,postbit_attachments_images,postbit_gotopost,forumdisplay_password_wrongpass,forumdisplay_password,posticons_icon,attachment_icon,postbit_reputation_formatted_link,newreply_disablesmilies_hidden,forumdisplay_rules,global_moderation_notice";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("newreply");

// Get the pid and tid and replyto from the input.
$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
$replyto = $mybb->get_input('replyto', MyBB::INPUT_INT);

// AJAX quick reply?
if(!empty($mybb->input['ajax']))
{
	unset($mybb->input['previewpost']);
}

// Edit a draft post.
$pid = 0;
$editdraftpid = '';
$mybb->input['action'] = $mybb->get_input('action');
if(($mybb->input['action'] == "editdraft" || $mybb->input['action'] == "do_newreply") && $mybb->get_input('pid', MyBB::INPUT_INT))
{
	$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
	$post = get_post($pid);
	if(!$post)
	{
		error($lang->error_invalidpost);
	}
	else if($mybb->user['uid'] != $post['uid'])
	{
		error($lang->error_post_noperms);
	}
	$pid = $post['pid'];
	$tid = $post['tid'];
	$editdraftpid = "<input type=\"hidden\" name=\"pid\" value=\"$pid\" />";
}

// Set up $thread and $forum for later use.
$thread = get_thread($tid);
if(!$thread)
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

// Make navigation
build_forum_breadcrumb($fid);
$thread['subject'] = htmlspecialchars_uni($thread['subject']);
add_breadcrumb($thread['subject'], get_thread_link($thread['tid']));
add_breadcrumb($lang->nav_newreply);

$forumpermissions = forum_permissions($fid);

// See if everything is valid up to here.
if(isset($post) && (($post['visible'] == 0 && !is_moderator($fid, "canviewunapprove")) || ($post['visible'] < 0 && $post['uid'] != $mybb->user['uid'])))
{
	error($lang->error_invalidpost);
}
if(($thread['visible'] == 0 && !is_moderator($fid, "canviewunapprove")) || $thread['visible'] < 0)
{
	error($lang->error_invalidthread);
}
if($forum['open'] == 0 || $forum['type'] != "f")
{
	error($lang->error_closedinvalidforum);
}
if($forumpermissions['canview'] == 0 || $forumpermissions['canpostreplys'] == 0)
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

if(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
{
	error_no_permission();
}

if(isset($forumpermissions['canonlyreplyownthreads']) && $forumpermissions['canonlyreplyownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
{
	error_no_permission();
}

// Coming from quick reply? Set some defaults
if($mybb->get_input('method') == "quickreply")
{
	if($mybb->user['subscriptionmethod'] == 1)
	{
		$mybb->input['postoptions']['subscriptionmethod'] = "none";
	}
	else if($mybb->user['subscriptionmethod'] == 2)
	{
		$mybb->input['postoptions']['subscriptionmethod'] = "email";
	}
	else if($mybb->user['subscriptionmethod'] == 3)
	{
		$mybb->input['postoptions']['subscriptionmethod'] = "pm";
	}
}

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);

if($mybb->settings['bbcodeinserter'] != 0 && $forum['allowmycode'] != 0 && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
{
	$codebuttons = build_mycode_inserter("message", $forum['allowsmilies']);
	if($forum['allowsmilies'] != 0)
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
	if(empty($mybb->input['previewpost']) && $mybb->input['action'] != "do_newreply")
	{
		$username = '';
	}
	else
	{
		$username = htmlspecialchars_uni($mybb->get_input('username'));
	}
	eval("\$loginbox = \"".$templates->get("loginbox")."\";");
}

// Check to see if the thread is closed, and if the user is a mod.
if(!is_moderator($fid, "canpostclosedthreads"))
{
	if($thread['closed'] == 1)
	{
		error($lang->redirect_threadclosed);
	}
}

// No weird actions allowed, show new reply form if no regular action.
if($mybb->input['action'] != "do_newreply" && $mybb->input['action'] != "editdraft")
{
	$mybb->input['action'] = "newreply";
}

// Even if we are previewing, still show the new reply form.
if(!empty($mybb->input['previewpost']))
{
	$mybb->input['action'] = "newreply";
}

// Setup a unique posthash for attachment management
if(!$mybb->get_input('posthash') && !$pid)
{
	$mybb->input['posthash'] = md5($thread['tid'].$mybb->user['uid'].random_str());
}

if((empty($_POST) && empty($_FILES)) && $mybb->get_input('processed', MyBB::INPUT_INT) == 1)
{
	error($lang->error_cannot_upload_php_post);
}

$errors = array();
$maximageserror = $attacherror = '';
if($mybb->settings['enableattachments'] == 1 && !$mybb->get_input('attachmentaid', MyBB::INPUT_INT) && ($mybb->get_input('newattachment') || $mybb->get_input('updateattachment') || ($mybb->input['action'] == "do_newreply" && $mybb->get_input('submit') && $_FILES['attachment'])))
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($pid)
	{
		$attachwhere = "pid='{$pid}'";
	}
	else
	{
		$attachwhere = "posthash='".$db->escape_string($mybb->get_input('posthash'))."'";
	}

	// If there's an attachment, check it and upload it
	if($forumpermissions['canpostattachments'] != 0)
	{
		// If attachment exists..
		if(!empty($_FILES['attachment']['name']) && !empty($_FILES['attachment']['type']))
		{
			if($_FILES['attachment']['size'] > 0)
			{
				$query = $db->simple_select("attachments", "aid", "filename='".$db->escape_string($_FILES['attachment']['name'])."' AND {$attachwhere}");
				$updateattach = $db->fetch_field($query, "aid");

				require_once MYBB_ROOT."inc/functions_upload.php";

				$update_attachment = false;
				if($updateattach > 0 && $mybb->get_input('updateattachment'))
				{
					$update_attachment = true;
				}
				$attachedfile = upload_attachment($_FILES['attachment'], $update_attachment);
			}
			else
			{
				$errors[] = $lang->error_uploadempty;
				$mybb->input['action'] = "newreply";
			}
		}
	}

	if(!empty($attachedfile['error']))
	{
		$errors[] = $attachedfile['error'];
		$mybb->input['action'] = "newreply";
	}

	if(!$mybb->get_input('submit'))
	{
		$editdraftpid = "<input type=\"hidden\" name=\"pid\" value=\"$pid\" />";
		$mybb->input['action'] = "newreply";
	}
}

// Remove an attachment.
if($mybb->settings['enableattachments'] == 1 && $mybb->get_input('attachmentaid', MyBB::INPUT_INT) && $mybb->get_input('attachmentact') == "remove")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	require_once MYBB_ROOT."inc/functions_upload.php";
	remove_attachment($pid, $mybb->get_input('posthash'), $mybb->get_input('attachmentaid', MyBB::INPUT_INT));
	if(!$mybb->get_input('submit'))
	{
		$editdraftpid = "<input type=\"hidden\" name=\"pid\" value=\"$pid\" />";
		$mybb->input['action'] = "newreply";
	}
}

$reply_errors = $quoted_ids = '';
$hide_captcha = false;

// Check the maximum posts per day for this user
if($mybb->usergroup['maxposts'] > 0 && $mybb->usergroup['cancp'] != 1)
{
	$daycut = TIME_NOW-60*60*24;
	$query = $db->simple_select("posts", "COUNT(*) AS posts_today", "uid='{$mybb->user['uid']}' AND visible='1' AND dateline>{$daycut}");
	$post_count = $db->fetch_field($query, "posts_today");
	if($post_count >= $mybb->usergroup['maxposts'])
	{
		$lang->error_maxposts = $lang->sprintf($lang->error_maxposts, $mybb->usergroup['maxposts']);
		error($lang->error_maxposts);
	}
}

if($mybb->input['action'] == "do_newreply" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("newreply_do_newreply_start");

	// If this isn't a logged in user, then we need to do some special validation.
	if($mybb->user['uid'] == 0)
	{
		// If they didn't specify a username then give them "Guest"
		if(!$mybb->get_input('username'))
		{
			$username = $lang->guest;
		}
		// Otherwise use the name they specified.
		else
		{
			$username = $mybb->get_input('username');
		}
		$uid = 0;
	

		if($mybb->settings['stopforumspam_on_newreply'])
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
					error($lang->sprintf($lang->error_stop_forum_spam_spammer,
						$stop_forum_spam_checker->getErrorText(array(
							'stopforumspam_check_usernames',
							'stopforumspam_check_ips'
							))));
				}
			}
			catch (Exception $e)
			{
				if($mybb->settings['stopforumspam_block_on_error'])
				{
					error($lang->error_stop_forum_spam_fetching);
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
	if(!$mybb->get_input('savedraft'))
	{
		$query = $db->simple_select("posts p", "p.pid, p.visible", "{$user_check} AND p.tid='{$thread['tid']}' AND p.subject='".$db->escape_string($mybb->get_input('subject'))."' AND p.message='".$db->escape_string($mybb->get_input('message'))."' AND p.visible != '-2' AND p.dateline>".(TIME_NOW-600));
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
		"tid" => $mybb->get_input('tid', MyBB::INPUT_INT),
		"replyto" => $mybb->get_input('replyto', MyBB::INPUT_INT),
		"fid" => $thread['fid'],
		"subject" => $mybb->get_input('subject'),
		"icon" => $mybb->get_input('icon', MyBB::INPUT_INT),
		"uid" => $uid,
		"username" => $username,
		"message" => $mybb->get_input('message'),
		"ipaddress" => $session->packedip,
		"posthash" => $mybb->get_input('posthash')
	);

	if(isset($mybb->input['pid']))
	{
		$post['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
	}

	// Are we saving a draft post?
	if($mybb->get_input('savedraft') && $mybb->user['uid'])
	{
		$post['savedraft'] = 1;
	}
	else
	{
		$post['savedraft'] = 0;
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

	// Set up the post options from the input.
	$post['options'] = array(
		"signature" => $postoptions['signature'],
		"subscriptionmethod" => $postoptions['subscriptionmethod'],
		"disablesmilies" => $postoptions['disablesmilies']
	);

	// Apply moderation options if we have them
	$post['modoptions'] = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);

	$posthandler->set_data($post);

	// Now let the post handler do all the hard work.
	$valid_post = $posthandler->validate_post();

	$post_errors = array();
	// Fetch friendly error messages if this is an invalid post
	if(!$valid_post)
	{
		$post_errors = $posthandler->get_friendly_errors();
	}

	// Mark thread as read
	require_once MYBB_ROOT."inc/functions_indicators.php";
	mark_thread_read($tid, $fid);

	// Check captcha image
	if($mybb->settings['captchaimage'] && !$mybb->user['uid'])
	{
		require_once MYBB_ROOT.'inc/class_captcha.php';
		$post_captcha = new captcha(false, "post_captcha");

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

		if($mybb->get_input('ajax', MyBB::INPUT_INT))
		{
			if($post_captcha->type == 1)
			{
				$randomstr = random_str(5);
				$imagehash = md5(random_str(12));

				$imagearray = array(
					"imagehash" => $imagehash,
					"imagestring" => $randomstr,
					"dateline" => TIME_NOW
				);

				$db->insert_query("captcha", $imagearray);

				//header("Content-type: text/html; charset={$lang->settings['charset']}");
				$data = '';
				$data .= "<captcha>$imagehash";

				if($hide_captcha)
				{
					$data .= "|$randomstr";
				}

				$data .= "</captcha>";

				//header("Content-type: application/json; charset={$lang->settings['charset']}");
				$json_data = array("data" => $data);
			}
			else if($post_captcha->type == 2)
			{
				//header("Content-type: text/html; charset={$lang->settings['charset']}");
				$data = "<captcha>reload</captcha>";

				//header("Content-type: application/json; charset={$lang->settings['charset']}");
				$json_data = array("data" => $data);
			}
		}
	}

	// One or more errors returned, fetch error list and throw to newreply page
	if(count($post_errors) > 0)
	{
		$reply_errors = inline_error($post_errors, '', $json_data);
		$mybb->input['action'] = "newreply";
	}
	else
	{
		$postinfo = $posthandler->insert_post();
		$pid = $postinfo['pid'];
		$visible = $postinfo['visible'];
		$closed = $postinfo['closed'];

		// Invalidate solved captcha
		if($mybb->settings['captchaimage'] && !$mybb->user['uid'])
		{
			$post_captcha->invalidate_captcha();
		}

		$force_redirect = false;

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
			$url = get_post_link($pid, $tid)."#pid{$pid}";
		}
		else
		{
			// Moderated post
			$lang->redirect_newreply .= '<br />'.$lang->redirect_newreply_moderation;
			$url = get_thread_link($tid);

			// User must see moderation notice, regardless of redirect settings
			$force_redirect = true;
		}

		// Mark any quoted posts so they're no longer selected - attempts to maintain those which weren't selected
		if(isset($mybb->input['quoted_ids']) && isset($mybb->cookies['multiquote']) && $mybb->settings['multiquote'] != 0)
		{
			// We quoted all posts - remove the entire cookie
			if($mybb->get_input('quoted_ids') == "all")
			{
				my_unsetcookie("multiquote");
			}
			// Only quoted a few - attempt to remove them from the cookie
			else
			{
				$quoted_ids = explode("|", $mybb->get_input('quoted_ids'));
				$multiquote = explode("|", $mybb->cookies['multiquote']);
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
						my_setcookie("multiquote", $new_multiquote);
					}
					// Otherwise, unset it
					else
					{
						my_unsetcookie("multiquote");
					}
				}
			}
		}

		$plugins->run_hooks("newreply_do_newreply_end");

		// This was a post made via the ajax quick reply - we need to do some special things here
		if($mybb->get_input('ajax', MyBB::INPUT_INT))
		{
			// Visible post
			if($visible == 1)
			{
				// Set post counter
				$postcounter = $thread['replies'] + 1;

				if(is_moderator($fid, "canviewunapprove"))
				{
					$postcounter += $thread['unapprovedposts'];
				}
				if(is_moderator($fid, "canviewdeleted"))
				{
					$postcounter += $thread['deletedposts'];
				}

				// Was there a new post since we hit the quick reply button?
				if($mybb->get_input('lastpid', MyBB::INPUT_INT))
				{
					$query = $db->simple_select("posts", "pid", "tid = '{$tid}' AND pid != '{$pid}'", array("order_by" => "pid", "order_dir" => "desc"));
					$new_post = $db->fetch_array($query);
					if($new_post['pid'] != $mybb->get_input('lastpid', MyBB::INPUT_INT))
					{
						redirect(get_thread_link($tid, 0, "lastpost"));
					}
				}
				
				if(!$mybb->settings['postsperpage'] || (int)$mybb->settings['postsperpage'] < 1)
				{
					$mybb->settings['postsperpage'] = 20;
				}

				// Lets see if this post is on the same page as the one we're viewing or not
				// if it isn't, redirect us
				if($perpage > 0 && (($postcounter) % $perpage) == 0)
				{
					$post_page = ($postcounter) / $mybb->settings['postsperpage'];
				}
				else
				{
					$post_page = (int)($postcounter / $mybb->settings['postsperpage']) + 1;
				}

				if($post_page > $mybb->get_input('from_page', MyBB::INPUT_INT))
				{
					redirect(get_thread_link($tid, 0, "lastpost"));
					exit;
				}

				// Return the post HTML and display it inline
				$query = $db->query("
					SELECT u.*, u.username AS userusername, p.*, f.*, eu.username AS editusername
					FROM ".TABLE_PREFIX."posts p
					LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
					LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
					LEFT JOIN ".TABLE_PREFIX."users eu ON (eu.uid=p.edituid)
					WHERE p.pid='{$pid}'
				");
				$post = $db->fetch_array($query);

				// Now lets fetch all of the attachments for this post
				$query = $db->simple_select("attachments", "*", "pid='{$pid}'");
				while($attachment = $db->fetch_array($query))
				{
					$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
				}

				// Establish altbg - may seem like this is backwards, but build_postbit reverses it
				if(($postcounter - $mybb->settings['postsperpage']) % 2 != 0)
				{
					$altbg = "trow1";
				}
				else
				{
					$altbg = "trow2";
				}

				$charset = "UTF-8";
				if($lang->settings['charset'])
				{
					$charset = $lang->settings['charset'];
				}

				require_once MYBB_ROOT."inc/functions_post.php";
				$pid = $post['pid'];
				$post = build_postbit($post);

				$data = '';
				$data .= $post;

				// Build a new posthash incase the user wishes to quick reply again
			    $new_posthash = md5($mybb->user['uid'].random_str());
				$data .= "<script type=\"text/javascript\">\n";
				$data .= "var hash = document.getElementById('posthash'); if(hash) { hash.value = '{$new_posthash}'; }\n";
				$data .= "if(typeof(inlineModeration) != 'undefined') {
					$('#inlinemod_{$pid}').bind(\"click\", function(e) {
						inlineModeration.checkItem();
					});
				}\n";

				if($closed == 1)
				{
					$data .= "$('#quick_reply_form .trow1').removeClass('trow1 trow2').addClass('trow_shaded');\n";
				}
				else
				{
					$data .= "$('#quick_reply_form .trow_shaded').removeClass('trow_shaded').addClass('trow1');\n";
				}

				$data .= "</script>\n";

				header("Content-type: application/json; charset={$lang->settings['charset']}");
				echo json_encode(array("data" => $data));

				exit;
			}
			// Post is in the moderation queue
			else
			{
				redirect(get_thread_link($tid, 0, "lastpost"), $lang->redirect_newreply_moderation, "", true);
				exit;
			}
		}
		else
		{
			$lang->redirect_newreply .= $lang->sprintf($lang->redirect_return_forum, get_forum_link($fid));
			redirect($url, $lang->redirect_newreply, "", $force_redirect);
			exit;
		}
	}
}

// Show the newreply form.
if($mybb->input['action'] == "newreply" || $mybb->input['action'] == "editdraft")
{
	$plugins->run_hooks("newreply_start");

	$quote_ids = $multiquote_external = '';
	// If this isn't a preview and we're not editing a draft, then handle quoted posts
	if(empty($mybb->input['previewpost']) && !$reply_errors && $mybb->input['action'] != "editdraft" && !$mybb->get_input('attachmentaid', MyBB::INPUT_INT) && !$mybb->get_input('newattachment') && !$mybb->get_input('updateattachment') && !$mybb->get_input('rem'))
	{
		$message = '';
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
		// Handle incoming 'quote' button
		if($replyto)
		{
			$quoted_posts[$replyto] = $replyto;
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

			require_once MYBB_ROOT."inc/functions_posting.php";
			$query = $db->query("
				SELECT p.subject, p.message, p.pid, p.tid, p.username, p.dateline, u.username AS userusername
				FROM ".TABLE_PREFIX."posts p
				LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
				WHERE p.pid IN ({$quoted_posts}) {$unviewable_forums} {$inactiveforums} {$visible_where}
			");
			$load_all = $mybb->get_input('load_all_quotes', MyBB::INPUT_INT);
			while($quoted_post = $db->fetch_array($query))
			{
				// Only show messages for the current thread
				if($quoted_post['tid'] == $tid || $load_all == 1)
				{
					// If this post was the post for which a quote button was clicked, set the subject
					if($replyto == $quoted_post['pid'])
					{
						$subject = preg_replace('#^RE:\s?#i', '', $quoted_post['subject']);
						// Subject too long? Shorten it to avoid error message
						if(my_strlen($subject) > 85)
						{
							$subject = my_substr($subject, 0, 82).'...';
						}
						$subject = "RE: ".$subject;
					}
					$message .= parse_quoted_message($quoted_post);
					$quoted_ids[] = $quoted_post['pid'];
				}
				// Count the rest
				else
				{
					++$external_quotes;
				}
			}
			if($mybb->settings['maxquotedepth'] != '0')
			{
				$message = remove_message_quotes($message);
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
					$multiquote_text = $lang->sprintf($lang->multiquote_external, $external_quotes);
					$multiquote_deselect = $lang->multiquote_external_deselect;
					$multiquote_quote = $lang->multiquote_external_quote;
				}
				eval("\$multiquote_external = \"".$templates->get("newreply_multiquote_external")."\";");
			}
			if(is_array($quoted_ids) && count($quoted_ids) > 0)
			{
				$quoted_ids = implode("|", $quoted_ids);
			}
		}
	}

	if(isset($mybb->input['quoted_ids']))
	{
		$quoted_ids = htmlspecialchars_uni($mybb->get_input('quoted_ids', MyBB::INPUT_INT));
	}

	if(isset($mybb->input['previewpost']))
	{
		$previewmessage = $mybb->get_input('message');
	}
	if(empty($message))
	{
		$message = $mybb->get_input('message');
	}
	$message = htmlspecialchars_uni($message);

	$postoptionschecked = array('signature' => '', 'disablesmilies' => '');
	$postoptions_subscriptionmethod_dont = $postoptions_subscriptionmethod_none = $postoptions_subscriptionmethod_email = $postoptions_subscriptionmethod_pm = '';

	// Set up the post options.
	if(!empty($mybb->input['previewpost']) || $reply_errors != '')
	{
		$postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);

		if(isset($postoptions['signature']) && $postoptions['signature'] == 1)
		{
			$postoptionschecked['signature'] = " checked=\"checked\"";
		}
		if(isset($postoptions['subscriptionmethod']) && $postoptions['subscriptionmethod'] == "none")
		{
			$postoptions_subscriptionmethod_none = "checked=\"checked\"";
		}
		else if(isset($postoptions['subscriptionmethod']) && $postoptions['subscriptionmethod'] == "email")
		{
			$postoptions_subscriptionmethod_email = "checked=\"checked\"";
		}
		else if(isset($postoptions['subscriptionmethod']) && $postoptions['subscriptionmethod'] == "pm")
		{
			$postoptions_subscriptionmethod_pm = "checked=\"checked\"";
		}
		else
		{
			$postoptions_subscriptionmethod_dont = "checked=\"checked\"";
		}
		if(isset($postoptions['disablesmilies']) && $postoptions['disablesmilies'] == 1)
		{
			$postoptionschecked['disablesmilies'] = " checked=\"checked\"";
		}
		$subject = $mybb->input['subject'];
	}
	elseif($mybb->input['action'] == "editdraft" && $mybb->user['uid'])
	{
		$message = htmlspecialchars_uni($post['message']);
		$subject = $post['subject'];
		if($post['includesig'] != 0)
		{
			$postoptionschecked['signature'] = " checked=\"checked\"";
		}
		if($post['smilieoff'] == 1)
		{
			$postoptionschecked['disablesmilies'] = " checked=\"checked\"";
		}
		if(isset($postoptions['subscriptionmethod']) && $postoptions['subscriptionmethod'] == "none")
		{
			$postoptions_subscriptionmethod_none = "checked=\"checked\"";
		}
		else if(isset($postoptions['subscriptionmethod']) && $postoptions['subscriptionmethod'] == "email")
		{
			$postoptions_subscriptionmethod_email = "checked=\"checked\"";
		}
		else if(isset($postoptions['subscriptionmethod']) && $postoptions['subscriptionmethod'] == "pm")
		{
			$postoptions_subscriptionmethod_pm = "checked=\"checked\"";
		}
		else
		{
			$postoptions_subscriptionmethod_dont = "checked=\"checked\"";
		}
		$mybb->input['icon'] = $post['icon'];
	}
	else
	{
		if($mybb->user['signature'] != '')
		{
			$postoptionschecked['signature'] = " checked=\"checked\"";
		}
		if($mybb->user['subscriptionmethod'] ==  1)
		{
			$postoptions_subscriptionmethod_none = "checked=\"checked\"";
		}
		else if($mybb->user['subscriptionmethod'] == 2)
		{
			$postoptions_subscriptionmethod_email = "checked=\"checked\"";
		}
		else if($mybb->user['subscriptionmethod'] == 3)
		{
			$postoptions_subscriptionmethod_pm = "checked=\"checked\"";
		}
		else
		{
			$postoptions_subscriptionmethod_dont = "checked=\"checked\"";
		}
	}

	if($forum['allowpicons'] != 0)
	{
		$posticons = get_post_icons();
	}

	// No subject?
	if(!isset($subject))
	{
		if(!empty($mybb->input['subject']))
		{
			$subject = $mybb->get_input('subject');
		}
		else
		{
			$subject = $thread['subject'];
			// Subject too long? Shorten it to avoid error message
			if(my_strlen($subject) > 85)
			{
				$subject = my_substr($subject, 0, 82).'...';
			}
			$subject = "RE: ".$subject;
		}
	}

	// Preview a post that was written.
	$preview = '';
	if(!empty($mybb->input['previewpost']))
	{
		// If this isn't a logged in user, then we need to do some special validation.
		if($mybb->user['uid'] == 0)
		{
			// If they didn't specify a username then give them "Guest"
			if(!$mybb->get_input('username'))
			{
				$username = $lang->guest;
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
		$posthandler->action = "post";

		// Set the post data that came from the input to the $post array.
		$post = array(
			"tid" => $mybb->get_input('tid', MyBB::INPUT_INT),
			"replyto" => $mybb->get_input('replyto', MyBB::INPUT_INT),
			"fid" => $thread['fid'],
			"subject" => $mybb->get_input('subject'),
			"icon" => $mybb->get_input('icon', MyBB::INPUT_INT),
			"uid" => $uid,
			"username" => $username,
			"message" => $mybb->get_input('message'),
			"ipaddress" => $session->packedip,
			"posthash" => $mybb->get_input('posthash')
		);

		if(isset($mybb->input['pid']))
		{
			$post['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
		}

		$posthandler->set_data($post);

		// Now let the post handler do all the hard work.
		$valid_post = $posthandler->verify_message();
		$valid_subject = $posthandler->verify_subject();
		
		// guest post --> verify author
		if($post['uid'] == 0)
		{
			$valid_username = $posthandler->verify_author();
		}
		else
		{
			$valid_username = true;
		}

		$post_errors = array();
		// Fetch friendly error messages if this is an invalid post
		if(!$valid_post || !$valid_subject || !$valid_username)
		{
			$post_errors = $posthandler->get_friendly_errors();
		}

		// One or more errors returned, fetch error list and throw to newreply page
		if(count($post_errors) > 0)
		{
			$reply_errors = inline_error($post_errors);
		}
		else
		{
			$quote_ids = htmlspecialchars_uni($mybb->get_input('quote_ids'));
			if(!isset($mybb->input['username']))
			{
				$mybb->input['username'] = $lang->guest;
			}
			$mybb->input['icon'] = $mybb->get_input('icon', MyBB::INPUT_INT);
			$query = $db->query("
				SELECT u.*, f.*
				FROM ".TABLE_PREFIX."users u
				LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
				WHERE u.uid='".$mybb->user['uid']."'
			");
			$post = $db->fetch_array($query);
			if(!$mybb->user['uid'] || !$post['username'])
			{
				$post['username'] = $mybb->get_input('username');
			}
			else
			{
				$post['userusername'] = $mybb->user['username'];
				$post['username'] = $mybb->user['username'];
			}
			$post['message'] = $previewmessage;
			$post['subject'] = $subject;
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

			// Fetch attachments assigned to this post.
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
	}

	$subject = htmlspecialchars_uni($parser->parse_badwords($subject));

	$posthash = htmlspecialchars_uni($mybb->get_input('posthash'));

	// Do we have attachment errors?
	if(count($errors) > 0)
	{
		$reply_errors = inline_error($errors);
	}

	// Get a listing of the current attachments.
	if($mybb->settings['enableattachments'] != 0 && $forumpermissions['canpostattachments'] != 0)
	{
		$attachcount = 0;
		if($pid)
		{
			$attachwhere = "pid='$pid'";
		}
		else
		{
			$attachwhere = "posthash='".$db->escape_string($posthash)."'";
		}
		$attachments = '';
		$query = $db->simple_select("attachments", "*", $attachwhere);
		while($attachment = $db->fetch_array($query))
		{
			$attachment['size'] = get_friendly_size($attachment['filesize']);
			$attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
			$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);

			if($mybb->settings['bbcodeinserter'] != 0 && $forum['allowmycode'] != 0 && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
			{
				eval("\$postinsert = \"".$templates->get("post_attachments_attachment_postinsert")."\";");
			}

			$attach_mod_options = '';
			eval("\$attach_rem_options = \"".$templates->get("post_attachments_attachment_remove")."\";");

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

		$noshowattach = '';
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

		$friendlyusage = get_friendly_size($usage['ausage']);
		$lang->attach_quota = $lang->sprintf($lang->attach_quota, $friendlyusage, $friendlyquota);

		if($mybb->settings['maxattachments'] == 0 || ($mybb->settings['maxattachments'] != 0 && $attachcount < $mybb->settings['maxattachments']) && !$noshowattach)
		{
			eval("\$attach_add_options = \"".$templates->get("post_attachments_add")."\";");
		}

		if(($mybb->usergroup['caneditattachments'] || $forumpermissions['caneditattachments']) && $attachcount > 0)
		{
			eval("\$attach_update_options = \"".$templates->get("post_attachments_update")."\";");
		}

		if($attach_add_options || $attach_update_options)
		{
			eval("\$newattach = \"".$templates->get("post_attachments_new")."\";");
		}

		eval("\$attachbox = \"".$templates->get("post_attachments")."\";");
	}

	// If the user is logged in, provide a save draft button.
	if($mybb->user['uid'])
	{
		eval("\$savedraftbutton = \"".$templates->get("post_savedraftbutton", 1, 0)."\";");
	}

	// Show captcha image for guests if enabled
	$captcha = '';
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
 			if($post_captcha->type == 1)
			{
				$post_captcha->build_captcha();
			}
			elseif($post_captcha->type == 2 || $post_captcha->type == 4)
			{
				$post_captcha->build_recaptcha();
			}
			elseif($post_captcha->type == 3)
			{
				$post_captcha->build_ayah();
			}

			if($post_captcha->html)
			{
				$captcha = $post_captcha->html;
			}
		}
		else if($correct && ($post_captcha->type == 2 || $post_captcha->type == 4))
		{
			$post_captcha->build_recaptcha();

			if($post_captcha->html)
			{
				$captcha = $post_captcha->html;
			}
		}
		else if($correct && $post_captcha->type == 3)
		{
			$post_captcha->build_ayah();

			if($post_captcha->html)
			{
				$captcha = $post_captcha->html;
			}
		}
	}

	$reviewmore = '';
	if($mybb->settings['threadreview'] != 0)
	{
		if(!$mybb->settings['postsperpage'] || (int)$mybb->settings['postsperpage'] < 1)
		{
			$mybb->settings['postsperpage'] = 20;
		}

		if(is_moderator($fid, "canviewunapprove"))
		{
			$visibility = "(visible='1' OR visible='0')";
		}
		else
		{
			$visibility = "visible='1'";
		}
		$query = $db->simple_select("posts", "COUNT(pid) AS post_count", "tid='{$tid}' AND {$visibility}");
		$numposts = $db->fetch_field($query, "post_count");
		
		if(!$mybb->settings['postsperpage'] || (int)$mybb->settings['postsperpage'] < 1)
		{
			$mybb->settings['postsperpage'] = 20;
		}

		if($numposts > $mybb->settings['postsperpage'])
		{
			$numposts = $mybb->settings['postsperpage'];
			$lang->thread_review_more = $lang->sprintf($lang->thread_review_more, $mybb->settings['postsperpage'], get_thread_link($tid));
			eval("\$reviewmore = \"".$templates->get("newreply_threadreview_more")."\";");
		}

		$query = $db->simple_select("posts", "pid", "tid='{$tid}' AND {$visibility}", array("order_by" => "dateline", "order_dir" => "desc", "limit" => $mybb->settings['postsperpage']));
		while($post = $db->fetch_array($query))
		{
			$pidin[] = $post['pid'];
		}

		$pidin = implode(",", $pidin);

		// Fetch attachments
		$query = $db->simple_select("attachments", "*", "pid IN ($pidin)");
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
			$reviewpostdate = my_date('relative', $post['dateline']);
			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode'],
				"allow_videocode" => $forum['allowvideocode'],
				"me_username" => $post['username'],
				"filter_badwords" => 1
			);
			if($post['smilieoff'] == 1)
			{
				$parser_options['allow_smilies'] = 0;
			}

			if($mybb->user['showimages'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestimages'] != 1 && $mybb->user['uid'] == 0)
			{
				$parser_options['allow_imgcode'] = 0;
			}

			if($mybb->user['showvideos'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestvideos'] != 1 && $mybb->user['uid'] == 0)
			{
				$parser_options['allow_videocode'] = 0;
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
	$disablesmilies = '';
	if($forum['allowsmilies'] != 0)
	{
		eval("\$disablesmilies = \"".$templates->get("newreply_disablesmilies")."\";");
	}
	else
	{
		eval("\$disablesmilies = \"".$templates->get("newreply_disablesmilies_hidden")."\";");
	}

	$modoptions = '';
	// Show the moderator options.
	if(is_moderator($fid))
	{
		if($mybb->get_input('processed', MyBB::INPUT_INT))
		{
			$mybb->input['modoptions'] = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);
			if(!isset($mybb->input['modoptions']['closethread']))
			{
				$mybb->input['modoptions']['closethread'] = 0;
			}
			$closed = (int)$mybb->input['modoptions']['closethread'];
			if(!isset($mybb->input['modoptions']['stickthread']))
			{
				$mybb->input['modoptions']['stickthread'] = 0;
			}
			$stuck = (int)$mybb->input['modoptions']['stickthread'];
		}
		else
		{
			$closed = $thread['closed'];
			$stuck = $thread['sticky'];
		}

		if($closed)
		{
			$closecheck = ' checked="checked"';
		}
		else
		{
			$closecheck = '';
		}

		if($stuck)
		{
			$stickycheck = ' checked="checked"';
		}
		else
		{
			$stickycheck = '';
		}

		eval("\$modoptions = \"".$templates->get("newreply_modoptions")."\";");
		$bgcolor = "trow1";
	}
	else
	{
		$bgcolor = "trow2";
	}

	// Fetch subscription select box
	eval("\$subscriptionmethod = \"".$templates->get("post_subscription_method")."\";");

	$lang->post_reply_to = $lang->sprintf($lang->post_reply_to, $thread['subject']);
	$lang->reply_to = $lang->sprintf($lang->reply_to, $thread['subject']);

	// Do we have any forum rules to show for this forum?
	$forumrules = '';
	if($forum['rulestype'] >= 2 && $forum['rules'])
	{
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
	if(!is_moderator($forum['fid'], "canapproveunapproveposts"))
	{
		if($forumpermissions['modposts'] == 1)
		{
			$moderation_text = $lang->moderation_forum_posts;
			eval('$moderation_notice = "'.$templates->get('global_moderation_notice').'";');
		}

		if($mybb->user['moderateposts'] == 1)
		{
			$moderation_text = $lang->moderation_user_posts;
			eval('$moderation_notice = "'.$templates->get('global_moderation_notice').'";');
		}
	}

	$plugins->run_hooks("newreply_end");

	$forum['name'] = strip_tags($forum['name']);

	eval("\$newreply = \"".$templates->get("newreply")."\";");
	output_page($newreply);
}
