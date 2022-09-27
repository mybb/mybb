<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Array of usergroup permission fields and their default values.
$usergroup_permissions = array(
	"isbannedgroup" => 0,
	"canview" => 1,
	"canviewthreads" => 1,
	"canviewprofiles" => 1,
	"candlattachments" => 1,
	"canviewboardclosed" => 1,
	"canpostthreads" => 1,
	"canpostreplys" => 1,
	"canpostattachments" => 1,
	"canratethreads" => 1,
	"modposts" => 0,
	"modthreads" => 0,
	"modattachments" => 0,
	"mod_edit_posts" => 0,
	"caneditposts" => 1,
	"candeleteposts" => 1,
	"candeletethreads" => 1,
	"caneditattachments" => 1,
	"canviewdeletionnotice" => 1,
	"canpostpolls" => 1,
	"canvotepolls" => 1,
	"canundovotes" => 0,
	"canusepms" => 1,
	"cansendpms" => 1,
	"cantrackpms" => 1,
	"candenypmreceipts" => 1,
	"pmquota" => 100,
	"maxpmrecipients" => 5,
	"cansendemail" => 1,
	"cansendemailoverride" => 0,
	"maxemails" => 4,
	"emailfloodtime" => 5,
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
	"canbeinvisible" => 1,
	"canbereported" => 0,
	"canchangewebsite" => 1,
	"showforumteam" => 0,
	"usereputationsystem" => 1,
	"cangivereputations" => 1,
	"candeletereputations" => 1,
	"reputationpower" => 1,
	"maxreputationsday" => 5,
	"maxreputationsperuser" => 0,
	"maxreputationsperthread" => 0,
	"candisplaygroup" => 0,
	"attachquota" => 5000,
	"cancustomtitle" => 0,
	"canwarnusers" => 0,
	"canreceivewarnings" => 1,
	"maxwarningsday" => 0,
	"canmodcp" => 0,
	"showinbirthdaylist" => 0,
	"canoverridepm" => 0,
	"canusesig" => 0,
	"canusesigxposts" => 0,
	"signofollow" => 0,
	"edittimelimit" => 0,
	"maxposts" => 0,
	"showmemberlist" => 1,
	"canmanageannounce" => 0,
	"canmanagemodqueue" => 0,
	"canmanagereportedcontent" => 0,
	"canviewmodlogs" => 0,
	"caneditprofiles" => 0,
	"canbanusers" => 0,
	"canviewwarnlogs" => 0,
	"canuseipsearch" => 0
);

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->user_groups, "index.php?module=user-groups");

if($mybb->input['action'] == "add" || !$mybb->input['action'])
{
	$sub_tabs['manage_groups'] = array(
		'title' => $lang->manage_user_groups,
		'link' => "index.php?module=user-groups",
		'description' => $lang->manage_user_groups_desc
	);
	$sub_tabs['add_group'] = array(
		'title' => $lang->add_user_group,
		'link' => "index.php?module=user-groups&amp;action=add",
		'description' => $lang->add_user_group_desc
	);
}

$plugins->run_hooks("admin_user_groups_begin");

if($mybb->input['action'] == "approve_join_request")
{
	$query = $db->simple_select("joinrequests", "*", "rid='".$mybb->input['rid']."'");
	$request = $db->fetch_array($query);

	if(!$request['rid'])
	{
		flash_message($lang->error_invalid_join_request, 'error');
		admin_redirect("index.php?module=user-groups");
	}

	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=user-groups&action=join_requests&gid={$request['gid']}");
	}

	$plugins->run_hooks("admin_user_groups_approve_join_request");

	// Add the user to the group
	join_usergroup($request['uid'], $request['gid']);

	// Delete the join request
	$db->delete_query("joinrequests", "rid='{$request['rid']}'");

	$plugins->run_hooks("admin_user_groups_approve_join_request_commit");

	flash_message($lang->success_join_request_approved, "success");
	admin_redirect("index.php?module=user-groups&action=join_requests&gid={$request['gid']}");
}

if($mybb->input['action'] == "deny_join_request")
{
	$query = $db->simple_select("joinrequests", "*", "rid='".$mybb->input['rid']."'");
	$request = $db->fetch_array($query);

	if(!$request['rid'])
	{
		flash_message($lang->error_invalid_join_request, 'error');
		admin_redirect("index.php?module=user-groups");
	}

	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=user-groups&action=join_requests&gid={$request['gid']}");
	}

	$plugins->run_hooks("admin_user_groups_deny_join_request");

	// Delete the join request
	$db->delete_query("joinrequests", "rid='{$request['rid']}'");

	$plugins->run_hooks("admin_user_groups_deny_join_request_commit");

	flash_message($lang->success_join_request_denied, "success");
	admin_redirect("index.php?module=user-groups&action=join_requests&gid={$request['gid']}");
}

