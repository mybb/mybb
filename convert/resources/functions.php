<?php
function update_session()
{
	global $session, $db;
	
	$session = $db->escape_string(serialize($session));
	$query = $db->simple_select("datacache", "*", "title='importcache'");
	$sess = $db->fetch_array($query);
	
	if(!$sess['cache'])
	{
		$insertarray = array(
			'title' => 'importcache',
			'cache' => $session,
		);
		$db->insert_query("datacache", $insertarray);
	} 
	else
	{
		$db->update_query("datacache", array('cache' => $session), "title='importcache'");
	}
}

function insert_user($user)
{
	global $db;

	foreach($user as $key => $value)
	{
		$insertarray[$key] = $db->escape_string($value);
	}
	
	$query = $db->insert_query("users", $insertarray);
	$uid = $db->insert_id();
	
	return $uid;
}

function insert_thread($thread)
{
	global $db;

	foreach($thread as $key => $value)
	{
		$insertarray[$key] = $db->escape_string($value);
	}
	
	$query = $db->insert_query("threads", $insertarray);
	$tid = $db->insert_id();
	
	return $tid;
}

function get_import_users()
{
	global $db;

	$query = $db->simple_select("users", "uid, importuid");
	while($user = $db->fetch_array($query))
	{
		$users[$user['importuid']] = $user['uid'];
	}
	return $users;
}

function get_import_forums()
{
	global $db;

	$query = $db->simple_select("forums", "fid, importfid");
	while($forum = $db->fetch_array($query))
	{
		$forums[$forum['importfid']] = $forum['fid'];
	}
	return $forums;
}

function get_import_threads()
{
	global $db;
	
	$query = $db->simple_select("threads", "tid, importtid");
	while($thread = $db->fetch_array($query))
	{
		$threads[$thread['importtid']] = $thread['tid'];
	}
	return $threads;
}

function get_import_posts()
{
	global $db;
	
	$query = $db->simple_select("posts", "pid, importpid");
	while($post = $db->fetch_array($query))
	{
		$posts[$post['importpid']] = $post['pid'];
	}
	return $posts;
}

function get_import_attachments()
{
	global $db;
	
	$query = $db->simple_select("attachments", "aid, importaid");
	while($attachment = $db->fetch_array($query))
	{
		$attachments[$attachment['importaid']] = $attachment['aid'];
	}
	return $attachments;
}

function get_import_usergroups()
{
	global $db;
	
	$query = $db->query("usergroups", "gid, importgid");
	while($usergroup = $db->fetch_array($query))
	{
		$usergroups[$usergroup['importgid']] = $usergroup['gid'];
	}
	return $usergroups;
}

function int_to_yesno($var)
{
	$var = intval($var);
	
	if($var == 1)
	{
		return "yes";
	}
	else
	{
		return "no";
	}
}
?>