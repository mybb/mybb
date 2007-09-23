<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

/**
 * Return a timestamp from a date.
 *
 * @param int The ID of the folder.
 * @return string The name of the folder.
 */
function modcp_date2timestamp($date)
{
	$d = explode('-', $date);
	$nowdate = date("H-j-n-Y");
	$n = explode('-', $nowdate);
	if($n[0] >= 12)
	{
		$n[1] += 1;
	}
	$n[1] += $d[0];
	$n[2] += $d[1];
	$n[3] += $d[2];
	return mktime(0, 0, 0, $n[2], $n[1], $n[3]);
}

/**
 * Return the ban time remaining.
 *
 * @param string The.
 * @return string The name of the folder.
 */
function modcp_getbanremaining($lifted)
{
	global $lang;
	$remain = $lifted-time();
	$years = intval($remain/31536000);
	$months = intval($remain/2592000);
	$weeks = intval($remain/604800);
	$days = intval($remain/86400);
	$hours = intval($remain/3600);
	if($years > 1)
	{
		$r = "{$years} {$lang->years}";
	}
	elseif($years == 1)
	{
		$r = "1 {$lang->year}";
	}
	elseif($months > 1)
	{
		$r = "{$months} {$lang->months}";
	}
	elseif($months == 1)
	{
		$r = "1 {$lang->month}";
	}
	elseif($weeks > 1)
	{
		$r = "<span class=\"highlight3\">{$weeks} {$lang->weeks}</span>";
	}
	elseif($weeks == 1)
	{
		$r = "<span class=\"highlight2\">1 {$lang->week}</span>";
	}
	elseif($days > 1)
	{
		$r = "<span class=\"highlight2\">{$days} {$lang->days}</span>";
	}
	elseif($days == 1)
	{
		$r = "<span class=\"highlight1\">1 {$lang->day}</span>";
	}
	elseif($days < 1)
	{
		$r = "<span class=\"highlight1\">{$hours} {$lang->hours}</span>";
	}
	return $r;
}

?>