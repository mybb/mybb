<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'moderation.php');

$templatelist = 'changeuserbox,loginbox,moderation_delayedmoderation_custommodtool,moderation_delayedmodaction_notes,moderation_delayedmoderation_merge,moderation_delayedmoderation_move';
$templatelist .= ',moderation_delayedmoderation,moderation_deletethread,moderation_deletepoll,moderation_deleteposts_post,moderation_deleteposts,moderation_mergeposts_post,moderation_mergeposts';
$templatelist .= ',moderation_move,moderation_threadnotes_modaction,moderation_threadnotes_delayedmodaction,moderation_threadnotes,moderation_getip_modoptions,moderation_getip,moderation_merge';
$templatelist .= ',moderation_split_post,moderation_split,moderation_inline_deletethreads,moderation_inline_movethreads,moderation_inline_deleteposts,moderation_inline_mergeposts';
$templatelist .= ',moderation_inline_splitposts,forumjump_bit,forumjump_special,forumjump_advanced,forumdisplay_password_wrongpass,forumdisplay_password';

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_upload.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;
require_once MYBB_ROOT."inc/class_moderation.php";
$moderation = new Moderation;

// Load global language phrases
$lang->load("moderation");

$plugins->run_hooks("moderation_start");

// Get some navigation if we need it
switch($mybb->input['action'])
{
	case "reports":
		add_breadcrumb($lang->reported_posts);
		break;
	case "allreports":
		add_breadcrumb($lang->all_reported_posts);
		break;

}
$tid = intval($mybb->input['tid']);
$pid = intval($mybb->input['pid']);
$fid = intval($mybb->input['fid']);

if($pid)
{
	$modlogdata['pid'] = $pid;
	$post = get_post($pid);
	$tid = $post['tid'];
	if(!$post['pid'])
	{
		error($lang->error_invalidpost);
	}
}

if($tid)
{
	$thread = get_thread($tid);
	$fid = $thread['fid'];
	if(!$thread['tid'])
	{
		error($lang->error_invalidthread);
	}
}

if($fid)
{
	$modlogdata['fid'] = $fid;
	$forum = get_forum($fid);

	// Make navigation
	build_forum_breadcrumb($fid);
}

$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

if($tid)
{
	add_breadcrumb($thread['subject'], get_thread_link($thread['tid']));
	$modlogdata['tid'] = $tid;
}

// Get our permissions all nice and setup
$permissions = forum_permissions($fid);

if($fid)
{
	// Check if this forum is password protected and we have a valid password
	check_forum_password($forum['fid']);
}

if($mybb->user['uid'] != 0)
{
	eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
}
else
{
	eval("\$loginbox = \"".$templates->get("loginbox")."\";");
}

$allowable_moderation_actions = array("getip", "cancel_delayedmoderation", "delayedmoderation");

if($mybb->request_method != "post" && !in_array($mybb->input['action'], $allowable_moderation_actions))
{
	error_no_permission();
}

