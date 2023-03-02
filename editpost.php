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
define('THIS_SCRIPT', 'editpost.php');

$templatelist = "editpost,previewpost,changeuserbox,codebuttons,post_attachments_attachment_postinsert,post_attachments_attachment_mod_unapprove,postbit_attachments_thumbnails,postbit_profilefield_multiselect_value";
$templatelist .= ",editpost_delete,forumdisplay_password_wrongpass,forumdisplay_password,editpost_reason,post_attachments_attachment_remove,post_attachments_update,post_subscription_method,postbit_profilefield_multiselect";
$templatelist .= ",postbit_avatar,postbit_find,postbit_pm,postbit_rep_button,postbit_www,postbit_email,postbit_reputation,postbit_warn,postbit_warninglevel,postbit_author_user,posticons";
$templatelist .= ",postbit_signature,postbit_classic,postbit,postbit_attachments_thumbnails_thumbnail,postbit_attachments_images_image,postbit_attachments_attachment,postbit_attachments_attachment_unapproved";
$templatelist .= ",posticons_icon,post_prefixselect_prefix,post_prefixselect_single,newthread_postpoll,editpost_disablesmilies,post_attachments_attachment_mod_approve,post_attachments_attachment_unapproved";
$templatelist .= ",postbit_warninglevel_formatted,postbit_reputation_formatted_link,editpost_signature,attachment_icon,post_attachments_attachment,post_attachments_add,post_attachments,editpost_postoptions,post_attachments_viewlink";
$templatelist .= ",postbit_attachments_images,global_moderation_notice,post_attachments_new,postbit_attachments,postbit_online,postbit_away,postbit_offline,postbit_gotopost,postbit_userstar,postbit_icon";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_upload.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("editpost");

$plugins->run_hooks("editpost_start");

// No permission for guests
if(!$mybb->user['uid'])
{
	error_no_permission();
}

// Get post info
$pid = $mybb->get_input('pid', MyBB::INPUT_INT);

// if we already have the post information...
if(isset($style) && $style['pid'] == $pid && $style['type'] != 'f')
{
	$post = &$style;
}
else
{
	$post = get_post($pid);
}

if(!$post || ($post['visible'] == -1 && $mybb->input['action'] != "restorepost"))
{
	error($lang->error_invalidpost);
}

// Get thread info
$tid = $post['tid'];
$thread = get_thread($tid);

if(!$thread)
{
	error($lang->error_invalidthread);
}

$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

// Get forum info
$fid = $post['fid'];
$forum = get_forum($fid);

if($thread['visible'] == 0 && !is_moderator($fid, "canviewunapprove") || $thread['visible'] == -1 && !is_moderator($fid, "canviewdeleted") || ($thread['visible'] < -1 && $thread['uid'] != $mybb->user['uid']))
{
	if($thread['visible'] == 0 && !($mybb->settings['showownunapproved'] && $thread['uid'] == $mybb->user['uid']))
	{
		error($lang->error_invalidthread);
	}
}
if(!$forum || $forum['type'] != "f")
{
	error($lang->error_closedinvalidforum);
}
if(($forum['open'] == 0 && !is_moderator($fid, "caneditposts")) || $mybb->user['suspendposting'] == 1)
{
	error_no_permission();
}

// Add prefix to breadcrumb
$breadcrumbprefix = '';
if($thread['prefix'])
{
	$threadprefixes = build_prefixes();
	if(!empty($threadprefixes[$thread['prefix']]))
	{
		$breadcrumbprefix = $threadprefixes[$thread['prefix']]['displaystyle'].'&nbsp;';
	}
}

// Make navigation
build_forum_breadcrumb($fid);
add_breadcrumb($breadcrumbprefix.$thread['subject'], get_thread_link($thread['tid']));
add_breadcrumb($lang->nav_editpost);

$forumpermissions = forum_permissions($fid);

if($mybb->settings['bbcodeinserter'] != 0 && $forum['allowmycode'] != 0 && $mybb->user['showcodebuttons'] != 0)
{
	$codebuttons = build_mycode_inserter("message", $mybb->settings['smilieinserter']);
}
if($mybb->settings['smilieinserter'] != 0)
{
	$smilieinserter = build_clickable_smilies();
}

