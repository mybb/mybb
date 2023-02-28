<?php
/**
 * Functions with high overlap with the application code under normal conditions.
 */

declare(strict_types=1);

namespace MyBB\Maintenance;

use datacache;
use DB_Base;
use Illuminate\Support\Str;
use MyBB;
use MyLanguage;

use function MyBB\app;

function getConfigurationFileData(bool $correctOldFormat = false): ?array
{
    $path = MYBB_ROOT . '/inc/config.php';

    if (file_exists($path)) {
        require $path;

        if (!isset($config) || !is_array($config)) {
            return null;
        }
    } else {
        return null;
    }

    if ($correctOldFormat) {
        return getCorrectedConfigurationFileData($config);
    } else {
        return $config;
    }
}

/**
 * Returns configuration data supplied values that may have been saved in previously supported formats.
 *
 * @psalm-pure
 */
function getCorrectedConfigurationFileData(array $config): array
{
    // changed in MyBB 1.4.0 (upgrade12)
    if (!is_array($config['database'])) {
        $config['database'] = array(
            'type' => $config['dbtype'] ?? null,
            'database' => $config['database'] ?? null,
            'table_prefix' => $config['table_prefix'] ?? null,
            'hostname' => $config['hostname'] ?? null,
            'username' => $config['username'] ?? null,
            'password' => $config['password'] ?? null,
            'encoding' => $config['db_encoding'] ?? null,
        );
    }

    // changed in MyBB 1.4.15 (upgrade17)
    if (in_array($config['database']['type'], ['sqlite3', 'sqlite2'])) {
        $config['database']['type'] = 'sqlite';
    }

    return $config;
}

function getConfigurationFileModificationTime(): ?int
{
    $path = MYBB_ROOT . '/inc/config.php';

    if (file_exists($path)) {
        try {
            $result = filemtime($path);

            if ($result !== false) {
                return $result;
            }
        } catch (\Exception) {
        }
    }

    return null;
}

function connectToDatabase(array $config): ?DB_Base
{
    $path = MYBB_ROOT . "inc/db_{$config['database']['type']}.php";

    if (!file_exists($path)) {
        return null;
    } else {
        require_once $path;

        $db = match ($config['database']['type']) {
            'sqlite' => new \DB_SQLite(),
            'pgsql' => new \DB_PgSQL(),
            'pgsql_pdo' => new \PostgresPdoDbDriver(),
            'mysql_pdo' => new \MysqlPdoDbDriver(),
            default => new \DB_MySQLi(),
        };

        // Connect to Database
        if (!defined('TABLE_PREFIX')) {
            define('TABLE_PREFIX', $config['database']['table_prefix']);
        }

        try {
            $db->connect($config['database']);
            $db->set_table_prefix(TABLE_PREFIX);
            $db->type = $config['database']['type'];
        } catch (\Exception) {
            $db = null;
        }

        return $db;
    }
}

function getCache(): ?datacache
{
    global $cache;

    $db = getDatabaseHandle(true);

    if (databaseHandleActive($db)) {
        if (!isset($cache)) {
            try {
                require_once MYBB_ROOT . 'inc/class_datacache.php';
                $cache = new datacache();
                $cache->cache();
            } catch (\Exception) {
                return null;
            }
        }

        return $cache;
    } else {
        return null;
    }
}

/**
 * Returns the MyBB version stored in the datacache (set during installation and updated during upgrade).
 */
function getDatacacheVersion(): ?string
{
    $cache = getCache();

    if ($cache === null) {
        return null;
    } else {
        try {
            return $cache->read('version', true)['version'] ?? null;
        } catch (\Exception) {
            return null;
        }
    }
}

function getSettings(bool $createCacheFile = false): ?array
{
    $settings = getSettingsFromCache();

    if ($settings === null) {
        $settings = getSettingsFromDatabase();

        if ($settings === null) {
            return null;
        }

        if ($createCacheFile === true) {
            writeSettingsFile($settings);
        }
    }

    return $settings;
}

function getSettingsFromDatabase(): ?array
{
    $db = getDatabaseHandle();

    if ($db !== null) {
        $settings = [];

        $query = $db->simple_select('settings', 'name,value', '', array('order_by' => 'title'));

        while ($row = $db->fetch_array($query)) {
            $settings[ $row['name'] ] = $row['value'];
        }

        return $settings;
    } else {
        return null;
    }
}

function getSettingsFromCache(): ?array
{
    if (file_exists(MYBB_ROOT . 'inc/settings.php')) {
        require_once MYBB_ROOT . 'inc/settings.php';
    }

    if (!isset($settings) || !is_array($settings)) {
        return null;
    } else {
        return $settings;
    }
}

