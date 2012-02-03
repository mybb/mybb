<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: akismet.php 5746 2012-02-03 10:03:25Z Tomm $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
// Basically, when we include this from class_plugins.php we can do stuff in init.php, which is before we cache our templates
// So we won't need an extra call to cache it.

if(my_strpos($_SERVER['PHP_SELF'], 'showthread.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'akismet_postbit_spam';
}

$plugins->add_hook("datahandler_post_insert_thread", "akismet_verify");
$plugins->add_hook("datahandler_post_insert_thread_post", "akismet_verify");
$plugins->add_hook("datahandler_post_insert_post", "akismet_verify");
$plugins->add_hook("datahandler_post_validate_post", "akismet_fake_draft");
$plugins->add_hook("datahandler_post_validate_thread", "akismet_fake_draft");
$plugins->add_hook("newreply_do_newreply_end", "akismet_redirect_thread");
$plugins->add_hook("newthread_do_newthread_end", "akismet_redirect_forum");
$plugins->add_hook("moderation_start", "akismet_moderation_start");
$plugins->add_hook("postbit", "akismet_postbit");

$plugins->add_hook("admin_forum_menu", "akismet_admin_nav");
$plugins->add_hook("admin_forum_permissions", "akismet_admin_permissions");
$plugins->add_hook("admin_load", "akismet_admin");
$plugins->add_hook("admin_forum_action_handler", "akismet_action_handler");
$plugins->add_hook("admin_config_plugins_activate_commit", "akismet_key");

function akismet_info()
{
	global $lang;
	
	$lang->load("forum_akismet", false, true);
	
	return array(
		"name"          => $lang->akismet,
		"description"   => $lang->akismet_desc,
		"website"       => "http://mybb.com",
		"author"        => "MyBB Group",
		"authorsite"    => "http://mybb.com",
		"version"       => "1.2.2",
		"guid"          => "e57a80dbe7ff85083596a1a3b7da3ce7",
		"compatibility" => "16*",
	);
}

/**
 * ADDITIONAL PLUGIN INSTALL/UNINSTALL ROUTINES
 *
 * _install():
 *   Called whenever a plugin is installed by clicking the "Install" button in the plugin manager.
 *   If no install routine exists, the install button is not shown and it assumed any work will be
 *   performed in the _activate() routine.
 *
 * function hello_install()
 * {
 * }
 *
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
 *
 * function hello_is_installed()
 * {
 *      global $db;
 *      if($db->table_exists("hello_world"))
 *      {
 *          return true;
 *      }
 *      return false;
 * }
 *
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
 *
 * function hello_uninstall()
 * {
 * }
 *
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    "visible" by adding templates/template changes, language changes etc.
 *
 * function hello_activate()
 * {
 * }
 *
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially "hide" the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
 *
 * function hello_deactivate()
 * {
 * }
 */
	
function akismet_install()
{
	global $db, $mybb, $lang;
	
	if($db->field_exists('akismetstopped', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP akismetstopped");
	}
	
	// DELETE ALL SETTINGS TO AVOID DUPLICATES
	$db->write_query("DELETE FROM ".TABLE_PREFIX."settings WHERE name IN(
		'akismetswitch',
		'akismetnumtillban',
		'akismetfidsignore',
		'akismetuidsignore',
		'akismetuserstoignore'
	)");
	$db->delete_query("settinggroups", "name = 'akismet'");
	$db->delete_query("datacache", "title = 'akismet_update_check'");
	
	$query = $db->simple_select("settinggroups", "COUNT(*) as rows");
	$rows = $db->fetch_field($query, "rows");
	
	$insertarray = array(
		'name' => 'akismet',
		'title' => 'Akismet',
		'description' => 'Options on how to configure and personalize Akismet',
		'disporder' => $rows+1,
		'isdefault' => 0
	);
	$group['gid'] = $db->insert_query("settinggroups", $insertarray);
	$mybb->akismet_insert_gid = $group['gid'];
	
	$insertarray = array(
		'name' => 'akismetswitch',
		'title' => 'Akismet Main Switch',
		'description' => 'Turns on or off Akismet.',
		'optionscode' => 'onoff',
		'value' => 1,
		'disporder' => 0,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'akismetapikey',
		'title' => 'API Key to use for Akismet',
		'description' => $db->escape_string('The API Key used to connect to Akismet. Please check here for more details: <a href="http://wordpress.com/api-keys/" target="_blank">http://wordpress.com/api-keys/</a>'),
		'optionscode' => 'text',
		'value' => '',
		'disporder' => 1,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'akismetnumtillban',
		'title' => 'Spam messages until ban',
		'description' => 'The number of spam messages detected by Akismet until the user gets banned (set to 0 to disable).',
		'optionscode' => 'text',
		'value' => '3',
		'disporder' => 2,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'akismetfidsignore',
		'title' => 'Forums to Ignore',
		'description' => 'Forums, separated by a comma, to ignore. Use the forum id, <strong>not the name</strong>.',
		'optionscode' => 'text',
		'value' => '',
		'disporder' => 3,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'akismetuidsignore',
		'title' => 'Usergroups to Ignore',
		'description' => 'Usergroups, separated by a comma, to ignore. Use the usergroup id, <strong>not the name</strong>.',
		'optionscode' => 'text',
		'value' => '6,4,3',
		'disporder' => 4,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'akismetuserstoignore',
		'title' => 'Users to Ignore',
		'description' => 'Users, separated by a comma, to ignore. Use the user id, <strong>not the name</strong>.',
		'optionscode' => 'text',
		'value' => '',
		'disporder' => 6,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD akismetstopped int NOT NULL default 0");

	rebuild_settings();
}

function akismet_is_installed()
{
	global $db;
	
	if($db->field_exists('akismetstopped', "users"))
	{
		return true;
	}
	
	return false;
}

function akismet_activate()
{
	global $db, $mybb;
	
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	
	find_replace_templatesets("postbit", "#".preg_quote('{$post[\'button_spam\']}')."#i", '', 0);
	find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'button_spam\']}')."#i", '', 0);
	
	$db->delete_query("templates", "title = 'akismet_postbit_spam'");
	
	find_replace_templatesets("postbit", "#".preg_quote('{$post[\'button_edit\']}')."#i", '{$post[\'button_spam\']}{$post[\'button_edit\']}');
	find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'button_edit\']}')."#i", '{$post[\'button_spam\']}{$post[\'button_edit\']}');
	
	$insert_array = array(
		'title' => 'akismet_postbit_spam',
		'template' => $db->escape_string('<a href="{$mybb->settings[\'bburl\']}/moderation.php?action=mark_as_spam&amp;pid={$post[\'pid\']}&amp;fid={$post[\'fid\']}"><img src="{$theme[\'imglangdir\']}/postbit_spam.gif" alt="{$lang->spam}" /></a>'),
		'sid' => '-1',
		'version' => '',
		'dateline' => TIME_NOW
	);
	
	$db->insert_query("templates", $insert_array);
	
	change_admin_permission('forum', 'akismet');
}

