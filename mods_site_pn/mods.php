<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'mods.php');

// Templates used by Mods Site
$templatelist  = "mods_index";

require_once "./global.php";

define("MYBB_VERSIONS", '16x,14x,12x');
define("RELEVANCE", 3); // defines the relevance for similar projects

require_once MYBB_ROOT."inc/plugins/mods/Mods.php";
$mods = Mods::getInstance();

// load language
$lang->load("mods");

// filter $mybb->input['action'] - we do not want invalid actions here.
$action = $mods->filterAction();
if ($action === false)
	$mods->error();
	
$mods->verifyPermissions();

$title = $lang->mods;

// Build Menu!
$menu = $mods->buildMenu();

// Build Navigation Highlight
$mods->buildNavHighlight();

// User or Guest block?
if ($mybb->user['uid'])
{
	$mybb->user['username'] = htmlspecialchars_uni($mybb->user['username']);
	eval("\$userblock = \"".$templates->get("mods_userblock")."\";");
}
else 
{
	$mybb->user['username'] = $lang->guest;
	eval("\$guestblock = \"".$templates->get("mods_guestblock")."\";");
}

$locationforjs = '';

/**** LOAD MODULE *****/
if ($mods->module_load($action))
{
	$primer['title'] = $lang->mods_primer_index_title;
	$primer['content'] = $lang->mods_primer_index_content;
	
	$meta_description = $primer['content'];
	
	$primer['content'] = '<p>'.$primer['content'].'</p>';
	
	eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
	eval("\$content = \"".$templates->get("mods_index")."\";");
}

/* 
 ************************ START **************************
 * Certain actions must be here because of inline errors *
 *********************************************************
*/

$mods->module_run_pre();

/* 
 ************************ END **************************
 * Certain actions must be here because of inline errors *
 *********************************************************
*/

$mods->module_run();

eval("\$page = \"".$templates->get("mods")."\";");

output_page($page);

// Terminate!
$mods->module_terminate();

exit;

?>