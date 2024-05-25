<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

@set_time_limit(0);

$page->add_breadcrumb_item($lang->file_verification, "index.php?module=tools-file_verification");

$plugins->run_hooks("admin_tools_file_verification_begin");

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_tools_file_verification_check");

	if($mybb->request_method == "post")
	{
		// User clicked no
		if($mybb->get_input('no'))
		{
			admin_redirect("index.php?module=tools-system_health");
		}

		$page->add_breadcrumb_item($lang->checking, "index.php?module=tools-file_verification");

		$page->output_header($lang->file_verification." - ".$lang->checking);

		require_once MYBB_ROOT.'inc/src/Maintenance/functions_core.php';

		$bad_files = \MyBB\Maintenance\getFileVerificationErrors();

		if($bad_files === null)
		{
			$page->output_inline_error(
				$lang->sprintf(
					$lang->file_verification_checksums_missing,
					'inc/checksums',
				)
			);
		}
		else
		{
			$plugins->run_hooks("admin_tools_file_verification_check_commit_start");

			$table = new Table;
			$table->construct_header($lang->file);
			$table->construct_header($lang->status, array("class" => "align_center", "width" => 100));

			if(array_merge_recursive($bad_files) === [])
			{
				$table->construct_cell($lang->no_corrupt_files_found, array('colspan' => 3));
				$table->construct_row();

				$table->output($lang->file_verification.": ".$lang->no_problems_found);
			}
			else
			{
				foreach($bad_files as $status => $files)
				{
					foreach($files as $relativePath)
					{
						$displayPath = htmlspecialchars_uni(substr($relativePath, 2));

						switch($status)
						{
							case "changed":
								$displayStatus = $lang->changed;
								$color = "#F22B48";
								break;
							case "missing":
								$displayStatus = $lang->missing;
								$color = "#5B5658";
								break;
						}

						$table->construct_cell(
							"<strong><span style=\"color: {$color};\">{$displayPath}</span></strong>"
						);
						$table->construct_cell(
							"<strong><span style=\"color: {$color};\">{$displayStatus}</span></strong>",
							array("class" => "align_center")
						);
						$table->construct_row();
					}
				}

				$table->output($lang->file_verification.": ".$lang->found_problems);
			}
		}

		$page->output_footer();
		exit;
	}

	$page->output_confirm_action("index.php?module=tools-file_verification", $lang->file_verification_message, $lang->file_verification);
}
