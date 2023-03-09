<?php

declare(strict_types=1);

namespace MyBB\Console\Command;

use Illuminate\Support\Arr;
use MyBB\Maintenance\Process\Helpers as ProcessHelpers;
use MyBB\Maintenance\Process\Model;
use MyBB\Maintenance\Process\Runtime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @psalm-import-type ParameterValues from Model
 * @psalm-import-type StepPlan from Runtime
 * @psalm-import-type ParameterPlan from Runtime
 * @psalm-import-type OperationPlan from Runtime
 * @psalm-import-type OperationResult from Runtime
 */
abstract class MaintenanceProcessCommand extends Command
{
    final protected const STEP_SUCCESS = 2;
    final protected const STEP_FAILURE = 4;
    final protected const STEP_FAILURE_RETRYABLE = 8;

    final protected const PARAMETER_ORIGIN_DEFAULT = 'default';
    final protected const PARAMETER_ORIGIN_ENV = 'env';
    final protected const PARAMETER_ORIGIN_DIRECT = 'direct';
    final protected const PARAMETER_ORIGIN_INTERACTIVE = 'interactive';

    final protected const PARAMETER_ORIGINS_BY_PRIORITY = [
        self::PARAMETER_ORIGIN_INTERACTIVE,
        self::PARAMETER_ORIGIN_DIRECT,
        self::PARAMETER_ORIGIN_ENV,
        self::PARAMETER_ORIGIN_DEFAULT,
    ];

    protected readonly Model $processModel;
    protected readonly Runtime $processRuntime;

    protected static string $applicationName = 'mybb';
    protected float $totalOperationRunTime = 0;
    protected array $directParameterValues;
    protected bool $useDefaults = false;

    protected \MyLanguage $lang;

    protected InputInterface $input;
    protected OutputInterface $output;
    protected SymfonyStyle $io;

    public function __construct(string $name = null)
    {
        $this->lang = \MyBB\app(\MyLanguage::class);

        $this->lang->load('maintenance');

        $processModel = Model::getByName(static::getDefaultName());

        if ($processModel === null) {
            throw new \RuntimeException('Process model file not found');
        }

        $this->processModel = $processModel;

        parent::__construct($name);
    }

    /**
     * Adapts language phrase strings, which may contain HTML, for command line output.
     */
    private static function format(string $phrase): string
    {
        $phrase = str_replace('<br>', "\n", $phrase);
        $phrase = strip_tags($phrase);

        return $phrase;
    }

    private static function getParameterEnvironmentVariableName(string $parameterName): string
    {
        return strtoupper(static::$applicationName . '_' . static::getDefaultName() . '_' . $parameterName);
    }

    protected function configure(): void
    {
        $this->addOption(
            'operations',
            'o',
            InputOption::VALUE_REQUIRED,
            'Run selected operations only (comma-separated)',
        );
        $this->addOption(
            'param',
            'p',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Specify parameter value (--param name=value)',
        );

        foreach ($this->processModel->getFlags() as $name => $flag) {
            $inputNameNormalized = str_replace('_', '-', $flag['inputName']);

            if ($flag['type'] === 'boolean') {
                $mode = InputOption::VALUE_NONE;
            } else {
                $mode = InputOption::VALUE_REQUIRED;
            }

            $this->addOption($inputNameNormalized, null, $mode, $this->lang->{'flag_' . $name});
        }
    }

