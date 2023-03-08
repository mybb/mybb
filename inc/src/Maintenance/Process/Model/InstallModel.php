<?php

declare(strict_types=1);

namespace MyBB\Maintenance\Process\Model;

use Exception;
use MyBB;
use MyBB\Maintenance\InstallationState;
use MyBB\Maintenance\Process\Helpers as ProcessHelpers;
use MyBB\Maintenance\Process\Model;
use MyBB\Maintenance\Process\Runtime;
use MyLanguage;

use function MyBB\app;

/**
 * @psalm-import-type PreconditionResult from Model
 * @psalm-import-type OperationCallbackResult from Model
 * @psalm-import-type DefinitionList from Model
 * @psalm-import-type ParameterFeedback from Model
 * @psalm-import-type ParameterValues from Model
 *
 * [type of dynamic properties in \MyLanguage interpreted by Psalm as `mixed` instead of `string`]
 * @psalm-suppress InvalidReturnType
 * @psalm-suppress InvalidReturnStatement
 * @psalm-suppress MoreSpecificReturnType
 * @psalm-suppress LessSpecificReturnStatement
 */
class InstallModel extends Model
{
    use CommonModelTrait;

    private const NAME = 'install';

    public function __construct()
    {
        parent::__construct();

        $this->setName(self::NAME);

        $this->setPrecondition($this->precondition(...));

        $this->addOperations([
            'requirements_check' => [
                'parameters' => [],
                'callback' => $this->requirementsCheckOperation(...),
            ],
            'file_verification' => [
                'parameters' => [],
                'conditions' => [
                    [
                        'type' => 'flagValue',
                        'name' => 'development_mode',
                        'in' => [false],
                    ],
                ],
                'callback' => $this->fileVerificationOperation(...),
            ],
            'statistics' => [
                'parameters' => [
                    'send_specifications' => [
                        'type' => 'checkbox',
                    ],
                ],
                'listed' => false,
                'conditions' => [
                    [
                        'type' => 'interfaceType',
                        'in' => ['http'],
                    ],
                    [
                        'type' => 'flagValue',
                        'name' => 'development_mode',
                        'in' => [false],
                    ],
                ],
                'callback' => $this->statisticsOperation(...),
            ],
            'configuration_file' => [
                'parameters' => [
                    'db_engine' => [
                        'type' => 'select',
                        'options' => fn () => array_column(
                            \MyBB\Maintenance\getAvailableDatabaseDriversData(false),
                            'title',
                            'engine',
                        ),
                        'required' => true,
                        'feedback' => true,
                    ],
                    'db_host' => [
                        'type' => 'text',
                        'conditions' => [
                            [
                                'type' => 'parameterValue',
                                'name' => 'db_engine',
                                'in' => ['mysql', 'pgsql'],
                            ],
                        ],
                        'required' => true,
                        'feedback' => true,
                        'keywords' => ['host', 'hostname'],
                    ],
                    'db_user' => [
                        'type' => 'text',
                        'conditions' => [
                            [
                                'type' => 'parameterValue',
                                'name' => 'db_engine',
                                'in' => ['mysql', 'pgsql'],
                            ],
                        ],
                        'required' => true,
                        'feedback' => true,
                        'keywords' => ['username', 'user', 'u'],
                    ],
                    'db_password' => [
                        'type' => 'password',
                        'autocomplete' => 'off',
                        'passwordRevealed' => function (Runtime $process): bool {
                            return $process->getFlagValue('development_mode') === true;
                        },
                        'conditions' => [
                            [
                                'type' => 'parameterValue',
                                'name' => 'db_engine',
                                'in' => ['mysql', 'pgsql'],
                            ],
                        ],
                        'feedback' => true,
                        'keywords' => ['password', 'pass', 'p'],
                    ],
                    'db_name' => [
                        'type' => 'text',
                        'conditions' => [
                            [
                                'type' => 'parameterValue',
                                'name' => 'db_engine',
                                'in' => ['mysql', 'pgsql'],
                            ],
                        ],
                        'required' => true,
                        'feedback' => true,
                        'keywords' => ['name', 'database', 'db'],
                    ],
                    'db_path' => [
                        'type' => 'text',
                        'conditions' => [
                            [
                                'type' => 'parameterValue',
                                'name' => 'db_engine',
                                'in' => ['sqlite'],
                            ],
                        ],
                        'required' => true,
                        'feedback' => true,
                    ],
                    'db_table_prefix' => [
                        'type' => 'text',
                        'defaultValue' => 'mybb_',
                        'maxlength' => 40,
                        'feedback' => true,
                    ],
                ],
                'callback' => $this->configurationFileOperation(...),
            ],
            'database_structure' => [
                'parameters' => [],
                'callback' => $this->databaseStructureOperation(...),
            ],
            'database_population' => [
                'parameters' => [],
                'callback' => $this->databasePopulationOperation(...),
            ],
            'board_settings' => [
                'parameters' => [
                    'bbname' => [
                        'type' => 'text',
                        'defaultValue' => fn() => \MyBB\Maintenance\getSuggestedBoardName(),
                        'prominent' => true,
                    ],
                    'bburl' => [
                        'type' => 'text',
                        'defaultValue' => fn() => \MyBB\Maintenance\getSuggestedBoardUrl(),
                        'required' => true,
                        'feedback' => true,
                    ],
                    'adminemail' => [
                        'type' => 'email',
                        'defaultValue' => fn() => \MyBB\Maintenance\getSuggestedAdminEmail(),
                        'required' => true,
                    ],
                    'acp_pin' => [
                        'type' => 'password',
                        'passwordScore' => true,
                        'defaultValue' => '',
                    ],
                ],
                'callback' => $this->boardSettingsOperation(...),
            ],
            'user_account' => [
                'parameters' => [
                    'account_username' => [
                        'type' => 'text',
                        'defaultValue' => 'admin',
                        'autocomplete' => 'username',
                        'required' => true,
                    ],
                    'account_email' => [
                        'type' => 'email',
                        'autocomplete' => 'email',
                        'required' => true,
                        'defaultValue' => function (Runtime $process): string {
                            return $process->getFlagValue('development_mode') === true ? 'admin@example.localhost' : '';
                        },
                    ],
                    'account_password' => [
                        'type' => 'password',
                        'passwordScore' => true,
                        'passwordRevealed' => function (Runtime $process): bool {
                            return $process->getFlagValue('development_mode') === true;
                        },
                        'autocomplete' => 'new-password',
                        'required' => true,
                        'defaultValue' => function (Runtime $process): string {
                            return $process->getFlagValue('development_mode') === true ? 'admin' : '';
                        },
                    ],
                ],
                'callback' => $this->userAccountOperation(...),
            ],
            'build_cache' => [
                'parameters' => [],
                'callback' => $this->buildCacheOperation(...),
            ],
            'lock' => [
                'parameters' => [],
                'callback' => $this->lockOperation(...),
            ],
        ]);

        $this->addSteps([
            'start' => [
                'heading' => function (): string {
                    $lang = app(MyLanguage::class);

                    return InstallationState::get() === InstallationState::NONE
                        ? $lang->install_step_start_heading
                        : $lang->install_step_start_heading_reinstall
                    ;
                },
                'description' => function (Runtime $process): string {
                    $lang = app(MyLanguage::class);

                    if (InstallationState::get() === InstallationState::NONE) {
                        return $lang->install_step_start_description;
                    } elseif ($process->getInterfaceType() === 'http') {
                        return $lang->install_step_start_description_reinstall;
                    } else {
                        return $lang->install_step_start_description_reinstall_cli;
                    }
                },
                'definitionList' => $this->startDefinitionList(...),
                'operationNames' => [
                    'requirements_check',
                    'file_verification',
                    'statistics',
                ],
                'submitBlockingOperationNames' => [
                    'requirements_check',
                ],
            ],
            'database' => [
                'operationNames' => [
                    'configuration_file',
                    'database_structure',
                    'database_population',
                ],
                'parameterFeedback' => $this->databaseParameterFeedback(...),
            ],
            'settings' => [
                'operationNames' => [
                    'board_settings',
                ],
                'parameterFeedback' => $this->settingsParameterFeedback(...),
            ],
            'account' => [
                'operationNames' => [
                    'user_account',
                    'build_cache',
                    'lock',
                ],
            ],
        ]);

        $this->addDeferredDefaultParameterValueSources([
            [
                'parameterNames' => [
                    'db_engine',
                    'db_host',
                    'db_user',
                    'db_password',
                    'db_name',
                ],
                'callback' => $this->databaseDeferredDefaultParameterValues(...),
            ],
        ]);

        $this->addFlags([
            'development_mode' => [
                'type' => 'boolean',
                'inputName' => 'dev',
                'defaultValue' => false,
            ],
            'no_discovery' => [
                'type' => 'boolean',
                'inputName' => 'no_discovery',
                'defaultValue' => false,
            ],
            'fast' => [
                'type' => 'boolean',
                'inputName' => 'fast',
                'defaultValue' => false,
                'aliasFor' => [
                    'development_mode' => true,
                ],
                'directives' => [
                    Model::DIRECTIVE_USE_DEFAULTS,
                ],
            ],
        ]);

        $this->setFinishUrl(function (Runtime $process): string {
            $url = $process->getParameterValue('bburl');

            if ($process->getFlagValue('development_mode') === true && $process->getOperationSubset() === null) {
                $url .= '/showthread.php?tid=1&pid=2#pid2'; // post with installation details in the "Welcome" thread
            } else {
                $url .= '/index.php';
            }

            return $url;
        });
    }

