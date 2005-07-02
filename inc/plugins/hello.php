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

$plugins->add_hook("pre_output_page", "hello_world");

function hello_info()
{
	return array(
		"name"			=> "Hello World!",
		"description"	=> "A sample plugin that prints hello world!",
		"webste"		=> "http://www.mybboard.com",
		"author"		=> "MyBB Group",
		"authorsite"	=> "http://www.mybboard.com",
		"version"		=> "1.0",
	);
}

function hello_world($page)
{
//error_reporting(E_ALL);
//return $page;
//echo $page;
	$page = str_replace("<div id=\"content\">", "<div id=\"content\">Hello World!<br />This is a sample MyBB Plugin (which can be disabled!) that displays this message on all pages.<br />", $page);
return $page;
}