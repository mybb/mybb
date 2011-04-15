var MyCodeSandbox = Class.create();

MyCodeSandbox.prototype = {

    url: null,
    button: null,
    regex_textbox: null,
    replacement_textbox: null,
    test_textbox: null,
    html_textbox: null,
    actual_div: null,
    spinnerImage: "../images/spinner_big.gif",
    
    initialize: function(url, button, regex_textbox, replacement_textbox, test_textbox, html_textbox, actual_div)
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
            
            Event.observe(button, "click", this.update.bindAsEventListener(this));
        }
    },
    
    update: function(e)
    {
        Event.stop(e);
        postData = "regex="+encodeURIComponent(this.regex_textbox.value)+"&replacement="+encodeURIComponent(this.replacement_textbox.value)+"&test_value="+encodeURIComponent(this.test_textbox.value)+"&my_post_key="+encodeURIComponent(my_post_key);

		this.spinner = new ActivityIndicator("body", {image: this.spinnerImage});

        new Ajax.Request(this.url, {
            method: 'post',
            postBody: postData,
            onComplete: this.onComplete.bind(this)
        });
    },
    
	onComplete: function(request)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/))
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);

			if(!message[1])
			{
				message[1] = "An unknown error occurred.";
			}
			
			alert('There was an error fetching the test results.\n\n'+message[1]);
		}
		else if(request.responseText)
		{
			this.actual_div.innerHTML = request.responseText;
			this.html_textbox.value = request.responseText;
		}

		this.spinner.destroy();

		return true;
	}
}