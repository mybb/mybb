<?php

declare(strict_types=1);

namespace MyBB\Maintenance\Process\Model;

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
 *
 * [type of dynamic properties in \MyLanguage interpreted by Psalm as `mixed` instead of `string`]
 * @psalm-suppress InvalidReturnType
 * @psalm-suppress InvalidReturnStatement
 * @psalm-suppress MoreSpecificReturnType
 * @psalm-suppress LessSpecificReturnStatement
 */
class UpgradeModel extends Model
{
    use CommonModelTrait;

    private const NAME = 'upgrade';

    public function __construct()
    {
        parent::__construct();

        $this->setName(self::NAME);

        $this->setPrecondition($this->precondition(...));

        $this->addOperations([
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
            'upgrade_plan' => [
                'parameters' => [
                    'upgrade_start' => [
                        'type' => 'select',
                        'options' => fn () => array_reverse(\MyBB\Maintenance\getLabeledUpgradeScripts(), true),
                        'defaultValue' => \MyBB\Maintenance\getNextUpgradeScriptNumber(),
                    ],
                ],
                'callback' => $this->upgradePlanOperation(...),
                'listed' => false,
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
            'update_settings' => [
                'parameters' => [],
                'callback' => $this->updateSettingsOperation(...),
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
                'description' => function (Runtime $process): string {
                    $lang = app(MyLanguage::class);

                    if (
                        $process->getInterfaceType() === 'http' &&
                        MyBB\Maintenance\httpRequestFromLocalNetwork()
                    ) {
                        return $lang->upgrade_step_start_description_link;
                    } else {
                        return $lang->upgrade_step_start_description;
                    }
                },
                'definitionList' => $this->startDefinitionList(...),
                'operationNames' => [
                    'file_verification',
                    'upgrade_plan',
                    'statistics',
                ],
                'finishBlockingOperationNames' => [
                    'upgrade_plan',
                ],
            ],
            'migration' => [
                'operationNames' => [],
            ],
            'rebuilding' => [
                'operationNames' => [
                    'update_settings',
                    'build_cache',
                    'lock',
                ],
            ],
        ]);

        $this->addFlags([
            'force' => [
                'type' => 'boolean',
                'inputName' => 'force',
                'defaultValue' => false,
            ],
            'development_mode' => [
                'type' => 'boolean',
                'inputName' => 'dev',
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

        $this->setFinishUrl('../index.php');

        // add migration functions from upgrade scripts as Operations
        $migrationOperations = [];

        foreach (\MyBB\Maintenance\loadUpgradeScriptsData() as $upgradeScriptNumber => $upgradeScript) {
            $i = 0;
            $migrationFunctionNamesCount = count($upgradeScript['migrationFunctions'] ?? []);

            foreach ($upgradeScript['migrationFunctions'] ?? [] as $migrationFunctionName) {
                $migrationOperations[$migrationFunctionName] = [
                    'parameters' => $upgradeScript['directives']['parameters'] ?? [],
                    'conditions' => [
                        [
                            'type' => 'stateVariable',
                            'name' => 'upgrade' . $upgradeScriptNumber,
                            'in' => ['1'],
                        ],
                    ],
                    'callback' => function (Runtime $process) use (
                        $migrationFunctionName,
                        $migrationFunctionNamesCount,
                        $upgradeScriptNumber,
                        $i,
                    ): array {
                        @set_time_limit(0);

                        try {
                            $result = $migrationFunctionName($process) ?? [];
                        } catch (\Exception $e) {
                            $result['error'] = [
                                'raw' => $e->getMessage(),
                                'retry' => true,
                            ];
                        }

                        // bump version history for each migration script after last function has run
                        if ($i === $migrationFunctionNamesCount) {
                            \MyBB\Maintenance\addUpgradeNumberToVersionHistory($upgradeScriptNumber);
                        }

                        return $result;
                    },
                ];

                $i++;
            }
        }

        $this->addOperations($migrationOperations);
        $this->addOperationNamesToStep(array_keys($migrationOperations), 'migration');
    }

    /**
     * @return PreconditionResult
     */
    private static function precondition(Runtime $process, MyLanguage $lang)
    {
        if (\MyBB\Maintenance\lockFileExists(static::NAME) && !MyBB\Maintenance\developmentEnvironment()) {
            \MyBB\Maintenance\httpOutputError(
                $lang->locked_title,
                $lang->sprintf($lang->locked, 'lock_' . static::NAME),
            );
        }

        $installationState = InstallationState::get();

        if ($installationState !== InstallationState::INSTALLED) {
            return [
                'error' => [
                    'title' => $lang->upgrade_initialization_failed_title,
                    'message' => $lang->{'installation_state_' . strtolower($installationState->name) . '_description'} .
                        '<br><br>' .
                        $lang->upgrade_initialization_failed,
                ],
            ];
        }

        if ($process->getInterfaceType() === 'http') {
            if (
                !\MyBB\Maintenance\authenticatedWithSession() &&
                !\MyBB\Maintenance\authenticatedWithFile(INSTALL_ROOT) &&
                !\MyBB\Maintenance\developmentEnvironment()
            ) {
                return [
                    'error' => [
                        'title' => $lang->upgrade_not_authorized_title,
                        'message' => $lang->sprintf(
                            $lang->upgrade_not_authorized,
                            'auth_' . \MyBB\Maintenance\getFileAuthenticationServerCode(),
                        ),
                    ],
                ];
            }
        }

        if (
            \MyBB\Maintenance\getNextUpgradeScriptNumber() === null &&
            $process->getFlagValue('force') !== true &&
            $process->getFlagValue('development_mode') !== true
        ) {
            return [
                'error' => [
                    'title' => $lang->upgrade_not_needed_title,
                    'message' => $lang->upgrade_not_needed,
                ],
            ];
        }

        return true;
    }

    /**
     * @return OperationCallbackResult
     */
    private static function upgradePlanOperation(Runtime $process): array
    {
        $stateVariables = [
             // value used during handling of collected upgrade directives
            'upgrade_start' => (string)$process->getParameterValue('upgrade_start'),
        ];

        $applicableUpgradeScriptNumbers = \MyBB\Maintenance\getApplicableUpgradeScriptNumbers(
            (int)$process->getParameterValue('upgrade_start')
        );

        foreach ($applicableUpgradeScriptNumbers as $number) {
            $stateVariables['upgrade' . $number] = '1';
        }

        return [
            'stateVariables' => $stateVariables,
        ];
    }

    /**
     * @return OperationCallbackResult
     */
    private static function statisticsOperation(MyBB $mybb): array
    {
        require_once MYBB_ROOT . 'inc/functions_serverstats.php';

        build_server_stats(0, '', $mybb->version_code, $mybb->config['database']['encoding']);

        return [];
    }

    /**
     * @return OperationCallbackResult
     */
    private static function updateSettingsOperation(Runtime $process, MyLanguage $lang): array
    {
        if (!is_writable(MYBB_ROOT . 'inc/settings.php')) {
            return [
                'error' => [
                    'message' => $lang->sprintf(
                        $lang->file_not_writable,
                        'inc/settings.php',
                    ),
                ],
                'retry' => true,
            ];
        }

        $directives = \MyBB\Maintenance\getResolvedUpgradeDirectives(
            \MyBB\Maintenance\loadUpgradeScriptsData(),
            (int)$process->getStateVariable('upgrade_start'),
        );

        \MyBB\Maintenance\syncSettings($directives['revert_all_settings'] == 1);

        return [];
    }

    /**
     * @return OperationCallbackResult
     */
    private static function buildCacheOperation(): array
    {
        \MyBB\Maintenance\runVersionCheckTask();

        \MyBB\Maintenance\rebuildDatacache();

        return [];
    }

    /**
     * @return DefinitionList
     */
    private static function startDefinitionList(Runtime $process, MyBB $mybb, MyLanguage $lang): array
    {
        $list = [];

        // installation state
        $value = InstallationState::getDescription($lang);

        if ($value !== null) {
            $list[] = [
                'title' => $lang->installation_state,
                'value' => $value,
            ];
        }

        // version
        $list[] = [
            'name' => 'version',
            'title' => $lang->upgrade_version_to_be_installed,
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
}
