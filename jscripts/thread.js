var Thread = {
	init: function()
	{
		Thread.qeCache = new Array();
		Thread.initMultiQuote();
		Thread.initQuickReply();
	},

	initMultiQuote: function()
	{
		var quoted = Cookie.get("multiquote");
		if(quoted)
		{
			var post_ids = quoted.split("|");
			post_ids.each(function(post_id) {
				if($("multiquote_"+post_id))
				{
					element = $("multiquote_"+post_id);
					element.src = element.src.replace("postbit_multiquote.gif", "postbit_multiquote_on.gif");
				}
			});
			if($('quickreply_multiquote'))
			{
				$('quickreply_multiquote').show();
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
			post_ids.each(function(post_id) {
				if(post_id != pid && post_id != '')
				{
					new_post_ids[new_post_ids.length] = post_id;
				}
				else if(post_id == pid)
				{
					is_new = false;
				}
			});
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
		if($('quickreply_multiquote'))
		{
			if(new_post_ids.length > 0)
			{
				$('quickreply_multiquote').show();
			}
			else
			{
				$('quickreply_multiquote').hide();
			}
		}
		Cookie.set("multiquote", new_post_ids.join("|"));
	},

	loadMultiQuoted: function()
	{
		if(use_xmlhttprequest == 1)
		{
			this.spinner = new ActivityIndicator("body", {image: imagepath + "/spinner_big.gif"});
			new Ajax.Request('xmlhttp.php?action=get_multiquoted&load_all=1', {method: 'get', onComplete: function(request) {Thread.multiQuotedLoaded(request); }});
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
		Thread.clearMultiQuoted();
		$('quickreply_multiquote').hide();
		$('quoted_ids').value = 'all';
		if(this.spinner)
		{
			this.spinner.destroy();
			this.spinner = '';
		}
		$('message').focus();	
	},

	clearMultiQuoted: function()
	{
		$('quickreply_multiquote').hide();
		var quoted = Cookie.get("multiquote");
		if(quoted)
		{
			var post_ids = quoted.split("|");
			post_ids.each(function(post_id) {
				if($("multiquote_"+post_id))
				{
					element = $("multiquote_"+post_id);
					element.src = element.src.replace("postbit_multiquote_on.gif", "postbit_multiquote.gif");
				}
			});
		}
		Cookie.unset('multiquote');
	},	

	deletePost: function(pid)
	{
		confirmReturn = confirm(quickdelete_confirm);
		if(confirmReturn == true)
		{
			var form = new Element("form", { method: "post", action: "editpost.php?action=deletepost&delete=1", style: "display: none;" });

			if(my_post_key)
			{
				form.insert({ bottom: new Element("input",
					{
						name: "my_post_key",
						type: "hidden",
						value: my_post_key
					})
				});
			}

			form.insert({ bottom: new Element("input",
				{
					name: "pid",
					type: "hidden",
					value: pid
				})
			});

			$$("body")[0].insert({ bottom: form });
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
		this.spinner = new ActivityIndicator("body", {image: imagepath + "/spinner_big.gif"});
		new Ajax.Request('xmlhttp.php?action=edit_post&do=get_post&pid='+pid, {method: 'get', onComplete: function(request) { Thread.quickEditLoaded(request, pid); }});
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
			if(this.spinner)
			{
				this.spinner.destroy();
				this.spinner = '';
			}
			alert('There was an error performing the update.\n\n'+message[1]);
			Thread.qeCache[pid] = "";
		}
		else if(request.responseText)
		{
			$("pid_"+pid).innerHTML = request.responseText;
			element = $("quickedit_"+pid);
			element.focus();
			offsetTop = -60;
			do
			{
				offsetTop += element.offsetTop || 0;
				element = element.offsetParent;
			}
			while(element);

			scrollTo(0, offsetTop);
		}
		if(this.spinner)
		{
			this.spinner.destroy();
			this.spinner = '';
		}
	},

	quickEditSave: function(pid)
	{
		message = $("quickedit_"+pid).value;
		if(message == "")
		{
			return false;
		}
		this.spinner = new ActivityIndicator("body", {image: imagepath + "/spinner_big.gif"});
		
		postData = "value="+encodeURIComponent(message).replace(/\+/g, "%2B");
		new Ajax.Request('xmlhttp.php?action=edit_post&do=update_post&pid='+pid+"&my_post_key="+my_post_key, {method: 'post', postBody: postData, onComplete: function(request) { Thread.quickEditSaved(request, pid); }});
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
			if(this.spinner)
			{
				this.spinner.destroy();
				this.spinner = '';
			}
			alert('There was an error performing the update.\n\n'+message[1]);
		}
		else if(request.responseText)
		{
			var message = request.responseText;
			var edited_regex = new RegExp("<editedmsg>(.*)</editedmsg>", "m");
			if(request.responseText.match(edited_regex))
			{
				var edited_message = request.responseText.match(edited_regex)[1];
				if($('edited_by_'+pid))
				{
					$('edited_by_'+pid).innerHTML = edited_message;
				}
				message = message.replace(edited_regex, '')
			}
			$("pid_"+pid).innerHTML = message;
			Thread.qeCache[pid] = "";
		}
		
		if(this.spinner)
		{
			this.spinner.destroy();
			this.spinner = '';
		}
	},

	initQuickReply: function()
	{
		if($('quick_reply_form') && use_xmlhttprequest == 1)
		{
			Event.observe($('quick_reply_submit'), "click", Thread.quickReply.bindAsEventListener(this));
		}
	},

	quickReply: function(e)
	{
		Event.stop(e);

		if(this.quick_replying)
		{
			return false;
		}

		this.quick_replying = 1;
		var post_body = Form.serialize('quick_reply_form');
		this.spinner = new ActivityIndicator("body", {image: imagepath + "/spinner_big.gif"});
		new Ajax.Request('newreply.php?ajax=1', {method: 'post', postBody: post_body, onComplete: function(request) { Thread.quickReplyDone(request); }});
		return false;
	},

	quickReplyDone: function(request)
	{
		if($('captcha_trow'))
		{
			captcha = request.responseText.match(/^<captcha>([0-9a-zA-Z]+)(\|([0-9a-zA-Z]+)|)<\/captcha>/);
			if(captcha)
			{
				request.responseText = request.responseText.replace(/^<captcha>(.*)<\/captcha>/, '');

				if(captcha[1] == "reload")
				{
					Recaptcha.reload();
				}
				else if($("captcha_img"))
				{
					if(captcha[1])
					{
						imghash = captcha[1];
						$('imagehash').value = imghash;
						if(captcha[3])
						{
							$('imagestring').type = "hidden";
							$('imagestring').value = captcha[3];
							// hide the captcha
							$('captcha_trow').style.display = "none";
						}
						else
						{
							$('captcha_img').src = "captcha.php?action=regimage&imagehash="+imghash;
							$('imagestring').type = "text";
							$('imagestring').value = "";
							$('captcha_trow').style.display = "";
						}
					}
				}
			}
		}
		if(request.responseText.match(/<error>([^<]*)<\/error>/))
		{
			message = request.responseText.match(/<error>([^<]*)<\/error>/);

			if(!message[1])
			{
				message[1] = "An unknown error occurred.";
			}

			if(this.spinner)
			{
				this.spinner.destroy();
				this.spinner = '';
			}
			alert('There was an error posting your reply:\n\n'+message[1]);
		}
		else if(request.responseText.match(/id="post_([0-9]+)"/))
		{
			var pid = request.responseText.match(/id="post_([0-9]+)"/)[1];
			var post = document.createElement("div");
			post.innerHTML = request.responseText;
			$('posts').appendChild(post);
			if(MyBB.browser == "ie" || MyBB.browser == "opera" || MyBB.browser == "safari" || MyBB.browser == "chrome")
			{
				var scripts = request.responseText.extractScripts();
				scripts.each(function(script)
				{
					eval(script);
				});
			}
			Form.reset('quick_reply_form');
			if($('lastpid'))
			{
				$('lastpid').value = pid;
			}
		}
		else
		{
			request.responseText.evalScripts(); 
		}
		
		if(this.spinner)
		{
			this.spinner.destroy();
			this.spinner = '';
		}
		this.quick_replying = 0;
	},

	showIgnoredPost: function(pid)
	{
		$('ignored_post_'+pid).hide();
		$('post_'+pid).show();
	}
};
Event.observe(document, 'dom:loaded', Thread.init);