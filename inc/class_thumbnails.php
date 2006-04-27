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

class MyThumbnail {
	
	/**
	 * Generates a thumbnail
	 *
	 * @param string The filename to save to.
	 * @param int The maximum height of the thumbnail.
	 * @param int The maximum width of the thumbnail.
	 * @return array Return array.
	 */
	function generate($file, $maxheight, $maxwidth)
	{
		if(!$this->gdVersion())
		{
			$code = 3;
		}
		else
		{
			list($imgwidth, $imgheight, $imgtype, $imgattr) = @getimagesize($file);
			if(($imgwidth > $maxwidth) || ($imgheight > $maxheight))
			{
				if($imgtype == 3)
				{
					if( function_exists("imagecreatefrompng"))
					{
						$im = @imagecreatefrompng($file);
					}
				}
				elseif($imgtype == 2)
				{
					if(function_exists("imagecreatefromjpeg"))
					{
						$im = @imagecreatefromjpeg($file);
					}
				}
				elseif($imgtype == 1)
				{
					if(function_exists("imagecreatefromgif"))
					{
						$im = @imagecreatefromgif($file);
					}
				}
				else
				{
					$code = 3;
				}
				if(!$im)
				{
					$code = 3;
				}
				else
				{
					$scale = $this->scaleImage($imgwidth, $imgheight, $maxwidth, $maxheight);

					$thumbwidth = $scale['width'];
					$thumbheight = $scale['height'];
					if($this->gdversion() >= 2)
					{
						$thumbim = @imagecreatetruecolor($thumbwidth, $thumbheight);
						@imagecopyresampled($thumbim, $im, 0, 0, 0, 0, $thumbwidth, $thumbheight, $imgwidth,$imgheight);
					}
					else
					{
						$thumbim = @imagecreate($thumbwidth, $thumbheight);
						@imagecopyresized($thumbim, $im, 0, 0, 0, 0, $thumbwidth, $thumbheight, $imgwidth, $imgheight);
					}
					if(!function_exists("imagegif") && $imgtype == 1)
					{
						$didgifjpg = 1;
					}
					ob_start();
					switch($imgtype)
					{
						case 1:
							if(function_exists("imagegif"))
							{
								imageGIF($thumbim);
							}
							else
							{
								imageJPEG($thumbim);
							}
							break;
						case 2:
							imageJPEG($thumbim);
							break;
						case 3:
							imagePNG($thumbim);
							break;
					}
					$thumbnail = ob_get_contents();
					@imagedestroy($thumbim);
					@imagedestroy($im);
					ob_end_clean();
					$code = 1;
				}
			}
			else
			{
				$thumbnail = implode("", file($file));
				$code = 2;
			}
		}
		if($didgifjpg) // Fix/cheat because of the poor imagegif function
		{
			$ret['thumbnail'] = "JPG|".$thumbnail;
//			die($return['thumbnail']);
		}
		else
		{
			$ret['thumbnail'] = $thumbnail;
		}
		$ret['code'] = $code;
		return $ret;
	}

	/**
	 * Scales an image.
	 *
	 * @param int Image width.
	 * @param int Image height.
	 * @param int Image maxwidth.
	 * @param int Image maxheight.
	 * @return array Array containing widths and heights.
	 */
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
		if($imgheight > $maxheight)
		{
			$newheight = $maxheight;
			$newwidth = ceil(($width*(($maxheight*100)/$height))/100);
		}
		$ret['width'] = $newwidth;
		$ret['height'] = $newheight;
		return $ret;
	}
	
	/**
	 * Gets the GD version.
	 *
	 * @return float The GD version.
	 */
	function gdVersion()
	{
		if (!extension_loaded('gd'))
		{
			return;
		}
		ob_start();
		phpinfo(8);
		$info=ob_get_contents();
		ob_end_clean();
		$info=stristr($info, 'gd version');
		preg_match('/\d/', $info, $gd);
		return $gd[0];
	}

}
?>