/**
 * @psalm-pure
 */
function getCorrectedSettings(array $settings): array
{
    $settings['wolcutoff'] = $settings['wolcutoffmins'] * 60;
    $settings['bbname_orig'] = $settings['bbname'];
    $settings['bbname'] = strip_tags($settings['bbname']);

    // Fix for people who for some specify a trailing slash on the board URL
    if(str_ends_with($settings['bburl'], "/"))
    {
        $settings['bburl'] = my_substr($settings['bburl'], 0, -1);
    }

    return $settings;
}

function getSettingValue(string $name, ?DB_Base $db = null): ?string
{
    if (InstallationState::get() === InstallationState::INSTALLED) {
        $db = $db ?? getDatabaseHandle();

        if ($db !== null) {
            $query = $db->simple_select('settings', 'value', "name='" . $db->escape_string($name) . "'");

            if ($db->num_rows($query) !== 0) {
                return $db->fetch_field(
                    $query,
                    'value',
                );
            }
        }
    }

    return null;
}

function writeSettingsFile(array $settings): void
{
    $settingsString = '';

    foreach ($settings as $name => $value) {
        $name = addcslashes($name, "\\'");
        $value = addcslashes($value, '\\"$');

        $settingsString .= <<<PHP
        \$settings['{$name}'] = "{$value}";\n
        PHP;
    }

    if (!empty($settingsString)) {
        $settingsString = <<<PHP
        <?php
        /*********************************\
          DO NOT EDIT THIS FILE, PLEASE USE
          THE SETTINGS EDITOR
        \*********************************/

        {$settingsString}

        PHP;

        $file = fopen(MYBB_ROOT . 'inc/settings.php', 'w');
        fwrite($file, $settingsString);
        fclose($file);
    }
}

/**
 * @return array<string,array{
 *   result: bool,
 *   failMessage: string,
 * }>
 */
function getRequirementsCheckResults(): array
{
    $lang = app(MyLanguage::class);

    $results = [];

    $checks = [
        'php_version' => function () use ($lang) {
            $minimumVersion = '8.1';

            return [
                'result' => version_compare(PHP_VERSION, $minimumVersion, '>='),
                'failMessage' => $lang->sprintf(
                    $lang->php_version_incompatible,
                    $minimumVersion,
                    PHP_VERSION,
                ),
            ];
        },
        'database_driver' => function () use ($lang) {
            return [
                'result' => !empty(getAvailableDatabaseDriversData()),
                'failMessage' => $lang->no_database_drivers,
            ];
        },
        'multi_byte_support' => function () use ($lang) {
            return [
                'result' => function_exists('mb_detect_encoding') || function_exists('iconv'),
                'failMessage' => $lang->no_multi_byte_extensions,
            ];
        },
        'xml_support' => function () use ($lang) {
            return [
                'result' => function_exists('xml_parser_create'),
                'failMessage' => $lang->no_xml_support,
            ];
        },
        'config_file_writable' => function () use ($lang) {
            $relativePath = 'inc/config.php';
            $path = MYBB_ROOT . $relativePath;

            if (!file_exists($path)) {
                $result = file_put_contents($path, '') !== false;
            } else {
                $result = true;
            }

            $result = $result && is_writable($path);

            return [
                'result' => $result,
                'failMessage' => $lang->sprintf(
                    $lang->file_not_writable,
                    $relativePath,
                ),
            ];
        },
        'settings_file_writable' => function () use ($lang) {
            $relativePath = 'inc/settings.php';
            $path = MYBB_ROOT . $relativePath;

            if (!file_exists($path)) {
                $result = file_put_contents($path, '');
            } else {
                $result = true;
            }

            $result = $result && is_writable($path);

            return [
                'result' => $result,
                'failMessage' => $lang->sprintf(
                    $lang->file_not_writable,
                    $relativePath,
                ),
            ];
        },
        'cache_directory_writable' => function () use ($lang) {
            $relativePath = 'cache/';

            return [
                'result' => is_writable(MYBB_ROOT . $relativePath),
                'failMessage' => $lang->sprintf(
                    $lang->directory_not_writable,
                    $relativePath,
                ),
            ];
        },
        'upload_directory_writable' => function () use ($lang) {
            $relativePath = 'uploads/';

            return [
                'result' => is_writable(MYBB_ROOT . $relativePath),
                'failMessage' => $lang->sprintf(
                    $lang->directory_not_writable,
                    $relativePath,
                ),
            ];
        },
        'avatar_directory_writable' => function () use ($lang) {
            $relativePath = 'uploads/avatars/';

            return [
                'result' => is_writable(MYBB_ROOT . $relativePath),
                'failMessage' => $lang->sprintf(
                    $lang->directory_not_writable,
                    $relativePath,
                ),
            ];
        },
    ];

    foreach ($checks as $name => $callback) {
        $results[$name] = $callback();
    }

    return $results;
}

