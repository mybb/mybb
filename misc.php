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

define("IN_MYBB", 1);

$templatelist = "redirect_markallread,redirect_markforumread";
$templatelist .= ",misc_buddypopup,misc_buddypopup_user_online,misc_buddypopup_user_offline,misc_buddypopup_user_sendpm";
$templatelist .= ",misc_smilies,misc_smilies_smilie,misc_help_section_bit,misc_help_section,misc_help";
require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";

// Load global language phrases
$lang->load("misc");

$plugins->run_hooks("misc_start");

if($mybb->input['action'] == "markread")
{
	if($mybb->input['fid'])
	{
		$validforum = get_forum($db->escape_string($mybb->input['fid']));
		if(!$validforum)
		{
			error($lang->error_invalidforum);
		}
		my_set_array_cookie("forumread", $mybb->input['fid'], time());

		$plugins->run_hooks("misc_markread_forum");

		redirect("forumdisplay.php?fid=".$mybb->input['fid'], $lang->redirect_markforumread);
	}
	else
	{
		if($mybb->user['uid'] != 0)
		{
			$db->update_query(TABLE_PREFIX."users", array('lastvisit' => time()), "uid='".$mybb->user['uid']."'");
			require_once MYBB_ROOT."/inc/functions_user.php";
			update_pm_count('', 2);
		}
		else
		{
			my_setcookie("mybb[lastvisit]", time());
		}

		$plugins->run_hooks("misc_markread_end");

		redirect("index.php", $lang->redirect_markforumsread);
	}
}
elseif($mybb->input['action'] == "clearpass")
{
	$plugins->run_hooks("misc_clearpass");

	if($mybb->input['fid'])
	{
		my_setcookie("forumpass[".intval($mybb->input['fid'])."]", '');
		redirect("index.php", $lang->redirect_forumpasscleared);
	}
}
elseif($mybb->input['action'] == "rules")
{
	if($mybb->input['fid'])
	{
		$plugins->run_hooks("misc_rules_start");

		$query = $db->simple_select(TABLE_PREFIX."forums", "*", "fid='".intval($mybb->input['fid'])."' AND active!='no'");
		$forum = $db->fetch_array($query);

		$forumpermissions = forum_permissions($forum['fid']);

		if($forum['type'] != "f" || $forum['rules'] == '')
		{
			error($lang->error_invalidforum);
		}
		if($forumpermissions['canview'] != "yes")
		{
			error_no_permission();
		}
		if(!$forum['rulestitle'])
		{
			$forum['rulestitle'] = sprintf($lang->forum_rules, $forum['name']);
		}

		require_once MYBB_ROOT."/inc/class_parser.php";
		$parser = new postParser();
		$parser_options = array(
			"allow_html" => 'yes',
			"allow_mycode" => 'yes',
			"allow_smilies" => 'yes',
			"allow_imgcode" => 'yes'
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

	add_breadcrumb($lang->nav_helpdocs, "misc.php?action=help");

	$query = $db->query("
		SELECT h.*, s.enabled AS section
		FROM ".TABLE_PREFIX."helpdocs h
		LEFT JOIN ".TABLE_PREFIX."helpsections s ON (s.sid=h.sid)
		WHERE h.hid='".intval($mybb->input['hid'])."'
	");
	$helpdoc = $db->fetch_array($query);
	if($helpdoc['hid'])
	{
		if($helpdoc['section'] != "no" && $helpdoc['enabled'] != "no")
		{
			$plugins->run_hooks("misc_help_helpdoc_start");

			if($helpdoc['usetranslation'] == "yes" || $helpdoc['hid'] <= 7)
			{
				$langnamevar = "d".$helpdoc['hid']."_name";
				$langdescvar = "d".$helpdoc['hid']."_desc";
				$langdocvar = "d".$helpdoc['hid']."_document";
				$helpdoc['name'] = $lang->$langnamevar;
				$helpdoc['description'] = $lang->$langdescvar;
				$helpdoc['document'] = $lang->$langdocvar;
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

		$query = $db->simple_select(TABLE_PREFIX."helpdocs", "*", "", array('order_by' => 'sid, disporder'));
		while($helpdoc = $db->fetch_array($query))
		{
			$helpdocs[$helpdoc['sid']][$helpdoc['disporder']][$helpdoc['hid']] = $helpdoc;
		}
		unset($helpdoc);
		$sections = '';
		$query = $db->simple_select(TABLE_PREFIX."helpsections", "*", "enabled != 'no'", array('order_by' => 'disporder'));
		while($section = $db->fetch_array($query))
		{
			if($section['usetranslation'] == "yes" || $section['sid'] <= 2)
			{
				$langnamevar = "s".$section['sid']."_name";
				$langdescvar = "s".$section['sid']."_desc";
				$section['name'] = $lang->$langnamevar;
				$section['description'] = $lang->$langdescvar;
			}
			if(is_array($helpdocs[$section['sid']]))
			{
				$helpbits = '';
				// Expand (or Collapse) forums
				if($mybb->input['action'] == "expand")
				{
					my_setcookie("fcollapse[$section[sid]]", '');
					$scollapse[$section['sid']] = '';
				}
				elseif($mybb->input['action'] == "collapse")
				{
					my_setcookie("fcollapse[$section[sid]]", "y");
					$scollapse[$section['sid']] = "y";
				}
				foreach($helpdocs[$section['sid']] as $key => $bit)
				{
					foreach($bit as $key => $helpdoc)
					{
						if($helpdoc['enabled'] != "no")
						{
							if($helpdoc['usetranslation'] == "yes" || $helpdoc['hid'] <= 7)
							{
								$langnamevar = "d".$helpdoc['hid'].'_name';
								$langdescvar = "d".$helpdoc['hid'].'_desc';
								$helpdoc['name'] = $lang->$langnamevar;
								$helpdoc['description'] = $lang->$langdescvar;
							}
							$altbg = alt_trow();
							eval("\$helpbits .= \"".$templates->get("misc_help_section_bit")."\";");
						}
					}
					$expdisplay = '';
					$sname = "sid_".$section['sid']."_c";
					if($collapsed[$sname] == "display: show;")
					{
						$expcolimage = "collapse_collapsed.gif";
						$expdisplay = "display: none;";
					}
					else
					{
						$expcolimage = "collapse.gif";
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
	if($mybb->input['removebuddy'])
	{
		$buddies = $mybb->user['buddylist'];
		$namesarray = explode(",",$buddies);
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
			$query = $db->update_query(TABLE_PREFIX."users", array('buddylist' => $buddylist), "uid='".$mybb->user['uid']."'");
			$mybb->user['buddylist'] = $buddylist;
		}
	}
	// Load Buddies
	$buddies = $mybb->user['buddylist'];
	$buddys = array();
	$namesarray = explode(',', $buddies);
	if(is_array($namesarray))
	{
		$comma = '';
		$sql = '';
		foreach($namesarray as $key => $buddyid)
		{
			$sql .= "$comma'$buddyid'";
			$comma = ",";
		}
		$timecut = time() - $mybb->settings['wolcutoff'];
		$query = $db->query("
			SELECT u.*, g.canusepms
			FROM ".TABLE_PREFIX."users u
			LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup)
			WHERE u.uid IN ($sql)
		");
		while($buddy = $db->fetch_array($query))
		{
			$buddy_name = format_name($buddy['username'], $buddy['usergroup'], $buddy['displaygroup']);
			$profile_link = build_profile_link($buddy_name, $buddy['uid'], '_blank');
			if($mybb->user['receivepms'] != "no" && $buddy['receivepms'] != "no" && $buddy['canusepms'] != "no")
			{
				eval("\$pmbuddy = \"".$templates->get("misc_buddypopup_user_sendpm")."\";");
			}
			else
			{
				$pmbuddy = '';
			}
			if($buddy['lastactive'] > $timecut && ($buddy['invisible'] == "no" || $mybb->user['usergroup'] == 4) && $buddy['lastvisit'] != $buddy['lastactive'])
			{
				eval("\$buddys[online] .= \"".$templates->get("misc_buddypopup_user_online")."\";");
			}
			else
			{
				eval("\$buddys[offline] .= \"".$templates->get("misc_buddypopup_user_offline")."\";");
			}
		}
	}

	$plugins->run_hooks("misc_buddypopup_end");

	eval("\$buddylist = \"".$templates->get("misc_buddypopup")."\";");
	output_page($buddylist);
}
elseif($mybb->input['action'] == "whoposted")
{
	$numposts = 0;
	$altbg = "trow1";
	$whoposted = '';
	$tid = intval($mybb->input['tid']);
	if($mybb->input['sort'] != 'username')
	{
		$sortsql = ' ORDER BY posts DESC';
	}
	else
	{
		$sortsql = ' ORDER BY p.username ASC';
	}
	$query = $db->query("
		SELECT COUNT(p.pid) AS posts, p.username AS postusername, u.uid, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE tid='".$tid."' AND p.visible='1'
		GROUP BY u.uid
		".$sortsql."
	");
	while($poster = $db->fetch_array($query))
	{
		if($poster['username'] == '')
		{
			$poster['username'] = $poster['postusername'];
		}
		$poster_name = format_name($poster['username'], $poster['usergroup'], $poster['displaygroup']);
		$profile_link = build_profile_link($poster_name, $poster['uid'], '_blank');
		$numposts += $poster['posts'];
		eval("\$whoposted .= \"".$templates->get("misc_whoposted_poster")."\";");
		$altbg = alt_trow();
	}
	eval("\$whop = \"".$templates->get("misc_whoposted")."\";");
	output_page($whop);
}
elseif($mybb->input['action'] == "smilies")
{
	$smilies = '';
	if($mybb->input['popup'])
	{ // make small popup list of smilies
		$editor = htmlspecialchars($mybb->input['editor']);
		$e = 1;
		$class = "trow1";
		$smilies = "<tr>";
		$query = $db->simple_select(TABLE_PREFIX."smilies", "*", "", array('order_by' => 'disporder'));
		while($smilie = $db->fetch_array($query))
		{
			$smiliefind = $smilie['find'];
			$smilie['find'] = $db->escape_string($smilie['find']);
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
			$smilies .= "<td colspan=\"2\" class=\"$class\">&nbsp;</td>";
		}
		eval("\$smiliespage = \"".$templates->get("misc_smilies_popup")."\";");
		output_page($smiliespage);
	}
	else
	{
		add_breadcrumb($lang->nav_smilies);
		$class = "trow1";
		$query = $db->simple_select(TABLE_PREFIX."smilies", "*", "", array('order_by' => 'disporder'));
		while($smilie = $db->fetch_array($query))
		{
			eval("\$smilies .= \"".$templates->get("misc_smilies_smilie")."\";");
			$class = alt_trow();
		}
		eval("\$smiliespage = \"".$templates->get("misc_smilies")."\";");
		output_page($smiliespage);
	}
}
elseif($mybb->input['action'] == "imcenter")
{
	if(!$mybb->input['imtype'])
	{
		error($lang->error_invalidimtype);
	}
	$uid = intval($mybb->input['uid']);
	$query = $db->simple_select(TABLE_PREFIX."users", "*", "uid='".$uid."'", array('limit' => 1));
	$user = $db->fetch_array($query);

	if(!$user['username'])
	{
		error($lang->error_invaliduser);
	}
	if(!$user[$mybb->input['imtype']])
	{
		error($lang->error_invalidimtype);
	}

	// build im navigation bar
	$navigationbar = $navsep = '';
	if($user['aim'])
	{
		$navigationbar .= "<a href=\"misc.php?action=imcenter&imtype=aim&uid=$uid\">$lang->aol_im</a>";
		$navsep = ' - ';
	}
	if($user['icq'])
	{
		$navigationbar .= "$navsep<a href=\"misc.php?action=imcenter&imtype=icq&uid=$uid\">$lang->icq</a>";
		$navsep = ' - ';
	}
	if($user['msn'])
	{
		$navigationbar .= "$navsep<a href=\"misc.php?action=imcenter&imtype=msn&uid=$uid\">$lang->msn</a>";
		$navsep = ' - ';
	}
	if($user['yahoo'])
	{
		$navigationbar .= "$navsep<a href=\"misc.php?action=imcenter&imtype=yahoo&uid=$uid\">$lang->yahoo_im</a>";
	}
	$lang->msn_address_is = sprintf($lang->msn_address_is, $user['username']);
	$lang->send_y_message = sprintf($lang->send_y_message, $user['username']);
	$lang->view_y_profile = sprintf($lang->view_y_profile, $user['username']);
	$imtemplate = "misc_imcenter_".$mybb->input['imtype'];
	eval("\$imcenter = \"".$templates->get($imtemplate)."\";");
	output_page($imcenter);
}
elseif($mybb->input['action'] == "syndication")
{
	$plugins->run_hooks("misc_syndication_start");

	$fid = $mybb->input['fid'];
	$version = $mybb->input['version'];
	$limit = $mybb->input['limit'];
	$forums = $mybb->input['forums'];

	add_breadcrumb($lang->nav_syndication);
	$unviewable = get_unviewable_forums();
	if(is_array($forums))
	{
		$unexp = explode(",", $unviewable);
		foreach($unexp as $fid)
		{
			$unview[$fid] = 1;
		}
		$syndicate = '';
		$comma = '';
		foreach($forums as $fid)
		{
			if($fid == "all")
			{
				$all = 1;
				break;
			}
			elseif(is_numeric($fid))
			{
				if(!$unview[$fid])
				{
					$syndicate .= $comma.$fid;
					$comma = ",";
					$flist[$fid] = 1;
				}
			}
		}
		$url = $mybb->settings['bburl']."/syndication.php";
		if(!$all)
		{
			$url .= "?fid=$syndicate";
			$add = 1;
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
			$add = 1;
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
		$limit = intval($_POST['limit']);
		eval("\$feedurl = \"".$templates->get("misc_syndication_feedurl")."\";");
	}
	unset($GLOBALS['forumcache']);
	if(!$limit || !is_numeric($limit))
	{
		$limit = 15;
	}

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
	$forumselect = makesyndicateforums("", $blah);

	$plugins->run_hooks("misc_syndication_end");

	eval("\$syndication = \"".$templates->get("misc_syndication")."\";");
	output_page($syndication);
}


if($mybb->input['action'] == "clearcookies")
{
	$plugins->run_hooks("misc_clearcookies");

	$remove_cookies = array('mybb', 'mybbuser', 'mybb[password]', 'mybb[lastvisit]', 'mybb[lastactive]', 'collapsed', 'mybb[forumread]', 'mybb[threadsread]', 'mybbadmin');

	if($mybb->settings['cookiedomain'])
	{
		foreach($remove_cookies as $name)
		{
			@setcookie($name, '', time()-1, $mybb->settings['cookiepath'], $mybb->settings['cookiedomain']);
		}
	}
	else
	{
		foreach($remove_cookies as $name)
		{
			@setcookie($name, '', time()-1, $mybb->settings['cookiepath']);
		}
	}
	redirect("index.php", $lang->redirect_cookiescleared);
}

function makesyndicateforums($pid="0", $selitem="", $addselect="1", $depth="", $permissions="")
{
	global $db, $forumcache, $permissioncache, $mybb, $selecteddone, $forumlist, $forumlistbits, $theme, $templates, $flist, $lang;

	$pid = intval($pid);
	if(!$permissions)
	{
		$permissions = $mybb->usergroup;
	}
	if(!is_array($forumcache))
	{
		// Get Forums
		$query = $db->simple_select(TABLE_PREFIX."forums", "*", "linkto = '' AND active!='no'", array('order_by' => 'pid, disporder'));
		while($forum = $db->fetch_array($query))
		{
			$forumcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
	}
	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}
	if(is_array($forumcache[$pid]))
	{
		foreach($forumcache[$pid] as $key => $main)
		{
			foreach($main as $key => $forum)
			{
				$perms = $permissioncache[$forum['fid']];
				if($perms['canview'] == "yes" || $mybb->settings['hideprivateforums'] == "no")
				{
					if($flist[$forum['fid']])
					{
						$optionselected = "selected=\"selected\"";
						$selecteddone = "1";
					}
					else
					{
						$optionselected = '';
					}

					if($forum['password'] == '')
					{
						$forumlistbits .= "<option value=\"$forum[fid]\" $optionselected>$depth $forum[name]</option>\n";
					}
					if($forumcache[$forum['fid']])
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
		if(!$selecteddone)
		{
			$addsel = " selected=\"selected\"";
		}
		$forumlist = "<select name=\"forums[]\" size=\"10\" multiple=\"multiple\">\n<option value=\"all\" $addsel>$lang->syndicate_all_forums</option>\n<option value=\"all\">----------------------</option>\n$forumlistbits\n</select>";
	}
	return $forumlist;
}

?>
