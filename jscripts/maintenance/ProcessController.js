class ProcessController extends EventTarget {
	/**
	 * @typedef {Object} Step
	 * @property {string} name
	 * @property {Map|null} disabledParameterValues
	 * @property {boolean} submitOnceNotBlocked
	 * @property {boolean} finishOnceNotBlocked
	 * @property {boolean} userInputStarted
	 * @property {boolean} submitted
	 * @property {boolean} everSubmitted
	 * @property {boolean} skipped
	 * @property {number} inputFeedbackStartTime
	 * @property {boolean} [initial]
	 * @property {Set<Parameter>} parameters
	 * @property {Set<string,Operation>} operations
	 * @property {Set<Operation>} submitBlockingOperations
	 * @property {Set<Operation>} finishBlockingOperations
	 */
	/**
	 * @typedef {Object} Operation
	 * @property {string} name
	 * @property {Step} step
	 * @property {string[]} parameterNames
	 * @property {boolean} active
	 * @property {boolean} completed
	 * @property {Object[]} conditions
	 * @property {boolean} listed
	 * @property {Set<Step>} submitBlockedSteps
	 * @property {Set<Step>} finishBlockedSteps
	 * @property {boolean} everFailed
	 * @property {boolean} skipped
	 */
	/**
	 * @typedef {Object} Parameter
	 * @property {string} name
	 * @property {Object[]} conditions
	 * @property {string[]} keywords
	 */
	/**
	 * @typedef {Object} OperationQueueCallback
	 * @property {Map<Operation, ?{success: boolean, retry: boolean}>} results
	 * @property {function} callback
	 */

	/** @type {number} */
	static inputChangeDebounceTimeMs = 300;

	/** @type {boolean} */
	useDefaults = false;

	/** @type {boolean} */
	clearOperationQueueOnRetryableSuccess = true;

	/** @type {?string} */
	finishButtonText = null;

	/**
	 * Additional data attached to AJAX operation requests.
	 *
	 * @type {Map<string,string>}
	 */
	extraInput = new Map();

	/** @type {boolean} */
	#navigationEnabled = true;

	/** @type {boolean} */
	#finished = false;

	/** @type {Map<string,Step>} */
	#steps = new Map();

	/** @type {?Step} */
	#displayedStep = null;

	/** @type Set<string> */
	#pendingDeferredDefaultParameterNames = new Set();

	/** @type Map<string,Operation> */
	#operations = new Map();

	/** @type {Set<Operation>} */
	#operationQueue = new Set();

	/** @type {Set<OperationQueueCallback>} */
	#operationQueueCallbacks = new Set();

	/** @type {boolean} */
	#operationQueueRunning = false;

	/** @type Map<string,Parameter> */
	#parameters = new Map();

	/** @type {Map<string,string>} */
	#parameterValues = new Map();

	/** @type {Map<string,string>} */
	#stateVariables = new Map();

	/** @type {Node|Document} */
	#$context = null;

	/**
	 * @param {Node|Document} $context
	 * @param {Object<string,Object>} stepPlans
	 * @param {Object<string,Object>} operationPlans
	 * @param {Object<string,Object>} parameterPlans
	 */
	constructor($context, stepPlans, operationPlans, parameterPlans) {
		super();

		performance.mark('process.init');

		this.#$context = $context;

		for (const [, parameterPlan] of Object.entries(parameterPlans)) {
			this.#parameters.set(parameterPlan.name, parameterPlan);
		}

		for (const [, operationPlan] of Object.entries(operationPlans)) {
			const operation = {};

			operation.name = operationPlan.name;
			operation.step = null;
			operation.parameterNames = operationPlan.parameterNames;
			operation.active = false;
			operation.completed = operationPlan.completed ?? false;
			operation.conditions = operationPlan.conditions;
			operation.listed = operationPlan.listed;
			operation.everFailed = false;
			operation.skipped = false;
			operation.submitBlockedSteps = new Set();
			operation.finishBlockedSteps = new Set();

			this.#operations.set(operation.name, operation);
		}

		for (const [, stepPlan] of Object.entries(stepPlans)) {
			const step = {};

			step.name = stepPlan.name;
			step.disabledParameterValues = null;
			step.submitOnceNotBlocked = false;
			step.finishOnceNotBlocked = false;
			step.userInputStarted = false;
			step.submitted = false;
			step.everSubmitted = false;
			step.inputFeedbackStartTime = 0;

			step.operations = new Set();
			step.submitBlockingOperations = new Set();
			step.finishBlockingOperations = new Set();

			stepPlan['operationNames']?.forEach(
				name => step.operations.add(this.#operations.get(name))
			);
			stepPlan['submitBlockingOperationNames']?.forEach(
				name => step.submitBlockingOperations.add(this.#operations.get(name))
			);
			stepPlan['finishBlockingOperationNames']?.forEach(
				name => step.finishBlockingOperations.add(this.#operations.get(name))
			);

			step.operations.forEach(
				operation => operation.step = step
			);
			step.submitBlockingOperations.forEach(
				operation => operation.submitBlockedSteps.add(step)
			);
			step.finishBlockingOperations.forEach(
				operation => operation.finishBlockedSteps.add(step)
			);

			step.parameters = new Set();

			stepPlan['parameterNames']?.forEach(
				name => step.parameters.add(this.#parameters.get(name))
			);

			// pick up on server-selected page
			if (stepPlan['initial'] === true) {
				this.#displayedStep = step;
			}

			step.skipped = this.#displayedStep === null;

			this.#steps.set(step.name, step);
		}

		const lastStep = this.#steps.get(
			Array.from(this.#steps.keys()).pop()
		);

		// wait for all operations before finishing the process
		for (const [, operation] of this.#operations) {
			if (operation.submitBlockedSteps.size === 0 && operation.finishBlockedSteps.size === 0) {
				lastStep.finishBlockingOperations.add(operation);
				operation.finishBlockedSteps.add(lastStep);
			}
		}
	}

	/**
	 * @return void
	 */
	start() {
		// navigation events
		this.#$$('form[data-step]').forEach($e => $e.addEventListener('submit', e => {
			this.#submitStep(this.#steps.get(e.currentTarget.getAttribute('data-step')));
			e.preventDefault();
		}));

		this.#$$('form[data-step] button:not([data-step-submit])').forEach($e => $e.addEventListener('click', function (e) {
			e.preventDefault();
		}));

		this.#$$('form[data-step] [name]').forEach($e => $e.addEventListener('input', () => {
			this.#steps.get($e.form.getAttribute('data-step')).userInputStarted = true;
		}, {
			once: true,
		}));

		const conditionalParameters = new Map();

		for (const [, step] of this.#steps) {
			if (step.skipped === false) {
				// parameter feedback
				const $form = this.#$('form[data-step="' + step.name + '"]');
				const $$feedbackEnabledFormFields = this.#$$('label [name][data-feedback]', $form);

				if ($$feedbackEnabledFormFields.length !== 0) {
					this.#showStepParametersFeedback(step);

					addInputCallbacks(
						$$feedbackEnabledFormFields,
						() => $$feedbackEnabledFormFields.forEach($e => removeFieldNote($e, true)),
						() => {
							this.#showStepParametersFeedback(step);
						},
						ProcessController.inputChangeDebounceTimeMs,
					);
				}

				const parametersWithDeferredDefaults = new Set();
				const autofillKeywords = new Map();

				for (const parameter of step.parameters) {
					for (const condition of parameter['conditions'].filter(e => e.type === 'parameterValue')) {
						conditionalParameters.set(
							this.#parameters.get(condition['name']),
							(conditionalParameters.get(condition['name']) ?? new Set()).add(parameter)
						);
					}

					if (parameter['hasDeferredDefault'] === true) {
						parametersWithDeferredDefaults.add(parameter.name);
					}

					if (Array.isArray(parameter['keywords'])) {
						autofillKeywords.set(parameter.name, new Set(parameter['keywords']));
					}
				}

				if (parametersWithDeferredDefaults.size !== 0) {
					this.#applyDeferredDefaultParameterValues(parametersWithDeferredDefaults, step);
				}

				if (autofillKeywords.size !== 0) {
					fillFieldsOnTextTransfer($form, autofillKeywords);
				}
			}
		}

		for (const [referencedParameter, parameters] of conditionalParameters) {
			parameters.forEach(parameter => this.#updateParameterVisibility(parameter));

			this.#$('form[data-step] [name="' + referencedParameter.name + '"]').addEventListener('change', () => {
				parameters.forEach(parameter => this.#updateParameterVisibility(parameter));
			});
		}


		if (this.#displayedStep !== null) {
			this.#startStep(this.#displayedStep);
		}

		performance.mark('process.start');
	}

	/**
	 * @return void
	 */
	#finish() {
		this.#finished = true;
		this.#updateStepControls(this.#displayedStep);

		performance.mark('process.finish');
		performance.measure('process', 'process.init', 'process.finish');

		this.dispatchEvent(new Event('finish'));
	}

	/**
	 * @param {string} name
	 * @param {string} value
	 * @param {boolean} propagate
	 */
	#setStateVariable(name, value, propagate = true) {
		this.#stateVariables.set(name.toString(), value);

		if (propagate === true) {
			this.#updateItemsVisibility();
		}
	}

	/**
	 * Propagates updates to conditionally-displayed items in the UI.
	 */
	#updateItemsVisibility() {
		for (const [, operation] of this.#operations) {
			this.#updateOperationVisibility(operation);
		}

		for (const [, parameter] of this.#parameters) {
			this.#updateParameterVisibility(parameter);
		}
	}

	/**
	 * @param {Operation|Parameter} item
	 */
	#runtimeConditionsSatisfied(item) {
		for (const condition of item.conditions ?? []) {
			let value;

			switch (condition['type']) {
				case 'parameterValue':
					value = this.#getParameterValue(this.#parameters.get(condition['name']));
					break;
				case 'stateVariable':
					value = this.#stateVariables.get(condition['name']) ?? null;
					break;
			}

			if (typeof(value) !== 'undefined' && !condition['in'].includes(value)) {
				return false;
			}
		}

		return true;
	}

	//#region parameters
	/**
	 * @param {Parameter} parameter
	 * @return {?string}
	 */
	#getParameterValue(parameter) {
		/** @type {HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement} */
		const $field = this.#$('form[data-step] [name="' + parameter.name + '"]');

		return $field.value ?? null;
	}

	/**
	 * @param {Parameter} parameter
	 */
	#updateParameterVisibility(parameter) {
		this.#setParameterVisibility(
			parameter,
			this.#runtimeConditionsSatisfied(parameter),
		);
	}

	/**
	 * @param {Parameter} parameter
	 * @param {boolean} state
	 */
	#setParameterVisibility(parameter, state) {
		const $label = this.#$('form[data-step] label[for="' + parameter.name + '"]');

		if ($label !== null) {
			$label.hidden = !state;

			$$('[data-required]', $label).forEach(
				$e => $e.toggleAttribute('required', state)
			);
		}
	}
	//#endregion

	//#region alerts
	/**
	 * @param {Step} step
	 * @param {Object} content
	 * @param {string} content.title
	 * @param {string} content.message
	 * @param {string[]} [content.list]
	 * @param {'error'|'warning'} type
	 * @param {Operation} [operation]
	 * @param {boolean} [retry]
	 * @return {void}
	 */
	#addStepAlert(step, content, type, operation, retry = false) {
		const $alert = getNodeFromTemplate('alert', this.#$context);

		this.#$('.alert', $alert).classList.add('alert--' + type);
		this.#$('.alert__title', $alert).innerText = content.title;
		this.#$('.alert__message', $alert).innerHTML = content.message;

		if (content.hasOwnProperty('list')) {
			const $list = this.#$context.createElement('ul');

			for (const element of content.list) {
				const $item = this.#$context.createElement('li');

				$item.innerHTML = element;

				$list.appendChild($item);
			}

			this.#$('.alert', $alert).appendChild($list);
		}

		if (content.hasOwnProperty('raw')) {
			const $details = this.#$context.createElement('small');

			$details.innerText = lang['operation_error_details_http'];

			this.#$('.alert', $alert).appendChild($details);
		}

		if (typeof operation === 'object') {
			this.#$('.alert', $alert).setAttribute('data-operation', operation.name);

			if (retry === true) {
				this.#$('.alert', $alert).setAttribute('data-retry-operation', operation.name);

				const $retryButton = $('button', getNodeFromTemplate('alertRetryButton', this.#$context));

				$retryButton.addEventListener('click', () => {
					this.#getStepParameterValues(step)
						.forEach((value, key) => this.#parameterValues.set(key, value));

					this.#enqueueOperations(new Set([operation]));
				}, {
					'once': true,
				});

				this.#$('.alert__controls', $alert).appendChild($retryButton);
			}
		}

		const $alertsContainer = this.#$('section[data-step="' + step.name + '"] .alerts');

		$alertsContainer.appendChild($alert);

		// don't scroll to alerts from pre-submit operations, unless retrying an operation
		let scroll;

		if (step.everSubmitted || (typeof operation !== 'undefined' && operation.everFailed === true)) {
			scroll = $alertsContainer.lastElementChild;
		} else {
			scroll = false;
		}

		this.#displayStep(step, scroll);
	}

	/**
	 * @param {Operation} operation
	 * @param {boolean} [stale]
	 * @return {void}
	 */
	#removeOperationAlerts(operation, stale = false) {
		this.#$$('main .alert[data-operation="' + operation.name + '"]').forEach($e => {
			if (stale === true) {
				$e.classList.add('alert--stale');
			} else {
				$e.remove();
			}
		});
	}

	/**
	 * @param {Step} step
	 * @return {void}
	 */
	#removeStepAlertsRetryOption(step) {
		this.#$$('section[data-step="' + step.name + '"] .alert[data-retry-operation]').forEach($e => {
			$e.removeAttribute('data-retry-operation');
			this.#$('button[data-retry]', $e).remove();
		});
	}
	//#endregion

	//#region steps
	/**
	 * @param {Step} step
	 * @return {Step|null}
	 */
	#getNextStep(step) {
		const array = Array.from(this.#steps.values());

		return array[array.indexOf(step) + 1] ?? null;
	}

	/**
	 * @param {Step} step
	 * @return {void}
	 */
	#startStep(step) {
		let scrollToElement;

		// don't scroll when displaying the first step
		if (step.name !== this.#steps.keys().next().value) {
			scrollToElement = this.#$('.page');
		} else {
			scrollToElement = false;
		}

		this.#displayStep(step, scrollToElement);

		if (this.useDefaults === true) {
			this.#stepDefaultValuesReady(step, () => this.#submitStep(step));
		}

		this.#enqueueConsecutiveSatisfiedOperations();
	}

	/**
	 * @param {Step} step
	 * @return {void}
	 */
	#submitStep(step) {
		if (this.#satisfiedQueuedOperations(step.submitBlockingOperations).size === 0) {
			step.submitOnceNotBlocked = false;
			step.submitted = true;
			step.everSubmitted = true;

			const input = this.#getStepParameterValues(step);
			input.forEach((value, key) => this.#parameterValues.set(key, value));

			this.#setStepParameterInputEnabled(step, false);

			this.#enqueueConsecutiveSatisfiedOperations();

			this.#addOperationQueueCallback(step.operations, result => {
				if (result.success === false) {
					step.submitted = false;

					input.forEach((value, key) => this.#parameterValues.delete(key));

					this.#setStepParameterInputEnabled(step, true);
				}
			});

			this.#finishStep(step);
		} else {
			step.submitOnceNotBlocked = true;
		}
	}

	/**
	 * @param {Step} step
	 * @return {void}
	 */
	#finishStep(step) {
		if (
			!Array.from(step.finishBlockingOperations)
				.some(operation => (
					this.#runtimeConditionsSatisfied(operation) &&
					operation.completed === false
				)
			)
		) {
			step.finishOnceNotBlocked = false;
			this.#setStepParameterInputEnabled(step, false);

			const nextStep = this.#getNextStep(step);

			if (nextStep === null) {
				this.#finish();
			} else {
				this.#startStep(nextStep);
			}
		} else {
			step.finishOnceNotBlocked = true;
			this.#updateStepControls(step);
		}
	}

	/**
	 * @param {Step} step
	 * @param {Node|false|null} [scroll] - an element to scroll the page to, `null` to scroll to first parameter field, or `false` to not scroll
	 * @return {void}
	 */
	#displayStep(step, scroll) {
		this.#displayedStep = step;
		step.finishOnceNotBlocked = false;

		const $sectionStep = this.#$('section[data-step="' + step.name + '"]');

		this.#$$(':is(nav, main) .step')?.forEach($e => {
			$e.removeAttribute('data-displayed');
			$e.hidden = true;
		});
		this.#$$(':is(nav, main) .step[data-step="' + step.name + '"]')?.forEach($e => {
			$e.setAttribute('data-displayed', '');
			$e.hidden = false;
		});

		if (scroll instanceof HTMLElement) {
			scroll.scrollIntoView({
				behavior: 'smooth',
			});
		} else if (scroll !== false) {
			const $firstField = this.#$$(
				'label:not([hidden]):first-of-type :is(input, select):not([hidden]):not([type="hidden"])',
				$sectionStep,
			)[0];

			$firstField?.focus();

			if (typeof $firstField?.select === 'function') {
				$firstField?.select();
			}
		}
	}

	/**
	 * Fetches and applies additional default form values for given parameters.
	 *
	 * @param {Set<string>} parameterNames
	 * @param {Step} step
	 * @return {void}
	 */
	#applyDeferredDefaultParameterValues(parameterNames, step) {
		parameterNames.forEach(name => this.#pendingDeferredDefaultParameterNames.add(name));

		const requestParameters = new Map([
			...this.#stateVariables,
			...this.extraInput,
		]);
		requestParameters.set('parameterNames', Array.from(parameterNames));

		fetchAction('get_deferred_default_values', requestParameters).then(data => {
			if (Object.keys(data).length !== 0 && step.userInputStarted === false) {
				for (const parameterName of Object.keys(data)) {
					const $field = this.#$('[name="' + parameterName + '"]');

					if ($field !== null) {
						$field.value = data[parameterName];

						const langStringName = 'deferred_default_parameter_note_' + parameterName;

						if (lang.hasOwnProperty(langStringName)) {
							setFieldNote($field, lang[langStringName], 'signal');
						}
					}

					this.#pendingDeferredDefaultParameterNames.delete(parameterName);
				}

				this.dispatchEvent(new Event('pendingDeferredDefaultParameterValuesChange'));

				// re-apply parameter feedback, excluding the populated parameters
				this.#showStepParametersFeedback(
					step,
					Array.from(this.#$$('label [name][data-feedback]'))
						.map(e => e.getAttribute('name'))
						.filter(e => !(e in data)),
				);
			}
		}).catch(handleError);
	}

	/**
	 * Fetches and displays notes associated with input fields.
	 *
	 * @param {Step} step
	 * @param {Array} [parameterNames] - limit displayed notes to fields with selected names
	 * @return {void}
	 */
	#showStepParametersFeedback(step, parameterNames = null) {
		if (parameterNames === null || parameterNames.length !== 0) {
			const startTime = new Date().getTime();

			step.inputFeedbackStartTime = startTime;

			const $form = this.#$('form[data-step="' + step.name + '"]');

			fetchAction('get_parameter_feedback', new FormData($form)).then(data => {
				if (step.inputFeedbackStartTime !== startTime) {
					// superseding check started
					return;
				}

				if (parameterNames === null) {
					const $$feedbackEnabledFormFields = this.#$$('label [name][data-feedback]', $form);

					$$feedbackEnabledFormFields.forEach($e => removeFieldNote($e));
				}

				for (const [parameterName, feedback] of Object.entries(data)) {
					if (parameterNames === null || parameterNames.includes(parameterName)) {
						const $field = $('[name="' + parameterName + '"]', $form);

						if ($field !== null) {
							setFieldNote($field, feedback.message, feedback.status);
						}
					}
				}
			})
			.catch(handleError);
		}
	}

	/**
	 * @param {Step} step
	 * @return {Map}
	 */
	#getStepParameterValues(step) {
		if (step.disabledParameterValues !== null) {
			return step.disabledParameterValues;
		} else {
			const $form = this.#$('form[data-step="' + step.name + '"]');

			return new Map(
				new FormData($form).entries()
			);
		}
	}

	/**
	 * Checks whether a form validates correctly, and optionally fires a callback once it does.
	 *
	 * @param {Step} step
	 * @param {function} [onReadyCallback]
	 * @return {boolean}
	 */
	#stepDefaultValuesReady(step, onReadyCallback) {
		const interactiveFields = Array.from(
			this.#$$('form[data-step="' + step.name + '"] [name]')
		);

		const deferredDefaultValuesPending = interactiveFields.some($e =>
			this.#pendingDeferredDefaultParameterNames.has($e.getAttribute('name'))
		);
		const fieldsValid = interactiveFields.every($e => $e.validity.valid);

		if (typeof onReadyCallback === 'function') {
			if (deferredDefaultValuesPending === false) {
				if (fieldsValid === true) {
					onReadyCallback();
				}
			} else {
				this.addEventListener(
					'pendingDeferredDefaultParameterValuesChange',
					() => this.#stepDefaultValuesReady(step, onReadyCallback),
					{
						once: true,
					}
				);
			}
		}

		return !deferredDefaultValuesPending && fieldsValid;
	}

	/**
	 * @param {Step} step
	 * @return {void}
	 */
	#updateStepControls(step) {
		const $button = this.#$('form[data-step="' + step.name + '"] .controls button');

		if (!$button.hasAttribute('data-idle-value')) {
			$button.setAttribute('data-idle-value', $button.innerHTML);
		}

		if (this.#finished === true) {
			$button.disabled = true;

			if (this.finishButtonText !== null) {
				$button.innerHTML = this.finishButtonText;
			}
		} else if (this.#navigationEnabled === false) {
			$button.hidden = true;
		} else {
			$button.hidden = false;

			let blockingOperations;

			if (step.submitted === false) {
				blockingOperations = step.submitBlockingOperations;
			} else {
				blockingOperations = step.finishBlockingOperations;
			}

			const queuedStepOperations = this.#satisfiedQueuedOperations(blockingOperations);

			let buttonText;

			if (queuedStepOperations.size === 0) {
				buttonText = $button.getAttribute('data-idle-value');
				$button.disabled = false;
			} else {
				const firstQueuedOperation = queuedStepOperations.values().next().value;
				const firstQueuedOperationTitle = lang['operation_' + firstQueuedOperation.name + '_title'];

				if (typeof firstQueuedOperationTitle !== 'undefined') {
					buttonText = lang['waiting_for_operation'].replace('{1}', firstQueuedOperationTitle);
				} else {
					buttonText = lang['waiting_for_operation_unlisted'];
				}

				$button.disabled = true;
			}

			$button.innerHTML = buttonText;
		}
	}

	/**
	 * @param {Step} step
	 * @param {boolean} [enabled]
	 * @return {void}
	 */
	#setStepParameterInputEnabled(step, enabled = true) {
		step.disabledParameterValues = enabled ? null : this.#getStepParameterValues(step);

		this.#$$('form[data-step="' + step.name + '"] fieldset')
			.forEach($e => $e.disabled = !enabled);
	}
	//#endregion

	//#region operations
	/**
	 * @param {Operation} operation
	 */
	#updateOperationVisibility(operation) {
		const $icon = this.#$('nav .operation[data-operation="' + operation.name + '"]');

		if ($icon) {
			$icon.hidden = !this.#runtimeConditionsSatisfied(operation);
		}
	}

	/**
	 * @param {Set<Operation>} operations
	 * @param {function} callback
	 */
	#addOperationQueueCallback(operations, callback) {
		if (operations.size === 0) {
			callback({
				success: true,
				retry: false,
			});
		} else {
			const results = new Map();

			operations.forEach(operation => results.set(operation, null));

			this.#operationQueueCallbacks.add({
				results: results,
				callback: callback,
			});
		}
	}

	/**
	 * Adds to the queue the first consecutive series of non-completed operations
	 * that can be run using registered parameter values.
	 *
	 * @return {Set<Operation>}
	 */
	#enqueueConsecutiveSatisfiedOperations() {
		const operations = new Set();

		stepsLoop:for (const [, step] of this.#steps) {
			if (step.skipped === false) {
				for (const operation of step.operations) {
					if (
						operation.completed === false &&
						operation.skipped === false &&
						!this.#operationQueue.has(operation)
					) {
						if (
							this.#runtimeConditionsSatisfied(operation) &&
							!operation.parameterNames.every(name => this.#parameterValues.has(name))
						) {
							// known to be applicable, and requires interaction
							break stepsLoop;
						} else {
							operations.add(operation);
						}
					}
				}
			}
		}

		this.#enqueueOperations(operations);

		return operations;
	}

	/**
	 * @param {Set<Operation>} operations
	 */
	#enqueueOperations(operations) {
		operations.forEach(operation => this.#operationQueue.add(operation));

		this.#runOperationQueue();
	}

	/**
	 * @returns {void}
	 */
	async #runOperationQueue() {
		if (this.#operationQueueRunning === true) {
			return;
		} else {
			this.#operationQueueRunning = true;
		}

		for (const operation of this.#operationQueue) {
			await new Promise((resolve, reject) => {
				if (!this.#runtimeConditionsSatisfied(operation)) {
					operation.skipped = true;
					resolve(null);
				} else {
					if (!operation.parameterNames.every(name => this.#parameterValues.has(name))) {
						reject();
					} else {
						resolve(
							this.#runOperation(operation)
						);
					}
				}
			}).then(operationResult => {
				this.#operationQueue.delete(operation);

				const clearQueue = (
					operationResult !== null &&
					(
						operationResult.success === false ||
						(
							operationResult.retry === true &&
							this.clearOperationQueueOnRetryableSuccess === true
						)
					)
				);

				for (const group of this.#operationQueueCallbacks) {
					if (operationResult !== null) {
						if (group.results.has(operation)) {
							group.results.set(operation, operationResult);
						}
					}

					// fire callbacks for effectively finished groups
					const groupResults = Array.from(group.results).filter(
						([operation, ]) => operation.skipped === false
					);

					if (clearQueue === true || groupResults.every(([, result]) => result !== null)) {
						group.callback({
							success: groupResults.every(([, result]) => result !== null && result.success === true),
							retry: groupResults.some(([, result]) => result !== null && result.retry === true),
						});

						this.#operationQueueCallbacks.delete(group);
					}
				}

				[
					...operation.submitBlockedSteps,
					...operation.finishBlockedSteps,
				].forEach(step => this.#updateStepControls(step));

				if (clearQueue === true) {
					throw null;
				}
			}).catch(() => {
				const clearedOperations = new Set(this.#operationQueue);

				this.#operationQueue.clear();
				this.#operationQueueCallbacks.clear();

				clearedOperations.forEach(o => this.#updateStepControls(o.step));
			});
		}

		this.#operationQueueRunning = false;

		this.dispatchEvent(new Event('operationQueueStop'));
	}

	/**
	 * @param {Operation} operation
	 * @return {Promise<{success: boolean, retry: boolean}>}
	 */
	async #runOperation(operation) {
		this.#setOperationState(operation, true);
		this.#removeOperationAlerts(operation, true);

		const result = {
			success: false,
			retry: false,
		};

		const requestParameters = new Map([
			...this.#parameterValues,
			...this.extraInput,
		]);
		requestParameters.set('operation', operation.name);
		requestParameters.set('stateVariables', Object.fromEntries(this.#stateVariables));

		await fetchAction('run_operation', requestParameters).then(jsonData => {
			this.#removeOperationAlerts(operation);

			if (jsonData.hasOwnProperty('retry') && jsonData['retry'] === true) {
				result.retry = true;

				this.#removeStepAlertsRetryOption(operation.step);
			} else {
				result.retry = false;
			}

			if (jsonData.hasOwnProperty('error')) {
				operation.everFailed = true;

				this.#addStepAlert(operation.step, jsonData.error, 'error', operation, result.retry);

				if (typeof jsonData.error.raw === 'string') {
					console.debug('Error in "' + operation.name + '" operation: ', jsonData.error.raw);
				}

				if (result.retry === false) {
					this.#navigationEnabled = false;

					this.#updateStepControls(this.#displayedStep);
				}
			}

			if (jsonData.hasOwnProperty('warning')) {
				operation.everFailed = true;

				this.#addStepAlert(operation.step, jsonData.warning, 'warning', operation, result.retry);
			}

			if (jsonData.hasOwnProperty('stateVariables')) {
				for (const [name, value] of Object.entries(jsonData.stateVariables)) {
					this.#setStateVariable(name.toString(), value, false);
				}

				this.#updateItemsVisibility();
			}

			result.success = jsonData.hasOwnProperty('success') && jsonData['success'] === true;

			if (result.success !== true) {
				operation.step.finishBlockingOperations.add(operation);
				operation.finishBlockedSteps.add(operation.step);

				this.#updateStepControls(operation.step);
			}
		}).catch(e => {
			handleError(e);

			result.success = false;

			this.#removeOperationAlerts(operation);
			this.#addStepAlert(operation.step, {
				title: lang['operation_error_title']
					.replace('{1}', lang['operation_' + operation.name + '_title'] ?? operation.name),
				message: lang['operation_error_message'],
			}, 'error', operation, true);
		});

		this.#setOperationState(operation, false, result.success);

		return result;
	}

	/**
	 * @param {Operation} operation
	 * @param {boolean|null} active
	 * @param {boolean|null} [completed]
	 * @return {void}
	 */
	#setOperationState(operation, active, completed) {
		if (typeof active === 'boolean') {
			operation.active = active;

			if (active === true) {
				performance.mark('operation.' + operation.name + '.active');
			} else {
				performance.mark('operation.' + operation.name + '.inactive');
				performance.measure(
					'operation.' + operation.name,
					'operation.' + operation.name + '.active',
					'operation.' + operation.name + '.inactive',
				);
			}
		}

		if (typeof completed === 'boolean') {
			operation.completed = completed;
		}

		if (operation.listed === true) {
			const $icon = this.#$('nav .operation[data-operation="' + operation.name + '"]');

			if ($icon) {
				let visualState;

				if (completed === true) {
					visualState = 'completed';
				} else if (active === true) {
					visualState = 'active';
				} else {
					visualState = 'inactive';
				}

				$icon.classList.remove(
					'operation--active',
					'operation--inactive',
					'operation--completed',
				);
				$icon.classList.add('operation--' + visualState);

				if (active === true) {
					scrollParentToCenterElement($icon, 'x');
				}
			}
		}

		this.#$('html').style.cursor = active === true ? 'progress' : 'auto';

		for (const step of operation.submitBlockedSteps) {
			if (
				step.submitOnceNotBlocked === true &&
				this.#satisfiedQueuedOperations(
					new Set(
						Array.from(step.submitBlockingOperations).filter(e => e !== operation)
					)
				).size === 0
			) {
				this.#submitStep(step);
			}

			this.#updateStepControls(step);
		}

		for (const step of operation.finishBlockedSteps) {
			if (
				step.finishOnceNotBlocked === true &&
				this.#satisfiedQueuedOperations(
					new Set(
						Array.from(step.finishBlockingOperations).filter(e => e !== operation)
					)
				).size === 0
			) {
				this.#finishStep(step);
			}

			this.#updateStepControls(step);
		}
	}

	/**
	 * @param {Set<Operation>} [operations]
	 * @return {Set}
	 */
	#satisfiedQueuedOperations(operations) {
		let queuedOperationsArray = Array.from(this.#operationQueue);

		if (typeof operations !== 'undefined') {
			queuedOperationsArray = queuedOperationsArray.filter(operation => operations.has(operation));
		}

		queuedOperationsArray = queuedOperationsArray.filter(operation => this.#runtimeConditionsSatisfied(operation));

		return new Set(queuedOperationsArray);
	}
	//#endregion

	//#region DOM
	/**
	 * @param {string} selector
	 * @param {Node} [$context]
	 * @return {HTMLElement|null}
	 */
	#$(selector, $context) {
		return ($context ?? this.#$context).querySelector(selector);
	}

	/**
	 * @param {string} selector
	 * @param {Node} [$context]
	 * @return {NodeList|null}
	 */
	#$$(selector, $context) {
		return ($context ?? this.#$context).querySelectorAll(selector);
	}
	//#endregion
}
