'use strict';

/**
 * Attribute-based <form> functionality.
 */

$$('[data-password-peekable]').forEach($e => {
	$e.setAttribute('spellcheck', 'false');

	$e.insertAdjacentHTML('afterend', `<div data-password-peek role="button" aria-pressed="false" title="${lang['show_password']}"><i class="icon far fa-eye"></i></div>`);

	$('[data-password-peek]', $e.parentNode).addEventListener('click', function (e) {
		const $input = $('input[type="text"], input[type="password"]', this.parentNode);

		const reveal = $input.type === 'password';

		$input.type = reveal ? 'text' : 'password';
		this.setAttribute('aria-pressed', reveal ? 'true' : 'false');

		e.preventDefault();
	});

	if ($e.getAttribute('data-password-revealed') === '1') {
		$('[data-password-peek]', $e.parentNode).click();
	}
});

$$('form').forEach(e => e.addEventListener('submit', function (e) {
	$$('[data-password-peekable]', e.target).forEach($input => {
		$input.setAttribute('type', 'password');
	});
}));

$$('[data-password-score]').forEach($e => {
	$e.insertAdjacentHTML('afterend', '<div class="password-score"><div></div></div>');

	const updateScore = () => {
		const result = zxcvbn($e.value, getTextInputValues());
		const $meter = $('.password-score', $e.closest('label'));

		if ($e.value !== '') {
			$meter.setAttribute('data-score', result.score);

			if (result.score <= 2) {
				setFieldNote($e, lang['weak_password'], 'warning');
			} else {
				removeFieldNote($e);
			}
		} else {
			$meter.removeAttribute('data-score');
			removeFieldNote($e);
		}
	};

	$e.addEventListener('keyup', updateScore);

	if ($e.value !== '') {
		updateScore();
	}
});

$$('form[data-submit-on-change] :is(input, select)').forEach($e => {
	$e.addEventListener('change', function (e) {
		e.target.closest('form').submit();
	});
});
