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

class converterOutput {

	var $doneheader;
	var $opened_form;
	var $script = "index.php";
	var $steps = array();
	var $title = "MyBB Conversion Wizard";

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

	function print_contents($contents)
	{
		echo $contents;
	}

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

	function board_list()
	{
		if(!$this->doneheader)
		{
			$this->print_header();
		}		
		echo "<table border='1' width='100%'>";
	
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
					echo "<td>{$version_info[1]}</td>\n";
					echo "<td><input type=\"radio\" name=\"board\" value=\"$bb_name\" /></td>\n";
					echo "</tr>\n";
				}
			}
		}
		closedir($dh);
		echo "</table>";
		$this->print_footer();
	}
		

	function module_list()
	{
		global $board, $import_session;
		
		$this->print_header("Module Selecion", "", 0);

		$completed_modules = explode(",", $import_session['completed_modules']);

		foreach($completedmodules as $mod)
		{
			$completed[$mod] = 1;
		}
		
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
			$dependancy_list = array();
			if(count($board->modules) == $i)
			{
				$class .= " last";
			}
			echo "<tr class=\"{$class}\">\n";
			echo "<td class=\"first\"><strong>".$module['name']."</strong>";
			if($module['description'])
			{
				echo "<br />".$module['description'];
			}

			// Fetch dependant modules
			$dependancies = explode(",", $module['dependancies']);
			if(count($dependancies) > 0)
			{
				foreach($dependancies as $dependancy)
				{
					if($dependancy == "") break;
					$dependancy_list[] = $board->modules[$dependancy]['name'];
					
					if(in_array($import_session['completed'], $dependancy))
					{
						$awaiting_dependancies = 1;
					}
				}
			}

			if(count($dependancy_list) > 0)
			{
				echo "<br /><small>Dependancies: ".implode(", ", $dependancy_list)."</small>";
			}
			
			echo "</td>";
			echo "<td class=\"last\" width=\"1\">";
			echo "<form method=\"post\" action=\"{$this->script}\">\n";
			if($import_session['module'] == $module['name'])
			{
				echo "<input type=\"submit\" class=\"submit_button\" value=\"Resume &raquo;\" disabled=\"disabled\" />";
			}
			elseif($awaiting_dependancies)
			{
				echo "<input type=\"submit\" class=\"submit_button\" value=\"Run &raquo;\" disabled=\"disabled\" />";
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