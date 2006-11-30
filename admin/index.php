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

define("IN_MYBB", 1);

require_once "./global.php";;

// Load language packs for this section
global $lang;
$lang->load("index");

$plugins->run_hooks("admin_index_start");

if($mybb->input['action'] == "header")
{
	$plugins->run_hooks("admin_index_header");

	echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
	echo "<html ".($lang->settings['rtl'] ? "dir=\"rtl\" " : "")."lang=\"".($lang->settings['htmllang'])."\">\n";
	echo "<head>\n";
	echo "<title>$lang->mybb_admin</title>\n";
	echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=".$lang->settings['charset']."\" />\n";
	echo "<link type=\"text/css\" rel=\"stylesheet\" href=\"$style\" />\n";
	echo "</head>\n";
	echo "<body id=\"logo\">\n";
	echo "<h1><span class=\"hidden\">MyBB Admin CP</span></h1>\n";
	$lang->logout_cp = sprintf($lang->logout_cp, $user['username']);
	echo "<div id=\"header-links\">\n<a href=\"index.php?".SID."&amp;action=home\" target=\"body\">".$lang->cp_home."</a>\n<a href=\"../index.php\" target=\"body\">".$lang->view_forums."</a>\n<a href=\"index.php?".SID."&amp;action=logout\" target=\"_parent\">".$lang->logout_cp."</a>\n</div>\n";
	echo "</body>\n";
	echo "</html>\n";
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
	$query = $db->simple_select("users", "COUNT(*) AS numusers");
	$users = $db->fetch_array($query);

	// Get the number of users awaiting validation
	$query = $db->simple_select("users", "COUNT(*) AS awaitingusers", "usergroup='5'");
	$awaitingusers = $db->fetch_array($query);

	// Get the number of new users for today
	$timecut = time() - 86400;
	$query = $db->simple_select("users", "COUNT(*) AS newusers", "regdate>'$timecut'");
	$newusers = $db->fetch_array($query);

	// Get the number of active users today
	$query = $db->simple_select("users", "COUNT(*) AS activeusers", "lastvisit>'$timecut'");
	$activeusers = $db->fetch_array($query);

	// Get the number of threads
	$query = $db->simple_select("threads", "COUNT(*) AS numthreads", "visible='1' AND closed NOT LIKE 'moved|%'");
	$threads = $db->fetch_array($query);

	// Get the number of unapproved threads
	$query = $db->simple_select("threads", "COUNT(*) AS numthreads", "visible='0' AND closed NOT LIKE 'moved|%'");
	$unapproved_threads = $db->fetch_array($query);

	// Get the number of new threads for today
	$query = $db->simple_select("threads", "COUNT(*) AS newthreads", "dateline>'$timecut' AND visible='1' AND closed NOT LIKE 'moved|%'");
	$newthreads = $db->fetch_array($query);

	// Get the number of posts
	$query = $db->simple_select("posts", "COUNT(*) AS numposts", "visible='1'");
	$posts = $db->fetch_array($query);

	// Get the number of unapproved posts
	$query = $db->simple_select("posts", "COUNT(*) AS numposts", "visible='0'");
	$unapproved_posts = $db->fetch_array($query);

	// Get the number of new posts for today
	$query = $db->simple_select("posts", "COUNT(*) AS newposts", "dateline>'$timecut' AND visible='1'");
	$newposts = $db->fetch_array($query);

	// Get the number and total file size of attachments
	$query = $db->simple_select("attachments", "COUNT(*) AS numattachs, SUM(filesize) as spaceused", "visible='1' AND pid>0");
	$attachs = $db->fetch_array($query);
	$attachs['spaceused'] = get_friendly_size($attachs['spaceused']);

	// Get the number of unapproved attachments
	$query = $db->simple_select("attachments", "COUNT(*) AS numattachs", "visible='0' AND pid>0");
	$unapproved_attachs = $db->fetch_array($query);

	// Fetch the last time an update check was run
	$update_check = $cache->read("update_check");

	// If last update check was greater than two weeks ago (14 days) show an alert
	if($update_check['last_check'] <= time()-60*60*24*14)
	{
		$lang->last_update_check_two_weeks = sprintf($lang->last_update_check_two_weeks, "index.php?".SID."&amp;action=vercheck");
		makewarning($lang->last_update_check_two_weeks);
	}

	// If the update check contains information about a newer version, show an alert
	if($update_check['latest_version_code'] > $mybb->version_code)
	{
		$lang->new_version_available = sprintf($lang->new_version_available, "MyBB {$mybb->version}", "<a href=\"http://www.mybboard.com/?fwlink=release_{$update_check['latest_version_code']}\" target=\"_new\">MyBB {$update_check['latest_version']}</a>");
		makewarning($lang->new_version_available);
	}

	$plugins->run_hooks("admin_index_home");

	// Program Statistics table
	starttable();
	tableheader($lang->welcome, "", 4);
	tablesubheader($lang->program_stats, "", 4);
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>".$lang->mybb_version."</b></td><td valign=\"top\" class=\"altbg2\">{$mybb->version}</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->php_version</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"index.php?".SID."&amp;action=phpinfo\">$phpversion</a></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->db_version</b></td><td valign=\"top\" class=\"altbg2\">{$db->title} $dbversion</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->server_load</b></td><td valign=\"top\" class=\"altbg2\">$serverload</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->total_users</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"users.php?".SID."&amp;action=find\">$users[numusers]</a></td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->users_awaiting_activation</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"users.php?".SID."&amp;action=find&amp;search[usergroups][]=5&amp;searchop[sortby]=regdate&amp;searchop[order]=desc\">$awaitingusers[awaitingusers]</a></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->new_users_today</b></td><td valign=\"top\" class=\"altbg2\">$newusers[newusers]</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->active_users_today</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"../online.php?action=today\">$activeusers[activeusers]</a></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->total_threads</b></td><td valign=\"top\" class=\"altbg2\">$threads[numthreads] (<a href=\"moderate.php?".SID."&amp;action=threads\" title=\"$lang->unapproved_threads\">$unapproved_threads[numthreads]</a>)</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->threads_today</b></td><td valign=\"top\" class=\"altbg2\">$newthreads[newthreads]</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->total_posts</b></td><td valign=\"top\" class=\"altbg2\">$posts[numposts] (<a href=\"moderate.php?".SID."&amp;action=posts\" title=\"$lang->unapproved_posts\">$unapproved_posts[numposts]</a>)</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->posts_today</b></td><td valign=\"top\" class=\"altbg2\"><a href=\"../search.php?action=getdaily\">$newposts[newposts]</a></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->total_attachments</b></td><td valign=\"top\" class=\"altbg2\">$attachs[numattachs] (<a href=\"moderate.php?".SID."&amp;action=attachments\" title=\"$lang->unapproved_attachs\">$unapproved_attachs[numattachs]</a>)</td>\n";
	echo "<td valign=\"top\" class=\"altbg1\"><b>$lang->attachment_space</b></td><td valign=\"top\" class=\"altbg2\">$attachs[spaceused]</td>\n";
	echo "</tr>\n";
	endtable();


	ob_start();
	endform();
	$endform = ob_get_contents();
	ob_end_clean();
	ob_start();
	startform("users.php", "", "find");
	$startform1 = ob_get_contents();
	ob_end_clean();
	ob_start();
	startform("forums.php", "", "add");
	$startform2 = ob_get_contents();
	ob_end_clean();


	// Quick admin options
	starttable();
	tableheader($lang->quick_options);
	tablesubheader($lang->quick_user_finder);
	makeinputcode($lang->find_user_account, "search[username]", "", "", "&nbsp;".makebuttoncode("find", $lang->find_button)."&nbsp;".makebuttoncode("listall", $lang->list_all_users).$endform, "", 1, $startform1);
	tablesubheader($lang->quick_forum_add);
	makeinputcode($lang->new_forum_name, "fname", "", "", "&nbsp;".makebuttoncode("add", $lang->add_button).$endform, "", 1, $startform2);
	endtable();

	// MyBB Credits
	starttable();
	tableheader($lang->mybb_credits);
	tablesubheader($lang->contributed);
	makelabelcode("<b>$lang->product_managers</b>", "<a href=\"http://www.surfionline.com\" target=\"_blank\">Chris Boulton</a>");
	makelabelcode("<b>$lang->developers</b>", "<a href=\"http://www.surfionline.com/\" target=\"_blank\">Chris Boulton</a><br /><a href=\"http://mods.mybboard.com/\" target=\"_blank\">Musicalmidget</a><br /><a href=\"http://www.dennistt.net/\" target=\"_blank\">DennisTT</a><br /><a href=\"http://www.peterakkies.com\" target=\"_blank\">Peter</a><br /><a href=\"http://www.tiki.rct3x.net\" target=\"_blank\">Tikitiki</a><br /><a href=\"http://www.decswxaqz.co.uk/\" target=\"_blank\">decswxaqz</a><br /><a href=\"http://www.mcdcpp.net/blog/\" target=\"_blank\">CraKteR</a>");
	makelabelcode("<b>$lang->graphics_and_style</b>", "<a href=\"http://www.surfionline.com\" target=\"_blank\">Chris Boulton</a><br /><a href=\"http://www.templatesforall.com\" target=\"_blank\">Scott Hough</a>");
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

	$current_version = rawurlencode($mybb->version_code);

	$updated_cache = array(
		"last_check" => time()
	);

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
	if($latest_code > $mybb->version_code)
	{
		$latest_version = "<span style=\"color: red\">".$latest_version."</font>";
		$version_warn = 1;
		$updated_cache = array(
			"latest_version" => $latest_version,
			"latest_version_code" => $latest_code
		);
	}

	$cache->update("update_check", $updated_cache);

	$plugins->run_hooks("admin_index_vercheck");

	require_once MYBB_ROOT."inc/class_feedparser.php";
	$feed_parser = new FeedParser();
	$feed_parser->parse_feed("http://www.mybboard.com/latest_news.php");
	echo $feed_parser->error;
	cpheader();
	starttable();
	tableheader($lang->vercheck);
	tablesubheader($lang->vercheck_up2date);
	makelabelcode($lang->your_version, $mybb->version." (".$mybb->version_code.")");
	makelabelcode($lang->latest_version, $latest_version);
	if($version_warn)
	{
		makelabelcode("<div align=\"center\"><b><span style=\"color: red;\">$lang->newer_ver</span></b></div>", "", 2);
	}
	endtable();
	echo "<br />";
	starttable();
	tableheader($lang->latest_ann);

	if($feed_parser->error == '')
	{
		foreach($feed_parser->items as $item)
		{
			if($item['date_timestamp'])
			{
				$stamp = " (".my_date($mybb->settings['dateformat'], $item['date_timestamp']).", ".my_date($mybb->settings['timeformat'], $item['date_timestamp']).")";
			}
			else
			{
				$stamp = '';
			}
			if($item['content'])
			{
				$content = $item['content'];
			}
			else
			{
				$content = $item['description'];
			}
			tablesubheader("<a href=\"{$item['link']}\" target=\"_blank\">{$item['title']}</a>{$stamp}");
			$content .= "<div style=\"text-align: right;\"><a href=\"{$item['link']}\" target=\"_blank\">{$lang->latest_ann_more}</a></div>";
			makelabelcode($content);
		}
	}
	else
	{
		makelabelcode($lang->unable_fetch_announcements." <!-- error code: {$feed_parser->error} -->");
	}
	endtable();
	cpfooter();
}
elseif ($mybb->input['action'] == "navigation")
{
	$menu = array();

	$plugins->run_hooks("admin_index_navigation");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<?php
echo "<html ".($lang->settings['rtl'] ? "dir=\"rtl\" " : "")."lang=\"".($lang->settings['htmllang'])."\">\n";
echo "<head>\n";
echo "<title>$lang->mybb_admin</title>\n";
?>
<base target="body" />
<link type="text/css" rel="stylesheet" href="<?php echo $style; ?>" />
</head>
<body class="lnav">
<?php

$menu[10] = array(
	"title" => "",
	"items" => array(
		10 => array("title" => $lang->cp_home, "url" => "index.php?".SID."&amp;action=home"),
		20 => array("title" => $lang->cp_prefs, "url" => "adminoptions.php?".SID),
		30 => array("title" => $lang->vercheck, "url" => "index.php?".SID."&amp;action=vercheck"),
	)
);

$menu[20] = array(
	"title" => $lang->nav_settings,
	"items" => array(
		10 => array("title" => $lang->nav_change, "url" => "settings.php?".SID."&amp;action=change"),
		20 => array("title" => $lang->nav_add_setting, "url" => "settings.php?".SID."&amp;action=add"),
		30 => array("title" => $lang->nav_plugin_manager, "url" => "plugins.php?".SID)
	)
);

$menu[30] = array(
	"title" => $lang->nav_forums,
	"items" => array(
		10 => array("title" => $lang->nav_add_forum, "url" => "forums.php?".SID."&amp;action=add"),
		20 => array("title" => $lang->nav_manage_forums, "url" => "forums.php?".SID."&amp;action=modify"),
		30 => array("title" => $lang->nav_forum_announcements, "url" => "announcements.php?".SID."&amp;action=modify"),
		40 => array("title" => $lang->nav_forum_permissions, "url" => "forumpermissions.php?".SID)
	)
);

$menu[40] = array(
	"title" => $lang->nav_moderation_queue,
	"items" => array(
		10 => array("title" => $lang->nav_threads_and_posts, "url" => "moderate.php?".SID."&amp;action=threadsposts"),
		20 => array("title" => $lang->nav_threads_only, "url" => "moderate.php?".SID."&amp;action=threads"),
		30 => array("title" => $lang->nav_posts_only, "url" => "moderate.php?".SID."&amp;action=posts"),
		40 => array("title" => $lang->nav_attachments, "url" => "moderate.php?".SID."&amp;action=attachments")
	)
);

$menu[50] = array(
	"title" => $lang->nav_attachments,
	"items" => array(
		10 => array("title" => $lang->nav_search_attachments, "url" => "attachments.php?".SID."&amp;action=search"),
		20 => array("title" => $lang->nav_search_orphan_attachments, "url" => "attachments.php?".SID."&amp;action=orphans"),
		30 => array("title" => $lang->nav_attach_stats, "url" => "attachments.php?".SID."&amp;action=stats"),
		40 => array("title" => $lang->nav_add_type, "url" => "attachments.php?".SID."&amp;action=add"),
		50 => array("title" => $lang->nav_manage_attachment_types, "url" => "attachments.php?".SID."&amp;action=modify"),
	)
);

$menu[60] = array(
	"title" => $lang->nav_users_groups,
	"items" => array(
		10 => array("title" => $lang->nav_add_user, "url" => "users.php?".SID."&amp;action=add"),
		20 => array("title" => $lang->nav_search_users, "url" => "users.php?".SID."&amp;action=search"),
		30 => array("title" => $lang->nav_manage_groups, "url" => "usergroups.php?".SID."&amp;action=modify"),
		40 => array("title" => $lang->nav_usertitles, "url" => "usertitles.php?".SID."&amp;action=modify"),
		50 => array("title" => $lang->nav_merge_users, "url" => "users.php?".SID."&amp;action=merge"),
		60 => array("title" => $lang->nav_custom_fields, "url" => "profilefields.php?".SID."&amp;action=modify"),
		70 => array("title" => $lang->nav_mass_email, "url" => "users.php?".SID."&amp;action=email"),
		80 => array("title" => $lang->nav_banning, "url" => "users.php?".SID."&amp;action=banned"),
		90 => array("title" => $lang->nav_adminperms, "url" => "adminoptions.php?".SID."&amp;action=adminpermissions")
	)
);

$menu[70] = array(
	"title" => $lang->nav_message_filters,
	"items" => array(
		10 => array("title" => $lang->nav_smilie_manager, "url" => "smilies.php?".SID."&amp;action=modify"),
		20 => array("title" => $lang->nav_manage_badwords, "url" => "badwords.php?".SID."&amp;action=modify"),
		30 => array("title" => $lang->nav_custom_mycode, "url" => "mycode.php?".SID."&amp;action=modify")
	)
);

$menu[80] = array(
	"title" => $lang->nav_themes,
	"items" => array(
		10 => array("title" => $lang->nav_add, "url" => "themes.php?".SID."&amp;action=add"),
		20 => array("title" => $lang->nav_modify_delete, "url" => "themes.php?".SID."&amp;action=modify"),
		30 => array("title" => $lang->nav_import, "url" => "themes.php?".SID."&amp;action=import"),
		40 => array("title" => $lang->nav_download, "url" => "themes.php?".SID."&amp;action=download")
	)
);

$menu[90] = array(
	"title" => $lang->nav_templates,
	"items" => array(
		10 => array("title" => $lang->nav_add, "url" => "templates.php?".SID."&amp;action=add"),
		20 => array("title" => $lang->nav_modify_delete, "url" => "templates.php?".SID."&amp;action=modify"),
		30 => array("title" => $lang->nav_search, "url" => "templates.php?".SID."&amp;action=search"),
		40 => array("title" => $lang->nav_addset, "url" => "templates.php?".SID."&amp;action=addset"),
		50 => array("title" => $lang->nav_find_updated, "url" => "templates.php?".SID."&amp;action=findupdated")
	)
);

$menu[100] = array(
	"title" => $lang->nav_language_packs,
	"items" => array(
		10 => array("title" => $lang->nav_manage, "url" => "languages.php?".SID)
	)
);

$menu[110] = array(
	"title" => $lang->nav_mod_toolbox,
	"items" => array(
		10 => array("title" => $lang->nav_add_post_mod_tool, "url" => "moderation.php?".SID."&amp;action=addposttool"),
		20 => array("title" => $lang->nav_add_thread_mod_tool, "url" => "moderation.php?".SID."&amp;action=addthreadtool"),
		30 => array("title" => $lang->nav_modify_delete, "url" => "moderation.php?".SID),
	)
);

$menu[120] = array(
	"title" => $lang->nav_posticons,
	"items" => array(
		10 => array("title" => $lang->nav_add, "url" => "icons.php?".SID."&amp;action=add"),
		20 => array("title" => $lang->nav_modify_delete, "url" => "icons.php?".SID."&amp;action=modify")
	)
);

$menu[130] = array(
	"title" => $lang->nav_stats_and_logging,
	"items" => array(
		10 => array("title" => $lang->nav_admin_log, "url" => "adminlogs.php?".SID),
		20 => array("title" => $lang->nav_mod_log, "url" => "modlogs.php?".SID)
	)
);

$menu[140] = array(
	"title" => $lang->nav_helpdocs,
	"items" => array(
		10 => array("title" => $lang->nav_add, "url" => "helpdocs.php?".SID."&amp;action=add"),
		20 => array("title" => $lang->nav_modify_delete, "url" => "helpdocs.php?".SID."&amp;action=modify")
	)
);

$menu[150] = array(
	"title" => $lang->nav_maintenance,
	"items" => array(
		10 => array("title" => $lang->nav_cache_manager, "url" => "maintenance.php?".SID."&amp;action=cache"),
		20 => array("title" => $lang->nav_recount_rebuild, "url" => "maintenance.php?".SID."&amp;action=rebuild"),
		30 => array("title" => $lang->nav_view_phpinfo, "url" => "index.php?".SID."&amp;action=phpinfo")
	)
);

$menu[160] = array(
	"title" => $lang->nav_db_tools,
	"items" => array(
		10 => array("title" => $lang->nav_db_backup, "url" => "dbtools.php?".SID."&amp;action=backup"),
		20 => array("title" => $lang->nav_db_existing_backups, "url" => "dbtools.php?".SID."&amp;action=existing"),
		30 => array("title" => $lang->nav_db_optimize, "url" => "dbtools.php?".SID."&amp;action=optimize"),

	)
);

$plugins->run_hooks("admin_index_navigation_end");

foreach($menu as $menu_section)
{
	foreach($menu_section['items'] as $item)
	{
		makenavoption($item['title'], $item['url']);
	}
	makenavselect($menu_section['title']);
}

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
		$goto = htmlspecialchars_uni($goto);
		if(!$parsed_url['query'])
		{
			$goto .= "?".SID;
		}
		else
		{
			$goto .= "&amp;".SID;
		}
	}
	else
	{
		$goto = 'index.php?'.SID.'&amp;action=home';
	}
	echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
	echo "<html ".($lang->settings['rtl'] ? "dir=\"rtl\" " : "")."lang=\"".($lang->settings['htmllang'])."\">\n";
	echo "<head>\n";
	echo "<title>$lang->mybb_admin</title>\n";
	echo "</head>\n";
	echo "<frameset rows=\"78, *\" frameborder=\"no\" border=\"0\" framespacing=\"0\">\n";
	echo "<frame name=\"header\" noresize scrolling=\"no\" src=\"index.php?".SID."&amp;action=header\" />\n";
	if($lang->settings['rtl'])
	{
		echo "<frameset cols=\"*,200\" frameborder=\"no\" border=\"0\" framespacing=\"0\">\n";
		echo "<frame name=\"body\" noresize scrolling=\"auto\" src=\"".$goto."\" />\n";
		echo "<frame name=\"nav\" noresize scrolling=\"auto\" src=\"index.php?".SID."&amp;action=navigation\" />\n";
	}
	else
	{
		echo "<frameset cols=\"200, *\" frameborder=\"no\" border=\"0\" framespacing=\"0\">\n";
		echo "<frame name=\"nav\" noresize scrolling=\"auto\" src=\"index.php?".SID."&amp;action=navigation\" />\n";
		echo "<frame name=\"body\" noresize scrolling=\"auto\" src=\"".$goto."\" />\n";
	}
	echo "</frameset>\n";
	echo "</frameset>\n";
	echo "</html>\n";
}
?>
