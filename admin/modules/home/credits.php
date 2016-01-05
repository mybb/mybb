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

$page->add_breadcrumb_item($lang->mybb_credits, "index.php?module=home-credits");

$plugins->run_hooks("admin_home_credits_begin");

if(!$mybb->input['action'])
{
	$page->output_header($lang->mybb_credits);

	$sub_tabs['credits'] = array(
		'title' => $lang->mybb_credits,
		'link' => "index.php?module=home-credits",
		'description' => $lang->mybb_credits_description
	);

	$sub_tabs['credits_about'] = array(
		'title' => $lang->about_the_team,
		'link' => "http://www.mybb.com/about/team",
		'link_target' => "_blank",
	);

	$sub_tabs['check_for_updates'] = array(
		'title' => $lang->check_for_updates,
		'link' => "index.php?module=home-credits&amp;fetch_new=1",
	);

	$plugins->run_hooks("admin_home_credits_start");

	$page->output_nav_tabs($sub_tabs, 'credits');

	$mybb_credits = $cache->read('mybb_credits');

	if($mybb->get_input('fetch_new', MyBB::INPUT_INT) == 1 || $mybb->get_input('fetch_new', MyBB::INPUT_INT) == -2 || ($mybb->get_input('fetch_new', MyBB::INPUT_INT) != -1 && (!is_array($mybb_credits) || $mybb_credits['last_check'] <= TIME_NOW - 60*60*24*14)))
	{
		$new_mybb_credits = array(
			'last_check' => TIME_NOW
		);

		require_once MYBB_ROOT."inc/class_xml.php";
		$contents = fetch_remote_file("http://www.mybb.com/mybb_team.xml");

		if(!$contents)
		{
			flash_message($lang->error_communication, 'error');
			if($mybb->get_input('fetch_new', MyBB::INPUT_INT) == -2)
			{
				admin_redirect('index.php?module=tools-cache');
			}
			admin_redirect('index.php?module=home-credits&amp;fetch_new=-1');
		}

		$parser = new XMLParser($contents);
		$tree = $parser->get_tree();
		$mybbgroup = array();
		foreach($tree['mybbgroup']['team'] as $team)
		{
			$members = array();
			foreach($team['member'] as $member)
			{
				$members[] = array(
					'name' => htmlspecialchars_uni($member['name']['value']),
					'username' => htmlspecialchars_uni($member['username']['value']),
					'profile' => htmlspecialchars_uni($member['profile']['value']),
					'lead' => (bool)$member['attributes']['lead'] or false
				);
			}
			$mybbgroup[] = array(
				'title' => htmlspecialchars_uni($team['attributes']['title']),
				'members' => $members
			);
		}
		$new_mybb_credits['credits'] = $mybbgroup;

		$cache->update('mybb_credits', $new_mybb_credits);

		if($mybb->get_input('fetch_new', MyBB::INPUT_INT) == -2)
		{
			$lang->load('tools_cache');
			flash_message($lang->success_cache_reloaded, 'success');
			admin_redirect('index.php?module=tools-cache');
		}
		else
		{
			flash_message($lang->success_credits_updated, 'success');
			admin_redirect('index.php?module=home-credits&amp;fetch_new=-1');
		}
	}

	if(empty($mybb_credits) || (is_array($mybb_credits) && empty($mybb_credits['credits'])))
	{
		$table = new Table;
		$table->construct_cell($lang->no_credits);
		$table->construct_row();
	}
	else
	{
		$largest_count = $i = 0;
		$team_max = array();
		foreach($mybb_credits['credits'] as $team)
		{
			$count = count($team['members']);
			$team_max[$i++] = $count;
			if($largest_count < $count)
			{
				$largest_count = $count;
			}
		}
		$largest_count -= 1;

		$table = new Table;
		foreach($mybb_credits['credits'] as $team)
		{
			$table->construct_header($team['title'], array('width' => '16%'));
		}

		for($i = 0; $i <= $largest_count; $i++)
		{
			foreach($team_max as $team => $max)
			{
				if($max < $i)
				{
					$table->construct_cell("&nbsp;");
				}
				else
				{
					$table->construct_cell("<a href=\"{$mybb_credits['credits'][$team]['members'][$i]['profile']}\" title=\"{$mybb_credits['credits'][$team]['members'][$i]['username']}\" target=\"_blank\">{$mybb_credits['credits'][$team]['members'][$i]['name']}</a>");
				}
			}
			$table->construct_row();
		}
	}

	$table->output($lang->mybb_credits);

	$page->output_footer();
}

