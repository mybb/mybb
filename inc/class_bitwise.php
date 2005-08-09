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