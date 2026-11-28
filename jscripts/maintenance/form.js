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


/*
 * The following section implements random password
 * generation for user sign-up.
 */

function random_str(length = 8) {
    const set = [
        ..."0123456789",
        ..."ABCDEFGHIJKLMNOPQRSTUVWXYZ",
        ..."abcdefghijklmnopqrstuvwxyz"
    ];
    
    let str = [];
    
    // better than Math.random()
    const randomInt = (min, max) => {
        const range = max - min + 1;
        const limit = Math.floor(0x100000000 / range) * range;
        const array = new Uint32Array(1);
        do {
            crypto.getRandomValues(array);
        } while (array[0] >= limit);
        return min + (array[0] % range);
    };
    
    // at least one character '0', '1', ..., '9'
    str.push(set[randomInt(0, 9)]);

    // at least one character 'A', 'B', ..., 'Z'
    str.push(set[randomInt(10, 35)]);

    // at least one character 'a', 'b', ..., 'z'
    str.push(set[randomInt(36, 61)]);

    length -= 3;

    for (let i = 0; i < length; i++) {
        str.push(set[randomInt(0, 61)]);
    }

    // Fisher-Yates Shuffle
    for (let i = str.length - 1; i > 0; i--) {
        const j = randomInt(0, i);
        [str[i], str[j]] = [str[j], str[i]];
    }
    
    return str.join('');
}

function onClick_newRandomPassword(event) {
    let checkbox = document.getElementById("randompassword");
    let newpw = random_str();

    event.preventDefault();
    
    document.getElementById('password').value = newpw;
    document.getElementById('password2').value = newpw;

    checkbox.checked = true;
    checkbox.dispatchEvent(new Event("change"));
}


                                    