var Thread = {
	init: function()
	{
		$(document).ready(function(){
			Thread.quickEdit();
			Thread.initQuickReply();
			Thread.initMultiQuote();
		});
	},
	
	initMultiQuote: function()
	{
		var quoted = $.cookie('multiquote');
		if(quoted)
		{
			var post_ids = quoted.split("|");
			
			$.each(post_ids, function(key, value) {
				if($("#multiquote_"+value))
				{
					$("#multiquote_"+value).parents("a:first").attr('class', 'postbit_multiquote_on');
				}
			});
			
			if($('#quickreply_multiquote'))
			{
				$('#quickreply_multiquote').show();
			}
		}
		return true;
	},
	
	multiQuote: function(pid)
	{
		var new_post_ids = new Array();
		var quoted = $.cookie("multiquote");
		var is_new = true;
		if(quoted)
		{
			var post_ids = quoted.split("|");
			
			$.each(post_ids, function(key, post_id) {
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

		if(is_new == true)
		{
			new_post_ids[new_post_ids.length] = pid;
			$("#multiquote_"+pid).parents("a:first").removeClass('postbit_multiquote');
			$("#multiquote_"+pid).parents("a:first").addClass('postbit_multiquote_on');
		}
		else
		{
			$("#multiquote_"+pid).parents("a:first").removeClass('postbit_multiquote_on');
			$("#multiquote_"+pid).parents("a:first").addClass('postbit_multiquote');
		}
		if($('#quickreply_multiquote'))
		{
			if(new_post_ids.length > 0)
			{
				$('#quickreply_multiquote').show();
			}
			else
			{
				$('#quickreply_multiquote').hide();
			}
		}
		$.cookie("multiquote", new_post_ids.join("|"));
	},
	
	loadMultiQuoted: function()
	{
		if(use_xmlhttprequest == 1)
		{
			$.ajax(
			{
				url: 'xmlhttp.php?action=get_multiquoted&load_all=1',
				type: 'get',
				complete: function (request, status)
				{
					Thread.multiQuotedLoaded(request, status);
				}
			});
			
			return false;
		}
		else
		{
			return true;
		}
	},

	multiQuotedLoaded: function(request)
	{
		var json = $.parseJSON(request.responseText);
		if(typeof json == 'object')
		{
			if(json.hasOwnProperty("errors"))
			{
				$.each(json.errors, function(i, message)
				{
					$.jGrowl(lang.error_fetching_post+' '+message);
				});
				return false;
			}
		}
	
		var id = 'message';
		if(typeof $('textarea').sceditor != 'undefined')
		{
			$('textarea').sceditor('instance').insert(json.message);
		}
		else
		{
			if($('#' + id).value)
			{
				$('#' + id).value += "\n";
			}
			$('#' + id).val($('#' + id).val() + json.message);
		}

		Thread.clearMultiQuoted();
		$('#quickreply_multiquote').hide();
		$('#quoted_ids').val('all');
		
		$('#message').focus();
	},
	
	clearMultiQuoted: function()
	{
		$('#quickreply_multiquote').hide();
		var quoted = $.cookie("multiquote");
		if(quoted)
		{
			var post_ids = quoted.split("|");
			
			$.each(post_ids, function(key, post_id) {
				if($("#multiquote_"+post_id).parents("a:first"))
				{
					$("#multiquote_"+post_id).parents("a:first").removeClass('postbit_multiquote_on');
					$("#multiquote_"+pid).parents("a:first").addClass('postbit_multiquote');
				}
			});
		}
		$.removeCookie('multiquote');
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
				submit : lang.quickedit_submit,
				cancel : lang.quickedit_cancel,
				tooltip : lang.quickedit_tooltip,
				event : "edit" + pid, // Triggered by the event "edit_[pid]",
				onblur : "ignore",
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
				//alert('clicking');
				e.stopPropagation();
				
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
		
		$.jGrowl(lang.posting, { openDuration: 'fast' });
		
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
		if(typeof json == 'object')
		{
			if(json.hasOwnProperty("errors"))
			{
				$("div.jGrowl").jGrowl("close");
				
				$.each(json.errors, function(i, message)
				{
					$.jGrowl(lang.error_posting_reply+' '+message);
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
		
		$("div.jGrowl").jGrowl("close");
	},
	
	showIgnoredPost: function(pid)
	{
		$('#ignored_post_'+pid).slideToggle("slow");
		$('#post_'+pid).slideToggle("slow");
	},
	
	deletePost: function(pid)
	{
		confirmReturn = confirm(quickdelete_confirm);
		if(confirmReturn == true)
		{
			$.ajax(
			{
				url: 'editpost.php?ajax=1&action=deletepost&delete=1&my_post_key='+my_post_key+'&pid='+pid,
				type: 'post',
				complete: function (request, status)
				{
					var json = $.parseJSON(request.responseText);
					if(json.hasOwnProperty("errors"))
					{
						$.each(json.errors, function(i, message)
						{
							$.jGrowl(lang.error_posting_reply+' '+message);
						});
					}
					else
					{
						// Do we have a "window.location" in our response message?
						// If so, we were successful
						if(request.responseText.indexOf("window.location") != -1)
						{
							$('#post_'+pid).slideToggle("slow");
							
							$.jGrowl(lang.post_deleted_successfully);
						}
						else
						{
							$.jGrowl(lang.error_unknown);
						}
					}
				}
			});
		}
	},

	reportPost: function(pid)
	{
		MyBB.popupWindow("/report.php?pid="+pid);
	},
	
	submitReport: function(pid)
	{
		// Get form, serialize it and send it
		var datastring = $(".reportPost_"+pid).serialize();
		$.ajax({
			type: "POST",
			url: "report.php",
			data: datastring,
			dataType: "html",
			success: function(data) {
				// Replace modal HTML
				$('.modal_'+pid).fadeOut('slow', function() {
					$('.modal_'+pid).html(data);
					$('.modal_'+pid).fadeIn('slow');
				});
			},
			error: function(){
				  alert(lang.error_unknown);
			}
		});
		
		return false;
	},
};

Thread.init();