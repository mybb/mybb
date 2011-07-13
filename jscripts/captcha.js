var captcha = {
	refresh: function()
	{
		var imagehash = $('imagehash').value;
		this.spinner = new ActivityIndicator("body", {image: imagepath + "/spinner_big.gif"});
		new Ajax.Request('xmlhttp.php?action=refresh_captcha&imagehash='+imagehash, {
			method: 'get',
			onComplete: function(request) { captcha.refresh_complete(request); }
		});
		return false;
	},

	refresh_complete: function(request)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/))
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);

			if(!message[1])
			{
				message[1] = "An unknown error occurred.";
			}

			alert('There was an error fetching the new captcha.\n\n'+message[1]);
		}
		else if(request.responseText)
		{
			$('captcha_img').src = "captcha.php?action=regimage&imagehash="+request.responseText;
			$('imagehash').value = request.responseText;
		}

		if(this.spinner)
		{
			this.spinner.destroy();
			this.spinner = '';
		}

		Element.removeClassName('imagestring_status', "validation_success");
		Element.removeClassName('imagestring_status', "validation_error");
		Element.removeClassName('imagestring_status', "validation_loading");
		$('imagestring_status').innerHTML = '';
		$('imagestring_status').hide();
		$('imagestring').className = 'textbox';
		$('imagestring').value = '';
	}
};