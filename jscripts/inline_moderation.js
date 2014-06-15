var inlineModeration = {
	init: function()
	{
		if(!inlineType || !inlineId)
		{
			return false;
		}

		inlineModeration.cookieName = 'inlinemod_'+inlineType+inlineId;
		var inputs = $('input');

		if(!inputs)
		{
			return false;
		}

		var inlineIds = inlineModeration.getCookie(inlineModeration.cookieName);
		var removedIds = inlineModeration.getCookie(inlineModeration.cookieName+'_removed');
		var allChecked = true;

		$(inputs).each(function() {
			var element = $(this);
			if((element.attr('name') != 'allbox') && (element.attr('type') == 'checkbox') && (element.attr('id')) && (element.attr('id').split('_')[0] == 'inlinemod'))
			{
				$(element).click(inlineModeration.checkItem);
			}

			if(element.attr('id'))
			{
				var inlineCheck = element.attr('id').split('_');
				var id = inlineCheck[1];

				if(inlineCheck[0] == 'inlinemod')
				{
					if(inlineIds.indexOf(id) != -1 || (inlineIds.indexOf('ALL') != -1 && removedIds.indexOf(id) == -1))
					{
						element.prop('checked', true);
						var post = element.parents('div.post_content');
						var thread = element.parents('tr');
						var fieldset = element.parents('fieldset');
						if(post.length > 0)
						{
							post.addClass('trow_selected');
						}
						else if(thread.length > 0)
						{
							thread.children('td').addClass('trow_selected');
						}
						else if(fieldset.length > 0)
						{
							fieldset.addClass('inline_selected');
						}

					}
					else
					{
						element.prop('checked', false);
						var post = element.parents('div.post_content');
						var thread = element.parents('tr');
						if(post.length > 0)
						{
							post.removeClass('trow_selected');
						}
						else if(thread.length > 0)
						{
							thread.children('td').removeClass('trow_selected');
						}
					}
					allChecked = false;
				}
			}
		});

		inlineModeration.updateCookies(inlineIds, removedIds);

		if(inlineIds.indexOf('ALL') != -1 && removedIds.length == 0)
		{
			var allSelectedRow = $('#allSelectedrow');
			if(allSelectedRow)
			{
				allSelectedRow.css('display', 'table-row');
			}
		}
		else if(inlineIds.indexOf('ALL') == -1 && allChecked == true)
		{
			var selectRow = $('#selectAllrow');
			if(selectRow)
			{
				selectRow.css('display', 'table-row');
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

		var inlineIds = inlineModeration.getCookie(inlineModeration.cookieName);
		var removedIds = inlineModeration.getCookie(inlineModeration.cookieName+'_removed');

		if(element.prop('checked') == true)
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
					var allSelectedRow = $('#allSelectedrow');
					if(allSelectedRow)
					{
						allSelectedRow.css('display', 'table-row');
					}
				}
			}
			var post = element.parents('div.post_content');
			var thread = element.parents('tr');
			if(post.length > 0)
			{
				post.addClass('trow_selected');
			}
			else if(thread.length > 0)
			{
				thread.children('td').addClass('trow_selected');
			}
		}
		else
		{
			if(inlineIds.indexOf('ALL') == -1)
			{
				inlineIds = inlineModeration.removeId(inlineIds, id);
				var selectRow = $('#selectAllrow');
				if(selectRow)
				{
					selectRow.css('display', 'none');
				}
			}
			else
			{
				removedIds = inlineModeration.addId(removedIds, id);
				var allSelectedRow = $('#allSelectedrow');
				if(allSelectedRow)
				{
					allSelectedRow.css('display', 'none');
				}
			}
			var post = element.parents('div.post_content');
			var thread = element.parents('tr');
			if(post.length > 0)
			{
				post.removeClass('trow_selected');
			}
			else if(thread.length > 0)
			{
				thread.children('td').removeClass('trow_selected');
			}
		}

		inlineModeration.updateCookies(inlineIds, removedIds);

		return true;
	},

	clearChecked: function()
	{
		var selectRow = $('#selectAllrow');
		if(selectRow)
		{
			selectRow.css('display', 'none');
		}

		var allSelectedRow = $('#allSelectedrow');
		if(allSelectedRow)
		{
			allSelectedRow.css('display', 'none');
		}

		var inputs = $('input');

		if(!inputs)
		{
			return false;
		}

		$(inputs).each(function() {
			var element = $(this);
			if(!element.val()) return;
			if(element.attr('type') == 'checkbox' && ((element.attr('id') && element.attr('id').split('_')[0] == 'inlinemod') || element.attr('name') == 'allbox'))
			{
				element.prop('checked', false);
			}
		});

		$('div.trow_selected').each(function() {
			$(this).removeClass('trow_selected');
		});

		$('td.trow_selected').each(function() {
			$(this).removeClass('trow_selected');
		});

		$('fieldset.inline_selected').each(function() {
			$(this).removeClass('inline_selected');
		});

		$('#inline_go').val(go_text+' (0)');
		$.removeCookie(inlineModeration.cookieName);
		$.removeCookie(inlineModeration.cookieName + '_removed');

		return true;
	},

	checkAll: function(master)
	{
		inputs = $('input');
		master = $(master);

		if(!inputs)
		{
			return false;
		}

		var inlineIds = inlineModeration.getCookie(inlineModeration.cookieName);
		var removedIds = inlineModeration.getCookie(inlineModeration.cookieName+'_removed');

		var newIds = new Array();
		$(inputs).each(function() {
			var element = $(this);
			if(!element.val() || !element.attr('id')) return;
			inlineCheck = element.attr('id').split('_');
			if((element.attr('name') != 'allbox') && (element.attr('type') == 'checkbox') && (inlineCheck[0] == 'inlinemod'))
			{
				var id = inlineCheck[1];
				var changed = (element.prop('checked') != master.prop('checked'));
				element.prop('checked', master.prop('checked'));

				var post = element.parents('div.post_content');
				var fieldset = element.parents('fieldset');
				var thread = element.parents('tr');
				if(post.length > 0)
				{
					if(master.prop('checked') == true)
					{
						post.addClass('trow_selected');
					}
					else
					{
						post.removeClass('trow_selected');
					}
				}
				else if(thread.length > 0)
				{
					if(master.prop('checked') == true)
					{
						thread.children('td').addClass('trow_selected');
					}
					else
					{
						thread.children('td').removeClass('trow_selected');
					}
				}
				else if(fieldset.length > 0)
				{
					if(master.prop('checked') == true)
					{
						fieldset.addClass('inline_selected');
					}
					else
					{
						fieldset.removeClass('inline_selected');
					}
				}

				if(changed)
				{
					if(master.prop('checked') == true)
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
			var selectRow = $('#selectAllrow');
			if(selectRow)
			{
				if(master.prop('checked') == true)
				{
					selectRow.css('display', 'table-row');
				}
				else
				{
					selectRow.css('display', 'none');
				}
			}
		}

		if(inlineIds.indexOf('ALL') == -1 || removedIds.length != 0)
		{
			var allSelectedRow = $('#allSelectedrow');
			if(allSelectedRow)
			{
				allSelectedRow.css('display', 'none');
			}
		}
		else if(inlineIds.indexOf('ALL') != -1 && removedIds.length == 0)
		{
			var allSelectedRow = $('#allSelectedrow');
			if(allSelectedRow)
			{
				allSelectedRow.css('display', 'table-row');
			}
		}
	},

	selectAll: function()
	{
		inlineModeration.updateCookies(new Array('ALL'), new Array());

		var selectRow = $('#selectAllrow');
		if(selectRow)
		{
			selectRow.css('display', 'none');
		}

		var allSelectedRow = $('#allSelectedrow');
		if(allSelectedRow)
		{
			allSelectedRow.css('display', 'table-row');
		}
	},

	getCookie: function(name)
	{
		var inlineCookie = $.cookie(name);

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
			var date = new Date();
			date.setTime(date.getTime() + (60 * 60 * 1000));
			$.cookie(name, data, { expires: date });
		}
		else
		{
			$.removeCookie(name);
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
		$('#inline_go').val(go_text+' ('+count+')');
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
$(inlineModeration.init);
