var Post = {
	init: function()
	{
	},
	
	loadMultiQuoted: function()
	{
		tid = document.input.tid.value;
		this.spinner = new ActivityIndicator("body", {image: "images/spinner_big.gif"});
		new ajax('xmlhttp.php?action=get_multiquoted&tid='+tid, {method: 'get', onComplete: function(request) {Post.multiQuotedLoaded(request); }});		
	},
	
	loadMultiQuotedAll: function()
	{
		this.spinner = new ActivityIndicator("body", {image: "images/spinner_big.gif"});
		new ajax('xmlhttp.php?action=get_multiquoted&load_all=1', {method: 'get', onComplete: function(request) {Post.multiQuotedLoaded(request); }});		
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
			alert('There was an error fetching the posts.\n\n'+message[1]);
		}
		else if(request.responseText)
		{
			if($('message').value)
			{
				$('message').value += "\n";
			}
			$('message').value += request.responseText;
		}
		$('multiquote_unloaded').style.display = 'none';
		document.input.quoted_ids.value = 'all';
		this.spinner.destroy();	
		this.spinner = '';	
	},
	
	clearMultiQuoted: function()
	{
		$('multiquote_unloaded').style.display = 'none';
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
}
Event.observe(window, 'load', Post.init);