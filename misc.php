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
 
 define("KILL_GLOBALS", 1);

$templatelist = "redirect_markallread,redirect_markforumread";
$templatelist .= ",misc_buddypopup,misc_buddypopup_user_online,misc_buddypopup_user_offline,misc_buddypopup_user_sendpm";
$templatelist .= ",misc_smilies,misc_smilies_smilie,misc_help_section_bit,misc_help_section,misc_help";
require "./global.php";

// Load global language phrases
$lang->load("misc");

$plugins->run_hooks("misc_start");

if($mybb->input['action'] == "markread")
{
	if($mybb->input['fid'])
	{
		$validforum = validateforum($mybb->input['fid']);
		if(!$validforum)
		{
			error($lang->error_invalidforum);
		}
		mysetarraycookie("forumread", $mybb->input['fid'], time());

		$plugins->run_hooks("misc_markread_forum");

		redirect("forumdisplay.php?fid=".$mybb->input['fid'], $lang->redirect_markforumread);
	}
	else
	{
		if($mybb->user['uid'] != 0)
		{
			$db->query("UPDATE ".TABLE_PREFIX."users SET lastvisit='".time()."' WHERE uid='".$mybb->user[uid]."'");
		}
		else
		{
			mysetcookie("mybb[lastvisit]", time());
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
		mysetcookie("forumpass[".$mybb->input['fid']."]", "");
		redirect("index.php", $lang->redirect_forumpasscleared);
	}
}
elseif($mybb->input['action'] == "rules")
{
	if($mybb->input['fid'])
	{
		$plugins->run_hooks("misc_rules_start");

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='".$mybb->input['fid']."' AND active!='no'");
		$forum = $db->fetch_array($query);

		$forumpermissions = forum_permissions($forum['fid']);

		if($forum['type'] != "f" || $forum['rules'] == "") {
			error($lang->error_invalidforum);
		}
		if($forumpermissions['canview'] != "yes") {
			nopermission();
		}
		if(!$forum['rulestitle'])
		{
			$forum['rulestitle'] = sprintf($lang->forum_rules, $forum['name']);
		}
		$forum['rules'] = nl2br($forum['rules']);
		// Make navigation
		makeforumnav($mybb->input['fid']);
		addnav($forum['rulestitle']);

		$plugins->run_hooks("misc_rules_end");

		eval("\$rules = \"".$templates->get("misc_rules_forum")."\";");
		outputpage($rules);
	}

}
elseif($mybb->input['action'] == "help")
{
	$lang->load("helpdocs");
	$lang->load("customhelpdocs");
	
	addnav($lang->nav_helpdocs, "misc.php?action=help");

	$query = $db->query("SELECT h.*, s.enabled AS section FROM ".TABLE_PREFIX."helpdocs h LEFT JOIN ".TABLE_PREFIX."helpsections s ON (s.sid=h.sid) WHERE h.hid='".$mybb->input['hid']."'");
	$helpdoc = $db->fetch_array($query);
	if($helpdoc['hid'])
	{
		if($helpdoc['section'] != "no" && $helpdoc['enabled'] != "no")
		{
			$plugins->run_hooks("misc_help_helpdoc_start");

			if($helpdoc['usetranslation'] == "yes" || $helpdoc['hid'] <= 7)
			{
				$langnamevar = $helpdoc['hid']."_name";
				$langdescvar = $helpdoc['hid']."_desc";
				$langdocvar = $helpdoc['hid']."_document";
				$helpdoc['name'] = $lang->$langnamevar;
				$helpdoc['description'] = $lang->$langdescvar;
				$helpdoc['document'] = $lang->$langdocvar;
			}
			addnav($helpdoc['name']);

			$plugins->run_hooks("misc_help_helpdoc_end");

			eval("\$helppage = \"".$templates->get("misc_help_helpdoc")."\";");
			outputpage($helppage);
		}
		else
		{
			error($lang->error_invalidhelpdoc);
		}
	}
	else
	{
		$plugins->run_hooks("misc_help_section_start");

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."helpdocs ORDER BY sid, disporder");
		while($helpdoc = $db->fetch_array($query))
		{
			$helpdocs[$helpdoc['sid']][$helpdoc['disporder']][$helpdoc['hid']] = $helpdoc;
		}
		unset($helpdoc);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."helpsections WHERE enabled!='no' ORDER BY disporder");
		while($section = $db->fetch_array($query))
		{
			if($section['usetranslation'] == "yes" || $section['sid'] <= 2)
			{
				$langnamevar = "s".$section['sid']."_name";
				$langdescvar = "s".$section['sid']."_desc";
				$section['name'] = $lang->$langnamevar;
				$section['description'] = $lang->$langdescvar;
			}
			else
			{
				$section['name'] = stripslashes($section['name']);
				$section['description'] = stripslashes($section['description']);
			}
			if(is_array($helpdocs[$section['sid']]))
			{
				$altbg = "trow1";
				$helpbits = "";
				// Expand (or Collapse) forums
				if($mybb->input['action'] == "expand")
				{
					mysetcookie("fcollapse[$section[sid]]", "");
					$scollapse[$section['sid']] = "";
				}
				elseif($mybb->input['action'] == "collapse")
				{
					mysetcookie("fcollapse[$section[sid]]", "y");
					$scollapse[$section['sid']] = "y";
				}
				while(list($key, $bit) = each($helpdocs[$section['sid']]))
				{
					while(list($key, $helpdoc) = each($bit))
					{
						if($helpdoc['enabled'] != "no")
						{
							if($helpdoc['usetranslation'] == "yes" || $helpdoc['hid'] <= 7)
							{
								$langnamevar = $helpdoc['hid'].'_name';
								$langdescvar = $helpdoc['hid'].'_desc';
								$helpdoc['name'] = $lang->$langnamevar;
								$helpdoc['description'] = $lang->$langdescvar;
							}
							else
							{
								$helpdoc['name'] = stripslashes($helpdoc['name']);
								$helpdoc['description'] = stripslashes($helpdoc['description']);
							}
							eval("\$helpbits .= \"".$templates->get("misc_help_section_bit")."\";");
							if($altbg == "trow2")
							{
								$altbg = "trow1";
							}
							else
							{
								$altbg = "trow2";
							}
						}
					}
					$expdisplay = "";
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
		outputpage($help);
	}
}
elseif($mybb->input['action'] == "buddypopup")
{
	$plugins->run_hooks("misc_buddypopup_start");

	if($mybb->user['uid'] == 0)
	{
		nopermission();
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
			$buddylist = implode(",", $namesarray);
			$query = $db->query("UPDATE ".TABLE_PREFIX."users SET buddylist='$buddylist' WHERE uid='".$mybb->user['uid']."'");
			$mybb->user['buddylist'] = $buddylist;
		}
	}
	// Load Buddies
	$buddies = $mybb->user['buddylist'];
	$namesarray = explode(",",$buddies);
	if(is_array($namesarray))
	{
		while(list($key, $buddyid) = each($namesarray))
		{
			$sql .= "$comma'$buddyid'";
			$comma = ",";
		}
		$timecut = time() - $mybb->settings['wolcutoff'];
		$query = $db->query("SELECT u.*, g.canusepms FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup) WHERE u.uid IN ($sql)");
		while($buddy = $db->fetch_array($query))
		{
			if($mybb->user['receivepms'] != "no" && $buddy['receivepms'] != "no" && $buddy['canusepms'] != "no")
			{
				eval("\$pmbuddy = \"".$templates->get("misc_buddypopup_user_sendpm")."\";");
			}
			else
			{
				$pmbuddy = "";
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
	outputpage($buddylist);
}
elseif($mybb->input['action'] == "whoposted")
{
	$numposts = 0;
	$altbg = "trow1";
	$query = $db->query("SELECT COUNT(p.pid) AS posts, p.username AS postusername, u.uid, u.username FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE tid='$tid' GROUP BY u.uid ORDER BY posts DESC");
	while($poster = $db->fetch_array($query))
	{
		if($poster['username'] == "")
		{
			$poster['username'] = $poster['postusername'];
		}
		$numposts += $poster['posts'];
		eval("\$whoposted .= \"".$templates->get("misc_whoposted_poster")."\";");
		if($altbg == "trow2")
		{
			$altbg = "trow1";
		}
		else
		{
			$altbg = "trow2";
		}
	}
	eval("\$whop = \"".$templates->get("misc_whoposted")."\";");
	outputpage($whop);
}
elseif($mybb->input['action'] == "smilies")
{
	if($popup)
	{ // make small popup list of smilies
		$e = 1;
		$class = "trow1";
		$smilies = "<tr>";
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."smilies ORDER BY disporder");
		while($smilie = $db->fetch_array($query))
		{
			$smiliefind = $smilie['find'];
			$smilie['find'] = addslashes($smilie['find']);
			eval("\$smilies .= \"".$templates->get("misc_smilies_popup_smilie")."\";");
			if($e == 2)
			{
				$smilies .= "</tr>";
				$e = 1;
				if($class == "trow1")
				{
					$class = "trow2";
				}
				else
				{
					$class = "trow1";
				}
			}
			else
			{
				$e = 2;
			}
		}
		if($e == 2)
		{
			$smilies .= "<td colspan=\"2\" class=\"$class\">&nbsp;</td></tr>";
		}
		eval("\$smiliespage = \"".$templates->get("misc_smilies_popup")."\";");
		outputpage($smiliespage);
	}
	else
	{
		addnav($lang->nav_smilies);
		$class = "trow1";
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."smilies ORDER BY disporder");
		while($smilie = $db->fetch_array($query))
		{
			eval("\$smilies .= \"".$templates->get("misc_smilies_smilie")."\";");
			if($class == "trow1")
			{
				$class = "trow2";
			}
			else
			{
				$class = "trow1";
			}
		}
		eval("\$smiliespage = \"".$templates->get("misc_smilies")."\";");
		outputpage($smiliespage);
	}
}
elseif($mybb->input['action'] == "imcenter")
{
	if(!$mybb->input['imtype'])
	{
		exit;
	}
	$uid = intval($mybb->input['uid']);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".$uid."' LIMIT 1");
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
	$navigationbar = "";
	if($user['aim'])
	{
		$navigationbar .= "<a href=\"misc.php?action=imcenter&imtype=aim&uid=$uid\">$lang->aol_im</a>";
		$navsep = " - ";
	}
	if($user['icq'])
	{
		$navigationbar .= "$navsep<a href=\"misc.php?action=imcenter&imtype=icq&uid=$uid\">$lang->icq</a>";
		$navsep = " - ";
	}
	if($user['msn'])
	{
		$navigationbar .= "$navsep<a href=\"misc.php?action=imcenter&imtype=msn&uid=$uid\">$lang->msn</a>";
		$navsep = " - ";
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
	outputpage($imcenter);
}
elseif($mybb->input['action'] == "syndication")
{

	$plugins->run_hooks("misc_syndication_start");

	$fid = $mybb->input['fid'];
	$version = $mybb->input['version'];
	$limit = $mybb->input['limit'];

	addnav($lang->nav_syndication);
	$unviewable = getunviewableforums();
	if(is_array($forums))
	{
		$unexp = explode(",", $unviewable);
		foreach($unexp as $fid)
		{
			$unview[$fid] = 1;
		}
		$syndicate = '';
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
		$url = $mybb->settings['bburl']."/rss.php";
		if(!$all)
		{
			$url .= "?fid=$syndicate";
			$add = 1;
		}
		if($version != "rss")
		{
			if(!$add)
			{
				$url .= "?";
			}
			else
			{
				$url .= "&";
			}
			$url .= "type=rss2.0";
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
	if($version == "rss2.0")
	{
		$rss2check = "checked=\"checked\"";
		$rsscheck = "";
	}
	else
	{
		$rss2check = "";
		$rsscheck = "checked=\"checked\"";
	}
	$forumselect = makesyndicateforums("", $blah);

	$plugins->run_hooks("misc_syndication_end");

	eval("\$syndication = \"".$templates->get("misc_syndication")."\";");
	outputpage($syndication);
}
	

if($mybb->input['action'] == "clearcookies")
{
	$plugins->run_hooks("misc_clearcookies");

	if($mybb->settings['cookiedomain'])
	{
		@setcookie("mybb[uid]", "", time()-1, $mybb->settings['cookiepath'], $mybb->settings['cookiedomain']);
		@setcookie("mybb[password]", "", time()-1, $mybb->settings['cookiepath'], $mybb->settings['cookiedomain']);
		@setcookie("mybb[lastvisit]", "", time()-1, $mybb->settings['cookiepath'], $mybb->settings['cookiedomain']);
		@setcookie("mybb[lastactive]", "", time()-1, $mybb->settings['cookiepath'], $mybb->settings['cookiedomain']);
	}
	else
	{
		@setcookie("mybb", "", time()-1, $mybb->settings['cookiepath']);
		@setcookie("mybb[password]", "", time()-1, $mybb->settings['cookiepath']);
		@setcookie("mybb[lastvisit]", "", time()-1, $mybb->settings['cookiepath']);
		@setcookie("mybb[lastactive]", "", time()-1, $mybb->settings['cookiepath']);
	}
	redirect("index.php", $lang->redirect_cookiescleared);
}

function makesyndicateforums($pid="0", $selitem="", $addselect="1", $depth="", $permissions="")
{
	global $db, $forumcache, $permissioncache, $settings, $mybb, $mybbuser, $selecteddone, $forumlist, $forumlistbits, $theme, $templates, $flist, $lang, $forumpass;
	$pid = intval($pid);
	if(!$permissions)
	{
		$permissions = $mybb->usergroup;
	}
	if(!is_array($forumcache))
	{
		// Get Forums
		$query = $db->query("SELECT f.* FROM ".TABLE_PREFIX."forums f WHERE linkto='' ORDER BY f.pid, f.disporder");
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
		while(list($key, $main) = each($forumcache[$pid]))
		{
			while(list($key, $forum) = each($main))
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
						$optionselected = "";
					}

					if($forum['password'] == "")
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