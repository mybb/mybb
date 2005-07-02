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
$lang->load("maintenance");

checkadminpermissions("canrunmaint");
logadmin();

switch($action)
{
	case "cache":
		addacpnav($lang->nav_cache_manager, "maintenance.php?action=cache");
		break;
	case "do_cache":
		if($view)
		{
			addacpnav($lang->cache_manager, "maintenance.php?action=cache");
			addacpnav($lang->nav_view_cache);
		}
		break;
}

if($action == "do_cache")
{
	if($view)
	{
		cpheader();
		starttable();
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE sid='-3' AND title='$cacheitem'");
		$cacheitem = $db->fetch_array($query);
		$cachecontents = unserialize($cacheitem['template']);
		if(empty($cachecontents))
		{
			$cachecontents = "Cache is empty.";
		}
		ob_start();
		print_r($cachecontents);
		$data = htmlspecialchars(ob_get_contents());
		ob_end_clean();
		makelabelcode("<pre>$data</pre>", "");
		endtable();
		cpfooter();
	}

	if($refresh)
	{
		if(method_exists($cache, "update$cacheitem"))
		{
			$func = "update$cacheitem";
			$cache->$func();
			cpredirect("maintenance.php?action=cache", $lang->cache_updated);
		}
		else
		{
			cpmessage($lang->nocache_update);
		}
	}
}

if($action == "cache")
{
	cpheader();
	starttable();
	tableheader($lang->cache_manager, "", "4");
	echo "<tr>\n";
	echo "<td class=\"subheader\">$lang->name</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->size</td>\n";
	echo "<td class=\"subheader\" align=\"center\" colspan=\"2\">$lang->options</td>\n";
	echo "</tr>\n";
	$query = $db->query("SELECT title, template FROM ".TABLE_PREFIX."templates WHERE sid='-3'");
	while($cacheitem = $db->fetch_array($query))
	{
		$size = getfriendlysize(strlen($cacheitem['template']));
		$bgcolor = getaltbg();
		startform("maintenance.php", "", "do_cache");
		makehiddencode("cacheitem", $cacheitem['title']);
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\" width=\"50%\">$cacheitem[title]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"15%\">$size</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"submit\" name=\"view\" value=\"$lang->view_contents\" class=\"submitbutton\"></td>";
		if(method_exists($cache, "update".$cacheitem['title']))
		{
			echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"submit\" name=\"refresh\" value=\"$lang->refresh_cache\" class=\"submitbutton\"></td>";
		}
		echo "</tr>\n";
		endform();
	}
	endtable();
	cpfooter();
}

if($action == "rebuildstats") {
	$cache->updatestats();
	cpmessage($lang->stats_rebuilt);
}

?>