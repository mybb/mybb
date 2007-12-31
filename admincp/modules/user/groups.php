
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
  *   Display Order
  *   Group Leaders
  *   Join Requests
  */

// Array of usergroup permission fields and their default values.
$usergroup_permissions = array(
	"isbannedgroup" => 0,
	"canview" => 1,
	"canviewthreads" => 1,
	"canviewprofiles" => 1,
	"candlattachments" => 1,
	"canpostthreads" => 1,
	"canpostreplys" => 1,
	"canpostattachments" => 1,
	"canratethreads" => 1,
	"caneditposts" => 1,
	"candeleteposts" => 1,
	"candeletethreads" => 1,
	"caneditattachments" => 1,
	"canpostpolls" => 1,
	"canvotepolls" => 1,
	"canusepms" => 1,
	"cansendpms" => 1,
	"cantrackpms" => 1,
	"candenypmreceipts" => 1,
	"pmquota" => 100,
	"maxpmrecipients" => 5,
	"cansendemail" => 1,
	"maxemails" => 4,
	"canviewmemberlist" => 1,
	"canviewcalendar" => 1,
	"canaddevents" => 1,
	"canbypasseventmod" => 0,
	"canmoderateevents" => 0,
	"canviewonline" => 1,
	"canviewwolinvis" => 0,
	"canviewonlineips" => 0,
	"cancp" => 0,
	"issupermod" => 0,
	"cansearch" => 1,
	"canusercp" => 1,
	"canuploadavatars" => 1,
	"canratemembers" => 1,
	"canchangename" => 0,
	"showforumteam" => 0,
	"usereputationsystem" => 1,
	"cangivereputations" => 1,
	"reputationpower" => 1,
	"maxreputationsday" => 5,
	"candisplaygroup" => 0,
	"attachquota" => 5000,
	"cancustomtitle" => 0,
	"canwarnusers" => 0,
	"canreceivewarnings" => 1,
	"maxwarningsday" => 0,
	"canmodcp" => 0
);

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
		'description' => "Here you can create a new user group and optionally copy the permissions from another user group. After saving the user group you will be taken to the full edit page for this user group."
	);
}

$plugins->run_hooks("admin_user_groups_begin");

if($mybb->input['action'] == "export")
{
	$plugins->run_hooks("admin_user_groups_export_start");
	
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
	
	$plugins->run_hooks("admin_user_groups_export_end");
	
	echo $xml;
	exit;	
}

if($mybb->input['action'] == "approve_join_request")
{
	$plugins->run_hooks("admin_user_groups_approve_join_request");
	
	$query = $db->simple_select("joinrequests", "*", "rid='".intval($mybb->input['rid'])."'");
	$request = $db->fetch_array($query);
	
	if(!$request['rid'])
	{
		flash_message("You have selected an invalid join request.", 'error');
		admin_redirect("index.php?".SID."&module=user/groups");
	}
	
	// Add the user to the group
	join_usergroup($request['uid'], $request['gid']);
	
	// Delete the join request
	$db->delete_query("joinrequests", "rid='{$request['rid']}'");
	
	flash_message("The join request has been successfully approved. The user is now a member of this user group.", "success");
	admin_redirect("index.php?".SID."&module=user/groups&action=join_requests&gid={$request['gid']}");
}

if($mybb->input['action'] == "deny_join_request")
{
	$plugins->run_hooks("admin_user_groups_deny_join_request");
	
	$query = $db->simple_select("joinrequests", "*", "rid='".intval($mybb->input['rid'])."'");
	$request = $db->fetch_array($query);

	if(!$request['rid'])
	{
		flash_message("You have selected an invalid join request.", 'error');
		admin_redirect("index.php?".SID."&module=user/groups");
	}

	// Delete the join request
	$db->delete_query("joinrequests", "rid='{$request['rid']}'");

	flash_message("The join request has been successfully denied.", "success");
	admin_redirect("index.php?".SID."&module=user/groups&action=join_requests&gid={$request['gid']}");
}

