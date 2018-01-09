<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

class installerOutput {

	/**
	 * @var bool
	 */
	public $doneheader;
	/**
	 * @var bool
	 */
	public $openedform;
	/**
	 * @var string
	 */
	public $script = "index.php";
	/**
	 * @var array
	 */
	public $steps = array();
	/**
	 * @var string
	 */
	public $title = "MyBB Installation Wizard";

	/**
	 * @param string $title
	 * @param string $image
	 * @param int    $form
	 * @param int    $error
	 */
	function print_header($title="", $image="welcome", $form=1, $error=0)
	{
		global $mybb, $lang;

		if($title == "")
		{
			$title = $lang->welcome;
		}

		if($lang->title)
		{
			$this->title = $lang->title;
		}

		@header("Content-type: text/html; charset=utf-8");

		$this->doneheader = 1;
		$dbconfig_add = '';
		if($image == "dbconfig")
		{
			$dbconfig_add = "<script type=\"text/javascript\">document.write('<style type=\"text/css\">.db_type { display: none; }</style>');</script>";
		}
		echo <<<END
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>{$this->title} &gt; {$title}</title>
	<link rel="stylesheet" href="stylesheet.css" type="text/css" />
	<script type="text/javascript" src="../jscripts/jquery.js?ver=1813"></script>
	<script type="text/javascript" src="../jscripts/general.js?ver=1813"></script>
	{$dbconfig_add}
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
		<div id="header">{$this->title}</div>
END;
		if($mybb->version_code >= 1700 && $mybb->version_code < 1800)
		{
			echo $lang->development_preview;
		}
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
						echo "				<li class=\"{$action} active\"><strong>{$step}</strong></li>\n";
					}
					else
					{
						echo "				<li class=\"{$action}\">{$step}</li>\n";
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
			<h2 class="{$image}">{$title}</h2>\n
END;
		}
	}

	/**
	 * @param string $contents
	 */
	function print_contents($contents)
	{
		echo $contents;
	}

	/**
	 * @param string $message
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
	 * @param string $nextact
	 */
	function print_footer($nextact="")
	{
		global $lang, $footer_extra;
		if($nextact && $this->openedform)
		{
			echo "\n			<input type=\"hidden\" name=\"action\" value=\"{$nextact}\" />";
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
				MyBB &copy; 2002-{$copyyear} MyBB Group
			</div>
		</div>
		</div>
		</div>
		{$formend}
		{$footer_extra}
</body>
</html>
END;
		exit;
	}
}
