<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: class_bitwise.php 5016 2010-06-12 00:24:02Z RyanGordon $
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