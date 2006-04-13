var inlineEditor = Class.create();

inlineEditor.prototype = {
	initialize: function(url, options)
	{
		this.url = url;
		this.elements = new Array();
		this.currentIndex = -1;

		if(!options.className && !options.textBox)
		{
			alert('You need to specify either a className or textBox in the options.');
			return false;
		}
		if(options.spinnerImage)
		{
			this.spinnerImage = options.spinnerImage;
		}
		if(options.editButton == true)
		{
			this.editButton = true;
		}
		if(options.textBox)
		{
			if(!$(options.textBox))
			{
				return false;
			}
			this.elements[0] = $(options.textBox);
			this.makeEditable($(options.textBox));
		}
		else
		{
			this.elements = DomLib.getElementsByClassName(document, "*", options.className);
			if(this.elements)
			{
				for(var i=0;i<this.elements.length;i++)
				{
					this.elements[i].index = i;
					this.makeEditable(this.elements[i]);
				}
			}
		}
	},

	makeEditable: function(element)
	{
		if(!this.editButton)
		{
			if(element.title != "")
			{
				element.title = element.title+" ";
			}
			element.title = element.title+"(Click and hold to edit)";
			element.onmousedown = this.onMouseDown.bindAsEventListener(this);
		}
		else
		{
			editButton = $(element.id+"_inlineedit");
			if(editButton)
			{
				editButton.onclick = function() {this.onButtonClick(element.id)}.bindAsEventListener(this);
			}
		}
	},

	onMouseDown: function(e)
	{
		element = Event.element(e);

		Event.stop(e);

		this.currentIndex = element.index;

		this.downTime = 0;

		this.timeout = setTimeout(this.showTextbox.bindAsEventListener(this), 1200);

		element.onmouseup = this.onMouseUp.bindAsEventListener(this);

		return false;
	},

	onMouseUp: function(e)
	{
		clearTimeout(this.timeout);

		this.currentIndex = -1;
	},

	onButtonClick: function(id)
	{
		if($(id))
		{
			this.currentIndex = $(id).index;
			this.showTextbox();
		}
		return false;
	},

	showTextbox: function()
	{
		this.element = this.elements[this.currentIndex];

		if(!this.element.parentNode || typeof(this.element.index) == "undefined")
		{
			return false;
		}
		this.currentIndex = this.element.index;
		value = this.element.innerHTML;
		this.textbox = document.createElement("input");
		this.textbox.style.width = "95%";
		this.textbox.type = "text";
		this.textbox.onblur = this.onBlur.bindAsEventListener(this);
		this.textbox.onkeypress = this.onKeyPress.bindAsEventListener(this);
		this.textbox.setAttribute("autocomplete", "off");
		this.textbox.name = "value";
		this.textbox.index = this.element.index;
		this.element.style.display = "none";
		this.textbox.value = MyBB.unHTMLchars(value);
		this.element.parentNode.insertBefore(this.textbox, this.element);
		this.textbox.focus();
	},

	onBlur: function(e)
	{
		this.hideTextbox();
	},

	onKeyPress: function(e)
	{
		if((e.keyCode == Event.KEY_RETURN || e.keyCode == Event.KEY_ESC))
		{
			this.hideTextbox();
		}
	},

	onSubmit: function(e)
	{
		this.hideTextbox();
	},

	hideTextbox: function()
	{
		this.textbox.onblur = "";
		newValue = MyBB.HTMLchars(this.textbox.value);
		if(newValue != this.element.innerHTML)
		{
			this.element.innerHTML = newValue;
			this.lastIndex = this.currentIndex;
			postData = "value="+encodeURIComponent(this.textbox.value)
			if(this.spinnerImage)
			{
				this.showSpinner();
			}
			if(this.element.id)
			{
				idInfo = this.element.id.split("_");
				if(idInfo[0] && idInfo[1])
				{
					postData = postData+"&"+idInfo[0]+"="+idInfo[1];
				}
			}
			new ajax(this.url, {method: 'post', postBody: postData, onComplete: this.onComplete.bindAsEventListener(this)});
		}
		this.element.style.display = "";
		this.currentIndex = -1;
		Element.remove(this.textbox);
	},

	onComplete: function(request)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/))
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);
			this.element.innerHTML = this.oldValue;
			if(!message[1])
			{
				message[1] = "An unknown error occurred.";
			}
			alert('There was an error performing the update.\n\n'+message[1]);
		}
		else if(request.responseText)
		{
			this.element.innerHTML = MyBB.HTMLchars(request.responseText);
		}
		if(this.spinnerImage)
		{
			this.hideSpinner();
		}
	},

	showSpinner: function()
	{
		if(!this.spinnerImage)
		{
			return false;
		}
		if(!this.spinner)
		{
			this.spinner = document.createElement("img");
			this.spinner.src = this.spinnerImage;
			this.spinner.alt = "Saving changes.."
			this.spinner.style.verticalAlign = "middle";
			this.spinner.style.paddingRight = "3px";
		}
		this.textbox.parentNode.insertBefore(this.spinner, this.textbox);
	},

	hideSpinner: function()
	{
		if(!this.spinnerImage)
		{
			return false;
		}
		Element.remove(this.spinner);
	}
}