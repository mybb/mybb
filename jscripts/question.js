var question = {
	refresh: function()
	{
		var question_id = $("#question_id").attr("value");

		$.ajax(
		{
			url: 'xmlhttp.php?action=refresh_question&question_id='+question_id,
			async: true,
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
				$.jGrowl(lang.question_fetch_failure + ' ' + message);
			});
		}
		else if(json.question)
		{
			$("#question").text(json.question);
		}
		
		if(json.sid)
		{
			$("#question_id").val(json.sid);
		}
		
		var answer_status = $("#answer_status");

		answer_status.removeClass("validation_success")
						  .removeClass("validation_error")
						  .removeClass("validation_loading")
						  .html("");
						  
		$('#answer').addClass('textbox');
		$('#answer').val('');
	}
};