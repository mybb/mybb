var Post = {
	init: function()
	{
	},

	loadMultiQuoted: function()
	{
		if(use_xmlhttprequest == 1)
		{
			tid = document.input.tid.value;
			this.spinner = new ActivityIndicator("body", {image: imagepath + "/spinner_big.gif"});
			new Ajax.Request('xmlhttp.php?action=get_multiquoted&tid='+tid, {method: 'get', onComplete: function(request) { Post.multiQuotedLoaded(request); }});
			return false;
		}
		else
		{
			return true;
		}
	},

	loadMultiQuotedAll: function()
	{
		if(use_xmlhttprequest == 1)
		{
			this.spinner = new ActivityIndicator("body", {image: imagepath + "/spinner_big.gif"});
			new Ajax.Request('xmlhttp.php?action=get_multiquoted&load_all=1', {method: 'get', onComplete: function(request) { Post.multiQuotedLoaded(request); }});
			return false;
		}
		else
		{
			return true;
		}
	},

	multiQuotedLoaded: function(request)
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
		}
		else if(request.responseText)
		{
			var id = 'message';
			if(typeof clickableEditor != 'undefined')
			{
				id = clickableEditor.textarea;
			}
			if($(id).value)
			{
				$(id).value += "\n";
			}
			$(id).value += request.responseText;
		}
		$('multiquote_unloaded').hide();
		document.input.quoted_ids.value = 'all';
		if(this.spinner)
		{
			this.spinner.destroy();
			this.spinner = '';
		}
	},

	clearMultiQuoted: function()
	{
		$('multiquote_unloaded').hide();
		Cookie.unset('multiquote');
	},

	removeAttachment: function(aid)
	{
		if(confirm(removeattach_confirm) == true)
		{
			document.input.attachmentaid.value = aid;
			document.input.attachmentact.value = "remove";
		}
		else
		{
			document.input.attachmentaid.value = 0;
			document.input.attachmentact.value = "";
			return false;
		}
	},

	attachmentAction: function(aid,action)
	{
		document.input.attachmentaid.value = aid;
		document.input.attachmentact.value = action;
	}
};
Event.observe(document, 'dom:loaded', Post.init);