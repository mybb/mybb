'use strict';

const $ = (selector, $context) => ($context ?? document).querySelector(selector);
const $$ = (selector, $context) => ($context ?? document).querySelectorAll(selector);

const documentDataCache = {};

function documentData(name) {
	if (!documentDataCache.hasOwnProperty(name)) {
		documentDataCache[name] = JSON.parse($('[data-json="' + name + '"]')?.text ?? null);
	}

	return documentDataCache[name] ?? null;
}

const lang = documentData('lang');

// general helpers
/**
 * @return {Promise<Object>}
 */
function fetchAction(action, parameters = null) {
	let body;

	if (parameters !== null) {
		if (parameters instanceof FormData) {
			body = JSON.stringify(
				Object.fromEntries(parameters)
			);
		} else if (parameters instanceof Map) {
			body = JSON.stringify(
				Object.fromEntries(
					parameters.entries()
				)
			);
		} else {
			body = JSON.stringify(parameters);
		}
	}

	return new Promise((resolve, reject) => {
		fetch('?action=' + action, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json',
			},
			body: body,
		}).then(response => {
			if (response.ok) {
				const contentTypeHeader = response.headers.get('content-type');

				if (contentTypeHeader !== null) {
					if (contentTypeHeader.includes('application/json')) {
						response.json().then(jsonData => {
							resolve(jsonData);
						}).catch(() => {
							reject(new Error('Response JSON parsing failed'));
						});
					} else {
						reject(new Error('Unexpected response content type'));
					}
				} else {
					reject(new Error('Unexpected response content type'));
				}
			} else {
				reject(new Error('Unsuccessful response status code'));
			}
		}).catch((e) => {
			reject(new Error(e));
		});
	});
}

/**
 * Triggers prefetching through DOM, having no available API
 *
 * @param {string} url
 */
function prefetch(url) {
	const $link = document.createElement('link');

	$link.setAttribute('rel', 'prefetch');
	$link.setAttribute('href', url);

	document.head.appendChild($link);
}

function handleError(error) {
	console.error(error);
}

// UI helpers
function getNodeFromTemplate(templateName, $context) {
	const e = $('template[data-name="' + templateName + '"]', $context);

	if (e instanceof HTMLTemplateElement) {
		return e.content.cloneNode(true);
	} else {
		return null;
	}
}

/**
 * Adds callbacks to a list of fields executed on `input` and `change` events
 *
 * @param {NodeList} $$fields
 * @param {function} onInput
 * @param {function} onChange
 * @param {number} changeTriggerDebounceTime - time (ms) before the onChange callback is executed with debounce on last `input` event
 */
function addInputCallbacks($$fields, onInput, onChange, changeTriggerDebounceTime) {
	let feedbackTimeout;
	const unprocessedFields = new Set();

	$$fields.forEach($e => $e.addEventListener('input', () => {
		unprocessedFields.add($e);

		if (typeof onChange === 'function' && typeof changeTriggerDebounceTime === 'number') {
			clearTimeout(feedbackTimeout);

			feedbackTimeout = setTimeout( () => {
				unprocessedFields.delete($e);
				onChange();
			}, changeTriggerDebounceTime);
		}

		if (typeof onInput === 'function') {
			onInput();
		}
	}));

	$$fields.forEach($e => $e.addEventListener('change', () => {
		if (typeof onChange === 'function') {
			clearTimeout(feedbackTimeout);

			if (unprocessedFields.has($e)) {
				onChange();
				unprocessedFields.delete($e);
			}
		}
	}));
}

function getTextInputValues() {
	const input = new Set();

	$$('input:is([type="text"], [type="email"]):not([data-password-peekable])')
		.forEach($e => input.add($e.value));

	return Array.from(input);
}

/**
 * @param {HTMLElement} $element
 * @param {'x'|'y'} axis
 * @return {void}
 */
function scrollParentToCenterElement($element, axis) {
	const options = {
		behavior: 'smooth',
	};

	const dimension = axis === 'x' ? 'Width' : 'Height';
	const anchor = axis === 'x' ? 'Left' : 'Top';

	let distance = $element['offset' + anchor];
	distance -= $element.parentElement['client' + dimension] / 2;
	distance += $element['offset' + dimension] / 2;

	options[axis === 'x' ? 'left' : 'top'] = Math.max(0, distance);

	$element.parentElement.scrollTo(options);
}

/**
 * Fills multiple fields using pasted/dropped text content using provided keywords.
 *
 * @param {HTMLElement} $parent
 * @param {Map<string,string[]>} fieldKeywords
 * @return {void}
 */
