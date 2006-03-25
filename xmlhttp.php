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

/**
 * The deal with this file is that it handles all of the XML HTTP Requests for MyBB.
 *
 * It contains a stripped down version of the MyBB core which does not load things
 * such as themes, who's online data, all of the language packs and more.
 *
 * This is done to make response times when using XML HTTP Requests faster and
 * less intense on the server.
 */

// We don't want visits here showing up on the Who's Online
define("NO_ONLINE", 1);

// Load MyBB core files
require "./inc/init.php";

$shutdown_queries = array();

// Load some of the stock caches we'll be using.
$groupscache = $cache->read("usergroups");

if(!is_array($groupscache))
{
	$cache->updateusergroups();
	$groupscache = $cache->read("usergroups");
}
$fpermissioncache = $cache->read("forumpermissions");


// Send page headers
pageheaders();


// Create the session
require "./inc/class_session.php";
$session = new session;
$session->init();

// Load the language we'll be using
if(!isset($mybb->settings['bblanguage']))
{
	$mybb->settings['bblanguage'] = "english";
}
if(isset($mybb->user['language']) && $lang->languageExists($mybb->user['language']))
{
	$mybb->settings['bblanguage'] = $mybb->user['language'];
}
$lang->setLanguage($mybb->settings['bblanguage']);

// Load the language pack for this file
//$lang->load("xmlhttp");

$plugins->run_hooks("xmlhttp");

if($mybb->input['action'] == "get_users")
{
	if(my_strlen($mybb->input['query']) < 3)
	{
		exit;
	}
	
	header("Content-type: text/html; charset=utf-8");

	$mybb->input['query'] = str_replace(array("%", "_"), array("\%", "\_"), $mybb->input['query']);
	
	$query_options = array(
		"order_by" => "username",
		"order_dir" => "asc",
		"limit_start" => 0,
		"limit" => 15
	);
	$query = $db->simple_select(TABLE_PREFIX."users", "uid, username", "username LIKE '".$db->escape_string($mybb->input['query'])."%'", $query_options);
	while($user = $db->fetch_array($query))
	{
		echo "<div>\n";
		echo "<span class=\"username\">".htmlspecialchars_uni($user['username'])."</span>\n";
		//echo "<span class=\"uid\">".$user['uid']."</span>\n";
		echo "</div>\n";
	}
}
?>