<?php
/**
 * Functions used for interpretation of `inc/upgrades/` scripts and numbers.
 */

declare(strict_types=1);

namespace MyBB\Maintenance;

function getLatestInstalledUpgradeNumber(): ?int
{
    $cache = getCache();

    if ($cache === null) {
        return null;
    } else {
        // Figure out which version we last updated from (as of 1.6)
        $version_history = $cache->read('version_history');

        // If array is empty then we must be upgrading to 1.6 since that's when this feature was added
        if (empty($version_history)) {
            $number = 16;
        } else {
            $number = (int)end($version_history);
        }

        return $number;
    }
}

function getNextUpgradeScriptNumber(): ?int
{
    $labeledUpgradeScripts = getLabeledUpgradeScripts();
    $latestInstalledUpgradeNumber = getLatestInstalledUpgradeNumber();

    if (array_key_exists($latestInstalledUpgradeNumber + 1, $labeledUpgradeScripts)) {
        return $latestInstalledUpgradeNumber + 1;
    } else {
        return null;
    }
}

/**
 * @return array<int, string>
 */
function getUpgradeScripts(): array
{
    static $entries = null;

    if ($entries === null) {
        $entries = [];

        $directoryPath = MYBB_ROOT . 'inc/upgrades';

        $dh = opendir($directoryPath);

        while (($file = readdir($dh)) !== false) {
            if (preg_match('#upgrade([0-9]+).php$#i', $file, $match)) {
                $entries[(int)$match[1]] = $file;
            }
        }

        closedir($dh);

        ksort($entries, SORT_NATURAL);
    }

    return $entries;
}

/**
 * Includes upgrade scripts and returns found migration functions and directives.
 */
function loadUpgradeScriptsData(): array
{
    static $upgradeScriptsData = null;

    if ($upgradeScriptsData === null) {
        $upgradeScriptsData = [];

        $applicableUpgradeScriptNumbers = array_keys(getUpgradeScripts());
        $directoryPath = MYBB_ROOT . 'inc/upgrades';

        foreach ($applicableUpgradeScriptNumbers as $upgradeScriptNumber) {
            $parameters = [];

            $upgrade_detail = [];

            $definedFunctionsBefore = get_defined_functions()['user'];

            require $directoryPath . '/' . 'upgrade' . $upgradeScriptNumber . '.php';

            $definedFunctionsAfter = get_defined_functions()['user'];

            $upgradeScriptsData[$upgradeScriptNumber] = [
                'migrationFunctions' => array_filter(
                    array_diff($definedFunctionsAfter, $definedFunctionsBefore),
                    fn($name) => str_starts_with($name, 'upgrade' . $upgradeScriptNumber . '_'),
                ),
                'directives' => $upgrade_detail,
            ];
        }
    }

    return $upgradeScriptsData;
}

function addUpgradeNumberToVersionHistory(int $number): void
{
    $cache = getCache();

    $version_history = $cache->read('version_history');

    $version_history[$number] = $number;

    $cache->update('version_history', $version_history);
}

/**
 * Returns the resulting directives from data of upgrade scripts.
 */
function getResolvedUpgradeDirectives(array $upgradeScriptsData, int $startUpgradeScriptNumber): array
{
    $directives = [
        'parameters' => [],

        // legacy upgrades
        'revert_all_templates' => 0,
        'revert_all_themes' => 0,
        'revert_all_settings' => 0,
        'requires_deactivated_plugins' => 0,
    ];

    $scriptsDirectives = array_filter(
        array_column($upgradeScriptsData, 'directives'),
        fn ($upgradeScriptNumber) => $upgradeScriptNumber >= $startUpgradeScriptNumber,
        ARRAY_FILTER_USE_KEY,
    );

    foreach ($scriptsDirectives as $scriptDirectives) {
        foreach ($scriptDirectives as $key => $value) {
            if ($key === 'parameters') {
                $directives[$key] = array_merge($directives['parameters'], $value);
            } elseif (!isset($directives[$key]) || $value > $directives[$key]) {
                $directives[$key] = $value;
            }
        }
    }

    return $directives;
}

/**
 * @return array<int, string>
 */
function getLabeledUpgradeScripts(): array
{
    static $entries = null;

    if ($entries === null) {
        $entries = [];

        $directoryPath = MYBB_ROOT . 'inc/upgrades';

        $upgradeScripts = getUpgradeScripts();

        foreach ($upgradeScripts as $number => $filename) {
            $fileContent = file_get_contents($directoryPath . '/' . $filename);

            preg_match("#Upgrade Script:(.*)#i", $fileContent, $verinfo);

            if (isset($verinfo[1]) && trim($verinfo[1])) {
                $entries[$number] = $verinfo[1];
            }
        }
    }

    return $entries;
}

/**
 * @return int[]
 */
function getApplicableUpgradeScriptNumbers(int $startingNumber = 0): array
{
    return array_filter(
        array_keys(getUpgradeScripts()),
        fn($number) => $number >= $startingNumber,
    );
}
