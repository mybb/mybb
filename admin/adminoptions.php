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
$lang->load("adminoptions");

logadmin();

switch($mybb->input['action'])
{
	case "adminpermissions":
	case "updateperms":
		addacpnav($lang->nav_admin_permissions, "adminoptions.php?action=adminpermissions");
		break;
	case "":
	case "updateprefs":
		addacpnav($lang->nav_admin_prefs);
		break;
}

if($mybb->input['action'] == "do_updateprefs")
{
	$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."adminoptions WHERE uid='$user[uid]' LIMIT 1");
	$adminoptions = $db->fetch_array($query);
	$sqlarray = array(
		"notes" => addslashes($mybb->input['notes']),
		"cpstyle" => addslashes($mybb->input['cpstyle']),
		);
	if(isset($adminoptions['uid']))
	{
		$db->update_query(TABLE_PREFIX."adminoptions", $sqlarray, "uid='".$user['uid']."'");
	}
	else
	{
		$db->insert_query(TABLE_PREFIX."adminoptions", $sqlarray);
	}
	cpredirect("adminoptions.php", $lang->prefs_updated);
}
if($mybb->input['action'] == "revokeperms")
{
	$uid = intval($mybb->input['uid']);
	checkadminpermissions("caneditaperms");
	
	$newperms = array(
		"permsset" => 0
		);
	$db->update_query(TABLE_PREFIX."adminoptions", $newperms, "uid='$uid'");

	if($uid < 0)
	{
		cpredirect("adminoptions.php?action=adminpermissions", $lang->group_perms_revoked);
	}
	else
	{
		cpredirect("adminoptions.php?action=adminpermissions", $lang->perms_revoked);
	}
}
if($mybb->input['action'] == "do_updateperms")
{
	$uid = intval($mybb->input['uid']);
	checkadminpermissions("caneditaperms");
	
	// Check if there are custom permissions for this admin.
	$query = $db->query("
		SELECT permsset
		FROM ".TABLE_PREFIX."adminoptions
		WHERE uid='$uid'
		LIMIT 1
	");
	$adminoptions = $db->fetch_array($query);
	
	// If no custom permissions are set for this admin, create a blank custom set first.
	if(!isset($adminoptions['permsset']))
	{
		$options_update = array(
			"uid" => $uid
		);
		$db->insert_query(TABLE_PREFIX."adminoptions", $options_update);
	}
	
	// Update the admin to the new permissions.
	$newperms = $mybb->input['newperms'];
	$sqlarray = array(
		"permsset" => '1',
		"caneditsettings" => addslashes($newperms['caneditsettings']),
		"caneditann" => addslashes($newperms['caneditann']),
		"caneditforums" => addslashes($newperms['caneditforums']),
		"canmodposts" => addslashes($newperms['canmodposts']),
		"caneditsmilies" => addslashes($newperms['caneditsmilies']),
		"caneditpicons" => addslashes($newperms['caneditpicons']),
		"caneditthemes" => addslashes($newperms['caneditthemes']),
		"canedittemps" => addslashes($newperms['canedittemps']),
		"caneditusers" => addslashes($newperms['caneditusers']),
		"caneditpfields" => addslashes($newperms['caneditpfields']),
		"caneditugroups" => addslashes($newperms['caneditugroups']),
		"caneditaperms" => addslashes($newperms['caneditaperms']),
		"caneditutitles" => addslashes($newperms['caneditutitles']),
		"caneditattach" => addslashes($newperms['caneditattach']),
		"canedithelp" => addslashes($newperms['canedithelp']),
		"caneditlangs" => addslashes($newperms['caneditlangs']),
		"canrunmaint" => addslashes($newperms['canrunmaint']),
		);
	$db->update_query(TABLE_PREFIX."adminoptions", $sqlarray, "uid='$uid'");
	
	// Redirect based on what the user did.
	if($uid == 0)
	{
		cpredirect("adminoptions.php?action=adminpermissions", $lang->default_perms_updated);
	}
	elseif($uid < 0)
	{
		cpredirect("adminoptions.php?action=adminpermissions", $lang->group_perms_updated);
	}
	else
	{
		cpredirect("adminoptions.php?action=adminpermissions", $lang->perms_updated);
	}
}
if($mybb->input['action'] == "updateperms")
{
	checkadminpermissions("caneditaperms");
	$uid = intval($mybb->input['uid']);
	if($uid > 0)
	{
		$query = $db->query("SELECT u.uid, u.username, g.cancp FROM (".TABLE_PREFIX."users u, ".TABLE_PREFIX."usergroups g) WHERE u.uid='$uid' AND u.usergroup=g.gid AND g.cancp='yes'");
		$admin = $db->fetch_array($query);
		$tsub = sprintf($lang->edit_admin_perms, $admin['username']);
		$permissions = getadminpermissions($uid);
		$lang->nav_edit_permissions = sprintf($lang->nav_edit_permissions, $admin['username']);
		addacpnav($lang->nav_edit_permissions);
	}
	elseif($uid < 0)
	{
		$gid = abs($uid);
		$query = $db->simple_select(TABLE_PREFIX."usergroups", "title", "gid='$gid'");
		$group = $db->fetch_array($query);
		$tsub = sprintf($lang->edit_admin_group_perms, $group['title']);
		$permissions = getadminpermissions("", $gid);
		$lang->nav_edit_permissions = sprintf($lang->nav_edit_group_permissions, $group['title']);
		addacpnav($lang->nav_edit_permissions);
	}
	else
	{
		$tsub = $lang->edit_default_perms;
		$query = $db->simple_select(TABLE_PREFIX."adminoptions", "*", "uid='0'");
		$permissions = $db->fetch_array($query);
		addacpnav($lang->nav_edit_def_permissions);
	}
	cpheader();
	startform("adminoptions.php", "", "do_updateperms");
	makehiddencode("uid", $uid);
	starttable();
	tableheader($lang->edit_perms);
	tablesubheader("$tsub");
	makeyesnocode($lang->can_manage_settings, "newperms[caneditsettings]", $permissions['caneditsettings']);
	makeyesnocode($lang->can_manage_announcements, "newperms[caneditann]", $permissions['caneditann']);
	makeyesnocode($lang->can_manage_forums, "newperms[caneditforums]", $permissions['caneditforums']);
	makeyesnocode($lang->can_moderate_posts, "newperms[canmodposts]", $permissions['canmodposts']);
	makeyesnocode($lang->can_manage_smilies, "newperms[caneditsmilies]", $permissions['caneditsmilies']);
	makeyesnocode($lang->can_manage_posticons, "newperms[caneditpicons]", $permissions['caneditpicons']);
	makeyesnocode($lang->can_manage_themes, "newperms[caneditthemes]", $permissions['caneditthemes']);
	makeyesnocode($lang->can_manage_templates, "newperms[canedittemps]", $permissions['canedittemps']);
	makeyesnocode($lang->can_manage_users, "newperms[caneditusers]", $permissions['caneditusers']);
	makeyesnocode($lang->can_manage_profilefields, "newperms[caneditpfields]", $permissions['caneditpfields']);
	makeyesnocode($lang->can_manage_usergroups, "newperms[caneditugroups]", $permissions['caneditugroups']);
	makeyesnocode($lang->can_manage_adminperms, "newperms[caneditaperms]", $permissions['caneditaperms']);
	makeyesnocode($lang->can_manage_usertitles, "newperms[caneditutitles]", $permissions['caneditutitles']);
	makeyesnocode($lang->can_manage_attachments, "newperms[caneditattach]", $permissions['caneditattach']);
	makeyesnocode($lang->can_manage_helpdocs, "newperms[canedithelp]", $permissions['canedithelp']);
	makeyesnocode($lang->can_manage_languages, "newperms[caneditlangs]", $permissions['caneditlangs']);
	makeyesnocode($lang->can_use_maint, "newperms[canrunmaint]", $permissions['canrunmaint']);
	endtable();
	endform($lang->update_permissions, $lang->reset_button);

	
	cpfooter();
}
if($mybb->input['action'] == "adminpermissions")
{
	checkadminpermissions("caneditaperms");
	cpheader();
	starttable();
	tableheader($lang->admin_perms.makelinkcode($lang->edit_default, "adminoptions.php?action=updateperms&uid=0", "", "header"), "", 5);
	echo "<tr>\n";
	echo "<td class=\"subheader\">$lang->username</td>\n";
	echo "<td class=\"subheader\">$lang->usergroup</td>\n";
	echo "<td class=\"subheader\">$lang->lastactive</td>\n";
	echo "<td class=\"subheader\">$lang->perm_options</td>\n";
	echo "<td class=\"subheader\">$lang->options</td>\n";
	echo "</tr>\n";
	$query = $db->query("SELECT u.uid, u.username, u.lastactive, g.cancp, g.title as usergroup, a.permsset FROM (".TABLE_PREFIX."users u, ".TABLE_PREFIX."usergroups g) LEFT JOIN ".TABLE_PREFIX."adminoptions a ON (a.uid=u.uid) WHERE u.usergroup=g.gid AND g.cancp='yes' ORDER BY u.username ASC");
	while($admin = $db->fetch_array($query))
	{
		$la = mydate($settings['dateformat'].",".$settings['timeformat'], $admin['lastactive']);
		$bgcolor = getaltbg();
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\">$admin[username]</td>\n";
		echo "<td class=\"$bgcolor\">$admin[usergroup]</td>\n";
		echo "<td class=\"$bgcolor\">$la</td>\n";
		echo "<td class=\"$bgcolor\">";
		if($admin['permsset'])
		{
			echo makelinkcode($lang->edit_perms2, "adminoptions.php?action=updateperms&uid=$admin[uid]");
			echo makelinkcode($lang->revoke_custom_perms, "adminoptions.php?action=revokeperms&uid=$admin[uid]");
		}
		else
		{
			echo makelinkcode($lang->set_perms, "adminoptions.php?action=updateperms&uid=$admin[uid]");
		}
		echo "</td>\n";
		echo "<td class=\"$bgcolor\">";
		echo makelinkcode($lang->admin_log, "adminlogs.php?action=view&fromadmin=$admin[uid]")."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}
	endtable();

	// Usergroup list
	starttable();
	tableheader($lang->admin_group_perms);
	echo "<tr>\n";
	echo "<td class=\"subheader\">$lang->groupname</td>\n";
	echo "<td class=\"subheader\">$lang->perm_options</td>\n";
	echo "</tr>\n";
	$query = $db->query("SELECT g.title, g.cancp, a.permsset, g.gid FROM (".TABLE_PREFIX."usergroups g) LEFT JOIN ".TABLE_PREFIX."adminoptions a ON (a.uid = -g.gid) WHERE g.cancp='yes' ORDER BY g.title ASC");
	while($group = $db->fetch_array($query))
	{
		$bgcolor = getaltbg();
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\">$group[title]</td>\n";
		echo "<td class=\"$bgcolor\">";
		$uid = -$group['gid'];
		if($group['permsset'])
		{
			echo makelinkcode($lang->edit_perms2, "adminoptions.php?action=updateperms&uid=$uid");
			echo makelinkcode($lang->revoke_custom_perms, "adminoptions.php?action=revokeperms&uid=$uid");
		}
		else
		{
			echo makelinkcode($lang->set_perms, "adminoptions.php?action=updateperms&uid=$uid");
		}
		echo "</td>\n";
		echo "</tr>\n";
	}
	endtable();
	cpfooter();

}
if($mybb->input['action'] == "updateprefs" || $mybb->input['action'] == "")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."adminoptions WHERE uid='$user[uid]'");
	$adminoptions = $db->fetch_array($query);

	$dir = @opendir($config['admindir']."/styles");
	while($folder = readdir($dir))
	{
		if($file != "." && $file != ".." && @file_exists($config['admindir']."/styles/$folder/stylesheet.css"))
		{
			$folders[$folder] = $folder;
		}
	}
	closedir($dir);
	ksort($folders);
	while(list($key, $val) = each($folders))
	{
		if($val == $adminoptions['cpstyle'])
		{
			$sel = "selected";
		}
		else
		{
			$sel = "";
		}
		$options .= "<option value=\"$val\" $sel>$val</option>\n";
	}
	cpheader();
	startform("adminoptions.php", "", "do_updateprefs");
	starttable();
	tableheader($lang->cp_prefs);
	tablesubheader($lang->prefs);
	makelabelcode($lang->cp_style, "<select name=\"cpstyle\" size=\"4\">\n<option value=\"\">Default</option>\n<option value=\"\">---------</option>\n$options</select>");
	tablesubheader($lang->notepad);
	makelabelcode("<center><textarea name=\"notes\" rows=\"25\" cols=\"80\">$adminoptions[notes]</textarea></center>", "", 2);
	endtable();
	endform($lang->update_prefs, $lang->reset_button);
	cpfooter();
}
?>