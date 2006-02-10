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

require_once "inc/class_syndication.php";
$syndication = new Syndication();
$syndication->set_feed_type($mybb->input['type']);
$syndication->set_limit($mybb->input['limit']);

/* Syndicate a specific forum or all viewable? */
if(isset($mybb->input['fid']))
{
	$flist = $mybb->input['fid'];
	$forums = explode(',', $flist);
	$syndication->set_forum_list($forums);
}
else 
{
	$syndication->set_forum_list();
}

/* This is where all the good stuff happens. */
$syndication->generate_feed();

?>