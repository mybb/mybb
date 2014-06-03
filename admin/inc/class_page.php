<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/*
 * MyBB Admin CP Page Generation Class
 */
class DefaultPage
{

	/**
	 * @var string The current style in use.
	 */
	public $style;

	/**
	 * @var array The primary menu items.
	 */
	public $menu = array();

	/**
	 * @var string The side bar menu items.
	 */
	public $submenu = '';

	/**
	 * @var string The module we're currently in.
	 */
	public $active_module;

	/**
	 * @var string The action we're currently performing.
	 */
	public $active_action;

	/**
	 * @var string Content for the side bar of the page if we have one.
	 */
	public $sidebar;

	/**
	 * @var array The breadcrumb trail leading up to this page.
	 */
	public $_breadcrumb_trail = array();

	/**
	 * @var string Any additional information to add between the <head> tags.
	 */
	public $extra_header = "";

	/**
	 * @var string Show a post verify error
	 */
	public $show_post_verify_error = '';

	/**
	 * Output the page header.
	 *
	 * @param string The title of the page.
	 */
	function output_header($title="")
	{
		global $mybb, $admin_session, $lang, $plugins;

		$plugins->run_hooks("admin_page_output_header");

		if(!$title)
		{
			$title = $lang->mybb_admin_panel;
		}

		$rtl = "";
		if($lang->settings['rtl'] == 1)
		{
			$rtl = " dir=\"rtl\"";
		}

		echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
		echo "<html xmlns=\"http://www.w3.org/1999/xhtml\"{$rtl}>\n";
		echo "<head profile=\"http://gmpg.org/xfn/1\">\n";
		echo "	<title>".$title."</title>\n";
		echo "	<meta name=\"author\" content=\"MyBB Group\" />\n";
		echo "	<meta name=\"copyright\" content=\"Copyright ".COPY_YEAR." MyBB Group.\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"styles/".$this->style."/main.css\" type=\"text/css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"styles/".$this->style."/modal.css\" type=\"text/css\" />\n";

		// Load stylesheet for this module if it has one
		if(file_exists(MYBB_ADMIN_DIR."styles/{$this->style}/{$this->active_module}.css"))
		{
			echo "	<link rel=\"stylesheet\" href=\"styles/{$this->style}/{$this->active_module}.css\" type=\"text/css\" />\n";
		}

		echo "	<script type=\"text/javascript\" src=\"../jscripts/jquery.js\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"../jscripts/jquery.plugins.min.js\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"../jscripts/general.js\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"./jscripts/admincp.js\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"./jscripts/tabs.js\"></script>\n";
		
		echo "	<link rel=\"stylesheet\" href=\"jscripts/jqueryui/css/redmond/jquery-ui-1.10.4.custom.min.css\" />\n";
		echo "	<script src=\"jscripts/jqueryui/js/jquery-ui-1.10.4.custom.min.js\"></script>\n";

		// Stop JS elements showing while page is loading (JS supported browsers only)
		echo "  <style type=\"text/css\">.popup_button { display: none; } </style>\n";
		echo "  <script type=\"text/javascript\">\n".
				"//<![CDATA[\n".
				"	document.write('<style type=\"text/css\">.popup_button { display: inline; } .popup_menu { display: none; }<\/style>');\n".
                "//]]>\n".
                "</script>\n";

		echo "	<script type=\"text/javascript\">
//<![CDATA[
var loading_text = '{$lang->loading_text}';
var cookieDomain = '{$mybb->settings['cookiedomain']}';
var cookiePath = '{$mybb->settings['cookiepath']}';
var cookiePrefix = '{$mybb->settings['cookieprefix']}';
var imagepath = '../images';

lang.unknown_error = \"{$lang->unknown_error}\";
lang.saved = \"{$lang->saved}\";
//]]>
</script>\n";
		echo $this->extra_header;
		echo "</head>\n";
		echo "<body>\n";
		echo "<div id=\"container\">\n";
		echo "	<div id=\"logo\"><h1><span class=\"invisible\">{$lang->mybb_admin_cp}</span></h1></div>\n";
		echo "	<div id=\"welcome\"><span class=\"logged_in_as\">{$lang->logged_in_as} <a href=\"index.php?module=user-users&amp;action=edit&amp;uid={$mybb->user['uid']}\" class=\"username\">{$mybb->user['username']}</a></span> | <a href=\"{$mybb->settings['bburl']}\" target=\"_blank\" class=\"forum\">{$lang->view_board}</a> | <a href=\"index.php?action=logout&amp;my_post_key={$mybb->post_code}\" class=\"logout\">{$lang->logout}</a></div>\n";
		echo $this->_build_menu();
		echo "	<div id=\"page\">\n";
		echo "		<div id=\"left_menu\">\n";
		echo $this->submenu;
		echo $this->sidebar;
		echo "		</div>\n";
		echo "		<div id=\"content\">\n";
		echo "			<div class=\"breadcrumb\">\n";
		echo $this->_generate_breadcrumb();
		echo "			</div>\n";
		echo "           <div id=\"inner\">\n";
		if(isset($admin_session['data']['flash_message']) && $admin_session['data']['flash_message'])
		{
			$message = $admin_session['data']['flash_message']['message'];
			$type = $admin_session['data']['flash_message']['type'];
			echo "<div id=\"flash_message\" class=\"{$type}\">\n";
			echo "{$message}\n";
			echo "</div>\n";
			update_admin_session('flash_message', '');
		}
		if($this->show_post_verify_error == true)
		{
			$this->output_error($lang->invalid_post_verify_key);
		}
	}

