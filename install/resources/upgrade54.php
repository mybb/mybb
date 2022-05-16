<?php
/**
 * MyBB 1.9
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Upgrade Script: 1.9.0
 */

$upgrade_detail = array(
    'revert_all_templates' => 0,
    'revert_all_themes' => 0,
    'revert_all_settings' => 0
);

@set_time_limit(0);

function upgrade54_dbchanges()
{
	global $output, $cache, $db, $mybb;

	$output->print_header('Updating Database');

	echo '<p>Performing necessary upgrade queries...</p>';
	flush();

	$db->drop_table('themes');
	switch($db->type)
	{
		case 'pgsql':
			$db->write_query('
CREATE TABLE '.TABLE_PREFIX."themes (
  tid serial,
  package varchar(32) NOT NULL,
  version varchar(32) NOT NULL,
  title varchar(100) NOT NULL default '',
  properties text NOT NULL default '',
  stylesheets text NOT NULL default '',
  allowedgroups text NOT NULL default '',
  PRIMARY KEY (tid)
);");
		case 'sqlite':
			$db->write_query('
CREATE TABLE '.TABLE_PREFIX."themes (
  tid INTEGER PRIMARY KEY,
  package varchar(32) NOT NULL default '',
  version varchar(32) NOT NULL,
  title varchar(100) NOT NULL default '',
  properties TEXT NOT NULL,
  stylesheets TEXT NOT NULL,
  allowedgroups TEXT NOT NULL
);");
		default: /* MySQL */
			$charset = $db->build_create_table_collation();
			$db->write_query('
CREATE TABLE '.TABLE_PREFIX."themes (
  tid smallint unsigned NOT NULL auto_increment,
  package varchar(32) NOT NULL,
  version varchar(32) NOT NULL,
  title varchar(100) NOT NULL default '',
  properties text NOT NULL,
  stylesheets text NOT NULL,
  allowedgroups text NOT NULL,
  PRIMARY KEY (tid)
) ENGINE=MyISAM{$charset};");
	}

	$core_theme_title = 'Default';
	$manifest_file = MYBB_ROOT.'inc/themes/core.default/current/manifest.json';
	if(is_readable($manifest_file))
	{
		$json = file_get_contents($manifest_file);
		$manifest = json_decode($json, true);
		if(is_array($manifest) && !empty($manifest['extra']['title']))
		{
			$core_theme_title = $manifest['extra']['title'];
		}
	}

	// An empty `version` field indicates to use `current` (or `devdist` if in development mode and that directory exists).
	$db->insert_query('themes', ['package' => 'core.default', 'version' => '', 'title' => $db->escape_string($core_theme_title)]);

	$output->print_contents('<p>Click next to continue with the upgrade process.</p>');
	$output->print_footer('54_done');
}
