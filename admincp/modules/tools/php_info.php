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

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

if($mybb->input['action'] == 'phpinfo')
{
	// Log admin action
	log_admin_action();

	phpinfo();
	exit;
}

$page->add_breadcrumb_item($lang->php_info, "index.php?".SID."&amp;module=tools/php_info");

if(!$mybb->input['action'])
{
	$page->output_header($lang->php_info);
	
	echo "<iframe src=\"index.php?".SID."&amp;module=tools/php_info&amp;action=phpinfo\" width=\"100%\" height=\"500\" frameborder=\"0\">{$lang->browser_no_iframe_support}</iframe>";
	
	$page->output_footer();
}

?>