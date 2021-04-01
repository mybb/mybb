var UserCP = {
	init: function()
	{
		$(function()
		{
			$(document).on('keydown', function(e)
			{ 
				if (e.keyCode == 27 && $('#buddyselect_container').is(':visible'))
				{ 
					$('#buddyselect_container').hide();
				}
			});
		});
	},

	regenBuddySelected: function()
	{
		var selectedBuddies = [];
		$('input[id^=checkbox_]').each(function()
		{
			if($(this).is(':checked'))
			{
				selectedBuddies.push($(this).parent().text().trim());
			}
		})
		$("#buddyselect_buddies").text(selectedBuddies.join(', '));
	},

	openBuddySelect: function(field)
	{
		if(!$("#"+field).length)
		{
			return false;
		}
		this.buddy_field = '#'+field;
		if($("#buddyselect_container").length)
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
				var json = JSON.parse(request.responseText);
				if(json.hasOwnProperty("errors"))
				{
					$.each(json.errors, function(i, message)
					{
					  $.jGrowl(lang.buddylist_error + message, {theme:'jgrowl_error'});
					});
					return false;
				}
			} catch (e) {
				if(request.responseText)
				{
					if(buddyselect_container.length)
					{
						buddyselect_container.remove();
					}
					var container = $("<div />");
					container.attr("id", "buddyselect_container").html(request.responseText).hide();
					$("body").append(container);
				}
			}
		}

		// Center it on the page (this should be in usercp.css)
		$("#buddyselect_container").css({"top": "50%", "left": "50%", "position": "fixed", "display": "block", "z-index": "1000", "text-align": "left", "transform": "translate(-50%, -50%)"});
		
		// Reset all checkboxes initially
		$('input[id^=checkbox_]').prop('checked', false);

		var listedBuddies = $(this.buddy_field).select2("data");
		$.each(listedBuddies, function()
		{
			var username = this.text;			
			$('input[id^=checkbox_]').each(function()
			{
				if($(this).parent().text().trim() == username)
				{
					$(this).prop('checked', true);
				}
			});
		});

		UserCP.regenBuddySelected();
	},

	// Deprecated function since MyBB 1.8.27
	selectBuddy: function(uid, username)
	{
		UserCP.regenBuddySelected();
	},

	closeBuddySelect: function(canceled)
	{
		if(canceled != true)
		{
			var buddies = $("#buddyselect_buddies").text().split(","), newbuddies = [];
			$.each(buddies, function(index, buddy)
			{
				buddy = buddy.trim();
				if(buddy !== "")
				{
					newbuddies.push({ id: buddy, text: buddy });
				}
			});
			$(this.buddy_field).select2("data", newbuddies).select2("focus");
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
			async: true,
	        complete: function (request)
	        {
				if(request.responseText.indexOf("buddy_count") >= 0 || request.responseText.indexOf("ignored_count") >= 0)
				{
					 $("#"+list+"_list").html(request.responseText);
				}
				else
				{
					$("#sentrequests").html(request.responseText);
				}
				
		        type_submit.prop("disabled", false);
		        type_add_username.prop("disabled", false);
		        type_submit.attr("value", old_value);
		        type_add_username.val("");
		        type_add_username.trigger('focus');
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

		MyBB.prompt(message, {
			buttons:[
					{title: yes_confirm, value: true},
					{title: no_confirm, value: false}
			],
			submit: function(e,v,m,f){
				if(v == true)
				{
					$.ajax(
					{
						type: 'post',
						url: 'usercp.php?action=do_editlists&my_post_key='+my_post_key+'&manage='+type+'&delete='+uid,
						data: { ajax: 1 },
						async: true
					});
				}
			}
		});

		return false;
	}
};

UserCP.init();