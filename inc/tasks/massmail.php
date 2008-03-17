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
 
require_once MYBB_ROOT."/inc/functions_massmail.php";
require_once MYBB_ROOT."inc/datahandlers/pm.php";

function task_massmail($task)
{
	global $db, $mybb, $lang;	
	
	$query = $db->simple_select("massemails", "*", "senddate <= '".TIME_NOW."' AND status IN (1,2)");
	while($mass_email = $db->fetch_array($query))
	{
		if($mass_email['status'] == 1)
		{
			$db->update_query("massemails", array('status' => 2), "mid='{$mass_email['mid']}'", 1);
		}
		
		$sentcount = 0;
		
		if(!$mass_email['perpage'])
		{
			$mass_email['perpage'] = 50;
		}
		
		// Need to perform the search to fetch the number of users we're emailing
		$member_query = build_mass_mail_query(unserialize($mass_email['conditions']));
		
		$query2 = $db->simple_select("users u", "u.uid, u.language, u.pmnotify, u.lastactive, u.username, u.email", $member_query, array('limit_start' => $mass_email['sentcount'], 'limit' => $mass_email['perpage'], 'order_by' => 'u.uid', 'order_dir' => 'asc'));
		while($user = $db->fetch_array($query2))
		{
			$mass_email['message'] = str_replace("{uid}", $user['uid'], $mass_email['message']);
			$mass_email['message'] = str_replace("{username}", $user['username'], $mass_email['message']);
			$mass_email['message'] = str_replace("{email}", $user['email'], $mass_email['message']);
			$mass_email['message'] = str_replace("{bbname}", $mybb->settings['bbname'], $mass_email['message']);
			$mass_email['message'] = str_replace("{bburl}", $mybb->settings['bburl'], $mass_email['message']);
			
			$mass_email['htmlmessage'] = str_replace("{uid}", $user['uid'], $mass_email['htmlmessage']);
			$mass_email['htmlmessage'] = str_replace("{username}", $user['username'], $mass_email['htmlmessage']);
			$mass_email['htmlmessage'] = str_replace("{email}", $user['email'], $mass_email['htmlmessage']);
			$mass_email['htmlmessage'] = str_replace("{bbname}", $mybb->settings['bbname'], $mass_email['htmlmessage']);
			$mass_email['htmlmessage'] = str_replace("{bburl}", $mybb->settings['bburl'], $mass_email['htmlmessage']);
				
			// Private Message
			if($mass_email['type'] == 1)
			{				
				$pm_handler = new PMDataHandler();
				$pm_handler->admin_override = true;
			
				$pm = array(
					"subject" => $mass_email['subject'],
					"message" => $mass_email['message'],
					"fromid" => $mass_email['uid']
				);
				
				$pm['to'] = explode(",", $user['username']);
				$pm_handler->set_data($pm);
				$pm_handler->validate_pm();
				$pm_handler->insert_pm();
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
		}
		
		$update_array = array();
		
		if($sentcount >= $mass_email['totalcount'])
		{
			$update_array['status'] = 3;
		}
		
		$update_array['sentcount'] = $mass_email['sentcount'] + $sentcount;
		
		$db->update_query("massemails", $update_array, "mid='{$mass_email['mid']}'", 1);
	}
	
	add_task_log($task, $lang->task_massmail_ran);
}
?>