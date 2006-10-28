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

/**
 * Convert an integer 1/0 into text yes/no
 * @param int Integer to be converted
 * @return string Correspondig yes or no
 */
function int_to_yesno($var)
{
	$var = intval($var);
	
	if($var == 1)
	{
		return 'yes';
	}
	else
	{
		return 'no';
	}
}

/**
 * Update the import session cache
 */
function update_session()
{
	global $session, $db;
	
	$session = $db->escape_string(serialize($session));
	$query = $db->simple_select("datacache", "*", "title='importcache'");
	$sess = $db->fetch_array($query);
	
	if(!$sess['cache'])
	{
		$insertarray = array(
			'title' => 'importcache',
			'cache' => $session,
		);
		$db->insert_query("datacache", $insertarray);
	} 
	else
	{
		$db->update_query("datacache", array('cache' => $session), "title='importcache'");
	}
}
?>