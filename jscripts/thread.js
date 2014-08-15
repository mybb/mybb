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
		var quoted = Cookie.get('multiquote');
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
		var quoted = Cookie.get("multiquote");
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
		Cookie.set("multiquote", new_post_ids.join("|"));
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
					$.jGrowl(lang.post_fetch_error + ' ' + message);
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
		var quoted = Cookie.get("multiquote");
		if(quoted)
		{
			var post_ids = quoted.split("|");

			$.each(post_ids, function(key, post_id) {
				if($("#multiquote_"+post_id).parents("a:first"))
				{
					$("#multiquote_"+post_id).parents("a:first").removeClass('postbit_multiquote_on');
					$("#multiquote_"+post_id).parents("a:first").addClass('postbit_multiquote');
				}
			});
		}
		Cookie.unset('multiquote');
	},

	quickEdit: function(el)
	{
		if(!el) el = '.post_body';

		$(el).each(function()
		{
			// Take pid out of the id attribute
			id = $(this).attr('id');
			pid = id.replace( /[^\d.]/g, '');

			$('#pid_' + pid).editable("xmlhttp.php?action=edit_post&do=update_post&pid=" + pid + '&my_post_key=' + my_post_key,
			{
				indicator: '<img src="'+spinner_image+'">',
				loadurl: "xmlhttp.php?action=edit_post&do=get_post&pid=" + pid,
				type: "textarea",
				rows: 12,
				submit: lang.save_changes,
				cancel: lang.cancel_edit,
				event: "edit" + pid, // Triggered by the event "edit_[pid]",
				onblur: "ignore",
				dataType: "json",
				submitdata: function (values, settings)
				{
					id = $(this).attr('id');
					pid = id.replace( /[^\d.]/g, '');
					return {
						editreason: $("#quickedit_" + pid + "_editreason").val()
					}
				},
				callback: function(values, settings)
				{
					id = $(this).attr('id');
					pid = id.replace( /[^\d.]/g, '');
					
					var json = $.parseJSON(values);
					if(typeof json == 'object')
					{
						if(json.hasOwnProperty("errors"))
						{
							$(".jGrowl").jGrowl("close");

							$.each(json.errors, function(i, message)
							{
								$.jGrowl(lang.quick_edit_update_error + ' ' + message);
							});
							$(this).html($('#pid_' + pid + '_temp').html());
						}
						else if(json.hasOwnProperty("moderation_post"))
						{
							$(".jGrowl").jGrowl("close");

							$(this).html(json.message);

							// No more posts on this page? (testing for "1" as the last post would be removed here)
							if($('.post').length == 1)
							{
								alert(json.moderation_post);
								window.location = json.url;
							}
							else
							{
								$.jGrowl(json.moderation_post);
								$('#post_' + pid).slideToggle();
							}
						}
						else if(json.hasOwnProperty("moderation_thread"))
						{
							$(".jGrowl").jGrowl("close");

							$(this).html(json.message);
							
							alert(json.moderation_thread);
							
							// Redirect user to forum
							window.location = json.url;
						}	
						else
						{
							// Change html content
							$(this).html(json.message);
							$('#edited_by_' + pid).html(json.editedmsg);
						}
					}
					else
					{
						// Change html content
						$(this).html(json.message);
						$('#edited_by_' + pid).html(json.editedmsg);
					}
					$('#pid_' + pid + '_temp').remove();
				}
			});
        });

		$('.quick_edit_button').each(function()
		{
			$(this).bind("click", function(e)
			{
				e.stopPropagation();

				// Take pid out of the id attribute
				id = $(this).attr('id');
				pid = id.replace( /[^\d.]/g, '');

				// Create a copy of the post
				if($('#pid_' + pid + '_temp').length == 0)
				{
					$('#pid_' + pid).clone().attr('id','pid_' + pid + '_temp').css('display','none').appendTo("body");
				}

				// Trigger the edit event
				$('#pid_' + pid).trigger("edit" + pid);

				// Edit Reason
				$('#pid_' + pid + ' textarea').attr('id', 'quickedit_' + pid);
				if(allowEditReason == 1 && $('#quickedit_' + pid + '_editreason').length == 0)
				{
					$('#quickedit_' + pid).after(lang.editreason + ': <input type="text" class="textbox" name="editreason" size="50" maxlength="150" id="quickedit_' + pid + '_editreason" /><br />');
				}
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
		
		// Spinner!
		$('#quickreply_spinner').show();

		$.ajax(
		{
			url: 'newreply.php?ajax=1',
			type: 'post',
			data: post_body,
			dataType: 'html',
        	complete: function (request, status)
        	{
		  		Thread.quickReplyDone(request, status);
				
				// Get rid of spinner
				$('#quickreply_spinner').hide();
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
				$(".jGrowl").jGrowl("close");

				$.each(json.errors, function(i, message)
				{
					$.jGrowl(lang.quick_reply_post_error + ' ' + message);
				});
			}
		}

		if($('#captcha_trow'))
		{
			cap = json.data.match(/^<captcha>([0-9a-zA-Z]+)(\|([0-9a-zA-Z]+)|)<\/captcha>/);
			if(cap)
			{
				json.data = json.data.replace(/^<captcha>(.*)<\/captcha>/, '');

				if(cap[1] == "reload")
				{
					Recaptcha.reload();
				}
				else if($("#captcha_img"))
				{
					if(cap[1])
					{
						imghash = cap[1];
						$('#imagehash').val(imghash);
						if(cap[3])
						{
							$('#imagestring').attr('type', 'hidden');
							$('#imagestring').val(cap[3]);
							// hide the captcha
							$('#captcha_trow').css('display', 'none');
						}
						else
						{
							$('#captcha_img').attr('src', "captcha.php?action=regimage&imagehash="+imghash);
							$('#imagestring').attr('type', 'text');
							$('#imagestring').val('');
							$('#captcha_trow').css('display', '');
						}
					}
				}
			}
		}
		
		if(json.hasOwnProperty("errors"))
			return false;

		if(json.data.match(/id="post_([0-9]+)"/))
		{
			var pid = json.data.match(/id="post_([0-9]+)"/)[1];
			var post = document.createElement("div");

			$('#posts').append(json.data);
			
			if (typeof inlineModeration != "undefined") // Guests don't have this object defined
				$("#inlinemod_" + pid).on('change', inlineModeration.checkItem);
				
			Thread.quickEdit("#pid_" + pid);

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

		$(".jGrowl").jGrowl("close");
	},

	showIgnoredPost: function(pid)
	{
		$('#ignored_post_'+pid).slideToggle("slow");
		$('#post_'+pid).slideToggle("slow");
	},

	deletePost: function(pid)
	{
		$.prompt(quickdelete_confirm, {
			buttons:[
					{title: yes_confirm, value: true},
					{title: no_confirm, value: false}
			],
			submit: function(e,v,m,f){
				if(v == true)
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
									$.jGrowl(lang.quick_delete_error + ' ' + message);
								});
							}
							else if(json.hasOwnProperty("data"))
							{
								// Soft deleted
								if(json.data == 1)
								{
									// Change CSS class of div 'pid_[pid]'
									$("#post_"+pid).addClass("unapproved_post");
									$("#post_"+pid).addClass("deleted_post");

									$("#quick_delete_" + pid).hide();
									$("#quick_restore_" + pid).show();

									$.jGrowl(lang.quick_delete_success);
								}
								else if(json.data == 2)
								{
									// Actually deleted
									$('#post_'+pid).slideToggle("slow");
									
									$.jGrowl(lang.quick_delete_success);
								} else if(json.data == 3) 
								{
									// deleted thread --> redirect
									
									if(!json.hasOwnProperty("url")) 
									{
										$.jGrowl(lang.unknown_error);
									}
									
									// set timeout for redirect
									window.setTimeout(function() 
									{
 										window.location = json.url;
									}, 3000);
									
									// print success message
									$.jGrowl(lang.quick_delete_thread_success);
								}
							}
							else
							{
								$.jGrowl(lang.unknown_error);
							}
						}
					});
				}
			}
		});
		
		return false;
	},


	restorePost: function(pid)
	{
		$.prompt(quickrestore_confirm, {
			buttons:[
					{title: yes_confirm, value: true},
					{title: no_confirm, value: false}
			],
			submit: function(e,v,m,f){
				if(v == true)
				{
					$.ajax(
					{
						url: 'editpost.php?ajax=1&action=restorepost&restore=1&my_post_key='+my_post_key+'&pid='+pid,
						type: 'post',
						complete: function (request, status)
						{
							var json = $.parseJSON(request.responseText);
							if(json.hasOwnProperty("errors"))
							{
								$.each(json.errors, function(i, message)
								{
									$.jGrowl(lang.quick_restore_error + ' ' + message);
								});
							}
							else if(json.hasOwnProperty("data"))
							{
								// Change CSS class of div 'pid_[pid]'
								$("#post_"+pid).removeClass("unapproved_post");
								$("#post_"+pid).removeClass("deleted_post");

								$("#quick_delete_" + pid).show();
								$("#quick_restore_" + pid).hide();

								$.jGrowl(lang.quick_restore_success);
							}
							else
							{
								$.jGrowl(lang.unknown_error);
							}
						}
					});
				}
			}
		});
		
		return false;
	},

	viewNotes: function(tid)
	{
		MyBB.popupWindow("/moderation.php?action=viewthreadnotes&tid="+tid);
	}
};

Thread.init();