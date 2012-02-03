<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: rss.php 5297 2010-12-28 22:01:14Z Tomm $
 */

/* Redirect traffic using old URI to new URI. */
$_SERVER['QUERY_STRING'] = str_replace(array("\n", "\r"), "", $_SERVER['QUERY_STRING']); 
header("Location: syndication.php?".$_SERVER['QUERY_STRING']);

?>