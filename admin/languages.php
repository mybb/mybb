<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

define("IN_MYBB", 1);

require_once "./global.php";

// Load language packs for this section
global $lang;
$lang->load("languages");

$languages = $lang->get_languages();

addacpnav($lang->nav_languages, "languages.php?".SID);

checkadminpermissions("caneditlangs");
logadmin();
if(!isset($mybb->input['action']))
{
	$mybb->input['action'] = '';
}

$plugins->run_hooks("admin_languages_start");

if($mybb->input['action'] == "do_editset")
{
	// Update the language set file

	$plugins->run_hooks("admin_languages_do_editset");

	// Validate input
	$editlang = basename($mybb->input['lang']);
	$file = MYBB_ROOT."inc/languages/".$editlang.".php";
	if(!file_exists($file))
	{
		cperror($lang->invalid_file);
	}
	if(!is_writable($file))
	{
		cperror($lang->cannot_write_to_file);
	}
	foreach($mybb->input['info'] as $key => $info)
	{
		$info = str_replace("\\", "\\\\", $info);
		$newlanginfo[$key] = str_replace("\"", '\"', $info);
	}
	if($newlanginfo['admin'] == "yes")
	{
		$newlanginfo['admin'] = 1;
	}
	else
	{
		$newlanginfo['admin'] = 0;
	}
	if($newlanginfo['rtl'] == "yes")
	{
		$newlanginfo['rtl'] = 1;
	}
	else
	{
		$newlanginfo['rtl'] = 0;
	}

	// Get contents of existing file
	require $file;

	// Make the contents of the new file
	$newfile = "<?php
// The friendly name of the language
\$langinfo['name'] = \"$newlanginfo[name]\";

// The author of the language
\$langinfo['author'] = \"$langinfo[author]\";

// The language authors website
\$langinfo['website'] = \"$langinfo[website]\";

// Compatible version of MyBB
\$langinfo['version'] = \"$langinfo[version]\";

// Sets if the translation includes the Admin CP (1 = yes, 0 = no)
\$langinfo['admin'] = $newlanginfo[admin];

// Sets if the language is RTL (Right to Left) (1 = yes, 0 = no)
\$langinfo['rtl'] = $newlanginfo[rtl];

// Sets the lang in the <html> on all pages
\$langinfo['htmllang'] = \"$newlanginfo[htmllang]\";

// Sets the character set, blank uses the default.
\$langinfo['charset'] = \"$newlanginfo[charset]\";".
"?".">";

	// Put it in!
	if($file = fopen($file, "w"))
	{
		fwrite($file, $newfile);
		fclose($file);
		cpredirect("languages.php?".SID, $lang->updated);
	}
	else
	{
		cperror($lang->cannot_write_to_file);
	}
}

