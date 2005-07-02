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
$lang->load("forumpermissions");

checkadminpermissions("caneditforums");
logadmin();

switch($action)
{
	case "edit":
		makeacpforumnav($fid);
		addacpnav($lang->nav_edit_permissions);
		break;
	default:
		addacpnav($lang->nav_forum_permissions, "forumpermissions.php");
		break;
}

function getforums($pid="0")
{
	global $db, $forumlist, $ownperms, $parentperms, $lang;
	if(!$ownperms)
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forumpermissions");
		while($permissions = $db->fetch_array($query))
		{
			$ownperms[$permissions[fid]][$permissions[gid]] = $permissions[pid];
		}
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE pid='$pid' ORDER BY disporder ASC");
	while($forum = $db->fetch_array($query))
	{
		$forumlist .= "\n<li><b>$forum[name]</b>\n";
		$forumlist .= "<ul>\n";
		$groupquery = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups ORDER BY title");
		while($usergroup = $db->fetch_array($groupquery))
		{
			if($ownperms[$forum[fid]][$usergroup[gid]])
			{
				$pid = $ownperms[$forum[fid]][$usergroup[gid]];
				$forumlist .= "<li><font color=\"red\">$usergroup[title]</font> ";
				$forumlist .= makelinkcode("<font color=\"red\">$lang->edit_perms</font>", "forumpermissions.php?action=edit&pid=$pid&fid=$forum[fid]");
			}
			else
			{
				$sql = buildparentlist($forum[fid]);
				$cusquery = $db->query("SELECT * FROM ".TABLE_PREFIX."forumpermissions WHERE $sql AND gid='$usergroup[gid]'");
				$customperms = $db->fetch_array($cusquery);
				if($customperms[pid])
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
		getforums($forum[fid]);
		$forumlist .= "</ul>\n";
		$forumlist .= "</li>\n";
	}
	return $forumlist;
}
if($action == "do_quickperms")
{
	savequickperms($fid);
	cpredirect("forumpermissions.php", "The forum permissions for this forum have been updated and/or deleted.");
}

		
if($action == "quickperms")
{
	cpheader();
	startform("forumpermissions.php", "", "do_quickperms");
	makehiddencode("fid", $fid);
	quickpermissions($fid);
	endform($lang->update_permissions, $lang->reset_button);
	cpfooter();
}

if($action == "do_edit")
{
	if($usecustom == "no")
	{
		if($pid)
		{
			$db->query("DELETE FROM ".TABLE_PREFIX."forumpermissions WHERE pid='$pid'");
		}
	}
	else
	{
		if($fid)
		{
			$db->query("INSERT INTO ".TABLE_PREFIX."forumpermissions (pid, fid, gid, canview, candlattachments, canpostthreads, canpostreplys, canpostattachments, canratethreads, caneditposts, candeleteposts, candeletethreads, caneditattachments, canpostpolls, canvotepolls, cansearch) VALUES (NULL, '$fid', '$gid', '$canview', '$candlattachments', '$canpostthreads', '$canpostreplys', '$canpostattachments', '$canratethreads', '$caneditposts', '$candeleteposts', '$candeletethreads', '$caneditattachments', '$canpostpolls', '$canvotepolls', '$cansearch')");
		}
		else
		{
			$db->query("UPDATE ".TABLE_PREFIX."forumpermissions SET canview='$canview', candlattachments='$candlattachments', canpostthreads='$canpostthreads', canpostreplys='$canpostreplys', canpostattachments='$canpostattachments', canratethreads='$canratethreads', caneditposts='$caneditposts', candeleteposts='$candeleteposts', candeletethreads='$candeletethreads', caneditattachments='$caneditattachments', canpostpolls='$canpostpolls', canvotepolls='$canvotepolls', cansearch='$cansearch' WHERE pid='$pid'");
		}
	}
	$cache->updateforumpermissions();
	cpredirect("forumpermissions.php", $lang->perms_updated);
}
if($action == "edit")
{
	if(!$noheader)
	{
		cpheader();
	}
	if($pid)
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forumpermissions WHERE pid='$pid'");
	}
	else
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forumpermissions WHERE fid='$fid' AND gid='$gid'");
	}
	$forumpermissions = $db->fetch_array($query);
	if(!$fid && !$gid) {
		$fid = $forumpermissions[fid];
		$gid = $forumpermissions[gid];
	}

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='$gid'");
	$usergroup = $db->fetch_array($query);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='$fid'");
	$forum = $db->fetch_array($query);
	startform("forumpermissions.php", "", "do_edit");
	$sperms = $forumpermissions;

	$sql = buildparentlist($fid);
	$cusquery = $db->query("SELECT * FROM ".TABLE_PREFIX."forumpermissions WHERE $sql AND gid='$gid'");
	$customperms = $db->fetch_array($cusquery);

	if($forumpermissions[pid])
	{
		$usecustom = "checked";
		makehiddencode("pid", $pid);
	}
	else
	{
		makehiddencode("fid", $fid);
		makehiddencode("gid", $gid);
		if(!$customperms[pid])
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

	if($customperms[pid] && !$sperms[fid])
	{
		starttable();
		makelabelcode($lang->inherit_note);
		endtable();
		echo "<br>";
	}
	starttable();
	$lang->edit_permissions = sprintf($lang->edit_permissions, $usergroup[title], $forum[name]);
	tableheader($lang->edit_permissions);
	makelabelcode("<input type=\"radio\" name=\"usecustom\" value=\"no\" $useusergroup> $lang->use_default_inherit", "", 2);
	makelabelcode("<input type=\"radio\" name=\"usecustom\" value=\"yes\" $usecustom> $lang->use_custom", "", 2);
	
	tablesubheader($lang->perms_viewing);
	makepermscode($lang->canview, "canview", $forumpermissions['canview']);
	makepermscode($lang->candlattachments, "candlattachments", $forumpermissions['candlattachments']);

	tablesubheader($lang->perms_posting);
	makepermscode($lang->canpostthreads, "canpostthreads", $forumpermissions['canpostthreads']);
	makepermscode($lang->canpostreplies, "canpostreplys", $forumpermissions[canpostreplys]);
	makepermscode($lang->canpostattachments, "canpostattachments", $forumpermissions[canpostattachments]);
	makepermscode($lang->canratethreads, "canratethreads", $forumpermissions[canratethreads]);
	
	tablesubheader($lang->perms_editing);
	makepermscode($lang->caneditposts, "caneditposts", $forumpermissions[caneditposts]);
	makepermscode($lang->candeleteposts, "candeleteposts", $forumpermissions[candeleteposts]);
	makepermscode($lang->candeletethreads, "candeletethreads", $forumpermissions[candeletethreads]);
	makepermscode($lang->caneditattachments, "caneditattachments", $forumpermissions[caneditattachments]);
	
	tablesubheader($lang->perms_polls);
	makepermscode($lang->canpostpolls, "canpostpolls", $forumpermissions[canpostpolls]);
	makepermscode($lang->canvotepolls, "canvotepolls", $forumpermissions[canvotepolls]);

	tablesubheader($lang->perms_misc);
	makepermscode($lang->cansearch, "cansearch", $forumpermissions[cansearch]);
	endtable();
	endform($lang->update_permissions, $lang->reset_button);
	cpfooter();
}

function makepermscode($title, $name, $value)
{
	makeyesnocode($title, $name, $value);
}

if($action == "modify" || $action == "")
{
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