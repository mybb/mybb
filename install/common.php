<?php

function clone_and_archive_default_theme()
{
	global $output;

	$core_theme_basedir = MYBB_ROOT.'inc/themes/core.default/';
	$board_codename = 'board.default';
	// Create a board theme inheriting from core.default
	$board_dir = MYBB_ROOT."inc/themes/{$board_codename}/";
	if (!file_exists($board_dir)) {
		if (!mkdir($board_dir, 0777, true)) {
			$output->print_error('Failed to create the directory: "'.htmlspecialchars_uni($board_dir).'".');
		} else {
			foreach (['theme.json', 'resources.json'] as $json_file) {
				$source_path = "{$core_theme_basedir}/{$json_file}";
				$dest_path   = "{$board_dir}/{$json_file}";
				if (!copy($source_path, $dest_path)) {
					$output->print_error("Failed to copy '{$source_path}' to '{$dest_path}'.");
				} else if ($json_file == 'theme.json') {
					$theme_properties = read_json_file($dest_path, $err_msg, false);
					if (!$theme_properties && $err_msg) {
						$output->print_error($err_msg);
					} else {
						$theme_properties['codename'] = $board_codename;
						$theme_properties['name'] = 'MyBB Default';
						$theme_properties['parent'] = 'core.default';
						if (!write_json_file($dest_path, $theme_properties)) {
							$output->print_error('Failed to write to the JSON file at "'.$dest_path.'".');
						}
					}
				}
			}
		}
	}

	require_once MYBB_ROOT.'inc/functions_themes.php';
	archive_themelet('core.default', /*$is_plugin_themelet = */false, $err_msg);
	if ($err_msg) {
		$output->print_error($err_msg);
	}
}

