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
require "./inc/functions_post.php";
//require "./inc/modules/blog/functions.php";

if($mybboard['vercode'] < "100.04")
{
	die();
}
if($modulecache['weblogs']['active'] != 1)
{
	die("Module not activated.");
}

$lang->load("weblogs");

$action = $_POST['action'] ? $_POST['action'] : $_GET['action'];

if($action == "do_newpost" && $preview)
{
	$action = "newpost";
}
switch($action)
{
	case "manage":
		addnav($lang->nav_usercp, "usercp.php");
		addnav($lang->nav_blog_settings);
		break;
	case "editcategories":
		addnav($lang->nav_usercp, "usercp.php");
		addnav($lang->nav_blog_editcategories);
		break;
	default:
}

if($action == "syndicate")
{
}

elseif($action == "do_manage")
{
	$name = addslashes($_POST['name']);
	if(!$name)
	{
		error($lang->no_weblog_name);
	}
	$description = addslashes($_POST['description']);
	if($_POST['private'] != 1)
	{
		$private = 0;
	}
	else
	{
		$private = 1;
	}

	if($_POST['type'] != 1)
	{
		$type = 0;
	}
	else
	{
		$type = 1;
	}
	$remoteurl = $_POST['remoteurl'];
	if($type == 1 && !$remoteurl)
	{
		error($lang->no_remote_url);
	}
	$epp = intval($_POST['epp']);
	if($epp < 1)
	{
		$epp = $settings['weblogs_default_epp'];
	}
	$cpp = intval($_POST['cpp']);
	if($cpp < 1)
	{
		$cpp = $settings['weblogs_default_cpp'];
	}
	if($_POST['canview'] != 1)
	{
		$canview = 0;
	}
	else
	{
		$canview = 1;
	}

	if($_POST['cansyndicate'] != 0)
	{
		$cansyndicate = 1;
	}
	else
	{
		$cansyndicate = 0;
	}

	if($_POST['leavecomments'] != 0 && $_POST['leavecomments'] != 2)
	{
		$leavecomments = 1;
	}
	else
	{
		$leavecomments = $_POST['leavecomments'];
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."weblogs WHERE uid='$mybb[uid]'");
	$blog = $db->fetch_array($query);
	if(!$blog['bid']) // Existing blog, update
	{
		$db->query("INSERT INTO ".TABLE_PREFIX."weblogs (bid,uid) VALUES (NULL,'".$mybb['uid']."')");
		$bid = $db->insert_id();
	}
	else
	{
		$bid = $blog['bid'];
	}
	// Now we update
	$db->query("UPDATE ".TABLE_PREFIX."weblogs SET name='$name', description='$description', private='$private', type='$type', remoteurl='$remoteurl', canview='$canview', leavecomments='$leavecomments', cansyndicate='$cansyndicate', epp='$epp', cpp='$cpp' WHERE bid='$bid' AND uid='".$mybb['uid']."'");
	redirect("weblogs.php?action=manage", $lang->weblog_settings_updated);
}
elseif($action == "manage")
{
	makeucpnav();
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."weblogs WHERE uid='$mybb[uid]'");
	$blog = $db->fetch_array($query);

	if($blog['bid']) // This user already has a log
	{
		$description = htmlspecialchars($blog['description']);
		$type = $blog['type'];
		$private = $blog['private'];
		$epp = $blog['epp'];
		$cpp = $blog['cpp'];
		$canview = $blog['canview'];
		$cansyndicate = $blog['cansyndicate'];
		$leavecommnets = $blog['leavecomments'];
	}
	else // Get default settings
	{
		$type = 0;
		$description = htmlspecialchars($settings['weblogs_default_description']);
		$private = $settings['weblogs_default_private'];
		$epp = $settings['weblogs_default_epp'];
		$cpp = $settings['weblogs_default_cpp'];
		$canview = $settings['weblogs_default_canview'];
		$candyndicate = $settings['weblogs_default_candyndicate'];
		$leavecomments = $settings['weblogs_default_leavecomments'];
	}

	if($type == 1) // Remote blog
	{
		$type_remote = "selected=\"selected\"";
	}

	if($private == 1)
	{
		$private_private = "selected=\"selected\"";
	}

	$explodedepp = explode(",", $settings['weblogs_epp']);
	foreach($explodedepp as $eppitem)
	{
		if($eppitem == $epp)
		{
			$eppselect .= "<option value=\"$eppitem\" selected=\"selected\">$eppitem</option>\n";
		}
		else
		{
			$eppselect .= "<option value=\"$eppitem\">$eppitem</option>\n";
		}
	}

	$explodedcpp = explode(",", $settings['weblogs_cpp']);
	foreach($explodedcpp as $cppitem)
	{
		if($cppitem = $cpp)
		{
			$cppselect .= "<option value=\"$cppitem\" selected=\"selected\">$cppitem</option>\n";
		}
		else
		{
			$cppselect .= "<option value=\"$cppitem\">$cppitem</option>\n";
		}
	}

	if($canview == 1) // Members can view only
	{
		$canview_users = "selected=\"selected\"";
	}

	if($cansyndicate == 1)
	{
		$cansyndicate_yes = "selected=\"selected\"";
	}

	if($leavecomments == 2) // Members only can comment
	{
		$cancomments_users = "selected=\"selected\"";
	}
	elseif($leavecomments == 0)
	{
		$cancomments_noone = "selected=\"selected\"";
	}


	eval("\$manage = \"".$templates->get("weblogs_manage")."\";");
	outputpage($manage);
}
elseif($action == "do_editcategories")
{
	ksort($category, SORT_STRING);
	$categories = "";
	@reset($category);
	foreach($category as $key => $val)
	{
		if(!$donecats[$va])
		{
			if(substr($key, 0, 3) == "new")
			{
				// new category
				$highestid++;
				$cid = $highestid;
			}
			else
			{
				// editing existing category
				if($key > $highestid)
				{
					$highestid = $key;
				}
				$cid = $key;
				if($val == "")
				{
					$db->query("UPDATE ".TABLE_PREFIX."weblogs_entries SET cid='1' WHERE uid='$mybb[uid]' AND cid='$cid'");
				}
			}
			if($val != "")
			{	$catlist[] = "$cid|$val";
			}
		}
	}
	$catlist = htmlspecialchars(implode("\n", $catlist));
	$db->query("UPDATE ".TABLE_PREFIX."weblogs SET categories='$catlist' WHERE uid='$mybb[uid]'");
	redirect("weblogs.php?action=editcategories", $lang->redirect_categories_updated);
}
elseif($action == "editcategories")
{
	makeucpnav();

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."weblogs WHERE uid='$mybb[uid]'");
	$blog = $db->fetch_array($query);
	if(!$blog['bid'])
	{
		header("Location: weblogs.php?action=manage");
		exit;
	}
	$categories = explode("\n", $blog['categories']);
	if(is_array($categories))
	{
		foreach($categories as $key => $category)
		{
			$catinfo = explode("|", $category, 2);
			$key = $catinfo[0];
			$category = $catinfo[1];
			eval("\$categorylist .= \"".$templates->get("weblogs_editcategories_category")."\";");
		}
	}
	for($i=1;$i<=5;$i++)
	{
		$key = "new$i";
		$category = "";
		eval("\$newcategories .= \"".$templates->get("weblogs_editcategories_category")."\";");
	}
	eval("\$categories = \"".$templates->get("weblogs_editcategories")."\";");
	outputpage($categories);
}
elseif($action == "do_newpost")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."weblogs WHERE uid='$mybb[uid]'");
	$blog = $db->fetch_array($query);
	if(!$blog['bid'])
	{
		header("Location: weblogs.php?action=manage");
		exit;
	}
	

}
elseif($action == "newpost")
{
	makeucpnav();
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."weblogs WHERE uid='$mybb[uid]'");
	$blog = $db->fetch_array($query);
	if(!$blog['bid'])
	{
		header("Location: weblogs.php?action=manage");
		exit;
	}
	$categories = explode("\n", $blog['categories']);
	if(is_array($categories))
	{
		foreach($categories as $cat)
		{
			$catinfo = explode("|", $cat, 2);
			if($catinfo[0] = $_POST['category'])
			{
				$catselect .= "<option value=\"$catinfo[0]\" selected=\"selected\">$catinfo[1]</option>\n";
			}
			else
			{
				$catselect .= "<option value=\"$catinfo[0]\">$catinfo[1]</option>\n";
			}
		}
	}
	if($preview) // Prepare the preview if we have one
	{
		$title = htmlspecialchars($_POST['title']);
		$message = htmlspecialchars($_POST['message']);
		if($_POST['disablemycode'] == 1)
		{
			$enablemycode = "no";
		}
			else
		{
			$enablemycode = $settings['weblogs_mycode'];
		}
		if($_POST['disable_smilies'] == 1)
		{
			$enablesmilies = "no";
		}
		else
		{
			$enablesmilies = $settings['weblogs_smilies'];
		}
		$previewmessage = postify($_POST['message'], $settings['weblogs_html'], $enablemycode, $enablesmilies, $settings['weblogs_imgcode']);
		eval("\$preview = \"".$templates->get("weblogs_newpost_preview")."\";");
	}
	eval("\$newpost = \"".$templates->get("weblogs_newpost")."\";");
	outputpage($newpost);
}
elseif($bid)
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."weblogs WHERE bid='$bid'");
	$blog = $db->fetch_array($query);
	$blog['name'] = htmlspecialchars($blog['name']);
	$blog['description'] = htmlspecialchars($blog['description']);

	addnav($blog['name'], $settings['bburl']."/weblogs.php?bid=$bid");

	if(!$blog['bid'])
	{
		error($lang->invalid_blog);
	}

	if($blog['private'] == 1)
	{
		error($lang->private_blog);
	}

	if($blog['canview'] == 1 && !$mybb['uid'])
	{
		nopermission();
	}
