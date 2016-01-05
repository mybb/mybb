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
 * Upgrade Script: 1.6.14, 1.6.15, 1.6.16, 1.6.17 or 1.6.18
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade30_dbchanges()
{
	global $cache, $output, $mybb, $db;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	$db->update_query('settings', array('value' => -1), 'name IN (\'postmergefignore\', \'postmergeuignore\') AND value=\'\'');
	$db->update_query('settings', array('optionscode' => 'forumselect'), 'name IN (\'postmergefignore\', \'portal_announcementsfid\') AND optionscode=\'text\'');
	$db->update_query('settings', array('optionscode' => 'groupselect'), 'name=\'postmergeuignore\' AND optionscode=\'text\'');

	if($db->type == "mysql" || $db->type == "mysqli")
	{
		if($db->index_exists('posts', 'tiddate'))
		{
			$db->drop_index('posts', 'tiddate');
		}

		$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts ADD INDEX (`tid`, `dateline`)");
	}

	if($db->field_exists('modposts', 'usergroups'))
	{
		$db->drop_column("usergroups", "modposts");
	}

	if($db->field_exists('modthreads', 'usergroups'))
	{
		$db->drop_column("usergroups", "modthreads");
	}

	if($db->field_exists('mod_edit_posts', 'usergroups'))
	{
		$db->drop_column("usergroups", "mod_edit_posts");
	}

	if($db->field_exists('modattachments', 'usergroups'))
	{
		$db->drop_column("usergroups", "modattachments");
	}

	if($db->field_exists('regex', 'profilefields'))
	{
		$db->drop_column("profilefields", "regex");
	}

	if($db->field_exists('allowhtml', 'profilefields'))
	{
		$db->drop_column("profilefields", "allowhtml");
	}

	if($db->field_exists('allowmycode', 'profilefields'))
	{
		$db->drop_column("profilefields", "allowmycode");
	}

	if($db->field_exists('allowsmilies', 'profilefields'))
	{
		$db->drop_column("profilefields", "allowsmilies");
	}

	if($db->field_exists('allowimgcode', 'profilefields'))
	{
		$db->drop_column("profilefields", "allowimgcode");
	}

	if($db->field_exists('allowvideocode', 'profilefields'))
	{
		$db->drop_column("profilefields", "allowvideocode");
	}

	if($db->field_exists('viewableby', 'profilefields'))
	{
		$db->drop_column("profilefields", "viewableby");
	}

	if($db->field_exists('editable`', 'profilefields'))
	{
		$db->drop_column("profilefields", "editable");
	}

	if($db->field_exists('editableby', 'profilefields'))
	{
		$db->drop_column("profilefields", "editableby");
	}

	if($db->field_exists('oldgroup', 'awaitingactivation'))
	{
		$db->drop_column("awaitingactivation", "oldgroup");
	}

	if($db->field_exists('status', 'forums'))
	{
		$db->drop_column("forums", "status");
	}

	if($db->field_exists('posthash', 'posts'))
	{
		$db->drop_column("posts", "posthash");
	}

	if($db->field_exists('isdefault', 'templategroups'))
	{
		$db->drop_column("templategroups", "isdefault");
	}

	if($db->table_exists('reportedposts'))
	{
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
	}

	if($db->field_exists('warnings', 'promotions'))
	{
		$db->drop_column("promotions", "warnings");
	}

	if($db->field_exists('warningstype', 'promotions'))
	{
		$db->drop_column("promotions", "warningstype");
	}

	if($db->field_exists('useragent', 'adminsessions'))
	{
		$db->drop_column("adminsessions", "useragent");
	}

	if($db->field_exists('deletedthreads', 'forums'))
	{
		$db->drop_column("forums", "deletedthreads");
	}

	if($db->field_exists('deletedposts', 'forums'))
	{
		$db->drop_column("forums", "deletedposts");
	}

	if($db->field_exists('threads', 'promotions'))
	{
		$db->drop_column('promotions', 'threads');
	}

	if($db->field_exists('threadtype', 'promotions'))
	{
		$db->drop_column('promotions', 'threadtype');
	}

	if($db->field_exists('online', 'promotions'))
	{
		$db->drop_column('promotions', 'online');
	}

	if($db->field_exists('onlinetype', 'promotions'))
	{
		$db->drop_column('promotions', 'onlinetype');
	}

	if($db->field_exists('modposts', 'forums'))
	{
		$db->drop_column("forums", "modposts");
	}

	if($db->field_exists('modthreads', 'forums'))
	{
		$db->drop_column("forums", "modthreads");
	}

	if($db->field_exists('mod_edit_posts', 'forums'))
	{
		$db->drop_column("forums", "mod_edit_posts");
	}

	if($db->field_exists('modattachments', 'forums'))
	{
		$db->drop_column("forums", "modattachments");
	}

	// Avoid complex convert coding...
	if($db->field_exists('hidden', 'profilefields'))
	{
		$db->update_query('profilefields', array('hidden' => 2), 'hidden=1');
		$db->update_query('profilefields', array('hidden' => 1), 'hidden=0');
		$db->update_query('profilefields', array('hidden' => 0), 'hidden=2');

		switch($db->type)
		{
			case "pgsql":
				$db->rename_column("profilefields", "hidden", "profile", "smallint", "set", "'0'");
				break;
			default:
				$db->rename_column("profilefields", "hidden", "profile", "tinyint(1) NOT NULL default '0'");
				break;
		}
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("usergroups", "modposts", "smallint NOT NULL default '0' AFTER canratethreads");
			$db->add_column("usergroups", "modthreads", "smallint NOT NULL default '0' AFTER modposts");
			$db->add_column("usergroups", "mod_edit_posts", "smallint NOT NULL default '0' AFTER modthreads");
			$db->add_column("usergroups", "modattachments", "smallint NOT NULL default '0' AFTER mod_edit_posts");
			$db->add_column("profilefields", "regex", "text NOT NULL default ''");
			$db->add_column("profilefields", "allowhtml", "smallint NOT NULL default '0'");
			$db->add_column("profilefields", "allowmycode", "smallint NOT NULL default '0'");
			$db->add_column("profilefields", "allowsmilies", "smallint NOT NULL default '0'");
			$db->add_column("profilefields", "allowimgcode", "smallint NOT NULL default '0'");
			$db->add_column("profilefields", "allowvideocode", "smallint NOT NULL default '0'");
			$db->add_column("profilefields", "viewableby", "text NOT NULL default ''");
			$db->add_column("profilefields", "editableby", "text NOT NULL default ''");
			$db->add_column("templategroups", "isdefault", "smallint NOT NULL default '0'");
			if($db->table_exists('reportedposts'))
			{
				$db->add_column("reportedposts", "type", "varchar(50) NOT NULL default ''");
				$db->add_column("reportedposts", "reports", "int NOT NULL default '0'");
				$db->add_column("reportedposts", "reporters", "text NOT NULL default ''");
				$db->add_column("reportedposts", "lastreport", "bigint NOT NULL default '0'");
			}
			$db->add_column("promotions", "threads", "int NOT NULL default '0' AFTER posttype");
			$db->add_column("promotions", "threadtype", "varchar(2) NOT NULL default '' AFTER threads");
			$db->add_column("promotions", "warnings", "int NOT NULL default '0' AFTER referralstype");
			$db->add_column("promotions", "warningstype", "varchar(2) NOT NULL default '' AFTER warnings");
			$db->add_column("promotions", "online", "int NOT NULL default '0' AFTER warningstype");
			$db->add_column("promotions", "onlinetype", "varchar(20) NOT NULL default '' AFTER online");
			$db->add_column("adminsessions", "useragent", "varchar(100) NOT NULL default ''");
			$db->add_column("forums", "deletedthreads", "int NOT NULL default '0' AFTER unapprovedposts");
			$db->add_column("forums", "deletedposts", "int NOT NULL default '0' AFTER deletedthreads");
			break;
		case "sqlite":
			$db->add_column("usergroups", "modposts", "tinyint(1) NOT NULL default '0' AFTER canratethreads");
			$db->add_column("usergroups", "modthreads", "tinyint(1) NOT NULL default '0' AFTER modposts");
			$db->add_column("usergroups", "mod_edit_posts", "tinyint(1) NOT NULL default '0' AFTER modthreads");
			$db->add_column("usergroups", "modattachments", "tinyint(1) NOT NULL default '0' AFTER mod_edit_posts");
			$db->add_column("profilefields", "regex", "text NOT NULL default ''");
			$db->add_column("profilefields", "allowhtml", "tinyint(1) NOT NULL default '0'");
			$db->add_column("profilefields", "allowmycode", "tinyint(1) NOT NULL default '0'");
			$db->add_column("profilefields", "allowsmilies", "tinyint(1) NOT NULL default '0'");
			$db->add_column("profilefields", "allowimgcode", "tinyint(1) NOT NULL default '0'");
			$db->add_column("profilefields", "allowvideocode", "tinyint(1) NOT NULL default '0'");
			$db->add_column("profilefields", "viewableby", "text NOT NULL default ''");
			$db->add_column("profilefields", "editableby", "text NOT NULL default ''");
			$db->add_column("templategroups", "isdefault", "tinyint(1) NOT NULL default '0'");
			if($db->table_exists('reportedposts'))
			{
				$db->add_column("reportedposts", "type", "varchar(50) NOT NULL default ''");
				$db->add_column("reportedposts", "reports", "int NOT NULL default '0'");
				$db->add_column("reportedposts", "reporters", "text NOT NULL default ''");
				$db->add_column("reportedposts", "lastreport", "bigint NOT NULL default '0'");
			}
			$db->add_column("promotions", "warnings", "int NOT NULL default '0' AFTER referralstype");
			$db->add_column("promotions", "warningstype", "varchar(2) NOT NULL default '' AFTER warnings");
			$db->add_column("adminsessions", "useragent", "varchar(100) NOT NULL default ''");
			$db->add_column("forums", "deletedthreads", "int NOT NULL default '0' AFTER unapprovedposts");
			$db->add_column("forums", "deletedposts", "int NOT NULL default '0' AFTER deletedthreads");
			break;
		default:
			$db->add_column("usergroups", "modposts", "tinyint(1) NOT NULL default '0' AFTER canratethreads");
			$db->add_column("usergroups", "modthreads", "tinyint(1) NOT NULL default '0' AFTER modposts");
			$db->add_column("usergroups", "mod_edit_posts", "tinyint(1) NOT NULL default '0' AFTER modthreads");
			$db->add_column("usergroups", "modattachments", "tinyint(1) NOT NULL default '0' AFTER mod_edit_posts");
			$db->add_column("profilefields", "regex", "text NOT NULL");
			$db->add_column("profilefields", "allowhtml", "tinyint(1) NOT NULL default '0'");
			$db->add_column("profilefields", "allowmycode", "tinyint(1) NOT NULL default '0'");
			$db->add_column("profilefields", "allowsmilies", "tinyint(1) NOT NULL default '0'");
			$db->add_column("profilefields", "allowimgcode", "tinyint(1) NOT NULL default '0'");
			$db->add_column("profilefields", "allowvideocode", "tinyint(1) NOT NULL default '0'");
			$db->add_column("profilefields", "viewableby", "text NOT NULL");
			$db->add_column("profilefields", "editableby", "text NOT NULL");
			$db->add_column("templategroups", "isdefault", "tinyint(1) NOT NULL default '0'");
			if($db->table_exists('reportedposts'))
			{
				$db->add_column("reportedposts", "type", "varchar(50) NOT NULL default ''");
				$db->add_column("reportedposts", "reports", "int unsigned NOT NULL default '0'");
				$db->add_column("reportedposts", "reporters", "text NOT NULL");
				$db->add_column("reportedposts", "lastreport", "bigint(30) NOT NULL default '0'");
			}
			$db->add_column("promotions", "threads", "int NOT NULL default '0' AFTER posttype");
			$db->add_column("promotions", "threadtype", "char(2) NOT NULL default '' AFTER threads");
			$db->add_column("promotions", "warnings", "int NOT NULL default '0' AFTER referralstype");
			$db->add_column("promotions", "warningstype", "char(2) NOT NULL default '' AFTER warnings");
			$db->add_column("promotions", "online", "int NOT NULL default '0' AFTER warningstype");
			$db->add_column("promotions", "onlinetype", "varchar(20) NOT NULL default '' AFTER online");
			$db->add_column("adminsessions", "useragent", "varchar(100) NOT NULL default ''");
			$db->add_column("forums", "deletedthreads", "int(10) NOT NULL default '0' AFTER unapprovedposts");
			$db->add_column("forums", "deletedposts", "int(10) NOT NULL default '0' AFTER deletedthreads");
			break;
	}

	$db->update_query('profilefields', array('viewableby' => '-1', 'editableby' => '-1'));

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("30_dbchanges2");
}

