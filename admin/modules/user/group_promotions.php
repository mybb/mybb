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

$page->add_breadcrumb_item($lang->user_group_promotions, "index.php?module=user-group_promotions");

$sub_tabs['usergroup_promotions'] = array(
	'title' => $lang->user_group_promotions,
	'link' => "index.php?module=user-group_promotions",
	'description' => $lang->user_group_promotions_desc
);

$sub_tabs['add_promotion'] = array(
	'title' => $lang->add_new_promotion,
	'link' => "index.php?module=user-group_promotions&amp;action=add",
	'description' => $lang->add_new_promotion_desc
);

$sub_tabs['promotion_logs'] = array(
	'title' => $lang->view_promotion_logs,
	'link' => "index.php?module=user-group_promotions&amp;action=logs",
	'description' => $lang->view_promotion_logs_desc
);

$plugins->run_hooks("admin_user_group_promotions_begin");

if($mybb->input['action'] == "disable")
{
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=user-group_promotions");
	}

	if(!trim($mybb->input['pid']))
	{
		flash_message($lang->error_no_promo_id, 'error');
		admin_redirect("index.php?module=user-group_promotions");
	}

	$query = $db->simple_select("promotions", "*", "pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'");
	$promotion = $db->fetch_array($query);

	if(!$promotion['pid'])
	{
		flash_message($lang->error_invalid_promo_id, 'error');
		admin_redirect("index.php?module=user-group_promotions");
	}

	$plugins->run_hooks("admin_user_group_promotions_disable");

	if($mybb->request_method == "post")
	{
		$update_promotion = array(
			"enabled" => 0
		);

		$plugins->run_hooks("admin_user_group_promotions_disable_commit");

		$db->update_query("promotions", $update_promotion, "pid = '{$promotion['pid']}'");

		// Log admin action
		log_admin_action($promotion['pid'], $promotion['title']);

		flash_message($lang->success_promo_disabled, 'success');
		admin_redirect("index.php?module=user-group_promotions");
	}
	else
	{
		$page->output_confirm_action("index.php?module=user-group_promotions&amp;action=disable&amp;pid={$promotion['pid']}", $lang->confirm_promo_disable);
	}
}

if($mybb->input['action'] == "delete")
{
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=user-group_promotions");
	}

	if(!trim($mybb->input['pid']))
	{
		flash_message($lang->error_no_promo_id, 'error');
		admin_redirect("index.php?module=user-group_promotions");
	}

	$query = $db->simple_select("promotions", "*", "pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'");
	$promotion = $db->fetch_array($query);

	if(!$promotion['pid'])
	{
		flash_message($lang->error_invalid_promo_id, 'error');
		admin_redirect("index.php?module=user-group_promotions");
	}

	$plugins->run_hooks("admin_user_group_promotions_delete");

	if($mybb->request_method == "post")
	{
		$db->delete_query("promotions", "pid = '{$promotion['pid']}'");

		$plugins->run_hooks("admin_user_group_promotions_delete_commit");

		// Log admin action
		log_admin_action($promotion['pid'], $promotion['title']);

		flash_message($lang->success_promo_deleted, 'success');
		admin_redirect("index.php?module=user-group_promotions");
	}
	else
	{
		$page->output_confirm_action("index.php?module=user-group_promotions&amp;action=delete&amp;pid={$mybb->input['pid']}", $lang->confirm_promo_deletion);
	}
}

if($mybb->input['action'] == "enable")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=user-group_promotions");
	}

	if(!trim($mybb->input['pid']))
	{
		flash_message($lang->error_no_promo_id, 'error');
		admin_redirect("index.php?module=user-group_promotions");
	}

	$query = $db->simple_select("promotions", "*", "pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'");
	$promotion = $db->fetch_array($query);

	if(!$promotion['pid'])
	{
		flash_message($lang->error_invalid_promo_id, 'error');
		admin_redirect("index.php?module=user-group_promotions");
	}

	$plugins->run_hooks("admin_user_group_promotions_enable");

	$update_promotion = array(
		"enabled" => 1
	);

	$plugins->run_hooks("admin_user_group_promotions_enable_commit");

	$db->update_query("promotions", $update_promotion, "pid = '{$promotion['pid']}'");

	// Log admin action
	log_admin_action($promotion['pid'], $promotion['title']);

	flash_message($lang->success_promo_enabled, 'success');
	admin_redirect("index.php?module=user-group_promotions");
}

