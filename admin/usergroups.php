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
$lang->load("usergroups");

if($mybb->input['action'] == "listusers")
{
	header("Location: users.php?action=find&search[usergroup]=".$mybb->input['gid']);
	exit;
}
if($mybb->input['action'] == "listsecondaryusers")
{
	header("Location: users.php?action=find&search[additionalusergroups]=".$mybb->input['gid']);
	exit;
}
addacpnav($lang->nav_usergroups, "usergroups.php");
switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_usergroup);
		break;
	case "edit":
		addacpnav($lang->nav_edit_usergroup);
		break;
	case "delete":
		addacpnav($lang->nav_delete_usergroup);
		break;
	case "groupleaders":
		addacpnav($lang->nav_groupleaders);
		break;
	case "editgroupleader":
		addacpnav($lang->nav_editgroupleader);
		break;
}

checkadminpermissions("caneditugroups");
logadmin();

//Change the ordering of the usergroups
if($mybb->input['action'] == "disporder")
{
	foreach($mybb->input['disporder'] as $gid=>$order)
	{
		$gid = intval($gid);
		$order = intval($order);
		if($gid != 0 && $order != 0)
		{
			$sql_array = array(
				'disporder' => $order,
			);
			$db->update_query(TABLE_PREFIX.'usergroups', $sql_array, "gid = '{$gid}'");
		}
	}
	$mybb->input['action'] = 'modify';
}

if($mybb->input['action'] == "do_add")
{
	if(empty($mybb->input['title']))
	{
		cperror($lang->grouptitle_empty);
	}
	if($mybb->input['joinable'] == "yes")
	{
		if($mybb->input['moderate'] == "yes")
		{
			$mybb->input['type'] = "4";
		}
		else
		{
			$mybb->input['type'] = "3";
		}
	}
	else
	{
		$mybb->input['type'] = "2";
	}
	if(strpos($mybb->input['namestyle'], "{username}") === false)
	{
		$mybb->input['namestyle'] = "{username}";
		$namenote = $lang->error_namenote;
	}
	if($mybb->input['ustars'] < 1)
	{
		$mybb->input['ustars'] = 0;
	}
	$grouparray = array(
		"type" => $mybb->input['type'],
		"title" => $db->escape_string($mybb->input['title']),
		"description" => $db->escape_string($mybb->input['description']),
		"namestyle" => $db->escape_string($mybb->input['namestyle']),
		"usertitle" => $db->escape_string($mybb->input['usertitle']),
		"stars" => intval($mybb->input['ustars']),
		"starimage" => $db->escape_string($mybb->input['starimage']),
		"image" => $db->escape_string($mybb->input['image']),
		"isbannedgroup" => $db->escape_string($mybb->input['isbannedgroup']),
		"canview" => $db->escape_string($mybb->input['canview']),
		"canviewthreads" => $db->escape_string($mybb->input['canviewthreads'])
		"canviewprofiles" => $db->escape_string($mybb->input['canviewprofiles']),
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
		"canusepms" => $db->escape_string($mybb->input['canusepms']),
		"cansendpms" => $db->escape_string($mybb->input['cansendpms']),
		"cantrackpms" => $db->escape_string($mybb->input['cantrackpms']),
		"candenypmreceipts" => $db->escape_string($mybb->input['candenypmreceipts']),
		"pmquota" => intval($mybb->input['pmquota']),
		"cansendemail" => $db->escape_string($mybb->input['cansendemail']),
		"canviewmemberlist" => $db->escape_string($mybb->input['canviewmemberlist']),
		"canviewcalendar" => $db->escape_string($mybb->input['canviewcalendar']),
		"canaddpublicevents" => $db->escape_string($mybb->input['canaddpublicevents']),
		"canaddprivateevents" => $db->escape_string($mybb->input['canaddprivateevents']),
		"canviewonline" => $db->escape_string($mybb->input['canviewonline']),
		"canviewwolinvis" => $db->escape_string($mybb->input['canviewwolinvis']),
		"canviewonlineips" => $db->escape_string($mybb->input['canviewonlineips']),
		"cancp" => $db->escape_string($mybb->input['cancp']),
		"issupermod" => $db->escape_string($mybb->input['issupermod']),
		"cansearch" => $db->escape_string($mybb->input['cansearch']),
		"canusercp" => $db->escape_string($mybb->input['canusercp']),
		"canuploadavatars" => $db->escape_string($mybb->input['canuploadavatars']),
		"canchangename" => $db->escape_string($mybb->input['canchangename']),
		"showforumteam" => $db->escape_string($mybb->input['showforumteam']),
		"usereputationsystem" => $db->escape_string($mybb->input['usereputationsystem']),
		"cangivereputations" => $db->escape_string($mybb->input['cangivereputations']),
		"reputationpower" => intval($mybb->input['reputationpower']),
		"maxreputationsday" => intval($mybb->input['maxreputationsday']),
		"candisplaygroup" => $db->escape_string($mybb->input['candisplaygroup']),
		"attachquota" => intval($mybb->input['attachquota']),
		"cancustomtitle" => $db->escape_string($mybb->input['cancustomtitle'])
		);

	$db->insert_query(TABLE_PREFIX."usergroups", $grouparray);
	$cache->updateusergroups();
	$cache->updateforumpermissions();
	cpredirect("usergroups.php", $lang->group_added.$namenote);
}