function fillFieldsOnTextTransfer($parent, fieldKeywords) {
	const populate = (text) => {
		const results = getMatchedKeyValueMappingsFromString(text, fieldKeywords);

		for (const [fieldName, value] of results) {
			const $field = $('fieldset:not([disabled]) [name="' + fieldName + '"]:not([disabled]):not([readonly])', $parent);

			$field.value = value;
			$field.dispatchEvent(new Event('change'));
		}
	};

	// visual feedback
	$parent.addEventListener('dragenter', e => {
		e.currentTarget.classList.add('dropzone');

		for (const [name,] of fieldKeywords) {
			$('fieldset:not([disabled]) input[name="' + name + '"]:not([disabled]):not([readonly])', e.currentTarget).classList.add('drop-target');
		}
	});
	$parent.addEventListener('dragleave', e => {
		if (document.body.contains(e.relatedTarget) && !e.currentTarget.contains(e.relatedTarget)) {
			e.currentTarget.classList.remove('dropzone');
			$$('.drop-target', e.currentTarget).forEach($e => $e.classList.remove('drop-target'));
		}
	});

	// drop events
	$parent.addEventListener('dragover', e => {
		if (!e.target.matches('input, textarea')) {
			e.preventDefault();
		}
	});
	$parent.addEventListener('drop', e => {
		if (e.target.getAttribute('type') !== 'password' && e.shiftKey === false) {
			e.currentTarget.classList.remove('dropzone');
			$$('.drop-target', e.currentTarget).forEach($e => $e.classList.remove('drop-target'));

			const text = e.dataTransfer.getData('text/plain');

			populate(text);

			e.preventDefault();
		}
	});

	// paste events
	for (const [name,] of fieldKeywords) {
		const $field = $('input[name="' + name + '"]', $parent);

		if ($field.getAttribute('type') !== 'password') {
			let modifierKeyPressed = false;

			$field.addEventListener('keydown', e => modifierKeyPressed = e.shiftKey);
			$field.addEventListener('keyup', e => modifierKeyPressed = e.shiftKey);
			$field.addEventListener('paste', e => {
				if (modifierKeyPressed === false) {
					const text = e.clipboardData.getData('text/plain');

					if (/.+\n.+/s.test(text)) {
						populate(text);

						e.preventDefault();
					}
				}
			});
		}
	}
}

/**
 * Returns possible key-value mappings from commonly used string formats (e.g. lists, tables, configuration files).
 *
 * @param {string} text
 * @param {Map<string,string[]>} keyKeywords - possible names for each key (in decreasing priority)
 * @return {Map<string,string>}
 */
function getMatchedKeyValueMappingsFromString(text, keyKeywords) {
	const results = new Map();

	for (const [fieldName, keywords] of keyKeywords) {
		for (const keyword of keywords) {
			const pattern =
				// extra content before target keyword at the same line (including extra syntax characters)
				'^.*'
				+
				// keyword (with a possible prefix ending with _) closest to the separator
				'(?<nameDelimiter>"|\'|`|)(\\w+_)?' + keyword + '\\k<nameDelimiter>'
				+
				// extra syntax characters after the identifier
				'[^\\w]*?'
				+
				// separator (including extra whitespace)
				'\\s*[:=\\t]\\s*'
				+
				// value
				'(?<valueDelimiter>"|\'|`|)(?<value>[^\\s]*)\\k<valueDelimiter>'
			;
			const regex = new RegExp(pattern, 'mi');

			const value = text.match(regex)?.groups['value'];

			if (typeof value !== 'undefined') {
				results.set(fieldName, value);

				break;
			}
		}
	}

	return results;
}

function getNoteHtml(text, type) {
	let icon;

	switch (type) {
		case 'error':
			icon = 'exclamation-circle fas';
			break;
		case 'signal':
			icon = 'flag fas';
			break;
		case 'success':
			icon = 'check fas';
			break;
		case 'warning':
			icon = 'exclamation-triangle fas';
			break;
	}

	return `<span class="note note--${type}"><i class="icon fa-${icon}"></i> ${text}</span>`;
}

function setFieldNote($field, text, type) {
	const $label = $field.closest('label');
	const $note = $('.note', $label);

	$note?.remove();

	$label.insertAdjacentHTML('beforeend', getNoteHtml(text, type));
}

function removeFieldNote($field, soft) {
	const $note = $('.note', $field.closest('label'));

	if (soft === true) {
		$note?.classList.add('note--stale');
	} else {
		$note?.remove();
	}
}

function addFlashText(text) {
	const $e = `<div class="flash">${text}</div>`;

	$('body').insertAdjacentHTML('beforeend', $e);
}

function cycleElementText($e, strings, intervalMs) {
	let index = 0;

	const changeString = () => {
		$e.classList.add('hidden');

		setTimeout(() => {
			index = index === (strings.length - 1) ? 0 : ++index;
			$e.innerHTML = strings[index];
			$e.classList.remove('hidden');

			setTimeout(changeString, intervalMs);
		}, 1000);
	};

	setTimeout(changeString, intervalMs);
}

$('body').setAttribute('javascript-active', '1');
