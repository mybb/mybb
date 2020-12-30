<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->awaiting_activation, "index.php?module=user-awaiting_activation");

$sub_tabs['awaiting_activation'] = array(
	'title' => $lang->awaiting_activation,
	'link' => "index.php?module=user-awaiting_activation",
	'description' => $lang->awaiting_activation_desc
);

$plugins->run_hooks("admin_user_awaiting_activation_begin");

if($mybb->input['action'] == "activate" && $mybb->request_method == "post")
{
	$plugins->run_hooks("admin_user_awaiting_activation_activate");

	if(!is_array($mybb->input['user']))
	{
		$mybb->input['user'] = array();
	}

	$mybb->input['user'] = array_map('intval', $mybb->input['user']);
	$user_ids = implode(", ", $mybb->input['user']);

	if(empty($user_ids))
	{
		flash_message($lang->no_users_selected, 'error');
		admin_redirect("index.php?module=user-awaiting_activation");
	}

	$num_activated = $num_deleted = 0;
	$users_to_delete = array();
	if(!empty($mybb->input['delete'])) // Delete selected user(s)
	{
		require_once MYBB_ROOT.'inc/datahandlers/user.php';
		$userhandler = new UserDataHandler('delete');

		$query = $db->simple_select("users", "uid, usergroup", "uid IN ({$user_ids})");
		while($user = $db->fetch_array($query))
		{
			if($user['usergroup'] == 5)
			{
				++$num_deleted;
				$users_to_delete[] = (int)$user['uid'];
			}
		}

		if(!empty($users_to_delete))
		{
			$userhandler->delete_user($users_to_delete, 1);
		}

		$plugins->run_hooks("admin_user_awaiting_activation_activate_delete_commit");

		// Log admin action
		log_admin_action('deleted', $num_deleted);

		flash_message($lang->success_users_deleted, 'success');
		admin_redirect("index.php?module=user-awaiting_activation");
	}
	else // Activate selected user(s)
	{
		$query = $db->simple_select("users", "uid, username, email, usergroup, coppauser", "uid IN ({$user_ids})");
		while($user = $db->fetch_array($query))
		{
			++$num_activated;
			if($user['coppauser'])
			{
				$updated_user = array(
					"coppauser" => 0
				);
			}
			else
			{
				$db->delete_query("awaitingactivation", "uid='{$user['uid']}'");
			}

			// Move out of awaiting activation if they're in it.
			if($user['usergroup'] == 5)
			{
				$updated_user['usergroup'] = 2;
			}

			$db->update_query("users", $updated_user, "uid='{$user['uid']}'");

			$message = $lang->sprintf($lang->email_adminactivateaccount, $user['username'], $mybb->settings['bbname'], $mybb->settings['bburl']); my_mail($user['email'], $lang->sprintf($lang->emailsubject_activateaccount, $mybb->settings['bbname']), $message);
		}

		$cache->update_awaitingactivation();

		$plugins->run_hooks("admin_user_awaiting_activation_activate_commit");

		// Log admin action
		log_admin_action('activated', $num_activated);

		flash_message($lang->success_users_activated, 'success');
		admin_redirect("index.php?module=user-awaiting_activation");
	}
}
	
if(!$mybb->input['action']) 
{
	$plugins->run_hooks("admin_user_awaiting_activation_start");

	$query = $db->simple_select("users", "COUNT(uid) AS users", "usergroup='5'");
	$total_rows = $db->fetch_field($query, "users");

	$per_page = 20;

	$mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);

	if($mybb->input['page'] > 1)
	{
		$mybb->input['page'] = $mybb->input['page'];
		$start = ($mybb->input['page']*$per_page)-$per_page;
		$pages = ceil($total_rows / $per_page);
		if($mybb->input['page'] > $pages)
		{
			$mybb->input['page'] = 1;
			$start = 0;
		}
	}
	else
	{
		$mybb->input['page'] = 1;
		$start = 0;
	}

	$page->output_header($lang->manage_awaiting_activation);

	$page->output_nav_tabs($sub_tabs, 'awaiting_activation');

	$form = new Form("index.php?module=user-awaiting_activation&amp;action=activate", "post");

	$table = new Table;
	$table->construct_header($form->generate_check_box("allbox", 1, '', array('class' => 'checkall')));
	$table->construct_header($lang->username, array('width' => '20%'));
	$table->construct_header($lang->registered, array('width' => '15%', 'class' => 'align_center'));
	$table->construct_header($lang->last_active, array('width' => '15%', 'class' => 'align_center'));
	$table->construct_header($lang->email, array('width' => '15%', 'class' => 'align_center'));
	$table->construct_header($lang->ipaddress, array('width' => '10%', 'class' => 'align_center'));
	$table->construct_header($lang->type, array('class' => 'align_center'));

	$query = $db->query("
		SELECT u.uid, u.username, u.regdate, u.regip, u.lastactive, u.email, u.coppauser, a.type AS reg_type, a.validated
		FROM ".TABLE_PREFIX."users u
		LEFT JOIN ".TABLE_PREFIX."awaitingactivation a ON (a.uid=u.uid)
		WHERE u.usergroup='5'
		ORDER BY u.regdate DESC
		LIMIT {$start}, {$per_page}
	");
	while($user = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$user['username'] = htmlspecialchars_uni($user['username']);
		$user['profilelink'] = build_profile_link($user['username'], $user['uid'], "_blank");
		$user['email'] = htmlspecialchars_uni($user['email']);
		$user['regdate'] = my_date('relative', $user['regdate']);
		$user['lastactive'] = my_date('relative', $user['lastactive']);

		if($user['reg_type'] == 'r' || $user['reg_type'] == 'b' && $user['validated'] == 0)
		{
			$user['type'] = $lang->email_activation;
		}
		elseif($user['coppauser'] == 1)
		{
			$user['type'] = $lang->admin_activation_coppa;
		}
		else
		{
			$user['type'] = $lang->administrator_activation;
		}

		if(empty($user['regip']))
		{
			$user['regip'] = $lang->na;
		}
		else
		{
			$user['regip'] = my_inet_ntop($db->unescape_binary($user['regip']));
		}

		$table->construct_cell($form->generate_check_box("user[{$user['uid']}]", $user['uid'], ''));
		$table->construct_cell($user['profilelink']);
		$table->construct_cell($user['regdate'], array("class" => "align_center"));
		$table->construct_cell($user['lastactive'], array("class" => "align_center"));
		$table->construct_cell($user['email'], array("class" => "align_center"));
		$table->construct_cell($user['regip'], array("class" => "align_center"));
		$table->construct_cell($user['type'], array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_users_awaiting_activation, array('colspan' => 7));
		$table->construct_row();
		$table->output($lang->manage_awaiting_activation);
	}
	else
	{
		$table->output($lang->manage_awaiting_activation);
		$buttons[] = $form->generate_submit_button($lang->activate_users, array('onclick' => "return confirm('{$lang->confirm_activate_users}');"));
		$buttons[] = $form->generate_submit_button($lang->delete_users, array('name' => 'delete', 'onclick' => "return confirm('{$lang->confirm_delete_users}');"));
		$form->output_submit_wrapper($buttons);
	}

	$form->end();

	echo "<br />".draw_admin_pagination($mybb->input['page'], $per_page, $total_rows, "index.php?module=user-awaiting_activation&amp;page={page}");

	$page->output_footer();
}
