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

$stylesheet = $mybb->get_input('stylesheet', 1);

if($stylesheet)
{
	$options = array(
		"limit" => 1
	);
	$query = $db->simple_select("themestylesheets", "stylesheet", "sid=".$stylesheet, $options);
	$stylesheet = $db->fetch_field($query, "stylesheet");
	
	if(!empty($mybb->settings['minifycss']))
	{
		$stylesheet = minify_stylesheet($stylesheet);
	}

	$plugins->run_hooks("css_start");

	header("Content-type: text/css");
	echo $stylesheet;
}


/**
 * Minify a stylesheet to remove comments, linebreaks, whitespace,
 * unnecessary semicolons, and prefers #rgb over #rrggbb.
 *
 * @param $stylesheet string The stylesheet in it's untouched form.
 * @return string The minified stylesheet
 */
function minify_stylesheet($stylesheet)
{
	// Remove comments.
	$stylesheet = preg_replace('@/\*.*?\*/@s', '', $stylesheet);
	// Remove whitespace around symbols.
	$stylesheet = preg_replace('@\s*([{}:;,])\s*@', '\1', $stylesheet);
	// Remove unnecessary semicolons.
	$stylesheet = preg_replace('@;}@', '}', $stylesheet);
	// Replace #rrggbb with #rgb when possible.
	$stylesheet = preg_replace('@#([a-f0-9])\1([a-f0-9])\2([a-f0-9])\3@i','#\1\2\3',$stylesheet);
	$stylesheet = trim($stylesheet);
	return $stylesheet;
}

exit;
?>
