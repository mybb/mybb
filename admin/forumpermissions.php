<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

define("IN_MYBB", 1);

require_once "./global.php";

// Load language packs for this section
global $lang;
$lang->load("forumpermissions");

checkadminpermissions("caneditforums");
logadmin();

switch($mybb->input['action'])
{
	case "edit":
		makeacpforumnav($fid);
		addacpnav($lang->nav_edit_permissions);
		break;
	default:
		addacpnav($lang->nav_forum_permissions, "forumpermissions.php?".SID);
		break;
}

$plugins->run_hooks("admin_forumpermissions_start");

function build_permission_forumbits($pid=0)
{
	global $db, $lang, $cache;

	// Sort out the forum cache first.
	static $fcache, $forumpermissions, $usergroups, $cachedforumpermissions;
	if(!is_array($fcache))
	{
		// Fetch usergroups
		$query = $db->simple_select("usergroups", "gid, title", "", array("order_by" => "title", "order_dir" => "asc"));
		while($usergroup = $db->fetch_array($query))
		{
			$usergroups[$usergroup['gid']] = $usergroup;
		}
		
		// Fetch forum permissions
		$query = $db->simple_select("forumpermissions", "fid, gid, pid");
		while($forumpermission = $db->fetch_array($query))
		{
			$forumpermissions[$forumpermission['fid']][$forumpermission['gid']] = $forumpermission['pid'];
		}
		
		// Fetch forums
		$query = $db->simple_select("forums", "*", "", array('order_by' =>'pid, disporder'));
		while($forum = $db->fetch_array($query))
		{
			$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
		
		$cachedforumpermissions = $cache->read("forumpermissions");
	}

	// Start the process.
	if(is_array($fcache[$pid]))
	{
		foreach($fcache[$pid] as $key => $main)
		{
			foreach($main as $key => $forum)
			{
				$forum_list .= "\n<li>";
				$forum_list .= "<div style=\"float:right\"><small>".makelinkcode($lang->copy_permissions_to, "forums.php?".SID."&amp;action=copy&amp;from=$forum[fid]&amp;copyforumsettings=no&amp;copygroups=all");
				$forum_list .= makelinkcode($lang->copy_permissions_from, "forums.php?".SID."&amp;action=copy&amp;to=$forum[fid]&amp;copyforumsettings=no&amp;copygroups=all")."</small></div>";
				$forum_list .= "<b>$forum[name]</b>\n";
				$forum_list .= "<ul>\n";
				foreach($usergroups as $usergroup)
				{
					// This forum has custom permissions for this group
					if($forumpermissions[$forum['fid']][$usergroup['gid']])
					{
						$pid = $forumpermissions[$forum['fid']][$usergroup['gid']];
						$forum_list .= "<li><span style=\"color: red\">$usergroup[title]</span> ";
						$forum_list .= makelinkcode("<span style=\"color: red\">$lang->edit_perms</span>", "forumpermissions.php?".SID."&amp;action=edit&amp;pid=$pid&amp;fid=$forum[fid]");
					}
					// This forum is inheriting permissions from the parent forum
					else if($cachedforumpermissions[$forum['fid']][$usergroup['gid']])
					{
						$forum_list .= "<li><span style=\"color: blue\">$usergroup[title]</span> ";
						$forum_list .= makelinkcode("<span style=\"color: blue\">$lang->set_perms</span>", "forumpermissions.php?".SID."&amp;action=edit&amp;fid=$forum[fid]&amp;gid=$usergroup[gid]");
					}
					// Otherwise, this forum has no permissions set and is inheriting from usergroup
					else
					{
						$forum_list .= "<li><span style=\"color: black\">$usergroup[title]</span> ";
						$forum_list .= makelinkcode("<span style=\"color: black\">$lang->set_perms</span>", "forumpermissions.php?".SID."&amp;action=edit&amp;fid=$forum[fid]&amp;gid=$usergroup[gid]");
					}
				}
				$forum_list .= "</li>\n";
				$forum_list .= build_permission_forumbits($forum['fid']);
				$forum_list .= "</ul>\n";
				$forum_list .= "</li>\n";
			}
		}
	}
	return $forum_list;
}

if($mybb->input['action'] == "do_quickperms")
{
	$inherit = $mybb->input['inherit'];
	$canview = $mybb->input['canview'];
	$canpostthreads = $mybb->input['canpostthreads'];
	$canpostreplies = $mybb->input['canpostreplies'];
	$canpostpolls = $mybb->input['canpostpolls'];
	$canpostattachments = $mybb->input['canpostattachments'];
	$plugins->run_hooks("admin_forumpermissions_do_quickperms");
	savequickperms($mybb->input['fid']);
	cpredirect("forumpermissions.php?".SID, $lang->perms_updated);
}


if($mybb->input['action'] == "quickperms")
{
	$plugins->run_hooks("admin_forumpermissions_quickperms");
	$fid = intval($mybb->input['fid']);
	cpheader();
	startform("forumpermissions.php", "", "do_quickperms");
	makehiddencode("fid", $fid);
	quickpermissions($fid);
	endform($lang->update_permissions, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "do_edit")
{
	$pid = intval($mybb->input['pid']);
	$fid = intval($mybb->input['fid']);
	$gid = intval($mybb->input['gid']);
	if($mybb->input['usecustom'] == "no")
	{
		if($pid)
		{
			$db->delete_query("forumpermissions", "pid='{$pid}'");
		}
		else
		{
			$db->delete_query("forumpermissions", "gid='{$gid}' AND fid='{$fid}'");
		}
	}
	else
	{
		$sqlarray = array(
			"canview" => $db->escape_string($mybb->input['canview']),
			"canviewthreads" => $db->escape_string($mybb->input['canviewthreads']),
			"candlattachments" => $db->escape_string($mybb->input['candlattachments']),
			"canpostthreads" => $db->escape_string($mybb->input['canpostthreads']),
			"canpostreplys" => $db->escape_string($mybb->input['canpostreplys']),
			"canpostattachments" => $db->escape_string($mybb->input['canpostattachments']),
			"canratethreads" => $db->escape_string($mybb->input['canratethreads']),
			"caneditposts" => $db->escape_string($mybb->input['caneditposts']),
			"candeleteposts" => $db->escape_string($mybb->input['candeleteposts']),
			"candeletethreads" => $db->escape_string($mybb->input['candeletethreads']),
			"caneditattachments" => $db->escape_string($mybb->input['caneditattachments']),
			"canpostpolls" => $db->escape_string($mybb->input['canpostpolls']),
			"canvotepolls" => $db->escape_string($mybb->input['canvotepolls']),
			"cansearch" => $db->escape_string($mybb->input['cansearch']),
		);
		$plugins->run_hooks("admin_forumpermissions_do_edit");
		if($fid)
		{
			$sqlarray['fid'] = $fid;
			$sqlarray['gid'] = intval($mybb->input['gid']);
			$db->insert_query("forumpermissions", $sqlarray);
		}
		else
		{
			$db->update_query("forumpermissions", $sqlarray, "pid='$pid'");
		}
	}
	$cache->update_forumpermissions();
	cpredirect("forumpermissions.php?".SID, $lang->perms_updated);
}
if($mybb->input['action'] == "edit")
{
	$pid = intval($mybb->input['pid']);
	$gid = intval($mybb->input['gid']);
	$fid = intval($mybb->input['fid']);
	if($pid)
	{
		$query = $db->simple_select("forumpermissions", "*", "pid='{$pid}'");
	}
	else
	{
		$options = array(
			"limit" => "1"
		);
		$query = $db->simple_select("forumpermissions", "*", "fid='{$fid}' AND gid='{$gid}'", $options);
	}
	$forumpermissions = $db->fetch_array($query);
	if(!$fid)
	{
		$fid = $forumpermissions['fid'];
	}
	if(!$gid)
	{
		$gid = $forumpermissions['gid'];
	}
	$query = $db->simple_select("usergroups", "*", "gid='$gid'");
	$usergroup = $db->fetch_array($query);
	$query = $db->simple_select("forums", "*", "fid='$fid'");
	$forum = $db->fetch_array($query);
	$plugins->run_hooks("admin_forumpermissions_edit");
	cpheader();
	startform("forumpermissions.php", "", "do_edit");
	$sperms = $forumpermissions;

	$sql = build_parent_list($fid);
	$query = $db->simple_select("forumpermissions", "*", "$sql AND gid='$gid'");
	$customperms = $db->fetch_array($query);

	if($forumpermissions['pid'])
	{
		$usecustom = " checked=\"checked\"";
		makehiddencode("pid", $pid);
	}
	else
	{
		makehiddencode("fid", $fid);
		makehiddencode("gid", $gid);
		if(!$customperms['pid'])
		{
			$forumpermissions = usergroup_permissions($gid);
			$useusergroup = " checked=\"checked\"";
		}
		else
		{
			$useusergroup = " checked=\"checked\"";
			$forumpermissions = forum_permissions($fid, 0, $gid);
		}
	}

	if($customperms['pid'] && !$sperms['fid'])
	{
		starttable();
		makelabelcode($lang->inherit_note);
		endtable();
		echo "<br />";
	}
	starttable();
	$lang->edit_permissions = sprintf($lang->edit_permissions, $usergroup['title'], $forum['name']);
	tableheader($lang->edit_permissions);
	makelabelcode("<label><input type=\"radio\" name=\"usecustom\" value=\"no\"$useusergroup /> $lang->use_default_inherit</label>", "", 2);
	makelabelcode("<label><input type=\"radio\" name=\"usecustom\" value=\"yes\"$usecustom /> $lang->use_custom</label>", "", 2);

	tablesubheader($lang->perms_viewing);
	makepermscode($lang->canview, "canview", $forumpermissions['canview']);
	makepermscode($lang->canviewthreads, 'canviewthreads', $forumpermissions['canviewthreads']);
	makepermscode($lang->candlattachments, "candlattachments", $forumpermissions['candlattachments']);

	tablesubheader($lang->perms_posting);
	makepermscode($lang->canpostthreads, "canpostthreads", $forumpermissions['canpostthreads']);
	makepermscode($lang->canpostreplies, "canpostreplys", $forumpermissions['canpostreplys']);
	makepermscode($lang->canpostattachments, "canpostattachments", $forumpermissions['canpostattachments']);
	makepermscode($lang->canratethreads, "canratethreads", $forumpermissions['canratethreads']);

	tablesubheader($lang->perms_editing);
	makepermscode($lang->caneditposts, "caneditposts", $forumpermissions['caneditposts']);
	makepermscode($lang->candeleteposts, "candeleteposts", $forumpermissions['candeleteposts']);
	makepermscode($lang->candeletethreads, "candeletethreads", $forumpermissions['candeletethreads']);
	makepermscode($lang->caneditattachments, "caneditattachments", $forumpermissions['caneditattachments']);

	tablesubheader($lang->perms_polls);
	makepermscode($lang->canpostpolls, "canpostpolls", $forumpermissions['canpostpolls']);
	makepermscode($lang->canvotepolls, "canvotepolls", $forumpermissions['canvotepolls']);

	tablesubheader($lang->perms_misc);
	makepermscode($lang->cansearch, "cansearch", $forumpermissions['cansearch']);
	endtable();
	endform($lang->update_permissions, $lang->reset_button);
	cpfooter();
}

function makepermscode($title, $name, $value)
{
	makeyesnocode($title, $name, $value);
}

if($mybb->input['action'] == "modify" || $mybb->input['action'] == "")
{
	$plugins->run_hooks("admin_forumpermissions_modify");
	if(!$noheader)
	{
		cpheader();
	}
	starttable();
	tableheader($lang->forum_permissions);
	tablesubheader($lang->guide);
	makelabelcode($lang->guide2);
	tablesubheader($lang->select_usergroup);
	$forumlist = build_permission_forumbits();
	makelabelcode("<ul>$forumlist</ul>", "");
	endtable();
	cpfooter();
}

?>
