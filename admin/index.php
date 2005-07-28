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

require "./global.php";

// Load language packs for this section
global $lang;
$lang->load("index");

if ($mybb->input['action']=="header")
{
	echo "<html>\n";
	echo "<head>";
	echo "<link rel=\"stylesheet\" href=\"$style\">";
	echo "</head>";
	echo "<body id=\"logo\">";
	echo "<h1><span class=\"hidden\">MyBB Admin CP</span></h1>";
	$lang->logout_cp = sprintf($lang->logout_cp, $user['username']);
	echo "<div id=\"header-links\"><a href=\"index.php?action=home\" target=\"body\">".$lang->cp_home."</a><a href=\"../index.php\" target=\"body\">".$lang->view_forums."</a><a href=\"index.php?action=logout\" target=\"_parent\">".$lang->logout_cp."</a></div>";
	echo "</body>";
	echo "</html>";
}

elseif ($mybb->input['action']=="home")
{
	logadmin();
	cpheader();
	// Get statistics
	$phpversion = phpversion();
	$dbversion = mysql_get_server_info();
	$serverload = serverload();
	if(!$serverload)
	{
		$serverload = $lang->unknown;
	}
	// Get the number of users
	$query = $db->query("SELECT COUNT(*) AS numusers FROM ".TABLE_PREFIX."users");
	$users = $db->fetch_array($query);
	
	// Get the number of users awaiting validation
	$query = $db->query("SELECT COUNT(*) AS awaitingusers FROM ".TABLE_PREFIX."users WHERE usergroup='5'");
	$awaitingusers = $db->fetch_array($query);
	
	// Get the number of new users for today
	$timecut = time() - 86400;
	$query = $db->query("SELECT COUNT(*) AS newusers FROM ".TABLE_PREFIX."users WHERE regdate>'$timecut'");
	$newusers = $db->fetch_array($query);
	
	// Get the number of active users today
	$timecut = time() - 86400;
	$query = $db->query("SELECT COUNT(*) AS activeusers FROM ".TABLE_PREFIX."users WHERE lastvisit>'$timecut'");
	$activeusers = $db->fetch_array($query);
	
	// Get the number of threads
	$query = $db->query("SELECT COUNT(*) AS numthreads FROM ".TABLE_PREFIX."threads");
	$threads = $db->fetch_array($query);
	
	// Get the number of new threads for today
	$timecut = time() - 86400;
	$query = $db->query("SELECT COUNT(*) AS newthreads FROM ".TABLE_PREFIX."threads WHERE dateline>'$timecut'");
	$newthreads = $db->fetch_array($query);

	// Get the number of posts
	$query = $db->query("SELECT COUNT(*) AS numposts FROM ".TABLE_PREFIX."posts");
	$posts = $db->fetch_array($query);
	
	// Get the number of new posts for today
	$timecut = time() - 86400;
	$query = $db->query("SELECT COUNT(*) AS newposts FROM ".TABLE_PREFIX."posts WHERE dateline>'$timecut'");
	$newposts = $db->fetch_array($query);

	// Program Statistics table
	starttable();
	tableheader($lang->welcome, "", 4);
	tablesubheader($lang->program_stats, "", 4);
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>".$lang->mybb_version."</b></td><td valign=\"top\" class=\"altbg2\">$mybboard[internalver]</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->php_version</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"index.php?action=phpinfo\">$phpversion</a></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->mysql_version</b></td><td valign=\"top\" class=\"altbg2\">$dbversion</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->server_load</b></td><td valign=\"top\" class=\"altbg2\">$serverload</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->total_users</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"users.php?action=find\">$users[numusers]</a></td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->users_awaiting_activation</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"users.php?action=find&search[additionalgroups][]=5&searchop[sortby]=regdate&searchop[order]=desc\">$awaitingusers[awaitingusers]</a></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->new_users_today</b></td><td valign=\"top\" class=\"altbg2\">$newusers[newusers]</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->active_users_today</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"../online.php?action=today\">$activeusers[activeusers]</a></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->total_threads</b></td><td valign=\"top\" class=\"altbg2\">$threads[numthreads]</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->threads_today</b></td><td valign=\"top\" class=\"altbg2\">$newthreads[newthreads]</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->total_posts</b></td><td valign=\"top\" class=\"altbg2\">$posts[numposts]</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->posts_today</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"../search.php?action=getdaily\">$newposts[newposts]</a></td>\n";
	echo "</tr>\n";
	endtable();

	// Quick admin options
	starttable();
	tableheader($lang->quick_options);
	tablesubheader($lang->quick_user_finder);
	startform("users.php", "", "find");
	makeinputcode($lang->find_user_account, "search[username]", "", "", "&nbsp;".makebuttoncode("find", $lang->find_button)."&nbsp;".makebuttoncode("listall", $lang->list_all_users));
	endform();
	tablesubheader($lang->quick_forum_add);
	startform("forums.php", "", "add");
	makeinputcode($lang->new_forum_name, "fname", "", "", "&nbsp;".makebuttoncode("add", $lang->add_button));
	endform();
	endtable();

	// MyBB Credits
	starttable();
	tableheader($lang->mybb_credits);
	tablesubheader($lang->contributed);
	makelabelcode("<b>$lang->product_managers</b>", "<a href=\"http://www.surfionline.com\">Chris Boulton</a><br>");
	makelabelcode("<b>$lang->developers</b>", "<a href=\"http://www.surfionline.com\">Chris Boulton</a><br><a href=\"http://www.mybbmods.com/\">Musicalmidget</a>");
	makelabelcode("<b>$lang->graphics_and_style</b>", "<a href=\"http://www.surfionline.com\">Chris Boulton</a><br /><a href=\"http://www.templatesforall.com\">Scott Hough</a>");
	endtable();

	// Installed Language Packs
	starttable();
	tableheader($lang->installed_langs);
	tablesubheader($lang->lang_authors);
	$langdir = "./inc/languages/";
	$dir = opendir($langdir);
	$langs = array();
	while(($lang1 = readdir($dir)) !== false)
	{
		if(filetype($langdir.$lang1) == 'file')
		{
			$langs[] = $langdir.$lang1;
		}
	}
	closedir($dir);
	sort($langs);
	while(list($key, $lang2) = each($langs))
	{
		require $lang2;
		if(!empty($langinfo['website']))
		{
			$author = "<a href=\"$langinfo[website]\">$langinfo[author]</a>";
		}
		else
		{
			$author = $langinfo['author'];
		}
		makelabelcode("<b>$langinfo[name]</b>", $author);
		unset($langinfo);
	}
	endtable();
	cpfooter();
}

