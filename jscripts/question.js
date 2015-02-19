var question = {
	refresh: function()
	{
		var question_id = $('#question_id').val();

		$.ajax(
		{
			url: 'xmlhttp.php?action=refresh_question&question_id='+question_id,
			method: 'get',
			dataType: 'json',
	        complete: function (request)
	        {
	        	question.refresh_complete(request);
	        }
		});

		return false;
	},

	refresh_complete: function(request)
	{
		var json = $.parseJSON(request.responseText);
		if(json.hasOwnProperty("errors"))
		{
			$.each(json.errors, function(i, message)
			{
				$.jGrowl(lang.question_fetch_failure + ' ' + message, {theme:'jgrowl_error'});
			});
		}
		else if(json.question && json.sid)
		{
			$("#question").text(json.question);
			$("#question_id").val(json.sid);
		}

		$('#answer').removeClass('error valid').val('').removeAttr('aria-invalid').removeData('previousValue')
					.next('label').remove();
	}
};