$mybb->input['action'] = $mybb->get_input('action');
if(!$mybb->input['action'] || isset($mybb->input['previewpost']))
{
	$mybb->input['action'] = "editpost";
}

if($mybb->input['action'] == "deletepost" && $mybb->request_method == "post")
{
	if(!is_moderator($fid, "candeleteposts") && !is_moderator($fid, "cansoftdeleteposts") && $pid != $thread['firstpost'] || !is_moderator($fid, "candeletethreads") && !is_moderator($fid, "cansoftdeletethreads") && $pid == $thread['firstpost'])
	{
		if($thread['closed'] == 1)
		{
			error($lang->redirect_threadclosed);
		}
		if($forumpermissions['candeleteposts'] == 0 && $pid != $thread['firstpost'] || $forumpermissions['candeletethreads'] == 0 && $pid == $thread['firstpost'])
		{
			error_no_permission();
		}
		if($mybb->user['uid'] != $post['uid'])
		{
			error_no_permission();
		}
		// User can't delete unapproved post unless allowed for own
		if($post['visible'] == 0 && !($mybb->settings['showownunapproved'] && $post['uid'] == $mybb->user['uid']))
		{
			error_no_permission();
		}
	}
	if($post['visible'] == -1 && $mybb->settings['soft_delete'] == 1)
	{
		error($lang->error_already_deleted);
	}
}
elseif($mybb->input['action'] == "restorepost" && $mybb->request_method == "post")
{
	if(!is_moderator($fid, "canrestoreposts") && $pid != $thread['firstpost'] || !is_moderator($fid, "canrestorethreads") && $pid == $thread['firstpost'] || $post['visible'] != -1)
	{
		error_no_permission();
	}
}
else
{
	if(!is_moderator($fid, "caneditposts"))
	{
		if($thread['closed'] == 1)
		{
			error($lang->redirect_threadclosed);
		}
		if($forumpermissions['caneditposts'] == 0)
		{
			error_no_permission();
		}
		if($mybb->user['uid'] != $post['uid'])
		{
			error_no_permission();
		}
		// Edit time limit
		$time = TIME_NOW;
		if($mybb->usergroup['edittimelimit'] != 0 && $post['dateline'] < ($time-($mybb->usergroup['edittimelimit']*60)))
		{
			$lang->edit_time_limit = $lang->sprintf($lang->edit_time_limit, $mybb->usergroup['edittimelimit']);
			error($lang->edit_time_limit);
		}
		// User can't edit unapproved post
		if(($post['visible'] == 0 && !($mybb->settings['showownunapproved'] && $post['uid'] == $mybb->user['uid'])) || $post['visible'] == -1)
		{
			error_no_permission();
		}
	}
}

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);

if((empty($_POST) && empty($_FILES)) && $mybb->get_input('processed', MyBB::INPUT_INT) == '1')
{
	error($lang->error_empty_post_input);
}

