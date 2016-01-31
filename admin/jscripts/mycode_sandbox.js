function MyCodeSandbox(url, button, regex_textbox, replacement_textbox, test_textbox, html_textbox, actual_div)
{
    if(button && regex_textbox && replacement_textbox && test_textbox && html_textbox && actual_div)
    {
        this.url = url;
        this.button = button;
        this.regex_textbox = regex_textbox;
        this.replacement_textbox = replacement_textbox;
        this.test_textbox = test_textbox;
        this.html_textbox = html_textbox;
        this.actual_div = actual_div;

        $(button).on('click', function(e) {
            e.preventDefault();
            this.update();
        }.bind(this));
    }
}

MyCodeSandbox.prototype.update = function(e)
{
    postData = "regex="+encodeURIComponent($(this.regex_textbox).val())+"&replacement="+encodeURIComponent($(this.replacement_textbox).val())+"&test_value="+encodeURIComponent($(this.test_textbox).val())+"&my_post_key="+encodeURIComponent(my_post_key);

    $.ajax(
    {
        url: this.url,
        async: true,
        method: 'post',
        data: postData,
        complete: function (request)
        {
            this.onComplete(request);
        }.bind(this)
    });
};

MyCodeSandbox.prototype.onComplete = function(request)
{
	if(request.responseText.match(/<error>(.*)<\/error>/))
	{
		message = request.responseText.match(/<error>(.*)<\/error>/);

		if(!message[1])
		{
			message[1] = lang.unknown_error;
		}

		alert(lang.mycode_sandbox_test_error + '\n\n' + message[1]);
	}
	else if(request.responseText)
	{
		$(this.actual_div).html(request.responseText);
		$(this.html_textbox).val(request.responseText);
	}

	return true;
};