if($mybb->input['action'] == "join_requests")
{
	$plugins->run_hooks("admin_user_groups_join_requests_start");
	
	$query = $db->simple_select("usergroups", "*", "gid='".intval($mybb->input['gid'])."'");
	$group = $db->fetch_array($query);

	if(!$group['gid'] || $group['type'] != 4)
	{
		flash_message($lang->error_invalid_user_group, 'error');
		admin_redirect("index.php?".SID."&module=user/groups");
	}
	
	if($mybb->request_method == "post" && is_array($mybb->input['users']))
	{
		$uid_in = implode(",", array_walk($mybb->input['users'], 'trim'));
		
		if(isset($mybb->input['approve']))
		{
			foreach($mybb->input['users'] as $uid)
			{
				$uid = intval($uid);
				join_usergroup($uid, $group['gid']);
			}
			// Log admin action
			log_admin_action("approve", $group['title'], $group['gid']);
			$message = "The selected join requests have been approved. The users are now part of this group.";
		}
		else
		{
			// Log admin action
			log_admin_action("deny", $group['title'], $group['gid']);
			$message = "The selected join requests have been denied.";
		}
		
		$plugins->run_hooks("admin_user_groups_join_requests_commit");
		
		// Go through and delete the join requests from the database
		$db->delete_query("joinrequests", "uid IN ({$uid_in}) AND gid='{$group['gid']}'");

		flash_message($message, 'success');
		admin_redirect("index.php?".SID."&module=user/groups&action=join_requests&gid={$group['gid']}");
	}
	
	$page->add_breadcrumb_item("Join Requests for {$group['title']}");
	$page->output_header("Join Requests for {$group['title']}");
	
	$sub_tabs = array();
	$sub_tabs['join_requests'] = array(
		'title' => "Group Join Requests",
		'link' => "index.php?".SID."&amp;module=user/groups&action=join_requests&gid={$group['gid']}",
		'description' => "Below is a list of users who are requesting access to this user group. From here, you can either approve or deny their request."
	);
		
	$page->output_nav_tabs($sub_tabs, 'join_requests');
	
	$query = $db->simple_select("joinrequests", "COUNT(*) AS num_requests", "gid='{$group['gid']}'");
	$num_requests = $db->fetch_field($query, "num_requests");

	$per_page = 20;

	if($mybb->input['page'] > 0)
	{
		$current_page = intval($mybb->input['page']);
		$start = ($current_page-1)*$per_page;
		$pages = $num_requests / $per_page;
		$pages = ceil($pages);
		if($current_page > $pages)
		{
			$start = 0;
			$current_page = 1;
		}
	}
	else
	{
		$start = 0;
		$current_page = 1;
	}

	// Do we need to construct the pagination?
	$pagination = '';
	if($num_requests > $per_page)
	{
		$pagination = draw_admin_pagination($page, $per_page, $num_requests, "index.php?".SID."&amp;module=user/groups&amp;action=join_requests&gid={$group['gid']}");
		echo $pagination;
	}	

	$form = new Form("index.php?".SID."&amp;module=user/groups&amp;action=join_requests&gid={$group['gid']}", "post");
	$table = new Table;
	$table->construct_header($form->generate_check_box("checkall", 1, "", array('class' => 'checkall')), array('width' => 1));
	$table->construct_header("User");
	$table->construct_header("Reason");
	$table->construct_header("Date Requested", array("class" => 'align_center', "width" => 200));
	$table->construct_header("Controls", array("class" => "align_center", "colspan" => 2, "width" => 200));

	$query = $db->query("
		SELECT j.*, u.username
		FROM ".TABLE_PREFIX."joinrequests j
		INNER JOIN ".TABLE_PREFIX."users u ON (u.uid=j.uid)
		WHERE j.gid='{$group['gid']}'
		ORDER BY dateline ASC
		LIMIT {$start}, {$per_page}
	");
	while($request = $db->fetch_array($query))
	{
		$table->construct_cell($form->generate_check_box("users[]", $request['uid'], ""));
		$table->construct_cell("<strong>".build_profile_link($request['username'], $request['uid'])."</strong>");
		$table->construct_cell(htmlspecialchars_uni($request['reason']));
		$table->construct_cell(my_date($mybb->settings['dateformat'].", ".$mybb->settings['timeformat'], $request['dateline']), array('class' => 'align_center'));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=user/groups&action=approve_join_request&amp;rid={$request['rid']}\">Approve</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=user/groups&action=deny_join_request&amp;rid={$request['rid']}\">Deny</a>", array("class" => "align_center"));
		$table->construct_row();	
	}
	
	if($table->num_rows() == 0)
	{
		$table->construct_cell("There are no outstanding join requests for this user group.", array("colspan" => 6));
		$table->construct_row();
	}

	$table->output("Join Requests for {$group['title']}");
	echo $pagination;

	$buttons[] = $form->generate_submit_button("Approve Selected Requests");
	$buttons[] = $form->generate_submit_button("Deny Selected Requests");
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();		
}
if($mybb->input['action'] == "add_leader" && $mybb->request_method == "post")
{
	$plugins->run_hooks("admin_user_groups_add_leader");
	
	$query = $db->simple_select("usergroups", "*", "gid='".intval($mybb->input['gid'])."'");
	$group = $db->fetch_array($query);

	if(!$group['gid'])
	{
		flash_message($lang->error_invalid_user_group, 'error');
		admin_redirect("index.php?".SID."&module=user/group");
	}
		
	$query = $db->simple_select("users", "uid, username", "LOWER(username)='".$db->escape_string(my_strtolower($mybb->input['username']))."'");
	$user = $db->fetch_array($query);
	if(!$user['uid'])
	{
		$errors[] = "The username you entered is invalid.";
	}
	else
	{
		// Is this user already a leader of this group?
		$query = $db->simple_select("groupleaders", "uid", "uid='{$user['uid']}' AND gid='{$group['gid']}'");
		$existing_leader = $db->fetch_field($query, "uid");
		if($existing_leader)
		{
			$errors[] = "The user is already a leader of this user group.";
		}
	}
	
	// No errors, insert
	if(!$errors)
	{
		$new_leader = array(
			"gid" => $group['gid'],
			"uid" => $user['uid'],
			"canmanagemembers" => intval($mybb->input['canmanagemembers']),
			"canmanagerequests" => intval($mybb->input['canmanagerequests'])
		);
		
		$plugins->run_hooks("admin_user_groups_add_leader_commit");
		
		$db->insert_query("groupleaders", $new_leader);

		// Log admin action
		log_admin_action($mybb->input['username'], $group['title']);

		flash_message("{$user['username']} was successfully made a group leader for this user group.", 'success');
		admin_redirect("index.php?".SID."&module=user/groups&action=leaders&gid={$group['gid']}");
	}
	else
	{
		// Errors, show leaders page
		$mybb->input['action'] = "leaders";
	}
}

