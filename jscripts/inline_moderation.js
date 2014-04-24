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
		
		for(var i=0; i < inputs.length; i++)
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

				if(inlineCheck[0] == "inlinemod")
				{
					if(inlineIds.indexOf('ALL') != -1)
					{
						inlineModeration.clearChecked();
						inlineCookie = null;
					}
					else if(inlineIds.indexOf(id) != -1)
					{
						element.checked = true;
						var tr = element.up('tr');
						var fieldset = element.up('fieldset');
						
						if(tr)
						{
							tr.addClassName('trow_selected');
						}
						
						if(fieldset)
						{
							fieldset.addClassName('inline_selected');	
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
		var remIds = new Array();
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

			if(inlineCookie.indexOf("ALL") != -1)
			{
				// We've already selected all threads, add this to our "no-go" cookie
				remIds[remIds.length] = id;
			}
		}

		goButton = $("inline_go");

		if(remIds.length)
		{
			inlineData = "|"+remIds.join("|")+"|";
			Cookie.set(inlineModeration.cookieName + '_removed', inlineData, 3600000);

			// Get the right count for us
			var count = goButton.value.replace(/[^\d]/g, '');
			inlineModeration.inlineCount = count;
			inlineModeration.inlineCount--;
		}
		else
		{
			inlineData = "|"+newIds.join("|")+"|";
			Cookie.set(inlineModeration.cookieName, inlineData, 3600000);
		}

		if(inlineModeration.inlineCount < 0)
		{
			inlineModeration.inlineCount = 0;
		}

		goButton.value = go_text+" ("+inlineModeration.inlineCount+")";

		return true;
	},

	clearChecked: function()
	{
		var selectRow = document.getElementById("selectAllrow");
		if(selectRow)
		{
			selectRow.style.display = "none";
		}
		
		var allSelectedRow = document.getElementById("allSelectedrow");
		if(allSelectedRow)
		{
			allSelectedRow.style.display = "none";
		}
		
		inputs = document.getElementsByTagName("input");

		if(!inputs)
		{
			return false;
		}

		$H(inputs).each(function(element) {
			var element = element.value;
			if(!element.value) return;
			if(element.type == "checkbox" && (element.id.split("_")[0] == "inlinemod" || element.name == "allbox"))
			{
				element.checked = false;
			}
		});

		$$('tr.trow_selected').each(function(element) {
			element.removeClassName('trow_selected');
		});

		$$('fieldset.inline_selected').each(function(element) {
			element.removeClassName('inline_selected');
		});

		inlineModeration.inlineCount = 0;
		goButton = $("inline_go");
		goButton.value = go_text+" (0)";
		Cookie.unset(inlineModeration.cookieName);
		Cookie.unset(inlineModeration.cookieName + '_removed');

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
				var fieldset = element.up('fieldset');
				if(tr && master.checked == true)
				{
					tr.addClassName('trow_selected');
				}
				else
				{
					tr.removeClassName('trow_selected');
				}
				
				if(typeof(fieldset) != "undefined")
				{
					if(master.checked == true)
					{
						fieldset.addClassName('inline_selected');
					}
					else
					{
						fieldset.removeClassName('inline_selected');
					}
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
		
		if(inlineModeration.inlineCount < all_text)
		{
			var selectRow = document.getElementById("selectAllrow");
			if(selectRow)
			{
				if(master.checked == true)
				{
					selectRow.style.display = "table-row";
				}
				else
				{
					selectRow.style.display = "none";
				}
			}
		}
		
		goButton.value = go_text+" ("+inlineModeration.inlineCount+")";
		Cookie.set(inlineModeration.cookieName, inlineData, 3600000);
	},
		
	selectAll: function()
	{
		goButton.value = go_text+" ("+all_text+")";
		Cookie.set(inlineModeration.cookieName, "|ALL|", 3600000);
		
		var selectRow = document.getElementById("selectAllrow");
		if(selectRow)
		{
			selectRow.style.display = "none";
		}
		
		var allSelectedRow = document.getElementById("allSelectedrow");
		if(allSelectedRow)
		{
			allSelectedRow.style.display = "table-row";
		}
	}
};
Event.observe(document, "dom:loaded", inlineModeration.init);