$attacherror = '';
if($mybb->settings['enableattachments'] == 1 && ($mybb->get_input('newattachment') || $mybb->get_input('updateattachment') || ((($mybb->input['action'] == "do_editpost" && isset($mybb->input['submitbutton'])) || ($mybb->input['action'] == "editpost" && isset($mybb->input['previewpost']))) && $_FILES['attachments'])))
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

	$ret = add_attachments($pid, $forumpermissions, $attachwhere, "editpost");

	if($mybb->get_input('ajax', MyBB::INPUT_INT) == 1)
	{
		if(isset($ret['success']))
		{
			$attachment = array('aid'=>'{1}', 'icon'=>'{2}', 'filename'=>'{3}', 'size'=>'{4}');
			if($mybb->settings['bbcodeinserter'] != 0 && $forum['allowmycode'] != 0 && $mybb->user['showcodebuttons'] != 0)
			{
				eval("\$postinsert = \"".$templates->get("post_attachments_attachment_postinsert")."\";");
			}
			// Moderating options
			$attach_mod_options = '';
			if(is_moderator($fid))
			{
				eval("\$attach_mod_options = \"".$templates->get("post_attachments_attachment_mod_unapprove")."\";");
			}
			eval("\$attach_rem_options = \"".$templates->get("post_attachments_attachment_remove")."\";");
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

	// Do we have attachment errors?
	if(!empty($errors))
	{
		$attacherror = inline_error($errors);
	}

	// If we were dealing with an attachment but didn't click 'Update Post', force the post edit page again.
	if(!isset($mybb->input['submitbutton']))
	{
		$mybb->input['action'] = "editpost";
	}
}

detect_attachmentact();

if($mybb->settings['enableattachments'] == 1 && $mybb->get_input('attachmentaid', MyBB::INPUT_INT) && isset($mybb->input['attachmentact']) && $mybb->input['action'] == "do_editpost" && $mybb->request_method == "post") // Lets remove/approve/unapprove the attachment
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$mybb->input['attachmentaid'] = $mybb->get_input('attachmentaid', MyBB::INPUT_INT);
	if($mybb->input['attachmentact'] == "remove")
	{
		remove_attachment($pid, "", $mybb->input['attachmentaid']);
	}
	elseif($mybb->get_input('attachmentact') == "approve" && is_moderator($fid, 'canapproveunapproveattachs'))
	{
		$update_sql = array("visible" => 1);
		$db->update_query("attachments", $update_sql, "aid='{$mybb->input['attachmentaid']}'");
		update_thread_counters($post['tid'], array('attachmentcount' => "+1"));
	}
	elseif($mybb->get_input('attachmentact') == "unapprove" && is_moderator($fid, 'canapproveunapproveattachs'))
	{
		$update_sql = array("visible" => 0);
		$db->update_query("attachments", $update_sql, "aid='{$mybb->input['attachmentaid']}'");
		update_thread_counters($post['tid'], array('attachmentcount' => "-1"));
	}

	if($mybb->get_input('ajax', MyBB::INPUT_INT) == 1)
	{
		$query = $db->simple_select("attachments", "SUM(filesize) AS ausage", "uid='".$mybb->user['uid']."'");
		$usage = $db->fetch_array($query);

		header("Content-type: application/json; charset={$lang->settings['charset']}");
		echo json_encode(array("success" => true, "usage" => get_friendly_size($usage['ausage'])));
		exit();
	}

	if(!isset($mybb->input['submitbutton']))
	{
		$mybb->input['action'] = "editpost";
	}
}