	/**
	 * Output the page footer.
	 */
	function output_footer($quit=true)
	{
		global $mybb, $maintimer, $db, $lang, $plugins;

		$plugins->run_hooks("admin_page_output_footer");

		$memory_usage = get_friendly_size(get_memory_usage());

		$totaltime = format_time_duration($maintimer->stop());
		$querycount = $db->query_count;

		if(my_strpos(getenv("REQUEST_URI"), "?"))
		{
			$debuglink = htmlspecialchars_uni(getenv("REQUEST_URI")) . "&amp;debug=1#footer";
		}
		else
		{
			$debuglink = htmlspecialchars_uni(getenv("REQUEST_URI")) . "?debug=1#footer";
		}

		echo "			</div>\n";
		echo "		</div>\n";
		echo "	<br style=\"clear: both;\" />";
		echo "	<br style=\"clear: both;\" />";
		echo "	</div>\n";
		echo "<div id=\"footer\"><p class=\"generation\">".$lang->sprintf($lang->generated_in, $totaltime, $debuglink, $querycount, $memory_usage)."</p><p class=\"powered\">Powered By <a href=\"http://www.mybb.com/\" target=\"_blank\">MyBB</a>, &copy; 2002-".COPY_YEAR." <a href=\"http://www.mybb.com/\" target=\"_blank\">MyBB Group</a>.</p></div>\n";
		if($mybb->debug_mode)
		{
			echo $db->explain;
		}
		echo "</div>\n";
		echo "</body>\n";
		echo "</html>\n";

		if($quit != false)
		{
			exit;
		}
	}

	/**
	 * Add an item to the page breadcrumb trail.
	 *
	 * @param string The name of the item to add.
	 * @param string The URL to the item we're adding (if there is one)
	 */
	function add_breadcrumb_item($name, $url="")
	{
		$this->_breadcrumb_trail[] = array("name" => $name, "url" => $url);
	}

