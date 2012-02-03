<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: css.php 5297 2010-12-28 22:01:14Z Tomm $
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