<?php

/**
 * Language strings used on maintenance pages and in Process interfaces.
 *
 * Strings in this file may need to remain readable after `strip_tags()` is applied
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
$l['upgrade_complete'] = 'Upgrade Complete';

$l['table_population'] = 'Table Population';
$l['theme_installation'] = 'Theme Insertion';
$l['create_admin'] = 'Create Administrator Account';

$l['already_installed'] = "MyBB is already installed";
$l['mybb_already_installed'] = "<p>Welcome to the installation wizard for MyBB {1}. MyBB has detected it is already configured in this directory.</p>
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
	<p>While there is nothing wrong with this, it is recommended that your upload the contents of the \"Upload\" directory and not the directory itself.<br /><br />For more information please see the <a href=\"https://docs.mybb.com/1.8/install/#uploading-files\" target=\"_blank\" rel=\"noopener\">MyBB Docs</a>.</p>
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
<p><input type="checkbox" name="allow_anonymous_info" value="1" id="allow_anonymous" checked="checked" /> <label for="allow_anonymous"> Send anonymous statistics about your server specifications to the MyBB Group</label> (<a href="https://docs.mybb.com/1.8/install/anonymous-statistics/" style="color: #555;" target="_blank" rel="noopener"><small>What information is sent?</small></a>)</p>';

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
$l['req_step_error_xmlsupport'] = 'MyBB requires PHP to be compiled with support for XML Data Handling. Please see <a href="http://www.php.net/xml" target="_blank" rel="noopener">PHP.net</a> for more information.';
$l['req_step_error_configdefaultfile'] = 'The configuration file (inc/config.default.php) could not be renamed. Please manually rename the <u>config.default.php</u> file to <u>config.php</u> to allow it to be written to or contact <a href="https://mybb.com/support" target="_blank" rel="noopener">MyBB Support.</a>';
$l['req_step_error_configfile'] = 'The configuration file (inc/config.php) is not writable. Please adjust the <a href="https://docs.mybb.com/1.8/administration/security/file-permissions" target="_blank" rel="noopener">chmod</a> permissions to allow it to be written to.';
$l['req_step_error_settingsfile'] = 'The settings file (inc/settings.php) is not writable. Please adjust the <a href="https://docs.mybb.com/1.8/administration/security/file-permissions" target="_blank" rel="noopener">chmod</a> permissions to allow it to be written to.';
$l['req_step_error_cachedir'] = 'The cache directory (cache/) is not writable. Please adjust the <a href="https://docs.mybb.com/1.8/administration/security/file-permissions" target="_blank" rel="noopener">chmod</a> permissions to allow it to be written to.';
$l['req_step_error_uploaddir'] = 'The uploads directory (uploads/) is not writable. Please adjust the <a href="https://docs.mybb.com/1.8/administration/security/file-permissions" target="_blank" rel="noopener">chmod</a> permissions to allow it to be written to.';
$l['req_step_error_avatardir'] = 'The avatars directory (uploads/avatars/) is not writable. Please adjust the <a href="https://docs.mybb.com/1.8/administration/security/file-permissions" target="_blank" rel="noopener">chmod</a> permissions to allow it to be written to.';
$l['req_step_error_cssddir'] = 'The css directory (css/) is not writable. Please adjust the <a href="https://docs.mybb.com/1.8/administration/security/file-permissions" target="_blank" rel="noopener">chmod</a> permissions to allow it to be written to.';
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
					<th colspan="2" class="first last">Cookie settings <a title="What\'s this?" target="_blank" rel="noopener" href="https://docs.mybb.com/1.8/development/cookies">(?)</a></th>
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
<p>The MyBB Group thanks you for your support in installing our software and we hope to see you around the <a href="https://community.mybb.com/" target="_blank" rel="noopener">Community Forums</a> if you need help or wish to become a part of the MyBB community.</p>';
$l['done_step_locked'] = '<p>Your installer has been locked. To unlock the installer please delete the \'lock\' file in this directory.</p><p>You may now proceed to your new copy of <a href="../index.php">MyBB</a> or its <a href="../admin/index.php">Admin Control Panel</a>.</p>';
$l['done_step_dirdelete'] = '<p><strong><span style="color:red">Please remove this directory before exploring your copy of MyBB.</span></strong></p>';
$l['done_whats_next'] = '<div class="error"><p><strong>Switching from another forum software?</strong></p><p>MyBB offers a merge system for easy merging of multiple forums from various different popular forum software, allowing an easy conversion process to MyBB. If you\'re looking to switch to MyBB, you\'re heading in the right direction! Check out the <a target="_blank" rel="noopener" href="https://mybb.com/download/merge-system">Merge System</a> for more information.</p>';

/* UPGRADE LANGUAGE VARIABLES */
$l['upgrade'] = "Upgrade Process";
$l['upgrade_not_needed'] = '<p>The upgrade process is not needed for this version.</p><p>If you believe this may be an error, you can <a href="upgrade.php?force=1">force the upgrade</a>. Otherwise, please press Next to lock the installer.</p>';
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
$l['upgrade_send_stats'] = "<p><input type=\"checkbox\" name=\"allow_anonymous_info\" value=\"1\" id=\"allow_anonymous\" checked=\"checked\" /> <label for=\"allow_anonymous\"> Send anonymous statistics about your server specifications to the MyBB Group</label> (<a href=\"https://docs.mybb.com/1.8/install/anonymous-statistics/\" style=\"color: #555;\" target=\"_blank\" rel=\"noopener\"><small>What information is sent?</small></a>)</p>";

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
$l['no_theme_functions_file'] = 'No theme functions file has been found. Make sure that all files are uploaded properly.';

