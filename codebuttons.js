var myTags = new Array();
var buttonOver = "";

init();

function init() {
	var cookies = document.cookie;
	var cookiename = "cb_mode=";
	var cstart = cookies.indexOf("; "+cookiename);
	if(cstart == -1) {
		cstart = cookies.indexOf(" "+cookiename);
		if(cstart != 0) {
			normalmode = false;
		}
	} else {
		cstart += 2;
	}
	var end = cookies.indexOf(";", cstart);
	if(end == -1) {
		end = cookies.length;
	}
	theMode = unescape(cookies.substring((cstart + cookiename.length)+1, end));
	if(theMode == "normal") {
		document.input.mode[0].checked = true;
		document.input.mode[1].checked = false;
		normalmode = true;
	} else {
		document.input.mode[1].checked = true;
		document.input.mode[0].checked = false;
		normalmode = false;
	}
}

function changeMode(newMode) {
	document.cookie = "cb_mode="+newMode+"; path=/; expires=Wed, 1 Jan 2020 00:00:00 GMT;";
	if(newMode == "normal") {
		normalmode = true;
	} else {
		normalmode = false;
	}
}

function garraySize(arrayName) {
	for (i=0;i<arrayName.length;i++) {
		if((arrayName[i] == "undefined") || (arrayName[i] == "") || (arrayName[i] == null)) {
			return i;
		}
	}
	return arrayName.length;
}

function arrayPush(arrayName, arrayValue) {
	arraySize = garraySize(arrayName);
	arrayName[arraySize] = arrayValue;
}

function arrayPop(arrayName) {
	arrayMo = garraySize(arrayName);
	arrayValue = arrayName[arrayMo - 1];
	delete arrayName[arrayMo - 1];
	return arrayValue;
}

function inArray(item, arrayName) {
	for(i=0;i<arrayName.length;i++) {
		if(arrayName[i] == item) {
			return true;
		}
	}
	return false;
}
function arrayPos(item, arrayName) {
	for(i=0;i<arrayName.length;i++) {
		if(arrayName[i] == item) {
			return i;
		}
	}
	return 0;
}

function insertList(type) {
	if(type) {
		theList = "[list="+type+"]\n";
	} else {
		theList = "[list]\n";
	}
	promptRes = "1";
	while((promptRes != "") && (promptRes != null)) {
		promptRes = prompt("Enter a list item. Click cancel or leave blank to end the list.", "");
		if((promptRes != "") && (promptRes != null)) {
			theList = theList+"[*]"+promptRes+"\n";
		}
	}
	theList = theList+"[/list]\n";
	doInsert(theList);
}
function insertHyperlink() {
	var url = prompt("Please enter the URL of the website.", "http://");
	if(url) {
		var urltitle = prompt("If you wish to, you may also insert a title to be shown instead of the URL.", "");
		if(urltitle) {
			doInsert("[url="+url+"]"+urltitle+"[/url]", "", false);
		} else {
			doInsert("[url]"+url+"[/url]", "", false);
		}
	} else {
		alert("Error!\n\nYou did not enter a URL for the website. Please try again.");
	}
}
function insertImage() {
	var image = prompt("Please enter the remote URL of the image.", "http://");
	if(image) {
		doInsert("[img]"+image+"[/img]", "", false);
	} else {
		alert("Error!\n\nYou did not enter a URL for the image. Please try again.");
	}
}
function insertEmail() {
	var email = prompt("Please enter the email address.", "");
	if(email) {
		var emailtitle = prompt("If you wish to, you may also insert a title to be shown instead of the email address.", "My Email Address");
		if(emailtitle) {
			doInsert("[email="+email+"]"+emailtitle+"[/email]", "", false);
		} else {
			doInsert("[email]"+email+"[/email]", "", false);
		}
	} else {
		alert("Error!\n\nYou did not enter a valid email address. Please try again.");
	}
}
function insertCode(myCode,selItem) {
	if(myCode == "list") {
		insertList(selItem);
	} else if(myCode == "url") {
		insertHyperlink();
	} else if(myCode == "img") {
		insertImage();
	} else if(myCode == "email") {
		insertEmail();
	} else {
		if(normalmode) {
			var prompttext = eval(myCode + "_prompt");
			if(selItem) {
				if(selItem != "-") {
					promptres = prompt(prompttext + "\n["+myCode+"="+selItem+"]xxx[/"+myCode+"]", "");
				}
			} else {
				promptres = prompt(prompttext + "\n["+myCode+"]xxx[/"+myCode+"]", "");
			}
			if((promptres != null) && (promptres != "")) {
				if(selItem) {
					if(selItem != "-") {
						doInsert("["+myCode+"="+selItem+"]"+promptres+"[/"+myCode+"]");
					}
				} else {
					doInsert("["+myCode+"]"+promptres+"[/"+myCode+"]");
				}
			} 
		} else {
			var thingA = myCode+"_"+selItem;
			args = thingA.split("_");
			lastIndex = 0;
			alreadyopen = false;
			for(i=0;i<myTags.length;i++) {
				theTag = myTags[i];
				if(theTag) {
					args2 = theTag.split("_");
					if(args2[0] == args[0]) {
						alreadyopen = true;
						lastIndex = i;
					}
				}
			}
			if(alreadyopen) {
				while(myTags[lastIndex]) {
					tagRemove = arrayPop(myTags);
					thingB = tagRemove.split("_");
					doInsert("[/"+thingB[0]+"]", "", false);
					// unclick it
					element = eval("document.images['"+tagRemove+"']");
					if(element && !element.disabled && element.nodeName == "IMG") {
						if(buttonOver == tagRemove) {
							element.className = "toolbar_hover";
						} else {
							element.className = "toolbar_normal";
						}
					}
					if(thingA == tagRemove) { // silly check =P
						noInsert = true;
					}
				}
			}

			if(selItem && selItem != "-" && !noInsert) {
				isClosed = doInsert("["+myCode+"="+selItem+"]", "[/"+myCode+"]", true);
				if(!isClosed) {
					arrayPush(myTags, myCode+"_"+selItem);
				} else {
					// change the buttons status to closed :P
					element = eval("document.images['"+myCode+"']");
					if(element && !element.disabled && element.nodeName == "IMG") {
						element.className = "toolbar_normal";
					}
				}
			}
			else if(!selItem && !alreadyopen) {
				isClosed = doInsert("["+myCode+"]", "[/"+myCode+"]", true);
				if(!isClosed) {
					arrayPush(myTags, myCode);
				} else {
					// change the buttons status to closed :P
					element = eval("document.images['"+myCode+"']");
					if(element && !element.disabled && element.nodeName == "IMG") {
						element.className = "toolbar_normal";
					}
				}
			}
			var alreadyopen = false;
			var noInsert = false;
		}
		if(myCode == "font")
		{
			document.input.font.selectedIndex = "0";
		}
		else if(myCode == "size")
		{
			document.input.size.selectedIndex = "0";
		}
		else if(myCode == "color")
		{
			document.input.color.selectedIndex = "0";
		}
	}
	setFocus(document.input.message);	
}