elseif($mybb->input['action'] == "vercheck") {
	logadmin();
	$ver = rawurlencode($mybboard['internalver']);
	$larr = @file("http://www.mybboard.com/vercheck.php?tver=".$ver);
	if(!$larr)
	{
		cperror($lang->vercheck_error);
		exit;
	}
	$latestver = implode("", $larr);
	$lann = @file("http://www.mybboard.com/latestann.php");
	if($latestver > $mybboard['internalver'])
	{
		$latestver = "<font color=\"red\">$latestver</font>";
		$verwarn = 1;
	}
	cpheader();
	starttable();
	tableheader($lang->vercheck);
	tablesubheader($lang->vercheck_up2date);
	makelabelcode($lang->your_version, $mybboard['internalver']);
	makelabelcode($lang->latest_version, $latestver);
	if($verwarn)
	{
		makelabelcode("<center><b><font color=red>$lang->newer_ver</font></b>", "", 2);
	}
	endtable();
	echo "<br>";
	starttable();
	tableheader($lang->latest_ann);
	while(list($key, $val) = each($lann))
	{
		$dstore = explode("|\|", $val);
		$subject = $dstore[0];
		$item = $dstore[1];
		$url = $dstore[2];
		tablesubheader($subject);
		if(strlen(trim($url)) > 0)
		{
			$item .= "<div align=\"right\"><a href=\"$url\">$lang->latest_ann_more</a></div>";
		}
		makelabelcode("$item");
	}
	endtable();
	cpfooter();
}