#region Process - common
$l['operation_error_title'] = '{1} — Error';
$l['operation_error_message'] = 'The operation failed. Please check error logs for details.';
$l['operation_warning_title'] = '{1} — Warning';

// navigation
$l['retry'] = 'Retry';
$l['retry_operation_confirm'] = 'Retry operation before proceeding?';
$l['retry_parameter_input'] = 'Change parameter values?';
$l['step_finish'] = 'Next';
$l['step_final_finish'] = 'Finish';
$l['waiting_for_operation'] = 'Waiting for {1}…';
$l['waiting_for_operation_hidden'] = 'Waiting…';
$l['redirecting'] = 'Redirecting…';
#endregion

#region CLI
$l['using_env_parameter_value'] = 'Using environment variable <{3}> for {1}';
$l['using_env_parameter_value_revealed'] = 'Using environment variable <{3}> for {1}: "{2}"';

$l['using_default_parameter_value'] = 'Using default parameter value for {1}';
$l['using_default_parameter_value_revealed'] = 'Using default parameter value for {1}: "{2}"';

$l['unknown_operation'] = 'Unknown operation "{1}"';
$l['unknown_parameter'] = 'Unknown process parameter "{1}"';
#endregion

#region fatal errors
$l['locked_title'] = 'Unlock to Continue';
$l['locked'] = 'Please remove the <code>{1}</code> file from the <code>install/</code> directory and try again in order to install or upgrade this MyBB board.';

$l['empty_config_to_reinstall_title'] = 'Before Re-Installing MyBB';
$l['empty_config_to_reinstall'] = '<p>If you are sure you want to overwrite the existing forum and install MyBB again, empty or delete the configuration file <code>inc/config.php</code> and refresh this page.
<br><br>
<p>If you intend to upgrade your forum instead, <a href="./upgrade.php"><strong>click here</strong></a>.';

$l['upgrade_initialization_failed_title'] = 'Initialization Failed';
$l['upgrade_initialization_failed'] = 'To upgrade MyBB, a functioning board is required. If you intend to set up a new board, launch the <a href="./index.php?process=install"><strong>installation process</strong></a> instead.';

$l['upgrade_not_authorized_title'] = 'Authorization Required to Upgrade';
$l['upgrade_not_authorized'] = 'Create a file named <code>{1}</code> in the installation directory and refresh this page.';

