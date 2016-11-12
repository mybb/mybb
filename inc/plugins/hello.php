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
if(!defined('IN_MYBB'))
{
	die('This file cannot be accessed directly.');
}

// cache templates - this is important when it comes to performance
// THIS_SCRIPT is defined by some of the MyBB scripts, including index.php
if(defined('THIS_SCRIPT'))
{
    global $templatelist;

    if(isset($templatelist))
    {
        $templatelist .= ',';
    }

	if(THIS_SCRIPT== 'index.php')
	{
		$templatelist .= 'hello_index, hello_message';
	}
	elseif(THIS_SCRIPT== 'showthread.php')
	{
		$templatelist .= 'hello_post, hello_message';
	}
}

if(defined('IN_ADMINCP'))
{
	// Add our hello_settings() function to the setting management module to load language strings.
	$plugins->add_hook('admin_config_settings_manage', 'hello_settings');
	$plugins->add_hook('admin_config_settings_change', 'hello_settings');
	$plugins->add_hook('admin_config_settings_start', 'hello_settings');
	// We could hook at 'admin_config_settings_begin' only for simplicity sake.
}
else
{
	// Add our hello_index() function to the index_start hook so when that hook is run our function is executed
	$plugins->add_hook('index_start', 'hello_index');

	// Add our hello_post() function to the postbit hook so it gets executed on every post
	$plugins->add_hook('postbit', 'hello_post');

	// Add our hello_new() function to the misc_start hook so our misc.php?action=hello inserts a new message into the created DB table.
	$plugins->add_hook('misc_start', 'hello_new');
}

function hello_info()
{
	global $lang;
	$lang->load('hello');

	/**
	 * Array of information about the plugin.
	 * name: The name of the plugin
	 * description: Description of what the plugin does
	 * website: The website the plugin is maintained at (Optional)
	 * author: The name of the author of the plugin
	 * authorsite: The URL to the website of the author (Optional)
	 * version: The version number of the plugin
	 * compatibility: A CSV list of MyBB versions supported. Ex, '121,123', '12*'. Wildcards supported.
	 * codename: An unique code name to be used by updated from the official MyBB Mods community.
	 */
	return array(
		'name'			=> 'Hello World!',
		'description'	=> $lang->hello_desc,
		'website'		=> 'https://mybb.com',
		'author'		=> 'MyBB Group',
		'authorsite'	=> 'https://mybb.com',
		'version'		=> '2.0',
		'compatibility'	=> '18*',
		'codename'		=> 'hello'
	);
}

