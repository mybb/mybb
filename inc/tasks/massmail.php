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

require_once MYBB_ROOT."/inc/functions_massmail.php";
require_once MYBB_ROOT."inc/datahandlers/pm.php";

function task_massmail($task)
{
	global $db, $mybb, $lang, $plugins;

	$query = $db->simple_select("massemails", "*", "senddate <= '".TIME_NOW."' AND status IN (1,2)");
	while($mass_email = $db->fetch_array($query))
	{
		if(is_object($plugins))
		{
			$args = array(
				'task' => &$task,
				'mass_email' => &$mass_email
			);
			$plugins->run_hooks('task_massmail', $args);
		}

		if($mass_email['status'] == 1)
		{
			$db->update_query("massemails", array('status' => 2), "mid='{$mass_email['mid']}'");
		}

		$sentcount = 0;

		if(!$mass_email['perpage'])
		{
			$mass_email['perpage'] = 50;
		}

		if(strpos($mass_email['htmlmessage'], '<br />') === false && strpos($mass_email['htmlmessage'], '<br>') === false)
		{
			$mass_email['htmlmessage'] = nl2br($mass_email['htmlmessage']);
		}

		$mass_email['orig_message'] = $mass_email['message'];
		$mass_email['orig_htmlmessage'] = $mass_email['htmlmessage'];

		// Need to perform the search to fetch the number of users we're emailing
		$member_query = build_mass_mail_query(my_unserialize($mass_email['conditions']));

		$count_query = $db->simple_select("users u", "COUNT(uid) AS num", $member_query);
		$mass_email['totalcount'] = $db->fetch_field($count_query, "num");

		$query2 = $db->simple_select("users u", "u.uid, u.language, u.pmnotify, u.lastactive, u.username, u.email", $member_query, array('limit_start' => $mass_email['sentcount'], 'limit' => $mass_email['perpage'], 'order_by' => 'u.uid', 'order_dir' => 'asc'));
		while($user = $db->fetch_array($query2))
		{
			$replacement_fields = array(
				"{uid}" => $user['uid'],
				"{username}" => $user['username'],
				"{email}" => $user['email'],
				"{bbname}" => $mybb->settings['bbname'],
				"{bburl}" => $mybb->settings['bburl'],
				"[".$lang->massmail_username."]" => $user['username'],
				"[".$lang->email_addr."]" => $user['email'],
				"[".$lang->board_name."]" => $mybb->settings['bbname'],
				"[".$lang->board_url."]" => $mybb->settings['bburl']
			);

			foreach($replacement_fields as $find => $replace)
			{
				$mass_email['message'] = str_replace($find, $replace, $mass_email['message']);
				$mass_email['htmlmessage'] = str_replace($find, $replace, $mass_email['htmlmessage']);
			}

			// Private Message
			if($mass_email['type'] == 1)
			{
				$pm_handler = new PMDataHandler();
				$pm_handler->admin_override = true;

				$pm = array(
					"subject" => $mass_email['subject'],
					"message" => $mass_email['message'],
					"fromid" => $mass_email['uid'],
					"options" => array("savecopy" => 0),
				);

				$pm['to'] = explode(",", $user['username']);
				$pm_handler->set_data($pm);
				if(!$pm_handler->validate_pm())
				{
					$friendly_errors = implode('\n', $pm_handler->get_friendly_errors());
					add_task_log($task, $lang->sprintf($lang->task_massmail_ran_errors, htmlspecialchars_uni($user['username']), $friendly_errors));
					$friendly_errors = "";
				}
				else
				{
					$pm_handler->insert_pm();
				}
			}
			// Normal Email
			else
			{
				switch($mass_email['format'])
				{
					case 2:
						$format = "both";
						$text_message = $mass_email['message'];
						$mass_email['message'] = $mass_email['htmlmessage'];
						break;
					case 1:
						$format = "html";
						$text_message = "";
						$mass_email['message'] = $mass_email['htmlmessage'];
						break;
					default:
						$format = "text";
						$text_message = "";
				}
				my_mail($user['email'], $mass_email['subject'], $mass_email['message'], "", "", "", false, $format, $text_message);
			}
			++$sentcount;

			$mass_email['message'] = $mass_email['orig_message'];
			$mass_email['htmlmessage'] = $mass_email['orig_htmlmessage'];
		}

		$update_array = array();

		$update_array['sentcount'] = $mass_email['sentcount'] + $sentcount;
		$update_array['totalcount'] = $mass_email['totalcount'];

		if($update_array['sentcount'] >= $mass_email['totalcount'])
		{
			$update_array['status'] = 3;
		}

		$db->update_query("massemails", $update_array, "mid='{$mass_email['mid']}'");
	}

	add_task_log($task, $lang->task_massmail_ran);
}
