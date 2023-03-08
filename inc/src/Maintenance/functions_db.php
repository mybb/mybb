<?php
/**
 * Functions used for testing and establishing database connections.
 */

declare(strict_types=1);

namespace MyBB\Maintenance;

use DB_Base;
use PDO;
use PDOException;

/**
 * @psalm-pure
 * @return array<string,array{
 *   engine: string,
 *   extension: string,
 *   class: string,
 *   title: string,
 *   short_title: string,
 *   structure_file: string,
 *   default_port?: int,
 * }>
 */
function getDatabaseDriversData(): array
{
    return [
        // non-PDO extensions
        'mysqli' => [
            'driver' => 'mysqli',
            'engine' => 'mysql',
            'extension' => 'mysqli',
            'class' => 'DB_MySQLi',
            'title' => 'MySQL Improved',
            'short_title' => 'MySQLi',
            'structure_file' => 'mysql_db_tables.php',
            'default_port' => 3306,
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'engine' => 'pgsql',
            'extension' => 'pgsql',
            'class' => 'DB_PgSQL',
            'title' => 'PostgreSQL',
            'short_title' => 'PostgreSQL',
            'structure_file' => 'pgsql_db_tables.php',
            'default_port' => 5432,
        ],

        // PDO extension
        'mysql_pdo' => [
            'driver' => 'mysql_pdo',
            'engine' => 'mysql',
            'extension' => 'pdo',
            'class' => 'MysqlPdoDbDriver',
            'title' => 'MySQL',
            'short_title' => 'MySQL',
            'structure_file' => 'mysql_db_tables.php',
            'default_port' => 3306,
        ],
        'pgsql_pdo' => [
            'driver' => 'pgsql_pdo',
            'engine' => 'pgsql',
            'extension' => 'pdo',
            'class' => 'PostgresPdoDbDriver',
            'title' => 'PostgreSQL',
            'short_title' => 'PostgreSQL',
            'structure_file' => 'pgsql_db_tables.php',
            'default_port' => 5432,
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'engine' => 'sqlite',
            'extension' => 'pdo',
            'class' => 'DB_SQLite',
            'title' => 'SQLite 3',
            'short_title' => 'SQLite',
            'structure_file' => 'sqlite_db_tables.php',
        ],
    ];
}

/**
 * @return array<string,array{
 *   engine: string,
 *   extension: string,
 *   class: string,
 *   title: string,
 *   short_title: string,
 *   structure_file: string,
 *   default_port?: int,
 * }>
 */
function getAvailableDatabaseDriversData(bool $legacy = true): array
{
    $availableDriverNames = PDO::getAvailableDrivers();

    return array_filter(
        getDatabaseDriversData(),
        function (array $driver) use ($legacy, $availableDriverNames) {
            if ($driver['extension'] === 'pdo') {
                return in_array($driver['engine'], $availableDriverNames);
            } elseif ($legacy === true) {
                return extension_loaded($driver['extension']);
            } else {
                return false;
            }
        },
    );
}

function getDatabaseEngineDriverData(string $engine): ?array
{
    $availableDrivers = getAvailableDatabaseDriversData(false);

    $engineDrivers = array_combine(
        array_keys($availableDrivers),
        array_column(
            $availableDrivers,
            'engine',
        ),
    );

    $driverName = array_search($engine, $engineDrivers);

    return $driverName === false ? null : $availableDrivers[$driverName];
}