// Begin!
switch($mybb->input['action'])
{
	// Delayed Moderation
	case "cancel_delayedmoderation":
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		add_breadcrumb($lang->delayed_moderation);
		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}

		$db->delete_query("delayedmoderation", "did='".intval($mybb->input['did'])."'");

		if($tid == 0)
		{
			moderation_redirect(get_forum_link($fid), $lang->redirect_delayed_moderation_cancelled);
		}
		else
		{
			moderation_redirect("moderation.php?action=delayedmoderation&amp;tid={$tid}&amp;my_post_key={$mybb->post_code}", $lang->redirect_delayed_moderation_cancelled);
		}
		break;
	case "do_delayedmoderation":
	case "delayedmoderation":
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		add_breadcrumb($lang->delayed_moderation);

		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}

		$errors = array();
		$customthreadtools = "";

		$allowed_types = array('openclosethread', 'deletethread', 'move', 'stick', 'merge', 'removeredirects', 'removesubscriptions', 'approveunapprovethread');

		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$query = $db->simple_select("modtools", 'tid, name', "(','||forums||',' LIKE '%,$fid,%' OR ','||forums||',' LIKE '%,-1,%' OR forums='') AND type = 't'");
				break;
			default:
				$query = $db->simple_select("modtools", 'tid, name', "(CONCAT(',',forums,',') LIKE '%,$fid,%' OR CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='') AND type = 't'");
		}
		while($tool = $db->fetch_array($query))
		{
			$allowed_types[] = "modtool_".$tool['tid'];

			$tool['name'] = htmlspecialchars_uni($tool['name']);

			$checked = "";
			if($mybb->input['type'] == "modtool_".$tool['tid'])
			{
				$checked = "checked=\"checked\"";
			}

			eval("\$customthreadtools .= \"".$templates->get("moderation_delayedmoderation_custommodtool")."\";");
		}

		if($mybb->input['tid'])
		{
			$mybb->input['tids'] = $mybb->input['tid'];
		}
		else
		{
			if($mybb->input['inlinetype'] == 'search')
			{
				$tids = getids($mybb->input['searchid'], 'search');
			}
			else
			{
				$fid = $mybb->input['fid'];
				$tids = getids($fid, "forum");
			}
			if(count($tids) < 1)
			{
				error($lang->error_inline_nothreadsselected);
			}

			$mybb->input['tids'] = $tids;
		}

		if($mybb->input['action'] == "do_delayedmoderation" && $mybb->request_method == "post")
		{
			if(!in_array($mybb->input['type'], $allowed_types))
			{
				$mybb->input['type'] = '';
				$errors[] = $lang->error_delayedmoderation_unsupported_type;
			}

			if($mybb->input['type'] == 'move' && !in_array($mybb->input['delayedmoderation']['method'], array('move', 'redirect', 'copy')))
			{
				$mybb->input['delayedmoderation']['method'] = '';
				$errors[] = $lang->error_delayedmoderation_unsupported_method;
			}

			if($mybb->input['type'] == 'move')
			{
				$newforum = get_forum($fid);
				if(!$newforum || $newforum['type'] != "f" || $newforum['type'] == "f" && $newforum['linkto'] != '')
				{
					$errors[] = $lang->error_invalidforum;
				}
			}

			if($mybb->input['delay'] < 1)
			{
				$mybb->input['delay'] = 1;
				$errors[] = $lang->error_delayedmoderation_invalid_delay;
			}

			if(!$errors)
			{
				if(is_array($mybb->input['tids']))
				{
					$mybb->input['tids'] = implode(',' , $mybb->input['tids']);
				}
				$db->insert_query("delayedmoderation", array(
					'type' => $db->escape_string($mybb->input['type']),
					'delaydateline' => TIME_NOW+(intval($mybb->input['delay'])*24*60*60),
					'uid' => $mybb->user['uid'],
					'tids' => $db->escape_string($mybb->input['tids']),
					'fid' => $fid,
					'dateline' => TIME_NOW,
					'inputs' => $db->escape_string(serialize($mybb->input['delayedmoderation']))
				));

				$lang->redirect_delayed_moderation_thread = $lang->sprintf($lang->redirect_delayed_moderation_thread, intval($mybb->input['delay']));

				if($mybb->input['tid'])
				{
					moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_delayed_moderation_thread);
				}
				else
				{
					if($mybb->input['inlinetype'] == 'search')
					{
						moderation_redirect(get_forum_link($fid), $lang->sprintf($lang->redirect_delayed_moderation_search, $mybb->input['delay']));
					}
					else
					{
						moderation_redirect(get_forum_link($fid), $lang->sprintf($lang->redirect_delayed_moderation_forum, $mybb->input['delay']));
					}
				}
			}
			else
			{
				$type_selected = array($mybb->input['type'] => "checked=\"checked\"");
				$method_selected = array($mybb->input['delayedmoderation']['method'] => "checked=\"checked\"");

				$mybb->input['delay'] = intval($mybb->input['delay']);
				$mybb->input['delayedmoderation']['redirect_expire'] = intval($mybb->input['delayedmoderation']['redirect_expire']);
				$mybb->input['delayedmoderation']['new_forum'] = intval($mybb->input['delayedmoderation']['new_forum']);
				$mybb->input['delayedmoderation']['subject'] = htmlspecialchars_uni($mybb->input['delayedmoderation']['subject']);
				$mybb->input['delayedmoderation']['threadurl'] = htmlspecialchars_uni($mybb->input['delayedmoderation']['threadurl']);

				$forumselect = build_forum_jump("", $mybb->input['delayedmoderation']['new_forum'], 1, '', 0, true, '', "delayedmoderation[new_forum]");
			}
		}
		else
		{
			$type_selected = array('openclosethread' => "checked=\"checked\"");
			$method_selected = array('move' => "checked=\"checked\"");

			$mybb->input['delay'] = 1;
			$mybb->input['delayedmoderation']['redirect_expire'] = '';
			$mybb->input['delayedmoderation']['subject'] = $thread['subject'];
			$mybb->input['delayedmoderation']['threadurl'] = '';

			$forumselect = build_forum_jump("", $fid, 1, '', 0, true, '', "delayedmoderation[new_forum]");
		}

		if(count($errors) > 0)
		{
			$display_errors = inline_error($errors);
		}

		$forum_cache = $cache->read("forums");

		$actions = array(
			'openclosethread' => $lang->open_close_thread,
			'deletethread' => $lang->delete_thread,
			'move' => $lang->move_copy_thread,
			'stick' => $lang->stick_unstick_thread,
			'merge' => $lang->merge_threads,
			'removeredirects' => $lang->remove_redirects,
			'removesubscriptions' => $lang->remove_subscriptions,
			'approveunapprovethread' => $lang->approve_unapprove_thread
		);

		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$query = $db->simple_select("modtools", 'tid, name', "(','||forums||',' LIKE '%,$fid,%' OR ','||forums||',' LIKE '%,-1,%' OR forums='') AND type = 't'");
				break;
			default:
				$query = $db->simple_select("modtools", 'tid, name', "(CONCAT(',',forums,',') LIKE '%,$fid,%' OR CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='') AND type = 't'");
		}
		while($tool = $db->fetch_array($query))
		{
			$actions['modtool_'.$tool['tid']] = htmlspecialchars_uni($tool['name']);
		}

		$delayedmods = '';
		$trow = alt_trow(1);
		if($tid == 0)
		{
			// Inline thread moderation is used
			if($mybb->input['inlinetype'] == 'search')
			{
				$tids = getids($mybb->input['searchid'], 'search');
			}
			else
			{
				$tids = getids($fid, "forum");
			}
			$where_array = array();
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					foreach($tids as $like)
					{
						$where_array[] = "','||d.tids||',' LIKE '%,".$db->escape_string($like).",%'";
					}
					$where_statement = implode(" OR ", $where_array);
					break;
				default:
					foreach($tids as $like)
					{
						$where_array[] = "CONCAT(',',d.tids,',') LIKE  '%,".$db->escape_string($like).",%'";
					}
					$where_statement = implode(" OR ", $where_array);
			}
			$query = $db->query("
				SELECT d.*, u.username, f.name AS fname
				FROM ".TABLE_PREFIX."delayedmoderation d
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=d.uid)
				LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=d.fid)
				WHERE ".$where_statement."
				ORDER BY d.dateline DESC
				LIMIT  0, 20
			");
		}
		else
		{
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					$query = $db->query("
						SELECT d.*, u.username, f.name AS fname
						FROM ".TABLE_PREFIX."delayedmoderation d
						LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=d.uid)
						LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=d.fid)
						WHERE ','||d.tids||',' LIKE '%,{$tid},%'
						ORDER BY d.dateline DESC
						LIMIT  0, 20
					");
					break;
				default:
					$query = $db->query("
						SELECT d.*, u.username, f.name AS fname
						FROM ".TABLE_PREFIX."delayedmoderation d
						LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=d.uid)
						LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=d.fid)
						WHERE CONCAT(',',d.tids,',') LIKE '%,{$tid},%'
						ORDER BY d.dateline DESC
						LIMIT  0, 20
					");
			}
		}
		while($delayedmod = $db->fetch_array($query))
		{
			$delayedmod['dateline'] = my_date("jS M Y, G:i", $delayedmod['delaydateline']);
			$delayedmod['profilelink'] = build_profile_link($delayedmod['username'], $delayedmod['uid']);
			$delayedmod['action'] = $actions[$delayedmod['type']];
			$info = '';
			if(strpos($delayedmod['tids'], ',') === false)
			{
				$delayed_thread = get_thread($delayedmod['tids']);
				$info .= "<strong>{$lang->thread}</strong> <a href=\"".get_thread_link($delayedmod['tids'])."\">".htmlspecialchars_uni($delayed_thread['subject'])."</a><br />";
			}
			else
			{
				$info .= "<strong>{$lang->thread}</strong> {$lang->multiple_threads}<br />";
			}

			if($delayedmod['fname'])
			{
				$info .= "<strong>{$lang->forum}</strong> <a href=\"".get_forum_link($delayedmod['fid'])."\">".htmlspecialchars_uni($delayedmod['fname'])."</a><br />";
			}
			$delayedmod['inputs'] = unserialize($delayedmod['inputs']);

			if($delayedmod['type'] == 'move')
			{
				$info .= "<strong>{$lang->new_forum}</strong>  <a href=\"".get_forum_link($delayedmod['inputs']['new_forum'])."\">".htmlspecialchars_uni($forum_cache[$delayedmod['inputs']['new_forum']]['name'])."</a><br />";
				if($delayedmod['inputs']['method'] == "redirect")
				{
					if(intval($delayedmod['inputs']['redirect_expire']) == 0)
					{
						$redirect_expire_bit = $lang->redirect_forever;
					}
					else
					{
						$redirect_expire_bit = intval($delayedmod['inputs']['redirect_expire'])." {$lang->days}";
					}
					$info .= "<strong>{$lang->leave_redirect_for}</strong> {$redirect_expire_bit}<br />";
				}
			}
			else if($delayedmod['type'] == 'merge')
			{
				$info .= "<strong>{$lang->new_subject}</strong> ".htmlspecialchars_uni($delayedmod['inputs']['subject'])."<br />";
				$info .= "<strong>{$lang->thread_to_merge_with}</strong> <a href=\"".htmlspecialchars_uni($delayedmod['inputs']['threadurl'])."\">".htmlspecialchars_uni($delayedmod['inputs']['threadurl'])."</a><br />";
			}

			eval("\$delayedmods .= \"".$templates->get("moderation_delayedmodaction_notes")."\";");
			$trow = alt_trow();
		}
		if(!$delayedmods)
		{
			$delayedmods = "<tr><td class=\"trow1\" colspan=\"5\">{$lang->no_delayed_mods}</td></tr>";
		}

		$url = '';
		if($mybb->input['tid'])
		{
			$lang->threads = $lang->thread;
			$threads = "<a href=\"".get_thread_link($tid)."\">{$thread['subject']}</a>";
			eval("\$moderation_delayedmoderation_merge = \"".$templates->get("moderation_delayedmoderation_merge")."\";");
		}
		else
		{
			if($mybb->input['inlinetype'] == 'search')
			{
				$tids = getids($mybb->input['searchid'], 'search');
				$url = htmlspecialchars_uni($mybb->input['url']);
			}
			else
			{
				$tids = getids($fid, "forum");
			}
			if(count($tids) < 1)
			{
				error($lang->error_inline_nothreadsselected);
			}

			$threads = $lang->sprintf($lang->threads_selected, count($tids));
		}
		eval("\$moderation_delayedmoderation_move = \"".$templates->get("moderation_delayedmoderation_move")."\";");

		$plugins->run_hooks("moderation_delayedmoderation");

		eval("\$delayedmoderation = \"".$templates->get("moderation_delayedmoderation")."\";");
		output_page($delayedmoderation);
		break;
	// Open or close a thread
	case "openclosethread":
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!is_moderator($fid, "canopenclosethreads"))
		{
			error_no_permission();
		}

		if($thread['closed'] == 1)
		{
			$openclose = $lang->opened;
			$redirect = $lang->redirect_openthread;
			$moderation->open_threads($tid);
		}
		else
		{
			$openclose = $lang->closed;
			$redirect = $lang->redirect_closethread;
			$moderation->close_threads($tid);
		}

		$lang->mod_process = $lang->sprintf($lang->mod_process, $openclose);

		log_moderator_action($modlogdata, $lang->mod_process);

		moderation_redirect(get_thread_link($thread['tid']), $redirect);
		break;

	// Stick or unstick that post to the top bab!
	case "stick";
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_stick");

		if($thread['sticky'] == 1)
		{
			$stuckunstuck = $lang->unstuck;
			$redirect = $lang->redirect_unstickthread;
			$moderation->unstick_threads($tid);
		}
		else
		{
			$stuckunstuck = $lang->stuck;
			$redirect = $lang->redirect_stickthread;
			$moderation->stick_threads($tid);
		}

		$lang->mod_process = $lang->sprintf($lang->mod_process, $stuckunstuck);

		log_moderator_action($modlogdata, $lang->mod_process);

		moderation_redirect(get_thread_link($thread['tid']), $redirect);
		break;

	// Remove redirects to a specific thread
	case "removeredirects":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_removeredirects");

		$moderation->remove_redirects($tid);

		log_moderator_action($modlogdata, $lang->redirects_removed);
		moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_redirectsremoved);
		break;

	// Delete thread confirmation page
	case "deletethread":

		add_breadcrumb($lang->nav_deletethread);

		if(!is_moderator($fid, "candeleteposts"))
		{
			if($permissions['candeletethreads'] != 1 || $mybb->user['uid'] != $thread['uid'])
			{
				error_no_permission();
			}
		}

		$plugins->run_hooks("moderation_deletethread");

		eval("\$deletethread = \"".$templates->get("moderation_deletethread")."\";");
		output_page($deletethread);
		break;

	// Delete the actual thread here
	case "do_deletethread":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!is_moderator($fid, "candeleteposts"))
		{
			if($permissions['candeletethreads'] != 1 || $mybb->user['uid'] != $thread['uid'])
			{
				error_no_permission();
			}
		}

		$plugins->run_hooks("moderation_do_deletethread");

		// Log the subject of the deleted thread
		$modlogdata['thread_subject'] = $thread['subject'];

		$thread['subject'] = $db->escape_string($thread['subject']);
		$lang->thread_deleted = $lang->sprintf($lang->thread_deleted, $thread['subject']);
		log_moderator_action($modlogdata, $lang->thread_deleted);

		$moderation->delete_thread($tid);

		mark_reports($tid, "thread");
		moderation_redirect(get_forum_link($fid), $lang->redirect_threaddeleted);
		break;

	// Delete the poll from a thread confirmation page
	case "deletepoll":
		add_breadcrumb($lang->nav_deletepoll);

		if(!is_moderator($fid, "candeleteposts"))
		{
			if($permissions['candeletethreads'] != 1 || $mybb->user['uid'] != $thread['uid'])
			{
				error_no_permission();
			}
		}

		$plugins->run_hooks("moderation_deletepoll");

		$query = $db->simple_select("polls", "*", "tid='$tid'");
		$poll = $db->fetch_array($query);
		if(!$poll['pid'])
		{
			error($lang->error_invalidpoll);
		}

		eval("\$deletepoll = \"".$templates->get("moderation_deletepoll")."\";");
		output_page($deletepoll);
		break;

	// Delete the actual poll here!
	case "do_deletepoll":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!$mybb->input['delete'])
		{
			error($lang->redirect_pollnotdeleted);
		}
		if(!is_moderator($fid, "candeleteposts"))
		{
			if($permissions['candeletethreads'] != 1 || $mybb->user['uid'] != $thread['uid'])
			{
				error_no_permission();
			}
		}
		$query = $db->simple_select("polls", "*", "tid='$tid'");
		$poll = $db->fetch_array($query);
		if(!$poll['pid'])
		{
			error($lang->error_invalidpoll);
		}

		$plugins->run_hooks("moderation_do_deletepoll");

		$lang->poll_deleted = $lang->sprintf($lang->poll_deleted, $thread['subject']);
		log_moderator_action($modlogdata, $lang->poll_deleted);

		$moderation->delete_poll($poll['pid']);

		moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_polldeleted);
		break;

	// Approve a thread
	case "approvethread":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!is_moderator($fid, "canopenclosethreads"))
		{
			error_no_permission();
		}
		$query = $db->simple_select("threads", "*", "tid='$tid'");
		$thread = $db->fetch_array($query);

		$plugins->run_hooks("moderation_approvethread");

		$lang->thread_approved = $lang->sprintf($lang->thread_approved, $thread['subject']);
		log_moderator_action($modlogdata, $lang->thread_approved);

		$moderation->approve_threads($tid, $fid);

		moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_threadapproved);
		break;

	// Unapprove a thread
	case "unapprovethread":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!is_moderator($fid, "canopenclosethreads"))
		{
			error_no_permission();
		}
		$query = $db->simple_select("threads", "*", "tid='$tid'");
		$thread = $db->fetch_array($query);

		$plugins->run_hooks("moderation_unapprovethread");

		$lang->thread_unapproved = $lang->sprintf($lang->thread_unapproved, $thread['subject']);
		log_moderator_action($modlogdata, $lang->thread_unapproved);

		$moderation->unapprove_threads($tid, $fid);

		moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_threadunapproved);
		break;

	// Delete selective posts in a thread
	case "deleteposts":
		add_breadcrumb($lang->nav_deleteposts);
		if(!is_moderator($fid, "candeleteposts"))
		{
			error_no_permission();
		}
		$posts = "";
		$query = $db->query("
			SELECT p.*, u.*
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid)
			WHERE tid='$tid'
			ORDER BY dateline ASC
		");
		$altbg = "trow1";
		while($post = $db->fetch_array($query))
		{
			$postdate = my_date($mybb->settings['dateformat'], $post['dateline']);
			$posttime = my_date($mybb->settings['timeformat'], $post['dateline']);

			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode'],
				"allow_videocode" => $forum['allowvideocode'],
				"filter_badwords" => 1
			);
			if($post['smilieoff'] == 1)
			{
				$parser_options['allow_smilies'] = 0;
			}

			$message = $parser->parse_message($post['message'], $parser_options);
			eval("\$posts .= \"".$templates->get("moderation_deleteposts_post")."\";");
			$altbg = alt_trow();
		}

		$plugins->run_hooks("moderation_deleteposts");

		eval("\$deleteposts = \"".$templates->get("moderation_deleteposts")."\";");
		output_page($deleteposts);
		break;

	// Lets delete those selected posts!
	case "do_deleteposts":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!is_moderator($fid, "candeleteposts"))
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_do_deleteposts");

		$deletethread = "1";
		$deletepost = $mybb->input['deletepost'];
		$query = $db->simple_select("posts", "*", "tid='$tid'");
		while($post = $db->fetch_array($query))
		{
			if($deletepost[$post['pid']] == 1)
			{
				$moderation->delete_post($post['pid']);
				$deletecount++;
				$plist[] = $post['pid'];
			}
			else
			{
				$deletethread = "0";
			}
		}
		if($deletethread)
		{
			$moderation->delete_thread($tid);
			$url = get_forum_link($fid);
			mark_reports($plist, "posts");
		}
		else
		{
			$url = get_thread_link($thread['tid']);
			mark_reports($tid, "thread");
		}
		$lang->deleted_selective_posts = $lang->sprintf($lang->deleted_selective_posts, $deletecount);
		log_moderator_action($modlogdata, $lang->deleted_selective_posts);
		moderation_redirect($url, $lang->redirect_postsdeleted);
		break;

	// Merge selected posts selection screen
	case "mergeposts":
		add_breadcrumb($lang->nav_mergeposts);

		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}
		$posts = "";
		$query = $db->query("
			SELECT p.*, u.*
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid)
			WHERE tid='$tid'
			ORDER BY dateline ASC
		");
		$altbg = "trow1";
		while($post = $db->fetch_array($query))
		{
			$postdate = my_date($mybb->settings['dateformat'], $post['dateline']);
			$posttime = my_date($mybb->settings['timeformat'], $post['dateline']);
			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode'],
				"allow_videocode" => $forum['allowvideocode'],
				"filter_badwords" => 1
			);
			if($post['smilieoff'] == 1)
			{
				$parser_options['allow_smilies'] = 0;
			}

			$message = $parser->parse_message($post['message'], $parser_options);
			eval("\$posts .= \"".$templates->get("moderation_mergeposts_post")."\";");
			$altbg = alt_trow();
		}

		$plugins->run_hooks("moderation_mergeposts");

		eval("\$mergeposts = \"".$templates->get("moderation_mergeposts")."\";");
		output_page($mergeposts);
		break;

	// Lets merge those selected posts!
	case "do_mergeposts":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_do_mergeposts");

		$mergepost = $mybb->input['mergepost'];
		if(count($mergepost) <= 1)
		{
			error($lang->error_nomergeposts);
		}

		foreach($mergepost as $pid => $yes)
		{
			$plist[] = intval($pid);
		}
		$masterpid = $moderation->merge_posts($plist, $tid, $mybb->input['sep']);

		mark_reports($plist, "posts");
		log_moderator_action($modlogdata, $lang->merged_selective_posts);
		moderation_redirect(get_post_link($masterpid)."#pid$masterpid", $lang->redirect_mergeposts);
		break;

	// Move a thread
	case "move":
		add_breadcrumb($lang->nav_move);
		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_move");

		$forumselect = build_forum_jump("", '', 1, '', 0, true, '', "moveto");
		eval("\$movethread = \"".$templates->get("moderation_move")."\";");
		output_page($movethread);
		break;

	// Lets get this thing moving!
	case "do_move":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		$moveto = intval($mybb->input['moveto']);
		$method = $mybb->input['method'];

		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}
		// Check if user has moderator permission to move to destination
		if(!is_moderator($moveto, "canmanagethreads") && !is_moderator($fid, "canmovetononmodforum"))
		{
			error_no_permission();
		}
		$newperms = forum_permissions($moveto);
		if($newperms['canview'] == 0 && !is_moderator($fid, "canmovetononmodforum"))
		{
			error_no_permission();
		}

		$newforum = get_forum($moveto);
		if(!$newforum || $newforum['type'] != "f" || $newforum['type'] == "f" && $newforum['linkto'] != '')
		{
			error($lang->error_invalidforum);
		}
		if($method != "copy" && $thread['fid'] == $moveto)
		{
			error($lang->error_movetosameforum);
		}

		$expire = 0;
		if(intval($mybb->input['redirect_expire']) > 0)
		{
			$expire = TIME_NOW + (intval($mybb->input['redirect_expire']) * 86400);
		}

		$the_thread = $tid;

		$newtid = $moderation->move_thread($tid, $moveto, $method, $expire);

		switch($method)
		{
			case "copy":
				log_moderator_action($modlogdata, $lang->thread_copied);
				break;
			default:
			case "move":
			case "redirect":
				log_moderator_action($modlogdata, $lang->thread_moved);
				break;
		}

		moderation_redirect(get_thread_link($newtid), $lang->redirect_threadmoved);
		break;

	// Thread notes editor
	case "threadnotes":
		add_breadcrumb($lang->nav_threadnotes);
		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}
		$thread['notes'] = htmlspecialchars_uni($parser->parse_badwords($thread['notes']));
		$trow = alt_trow(1);
		$query = $db->query("
			SELECT l.*, u.username, t.subject AS tsubject, f.name AS fname, p.subject AS psubject
			FROM ".TABLE_PREFIX."moderatorlog l
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=l.fid)
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=l.pid)
			WHERE t.tid='$tid'
			ORDER BY l.dateline DESC
			LIMIT  0, 20
		");
		while($modaction = $db->fetch_array($query))
		{
			$modaction['dateline'] = my_date("jS M Y, G:i", $modaction['dateline']);
			$modaction['profilelink'] = build_profile_link($modaction['username'], $modaction['uid']);
			$modaction['action'] = htmlspecialchars_uni($modaction['action']);
			$info = '';
			if($modaction['tsubject'])
			{
				$info .= "<strong>$lang->thread</strong> <a href=\"".get_thread_link($modaction['tid'])."\">".htmlspecialchars_uni($modaction['tsubject'])."</a><br />";
			}
			if($modaction['fname'])
			{
				$info .= "<strong>$lang->forum</strong> <a href=\"".get_forum_link($modaction['fid'])."\">".htmlspecialchars_uni($modaction['fname'])."</a><br />";
			}
			if($modaction['psubject'])
			{
				$info .= "<strong>$lang->post</strong> <a href=\"".get_post_link($modaction['pid'])."#pid".$modaction['pid']."\">".htmlspecialchars_uni($modaction['psubject'])."</a>";
			}

			eval("\$modactions .= \"".$templates->get("moderation_threadnotes_modaction")."\";");
			$trow = alt_trow();
		}
		if(!$modactions)
		{
			$modactions = "<tr><td class=\"trow1\" colspan=\"4\">$lang->no_mod_options</td></tr>";
		}

		$actions = array(
			'openclosethread' => $lang->open_close_thread,
			'deletethread' => $lang->delete_thread,
			'move' => $lang->move_copy_thread,
			'stick' => $lang->stick_unstick_thread,
			'merge' => $lang->merge_threads,
			'removeredirects' => $lang->remove_redirects,
			'removesubscriptions' => $lang->remove_subscriptions,
			'approveunapprovethread' => $lang->approve_unapprove_thread
		);

		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$query = $db->simple_select("modtools", 'tid, name', "(','||forums||',' LIKE '%,$fid,%' OR ','||forums||',' LIKE '%,-1,%' OR forums='') AND type = 't'");
				break;
			default:
				$query = $db->simple_select("modtools", 'tid, name', "(CONCAT(',',forums,',') LIKE '%,$fid,%' OR CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='') AND type = 't'");
		}
		while($tool = $db->fetch_array($query))
		{
			$actions['modtool_'.$tool['tid']] = htmlspecialchars_uni($tool['name']);
		}

		$forum_cache = $cache->read("forums");

		$trow = alt_trow(1);
		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$query = $db->query("
					SELECT d.*, u.username, f.name AS fname
					FROM ".TABLE_PREFIX."delayedmoderation d
					LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=d.uid)
					LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=d.fid)
					WHERE ','||d.tids||',' LIKE '%,{$tid},%'
					ORDER BY d.dateline DESC
					LIMIT  0, 20
				");
				break;
			default:
				$query = $db->query("
					SELECT d.*, u.username, f.name AS fname
					FROM ".TABLE_PREFIX."delayedmoderation d
					LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=d.uid)
					LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=d.fid)
					WHERE CONCAT(',',d.tids,',') LIKE '%,{$tid},%'
					ORDER BY d.dateline DESC
					LIMIT  0, 20
				");
		}
		while($delayedmod = $db->fetch_array($query))
		{
			$delayedmod['dateline'] = my_date("jS M Y, G:i", $delayedmod['delaydateline']);
			$delayedmod['profilelink'] = build_profile_link($delayedmod['username'], $delayedmod['uid']);
			$delayedmod['action'] = $actions[$delayedmod['type']];
			$info = '';
			if(strpos($delayedmod['tids'], ',') === false)
			{
				$info .= "<strong>{$lang->thread}</strong> <a href=\"".get_thread_link($delayedmod['tids'])."\">{$thread['subject']}</a><br />";
			}
			else
			{
				$info .= "<strong>{$lang->thread}</strong> {$lang->multiple_threads}<br />";
			}

			if($delayedmod['fname'])
			{
				$info .= "<strong>{$lang->forum}</strong> <a href=\"".get_forum_link($delayedmod['fid'])."\">".htmlspecialchars_uni($delayedmod['fname'])."</a><br />";
			}
			$delayedmod['inputs'] = unserialize($delayedmod['inputs']);

			if($delayedmod['type'] == 'move')
			{
				$info .= "<strong>{$lang->new_forum}</strong>  <a href=\"".get_forum_link($delayedmod['inputs']['new_forum'])."\">".htmlspecialchars_uni($forum_cache[$delayedmod['inputs']['new_forum']]['name'])."</a><br />";
				if($delayedmod['inputs']['method'] == "redirect")
				{
					$info .= "<strong>{$lang->leave_redirect_for}</strong> ".intval($delayedmod['inputs']['redirect_expire'])." {$lang->days}<br />";
				}
			}
			else if($delayedmod['type'] == 'merge')
			{
				$info .= "<strong>{$lang->new_subject}</strong> ".htmlspecialchars_uni($delayedmod['inputs']['subject'])."<br />";
				$info .= "<strong>{$lang->thread_to_merge_with}</strong> <a href=\"".htmlspecialchars_uni($delayedmod['inputs']['threadurl'])."\">".htmlspecialchars_uni($delayedmod['inputs']['threadurl'])."</a><br />";
			}

			eval("\$delayedmods .= \"".$templates->get("moderation_threadnotes_delayedmodaction")."\";");
			$trow = alt_trow();
		}
		if(!$delayedmods)
		{
			$delayedmods = "<tr><td class=\"trow1\" colspan=\"4\">{$lang->no_delayed_mods}</td></tr>";
		}

		$plugins->run_hooks("moderation_threadnotes");

		eval("\$threadnotes = \"".$templates->get("moderation_threadnotes")."\";");
		output_page($threadnotes);
		break;

	// Update the thread notes!
	case "do_threadnotes":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_do_threadnotes");

		log_moderator_action($modlogdata, $lang->thread_notes_edited);
		$sqlarray = array(
			"notes" => $db->escape_string($mybb->input['threadnotes']),
		);
		$db->update_query("threads", $sqlarray, "tid='$tid'");
		moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_threadnotesupdated);
		break;

	// Lets look up the ip address of a post
	case "getip":
		add_breadcrumb($lang->nav_getip);
		if(!is_moderator($fid, "canviewips"))
		{
			error_no_permission();
		}

		$hostname = @gethostbyaddr($post['ipaddress']);
		if(!$hostname || $hostname == $post['ipaddress'])
		{
			$hostname = $lang->resolve_fail;
		}

		$username = build_profile_link($post['username'], $post['uid']);

		// Moderator options
		$modoptions = "";
		if($mybb->usergroup['canmodcp'] == 1)
		{
			eval("\$modoptions = \"".$templates->get("moderation_getip_modoptions")."\";");
		}

		eval("\$getip = \"".$templates->get("moderation_getip")."\";");
		output_page($getip);
		break;

	// Merge threads
	case "merge":
		add_breadcrumb($lang->nav_merge);
		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_merge");

		eval("\$merge = \"".$templates->get("moderation_merge")."\";");
		output_page($merge);
		break;

	// Lets get those threads together baby! (Merge threads)
	case "do_merge":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_do_merge");

		// explode at # sign in a url (indicates a name reference) and reassign to the url
		$realurl = explode("#", $mybb->input['threadurl']);
		$mybb->input['threadurl'] = $realurl[0];

		// Are we using an SEO URL?
		if(substr($mybb->input['threadurl'], -4) == "html")
		{
			// Get thread to merge's tid the SEO way
			preg_match("#thread-([0-9]+)?#i", $mybb->input['threadurl'], $threadmatch);
			preg_match("#post-([0-9]+)?#i", $mybb->input['threadurl'], $postmatch);

			if($threadmatch[1])
			{
				$parameters['tid'] = $threadmatch[1];
			}

			if($postmatch[1])
			{
				$parameters['pid'] = $postmatch[1];
			}
		}
		else
		{
			// Get thread to merge's tid the normal way
			$splitloc = explode(".php", $mybb->input['threadurl']);
			$temp = explode("&", my_substr($splitloc[1], 1));

			if(!empty($temp))
			{
				for($i = 0; $i < count($temp); $i++)
				{
					$temp2 = explode("=", $temp[$i], 2);
					$parameters[$temp2[0]] = $temp2[1];
				}
			}
			else
			{
				$temp2 = explode("=", $splitloc[1], 2);
				$parameters[$temp2[0]] = $temp2[1];
			}
		}

		if($parameters['pid'] && !$parameters['tid'])
		{
			$query = $db->simple_select("posts", "*", "pid='".intval($parameters['pid'])."'");
			$post = $db->fetch_array($query);
			$mergetid = $post['tid'];
		}
		elseif($parameters['tid'])
		{
			$mergetid = $parameters['tid'];
		}
		$mergetid = intval($mergetid);
		$query = $db->simple_select("threads", "*", "tid='".intval($mergetid)."'");
		$mergethread = $db->fetch_array($query);
		if(!$mergethread['tid'])
		{
			error($lang->error_badmergeurl);
		}
		if($mergetid == $tid)
		{ // sanity check
			error($lang->error_mergewithself);
		}
		if(!is_moderator($mergethread['fid'], "canmanagethreads"))
		{
			error_no_permission();
		}
		if($mybb->input['subject'])
		{
			$subject = $mybb->input['subject'];
		}
		else
		{
			$subject = $thread['subject'];
		}

		$moderation->merge_threads($mergetid, $tid, $subject);

		log_moderator_action($modlogdata, $lang->thread_merged);

		moderation_redirect(get_thread_link($tid), $lang->redirect_threadsmerged);
		break;

	// Divorce the posts in this thread (Split!)
	case "split":
		add_breadcrumb($lang->nav_split);
		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}
		$query = $db->query("
			SELECT p.*, u.*
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid)
			WHERE tid='$tid'
			ORDER BY dateline ASC
		");
		$numposts = $db->num_rows($query);
		if($numposts <= "1")
		{
			error($lang->error_cantsplitonepost);
		}

		$altbg = "trow1";
		$posts = '';
		while($post = $db->fetch_array($query))
		{
			$postdate = my_date($mybb->settings['dateformat'], $post['dateline']);
			$posttime = my_date($mybb->settings['timeformat'], $post['dateline']);
			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode'],
				"allow_videocode" => $forum['allowvideocode'],
				"filter_badwords" => 1
			);
			if($post['smilieoff'] == 1)
			{
				$parser_options['allow_smilies'] = 0;
			}

			$message = $parser->parse_message($post['message'], $parser_options);
			eval("\$posts .= \"".$templates->get("moderation_split_post")."\";");
			$altbg = alt_trow();
		}
		$forumselect = build_forum_jump("", $fid, 1, '', 0, true, '', "moveto");

		$plugins->run_hooks("moderation_split");

		eval("\$split = \"".$templates->get("moderation_split")."\";");
		output_page($split);
		break;

	// Lets break them up buddy! (Do the split)
	case "do_split":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_do_split");

		if(!is_array($mybb->input['splitpost']))
		{
			error($lang->error_nosplitposts);
		}
		$query = $db->simple_select("posts", "COUNT(*) AS totalposts", "tid='{$tid}'");
		$count = $db->fetch_array($query);

		if($count['totalposts'] == 1)
		{
			error($lang->error_cantsplitonepost);
		}

		if($count['totalposts'] == count($mybb->input['splitpost']))
		{
			error($lang->error_cantsplitall);
		}

		if($mybb->input['moveto'])
		{
			$moveto = intval($mybb->input['moveto']);
		}
		else
		{
			$moveto = $fid;
		}

		$newforum = get_forum($moveto);
		if(!$newforum || $newforum['type'] != "f" || $newforum['type'] == "f" && $newforum['linkto'] != '')
		{
			error($lang->error_invalidforum);
		}

		// move the selected posts over
		$query = $db->simple_select("posts", "pid", "tid='$tid'");
		while($post = $db->fetch_array($query))
		{
			if($mybb->input['splitpost'][$post['pid']] == 1)
			{
				$pids[] = $post['pid'];
			}
			mark_reports($post['pid'], "post");
		}

		$newtid = $moderation->split_posts($pids, $tid, $moveto, $mybb->input['newsubject']);

		log_moderator_action($modlogdata, $lang->thread_split);

		moderation_redirect(get_thread_link($newtid), $lang->redirect_threadsplit);
		break;

	// Delete Thread Subscriptions
	case "removesubscriptions":
		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}

		$plugins->run_hooks("moderation_removesubscriptions");

		$moderation->remove_thread_subscriptions($tid, true);

		log_moderator_action($modlogdata, $lang->removed_subscriptions);

		moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_removed_subscriptions);
		break;

	// Delete Threads - Inline moderation
	case "multideletethreads":
		add_breadcrumb($lang->nav_multi_deletethreads);

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->input['searchid'], 'search');
			if(!is_moderator_by_tids($threads, 'candeleteposts'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			if(!is_moderator($fid, 'candeleteposts'))
			{
				error_no_permission();
			}
		}
		if(count($threads) < 1)
		{
			error($lang->error_inline_nothreadsselected);
		}

		$inlineids = implode("|", $threads);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		$return_url = htmlspecialchars_uni($mybb->input['url']);
		eval("\$multidelete = \"".$templates->get("moderation_inline_deletethreads")."\";");
		output_page($multidelete);
		break;

	// Actually delete the threads - Inline moderation
	case "do_multideletethreads":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		$threadlist = explode("|", $mybb->input['threads']);
		if(!is_moderator_by_tids($threadlist, "candeleteposts"))
		{
			error_no_permission();
		}
		foreach($threadlist as $tid)
		{
			$tid = intval($tid);
			$moderation->delete_thread($tid);
			$tlist[] = $tid;
		}
		log_moderator_action($modlogdata, $lang->multi_deleted_threads);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		mark_reports($tlist, "threads");
		moderation_redirect(get_forum_link($fid), $lang->redirect_inline_threadsdeleted);
		break;

	// Open threads - Inline moderation
	case "multiopenthreads":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->input['searchid'], 'search');
			if(!is_moderator_by_tids($threads, 'canopenclosethreads'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			if(!is_moderator($fid, 'canopenclosethreads'))
			{
				error_no_permission();
			}
		}

		if(count($threads) < 1)
		{
			error($lang->error_inline_nothreadsselected);
		}

		$moderation->open_threads($threads);

		log_moderator_action($modlogdata, $lang->multi_opened_threads);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		moderation_redirect(get_forum_link($fid), $lang->redirect_inline_threadsopened);
		break;

	// Close threads - Inline moderation
	case "multiclosethreads":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->input['searchid'], 'search');
			if(!is_moderator_by_tids($threads, 'canmanagethreads'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			if(!is_moderator($fid, 'canmanagethreads'))
			{
				error_no_permission();
			}
		}
		if(count($threads) < 1)
		{
			error($lang->error_inline_nothreadsselected);
		}

		$moderation->close_threads($threads);

		log_moderator_action($modlogdata, $lang->multi_closed_threads);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		moderation_redirect(get_forum_link($fid), $lang->redirect_inline_threadsclosed);
		break;

	// Approve threads - Inline moderation
	case "multiapprovethreads":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->input['searchid'], 'search');
			if(!is_moderator_by_tids($threads, 'canmanagethreads'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			if(!is_moderator($fid, 'canmanagethreads'))
			{
				error_no_permission();
			}
		}
		if(count($threads) < 1)
		{
			error($lang->error_inline_nothreadsselected);
		}

		$moderation->approve_threads($threads, $fid);

		log_moderator_action($modlogdata, $lang->multi_approved_threads);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		$cache->update_stats();
		moderation_redirect(get_forum_link($fid), $lang->redirect_inline_threadsapproved);
		break;

	// Unapprove threads - Inline moderation
	case "multiunapprovethreads":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->input['searchid'], 'search');
			if(!is_moderator_by_tids($threads, 'canmanagethreads'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			if(!is_moderator($fid, 'canmanagethreads'))
			{
				error_no_permission();
			}
		}
		if(count($threads) < 1)
		{
			error($lang->error_inline_nothreadsselected);
		}

		$moderation->unapprove_threads($threads, $fid);

		log_moderator_action($modlogdata, $lang->multi_unapproved_threads);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		$cache->update_stats();
		moderation_redirect(get_forum_link($fid), $lang->redirect_inline_threadsunapproved);
		break;

	// Stick threads - Inline moderation
	case "multistickthreads":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->input['searchid'], 'search');
			if(!is_moderator_by_tids($threads, 'canopenclosethreads'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			if(!is_moderator($fid, 'canopenclosethreads'))
			{
				error_no_permission();
			}
		}
		if(count($threads) < 1)
		{
			error($lang->error_inline_nothreadsselected);
		}

		$moderation->stick_threads($threads);

		log_moderator_action($modlogdata, $lang->multi_stuck_threads);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		moderation_redirect(get_forum_link($fid), $lang->redirect_inline_threadsstuck);
		break;

	// Unstick threads - Inline moderaton
	case "multiunstickthreads":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->input['searchid'], 'search');
			if(!is_moderator_by_tids($threads, 'canopenclosethreads'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			if(!is_moderator($fid, 'canopenclosethreads'))
			{
				error_no_permission();
			}
		}
		if(count($threads) < 1)
		{
			error($lang->error_inline_nothreadsselected);
		}

		$moderation->unstick_threads($threads);

		log_moderator_action($modlogdata, $lang->multi_unstuck_threads);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		moderation_redirect(get_forum_link($fid), $lang->redirect_inline_threadsunstuck);
		break;

	// Move threads - Inline moderation
	case "multimovethreads":
		add_breadcrumb($lang->nav_multi_movethreads);

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->input['searchid'], 'search');
			if(!is_moderator_by_tids($threads, 'canmanagethreads'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			if(!is_moderator($fid, 'canmanagethreads'))
			{
				error_no_permission();
			}
		}

		if(count($threads) < 1)
		{
			error($lang->error_inline_nothreadsselected);
		}
		$inlineids = implode("|", $threads);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		$forumselect = build_forum_jump("", '', 1, '', 0, true, '', "moveto");
		$return_url = htmlspecialchars_uni($mybb->input['url']);
		eval("\$movethread = \"".$templates->get("moderation_inline_movethreads")."\";");
		output_page($movethread);
		break;

	// Actually move the threads in Inline moderation
	case "do_multimovethreads":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		$moveto = intval($mybb->input['moveto']);
		$threadlist = explode("|", $mybb->input['threads']);
		if(!is_moderator_by_tids($threadlist, 'canmanagethreads'))
		{
			error_no_permission();
		}
		foreach($threadlist as $tid)
		{
			$tids[] = intval($tid);
		}
		// Make sure moderator has permission to move to the new forum
		$newperms = forum_permissions($moveto);
		if(($newperms['canview'] == 0 || !is_moderator($moveto, 'canmanagethreads')) && !is_moderator_by_tids($tids, 'canmovetononmodforum'))
		{
			error_no_permission();
		}

		$newforum = get_forum($moveto);
		if(!$newforum || $newforum['type'] != "f" || $newforum['type'] == "f" && $newforum['linkto'] != '')
		{
			error($lang->error_invalidforum);
		}

		$moderation->move_threads($tids, $moveto);

		log_moderator_action($modlogdata, $lang->multi_moved_threads);

		moderation_redirect(get_forum_link($moveto), $lang->redirect_inline_threadsmoved);
		break;

	// Delete posts - Inline moderation
	case "multideleteposts":
		add_breadcrumb($lang->nav_multi_deleteposts);

		if($mybb->input['inlinetype'] == 'search')
		{
			$posts = getids($mybb->input['searchid'], 'search');
		}
		else
		{
			$posts = getids($tid, 'thread');
		}

		if(count($posts) < 1)
		{
			error($lang->error_inline_nopostsselected);
		}
		if(!is_moderator_by_pids($posts, "candeleteposts"))
		{
			error_no_permission();
		}
		$inlineids = implode("|", $posts);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($tid, 'thread');
		}

		$return_url = htmlspecialchars_uni($mybb->input['url']);

		eval("\$multidelete = \"".$templates->get("moderation_inline_deleteposts")."\";");
		output_page($multidelete);
		break;

	// Actually delete the posts in inline moderation
	case "do_multideleteposts":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		$postlist = explode("|", $mybb->input['posts']);
		if(!is_moderator_by_pids($postlist, "candeleteposts"))
		{
			error_no_permission();
		}
		$postlist = array_map('intval', $postlist);
		$pids = implode(',', $postlist);

		$tids = array();
		if($pids)
		{
			$query = $db->simple_select("threads", "tid", "firstpost IN({$pids})");
			while($threadid = $db->fetch_field($query, "tid"))
			{
				$tids[] = $threadid;
			}
		}

		$deletecount = 0;
		foreach($postlist as $pid)
		{
			$pid = intval($pid);
			$moderation->delete_post($pid);
			$plist[] = $pid;
			$deletecount++;
		}

		// If we have multiple threads, we must be coming from the search
		if(!empty($tids))
		{
			foreach($tids as $tid)
			{
				$moderation->delete_thread($tid);
				mark_reports($tid, "thread");
				$url = get_forum_link($fid);
			}
		}
		// Otherwise we're just deleting from showthread.php
		else
		{
			$query = $db->simple_select("posts", "*", "tid='$tid'");
			$numposts = $db->num_rows($query);
			if(!$numposts)
			{
				$moderation->delete_thread($tid);
				mark_reports($tid, "thread");
				$url = get_forum_link($fid);
			}
			else
			{
				mark_reports($plist, "posts");
				$url = get_thread_link($thread['tid']);
			}
		}

		$lang->deleted_selective_posts = $lang->sprintf($lang->deleted_selective_posts, $deletecount);
		log_moderator_action($modlogdata, $lang->deleted_selective_posts);
		moderation_redirect($url, $lang->redirect_postsdeleted);
		break;

	// Merge posts - Inline moderation
	case "multimergeposts":
		add_breadcrumb($lang->nav_multi_mergeposts);

		if($mybb->input['inlinetype'] == 'search')
		{
			$posts = getids($mybb->input['searchid'], 'search');
		}
		else
		{
			$posts = getids($tid, 'thread');
		}

		// Add the selected posts from other threads
		foreach($mybb->cookies as $key => $value)
		{
			if(strpos($key, "inlinemod_thread") !== false && $key != "inlinemod_thread$tid")
			{
				$inlinepostlist = explode("|", $mybb->cookies[$key]);
				foreach($inlinepostlist as $p)
				{
					$p = intval($p);

					if(!empty($p))
					{
						$posts[] = intval($p);
					}
				}
				// Remove the cookie once its data is retrieved
				my_unsetcookie($key);
			}
		}

		if(empty($posts))
		{
			error($lang->error_inline_nopostsselected);
		}

		if(!is_moderator_by_pids($posts, "canmanagethreads"))
		{
			error_no_permission();
		}

		$postlist = "";
		$query = $db->query("
			SELECT p.*, u.*
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (p.uid=u.uid)
			WHERE pid IN (".implode($posts, ",").")
			ORDER BY dateline ASC
		");
		$altbg = "trow1";
		while($post = $db->fetch_array($query))
		{
			$postdate = my_date($mybb->settings['dateformat'], $post['dateline']);
			$posttime = my_date($mybb->settings['timeformat'], $post['dateline']);
			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode'],
				"allow_videocode" => $forum['allowvideocode'],
				"filter_badwords" => 1
			);
			if($post['smilieoff'] == 1)
			{
				$parser_options['allow_smilies'] = 0;
			}

			$message = $parser->parse_message($post['message'], $parser_options);
			eval("\$postlist .= \"".$templates->get("moderation_mergeposts_post")."\";");
			$altbg = alt_trow();
		}

		$inlineids = implode("|", $posts);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($tid, 'thread');
		}

		$return_url = htmlspecialchars_uni($mybb->input['url']);

		eval("\$multimerge = \"".$templates->get("moderation_inline_mergeposts")."\";");
		output_page($multimerge);
		break;

	// Actually merge the posts - Inline moderation
	case "do_multimergeposts":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		$mergepost = $mybb->input['mergepost'];
		if(count($mergepost) <= 1)
		{
			error($lang->error_nomergeposts);
		}

		foreach($mergepost as $pid => $yes)
		{
			$postlist[] = intval($pid);
		}

		if(!is_moderator_by_pids($postlist, "canmanagethreads"))
		{
			error_no_permission();
		}

		foreach($postlist as $pid)
		{
			$pid = intval($pid);
			$plist[] = $pid;
		}

		$masterpid = $moderation->merge_posts($plist, $tid, $mybb->input['sep']);

		mark_reports($plist, "posts");
		log_moderator_action($modlogdata, $lang->merged_selective_posts);
		moderation_redirect(get_post_link($masterpid)."#pid$masterpid", $lang->redirect_inline_postsmerged);
		break;

	// Split posts - Inline moderation
	case "multisplitposts":
		add_breadcrumb($lang->nav_multi_splitposts);

		if($mybb->input['inlinetype'] == 'search')
		{
			$posts = getids($mybb->input['searchid'], 'search');
		}
		else
		{
			$posts = getids($tid, 'thread');
		}

		if(count($posts) < 1)
		{
			error($lang->error_inline_nopostsselected);
		}

		if(!is_moderator_by_pids($posts, "canmanagethreads"))
		{
			error_no_permission();
		}
		$posts = array_map('intval', $posts);
		$pidin = implode(',', $posts);

		// Make sure that we are not splitting a thread with one post
		// Select number of posts in each thread that the splitted post is in
		$query = $db->query("
			SELECT DISTINCT p.tid, COUNT(q.pid) as count
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."posts q ON (p.tid=q.tid)
			WHERE p.pid IN ($pidin)
			GROUP BY p.tid, p.pid
		");
		$threads = $pcheck = array();
		while($tcheck = $db->fetch_array($query))
		{
			if(intval($tcheck['count']) <= 1)
			{
				error($lang->error_cantsplitonepost);
			}
			$threads[] = $pcheck[] = $tcheck['tid']; // Save tids for below
		}

		// Make sure that we are not splitting all posts in the thread
		// The query does not return a row when the count is 0, so find if some threads are missing (i.e. 0 posts after removal)
		$query = $db->query("
			SELECT DISTINCT p.tid, COUNT(q.pid) as count
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."posts q ON (p.tid=q.tid)
			WHERE p.pid IN ($pidin) AND q.pid NOT IN ($pidin)
			GROUP BY p.tid, p.pid
		");
		$pcheck2 = array();
		while($tcheck = $db->fetch_array($query))
		{
			if($tcheck['count'] > 0)
			{
				$pcheck2[] = $tcheck['tid'];
			}
		}
		if(count($pcheck2) != count($pcheck))
		{
			// One or more threads do not have posts after splitting
			error($lang->error_cantsplitall);
		}

		$inlineids = implode("|", $posts);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($tid, 'thread');
		}
		$forumselect = build_forum_jump("", $fid, 1, '', 0, true, '', "moveto");
		eval("\$splitposts = \"".$templates->get("moderation_inline_splitposts")."\";");
		output_page($splitposts);
		break;

	// Actually split the posts - Inline moderation
	case "do_multisplitposts":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		$plist = array();
		$postlist = explode("|", $mybb->input['posts']);
		foreach($postlist as $pid)
		{
			$pid = intval($pid);
			$plist[] = $pid;
		}

		if(!is_moderator_by_pids($plist, "canmanagethreads"))
		{
			error_no_permission();
		}

		// Ensure all posts exist
		$posts = array();
		if(!empty($plist))
		{
			$query = $db->simple_select('posts', 'pid', 'pid IN ('.implode(',', $plist).')');
			while($pid = $db->fetch_field($query, 'pid'))
			{
				$posts[] = $pid;
			}
		}

		if(empty($posts))
		{
			error($lang->error_inline_nopostsselected);
		}

		$pidin = implode(',', $posts);

		// Make sure that we are not splitting a thread with one post
		// Select number of posts in each thread that the splitted post is in
		$query = $db->query("
			SELECT DISTINCT p.tid, COUNT(q.pid) as count
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."posts q ON (p.tid=q.tid)
			WHERE p.pid IN ($pidin)
			GROUP BY p.tid, p.pid
		");
		$pcheck = array();
		while($tcheck = $db->fetch_array($query))
		{
			if(intval($tcheck['count']) <= 1)
			{
				error($lang->error_cantsplitonepost);
			}
			$pcheck[] = $tcheck['tid']; // Save tids for below
		}

		// Make sure that we are not splitting all posts in the thread
		// The query does not return a row when the count is 0, so find if some threads are missing (i.e. 0 posts after removal)
		$query = $db->query("
			SELECT DISTINCT p.tid, COUNT(q.pid) as count
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."posts q ON (p.tid=q.tid)
			WHERE p.pid IN ($pidin) AND q.pid NOT IN ($pidin)
			GROUP BY p.tid, p.pid
		");
		$pcheck2 = array();
		while($tcheck = $db->fetch_array($query))
		{
			if($tcheck['count'] > 0)
			{
				$pcheck2[] = $tcheck['tid'];
			}
		}
		if(count($pcheck2) != count($pcheck))
		{
			// One or more threads do not have posts after splitting
			error($lang->error_cantsplitall);
		}

		if($mybb->input['moveto'])
		{
			$moveto = intval($mybb->input['moveto']);
		}
		else
		{
			$moveto = $fid;
		}

		$newforum = get_forum($moveto);
		if(!$newforum || $newforum['type'] != "f" || $newforum['type'] == "f" && $newforum['linkto'] != '')
		{
			error($lang->error_invalidforum);
		}

		$newsubject = $mybb->input['newsubject'];
		$newtid = $moderation->split_posts($posts, $tid, $moveto, $newsubject);

		$pid_list = implode(', ', $posts);
		$lang->split_selective_posts = $lang->sprintf($lang->split_selective_posts, $pid_list, $newtid);
		log_moderator_action($modlogdata, $lang->split_selective_posts);

		moderation_redirect(get_thread_link($newtid), $lang->redirect_threadsplit);
		break;

	// Approve posts - Inline moderation
	case "multiapproveposts":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if($mybb->input['inlinetype'] == 'search')
		{
			$posts = getids($mybb->input['searchid'], 'search');
		}
		else
		{
			$posts = getids($tid, 'thread');
		}
		if(count($posts) < 1)
		{
			error($lang->error_inline_nopostsselected);
		}

		if(!is_moderator_by_pids($posts, "canmanagethreads"))
		{
			error_no_permission();
		}

		$pids = array();
		foreach($posts as $pid)
		{
			$pids[] = intval($pid);
		}

		$moderation->approve_posts($pids);

		log_moderator_action($modlogdata, $lang->multi_approve_posts);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($tid, 'thread');
		}
		moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_inline_postsapproved);
		break;

	// Unapprove posts - Inline moderation
	case "multiunapproveposts":

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if($mybb->input['inlinetype'] == 'search')
		{
			$posts = getids($mybb->input['searchid'], 'search');
		}
		else
		{
			$posts = getids($tid, 'thread');
		}

		if(count($posts) < 1)
		{
			error($lang->error_inline_nopostsselected);
		}
		$pids = array();

		if(!is_moderator_by_pids($posts, "canmanagethreads"))
		{
			error_no_permission();
		}
		foreach($posts as $pid)
		{
			$pids[] = intval($pid);
		}

		$moderation->unapprove_posts($pids);

		log_moderator_action($modlogdata, $lang->multi_unapprove_posts);
		if($mybb->input['inlinetype'] == 'search')
		{
			clearinline($mybb->input['searchid'], 'search');
		}
		else
		{
			clearinline($tid, 'thread');
		}
		moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_inline_postsunapproved);
		break;
	default:
		require_once MYBB_ROOT."inc/class_custommoderation.php";
		$custommod = new CustomModeration;
		$tool = $custommod->tool_info(intval($mybb->input['action']));
		if($tool !== false)
		{
			// Verify incoming POST request
			verify_post_check($mybb->input['my_post_key']);

			if($tool['type'] == 't' && $mybb->input['modtype'] == 'inlinethread')
			{
				if($mybb->input['inlinetype'] == 'search')
				{
					$tids = getids($mybb->input['searchid'], 'search');
				}
				else
				{
					$tids = getids($fid, "forum");
				}
				if(count($tids) < 1)
				{
					error($lang->error_inline_nopostsselected);
				}
				if(!is_moderator_by_tids($tids))
				{
					error_no_permission();
				}

				$thread_options = unserialize($tool['threadoptions']);
				if($thread_options['movethread'] && $forum_cache[$thread_options['movethread']]['type'] != "f")
				{
					error($lang->error_movetocategory);
				}

				$custommod->execute(intval($mybb->input['action']), $tids);
 				$lang->custom_tool = $lang->sprintf($lang->custom_tool, $tool['name']);
				log_moderator_action($modlogdata, $lang->custom_tool);
				if($mybb->input['inlinetype'] == 'search')
				{
					clearinline($mybb->input['searchid'], 'search');
					$lang->redirect_customtool_search = $lang->sprintf($lang->redirect_customtool_search, $tool['name']);
					$return_url = htmlspecialchars_uni($mybb->input['url']);
					redirect($return_url, $lang->redirect_customtool_search);
				}
				else
				{
					clearinline($fid, "forum");
					$lang->redirect_customtool_forum = $lang->sprintf($lang->redirect_customtool_forum, $tool['name']);
					redirect(get_forum_link($fid), $lang->redirect_customtool_forum);
				}
				break;
			}
			elseif($tool['type'] == 't' && $mybb->input['modtype'] == 'thread')
			{
				if(!is_moderator_by_tids($tid))
				{
					error_no_permission();
				}

				$thread_options = unserialize($tool['threadoptions']);
				if($thread_options['movethread'] && $forum_cache[$thread_options['movethread']]['type'] != "f")
				{
					error($lang->error_movetocategory);
				}

				$ret = $custommod->execute(intval($mybb->input['action']), $tid);
 				$lang->custom_tool = $lang->sprintf($lang->custom_tool, $tool['name']);
				log_moderator_action($modlogdata, $lang->custom_tool);
				if($ret == 'forum')
				{
					$lang->redirect_customtool_forum = $lang->sprintf($lang->redirect_customtool_forum, $tool['name']);
					moderation_redirect(get_forum_link($fid), $lang->redirect_customtool_forum);
				}
				else
				{
					$lang->redirect_customtool_thread = $lang->sprintf($lang->redirect_customtool_thread, $tool['name']);
					moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_customtool_thread);
				}
				break;
			}
			elseif($tool['type'] == 'p' && $mybb->input['modtype'] == 'inlinepost')
			{
				if($mybb->input['inlinetype'] == 'search')
				{
					$pids = getids($mybb->input['searchid'], 'search');
				}
				else
				{
					$pids = getids($tid, 'thread');
				}

				if(count($pids) < 1)
				{
					error($lang->error_inline_nopostsselected);
				}
				if(!is_moderator_by_pids($pids))
				{
					error_no_permission();
				}

				// Get threads which are associated with the posts
				$tids = array();
				$options = array(
					'order_by' => 'dateline',
					'order_dir' => 'asc'
				);
				$query = $db->simple_select("posts", "DISTINCT tid", "pid IN (".implode(',',$pids).")", $options);
				while($row = $db->fetch_array($query))
				{
					$tids[] = $row['tid'];
				}

				$ret = $custommod->execute(intval($mybb->input['action']), $tids, $pids);
 				$lang->custom_tool = $lang->sprintf($lang->custom_tool, $tool['name']);
				log_moderator_action($modlogdata, $lang->custom_tool);
				if($mybb->input['inlinetype'] == 'search')
				{
					clearinline($mybb->input['searchid'], 'search');
					$lang->redirect_customtool_search = $lang->sprintf($lang->redirect_customtool_search, $tool['name']);
					$return_url = htmlspecialchars_uni($mybb->input['url']);
					redirect($return_url, $lang->redirect_customtool_search);
				}
				else
				{
					clearinline($tid, 'thread');
					if($ret == 'forum')
					{
						$lang->redirect_customtool_forum = $lang->sprintf($lang->redirect_customtool_forum, $tool['name']);
						moderation_redirect(get_forum_link($fid), $lang->redirect_customtool_forum);
					}
					else
					{
						$lang->redirect_customtool_thread = $lang->sprintf($lang->redirect_customtool_thread, $tool['name']);
						moderation_redirect(get_thread_link($tid), $lang->redirect_customtool_thread);
					}
				}

				break;
			}
		}
		error_no_permission();
		break;
}