$l['upgrade_not_needed_title'] = 'Already Up to Date';
$l['upgrade_not_needed'] = 'Running the upgrade process is not needed for this version.<br><br>If you believe this may be an error, you can <a href="./index.php?process=upgrade&force=1">force the upgrade</a>. Otherwise, you can delete the installation directory.</p>';
#endregion

#region common operations content
// flag listing
$l['flags'] = 'Custom Options';
$l['flag_value'] = '{1}: {2}';
$l['flag_development_mode'] = 'Development mode';
$l['flag_no_discovery'] = 'No database suggestions';
$l['flag_force'] = 'Force update';
$l['flag_fast'] = 'Development mode & use defaults';

// installation state
$l['installation_state'] = 'Current Status';
$l['installation_state_none'] = 'Not installed';
$l['installation_state_none_description'] = 'No installed MyBB board was detected.';
$l['installation_state_configuration_file'] = '<abbr title="inc/config.php">Configuration file</abbr> with no working database connection';
$l['installation_state_configuration_file_description'] = 'A <abbr title="inc/config.php">Configuration file</abbr> exists, but no working database connection can be established.';
$l['installation_state_database_connection'] = '<abbr title="inc/config.php">Configuration file</abbr> with valid database credentials, no data in database';
$l['installation_state_database_connection_description'] = 'A <abbr title="inc/config.php">Configuration file</abbr> with valid database credentials exists, but no data was found in the database.';
$l['installation_state_installed'] = 'Installed version {1} or newer';
#endregion

#region data seeding
$l['board_name_default'] = 'My Board';
$l['welcome_thread_username'] = 'MyBB Installer';
$l['welcome_thread_subject'] = 'Welcome to MyBB';
$l['welcome_thread_message'] = 'Your forum has been successfully installed.

