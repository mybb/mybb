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

	function print_header($title="Welcome", $form=1)
	{
		$this->doneheader = 1;
		echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
		echo "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n";
		echo "<html>\n";
		echo "<head>\n";
		echo "<title>MyBulletinBoard Installation Wizard</title>\n";
		echo "<link rel=\"stylesheet\" href=\"stylesheet.css\" type=\"text/css\" />\n";
		echo "</head>\n";
		echo "<body>\n";
		if($form)
		{
			echo "<form method=\"post\" action=\"".$this->script."\">\n";
			$this->openedform = 1;
		}
		echo "<table class=\"outer\">\n";
		echo "<tr>\n";
		echo "<td id=\"header\"><img src=\"header.gif\"></td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td class=\"thead\">Installation Wizard</td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td class=\"thead2\">$title</td>\n";
		echo "</tr>\n";
	}

	function print_contents($contents)
	{
		echo "<tr>\n";
		echo "<td class=\"content\">\n";
		echo $contents;
		echo "</td>\n";
		echo "</tr>\n";
	}

	function print_error($message)
	{
		if(!$this->doneheader)
		{
			$this->print_header("Error", 0);
		}
		$this->print_contents($message);
		$this->print_footer();
	}


	function print_footer($nextact="")
	{
		if($nextact && $this->openedform) {
			echo "<tr>\n";
			echo "<td class=\"content\">\n";
			echo "<div align=\"right\"><input type=\"submit\" name=\"submit\" value=\"Next Step\" /><input type=\"hidden\" name=\"action\" value=\"$nextact\" /></div>\n";
			echo "</td>\n";
			echo "</tr>\n";
		}
		echo "<tr>\n";
		echo "<td class=\"bottom\" align=\"right\">&copy; 2004 The MyBulletinBoard Group</td>\n";
		echo "</tr>\n";
		echo "</table>\n";
		if($this->openedform)
		{
			echo "</form>\n";
		}
		echo "</body>\n";
		echo "</html>\n";
		exit;
	}
}
?>