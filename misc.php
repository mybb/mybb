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
define('THIS_SCRIPT', 'misc.php');

$templatelist = "misc_rules_forum,misc_help_helpdoc,misc_whoposted_poster,misc_whoposted,misc_smilies_popup_smilie,misc_smilies_popup,misc_smilies_popup_empty,misc_syndication_feedurl,misc_syndication";
$templatelist .= ",misc_buddypopup,misc_buddypopup_user,misc_buddypopup_user_none,misc_buddypopup_user_online,misc_buddypopup_user_offline,misc_buddypopup_user_sendpm";
$templatelist .= ",misc_smilies,misc_smilies_smilie,misc_help_section_bit,misc_help_section,misc_help,forumdisplay_password_wrongpass,forumdisplay_password";
require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";

// Load global language phrases
$lang->load("misc");

$plugins->run_hooks("misc_start");

$mybb->input['action'] = $mybb->get_input('action');
if($mybb->input['action'] == "dstswitch" && $mybb->request_method == "post" && $mybb->user['uid'] > 0)
{
	if($mybb->user['dstcorrection'] == 2)
	{
		if($mybb->user['dst'] == 1)
		{
			$update_array = array("dst" => 0);
		}
		else
		{
			$update_array = array("dst" => 1);
		}
	}
	$db->update_query("users", $update_array, "uid='{$mybb->user['uid']}'");
	if(!isset($mybb->input['ajax']))
	{
		redirect("index.php", $lang->dst_settings_updated);
	}
	else
	{
		echo "done";
		exit;
	}
}
elseif($mybb->input['action'] == "markread")
{
	if($mybb->user['uid'] && verify_post_check($mybb->get_input('my_post_key'), true) !== true)
	{
		// Protect our user's unread forums from CSRF
		error($lang->invalid_post_code);
	}

	if(isset($mybb->input['fid']))
	{
		$validforum = get_forum($mybb->input['fid']);
		if(!$validforum)
		{
			if(!isset($mybb->input['ajax']))
			{
				error($lang->error_invalidforum);
			}
			else
			{
				echo 0;
				exit;
			}
		}

		require_once MYBB_ROOT."/inc/functions_indicators.php";
		mark_forum_read($mybb->input['fid']);

		$plugins->run_hooks("misc_markread_forum");

		if(!isset($mybb->input['ajax']))
		{
			redirect(get_forum_link($mybb->input['fid']), $lang->redirect_markforumread);
		}
		else
		{
			echo 1;
			exit;
		}
	}
	else
	{

		$plugins->run_hooks("misc_markread_end");
		require_once MYBB_ROOT."/inc/functions_indicators.php";
		mark_all_forums_read();
		redirect("index.php", $lang->redirect_markforumsread);
	}
}
elseif($mybb->input['action'] == "clearpass")
{
	$plugins->run_hooks("misc_clearpass");

	if(isset($mybb->input['fid']))
	{
		if(!verify_post_check($mybb->get_input('my_post_key')))
		{
			error($lang->invalid_post_code);
		}

		my_unsetcookie("forumpass[".$mybb->input['fid']."]");
		redirect("index.php", $lang->redirect_forumpasscleared);
	}
}
elseif($mybb->input['action'] == "rules")
{
	if(isset($mybb->input['fid']))
	{
		$plugins->run_hooks("misc_rules_start");

		$fid = $mybb->input['fid'];

		$forum = get_forum($fid);
		if(!$forum || $forum['type'] != "f" || $forum['rules'] == '')
		{
			error($lang->error_invalidforum);
		}

		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] != 1)
		{
			error_no_permission();
		}

		if(!$forum['rulestitle'])
		{
			$forum['rulestitle'] = $lang->sprintf($lang->forum_rules, $forum['name']);
		}

		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser();
		$parser_options = array(
			"allow_html" => 1,
			"allow_mycode" => 1,
			"allow_smilies" => 1,
			"allow_imgcode" => 1,
			"filter_badwords" => 1
		);

		$forum['rules'] = $parser->parse_message($forum['rules'], $parser_options);

		// Make navigation
		build_forum_breadcrumb($mybb->input['fid']);
		add_breadcrumb($forum['rulestitle']);

		$plugins->run_hooks("misc_rules_end");

		eval("\$rules = \"".$templates->get("misc_rules_forum")."\";");
		output_page($rules);
	}

}
elseif($mybb->input['action'] == "help")
{
	$lang->load("helpdocs");
	$lang->load("helpsections");
	$lang->load("customhelpdocs");
	$lang->load("customhelpsections");

	$hid = $mybb->get_input('hid', 1);
	add_breadcrumb($lang->nav_helpdocs, "misc.php?action=help");

	if($hid)
	{
		$query = $db->query("
			SELECT h.*, s.enabled AS section
			FROM ".TABLE_PREFIX."helpdocs h
			LEFT JOIN ".TABLE_PREFIX."helpsections s ON (s.sid=h.sid)
			WHERE h.hid='{$hid}'
		");

		$helpdoc = $db->fetch_array($query);
		if($helpdoc['section'] != 0 && $helpdoc['enabled'] != 0)
		{
			$plugins->run_hooks("misc_help_helpdoc_start");

			if($helpdoc['usetranslation'] == 1)
			{
				$langnamevar = "d".$helpdoc['hid']."_name";
				$langdescvar = "d".$helpdoc['hid']."_desc";
				$langdocvar = "d".$helpdoc['hid']."_document";
				if($lang->$langnamevar)
				{
					$helpdoc['name'] = $lang->$langnamevar;
				}
				if($lang->$langdescvar)
				{
					$helpdoc['description'] = $lang->$langdescvar;
				}
				if($lang->$langdocvar)
				{
					$helpdoc['document'] = $lang->$langdocvar;

					if($langdocvar == "d3_document")
					{
						$helpdoc['document'] = $lang->sprintf($helpdoc['document'], $mybb->user['logoutkey']);
					}
				}
			}
			add_breadcrumb($helpdoc['name']);

			$plugins->run_hooks("misc_help_helpdoc_end");

			eval("\$helppage = \"".$templates->get("misc_help_helpdoc")."\";");
			output_page($helppage);
		}
		else
		{
			error($lang->error_invalidhelpdoc);
		}
	}
	else
	{
		$plugins->run_hooks("misc_help_section_start");

		$query = $db->simple_select("helpdocs", "*", "", array('order_by' => 'sid, disporder'));
		while($helpdoc = $db->fetch_array($query))
		{
			$helpdocs[$helpdoc['sid']][$helpdoc['disporder']][$helpdoc['hid']] = $helpdoc;
		}
		unset($helpdoc);
		$sections = '';
		$query = $db->simple_select("helpsections", "*", "enabled != 0", array('order_by' => 'disporder'));
		while($section = $db->fetch_array($query))
		{
			if($section['usetranslation'] == 1)
			{
				$langnamevar = "s".$section['sid']."_name";
				$langdescvar = "s".$section['sid']."_desc";
				if($lang->$langnamevar)
				{
					$section['name'] = $lang->$langnamevar;
				}
				if($lang->$langdescvar)
				{
					$section['description'] = $lang->$langdescvar;
				}
			}
			if(is_array($helpdocs[$section['sid']]))
			{
				$helpbits = '';
				foreach($helpdocs[$section['sid']] as $key => $bit)
				{
					foreach($bit as $key => $helpdoc)
					{
						if($helpdoc['enabled'] != 0)
						{
							if($helpdoc['usetranslation'] == 1)
							{
								$langnamevar = "d".$helpdoc['hid'].'_name';
								$langdescvar = "d".$helpdoc['hid'].'_desc';
								if($lang->$langnamevar)
								{
									$helpdoc['name'] = $lang->$langnamevar;
								}
								if($lang->$langdescvar)
								{
									$helpdoc['description'] = $lang->$langdescvar;
								}
							}
							$altbg = alt_trow();
							eval("\$helpbits .= \"".$templates->get("misc_help_section_bit")."\";");
						}
					}
					$expdisplay = '';
					$sname = "sid_".$section['sid']."_c";
					if(isset($collapsed[$sname]) && $collapsed[$sname] == "display: show;")
					{
						$expcolimage = "collapse_collapsed.png";
						$expdisplay = "display: none;";
						$expthead = " thead_collapsed";
					}
					else
					{
						$expcolimage = "collapse.png";
						$expthead = "";
					}
				}
				eval("\$sections .= \"".$templates->get("misc_help_section")."\";");
			}
		}

		$plugins->run_hooks("misc_help_section_end");

		eval("\$help = \"".$templates->get("misc_help")."\";");
		output_page($help);
	}
}
elseif($mybb->input['action'] == "buddypopup")
{
	$plugins->run_hooks("misc_buddypopup_start");

	if($mybb->user['uid'] == 0)
	{
		error_no_permission();
	}

	if(isset($mybb->input['removebuddy']) && verify_post_check($mybb->input['my_post_key']))
	{
		$buddies = $mybb->user['buddylist'];
		$namesarray = explode(",", $buddies);
		$mybb->input['removebuddy'] = $mybb->get_input('removebuddy', 1);
		if(is_array($namesarray))
		{
			foreach($namesarray as $key => $buddyid)
			{
				if($buddyid == $mybb->input['removebuddy'])
				{
					unset($namesarray[$key]);
				}
			}
			$buddylist = implode(',', $namesarray);
			$db->update_query("users", array('buddylist' => $buddylist), "uid='".$mybb->user['uid']."'");
			$mybb->user['buddylist'] = $buddylist;
		}
	}

	// Load Buddies
	$buddies = '';
	if($mybb->user['buddylist'] != "")
	{
		$buddys = array('online' => '', 'offline' => '');
		$timecut = TIME_NOW - $mybb->settings['wolcutoff'];

		$query = $db->simple_select("users", "*", "uid IN ({$mybb->user['buddylist']})", array('order_by' => 'lastactive'));

		while($buddy = $db->fetch_array($query))
		{
			$buddy_name = format_name($buddy['username'], $buddy['usergroup'], $buddy['displaygroup']);
			$profile_link = build_profile_link($buddy_name, $buddy['uid'], '_blank', 'if(window.opener) { window.opener.location = this.href; return false; }');

			$send_pm = '';
			if($mybb->user['receivepms'] != 0 && $buddy['receivepms'] != 0 && $groupscache[$buddy['usergroup']]['canusepms'] != 0)
			{
				eval("\$send_pm = \"".$templates->get("misc_buddypopup_user_sendpm")."\";");
			}

			if($buddy['lastactive'])
			{
				$last_active = $lang->sprintf($lang->last_active, my_date('relative', $buddy['lastactive']));
			}
			else
			{
				$last_active = $lang->sprintf($lang->last_active, $lang->never);
			}

			$buddy['avatar'] = format_avatar(htmlspecialchars_uni($buddy['avatar']), $buddy['avatardimensions'], '44x44');

			if($buddy['lastactive'] > $timecut && ($buddy['invisible'] == 0 || $mybb->user['usergroup'] == 4) && $buddy['lastvisit'] != $buddy['lastactive'])
			{
				$bonline_alt = alt_trow();
				eval("\$buddys['online'] .= \"".$templates->get("misc_buddypopup_user_online")."\";");
			}
			else
			{
				$boffline_alt = alt_trow();
				eval("\$buddys['offline'] .= \"".$templates->get("misc_buddypopup_user_offline")."\";");
			}
		}

		$colspan = ' colspan="2"';
		if(empty($buddys['online']))
		{
			$error = $lang->online_none;
			eval("\$buddys['online'] = \"".$templates->get("misc_buddypopup_user_none")."\";");
		}

		if(empty($buddys['offline']))
		{
			$error = $lang->offline_none;
			eval("\$buddys['offline'] = \"".$templates->get("misc_buddypopup_user_none")."\";");
		}

		eval("\$buddies = \"".$templates->get("misc_buddypopup_user")."\";");
	}
	else
	{
		// No buddies? :(
		$colspan = '';
		$error = $lang->no_buddies;
		eval("\$buddies = \"".$templates->get("misc_buddypopup_user_none")."\";");
	}

	$plugins->run_hooks("misc_buddypopup_end");

	eval("\$buddylist = \"".$templates->get("misc_buddypopup", 1, 0)."\";");
	echo $buddylist;
	exit;
}
elseif($mybb->input['action'] == "whoposted")
{
	$numposts = 0;
	$altbg = alt_trow();
	$whoposted = '';
	$tid = $mybb->get_input('tid', 1);
	$thread = get_thread($tid);

	// Make sure we are looking at a real thread here.
	if(!$thread)
	{
		error($lang->error_invalidthread);
	}

	if(is_moderator($thread['fid']))
	{
		$ismod = true;
		$show_posts = "(p.visible = '1' OR p.visible = '0')";
	}
	else
	{
		$ismod = false;
		$show_posts = "p.visible = '1'";
	}

	// Make sure we are looking at a real thread here.
	if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
	{
		error($lang->error_invalidthread);
	}
	// Does the thread belong to a valid forum?
	$forum = get_forum($thread['fid']);
	if(!$forum || $forum['type'] != "f")
	{
		error($lang->error_invalidforum);
	}

	// Does the user have permission to view this thread?
	$forumpermissions = forum_permissions($forum['fid']);

	if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
	{
		error_no_permission();
	}

	// Check if this forum is password protected and we have a valid password
	check_forum_password($forum['fid']);

	if($mybb->get_input('sort') != 'username')
	{
		$sortsql = ' ORDER BY posts DESC';
	}
	else
	{
		$sortsql = ' ORDER BY p.username ASC';
	}
	$whoposted = '';
	$query = $db->query("
		SELECT COUNT(p.pid) AS posts, p.username AS postusername, u.uid, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE tid='".$tid."' AND $show_posts
		GROUP BY u.uid, p.username, u.uid, u.username, u.usergroup, u.displaygroup
		".$sortsql."
	");
	while($poster = $db->fetch_array($query))
	{
		if($poster['username'] == '')
		{
			$poster['username'] = $poster['postusername'];
		}
		$poster_name = format_name($poster['username'], $poster['usergroup'], $poster['displaygroup']);
		if($poster['uid'])
		{
			$onclick = "opener.location.href='".get_profile_link($poster['uid'])."'; return false;";
		}
		$profile_link = build_profile_link($poster_name, $poster['uid'], '_blank', $onclick);
		$numposts += $poster['posts'];
		eval("\$whoposted .= \"".$templates->get("misc_whoposted_poster")."\";");
		$altbg = alt_trow();
	}
	$numposts = my_number_format($numposts);
	$poster['posts'] = my_number_format($poster['posts']);
	eval("\$whop = \"".$templates->get("misc_whoposted", 1, 0)."\";");
	echo $whop;
	exit;
}
elseif($mybb->input['action'] == "smilies")
{
	$smilies = '';
	if(!empty($mybb->input['popup']) && !empty($mybb->input['editor']))
	{ // make small popup list of smilies
		$editor = preg_replace('#([^a-zA-Z0-9_-]+)#', '', $mybb->get_input('editor'));
		$e = 1;
		$class = "trow1";
		$smilies = "<tr>";
		$query = $db->simple_select("smilies", "*", "", array('order_by' => 'disporder'));
		while($smilie = $db->fetch_array($query))
		{
			$smilie['insert'] = addslashes($smilie['find']);
			$smilie['find'] = htmlspecialchars_uni($smilie['find']);
			eval("\$smilies .= \"".$templates->get("misc_smilies_popup_smilie")."\";");
			if($e == 2)
			{
				$smilies .= "</tr><tr>";
				$e = 1;
				$class = alt_trow();
			}
			else
			{
				$e = 2;
			}
		}
		if($e == 2)
		{
			eval("\$smilies .= \"".$templates->get("misc_smilies_popup_empty")."\";");
		}
		eval("\$smiliespage = \"".$templates->get("misc_smilies_popup")."\";");
		output_page($smiliespage);
	}
	else
	{
		add_breadcrumb($lang->nav_smilies);
		$class = "trow1";
		$query = $db->simple_select("smilies", "*", "", array('order_by' => 'disporder'));
		while($smilie = $db->fetch_array($query))
		{
			$smilie['find'] = htmlspecialchars_uni($smilie['find']);
			eval("\$smilies .= \"".$templates->get("misc_smilies_smilie")."\";");
			$class = alt_trow();
		}
		eval("\$smiliespage = \"".$templates->get("misc_smilies")."\";");
		output_page($smiliespage);
	}
}
elseif($mybb->input['action'] == "imcenter")
{
	$mybb->input['imtype'] = $mybb->get_input('imtype');
	if($mybb->input['imtype'] != "aim" && $mybb->input['imtype'] != "skype" && $mybb->input['imtype'] != "yahoo")
	{
		error($lang->error_invalidimtype);
	}
	$uid = $mybb->get_input('uid', 1);
	$user = get_user($uid);

	if(!$user)
	{
		error($lang->error_invaliduser);
	}
	if(empty($user[$mybb->input['imtype']]))
	{
		error($lang->error_invalidimtype);
	}

	// build im navigation bar
	$navigationbar = $navsep = '';
	if($user['aim'])
	{
		$navigationbar .= "<a href=\"misc.php?action=imcenter&amp;imtype=aim&amp;uid=$uid\">$lang->aol_im</a>";
		$navsep = ' - ';
	}
	if($user['skype'])
	{
		$navigationbar .= "$navsep<a href=\"misc.php?action=imcenter&amp;imtype=skype&amp;uid=$uid\">$lang->skype</a>";
		$navsep = ' - ';
	}
	if($user['yahoo'])
	{
		$navigationbar .= "$navsep<a href=\"misc.php?action=imcenter&amp;imtype=yahoo&amp;uid=$uid\">$lang->yahoo_im</a>";
	}

	$lang->chat_on_skype = $lang->sprintf($lang->chat_on_skype, $user['username']);
	$lang->call_on_skype = $lang->sprintf($lang->call_on_skype, $user['username']);
	$lang->send_y_message = $lang->sprintf($lang->send_y_message, $user['username']);
	$lang->view_y_profile = $lang->sprintf($lang->view_y_profile, $user['username']);

	$imtemplate = "misc_imcenter_".$mybb->input['imtype'];
	eval("\$imcenter = \"".$templates->get($imtemplate, 1, 0)."\";");
	echo $imcenter;
	exit;
}
elseif($mybb->input['action'] == "syndication")
{
	$plugins->run_hooks("misc_syndication_start");

	$fid = $mybb->get_input('fid', 1);
	$version = $mybb->get_input('version');
	$limit = $mybb->get_input('limit', 1);
	$forums = $mybb->get_input('forums', 2);
	$limit = 15;
	$feedurl = '';
	$add = false;

	add_breadcrumb($lang->nav_syndication);
	$unviewable = get_unviewable_forums();
	if(is_array($forums))
	{
		$unexp = explode(",", $unviewable);
		foreach($unexp as $fid)
		{
			$unview[$fid] = true;
		}
		$syndicate = '';
		$comma = '';
		$all = false;
		foreach($forums as $fid)
		{
			if($fid == "all")
			{
				$all = true;
				break;
			}
			elseif(is_numeric($fid))
			{
				if(!isset($unview[$fid]))
				{
					$syndicate .= $comma.$fid;
					$comma = ",";
					$flist[$fid] = true;
				}
			}
		}
		$url = $mybb->settings['bburl']."/syndication.php";
		if(!$all)
		{
			$url .= "?fid=$syndicate";
			$add = true;
		}

		// If the version is not RSS2.0, set the type to Atom1.0.
		if($version != "rss2.0")
		{
			if(!$add)
			{
				$url .= "?";
			}
			else
			{
				$url .= "&";
			}
			$url .= "type=atom1.0";
			$add = true;
		}
		if(intval($limit) > 0)
		{
			if($limit > 100)
			{
				$limit = 100;
			}
			if(!$add)
			{
				$url .= "?";
			}
			else
			{
				$url .= "&";
			}
			if(is_numeric($limit))
			{
				$url .= "limit=$limit";
			}
		}
		eval("\$feedurl = \"".$templates->get("misc_syndication_feedurl")."\";");
	}
	unset($GLOBALS['forumcache']);

	// If there is no version in the input, check the default (RSS2.0).
	if($version == "atom1.0")
	{
		$atom1check = "checked=\"checked\"";
		$rss2check = '';
	}
	else
	{
		$atom1check = '';
		$rss2check = "checked=\"checked\"";
	}
	$forumselect = makesyndicateforums();

	$plugins->run_hooks("misc_syndication_end");

	eval("\$syndication = \"".$templates->get("misc_syndication")."\";");
	output_page($syndication);
}
elseif($mybb->input['action'] == "clearcookies")
{
	$plugins->run_hooks("misc_clearcookies");

	if($mybb->get_input('key') != $mybb->user['logoutkey'])
	{
		error($lang->error_invalidkey);
	}

	$remove_cookies = array('mybb', 'mybbuser', 'mybb[password]', 'mybb[lastvisit]', 'mybb[lastactive]', 'collapsed', 'mybb[forumread]', 'mybb[threadsread]', 'mybbadmin');

	foreach($remove_cookies as $name)
	{
		@my_setcookie($name, '', TIME_NOW-1, $mybb->settings['cookiepath'], $mybb->settings['cookiedomain']);
	}
	redirect("index.php", $lang->redirect_cookiescleared);
}

function makesyndicateforums($pid="0", $selitem="", $addselect="1", $depth="", $permissions="")
{
	global $db, $forumcache, $permissioncache, $mybb, $forumlist, $forumlistbits, $flist, $lang, $unviewable;
	static $unviewableforums;

	$pid = intval($pid);
	if(!$permissions)
	{
		$permissions = $mybb->usergroup;
	}

	if(!is_array($forumcache))
	{
		// Get Forums
		$query = $db->simple_select("forums", "*", "linkto = '' AND active!=0", array('order_by' => 'pid, disporder'));
		while($forum = $db->fetch_array($query))
		{
			$forumcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
	}

	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}

	if(!$unviewableforums)
	{
		// Save our unviewable forums in an array
		$unviewableforums = explode(",", str_replace("'", "", $unviewable));
	}

	if(is_array($forumcache[$pid]))
	{
		foreach($forumcache[$pid] as $key => $main)
		{
			foreach($main as $key => $forum)
			{
				$perms = $permissioncache[$forum['fid']];
				if($perms['canview'] == 1 || $mybb->settings['hideprivateforums'] == 0)
				{
					if(isset($flist[$forum['fid']]))
					{
						$optionselected = "selected=\"selected\"";
						$selecteddone = "1";
					}
					else
					{
						$optionselected = '';
					}

					if($forum['password'] == '' && !in_array($forum['fid'], $unviewableforums) || $forum['password'] && isset($mybb->cookies['forumpass'][$forum['fid']]) && $mybb->cookies['forumpass'][$forum['fid']] == md5($mybb->user['uid'].$forum['password']))
					{
						$forumlistbits .= "<option value=\"{$forum['fid']}\" $optionselected>$depth {$forum['name']}</option>\n";
					}

					if(!empty($forumcache[$forum['fid']]))
					{
						$newdepth = $depth."&nbsp;&nbsp;&nbsp;&nbsp;";
						$forumlistbits .= makesyndicateforums($forum['fid'], $selitem, 0, $newdepth, $perms);
					}
				}
			}
		}
	}
	if($addselect)
	{
		if(empty($selecteddone))
		{
			$addsel = " selected=\"selected\"";
		}
		else
		{
			$addsel = '';
		}
		$forumlist = "<select name=\"forums[]\" size=\"10\" multiple=\"multiple\">\n<option value=\"all\" $addsel>$lang->syndicate_all_forums</option>\n<option value=\"all\">----------------------</option>\n$forumlistbits\n</select>";
	}
	return $forumlist;
}
?>