if($mybb->input['action'] == "do_deletegroupleader")
{
	$db->query("DELETE FROM ".TABLE_PREFIX."groupleaders WHERE uid='".intval($mybb->input['uid'])."' AND gid='".intval($mybb->input['gid'])."'");
	cpredirect("usergroups.php?action=groupleaders&gid=".$mybb->input['gid'], $lang->leader_deleted);
}

if($mybb->input['action'] == "do_addgroupleader" || $mybb->input['action'] == "do_editgroupleader")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='".$db->escape_string($mybb->input['username'])."'");
	$user = $db->fetch_array($query);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='".intval($mybb->input['gid'])."'");
	$usergroup = $db->fetch_array($query);
	if(!$user['username'])
	{
		cperror($lang->add_leader_no_user);
	}
	if($mybb->input['canmanagemembers'] != "yes")
	{
		$mybb->input['canmanagemembers'] = "no";
	}
	if($mybb->input['canmanagerequests'] != "yes")
	{
		$mybb->input['canmanagerequests'] = "no";
	}
	$leaderarray = array(
		"gid" => $mybb->input['gid'],
		"uid" => $user['uid'],
		"canmanagemembers" => $db->escape_string($mybb->input['canmanagemembers']),
		"canmanagerequests" => $db->escape_string($mybb->input['canmanagerequests']),
		);

	if($mybb->input['action'] == "do_editgroupleader")
	{
		$lid = intval($mybb->input['lid']);
		$db->update_query(TABLE_PREFIX."groupleaders", $leaderarray, "lid='$lid'");
		$success_text= $lang->leader_edited;
	}
	else
	{
		$db->insert_query(TABLE_PREFIX."groupleaders", $leaderarray);
		$success_text= sprintf($lang->leader_added, $usergroup['title']);
	}

	cpredirect("usergroups.php?action=groupleaders&gid=".$mybb->input['gid'], $success_text);
}