if($mybb->input['action'] == "deletepost" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("editpost_deletepost");

	if($mybb->get_input('delete', MyBB::INPUT_INT) == 1)
	{
		$query = $db->simple_select("posts", "pid", "tid='{$tid}'", array("limit" => 1, "order_by" => "dateline, pid"));
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
			if($forumpermissions['candeletethreads'] == 1 || is_moderator($fid, "candeletethreads") || is_moderator($fid, "cansoftdeletethreads"))
			{
				require_once MYBB_ROOT."inc/class_moderation.php";
				$moderation = new Moderation;

				if($mybb->settings['soft_delete'] == 1 || is_moderator($fid, "cansoftdeletethreads"))
				{
					$modlogdata['pid'] = $pid;

					$moderation->soft_delete_threads(array($tid));
					log_moderator_action($modlogdata, $lang->thread_soft_deleted);
				}
				else
				{
					$moderation->delete_thread($tid);
					mark_reports($tid, "thread");
					log_moderator_action($modlogdata, $lang->thread_deleted);
				}

				if($mybb->input['ajax'] == 1)
				{
					header("Content-type: application/json; charset={$lang->settings['charset']}");
					if(is_moderator($fid, "canviewdeleted"))
					{
						echo json_encode(array("data" => '1', "first" => '1'));
					}
					else
					{
						echo json_encode(array("data" => '3', "url" => get_forum_link($fid)));
					}
				}
				else
				{
					redirect(get_forum_link($fid), $lang->redirect_threaddeleted);
				}
			}
			else
			{
				error_no_permission();
			}
		}
		else
		{
			if($forumpermissions['candeleteposts'] == 1 || is_moderator($fid, "candeleteposts") || is_moderator($fid, "cansoftdeleteposts"))
			{
				// Select the first post before this
				require_once MYBB_ROOT."inc/class_moderation.php";
				$moderation = new Moderation;

				if($mybb->settings['soft_delete'] == 1 || is_moderator($fid, "cansoftdeleteposts"))
				{
					$modlogdata['pid'] = $pid;

					$moderation->soft_delete_posts(array($pid));
					log_moderator_action($modlogdata, $lang->post_soft_deleted);
				}
				else
				{
					$moderation->delete_post($pid);
					mark_reports($pid, "post");
					log_moderator_action($modlogdata, $lang->post_deleted);
				}

				$query = $db->simple_select("posts", "pid", "tid='{$tid}' AND dateline <= '{$post['dateline']}'", array("limit" => 1, "order_by" => "dateline DESC, pid DESC"));
				$next_post = $db->fetch_array($query);
				if($next_post['pid'])
				{
					$redirect = get_post_link($next_post['pid'], $tid)."#pid{$next_post['pid']}";
				}
				else
				{
					$redirect = get_thread_link($tid);
				}

				if($mybb->input['ajax'] == 1)
				{
					header("Content-type: application/json; charset={$lang->settings['charset']}");
					if(is_moderator($fid, "canviewdeleted"))
					{
						echo json_encode(array("data" => '1', "first" => '0'));
					}
					else
					{
						echo json_encode(array("data" => '2'));
					}
				}
				else
				{
					redirect($redirect, $lang->redirect_postdeleted);
				}
			}
			else
			{
				error_no_permission();
			}
		}
	}
	else
	{
		error($lang->redirect_nodelete);
	}
}

if($mybb->input['action'] == "restorepost" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("editpost_restorepost");

	if($mybb->get_input('restore', MyBB::INPUT_INT) == 1)
	{
		$query = $db->simple_select("posts", "pid", "tid='{$tid}'", array("limit" => 1, "order_by" => "dateline, pid"));
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
		$modlogdata['pid'] = $pid;
		if($firstpost)
		{
			if(is_moderator($fid, "canrestorethreads"))
			{
				require_once MYBB_ROOT."inc/class_moderation.php";
				$moderation = new Moderation;
				$moderation->restore_threads(array($tid));
				log_moderator_action($modlogdata, $lang->thread_restored);
				if($mybb->input['ajax'] == 1)
				{
					header("Content-type: application/json; charset={$lang->settings['charset']}");
					echo json_encode(array("data" => '1', "first" => '1'));
				}
				else
				{
					redirect(get_forum_link($fid), $lang->redirect_threadrestored);
				}
			}
			else
			{
				error_no_permission();
			}
		}
		else
		{
			if(is_moderator($fid, "canrestoreposts"))
			{
				// Select the first post before this
				require_once MYBB_ROOT."inc/class_moderation.php";
				$moderation = new Moderation;
				$moderation->restore_posts(array($pid));
				log_moderator_action($modlogdata, $lang->post_restored);
				$redirect = get_post_link($pid, $tid)."#pid{$pid}";

				if($mybb->input['ajax'] == 1)
				{
					header("Content-type: application/json; charset={$lang->settings['charset']}");
					echo json_encode(array("data" => '1', "first" => '0'));
				}
				else
				{
					redirect($redirect, $lang->redirect_postrestored);
				}
			}
			else
			{
				error_no_permission();
			}
		}
	}
	else
	{
		error($lang->redirect_norestore);
	}
}

$postoptions = array();

