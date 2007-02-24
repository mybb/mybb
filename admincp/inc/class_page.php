<?php
class Page
{
	var $style;

	var $menu = array();
	var $active_module;
	var $active_action;
	
	var $sidebar;

	var $breadcrumb_trail = array();

	function output_header($title="MyBB Administration Panel")
	{
		global $mybb, $admin_session;
		echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
		echo "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n";
		echo "<head profile=\"http://gmpg.org/xfn/1\">\n";
		echo "	<title>".$title."</title>\n";
		echo "	<meta name=\"author\" content=\"MyBB Group\" />\n";
		echo "	<meta name=\"copyright\" content=\"Copyright 2005 MyBB Group.\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"styles/".$this->style."/main.css\" type=\"text/css\" />\n";

		// Load stylesheet for this module if it has one
		if(file_exists(MYBB_ADMIN_DIR."styles/{$this->style}/{$this->active_module}.css"))
		{
				echo "	<link rel=\"stylesheet\" href=\"styles/{$this->style}/{$this->active_module}.css\" type=\"text/css\" />\n";
		}

		echo "</head>\n";
		echo "<body>\n";
		echo "<div id=\"container\">\n";
		echo "	<div id=\"logo\"><h1><span class=\"invisible\">MyBB Admin CP</span></h1></div>\n";
		echo "	<div id=\"welcome\"><span class=\"logged_in_as\">Logged in as <a href=\"#\" class=\"username\">{$mybb->user['username']}</a></span> | <a href=\"index.php?".SID."&amp;action=logout\" class=\"logout\">Logout</a></div>\n";
		echo $this->build_menu();
		echo "	<div id=\"page\">\n";
		echo "		<div id=\"left_menu\">\n";
		echo $this->sidebar;
		echo "		</div>\n";
		echo "		<div id=\"content\">\n";
		echo "			<div class=\"breadcrumb\">\n";
		echo $this->generate_breadcrumb();
		echo "			</div>\n";
		if($admin_session['data']['flash_message'])
		{
			$message = $admin_session['data']['flash_message']['message'];
			$type = $admin_session['data']['flash_message']['type'];
			echo "<div id=\"flash_message\" class=\"{$type}\">\n";
			echo "{$message}\n";
			echo "</div>\n";
			update_admin_session('flash_message', '');
		}
	}

	function output_footer()
	{
		global $maintimer, $db;
		$totaltime = $maintimer->stop();
		$querycount = $db->query_count;
		echo "		</div>\n";
		echo "	<br style=\"clear: both;\" />";
		echo "	</div>\n";
		echo "<div id=\"footer\"><p class=\"generation\">Generated in {$totaltime} seconds with {$querycount} queries.</p><p class=\"powered\">Powered By MyBB. &copy; ".date("Y")." MyBB Group. All Rights Reserved.</p></div>\n";
		echo "</div>\n";
		echo "</body>\n";
		echo "</html>\n";
	}
	
