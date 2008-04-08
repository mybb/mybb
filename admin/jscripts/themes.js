var ThemeSelector = Class.create();

ThemeSelector.prototype = {

    url: null,
	save_url: null,
    selector: null,
    stylesheet: null,
	sid: null,
	selector_form: null,
	tid: null,
    spinnerImage: "../images/spinner_big.gif",
	miniSpinnerImage: "../images/spinner.gif",
	isajax: false,
	
	background: null,
	width: null,
	color: null,
	extra: null,
	font: null,
	font_family: null,
	font_size: null,
	font_style: null,
	font_weight: null,
    
    initialize: function(url, save_url, selector, stylesheet, sid, selector_form, tid)
    {
		if(url && save_url && selector && stylesheet && sid && selector_form && tid)
        {
            this.url = url;
			this.save_url = save_url;
            this.selector = selector;
            this.stylesheet = stylesheet;
			this.sid = sid;
			this.selector_form = selector_form;
			this.tid = tid;
			
			this.background = $("background").value;
			this.width = $("width").value;
			this.color = $("color").value;
			this.extra = $("extra").value;
			this.font = $("font").value;
			this.font_family = $("font_family").value;
			this.font_size = $("font_size").value;
			this.font_style = $("font_style").value;
			this.font_weight = $("font_weight").value;
			
			Event.observe(window, "unload", this.saveCheck.bindAsEventListener(this, false));
			Event.observe($("save"), "click", this.save.bindAsEventListener(this, true));
			Event.observe(this.selector, "change", this.updateSelector.bindAsEventListener(this));
			Event.observe(this.selector_form, "submit", this.updateSelector.bindAsEventListener(this));
		}
    },
    
    updateSelector: function(e)
    {
        Event.stop(e);
		
		this.saveCheck(e, true);
		
        postData = "sid="+encodeURIComponent(this.sid)+"&selector="+encodeURIComponent(this.selector.value);
		
		$("mini_spinner").innerHTML = "<img src=\""+this.miniSpinnerImage+"\" alt=\"\" /> ";

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
			if($("saved").innerHTML)
			{
				var saved = $("saved").innerHTML;
			}
			this.stylesheet.innerHTML = request.responseText;
		}
		
		this.background = $("background").value;
		this.width = $("width").value;
		this.color = $("color").value;
		this.extra = $("extra").value;
		this.font = $("font").value;
		this.font_family = $("font_family").value;
		this.font_size = $("font_size").value;
		this.font_style = $("font_style").value;
		this.font_weight = $("font_weight").value;
		
		$("mini_spinner").innerHTML = "";
		
		if(saved)
		{
			$("saved").innerHTML = saved;
			window.setTimeout("$(\"saved\").innerHTML = \"\";", 30000);
		}

		return true;
	},
	
	saveCheck: function(e, isajax)
    {
		if(this.background != $("background").value || this.width != $("width").value || this.color != $("color").value || this.extra != $("extra").value || this.font != $("font").value || this.font_family != $("font_family").value || this.font_size != $("font_size").value || this.font_style != $("font_style").value || this.font_weight != $("font_weight").value)
		{
			confirmReturn = confirm("Do you want to save your changes first?");
			if(confirmReturn == true)
			{
				this.save(false, isajax);
				alert('Saved');
			}
		}
		return true;
    },
	
	save: function(e, isajax)
    {
		if(e)
		{
        	Event.stop(e);
		}
		
        postData = "background="+encodeURIComponent($("background").value)+"&width="+encodeURIComponent($("width").value)+"&color="+encodeURIComponent($("color").value)+"&extra="+encodeURIComponent($("extra").value)+"&font="+encodeURIComponent($("font").value)+"&font_family="+encodeURIComponent($("font_family").value)+"&font_size="+encodeURIComponent($("font_size").value)+"&font_style="+encodeURIComponent($("font_style").value)+"&font_weight="+encodeURIComponent($("font_weight").value)+"&selector="+encodeURIComponent(this.selector.value)+"&sid="+encodeURIComponent(this.sid)+"&tid="+encodeURIComponent(this.tid)+"&my_post_key="+encodeURIComponent(my_post_key);
		
		if(isajax == true)
		{
			postData += "&ajax=1";
		}
		
		this.isajax = isajax;
		
		if(isajax == true)
		{
			this.spinner2 = new ActivityIndicator("body", {image: this.spinnerImage});
		}
		
		if(isajax == true)
		{
			new Ajax.Request(this.save_url, {
				method: 'post',
				postBody: postData,
				onComplete: this.onSaveComplete.bind(this)
			});
		}
		else
		{
			new Ajax.Request(this.save_url, {
				method: 'post',
				postBody: postData,
				onComplete: this.onUnloadSaveComplete.bind(this)
			});
		}
    },
    
	onSaveComplete: function(request)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/))
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);

			if(!message[1])
			{
				message[1] = "An unknown error occurred.";
			}
			
			alert('There was an error fetching the test results.\n\n'+message[1]);
			return false;
		}
		else if(request.responseText)
		{
			$("saved").innerHTML = request.responseText;
		}

		this.spinner2.destroy();

		return true;
	},
	
	onUnloadSaveComplete: function(request)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/))
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);

			if(!message[1])
			{
				message[1] = "An unknown error occurred.";
			}
			
			alert('There was an error fetching the test results.\n\n'+message[1]);
			return false;
		}
		
		return true;
	}
}