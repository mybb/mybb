<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://mybb.com/about/license
 *
 * $Id: $
 */

/**
 * Upgrade Script: 1.6.10
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade28_dbchanges()
{
	global $cache, $output, $mybb;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";

	if($db->type == "mysql" || $db->type == "mysqli")
	{
		if($db->index_exists('posts', 'tiddate'))
		{
			$db->drop_index('posts', 'tiddate');
		}

		$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts ADD INDEX (`tid`, `dateline`)");
	}

	if($db->field_exists('posthash', 'posts'))
	{
		$db->drop_column("posts", "posthash");
	}

	if($db->field_exists('isdefault', 'templategroups'))
	{
		$db->drop_column("templategroups", "isdefault");
	}

	if($db->field_exists('type', 'reportedposts'))
	{
		$db->drop_column("reportedposts", "type");
	}

	if($db->field_exists('reports', 'reportedposts'))
	{
		$db->drop_column("reportedposts", "reports");
	}

	if($db->field_exists('reporters', 'reportedposts'))
	{
		$db->drop_column("reportedposts", "reporters");
	}

	if($db->field_exists('lastreport', 'reportedposts'))
	{
		$db->drop_column("reportedposts", "lastreport");
	}

	if($db->field_exists('canbereported', 'usergroups'))
	{
		$db->drop_column('usergroups', 'canbereported');
	}

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$db->add_column("templategroups", "isdefault", "int NOT NULL default '0'");
			$db->add_column("reportedposts", "type", "varchar(50) NOT NULL default ''");
			$db->add_column("reportedposts", "reports", "int NOT NULL default '0'");
			$db->add_column("reportedposts", "reporters", "text NOT NULL default ''");
			$db->add_column("reportedposts", "lastreport", "bigint NOT NULL default '0'");
			$db->add_column("usergroups", "canbereported", "int NOT NULL default '0'");
			break;
		default:
			$db->add_column("templategroups", "isdefault", "int(1) NOT NULL default '0'");
			$db->add_column("reportedposts", "type", "varchar(50) NOT NULL default ''");
			$db->add_column("reportedposts", "reports", "int unsigned NOT NULL default '0'");
			$db->add_column("reportedposts", "reporters", "text NOT NULL");
			$db->add_column("reportedposts", "lastreport", "bigint(30) NOT NULL default '0'");
			$db->add_column("usergroups", "canbereported", "int(1) NOT NULL default '0'");
			break;
	}

	$groups = array();
	for($i = 1; $i <= 39; $i++)
	{
		$groups[] = $i;
	}

	$sql = implode(',', $groups);
	$db->update_query("templategroups", array('isdefault' => 1), "gid IN ({$sql})");

	$db->update_query("reportedposts", array('type' => 'post'));

	// Sync usergroups with canbereported; no moderators or banned groups
	$groups = array();
	$usergroups = $cache->read('usergroups');

	foreach($usergroups as $group)
	{
		if($group['canmodcp'] || $group['isbannedgroup'])
		{
			continue;
		}

		$groups[] = "'{$group['gid']}'";
	}

	$usergroups = implode(',', $groups);
	$db->update_query('usergroups', array('canbereported' => 1), "gid IN ({$usergroups})");

	sync_tasks(0);

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("28_updatetheme");
}

function upgrade28_updatetheme()
{
	global $db, $mybb, $output;

	if(file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions_themes.php"))
	{
		require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions_themes.php";
	}
	else if(file_exists(MYBB_ROOT."admin/inc/functions_themes.php"))
	{
		require_once MYBB_ROOT."admin/inc/functions_themes.php";
	}
	else
	{
		$output->print_error("Please make sure your admin directory is uploaded correctly.");
	}
	
	$output->print_header("Updating Themes");
	$contents = "<p>Updating the Default theme... ";

	$db->delete_query("templates", "sid = '1'");
	$query = $db->simple_select("themes", "tid", "tid = '2'");

	if($db->num_rows($query))
	{
		// Remove existing default theme
		$db->delete_query("themes", "tid = '2'");
		$db->delete_query("themestylesheets", "tid = '2'");
	}

	// Sounds crazy, but the new master files need to be inserted first
	// so we can inherit them properly
	$theme = @file_get_contents(INSTALL_ROOT.'resources/mybb_theme.xml');
	import_theme_xml($theme, array("tid" => 1, "no_templates" => 1, "version_compat" => 1));

	// Create the new default theme
	$tid = build_new_theme("Default", null, 1);
	$db->update_query("themes", array("tid" => 2), "tid = '{$tid}'");

	$tid = 2;
	
	// Now that the default theme is back, we need to insert our colors
	$query = $db->simple_select("themes", "*", "tid = '{$tid}'");

	$theme = $db->fetch_array($query);
	$properties = unserialize($theme['properties']);
	$stylesheets = unserialize($theme['stylesheets']);

	$query = $db->simple_select("themes", "tid", "def != '0'");

	if(!$db->num_rows($query))
	{
		// We remove the user's default theme, so put it back
		$db->update_query("themes", array("def" => 1), "tid = '{$tid}'");
	}

	require_once MYBB_ROOT."inc/class_xml.php";
	$colors = @file_get_contents(INSTALL_ROOT.'resources/mybb_theme_colors.xml');
	$parser = new XMLParser($colors);
	$tree = $parser->get_tree();

	if(is_array($tree) && is_array($tree['colors']))
	{
		if(is_array($tree['colors']['scheme']))
		{
			foreach($tree['colors']['scheme'] as $tag => $value)
			{
				$exp = explode("=", $value['value']);

				$properties['colors'][$exp[0]] = $exp[1];
			}
		}

		if(is_array($tree['colors']['stylesheets']))
		{
			$count = count($properties['disporder']) + 1;
			foreach($tree['colors']['stylesheets']['stylesheet'] as $stylesheet)
			{
				$new_stylesheet = array(
					"name" => $db->escape_string($stylesheet['attributes']['name']),
					"tid" => 2,
					"attachedto" => $db->escape_string($stylesheet['attributes']['attachedto']),
					"stylesheet" => $db->escape_string($stylesheet['value']),
					"lastmodified" => TIME_NOW,
					"cachefile" => $db->escape_string($stylesheet['attributes']['name'])
				);
			
				$sid = $db->insert_query("themestylesheets", $new_stylesheet);
				$css_url = "css.php?stylesheet={$sid}";

				$cached = cache_stylesheet($tid, $stylesheet['attributes']['name'], $stylesheet['value']);

				if($cached)
				{
					$css_url = $cached;
				}

				// Add to display and stylesheet list
				$properties['disporder'][$stylesheet['attributes']['name']] = $count;
				$stylesheets[$stylesheet['attributes']['attachedto']]['global'][] = $css_url;

				++$count;
			}
		}
		
		$update_array = array(
			"properties" => $db->escape_string(serialize($properties)),
			"stylesheets" => $db->escape_string(serialize($stylesheets))
		);

		$db->update_query("themes", $update_array, "tid = '{$tid}'");
	}

	$contents .= "done.</p>";
	echo $contents;

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("28_done");
}
?>