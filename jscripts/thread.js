var Thread = {
	init: function()
	{
		Thread.qeCache = new Array();
		Thread.initMultiQuote();
	},
	
	initMultiQuote: function()
	{
		var quoted = Cookie.get("multiquote");
		if(quoted)
		{
			var post_ids = quoted.split("|");
			for(var i=0; i < post_ids.length; i++)
			{
				if(post_ids[i] != '')
				{
					if($("multiquote_"+post_ids[i]))
					{
						element = $("multiquote_"+post_ids[i]);
						element.src = element.src.replace("postbit_multiquote.gif", "postbit_multiquote_on.gif");
					}
				}
			}
		}
		return true;
	},
	
	multiQuote: function(pid)
	{
		var new_post_ids = new Array();
		var quoted = Cookie.get("multiquote");
		var is_new = true;
		if(quoted)
		{
			var post_ids = quoted.split("|");
			for(var i = 0; i < post_ids.length; i++)
			{
				if(post_ids[i] != pid && post_ids[pid] != '')
				{
					new_post_ids[new_post_ids.length] = post_ids[i];
				}
				else if(post_ids[i] == pid)
				{
					is_new = false;
				}
			}
		}
		element = $("multiquote_"+pid);
		if(is_new == true)
		{
			element.src = element.src.replace("postbit_multiquote.gif", "postbit_multiquote_on.gif");
			new_post_ids[new_post_ids.length] = pid;
		}
		else
		{
			element.src = element.src.replace("postbit_multiquote_on.gif", "postbit_multiquote.gif");
		}
		Cookie.set("multiquote", new_post_ids.join("|"));
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
		MyBB.popupWindow("report.php?pid="+pid, "reportPost", 400, 300)
	},
	
	quickEdit: function(pid)
	{
		if(!$("pid_"+pid))
		{
			return false;
		}
		if(Thread.qeCache[pid])
		{
			return false;
		}
		Thread.qeCache[pid] = $("pid_"+pid).innerHTML;
		this.spinner = new ActivityIndicator("body", {image: "images/spinner_big.gif"});
		new ajax('xmlhttp.php?action=edit_post&do=get_post&pid='+pid, {method: 'get', onComplete: function(request) { Thread.quickEditLoaded(request, pid); }});
		return false;
	},
	
	quickEditLoaded: function(request, pid)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/))
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);
			if(!message[1])
			{
				message[1] = "An unknown error occurred.";
			}
			alert('There was an error performing the update.\n\n'+message[1]);
		}
		else if(request.responseText)
		{
			$("pid_"+pid).innerHTML = request.responseText;
		}
		element = $("quickedit_"+pid);
		element.focus();
		offsetTop = -60;
		do
		{
			offsetTop += element.offsetTop || 0;
			element = element.offsetParent;
		} while(element);
		
		scrollTo(0, offsetTop);
		
		this.spinner.destroy();	
		this.spinner = '';	
	},
	
	quickEditSave: function(pid)
	{
		message = $("quickedit_"+pid).value;
		if(message == "")
		{
			return false;
		}
		this.spinner = new ActivityIndicator("body", {image: "images/spinner_big.gif"});
		
		postData = "value="+escape(message).replace(/\+/g, "%2B");
		postData += "&pid="+pid;
alert(postData);
		new ajax('xmlhttp.php?action=edit_post&do=update_post', {method: 'post', postBody: postData, onComplete: function(request) { Thread.quickEditSaved(request, pid); }});		
	},
	
	quickEditCancel: function(pid)
	{
		$("pid_"+pid).innerHTML = Thread.qeCache[pid];
		Thread.qeCache[pid] = "";
		if(this.spinner)
		{
			this.spinner.destroy();
			this.spinner = '';
		}
	},
	
	quickEditSaved: function(request, pid)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/))
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);
			if(!message[1])
			{
				message[1] = "An unknown error occurred.";
			}
			alert('There was an error performing the update.\n\n'+message[1]);
		}
		else if(request.responseText)
		{
			$("pid_"+pid).innerHTML = request.responseText;
		}
		Thread.qeCache[pid] = "";
		this.spinner.destroy();
		this.spinner = '';
	}
}
Event.observe(window, 'load', Thread.init);