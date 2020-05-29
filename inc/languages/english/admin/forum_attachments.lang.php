<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */

// Tabs
$l['attachments'] = "Attachments";
$l['stats'] = "Statistics";
$l['find_attachments'] = "Find Attachments";
$l['find_attachments_desc'] = "Using the attachments search system you can search for specific files users have attached to your forums. Begin by entering some search terms below. All fields are optional and won't be included in the criteria unless they contain a value.";
$l['find_orphans'] = "Find Orphaned Attachments";
$l['find_orphans_desc'] = "Orphaned attachments are attachments which are for some reason missing in the database or the file system. This utility will assist you in locating and removing them.";
$l['attachment_stats'] = "Attachment Statistics";
$l['attachment_stats_desc'] = "Below are some general statistics for the attachments currently on your forum.";

// Errors
$l['error_nothing_selected'] = "Please select one or more attachments to delete.";
$l['error_no_attachments'] = "There aren't any attachments on your forum yet. Once an attachment is posted you'll be able to access this section.";
$l['error_not_all_removed'] = "Only some orphaned attachments were successfully deleted, others could not be removed from the uploads directory.";
$l['error_count'] = 'Unable to remove {1} attachment(s).';
$l['error_invalid_username'] = "The username you entered is invalid.";
$l['error_invalid_forums'] = "One or more forums you selected are invalid.";
$l['error_no_results'] = "No attachments were found with the specified search criteria.";
$l['error_not_found'] = "Attachment file could not be found in the uploads directory.";
$l['error_not_attached'] = "Attachment was uploaded over 24 hours ago but not attached to a post.";
$l['error_does_not_exist'] = "Thread or post for this attachment no longer exists.";

// Success
$l['success_deleted'] = "The selected attachments have been deleted successfully.";
$l['success_orphan_deleted'] = "The selected orphaned attachment(s) have been deleted successfully.";
$l['success_count'] = '{1} attachment(s) removed successfully.';
$l['success_no_orphans'] = "There are no orphaned attachments on your forum.";

// Confirm
$l['confirm_delete'] = "Are you sure you wish to delete the selected attachments?";

// == Pages
// = Stats
$l['general_stats'] = "General Statistics";
$l['stats_attachment_stats'] = "Attachments - Attachment Statistics";
$l['num_uploaded'] = "<strong>No. Uploaded Attachments</strong>";
$l['space_used'] = "<strong>Attachment Space Used</strong>";
$l['bandwidth_used'] = "<strong>Estimated Bandwidth Usage</strong>";
$l['average_size'] = "<strong>Average Attachment Size</strong>";
$l['size'] = "Size";
$l['posted_by'] = "Posted By";
$l['thread'] = "Thread";
$l['downloads'] = "Downloads";
$l['date_uploaded'] = "Date Uploaded";
$l['popular_attachments'] = "Top 5 Most Popular Attachments";
$l['largest_attachments'] = "Top 5 Largest Attachments";
$l['users_diskspace'] = "Top 5 Users Using the Most Disk Space";
$l['username'] = "Username";
$l['total_size'] = "Total Size";

// = Orphans
$l['orphan_results'] = "Orphaned Attachments Search - Results";
$l['orphan_attachments_search'] = "Orphaned Attachments Search";
$l['reason_orphaned'] = "Reason Orphaned";
$l['reason_not_in_table'] = "Not in attachments table";
$l['reason_file_missing'] = "Attached file missing";
$l['reason_thread_deleted'] = "Thread been deleted";
$l['reason_post_never_made'] = "Post never made";
$l['unknown'] = "Unknown";
$l['results'] = "Results";
$l['step1'] = "Step 1";
$l['step2'] = "Step 2";
$l['step1of2'] = "Step 1 of 2 - File System Scan";
$l['step2of2'] = "Step 2 of 2 - Database Scan";
$l['step1of2_line1'] = "Please wait, the file system is currently being scanned for orphaned attachments.";
$l['step2of2_line1'] = "Please wait, the database is currently being scanned for orphaned attachments.";
$l['step_line2'] = "You'll automatically be redirected to the next step once this process is complete.";
$l['scanning'] = 'Scanning&hellip;';

// = Attachments / Index
$l['index_find_attachments'] = "Attachments - Find Attachments";
$l['find_where'] = "Find attachments where&hellip;";
$l['name_contains'] = "File name contains";
$l['name_contains_desc'] = "Search for attachments that include the given query in the file name. For example, enter .zip to find attachments using the .zip file extension.";
$l['type_contains'] = "File type contains";
$l['forum_is'] = "Forum is";
$l['username_is'] = "Posters' username is";
$l['poster_is'] = "Poster is";
$l['poster_is_either'] = "User or Guest";
$l['poster_is_user'] = "Users Only";
$l['poster_is_guest'] = "Guests Only";
$l['more_than'] = "More than";
$l['greater_than'] = "Greater than";
$l['is_exactly'] = "Is exactly";
$l['less_than'] = "Less than";
$l['date_posted_is'] = "Date posted is";
$l['days_ago'] = "days ago";
$l['file_size_is'] = "File size is";
$l['kb'] = "KB";
$l['download_count_is'] = "Download count is";
$l['display_options'] = "Display Options";
$l['filename'] = "File Name";
$l['filesize'] = "File Size";
$l['download_count'] = "Download Count";
$l['post_username'] = "Post Username";
$l['asc'] = "Ascending";
$l['desc'] = "Descending";
$l['sort_results_by'] = "Sort results by";
$l['results_per_page'] = "Results per page";
$l['in'] = "in";

// Buttons
$l['button_delete_orphans'] = "Delete Checked Orphans";
$l['button_delete_attachments'] = "Delete Checked Attachments";
$l['button_find_attachments'] = "Find Attachments";
