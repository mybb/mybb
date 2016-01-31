<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */

/* INSTALL LANGUAGE VARIABLES */
$l['none'] = 'None';
$l['not_installed'] = 'Not Installed';
$l['installed'] = 'Installed';
$l['not_writable'] = 'Not Writable';
$l['writable'] = 'Writable';
$l['done'] = 'done';
$l['next'] = 'Next';
$l['error'] = 'Error';
$l['multi_byte'] = 'Multi-Byte';
$l['recheck'] = 'Recheck';

$l['title'] = "MyBB Installation Wizard";
$l['welcome'] = 'Welcome';
$l['license_agreement'] = 'License Agreement';
$l['req_check'] = 'Requirements Check';
$l['db_config'] = 'Database Configuration';
$l['table_creation'] = 'Table Creation';
$l['data_insertion'] = 'Data Insertion';
$l['theme_install'] = 'Theme Installation';
$l['board_config'] = 'Board Configuration';
$l['admin_user'] = 'Administrator User';
$l['finish_setup'] = 'Finish Setup';

$l['table_population'] = 'Table Population';
$l['theme_installation'] = 'Theme Insertion';
$l['create_admin'] = 'Create Administrator Account';

$l['already_installed'] = "MyBB is already installed";
$l['mybb_already_installed'] = "<p>Welcome to the installation wizard for MyBB {1}. MyBB has detected it is already configured this directory.</p>
<p>Please choose a suitable action below:</p>

<div class=\"border_wrapper upgrade_note\" style=\"padding: 4px;\">
	<h3>Upgrade my existing copy of MyBB to {1} <span style=\"font-size: 80%; color: maroon;\">(Recommended)</span></h3>
	<p>This option will upgrade your current version of MyBB to MyBB {1}.</p>
	<p>You should choose this option when you wish to retain your current forum threads, posts, users and other information.</p>
	<form method=\"post\" action=\"upgrade.php\">
		<div class=\"next_button\"><input type=\"submit\" class=\"submit_button\" value=\"Upgrade to MyBB {1} &raquo;\" /></div>
	</form>
</div>

<div style=\"padding: 4px;\">
	<h3>Install a new copy of MyBB</h3>
	<p>This option will <span style=\"color: red;\">delete any existing forum you may have set up</span> and install a fresh version of MyBB.</p>
	<p>You should choose this option to erase your existing copy of MyBB if you wish to start again.</p>
	<form method=\"post\" action=\"index.php\" onsubmit=\"return confirm('Are you sure you wish to install a fresh copy of MyBB?\\n\\nThis will delete your existing forum. THIS PROCESS CANNOT BE UNDONE.');\">
		<input type=\"hidden\" name=\"action\" value=\"intro\" />
		<div class=\"next_button\"><input type=\"submit\" class=\"submit_button\" value=\"Install MyBB {1} &raquo;\" /></div>
	</form>
</div>";

$l['mybb_incorrect_folder'] = "<div class=\"border_wrapper upgrade_note\" style=\"padding: 4px;\">
	<h3>MyBB has detected that it is running from the \"Upload\" directory.</h3>
	<p>While there is nothing wrong with this, it is recommended that your upload the contents of the \"Upload\" directory and not the directory itself.<br /><br />For more information please see the <a href=\"http://docs.mybb.com/Help-Upload_Directory.html\" target=\"_blank\">MyBB Docs</a>.</p>
</div>";

$l['welcome_step'] = '<p>Welcome to the installation wizard for MyBB {1}. This wizard will install and configure a copy of MyBB on your server.</p>
<p>Now that you\'ve uploaded the MyBB files the database and settings need to be created and imported. Below is an outline of what is going to be completed during installation.</p>
<ul>
	<li>MyBB requirements checked</li>
	<li>Configuration of database engine</li>
	<li>Creation of database tables</li>
	<li>Default data inserted</li>
	<li>Default themes and templates imported</li>
	<li>Creation of an administrator account to manage your board</li>
	<li>Basic board settings configured</li>
