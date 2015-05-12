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

$page->add_breadcrumb_item($lang->banning, "index.php?module=user-banning");


$sub_tabs['ips'] = array(
	'title' => $lang->banned_ips,
	'link' => "index.php?module=config-banning",
);

$sub_tabs['bans'] = array(
	'title' => $lang->banned_accounts,
	'link' => "index.php?module=user-banning",
	'description' => $lang->banned_accounts_desc
);

$sub_tabs['usernames'] = array(
	'title' => $lang->disallowed_usernames,
	'link' => "index.php?module=config-banning&amp;type=usernames",
);

$sub_tabs['emails'] = array(
	'title' => $lang->disallowed_email_addresses,
	'link' => "index.php?module=config-banning&amp;type=emails",
);

// Fetch banned groups
$query = $db->simple_select("usergroups", "gid,title", "isbannedgroup=1", array('order_by' => 'title'));
while($group = $db->fetch_array($query))
{
	$banned_groups[$group['gid']] = $group['title'];
}

// Fetch ban times
$ban_times = fetch_ban_times();

$plugins->run_hooks("admin_user_banning_begin");

if($mybb->input['action'] == "prune")
{
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=user-banning");
	}

	$query = $db->simple_select("banned", "*", "uid='{$mybb->input['uid']}'");
	$ban = $db->fetch_array($query);

	if(!$ban['uid'])
	{
		flash_message($lang->error_invalid_ban, 'error');
		admin_redirect("index.php?module=user-banning");
	}

	$user = get_user($ban['uid']);

	if(is_super_admin($user['uid']) && ($mybb->user['uid'] != $user['uid'] && !is_super_admin($mybb->user['uid'])))
	{
		flash_message($lang->cannot_perform_action_super_admin_general, 'error');
		admin_redirect("index.php?module=user-banning");
	}

	$plugins->run_hooks("admin_user_banning_prune");

	if($mybb->request_method == "post")
	{
		require_once MYBB_ROOT."inc/class_moderation.php";
		$moderation = new Moderation();

		$query = $db->simple_select("threads", "tid", "uid='{$user['uid']}'");
		while($thread = $db->fetch_array($query))
		{
			$moderation->delete_thread($thread['tid']);
		}

		$query = $db->simple_select("posts", "pid", "uid='{$user['uid']}'");
		while($post = $db->fetch_array($query))
		{
			$moderation->delete_post($post['pid']);
		}

		$plugins->run_hooks("admin_user_banning_prune_commit");

		$cache->update_reportedcontent();

		// Log admin action
		log_admin_action($mybb->input['uid'], $user['username']);

		flash_message($lang->success_pruned, 'success');
		admin_redirect("index.php?module=user-banning");
	}
	else
	{
		$page->output_confirm_action("index.php?module=user-banning&amp;action=prune&amp;uid={$user['uid']}", $lang->confirm_prune);
	}
}