function akismet_deactivate()
{
	global $db, $mybb;
	
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	
	find_replace_templatesets("postbit", "#".preg_quote('{$post[\'button_spam\']}')."#i", '', 0);
	find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'button_spam\']}')."#i", '', 0);
	
	$db->delete_query("templates", "title = 'akismet_postbit_spam'");
	
	change_admin_permission('forum', 'akismet', -1);
}

function akismet_uninstall()
{
	global $db;
	
	if($db->field_exists('akismetstopped', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP akismetstopped");
	}
	
	// DELETE ALL SETTINGS TO AVOID DUPLICATES
	$db->write_query("DELETE FROM ".TABLE_PREFIX."settings WHERE name IN(
		'akismetswitch',
		'akismetapikey',
		'akismetnumtillban',
		'akismetfidsignore',
		'akismetuidsignore',
		'akismetuserstoignore'
	)");
	$db->delete_query("settinggroups", "name = 'akismet'");
	$db->delete_query("datacache", "title = 'akismet_update_check'");
	rebuild_settings();
}

function akismet_key()
{
	global $installed, $mybb;
	
	if($installed == false && $mybb->input['plugin'] == "akismet")
	{
		global $message;
	
		flash_message($message, 'success');
		admin_redirect("index.php?module=config-settings&action=change&gid=".intval($mybb->akismet_insert_gid)."#row_setting_akismetapikey");
	}
}


function akismet_show_confirm_page()
{
	global $mybb, $lang, $theme, $pid, $fid, $db, $headerinclude, $header, $footer;
	
	$pid = intval($pid);
	$fid = intval($fid);
	
	$query = $db->simple_select("posts", "subject", "pid='{$pid}'", 1);
	$post = $db->fetch_array($query);
	$post['subject'] = htmlspecialchars_uni($post['subject']);
	
	if(!$post)
	{
		error("Invalid Post ID.");
	}
	
	output_page("<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->mark_as_spam}</title>
{$headerinclude}
</head>
<body>
{$header}
<form action=\"moderation.php\" method=\"post\">
<input type=\"hidden\" name=\"my_post_key\" value=\"{$mybb->post_code}\" />
<table border=\"0\" cellspacing=\"{$theme['borderwidth']}\" cellpadding=\"{$theme['tablespace']}\" class=\"tborder\">
<tr>
<td class=\"thead\" colspan=\"2\"><strong>{$post['subject']} - {$lang->mark_as_spam}</strong></td>
</tr>
<tr>
<td class=\"trow1\" colspan=\"2\" align=\"center\">{$lang->confirm_mark_as_spam}</td>
</tr>
{$loginbox}
</table>
<br />
<div align=\"center\"><input type=\"submit\" class=\"button\" name=\"submit\" value=\"{$lang->mark_as_spam}\" /></div>
<input type=\"hidden\" name=\"action\" value=\"mark_as_spam\" />
<input type=\"hidden\" name=\"pid\" value=\"{$pid}\" />
<input type=\"hidden\" name=\"fid\" value=\"{$fid}\" />
</form>
{$footer}
</body>
</html>");
	exit;
}

function akismet_moderation_start()
{
	global $mybb, $db, $akismet, $lang, $cache, $fid, $pid;
	
	if(!$mybb->settings['akismetswitch'] || $mybb->input['action'] != 'mark_as_spam')
	{
		return;
	}
	
	$lang->load("akismet", false, true);
	
	if(!$mybb->input['pid'])
	{
		error("No Post ID specified.");
	}
	
	$pid = intval($mybb->input['pid']);
	
	if(!$mybb->input['fid'])
	{
		error("No Forum ID specified.");
	}
	
	$fid = intval($mybb->input['fid']);
	
	if(!is_moderator($fid))
	{
		error("No Permissions to do this action.");
	}
	
	$query = $db->query("
		SELECT p.uid, p.username, u.email, u.website, u.akismetstopped, p.message, p.ipaddress, p.tid, p.replyto, p.fid, f.usepostcounts
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid)
		WHERE p.pid = '{$pid}'
	");
	$post = $db->fetch_array($query);
	
	if(!$post)
	{
		error("Invalid Post ID.");
	}
	
	if(!$mybb->input['my_post_key'] || $mybb->request_method != "post")
	{
		akismet_show_confirm_page();
	}
	
	verify_post_check($mybb->input['my_post_key']);
	
	$akismet_array = array(
		'type' => 'post',
		'username' => $post['username'],
		'email' => $post['email'],
		'website' => $post['website'],
		'message' => $post['message'],
		'user_ip' => $post['ipaddress']
	);
	
	if($post['replyto'] == 0)
	{
		$db->update_query("threads", array('visible' => '-4'), "tid = '{$post['tid']}'");
		$db->update_query("posts", array('visible' => '-4'), "tid = '{$post['tid']}'");
		$snippit = "thread";
	}
	else
	{
		$db->update_query("posts", array('visible' => '-4'), "pid = '{$pid}'");
		$snippit = "post";
	}
	
	if(!$akismet)
	{
		$akismet = new Akismet($mybb->settings['bburl'], $mybb->settings['akismetapikey'],  $akismet_array);
	}
	
	$akismet->submit_spam();

	$numakismetthread = $numakismetpost = 0;

	if($snippit == "thread")
	{
		$query = $db->query("
			SELECT p.uid, u.usergroup
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE p.tid = '{$post['tid']}'
		");
		while($post2 = $db->fetch_array($query))
		{
			++$numakismetpost;

			if($post['usepostcounts'] != 0)
			{
				$db->write_query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum-1 WHERE uid = '{$post2['uid']}'");
			}

			if($mybb->settings['akismetuidsignore'])
			{
				$akismet_uids_ignore = explode(',', $mybb->settings['akismetuidsignore']);
				if(in_array($post2['usergroup'], $akismet_uids_ignore) || is_super_admin($post2['uid']))
				{
					continue;
				}
			}
			
			if(is_super_admin($post2['uid']))
			{
				continue;
			}
			
			$db->write_query("UPDATE ".TABLE_PREFIX."users SET akismetstopped=akismetstopped+1 WHERE uid = '{$post2['uid']}'");
			$query1 = $db->simple_select("users", "akismetstopped", "uid = '{$post2['uid']}'");
			$akismetstopped = $db->fetch_field($query1, 'akismetstopped');
			
			// Check if the person should be banned
			if($mybb->settings['akismetnumtillban'] > 0 && $akismetstopped >= $mybb->settings['akismetnumtillban'])
			{
				$banned_user = array(
					"uid" => $post2['uid'],
					"admin" => 0,
					"gid" => 7,
					"oldgroup" => $post2['usergroup'],
					"dateline" => TIME_NOW,
					"bantime" => 'perm',
					"lifted" => 'perm',
					"reason" => "Automatically banned by the Akismet system for spamming.",
					"oldadditionalgroups" => ''
				);
				$db->insert_query("banned", $banned_user);
				
				$db->update_query("users", array('usergroup' => 7), "uid = '{$post2['uid']}'");
				
				$cache->update_moderators();
			}
		}
		
		++$numakismetthread;
	}
	else
	{
		$db->write_query("UPDATE ".TABLE_PREFIX."users SET akismetstopped=akismetstopped+1 WHERE uid = '{$post['uid']}'");
		$query = $db->simple_select("users", "akismetstopped, usergroup", "uid = '{$post['uid']}'");
		$akismetstopped = $db->fetch_field($query, 'akismetstopped');
		$usergroup = $db->fetch_field($query, 'usergroup');
		
		if($mybb->settings['akismetuidsignore'])
		{
			$akismet_uids_ignore = explode(',', $mybb->settings['akismetuidsignore']);
			if(in_array($usergroup, $akismet_uids_ignore))
			{
				continue;
			}
		}
		
		if(is_super_admin($post['uid']))
		{
			continue;
		}
		
		// Check if the person should be banned
		if($mybb->settings['akismetnumtillban'] > 0 && $akismetstopped >= $mybb->settings['akismetnumtillban'])
		{
			$banned_user = array(
				"uid" => $post['uid'],
				"admin" => 0,
				"gid" => 7,
				"oldgroup" => $usergroup,
				"dateline" => TIME_NOW,
				"bantime" => 'perm',
				"lifted" => 'perm',
				"reason" => "Automatically banned by the Akismet system for spamming.",
				"oldadditionalgroups" => ''
			);
			$db->insert_query("banned", $banned_user);
			
			$db->update_query("users", array('usergroup' => 7), "uid = '{$post['uid']}'");
			
			$cache->update_moderators();
		}
		
		++$numakismetpost;
		
		if($post['usepostcounts'] != 0)
		{
			$db->write_query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum-1 WHERE uid = '{$post['uid']}'");
		}
	}
	
	update_thread_counters($post['tid'], array('replies' => '-'.$numakismetpost));
	update_forum_counters($post['fid'], array('threads' => '-'.$numakismetthread, 'posts' => '-'.$numakismetpost));
	
	if($snippit == "thread")
	{
		redirect("./forumdisplay.php?fid={$post['fid']}", $lang->thread_spam_success);
	}
	else
	{
		redirect("./showthread.php?tid={$post['tid']}", $lang->post_spam_success);
	}
}

function akismet_postbit(&$post)
{
	global $templates, $mybb, $theme, $lang;
	
	if(!$mybb->settings['akismetswitch'] || !is_moderator($post['fid']))
	{
		return;
	}
	
	if($mybb->settings['akismetuidsignore'])
	{
		$akismet_uids_ignore = explode(',', $mybb->settings['akismetuidsignore']);
		if(in_array($usergroup, $akismet_uids_ignore))
		{
			return;
		}
	}
	
	if(is_super_admin($post['uid']))
	{
		return;
	}
	
	$lang->load("akismet", false, true);
	
	eval("\$post['button_spam'] = \"".$templates->get("akismet_postbit_spam")."\";");
}

function akismet_verify(&$post)
{
	global $mybb, $isspam, $akismet;
	
	if($isspam == true && $mybb->settings['akismetswitch'] == 1)
	{
		if(isset($post->thread_insert_data))
		{
			$post->thread_insert_data['visible'] = '-4';
		}
		
		$post->post_insert_data['visible'] = '-4';
	}
}

function akismet_fake_draft(&$post)
{
	global $mybb, $isspam, $akismet, $cache;
	
	$exclude_array = explode(',', $mybb->settings['akismetuserstoignore']);
	
	if(!$mybb->settings['akismetswitch'] || in_array($mybb->user['uid'], $exclude_array) || is_super_admin($mybb->user['uid']))
	{
		return;
	}
	
	if($mybb->settings['akismetfidsignore'])
	{
		$akismet_fids_ignore = explode(',', $mybb->settings['akismetfidsignore']);
		if(in_array($post->data['fid'], $akismet_fids_ignore))
		{
			return;
		}
	}
	
	if($mybb->settings['akismetuidsignore'])
	{
		$akismet_uids_ignore = explode(',', $mybb->settings['akismetuidsignore']);
		if(in_array($mybb->user['usergroup'], $akismet_uids_ignore))
		{
			return;
		}
	}
	
	$akismet_array = array(
		'type' => 'post',
		'username' => $post->data['username'],
		'email' => $mybb->user['email'],
		'website' => $mybb->user['website'],
		'message' => $post->data['message'],
		'user_ip' => $mybb->user['ipaddress']
	);
	
	if(!$akismet)
	{
		$akismet = new Akismet($mybb->settings['bburl'], $mybb->settings['akismetapikey'],  $akismet_array);
	}
	
	if($akismet->check())
	{
		global $db;
		
		$isspam = true;
		
		// Update our spam count attempts
		++$mybb->user['akismetstopped'];
		$db->update_query("users", array('akismetstopped' => $mybb->user['akismetstopped']), "uid = '{$mybb->user['uid']}'");
		
		// Check if the person should be banned
		if($mybb->settings['akismetnumtillban'] > 0 && $mybb->user['akismetstopped'] >= $mybb->settings['akismetnumtillban'])
		{
			$banned_user = array(
				"uid" => $mybb->user['uid'],
				"admin" => 0,
				"gid" => 7,
				"oldgroup" => $mybb->user['usergroup'],
				"dateline" => TIME_NOW,
				"bantime" => 'perm',
				"lifted" => 'perm',
				"reason" => "Automatically banned by the Akismet system for spamming.",
				"oldadditionalgroups" => ''
			);
			$db->insert_query("banned", $banned_user);
			
			$db->update_query("users", array('usergroup' => 7), "uid = '{$mybb->user['uid']}'");
			
			$cache->update_moderators();
			
			// We better do this..otherwise they have dodgy permissions
			$mybb->user['banoldgroup'] = $mybb->user['usergroup'];
			$mybb->user['usergroup'] = 7;
			
			global $mybbgroups;
			
			$mybbgroups = $mybb->user['usergroup'];
			if($mybb->user['additionalgroups'])
			{
				$mybbgroups .= ','.$mybb->user['additionalgroups'];
			}
		}
		
		// Fake visibility
		// Essentially because you can't modify the $visible variable we need to trick it
		// into thinking its saving a draft so it won't modify the users lastpost and postcount
		// In akismet_verify, its set back to -4 so we can still uniquely verify that this is a spam message
		// before it's inserted into the database.
		$post->data['savedraft'] = 1;
	}
}

function akismet_redirect_thread()
{
	global $isspam, $url, $lang, $thread, $mybb;
	
	if($isspam && $mybb->settings['akismetswitch'] == 1)
	{
		$lang->load("akismet", false, true);
		
		$url = get_thread_link($thread['tid']);
		$url2 = get_forum_link($thread['fid']);
		
		error("<div align=\"center\">".$lang->redirect_newreply."<br /><br />".$lang->sprintf($lang->redirect_return_forum, $url, $url2)."</div>", $lang->akismet_error);
	}
}

function akismet_redirect_forum()
{
	global $isspam, $url, $lang, $fid, $mybb;
	
	if($isspam && $mybb->settings['akismetswitch'] == 1)
	{
		$lang->load("akismet", false, true);
		
		$url = get_forum_link($fid);
		
		error("<div align=\"center\">".$lang->redirect_newthread."<br /><br />".$lang->sprintf($lang->redirect_return_forum, $url)."</div>", $lang->akismet_error);
	}
}

function akismet_action_handler(&$action)
{
	$action['akismet'] = array('active' => 'akismet', 'file' => '');
}

function akismet_admin_nav(&$sub_menu)
{
	global $mybb, $lang;
	
	if($mybb->settings['akismetswitch'] == 1)
	{
		$lang->load("forum_akismet", false, true);
		
		end($sub_menu);
		$key = (key($sub_menu))+10;
		
		if(!$key)
		{
			$key = '50';
		}
		
		$sub_menu[$key] = array('id' => 'akismet', 'title' => $lang->akismet, 'link' => "index.php?module=forum-akismet");
	}
}

function akismet_admin_permissions(&$admin_permissions)
{
	global $db, $mybb;
	
	if($mybb->settings['akismetswitch'] == 1)
	{
		global $lang;
		
		$lang->load("forum_akismet", false, true);
		
		$admin_permissions['akismet'] = $lang->can_manage_akismet;
	}
}

function akismet_admin()
{
	global $mybb, $db, $page, $lang;
	
	if($page->active_action != "akismet")
	{
		return;
	}
	
	$page->add_breadcrumb_item($lang->akismet);
	
	if($mybb->input['delete_all'] && $mybb->request_method == "post")
	{
		// User clicked no
		if($mybb->input['no'])
		{
			admin_redirect("index.php?module=forum-akismet");
		}
		
		if($mybb->request_method == "post")
		{
			// Delete the template
			$db->delete_query("posts", "visible = '-4'");
			
			// Log admin action
			log_admin_action();
			
			flash_message($lang->success_deleted_spam, 'success');
			admin_redirect("index.php?module=forum-akismet");
		}
		else
		{
			$page->output_confirm_action("index.php?module=forum-akismet&amp;delete_all=1", $lang->confirm_spam_deletion);
		}
	}
	
	if($mybb->input['unmark'] && $mybb->request_method == "post")
	{
		$unmark = $mybb->input['akismet'];
		
		if(empty($unmark))
		{
			flash_message($lang->error_unmark, 'error');
			admin_redirect("index.php?module=forum-akismet");
		}
		
		$posts_in = '';
		$comma = '';
		foreach($unmark as $key => $val)
		{
			$posts_in .= $comma.intval($key);
			$comma = ',';
		}
		
		$query = $db->simple_select("posts", "pid, tid", "pid IN ({$posts_in}) AND replyto = '0'");
		while($post = $db->fetch_array($query))
		{
			$threadp[] = $post['tid'];
		}
		
		if(!is_array($threadp))
		{
			$threadp = array();
		}
		
		$thread_list = implode(',', $threadp);
		
		$query = $db->query("
			SELECT p.tid, f.usepostcounts, p.uid, p.fid, p.dateline, p.replyto, t.lastpost, t.lastposter, t.lastposteruid, t.subject
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=p.fid)
			WHERE p.pid IN ({$posts_in}) AND p.visible = '-4'
		");
		while($post = $db->fetch_array($query))
		{
			// Fetch the last post for this forum
			$query2 = $db->query("
				SELECT tid, lastpost, lastposter, lastposteruid, subject
				FROM ".TABLE_PREFIX."threads
				WHERE fid='{$post['fid']}' AND visible='1' AND closed NOT LIKE 'moved|%'
				ORDER BY lastpost DESC
				LIMIT 0, 1
			");
			$lastpost = $db->fetch_array($query2);
			
			if($post['lastpost'] > $lastpost['lastpost'])
			{
				$lastpost['lastpost'] = $post['lastpost'];
				$lastpost['lastposter'] = $post['lastposter'];
				$lastpost['lastposteruid'] = $post['lastposteruid'];
				$lastpost['subject'] = $post['subject'];
				$lastpost['tid'] = $post['tid'];
			}
			
			$update_count = array(
				"lastpost" => intval($lastpost['lastpost']),
				"lastposter" => $db->escape_string($lastpost['lastposter']),
				"lastposteruid" => intval($lastpost['lastposteruid']),
				"lastposttid" => intval($lastpost['tid']),
				"lastpostsubject" => $db->escape_string($lastpost['subject'])
			);
			
			$db->update_query("forums", $update_count, "fid='{$post['fid']}'");
			
			$query2 = $db->query("
				SELECT u.uid, u.username, p.username AS postusername, p.dateline
				FROM ".TABLE_PREFIX."posts p
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
				WHERE p.tid='{$post['tid']}' AND p.visible='1' OR p.pid = '{$post['pid']}'
				ORDER BY p.dateline DESC
				LIMIT 1"
			);
			$lastpost = $db->fetch_array($query2);
			
			$query2 = $db->query("
				SELECT u.uid, u.username, p.username AS postusername, p.dateline
				FROM ".TABLE_PREFIX."posts p
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
				WHERE p.tid='{$post['tid']}'
				ORDER BY p.dateline ASC
				LIMIT 0,1
			");
			$firstpost = $db->fetch_array($query2);
			
			if(!$firstpost['username'])
			{
				$firstpost['username'] = $firstpost['postusername'];
			}
			if(!$lastpost['username'])
			{
				$lastpost['username'] = $lastpost['postusername'];
			}
			
			if(!$lastpost['dateline'])
			{
				$lastpost['username'] = $firstpost['username'];
				$lastpost['uid'] = $firstpost['uid'];
				$lastpost['dateline'] = $firstpost['dateline'];
			}
			
			$lastpost['username'] = $db->escape_string($lastpost['username']);
			$firstpost['username'] = $db->escape_string($firstpost['username']);
			
			$query2 = $db->simple_select("users", "akismetstopped", "uid='{$post['uid']}'");
			$akismetstopped = $db->fetch_field($query2, "akismetstopped")-1;
			
			if($akismetstopped < 0)
			{
				$akismetstopped = 0;
			}
			$db->update_query("users", array('akismetstopped' => $akismetstopped), "uid='{$post['uid']}'");
			
			$update_array = array(
				'username' => $firstpost['username'],
				'uid' => intval($firstpost['uid']),
				'lastpost' => intval($lastpost['dateline']),
				'lastposter' => $lastpost['username'],
				'lastposteruid' => intval($lastpost['uid']),
			);
			$db->update_query("threads", $update_array, "tid='{$post['tid']}'");
			
			if($post['usepostcounts'] != 0)
			{
				$db->write_query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum+1 WHERE uid = '{$post['uid']}'");
			}
			
			$newthreads = $newreplies = 0;
			
			if($post['replyto'] == 0)
			{
				++$newthreads;
			}
			else
			{
				++$newreplies;
			}
			
			update_thread_counters($post['tid'], array('replies' => '+'.$newreplies));
			update_forum_counters($post['fid'], array('threads' => '+'.$newthreads, 'posts' => '+1'));
		}
		
		$approve = array(
			"visible" => 1,
		);
		
		if($thread_list)
		{
			$db->update_query("threads", $approve, "tid IN ({$thread_list})");
		}
		
		$db->update_query("posts", $approve, "pid IN ({$posts_in})");
		
		// Log admin action
		log_admin_action();
		
		flash_message($lang->success_unmarked, 'success');
		admin_redirect("index.php?module=forum-akismet");
	}
	
	if($mybb->input['delete'] && $mybb->request_method == "post")
	{
		$deletepost = $mybb->input['akismet'];
		
		if(empty($deletepost))
		{
			flash_message($lang->error_deletepost, 'error');
			admin_redirect("index.php?module=forum-akismet");
		}
		
		$posts_in = '';
		$comma = '';
		foreach($deletepost as $key => $val)
		{
			$posts_in .= $comma.intval($key);
			$comma = ',';
		}
		
		$query = $db->simple_select("posts", "pid, tid", "pid IN ({$posts_in}) AND replyto = '0'");
		while($post = $db->fetch_array($query))
		{
			$threadp[$post['pid']] = $post['tid'];
		}
		
		if(!is_array($threadp))
		{
			$threadp = array();
		}
		
		require_once MYBB_ROOT."inc/functions_upload.php";
		
		foreach($deletepost as $pid => $val)
		{
			if(array_key_exists($pid, $threadp))
			{
				$db->delete_query("posts", "pid IN ({$posts_in})");
				$db->delete_query("attachments", "pid IN ({$posts_in})");
				
				// Get thread info
				$query = $db->simple_select("threads", "poll", "tid='".$threadp[$pid]."'");
				$poll = $db->fetch_field($query, 'poll');
				
				// Delete threads, redirects, favorites, polls, and poll votes
				$db->delete_query("threads", "tid='".$threadp[$pid]."'");
				$db->delete_query("threads", "closed='moved|".$threadp[$pid]."'");
				$db->delete_query("threadsubscriptions", "tid='".$threadp[$pid]."'");
				$db->delete_query("polls", "tid='".$threadp[$pid]."'");
				$db->delete_query("pollvotes", "pid='{$poll}'");
			}
			
			// Remove attachments
			remove_attachments($pid);
			
			// Delete the post
			$db->delete_query("posts", "pid='{$pid}'");
		}
		
		// Log admin action
		log_admin_action();
		
		flash_message($lang->success_spam_deleted, 'success');
		admin_redirect("index.php?module=forum-akismet");
	}
	
	if(!$mybb->input['action'])
	{
		require MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;
		
		$page->output_header($lang->akismet);
		
		$form = new Form("index.php?module=forum-akismet", "post");
		
		$table = new Table;
		$table->construct_header($form->generate_check_box("checkall", 1, '', array('class' => 'checkall')), array('width' => '5%'));
		$table->construct_header("Title / Username / Post", array('class' => 'align_center'));
		
		$mybb->input['page'] = intval($mybb->input['page']);
		
		if($mybb->input['page'] > 0)
		{
			$start = $mybb->input['page'] * 20;
		}
		else
		{
			$start = 0;
		}
		
		$query = $db->simple_select("posts", "COUNT(pid) as spam", "visible = '-4'");
		$total_rows = $db->fetch_field($query, 'spam');
		
		if($start > $total_rows)
		{
			$start = $total_rows - 20;
		}
		
		if($start < 0)
		{
			$start = 0;
		}
		
		$query = $db->simple_select("posts", "*", "visible = '-4'", array('limit_start' => $start, 'limit' => '20', 'order_by' => 'dateline', 'order_dir' => 'desc'));
		while($post = $db->fetch_array($query))
		{
			if($post['uid'] != 0)
			{
				$username = "<a href=\"../".str_replace("{uid}", $post['uid'], PROFILE_URL)."\" target=\"_blank\">".format_name($post['username'], $post['usergroup'], $post['displaygroup'])."</a>";
			}
			else
			{
				$username = $post['username'];
			}
			
			$table->construct_cell($form->generate_check_box("akismet[{$post['pid']}]", 1, ''));
			$table->construct_cell("<span style=\"float: right;\">{$lang->username}: {$username}</span> <span style=\"float: left;\">{$lang->title}: ".htmlspecialchars_uni($post['subject'])." <strong>(".my_date($mybb->settings['dateformat'], $post['dateline']).", ".my_date($mybb->settings['timeformat'], $post['dateline']).")</strong></span>");
			$table->construct_row();
			
			$parser_options = array(
				"allow_html" => 0,
				"allow_mycode" => 0,
				"allow_smilies" => 0,
				"allow_imgcode" => 0,
				"me_username" => $post['username'],
				"filter_badwords" => 1
			);
			$post['message'] = $parser->parse_message($post['message'], $parser_options);
			
			$table->construct_cell($post['message'], array("colspan" => 2));
			$table->construct_row();
		}
		
		$num_rows = $table->num_rows();
		
		if($num_rows == 0)
		{
			$table->construct_cell($lang->no_spam_found, array("class" => "align_center", "colspan" => 2));
			$table->construct_row();
		}
		
		$table->output($lang->detected_spam_messages);
		
		echo "<br />".draw_admin_pagination($mybb->input['page'], 20, $total_rows, "index.php?module=forum-akismet&amp;page={page}");
		
		$buttons[] = $form->generate_submit_button($lang->unmark_selected, array('name' => 'unmark'));
		$buttons[] = $form->generate_submit_button($lang->deleted_selected, array('name' => 'delete'));
		
		if($num_rows > 0)
		{
			$buttons[] = $form->generate_submit_button($lang->delete_all, array('name' => 'delete_all', 'onclick' => "return confirm('{$lang->confirm_spam_deletion}');"));
		}
		
		$form->output_submit_wrapper($buttons);
		
		$form->end();
		
		$page->output_footer();
	}
	
	exit;
}

/**
 * This class is Copyright 2009 Ryan Gordon (Tikitiki)
 * Built to communicate with the akismet server
 */

class Akismet {

	/**
	 * The array of required server keys when building a query string
	 *
	 * @var array
	 */
	var $required = array(
		'HTTP_REFERRER',
		'HTTP_ACCEPT_CHARSET',
		'SERVERNAME',
		'SERVER_ADDR',
		'REMOTE_ADDR',
		'HTTP_USER_AGENT'
	);
	
	/**
	 * The array of a post to validate against Akismet.
	 *
	 * @var array
	 */
	var $post = array();
	
	/**
	 * The port to use to connect to the Akismet servers
	 *
	 * @var integer
	 */
	var $port = 80;

	/**
	 * The address to use to connect to the Akismet servers
	 *
	 * @var string
	 */
	var $host = "rest.akismet.com";

	/**
	 * The version of Akismet being used
	 *
	 * @var integer
	 */
	var $version = "1.1";

	/**
	 * The API key used to validate your use of Akismet
	 *
	 * @var string
	 */
	var $key = false;

	/**
	 * The main page of your forum
	 *
	 * @var string
	 */
	var $site = false;

	/**
	 * The object of the Akismet connection
	 *
	 * @var object
	 */
	var $connection;
	
	/**
	 * Errors (uh oh)
	 *
	 * @var array
	 */
	var $errors = array();
	
	/**
	 * Initilize the Akismet class
	 *
	 * @param string The board url.
	 * @param int The API Key used to validate your use of Akismet.
	 * @param array Array of the post that is going to be validated by Akismet.
	 */
	function Akismet($url, $api_key, $post)
	{
		// Set option stuff
		$this->url = $url;
		$this->api_key = $api_key;
		
		$this->post = $post;
		
		$this->format_post();
		
		if(!isset($this->post['user_ip']))
		{
			if($_SERVER['REMOTE_ADDR'] != getenv('SERVER_ADDR'))
			{
				$this->post['user_ip'] = $_SERVER['REMOTE_ADDR'];
			}
			else
			{
				$this->post['user_ip'] = getenv('HTTP_X_FORWARDED_FOR');
			}
		}
		
		if(!isset($this->post['permalink']))
		{
			$this->post['permalink'] = $_SERVER['HTTP_REFERER'];
		}
		
		if(!isset($this->post['user_agent']))
		{
			$this->post['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
		}
		
		if(!isset($this->post['referrer']))
		{
			$this->post['referrer'] = $_SERVER['HTTP_REFERER'];
		}
		
		$this->post['blog'] = $url;
		
		// Check if the API key is valid
		if(!$this->validate_api_key())
		{
			$this->set_error("invalid_key");
		}
	}
	
	/**
	 * Checks a post against the Akismet server
	 *
	 * @return boolean True if the comment passed spam validation.
	 */
	function check()
	{
		if($this->fetch_response($this->build_query_string(), 'comment-check') == "true")
		{
			// We have spam!
			return true;
		}
		
		// Good! The check failed; We're all good to go!
		return false;
	}
	
	/**
	 * Submits a spam post to the Akismet server
	 *
	 */
	function submit_spam()
	{
		$this->fetch_response($this->build_query_string(), 'submit-spam');
	}
	
	/**
	 * Submits a ham post to the Akismet server
	 *
	 */
	function submit_ham()
	{
		$this->fetch_response($this->build_query_string(), 'submit-ham');
	}
	
	/**
	 * Validate a API Key against the Akismet server
	 *
	 * @return boolean True if the API Key passed.
	 */
	function validate_api_key()
	{
		if($this->fetch_response("key=".$this->api_key."&blog=".urlencode($this->url), 'verify-key') == "valid")
		{
			return true;
		}
		
		return false;
	}
	
	/**
	 * Formats the comment array to the Akismet API Standards
	 *
	 */
	function format_post()
	{
		$format = array(
			'type' => 'comment_type',
			'username' => 'comment_author',
			'email' => 'comment_author_email',
			'website' => 'comment_author_url',
			'message' => 'comment_content',
		);
		
		// Basically we're assigning $long to the comment array if $short in the comment array, is not null
		foreach($format as $short => $long)
		{
			if(isset($this->post[$short]))
			{
				$this->post[$long] = $this->post[$short];
				unset($this->post[$short]);
			}
		}
	}
	
	/**
	 * Builds a query string to work with Akismet
	 *
	 * @return string The built query string
	 */
	function build_query_string()
	{
		foreach($_SERVER as $key => $value)
		{
			if(in_array($key, $this->required))
			{
				if($key == 'REMOTE_ADDR')
				{
					$this->post[$key] = $this->post['user_ip'];
				}
				else
				{
					$this->post[$key] = $value;
				}
			}
		}
		
		
		$query_string = '';
		foreach($this->post as $key => $data)
		{
			$query_string .= $key.'='.urlencode(stripslashes($data)).'&';
		}
		
		return $query_string;
	}
	
	/**
	 * Connects to the Akismet server
	 *
	 * @return boolean True on success.
	 */
	function connect()
	{
		$this->connection = @fsockopen($this->host, 80);
		if(!$this->connection)
		{
			$this->set_error("server_not_found");
			return false;
		}
		
		return true;
	}
	
	/**
	 * Sends a request to the Akismet server
	 *
	 * @param string The request uri.
	 * @param string The path to what is being checked (e.x. 'comment-spam').
	 * @return mixed The response on success, false otherwise.
	 */
	function fetch_response($request, $path)
	{
		$this->connect();
		
		if($this->connection == true && !$this->errors['server_not_found'])
		{
			if(!empty($this->api_key))
			{
				$api_key = $this->api_key.".";
			}
			else
			{
				$api_key = "";
			}
			
			$http_request = "POST /{$this->version}/{$path} HTTP/1.1\r\n";
			$http_request .= "Host: {$api_key}{$this->host}\r\n";
			$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n";
			$http_request .= "Content-Length: ".strlen($request)."\r\n";
			$http_request .= "User-Agent: MyBB/1.6 | Akismet/1.1\r\n";
			$http_request .= "Connection: close\r\n";
			$http_request .= "\r\n";
			$http_request .= $request;
			
			@fwrite($this->connection, $http_request);
			
			$http_response = "";
			while(feof($this->connection) === false)
			{
				$http_response .= @fgets($this->connection, 1160);
			}
			
			$http_response = explode("\r\n\r\n", $http_response, 2);
			return $http_response[1];
		}
		else
		{
			$this->set_error("response_failed");
			return false;
		}
		
		$this->disconnect();
	}
	
	/**
	 * Disconnects from the Akismet server
	 *
	 */
	function disconnect()
	{
		@fclose($this->connection);
	}
	
	/**
	 * Append an error onto the error array
	 *
	 * @param string The error message.
	 * @param int The error code of the error.
	 * @return boolean Always true.
	 */
	function set_error($error_code)
	{
		switch($error_code)
		{
			case "server_not_found":
				$message = "Could not connect to Akismet server.";
				break;
			case "response_failed":
				$message = "There was a problem retrieving the response.";
				break;
			case "invalid_key":
				$message = "Your Akismet API key does not appear to be valid.";
				break;
			default:
				$message = "Unkown error.";
				break;
		}
		$this->errors[$error_code] = $message;
		
		return true;
	}
	
	/**
	 * Check if a specified error exists with the error array
	 *
	 * @param int The error code
	 * @return boolean True if error exists.
	 */
	function error_exists($error_code)
	{
		if(isset($this->errors[$error_code]))
		{
			return true;
		}
		
		return false;
	}
	
	/**
	 * Checks if there are any errors stored in the error array
	 *
	 * @return boolean True if there errors.
	 */
	function errors_exist()
	{
		if(count($this->errors) > 0)
		{
			return true;
		}
		
		return false;
	}
}

?>