if($mybb->input['action'] == "join_requests")
{
	$query = $db->simple_select("usergroups", "*", "gid='".$mybb->get_input('gid', MyBB::INPUT_INT)."'");
	$group = $db->fetch_array($query);

	if(!$group['gid'] || $group['type'] != 4)
	{
		flash_message($lang->error_invalid_user_group, 'error');
		admin_redirect("index.php?module=user-groups");
	}

	$plugins->run_hooks("admin_user_groups_join_requests_start");

	if($mybb->request_method == "post" && is_array($mybb->input['users']))
	{
		$uid_in = implode(",", array_map('intval', $mybb->input['users']));

		if(isset($mybb->input['approve']))
		{
			foreach($mybb->input['users'] as $uid)
			{
				$uid = (int)$uid;
				join_usergroup($uid, $group['gid']);
			}
			// Log admin action
			log_admin_action("approve", $group['title'], $group['gid']);
			$message = $lang->success_selected_requests_approved;
		}
		else
		{
			// Log admin action
			log_admin_action("deny", $group['title'], $group['gid']);
			$message = $lang->success_selected_requests_denied;
		}

		$plugins->run_hooks("admin_user_groups_join_requests_commit");

		// Go through and delete the join requests from the database
		$db->delete_query("joinrequests", "uid IN ({$uid_in}) AND gid='{$group['gid']}'");

		$plugins->run_hooks("admin_user_groups_join_requests_commit_end");

		flash_message($message, 'success');
		admin_redirect("index.php?module=user-groups&action=join_requests&gid={$group['gid']}");
	}

	$page->add_breadcrumb_item($lang->join_requests_for.' '.htmlspecialchars_uni($group['title']));
	$page->output_header($lang->join_requests_for.' '.htmlspecialchars_uni($group['title']));

	$sub_tabs = array();
	$sub_tabs['join_requests'] = array(
		'title' => $lang->group_join_requests,
		'link' => "index.php?module=user-groups&action=join_requests&gid={$group['gid']}",
		'description' => $lang->group_join_requests_desc
	);

	$page->output_nav_tabs($sub_tabs, 'join_requests');

	$query = $db->simple_select("joinrequests", "COUNT(*) AS num_requests", "gid='{$group['gid']}'");
	$num_requests = $db->fetch_field($query, "num_requests");

	$per_page = 20;
	$pagenum = $mybb->get_input('page', MyBB::INPUT_INT);
	if($pagenum)
	{
		$start = ($pagenum - 1) * $per_page;
		$pages = ceil($num_requests / $per_page);
		if($pagenum > $pages)
		{
			$start = 0;
			$pagenum = 1;
		}
	}
	else
	{
		$start = 0;
		$pagenum = 1;
	}

	// Do we need to construct the pagination?
	$pagination = '';
	if($num_requests > $per_page)
	{
		$pagination = draw_admin_pagination($pagenum, $per_page, $num_requests, "index.php?module=user-groups&amp;action=join_requests&gid={$group['gid']}");
		echo $pagination;
	}

	$form = new Form("index.php?module=user-groups&amp;action=join_requests&gid={$group['gid']}", "post");
	$table = new Table;
	$table->construct_header($form->generate_check_box("allbox", 1, "", array('class' => 'checkall')), array('width' => 1));
	$table->construct_header($lang->users);
	$table->construct_header($lang->reason);
	$table->construct_header($lang->date_requested, array("class" => 'align_center', "width" => 200));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 200));

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
		$table->construct_cell("<strong>".build_profile_link(htmlspecialchars_uni($request['username']), $request['uid'], "_blank")."</strong>");
		$table->construct_cell(htmlspecialchars_uni($request['reason']));
		$table->construct_cell(my_date('relative', $request['dateline']), array('class' => 'align_center'));

		$popup = new PopupMenu("join_{$request['rid']}", $lang->options);
		$popup->add_item($lang->approve, "index.php?module=user-groups&action=approve_join_request&amp;rid={$request['rid']}&amp;my_post_key={$mybb->post_code}");
		$popup->add_item($lang->deny, "index.php?module=user-groups&action=deny_join_request&amp;rid={$request['rid']}&amp;my_post_key={$mybb->post_code}");

		$table->construct_cell($popup->fetch(), array('class' => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_join_requests, array("colspan" => 6));
		$table->construct_row();
	}

	$table->output($lang->join_requests_for.' '.htmlspecialchars_uni($group['title']));
	echo $pagination;

	$buttons[] = $form->generate_submit_button($lang->approve_selected_requests, array('name' => 'approve'));
	$buttons[] = $form->generate_submit_button($lang->deny_selected_requests, array('name' => 'deny'));
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}
if($mybb->input['action'] == "add_leader" && $mybb->request_method == "post")
{
	$query = $db->simple_select("usergroups", "*", "gid='".$mybb->get_input('gid', MyBB::INPUT_INT)."'");
	$group = $db->fetch_array($query);

	if(!$group['gid'])
	{
		flash_message($lang->error_invalid_user_group, 'error');
		admin_redirect("index.php?module=user-group");
	}

	$plugins->run_hooks("admin_user_groups_add_leader");

	$user = get_user_by_username($mybb->input['username'], array('fields' => 'username'));
	if(empty($user['uid']))
	{
		$errors[] = $lang->error_invalid_username;
	}
	else
	{
		// Is this user already a leader of this group?
		$query = $db->simple_select("groupleaders", "uid", "uid='{$user['uid']}' AND gid='{$group['gid']}'");
		$existing_leader = $db->fetch_field($query, "uid");
		if($existing_leader)
		{
			$errors[] = $lang->error_already_leader;
		}
	}

	// No errors, insert
	if(!$errors)
	{
		$new_leader = array(
			"gid" => $group['gid'],
			"uid" => $user['uid'],
			"canmanagemembers" => $mybb->get_input('canmanagemembers', MyBB::INPUT_INT),
			"canmanagerequests" => $mybb->get_input('canmanagerequests', MyBB::INPUT_INT),
			"caninvitemembers" => $mybb->get_input('caninvitemembers', MyBB::INPUT_INT)
		);

		$makeleadermember = $mybb->get_input('makeleadermember', MyBB::INPUT_INT);
		if($makeleadermember == 1)
		{
			join_usergroup($user['uid'], $group['gid']);
		}

		$plugins->run_hooks("admin_user_groups_add_leader_commit");

		$db->insert_query("groupleaders", $new_leader);

		$cache->update_groupleaders();

		// Log admin action
		log_admin_action($user['uid'], $user['username'], $group['gid'], $group['title']);

		$username = htmlspecialchars_uni($user['username']);
		flash_message("{$username} ".$lang->success_user_made_leader, 'success');
		admin_redirect("index.php?module=user-groups&action=leaders&gid={$group['gid']}");
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
	$query = $db->simple_select("usergroups", "*", "gid='".$mybb->get_input('gid', MyBB::INPUT_INT)."'");
	$group = $db->fetch_array($query);

	if(!$group['gid'])
	{
		flash_message($lang->error_invalid_user_group, 'error');
		admin_redirect("index.php?module=user-groups");
	}

	$plugins->run_hooks("admin_user_groups_leaders");

	$page->add_breadcrumb_item($lang->group_leaders_for.' '.htmlspecialchars_uni($group['title']));
	$page->output_header($lang->group_leaders_for.' '.htmlspecialchars_uni($group['title']));

	$sub_tabs = array();
	$sub_tabs['group_leaders'] = array(
		'title' => $lang->manage_group_leaders,
		'link' => "index.php?module=user-groups&action=leaders&gid={$group['gid']}",
		'description' => $lang->manage_group_leaders_desc
	);

	$page->output_nav_tabs($sub_tabs, 'group_leaders');

	$table = new Table;
	$table->construct_header($lang->user);
	$table->construct_header($lang->can_manage_members, array("class" => 'align_center', "width" => 200));
	$table->construct_header($lang->can_manage_join_requests, array("class" => 'align_center', "width" => 200));
	$table->construct_header($lang->can_invite_members, array("class" => 'align_center', "width" => 200));
	$table->construct_header($lang->controls, array("class" => "align_center", "colspan" => 2, "width" => 200));

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
		{
			$canmanagemembers = $lang->yes;
		}
		else
		{
			$canmanagemembers = $lang->no;
		}

		if($leader['canmanagerequests'])
		{
			$canmanagerequests = $lang->yes;
		}
		else
		{
			$canmanagerequests = $lang->no;
		}

		if($leader['caninvitemembers'])
		{
			$caninvitemembers = $lang->yes;
		}
		else
		{
			$caninvitemembers = $lang->no;
		}

		$table->construct_cell("<strong>".build_profile_link($leader['username'], $leader['uid'], "_blank")."</strong>");
		$table->construct_cell($canmanagemembers, array("class" => "align_center"));
		$table->construct_cell($canmanagerequests, array("class" => "align_center"));
		$table->construct_cell($caninvitemembers, array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=user-groups&amp;action=edit_leader&lid={$leader['lid']}\">{$lang->edit}</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=user-groups&amp;action=delete_leader&amp;lid={$leader['lid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_group_leader_deletion}')\">{$lang->delete}</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_assigned_leaders, array("colspan" => 5));
		$table->construct_row();
	}

	$table->output($lang->group_leaders_for.' '.htmlspecialchars_uni($group['title']));

	$form = new Form("index.php?module=user-groups&amp;action=add_leader&amp;gid={$group['gid']}", "post");

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = array_merge($mybb->input, array(
				"canmanagemembers" => 1,
				"canmanagerequests" => 1,
				"caninvitemembers" => 1,
				"makeleadermember" => 0
			)
		);
	}

	$form_container = new FormContainer($lang->add_group_leader.' '.htmlspecialchars_uni($group['title']));
	$form_container->output_row($lang->username." <em>*</em>", "", $form->generate_text_box('username', htmlspecialchars_uni($mybb->get_input('username')), array('id' => 'username')), 'username');
	$form_container->output_row($lang->can_manage_group_members, $lang->can_manage_group_members_desc, $form->generate_yes_no_radio('canmanagemembers', $mybb->input['canmanagemembers']));
	$form_container->output_row($lang->can_manage_group_join_requests, $lang->can_manage_group_join_requests_desc, $form->generate_yes_no_radio('canmanagerequests', $mybb->input['canmanagerequests']));
	$form_container->output_row($lang->can_invite_group_members, $lang->can_invite_group_members_desc, $form->generate_yes_no_radio('caninvitemembers', $mybb->input['caninvitemembers']));
	$form_container->output_row($lang->make_user_member, $lang->make_user_member_desc, $form->generate_yes_no_radio('makeleadermember', $mybb->input['makeleadermember']));
	$form_container->end();

	// Autocompletion for usernames
	echo '
	<link rel="stylesheet" href="../jscripts/select2/select2.css">
	<script type="text/javascript" src="../jscripts/select2/select2.min.js?ver=1804"></script>
	<script type="text/javascript">
	<!--
	$("#username").select2({
		placeholder: "'.$lang->search_for_a_user.'",
		minimumInputLength: 2,
		multiple: false,
		ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
			url: "../xmlhttp.php?action=get_users",
			dataType: \'json\',
			data: function (term, page) {
				return {
					query: term // search term
				};
			},
			results: function (data, page) { // parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to alter remote JSON data
				return {results: data};
			}
		},
		initSelection: function(element, callback) {
			var query = $(element).val();
			if (query !== "") {
				$.ajax("../xmlhttp.php?action=get_users&getone=1", {
					data: {
						query: query
					},
					dataType: "json"
				}).done(function(data) { callback(data); });
			}
		}
	});
	// -->
	</script>';

	$buttons[] = $form->generate_submit_button($lang->save_group_leader);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete_leader")
{
	$query = $db->query("
		SELECT l.*, u.username
		FROM ".TABLE_PREFIX."groupleaders l
		INNER JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		WHERE l.lid='".$mybb->get_input('lid', MyBB::INPUT_INT)."'");
	$leader = $db->fetch_array($query);

	if(!$leader['lid'])
	{
		flash_message($lang->error_invalid_group_leader, 'error');
		admin_redirect("index.php?module=user-groups");
	}

	$query = $db->simple_select("usergroups", "*", "gid='{$leader['gid']}'");
	$group = $db->fetch_array($query);

	// User clicked no
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=user-groups");
	}

	$plugins->run_hooks("admin_user_groups_delete_leader");

	if($mybb->request_method == "post")
	{
		$plugins->run_hooks("admin_user_groups_delete_leader_commit");

		// Delete the leader
		$db->delete_query("groupleaders", "lid='{$leader['lid']}'");

		$plugins->run_hooks("admin_user_groups_delete_leader_commit_end");

		$cache->update_groupleaders();

		// Log admin action
		log_admin_action($leader['uid'], $leader['username'], $group['gid'], $group['title']);

		flash_message($lang->success_group_leader_deleted, 'success');
		admin_redirect("index.php?module=user-groups&action=leaders&gid={$group['gid']}");
	}
	else
	{
		$page->output_confirm_action("index.php?module=user-groups&amp;action=delete_leader&amp;lid={$leader['lid']}", $lang->confirm_group_leader_deletion);
	}
}

