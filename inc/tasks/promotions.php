<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
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
		$sql_update = array();
		
		// Based on the promotion generate criteria for user selection
		$requirements = explode(',', $promotions['requirements']);
		if(in_array('postcount', $requirements) && intval($promotion['posts']) > 0 && !empty($promotion['posttype']))
		{
			$sql_where .= "postnum {$promotion['posttype']} '{$promotion['posts']}'{$and}";
			
			$and = " AND ";
		}
		
		if(in_array('reputation', $requirements) && intval($promotion['reputations']) > 0 && !empty($promotion['reputationtype']))
		{
			$sql_where .= "reputation {$promotion['reputationtype']} '{$promotion['reputations']}'{$and}";
			
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
					$regdate = $promotion['registered']*60*60*24*7*30*12;
					break;
				default:
					$regdate = $promotion['registered']*60*60*24;
			}
			$sql_where .= "regdate >= '".time()-$regdate."'{$and}";
			
			$and = " AND ";
		}
		
		if(!empty($promotion['originalusergroup']))
		{
			$sql_where .= "usergroup = '{$promotion['originalusergroup']}'{$and}");
				
			$and = " AND ";
		}
		
		if(!empty($promotion['newusergroup']))
		{
			$sql_where .= "usergroup != '{$promotion['newusergroup']}'{$and}");
					
			$and = " AND ";
		}
		
		$sql_where .= "lastactive >= '{$task['lastrun']}";
		
		$db->update_query("users", array('usergroup' => $promotion['newusergroup']), $sql_where);
	}	
}
?>