// Some little handy functions for our inline moderation
function getids($id, $type)
{
	global $mybb;

	$newids = array();
	$cookie = "inlinemod_".$type.$id;
	$cookie_ids = explode("|", $mybb->cookies[$cookie]);

	foreach($cookie_ids as $cookie_id)
	{
		if(empty($cookie_id))
		{
			continue;
		}

		if($cookie_id == 'ALL')
		{
			$newids += getallids($id, $type);
		}
		else
		{
			$newids[] = intval($cookie_id);
		}
	}

	return $newids;
}

function getallids($id, $type)
{
	global $db, $mybb;

	$ids = array();

	// Get any removed threads (after our user hit 'all')
	$removed_ids = array();
	$cookie = "inlinemod_".$type.$id."_removed";
	if($mybb->cookies[$cookie])
	{
		$removed_ids = explode("|", $mybb->cookies[$cookie]);

		if(!is_array($removed_ids))
		{
			$removed_ids = array();
		}
	}

	// "Select all Threads in this forum" only supported by forumdisplay and search
	if($type == 'forum')
	{
		$query = $db->simple_select("threads", "tid", "fid='".intval($id)."'");
		while($tid = $db->fetch_field($query, "tid"))
		{
			if(in_array($tid, $removed_ids))
			{
				continue;
			}

			$ids[] = $tid;
		}
	}
	else if($type == 'search')
	{
		$query = $db->simple_select("searchlog", "*", "sid='".$db->escape_string($id)."' AND uid='{$mybb->user['uid']}'", 1);
		$searchlog = $db->fetch_array($query);
		if($searchlog['resulttype'] == 'posts')
		{
			$ids = explode(',', $searchlog['posts']);
		}
		else
		{
			$ids = explode(',', $searchlog['threads']);
		}

		if(is_array($ids))
		{
			foreach($ids as $key => $tid)
			{
				if(in_array($tid, $removed_ids))
				{
					unset($ids[$key]);
				}
			}
		}
	}

	return $ids;
}

