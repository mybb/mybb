<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: css.php 4304 2009-01-02 01:11:56Z chris $
 */

define("IN_MYBB", 1);
define("NO_ONLINE", 1);
define('THIS_SCRIPT', 'css.php');

require_once "./inc/init.php";

$stylesheet = intval($mybb->input['stylesheet']);

if($stylesheet)
{
	$options = array(
		"limit" => 1
	);
	$query = $db->simple_select("themestylesheets", "stylesheet", "sid=".$stylesheet, $options);
	$stylesheet = $db->fetch_field($query, "stylesheet");

	header("Content-type: text/css");
	echo $stylesheet;
}
exit;
?>