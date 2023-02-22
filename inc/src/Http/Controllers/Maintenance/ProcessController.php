<?php

declare(strict_types=1);

namespace MyBB\Http\Controllers\Maintenance;

use Illuminate\Http\Request;
use MyBB;
use MyBB\Maintenance\InstallationState;
use MyBB\Maintenance\Process\Helpers as ProcessHelpers;
use MyBB\Maintenance\Process\Model;
use MyBB\Maintenance\Process\Runtime;
use MyLanguage;

class ProcessController
{
    protected bool $useDefaults = false;

    protected Model $processModel;
    protected Runtime $processRuntime;

    public function __construct(Request $request)
    {
        $processNames = [
            'install',
            'upgrade',
        ];

        if ($request->has('process') && in_array($request->get('process'), $processNames, true)) {
            $processName = $request->get('process');
        } else {
            $processName = InstallationState::get() === InstallationState::INSTALLED ? 'upgrade' : 'install';
        }

        $this->initializeProcess($processName, $request);
    }

    public function getDeferredDefaultValues(Request $request): ?array
    {
        $result = null;

        if (!$request->has('parameterNames') || is_array($request->get('parameterNames'))) {
            $result = $this->processRuntime->getDeferredDefaultParameterValues($request->get('parameterNames'));
        }

        return $result;
    }

    public function getParameterFeedback(Request $request): ?array
    {
        $result = null;

        if ($request->has('step')) {
            $result = $this->processRuntime->getStepParameterFeedback($request->get('step'), $request->all());
        }

        return $result;
    }

    public function runOperation(Request $request, MyLanguage $lang): ?array
    {
        $result = null;

        if ($request->has('operation')) {
            $result = $this->processRuntime->runOperation($request->get('operation'));
            $result = ProcessHelpers::getLocalizedOperationResult($request->get('operation'), $result, $lang);
        }

        return $result;
    }

