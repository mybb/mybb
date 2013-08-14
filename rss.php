<?php
/**
 * MyBB 1.8
 * Copyright 2013 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * $Id$
 */

/* Redirect traffic using old URI to new URI. */
$string = '';
if(isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] != '')
{
	$string .= '?'.str_replace(array("\n", "\r"), "", $_SERVER['QUERY_STRING']);
}

header('Location: syndication.php'.$string);

?>