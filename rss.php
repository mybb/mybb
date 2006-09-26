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

/* Redirect traffic using old URI to new URI. */
$_SERVER['QUERY_STRING'] = str_replace(array("\n", "\r"), "", $_SERVER['QUERY_STRING']);
header("Location: syndication.php?".$_SERVER['QUERY_STRING']);

?>