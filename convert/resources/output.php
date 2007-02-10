<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/license.php
 *
 * $Id: output.php 2109 2006-08-18 05:15:51Z Tikitiki $
 */

/**
 * Class to create output from the converter scripts
 */
class converterOutput
{
	/**
	 * This is set to 1 if the header has been called.
	 * @var int 1 or 0
	 */
	var $doneheader;
	
	/**
	 * This is set to 1 if a form has been opened.
	 * @var int 1 or 0
	 */
	var $opened_form;
	
	/**
	 * Script name
	 * @var string  
	 */
	var $script = "index.php";
	
	/**
	 * Steps for conversion
	 * @var array
	 */
	var $steps = array();
	
	/**
	 * Title of the system
	 * @var string
	 */
	var $title = "MyBB Merge Wizard";

	/**
	 * Method to print the converter header
	 * @param string Page title
	 * @param string Icon to be used
	 * @param int Open a form 1/0
	 * @param int Error???
	 */
	function print_header($title="Welcome", $image="welcome", $form=1, $error=0)
	{
		global $mybb;

		$this->doneheader = 1;

		echo <<<END
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>$this->title &gt; $title</title>
	<link rel="stylesheet" href="stylesheet.css" type="text/css" />
</head>
<body>
END;

		echo <<<END
		<div id="container">
		<div id="logo">
			<h1><span class="invisible">MyBB</span></h1>
		</div>
		<div id="inner_container">
		<div id="header">$this->title</div>
		<div id="content">
END;
		if($form)
		{
			echo "\n	<form method=\"post\" action=\"".$this->script."\">\n";
			$this->opened_form = 1;
		}

		if($title != "")
		{
			echo <<<END
			<h2 class="$image">$title</h2>\n
END;
		}
	}

	/**
	 * Echo the contents out
	 * @param string Contents to echo out
	 */
	function print_contents($contents)
	{
		echo $contents;
	}

	/**
	 * Print an error block, and the footer.
	 * @param string Error string
	 */
	function print_error($message)
	{
		if(!$this->doneheader)
		{
			$this->print_header('Error', "", 0, 1);
		}
		echo "			<div class=\"error\">\n				";
		echo "<h3>Error</h3>";
		$this->print_contents($message);
		echo "\n			</div>";

		$this->print_footer();
	}

	/**
	 * Print a list of possible boards to convert from, and the footer
	 */
	function board_list()
	{
		if(!$this->doneheader)
		{
			$this->print_header();
		}

		echo "<p>Thank you for choosing MyBB.  This wizard will guide you through the process of converting from your existing bulletin board software to MyBB.";

		echo "<div class=\"border_wrapper\">\n";
		echo "<div class=\"title\">Board Selection</div>\n";
		echo "<table class=\"general\" cellspacing=\"0\">\n";
		echo "<tr>\n";
		echo "<th colspan=\"2\" class=\"first last\">Please select the board you wish to convert from.</th>\n";
		echo "</tr>\n";

		$dh = opendir(CONVERT_ROOT."boards");
		while(($file = readdir($dh)) !== false)
		{
			if($file != "." && $file != ".." && get_extension($file) == "php")
			{
				$bb_name = str_replace(".php", "", $file);
				$board_script = file_get_contents(CONVERT_ROOT."boards/{$file}");				
				// Match out board name
				preg_match("#Board Name:(.*)#i", $board_script, $version_info);
				if($version_info[1])
				{
					$board_array[$bb_name] = $version_info[1];
				}
			}
		}

		asort($board_array);

		foreach($board_array as $bb_name => $version_info)
		{
			echo "<tr>\n";
			echo "<td><label for=\"$bb_name\">$version_info</label></td>\n";
			echo "<td><input type=\"radio\" name=\"board\" value=\"$bb_name\" id=\"$bb_name\" /></td>\n";
			echo "</tr>\n";
		}

		closedir($dh);
		echo "</table>\n";
		echo "</div>\n";

		$this->print_footer();
	}

