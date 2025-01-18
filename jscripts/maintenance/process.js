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

		setInterstitial(`${timeFormatted} s`);
	}

	const finishUrl = documentData('finishUrl');

	if (finishUrl !== null) {
		let preRedirectTasks;

		if (showExecutionTime === true) {
			preRedirectTasks = [];
		} else {
			const preloadTimeoutMs = 4000; // multiple of interstitial animation duration

			const $interstitial = setInterstitial();

			preRedirectTasks = [
				new Promise(resolve => {
					$interstitial.addEventListener('animationend', e => {
						if (e.animationName === 'interstitial-fade-in') {
							resolve();
						}
					});
				}),
				Promise.race([
					new Promise(resolve => prefetch(finishUrl, resolve)),

					 // fallback for unsupported prefetch events
					new Promise(resolve => setTimeout(resolve, preloadTimeoutMs)),
				]),
			];
		}

		Promise.all(preRedirectTasks).then(() => {
			window.location = finishUrl;
		});
	}
});

window.addEventListener('pageshow', (event) => {
	if (event.persisted) {
		window.location.reload();
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
