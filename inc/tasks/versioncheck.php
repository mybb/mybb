<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

function task_versioncheck($task)
{
	global $cache, $lang, $mybb;

	require_once MYBB_ROOT.'inc/src/Maintenance/functions_version.php';

	$current_version = rawurlencode($mybb->version_code);

	$updated_cache = array(
		'last_check' => TIME_NOW
	);

	// Check for the latest version
	$contents = \MyBB\Maintenance\fetchLatestVersionDetails();

	if(!$contents)
	{
		add_task_log($task, $lang->task_versioncheck_ran_errors);
		return false;
	}

	$latest_code = (int)$contents['version_code'];
	$latest_version = "<strong>".htmlspecialchars_uni($contents['latest_version'])."</strong> (".$latest_code.")";
	if($latest_code > $mybb->version_code)
	{
		$latest_version = "<span style=\"color: #C00;\">".$latest_version."</span>";
		$version_warn = 1;
		$updated_cache['latest_version'] = $latest_version;
		$updated_cache['latest_version_code'] = $latest_code;
	}
	else
	{
		$latest_version = "<span style=\"color: green;\">".$latest_version."</span>";
	}

	// Check for latest development information
	if(\MyBB\Maintenance\hasDevelopmentArtifacts())
	{
		$branch_name = \MyBB\Maintenance\getDevelopmentBranchName($mybb->version);

		$details = \MyBB\Maintenance\fetchDevelopmentBranchDetails($branch_name);

		if($details !== null)
		{
			$updated_cache['repository'][$branch_name] = $details;
		}
	}

	// Check for the latest news
	$news = \MyBB\Maintenance\fetchLatestNews();

	if($news !== null)
	{
		$updated_cache['news'] = $news;
	}

	$cache->update("update_check", $updated_cache);
	add_task_log($task, $lang->task_versioncheck_ran);
}