	/**
	 * Generate a breadcrumb trail.
	 */
	function _generate_breadcrumb()
	{
		if(!is_array($this->_breadcrumb_trail))
		{
			return false;
		}
		$trail = "";
		foreach($this->_breadcrumb_trail as $key => $crumb)
		{
			if(isset($this->_breadcrumb_trail[$key+1]))
			{
				$trail .= "<a href=\"".$crumb['url']."\">".$crumb['name']."</a>";
				if(isset($this->_breadcrumb_trail[$key+2]))
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

	/**
	 * Output a success message.
	 *
	 * @param string The message to output.
	 */
	function output_success($message)
	{
		echo "<div class=\"success\">{$message}</div>\n";
	}

	/**
	 * Output an alert/warning message.
	 *
	 * @param string The message to output.
	 * @param string The ID of the alert/warning (optional)
	 */
	function output_alert($message, $id="")
	{
		if($id)
		{
			$id = " id=\"{$id}\"";
		}
		echo "<div class=\"alert\"{$id}>{$message}</div>\n";
	}

	/**
	 * Output an inline message.
	 *
	 * @param string The message to output.
	 */
	function output_inline_message($message)
	{
		echo "<div class=\"inline_message\">{$message}</div>\n";
	}

	/**
	 * Output a single error message.
	 *
	 * @param string The message to output.
	 */
	function output_error($error)
	{
		echo "<div class=\"error\">\n";
		echo "{$error}\n";
		echo "</div>\n";
	}

	/**
	 * Output one or more inline error messages.
	 *
	 * @param array Array of error messages to output.
	 */
	function output_inline_error($errors)
	{
		global $lang;

		if(!is_array($errors))
		{
			$errors = array($errors);
		}
		echo "<div class=\"error\">\n";
		echo "<p><em>{$lang->encountered_errors}</em></p>\n";
		echo "<ul>\n";
		foreach($errors as $error)
		{
			echo "<li>{$error}</li>\n";
		}
		echo "</ul>\n";
		echo "</div>\n";
	}


	/**
	 * Generate the login page.
	 *
	 * @param string The any message to output on the page if there is one.
	 * @param string The class name of the message (defaults to success)
	 */
	function show_login($message="", $class="success")
	{
		global $lang, $cp_style, $mybb;

		$copy_year = COPY_YEAR;

		$login_container_width = "";
		$login_label_width = "";

		// If the language string for "Username" is too cramped then use this to define how much larger you want the gap to be (in px)
		if(isset($lang->login_field_width))
        {
        	$login_label_width = " style=\"width: ".(intval($lang->login_field_width)+100)."px;\"";
			$login_container_width = " style=\"width: ".(410+(intval($lang->login_field_width)))."px;\"";
        }

		print <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head profile="http://gmpg.org/xfn/1">
<title>{$lang->mybb_admin_login}</title>
<meta name="author" content="MyBB Group" />
<meta name="copyright" content="Copyright {$copy_year} MyBB Group." />
<link rel="stylesheet" href="./styles/{$cp_style}/login.css" type="text/css" />
<script type="text/javascript" src="../jscripts/jquery.js"></script>
<script type="text/javascript" src="../jscripts/general.js"></script>
<script type="text/javascript" src="./jscripts/admincp.js"></script>
<script type="text/javascript">
//<![CDATA[
	loading_text = '{$lang->loading_text}';
//]]>
</script>
</head>
<body>
<div id="container"{$login_container_width}>
	<div id="header">
		<div id="logo">
			<h1><a href="../" title="{$lang->return_to_forum}"><span class="invisible">{$lang->mybb_acp}</span></a></h1>

		</div>
	</div>
	<div id="content">
		<h2>{$lang->please_login}</h2>
EOF;
		if($message)
		{
			echo "<p id=\"message\" class=\"{$class}\"><span class=\"text\">{$message}</span></p>";
		}
		// Make query string nice and pretty so that user can go to his/her preferred destination
		$query_string = '';
		if($_SERVER['QUERY_STRING'])
		{
			$query_string = '?'.preg_replace('#adminsid=(.{32})#i', '', $_SERVER['QUERY_STRING']);
			$query_string = preg_replace('#my_post_key=(.{32})#i', '', $query_string);
			$query_string = str_replace('action=logout', '', $query_string);
			$query_string = preg_replace('#&+#', '&', $query_string);
			$query_string = str_replace('?&', '?', $query_string);
			$query_string = htmlspecialchars_uni($query_string);
		}
		switch($mybb->settings['username_method'])
		{
			case 0:
				$lang_username = $lang->username;
				break;
			case 1:
				$lang_username = $lang->username1;
				break;
			case 2:
				$lang_username = $lang->username2;
				break;
			default:
				$lang_username = $lang->username;
				break;
		}

		// Secret PIN
		global $config;
		if(isset($config['secret_pin']) && $config['secret_pin'] != '')
		{
			$secret_pin = "<div class=\"label\"{$login_label_width}><label for=\"pin\">{$lang->secret_pin}</label></div>
            <div class=\"field\"><input type=\"password\" name=\"pin\" id=\"pin\" class=\"text_input\" /></div>";
		}
		else
		{
			$secret_pin = '';
		}

       	$_SERVER['PHP_SELF'] = htmlspecialchars_uni($_SERVER['PHP_SELF']);
print <<<EOF
		<p>{$lang->enter_username_and_password}</p>
		<form method="post" action="{$_SERVER['PHP_SELF']}{$query_string}">
		<div class="form_container">

			<div class="label"{$login_label_width}><label for="username">{$lang_username}</label></div>

			<div class="field"><input type="text" name="username" id="username" class="text_input initial_focus" /></div>

			<div class="label"{$login_label_width}><label for="password">{$lang->password}</label></div>
			<div class="field"><input type="password" name="password" id="password" class="text_input" /></div>

            {$secret_pin}
		</div>
		<p class="submit">
			<span class="forgot_password">
				<a href="../member.php?action=lostpw">{$lang->lost_password}</a>
			</span>

			<input type="submit" value="{$lang->login}" />
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

	/**
	 * Generate the lockout page
	 *
	 */
	function show_lockedout()
	{
		global $lang, $mybb, $cp_style;

		$copy_year = COPY_YEAR;
		$allowed_attempts = intval($mybb->settings['maxloginattempts']);
		$lockedout_message = $lang->sprintf($lang->error_mybb_admin_lockedout_message, $allowed_attempts);

		print <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head profile="http://gmpg.org/xfn/1">
<title>{$lang->mybb_admin_cp} - {$lang->error_mybb_admin_lockedout}</title>
<meta name="author" content="MyBB Group" />
<meta name="copyright" content="Copyright {$copy_year} MyBB Group." />
<link rel="stylesheet" href="./styles/{$cp_style}/login.css" type="text/css" />
</head>
<body>
<div id="container">
	<div id="header">
		<div id="logo">
			<h1><a href="../" title="{$lang->return_to_forum}"><span class="invisible">{$lang->mybb_acp}</span></a></h1>

		</div>
	</div>
	<div id="content">
		<h2>{$lang->error_mybb_admin_lockedout}</h2>
		<div class="alert">{$lockedout_message}</div>
	</div>
</div>
</body>
</html>
EOF;
	exit;
	}

	/**
	 * Generate the lockout unlock page
	 *
	 * @param string The any message to output on the page if there is one.
	 * @param string The class name of the message (defaults to success)
	 */
	function show_lockout_unlock($message="", $class="success")
	{
		global $lang, $mybb, $cp_style;

		$copy_year = COPY_YEAR;
		switch($mybb->settings['username_method'])
		{
			case 0:
				$lang_username = $lang->username;
				break;
			case 1:
				$lang_username = $lang->username1;
				break;
			case 2:
				$lang_username = $lang->username2;
				break;
			default:
				$lang_username = $lang->username;
				break;
		}

		if($message)
		{
			$message = "<p id=\"message\" class=\"{$class}\"><span class=\"text\">{$message}</span></p>";
		}

		print <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head profile="http://gmpg.org/xfn/1">
<title>{$lang->mybb_admin_cp} - {$lang->lockout_unlock}</title>
<meta name="author" content="MyBB Group" />
<meta name="copyright" content="Copyright {$copy_year} MyBB Group." />
<link rel="stylesheet" href="./styles/{$cp_style}/login.css" type="text/css" />
</head>
<body>
<div id="container">
	<div id="header">
		<div id="logo">
			<h1><a href="../" title="{$lang->return_to_forum}"><span class="invisible">{$lang->mybb_acp}</span></a></h1>

		</div>
	</div>
	<div id="content">
		<h2>{$lang->lockout_unlock}</h2>
		{$message}
		<p>{$lang->enter_username_and_token}</p>
		<form method="post" action="index.php">
		<div class="form_container">

			<div class="label"{$login_label_width}><label for="username">{$lang_username}</label></div>

			<div class="field"><input type="text" name="username" id="username" class="text_input initial_focus" /></div>

			<div class="label"{$login_label_width}><label for="token">{$lang->unlock_token}</label></div>
			<div class="field"><input type="text" name="token" id="token" class="text_input" /></div>
		</div>
		<p class="submit">
			<span class="forgot_password">
				<a href="../member.php?action=lostpw">{$lang->lost_password}</a>
			</span>

			<input type="submit" value="{$lang->unlock_account}" />
			<input type="hidden" name="action" value="unlock" />
		</p>
		</form>
	</div>
</div>
</body>
</html>
EOF;
	exit;
	}

	/**
	 * Add an item to the primary navigation menu.
	 *
	 * @param string The title of the menu item.
	 * @param string The ID of the menu item. This should correspond with the module the menu will run.
	 * @param string The link to follow when the menu item is clicked.
	 * @param int The display order of the menu item. Lower display order means closer to start of the menu.
	 * @param array Array of sub menu items if there are any.
	 */
	function add_menu_item($title, $id, $link, $order=10, $submenu=array())
	{
		$this->_menu[$order][] = array(
			"title" => $title,
			"id" => $id,
			"link" => $link,
			"submenu" => $submenu
		);
	}

	/**
	 * Build the actual navigation menu.
	 */
	function _build_menu()
	{
		if(!is_array($this->_menu))
		{
			return false;
		}
		$build_menu = "<div id=\"menu\">\n<ul>\n";
		ksort($this->_menu);
		foreach($this->_menu as $items)
		{
			foreach($items as $menu_item)
			{
				$menu_item['link'] = htmlspecialchars_uni($menu_item['link']);
				if($menu_item['id'] == $this->active_module)
				{
					$sub_menu = $menu_item['submenu'];
					$sub_menu_title = $menu_item['title'];
					$build_menu .= "<li><a href=\"{$menu_item['link']}\" class=\"active\">{$menu_item['title']}</a></li>\n";

				}
				else
				{
					$build_menu .= "<li><a href=\"{$menu_item['link']}\">{$menu_item['title']}</a></li>\n";
				}
			}
		}
		$build_menu .= "</ul>\n</div>";

		if($sub_menu)
		{
			$this->_build_submenu($sub_menu_title, $sub_menu);
		}
		return $build_menu;
	}


	/**
	 * Build a navigation sub menu if we have one.
	 *
	 * @param string A title for the sub menu.
	 * @param array Array of items for the sub menu.
	 */
	function _build_submenu($title, $items)
	{
		if(is_array($items))
		{
			$sidebar = new sideBarItem($title);
			$sidebar->add_menu_items($items, $this->active_action);
			$this->submenu .= $sidebar->get_markup();
		}
	}

	/**
	 * Switch between two different alternating background colours.
	 */
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

	/**
	 * Output a Javascript based tab control on to the page.
	 *
	 * @param array Array of tabs in name => title format. Name should correspond to the name of a DIV containing the tab content.
	 * @param boolean Whether or not to run the event onload or instantly
	 * @param string The ID to use for the tabs for if you run multiple instances of the tabbing control in one html page
	 */
	function output_tab_control($tabs=array(), $observe_onload=true, $id="tabs")
	{
		global $plugins;
		$tabs = $plugins->run_hooks("admin_page_output_tab_control_start", $tabs);
		echo "<ul class=\"tabs\" id=\"{$id}\">\n";
		$tab_count = count($tabs);
		$done = 1;
		foreach($tabs as $anchor => $title)
		{
			$class = "";
			if($tab_count == $done)
			{
				$class .= " last";
			}
			if($done == 1)
			{
				$class .= " first";
			}
			++$done;
			echo "<li class=\"{$class}\"><a href=\"#tab_{$anchor}\">{$title}</a></li>\n";
		}
		echo "</ul>\n";
		$plugins->run_hooks("admin_page_output_tab_control_end", $tabs);
	}

	/**
	 * Output a series of primary navigation tabs for swithcing between items within a particular module/action.
	 *
	 * @param array Nested array of tabs containing possible keys of align, link_target, link, title.
	 * @param string The name of the active tab. Corresponds with the key of each tab item.
	 */
	function output_nav_tabs($tabs=array(), $active='')
	{
		global $plugins;
		$tabs = $plugins->run_hooks("admin_page_output_nav_tabs_start", $tabs);
		echo "<div class=\"nav_tabs\">";
		echo "\t<ul>\n";
		foreach($tabs as $id => $tab)
		{
			$class = '';
			if($id == $active)
			{
				$class = ' active';
			}
			if(isset($tab['align']) == "right")
			{
				$class .= " right";
			}
			$target = '';
			if(isset($tab['link_target']))
			{
				$target = " target=\"{$tab['link_target']}\"";
			}
			if(!isset($tab['link']))
			{
				$tab['link'] = '';
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
		$arguments = array('tabs' => $tabs, 'active' => $active);
		$plugins->run_hooks("admin_page_output_nav_tabs_end", $arguments);
	}

	/**
	 * Output a page asking if a user wishes to continue performing a specific action.
	 *
	 * @param string The URL to be forwarded to.
	 * @param string The confirmation message to output.
	 * @param string The title to use in the output header
	 */
	function output_confirm_action($url, $message="", $title="")
	{
		global $lang;

		if(!$message)
		{
			$message = $lang->confirm_action;
		}
		$this->output_header($title);
		$form = new Form($url, 'post');
		echo "<div class=\"confirm_action\">\n";
		echo "<p>{$message}</p>\n";
		echo "<br />\n";
		echo "<p class=\"buttons\">\n";
		echo $form->generate_submit_button($lang->yes, array('class' => 'button_yes'));
		echo $form->generate_submit_button($lang->no, array("name" => "no", 'class' => 'button_no'));
		echo "</p>\n";
		echo "</div>\n";
		$form->end();
		$this->output_footer();
	}

	/**
	 * Build a clickable MyCode editor for the Admin CP.
	 *
	 * @param string The ID of the textarea to bind the editor to.
	 * @param string The language string for the editor.
	 * @return string The build MyCode editor Javascript.
	 */
	function build_codebuttons_editor($bind, $editor_language)
	{
		global $lang;
		
		return <<<EOF

<script type="text/javascript">
$(function() {
	$("#{$bind}").sceditor({
		plugins: "bbcode",
		style: "../jscripts/sceditor/editor_themes/mybb.css",
		rtl: {$lang->settings['rtl']},
        locale: "{$lang->settings['htmllang']}",
		emoticons: {
			// Emoticons to be included in the dropdown
			dropdown: {
				":s": "../images/smilies/confused.png",
				":-/": "../images/smilies/undecided.png",
				":)": "../images/smilies/smile.png",
				";)": "../images/smilies/wink.png",
				":D": "../images/smilies/biggrin.png",
				":P": "../images/smilies/tongue.png",
				":(": "../images/smilies/sad.png",
				":@": "../images/smilies/angry.png",
				":blush:": "../images/smilies/blush.png",
			},
			// Emoticons to be included in the more section
			more: {
				":angel:": "../images/smilies/angel.png",
				":dodgy:": "../images/smilies/dodgy.png",
				":exclamation:": "../images/smilies/exclamation.png",
				":heart:": "../images/smilies/heart.png",
				":huh:": "../images/smilies/huh.png",
				":idea:": "../images/smilies/lightbulb.png",
				":sleepy:": "../images/smilies/sleepy.png",
				":cool:": "../images/smilies/cool.png",
				":rolleyes:": "../images/smilies/rolleyes.png",
				":shy:": "../images/smilies/shy.png",
				":at:": "../images/smilies/at.png"
			}
		},
        toolbar: "bold,italic,underline,strike|left,center,right,justify|font,size,color,removeformat|horizontalrule,image,email,link,unlink|video,emoticon|bulletlist,orderedlist|code,quote|maximize,source",
	});
      
	MyBBEditor = $("#{$bind}").sceditor("instance");
});
</script>
EOF;
	}
}

/**
 * A class for generating side bar blocks.
 */
class DefaultSidebarItem
{
	/**
	 * @var The title of the side bar block.
	 */
	private $_title;

	/**
	 * @var string The contents of the side bar block.
	 */
	private $_contents;

	/**
	 * Constructor. Set the title of the side bar block.
	 *
	 * @param string The title of the side bar block.
	 */
	function __construct($title="")
	{
		$this->_title = $title;
	}

	/**
	 * Add menus item to the side bar block.
	 *
	 * @param array Array of menu items to add. Each menu item should be a nested array of id, link and title.
	 * @param string The ID of the active menu item if there is one.
	 */
	function add_menu_items($items, $active)
	{
		global $run_module;

		$this->_contents = "<ul class=\"menu\">";
		foreach($items as $item)
		{
			if(!check_admin_permissions(array("module" => $run_module, "action" => $item['id']), false))
			{
				continue;
			}

			$class = "";
			if($item['id'] == $active)
			{
				$class = "active";
			}
			$item['link'] = htmlspecialchars_uni($item['link']);
			$this->_contents .= "<li class=\"{$class}\"><a href=\"{$item['link']}\">{$item['title']}</a></li>\n";
		}
		$this->_contents .= "</ul>";
	}

	/**
	 * Sets custom html to the contents variable
	 *
	 * @param string The custom html to set
	 */
	function set_contents($html)
	{
		$this->_contents = $html;
	}

	/**
	 * Fetch the HTML markup for the side bar box.
	 */
	function get_markup()
	{
		$markup = "<div class=\"left_menu_box\">\n";
		$markup .= "<div class=\"title\">{$this->_title}</div>\n";
		if($this->_contents)
		{
			$markup .= $this->_contents;
		}
		$markup .= "</div>\n";
		return $markup;
	}
}

/**
 * Generate a Javascript based popup menu.
 */
class DefaultPopupMenu
{
	/**
	 * @var string The title of the popup menu to be shown on the button.
	 */
	private $_title;

	/**
	 * @var string The ID of this popup menu. Must be unique.
	 */
	private $_id;

	/**
	 * @var string Built HTML for the items in the popup menu.
	 */
	private $_items;

	/**
	 * Initialise a new popup menu.
	 *
	 * @var string The ID of the popup menu.
	 * @var string The title of the popup menu.
	 */
	function __construct($id, $title='')
	{
		$this->_id = $id;
		$this->_title = $title;
	}

	/**
	 * Add an item to the popup menu.
	 *
	 * @param string The title of this item.
	 * @param string The page this item should link to.
	 * @param string The onclick event handler if we have one.
	 */
	function add_item($text, $link, $onclick='')
	{
		if($onclick)
		{
			$onclick = " onclick=\"{$onclick}\"";
		}
		$this->_items .= "<div class=\"popup_item_container\"><a href=\"{$link}\"{$onclick} class=\"popup_item\">{$text}</a></div>\n";
	}

	/**
	 * Fetch the contents of the popup menu.
	 *
	 * @return string The popup menu.
	 */
	function fetch()
	{
		$popup = "<div class=\"popup_menu\" id=\"{$this->_id}_popup\">\n{$this->_items}</div>\n";
		if($this->_title)
		{
			$popup .= "<a href=\"javascript:;\" id=\"{$this->_id}\" class=\"popup_button\">{$this->_title}</a>\n";
		}
		$popup .= "<script type=\"text/javascript\">\n";
		$popup .= "$(\"#{$this->_id}\").popupMenu();\n";
		$popup .= "</script>\n";
		return $popup;
	}

	/**
	 * Outputs a popup menu to the browser.
	 */
	function output()
	{
		echo $this->fetch();
	}
}
?>