/*
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    'visible' by adding templates/template changes, language changes etc.
*/
function hello_activate()
{
	global $db, $lang;
	$lang->load('hello');

	// Add a new template (hello_index) to our global templates (sid = -1)
	$templatearray = array(
	'index' => '<br />
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
					{$lang->hello_add_message}: <input type="text" name="message" class="textbox" /> <input type="submit" name="submit" class="button" value="{$lang->hello_add}" />
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
<br />',
	'post' => '<br /><br /><strong>{$lang->hello}:</strong><br />{$messages}',
	'message' => '<br /> - {$message}'
	);

	$group = array(
		'prefix' => $db->escape_string('hello'),
		'title' => $db->escape_string('Hello World!')
	);

	// Update or create template group:
	$query = $db->simple_select('templategroups', 'prefix', "prefix='{$group['prefix']}'");

	if($db->fetch_field($query, 'prefix'))
	{
		$db->update_query('templategroups', $group, "prefix='{$group['prefix']}'");
	}
	else
	{
		$db->insert_query('templategroups', $group);
	}

	// Query already existing templates.
	$query = $db->simple_select('templates', 'tid,title,template', "sid=-2 AND (title='{$group['prefix']}' OR title LIKE '{$group['prefix']}=_%' ESCAPE '=')");

	$templates = $duplicates = array();

	while($row = $db->fetch_array($query))
	{
		$title = $row['title'];
		$row['tid'] = (int)$row['tid'];

		if(isset($templates[$title]))
		{
			// PluginLibrary had a bug that caused duplicated templates.
			$duplicates[] = $row['tid'];
			$templates[$title]['template'] = false; // force update later
		}
		else
		{
			$templates[$title] = $row;
		}
	}

	// Delete duplicated master templates, if they exist.
	if($duplicates)
	{
		$db->delete_query('templates', 'tid IN ('.implode(",", $duplicates).')');
	}

	// Update or create templates.
	foreach($templatearray as $name => $code)
	{
		if(strlen($name))
		{
			$name = "hello_{$name}";
		}
		else
		{
			$name = "hello";
		}

		$template = array(
			'title' => $db->escape_string($name),
			'template' => $db->escape_string($code),
			'version' => 1,
			'sid' => -2,
			'dateline' => TIME_NOW
		);

		// Update
		if(isset($templates[$name]))
		{
			if($templates[$name]['template'] !== $code)
			{
				// Update version for custom templates if present
				$db->update_query('templates', array('version' => 0), "title='{$template['title']}'");

				// Update master template
				$db->update_query('templates', $template, "tid={$templates[$name]['tid']}");
			}
		}
		// Create
		else
		{
			$db->insert_query('templates', $template);
		}

		// Remove this template from the earlier queried list.
		unset($templates[$name]);
	}

	// Remove no longer used templates.
	foreach($templates as $name => $row)
	{
		$db->delete_query('templates', "title='{$db->escape_string($name)}'");
	}

	// Settings group array details
	$group = array(
		'name' => 'hello',
		'title' => $db->escape_string($lang->setting_group_hello),
		'description' => $db->escape_string($lang->setting_group_hello_desc),
		'isdefault' => 0
	);

	// Check if the group already exists.
	$query = $db->simple_select('settinggroups', 'gid', "name='hello'");

	if($gid = (int)$db->fetch_field($query, 'gid'))
	{
		// We already have a group. Update title and description.
		$db->update_query('settinggroups', $group, "gid='{$gid}'");
	}
	else
	{
		// We don't have a group. Create one with proper disporder.
		$query = $db->simple_select('settinggroups', 'MAX(disporder) AS disporder');
		$disporder = (int)$db->fetch_field($query, 'disporder');

		$group['disporder'] = ++$disporder;

		$gid = (int)$db->insert_query('settinggroups', $group);
	}

	// Deprecate all the old entries.
	$db->update_query('settings', array('description' => 'HELLODELETEMARKER'), "gid='{$gid}'");

	// add settings
	$settings = array(
	'display1'	=> array(
		'optionscode'	=> 'yesno',
		'value'			=> 1
	),
	'display2'	=> array(
		'optionscode'	=> 'yesno',
		'value'			=> 1
	));

	$disporder = 0;

	// Create and/or update settings.
	foreach($settings as $key => $setting)
	{
		// Prefix all keys with group name.
		$key = "hello_{$key}";

		$lang_var_title = "setting_{$key}";
		$lang_var_description = "setting_{$key}_desc";

		$setting['title'] = $lang->{$lang_var_title};
		$setting['description'] = $lang->{$lang_var_description};

		// Filter valid entries.
		$setting = array_intersect_key($setting,
			array(
				'title' => 0,
				'description' => 0,
				'optionscode' => 0,
				'value' => 0,
		));

		// Escape input values.
		$setting = array_map(array($db, 'escape_string'), $setting);

		// Add missing default values.
		++$disporder;

		$setting = array_merge(
			array('description' => '',
				'optionscode' => 'yesno',
				'value' => 0,
				'disporder' => $disporder),
		$setting);

		$setting['name'] = $db->escape_string($key);
		$setting['gid'] = $gid;

		// Check if the setting already exists.
		$query = $db->simple_select('settings', 'sid', "gid='{$gid}' AND name='{$setting['name']}'");

		if($sid = $db->fetch_field($query, 'sid'))
		{
			// It exists, update it, but keep value intact.
			unset($setting['value']);
			$db->update_query('settings', $setting, "sid='{$sid}'");
		}
		else
		{
			// It doesn't exist, create it.
			$db->insert_query('settings', $setting);
			// Maybe use $db->insert_query_multiple somehow
		}
	}

	// Delete deprecated entries.
	$db->delete_query('settings', "gid='{$gid}' AND description='HELLODELETEMARKER'");

	// This is required so it updates the settings.php file as well and not only the database - they must be synchronized!
	rebuild_settings();

	// Include this file because it is where find_replace_templatesets is defined
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

	// Edit the index template and add our variable to above {$forums}
	find_replace_templatesets('index', '#'.preg_quote('{$forums}').'#', "{\$hello}\n{\$forums}");
}

/*
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially 'hide' the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
*/
function hello_deactivate()
{
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

	// remove template edits
	find_replace_templatesets('index', '#'.preg_quote('{$hello}').'#', '');
}

/*
 * _install():
 *   Called whenever a plugin is installed by clicking the 'Install' button in the plugin manager.
 *   If no install routine exists, the install button is not shown and it assumed any work will be
 *   performed in the _activate() routine.
*/
function hello_install()
{
	global $db;

	// Create our table collation
	$collation = $db->build_create_table_collation();

	// Create table if it doesn't exist already
	if(!$db->table_exists('hello_messages'))
	{
		switch($db->type)
		{
			case "pgsql":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."hello_messages (
					mid serial,
					message varchar(100) NOT NULL default '',
					PRIMARY KEY (mid)
				);");
				break;
			case "sqlite":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."hello_messages (
					mid INTEGER PRIMARY KEY,
					message varchar(100) NOT NULL default ''
				);");
				break;
			default:
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."hello_messages (
					mid int unsigned NOT NULL auto_increment,
					message varchar(100) NOT NULL default '',
					PRIMARY KEY (mid)
				) ENGINE=MyISAM{$collation};");
				break;
		}
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
	global $db;

	// If the table exists then it means the plugin is installed because we only drop it on uninstallation
	return $db->table_exists('hello_messages');
}

