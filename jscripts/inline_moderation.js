var inlineModeration = {
	init: function()
	{
		inlineModeration.inlineCount = 0;
		if(!inlineType || !inlineId)
		{
			return false;
		}

		inlineModeration.cookieName = "inlinemod_"+inlineType+inlineId;
		inputs = $("input");

		if(!inputs)
		{
			return false;
		}

		inlineCookie = $.cookie(inlineModeration.cookieName);

		if(inlineCookie)
		{
			inlineIds = inlineCookie.split("|");
		}
		
		$(inputs).each(function() {
			var element = $(this);
			if((element.attr('name') != "allbox") && (element.attr('type') == "checkbox") && (element.attr('id')) && (element.attr('id').split("_")[0] == "inlinemod"))
			{
				$(element).click(inlineModeration.checkItem);
			}

			if(inlineCookie && element.attr('id'))
			{
				inlineCheck = element.attr('id').split("_");
				id = inlineCheck[1];

				if(inlineIds.indexOf('ALL') != -1)
				{
					inlineModeration.clearChecked();
					inlineCookie = null;
				}
				else if(inlineIds.indexOf(id) != -1)
				{
					element.prop('checked', true);
					var post = element.parents('.post_content');
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
					var post = element.parents('.post_content');
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
			}
		});
		
		if(inlineCookie)
		{
			goButton = $("#inline_go");
			if(inlineIds)
			{
				var inlineCount = 0;
				$.each(inlineIds, function(index, item) {
					if(item != '') inlineCount++;
				});
				inlineModeration.inlineCount = inlineCount;
			}
			goButton.val(go_text+" ("+(inlineModeration.inlineCount)+")");
		}
		return true;
	},

	checkItem: function()
	{
		element = $(this);

		if(!element || !element.attr('id'))
		{
			return false;
		}

		inlineCheck = element.attr('id').split("_");
		id = inlineCheck[1];

		if(!id)
		{
			return false;
		}

		var newIds = new Array();
		var remIds = new Array();
		inlineCookie = $.cookie(inlineModeration.cookieName);

		if(inlineCookie)
		{
			inlineIds = inlineCookie.split("|");
			$.each(inlineIds, function(index, item) {
				if(item != "" && item != null)
				{
					if(item != id)
					{
						newIds[newIds.length] = item;
					}
				}
			});
		}

		if(element.prop('checked') == true)
		{
			inlineModeration.inlineCount++;
			newIds[newIds.length] = id;

			var post = element.parents('.post_content');
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
			inlineModeration.inlineCount--;
			var post = element.parents('.post_content');
      var thread = element.parents('tr');
			if(post.length > 0)
			{
				post.removeClass('trow_selected');
			}
      else if(thread.length > 0)
      {
        thread.children('td').removeClass('trow_selected');
      }

			if(inlineCookie && inlineCookie.indexOf("ALL") != -1)
			{
				// We've already selected all threads, add this to our "no-go" cookie
				remIds[remIds.length] = id;
			}
		}

		goButton = $("#inline_go");
    
    var date = new Date();
    date.setTime(date.getTime() + (60 * 60 * 1000));

		if(remIds.length)
		{
			inlineData = "|"+remIds.join("|")+"|";
			$.cookie(inlineModeration.cookieName + '_removed', inlineData, { expires: date });

			// Get the right count for us
			var count = goButton.val().replace(/[^\d]/g, '');
			inlineModeration.inlineCount = count;
			inlineModeration.inlineCount--;
		}
		else
		{
			inlineData = "|"+newIds.join("|")+"|";
			$.cookie(inlineModeration.cookieName, inlineData, { expires: date });
		}

		if(inlineModeration.inlineCount < 0)
		{
			inlineModeration.inlineCount = 0;
		}

		goButton.val(go_text+" ("+inlineModeration.inlineCount+")");

		return true;
	},

	clearChecked: function()
	{
		var selectRow = $("#selectAllrow");
		if(selectRow)
		{
			selectRow.css('display', "none");
		}
		
		var allSelectedRow = $("#allSelectedrow");
		if(allSelectedRow)
		{
			allSelectedRow.css('display', "none");
		}
		
		inputs = $("input");

		if(!inputs)
		{
			return false;
		}

		$(inputs).each(function() {
			var element = $(this);
			if(!element.val()) return;
			if(element.attr('type') == "checkbox" && ((element.attr('id') && element.attr('id').split("_")[0] == "inlinemod") || element.attr('name') == "allbox"))
			{
				element.prop('checked', false);
			}
		});

		$('.trow_selected').each(function() {
			$(this).removeClass('trow_selected');
		});

		$('fieldset.inline_selected').each(function() {
			$(this).removeClass('inline_selected');
		});

		inlineModeration.inlineCount = 0;
		goButton = $("#inline_go");
		goButton.val(go_text+" (0)");
		$.removeCookie(inlineModeration.cookieName);
		$.removeCookie(inlineModeration.cookieName + '_removed');

		return true;
	},

	checkAll: function(master)
	{
		inputs = $("input");
    master = $(master);

		if(!inputs)
		{
			return false;
		}

		inlineCookie = $.cookie(inlineModeration.cookieName);

		if(inlineCookie)
		{
			inlineIds = inlineCookie.split("|");
		}

		var newIds = new Array();
		$(inputs).each(function() {
			var element = $(this);
			if(!element.val() || !element.attr('id')) return;
			inlineCheck = element.attr('id').split("_");
			if((element.attr('name') != "allbox") && (element.attr('type') == "checkbox") && (inlineCheck[0] == "inlinemod"))
			{
				id = inlineCheck[1];
				var changed = (element.prop('checked') != master.prop('checked'));
				element.prop('checked', master.prop('checked'));

				var post = element.parents('.post_content');
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
		goButton = $("#inline_go");

		if(inlineModeration.inlineCount < 0)
		{
			inlineModeration.inlineCount = 0;
		}
		
		if(inlineModeration.inlineCount < all_text)
		{
			var selectRow = $("#selectAllrow");
			if(selectRow)
			{
				if(master.prop('checked') == true)
				{
					selectRow.css('display', "table-row");
				}
				else
				{
					selectRow.css('display', "none");
				}
			}
		}
		
		goButton.val(go_text+" ("+inlineModeration.inlineCount+")");
    
    var date = new Date();
    date.setTime(date.getTime() + (60 * 60 * 1000));
		$.cookie(inlineModeration.cookieName, inlineData, { expires: date });
	},
		
	selectAll: function()
	{
		goButton.val(go_text+" ("+all_text+")");
    var date = new Date();
    date.setTime(date.getTime() + (60 * 60 * 1000));
		$.cookie(inlineModeration.cookieName, "|ALL|", { expires: date });
		
		var selectRow = $("#selectAllrow");
		if(selectRow)
		{
			selectRow.css('display', "none");
		}
		
		var allSelectedRow = $("#allSelectedrow");
		if(allSelectedRow)
		{
			allSelectedRow.css('display', "table-row");
		}
	}
};
$(inlineModeration.init);