if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{
		$db->query("DELETE FROM ".TABLE_PREFIX."usergroups WHERE gid='".intval($mybb->input['gid'])."' AND type!='1'");
		$db->query("UPDATE ".TABLE_PREFIX."users SET usergroup='2' WHERE usergroup='".intval($mybb->input['gid'])."'");
		$db->query("UPDATE ".TABLE_PREFIX."users SET displaygroup=usergroup WHERE displaygroup='".intval($mybb->input['gid'])."'");
		$cache->updateusergroups();
		$cache->updateforumpermissions();
		cpredirect("usergroups.php", $lang->group_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "do_edit")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='".intval($mybb->input['gid'])."'");
	$usergroup = $db->fetch_array($query);

	if($mybb->input['joinable'] == "yes")
	{
		if($mybb->input['moderate'] == "yes")
		{
			$mybb->input['type'] = "4";
		}
		else
		{
			$mybb->input['type'] = "3";
		}
	}
	else
	{
		$mybb->input['type'] = "2";
	}
	if($usergroup['type'] == "1")
	{
		$mybb->input['type'] = 1;
	}
	if(strpos($mybb->input['namestyle'], "{username}") === false)
	{
		$mybb->input['namestyle'] = "{username}";
		$namenote = $lang->error_namenote;
	}
	if($mybb->input['ustars'] < 1)
	{
		$mybb->input['ustars'] = 0;
	}
	$grouparray = array(
		"type" => intval($mybb->input['type']),
		"title" => $db->escape_string($mybb->input['title']),
		"description" => $db->escape_string($mybb->input['description']),
		"namestyle" => $db->escape_string($mybb->input['namestyle']),
		"usertitle" => $db->escape_string($mybb->input['usertitle']),
		"stars" => intval($mybb->input['ustars']),
		"starimage" => $db->escape_string($mybb->input['starimage']),
		"image" => $db->escape_string($mybb->input['image']),
		"isbannedgroup" => $db->escape_string($mybb->input['isbannedgroup']),
		"canview" => $db->escape_string($mybb->input['canview']),
		"canviewthreads" => $db->escape_string($mybb->input['canviewthreads']),
		"canviewprofiles" => $db->escape_string($mybb->input['canviewprofiles']),
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
		"canusepms" => $db->escape_string($mybb->input['canusepms']),
		"cansendpms" => $db->escape_string($mybb->input['cansendpms']),
		"cantrackpms" => $db->escape_string($mybb->input['cantrackpms']),
		"candenypmreceipts" => $db->escape_string($mybb->input['candenypmreceipts']),
		"pmquota" => intval($mybb->input['pmquota']),
		"cansendemail" => $db->escape_string($mybb->input['cansendemail']),
		"canviewmemberlist" => $db->escape_string($mybb->input['canviewmemberlist']),
		"canviewcalendar" => $db->escape_string($mybb->input['canviewcalendar']),
		"canaddpublicevents" => $db->escape_string($mybb->input['canaddpublicevents']),
		"canaddprivateevents" => $db->escape_string($mybb->input['canaddprivateevents']),
		"canviewonline" => $db->escape_string($mybb->input['canviewonline']),
		"canviewwolinvis" => $db->escape_string($mybb->input['canviewwolinvis']),
		"canviewonlineips" => $db->escape_string($mybb->input['canviewonlineips']),
		"cancp" => $db->escape_string($mybb->input['cancp']),
		"issupermod" => $db->escape_string($mybb->input['issupermod']),
		"cansearch" => $db->escape_string($mybb->input['cansearch']),
		"canusercp" => $db->escape_string($mybb->input['canusercp']),
		"canuploadavatars" => $db->escape_string($mybb->input['canuploadavatars']),
		"canchangename" => $db->escape_string($mybb->input['canchangename']),
		"showforumteam" => $db->escape_string($mybb->input['showforumteam']),
		"usereputationsystem" => $db->escape_string($mybb->input['usereputationsystem']),
		"cangivereputations" => $db->escape_string($mybb->input['cangivereputations']),
		"reputationpower" => intval($mybb->input['reputationpower']),
		"maxreputationsday" => intval($mybb->input['maxreputationsday']),
		"attachquota" => $db->escape_string($mybb->input['attachquota']),
		"cancustomtitle" => $db->escape_string($mybb->input['cancustomtitle'])
		);
	// Only update the candisplaygroup setting if not a core usergroup
	if($usergroup['type'] != 1)
	{
		$grouparray['candisplaygroup'] = $db->escape_string($mybb->input['candisplaygroup']);
	}

	$db->update_query(TABLE_PREFIX."usergroups", $grouparray, "gid='".$mybb->input['gid']."'");
	$cache->updateusergroups();
	$cache->updateforumpermissions();
	cpredirect("usergroups.php", $lang->group_updated.$namenote);
}
if($mybb->input['action'] == "add")
{
	cpheader();
	startform("usergroups.php", "" , "do_add");
	starttable();
	tableheader($lang->new_group);
	makeinputcode($lang->title, "title");
	maketextareacode($lang->description, "description");
	makeinputcode($lang->namestyle, "namestyle", "{username}");
	makeinputcode($lang->usertitle, "usertitle");
	makeinputcode($lang->stars, "ustars");
	makeinputcode($lang->star_image, "starimage", "images/star.gif");
	makeinputcode($lang->group_image, "image");

	tablesubheader($lang->group_options);
	makeyesnocode($lang->show_team_page, "showforumteam", "no");
	makeyesnocode($lang->banned_group, "isbannedgroup", "no");

	tablesubheader($lang->perms_joinable);
	makeyesnocode($lang->can_join_group, "joinable", "no");
	makeyesnocode($lang->moderate_joins, "moderate", "no");
	makeyesnocode($lang->can_display_group, "candisplaygroup", "no");

	tablesubheader($lang->perms_viewing);
	makeyesnocode($lang->can_view_board, "canview", "yes");
	makeyesnocode($lang->can_view_threads, 'canviewthreads', 'yes');
	makeyesnocode($lang->can_search_forums, "cansearch", "yes");
	makeyesnocode($lang->can_view_profiles, "canviewprofiles", "yes");
	makeyesnocode($lang->can_download_attachments, "candlattachments", "yes");

	tablesubheader($lang->perms_posting);
	makeyesnocode($lang->can_post_threads, "canpostthreads", "yes");
	makeyesnocode($lang->can_post_replies, "canpostreplys", "yes");
	makeyesnocode($lang->can_rate_threads, "canratethreads", "yes");

	tablesubheader($lang->perms_attachments);
	makeyesnocode($lang->can_post_attachments, "canpostattachments", "yes");
	makeinputcode($lang->attach_quota, "attachquota", "10000");

	tablesubheader($lang->perms_editing);
	makeyesnocode($lang->can_edit_posts, "caneditposts", "yes");
	makeyesnocode($lang->can_delete_posts, "candeleteposts", "yes");
	makeyesnocode($lang->can_delete_threads, "candeletethreads", "yes");
	makeyesnocode($lang->can_edit_attachments, "caneditattachments", "yes");

	tablesubheader($lang->perms_reputations);
	makeyesnocode($lang->show_reputations, "usereputationsystem", "yes");
	makeyesnocode($lang->can_give_reputations, "cangivereputations", "yes");
	$reputation_power = array(1 => 1, 2 => 2, 3 => 3);
	makeselectcode_array($lang->reputation_points, "reputationpower", $reputation_power, 1);
	makeinputcode($lang->reputation_points, "reputationpower", 1, 4);
	makeinputcode($lang->max_reputations_day, "maxreputationsday", "5", 4);

	tablesubheader($lang->perms_polls);
	makeyesnocode($lang->can_post_polls, "canpostpolls", "yes");
	makeyesnocode($lang->can_vote_polls, "canvotepolls", "yes");

	tablesubheader($lang->perms_pms);
	makeyesnocode($lang->can_use_pms, "canusepms", "yes");
	makeyesnocode($lang->can_send_pms, "cansendpms", "yes");
	makeyesnocode($lang->can_track_pms, "cantrackpms", "yes");
	makeyesnocode($lang->can_deny_pms, "candenypmreceipts", "yes");
	makeinputcode($lang->pm_quota, "stars", "50", 4);

	tablesubheader($lang->perms_calendar);
	makeyesnocode($lang->can_view_calendar, "canviewcalendar", "yes");
	makeyesnocode($lang->can_add_public, "canaddpublicevents", "no");
	makeyesnocode($lang->can_add_private, "canaddprivateevents", "no");

	tablesubheader($lang->perms_wol);
	makeyesnocode($lang->can_view_wol, "canviewonline", "yes");
	makeyesnocode($lang->can_view_invisible, "canviewwolinvis", "no");
	makeyesnocode($lang->can_view_ips, "canviewonlineips", "no");

	tablesubheader($lang->perms_account);
	makeyesnocode($lang->can_access_ucp, "canusercp", "yes");
	makeyesnocode($lang->can_change_name, "canchangename", "no");
	makeyesnocode($lang->can_custom_titles, "cancustomtitle", "no");
	makeyesnocode($lang->can_upload_avatars, "canuploadavatars", "yes");


	tablesubheader($lang->perms_admin);
	makeyesnocode($lang->can_access_acp, "cancp", "no");
	makeyesnocode($lang->is_smod, "issupermod", "no");

	tablesubheader($lang->perms_misc);
	makeyesnocode($lang->can_view_mlist, "canviewmemberlist", "yes");
	makeyesnocode($lang->can_send_emails, "cansendemail", "yes");
	endtable();
	endform($lang->add_group, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "delete")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='".intval($mybb->input['gid'])."'");
	$usergroup = $db->fetch_array($query);
	$lang->delete_group = sprintf($lang->delete_group, $usergroup['title']);
	$lang->confirm_delete_group = sprintf($lang->confirm_delete_group, $usergroup['title']);
	cpheader();
	startform("usergroups.php", "", "do_delete");
	makehiddencode("gid", $mybb->input['gid']);
	starttable();
	tableheader($lang->delete_group, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<center>$lang->confirm_delete_group<br><br>$yes$no</center>", "");
	endtable();
	endform();
	cpfooter();
}
if($mybb->input['action'] == "edit")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='".intval($mybb->input['gid'])."'");
	$usergroup = $db->fetch_array($query);
	if($usergroup['type'] == "3")
	{
		$joinable = "yes";
		$moderate = "no";
	}
	elseif($usergroup['type'] == "4")
	{
		$joinable = "yes";
		$moderate = "yes";
	}
	else
	{
		$joinable = "no";
		$moderate = "no";
	}
	$lang->edit_group = sprintf($lang->edit_group, $usergroup['title']);
	cpheader();
	startform("usergroups.php", "", "do_edit");
	makehiddencode("gid", htmlspecialchars($mybb->input['gid']));
	starttable();
	tableheader($lang->edit_group);
	makeinputcode($lang->title, "title", $usergroup['title']);
	maketextareacode($lang->description, "description", $usergroup['description']);
	makeinputcode($lang->namestyle, "namestyle", $usergroup['namestyle']);
	makeinputcode($lang->usertitle, "usertitle", $usergroup['usertitle']);
	makeinputcode($lang->stars, "ustars", $usergroup['stars']);
	makeinputcode($lang->star_image, "starimage", $usergroup['starimage']);
	makeinputcode($lang->group_image, "image", $usergroup['image']);

	tablesubheader($lang->group_options);
	if($usergroup['gid'] != "1" && $usergroup['gid'] != "5")
	{
		makeyesnocode($lang->show_team_page, "showforumteam", $usergroup['showforumteam']);
	}
	makeyesnocode($lang->banned_group, "isbannedgroup", $usergroup['isbannedgroup']);

	if($usergroup['type'] != 1)
	{
		tablesubheader($lang->perms_joinable);
		makeyesnocode($lang->can_join_group, "joinable", $joinable);
		makeyesnocode($lang->moderate_joins, "moderate", $moderate);
		makeyesnocode($lang->can_display_group, "candisplaygroup", $usergroup['candisplaygroup']);
	}

	tablesubheader($lang->perms_viewing);
	makeyesnocode($lang->can_view_board, "canview", $usergroup['canview']);
	makeyesnocode($lang->can_view_threads, 'canviewthreads', $usergroup['canviewthreads']);
	makeyesnocode($lang->can_search_forums, "cansearch", $usergroup['cansearch']);
	makeyesnocode($lang->can_view_profiles, "canviewprofiles", $usergroup['canviewprofiles']);
	makeyesnocode($lang->can_download_attachments, "candlattachments", $usergroup['candlattachments']);

	tablesubheader($lang->perms_posting);
	makeyesnocode($lang->can_post_threads, "canpostthreads", $usergroup['canpostthreads']);
	makeyesnocode($lang->can_post_replies, "canpostreplys", $usergroup['canpostreplys']);
	makeyesnocode($lang->can_rate_threads, "canratethreads", $usergroup['canratethreads']);

	tablesubheader($lang->perms_attachments);
	makeyesnocode($lang->can_post_attachments, "canpostattachments", $usergroup['canpostattachments']);
	makeinputcode($lang->attach_quota, "attachquota", $usergroup['attachquota']);

	tablesubheader($lang->perms_editing);
	makeyesnocode($lang->can_edit_posts, "caneditposts", $usergroup['caneditposts']);
	makeyesnocode($lang->can_delete_posts, "candeleteposts", $usergroup['candeleteposts']);
	makeyesnocode($lang->can_delete_threads, "candeletethreads", $usergroup['candeletethreads']);
	makeyesnocode($lang->can_edit_attachments, "caneditattachments", $usergroup['caneditattachments']);

	tablesubheader($lang->perms_reputations);

	makeyesnocode($lang->show_reputations, "usereputationsystem", $usergroup['usereputationsystem']);
	makeyesnocode($lang->can_give_reputations, "cangivereputations", $usergroup['cangivereputations']);
	$reputation_power = array(1 => 1, 2 => 2, 3 => 3);
	makeselectcode_array($lang->reputation_points, "reputationpower", $reputation_power, $usergroup['reputationpower']);
	makeinputcode($lang->max_reputations_day, "maxreputationsday", $usergroup['maxreputationsday'], 4);

	tablesubheader($lang->perms_polls);
	makeyesnocode($lang->can_post_polls, "canpostpolls", $usergroup['canpostpolls']);
	makeyesnocode($lang->can_vote_polls, "canvotepolls", $usergroup['canvotepolls']);

	tablesubheader($lang->perms_pms);
	makeyesnocode($lang->can_use_pms, "canusepms", $usergroup['canusepms']);
	makeyesnocode($lang->can_send_pms, "cansendpms", $usergroup['cansendpms']);
	makeyesnocode($lang->can_track_pms, "cantrackpms", $usergroup['cantrackpms']);
	makeyesnocode($lang->can_deny_pms, "candenypmreceipts", $usergroup['candenypmreceipts']);
	makeinputcode($lang->pm_quota, "pmquota", $usergroup['pmquota'], 4);

	tablesubheader($lang->perms_calendar);
	makeyesnocode($lang->can_view_calendar, "canviewcalendar", $usergroup['canviewcalendar']);
	makeyesnocode($lang->can_add_public, "canaddpublicevents", $usergroup['canaddpublicevents']);
	makeyesnocode($lang->can_add_private, "canaddprivateevents", $usergroup['canaddprivateevents']);

	tablesubheader($lang->perms_wol);
	makeyesnocode($lang->can_view_wol, "canviewonline", $usergroup['canviewonline']);
	makeyesnocode($lang->can_view_invisible, "canviewwolinvis", $usergroup['canviewwolinvis']);
	makeyesnocode($lang->can_view_ips, "canviewonlineips", $usergroup['canviewonlineips']);

	tablesubheader($lang->perms_account);
	makeyesnocode($lang->can_access_ucp, "canusercp", $usergroup['canusercp']);
	makeyesnocode($lang->can_change_name, "canchangename", $usergroup['canchangename']);
	makeyesnocode($lang->can_custom_titles, "cancustomtitle", $usergroup['cancustomtitle']);
	makeyesnocode($lang->can_upload_avatars, "canuploadavatars", $usergroup['canuploadavatars']);

	tablesubheader($lang->perms_admin);
	makeyesnocode($lang->can_access_acp, "cancp", $usergroup['cancp']);
	makeyesnocode($lang->is_smod, "issupermod", $usergroup['issupermod']);

	tablesubheader($lang->perms_misc);
	makeyesnocode($lang->can_view_mlist, "canviewmemberlist", $usergroup['canviewmemberlist']);
	makeyesnocode($lang->can_send_emails, "cansendemail", $usergroup['cansendemail']);
	endtable();
	endform($lang->update_group, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "editgroupleader")
{
	$lid = intval($mybb->input['lid']);

	$query = $db->query("SELECT l.*, u.username FROM (".TABLE_PREFIX."groupleaders l, ".TABLE_PREFIX."users u) WHERE l.uid=u.uid AND l.lid='$lid'");
	$leader = $db->fetch_array($query);
	if(!$leader['uid'])
	{
		cperror($lang->invalid_leader);
	}

	cpheader();
	startform("usergroups.php", "", "do_editgroupleader");
	makehiddencode("gid", $leader['gid']);
	makehiddencode("lid", $leader['lid']);
	starttable();
	tableheader($lang->edit_leader);
	makeinputcode($lang->username, "username", $leader['username']);
	makeyesnocode($lang->can_manage_members, "canmanagemembers", $leader['canmanagemembers']);
	makeyesnocode($lang->can_manage_requests, "canmanagerequests", $leader['canmanagerequests']);
	endtable();
	endform($lang->edit_leader);
	cpfooter();
}

if($mybb->input['action'] == "groupleaders")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='".intval($mybb->input['gid'])."'");
	$usergroup = $db->fetch_array($query);

	cpheader();
	$lang->manage_group_leaders_for = sprintf($lang->manage_group_leaders_for, $usergroup['title']);
	startform("usergroups.php", "", "do_groupleaders");
	makehiddencode("gid", $mybb->input['gid']);
	starttable();
	tableheader($lang->manage_group_leaders_for, "", 2);
	tablesubheader($lang->existing_leaders, "", 2);
	$query = $db->query("SELECT l.*, u.username FROM ".TABLE_PREFIX."groupleaders l LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid) WHERE l.gid='".intval($mybb->input['gid'])."' ORDER BY u.username ASC");
	while($leader = $db->fetch_array($query))
	{
		$edit = makelinkcode($lang->edit_leader, "usergroups.php?action=editgroupleader&lid=".$leader['lid']);
		$delete = makelinkcode($lang->delete_leader, "usergroups.php?action=do_deletegroupleader&gid=".$mybb->input['gid']."&uid=".$leader['uid']);
		$editprofile = makelinkcode($lang->edit_profile, "users.php?action=edit&uid=".$leader['uid']);
		makelabelcode("<a href=\"../member.php?action=profile&uid=".$leader['uid']."\">".$leader['username']."</a>", "$edit $delete $editprofile");
	}
	if(!$editprofile) // Talk about cheating!
	{
		makelabelcode("<center>$lang->no_group_leaders</center>", "", 2);
	}
	endtable();

	startform("usergroups.php", "", "do_addgroupleader");
	makehiddencode("gid", $mybb->input['gid']);
	starttable();
	tableheader($lang->add_new_leader);
	makeinputcode($lang->username, "username");
	makeyesnocode($lang->can_manage_members, "canmanagemembers");
	makeyesnocode($lang->can_manage_requests, "canmanagerequests");
	endtable();
	endform($lang->add_leader);
	cpfooter();
}