function upgrade30_dbchanges2()
{
	global $cache, $output, $mybb, $db;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->field_exists('ipaddress', 'privatemessages'))
	{
		$db->drop_column('privatemessages', 'ipaddress');
	}

	if($db->field_exists('canonlyreplyownthreads', 'forumpermissions'))
	{
		$db->drop_column("forumpermissions", "canonlyreplyownthreads");
	}

	if($db->field_exists('modposts', 'forumpermissions'))
	{
		$db->drop_column("forumpermissions", "modposts");
	}

	if($db->field_exists('modthreads', 'forumpermissions'))
	{
		$db->drop_column("forumpermissions", "modthreads");
	}

	if($db->field_exists('mod_edit_posts', 'forumpermissions'))
	{
		$db->drop_column("forumpermissions", "mod_edit_posts");
	}

	if($db->field_exists('modattachments', 'forumpermissions'))
	{
		$db->drop_column("forumpermissions", "modattachments");
	}

	if($db->field_exists('canbereported', 'usergroups'))
	{
		$db->drop_column('usergroups', 'canbereported');
	}

	if($db->field_exists('edittimelimit', 'usergroups'))
	{
		$db->drop_column("usergroups", "edittimelimit");
	}

	if($db->field_exists('maxposts', 'usergroups'))
	{
		$db->drop_column("usergroups", "maxposts");
	}

	if($db->field_exists('showmemberlist', 'usergroups'))
	{
		$db->drop_column("usergroups", "showmemberlist");
	}

	if($db->field_exists('canviewboardclosed', 'usergroups'))
	{
		$db->drop_column("usergroups", "canviewboardclosed");
	}

	if($db->field_exists('deletedposts', 'threads'))
	{
		$db->drop_column("threads", "deletedposts");
	}

	if($db->field_exists('used', 'captcha'))
	{
		$db->drop_column("captcha", "used");
	}

	if($db->field_exists('editreason', 'posts'))
	{
		$db->drop_column("posts", "editreason");
	}

	if($db->field_exists('usethreadcounts', 'forums'))
	{
		$db->drop_column("forums", "usethreadcounts");
	}

	if($db->field_exists('requireprefix', 'forums'))
	{
		$db->drop_column("forums", "requireprefix");
	}

	if($db->field_exists('threadnum', 'users'))
	{
		$db->drop_column("users", "threadnum");
	}

	if($db->field_exists('canchangewebsite', 'usergroups'))
	{
		$db->drop_column("usergroups", "canchangewebsite");
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("forumpermissions", "canonlyreplyownthreads", "smallint NOT NULL default '0' AFTER canpostreplys");
			$db->add_column("forumpermissions", "modposts", "smallint NOT NULL default '0' AFTER caneditattachments");
			$db->add_column("forumpermissions", "modthreads", "smallint NOT NULL default '0' AFTER modposts");
			$db->add_column("forumpermissions", "mod_edit_posts", "smallint NOT NULL default '0' AFTER modthreads");
			$db->add_column("forumpermissions", "modattachments", "smallint NOT NULL default '0' AFTER mod_edit_posts");
			$db->add_column("usergroups", "canbereported", "smallint NOT NULL default '0' AFTER canchangename");
			$db->add_column("usergroups", "canchangewebsite", "smallint NOT NULL default '1' AFTER canbereported");
			$db->add_column("usergroups", "edittimelimit", "int NOT NULL default '0'");
			$db->add_column("usergroups", "maxposts", "int NOT NULL default '0'");
			$db->add_column("usergroups", "showmemberlist", "smallint NOT NULL default '1'");
			$db->add_column("usergroups", "canviewboardclosed", "smallint NOT NULL default '0' AFTER candlattachments");
			$db->add_column("threads", "deletedposts", "int NOT NULL default '0' AFTER unapprovedposts");
			$db->add_column("captcha", "used", "smallint NOT NULL default '0'");
			$db->add_column("posts", "editreason", "varchar(150) NOT NULL default '' AFTER edittime");
			$db->add_column("forums", "usethreadcounts", "smallint NOT NULL default '0' AFTER usepostcounts");
			$db->add_column("forums", "requireprefix", "smallint NOT NULL default '0' AFTER usethreadcounts");
			$db->add_column("users", "threadnum", "int NOT NULL default '0' AFTER postnum");
			break;
		default:
			$db->add_column("forumpermissions", "canonlyreplyownthreads", "tinyint(1) NOT NULL default '0' AFTER canpostreplys");
			$db->add_column("forumpermissions", "modposts", "tinyint(1) NOT NULL default '0' AFTER caneditattachments");
			$db->add_column("forumpermissions", "modthreads", "tinyint(1) NOT NULL default '0' AFTER modposts");
			$db->add_column("forumpermissions", "mod_edit_posts", "tinyint(1) NOT NULL default '0' AFTER modthreads");
			$db->add_column("forumpermissions", "modattachments", "tinyint(1) NOT NULL default '0' AFTER mod_edit_posts");
			$db->add_column("usergroups", "canbereported", "tinyint(1) NOT NULL default '0' AFTER canchangename");
			$db->add_column("usergroups", "canchangewebsite", "tinyint(1) NOT NULL default '1' AFTER canbereported");
			$db->add_column("usergroups", "edittimelimit", "int(4) NOT NULL default '0'");
			$db->add_column("usergroups", "maxposts", "int(4) NOT NULL default '0'");
			$db->add_column("usergroups", "showmemberlist", "tinyint(1) NOT NULL default '1'");
			$db->add_column("usergroups", "canviewboardclosed", "tinyint(1) NOT NULL default '0' AFTER candlattachments");
			$db->add_column("threads", "deletedposts", "int(10) NOT NULL default '0' AFTER unapprovedposts");
			$db->add_column("captcha", "used", "tinyint(1) NOT NULL default '0'");
			$db->add_column("posts", "editreason", "varchar(150) NOT NULL default '' AFTER edittime");
			$db->add_column("forums", "usethreadcounts", "tinyint(1) NOT NULL default '0' AFTER usepostcounts");
			$db->add_column("forums", "requireprefix", "tinyint(1) NOT NULL default '0' AFTER usethreadcounts");
			$db->add_column("users", "threadnum", "int(10) NOT NULL default '0' AFTER postnum");
			break;
	}

	$db->update_query('forums', array('usethreadcounts' => 1), 'usepostcounts = 1');

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("30_dbchanges3");
}

function upgrade30_dbchanges3()
{
	global $cache, $output, $mybb, $db;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->field_exists('cansoftdeleteposts', 'moderators'))
	{
		$db->drop_column('moderators', 'cansoftdeleteposts');
	}

	if($db->field_exists('canrestoreposts', 'moderators'))
	{
		$db->drop_column("moderators", "canrestoreposts");
	}

	if($db->field_exists('cansoftdeletethreads', 'moderators'))
	{
		$db->drop_column('moderators', 'cansoftdeletethreads');
	}

	if($db->field_exists('canrestorethreads', 'moderators'))
	{
		$db->drop_column("moderators", "canrestorethreads");
	}

	if($db->field_exists('candeletethreads', 'moderators'))
	{
		$db->drop_column("moderators", "candeletethreads");
	}

	if($db->field_exists('canviewunapprove', 'moderators'))
	{
		$db->drop_column("moderators", "canviewunapprove");
	}

	if($db->field_exists('canviewdeleted', 'moderators'))
	{
		$db->drop_column("moderators", "canviewdeleted");
	}

	if($db->field_exists('canstickunstickthreads', 'moderators'))
	{
		$db->drop_column("moderators", "canstickunstickthreads");
	}

	if($db->field_exists('canapproveunapprovethreads', 'moderators'))
	{
		$db->drop_column("moderators", "canapproveunapprovethreads");
	}

	if($db->field_exists('canapproveunapproveposts', 'moderators'))
	{
		$db->drop_column("moderators", "canapproveunapproveposts");
	}

	if($db->field_exists('canapproveunapproveattachs', 'moderators'))
	{
		$db->drop_column("moderators", "canapproveunapproveattachs");
	}

	if($db->field_exists('canmanagepolls', 'moderators'))
	{
		$db->drop_column("moderators", "canmanagepolls");
	}

	if($db->field_exists('canpostclosedthreads', 'moderators'))
	{
		$db->drop_column("moderators", "canpostclosedthreads");
	}

	if($db->field_exists('canmanageannouncements', 'moderators'))
	{
		$db->drop_column("moderators", "canmanageannouncements");
	}

	if($db->field_exists('canmanagereportedposts', 'moderators'))
	{
		$db->drop_column("moderators", "canmanagereportedposts");
	}

	if($db->field_exists('canviewmodlog', 'moderators'))
	{
		$db->drop_column("moderators", "canviewmodlog");
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("moderators", "cansoftdeleteposts", "smallint NOT NULL default '0' AFTER caneditposts");
			$db->add_column("moderators", "canrestoreposts", "smallint NOT NULL default '0' AFTER cansoftdeleteposts");
			$db->add_column("moderators", "cansoftdeletethreads", "smallint NOT NULL default '0' AFTER candeleteposts");
			$db->add_column("moderators", "canrestorethreads", "smallint NOT NULL default '0' AFTER cansoftdeletethreads");
			$db->add_column("moderators", "candeletethreads", "smallint NOT NULL default '0' AFTER canrestorethreads");
			$db->add_column("moderators", "canviewunapprove", "smallint NOT NULL default '0' AFTER canviewips");
			$db->add_column("moderators", "canviewdeleted", "smallint NOT NULL default '0' AFTER canviewunapprove");
			$db->add_column("moderators", "canstickunstickthreads", "smallint NOT NULL default '0' AFTER canopenclosethreads");
			$db->add_column("moderators", "canapproveunapprovethreads", "smallint NOT NULL default '0' AFTER canstickunstickthreads");
			$db->add_column("moderators", "canapproveunapproveposts", "smallint NOT NULL default '0' AFTER canapproveunapprovethreads");
			$db->add_column("moderators", "canapproveunapproveattachs", "smallint NOT NULL default '0' AFTER canapproveunapproveposts");
			$db->add_column("moderators", "canmanagepolls", "smallint NOT NULL default '0' AFTER canmanagethreads");
			$db->add_column("moderators", "canpostclosedthreads", "smallint NOT NULL default '0' AFTER canmanagepolls");
			$db->add_column("moderators", "canmanageannouncements", "smallint NOT NULL default '0' AFTER canusecustomtools");
			$db->add_column("moderators", "canmanagereportedposts", "smallint NOT NULL default '0' AFTER canmanageannouncements");
			$db->add_column("moderators", "canviewmodlog", "smallint NOT NULL default '0' AFTER canmanagereportedposts");
			break;
		default:
			$db->add_column("moderators", "cansoftdeleteposts", "tinyint(1) NOT NULL default '0' AFTER caneditposts");
			$db->add_column("moderators", "canrestoreposts", "tinyint(1) NOT NULL default '0' AFTER cansoftdeleteposts");
			$db->add_column("moderators", "cansoftdeletethreads", "tinyint(1) NOT NULL default '0' AFTER candeleteposts");
			$db->add_column("moderators", "canrestorethreads", "tinyint(1) NOT NULL default '0' AFTER cansoftdeletethreads");
			$db->add_column("moderators", "candeletethreads", "tinyint(1) NOT NULL default '0' AFTER canrestorethreads");
			$db->add_column("moderators", "canviewunapprove", "tinyint(1) NOT NULL default '0' AFTER canviewips");
			$db->add_column("moderators", "canviewdeleted", "tinyint(1) NOT NULL default '0' AFTER canviewunapprove");
			$db->add_column("moderators", "canstickunstickthreads", "tinyint(1) NOT NULL default '0' AFTER canopenclosethreads");
			$db->add_column("moderators", "canapproveunapprovethreads", "tinyint(1) NOT NULL default '0' AFTER canstickunstickthreads");
			$db->add_column("moderators", "canapproveunapproveposts", "tinyint(1) NOT NULL default '0' AFTER canapproveunapprovethreads");
			$db->add_column("moderators", "canapproveunapproveattachs", "tinyint(1) NOT NULL default '0' AFTER canapproveunapproveposts");
			$db->add_column("moderators", "canmanagepolls", "tinyint(1) NOT NULL default '0' AFTER canmanagethreads");
			$db->add_column("moderators", "canpostclosedthreads", "tinyint(1) NOT NULL default '0' AFTER canmanagepolls");
			$db->add_column("moderators", "canmanageannouncements", "tinyint(1) NOT NULL default '0' AFTER canusecustomtools");
			$db->add_column("moderators", "canmanagereportedposts", "tinyint(1) NOT NULL default '0' AFTER canmanageannouncements");
			$db->add_column("moderators", "canviewmodlog", "tinyint(1) NOT NULL default '0' AFTER canmanagereportedposts");
			break;
	}

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("30_dbchanges4");
}

