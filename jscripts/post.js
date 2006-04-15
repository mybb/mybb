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
			document.input.removeattachment.value = aid;
		}
		else
		{
			document.input.removeattachment.value = 0;
			return false;
		}
	}
}
Event.observe(window, 'load', Post.init);