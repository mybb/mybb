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
		addacpnav($lang->nav_forum_permissions, "forumpermissions.php");
		break;
}

$plugins->run_hooks("admin_forumpermissions_start");

function getforums($pid="0")
{
	global $db, $forumlist, $ownperms, $parentperms, $lang;
	if(!$ownperms)
	{
		$query = $db->simple_select(TABLE_PREFIX."forumpermissions");
		while($permissions = $db->fetch_array($query))
		{
			$ownperms[$permissions['fid']][$permissions['gid']] = $permissions['pid'];
		}
	}
	$options = array(
		"order_by" => "disporder",
		"order_dir" => "ASC"
	);
	$query = $db->simple_select(TABLE_PREFIX."forums", "*", "pid='$pid'", $options);
	while($forum = $db->fetch_array($query))
	{
		$forumlist .= "\n<li>";
		$forumlist .= "<div style=\"float:right\"><small>".makelinkcode($lang->copy_permissions_to, "forums.php?action=copy&from=$forum[fid]&copyforumsettings=no&copygroups=all");
		$forumlist .= makelinkcode($lang->copy_permissions_from, "forums.php?action=copy&to=$forum[fid]&copyforumsettings=no&copygroups=all")."</small></div>";
		$forumlist .= "<b>$forum[name]</b>\n";
		$forumlist .= "<ul>\n";
		$groupquery = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups ORDER BY title");
		while($usergroup = $db->fetch_array($groupquery))
		{
			if($ownperms[$forum['fid']][$usergroup['gid']])
			{
				$pid = $ownperms[$forum['fid']][$usergroup['gid']];
				$forumlist .= "<li><font color=\"red\">$usergroup[title]</font> ";
				$forumlist .= makelinkcode("<font color=\"red\">$lang->edit_perms</font>", "forumpermissions.php?action=edit&pid=$pid&fid=$forum[fid]");
			}
			else
			{
				$sql = build_parent_list($forum['fid']);
				$cusquery = $db->simple_select(TABLE_PREFIX."forumpermissions", "*", "$sql AND gid='$usergroup[gid]'");
				$customperms = $db->fetch_array($cusquery);
				if($customperms['pid'])
				{
					$forumlist .= "<li><font color=\"blue\">$usergroup[title]</font> ";
					$forumlist .= makelinkcode("<font color=\"blue\">$lang->set_perms</font>", "forumpermissions.php?action=edit&fid=$forum[fid]&gid=$usergroup[gid]");
				}
				else
				{
					$forumlist .= "<li><font color=\"black\">$usergroup[title]</font> ";
					$forumlist .= makelinkcode("<font color=\"black\">$lang->set_perms</font>", "forumpermissions.php?action=edit&fid=$forum[fid]&gid=$usergroup[gid]");
				}
			}
			$forumlist .= "</font></li>\n";
		}
		getforums($forum['fid']);
		$forumlist .= "</ul>\n";
		$forumlist .= "</li>\n";
	}
	return $forumlist;
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
	savequickperms($fid);
	cpredirect("forumpermissions.php", $lang->perms_updated);
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
	if($usecustom == "no")
	{
		if($pid)
		{
			$db->delete_query(TABLE_PREFIX."forumpermissions", "pid='{$pid}'");
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
			$db->insert_query(TABLE_PREFIX."forumpermissions", $sqlarray);
		}
		else
		{
			$db->update_query(TABLE_PREFIX."forumpermissions", $sqlarray, "pid='$pid'");
		}
	}
	$cache->updateforumpermissions();
	cpredirect("forumpermissions.php", $lang->perms_updated);
}
if($mybb->input['action'] == "edit")
{
	$pid = intval($mybb->input['pid']);
	$gid = intval($mybb->input['gid']);
	$fid = intval($mybb->input['fid']);
	if($pid)
	{
		$query = $db->simple_select(TABLE_PREFIX."forumpermissions", "*", "pid='{$pid}'");
	}
	else
	{
		$options = array(
			"limit" => "1"
		);
		$query = $db->simple_select(TABLE_PREFIX."forumpermissions", "*", "fid='{$fid}' AND gid='{$gid}'", $options);
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
	$query = $db->simple_select(TABLE_PREFIX."usergroups", "*", "gid='$gid'");
	$usergroup = $db->fetch_array($query);
	$query = $db->simple_select(TABLE_PREFIX."forums", "*", "fid='$fid'");
	$forum = $db->fetch_array($query);
	$plugins->run_hooks("admin_forumpermissions_edit");
	cpheader();
	startform("forumpermissions.php", "", "do_edit");
	$sperms = $forumpermissions;

	$sql = build_parent_list($fid);
	$query = $db->simple_select(TABLE_PREFIX."forumpermissions", "*", "$sql AND gid='$gid'");
	$customperms = $db->fetch_array($query);

	if($forumpermissions['pid'])
	{
		$usecustom = "checked";
		makehiddencode("pid", $pid);
	}
	else
	{
		makehiddencode("fid", $fid);
		makehiddencode("gid", $gid);
		if(!$customperms['pid'])
		{
			$forumpermissions = usergroup_permissions($gid);
			$useusergroup = "checked=\"checked\"";
		}
		else
		{
			$useusergroup = "checked=\"checked\"";
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
	makelabelcode("<input type=\"radio\" name=\"usecustom\" value=\"no\" $useusergroup> $lang->use_default_inherit", "", 2);
	makelabelcode("<input type=\"radio\" name=\"usecustom\" value=\"yes\" $usecustom> $lang->use_custom", "", 2);

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
	$forumlist = getforums();
	makelabelcode("<ul>$forumlist</ul>", "");
	endtable();
	cpfooter();
}

?>