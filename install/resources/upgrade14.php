<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

/**
 * Upgrade Script: MyBB 1.4.2
 */


$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade14_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();
	
	// TODO: Need to check for PostgreSQL / SQLite support

	if($db->field_exists('codepress', "adminoptions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions DROP codepress;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions ADD codepress int(1) NOT NULL default '1' AFTER cpstyle");
	
	if($db->type != "sqlite2" && $db->type != "sqlite3")
	{
		$query = $db->query("SHOW INDEX FROM ".TABLE_PREFIX."users");
		while($ukey = $db->fetch_array($query))
		{
			if($ukey['Key_name'] == "longregip")
			{
				$longregip_index = true;
				continue;
			}
			
			if($ukey['Key_name'] == "longlastip")
			{
				$longlastip_index = true;
				continue;
			}
		}
		if($longlastip_index == true)
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP KEY longlastip");
		}
		
		if($longregip_index == true)
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP KEY longregip");
		}
		
		$query = $db->query("SHOW INDEX FROM ".TABLE_PREFIX."posts");
		while($pkey = $db->fetch_array($query))
		{
			if($pkey['Key_name'] == "longipaddress")
			{
				$longipaddress_index = true;
				break;
			}
		}
		if($longipaddress_index == true)
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts DROP KEY longipaddress");
		}
	}
	
	if($db->field_exists('loginattempts', "sessions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."sessions DROP loginattempts;");
	}
	
	if($db->field_exists('loginattempts', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP loginattempts;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD loginattempts tinyint(2) NOT NULL default '1';");
	
	if($db->field_exists('failedlogin', "sessions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."sessions DROP failedlogin;");
	}
	
	if($db->field_exists('failedlogin', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP failedlogin;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD failedlogin bigint(30) NOT NULL default '0';");
	
	if($db->type == "mysql" || $db->type == "mysqli")
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD INDEX longregip (longregip)");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD INDEX longlastip (longlastip)");
	}
	
	if($db->type == "sqlite2" || $db->type == "sqlite3")
	{
		// Because SQLite 2 nor 3 allows changing a column with a primary key constraint we have to completely rebuild the entire table
		// *sigh* This is the 21st century, right?
		$query = $db->simple_select("datacache");
		while($datacache = $db->fetch_array($query))
		{
			$temp_datacache[$datacache['title']] = array('title' => $db->escape_string($datacache['title']), 'cache' => $db->escape_string($datacache['cache']));
		}
		
		$db->write_query("DROP TABLE ".TABLE_PREFIX."datacache");
		
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."datacache (
  title varchar(50) NOT NULL default '' PRIMARY KEY,
  cache mediumTEXT NOT NULL
);");
		
		reset($temp_datacache);
		foreach($temp_datacache as $data)
		{
			$db->insert_query("datacache", $data);
		}
	}
	else if($db->type == "pgsql")
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."datacache ADD PRIMARY KEY (title)");
	}

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("14_dbchanges1");
}

function upgrade14_dbchanges1()
{
	global $db, $output;
	
	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();
	
	if($db->type == "mysql" || $db->type == "mysqli")
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts ADD INDEX longipaddress (longipaddress)");
	}
	
	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("14_dbchanges2");
}

function upgrade14_dbchanges2()
{
	global $db, $output;
	
	$output->print_header("Cleaning up settings and setting groups");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();
	
	$db->delete_query("settinggroups", "name='banning' AND isdefault='0'", 1);
	
	$db->delete_query("settings", "name='bannedusernames'", 1);
	$db->delete_query("settings", "name='bannedips'", 1);
	$db->delete_query("settings", "name='bannedemails'", 1);
	$db->delete_query("settings", "name='publiceventcolor'", 1);
	$db->delete_query("settings", "name='privateeventcolor'", 1);
	$db->delete_query("settings", "name='cssmedium'", 1);	
	
	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("14_done");
}


?>