</ul>
<p>After each step has successfully been completed, click Next to move on to the next step.</p>
<p>Click "Next" to view the MyBB license agreement.</p>
<p><input type="checkbox" name="allow_anonymous_info" value="1" id="allow_anonymous" checked="checked" /> <label for="allow_anonymous"> Send anonymous statistics about your server specifications to the MyBB Group</label> (<a href="http://docs.mybb.com/Anonymous_Statistics.html" style="color: #555;" target="_blank"><small>What information is sent?</small></a>)</p>';

$l['license_step'] = '<div class="license_agreement">
{1}
</div>
<p><strong>By clicking Next, you agree to the terms stated in the MyBB License Agreement above.</strong></p>';


$l['req_step_top'] = '<p>Before you can install MyBB, we must check that you meet the minimum requirements for installation.</p>';
$l['req_step_reqtable'] = '<div class="border_wrapper">
			<div class="title">Requirements Check</div>
		<table class="general" cellspacing="0">
		<thead>
			<tr>
				<th colspan="2" class="first last">Requirements</th>
			</tr>
		</thead>
		<tbody>
		<tr class="first">
			<td class="first">PHP Version:</td>
			<td class="last alt_col">{1}</td>
		</tr>
		<tr class="alt_row">
			<td class="first">Supported DB Extensions:</td>
			<td class="last alt_col">{2}</td>
		</tr>
		<tr class="alt_row">
			<td class="first">Supported Translation Extensions:</td>
			<td class="last alt_col">{3}</td>
		</tr>
		<tr class="alt_row">
			<td class="first">PHP XML Extensions:</td>
			<td class="last alt_col">{4}</td>
		</tr>
		<tr class="alt_row">
			<td class="first">Configuration File Writable:</td>
			<td class="last alt_col">{5}</td>
		</tr>
		<tr>
			<td class="first">Settings File Writable:</td>
			<td class="last alt_col">{6}</td>
		</tr>
		<tr>
			<td class="first">Cache Directory Writable:</td>
			<td class="last alt_col">{7}</td>
		</tr>
		<tr class="alt_row">
			<td class="first">File Uploads Directory Writable:</td>
			<td class="last alt_col">{8}</td>
		</tr>
		<tr class="last">
			<td class="first">Avatar Uploads Directory Writable:</td>
			<td class="last alt_col">{9}</td>
		</tr>
		</tbody>
		</table>
		</div>';
$l['req_step_reqcomplete'] = '<p><strong>Congratulations, you meet the requirements to run MyBB.</strong></p>
<p>Click Next to continue with the installation process.</p>';

$l['req_step_span_fail'] = '<span class="fail"><strong>{1}</strong></span>';
$l['req_step_span_pass'] = '<span class="pass">{1}</span>';

$l['req_step_error_box'] = '<p><strong>{1}</strong></p>';
$l['req_step_error_phpversion'] = 'MyBB Requires PHP 5.2.0 or later to run. You currently have {1} installed.';
$l['req_step_error_dboptions'] = 'MyBB requires one or more suitable database extensions to be installed. Your server reported that none were available.';
$l['req_step_error_xmlsupport'] = 'MyBB requires PHP to be compiled with support for XML Data Handling. Please see <a href="http://www.php.net/xml" target="_blank">PHP.net</a> for more information.';
$l['req_step_error_configdefaultfile'] = 'The configuration file (inc/config.default.php) could not be renamed. Please manually rename the <u>config.default.php</u> file to <u>config.php</u> to allow it to be written to or contact <a href="http://www.mybb.com/support" target="_blank">MyBB Support.</a>';
$l['req_step_error_configfile'] = 'The configuration file (inc/config.php) is not writable. Please adjust the <a href="http://docs.mybb.com/CHMOD_Files.html" target="_blank">chmod</a> permissions to allow it to be written to.';
$l['req_step_error_settingsfile'] = 'The settings file (inc/settings.php) is not writable. Please adjust the <a href="http://docs.mybb.com/CHMOD_Files.html" target="_blank">chmod</a> permissions to allow it to be written to.';
$l['req_step_error_cachedir'] = 'The cache directory (cache/) is not writable. Please adjust the <a href="http://docs.mybb.com/CHMOD_Files.html" target="_blank">chmod</a> permissions to allow it to be written to.';
$l['req_step_error_uploaddir'] = 'The uploads directory (uploads/) is not writable. Please adjust the <a href="http://docs.mybb.com/CHMOD_Files.html" target="_blank">chmod</a> permissions to allow it to be written to.';
$l['req_step_error_avatardir'] = 'The avatars directory (uploads/avatars/) is not writable. Please adjust the <a href="http://docs.mybb.com/CHMOD_Files.html" target="_blank">chmod</a> permissions to allow it to be written to.';
$l['req_step_error_cssddir'] = 'The css directory (css/) is not writable. Please adjust the <a href="http://docs.mybb.com/CHMOD_Files.html" target="_blank">chmod</a> permissions to allow it to be written to.';
$l['req_step_error_tablelist'] = '<div class="error">
<h3>Error</h3>
<p>The MyBB Requirements check failed due to the reasons below. MyBB installation cannot continue because you did not meet the MyBB requirements. Please correct the errors below and try again:</p>
{1}
</div>';


