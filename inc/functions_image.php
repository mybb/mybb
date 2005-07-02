<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

function generate_thumbnail($file, $path, $filename, $maxheight, $maxwidth)
{
	if(!function_exists("imagecreate"))
	{
		$thumb['code'] = 3;
		return $thumb;
	}
	list($imgwidth, $imgheight, $imgtype, $imgattr) = getimagesize($file);
	if(($imgwidth > $maxwidth) || ($imgheight > $maxheight))
	{
		if($imgtype == 3)
		{
			if( function_exists("imagecreatefrompng"))
			{
				$im = imagecreatefrompng($file);
			}
		}
		elseif($imgtype == 2)
		{
			if(function_exists("imagecreatefromjpeg"))
			{
				$im = imagecreatefromjpeg($file);
			}
		}
		elseif($imgtype == 1)
		{
			if(function_exists("imagecreatefromgif"))
			{
				$im = imagecreatefromgif($file);
			}
		}
		else
		{
			$thumb['code'] = 3;
			return $thumb;
		}
		if(!$im)
		{
			$thumb['code'] = 3;
			return $thumb;
		}
		$scale = scaleImage($imgwidth, $imgheight, $maxwidth, $maxheight);
		$thumbwidth = $scale['width'];
		$thumbheight = $scale['height'];
		$thumbim = @imagecreatetruecolor($thumbwidth, $thumbheight);
		if($thumbim)
		{
			imagecopyresampled($thumbim, $im, 0, 0, 0, 0, $thumbwidth, $thumbheight, $imgwidth,$imgheight);
		}
		else
		{
			$thumbim = imagecreate($thumbwidth, $thumbheight);
			imagecopyresized($thumbim, $im, 0, 0, 0, 0, $thumbwidth, $thumbheight, $imgwidth, $imgheight);
		}
		@imagedestroy($im);
		if(!function_exists("imagegif") && $imgtype == 1)
		{
			$filename = str_replace(".gif", ".jpg", $filename);
		}
		switch($imgtype)
		{
			case 1:
				if(function_exists("imagegif"))
				{
					@imagegif($thumbim, $path."/".$filename);
				}
				else
				{
					@imagejpeg($thumbim, $path."/".$filename);
				}
				break;
			case 2:
				@imagejpeg($thumbim, $path."/".$filename);
				break;
			case 3:
				@imagepng($thumbim, $path."/".$filename);
				break;
		}
		@chmod($path."/".$filename, 0666);
		@imagedestroy($thumbim);
		$thumb['code'] = 1;
		$thumb['filename'] = $filename;
		return $thumb;
	}
}

function scaleImage($width, $height, $maxwidth, $maxheight)
{
	$newwidth = $width;
	$newheight = $height;

	if($width > $maxwidth)
	{
		$newwidth = $maxwidth;
		$newheight = ceil(($height*(($maxwidth*100)/$width))/100);
		$height = $newheight;
		$width = $newwidth;
	}
	if($height > $maxheight)
	{
		$newheight = $maxheight;
		$newwidth = ceil(($width*(($maxheight*100)/$height))/100);
	}
	$ret['width'] = $newwidth;
	$ret['height'] = $newheight;
	return $ret;
}
?>