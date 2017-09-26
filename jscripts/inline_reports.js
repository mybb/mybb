var inlineReports = {
	init: function()
	{
		inlineReports.cookieName = 'inlinereports';
		var inputs = $('input');

		if(!inputs.length)
		{
			return false;
		}

		var inlineIds = inlineReports.getCookie(inlineReports.cookieName);
		var removedIds = inlineReports.getCookie(inlineReports.cookieName+'_removed');
		var allChecked = true;

		$(inputs).each(function() {
			var element = $(this);
			if((element.attr('name') != 'allbox') && (element.attr('type') == 'checkbox') && (element.attr('id')) && (element.attr('id').split('_')[0] == 'reports'))
			{
				$(element).click(inlineReports.checkItem);
			}

			if(element.attr('id'))
			{
				var inlineCheck = element.attr('id').split('_');
				var id = inlineCheck[1];

				if(inlineCheck[0] == 'reports')
				{
					if(inlineIds.indexOf(id) != -1 || (inlineIds.indexOf('ALL') != -1 && removedIds.indexOf(id) == -1))
					{
						element.prop('checked', true);
						var report = element.parents('.inline_row');
						if(report.length)
						{
							report.addClass('trow_selected');
						}
					}
					else
					{
						element.prop('checked', false);
						var report = element.parents('.inline_row');
						if(report.length)
						{
							report.removeClass('trow_selected');
						}
					}
					allChecked = false;
				}
			}
		});

		inlineReports.updateCookies(inlineIds, removedIds);

		if(inlineIds.indexOf('ALL') != -1 && removedIds.length == 0)
		{
			var allSelectedRow = $('#allSelectedrow');
			if(allSelectedRow)
			{
				allSelectedRow.show();
			}
		}
		else if(inlineIds.indexOf('ALL') == -1 && allChecked == true)
		{
			var selectRow = $('#selectAllrow');
			if(selectRow)
			{
				selectRow.show();
			}
		}
		return true;
	},

	checkItem: function()
	{
		var element = $(this);

		if(!element || !element.attr('id'))
		{
			return false;
		}

		var inlineCheck = element.attr('id').split('_');
		var id = inlineCheck[1];

		if(!id)
		{
			return false;
		}

		var inlineIds = inlineReports.getCookie(inlineReports.cookieName);
		var removedIds = inlineReports.getCookie(inlineReports.cookieName+'_removed');

		if(element.prop('checked') == true)
		{
			if(inlineIds.indexOf('ALL') == -1)
			{
				inlineIds = inlineReports.addId(inlineIds, id);
			}
			else
			{
				removedIds = inlineReports.removeId(removedIds, id);
				if(removedIds.length == 0)
				{
					var allSelectedRow = $('#allSelectedrow');
					if(allSelectedRow)
					{
						allSelectedRow.show();
					}
				}
			}
			var report = element.parents('.inline_row');
			if(report.length)
			{
				report.addClass('trow_selected');
			}
		}
		else
		{
			if(inlineIds.indexOf('ALL') == -1)
			{
				inlineIds = inlineReports.removeId(inlineIds, id);
				var selectRow = $('#selectAllrow');
				if(selectRow)
				{
					selectRow.hide();
				}
			}
			else
			{
				removedIds = inlineReports.addId(removedIds, id);
				var allSelectedRow = $('#allSelectedrow');
				if(allSelectedRow)
				{
					allSelectedRow.hide();
				}
			}
			var report = element.parents('.inline_row');
			if(report.length)
			{
				report.removeClass('trow_selected');
			}
		}

		inlineReports.updateCookies(inlineIds, removedIds);

		return true;
	},

	clearChecked: function()
	{
		$('#selectAllrow').hide();
		$('#allSelectedrow').hide();

		var inputs = $('input');

		if(!inputs.length)
		{
			return false;
		}

		$(inputs).each(function() {
			var element = $(this);
			if(!element.val()) return;
			if(element.attr('type') == 'checkbox' && ((element.attr('id') && element.attr('id').split('_')[0] == 'reports') || element.attr('name') == 'allbox'))
			{
				element.prop('checked', false);
			}
		});

		$('.trow_selected').each(function() {
			$(this).removeClass('trow_selected');
		});

		$('#inline_read').val(mark_read_text+' (0)');
		Cookie.unset(inlineReports.cookieName);
		Cookie.unset(inlineReports.cookieName + '_removed');

		return true;
	},

	checkAll: function(master)
	{
		inputs = $('input');
		master = $(master);

		if(!inputs.length)
		{
			return false;
		}

		var inlineIds = inlineReports.getCookie(inlineReports.cookieName);
		var removedIds = inlineReports.getCookie(inlineReports.cookieName+'_removed');

		var newIds = new Array();
		$(inputs).each(function() {
			var element = $(this);
			if(!element.val() || !element.attr('id')) return;
			inlineCheck = element.attr('id').split('_');
			if((element.attr('name') != 'allbox') && (element.attr('type') == 'checkbox') && (inlineCheck[0] == 'reports'))
			{
				var id = inlineCheck[1];
				var changed = (element.prop('checked') != master.prop('checked'));
				element.prop('checked', master.prop('checked'));

				var report = element.parents('.inline_row');
				if(report.length)
				{
					if(master.prop('checked') == true)
					{
						report.addClass('trow_selected');
					}
					else
					{
						report.removeClass('trow_selected');
					}
				}

				if(changed)
				{
					if(master.prop('checked') == true)
					{
						if(inlineIds.indexOf('ALL') == -1)
						{
							inlineIds = inlineReports.addId(inlineIds, id);
						}
						else
						{
							removedIds = inlineReports.removeId(removedIds, id);
						}
					}
					else
					{
						if(inlineIds.indexOf('ALL') == -1)
						{
							inlineIds = inlineReports.removeId(inlineIds, id);
						}
						else
						{
							removedIds = inlineReports.addId(removedIds, id);
						}
					}
				}
			}
		});

		var count = inlineReports.updateCookies(inlineIds, removedIds);

		if(count < all_text)
		{
			var selectRow = $('#selectAllrow');
			if(selectRow.length)
			{
				if(master.prop('checked') == true)
				{
					selectRow.show();
				}
				else
				{
					selectRow.hide();
				}
			}
		}

		if(inlineIds.indexOf('ALL') == -1 || removedIds.length != 0)
		{
			$('#allSelectedrow').hide();
		}
		else if(inlineIds.indexOf('ALL') != -1 && removedIds.length == 0)
		{
			$('#allSelectedrow').show();
		}
	},

	selectAll: function()
	{
		inlineReports.updateCookies(new Array('ALL'), new Array());

		$('#selectAllrow').hide();
		$('#allSelectedrow').show();
	},

	getCookie: function(name)
	{
		var inlineCookie = Cookie.get(name);

		var ids = new Array();
		if(inlineCookie)
		{
			var inlineIds = inlineCookie.split('|');
			$.each(inlineIds, function(index, item) {
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
			Cookie.set(name, data, 60 * 60 * 1000);
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
		$('#inline_read').val(mark_read_text+' ('+count+')');
		if(count == 0)
		{
			inlineReports.clearChecked();
		}
		else
		{
			inlineReports.setCookie(inlineReports.cookieName, inlineIds);
			inlineReports.setCookie(inlineReports.cookieName+'_removed', removedIds);
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

$(inlineReports.init);