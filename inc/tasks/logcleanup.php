<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

function task_logcleanup($task)
{
	global $mybb, $db, $lang, $plugins;

	// Clear out old admin logs
	if($mybb->config['log_pruning']['admin_logs'] > 0)
	{
		$cut = TIME_NOW-60*60*24*$mybb->config['log_pruning']['admin_logs'];
		$db->delete_query("adminlog", "dateline<'{$cut}'");
	}

	// Clear out old moderator logs
	if($mybb->config['log_pruning']['mod_logs'] > 0)
	{
		$cut = TIME_NOW-60*60*24*$mybb->config['log_pruning']['mod_logs'];
		$db->delete_query("moderatorlog", "dateline<'{$cut}'");
	}

	// Clear out old task logs
	if($mybb->config['log_pruning']['task_logs'] > 0)
	{
		$cut = TIME_NOW-60*60*24*$mybb->config['log_pruning']['task_logs'];
		$db->delete_query("tasklog", "dateline<'{$cut}'");
	}

	// Clear out old mail error logs
	if($mybb->config['log_pruning']['mail_logs'] > 0)
	{
		$cut = TIME_NOW-60*60*24*$mybb->config['log_pruning']['mail_logs'];
		$db->delete_query("mailerrors", "dateline<'{$cut}'");
	}

	// Clear out old user mail logs
	if($mybb->config['log_pruning']['user_mail_logs'] > 0)
	{
		$cut = TIME_NOW-60*60*24*$mybb->config['log_pruning']['user_mail_logs'];
		$db->delete_query("maillogs", "dateline<'{$cut}'");
	}

	// Clear out old promotion logs
	if($mybb->config['log_pruning']['promotion_logs'] > 0)
	{
		$cut = TIME_NOW-60*60*24*$mybb->config['log_pruning']['promotion_logs'];
		$db->delete_query("promotionlogs", "dateline<'{$cut}'");
	}

	if(is_object($plugins))
	{
		$plugins->run_hooks('task_logcleanup', $task);
	}

	add_task_log($task, $lang->task_logcleanup_ran);
}
