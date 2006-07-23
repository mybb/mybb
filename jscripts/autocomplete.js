var autoComplete = Class.create();

autoComplete.prototype = {
	
	initialize: function(textbox, url, options)
	{
		if(!$(textbox))
		{
			return false;
		}
		
		this.cache = new Array();
	
		this.lastValue = '';
		
		this.textbox = $(textbox);
		this.textbox.setAttribute("autocomplete", "off");
		this.textbox.autocompletejs = this;
		Event.observe(this.textbox, "keypress", this.onKeyPress.bindAsEventListener(this));
		if(this.textbox.form)
		{
			if(this.textbox.form.onsubmit)
			{
				this.oldOnSubmit = this.textbox.form.onsubmit;
			}
			this.textbox.form.onsubmit = function(e) { return this.onFormSubmit(); }.bind(this);
		}
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
		this.popup.style.position = "absolute";
		this.popup.className = "autocomplete";
		this.popup.style.display = "none";
		document.body.appendChild(this.popup);
		
		this.timeout = false;

		this.textbox.popup = this;
		
		Event.observe(document, "unload", this.clearCache.bindAsEventListener(this));
	},

	onFormSubmit: function()
	{
		if(this.currentIndex != -1)
		{
			return false;
		}
		this.textbox.setAttribute("autocomplete", "on");
		return true;
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
					this.scrollToItem(this.currentIndex-1);
					this.highlightItem(this.currentIndex-1);
					this.setTypeAhead(this.currentIndex);
				}
				Event.stop(e);
				break;
			case Event.KEY_DOWN:
				if(this.currentIndex+1 < this.popup.childNodes.length)
				{
					this.scrollToItem(this.currentIndex+1);
					this.highlightItem(this.currentIndex+1);
					this.setTypeAhead(this.currentIndex);
				}
				Event.stop(e);
				break;
			case Event.KEY_TAB:
				if(this.popup.display != "none" && this.currentIndex > -1)
				{
					this.updateValue(this.popup.childNodes[this.currentIndex]);
					this.hidePopup();
					return false;
				}
			case Event.KEY_RETURN:
				if(this.currentIndex != -1)
				{
					Event.stop(e);
				}
				if(this.popup.display != "none" && this.currentIndex > -1)
				{
					this.updateValue(this.popup.childNodes[this.currentIndex]);
					this.hidePopup();
				}
				break;
			case Event.KEY_ESC:
				this.hidePopup();
				break;
			default:
				this.currentKeyCode = e.keyCode;
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
		if(this.lastValue == this.textbox.value)
		{
			return false;
		}
		this.lastValue = this.textbox.value;
		cacheValue = this.textbox.length+this.textbox.value;
		if(this.textbox.value.length >= this.minChars)
		{
						
			if(this.cache[cacheValue])
			{
				this.popup.innerHTML = this.cache[cacheValue];
				this.onComplete();
			}
			else
			{
				new ajax(this.buildURL(), {method: 'get', onComplete: this.onComplete.bindAsEventListener(this)});
			}
		}
		else
		{
			this.hidePopup();
		}
	},
	
	onComplete: function(request)
	{
		// Cached results or fresh ones?
		if(request)
		{
			if(request.responseText.charAt(0) != "<")
			{
				this.hidePopup();
				return false;
			}
			cacheValue = this.textbox.length+this.textbox.value;
			this.popup.innerHTML = request.responseText;
			this.cache[cacheValue] = this.popup.innerHTML;
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
		if(this.popup.childNodes.length < 1)
		{
			this.hidePopup();
			return false;
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
		
		// Clone to get offset height (not possible when display=none)
	    var clone = this.popup.cloneNode(true);
		document.body.appendChild(clone);
		clone.style.top = "-1000px";
		clone.style.display = "block";
		offsetHeight = clone.offsetHeight
		Element.remove(clone);
		
		var maxHeight = 100;
		if(offsetHeight > 0 && offsetHeight < maxHeight)
		{
			this.popup.style.overflow = "hidden";	
		}
		else if(MyBB.browser == "ie")
		{
			this.popup.style.height = maxHeight+"px";
			this.popup.style.overflowY = "auto";
		}
		else
		{
			this.popup.style.maxHeight = maxHeight+"px";
			this.popup.style.overflow = "auto";
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
		this.popup.style.display = "";
		if(this.currentKeyCode != 8 && this.currentKeyCode != 46)
		{
			this.highlightItem(0);
			this.setTypeAhead(0);	
		}
	},
	
	hidePopup: function()
	{
		this.popup.style.display = "none";
		this.currentIndex = -1;
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
		//alert('setting value to '+textBoxValue)
		this.textbox.value = textBoxValue;
		return textBoxValue;
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
		this.hidePopup();
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
	
	scrollToItem: function(selectedItem)
	{
		newItem = this.popup.childNodes[selectedItem];
		if(!newItem)
		{
			return false;
		}
		if(newItem.offsetTop+newItem.offsetHeight > this.popup.scrollTop+this.popup.offsetHeight)
		{
			this.popup.scrollTop = (newItem.offsetTop+newItem.offsetHeight) - this.popup.offsetHeight;
		}
		else if((newItem.offsetTop+newItem.offsetHeight) < this.popup.scrollTop)
		{
			this.popup.scrollTop = newItem.offsetTop;
		}
		else if(newItem.offsetTop < this.popup.scrollTop)
		{
			this.popup.scrollTop = newItem.offsetTop;
		}
		else if(newItem.offsetTop > (this.popup.scrollTop + this.popup.offsetHeight))
		{
			this.popup.scrollTop = (newItem.offsetTop+newItem.offsetHeight)-this.popup.offsetHeight;
		}
	},
	
	setTypeAhead: function(selectedItem)
	{
		selectedItem = this.popup.childNodes[selectedItem];
		if(!selectedItem || (!this.textbox.setSelectionRange && !this.textbox.createTextRange))
		{
			return false;
		}
		currentValue = this.textbox.value;
		newValue = this.updateValue(selectedItem);
		selectStart = currentValue.length;
		selectEnd = newValue.length;
		if(this.textbox.setSelectionRange)
		{
			this.textbox.setSelectionRange(selectStart, selectEnd);
		}
		else if(this.textbox.createTextRange)
		{
			var range = this.textbox.createTextRange();
			range.moveStart('character', selectStart);
			range.moveEnd('character', selectEnd-newValue.length);
			range.select();
		}
	},
	
	clearCache: function()
	{
		this.cache = '';
	}
};