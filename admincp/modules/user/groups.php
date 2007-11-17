
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

$page->add_breadcrumb_item($lang->user_groups, "index.php?".SID."&amp;module=user/groups");

if($mybb->input['action'] == "add" || !$mybb->input['action'])
{
	$sub_tabs['manage_groups'] = array(
		'title' => $lang->manage_user_groups,
		'link' => "index.php?".SID."&amp;module=user/groups",
		'description' => $lang->manage_user_groups_desc
	);
	$sub_tabs['add_group'] = array(
		'title' => $lang->add_user_group,
		'link' => "index.php?".SID."&amp;module=user/groups&amp;action=add",
		'description' => $lang->add_user_group_desc
	);
}

if($mybb->input['action'] == "export")
{
	// Log admin action
	log_admin_action();

	$gidwhere = "";
	if($mybb->input['gid'])
	{
		$gidwhere = "gid='".intval($mybb->input['gid'])."'";
	}
	$xml = "<?xml version=\"1.0\" encoding=\"{$lang->settings['charset']}\"?".">\n";
	$xml = "<usergroups version=\"{$mybb->version_code}\" exported=\"".time()."\">\n";

	$query = $db->simple_select("usergroups", "*", $gidwhere, array('order_by' => 'gid', 'order_dir' => 'ASC'));
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
			$errors[] = $lang->error_missing_title;
		}

		if(!$errors)
		{
			if($mybb->input['joinable'] == 1)
			{
				if($mybb->input['moderate'] == 1)
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
			
			flash_message($lang->success_group_created, 'success');
			admin_redirect("index.php?".SID."&module=user/groups");
		}
	}

	$page->add_breadcrumb_item($lang->add_user_group);
	$page->output_header($lang->user_group." - ".$lang->add_user_group);
	
	$page->output_nav_tabs($sub_tabs, 'add_group');
	$form = new Form("index.php?".SID."&amp;module=user/groups&amp;action=add", "post");
	
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
		flash_message($lang->error_invalid_user_group, 'error');
		admin_redirect("index.php?".SID."&module=user/group");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		if(!$errors)
		{
			if($mybb->input['joinable'] == 1)
			{
				if($mybb->input['moderate'] == 1)
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
			
			flash_message($lang->success_group_updated, 'success');
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
		flash_message($lang->error_invalid_user_group, 'error');
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


		flash_message($lang->success_group_deleted, 'success');
		admin_redirect("index.php?".SID."&module=user/groups");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=user/groups&amp;action=delete&amp;gid={$usergroup['gid']}", $lang->confirm_group_deletion);
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
	if($mybb->request_method == "post")
	{
		if(!empty($mybb->input['disporder']))
		{
			foreach($mybb->input['disporder'] as $gid => $order)
			{
				$db->update_query("usergroups", array('disporder' => intval($order)), "gid='".intval($gid)."'");
			}
					
			$cache->update_usergroups();
		
			flash_message($lang->success_groups_disporder_updated, 'success');
			admin_redirect("index.php?".SID."&module=user/groups");
		}
	}
	
	$page->output_header($lang->manage_user_groups);
	$page->output_nav_tabs($sub_tabs, 'manage_groups');
	
	$form = new Form("index.php?".SID."&amp;module=user/groups", "post", "groups");
	
	$query = $db->query("SELECT g.gid, COUNT(u.uid) AS users FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup) GROUP BY gid;");
	while($groupcount = $db->fetch_array($query))
	{
		$primaryusers[$groupcount['gid']] = $groupcount['users'];
	}

	switch($db->type)
	{
		case "sqlite3":
		case "sqlite2":
			$query = $db->query("SELECT g.gid, COUNT(u.uid) AS users FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."usergroups g ON (','|| u.additionalgroups|| ',' LIKE '%,'|| g.gid|| ',%')) WHERE g.gid!='' GROUP BY gid;");
			break;
		default:
			$query = $db->query("SELECT g.gid, COUNT(u.uid) AS users FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."usergroups g ON (CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%')) WHERE g.gid!='' GROUP BY gid;");
	}
	while($groupcount = $db->fetch_array($query))
	{
		$secondaryusers[$groupcount['gid']] = $groupcount['users'];
	}

	$query = $db->query("SELECT g.gid, COUNT(r.uid) AS users FROM ".TABLE_PREFIX."joinrequests r LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=r.gid) GROUP BY gid;");
	while($joinrequest = $db->fetch_array($query))
	{
		$joinrequests[$joinrequest['gid']] = $joinrequest['users'];
	}
	
	$form_container = new FormContainer($lang->user_groups);
	$form_container->output_row_header($lang->group);
	$form_container->output_row_header($lang->number_of_users);
	$form_container->output_row_header($lang->order, array("class" => "align_center", 'width' => '5%'));
	$form_container->output_row_header($lang->controls, array("class" => "align_center"));
	$query = $db->simple_select("usergroups", "*", "", array('order_by' => 'disporder'));
	while($usergroup = $db->fetch_array($query))
	{
		if($usergroup['type'] > 1)
		{
			$icon = "<img src=\"styles/default/images/icons/custom.gif\" alt=\"{$lang->custom_user_group}\" style=\"vertical-align: middle;\" />";
		}
		else
		{
			$icon = "<img src=\"styles/default/images/icons/default.gif\" alt=\"{$lang->default_user_group}\" style=\"vertical-align: middle;\" />";
		}
		$form_container->output_cell("<div class=\"float_right\">{$icon}</div><div><strong><a href=\"index.php?".SID."&amp;module=user/users&amp;action=edit&amp;uid={$moderator['uid']}\">{$usergroup['title']}</a></strong><br /><small>{$usergroup['description']}</small></div>");
		
		if(!$primaryusers[$usergroup['gid']])
		{
			$primaryusers[$usergroup['gid']] = 0;
		}
		$numusers = $primaryusers[$usergroup['gid']];
		if($secondaryusers[$usergroup['gid']])
		{
			$numusers .= " ({$secondaryusers[$usergroup['gid']]})";
		}
		if($joinrequests[$usergroup['gid']] > 0)
		{
			$numusers .= " <a href=\"index.php?".SID."&amp;module=user/groups&amp;action=joinrequests&amp;gid={$usergroup['gid']}\"><span style=\"color: red;\">{$joinrequests[$usergroup['gid']]}</span></a>";
		}
		$form_container->output_cell($numusers, array("class" => "align_center"));
		
		if($usergroup['showforumteam'] == 1)
		{
			$form_container->output_cell("<input type=\"text\" name=\"disporder[{$usergroup['gid']}]\" value=\"{$usergroup['disporder']}\" class=\"text_input\" style=\"width: 80%;\" class=\"align_center\" />", array("class" => "align_center"));
		}
		else
		{
			$form_container->output_cell("&nbsp;", array("class" => "align_center"));
		}
		
		$popup = new PopupMenu("usergroup_{$usergroup['gid']}", $lang->options);
		$popup->add_item($lang->edit_group, "index.php?".SID."&amp;module=user/groups&amp;action=edit&amp;gid={$usergroup['gid']}");
		if($joinrequests[$usergroup['gid']] > 0)
		{
			$popup->add_item($lang->moderate_join_requests, "index.php?".SID."&amp;module=user/groups&amp;action=joinrequests&amp;gid={$usergroup['gid']}");
		}
		if($usergroup['type'] > 1)
		{
			$popup->add_item($lang->delete_group, "index.php?".SID."&amp;module=user/groups&amp;action=delete&amp;gid={$usergroup['gid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_group_deletion}')");
		}
		$form_container->output_cell($popup->fetch(), array("class" => "align_center"));
		$form_container->construct_row();
	}
	
	if(count($form_container->container->rows) == 0)
	{
		$form_container->output_cell($lang->no_groups, array('colspan' => 4));
		$form_container->construct_row();
	}
	
	$form_container->end();
	
	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->update_groups_order);
	$buttons[] = $form->generate_reset_button($lang->reset);	
	
	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	echo <<<LEGEND
	<br />
	<fieldset>
<legend>{$lang->legend}</legend>
<img src="styles/default/images/icons/custom.gif" alt="{$lang->custom_user_group}" style="vertical-align: middle;" /> {$lang->custom_user_group}<br />
<img src="styles/default/images/icons/default.gif" alt="{$lang->default_user_group}" style="vertical-align: middle;" /> {$lang->default_user_group}
</fieldset>
LEGEND;
	
	$page->output_footer();
}
?>