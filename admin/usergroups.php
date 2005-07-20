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
$lang->load("usergroups");

if($mybb->input['action'] == "listusers")
{
	header("Location: users.php?action=find&search[usergroup]=$gid");
	exit;
}
if($mybb->input['action'] == "listsecondaryusers")
{
	header("Location: users.php?action=find&search[additionalusergroups]=$gid");
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
}

checkadminpermissions("caneditugroups");
logadmin();

if($mybb->input['action'] == "do_add")
{
	$namestyle = $_POST['namestyle'];
	if($joinable == "yes")
	{
		if($moderate == "yes")
		{
			$type = "4";
		}
		else
		{
			$type = "3";
		}
	}
	else
	{
		$type = "2";
	}
	if(strpos($namestyle, "{username}") === false)
	{
		$namestyle = "{username}";
		$namenote = $lang->error_namenote;
	}
	$namestyle = addslashes($namestyle);
	$description = addslashes($_POST['description']);
	$title = addslashes($_POST['title']);
	$usertitle = addslashes($_POST['usertitle']);
	$ustars = intval($_POST['ustars']);
	if($ustars < 1)
	{
		$ustars = 0;
	}
	$image = addslashes($_POST['image']);
	$db->query("INSERT INTO ".TABLE_PREFIX."usergroups (gid,type,title,description,namestyle,usertitle,stars,starimage,image,isbannedgroup,canview,canviewprofiles,candlattachments,canpostthreads,canpostreplys,canpostattachments,canratethreads,caneditposts,candeleteposts,candeletethreads,caneditattachments,canpostpolls,canvotepolls,canusepms,cansendpms,cantrackpms,candenypmreceipts,pmquota,cansendemail,canviewmemberlist,canviewcalendar,canaddpublicevents,canaddprivateevents,canviewonline,canviewwolinvis,canviewonlineips,cancp,issupermod,cansearch,canusercp,canuploadavatars,canratemembers,canchangename,showforumteam,usereputationsystem,cangivereputations,reputationpower,maxreputationsday,candisplaygroup,attachquota,cancustomtitle) VALUES (NULL,'$type','$title','$description','$namestyle','$usertitle','$ustars','$starimage','$image','$isbannedgroup','$canview','$canviewprofiles','$candlattachments','$canpostthreads','$canpostreplys','$canpostattachments','$canratethreads','$caneditposts','$candeleteposts','$candeletethreads','$caneditattachments','$canpostpolls','$canvotepolls','$canusepms','$cansendpms','$cantrackpms','$candenypmreceipts','$pmquota','$cansendemail','$canviewmemberlist','$canviewcalendar','$canaddpublicevents','$canaddprivateevents','$canviewonline','$canviewwolinvis','$canviewonlineips','$cancp','$issupermod','$cansearch','$canusercp','$canuploadavatars','$canratemembers','$canchangename','$showforumteam','$usereputationsystem','$cangivereputations','$reputationpower','$maxreputationsday','$candisplaygroup','$attachquota','$cancustomtitle')");
	
	$cache->updateusergroups();
	$cache->updateforumpermissions();
	cpredirect("usergroups.php", $lang->group_added.$namenote);
}

if($mybb->input['action'] == "do_deletegroupleader")
{
	$db->query("DELETE FROM ".TABLE_PREFIX."groupleaders WHERE uid='$uid' AND gid='$gid'");
	cpredirect("usergroups.php?action=groupleaders&gid=$gid", $lang->leader_deleted);
}

if($mybb->input['action'] == "do_addgroupleader")
{
	$username = addslashes($_POST['username']);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='$username'");
	$user = $db->fetch_array($query);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='$gid'");
	$usergroup = $db->fetch_array($query);
	if(!$user['username'])
	{
		cperror($lang->add_leader_no_user);
	}
	$db->query("INSERT INTO ".TABLE_PREFIX."groupleaders (lid,gid,uid) VALUES (NULL,'$gid','$user[uid]')");
	$lang->leader_added = sprintf($lang->leader_added, $usergroup['title']);
	cpredirect("usergroups.php?action=groupleaders&gid=$gid", $lang->leader_added);
}

