<?php

/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class Unrecommend implements Modules
{
	public function setup()
	{
	}
	
	public function end()
	{
	}

	public function run_pre()
	{
		global $mybb, $lang, $db, $templates, $mods, $inline_errors, $action;
		
		// Correct post key?
		verify_post_check($mybb->input['my_post_key']);
		
		// Does the project exist? Must be approved in order to be viewed
		$pid = (int)$mybb->input['pid'];
		$project = $mods->projects->getByID($pid,true,true);
		if (empty($project))
		{
			$mods->error($lang->mods_invalid_pid);
		}
		
		// Did we already recommend this project?
		$query = $db->simple_select('mods_recommended', '*', 'uid=\''.intval($mybb->user['uid']).'\' AND pid=\''.intval($project['pid']).'\'');
		$recommend = $db->fetch_array($query);
		if (empty($recommend))
		{
			$mods->error($lang->mods_noy_recommended);
		}
		
		$db->delete_query('mods_recommended', 'pid=\''.$pid.'\' AND  uid=\''.(int)$mybb->user['uid'].'\'');
		
		$action = 'view';
	}
	
	public function run()
	{
		global $mybb, $db, $lang, $mods, $primerblock, $rightblock, $content, $title, $theme, $templates, $navigation, $inline_errors;
	}
}

?>