function getDatabaseSuggestionCredentialSets(): array
{
    $engines = [];

    foreach (getAvailableDatabaseDriversData() as $data) {
        if (isset($data['default_port']) && !in_array($data['engine'], $engines)) {
            $engines[] = $data['engine'];
        }
    }

    if ($engines !== []) {
        // general credentials
        $credentialSets = [
            // server
            ['host' => 'localhost'],
            ['host' => 'db'],
            ['host' => 'database'],
            ['engine' => 'mysql', 'host' => 'mysql'],
            ['engine' => 'pgsql', 'host' => 'postgresql'],
            ['engine' => 'pgsql', 'host' => 'postgres'],
            ['engine' => 'pgsql', 'host' => 'pgsql'],

            // authentication
            ['user' => 'mybb', 'password' => 'mybb'],
            ['user' => 'user', 'password' => 'user'],

            // database
            ['name' => 'db'],
            ['name' => 'database'],
            ['name' => 'mybb'],
            ['name' => 'forum'],
            ['name' => 'user'],
        ];

        // location-dependent
        $values = [
            $_SERVER['SERVER_NAME'] ?? null,
            basename(realpath(MYBB_ROOT)),
        ];

        foreach ($values as $value) {
            if (!empty($value)) {
                $credentialSets[] = ['user' => $value, 'password' => $value];
                $credentialSets[] = ['name' => $value];
            }
        }

        // engines to try for each generic host name
        foreach ($credentialSets as $credentialSet) {
            if (isset($credentialSet['host']) && !isset($credentialSet['engine'])) {
                $credentialSetProduct = [];

                foreach ($engines as $engine) {
                    $credentialSetProduct[] = array_merge($credentialSet, [
                        'engine' => $engine,
                    ]);
                }

                array_splice($credentialSets, array_search($credentialSet, $credentialSets), 1, $credentialSetProduct);
            }
        }

        // existing configuration
        $config = getConfigurationFileData();

        if (
            $config !== null &&
            isset($config['database']['type'])
            && array_key_exists($config['database']['type'], getAvailableDatabaseDriversData())
        ) {
            array_unshift($credentialSets, array_filter([
                'engine' => getAvailableDatabaseDriversData()[$config['database']['type']]['engine'],
                'host' => $config['database']['hostname'] ?? null,
                'user' => $config['database']['username'] ?? null,
                'password' => $config['database']['password'] ?? null,
                'name' => $config['database']['database'] ?? null,
            ]));
        }

        return $credentialSets;
    } else {
        return [];
    }
}

/**
 * @psalm-pure
 * @return array{
 *   name: string,
 *   port: int|null,
 * }
 */
function getDatabaseHostData(string $host): array
{
    $hostSplit = explode(':', $host, 2);

    $data = [
        'name' => $hostSplit[0],
        'port' => isset($hostSplit[1]) ? (int)$hostSplit[1] : null,
    ];

    return $data;
}

/**
 * @param array{
 *   engine?: string,
 *   host?: string,
 *   port?: string,
 *   user?: string,
 *   password?: string,
 *   name?: string,
 *   path?: string,
 *   table_prefix?: string,
 * } $parameters
 */
function getDsn(array $parameters): ?string
{
    $dsn = null;

    if (isset($parameters['engine'])) {
        $driverData = getDatabaseEngineDriverData($parameters['engine']);

        if ($driverData !== null) {
            if (isset($driverData['default_port']) && isset($parameters['host']) && $parameters['host'] !== '') {
                // client-server DBMS

                $host = getDatabaseHostData($parameters['host']);

                $dsn = $parameters['engine'] . ':host=' . $host['name'] . ';port=' . ($host['port'] ?? $driverData['default_port']);

                if (!empty($parameters['name'])) {
                    $dsn .= ';dbname=' . $parameters['name'];
                }
            } elseif (
                !isset($driverData['default_port']) &&
                !empty($parameters['path'])
            ) {
                // embedded DBMS

                $dsn = $parameters['engine'] . ':' . $parameters['path'];
            }
        }
    }

    return $dsn;
}

/**
 * @param array{
 *   engine?: string,
 *   host?: string,
 *   port?: string,
 *   user?: string,
 *   password?: string,
 *   name?: string,
 *   path?: string,
 *   table_prefix?: string,
 * } $parameters
 * @return array{
 *   checks: array{
 *     engine: bool|null,
 *     server: bool|null,
 *     authentication: bool|null,
 *     database: bool|null,
 *     prefix_tables: int|null,
 *   },
 *   message?: string,
 *   code?: mixed,
 * }
 */
