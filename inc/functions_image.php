<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/eula.html
 *
 * $Id$
 */

/**
 * Generates a thumbnail
 * 
 * @param string filename
 * @param string upload path
 * @param string thumbnail filename
 * @param int maximum thumbnail height
 * @param int maximum thumbnail width
 * @return array returns an array with error code and filename (Code 1: Thumbnail generated; Code 3: Could not create thumbnail; Code 4: Image does not need to be scaled)
 */
function generate_thumbnail($file, $path, $filename, $maxheight, $maxwidth)
{
	// Check if thumbnail can be generated and if the file is valid image
	if(!function_exists("imagecreate"))
	{
		$thumb['code'] = 3;
		return $thumb;
	}
	list($imgwidth, $imgheight, $imgtype, $imgattr, $imgbits, $imgchan) = getimagesize($file);
	if($imgwidth == 0 || $imgheight == 0)
	{
		$thumb['code'] = 3;
		return $thumb;
	}
	
	// Only generate a thumbnail if image is larger than maximum thumbnail size
	if(($imgwidth >= $maxwidth) || ($imgheight >= $maxheight))
	{
		// Up the memory limit if needed
		check_thumbnail_memory($imgwidth, $imgheight, $imgtype, $imgbits, $imgchan);
		
		// Get the image into memory based on file type
		if($imgtype == 3)
		{
			if(@function_exists("imagecreatefrompng"))
			{
				$im = @imagecreatefrompng($file);
			}
		}
		elseif($imgtype == 2)
		{
			if(@function_exists("imagecreatefromjpeg"))
			{
				$im = @imagecreatefromjpeg($file);
			}
		}
		elseif($imgtype == 1)
		{
			if(@function_exists("imagecreatefromgif"))
			{
				$im = @imagecreatefromgif($file);
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
		
		// Scale the image and resample
		$scale = scale_image($imgwidth, $imgheight, $maxwidth, $maxheight);
		$thumbwidth = $scale['width'];
		$thumbheight = $scale['height'];
		$thumbim = @imagecreatetruecolor($thumbwidth, $thumbheight);
		if($thumbim)
		{
			@imagecopyresampled($thumbim, $im, 0, 0, 0, 0, $thumbwidth, $thumbheight, $imgwidth,$imgheight);
		}
		else
		{
			$thumbim = @imagecreate($thumbwidth, $thumbheight);
			@imagecopyresized($thumbim, $im, 0, 0, 0, 0, $thumbwidth, $thumbheight, $imgwidth, $imgheight);
		}
		@imagedestroy($im);
		
		// Output image to file based on file type
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
	else
	{
		return array("code" => 4);
	}
}

function check_thumbnail_memory($width, $height, $type, $bitdepth, $channels)
{
	if(!function_exists("memory_get_usage"))
	{
		return false;
	}

	$memory_limit = @ini_get("memory_limit");
	if(!$memory_limit || $memory_limit == -1)
	{
		return false;
	}

	$limit = preg_match("#^([0-9]+)\s?([kmg])b?$#i", trim(strtolower($memory_limit)), $matches);
	$memory_limit = 0;
	if($matches[1] && $matches[2])
	{
		switch($matches[2])
		{
			case "k":
				$memory_limit = $matches[1] * 1024;
				break;
			case "m":
				$memory_limit = $matches[1] * 1048576;
				break;
			case "g":
				$memory_limit = $matches[1] * 1073741824;
		}
	}
	$current_usage = memory_get_usage();
	$free_memory = $memory_limit - $current_usage;
	
	$thumbnail_memory = round(($width * $height * $bitdepth * $channels / 8) * 5);
	$thumbnail_memory += 2097152;
	
	if($thumbnail_memory > $free_memory)
	{
		@ini_set("memory_limit", $memory_limit+$thumbnail_memory);
	}
}

function scale_image($width, $height, $maxwidth, $maxheight)
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