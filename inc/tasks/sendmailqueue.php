<?php
/**
 * MyBB 1.8
 *
 * Copyright 2020 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

function task_sendmailqueue($task)
{
	global $mybb, $lang;

	$num_to_send = max(1, (int) $mybb->settings['mail_queue_limit']);

	send_mail_queue($num_to_send);

	add_task_log($task, $lang->sprintf($lang->task_sendmailqueue_ran, $num_to_send));
}