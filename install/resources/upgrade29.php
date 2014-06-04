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
 * Upgrade Script: 1.6.13
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade29_dbchanges()
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

	if($db->field_exists('editreason', 'posts'))
	{
		$db->drop_column("editreason", "posts");
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
			$db->add_column("promotions", "warnings", "int NOT NULL default '0' AFTER referralstype");
			$db->add_column("promotions", "warningstype", "varchar(2) NOT NULL default '' AFTER warnings");
			$db->add_column("adminsessions", "useragent", "varchar(100) NOT NULL default ''");
			$db->add_column("forums", "deletedthreads", "int NOT NULL default '0' AFTER unapprovedposts");
			$db->add_column("forums", "deletedposts", "int NOT NULL default '0' AFTER deletedthreads");
			$db->add_column("posts", "editreason", "varchar(100) NOT NULL default ''");
			break;
		default:
			$db->add_column("templategroups", "isdefault", "int(1) NOT NULL default '0'");
			$db->add_column("reportedposts", "type", "varchar(50) NOT NULL default ''");
			$db->add_column("reportedposts", "reports", "int unsigned NOT NULL default '0'");
			$db->add_column("reportedposts", "reporters", "text NOT NULL");
			$db->add_column("reportedposts", "lastreport", "bigint(30) NOT NULL default '0'");
			$db->add_column("promotions", "warnings", "int NOT NULL default '0' AFTER referralstype");
			$db->add_column("promotions", "warningstype", "char(2) NOT NULL default '' AFTER warnings");
			$db->add_column("adminsessions", "useragent", "varchar(100) NOT NULL default ''");
			$db->add_column("forums", "deletedthreads", "int(10) NOT NULL default '0' AFTER unapprovedposts");
			$db->add_column("forums", "deletedposts", "int(10) NOT NULL default '0' AFTER deletedthreads");
			$db->add_column("posts", "editreason", "varchar(100) NOT NULL default ''");
			break;
	}

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("29_dbchanges2");
}

function upgrade29_dbchanges2()
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

	if($db->field_exists('cansoftdelete', 'moderators'))
	{
		$db->drop_column("moderators", "cansoftdelete");
	}

	if($db->field_exists('canrestore', 'moderators'))
	{
		$db->drop_column("moderators", "canrestore");
	}

	if($db->field_exists('deletedposts', 'threads'))
	{
		$db->drop_column("threads", "deletedposts");
	}

	if($db->field_exists('used', 'captcha'))
	{
		$db->drop_column("captcha", "used");
	}

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$db->add_column("forumpermissions", "canonlyreplyownthreads", "int NOT NULL default '0' AFTER canpostreplys");
			$db->add_column("usergroups", "canbereported", "int(1) NOT NULL default '0' AFTER canchangename");
			$db->add_column("usergroups", "edittimelimit", "int NOT NULL default '0'");
			$db->add_column("usergroups", "maxposts", "int NOT NULL default '0'");
			$db->add_column("usergroups", "showmemberlist", "int NOT NULL default '1'");
			$db->add_column("usergroups", "canviewboardclosed", "int NOT NULL default '0' AFTER candlattachments");			
			$db->add_column("moderators", "cansoftdelete", "int NOT NULL default '0' AFTER canusecustomtools");
			$db->add_column("moderators", "canrestore", "int NOT NULL default '0' AFTER cansoftdelete");
			$db->add_column("threads", "deletedposts", "int NOT NULL default '0' AFTER unapprovedposts");
			$db->add_column("captcha", "used", "int NOT NULL default '0'");
			break;
		default:
			$db->add_column("forumpermissions", "canonlyreplyownthreads", "int(1) NOT NULL default '0' AFTER canpostreplys");
			$db->add_column("usergroups", "canbereported", "int(1) NOT NULL default '0' AFTER canchangename");
			$db->add_column("usergroups", "edittimelimit", "int(4) NOT NULL default '0'");
			$db->add_column("usergroups", "maxposts", "int(4) NOT NULL default '0'");
			$db->add_column("usergroups", "showmemberlist", "int(1) NOT NULL default '1'");
			$db->add_column("usergroups", "canviewboardclosed", "int(1) NOT NULL default '0' AFTER candlattachments");
			$db->add_column("moderators", "cansoftdelete", "int(1) NOT NULL default '0' AFTER canusecustomtools");
			$db->add_column("moderators", "canrestore", "int(1) NOT NULL default '0' AFTER cansoftdelete");
			$db->add_column("threads", "deletedposts", "int(10) NOT NULL default '0' AFTER unapprovedposts");
			$db->add_column("captcha", "used", "int(1) NOT NULL default '0'");
			break;
	}

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("29_dbchanges3");
}

