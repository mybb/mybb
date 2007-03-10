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