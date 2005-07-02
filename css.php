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

define("KILL_GLOBALS", 1);

require "./inc/init.php";

$theme = intval($mybb->input['theme']);

if($theme)
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."themes WHERE tid='$theme'");
}
else
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."themes WHERE def='1'");
}
$theme = $db->fetch_array($query);

// Find out if we are using header/category background images


header("Content-type: text/css");
echo $theme['css'];
exit;
?>