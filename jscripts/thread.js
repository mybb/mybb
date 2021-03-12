var Thread = {
	init: function()
	{
		$(function(){
			Thread.quickEdit();
			Thread.initQuickReply();
			Thread.initMultiQuote();

			// Set spinner image
			$('#quickreply_spinner img').attr('src', spinner_image);
		});
	},

	initMultiQuote: function()
	{
		var quoted = Cookie.get('multiquote');
		if(quoted)
		{
			var post_ids = quoted.split("|");

			$.each(post_ids, function(key, value) {
				var mquote_a = $("#multiquote_"+value).closest('a');
				if(mquote_a.length)
				{
					mquote_a.removeClass('postbit_multiquote').addClass('postbit_multiquote_on');
				}
			});

			var mquote_quick = $('#quickreply_multiquote');
			if(mquote_quick.length)
			{
				mquote_quick.show();
			}
		}
		return true;
	},

	multiQuote: function(pid)
	{
		var new_post_ids = new Array();
		var quoted = Cookie.get("multiquote");
		var is_new = true;
		var deleted = false;
		if($("#pid" + pid).next("div.post").hasClass('deleted_post'))
		{
			$.jGrowl(lang.post_deleted_error, {theme:'jgrowl_error'});
			deleted = true;
		}

		if(quoted && !deleted)
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

		var mquote_a = $("#multiquote_"+pid).closest('a')
		if(is_new == true && !deleted)
		{
			new_post_ids[new_post_ids.length] = pid;
			mquote_a.removeClass('postbit_multiquote').addClass('postbit_multiquote_on');
		}
		else
		{
			mquote_a.removeClass('postbit_multiquote_on').addClass('postbit_multiquote');
		}

		var mquote_quick = $('#quickreply_multiquote');
		if(mquote_quick.length)
		{
			if(new_post_ids.length)
			{
				mquote_quick.show();
			}
			else
			{
				mquote_quick.hide();
			}
		}
		Cookie.set("multiquote", new_post_ids.join("|"));
	},

	loadMultiQuoted: function()
	{
		if(use_xmlhttprequest == 1)
		{
			// Spinner!
			var mquote_spinner = $('#quickreply_spinner');
			mquote_spinner.show();

			$.ajax(
			{
				url: 'xmlhttp.php?action=get_multiquoted&load_all=1',
				type: 'get',
				complete: function (request, status)
				{
					Thread.multiQuotedLoaded(request, status);

					// Get rid of spinner
					mquote_spinner.hide();
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
		var json = JSON.parse(request.responseText);
		if(typeof json == 'object')
		{
			if(json.hasOwnProperty("errors"))
			{
				$.each(json.errors, function(i, message)
				{
					$.jGrowl(lang.post_fetch_error + ' ' + message, {theme:'jgrowl_error'});
				});
				return false;
			}
		}

		if(typeof MyBBEditor !== 'undefined' && MyBBEditor !== null)
		{
			MyBBEditor.insert(json.message);
		}
		else
		{
			var id = $('#message');
			if(id.value)
			{
				id.value += "\n";
			}
			id.val(id.val() + json.message);
		}

		Thread.clearMultiQuoted();
		$('#quickreply_multiquote').hide();
		$('#quoted_ids').val('all');

		$('#message').trigger('focus');
	},

	clearMultiQuoted: function()
	{
		$('#quickreply_multiquote').hide();
		var quoted = Cookie.get("multiquote");
		if(quoted)
		{
			var post_ids = quoted.split("|");

			$.each(post_ids, function(key, post_id) {
				var mquote_a = $("#multiquote_"+post_id).closest('a');
				if(mquote_a.length)
				{
					mquote_a.removeClass('postbit_multiquote_on').addClass('postbit_multiquote');
				}
			});
		}
		Cookie.unset('multiquote');
	},

	quickEdit: function(el)
	{
		if(typeof el === 'undefined' || !el.length) el = '.post_body';

		$(el).each(function()
		{
			// Take pid out of the id attribute
			id = $(this).attr('id');
			pid = id.replace( /[^\d.]/g, '');

			$('#pid_' + pid).editable("xmlhttp.php?action=edit_post&do=update_post&pid=" + pid + '&my_post_key=' + my_post_key,
			{
				indicator: spinner,
				loadurl: "xmlhttp.php?action=edit_post&do=get_post&pid=" + pid,
				type: "textarea",
				rows: 12,
				submit: lang.save_changes,
				cancel: lang.cancel_edit,
				placeholder: "",
				event: "edit" + pid, // Triggered by the event "edit_[pid]",
				onblur: "ignore",
				dataType: "json",
				submitdata: function (values, settings)
				{
					id = $(this).attr('id');
					pid = id.replace( /[^\d.]/g, '');
					$("#quickedit_" + pid + "_editreason_original").val($("#quickedit_" + pid + "_editreason").val());
					return {
						editreason: $("#quickedit_" + pid + "_editreason").val()
					}
				},
				callback: function(values, settings)
				{
					id = $(this).attr('id');
					pid = id.replace( /[^\d.]/g, '');

					var json = JSON.parse(values);
					if(typeof json == 'object')
					{
						if(json.hasOwnProperty("errors"))
						{
							$(".jGrowl").jGrowl("close");

							$.each(json.errors, function(i, message)
							{
								$.jGrowl(lang.quick_edit_update_error + ' ' + message, {theme:'jgrowl_error'});
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
								$.jGrowl(json.moderation_post, {theme:'jgrowl_success'});
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
			$(this).on("click", function(e)
			{
				e.preventDefault();

				// Take pid out of the id attribute
				id = $(this).attr('id');
				pid = id.replace( /[^\d.]/g, '');
				if($("#pid" + pid).next("div.post").hasClass('deleted_post'))
				{
					$.jGrowl(lang.post_deleted_error, {theme:'jgrowl_error'});
					return false;
				}

				// Create a copy of the post
				if($('#pid_' + pid + '_temp').length == 0)
				{
					$('#pid_' + pid).clone().attr('id','pid_' + pid + '_temp').appendTo("body").hide();
				}

				// Trigger the edit event
				$('#pid_' + pid).trigger("edit" + pid);

				// Edit Reason
				$('#pid_' + pid + ' textarea').attr('id', 'quickedit_' + pid);
				if(allowEditReason == 1 && $('#quickedit_' + pid + '_editreason').length == 0)
				{
					edit_el = $('#editreason_' + pid + '_original').clone().attr('id','editreason_' + pid);
					edit_el.children('#quickedit_' + pid + '_editreason_original').attr('id','quickedit_' + pid + '_editreason');
					edit_el.insertAfter('#quickedit_' + pid).show();
				}
			});
        });

		return false;
	},

	initQuickReply: function()
	{
		if($('#quick_reply_form').length && use_xmlhttprequest == 1)
		{
			// Bind closing event to our popup menu
			$('#quick_reply_submit').on('click', function(e) {
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
		var qreply_spinner = $('#quickreply_spinner');
		qreply_spinner.show();

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
				qreply_spinner.hide();
          	}
		});

		return false;
	},

	quickReplyDone: function(request, status)
	{
		this.quick_replying = 0;

		var json = JSON.parse(request.responseText);
		if(typeof json == 'object')
		{
			if(json.hasOwnProperty("errors"))
			{
				$(".jGrowl").jGrowl("close");

				$.each(json.errors, function(i, message)
				{
					$.jGrowl(lang.quick_reply_post_error + ' ' + message, {theme:'jgrowl_error'});
				});
				$('#quickreply_spinner').hide();
			}
		}

		if($('#captcha_trow').length)
		{
			cap = json.data.match(/^<captcha>([0-9a-zA-Z]+)(\|([0-9a-zA-Z]+)|)<\/captcha>/);
			if(cap)
			{
				json.data = json.data.replace(/^<captcha>(.*)<\/captcha>/, '');

				if($("#captcha_img").length)
				{
					if(cap[1])
					{
						imghash = cap[1];
						$('#imagehash').val(imghash);
						if(cap[3])
						{
							$('#imagestring').attr('type', 'hidden').val(cap[3]);
							// hide the captcha
							$('#captcha_trow').hide();
						}
						else
						{
							$('#captcha_img').attr('src', "captcha.php?action=regimage&imagehash="+imghash);
							$('#imagestring').attr('type', 'text').val('');
							$('#captcha_trow').show();
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

			// Eval javascript
			$(json.data).filter("script").each(function(e) {
				eval($(this).text());
			});

			$('#quick_reply_form')[0].reset();

			var lastpid = $('#lastpid');
			if(lastpid.length)
			{
				lastpid.val(pid);
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

	showDeletedPost: function(pid)
	{
		$('#deleted_post_'+pid).slideToggle("slow");
		$('#post_'+pid).slideToggle("slow");
	},

	deletePost: function(pid)
	{
		MyBB.prompt(quickdelete_confirm, {
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
							var json = JSON.parse(request.responseText);
							if(json.hasOwnProperty("errors"))
							{
								$.each(json.errors, function(i, message)
								{
									$.jGrowl(lang.quick_delete_error + ' ' + message, {theme:'jgrowl_error'});
								});
							}
							else if(json.hasOwnProperty("data"))
							{
								// Soft deleted
								if(json.data == 1)
								{
									// Change CSS class of div 'post_[pid]'
									$("#post_"+pid).addClass("unapproved_post deleted_post");
									if(json.first == 1)
									{
										$("#quick_reply_form, .thread_tools, .new_reply_button, .inline_rating").hide();
										$("#moderator_options_selector option.option_mirage").attr("disabled","disabled");
										$("#moderator_options_selector option[value='softdeletethread']").val("restorethread").text(lang.restore_thread);
									}

									$.jGrowl(lang.quick_delete_success, {theme:'jgrowl_success'});
								}
								else if(json.data == 2)
								{
									// Actually deleted
									$('#post_'+pid).slideToggle("slow");

									$.jGrowl(lang.quick_delete_success, {theme:'jgrowl_success'});
								} else if(json.data == 3)
								{
									// deleted thread --> redirect

									if(!json.hasOwnProperty("url"))
									{
										$.jGrowl(lang.unknown_error, {theme:'jgrowl_error'});
									}

									// set timeout for redirect
									window.setTimeout(function()
									{
 										window.location = json.url;
									}, 3000);

									// print success message
									$.jGrowl(lang.quick_delete_thread_success, {theme:'jgrowl_success'});
								}
							}
							else
							{
								$.jGrowl(lang.unknown_error, {theme:'jgrowl_error'});
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
		MyBB.prompt(quickrestore_confirm, {
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
							var json = JSON.parse(request.responseText);
							if(json.hasOwnProperty("errors"))
							{
								$.each(json.errors, function(i, message)
								{
									$.jGrowl(lang.quick_restore_error + ' ' + message, {theme:'jgrowl_error'});
								});
							}
							else if(json.hasOwnProperty("data"))
							{
								// Change CSS class of div 'post_[pid]'
								$("#post_"+pid).removeClass("unapproved_post deleted_post");
								if(json.first == 1)
								{
									$("#quick_reply_form, .thread_tools, .new_reply_button, .inline_rating").show();
									$("#moderator_options_selector option.option_mirage").prop("disabled", false);
									$("#moderator_options_selector option[value='restorethread']").val("softdeletethread").text(lang.softdelete_thread);
								}

								$.jGrowl(lang.quick_restore_success, {theme:'jgrowl_success'});
							}
							else
							{
								$.jGrowl(lang.unknown_error, {theme:'jgrowl_error'});
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
		MyBB.popupWindow("/moderation.php?action=viewthreadnotes&tid="+tid+"&modal=1");
	}
};

Thread.init();
