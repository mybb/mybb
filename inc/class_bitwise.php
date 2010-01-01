<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: class_bitwise.php 4304 2009-01-02 01:11:56Z chris $
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