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

$stylesheets = $mybb->get_input('stylesheet', MyBB::INPUT_ARRAY);

if(!empty($stylesheets))
{
	$stylesheet_list = '';
	$sep = '';

	foreach($stylesheets as $stylesheet)
	{
		$stylesheet_list .= $sep . (int) $stylesheet;
		$sep = ', ';
	}

	$content = '';

	$query = $db->simple_select('themestylesheets', 'stylesheet', "sid IN ({$stylesheet_list})", array(
		'order_by' => 'sid',
		'order_dir' => 'ASC',
	));

	while($row = $db->fetch_array($query))
	{
		$stylesheet = $row['stylesheet'];

		$plugins->run_hooks('css_start');

		if(!empty($mybb->settings['minifycss']))
		{
			$stylesheet = minify_stylesheet($stylesheet);
		}

		$plugins->run_hooks('css_end');

		$content .= $stylesheet;
	}

	header('Content-type: text/css');
	echo $content;
}
exit;
