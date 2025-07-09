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
	$stylesheet_list = implode(', ', array_map('intval', $stylesheets));

	$content = '';
	$prefix = TABLE_PREFIX;

	switch($db->type)
	{
		case 'pgsql':
		case 'sqlite':
			$sql = <<<SQL
SELECT stylesheet FROM {$prefix}themestylesheets
  WHERE sid IN ({$stylesheet_list})
  ORDER BY CASE sid\n
SQL;

			$i = 0;
			foreach($stylesheets as $sid)
			{
				$sid = (int) $sid;

				$sql .= "WHEN {$sid} THEN {$i}\n";
				$i++;
			}

			$sql .= 'END;';
			break;
		default:
			$sql = <<<SQL
SELECT stylesheet FROM {$prefix}themestylesheets
  WHERE sid IN ({$stylesheet_list})
  ORDER BY FIELD(sid, {$stylesheet_list});
SQL;
			break;
	}

	$query = $db->query($sql);

	while($row = $db->fetch_array($query))
	{
		$stylesheet = $row['stylesheet'];

		$plugins->run_hooks('css_start', $stylesheet);

		if(!empty($mybb->settings['minifycss']))
		{
			$stylesheet = minify_stylesheet($stylesheet);
		}

		$plugins->run_hooks('css_end', $stylesheet);

		$content .= $stylesheet;
	}

	header('Content-type: text/css');
	echo $content;
}
exit;
