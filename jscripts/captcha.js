var captcha = {
	refresh: function()
	{
		imagehash = $("#imagehash");
		var imagehash_value = imagehash.attr("value");

		$.ajax(
		{
			url: 'xmlhttp.php?action=refresh_captcha&imagehash='+imagehash_value,
			async: true,
			method: 'get',
			dataType: 'json',
	        complete: function (request)
	        {
	        	captcha.refresh_complete(request);
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
				$.jGrowl(lang.captcha_fetch_failure + ' ' + message);
			});
		}
		else if(json.imagehash)
		{
			$("#captcha_img").attr("src", "captcha.php?action=regimage&imagehash=" + json.imagehash);
			imagehash.attr("value", json.imagehash);
		}

		var imagestring_status = $("#imagestring_status");

		imagestring_status.removeClass("validation_success")
						  .removeClass("validation_error")
						  .removeClass("validation_loading")
						  .html("")
						  .hide();

		var imagestring = $("#imagestring");

		imagestring.addClass("textbox").attr("value", "");
	}
};