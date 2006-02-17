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

/**
 * Thread handling class, provides common structure to handle thread data.
 *
 */
class ThreadDataHandler extends Handler
{
	/**
	 * Insert a thread into the database.
	 *
	 * @param array The thread data array.
	 */
	function insert_thread($thread)
	{
		global $db;
		
		if($this->get_validated !== true)
		{
			die("The thread needs to be validated before inserting it into the DB.");
		}
		if(!empty($this->get_errors()))
		{
			die("The thread is not valid.");
		}

		$db->insert_query(TABLE_PREFIX."threads", $thread);
		
		// Update thread count for forum the thread is in.
		updateforumcount($thread['fid']);
		
	}
}

?>