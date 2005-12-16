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
	},
	
	deletePost: function(pid)
	{
		confirmReturn = confirm(quickdelete_confirm);
		if(confirmReturn == true) {
			window.location = "editpost.php?action=deletepost&pid="+pid+"&delete=yes";
		}
	},
	
	reportPost: function(pid)
	{
		popupWin("report.php?pid="+pid, "reportPost", 400, 300)
	}
}