if($mybb->input['action'] == "do_editpost" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("editpost_do_editpost_start");

	// Set up posthandler.
	require_once MYBB_ROOT."inc/datahandlers/post.php";
	$posthandler = new PostDataHandler("update");
	$posthandler->action = "post";

	// Set the post data that came from the input to the $post array.
	$post = array(
		"pid" => $mybb->input['pid'],
		"prefix" => $mybb->get_input('threadprefix', MyBB::INPUT_INT),
		"subject" => $mybb->get_input('subject'),
		"icon" => $mybb->get_input('icon', MyBB::INPUT_INT),
		"uid" => $post['uid'],
		"username" => $post['username'],
		"edit_uid" => $mybb->user['uid'],
		"message" => $mybb->get_input('message'),
		"editreason" => $mybb->get_input('editreason'),
	);

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
		$postinfo = $posthandler->update_post();
		$visible = $postinfo['visible'];
		$first_post = $postinfo['first_post'];

		// Help keep our attachments table clean.
		$db->delete_query("attachments", "filename='' OR filesize<1");

		// Did the user choose to post a poll? Redirect them to the poll posting page.
		if($mybb->get_input('postpoll', MyBB::INPUT_INT) && $forumpermissions['canpostpolls'])
		{
			$url = "polls.php?action=newpoll&tid=$tid&polloptions=".$mybb->get_input('numpolloptions', MyBB::INPUT_INT);
			$lang->redirect_postedited = $lang->redirect_postedited_poll;
		}
		else if($visible == 0 && $first_post && !is_moderator($fid, "canviewunapprove", $mybb->user['uid']))
		{
			// Moderated post
			$lang->redirect_postedited .= $lang->redirect_thread_moderation;
			$url = get_forum_link($fid);
		}
		else if($visible == 0 && !is_moderator($fid, "canviewunapprove", $mybb->user['uid']))
		{
			$lang->redirect_postedited .= $lang->redirect_post_moderation;
			$url = get_thread_link($tid);
		}
		// Otherwise, send them back to their post
		else
		{
			$lang->redirect_postedited .= $lang->redirect_postedited_redirect;
			$url = get_post_link($pid, $tid)."#pid{$pid}";
		}
		$plugins->run_hooks("editpost_do_editpost_end");

		redirect($url, $lang->redirect_postedited);
	}
}