if($mybb->input['action'] == "do_delete")
{
	if($deletesubmit)
	{	
		$db->query("DELETE FROM ".TABLE_PREFIX."usergroups WHERE gid='$gid' AND type!='1'");
		$db->query("UPDATE ".TABLE_PREFIX."users SET usergroup='2' WHERE usergroup='$gid'");
		$db->query("UPDATE ".TABLE_PREFIX."users SET displaygroup=usergroup WHERE displaygroup='$gid'");
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
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='$gid'");
	$usergroup = $db->fetch_array($query);
	if($joinable == "yes")
	{
		if($moderate == "yes")
		{
			$type = "4";
		}
		else
		{
			$type = "3";
		}
	}
	else
	{
		$type = "2";
	}
	if($usergroup['type'] == "1")
	{
		$type = 1;
	}
	if(strpos($namestyle, "{username}") === false)
	{
		$namestyle = "{username}";
		$namenote = $lang->error_namenote;
	}
	$description = addslashes($_POST['description']);
	$title = addslashes($_POST['title']);
	$usertitle = addslashes($_POST['usertitle']);
	$ustars = intval($_POST['ustars']);
	if($ustars < 1)
	{
		$ustars = 0;
	}
	$image = addslashes($_POST['image']);
	$db->query("UPDATE ".TABLE_PREFIX."usergroups SET type='$type', title='$title', description='$description', namestyle='$namestyle', usertitle='$usertitle', stars='$stars', starimage='$starimage', image='$image', isbannedgroup='$isbannedgroup', canview='$canview', canviewprofiles='$canviewprofiles', candlattachments='$candlattachments', canpostthreads='$canpostthreads', canpostreplys='$canpostreplys', canpostattachments='$canpostattachments', canratethreads='$canratethreads', caneditposts='$caneditposts', candeleteposts='$candeleteposts', candeletethreads='$candeletethreads', caneditattachments='$caneditattachments', canpostpolls='$canpostpolls', canvotepolls='$canvotepolls', canusepms='$canusepms', cansendpms='$cansendpms', cantrackpms='$cantrackpms', candenypmreceipts='$candenypmreceipts', pmquota='$pmquota', cansendemail='$cansendemail', canviewmemberlist='$canviewmemberlist', canviewcalendar='$canviewcalendar', canaddpublicevents='$canaddpublicevents', canaddprivateevents='$canaddprivateevents', canviewonline='$canviewonline', canviewwolinvis='$canviewwolinvis', canviewonlineips='$canviewonlineips', cancp='$cancp', issupermod='$issupermod', cansearch='$cansearch', canusercp='$canusercp', canuploadavatars='$canuploadavatars', canratemembers='$canratemembers', canchangename='$canchangename', showforumteam='$showforumteam', usereputationsystem='$usereputationsystem', cangivereputations='$cangivereputations', reputationpower='$reputationpower', maxreputationsday='$maxreputationsday', candisplaygroup='$candisplaygroup', attachquota='$attachquota', cancustomtitle='$cancustomtitle' WHERE gid='$gid'");
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
	makeinputcode($lang->namestyle, "namestyle");
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
	makeyesnocode($lang->can_rate_members, "canratemembers", "yes");
	endtable();
	endform($lang->add_group, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "delete")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='$gid'");
	$usergroup = $db->fetch_array($query);
	$lang->delete_group = sprintf($lang->delete_group, $usergroup['title']);
	$lang->confirm_delete_group = sprintf($lang->confirm_delete_group, $usergroup['title']);
	cpheader();
	startform("usergroups.php", "", "do_delete");
	makehiddencode("gid", $gid);
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
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='$gid'");
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
	$usergroup['description'] = stripslashes($usergroup['description']);
	$usergroup['namestyle'] = stripslashes($usergroup['namestyle']);
	$lang->edit_group = sprintf($lang->edit_group, $usergroup['title']);
	cpheader();
	startform("usergroups.php", "", "do_edit");
	makehiddencode("gid", $gid);
	starttable();
	tableheader($lang->edit_group);
	makeinputcode($lang->title, "title", $usergroup['title']);
	maketextareacode($lang->description, "description", $usergroup['description']);
	makeinputcode($lang->namestyle, "namestyle", $usergroup['namestyle']);
	makeinputcode($lang->usertitle, "usertitle", $usergroup['usertitle']);
	makeinputcode($lang->stars, "stars", $usergroup['stars']);
	makeinputcode($lang->star_image, "starimage", $usergroup['starimage']);
	makeinputcode($lang->group_image, "image", $usergroup['image']);

	tablesubheader($lang->group_options);
	if($usergroup['gid'] != "1" && $usergroup['gid'] != "5" && $usergroup['gid'] != "6")
	{
		makeyesnocode($lang->show_team_page, "showforumteam", $usergroup['showforumteam']);
	}
	makeyesnocode($lang->banned_group, "isbannedgroup", $usergroup['isbannedgroup']);

	if($usergroup['type'] != 1)
	{
		tablesubheader($lang->perms_joinable);
		makeyesnocode($lang->can_join_group, "joinable", $joinable);
		makeyesnocode($lang->moderate_joins, "moderate", $moderate);
		makeyesnocode($lang->can_display_group, "candisplaygroup", $usergroup['candisplaygroupo']);
	}

	tablesubheader($lang->perms_viewing);
	makeyesnocode($lang->can_view_board, "canview", $usergroup['canview']);
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
	makeinputcode($lang->reputation_points, "reputationpower", $usergroup['reputationpower'], 4);
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
	makeyesnocode($lang->can_rate_members, "canratemembers", $usergroup['canratemembers']);
	endtable();
	endform($lang->update_group, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "groupleaders")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='$gid'");
	$usergroup = $db->fetch_array($query);

	cpheader();
	$lang->manage_group_leaders_for = sprintf($lang->manage_group_leaders_for, $usergroup['title']);
	startform("usergroups.php", "", "do_groupleaders");
	makehiddencode("gid", $gid);
	starttable();
	tableheader($lang->manage_group_leaders_for);
	tablesubheader($lang->existing_leaders);
	$query = $db->query("SELECT l.*, u.username FROM ".TABLE_PREFIX."groupleaders l LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid) WHERE l.gid='$gid' ORDER BY u.username ASC");
	while($leader =  $db->fetch_array($query))
	{
		$delete = makelinkcode($lang->delete_leader, "usergroups.php?action=do_deletegroupleader&gid=$gid&uid=".$leader['uid']);
		$editprofile = makelinkcode($lang->edit_profile, "users.php?action=edit&uid=".$leader['uid']);
		makelabelcode("<a href=\"../member.php?action=profile&uid=".$leader['uid']."\">".$leader['username']."</a> $delete $editprofile", "", 2);
	}
	if(!$editprofile) // Talk about cheating!
	{
		makelabelcode("<center>$lang->no_group_leaders</center>", "", 2);
	}
	endtable();

	startform("usergroups.php", "", "do_addgroupleader");
	makehiddencode("gid", $gid);
	starttable();
	tableheader($lang->add_new_leader);
	makeinputcode($lang->username, "username");
	endtable();
	endform($lang->add_leader);
	cpfooter();
}

if($mybb->input['action'] == "do_joinrequests")
{
	if(is_array($request))
	{
		foreach($request as $uid => $what)
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
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='$gid'");
	$usergroup = $db->fetch_array($query);
	$query = $db->query("SELECT j.*, u.username FROM ".TABLE_PREFIX."joinrequests j LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=j.uid) WHERE j.gid='$gid' ORDER BY u.username ASC");
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
	makehiddencode("gid", $gid);
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
	startform("", "usergroups");
	$hopto[] = "<input type=\"button\" value=\"$lang->create_new_group\" onclick=\"hopto('usergroups.php?action=add');\" class=\"hoptobutton\">";
	makehoptolinks($hopto);
	starttable();
	tableheader($lang->default_groups, "", 4);
	echo "<tr>\n";
	echo "<td class=\"subheader\">$lang->title_list</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->users</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->controls</td>\n";
	echo "</tr>\n";
	$query = $db->query("SELECT g.*, COUNT(u.uid) AS users, COUNT(u2.uid) AS secondaryusers FROM ".TABLE_PREFIX."usergroups g LEFT JOIN ".TABLE_PREFIX."users u ON (u.usergroup=g.gid) LEFT JOIN ".TABLE_PREFIX."users u2 ON (CONCAT(',', u2.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%')) WHERE g.type='1' GROUP BY g.gid ORDER BY g.title");
	while($usergroup = $db->fetch_array($query))
	{
		$bgcolor = getaltbg();
		startform("usergroups.php");
		makehiddencode("gid", $usergroup['gid']);
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\">$usergroup[title]<br><small>$usergroup[description]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\">$usergroup[users]";
		if($usergroup['secondaryusers'])
		{
			echo " ($usergroup[secondaryusers])";
		}
		echo "<td class=\"$bgcolor\" align=\"right\" nowrap=\"nowrap\">\n";
		echo "<select name=\"usergroup_$usergroup[gid]\" onchange=\"usergroup_hop($usergroup[gid]);\">\n";
		echo "<option value=\"edit\">$lang->select_edit</option>\n";
		echo "<option value=\"listusers\">$lang->list_users</option>\n";
		echo "<option value=\"listsecondaryusers\">$lang->list_secondary_users</option>\n";
		echo "</select>&nbsp;<input type=\"button\" onclick=\"usergroup_hop($usergroup[gid]);\" value=\"$lang->go\"></td>\n";
		echo "</tr>\n";
		$donedefault = 1;
	}
	endtable();

	$query = $db->query("SELECT g.*, COUNT(u.uid) AS users, COUNT(u2.uid) AS secondaryusers FROM ".TABLE_PREFIX."usergroups g LEFT JOIN ".TABLE_PREFIX."users u ON (u.usergroup=g.gid) LEFT JOIN ".TABLE_PREFIX."users u2 ON (CONCAT(',', u2.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%')) WHERE g.type='2' GROUP BY g.gid ORDER BY g.title");
	$count = $db->num_rows($query);
	if($count > 0)
	{
		starttable();
		tableheader($lang->custom_groups, "", 4);
		echo "<tr>\n";
		echo "<td class=\"subheader\">$lang->title</td>\n";
		echo "<td class=\"subheader\" align=\"center\">$lang->users</td>\n";
		echo "<td class=\"subheader\" align=\"center\">$lang->controls</td>\n";
		echo "</tr>\n";
		while($usergroup = $db->fetch_array($query))
		{
			$bgcolor = getaltbg();
			startform("usergroups.php");
			makehiddencode("gid", $usergroup['gid']);
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\">$usergroup[title]</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">$usergroup[users]";
			if($usergroup['secondaryusers'])
			{
				echo " ($usergroup[secondaryusers])";
			}
			echo "</td>\n";
			echo "<td class=\"$bgcolor\" align=\"right\" nowrap=\"nowrap\">\n";
			echo "<select name=\"usergroup_$usergroup[gid]\" onchange=\"usergroup_hop($usergroup[gid]);\">\n";
			echo "<option value=\"edit\">$lang->select_edit</option>\n";
			echo "<option value=\"delete\">$lang->select_delete</option>\n";
			echo "<option value=\"listusers\">$lang->list_users</option>\n";
			echo "<option value=\"listsecondaryusers\">$lang->list_secondary_users</option>\n";
			echo "<option value=\"groupleaders\">$lang->group_leaders</option>\n";
			echo "</select>&nbsp;<input type=\"button\" onclick=\"usergroup_hop($usergroup[gid]);\" value=\"$lang->go\"></td>\n";
			echo "</tr>\n";
			$donecustom = 1;
		}
		endtable();
	}
	unset($count);
	$query = $db->query("SELECT g.*, COUNT(u.uid) AS users, COUNT(u2.uid) AS secondaryusers, COUNT(j.uid) AS joinrequests FROM ".TABLE_PREFIX."usergroups g LEFT JOIN ".TABLE_PREFIX."users u ON (u.usergroup=g.gid) LEFT JOIN ".TABLE_PREFIX."users u2 ON (CONCAT(',', u2.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%')) LEFT JOIN ".TABLE_PREFIX."joinrequests j ON (j.gid=g.gid) WHERE g.type='3' OR g.type='4' GROUP BY g.gid ORDER BY g.title");
	$count = $db->num_rows($query);
	if($count > 0)
	{
		starttable();
		tableheader($lang->public_custom_groups, "", 5);
		echo "<tr>\n";
		echo "<td class=\"subheader\">$lang->title</td>\n";
		echo "<td class=\"subheader\" align=\"center\">$lang->users</td>\n";
		echo "<td class=\"subheader\" align=\"center\">$lang->join_requests</td>\n";
		echo "<td class=\"subheader\" align=\"center\">$lang->controls</td>\n";
		echo "</tr>\n";
		while($usergroup = $db->fetch_array($query))
		{
			$bgcolor = getaltbg();
			startform("usergroups.php");
			makehiddencode("gid", $usergroup['gid']);
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\">$usergroup[title]</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">$usergroup[users]";
			if($usergroup['secondaryusers'])
			{
				echo " ($usergroup[secondaryusers])";
			}
			$modrequests = "";
			if($usergroup['joinrequests'] > 0)
			{
				$usergroup['joinrequests'] = "<span class=\"highlight1\">".$usergroup['joinrequests']."</span>";
				$modrequests = "<option value=\"joinrequests\">$lang->moderate_join_requests</option>";
			}
			echo "</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">$usergroup[joinrequests]</td>\n";
			echo "<td class=\"$bgcolor\" align=\"right\" nowrap=\"nowrap\">\n";
			echo "<select name=\"usergroup_$usergroup[gid]\" onchange=\"usergroup_hop($usergroup[gid]);\">\n";
			echo "<option value=\"edit\">$lang->select_edit</option>\n";
			echo "<option value=\"delete\">$lang->select_delete</option>\n";
			echo "<option value=\"listusers\">$lang->list_users</option>\n";
			echo "<option value=\"listsecondaryusers\">$lang->list_secondary_users</option>\n";
			echo "<option value=\"groupleaders\">$lang->group_leaders</option>\n";
			echo "<option value=\"joinrequests\">$lang->moderate_join_requests</option>\n";
			echo "</select>&nbsp;<input type=\"button\" onclick=\"usergroup_hop($usergroup[gid]);\" value=\"$lang->go\"></td>\n";
			echo "</tr>\n";
		}
		endform();
		endtable();
	}
	cpfooter();
}
?>