if($mybb->input['action'] == "do_joinrequests")
{
	if(is_array($mybb->input['request']))
	{
		foreach($mybb->input['request'] as $uid => $what)
		{
			if($what == "accept")
			{
				join_usergroup(intval($uid), $gid);
				$uidin[] = $uid;
			}
			elseif($what == "decline")
			{
				$uidin[] = $uid;
			}
		}
	}
	if(is_array($uidin))
	{
		$uids = implode(",", $uidin);
		$db->query("DELETE FROM ".TABLE_PREFIX."joinrequests WHERE uid IN($uids)");
	}
	cpredirect("usergroups.php", $lang->join_requests_moderated);
}

if($mybb->input['action'] == "joinrequests")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='".intval($mybb->input['gid'])."'");
	$usergroup = $db->fetch_array($query);
	$query = $db->query("SELECT j.*, u.username FROM ".TABLE_PREFIX."joinrequests j LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=j.uid) WHERE j.gid='".intval($mybb->input['gid'])."' ORDER BY u.username ASC");
	$numrequests = $db->num_rows($query);
	if($numrequests < 1)
	{
		cperror($lang->no_join_requests);
	}
		cpheader();
?>
<script type="text/javascript">
<!--
function radioAll(formName, value)
{
	for(var i=0;i<formName.elements.length;i++)
	{
		var element = formName.elements[i];
		if((element.name != "allbox") && (element.type == "radio")) {
			if(element.value == value)
			{
				element.checked = true;
			}
		}
	}
}