$l['db_step_config_db'] = '<p>It is now time to configure the database that MyBB will use as well as your database authentication details. If you do not have this information, it can usually be obtained from your webhost.</p>';
$l['db_step_config_table'] = '<div class="border_wrapper">
<div class="title">Database Configuration</div>
<table class="general" cellspacing="0">
<tr>
	<th colspan="2" class="first last">Database Settings</th>
</tr>
<tr class="first">
	<td class="first"><label for="dbengine">Database Engine:</label></td>
	<td class="last alt_col"><select name="dbengine" id="dbengine" onchange="updateDBSettings();">{1}</select></td>
</tr>
{2}
</table>
</div>
<p>Once you\'ve checked these details are correct, click next to continue.</p>';

$l['database_settings'] = "Database Settings";
$l['database_path'] = "Database Path:";
$l['database_host'] = "Database Server Hostname:";
$l['database_user'] = "Database Username:";
$l['database_pass'] = "Database Password:";
$l['database_name'] = "Database Name:";
$l['table_settings'] = "Table Settings";
$l['table_prefix'] = "Table Prefix:";
$l['table_encoding'] = "Table Encoding:";

$l['db_step_error_config'] = '<div class="error">
<h3>Error</h3>
<p>There seems to be one or more errors with the database configuration information that you supplied:</p>
{1}
<p>Once the above are corrected, continue with the installation.</p>
</div>';
$l['db_step_error_invalidengine'] = 'You have selected an invalid database engine. Please make your selection from the list below.';
$l['db_step_error_noconnect'] = 'Could not connect to the database server at \'{1}\' with the supplied username and password. Are you sure the hostname and user details are correct?';
$l['db_step_error_nodbname'] = 'Could not select the database \'{1}\'. Are you sure it exists and the specified username and password have access to it?';
$l['db_step_error_missingencoding'] = 'You have not selected an encoding yet. Please make sure you selected an encoding before continuing. (Select \'UTF-8 Unicode\' if you are not sure)';
$l['db_step_error_sqlite_invalid_dbname'] = 'You may not use relative URLs for SQLite databases. Please use a file system path (ex: /home/user/database.db) for your SQLite database.';
$l['db_step_error_invalid_tableprefix'] = 'You may only use an underscore (_) and alphanumeric characters in a table prefix. Please use a valid table prefix before continuing.';
$l['db_step_error_tableprefix_too_long'] = 'You may only use a table prefix with a length of 40 characters or less. Please use a shorter table prefix before continuing.';
$l['db_step_error_utf8mb4_error'] = '\'4-Byte UTF-8 Unicode\' requires MySQL 5.5.3 or above. Please select an encoding which is compatible with your MySQL version.';

$l['tablecreate_step_connected'] = '<p>Connection to the database server and database you specified was successful.</p>
<p>Database Engine: {1} {2}</p>
<p>The MyBB database tables will now be created.</p>';
$l['tablecreate_step_created'] = 'Creating table {1}...';
$l['tablecreate_step_done'] = '<p>All tables have been created, click Next to populate them.</p>';

$l['populate_step_insert'] = '<p>Now that the basic tables have been created, it\'s time to insert the default data.</p>';
$l['populate_step_inserted'] = '<p>The default data has successfully been inserted into the database. Click Next to insert the default MyBB template and theme sets.</p>';


$l['theme_step_importing'] = '<p>Loading and importing theme and template file...</p>';
$l['theme_step_imported'] = '<p>The default theme and template sets have been successfully inserted. Click Next to configure the basic options for your board.</p>';


