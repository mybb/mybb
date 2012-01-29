var validationMessageTag = 'p';

function setValid(inputElement, messageText) {
	$(inputElement).removeClass('invalid');
	$(inputElement).addClass('valid');

	var messageString = '<' + validationMessageTag + ' class="valid">' + messageText + '</' + validationMessageTag + '>';

	$('.' + $(inputElement).attr('name') + 'Messages').html(messageString)
	                                                  .fadeIn();
}

function setInvalid(inputElement, messageText) {
	$(inputElement).removeClass('valid');
	$(inputElement).addClass('invalid');

	var messageString = '<' + validationMessageTag + ' class="invalid">' + messageText + '</' + validationMessageTag + '>';

	$('.' + $(inputElement).attr('name') + 'Messages').html(messageString)
	                                                  .fadeIn();
}

$(document).ready(function() {
	$('input.validateEmailAddress').focusout(function() {
		var emailAddress = $(this).attr('value');
		var validRegex = /^[a-zA-Z0-9\-_+\.]+\@[a-zA-Z0-9\-_+\.]+\.[a-zA-Z0-9]{2,4}$/

		if(!emailAddress.match(validRegex)) {
			setInvalid($(this), 'Not a valid email address.');
		} else {
			setValid($(this), 'Email address is valid.');
		}
	});
});
