var autoComplete = Class.create();

autoComplete.prototype = {
	
	initialize: function(textbox, url, options)
	{
		if(!$(textbox))
		{
			return false;
		}
		
		this.status = '';
		
		this.cache = new Array();
	
		this.lastValue = '';
		
		this.textbox = $(textbox);
		this.textbox.setAttribute("autocomplete", "off");
		this.textbox.autocompletejs = this;
		Event.observe(this.textbox, "keypress", this.onKeyPress.bindAsEventListener(this));
		
		this.url = url;
		
		this.currentIndex = -1;
		this.valueSpan = options.valueSpan;
		this.urlParam = options.urlParam;
		if(options.minChars)
		{
			this.minChars = options.minChars;
		}
		else
		{
			this.minChars = 3;
		}
		this.popup = document.createElement("div");
		this.popup.id = textbox+"_popup";
		this.popup.style.position = "absolute";
		this.popup.className = "autocomplete";
		this.popup.style.display = "none";
		document.body.appendChild(this.popup);
		
		this.timeout = false;
		
		Event.observe(document, "unload", this.clearCache.bindAsEventListener(this));
	},

	
	onKeyPress: function(e)
	{
		if(this.timeout)
		{
			clearTimeout(this.timeout);
		}
		switch(e.keyCode)
		{
			case Event.KEY_LEFT:
			case Event.KEY_RIGHT:
				break;
			case Event.KEY_UP:
				if(this.currentIndex > 0)
				{
					this.highlightItem(this.currentIndex-1);
				}
				Event.stop(e);
				break;
			case Event.KEY_DOWN:
				if(this.currentIndex+1 < this.popup.childNodes.length)
				{
					this.highlightItem(this.currentIndex+1);
				}
				Event.stop(e);
				break;
			case Event.KEY_TAB:
				if(this.popup.display != "none" && this.currentIndex > -1)
				{
					this.updateValue(this.popup.childNodes[this.currentIndex]);
					this.hidePopup(1);
					return false;
				}
			case Event.KEY_RETURN:
				if(this.popup.display != "none" && this.currentIndex > -1)
				{
					this.updateValue(this.popup.childNodes[this.currentIndex]);
					this.hidePopup(1);
				}
				Event.stop(e);
				break;
			case Event.KEY_ESC:
				this.hidePopup();
				break;
			default:
				this.status = '';
				setTimeout("$('"+this.textbox.id+"').autocompletejs.doRequest();", 500);
				break;
		}
	},
	
	buildURL: function()
	{
		if(!this.urlParam)
		{
			this.urlParam = "query";
		}
		var separator = "?";
		if(this.url.indexOf("?") >= 0)
		{
			separator = "&";
		}
		return this.url+separator+this.urlParam+"="+encodeURIComponent(this.textbox.value);		
	},
	
	doRequest: function()
	{
		if(this.textbox.value.length >= this.minChars)
		{
			if(this.lastValue == this.textbox.value)
			{
				return false;
			}
			this.lastValue = this.textbox.value;
						
			if(this.cache[this.textbox.value])
			{
				this.popup.innerHTML = this.cache[this.textbox.value];
				this.onComplete();
			}
			new ajax(this.buildURL(), {method: 'get', onComplete: this.onComplete.bindAsEventListener(this)});
		}
	},
	
	onComplete: function(request)
	{
		if(this.status == 'hidden')
		{
			return;
		}
		this.hidePopup();		
		// Cached results or fresh ones?
		if(request)
		{
			if(request.responseText.charAt(0) != "<")
			{
				return false;
			}
			this.popup.innerHTML = request.responseText;
			this.cache[this.textbox.value] = this.popup.innerHTML;
		}
		this.currentIndex = -1;
		if(this.popup.childNodes.length < 1)
		{
			return false;
		}
		for(var i=0;i<this.popup.childNodes.length;i++)
		{
			if (this.popup.childNodes[i].nodeType == 3 && !/\S/.test(this.popup.childNodes[i].nodeValue))	
			{
				this.popup.removeChild(this.popup.childNodes[i]);
			}		
		}
		for(var i=0;i<this.popup.childNodes.length;i++)
		{
			var item = this.popup.childNodes[i];
			item.index = i;
			item.style.padding = "2px";
			item.style.height = "1em";
			Event.observe(item, "mouseover", this.itemOver.bindAsEventListener(this));
			Event.observe(item, "click", this.itemClick.bindAsEventListener(this));
		}
		var maxHeight = 100;
		if(this.popup.offsetHeight > 0 && this.popup.offsetHeight < maxHeight)
		{
			this.popup.style.overflow = "hidden";	
		}
		else if(MyBB.browser == "mozilla")
		{
			this.popup.style.maxHeight = maxHeight+"px";
		}
		else
		{
			this.popup.style.height = maxHeight+"px";
			this.popup.style.overflowY = "auto";
		}
		this.popup.style.width = this.textbox.offsetWidth-2+"px";
		element = this.textbox;
		offsetTop = offsetLeft = 0;
		do
		{
			offsetTop += element.offsetTop || 0;
			offsetLeft += element.offsetLeft || 0;
			element = element.offsetParent;
		} while(element);
		
		this.popup.style.marginTop = "-1px";
		if(MyBB.browser == "ie")
		{
			this.popup.style.left = offsetLeft+1+"px";			
		}
		else
		{
			this.popup.style.left = offsetLeft+"px";
		}
		this.popup.style.top = offsetTop+this.textbox.offsetHeight+"px";
		
		this.popup.scrollTop = 0;		
		Event.observe(document, "click", this.hidePopup.bindAsEventListener(this));
		this.highlightItem(0);
		this.popup.style.display = "";
	},
	
	hidePopup: function(hard)
	{
		this.popup.style.display = "none";
		this.textbox.onkeypress = '';
		if(typeof(hard) != 'undefined' && hard == '1')
		{
			this.status = 'hidden';
		}
	},
	
	updateValue: function(selectedItem)
	{
		if(this.valueSpan && selectedItem.innerHTML)
		{
			var items = selectedItem.getElementsByTagName("SPAN");
			if(items)
			{
				for(var i=0;i<items.length;i++)
				{
					if(items[i].className == this.valueSpan)
					{
						textBoxValue = items[i].innerHTML;
						break;
					}
				}
			}
		}
		
		else if(!this.valueSpan && selectedItem.innerHTML)
		{
			textBoxValue = selectedItem.innerHTML;
		}
		
		else
		{
			textBoxValue = selectedItem;
		}
		this.textbox.value = textBoxValue;
	},
	
	itemOver: function(event)
	{
		var element = Event.findElement(event, 'DIV');
		element.style.cursor = 'pointer';
		selectedItem = element.index;
		this.highlightItem(selectedItem);
	},
	
	itemClick: function(event)
	{
		var element = Event.findElement(event, 'DIV');
		selectedItem = element.index;
		this.updateValue(this.popup.childNodes[selectedItem]);
		this.hidePopup(1);
		this.textbox.focus();
	},
	
	highlightItem: function(selectedItem)
	{
		if(this.currentIndex != -1)
		{
			this.popup.childNodes[this.currentIndex].className = "";
		}
		this.currentIndex = selectedItem;
		this.popup.childNodes[this.currentIndex].className = "autocomplete_selected";
	},
	
	clearCache: function()
	{
		this.cache = '';
	}
};