    /**
     * @return PreconditionResult
     */
    private static function precondition(Runtime $process, MyLanguage $lang)
    {
        if ($process->getInterfaceType() === 'http' && !\MyBB\Maintenance\developmentEnvironment()) {
            if (\MyBB\Maintenance\lockFileExists(static::NAME)) {
                \MyBB\Maintenance\httpOutputError(
                    $lang->locked_title,
                    $lang->sprintf($lang->locked, 'lock_' . static::NAME),
                );
            }

            if (InstallationState::get() > InstallationState::NONE) {
                $time = \MyBB\Maintenance\getConfigurationFileModificationTime();

                if ($time !== null && (TIME_NOW - $time) > (60 * 15)) {
                    return [
                        'error' => [
                            'title' => $lang->empty_config_to_reinstall_title,
                            'message' => $lang->empty_config_to_reinstall,
                        ],
                    ];
                }
            }
        }

        return true;
    }

    /**
     * @return OperationCallbackResult
     */
    private static function requirementsCheckOperation(): array
    {
        $errors = array_filter(\MyBB\Maintenance\getRequirementsCheckResults(), function (array $data) {
            return $data['result'] === false;
        });

        if ($errors) {
            $result = [
                'error' => [
                    'message' => '',
                    'list' => array_column($errors, 'failMessage'),
                ],
                'retry' => true,
            ];
        } else {
            $result = [];
        }

        return $result;
    }

