<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: functions_compat.php 5297 2010-12-28 22:01:14Z Tomm $
 */

/**
 * Below are compatibility functions which replicate functions in newer versions of PHP.
 *
 * This allows MyBB to continue working on older installations of PHP 5.1 and above without these functions.
 */

if(!function_exists('memory_get_peak_usage'))
{
	function memory_get_peak_usage($real_usage=false)
	{
		return memory_get_usage($real_usage);
	}
}

?>