function upgrade30_dbchanges4()
{
	global $cache, $output, $mybb, $db;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->field_exists('emailfloodtime', 'usergroups'))
	{
		$db->drop_column("usergroups", "emailfloodtime");
	}

	if($db->field_exists('canmanageannounce', 'usergroups'))
	{
		$db->drop_column("usergroups", "canmanageannounce");
	}

	if($db->field_exists('canmanagemodqueue', 'usergroups'))
	{
		$db->drop_column("usergroups", "canmanagemodqueue");
	}

	if($db->field_exists('canmanagereportedcontent', 'usergroups'))
	{
		$db->drop_column("usergroups", "canmanagereportedcontent");
	}

	if($db->field_exists('canviewmodlogs', 'usergroups'))
	{
		$db->drop_column("usergroups", "canviewmodlogs");
	}

	if($db->field_exists('caneditprofiles', 'usergroups'))
	{
		$db->drop_column("usergroups", "caneditprofiles");
	}

	if($db->field_exists('canbanusers', 'usergroups'))
	{
		$db->drop_column("usergroups", "canbanusers");
	}

	if($db->field_exists('canviewwarnlogs', 'usergroups'))
	{
		$db->drop_column("usergroups", "canviewwarnlogs");
	}

	if($db->field_exists('canuseipsearch', 'usergroups'))
	{
		$db->drop_column("usergroups", "canuseipsearch");
	}

	if($db->field_exists('type', 'maillogs'))
	{
		$db->drop_column("maillogs", "type");
	}

	if($db->field_exists('groups', 'modtools'))
	{
		$db->drop_column("modtools", "groups");
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("usergroups", "emailfloodtime", "int NOT NULL default '5' AFTER maxemails");
			$db->add_column("usergroups", "canmanageannounce", "smallint NOT NULL default '0' AFTER showmemberlist");
			$db->add_column("usergroups", "canmanagemodqueue", "smallint NOT NULL default '0' AFTER canmanageannounce");
			$db->add_column("usergroups", "canmanagereportedcontent", "smallint NOT NULL default '0' AFTER canmanagemodqueue");
			$db->add_column("usergroups", "canviewmodlogs", "smallint NOT NULL default '0' AFTER canmanagereportedcontent");
			$db->add_column("usergroups", "caneditprofiles", "smallint NOT NULL default '0' AFTER canviewmodlogs");
			$db->add_column("usergroups", "canbanusers", "smallint NOT NULL default '0' AFTER caneditprofiles");
			$db->add_column("usergroups", "canviewwarnlogs", "smallint NOT NULL default '0' AFTER canbanusers");
			$db->add_column("usergroups", "canuseipsearch", "smallint NOT NULL default '0' AFTER canviewwarnlogs");
			$db->add_column("maillogs", "type", "smallint NOT NULL default '0'");
			break;
		default:
			$db->add_column("usergroups", "emailfloodtime", "int(3) NOT NULL default '5' AFTER maxemails");
			$db->add_column("usergroups", "canmanageannounce", "tinyint(1) NOT NULL default '0' AFTER showmemberlist");
			$db->add_column("usergroups", "canmanagemodqueue", "tinyint(1) NOT NULL default '0' AFTER canmanageannounce");
			$db->add_column("usergroups", "canmanagereportedcontent", "tinyint(1) NOT NULL default '0' AFTER canmanagemodqueue");
			$db->add_column("usergroups", "canviewmodlogs", "tinyint(1) NOT NULL default '0' AFTER canmanagereportedcontent");
			$db->add_column("usergroups", "caneditprofiles", "tinyint(1) NOT NULL default '0' AFTER canviewmodlogs");
			$db->add_column("usergroups", "canbanusers", "tinyint(1) NOT NULL default '0' AFTER caneditprofiles");
			$db->add_column("usergroups", "canviewwarnlogs", "tinyint(1) NOT NULL default '0' AFTER canbanusers");
			$db->add_column("usergroups", "canuseipsearch", "tinyint(1) NOT NULL default '0' AFTER canviewwarnlogs");
			$db->add_column("maillogs", "type", "tinyint(1) NOT NULL default '0'");
			break;
	}

	switch($db->type)
	{
		case "sqlite":
			$db->add_column("modtools", "groups", "text NOT NULL default ''");
			break;
		default:
			$db->add_column("modtools", "groups", "text NOT NULL");
			break;
	}

	$update_array = array(
		"canmanageannounce" => 1,
		"canmanagemodqueue" => 1,
		"canmanagereportedcontent" => 1,
		"canviewmodlogs" => 1,
		"caneditprofiles" => 1,
		"canbanusers" => 1,
		"canviewwarnlogs" => 1,
		"canuseipsearch" => 1
	);
	$db->update_query("usergroups", $update_array, "canmodcp= '1'");

	$update_array = array(
		"type" => 1
	);
	$db->update_query("maillogs", $update_array, "tid= '0'");

	$update_array = array(
		"type" => 2
	);
	$db->update_query("maillogs", $update_array, "tid > '0'");

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("30_dbchanges5");
}

function upgrade30_dbchanges5()
{
	global $cache, $output, $mybb, $db;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->table_exists("questions"))
	{
		$db->drop_table("questions");
	}

	if($db->table_exists("questionsessions"))
	{
		$db->drop_table("questionsessions");
	}

	if($db->table_exists("spamlog"))
	{
		$db->drop_table("spamlog");
	}

	$collation = $db->build_create_table_collation();

	switch($db->type)
	{
		case "sqlite":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."questions (
				qid INTEGER PRIMARY KEY,
				question varchar(200) NOT NULL default '',
				answer varchar(150) NOT NULL default '',
				shown int unsigned NOT NULL default 0,
				correct int unsigned NOT NULL default 0,
				incorrect int unsigned NOT NULL default 0,
				active tinyint(1) NOT NULL default '0'
			);");
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."questionsessions (
				sid varchar(32) NOT NULL default '',
				qid int unsigned NOT NULL default '0',
				dateline int unsigned NOT NULL default '0'
			);");
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."spamlog (
				sid INTEGER PRIMARY KEY,
				username varchar(120) NOT NULL DEFAULT '',
				email varchar(220) NOT NULL DEFAULT '',
				ipaddress blob(16) NOT NULL default '',
				dateline int unsigned NOT NULL default '0',
				data TEXT NOT NULL
			);");
			break;
		case "pgsql":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."questions (
				qid serial,
				question varchar(200) NOT NULL default '',
				answer varchar(150) NOT NULL default '',
				shown int NOT NULL default 0,
				correct int NOT NULL default 0,
				incorrect int NOT NULL default 0,
				active smallint NOT NULL default '0',
				PRIMARY KEY (qid)
			);");
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."questionsessions (
				sid varchar(32) NOT NULL default '',
				qid int NOT NULL default '0',
				dateline int NOT NULL default '0',
				UNIQUE (sid)
			);");
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."spamlog (
				sid serial,
				username varchar(120) NOT NULL DEFAULT '',
				email varchar(220) NOT NULL DEFAULT '',
				ipaddress bytea NOT NULL default '',
				dateline numeric(30,0) NOT NULL default '0',
				data text NOT NULL default '',
				PRIMARY KEY (sid)
			);");
			break;
		default:
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."questions (
				qid int unsigned NOT NULL auto_increment,
				question varchar(200) NOT NULL default '',
				answer varchar(150) NOT NULL default '',
				shown int unsigned NOT NULL default 0,
				correct int unsigned NOT NULL default 0,
				incorrect int unsigned NOT NULL default 0,
				active tinyint(1) NOT NULL default '0',
				PRIMARY KEY (qid)
			) ENGINE=MyISAM{$collation}");
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."questionsessions (
				sid varchar(32) NOT NULL default '',
				qid int unsigned NOT NULL default '0',
				dateline int unsigned NOT NULL default '0',
				PRIMARY KEY (sid)
			) ENGINE=MyISAM{$collation}");
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."spamlog (
				sid int unsigned NOT NULL auto_increment,
				username varchar(120) NOT NULL DEFAULT '',
				email varchar(220) NOT NULL DEFAULT '',
				ipaddress varbinary(16) NOT NULL default '',
				dateline int unsigned NOT NULL default '0',
				data text NOT NULL,
				PRIMARY KEY (sid)
			) ENGINE=MyISAM{$collation}");
	}

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("30_dbchanges6");
}

