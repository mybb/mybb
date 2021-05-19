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

		$file = explode("\n", @file_get_contents("https://mybb.com/checksums/release_mybb_{$mybb->version_code}.txt"));

		if(strstr($file[0], "<?xml") !== false || empty($file[0]))
		{
			$page->output_inline_error($lang->error_communication);
			$page->output_footer();
			exit;
		}

		// Parser-up our checksum file from the MyBB Server
		foreach($file as $line)
		{
			$parts = explode(" ", $line, 2);
			if(empty($parts[0]) || empty($parts[1]))
			{
				continue;
			}

			if(substr($parts[1], 0, 7) == "./admin")
			{
				$parts[1] = "./{$mybb->config['admin_dir']}".substr($parts[1], 7);
			}

			if(file_exists(MYBB_ROOT."forums.php") && !file_exists(MYBB_ROOT."portal.php"))
			{
				if(trim($parts[1]) == "./index.php")
				{
					$parts[1] = "./forums.php";
				}
				elseif($parts[1] == "./portal.php")
				{
					$parts[1] = "./index.php";
				}
			}

			if(!file_exists(MYBB_ROOT."inc/plugins/hello.php") && $parts[1] == "./inc/plugins/hello.php")
			{
				continue;
			}

			if(!is_dir(MYBB_ROOT."install/") && substr($parts[1], 0, 10) == "./install/")
			{
				continue;
			}

			$checksums[trim($parts[1])][] = $parts[0];
		}

		$bad_files = verify_files();

		$plugins->run_hooks("admin_tools_file_verification_check_commit_start");

		$table = new Table;
		$table->construct_header($lang->file);
		$table->construct_header($lang->status, array("class" => "align_center", "width" => 100));

		foreach($bad_files as $file)
		{
			switch($file['status'])
			{
				case "changed":
					$file['status'] = $lang->changed;
					$color = "#F22B48";
					break;
				case "missing":
					$file['status'] = $lang->missing;
					$color = "#5B5658";
					break;
			}

			$table->construct_cell("<strong><span style=\"color: {$color};\">".htmlspecialchars_uni(substr($file['path'], 2))."</span></strong>");

			$table->construct_cell("<strong><span style=\"color: {$color};\">{$file['status']}</span></strong>", array("class" => "align_center"));
			$table->construct_row();
		}

		$no_errors = false;
		if($table->num_rows() == 0)
		{
			$no_errors = true;
			$table->construct_cell($lang->no_corrupt_files_found, array('colspan' => 3));
			$table->construct_row();
		}

		if($no_errors)
		{
			$table->output($lang->file_verification.": ".$lang->no_problems_found);
		}
		else
		{
			$table->output($lang->file_verification.": ".$lang->found_problems);
		}

		$page->output_footer();
		exit;
	}

	$page->output_confirm_action("index.php?module=tools-file_verification", $lang->file_verification_message, $lang->file_verification);
}
