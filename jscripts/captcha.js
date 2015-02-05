var captcha = {
	refresh: function()
	{
		var imagehash_value = $('#imagehash').val();

		$.ajax(
		{
			url: 'xmlhttp.php?action=refresh_captcha&imagehash='+imagehash_value,
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
			$('#imagehash').val(json.imagehash);
		}

		$('#imagestring').removeClass('error valid').val('').removeAttr('aria-invalid').removeData('previousValue')
						.next('label').remove();
	}
};