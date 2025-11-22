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
define('THIS_SCRIPT', 'attachment.php');

// Load MyBB core files
require_once dirname(__FILE__).'/inc/init.php';

$shutdown_queries = $shutdown_functions = array();

// Load some of the stock caches we'll be using.
$groupscache = $cache->read('usergroups');

if(!is_array($groupscache))
{
	$cache->update_usergroups();
	$groupscache = $cache->read('usergroups');
}

// Do not use a session system for defined pages
if(isset($mybb->input['thumbnail']))
{
	define('NO_ONLINE', 1);
}

// Create the session
require_once MYBB_ROOT.'inc/class_session.php';

$session = new session();

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

if($lang->settings['charset'])
{
	$charset = $lang->settings['charset'];
}
// If not, revert to UTF-8
else
{
	$charset = "UTF-8";
}

if(function_exists('mb_internal_encoding'))
{
	mb_internal_encoding($charset);
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

	if($theme && !is_member($theme['allowedgroups']) && $theme['allowedgroups'] != 'all')
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
if(!isset($theme['tid']) || !$theme['tid'])
{
	// Missing theme was from a user, run a query to set any users using the theme to the default
	$db->update_query('users', array('style' => 0), "style = '{$mybb->user['style']}'");

	// Attempt to load the master or any other theme if the master is not available
	$query = $db->simple_select('themes', 'name, tid, properties, stylesheets', '', array('order_by' => 'tid', 'limit' => 1));

	$theme = $db->fetch_array($query);
}

$theme = array_merge($theme, my_unserialize($theme['properties']));

// Set the appropriate image language directory for this theme.
// Are we linking to a remote theme server?
if(my_validate_url($theme['imgdir']))
{
	// If a language directory for the current language exists within the theme - we use it
	if(!empty($mybb->user['language']))
	{
		$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->user['language'];
	}
	// Check if a custom language directory exists for this theme
	elseif(!empty($mybb->settings['bblanguage']))
	{
		$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->settings['bblanguage'];
	}
	// Otherwise, the image language directory is the same as the language directory for the theme
	else
	{
		$theme['imglangdir'] = $theme['imgdir'];
	}
}
else
{
	$img_directory = $theme['imgdir'];

	if($mybb->settings['usecdn'] && !empty($mybb->settings['cdnpath']))
	{
		$img_directory = rtrim($mybb->settings['cdnpath'], '/').'/'.ltrim($theme['imgdir'], '/');
	}

	if(!is_dir($img_directory))
	{
		$theme['imgdir'] = 'images';
	}

	// If a language directory for the current language exists within the theme - we use it
	if(!empty($mybb->user['language']) && is_dir($img_directory.'/'.$mybb->user['language']))
	{
		$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->user['language'];
	}
	// Check if a custom language directory exists for this theme
	elseif(is_dir($img_directory.'/'.$mybb->settings['bblanguage']))
	{
		$theme['imglangdir'] = $theme['imgdir'].'/'.$mybb->settings['bblanguage'];
	}
	// Otherwise, the image language directory is the same as the language directory for the theme
	else
	{
		$theme['imglangdir'] = $theme['imgdir'];
	}

	$theme['imgdir'] = $mybb->get_asset_url($theme['imgdir']);

	$theme['imglangdir'] = $mybb->get_asset_url($theme['imglangdir']);
}

$lang->load("global");

$mybb->input['action'] = $mybb->get_input('action');

if($mybb->settings['enableattachments'] != 1)
{
	error($lang->attachments_disabled);
}

// Find the AID we're looking for
if(isset($mybb->input['thumbnail']))
{
	$aid = $mybb->get_input('thumbnail', MyBB::INPUT_INT);
}
else
{
	$aid = $mybb->get_input('aid', MyBB::INPUT_INT);
}

$pid = $mybb->get_input('pid', MyBB::INPUT_INT);

// Select attachment data from database
if($aid)
{
	$query = $db->simple_select("attachments", "*", "aid='{$aid}'");
}
else
{
	$query = $db->simple_select("attachments", "*", "pid='{$pid}'");
}

$attachment = $db->fetch_array($query);

$plugins->run_hooks("attachment_start");

if(!$attachment)
{
	error($lang->error_invalidattachment);
}

if($attachment['thumbnail'] == '' && isset($mybb->input['thumbnail']))
{
	error($lang->error_invalidattachment);
}

$attachtypes = (array)$cache->read('attachtypes');

$ext = get_extension($attachment['filename']);

if(empty($attachtypes[$ext]))
{
	error($lang->error_invalidattachment);
}

$attachtype = $attachtypes[$ext];

$pid = $attachment['pid'];

// Don't check the permissions on preview
if($pid || $attachment['uid'] != $mybb->user['uid'])
{
	$post = get_post($pid);

	if(!$post)
	{
		error($lang->error_invalidthread);
	}

	// Check permissions if the post is not a draft
	if($post['visible'] != -2)
	{
		$thread = get_thread($post['tid']);

		if(!$thread && !isset($mybb->input['thumbnail']))
		{
			error($lang->error_invalidthread);
		}
		$fid = $thread['fid'];

		// Get forum info
		$forum = get_forum($fid);

		// Permissions
		$forumpermissions = forum_permissions($fid);

		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']) || ($forumpermissions['candlattachments'] == 0 && empty($mybb->input['thumbnail'])))
		{
			error_no_permission();
		}

		// Error if attachment is invalid or not visible
		if(!$attachment['attachname'] || (!is_moderator($fid, "canviewunapprove") && ($attachment['visible'] != 1 || $thread['visible'] != 1 || $post['visible'] != 1)))
		{
			error($lang->error_invalidattachment);
		}

		if($attachtype['forums'] != -1 && strpos(','.$attachtype['forums'].',', ','.$fid.',') === false)
		{
			error_no_permission();
		}
	}
}

if(!isset($mybb->input['thumbnail'])) // Only increment the download count if this is not a thumbnail
{
	if(!is_member($attachtype['groups']))
	{
		error_no_permission();
	}

	$attachupdate = array(
		"downloads" => $attachment['downloads'] + 1,
	);
	$db->update_query("attachments", $attachupdate, "aid='{$attachment['aid']}'");
}

// basename isn't UTF-8 safe. This is a workaround.
$attachment['filename'] = ltrim(basename(' '.$attachment['filename']));

$uploadspath_abs = mk_path_abs($mybb->settings['uploadspath']);

$plugins->run_hooks("attachment_end");

if(isset($mybb->input['thumbnail']))
{
	if(!file_exists($uploadspath_abs."/".$attachment['thumbnail']))
	{
		error($lang->error_invalidattachment);
	}

	$ext = get_extension($attachment['thumbnail']);
	switch($ext)
	{
		case "gif":
			$type = "image/gif";
			break;
		case "bmp":
			$type = "image/bmp";
			break;
		case "png":
			$type = "image/png";
			break;
		case "jpg":
		case "jpeg":
		case "jpe":
			$type = "image/jpeg";
			break;
		default:
			$type = "image/unknown";
			break;
	}

	header("Content-disposition: filename=\"{$attachment['filename']}\"");
	header("Content-type: ".$type);
	$thumb = $uploadspath_abs."/".$attachment['thumbnail'];
	header("Content-length: ".@filesize($thumb));
	$handle = fopen($thumb, 'rb');
	while(!feof($handle))
	{
		echo fread($handle, 8192);
	}
	fclose($handle);
}
else
{
	if(!file_exists($uploadspath_abs."/".$attachment['attachname']))
	{
		error($lang->error_invalidattachment);
	}

	$ext = get_extension($attachment['filename']);

	switch($attachment['filetype'])
	{
		case "application/pdf":
		case "image/bmp":
		case "image/gif":
		case "image/jpeg":
		case "image/pjpeg":
		case "image/png":
		case "text/plain":
			header("Content-type: {$attachment['filetype']}");
			if(!empty($attachtypes[$ext]['forcedownload']))
			{
				$disposition = "attachment";
			}
			else
			{
				$disposition = "inline";
			}
			break;

		default:
			$filetype = $attachment['filetype'];

			if(!$filetype)
			{
				$filetype = 'application/force-download';
			}

			header("Content-type: {$filetype}");
			$disposition = "attachment";
	}

	if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "msie") !== false)
	{
		header("Content-disposition: attachment; filename=\"{$attachment['filename']}\"");
	}
	else
	{
		header("Content-disposition: {$disposition}; filename=\"{$attachment['filename']}\"");
	}

	if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "msie 6.0") !== false)
	{
		header("Expires: -1");
	}

	header("Content-length: {$attachment['filesize']}");
	header("Content-range: bytes=0-".($attachment['filesize']-1)."/".$attachment['filesize']);
	$handle = fopen($uploadspath_abs."/".$attachment['attachname'], 'rb');
	while(!feof($handle))
	{
		echo fread($handle, 8192);
	}
	fclose($handle);
}