-->
</script>
<?php
	$lang->manage_requests_for = sprintf($lang->manage_requests_for, $usergroup['title']);
	startform("usergroups.php", "reqform", "do_joinrequests");
	makehiddencode("gid", $mybb->input['gid']);
	starttable();
	tableheader($lang->manage_requests_for, "", 5);
	tablesubheader(array($lang->req_username,
						 $lang->reason,
						 $lang->accept." <a href=\"javascript:radioAll(document.reqform, 'accept');\">".$lang->all."</a>",
						 $lang->ignore." <a href=\"javascript:radioAll(document.reqform, 'ignore');\">".$lang->all."</a>",
						 $lang->decline." <a href=\"javascript:radioAll(document.reqform, 'decline');\">".$lang->all."</a>")
	);
	while($user = $db->fetch_array($query))
	{
		$bgcolor = getaltbg();
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\" width=\"25%\"><a href=\"member.php?action=profile&uid=".$user['uid']."\">".$user['username']."</a></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"30%\">".$user['reason']."</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"15%\"><input type=\"radio\" name=\"request[".$user['uid']."]\" value=\"accept\" /></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"15%\"><input type=\"radio\" name=\"request[".$user['uid']."]\" value=\"ignore\" checked=\"checked\" /></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"15%\"><input type=\"radio\" name=\"request[".$user['uid']."]\" value=\"decline\" /></td>\n";
		echo "</tr>\n";
	}
	endtable();
	endform($lang->action_requests);
	cpfooter();
}