elseif ($mybb->input['action']=="navigation")
{
?>
<html>
<head>
<base target="body">
<link rel="stylesheet" href="<?php echo $style; ?>">
</head>
<body>
<?php
makenavoption($lang->cp_home, "index.php?action=home");
makenavoption($lang->cp_prefs, "adminoptions.php");
makenavoption($lang->vercheck, "index.php?action=vercheck");
makenavselect("");

makenavoption($lang->nav_change, "settings.php?action=change");
makenavoption($lang->nav_add_setting, "settings.php?action=add");
makenavoption($lang->nav_plugin_manager, "plugins.php");
makenavselect($lang->nav_settings);

makenavoption($lang->nav_add_forum, "forums.php?action=add");
makenavoption($lang->nav_manage_forums, "forums.php?action=modify");
makenavoption($lang->nav_forum_announcements, "announcements.php?action=modify");
makenavoption($lang->nav_forum_permissions, "forumpermissions.php");
makenavselect($lang->nav_forums);

makenavoption($lang->nav_threads_and_posts, "moderate.php?action=threadsposts");
makenavoption($lang->nav_threads_only, "moderate.php?action=threads");
makenavoption($lang->nav_posts_only, "moderate.php?action=posts");
makenavoption($lang->nav_attachments, "moderate.php?action=attachments");
makenavselect($lang->nav_moderation_queue);

makenavoption($lang->nav_search_attachments, "attachments.php?action=search");
makenavoption($lang->nav_add_type, "attachments.php?action=add");
makenavoption($lang->nav_manage_attachment_types, "attachments.php?action=modify");
makenavselect($lang->nav_attachments);

makenavoption($lang->nav_add_user, "users.php?action=add");
makenavoption($lang->nav_search_users, "users.php?action=search");
makenavoption($lang->nav_manage_groups, "usergroups.php?action=modify");
makenavoption($lang->nav_usertitles, "usertitles.php?action=modify");
makenavoption($lang->nav_merge_users, "users.php?action=merge");
makenavoption($lang->nav_custom_fields, "profilefields.php?action=modify");
makenavoption($lang->nav_mass_email, "users.php?action=email");
makenavoption($lang->nav_banning, "users.php?action=banned");
makenavoption($lang->nav_adminperms, "adminoptions.php?action=adminpermissions");
makenavselect($lang->nav_users_groups);

makenavoption($lang->nav_smilie_manager, "smilies.php?action=modify");
makenavoption($lang->nav_manage_badwords, "badwords.php?action=modify");
makenavselect($lang->nav_message_filters);

makenavoption($lang->nav_add, "themes.php?action=add");
makenavoption($lang->nav_modify_delete, "themes.php?action=modify");
makenavoption($lang->nav_import, "themes.php?action=import");
makenavoption($lang->nav_download, "themes.php?action=download");
makenavselect($lang->nav_themes);

makenavoption($lang->nav_add, "templates.php?action=add");
makenavoption($lang->nav_modify_delete, "templates.php?action=modify");
makenavoption($lang->nav_search, "templates.php?action=search");
makenavoption($lang->nav_addset, "templates.php?action=addset");
makenavselect($lang->nav_templates);

makenavoption($lang->nav_add, "icons.php?action=add");
makenavoption($lang->nav_modify_delete, "icons.php?action=modify");
makenavselect($lang->nav_posticons);

makenavoption($lang->nav_admin_log, "adminlogs.php");
makenavoption($lang->nav_mod_log, "modlogs.php");
makenavselect($lang->nav_stats_and_logging);

makenavoption($lang->nav_add, "helpdocs.php?action=add");
makenavoption($lang->nav_modify_delete, "helpdocs.php?action=modify");
makenavselect($lang->nav_helpdocs);

makenavoption($lang->nav_db_maint, "misc.php?action=dbmaint");
makenavoption($lang->nav_cache_manager, "maintenance.php?action=cache");
makenavoption($lang->nav_recount_stats, "maintenance.php?action=rebuildstats");
makenavoption($lang->nav_view_phpinfo, "index.php?action=phpinfo");
makenavselect($lang->nav_maintenance);
?>
</body>
</html>
<?php
}

elseif($mybb->input['action'] == "phpinfo")
{
	phpinfo();
}

else
{
	if(!empty($mybb->input['goto']))
	{
		$goto = htmlspecialchars_uni($mybb->input['goto']);
	}
	else
	{
		$goto = 'index.php?action=home';
	}
	echo "<html>\n";
	echo "<head>\n";
	echo "<title>$lang->mybb_admin</title>\n";
	echo "</head>\n";
	echo "<frameset rows=\"78, *\" frameborder=\"no\" border=\"0\" framespacing=\"0\">\n";
	echo "<frame name=\"header\" noresize scrolling=\"no\" src=\"index.php?action=header\">\n";
	echo "<frameset cols=\"200, *\" frameborder=\"no\" border=\"0\" framespacing=\"0\">\n";
	echo "<frame name=\"nav\" noresize scrolling=\"auto\" src=\"index.php?action=navigation\">\n";
	echo "<frame name=\"body\" noresize scrolling=\"auto\" src=\"".$goto."\">\n";
	echo "</frameset>\n";
	echo "</frameset>\n";
	echo "</html>\n";
}
?>