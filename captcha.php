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

$img_width = 200;
$img_height = 60;

// The following settings are only used for TTF fonts
$min_size = 20;
$max_size = 32;

$min_angle = -30;
$max_angle = 30;

$mybb->input['imagehash'] = $mybb->get_input('imagehash');
if($mybb->input['imagehash'])
{
	$query = $db->simple_select("captcha", "*", "imagehash='".$db->escape_string($mybb->get_input('imagehash'))."' AND used=0", array("limit" => 1));
	$regimage = $db->fetch_array($query);
	if(!$regimage)
	{
		exit;
	}
	// Mark captcha as used
	$db->update_query('captcha', array('used' => 1), "imagehash='".$db->escape_string($regimage['imagehash'])."'");
	$imagestring = $regimage['imagestring'];
}
else
{
	exit;
}

$ttf_fonts = array();

// We have support for true-type fonts (FreeType 2)
if(function_exists("imagefttext"))
{
	// Get a list of the files in the 'catpcha_fonts' directory
	$ttfdir  = @opendir(MYBB_ROOT."inc/captcha_fonts");
	if($ttfdir !== false)
	{
		while(($file = readdir($ttfdir)) !== false)
		{
			// If this file is a ttf file, add it to the list
			if(is_file(MYBB_ROOT."inc/captcha_fonts/".$file) && get_extension($file) == "ttf")
			{
				$ttf_fonts[] = MYBB_ROOT."inc/captcha_fonts/".$file;
			}
		}
		closedir($ttfdir);
	}
}

// Have one or more TTF fonts in our array, we can use TTF captha's
if(count($ttf_fonts) > 0)
{
	$use_ttf = 1;
}
else
{
	$use_ttf = 0;
}

// Check for GD >= 2, create base image
if(gd_version() >= 2)
{
	$im = imagecreatetruecolor($img_width, $img_height);
}
else
{
	$im = imagecreate($img_width, $img_height);
}

// No GD support, die.
if(!$im)
{
	die("No GD support.");
}

// Fill the background with white
$bg_color = imagecolorallocate($im, 255, 255, 255);
imagefill($im, 0, 0, $bg_color);

// Draw random circles, squares or lines?
$to_draw = my_rand(0, 2);
if($to_draw == 1)
{
	draw_circles($im);
}
else if($to_draw == 2)
{
	draw_squares($im);
}
else
{
	draw_lines($im);
}

// Draw dots on the image
draw_dots($im);

// Write the image string to the image
draw_string($im, $imagestring);

// Draw a nice border around the image
$border_color = imagecolorallocate($im, 0, 0, 0);
imagerectangle($im, 0, 0, $img_width-1, $img_height-1, $border_color);

// Output the image
header("Content-type: image/png");
imagepng($im);
imagedestroy($im);

/**
 * Draws a random number of lines on the image.
 *
 * @param resource $im The image.
 */
function draw_lines(&$im)
{
	global $img_width, $img_height;

	for($i = 10; $i < $img_width; $i += 10)
	{
		$color = imagecolorallocate($im, my_rand(150, 255), my_rand(150, 255), my_rand(150, 255));
		imageline($im, $i, 0, $i, $img_height, $color);
	}
	for($i = 10; $i < $img_height; $i += 10)
	{
		$color = imagecolorallocate($im, my_rand(150, 255), my_rand(150, 255), my_rand(150, 255));
		imageline($im, 0, $i, $img_width, $i, $color);
	}
}

/**
 * Draws a random number of circles on the image.
 *
 * @param resource $im The image.
 */
function draw_circles(&$im)
{
	global $img_width, $img_height;

	$circles = $img_width*$img_height / 100;
	for($i = 0; $i <= $circles; ++$i)
	{
		$color = imagecolorallocate($im, my_rand(180, 255), my_rand(180, 255), my_rand(180, 255));
		$pos_x = my_rand(1, $img_width);
		$pos_y = my_rand(1, $img_height);
		$circ_width = ceil(my_rand(1, $img_width)/2);
		$circ_height = my_rand(1, $img_height);
		imagearc($im, $pos_x, $pos_y, $circ_width, $circ_height, 0, my_rand(200, 360), $color);
	}
}

/**
 * Draws a random number of dots on the image.
 *
 * @param resource $im The image.
 */
function draw_dots(&$im)
{
	global $img_width, $img_height;

	$dot_count = $img_width*$img_height/5;
	for($i = 0; $i <= $dot_count; ++$i)
	{
		$color = imagecolorallocate($im, my_rand(200, 255), my_rand(200, 255), my_rand(200, 255));
		imagesetpixel($im, my_rand(0, $img_width), my_rand(0, $img_height), $color);
	}
}

