var autoComplete = Class.create();

autoComplete.prototype = {
	initialize: function(textbox, url, options)
	{
		if(!$(textbox))
		{
			return false;
		}

		this.cache = new Object();
		this.lastValue = '';
		this.lastKeycode = 0;
		this.textbox = $(textbox);
		this.formSubmit = false;
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

		if(options.delimChar)
		{
			this.delimChar = options.delimChar;
		}
		this.menuOpen = false;
		this.timeout = false;

		Event.observe(document, "dom:loaded", this.init.bindAsEventListener(this));
	},

	init: function()
	{
		this.textbox.setAttribute("autocomplete", "off");
		this.textbox.autocompletejs = this;
		Event.observe(this.textbox, "keypress", this.onKeyPress.bindAsEventListener(this));
		Event.observe(this.textbox, "keyup", this.onKeyUp.bindAsEventListener(this));
		Event.observe(this.textbox, "keydown", this.onKeyDown.bindAsEventListener(this));

		if(this.textbox.form)
		{
			Event.observe(this.textbox.form, "submit", this.onFormSubmit.bindAsEventListener(this));
			this.formSubmit = true;
		}

		this.textbox.onsubmit = this.onFormSubmit.bindAsEventListener(this);
		this.popup = document.createElement("div");
		this.popup.style.position = "absolute";
		this.popup.className = "autocomplete";
		this.popup.style.display = 'none';
		document.body.appendChild(this.popup);

		this.textbox.popup = this;

		Event.observe(document, "unload", this.clearCache.bindAsEventListener(this));
	},

	onFormSubmit: function(e)
	{
		if(this.lastKeycode == Event.KEY_RETURN && this.menuOpen == true)
		{
			setTimeout(function() { this.textbox.focus() }.bind(this), 10);
			this.menuOpen = false;
			this.hidePopup();
			Event.stop(e);
			return false;
		}
		else
		{
			return true;
		}
		//this.textbox.setAttribute("autocomplete", "on");
	},

	onKeyDown: function(e)
	{
		this.lastKeycode = e.keyCode;
	},

	onKeyUp: function(e)
	{
		this.lastKeycode = e.keyCode;
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
				if(this.popup.visible())
				{
					if(this.currentIndex > 0)
					{
						this.scrollToItem(this.currentIndex-1);
						this.highlightItem(this.currentIndex-1);
						this.setTypeAhead(this.currentIndex);
					}
					else if(this.currentIndex == 0)
					{
						this.textbox.value = this.lastValue;
						this.hidePopup();
					}
				}
				Event.stop(e);
				break;
			case Event.KEY_DOWN:
				if(this.currentIndex+1 < this.popup.childNodes.length && this.popup.visible())
				{
					this.scrollToItem(this.currentIndex+1);
					this.highlightItem(this.currentIndex+1);
					this.setTypeAhead(this.currentIndex);
				}
				Event.stop(e);
				break;
			case Event.KEY_TAB:
				if(this.popup.visible() && this.currentIndex > -1)
				{
					this.updateValue(this.popup.childNodes[this.currentIndex]);
					this.hidePopup();
					this.currentIndex = -1;
					
					if(this.delimChar)
					{
						Event.stop(e);
					}
					return false;
				}
				break;
			case Event.KEY_RETURN:
				if(this.menuOpen == true)
				{
					Event.stop(e);
				}
				if(this.currentIndex != -1)
				{
					this.updateValue(this.popup.childNodes[this.currentIndex]);
					this.hidePopup();
					this.currentIndex = -1;
					this.clearSelection();
				}
				//return false;
				break;
			case Event.KEY_ESC:
				this.hidePopup();
				break;
			default:
				this.currentKeyCode = e.keyCode;
				this.timeout = setTimeout("$('"+this.textbox.id+"').autocompletejs.doRequest();", 500);
				break;
		}
		return true;
	},

	buildURL: function(value)
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

		return this.url+separator+this.urlParam+"="+encodeURIComponent(value);		
	},

	doRequest: function()
	{
		if(this.lastValue == this.textbox.value)
		{
			return false;
		}

		this.lastValue = this.textbox.value;
		this.previousComplete = '';
		value = this.textbox.value;
		cacheValue = this.textbox.length+this.textbox.value;

		if(this.delimChar)
		{
			delimIndex = value.lastIndexOf(this.delimChar);
			if(delimIndex >= -1)
			{
				if(value.charAt(delimIndex+1) == " ")
				{
					delimIndex += 1;
				}

				this.previousComplete = value.substr(0, delimIndex+1);
				value = value.substr(delimIndex+1);
			}
		}

		if(value.length >= this.minChars)
		{
			if(this.cache[cacheValue])
			{
				this.popup.innerHTML = this.cache[cacheValue];
				this.onComplete();
			}
			else
			{
				new Ajax.Request(this.buildURL(value), {method: 'get', onComplete: this.onComplete.bindAsEventListener(this)});
			}
		}
		else
		{
			if(this.popup.visible())
			{
				this.hidePopup();
			}
		}
	},

	onComplete: function(request)
	{
		// Cached results or fresh ones?
		if(request)
		{
			if(request.responseText.charAt(0) != "<")
			{
				if(this.popup.visible())
				{
					this.hidePopup();
				}
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

		$A(this.popup.childNodes).each(function(node)
		{
			if (node.nodeType == 3 && !/\S/.test(node.nodeValue))	
			{
				this.popup.removeChild(node);
			}
		}.bind(this));

		if(this.popup.childNodes.length < 1)
		{
			if(this.popup.visible())
			{
				this.hidePopup();
			}
			return false;
		}

		$A(this.popup.childNodes).each(function(item, i)
		{
			item.index = i;
			item.style.padding = "1px";
			item.style.clear = "both";
			//item.style.height = "1em";
			Event.observe(item, "mouseover", this.itemOver.bindAsEventListener(this));
			Event.observe(item, "click", this.itemClick.bindAsEventListener(this));
		}.bind(this));

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

		var offsetTop = 0;
		var offsetLeft = 0;

		this.popup.style.width = this.textbox.offsetWidth-3+"px";
		element = this.textbox;
		do
		{
			offsetTop += element.offsetTop || 0;
			offsetLeft += element.offsetLeft || 0;
			element = element.offsetParent;
			if(element && ($(element).getStyle('position') == 'absolute'))
			{
				break;
			}
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
		Event.observe(this.textbox, "blur", this.hidePopup.bindAsEventListener(this));
		Event.observe(this.popup, "mouseover", this.popupOver.bindAsEventListener(this));
		Event.observe(this.popup, "mouseout", this.popupOut.bindAsEventListener(this));
		this.popup.style.display = '';
		this.menuOpen = true;
		this.overPopup = 0;

		if(this.currentKeyCode != 8 && this.currentKeyCode != 46)
		{
			this.highlightItem(0);
			/*his.setTypeAhead(0, 1);*/
		}
	},

	hidePopup: function()
	{
		this.popup.hide();
		this.menuOpen = false;
		Event.stopObserving(this.textbox, "blur", this.hidePopup.bindAsEventListener(this));
		Event.stopObserving(this.popup, "mouseover", this.popupOver.bindAsEventListener(this));
		Event.stopObserving(this.popup, "mouseout", this.popupOut.bindAsEventListener(this));

		if(this.overPopup == 1 && this.currentIndex > -1)
		{
			this.updateValue(this.popup.childNodes[this.currentIndex]);
			this.currentIndex = -1;
			this.textbox.focus();
		}
	},

	popupOver: function()
	{
		this.overPopup = 1;
	},

	popupOut: function()
	{
		this.overPopup = 1;
	},

	updateValue: function(selectedItem)
	{
		if(this.valueSpan && selectedItem.innerHTML)
		{
			var items = selectedItem.getElementsByTagName("SPAN");
			if(items)
			{
				$A(items).each(function(item) {
					if(item.className == this.valueSpan)
					{
						textBoxValue = item.innerHTML;
					}
				}.bind(this));
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

		this.textbox.value = "";
		if(this.delimChar)
		{
			if(this.previousComplete)
			{
				this.textbox.value = this.previousComplete;
			}
			this.textbox.value += textBoxValue+this.delimChar+" ";
		}
		else
		{
			this.textbox.value = textBoxValue;
		}
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
		this.currentIndex = -1;
		this.clearSelection();
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

	setTypeAhead: function(selectedItem, selectChanges)
	{
		selectedItem = this.popup.childNodes[selectedItem];

		if(!selectedItem || (!this.textbox.setSelectionRange && !this.textbox.createTextRange))
		{
			return false;
		}

		if(selectChanges)
		{
			selectStart = this.textbox.value.length;
		}

		newValue = this.updateValue(selectedItem);
		selectEnd = this.textbox.value.length;
		if(!selectChanges)
		{
			selectStart = selectEnd;
		}

		if(this.textbox.setSelectionange)
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

	clearSelection: function()
	{
		selectEnd = this.textbox.value.length;

		if(this.textbox.setSelectionRange)
		{
			this.textbox.setSelectionRange(selectEnd, selectEnd);
		}
		else if(window.createTextRange)
		{
			var range = this.textbox.createTextRange();
			range.moveStart('character', selectEnd);
			range.moveEnd('character', selectEnd);
			range.select();
		}
	},

	clearCache: function()
	{
		this.cache = '';
	}
};