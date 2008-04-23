<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
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

$page->add_breadcrumb_item($lang->mybb_credits, "index.php?module=home/credits");

$plugins->run_hooks("admin_home_credits_begin");

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_home_credits_start");
	
	$page->output_header($lang->mybb_credits);
	
	$sub_tabs['credits'] = array(
		'title' => $lang->mybb_credits,
		'link' => "index.php?module=home/credits",
		'description' => $lang->mybb_credits_description
	);
	
	$sub_tabs['credits_about'] = array(
		'title' => $lang->about_the_team,
		'link' => "http://mybboard.net/about/team",
		'link_target' => "_blank",
	);

	$page->output_nav_tabs($sub_tabs, 'credits');
	
	$table = new Table;
	$table->construct_header($lang->product_managers, array('width' => '33%'));
	$table->construct_header($lang->developers, array('width' => '33%'));
	$table->construct_header($lang->graphics_and_style, array('width' => '33%'));
	
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=1\" target=\"_blank\">Chris Boulton</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=1\" target=\"_blank\">Chris Boulton</a>");	
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=1\" target=\"_blank\">Chris Boulton</a>");
	$table->construct_row();
	
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=14\" target=\"_blank\">Musicalmidget</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=81\" target=\"_blank\">DennisTT</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=5\" target=\"_blank\">Scott Hough</a>");
	$table->construct_row();	
	
	$table->construct_cell("&nbsp;");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=2165\" target=\"_blank\">Tikitiki</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=1830\" target=\"_blank\">Justin S.</a>");
	$table->construct_row();
	
	$table->construct_cell("&nbsp;");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=1653\" target=\"_blank\">DrPoodle</a>");
	$table->construct_cell("&nbsp;");
	$table->construct_row();
	
	$table->construct_cell("&nbsp;");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=7473\" target=\"_blank\">ZiNgA BuRgA</a>");
	$table->construct_cell("&nbsp;");
	$table->construct_row();
	
	$table->output($lang->mybb_credits);
	
	$page->output_footer();
}

?>