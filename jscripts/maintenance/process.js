'use strict';

const processController = new ProcessController(
	document,
	documentData('stepPlans'),
	documentData('operationPlans'),
	documentData('parameterPlans'),
);
let showExecutionTime = (
	documentData('flagValues')['development_mode'] === true &&
	documentData('useDefaults') === true
);

if (documentData('useDefaults') === true) {
	processController.useDefaults = true;
	processController.clearOperationQueueOnRetryableSuccess = false;
}

if (documentData('finishUrl') !== null) {
	processController.finishButtonText = lang['redirecting'];
}

processController.extraInput = new Map(
	Object.entries(documentData('nonDefaultFlagValues'))
);

processController.extraInput.set('process', documentData('processName'));
processController.extraInput.set('language', lang.language);

processController.addEventListener('operationQueueStop', () => {
	// non-interactive flow interrupted if dispatched before `finish` event
	showExecutionTime = false;
});
processController.addEventListener('finish', () => {
	if (showExecutionTime === true) {
		const time = (performance.getEntriesByName('process')[0]).duration;
		const timeFormatted = (time / 1000).toFixed(2);

		addFlashText(`${timeFormatted} s`);
	}

	const finishUrl = documentData('finishUrl');

	if (finishUrl !== null) {
		const $e = $('body');

		$('html').style.cursor = 'progress';

		if (showExecutionTime === true) {
			window.location = finishUrl;
		} else {
			prefetch(finishUrl);

			$e.addEventListener('animationend', e => {
				if (e.animationName === 'body-fade-out') {
					window.location = finishUrl;
				}
			});
		}

		$e.classList.add('fade-out');
	}
});

$$('button[data-step-submit]').forEach($e => $e.disabled = false);

processController.start();

// version check
$('dt[data-name="version"] + dd')?.insertAdjacentHTML(
	'beforeend',
	`<button data-action="versionCheck"><i class="icon fas fa-sync"></i> ${lang['version_check']}</button>`
);
$('button[data-action="versionCheck"]')?.addEventListener('click', function () {
	this.disabled = true;

	this.innerHTML = lang['version_check_active'];

	fetchAction('get_latest_version').then(data => {
		if (data['upToDate'] === true) {
			this.outerHTML = getNoteHtml(lang['version_check_latest'], 'success');
		} else if (data['upToDate'] === false) {
			const text = lang['version_check_newer'].replace('{1}', '' + data['latest_version']);
			this.outerHTML = getNoteHtml(text, 'warning');
		} else {
			this.outerHTML = getNoteHtml(lang['version_check_error'], 'warning');
		}
	});
});

if (documentData('processName') === 'install') {
	if (typeof lang['powered_by_phrases'] === 'string') {
		const phraseCycleIntervalMs = 7000;
		cycleElementText($('.powered-by-phrase'), lang['powered_by_phrases'].split("\n"), phraseCycleIntervalMs);
	}
}
