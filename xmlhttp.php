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

$shutdown_queries = array();

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

// Load the language pack for this file.
if(isset($mybb->user['style']) && intval($mybb->user['style']) != 0)
{
	$loadstyle = "tid='".$mybb->user['style']."'";
}
else
{
	$loadstyle = "def='1'";
}

// Load basic theme information that we could be needing.
if($loadstyle == "def='1'")
{
	if(!$cache->read('default_theme'))
	{
		$cache->update_default_theme();
	}
	$theme = $cache->read('default_theme');
}

$theme = @array_merge($theme, unserialize($theme['properties']));

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

$templatelist = "postbit_editedby,xmlhttp_inline_post_editor,xmlhttp_buddyselect_online,xmlhttp_buddyselect_offline,xmlhttp_buddyselect";
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

$plugins->run_hooks("xmlhttp");

$mybb->input['action'] = $mybb->get_input('action');

// Fetch a list of usernames beginning with a certain string (used for auto completion)
if($mybb->input['action'] == "get_users")
{
	$mybb->input['query'] = ltrim($mybb->get_input('query'));

	// If the string is less than 3 characters, quit.
	if(my_strlen($mybb->input['query']) < 3)
	{
		exit;
	}

	if($mybb->get_input('getone', 1) == 1)
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

	// Editing a post subject.
	if($mybb->get_input('pid', 1))
	{
		// Fetch the post from the database.
		$post = get_post($mybb->get_input('pid', 1));

		// No result, die.
		if(!$post)
		{
			xmlhttp_error($lang->post_doesnt_exist);
		}

		// Fetch the thread associated with this post.
		$thread = get_thread($post['tid']);
		if(!$thread)
		{
			xmlhttp_error($lang->thread_doesnt_exist);
		}
	}

	// We're editing a thread subject.
	else if($mybb->get_input('tid', 1))
	{
		// Fetch the thread.
		$thread = get_thread($mybb->get_input('tid', 1));
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
				"pid" => $post['pid'],
				"fid" => $forum['fid']
			);
			log_moderator_action($modlogdata, $lang->edited_post);
		}
	}

	require_once MYBB_ROOT."inc/class_parser.php";
	$parser = new postParser;

	// Send our headers.
	header("Content-type: application/json; charset={$charset}");

	$mybb->input['value'] = $parser->parse_badwords($mybb->get_input('value'));

	// Spit the subject back to the browser.
	$subject = substr($mybb->input['value'], 0, 120); // 120 is the varchar length for the subject column
	echo json_encode(array("subject" => htmlspecialchars_uni($subject)));

	// Close the connection.
	exit;
}
else if($mybb->input['action'] == "edit_post")
{
	// Fetch the post from the database.
	$post = get_post($mybb->get_input('pid', 1));

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

	// Fetch forum permissions.
	$forumpermissions = forum_permissions($forum['fid']);

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
		else if($mybb->settings['edittimelimit'] != 0 && $post['dateline'] < (TIME_NOW-($mybb->settings['edittimelimit']*60)))
		{
			$lang->edit_time_limit = $lang->sprintf($lang->edit_time_limit, $mybb->settings['edittimelimit']);
			xmlhttp_error($lang->edit_time_limit);
		}
		// User can't edit unapproved post
		if($post['visible'] == 0)
		{
			xmlhttp_error($lang->post_moderation);
		}

		// Forum is closed - no editing allowed
		if($forum['open'] == 0)
		{
			xmlhttp_error($lang->no_permission_edit_post);
		}
	}
	if($mybb->get_input('do') == "get_post")
	{
		// Send our headers.
		//header("Content-type: text/xml; charset={$charset}");
		header("Content-type: text/html; charset={$charset}");

		//$post['message'] = htmlspecialchars_uni($post['message']);

		// Send the contents of the post.
		/*eval("\$inline_editor = \"".$templates->get("xmlhttp_inline_post_editor")."\";");
		echo "<?xml version=\"1.0\" encoding=\"{$charset}\"?".">";
		echo "<form>".$inline_editor."</form>";*/
		echo $post['message'];
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
		if(my_strtolower($charset) != "utf-8")
		{
			if(function_exists("iconv"))
			{
				$message = iconv($charset, "UTF-8//IGNORE", $message);
			}
			else if(function_exists("mb_convert_encoding"))
			{
				$message = @mb_convert_encoding($message, $charset, "UTF-8");
			}
			else if(my_strtolower($charset) == "iso-8859-1")
			{
				$message = utf8_decode($message);
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
			$postinfo = $posthandler->update_post();
			$visible = $postinfo['visible'];
			if($visible == 0 && !is_moderator($post['fid']))
			{
				echo json_encode(array("failed" => $lang->post_moderation));
				exit;
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
			eval("\$editedmsg = \"".$templates->get("postbit_editedby")."\";");
		}

		// Send our headers.
		header("Content-type: application/json; charset={$charset}");

		$editedmsg_response = null;
		if($editedmsg)
		{
			$editedmsg_response = str_replace(array("\r", "\n"), "", $editedmsg);
		}

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

	// Are we loading all quoted posts or only those not in the current thread?
	if(empty($mybb->input['load_all']))
	{
		$from_tid = "p.tid != '".$mybb->get_input('tid', 1)."' AND ";
	}
	else
	{
		$from_tid = '';
	}

	require_once MYBB_ROOT."inc/class_parser.php";
	$parser = new postParser;

	require_once MYBB_ROOT."inc/functions_posting.php";

	// Query for any posts in the list which are not within the specified thread
	$query = $db->query("
		SELECT p.subject, p.message, p.pid, p.tid, p.username, p.dateline, t.fid, p.visible, u.username AS userusername
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE {$from_tid}p.pid IN ($quoted_posts) {$unviewable_forums}
		ORDER BY p.dateline
	");
	while($quoted_post = $db->fetch_array($query))
	{
		if(!is_moderator($quoted_post['fid']) && $quoted_post['visible'] == 0)
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
		echo json_encode(array("fail" => $lang->captcha_valid_not_exists));
		exit;
	}
	$imagestring = $db->fetch_field($query, 'imagestring');

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
else if($mybb->input['action'] == "complex_password")
{
	$password = trim($mybb->get_input('password'));
	$password = str_replace(array(unichr(160), unichr(173), unichr(0xCA), dec_to_utf8(8238), dec_to_utf8(8237), dec_to_utf8(8203)), array(" ", "-", "", "", "", ""), $password);

	header("Content-type: application/json; charset={$charset}");
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
	$username = trim($username);
	$username = str_replace(array(unichr(160), unichr(173), unichr(0xCA), dec_to_utf8(8238), dec_to_utf8(8237), dec_to_utf8(8203)), array(" ", "-", "", "", "", ""), $username);

	// Remove multiple spaces from the username
	$username = preg_replace("#\s{2,}#", " ", $username);

	header("Content-type: application/json; charset={$charset}");

	if(empty($username))
	{
		echo $lang->banned_characters_username;
		exit;
	}

	// Check if the username belongs to the list of banned usernames.
	$banned_username = is_banned_username($username, true);
	if($banned_username)
	{
		echo $lang->banned_username;
		exit;
	}

	// Check for certain characters in username (<, >, &, and slashes)
	if(strpos($username, "<") !== false || strpos($username, ">") !== false || strpos($username, "&") !== false || my_strpos($username, "\\") !== false || strpos($username, ";") !== false || !validate_utf8_string($username, false, false))
	{
		echo $lang->banned_characters_username;
		exit;
	}

	// Check if the username is actually already in use
	$query = $db->simple_select("users", "uid", "LOWER(username)='".$db->escape_string(my_strtolower($username))."'");
	$user = $db->fetch_array($query);

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
	$query = $db->simple_select("users", "uid", "LOWER(username)='".$db->escape_string(my_strtolower($username))."'");
	$user = $db->fetch_array($query);

	if($user['uid'])
	{
		$lang->valid_username = $lang->sprintf($lang->valid_username, htmlspecialchars_uni($username));
		echo json_encode(array("success" => $lang->valid_username));
		exit;
	}
	else
	{
		$lang->invalid_username = htmlspecialchars_uni($lang->sprintf($lang->invalid_username, htmlspecialchars_uni($username)));
		echo json_encode(array("fail" => $lang->invalid_username));
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
 * @param string The message to send back.
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

?>