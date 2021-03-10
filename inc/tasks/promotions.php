<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

function task_promotions($task)
{
	global $mybb, $db, $lang, $cache, $plugins;

	$usergroups = $cache->read("usergroups");
	// Iterate through all our promotions
	$query = $db->simple_select("promotions", "*", "enabled = '1'");
	while($promotion = $db->fetch_array($query))
	{
		// Does the destination usergroup even exist?? If it doesn't and it moves a user to it, the user will get PHP errors.
		if(!array_key_exists($promotion['newusergroup'], $usergroups))
		{
			// Instead of just skipping this promotion, disable it to stop it even being selected when this task is run.
			$update = array(
				"enabled" => 0
			);
			$db->update_query("promotions", $update, "pid = '" . (int)$promotion['pid'] . "'");
			continue;
		}

		$and = "";
		$sql_where = "";

		// Based on the promotion generate criteria for user selection
		$requirements = explode(',', $promotion['requirements']);
		if(in_array('postcount', $requirements) && (int)$promotion['posts'] >= 0 && !empty($promotion['posttype']))
		{
			$sql_where .= "{$and}postnum {$promotion['posttype']} '{$promotion['posts']}'";

			$and = " AND ";
		}

		if(in_array('threadcount', $requirements) && (int)$promotion['threads'] >= 0 && !empty($promotion['threadtype']))
		{
			$sql_where .= "{$and}threadnum {$promotion['threadtype']} '{$promotion['threads']}'";

			$and = " AND ";
		}

		if(in_array('reputation', $requirements) && !empty($promotion['reputationtype']))
		{
			$sql_where .= "{$and}reputation {$promotion['reputationtype']} '{$promotion['reputations']}'";

			$and = " AND ";
		}

		if(in_array('referrals', $requirements) && (int)$promotion['referrals'] >= 0 && !empty($promotion['referralstype']))
		{
			$sql_where .= "{$and}referrals {$promotion['referralstype']} '{$promotion['referrals']}'";

			$and = " AND ";
		}

		if(in_array('warnings', $requirements) && (int)$promotion['warnings'] >= 0 && !empty($promotion['warningstype']))
		{
			$sql_where .= "{$and}warningpoints {$promotion['warningstype']} '{$promotion['warnings']}'";

			$and = " AND ";
		}

		if(in_array('timeregistered', $requirements) && (int)$promotion['registered'] > 0 && !empty($promotion['registeredtype']))
		{
			switch($promotion['registeredtype'])
			{
				case "hours":
					$regdate = $promotion['registered']*60*60;
					break;
				case "days":
					$regdate = $promotion['registered']*60*60*24;
					break;
				case "weeks":
					$regdate = $promotion['registered']*60*60*24*7;
					break;
				case "months":
					$regdate = $promotion['registered']*60*60*24*30;
					break;
				case "years":
					$regdate = $promotion['registered']*60*60*24*365;
					break;
				default:
					$regdate = $promotion['registered']*60*60*24;
			}
			$sql_where .= "{$and}regdate <= '".(TIME_NOW-$regdate)."'";
			$and = " AND ";
		}

		if(in_array('timeonline', $requirements) && (int)$promotion['online'] > 0 && !empty($promotion['onlinetype']))
		{
			switch($promotion['onlinetype'])
			{
				case "hours":
					$timeonline = $promotion['online']*60*60;
					break;
				case "days":
					$timeonline = $promotion['online']*60*60*24;
					break;
				case "weeks":
					$timeonline = $promotion['online']*60*60*24*7;
					break;
				case "months":
					$timeonline = $promotion['online']*60*60*24*30;
					break;
				case "years":
					$timeonline = $promotion['online']*60*60*24*365;
					break;
				default:
					$timeonline = $promotion['online']*60*60*24;
			}
			$sql_where .= "{$and}timeonline > '".$timeonline."'";
			$and = " AND ";
		}

		if(!empty($promotion['originalusergroup']) && $promotion['originalusergroup'] != '*')
		{
			$sql_where .= "{$and}usergroup IN ({$promotion['originalusergroup']})";

			$and = " AND ";
		}

		if(!empty($promotion['newusergroup']))
		{
			// Skip users that are already in the new group
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					$sql_where .= "{$and}usergroup != '{$promotion['newusergroup']}' AND ','||additionalgroups||',' NOT LIKE '%,{$promotion['newusergroup']},%'";
					break;
				default:
					$sql_where .= "{$and}usergroup != '{$promotion['newusergroup']}' AND CONCAT(',', additionalgroups, ',') NOT LIKE '%,{$promotion['newusergroup']},%'";
			}

			$and = " AND ";
		}

		$uid = array();
		$log_inserts = array();

		if($promotion['usergrouptype'] == "secondary")
		{
			$usergroup_select = "additionalgroups";
		}
		else
		{
			$usergroup_select = "usergroup";
		}

		if(is_object($plugins))
		{
			$args = array(
				'task' => &$task,
				'promotion' => &$promotion,
				'sql_where' => &$sql_where,
				'and' => &$and,
				'usergroup_select' => &$usergroup_select
			);
			$plugins->run_hooks('task_promotions', $args);
		}

		$query2 = $db->simple_select("users", "uid,{$usergroup_select}", $sql_where);

		$uids = array();
		while($user = $db->fetch_array($query2))
		{
			if(is_super_admin($user['uid']))
			{
				// Skip super admins
				continue;
			}

			// super admin check?
			if($usergroup_select == "additionalgroups")
			{
				$log_inserts[] = array(
					'pid' => $promotion['pid'],
					'uid' => $user['uid'],
					'oldusergroup' => $db->escape_string($user['additionalgroups']),
					'newusergroup' => $promotion['newusergroup'],
					'dateline' => TIME_NOW,
					'type' => "secondary",
				);
			}
			else
			{
				$log_inserts[] = array(
					'pid' => $promotion['pid'],
					'uid' => $user['uid'],
					'oldusergroup' => $user['usergroup'],
					'newusergroup' => $promotion['newusergroup'],
					'dateline' => TIME_NOW,
					'type' => "primary",
				);
			}

			$uids[] = $user['uid'];


			if($usergroup_select == "additionalgroups")
			{
				if(join_usergroup($user['uid'], $promotion['newusergroup']) === false)
				{
					// Did the user already have the additional usergroup?
					array_pop($log_inserts);
					array_pop($uids);
				}
			}

			if((count($uids) % 20) == 0)
			{
				if($usergroup_select == "usergroup")
				{
					$db->update_query("users", array('usergroup' => $promotion['newusergroup']), "uid IN(".implode(",", $uids).")");
				}

				if(!empty($log_inserts))
				{
					$db->insert_query_multiple("promotionlogs", $log_inserts);
				}

				$uids = array();
				$log_inserts = array();
			}
		}

		if(count($uids) > 0)
		{
			if($usergroup_select == "usergroup")
			{
				$db->update_query("users", array('usergroup' => $promotion['newusergroup']), "uid IN(".implode(",", $uids).")");
			}

			if(!empty($log_inserts))
			{
				$db->insert_query_multiple("promotionlogs", $log_inserts);
			}

			$uids = array();
			$log_inserts = array();
		}
	}

	$cache->update_moderators();

	add_task_log($task, $lang->task_promotions_ran);
}
