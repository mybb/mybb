<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

class installerOutput {
	var $doneheader;
	var $openedform;
	var $script = "index.php";
	var $steps = array();
	var $title = "MyBB Installation Wizard";

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
	<script src="general.js" type="text/javascript" /></script>
</head>
<body>
END;
		if($form)
		{
			echo "\n	<form method=\"post\" action=\"".$this->script."\">\n";
			$this->openedform = 1;
		}
		
		echo <<<END
\n		<div id="container">
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
		if(!$this->doneheader)
		{
			$this->print_header("Error", "", 0, 1);
		}
		echo "			<div class=\"error\">\n				";
		echo "<h3>Error</h3>";
		$this->print_contents($message);
		echo "\n			</div>";
		$this->print_footer();
	}


	function print_footer($nextact="")
	{
		if($nextact && $this->openedform)
		{
			echo "\n			<input type=\"hidden\" name=\"action\" value=\"$nextact\" />";
			echo "\n				<div id=\"next_button\"><input type=\"submit\" class=\"submit_button\" value=\"Next &raquo;\" /></div><br style=\"clear: both;\" />\n";
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
		$formend
</body>
</html>
END;
		exit;
	}
}
?>