// Show a listing of group leaders
if($mybb->input['action'] == "leaders")
{
	$plugins->run_hooks("admin_user_groups_leaders");
	
	$query = $db->simple_select("usergroups", "*", "gid='".intval($mybb->input['gid'])."'");
	$group = $db->fetch_array($query);

	if(!$group['gid'])
	{
		flash_message($lang->error_invalid_user_group, 'error');
		admin_redirect("index.php?".SID."&module=user/groups");
	}

	$page->add_breadcrumb_item("Group leaders for {$group['title']}");
	$page->output_header("Group Leaders for {$group['title']}");
	
	$sub_tabs = array();
	$sub_tabs['group_leaders'] = array(
		'title' => "Manage Group Leaders",
		'link' => "index.php?".SID."&amp;module=user/groups&action=leaders&gid={$group['gid']}",
		'description' => "Here you can manage the group leaders for this user group. Depending on the options you enable for the leader, these users can manage the users who are a member of this group and can moderate any join requests if this group is publicly joinable."
	);
		
	$page->output_nav_tabs($sub_tabs, 'group_leaders');

	$table = new Table;
	$table->construct_header("User");
	$table->construct_header("Can Manage Members?", array("class" => 'align_center', "width" => 200));
	$table->construct_header("Can Manage Join Requests?", array("class" => 'align_center', "width" => 200));
	$table->construct_header("Controls", array("class" => "align_center", "colspan" => 2, "width" => 200));

	$query = $db->query("
		SELECT g.*, u.username
		FROM ".TABLE_PREFIX."groupleaders g
		INNER JOIN ".TABLE_PREFIX."users u ON (u.uid=g.uid)
		WHERE g.gid='{$group['gid']}'
		ORDER BY u.username ASC
	");
	while($leader = $db->fetch_array($query))
	{
		$leader['username'] = htmlspecialchars_uni($leader['username']);
		if($leader['canmanagemembers'])
			$canmanagemembers = $lang->yes;
		else
			$canmanagemembers = $lang->no;
			
		if($leader['canmanagerequests'])
			$canmanagerequests = $lang->yes;
		else
			$canmanagerequests = $lang->no;
		
		$table->construct_cell("<strong>".build_profile_link($leader['username'], $leader['uid'])."</strong>");
		$table->construct_cell($canmanagemembers, array("class" => "align_center"));
		$table->construct_cell($canmanagerequests, array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=user/groups&amp;action=edit_leader&lid={$leader['lid']}\">Edit</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=user/groups&amp;action=delete_leader&amp;lid={$leader['lid']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you want to delete this group leader?')\">Delete</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_row();
	}
	
	if($table->num_rows() == 0)
	{
		$table->construct_cell("You haven't assigned any users as leaders of this group yet. To create a leader for this group, fill in the form below.", array("colspan" => 5));
		$table->construct_row();
	}
	
	$table->output("Group Leaders for {$group['title']}");

	$form = new Form("index.php?".SID."&amp;module=user/groups&amp;action=add_leader&gid={$group['gid']}", "post");
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = array(
			"canmanagemembers" => 1,
			"canmanagerequests" => 1
		);
	}
	
	$form_container = new FormContainer("Add Group Leader to {$group['title']}");
	$form_container->output_row($lang->username." <em>*</em>", "", $form->generate_text_box('username', $mybb->input['username'], array('id' => 'username')), 'username');
	$form_container->output_row("Can Manage Group Members?", "If this user should be able to manage the members within a group, set to Yes.", $form->generate_yes_no_radio('canmanagemembers', $mybb->input['canmanagemembers']));
		$form_container->output_row("Can Manage Group Join Requests?", "Should this user be able to approve or deny new membership requests for this group?", $form->generate_yes_no_radio('canmanagerequests', $mybb->input['canmanagerequests']));
	$buttons[] = $form->generate_submit_button("Save Group Leader");
	
	$form_container->end();
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();	
}


if($mybb->input['action'] == "delete_leader")
{
	$plugins->run_hooks("admin_user_groups_delete_leader");
	
	$query = $db->query("
		SELECT l.*, u.username
		FROM ".TABLE_PREFIX."groupleaders l
		INNER JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		WHERE l.lid='".intval($mybb->input['lid'])."'");
	$leader = $db->fetch_array($query);
	
	if(!$leader['lid'])
	{
		flash_message("You specified an invalid group leader.", 'error');
		admin_redirect("index.php?".SID."&module=user/groups");		
	}
	
	$query = $db->simple_select("usergroups", "*", "gid='{$leader['gid']}'");
	$group = $db->fetch_array($query);	
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=user/groups");
	}

	if($mybb->request_method == "post")
	{		
		// Delete the leader
		$db->delete_query("groupleaders", "lid='{$lid}'");
		
		$plugins->run_hooks("admin_user_groups_delete_leader_commit");

		// Log admin action
		log_admin_action($leader['username'], $group['title']);

		flash_message("The user has been successfully removed from the group leaders list for this group", 'success');
		admin_redirect("index.php?".SID."&module=user/groups&action=leaders&gid={$group['gid']}");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=user/groups&amp;action=delete_leader&amp;lid={$leader['lid']}", "Are you sure you want to delete this group leader?");
	}
}

if($mybb->input['action'] == "edit_leader")
{
	$plugins->run_hooks("admin_user_groups_edit_leader");
	
	$query = $db->query("
		SELECT l.*, u.username
		FROM ".TABLE_PREFIX."groupleaders l
		INNER JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		WHERE l.lid='".intval($mybb->input['lid'])."'
	");
	$leader = $db->fetch_array($query);

	if(!$leader['lid'])
	{
		flash_message("You specified an invalid group leader.", 'error');
		admin_redirect("index.php?".SID."&module=user/groups");		
	}

	$query = $db->simple_select("usergroups", "*", "gid='{$leader['gid']}'");
	$group = $db->fetch_array($query);
		
	if($mybb->request_method == "post")
	{
		$updated_leader = array(
			"canmanagemembers" => intval($mybb->input['canmanagemembers']),
			"canmanagerequests" => intval($mybb->input['canmanagerequests'])
		);
		$db->update_query("groupleaders", $updated_leader, "lid={$leader['lid']}");
		
		$plugins->run_hooks("admin_user_groups_edit_leader_commit");

		// Log admin action
		log_admin_action($leader['username'], $group['title']);

		flash_message("The group leader has been updated successfully.", 'success');
		admin_redirect("index.php?".SID."&module=user/groups&action=leaders&gid={$group['gid']}");		
	}
	
	if(!$errors)
		$mybb->input = $leader;
	
	$page->add_breadcrumb_item("Group leaders for {$group['title']}", "index.php?".SID."&module=user/groups&action=leaders&gid={$group['gid']}");
	$page->add_breadcrumb_item("Edit Leader: {$leader['username']}");
	
	$page->output_header("Edit Group Leader");
	
	$sub_tabs = array();
	$sub_tabs['group_leaders'] = array(
		'title' => "Edit Group Leader",
		'link' => "index.php?".SID."&amp;module=user/groups&action=edit_leader&lid={$leader['lid']}",
		'description' => "Here you can change the permissions for this group leader in regards to what actions they're allowed to perform."
	);
		
	$page->output_nav_tabs($sub_tabs, 'group_leaders');

	$form = new Form("index.php?".SID."&amp;module=user/groups&amp;action=edit_leader&lid={$leader['lid']}'", "post");
	
	$form_container = new FormContainer("Edit Group Leader");
	$form_container->output_row($lang->username." <em>*</em>", "", $leader['username']);
	
	$form_container->output_row("Can Manage Group Members?", "If this user should be able to manage the members within a group, set to Yes.", $form->generate_yes_no_radio('canmanagemembers', $mybb->input['canmanagemembers']));
		$form_container->output_row("Can Manage Group Join Requests?", "Should this user be able to approve or deny new membership requests for this group?", $form->generate_yes_no_radio('canmanagerequests', $mybb->input['canmanagerequests']));
	$buttons[] = $form->generate_submit_button("Save Group Leader");
	
	$form_container->end();
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();	
}

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_user_groups_add");
	
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		if(!$errors)
		{
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
				"disporder" => 0
			);
			
			// Set default permissions
			if($mybb->input['copyfrom'] == 0)
			{
				$new_usergroup = array_merge($new_usergroup, $usergroup_permissions);
			}
			// Copying permissions from another group
			else
			{
				$query = $db->simple_select("usergroups", "*", "gid='".intval($mybb->input['copyfrom'])."'");
				$existing_usergroup = $db->fetch_array($query);
				foreach(array_keys($usergroup_permissions) as $field)
				{
					$new_usergroup[$field] = $existing_usergroup[$field];
				}
			}
			
			$gid = $db->insert_query("usergroups", $new_usergroup);
			
			// Are we copying permissions? If so, copy all forum permissions too
			if($mybb->input['copyfrom'] > 0)
			{
				$query = $db->simple_select("forumpermissions", "*", "gid='".intval($mybb->input['copyfrom'])."'");
				while($forum_permission = $db->fetch_array($query))
				{
					unset($forum_permission['pid']);
					$forum_permission['gid'] = $gid;
					$db->insert_query("forumpermissions", $forum_permission);
				}
			}

			// Update the caches
			$cache->update_usergroups();
			$cache->update_forumpermissions();
			
			$plugins->run_hooks("admin_user_groups_add_commit");

			// Log admin action
			log_admin_action($gid, $mybb->input['title']);
			
			flash_message($lang->success_group_created, 'success');
			admin_redirect("index.php?".SID."&module=user/groups&action=edit&gid={$gid}");
		}
	}

	$page->add_breadcrumb_item($lang->add_user_group);
	$page->output_header($lang->add_user_group);
	
	$page->output_nav_tabs($sub_tabs, 'add_group');
	$form = new Form("index.php?".SID."&amp;module=user/groups&amp;action=add", "post");
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = array(
			"namestyle" => "{username}"
		);
	}

	$form_container = new FormContainer("Add User Group");
	$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Short Description", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$form_container->output_row("Username Style", "This option allows you to set a custom username style for users who have this user group as their display group. <strong>Use {username} to represent the user's name.</strong>", $form->generate_text_box('namestyle', $mybb->input['namestyle'], array('id' => 'namestyle')), 'namestyle');
	$form_container->output_row("Default User Title", "If the user has nothing entered in their custom user title field, the user title entered here will be displayed.", $form->generate_text_box('usertitle', $mybb->input['usertitle'], array('id' => 'usertitle')), 'usertitle');

	$options[0] = "Don't copy permissions from another group";
	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
	while($usergroup = $db->fetch_array($query))
	{
		$options[$usergroup['gid']] = $usergroup['title'];
	}
	$form_container->output_row("Copy Permissions From...", "If you wish, you can copy the forum and group permissions from another group. To make use of this, select a group to copy permissons from.", $form->generate_select_box('copyfrom', $options, $mybb->input['copyfrom'], array('id' => 'copyfrom')), 'copyfrom');

	$form_container->end();
	$buttons[] = $form->generate_submit_button("Save User Group");
	$form->output_submit_wrapper($buttons);

	$form->end();
	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$plugins->run_hooks("admin_user_groups_edit");
	
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
				"isbannedgroup" => intval($mybb->input['isbannedgroup']),
				"canview" => intval($mybb->input['canview']),
				"canviewthreads" => intval($mybb->input['canviewthreads']),
				"canviewprofiles" => intval($mybb->input['canviewprofiles']),
				"candlattachments" => intval($mybb->input['candlattachments']),
				"canpostthreads" => intval($mybb->input['canpostthreads']),
				"canpostreplys" => intval($mybb->input['canpostreplys']),
				"canpostattachments" => intval($mybb->input['canpostattachments']),
				"canratethreads" => intval($mybb->input['canratethreads']),
				"caneditposts" => intval($mybb->input['caneditposts']),
				"candeleteposts" => intval($mybb->input['candeleteposts']),
				"candeletethreads" => intval($mybb->input['candeletethreads']),
				"caneditattachments" => intval($mybb->input['caneditattachments']),
				"canpostpolls" => intval($mybb->input['canpostpolls']),
				"canvotepolls" => intval($mybb->input['canvotepolls']),
				"canusepms" => intval($mybb->input['canusepms']),
				"cansendpms" => intval($mybb->input['cansendpms']),
				"cantrackpms" => intval($mybb->input['cantrackpms']),
				"candenypmreceipts" => intval($mybb->input['candenypmreceipts']),
				"pmquota" => intval($mybb->input['pmquota']),
				"maxpmrecipients" => intval($mybb->input['maxpmrecipients']),
				"cansendemail" => intval($mybb->input['cansendemail']),
				"maxemails" => intval($mybb->input['maxemails']),		
				"canviewmemberlist" => intval($mybb->input['canviewmemberlist']),
				"canviewcalendar" => intval($mybb->input['canviewcalendar']),
				"canaddevents" => intval($mybb->input['canaddevents']),
				"canbypasseventmod" => intval($mybb->input['canbypasseventmod']),
				"canmoderateevents" => intval($mybb->input['canmoderateevents']),
				"canviewonline" => intval($mybb->input['canviewonline']),
				"canviewwolinvis" => intval($mybb->input['canviewwolinvis']),
				"canviewonlineips" => intval($mybb->input['canviewonlineips']),
				"cancp" => intval($mybb->input['cancp']),
				"issupermod" => intval($mybb->input['issupermod']),
				"cansearch" => intval($mybb->input['cansearch']),
				"canusercp" => intval($mybb->input['canusercp']),
				"canuploadavatars" => intval($mybb->input['canuploadavatars']),
				"canchangename" => intval($mybb->input['canchangename']),
				"showforumteam" => intval($mybb->input['showforumteam']),
				"usereputationsystem" => intval($mybb->input['usereputationsystem']),
				"cangivereputations" => intval($mybb->input['cangivereputations']),
				"reputationpower" => intval($mybb->input['reputationpower']),
				"maxreputationsday" => intval($mybb->input['maxreputationsday']),
				"attachquota" => intval($mybb->input['attachquota']),
				"cancustomtitle" => intval($mybb->input['cancustomtitle']),
				"canwarnusers" => intval($mybb->input['canwarnusers']),
				"canreceivewarnings" =>intval($mybb->input['canreceivewarnings']),
				"maxwarningsday" => intval($mybb->input['maxwarningsday']),
				"canmodcp" => intval($mybb->input['canmodcp'])
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
			
			$plugins->run_hooks("admin_user_groups_edit_commit");

			// Log admin action
			log_admin_action($usergroup['gid'], $mybb->input['title']);
			
			flash_message($lang->success_group_updated, 'success');
			admin_redirect("index.php?".SID."&module=user/groups");
		}
	}
	
	$page->add_breadcrumb_item("Edit User Group");
	$page->output_header("Edit User Group");
	
	$sub_tabs = array();	
	$sub_tabs['edit_group'] = array(
		'title' => "Edit User Group",
		'description' => "Here you can edit an existing user group."
	);

	$form = new Form("index.php?".SID."&amp;module=user/groups&amp;action=edit&amp;gid={$usergroup['gid']}", "post");

	$page->output_nav_tabs($sub_tabs, 'edit_group');

	// If we have any error messages, show them
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		if($usergroup['type'] == "3")
		{
			$usergroup['joinable'] = 1;
			$usergroup['moderate'] = 0;
		}
		elseif($usergroup['type'] == "4")
		{
			$usergroup['joinable'] = 1;
			$usergroup['moderate'] = 1;
		}
		else
		{
			$usergroup['joinable'] = 0;
			$usergroup['moderate'] = 0;
		}		
		$mybb->input = $usergroup;
	}	
	$tabs = array(
		"general" => "General",
		"forums_posts" => "Forums and Posts",
		"users_permissions" => "Users and Permissions",
		"misc" => "Miscellaneous"		
	);
	$page->output_tab_control($tabs);
	
	echo "<div id=\"tab_general\">";	
	$form_container = new FormContainer("General");
	$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row("Short Description", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$form_container->output_row("Username Style", "This option allows you to set a custom username style for users who have this user group as their display group. <strong>Use {username} to represent the user's name.</strong>", $form->generate_text_box('namestyle', $mybb->input['namestyle'], array('id' => 'namestyle')), 'namestyle');
	$form_container->output_row("Default User Title", "If the user has nothing entered in their custom user title field, the user title entered here will be displayed.", $form->generate_text_box('usertitle', $mybb->input['usertitle'], array('id' => 'usertitle')), 'usertitle');

	$stars = "<table cellpadding=\"3\"><tr><td>".$form->generate_text_box('stars', $mybb->input['stars'], array('class' => 'field50', 'id' => 'stars'))."</td><td>".$form->generate_text_box('starimage', $mybb->input['starimage'], array('id' => 'starimage'))."</td></tr>";
	$stars .= "<tr><td><small># of Stars</small></td><td><small>Star Image</small></td></tr></table>";
	$form_container->output_row("User Stars", "If you enter a number of stars and the location of a star image, this star image will be shown for this particular user group.", $stars, "stars");

	$form_container->output_row("Group Image", "Here you can set a group image which will show on each post made by users in this group. Please use <strong>{lang}</strong> to represent the user's chosen language if translated group images are available", $form->generate_text_box('groupimage', $mybb->input['groupimage'], array('id' => 'groupimage')), 'groupimage');

	$general_options = array();
	if($usergroup['gid'] != "1" && $usergroup['gid'] != "5")
	{
		$general_options[] = $form->generate_check_box("showforumteam", 1, "Show on 'Forum Team' page", array("checked" => $mybb->input['showforumteam']));
	}
	$general_options[] =	$form->generate_check_box("isbannedgroup", 1, "Is banned group?<br /><small>If this group is a 'banned' user group, users will be able to be 'banned' in to this user group.</small>", array("checked" => $mybb->input['isbannedgroup']));
	
	$form_container->output_row("General Options", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

	if($usergroup['type'] != 1)
	{	
		$public_options = array(
			$form->generate_check_box("joinable", 1, "Users can join this group", array("checked" => $mybb->input['joinable'])),
			$form->generate_check_box("moderate", 1, "Moderate all new join requests", array("checked" => $mybb->input['isbannedgroup'])),
			$form->generate_check_box("candisplaygroup", 1, "Users can set this group as their display group.<br /><small>If set to yes, users will be able to set this user group as their display group for user titles, stars, name style and group images.</small>", array("checked" => $mybb->input['candisplaygroup'])),
			);
		$form_container->output_row("Publicly Joinable Options", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $public_options)."</div>");
	}
		
	$admin_options = array(
		$form->generate_check_box("issupermod", 1, "Is Super Moderator", array("checked" => $mybb->input['issupermod'])),
		$form->generate_check_box("canmodcp", 1, "Can access Moderator CP", array("checked" => $mybb->input['canmodcp'])),
		$form->generate_check_box("cancp", 1, "Can access Admin CP", array("checked" => $mybb->input['cancp']))
	);
	$form_container->output_row("Moderation/Administration Options", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $admin_options)."</div>");

	$form_container->end();
	echo "</div>";
	
	//
	// FORUMS AND POSTS
	//
	echo "<div id=\"tab_forums_posts\">";	
	$form_container = new FormContainer("Forums and Posts");

	$viewing_options = array(
		$form->generate_check_box("canview", 1, "Can view board", array("checked" => $mybb->input['canview'])),
		$form->generate_check_box("canviewthreads", 1, "Can view threads", array("checked" => $mybb->input['canviewthreads'])),
		$form->generate_check_box("cansearch", 1, "Can search forums", array("checked" => $mybb->input['cansearch'])),
		$form->generate_check_box("canviewprofiles", 1, "Can view user profiles", array("checked" => $mybb->input['canviewprofiles'])),
		$form->generate_check_box("candlattachments", 1, "Moderate all new join requests", array("checked" => $mybb->input['candlattachments'])),
	);
	$form_container->output_row("Viewing Options", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $viewing_options)."</div>");	
	
	$posting_options = array(
		$form->generate_check_box("canpostthreads", 1, "Can post new threads", array("checked" => $mybb->input['canpostthreads'])),
		$form->generate_check_box("canpostreplys", 1, "Can post replies to threads", array("checked" => $mybb->input['canpostreplys'])),
		$form->generate_check_box("canratethreads", 1, "Can rate threads", array("checked" => $mybb->input['canratethreads']))
	);
	$form_container->output_row("Posting/Rating Options", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $posting_options)."</div>");
	
	$poll_options = array(
		$form->generate_check_box("canpostpolls", 1, "Can post polls", array("checked" => $mybb->input['canpostpolls'])),
		$form->generate_check_box("canvotepolls", 1, "Can vote in polls", array("checked" => $mybb->input['canvotepolls']))
	);
	$form_container->output_row("Poll Options", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $poll_options)."</div>");	

	$attachment_options = array(
		$form->generate_check_box("canpostattachments", 1, "Can post attachments", array("checked" => $mybb->input['canpostattachments'])),
		"Attachment Quota:<br /><small>Here you can set the attachment quota that each user in this group will receive. If set to 0, there is no limit.</small><br />".$form->generate_text_box('attachquota', $mybb->input['attachquota'], array('id' => 'attachquota', 'class' => 'field50')). "KB"
	);
	$form_container->output_row("Attachment Options", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $attachment_options)."</div>");	

	$editing_options = array(
		$form->generate_check_box("caneditposts", 1, "Can edit own posts", array("checked" => $mybb->input['caneditposts'])),
		$form->generate_check_box("candeleteposts", 1, "Can delete own posts", array("checked" => $mybb->input['candeleteposts'])),
		$form->generate_check_box("candeletethreads", 1, "Can delete own threads", array("checked" => $mybb->input['candeletethreads'])),
		$form->generate_check_box("caneditattachments", 1, "Can edit own attachments", array("checked" => $mybb->input['caneditattachments']))
	);
	$form_container->output_row("Editing/Deleting Options", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $editing_options)."</div>");
	
	$form_container->end();
	echo "</div>";	

	//
	// USERS AND PERMISSIONS
	//
	echo "<div id=\"tab_users_permissions\">";	
	$form_container = new FormContainer("Users and Permissions");

	$account_options = array(
		$form->generate_check_box("canusercp", 1, "Can access User CP", array("checked" => $mybb->input['canusercp'])),
		$form->generate_check_box("canchangename", 1, "Can change username", array("checked" => $mybb->input['canchangename'])),
		$form->generate_check_box("cancustomtitle", 1, "Can use custom user titles", array("checked" => $mybb->input['cancustomtitle'])),
		$form->generate_check_box("canuploadavatars", 1, "Can upload avatars", array("checked" => $mybb->input['canuploadavatars']))
	);
	$form_container->output_row("Account Management", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $account_options)."</div>");	

	$reputation_options = array(
		$form->generate_check_box("usereputationsystem", 1, "Show reputations for users in this group", array("checked" => $mybb->input['usereputationsystem'])),
		$form->generate_check_box("cangivereputations", 1, "Can give reputations to users", array("checked" => $mybb->input['cangivereputations'])),
		"Points to Award/Take Away:<br /><small>Here you need to enter the number of points to give or take away on each reputation given by users of this group.</small><br />".$form->generate_text_box('reputationpower', $mybb->input['reputationpower'], array('id' => 'reputationpower', 'class' => 'field50')),
		"Maximum Reputations Allowed Per Day:<br /><small>Here you can enter the maximum number of reputations that users in this group can give per day. To allow unlimited reputations per day, enter 0.</small><br />".$form->generate_text_box('maxreputationsday', $mybb->input['maxreputationsday'], array('id' => 'maxreputationsday', 'class' => 'field50'))	
	);
	$form_container->output_row("Reputation System", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $reputation_options)."</div>");

	$warning_options = array(
		$form->generate_check_box("canwarnusers", 1, "Can send warnings to other users", array("checked" => $mybb->input['canwarnusers'])),
		$form->generate_check_box("canreceivewarnings", 1, "Can receive warnings from other users", array("checked" => $mybb->input['canreceivewarnings'])),
		"Maximum warnings allowed per day:<br />".$form->generate_text_box('maxwarningsday', $mybb->input['maxwarningsday'], array('id' => 'maxwarningsday', 'class' => 'field50'))
	);
	$form_container->output_row("Warning System", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $warning_options)."</div>");

	$pm_options = array(
		$form->generate_check_box("canusepms", 1, "Can use private messaging", array("checked" => $mybb->input['canusepms'])),
		$form->generate_check_box("cansendpms", 1, "Can send private messages", array("checked" => $mybb->input['cansendpms'])),
		$form->generate_check_box("cantrackpms", 1, "Can track sent private messages", array("checked" => $mybb->input['cantrackpms'])),
		$form->generate_check_box("candenypmreceipts", 1, "Can deny message receipt notifications", array("checked" => $mybb->input['candenypmreceipts'])),
		"Message Quota:<br /><small>Maximum number of private messages that can be stored by users in this group. If empty, users can store unlimited messages.</small><br />".$form->generate_text_box('pmquota', $mybb->input['pmquota'], array('id' => 'pmquota', 'class' => 'field50')),
		"Maximum Recipients Per Message:<br /><small>Maximum number of recipients a user can send a private message to at one time. If empty, users can send private messages to an unlimited number of recipients.</small><br />".$form->generate_text_box('maxpmrecipients', $mybb->input['maxpmrecipients'], array('id' => 'maxpmrecipients', 'class' => 'field50'))
	);
	$form_container->output_row("Private Messaging", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $pm_options)."</div>");
	
	$form_container->end();
	echo "</div>";

	//
	// MISC
	//
	echo "<div id=\"tab_misc\">";	
	$form_container = new FormContainer("Miscellaneous");

	$calendar_options = array(
		$form->generate_check_box("canviewcalendar", 1, "Can view calendar", array("checked" => $mybb->input['canviewcalendar'])),
		$form->generate_check_box("canaddevents", 1, "Can post calendar events", array("checked" => $mybb->input['canaddevents'])),
		$form->generate_check_box("canbypasseventmod", 1, "Can bypass calendar event moderation queue", array("checked" => $mybb->input['canbypasseventmod'])),
		$form->generate_check_box("canmoderateevents", 1, "Can moderate calendar events", array("checked" => $mybb->input['canmoderateevents']))
	);
	$form_container->output_row("Calendar", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $calendar_options)."</div>");

	$wol_options = array(
		$form->generate_check_box("canviewonline", 1, "Can view who's online", array("checked" => $mybb->input['canviewonline'])),
		$form->generate_check_box("canviewwolinvis", 1, "Can view invisible users", array("checked" => $mybb->input['canviewwolinvis'])),
		$form->generate_check_box("canviewonlineips", 1, "Can view IP addresses on who's online", array("checked" => $mybb->input['canviewonlineips']))
	);
	$form_container->output_row("Who's Online", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $wol_options)."</div>");

	$misc_options = array(
		$form->generate_check_box("canviewmemberlist", 1, "Can view member list", array("checked" => $mybb->input['canviewonline'])),
		$form->generate_check_box("cansendemail", 1, "Can send threads to friends and email users", array("checked" => $mybb->input['canviewwolinvis'])),
		"Maximum Emails Per Day:<br /><small>The maximum number of emails users can send using the 'Email User' and 'Send Thread to Friend' features.</small><br />".$form->generate_text_box('maxemails', $mybb->input['maxemails'], array('id' => 'maxemails', 'class' => 'field50'))
	);
	$form_container->output_row("Miscellaneous", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $misc_options)."</div>");

	$form_container->end();
	echo "</div>";
	
	$buttons[] = $form->generate_submit_button("Save User Group");
	$form->output_submit_wrapper($buttons);

	$form->end();
	$page->output_footer();	
}