    /**
     * @return OperationCallbackResult
     */
    private static function statisticsOperation(MyBB $mybb): array
    {
        require_once MYBB_ROOT . 'inc/functions_serverstats.php';

        build_server_stats(1, '', $mybb->version_code);

        return [];
    }

    /**
     * @return OperationCallbackResult
     */
    private static function configurationFileOperation(Runtime $process, MyLanguage $lang): array
    {
        $testResults = \MyBB\Maintenance\testDatabaseParameters(
            array_map(
                'strval',
                [
                    'engine' => $process->getParameterValue('db_engine'),
                    'host' => $process->getParameterValue('db_host'),
                    'user' => $process->getParameterValue('db_user'),
                    'password' => $process->getParameterValue('db_password'),
                    'name' => $process->getParameterValue('db_name'),
                    'path' => $process->getParameterValue('db_path'),
                    'table_prefix' => $process->getParameterValue('db_table_prefix'),
                ]
            )
        );

        if (
            !in_array(false, $testResults['checks'], true) &&
            !in_array(null, $testResults['checks'], true)
        ) {
            $testConfig = [
                'database' => [
                    'type' => \MyBB\Maintenance\getDatabaseEngineDriverData($process->getParameterValue('db_engine'))['driver'],
                    'database' => $process->getParameterValue('db_engine') === 'sqlite'
                        ? $process->getParameterValue('db_path')
                        : $process->getParameterValue('db_name'),
                    'table_prefix' => $process->getParameterValue('db_table_prefix'),
                    'hostname' => $process->getParameterValue('db_engine') === 'sqlite'
                        ? ''
                        : $process->getParameterValue('db_host'),
                    'username' => $process->getParameterValue('db_engine') === 'sqlite'
                        ? ''
                        : $process->getParameterValue('db_user'),
                    'password' => $process->getParameterValue('db_engine') === 'sqlite'
                        ?  ''
                        : $process->getParameterValue('db_password'),
                ],
            ];

            $db = \MyBB\Maintenance\connectToDatabase($testConfig);

            if ($db) {
                try {
                    \MyBB\Maintenance\writeConfigurationFile($testConfig);

                    $result = [];
                } catch (Exception) {
                    $result = [
                        'error' => [
                            'message' => $lang->could_not_write_configuration_file,
                        ],
                    ];
                }
            } else {
                $result = [
                    'error' => [
                        'message' => $lang->could_not_connect_to_database,
                    ],
                    'retry' => true,
                ];
            }
        } else {
            $result = [
                'error' => [
                    'message' => $lang->database_parameter_check_failed,
                ],
                'retry' => true,
            ];

            if (isset($testResults['code'])) {
                $result['error']['raw'] = $testResults['message'];
            }
        }

        return $result;
    }