function upgrade30_dbchanges6()
{
	global $cache, $output, $mybb, $db;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->table_exists("buddyrequests"))
	{
		$db->drop_table("buddyrequests");
	}

	$collation = $db->build_create_table_collation();

	switch($db->type)
	{
		case "pgsql":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."buddyrequests (
				 id serial,
				 uid int NOT NULL,
				 touid int NOT NULL,
				 date int NOT NULL,
				 PRIMARY KEY (id)
			);");
			break;
		case "sqlite":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."buddyrequests (
				 id INTEGER PRIMARY KEY,
				 uid bigint unsigned NOT NULL,
				 touid bigint unsigned NOT NULL,
				 date int unsigned NOT NULL
			);");
			break;
		default:
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."buddyrequests (
				 id int(10) UNSIGNED NOT NULL auto_increment,
				 uid bigint(30) UNSIGNED NOT NULL,
				 touid bigint(30) UNSIGNED NOT NULL,
				 date int(11) UNSIGNED NOT NULL,
				 KEY (uid),
				 KEY (touid),
				 PRIMARY KEY (id)
			) ENGINE=MyISAM{$collation};");
			break;
	}

	if($db->field_exists('msn', 'users'))
	{
		$db->drop_column("users", "msn");
	}

	if($db->field_exists('postbit', 'profilefields'))
	{
		$db->drop_column("profilefields", "postbit");
	}

	if($db->field_exists('skype', 'users'))
	{
		$db->drop_column("users", "skype");
	}

	if($db->field_exists('google', 'users'))
	{
		$db->drop_column("users", "google");
	}

	if($db->field_exists('cplanguage', 'adminoptions'))
	{
		$db->drop_column("adminoptions", "cplanguage");
	}

	if($db->field_exists('showimages', 'users'))
	{
		$db->drop_column("users", "showimages");
	}

	if($db->field_exists('showvideos', 'users'))
	{
		$db->drop_column("users", "showvideos");
	}

	if($db->field_exists('caninvitemembers', 'groupleaders'))
	{
		$db->drop_column("groupleaders", "caninvitemembers");
	}

	if($db->field_exists('invite', 'joinrequests'))
	{
		$db->drop_column("joinrequests", "invite");
	}

	if($db->field_exists('registration', 'profilefields'))
	{
		$db->drop_column("profilefields", "registration");
	}

	if($db->field_exists('validated', 'awaitingactivation'))
	{
		$db->drop_column("awaitingactivation", "validated");
	}

	if($db->field_exists('sourceeditor', 'users'))
	{
		$db->drop_column("users", "sourceeditor");
	}

	if($db->field_exists('buddyrequestspm', 'users'))
	{
		$db->drop_column("users", "buddyrequestspm");
	}

	if($db->field_exists('buddyrequestsauto', 'users'))
	{
		$db->drop_column("users", "buddyrequestsauto");
	}

	if($db->field_exists('ipaddress', 'privatemessages'))
	{
		$db->drop_column("privatemessages", "ipaddress");
	}

	if($db->field_exists('maxoptions', 'polls'))
	{
		$db->drop_column("polls", "maxoptions");
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("profilefields", "postbit", "smallint NOT NULL default '0' AFTER profile");
			$db->add_column("users", "skype", "varchar(75) NOT NULL default '' AFTER yahoo");
			$db->add_column("users", "google", "varchar(75) NOT NULL default '' AFTER skype");
			$db->add_column("adminoptions", "cplanguage", "varchar(50) NOT NULL default '' AFTER cpstyle");
			$db->add_column("users", "showimages", "smallint NOT NULL default '1' AFTER threadmode");
			$db->add_column("users", "showvideos", "smallint NOT NULL default '1' AFTER showimages");
			$db->add_column("users", "buddyrequestspm", "smallint NOT NULL default '1' AFTER pmnotify");
			$db->add_column("users", "buddyrequestsauto", "smallint NOT NULL default '0' AFTER buddyrequestspm");
			$db->add_column("groupleaders", "caninvitemembers", "smallint NOT NULL default '0'");
			$db->add_column("joinrequests", "invite", "smallint NOT NULL default '0'");
			$db->add_column("profilefields", "registration", "smallint NOT NULL default '0' AFTER required");
			$db->add_column("awaitingactivation", "validated", "smallint NOT NULL default '0' AFTER type");
			$db->add_column("users", "sourceeditor", "smallint NOT NULL default '0'");
			break;
		default:
			$db->add_column("profilefields", "postbit", "tinyint(1) NOT NULL default '0' AFTER profile");
			$db->add_column("users", "skype", "varchar(75) NOT NULL default '' AFTER yahoo");
			$db->add_column("users", "google", "varchar(75) NOT NULL default '' AFTER skype");
			$db->add_column("adminoptions", "cplanguage", "varchar(50) NOT NULL default '' AFTER cpstyle");
			$db->add_column("users", "showimages", "tinyint(1) NOT NULL default '1' AFTER threadmode");
			$db->add_column("users", "showvideos", "tinyint(1) NOT NULL default '1' AFTER showimages");
			$db->add_column("users", "buddyrequestspm", "tinyint(1) NOT NULL default '1' AFTER pmnotify");
			$db->add_column("users", "buddyrequestsauto", "tinyint(1) NOT NULL default '0' AFTER buddyrequestspm");
			$db->add_column("groupleaders", "caninvitemembers", "tinyint(1) NOT NULL default '0'");
			$db->add_column("joinrequests", "invite", "tinyint(1) NOT NULL default '0'");
			$db->add_column("profilefields", "registration", "tinyint(1) NOT NULL default '0' AFTER required");
			$db->add_column("awaitingactivation", "validated", "tinyint(1) NOT NULL default '0' AFTER type");
			$db->add_column("users", "sourceeditor", "tinyint(1) NOT NULL default '0'");
			break;
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("privatemessages", "ipaddress", "bytea NOT NULL default ''");
			$db->add_column("polls", "maxoptions", "smallint NOT NULL default '0'");
			break;
		case "sqlite":
			$db->add_column("privatemessages", "ipaddress", "blob(16) NOT NULL default ''");
			$db->add_column("polls", "maxoptions", "smallint NOT NULL default '0'");
			break;
		default:
			$db->add_column("privatemessages", "ipaddress", "varbinary(16) NOT NULL default ''");
			$db->add_column("polls", "maxoptions", "smallint unsigned NOT NULL default '0'");
			break;
	}

	$groups = range(1, 39);

	$sql = implode(',', $groups);
	$db->update_query("templategroups", array('isdefault' => 1), "gid IN ({$sql})");

	if($db->table_exists('reportedposts'))
	{
		$db->update_query("reportedposts", array('type' => 'post'));
	}

	$db->insert_query("questions", array('question' => 'What does 2 + 2 equal?', 'answer' => '4\nFour', 'active' => '1'));

	$query = $db->simple_select("attachtypes", "COUNT(*) as numexists", "extension='psd'");
	if($db->fetch_field($query, "numexists") == 0)
	{
		$db->insert_query("attachtypes", array('name' => "Adobe Photoshop File", 'mimetype' => 'application/x-photoshop', 'extension' => "psd", 'maxsize' => '1024', 'icon' => 'images/attachtypes/psd.png'));
	}
	// SQLite... As we modify tables below we need to close all cursors before...
	if($db->type == "sqlite")
	{
		$query->closeCursor();
	}

	$query = $db->simple_select("templategroups", "COUNT(*) as numexists", "prefix='video'");
	if($db->fetch_field($query, "numexists") == 0)
	{
		$db->insert_query("templategroups", array('prefix' => 'video', 'title' => '<lang:group_video>', 'isdefault' => '1'));
	}
	// SQLite... As we modify tables below we need to close all cursors before...
	if($db->type == "sqlite")
	{
		$query->closeCursor();
	}

	$query = $db->simple_select("templategroups", "COUNT(*) as numexists", "prefix='php'");
	if($db->fetch_field($query, "numexists") != 0)
	{
		$db->update_query("templategroups", array('prefix' => 'announcement', 'title' => '<lang:group_announcement>'), "prefix='php'");
	}
	// SQLite... As we modify tables below we need to close all cursors before...
	if($db->type == "sqlite")
	{
		$query->closeCursor();
	}

	$query = $db->simple_select("templategroups", "COUNT(*) as numexists", "prefix='redirect'");
	if($db->fetch_field($query, "numexists") != 0)
	{
		$db->update_query("templategroups", array('prefix' => 'posticons', 'title' => '<lang:group_posticons>'), "prefix='redirect'");
	}
	// SQLite... As we modify tables below we need to close all cursors before...
	if($db->type == "sqlite")
	{
		$query->closeCursor();
	}

	// Sync usergroups with canbereported; no moderators or banned groups
	echo "<p>Updating usergroup permissions...</p>";
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

	if(!empty($groups))
	{
		$usergroups = implode(',', $groups);
		$db->update_query('usergroups', array('canbereported' => 1), "gid IN ({$usergroups})");
	}

	$db->update_query('usergroups', array('canviewboardclosed' => 1), 'cancp = 1');

	if($db->table_exists('reportedposts'))
	{
		if($db->field_exists("pid", "reportedposts") && !$db->field_exists("id", "reportedposts"))
		{
			switch($db->type)
			{
				case "pgsql":
					$db->rename_column("reportedposts", "pid", "id", "int", true, "'0'");
					break;
				default:
					$db->rename_column("reportedposts", "pid", "id", "int unsigned NOT NULL default '0'");
			}
		}

		if($db->field_exists("tid", "reportedposts") && !$db->field_exists("id2", "reportedposts"))
		{
			switch($db->type)
			{
				case "pgsql":
					$db->rename_column("reportedposts", "tid", "id2", "int", true, "'0'");
					break;
				default:
					$db->rename_column("reportedposts", "tid", "id2", "int unsigned NOT NULL default '0'");
			}
		}

		if($db->field_exists("fid", "reportedposts") && !$db->field_exists("id3", "reportedposts"))
		{
			switch($db->type)
			{
				case "pgsql":
					$db->rename_column("reportedposts", "fid", "id3", "int", true, "'0'");
					break;
				default:
					$db->rename_column("reportedposts", "fid", "id3", "int unsigned NOT NULL default '0'");
			}
		}
	}

	if($db->table_exists('reportedposts'))
	{
		if($db->table_exists("reportedcontent"))
		{
			$db->drop_table("reportedcontent");
		}

		$db->rename_table("reportedposts", "reportedcontent");

		$cache->delete('reportedposts');
	}

	$db->update_query("settings", array('optionscode' => 'select\r\n0=No CAPTCHA\r\n1=MyBB Default CAPTCHA\r\n2=reCAPTCHA\r\n3=Are You a Human'), "name='captchaimage'");
	$db->update_query("settings", array('optionscode' => 'select\r\ninstant=Instant Activation\r\nverify=Send Email Verification\r\nrandompass=Send Random Password\r\nadmin=Administrator Activation\r\nboth=Email Verification & Administrator Activation'), "name='regtype'");
	$db->update_query("settings", array('optionscode' => $db->escape_string('php
<select name=\"upsetting[{$setting[\'name\']}]\">
<option value=\"-12\" ".($setting[\'value\'] == -12?"selected=\"selected\"":"").">GMT -12:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -12).")</option>
<option value=\"-11\" ".($setting[\'value\'] == -11?"selected=\"selected\"":"").">GMT -11:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -11).")</option>
<option value=\"-10\" ".($setting[\'value\'] == -10?"selected=\"selected\"":"").">GMT -10:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -10).")</option>
<option value=\"-9.5\" ".($setting[\'value\'] == -9.5?"selected=\"selected\"":"").">GMT -9:30 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -9.5).")</option>
<option value=\"-9\" ".($setting[\'value\'] == -9?"selected=\"selected\"":"").">GMT -9:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -9).")</option>
<option value=\"-8\" ".($setting[\'value\'] == -8?"selected=\"selected\"":"").">GMT -8:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -8).")</option>
<option value=\"-7\" ".($setting[\'value\'] == -7?"selected=\"selected\"":"").">GMT -7:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -7).")</option>
<option value=\"-6\" ".($setting[\'value\'] == -6?"selected=\"selected\"":"").">GMT -6:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -6).")</option>
<option value=\"-5\" ".($setting[\'value\'] == -5?"selected=\"selected\"":"").">GMT -5:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -5).")</option>
<option value=\"-4.5\" ".($setting[\'value\'] == -4.5?"selected=\"selected\"":"").">GMT -4:30 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -4.5).")</option>
<option value=\"-4\" ".($setting[\'value\'] == -4?"selected=\"selected\"":"").">GMT -4:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -4).")</option>
<option value=\"-3.5\" ".($setting[\'value\'] == -3.5?"selected=\"selected\"":"").">GMT -3:30 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -3.5).")</option>
<option value=\"-3\" ".($setting[\'value\'] == -3?"selected=\"selected\"":"").">GMT -3:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -3).")</option>
<option value=\"-2\" ".($setting[\'value\'] == -2?"selected=\"selected\"":"").">GMT -2:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -2).")</option>
<option value=\"-1\" ".($setting[\'value\'] == -1?"selected=\"selected\"":"").">GMT -1:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, -1).")</option>
<option value=\"0\" ".($setting[\'value\'] == 0?"selected=\"selected\"":"").">GMT (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 0).")</option>
<option value=\"+1\" ".($setting[\'value\'] == 1?"selected=\"selected\"":"").">GMT +1:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 1).")</option>
<option value=\"+2\" ".($setting[\'value\'] == 2?"selected=\"selected\"":"").">GMT +2:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 2).")</option>
<option value=\"+3\" ".($setting[\'value\'] == 3?"selected=\"selected\"":"").">GMT +3:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 3).")</option>
<option value=\"+3.5\" ".($setting[\'value\'] == 3.5?"selected=\"selected\"":"").">GMT +3:30 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 3.5).")</option>
<option value=\"+4\" ".($setting[\'value\'] == 4?"selected=\"selected\"":"").">GMT +4:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 4).")</option>
<option value=\"+4.5\" ".($setting[\'value\'] == 4.5?"selected=\"selected\"":"").">GMT +4:30 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 4.5).")</option>
<option value=\"+5\" ".($setting[\'value\'] == 5?"selected=\"selected\"":"").">GMT +5:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 5).")</option>
<option value=\"+5.5\" ".($setting[\'value\'] == 5.5?"selected=\"selected\"":"").">GMT +5:30 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 5.5).")</option>
<option value=\"+5.75\" ".($setting[\'value\'] == 5.75?"selected=\"selected\"":"").">GMT +5:45 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 5.75).")</option>
<option value=\"+6\" ".($setting[\'value\'] == 6?"selected=\"selected\"":"").">GMT +6:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 6).")</option>
<option value=\"+6.5\" ".($setting[\'value\'] == 6.5?"selected=\"selected\"":"").">GMT +6:30 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 6.5).")</option>
<option value=\"+7\" ".($setting[\'value\'] == 7?"selected=\"selected\"":"").">GMT +7:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 7).")</option>
<option value=\"+8\" ".($setting[\'value\'] == 8?"selected=\"selected\"":"").">GMT +8:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 8).")</option>
<option value=\"+9\" ".($setting[\'value\'] == 9?"selected=\"selected\"":"").">GMT +9:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 9).")</option>
<option value=\"+9.5\" ".($setting[\'value\'] == 9.5?"selected=\"selected\"":"").">GMT +9:30 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 9.5).")</option>
<option value=\"+10\" ".($setting[\'value\'] == 10?"selected=\"selected\"":"").">GMT +10:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 10).")</option>
<option value=\"+10.5\" ".($setting[\'value\'] == 10.5?"selected=\"selected\"":"").">GMT +10:30 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 10.5).")</option>
<option value=\"+11\" ".($setting[\'value\'] == 11?"selected=\"selected\"":"").">GMT +11:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 11).")</option>
<option value=\"+11.5\" ".($setting[\'value\'] == 11.5?"selected=\"selected\"":"").">GMT +11:30 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 11.5).")</option>
<option value=\"+12\" ".($setting[\'value\'] == 12?"selected=\"selected\"":"").">GMT +12:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 12).")</option>
<option value=\"+12.75\" ".($setting[\'value\'] == 12.75?"selected=\"selected\"":"").">GMT +12:45 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 12.75).")</option>
<option value=\"+13\" ".($setting[\'value\'] == 13?"selected=\"selected\"":"").">GMT +13:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 13).")</option>
<option value=\"+14\" ".($setting[\'value\'] == 14?"selected=\"selected\"":"").">GMT +14:00 Hours (".my_date($mybb->settings[\'timeformat\'], TIME_NOW, 14).")</option>
</select>')), "name='timezoneoffset'");

	// Update tasks
	$added_tasks = sync_tasks();

	// For the version check task, set a random date and hour (so all MyBB installs don't query mybb.com all at the same time)
	$update_array = array(
		'hour' => rand(0, 23),
		'weekday' => rand(0, 6)
	);

	$db->update_query("tasks", $update_array, "file = 'versioncheck'");

	echo "<p>Added {$added_tasks} new tasks.</p>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("30_threadcount");
}

function upgrade30_threadcount()
{
	global $db, $output;

	$output->print_header("Counting user thread count");

	if(!$_POST['theadspage'])
	{
		$threads = 500;
	}
	else
	{
		$threads = (int)$_POST['theadspage'];
	}

	if($_POST['threadstart'])
	{
		$startat = (int)$_POST['threadstart'];
		$upper = $startat+$threads;
		$lower = $startat;
	}
	else
	{
		$startat = 0;
		$upper = $threads;
		$lower = 0;
	}

	$query = $db->simple_select("users", "COUNT(uid) AS usercount");
	$cnt = $db->fetch_array($query);

	if($upper > $cnt['usercount'])
	{
		$upper = $cnt['usercount'];
	}

	echo "<p>Counting thread count of user #{$lower} to #{$upper} ({$cnt['usercount']} Total)</p>";
	flush();

	$threadnum = false;

	$query = $db->simple_select("users", "threadnum, uid", "", array('limit_start' => $lower, 'limit' => $threads));
	while($thread = $db->fetch_array($query))
	{
		$query2 = $db->simple_select("threads", "COUNT(tid) AS thread_count", "uid='{$thread['uid']}' AND visible = 1");
		$num_threads = $db->fetch_field($query2, "thread_count");

		$db->update_query("users", array('threadnum' => $num_threads), "uid = '{$thread['uid']}'");

		$threadnum = true;
	}

	$remaining = $upper-$cnt['usercount'];
	if($remaining && $threadnum)
	{
		$nextact = "30_threadcount";
		$startat = $startat+$threads;
		$contents = "<p><input type=\"hidden\" name=\"theadspage\" value=\"$threads\" /><input type=\"hidden\" name=\"threadstart\" value=\"$startat\" />Done. Click Next to move on to the next set of thread counts.</p>";
	}
	else
	{
		$nextact = "30_dbchanges_optimize1";
		$contents = "<p>Done</p><p>All users have had their thread count counted. Click next to continue.</p>";
	}
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer($nextact);
}

function upgrade30_dbchanges_optimize1()
{
	global $output, $mybb, $db;

	$output->print_header("Optimizing Database");

	echo "<p>Performing necessary optimization queries...</p>";
	flush();

	switch($db->type)
	{
		case "pgsql":
			$db->modify_column("adminoptions", "loginattempts", "smallint", "set", "'0'");
			$db->modify_column("adminviews", "perpage", "smallint", "set", "'0'");
			$db->modify_column("calendars", "disporder", "smallint", "set", "'0'");
			$db->modify_column("calendars", "eventlimit", "smallint", "set", "'0'");
			$db->modify_column("events", "timezone", "varchar(5)", "set", "''");
			$db->modify_column("forums", "lastposttid", "int", "set", "'0'");
			$db->modify_column("mailerrors", "smtpcode", "smallint", "set", "'0'");
			$db->modify_column("maillogs", "touid", "int", "set", "'0'");
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."polls ALTER COLUMN numvotes DROP DEFAULT"); // We need to drop the default first as PostgreSQL can't cast default values
			$db->modify_column("polls", "numvotes", "int USING (trim(numvotes)::int)", "set", "'0'");
			$db->modify_column("profilefields", "postnum", "smallint", "set", "'0'");
			$db->modify_column("reputation", "reputation", "smallint", "set", "'0'");
			$db->modify_column("spiders", "theme", "smallint", "set", "'0'");
			$db->modify_column("spiders", "usergroup", "smallint", "set", "'0'");
			$db->modify_column("templates", "sid", "smallint", "set", "'0'");
			$db->modify_column("themestylesheets", "tid", "smallint", "set", "'0'");
			$db->modify_column("usergroups", "canusesigxposts", "smallint", "set", "'0'");
			$db->modify_column("users", "timezone", "varchar(5)", "set", "''");
			$db->modify_column("users", "reputation", "int", "set", "'0'");
			$db->modify_column("warninglevels", "percentage", "smallint", "set", "'0'");
			$db->modify_column("warningtypes", "points", "smallint", "set", "'0'");
			$db->modify_column("warnings", "points", "smallint", "set", "'0'");
			break;
		case "sqlite":
			$db->modify_column("adminoptions", "loginattempts", "smallint NOT NULL default '0'");
			$db->modify_column("adminviews", "perpage", "smallint NOT NULL default '0'");
			$db->modify_column("calendars", "disporder", "smallint NOT NULL default '0'");
			$db->modify_column("calendars", "eventlimit", "smallint NOT NULL default '0'");
			$db->modify_column("events", "timezone", "varchar(5) NOT NULL default ''");
			$db->modify_column("forums", "lastposttid", "int NOT NULL default '0'");
			$db->modify_column("mailerrors", "smtpcode", "smallint NOT NULL default '0'");
			$db->modify_column("maillogs", "touid", "int NOT NULL default '0'");
			$db->modify_column("polls", "numvotes", "int NOT NULL default '0'");
			$db->modify_column("profilefields", "postnum", "smallint NOT NULL default '0'");
			$db->modify_column("reputation", "reputation", "smallint NOT NULL default '0'");
			$db->modify_column("spiders", "theme", "smallint NOT NULL default '0'");
			$db->modify_column("spiders", "usergroup", "smallint NOT NULL default '0'");
			$db->modify_column("templates", "sid", "smallint NOT NULL default '0'");
			$db->modify_column("themestylesheets", "tid", "smallint NOT NULL default '0'");
			$db->modify_column("usergroups", "canusesigxposts", "smallint NOT NULL default '0'");
			$db->modify_column("users", "timezone", "varchar(5) NOT NULL default ''");
			$db->modify_column("users", "reputation", "int NOT NULL default '0'");
			$db->modify_column("warninglevels", "percentage", "smallint NOT NULL default '0'");
			$db->modify_column("warningtypes", "points", "smallint NOT NULL default '0'");
			$db->modify_column("warnings", "points", "smallint NOT NULL default '0'");
			break;
		default:
			$db->modify_column("adminoptions", "loginattempts", "smallint unsigned NOT NULL default '0'");
			$db->modify_column("adminviews", "perpage", "smallint(4) NOT NULL default '0'");
			$db->modify_column("calendars", "disporder", "smallint unsigned NOT NULL default '0'");
			$db->modify_column("calendars", "eventlimit", "smallint(3) NOT NULL default '0'");
			$db->modify_column("events", "timezone", "varchar(5) NOT NULL default ''");
			$db->modify_column("forums", "lastposttid", "int unsigned NOT NULL default '0'");
			$db->modify_column("mailerrors", "smtpcode", "smallint(5) unsigned NOT NULL default '0'");
			$db->modify_column("maillogs", "touid", "int unsigned NOT NULL default '0'");
			$db->modify_column("polls", "numvotes", "int unsigned NOT NULL default '0'");
			$db->modify_column("profilefields", "postnum", "smallint unsigned NOT NULL default '0'");
			$db->modify_column("reputation", "reputation", "smallint NOT NULL default '0'");
			$db->modify_column("spiders", "theme", "smallint unsigned NOT NULL default '0'");
			$db->modify_column("spiders", "usergroup", "smallint unsigned NOT NULL default '0'");
			$db->modify_column("templates", "sid", "smallint NOT NULL default '0'");
			$db->modify_column("themestylesheets", "tid", "smallint unsigned NOT NULL default '0'");
			$db->modify_column("usergroups", "canusesigxposts", "smallint unsigned NOT NULL default '0'");
			$db->modify_column("users", "timezone", "varchar(5) NOT NULL default ''");
			$db->modify_column("users", "reputation", "int NOT NULL default '0'");
			$db->modify_column("warninglevels", "percentage", "smallint(3) NOT NULL default '0'");
			$db->modify_column("warningtypes", "points", "smallint unsigned NOT NULL default '0'");
			$db->modify_column("warnings", "points", "smallint unsigned NOT NULL default '0'");
			break;
	}

	if($db->type != "pgsql")
	{
		// PgSQL doesn't support longtext
		if($db->type == "sqlite")
		{
			// And SQLite doesn't like text columns without a default value...
			$db->modify_column("themestylesheets", "stylesheet", "longtext NOT NULL default ''");
		}
		else
		{
			// ...while MySQL hates text columns with a default value
			$db->modify_column("themestylesheets", "stylesheet", "longtext NOT NULL");
		}
	}

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("30_dbchanges_optimize2");
}

function upgrade30_dbchanges_optimize2()
{
	global $output, $mybb, $db;

	$output->print_header("Optimizing Database");

	echo "<p>Performing necessary optimization queries...</p>";
	echo "<p>Adding indexes to tables...</p>";
	flush();

	if($db->index_exists('sessions', 'location1'))
	{
		$db->drop_index('sessions', 'location1');
	}

	if($db->index_exists('sessions', 'location2'))
	{
		$db->drop_index('sessions', 'location2');
	}

	if($db->type == "mysql" || $db->type == "mysqli")
	{
		$update_data = array(
			'adminlog' => 'uid',
			'banfilters' => 'type',
			'events' => 'cid',
			'forumsubscriptions' => 'uid',
			'moderatorlog' => array('uid', 'fid'),
			'polls' => 'tid',
			'reportedcontent' => 'reportstatus',
			'settings' => 'gid',
			'themestylesheets' => 'tid',
			'warnings' => 'uid',
			'forumpermissions' => array('fid' => array('fid', 'gid')),
			'sessions' => array('location' => array('location1', 'location2')),
			'templates' => array('sid' => array('sid', 'title'))
		);

		foreach($update_data as $table => $index)
		{
			if(!is_array($index))
			{
				$index = array($index);
			}

			foreach($index as $_index => $keys)
			{
				if(!is_array($keys))
				{
					if(!$db->index_exists($table, $keys))
					{
						$db->write_query("ALTER TABLE ".TABLE_PREFIX."{$table} ADD INDEX (`{$keys}`)");
					}
				}
				else
				{
					if(!$db->index_exists($table, $_index))
					{
						$db->write_query("ALTER TABLE ".TABLE_PREFIX."{$table} ADD INDEX `{$_index}`(`".implode('`, `', $keys)."`)");
					}
				}
			}
		}
	}

	echo "<p>Dropping old indexes from tables...</p>";

	if($db->index_exists('attachments', 'posthash'))
	{
		$db->drop_index('attachments', 'posthash');
	}

	if($db->index_exists('reportedcontent', 'dateline'))
	{
		$db->drop_index('reportedcontent', 'dateline');
	}

	if($db->index_exists('reputation', 'pid'))
	{
		$db->drop_index('reputation', 'pid');
	}

	if($db->index_exists('reputation', 'dateline'))
	{
		$db->drop_index('reputation', 'dateline');
	}

	if($db->index_exists('users', 'birthday'))
	{
		$db->drop_index('users', 'birthday');
	}

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("30_dbchanges_optimize3");
}

function upgrade30_dbchanges_optimize3()
{
	global $cache, $output, $mybb, $db;

	$output->print_header("Optimizing Database");

	echo "<p>Performing necessary optimization queries...</p>";
	flush();

	$to_tinyint = array(
		"adminoptions" => array("codepress"),
		"adminviews" => array("visibility"),
		"announcements" => array("allowhtml", "allowmycode", "allowsmilies"),
		"attachments" => array("visible"),
		"banfilters" => array("type"),
		"calendars" => array("startofweek", "showbirthdays", "moderation", "allowhtml", "allowmycode", "allowimgcode", "allowvideocode", "allowsmilies"),
		"calendarpermissions" => array("canviewcalendar", "canaddevents", "canbypasseventmod", "canmoderateevents"),
		"events" => array("visible", "private", "ignoretimezone", "usingtime"),
		"forumpermissions" => array("canview", "canviewthreads", "canonlyviewownthreads", "candlattachments", "canpostthreads", "canpostreplys", "canpostattachments", "canratethreads", "caneditposts", "candeleteposts", "candeletethreads", "caneditattachments", "canpostpolls", "canvotepolls", "cansearch"),
		"forums" => array("active", "open", "allowhtml", "allowmycode", "allowsmilies", "allowimgcode", "allowvideocode", "allowpicons", "allowtratings", "usepostcounts", "showinjump", "overridestyle", "rulestype"),
		"groupleaders" => array("canmanagemembers", "canmanagerequests"),
		"helpdocs" => array("usetranslation", "enabled"),
		"helpsections" => array("usetranslation", "enabled"),
		"moderators" => array("isgroup", "caneditposts", "candeleteposts", "canviewips", "canopenclosethreads", "canmanagethreads", "canmovetononmodforum", "canusecustomtools"),
		"mycode" => array("active"),
		"polls" => array("closed", "multiple", "public"),
		"posts" => array("includesig", "smilieoff", "visible"),
		"privatemessages" => array("status", "includesig", "smilieoff", "receipt"),
		"profilefields" => array("required"),
		"reportedcontent" => array("reportstatus"),
		"sessions" => array("anonymous", "nopermission"),
		"settinggroups" => array("isdefault"),
		"settings" => array("isdefault"),
		"smilies" => array("showclickable"),
		"tasks" => array("enabled", "logging"),
		"themes" => array("def"),
		"threads" => array("sticky", "visible"),
		"threadsubscriptions" => array("notification"),
		"usergroups" => array("isbannedgroup", "canview", "canviewthreads", "canviewprofiles", "candlattachments", "canviewboardclosed", "canpostthreads", "canpostreplys", "canpostattachments", "canratethreads", "caneditposts", "candeleteposts", "candeletethreads", "caneditattachments", "canpostpolls", "canvotepolls", "canundovotes", "canusepms", "cansendpms", "cantrackpms", "candenypmreceipts", "cansendemail", "cansendemailoverride", "canviewmemberlist", "canviewcalendar", "canaddevents", "canbypasseventmod", "canmoderateevents", "canviewonline", "canviewwolinvis", "canviewonlineips", "cancp", "issupermod", "cansearch", "canusercp", "canuploadavatars", "canratemembers", "canchangename", "canbereported", "showforumteam", "usereputationsystem", "cangivereputations", "candisplaygroup", "cancustomtitle", "canwarnusers", "canreceivewarnings", "canmodcp", "showinbirthdaylist", "canoverridepm", "canusesig", "signofollow"),
		"users" => array("allownotices", "hideemail", "subscriptionmethod", "invisible", "receivepms", "receivefrombuddy", "pmnotice", "pmnotify", "showsigs", "showavatars", "showquickreply", "showredirect", "showcodebuttons", "coppauser", "classicpostbit"),
		"warnings" => array("expired")
	);

	foreach($to_tinyint as $table => $columns)
	{
		echo "<p>{$table}: Converting column type to tinyint</p>";
		$change_column = array();
		foreach($columns as $column)
		{
			if($db->type == "pgsql")
			{
				$db->modify_column($table, $column, "smallint", "set", "'0'");
			}
			else if($db->type == "sqlite")
			{
				$change_column[] = "CHANGE {$column} {$column} tinyint(1) NOT NULL default '0'";
			}
			else
			{
				$change_column[] = "MODIFY {$column} tinyint(1) NOT NULL default '0'";
			}
		}
		if($db->type != "pgsql")
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."{$table} ".implode(", ", $change_column));
		}
	}

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("30_dbchanges_optimize4");
}

function upgrade30_dbchanges_optimize4()
{
	global $cache, $output, $mybb, $db;

	$output->print_header("Optimizing Database");

	echo "<p>Performing necessary optimization queries...</p>";
	flush();

	$to_int = array(
		"adminlog" => array("dateline"),
		"adminsessions" => array("dateline", "lastactive"),
		"announcements" => array("startdate", "enddate"),
		"attachments" => array("dateuploaded"),
		"awaitingactivation" => array("dateline"),
		"banfilters" => array("lastuse", "dateline"),
		"banned" => array("dateline", "lifted"),
		"captcha" => array("dateline"),
		"delayedmoderation" => array("delaydateline", "dateline"),
		"forumsread" => array("dateline"),
		"joinrequests" => array("dateline"),
		"massemails" => array("dateline", "senddate"),
		"mailerrors" => array("dateline"),
		"maillogs" => array("dateline"),
		"moderatorlog" => array("dateline"),
		"polls" => array("dateline", "timeout"),
		"pollvotes" => array("dateline"),
		"posts" => array("dateline", "edittime"),
		"privatemessages" => array("dateline", "deletetime", "statustime", "readtime"),
		"promotionlogs" => array("dateline"),
		"reportedcontent" => array("dateline", "lastreport"),
		"reputation" => array("dateline"),
		"searchlog" => array("dateline"),
		"sessions" => array("time"),
		"spiders" => array("lastvisit"),
		"stats" => array("dateline"),
		"tasks" => array("nextrun", "lastrun", "locked"),
		"tasklog" => array("dateline"),
		"templates" => array("dateline"),
		"themestylesheets" => array("lastmodified"),
		"threads" => array("dateline", "lastpost"),
		"threadsread" => array("dateline"),
		"threadsubscriptions" => array("dateline"),
		"threadsread" => array("dateline"),
		"usergroups" => array("reputationpower", "maxreputationsday", "maxreputationsperuser", "maxreputationsperthread", "attachquota"),
		"users" => array("regdate", "lastactive", "lastvisit", "lastpost", "timeonline", "moderationtime", "suspensiontime", "suspendsigtime"),
		"warningtypes" => array("expirationtime"),
		"warnings" => array("dateline", "expires", "daterevoked")
	);

	foreach($to_int as $table => $columns)
	{
		echo "<p>{$table}: Converting column type to int</p>";
		$change_column = array();
		foreach($columns as $column)
		{
			if($db->type == "pgsql")
			{
				$db->modify_column($table, $column, "int", "set", "'0'");
			}
			else if($db->type == "sqlite")
			{
				$change_column[] = "CHANGE {$column} {$column} int unsigned NOT NULL default '0'";
			}
			else
			{
				$change_column[] = "MODIFY {$column} int unsigned NOT NULL default '0'";
			}
		}
		if($db->type != "pgsql")
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."{$table} ".implode(", ", $change_column));
		}
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("30_dbchanges_smilies");
}

function upgrade30_dbchanges_smilies()
{
	global $cache, $output, $db;

	$output->print_header("Updating Smilies");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->type == 'pgsql')
	{
		$db->modify_column("smilies", "find", "text", "set");
	}
	else
	{
		$db->modify_column("smilies", "find", "text NOT NULL");
	}

	$query = $db->simple_select('smilies', 'sid, image, find', '', array('order_by' => 'image, sid'));
	$last_image = null;
	$last_sid = 0;
	$skip = array();
	while($smilie = $db->fetch_array($query))
	{
		if(in_array($smilie['sid'], $skip))
		{
			continue;
		}

		if($smilie['image'] == $last_image && $smilie['image'] != null)
		{
			$dupe_query = $db->simple_select('smilies', 'sid, find', 'image = "'.$db->escape_string($smilie['image']).'"');
			$dupes = '';
			$find = array();
			$skip = array();
			while($dupe = $db->fetch_array($dupe_query))
			{
				if($dupe['sid'] != $last_sid)
				{
					$dupes .= (int)$dupe['sid'].',';
					$find[] = trim($dupe['find']);
					$skip[] = (int)$dupe['sid'];
				}
				else
				{
					$find[] = $dupe['find'];
				}
			}
			$dupes = rtrim($dupes, ',');
			$db->delete_query('smilies', 'sid IN('.$dupes.')');
			$db->update_query('smilies', array('find' => implode("\n", $find)), 'sid = "'.(int)$last_sid.'"');
			$db->free_result($dupe_query);
		}
		else
		{
			$last_sid = $smilie['sid'];
			$last_image = $smilie['image'];
		}
	}

	$cache->update_smilies();

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("30_dbchanges_ip");
}

function upgrade30_dbchanges_ip()
{
	global $mybb, $db, $output;

	$output->print_header("IP Conversion");

	$ipstart = $iptable = '';

	switch($mybb->input['iptask'])
	{
		case 8:
			echo "<p>Adding database indices (3/3)...</p>";
			flush();

			if(!$db->index_exists('users', 'lastip'))
			{
				// This may take a while
				if($db->type == "mysql" || $db->type == "mysqli")
				{
					$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD INDEX lastip (lastip)");
				}
				elseif($db->type == "pgsql")
				{
					$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD INDEX (`lastip`)");
				}
			}
			$next_task = 9;
			break;
		case 7:
			echo "<p>Adding database indices (2/3)...</p>";
			flush();

			if(!$db->index_exists('users', 'regip'))
			{
				// This may take a while
				if($db->type == "mysql" || $db->type == "mysqli")
				{
					$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD INDEX regip (regip)");
				}
				elseif($db->type == "pgsql")
				{
					$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD INDEX (`regip`)");
				}
			}
			$next_task = 8;
			break;
		case 6:
			echo "<p>Adding database indices (1/3)...</p>";
			flush();

			if(!$db->index_exists('posts', 'ipaddress'))
			{
				// This may take a while
				if($db->type == "mysql" || $db->type == "mysqli")
				{
					$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts ADD INDEX ipaddress (ipaddress)");
				}
				elseif($db->type == "pgsql")
				{
					$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts ADD INDEX (`ipaddress`)");
				}
			}
			$next_task = 7;
			break;
		case 5:
			if(!$_POST['ipspage'])
			{
				$ipp = 5000;
			}
			else
			{
				$ipp = (int)$_POST['ipspage'];
			}

			if($_POST['ipstart'])
			{
				$startat = (int)$_POST['ipstart'];
				$upper = $startat+$ipp-1;
				$lower = $startat;
			}
			else
			{
				$startat = 0;
				$upper = $ipp;
				$lower = 0;
			}

			$next_task = 5;
			switch($mybb->input['iptable'])
			{
				case 7:
					echo "<p>Converting user IPs...</p>";
					flush();
					$query = $db->simple_select("users", "COUNT(uid) AS ipcount");
					if($db->type == "mysql" || $db->type == "mysqli")
					{
						$next_task = 6;
					}
					else
					{
						$next_task = 9;
					}
					break;
				case 6:
					echo "<p>Converting thread rating IPs...</p>";
					flush();
					$query = $db->simple_select("threadratings", "COUNT(rid) AS ipcount");
					break;
				case 5:
					echo "<p>Converting session IPs...</p>";
					flush();
					$query = $db->simple_select("sessions", "COUNT(sid) AS ipcount");
					break;
				case 4:
					echo "<p>Converting post IPs...</p>";
					flush();
					$query = $db->simple_select("posts", "COUNT(pid) AS ipcount");
					break;
				case 3:
					echo "<p>Converting moderator log IPs...</p>";
					flush();
					$query = $db->simple_select("moderatorlog", "COUNT(DISTINCT ipaddress) AS ipcount");
					break;
				case 2:
					echo "<p>Converting mail log IPs...</p>";
					flush();
					$query = $db->simple_select("maillogs", "COUNT(mid) AS ipcount");
					break;
				default:
					echo "<p>Converting admin log IPs...</p>";
					flush();
					$query = $db->simple_select("adminlog", "COUNT(DISTINCT ipaddress) AS ipcount");
					break;
			}
			$cnt = $db->fetch_array($query);

			if($upper > $cnt['ipcount'])
			{
				$upper = $cnt['ipcount'];
			}

			echo "<p>Converting ip {$lower} to {$upper} ({$cnt['ipcount']} Total)</p>";
			flush();

			$ipaddress = false;

			switch($mybb->input['iptable'])
			{
				case 7:
					$query = $db->simple_select("users", "uid, regip, lastip", "", array('limit_start' => $lower, 'limit' => $ipp));
					break;
				case 6:
					$query = $db->simple_select("threadratings", "rid, ipaddress", "", array('limit_start' => $lower, 'limit' => $ipp));
					break;
				case 5:
					$query = $db->simple_select("sessions", "sid, ip", "", array('limit_start' => $lower, 'limit' => $ipp));
					break;
				case 4:
					$query = $db->simple_select("posts", "pid, ipaddress", "", array('limit_start' => $lower, 'limit' => $ipp));
					break;
				case 3:
					$query = $db->simple_select("moderatorlog", "DISTINCT(ipaddress)", "", array('limit_start' => $lower, 'limit' => $ipp));
					break;
				case 2:
					$query = $db->simple_select("maillogs", "mid, ipaddress", "", array('limit_start' => $lower, 'limit' => $ipp));
					break;
				default:
					$query = $db->simple_select("adminlog", "DISTINCT(ipaddress)", "", array('limit_start' => $lower, 'limit' => $ipp));
					$mybb->input['iptable'] = 1;
					break;
			}
			while($data = $db->fetch_array($query))
			{
				// Skip invalid IPs
				switch($mybb->input['iptable'])
				{
					case 7:
						$ip1 = my_inet_pton($db->unescape_binary($data['regip']));
						$ip2 = my_inet_pton($db->unescape_binary($data['lastip']));
						if($ip1 === false && $ip2 === false)
						{
							continue;
						}
						break;
					case 5:
						$ip = my_inet_pton($db->unescape_binary($data['ip']));
						if($ip === false)
						{
							continue;
						}
						break;
					case 6:
					case 4:
					case 3:
					case 2:
					default:
						$ip = my_inet_pton($db->unescape_binary($data['ipaddress']));
						if($ip === false)
						{
							continue;
						}
						break;
				}

				switch($mybb->input['iptable'])
				{
					case 7:
						$db->update_query("users", array('regip' => $db->escape_binary($ip1), 'lastip' => $db->escape_binary($ip2)), "uid = '".(int)$data['uid']."'");
						break;
					case 6:
						$db->update_query("threadratings", array('ipaddress' => $db->escape_binary($ip)), "rid = '".(int)$data['rid']."'");
						break;
					case 5:
						$db->update_query("sessions", array('ip' => $db->escape_binary($ip)), "sid = '".(int)$data['sid']."'");
						break;
					case 4:
						$db->update_query("posts", array('ipaddress' => $db->escape_binary($ip)), "pid = '".(int)$data['pid']."'");
						break;
					case 3:
						$db->update_query("moderatorlog", array('ipaddress' => $db->escape_binary($ip)), "ipaddress = '".$db->escape_string($data['ipaddress'])."'");
						break;
					case 2:
						$db->update_query("maillogs", array('ipaddress' => $db->escape_binary($ip)), "mid = '".(int)$data['mid']."'");
						break;
					default:
						$db->update_query("adminlog", array('ipaddress' => $db->escape_binary($ip)), "ipaddress = '".$db->escape_string($data['ipaddress'])."'");
						break;
				}
				$ipaddress = true;
			}

			$remaining = $upper-$cnt['ipcount'];
			if($remaining && $ipaddress)
			{
				$startat = $startat+$ipp;
				$ipstart = "<input type=\"hidden\" name=\"ipstart\" value=\"$startat\" />";
				$iptable = $mybb->input['iptable'];
			}
			else
			{
				$iptable = $mybb->input['iptable']+1;
			}
			if($iptable <= 10)
			{
				$iptable = "<input type=\"hidden\" name=\"iptable\" value=\"$iptable\" />";
			}
			break;
		case 4:
			$next_task = 4;
			switch($mybb->input['iptable'])
			{
				case 10:
					echo "<p>Updating user table (4/4)...</p>";
					flush();

					$table = 'users';
					$column = 'lastip';
					$next_task = 5;
					break;
				case 9:
					echo "<p>Updating user table (3/4)...</p>";
					flush();

					$table = 'users';
					$column = 'regip';
					break;
				case 8:
					echo "<p>Updating threadreating table...</p>";
					flush();

					$table = 'threadratings';
					$column = 'ipaddress';
					break;
				case 7:
					echo "<p>Updating session table...</p>";
					flush();

					$table = 'sessions';
					$column = 'ip';
					break;
				case 6:
					echo "<p>Updating searchlog table...</p>";
					flush();

					$table = 'searchlog';
					$column = 'ipaddress';
					// Skip conversion
					$db->delete_query('searchlog');
					break;
				case 5:
					echo "<p>Updating post table (2/2)...</p>";
					flush();

					$table = 'posts';
					$column = 'ipaddress';
					break;
				case 4:
					echo "<p>Updating moderatorlog table...</p>";
					flush();

					$table = 'moderatorlog';
					$column = 'ipaddress';
					break;
				case 3:
					echo "<p>Updating maillog table...</p>";
					flush();

					$table = 'maillogs';
					$column = 'ipaddress';
					break;
				case 2:
					echo "<p>Updating adminsession table...</p>";
					flush();

					$table = 'adminsessions';
					$column = 'ip';
					// Skip conversion
					$db->delete_query('adminsessions');
					break;
				default:
					echo "<p>Updating adminlog table...</p>";
					flush();

					$mybb->input['iptable'] = 1;
					$table = 'adminlog';
					$column = 'ipaddress';
					break;
			}
			// Truncate invalid IPs
			$db->write_query("UPDATE ".TABLE_PREFIX."{$table} SET {$column} = SUBSTR({$column}, 16) WHERE LENGTH({$column})>16");
			switch($db->type)
			{
				case "pgsql":
					// Drop default value before converting the column
					$db->modify_column($table, $column, false, false);
					$db->modify_column($table, $column, "bytea USING {$column}::bytea", 'set', "''");
					break;
				case "sqlite":
					$db->modify_column($table, $column, "blob(16) NOT NULL default ''");
					break;
				default:
					$db->modify_column($table, $column, "varbinary(16) NOT NULL default ''");
					break;
			}
			if($mybb->input['iptable'] < 10)
			{
				$iptable = "<input type=\"hidden\" name=\"iptable\" value=\"".($mybb->input['iptable']+1)."\" />";
			}
			break;
		case 3:
			echo "<p>Updating user table (2/4)...</p>";
			flush();

			if($db->field_exists('longlastip', 'users'))
			{
				// This may take a while
				$db->drop_column("users", "longlastip");
			}
			$next_task = 4;
			break;
		case 2:
			echo "<p>Updating user table (1/4)...</p>";
			flush();

			if($db->field_exists('longregip', 'users'))
			{
				// This may take a while
				$db->drop_column("users", "longregip");
			}
			$next_task = 3;
			break;
		default:
			echo "<p>Updating post table (1/2)...</p>";
			flush();

			if($db->field_exists('longipaddress', 'posts'))
			{
				// This may take a while
				$db->drop_column("posts", "longipaddress");
			}
			$next_task = 2;
			break;
	}

	if($next_task == 9)
	{
		$contents = "<p>Click next to continue with the upgrade process.</p>";
		$nextact = "30_updatetheme";
	}
	else
	{
		$contents = "<p><input type=\"hidden\" name=\"iptask\" value=\"{$next_task}\" />{$iptable}{$ipstart}Done. Click Next to continue the IP conversion.</p>";

		global $footer_extra;
		$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";
		$nextact = "30_dbchanges_ip";
	}

	$output->print_contents($contents);

	$output->print_footer($nextact);
}

function upgrade30_updatetheme()
{
	global $db, $mybb, $output, $config;

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

	// New default user star
	$contents = "<p>Updating the Default user star image... ";
	$db->update_query("usergroups", array('starimage' => 'images/star.png'), "starimage='images/star.gif'");
	$contents .= "done.</p>";

	$contents .= "<p>Adding new stylesheets... ";
	
	$query = $db->simple_select("themes", "*", "tid='1'");

	$theme = $db->fetch_array($query);
	$properties = my_unserialize($theme['properties']);
	$stylesheets = my_unserialize($theme['stylesheets']);

	$old = array("global.css", "usercp.css", "modcp.css", "star_ratings.css");
	require_once MYBB_ROOT."inc/class_xml.php";
	$colors = @file_get_contents(INSTALL_ROOT.'resources/mybb_theme.xml');
	$parser = new XMLParser($colors);
	$tree = $parser->get_tree();

	if(is_array($tree) && is_array($tree['theme']))
	{
		if(is_array($tree['theme']['stylesheets']))
		{
			foreach($tree['theme']['stylesheets']['stylesheet'] as $stylesheet)
			{
				$new_stylesheet = array(
					"name" => $db->escape_string($stylesheet['attributes']['name']),
					"tid" => 1,
					"attachedto" => $db->escape_string($stylesheet['attributes']['attachedto']),
					"stylesheet" => $db->escape_string($stylesheet['value']),
					"lastmodified" => TIME_NOW,
					"cachefile" => $db->escape_string($stylesheet['attributes']['name'])
				);

				if(in_array($new_stylesheet['name'], $old))
				{
					// We can update the disporder here
					$properties['disporder'][$stylesheet['attributes']['name']] = $stylesheet['attributes']['disporder'];
				}
				else
				{
					// Insert new stylesheet
					$sid = $db->insert_query("themestylesheets", $new_stylesheet);
					$css_url = "css.php?stylesheet={$sid}";

					$cached = cache_stylesheet($tid, $stylesheet['attributes']['name'], $stylesheet['value']);

					if($cached)
					{
						$css_url = $cached;
					}

					// Add to display and stylesheet list
					$properties['disporder'][$stylesheet['attributes']['name']] = $stylesheet['attributes']['disporder'];
					$attachedto = $stylesheet['attributes']['attachedto'];
					if(!$attachedto)
					{
						$attachedto = "global";
					}

					// private.php?compose,folders|usercp.php,global|global
					$attachedto = explode("|", $attachedto);
					foreach($attachedto as $attached_file)
					{
						$attached_actions = explode(",", $attached_file);
						$attached_file = array_shift($attached_actions);
						if(count($attached_actions) == 0)
						{
							$attached_actions = array("global");
						}

						foreach($attached_actions as $action)
						{
							$stylesheets[$attached_file][$action][] = $css_url;
						}
					}
				}
			}
		}
	}

	$update_array = array(
		"properties" => $db->escape_string(my_serialize($properties)),
		"stylesheets" => $db->escape_string(my_serialize($stylesheets))
	);

	$db->update_query("themes", $update_array, "tid = '1'");

	$contents .= "done.</p>";

	$contents .= "<p>Adding a disporder to all stylesheets... ";

	$query = $db->simple_select("themes", "tid,properties,stylesheets");
	while($theme = $db->fetch_array($query))
	{
		$properties = my_unserialize($theme['properties']);
		$stylesheets = my_unserialize($theme['stylesheets']);

		// Disporder already set?
		if(isset($properties['disporder']) && !empty($properties['disporder']))
		{
			continue;
		}

		$disporder = 1;

		// First go through all own stylesheets
		$query2 = $db->simple_select("themestylesheets", "name", "tid='{$theme['tid']}'");
		while($name = $db->fetch_field($query2, "name"))
		{
			$properties['disporder'][$name] = $disporder;
			$disporder++;
		}

		// Next go through the inherited stylesheets
		if(!empty($stylesheets))
		{
			foreach($stylesheets as $a)
			{
				foreach($a as $file => $stylesheet)
				{
					// Don't ask me... Throws an error otherwise
					if(empty($stylesheet))
					{
						continue;
					}
					foreach($stylesheet as $s)
					{
						$name = pathinfo($s, PATHINFO_BASENAME);
						if(empty($properties['disporder']) || !in_array($name, array_keys($properties['disporder'])))
						{
							$properties['disporder'][$name] = $disporder;
							$disporder++;
						}
					}
				}
			}
		}

		$db->update_query("themes", array("properties" => $db->escape_string(my_serialize($properties))), "tid='{$theme['tid']}'");
	}

	$contents .= "done.</p>";

	$contents .= "<p>Adding the Default colors... ";

	$query = $db->simple_select("themes", "*", "tid = '2'");

	// Someone deleted the default theme... :o
	if($db->num_rows($query) != 0)
	{
		$theme = $db->fetch_array($query);
		$properties = my_unserialize($theme['properties']);
		$stylesheets = my_unserialize($theme['stylesheets']);
		
		$properties['editortheme'] = "mybb.css"; // New editor, so reset the theme for it
		$properties['tablespace'] = 5;
		$properties['borderwidth'] = 0;
		// Reset the logo if it's still the default one
		if($properties['logo'] == "images/logo.gif")
		{
			$properties['logo'] = "images/logo.png";
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
				"properties" => $db->escape_string(my_serialize($properties)),
				"stylesheets" => $db->escape_string(my_serialize($stylesheets))
			);
	
			$db->update_query("themes", $update_array, "tid = '2'");
		}
	}

	$contents .= "done.</p>";

	$contents .= '<p>Re-caching and minifying existing stylesheets...</p>';

	$num_re_cached = recache_existing_styles();

	$contents .= "Done. {$num_re_cached} stylesheets re-cached.";

	echo $contents;

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");

	if(!isset($config['secret_pin']) && is_writable(MYBB_ROOT."inc/config.php"))
	{
		$output->print_footer("30_acppin");
	}
	else
	{
		$output->print_footer("30_done");
	}
}

function upgrade30_acppin()
{
	global $config, $output;

	$output->print_header("Add an ACP PIN");

	echo "<p>We added a new security function in 1.8: The possibility to set a security PIN which you need to enter the ACP.<br />\n";
	echo "If you don't want to set a PIN you can simply skip this step (leave the field below empty). You can still set the PIN later (see the docs to see how).</p>\n";
	echo '<div class="border_wrapper">
			<div class="title">ACP PIN Configuration</div>
			<table class="general" cellspacing="0">
				<tbody>
				<tr>
					<th colspan="2" class="first last">ACP Security PIN</th>
				</tr>
				<tr class="first">
					<td class="first"><label for="bbname">PIN:</label></td>
					<td class="last alt_col"><input type="password" class="text_input" name="pin" id="pin" value="'.$config['secret_pin'].'" /></td>
				</tr>
				</tbody>
			</table>
		</div>';

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");

	$output->print_footer("30_acppin_submit");
}

function upgrade30_acppin_submit()
{
	global $db, $mybb, $output, $config;

	$output->print_header("Writing the config file");

	$content = "<p>We're now writing your PIN (if you've entered one) to the config.php file... ";

	if(!is_writable(MYBB_ROOT."inc/config.php"))
	{
		$content .= "Failed (config.php not writable)";
	}
	else if(isset($config['secret_pin']))
	{
		$content .= "Skipped (PIN already set)";
	}
	else
	{
		$pin = addslashes($mybb->get_input('pin'));

		$file = @fopen(MYBB_ROOT."inc/config.php", "r+");

		$contents = '';
		while(!@feof($file))
		{
			$contents .= @fread($file, 8436);
		}

		$contents_temp = str_replace(array("\r", "\t", "\n", " ", "\0", "\x0B"), '', $contents);

		// Set the pointer before the closing php tag to remove it
		$pos = strrpos($contents, "?>");
		if(my_substr($contents_temp, -2) == "?>")
		{
			@fseek($file, $pos, SEEK_SET);
		}

		@fwrite($file, "
/**
 * Admin CP Secret PIN
 *  If you wish to request a PIN
 *  when someone tries to login
 *  on your Admin CP, enter it below.
 */

\$config['secret_pin'] = '{$pin}';");

		@fclose($file);

		$content .= "Done";		
	}

	echo $content."</p>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");

	$output->print_footer("30_done");
}

/**
 * Re-cache the existing stylesheets so that they get minified.
 *
 * @return int The number of re-cached stylesheets.
 */
function recache_existing_styles()
{
	global $db;

	$query = $db->simple_select('themestylesheets', '*');

	$num_updated = 0;

	while($stylesheet = $db->fetch_array($query))
	{
		if (cache_stylesheet((int) $stylesheet['tid'], $stylesheet['name'], $stylesheet['stylesheet']))
		{
			++$num_updated;
		}
	}

	return $num_updated;
}
