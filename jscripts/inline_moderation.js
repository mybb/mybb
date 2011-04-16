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
		
		for(var i=0;i<inputs.length;i++)
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

				if(inlineIds.indexOf(id) != -1)
				{
					element.checked = true;
					var tr = element.up('tr');
					
					if(tr)
					{
						tr.addClassName('trow_selected');
					}
				}
				else
				{
					element.checked = false;
					var tr = element.up('tr');
					if(tr)
					{
						tr.removeClassName('trow_selected');
					}
				}
			}
		}
		
		if(inlineCookie)
		{
			goButton = $("inline_go");
			if(inlineIds)
			{
				var inlineCount = 0;
				inlineIds.each(function(item) {
					if(item != '') inlineCount++;
				});
				inlineModeration.inlineCount = inlineCount;
			}
			goButton.value = go_text+" ("+(inlineModeration.inlineCount)+")";
		}
		return true;
	},

	checkItem: function(e)
	{
		element = Event.element(e);

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
			inlineIds.each(function(item) {
				if(item != "" && item != null)
				{
					if(item != id)
					{
						newIds[newIds.length] = item;
					}
				}
			});
		}

		if(element.checked == true)
		{
			inlineModeration.inlineCount++;
			newIds[newIds.length] = id;
			var tr = element.up('tr');
			if(tr)
			{
				tr.addClassName('trow_selected');
			}
		}
		else
		{
			inlineModeration.inlineCount--;
			var tr = element.up('tr');
			if(tr)
			{
				tr.removeClassName('trow_selected');
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

		return true;
	},

	clearChecked: function()
	{
		inputs = document.getElementsByTagName("input");

		if(!inputs)
		{
			return false;
		}

		$H(inputs).each(function(element) {
			var element = element.value;
			if(!element.value) return;
			if((element.name != "allbox") && (element.type == "checkbox") && (element.id.split("_")[0] == "inlinemod"))
			{
				element.checked = false;
			}
		});

		$$('tr.trow_selected').each(function(element) {
			element.removeClassName('trow_selected');
		});

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
		$H(inputs).each(function(element) {
			var element = element.value;
			if(!element.value) return;
			inlineCheck = element.id.split("_");
			if((element.name != "allbox") && (element.type == "checkbox") && (inlineCheck[0] == "inlinemod"))
			{
				id = inlineCheck[1];
				var changed = (element.checked != master.checked);
				element.checked = master.checked;
				
				var tr = element.up('tr');
				if(tr && master.checked == true)
				{
					tr.addClassName('trow_selected');
				}
				else
				{
					tr.removeClassName('trow_selected');
				}

				if(changed)
				{
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
		});

		inlineData = "|"+newIds.join("|")+"|";
		goButton = $("inline_go");

		if(inlineModeration.inlineCount < 0)
		{
			inlineModeration.inlineCount = 0;
		}

		goButton.value = go_text+" ("+inlineModeration.inlineCount+")";
		Cookie.set(inlineModeration.cookieName, inlineData, 3600000);
	}
};
Event.observe(document, "dom:loaded", inlineModeration.init);