<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

class DefaultPage
{
	var $style;

	var $menu = array();
	var $active_module;
	var $active_action;
	
	var $sidebar;

	var $breadcrumb_trail = array();
	
	var $extra_header = "";

	function output_header($title="MyBB Administration Panel")
	{
		global $mybb, $admin_session;
		
		echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
		echo "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n";
		echo "<head profile=\"http://gmpg.org/xfn/1\">\n";
		echo "	<title>".$title."</title>\n";
		echo "	<meta name=\"author\" content=\"MyBB Group\" />\n";
		echo "	<meta name=\"copyright\" content=\"Copyright ".date('Y')." MyBB Group.\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"styles/".$this->style."/main.css\" type=\"text/css\" />\n";

		// Load stylesheet for this module if it has one
		if(file_exists(MYBB_ADMIN_DIR."styles/{$this->style}/{$this->active_module}.css"))
		{
			echo "	<link rel=\"stylesheet\" href=\"styles/{$this->style}/{$this->active_module}.css\" type=\"text/css\" />\n";
		}

		echo "	<script type=\"text/javascript\" src=\"../jscripts/prototype.js\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"../jscripts/general.js\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"../jscripts/popup_menu.js\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"./jscripts/admincp.js\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"./jscripts/tabs.js\"></script>\n";
		echo $this->extra_header;
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
		echo "           <div id=\"inner\">\n";
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

	function output_footer($quit=true)
	{
		global $maintimer, $db;
		$totaltime = $maintimer->stop();
		$querycount = $db->query_count;
		echo "			</div>\n";
		echo "		</div>\n";
		echo "	<br style=\"clear: both;\" />";
		echo "	</div>\n";
		echo "<div id=\"footer\"><p class=\"generation\">Generated in {$totaltime} seconds with {$querycount} queries.</p><p class=\"powered\">Powered By MyBB. &copy; ".date("Y")." MyBB Group. All Rights Reserved.</p></div>\n";
		echo "</div>\n";
		echo "</body>\n";
		echo "</html>\n";
		
		if($quit != false)
		{
			exit;
		}
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
	
	function output_success($message)
	{
		echo "<div class=\"success\">".$message."</div>\n";
	}

	function output_alert($message)
	{
		echo "<div class=\"alert\">".$message."</div>\n";
	}
	
	function output_inline_message($message)
	{
		echo "<div class=\"inline_message\">".$message."</div>\n";
	}
	
	function output_error($error)
	{
		echo "<div class=\"error\">\n";
		echo "{$error}\n";
		echo "</div>\n";
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

	function show_login($message="Please enter your username and password to continue.", $class="success")
	{
print <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head profile="http://gmpg.org/xfn/1">
<title>MyBB Administration - Login</title>
<meta name="author" content="MyBB Group" />
<meta name="copyright" content="Copyright 2006 MyBB Group." />
<link rel="stylesheet" href="./styles/default/login.css" type="text/css" />
</head>
<body>
<div id="container">
	<div id="header">
		<div id="logo">
			<h1><a href="../" title="Return to forum"><span class="invisible">MyBB Admin CP</span></a></h1>

		</div>
	</div>
	<div id="content">
		<h2>Please Login</h2>
					<p id="message" class="{$class}"><span class="text">{$message}</span><br />Please enter your username and password to continue.</p>
		
		<form method="post" action="{$_SERVER['PHP_SELF']}">
		<div class="form_container">

			<div class="label"><label for="username">Username:</label></div>

			<div class="field"><input type="text" name="username" id="username" class="text_input" /></div>

			<div class="label"><label for="password">Password:</label></div>
			<div class="field"><input type="password" name="password" id="password" class="text_input" /></div>
		</div>
		<p class="submit">
			<input type="submit" value="Login" />

			<input type="hidden" name="do" value="login" />
		</p>

		</form>
	</div>
</div>
</body>
</html>
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
	
	function output_tab_control($tabs=array())
	{
		echo "<script type=\"text/javascript\">\n";
		echo "Event.observe(window,'load',function(){\n";
		echo "	\$\$('.tabs').each(function(tabs)\n";
		echo "	{\n";
		echo "		new Control.Tabs(tabs);\n";
		echo "	});\n";
		echo "});\n";
		echo "</script>\n";
		echo "<ul class=\"tabs\">\n";
		foreach($tabs as $anchor => $title)
		{
			echo "<li><a href=\"#tab_{$anchor}\">{$title}</a></li>\n";
		}
		echo "</ul>\n";
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
			if($tab['link_target'])
			{
				$target = " target=\"{$tab['link_target']}\"";
			}
			echo "\t\t<li class=\"{$class}\"><a href=\"{$tab['link']}\"{$target}>{$tab['title']}</a></li>\n";
			$target = '';
		}
		echo "\t</ul>\n";
		if($tabs[$active]['description'])
		{
			echo "\t<div class=\"tab_description\">{$tabs[$active]['description']}</div>\n";
		}
		echo "</div>";
	}

	function output_confirm_action($url, $message='')
	{
		global $lang;
		if(!$message)
		{
			$message = 'Are you sure you wish to perform this action?';
		}
		$this->output_header();
		$form = new Form($url, 'post');
		echo "<div class=\"confirm_action\">\n";
		echo "<p>{$message}</p>\n";
		echo "<br />\n";
		echo "<p class=\"buttons\">\n";
		echo $form->generate_submit_button("Yes", array('class' => 'button_yes'));
		echo $form->generate_submit_button("No", array("name" => "no", 'class' => 'button_no'));
		echo "</p>\n";
		echo "</div>\n";
		$form->end();
		$this->output_footer();
	}
	
}

class DefaultSidebarItem
{
	var $title;
	var $menu_contents;
	
	function DefaultSidebarItem($title="")
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

class DefaultPopupMenu
{
	var $title;
	var $id;
	var $items;

	function DefaultPopupMenu($id, $title='')
	{
		$this->id = $id;
		$this->title = $title;
	}

	function add_item($text, $link, $onclick='')
	{
		if($onclick)
		{
			$onclick = " onclick=\"{$onclick}\"";
		}
		$this->items .= "<div class=\"popup_item_container\"><a href=\"{$link}\"{$onclick} class=\"popup_item\">{$text}</a></div>\n";
	}

	function fetch()
	{
		$popup = "<div class=\"popup_menu\" id=\"{$this->id}_popup\">\n{$this->items}</div>\n";
		$popup .= "<script type=\"text/javascript\">\n";
		if($this->title)
		{
			$popup .= "document.write('<a href=\"javascript:;\" id=\"{$this->id}\" class=\"popup_button\">{$this->title}<\/a>');\n";
		}
		$popup .= "new PopupMenu('{$this->id}');\n";
		$popup .= "</script>\n";
		return $popup;
	}

	function output()
	{
		echo $this->fetch();
	}
}
?>