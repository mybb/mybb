// Initial code for Autocomplete was based
// on that at
// http://www.papermountain.org/demos/live/
// All other code is copyright 2005 Chris Boulton

if (navigator.userAgent.indexOf("Safari") > 0)
{
  isSafari = true;
  isMoz = false;
  isIE = false;
}
else if (navigator.product == "Gecko")
{
  isSafari = false;
  isMoz = true;
  isIE = false;
}
else
{
  isSafari = false;
  isMoz = false;
  isIE = true;
}

var request = false;
var callbackFunc = false;

function debug_text(text)
{
	document.myform.debug.value = document.myform.debug.value + "\n"+text;
}

function xmlHttpRequest(urlFunc, callback)
{
	if(window.XMLHttpRequest)
	{
		request = new XMLHttpRequest();
	}

	function newRequest()
	{
		if(!window.XMLHttpRequest)
		{
			request = new ActiveXObject("Microsoft.XMLHTTP");
		}
		if(request && request.readyState < 4)
		{
			request.abort();
		}
		request.onreadystatechange = stateChange;
		request.open("GET", urlFunc(), true);
		request.send(null);
		return false;
	}

	function stateChange()
	{
		if(request.readyState == 4)
		{
			if(callback)
			{
				callback(request.responseText);
			}
		}
	}
	return newRequest;
}

function buildURL(url, value)
{
	var separator = "?";
	if(url.indexOf("?") >= 0)
	{
		separator = "&";
	}
	return url+separator+"query="+escape(value);
}

function autoComplete(id, url)
{
	var textBox = document.getElementById(id);
	var popup = document.getElementById(id+"-popup");
	var popupTop = popup.offsetTop;
	var current = 0;
	function completeURL()
	{
		return buildURL(url, textBox.value);
	}

	function hidePopup()
	{
		popup.style.visibility = "hidden";
	}

	function handlePopupOver()
	{
		removeListener(textBox, "blur", hidePopup);
	}
    
	function handlePopupOut()
	{
		attachListener(textBox, "blur", hidePopup);
	}

	function handleClick(element)
	{
		textBox.value = eventElement(element).innerHTML;
		popup.style.visibility = "hidden";
		textBox.focus();
	}

	function handleOver(element)
	{
		popup.firstChild.childNodes[current].className = "";
		current = eventElement(element).index;
		popup.firstChild.childNodes[current].className = "selected";
	}

	function addOptionEvents(option)
	{
		attachListener(option, "click", handleClick);
		attachListener(option, "mouseover", handleOver);
	}

	function buildPopup(results)
	{
		var maxHeight;
		popup.innerHTML = results;
		var options = popup.firstChild.childNodes;
		if((options.length > 1) || (options.length == 1 && options[0].innerHTML != textBox.value))
		{
			
			// Set the styles for the popup
			if(isIE)
			{

				maxHeight = 200;
				popup.style.left = "0px";
				popup.style.top = (popupTop+textBox.offsetHeight)+"px";
				popup.style.width = textBox.offsetWidth-2+"px";
			}
			else
			{
				maxHeight = window.outerHeight / 3;
				popup.style.width = textBox.offsetWidth-2+"px";
			}
			if(popup.offsetHeight < maxHeight)
			{
				popup.style.overflow = "hidden";
			}
			else if(isMoz)
			{
				popup.style.maxHeight = maxHeight+"px";
				popup.style.overflow = "-moz-scrollbars-vertical";
			}
			else
			{
				popup.style.height = maxHeight+"px";
				popup.style.overflowY = "auto";
			}
			popup.scrollTop = 0;
			popup.style.visibility = "visible";
			i = 0;
			for(i = 0; i < options.length; i++)
			{
				options[i].index = i;
				addOptionEvents(options[i]);
			}
			options[0].className = "selected";
		}
	}

	var completeRequest = xmlHttpRequest(completeURL, buildPopup);
	var timeout = false;

	function start(element)
	{
		if(timeout)
		{
			window.clearTimeout(timeout);
		}

		// Up
		if(element.keyCode == 38)
		{
			if(current > 0)
			{
				popup.firstChild.childNodes[current].className = "";
				current--;
				popup.firstChild.childNodes[current].className = "selected";
				popup.firstChild.childNodes[current].scrollIntoView(true);
			}
			if(isIE)
			{
				event.returnValue = false;
			}
			else
			{
				element.preventDefault();
			}
		}

		// Down
		else if(element.keyCode == 40)
		{
			if(current < popup.firstChild.childNodes.length - 1)
			{
				popup.firstChild.childNodes[current].className = "";
				current++;
				popup.firstChild.childNodes[current].className = "selected";
				popup.firstChild.childNodes[current].scrollIntoView(false);
			}
			if(isIE)
			{
				event.returnValue = false;
			}
			else
			{
				element.preventDefault();
			}
		}

		// Enter / Tab
		else if(element.keyCode == 13 || element.keyCode == 9)
		{
			if(popup.style.visibility == "visible")
			{
				textBox.value = popup.firstChild.childNodes[current].innerHTML;
				popup.style.visibility = "hidden";
				if(isIE)
				{
					event.returnValue = false;
				}
				else
				{
					element.preventDefault();
				}
			}
		}

		// Escape
		else if(element.keyCode == 27)
		{
			hidePopup();
		}
		else
		{
			timeout = window.setTimeout(completeRequest, 300);
		}
	}
	attachKeyListener(textBox, start);
	attachListener(popup, "mouseover", handlePopupOver);
	attachListener(popup, "mouseout", handlePopupOut);

}

