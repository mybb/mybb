<?php
/**
 * MyBB 1.8
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
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade44_dbchanges()
{
	global $db, $output;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->field_exists('moved', 'threads'))
	{
		$db->drop_column("threads", "moved");
	}

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$db->add_column("threads", "moved", "int NOT NULL default '0'");
			break;
		default:
			$db->add_column("threads", "moved", "int NOT NULL default '0' AFTER closed");
			break;
	}

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("44_dbchanges2");
}

function upgrade44_dbchanges2()
{
	global $db, $output;

	$output->print_header("Updating Database");
	echo "<p>Updating moved threads...</p>";
	flush();

	$query = $db->query("
		SELECT tid, closed
		FROM ".TABLE_PREFIX."threads
		WHERE closed LIKE 'moved|%'
		ORDER BY tid DESC
	");
	while($thread = $db->fetch_array($query))
	{
		$tid = substr($thread['closed'], 6);

		$update_array = array(
			'closed' => 2,
			'moved' => (int)$tid
		);
		$db->update_query("threads", $update_array, "tid='{$thread['tid']}'");
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("44_dbchanges3");
}

function upgrade44_dbchanges3()
{
	global $db, $output;

	$output->print_header("Updating Database");
	echo "<p>Updating moved threads...</p>";
	flush();

	switch($db->type)
	{
		case "pgsql":
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ALTER COLUMN closed DROP DEFAULT"); // We need to drop the default first as PostgreSQL can't cast default values
			$db->modify_column("threads", "closed", "smallint USING (trim(closed)::smallint)", "set", "'0'");
			break;
		default:
			$db->modify_column("threads", "closed", "tinyint(1) NOT NULL default '0'");
			break;
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("44_done");
}
