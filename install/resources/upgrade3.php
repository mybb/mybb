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
 * Upgrade Script: Release Candidate 4
 */

$upgrade_detail = array(
	"revert_all_templates" => 1,
	"revert_all_themes" => 1,
	"revert_all_settings" => 1,
	"requires_deactivated_plugins" => 1,
);

@set_time_limit(0);

function upgrade3_dbchanges()
{
	global $db, $output;

	$output->print_header("Attachment Conversion to Files");

	$contents = "<p>The first step of the upgrade process from RC4 is to move your attachments and avatars to the file system.</p>";

	if(!@is_dir("../uploads/"))
	{
		$errors = "<p>../uploads/ Does not exist in your forums' directory. Please create this directory.";
	}
	else
	{
		if(!@is_writable("../uploads/"))
		{
			@my_chmod("../uploads", '0777');
			if(!@is_writable("../uploads/"))
			{
				$errors = "<p>../uploads/ is not writable! Please chmod this directory so it's writable (766 or 777).";
			}
		}
	}
	if(!@is_dir("../uploads/avatars/"))
	{
		$errors .= "<p>../uploads/avatars/ Does not exist. Please create this directory.";
	}
	else
	{
		if(!@is_writable("../uploads/avatars/"))
		{
			@my_chmod("../uploads/avatars/", '0777');
			if(!is_writable("../uploads/avatars/"))
			{
				$errors = "<p>../uploads/avatars/ is not writable! Please chmod this directory so it's writable (766 or 777).";
			}
		}
	}

	if($errors)
	{
		$output->print_contents($contents."<p><span style=\"color: red\">To be able to do this you must perform the following:</span></p>$errors");
		$output->print_footer("3_dbchanges");
		exit;
	}

	$contents .= "<p>Okay, we've determined that the specified directory settings have been met.</p>If you wish to change the number of attachments to process per page then you can do so below.</p>";
	$contents .= "<p><strong>Attachments Per Page:</strong> <input type=\"text\" size=\"3\" value=\"50\" name=\"attachmentspage\" /></p>";
	$contents .= "<p>Once you're ready, press next to begin the conversion.</p>";

	$output->print_contents($contents);
	$output->print_footer("3_convertattachments");
}

