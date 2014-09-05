<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Make sure we can't access this file directly from the browser.
if(!defined("IN_MYBB"))
{
	die("This file cannot be accessed directly.");
}
	
// cache templates - this is important when it comes to performance
// THIS_SCRIPT is defined by some of the MyBB scripts, including index.php
if(THIS_SCRIPT == 'index.php')
{
    global $templatelist;
    if(isset($templatelist))
    {
        $templatelist .= ',';
    }
    $templatelist .= 'hello_index';
}

// Add our hello_index() function to the index_start hook so when that hook is run our function is executed
$plugins->add_hook("index_start", "hello_index");

// Add our hello_post() function to the postbit hook so it gets executed on every post
$plugins->add_hook('postbit', 'hello_post');

// Add our hello_new() function to the misc_start hook so our misc.php?action=hello inserts a new message into the created DB table.
$plugins->add_hook('misc_start', 'hello_new');

function hello_info()
{
	/**
	 * Array of information about the plugin.
	 * name: The name of the plugin
	 * description: Description of what the plugin does
	 * website: The website the plugin is maintained at (Optional)
	 * author: The name of the author of the plugin
	 * authorsite: The URL to the website of the author (Optional)
	 * version: The version number of the plugin
	 * guid: Unique ID issued by the MyBB Mods site for version checking
	 * compatibility: A CSV list of MyBB versions supported. Ex, "121,123", "12*". Wildcards supported.
	 */
	return array(
		"name"			=> "Hello World!",
		"description"	=> "A sample plugin that prepends the messages in each post",
		"website"		=> "http://mybb.com",
		"author"		=> "MyBB Group",
		"authorsite"	=> "http://www.mybb.com",,
		"version"		=> "2.0",
		"compatibility" => "18*"
	);
}

/*
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    "visible" by adding templates/template changes, language changes etc.
*/
function hello_activate()
{
	global $mybb, $db;
	
	// Add a new template (hello_index) to our global templates (sid = -1)
	$templatearray = array(
		"tid" => "NULL",
		"title" => 'hello_index',
		"template" => $db->escape_string('
<br />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<thead>
	<tr>
		<td class="thead">
			<strong>{$lang->hello}</strong>
		</td>
	</tr>
</thead>
<tbody>
	<tr>
		<td class="tcat">
			<form method="POST" action="misc.php">
				<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
				<input type="hidden" name="action" value="hello" />
				{$lang->hello_add_message}: <input type="text" name="message" class="textbox" /> <input type="submit" name="submit" value="{$lang->hello_add}" />
			</form>
		</td>
	</tr>
	<tr>
		<td class="trow1">
		{$messages}
		</td>
	</tr>
</tbody>
</table>
<br />'),
		"sid" => "-1",
	);

	$db->insert_query("templates", $templatearray);
	
	// Include this file because it is where find_replace_templatesets is defined
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	
	// Edit the index template and add our variable to above {$forums}
	find_replace_templatesets('index', '#'.preg_quote('{$forums}').'#', '{$hello}'."\n".'{$forums}');
}

/*
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially "hide" the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
*/
function hello_deactivate()
{
	global $db;
	
	// remove our template
	$db->delete_query('templates', 'title IN (\'hello_index\') AND sid=\'-1\'');
	
	require_once MYBB_ROOT."inc/adminfunctions_templates.php";
	
	// remove edits
	find_replace_templatesets('index', '#'.preg_quote('{$hello}').'#', '');
}

/*
 * _install():
 *   Called whenever a plugin is installed by clicking the "Install" button in the plugin manager.
 *   If no install routine exists, the install button is not shown and it assumed any work will be
 *   performed in the _activate() routine.
*/
function hello_install()
{
	global $db, $lang, $mybb;

	// create settings group
	$insertarray = array(
		'name' => 'hello', 
		'title' => 'Test Plugin', 
		'description' => "Settings for Test Plugin.", 
		'disporder' => 100,
		'isdefault' => 0
	);
	$gid = $db->insert_query("settinggroups", $insertarray);
	
	// add settings
	$setting1 = array(
		"name"			=> "hello_display1",
		"title"			=> "Display Message Index",
		"description"	=> "Set to no if you do not want to display the messages on index.",
		"optionscode"	=> "yesno",
		"value"			=> 1,
		"disporder"		=> 1,
		"gid"			=> $gid
	);
	$db->insert_query("settings", $setting1);
	
	// add settings
	$setting2 = array(
		"name"			=> "hello_display2",
		"title"			=> "Display Message Postbit",
		"description"	=> "Set to no if you do not want to display the messages below every post.",
		"optionscode"	=> "yesno",
		"value"			=> 1,
		"disporder"		=> 2,
		"gid"			=> $gid
	);
	$db->insert_query("settings", $setting2);
	
	// This is required so it updates the settings.php file as well and not only the database - they must be synchronized!
	rebuild_settings();
	
	// Create our entries table
	$collation = $db->build_create_table_collation();
	
	// create table if it doesn't exist already
	if(!$db->table_exists("hello_messages"))
    {
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."hello_messages` (
		  `mid` int(10) UNSIGNED NOT NULL auto_increment,
		  `message` varchar(100) NOT NULL default '',
		  PRIMARY KEY  (`mid`)
			) ENGINE=MyISAM{$collation}");
	}
}

