var Post = {
	init: function()
	{
	},
	
	quickQuote: function(pid)
	{
		post = $("qq"+pid);
		author = $("qqauthor"+pid);
		
		if(!post)
		{
			return false;
		}
		
		document.input.message.value += "[quote="+author.innerHTML+"]"+MyBB.unHTMLchars(post.innerHTML)+"[/quote]\n\n";
		document.input.message.focus();
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