$l['config_step_table'] = '<p>It is now time for you to configure the basic settings for your forums such as forum name, URL, your website details, along with your "cookie" domain and paths. These settings can easily be changed in the future through the MyBB Admin Control Panel.</p>
		<div class="border_wrapper">
			<div class="title">Board Configuration</div>
			<table class="general" cellspacing="0">
				<tbody>
				<tr>
					<th colspan="2" class="first last">Forum Details</th>
				</tr>
				<tr class="first">
					<td class="first"><label for="bbname">Forum Name:</label></td>
					<td class="last alt_col"><input type="text" class="text_input" name="bbname" id="bbname" value="{1}" /></td>
				</tr>
				<tr class="alt_row last">
					<td class="first"><label for="bburl">Forum URL (No trailing slash):</label></td>
					<td class="last alt_col"><input type="text" class="text_input" name="bburl" id="bburl" value="{2}" onkeyup="warnUser(this, \'This option was set automatically. Do not change it if you are not sure about the correct value, otherwise links on your forum may be broken.\')" onchange="warnUser(this, \'This option was set automatically. Do not change it if you are not sure about the correct value, otherwise links on your forum may be broken.\')" /></td>
				</tr>
				<tr>
					<th colspan="2" class="first last">Website Details</th>
				</tr>
				<tr>
					<td class="first"><label for="websitename">Website Name:</label></td>
					<td class="last alt_col"><input type="text" class="text_input" name="websitename" id="websitename" value="{3}" /></td>
				</tr>
				<tr class="alt_row last">
					<td class="first"><label for="websiteurl">Website URL:</label></td>
					<td class="last alt_col"><input type="text" class="text_input" name="websiteurl" id="websiteurl" value="{4}" /></td>
				</tr>
				<tr>
					<th colspan="2" class="first last">Cookie settings <a title="What\'s this?" target="_blank" href="http://docs.mybb.com/Cookie_Settings.html">(?)</a></th>
				</tr>
				<tr>
					<td class="first"><label for="cookiedomain">Cookie Domain:</label></td>
					<td class="last alt_col"><input type="text" class="text_input" name="cookiedomain" id="cookiedomain" value="{5}" onkeyup="warnUser(this, \'This option was set automatically. Do not change it if you are not sure about the correct value, otherwise logging in or out on your forum may be broken.\')" onchange="warnUser(this, \'This option was set automatically. Do not change it if you are not sure about the correct value, otherwise logging in or out on your forum may be broken.\')" /></td>
				</tr>
				<tr class="alt_row last">
					<td class="first"><label for="cookiepath">Cookie Path:</label></td>
					<td class="last alt_col"><input type="text" class="text_input" name="cookiepath" id="cookiepath" value="{6}" onkeyup="warnUser(this, \'This option was set automatically. Do not change it if you are not sure about the correct value, otherwise logging in or out on your forum may be broken.\')" onchange="warnUser(this, \'This option was set automatically. Do not change it if you are not sure about the correct value, otherwise logging in or out on your forum may be broken.\')" /></td>
				</tr>
				<tr>
					<th colspan="2" class="first last">Contact Details</th>
				</tr>
				<tr class="last">
					<td class="first"><label for="contactemail">Contact Email:</label></td>
					<td class="last alt_col"><input type="text" class="text_input" name="contactemail" id="contactemail" value="{7}" /></td>
				</tr>
				<tr>
					<th colspan="2" class="first last">Security Settings</th>
				</tr>
				<tr class="last">
					<td class="first"><label for="acppin">ACP PIN:</label><br />Leave this empty if you don\'t want to set one</td>
					<td class="last alt_col"><input type="password" class="text_input" name="pin" id="acppin" value="" /></td>
				</tr>
				</tbody>
			</table>
		</div>

	<p>Once you\'ve correctly entered the details above and are ready to proceed, click Next.</p>';

$l['config_step_error_config'] = '<div class="error">
<h3>Error</h3>
<p>There seems to be one or more errors with the board configuration you supplied:</p>
{1}
<p>Once the above are corrected, continue with the installation.</p>
</div>';
$l['config_step_error_url'] = 'You did not enter the URL to your forums.';
$l['config_step_error_name'] = 'You did not enter a name for your copy of MyBB.';
$l['config_step_revert'] = 'Click to revert this setting to original value.';