    /**
     * @psalm-return Command::SUCCESS|Command::FAILURE
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupIO($input, $output);

        try {
            $this->initializeProcess();
        } catch (\RuntimeException) {
            return Command::FAILURE;
        }

        $result = $this->executeProcess();

        if ($result === true) {
            return Command::SUCCESS;
        } else {
            return Command::FAILURE;
        }
    }

    private function setupIO(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);

        $output->getFormatter()->setStyle(
            'meta',
            new OutputFormatterStyle(null, null, ['bold']),
        );
        $output->getFormatter()->setStyle(
            'signal',
            new OutputFormatterStyle('cyan'),
        );
        $output->getFormatter()->setStyle(
            'raw',
            new OutputFormatterStyle('green'),
        );
    }

    /**
     * @throws \RuntimeException
     */
    private function initializeProcess(): void
    {
        /** @psalm-suppress InaccessibleProperty (https://github.com/vimeo/psalm/issues/7608) */
        $this->processRuntime = new Runtime($this->processModel);

        $this->processRuntime->setInterfaceType('cli');

        // flags
        $this->processRuntime->initializeFlagValues();

        foreach ($this->processModel->getFlags() as $name => $flag) {
            $inputNameNormalized = str_replace('_', '-', $flag['inputName']);

            $this->processRuntime->setFlagValues([
                $name => $this->input->getOption($inputNameNormalized),
            ]);
        }

        if ($this->processRuntime->flagDirectiveSet(Model::DIRECTIVE_USE_DEFAULTS)) {
            $this->useDefaults = true;
        }

        // shortcut flags
        if ($this->input->hasOption('fast') && $this->input->getOption('fast') === true) {
            $this->processRuntime->setFlagValues([
                'development_mode' => true,
            ]);
            $this->useDefaults = true;
        }

        if (!$this->input->isInteractive()) {
            $this->useDefaults = true;
        }

        // precondition
        $preconditionResult = $this->processRuntime->getPreconditionResult();

        if ($preconditionResult !== true) {
            $this->io->error(
                static::format($preconditionResult['error']['message'])
            );

            throw new \RuntimeException();
        }

        // operation subset
        /** @var ?string */
        $operationNamesCsv = $this->input->getOption('operations');
        $requestedOperationsNames = $this->validateRequestedOperationNamesCsv($operationNamesCsv);

        if ($requestedOperationsNames !== null) {
            $this->processRuntime->setOperationSubset($requestedOperationsNames);
        }

        // direct parameters
        /** @var array */
        $parameters = $this->input->getOption('param');

        $this->directParameterValues = $this->validateDirectParameters($parameters);
    }

    private function executeProcess(): bool
    {
        $nonInteractiveParameterValueOrigins = [
            self::PARAMETER_ORIGIN_DIRECT,
            self::PARAMETER_ORIGIN_ENV,
        ];

        if ($this->useDefaults) {
            $nonInteractiveParameterValueOrigins[] = self::PARAMETER_ORIGIN_DEFAULT;
        }

        $stepPlans = $this->processRuntime->getStepPlans();

        foreach ($stepPlans as $stepPlan) {
            $stepNonInteractiveParameterValueOrigins = $nonInteractiveParameterValueOrigins;

            do {
                $stepResult = $this->executeStep($stepPlan, $stepNonInteractiveParameterValueOrigins);

                if ($stepResult === self::STEP_SUCCESS) {
                    break;
                } elseif ($stepResult === self::STEP_FAILURE) {
                    return false;
                } elseif ($stepResult === self::STEP_FAILURE_RETRYABLE) {
                    if (
                        $this->input->isInteractive() &&
                        $this->io->confirm($this->lang->retry_step, true)
                    ) {
                        // collect parameter values interactively on subsequent attempts
                        $stepNonInteractiveParameterValueOrigins = [];
                    } else {
                        return false;
                    }
                }
            } while (true);
        }

        $this->finishProcess();

        return true;
    }

    private function finishProcess(): void
    {
        if ($this->processRuntime->getOperationSubset() === null) {
            $url = $this->processRuntime->getFinishUrl();

            if ($url !== null) {
                $urlEscaped = OutputFormatter::escape($url);

                $this->io->newLine();
                $this->io->writeln([
                    '<href=' . $urlEscaped . '>' . $urlEscaped . '</>',
                ]);
                $this->io->newLine();
            }
        }

        if ($this->output->isVeryVerbose()) {
            $timeSeconds = round($this->totalOperationRunTime, 4);

            $this->io->newLine();
            $this->output->write('<meta>∑(t) = ' . $timeSeconds . ' s</>');
            $this->io->newLine();
        }
    }