function clearinline($id, $type)
{
	my_unsetcookie("inlinemod_".$type.$id);
	my_unsetcookie("inlinemod_".$type.$id."_removed");
}

function extendinline($id, $type)
{
	global $mybb;

	my_setcookie("inlinemod_$type$id", '', TIME_NOW+3600);
	my_setcookie("inlinemod_$type$id_removed", '', TIME_NOW+3600);
}

/**
 * Checks if the current user is a moderator of all the posts specified
 *
 * Note: If no posts are specified, this function will return true.  It is the
 * responsibility of the calling script to error-check this case if necessary.
 *
 * @param array Array of post IDs
 * @param string Permission to check
 * @returns bool True if moderator of all; false otherwise
 */
function is_moderator_by_pids($posts, $permission='')
{
	global $db, $mybb;

	// Speedy determination for supermods/admins and guests
	if($mybb->usergroup['issupermod'])
	{
		return true;
	}
	elseif(!$mybb->user['uid'])
	{
		return false;
	}
	// Make an array of threads if not an array
	if(!is_array($posts))
	{
		$posts = array($posts);
	}
	// Validate input
	$posts = array_map('intval', $posts);
	$posts[] = 0;
	// Get forums
	$posts_string = implode(',', $posts);
	$query = $db->simple_select("posts", "DISTINCT fid", "pid IN ($posts_string)");
	while($forum = $db->fetch_array($query))
	{
		if(!is_moderator($forum['fid'], $permission))
		{
			return false;
		}
	}
	return true;
}

