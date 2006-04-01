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

/**
 * The deal with this file is that it handles all of the XML HTTP Requests for MyBB.
 *
 * It contains a stripped down version of the MyBB core which does not load things
 * such as themes, who's online data, all of the language packs and more.
 *
 * This is done to make response times when using XML HTTP Requests faster and
 * less intense on the server.
 */

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
pageheaders();


// Create the session
require "./inc/class_session.php";
$session = new session;
$session->init();

// Load the language we'll be using
if(!isset($mybb->settings['bblanguage']))
{
	$mybb->settings['bblanguage'] = "english";
}
if(isset($mybb->user['language']) && $lang->languageExists($mybb->user['language']))
{
	$mybb->settings['bblanguage'] = $mybb->user['language'];
}
$lang->setLanguage($mybb->settings['bblanguage']);

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

//$lang->load("xmlhttp");

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
	$mybb->input['query'] = rawurldecode($mybb->input['query']);
	$mybb->input['query'] = str_replace(array("%", "_"), array("\%", "\_"), $mybb->input['query']);
	
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
		// Send the result to the browser for this user.
		echo "<div>\n";
		echo "<span class=\"username\">".htmlspecialchars_uni($user['username'])."</span>\n";
		//echo "<span class=\"uid\">".$user['uid']."</span>\n";
		echo "</div>\n";
	}
}
// This action provides editing of thread/post subjects from within their respective list pages.
else if($mybb->input['action'] == "edit_subject")// && $mybb->request_method == "post")
{
	// Sanitize the incoming subject.
	$mybb->input['value'] = rawurldecode($mybb->input['value']);
		
	// If we don't have a new subject, quit straight away with an error.
	if(strlen(trim($mybb->input['value'])) == 0)
	{
		xmlhttp_error("You did not enter a new subject.");
	}
	
	// Editing a post subject.
	if($mybb->input['pid'])
	{
		// Fetch the post from the database.
		$post = get_post($mybb->input['pid']);
		
		// No result, die.
		if(!$post['pid'])
		{
			xmlhttp_error("The specified post does not exist.");
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
	else
	{
		xmlhttp_error("");
	}
	// Fetch the specific forum this thread/post is in.
	$forum = get_forum($thread['fid']);

	// Missing thread, invalid forum? Error.
	if(!$thread['tid'] || !$forum['fid'] || $forum['type'] != "f")
	{
		xmlhttp_error("The specified thread does not exist.");
	}
	
	// Fetch forum permissions.
	$forumpermissions = forum_permissions($forum['fid']);
	
	// If this user is not a moderator with "caneditposts" permissions.
	if(ismod($forum['fid'], "caneditposts") != "yes")
	{
		// Thread is closed - no editing allowed.
		if($thread['closed'] == "yes")
		{
			xmlhttp_error("This thread is closed and you may not edit subjects.");
		}
		// Forum is not open, user doesn't have permission to edit, or author doesn't match this user - don't allow editing.
		else if($forum['open'] == "no" || $forumpermissions['caneditposts'] == "no" || $mybb->user['uid'] != $post['uid'])
		{
			xmlhttp_error("You do not have permission to this title.");
		}
		// If we're past the edit time limit - don't allow editing.
		else if($mybb->settings['edittimelimit'] != 0 && $post['dateline'] < (time()-($mybb->settings['edittimelimit']*60)))
		{
			$lang->edit_time_limit = sprintf($lang->edit_time_limit, $mybb->settings['edittimelimit']);
			xmlhttp_error($lang->edit_time_limit);
		}
	}
	// Update the post subject in the posts table.
	$new_subject = array(
		"subject" => $db->escape_string($mybb->input['value'])
	);
	$db->update_query(TABLE_PREFIX."posts", $new_subject, "pid='".$post['pid']."'");
	
	// If this is a thread subject we're editing, also update the thread subject.
	if($mybb->input['tid'])
	{
		$db->update_query(TABLE_PREFIX."threads", $new_subject, "tid='".$thread['tid']."'");
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
		xmlhttp_error("The specified post does not exist.");
	}
	
	// Fetch the thread associated with this post.
	$thread = get_thread($post['tid']);

	// Fetch the specific forum this thread/post is in.
	$forum = get_forum($thread['fid']);

	// Missing thread, invalid forum? Error.
	if(!$thread['tid'] || !$forum['fid'] || $forum['type'] != "f")
	{
		xmlhttp_error("The specified thread does not exist.");
	}
	
	// Fetch forum permissions.
	$forumpermissions = forum_permissions($forum['fid']);
	
	// If this user is not a moderator with "caneditposts" permissions.
	if(ismod($forum['fid'], "caneditposts") != "yes")
	{
		// Thread is closed - no editing allowed.
		if($thread['closed'] == "yes")
		{
			xmlhttp_error("This thread is closed and you may not edit subjects.");
		}
		// Forum is not open, user doesn't have permission to edit, or author doesn't match this user - don't allow editing.
		else if($forum['open'] == "no" || $forumpermissions['caneditposts'] == "no" || $mybb->user['uid'] != $post['uid'])
		{
			xmlhttp_error("You do not have permission to this title.");
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
		
		// Send the contents of the post.
		eval("\$inline_editor = \"".$templates->get("xmlhttp_inline_post_editor")."\";");		
		echo $inline_editor;
		exit;
	}
	else if($mybb->input['do'] == "update_post")
	{
		$message = rawurldecode($mybb->input['value']);
		$updatepost = array(
			"message" => addslashes($message),
		);

		// If we need to show the edited by, let's do so.
		if(($mybb->settings['showeditedby'] == "yes" && ismod($post['fid'], "caneditposts", $post['edit_uid']) != "yes") || ($mybb->settings['showeditedbyadmin'] == "yes" && ismod($post['fid'], "caneditposts", $post['edit_uid']) == "yes"))
		{
			$updatepost['edituid'] = intval($post['edit_uid']);
			$updatepost['edittime'] = time();
		}
		
		$db->update_query(TABLE_PREFIX."posts", $updatepost, "pid='".$mybb->input['pid']."'");
		
		require "./inc/class_parser.php";
		$parser = new postParser;
		
		$parser_options = array(
			"allow_html" => $forum['allowhtml'],
			"allow_mycode" => $forum['allowmycode'],
			"allow_smilies" => $forum['allowsmilies'],
			"allow_imgcode" => $forum['allowimgcode']
		);
		if($post['smilieoff'] == "yes")
		{
			$parser_options['allow_smilies'] = "no";
		}
	
		$message = $parser->parse_message($message, $parser_options);
		
		// Send our headers.
		header("Content-type: text/plain; charset=utf-8");
		echo "<p>\n";
		echo $message;
		echo "</p>\n";
		exit;	
	}
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