    /**
     * @param StepPlan $stepPlan
     * @param self::PARAMETER_ORIGIN_*[] $nonInteractiveParameterValueOrigins
     * @psalm-return self::STEP_*
     */
    private function executeStep(array $stepPlan, array $nonInteractiveParameterValueOrigins)
    {
        $deferredDefaultParameterValues = [];
        $unresolvedStepParameters = [];

        $stepParameterPlans = Arr::only(
            $this->processRuntime->getParameterPlans(),
            $stepPlan['parameterNames'],
        );

        $resolvedNonInteractiveParameterValuesByOrigin = $this->initializeStepParameters(
            Arr::only(
                $stepParameterPlans,
                array_keys(
                    array_filter($this->processRuntime->getParameterValues(), 'is_null')
                )
            ),
            $nonInteractiveParameterValueOrigins,
            $unresolvedStepParameters,
            $deferredDefaultParameterValues,
        );


        if ($this->processRuntime->getOperationSubset() === null) {
            $this->outputStepHeadings(
                $stepPlan,
                $unresolvedStepParameters !== [],
            );
        }

        $this->outputParameterNotes(
            $stepParameterPlans,
            $deferredDefaultParameterValues,
            $resolvedNonInteractiveParameterValuesByOrigin,
        );

        if ($this->input->isInteractive()) {
            $this->executeStepParameterInteraction($stepPlan, $unresolvedStepParameters);
        } else {
            $unresolvedRequiredStepParameterValues = array_filter(
                $unresolvedStepParameters,
                fn ($parameterPlan) => !empty($parameterPlan['required']),
            );

            if ($unresolvedRequiredStepParameterValues !== []) {
                return self::STEP_FAILURE;
            }
        }


        foreach ($stepPlan['operationNames'] as $operationName) {
            $operation = $this->processRuntime->getOperationPlan($operationName);

            if (
                $this->processRuntime->conditionsSatisfied(
                    $operation,
                    array_intersect(
                        Model::SUPPORTED_CONDITIONS[Model::TYPE_OPERATION],
                        Model::RUNTIME_CONDITION_TYPES,
                    ),
                )
            ) {
                do {
                    $operationResult = $this->executeOperation($operation);
                } while (
                    $operationResult['success'] === true &&
                    $operationResult['retry'] === true &&
                    $this->processRuntime->getOperationSubset() !== [$operationName] &&
                    $this->input->isInteractive() &&
                    $this->io->confirm($this->lang->retry_operation_confirm, true)
                );

                if ($operationResult['success'] === false) {
                    if ($operationResult['retry'] === true) {
                        $this->processRuntime->setParameterValues(
                            array_fill_keys($operation['parameterNames'], null)
                        );

                        return self::STEP_FAILURE_RETRYABLE;
                    } else {
                        return self::STEP_FAILURE;
                    }
                }
            }
        }


        return self::STEP_SUCCESS;
    }

    /**
     * @param StepPlan $stepPlan
     */
    private function outputStepHeadings(
        array $stepPlan,
        bool $instructions,
    ): void {
        $this->io->section(
            $stepPlan['heading'] ?? $this->lang->{static::getDefaultName() . '_step_' . $stepPlan['name'] . '_heading'}
        );

        if (isset($stepPlan['description'])) {
            $this->io->text(
                $stepPlan['description']
                ?? $this->lang->{static::getDefaultName() . '_step_' . $stepPlan['name'] . '_description'}
            );
            $this->io->newLine();
        }

        if ($instructions === true && isset($stepPlan['instructions'])) {
            $this->io->text(
                $stepPlan['instructions']
                ?? $this->lang->{static::getDefaultName() . '_step_' . $stepPlan['name'] . '_instructions'}
            );
            $this->io->newLine();
        }

        if (!empty($stepPlan['definitionList'])) {
            $definitionList = [];

            foreach ($stepPlan['definitionList'] as $item) {
                if (is_array($item['value'])) {
                    $value = ' * ' . implode("\n * ", $item['value']);
                } else {
                    $value = $item['value'];
                }

                $definitionList[] = [
                    $item['title'] => OutputFormatter::escape(static::format($value)),
                ];
            }

            $this->io->definitionList(...$definitionList);
        }
    }

    /**
     * @param array<string,ParameterPlan> $parameterPlans
     * @param ParameterValues $deferredDefaultParameterValues
     * @param array<self::PARAMETER_ORIGIN_*,ParameterValues> $resolvedNonInteractiveParameterValuesByOrigin
     */
    private function outputParameterNotes(
        array $parameterPlans,
        array $deferredDefaultParameterValues,
        array $resolvedNonInteractiveParameterValuesByOrigin,
    ): void
    {
        $notes = [];

        // list deferred default parameter value notes, in original parameter order
        foreach ($parameterPlans as $parameterPlan) {
            if (array_key_exists($parameterPlan['name'], $deferredDefaultParameterValues)) {
                $note = $this->lang->{'deferred_default_parameter_note_' . $parameterPlan['name']} ?? null;

                if ($note !== null) {
                    $notes[] = '<signal>' . $note . '</>';
                }
            }
        }

        // list origins of implicitly resolved parameter values
        $implicitParameterOrigins = [
            self::PARAMETER_ORIGIN_ENV,
            self::PARAMETER_ORIGIN_DEFAULT,
        ];

        foreach ($resolvedNonInteractiveParameterValuesByOrigin as $origin => $parameterValues) {
            if (in_array($origin, $implicitParameterOrigins)) {
                foreach ($parameterValues as $parameterName => $value) {
                    $parameterPlan = $parameterPlans[$parameterName];

                    $revealed = (
                        $parameterPlan['type'] !== 'password' || !empty($parameterPlan['passwordRevealed'])
                    );

                    $notes[] =
                        '<signal>' .
                        $this->lang->sprintf(
                            $this->lang->{'using_' . $origin . '_parameter_value' . ($revealed ? '_revealed' : null)},
                            $this->lang->{'parameter_' . $parameterName . '_title'},
                            OutputFormatter::escape($value),
                            self::getParameterEnvironmentVariableName($parameterName),
                        ) .
                        '</signal>';
                }
            }
        }

        if ($notes !== []) {
            $this->io->listing($notes);
        }
    }

