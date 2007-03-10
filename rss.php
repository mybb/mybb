<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

/* Redirect traffic using old URI to new URI. */
$_SERVER['QUERY_STRING'] = str_replace(array("\n", "\r"), "", $_SERVER['QUERY_STRING']); 
header("Location: syndication.php?".$_SERVER['QUERY_STRING']);

?>