<?php
/**
 * This is an example style file for Admin CP styles.
 *
 * It allows you to override our existing layout generation
 * classes with your own to further customise the Admin CP
 * layout beyond CSS.
 *
 * Your class name      Should extend
 * ---------------      -------------
 * Page                 DefaultPage
 * SidebarItem          DefaultSidebarItem
 * PopupMenu            DefaultPopupMenu
 * Table                DefaultTable
 * Form                 DefaultForm
 * FormContainer        DefaultFormContainer
 *
 * For example, to output your own custom header:
 *
 * class Page extends DefaultPage
 * {
 *   function output_header($title)
 *   {
 *      echo "<h1>{$title}</h1>";
 *   }
 * }
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class Page extends DefaultPage
{
	function _generate_breadcrumb()
	{
		if(!is_array($this->_breadcrumb_trail))
		{
			return false;
		}
		$trail = "";
		foreach($this->_breadcrumb_trail as $key => $crumb)
		{
			if($this->_breadcrumb_trail[$key+1])
			{
				$trail .= "<a href=\"".$crumb['url']."\">".$crumb['name']."</a>";
				if($this->_breadcrumb_trail[$key+2])
				{
					$trail .= " &raquo; ";
				}
			}
			else
			{
				$trail .= " &raquo; <span class=\"active\">".$crumb['name']."</span>";
			}
		}
		return $trail;
	}
}

class SidebarItem extends DefaultSidebarItem
{
}

class PopupMenu extends DefaultPopupMenu
{
}

class Table extends DefaultTable
{
}

class Form extends DefaultForm
{
}

class FormContainer extends DefaultFormContainer
{
}