$l['admin_step_setupsettings'] = '<p>Setting up basic board settings...</p>';
$l['admin_step_insertesettings'] = '<p>Inserted {1} settings into {2} groups.</p>
<p>Updating settings with user defined values.</p>';
$l['admin_step_insertedtasks'] = '<p>Inserted {1} scheduled tasks.</p>';
$l['admin_step_insertedviews'] = '<p>Inserted {1} admin views.</p>';
$l['admin_step_createadmin'] ='<p>You need to create an initial administrator account for you to login and manage your copy of MyBB. Please fill in the required fields below to create this account.</p>';
$l['admin_step_admintable'] = '<div class="border_wrapper">
			<div class="title">Administrator Account Details</div>

		<table class="general" cellspacing="0">
		<thead>
		<tr>
			<th colspan="2" class="first last">Account Details</th>
		</tr>
		</thead>
		<tr class="first">
			<td class="first"><label for="adminuser">Username:</label></td>
			<td class="alt_col last"><input type="text" class="text_input" name="adminuser" id="adminuser" value="{1}" /></td>
		</tr>
		<tr class="alt_row">
			<td class="first"><label for="adminpass">Password:</label></td>
			<td class="alt_col last"><input type="password" class="text_input" name="adminpass" id="adminpass" value="" autocomplete="off" onchange="comparePass()" /></td>
		</tr>
		<tr class="last">
			<td class="first"><label for="adminpass2">Retype Password:</label></td>
			<td class="alt_col last"><input type="password" class="text_input" name="adminpass2" id="adminpass2" value="" autocomplete="off" onchange="comparePass()"  /></td>
		</tr>
		<tr>
			<th colspan="2" class="first last">Contact Details</th>
		</tr>
		<tr class="first last">
			<td class="first"><label for="adminemail">Email Address:</label></td>
			<td class="alt_col last"><input type="text" class="text_input" name="adminemail" id="adminemail" value="{2}" /></td>
		</tr>
	</table>
	</div>

	<p>Once you\'ve correctly entered the details above and are ready to proceed, click Next.</p>';

$l['admin_step_error_config'] = '<div class="error">
<h3>Error</h3>
<p>There seems to be one or more errors with the board configuration you supplied:</p>
{1}
<p>Once the above are corrected, continue with the installation.</p>
</div>';
$l['admin_step_error_nouser'] = 'You did not enter a username for your Administrator account.';
$l['admin_step_error_nopassword'] = 'You did not enter a password for your Administrator account.';
$l['admin_step_error_nomatch'] = 'The passwords you entered do not match.';
$l['admin_step_error_noemail'] = 'You did not enter your email address for the Administrator\'s account.';
$l['admin_step_nomatch'] = 'The retyped password does not match the password from the first input. Please correct it before continuing.';

$l['done_step_usergroupsinserted'] = "<p>Importing user groups...";
$l['done_step_admincreated'] = '<p>Creating Administrator account...';
$l['done_step_adminoptions'] = '<p>Building Administrator permissions...';
$l['done_step_cachebuilding'] = '<p>Building data caches...';
$l['done_step_success'] = '<p class="success">Your copy of MyBB has successfully been installed and configured correctly.</p>
<p>The MyBB Group thanks you for your support in installing our software and we hope to see you around the <a href="http://community.mybb.com/" target="_blank">Community Forums</a> if you need help or wish to become a part of the MyBB community.</p>';
$l['done_step_locked'] = '<p>Your installer has been locked. To unlock the installer please delete the \'lock\' file in this directory.</p><p>You may now proceed to your new copy of <a href="../index.php">MyBB</a> or its <a href="../admin/index.php">Admin Control Panel</a>.</p>';
$l['done_step_dirdelete'] = '<p><strong><span style="color:red">Please remove this directory before exploring your copy of MyBB.</span></strong></p>';
$l['done_whats_next'] = '<div class="error"><p><strong>Switching from another forum software?</strong></p><p>MyBB offers a merge system for easy merging of multiple forums from various different popular forum software, allowing an easy conversion process to MyBB. If you\'re looking to switch to MyBB, you\'re heading in the right direction! Check out the <a target="_blank" href="http://www.mybb.com/downloads/merge-system">Merge System</a> for more information.</p>';

