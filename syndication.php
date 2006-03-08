<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

define("NO_ONLINE", 1);

require "./global.php";

// Load global language phrases
$lang->load("syndication");

// Load syndication class.
require_once "inc/class_syndication.php";
$syndication = new Syndication();

// Set the feed type and add a feed wrapper.
$syndication->set_feed_format($mybb->input['type']);
$syndication->start_wrapper();

// Find out the thread limit.
$thread_limit = intval($mybb->input['limit']);

// Syndicate a specific forum or all viewable?
if(isset($mybb->input['fid']))
{
	$forumlist = $mybb->input['fid'];
	$forumlist = explode(',', $forumlist);
}
else
{
	$forumlist = "";
}

// Get the forums the user is not allowed to see.
$unviewable = getunviewableforums();

// If there are any, add SQL to exclude them.
if($unviewable)
{
	$unviewable = "AND f.fid NOT IN($unviewable)";
}

// If there are no forums to syndicate, syndicate all viewable.
if(!empty($forumlist))
{
	$forum_ids = "'-1'";
	foreach($forumlist as $fid)
	{
		$forum_ids .= ",'".intval($fid)."'";
	}
	$forumlist = "AND f.fid IN ($forum_ids) $unviewable";
}
else
{
	$forumlist = $unviewable;
}

// Get the threads to syndicate.
$query = $db->query("
	SELECT t.*, f.name AS forumname, p.message AS postmessage
	FROM ".TABLE_PREFIX."threads t
	LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid)
	LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=t.firstpost)
	WHERE 1=1
	AND p.visible=1 ".$forumlist."
	ORDER BY t.dateline DESC
	LIMIT 0, ".$thread_limit
);

// Loop through all the threads.
while($thread = $db->fetch_array($query))
{
	$syndication->add_post($thread);
}

// Then output the feed XML.
$syndication->end_wrapper();
$syndication->output_feed();

?>