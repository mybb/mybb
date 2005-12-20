var MyBB = {
	init: function()
	{
		this.detectBrowser();
		this.attachListener(window, "load", MyBB.pageLoaded);
	},
	
	pageLoaded: function()
	{
	
	},
	
	detectBrowser: function()
	{
		this.useragent = navigator.userAgent.toLowerCase();
		this.useragent_version = parseInt(navigator.appVersion);
		
		if(navigator.product == "Gecko")
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
	
	deletePost: function()
	{
		confirmReturn = confirm(quickdelete_confirm);
		if(confirmReturn == true) {
			window.location = "editpost.php?action=deletepost&pid="+pid+"&delete=yes";
		}
	},
	
	deleteEvent: function()
	{
		confirmReturn = confirm(deleteevent_confirm);
		if(confirmReturn == true) {
			window.location = "calendar.php?action=do_editevent&eid="+eid+"&delete=yes";
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

	reputation: function(pid, type)
	{
		popupWin("reputation.php?pid=" + pid + "&type=" + type, "reputation", 400, 300)
	},
	
	whoPosted: function(tid)
	{
		popupWin("misc.php?action=whoposted&tid=" + tid, "whoPosted", 230, 300)
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
	
	attachListener: function(element, type, listener)
	{
		if(element.addEventListener)
		{
			element.addEventListener(type, listener, false);
		}
		else
		{
			element.attachEvent("on"+type, listener, false);
		}
	},
	
	removeListener: function(element, type, listener)
	{
		if(element.removeEventListener)
		{
			element.removeEventListener(type, listener, false);
		}
		else
		{
			element.detachEvent("on"+type, listener);
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
	}
}
function expandCollapse(id) {
	expandedItem = getElemRefs(id+"_e");
	collapsedItem = getElemRefs(id+"_c");
	if(expandedItem && collapsedItem) {
		if(expandedItem.style.display == "none") {
			hideDiv(id+"_c");
			showDiv(id+"_e");
			saveCollapsed(id);
		} else {
			hideDiv(id+"_e");
			showDiv(id+"_c");
			saveCollapsed(id, 1);
		}
	}
	if(expandedItem && !collapsedItem)
	{
		collapseImage = getElemRefs(id+"_collapseimg");
		if(expandedItem.style.display == "none") {
			showDiv(id+"_e");
			saveCollapsed(id);
			if(collapseImage) {
				collapseImage.src = collapseImage.src.replace("collapse_collapsed.gif","collapse.gif");
				collapseImage.alt = collapseImage.alt.replace("[-]","[+]");
			}
		} else {
			hideDiv(id+"_e");
			saveCollapsed(id, 1);
			if(collapseImage) {
				collapseImage.src = collapseImage.src.replace("collapse.gif","collapse_collapsed.gif");
				collapseImage.alt = collapseImage.alt.replace("[+]","[-]");
			}
		}
	}
}

function getElemRefs(id) {
	if(document.getElementById) {
		return document.getElementById(id);
	}
	else if(document.all) {
		return document.all[id];
	}
	else if(document.layers) {
		return document.layers[id];
	}
}

function showDiv(id) {
	var lyr = getElemRefs(id);
	if(lyr && lyr.style) {
		lyr.style.display = "";
	}
}

function hideDiv(id) {
	var lyr = getElemRefs(id);
	if(lyr && lyr.style) {
		lyr.style.display = "none";
	}
}

function saveCollapsed(id, add) {
	var saved = new Array();
	var newcollapsed = new Array();
	if(collapsed = getcookie("collapsed")) {
		saved = collapsed.split("|");
		for(i=0;i<saved.length;i++) {
			if(saved[i] != id && saved[id] != "") {
				newcollapsed[newcollapsed.length] = saved[i];
			}
		}
	}
	if(add) {
		newcollapsed[newcollapsed.length] = id;
	}
	col = newcollapsed.join("|");
	setcookie("collapsed", col);
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
MyBB.init();