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

require "./global.php";

// Load language packs for this section
global $lang;
$lang->load("forums");

checkadminpermissions("caneditforums");
logadmin();

addacpnav($lang->nav_forums, "forums.php");
switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_forum);
		break;
	case "edit":
		addacpnav($lang->nav_edit_forum);
		break;
	case "delete":
		addacpnav($lang->nav_delete_forum);
		break;
	case "copy":
		addacpnav($lang->nav_copy_forum);
		break;
	case "addmod":
		makeacpforumnav($fid);
		addacpnav($lang->nav_add_mod);
		break;
	case "editmod":
		addacpnav($lang->nav_edit_mod);
		break;
	case "deletemod":
		addacpnav($lang->nav_delete_mod);
		break;
	default:
		if($fid)
		{
			makeacpforumnav($fid);
		}
		break;
}

$plugins->run_hooks("admin_forums_start");

function getforums($pid=0, $depth=1)
{
	global $db, $iforumcache, $lang, $forum_cache, $comma;
	if(!is_array($iforumcache))
	{
		if(!is_array($forum_cache))
		{
			cache_forums();
		}
		if(!is_array($forum_cache))
		{
			return false;
		}

		reset($forum_cache);
		while(list($key, $val) = each($forum_cache))
		{
			$iforumcache[$val['pid']][$val['disporder']][$val['fid']] = $val;
		}
	}
	reset($iforumcache);
	if(is_array($iforumcache[$pid]))
	{
		foreach($iforumcache[$pid] as $key => $main)
		{
			$comma = "";
			foreach($main as $key => $forum)
			{
				$forum['name'] = $forum['name'];
				if($forum['active'] == "no")
				{
					$forum['name'] = "<em>".$forum['name']."</em>";
				}
				$forum['description'] = $forum['description'];
				if($forum['type'] == "c" && ($depth == 1 || $depth == 2))
				{
					echo "<tr>\n";
					echo "<td class=\"subheader\" colspan=\"3\">";
					if($depth == 2)
					{
						echo "<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\"><tr><td width=\"10\">&nbsp;</td><td class=\"subtext\">";
					}
					echo "<div style=\"float: right;\">";
					echo "<input type=\"button\" value=\"$lang->add_child_forum\" onclick=\"hopto('forums.php?action=add&pid=".$forum['fid']."');\" class=\"submitbutton\">";
					echo "<input type=\"button\" value=\"$lang->getforums_details\" onclick=\"hopto('forums.php?fid=".$forum['fid']."');\" class=\"submitbutton\">";
					echo "<input type=\"button\" value=\"$lang->getforums_edit\" onclick=\"hopto('forums.php?action=edit&fid=".$forum['fid']."');\" class=\"submitbutton\">";
					echo "<input type=\"button\" value=\"$lang->permissions\" onclick=\"hopto('forumpermissions.php?action=quickperms&fid=".$forum['fid']."');\" class=\"submitbutton\">";
					echo "<input type=\"button\" value=\"$lang->getforums_delete\" onclick=\"hopto('forums.php?action=delete&fid=".$forum['fid']."');\" class=\"submitbutton\">";
					echo "&nbsp;<input type=\"textbox\" name=\"disporder[".$forum['fid']."]\" value=\"".$forum['disporder']."\" size=\"2\" />";
					echo "</div><div><a href=\"forums.php?fid=".$forum['fid']."\">".$forum['name']."</a></div>";
					echo "</td>\n";
					if($depth == 2)
					{
						echo "</tr></table></td>";
					}
					echo "</tr>\n";

					if(is_array($iforumcache[$forum['fid']]))
					{
						if($depth == 2)
						{
							$altbg = getaltbg();
							echo "<tr>\n";
							echo "<td class=\"$altbg\" width=\"10\">&nbsp;</td>\n";
							echo "<td class=\"$altbg\" colspan=\"2\"><small>";
							echo "Sub Forums: ";
						}
						getforums($forum['fid'], $depth+1);
						if($depth == 2)
						{
							echo "</small></td></tr>\n";
						}
					}
				}
				elseif($forum['type'] == "f" && ($depth == 1 || $depth == 2))
				{
					$altbg = getaltbg();
					echo "<tr>\n";
					if($depth == 1)
					{
						echo "<td class=\"$altbg\" colspan=\"2\">";
					}
					else
					{
						echo "<td class=\"$altbg\" width=\"10\">&nbsp;</td>\n";
						echo "<td class=\"$altbg\">";
					}
					echo "<b><a href=\"forums.php?fid=".$forum['fid']."\">".$forum['name']."</a></b><br /><span class=\"smalltext\">".$forum['description'];
					if(is_array($iforumcache[$forum['fid']]) && $depth == 2)
					{
						$comma = "";
						echo "<br /><br />Sub Forums: ";
						getforums($forum['fid'], $depth+1);
					}
					echo "</span></td>\n";
					echo "<td class=\"$altbg\" align=\"right\">";
					echo "<input type=\"button\" value=\"$lang->getforums_details\" onclick=\"hopto('forums.php?fid=".$forum['fid']."');\" class=\"submitbutton\">";
					echo "<input type=\"button\" value=\"$lang->getforums_edit\" onclick=\"hopto('forums.php?action=edit&fid=".$forum['fid']."');\" class=\"submitbutton\">";
					echo "<input type=\"button\" value=\"$lang->permissions\" onclick=\"hopto('forumpermissions.php?action=quickperms&fid=".$forum['fid']."');\" class=\"submitbutton\">";
					echo "<input type=\"button\" value=\"$lang->getforums_delete\" onclick=\"hopto('forums.php?action=delete&fid=".$forum['fid']."');\" class=\"submitbutton\">";
					echo "&nbsp;<input type=\"textbox\" name=\"disporder[".$forum['fid']."]\" value=\"".$forum['disporder']."\" size=\"2\" />";
					echo "</td>";
					echo "</tr>\n";
					if(is_array($iforumcache[$forum['fid']]) && $depth == 1)
					{
						getforums($forum['fid'], $depth+1);
					}
				}
				elseif($depth == 3)
				{
					echo "$comma <a href=\"forums.php?fid=".$forum['fid']."\">".$forum['name']."</a>";
					$comma = ", ";
				}
			}
		}
	}
}