function fetchData(url, elementID)
{
	function fetchDataResult(result)
	{
		var element = document.getElementById(elementID);
		var regex = /<result>((.|\n)*)<\/result>/;
		if(element && result.match(regex))
		{
			result = regex.exec(result);
			element.innerHTML = result[1];
		}
	}

	function fetchDataURL()
	{
		return url;
	}
	var completeRequest = xmlHttpRequest(fetchDataURL, fetchDataResult)
	completeRequest();	
}

function instantEdit(span, url)
{
	var oldValue = span.innerHTML;
	var newValue = "";

	if(span.parentNode)
	{
		parentItem = span.parentNode;
	}
	else if(span.parentElement)
	{
		parentItem = span.parentElement;
	}

	function instantUpdateResult(result)
	{
		removeListener(parentItem.firstChild, "blur", handleBlur);
		removeKeyListener(parentItem.firstChild, handleEnter);
		var regex = /<error>((.|\n)*)<\/error>/;
		if(result.match(regex))
		{
			var errorMsg = regex.exec(result);
			alert(errorMsg[1]);
		}
		instantUpdateSpan();
	}

	function instantUpdateURL()
	{
		return buildURL(url, newValue);
	}

	function handleBlur(element)
	{
		newValue = eventElement(element).value;
		saveUpdate();
	}

	function handleEnter(element)
	{
		newValue = parentItem.firstChild.value;
		if(element.keyCode == 13)
		{
			saveUpdate();
		}
	}

	function saveUpdate()
	{
		if(newValue && newValue != oldValue)
		{
			var completeRequest = xmlHttpRequest(instantUpdateURL, instantUpdateResult)
			completeRequest();
		}
		else
		{
			newValue = oldValue;
			instantUpdateSpan();
		}
	}

	function instantUpdateSpan()
	{
		parentItem.innerHTML = "<span style=\"font-size: 13px;\" ondblclick=\"javascript:instantEdit(this, '"+url+"');\">"+newValue+"</span>";
	}

	if(parentItem)
	{
		parentItem.innerHTML = "<input type=\"text\" value=\""+span.innerHTML+"\" size=\"30\" />";
		parentItem.firstChild.focus();
		attachKeyListener(parentItem.firstChild, handleEnter);
		attachListener(parentItem.firstChild, "blur", handleBlur);
	}
}


function eventElement(event)
{
  if(isMoz)
  {
    return event.currentTarget;
  }
  else
  {
    return event.srcElement;
  }
}

function attachListener(element, type, listener)
{
	if(element.addEventListener)
	{
		element.addEventListener(type, listener, false);
	}
	else
	{
		element.attachEvent("on"+type, listener, false);
	}
}

function removeListener(element, type, listener)
{
	if(element.removeEventListener)
	{
		element.removeEventListener(type, listener, false);
	}
	else
	{
		element.detachEvent("on"+type, listener);
	}
}

function attachKeyListener(element, listener)
{
	if(isSafari)
	{
		element.addEventListener("keydown", listener, false);
	}
	else if(isMoz)
	{
		element.addEventListener("keypress", listener, false);
	}
	else
	{
		element.attachEvent("onkeydown", listener);
	}
}

function removeKeyListener(element, listener)
{
	if(isSafari)
	{
		element.removeEventListener("keydown", listener, false);
	}
	else if(isMoz)
	{
		element.removeEventListener("keypress", listener, false);
	}
	else
	{
		element.detachEvent("onkeydown", listener);
	}
}