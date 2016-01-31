<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Upgrade Script: 1.8.0
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade31_dbchanges()
{
	global $db, $output;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	$query = $db->simple_select("templategroups", "COUNT(*) as numexists", "prefix='sendthread'");
	if($db->fetch_field($query, "numexists") == 0)
	{
		$db->insert_query("templategroups", array('prefix' => 'sendthread', 'title' => '<lang:group_sendthread>', 'isdefault' => '1'));
	}

	$db->update_query('settings', array('optionscode' => 'numeric'), 'name IN (\'statslimit\', \'maxmultipagelinks\', \'deleteinvites\', \'gziplevel\', \'subforumsindex\', \'showbirthdayspostlimit\', \'threadsperpage\') AND optionscode=\'text\'');
	$db->update_query('settings', array('optionscode' => 'numeric'), 'name IN (\'hottopic\', \'hottopicviews\', \'announcementlimit\', \'postsperpage\', \'threadreadcut\', \'similarityrating\', \'similarlimit\') AND optionscode=\'text\'');
	$db->update_query('settings', array('optionscode' => 'numeric'), 'name IN (\'minnamelength\', \'maxnamelength\', \'minpasswordlength\', \'maxpasswordlength\', \'betweenregstime\', \'maxregsbetweentime\', \'failedcaptchalogincount\') AND optionscode=\'text\'');
	$db->update_query('settings', array('optionscode' => 'numeric'), 'name IN (\'failedlogincount\', \'failedlogintime\', \'regtime\', \'maxsigimages\', \'siglength\', \'avatarsize\', \'customtitlemaxlength\') AND optionscode=\'text\'');
	$db->update_query('settings', array('optionscode' => 'numeric'), 'name IN (\'minmessagelength\', \'maxmessagelength\', \'postfloodsecs\', \'postmergemins\', \'maxpostimages\', \'maxpostvideos\', \'subscribeexcerpt\') AND optionscode=\'text\'');
	$db->update_query('settings', array('optionscode' => 'numeric'), 'name IN (\'wordwrap\', \'maxquotedepth\', \'polloptionlimit\', \'maxpolloptions\', \'polltimelimit\', \'maxattachments\', \'attachthumbh\') AND optionscode=\'text\'');
	$db->update_query('settings', array('optionscode' => 'numeric'), 'name IN (\'attachthumbw\', \'membersperpage\', \'repsperpage\', \'maxreplength\', \'minreplength\', \'maxwarningpoints\', \'pmfloodsecs\') AND optionscode=\'text\'');
	$db->update_query('settings', array('optionscode' => 'numeric'), 'name IN (\'maxpmquotedepth\', \'wolcutoffmins\', \'refreshwol\', \'prunepostcount\', \'dayspruneregistered\', \'dayspruneunactivated\', \'portal_numannouncements\') AND optionscode=\'text\'');
	$db->update_query('settings', array('optionscode' => 'numeric'), 'name IN (\'portal_showdiscussionsnum\', \'searchfloodtime\', \'minsearchword\', \'searchhardlimit\', \'smilieinsertertot\', \'smilieinsertercols\', \'maxloginattempts\') AND optionscode=\'text\'');
	$db->update_query('settings', array('optionscode' => 'numeric'), 'name IN (\'loginattemptstimeout\', \'contact_maxsubjectlength\', \'contact_minmessagelength\', \'contact_maxmessagelength\', \'purgespammerpostlimit\', \'purgespammerbangroup\', \'statscachetime\') AND optionscode=\'text\'');

	// Update help documents
	$query = $db->simple_select('helpdocs', 'document', 'hid=\'3\'');
	$helpdoc = $db->fetch_array($query);
	if(my_strpos($helpdoc['document'], ';key={1}') !== false)
	{
		$helpdoc['document'] = str_replace(';key={1}', ';my_post_key={1}', $helpdoc['document']);
	}
	$db->update_query('helpdocs', array('document' => $db->escape_string($helpdoc['document'])), 'hid=\'3\'');

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("31_done");
}