/*
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
*/
function hello_is_installed()
{
	global $db, $mybb;
	
	// If the table exists then it means the plugin is installed because we only drop it on uninstallation
	if($db->table_exists("hello_messages"))
	{
		return true;
	}
		
	return false;
}

/*
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
*/
function hello_uninstall()
{
	global $db, $mybb;
	
	// delete settings group
	$db->delete_query("settinggroups", "name = 'hello'");
	
	// remove settings
	$db->delete_query('settings', 'name IN (\'hello_display1\',\'hello_display2\')');
	
	// This is required so it updates the settings.php file as well and not only the database - they must be synchronized!
	rebuild_settings();
	
	// drop tables
	if($db->table_exists('hello_messages'))
	{
		$db->drop_table('hello_messages');
	}
}

/*
 * Displays the list of messages on index and a form to submit new messages - depending on the setting of course.
*/
function hello_index()
{
	global $mybb, $db, $lang, $templates, $hello, $theme;
	
	// Only run this function is the setting is set to yes
	if($mybb->settings['hello_display1'] == 0)
	{
		return;
	}
	
	// Load our language file
	$lang->load("hello");
	
	// Retreive all messages from the database
	$messages = '';
	$query = $db->simple_select('hello_messages', '*', '', array('order_by' => 'mid', 'order_dir' => 'DESC'));
	while($msg = $db->fetch_array($query))
	{
		// htmlspecialchars_uni is similar to PHP's htmlspecialchars but allows unicode
		$messages .= '<br /> - '.htmlspecialchars_uni($msg['message']);
	}
	
	// If no messages were found, display that notice.
	if(empty($messages))
	{
		$messages = $lang->hello_empty;
	}
	
	// Set $hello as our template and use eval() to do it so we can have our variables parsed
	eval("\$hello = \"".$templates->get('hello_index')."\";");
}

/*
 * Displays the list of messages under every post - depending on the setting.
 * @param $post Array containing information about the current post. Note: must be received by reference otherwise our changes are not preserved.
*/
function hello_post(&$post)
{
	global $db, $mybb, $lang;
	
	// Only run this function is the setting is set to yes
	if($mybb->settings['hello_display2'] == 0)
	{
		return;
	}
	
	// Load our language file
	if(!isset($lang->hello))
	{
		$lang->load("hello");
	}
	
	static $messages;
	
	// Only retreive messages from the database if they were not retreived already
	if(!isset($messages))
	{
		// Retreive all messages from the database
		$messages = '';
		$query = $db->simple_select('hello_messages', '*', '', array('order_by' => 'mid', 'order_dir' => 'DESC'));
		while($msg = $db->fetch_array($query))
		{
			// htmlspecialchars_uni is similar to PHP's htmlspecialchars but allows unicode
			$messages .= '<br /> - '.htmlspecialchars_uni($msg['message']);
		}
		
		// If no messages were found, display that notice.
		if(empty($messages))
		{
			$messages = $lang->hello_empty;
		}
	}
	
	// Alter the current post's message
	$post['message'] .= '<br /><br /><strong>'.$lang->hello.':</strong><br />'.$messages;
}

/*
 * This is where new messages get submitted.
*/
function hello_new()
{
	global $db, $mybb, $lang;
	
	// If we're not running the 'hello' action as specified in our form, get out of there.
	if($mybb->input['action'] != 'hello')
	{
		return;
	}
	
	// Only accept POST
	if($mybb->request_method != "post")
	{
		error();
	}
	
	// Correct post key? This is important to prevent CSRF
	verify_post_check($mybb->input['my_post_key']);
	
	// Load our language file
	if(!isset($lang->hello))
	{
		$lang->load("hello");
	}
	
	// Message cannot be empty
	if(empty($mybb->input['message']))
	{
		error($lang->hello_message_empty);
	}
	
	// Escape input data
	$message = $db->escape_string(trim($mybb->input['message']));
	
	// Insert into database
	$db->insert_query('hello_messages', array('message' => $message));
	
	// Redirect to index.php with a message
	redirect("index.php", $lang->hello_done);
}