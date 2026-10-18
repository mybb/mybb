<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define("NO_ONLINE", 1);
define('THIS_SCRIPT', 'captcha.php');
define("ALLOWABLE_PAGE", 1);

require_once "./global.php";

if(!function_exists("imagepng") || !function_exists("imagettftext"))
{
	http_response_code(503);
	exit;
}

$mybb->input['imagehash'] = $mybb->get_input('imagehash');
if($mybb->input['imagehash'])
{
	$query = $db->simple_select("captcha", "*", "imagehash='".$db->escape_string($mybb->get_input('imagehash'))."' AND used=0", array("limit" => 1));
	$regimage = $db->fetch_array($query);
	if(!$regimage)
	{
		exit;
	}
	$imagestring = $regimage['imagestring'];
}
else
{
	exit;
}

$image = (new \MyBB\Captcha\Generator())->render($imagestring);

// Mark the image as fetched after rendering succeeds, without consuming the answer.
$db->update_query('captcha', array('used' => 1), "imagehash='".$db->escape_string($regimage['imagehash'])."'");

header("Content-type: image/png");
header("Cache-Control: no-store");
echo $image;
