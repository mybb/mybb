<?php
/**
 * MyBB 1.6 English Language Pack
 * Copyright 2010 MyBB Group, All Rights Reserved
 * 
 * $Id: tools_recount_rebuild.lang.php 5500 2011-07-19 08:58:09Z Tomm $
 */

$l['recount_rebuild'] = "Recount &amp; Rebuild";
$l['recount_rebuild_desc'] = "Here you can recount &amp; rebuild data to fix any synchronization errors in your forum.";

$l['data_per_page'] = "Data Entries Per Page";
$l['recount_stats'] = "Recount Statistics";
$l['recount_stats_desc'] = "This will recount and update your forum statistics on the forum index and statistics pages.";
$l['rebuild_forum_counters'] = "Rebuild Forum Counters";
$l['rebuild_forum_counters_desc'] = "When this is run, the post/thread counters and last post of each forum will be updated to reflect the correct values.";
$l['rebuild_thread_counters'] = "Rebuild Thread Counters";
$l['rebuild_thread_counters_desc'] = "When this is run, the post/view counters and last post of each thread will be updated to reflect the correct values.";
$l['recount_user_posts'] = "Recount User Post Counts";
$l['recount_user_posts_desc'] = "When this is run, the post count for each user will be updated to reflect its current live value based on the posts in the database, and forums that have post count disabled.";
$l['rebuild_attachment_thumbs'] = "Rebuild Attachment Thumbnails";
$l['rebuild_attachment_thumbs_desc'] = "This will rebuild attachment thumbnails to ensure they're using the current width and height dimensions and will also rebuild missing thumbnails.";

$l['success_rebuilt_forum_counters'] = "The forum counters have been rebuilt successfully.";
$l['success_rebuilt_thread_counters'] = "The thread counters have been rebuilt successfully.";
$l['success_rebuilt_user_counters'] = "The user posts count have been recounted successfully.";
$l['success_rebuilt_attachment_thumbnails'] = "The attachment thumbnails have been rebuilt successfully.";
$l['success_rebuilt_forum_stats'] = "The forum statistics have been rebuilt successfully.";

$l['confirm_proceed_rebuild'] = "Click \"Proceed\" to continue the recount and rebuild process.";
$l['automatically_redirecting'] = "Automatically Redirecting...";

?>