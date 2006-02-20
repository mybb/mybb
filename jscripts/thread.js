var Thread = {
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
		
		document.input.message.value += "[quote="+author.innerHTML+"]"+unHTMLchars(post.innerHTML)+"[/quote]\n\n";
		document.input.message.focus();
	},

	deletePost: function(pid)
	{
		confirmReturn = confirm(quickdelete_confirm);
		if(confirmReturn == true) {
			form = document.createElement("form");
			form.setAttribute("method", "post");
			form.setAttribute("action", "editpost.php?action=deletepost&delete=yes");
			form.setAttribute("style", "display: none;");

			var input = document.createElement("input");
			input.setAttribute("name", "pid");
			input.setAttribute("type", "hidden");
			input.setAttribute("value", pid);

			form.appendChild(input);
			document.getElementsByTagName("body")[0].appendChild(form);
			form.submit();
		}
	},
	
	reportPost: function(pid)
	{
		popupWin("report.php?pid="+pid, "reportPost", 400, 300)
	}
}
Event.observe(window, 'load', Thread.init);