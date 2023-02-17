<?php

declare(strict_types=1);

namespace MyBB\Maintenance\Process;

/**
 * Represents interface-independent logic to execute code according to environment data and user input.
 *
 * @psalm-type PreconditionModel = callable(Runtime $process=):PreconditionResult
 * @psalm-type PreconditionResult = true|array{
 *   error: Alert,
 * }
 * @psalm-type ParameterFeedbackModel = callable(ParameterValues $parameterValues, Runtime $process=):ParameterFeedback
 * @psalm-type ParameterFeedback = array<string,array{
 *   status: string,
 *   message: string,
 * }>
 * @psalm-type StepModel = array{
 *   'is': 'step',
 *   heading?: string|callable(Runtime $process=):string,
 *   description?: string|callable(Runtime $process=):string,
 *   instructions?: string|callable(Runtime $process=):string,
 *   definitionList?: DefinitionList|callable(Runtime $process=):DefinitionList,
 *   parameterFeedback?: ParameterFeedbackModel,
 *   operationNames: string[],
 *   submitBlockingOperationNames?: string[],
 *   finishBlockingOperationNames?: string[],
 * }
 * @psalm-type OperationModel = array{
 *   'is': 'operation',
 *   conditions?: Condition[],
 *   listed?: bool,
 *   parameterNames?: string[],
 *   callback: callable(Runtime $process=):OperationCallbackResult,
 * }
 * @psalm-type OperationCallbackResult = array{
 *   error?: Alert,
 *   warning?: Alert,
 *   retry?: bool,
 *   stateVariables?: array<string,string>,
 * }
 * @psalm-type ParameterModel = array{
 *   'is': 'parameter',
 *   type: 'text'|'email'|'password'|'select'|'checkbox',
 *   defaultValue?: string|callable(Runtime $process=):string,
 *   options?: string[]|callable(Runtime $process=):string[],
 *   maxlength?: int,
 *   required?: bool,
 *   feedback?: bool,
 *   autocomplete?: string,
 *   passwordScore?: bool,
 *   passwordRevealed?: bool|callable(Runtime $process=):bool,
 *   conditions?: Condition[],
 *   keywords?: string[],
 * }
 * @psalm-type DeferredDefaultParameterValueSourceModel = array{
 *   parameterNames: string[],
 *   callback: callable(Runtime $process=):ParameterValues,
 * }
 * @psalm-type FlagModel = array{
 *   type: 'boolean'|'string'|'integer',
 *   inputName: string,
 *   defaultValue: mixed|callable(Runtime $process=):mixed,
 *   aliasFor?: array<string,'boolean'|'string'|'integer'>,
 *   directives?: self::DIRECTIVE_*[],
 * }
 * @psalm-type FinishUrlModel = FinishUrl|callable(Runtime $process=):FinishUrl
 *
 * @psalm-type Condition = array{
 *   type: 'parameterValue'|'interfaceType'|'flagValue'|'stateVariable',
 *   name: string,
 *   in?: mixed[],
 * }
 * @psalm-type DefinitionList = list<array{
 *   name?: string,
 *   title: string,
 *   value: string|string[],
 * }>
 * @psalm-type ParameterValues = array<string,string>
 * @psalm-type Alert = array{
 *   title?: string,
 *   message?: string,
 *   list?: string[],
 *   raw?: string,
 * }
 * @psalm-type FinishUrl = string|null
 *
 *
 * @psalm-consistent-constructor
 */
class Model
{
    final public const TYPE_OPERATION = 'operation';
    final public const TYPE_PARAMETER = 'parameter';
    final public const TYPE_STEP = 'step';

    // Condition usage
    final public const SUPPORTED_CONDITIONS = [
        self::TYPE_OPERATION => [
            'interfaceType',
            'flagValue',
            'stateVariable',
        ],
        self::TYPE_PARAMETER => [
            'interfaceType',
            'flagValue',
            'parameterValue',
            'stateVariable',
        ],
        self::TYPE_STEP => [],
    ];

    final public const INIT_CONDITION_TYPES = [
        'interfaceType',
        'flagValue',
    ];
    final public const RUNTIME_CONDITION_TYPES = [
        'parameterValue',
        'stateVariable',
    ];

    final public const CONDITION_TYPES =
        self::INIT_CONDITION_TYPES +
        self::RUNTIME_CONDITION_TYPES
    ;

    // Flag directives
    final public const DIRECTIVE_USE_DEFAULTS = 2;

    private readonly string $name;

    /** @var PreconditionModel|null */
    private $precondition = null;

     /** @var array<string,StepModel> */
    private array $steps = [];

    /** @var array<string,OperationModel> */
    private array $operations = [];

    /** @var array<string,ParameterModel> */
    private array $parameters = [];

    /** @var DeferredDefaultParameterValueSourceModel[] */
    private array $deferredDefaultParameterValueSources = [];

    /** @var array<string,FlagModel> */
    private array $flags = [];

    /**@var FinishUrlModel */
    private $finishUrl = null;

    public static function getByName(string $name): ?self
    {
        $fqn = __NAMESPACE__ . '\\Model\\' . ucfirst($name) . 'Model';

        if (is_a($fqn, self::class, true)) {
            return new $fqn();
        } else {
            return null;
        }
    }

    public function __construct()
    {
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name ?? null;
    }

    /**
     * @param PreconditionModel|null $precondition
     */
    public function setPrecondition(?callable $precondition): void
    {
        $this->precondition = $precondition;
    }

    public function getPrecondition(): ?callable
    {
        return $this->precondition;
    }

