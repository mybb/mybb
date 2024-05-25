<?php

declare(strict_types=1);

namespace MyBB\Maintenance\Process;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Combines the Model with data, resolves callbacks and conditions, and builds Plans used by interface controllers.
 *
 * @psalm-import-type PreconditionResult from Model
 * @psalm-import-type ParameterFeedback from Model
 * @psalm-import-type OperationModel from Model
 * @psalm-import-type OperationCallbackResult from Model
 * @psalm-import-type ParameterModel from Model
 * @psalm-import-type Condition from Model
 * @psalm-import-type DefinitionList from Model
 * @psalm-import-type ParameterValues from Model
 * @psalm-import-type Alert from Model
 * @psalm-import-type FinishUrl from Model
 *
 * @psalm-type StepPlan = array{
 *   'is': 'step',
 *   name: string,
 *   heading?: string,
 *   description?: string,
 *   instructions?: string,
 *   definitionList?: DefinitionList,
 *   operationNames: string[],
 *   parameterNames: string[],
 *   submitBlockingOperationNames?: string[],
 *   finishBlockingOperationNames?: string[],
 * }
 * @psalm-type OperationPlan = array{
 *   'is': 'operation',
 *   name: string,
 *   conditions?: Condition[],
 *   listed: bool,
 *   parameterNames: string[],
 * }
 * @psalm-type ParameterPlan = array{
 *   'is': 'parameter',
 *   name: string,
 *   type: 'text'|'email'|'password'|'select'|'checkbox',
 *   value?: string,
 *   defaultValue?: string,
 *   options?: string[],
 *   maxlength?: int,
 *   required?: bool,
 *   feedback?: bool,
 *   autocomplete?: string,
 *   passwordScore?: bool,
 *   passwordRevealed?: bool,
 *   conditions?: Condition[],
 *   keywords?: string[],
 *   operationNames: string[],
 * }
 *
 * @psalm-type InterfaceType = 'cli'|'http'
 * @psalm-type OperationResult = array{
 *   error?: Alert,
 *   warning?: Alert,
 *   retry: ?bool,
 *   success: bool,
 *   resultLevel: 'error'|'warning'|'success',
 *   stateVariables?: array<string,string>,
 * }
 */
class Runtime
{
    public readonly Model $model;

    private bool $plansBuilt = false;

    /** @var array<string,StepPlan> */
    private array $stepPlans;

    /** @var array<string,OperationPlan> */
    private array $operationPlans;

    /** @var array<string,ParameterPlan> */
    private array $parameterPlans;

    /**
     * Names of Operations to run. If null, all applicable Operations are run.
     *
     * @var ?string[]
     */
    private ?array $operationSubset = null;

    /** @var ?InterfaceType */
    private ?string $interfaceType = null;

    /** @var array<string,mixed> */
    private array $flagValues = [];

    /** @var array<string,mixed> */
    private array $nonDefaultFlagValues = [];

    /** @psalm-var Model::DIRECTIVE_*[] */
    private array $flagDirectives = [];

    /** @var array<string,string|null> */
    private array $parameterValues = [];

    /** @var array<string,string> */
    private array $stateVariables = [];

    public function __construct(Model $model)
    {
        $this->model = $model;

        $this->updateFromModel();
    }

    public function setFlagValue(string $name, mixed $value, bool $isDefault = false): void
    {
        $flagModel = $this->model->getFlags()[$name];

        $valueConverted = $this->getFlagValueConvertedToType($value, $flagModel['type']);

        $this->flagValues[$name] = $valueConverted;

        if ($isDefault === true) {
            unset($this->nonDefaultFlagValues[$name]);
        } else {
            $this->nonDefaultFlagValues[$name] = $valueConverted;
        }

        if ($flagModel['type'] === 'boolean' && $valueConverted === true) {
            foreach ($flagModel['aliasFor'] ?? [] as $name => $value) {
                $this->setFlagValue($name, $value, $isDefault);
            }

            foreach ($flagModel['directives'] ?? [] as $directive) {
                if (!in_array($directive, $this->flagDirectives, true)) {
                    $this->flagDirectives[] = $directive;
                }
            }
        }

        $this->plansBuilt = false;
    }

    public function setFlagValues(array $values): void
    {
        foreach ($values as $name => $value) {
            $this->setFlagValue($name, $value);
        }
    }

    public function getFlagValue(string $name): mixed
    {
        return $this->flagValues[$name];
    }

    public function getFlagValues(): array
    {
        return $this->flagValues;
    }

    public function getNonDefaultFlagValues(): array
    {
        return $this->nonDefaultFlagValues;
    }