    /**
     * @return OperationCallbackResult
     */
    private static function databaseStructureOperation(MyLanguage $lang): array
    {
        try {
            $config = \MyBB\Maintenance\getConfigurationFileData();

            if ($config === null) {
                $result = [
                    'error' => [
                        'message' => $lang->configuration_file_not_installed,
                    ],
                ];
            } else {
                $db = \MyBB\Maintenance\getDatabaseHandle();

                \MyBB\Maintenance\createDatabaseStructure($config, $db);

                $result = [];
            }
        } catch (Exception $e) {
            $result = [
                'error' => [
                    'raw' => $e->getMessage(),
                ],
                'retry' => true,
            ];
        }

        return $result;
    }

    /**
     * @return OperationCallbackResult
     */
    private static function databasePopulationOperation(): array
    {
        try {
            $config = \MyBB\Maintenance\getConfigurationFileData();
            $db = \MyBB\Maintenance\getDatabaseHandle(true);

            \MyBB\Maintenance\populateDatabase($config, $db);

            $result = [];
        } catch (Exception $e) {
            $result = [
                'error' => [
                    'raw' => $e->getMessage(),
                ],
                'retry' => true,
            ];
        }

        return $result;
    }

    /**
     * @return OperationCallbackResult
     */
    private static function boardSettingsOperation(Runtime $process, MyLanguage $lang): array
    {
        try {
            $db = \MyBB\Maintenance\getDatabaseHandle(true);

            $settingsOverride = [
                'bbname' => $process->getParameterValue('bbname'),
                'bburl' => $process->getParameterValue('bburl'),
                'adminemail' => $process->getParameterValue('adminemail'),
                'cookiedomain' => \MyBB\Maintenance\getCookieDomainByUrl($process->getParameterValue('bburl')),
                'cookiepath' => \MyBB\Maintenance\getCookiePathByUrl($process->getParameterValue('bburl')),
                'cookiesecureflag' => \MyBB\Maintenance\getCookieSecureFlagByUrl($process->getParameterValue('bburl')),
            ];

            if ($lang->language !== $lang->fallbackLanguage) {
                $settingsOverride['bblanguage'] = $lang->language;
            }

            \MyBB\Maintenance\insertSettings($db, $settingsOverride);

            if (!empty($process->getParameterValue('acp_pin'))) {
                \MyBB\Maintenance\writeAcpPin($process->getParameterValue('acp_pin'));
            }

            $result = [];
        } catch (Exception $e) {
            $result = [
                'error' => [
                    'raw' => $e->getMessage(),
                ],
                'retry' => true,
            ];
        }

        return $result;
    }

