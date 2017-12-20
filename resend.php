<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'resend.php');

//todo: activate template
//$templatelist = "multiforum_resend";

require_once './global.php';

// Load global language phrases
$lang->load('multiforums');


//make sure that multifoums feature is enabled, if not then die
if (!$multiforums->isEnabled()) {
	$multiforums->error($lang->not_enabled, MYBB_MULTIFORUMS_NOT_ENABLED);
	die();
}