/* UPGRADE LANGUAGE VARIABLES */
$l['upgrade'] = "Upgrade Process";
$l['upgrade_welcome'] = "<p>Welcome to the upgrade wizard for MyBB {1}.</p><p>Before you continue, please make sure you know which version of MyBB you were previously running as you will need to select it below.</p><p><strong>We strongly recommend that you also obtain a complete backup of your database and files before attempting to upgrade</strong> so if something goes wrong you can easily revert back to the previous version.  Also, ensure that your backups are complete before proceeding.</p><p>Make sure you only click Next ONCE on each step of the upgrade process. Pages may take a while to load depending on the size of your forum.</p><p>Once you are ready, please select your old version below and click Next to continue.</p>";
$l['upgrade_templates_reverted'] = 'Templates Reverted';
$l['upgrade_templates_reverted_success'] = "<p>All of the templates have successfully been reverted to the new ones contained in this release. Please press next to continue with the upgrade process.</p>";
$l['upgrade_settings_sync'] = 'Settings Synchronization';
$l['upgrade_settings_sync_success'] = "<p>The board settings have been synchronized with the latest in MyBB.</p><p>{1} new settings inserted along with {2} new setting groups.</p><p>To finalize the upgrade, please click next below to continue.</p>";
$l['upgrade_datacache_building'] = 'Data Cache Building';
$l['upgrade_building_datacache'] = '<p>Building caches...';
$l['upgrade_continue'] = 'Please press next to continue';
$l['upgrade_locked'] = "<p>Your installer has been locked. To unlock the installer please delete the 'lock' file in this directory.</p><p>You may now proceed to your upgraded copy of <a href=\"../index.php\">MyBB</a> or its <a href=\"../{1}/index.php\">Admin Control Panel</a>.</p>";
$l['upgrade_removedir'] = 'Please remove this directory before exploring your upgraded MyBB.';
$l['upgrade_congrats'] = "<p>Congratulations, your copy of MyBB has successfully been updated to {1}.</p>{2}<p><strong>What's Next?</strong></p><ul><li>Please use the 'Find Updated Templates' tool in the Admin CP to find customized templates updated during this upgrade process. Edit them to contain the changes or revert them to originals.</li><li>Ensure that your board is still fully functional.</li></ul>";
$l['upgrade_template_reversion'] = "Template Reversion Warning";
$l['upgrade_template_reversion_success'] = "<p>All necessary database modifications have successfully been made to upgrade your board.</p><p>This upgrade requires all templates to be reverted to the new ones contained in the package so please back up any custom templates you have made before clicking next.";
$l['upgrade_send_stats'] = "<p><input type=\"checkbox\" name=\"allow_anonymous_info\" value=\"1\" id=\"allow_anonymous\" checked=\"checked\" /> <label for=\"allow_anonymous\"> Send anonymous statistics about your server specifications to the MyBB Group</label> (<a href=\"http://docs.mybb.com/Anonymous_Statistics.html\" style=\"color: #555;\" target=\"_blank\"><small>What information is sent?</small></a>)</p>";

$l['please_login'] = "Please Login";
$l['login'] = "Login";
$l['login_desc'] = "Please enter your username and password to begin the upgrade process. You must be a valid forum administrator to perform the upgrade.";
$l['login_username'] = "Username";
$l['login_password'] = "Password";
$l['login_password_desc'] = "Please note that passwords are case sensitive.";

/* Error messages */
$l['development_preview'] = "<div class=\"error\"><h2 class=\"fail\">Warning</h2><p>This version of MyBB is a development preview and is to be used for testing purposes only.</p><p>No official support, other than for plugins and theme development, will be provided for this version. By continuing with this install/upgrade you do so at your own risk.</p></div>";
$l['locked'] = 'The installer is currently locked, please remove \'lock\' from the install directory to continue';
$l['no_permision'] = "You do not have permissions to run this process. You need administrator permissions to be able to run the upgrade procedure.<br /><br />If you need to logout, please click <a href=\"upgrade.php?action=logout&amp;logoutkey={1}\">here</a>. From there you will be able to log in again under your administrator account.";

$l['task_versioncheck_ran'] = "The version check task successfully ran.";