    /**
     * @param StepPlan $stepPlan
     * @param ParameterPlan[] $unresolvedStepParameterPlans
     */
    private function executeStepParameterInteraction(array $stepPlan, array $unresolvedStepParameterPlans): void
    {
        $firstAttempt = true;

        // collect parameter values interactively with feedback
        do {
            if ($firstAttempt === true) {
                $firstAttempt = false;
            } else {
                // resolve all parameters again, interactively (in original order), with current values as UI defaults

                $stepParameterPlans = Arr::only(
                    $this->processRuntime->getParameterPlans(),
                    $stepPlan['parameterNames'],
                );

                $unresolvedStepParameterPlans = array_filter(
                    $stepParameterPlans,
                    fn ($parameterPlan) => $this->processRuntime->conditionsSatisfied(
                        $parameterPlan,
                        array_intersect(
                            Model::SUPPORTED_CONDITIONS[Model::TYPE_PARAMETER],
                            Model::RUNTIME_CONDITION_TYPES,
                        ),
                    ),
                );

                foreach ($unresolvedStepParameterPlans as $parameterPlan) {
                    $unresolvedStepParameterPlans[$parameterPlan['name']]['defaultValue'] =
                        $this->processRuntime->getParameterValue($parameterPlan['name']);
                }
            }

            $this->resolveParameterValues($unresolvedStepParameterPlans, ['interactive']);

            $parameterFeedback = $this->processRuntime->getStepParameterFeedback($stepPlan['name']);

            if ($parameterFeedback !== null) {
                foreach ($parameterFeedback as $parameterName => $feedback) {
                    if ($feedback['status'] !== 'success') {
                        $this->io->note(
                            $this->lang->{'parameter_' . $parameterName . '_title'} . ': ' . $feedback['message']
                        );
                    }
                }
            } else {
                $parameterFeedback = [];
            }
        } while (
            array_intersect(['error', 'warning'], array_column($parameterFeedback, 'status')) &&
            !(
                // non-error feedback in development mode
                $this->processModel->flagExists('development_mode') &&
                $this->processRuntime->getFlagValue('development_mode') === true &&
                !array_intersect(['error'], array_column($parameterFeedback, 'status'))
            ) &&
            $this->io->confirm(
                $this->lang->retry_parameter_input,
                false,
            )
        );
    }

    /**
     * Resolves parameter values non-interactively and provides related data.
     *
     * @param ParameterPlan[] $parameterPlans
     * @param self::PARAMETER_ORIGIN_*[] $origins
     * @param array<string,ParameterPlan> $unresolvedStepParameterPlans
     * @param ParameterValues $deferredDefaultParameterValues
     * @return array<self::PARAMETER_ORIGIN_*,ParameterValues>
     */
    private function initializeStepParameters(
        array $parameterPlans,
        array $origins,
        array &$unresolvedStepParameterPlans = [],
        array &$deferredDefaultParameterValues = []
    ): array {
        $resolvedParameterValuesByOrigin = [];
        $unresolvedStepParameterPlans = $parameterPlans;

        foreach (self::PARAMETER_ORIGINS_BY_PRIORITY as $origin) {
            if ($origin === self::PARAMETER_ORIGIN_INTERACTIVE) {
                continue;
            }

            if ($origin === self::PARAMETER_ORIGIN_DEFAULT) {
                // fetch additional parameter value suggestions
                $deferredDefaultParameterValues = $this->processRuntime->getDeferredDefaultParameterValues(
                    array_keys($unresolvedStepParameterPlans),
                );

                foreach ($deferredDefaultParameterValues as $parameterName => $value) {
                    $unresolvedStepParameterPlans[$parameterName]['defaultValue'] = $value;
                }
            }

            if (in_array($origin, $origins)) {
                $resolvedValues = $this->resolveParameterValues(
                    $unresolvedStepParameterPlans,
                    [$origin],
                    $resolvedParameterValuesByOrigin,
                );

                $unresolvedStepParameterPlans = array_filter(
                    array_diff_key($unresolvedStepParameterPlans, $resolvedValues),
                    fn (array $parameterPlan) => $this->processRuntime->conditionsSatisfied(
                        $parameterPlan,
                        array_intersect(
                            Model::SUPPORTED_CONDITIONS[Model::TYPE_PARAMETER],
                            Model::RUNTIME_CONDITION_TYPES,
                        ),
                    ),
                );
            }
        }

        return $resolvedParameterValuesByOrigin;
    }