/**
 * Checks if the current user is a moderator of all the threads specified
 *
 * Note: If no threads are specified, this function will return true.  It is the
 * responsibility of the calling script to error-check this case if necessary.
 *
 * @param array Array of thread IDs
 * @param string Permission to check
 * @returns bool True if moderator of all; false otherwise
 */
function is_moderator_by_tids($threads, $permission='')
{
	global $db, $mybb;

	// Speedy determination for supermods/admins and guests
	if($mybb->usergroup['issupermod'])
	{
		return true;
	}
	elseif(!$mybb->user['uid'])
	{
		return false;
	}
	// Make an array of threads if not an array
	if(!is_array($threads))
	{
		$threads = array($threads);
	}
	// Validate input
	$threads = array_map('intval', $threads);
	$threads[] = 0;
	// Get forums
	$threads_string = implode(',', $threads);
	$query = $db->simple_select("threads", "DISTINCT fid", "tid IN ($threads_string)");
	while($forum = $db->fetch_array($query))
	{
		if(!is_moderator($forum['fid'], $permission))
		{
			return false;
		}
	}
	return true;
}

/**
 * Special redirect that takes a return URL into account
 * @param string URL
 * @param string Message
 * @param string Title
 */
function moderation_redirect($url, $message="", $title="")
{
	global $mybb;
	if(!empty($mybb->input['url']))
	{
		redirect(htmlentities($mybb->input['url']), $message, $title);
	}
	redirect($url, $message, $title);
}
?>