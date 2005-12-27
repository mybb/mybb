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

/**
 * Upgrade Script: 1.0 Final
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
	);

@set_time_limit(0);

function upgrade5_dbchanges()
{
	global $db, $output;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";

	$db->query("ALTER TABLE ".TABLE_PREFIX."users CHANGE avatartype avatartype varchar(10) NOT NULL AFTER avatar;");

	echo "Done</p>";
	
	$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."mycodes");	
	$db->query("
		CREATE TABLE ".TABLE_PREFIX."mycodes (
			cid int unsigned NOT NULL auto_increment,
			regex varchar(255) NOT NULL default '',
			replacement varchar(255) NOT NULL default '',
			PRIMARY KEY(cid)
		) TYPE=MyISAM;
	");
	
	$insert_mycodes = array(
		"#\[b\](.*?)\[/b\]#si" => "<strong>$1</strong>",
		"#\[i\](.*?)\[/i\]#si" => "<em>$1</em>",
		"#\[u\](.*?)\[/u\]#si" => "<u>$1</u>",
		"#\[s\](.*?)\[/s\]#si" => "<del>$1</del>",
		"#\(c\)#i" => "&copy;",
		"#\(tm\)#i" => "&#153;",
		"#\(r\)#i" => "&reg;",
		"#\[url\]([a-z]+?://)([^\r\n\"\[<]+?)\[/url\]#sei" => "MyCode::do_shorturl(\"$1$2\")",
		"#\[url\]([^\r\n\"\[<]+?)\[/url\]#ei" => "MyCode::do_shorturl(\"$1\")",
		"#\[url=([a-z]+?://)([^\r\n\"\[<]+?)\](.+?)\[/url\]#esi" => "MyCode::do_shorturl(\"$1$2\", \"$3\")",
		"#\[url=([^\r\n\"\[<]+?)\](.+?)\[/url\]#esi" => "MyCode::do_shorturl(\"$1\", \"$2\")",
		"#\[email\](.*?)\[/email\]#ei" => "MyCode::do_emailurl(\"$1\")",
		"#\[email=(.*?)\](.*?)\[/email\]#ei" => "MyCode::do_emailurl(\"$1\", \"$2\")",
		"#\[color=([a-zA-Z]*|\#?[0-9a-fA-F]{6})](.*?)\[/color\]#si" => "<span style=\"color: $1;\">$2</span>",
		"#\[size=(xx-small|x-small|small|medium|large|x-large|xx-large)\](.*?)\[/size\]#si" => "<span style=\"font-size: $1;\">$2</span>",
		"#\[font=([a-z ]+?)\](.+?)\[/font\]#si" => "<span style=\"font-family: $1;\">$2</span>",
		"#\[align=(left|center|right|justify)\](.*?)\[/align\]#si" => "<p style=\"text-align: $1;\">$2</p>",
	);
	
	foreach($insert_mycodes as $regex => $replacement)
	{
		$db->query("INSERT INTO ".TABLE_PREFIX."mycodes (cid, regex, replacement) VALUES (0, '".mysql_real_escape_string($regex)."', '".mysql_real_escape_string($replacement)."')");
	}
	
	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("5_done");
}

?>