function upgrade3_convertattachments()
{
	global $db, $output;

	$output->print_header("Attachment Conversion to Files");

	if(!$_POST['attachmentspage'])
	{
		$app = 50;
	}
	else
	{
		$app = $_POST['attachmentspage'];
	}

	if($_POST['attachmentstart'])
	{
		$startat = $_POST['attachmentstart'];
		$upper = $startat+$app;
		$lower = $startat;
	}
	else
	{
		$startat = 0;
		$upper = $app;
		$lower = 1;
	}

	require_once MYBB_ROOT."inc/settings.php";

	$query = $db->simple_select("attachments", "COUNT(aid) AS attachcount");
	$cnt = $db->fetch_array($query);

	$contents .= "<p>Converting attachments $lower to $upper (".$cnt['attachcount']." Total)</p>";
	echo "<p>Converting attachments $lower to $upper (".$cnt['attachcount']." Total)</p>";

	if($db->field_exists("uid", TABLE_PREFIX."attachments"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments DROP uid;");
	}
	// Add uid column
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments ADD uid smallint(6) NOT NULL AFTER posthash;");


	if($db->field_exists("thumbnail", TABLE_PREFIX."attachments"))
	{
		// Drop thumbnail column
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments DROP thumbnail");
	}

	if($db->field_exists("thumbnail", TABLE_PREFIX."attachments"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments DROP thumbnail;");
	}
	// Add thumbnail column
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments ADD thumbnail varchar(120) NOT NULL;");

	if($db->field_exists("attachname", TABLE_PREFIX."attachments"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments DROP attachname;");
	}
	// Add attachname column
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments ADD attachname varchar(120) NOT NULL AFTER filesize;");

	if(!$db->field_exists("donecon", TABLE_PREFIX."attachments"))
	{
		// Add temporary column
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments ADD donecon smallint(1) NOT NULL;");
	}

	$query = $db->query("
		SELECT a.*, p.uid AS puid, p.dateline
		FROM ".TABLE_PREFIX."attachments a
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
		WHERE a.donecon != '1'
		ORDER BY a.aid ASC LIMIT {$app}
	");
	while($attachment = $db->fetch_array($query))
	{
		$filename = "post_".$attachment['puid']."_".$attachment['dateline'].$attachment['aid'].".attach";
		$ext = my_strtolower(my_substr(strrchr($attachment['filename'], "."), 1));
		$fp = fopen("../uploads/".$filename, "wb");
		if(!$fp)
		{
			die("Unable to create file. Please check permissions and refresh page.");
		}
		fwrite($fp, $attachment['filedata']);
		fclose($fp);
		unset($attachment['filedata']);
		if($ext == "gif" || $ext == "png" || $ext == "jpg" || $ext == "jpeg" || $ext == "jpe")
		{
			require_once MYBB_ROOT."inc/functions_image.php";
			$thumbname = str_replace(".attach", "_thumb.$ext", $filename);
			$thumbnail = generate_thumbnail("../uploads/".$filename, "../uploads", $thumbname, $settings['attachthumbh'], $settings['attachthumbw']);
			if($thumbnail['code'] == 4)
			{
				// Image was too small - fake a filename
				$thumbnail['filename'] = "SMALL";
			}
		}
		$db->write_query("UPDATE ".TABLE_PREFIX."attachments SET attachname='".$filename."', donecon='1', uid='".$attachment['puid']."', thumbnail='".$thumbnail['filename']."' WHERE aid='".$attachment['aid']."'");
		unset($thumbnail);
	}

	echo "<p>Done.</p>";
	$query = $db->simple_select("attachments", "COUNT(aid) AS attachrem", "donecon != '1'");
	$cnt = $db->fetch_array($query);

	if($cnt['attachrem'] != 0)
	{
		$nextact = "3_convertattachments";
		$startat = $startat+$app;
		$contents .= "<p><input type=\"hidden\" name=\"attachmentspage\" value=\"$app\" /><input type=\"hidden\" name=\"attachmentstart\" value=\"$startat\" />Done. Click Next to move on to the next set of attachments.</p>";
	}
	else
	{
		if($db->field_exists("donecon", TABLE_PREFIX."attachments"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments DROP donecon");
		}

		if($db->field_exists("filedata", TABLE_PREFIX."attachments"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments DROP filedata");
		}

		if($db->field_exists("thumbnailsm", TABLE_PREFIX."attachments"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments DROP thumbnailsm");
		}
		$nextact = "3_convertavatars";
		$contents .= "<p>Done</p><p>All attachments have been moved to the file system. The next step is converting avatars to the file system.</p>";
		$contents .= "<p>If you wish to change the number of uploaded avatars to process per page then you can do so below.</p>";
		$contents .= "<p><strong>Avatars Per Page:</strong> <input type=\"text\" size=\"3\" value=\"200\" name=\"userspage\" /></p>";
		$contents .= "<p>Once you're ready, press next to begin the conversion.</p>";
	}
	$output->print_contents($contents);
	$output->print_footer($nextact);
}

function upgrade3_convertavatars()
{
	global $db, $output;

	$output->print_header("Avatar Conversion to Files");

	if(!$_POST['userspage'])
	{
		$app = 50;
	}
	else
	{
		$app = $_POST['userspage'];
	}

	if($_POST['avatarstart'])
	{
		$startat = $_POST['avatarstart'];
		$upper = $startat+$app;
		$lower = $startat;
	}
	else
	{
		$startat = 0;
		$upper = $app;
		$lower = 1;
	}

	require_once MYBB_ROOT."inc/settings.php";

	$query = $db->simple_select("avatars", "COUNT(uid) AS avatarcount");
	$cnt = $db->fetch_array($query);

	$contents .= "<p>Converting avatars $lower to $upper (".$cnt['avatarcount']." Total)</p>";

	// Add temporary column
	if(!$db->field_exists("donecon", TABLE_PREFIX."avatars"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."avatars ADD donecon smallint(1) NOT NULL;");
	}

	if($db->field_exists("avatartype", TABLE_PREFIX."attachments"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments DROP avatartype;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD avatartype varchar(10) NOT NULL AFTER avatar;");

	$query = $db->simple_select("avatars", "*", "donecon != '1'", array('order_by' => 'uid', 'order_dir' => 'asc', 'limit' => $app));
	while($avatar = $db->fetch_array($query))
	{
		$ext = "";
		switch($avatar['type'])
		{
			case "image/jpeg":
			case "image/jpg":
			case "image/pjpeg":
				$ext = "jpg";
				break;
			case "image/x-png":
			case "image/png":
				$ext = "png";
				break;
			case "image/gif":
				$ext = "gif";
				break;
		}

		if($ext)
		{
			$filename = "avatar_".$avatar['uid'].".".$ext;
			$fp = @fopen("../uploads/avatars/".$filename, "wb");
			if(!$fp)
			{
				die("Unable to create file. Please check permissions and refresh page.");
			}
			fwrite($fp, $avatar['avatar']);
			fclose($fp);
			$db->write_query("UPDATE ".TABLE_PREFIX."avatars SET donecon='1' WHERE uid='".$avatar['uid']."'");
			$db->write_query("UPDATE ".TABLE_PREFIX."users SET avatar='uploads/avatars/$filename', avatartype='upload' WHERE uid='".$avatar['uid']."'");
		}
	}

	echo "<p>Done.</p>";
	$query = $db->simple_select("avatars", "COUNT(uid) AS avatarsrem", "donecon!='1'");
	$cnt = $db->fetch_array($query);

	if($cnt['avatarsrem'] != 0)
	{
		$nextact = "3_convertavatars";
		$startat = $startat+$app;
		$contents .= "<p><input type=\"hidden\" name=\"userspage\" value=\"$app\" /><input type=\"hidden\" name=\"avatarstart\" value=\"$startat\" />Done. Click Next to move on to the next set of avatars.</p>";
	}
	else
	{
		$db->drop_table("avatars");
		$nextact = "3_dbchanges2";
		$contents .= "<p>Done</p><p>All avatars have been moved to the file system. The next step is performing the necessary database modifications for MyBB Gold.</p>";
	}
	$output->print_contents($contents);
	$output->print_footer($nextact);
}

function upgrade3_dbchanges2()
{
	global $db, $output;

	$output->print_header("Database Changes");

	$contents = "<p>Performing necessary database changes.</p>";

	if($db->field_exists("additionalgroups", TABLE_PREFIX."users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP additionalgroups;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD additionalgroups varchar(200) NOT NULL default '' AFTER usergroup;");

	if($db->field_exists("displaygroup", TABLE_PREFIX."users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP displaygroup;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD displaygroup smallint(6) NOT NULL default '0' AFTER additionalgroups;");

	if($db->field_exists("candisplaygroup", TABLE_PREFIX."usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP candisplaygroup;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD candisplaygroup varchar(3) NOT NULL;");

	if(!$db->field_exists("reason", TABLE_PREFIX."banned"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."banned DROP reason;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."banned ADD reason varchar(200) NOT NULL");

	if($db->field_exists("rulestype", TABLE_PREFIX."forums"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums DROP rulestype;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums ADD rulestype smallint(1) NOT NULL;");

	if($db->field_exists("rulestitle", TABLE_PREFIX."forums"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums DROP rulestitle;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums ADD rulestitle varchar(200) NOT NULL;");

	if($db->field_exists("rules", TABLE_PREFIX."forums"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums DROP rules;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums ADD rules text NOT NULL;");

	if($db->field_exists("usetranslation", TABLE_PREFIX."helpdocs"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums DROP helpdocs;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."helpdocs ADD usetranslation CHAR( 3 ) NOT NULL AFTER document;");

	if($db->field_exists("enabled", TABLE_PREFIX."helpdocs"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."helpdocs DROP enabled;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."helpdocs ADD enabled CHAR( 3 ) NOT NULL AFTER usetranslation;");

		/*

		This will break the upgrade for users who have customised help documents

		$db->write_query("UPDATE ".TABLE_PREFIX."helpdocs SET hid='6' WHERE hid='7'");
		$db->write_query("UPDATE ".TABLE_PREFIX."helpdocs SET hid='7' WHERE hid='8'");*/

	if($db->field_exists("usetranslation", TABLE_PREFIX."helpsections"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."helpsections DROP usetranslation;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."helpsections ADD usetranslation CHAR( 3 ) NOT NULL AFTER description;");

	if($db->field_exists("enabled", TABLE_PREFIX."helpsections"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."helpsections DROP enabled;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."helpsections ADD enabled CHAR( 3 ) NOT NULL AFTER usetranslation;");

	if($db->field_exists("firstpost", TABLE_PREFIX."threads"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads DROP firstpost;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ADD firstpost int unsigned NOT NULL default '0' AFTER dateline;");

	if($db->field_exists("attachquota", TABLE_PREFIX."usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP attachquota;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD attachquota bigint(30) NOT NULL default '0';");

	if($db->field_exists("cancustomtitle", TABLE_PREFIX."usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP cancustomtitle;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD cancustomtitle varchar(3) NOT NULL;");


	$db->drop_table("groupleaders");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."groupleaders (
	 lid smallint(6) NOT NULL auto_increment,
	 gid smallint(6) NOT NULL,
	 uid smallint(6) NOT NULL,
	 PRIMARY KEY(lid)
	);");

	$db->drop_table("joinrequests");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."joinrequests (
	 rid smallint(6) NOT NULL auto_increment,
	 uid smallint(6) NOT NULL,
	 gid smallint(6) NOT NULL,
	 reason varchar(250) NOT NULL,
	 dateline bigint(30) NOT NULL,
	 PRIMARY KEY(rid)
	);");

	$db->drop_table("online");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."sessions (
	  sid varchar(32) NOT NULL default '',
	  uid int unsigned NOT NULL default '0',
	  ip varchar(40) NOT NULL default '',
	  time bigint(30) NOT NULL default '0',
	  location varchar(150) NOT NULL default '',
	  useragent varchar(100) NOT NULL default '',
	  anonymous int(1) NOT NULL default '0',
	  nopermission int(1) NOT NULL default '0',
	  location1 int(10) NOT NULL default '0',
	  location2 int(10) NOT NULL default '0',
	  PRIMARY KEY(sid),
	  KEY location1 (location1),
	  KEY location2 (location2)
	);");

	if($db->field_exists("salt", TABLE_PREFIX."users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP salt;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD salt varchar(10) NOT NULL AFTER password;");


	if($db->field_exists("loginkey", TABLE_PREFIX."users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP loginkey;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD loginkey varchar(50) NOT NULL AFTER salt;");


	if($db->field_exists("pmnotify", TABLE_PREFIX."users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP pmnotify;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD pmnotify varchar(3) NOT NULL AFTER pmpopup;");

	$collation = $db->build_create_table_collation();

	$db->drop_table("settinggroups");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."settinggroups (
	  gid smallint(6) NOT NULL auto_increment,
	  name varchar(220) NOT NULL default '',
	  description text NOT NULL,
	  disporder smallint(6) NOT NULL default '0',
	  isdefault char(3) NOT NULL default '',
	  PRIMARY KEY  (gid)
	) ENGINE=MyISAM{$collation};");

	$db->drop_table("settings");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."settings (
	  sid smallint(6) NOT NULL auto_increment,
	  name varchar(120) NOT NULL default '',
	  title varchar(120) NOT NULL default '',
	  description text NOT NULL,
	  optionscode text NOT NULL,
	  value text NOT NULL,
	  disporder smallint(6) NOT NULL default '0',
	  gid smallint(6) NOT NULL default '0',
	  PRIMARY KEY  (sid)
	) ENGINE=MyISAM{$collation};");

	$db->drop_table("datacache");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."datacache (
	  title varchar(30) NOT NULL default '',
	  cache mediumtext NOT NULL,
	  PRIMARY KEY(title)
	) ENGINE=MyISAM{$collation};");

	$contents .= "<p>Done</p>";
	$contents .= "<p>Dropping settings and rebuilding them...";

	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (1, 'General Configuration', 'This section contains various settings such as your board name and url, as well as your website name and url.', 2, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (3, 'Date and Time Formats', 'Here you can specify the different date and time formats used to display dates and times on the forums.', 4, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (7, 'Forum Display Options', 'This section allows you to manage the various settings used on the forum fisplay (forumdisplay.php) of your boards such as enabling and disabling different features.', 6, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (8, 'Show Thread Options', 'This section allows you to manage the various settings used on the thread display page (showthread.php) of your boards such as enabling and disabling different features.', 7, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (11, 'Private Messaging', 'Various options with relation to the MyBB Private Messaging system (private.php) can be managed and set here.', 11, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (12, 'Member List', 'This section allows you to control various aspects of the board member listing (memberlist.php), such as how many members to show per page, and which features to enable or disable.', 10, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (13, 'Posting', 'These options control the various elements in relation to posting messages on the forums.', 9, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (14, 'Banning Options', '', 15, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (16, 'Forum Home Options', 'This section allows you to manage the various settings used on the forum home (index.php) of your boards such as enabling and disabling different features.', 5, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (17, 'Calendar', 'The board calendar allows the public and private listing of events and members'' birthdays. This section allows you to control and manage the settings for the Calendar.', 12, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (27, 'Server and Optimization Options', 'These options allow you to set various server and optimization preferences allowing you to reduce the load on your server, and gain better performance on your board.', 3, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (19, 'User Registration and Profile Options', 'Here you can control various settings with relation to user account registration and account management.', 8, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (20, 'Clickable Smilies and BB Code', '', 17, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (23, 'Who''s Online', '', 13, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (26, 'Board Online / Offline', 'These settings allow you to globally turn your forums online or offline, and allow you to specify a reason for turning them off.', 1, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (28, 'Control Panel Preferences (Global)', '', 19, 'yes');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settinggroups (gid, name, description, disporder, isdefault) VALUES (30, 'Portal Settings', '', 14, 'yes');");

	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'boardclosed', 'Board Closed', 'If you need to close your forums to make some changes or perform an upgrade, this is the global switch. Viewers will not be able to view your forums, however, they will see a message with the reason you specify below.<br />\r\n<br />\r\n<b>Administrators will still be able to view the forums.</b>', 'yesno', 'no', 1, 26);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'boardclosed_reason', 'Board Closed Reason', 'If your forum is closed, you can set a message here that your visitors will be able to see when they visit your forums.', 'textarea', 'These forums are currently closed for maintenance. Please check back later.', 2, 26);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'bbname', 'Board Name', 'The name of your message boards. We recommend that it is not over 75 characters.', 'text', 'Your Forums', 1, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'bburl', 'Board URL', 'The url to your forums.<br />Include the http://. Do NOT include a trailing slash.', 'text', 'http://', 2, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'homename', 'Homepage Name', 'The name of your homepage. This will appear in the footer with a link to it.', 'text', 'Your Website', 3, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'homeurl', 'Homepage URL', 'The full URL of your homepage. This will be linked to in the footer along with its name.', 'text', 'http://', 4, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'dateformat', 'Date Format', 'The format of the dates used on the forum. This format uses the PHP date() function. We recommend not changing this unless you know what you''re doing.', 'text', 'm-d-Y', 1, 3);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'adminemail', 'Admin Email', 'The administrator''s email address. This will be used for outgoing emails sent via the forums.', 'text', 'root@localhost', 5, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'timeformat', 'Time Format', 'The format of the times used on the forum. This format uses PHP''s date() function. We recommend not changing this unless you know what you''re doing.', 'text', 'h:i A', 2, 3);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'threadsperpage', 'Threads Per Page', '', 'text', '20', 1, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'hottopic', 'Replys For Hot Topic', 'The number of replies that is needed for a topic to be considered ''hot''.', 'text', '20', 3, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'cookiedomain', 'Cookie Domain', 'The domain which cookies should be set to. This can remain blank. It should also start with a . so it covers all subdomains.', 'text', '', 8, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'cookiepath', 'Cookie Path', 'The path which cookies are set to, we recommend setting this to the full directory path to your forums with a trailing slash.', 'text', '', 9, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'postsperpage', 'Posts Per Page:', 'The number of posts to display per page. We recommend its not higher than 20 for people with slower connections.', 'text', '10', 1, 8);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'regdateformat', 'Registered Date Format', 'The format used on showthread where it shows when the user registered.', 'text', 'M Y', 3, 3);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'sigmycode', 'Allow MyCode in Signatures', 'Do you want to allow MyCode to be used in users'' signatures?', 'yesno', 'yes', 1, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'sigsmilies', 'Allow Smilies in Signatures', 'Do you want to allow smilies to be used in users'' signatures?', 'yesno', 'yes', 3, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'sightml', 'Allow HTML in Signatures', 'Do you want to allow HTML to be used in users'' signatures?', 'yesno', 'no', 4, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'sigimgcode', 'Allow [img] Code in Signatures', 'Do you want to allow [img] code to be used in users'' signatures?', 'yesno', 'yes', 5, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'threadusenetstyle', 'Usenet Style Thread View', 'Selecting yes will cause posts to look similar to how posts look in USENET. No will cause posts to look the modern way.', 'yesno', 'no', 4, 8);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'pmsallowhtml', 'Allow HTML', 'Selecting yes will allow HTML to be used in private messages.', 'yesno', 'no', 1, 11);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'pmsallowmycode', 'Allow MyCode', 'Selecting yes will allow MyCode to be used in private messages.', 'yesno', 'yes', 2, 11);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'pmsallowsmilies', 'Allow Smilies', 'Selecting yes will allow Smilies to be used in private messages.', 'yesno', 'yes', 3, 11);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'pmsallowimgcode', 'Allow [img] Code', 'Selecting yes will allow [img] Code to be used in private messages.', 'yesno', 'yes', 4, 11);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'siglength', 'Length limit in Signatures', 'The maximum number of characters a user can place in a signature.', 'text', '255', 6, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'messagelength', 'Maximum Message Length', 'The maximum number of characters to allow in a message. A setting of 0 allows an unlimited length.', 'text', '0', 1, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'membersperpage', 'Members Per Page', 'The number of members to show per page on the member list.', 'text', '20', 1, 12);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'load', '*NIX Load Limiting', 'Limit the maximum server load before myBB rejects people.  0 for none.  Recommended limit is 5.0.', 'text', '0', 5, 27);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'emailkeep', 'Users Keep Email', 'If a current user has an email already registered in your banned list, should he be allowed to keep it.', 'yesno', 'no', 4, 14);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'ipban', 'Ban by IP', 'Here, you may specify IP addresses or a range of IP addresses.  You must separate each IP with a space.', 'textarea', '', 2, 14);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'emailban', 'Ban by Email', 'You may specify specific email addresses to ban, or you may specify a domain.  You must separate email addresses and domains with a space.', 'textarea', '', 3, 14);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'avatarsize', 'Max Uploaded Avatar Size', 'Maximum file size (in kilobytes) of uploaded avatars.', 'text', '10', 8, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'avatardir', 'Avatar Directory', 'The directory where your avatars are stored. These are used in the avatar list in the User CP.', 'text', 'images/avatars', 7, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showeditedby', 'Show ''edited by'' Messages', 'Once a post is edited by a regular user, do you want to show the edited by message?', 'yesno', 'yes', 6, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'maxposts', 'Maximum Posts Per Day', 'This is the total number of posts allowed per user per day.  0 for unlimited.', 'text', '0', 2, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showeditedbyadmin', 'Show ''edited by'' Message for Forum Staff', 'Do you want to show edited by messages for forum staff when they edit their posts?', 'yesno', 'yes', 7, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'bannedusernames', 'Banned Usernames', 'Ban users from registering certain usernames.  Seperate them with a space.', 'textarea', '', 1, 14);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'maxpolloptions', 'Maximum Number of Poll Options', 'The maximum number of options for polls that users can post.', 'text', '10', 3, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'dotfolders', 'Use ''dot'' Icons', 'Do you want to show dots on the thread indicators of threads users have participated in.', 'yesno', 'yes', 8, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'contactlink', 'Contact Us Link', 'This will be used for the Contact Us link on the bottom of all the forum pages. Can either be an email address (using mailto:email@website.com) or a hyperlink.', 'text', '#', 6, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showdescriptions', 'Show Forum Descriptions?', 'This option will allow you to turn off showing the descriptions for forums.', 'yesno', 'yes', 1, 16);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showbirthdays', 'Show Today''s Birthdays?', 'Do you want to show today''s birthdays on the forum homepage?', 'yesno', 'yes', 2, 16);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showwol', 'Show Who''s Online?', 'Display the currently active users on the forum home page.', 'yesno', 'yes', 4, 16);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'hideprivateforums', 'Hide Private Forums?', 'You can hide private forums by turning this option on. This option also hides forums on the forum jump and all subforums.', 'yesno', 'yes', 3, 16);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showsimilarthreads', 'Show ''Similar Threads'' Table', 'The Similar Threads table shows threads that are relevant to the thread being read. You can set the relevancy below.', 'yesno', 'no', 5, 8);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'similarityrating', 'Similar Threads Relevancy Rating', 'This allows you to limit similar threads to ones more relevant (0 being not relevant). This number should not be over 10 and should not be set low (<5) for large forums.', 'text', '1', 7, 8);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'similarlimit', 'Similar Threads Limit', 'Here you can change the total amount of similar threads to be shown in the similar threads table. It is recommended that it is not over 15 for 56k users.', 'text', '10', 8, 8);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'privateeventcolor', 'Private Events Color', 'The color that private events will be shown in on the main calendar page.', 'text', 'red', 2, 17);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'publiceventcolor', 'Public Events Color', 'The color that public events will be shown in on the main calendar page.', 'text', 'green', 1, 17);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'hottopicviews', 'Views For Hot Topic', 'The number of views a thread can have before it is considered ''hot''.', 'text', '150', 7, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'logip', 'Log Posting IP Addresses', 'Do you wish to log ip addresses of users who post, and who you want to show ip addresses to.', 'radio\r\nno=Do not log IP\r\nhide=Show to Admins & Mods\r\nshow=Show to all Users', 'hide', 3, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'statslimit', 'Stats Limit', 'The number of threads to show on the stats page for most replies and most views.', 'text', '15', 10, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'modlist', 'Forums'' Moderator Listing', 'Here you can turn on or off the listing of moderators for each forum on index.php and forumdisplay.php', 'onoff', 'on', 5, 16);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'smilieinserter', 'Clickable Smilies Inserter', 'Clickable smilies will appear on the posting pages if this option is set to ''on''.', 'onoff', 'on', 1, 20);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'smilieinsertertot', 'No. of Smilies to show', 'Enter the total number of smilies to show on the clickable smilie inserter.', 'text', '20', 2, 20);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'smilieinsertercols', 'No. of Smilie Cols to Show', 'Enter the number of columns you wish to show on the clickable smilie inserter.', 'text', '4', 3, 20);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showindexstats', 'Show Small Stats Section', 'Do you want to show the total number of threads, posts, members, and the last member on the forum home?', 'yesno', 'yes', 6, 16);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'regtype', 'Registration Method', 'Please select the method of registration to use when users register.', 'select\r\ninstant=Instant Activation\r\nverify=Send Email Verification\r\nrandompass=Send Random Password\r\nadmin=Administrator Activation', 'verify', 1, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'userpppoptions', 'User Selectable Posts Per Page', 'If you would like to allow users to select how many posts are shown per page in a thread, enter the options they should be able to select separated with commas. If this is left blank they will not be able to choose how many posts are shown per page.', 'text', '5,10,20,25,30,40,50', 2, 8);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'usertppoptions', 'User Selectable Threads Per Page', 'If you would like to allow users to select how many threads per page are shown in a forum, enter the options they should be able to select separated with commas. If this is left blank they will not be able to choose how many threads are shown per page.', 'text', '10,20,25,30,40,50', 6, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'wolcutoffmins', 'Cut-off Time (mins)', 'The number of minutes before a user is marked offline. Recommended: 15.', 'text', '15', 1, 23);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'postfloodcheck', 'Post Flood Checking', 'Set to on if you want to enable flood checking for posts. Specifiy the time between posts below.', 'onoff', 'on', 4, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'postfloodsecs', 'Post Flood Time', 'Set the time (in seconds) users have to wait between posting, to be in effect; the option above must be on.', 'text', '60', 5, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'gzipoutput', 'Use GZip Page Compression?', 'Do you want to compress pages in GZip format when they are sent to the browser? This means quicker downloads for your visitors, and less traffic usage for you. The level of the compression is set by the server''s load.', 'yesno', 'yes', 1, 27);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'standardheaders', 'Send Standard Headers', 'With some web servers, this option can cause problems; with others, it is needed. ', 'yesno', 'no', 2, 27);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'nocacheheaders', 'Send No Cache Headers', 'With this option you can prevent caching of the page by the browser.', 'yesno', 'no', 3, 27);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'maxpostimages', 'Maximum Images per Post', 'Enter the maximum number of images (including smilies) a user can put in their post. Set to 0 to disable this.', 'text', '10', 8, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'maxsigimages', 'Maximum Number of Images per Signature', 'Enter the maximum number of images (including smilies) a user can put in their signature. Set to 0 to disable this.', 'text', '2', 2, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'browsingthisforum', 'Users Browsing this Forum', 'Here you can turn off the ''users browsing this forum'' feature.', 'onoff', 'on', 9, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'usereferrals', 'Use Referrals System', 'Do you want to use the user referrals system on these forums?', 'yesno', 'yes', 3, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'subscribeexcerpt', 'Amount of Characters for Subscription Previews', 'How many characters of the post do you want to send with the email notification of a new reply.', 'text', '100', 9, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'cpstyle', 'Control Panel Style', 'The Default style that the control panel will use. Styles are inside the styles folder. A folder name inside that folder becomes the style title and style.css inside the style title folder is the css style file.', 'cpstyle', 'Axiom', 2, 28);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'cplanguage', 'Control Panel Language', 'The language of the control panel.', 'adminlanguage', 'english', 1, 28);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'minnamelength', 'Minimum Username Length', 'The minimum number of characters a username can be when a user registers.', 'text', '3', 5, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'maxnamelength', 'Maximum Username Length', 'The maximum number of characters a username can be when a user registers.', 'text', '30', 6, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'redirects', 'Friendly Redirection Pages', 'This will enable friendly redirection pages instead of bumping the user directly to the page.', 'onoff', 'on', 4, 27);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'betweenregstime', 'Time Between Registrations', 'The amount of time (in hours) to disallow registrations for users who have already registered an account under the same ip address.', 'text', '24', 2, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'maxregsbetweentime', 'Maximum Registrations Per IP Address', 'This option allows you to set the maximum amount of times a certain user can register within the timeframe specified above.', 'text', '2', 4, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showstats', 'Show forum statistics', 'Do you want to show the total number of posts, threads, members and the last registered member on the portal page?', 'yesno', 'yes', 5, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showpms', 'Show the number of PMs to users', 'Do you want to show the number of private messages the current user has in their pm system.', 'yesno', 'yes', 4, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showwelcome', 'Show the Welcome box', 'Do you want to show the welcome box to visitors / users.', 'yesno', 'yes', 3, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_numannouncements', 'Number of announcements to show', 'Please enter the number of announcements to show on the main page.', 'text', '10', 2, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showstats', 'Show forum statistics', 'Do you want to show the total number of posts, threads, members and the last registered member on the portal page?', 'yesno', 'yes', 5, 29);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showwol', 'Show Whos Online', 'Do you want to show the ''whos online'' information to users when they visit the portal page?', 'yesno', 'yes', 6, 29);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_announcementsfid', 'Forum ID to pull announcements from', 'Please enter the forum id (fid) of the forum you wish to pull the announcements from', 'text', '1', 1, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showdiscussionsnum', 'Number of latest discussions to show', 'Please enter the number of current forum discussions to show on the portal page.', 'text', '10', 8, 29);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showwol', 'Show Who''s Online', 'Do you want to show the ''Who''s online'' information to users when they visit the portal page?', 'yesno', 'yes', 6, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showsearch', 'Show Search Box', 'Do you want to show the search box, allowing users to quickly search the forums on the portal?', 'yesno', 'yes', 7, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showdiscussions', 'Show Latest Discussions', 'Do you wish to show the current forum discussions on the portal page?', 'yesno', 'yes', 8, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showdiscussionsnum', 'Number of latest discussions to show', 'Please enter the number of current forum discussions to show on the portal page.', 'text', '10', 9, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'attachthumbh', 'Attached Thumbnail Maximum Height', 'Enter the height that attached thumbnails should be generated at.', 'text', '60', 12, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'attachthumbw', 'Attached Thumbnail Maximum Width', 'Enter the width that attached thumbnails should be generated at.', 'text', '60', 13, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'maxattachments', 'Maximum Attachments Per Post', 'THe maximum number of attachments a user is allowed to upload per post.', 'text', '5', 10, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'attachthumbnails', 'Show Attached Thumbnails in Posts', 'Do you want to show the generated thumbnails for attached images inside the posts?', 'yesno', 'yes', 11, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'polloptionlimit', 'Maximum <!-- Poll --> Option Length', 'The maximum length that each poll option can be. (Set to 0 to disable).', 'text', '250', 1, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'timezoneoffset', 'Default Timezone Offset', 'Here you can set the default timezone offset for guests and members using the default offset.', 'text', '+10', 4, 3);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'bblanguage', 'Default Language', 'The default language that MyBB should use for guests and for users without a selected language in their user control panel.', 'language', 'english', 7, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'regimage', 'Antispam Registration Image', 'If yes, and GD is installed, an image will be shown during registration where users are required to enter the text contained within the image to continue with registration.', 'onoff', 'on', 1, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'reportmethod', 'Reported Posts Medium', 'Please select from the list how you want reported posts to be dealt with. Storing them in the database is probably the better of the options listed.', 'radio\r\ndb=Stored in the Database\r\npms=Sent as Private Messages\r\nemail=Sent via Email', 'db', 1, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'threadreadcut', 'Read Threads in Database (Days)', 'The number of days that you wish to keep thread read information in the database. For large boards, we do not recommend a high number as the board will become slower. Set to 0 to disable.', 'text', '7', 3, 8);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'announcementlimit', 'Announcements Limit', 'The number of forum announcements to  show in the thread listing on the forum display pages. Set to 0 to show all active announcements.', 'text', '2', 10, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'uploadspath', 'Uploads Path', 'The path used for all board uploads. It <b>must be chmod 777</b> (on Unix servers).', 'text', './uploads', 1, 27);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'maxavatardims', 'Maximum Avatar Dimensions', 'The maximum dimensions that an avatar can be, in the format of width<b>x</b>height. If this is left blank then there will be no dimension restriction.', 'text', '10x10', 1, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'avataruploadpath', 'Avatar Upload Path', 'This is the path where custom avatars will be uploaded to. This directory <b>must be chmod 777</b> (writable) for uploads to work.', 'text', './uploads/avatars', 1, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'subforumsindex', 'Subforums to show on Index listing', 'The number of subforums that you wish to show inside forums on the index and forumdisplay pages. Set to 0 to disable this', 'text', '2', 1, 16);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'disableregs', 'Disable Registrations', 'Allows you to turn off the capability for users to register with one click.', 'yesno', 'no', 9, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'decpoint', 'Decimal Point', 'The decimal point you use in your region.', 'text', '.', 1, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'thousandssep', 'Thousands Numeric Separator', 'The punctuation you want to use .  (for example, the setting \',\' with the number 1200 will give you a number such as 1,200)', 'text', ',', 1, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showvernum', 'Show Version Numbers', 'Allows you to turn off the public display of version numbers in MyBB.', 'onoff', 'off', 1, 1);");

	echo "Done</p>";
	$output->print_contents($contents);
	$output->print_footer("3_dbchanges3");
}

function upgrade3_dbchanges3()
{
	global $db, $output;

	$output->print_header("Database Field Size Changes");

	$contents = "<p>Performing necessary database field size changes.</p>";

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminlog CHANGE uid uid int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions CHANGE uid uid int(10) NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."announcements CHANGE aid aid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."announcements CHANGE fid fid int(10) NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."announcements CHANGE uid uid int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments CHANGE aid aid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments CHANGE uid uid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments CHANGE visible visible int(1) NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments CHANGE downloads downloads int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachtypes CHANGE atid atid int unsigned NOT NULL auto_increment;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."awaitingactivation CHANGE aid aid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."awaitingactivation CHANGE uid uid int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."badwords CHANGE bid bid int unsigned NOT NULL auto_increment;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."banned CHANGE uid uid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."banned CHANGE gid gid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."banned CHANGE oldgroup oldgroup int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."banned CHANGE admin admin int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."events CHANGE eid eid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."events CHANGE author author int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."favorites CHANGE fid fid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."favorites CHANGE uid uid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."favorites CHANGE tid tid int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forumpermissions CHANGE pid pid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forumpermissions CHANGE fid fid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forumpermissions CHANGE gid gid int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums CHANGE fid fid smallint unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums CHANGE pid pid smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums CHANGE disporder disporder smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums CHANGE threads threads int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums CHANGE posts posts int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums CHANGE style style smallint unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forumsubscriptions CHANGE fsid fsid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forumsubscriptions CHANGE fid fid smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forumsubscriptions CHANGE uid uid int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."groupleaders CHANGE lid lid smallint unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."groupleaders CHANGE gid gid smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."groupleaders CHANGE uid uid int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."helpdocs CHANGE hid hid smallint unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."helpdocs CHANGE sid sid smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."helpdocs CHANGE disporder disporder smallint unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."helpsections CHANGE sid sid smallint unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."helpsections CHANGE disporder disporder smallint unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."icons CHANGE iid iid smallint unsigned NOT NULL auto_increment;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."joinrequests CHANGE rid rid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."joinrequests CHANGE uid uid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."joinrequests CHANGE gid gid smallint unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."moderatorlog CHANGE uid uid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."moderatorlog CHANGE fid fid smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."moderatorlog CHANGE tid tid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."moderatorlog CHANGE pid pid int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."moderators CHANGE mid mid smallint unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."moderators CHANGE fid fid smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."moderators CHANGE uid uid int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."polls CHANGE pid pid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."polls CHANGE tid tid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."polls CHANGE numoptions numoptions smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."polls CHANGE numvotes numvotes smallint unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."pollvotes CHANGE vid vid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."pollvotes CHANGE pid pid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."pollvotes CHANGE uid uid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."pollvotes CHANGE voteoption voteoption smallint unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE pid pid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE tid tid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE replyto replyto int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE fid fid smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE icon icon smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE uid uid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE edituid edituid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE visible visible int(1) NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages CHANGE pmid pmid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages CHANGE uid uid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages CHANGE toid toid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages CHANGE fromid fromid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages CHANGE folder folder smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages CHANGE icon icon smallint unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."profilefields CHANGE fid fid smallint unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."profilefields CHANGE disporder disporder smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."profilefields CHANGE length length smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."profilefields CHANGE maxlength maxlength smallint unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."reportedposts CHANGE rid rid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."reportedposts CHANGE pid pid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."reportedposts CHANGE tid tid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."reportedposts CHANGE fid fid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."reportedposts CHANGE uid uid int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."reputation CHANGE uid uid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."reputation CHANGE pid pid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."reputation CHANGE adduid adduid int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."searchlog CHANGE sid sid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."searchlog CHANGE uid uid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."searchlog CHANGE limitto limitto smallint(4) NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."settinggroups CHANGE gid gid smallint unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."settinggroups CHANGE disporder disporder smallint unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."settings CHANGE sid sid smallint unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."settings CHANGE disporder disporder smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."settings CHANGE gid gid smallint unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."smilies CHANGE sid sid smallint unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."smilies CHANGE disporder disporder smallint unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."templates CHANGE tid tid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."templates CHANGE sid sid int(10) NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."templatesets CHANGE sid sid smallint unsigned NOT NULL auto_increment;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes CHANGE tid tid smallint unsigned NOT NULL auto_increment;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadratings CHANGE rid rid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadratings CHANGE tid tid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadratings CHANGE uid uid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadratings CHANGE rating rating smallint unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE tid tid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE fid fid smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE icon icon smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE poll poll int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE uid uid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE replies replies int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE views views int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE sticky sticky int(1) NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE numratings numratings smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE totalratings totalratings smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE visible visible int(1) NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadsread CHANGE tid tid int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadsread CHANGE uid uid int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."userfields CHANGE ufid ufid int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups CHANGE gid gid smallint unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups CHANGE stars stars smallint(4) NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE uid uid int unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE usergroup usergroup smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE displaygroup displaygroup smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE style style smallint unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE referrer referrer int unsigned NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usertitles CHANGE utid utid smallint unsigned NOT NULL auto_increment;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usertitles CHANGE posts posts int unsigned NOT NULL;");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usertitles CHANGE stars stars smallint(4) NOT NULL;");

	echo "Done</p>";

	$contents .= "<span style=\"color: red; font-weight: bold;\">WARNING:</span> The next step will delete any custom themes or templates you have! Please back them up before continuing!</p>";
	$output->print_contents($contents);
	$output->print_footer("3_done");
}
