<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: class_bitwise.php 5297 2010-12-28 22:01:14Z Tomm $
 */

class bitwise
{
	function set($bits, $bit)
	{
		$bits |= $bit;
		return $bits;
	}

	function remove($bits, $bit)
	{
		$bits &= ~$bit;
		return $bits;
	}

	function toggle($bits, $bit)
	{
		$bits ^= $bit;
		return $bits;
	}
}
?>