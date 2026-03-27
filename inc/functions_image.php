<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

enum ThumbnailOptionsPosition: string
{
    case None = '';
    case Left = 'L';
    case TopLeft = 'TL';
    case BottomLeft = 'BL';
    case Right = 'R';
    case TopRight = 'TR';
    case BottomRight = 'BR';
    case Top = 'T';
    case Bottom = 'B';
    case Center = 'C';
}

/**
 * Generates a thumbnail based on specified dimensions (supports png, jpg, gif, bmp, webp, and avif)
 *
 * @param string $file the full path to the original image
 * @param string $path the directory path to where to save the new image
 * @param string $filename the filename to save the new image as
 * @param int $maxheight maximum height dimension
 * @param int $maxwidth maximum width dimension
 * @param int $quality quality of the thumbnail
 * @param bool $zoom_crop 0 = no zoom crop, 1 = zoom crop
 * @param ThumbnailOptionsPosition $fixed_aspect_ratio
 * @param string $background_color background color
 * @param ThumbnailOptionsPosition $alignment
 * @param int $rotate_angle 360 = no rotation
 * @param int $dots_per_inch 72 = default
 * @param bool $ignore_aspect_ratio false = no ignore, true = ignore
 * @param int $maximum_file_bytes 0 = no limit
 * @param array $filters
 * @return array thumbnail on success, error code 4 on failure
 */
function generate_thumbnail(
    $file,
    $path,
    $filename,
    $maxheight,
    $maxwidth,
    int $quality = 100,
    bool $zoom_crop = false,
    ThumbnailOptionsPosition $fixed_aspect_ratio = ThumbnailOptionsPosition::None,
    string $background_color = '',
    ThumbnailOptionsPosition $alignment = ThumbnailOptionsPosition::None,
    int $rotate_angle = 360,
    int $dots_per_inch = 72,
    bool $ignore_aspect_ratio = false,
    int $maximum_file_bytes = 0,
    array $filters = [
        //'lvl',
        //'wmt',
        //'blur',
        //'grayscale',
        //'negate',
        //'sepia',
        //'edge',
        //'edge',
        //'pixelate',
    ],
) {
    try {
        $phpThumb = new phpThumb();

        $phpThumb->setSourceFilename($file);

        $phpThumb->setParameter('h', $maxheight);

        $phpThumb->setParameter('w', $maxwidth);

        $phpThumb->setParameter('q', $quality);

        $phpThumb->setParameter('zc', $zoom_crop);

        $phpThumb->setParameter('far', $fixed_aspect_ratio);

        $phpThumb->setParameter('f', my_strtolower(my_substr(strrchr($filename, '.'), 1)));

        $phpThumb->setParameter('bg', $background_color);

        $phpThumb->setParameter('fltr', $background_color);

        $phpThumb->setParameter('a', $alignment);

        $phpThumb->setParameter('ra', $rotate_angle);

        $phpThumb->setParameter('bcc', $background_color);

        $phpThumb->setParameter('bc', $background_color);

        $phpThumb->setParameter('dpi', $dots_per_inch);

        $phpThumb->setParameter('iar', $ignore_aspect_ratio);

        $phpThumb->setParameter('maxb', $maximum_file_bytes);

        if ($filters) {
            $phpThumb->setParameter('fltr', $filters);
        }

        $phpThumb->GenerateThumbnail();

        if ($phpThumb->RenderToFile($path."/".$filename) !== false) {
            return [
                'code' => 1,
                'filename' => $filename,
            ];
        }
    } catch (Exception $e) {
    }

    return ['code' => 4];
}

/**
 * Attempts to allocate enough memory to generate the thumbnail
 *
 * @param integer $width width dimension
 * @param integer $height height dimension
 * @param string $type one of the IMAGETYPE_XXX constants indicating the type of the image
 * @param string $bitdepth the bits area the number of bits for each color
 * @param string $channels the channels - 3 for RGB pictures and 4 for CMYK pictures
 * @return bool
 *
 * @deprecated
 */
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

	$limit = preg_match("#^([0-9]+)\s?([kmg])b?$#i", trim(my_strtolower($memory_limit)), $matches);
	$memory_limit = (int)$memory_limit;
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
		if($matches[1] && $matches[2])
		{
			switch($matches[2])
			{
				case "k":
					$memory_limit = ceil((($memory_limit+$thumbnail_memory) / 1024))."K";
					break;
				case "m":
					$memory_limit = ceil((($memory_limit+$thumbnail_memory) / 1048576))."M";
					break;
				case "g":
					$memory_limit = ceil((($memory_limit+$thumbnail_memory) / 1073741824))."G";
			}
		}

		@ini_set("memory_limit", $memory_limit);
	}

	return true;
}

/**
 * Figures out the correct dimensions to use
 *
 * @param integer $width current width dimension
 * @param integer $height current height dimension
 * @param integer $maxwidth max width dimension
 * @param integer $maxheight max height dimension
 * @return array correct height & width
 */
function scale_image($width, $height, $maxwidth, $maxheight)
{
	$width = (int)$width;
	$height = (int)$height;

	if(!$width) $width = $maxwidth;
	if(!$height) $height = $maxheight;

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
