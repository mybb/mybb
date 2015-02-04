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
 * Output the archive page header.
 *
 * @param string The page title.
 * @param string The full page title.
 * @param string The full page URL.
 */
function archive_header($title="", $fulltitle="", $fullurl="")
{
	global $mybb, $lang, $db, $nav, $archiveurl, $sent_header;

	// Build the archive navigation.
	$nav = archive_navigation();

	// If there is a title, append it to the bbname.
	if(!$title)
	{
		$title = $mybb->settings['bbname'];
	}
	else
	{
		$title = $mybb->settings['bbname']." - ".$title;
	}

	// If the language doesn't have a charset, make it UTF-8.
	if($lang->settings['charset'])
	{
		$charset = $lang->settings['charset'];
	}
	else
	{
		$charset = "utf-8";
	}

	$dir = '';
	if($lang->settings['rtl'] == 1)
	{
		$dir = " dir=\"rtl\"";
	}

	if($lang->settings['htmllang'])
	{
		$htmllang = " xml:lang=\"".$lang->settings['htmllang']."\" lang=\"".$lang->settings['htmllang']."\"";
	}
	else
	{
		$htmllang = " xml:lang=\"en\" lang=\"en\"";
	}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"<?php echo $dir; echo $htmllang; ?>>
<head>
<title><?php echo $title; ?></title>
<meta http-equiv="content-type" content="text/html; charset=<?php echo $charset; ?>" />
<meta name="robots" content="index,follow" />
<link type="text/css" rel="stylesheet" rev="stylesheet" href="<?php echo $archiveurl; ?>/screen.css" media="screen" />
<link type="text/css" rel="stylesheet" rev="stylesheet" href="<?php echo $archiveurl; ?>/print.css" media="print" />
</head>
<body>
<div id="container">
<h1><a href="<?php echo $mybb->settings['bburl']; ?>/index.php"><?php echo $mybb->settings['bbname_orig']; ?></a></h1>
<div class="navigation"><?php echo $nav; ?></div>
<div id="fullversion"><strong><?php echo $lang->archive_fullversion; ?></strong> <a href="<?php echo $fullurl; ?>"><?php echo $fulltitle; ?></a></div>
<div id="infobox"><?php echo $lang->sprintf($lang->archive_note, $fullurl); ?></div>
<div id="content">
<?php
	$sent_header = 1;
}

/**
 * Build the archive navigation.
 *
 * @return string The build navigation
 */
function archive_navigation()
{
	global $navbits, $mybb, $lang;

	$navsep = " &gt; ";
	$nav = $activesep = '';
	if(is_array($navbits))
	{
		reset($navbits);
		foreach($navbits as $key => $navbit)
		{
			if(!empty($navbits[$key+1]))
			{
				if(!empty($navbits[$key+2]))
				{
					$sep = $navsep;
				}
				else
				{
					$sep = "";
				}
				$nav .= "<a href=\"".$navbit['url']."\">".$navbit['name']."</a>$sep";
			}
		}
	}
	$navsize = count($navbits);
	$navbit = $navbits[$navsize-1];
	if(!empty($nav))
	{
		$activesep = $navsep;
	}
	$nav .= $activesep.$navbit['name'];

	return $nav;
}

/**
 * Output multipage navigation.
 *
 * @param int The total number of items.
 * @param int The items per page.
 * @param int The current page.
 * @param string The URL base.
*/
function archive_multipage($count, $perpage, $page, $url)
{
	global $lang;
	if($count > $perpage)
	{
		$pages = $count / $perpage;
		$pages = ceil($pages);

		$mppage = null;
		for($i = 1; $i <= $pages; ++$i)
		{
			if($i == $page)
			{
				$mppage .= "<strong>$i</strong> ";
			}
			else
			{
				$mppage .= "<a href=\"$url-$i.html\">$i</a> ";
			}
		}
		$multipage = "<div class=\"multipage\"><strong>".$lang->archive_pages."</strong> $mppage</div>";
		echo $multipage;
	}
}

/**
 * Output the archive footer.
 *
 */
function archive_footer()
{
	global $mybb, $lang, $db, $nav, $maintimer, $fulltitle, $fullurl, $sent_header;
	$totaltime = $maintimer->stop();
	if($mybb->settings['showvernum'] == 1)
	{
		$mybbversion = ' '.$mybb->version;
	}
	else
	{
		$mybbversion = "";
	}
?>
</div>
<div class="navigation"><?php echo $nav; ?></div>
</div>
<div id="footer">
<?php echo $lang->powered_by; ?> <a href="http://www.mybb.com">MyBB</a><?php echo $mybbversion; ?>, &copy; 2002-<?php echo date("Y"); ?> <a href="http://www.mybb.com">MyBB Group</a>
</div>
</body>
</html>
<?php
}

/**
 * Output an archive error.
 *
 * @param string The error language string identifier.
 */
function archive_error($error)
{
	global $lang, $mybb, $sent_header;
	if(!$sent_header)
	{
		archive_header("", $mybb->settings['bbname'], $mybb->settings['bburl']."/index.php");
	}
?>
<div class="error">
<div class="header"><?php echo $lang->error; ?></div>
<div class="message"><?php echo $error; ?></div>
</div>
<?php
	archive_footer();
	exit;
}

/**
 * Ouput a "no permission"page.
 */
function archive_error_no_permission()
{
	global $lang, $db, $session;

	$noperm_array = array (
		"nopermission" => '1',
		"location1" => 0,
		"location2" => 0
	);

	$db->update_query("sessions", $noperm_array, "sid='{$session->sid}'");

	archive_error($lang->archive_nopermission);
}

/**
 * Check the password given on a certain forum for validity
 *
 * @param int The forum ID
 * @param boolean The Parent ID
 */
function check_forum_password_archive($fid, $pid=0)
{
	global $forum_cache;

	if(!is_array($forum_cache))
	{
		$forum_cache = cache_forums();
		if(!$forum_cache)
		{
			return false;
		}
	}

	// Loop through each of parent forums to ensure we have a password for them too
	$parents = explode(',', $forum_cache[$fid]['parentlist']);
	rsort($parents);
	if(!empty($parents))
	{
		foreach($parents as $parent_id)
		{
			if($parent_id == $fid || $parent_id == $pid)
			{
				continue;
			}

			if($forum_cache[$parent_id]['password'] != "")
			{
				check_forum_password_archive($parent_id, $fid);
			}
		}
	}

	$password = $forum_cache[$fid]['password'];
	if($password)
	{
		if(!$mybb->cookies['forumpass'][$fid] || ($mybb->cookies['forumpass'][$fid] && md5($mybb->user['uid'].$password) != $mybb->cookies['forumpass'][$fid]))
		{
			archive_error_no_permission();
		}
	}
}