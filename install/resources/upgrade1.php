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
 * Upgrade Script: Release Candidate 2
 */

$upgrade_detail = array(
	"revert_all_templates" => 1,
	"revert_all_themes" => 1,
	"revert_all_settings" => 1,
	"requires_deactivated_plugins" => 1,
);

function upgrade1_dbchanges()
{
	global $db, $output;

	$output->print_header("Database Changes since Release Candidate 2");

	$contents .= "<p>Making necessary database modifications...";

	if($db->field_exists('regip', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP regid;");
	}
	$db->write_query("ALTER TABLE users ADD regip varchar(50) NOT NULL;");

	$db->write_query("ALTER TABLE banned CHANGE lifted lifted varchar(40) NOT NULL;");

	if($db->field_exists('posthash', "attachments"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments DROP posthash;");
	}
	$db->write_query("ALTER TABLE attachments ADD posthash varchar(50) NOT NULL AFTER pid;");

	if($db->field_exists('thumbnail', "attachments"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments DROP thumbnail;");
	}
	$db->write_query("ALTER TABLE attachments ADD thumbnail blob NOT NULL");

	if($db->field_exists('thumbnailsm', "attachments"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments DROP thumbnailsm;");
	}
	$db->write_query("ALTER TABLE attachments ADD thumbnailsm char(3) NOT NULL;");

	$db->write_query("DELETE FROM attachtypes");
	$db->write_query("INSERT INTO attachtypes VALUES(NULL,'Compressed Archive','','zip gz tar rar ace cab','1024');");
	$db->write_query("INSERT INTO attachtypes VALUES(NULL,'JPEG Image','','jpg jpe jpeg','500');");
	$db->write_query("INSERT INTO attachtypes VALUES(NULL,'Text Document','text/plain','txt','200');");
	$db->write_query("INSERT INTO attachtypes VALUES(NULL,'GIF Image','image/gif','gif','500');");
	$db->write_query("INSERT INTO attachtypes VALUES(NULL,'Bitmap Image','','bmp','500');");
	$db->write_query("INSERT INTO attachtypes VALUES(NULL,'PHP File','','php phps php3 php4','500');");
	$db->write_query("INSERT INTO attachtypes VALUES(NULL,'PNG Image','image/png image/x-png','png','50');");
	$db->write_query("INSERT INTO attachtypes VALUES(NULL,'Microsoft Word Document','','doc rtf','1024');");
	$db->write_query("INSERT INTO attachtypes VALUES(NULL,'Executable','','exe com bat','1024');");

	if($db->field_exists('small', "themes"))
	{
		$db->write_query("ALTER TABLE themes DROP small;");
	}
	if($db->field_exists('smallend', "themes"))
	{
		$db->write_query("ALTER TABLE themes DROP smallend;");
	}
	if($db->field_exists('font', "themes"))
	{
		$db->write_query("ALTER TABLE themes DROP font;");
	}
	if($db->field_exists('frontend', "themes"))
	{
		$db->write_query("ALTER TABLE themes DROP fontend;");
	}
	if($db->field_exists('large', "themes"))
	{
		$db->write_query("ALTER TABLE themes DROP large;");
	}
	if($db->field_exists('largeend', "themes"))
	{
		$db->write_query("ALTER TABLE themes DROP largeend;");
	}

	if($db->field_exists('smallfont', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP smallfont;");
	}
	$db->write_query("ALTER TABLE themes ADD smallfont varchar(150) NOT NULL;");

	if($db->field_exists('smallfontsize', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP smallfontsize;");
	}
	$db->write_query("ALTER TABLE themes ADD smallfontsize varchar(20) NOT NULL;");

	if($db->field_exists('normalfont', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP normalfont;");
	}
	$db->write_query("ALTER TABLE themes ADD normalfont varchar(150) NOT NULL;");

	if($db->field_exists('normalfontsize', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP normalfontsize;");
	}
	$db->write_query("ALTER TABLE themes ADD normalfontsize varchar(20) NOT NULL;");

	if($db->field_exists('largefont', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP largefont;");
	}
	$db->write_query("ALTER TABLE themes ADD largefont varchar(150) NOT NULL;");

	if($db->field_exists('largefontsize', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP largefontsize;");
	}
	$db->write_query("ALTER TABLE themes ADD largefontsize varchar(20) NOT NULL;");

	if($db->field_exists('menubgcolor', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP menubgcolor;");
	}
	$db->write_query("ALTER TABLE themes ADD menubgcolor varchar(15) NOT NULL;");

	if($db->field_exists('menubgimage', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP menubgimage;");
	}
	$db->write_query("ALTER TABLE themes ADD menubgimage varchar(100) NOT NULL;");

	if($db->field_exists('menucolor', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP menucolor;");
	}
	$db->write_query("ALTER TABLE themes ADD menucolor varchar(15) NOT NULL;");

	if($db->field_exists('menuhoverbgcolor', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP menuhoverbgcolor;");
	}
	$db->write_query("ALTER TABLE themes ADD menuhoverbgcolor varchar(15) NOT NULL;");

	if($db->field_exists('menuhoverbgimage', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP menuhoverbgimage;");
	}
	$db->write_query("ALTER TABLE themes ADD menuhoverbgimage varchar(100) NOT NULL;");

	if($db->field_exists('menuhovercolor', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP menuhovercolor;");
	}
	$db->write_query("ALTER TABLE themes ADD menuhovercolor varchar(15) NOT NULL;");

	if($db->field_exists('panelbgcolor', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP panelbgcolor;");
	}
	$db->write_query("ALTER TABLE themes ADD panelbgcolor varchar(15) NOT NULL;");

	if($db->field_exists('panelbgimage', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP panelbgimage;");
	}
	$db->write_query("ALTER TABLE themes ADD panelbgimage varchar(100) NOT NULL;");

	if($db->field_exists('linkhover', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP linkhover;");
	}
	$db->write_query("ALTER TABLE themes ADD linkhover varchar(15) NOT NULL AFTER link;");

	if($db->field_exists('extracss', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP extracss;");
	}
	$db->write_query("ALTER TABLE themes ADD extracss varchar(10) NOT NULL AFTER linkhover;");


	$db->write_query("UPDATE themes SET linkhover='#000000'");

	$db->write_query("UPDATE themes SET smallfont='Verdana', smallfontsize='11px', normalfont='Verdana', normalfontsize='13px', largefont='Verdana', largefontsize='20px';");

	$contents .= "done</p>";

	$db->drop_table("settinggroups", false, false);
	$db->write_query("CREATE TABLE settinggroups (
	  gid smallint(6) NOT NULL auto_increment,
	  name varchar(220) NOT NULL default '',
	  description text NOT NULL,
	  disporder smallint(6) NOT NULL default '0',
	  isdefault char(3) NOT NULL default '',
	  PRIMARY KEY  (gid)
	);");

	$db->drop_table("settings", false, false);
	$db->write_query("CREATE TABLE settings (
	  sid smallint(6) NOT NULL auto_increment,
	  name varchar(120) NOT NULL default '',
	  title varchar(120) NOT NULL default '',
	  description text NOT NULL,
	  optionscode text NOT NULL,
	  value text NOT NULL,
	  disporder smallint(6) NOT NULL default '0',
	  gid smallint(6) NOT NULL default '0',
	  PRIMARY KEY  (sid)
	);");

	$output->print_contents("$contents<p>Please click next to continue with the upgrade process.</p>");
	$output->print_footer("1_dbchanges2");
}
function upgrade1_dbchanges2()
{
	global $db, $output;

	$output->print_header("Database Changes since Release Candidate 2");

	$db->write_query("DELETE FROM themes");
	$arr = @file("./resources/theme.mybb");
	$contents = @implode("", $arr);

	// now we have $contents we can start dealing with the .theme file!
	$themee = explode("(_!&!_)", $contents);
	$thver = $themee[0];
	$thname = "Light";
	$thcontents = $themee[2];
	$thtemplates = $themee[3];

	// go through the actual theme part (colours etc)
	$themefile = explode("|#*^^&&^^*#|", $thcontents);
	$themeq1 = "";
	$themeq2 = "";
	foreach($themefile as $key => $val)
	{
		list($item, $value) = explode("|#*!!**!!*#|", $val);
		if($db->field_exists($item, "themes"))
		{
			$themebits[$item] = $value;
		}
	}

	// add the theme set
	$tquery1 = "";
	$tquery2 = "";
	unset($themebits['templateset']);
	foreach($themebits as $key => $val)
	{
		if($key && $val)
		{
			$tquery1 .= ",$key";
			$tquery2 .= ",'$val'";
		}
	}
	$db->write_query("INSERT INTO themes (tid,name,templateset$tquery1) VALUES ('','((master))','$sid'$tquery2)");
	$thetid = $db->write_query("INSERT INTO themes (tid,name,templateset$tquery1) VALUES ('','$thname','$sid2'$tquery2)");
	$db->write_query("UPDATE themes SET def='1' WHERE tid='$thetid'");

	$output->print_contents("Theme imported<p>Please click next to continue with the upgrade process.</p>");
	$output->print_footer("1_dbchanges3");
}
function upgrade1_dbchanges3()
{

	global $db, $output;

	$output->print_header("Database Changes since Release Candidate 2");

	$contents .= "<p>Reinserting all board settings...";

	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (1, 'General Configuration', 'This section contains various settings such as your board name and url, as well as your website name and url.', 2, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (3, 'Date and Time Formats', 'Here you can specify the different date and time formats used to display dates and times on the forums.', 4, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (7, 'Forum Display Options', 'This section allows you to manage the various settings used on the forum fisplay (forumdisplay.php) of your boards such as enabling and disabling different features.', 6, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (8, 'Show Thread Options', 'This section allows you to manage the various settings used on the thread display page (showthread.php) of your boards such as enabling and disabling different features.', 7, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (10, 'MyCode and Formatting Options', '', 16, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (11, 'Private Messaging', 'Various options with relation to the MyBB Private Messaging system (private.php) can be managed and set here.', 11, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (12, 'Member List', 'This section allows you to control various aspects of the board member listing (memberlist.php), such as how many members to show per page, and which features to enable or disable.', 10, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (13, 'Posting', 'These options control the various elements in relation to posting messages on the forums.', 9, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (14, 'Banning Options', '', 15, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (16, 'Forum Home Options', 'This section allows you to manage the various settings used on the forum home (index.php) of your boards such as enabling and disabling different features.', 5, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (17, 'Calendar', 'The board calendar allows the public and private listing of events and members\' birthdays. This section allows you to control and manage the settings for the Calendar.', 12, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (27, 'Server and Optimization Options', 'These options allow you to set various server and optimization preferences allowing you to reduce the load on your server, and gain better performance on your board.', 3, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (19, 'User Registration and Profile Options', 'Here you can control various settings with relation to user account registration and account management.', 8, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (20, 'Clickable Smilies and BB Code', '', 17, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (21, 'Common Language Bits', '', 18, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (23, 'Who\'s Online', '', 13, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (26, 'Board Online / Offline', 'These settings allow you to globally turn your forums online or offline, and allow you to specify a reason for turning them off.', 1, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (28, 'Control Panel Preferences (Global)', '', 19, 'yes');");
	$db->write_query("INSERT INTO `settinggroups` (`gid`, `name`, `description`, `disporder`, `isdefault`) VALUES (30, 'Portal Settings', '', 14, 'yes');");

	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (1, 'boardclosed', 'Board Closed', 'If you need to close your forums to make some changes or perform an upgrade, this is the global switch. Viewers will not be able to view your forums, however, they will see a message with the reason you specify below.<br />\r\n<br />\r\n<b>Administrators will still be able to view the forums.</b>', 'yesno', 'no', 1, 26);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (2, 'boardclosed_reason', 'Board Closed Reason', 'If your forum is closed, you can set a message here that your visitors will be able to see when they visit your forums.', 'textarea', 'These forums are currently closed for maintenance. Please check back later.', 2, 26);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (3, 'bbname', 'Board Name', 'The name of your message boards. We recommend that it is not over 75 characters.', 'text', 'MyBB Community Forums', 1, 1);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (4, 'bburl', 'Board URL', 'The url to your forums.<br />Include the http://. Do NOT include a trailing slash.', 'text', 'http://www.mybb.com/community', 2, 1);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (5, 'homename', 'Homepage Name', 'The name of your homepage. This will appear in the footer with a link to it.', 'text', 'MyBB', 3, 1);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (6, 'homeurl', 'Homepage URL', 'The full URL of your homepage. This will be linked to in the footer along with its name.', 'text', 'http://www.mybb.com', 4, 1);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (7, 'reportviapms', 'Send Reported Posts via PMS', 'Do you want to send reported post messages via the private messaging function to moderators?', 'yesno', 'yes', 5, 8);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (8, 'dateformat', 'Date Format', 'The format of the dates used on the forum. This format uses the PHP date() function. We recommend not changing this unless you know what you\'re doing.', 'text', 'm-d-Y', 1, 3);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (9, 'adminemail', 'Admin Email', 'The administrator\'s email address. This will be used for outgoing emails sent via the forums.', 'text', 'contact@mybb.com', 1, 1);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (10, 'timeformat', 'Time Format', 'The format of the times used on the forum. This format uses PHP\'s date() function. We recommend not changing this unless you know what you\'re doing.', 'text', 'h:i A', 2, 3);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (11, 'threadsperpage', 'Threads Per Page', '', 'text', '20', 1, 7);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (12, 'stickyprefix', 'Sticky Threads Prefix', 'The prefix of topics which have been made sticky by a moderator or administrator.', 'text', '<b>Sticky:</b>', 2, 7);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (13, 'hottopic', 'Replys For Hot Topic', 'The number of replies that is needed for a topic to be considered \'hot\'.', 'text', '20', 3, 7);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (14, 'cookiedomain', 'Cookie Domain', 'The domain which cookies should be set to. This can remain blank. It should also start with a . so it covers all subdomains.', 'text', '', 5, 1);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (15, 'cookiepath', 'Cookie Path', 'The path which cookies are set to, we recommend setting this to the full directory path to your forums with a trailing slash.', 'text', '', 6, 1);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (16, 'pollprefix', 'Poll Prefix', 'The prefix on forum display which contain polls.', 'text', '<b>Poll:</b>', 4, 7);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (17, 'postsperpage', 'Posts Per Page:', 'The number of posts to display per page. We recommend its not higher than 20 for people with slower connections.', 'text', '10', 1, 8);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (18, 'regdateformat', 'Registered Date Format', 'The format used on showthread where it shows when the user registered.', 'text', 'M Y', 3, 3);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (19, 'sigmycode', 'Allow MyCode in Signatures', 'Do you want to allow MyCode to be used in users\' signatures?', 'yesno', 'yes', 1, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (20, 'sigsmilies', 'Allow Smilies in Signatures', 'Do you want to allow smilies to be used in users\' signatures?', 'yesno', 'yes', 3, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (21, 'sightml', 'Allow HTML in Signatures', 'Do you want to allow HTML to be used in users\' signatures?', 'yesno', 'no', 4, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (22, 'sigimgcode', 'Allow [img] Code in Signatures', 'Do you want to allow [img] code to be used in users\' signatures?', 'yesno', 'yes', 5, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (23, 'quoteboxstyle', 'Fancy Quote Boxes', 'Selecting yes will cause quotes to be in a table and look more professional. Selecting no will show quotes in the traditional way.', 'yesno', 'yes', 1, 10);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (24, 'codeboxstyle', 'Fancy Code Boxes', 'Selecting yes will cause code to be in a table and look more professional. Selecting no will show code in the traditional way.', 'yesno', 'yes', 2, 10);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (25, 'threadusenetstyle', 'Usenet Style Thread View', 'Selecting yes will cause posts to look similar to how posts look in USENET. No will cause posts to look the modern way.', 'yesno', 'no', 3, 8);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (26, 'pmsallowhtml', 'Allow HTML', 'Selecting yes will allow HTML to be used in private messages.', 'yesno', 'no', 1, 11);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (27, 'pmsallowmycode', 'Allow MyCode', 'Selecting yes will allow MyCode to be used in private messages.', 'yesno', 'yes', 2, 11);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (28, 'pmsallowsmilies', 'Allow Smilies', 'Selecting yes will allow Smilies to be used in private messages.', 'yesno', 'yes', 3, 11);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (29, 'pmsallowimgcode', 'Allow [img] Code', 'Selecting yes will allow [img] Code to be used in private messages.', 'yesno', 'yes', 4, 11);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (30, 'siglength', 'Length limit in Signatures', 'The maximum number of characters a user can place in a signature.', 'text', '255', 6, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (31, 'messagelength', 'Maximum Message Length', 'The maximum number of characters to allow in a message. A setting of 0 allows an unlimited length.', 'text', '0', 1, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (32, 'membersperpage', 'Members Per Page', 'The number of members to show per page on the member list.', 'text', '20', 1, 12);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (33, 'load', '*NIX Load Limiting', 'Limit the maximum server load before myBB rejects people.  0 for none.  Recommended limit is 5.0.', 'text', '0', 5, 27);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (34, 'emailkeep', 'Users Keep Email', 'If a current user has an email already registered in your banned list, should he be allowed to keep it.', 'yesno', 'no', 4, 14);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (35, 'ipban', 'Ban by IP', 'Here, you may specify IP addresses or a range of IP addresses.  You must separate each IP with a space.', 'textarea', '', 2, 14);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (36, 'emailban', 'Ban by Email', 'You may specify specific email addresses to ban, or you may specify a domain.  You must separate email addresses and domains with a space.', 'textarea', '', 3, 14);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (37, 'avatarsize', 'Max Uploaded Avatar Size', 'Maximum file size (in kilobytes) of uploaded avatars.', 'text', '10', 8, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (38, 'avatardir', 'Avatar Directory', 'The directory where your avatars are stored. These are used in the avatar list in the User CP.', 'text', 'images/avatars', 7, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (39, 'showeditedby', 'Show \'edited by\' Messages', 'Once a post is edited by a regular user, do you want to show the edited by message?', 'yesno', 'yes', 6, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (40, 'maxposts', 'Maximum Posts Per Day', 'This is the total number of posts allowed per user per day.  0 for unlimited.', 'text', '0', 2, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (41, 'showeditedbyadmin', 'Show \'edited by\' Message for Forum Staff', 'Do you want to show edited by messages for forum staff when they edit their posts?', 'yesno', 'yes', 7, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (42, 'bannedusernames', 'Banned Usernames', 'Ban users from registering certain usernames.  Seperate them with a space.', 'textarea', 'drcracker Oops! hmmm', 1, 14);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (43, 'maxpolloptions', 'Maximum Number of Poll Options', 'The maximum number of options for polls that users can post.', 'text', '10', 3, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (44, 'dotfolders', 'Use \'dot\' Icons', 'Do you want to show dots on the thread indicators of threads users have participated in.', 'yesno', 'yes', 8, 7);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (45, 'contactlink', 'Contact Us Link', 'This will be used for the Contact Us link on the bottom of all the forum pages. Can either be an email address (using mailto:email@website.com) or a hyperlink.', 'text', 'mailto:contact@mybb.com', 2, 1);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (46, 'showdescriptions', 'Show Forum Descriptions?', 'This option will allow you to turn off showing the descriptions for forums.', 'yesno', 'yes', 1, 16);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (47, 'showbirthdays', 'Show Today\'s Birthdays?', 'Do you want to show today\'s birthdays on the forum homepage?', 'yesno', 'yes', 2, 16);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (48, 'showwol', 'Show Who\'s Online?', 'Display the currently active users on the forum home page.', 'yesno', 'yes', 4, 16);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (49, 'hideprivateforums', 'Hide Private Forums?', 'You can hide private forums by turning this option on. This option also hides forums on the forum jump and all subforums.', 'yesno', 'yes', 3, 16);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (50, 'showsimilarthreads', 'Show \'Similar Threads\' Table', 'The Similar Threads table shows threads that are relevant to the thread being read. You can set the relevancy below.', 'yesno', 'no', 4, 8);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (51, 'similarityrating', 'Similar Threads Relevancy Rating', 'This allows you to limit similar threads to ones more relevant (0 being not relevant). This number should not be over 10 and should not be set low (<5) for large forums.', 'text', '1', 6, 8);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (52, 'similarlimit', 'Similar Threads Limit', 'Here you can change the total amount of similar threads to be shown in the similar threads table. It is recommended that it is not over 15 for 56k users.', 'text', '10', 7, 8);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (53, 'privateeventcolor', 'Private Events Color', 'The color that private events will be shown in on the main calendar page.', 'text', 'red', 2, 17);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (54, 'publiceventcolor', 'Public Events Color', 'The color that public events will be shown in on the main calendar page.', 'text', 'green', 1, 17);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (55, 'movedprefix', 'Moved Threads Prefix', 'The prefix that threads that have been moved to another forum should have.', 'text', '<b>Moved:</b>', 5, 7);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (56, 'hottopicviews', 'Views For Hot Topic', 'The number of views a thread can have before it is considered \'hot\'.', 'text', '150', 7, 7);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (59, 'logip', 'Log Posting IP Addresses', 'Do you wish to log ip addresses of users who post, and who you want to show ip addresses to.', 'radio\r\nno=Do not log IP\r\nhide=Show to Admins & Mods\r\nshow=Show to all Users', 'hide', 3, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (60, 'statslimit', 'Stats Limit', 'The number of threads to show on the stats page for most replies and most views.', 'text', '15', 5, 1);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (65, 'modlist', 'Forums\' Moderator Listing', 'Here you can turn on or off the listing of moderators for each forum on index.php and forumdisplay.php', 'onoff', 'on', 5, 16);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (66, 'smilieinserter', 'Clickable Smilies Inserter', 'Clickable smilies will appear on the posting pages if this option is set to \'on\'.', 'onoff', 'on', 1, 20);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (67, 'smilieinsertertot', 'No. of Smilies to show', 'Enter the total number of smilies to show on the clickable smilie inserter.', 'text', '20', 2, 20);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (68, 'smilieinsertercols', 'No. of Smilie Cols to Show', 'Enter the number of columns you wish to show on the clickable smilie inserter.', 'text', '4', 3, 20);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (69, 'cantext', '\'Can\' text', 'The text that will be used in templates when the user has permission to perform an action (eg. post new threads).', 'text', '<b>can</b>', 1, 21);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (70, 'canttext', '\'Cannot\' text', 'The text that will be used in templates when the user does not have permission to perform an action (eg. post new threads).', 'text', '<b>cannot</b>', 2, 21);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (71, 'ontext', '\'On\' text', 'The text that will be used when a feature is turned On (eg. BB Code in posts).', 'text', '<b>On</b>', 3, 21);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (72, 'offtext', '\'Off\' text', 'The text that will be used when a feature is turned Off (eg. BB Code in posts).', 'text', '<b>Off</b>', 4, 21);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (73, 'showindexstats', 'Show Small Stats Section', 'Do you want to show the total number of threads, posts, members, and the last member on the forum home?', 'yesno', 'yes', 6, 16);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (74, 'regtype', 'Registration Method', 'Please select the method of registration to use when users register.', 'select\r\ninstant=Instant Activation\r\nverify=Send Email Verification\r\nrandompass=Send Random Password', 'verify', 1, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (75, 'userpppoptions', 'User Selectable Posts Per Page', 'If you would like to allow users to select how many posts are shown per page in a thread, enter the options they should be able to select separated with commas. If this is left blank they will not be able to choose how many posts are shown per page.', 'text', '5,10,20,25,30,40,50', 2, 8);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (76, 'usertppoptions', 'User Selectable Threads Per Page', 'If you would like to allow users to select how many threads per page are shown in a forum, enter the options they should be able to select separated with commas. If this is left blank they will not be able to choose how many threads are shown per page.', 'text', '10,20,25,30,40,50', 6, 7);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (77, 'wolcutoffmins', 'Cut-off Time (mins)', 'The number of minutes before a user is marked offline. Recommended: 15.', 'text', '15', 1, 23);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (78, 'postfloodcheck', 'Post Flood Checking', 'Set to on if you want to enable flood checking for posts. Specifiy the time between posts below.', 'onoff', 'on', 4, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (79, 'postfloodsecs', 'Post Flood Time', 'Set the time (in seconds) users have to wait between posting, to be in effect; the option above must be on.', 'text', '60', 5, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (80, 'gzipoutput', 'Use GZip Page Compression?', 'Do you want to compress pages in GZip format when they are sent to the browser? This means quicker downloads for your visitors, and less traffic usage for you. The level of the compression is set by the server\'s load.', 'yesno', 'yes', 1, 27);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (81, 'standardheaders', 'Send Standard Headers', 'With some web servers, this option can cause problems; with others, it is needed. ', 'yesno', 'no', 2, 27);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (82, 'nocacheheaders', 'Send No Cache Headers', 'With this option you can prevent caching of the page by the browser.', 'yesno', 'no', 3, 27);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (83, 'maxpostimages', 'Maximum Images per Post', 'Enter the maximum number of images (including smilies) a user can put in their post. Set to 0 to disable this.', 'text', '10', 8, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (84, 'maxsigimages', 'Maximum Number of Images per Signature', 'Enter the maximum number of images (including smilies) a user can put in their signature. Set to 0 to disable this.', 'text', '2', 2, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (85, 'browsingthisforum', 'Users Browsing this Forum', 'Here you can turn off the \'users browsing this forum\' feature.', 'onoff', 'on', 9, 7);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (86, 'usereferrals', 'Use Referrals System', 'Do you want to use the user referrals system on these forums?', 'yesno', 'yes', 3, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (87, 'subscribeexcerpt', 'Amount of Characters for Subscription Previews', 'How many characters of the post do you want to send with the email notification of a new reply.', 'text', '100', 9, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (93, 'cpstyle', 'Control Panel Style', 'The Default style that the control panel will use. Styles are inside the styles folder. A folder name inside that folder becomes the style title and style.css inside the style title folder is the css style file.', 'cpstyle', 'Axiom', 1, 28);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (121, 'cplanguage', 'Control Plane Language', 'The language of the control panel.', 'language', 'english', 1, 28);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (94, 'minnamelength', 'Minimum Username Length', 'The minimum number of characters a username can be when a user registers.', 'text', '3', 5, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (95, 'maxnamelength', 'Maximum Username Length', 'The maximum number of characters a username can be when a user registers.', 'text', '30', 6, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (96, 'redirects', 'Friendly Redirection Pages', 'This will enable friendly redirection pages instead of bumping the user directly to the page.', 'onoff', 'on', 4, 27);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (97, 'betweenregstime', 'Time Between Registrations', 'The amount of time (in hours) to disallow registrations for users who have already registered an account under the same ip address.', 'text', '24', 2, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (98, 'maxregsbetweentime', 'Maximum Registrations Per IP Address', 'This option allows you to set the maximum amount of times a certain user can register within the timeframe specified above.', 'text', '2', 4, 19);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (111, 'portal_showstats', 'Show forum statistics', 'Do you want to show the total number of posts, threads, members and the last registered member on the portal page?', 'yesno', 'yes', 5, 30);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (110, 'portal_showpms', 'Show the number of PMs to users', 'Do you want to show the number of private messages the current user has in their pm system.', 'yesno', 'yes', 4, 30);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (109, 'portal_showwelcome', 'Show the Welcome box', 'Do you want to show the welcome box to visitors / users.', 'yesno', 'yes', 3, 30);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (108, 'portal_numannouncements', 'Number of announcements to show', 'Please enter the number of announcements to show on the main page.', 'text', '10', 2, 30);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (103, 'portal_showstats', 'Show forum statistics', 'Do you want to show the total number of posts, threads, members and the last registered member on the portal page?', 'yesno', 'yes', 5, 29);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (104, 'portal_showwol', 'Show Whos Online', 'Do you want to show the \'whos online\' information to users when they visit the portal page?', 'yesno', 'yes', 6, 29);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (107, 'portal_announcementsfid', 'Forum ID to pull announcements from', 'Please enter the forum id (fid) of the forum you wish to pull the announcements from', 'text', '2', 1, 30);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (106, 'portal_showdiscussionsnum', 'Number of latest discussions to show', 'Please enter the number of current forum discussions to show on the portal page.', 'text', '10', 8, 29);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (112, 'portal_showwol', 'Show Who\'s Online', 'Do you want to show the \'Who\'s online\' information to users when they visit the portal page?', 'yesno', 'yes', 6, 30);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (113, 'portal_showsearch', 'Show Search Box', 'Do you want to show the search box, allowing users to quickly search the forums on the portal?', 'yesno', 'yes', 7, 30);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (114, 'portal_showdiscussions', 'Show Latest Discussions', 'Do you wish to show the current forum discussions on the portal page?', 'yesno', 'yes', 8, 30);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (115, 'portal_showdiscussionsnum', 'Number of latest discussions to show', 'Please enter the number of current forum discussions to show on the portal page.', 'text', '10', 9, 30);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (116, 'attachthumbh', 'Attached Thumbnail Maximum Height', 'Enter the width that attached thumbnails should be generated at.', 'text', '60', 12, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (117, 'attachthumbw', 'Attached Thumbnail Maximum Width', 'Enter the width that attached thumbnails should be generated at.', 'text', '60', 13, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (118, 'maxattachments', 'Maximum Attachments Per Post', 'THe maximum number of attachments a user is allowed to upload per post.', 'text', '5', 10, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (119, 'attachthumbnails', 'Show Attached Thumbnails in Posts', 'Do you want to show the generated thumbnails for attached images inside the posts?', 'yesno', 'yes', 11, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (120, 'polloptionlimit', 'Maximum Poll Option Length', 'The maximum length that each poll option can be. (Set to 0 to disable).', 'text', '250', 1, 13);");
	$db->write_query("INSERT INTO `settings` (`sid`, `name`, `title`, `description`, `optionscode`, `value`, `disporder`, `gid`) VALUES (122, 'timezoneoffset', 'Default Timezone Offset', 'Here you can set the default timezone offset for guests and members using the default offset.', 'text', '+10', 5, 3);");

	$output->print_contents("$contents<p>Please click next to continue with the upgrade process.</p>");
	$output->print_footer("1_done");
}