To access MyBB support resources, documentation, troubleshoot problems, or submit feedback, please visit [url=https://mybb.com/support/]mybb.com/support[/url].

We invite you to become a part of the MyBB Community formed by users, webmasters, extension authors, and developers by joining the [url=https://community.mybb.com]Community Forums[/url].

Thanks,
The MyBB Team[color=#ffffff]. [i]Free never tasted so good.[/i][/color]
';
$l['welcome_thread_message_devmode'] = 'MyBB {2} installed at {3} in development mode with [i]{4}[/i] using [i]{5}[/i].

[list]
[*][url={1}?process=install][color=#007AC8][b]Reinstall[/b][/color][/url]
[*][url={1}?process=install&dev][color=#007AC8][b]Reinstall[/b] in development mode[/color][/url]
[*][url={1}?process=install&fast][color=#007AC8][b]Fast Reinstall[/b] (development mode & use defaults)[/color][/url]
[/list]
[list]
[*][url={1}?process=upgrade][color=#218463][b]Upgrade[/b][/color][/url]
[*][url={1}?process=upgrade&dev][color=#218463][b]Upgrade[/b] in development mode[/color][/url]
[*][url={1}?process=upgrade&fast][color=#218463][b]Fast Upgrade[/b] (development mode & use defaults)[/color][/url]
[/list]';
#endregion

#region miscellaneous
$l['task_versioncheck_ran'] = 'The version check task successfully ran.';
$l['na'] = 'N/A';
#endregion

#region Process Model: install
// general
$l['install_page_title'] = 'MyBB Installation';
$l['install_header_title'] = 'Installation';

// steps
$l['install_step_start_title'] = 'Start';
$l['install_step_database_title'] = 'Database';
$l['install_step_settings_title'] = 'Settings';
$l['install_step_account_title'] = 'My Account';

// step pages
$l['install_step_start_heading'] = 'Install New Board';
$l['install_step_start_heading_reinstall'] = 'Re-install Board';
$l['install_step_start_description'] = 'Welcome to the MyBB setup. During this process the forum software will be installed on your server.';
$l['install_step_start_description_reinstall'] = 'Welcome to the MyBB setup. During this process the forum software will be installed again on your server, overwriting the existing board. A new forum will be created — current threads, posts, users and other information will be deleted (<a href="./index.php?process=upgrade"><strong>upgrade instead</strong></a>?).';
$l['install_step_start_description_reinstall_cli'] = 'Welcome to the MyBB setup. During this process the forum software will be installed again on your server, overwriting the existing board. A fresh forum will be created and current threads, posts, users and other information will be deleted.';

$l['install_step_database_heading'] = 'Connect to a Database';
$l['install_step_database_instructions'] = 'Provide connection details for the database where forum content will be stored. If you do not have this information, it can be usually obtained from your web host, or a database can be created in your hosting control panel.';

$l['install_step_settings_heading'] = 'Set Board Settings';
$l['install_step_settings_instructions'] = 'Configure and review basic settings for your new board. These can be easily changed later.';

$l['install_step_account_heading'] = 'Create an Account';
$l['install_step_account_instructions'] = 'Set up login details for your account with administrator permissions.';

// operations
$l['operation_requirements_check_title'] = 'Requirements Check';
$l['operation_file_verification_title'] = 'File Verification';
$l['operation_statistics_title'] = 'Statistics';
$l['operation_configuration_file_title'] = 'Configuration File';
$l['operation_database_structure_title'] = 'Database Structure';
$l['operation_database_population_title'] = 'Database Population';
$l['operation_board_settings_title'] = 'Board Settings';
$l['operation_user_account_title'] = 'User Account';
$l['operation_build_cache_title'] = 'Cache Building';
$l['operation_lock_title'] = 'Installer Lock';

$l['operation_requirements_check_error_title'] = 'Some requirements not met';

// parameters
$l['parameter_send_specifications_title'] = 'Send one-time anonymous statistics with server specifications to improve MyBB <a href="https://docs.mybb.com/1.8/install/anonymous-statistics/" target="_blank" rel="noreferrer" title="More information on MyBB.com" class="information"><i class="fas fa-info-circle"></i></a>';

$l['parameter_db_engine_title'] = 'Database Engine';
$l['parameter_db_host_title'] = 'Database Hostname';
$l['parameter_db_user_title'] = 'Database Username';
$l['parameter_db_password_title'] = 'Database Password';
$l['parameter_db_name_title'] = 'Database Name';
$l['parameter_db_path_title'] = 'Database Path';
$l['parameter_db_table_prefix_title'] = 'Table Prefix';

$l['parameter_bbname_title'] = 'Board Name';
$l['parameter_bbname_value'] = 'Forums';
$l['parameter_bburl_title'] = 'Board URL';
$l['parameter_adminemail_title'] = 'Administrative Email Address';
$l['parameter_adminemail_description'] = 'Used for outgoing emails and the contact form.';
$l['parameter_cookiedomain_title'] = 'Cookie Domain';
$l['parameter_cookiepath_title'] = 'Cookie Path';
$l['parameter_acp_pin_title'] = 'Admin Control Panel PIN';
$l['parameter_acp_pin_description'] = 'An optional code required to enter the ACP, in addition to individual account passwords.';

$l['parameter_account_username_title'] = 'Username';
$l['parameter_account_email_title'] = 'Email Address';
$l['parameter_account_password_title'] = 'Password';

// parameter notes
$l['deferred_default_parameter_note_db_host'] = 'Discovered running server';
$l['deferred_default_parameter_note_db_user'] = 'Discovered accepted credentials';
$l['deferred_default_parameter_note_db_name'] = 'Discovered accessible database';

// parameter feedback
$l['parameter_feedback_settings_bburl_loopback'] = 'Loopback address may be unavailable on other devices';

$l['parameter_feedback_database_check_server_success'] = 'Able to connect';
$l['parameter_feedback_database_check_server_error'] = 'Cannot connect';
$l['parameter_feedback_database_check_authentication_success'] = 'Able to authenticate';
$l['parameter_feedback_database_check_authentication_error'] = 'Not able to authenticate';
$l['parameter_feedback_database_check_database_success'] = 'Able to use database';
$l['parameter_feedback_database_check_database_error'] = 'Not able to use database';
$l['parameter_feedback_database_check_prefix_tables_success'] = 'No existing tables with this prefix';
$l['parameter_feedback_database_check_prefix_tables_warning'] = '{1} existing table(s) with this prefix to overwrite';

// operations content
$l['version_to_be_installed'] = 'Version to be Installed';

$l['php_version_incompatible'] = 'PHP {1} or newer required (currently on version {2})';
$l['no_multi_byte_extensions'] = 'No PHP extensions for multi-byte support available (<code>mbstring</code>, <code>iconv</code>)';
$l['no_database_drivers'] = 'No available database engine drivers (MySQL, PostgreSQL, SQLite)';
$l['no_xml_support'] = 'No PHP extension for XML support available (<code>xml</code>)';
$l['directory_not_writable'] = 'The <code>{1}</code> directory is not writable. Please <a href="https://docs.mybb.com/1.8/administration/security/file-permissions" target="_blank" rel="noreferrer">adjust permissions</a> to allow it to be written to.';
$l['file_not_writable'] = 'The <code>{1}</code> file is not writable. Please <a href="https://docs.mybb.com/1.8/administration/security/file-permissions" target="_blank" rel="noreferrer">adjust permissions</a> to allow it to be written to.';
$l['lock_file_not_writable'] = 'Could not create the lock file <code>{1}</code>. Please remove the install directory before proceeding to your forum.';

$l['file_verification_checksums_missing'] = 'Could not verify files because the <code>{1}</code> file is missing. This file may not be included in development-only versions.';
$l['file_verification_failed'] = 'The following files ({1}) may be corrupted. You should copy them again from the <a href="https://mybb.com/download" target="_blank">original package</a> before proceeding.';
$l['file_verification_changed'] = 'Modified: {1}';
$l['file_verification_missing'] = 'Missing: {1}';

$l['could_not_write_configuration_file'] = 'Could not write data to the configuration file.';
$l['could_not_connect_to_database'] = 'Could not connect to the database using saved parameters.';
$l['database_parameter_check_failed'] = 'Could not connect to the database using provided parameters.';
$l['configuration_file_not_installed'] = 'No installed configuration file found.';
#endregion

#region Process Model: upgrade
// general
$l['upgrade_page_title'] = 'MyBB Upgrade';
$l['upgrade_header_title'] = 'Upgrade';

// steps
$l['upgrade_step_start_title'] = 'Start';
$l['upgrade_step_migration_title'] = 'Migration';
$l['upgrade_step_rebuilding_title'] = 'Rebuilding';

// step pages
$l['upgrade_step_start_heading'] = 'Upgrade Board Software';
$l['upgrade_step_start_description'] = 'Welcome to the upgrade of MyBB. This process will update your board to use the new software version.<br><br>We strongly recommend creating a backup of your database and files and verifying it before the upgrade.';
$l['upgrade_step_start_description_link'] = 'Welcome to the upgrade of MyBB. This process will update your board to use the new software version (<a href="./index.php?process=install"><strong>re-install instead</strong></a>?).<br><br>We strongly recommend creating a backup of your database and files and verifying it before the upgrade.';

$l['upgrade_step_migration_heading'] = 'Migrate Data';
$l['upgrade_step_migration_description'] = 'Applicable upgrade scripts will now be executed.';

$l['upgrade_step_rebuilding_heading'] = 'Rebuild Information';
$l['upgrade_step_rebuilding_description'] = 'Common information and caches will be re-generated.';

// operations
$l['operation_upgrade_plan_title'] = 'Upgrade Planning';
$l['operation_update_settings_title'] = 'Settings Rebuilding';

// parameters
$l['parameter_upgrade_start_title'] = 'Starting Version Number';
$l['parameter_upgrade_start_description'] = 'The selected upgrade, and all upgrades with higher versions, will be applied.';

// operations content
$l['upgrade_version_to_be_installed'] = 'Version After Upgrade';
$l['upgrades_to_apply'] = 'Upgrades to Apply';
$l['upgrade_to_apply'] = '#{1} (from version {2})';
#endregion
