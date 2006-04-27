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

$noonline = 1;
require "./global.php";

if($mybb->input['action'] == "regimage")
{
	if($mybb->input['imagehash'] == "test")
	{
		$imagestring = "MyBB";
	}
	else
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."regimages WHERE imagehash='".$db->escape_string($mybb->input['imagehash'])."'");
		$regimage = $db->fetch_array($query);
		$imagestring = $regimage['imagestring'];
		for($i=0;$i<strlen($imagestring);$i++)
		{
			$newstring .= $space.$imagestring[$i];
			$space = " ";
		}
		$imagestring = $newstring;
	}
	if(function_exists("imagecreatefrompng"))
	{
		$fontwidth = imageFontWidth(5);
		$fontheight = imageFontHeight(5);
		$textwidth = $fontwidth*strlen($imagestring);
		$textheight = $fontheight;
	
		$randimg = rand(1, 5);
		$im = imagecreatefrompng("images/regimages/reg".$randimg.".png");
	
		$imgheight = 40;
		$imgwidth = 150;
		$textposh = ($imgwidth-$textwidth)/2;
		$textposv = ($imgheight-$textheight)/2;
		
		// Lets draw some random dots
		if($imagehash != "test")
		{
			$dots = $imgheight*$imgwidth/35;
			for($i=1;$i<=$dots;$i++)
			{
				imagesetpixel($im, rand(0, $imgwidth), rand(0, $imgheight), $textcolor);
			}
		}
		
		$textcolor = imagecolorallocate($im, 0, 0, 0);
		imagestring($im, 5, $textposh, $textposv, $imagestring, $textcolor);
	
		// output the image
		header("Content-type: image/png");
		imagepng($im);
		imagedestroy($im);
		exit;
	}
	else
	{
		header("Location: images/clear.gif");
	}
}
else
{
	error($lang->error_invalidaction);
}
?>