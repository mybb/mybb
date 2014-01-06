var UserCP = {
	init: function()
	{
	},

	openBuddySelect: function(field)
	{
		if(!$("#"+field))
		{
			return false;
		}
		this.buddy_field = '#'+field;
		if($("#buddyselect_container").length > 0)
		{
			UserCP.buddySelectLoaded();
			return false;
		}
		if(use_xmlhttprequest == 1)
		{
			$.ajax(
			{
				url: 'xmlhttp.php?action=get_buddyselect',
				async: true,
	            complete: function (request)
	            {
	                UserCP.buddySelectLoaded(request);
	            }
			});
		}
	},

	buddySelectLoaded: function(request)
	{
		var buddyselect_container = $("#buddyselect_container");
		// Using new copy
		if(request)
		{
			try {
				var json = $.parseJSON(request.responseText);
				if(json.hasOwnProperty("errors"))
				{
					$.each(json.errors, function(i, message)
					{
					  $.jGrowl('There was an error fetching the buddy list. '+message);
					});
					return false;
				}
			} catch (e) {
				if(request.responseText)
				{
					if(buddyselect_container.length > 0)
					{
						buddyselect_container.remove();
					}
					var container = $("<div />");
					container.attr("id", "buddyselect_container");
					container.css("display", "none");
					container.html(request.responseText);
					$("body").append(container);
				}
			}
		}
		else
		{
			buddyselect_container.hide();
			$("#buddyselect_container input:checked").each(function()
			{
				$(this).attr("checked", false);
			});
			$("#buddyselect_buddies").html("");
			container = buddyselect_container;
		}

		// Clone off screen
		var clone = container.clone(true);
		$("body").append(clone);
		clone.css("width", "300px")
			 .css("top", "-10000px")
		     .css("display", "block")
		     .remove();

		// Center it on the page
		$("#buddyselect_container").css("top", "50%")
		                           .css("left", "50%")
		                           .css("position", "fixed")
		                           .css("display", "block")
		                           .css("z-index", "1000")
		                           .css("text-align", "left")
                                   .css("margin-left", -$("#buddyselect_container").outerWidth() / 2 + 'px')
                                   .css("margin-top", -$("#buddyselect_container").outerHeight() / 2 + 'px');
	},

	selectBuddy: function(uid, username)
	{
		var checkbox = $("#checkbox_"+uid);
		var buddyselect_buddies_uid = $("#buddyselect_buddies_"+uid);
		var buddyselect_buddies = $("#buddyselect_buddies");
		// Buddy already in list - remove
		if(buddyselect_buddies_uid.length > 0)
		{
			buddyselect_buddies_uid.remove();
			var buddies = buddyselect_buddies.text();
			if(buddies.charAt(0) == ",")
			{
				first_buddy = buddyselect_buddies.children()[0];
				first_buddy.innerHTML = first_buddy.innerHTML.substr(1, first_buddy.innerHTML.length);
			}
		}
		// Add buddy to list
		else
		{
			var buddies = buddyselect_buddies.text();
			if(buddies != "")
			{
				username = ", "+username;
			}
			var buddy = $("<span />");
			buddy.attr("id", "buddyselect_buddies_"+uid)
			     .html(username);
			buddyselect_buddies.append(buddy);
		}
	},

	closeBuddySelect: function(canceled)
	{
		if(canceled != true)
		{
			var buddies = $("#buddyselect_buddies").text();
			existing_buddies = $(this.buddy_field).val();
			if(existing_buddies != "" && existing_buddies != buddies)
			{
				existing_buddies = existing_buddies.replace(/^\s+|\s+$/g, "");
				existing_buddies = existing_buddies.replace(/,\s?/g, ",");
				exp_buddies = buddies.split(",");
				$.each(exp_buddies, function(i, buddy)
				{
					buddy = buddy.replace(/^\s+|\s+$/g, "");
					if((","+existing_buddies+",").toLowerCase().indexOf(","+buddy.toLowerCase()+",") == -1)
					{
						if(existing_buddies)
						{
							existing_buddies += ",";
						}
						existing_buddies += buddy;
					}
				});
				$(this.buddy_field).text(existing_buddies.replace(/,\s?/g, ", "));
			}
			else
			{
				$(this.buddy_field).text(buddies);
			}
			$(this.buddy_field).focus();
		}
		$("#buddyselect_container").hide();
	},

	addBuddy: function(type)
	{
		var type_submit = $("#"+type+"_submit");
		var type_add_username = $("#"+type+"_add_username");

		if(type_add_username.val().length == 0)
		{
			return false;
		}
		if(use_xmlhttprequest != 1)
		{
			return true;
		}

		var old_value = type_submit.val();

		type_add_username.attr("disabled", true);
		type_submit.attr("disabled", true);

		if(type == "ignored")
		{
			type_submit.attr("value", lang.adding_ignored);
			var list = "ignore";
		}
		else
		{
			type_submit.attr("value", lang.adding_buddy);
			var list = "buddy";
		}

		$.ajax(
		{
			type: 'post',
			url: 'usercp.php?action=do_editlists&my_post_key='+my_post_key+'&manage='+type,
			data: { ajax: 1, add_username: type_add_username.val() },
			async: false,
	        complete: function (request)
	        {
		        $("#"+list+"_list").html(request.responseText);
		        type_submit.removeAttr("disabled");
		        type_add_username.removeAttr("disabled");
		        type_submit.attr("value", old_value);
		        type_add_username.val("");
		        type_add_username.focus();
				type_add_username.select2('data', null);
	        }
		});

		return false;
	},

	removeBuddy: function(type, uid)
	{
		if(type == "ignored")
		{
			var message = lang.remove_ignored;
		}
		else
		{
			var message = lang.remove_buddy;
		}

		if(confirm(message))
		{
			if(use_xmlhttprequest != 1)
			{
				return true;
			}
			$.ajax(
			{
				type: 'post',
				url: 'usercp.php?action=do_editlists&my_post_key='+my_post_key+'&manage='+type+'&delete='+uid,
				data: { ajax: 1 },
				async: false
			});
		}

		return false;
	}
};
