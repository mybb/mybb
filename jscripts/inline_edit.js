var inlineEditor = Class.create();

inlineEditor.prototype = {
	initialize: function(url, options)
	{
		if(use_xmlhttprequest != 1)
		{
			return false;
		}

		this.url = url;
		this.elements = new Array();
		this.currentElement = '';

		this.options = options;
		if(!options.className)
		{
			alert('You need to specify a className in the options.');
			return false;
		}

		this.className = options.className;
		if(options.spinnerImage)
		{
			this.spinnerImage = options.spinnerImage;
		}

		this.elements = $$('.'+options.className);
		if(this.elements)
		{
			this.elements.each(function(element) {
				if(element.id)
				{
					this.makeEditable(element);
				}
			}.bind(this));
		}
		return true;
	},

	makeEditable: function(element)
	{
		if(element.title != "")
		{
			element.title = element.title+" ";
		}

		if(!this.options.lang_click_edit)
		{
			this.options.lang_click_edit = "(Click and hold to edit)";
		}

		element.title = element.title+this.options.lang_click_edit;
		element.onmousedown = this.onMouseDown.bindAsEventListener(this);
		return true;
	},

	onMouseDown: function(e)
	{
		var element = Event.element(e);
		Event.stop(e);

		if(this.currentElement != '')
		{
			return false;
		}

		// Fix for konqueror which likes to set event element as the text not the link
		if(typeof(element.id) == "undefined" && typeof(element.parentNode.id) != "undefined")
		{
			element.id = element.parentNode.id;
		}

		this.currentElement = element.id;
		this.timeout = setTimeout(this.showTextbox.bind(this), 1200);
		document.onmouseup = this.onMouseUp.bindAsEventListener(this);
		return false;
	},

	onMouseUp: function(e)
	{
		clearTimeout(this.timeout);
		Event.stop(e);	
		return false;
	},

	onButtonClick: function(id)
	{
		if($(id))
		{
			this.currentElement = id;
			this.showTextbox();
		}
		return false;
	},

	showTextbox: function()
	{
		this.element = $(this.currentElement);

		if(typeof(this.element.parentNode) == "undefined" || typeof(this.element.id) == "undefined")
		{
			return false;
		}

		this.oldValue = this.element.innerHTML;
		this.testNode = this.element.parentNode;

		if(!this.testNode)
		{
			return false;
		}

		this.cache = this.testNode.innerHTML;

		this.textbox = document.createElement("input");
		this.textbox.style.width = "95%";
		this.textbox.maxLength="85";
		this.textbox.className = "textbox";
		this.textbox.type = "text";
		Event.observe(this.textbox, "blur", this.onBlur.bindAsEventListener(this));
		Event.observe(this.textbox, "keypress", this.onKeyUp.bindAsEventListener(this));
		this.textbox.setAttribute("autocomplete", "off");
		this.textbox.name = "value";
		this.textbox.index = this.element.index;
		this.textbox.value = MyBB.unHTMLchars(this.oldValue);

		Element.remove(this.element);
		this.testNode.innerHTML = '';
		this.testNode.appendChild(this.textbox);
		this.textbox.focus();
		return true;
	},

	onBlur: function(e)
	{
		this.hideTextbox();
		return true;
	},

	onKeyUp: function(e)
	{
		if(e.keyCode == Event.KEY_RETURN)
		{
			this.hideTextbox();
		}
		else if(e.keyCode == Event.KEY_ESC)
		{
			this.cancelEdit();
		}
		return true;
	},

	onSubmit: function(e)
	{
		this.hideTextbox();
		return true;
	},

	hideTextbox: function()
	{
		if(!this.currentElement)
			return;
		Event.stopObserving(this.textbox, "blur", this.onBlur.bindAsEventListener(this));
		var newValue = this.textbox.value;

		if(typeof(newValue) != "undefined" && newValue != '' && MyBB.HTMLchars(newValue) != this.oldValue)
		{
			this.testNode.innerHTML = this.cache;
			
			this.element = $(this.currentElement);
			this.element.innerHTML = MyBB.HTMLchars(newValue);
			this.element.onmousedown = this.onMouseDown.bindAsEventListener(this);
			this.lastElement = this.currentElement;
			postData = "value="+encodeURIComponent(newValue);
			
			if(this.spinnerImage)
			{
				this.showSpinner();
			}

			idInfo = this.element.id.split("_");
			if(idInfo[0] && idInfo[1])
			{
				postData = postData+"&"+idInfo[0]+"="+idInfo[1];
			}
			new Ajax.Request(this.url, {method: 'post', postBody: postData, onComplete: this.onComplete.bind(this)});
		}
		else
		{
			Element.remove(this.textbox);
			this.testNode.innerHTML = this.cache;
 			this.element = $(this.currentElement);
			this.element.onmousedown = this.onMouseDown.bindAsEventListener(this);
		}
		this.currentElement = '';
		return true;
	},

	cancelEdit: function()
	{
		Element.remove(this.textbox);
		this.testNode.innerHTML = this.cache;
		this.element = $(this.currentElement);
		this.element.onmousedown = this.onMouseDown.bindAsEventListener(this);
		this.currentElement = '';
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
			
			if(this.spinnerImage)
			{
				this.hideSpinner();
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
		this.currentIndex = -1;
		return true;
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

			if(saving_changes)
			{
				this.spinner.alt = saving_changes;
			}
			else
			{
				this.spinner.alt = "Saving changes..";
			}

			this.spinner.style.verticalAlign = "middle";
			this.spinner.style.paddingRight = "3px";
		}
		this.testNode.insertBefore(this.spinner, this.testNode.firstChild);
		return true;
	},

	hideSpinner: function()
	{
		if(!this.spinnerImage)
		{
			return false;
		}

		Element.remove(this.spinner);
		return true;
	}
};