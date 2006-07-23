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

// Load MyBB core files
require "./inc/init.php";

$shutdown_queries = array();

// Load some of the stock caches we'll be using.
$groupscache = $cache->read("usergroups");

if(!is_array($groupscache))
{
	$cache->updateusergroups();
	$groupscache = $cache->read("usergroups");
}
$fpermissioncache = $cache->read("forumpermissions");


// Send page headers
send_page_headers();


// Create the session
require MYBB_ROOT."inc/class_session.php";
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

// Load the language pack for this file.
if(isset($mybb->user['style']) && intval($mybb->user['style']) != 0)
{
	$loadstyle = "tid='".$mybb->user['style']."'";
}
else
{
	$loadstyle = "def=1";
}

$query = $db->simple_select(TABLE_PREFIX."themes", "name, tid, themebits", $loadstyle);
$theme = $db->fetch_array($query);
$theme = @array_merge($theme, unserialize($theme['themebits']));

// Set the appropriate image language directory for this theme.
if(!empty($mybb->user['language']) && is_dir($theme['imgdir'].'/'.$mybb->user['language']))
{
	$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->user['language'];
}
else
{
	if(is_dir($theme['imgdir'].'/'.$mybb->settings['bblanguage']))
	{
		$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->settings['bblanguage'];
	}
	else
	{
		$theme['imglangdir'] = $theme['imgdir'];
	}
}

$lang->load("global");
$lang->load("xmlhttp");

// Load basic theme information that we could be needing.

$plugins->run_hooks("xmlhttp");

