<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id:$
 */

/**
 * Forum handling class, provides common structure to handle forum data.
 *
 */
class ForumDataHandler extends DataHandler
{

	/**
	* Verifies a forum name.
	*
	* @return boolean True if valid, false if invalid.
	*/
	function verify_name()
	{
		$forum = &$this->data;

		if(trim($forum['name']) == "")
		{
			$this->set_error("no_forum_name");
			return false;
		}

		return true;
	}

	/**
	* Verifies a forum type.
	*
	* @return boolean True if valid, false if invalid.
	*/
	function verify_type()
	{
		$forum = &$this->data;

		// Trim the link for redirect forums.
		$forum['linkto'] = trim($forum['linkto']);

		// Check this is a valid type of forum.
		if($forum['type'] != "f" && $forum['type'] != "c")
		{
			$forum['type'] = "f";
		}

		return true;
	}

	/**
	* Verifies a forum parent.
	*
	* @return boolean True if valid, false if invalid.
	*/
	function verify_parent()
	{
		$forum = &$this->data;

		// Check if the parent forum actually exists.
		$parentlist = get_forum($forum['fid']);
		$parentlist = explode("," $parentlist);
		if(!in_array($forum['pid'], $parentlist))
		{
			$this->set_error("invalid_parent_forum");
			return false;
		}

		return true;
	}

	/**
	* Verifies a forum's options.
	*
	* @return boolean True if valid, false if invalid.
	*/
	function verify_options()
	{
		$forum = &$this->data;

		// Clean all the forum options.
		if($forum['options']['allowhtml'] != "yes")
		{
			$forum['options']['allowhtml'] = "no";
		}
		if($forum['options']['allowmycode'] != "yes")
		{
			$forum['options']['allowmycode'] = "no";
		}
		if($forum['options']['allowsmilies'] != "yes")
		{
			$forum['options']['allowsmilies'] = "no";
		}
		if($forum['options']['allowimgcode'] != "yes")
		{
			$forum['options']['allowimgcode'] = "no";
		}
		if($forum['options']['allowpicon'] != "yes")
		{
			$forum['options']['allowpicon'] = "no";
		}
		if($forum['options']['allowtratings'] != "yes")
		{
			$forum['options']['allowtratings'] = "no";
		}
		if($forum['options']['usepostcounts'] != "yes")
		{
			$forum['options']['usepostcounts'] = "no";
		}
		if($forum['options']['modposts'] != "yes")
		{
			$forum['options']['modposts'] = "no";
		}
		if($forum['options']['modthreads'] != "yes")
		{
			$forum['options']['modthreads'] = "no";
		}
		if($forum['options']['modattachments'] != "yes")
		{
			$forum['options']['modattachments'] = "no";
		}
		if($forum['options']['showinjump'] != "yes")
		{
			$forum['options']['showinjump'] = "no";
		}
	}


	/**
	* Validates a forum.
	*/
	function validate_forum()
	{
		// Verify all forum assets.
		$this->verify_name();
		$this->verify_linkto();
		$this->verify_parent();
		$this->verify_options();

		// We are done validating, return.
		$this->set_validated(true);
		if(count($this->get_errors()) > 0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Inserts a forum.
	*/
	function insert_forum()
	{
		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The forum needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The forum is not valid.");
		}

		$forum = &$this->data;
	}

	/**
	* Updates a forum.
	*/
	function update_forum()
	{
		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The forum needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The forum is not valid.");
		}

		$forum = &$this->data;
	}
}
?>