/*
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
*/
function hello_uninstall()
{
	global $db, $mybb;

	if($mybb->request_method != 'post')
	{
		global $page, $lang;
		$lang->load('hello');

		$page->output_confirm_action('index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=hello', $lang->hello_uninstall_message, $lang->hello_uninstall);
	}

	// Delete template groups.
	$db->delete_query('templategroups', "prefix='hello'");

	// Delete templates belonging to template groups.
	$db->delete_query('templates', "title='hello' OR title LIKE 'hello_%'");

	// Delete settings group
	$db->delete_query('settinggroups', "name='hello'");

	// Remove the settings
	$db->delete_query('settings', "name IN ('hello_display1','hello_display2')");

	// This is required so it updates the settings.php file as well and not only the database - they must be synchronized!
	rebuild_settings();

	// Drop tables if desired
	if(!isset($mybb->input['no']))
	{
		$db->drop_table('hello_messages');
	}
}

/*
 * Loads the settings language strings.
*/
function hello_settings()
{
	global $lang;

	// Load our language file
	$lang->load('hello');
}

/*
 * Displays the list of messages on index and a form to submit new messages - depending on the setting of course.
*/
function hello_index()
{
	global $mybb;

	// Only run this function is the setting is set to yes
	if($mybb->settings['hello_display1'] == 0)
	{
		return;
	}

	global $db, $lang, $templates, $hello, $theme;

	// Load our language file
	$lang->load('hello');

	// Retreive all messages from the database
	$messages = '';
	$query = $db->simple_select('hello_messages', 'message', '', array('order_by' => 'mid', 'order_dir' => 'DESC'));
	while($message = $db->fetch_field($query, 'message'))
	{
		// htmlspecialchars_uni is similar to PHP's htmlspecialchars but allows unicode
		$message = htmlspecialchars_uni($message);
		$messages .= eval($templates->render('hello_message'));
	}

	// If no messages were found, display that notice.
	if(empty($messages))
	{
		$message = $lang->hello_empty;
		$messages = eval($templates->render('hello_message'));
	}

	// Set $hello as our template and use eval() to do it so we can have our variables parsed
	#eval('$hello = "'.$templates->get('hello_index').'";');
	$hello = eval($templates->render('hello_index'));
}

/*
 * Displays the list of messages under every post - depending on the setting.
 * @param $post Array containing information about the current post. Note: must be received by reference otherwise our changes are not preserved.
*/
function hello_post(&$post)
{
	global $settings;

	// Only run this function is the setting is set to yes
	if($settings['hello_display2'] == 0)
	{
		return;
	}

	global $lang, $templates;

	// Load our language file
	if(!isset($lang->hello))
	{
		$lang->load('hello');
	}

	static $messages;

	// Only retreive messages from the database if they were not retreived already
	if(!isset($messages))
	{
		global $db;

		// Retreive all messages from the database
		$messages = '';
		$query = $db->simple_select('hello_messages', 'message', '', array('order_by' => 'mid', 'order_dir' => 'DESC'));
		while($message = $db->fetch_field($query, 'message'))
		{
			// htmlspecialchars_uni is similar to PHP's htmlspecialchars but allows unicode
			$message = htmlspecialchars_uni($message);
			$messages .= eval($templates->render('hello_message'));
		}

		// If no messages were found, display that notice.
		if(empty($messages))
		{
			$message = $lang->hello_empty;
			$messages = eval($templates->render('hello_message'));
		}
	}

	// Alter the current post's message
	$post['message'] .= eval($templates->render('hello_post'));
}

/*
* This is where new messages get submitted.
*/
function hello_new()
{
	global $mybb;

	// If we're not running the 'hello' action as specified in our form, get out of there.
	if($mybb->get_input('action') != 'hello')
	{
		return;
	}

	// Only accept POST
	if($mybb->request_method != 'post')
	{
		error_no_permission();
	}

	global $lang;

	// Correct post key? This is important to prevent CSRF
	verify_post_check($mybb->get_input('my_post_key'));

	// Load our language file
	$lang->load('hello');

	$message = trim($mybb->get_input('message'));

	// Message cannot be empty
	if(!$message || my_strlen($message) > 100)
	{
		error($lang->hello_message_empty);
	}

	global $db;

	// Escape input data
	$message = $db->escape_string($message);

	// Insert into database
	$db->insert_query('hello_messages', array('message' => $message));

	// Redirect to index.php with a message
	redirect('index.php', $lang->hello_done);
}
