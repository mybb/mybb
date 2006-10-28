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
	var $openedform;
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
			$this->openedform = 1;
		}
		
		echo <<<END
		<div id="container">
		<div id="logo">
			<h1><span class="invisible">MyBB</span></h1>
		</div>
		<div id="inner_container">
		<div id="header">$this->title</div>
END;
		if(empty($this->steps))
		{
			$this->steps = array();
		}
		if(is_array($this->steps))
		{
			echo "\n		<div id=\"progress\">";
			echo "\n			<ul>\n";
			foreach($this->steps as $action => $step)
			{
				if($action == $mybb->input['action'])
				{
					echo "				<li class=\"active\"><strong>$step</strong></li>\n";
				}
				else
				{
					echo "				<li>$step</li>\n";
				}
			}
			echo "			</ul>";
			echo "\n		</div>";
			echo "\n		<div id=\"content\">\n";
		}
		else
		{
			echo "\n		<div id=\"progress_error\"></div>";
			echo "\n		<div id=\"content_error\">\n";
		}
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
	
	function print_boards()
	{
		$boardscripts = array();
	
		$dh = opendir(CONVERT_ROOT."/boards");
		while(($file = readdir($dh)) !== false)
		{
			if($file != "." && $file != "..")
			{
				$boardscripts[] = str_replace('.php', '', $file);
			}
		}
		closedir($dh);
		
		foreach($boardscripts as $key => $file)
		{
			$boardscript = file_get_contents(CONVERT_ROOT."/boards/{$file}.php");
			preg_match("#Board Name:(.*)#i", $boardscript, $verinfo);
			if(!$boardscripts[$key+1])
			{
				$boards .= "<option value=\"$file\" selected=\"selected\">$verinfo[1]</option>\n";
			}
			else
			{
				$boards .= "<option value=\"$file\">$verinfo[1]</option>\n";
			}
		}
		unset($boardscripts);
		unset($boardscript);
		
		return $boards;
	}
	
	function module_list()
	{
		global $board, $session;
		
		if(!$this->doneheader)
		{
			$this->print_header();
		}
		
		echo "<table border='1' width='100%'>";
		$completedmodules = explode(",", $session['completedmodules']);
		
		foreach($completedmodules as $mod)
		{
			$completed[$mod] = 1;
		}
		
		foreach($board->modules as $key => $module)
		{
			echo "<tr>";
			echo "<td><b>".$module['name']."</b><br />".$module['description'];
			echo $key." - ".$module['name']."<br />";
			echo $module['description'];
			$dependancies = explode(",", $module['dependancies']);
			
			if(is_array($dependancies))
			{
				echo "<br />Dependancies: ";
				foreach($dependancies as $depend)
				{
					if($depend == "")
					{
						break;
					}
					
					echo $board->modules[$depend]['name'].",";
					
					if(!$completed[$depend])
					{
						$awaitingdepend = 1;
					}
				}
			}
			
			echo "</td>";
			echo "<td>";
			
			if($session['module'])
			{
				echo "RESUME";
			}
			elseif($awaitingdepend)
			{
				echo "AWAITING ON DEPENDANCIES TO BE COMPLETED";
			}
			else
			{
				echo "<a href=\"index.php?module=$key\">RUN MODULE</a>";
			}
			echo "</td>";
			echo "</tr>";
		}
		echo "</table>";
		$this->print_footer("", "", 1);
	}


	function print_footer($nextact="", $name="", $do_session=0)
	{
		global $lang;
		if($nextact !== "" && $this->openedform)
		{
			if(!$name)
			{
				$name = "action";
			}
			echo "\n			<input type=\"hidden\" name=\"$name\" value=\"$nextact\" />";
			echo "\n				<div id=\"next_button\"><input type=\"submit\" class=\"submit_button\" value=\"".$lang->next." &raquo;\" /></div><br style=\"clear: both;\" />\n";
			$formend = "</form>";
		}
		elseif(!$nextact && $this->openedform)
		{
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
			update_session();
		}
		exit;
	}
}
?>