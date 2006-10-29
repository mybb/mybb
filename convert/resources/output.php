<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id: output.php 2109 2006-08-18 05:15:51Z Tikitiki $
 */

/**
 * Class to create output from the converter scripts
 */
class converterOutput {

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
	var $title = "MyBB Conversion Wizard";

	/**
	 * Method to print the converter header
	 * @param string Page title
	 * @param string Icon to be used
	 * @param int Open a form 1/0
	 * @param int Error???
	 */
	function print_header($title="Welcome", $image="welcome", $form=1, $error=0)
	{
		global $mybb, $lang;
		
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
		if($form)
		{
			echo "\n	<form method=\"post\" action=\"".$this->script."\">\n";
			$this->opened_form = 1;
		}
		
		echo <<<END
		<div id="container">
		<div id="logo">
			<h1><span class="invisible">MyBB</span></h1>
		</div>
		<div id="inner_container">
		<div id="header">$this->title</div>
		<div id="content">
END;
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
		global $lang;
		if(!$this->doneheader)
		{
			$this->print_header($lang->error, "", 0, 1);
		}
		echo "			<div class=\"error\">\n				";
		echo "<h3>".$lang->error."</h3>";
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
		echo "<div class=\"border_wrapper\">\n";
		echo "<div class=\"title\">Board Selection</div>\n";
		echo "<table class=\"general\" cellspacing=\"0\">\n";
		echo "<tr>\n";
		echo "<th colspan=\"2\" class=\"first last\">Please select the board you wish to convert from.</th>\n";
		echo "</tr>\n";
		$dh = opendir(CONVERT_ROOT."/boards");
		while(($file = readdir($dh)) !== false)
		{
			if($file != "." && $file != ".." && get_extension($file) == "php")
			{
				$bb_name = str_replace(".php", "", $file);
				$board_script = file_get_contents(CONVERT_ROOT."/boards/{$file}");
				// Match out board name
				preg_match("#Board Name:(.*)#i", $board_script, $version_info);
				if($version_info[1])
				{
					echo "<tr>\n";
					echo "<td><label for=\"$bb_name\">{$version_info[1]}</label></td>\n";
					echo "<td><input type=\"radio\" name=\"board\" value=\"$bb_name\" id=\"$bb_name\" /></td>\n";
					echo "</tr>\n";
				}
			}
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
		
		$this->print_header("Module Selection", "", 0);
		
		echo "<div class=\"border_wrapper\">\n";
		echo "<div class=\"title\">Module Selection</div>\n";
		echo "<table class=\"general\" cellspacing=\"0\">\n";
		echo "<tr>\n";
		echo "<th colspan=\"2\" class=\"first last\">Please select a module to run.</th>\n";
		echo "</tr>\n";
		$class = "first";
		$i=0;
		foreach($board->modules as $key => $module)
		{
			++$i;
			$dependency_list = array();
			if(count($board->modules) == $i)
			{
				$class .= " last";
			}
			echo "<tr class=\"{$class}\">\n";
			echo "<td class=\"first\"><strong>".$module['name']."</strong>";
			if($module['description'])
			{
				echo '<br />'.$module['description'];
			}
			
			if(in_array($key, $import_session['completed']))
			{
				// Module has been completed.  Thus show.
				echo '<br /><small class="pass">Completed</small>';
			}
			

			// Fetch dependent modules
			$dependencies = explode(',', $module['dependencies']);
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
					}
					else
					{
						// Dependency has been run
						$dependency_list[] = '<del>'.$board->modules[$dependency]['name'].'</del>';
					}
				}
			}

			if(count($dependency_list) > 0)
			{
				echo "<br /><small>Dependencies: ".implode(", ", $dependency_list)."</small>";
			}
			
			echo "</td>";
			echo "<td class=\"last\" width=\"1\">";
			echo "<form method=\"post\" action=\"{$this->script}\">\n";
			if($import_session['module'] == $key)
			{
				echo "<input type=\"submit\" class=\"submit_button\" value=\"Resume &raquo;\" disabled=\"disabled\" />";
			}
			elseif($awaiting_dependencies)
			{
				echo "<input type=\"submit\" class=\"submit_button submit_button_disabled\" value=\"Run &raquo;\" disabled=\"disabled\" />";
			}
			else
			{
				echo "<input type=\"submit\" class=\"submit_button\" value=\"Run &raquo;\" />";
			}
			echo "<input type=\"hidden\" name=\"module\" value=\"{$key}\" />";
			echo "</form>";
			echo "</td>";
			echo "</tr>";
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
		echo "</div>\n";
		$this->print_footer("", "", 1);
	}

	/**
	 * Print the footer of the page
	 * @param string The next 'action'
	 * @param string The name of the next action
	 * @param int Do session update? 1/0
	 */
	function print_footer($next_action="", $name="", $do_session=1)
	{
		global $lang;
		if($this->opened_form)
		{
			echo "\n				<div id=\"next_button\"><input type=\"submit\" class=\"submit_button\" value=\"".$lang->next." &raquo;\" /></div><br style=\"clear: both;\" />\n";
			$formend = "</form>";
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
		$formend
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