if($mybb->input['action'] == "edit")
{
	if(!trim($mybb->input['pid']))
	{
		flash_message($lang->error_no_promo_id, 'error');
		admin_redirect("index.php?module=user-group_promotions");
	}

	$query = $db->simple_select("promotions", "*", "pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'");
	$promotion = $db->fetch_array($query);

	if(!$promotion)
	{
		flash_message($lang->error_invalid_promo_id, 'error');
		admin_redirect("index.php?module=user-group_promotions");
	}

	$plugins->run_hooks("admin_user_group_promotions_edit");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_no_title;
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = $lang->error_no_desc;
		}

		if(empty($mybb->input['requirements']))
		{
			$errors[] = $lang->error_no_requirements;
		}

		if(empty($mybb->input['originalusergroup']))
		{
			$errors[] = $lang->error_no_orig_usergroup;
		}

		if(!trim($mybb->input['newusergroup']))
		{
			$errors[] = $lang->error_no_new_usergroup;
		}

		if(!trim($mybb->input['usergroupchangetype']))
		{
			$errors[] = $lang->error_no_usergroup_change_type;
		}

		if(!$errors)
		{
			if(in_array('*', $mybb->input['originalusergroup']))
			{
				$mybb->input['originalusergroup'] = '*';
			}
			else
			{
				$mybb->input['originalusergroup'] = implode(',', array_map('intval', $mybb->input['originalusergroup']));
			}

			$allowed_operators = array('>', '>=', '=', '<=', '<');
			$operator_fields = array('posttype', 'threadtype', 'reputationtype', 'referralstype', 'warningstype');

			foreach($operator_fields as $field)
			{
				if(!in_array($mybb->get_input($field), $allowed_operators))
				{
					$mybb->input[$field] = '=';
				}
			}

			$allowed_times = array('hours', 'days', 'weeks', 'months', 'years');
			$time_fields = array('timeregisteredtype', 'timeonlinetype');

			foreach($time_fields as $field)
			{
				if(!in_array($mybb->get_input($field), $allowed_times))
				{
					$mybb->input[$field] = 'days';
				}
			}

			$update_promotion = array(
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"posts" => $mybb->get_input('postcount', MyBB::INPUT_INT),
				"posttype" => $db->escape_string($mybb->input['posttype']),
				"threads" => $mybb->get_input('threadcount', MyBB::INPUT_INT),
				"threadtype" => $db->escape_string($mybb->input['threadtype']),
				"registered" => $mybb->get_input('timeregistered', MyBB::INPUT_INT),
				"registeredtype" => $db->escape_string($mybb->input['timeregisteredtype']),
				"online" => $mybb->get_input('timeonline', MyBB::INPUT_INT),
				"onlinetype" => $db->escape_string($mybb->input['timeonlinetype']),
				"reputations" => $mybb->get_input('reputationcount', MyBB::INPUT_INT),
				"reputationtype" => $db->escape_string($mybb->input['reputationtype']),
				"referrals" => $mybb->get_input('referrals', MyBB::INPUT_INT),
				"referralstype" => $db->escape_string($mybb->input['referralstype']),
				"warnings" => $mybb->get_input('warnings', MyBB::INPUT_INT),
				"warningstype" => $db->escape_string($mybb->input['warningstype']),
				"requirements" => $db->escape_string(implode(",", $mybb->input['requirements'])),
				"originalusergroup" => $db->escape_string($mybb->input['originalusergroup']),
				"newusergroup" => $mybb->get_input('newusergroup', MyBB::INPUT_INT),
				"usergrouptype" => $db->escape_string($mybb->input['usergroupchangetype']),
				"enabled" => $mybb->get_input('enabled', MyBB::INPUT_INT),
				"logging" => $mybb->get_input('logging', MyBB::INPUT_INT)
			);

			$plugins->run_hooks("admin_user_group_promotions_edit_commit");

			$db->update_query("promotions", $update_promotion, "pid = '{$promotion['pid']}'");

			// Log admin action
			log_admin_action($promotion['pid'], $mybb->input['title']);

			flash_message($lang->success_promo_updated, 'success');
			admin_redirect("index.php?module=user-group_promotions");
		}
	}

	$page->add_breadcrumb_item($lang->edit_promotion);
	$page->output_header($lang->user_group_promotions." - ".$lang->edit_promotion);

	$sub_tabs = array();
	$sub_tabs['edit_promotion'] = array(
		'title' => $lang->edit_promotion,
		'link' => "index.php?module=user-group_promotions&amp;action=edit",
		'description' => $lang->edit_promotion_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_promotion');
	$form = new Form("index.php?module=user-group_promotions&amp;action=edit", "post", "edit");
	echo $form->generate_hidden_field("pid", $promotion['pid']);
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['title'] = $promotion['title'];
		$mybb->input['description'] = $promotion['description'];
		$mybb->input['requirements'] = explode(',', $promotion['requirements']);
		$mybb->input['reputationcount'] = $promotion['reputations'];
		$mybb->input['reputationtype'] = $promotion['reputationtype'];
		$mybb->input['postcount'] = $promotion['posts'];
		$mybb->input['posttype'] = $promotion['posttype'];
		$mybb->input['threadcount'] = $promotion['threads'];
		$mybb->input['threadtype'] = $promotion['threadtype'];
		$mybb->input['referrals'] = $promotion['referrals'];
		$mybb->input['referralstype'] = $promotion['referralstype'];
		$mybb->input['warnings'] = $promotion['warnings'];
		$mybb->input['warningstype'] = $promotion['warningstype'];
		$mybb->input['timeregistered'] = $promotion['registered'];
		$mybb->input['timeregisteredtype'] = $promotion['registeredtype'];
		$mybb->input['timeonline'] = $promotion['online'];
		$mybb->input['timeonlinetype'] = $promotion['onlinetype'];
		$mybb->input['originalusergroup'] = explode(',', $promotion['originalusergroup']);
		$mybb->input['usergroupchangetype'] = $promotion['usergrouptype'];
		$mybb->input['newusergroup'] = $promotion['newusergroup'];
		$mybb->input['enabled'] = $promotion['enabled'];
		$mybb->input['logging'] = $promotion['logging'];
	}

	$form_container = new FormContainer($lang->edit_promotion);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_desc." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');

	$options = array(
		"postcount" => $lang->post_count,
		"threadcount" => $lang->thread_count,
		"reputation" => $lang->reputation,
		"referrals" => $lang->referrals,
		"warnings" => $lang->warning_points,
		"timeregistered" => $lang->time_registered,
		"timeonline" => $lang->time_online
	);

	$form_container->output_row($lang->promo_requirements." <em>*</em>", $lang->promo_requirements_desc, $form->generate_select_box('requirements[]', $options, $mybb->input['requirements'], array('id' => 'requirements', 'multiple' => true, 'size' => 5)), 'requirements');

	$options_type = array(
		">" => $lang->greater_than,
		">=" => $lang->greater_than_or_equal_to,
		"=" => $lang->equal_to,
		"<=" => $lang->less_than_or_equal_to,
		"<" => $lang->less_than
	);

	$form_container->output_row($lang->post_count, $lang->post_count_desc, $form->generate_numeric_field('postcount', $mybb->input['postcount'], array('id' => 'postcount', 'min' => 0))." ".$form->generate_select_box("posttype", $options_type, $mybb->input['posttype'], array('id' => 'posttype')), 'postcount');

	$form_container->output_row($lang->thread_count, $lang->thread_count_desc, $form->generate_numeric_field('threadcount', $mybb->input['threadcount'], array('id' => 'threadcount', 'min' => 0))." ".$form->generate_select_box("threadtype", $options_type, $mybb->input['threadtype'], array('id' => 'threadtype')), 'threadcount');

	$form_container->output_row($lang->reputation_count, $lang->reputation_count_desc, $form->generate_numeric_field('reputationcount', $mybb->input['reputationcount'], array('id' => 'reputationcount', 'min' => 0))." ".$form->generate_select_box("reputationtype", $options_type, $mybb->input['reputationtype'], array('id' => 'reputationtype')), 'reputationcount');

	$options = array(
		"hours" => $lang->hours,
		"days" => $lang->days,
		"weeks" => $lang->weeks,
		"months" => $lang->months,
		"years" => $lang->years
	);

	$form_container->output_row($lang->referral_count, $lang->referral_count_desc, $form->generate_numeric_field('referrals', $mybb->input['referrals'], array('id' => 'referrals', 'min' => 0))." ".$form->generate_select_box("referralstype", $options_type, $mybb->input['referralstype'], array('id' => 'referralstype')), 'referrals');

	$form_container->output_row($lang->warning_points, $lang->warning_points_desc, $form->generate_numeric_field('warnings', $mybb->input['warnings'], array('id' => 'warnings', 'min' => 0))." ".$form->generate_select_box("warningstype", $options_type, $mybb->input['warningstype'], array('id' => 'warningstype')), 'warnings');

	$form_container->output_row($lang->time_registered, $lang->time_registered_desc, $form->generate_numeric_field('timeregistered', $mybb->input['timeregistered'], array('id' => 'timeregistered', 'min' => 0))." ".$form->generate_select_box("timeregisteredtype", $options, $mybb->input['timeregisteredtype'], array('id' => 'timeregisteredtype')), 'timeregistered');

	$form_container->output_row($lang->time_online, $lang->time_online_desc, $form->generate_numeric_field('timeonline', $mybb->input['timeonline'], array('id' => 'timeonline', 'min' => 0))." ".$form->generate_select_box("timeonlinetype", $options, $mybb->input['timeonlinetype'], array('id' => 'timeonlinetype')), 'timeonline');

	$options = array();

	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
	while($usergroup = $db->fetch_array($query))
	{
		$options[(int)$usergroup['gid']] = htmlspecialchars_uni($usergroup['title']);
	}

	$form_container->output_row($lang->orig_user_group." <em>*</em>", $lang->orig_user_group_desc, $form->generate_select_box('originalusergroup[]', $options, $mybb->input['originalusergroup'], array('id' => 'originalusergroup', 'multiple' => true, 'size' => 5)), 'originalusergroup');

	unset($options['*']); // Remove the all usergroups option
	$form_container->output_row($lang->new_user_group." <em>*</em>", $lang->new_user_group_desc, $form->generate_select_box('newusergroup', $options, $mybb->input['newusergroup'], array('id' => 'newusergroup')), 'newusergroup');

	$options = array(
		'primary' => $lang->primary_user_group,
		'secondary' => $lang->secondary_user_group
	);

	$form_container->output_row($lang->user_group_change_type." <em>*</em>", $lang->user_group_change_type_desc, $form->generate_select_box('usergroupchangetype', $options, $mybb->input['usergroupchangetype'], array('id' => 'usergroupchangetype')), 'usergroupchangetype');

	$form_container->output_row($lang->enabled." <em>*</em>", "", $form->generate_yes_no_radio("enabled", $mybb->input['enabled'], true));

	$form_container->output_row($lang->enable_logging." <em>*</em>", "", $form->generate_yes_no_radio("logging", $mybb->input['logging'], true));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->update_promotion);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_user_group_promotions_add");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_no_title;
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = $lang->error_no_desc;
		}

		if(empty($mybb->input['requirements']))
		{
			$errors[] = $lang->error_no_requirements;
		}

		if(empty($mybb->input['originalusergroup']))
		{
			$errors[] = $lang->error_no_orig_usergroup;
		}

		if(!trim($mybb->input['newusergroup']))
		{
			$errors[] = $lang->error_no_new_usergroup;
		}

		if(!trim($mybb->input['usergroupchangetype']))
		{
			$errors[] = $lang->error_no_usergroup_change_type;
		}

		if(!$errors)
		{
			if(in_array('*', $mybb->input['originalusergroup']))
			{
				$mybb->input['originalusergroup'] = '*';
			}
			else
			{
				$mybb->input['originalusergroup'] = implode(',', array_map('intval', $mybb->input['originalusergroup']));
			}

			$allowed_operators = array('>', '>=', '=', '<=', '<');
			$operator_fields = array('posttype', 'threadtype', 'reputationtype', 'referralstype', 'warningstype');

			foreach($operator_fields as $field)
			{
				if(!in_array($mybb->get_input($field), $allowed_operators))
				{
					$mybb->input[$field] = '=';
				}
			}

			$allowed_times = array('hours', 'days', 'weeks', 'months', 'years');
			$time_fields = array('timeregisteredtype', 'timeonlinetype');

			foreach($time_fields as $field)
			{
				if(!in_array($mybb->get_input($field), $allowed_times))
				{
					$mybb->input[$field] = 'days';
				}
			}

			$new_promotion = array(
				"title" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"posts" => $mybb->get_input('postcount', MyBB::INPUT_INT),
				"posttype" => $db->escape_string($mybb->input['posttype']),
				"threads" => $mybb->get_input('threadcount', MyBB::INPUT_INT),
				"threadtype" => $db->escape_string($mybb->input['threadtype']),
				"registered" => $mybb->get_input('timeregistered', MyBB::INPUT_INT),
				"registeredtype" => $db->escape_string($mybb->input['timeregisteredtype']),
				"online" => $mybb->get_input('timeonline', MyBB::INPUT_INT),
				"onlinetype" => $db->escape_string($mybb->input['timeonlinetype']),
				"reputations" => $mybb->get_input('reputationcount', MyBB::INPUT_INT),
				"reputationtype" => $db->escape_string($mybb->input['reputationtype']),
				"referrals" => $mybb->get_input('referrals', MyBB::INPUT_INT),
				"referralstype" => $db->escape_string($mybb->input['referralstype']),
				"warnings" => $mybb->get_input('warnings', MyBB::INPUT_INT),
				"warningstype" => $db->escape_string($mybb->input['warningstype']),
				"requirements" => $db->escape_string(implode(",", $mybb->input['requirements'])),
				"originalusergroup" => $db->escape_string($mybb->input['originalusergroup']),
				"usergrouptype" => $db->escape_string($mybb->input['usergroupchangetype']),
				"newusergroup" => $mybb->get_input('newusergroup', MyBB::INPUT_INT),
				"enabled" => $mybb->get_input('enabled', MyBB::INPUT_INT),
				"logging" => $mybb->get_input('logging', MyBB::INPUT_INT)
			);

			$pid = $db->insert_query("promotions", $new_promotion);

			$plugins->run_hooks("admin_user_group_promotions_add_commit");

			// Log admin action
			log_admin_action($pid, $mybb->input['title']);

			flash_message($lang->success_promo_added, 'success');
			admin_redirect("index.php?module=user-group_promotions");
		}
	}
	$page->add_breadcrumb_item($lang->add_new_promotion);
	$page->output_header($lang->user_group_promotions." - ".$lang->add_new_promotion);

	$sub_tabs['usergroup_promotions'] = array(
		'title' => $lang->user_group_promotions,
		'link' => "index.php?module=user-group_promotions"
	);

	$sub_tabs['add_promotion'] = array(
		'title' => $lang->add_new_promotion,
		'link' => "index.php?module=user-group_promotions&amp;action=add",
		'description' => $lang->add_new_promotion_desc
	);

	$sub_tabs['promotion_logs'] = array(
		'title' => $lang->view_promotion_logs,
		'link' => "index.php?module=user-group_promotions&amp;action=logs"
	);

	$page->output_nav_tabs($sub_tabs, 'add_promotion');
	$form = new Form("index.php?module=user-group_promotions&amp;action=add", "post", "add");
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['reputationcount'] = '0';
		$mybb->input['referrals'] = '0';
		$mybb->input['warnings'] = '0';
		$mybb->input['postcount'] = '0';
		$mybb->input['threadcount'] = '0';
		$mybb->input['timeregistered'] = '0';
		$mybb->input['timeregisteredtype'] = 'days';
		$mybb->input['timeonline'] = '0';
		$mybb->input['timeonlinetype'] = 'days';
		$mybb->input['originalusergroup'] = '*';
		$mybb->input['newusergroup'] = '2';
		$mybb->input['enabled'] = '1';
		$mybb->input['logging'] = '1';
	}
	$form_container = new FormContainer($lang->add_new_promotion);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $mybb->get_input('title'), array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_desc." <em>*</em>", "", $form->generate_text_box('description', $mybb->get_input('description'), array('id' => 'description')), 'description');

	$options = array(
		"postcount" => $lang->post_count,
		"threadcount" => $lang->thread_count,
		"reputation" => $lang->reputation,
		"referrals" => $lang->referrals,
		"warnings" => $lang->warning_points,
		"timeregistered" => $lang->time_registered,
		"timeonline" => $lang->time_online
	);

	$form_container->output_row($lang->promo_requirements." <em>*</em>", $lang->promo_requirements_desc, $form->generate_select_box('requirements[]', $options, $mybb->get_input('requirements', MyBB::INPUT_ARRAY), array('id' => 'requirements', 'multiple' => true, 'size' => 5)), 'requirements');

	$options_type = array(
		">" => $lang->greater_than,
		">=" => $lang->greater_than_or_equal_to,
		"=" => $lang->equal_to,
		"<=" => $lang->less_than_or_equal_to,
		"<" => $lang->less_than
	);

	$form_container->output_row($lang->post_count, $lang->post_count_desc, $form->generate_numeric_field('postcount', $mybb->get_input('postcount'), array('id' => 'postcount', 'min' => 0))." ".$form->generate_select_box("posttype", $options_type, $mybb->get_input('posttype'), array('id' => 'posttype')), 'postcount');

	$form_container->output_row($lang->thread_count, $lang->thread_count_desc, $form->generate_numeric_field('threadcount', $mybb->get_input('threadcount'), array('id' => 'threadcount', 'min' => 0))." ".$form->generate_select_box("threadtype", $options_type, $mybb->get_input('threadtype'), array('id' => 'threadtype')), 'threadcount');

	$form_container->output_row($lang->reputation_count, $lang->reputation_count_desc, $form->generate_numeric_field('reputationcount', $mybb->get_input('reputationcount'), array('id' => 'reputationcount', 'min' => 0))." ".$form->generate_select_box("reputationtype", $options_type, $mybb->get_input('reputationtype'), array('id' => 'reputationtype')), 'reputationcount');

	$options = array(
		"hours" => $lang->hours,
		"days" => $lang->days,
		"weeks" => $lang->weeks,
		"months" => $lang->months,
		"years" => $lang->years
	);

	$form_container->output_row($lang->referral_count, $lang->referral_count_desc, $form->generate_numeric_field('referrals', $mybb->get_input('referrals'), array('id' => 'referrals', 'min' => 0))." ".$form->generate_select_box("referralstype", $options_type, $mybb->get_input('referralstype'), array('id' => 'referralstype')), 'referrals');

	$form_container->output_row($lang->warning_points, $lang->warning_points_desc, $form->generate_numeric_field('warnings', $mybb->get_input('warnings'), array('id' => 'warnings', 'min' => 0))." ".$form->generate_select_box("warningstype", $options_type, $mybb->get_input('warningstype'), array('id' => 'warningstype')), 'warnings');

	$form_container->output_row($lang->time_registered, $lang->time_registered_desc, $form->generate_numeric_field('timeregistered', $mybb->get_input('timeregistered'), array('id' => 'timeregistered', 'min' => 0))." ".$form->generate_select_box("timeregisteredtype", $options, $mybb->get_input('timeregisteredtype'), array('id' => 'timeregisteredtype')), 'timeregistered');

	$form_container->output_row($lang->time_online, $lang->time_online_desc, $form->generate_numeric_field('timeonline', $mybb->get_input('timeonline'), array('id' => 'timeonline', 'min' => 0))." ".$form->generate_select_box("timeonlinetype", $options, $mybb->get_input('timeonlinetype'), array('id' => 'timeonlinetype')), 'timeonline');
	$options = array();

	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
	while($usergroup = $db->fetch_array($query))
	{
		$options[(int)$usergroup['gid']] = htmlspecialchars_uni($usergroup['title']);
	}

	$form_container->output_row($lang->orig_user_group." <em>*</em>", $lang->orig_user_group_desc, $form->generate_select_box('originalusergroup[]', $options, $mybb->get_input('originalusergroup', MyBB::INPUT_ARRAY), array('id' => 'originalusergroup', 'multiple' => true, 'size' => 5)), 'originalusergroup');

	unset($options['*']);
	$form_container->output_row($lang->new_user_group." <em>*</em>", $lang->new_user_group_desc, $form->generate_select_box('newusergroup', $options, $mybb->get_input('newusergroup'), array('id' => 'newusergroup')), 'newusergroup');

	$options = array(
		'primary' => $lang->primary_user_group,
		'secondary' => $lang->secondary_user_group
	);

	$form_container->output_row($lang->user_group_change_type." <em>*</em>", $lang->user_group_change_type_desc, $form->generate_select_box('usergroupchangetype', $options, $mybb->get_input('usergroupchangetype'), array('id' => 'usergroupchangetype')), 'usergroupchangetype');

	$form_container->output_row($lang->enabled." <em>*</em>", "", $form->generate_yes_no_radio("enabled", $mybb->get_input('enabled'), true));

	$form_container->output_row($lang->enable_logging." <em>*</em>", "", $form->generate_yes_no_radio("logging", $mybb->get_input('logging'), true));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->update_promotion);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "logs")
{
	$plugins->run_hooks("admin_user_group_promotions_logs");

	$query = $db->simple_select("promotionlogs", "COUNT(plid) as promotionlogs");
	$total_rows = $db->fetch_field($query, "promotionlogs");

	if($mybb->get_input('page', MyBB::INPUT_INT) > 1)
	{
		$mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);
		$start = ($mybb->input['page']*20)-20;
		$pages = ceil($total_rows / 20);
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

	$page->add_breadcrumb_item($lang->promotion_logs);
	$page->output_header($lang->user_group_promotions." - ".$lang->promotion_logs);

	$page->output_nav_tabs($sub_tabs, 'promotion_logs');

	$table = new Table;
	$table->construct_header($lang->promoted_user, array("class" => "align_center", "width" => '20%'));
	$table->construct_header($lang->user_group_change_type, array("class" => "align_center", "width" => '20%'));
	$table->construct_header($lang->orig_user_group, array("class" => "align_center", "width" => '20%'));
	$table->construct_header($lang->new_user_group, array("class" => "align_center", "width" => '20%'));
	$table->construct_header($lang->time_promoted, array("class" => "align_center", "width" => '20%'));

	$query = $db->query("
		SELECT pl.*,u.username
		FROM ".TABLE_PREFIX."promotionlogs pl
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pl.uid)
		ORDER BY dateline DESC
		LIMIT {$start}, 20
	");
	while($log = $db->fetch_array($query))
	{
		$log['username'] = "<a href=\"index.php?module=user-view&amp;action=edit&amp;uid={$log['uid']}\">".htmlspecialchars_uni($log['username'])."</a>";

		if($log['type'] == "secondary" || (!empty($log['oldusergroup']) && strstr(",", $log['oldusergroup'])))
		{
			$log['oldusergroup'] = "<i>".$lang->multiple_usergroups."</i>";
			$log['newusergroup'] = htmlspecialchars_uni($groupscache[$log['newusergroup']]['title']);
		}
		else
		{
			$log['oldusergroup'] = htmlspecialchars_uni($groupscache[$log['oldusergroup']]['title']);
			$log['newusergroup'] = htmlspecialchars_uni($groupscache[$log['newusergroup']]['title']);
		}

		if($log['type'] == "secondary")
		{
			$log['type'] = $lang->secondary;
		}
		else
		{
			$log['type'] = $lang->primary;
		}

		$log['dateline'] = my_date('relative', $log['dateline']);
		$table->construct_cell($log['username']);
		$table->construct_cell($log['type'], array('style' => 'text-align: center;'));
		$table->construct_cell($log['oldusergroup'], array('style' => 'text-align: center;'));
		$table->construct_cell($log['newusergroup'], array('style' => 'text-align: center;'));
		$table->construct_cell($log['dateline'], array('style' => 'text-align: center;'));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_promotion_logs, array("colspan" => "5"));
		$table->construct_row();
	}

	$table->output($lang->promotion_logs);

	echo "<br />".draw_admin_pagination($mybb->input['page'], "20", $total_rows, "index.php?module=user-group_promotions&amp;action=logs&amp;page={page}");

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_user_group_promotions_start");

	$page->output_header($lang->promotion_manager);

	$page->output_nav_tabs($sub_tabs, 'usergroup_promotions');

	$table = new Table;
	$table->construct_header($lang->promotion);
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

	$query = $db->simple_select("promotions", "*", "", array("order_by" => "title", "order_dir" => "asc"));
	while($promotion = $db->fetch_array($query))
	{
		$promotion['title'] = htmlspecialchars_uni($promotion['title']);
		$promotion['description'] = htmlspecialchars_uni($promotion['description']);
		if($promotion['enabled'] == 1)
		{
			$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_on.png\" alt=\"({$lang->alt_enabled})\" title=\"{$lang->alt_enabled}\"  style=\"vertical-align: middle;\" /> ";
		}
		else
		{
			$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_off.png\" alt=\"({$lang->alt_disabled})\" title=\"{$lang->alt_disabled}\"  style=\"vertical-align: middle;\" /> ";
		}

		$table->construct_cell("<div>{$icon}<strong><a href=\"index.php?module=user-group_promotions&amp;action=edit&amp;pid={$promotion['pid']}\">{$promotion['title']}</a></strong><br /><small>{$promotion['description']}</small></div>");

		$popup = new PopupMenu("promotion_{$promotion['pid']}", $lang->options);
		$popup->add_item($lang->edit_promotion, "index.php?module=user-group_promotions&amp;action=edit&amp;pid={$promotion['pid']}");
		if($promotion['enabled'] == 1)
		{
			$popup->add_item($lang->disable_promotion, "index.php?module=user-group_promotions&amp;action=disable&amp;pid={$promotion['pid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_promo_disable}')");
		}
		else
		{
			$popup->add_item($lang->enable_promotion, "index.php?module=user-group_promotions&amp;action=enable&amp;pid={$promotion['pid']}&amp;my_post_key={$mybb->post_code}");
		}
		$popup->add_item($lang->delete_promotion, "index.php?module=user-group_promotions&amp;action=delete&amp;pid={$promotion['pid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_promo_deletion}')");
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_promotions_set, array("colspan" => "2"));
		$table->construct_row();
	}

	$table->output($lang->user_group_promotions);

	$page->output_footer();
}