if($mybb->input['action'] == "editset")
{
	$editlang = basename($mybb->input['lang']);
	$file = MYBB_ROOT."inc/languages/".$editlang.".php";
	if(!file_exists($file))
	{
		cperror($lang->invalid_file);
	}

	addacpnav($languages[$editlang], "languages.php?".SID."&action=edit&lang=$editlang");
	addacpnav($lang->nav_editing_set);

	// Get language info
	require $file;

	$plugins->run_hooks("admin_languages_editset");

	cpheader();
	startform("languages.php", "editset", "do_editset");
	starttable();
	$lang->editing_set = sprintf($lang->editing_set, $languages[$editlang]);
	tableheader($lang->editing_set);
	makeinputcode($lang->set_friendly_name, "info[name]", $langinfo['name']);
	makelabelcode($lang->set_author, $langinfo['author']);
	makelabelcode($lang->set_website, $langinfo['website']);
	makelabelcode($lang->set_mybb_version, $langinfo['version']);
	if($langinfo['admin'])
	{
		$langinfo['admin'] = "yes";
	}
	else
	{
		$langinfo['admin'] = "no";
	}
	makeyesnocode($lang->set_admin, "info[admin]", $langinfo['admin']);
	if($langinfo['rtl'])
	{
		$langinfo['rtl'] = "yes";
	}
	else
	{
		$langinfo['rtl'] = "no";
	}
	makeyesnocode($lang->set_rtl, "info[rtl]", $langinfo['rtl']);
	makeinputcode($lang->set_htmllang, "info[htmllang]", $langinfo['htmllang']);
	makeinputcode($lang->set_charset, "info[charset]", $langinfo['charset']);

	// Check if file is writable, before allowing submission
	if(!is_writable($file))
	{
		$lang->update_button = '';
		makelabelcode($lang->note_cannot_write, "", 2);
	}

	endtable();
	makehiddencode("lang", $editlang);
	endform($lang->update_button, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "do_edit")
{
	// Update the language variables file

	// Validate input
	$editlang = basename($mybb->input['lang']);
	$editwith = basename($mybb->input['editwith']);
	$folder = MYBB_ROOT."inc/languages/".$editlang."/";
	if(!file_exists($folder))
	{
		cperror($lang->invalid_set);
	}
	$file = basename($mybb->input['file']);
	if($mybb->input['inadmin'] == 1)
	{
		$file = 'admin/'.$file;
	}
	$editfile = $folder.$file;
	if(!file_exists($editfile))
	{
		cperror($lang->invalid_file);
	}
	if(!is_writable($editfile))
	{
		cperror($lang->cannot_write_to_file);
	}

	// Make the contents of the new file
	$newfile = "<"."?php\n";
	foreach($mybb->input['edit'] as $key => $phrase)
	{
		$phrase = str_replace("\\", "\\\\", $phrase);
		$phrase = str_replace("\"", '\"', $phrase);
		$key = str_replace("\\", '', $key);
		$key = str_replace("'", '', $key);
		$newfile .= "\$l['$key'] = \"$phrase\";\n";
	}
	if(is_array($mybb->input['newkey']))
	{
		foreach($mybb->input['newkey'] as $i => $key)
		{
			$phrase = $mybb->input['newvalue'][$i];
			if(!empty($key) && !empty($phrase))
			{
				$phrase = str_replace("\\", "\\\\", $phrase);
				$phrase = str_replace("\"", '\"', $phrase);
				$key = str_replace("\\", '', $key);
				$key = str_replace("'", '', $key);
				$newfile .= "\$l['$key'] = \"$phrase\";\n";
			}
		}
	}
	$newfile .= "?".">";
	$plugins->run_hooks("admin_languages_do_edit");
	// Put it in!
	if($file = fopen($editfile, "w"))
	{
		fwrite($file, $newfile);
		fclose($file);
		cpredirect("languages.php?".SID."&action=edit&lang=$editlang&editwith=$editwith", $lang->updated);
	}
	else
	{
		cperror($lang->cannot_write_to_file);
	}
}

if($mybb->input['action'] == "edit")
{
	// Editing language

	// Validate input
	$editlang = basename($mybb->input['lang']);
	$folder = MYBB_ROOT."inc/languages/".$editlang."/";
	$editwith = basename($mybb->input['editwith']);
	$editwithfolder = '';
	if($editwith)
	{
		$editwithfolder = MYBB_ROOT."inc/languages/".$editwith."/";
	}
	if(!file_exists($folder) || ($editwithfolder && !file_exists($editwithfolder)))
	{
		cperror($lang->invalid_set);
	}

	addacpnav($languages[$editlang], "languages.php?".SID."&amp;action=edit&amp;lang=$editlang&amp;editwith=$editwith");

	if(isset($mybb->input['file']))
	{
		// List language variables in specific file

		// Validate input
		$file = basename($mybb->input['file']);
		if($mybb->input['inadmin'] == 1)
		{
			$file = 'admin/'.$file;
		}
		$editfile = $folder.$file;
		$withfile = '';
		$editwithfile = '';
		if($editwithfolder)
		{
			$editwithfile = $editwithfolder.$file;
		}
		if(!file_exists($editfile) || ($editwithfile && !file_exists($editwithfile)))
		{
			cperror($lang->invalid_file);
		}

		addacpnav(sprintf($lang->nav_editing_file, $file));

		// Get file being edited in an array
		require $editfile;
		if(count($l) > 0)
		{
			$editvars = $l;
		}
		else
		{
			$editvars = array();
		}
		unset($l);

		$withvars = array();
		// Get edit with file in an array
		if($editwithfile)
		{
			require $editwithfile;
			$withvars = $l;
			unset($l);
		}

		$plugins->run_hooks("admin_languages_edit_edit");

		// Start output
		cpheader();
		startform("languages.php", "edit", "do_edit");

		// Check if file is writable, before allowing submission
		if(!is_writable($editfile))
		{
			$lang->update_button = '';
			makewarning($lang->note_cannot_write);
		}

		starttable();
		if($editwithfile)
		{
			// Editing with another file
			$lang->editing_file_in_set_with = sprintf($lang->editing_file_in_set_with, $file, $languages[$editlang], $languages[$editwith]);
			tableheader($lang->editing_file_in_set_with, "", 1);
			//tablesubheader(array($lang->variable, $languages[$editlang], $languages[$editwith]));
			if(count($editvars) == 0)
			{
				makelabelcode("<div align=\"center\">".$lang->no_variables."</div>", "", 1);
			}
			else
			{
				// Make each editing row
				foreach($editvars as $key => $value)
				{
					if(my_strtolower($langinfo['charset']) == "utf-8")
					{
						$withvars[$key] = preg_replace("#%u([0-9A-F]{1,4})#ie", "dec_to_utf8(hexdec('$1'));", $withvars[$key]);
						$value = preg_replace("#%u([0-9A-F]{1,4})#ie", "dec_to_utf8(hexdec('$1'));", $value);
					}
					else
					{
						$withvars[$key] = preg_replace("#%u([0-9A-F]{1,4})#ie", "dec_to_utf8(hexdec('$1'));", $withvars[$key]);
						$value = preg_replace("#%u([0-9A-F]{1,4})#ie", "'&#'.hexdec('$1').';'", $value);
					}	
					tablesubheader($key, "", 1);
					echo "<tr>\n";
					echo "<td class=\"altbg1\"><strong>".$languages[$editwith]."</strong><br /><textarea style=\"width: 98%; padding: 4px;\" rows=\"2\" disabled=\"disabled\">".htmlspecialchars($withvars[$key])."</textarea></td>\n";
					echo "</tr>";
					echo "<tr>\n";
					echo "<td class=\"altbg1\"><strong>".$languages[$editlang]."</strong><br /><textarea style=\"width: 98%; padding: 4px;\" rows=\"2\" name=\"edit[$key]\">".htmlspecialchars($value)."</textarea></td>\n";
					echo "</tr>";
				}
			}
		}
		else
		{
			// Editing individually
			$lang->editing_file_in_set = sprintf($lang->editing_file_in_set, $file, $languages[$editlang]);
			tableheader($lang->editing_file_in_set, "", 1);
			if(count($editvars) == 0)
			{
				makelabelcode("<div align=\"center\">".$lang->no_variables."</div>", "", 1);
			}
			else
			{
				// Make each editing row
				foreach($editvars as $key => $value)
				{
					if(my_strtolower($langinfo['charset']) == "utf-8")
					{
						$value = preg_replace("#%u([0-9A-F]{1,4})#ie", "dec_to_utf8(hexdec('$1'));", $value);
					}
					else
					{
						$value = preg_replace("#%u([0-9A-F]{1,4})#ie", "'&#'.hexdec('$1').';'", $value);
					}
					tablesubheader($key, "", 1);
					echo "<tr>\n";
					echo "<td class=\"altbg1\"><textarea style=\"width: 98%; padding: 4px;\" rows=\"2\" name=\"edit[$key]\">".htmlspecialchars($value)."</textarea></td>\n";
					echo "</tr>";
				}
			}
		}
		if(md5($mybb->input['debugmode']) == "0100e895f975e14f4193538dac4d0dc7")
		{
			tablesubheader($lang->new_variables, "", 3);
			// Make rows for creating new variables
			for($i = 0; $i < 5; ++$i)
			{
				$bgcolor = getaltbg();
				echo "<tr>\n";
				echo "<td class=\"$bgcolor\"><input type=\"text\" class=\"inputbox\" name=\"newkey[$i]\" value=\"\" size=\"25\" /><br /><textarea style=\"width: 98%; padding: 4px;\" rows=\"2\" name=\"new[$i]\"><textarea></td>\n";
				echo "</tr>";
			}
		}

		endtable();

		makehiddencode("lang", $editlang);
		makehiddencode("editwith", $editwith);
		makehiddencode("inadmin", intval($mybb->input['inadmin']));
		makehiddencode("file", $file);

		endform($lang->update_button, $lang->reset_button);
		cpfooter();
	}
	else
	{
		// List files in specific language
		
		require MYBB_ROOT."inc/languages/".$editlang.".php";

		// Get files in main folder
		$filenames = array();
		if($handle = opendir($folder))
		{
			while(false !== ($file = readdir($handle)))
			{
				if(preg_match("#\.lang\.php$#", $file))
				{
					$filenames[] = $file;
				}
			}
			closedir($handle);
			sort($filenames);
		}
		if($langinfo['admin'] != 0)
		{		
			// Get files in admin folder
			$adminfilenames = array();
			if($handle = opendir($folder."admin"))
			{
				while(false !== ($file = readdir($handle)))
				{
					if(preg_match("#\.lang\.php$#", $file))
					{
						$adminfilenames[] = $file;
					}
				}
				closedir($handle);
				sort($adminfilenames);
			}
			$allfilenames = array_merge($filenames, $adminfilenames);
		}
		else
		{
			$allfilenames = $filenames;
		}
		
		$allfilenames = array_unique($allfilenames);
		asort($allfilenames);

		$plugins->run_hooks("admin_languages_edit_list");
		
		$tablesubheaderarray = array('&nbsp;', $lang->main_folder);
		if($langinfo['admin'] != 0)
		{
			$tablesubheaderarray[] = $lang->admin_folder;
		}

		// Output
		cpheader();
		startform("languages.php", "choose", "edit");
		makehiddencode("lang", $editlang);
		makehiddencode("editwith", $editwith);
		starttable();
		tableheader($lang->choose_file_to_edit, '', count($tablesubheaderarray));
		tablesubheader($tablesubheaderarray);
		foreach($allfilenames as $filename)
		{
			$bgcolor = getaltbg();
			$normal_link = '';
			$admin_link = '';
			if(in_array($filename, $filenames))
			{
				$normal_link = makelinkcode($lang->edit_link, "languages.php?".SID."&amp;action=edit&amp;lang=$editlang&amp;editwith=$editwith&amp;file=$filename");
			}
			if(is_array($adminfilenames) && $langinfo['admin'] != 0)
			{
			  	if(in_array($filename, $adminfilenames))
			  	{
					$admin_link = "<td align=\"center\">".makelinkcode($lang->edit_link, "languages.php?".SID."&amp;action=edit&amp;lang={$editlang}&amp;editwith={$editwith}&amp;file={$config['admindir']}/{$filename}&amp;inadmin=1")."</td>"; 
        		}
        		else
        		{
          			$admin_link = "<td align=\"center\">&nbsp;</td>";
       			}
			}
			echo "<tr class=\"{$bgcolor}\">
	<td>{$filename}</td>
	<td align=\"center\">{$normal_link}</td>
	{$admin_link}
</tr>
";
		}
		endtable();
		endform();
		cpfooter();
	}
}

if(empty($mybb->input['action']))
{
	$plugins->run_hooks("admin_languages_list");

	// List language packs
	cpheader();
	starttable();
	tableheader($lang->installed_langs, "", 3);
	tablesubheader(array($lang->language, $lang->editing_options, $lang->options));
	asort($languages);

	// Make array for language select list
	$langselectlangs[0] = $lang->edit;
	foreach($languages as $key1 => $langname1)
	{
		$langselectlangs[$key1] = sprintf($lang->edit_with, $langname1);
	}

	foreach($languages as $key => $langname)
	{
		$bgcolor = getaltbg();
		require MYBB_ROOT."inc/languages/".$key.".php";

		if(!empty($langinfo['website']))
		{
			$author = "<a href=\"$langinfo[website]\">$langinfo[author]</a>";
		}
		else
		{
			$author = $langinfo['author'];
		}
		
		list($adminkey, $adminvalue) = explode("=", SID);
		// Make edit with language select list
		$langselect = "<form action=\"languages.php\" method=\"get\">";
		$langselect .= "<input type=\"hidden\" name=\"$adminkey\" value=\"$adminvalue\" />";
		$langselect .= "<input type=\"hidden\" name=\"action\" value=\"editset\" />";
		$langselect .= "<input type=\"hidden\" name=\"action\" value=\"edit\" />";
		$langselect .= "<input type=\"hidden\" name=\"lang\" value=\"$key\" />";
		$langselect .= makehopper("editwith", $langselectlangs);
		$langselect .= "</form>";

		// Make other options select list
		$optionlist = array(
			"editset" => $lang->edit_set,
		);
		$options = "<form action=\"languages.php\" method=\"get\">";
		$options .= "<input type=\"hidden\" name=\"$adminkey\" value=\"$adminvalue\" />";
		$options .= "<input type=\"hidden\" name=\"action\" value=\"editset\" />";
		$options .= "<input type=\"hidden\" name=\"lang\" value=\"$key\" />";
		$options .= makehopper("action", $optionlist);
		$options .= "</form>";

		echo "<tr>\n";
		echo "<td class=\"$bgcolor\" valign=\"top\"><strong>$langinfo[name]</strong><br /><span class=\"smalltext\">$author</span></td>\n";
		echo "<td class=\"$bgcolor\" valign=\"top\">$langselect</td>\n";
		echo "<td class=\"$bgcolor\" valign=\"top\">$options</td>\n";
		echo "</tr>\n";

		unset($langinfo);
	}
	endtable();
	cpfooter();
}
?>
