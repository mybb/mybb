var inlineModeration = {
	init: function()
	{
		if(!inlineType || !inlineId)
		{
			return false;
		}

		inlineModeration.cookieName = 'inlinemod_'+inlineType+inlineId;
		var inputs = document.getElementsByTagName('input');

		if(!inputs)
		{
			return false;
		}

		var inlineIds = inlineModeration.getCookie(inlineModeration.cookieName);
		var removedIds = inlineModeration.getCookie(inlineModeration.cookieName+'_removed');
		var allChecked = true;

		for(var i=0; i < inputs.length; i++)
		{
			var element = inputs[i];
			if((element.name != 'allbox') && (element.type == 'checkbox') && (element.id.split('_')[0] == 'inlinemod'))
			{
				Event.observe(element, 'click', inlineModeration.checkItem);
			}

			var inlineCheck = element.id.split('_');
			var id = inlineCheck[1];

			if(inlineCheck[0] == 'inlinemod')
			{
				if(inlineIds.indexOf(id) != -1 || (inlineIds.indexOf('ALL') != -1 && removedIds.indexOf(id) == -1))
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
					allChecked = false;
				}
			}
		}

		inlineModeration.updateCookies(inlineIds, removedIds);

		if(inlineIds.indexOf('ALL') != -1 && removedIds.length == 0)
		{
			var allSelectedRow = document.getElementById('allSelectedrow');
			if(allSelectedRow)
			{
				allSelectedRow.style.display = 'table-row';
			}
		}
		else if(inlineIds.indexOf('ALL') == -1 && allChecked == true)
		{
			var selectRow = document.getElementById('selectAllrow');
			if(selectRow)
			{
				selectRow.style.display = 'table-row';
			}
		}
		return true;
	},

	checkItem: function(e)
	{
		var element = Event.element(e);

		if(!element)
		{
			return false;
		}

		var inlineCheck = element.id.split('_');
		var id = inlineCheck[1];

		if(!id)
		{
			return false;
		}

		var inlineIds = inlineModeration.getCookie(inlineModeration.cookieName);
		var removedIds = inlineModeration.getCookie(inlineModeration.cookieName+'_removed');

		if(element.checked == true)
		{
			if(inlineIds.indexOf('ALL') == -1)
			{
				inlineIds = inlineModeration.addId(inlineIds, id);
			}
			else
			{
				removedIds = inlineModeration.removeId(removedIds, id);
				if(removedIds.length == 0)
				{
					var allSelectedRow = document.getElementById('allSelectedrow');
					if(allSelectedRow)
					{
						allSelectedRow.style.display = 'table-row';
					}
				}
			}
			var tr = element.up('tr');
			if(tr)
			{
				tr.addClassName('trow_selected');
			}
		}
		else
		{
			if(inlineIds.indexOf('ALL') == -1)
			{
				inlineIds = inlineModeration.removeId(inlineIds, id);
				var selectRow = document.getElementById('selectAllrow');
				if(selectRow)
				{
					selectRow.style.display = 'none';
				}
			}
			else
			{
				removedIds = inlineModeration.addId(removedIds, id);
				var allSelectedRow = document.getElementById('allSelectedrow');
				if(allSelectedRow)
				{
					allSelectedRow.style.display = 'none';
				}
			}
			var tr = element.up('tr');
			if(tr)
			{
				tr.removeClassName('trow_selected');
			}
		}

		inlineModeration.updateCookies(inlineIds, removedIds);

		return true;
	},

	clearChecked: function()
	{
		var selectRow = document.getElementById('selectAllrow');
		if(selectRow)
		{
			selectRow.style.display = 'none';
		}

		var allSelectedRow = document.getElementById('allSelectedrow');
		if(allSelectedRow)
		{
			allSelectedRow.style.display = 'none';
		}

		var inputs = document.getElementsByTagName('input');

		if(!inputs)
		{
			return false;
		}

		$H(inputs).each(function(element) {
			var element = element.value;
			if(!element.value) return;
			if(element.type == 'checkbox' && (element.id.split('_')[0] == 'inlinemod' || element.name == 'allbox'))
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

		goButton = $('inline_go');
		goButton.value = go_text+' (0)';
		Cookie.unset(inlineModeration.cookieName);
		Cookie.unset(inlineModeration.cookieName + '_removed');

		return true;
	},

	checkAll: function(master)
	{
		var inputs = document.getElementsByTagName('input');

		if(!inputs)
		{
			return false;
		}

		var inlineIds = inlineModeration.getCookie(inlineModeration.cookieName);
		var removedIds = inlineModeration.getCookie(inlineModeration.cookieName+'_removed');

		var newIds = new Array();
		$H(inputs).each(function(element) {
			var element = element.value;
			if(!element.value) return;
			inlineCheck = element.id.split('_');
			if((element.name != 'allbox') && (element.type == 'checkbox') && (inlineCheck[0] == 'inlinemod'))
			{
				var id = inlineCheck[1];
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

				if(typeof(fieldset) != 'undefined')
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
						if(inlineIds.indexOf('ALL') == -1)
						{
							inlineIds = inlineModeration.addId(inlineIds, id);
						}
						else
						{
							removedIds = inlineModeration.removeId(removedIds, id);
						}
					}
					else
					{
						if(inlineIds.indexOf('ALL') == -1)
						{
							inlineIds = inlineModeration.removeId(inlineIds, id);
						}
						else
						{
							removedIds = inlineModeration.addId(removedIds, id);
						}
					}
				}
			}
		});

		var count = inlineModeration.updateCookies(inlineIds, removedIds);

		if(count < all_text)
		{
			var selectRow = document.getElementById('selectAllrow');
			if(selectRow)
			{
				if(master.checked == true)
				{
					selectRow.style.display = 'table-row';
				}
				else
				{
					selectRow.style.display = 'none';
				}
			}
		}

		if(inlineIds.indexOf('ALL') == -1 || removedIds.length != 0)
		{
			var allSelectedRow = document.getElementById('allSelectedrow');
			if(allSelectedRow)
			{
				allSelectedRow.style.display = 'none';
			}
		}
		else if(inlineIds.indexOf('ALL') != -1 && removedIds.length == 0)
		{
			var allSelectedRow = document.getElementById('allSelectedrow');
			if(allSelectedRow)
			{
				allSelectedRow.style.display = 'table-row';
			}
		}
	},

	selectAll: function()
	{
		inlineModeration.updateCookies(new Array('ALL'), new Array());

		var selectRow = document.getElementById('selectAllrow');
		if(selectRow)
		{
			selectRow.style.display = 'none';
		}

		var allSelectedRow = document.getElementById('allSelectedrow');
		if(allSelectedRow)
		{
			allSelectedRow.style.display = 'table-row';
		}
	},

	getCookie: function(name)
	{
		var inlineCookie = Cookie.get(name);

		var ids = new Array();
		if(inlineCookie)
		{
			var inlineIds = inlineCookie.split('|');
			inlineIds.each(function(item) {
				if(item != '' && item != null)
				{
					ids.push(item);
				}
			});
		}
		return ids;
	},

	setCookie: function(name, array)
	{
		if(array.length != 0)
		{
			var data = '|'+array.join('|')+'|';
			Cookie.set(name, data, 3600000);
		}
		else
		{
			Cookie.unset(name);
		}
	},

	updateCookies: function(inlineIds, removedIds)
	{
		if(inlineIds.indexOf('ALL') != -1)
		{
			var count = all_text - removedIds.length;
		}
		else
		{
			var count = inlineIds.length;
		}
		if(count < 0)
		{
			count = 0;
		}
		$('inline_go').value = go_text+' ('+count+')';
		if(count == 0)
		{
			inlineModeration.clearChecked();
		}
		else
		{
			inlineModeration.setCookie(inlineModeration.cookieName, inlineIds);
			inlineModeration.setCookie(inlineModeration.cookieName+'_removed', removedIds);
		}
		return count;
	},

	addId: function(array, id)
	{
		if(array.indexOf(id) == -1)
		{
			array.push(id);
		}
		return array;
	},

	removeId: function(array, id)
	{
		var position = array.indexOf(id);
		if(position != -1)
		{
			array.splice(position, 1);
		}
		return array;
	}
};
Event.observe(document, 'dom:loaded', inlineModeration.init);