function upgrade29_dbchanges3()
{
	global $cache, $output, $mybb, $db;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();


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

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$db->add_column("profilefields", "postbit", "int NOT NULL default '0' AFTER hidden");
			$db->add_column("users", "skype", "varchar(75) NOT NULL default '' AFTER yahoo");
			$db->add_column("users", "google", "varchar(75) NOT NULL default '' AFTER skype");
			$db->add_column("adminoptions", "cplanguage", "varchar(50) NOT NULL default '' AFTER cpstyle");
			break;
		default:
			$db->add_column("profilefields", "postbit", "int(1) NOT NULL default '0' AFTER hidden");
			$db->add_column("users", "skype", "varchar(75) NOT NULL default '' AFTER yahoo");
			$db->add_column("users", "google", "varchar(75) NOT NULL default '' AFTER skype");
			$db->add_column("adminoptions", "cplanguage", "varchar(50) NOT NULL default '' AFTER cpstyle");
			break;
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("privatemessages", "ipaddress", "bytea NOT NULL default ''");
			break;
		case "sqlite":
			$db->add_column("privatemessages", "ipaddress", "blob(16) NOT NULL default ''");
			break;
		default:
			$db->add_column("privatemessages", "ipaddress", "varbinary(16) NOT NULL default ''");
			break;
	}

	$groups = range(1, 39);

	$sql = implode(',', $groups);
	$db->update_query("templategroups", array('isdefault' => 1), "gid IN ({$sql})");

	$db->update_query("reportedposts", array('type' => 'post'));

	$query = $db->simple_select("attachtypes", "COUNT(*) as numexists", "extension='psd'");
	if($db->fetch_field($query, "numexists") == 0)
	{
		$db->insert_query("attachtypes", array('name' => "Adobe Photoshop File", 'mimetype' => 'application/x-photoshop', 'extension' => "psd", 'maxsize' => '1024', 'icon' => 'images/attachtypes/psd.png'));
	}

	$query = $db->simple_select("templategroups", "COUNT(*) as numexists", "prefix='video'");
	if($db->fetch_field($query, "numexists") == 0)
	{
		$db->insert_query("templategroups", array('prefix' => 'video', 'title' => '<lang:group_video>', 'isdefault' => '1'));
	}

	$query = $db->simple_select("templategroups", "COUNT(*) as numexists", "prefix='php'");
	if($db->fetch_field($query, "numexists") != 0)
	{
		$db->update_query("templategroups", array('prefix' => 'announcement', 'title' => '<lang:group_announcement>'), "prefix='php'");
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

	$usergroups = implode(',', $groups);
	$db->update_query('usergroups', array('canbereported' => 1), "gid IN ({$usergroups})");

	$db->update_query('usergroups', array('canviewboardclosed' => 1), 'cancp = 1');

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

	if($db->table_exists("reportedcontent"))
	{
		$db->drop_table("reportedcontent");
	}

	$db->rename_table("reportedposts", "reportedcontent");
	$cache->delete('reportedposts');

	$db->update_query("settings", array('optionscode' => 'select\r\n0=No CAPTCHA\r\n1=MyBB Default CAPTCHA\r\n2=reCAPTCHA\r\n3=Are You a Human'), "name='captchaimage'");

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
	$output->print_footer("29_dbchanges_ip");
}

function upgrade29_dbchanges_ip()
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
				$ipp = $_POST['ipspage'];
			}

			if($_POST['ipstart'])
			{
				$startat = $_POST['ipstart'];
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
					echo "<p>Converting session IPs...</p>";
					flush();
					break;
				case 5:
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
						$db->update_query("users", array('regip' => $db->escape_binary($ip1), 'lastip' => $db->escape_binary($ip2)), "uid = '".intval($data['uid'])."'");
						break;
					case 6:
						$db->update_query("threadratings", array('ipaddress' => $db->escape_binary($ip)), "rid = '".intval($data['rid'])."'");
						break;
					case 5:
						$db->update_query("sessions", array('ip' => $db->escape_binary($ip)), "sid = '".intval($data['sid'])."'");
						break;
					case 4:
						$db->update_query("posts", array('ipaddress' => $db->escape_binary($ip)), "pid = '".intval($data['pid'])."'");
						break;
					case 3:
						$db->update_query("moderatorlog", array('ipaddress' => $db->escape_binary($ip)), "ipaddress = '".$db->escape_string($data['ipaddress'])."'");
						break;
					case 2:
						$db->update_query("maillogs", array('ipaddress' => $db->escape_binary($ip)), "mid = '".intval($data['mid'])."'");
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
					// Skip conversation
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
					// Skip conversation
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
					$db->modify_column($table, $column, "blob(16) NOT NULL");
					break;
				default:
					$db->modify_column($table, $column, "varbinary(16) NOT NULL");
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
		$nextact = "29_updatetheme";
	}
	else
	{
		$contents = "<p><input type=\"hidden\" name=\"iptask\" value=\"{$next_task}\" />{$iptable}{$ipstart}Done. Click Next to continue the IP conversation.</p>";

		global $footer_extra;
		$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";
		$nextact = "29_dbchanges_ip";
	}

	$output->print_contents($contents);

	$output->print_footer($nextact);
}

function upgrade29_updatetheme()
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
	$output->print_footer("29_done");
}
?>