$settings['weblogs_default_epp'] = "10";
	if($blog['epp'] < 1)
	{
		$blog['epp'] = $settings['weblogs_default_epp'];
	}
	$blocks['1'] = "latest_10_posts";
	$blocks['2'] = "archive_months";

	foreach($blocks as $blockfile)
	{
		if(file_exists("./inc/modules/weblogs/block.".$blockfile.".php"))
		{
			require_once "./inc/modules/weblogs/block.".$blockfile.".php";
			$func = "block_".$blockfile;
			$rightblocks .= $func();
		}
	}

	if($action == "archives") // Show the archives listing
	{
	}
	else // Show pages of entries
	{
		$publishedwhere = "";
		if($mybb['uid'] != $blog['uid'])
		{
			$publishedwhere = " AND status='1'";
		}
		$categoriesexp = explode("\n", $blog['categories']);
		foreach($categoriesexp as $category)
		{
			$categories[strtolower($category)] = $category;
		}
		// Do Multi Pages
		$query = $db->query("SELECT COUNT(*) AS entries FROM ".TABLE_PREFIX."weblogs_entries WHERE 1=1 $publishedwhere AND bid='$bid'");
		$entrycount = $db->result($query, 0);
		if($page)
		{
			$start = ($page-1) *$perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}
		$multipage = multipage($entrycount['entries'], $blog['epp'], $page, "weblogs.php?bid=$bid");

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."weblogs_entries WHERE 1=1 $publishedwhere ORDER BY dateline DESC LIMIT $start, ".$blog['epp']);
		while($entry = $db->fetch_array($query))
		{
			$entry['title'] = htmlspecialchars($entry['title']);
			$entrydate = mydate($settings['dateformat'], $entry['dateline']);
			$entrytime = mydate($settings['timeformat'], $entry['dateline']);
			if($entry['disablemycode'] == 1)
			{
				$enable_mycode = "no";
			}
			else
			{
				$enable_mycode = $settings['weblogs_mycode'];
			}
			if($entry['disablesmilies'] == 1)
			{
				$enable_smilies = "no";
			}
			else
			{
				$enable_smilies = $settings['weblogs_smilies'];
			}
			$cats = explode(",", $entry['categories']);
			foreach($cats as $cat)
			{
				if($categories[strtolower($cat)])
				{
					$entry_categories .= "$comma<a href=\"".$settings['bburl']."/weblogs.php?bid=$bid&category=".urlencode(strtolower($cat))."\">$cat</a>";
					$comma = ",";
				}
			}
			if($entry_categories)
			{
				$entry_categories = $lang->posted_in." ".$entry_categories;
			}
			$content = postify($entry['content'], $settings['weblogs_html'], $enable_mycode, $allow_smilies, $settings['weblogs_imgcode']);
			eval("\$entries .= \"".$templates->get("weblogs_blog_entries_entry")."\";");
			$entry_categories = "";
		}
		eval("\$blogcontent = \"".$templates->get("weblogs_blog_entries")."\";");
	}
	eval("\$weblog = \"".$templates->get("weblogs_blog")."\";");
	outputpage($weblog);
}
else
{
	if(!$settings['blogs_per_page'])
	{
		$settings['blogs_per_page'] = 10;
	}
	// Do Multi Pages
	$query = $db->query("SELECT COUNT(*) AS blogs FROM ".TABLE_PREFIX."weblogs WHERE private='0'");
	$blogcount = $db->result($query, 0);
	if($page)
	{
		$start = ($page-1) *$perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$multipage = multipage($blogcount['blogs'], $settings['blogs_per_page'], $page, "weblogs.php?");

	$query = $db->query("SELECT b.*, u.username FROM ".TABLE_PREFIX."weblogs b LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=b.uid) WHERE b.private='0' ORDER BY lastpost DESC LIMIT $start, ".$settings['blogs_per_page']);
	while($blog = $db->fetch_array($query))
	{
		$blog['name'] = htmlspecialchars($blog['name']);
		$blog['description'] = htmlspecialchars($blog['description']);
		if($blog['lastpost'] && $blog['entries'] > 0)
		{
			$lastpostdate = mydate($settings['dateformat'], $blog['lastpost']);
			$lastposttime = mydate($settings['timeformat'], $blog['lastpost']);
			$lastposttitle = $blog['lastposttitle'];
			if(strlen($blog['lastposttitle']) > 25)
			{
				$lastposttitle = substr($lastposttitle, 0, 25)."...";
			}
			$lastposttitle = htmlspecialchars($lastpostitle);
			eval("\$lastpost = \"".$templates->get("weblogs_listing_blog_lastpost")."\";");
		}
		else
		{
			if($blog['type'] == 1)
			{
				$lastpost = "<span style=\"text-align: center;\">-</span>";
			}
			else
			{
				$lastpost = "<span style=\"text-align: center;\">".$lang->never."</span>";
			}
		}
		if($blog['lastpost'] > $mybb['lastvisit'])
		{
			$onoff = "on";
			$onoffalt = $lang->weblog_onoff_on;
		}
		else
		{
			$onoff = "off";
			$onoffalt = $lang->weblog_onoff_off;
		}
		$trow = alt_trow();
		eval("\$blogs .= \"".$templates->get("weblogs_listing_blog")."\";");
	}
	if(!$blogs)
	{
			eval("\$blogs = \"".$templates->get("weblogs_listing_none")."\";");
	}
	eval("\$listing = \"".$templates->get("weblogs_listing")."\";");
	outputpage($listing);
}

?>