    /**
     * @return OperationCallbackResult
     */
    private static function userAccountOperation(Runtime $process, MyBB $mybb, MyLanguage $lang): array
    {
        try {
            $db = \MyBB\Maintenance\getDatabaseHandle();

            $mybb->settings = \MyBB\Maintenance\getCorrectedSettings(
                \MyBB\Maintenance\getSettings(true)
            );

            $user = \MyBB\Maintenance\insertUser($db, [
                'username' => $process->getParameterValue('account_username'),
                'password' => $process->getParameterValue('account_password'),
                'email' => $process->getParameterValue('account_email'),
                'regip' => get_ip(),
                'usergroup' => 4,
                'language' => $lang->language === $lang->fallbackLanguage ? '' : $lang->language,
            ]);

            if ($process->getInterfaceType() === 'http') {
                \MyBB\Maintenance\httpAttachUserSession($user);

                $sid = \MyBB\Maintenance\createAcpUserSession($db, $user, [
                    'ip' => get_ip(),
                    'useragent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ]);
                \MyBB\Maintenance\httpAttachAcpUserSession($sid);
            }

            $result = [];
        } catch (Exception $e) {
            $result = [
                'error' => [
                    'raw' => $e->getMessage(),
                ],
                'retry' => true,
            ];
        }

        return $result;
    }

    /**
     * @return OperationCallbackResult
     */
    private static function buildCacheOperation(Runtime $process, MyBB $mybb): array
    {
        try {
            $config = \MyBB\Maintenance\getConfigurationFileData();
            $db = \MyBB\Maintenance\getDatabaseHandle(true);

            $mybb->settings = \MyBB\Maintenance\getCorrectedSettings(
                \MyBB\Maintenance\getSettings(true)
            );

            \MyBB\Maintenance\buildDatacache($db);
            \MyBB\Maintenance\createInitialContent($config, $db, $process->getFlagValue('development_mode'));

            $result = [];
        } catch (Exception $e) {
            $result = [
                'error' => [
                    'raw' => $e->getMessage(),
                ],
                'retry' => true,
            ];
        }

        return $result;
    }

    /**
     * @return DefinitionList
     */
    private static function startDefinitionList(Runtime $process, MyBB $mybb, MyLanguage $lang): array
    {
        $list = [];

        // installation state
        if (InstallationState::get() !== InstallationState::NONE) {
            $list[] = [
                'title' => $lang->installation_state,
                'value' => InstallationState::getDescription($lang),
            ];
        }

        // version
        $list[] = [
            'name' => 'version',
            'title' => $lang->version_to_be_installed,
            'value' => htmlspecialchars_uni($mybb->version),
        ];

        // flag values
        $values = ProcessHelpers::getNonDefaultFlagValueDescriptions($process, $lang);

        if ($values !== []) {
            $list[] = [
                'title' => $lang->flags,
                'value' => $values,
            ];
        }

        return $list;
    }

    /**
     * @return ParameterFeedback
     */
    private static function databaseParameterFeedback(array $parameterValues): array
    {
        $lang = app(MyLanguage::class);

        $result = [];

        $testResults = \MyBB\Maintenance\testDatabaseParameters(
            array_map(
                'strval',
                array_filter(
                    [
                        'engine' => $parameterValues['db_engine'] ?? null,
                        'host' => $parameterValues['db_host'] ?? null,
                        'user' => $parameterValues['db_user'] ?? null,
                        'password' => $parameterValues['db_password'] ?? null,
                        'name' => $parameterValues['db_name'] ?? null,
                        'path' => $parameterValues['db_path'] ?? null,
                        'table_prefix' => $parameterValues['db_table_prefix'] ?? null,
                    ],
                )
            )
        );

        $checks = [
            'server' => [
                'fieldNames' => ['db_host'],
                'successValue' => true,
                'typeOnFailure' => 'error',
            ],
            'authentication' => [
                'fieldNames' => ['db_user', 'db_password'],
                'successValue' => true,
                'typeOnFailure' => 'error',
            ],
            'database' => [
                'fieldNames' => ['db_name', 'db_path'],
                'successValue' => true,
                'typeOnFailure' => 'error',
            ],
            'prefix_tables' => [
                'fieldNames' => ['db_table_prefix'],
                'successValue' => 0,
                'typeOnFailure' => 'warning',
            ],
        ];

        foreach ($checks as $checkName => $check) {
            if ($testResults['checks'][$checkName] !== null) {
                if ($testResults['checks'][$checkName] === $check['successValue']) {
                    $status = 'success';
                } else {
                    $status = $check['typeOnFailure'];
                }

                foreach ($check['fieldNames'] as $fieldName) {
                    $result[$fieldName] = [
                        'status' => $status,
                        'message' => $lang->sprintf(
                            $lang->{'parameter_feedback_database_check_' . $checkName . '_' . $status},
                            $testResults['checks'][$checkName],
                        ),
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * @return ParameterFeedback
     */
    private static function settingsParameterFeedback(Runtime $process, array $parameterValues): array
    {
        $lang = app(MyLanguage::class);

        $result = [];

        if ($process->getFlagValue('development_mode') === false) {
            $host = parse_url((string)($parameterValues['bburl'] ?? null), PHP_URL_HOST);

            if ($host && \MyBB\Maintenance\hostnameResolvesToLoopbackAddress($host)) {
                $result['bburl'] = [
                    'status' => 'warning',
                    'message' => $lang->parameter_feedback_settings_bburl_loopback,
                ];
            }
        }

        return $result;
    }

    /**
     * @return ParameterValues
     */
    private static function databaseDeferredDefaultParameterValues(Runtime $process): array
    {
        if ($process->getFlagValue('no_discovery') === false) {
            $values = [];

            $processParameterValues = $process->getParameterValues();
            $existingDatabaseParameters = [];

            foreach (
                [
                    'db_engine',
                    'db_host',
                    'db_user',
                    'db_password',
                    'db_name',
                ] as $parameterName
            ) {
                if (
                    array_key_exists($parameterName, $processParameterValues) &&
                    $processParameterValues[$parameterName] !== null
                ) {
                    $internalName = str_replace('db_', '', $parameterName);
                    $existingDatabaseParameters[$internalName] = (string)$processParameterValues[$parameterName];
                }
            }

            $suggestedDatabaseParameters = \MyBB\Maintenance\getSuggestedDatabaseParameters($existingDatabaseParameters);

            foreach ($suggestedDatabaseParameters as $name => $value) {
                $values['db_' . $name] = $value;
            }

            return $values;
        } else {
            return [];
        }
    }
}
