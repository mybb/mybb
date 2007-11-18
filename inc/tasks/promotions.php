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

function task_promotions($task)
{
	global $mybb, $db;
	
	// Iterate through all our promotions
	$query = $db->simple_select("promotions", "*", "enabled = '1'");
	while($promotion = $db->fetch_array($query))
	{
		$and = "";
		$sql_where = "";
		
		// Based on the promotion generate criteria for user selection
		$requirements = explode(',', $promotion['requirements']);
		if(in_array('postcount', $requirements) && intval($promotion['posts']) > 0 && !empty($promotion['posttype']))
		{
			$sql_where .= "{$and}postnum {$promotion['posttype']} '{$promotion['posts']}'";
			
			$and = " AND ";
		}
		
		if(in_array('reputation', $requirements) && intval($promotion['reputations']) > 0 && !empty($promotion['reputationtype']))
		{
			$sql_where .= "{$and}reputation {$promotion['reputationtype']} '{$promotion['reputations']}'";
			
			$and = " AND ";
		}
		
		if(in_array('timeregistered', $requirements) && intval($promotion['registered']) > 0 && !empty($promotion['registeredtype']))
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
				case "months":
					$regdate = $promotion['registered']*60*60*24*7*30;
					break;
				case "years":
					$regdate = $promotion['registered']*60*60*24*7*365;
					break;
				default:
					$regdate = $promotion['registered']*60*60*24;
			}
			$sql_where .= "{$and}regdate <= '".(TIME_NOW-$regdate)."'";
			$and = " AND ";
		}
		
		if(!empty($promotion['originalusergroup']) && $promotion['originalusergroup'] != '*')
		{
			$sql_where .= "{$and}usergroup IN ({$promotion['originalusergroup']})";
				
			$and = " AND ";
		}
		
		if(!empty($promotion['newusergroup']))
		{
			$sql_where .= "{$and}usergroup != '{$promotion['newusergroup']}'";
					
			$and = " AND ";
		}
		
		$sql_where .= "{$and}lastactive >= '{$task['lastrun']}'";
		
		$uid = array();
		$log_inserts = array();
		
		$query = $db->simple_select("users", "uid,usergroup", $sql_where);
		while($user = $db->fetch_array($query))
		{
			$uids[] = $user['uid'];
			$log_inserts[] = array(
				'pid' => $promotion['pid'],
				'uid' => $user['uid'],
				'oldusergroup' => $user['usergroup'],
				'newusergroup' => $promotion['newusergroup'],
				'dateline' => TIME_NOW
			);			
			
			if((count($uids) % 20) == 0)
			{
				$db->update_query("users", array('usergroup' => $promotion['newusergroup']), "uid IN(".implode(",", $uids).")");
				$db->insert_query_multiple("promotionlogs", $log_inserts);
				
				$uids = array();
				$log_inserts = array();
			}
		}
		
		if(count($uids) > 0)
		{
			$db->update_query("users", array('usergroup' => $promotion['newusergroup']), "uid IN(".implode(",", $uids).")");
			$db->insert_query_multiple("promotionlogs", $log_inserts);
				
			$uids = array();
			$log_inserts = array();
		}
	}	
}
?>