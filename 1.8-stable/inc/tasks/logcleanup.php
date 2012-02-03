<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: logcleanup.php 5297 2010-12-28 22:01:14Z Tomm $
 */

function task_logcleanup($task)
{
	global $mybb, $db, $lang;

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
	
	add_task_log($task, $lang->task_logcleanup_ran);
}
?>