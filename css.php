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


define("NO_ONLINE", 1);

require "./inc/init.php";

$theme = intval($mybb->input['theme']);
$cssfile = "./css{$theme}.css";

// If there is a theme set in the input, use the tid, otherwise use default.
if($theme)
{
	$options = array(
		"limit" => 1
	);
	$query = $db->simple_select(TABLE_PREFIX."themes", "css", "tid=".$theme, $options);
}
else
{
	$options = array(
		"limit" => 1
	);
	$query = $db->simple_select(TABLE_PREFIX."themes", "css", "def=1", $options);
}
$theme = $db->fetch_array($query);

header("Content-type: text/css");
echo $theme['css'];
exit;
?>