var UserCP = {
	init: function()
	{
	},

	openBuddySelect: function(field)
	{
		if(!$(field))
		{
			return false;
		}
		this.buddy_field = field;
		if($('buddyselect_container'))
		{
			UserCP.buddySelectLoaded();
			return false;
		}
		if(use_xmlhttprequest == 1)
		{
			this.spinner = new ActivityIndicator("body", {image: "images/spinner_big.gif"});
			new Ajax.Request('xmlhttp.php?action=get_buddyselect', {method: 'get', onComplete: function(request) { UserCP.buddySelectLoaded(request); }});
		}
	},

	buddySelectLoaded: function(request)
	{
		// Using new copy
		if(request)
		{
			if(request.responseText.match(/<error>(.*)<\/error>/))
			{
				message = request.responseText.match(/<error>(.*)<\/error>/);
				if(!message[1])
				{
					message[1] = "An unknown error occurred.";
				}
				if(this.spinner)
				{
					this.spinner.destroy();
					this.spinner = '';
				}
				alert('There was an error fetching the posts.\n\n'+message[1]);
				return false;
			}
			else if(request.responseText)
			{
				if($('buddyselect_container'))
				{
					Element.remove('buddyselect_container');
				}
				var container = document.createElement('DIV');
				container.id = "buddyselect_container";
				container.style.display = 'none';
				container.innerHTML = request.responseText;
				document.body.appendChild(container);
			}
		}
		else
		{
			Element.hide('buddyselect_container');
			var checkboxes = $('buddyselect_container').getElementsByTagName("input");
			$A(checkboxes).each(function(item) {
				item.checked = false;
			});
			$('buddyselect_buddies').innerHTML = '';
			container = $('buddyselect_container');
		}

		// Clone off screen
		var clone = container.cloneNode(true);
		document.body.appendChild(clone);
		clone.style.width = '300px';
		clone.style.top = "-10000px";
		clone.style.display = "block";
		offsetHeight = clone.offsetHeight;
		offsetWidth = clone.offsetWidth;
		Element.remove(clone);

		// Center it on the page
		arrayPageSize = DomLib.getPageSize();
		arrayPageScroll = DomLib.getPageScroll();
		var top = arrayPageScroll[1] + ((arrayPageSize[3] - 35 - offsetHeight) / 2);
		var left = ((arrayPageSize[0] - 20 - offsetWidth) / 2);
		$('buddyselect_container').style.top = top+"px";
		$('buddyselect_container').style.left = left+"px";
		$('buddyselect_container').style.position = "absolute";
		$('buddyselect_container').style.display = "block";
		$('buddyselect_container').style.zIndex = '1000';
		$('buddyselect_container').style.textAlign = 'left';
		if(this.spinner)
		{
			this.spinner.destroy();
			this.spinner = '';
		}
	},

	selectBuddy: function(uid, username)
	{
		var checkbox = $('checkbox_'+uid);
		// Buddy already in list - remove
		if($('buddyselect_buddies_'+uid))
		{
			Element.remove('buddyselect_buddies_'+uid);
			var buddies = $('buddyselect_buddies').innerHTML.stripTags();
			if(buddies.charAt(0) == ",")
			{
				first_buddy = $('buddyselect_buddies').childNodes[0];
				first_buddy.innerHTML = first_buddy.innerHTML.substr(1, first_buddy.innerHTML.length);
			}
		}
		// Add buddy to list
		else
		{
			var buddies = $('buddyselect_buddies').innerHTML.stripTags();
			if(buddies != "")
			{
				username = ", "+username;
			}
			var buddy = document.createElement('span');
			buddy.id = "buddyselect_buddies_"+uid;
			buddy.innerHTML = username;
			$('buddyselect_buddies').appendChild(buddy);
		}
	},

	closeBuddySelect: function(canceled)
	{
		if(canceled != true)
		{
			var buddies = $('buddyselect_buddies').innerHTML.stripTags();
			existing_buddies = $(this.buddy_field).value;
			if(existing_buddies != "")
			{
				existing_buddies = existing_buddies.replace(/^\s+|\s+$/g, "");
				existing_buddies = existing_buddies.replace(/,\s?/g, ",");
				exp_buddies = buddies.split(",");
				exp_buddies.each(function(buddy, i)
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
				$(this.buddy_field).value = existing_buddies.replace(/,\s?/g, ", ");
			}
			else
			{
				$(this.buddy_field).value = buddies;
			}
			$(this.buddy_field).focus();
		}
		$('buddyselect_container').hide();
	},

	addBuddy: function(type)
	{
		if(!$(type+'_add_username').value)
		{
			return false;
		}
		if(use_xmlhttprequest != 1)
		{
			return true;
		}

		var old_value = $(type+'_submit').value;

		if(type == "ignored")
		{
			$(type+'_submit').value = lang.adding_ignored;
			var list = 'ignore';
		}
		else
		{
			$(type+'_submit').value = lang.adding_buddy;
			var list = 'buddy';
		}

		new Ajax.Updater(list+'_list', 'usercp.php?action=do_editlists&my_post_key='+my_post_key+'&manage='+type, {method: 'post', postBody: 'ajax=1&add_username='+encodeURIComponent($(type+'_add_username').value), evalScripts: true, onComplete: function() { $(type+'_submit').value = old_value; $(type+'_submit').disabled = false; $(type+'_add_username').disabled = false; $(type+'_add_username').value = ''; $(type+'_add_username').focus(); }});
		$(type+'_add_username').disabled = true;
		$(type+'_submit').disabled = true;
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
			new Ajax.Request('usercp.php?action=do_editlists&my_post_key='+my_post_key+'&manage='+type+'&delete='+uid, {method: 'post', postBody: 'ajax=1'});
		}

		return false;
	}
};
