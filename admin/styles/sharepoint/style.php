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
	function generate_breadcrumb()
	{
		if(!is_array($this->breadcrumb_trail))
		{
			return false;
		}
		$trail = "";
		foreach($this->breadcrumb_trail as $key => $crumb)
		{
			if($this->breadcrumb_trail[$key+1])
			{
				$trail .= "<a href=\"".$crumb['url']."\">".$crumb['name']."</a>";
				if($this->breadcrumb_trail[$key+2])
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


	function output_nav_tabs($tabs=array(), $active='')
	{
		global $plugins;
		$tabs = $plugins->run_hooks("admin_page_output_nav_tabs_start", $tabs);
		if(count($tabs) > 1)
		{
			$first = true;
			echo "<div class=\"nav_tabs\">";
			echo "\t<ul>\n";
			foreach($tabs as $id => $tab)
			{
				if($id == $active)
				{
					continue;
				}
				$class = '';
				if($tab['link_target'])
				{
					$target = " target=\"{$tab['link_target']}\"";
				}
				if($first) $class .= " first";
				echo "\t\t<li class=\"{$class}\"><a href=\"{$tab['link']}\"{$target}>{$tab['title']}</a></li>\n";
				$first = false;
				$target = '';
			}
			echo "\t</ul>\n";
			echo "</div>";
		}

		if($tabs[$active])
		{
			$intro_tab = $tabs[$active];
			echo "<div class=\"intro\">";
			echo "<h2>{$intro_tab['title']}</h2>";
			echo "<p>{$intro_tab['description']}</p>";
			echo "</div>";
		}
		$arguments = array('tabs' => $tabs, 'active' => $active);
		$plugins->run_hooks("admin_page_output_nav_tabs_end", $arguments);
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
?>