function closeTags() {
	if(myTags[0]) {
		while(myTags[0]) {
			tagRemove = arrayPop(myTags);
			args = tagRemove.split("_");
			isClosed = doInsert("[/"+args[0]+"]", "", false, true);
			element = eval("document.images['"+tagRemove+"']");
			if(element && !element.disabled && element.nodeName == "IMG") {
				element.className = "toolbar_normal";
			}
		}
	}
	myTags = new Array();
	setFocus(document.input.message);
}

function doInsert(myCode, myClose, singleTag, ignoreSel) {
	isClosed = true;
	if(ignoreSel != true) {
		ignoreSel = false;
	}
	var messageBox = document.input.message;
	if(is_ie && is_win && (agt_ver >= 4)) {
		setFocus(messageBox);
		var seltext = document.selection;
		var range = seltext.createRange();
		if(ignoreSel != false) {
			range.collapse;
		}
		if(((seltext.type == "Text" || seltext.type == "None") && range != null) && ignoreSel != true) {
			if(myClose && range.text.length > 0) {
				myCode = myCode+range.text+myClose;
			} else {
				if(singleTag) {
					isClosed = false;
				}
			}
			range.text = myCode;
		} else {
			messageBox.value += myCode;
		}
	}
	else if(is_mozilla && messageBox.selectionEnd) {
		var select_start = messageBox.selectionStart;
		var select_end = messageBox.selectionEnd;
		if(select_end <= 2) {
			select_end = messageBox.textLength;
		}
		var start = (messageBox.value).substring(0, select_start);
		var middle = (messageBox.value).substring(select_start, select_end);
		var end = (messageBox.value).substring(select_end, messageBox.textLength);
		if((messageBox.selectionEnd - messageBox.selectionStart > 0) && ignoreSel != true) {
			middle = myCode+middle+myClose;
		} else {
			if(singleTag) {
				isClosed = false;
			}
			middle = myCode+middle;
		}
		messageBox.value = start+middle+end;
	}
	else {
		messageBox.value += myCode;
		if(singleTag) {
			isClosed = false;
		}
	}
	setFocus(messageBox);
				
	return isClosed;
}
function setFocus(formElement) {
	formElement.focus();
}
function addsmilie(smilie) {
	doInsert(smilie);
}

// these are our cool functions for hovering/highlighting etc...nifty...huh?
function toolbarHover(myCode, method) {
	element = eval("document.images['"+myCode+"']");
	if(element && !element.disabled && element.nodeName == "IMG") {
		element.className = "toolbar_hover";
		buttonOver = myCode;
	}
}
function toolbarUnHover(myCode) {
	element = eval("document.images['"+myCode+"']");
	if(element && !element.disabled && element.nodeName == "IMG") {
		if(inArray(myCode, myTags)) {
			element.className = "toolbar_clicked";
		} else {
			element.className = "toolbar_normal";
		}
	}
	buttonOver = "";
}
function toolbarMouseDown(myCode) {
	element = eval("document.images['"+myCode+"']");
	if(element && !element.disabled && element.nodeName == "IMG") {
		element.className = "toolbar_mousedown";
	}
}