// Fetch a list of usernames beginning with a certain string (used for auto completion)
if($mybb->input['action'] == "get_users")
{
	// If the string is less than 3 characters, quit.
	if(my_strlen($mybb->input['query']) < 3)
	{
		exit;
	}
	
	// Send our headers.
	header("Content-type: text/html; charset=utf-8");

	// Sanitize the input.
	$mybb->input['query'] = str_replace(array("%", "_"), array("\\%", "\\_"), $mybb->input['query']);
	
	// Query for any matching users.
	$query_options = array(
		"order_by" => "username",
		"order_dir" => "asc",
		"limit_start" => 0,
		"limit" => 15
	);
	$query = $db->simple_select(TABLE_PREFIX."users", "uid, username", "username LIKE '".$db->escape_string($mybb->input['query'])."%'", $query_options);
	while($user = $db->fetch_array($query))
	{
		$user['username'] = htmlspecialchars_uni($user['username']);
		// Send the result to the browser for this user.
		echo "<div>\n";
		echo "<span class=\"username\">{$user['username']}</span>\n";
		//echo "<span class=\"uid\">".$user['uid']."</span>\n";
		echo "</div>\n";
	}
}
// This action provides editing of thread/post subjects from within their respective list pages.
else if($mybb->input['action'] == "edit_subject" && $mybb->request_method == "post")
{
	// Editing a post subject.
	if($mybb->input['pid'])
	{
		// Fetch the post from the database.
		$post = get_post($mybb->input['pid']);
		
		// No result, die.
		if(!$post['pid'])
		{
			xmlhttp_error($lang->post_doesnt_exist);
		}
		
		// Fetch the thread associated with this post.
		$thread = get_thread($post['tid']);
	}
	
	// We're editing a thread subject.
	else if($mybb->input['tid'])
	{
		// Fetch the thread.
		$thread = get_thread($mybb->input['tid']);
		
		// Fetch some of the information from the first post of this thread.
		$query_options = array(
			"order_by" => "dateline",
			"order_dir" => "asc",
		);
		$query = $db->simple_select(TABLE_PREFIX."posts", "pid,uid,dateline", "tid='".$thread['tid']."'", $query_options);
		$post = $db->fetch_array($query);
	}
	// Fetch the specific forum this thread/post is in.
	$forum = get_forum($thread['fid']);

	// Missing thread, invalid forum? Error.
	if(!$thread['tid'] || !$forum['fid'] || $forum['type'] != "f")
	{
		xmlhttp_error($lang->thread_doesnt_exist);
	}
	
	// Fetch forum permissions.
	$forumpermissions = forum_permissions($forum['fid']);
	
	// If this user is not a moderator with "caneditposts" permissions.
	if(is_moderator($forum['fid'], "caneditposts") != "yes")
	{
		// Thread is closed - no editing allowed.
		if($thread['closed'] == "yes")
		{
			xmlhttp_error($lang->thread_closed_edit_subjects);
		}
		// Forum is not open, user doesn't have permission to edit, or author doesn't match this user - don't allow editing.
		else if($forum['open'] == "no" || $forumpermissions['caneditposts'] == "no" || $mybb->user['uid'] != $post['uid'] || $mybb->user['uid'] == 0)
		{
			xmlhttp_error($lang->no_permission_edit_subject);
		}
		// If we're past the edit time limit - don't allow editing.
		else if($mybb->settings['edittimelimit'] != 0 && $post['dateline'] < (time()-($mybb->settings['edittimelimit']*60)))
		{
			$lang->edit_time_limit = sprintf($lang->edit_time_limit, $mybb->settings['edittimelimit']);
			xmlhttp_error($lang->edit_time_limit);
		}
		$ismod = false;
	}
	else
	{
		$ismod = true;
	}
	
	// Set up posthandler.
	require_once MYBB_ROOT."inc/datahandlers/post.php";
	$posthandler = new PostDataHandler("update");
	$posthandler->action = "post";

	// Set the post data that came from the input to the $post array.
	$updatepost = array(
		"pid" => $post['pid'],
		"tid" => $thread['tid'],
		"subject" => $mybb->input['value'],
		"edit_uid" => $mybb->user['uid']
	);
	$posthandler->set_data($updatepost);

	// Now let the post handler do all the hard work.
	if(!$posthandler->validate_post())
	{
		$post_errors = $posthandler->get_friendly_errors();
		$errors = implode("\n\n", $post_errors);
		xmlhttp_error($errors);
	}
	// No errors were found, we can call the update method.
	else
	{
		$posthandler->update_post();
		if($ismod == true)
		{
			$modlogdata = array(
				"tid" => $thread['tid'],
				"pid" => $post['pid'],
				"fid" => $forum['fid']
			);
			log_moderator_action($modlogdata, "Edited Post");
		}
	}

	// Send our headers.
	header("Content-type: text/html; charset=utf-8");
	
	// Spit the subject back to the browser.
	echo $mybb->input['value'];
	
	// Close the connection.
	exit;
}
else if($mybb->input['action'] == "edit_post")
{
	// Fetch the post from the database.
	$post = get_post($mybb->input['pid']);
		
	// No result, die.
	if(!$post['pid'])
	{
		xmlhttp_error($lang->post_doesnt_exist);
	}
	
	// Fetch the thread associated with this post.
	$thread = get_thread($post['tid']);

	// Fetch the specific forum this thread/post is in.
	$forum = get_forum($thread['fid']);

	// Missing thread, invalid forum? Error.
	if(!$thread['tid'] || !$forum['fid'] || $forum['type'] != "f")
	{
		xmlhttp_error($lang->thread_doesnt_exist);
	}
	
	// Fetch forum permissions.
	$forumpermissions = forum_permissions($forum['fid']);
	
	// If this user is not a moderator with "caneditposts" permissions.
	if(is_moderator($forum['fid'], "caneditposts") != "yes")
	{
		// Thread is closed - no editing allowed.
		if($thread['closed'] == "yes")
		{
			xmlhttp_error($lang->thread_closed_edit_message);
		}
		// Forum is not open, user doesn't have permission to edit, or author doesn't match this user - don't allow editing.
		else if($forum['open'] == "no" || $forumpermissions['caneditposts'] == "no" || $mybb->user['uid'] != $post['uid'] || $mybb->user['uid'] == 0)
		{
			xmlhttp_error($lang->no_permission_edit_post);
		}
		// If we're past the edit time limit - don't allow editing.
		else if($mybb->settings['edittimelimit'] != 0 && $post['dateline'] < (time()-($mybb->settings['edittimelimit']*60)))
		{
			$lang->edit_time_limit = sprintf($lang->edit_time_limit, $mybb->settings['edittimelimit']);
			xmlhttp_error($lang->edit_time_limit);
		}
	}
	if($mybb->input['do'] == "get_post")
	{
		// Send our headers.
		header("Content-type: text/html; charset=utf-8");
		
		$post['message'] = htmlspecialchars_uni($post['message']);
		
		// Send the contents of the post.
		eval("\$inline_editor = \"".$templates->get("xmlhttp_inline_post_editor")."\";");		
		echo $inline_editor;
		exit;
	}
	else if($mybb->input['do'] == "update_post")
	{
		//$message = rawurldecode($mybb->input['value']);
		$message = $mybb->input['value'];
		// Set up posthandler.
		require_once MYBB_ROOT."inc/datahandlers/post.php";
		$posthandler = new PostDataHandler("update");
		$posthandler->action = "post";

		// Set the post data that came from the input to the $post array.
		$updatepost = array(
			"pid" => $mybb->input['pid'],
			"message" => $message,
			"edit_uid" => $mybb->user['uid']
		);
		$posthandler->set_data($updatepost);

		// Now let the post handler do all the hard work.
		if(!$posthandler->validate_post())
		{
			$post_errors = $posthandler->get_friendly_errors();
			$errors = implode("\n\n", $post_errors);
			xmlhttp_error($errors);
		}
		// No errors were found, we can call the update method.
		else
		{
			$posthandler->update_post();
		}

		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;
		
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
	
		$post['message'] = $parser->parse_message($message, $parser_options);
		
		// Now lets fetch all of the attachments for these posts.
		$query = $db->simple_select(TABLE_PREFIX."attachments", "*", "pid='{$post['pid']}'");
		while($attachment = $db->fetch_array($query))
		{
			$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
		}
		
		require_once MYBB_ROOT."/inc/functions_post.php";
		
		get_post_attachments($post['pid'], $post);
		
		// Send our headers.
		header("Content-type: text/plain; charset=utf-8");
		echo "<p>\n";
		echo $post['message'];
		echo "</p>\n";
	}
}
// Fetch the list of multiquoted posts which are not in a specific thread
else if($mybb->input['action'] == "get_multiquoted")
{
	// If the cookie does not exist, exit
	if(!array_key_exists("multiquote", $_COOKIE))
	{
		exit;
	}
	// Divide up the cookie using our delimeter
	$multiquoted = explode("|", $_COOKIE['multiquote']);
	
	// No values - exit
	if(!is_array($multiquoted))
	{
		exit;
	}
	
	// Loop through each post ID and sanitize it before querying
	foreach($multiquoted as $post)
	{
		$quoted_posts[$post] = intval($post);
	}

	// Join the post IDs back together
	$quoted_posts = implode(",", $quoted_posts);
	
	// Fetch unviewable forums
	$unviewable_forums = get_unviewable_forums();
	if($unviewable_forums)
	{
		$unviewable_forums = "AND t.fid NOT IN ({$unviewable_forums})";
	}
	$message = '';
	// Query for any posts in the list which are not within the specified thread
	$query = $db->query("
		SELECT p.subject, p.message, p.pid, p.tid, p.username, u.username AS userusername
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE p.tid != '".intval($mybb->input['tid'])."' AND p.pid IN ($quoted_posts) {$unviewable_forums} AND p.visible='1'
	");
	while($quoted_post = $db->fetch_array($query))
	{
		// Swap username over if we have a registered user
		if($quoted_post['userusername'])
		{
			$quoted_post['username'] = $quoted_post['userusername'];
		}
		// Clean up the message
		$quoted_post['message'] = preg_replace('#^/me (.*)$#im', "* $quoted[username] \\1", $quoted_post['message']);
		$quoted_post['message'] = preg_replace("#\[attachment=([0-9]+?)\]#i", '', $quoted_post['message']);
		
		// Tack on to list of messages
		$message .= "[quote={$quoted_post['username']}]\n{$quoted_post['message']}\n[/quote]\n\n";
	}
	
	// Send our headers.
	header("Content-type: text/plain; charset=utf-8");
	echo $message;
	exit;	
}
/**
 * Spits an XML Http based error message back to the browser
 *
 * @param string The message to send back.
 */
function xmlhttp_error($message)
{
	// Send our headers.
	header("Content-type: text/html; charset=utf-8");
	
	// Send the error message.
	echo "<error>".$message."</error>";
	
	// Exit
	exit;
}
?>