/**
 * Draws a random number of squares on the image.
 *
 * @param resource $im The image.
 */
function draw_squares(&$im)
{
	global $img_width, $img_height;

	$square_count = 30;
	for($i = 0; $i <= $square_count; ++$i)
	{
		$color = imagecolorallocate($im, my_rand(150, 255), my_rand(150, 255), my_rand(150, 255));
		$pos_x = my_rand(1, $img_width);
		$pos_y = my_rand(1, $img_height);
		$sq_width = $sq_height = my_rand(10, 20);
		$pos_x2 = $pos_x + $sq_height;
		$pos_y2 = $pos_y + $sq_width;
		imagefilledrectangle($im, $pos_x, $pos_y, $pos_x2, $pos_y2, $color);
	}
}

/**
 * Writes text to the image.
 *
 * @param resource $im The image.
 * @param string $string The string to be written
 *
 * @return bool False if string is empty, true otherwise
 */
function draw_string(&$im, $string)
{
	global $use_ttf, $min_size, $max_size, $min_angle, $max_angle, $ttf_fonts, $img_height, $img_width;

	if(empty($string))
	{
		return false;
	}

	$spacing = $img_width / my_strlen($string);
	$string_length = my_strlen($string);
	for($i = 0; $i < $string_length; ++$i)
	{
		// Using TTF fonts
		if($use_ttf)
		{
			// Select a random font size
			$font_size = my_rand($min_size, $max_size);

			// Select a random font
			$font = array_rand($ttf_fonts);
			$font = $ttf_fonts[$font];

			// Select a random rotation
			$rotation = my_rand($min_angle, $max_angle);

			// Set the colour
			$r = my_rand(0, 200);
			$g = my_rand(0, 200);
			$b = my_rand(0, 200);
			$color = imagecolorallocate($im, $r, $g, $b);

			// Fetch the dimensions of the character being added
			$dimensions = imageftbbox($font_size, $rotation, $font, $string[$i], array());
			$string_width = $dimensions[2] - $dimensions[0];
			$string_height = $dimensions[3] - $dimensions[5];

			// Calculate character offsets
			//$pos_x = $pos_x + $string_width + ($string_width/4);
			$pos_x = $spacing / 4 + $i * $spacing;
			$pos_y = ceil(($img_height-$string_height/2));

			// Draw a shadow
			$shadow_x = my_rand(-3, 3) + $pos_x;
			$shadow_y = my_rand(-3, 3) + $pos_y;
			$shadow_color = imagecolorallocate($im, $r+20, $g+20, $b+20);
			imagefttext($im, $font_size, $rotation, $shadow_x, $shadow_y, $shadow_color, $font, $string[$i], array());

			// Write the character to the image
			imagefttext($im, $font_size, $rotation, $pos_x, $pos_y, $color, $font, $string[$i], array());
		}
		else
		{
			// Get width/height of the character
			$string_width = imagefontwidth(5);
			$string_height = imagefontheight(5);

			// Calculate character offsets
			$pos_x = $spacing / 4 + $i * $spacing;
			$pos_y = $img_height / 2 - $string_height -10 + my_rand(-3, 3);

			// Create a temporary image for this character
			if(gd_version() >= 2)
			{
				$temp_im = imagecreatetruecolor(15, 20);
			}
			else
			{
				$temp_im = imagecreate(15, 20);
			}
			$bg_color = imagecolorallocate($temp_im, 255, 255, 255);
			imagefill($temp_im, 0, 0, $bg_color);
			imagecolortransparent($temp_im, $bg_color);

			// Set the colour
			$r = my_rand(0, 200);
			$g = my_rand(0, 200);
			$b = my_rand(0, 200);
			$color = imagecolorallocate($temp_im, $r, $g, $b);

			// Draw a shadow
			$shadow_x = my_rand(-1, 1);
			$shadow_y = my_rand(-1, 1);
			$shadow_color = imagecolorallocate($temp_im, $r+50, $g+50, $b+50);
			imagestring($temp_im, 5, 1+$shadow_x, 1+$shadow_y, $string[$i], $shadow_color);

			imagestring($temp_im, 5, 1, 1, $string[$i], $color);

			// Copy to main image
			imagecopyresized($im, $temp_im, $pos_x, $pos_y, 0, 0, 40, 55, 15, 20);
			imagedestroy($temp_im);
		}
	}
	return true;
}