if($mybb->input['action'] == "edit_leader")
{
	$query = $db->query("
		SELECT l.*, u.username
		FROM ".TABLE_PREFIX."groupleaders l
		INNER JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		WHERE l.lid='".$mybb->get_input('lid', MyBB::INPUT_INT)."'
	");
	$leader = $db->fetch_array($query);

	if(!$leader['lid'])
	{
		flash_message($lang->error_invalid_group_leader, 'error');
		admin_redirect("index.php?module=user-groups");
	}

	$query = $db->simple_select("usergroups", "*", "gid='{$leader['gid']}'");
	$group = $db->fetch_array($query);

	$plugins->run_hooks("admin_user_groups_edit_leader");

	if($mybb->request_method == "post")
	{
		$updated_leader = array(
			"canmanagemembers" => $mybb->get_input('canmanagemembers', MyBB::INPUT_INT),
			"canmanagerequests" => $mybb->get_input('canmanagerequests', MyBB::INPUT_INT),
			"caninvitemembers" => $mybb->get_input('caninvitemembers', MyBB::INPUT_INT)
		);

		$plugins->run_hooks("admin_user_groups_edit_leader_commit");

		$db->update_query("groupleaders", $updated_leader, "lid={$leader['lid']}");

		$cache->update_groupleaders();

		// Log admin action
		log_admin_action($leader['uid'], $leader['username'], $group['gid'], $group['title']);

		flash_message($lang->success_group_leader_updated, 'success');
		admin_redirect("index.php?module=user-groups&action=leaders&gid={$group['gid']}");
	}

	if(!$errors)
	{
		$mybb->input = array_merge($mybb->input, $leader);
	}

	$page->add_breadcrumb_item($lang->group_leaders_for.' '.htmlspecialchars_uni($group['title']), "index.php?module=user-groups&action=leaders&gid={$group['gid']}");
	$leader['username'] = htmlspecialchars_uni($leader['username']);
	$page->add_breadcrumb_item($lang->edit_leader." {$leader['username']}");

	$page->output_header($lang->edit_group_leader);

	$sub_tabs = array();
	$sub_tabs['group_leaders'] = array(
		'title' => $lang->edit_group_leader,
		'link' => "index.php?module=user-groups&action=edit_leader&lid={$leader['lid']}",
		'description' => $lang->edit_group_leader_desc
	);

	$page->output_nav_tabs($sub_tabs, 'group_leaders');

	$form = new Form("index.php?module=user-groups&amp;action=edit_leader&amp;lid={$leader['lid']}", "post");

	$form_container = new FormContainer($lang->edit_group_leader);
	$form_container->output_row($lang->username." <em>*</em>", "", $leader['username']);

	$form_container->output_row($lang->can_manage_group_members, $lang->can_manage_group_members_desc, $form->generate_yes_no_radio('canmanagemembers', $mybb->input['canmanagemembers']));
	$form_container->output_row($lang->can_manage_group_join_requests, $lang->can_manage_group_join_requests_desc, $form->generate_yes_no_radio('canmanagerequests', $mybb->input['canmanagerequests']));
	$form_container->output_row($lang->can_invite_group_members, $lang->can_invite_group_members_desc, $form->generate_yes_no_radio('caninvitemembers', $mybb->input['caninvitemembers']));
	$buttons[] = $form->generate_submit_button($lang->save_group_leader);

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

		if(my_strpos($mybb->input['namestyle'], "{username}") === false)
		{
			$errors[] = $lang->error_missing_namestyle_username;
		}

		if(!$errors)
		{
			if($mybb->get_input('stars') < 1)
			{
				$mybb->input['stars'] = 0;
			}

			if(!$mybb->get_input('starimage'))
			{
				$mybb->input['starimage'] = "images/star.png";
			}

			$new_usergroup = array(
				"type" => 2,
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"namestyle" => $db->escape_string($mybb->input['namestyle']),
				"usertitle" => $db->escape_string($mybb->input['usertitle']),
				"stars" => $mybb->get_input('stars', MyBB::INPUT_INT),
				"starimage" => $db->escape_string($mybb->input['starimage']),
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
				$query = $db->simple_select("usergroups", "*", "gid='".$mybb->get_input('copyfrom', MyBB::INPUT_INT)."'");
				$existing_usergroup = $db->fetch_array($query);
				foreach(array_keys($usergroup_permissions) as $field)
				{
					$new_usergroup[$field] = $existing_usergroup[$field];
				}
			}

			$plugins->run_hooks("admin_user_groups_add_commit");

			$gid = $db->insert_query("usergroups", $new_usergroup);

			$plugins->run_hooks("admin_user_groups_add_commit_end");

			// Are we copying permissions? If so, copy all forum permissions too
			if($mybb->input['copyfrom'] > 0)
			{
				$query = $db->simple_select("forumpermissions", "*", "gid='".$mybb->get_input('copyfrom', MyBB::INPUT_INT)."'");
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

			// Log admin action
			log_admin_action($gid, $mybb->input['title']);

			$groups = $cache->read('usergroups');
			$grouptitles = array_column($groups, 'title');

			$message = $lang->success_group_created;
			if(in_array($mybb->input['title'], $grouptitles) && count(array_keys($grouptitles, $mybb->input['title'])) > 1)
			{
				$message = $lang->sprintf($lang->success_group_created_duplicate_title, htmlspecialchars_uni($mybb->input['title']));
			}

			flash_message($message, 'success');
			admin_redirect("index.php?module=user-groups&action=edit&gid={$gid}");
		}
	}

	$page->add_breadcrumb_item($lang->add_user_group);
	$page->output_header($lang->add_user_group);

	$page->output_nav_tabs($sub_tabs, 'add_group');
	$form = new Form("index.php?module=user-groups&amp;action=add", "post");

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = array_merge($mybb->input, array(
				"namestyle" => "{username}"
			)
		);
	}

	$form_container = new FormContainer($lang->add_user_group);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $mybb->get_input('title'), array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_description, "", $form->generate_text_box('description', $mybb->get_input('description'), array('id' => 'description')), 'description');
	$form_container->output_row($lang->username_style, $lang->username_style_desc, $form->generate_text_box('namestyle', $mybb->get_input('namestyle'), array('id' => 'namestyle')), 'namestyle');
	$form_container->output_row($lang->user_title, $lang->user_title_desc, $form->generate_text_box('usertitle', $mybb->get_input('usertitle'), array('id' => 'usertitle')), 'usertitle');

	$options[0] = $lang->do_not_copy_permissions;
	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
	while($usergroup = $db->fetch_array($query))
	{
		$options[$usergroup['gid']] = htmlspecialchars_uni($usergroup['title']);
	}
	$form_container->output_row($lang->copy_permissions_from, $lang->copy_permissions_from_desc, $form->generate_select_box('copyfrom', $options, $mybb->get_input('copyfrom'), array('id' => 'copyfrom')), 'copyfrom');

	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->save_user_group);
	$form->output_submit_wrapper($buttons);

	$form->end();
	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("usergroups", "*", "gid='".$mybb->get_input('gid', MyBB::INPUT_INT)."'");
	$usergroup = $db->fetch_array($query);

	if(!$usergroup['gid'])
	{
		flash_message($lang->error_invalid_user_group, 'error');
		admin_redirect("index.php?module=user-group");
	}
	else
	{
		if(preg_match("#<((m[^a])|(b[^diloru>])|(s[^aemptu >]))(\s*[^>]*)>#si", $mybb->get_input('namestyle')))
		{
			$errors[] = $lang->error_disallowed_namestyle_username;
			$mybb->input['namestyle'] = $usergroup['namestyle'];
		}
	}

	$plugins->run_hooks("admin_user_groups_edit");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->get_input('title')))
		{
			$errors[] = $lang->error_missing_title;
		}

		if(my_strpos($mybb->get_input('namestyle'), "{username}") === false)
		{
			$errors[] = $lang->error_missing_namestyle_username;
		}

		if($mybb->get_input('moderate') == 1 && $mybb->get_input('invite') == 1)
		{
			$errors[] = $lang->error_cannot_have_both_types;
		}

		if(!$errors)
		{
			if($mybb->get_input('joinable') == 1)
			{
				if($mybb->get_input('moderate') == 1)
				{
					$mybb->input['type'] = "4";
				}
				elseif($mybb->get_input('invite') == 1)
				{
					$mybb->input['type'] = "5";
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

			if($mybb->get_input('stars') < 1)
			{
				$mybb->input['stars'] = 0;
			}

			$updated_group = array(
				"type" => $mybb->get_input('type', MyBB::INPUT_INT),
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"namestyle" => $db->escape_string($mybb->input['namestyle']),
				"usertitle" => $db->escape_string($mybb->input['usertitle']),
				"stars" => $mybb->get_input('stars', MyBB::INPUT_INT),
				"starimage" => $db->escape_string($mybb->input['starimage']),
				"image" => $db->escape_string($mybb->input['image']),
				"isbannedgroup" => $mybb->get_input('isbannedgroup', MyBB::INPUT_INT),
				"canview" => $mybb->get_input('canview', MyBB::INPUT_INT),
				"canviewthreads" => $mybb->get_input('canviewthreads', MyBB::INPUT_INT),
				"canviewprofiles" => $mybb->get_input('canviewprofiles', MyBB::INPUT_INT),
				"candlattachments" => $mybb->get_input('candlattachments', MyBB::INPUT_INT),
				"canviewboardclosed" => $mybb->get_input('canviewboardclosed', MyBB::INPUT_INT),
				"canpostthreads" => $mybb->get_input('canpostthreads', MyBB::INPUT_INT),
				"canpostreplys" => $mybb->get_input('canpostreplys', MyBB::INPUT_INT),
				"canpostattachments" => $mybb->get_input('canpostattachments', MyBB::INPUT_INT),
				"canratethreads" => $mybb->get_input('canratethreads', MyBB::INPUT_INT),
				"modposts" => $mybb->get_input('modposts', MyBB::INPUT_INT),
				"modthreads" => $mybb->get_input('modthreads', MyBB::INPUT_INT),
				"mod_edit_posts" => $mybb->get_input('mod_edit_posts', MyBB::INPUT_INT),
				"modattachments" => $mybb->get_input('modattachments', MyBB::INPUT_INT),
				"caneditposts" => $mybb->get_input('caneditposts', MyBB::INPUT_INT),
				"candeleteposts" => $mybb->get_input('candeleteposts', MyBB::INPUT_INT),
				"candeletethreads" => $mybb->get_input('candeletethreads', MyBB::INPUT_INT),
				"caneditattachments" => $mybb->get_input('caneditattachments', MyBB::INPUT_INT),
				"canviewdeletionnotice" => $mybb->get_input('canviewdeletionnotice', MyBB::INPUT_INT),
				"canpostpolls" => $mybb->get_input('canpostpolls', MyBB::INPUT_INT),
				"canvotepolls" => $mybb->get_input('canvotepolls', MyBB::INPUT_INT),
				"canundovotes" => $mybb->get_input('canundovotes', MyBB::INPUT_INT),
				"canusepms" => $mybb->get_input('canusepms', MyBB::INPUT_INT),
				"cansendpms" => $mybb->get_input('cansendpms', MyBB::INPUT_INT),
				"cantrackpms" => $mybb->get_input('cantrackpms', MyBB::INPUT_INT),
				"candenypmreceipts" => $mybb->get_input('candenypmreceipts', MyBB::INPUT_INT),
				"pmquota" => $mybb->get_input('pmquota', MyBB::INPUT_INT),
				"maxpmrecipients" => $mybb->get_input('maxpmrecipients', MyBB::INPUT_INT),
				"cansendemail" => $mybb->get_input('cansendemail', MyBB::INPUT_INT),
				"cansendemailoverride" => $mybb->get_input('cansendemailoverride', MyBB::INPUT_INT),
				"maxemails" => $mybb->get_input('maxemails', MyBB::INPUT_INT),
				"emailfloodtime" => $mybb->get_input('emailfloodtime', MyBB::INPUT_INT),
				"canviewmemberlist" => $mybb->get_input('canviewmemberlist', MyBB::INPUT_INT),
				"canviewcalendar" => $mybb->get_input('canviewcalendar', MyBB::INPUT_INT),
				"canaddevents" => $mybb->get_input('canaddevents', MyBB::INPUT_INT),
				"canbypasseventmod" => $mybb->get_input('canbypasseventmod', MyBB::INPUT_INT),
				"canmoderateevents" => $mybb->get_input('canmoderateevents', MyBB::INPUT_INT),
				"canviewonline" => $mybb->get_input('canviewonline', MyBB::INPUT_INT),
				"canviewwolinvis" => $mybb->get_input('canviewwolinvis', MyBB::INPUT_INT),
				"canviewonlineips" => $mybb->get_input('canviewonlineips', MyBB::INPUT_INT),
				"cancp" => $mybb->get_input('cancp', MyBB::INPUT_INT),
				"issupermod" => $mybb->get_input('issupermod', MyBB::INPUT_INT),
				"cansearch" => $mybb->get_input('cansearch', MyBB::INPUT_INT),
				"canusercp" => $mybb->get_input('canusercp', MyBB::INPUT_INT),
				"canuploadavatars" => $mybb->get_input('canuploadavatars', MyBB::INPUT_INT),
				"canchangename" => $mybb->get_input('canchangename', MyBB::INPUT_INT),
				"canbereported" => $mybb->get_input('canbereported', MyBB::INPUT_INT),
				"canbeinvisible" => $mybb->get_input('canbeinvisible', MyBB::INPUT_INT),
				"canchangewebsite" => $mybb->get_input('canchangewebsite', MyBB::INPUT_INT),
				"showforumteam" => $mybb->get_input('showforumteam', MyBB::INPUT_INT),
				"usereputationsystem" => $mybb->get_input('usereputationsystem', MyBB::INPUT_INT),
				"cangivereputations" => $mybb->get_input('cangivereputations', MyBB::INPUT_INT),
				"candeletereputations" => $mybb->get_input('candeletereputations', MyBB::INPUT_INT),
				"reputationpower" => $mybb->get_input('reputationpower', MyBB::INPUT_INT),
				"maxreputationsday" => $mybb->get_input('maxreputationsday', MyBB::INPUT_INT),
				"maxreputationsperuser" => $mybb->get_input('maxreputationsperuser', MyBB::INPUT_INT),
				"maxreputationsperthread" => $mybb->get_input('maxreputationsperthread', MyBB::INPUT_INT),
				"attachquota" => $mybb->get_input('attachquota', MyBB::INPUT_INT),
				"cancustomtitle" => $mybb->get_input('cancustomtitle', MyBB::INPUT_INT),
				"canwarnusers" => $mybb->get_input('canwarnusers', MyBB::INPUT_INT),
				"canreceivewarnings" =>$mybb->get_input('canreceivewarnings', MyBB::INPUT_INT),
				"maxwarningsday" => $mybb->get_input('maxwarningsday', MyBB::INPUT_INT),
				"canmodcp" => $mybb->get_input('canmodcp', MyBB::INPUT_INT),
				"showinbirthdaylist" => $mybb->get_input('showinbirthdaylist', MyBB::INPUT_INT),
				"canoverridepm" => $mybb->get_input('canoverridepm', MyBB::INPUT_INT),
				"canusesig" => $mybb->get_input('canusesig', MyBB::INPUT_INT),
				"canusesigxposts" => $mybb->get_input('canusesigxposts', MyBB::INPUT_INT),
				"signofollow" => $mybb->get_input('signofollow', MyBB::INPUT_INT),
				"edittimelimit" => $mybb->get_input('edittimelimit', MyBB::INPUT_INT),
				"maxposts" => $mybb->get_input('maxposts', MyBB::INPUT_INT),
				"showmemberlist" => $mybb->get_input('showmemberlist', MyBB::INPUT_INT),
				"canmanageannounce" => $mybb->get_input('canmanageannounce', MyBB::INPUT_INT),
				"canmanagemodqueue" => $mybb->get_input('canmanagemodqueue', MyBB::INPUT_INT),
				"canmanagereportedcontent" => $mybb->get_input('canmanagereportedcontent', MyBB::INPUT_INT),
				"canviewmodlogs" => $mybb->get_input('canviewmodlogs', MyBB::INPUT_INT),
				"caneditprofiles" => $mybb->get_input('caneditprofiles', MyBB::INPUT_INT),
				"canbanusers" => $mybb->get_input('canbanusers', MyBB::INPUT_INT),
				"canviewwarnlogs" => $mybb->get_input('canviewwarnlogs', MyBB::INPUT_INT),
				"canuseipsearch" => $mybb->get_input('canuseipsearch', MyBB::INPUT_INT)
			);

			// Only update the candisplaygroup setting if not a default user group
			if($usergroup['type'] != 1)
			{
				$updated_group['candisplaygroup'] = $mybb->get_input('candisplaygroup', MyBB::INPUT_INT);
			}

			$plugins->run_hooks("admin_user_groups_edit_commit");

			$db->update_query("usergroups", $updated_group, "gid='{$usergroup['gid']}'");

			// Update the caches
			$cache->update_usergroups();
			$cache->update_forumpermissions();

			// Log admin action
			log_admin_action($usergroup['gid'], $mybb->input['title']);

			$groups = $cache->read('usergroups');
			$grouptitles = array_column($groups, 'title');

			$message = $lang->success_group_updated;
			if(in_array($mybb->input['title'], $grouptitles) && count(array_keys($grouptitles, $mybb->input['title'])) > 1)
			{
				$message = $lang->sprintf($lang->success_group_updated_duplicate_title, htmlspecialchars_uni($mybb->input['title']));
			}

			flash_message($message, 'success');
			admin_redirect("index.php?module=user-groups");
		}
	}

	$page->add_breadcrumb_item($lang->edit_user_group);
	$page->output_header($lang->edit_user_group);

	$sub_tabs = array();
	$sub_tabs['edit_group'] = array(
		'title' => $lang->edit_user_group,
		'description' => $lang->edit_user_group_desc
	);

	$form = new Form("index.php?module=user-groups&amp;action=edit&amp;gid={$usergroup['gid']}", "post");

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
			$usergroup['invite'] = 0;
		}
		elseif($usergroup['type'] == "4")
		{
			$usergroup['joinable'] = 1;
			$usergroup['moderate'] = 1;
			$usergroup['invite'] = 0;
		}
		elseif($usergroup['type'] == "5")
		{
			$usergroup['joinable'] = 1;
			$usergroup['moderate'] = 0;
			$usergroup['invite'] = 1;
		}
		else
		{
			$usergroup['joinable'] = 0;
			$usergroup['moderate'] = 0;
			$usergroup['invite'] = 0;
		}
		$mybb->input = array_merge($mybb->input, $usergroup);
	}
	$tabs = array(
		"general" => $lang->general,
		"forums_posts" => $lang->forums_posts,
		"users_permissions" => $lang->users_permissions,
		"misc" => $lang->misc,
		"modcp" => $lang->mod_cp
	);
	$tabs = $plugins->run_hooks("admin_user_groups_edit_graph_tabs", $tabs);
	$page->output_tab_control($tabs);

	echo "<div id=\"tab_general\">
	<script type=\"text/javascript\">
		$(function(){
			$('input[name=\"moderate\"]').parents(\".group_settings_bit\").addClass(\"joinable_dependent\");
			$('input[name=\"invite\"]').parents(\".group_settings_bit\").addClass(\"joinable_dependent\");
			if($('input[name=\"joinable\"]').prop(\"checked\") == false){
				$(\".joinable_dependent\").hide();
			}
			$('input[name=\"joinable\"]').on('change', function() {
				$(\".joinable_dependent\").slideToggle();
			})
		});
	</script>";
	$form_container = new FormContainer($lang->general);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_description, "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->username_style, $lang->username_style_desc, $form->generate_text_box('namestyle', $mybb->input['namestyle'], array('id' => 'namestyle')), 'namestyle');
	$form_container->output_row($lang->user_title, $lang->user_title_desc, $form->generate_text_box('usertitle', $mybb->input['usertitle'], array('id' => 'usertitle')), 'usertitle');

	$stars = "<table cellpadding=\"3\"><tr><td>".$form->generate_numeric_field('stars', $mybb->input['stars'], array('class' => 'field50', 'id' => 'stars', 'min' => 0))."</td><td>".$form->generate_text_box('starimage', $mybb->input['starimage'], array('id' => 'starimage'))."</td></tr>";
	$stars .= "<tr><td><small>{$lang->stars}</small></td><td><small>{$lang->star_image}</small></td></tr></table>";
	$form_container->output_row($lang->user_stars, $lang->user_stars_desc, $stars, "stars");

	$form_container->output_row($lang->group_image, $lang->group_image_desc, $form->generate_text_box('image', $mybb->input['image'], array('id' => 'image')), 'image');

	$general_options = array();
	$general_options[] = $form->generate_check_box("showmemberlist", 1, $lang->member_list, array("checked" => $mybb->input['showmemberlist']));
	if($usergroup['gid'] != "1" && $usergroup['gid'] != "5")
	{
		$general_options[] = $form->generate_check_box("showforumteam", 1, $lang->forum_team, array("checked" => $mybb->input['showforumteam']));
	}
	$general_options[] =	$form->generate_check_box("isbannedgroup", 1, $lang->is_banned_group, array("checked" => $mybb->input['isbannedgroup']));

	$form_container->output_row($lang->general_options, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

	if($usergroup['type'] != 1)
	{
		$public_options = array(
			$form->generate_check_box("joinable", 1, $lang->user_joinable, array("checked" => $mybb->input['joinable'])),
			$form->generate_check_box("moderate", 1, $lang->moderate_join_requests, array("checked" => $mybb->input['moderate'])),
			$form->generate_check_box("invite", 1, $lang->invite_only, array("checked" => $mybb->input['invite'])),
			$form->generate_check_box("candisplaygroup", 1, $lang->can_set_as_display_group, array("checked" => $mybb->input['candisplaygroup'])),
			);
		$form_container->output_row($lang->publicly_joinable_options, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $public_options)."</div>");
	}

	$admin_options = array(
		$form->generate_check_box("issupermod", 1, $lang->is_super_mod, array("checked" => $mybb->input['issupermod'])),
		$form->generate_check_box("canmodcp", 1, $lang->can_access_mod_cp, array("checked" => $mybb->input['canmodcp'])),
		$form->generate_check_box("cancp", 1, $lang->can_access_admin_cp, array("checked" => $mybb->input['cancp']))
	);
	$form_container->output_row($lang->moderation_administration_options, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $admin_options)."</div>");

	$form_container->end();
	echo "</div>";

	//
	// FORUMS AND POSTS
	//
	echo "<div id=\"tab_forums_posts\">";
	$form_container = new FormContainer($lang->forums_posts);

	$viewing_options = array(
		$form->generate_check_box("canview", 1, $lang->can_view_board, array("checked" => $mybb->input['canview'])),
		$form->generate_check_box("canviewthreads", 1, $lang->can_view_threads, array("checked" => $mybb->input['canviewthreads'])),
		$form->generate_check_box("cansearch", 1, $lang->can_search_forums, array("checked" => $mybb->input['cansearch'])),
		$form->generate_check_box("canviewprofiles", 1, $lang->can_view_profiles, array("checked" => $mybb->input['canviewprofiles'])),
		$form->generate_check_box("candlattachments", 1, $lang->can_download_attachments, array("checked" => $mybb->input['candlattachments'])),
		$form->generate_check_box("canviewboardclosed", 1, $lang->can_view_board_closed, array("checked" => $mybb->input['canviewboardclosed']))
	);
	$form_container->output_row($lang->viewing_options, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $viewing_options)."</div>");

	$posting_options = array(
		$form->generate_check_box("canpostthreads", 1, $lang->can_post_threads, array("checked" => $mybb->input['canpostthreads'])),
		$form->generate_check_box("canpostreplys", 1, $lang->can_post_replies, array("checked" => $mybb->input['canpostreplys'])),
		$form->generate_check_box("canratethreads", 1, $lang->can_rate_threads, array("checked" => $mybb->input['canratethreads'])),
		"{$lang->max_posts_per_day}<br /><small class=\"input\">{$lang->max_posts_per_day_desc}</small><br />".$form->generate_numeric_field('maxposts', $mybb->input['maxposts'], array('id' => 'maxposts', 'class' => 'field50', 'min' => 0))
	);
	$form_container->output_row($lang->posting_rating_options, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $posting_options)."</div>");

	$moderator_options = array(
		$form->generate_check_box("modposts", 1, $lang->mod_new_posts, array("checked" => $mybb->input['modposts'])),
		$form->generate_check_box("modthreads", 1, $lang->mod_new_threads, array("checked" => $mybb->input['modthreads'])),
		$form->generate_check_box("modattachments", 1, $lang->mod_new_attachments, array("checked" => $mybb->input['modattachments'])),
		$form->generate_check_box("mod_edit_posts", 1, $lang->mod_after_edit, array("checked" => $mybb->input['mod_edit_posts']))
	);
	$form_container->output_row($lang->moderation_options, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $moderator_options)."</div>");

	$poll_options = array(
		$form->generate_check_box("canpostpolls", 1, $lang->can_post_polls, array("checked" => $mybb->input['canpostpolls'])),
		$form->generate_check_box("canvotepolls", 1, $lang->can_vote_polls, array("checked" => $mybb->input['canvotepolls'])),
		$form->generate_check_box("canundovotes", 1, $lang->can_undo_votes, array("checked" => $mybb->input['canundovotes']))
	);
	$form_container->output_row($lang->poll_options, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $poll_options)."</div>");

	$attachment_options = array(
		$form->generate_check_box("canpostattachments", 1, $lang->can_post_attachments, array("checked" => $mybb->input['canpostattachments'])),
		"{$lang->attach_quota}<br /><small class=\"input\">{$lang->attach_quota_desc}</small><br />".$form->generate_numeric_field('attachquota', $mybb->input['attachquota'], array('id' => 'attachquota', 'class' => 'field50', 'min' => 0)). "KB"
	);
	$form_container->output_row($lang->attachment_options, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $attachment_options)."</div>");

	// Remove these options if the group being editied is Guest (GID=1)
	if($usergroup['gid'] != 1)
	{
		$editing_options = array(
			$form->generate_check_box("caneditposts", 1, $lang->can_edit_posts, array("checked" => $mybb->input['caneditposts'])),
			$form->generate_check_box("candeleteposts", 1, $lang->can_delete_posts, array("checked" => $mybb->input['candeleteposts'])),
			$form->generate_check_box("candeletethreads", 1, $lang->can_delete_threads, array("checked" => $mybb->input['candeletethreads'])),
			$form->generate_check_box("caneditattachments", 1, $lang->can_edit_attachments, array("checked" => $mybb->input['caneditattachments'])),
			$form->generate_check_box("canviewdeletionnotice", 1, $lang->can_view_deletion_notices, array("checked" => $mybb->input['canviewdeletionnotice'])),
			"{$lang->edit_time_limit}<br /><small class=\"input\">{$lang->edit_time_limit_desc}</small><br />".$form->generate_numeric_field('edittimelimit', $mybb->input['edittimelimit'], array('id' => 'edittimelimit', 'class' => 'field50', 'min' => 0))
		);
		$form_container->output_row($lang->editing_deleting_options, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $editing_options)."</div>");
	}

	$form_container->end();
	echo "</div>";

	//
	// USERS AND PERMISSIONS
	//
	echo "<div id=\"tab_users_permissions\">";
	$form_container = new FormContainer($lang->users_permissions);

	$account_options = array(
		$form->generate_check_box("canbereported", 1, $lang->can_be_reported, array("checked" => $mybb->input['canbereported'])),
		$form->generate_check_box("canbeinvisible", 1, $lang->can_be_invisible, array("checked" => $mybb->input['canbeinvisible'])),
		$form->generate_check_box("canusercp", 1, $lang->can_access_usercp, array("checked" => $mybb->input['canusercp'])),
		$form->generate_check_box("canchangename", 1, $lang->can_change_username, array("checked" => $mybb->input['canchangename'])),
		$form->generate_check_box("cancustomtitle", 1, $lang->can_use_usertitles, array("checked" => $mybb->input['cancustomtitle'])),
		$form->generate_check_box("canuploadavatars", 1, $lang->can_upload_avatars, array("checked" => $mybb->input['canuploadavatars'])),
		$form->generate_check_box("canusesig", 1, $lang->can_use_signature, array("checked" => $mybb->input['canusesig'])),
		$form->generate_check_box("signofollow", 1, $lang->uses_no_follow, array("checked" => $mybb->input['signofollow'])),
		$form->generate_check_box("canchangewebsite", 1, $lang->can_change_website, array("checked" => $mybb->input['canchangewebsite'])),
		"{$lang->required_posts}<br /><small class=\"input\">{$lang->required_posts_desc}</small><br />".$form->generate_numeric_field('canusesigxposts', $mybb->input['canusesigxposts'], array('id' => 'canusesigxposts', 'class' => 'field50', 'min' => 0))
	);
	$form_container->output_row($lang->account_management, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $account_options)."</div>");

	$reputation_options = array(
		$form->generate_check_box("usereputationsystem", 1, $lang->show_reputations, array("checked" => $mybb->input['usereputationsystem'])),
		$form->generate_check_box("cangivereputations", 1, $lang->can_give_reputation, array("checked" => $mybb->input['cangivereputations'])),
		$form->generate_check_box("candeletereputations", 1, $lang->can_delete_own_reputation, array("checked" => $mybb->input['candeletereputations'])),
		"{$lang->points_to_award_take}<br /><small class=\"input\">{$lang->points_to_award_take_desc}</small><br />".$form->generate_numeric_field('reputationpower', $mybb->input['reputationpower'], array('id' => 'reputationpower', 'class' => 'field50', 'min' => 0)),
		"{$lang->max_reputations_perthread}<br /><small class=\"input\">{$lang->max_reputations_perthread_desc}</small><br />".$form->generate_numeric_field('maxreputationsperthread', $mybb->input['maxreputationsperthread'], array('id' => 'maxreputationsperthread', 'class' => 'field50', 'min' => 0)),
		"{$lang->max_reputations_peruser}<br /><small class=\"input\">{$lang->max_reputations_peruser_desc}</small><br />".$form->generate_numeric_field('maxreputationsperuser', $mybb->input['maxreputationsperuser'], array('id' => 'maxreputationsperuser', 'class' => 'field50', 'min' => 0)),
		"{$lang->max_reputations_daily}<br /><small class=\"input\">{$lang->max_reputations_daily_desc}</small><br />".$form->generate_numeric_field('maxreputationsday', $mybb->input['maxreputationsday'], array('id' => 'maxreputationsday', 'class' => 'field50', 'min' => 0))
	);
	$form_container->output_row($lang->reputation_system, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $reputation_options)."</div>");

	$warning_options = array(
		$form->generate_check_box("canwarnusers", 1, $lang->can_send_warnings, array("checked" => $mybb->input['canwarnusers'])),
		$form->generate_check_box("canreceivewarnings", 1, $lang->can_receive_warnings, array("checked" => $mybb->input['canreceivewarnings'])),
		"{$lang->warnings_per_day}<br />".$form->generate_numeric_field('maxwarningsday', $mybb->input['maxwarningsday'], array('id' => 'maxwarningsday', 'class' => 'field50'))
	);
	$form_container->output_row($lang->warning_system, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $warning_options)."</div>");

	$pm_options = array(
		$form->generate_check_box("canusepms", 1, $lang->can_use_pms, array("checked" => $mybb->input['canusepms'])),
		$form->generate_check_box("cansendpms", 1, $lang->can_send_pms, array("checked" => $mybb->input['cansendpms'])),
		$form->generate_check_box("canoverridepm", 1, $lang->can_override_pms, array("checked" => $mybb->input['canoverridepm'])),
		$form->generate_check_box("cantrackpms", 1, $lang->can_track_pms, array("checked" => $mybb->input['cantrackpms'])),
		$form->generate_check_box("candenypmreceipts", 1, $lang->can_deny_reciept, array("checked" => $mybb->input['candenypmreceipts'])),
		"{$lang->message_quota}<br /><small>{$lang->message_quota_desc}</small><br />".$form->generate_numeric_field('pmquota', $mybb->input['pmquota'], array('id' => 'pmquota', 'class' => 'field50', 'min' => 0)),
		"{$lang->max_recipients}<br /><small>{$lang->max_recipients_desc}</small><br />".$form->generate_numeric_field('maxpmrecipients', $mybb->input['maxpmrecipients'], array('id' => 'maxpmrecipients', 'class' => 'field50', 'min' => 0))
	);
	$form_container->output_row($lang->private_messaging, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $pm_options)."</div>");

	$form_container->end();
	echo "</div>";

	//
	// MISC
	//
	echo "<div id=\"tab_misc\">";
	$form_container = new FormContainer($lang->misc);

	$calendar_options = array(
		$form->generate_check_box("canviewcalendar", 1, $lang->can_view_calendar, array("checked" => $mybb->input['canviewcalendar'])),
		$form->generate_check_box("canaddevents", 1, $lang->can_post_events, array("checked" => $mybb->input['canaddevents'])),
		$form->generate_check_box("canbypasseventmod", 1, $lang->can_bypass_event_moderation, array("checked" => $mybb->input['canbypasseventmod'])),
		$form->generate_check_box("canmoderateevents", 1, $lang->can_moderate_events, array("checked" => $mybb->input['canmoderateevents']))
	);
	$form_container->output_row($lang->calendar, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $calendar_options)."</div>");

	$wol_options = array(
		$form->generate_check_box("canviewonline", 1, $lang->can_view_whos_online, array("checked" => $mybb->input['canviewonline'])),
		$form->generate_check_box("canviewwolinvis", 1, $lang->can_view_invisible, array("checked" => $mybb->input['canviewwolinvis'])),
		$form->generate_check_box("canviewonlineips", 1, $lang->can_view_ips, array("checked" => $mybb->input['canviewonlineips']))
	);
	$form_container->output_row($lang->whos_online, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $wol_options)."</div>");

	$misc_options = array(
		$form->generate_check_box("canviewmemberlist", 1, $lang->can_view_member_list, array("checked" => $mybb->input['canviewmemberlist'])),
		$form->generate_check_box("showinbirthdaylist", 1, $lang->show_in_birthday_list, array("checked" => $mybb->input['showinbirthdaylist'])),
		$form->generate_check_box("cansendemail", 1, $lang->can_email_users, array("checked" => $mybb->input['cansendemail'])),
		$form->generate_check_box("cansendemailoverride", 1, $lang->can_email_users_override, array("checked" => $mybb->input['cansendemailoverride'])),
		"{$lang->max_emails_per_day}<br /><small class=\"input\">{$lang->max_emails_per_day_desc}</small><br />".$form->generate_numeric_field('maxemails', $mybb->input['maxemails'], array('id' => 'maxemails', 'class' => 'field50', 'min' => 0)),
		"{$lang->email_flood_time}<br /><small class=\"input\">{$lang->email_flood_time_desc}</small><br />".$form->generate_numeric_field('emailfloodtime', $mybb->input['emailfloodtime'], array('id' => 'emailfloodtime', 'class' => 'field50', 'min' => 0))
	);
	$form_container->output_row($lang->misc, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $misc_options)."</div>");

	$form_container->end();
	echo "</div>";

	//
	// MODERATOR CP
	//
	echo "<div id=\"tab_modcp\">";
	$form_container = new FormContainer($lang->mod_cp);

	$forum_post_options = array(
		$form->generate_check_box("canmanageannounce", 1, $lang->can_manage_announce, array("checked" => $mybb->input['canmanageannounce'])),
		$form->generate_check_box("canmanagemodqueue", 1, $lang->can_manage_mod_queue, array("checked" => $mybb->input['canmanagemodqueue'])),
		$form->generate_check_box("canmanagereportedcontent", 1, $lang->can_manage_reported_content, array("checked" => $mybb->input['canmanagereportedcontent'])),
		$form->generate_check_box("canviewmodlogs", 1, $lang->can_view_mod_logs, array("checked" => $mybb->input['canviewmodlogs']))
	);
	$form_container->output_row($lang->forum_post_options, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $forum_post_options)."</div>");

	$user_options = array(
		$form->generate_check_box("caneditprofiles", 1, $lang->can_edit_profiles, array("checked" => $mybb->input['caneditprofiles'])),
		$form->generate_check_box("canbanusers", 1, $lang->can_ban_users, array("checked" => $mybb->input['canbanusers'])),
		$form->generate_check_box("canviewwarnlogs", 1, $lang->can_view_warnlogs, array("checked" => $mybb->input['canviewwarnlogs'])),
		$form->generate_check_box("canuseipsearch", 1, $lang->can_use_ipsearch, array("checked" => $mybb->input['canuseipsearch']))
	);
	$form_container->output_row($lang->user_options, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $user_options)."</div>");

	$form_container->end();
	echo "</div>";

	$plugins->run_hooks("admin_user_groups_edit_graph");

	$buttons[] = $form->generate_submit_button($lang->save_user_group);
	$form->output_submit_wrapper($buttons);

	$form->end();
	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("usergroups", "*", "gid='".$mybb->get_input('gid', MyBB::INPUT_INT)."'");
	$usergroup = $db->fetch_array($query);

	if(!$usergroup['gid'])
	{
		flash_message($lang->error_invalid_user_group, 'error');
		admin_redirect("index.php?module=user-groups");
	}
	if($usergroup['type'] == 1)
	{
		flash_message($lang->error_default_group_delete, 'error');
		admin_redirect("index.php?module=user-groups");
	}

	// User clicked no
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=user-groups");
	}

	$plugins->run_hooks("admin_user_groups_delete");

	if($mybb->request_method == "post")
	{
		if($usergroup['isbannedgroup'] == 1)
		{
			// If banned group, move users to default banned group
			$updated_users = array("usergroup" => 7);
		}
		else
		{
			// Move any users back to the registered group
			$updated_users = array("usergroup" => 2);
		}

		$db->update_query("users", $updated_users, "usergroup='{$usergroup['gid']}'");

		$updated_users = array("displaygroup" => "usergroup");
		$plugins->run_hooks("admin_user_groups_delete_commit");

		$db->update_query("users", $updated_users, "displaygroup='{$usergroup['gid']}'", "", true); // No quotes = displaygroup=usergroup

		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$query = $db->simple_select("users", "uid", "','||additionalgroups||',' LIKE '%,{$usergroup['gid']},%'");
				break;
			default:
				$query = $db->simple_select("users", "uid", "CONCAT(',',additionalgroups,',') LIKE '%,{$usergroup['gid']},%'");
		}
		while($user = $db->fetch_array($query))
		{
			leave_usergroup($user['uid'], $usergroup['gid']);
		}

		$db->update_query("banned", array("gid" => 7), "gid='{$usergroup['gid']}'");
		$db->update_query("banned", array("oldgroup" => 2), "oldgroup='{$usergroup['gid']}'");
		$db->update_query("banned", array("olddisplaygroup" => "oldgroup"), "olddisplaygroup='{$usergroup['gid']}'", "", true); // No quotes = displaygroup=usergroup

		$db->delete_query("forumpermissions", "gid='{$usergroup['gid']}'");
		$db->delete_query("calendarpermissions", "gid='{$usergroup['gid']}'");
		$db->delete_query("joinrequests", "gid='{$usergroup['gid']}'");
		$db->delete_query("moderators", "id='{$usergroup['gid']}' AND isgroup='1'");
		$db->delete_query("groupleaders", "gid='{$usergroup['gid']}'");
		$db->delete_query("usergroups", "gid='{$usergroup['gid']}'");

		$plugins->run_hooks("admin_user_groups_delete_commit_end");

		$cache->update_groupleaders();
		$cache->update_moderators();
		$cache->update_usergroups();
		$cache->update_forumpermissions();

		// Log admin action
		log_admin_action($usergroup['gid'], $usergroup['title']);

		flash_message($lang->success_group_deleted, 'success');
		admin_redirect("index.php?module=user-groups");
	}
	else
	{
		$page->output_confirm_action("index.php?module=user-groups&amp;action=delete&amp;gid={$usergroup['gid']}", $lang->confirm_group_deletion);
	}
}