    /**
     * @param array<string,ParameterPlan> $parameterPlans
     * @param self::PARAMETER_ORIGIN_*[] $origins
     * @param array<self::PARAMETER_ORIGIN_*,ParameterValues> $resolvedValuesByOrigin
     * @throws \Exception
     */
    private function resolveParameterValues(
        array $parameterPlans,
        array $origins,
        array &$resolvedValuesByOrigin = []
    ): array {
        $resolvedValues = [];

        foreach ($parameterPlans as $parameterName => $parameterPlan) {
            if (
                $this->processRuntime->conditionsSatisfied(
                    $parameterPlan,
                    array_intersect(
                        Model::SUPPORTED_CONDITIONS[Model::TYPE_PARAMETER],
                        Model::RUNTIME_CONDITION_TYPES,
                    ),
                )
            ) {
                foreach ($origins as $origin) {
                    $resolvedValue = null;

                    switch ($origin) {
                        case self::PARAMETER_ORIGIN_DEFAULT:
                            if (isset($parameterPlan['defaultValue'])) {
                                $resolvedValue = $parameterPlan['defaultValue'];
                            }

                            break;
                        case self::PARAMETER_ORIGIN_DIRECT:
                            if (isset($this->directParameterValues[$parameterName])) {
                                $resolvedValue = $this->directParameterValues[$parameterName];
                            }

                            break;
                        case self::PARAMETER_ORIGIN_ENV:
                            $value = getenv(self::getParameterEnvironmentVariableName($parameterName));

                            if ($value !== false) {
                                $resolvedValue = $value;
                            }

                            break;
                        case self::PARAMETER_ORIGIN_INTERACTIVE:
                            $resolvedValue = $this->collectParameterValueInteractively($parameterPlan);
                            break;
                    }

                    if ($resolvedValue !== null) {
                        $resolvedValues[$parameterName] = $resolvedValue;
                        $resolvedValuesByOrigin[$origin][$parameterName] = $resolvedValue;

                        // add values individually to make them available for subsequent condition checks
                        $this->processRuntime->setParameterValues([
                            $parameterName => $resolvedValue,
                        ]);

                        break;
                    }
                }
            }
        }

        return $resolvedValues;
    }

    /**
     * @param OperationPlan $operationPlan
     * @return OperationResult
     */
    private function executeOperation(array $operationPlan): array
    {
        if ($this->output->isVerbose()) {
            if ($operationPlan['listed'] === true) {
                $title = $this->lang->{'operation_' . $operationPlan['name'] . '_title'} ?? null;

                if ($title !== null) {
                    $this->io->write(
                        ' ' .
                        $this->lang->sprintf(
                            $this->lang->waiting_for_operation,
                            $this->lang->{'operation_' . $operationPlan['name'] . '_title'},
                        )
                    );
                } else {
                    $this->io->write(
                        ' ' .
                        $this->lang->waiting_for_operation_hidden,
                    );
                }
            }
        }

        $startTime = microtime(true);

        $operationResult = $this->processRuntime->runOperation($operationPlan['name']);

        $endTime = microtime(true);

        if ($this->output->isVeryVerbose()) {
            $time = $endTime - $startTime;

            $this->totalOperationRunTime += $time;

            $timeSeconds = round($time, 4);

            $this->output->write(' <meta>(' . $timeSeconds . ' s)</meta>');
        }

        if ($this->output->isVerbose()) {
            $this->io->newLine();
        }

        $this->outputOperationAlerts($operationPlan['name'], $operationResult);

        return $operationResult;
    }

