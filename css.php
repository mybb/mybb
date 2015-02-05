<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define("NO_ONLINE", 1);
define('THIS_SCRIPT', 'css.php');

require_once "./inc/init.php";
require_once MYBB_ROOT . $config['admin_dir'] . '/inc/functions_themes.php';

$stylesheet = $mybb->get_input('stylesheet', MyBB::INPUT_INT);

if($stylesheet)
{
	$options = array(
		"limit" => 1
	);
	$query = $db->simple_select("themestylesheets", "stylesheet", "sid=".$stylesheet, $options);
	$stylesheet = $db->fetch_field($query, "stylesheet");

	$plugins->run_hooks("css_start");

	if(!empty($mybb->settings['minifycss']))
	{
		$stylesheet = minify_stylesheet($stylesheet);
	}

	$plugins->run_hooks("css_end");

	header("Content-type: text/css");
	echo $stylesheet;
}
exit;
