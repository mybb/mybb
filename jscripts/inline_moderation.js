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
		
		inlineCookie = Cookie.get(inlineModeration.cookieName);
		
		if(inlineCookie)
		{
			inlineIds = inlineCookie.split("|");
		}
		
		for(var i=0; i < inputs.length; ++i)
		{
			var element = inputs[i];
			
			if((element.name != "allbox") && (element.type == "checkbox") && (element.id.split("_")[0] == "inlinemod"))
			{
				Event.observe(element, "click", inlineModeration.checkItem);
			}
			
			if(inlineCookie)
			{
				inlineCheck = element.id.split("_");
				id = inlineCheck[1];
				
				if(MyBB.inArray(id, inlineIds))
				{
					element.checked = true;
				}
				else
				{
					element.checked = false;
				}
			}
		}
		
		if(inlineCookie)
		{
			goButton = $("inline_go");
			if(inlineCookie)
			{
				var inlineCount = 0;
				for(i=0; i < inlineIds.length; ++i)
				{
					if(inlineIds[i] != '')
					{
						inlineCount++;
					}
				}
				inlineModeration.inlineCount = inlineCount;
			}
			goButton.value = go_text+" ("+(inlineModeration.inlineCount)+")";
		}	
		return true;
	},

	checkItem: function(e)
	{
		element = MyBB.eventElement(e);
		
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
			for(i=0; i < inlineIds.length; ++i)
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
		Cookie.set(inlineModeration.cookieName, inlineData, 3600000);
		
		return true;
	},
	
	clearChecked: function()
	{
		inputs = document.getElementsByTagName("input");
		
		if(!inputs)
		{
			return false;
		}
		
		for(var i=0; i < inputs.length; ++i)
		{
			var element = inputs[i];
			
			if((element.name != "allbox") && (element.type == "checkbox") && (element.id.split("_")[0] == "inlinemod"))
			{
				element.checked = false;
			}
		}

		inlineModeration.inlineCount = 0;
		goButton = $("inline_go");
		goButton.value = go_text+" (0)";
		Cookie.unset(inlineModeration.cookieName);
		
		return true;
	},
	
	checkAll: function(master)
	{
		inputs = document.getElementsByTagName("input");
		
		if(!inputs)
		{
			return false;
		}
		
		inlineCookie = Cookie.get(inlineModeration.cookieName);
		
		if(inlineCookie)
		{
			inlineIds = inlineCookie.split("|");
		}
		
		var newIds = new Array();
		for(var i=0; i < inputs.length; ++i)
		{
			var element = inputs[i];
			inlineCheck = element.id.split("_");
			
			if((element.name != "allbox") && (element.type == "checkbox") && (inlineCheck[0] == "inlinemod"))
			{
				id = inlineCheck[1];
				element.checked = master.checked;
				
				if(master.checked == true)
				{
					inlineModeration.inlineCount++;
					newIds[newIds.length] = id;
				}
				else
				{
					inlineModeration.inlineCount--;	
				}
			}
		}
		
		inlineData = "|"+newIds.join("|")+"|";
		goButton = $("inline_go");
		
		if(inlineModeration.inlineCount < 0)
		{
			inlineModeration.inlineCount = 0;
		}
		goButton.value = go_text+" ("+inlineModeration.inlineCount+")";
		Cookie.set(inlineModeration.cookieName, inlineData, 3600000);		
	}
}
Event.observe(window, "load", inlineModeration.init);