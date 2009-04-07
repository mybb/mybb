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
	$table->construct_header($lang->product_managers, array('width' => '20%'));
	$table->construct_header($lang->developers, array('width' => '20%'));
	$table->construct_header($lang->graphics_and_style, array('width' => '20%'));
	$table->construct_header($lang->software_quality_assurance, array('width' => '20%'));
	$table->construct_header($lang->support_representative, array('width' => '20%'));
	
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-1.html\" target=\"_blank\">Chris Boulton</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-2165.html\" target=\"_blank\">Ryan Gordon</a>");	
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-1.html\" target=\"_blank\">Chris Boulton</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-895.html\" target=\"_blank\">Michael Schlechtinger</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-3971.html\" target=\"_blank\">Ryan Loos</a>");
	$table->construct_row();
	
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-14.html\" target=\"_blank\">Alan Crisp</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-81.html\" target=\"_blank\">Dennis Tsang</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-1830.html\" target=\"_blank\">Justin S.</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-1331.html\" target=\"_blank\">Chris</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-2249.html\" target=\"_blank\">Kevin Camps</a>");
	$table->construct_row();	
	
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-27.html\" target=\"_blank\">Tom Huls</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-14621.html\" target=\"_blank\">Tom Moore</a>");
	$table->construct_cell("&nbsp;");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-2824.html\" target=\"_blank\">Stefan T.</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-13556.html\" target=\"_blank\">Matt Rogowski</a>");
	$table->construct_row();
	
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-81.html\" target=\"_blank\">DennisTT</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-1923.html\" target=\"_blank\">Sergio Montoya</a>");
	$table->construct_cell("&nbsp;");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-8242.html\" target=\"_blank\">dvb</a>");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-8469.html\" target=\"_blank\">Tom Loveric</a>");
	$table->construct_row();
	
	$table->construct_cell("&nbsp;");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-6391.html\" target=\"_blank\">Jason Martin</a>");
	$table->construct_cell("&nbsp;");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-6928.html\" target=\"_blank\">Imad Jomaa</a>");
	$table->construct_cell("&nbsp;");
	$table->construct_row();
	
	$table->construct_cell("&nbsp;");
	$table->construct_cell("&nbsp;");
	$table->construct_cell("&nbsp;");
	$table->construct_cell("<a href=\"http://community.mybboard.net/user-9138.html\" target=\"_blank\">Max Marze</a>");
	$table->construct_cell("&nbsp;");
	$table->construct_row();
	
	$table->output($lang->mybb_credits);
	
	$page->output_footer();
}

?>