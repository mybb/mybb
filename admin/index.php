<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
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

$plugins->run_hooks("admin_index_start");

if($mybb->input['action'] == "header")
{
	$plugins->run_hooks("admin_index_header");
	
	echo "<html ".($lang->settings['rtl'] ? "dir=\"rtl\"" : "")."lang=\"".($lang->settings['htmllang'])."\">\n";
	echo "<head>";
	echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=".$lang->settings['charset']."\">";
	echo "<link rel=\"stylesheet\" href=\"$style\">";
	echo "</head>";
	echo "<body id=\"logo\">";
	echo "<h1><span class=\"hidden\">MyBB Admin CP</span></h1>";
	$lang->logout_cp = sprintf($lang->logout_cp, $user['username']);
	echo "<div id=\"header-links\"><a href=\"index.php?".SID."&action=home\" target=\"body\">".$lang->cp_home."</a><a href=\"../index.php\" target=\"body\">".$lang->view_forums."</a><a href=\"index.php?".SID."&action=logout\" target=\"_parent\">".$lang->logout_cp."</a></div>";
	echo "</body>";
	echo "</html>";
}
elseif($mybb->input['action'] == "home")
{
	logadmin();
	cpheader();
	// Get statistics
	$phpversion = phpversion();
	$dbversion = $db->get_version();
	$serverload = get_server_load();
	if(!$serverload)
	{
		$serverload = $lang->unknown;
	}
	// Get the number of users
	$query = $db->simple_select(TABLE_PREFIX."users", "COUNT(*) AS numusers");
	$users = $db->fetch_array($query);

	// Get the number of users awaiting validation
	$query = $db->simple_select(TABLE_PREFIX."users", "COUNT(*) AS awaitingusers", "usergroup='5'");
	$awaitingusers = $db->fetch_array($query);

	// Get the number of new users for today
	$timecut = time() - 86400;
	$query = $db->simple_select(TABLE_PREFIX."users", "COUNT(*) AS newusers", "regdate>'$timecut'");
	$newusers = $db->fetch_array($query);

	// Get the number of active users today
	$query = $db->simple_select(TABLE_PREFIX."users", "COUNT(*) AS activeusers", "lastvisit>'$timecut'");
	$activeusers = $db->fetch_array($query);

	// Get the number of threads
	$query = $db->simple_select(TABLE_PREFIX."threads", "COUNT(*) AS numthreads");
	$threads = $db->fetch_array($query);

	// Get the number of unapproved threads
	$query = $db->simple_select(TABLE_PREFIX."threads", "COUNT(*) AS numthreads", "visible='0'");
	$unapproved_threads = $db->fetch_array($query);

	// Get the number of new threads for today
	$query = $db->simple_select(TABLE_PREFIX."threads", "COUNT(*) AS newthreads", "dateline>'$timecut'");
	$newthreads = $db->fetch_array($query);

	// Get the number of posts
	$query = $db->simple_select(TABLE_PREFIX."posts", "COUNT(*) AS numposts");
	$posts = $db->fetch_array($query);

	// Get the number of unapproved posts
	$query = $db->simple_select(TABLE_PREFIX."posts", "COUNT(*) AS numposts", "visible='0'");
	$unapproved_posts = $db->fetch_array($query);

	// Get the number of new posts for today
	$query = $db->simple_select(TABLE_PREFIX."posts", "COUNT(*) AS newposts", "dateline>'$timecut'");
	$newposts = $db->fetch_array($query);

	// Get the number and total file size of attachments
	$query = $db->simple_select(TABLE_PREFIX."attachments", "COUNT(*) AS numattachs, SUM(filesize) as spaceused");
	$attachs = $db->fetch_array($query);
	$attachs['spaceused'] = get_friendly_size($attachs['spaceused']);

	// Get the number of unapproved attachments
	$query = $db->simple_select(TABLE_PREFIX."attachments", "COUNT(*) AS numattachs", "visible='0' AND pid>0");
	$unapproved_attachs = $db->fetch_array($query);
	
	$plugins->run_hooks("admin_index_home");

	// Program Statistics table
	starttable();
	tableheader($lang->welcome, "", 4);
	tablesubheader($lang->program_stats, "", 4);
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>".$lang->mybb_version."</b></td><td valign=\"top\" class=\"altbg2\">$mybboard[internalver]</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->php_version</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"index.php?".SID."&action=phpinfo\">$phpversion</a></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->db_version</b></td><td valign=\"top\" class=\"altbg2\">{$db->title} $dbversion</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->server_load</b></td><td valign=\"top\" class=\"altbg2\">$serverload</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->total_users</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"users.php?".SID."&action=find\">$users[numusers]</a></td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->users_awaiting_activation</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"users.php?".SID."&action=find&search[additionalgroups][]=5&searchop[sortby]=regdate&searchop[order]=desc\">$awaitingusers[awaitingusers]</a></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->new_users_today</b></td><td valign=\"top\" class=\"altbg2\">$newusers[newusers]</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->active_users_today</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"../online.php?action=today\">$activeusers[activeusers]</a></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->total_threads</b></td><td valign=\"top\" class=\"altbg2\">$threads[numthreads] (<a href=\"moderate.php?".SID."&action=threads\" title=\"$lang->unapproved_threads\">$unapproved_threads[numthreads]</a>)</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->threads_today</b></td><td valign=\"top\" class=\"altbg2\">$newthreads[newthreads]</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->total_posts</b></td><td valign=\"top\" class=\"altbg2\">$posts[numposts] (<a href=\"moderate.php?".SID."&action=posts\" title=\"$lang->unapproved_posts\">$unapproved_posts[numposts]</a>)</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->posts_today</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"../search.php?action=getdaily\">$newposts[newposts]</a></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->total_attachments</b></td><td valign=\"top\" class=\"altbg2\">$attachs[numattachs] (<a href=\"moderate.php?".SID."&action=attachments\" title=\"$lang->unapproved_attachs\">$unapproved_attachs[numattachs]</a>)</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->attachment_space</b></td><td valign=\"top\" class=\"altbg2\">$attachs[spaceused]</td>\n";
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
	makelabelcode("<b>$lang->product_managers</b>", "<a href=\"http://www.surfionline.com\">Chris Boulton</a>");
	makelabelcode("<b>$lang->developers</b>", "<a href=\"http://www.surfionline.com/\">Chris Boulton</a><br /><a href=\"http://mods.mybboard.com/\">Musicalmidget</a><br /><a href=\"http://www.dennistt.net/\">DennisTT</a><br /><a href=\"http://www.linuxbox.co.nz/~kieran/\">k776</a><br /><a href=\"http://www.peterakkies.com\">Peter</a>");
	makelabelcode("<b>$lang->graphics_and_style</b>", "<a href=\"http://www.surfionline.com\">Chris Boulton</a><br /><a href=\"http://www.templatesforall.com\">Scott Hough</a>");
	endtable();

	// Installed Language Packs
	starttable();
	tableheader($lang->installed_langs);
	tablesubheader($lang->lang_authors);
	$languages = $lang->get_languages();
	asort($languages);
	foreach($languages as $key => $langname)
	{
		require MYBB_ROOT."inc/languages/".$key.".php";
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
elseif($mybb->input['action'] == "vercheck") 
{
	logadmin();

	$current_version = rawurlencode($mybboard['vercode']);

	require_once MYBB_ROOT."inc/class_xml.php";
	$contents = @implode("", @file("http://mybboard.com/version_check.php"));
	if(!$contents)
	{
		cperror($lang->vercheck_error);
		exit;
	}

	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();

	$latest_code = $tree['mybb']['version_code']['value'];
	$latest_version = $tree['mybb']['latest_version']['value']." (".$latest_code.")";
	if($latest_code > $mybboard['vercode'])
	{
		$latest_version = "<span style=\"color: red\">".$latest_version."</font>";
		$version_warn = 1;
	}

	$plugins->run_hooks("admin_index_vercheck");
	
	$lann = @file("http://www.mybboard.com/latestann.php");
	cpheader();
	starttable();
	tableheader($lang->vercheck);
	tablesubheader($lang->vercheck_up2date);
	makelabelcode($lang->your_version, $mybboard['internalver']." (".$mybboard['vercode'].")");
	makelabelcode($lang->latest_version, $latest_version);
	if($version_warn)
	{
		makelabelcode("<div align=\"center\"><b><font color=red>$lang->newer_ver</font></b></div>", "", 2);
	}
	endtable();
	echo "<br />";
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
elseif ($mybb->input['action'] == "navigation")
{
	$plugins->run_hooks("admin_index_navigation");
?>
<html>
<head>
<base target="body">
<link rel="stylesheet" href="<?php echo $style; ?>">
</head>
<body class="lnav">
<?php

// STILL NEED TO FIX PLUGIN HOOKS FOR MENU ITEMS

makenavoption($lang->cp_home, "index.php?".SID."&action=home");
makenavoption($lang->cp_prefs, "adminoptions.php?".SID);
makenavoption($lang->vercheck, "index.php?".SID."&action=vercheck");
makenavselect("");

makenavoption($lang->nav_change, "settings.php?".SID."&action=change");
makenavoption($lang->nav_add_setting, "settings.php?".SID."&action=add");
makenavoption($lang->nav_plugin_manager, "plugins.php?".SID);
makenavselect($lang->nav_settings);

makenavoption($lang->nav_add_forum, "forums.php?".SID."&action=add");
makenavoption($lang->nav_manage_forums, "forums.php?".SID."&action=modify");
makenavoption($lang->nav_forum_announcements, "announcements.php?".SID."&action=modify");
makenavoption($lang->nav_forum_permissions, "forumpermissions.php?".SID);
makenavselect($lang->nav_forums);

makenavoption($lang->nav_threads_and_posts, "moderate.php?".SID."&action=threadsposts");
makenavoption($lang->nav_threads_only, "moderate.php?".SID."&action=threads");
makenavoption($lang->nav_posts_only, "moderate.php?".SID."&action=posts");
makenavoption($lang->nav_attachments, "moderate.php?".SID."&action=attachments");
makenavselect($lang->nav_moderation_queue);

makenavoption($lang->nav_search_attachments, "attachments.php?".SID."&action=search");
makenavoption($lang->nav_search_orphan_attachments, "attachments.php?".SID."&action=orphans");
makenavoption($lang->nav_attach_stats, "attachments.php?".SID."&action=stats");
makenavoption($lang->nav_add_type, "attachments.php?".SID."&action=add");
makenavoption($lang->nav_manage_attachment_types, "attachments.php?".SID."&action=modify");
makenavselect($lang->nav_attachments);

makenavoption($lang->nav_add_user, "users.php?".SID."&action=add");
makenavoption($lang->nav_search_users, "users.php?".SID."&action=search");
makenavoption($lang->nav_manage_groups, "usergroups.php?".SID."&action=modify");
makenavoption($lang->nav_usertitles, "usertitles.php?".SID."&action=modify");
makenavoption($lang->nav_merge_users, "users.php?".SID."&action=merge");
makenavoption($lang->nav_custom_fields, "profilefields.php?".SID."&action=modify");
makenavoption($lang->nav_mass_email, "users.php?".SID."&action=email");
makenavoption($lang->nav_banning, "users.php?".SID."&action=banned");
makenavoption($lang->nav_adminperms, "adminoptions.php?".SID."&action=adminpermissions");
makenavselect($lang->nav_users_groups);

makenavoption($lang->nav_smilie_manager, "smilies.php?".SID."&action=modify");
makenavoption($lang->nav_manage_badwords, "badwords.php?".SID."&action=modify");
makenavoption($lang->nav_custom_mycode, "mycode.php?".SID."&action=modify");
makenavselect($lang->nav_message_filters);

makenavoption($lang->nav_add, "themes.php?".SID."&action=add");
makenavoption($lang->nav_modify_delete, "themes.php?".SID."&action=modify");
makenavoption($lang->nav_import, "themes.php?".SID."&action=import");
makenavoption($lang->nav_download, "themes.php?".SID."&action=download");
makenavselect($lang->nav_themes);

makenavoption($lang->nav_add, "templates.php?".SID."&action=add");
makenavoption($lang->nav_modify_delete, "templates.php?".SID."&action=modify");
makenavoption($lang->nav_search, "templates.php?".SID."&action=search");
makenavoption($lang->nav_addset, "templates.php?".SID."&action=addset");
makenavoption($lang->nav_find_updated, "templates.php?".SID."&action=findupdated");
makenavselect($lang->nav_templates);

makenavoption($lang->nav_manage, "languages.php?".SID);
makenavselect($lang->nav_language_packs);

makenavoption($lang->nav_add_post_mod_tool, "moderation.php?".SID."&action=addposttool");
makenavoption($lang->nav_add_thread_mod_tool, "moderation.php?".SID."&action=addthreadtool");
makenavoption($lang->nav_modify_delete, "moderation.php?".SID);
makenavselect($lang->nav_mod_toolbox);

makenavoption($lang->nav_add, "icons.php?".SID."&action=add");
makenavoption($lang->nav_modify_delete, "icons.php?".SID."&action=modify");
makenavselect($lang->nav_posticons);

makenavoption($lang->nav_admin_log, "adminlogs.php?".SID);
makenavoption($lang->nav_mod_log, "modlogs.php?".SID);
makenavselect($lang->nav_stats_and_logging);

makenavoption($lang->nav_add, "helpdocs.php?".SID."&action=add");
makenavoption($lang->nav_modify_delete, "helpdocs.php?".SID."&action=modify");
makenavselect($lang->nav_helpdocs);

makenavoption($lang->nav_db_maint, "misc.php?".SID."&action=dbmaint");
makenavoption($lang->nav_cache_manager, "maintenance.php?".SID."&action=cache");
makenavoption($lang->nav_recount_stats, "maintenance.php?".SID."&action=rebuildstats");
makenavoption($lang->nav_view_phpinfo, "index.php?".SID."&action=phpinfo");
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
	$plugins->run_hooks("admin_index_frameset");
	if(!empty($mybb->input['goto']))
	{
		// Strip session ID from goto
		$goto = preg_replace("#adminsid=[a-zA-Z0-9]{32}#i", "", $mybb->input['goto']);
		$parsed_url = parse_url($goto);
		if(!$parsed_url['query'])
		{
			$goto .= "?".SID;
		}
		else
		{
			$goto .= "&".SID;
		}
		$goto = htmlspecialchars_uni($goto);
	}
	else
	{
		$goto = 'index.php?'.SID.'&action=home';
	}
	echo "<html>\n";
	echo "<head>\n";
	echo "<title>$lang->mybb_admin</title>\n";
	echo "</head>\n";
	echo "<frameset rows=\"78, *\" frameborder=\"no\" border=\"0\" framespacing=\"0\">\n";
	echo "<frame name=\"header\" noresize scrolling=\"no\" src=\"index.php?".SID."&action=header\">\n";
	if($lang->settings['rtl'])
	{
		echo "<frameset cols=\"*,200\" frameborder=\"no\" border=\"0\" framespacing=\"0\">\n";
		echo "<frame name=\"body\" noresize scrolling=\"auto\" src=\"".$goto."\">\n";
		echo "<frame name=\"nav\" noresize scrolling=\"auto\" src=\"index.php?".SID."&action=navigation\">\n";
	}
	else
	{
		echo "<frameset cols=\"200, *\" frameborder=\"no\" border=\"0\" framespacing=\"0\">\n";
		echo "<frame name=\"nav\" noresize scrolling=\"auto\" src=\"index.php?".SID."&action=navigation\">\n";
		echo "<frame name=\"body\" noresize scrolling=\"auto\" src=\"".$goto."\">\n";
	}
	echo "</frameset>\n";
	echo "</frameset>\n";
	echo "</html>\n";
}
?>