	/**
	 * Print a list of modules and their dependencies for user to choose from, and the footer
	 */
	function module_list()
	{
		global $board, $import_session;

		if(count($board->modules) == count($import_session['completed']))
		{
			header("Location: index.php?action=finish");
			exit;
		}

		$this->print_header("Module Selection", "", 0);

		echo "<div class=\"border_wrapper\">\n";
		echo "<div class=\"title\">Module Selection</div>\n";
		echo "<table class=\"general\" cellspacing=\"0\">\n";
		echo "<tr>\n";
		echo "<th colspan=\"2\" class=\"first last\">Please select a module to run.</th>\n";
		echo "</tr>\n";

		$class = "first";
		$i = 0;

		foreach($board->modules as $key => $module)
		{
			++$i;
			$dependency_list = array();
			$awaiting_dependencies = 0;

			// Fetch dependent modules
			$dependencies = explode(',', $module['dependencies']);
			$icon = '';
			if(count($dependencies) > 0)
			{
				foreach($dependencies as $dependency)
				{
					if($dependency == '')
					{
						break;
					}

					if(!in_array($dependency, $import_session['completed']))
					{
						// Cannot be run yet
						$awaiting_dependencies = 1;
						$dependency_list[] = $board->modules[$dependency]['name'];
						$icon = ' awaiting';
					}
					else
					{
						// Dependency has been run
						$dependency_list[] = "<del>".$board->modules[$dependency]['name']."</del>\n";
					}
				}
			}

			if(in_array($key, $import_session['completed']))
			{
				// Module has been completed.  Thus show.
				$icon = ' completed';
			}

			if(count($board->modules) == $i)
			{
				$class .= " last";
			}

			echo "<tr class=\"{$class}\">\n";
			echo "<td class=\"first\"><div class=\"module{$icon}\">".$module['name']."</div>\n";

			if($module['description'])
			{
				echo "<div class=\"module_description\">".$module['description']."</div>\n";
			}

			if(in_array($key, $import_session['completed']))
			{
				// Module has been completed.  Thus show.
				echo "<div class=\"pass module_description\">Completed</div>\n";
			}

			if(count($dependency_list) > 0)
			{
				echo "<div class=\"module_description\"><small>Dependencies: ".implode(', ', $dependency_list)."</small></div>\n";
			}

			echo "</td>\n";
			echo "<td class=\"last\" width=\"1\">\n";
			echo "<form method=\"post\" action=\"{$this->script}\">\n";

			if($import_session['module'] == $key || in_array($key, $import_session['resume_module']))
			{
				echo "<input type=\"submit\" class=\"submit_button\" value=\"Resume &raquo;\" />\n";
			}
			elseif($awaiting_dependencies || in_array($key, $import_session['disabled']) || in_array($key, $import_session['completed']) && $key != "db_configuration")
			{
				echo "<input type=\"submit\" class=\"submit_button submit_button_disabled\" value=\"Run &raquo;\" disabled=\"disabled\" />\n";
			}
			else
			{
				echo "<input type=\"submit\" class=\"submit_button\" value=\"Run &raquo;\" />\n";
			}

			echo "<input type=\"hidden\" name=\"module\" value=\"{$key}\" />\n";
			echo "</form>\n";
			echo "</td>\n";
			echo "</tr>\n";

			if($class == "alt_row")
			{
				$class = "";
			}
			else
			{
				$class = "alt_row";
			}
		}

		echo "</table>\n";
		echo "</div><br />\n";
		echo '<p>After you have run the modules you want, continue to the next step in the conversion process.  The cleanup step will remove any temporary data created during the conversion.</p>';
		echo "<form method=\"post\" action=\"{$this->script}\">\n";
		echo '<input type="hidden" name="action" value="finish" />';
		echo '<div style="text-align:right"><input type="submit" class="submit_button" value="Cleanup &raquo;" /></div></form>';

		$this->print_footer('', '', 1);
	}

	/**
	 * Print a list of fields to be written in by user for database details.
	 */
	function print_database_details_table($name)
	{
		global $dbengines, $dbhost, $dbuser, $dbname, $tableprefix;
		echo <<<EOF
<div class="border_wrapper">
<div class="title">$name Database Configuration</div>
<table class="general" cellspacing="0">
<tr>
	<th colspan="2" class="first last">Database Settings</th>
</tr>
<tr class="first">
	<td class="first"><label for="dbengine">Database Engine:</label></td>
	<td class="last alt_col"><select name="dbengine" id="dbengine">{$dbengines}</select></td>
</tr>

<tr class="alt_row">
	<td class="first"><label for="dbhost">Database Host:</label></td>
	<td class="last alt_col"><input type="text" class="text_input" name="dbhost" id="dbhost" value="{$dbhost}" /></td>
</tr>
<tr>
	<td class="first"><label for="dbuser">Database Username:</label></td>
	<td class="last alt_col"><input type="text" class="text_input" name="dbuser" id="dbuser" value="{$dbuser}" /></td>
</tr>
<tr class="alt_row">
	<td class="first"><label for="dbpass">Database Password:</label></td>
	<td class="last alt_col"><input type="password" class="text_input" name="dbpass" id="dbpass" value="" /></td>
</tr>
<tr class="last">
	<td class="first"><label for="dbname">Database Name:</label></td>
	<td class="last alt_col"><input type="text" class="text_input" name="dbname" id="dbname" value="{$dbname}" /></td>
</tr>
<tr>
	<th colspan="2" class="first last">Table Settings</th>
</tr>
<tr class="last">
	<td class="first"><label for="tableprefix">Table Prefix:</label></td>
	<td class="last alt_col"><input type="text" class="text_input" name="tableprefix" id="tableprefix" value="{$tableprefix}" /></td>
</tr>
</table>
</div>
<p>Once you have checked these details are correct, click next to continue.</p>
EOF;
	}