if($mybb->input['action'] == "modify" || $mybb->input['action'] == "")
{
	cpheader();

	$query = $db->query("SELECT g.gid, COUNT(u.uid) AS users FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup) GROUP BY gid;");
	while($groupcount = $db->fetch_array($query))
	{
		$primaryusers[$groupcount['gid']] = $groupcount['users'];
	}

	$query = $db->query("SELECT g.gid, COUNT(u.uid) AS users FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."usergroups g ON (CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%')) WHERE g.gid!='' GROUP BY gid;");
	while($groupcount = $db->fetch_array($query))
	{
		$secondaryusers[$groupcount['gid']] = $groupcount['users'];
	}

	$query = $db->query("SELECT g.gid, COUNT(r.uid) AS users FROM ".TABLE_PREFIX."joinrequests r LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=r.gid) GROUP BY gid;");
	while($joinrequest = $db->fetch_array($query))
	{
		$joinrequests[$joinrequest['gid']] = $joinrequest['users'];
	}
?>
<script type="text/javascript">
<!--
function usergroup_hop(gid)
{
	usergroupaction = "usergroup_"+gid;
	action = eval("document.usergroups.usergroup_"+gid+".options[document.usergroups.usergroup_"+gid+".selectedIndex].value");
	window.location = "usergroups.php?action="+action+"&gid="+gid;
}
-->
</script>
<?php
	//startform('', 'usergroups');
	startform('usergroups.php', 'usergroups');

	$hopto[] = "<input type=\"button\" value=\"$lang->create_new_group\" onclick=\"hopto('usergroups.php?action=add');\" class=\"hoptobutton\">";
	makehoptolinks($hopto);
	starttable();
	tableheader($lang->default_groups, '', 4);
	echo "<tr class=\"subheader\">\n";
	echo "<td width=\"55%\">{$lang->title_list}</td>\n";
	echo "<td width=\"20%\" align=\"center\">{$lang->users}</td>\n";
	echo "<td width=\"20%\" align=\"center\">{$lang->controls}</td>\n";
	echo "<td width=\"5%\">{$lang->forum_team_order}</td>\n";
	echo "</tr>\n";

	$sql_options = array(
		'order_by' => 'disporder',
		'order_dir' => 'ASC',
	);
	$query = $db->simple_select(TABLE_PREFIX.'usergroups', '*', "type='1'", $sql_options);

	while($usergroup = $db->fetch_array($query))
	{
		$bgcolor = getaltbg();
		makehiddencode('gid', $usergroup['gid']);
		echo "<tr class=\"{$bgcolor}\">\n";
		echo "<td>{$usergroup['title']}<br><small>{$usergroup['description']}</td>\n";
		echo "<td align=\"center\">".$primaryusers[$usergroup['gid']];
		if($secondaryusers[$usergroup['gid']])
		{
			echo ' ('.$secondaryusers[$usergroup['gid']].')';
		}
		echo "<td align=\"right\" nowrap=\"nowrap\">\n";
		echo "<select name=\"usergroup_{$usergroup['gid']}\" onchange=\"usergroup_hop({$usergroup['gid']});\">\n";
		echo "<option value=\"edit\">{$lang->select_edit}</option>\n";
		echo "<option value=\"listusers\">{$lang->list_users}</option>\n";
		echo "<option value=\"listsecondaryusers\">{$lang->list_secondary_users}</option>\n";
		echo "<option value=\"groupleaders\">{$lang->group_leaders}</option>\n";
		echo "</select>&nbsp;<input type=\"button\" onclick=\"usergroup_hop({$usergroup['gid']});\" value=\"{$lang->go}\"></td>\n";
		if($usergroup['showforumteam'] == 'yes')
			{
				echo "<td align=\"center\"><input type=\"text\" name=\"disporder[{$usergroup['gid']}]\" value=\"{$usergroup['disporder']}\" size=\"2\" /></td>\n";
			}
			else
			{
				echo "<td>&nbsp;</td>\n";
			}
		echo "</tr>\n";
		$donedefault = 1;
	}
	endtable();

	//Get custom, private usergroups
	$sql_options = array(
		'order_by' => 'disporder',
		'order_dir' => 'ASC',
	);
	$query = $db->simple_select(TABLE_PREFIX.'usergroups', '*', "type='2'", $sql_options);
	$count = $db->num_rows($query);
	if($count > 0)
	{
		starttable();
		tableheader($lang->custom_groups, "", 4);
		echo "<tr class=\"subheader\">\n";
		echo "<td width=\"55%\">{$lang->title}</td>\n";
		echo "<td width=\"20%\" align=\"center\">{$lang->users}</td>\n";
		echo "<td width=\"20%\" align=\"center\">{$lang->controls}</td>\n";
		echo "<td width=\"5%\">{$lang->forum_team_order}</td>\n";
		echo "</tr>\n";

		while($usergroup = $db->fetch_array($query))
		{
			$bgcolor = getaltbg();
			//startform('usergroups.php');
			makehiddencode('gid', $usergroup['gid']);
			echo "<tr class=\"{$bgcolor}\">\n";
			echo "<td>{$usergroup['title']}</td>\n";
			echo "<td align=\"center\">".$primaryusers[$usergroup['gid']];
			if($secondaryusers[$usergroup['gid']])
			{
				echo " (".$secondaryusers[$usergroup['gid']].")";
			}
			echo "</td>\n";
			echo "<td align=\"center\" nowrap=\"nowrap\">\n";
			echo "<select name=\"usergroup_{$usergroup['gid']}\" onchange=\"usergroup_hop({$usergroup['gid']});\">\n";
			echo "<option value=\"edit\">{$lang->select_edit}</option>\n";
			echo "<option value=\"delete\">{$lang->select_delete}</option>\n";
			echo "<option value=\"listusers\">{$lang->list_users}</option>\n";
			echo "<option value=\"listsecondaryusers\">{$lang->list_secondary_users}</option>\n";
			echo "<option value=\"groupleaders\">{$lang->group_leaders}</option>\n";
			echo "</select>&nbsp;<input type=\"button\" onclick=\"usergroup_hop({$usergroup['gid']});\" value=\"{$lang->go}\"></td>\n";
			if($usergroup['showforumteam'] == 'yes')
			{
				echo "<td align=\"center\"><input type=\"text\" name=\"disporder[{$usergroup['gid']}]\" value=\"{$usergroup['disporder']}\" size=\"2\" /></td>\n";
			}
			else
			{
				echo "<td>&nbsp;</td>\n";
			}
			echo "</tr>\n";
			$donecustom = 1;
		}
		endtable();
	}
	unset($count);


	//Get custom pubic/private user groups
	$sql_options = array(
		'order_by' => 'disporder',
		'order_dir' => 'ASC',
	);
	$query = $db->simple_select(TABLE_PREFIX.'usergroups', '*', "type='3' OR type='4'", $sql_options);
	$count = $db->num_rows($query);
	if($count > 0)
	{
		starttable();
		tableheader($lang->public_custom_groups, "", 5);
		echo "<tr class=\"subheader\">\n";
		echo "<td width=\"55%\">{$lang->title}</td>\n";
		echo "<td width=\"10%\" align=\"center\">{$lang->users}</td>\n";
		echo "<td width=\"10%\" align=\"center\">{$lang->join_requests}</td>\n";
		echo "<td width=\"20%\" align=\"center\">{$lang->controls}</td>\n";
		echo "<td width=\"5%\">{$lang->forum_team_order}</td>\n";
		echo "</tr>\n";
		while($usergroup = $db->fetch_array($query))
		{
			$bgcolor = getaltbg();
			//startform('usergroups.php');
			makehiddencode('gid', $usergroup['gid']);
			echo "<tr class=\"{$bgcolor}\">\n";
			echo "<td>{$usergroup['title']}</td>\n";
			echo "<td align=\"center\">".$primaryusers[$usergroup['gid']];
			if($secondaryusers[$usergroup['gid']])
			{
				echo ' ('.$secondaryusers[$usergroup['gid']].')';
			}
			$modrequests = '';
			if($joinrequests[$usergroup['gid']] > 0)
			{
				$usergroup['joinrequests'] = "<span class=\"highlight1\">".$joinrequests[$usergroup['gid']]."</span>";
				$modrequests = "<option value=\"joinrequests\">{$lang->moderate_join_requests}</option>\n";
			}
			echo "</td>\n";
			echo "<td align=\"center\">$usergroup[joinrequests]</td>\n";
			echo "<td align=\"center\" nowrap=\"nowrap\">\n";
			echo "<select name=\"usergroup_{$usergroup['gid']}\" onchange=\"usergroup_hop({$usergroup['gid']});\">\n";
			echo "<option value=\"edit\">{$lang->select_edit}</option>\n";
			echo "<option value=\"delete\">{$lang->select_delete}</option>\n";
			echo "<option value=\"listusers\">{$lang->list_users}</option>\n";
			echo "<option value=\"listsecondaryusers\">{$lang->list_secondary_users}</option>\n";
			echo "<option value=\"groupleaders\">{$lang->group_leaders}</option>\n";
			echo "<option value=\"joinrequests\">{$lang->moderate_join_requests}</option>\n";
			echo "</select>&nbsp;<input type=\"button\" onclick=\"usergroup_hop({$usergroup['gid']});\" value=\"{$lang->go}\"></td>\n";
			if($usergroup['showforumteam'] == 'yes')
			{
				echo "<td align=\"center\"><input type=\"text\" name=\"disporder[{$usergroup['gid']}]\" value=\"{$usergroup['disporder']}\" size=\"2\" /></td>\n";
			}
			else
			{
				echo "<td>&nbsp;</td>\n";
			}
			echo "</tr>\n";
		}
		//endform();
		endtable();
	}
	makehiddencode('action', 'disporder');
	endform($lang->update_orders, $lang->reset);
	cpfooter();
}
?>