if($mybb->input['action'] == "lift")
{
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=user-banning");
	}

	$query = $db->simple_select("banned", "*", "uid='{$mybb->input['uid']}'");
	$ban = $db->fetch_array($query);

	if(!$ban['uid'])
	{
		flash_message($lang->error_invalid_ban, 'error');
		admin_redirect("index.php?module=user-banning");
	}

	$user = get_user($ban['uid']);

	if(is_super_admin($user['uid']) && ($mybb->user['uid'] != $user['uid'] && !is_super_admin($mybb->user['uid'])))
	{
		flash_message($lang->cannot_perform_action_super_admin_general, 'error');
		admin_redirect("index.php?module=user-banning");
	}

	$plugins->run_hooks("admin_user_banning_lift");

	if($mybb->request_method == "post")
	{
		$updated_group = array(
			'usergroup' => $ban['oldgroup'],
			'additionalgroups' => $ban['oldadditionalgroups'],
			'displaygroup' => $ban['olddisplaygroup']
		);
		$db->delete_query("banned", "uid='{$ban['uid']}'");

		$plugins->run_hooks("admin_user_banning_lift_commit");

		$db->update_query("users", $updated_group, "uid='{$ban['uid']}'");

		$cache->update_banned();
		$cache->update_moderators();

		// Log admin action
		log_admin_action($mybb->input['uid'], $user['username']);

		flash_message($lang->success_ban_lifted, 'success');
		admin_redirect("index.php?module=user-banning");
	}
	else
	{
		$page->output_confirm_action("index.php?module=user-banning&amp;action=lift&amp;uid={$ban['uid']}", $lang->confirm_lift_ban);
	}
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("banned", "*", "uid='{$mybb->input['uid']}'");
	$ban = $db->fetch_array($query);

	$user = get_user($ban['uid']);

	if(!$ban['uid'])
	{
		flash_message($lang->error_invalid_ban, 'error');
		admin_redirect("index.php?module=user-banning");
	}

	$plugins->run_hooks("admin_user_banning_edit");

	if($mybb->request_method == "post")
	{
		if(!$ban['uid'])
		{
			$errors[] = $lang->error_invalid_username;
		}
		// Is the user we're trying to ban a super admin and we're not?
		else if(is_super_admin($ban['uid']) && !is_super_admin($ban['uid']))
		{
			$errors[] = $lang->error_no_perm_to_ban;
		}

		if($ban['uid'] == $mybb->user['uid'])
		{
			$errors[] = $lang->error_ban_self;
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

			$reason = my_substr($mybb->input['reason'], 0, 255);

			if(count($banned_groups) == 1)
			{
				$group = array_keys($banned_groups);
				$mybb->input['usergroup'] = $group[0];
			}

			$update_array = array(
				'gid' => $mybb->get_input('usergroup', MyBB::INPUT_INT),
				'dateline' => TIME_NOW,
				'bantime' => $db->escape_string($mybb->input['bantime']),
				'lifted' => $db->escape_string($lifted),
				'reason' => $db->escape_string($reason)
			);

			$db->update_query('banned', $update_array, "uid='{$ban['uid']}'");

			// Move the user to the banned group
			$update_array = array(
				'usergroup' => $mybb->get_input('usergroup', MyBB::INPUT_INT),
				'displaygroup' => 0,
				'additionalgroups' => '',
			);
			$db->update_query('users', $update_array, "uid = {$ban['uid']}");

			$plugins->run_hooks("admin_user_banning_edit_commit");

			$cache->update_banned();

			// Log admin action
			log_admin_action($mybb->input['uid'], $user['username']);

			flash_message($lang->success_ban_updated, 'success');
			admin_redirect("index.php?module=user-banning");
		}
	}
	$page->add_breadcrumb_item($lang->edit_ban);
	$page->output_header($lang->edit_ban);

	$sub_tabs = array();
	$sub_tabs['edit'] = array(
		'title' => $lang->edit_ban,
		'description' => $lang->edit_ban_desc
	);
	$page->output_nav_tabs($sub_tabs, "edit");

	$form = new Form("index.php?module=user-banning&amp;action=edit&amp;uid={$ban['uid']}", "post");
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = array_merge($mybb->input, $ban);
	}

	$form_container = new FormContainer($lang->edit_ban);
	$form_container->output_row($lang->ban_username, "", $user['username']);
	$form_container->output_row($lang->ban_reason, "", $form->generate_text_area('reason', $mybb->input['reason'], array('id' => 'reason', 'maxlength' => '255')), 'reason');
	if(count($banned_groups) > 1)
	{
		$form_container->output_row($lang->ban_group, $lang->ban_group_desc, $form->generate_select_box('usergroup', $banned_groups, $mybb->input['usergroup'], array('id' => 'usergroup')), 'usergroup');
	}

	if($mybb->input['bantime'] == 'perm' || $mybb->input['bantime'] == '' || $mybb->input['lifted'] == 'perm' ||$mybb->input['lifted'] == '')
	{
		$mybb->input['bantime'] = '---';
		$mybb->input['lifted'] = '---';
	}

	foreach($ban_times as $time => $period)
	{
		if($time != '---')
		{
			$friendly_time = my_date("D, jS M Y @ g:ia", ban_date2timestamp($time));
			$period = "{$period} ({$friendly_time})";
		}
		$length_list[$time] = $period;
	}
	$form_container->output_row($lang->ban_time, "", $form->generate_select_box('bantime', $length_list, $mybb->input['bantime'], array('id' => 'bantime')), 'bantime');

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->update_ban);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$where_sql_full = $where_sql = '';

	$plugins->run_hooks("admin_user_banning_start");

	if($mybb->request_method == "post")
	{
		$options = array(
			'fields' => array('username', 'usergroup', 'additionalgroups', 'displaygroup')
		);

		$user = get_user_by_username($mybb->input['username'], $options);
		
		// Are we searching a user?
		if(isset($mybb->input['search']) && $mybb->get_input('search') != '')
		{
			$where_sql = 'uid=\''.(int)$user['uid'].'\'';
			$where_sql_full = 'WHERE b.uid=\''.(int)$user['uid'].'\'';
		}
		else
		{
			if(!$user['uid'])
			{
				$errors[] = $lang->error_invalid_username;
			}
			// Is the user we're trying to ban a super admin and we're not?
			else if(is_super_admin($user['uid']) && !is_super_admin($mybb->user['uid']))
			{
				$errors[] = $lang->error_no_perm_to_ban;
			}
			else
			{
				$query = $db->simple_select("banned", "uid", "uid='{$user['uid']}'");
				if($db->fetch_field($query, "uid"))
				{
					$errors[] = $lang->error_already_banned;
				}
				
				// Get PRIMARY usergroup information
				$usergroups = $cache->read("usergroups");
				if(!empty($usergroups[$user['usergroup']]) && $usergroups[$user['usergroup']]['isbannedgroup'] == 1)
				{
					$errors[] = $lang->error_already_banned;
				}
			}

			if($user['uid'] == $mybb->user['uid'])
			{
				$errors[] = $lang->error_ban_self;
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

				$reason = my_substr($mybb->input['reason'], 0, 255);

				if(count($banned_groups) == 1)
				{
					$group = array_keys($banned_groups);
					$mybb->input['usergroup'] = $group[0];
				}

				$insert_array = array(
					'uid' => $user['uid'],
					'gid' => $mybb->get_input('usergroup', MyBB::INPUT_INT),
					'oldgroup' => $user['usergroup'],
					'oldadditionalgroups' => $user['additionalgroups'],
					'olddisplaygroup' => $user['displaygroup'],
					'admin' => (int)$mybb->user['uid'],
					'dateline' => TIME_NOW,
					'bantime' => $db->escape_string($mybb->input['bantime']),
					'lifted' => $db->escape_string($lifted),
					'reason' => $db->escape_string($reason)
				);
				$db->insert_query('banned', $insert_array);

				// Move the user to the banned group
				$update_array = array(
					'usergroup' => $mybb->get_input('usergroup', MyBB::INPUT_INT),
					'displaygroup' => 0,
					'additionalgroups' => '',
				);

				$db->delete_query("forumsubscriptions", "uid = '{$user['uid']}'");
				$db->delete_query("threadsubscriptions", "uid = '{$user['uid']}'");

				$plugins->run_hooks("admin_user_banning_start_commit");

				$db->update_query('users', $update_array, "uid = '{$user['uid']}'");

				$cache->update_banned();

				// Log admin action
				log_admin_action($user['uid'], $user['username'], $lifted);

				flash_message($lang->success_banned, 'success');
				admin_redirect("index.php?module=user-banning");
			}
		}
	}

	$page->output_header($lang->banned_accounts);

	$page->output_nav_tabs($sub_tabs, "bans");

	$query = $db->simple_select("banned", "COUNT(*) AS ban_count", $where_sql);
	$ban_count = $db->fetch_field($query, "ban_count");

	$per_page = 20;

	if($mybb->input['page'] > 0)
	{
		$current_page = $mybb->get_input('page', MyBB::INPUT_INT);
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

	$pagination = draw_admin_pagination($current_page, $per_page, $ban_count, "index.php?module=user-banning&amp;page={page}");

	$table = new Table;
	$table->construct_header($lang->user);
	$table->construct_header($lang->ban_lifts_on, array("class" => "align_center", "width" => 150));
	$table->construct_header($lang->time_left, array("class" => "align_center", "width" => 150));
	$table->construct_header($lang->controls, array("class" => "align_center", "colspan" => 2, "width" => 200));
	$table->construct_header($lang->moderation, array("class" => "align_center", "colspan" => 1, "width" => 200));

	// Fetch bans
	$query = $db->query("
		SELECT b.*, a.username AS adminuser, u.username
		FROM ".TABLE_PREFIX."banned b
		LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users a ON (b.admin=a.uid)
		{$where_sql_full}
		ORDER BY dateline DESC
		LIMIT {$start}, {$per_page}
	");

	// Get the banned users
	while($ban = $db->fetch_array($query))
	{
		$profile_link = build_profile_link($ban['username'], $ban['uid'], "_blank");
		$ban_date = my_date($mybb->settings['dateformat'], $ban['dateline']);
		if($ban['lifted'] == 'perm' || $ban['lifted'] == '' || $ban['bantime'] == 'perm' || $ban['bantime'] == '---')
		{
			$ban_period = $lang->permenantly;
			$time_remaining = $lifts_on = $lang->na;
		}
		else
		{
			$ban_period = $lang->for." ".$ban_times[$ban['bantime']];

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

			$lifts_on = my_date($mybb->settings['dateformat'], $ban['lifted']);
		}

		if(!$ban['adminuser'])
		{
			if($ban['admin'] == 0)
			{
				$ban['adminuser'] = "MyBB System";
			}
			else
			{
				$ban['adminuser'] = $ban['admin'];
			}
		}

		$table->construct_cell($lang->sprintf($lang->bannedby_x_on_x, $profile_link, htmlspecialchars_uni($ban['adminuser']), $ban_date, $ban_period));
		$table->construct_cell($lifts_on, array("class" => "align_center"));
		$table->construct_cell($time_remaining, array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=user-banning&amp;action=edit&amp;uid={$ban['uid']}\">{$lang->edit}</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=user-banning&amp;action=lift&amp;uid={$ban['uid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_lift_ban}');\">{$lang->lift}</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=user-banning&amp;action=prune&amp;uid={$ban['uid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_prune}');\">{$lang->prune_threads_and_posts}</a>", array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_banned_users, array("colspan" => "6"));
		$table->construct_row();
	}
	$table->output($lang->banned_accounts);
	echo $pagination;

	$form = new Form("index.php?module=user-banning", "post");
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	if($mybb->input['uid'] && !$mybb->input['username'])
	{
		$user = get_user($mybb->input['uid']);
		$mybb->input['username'] = $user['username'];
	}

	$form_container = new FormContainer($lang->ban_a_user);
	$form_container->output_row($lang->ban_username, $lang->autocomplete_enabled, $form->generate_text_box('username', $mybb->input['username'], array('id' => 'username')), 'username');
	$form_container->output_row($lang->ban_reason, "", $form->generate_text_area('reason', $mybb->input['reason'], array('id' => 'reason', 'maxlength' => '255')), 'reason');
	if(count($banned_groups) > 1)
	{
		$form_container->output_row($lang->ban_group, $lang->add_ban_group_desc, $form->generate_select_box('usergroup', $banned_groups, $mybb->input['usergroup'], array('id' => 'usergroup')), 'usergroup');
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
	$form_container->output_row($lang->ban_time, "", $form->generate_select_box('bantime', $length_list, $mybb->input['bantime'], array('id' => 'bantime')), 'bantime');

	$form_container->end();

	// Autocompletion for usernames
	echo '
	<link rel="stylesheet" href="../jscripts/select2/select2.css">
	<script type="text/javascript" src="../jscripts/select2/select2.min.js?ver=1804"></script>
	<script type="text/javascript">
	<!--
	$("#username").select2({
		placeholder: "'.$lang->search_user.'",
		minimumInputLength: 3,
		maximumSelectionSize: 3,
		multiple: false,
		ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
			url: "../xmlhttp.php?action=get_users",
			dataType: \'json\',
			data: function (term, page) {
				return {
					query: term, // search term
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
		},
	});

  	$(\'[for=username]\').click(function(){
		$("#username").select2(\'open\');
		return false;
	});
	// -->
	</script>';

	$buttons[] = $form->generate_submit_button($lang->ban_user);
	$buttons[] = $form->generate_submit_button($lang->search_user, array('name' => 'search'));
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}