    public function index(Request $request, MyLanguage $lang, array $languages): string
    {
        $noscriptRedirectLocation = null;
        $explicitStepRequest = false;

        $operationPlans = $this->processRuntime->getOperationPlans();
        $stepPlans = $this->processRuntime->getStepPlans();

        if (count($stepPlans) !== 0) {
            $setupParameters = [
                'process' => $this->processModel->getName(),
                'operations' => $this->processRuntime->getOperationSubset(),
            ];

            $setupParameters += $this->processRuntime->getNonDefaultFlagValues();

            if ($request->has('language') && $request->get('language') === $lang->language) {
                $setupParameters['language'] = $request->get('language');
            }


            $noscriptRedirectLocation = $_SERVER['PHP_SELF'] . '?' . http_build_query([
                'step' => array_key_first($stepPlans),
            ] + $setupParameters);


            if ($request->has('step') && array_key_exists($request->get('step'), $stepPlans)) {
                $currentStepName = $request->get('step');
                $explicitStepRequest = true;
            } else {
                /** @var string $currentStepName */
                $currentStepName = array_key_first($stepPlans);
            }

            $currentStepPlan = &$stepPlans[$currentStepName];
            $currentStepPlan['initial'] = true;


            if ($explicitStepRequest) {
                // synchronous operation execution during no-JS navigation

                // set operations in previous steps as completed for navigation display continuity
                foreach ($stepPlans as $stepName => $stepPlan) {
                    if ($stepName === $currentStepName) {
                        break;
                    } else {
                        foreach ($stepPlan['operationNames'] as $operationName) {
                            $operationPlans[$operationName]['completed'] = true;
                        }
                    }
                }

                // set current step's operations' status
                $succeededStepOperations = (array)$request->get('succeededStepOperations', []);
                $interactionRequired = false;

                foreach ($currentStepPlan['operationNames'] as $operationName) {
                    if (
                        $this->processRuntime->conditionsSatisfied(
                            $this->processRuntime->getOperationPlan($operationName),
                            array_intersect(
                                Model::SUPPORTED_CONDITIONS[Model::TYPE_OPERATION],
                                Model::RUNTIME_CONDITION_TYPES,
                            ),
                        )
                    ) {
                        if (in_array($operationName, $succeededStepOperations)) {
                            // preceding operations
                            $operationPlans[$operationName]['completed'] = true;
                        } elseif (
                            array_diff(
                                $operationPlans[$operationName]['parameterNames'],
                                array_keys($request->all())
                            ) === []
                        ) {
                            // satisfied operations

                            $operationResult = $this->processRuntime->runOperation($operationName);
                            $operationResult = ProcessHelpers::getLocalizedOperationResult(
                                $operationName,
                                $operationResult,
                                $lang
                            );

                            foreach (['error', 'warning'] as $type) {
                                if (isset($operationResult[$type])) {
                                    $currentStepPlan['alerts'][] = [
                                        'type' => $type,
                                        'title' => $operationResult[$type]['title'],
                                        'message' => $operationResult[$type]['message'],
                                        'list' => $operationResult[$type]['list'],
                                        'operationName' => $operationName,
                                        'retry' => false,
                                    ];
                                }
                            }

                            if ($operationResult['success'] === true) {
                                $operationPlans[$operationName]['completed'] = true;
                            }

                            if ($operationResult['success'] === false || $operationResult['retry'] === true) {
                                break;
                            }
                        } else {
                            // succeeding applicable operations
                            $interactionRequired = true;

                            break;
                        }
                    }
                }

                if ($interactionRequired === false && $request->getMethod() === 'POST') {
                    $stepNames = array_keys($stepPlans);

                    /** @var int $currentStepIndex */
                    $currentStepIndex = array_search($currentStepName, $stepNames);

                    $nextStepName = $stepNames[$currentStepIndex + 1] ?? null;

                    if ($nextStepName !== null) {
                        \MyBB\Maintenance\httpRedirect(
                            $_SERVER['PHP_SELF'] . '?' . http_build_query([
                                'step' => $nextStepName,
                                'stateVariables' => $this->processRuntime->getStateVariables(),
                            ] + $setupParameters),
                        );
                    } else {
                        $url = $this->processRuntime->getFinishUrl();

                        if ($url !== null) {
                            \MyBB\Maintenance\httpRedirect($url);
                        }
                    }
                }
            }

        }

        // interface-specific alerts
        if (
            \MyBB\Maintenance\httpRequestOverSecureTransport() === false &&
            \MyBB\Maintenance\httpRequestFromLocalNetwork() === false
        ) {
            $stepPlans['start']['alerts'][] = [
                'type' => 'warning',
                'title' => $lang->insecure_transport,
                'message' => $lang->insecure_transport_message,
            ];
        }


        $response = \MyBB\Maintenance\template('maintenance/process/process.twig', [
            'languages' => $languages,
            'processName' => $this->processModel->getName(),
            'stepPlans' => $stepPlans,
            'operationPlans' => $operationPlans,
            'parameterPlans' => $this->processRuntime->getParameterPlans(),
            'flagValues' => $this->processRuntime->getFlagValues(),
            'nonDefaultFlagValues' => $this->processRuntime->getNonDefaultFlagValues(),
            'operationSubset' => $this->processRuntime->getOperationSubset(),
            'stateVariables' => $this->processRuntime->getStateVariables(),
            'useDefaults' => $this->useDefaults,
            'explicitStepRequest' => $explicitStepRequest,
            'noscriptRedirectLocation' => $noscriptRedirectLocation,
            'finishUrl' => $this->processRuntime->getFinishUrl(),
        ]);

        return $response;
    }

    private function initializeProcess(string $processName, Request $request): void
    {
        $this->processModel = Model::getByName($processName);

        $this->processRuntime = new Runtime($this->processModel);

        $this->processRuntime->setInterfaceType('http');

        // flags
        $this->processRuntime->initializeFlagValues();

        foreach ($this->processModel->getFlags() as $name => $flag) {
            $value =
                $request->get($name)
                ?? $request->get($flag['inputName'])
                ?? null;

            if ($value !== null) {
                $this->processRuntime->setFlagValues([
                    $name => $value,
                ]);
            }
        }

        if ($this->processRuntime->flagDirectiveSet(Model::DIRECTIVE_USE_DEFAULTS)) {
            $this->useDefaults = true;
        }

        // precondition
        $preconditionResult = $this->processRuntime->getPreconditionResult();

        if ($preconditionResult !== true) {
            \MyBB\Maintenance\httpOutputError(
                $preconditionResult['error']['title'] ?? '',
                $preconditionResult['error']['message'],
            );
        }

        // operation subset
        if ($request->has('operations')) {
            $this->processRuntime->setOperationSubset((array)$request->get('operations'));
        }

        // parameters
        $this->processRuntime->setParameterValues($request->all());

        // state variables
        $stateVariables = array_filter(
            (array)$request->get('stateVariables', []),
        );

        foreach ($stateVariables as $name => $value) {
            if (is_scalar($value)) {
                $this->processRuntime->setStateVariable($name, $value);
            }
        }
    }
}
