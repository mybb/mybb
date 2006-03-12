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

function getforums($pid=0, $depth=1)
{
	global $db, $iforumcache, $lang, $forumcache, $comma;
	if(!is_array($iforumcache))
	{
		if(!is_array($forumcache))
		{
			cacheforums();
		}
		if(!is_array($forumcache))
		{
			return false;
		}

		reset($forumcache);
		while(list($key, $val) = each($forumcache))
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

function makeparentlist($fid, $navsep=",") {
	global $pforumcache, $db;
	if(!$pforumcache) {
		$query = $db->query("SELECT name, fid, pid FROM ".TABLE_PREFIX."forums ORDER BY disporder, pid");
		while($forum = $db->fetch_array($query)){
			$pforumcache[$forum[fid]][$forum[pid]] = $forum;
		}
	}
	reset($pforumcache);
	reset($pforumcache[$fid]);
	while(list($key, $forum) = each($pforumcache[$fid])) {
		if($fid == $forum[fid]) {
			if($pforumcache[$forum[pid]]){
				$navigation = makeparentlist($forum[pid], $navsep) . $navigation;
			}
			if($navigation) {
				$navigation .= $navsep;
			}
			$navigation .= "$forum[fid]";
		}
	}
	return $navigation;
}
if($mybb->input['action'] == "do_add") {
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
		"name" => addslashes($mybb->input['name']),
		"description" => addslashes($mybb->input['description']),
		"linkto" => addslashes($mybb->input['linkto']),
		"type" => $type,
		"pid" => $pid,
		"disporder" => intval($mybb->input['disporder']),
		"active" => addslashes($mybb->input['isactive']),
		"open" => addslashes($mybb->input['isopen']),
		"threads" => '0',
		"posts" => '0',
		"lastpost" => '0',
		"lastposter" => '0',
		"allowhtml" => addslashes($mybb->input['allowhtml']),
		"allowmycode" => addslashes($mybb->input['allowmycode']),
		"allowsmilies" => addslashes($mybb->input['allowsmilies']),
		"allowimgcode" => addslashes($mybb->input['allowimgcode']),
		"allowpicons" => addslashes($mybb->input['allowpicons']),
		"allowtratings" => addslashes($mybb->input['allowtratings']),
		"usepostcounts" => addslashes($mybb->input['usepostcounts']),
		"password" => addslashes($mybb->input['password']),
		"showinjump" => addslashes($mybb->input['showinjump']),
		"modposts" => addslashes($mybb->input['modposts']),
		"modthreads" => addslashes($mybb->input['modthreads']),
		"modattachments" => addslashes($mybb->input['modattachments']),
		"style" => addslashes($mybb->input['fstyle']),
		"overridestyle" => addslashes($mybb->input['overridestyle']),
		"rulestype" => addslashes($mybb->input['rulestype']),
		"rulestitle" => addslashes($mybb->input['rulestitle']),
		"rules" => addslashes($mybb->input['rules']),
		"defaultdatecut" => intval($mybb->input['defaultdatecut']),
		"defaultsortby" => addslashes($mybb->input['defaultsortby']),
		"defaultsortorder" => addslashes($mybb->input['defaultsortorder']),
		);
	$db->insert_query(TABLE_PREFIX."forums", $sqlarray);
	$fid = $db->insert_id();
	$parentlist = makeparentlist($fid);
	$db->query("UPDATE ".TABLE_PREFIX."forums SET parentlist='$parentlist' WHERE fid='$fid'");
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
if($mybb->input['action'] == "do_addmod") {
	$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['username'])."' LIMIT 1");
	$user = $db->fetch_array($query);
	if($user['uid'])
	{
		$fid = intval($mybb->input['fid']);
		$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."moderators WHERE uid='".$user['uid']."' AND fid='".$fid."' LIMIT 1");
		$mod = $db->fetch_array($query);
		if(!$mod['uid'])
		{
			$caneditposts = addslashes($mybb->input['caneditposts']);
			$candeleteposts = addslashes($mybb->input['candeleteposts']);
			$canviewips = addslashes($mybb->input['canviewips']);
			$canopenclosethreads = addslashes($mybb->input['canopenclosethreads']);
			$canmanagethreads = addslashes($mybb->input['canmanagethreads']);
			$newmod = array(
				"fid" => $fid,
				"uid" => $user['uid'],
				"caneditposts" => $caneditposts,
				"candeleteposts" => $candeleteposts,
				"canviewips" => $canviewips,
				"canopenclosethreads" => $canopenclosethreads,
				"canmanagethreads" => $canmanagethreads
				);

			$db->insert_query(TABLE_PREFIX."moderators", $newmod);

			$db->query("UPDATE ".TABLE_PREFIX."users SET usergroup='6' WHERE uid='$user[uid]' AND usergroup='2'");
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
if($mybb->input['action'] == "do_delete") {
	if($mybb->input['deletesubmit'])
	{	
		$fid = intval($mybb->input['fid']);
		$db->query("DELETE FROM ".TABLE_PREFIX."forums WHERE fid='$fid'");
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE CONCAT(',', parentlist, ',') LIKE '%,$fid,%'");
		while($f = $db->fetch_array($query))
		{
			$fids[$f['fid']] = $fid;
			$delquery .= " OR fid='$f[fid]'";
		}

		/**
		 * This slab of code pulls out the moderators for this forum,
		 * checks if they moderate any other forums, and if they don't
		 * it moves them back to the registered usergroup
		 */

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."moderators WHERE fid='$fid'");
		while($mod = $db->fetch_array($query))
		{
			$moderators[$mod['uid']] = $mod['uid'];
		}
		if(is_array($moderators))
		{
			$mod_list = implode(",", $moderators);
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."moderators WHERE fid!='$fid' AND uid IN ($mod_list)");
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
				$db->query("UPDATE ".TABLE_PREFIX."usergroups SET usergroup='2' WHERE uid IN ($mod_list) AND usergroup='6'");
			}
		}
		$db->query("DELETE FROM ".TABLE_PREFIX."forums WHERE CONCAT(',',parentlist,',') LIKE '%,$fid,%'");
		$db->query("DELETE FROM ".TABLE_PREFIX."threads WHERE fid='$fid' $delquery");
		$db->query("DELETE FROM ".TABLE_PREFIX."posts WHERE fid='$fid' $delquery");
		$db->query("DELETE FROM ".TABLE_PREFIX."moderators WHERE fid='$fid' $delquery");

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

if($mybb->input['action'] == "do_deletemod") {
	if($mybb->input['deletesubmit'])
	{
		$mid = intval($mybb->input['mid']);
		$query = $db->query("SELECT m.*, u.usergroup FROM ".TABLE_PREFIX."moderators m LEFT JOIN ".TABLE_PREFIX."users u ON u.uid=m.uid WHERE m.mid='$mid'");
		$mod = $db->fetch_array($query);
		$db->query("DELETE FROM ".TABLE_PREFIX."moderators WHERE mid='$mid'");
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."moderators WHERE uid='$mod[uid]'");
		if($db->fetch_array($query))
		{
			$db->query("UPDATE ".TABLE_PREFIX."users SET usergroup='2' WHERE uid='$mod[uid]' AND usergroup!='4' AND usergroup!='3'");
		}
		$cache->updatemoderators();
		cpredirect("forums.php?fid=$fid", $lang->mod_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "do_edit") {
	$fid = intval($mybb->input['fid']);
	$pid = intval($mybb->input['pid']);

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='".$fid."'");
	$forum = $db->fetch_array($query);

	if($pid == $fid)
	{
		cpmessage($lang->forum_parent_itself);
	}
	else
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE pid='$fid'");
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
			"name" => addslashes($mybb->input['name']),
			"description" => addslashes($mybb->input['description']),
			"linkto" => addslashes($mybb->input['linkto']),
			"type" => $type,
			"pid" => $pid,
			"disporder" => intval($mybb->input['disporder']),
			"active" => addslashes($mybb->input['isactive']),
			"open" => addslashes($mybb->input['isopen']),
			"allowhtml" => addslashes($mybb->input['allowhtml']),
			"allowmycode" => addslashes($mybb->input['allowmycode']),
			"allowsmilies" => addslashes($mybb->input['allowsmilies']),
			"allowimgcode" => addslashes($mybb->input['allowimgcode']),
			"allowpicons" => addslashes($mybb->input['allowpicons']),
			"allowtratings" => addslashes($mybb->input['allowtratings']),
			"usepostcounts" => addslashes($mybb->input['usepostcounts']),
			"password" => addslashes($mybb->input['password']),
 			"showinjump" => addslashes($mybb->input['showinjump']),
			"modposts" => addslashes($mybb->input['modposts']),
			"modthreads" => addslashes($mybb->input['modthreads']),
			"modattachments" => addslashes($mybb->input['modattachments']),
			"style" => intval($mybb->input['fstyle']),
			"overridestyle" => addslashes($mybb->input['overridestyle']),
			"rulestype" => addslashes($mybb->input['rulestype']),
			"rulestitle" => addslashes($mybb->input['rulestitle']),
			"rules" => addslashes($mybb->input['rules']),
			"defaultdatecut" => intval($mybb->input['defaultdatecut']),
			"defaultsortby" => addslashes($mybb->input['defaultsortby']),
			"defaultsortorder" => addslashes($mybb->input['defaultsortorder']),
			);
			
		$db->update_query(TABLE_PREFIX."forums", $sqlarray, "fid='$fid'", 1);
		if($pid != $forum['pid'])
		{
				$sql_array = array(
					"parentlist" => makeparentlist($fid),
					);
				$db->update_query(TABLE_PREFIX."forums", $sql_array, "fid='$fid'", 1);
			// Rebuild the parentlist of all of the forums this forum was a parent of
			$query = $db->query("SELECT fid FROM ".TABLE_PREFIX."forums WHERE CONCAT(',',parentlist,',') LIKE '%,$fid,%'");
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
if($mybb->input['action'] == "do_editmod") {
	cpheader();
	$username = addslashes($mybb->input['username']);
	$fid = intval($mybb->input['fid']);

	$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username='$username'");
	$user = $db->fetch_array($query);
	if($user['uid'])
	{
		$sqlarray = array(
			"fid" => intval($mybb->input['fid']),
			"uid" => $user['uid'],
			"caneditposts" => addslashes($mybb->input['caneditposts']),
			"candeleteposts" => addslashes($mybb->input['candeleteposts']),
			"canviewips" => addslashes($mybb->input['canviewips']),
			"canopenclosethreads" => addslashes($mybb->input['canopenclosethreads']),
			"canmanagethreads" => addslashes($mybb->input['canmanagethreads']),
			);

		$db->update_query(TABLE_PREFIX."moderators", $sqlarray, "mid='".intval($mybb->input['mid'])."'");
		$cache->updatemoderators();
		cpredirect("forums.php?fid=$fid", $lang->mod_updated);
	}
	else
	{
		cpmessage($lang->mod_user_notfound);
	}
}

if($mybb->input['action'] == "add") {
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
		1000 => $lang->datelimit_beginning,
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
if($mybb->input['action'] == "addmod") {
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
	endtable();
	endform($lang->add_moderator, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "delete") {
	$fid = intval($mybb->input['fid']);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$fid'");
	$forum = $db->fetch_array($query);
	cpheader();
	startform("forums.php", "", "do_delete");
	makehiddencode("fid", $fid);
	starttable();
	$lang->delete_forum = sprintf($lang->delete_forum, $forum['name']);
	tableheader($lang->delete_forum, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	$lang->delete_forum_confirm = sprintf($lang->delete_forum_confirm, $forum['name']);
	makelabelcode("<center>$lang->delete_forum_confirm<br><br>$yes$no</center>", "");
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "deletemod") {
	$mid = intval($mybb->input['mid']);
	$fid = intval($mybb->input['fid']);
	cpheader();
	startform("forums.php", "", "do_deletemod");
	makehiddencode("mid", $mid);
	makehiddencode("fid", $fid);
	starttable();
	tableheader($lang->delete_mod, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<center>$lang->delete_mod_confirm<br><br>$yes$no</center>", "");
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "edit") {
	if(!$noheader)
	{
		cpheader();
	}
	$fid = intval($mybb->input['fid']);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$fid'");
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
	if(!$forum['style']) {
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
if($mybb->input['action'] == "editmod") {
	if(!$noheader)
	{
		cpheader();
	}
	$mid = intval($mybb->input['mid']);
	$fid = intval($mybb->input['fid']);
	$query = $db->query("SELECT m.*, u.username FROM ".TABLE_PREFIX."moderators m LEFT JOIN ".TABLE_PREFIX."users u ON u.uid=m.uid WHERE m.mid='$mid'");
	$moderator = $db->fetch_array($query);
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
	endtable();
	endform($lang->update_moderator, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "do_modify") {
	while(list($fid, $order) = each($mybb->input['disporder']))
	{
		$fid = intval($fid);
		$order = intval($order);
		$db->query("UPDATE ".TABLE_PREFIX."forums SET disporder='$order' WHERE fid='$fid'");
	}
	$cache->updateforums();
	cpredirect("forums.php", $lang->orders_updated);
}

if($mybb->input['action'] == "modify" || $mybb->input['action'] == "") {
	cpheader();
	$fid = intval($mybb->input['fid']);
	if($fid)
	{
		$query = $db->query("SELECT f.*, t.subject AS lastpostsubject FROM ".TABLE_PREFIX."forums f LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = f.lastposttid) WHERE f.fid='$fid'");
		$forum = $db->fetch_array($query);

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forumpermissions WHERE fid='$fid'");
		while($fperm = $db->fetch_array($query))
		{
			$fperms[$fperm[gid]] = $fperm;
		}

		$hopto[] = "<input type=\"button\" value=\"$lang->add_child_forum\" onclick=\"hopto('forums.php?action=add&pid=$fid');\" class=\"hoptobutton\">";
		$hopto[] = "<input type=\"button\" value=\"$lang->edit_forum_settings\" onclick=\"hopto('forums.php?action=edit&fid=$fid');\" class=\"hoptobutton\">";
		$hopto[] = "<input type=\"button\" value=\"$lang->delete_forum2\" onclick=\"hopto('forums.php?action=delete&fid=$fid');\" class=\"hoptobutton\">";
		makehoptolinks($hopto);
		
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE pid='$fid'");
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
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups ORDER BY title ASC");
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
				$sql = buildparentlist($fid);
				$cusquery = $db->query("SELECT * FROM ".TABLE_PREFIX."forumpermissions WHERE $sql AND gid='$usergroup[gid]'");
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
		$parentlist = buildparentlist($fid, 'm.fid');
		$modquery = $db->query("SELECT m.mid, m.uid, m.fid, u.username FROM ".TABLE_PREFIX."moderators m LEFT JOIN ".TABLE_PREFIX."users u ON (m.uid=u.uid) WHERE $parentlist ORDER BY u.username");
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