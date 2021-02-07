$(function () {
	$.validator.messages.required = lang.js_validator_not_empty;

	$('#registration_form').validate({
		rules: {
			username: {
				required: true,
				minlength: regsettings.minnamelength,
				maxlength: regsettings.maxnamelength,
				remote: {
					url: 'xmlhttp.php?action=username_availability',
					type: 'post',
					dataType: 'json',
					data:
					{
						my_post_key: my_post_key
					},
				},
			},
			email: {
				required: true,
				email: true,
				remote: {
					url: 'xmlhttp.php?action=email_availability',
					type: 'post',
					dataType: 'json',
					data:
					{
						my_post_key: my_post_key
					},
				},
			},
			email2: {
				required: true,
				email: true,
				equalTo: '#email'
			},
		},
		messages: {
			username: {
				required: lang.js_validator_no_username,
				minlength: lang.js_validator_username_length,
				maxlength: lang.js_validator_username_length,
			},
			email: lang.js_validator_invalid_email,
			email2: lang.js_validator_email_match,
		},
		errorPlacement: function (error, element) {
			if (element.is(':checkbox') || element.is(':radio'))
				error.insertAfter($('input[name=\"' + element.attr('name') + '\"]').last().next('span'));
			else
				error.insertAfter(element);
		}
	});

	var requiredfields = JSON.parse(regsettings.requiredfields);
	$.each(requiredfields, function () {
		var input_type = 'input';
		var depth = "";
		if (this.type == "textarea") {
			input_type = "textarea";
		} else if (this.type == "multiselect") {
			input_type = "select";
			depth = "[]";
		} else if (this.type == "checkbox") {
			depth = "[]";
		}

		$(input_type + '[name="profile_fields[' + this.fid + ']' + depth + '"]').rules('add', {
			required: true,
		});
	});

	if (regsettings.captchaimage == "1" && regsettings.captchahtml == "1") {
		$('#imagestring').rules('add', {
			required: true,
			remote: {
				url: 'xmlhttp.php?action=validate_captcha',
				type: 'post',
				dataType: 'json',
				data:
				{
					imagehash: function () {
						return $('#imagehash').val();
					},
					my_post_key: my_post_key
				},
			},
			messages: {
				required: lang.js_validator_no_image_text
			}
		});
	}

	if (regsettings.securityquestion == "1" && regsettings.questionexists == "1") {
		$('#answer').rules('add', {
			required: true,
			remote: {
				url: 'xmlhttp.php?action=validate_question',
				type: 'post',
				dataType: 'json',
				data:
				{
					question: function () {
						return $('#question_id').val();
					},
					my_post_key: my_post_key
				},
			},
			messages: {
				required: lang.js_validator_no_security_question
			}
		});
	}

	if (regsettings.regtype !== "randompass") {
		$.validator.addMethod('passwordSecurity', function (value, element, param) {
			return !(
				($('#email').val() != '' && value == $('#email').val()) ||
				($('#username').val() != '' && value == $('#username').val()) ||
				($('#email').val() != '' && value.indexOf($('#email').val()) > -1) ||
				($('#username').val() != '' && value.indexOf($('#username').val()) > -1) ||
				($('#email').val() != '' && $('#email').val().indexOf(value) > -1) ||
				($('#username').val() != '' && $('#username').val().indexOf(value) > -1)
			);
		}, lang.js_validator_bad_password_security);

		if (regsettings.requirecomplexpasswords == "1") {
			$('#password').rules('add', {
				required: true,
				minlength: regsettings.minpasswordlength,
				remote: {
					url: 'xmlhttp.php?action=complex_password',
					type: 'post',
					dataType: 'json',
					data:
					{
						my_post_key: my_post_key
					},
				},
				passwordSecurity: '',
				messages: {
					minlength: lang.js_validator_password_length,
					required: lang.js_validator_password_length,
				}
			});
		} else {
			$('#password').rules('add', {
				required: true,
				minlength: regsettings.minpasswordlength,
				passwordSecurity: '',
				messages: {
					minlength: lang.js_validator_password_length,
					required: lang.js_validator_password_length
				}
			});
		}

		$('#password2').rules('add', {
			required: true,
			minlength: regsettings.minpasswordlength,
			equalTo: '#password',
			messages: {
				minlength: lang.js_validator_password_length,
				required: lang.js_validator_password_length,
				equalTo: lang.js_validator_password_matches
			}
		});
	}
});