    /**
     * @param array<string,StepModel> $steps
     */
    public function addSteps(array $steps): void
    {
        $this->steps = array_merge($this->steps, $steps);
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * @param string[] $operationNames
     */
    public function addOperationNamesToStep(array $operationNames, string $stepName): void
    {
        $this->steps[$stepName]['operationNames'] = array_merge(
            $this->steps[$stepName]['operationNames'],
            $operationNames,
        );
    }

    /**
     * @return ?ParameterFeedbackModel
     */
    public function getStepParameterFeedback(string $stepName): ?callable
    {
        return $this->steps[$stepName]['parameterFeedback'] ?? null;
    }

    /**
     * @param array<string,array> $operations
     * @throws \InvalidArgumentException
     */
    public function addOperations(array $operations): void
    {
        foreach ($operations as $name => $operation) {
            $operation['is'] = self::TYPE_OPERATION;

            $this->validateItemConditionTypes($operation);

            if (isset($operation['parameters'])) {
                $operation['parameterNames'] = array_keys($operation['parameters']);

                $this->addParameters($operation['parameters'], $operation['conditions'] ?? []);

                unset($operation['parameters']);
            }

            $this->operations[$name] = $operation;
        }
    }

    /**
     * @return OperationModel[]
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * @return ?OperationModel
     */
    public function getOperation(string $name): ?array
    {
        return $this->operations[$name] ?? null;
    }

    /**
     * @return string[]
     */
    public function getOperationNames(): array
    {
        return array_keys($this->operations);
    }

    public function addParameters(array $parameters, array $operationConditions = []): void
    {
        $operationRuntimeConditions = array_filter(
            $operationConditions,
            fn ($condition) => in_array(
                $condition['type'],
                array_intersect(
                    self::SUPPORTED_CONDITIONS[self::TYPE_OPERATION],
                    self::RUNTIME_CONDITION_TYPES,
                ),
            ),
        );

        foreach ($parameters as $name => $parameter) {
            $parameter['is'] = self::TYPE_PARAMETER;

            $existingParameterModel = $this->getParameter($name);

            if ($existingParameterModel !== null) {
                $parameter['required'] = ($parameter['required'] ?? false) ||
                    ($existingParameterModel['required'] ?? false);

                $parameter['conditions'] = array_merge(
                    $this->getCombinedConditions(
                        $parameter['conditions'] ?? [],
                        $existingParameterModel['conditions'] ?? [],
                    ),
                    $operationRuntimeConditions,
                );
            }

            $this->validateItemConditionTypes($parameter);

            $this->parameters[$name] = $parameter;
        }
    }

    /**
     * @return ParameterModel[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return ?ParameterModel
     */
    public function getParameter(string $name): ?array
    {
        return $this->parameters[$name] ?? null;
    }

    /**
     * @return string[]
     */
    public function getParameterNames(): array
    {
        return array_keys($this->parameters);
    }

    /**
     * @param DeferredDefaultParameterValueSourceModel[] $sources
     */
    public function addDeferredDefaultParameterValueSources(array $sources): void
    {
        $this->deferredDefaultParameterValueSources = array_merge(
            $this->deferredDefaultParameterValueSources,
            $sources,
        );
    }

    public function getDeferredDefaultParameterSources(): array
    {
        return $this->deferredDefaultParameterValueSources;
    }

    /**
     * @param array<string,FlagModel> $flags
     */
    public function addFlags(array $flags): void
    {
        foreach ($flags as $name => $flag) {
            $this->flags[$name] = $flag;
        }
    }

    public function getFlags(): array
    {
        return $this->flags;
    }

    public function flagExists(string $name): bool
    {
        return array_key_exists($name, $this->flags);
    }

    /**
     * @param FinishUrlModel $value
     */
    public function setFinishUrl(callable|string|null $value): void
    {
        $this->finishUrl = $value;
    }

    /**
     * @return FinishUrlModel
     */
    public function getFinishUrl(): callable|string|null
    {
        return $this->finishUrl;
    }


    /**
     * Returns a combined Condition set that evaluates positively when Conditions from any of the sets are satisfied.
     *
     * @param Condition[] ...$conditionSets
     * @return Condition[]
     */
    private function getCombinedConditions(array ...$conditionSets): array
    {
        // group Conditions by {type, name, set}
        $conditionsByTypeNameSet = [];

        $i = 0;
        foreach ($conditionSets as $conditionSet) {
            foreach ($conditionSet as $condition) {
                $conditionsByTypeNameSet[ $condition['type'] ][ $condition['name'] ][ $i ][] = $condition;
            }

            $i++;
        }

        // unset any {type, name} group that doesn't exist in all sets
        $conditionSetCount = count($conditionSets);

        foreach ($conditionsByTypeNameSet as $type => $conditionsByNameSet) {
            foreach ($conditionsByNameSet as $name => $conditionsBySet) {
                if (count($conditionsBySet) !== $conditionSetCount) {
                    unset($conditionsByTypeNameSet[$type][$name]);
                }
            }
        }

        // build union Conditions
        $mergedConditions = [];

        foreach ($conditionsByTypeNameSet as $type => $conditionsByNameSet) {
            foreach ($conditionsByNameSet as $name => $conditionsBySet) {
                $conditions = array_merge(...$conditionsBySet);

                $mergedConditions[] = [
                    'type' => $type,
                    'name' => $name,
                    'in' => array_merge(
                        ...array_column($conditions, 'in'),
                    ),
                ];
            }
        }

        return $mergedConditions;
    }

    /**
     * @param OperationModel|ParameterModel $item
     */
    private function validateItemConditionTypes(array $item): void
    {
        if (
            array_diff(
                array_column($item['conditions'] ?? [], 'type'),
                self::SUPPORTED_CONDITIONS[ $item['is'] ],
            )
        ) {
            throw new \InvalidArgumentException(
                'Attempting to use condition type(s) not supported for type ' . $item['is'],
            );
        }
    }
}
