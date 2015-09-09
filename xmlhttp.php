<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * The deal with this file is that it handles all of the XML HTTP Requests for MyBB.
 *
 * It contains a stripped down version of the MyBB core which does not load things
 * such as themes, who's online data, all of the language packs and more.
 *
 * This is done to make response times when using XML HTTP Requests faster and
 * less intense on the server.
 */

define("IN_MYBB", 1);

// We don't want visits here showing up on the Who's Online
define("NO_ONLINE", 1);

define('THIS_SCRIPT', 'xmlhttp.php');

// Load MyBB core files
require_once dirname(__FILE__)."/inc/init.php";

$shutdown_queries = $shutdown_functions = array();

// Load some of the stock caches we'll be using.
$groupscache = $cache->read("usergroups");

if(!is_array($groupscache))
{
	$cache->update_usergroups();
	$groupscache = $cache->read("usergroups");
}

// Send no cache headers
header("Expires: Sat, 1 Jan 2000 01:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

// Create the session
require_once MYBB_ROOT."inc/class_session.php";
$session = new session;
$session->init();

// Load the language we'll be using
if(!isset($mybb->settings['bblanguage']))
{
	$mybb->settings['bblanguage'] = "english";
}
if(isset($mybb->user['language']) && $lang->language_exists($mybb->user['language']))
{
	$mybb->settings['bblanguage'] = $mybb->user['language'];
}
$lang->set_language($mybb->settings['bblanguage']);

if(function_exists('mb_internal_encoding') && !empty($lang->settings['charset']))
{
	@mb_internal_encoding($lang->settings['charset']);
}

// Load the theme
// 1. Check cookies
if(!$mybb->user['uid'] && !empty($mybb->cookies['mybbtheme']))
{
	$mybb->user['style'] = (int)$mybb->cookies['mybbtheme'];
}

// 2. Load style
if(isset($mybb->user['style']) && (int)$mybb->user['style'] != 0)
{
	$loadstyle = "tid='".(int)$mybb->user['style']."'";
}
else
{
	$loadstyle = "def='1'";
}

// Load basic theme information that we could be needing.
if($loadstyle != "def='1'")
{
	$query = $db->simple_select('themes', 'name, tid, properties, allowedgroups', $loadstyle, array('limit' => 1));
	$theme = $db->fetch_array($query);

	if(isset($theme['tid']) && !is_member($theme['allowedgroups']) && $theme['allowedgroups'] != 'all')
	{
		if(isset($mybb->cookies['mybbtheme']))
		{
			my_unsetcookie('mybbtheme');
		}

		$loadstyle = "def='1'";
	}
}

if($loadstyle == "def='1'")
{
	if(!$cache->read('default_theme'))
	{
		$cache->update_default_theme();
	}

	$theme = $cache->read('default_theme');
}

// No theme was found - we attempt to load the master or any other theme
if(!isset($theme['tid']) || isset($theme['tid']) && !$theme['tid'])
{
	// Missing theme was from a user, run a query to set any users using the theme to the default
	$db->update_query('users', array('style' => 0), "style = '{$mybb->user['style']}'");

	// Attempt to load the master or any other theme if the master is not available
	$query = $db->simple_select('themes', 'name, tid, properties, stylesheets', '', array('order_by' => 'tid', 'limit' => 1));
	$theme = $db->fetch_array($query);
}
$theme = @array_merge($theme, my_unserialize($theme['properties']));

// Set the appropriate image language directory for this theme.
// Are we linking to a remote theme server?
if(my_substr($theme['imgdir'], 0, 7) == 'http://' || my_substr($theme['imgdir'], 0, 8) == 'https://')
{
	// If a language directory for the current language exists within the theme - we use it
	if(!empty($mybb->user['language']))
	{
		$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->user['language'];
	}
	else
	{
		// Check if a custom language directory exists for this theme
		if(!empty($mybb->settings['bblanguage']))
		{
			$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->settings['bblanguage'];
		}
		// Otherwise, the image language directory is the same as the language directory for the theme
		else
		{
			$theme['imglangdir'] = $theme['imgdir'];
		}
	}
}
else
{
	$img_directory = $theme['imgdir'];

	if($mybb->settings['usecdn'] && !empty($mybb->settings['cdnpath']))
	{
		$img_directory = rtrim($mybb->settings['cdnpath'], '/') . '/' . ltrim($theme['imgdir'], '/');
	}

	if(!@is_dir($img_directory))
	{
		$theme['imgdir'] = 'images';
	}

	// If a language directory for the current language exists within the theme - we use it
	if(!empty($mybb->user['language']) && is_dir($img_directory.'/'.$mybb->user['language']))
	{
		$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->user['language'];
	}
	else
	{
		// Check if a custom language directory exists for this theme
		if(is_dir($img_directory.'/'.$mybb->settings['bblanguage']))
		{
			$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->settings['bblanguage'];
		}
		// Otherwise, the image language directory is the same as the language directory for the theme
		else
		{
			$theme['imglangdir'] = $theme['imgdir'];
		}
	}

	$theme['imgdir'] = $mybb->get_asset_url($theme['imgdir']);
	$theme['imglangdir'] = $mybb->get_asset_url($theme['imglangdir']);
}

$templatelist = "postbit_editedby,xmlhttp_buddyselect_online,xmlhttp_buddyselect_offline,xmlhttp_buddyselect";
$templates->cache($db->escape_string($templatelist));

if($lang->settings['charset'])
{
	$charset = $lang->settings['charset'];
}
// If not, revert to UTF-8
else
{
	$charset = "UTF-8";
}

$lang->load("global");
$lang->load("xmlhttp");

$closed_bypass = array("refresh_captcha", "validate_captcha");

$mybb->input['action'] = $mybb->get_input('action');

$plugins->run_hooks("xmlhttp");

// If the board is closed, the user is not an administrator and they're not trying to login, show the board closed message
if($mybb->settings['boardclosed'] == 1 && $mybb->usergroup['canviewboardclosed'] != 1 && !in_array($mybb->input['action'], $closed_bypass))
{
	// Show error
	if(!$mybb->settings['boardclosed_reason'])
	{
		$mybb->settings['boardclosed_reason'] = $lang->boardclosed_reason;
	}

	$lang->error_boardclosed .= "<br /><em>{$mybb->settings['boardclosed_reason']}</em>";

	xmlhttp_error($lang->error_boardclosed);
}

// Fetch a list of usernames beginning with a certain string (used for auto completion)
if($mybb->input['action'] == "get_users")
{
	$mybb->input['query'] = ltrim($mybb->get_input('query'));

	// If the string is less than 3 characters, quit.
	if(my_strlen($mybb->input['query']) < 3)
	{
		exit;
	}

	if($mybb->get_input('getone', MyBB::INPUT_INT) == 1)
	{
		$limit = 1;
	}
	else
	{
		$limit = 15;
	}

	// Send our headers.
	header("Content-type: application/json; charset={$charset}");

	// Query for any matching users.
	$query_options = array(
		"order_by" => "username",
		"order_dir" => "asc",
		"limit_start" => 0,
		"limit" => $limit
	);

	$plugins->run_hooks("xmlhttp_get_users_start");

	$query = $db->simple_select("users", "uid, username", "username LIKE '".$db->escape_string_like($mybb->input['query'])."%'", $query_options);
	if($limit == 1)
	{
		$user = $db->fetch_array($query);
		$user['username'] = htmlspecialchars_uni($user['username']);
		$data = array('id' => $user['username'], 'text' => $user['username']);
	}
	else
	{
		$data = array();
		while($user = $db->fetch_array($query))
		{
			$user['username'] = htmlspecialchars_uni($user['username']);
			$data[] = array('id' => $user['username'], 'text' => $user['username']);
		}
	}

	$plugins->run_hooks("xmlhttp_get_users_end");

	echo json_encode($data);
	exit;
}
// This action provides editing of thread/post subjects from within their respective list pages.
else if($mybb->input['action'] == "edit_subject" && $mybb->request_method == "post")
{
	// Verify POST request
	if(!verify_post_check($mybb->get_input('my_post_key'), true))
	{
		xmlhttp_error($lang->invalid_post_code);
	}

	// We're editing a thread subject.
	if($mybb->get_input('tid', MyBB::INPUT_INT))
	{
		// Fetch the thread.
		$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
		if(!$thread)
		{
			xmlhttp_error($lang->thread_doesnt_exist);
		}

		// Fetch some of the information from the first post of this thread.
		$query_options = array(
			"order_by" => "dateline",
			"order_dir" => "asc",
		);
		$query = $db->simple_select("posts", "pid,uid,dateline", "tid='".$thread['tid']."'", $query_options);
		$post = $db->fetch_array($query);
	}
	else
	{
		exit;
	}

	// Fetch the specific forum this thread/post is in.
	$forum = get_forum($thread['fid']);

	// Missing thread, invalid forum? Error.
	if(!$forum || $forum['type'] != "f")
	{
		xmlhttp_error($lang->thread_doesnt_exist);
	}

	// Fetch forum permissions.
	$forumpermissions = forum_permissions($forum['fid']);

	$plugins->run_hooks("xmlhttp_edit_subject_start");

	// If this user is not a moderator with "caneditposts" permissions.
	if(!is_moderator($forum['fid'], "caneditposts"))
	{
		// Thread is closed - no editing allowed.
		if($thread['closed'] == 1)
		{
			xmlhttp_error($lang->thread_closed_edit_subjects);
		}
		// Forum is not open, user doesn't have permission to edit, or author doesn't match this user - don't allow editing.
		else if($forum['open'] == 0 || $forumpermissions['caneditposts'] == 0 || $mybb->user['uid'] != $post['uid'] || $mybb->user['uid'] == 0)
		{
			xmlhttp_error($lang->no_permission_edit_subject);
		}
		// If we're past the edit time limit - don't allow editing.
		else if($mybb->usergroup['edittimelimit'] != 0 && $post['dateline'] < (TIME_NOW-($mybb->usergroup['edittimelimit']*60)))
		{
			$lang->edit_time_limit = $lang->sprintf($lang->edit_time_limit, $mybb->usergroup['edittimelimit']);
			xmlhttp_error($lang->edit_time_limit);
		}
		$ismod = false;
	}
	else
	{
		$ismod = true;
	}
	$subject = $mybb->get_input('value');
	if(my_strtolower($charset) != "utf-8")
	{
		if(function_exists("iconv"))
		{
			$subject = iconv($charset, "UTF-8//IGNORE", $subject);
		}
		else if(function_exists("mb_convert_encoding"))
		{
			$subject = @mb_convert_encoding($subject, $charset, "UTF-8");
		}
		else if(my_strtolower($charset) == "iso-8859-1")
		{
			$subject = utf8_decode($subject);
		}
	}

	// Only edit subject if subject has actually been changed
	if($thread['subject'] != $subject)
	{
		// Set up posthandler.
		require_once MYBB_ROOT."inc/datahandlers/post.php";
		$posthandler = new PostDataHandler("update");
		$posthandler->action = "post";

		// Set the post data that came from the input to the $post array.
		$updatepost = array(
			"pid" => $post['pid'],
			"tid" => $thread['tid'],
			"subject" => $subject,
			"edit_uid" => $mybb->user['uid']
		);
		$posthandler->set_data($updatepost);

		// Now let the post handler do all the hard work.
		if(!$posthandler->validate_post())
		{
			$post_errors = $posthandler->get_friendly_errors();
			xmlhttp_error($post_errors);
		}
		// No errors were found, we can call the update method.
		else
		{
			$posthandler->update_post();
			if($ismod == true)
			{
				$modlogdata = array(
					"tid" => $thread['tid'],
					"fid" => $forum['fid']
				);
				log_moderator_action($modlogdata, $lang->edited_post);
			}
		}
	}

	require_once MYBB_ROOT."inc/class_parser.php";
	$parser = new postParser;

	// Send our headers.
	header("Content-type: application/json; charset={$charset}");

	$plugins->run_hooks("xmlhttp_edit_subject_end");

	$mybb->input['value'] = $parser->parse_badwords($mybb->get_input('value'));

	// Spit the subject back to the browser.
	$subject = substr($mybb->input['value'], 0, 120); // 120 is the varchar length for the subject column
	echo json_encode(array("subject" => '<a href="'.get_thread_link($thread['tid']).'">'.htmlspecialchars_uni($subject).'</a>'));

	// Close the connection.
	exit;
}
else if($mybb->input['action'] == "edit_post")
{
	// Fetch the post from the database.
	$post = get_post($mybb->get_input('pid', MyBB::INPUT_INT));

	// No result, die.
	if(!$post)
	{
		xmlhttp_error($lang->post_doesnt_exist);
	}

	// Fetch the thread associated with this post.
	$thread = get_thread($post['tid']);

	// Fetch the specific forum this thread/post is in.
	$forum = get_forum($thread['fid']);

	// Missing thread, invalid forum? Error.
	if(!$thread || !$forum || $forum['type'] != "f")
	{
		xmlhttp_error($lang->thread_doesnt_exist);
	}

	// Check if this forum is password protected and we have a valid password
	if(check_forum_password($forum['fid'], 0, true))
	{
		xmlhttp_error($lang->wrong_forum_password);
	}

	// Fetch forum permissions.
	$forumpermissions = forum_permissions($forum['fid']);

	$plugins->run_hooks("xmlhttp_edit_post_start");

	// If this user is not a moderator with "caneditposts" permissions.
	if(!is_moderator($forum['fid'], "caneditposts"))
	{
		// Thread is closed - no editing allowed.
		if($thread['closed'] == 1)
		{
			xmlhttp_error($lang->thread_closed_edit_message);
		}
		// Forum is not open, user doesn't have permission to edit, or author doesn't match this user - don't allow editing.
		else if($forum['open'] == 0 || $forumpermissions['caneditposts'] == 0 || $mybb->user['uid'] != $post['uid'] || $mybb->user['uid'] == 0 || $mybb->user['suspendposting'] == 1)
		{
			xmlhttp_error($lang->no_permission_edit_post);
		}
		// If we're past the edit time limit - don't allow editing.
		else if($mybb->usergroup['edittimelimit'] != 0 && $post['dateline'] < (TIME_NOW-($mybb->usergroup['edittimelimit']*60)))
		{
			$lang->edit_time_limit = $lang->sprintf($lang->edit_time_limit, $mybb->usergroup['edittimelimit']);
			xmlhttp_error($lang->edit_time_limit);
		}
		// User can't edit unapproved post
		if($post['visible'] == 0)
		{
			xmlhttp_error($lang->post_moderation);
		}
	}

	$plugins->run_hooks("xmlhttp_edit_post_end");

	if($mybb->get_input('do') == "get_post")
	{
		// Send our headers.
		header("Content-type: application/json; charset={$charset}");

		// Send the contents of the post.
		echo json_encode($post['message']);
		exit;
	}
	else if($mybb->get_input('do') == "update_post")
	{
		// Verify POST request
		if(!verify_post_check($mybb->get_input('my_post_key'), true))
		{
			xmlhttp_error($lang->invalid_post_code);
		}

		$message = $mybb->get_input('value');
		$editreason = $mybb->get_input('editreason');
		if(my_strtolower($charset) != "utf-8")
		{
			if(function_exists("iconv"))
			{
				$message = iconv($charset, "UTF-8//IGNORE", $message);
				$editreason = iconv($charset, "UTF-8//IGNORE", $editreason);
			}
			else if(function_exists("mb_convert_encoding"))
			{
				$message = @mb_convert_encoding($message, $charset, "UTF-8");
				$editreason = @mb_convert_encoding($editreason, $charset, "UTF-8");
			}
			else if(my_strtolower($charset) == "iso-8859-1")
			{
				$message = utf8_decode($message);
				$editreason = utf8_decode($editreason);
			}
		}

		// Set up posthandler.
		require_once MYBB_ROOT."inc/datahandlers/post.php";
		$posthandler = new PostDataHandler("update");
		$posthandler->action = "post";

		// Set the post data that came from the input to the $post array.
		$updatepost = array(
			"pid" => $post['pid'],
			"message" => $message,
			"editreason" => $editreason,
			"edit_uid" => $mybb->user['uid']
		);

		// If this is the first post set the prefix. If a forum requires a prefix the quick edit would throw an error otherwise
		if($post['pid'] == $thread['firstpost'])
		{
			$updatepost['prefix'] = $thread['prefix'];
		}

		$posthandler->set_data($updatepost);

		// Now let the post handler do all the hard work.
		if(!$posthandler->validate_post())
		{
			$post_errors = $posthandler->get_friendly_errors();
			xmlhttp_error($post_errors);
		}
		// No errors were found, we can call the update method.
		else
		{
			$postinfo = $posthandler->update_post();
			$visible = $postinfo['visible'];
			if($visible == 0 && !is_moderator($post['fid'], "canviewunapprove"))
			{
				// Is it the first post?
				if($thread['firstpost'] == $post['pid'])
				{
					echo json_encode(array("moderation_thread" => $lang->thread_moderation, 'url' => $mybb->settings['bburl'].'/'.get_forum_link($thread['fid']), "message" => $post['message']));
					exit;
				}
				else
				{
					echo json_encode(array("moderation_post" => $lang->post_moderation, 'url' => $mybb->settings['bburl'].'/'.get_thread_link($thread['tid']), "message" => $post['message']));
					exit;
				}
			}
		}

		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;

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

		$post['message'] = $parser->parse_message($message, $parser_options);

		// Now lets fetch all of the attachments for these posts.
		if($mybb->settings['enableattachments'] != 0)
		{
			$query = $db->simple_select("attachments", "*", "pid='{$post['pid']}'");
			while($attachment = $db->fetch_array($query))
			{
				$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
			}

			require_once MYBB_ROOT."inc/functions_post.php";

			get_post_attachments($post['pid'], $post);
		}

		// Figure out if we need to show an "edited by" message
		// Only show if at least one of "showeditedby" or "showeditedbyadmin" is enabled
		if($mybb->settings['showeditedby'] != 0 && $mybb->settings['showeditedbyadmin'] != 0)
		{
			$post['editdate'] = my_date('relative', TIME_NOW);
			$post['editnote'] = $lang->sprintf($lang->postbit_edited, $post['editdate']);
			$post['editedprofilelink'] = build_profile_link($mybb->user['username'], $mybb->user['uid']);
			$post['editreason'] = trim($editreason);
			$editreason = "";
			if($post['editreason'] != "")
			{
				$post['editreason'] = $parser->parse_badwords($post['editreason']);
				$post['editreason'] = htmlspecialchars_uni($post['editreason']);
				eval("\$editreason = \"".$templates->get("postbit_editedby_editreason")."\";");
			}
			eval("\$editedmsg = \"".$templates->get("postbit_editedby")."\";");
		}

		// Send our headers.
		header("Content-type: application/json; charset={$charset}");

		$editedmsg_response = null;
		if($editedmsg)
		{
			$editedmsg_response = str_replace(array("\r", "\n"), "", $editedmsg);
		}

		$plugins->run_hooks("xmlhttp_update_post");

		echo json_encode(array("message" => $post['message']."\n", "editedmsg" => $editedmsg_response));
		exit;
	}
}
// Fetch the list of multiquoted posts which are not in a specific thread
else if($mybb->input['action'] == "get_multiquoted")
{
	// If the cookie does not exist, exit
	if(!array_key_exists("multiquote", $mybb->cookies))
	{
		exit;
	}
	// Divide up the cookie using our delimeter
	$multiquoted = explode("|", $mybb->cookies['multiquote']);

	$plugins->run_hooks("xmlhttp_get_multiquoted_start");

	// No values - exit
	if(!is_array($multiquoted))
	{
		exit;
	}

	// Loop through each post ID and sanitize it before querying
	foreach($multiquoted as $post)
	{
		$quoted_posts[$post] = (int)$post;
	}

	// Join the post IDs back together
	$quoted_posts = implode(",", $quoted_posts);

	// Fetch unviewable forums
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
	$message = '';

	// Are we loading all quoted posts or only those not in the current thread?
	if(empty($mybb->input['load_all']))
	{
		$from_tid = "p.tid != '".$mybb->get_input('tid', MyBB::INPUT_INT)."' AND ";
	}
	else
	{
		$from_tid = '';
	}

	require_once MYBB_ROOT."inc/class_parser.php";
	$parser = new postParser;

	require_once MYBB_ROOT."inc/functions_posting.php";

	$plugins->run_hooks("xmlhttp_get_multiquoted_intermediate");

	// Query for any posts in the list which are not within the specified thread
	$query = $db->query("
		SELECT p.subject, p.message, p.pid, p.tid, p.username, p.dateline, t.fid, p.visible, u.username AS userusername
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE {$from_tid}p.pid IN ({$quoted_posts}) {$unviewable_forums} {$inactiveforums}
		ORDER BY p.dateline
	");
	while($quoted_post = $db->fetch_array($query))
	{
		if(!is_moderator($quoted_post['fid'], "canviewunapprove") && $quoted_post['visible'] == 0)
		{
			continue;
		}

		$message .= parse_quoted_message($quoted_post, false);
	}
	if($mybb->settings['maxquotedepth'] != '0')
	{
		$message = remove_message_quotes($message);
	}

	// Send our headers.
	header("Content-type: application/json; charset={$charset}");

	$plugins->run_hooks("xmlhttp_get_multiquoted_end");

	echo json_encode(array("message" => $message));
	exit;
}
else if($mybb->input['action'] == "refresh_captcha")
{
	$imagehash = $db->escape_string($mybb->get_input('imagehash'));
	$query = $db->simple_select("captcha", "dateline", "imagehash='$imagehash'");
	if($db->num_rows($query) == 0)
	{
		xmlhttp_error($lang->captcha_not_exists);
	}
	$db->delete_query("captcha", "imagehash='$imagehash'");
	$randomstr = random_str(5);
	$imagehash = md5(random_str(12));
	$regimagearray = array(
		"imagehash" => $imagehash,
		"imagestring" => $randomstr,
		"dateline" => TIME_NOW
	);

	$plugins->run_hooks("xmlhttp_refresh_captcha");

	$db->insert_query("captcha", $regimagearray);
	header("Content-type: application/json; charset={$charset}");
	echo json_encode(array("imagehash" => $imagehash));
	exit;
}
else if($mybb->input['action'] == "validate_captcha")
{
	header("Content-type: application/json; charset={$charset}");
	$imagehash = $db->escape_string($mybb->get_input('imagehash'));
	$query = $db->simple_select("captcha", "imagestring", "imagehash='$imagehash'");
	if($db->num_rows($query) == 0)
	{
		echo json_encode($lang->captcha_valid_not_exists);
		exit;
	}
	$imagestring = $db->fetch_field($query, 'imagestring');

	$plugins->run_hooks("xmlhttp_validate_captcha");

	if(my_strtolower($imagestring) == my_strtolower($mybb->get_input('imagestring')))
	{
		//echo json_encode(array("success" => $lang->captcha_matches));
		echo json_encode("true");
		exit;
	}
	else
	{
		echo json_encode($lang->captcha_does_not_match);
		exit;
	}
}
else if($mybb->input['action'] == "refresh_question" && $mybb->settings['securityquestion'])
{
	header("Content-type: application/json; charset={$charset}");

	$sid = $db->escape_string($mybb->get_input('question_id'));
	$query = $db->query("
		SELECT q.qid, s.sid
		FROM ".TABLE_PREFIX."questionsessions s
		LEFT JOIN ".TABLE_PREFIX."questions q ON (q.qid=s.qid)
		WHERE q.active='1' AND s.sid='{$sid}'
	");

	if($db->num_rows($query) == 0)
	{
		xmlhttp_error($lang->answer_valid_not_exists);
	}

	$qsession = $db->fetch_array($query);

	// Delete previous question session
	$db->delete_query("questionsessions", "sid='$sid'");

	require_once MYBB_ROOT."inc/functions_user.php";

	$sid = generate_question($qsession['qid']);
	$query = $db->query("
		SELECT q.question, s.sid
		FROM ".TABLE_PREFIX."questionsessions s
		LEFT JOIN ".TABLE_PREFIX."questions q ON (q.qid=s.qid)
		WHERE q.active='1' AND s.sid='{$sid}' AND q.qid!='{$qsession['qid']}'
	");

	$plugins->run_hooks("xmlhttp_refresh_question");

	if($db->num_rows($query) > 0)
	{
		$question = $db->fetch_array($query);

		echo json_encode(array("question" => htmlspecialchars_uni($question['question']), 'sid' => htmlspecialchars_uni($question['sid'])));
		exit;
	}
	else
	{
		xmlhttp_error($lang->answer_valid_not_exists);
	}
}
elseif($mybb->input['action'] == "validate_question" && $mybb->settings['securityquestion'])
{
	header("Content-type: application/json; charset={$charset}");
	$sid = $db->escape_string($mybb->get_input('question'));
	$answer = $db->escape_string($mybb->get_input('answer'));

	$query = $db->query("
		SELECT q.*, s.sid
		FROM ".TABLE_PREFIX."questionsessions s
		LEFT JOIN ".TABLE_PREFIX."questions q ON (q.qid=s.qid)
		WHERE q.active='1' AND s.sid='{$sid}'
	");

	if($db->num_rows($query) == 0)
	{
		echo json_encode($lang->answer_valid_not_exists);
		exit;
	}
	else
	{
		$question = $db->fetch_array($query);
		$valid_answers = preg_split("/\r\n|\n|\r/", $question['answer']);
		$validated = 0;

		foreach($valid_answers as $answers)
		{
			if(my_strtolower($answers) == my_strtolower($answer))
			{
				$validated = 1;
			}
		}

		$plugins->run_hooks("xmlhttp_validate_question");

		if($validated != 1)
		{
			echo json_encode($lang->answer_does_not_match);
			exit;
		}
		else
		{
			echo json_encode("true");
			exit;
		}
	}

	exit;
}
else if($mybb->input['action'] == "complex_password")
{
	$password = trim($mybb->get_input('password'));
	$password = str_replace(array(unichr(160), unichr(173), unichr(0xCA), dec_to_utf8(8238), dec_to_utf8(8237), dec_to_utf8(8203)), array(" ", "-", "", "", "", ""), $password);

	header("Content-type: application/json; charset={$charset}");

	$plugins->run_hooks("xmlhttp_complex_password");

	if(!preg_match("/^.*(?=.{".$mybb->settings['minpasswordlength'].",})(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$/", $password))
	{
		echo json_encode($lang->complex_password_fails);
	}
	else
	{
		// Return nothing but an OK password if passes regex
		echo json_encode("true");
	}

	exit;
}
else if($mybb->input['action'] == "username_availability")
{
	if(!verify_post_check($mybb->get_input('my_post_key'), true))
	{
		xmlhttp_error($lang->invalid_post_code);
	}

	require_once MYBB_ROOT."inc/functions_user.php";
	$username = $mybb->get_input('username');

	// Fix bad characters
	$username = trim_blank_chrs($username);
	$username = str_replace(array(unichr(160), unichr(173), unichr(0xCA), dec_to_utf8(8238), dec_to_utf8(8237), dec_to_utf8(8203)), array(" ", "-", "", "", "", ""), $username);

	// Remove multiple spaces from the username
	$username = preg_replace("#\s{2,}#", " ", $username);

	header("Content-type: application/json; charset={$charset}");

	if(empty($username))
	{
		echo json_encode($lang->banned_characters_username);
		exit;
	}

	// Check if the username belongs to the list of banned usernames.
	$banned_username = is_banned_username($username, true);
	if($banned_username)
	{
		echo json_encode($lang->banned_username);
		exit;
	}

	// Check for certain characters in username (<, >, &, and slashes)
	if(strpos($username, "<") !== false || strpos($username, ">") !== false || strpos($username, "&") !== false || my_strpos($username, "\\") !== false || strpos($username, ";") !== false || strpos($username, ",") !== false || !validate_utf8_string($username, false, false))
	{
		echo json_encode($lang->banned_characters_username);
		exit;
	}

	// Check if the username is actually already in use
	$user = get_user_by_username($username);

	$plugins->run_hooks("xmlhttp_username_availability");

	if($user['uid'])
	{
		$lang->username_taken = $lang->sprintf($lang->username_taken, htmlspecialchars_uni($username));
		echo json_encode($lang->username_taken);
		exit;
	}
	else
	{
		//$lang->username_available = $lang->sprintf($lang->username_available, htmlspecialchars_uni($username));
		echo json_encode("true");
		exit;
	}
}
else if($mybb->input['action'] == "username_exists")
{
	if(!verify_post_check($mybb->get_input('my_post_key'), true))
	{
		xmlhttp_error($lang->invalid_post_code);
	}

	require_once MYBB_ROOT."inc/functions_user.php";
	$username = $mybb->get_input('value');

	header("Content-type: application/json; charset={$charset}");

	if(!trim($username))
	{
		echo json_encode(array("success" => 1));
		exit;
	}

	// Check if the username actually exists
	$user = get_user_by_username($username);

	$plugins->run_hooks("xmlhttp_username_exists");

	if($user['uid'])
	{
		$lang->valid_username = $lang->sprintf($lang->valid_username, htmlspecialchars_uni($username));
		echo json_encode(array("success" => $lang->valid_username));
		exit;
	}
	else
	{
		$lang->invalid_username = $lang->sprintf($lang->invalid_username, htmlspecialchars_uni($username));
		echo json_encode($lang->invalid_username);
		exit;
	}
}
else if($mybb->input['action'] == "get_buddyselect")
{
	// Send our headers.
	header("Content-type: text/plain; charset={$charset}");

	if($mybb->user['buddylist'] != "")
	{
		$query_options = array(
			"order_by" => "username",
			"order_dir" => "asc"
		);

		$plugins->run_hooks("xmlhttp_get_buddyselect_start");

		$timecut = TIME_NOW - $mybb->settings['wolcutoff'];
		$query = $db->simple_select("users", "uid, username, usergroup, displaygroup, lastactive, lastvisit, invisible", "uid IN ({$mybb->user['buddylist']})", $query_options);
		$online = array();
		$offline = array();
		while($buddy = $db->fetch_array($query))
		{
			$buddy_name = format_name($buddy['username'], $buddy['usergroup'], $buddy['displaygroup']);
			$profile_link = build_profile_link($buddy_name, $buddy['uid'], '_blank');
			if($buddy['lastactive'] > $timecut && ($buddy['invisible'] == 0 || $mybb->user['usergroup'] == 4) && $buddy['lastvisit'] != $buddy['lastactive'])
			{
				eval("\$online[] = \"".$templates->get("xmlhttp_buddyselect_online")."\";");
			}
			else
			{
				eval("\$offline[] = \"".$templates->get("xmlhttp_buddyselect_offline")."\";");
			}
		}
		$online = implode("", $online);
		$offline = implode("", $offline);

		$plugins->run_hooks("xmlhttp_get_buddyselect_end");

		eval("\$buddy_select = \"".$templates->get("xmlhttp_buddyselect")."\";");
		echo $buddy_select;
	}
	else
	{
		xmlhttp_error($lang->buddylist_error);
	}
}

/**
 * Spits an XML Http based error message back to the browser
 *
 * @param string $message The message to send back.
 */
function xmlhttp_error($message)
{
	global $charset;

	// Send our headers.
	header("Content-type: application/json; charset={$charset}");

	// Do we have an array of messages?
	if(is_array($message))
	{
		$response = array();
		foreach($message as $error)
		{
			$response[] = $error;
		}

		// Send the error messages.
		echo json_encode(array("errors" => array($response)));

		exit;
	}

	// Just a single error? Send it along.
	echo json_encode(array("errors" => array($message)));

	exit;
}
