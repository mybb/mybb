var MyBB = {
	init: function()
	{
		this.detectBrowser();
		Event.observe(window, "load", MyBB.pageLoaded);
		return true;
	},

	pageLoaded: function()
	{
		expandables.init();
	},

	detectBrowser: function()
	{
		this.useragent = navigator.userAgent.toLowerCase();
		this.useragent_version = parseInt(navigator.appVersion);

		if(navigator.product == "Gecko" && navigator.vendor.indexOf("Apple Computer") != -1)
		{
			this.browser = "safari";
		}
		else if(navigator.product == "Gecko")
		{
			this.browser = "mozilla";
		}
		else if(this.useragent.indexOf("opera") != -1)
		{
			this.browser = "opera";
		}
		else if(this.useragent.indexOf("konqueror") != -1)
		{
			this.browser = "konqueror";
		}
		else if(this.useragent.indexOf("msie") != -1)
		{
			this.browser = "ie";
		}
		else if(this.useragent.indexOf("compatible") == -1 && this.useragent.indexOf("mozilla") != -1)
		{
			this.browser = "netscape";
		}

		if(this.useragent.indexOf("win") != -1)
		{
			this.os = "win";
		}
		else if(this.useragent.indexOf("mac") != -1)
		{
			this.os = "mac";
		}
	},

	popupWindow: function(url, name, width, height)
	{
		settings = "toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes";
		if(width)
		{
			settings = settings+",width="+width;
		}
		if(height)
		{
			settings = settings+",height="+height;
		}
		window.open(url, name, settings);
	},

	newPM: function()
	{
		confirmReturn = confirm(newpm_prompt);
		if(confirmReturn == true) {
			settings="toolbar=yes,location=yes,directories=yes,status=yes,menubar=yes,scrollbars=yes,resizable=yes,width=600,height=500";
			NewWindow=window.open('private.php','pmPopup',settings);
		}
	},

	deleteEvent: function(eid)
	{
		confirmReturn = confirm(deleteevent_confirm);
		if(confirmReturn == true)
		{
			form = document.createElement("form");
			form.setAttribute("method", "post");
			form.setAttribute("action", "calendar.php");
			form.setAttribute("style", "display: none;");

			var input = document.createElement("input");
			input.setAttribute("name", "action");
			input.setAttribute("type", "hidden");
			input.setAttribute("value", "do_editevent");
			form.appendChild(input);

			var input = document.createElement("input");
			input.setAttribute("name", "eid");
			input.setAttribute("type", "hidden");
			input.setAttribute("value", eid);
			form.appendChild(input);

			var input = document.createElement("input");
			input.setAttribute("name", "delete");
			input.setAttribute("type", "hidden");
			input.setAttribute("value", "yes");
			form.appendChild(input);

			document.getElementsByTagName("body")[0].appendChild(form);
			form.submit();
		}
	},


	checkAll: function(formName)
	{
		for(var i=0;i<formName.elements.length;i++)
		{
			var element = formName.elements[i];
			if((element.name != "allbox") && (element.type == "checkbox"))
			{
				element.checked = formName.allbox.checked;
			}
		}
	},

	reputation: function(uid)
	{
		MyBB.popupWindow("reputation.php?action=add&uid="+uid, "reputation", 400, 350)
	},


	deleteReputation: function(uid, rid)
	{
		confirmReturn = confirm(delete_reputation_confirm);
		if(confirmReturn == true)
		{
			form = document.createElement("form");
			form.setAttribute("method", "post");
			form.setAttribute("action", "reputation.php?action=delete");
			form.setAttribute("style", "display: none;");

			var input = document.createElement("input");
			input.setAttribute("name", "rid");
			input.setAttribute("type", "hidden");
			input.setAttribute("value", rid);
			form.appendChild(input);

			var input = document.createElement("input");
			input.setAttribute("name", "uid");
			input.setAttribute("type", "hidden");
			input.setAttribute("value", uid);
			form.appendChild(input);

			document.getElementsByTagName("body")[0].appendChild(form);
			form.submit();
		}
	},

	whoPosted: function(tid)
	{
		MyBB.popupWindow("misc.php?action=whoposted&tid=" + tid, "whoPosted", 230, 300)
	},

	hopPage: function(tid, page, pages)
	{
		if(pages > 1)
		{
			defpage = page + 1;
		}
		else
		{
			defpage = 1;
		}
		promptres = prompt("Quick Page Jump\nPlease enter a page number between 1 and "+pages+" to jump to.", defpage);
		if((promptres != null) && (promptres != "") & (promptres > 1) && (promptres <= pages))
		{
			window.location = "showthread.php?tid="+tid+"&page"+promotres;
		}
	},

	eventElement: function(event)
	{
		if(event.currentTarget)
		{
			return event.currentTarget;
		}
		else
		{
			return event.srcElement;
		}
	},

	arraySize: function(array_name)
	{
		for(var i=0;i<array_name.length;i++)
		{
			if(array_name[i] == "undefined" || array_name[i] == "" || array_name[i] == null)
			{
				return i;
			}
		}
		return array_name.length;
	},

	arrayPush: function(array_name, array_value)
	{
		array_size = MyBB.arraySize(array_name);
		array_name[array_size] = array_value;
	},

	arrayPop: function(array_name)
	{
		array_size = MyBB.arraySize(array_name);
		array_value = array_name[array_size-1];
		delete array_name[array_size-1];
		return array_value;
	},

	inArray: function(item, array_name)
	{
		for(var i=0;i<array_name.length;i++)
		{
			if(array_name[i] == item)
			{
				return true;
			}
		}
		return false;
	},

	unHTMLchars: function(text)
	{
		text = text.replace(/&lt;/g, "<");
		text = text.replace(/&gt;/g, ">");
		text = text.replace(/&nbsp;/g, " ");
		text = text.replace(/&quot;/g, "\"");
		text = text.replace(/&amp;/g, "&");
		return text;
	},

	HTMLchars: function(text)
	{
		text = text.replace(new RegExp("&(?!#[0-9]+;)", "g"), "&amp;");		
		text = text.replace(/</g, "&lt;");
		text = text.replace(/>/g, "&gt;");
		text = text.replace(/"/g, "&quot;");
		text = text.replace(/  /g, "&nbsp;&nbsp;");		
		return text;
	}
}

var Cookie = {

	get: function(name)
	{
		cookies = document.cookie;
		name = name+"=";
		cookiePos = cookies.indexOf(name);
		if(cookiePos != -1) {
			cookieStart = cookiePos+name.length;
			cookieEnd = cookies.indexOf(";", cookieStart);
			if(cookieEnd == -1) {
				cookieEnd = cookies.length;
			}
			return unescape(cookies.substring(cookieStart, cookieEnd));
		}
	},

	set: function(name, value, expires)
	{
		if(!expires) {
			expires = "; expires=Wed, 1 Jan 2020 00:00:00 GMT;"
		} else {
			expire = new Date();
			expire.setTime(expire.getTime()+(expires*1000));
			expires = "; expires="+expire.toGMTString();
		}
		if(cookieDomain) {
			domain = "; domain="+cookieDomain;
		}
		else
		{
			domain = "";
		}
		if(cookiePath != "") {
			path = cookiePath;
		}
		else
		{
			path = "";
		}
		document.cookie = name+"="+escape(value)+"; path="+path+domain+expires;
	},

	unset: function(name)
	{
		Cookie.set(name, 0, -1);
	}
}

var DomLib = {

	addClass: function(element, name)
	{
		if(element)
		{
			if(element.className != "")
			{
				element.className += " "+name;
			}
			else
			{
				element.className = name;
			}
		}
	},

	removeClass: function(element, name)
	{
		if(element.className == element.className.replace(" ", "-"))
		{
			element.className = element.className.replace(name, "");
		}
		else
		{
			element.className = element.className.replace(" "+name, "");
		}
	},

	getElementsByClassName: function(oElm, strTagName, strClassName)
	{
	    var arrElements = (strTagName == "*" && document.all)? document.all : oElm.getElementsByTagName(strTagName);
	    var arrReturnElements = new Array();
	    strClassName = strClassName.replace(/\-/g, "\\-");
	    var oRegExp = new RegExp("(^|\\s)" + strClassName + "(\\s|$)");
	    var oElement;
	    for(var i=0; i<arrElements.length; i++)
		{
	        oElement = arrElements[i];
	        if(oRegExp.test(oElement.className))
			{
	            arrReturnElements.push(oElement);
	        }
	    }
	    return (arrReturnElements)
	},

	// This function is from quirksmode.org
	// Modified for use in MyBB
	getPageScroll: function()
	{
		var yScroll;
		if(self.pageYOffset)
		{
			yScroll = self.pageYOffset;
		}
		else if(document.documentElement && document.documentElement.scrollTop) // Explorer 6 Strict
		{
			yScroll = document.documentElement.scrollTop;
		}
		else if(document.body) // all other Explorers
		{
			yScroll = document.body.scrollTop;
		}
		arrayPageScroll = new Array('',yScroll);
		return arrayPageScroll;
	},

	// This function is from quirksmode.org
	// Modified for use in MyBB
	getPageSize: function()
	{
		var xScroll, yScroll;

		if(window.innerHeight && window.scrollMaxY)
		{
			xScroll = document.body.scrollWidth;
			yScroll = window.innerHeight + window.scrollMaxY;
		}
		else if(document.body.scrollHeight > document.body.offsetHeight) // all but Explorer Mac
		{
			xScroll = document.body.scrollWidth;
			yScroll = document.body.scrollHeight;
		}
		else  // Explorer Mac...would also work in Explorer 6 Strict, Mozilla and Safari
		{
			xScroll = document.body.offsetWidth;
			yScroll = document.body.offsetHeight;
		}

		var windowWidth, windowHeight;
		if(self.innerHeight) // all except Explorer
		{
			windowWidth = self.innerWidth;
			windowHeight = self.innerHeight;
		}
		else if(document.documentElement && document.documentElement.clientHeight)  // Explorer 6 Strict Mode
		{
			windowWidth = document.documentElement.clientWidth;
			windowHeight = document.documentElement.clientHeight;
		}
		else if (document.body) // other Explorers
		{
			windowWidth = document.body.clientWidth;
			windowHeight = document.body.clientHeight;
		}
		
		var pageHeight, pageWidth;
		// for small pages with total height less then height of the viewport
		if(yScroll < windowHeight)
		{
			pageHeight = windowHeight;
		}
		else
		{
			pageHeight = yScroll;
		}

		// for small pages with total width less then width of the viewport
		if(xScroll < windowWidth)
		{
			pageWidth = windowWidth;
		}
		else
		{
			pageWidth = xScroll;
		}
		var arrayPageSize = new Array(pageWidth,pageHeight,windowWidth,windowHeight);
		return arrayPageSize;
	}

}

var expandables = {

	init: function()
	{
		expanders = DomLib.getElementsByClassName(document, "img", "expander");
		if(expanders.length > 0)
		{
			for(var i=0;i<expanders.length;i++)
			{
				var expander = expanders[i];
				if(!expander.id)
				{
					continue;
				}
				Event.observe(expander, "click", this.expandCollapse.bindAsEventListener(this));
				if(MyBB.browser == "ie")
				{
					expander.style.cursor = "hand";
				}
				else
				{
					expander.style.cursor = "pointer";
				}
				expander.controls = expander.id.replace("_img", "");
				var row = $(expander.controls);
				if(row)
				{
					Event.observe(row, "dblclick", this.expandCollapse.bindAsEventListener(this));
					row.controls = expander.id.replace("_img", "");
				}
			}
		}
	},

	expandCollapse: function(e)
	{
		element = MyBB.eventElement(e)
		if(!element || !element.controls)
		{
			return false;
		}
		var expandedItem = $(element.controls+"_e");
		var collapsedItem = $(element.controls+"_c");

		if(expandedItem && collapsedItem)
		{
			if(expandedItem.style.display == "none")
			{
				expandedItem.style.display = "";
				collapsedItem.style.display = "none";
				this.saveCollapsed(element.controls);
			}
			else
			{
				expandedItem.style.display = "none";
				collapsedItem.style.display = "";
				this.saveCollapsed(element.controls, 1);
			}
		}
		else if(expandedItem && !collapsedItem)
		{
			if(expandedItem.style.display == "none")
			{
				expandedItem.style.display = "";
				element.src = element.src.replace("collapse_collapsed.gif", "collapse.gif");
				element.alt = "[-]";
				this.saveCollapsed(element.controls);
			}
			else
			{
				expandedItem.style.display = "none";
				element.src = element.src.replace("collapse.gif", "collapse_collapsed.gif");
				element.alt = "[+]";
				this.saveCollapsed(element.controls, 1);
			}
		}
		return true;
	},

	saveCollapsed: function(id, add)
	{
		var saved = new Array();
		var newCollapsed = new Array();
		var collapsed = Cookie.get("collapsed");
		if(collapsed)
		{
			saved = collapsed.split("|");
			for(var i=0;i<saved.length;i++)
			{
				if(saved[i] != id && saved[id] != "")
				{
					newCollapsed[newCollapsed.length] = saved[i];
				}
			}
		}
		if(add == 1)
		{
			newCollapsed[newCollapsed.length] = id;
		}
		Cookie.set("collapsed", newCollapsed.join("|"));
	}
};

var ActivityIndicator = Class.create();

ActivityIndicator.prototype = {
	initialize: function(owner, options)
	{
		var image;
		if(options && options.image)
		{
			image = "<img src=\""+options.image+"\" alt=\"\" />";
		}
		else
		{
			image = "";
		}
		this.height = options.height || 150;
		this.width = options.width || 150;
		if(owner == "body")
		{
			arrayPageSize = DomLib.getPageSize();
			arrayPageScroll = DomLib.getPageScroll();
			var top = arrayPageScroll[1] + ((arrayPageSize[3] - 35 - this.height) / 2);
			var left = ((arrayPageSize[0] - 20 - this.width) / 2);
			owner = document.getElementsByTagName("body").item(0);
		}
		else
		{
			if($(owner))
			{
				owner = $(owner);
			}
			element = owner;
			top = left = 0;
			do
			{
				top += element.offsetTop || 0;
				left += element.offsetLeft || 0;
				element = element.offsetParent;
			} while(element);

			left += owner.offsetWidth;
			top += owner.offsetHeight;
		}
		this.spinner = document.createElement("div");
		this.spinner.style.border = "1px solid #000000";
		this.spinner.style.background = "#FFFFFF";
		this.spinner.style.position = "absolute";
		this.spinner.style.zIndex = 1000;
		this.spinner.style.textAlign = "center";
		this.spinner.style.verticalAlign = "middle";

		this.spinner.innerHTML = "<br />"+image+"<br /><br /><strong>"+loading_text+"</strong>";
		this.spinner.style.width = this.width + "px";
		this.spinner.style.height = this.height + "px";
		this.spinner.style.top = top + "px";
		this.spinner.style.left = left + "px";
		this.spinner.id = "spinner";
		owner.insertBefore(this.spinner, owner.firstChild);
	},

	destroy: function()
	{
		Element.remove(this.spinner);
	}
}

MyBB.init();