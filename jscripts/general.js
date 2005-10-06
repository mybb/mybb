var agt = navigator.userAgent.toLowerCase();
var agt_ver = parseInt(navigator.appVersion);
var is_mozilla = (navigator.product == "Gecko");
var is_opera = (agt.indexOf("opera") != -1);
var is_konqueror = (agt.indexOf("konqueror") != -1);
var is_webtv = (agt.indexOf("webtv") != -1);
var is_ie = ((agt.indexOf("msie") != -1) && (!is_opera) && (!is_webtv));
var is_netscape = ((agt.indexOf("compatible") == -1) && (agt.indexOf("mozilla") != -1) && (!is_opera) && (!is_webtv));
var is_win = (agt.indexOf("win" != -1));
var is_mac = (agt.indexOf("mac") != -1);
var cancelForm = 0;

function setcookie(name,value,expires) {
	var path = "/";
	var domain = "";
	if(!expires) {
		expires = "; expires=Wed, 1 Jan 2020 00:00:00 GMT;"
	} else {
		expires = "; expires=" +expires;
	}
	if(cookieDomain) {
		domain = "; domain="+cookieDomain;
	}
	if(cookiePath != "") {
		path = cookiePath;
	}
	document.cookie = name+"="+escape(value)+"; path="+path+domain+expires;
}

function getcookie(name) {
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
}

function unsetcookie(name) {
	expire = " expires=Thu, 01-Jan-70 00:00:01 GMT;"
	document.cookie = name+"="+"; path=/;"+expire;
}

function popupWin(url, window_name, window_width, window_height) {
	settings= "toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes"; 
	if(window_width)
	{
		settings = settings+",width="+window_width+;
	}
	if(window_height)
	{
		settings = settings+",height="+window_height;
	}
	window.open(url,window_name,settings);
}

function newPM() {
	confirmReturn = confirm(newpm_prompt);
	if(confirmReturn == true) {
		settings="toolbar=yes,location=yes,directories=yes,status=yes,menubar=yes,scrollbars=yes,resizable=yes,width=600,height=500"; 
		NewWindow=window.open('private.php','pmPopup',settings);
	}
}

function deletePost(pid) {
	confirmReturn = confirm(quickdelete_confirm);
	if(confirmReturn == true) {
		window.location = "editpost.php?action=deletepost&pid="+pid+"&delete=yes";
	}
}

function deleteEvent(eid) {
	confirmReturn = confirm(deleteevent_confirm);
	if(confirmReturn == true) {
		window.location = "calendar.php?action=do_editevent&eid="+eid+"&delete=yes";
	}
}

function removeAttachment(aid) {
	confirmReturn = confirm(removeattach_confirm);
	if(confirmReturn == true) {
		document.input.removeattachment.value = aid;
		cancelForm = 0;
	} else {
		cancelForm = 1;
		document.input.removeattachment.value = 0;
	}
}

function checkForm() {
	if(cancelForm == 1) {
		return false;
	} else {
		return true;
	}
}

function checkAll(formName) {
	for(var i=0;i<formName.elements.length;i++) {
		var element = formName.elements[i];
		if((element.name != "allbox") && (element.type == "checkbox")) {
			element.checked = formName.allbox.checked;
		}
	}
}

function reportPost(pid) {
	popupWin("report.php?pid=" + pid, "reportPost", 400, 300)
}

function reputation(pid, type) {
	popupWin("reputation.php?pid=" + pid + "&type=" + type, "reputation", 400, 300)
}

function whoPosted(tid) {
	popupWin("misc.php?action=whoposted&tid=" + tid, "whoPosted", 230, 300)
}

function PageHopTo(tid, page, pages) {
	if(pages > 1) {
		defpage = page + 1;
	} else {
		defpage = 1;
	}
	promptres = prompt("Quick Page Jump\nPlease enter a page number between 1 and "+pages+" to jump to.", defpage);
	if((promptres != null) && (promptres != "") & (promptres > 1) && (promptres <= pages)) {
		window.location = "showthread.php?tid="+tid+"&page"+promotres;
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
			}
		} else {
			hideDiv(id+"_e");
			saveCollapsed(id, 1);
			if(collapseImage) {
				collapseImage.src = collapseImage.src.replace("collapse.gif","collapse_collapsed.gif");
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

var inlinetype = "";
var inlinecount = 0;

function begininline(id, type) {
	inlinetype = type+id;
}

function selectinline(id, element) {
	var cookiename = "inlinemod_"+inlinetype;
	var newids = new Array();
	if(inlinecookie = getcookie(cookiename)) {
		inlineids = inlinecookie.split("|");
		for(i = 0; i < inlineids.length; i++) {
			if(inlineids[i] != "") {
				if(inlineids[i] != id) {
					newids[newids.length] = inlineids[i];
				}
			}
		}
	}
	if(element.checked) {
		inlinecount++;
		newids[newids.length] = id;
	} else {
		inlinecount--;
	}
	inlinedata = "|"+newids.join("|")+"|";
	ob = getElemRefs("inline_go");
	ob.value = go_text+" ("+inlinecount+")";
	setcookie(cookiename, inlinedata, inlineexpire());
}

function inlineexpire() {
	expire = new Date();
	expire.setTime(expire.getTime()+216000);
	return expire.toGMTString();
}

function inlineunset() {
	var cookiename = "inlinemod_"+inlinetype;
	inlinecookie = getcookie(cookiename);
	inlinecount = 0;
	if(inlinecookie) {
		inlineids = inlinecookie.split("|");
		for(i = 0; i < inlineids.length; i++) {
			if(inlineids[i] != "" && inlineids[i] != null) {
				ob = getElemRefs("inlinemod_"+inlineids[i]);

				if(ob) {
					ob.checked = false;
				}
			}
		}
	}
	ob = getElemRefs("inline_go");
	ob.value = go_text+" ("+inlinecount+")";
	unsetcookie(cookiename);
}