if($mybb->input['action'] == "disporder" && $mybb->request_method == "post")
{
	$plugins->run_hooks("admin_user_groups_disporder");

	foreach($mybb->input['disporder'] as $gid=>$order)
	{
		$gid = (int)$gid;
		$order = (int)$order;
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

	flash_message($lang->success_group_disporders_updated, 'success');
	admin_redirect("index.php?module=user-groups");
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
				$db->update_query("usergroups", array('disporder' => (int)$order), "gid='".(int)$gid."'");
			}

			$plugins->run_hooks("admin_user_groups_start_commit");

			$cache->update_usergroups();

			flash_message($lang->success_groups_disporder_updated, 'success');
			admin_redirect("index.php?module=user-groups");
		}
	}

	$page->output_header($lang->manage_user_groups);
	$page->output_nav_tabs($sub_tabs, 'manage_groups');

	$form = new Form("index.php?module=user-groups", "post", "groups");

	$primaryusers = $secondaryusers = array();

	$query = $db->query("
		SELECT g.gid, COUNT(u.uid) AS users
		FROM ".TABLE_PREFIX."users u
		LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup)
		GROUP BY g.gid
	");
	while($groupcount = $db->fetch_array($query))
	{
		$primaryusers[$groupcount['gid']] = $groupcount['users'];
	}

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$query = $db->query("
				SELECT g.gid, COUNT(u.uid) AS users
				FROM ".TABLE_PREFIX."users u
				LEFT JOIN ".TABLE_PREFIX."usergroups g ON (','|| u.additionalgroups|| ',' LIKE '%,'|| g.gid|| ',%')
				WHERE g.gid != '0' AND g.gid is not NULL GROUP BY g.gid
			");
			break;
		default:
			$query = $db->query("
				SELECT g.gid, COUNT(u.uid) AS users
				FROM ".TABLE_PREFIX."users u
				LEFT JOIN ".TABLE_PREFIX."usergroups g ON (CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%'))
				WHERE g.gid != '0' AND g.gid is not NULL GROUP BY g.gid
			");
	}
	while($groupcount = $db->fetch_array($query))
	{
		$secondaryusers[$groupcount['gid']] = $groupcount['users'];
	}

	$query = $db->query("
		SELECT g.gid, COUNT(r.uid) AS users
		FROM ".TABLE_PREFIX."joinrequests r
		LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=r.gid)
		GROUP BY g.gid
	");

	$joinrequests = array();
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
	while($leader = $db->fetch_array($query))
	{
		$leaders[$leader['gid']][] = build_profile_link(htmlspecialchars_uni($leader['username']), $leader['uid'], "_blank");
	}

	$form_container = new FormContainer($lang->user_groups);
	$form_container->output_row_header($lang->group);
	$form_container->output_row_header($lang->number_of_users, array("class" => "align_center", 'width' => '75'));
	$form_container->output_row_header($lang->order, array("class" => "align_center", 'width' => '5%'));
	$form_container->output_row_header($lang->controls, array("class" => "align_center"));

	$query = $db->simple_select("usergroups", "*", "", array('order_by' => 'disporder'));
	while($usergroup = $db->fetch_array($query))
	{
		if($usergroup['type'] > 1)
		{
			$icon = "<img src=\"styles/default/images/icons/custom.png\" alt=\"{$lang->custom_user_group}\" style=\"vertical-align: middle;\" />";
		}
		else
		{
			$icon = "<img src=\"styles/default/images/icons/default.png\" alt=\"{$lang->default_user_group}\" style=\"vertical-align: middle;\" />";
		}

		$leaders_list = '';
		if(isset($leaders[$usergroup['gid']]))
		{
			$leaders_list = "<br />{$lang->group_leaders}: ".implode($lang->comma, $leaders[$usergroup['gid']]);
		}

		$join_requests = '';
		if(isset($joinrequests[$usergroup['gid']]) && $joinrequests[$usergroup['gid']] > 1 && $usergroup['type'] == 4)
		{
			$join_requests = " <small><a href=\"index.php?module=user-groups&amp;action=join_requests&amp;gid={$usergroup['gid']}\"><span style=\"color: red;\">({$joinrequests[$usergroup['gid']]} {$lang->outstanding_join_request})</span></a></small>";
		}
		else if(isset($joinrequests[$usergroup['gid']]) && $joinrequests[$usergroup['gid']] == 1 && $usergroup['type'] == 4)
		{
			$join_requests = " <small><a href=\"index.php?module=user-groups&amp;action=join_requests&amp;gid={$usergroup['gid']}\"><span style=\"color: red;\">({$joinrequests[$usergroup['gid']]} {$lang->outstanding_join_request})</span></a></small>";
		}

		$form_container->output_cell("<div class=\"float_right\">{$icon}</div><div><strong><a href=\"index.php?module=user-groups&amp;action=edit&amp;gid={$usergroup['gid']}\">".format_name(htmlspecialchars_uni($usergroup['title']), $usergroup['gid'])."</a></strong>{$join_requests}<br /><small>".htmlspecialchars_uni($usergroup['description'])."{$leaders_list}</small></div>");

		if(!isset($primaryusers[$usergroup['gid']]))
		{
			$primaryusers[$usergroup['gid']] = 0;
		}
		if(!isset($secondaryusers[$usergroup['gid']]))
		{
			$secondaryusers[$usergroup['gid']] = 0;
		}
		$numusers = $primaryusers[$usergroup['gid']];
		$numusers += $secondaryusers[$usergroup['gid']];

		$form_container->output_cell(my_number_format($numusers), array("class" => "align_center"));

		if($usergroup['showforumteam'] == 1)
		{
			$form_container->output_cell($form->generate_numeric_field("disporder[{$usergroup['gid']}]", "{$usergroup['disporder']}", array('class' => 'align_center', 'style' => 'width:80%')), array("class" => "align_center"));
		}
		else
		{
			$form_container->output_cell("&nbsp;", array("class" => "align_center"));
		}

		$popup = new PopupMenu("usergroup_{$usergroup['gid']}", $lang->options);
		$popup->add_item($lang->edit_group, "index.php?module=user-groups&amp;action=edit&amp;gid={$usergroup['gid']}");
		$popup->add_item($lang->list_users, "index.php?module=user-users&amp;action=search&amp;results=1&amp;conditions[usergroup]={$usergroup['gid']}");
		if(isset($joinrequests[$usergroup['gid']]) && $joinrequests[$usergroup['gid']] > 0 && $usergroup['type'] == 4)
		{
			$popup->add_item($lang->join_requests, "index.php?module=user-groups&amp;action=join_requests&amp;gid={$usergroup['gid']}");
		}
		$popup->add_item($lang->group_leaders, "index.php?module=user-groups&amp;action=leaders&amp;gid={$usergroup['gid']}");
		if($usergroup['type'] > 1)
		{
			$popup->add_item($lang->delete_group, "index.php?module=user-groups&amp;action=delete&amp;gid={$usergroup['gid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_group_deletion}')");
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
<img src="styles/default/images/icons/custom.png" alt="{$lang->custom_user_group}" style="vertical-align: middle;" /> {$lang->custom_user_group}<br />
<img src="styles/default/images/icons/default.png" alt="{$lang->default_user_group}" style="vertical-align: middle;" /> {$lang->default_user_group}
</fieldset>
LEGEND;

	$page->output_footer();
}
