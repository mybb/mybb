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

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item("MyBB Credits", "index.php?".SID."&amp;module=home/credits");

if(!$mybb->input['action'])
{
	$page->output_header("Credits");
	
	$sub_tabs['credits'] = array(
		'title' => "MyBB Credits",
		'link' => "index.php?".SID."&amp;module=home/credits",
		'description' => "These people have contributed their time and effort to create MyBB."
	);
	
	$sub_tabs['credits_about'] = array(
		'title' => "About the Team",
		'link' => "http://mybboard.net/about/team",
		'link_target' => "_blank",
		'description' => "Some facts about the MyBB Team."
	);

	$page->output_nav_tabs($sub_tabs, 'credits');
	
	$table = new Table;
	$table->construct_header("Product Managers", array('width' => '33%'));
	$table->construct_header("Developers", array('width' => '33%'));
	$table->construct_header("Graphics and Style", array('width' => '33%'));
	
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=1\" target=\"_blank\">Chris Boulton</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=1\" target=\"_blank\">Chris Boulton</a>");	
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=1\" target=\"_blank\">Chris Boulton</a>");
	$table->construct_row();
	
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=14\" target=\"_blank\">Musicalmidget</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=81\" target=\"_blank\">DennisTT</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=5\" target=\"_blank\">Scott Hough</a>");
	$table->construct_row();	
	
	$table->construct_cell("&nbsp;");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=240\" target=\"_blank\">Peter</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=1830\" target=\"_blank\">Justin S.</a>");
	$table->construct_row();
	
	$table->construct_cell("&nbsp;");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=2165\" target=\"_blank\">Tikitiki</a>");
	$table->construct_cell("&nbsp;");
	$table->construct_row();
	
	$table->construct_cell("&nbsp;");
	$table->construct_cell("<a href=\"http://community.mybboard.net/member.php?action=profile&amp;uid=1959\" target=\"_blank\">Crakter</a>");
	$table->construct_cell("&nbsp;");
	$table->construct_row();
	
	$table->output("MyBB Credits");
	
	$page->output_footer();
}

?>