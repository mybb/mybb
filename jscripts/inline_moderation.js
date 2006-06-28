var inlineModeration = {

	init: function()
	{
		inlineModeration.inlineCount = 0;
		if(!inlineType || !inlineId)
		{
			return false;
		}
		inlineModeration.cookieName = "inlinemod_"+inlineType+inlineId;
		inputs = document.getElementsByTagName("input");
		if(!inputs)
		{
			return false;
		}
		for(var i=0;i<inputs.length;i++)
		{
			var element = inputs[i];
			if((element.name != "allbox") && (element.type == "checkbox"))
			{
				Event.observe(element, "click", inlineModeration.checkItem);
			}
		}
		return true;
	},

	checkItem: function(e)
	{
		element = MyBB.eventElement(e)
		if(!element)
		{
			return false;
		}
		inlineCheck = element.id.split("_");
		id = inlineCheck[1];
		if(!id)
		{
			return false;
		}
		var newIds = new Array();
		inlineCookie = Cookie.get(inlineModeration.cookieName);
		if(inlineCookie)
		{
			inlineIds = inlineCookie.split("|");
			for(i=0;i<inlineIds.length;i++)
			{
				if(inlineIds[i] != "" && inlineIds != null)
				{
					if(inlineIds[i] != id)
					{
						newIds[newIds.length] = inlineIds[i];
					}
				}
			}
		}
		
		if(element.checked == true)
		{
			inlineModeration.inlineCount++;
			newIds[newIds.length] = id;
		}
		else
		{
			inlineModeration.inlineCount--;
		}
		inlineData = "|"+newIds.join("|")+"|";
		goButton = $("inline_go");
		if(inlineModeration.inlineCount < 0)
		{
			inlineModeration.inlineCount = 0;
		}
		goButton.value = go_text+" ("+inlineModeration.inlineCount+")";
		Cookie.set(inlineModeration.cookieName, inlineData, 120);
		return true;
	},
	
	clearChecked: function()
	{
		inputs = document.getElementsByTagName("input");
		if(!inputs)
		{
			return false;
		}
		for(var i=0;i<inputs.length;i++)
		{
			var element = inputs[i];
			if((element.name != "allbox") && (element.type == "checkbox"))
			{
				element.checked = false;
			}
		}

		inlineModeration.inlineCount = 0;
		goButton = $("inline_go");
		goButton.value = go_text+" (0)";
		Cookie.unset(inlineModeration.cookieName);
		return true;
	}
}
Event.observe(window, "load", inlineModeration.init);