    /**
     * @param Model::DIRECTIVE_* $directive
     */
    public function flagDirectiveSet(int $directive): bool
    {
        return in_array($directive, $this->flagDirectives, true);
    }

    public function initializeFlagValues(): void
    {
        foreach ($this->model->getFlags() as $flagName => $flag) {
            if (!isset($this->flagValues[$flagName])) {
                $expression = $flag['defaultValue'];

                $resolvedValue = is_callable($expression) ? $this->resolveCallback($expression) : $expression;

                $this->setFlagValue($flagName, $resolvedValue, true);
            }
        }
    }

    public function setOperationSubset(array $names): void
    {
        $this->operationSubset = array_intersect(
            $names,
            $this->model->getOperationNames(),
        );
    }

    public function getOperationSubset(): ?array
    {
        return $this->operationSubset;
    }

    public function getStateVariables(): array
    {
        return $this->stateVariables;
    }

    public function getStateVariable(string $name): ?string
    {
        return $this->stateVariables[$name] ?? null;
    }

    public function setStateVariable(string $name, string $value): void
    {
        $this->stateVariables[$name] = $value;
    }

    /**
     * @psalm-param ?InterfaceType $interfaceType
     */
    public function setInterfaceType(?string $interfaceType): void
    {
        $this->interfaceType = $interfaceType;

        $this->plansBuilt = false;
    }

    public function getInterfaceType(): ?string
    {
        return $this->interfaceType;
    }

    public function setParameterValues(array $parameters): void
    {
        $this->parameterValues = array_merge(
            $this->parameterValues,
            array_intersect_key($parameters, $this->parameterValues),
        );

        $this->plansBuilt = false;
    }

    public function getParameterValue(string $name): ?string
    {
        return $this->parameterValues[$name];
    }

    public function getParameterValues(): array
    {
        return $this->parameterValues;
    }

    /**
     * @return PreconditionResult
     */
    public function getPreconditionResult(): bool|array
    {
        $precondition = $this->model->getPrecondition();

        if ($precondition === null) {
            return true;
        } else {
            return $this->resolveCallback($precondition);
        }
    }

