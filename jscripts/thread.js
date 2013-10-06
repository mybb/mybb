var Thread = {
	init: function()
	{
		$(document).ready(function(){
			Thread.quickEdit();
			Thread.initQuickReply();
		});
	},
	
	quickEdit: function()
	{
		$('.post_body').each(function() {
		
			// Take pid out of the id attribute
			id = $(this).attr('id');
			pid = id.replace( /[^\d.]/g, '');

			$('#pid_' + pid).editable("xmlhttp.php?action=edit_post&do=update_post&pid=" + pid + '&my_post_key=' + my_post_key, {
				indicator : "<img src='images/spinner.gif'>",
				loadurl : "xmlhttp.php?action=edit_post&do=get_post&pid=" + pid,
				type : "textarea",
				submit : "OK",
				cancel : "Cancel",
				tooltip : "Click to edit...",
				event : "edit" + pid, // Triggered by the event "edit_[pid]"
				callback : function(values, settings) {
					values = JSON.parse(values);
					
					// Change html content
					$('#pid_' + pid).html(values.message);
					$('#edited_by_' + pid).html(values.editedmsg);
				}
			});
        });
		
		$('.quick_edit_button').each(function() {
			$(this).bind("click", function(e) {
				//e.stopPropagation();
				
				// Take pid out of the id attribute
				id = $(this).attr('id');
				pid = id.replace( /[^\d.]/g, '');
				
				// Force popup menu closure
				$('#edit_post_' + pid + '_popup').trigger('close_popup');
			
				// Trigger the edit event
				$('#pid_' + pid).trigger("edit" + pid);
			});
        });

		return false;
	},
	
	initQuickReply: function()
	{
		if($('#quick_reply_form') && use_xmlhttprequest == 1)
		{
			// Bind closing event to our popup menu
			$('#quick_reply_submit').bind('click', function(e) {
				return Thread.quickReply(e);
			});
		}
	},
	
	quickReply: function(e)
	{		
		e.stopPropagation();

		if(this.quick_replying)
		{
			return false;
		}

		this.quick_replying = 1;
		var post_body = $('#quick_reply_form').serialize();
		//this.spinner = new ActivityIndicator("body", {image: imagepath + "/spinner_big.gif"});
		
		$.ajax(
		{
			url: 'newreply.php?ajax=1',
			type: 'post',
			data: post_body,
			dataType: 'html',
        	complete: function (request, status)
        	{
		  		Thread.quickReplyDone(request, status);
          	}
		});
		
		return false;
	},
	
	quickReplyDone: function(request, status)
	{
		this.quick_replying = 0;
		
		var json = $.parseJSON(request.responseText);
		if(typeof response == 'object')
		{
			if(json.hasOwnProperty("errors"))
			{
				$.each(json.errors, function(i, message)
				{
				  $.jGrowl('There was an error posting your reply: '+message);
				});
				return false;
			}
		}
		
		if($('#captcha_trow'))
		{
			captcha = json.data.match(/^<captcha>([0-9a-zA-Z]+)(\|([0-9a-zA-Z]+)|)<\/captcha>/);
			if(captcha)
			{
				json.data = json.data.replace(/^<captcha>(.*)<\/captcha>/, '');

				if(captcha[1] == "reload")
				{
					Recaptcha.reload();
				}
				else if($("#captcha_img"))
				{
					if(captcha[1])
					{
						imghash = captcha[1];
						$('#imagehash').value = imghash;
						if(captcha[3])
						{
							$('#imagestring').type = "hidden";
							$('#imagestring').value = captcha[3];
							// hide the captcha
							$('#captcha_trow').style.display = "none";
						}
						else
						{
							$('#captcha_img').src = "captcha.php?action=regimage&imagehash="+imghash;
							$('#imagestring').type = "text";
							$('#imagestring').value = "";
							$('#captcha_trow').style.display = "";
						}
					}
				}
			}
		}
		
		if(json.data.match(/id="post_([0-9]+)"/))
		{
			var pid = json.data.match(/id="post_([0-9]+)"/)[1];
			var post = document.createElement("div");
			$('#posts').append(json.data);
			
			/*if(MyBB.browser == "ie" || MyBB.browser == "opera" || MyBB.browser == "safari" || MyBB.browser == "chrome")
			{*/
				// Eval javascript
				$(json.data).filter("script").each(function(e) { 
					eval($(this).text());
				});
			//}
			
			$('#quick_reply_form')[0].reset();
			
			if($('#lastpid'))
			{
				$('#lastpid').val(pid);
			}
		}
		else
		{
			// Eval javascript
			$(json.data).filter("script").each(function(e) { 
				eval($(this).text());
			});
		}
		
		/*if(this.spinner)
		{
			this.spinner.destroy();
			this.spinner = '';
		}*/
	},
	
	showIgnoredPost: function(pid)
	{
		$('#ignored_post_'+pid).slideToggle("slow");
		$('#post_'+pid).slideToggle("slow");
	},
	
	/*deletePost: function(pid)
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
	},*/

	reportPost: function(pid)
	{
		MyBB.popupWindow("/report.php?pid="+pid);
	},
};

Thread.init();