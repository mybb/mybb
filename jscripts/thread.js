var Thread = {
	quickQuote: function(pid)
	{
		post = document.getElementById("qq"+pid);
		author = document.getElementById("qqauthor"+pid);
		
		if(!post)
		{
			return false;
		}
		
		document.input.message.value += "[quote="+author.innerHTML+"]"+unHTMLchars(post.innerHTML)+"[/quote]\n\n";
		document.input.message.focus();
	}
}