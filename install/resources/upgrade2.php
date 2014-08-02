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
 * Upgrade Script: Release Candidate 3
 */

$upgrade_detail = array(
	"revert_all_templates" => 1,
	"revert_all_themes" => 1,
	"revert_all_settings" => 1,
	"requires_deactivated_plugins" => 1,
);

function upgrade2_dbchanges()
{
	global $db, $output;

	$output->print_header("Database Changes since Release Candidate 3");

	$contents .= "<p>Making necessary database modifications...";

	$db->drop_table("badwords");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."badwords (
	  bid smallint(6) NOT NULL auto_increment,
	  badword varchar(100) NOT NULL,
	  replacement varchar(100) NOT NULL,
	  PRIMARY KEY(bid)
	);");

	if($db->field_exists("icon", TABLE_PREFIX."attachtypes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachtypes DROP icon;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachtypes ADD icon varchar(100) NOT NULL;");

	$db->delete_query("attachtypes");

	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (1, 'Zip File', 'application/zip', 'zip', 1024, 'images/attachtypes/zip.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (2, 'JPEG Image', 'image/jpeg', 'jpg', 500, 'images/attachtypes/image.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (3, 'Text Document', 'text/plain', 'txt', 200, 'images/attachtypes/txt.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (4, 'GIF Image', 'image/gif', 'gif', 500, 'images/attachtypes/image.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (6, 'PHP File', 'application/octet-stream', 'php', 500, 'images/attachtypes/php.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (7, 'PNG Image', 'image/png', 'png', 500, 'images/attachtypes/image.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (8, 'Microsoft Word Document', 'application/msword', 'doc', 1024, 'images/attachtypes/doc.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (9, '', 'application/octet-stream', 'htm', 100, 'images/attachtypes/html.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (10, '', 'application/octet-stream', 'html', 100, 'images/attachtypes/html.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (11, '', 'image/jpeg', 'jpeg', 500, 'images/attachtypes/image.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (12, '', 'application/x-gzip', 'gz', 1024, 'images/attachtypes/tgz.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (13, '', 'application/x-tar', 'tar', 1024, 'images/attachtypes/tar.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (14, '', 'text/css', 'css', 100, 'images/attachtypes/css.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (15, '', 'application/pdf', 'pdf', 2048, 'images/attachtypes/pdf.gif');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (16, '', 'image/bmp', 'bmp', 500, 'images/attachtypes/image.gif');");

	if($db->field_exists("outerwidth", TABLE_PREFIX."themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP outerwidth;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes ADD outerwidth varchar(15) NOT NULL;");

	if($db->field_exists("icon", TABLE_PREFIX."themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP icon;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes ADD outercolor varchar(15) NOT NULL;");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes CHANGE body bodybgcolor varchar(15) NOT NULL;");

	if($db->field_exists("bodybgimage", TABLE_PREFIX."themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP bodybgimage;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes ADD bodybgimage varchar(100) NOT NULL default '' AFTER bodybgcolor;");

	if($db->field_exists("bodybgimageattributes", TABLE_PREFIX."themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP bodydbimageattributes;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes ADD bodybgimageattributes varchar(100) NOT NULL default '' AFTER bodybgimage;");


	$db->write_query("UPDATE ".TABLE_PREFIX."themes SET outerwidth='0', bodybgcolor='#e3e3e3', bodybgimage='images/Light/logo_bg.png', bodybgimageattributes='repeat-x'");

	$db->drop_table("regimages");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."regimages (
	  imagehash varchar(32) NOT NULL,
	  imagestring varchar(8) NOT NULL,
	  dateline bigint(30) NOT NULL
	);");

	$db->write_query("UPDATE ".TABLE_PREFIX."adminoptions SET cpstyle=''");

	if($db->field_exists("language", TABLE_PREFIX."users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP language;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD language varchar(50) NOT NULL;");

	if($db->field_exists("timeonline", TABLE_PREFIX."users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP timeonline;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD timeonline bigint(30) NOT NULL default '0';");

	if($db->field_exists("showcodebuttons", TABLE_PREFIX."users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."user DROP showcodebuttons;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD showcodebuttons int(1) NOT NULL default '1';");

	$db->write_query("UPDATE ".TABLE_PREFIX."users SET language='english', showcodebuttons=1");

	if($db->field_exists("oldgroup", TABLE_PREFIX."awaitingactivation"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."awaitingactivation DROP oldgroup;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."awaitingactivation ADD oldgroup bigint(30) NOT NULL;");

	if($db->field_exists("misc", TABLE_PREFIX."awaitingactivation"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."awaitingactivation DROP misc;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."awaitingactivation ADD misc varchar(255) NOT NULL;");

	$db->write_query("DELETE FROM ".TABLE_PREFIX."awaitingactivation WHERE type='e'");

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
	);");

	$db->drop_table("reportedposts");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."reportedposts (
	  rid smallint(6) NOT NULL auto_increment,
	  pid smallint(6) NOT NULL,
	  tid smallint(6) NOT NULL,
	  fid smallint(6) NOT NULL,
	  uid smallint(6) NOT NULL,
	  reportstatus int(1) NOT NULL,
	  reason varchar(250) NOT NULL,
	  dateline bigint(30) NOT NULL,
	  PRIMARY KEY (rid)
	);");

	$db->drop_table("threadsread");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."threadsread (
	  tid smallint(6) NOT NULL,
	  uid smallint(6) NOT NULL,
	  dateline int(10) NOT NULL,
	  UNIQUE KEY tiduid (tid, uid)
	);");
	$contents .= "done</p>";

	$output->print_contents("$contents<p>Please click next to continue with the upgrade process.</p>");
	$output->print_footer("2_dbchanges2");
}

function upgrade2_dbchanges2()
{
	global $db, $output;

	$output->print_header("Database Changes since Release Candidate 3");
	$contents .= "<p>Reinserting settings...";

	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'boardclosed', 'Board Closed', 'If you need to close your forums to make some changes or perform an upgrade, this is the global switch. Viewers will not be able to view your forums, however, they will see a message with the reason you specify below.<br />\r\n<br />\r\n<b>Administrators will still be able to view the forums.</b>', 'yesno', 'no', 1, 26);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'boardclosed_reason', 'Board Closed Reason', 'If your forum is closed, you can set a message here that your visitors will be able to see when they visit your forums.', 'textarea', 'These forums are currently closed for maintenance. Please check back later.', 2, 26);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'bbname', 'Board Name', 'The name of your message boards. We recommend that it is not over 75 characters.', 'text', 'MyBB Forums', 1, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'bburl', 'Board URL', 'The url to your forums.<br />Include the http://. Do NOT include a trailing slash.', 'text', '', 2, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'homename', 'Homepage Name', 'The name of your homepage. This will appear in the footer with a link to it.', 'text', 'MyBB', 3, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'homeurl', 'Homepage URL', 'The full URL of your homepage. This will be linked to in the footer along with its name.', 'text', 'http://www.mybb.com', 4, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'dateformat', 'Date Format', 'The format of the dates used on the forum. This format uses the PHP date() function. We recommend not changing this unless you know what you\'re doing.', 'text', 'm-d-Y', 1, 3);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'adminemail', 'Admin Email', 'The administrator\'s email address. This will be used for outgoing emails sent via the forums.', 'text', '', 5, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'timeformat', 'Time Format', 'The format of the times used on the forum. This format uses PHP\'s date() function. We recommend not changing this unless you know what you\'re doing.', 'text', 'h:i A', 2, 3);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'threadsperpage', 'Threads Per Page', '', 'text', '20', 1, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'stickyprefix', 'Sticky Threads Prefix', 'The prefix of topics which have been made sticky by a moderator or administrator.', 'text', '<b>Sticky:</b>', 2, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'hottopic', 'Replys For Hot Topic', 'The number of replies that is needed for a topic to be considered \'hot\'.', 'text', '20', 3, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'cookiedomain', 'Cookie Domain', 'The domain which cookies should be set to. This can remain blank. It should also start with a . so it covers all subdomains.', 'text', '', 8, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'cookiepath', 'Cookie Path', 'The path which cookies are set to, we recommend setting this to the full directory path to your forums with a trailing slash.', 'text', '', 9, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'pollprefix', 'Poll Prefix', 'The prefix on forum display which contain polls.', 'text', '<b>Poll:</b>', 4, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'postsperpage', 'Posts Per Page:', 'The number of posts to display per page. We recommend its not higher than 20 for people with slower connections.', 'text', '10', 1, 8);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'regdateformat', 'Registered Date Format', 'The format used on showthread where it shows when the user registered.', 'text', 'M Y', 3, 3);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'sigmycode', 'Allow MyCode in Signatures', 'Do you want to allow MyCode to be used in users\' signatures?', 'yesno', 'yes', 1, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'sigsmilies', 'Allow Smilies in Signatures', 'Do you want to allow smilies to be used in users\' signatures?', 'yesno', 'yes', 3, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'sightml', 'Allow HTML in Signatures', 'Do you want to allow HTML to be used in users\' signatures?', 'yesno', 'no', 4, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'sigimgcode', 'Allow [img] Code in Signatures', 'Do you want to allow [img] code to be used in users\' signatures?', 'yesno', 'yes', 5, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'quoteboxstyle', 'Fancy Quote Boxes', 'Selecting yes will cause quotes to be in a table and look more professional. Selecting no will show quotes in the traditional way.', 'yesno', 'yes', 1, 10);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'codeboxstyle', 'Fancy Code Boxes', 'Selecting yes will cause code to be in a table and look more professional. Selecting no will show code in the traditional way.', 'yesno', 'yes', 2, 10);");
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
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showeditedby', 'Show \'edited by\' Messages', 'Once a post is edited by a regular user, do you want to show the edited by message?', 'yesno', 'yes', 6, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'maxposts', 'Maximum Posts Per Day', 'This is the total number of posts allowed per user per day.  0 for unlimited.', 'text', '0', 2, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showeditedbyadmin', 'Show \'edited by\' Message for Forum Staff', 'Do you want to show edited by messages for forum staff when they edit their posts?', 'yesno', 'yes', 7, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'bannedusernames', 'Banned Usernames', 'Ban users from registering certain usernames.  Seperate them with a space.', 'textarea', 'drcracker Oops! hmmm', 1, 14);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'maxpolloptions', 'Maximum Number of Poll Options', 'The maximum number of options for polls that users can post.', 'text', '10', 3, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'dotfolders', 'Use \'dot\' Icons', 'Do you want to show dots on the thread indicators of threads users have participated in.', 'yesno', 'yes', 8, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'contactlink', 'Contact Us Link', 'This will be used for the Contact Us link on the bottom of all the forum pages. Can either be an email address (using mailto:email@website.com) or a hyperlink.', 'text', 'mailto:contact@mybb.com', 6, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showdescriptions', 'Show Forum Descriptions?', 'This option will allow you to turn off showing the descriptions for forums.', 'yesno', 'yes', 1, 16);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showbirthdays', 'Show Today\'s Birthdays?', 'Do you want to show today\'s birthdays on the forum homepage?', 'yesno', 'yes', 2, 16);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showwol', 'Show Who\'s Online?', 'Display the currently active users on the forum home page.', 'yesno', 'yes', 4, 16);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'hideprivateforums', 'Hide Private Forums?', 'You can hide private forums by turning this option on. This option also hides forums on the forum jump and all subforums.', 'yesno', 'yes', 3, 16);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showsimilarthreads', 'Show \'Similar Threads\' Table', 'The Similar Threads table shows threads that are relevant to the thread being read. You can set the relevancy below.', 'yesno', 'no', 5, 8);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'similarityrating', 'Similar Threads Relevancy Rating', 'This allows you to limit similar threads to ones more relevant (0 being not relevant). This number should not be over 10 and should not be set low (<5) for large forums.', 'text', '1', 7, 8);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'similarlimit', 'Similar Threads Limit', 'Here you can change the total amount of similar threads to be shown in the similar threads table. It is recommended that it is not over 15 for 56k users.', 'text', '10', 8, 8);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'privateeventcolor', 'Private Events Color', 'The color that private events will be shown in on the main calendar page.', 'text', 'red', 2, 17);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'publiceventcolor', 'Public Events Color', 'The color that public events will be shown in on the main calendar page.', 'text', 'green', 1, 17);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'movedprefix', 'Moved Threads Prefix', 'The prefix that threads that have been moved to another forum should have.', 'text', '<b>Moved:</b>', 5, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'hottopicviews', 'Views For Hot Topic', 'The number of views a thread can have before it is considered \'hot\'.', 'text', '150', 7, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'logip', 'Log Posting IP Addresses', 'Do you wish to log ip addresses of users who post, and who you want to show ip addresses to.', 'radio\r\nno=Do not log IP\r\nhide=Show to Admins & Mods\r\nshow=Show to all Users', 'hide', 3, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'statslimit', 'Stats Limit', 'The number of threads to show on the stats page for most replies and most views.', 'text', '15', 10, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'modlist', 'Forums\' Moderator Listing', 'Here you can turn on or off the listing of moderators for each forum on index.php and forumdisplay.php', 'onoff', 'on', 5, 16);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'smilieinserter', 'Clickable Smilies Inserter', 'Clickable smilies will appear on the posting pages if this option is set to \'on\'.', 'onoff', 'on', 1, 20);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'smilieinsertertot', 'No. of Smilies to show', 'Enter the total number of smilies to show on the clickable smilie inserter.', 'text', '20', 2, 20);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'smilieinsertercols', 'No. of Smilie Cols to Show', 'Enter the number of columns you wish to show on the clickable smilie inserter.', 'text', '4', 3, 20);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'showindexstats', 'Show Small Stats Section', 'Do you want to show the total number of threads, posts, members, and the last member on the forum home?', 'yesno', 'yes', 6, 16);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'regtype', 'Registration Method', 'Please select the method of registration to use when users register.', 'select\r\ninstant=Instant Activation\r\nverify=Send Email Verification\r\nrandompass=Send Random Password', 'verify', 1, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'userpppoptions', 'User Selectable Posts Per Page', 'If you would like to allow users to select how many posts are shown per page in a thread, enter the options they should be able to select separated with commas. If this is left blank they will not be able to choose how many posts are shown per page.', 'text', '5,10,20,25,30,40,50', 2, 8);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'usertppoptions', 'User Selectable Threads Per Page', 'If you would like to allow users to select how many threads per page are shown in a forum, enter the options they should be able to select separated with commas. If this is left blank they will not be able to choose how many threads are shown per page.', 'text', '10,20,25,30,40,50', 6, 7);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'wolcutoffmins', 'Cut-off Time (mins)', 'The number of minutes before a user is marked offline. Recommended: 15.', 'text', '15', 1, 23);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'postfloodcheck', 'Post Flood Checking', 'Set to on if you want to enable flood checking for posts. Specifiy the time between posts below.', 'onoff', 'on', 4, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'postfloodsecs', 'Post Flood Time', 'Set the time (in seconds) users have to wait between posting, to be in effect; the option above must be on.', 'text', '60', 5, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'gzipoutput', 'Use GZip Page Compression?', 'Do you want to compress pages in GZip format when they are sent to the browser? This means quicker downloads for your visitors, and less traffic usage for you. The level of the compression is set by the server\'s load.', 'yesno', 'yes', 1, 27);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'standardheaders', 'Send Standard Headers', 'With some web servers, this option can cause problems; with others, it is needed. ', 'yesno', 'no', 2, 27);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'nocacheheaders', 'Send No Cache Headers', 'With this option you can prevent caching of the page by the browser.', 'yesno', 'no', 3, 27);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'maxpostimages', 'Maximum Images per Post', 'Enter the maximum number of images (including smilies) a user can put in their post. Set to 0 to disable this.', 'text', '10', 8, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'maxsigimages', 'Maximum Number of Images per Signature', 'Enter the maximum number of images (including smilies) a user can put in their signature. Set to 0 to disable this.', 'text', '2', 2, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'browsingthisforum', 'Users Browsing this Forum', 'Here you can turn off the \'users browsing this forum\' feature.', 'onoff', 'on', 9, 7);");
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
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showwol', 'Show Whos Online', 'Do you want to show the \'whos online\' information to users when they visit the portal page?', 'yesno', 'yes', 6, 29);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_announcementsfid', 'Forum ID to pull announcements from', 'Please enter the forum id (fid) of the forum you wish to pull the announcements from', 'text', '1', 1, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showdiscussionsnum', 'Number of latest discussions to show', 'Please enter the number of current forum discussions to show on the portal page.', 'text', '10', 8, 29);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showwol', 'Show Who\'s Online', 'Do you want to show the \'Who\'s online\' information to users when they visit the portal page?', 'yesno', 'yes', 6, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showsearch', 'Show Search Box', 'Do you want to show the search box, allowing users to quickly search the forums on the portal?', 'yesno', 'yes', 7, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showdiscussions', 'Show Latest Discussions', 'Do you wish to show the current forum discussions on the portal page?', 'yesno', 'yes', 8, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'portal_showdiscussionsnum', 'Number of latest discussions to show', 'Please enter the number of current forum discussions to show on the portal page.', 'text', '10', 9, 30);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'attachthumbh', 'Attached Thumbnail Maximum Height', 'Enter the width that attached thumbnails should be generated at.', 'text', '60', 12, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'attachthumbw', 'Attached Thumbnail Maximum Width', 'Enter the width that attached thumbnails should be generated at.', 'text', '60', 13, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'maxattachments', 'Maximum Attachments Per Post', 'THe maximum number of attachments a user is allowed to upload per post.', 'text', '5', 10, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'attachthumbnails', 'Show Attached Thumbnails in Posts', 'Do you want to show the generated thumbnails for attached images inside the posts?', 'yesno', 'yes', 11, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'polloptionlimit', 'Maximum Poll Option Length', 'The maximum length that each poll option can be. (Set to 0 to disable).', 'text', '250', 1, 13);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'timezoneoffset', 'Default Timezone Offset', 'Here you can set the default timezone offset for guests and members using the default offset.', 'text', '+10', 4, 3);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'bblanguage', 'Default Language', 'The default language that MyBB should use for guests and for users without a selected language in their user control panel.', 'language', 'english', 7, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'regimage', 'Antispam Registration Image', 'If yes, and GD is installed, an image will be shown during registration where users are required to enter the text contained within the image to continue with registration.', 'onoff', 'on', 1, 19);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'reportmethod', 'Reported Posts Medium', 'Please select from the list how you want reported posts to be dealt with. Storing them in the database is probably the better of the options listed.', 'radio\r\ndb=Stored in the Database\r\npms=Sent as Private Messages\r\nemail=Sent via Email', 'db', 1, 1);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'threadreadcut', 'Read Threads in Database (Days)', 'The number of days that you wish to keep thread read information in the database. For large boards, we do not recommend a high number as the board will become slower. Set to 0 to disable.', 'text', '7', 3, 8);");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (NULL, 'announcementlimit', 'Announcements Limit', 'The number of forum announcements to  show in the thread listing on the forum display pages. Set to 0 to show all active announcements.', 'text', '2', 10, 7);");

	$output->print_contents("$contents<p>Please click next to continue with the upgrade process.</p>");
	$output->print_footer("2_done");
}