if($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("admin_user_groups_delete");
	
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

		$plugins->run_hooks("admin_user_groups_delete_commit");

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

if($mybb->input['action'] == "disporder" && $mybb->_request_method == "post")
{
	$plugins->run_hooks("admin_user_groups_disporder");
	
	foreach($mybb->input['disporder'] as $gid=>$order)
	{
		$gid = intval($gid);
		$order = intval($order);
		if($gid != 0 && $order != 0)
		{
			$sql_array = array(
				'disporder' => $order,
			);
			$db->update_query('usergroups', $sql_array, "gid = '{$gid}'");
		}
	}

	// Log admin action
	log_admin_action();
	
	$plugins->run_hooks("admin_user_groups_disporder_commit");

	flash_message("The user group display orders have been updated successfully.", 'success');
	admin_redirect("index.php?".SID."&module=user/groups");
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_user_groups_start");
	
	if($mybb->request_method == "post")
	{
		if(!empty($mybb->input['disporder']))
		{
			foreach($mybb->input['disporder'] as $gid => $order)
			{
				$db->update_query("usergroups", array('disporder' => intval($order)), "gid='".intval($gid)."'");
			}
					
			$cache->update_usergroups();
			
			$plugins->run_hooks("admin_user_groups_start_commit");
		
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
		case "pgsql":
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
	
	// Fetch group leaders
	$leaders = array();
	$query = $db->query("
		SELECT u.username, u.uid, l.gid
		FROM ".TABLE_PREFIX."groupleaders l
		INNER JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		ORDER BY u.username ASC
	");
	while($leader = $db->fetch_array($query)) {
		$leaders[$leader['gid']][] = build_profile_link($leader['username'], $leader['uid'], "_blank");
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
		
		$leaders_list = '';
		if(isset($leaders[$usergroup['gid']]))
		{
			$leaders_list = "<br />Group Leaders: ".implode(", ", $leaders[$usergroup['gid']]);
		}
			
		$join_requests = '';
		if($joinrequests[$usergroup['gid']] > 1)
		{
			$join_requests = " <small><a href=\"index.php?".SID."&amp;module=user/groups&amp;action=join_requests&amp;gid={$usergroup['gid']}\"><span style=\"color: red;\">({$joinrequests[$usergroup['gid']]} outstanding join requests)</span></a></small>";
		}
		else if($joinrequests[$usergroup['gid']] == 1)
		{
			$join_requests = " <small><a href=\"index.php?".SID."&amp;module=user/groups&amp;action=join_requests&amp;gid={$usergroup['gid']}\"><span style=\"color: red;\">({$joinrequests[$usergroup['gid']]} outstanding join request)</span></a></small>";			
		}
		
		$form_container->output_cell("<div class=\"float_right\">{$icon}</div><div><strong><a href=\"index.php?".SID."&amp;module=user/groups&amp;action=edit&amp;gid={$usergroup['gid']}\">{$usergroup['title']}</a></strong>{$join_requests}<br /><small>{$usergroup['description']}{$leaders_list}</small></div>");
		
		if(!$primaryusers[$usergroup['gid']])
		{
			$primaryusers[$usergroup['gid']] = 0;
		}
		$numusers = $primaryusers[$usergroup['gid']];
		$numusers += $secondaryusers[$usergroups['gid']];

		$form_container->output_cell($numusers, array("class" => "align_center"));
		
		if($usergroup['showforumteam'] == 1)
		{
			$form_container->output_cell("<input type=\"text\" name=\"disporder[{$usergroup['gid']}]\" value=\"{$usergroup['disporder']}\" class=\"text_input align_center\" style=\"width: 80%;\" />", array("class" => "align_center"));
		}
		else
		{
			$form_container->output_cell("&nbsp;", array("class" => "align_center"));
		}
		
		$popup = new PopupMenu("usergroup_{$usergroup['gid']}", $lang->options);
		$popup->add_item($lang->edit_group, "index.php?".SID."&amp;module=user/groups&amp;action=edit&amp;gid={$usergroup['gid']}");
		$popup->add_item("List Users", "index.php?".SID."&amp;module=user/users&amp;action=search&amp;results=1&amp;conditions[usergroup]={$usergroup['gid']}");
		if($joinrequests[$usergroup['gid']] > 0)
		{
			$popup->add_item($lang->moderate_join_requests, "index.php?".SID."&amp;module=user/groups&amp;action=join_requests&amp;gid={$usergroup['gid']}");
		}
		$popup->add_item("Group Leaders", "index.php?".SID."&amp;module=user/groups&amp;action=leaders&amp;gid={$usergroup['gid']}");
		if($usergroup['type'] > 1)
		{
			$popup->add_item($lang->delete_group, "index.php?".SID."&amp;module=user/groups&amp;action=delete&amp;gid={$usergroup['gid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_group_deletion}')");
		}
		$form_container->output_cell($popup->fetch(), array("class" => "align_center"));
		$form_container->construct_row();
	}
	
	if($form_container->num_rows() == 0)
	{
		$form_container->output_cell($lang->no_groups, array('colspan' => 4));
		$form_container->construct_row();
	}
	
	$form_container->end();
	
	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->update_groups_order);
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