function makeparentlist($fid, $navsep=",")
{
	global $pforumcache, $db;
	if(!$pforumcache)
	{
		$options = array(
			"order_by" => "disporder, pid"
		);
		$query = $db->simple_select(TABLE_PREFIX."forums", "name, fid, pid", "", $options);
		while($forum = $db->fetch_array($query))
		{
			$pforumcache[$forum[fid]][$forum[pid]] = $forum;
		}
	}
	reset($pforumcache);
	reset($pforumcache[$fid]);
	while(list($key, $forum) = each($pforumcache[$fid]))
	{
		if($fid == $forum[fid])
		{
			if($pforumcache[$forum[pid]])
			{
				$navigation = makeparentlist($forum[pid], $navsep) . $navigation;
			}
			if($navigation)
			{
				$navigation .= $navsep;
			}
			$navigation .= "$forum[fid]";
		}
	}
	return $navigation;
}
if($mybb->input['action'] == "do_add")
{
	$pid = intval($mybb->input['pid']);
	if($mybb->input['isforum'] == "no")
	{
		$type = "c";
	}
	else
	{
		$type = "f";
	}
	if($mybb->input['pid'] == 0 && $type == "f")
	{
		cperror($lang->forum_noparent);
	}
	$sqlarray = array(
		"name" => $db->escape_string($mybb->input['name']),
		"description" => $db->escape_string($mybb->input['description']),
		"linkto" => $db->escape_string($mybb->input['linkto']),
		"type" => $type,
		"pid" => $pid,
		"disporder" => intval($mybb->input['disporder']),
		"active" => $db->escape_string($mybb->input['isactive']),
		"open" => $db->escape_string($mybb->input['isopen']),
		"threads" => '0',
		"posts" => '0',
		"lastpost" => '0',
		"lastposter" => '0',
		"allowhtml" => $db->escape_string($mybb->input['allowhtml']),
		"allowmycode" => $db->escape_string($mybb->input['allowmycode']),
		"allowsmilies" => $db->escape_string($mybb->input['allowsmilies']),
		"allowimgcode" => $db->escape_string($mybb->input['allowimgcode']),
		"allowpicons" => $db->escape_string($mybb->input['allowpicons']),
		"allowtratings" => $db->escape_string($mybb->input['allowtratings']),
		"usepostcounts" => $db->escape_string($mybb->input['usepostcounts']),
		"password" => $db->escape_string($mybb->input['password']),
		"showinjump" => $db->escape_string($mybb->input['showinjump']),
		"modposts" => $db->escape_string($mybb->input['modposts']),
		"modthreads" => $db->escape_string($mybb->input['modthreads']),
		"modattachments" => $db->escape_string($mybb->input['modattachments']),
		"style" => $db->escape_string($mybb->input['fstyle']),
		"overridestyle" => $db->escape_string($mybb->input['overridestyle']),
		"rulestype" => $db->escape_string($mybb->input['rulestype']),
		"rulestitle" => $db->escape_string($mybb->input['rulestitle']),
		"rules" => $db->escape_string($mybb->input['rules']),
		"defaultdatecut" => intval($mybb->input['defaultdatecut']),
		"defaultsortby" => $db->escape_string($mybb->input['defaultsortby']),
		"defaultsortorder" => $db->escape_string($mybb->input['defaultsortorder']),
		);
	$db->insert_query(TABLE_PREFIX."forums", $sqlarray);
	$plugins->run_hooks("admin_forums_do_add");
	$fid = $db->insert_id();
	$parentlist = makeparentlist($fid);
	$updatearray = array(
		"parentlist" => "$parentlist"
	);
	$db->update_query(TABLE_PREFIX."forums", $updatearray, "fid='$fid'");
	$inherit = $mybb->input['inherit'];
	$canview = $mybb->input['canview'];
	$canpostthreads = $mybb->input['canpostthreads'];
	$canpostreplies = $mybb->input['canpostreplies'];
	$canpostpolls = $mybb->input['canpostpolls'];
	$canpostattachments = $mybb->input['canpostattachments'];
	savequickperms($fid);
	$cache->updateforums();
	$cache->updateforumpermissions();

	cpredirect("forums.php", $lang->forum_added);
}
if($mybb->input['action'] == "do_addmod")
{
	$options = array(
		"limit" => "1"
	);
	$query = $db->simple_select(TABLE_PREFIX."users", "uid", "username='".$db->escape_string($mybb->input['username'])."'", $options);
	$user = $db->fetch_array($query);
	if($user['uid'])
	{
		$fid = intval($mybb->input['fid']);
		$options = array(
			"limit" => "1"
		);
		$query = $db->simple_select(TABLE_PREFIX."moderators", "uid", "uid='".$user['uid']."' AND fid='".$fid."'", $options);
		$mod = $db->fetch_array($query);
		if(!$mod['uid'])
		{
			$caneditposts = $db->escape_string($mybb->input['caneditposts']);
			$candeleteposts = $db->escape_string($mybb->input['candeleteposts']);
			$canviewips = $db->escape_string($mybb->input['canviewips']);
			$canopenclosethreads = $db->escape_string($mybb->input['canopenclosethreads']);
			$canmanagethreads = $db->escape_string($mybb->input['canmanagethreads']);
			$canmovetononmodforum = $db->escape_string($mybb->input['canmovetononmodforum']);
			$newmod = array(
				"fid" => $fid,
				"uid" => $user['uid'],
				"caneditposts" => $caneditposts,
				"candeleteposts" => $candeleteposts,
				"canviewips" => $canviewips,
				"canopenclosethreads" => $canopenclosethreads,
				"canmanagethreads" => $canmanagethreads,
				"canmovetononmodforum" => $canmovetononmodforum
				);
			$plugins->run_hooks("admin_forums_do_addmod");
			$db->insert_query(TABLE_PREFIX."moderators", $newmod);
			$updatequery = array(
				"usergroup" => "6"
			);
			$db->insert_query(TABLE_PREFIX."users", $updatequery, "uid='$user[uid]' AND usergroup='2'");
			$cache->updatemoderators();
			cpredirect("forums.php?fid=$fid", $lang->mod_added);
		}
		else
		{
			cpredirect("forums.php?fid=$fid", $lang->mod_alreadyismod);
		}
	}
	else
	{
		cpredirect("forums.php?action=addmod", $lang->mod_user_notfound);
	}
	$noheader = 1;
}
if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{
		$fid = intval($mybb->input['fid']);
		$db->delete_query(TABLE_PREFIX."forums", "fid='$fid'");
		$query = $db->simple_select(TABLE_PREFIX."forums", "*", "CONCAT(',', parentlist, ',') LIKE '%,$fid,%'");
		while($f = $db->fetch_array($query))
		{
			$fids[$f['fid']] = $fid;
			$delquery .= " OR fid='$f[fid]'";
		}
		
		$plugins->run_hooks("admin_forums_do_delete");

		/**
		 * This slab of code pulls out the moderators for this forum,
		 * checks if they moderate any other forums, and if they don't
		 * it moves them back to the registered usergroup
		 */

		$query = $db->query(TABLE_PREFIX."moderators", "*", "fid='$fid'");
		while($mod = $db->fetch_array($query))
		{
			$moderators[$mod['uid']] = $mod['uid'];
		}
		if(is_array($moderators))
		{
			$mod_list = implode(",", $moderators);
			$query = $db->simple_select(TABLE_PREFIX."moderators", "*", "fid != '$fid' AND uid IN ($mod_list)");
			while($mod = $db->fetch_array($query))
			{
				unset($moderators[$mod['uid']]);
			}
		}
		if(is_array($moderators))
		{
			$mod_list = implode(",", $moderators);
			if($mod_list)
			{
				$updatequery = array(
					"usergroup" => "2"
				);
				$db->update_query(TABLE_PREFIX."usergroups", $updatequery, "uid IN ($mod_list) AND usergroup='6'");
			}
		}
		$db->delete_query(TABLE_PREFIX."forums", "CONCAT(',',parentlist,',') LIKE '%,$fid,%'");
		$db->delete_query(TABLE_PREFIX."threads", "fid='$fid' $delquery");
		$db->delete_query(TABLE_PREFIX."posts", "fid='$fid' $delquery");
		$db->delete_query(TABLE_PREFIX."moderators", "fid='$fid' $delquery");

		$cache->updateforums();
		$cache->updatemoderators();
		$cache->updateforumpermissions();

		cpredirect("forums.php", $lang->forum_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "do_deletemod")
{
	if($mybb->input['deletesubmit'])
	{
		$mid = intval($mybb->input['mid']);
		$query = $db->simple_select(TABLE_PREFIX."moderators m LEFT JOIN ".TABLE_PREFIX."users u ON u.uid=m.uid", "m.*, u.usergroup", "m.mid='$mid'");
		$mod = $db->fetch_array($query);
		$plugins->run_hooks("admin_forums_do_deletemod");
		$db->delete_query(TABLE_PREFIX."moderators", "mid='$mid'");
		$query = $db->simple_select(TABLE_PREFIX."moderators", "*", "uid='$mod[uid]'");
		if($db->fetch_array($query))
		{
			$updatequery = array(
				"usergroup" => "2"
			);
			$db->update_query(TABLE_PREFIX."users", $updatequery, "uid='$mod[uid]' AND usergroup!='4' AND usergroup!='3'");
		}
		$cache->updatemoderators();
		cpredirect("forums.php?fid=$fid", $lang->mod_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "do_edit")
{
	$fid = intval($mybb->input['fid']);
	$pid = intval($mybb->input['pid']);

	$query = $db->simple_select(TABLE_PREFIX."forums", "*", "fid='".$fid."'");
	$forum = $db->fetch_array($query);

	if($pid == $fid)
	{
		cpmessage($lang->forum_parent_itself);
	}
	else
	{
		$query = $db->simple_select(TABLE_PREFIX."forums", "*", "pid='".$fid."'");
		while($child = $db->fetch_array($query))
		{
			if($child['fid'] == $pid)
			{
				cpmessage($lang->forum_parent_child);
			}
		}
		if($mybb->input['isforum'] == "no")
		{
			$type = "c";
		}
		else
		{
			$type = "f";
		}

		if($mybb->input['pid'] == 0 && $type == "f")
		{
			cperror($lang->forum_noparent);
		}

		$sqlarray = array(
			"name" => $db->escape_string($mybb->input['name']),
			"description" => $db->escape_string($mybb->input['description']),
			"linkto" => $db->escape_string($mybb->input['linkto']),
			"type" => $type,
			"pid" => $pid,
			"disporder" => intval($mybb->input['disporder']),
			"active" => $db->escape_string($mybb->input['isactive']),
			"open" => $db->escape_string($mybb->input['isopen']),
			"allowhtml" => $db->escape_string($mybb->input['allowhtml']),
			"allowmycode" => $db->escape_string($mybb->input['allowmycode']),
			"allowsmilies" => $db->escape_string($mybb->input['allowsmilies']),
			"allowimgcode" => $db->escape_string($mybb->input['allowimgcode']),
			"allowpicons" => $db->escape_string($mybb->input['allowpicons']),
			"allowtratings" => $db->escape_string($mybb->input['allowtratings']),
			"usepostcounts" => $db->escape_string($mybb->input['usepostcounts']),
			"password" => $db->escape_string($mybb->input['password']),
 			"showinjump" => $db->escape_string($mybb->input['showinjump']),
			"modposts" => $db->escape_string($mybb->input['modposts']),
			"modthreads" => $db->escape_string($mybb->input['modthreads']),
			"modattachments" => $db->escape_string($mybb->input['modattachments']),
			"style" => intval($mybb->input['fstyle']),
			"overridestyle" => $db->escape_string($mybb->input['overridestyle']),
			"rulestype" => $db->escape_string($mybb->input['rulestype']),
			"rulestitle" => $db->escape_string($mybb->input['rulestitle']),
			"rules" => $db->escape_string($mybb->input['rules']),
			"defaultdatecut" => intval($mybb->input['defaultdatecut']),
			"defaultsortby" => $db->escape_string($mybb->input['defaultsortby']),
			"defaultsortorder" => $db->escape_string($mybb->input['defaultsortorder']),
			);
		$plugins->run_hooks("admin_forums_do_edit");
		$db->update_query(TABLE_PREFIX."forums", $sqlarray, "fid='$fid'", 1);
		if($pid != $forum['pid'])
		{
			// Update the parentlist of this forum.
			$sql_array = array(
				"parentlist" => makeparentlist($fid),
				);
			$db->update_query(TABLE_PREFIX."forums", $sql_array, "fid='$fid'", 1);
			// Rebuild the parentlist of all of the subforums of this forum
			$query = $db->simple_select(TABLE_PREFIX."forums", "fid", "CONCAT(',',parentlist,',') LIKE '%,$fid,%'");
			while($childforum = $db->fetch_array($query))
			{
				$sql_array = array(
					"parentlist" => makeparentlist($childforum['fid']),
					);
				$db->update_query(TABLE_PREFIX."forums", $sql_array, "fid='".$childforum['fid']."'", 1);
			}
		}
		$cache->updateforums();
		$cache->updateforumpermissions();

		cpredirect("forums.php", $lang->forum_updated);
	}
}
if($mybb->input['action'] == "do_editmod")
{
	cpheader();
	$username = $db->escape_string($mybb->input['username']);
	$fid = intval($mybb->input['fid']);

	$query = $db->simple_select(TABLE_PREFIX."users", "uid", "username='$username'");
	$user = $db->fetch_array($query);
	if($user['uid'])
	{
		$sqlarray = array(
			"fid" => intval($mybb->input['fid']),
			"uid" => $user['uid'],
			"caneditposts" => $db->escape_string($mybb->input['caneditposts']),
			"candeleteposts" => $db->escape_string($mybb->input['candeleteposts']),
			"canviewips" => $db->escape_string($mybb->input['canviewips']),
			"canopenclosethreads" => $db->escape_string($mybb->input['canopenclosethreads']),
			"canmanagethreads" => $db->escape_string($mybb->input['canmanagethreads']),
			"canmovetononmodforum" => $db->escape_string($mybb->input['canmovetononmodforum'])
			);
		$plugins->run_hooks("admin_forums_do_editmod");
		$db->update_query(TABLE_PREFIX."moderators", $sqlarray, "mid='".intval($mybb->input['mid'])."'");
		$cache->updatemoderators();
		cpredirect("forums.php?fid=$fid", $lang->mod_updated);
	}
	else
	{
		cpmessage($lang->mod_user_notfound);
	}
}

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_forums_add");
	cpheader();
	startform("forums.php", "" , "do_add");
	starttable();
	tableheader($lang->add_forum);
	makeinputcode($lang->name, "name", $mybb->input['fname']);
	maketextareacode($lang->description, "description");
	makeinputcode($lang->forumlink, "linkto");
	makeinputcode($lang->disporder, "disporder", "1", "4");
	makelabelcode($lang->parentforum, forumselect("pid", intval($mybb->input['pid'])));

	tablesubheader($lang->access_perm_options);
	makeinputcode($lang->forum_password, "password");

	tablesubheader($lang->posting_options);
	if($mybb->input['type'] == 'c')
	{
		$typesel = "no";
	}
	else
	{
		$typesel = "yes";
	}
	makeyesnocode($lang->act_as_forum, "isforum", $typesel);
	makeyesnocode($lang->forum_active, "isactive", "yes");
	makeyesnocode($lang->forum_open, "isopen", "yes");

	tablesubheader($lang->moderation_options);
	makeyesnocode($lang->moderate_posts, "modposts", "no");
	makeyesnocode($lang->moderate_threads, "modthreads", "no");
	makeyesnocode($lang->moderate_attachments, "modattachments", "no");

	tablesubheader($lang->style_options);
	makeselectcode($lang->style, "fstyle", "themes", "tid", "name", "0", $lang->use_default, "", "name!='((master))' AND name!='((master-backup))'");
	makeyesnocode($lang->override_style, "overridestyle", "no");

	tablesubheader($lang->forum_rules);
	makelabelcode($lang->rules_display_method, "<select name=\"rulestype\"><option value=\"0\">".$lang->dont_display_rules."</option><option value=\"1\">".$lang->display_rules_inline."</option><option value=\"2\">".$lang->display_rules_link."</option></select>");
	makeinputcode($lang->rules_title, "rulestitle");
	maketextareacode($lang->rules, "rules");

	tablesubheader($lang->default_viewing_options);
	$datecut_array = array(
		1 => $lang->datelimit_1day,
		5 => $lang->datelimit_5days,
		10 => $lang->datelimit_10days,
		20 => $lang->datelimit_20days,
		50 => $lang->datelimit_50days,
		75 => $lang->datelimit_75days,
		100 => $lang->datelimit_100days,
		365 => $lang->datelimit_lastyear,
		9999 => $lang->datelimit_beginning,
		);
	makeselectcode_array($lang->default_datecut, "defaultdatecut", $datecut_array, "", true, $lang->board_default);
	$sortby_array = array(
		"subject" => $lang->sort_by_subject,
		"lastpost" => $lang->sort_by_lastpost,
		"starter" => $lang->sort_by_starter,
		"started" => $lang->sort_by_started,
		"rating" => $lang->sort_by_rating,
		"replies" => $lang->sort_by_replies,
		"views" => $lang->sort_by_views,
		);
	makeselectcode_array($lang->default_sortby, "defaultsortby", $sortby_array, "", true, $lang->board_default);
	$sortorder_array = array(
		"asc" => $lang->sort_order_asc,
		"desc" => $lang->sort_order_desc,
		);
	makeselectcode_array($lang->default_sortorder, "defaultsortorder", $sortorder_array, "", true, $lang->board_default);

	tablesubheader($lang->misc_options);
	makeyesnocode($lang->allow_html, "allowhtml", "no");
	makeyesnocode($lang->allow_mycode, "allowmycode", "yes");
	makeyesnocode($lang->allow_smilies, "allowsmilies", "yes");
	makeyesnocode($lang->allow_img_code, "allowimgcode", "yes");
	makeyesnocode($lang->allow_posticons, "allowpicons", "yes");
	makeyesnocode($lang->allow_ratings, "allowtratings", "yes");
	makeyesnocode($lang->show_forum_jump, "showinjump");
	makeyesnocode($lang->use_postcounts, "usepostcounts", "yes");
	endtable();
	echo "<br />";
	quickpermissions("", $pid);
	endform($lang->add_forum, $lang->reset_button);
	cpfooter();

}
if($mybb->input['action'] == "addmod")
{
	$plugins->run_hooks("admin_forums_addmod");
	if(!$noheader)
	{
		cpheader();
	}
	startform("forums.php", "", "do_addmod");
	starttable();
	tableheader($lang->add_moderator);
	makeinputcode($lang->username, "username");
	makelabelcode($lang->forum, forumselect("fid", intval($mybb->input['fid'])));
	tablesubheader($lang->mod_perms);
	makeyesnocode($lang->caneditposts, "caneditposts", "yes");
	makeyesnocode($lang->candeleteposts, "candeleteposts", "yes");
	makeyesnocode($lang->canviewips, "canviewips", "yes");
	makeyesnocode($lang->canopenclose, "canopenclosethreads", "yes");
	makeyesnocode($lang->canmanage, "canmanagethreads", "yes");
	makeyesnocode($lang->canmovetononmodforum, "canmovetononmodforum", "yes");
	endtable();
	endform($lang->add_moderator, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "delete")
{
	$fid = intval($mybb->input['fid']);
	$query = $db->simple_select(TABLE_PREFIX."forums", "*", "fid='$fid'");
	$forum = $db->fetch_array($query);
	$plugins->run_hooks("admin_forums_delete");
	cpheader();
	startform("forums.php", "", "do_delete");
	makehiddencode("fid", $fid);
	starttable();
	$lang->delete_forum = sprintf($lang->delete_forum, $forum['name']);
	tableheader($lang->delete_forum, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	$lang->delete_forum_confirm = sprintf($lang->delete_forum_confirm, $forum['name']);
	makelabelcode("<div align=\"center\">$lang->delete_forum_confirm<br /><br />$yes$no</div>", "");
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "deletemod")
{
	
	$mid = intval($mybb->input['mid']);
	$fid = intval($mybb->input['fid']);
	$plugins->run_hooks("admin_forums_deletemod");
	cpheader();
	startform("forums.php", "", "do_deletemod");
	makehiddencode("mid", $mid);
	makehiddencode("fid", $fid);
	starttable();
	tableheader($lang->delete_mod, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<div align=\"center\">$lang->delete_mod_confirm<br /><br />$yes$no</div>", "");
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "edit")
{
	if(!$noheader)
	{
		cpheader();
	}
	$fid = intval($mybb->input['fid']);
	$query = $db->simple_select(TABLE_PREFIX."forums", "*", "fid='$fid'");
	$forum = $db->fetch_array($query);
	$forum['description'] = $forum['description'];
	$pid = $forum['pid'];
	if($forum[type] == "c")
	{
		$isforum = "no";
	}
	else
	{
		$isforum = "yes";
	}
	$plugins->run_hooks("admin_forums_edit");
	startform("forums.php", "", "do_edit");
	makehiddencode("fid", $fid);
	starttable();
	$lang->edit_forum = sprintf($lang->edit_forum, $forum['name']);
	tableheader($lang->edit_forum);
	makeinputcode($lang->name, "name", $forum[name]);
	maketextareacode($lang->description, "description", $forum['description']);
	makeinputcode($lang->forumlink, "linkto", $forum['linkto']);
	makeinputcode($lang->disporder, "disporder", "$forum[disporder]", "4");
	makelabelcode($lang->parentforum, forumselect("pid", $forum['pid']));

	tablesubheader($lang->access_perm_options);
	makeinputcode($lang->forum_password, "password", $forum['password']);

	tablesubheader($lang->posting_options);
	makeyesnocode($lang->act_as_forum, "isforum", $isforum);
	makeyesnocode($lang->forum_active, "isactive", $forum['active']);
	makeyesnocode($lang->forum_open, "isopen", $forum['open']);

	tablesubheader($lang->moderation_options);
	makeyesnocode($lang->moderate_posts, "modposts", $forum['modposts']);
	makeyesnocode($lang->moderate_threads, "modthreads", $forum['modthreads']);
	makeyesnocode($lang->moderate_attachments, "modattachments", $forum['modattachments']);

	tablesubheader($lang->style_options);
	if(!$forum['style'])
	{
		$forum['style'] = "0";
	}
	makeselectcode($lang->style, "fstyle", "themes", "tid", "name", $forum['style'], $lang->use_default, "", "name!='((master))' AND name!='((master-backup))'");
	makeyesnocode($lang->override_style, "overridestyle", $forum['overridestyle']);

	tablesubheader($lang->forum_rules);
	if($forum['rulestype'] == 1)
	{
		$rulesdispin = "selected=\"selected\"";
	}
	elseif($forum['rulestype'] == 2)
	{
		$rulesdisplink = "selected=\"selected\"";
	}
	else
	{
		$rulesnodisp = "selected=\"selected\"";
	}
	makelabelcode($lang->rules_display_method, "<select name=\"rulestype\"><option value=\"0\" $rulesnodisp>".$lang->dont_display_rules."</option><option value=\"1\" $rulesdispin>".$lang->display_rules_inline."</option><option value=\"2\" $rulesdisplink>".$lang->display_rules_link."</option></select>");
	makeinputcode($lang->rules_title, "rulestitle", $forum['rulestitle']);
	maketextareacode($lang->rules, "rules", $forum['rules']);

	tablesubheader($lang->default_viewing_options);
	$datecut_array = array(
		1 => $lang->datelimit_1day,
		5 => $lang->datelimit_5days,
		10 => $lang->datelimit_10days,
		20 => $lang->datelimit_20days,
		50 => $lang->datelimit_50days,
		75 => $lang->datelimit_75days,
		100 => $lang->datelimit_100days,
		365 => $lang->datelimit_lastyear,
		1000 => $lang->datelimit_beginning,
		);
	makeselectcode_array($lang->default_datecut, "defaultdatecut", $datecut_array, $forum['defaultdatecut'], true, $lang->board_default);
	$sortby_array = array(
		"subject" => $lang->sort_by_subject,
		"lastpost" => $lang->sort_by_lastpost,
		"starter" => $lang->sort_by_starter,
		"started" => $lang->sort_by_started,
		"rating" => $lang->sort_by_rating,
		"replies" => $lang->sort_by_replies,
		"views" => $lang->sort_by_views,
		);
	makeselectcode_array($lang->default_sortby, "defaultsortby", $sortby_array, $forum['defaultsortby'], true, $lang->board_default);
	$sortorder_array = array(
		"asc" => $lang->sort_order_asc,
		"desc" => $lang->sort_order_desc,
		);
	makeselectcode_array($lang->default_sortorder, "defaultsortorder", $sortorder_array, $forum['defaultsortorder'], true, $lang->board_default);

	tablesubheader($lang->misc_options);
	makeyesnocode($lang->allow_html, "allowhtml", $forum['allowhtml']);
	makeyesnocode($lang->allow_mycode, "allowmycode", $forum['allowmycode']);
	makeyesnocode($lang->allow_smilies, "allowsmilies", $forum['allowsmilies']);
	makeyesnocode($lang->allow_img_code, "allowimgcode", $forum['allowimgcode']);
	makeyesnocode($lang->allow_posticons, "allowpicons", $forum['allowpicons']);
	makeyesnocode($lang->allow_ratings, "allowtratings", $forum['allowtratings']);
	makeyesnocode($lang->show_forum_jump, "showinjump", $forum['showinjump']);
	makeyesnocode($lang->use_postcounts, "usepostcounts", $forum['usepostcounts']);
	endtable();
	endform($lang->update_forum, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "editmod")
{
	if(!$noheader)
	{
		cpheader();
	}
	$mid = intval($mybb->input['mid']);
	$fid = intval($mybb->input['fid']);
	$query = $db->simple_select(TABLE_PREFIX."moderators m LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=m.uid)", "m.*, u.username", "m.mid='$mid'");
	$moderator = $db->fetch_array($query);
	$plugins->run_hooks("admin_forums_editmod");
	startform("forums.php", "", "do_editmod");
	makehiddencode("mid", $mid);
	makehiddencode("fid", $fid);
	starttable();
	tableheader($lang->edit_moderator);
	makeinputcode($lang->username, "username", $moderator['username']);
	makelabelcode($lang->forum, forumselect("fid", $moderator['fid']));
	tablesubheader($lang->mod_perms);
	makeyesnocode($lang->caneditposts, "caneditposts", $moderator['caneditposts']);
	makeyesnocode($lang->candeleteposts, "candeleteposts", $moderator['candeleteposts']);
	makeyesnocode($lang->canviewips, "canviewips", $moderator['canviewips']);
	makeyesnocode($lang->canopenclose, "canopenclosethreads", $moderator['canopenclosethreads']);
	makeyesnocode($lang->canmanage, "canmanagethreads", $moderator['canmanagethreads']);
	makeyesnocode($lang->canmovetononmodforum, "canmovetononmodforum", $moderator['canmovetononmodforum']);
	endtable();
	endform($lang->update_moderator, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "do_modify")
{
	$plugins->run_hooks("admin_forums_do_modify");
	while(list($fid, $order) = each($mybb->input['disporder']))
	{
		$fid = intval($fid);
		$order = intval($order);
		$updatequery = array(
			"disporder" => $order
		);
		$db->update_query(TABLE_PREFIX."forums", $updatequery, "fid='$fid'");
	}
	$cache->updateforums();
	cpredirect("forums.php", $lang->orders_updated);
}

if($mybb->input['action'] == "do_copy") // Actually copy the forum
{
	$plugins->run_hooks("admin_forums_do_copy");
	$from = intval($mybb->input['from']);
	$to = intval($mybb->input['to']);

	// Find the source forum
	$query = $db->simple_select(TABLE_PREFIX."forums", '*', "fid='{$from}'");
	$from_forum = $db->fetch_array($query);
	if(!$db->num_rows($query))
	{
		cperror($lang->invalid_source_forum);
	}

	if($to == -1)
	{
		// Create a new forum
		if(empty($mybb->input['name']))
		{
			cperror($lang->new_forum_needs_name);
		}
		if($mybb->input['isforum'] == 'no')
		{
			$type = 'c';
		}
		else
		{
			$type = 'f';
		}
		if($mybb->input['pid'] == 0 && $type == 'f')
		{
			cperror($lang->forum_noparent);
		}

		$new_forum = $from_forum;
		unset($new_forum['fid']);
		$new_forum['name'] = $db->escape_string($mybb->input['name']);
		$new_forum['description'] = $db->escape_string($mybb->input['description']);
		$new_forum['type'] = $type;
		$new_forum['pid'] = intval($mybb->input['pid']);
		
		$db->insert_query(TABLE_PREFIX."forums", $new_forum);
		$to = $db->insert_id();

		// Generate parent list
		$parentlist = makeparentlist($to);
		$updatearray = array(
			'parentlist' => $parentlist
		);
		$db->update_query(TABLE_PREFIX."forums", $updatearray, "fid='{$to}'");
	}
	elseif($mybb->input['copyforumsettings'] == "yes")
	{
		// Copy settings to existing forum
		$query = $db->simple_select(TABLE_PREFIX."forums", '*', "fid='{$to}'");
		$to_forum = $db->fetch_array($query);
		if(!$db->num_rows($query))
		{
			cperror($lang->invalid_destination_forum);
		}

		$new_forum = $from_forum;
		unset($new_forum['fid']);
		$new_forum['name'] = $db->escape_string($to_forum['name']);
		$new_forum['description'] = $db->escape_string($to_forum['description']);
		$new_forum['pid'] = $db->escape_string($to_forum['pid']);
		$new_forum['parentlist'] = $db->escape_string($to_forum['parentlist']);

		$db->update_query(TABLE_PREFIX."forums", $new_forum, "fid='{$to}'");
	}
	
	// Copy permissions
	if(is_array($mybb->input['copygroups']) && count($mybb->input['copygroups'] > 0))
	{
		foreach($mybb->input['copygroups'] as $gid)
		{
			$groups[] = intval($gid);
		}
		$groups = implode(',', $groups);
		$query = $db->simple_select(TABLE_PREFIX."forumpermissions", '*', "fid='{$from}' AND gid IN ({$groups})");
		$db->delete_query(TABLE_PREFIX."forumpermissions", "fid='{$to}' AND gid IN ({$groups})", 1);
		while($permissions = $db->fetch_array($query))
		{
			unset($permissions['pid']);
			$permissions['fid'] = $to;

			$db->insert_query(TABLE_PREFIX."forumpermissions", $permissions);
		}
	}
	$cache->updateforums();
	$cache->updateforumpermissions();

	cpmessage($lang->copy_successful);
}

if($mybb->input['action'] == "copy") // Show the copy forum form
{
	$plugins->run_hooks("admin_forums_copy");
	$from = intval($mybb->input['from']);
	$to = intval($mybb->input['to']);
	if(!$noheader)
	{
		cpheader();
	}
	startform('forums.php', '', 'do_copy');
	starttable();
	tableheader($lang->copy_forum);
	makelabelcode($lang->copy_forum_note, '', 2);
	makelabelcode($lang->source_forum, forumselect('from', $from, '', '', 0));
	unset($forumselect);
	makelabelcode($lang->destination_forum, forumselect('to', $to, '', '', 0, $lang->no_copy_to_existing));

	tablesubheader($lang->copy_to_new_forum);
	makeinputcode($lang->name, 'name');
	maketextareacode($lang->description, 'description');
	unset($forumselect);
	makelabelcode($lang->parentforum, forumselect('pid'));
	makeyesnocode($lang->act_as_forum, 'isforum', 'yes');

	tablesubheader($lang->copy_settings);
	if(!isset($mybb->input['copyforumsettings']))
	{
		$mybb->input['copyforumsettings'] = 'yes';
	}
	makeyesnocode($lang->copy_forum_settings, 'copyforumsettings', $mybb->input['copyforumsettings']);
	$query = $db->query("SELECT gid, title FROM ".TABLE_PREFIX."usergroups ORDER BY title ASC");
	while($usergroup = $db->fetch_array($query))
	{
		$selected = '';
		if($mybb->input['copygroups'] == 'all' || (is_array($mybb->input['copygroups']) && in_array($usergroup['gid'], $mybb->input['copygroups'])))
		{
			$selected = ' checked="checked"';
		}
		$group_list[] = "<input type=\"checkbox\" name=\"copygroups[]\" value=\"$usergroup[gid]\"$selected /> $usergroup[title]";
	}
	$group_list = implode("<br />\n", $group_list);
	makelabelcode($lang->copy_usergroups, "<small>$group_list</small>");
	endtable();
	endform($lang->copy_forum_button, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "modify" || $mybb->input['action'] == "")
{
	$plugins->run_hooks("admin_forums_modify");
	cpheader();
	$fid = intval($mybb->input['fid']);
	if($fid)
	{
		$query = $db->simple_select(TABLE_PREFIX."forums f LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = f.lastposttid)", "f.*, t.subject AS lastpostsubject", "f.fid='$fid'");
		$forum = $db->fetch_array($query);

		$query = $db->simple_select(TABLE_PREFIX."forumpermissions", "*", "fid='$fid'");
		while($fperm = $db->fetch_array($query))
		{
			$fperms[$fperm[gid]] = $fperm;
		}

		$hopto[] = "<input type=\"button\" value=\"$lang->add_child_forum\" onclick=\"hopto('forums.php?action=add&pid=$fid');\" class=\"hoptobutton\">";
		$hopto[] = "<input type=\"button\" value=\"$lang->edit_forum_settings\" onclick=\"hopto('forums.php?action=edit&fid=$fid');\" class=\"hoptobutton\">";
		$hopto[] = "<input type=\"button\" value=\"$lang->delete_forum2\" onclick=\"hopto('forums.php?action=delete&fid=$fid');\" class=\"hoptobutton\">";
		$hopto[] = "<input type=\"button\" value=\"$lang->copy_forum_button\" onclick=\"hopto('forums.php?action=copy&from=$fid');\" class=\"hoptobutton\">";
		makehoptolinks($hopto);

		$query = $db->simple_select(TABLE_PREFIX."forums", "*", "pid='$fid'");
		$child = $db->fetch_array($query);
		if($child['fid'])
		{
			startform("forums.php", "", "do_modify");
			starttable();
			$lang->forums_in = sprintf($lang->forums_in, $forum['name']);
			tableheader($lang->forums_in, "", 3);
			getforums($fid);
			endtable();
			endform($lang->update_orders, $lang->reset_button);
		}
		starttable("90%", 0, 0);
		echo "<tr>\n";
		echo "<td width=\"49%\" valign=\"top\">\n";
		starttable("100%");
		tableheader($lang->forum_permissions);
		tablesubheader("<div align=\"right\"><input type=\"button\" value=\"$lang->quick_permissions\" onclick=\"hopto('forumpermissions.php?action=quickperms&fid=$fid');\" class=\"submitbutton\"></div>");
		$options = array(
			"order_by" => "title",
			"order_dir" => "ASC"
		);
		$query = $db->simple_select(TABLE_PREFIX."usergroups", "*", "", $options);
		while($usergroup = $db->fetch_array($query))
		{
			if($fperms[$usergroup['gid']])
			{
				$highlight = "highlight1";
				$editset = $lang->edit_permissions;
				$link = "&fid=$fid&pid=".$fperms[$usergroup['gid']]['pid'];
			}
			else
			{
				$sql = build_parent_list($fid);
				$cusquery = $db->simple_select(TABLE_PREFIX."forumpermissions", "*", "$sql AND gid='$usergroup[gid]'");
				$customperms = $db->fetch_array($cusquery);
				if($customperms['gid'])
				{
					$highlight = "highlight2";
				}
				else
				{
					$highlight = "";
				}
				$editset = $lang->set_permissions;
				$link = "&fid=$fid&gid=".$usergroup['gid'];
			}
			makelabelcode("<span class=\"$highlight\">".$usergroup['title']."</span>", "<div align=\"right\"><input type=\"button\" value=\"$editset\" onclick=\"hopto('forumpermissions.php?action=edit$link');\" class=\"submitbutton\"></div>");
		}
		endtable();
		echo "</td>\n";
		echo "<td width=\"2%\">&nbsp;&nbsp;</td>";
		echo "<td width=\"49%\" valign=\"top\" align=\"right\">\n";
		starttable("100%");
		tableheader($lang->forum_moderators);
		makelabelcode($lang->mods_colors_note, '', 2);
		tablesubheader("<div align=\"right\"><input type=\"button\" value=\"$lang->add_mod\" onclick=\"hopto('forums.php?action=addmod&fid=$fid');\" class=\"submitbutton\"></div>");
		$parentlist = build_parent_list($fid, 'm.fid');
		$options = array(
			"order_by" => "u.username"
		);
		$modquery = $db->simple_select(TABLE_PREFIX."moderators m LEFT JOIN ".TABLE_PREFIX."users u ON (m.uid=u.uid)", "m.mid, m.uid, m.fid, u.username", $parentlist, $options);
		$nummods = $db->num_rows($modquery);
		if(!$nummods)
		{
			makelabelcode("<div align=\"center\">".$lang->no_mods_note."</div>", "", 2);
		}
		while($mod = $db->fetch_array($modquery))
		{
			if($mod['fid'] != $fid)
			{
				$mod['username'] = '<span class="highlight2">' . $mod['username'] . '</span>';
			}
			makelabelcode($mod['username'], "<div align=\"right\"><input type=\"button\" value=\"$lang->edit\" onclick=\"hopto('forums.php?action=editmod&mid=".$mod['mid']."');\" class=\"submitbutton\"><input type=\"button\" value=\"$lang->delete\" onclick=\"hopto('forums.php?action=deletemod&fid=".$mod['fid']."&mid=".$mod['mid']."');\" class=\"submitbutton\"></div>");
		}
		endtable();
		echo "</td>\n";
		echo "</tr>";
		endtable();
	}
	else
	{
		$hopto[] = "<input type=\"button\" value=\"$lang->create_new_forum\" onclick=\"hopto('forums.php?action=add');\" class=\"hoptobutton\">";
		$hopto[] = "<input type=\"button\" value=\"$lang->forum_announcements\" onclick=\"hopto('announcements.php');\" class=\"hoptobutton\">";
		$hopto[] = "<input type=\"button\" value=\"$lang->forum_permissions\" onclick=\"hopto('forumpermissions.php');\" class=\"hoptobutton\">";
		$hopto[] = "<input type=\"button\" value=\"$lang->copy_forum_button\" onclick=\"hopto('forums.php?action=copy');\" class=\"hoptobutton\">";
		makehoptolinks($hopto);
		startform("forums.php", "", "do_modify");
		starttable();
		tableheader($lang->forums, "", 3);
		getforums(0);
		endtable();
		endform($lang->update_orders, $lang->reset_button);
	}

	cpfooter();
}

?>