    /**
     * @param OperationResult $operationResults
     */
    private function outputOperationAlerts(string $operationName, array $operationResults): void
    {
        $operationResults = ProcessHelpers::getLocalizedOperationResult($operationName, $operationResults, $this->lang);

        foreach (['error', 'warning'] as $type) {
            if (isset($operationResults[$type])) {
                $this->io->$type(
                    static::format($operationResults[$type]['title'] . "\n\n" . $operationResults[$type]['message'])
                );

                if (isset($operationResults[$type]['list'])) {
                    $this->io->listing(
                        array_map(self::format(...), $operationResults[$type]['list'])
                    );
                }

                if (isset($operationResults[$type]['raw']) && $this->output->isVerbose()) {
                    $this->output->write(
                        '<raw>' . OutputFormatter::escape($operationResults[$type]['raw']) . '</raw>'
                    );
                    $this->io->newLine(2);
                }
            }
        }
    }

    /**
     * @param ParameterPlan $parameterPlan
     * @throws \InvalidArgumentException
     */
    private function collectParameterValueInteractively(array $parameterPlan): ?string
    {
        $title = $this->lang->{'parameter_' . $parameterPlan['name'] . '_title'};
        $defaultValue = $parameterPlan['defaultValue'] ?? null;

        switch ($parameterPlan['type']) {
            case 'text':
            case 'email':
                $value = $this->io->ask($title, $defaultValue);
                break;
            case 'password':
                if (
                    ($parameterPlan['passwordRevealed'] ?? null) === true ||
                    isset($parameterPlan['defaultValue'])
                ) {
                    $value = $this->io->ask($title, $defaultValue);
                } else {
                    $value = $this->io->askHidden($title);
                }
                break;
            case 'select':
                $value = $this->io->choice(
                    $title,
                    array_map('strval', array_keys($parameterPlan['options'])),
                    $defaultValue,
                );
                break;
            case 'checkbox':
                $value = $this->io->confirm($title, $defaultValue == true) ? '1' : '0';
                break;
            default:
                throw new \InvalidArgumentException('Unknown parameter type');
        }

        $this->io->newLine();

        return $value;
    }

    /**
     * @throws \RuntimeException
     */
    private function validateRequestedOperationNamesCsv(?string $requestedOperationNamesCsv): ?array
    {
        $requestedOperationsNames = null;

        if ($requestedOperationNamesCsv !== null) {
            $requestedOperationsNames = array_unique(
                explode(',', $requestedOperationNamesCsv)
            );

            $nonexistentOperations = array_diff(
                $requestedOperationsNames,
                $this->processModel->getOperationNames()
            );

            if (count($nonexistentOperations) !== 0) {
                $this->io->error(
                    array_map(
                        function ($name): string {
                            return $this->lang->sprintf(
                                $this->lang->unknown_operation,
                                $name,
                            );
                        },
                        $nonexistentOperations,
                    )
                );

                throw new \RuntimeException();
            }
        }

        return $requestedOperationsNames;
    }

    /**
     * Validates and returns parameters passed during command invocation using `--param`.
     *
     * @param string[] $parametersEncoded Array of parameter strings in the `name=value` format
     * @throws \RuntimeException
     */
    private function validateDirectParameters(array $parametersEncoded): array
    {
        $parameters = [];

        $nonexistentParameters = [];

        $knownParameterNames = array_keys($this->processRuntime->getParameterPlans());

        foreach ($parametersEncoded as $parameterEncoded) {
            if (!str_contains($parameterEncoded, '=')) {
                $parameterName = $parameterEncoded;
                $parameterValue = '';
            } else {
                [$parameterName, $parameterValue] = explode('=', $parameterEncoded, 2);
            }

            if (!in_array($parameterName, $knownParameterNames)) {
                $nonexistentParameters[] = $parameterName;
            } else {
                $parameters[$parameterName] = $parameterValue;
            }
        }

        if (count($nonexistentParameters) !== 0) {
            $this->io->error(
                array_map(
                    function ($name): string {
                        return $this->lang->sprintf(
                            $this->lang->unknown_parameter,
                            $name,
                        );
                    },
                    $nonexistentParameters,
                )
            );

            throw new \RuntimeException();
        } else {
            return $parameters;
        }
    }
}