function testDatabaseParameters(array $parameters, float $timeoutSeconds = 5): array
{
    $results = [
        'checks' => [
            'server' => null,
            'authentication' => null,
            'database' => null,
            'prefix_tables' => null,
        ],
    ];

    if (isset($parameters['engine'])) {
        $driverData = getDatabaseEngineDriverData($parameters['engine']);

        if ($driverData !== null) {
            $results['checks']['engine'] = true;

            $dsn = getDsn($parameters);

            if ($dsn !== null) {
                // prevent filename-related issues
                if (
                    empty($parameters['path']) ||
                    preg_match('/^(.*\/)?[\w._-]+[\w]+$/', $parameters['path']) === 1
                ) {
                    // track file created for testing purposes to delete later
                    if (!empty($parameters['path']) && !file_exists($parameters['path'])) {
                        $temporaryFilePath = $parameters['path'];
                    }

                    try {
                        $pdo = new PDO(
                            $dsn,
                            $parameters['user'] ?? null,
                            $parameters['password'] ?? null,
                            [
                                PDO::ATTR_TIMEOUT => max((int)$timeoutSeconds, 1),
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            ]
                        );

                        $results['checks']['server'] = true;
                        $results['checks']['authentication'] = true;

                        if (!empty($parameters['name']) || $parameters['engine'] === 'sqlite') {
                            $results['checks']['database'] = true;
                        }

                        if ($results['checks']['database'] === true && isset($parameters['table_prefix'])) {
                            switch ($parameters['engine']) {
                                case 'sqlite':
                                    $statement = $pdo->prepare(<<<'SQL'
                                        SELECT
                                            COUNT(*) AS n
                                            FROM sqlite_master
                                            WHERE type = 'table' AND name LIKE :name ESCAPE '\'
                                    SQL
                                    );
                                    $statement->execute([
                                        ':name' => addcslashes($parameters['table_prefix'], '\\%_') . '%',
                                    ]);
                                    break;
                                default:
                                    /** @var string $parameters['name'] */

                                    $statement = $pdo->prepare(<<<'SQL'
                                        SELECT
                                            COUNT(*) AS n
                                            FROM information_schema.tables
                                            WHERE (table_catalog = :schema OR table_schema = :schema) AND table_name LIKE :name
                                    SQL
                                    );
                                    $statement->execute([
                                        ':schema' => $parameters['name'],
                                        ':name' => addcslashes($parameters['table_prefix'], '\\%_') . '%',
                                    ]);
                                    break;
                            }

                            $results['checks']['prefix_tables'] = (int)($statement->fetch()['n'] ?? null);
                        }
                    } catch (PDOException $e) {
                        $results['checks'] = array_merge(
                            $results['checks'],
                            getDatabaseTestResultsFromException($e, $parameters),
                        );

                        $results['message'] = $e->getMessage();
                        $results['code'] = $e->getCode();
                    } finally {
                        if (isset($temporaryFilePath)) {
                            unlink($temporaryFilePath);
                        }
                    }
                }
            } elseif (isset($parameters['host'])) {
                $host = getDatabaseHostData($parameters['host']);

                if (isset($driverData['default_port'])) {
                    try {
                        $results['checks']['server'] = @fsockopen(
                            $host['name'],
                            $host['port'] ?? $driverData['default_port'],
                            $_,
                            $_,
                            $timeoutSeconds
                        ) !== false;
                    } catch (\Exception) {
                        $results['checks']['server'] = false;
                    }
                }
            }
        } else {
            $results['checks']['engine'] = false;
        }
    } else {
        $results['checks']['engine'] = false;
    }

    return $results;
}

/**
 * @psalm-pure
 * @return array{
 *   server: bool|null,
 *   authentication: bool|null,
 *   database: bool|null,
 * }
 */
function getDatabaseTestResultsFromException(PDOException $e, array $parameters): array
{
    $results = [
        'server' => null,
        'authentication' => null,
        'database' => null,
    ];

    $message = $e->getMessage();
    $code = $e->getCode();

    if (
        $code === 2002 ||
        str_contains($message, 'could not translate host name') ||
        str_contains($message, 'Connection refused')
    ) {
        $results['server'] = false;
    } elseif (
        in_array($code, [1044, 1045]) ||
        str_contains($message, 'authentication failed') ||
        str_contains($message, 'no password supplied')
    ) {
        $results['server'] = true;

        if (isset($parameters['user']) && isset($parameters['password'])) {
            $results['authentication'] = false;
        }
    } elseif (
        $code === 1049 ||
        str_contains($message, 'FATAL:  database') ||
        str_contains($message, 'unable to open database file')
    ) {
        $results['server'] = true;
        $results['authentication'] = true;
        $results['database'] = false;
    }

    return $results;
}

function getDatabaseHandle(bool $persistent = false): ?DB_Base
{
    if (isset($GLOBALS['db'])) {
        $db = $GLOBALS['db'];
    } else {
        $config = $GLOBALS['config'] ?? getConfigurationFileData();

        if ($config !== null) {
            $db = connectToDatabase($config);

            if ($persistent === true) {
                $GLOBALS['db'] = $db;
            }
        } else {
            $db = null;
        }
    }

    return $db;
}

/**
 * @psalm-assert-if-true !null $db
 * @psalm-pure
 */
function databaseHandleActive(?DB_Base $db): bool
{
    if ($db === null) {
        return false;
    }

    return ($db->current_link ?? $db->db) == true;
}