    /**
     * @return array{
     *   resultLevel: 'success'|'warning'|'error',
     *   operationResults: array<string,OperationResult>,
     * }
     */
    public function run(bool $stopOnWarning = true): array
    {
        $results = [
            'resultLevel' => 'success',
            'operationResults' => [],
        ];

        if ($this->getPreconditionResult() !== true) {
            $results['resultLevel'] = 'error';
        } else {
            foreach ($this->getStepPlans() as $step) {
                foreach ($step['operationNames'] as $operationName) {
                    if (
                        $this->conditionsSatisfied(
                            $this->getOperationPlan($operationName),
                            array_intersect(
                                Model::SUPPORTED_CONDITIONS[Model::TYPE_OPERATION],
                                Model::RUNTIME_CONDITION_TYPES,
                            ),
                        )
                    ) {
                        $operationResult = $this->runOperation($operationName);

                        $results['operationResults'][$operationName] = $operationResult;

                        if ($operationResult['resultLevel'] === 'warning' && $results['resultLevel'] === 'success') {
                            $results['resultLevel'] = 'warning';

                            if ($stopOnWarning === true) {
                                break 2;
                            }
                        }

                        if ($operationResult['resultLevel'] === 'error') {
                            $results['resultLevel'] = 'error';

                            break 2;
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * @return array<string,OperationPlan>
     */
    public function getOperationPlans(): array
    {
        if ($this->plansBuilt === false) {
            $this->buildPlansFromRuntime();
        }

        return $this->operationPlans;
    }

    /**
     * @return OperationPlan
     */
    public function getOperationPlan(string $name): array
    {
        if ($this->plansBuilt === false) {
            $this->buildPlansFromRuntime();
        }

        return $this->operationPlans[$name];
    }

    /**
     * @return array<string,ParameterPlan>
     */
    public function getParameterPlans(): array
    {
        if ($this->plansBuilt === false) {
            $this->buildPlansFromRuntime();
        }

        return $this->parameterPlans;
    }

    /**
     * @return ParameterPlan
     */
    public function getParameterPlan(string $name): array
    {
        if ($this->plansBuilt === false) {
            $this->buildPlansFromRuntime();
        }

        return $this->parameterPlans[$name];
    }

    /**
     * @return array<string,StepPlan>
     */
    public function getStepPlans(): array
    {
        if ($this->plansBuilt === false) {
            $this->buildPlansFromRuntime();
        }

        return $this->stepPlans;
    }

    /**
     * @param string[]|null $parameterNames
     * @return array<string,string>
     */
    public function getDeferredDefaultParameterValues(?array $parameterNames = null): array
    {
        $values = [];

        foreach ($this->model->getDeferredDefaultParameterSources() as $source) {
            if (array_intersect($source['parameterNames'], $parameterNames ?? array_keys($this->parameterPlans))) {
                $values = array_merge($values, $this->resolveCallback($source['callback']));
            }
        }

        return $values;
    }

    /**
     * @return ?ParameterFeedback
     */
    public function getStepParameterFeedback(string $stepName, ?array $parameterValues = null): ?array
    {
        $feedback = $this->model->getStepParameterFeedback($stepName);

        if (is_callable($feedback)) {
            if ($parameterValues === null) {
                $stepParameterValues = Arr::only(
                    $this->getParameterValues(),
                    $this->getStepPlans()[$stepName]['parameterNames'],
                );
            } else {
                $stepParameterValues = $parameterValues;
            }

            return $this->resolveCallback($feedback, [
                'parameterValues' => $stepParameterValues,
            ]);
        } else {
            return null;
        }
    }

    /**
     * @return FinishUrl
     */
    public function getFinishUrl(): ?string
    {
        $value = $this->model->getFinishUrl();

        if (is_callable($value)) {
            return $this->resolveCallback($value);
        } else {
            return $value;
        }
    }

    /**
     * @return OperationResult
     */
    public function runOperation(string $name): array
    {
        $operation = $this->model->getOperation($name);

        if ($operation === null) {
            throw new RuntimeException('Operation does not exist');
        }

        /** @var OperationCallbackResult $result */
        $result = $this->resolveCallback($operation['callback']);

        $result['success'] = !isset($result['error']);
        $result['retry'] = $result['retry'] ?? null;

        if (isset($result['error'])) {
            $result['resultLevel'] = 'error';
        } elseif (isset($result['warning'])) {
            $result['resultLevel'] = 'warning';
        } else {
            $result['resultLevel'] = 'success';
        }

        foreach ($result['stateVariables'] ?? [] as $name => $value) {
            $this->setStateVariable($name, $value);
        }

        return $result;
    }

    /**
     * @param array{
     *   'is': string,
     *   conditions?: Condition[],
     *   ...
     * } $plan
     * @param array<array-key, 'parameterValue'|'interfaceType'|'flagValue'|'stateVariable'> $conditionTypes
     */
    public function conditionsSatisfied(array $plan, array $conditionTypes): bool
    {
        if (
            array_diff(
                $conditionTypes,
                Model::SUPPORTED_CONDITIONS[ $plan['is'] ] ?? [],
            )
        ) {
            throw new RuntimeException('Attempting to use condition type(s) not supported for item type');
        }

        if (isset($plan['conditions'])) {
            foreach ($plan['conditions'] as $condition) {
                if (in_array($condition['type'], $conditionTypes)) {
                    switch ($condition['type']) {
                        case 'parameterValue':
                            $value = $this->parameterValues[$condition['name']] ?? null;

                            if (isset($condition['in'])) {
                                if ($value !== null && !in_array($value, $condition['in'])) {
                                    return false;
                                }
                            } else {
                                throw new RuntimeException('Unsupported condition');
                            }

                            break;
                        case 'interfaceType':
                            if (isset($condition['in'])) {
                                if (!in_array($this->interfaceType, $condition['in'], true)) {
                                    return false;
                                }
                            } else {
                                throw new RuntimeException('Unsupported condition');
                            }

                            break;
                        case 'flagValue':
                            $flagValue = $this->flagValues[$condition['name']] ?? null;

                            if (isset($condition['in'])) {
                                if (!in_array($flagValue, $condition['in'], true)) {
                                    return false;
                                }
                            } else {
                                throw new RuntimeException('Unsupported condition');
                            }

                            break;
                        case 'stateVariable':
                            if (isset($condition['in'])) {
                                return in_array(
                                    $this->stateVariables[ $condition['name'] ] ?? null,
                                    $condition['in'],
                                    true,
                                );
                            } else {
                                throw new RuntimeException('Unsupported condition');
                            }

                            break;
                        default:
                            throw new RuntimeException('Unknown condition type');
                    }
                }
            }
        }

        return true;
    }

    /**
     * Propagates Model changes.
     */
    public function updateFromModel(): void
    {
        // initialize missing parameter values
        $parameterNames = array_merge(
            ...array_column($this->model->getOperations(), 'parameterNames')
        );

        $this->parameterValues = array_merge(
            array_fill_keys($parameterNames, null),
            $this->parameterValues,
        );

        $this->plansBuilt = false;
    }

    /**
     * Builds data for use in controllers.
     */
    protected function buildPlansFromRuntime(): void
    {
        $this->initializeFlagValues();

        $this->parameterPlans = [];

        $parametersWithDeferredDefaults = array_merge(
            ...array_column(
                $this->model->getDeferredDefaultParameterSources(),
                'parameterNames',
            ),
        );

        foreach ($this->model->getParameters() as $parameterName => $parameterModel) {
            // check conditions not handled by the controller

            if ($this->conditionsSatisfied($parameterModel, Model::INIT_CONDITION_TYPES)) {
                $parameterPlan = array_merge(
                    [
                        'conditions' => [],
                    ],
                    $parameterModel,
                    [
                        'name' => $parameterName,
                        'hasDeferredDefault' => in_array(
                            $parameterName,
                            $parametersWithDeferredDefaults,
                        ),
                        'operationNames' => [], // populated in subsequent loops
                    ],
                );

                $this->resolveModelCallbacks($parameterPlan, [
                    'options',
                    'defaultValue',
                    'passwordRevealed',
                ]);

                if (isset($this->parameterValues[$parameterName])) {
                    $parameterPlan['value'] = $this->parameterValues[$parameterName];
                }

                $this->parameterPlans[$parameterName] = $parameterPlan;
            }
        }

        $this->operationPlans = [];

        foreach ($this->model->getOperations() as $operationName => $operationModel) {
            if (
                ($this->operationSubset === null || in_array($operationName, $this->operationSubset)) &&
                $this->conditionsSatisfied($operationModel, Model::INIT_CONDITION_TYPES)
            ) {
                $operationPlan = [
                    'is' => Model::TYPE_OPERATION,
                    'name' => $operationName,
                    'conditions' => $operationModel['conditions'] ?? [],
                    'listed' => $operationModel['listed'] ?? true,
                    'parameterNames' => [],
                ];

                // link Plans
                foreach ($operationModel['parameterNames'] ?? [] as $parameterName) {
                    if (array_key_exists($parameterName, $this->parameterPlans)) {
                        $this->parameterPlans[$parameterName]['operationNames'][] = $operationName;
                        $operationPlan['parameterNames'][] = $parameterName;
                    }
                }

                $this->operationPlans[$operationName] = $operationPlan;
            }
        }

        $this->stepPlans = [];

        foreach ($this->model->getSteps() as $stepName => $stepModel) {
            $stepPlan = array_merge($stepModel, [
                'name' => $stepName,
                'operationNames' => [],
                'parameterNames' => [],
            ]);

            // link Plans
            foreach ($stepModel['operationNames'] as $operationName) {
                $operationModel = $this->model->getOperation($operationName);

                if ($operationModel === null) {
                    throw new RuntimeException('Operation assigned to Step is not declared');
                }

                if (array_key_exists($operationName, $this->operationPlans)) {
                    $stepPlan['operationNames'][] = $operationName;
                    $stepPlan['parameterNames'] = array_merge(
                        $stepPlan['parameterNames'],
                        $this->operationPlans[$operationName]['parameterNames'],
                    );
                }
            }

            // skip step if no effective operations apply
            if (empty($stepPlan['operationNames'])) {
                continue;
            }

            $stepPlan['parameterNames'] = array_unique($stepPlan['parameterNames']);

            $this->resolveModelCallbacks($stepPlan, [
                'heading',
                'description',
                'instructions',
                'definitionList',
            ]);

            $this->stepPlans[$stepName] = $stepPlan;
        }

        $this->plansBuilt = true;
    }

    protected function getFlagValueConvertedToType(mixed $value, string $type): bool|int|string
    {
        $valueConverted = match ($type) {
            'boolean' => in_array($value, [true, '', '1', 'true'], true),
            'integer' => (int)$value,
            'string' => (string)$value,
            default => throw new RuntimeException('Unsupported flag value type'),
        };

        return $valueConverted;
    }

    protected function resolveModelCallbacks(array &$item, array $properties): void
    {
        foreach ($properties as $property) {
            if (isset($item[$property]) && is_callable($item[$property])) {
                $item[$property] = $this->resolveCallback($item[$property]);
            }
        }
    }

    /**
     * Resolves Model callbacks with dependency injection.
     */
    protected function resolveCallback(callable $callback, array $parameters = []): mixed
    {
        return Container::getInstance()->call(
            $callback,
            array_merge(
                [
                    'process' => $this,
                ],
                $parameters,
            )
        );
    }
}