	/**
	 * Print final page
	 */
	function finish_conversion()
	{
		global $config;

		if(!$this->doneheader)
		{
			$this->print_header("Completion", '', 1);
		}

		if(!isset($config['admin_dir']))
		{
			$config['admin_dir'] = "admin";
		}

		echo '<p>The current conversion session has been finished.  You may now go to your copy of <a href="../">MyBB</a> or your <a href="../'.$config['admin_dir'].'/index.php">Admin Control Panel</a>.  It is recommended that you run the Rebuild and Recount tools in the Admin CP.</p>';
		echo '
<p>Please remove this directory if you are not planning on converting any other forums.</p>';
		
		echo '<br />
<p>The following will allow you to download a detailed report generated by the converter in several styles.
<div class="border_wrapper">
<div class="title">Report Generation</div>
<table class="general" cellspacing="0">
<tr>
<th colspan="2" class="first last">Please select the report style you wish to generate.</th>
</tr>
<tr>
<td><label for="txt"> Plain Text File
</label></td>
<td><input type="radio" name="reportgen" value="txt" id="txt" /></td>
</tr>

<tr>
<td><label for="html"> HTML (Browser Viewable) File
</label></td>
<td><input type="radio" name="reportgen" value="html" id="html" /></td>
</tr>
<tr>

<td><label for="xml"> XML File
</label></td>
<td><input type="radio" name="reportgen" value="xml" id="xml" /></td>
</tr>
</table>
</div>

		<div id="next_button"><input type="submit" class="submit_button" value="Download &raquo;" /></div>
		
</form>';

		$this->print_footer('', '', 1, true);
	}

	/**
	 * Print the footer of the page
	 * @param string The next 'action'
	 * @param string The name of the next action
	 * @param int Do session update? 1/0
	 */
	function print_footer($next_action="", $name="", $do_session=1, $override_form=false)
	{
		global $import_session;

		if($this->opened_form && $override_form != true)
		{
			global $mybb;
			
			if($mybb->input['autorefresh'] == "yes" || $mybb->input['autorefresh'] == "no")
			{
				$import_session['autorefresh'] = $mybb->input['autorefresh'];
			}
			
			if($import_session['autorefresh'] == "yes")
			{
				echo "\n		<meta http-equiv=\"Refresh\" content=\"3; url=".$this->script."\" />";
				echo "\n		<div id=\"next_button\"><input type=\"submit\" class=\"submit_button\" value=\"Redirecting... &raquo;\" alt=\"Click to continue, if you do not wish to wait.\"/></div>"; 
			}
			else
			{
				echo "\n		<div id=\"next_button\"><input type=\"submit\" class=\"submit_button\" value=\"Next &raquo;\" /></div>";
			}
			echo "\n	</form>\n";

			// Only if we're in a module
			if($import_session['module'] && $import_session['module'] != 'db_configuration' && (!defined('BACK_BUTTON') || BACK_BUTTON != false))
			{				
				echo "\n	<form method=\"post\" action=\"".$this->script."\">\n";
				echo "\n		<input type=\"hidden\" name=\"action\" value=\"module_list\" />\n";
				echo "\n		<div id=\"back_button\"><input type=\"submit\" class=\"submit_button\" value=\"&laquo; Back\" /></div><br style=\"clear: both;\" />\n";
				echo "\n	</form>\n";
				
			}
			else
			{
				echo "\n <br style=\"clear: both;\" />";
			}
		}
		else
		{
			$formend = "";
		}

		echo <<<END
		</div>
		<div id="footer">
END;

		$copyyear = date('Y');
		echo <<<END
			<div id="copyright">
				MyBB &copy; 2002-$copyyear MyBB Group
			</div>
		</div>
		</div>
		</div>
</body>
</html>
END;
		if($do_session == 1)
		{
			update_import_session();
		}
		exit;
	}
}
?>