	function add_breadcrumb_item($name, $url="")
	{
		$this->breadcrumb_trail[] = array("name" => $name, "url" => $url);
	}
	
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
				$trail .= "<span class=\"active\">".$crumb['name']."</span>";
			}
		}
		return $trail;
	}	
	
	function output_intro($title, $description, $class="")
	{
		echo "		<div class=\"intro_description\">\n";
		echo "	<div class=\"{$class}\">\n";
		echo "	<h2>{$title}</h2>\n";
		echo "	<p>{$description}</p>\n";
		echo "</div>\n";
		echo "</div>\n";	
	}

	function output_alert($message)
	{
		echo "<div class=\"alert\">".$message."</div>\n";
	}
	
	function output_inline_message($message)
	{
		echo "<div class=\"inline_message\">".$message."</div>\n";
	}

	function output_inline_error($errors)
	{
		if(!is_array($errors))
		{
			$errors = array($errors);
		}
		echo "<div class=\"error\">\n";
		echo "<p><em>The following errors were encountered:</em></p>\n";
		echo "<ul>\n";
		foreach($errors as $error)
		{
			echo "<li>{$error}</li>\n";
		}
		echo "</ul>\n";
		echo "</div>\n";
	}

	function show_login($message="Please enter your username and password to continue")
	{
print <<<EOF
<form method="post" action="{$_SERVER['PHP_SELF']}">
<strong>{$message}</strong>
Username: <input type="text" name="username" /><br />
Password: <input type="password" name="password" /><br />
<input type="submit" value="Login" />
<input type="hidden" name="do" value="login" />
</form>
EOF;
	exit;
	}

	function add_menu_item($title, $id, $link, $order=10)
	{
		$this->menu[$order][] = array(
			"title" => $title,
			"id" => $id,
			"link" => $link
		);
	}

	function build_menu()
	{
		if(!is_array($this->menu))
		{
			return false;
		}
		$build_menu = "<div id=\"menu\">\n<ul>\n";
		ksort($this->menu);
		foreach($this->menu as $items)
		{
			foreach($items as $menu_item)
			{
				$menu_item['link'] = htmlspecialchars($menu_item['link']);
				if($menu_item['id'] == $this->active_module)
				{
					$build_menu .= "<li><a href=\"".$menu_item['link']."\" class=\"active\">".$menu_item['title']."</a></li>\n";
				}
				else
				{
					$build_menu .= "<li><a href=\"".$menu_item['link']."\">".$menu_item['title']."</a></li>\n";
				}
			}
		}
		$build_menu .= "</ul>\n</div>";
		return $build_menu;
	}
	
	function get_alt_bg()
	{
		static $alt_bg;
		if($alt_bg == "alt1")
		{
			$alt_bg = "alt2";
			return "alt1";
		}
		else
		{
			$alt_bg = "alt1";
			return $alt_bg;
		}
	}
	
	function output_tab_control($name, $tabs=array())
	{
		echo "<script type=\"text/javascript\">\n";
		echo "  {$name} = new TabControl();\n";
		foreach($tabs as $tab => $title)
		{
			echo "  {$name}.register('{$tab}', '{$title}');\n";			
		}
		echo "</script>\n";
	}

	function output_nav_tabs($tabs=array(), $active='')
	{
		echo "<div class=\"nav_tabs\">";
		echo "\t<ul>\n";
		foreach($tabs as $id => $tab)
		{
			$class = '';
			if($id == $active)
			{
				$class = ' active';
			}
			if($tab['align'] == "right")
			{
				$class .= " right";
			}
			echo "\t\t<li class=\"{$class}\"><a href=\"{$tab['link']}\">{$tab['title']}</a></li>\n";
		}
		echo "\t</ul>\n";
		if($tabs[$active]['description'])
		{
			echo "\t<div class=\"tab_description\">{$tabs[$active]['description']}</div>\n";
		}
		else
		{
			echo "<br style=\"clear: both;\" />\n";
		}
		echo "</div><br style=\"clear: left;\" />";
	}
}

class sideBarItem
{
	var $title;
	var $menu_contents;
	
	function sideBarItem($title="")
	{
		$this->title = $title;
	}
	
	function add_menu_items($items, $active)
	{
		foreach($items as $item)
		{
			$class = "";
			if($item['id'] == $active)
			{
				$class = "active";
			}
			$item['link'] = htmlspecialchars($item['link']);
			$this->menu_contents .= "<li class=\"{$class}\"><a href=\"{$item['link']}\">{$item['title']}</a></li>\n";
		}
	}
	
	function get_markup()
	{
		$markup = "<div class=\"left_menu_box\">\n";
		$markup .= "<div class=\"title\">{$this->title}</div>\n";
		if($this->menu_contents)
		{
			$markup .= "<ul class=\"menu\">\n";
			$markup .= $this->menu_contents;
			$markup .= "</ul>\n";
		}
		$markup .= "</div>\n";
		return $markup;
	}
}
?>