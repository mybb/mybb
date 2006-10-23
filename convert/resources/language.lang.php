<?php
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

$l['welcome'] = 'Welcome';
$l['license_agreement'] = 'License Agreement';
$l['db_config'] = 'Database Config';
$l['data_conversion'] = 'Conversion';
$l['finish_setup'] = 'Finish Setup';

$l['welcome_step'] = '<p>Welcome to the conversion wizard for MyBB {1}. This wizard will convert and configure a copy of MyBB on your server from another bulliten board software.</p>
<p>Now that you\'ve uploaded the MyBB conversion files you will need to select which bulliten board software you are trying to convert from at the bottom of the page. Below is an outline of what is going to be completed during installation.</p>
<ul>
	<li>Database Configuration</li>
	<li>License Agreement</li>
	<li>Conversion of database data</li>
</ul>
<p>After each step has successfully been completed, click Next to move on to the next step.</p>
<p>Click "Next" to view the MyBB license agreement.</p>';

$l['license_step'] = '<div class="license_agreement">
{1}
</div>
<p><strong>By clicking Next, you agree to the terms stated in the MyBB License Agreement above.</strong></p>';

$l['db_step_config_db'] = '<p>It is now time to configure the database that MyBB will use to convert from as well as your database authentication details. If you do not have this information, it can usually be obtained from your webhost.</p>';
$l['db_step_config_table'] = '<div class="border_wrapper">
<div class="title">Database Configuration</div>
<table class="general" cellspacing="0">
<tr>
	<th colspan="2" class="first last">Conversion Database Settings</th>
</tr>
<tr class="first">
	<td class="first"><label for="dbboard">Board Software:</label></td>
	<td class="last alt_col"><select name="dbboard" id="dbboard">{1}</select></td>
</tr>
<tr class="first">
	<td class="first"><label for="dbengine">Database Engine:</label></td>
	<td class="last alt_col"><select name="dbengine" id="dbengine">{2}</select></td>
</tr>
<tr class="alt_row">
	<td class="first"><label for="dbhost">Database Host:</label></td>
	<td class="last alt_col"><input type="text" class="text_input" name="dbhost" id="dbhost" value="{3}" /></td>
</tr>
<tr>
	<td class="first"><label for="dbuser">Database Username:</label></td>
	<td class="last alt_col"><input type="text" class="text_input" name="dbuser" id="dbuser" value="{4}" /></td>
</tr>
<tr class="alt_row">
	<td class="first"><label for="dbpass">Database Password:</label></td>
	<td class="last alt_col"><input type="password" class="text_input" name="dbpass" id="dbpass" value="" /></td>
</tr>
<tr class="last">
	<td class="first"><label for="dbname">Database Name:</label></td>
	<td class="last alt_col"><input type="text" class="text_input" name="dbname" id="dbname" value="{5}" /></td>
</tr>
<tr>
	<th colspan="2" class="first last">Table Settings</th>
</tr>
<tr class="last">
	<td class="first"><label for="tableprefix">Table Prefix:</label></td>
	<td class="last alt_col"><input type="text" class="text_input" name="tableprefix" id="tableprefix" value="{6}" /></td>
</tr>
</table>
</div>
<p>Once you\'ve checked these details are correct, click next to continue.</p>';
$l['db_step_error_config'] = '<div class="error">
<h3>Error</h3>
<p>There seems to be one or more errors with the database configuration information that you supplied:</p>
{1}
<p>Once the above are corrected, continue with the installation.</p>
</div>';
$l['db_step_error_invalidengine'] = 'You have selected an invalid database engine. Please make your selection from the list below.';
$l['db_step_error_noconnect'] = 'Could not connect to the database server at \'{1}\' with the supplied username and password. Are you sure the hostname and user details are correct?';
$l['db_step_error_nodbname'] = 'Could not select the database \'{1}\'. Are you sure it exists and the specified username and password have access to it?';

$l['data_step_converting'] = '<p>Converting the data from your previous bulliten board forum...</p>';
$l['data_step_imported'] = '<p>The data from your previous bulliten board forum has een successfully inserted. Click Next to procceed.</p>';

$l['admin_step_createadmin'] ='<p>You need to select your Super Administrator accounts before you can login and manage your copy of MyBB..</p>';
$l['admin_step_admintable'] = '<div class="border_wrapper">
			<div class="title">Administrator Account Details</div>

		<table class="general" cellspacing="0">
		<thead>
		<tr>
			<th colspan="2" class="first last">Super Administrators</th>
		</tr>
		</thead>
		<tr class="first">
			<td class="first"><label for="adminuser">Username:</label></td>
			<td class="alt_col last"><input type="text" class="text_input" name="adminuser" id="adminuser" value="{1}" autocomplete="off" /></td>
		</tr>
		<tr class="alt_row">
			<td class="first"><label for="adminpass">Password:</label></td>
			<td class="alt_col last"><input type="password" class="text_input" name="adminpass" id="adminpass" value="" autocomplete="off"  /></td>
		</tr>
		<tr class="last">
			<td class="first"><label for="adminpass2">Retype Password:</label></td>
			<td class="alt_col last"><input type="password" class="text_input" name="adminpass2" id="adminpass2" value="" autocomplete="off"  /></td>
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


$l['done_step_admincreated'] = '<p>Creating Administrator account...';
$l['done_step_cachebuilding'] = '<p>Building data cache\'s...';
$l['done_step_success'] = '<p class="success">Your copy of MyBB has successfully been installed and configured correctly.</p>
<p>The MyBB Group thanks you for your support in installing our software and we hope to see you around the community forums if you need help or wish to become a part of the MyBB community.</p>';
$l['done_step_locked'] = '<p>Your converter has been locked. To unlock the installer please delete the \'lock\' file in this directory.</p><p>You may now proceed to your new copy of <a href="../index.php">MyBB</a> or its <a href="../admin/index.php">Admin Control Panel</a>.</p>';
$l['done_step_dirdelete'] = '<p><strong><span style="colour:red">Please remove this directory before exploring your copy of MyBB.</span></strong></p>';
$l['done_subscribe_mailing'] = '<div class="error"><p><strong>Make sure you\'re subscribed to the updates mailing list!</strong></p><p>Everytime we release a new version of MyBB, be it a new feature release or security update, we send out a message via our mailing list to alert you of the release.</p><p>This helps keep you up to date with new security releases and ensures you\'re running the latest and greatest version of MyBB!</p><p><a href="http://www.mybboard.com/mailinglist.php">Subscribe to the updates mailing list!</a></p>';

/* Error messages */
$l['locked'] = 'The converter is currently locked, please remove \'lock\' from the install directory to continue';
?>