if(!$mybb->input['action'] || $mybb->input['action'] == "editpost")
{
	$plugins->run_hooks("editpost_action_start");

	$preview = '';
	if(!isset($mybb->input['previewpost']))
	{
		$icon = $post['icon'];
	}

	if($forum['allowpicons'] != 0)
	{
		$posticons = get_post_icons();
	}

	$mybb->user['username'] = htmlspecialchars_uni($mybb->user['username']);
	eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");

	$deletebox = '';
	
	if(isset($post['visible']) && $post['visible'] != -1 && (($thread['firstpost'] == $pid && (is_moderator($fid, "candeletethreads") || $forumpermissions['candeletethreads'] == 1 && $mybb->user['uid'] == $post['uid'])) || ($thread['firstpost'] != $pid && (is_moderator($fid, "candeleteposts") || $forumpermissions['candeleteposts'] == 1 && $mybb->user['uid'] == $post['uid']))))
	{
		eval("\$deletebox = \"".$templates->get("editpost_delete")."\";");
	}

	$bgcolor = "trow1";
	if($mybb->settings['enableattachments'] != 0 && $forumpermissions['canpostattachments'] != 0)
	{ // Get a listing of the current attachments, if there are any
		$attachcount = 0;
		$query = $db->simple_select("attachments", "*", "pid='{$pid}'");
		$attachments = '';
		while($attachment = $db->fetch_array($query))
		{
			$attachment['size'] = get_friendly_size($attachment['filesize']);
			$attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
			$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);

			if($mybb->settings['bbcodeinserter'] != 0 && $forum['allowmycode'] != 0 && $mybb->user['showcodebuttons'] != 0)
			{
				eval("\$postinsert = \"".$templates->get("post_attachments_attachment_postinsert")."\";");
			}
			// Moderating options
			$attach_mod_options = '';
			if(is_moderator($fid))
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

			// Remove Attachment
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
		$query = $db->simple_select("attachments", "SUM(filesize) AS ausage", "uid='".$mybb->user['uid']."'");
		$usage = $db->fetch_array($query);
		if($usage['ausage'] > ($mybb->usergroup['attachquota']*1024) && $mybb->usergroup['attachquota'] != 0)
		{
			$noshowattach = 1;
		}
		else
		{
			$noshowattach = 0;
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

		$attach_update_options = '';

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
	else
	{
		$attachbox = '';
	}
	if(!$mybb->get_input('attachmentaid', MyBB::INPUT_INT) && !$mybb->get_input('newattachment') && !$mybb->get_input('updateattachment') && !isset($mybb->input['previewpost']))
	{
		$message = $post['message'];
		$subject = $post['subject'];
		$reason = htmlspecialchars_uni($post['editreason']);
	}
	else
	{
		$message = $mybb->get_input('message');
		$subject = $mybb->get_input('subject');
		$reason = htmlspecialchars_uni($mybb->get_input('editreason'));
	}

	$previewmessage = $message;
	$previewsubject = $subject;
	$message = htmlspecialchars_uni($message);
	$subject = htmlspecialchars_uni($subject);

	if(!isset($post_errors))
	{
		$post_errors = '';
	}

	$subscribe = $nonesubscribe = $emailsubscribe = $pmsubscribe = '';
	$postoptionschecked = array('signature' => '', 'disablesmilies' => '');

	if(!empty($mybb->input['previewpost']) || $post_errors)
	{
		// Set up posthandler.
		require_once MYBB_ROOT."inc/datahandlers/post.php";
		$posthandler = new PostDataHandler("update");
		$posthandler->action = "post";

		// Set the post data that came from the input to the $post array.
		$post = array(
			"pid" => $mybb->input['pid'],
			"prefix" => $mybb->get_input('threadprefix', MyBB::INPUT_INT),
			"subject" => $mybb->get_input('subject'),
			"icon" => $mybb->get_input('icon', MyBB::INPUT_INT),
			"uid" => $post['uid'],
			"username" => $post['username'],
			"edit_uid" => $mybb->user['uid'],
			"message" => $mybb->get_input('message'),
		);

		$postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
		if(!isset($postoptions['signature']))
		{
			$postoptions['signature'] = 0;
		}
		if(!isset($postoptions['emailnotify']))
		{
			$postoptions['emailnotify'] = 0;
		}
		if(!isset($postoptions['disablesmilies']))
		{
			$postoptions['disablesmilies'] = 0;
		}

		// Set up the post options from the input.
		$post['options'] = array(
			"signature" => $postoptions['signature'],
			"emailnotify" => $postoptions['emailnotify'],
			"disablesmilies" => $postoptions['disablesmilies']
		);

		$posthandler->set_data($post);

		// Now let the post handler do all the hard work.
		if(!$posthandler->validate_post())
		{
			$post_errors = $posthandler->get_friendly_errors();
			$post_errors = inline_error($post_errors);
			$mybb->input['action'] = "editpost";
			$mybb->input['previewpost'] = 0;
		}
		else
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
			
			$subscription_method = get_subscription_method($tid, $postoptions);
			${$subscription_method.'subscribe'} = "checked=\"checked\" ";
		}
	}

	if(!empty($mybb->input['previewpost']))
	{
		if(!$post['uid'])
		{
			$query = $db->simple_select('posts', 'username, dateline', "pid='{$pid}'");
			$postinfo = $db->fetch_array($query);
		}
		else
		{
			// Figure out the poster's other information.
			$query = $db->query("
				SELECT u.*, f.*, p.dateline
				FROM ".TABLE_PREFIX."users u
				LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
				LEFT JOIN ".TABLE_PREFIX."posts p ON (p.uid=u.uid)
				WHERE u.uid='{$post['uid']}' AND p.pid='{$pid}'
				LIMIT 1
			");
			$postinfo = $db->fetch_array($query);
			$postinfo['userusername'] = $postinfo['username'];
		}

		$query = $db->simple_select("attachments", "*", "pid='{$pid}'");
		while($attachment = $db->fetch_array($query))
		{
			$attachcache[0][$attachment['aid']] = $attachment;
		}

		if(!isset($postoptions['disablesmilies']))
		{
			$postoptions['disablesmilies'] = 0;
		}

		// Set the values of the post info array.
		$postinfo['message'] = $previewmessage;
		$postinfo['subject'] = $previewsubject;
		$postinfo['icon'] = $icon;
		$postinfo['smilieoff'] = $postoptions['disablesmilies'];

		$postbit = build_postbit($postinfo, 1);
		eval("\$preview = \"".$templates->get("previewpost")."\";");
	}
	else if(!$post_errors)
	{
		$preview = '';

		if($post['includesig'] != 0)
		{
			$postoptionschecked['signature'] = " checked=\"checked\"";
		}

		if($post['smilieoff'] == 1)
		{
			$postoptionschecked['disablesmilies'] = " checked=\"checked\"";
		}

		$subscription_method = get_subscription_method($tid, $postoptions);
		${$subscription_method.'subscribe'} = "checked=\"checked\" ";
	}

	// Generate thread prefix selector if this is the first post of the thread
	if($thread['firstpost'] == $pid)
	{
		if(!$mybb->get_input('threadprefix', MyBB::INPUT_INT))
		{
			$mybb->input['threadprefix'] = $thread['prefix'];
		}

		$prefixselect = build_prefix_select($forum['fid'], $mybb->get_input('threadprefix', MyBB::INPUT_INT), 0, $thread['prefix']);
	}
	else
	{
		$prefixselect = "";
	}

	$editreason = '';
	if($mybb->settings['alloweditreason'] == 1)
	{
		eval("\$editreason = \"".$templates->get("editpost_reason")."\";");
		$bgcolor = "trow2";
		$bgcolor2 = "trow1";
	}
	else
	{
		$bgcolor = "trow1";
		$bgcolor2 = "trow2";
	}

	// Fetch subscription select box
	eval("\$subscriptionmethod = \"".$templates->get("post_subscription_method")."\";");

	$query = $db->simple_select("posts", "*", "tid='{$tid}'", array("limit" => 1, "order_by" => "dateline, pid"));
	$firstcheck = $db->fetch_array($query);

	$time = TIME_NOW;
	if($firstcheck['pid'] == $pid && $forumpermissions['canpostpolls'] != 0 && $thread['poll'] < 1 && (is_moderator($fid, "canmanagepolls") || $thread['dateline'] > ($time-($mybb->settings['polltimelimit']*60*60)) || $mybb->settings['polltimelimit'] == 0))
	{
		$lang->max_options = $lang->sprintf($lang->max_options, $mybb->settings['maxpolloptions']);
		$numpolloptions = $mybb->get_input('numpolloptions', MyBB::INPUT_INT);
		$postpollchecked = '';
		
		if($numpolloptions < 1)
		{
			$numpolloptions = 2;
		}
		
		if($mybb->get_input('postpoll', MyBB::INPUT_INT) == 1)
		{
			$postpollchecked = 'checked="checked"';
		}
		
		eval("\$pollbox = \"".$templates->get("newthread_postpoll")."\";");
	}
	else
	{
		$pollbox = '';
	}

	// Hide signature option if no permission
	$signature = '';
	if($mybb->usergroup['canusesig'] == 1 && !$mybb->user['suspendsignature'])
	{
		eval("\$signature = \"".$templates->get('editpost_signature')."\";");
	}

	// Can we disable smilies or are they disabled already?
	$disablesmilies = '';
	if($forum['allowsmilies'] != 0)
	{
		eval("\$disablesmilies = \"".$templates->get("editpost_disablesmilies")."\";");
	}

	$postoptions = '';
	if(!empty($signature) || !empty($disablesmilies))
	{
		eval("\$postoptions = \"".$templates->get("editpost_postoptions")."\";");
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
		if($forumpermissions['mod_edit_posts'] == 1)
		{
			$moderation_text = $lang->moderation_forum_edits;
			eval('$moderation_notice = "'.$templates->get('global_moderation_notice').'";');
		}
	}

	$php_max_upload_size = get_php_upload_limit();
	$php_max_file_uploads = (int)ini_get('max_file_uploads');
	eval("\$post_javascript = \"".$templates->get("post_javascript")."\";");

	$plugins->run_hooks("editpost_end");

	$forum['name'] = strip_tags($forum['name']);

	eval("\$editpost = \"".$templates->get("editpost")."\";");
	output_page($editpost);
}
