var MyBB = {
	init: function()
	{
		this.detectBrowser();
		Event.observe(document, "dom:loaded", MyBB.pageLoaded);
		return true;
	},

	pageLoaded: function()
	{
		MyBB.page_loaded = 1;

		expandables.init();

		// Initialise check all boxes
		checkall = $$('input.checkall');
		checkall.each(function(element) {
			Event.observe(element, "click", MyBB.checkAll.bindAsEventListener(this));
		});

		// Initialise "initial focus" field if we have one
		initialfocus = $$('input.initial_focus');
		if(initialfocus[0])
		{
			initialfocus[0].focus();
		}

		if(typeof(use_xmlhttprequest) != "undefined" && use_xmlhttprequest == 1)
		{
			mark_read_imgs = $$('img.ajax_mark_read');
			mark_read_imgs.each(function(element) {
				if(element.src.match("off.gif") || element.src.match("offlock.gif") || (element.title && element.title == lang.no_new_posts)) return;
				Event.observe(element, "click", MyBB.markForumRead.bindAsEventListener(this));
				element.style.cursor = 'pointer';
				if(element.title)
				{
					element.title += " - ";
				}
				element.title += lang.click_mark_read;
			});
		}
	},

	detectBrowser: function()
	{
		this.useragent = navigator.userAgent.toLowerCase();
		this.useragent_version = parseInt(navigator.appVersion);

		if(navigator.product == "Gecko" && navigator.vendor.indexOf("Apple Computer") != -1)
		{
			this.browser = "safari";
		}
		else if(this.useragent.indexOf("chrome") != -1)
		{
			this.browser = "chrome";
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

	deleteEvent: function(eid)
	{
		confirmReturn = confirm(deleteevent_confirm);

		if(confirmReturn == true)
		{
			var form = new Element("form", { method: "post", action: "calendar.php", style: "display: none;" });

			form.insert({ bottom: new Element("input",
				{
					name: "action",
					type: "hidden",
					value: "do_editevent"
				})
			});

			if(my_post_key)
			{
				form.insert({ bottom: new Element("input",
					{
						name: "my_post_key",
						type: "hidden",
						value: my_post_key
					})
				});
			}

			form.insert({ bottom: new Element("input",
				{
					name: "eid",
					type: "hidden",
					value: eid
				})
			});

			form.insert({ bottom: new Element("input",
				{
					name: "delete",
					type: "hidden",
					value: 1
				})
			});

			$$("body")[0].insert({ bottom: form });
			form.submit();
		}
	},

	checkAll: function(e)
	{
		var allbox = Event.element(e);
		var form = Event.findElement(e, 'FORM');
		if(!form)
		{
			return false;
		}
		form.getElements().each(function(element) {		
			if(!element.hasClassName("checkall") && element.type == "checkbox")
			{
				element.checked = allbox.checked;
			}
		});
	},

	reputation: function(uid, pid)
	{
		if(!pid)
		{
			var pid = 0;
		}

		MyBB.popupWindow("reputation.php?action=add&uid="+uid+"&pid="+pid, "reputation", 400, 350)
	},

	deleteReputation: function(uid, rid)
	{
		confirmReturn = confirm(delete_reputation_confirm);

		if(confirmReturn == true)
		{
			var form = new Element("form", { method: "post", action: "reputation.php?action=delete", style: "display: none;" });

			form.insert({ bottom: new Element("input",
				{
					name: "rid",
					type: "hidden",
					value: rid
				})
			});

			if(my_post_key)
			{
				form.insert({ bottom: new Element("input",
					{
						name: "my_post_key",
						type: "hidden",
						value: my_post_key
					})
				});
			}

			form.insert({ bottom: new Element("input",
				{
					name: "uid",
					type: "hidden",
					value: uid
				})
			});

			$$("body")[0].insert({ bottom: form });
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

		if((promptres != null) && (promptres != "") && (promptres > 1) && (promptres <= pages))
		{
			window.location = "showthread.php?tid="+tid+"&page"+promotres;
		}
	},

	markForumRead: function(event)
	{
		element = Event.element(event);
		if(!element)
		{
			return false;
		}
		var fid = element.id.replace("mark_read_", "");
		if(!fid)
		{
			return false;
		}
		new Ajax.Request('misc.php?action=markread&fid='+fid+'&ajax=1&my_post_key='+my_post_key, {method: 'get', onComplete: function(request) {MyBB.forumMarkedRead(fid, request); }});
	},

	forumMarkedRead: function(fid, request)
	{
		if(request.responseText == 1)
		{
			$('mark_read_'+fid).src = $('mark_read_'+fid).src.replace("on.gif", "off.gif");
			Event.stopObserving($('mark_read_'+fid), "click", MyBB.markForumRead.bindAsEventListener(this));
			$('mark_read_'+fid).style.cursor = 'default';
			$('mark_read_'+fid).title = lang.no_new_posts;
		}
	},

	detectDSTChange: function(timezone_with_dst)
	{
		var date = new Date();
		var local_offset = date.getTimezoneOffset() / 60;
		if(Math.abs(parseInt(timezone_with_dst) + local_offset) == 1)
		{
			if(use_xmlhttprequest != 1 || !new Ajax.Request('misc.php?action=dstswitch&ajax=1', {method: 'post'})) // Ajax update failed? (No ajax support) Fake it
			{
				var form = new Element("form", { method: "post", action: "misc.php", style: "display: none;" });

				form.insert({ bottom: new Element("input",
					{
						name: "action",
						type: "hidden",
						value: "dstswitch"
					})
				});

				$$("body")[0].insert({ bottom: form });
				form.submit();
			}
		}
	},

	dismissPMNotice: function()
	{
		if(!$('pm_notice'))
		{
			return false;
		}

		if(use_xmlhttprequest != 1)
		{
			return true;
		}

		new Ajax.Request('private.php?action=dismiss_notice', {method: 'post', postBody: 'ajax=1&my_post_key='+my_post_key});
		Element.remove('pm_notice');
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
		return text;
	},

	changeLanguage: function()
	{
		form = $('lang_select');
		if(!form)
		{
			return false;
		}
		form.submit();
	},
	
	quickLogin: function()
	{		
		if($("quick_login"))
		{
			var form = new Element("form", { method: "post", action: "member.php" });
			form.insert({ bottom: new Element("input",
				{
					name: "action",
					type: "hidden",
					value: "do_login"
				})
			});

			if(document.location.href)
			{
				form.insert({ bottom: new Element("input",
					{
						name: "url",
						type: "hidden",
						value: this.HTMLchars(document.location.href)
					})
				});
			}

			form.insert({ bottom: new Element("input",
				{
					name: "quick_login",
					type: "hidden",
					value: "1"
				})
			});

			form.insert({ bottom: new Element("input",
				{
					name: "quick_username",
					id: "quick_login_username",
					type: "text",
					value: lang.username,
					"class": "textbox",
					onfocus: "if(this.value == '"+lang.username+"') { this.value=''; }",
					onblur: "if(this.value == '') { this.value='"+lang.username+"'; }"
				})
			}).insert({ bottom: "&nbsp;" });

			form.insert({ bottom: new Element("input",
				{
					name: "quick_password",
					id: "quick_login_password",
					type: "password",
					value: lang.password,
					"class": "textbox",
					onfocus: "if(this.value == '"+lang.password+"') { this.value=''; }",
					onblur: "if(this.value == '') { this.value='"+lang.password+"'; }"
				})
			}).insert({ bottom: "&nbsp;" });

			form.insert({ bottom: new Element("input",
				{
					name: "submit",
					type: "submit",
					value: lang.login,
					"class": "button"
				})
			});

			var span = new Element("span", { "class": "remember_me" }).insert({ bottom: new Element("input",
				{
					name: "quick_remember",
					id: "quick_login_remember",
					type: "checkbox",
					value: "yes",
					"class": "checkbox"
				})
			});

			span.innerHTML += "<label for=\"quick_login_remember\"> "+lang.remember_me+"</label>";
			form.insert({ bottom: span });

			form.innerHTML += lang.lost_password+lang.register_url;
	
			$("quick_login").innerHTML = "";
			$("quick_login").insert({ before: form });

			$("quick_login_remember").setAttribute("checked", "checked");
			$('quick_login_username').focus();
		}

		return false;
	}
};

var Cookie = {
	get: function(name)
	{
		cookies = document.cookie;
		name = cookiePrefix+name+"=";
		cookiePos = cookies.indexOf(name);
		
		if(cookiePos != -1) 
		{
			cookieStart = cookiePos+name.length;
			cookieEnd = cookies.indexOf(";", cookieStart);
			
			if(cookieEnd == -1) 
			{
				cookieEnd = cookies.length;
			}
			
			return unescape(cookies.substring(cookieStart, cookieEnd));
		}
	},

	set: function(name, value, expires)
	{
		if(!expires) 
		{
			expires = "; expires=Wed, 1 Jan 2020 00:00:00 GMT;"
		} 
		else 
		{
			expire = new Date();
			expire.setTime(expire.getTime()+(expires*1000));
			expires = "; expires="+expire.toGMTString();
		}

		if(cookieDomain) 
		{
			domain = "; domain="+cookieDomain;
		}
		else
		{
			domain = "";
		}

		if(cookiePath != "") 
		{
			path = cookiePath;
		}
		else
		{
			path = "";
		}

		document.cookie = cookiePrefix+name+"="+escape(value)+"; path="+path+domain+expires;
	},

	unset: function(name)
	{
		Cookie.set(name, 0, -1);
	}
};

var DomLib = {
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
		else if(document.body.scrollHeight > document.body.offsetHeight) // All but Explorer Mac
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

		// For small pages with total height less then height of the viewport
		if(yScroll < windowHeight)
		{
			pageHeight = windowHeight;
		}
		else
		{
			pageHeight = yScroll;
		}

		// For small pages with total width less then width of the viewport
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
};

var expandables = {
	init: function()
	{
		expanders = $$('img.expander');
		if(expanders.length > 0)
		{
			expanders.each(function(expander) {
				if(!expander.id)
				{
					return;
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
			}.bind(this));
		}
	},

	expandCollapse: function(e)
	{
		element = Event.element(e)
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
				expandedItem.show();
				collapsedItem.hide();
				this.saveCollapsed(element.controls);
			}
			else
			{
				expandedItem.hide();
				collapsedItem.show();
				this.saveCollapsed(element.controls, 1);
			}
		}
		else if(expandedItem && !collapsedItem)
		{
			if(expandedItem.style.display == "none")
			{
				expandedItem.show();
				element.src = element.src.replace("collapse_collapsed.gif", "collapse.gif");
				element.alt = "[-]";
				element.title = "[-]";
				this.saveCollapsed(element.controls);
			}
			else
			{
				expandedItem.hide();
				element.src = element.src.replace("collapse.gif", "collapse_collapsed.gif");
				element.alt = "[+]";
				element.title = "[+]";
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
			saved.each(function(item) {
				if(item != id && item != "")
				{
					newCollapsed[newCollapsed.length] = item;
				}
			});
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

			var offset = Position.positionedOffset(owner);
			left = offset[0];
			top = offset[1];
		}

		this.spinner = document.createElement("div");
		this.spinner.style.border = "1px solid #000000";
		this.spinner.style.background = "#FFFFFF";
		this.spinner.style.color = "#000000";
		this.spinner.style.position = "absolute";
		this.spinner.style.zIndex = 1000;
		this.spinner.style.textAlign = "center";
		this.spinner.style.verticalAlign = "middle";
		this.spinner.style.fontSize = "13px";

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
};

/* Lang this! */
var lang = {

};

/* additions for IE5 compatibility */ 
if(!Array.prototype.shift) {
	Array.prototype.shift = function()
	{
		firstElement = this[0];
		this.reverse();
		this.length = Math.max(this.length-1,0);
		this.reverse();
		return firstElement;
	}
}

if(!Array.prototype.unshift) { 
	Array.prototype.unshift = function()
	{
		this.reverse();
		for(var i=arguments.length-1;i>=0;i--) {
			this[this.length]=arguments[i]
		}
		this.reverse();
		return this.length
	}
}

if(!Array.prototype.push) {
	Array.prototype.push = function()
	{
		for(var i=0;i<arguments.length;i++){
			this[this.length]=arguments[i]
		};
		return this.length;
	}
}

if(!Array.prototype.pop) {
	Array.prototype.pop = function() {
		lastElement = this[this.length-1];
		this.length = Math.max(this.length-1,0);
		return lastElement;
	}
}

if (!Function.prototype.apply) {
	Function.prototype.apply = function(oScope, args) {
		var sarg = [];
		var rtrn, call;

		if (!oScope) oScope = window;
		if (!args) args = [];

		for (var i = 0; i < args.length; i++) {
			sarg[i] = "args["+i+"]";
		}

		call = "oScope.__applyTemp__(" + sarg.join(",") + ");";

		oScope.__applyTemp__ = this;
		rtrn = eval(call);
		//delete oScope.__applyTemp__;
		return rtrn;
	}
}

if(!Function.prototype.call) {
	Function.prototype.call = function(obj, param) {
		obj.base = this;
		obj.base(param);
	}
}

MyBB.init();