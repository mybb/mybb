
<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */
 
 /**
  * TODO:
  *   Add
  *   Edit
  *   Display Order
  *   Group Leaders
  *   Join Requests
  */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item("User Groups", "index.php?".SID."&amp;module=user/groups");

if($mybb->input['action'] == "add" || !$mybb->input['action'])
{
	$sub_tabs['manage_groups'] = array(
		'title' => "User Groups",
		'link' => "index.php?".SID."&amp;module=user/groups",
		'description' => ""
	);
	$sub_tabs['add_group'] = array(
		'title' => "Add New User Group",
		'link' => "index.php?".SID."&amp;module=user/groups&amp;action=add",
	);
}

if($mybb->input['action'] == "export")
{
	// Log admin action
	log_admin_action();

	$gidwhere = "";
	if($mybb->input['gid'])
	{
		$gidwhere = "WHERE gid='".intval($mybb->input['gid'])."'";
	}
	$xml = "<?xml version=\"1.0\" encoding=\"{$lang->settings['charset']}\"?".">\n";
	$xml = "<usergroups version=\"{$mybb->version_code}\" exported=\"".time()."\">\n";

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups $gidwhere ORDER BY gid ASC");
	while($usergroup = $db->fetch_array($query))
	{
		$xml .= "\t\t<usergroup>\n";
		foreach($usergroup as $key => $value)
		{
			$xml .= "\t\t\t<{$key}><![CDATA[{$value}]]></{$key}>\n";
		}
		$xml .= "\t\t</usergroup>\n";
	}

	$xml .= "</usergroups>";
	$mybb->settings['bbname'] = urlencode($mybb->settings['bbname']);

	header("Content-disposition: filename=".$mybb->settings['bbname']."-usergroups.xml");
	header("Content-Length: ".my_strlen($xml));
	header("Content-type: unknown/unknown");
	header("Pragma: no-cache");
	header("Expires: 0");
	echo $xml;
	exit;	
}

