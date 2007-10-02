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

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item("Banning", "index.php?".SID."&amp;module=user/banning");

$sub_tabs['bans'] = array(
	'title' => "Banned Accounts",
	'link' => "index.php?".SID."&amp;module=user/banning",
	'description' => "Here you can manage user accounts which are banned from access to the board."
);

$sub_tabs['ban'] = array(
	'title' => "Ban a User",
	'link' => "index.php?".SID."&amp;module=user/banning&amp;action=ban",
	'description' => ""
);

// Fetch banned groups
$query = $db->simple_select("usergroups", "gid,title", "isbannedgroup=1", array('order_by' => 'title'));
while($group = $db->fetch_array($query))
{
	$banned_groups[$group['gid']] = $group['title'];
}

// Fetch ban times
$ban_times = fetch_ban_times();

if($mybb->input['action'] == "lift")
{
	$query = $db->simple_select("banned", "*", "uid='{$mybb->input['uid']}'");
	$ban = $db->fetch_array($query);
	$user = get_user($ban['uid']);

	if(!$ban['uid'])
	{
		flash_message("You have selected an invalid ban to edit.", 'error');
		admin_redirect("index.php?".SID."&module=user/banning");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=user/banning");
	}

	if($mybb->request_method == "post")
	{
		$updated_group = array(
			'usergroup' => $ban['oldgroup'],
			'additionalgroups' => $ban['oldadditionalgroups'],
			'displaygroup' => $ban['olddisplaygroup']
		);
		$db->update_query("users", $updated_group, "uid='{$ban['uid']}'");
		$db->delete_query("banned", "uid='{$ban['uid']}'");

		// Log admin action
		log_admin_action($mybb->input['uid'], $user['username']);

		flash_message("The ban has successfully been lifted.", 'success');
		admin_redirect("index.php?".SID."&module=user/banning");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=user/banning&amp;action=lift&amp;uid={$ban['uid']}", "Are you sure you want to lift this ban?");
	}
}