/**
 * @return null|array<string,string[]>
 */
function getDeclaredFileChecksums(): ?array
{
    $checksumsFile = MYBB_ROOT . 'inc/checksums';

    if (file_exists($checksumsFile)) {
        $file = file($checksumsFile);

        $declaredFiles = [];

        foreach ($file as $line) {
            $parts = explode(' ', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $declaredChecksum = trim($parts[0]);
            $relativePath = trim($parts[1]);

            if (!isset($declaredFiles[$relativePath])) {
                $declaredFiles[$relativePath] = [];
            }

            $declaredFiles[$relativePath][] = $declaredChecksum;
        }

        return $declaredFiles;
    } else {
        return null;
    }
}

/**
 * @return array{
 *   changed: array{
 *     files: string[],
 *     directories: string[],
 *   },
 *   missing: array{
 *     files: string[],
 *     directories: string[],
 *   },
 * }
 */
function getIgnoredFileVerificationFailures(): array
{
    // static
    $ignore = [
        'changed' => [
            'files' => [],
            'directories' => [],
        ],
        'missing' => [
            'files' => [
                './htaccess.txt',
                './htaccess-nginx.txt',
                './inc/plugins/hello.php',
            ],
            'directories' => [],
        ],
    ];

    // contextual
    if (!defined('IN_INSTALL')) {
        $ignore['missing']['directories'][] = './install/';
    }

    return $ignore;
}

/**
 * Returns a list of file checksum mismatches by type.
 * Files not declared with checksums are ignored.
 *
 * @return null|array{
 *   changed: string[],
 *   missing: string[],
 * }
 */
function getFileVerificationErrors(): ?array
{
    $algorithm = 'sha512';
    $bufferLength = 8192;

    $mybb = app(MyBB::class);

    $fileChecksums = getDeclaredFileChecksums();

    if ($fileChecksums !== null) {
        $results = $interpretedResults = [
            'changed' => [],
            'missing' => [],
        ];

        // calculate & compare checksums
        foreach ($fileChecksums as $relativePath => $declaredChecksums) {

            // adjustments for commonly renamed directories
            if (defined('INSTALL_ROOT') && str_starts_with($relativePath, './install/')) {
                // `install/`
                $relativePath = './' . basename(INSTALL_ROOT) . '/' . substr($relativePath, 10);
            } elseif (isset($mybb->config['admin_dir']) && str_starts_with($relativePath, './admin/')) {
                // `admin/`
                $relativePath = './' . $mybb->config['admin_dir'] . '/' . substr($relativePath, 8);
            }

            $absolutePath = MYBB_ROOT . $relativePath;

            if (file_exists($absolutePath)) {
                $handle = fopen($absolutePath, 'rb');
                $hashingContext = hash_init($algorithm);

                while (!feof($handle)) {
                    hash_update($hashingContext, fread($handle, $bufferLength));
                }

                fclose($handle);

                $localChecksum = hash_final($hashingContext);

                if (!in_array($localChecksum, $declaredChecksums, true)) {
                    $results['changed'][] = $relativePath;
                }
            } else {
                $results['missing'][] = $relativePath;
            }
        }

        // filter results according to list of ignored paths
        $ignoredVerificationFailures = getIgnoredFileVerificationFailures();

        foreach ($results as $type => $relativePaths) {
            $interpretedResults[$type] = array_filter(
                $relativePaths,
                fn($relativePath) => (
                    !in_array($relativePath, $ignoredVerificationFailures[$type]['files']) &&
                    !Str::startsWith($relativePath, $ignoredVerificationFailures[$type]['directories'])
                ),
            );
        }

        return $interpretedResults;
    } else {
        return null;
    }
}

function developmentEnvironment(): bool
{
    return getenv('MYBB_DEV_MODE') === '1';
}

function getLatestVersionDetails(): ?array
{
    $body = fetch_remote_file('https://mybb.com/version_check.json');

    if ($body !== false) {
        $data = json_decode($body, true);

        if (isset($data['mybb'])) {
            return $data['mybb'];
        }
    }

    return null;
}