if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this new user group";
		}

		if(!$errors)
		{
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

			if(my_strpos($mybb->input['namestyle'], "{username}") === false)
			{
				$mybb->input['namestyle'] = "{username}";
			}

			if($mybb->input['stars'] < 1)
			{
				$mybb->input['stars'] = 0;
			}

			$new_usergroup = array(
				"type" => $mybb->input['type'],
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"namestyle" => $db->escape_string($mybb->input['namestyle']),
				"usertitle" => $db->escape_string($mybb->input['usertitle']),
				"stars" => intval($mybb->input['stars']),
				"starimage" => $db->escape_string($mybb->input['starimage']),
				"image" => $db->escape_string($mybb->input['image']),
				"disporder" => 0,
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
				"maxpmrecipients" => intval($mybb->input['maxpmrecipients']),
				"cansendemail" => $db->escape_string($mybb->input['cansendemail']),
				"maxemails" => intval($mybb->input['maxemails']),
				"canviewmemberlist" => $db->escape_string($mybb->input['canviewmemberlist']),
				"canviewcalendar" => $db->escape_string($mybb->input['canviewcalendar']),
				"canaddevents" => $db->escape_string($mybb->input['canaddevents']),
				"canbypasseventmod" => $db->escape_string($mybb->input['canbypasseventmod']),
				"canmoderateevents" => $db->escape_string($mybb->input['canmoderateevents']),
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
				"cancustomtitle" => $db->escape_string($mybb->input['cancustomtitle']),
				"canwarnusers" => $db->escape_string($mybb->input['canwarnusers']),
				"canreceivewarnings" => $db->escape_string($mybb->input['canreceivewarnings']),
				"maxwarningsday" => intval($mybb->input['maxwarningsday'])
			);
			
			$gid = $db->insert_query("usergroups", $new_usergroup);

			// Update the caches
			$cache->update_usergroups();
			$cache->update_forumpermissions();

			// Log admin action
			log_admin_action($gid, $mybb->input['title']);
			
			flash_message("The new user group has successfully been created", 'success');
			admin_redirect("index.php?".SID."&module=user/groups");
		}
	}

		$page->add_breadcrumb_item("Add New User Title");
	$page->output_header("User Titles - Add User Title");
	
	$page->output_nav_tabs($sub_tabs, 'add_title');
	$form = new Form("index.php?".SID."&amp;module=user/titles&amp;action=add", "post");
	
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}

}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("usergroups", "*", "gid='".intval($mybb->input['gid'])."'");
	$usergroup = $db->fetch_array($query);

	if(!$usergroup['gid'])
	{
		flash_message("You have specified an invalid user group", 'error');
		admin_redirect("index.php?".SID."&module=user/group");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = "You did not enter a title for this user group";
		}

		if(!$errors)
		{
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

			if($usergroup['type'] == 1)
			{
				$mybb->input['type'] = 1;
			}

			if(my_strpos($mybb->input['namestyle'], "{username}") === false)
			{
				$mybb->input['namestyle'] = "{username}";
			}

			if($mybb->input['stars'] < 1)
			{
				$mybb->input['stars'] = 0;
			}

			$updated_group = array(
				"type" => intval($mybb->input['type']),
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"namestyle" => $db->escape_string($mybb->input['namestyle']),
				"usertitle" => $db->escape_string($mybb->input['usertitle']),
				"stars" => intval($mybb->input['stars']),
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
				"maxpmrecipients" => intval($mybb->input['maxpmrecipients']),
				"cansendemail" => $db->escape_string($mybb->input['cansendemail']),
				"maxemails" => intval($mybb->input['maxemails']),		
				"canviewmemberlist" => $db->escape_string($mybb->input['canviewmemberlist']),
				"canviewcalendar" => $db->escape_string($mybb->input['canviewcalendar']),
				"canaddevents" => $db->escape_string($mybb->input['canaddevents']),
				"canbypasseventmod" => $db->escape_string($mybb->input['canbypasseventmod']),
				"canmoderateevents" => $db->escape_string($mybb->input['canmoderateevents']),
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
				"cancustomtitle" => $db->escape_string($mybb->input['cancustomtitle']),
				"canwarnusers" => $db->escape_string($mybb->input['canwarnusers']),
				"canreceivewarnings" => $db->escape_string($mybb->input['canreceivewarnings']),
				"maxwarningsday" => intval($mybb->input['maxwarningsday'])
			);

			// Only update the candisplaygroup setting if not a default user group
			if($usergroup['type'] != 1)
			{
				$updated_group['candisplaygroup'] = $db->escape_string($mybb->input['candisplaygroup']);
			}

			$db->update_query("usergroups", $updated_group, "gid='{$usergroup['gid']}'");

			// Update the caches
			$cache->update_usergroups();
			$cache->update_forumpermissions();

			// Log admin action
			log_admin_action($usergroup['gid'], $mybb->input['title']);
			
			flash_message("The user group has successfully been updated", 'success');
			admin_redirect("index.php?".SID."&module=user/groups");
		}
	}	
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("usergroups", "*", "gid='".intval($mybb->input['gid'])."'");
	$usergroup = $db->fetch_array($query);

	if(!$usergroup['gid'])
	{
		flash_message("You have specified an invalid user group", 'error');
		admin_redirect("index.php?".SID."&module=user/groups");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=user/groups");
	}

	if($mybb->request_method == "post")
	{
		// Move any users back to the registered group
		$updated_users = array("usergroup" => 2);
		$db->update_query("users", $updated_users, "usergroup='{$usergroup['gid']}'");

		$updated_users = array("displaygroup" => "usergroup");
		$db->update_query("users", $updated_users, "displaygroup='{$usergroup['gid']}'", "", false); // No quotes = displaygroup=usergroup

		$db->delete_query("groupleaders", "gid='{$usergroup['gid']}'");
		$db->delete_query("usergroups", "gid='{$usergroup['gid']}'");

		// Log admin action
		log_admin_action($usergroup['title']);


		flash_message("The specified user group has successfully been deleted.", 'success');
		admin_redirect("index.php?".SID."&module=user/groups");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=user/groups&amp;action=delete&amp;gid={$usergroup['gid']}", "Are you sure you want to delete this user group?");
	}
}

if($mybb->input['action'] == "disporder")
{
	if($mybb->request_method == "post")
	{
	}
}

if(!$mybb->input['action'])
{
}
?>