if($mybb->input['action'] == "ban")
{
	if($mybb->request_method == "post")
	{
		$query = $db->simple_select("users", "uid, usergroup, additionalgroups, displaygroup", "LOWER(username)='".$db->escape_string(my_strtolower($mybb->input['username']))."'", array('limit' => 1));
		$user = $db->fetch_array($query);

		if(!$user['uid'])
		{
			$errors[] = "The username you have entered is invalid and does not exist.";
		}
		// Is the user we're trying to ban a super admin and we're not?
		else if(is_super_admin($user['uid']) && !is_super_admin($user['uid']))
		{
			$errors[] = "You do not have permission to ban this user.";
		}
		else
		{
			$query = $db->simple_select("banned", "uid", "uid='{$user['uid']}'");
			if($db->fetch_field($query, "uid"))
			{
				$errors[] = "This user already belongs to a banned group and cannot be added to a new one.";
			}
		}

		if($user['uid'] == $mybb->user['uid'])
		{
			$errors[] = "You cannot ban yourself.";
		}

		// Reason?
		if(!$mybb->input['reason'])
		{
			$errors[] = "You did not enter a reason to ban this user.";
		}

		// No errors? Insert
		if(!$errors)
		{
			// Ban the user
			if($mybb->input['bantime'] == '---')
			{
				$lifted = 0;
			}
			else
			{
				$lifted = ban_date2timestamp($mybb->input['bantime']);
			}

			if(count($banned_groups) == 1)
			{
				$group = array_keys($banned_groups);
				$mybb->input['usergroup'] = $group[0];
			}
							
			$insert_array = array(
				'uid' => $user['uid'],
				'gid' => intval($mybb->input['usergroup']),
				'oldgroup' => $user['usergroup'],
				'oldadditionalgroups' => $user['additionalgroups'],
				'olddisplaygroup' => $user['displaygroup'],
				'admin' => intval($mybb->user['uid']),
				'dateline' => TIME_NOW,
				'bantime' => $db->escape_string($mybb->input['bantime']),
				'lifted' => $db->escape_string($lifted),
				'reason' => $db->escape_string($mybb->input['reason'])
			);
			$db->insert_query('banned', $insert_array);
			
			// Move the user to the banned group
			$update_array = array(
				'usergroup' => intval($mybb->input['usergroup']),
				'displaygroup' => 0,
				'additionalgroups' => '',
			);
			$db->update_query('users', $update_array, "uid = {$user['uid']}");

			// Log admin action
			log_admin_action($user['uid'], $user['username']);

			flash_message("The user has successfully been banned", 'success');
			admin_redirect("index.php?".SID."&module=user/banning");
		}
	}
	$page->add_breadcrumb_item("Ban a User");
	$page->output_header("Ban a User");
	$page->output_nav_tabs($sub_tabs, "ban");

	$form = new Form("index.php?".SID."&amp;module=user/banning&amp;action=ban", "post");
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	
	$form_container = new FormContainer("Ban a User");
	$form_container->output_row("Username <em>*</em>", "Auto-complete is enabled in this field.", $form->generate_text_box('username', $mybb->input['username'], array('id' => 'username')), 'username');
	$form_container->output_row("Ban Reason <em>*</em>", "", $form->generate_text_box('reason', $mybb->input['reason'], array('id' => 'reason')), 'reason');
	if(count($banned_groups) > 1)
	{
		$form_container->output_row("Banned Group <em>*</em>", "In order for this user to be banned they must be moved to a banned group", $form->generate_select_box('usergroup', $banned_groups, $mybb->input['usergroup'], array('id' => 'usergroup')), 'usergroup');
	}
	foreach($ban_times as $time => $period)
	{
		if($time != "---")
		{
			$friendly_time = my_date("D, jS M Y @ g:ia", ban_date2timestamp($time));
			$period = "{$period} ({$friendly_time})";
		}
		$length_list[$time] = $period;
	}
	$form_container->output_row("Ban Length <em>*</em>", "", $form->generate_select_box('bantime', $length_list, $mybb->input['bantime'], array('id' => 'bantime')), 'bantime');	

	$form_container->end();

	$buttons[] = $form->generate_submit_button("Ban User");
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("banned", "*", "uid='{$mybb->input['uid']}'");
	$ban = $db->fetch_array($query);

	$user = get_user($ban['uid']);

	if(!$ban['uid'])
	{
		flash_message("You have selected an invalid ban to edit.", 'error');
		admin_redirect("index.php?".SID."&module=user/banning");
	}

	if($mybb->request_method == "post")
	{
		// Reason?
		if(!$mybb->input['reason'])
		{
			$errors[] = "You did not enter a reason to ban this user.";
		}

		// No errors? Update
		if(!$errors)
		{
			// Ban the user
			if($mybb->input['bantime'] == '---')
			{
				$lifted = 0;
			}
			else
			{
				$lifted = ban_date2timestamp($mybb->input['bantime'], $ban['dateline']);
			}

			if(count($banned_groups) == 1)
			{
				$group = array_keys($banned_groups);
				$mybb->input['usergroup'] = $group[0];
			}

			$update_array = array(
				'gid' => intval($mybb->input['usergroup']),
				'dateline' => TIME_NOW,
				'bantime' => $db->escape_string($mybb->input['bantime']),
				'lifted' => $db->escape_string($lifted),
				'reason' => $db->escape_string($mybb->input['reason'])
			);
		
			$db->update_query('banned', $update_array, "uid='{$ban['uid']}'");
		
			// Move the user to the banned group
			$update_array = array(
				'usergroup' => intval($mybb->input['usergroup']),
				'displaygroup' => 0,
				'additionalgroups' => '',
			);
			$db->update_query('users', $update_array, "uid = {$ban['uid']}");

			// Log admin action
			log_admin_action($mybb->input['uid'], $user['username']);

			flash_message("The ban has successfully been updated.", 'success');
			admin_redirect("index.php?".SID."&module=user/banning");
		}
	}
	$page->add_breadcrumb_item("Edit Ban");
	$page->output_header("Edit Ban");

	$sub_tabs = array();
	$sub_tabs['edit'] = array(
		'title' => "Edit Ban",
		'description' => ""
	);
	$page->output_nav_tabs($sub_tabs, "edit");

	$form = new Form("index.php?".SID."&amp;module=user/banning&amp;action=edit&amp;uid={$ban['uid']}", "post");
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = $ban;
	}
	
	$form_container = new FormContainer("Edit Ban");
	$form_container->output_row("Username <em>*</em>", "", $user['username']);
	$form_container->output_row("Ban Reason <em>*</em>", "", $form->generate_text_box('reason', $mybb->input['reason'], array('id' => 'reason')), 'reason');
	if(count($banned_groups) > 1)
	{
		$form_container->output_row("Banned Group <em>*</em>", "In order for this user to be banned they must be moved to a banned group", $form->generate_select_box('usergroup', $banned_groups, $mybb->input['usergroup'], array('id' => 'usergroup')), 'usergroup');
	}
	foreach($ban_times as $time => $period)
	{
		if($time != "---")
		{
			$friendly_time = my_date("D, jS M Y @ g:ia", ban_date2timestamp($time));
			$period = "{$period} ({$friendly_time})";
		}
		$length_list[$time] = $period;
	}
	$form_container->output_row("Ban Length <em>*</em>", "", $form->generate_select_box('bantime', $length_list, $mybb->input['bantime'], array('id' => 'bantime')), 'bantime');	

	$form_container->end();

	$buttons[] = $form->generate_submit_button("Update Ban");
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header("Banned Accounts");

	$page->output_nav_tabs($sub_tabs, "bans");

	$query = $db->simple_select("banned", "COUNT(*) AS ban_count");
	$ban_count = $db->fetch_field($query, "ban_count");

	$per_page = 20;

	if($mybb->input['page'] > 0)
	{
		$current_page = intval($mybb->input['page']);
		$start = ($current_page-1)*$per_page;
		$pages = $ban_count / $per_page;
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

	$pagination = draw_admin_pagination($current_page, $per_page, $ban_count, "index.php?".SID."&amp;module=user/banning&amp;page={page}");

	$table = new Table;
	$table->construct_header("User");
	$table->construct_header("Ban Lifts On", array("class" => "align_center", "width" => 150));
	$table->construct_header("Time Left", array("class" => "align_center", "width" => 150));
	$table->construct_header($lang->controls, array("class" => "align_center", "colspan" => 2, "width" => 200));

	// Fetch bans
	$query = $db->query("
		SELECT b.*, a.username AS adminuser, u.username
		FROM ".TABLE_PREFIX."banned b
		LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid) 
		LEFT JOIN ".TABLE_PREFIX."users a ON (b.admin=a.uid) 
		ORDER BY lifted ASC
		LIMIT {$start}, {$per_page}
	");
	
	// Get the banned users
	while($ban = $db->fetch_array($query))
	{
		$profile_link = build_profile_link($ban['username'], $ban['uid']);
		$ban_date = my_date($mybb->settings['dateformat'], $ban['dateline']);
		if($ban['lifted'] == 'perm' || $ban['lifted'] == '' || $ban['bantime'] == 'perm' || $ban['bantime'] == '---')
		{
			$ban_peiod = "permanently";
			$time_remaning = $lifts_on = "N/A";
		}
		else
		{
			$ban_period = "for ".$ban_times[$ban['bantime']];

			$remaining = $ban['lifted']-TIME_NOW;
			$time_remaining = nice_time($remaining, array('short' => 1, 'seconds' => false))."";

			if($remaining < 3600)
			{
				$time_remaining = "<span style=\"color: red;\">{$time_remaining}</span>";
			}
			else if($remaining < 86400)
			{
				$time_remaining = "<span style=\"color: maroon;\">{$time_remaining}</span>";
			}
			else if($remaining < 604800)
			{
				$time_remaining = "<span style=\"color: green;\">{$time_remaining}</span>";
			}
			else
			{
				$time_remaining = "{$time_remaining}";
			}
			$lifts_on = my_date($mybb->settings['dateformat'], $ban['lifted']);
		}

		$table->construct_cell("<strong>{$profile_link}</strong><br /><small>Banned by {$ban['adminuser']} on {$ban_date} {$ban_period}</small>");
		$table->construct_cell($lifts_on, array("class" => "align_center"));
		$table->construct_cell($time_remaining, array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=user/banning&amp;action=edit&amp;uid={$ban['uid']}\">{$lang->edit}</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=user/banning&amp;action=lift&amp;uid={$ban['uid']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you want to lift this ban?');\">Lift</a>", array("class" => "align_center"));
		$table->construct_row();
	}

	if(count($table->rows) == 0)
	{
		$table->construct_cell("You don't have any banned users at the moment.", array("colspan" => "5"));
		$table->construct_row();
	}
	$table->output("Banned Accounts");
	echo $pagination;

	$page->output_footer();
}
?>