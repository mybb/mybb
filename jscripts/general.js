var MyBB = {
	init: function()
	{
		$(function()
		{
			MyBB.pageLoaded();
		});

		return true;
	},

	pageLoaded: function()
	{
		expandables.init();

		/* Create the Check All feature */
		$('[name="allbox"]').each(function(key, value) {
			var allbox = this;
			var checked = $(this).is(':checked');
			var checkboxes = $(this).closest('form').find(':checkbox').not('[name="allbox"]');

			checkboxes.on('change', function() {
				if(checked && !$(this).prop('checked'))
				{
					checked = false;
					$(allbox).trigger('change', ['item']);
				}
			});

			$(this).on('change', function(event, origin) {
				checked = $(this).is(':checked');

				if(typeof(origin) == "undefined")
				{
					checkboxes.each(function() {
						if(checked != $(this).is(':checked'))
						{
							$(this).prop('checked', checked).trigger('change');
						}
					});
				}
			});
		});

		// Initialise "initial focus" field if we have one
		var initialfocus = $(".initial_focus");
		if(initialfocus.length)
		{
			initialfocus.trigger('focus');
		}

		if(typeof(use_xmlhttprequest) != "undefined" && use_xmlhttprequest == 1)
		{
			mark_read_imgs = $(".ajax_mark_read");
			mark_read_imgs.each(function()
			{
				var element = $(this);
				if(element.hasClass('forum_off') || element.hasClass('forum_offclose') || element.hasClass('forum_offlink') || element.hasClass('subforum_minioff') || element.hasClass('subforum_minioffclose') || element.hasClass('subforum_miniofflink') || (element.attr("title") && element.attr("title") == lang.no_new_posts)) return;

				element.on('click', function()
				{
					MyBB.markForumRead(this);
				});

				element.css("cursor", "pointer");
				if(element.attr("title"))
				{
					element.attr("title", element.attr("title") + " - ");
				}
				element.attr("title", element.attr("title") + lang.click_mark_read);
			});
		}

		if(typeof $.modal !== "undefined")
		{
			$(document).on($.modal.OPEN, function(event, modal) {
				$("body").css("overflow", "hidden");
				if(initialfocus.length > 0)
				{
					initialfocus.trigger('focus');
				}
			});

			$(document).on($.modal.CLOSE, function(event, modal) {
				$("body").css("overflow", "auto");
			});
		}

		$("a.referralLink").on('click', MyBB.showReferrals);

		if($('.author_avatar').length)
		{
			$(".author_avatar img").on('error', function () {
				$(this).unbind("error").closest('.author_avatar').remove();
			});
		}
	},

	popupWindow: function(url, options, root)
	{
		if(!options) options = { fadeDuration: 250, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) }
		if(root != true)
			url = rootpath + url;

		$.get(url, function(html)
		{
			$(html).appendTo('body').modal(options);
		});
	},

	prompt: function(message, options)
	{
		var defaults = { fadeDuration: 250, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) };
		var buttonsText = '';

		for (var i in options.buttons)
		{
			buttonsText += templates.modal_button.replace('__title__', options.buttons[i].title);
		}

		var html = templates.modal.replace('__buttons__', buttonsText).replace('__message__', message);
		var modal = $(html);
		modal.modal($.extend(defaults, options));
		var buttons = modal.find('.modal_buttons > .button');
		buttons.on('click', function(e)
		{
			e.preventDefault();
			var index = $(this).index();
			if (options.submit(e, options.buttons[index].value) == false)
				return;

			$.modal.close();
		});

		if (buttons[0])
		{
			modal.on($.modal.OPEN, function()
			{
				$(buttons[0]).trigger('focus');
			});
		}

		return modal;
	},

	deleteEvent: function(eid)
	{
		MyBB.prompt(deleteevent_confirm, {
			buttons:[
					{title: yes_confirm, value: true},
					{title: no_confirm, value: false}
			],
			submit: function(e,v,m,f){
				if(v == true)
				{
					var form = $("<form />",
							   {
									method: "post",
									action: "calendar.php",
									style: "display: none;"
							   });

					form.append(
						$("<input />",
						{
							name: "action",
							type: "hidden",
							value: "do_deleteevent"
						})
					);

					if(my_post_key)
					{
						form.append(
							$("<input />",
							{
								name: "my_post_key",
								type: "hidden",
								value: my_post_key
							})
						);
					}

					form.append(
						$("<input />",
						{
							name: "eid",
							type: "hidden",
							value: eid
						})
					);

					form.append(
						$("<input />",
						{
							name: "delete",
							type: "hidden",
							value: 1
						})
					);

					$("body").append(form);
					form.trigger('submit');
				}
			}
		});
	},

	reputation: function(uid, pid)
	{
		if(!pid)
		{
			var pid = 0;
		}

		MyBB.popupWindow("/reputation.php?action=add&uid="+uid+"&pid="+pid+"&modal=1");
	},

	viewNotes: function(uid)
	{
		MyBB.popupWindow("/member.php?action=viewnotes&uid="+uid+"&modal=1");
	},

	getIP: function(pid)
	{
		MyBB.popupWindow("/moderation.php?action=getip&pid="+pid+"&modal=1");
	},

	getPMIP: function(pmid)
	{
		MyBB.popupWindow("/moderation.php?action=getpmip&pmid="+pmid+"&modal=1");
	},

	deleteReputation: function(uid, rid)
	{
		MyBB.prompt(delete_reputation_confirm, {
			buttons:[
					{title: yes_confirm, value: true},
					{title: no_confirm, value: false}
			],
			submit: function(e,v,m,f){
				if(v == true)
				{
					var form = $("<form />",
					{
						method: "post",
						action: "reputation.php?action=delete",
						style: "display: none;"
					});

					form.append(
						$("<input />",
						{
							name: "rid",
							type: "hidden",
							value: rid
						})
					);

					if(my_post_key)
					{
						form.append(
							$("<input />",
							{
								name: "my_post_key",
								type: "hidden",
								value: my_post_key
							})
						);
					}

					form.append(
						$("<input />",
						{
							name: "uid",
							type: "hidden",
							value: uid
						})
					);

					$("body").append(form);
					form.trigger('submit');
				}
			}
		});

		return false;
	},

	whoPosted: function(tid, sortby)
	{
		var sort = "", url, body;

		if(typeof sortby === "undefined")
		{
			sortby = "";
		}

		if(sortby == "username")
		{
			sort = "&sort=" + sortby;
		}
		url = "/misc.php?action=whoposted&tid="+tid+sort+"&modal=1";

		// if the modal is already open just replace the contents
		if($.modal.isActive())
		{
			// don't waste a query if we are already sorted correctly
			if(sortby == MyBB.whoPostedSort)
			{
				return;
			}

			MyBB.whoPostedSort = sortby;

			$.get(rootpath + url, function(html)
			{
				// just replace the inner div
				body = $(html).children("div");
				$("div.modal").children("div").replaceWith(body);
			});
			return;
		}
		MyBB.whoPostedSort = "";
		MyBB.popupWindow(url);
	},

	markForumRead: function(event)
	{
		var element = $(event);
		if(!element.length)
		{
			return false;
		}
		var fid = element.attr("id").replace("mark_read_", "");
		if(!fid)
		{
			return false;
		}

		$.ajax(
		{
			url: 'misc.php?action=markread&fid=' + fid + '&ajax=1&my_post_key=' + my_post_key,
			async: true,
        	success: function (request)
        	{
		  		MyBB.forumMarkedRead(fid, request);
          	}
		});
	},

	forumMarkedRead: function(fid, request)
	{
		if(request == 1)
		{
			var markreadfid = $("#mark_read_"+fid);
			if(markreadfid.hasClass('subforum_minion'))
			{
				markreadfid.removeClass('subforum_minion').addClass('subforum_minioff');
			}
			else
			{
				markreadfid.removeClass('forum_on').addClass('forum_off');
			}
			markreadfid.css("cursor", "default").attr("title", lang.no_new_posts);
		}
	},

	unHTMLchars: function(text)
	{
		text = text.replace(/&lt;/g, "<");
		text = text.replace(/&gt;/g, ">");
		text = text.replace(/&nbsp;/g, " ");
		text = text.replace(/&quot;/g, "\"");
		text = text.replace(/&amp;/g, "&");
		return text;
	},

	HTMLchars: function(text)
	{
		text = text.replace(new RegExp("&(?!#[0-9]+;)", "g"), "&amp;");
		text = text.replace(/</g, "&lt;");
		text = text.replace(/>/g, "&gt;");
		text = text.replace(/"/g, "&quot;");
		return text;
	},

	changeLanguage: function()
	{
		form = $("#lang_select");
		if(!form.length)
		{
			return false;
		}
		form.trigger('submit');
	},

	changeTheme: function()
	{
		form = $("#theme_select");
		if(!form.length)
		{
			return false;
		}
		form.trigger('submit');
	},

	detectDSTChange: function(timezone_with_dst)
	{
		var date = new Date();
		var local_offset = date.getTimezoneOffset() / 60;
		if(Math.abs(parseInt(timezone_with_dst) + local_offset) == 1)
		{
			$.ajax(
			{
				url: 'misc.php?action=dstswitch&ajax=1',
				async: true,
				method: 'post',
	          	error: function (request)
	          	{
	          		if(use_xmlhttprequest != 1)
	                {
						var form = $("<form />",
						           {
						           		method: "post",
						           		action: "misc.php",
						           		style: "display: none;"
						           });

						form.append(
						    $("<input />",
							{
								name: "action",
								type: "hidden",
								value: "dstswitch"
							})
						);

						$("body").append(form);
						form.trigger('submit');
	                }
	            }
			});
		}
	},

	dismissPMNotice: function(bburl)
	{
		var pm_notice = $("#pm_notice");
		if(!pm_notice.length)
		{
			return false;
		}

		if(use_xmlhttprequest != 1)
		{
			return true;
		}

		$.ajax(
		{
			type: 'post',
			url: bburl + 'private.php?action=dismiss_notice',
			data: { ajax: 1, my_post_key: my_post_key },
			async: true
		});
		pm_notice.remove();
		return false;
	},

	submitReputation: function(uid, pid, del)
	{
		// Get form, serialize it and send it
		var datastring = $(".reputation_"+uid+"_"+pid).serialize();

		if(del == 1)
			datastring = datastring + '&delete=1';

		$.ajax({
			type: "POST",
			url: "reputation.php?modal=1",
			data: datastring,
			dataType: "html",
			success: function(data) {
				// Replace modal HTML (we have to access by class because the modals are appended to the end of the body, and when we get by class we get the last element of that class - which is what we want)
				$(".modal_"+uid+"_"+pid).fadeOut('slow', function() {
					$(".modal_"+uid+"_"+pid).html(data);
					$(".modal_"+uid+"_"+pid).fadeIn('slow');
					$(".modal").fadeIn('slow');
				});
			},
			error: function(){
				  alert(lang.unknown_error);
			}
		});

		return false;
	},

	deleteAnnouncement: function(data)
	{
		MyBB.prompt(announcement_quickdelete_confirm, {
			buttons:[
					{title: yes_confirm, value: true},
					{title: no_confirm, value: false}
			],
			submit: function(e,v,m,f){
				if(v == true)
				{
					window.location=data.href.replace('action=delete_announcement','action=do_delete_announcement');
				}
			}
		});

		return false;
	},

	showReferrals: function(e)
	{
		var idPieces, uid;

		e.preventDefault();
		
		if(typeof this.id == "undefined")
		{
			return false;
		}

		idPieces = this.id.split("_");
		uid = parseInt(idPieces[idPieces.length - 1], 10);

		if(uid <= 0)
		{
			return false;
		}

		MyBB.popupWindow("/xmlhttp.php?action=get_referrals&uid="+uid);
	},

	// Fixes https://github.com/mybb/mybb/issues/1232
	select2: function()
	{
		if(typeof $.fn.select2 !== "undefined")
		{
			$.extend($.fn.select2.defaults, {
				formatMatches: function (matches) {
					if(matches == 1)
					{
						return lang.select2_match;
					}
					else
					{
						return lang.select2_matches.replace('{1}',matches);
					}
				},
				formatNoMatches: function () {
					return lang.select2_nomatches;
				},
				formatInputTooShort: function (input, min) {
					var n = min - input.length;
					if( n == 1)
					{
						return lang.select2_inputtooshort_single;
					}
					else
					{
						return lang.select2_inputtooshort_plural.replace('{1}', n);
					}
				},
				formatInputTooLong: function (input, max) {
					var n = input.length - max;
					if( n == 1)
					{
						return lang.select2_inputtoolong_single;
					}
					else
					{
						return lang.select2_inputtoolong_plural.replace('{1}', n);
					}
				},
				formatSelectionTooBig: function (limit) {
					if( limit == 1)
					{
						return lang.select2_selectiontoobig_single;
					}
					else
					{
						return lang.select2_selectiontoobig_plural.replace('{1}', limit);
					}
				},
				formatLoadMore: function (pageNumber) {
					return lang.select2_loadmore;
				},
				formatSearching: function () {
					return lang.select2_searching;
				}
			});
		}
	}
};

var Cookie = {
	get: function(name)
	{
		name = cookiePrefix + name;
		return Cookies.get(name);
	},

	set: function(name, value, expires)
	{
		name = cookiePrefix + name;
		if(!expires)
		{
			expires = 315360000; // 10*365*24*60*60 => 10 years
		}

		expire = new Date();
		expire.setTime(expire.getTime()+(expires*1000));

		options = {
			expires: expire,
			path: cookiePath,
			domain: cookieDomain,
			secure: cookieSecureFlag == true,
		};

		return Cookies.set(name, value, options);
	},

	unset: function(name)
	{
		name = cookiePrefix + name;

		options = {
			path: cookiePath,
			domain: cookieDomain
		};
		return Cookies.remove(name, options);
	}
};

var expandables = {

	init: function()
	{
		var expanders = $(".expcolimage .expander");
		if(expanders.length)
		{
			expanders.each(function()
			{
        		var expander = $(this);
				if(expander.attr("id") == false)
				{
					return;
				}

				expander.on('click', function()
				{
					controls = expander.attr("id").replace("_img", "");
					expandables.expandCollapse(this, controls);
				});

				if(MyBB.browser == "ie")
				{
					expander.css("cursor", "hand");
				}
				else
				{
					expander.css("cursor", "pointer");
				}
			});
		}
	},

	expandCollapse: function(e, controls)
	{
		element = $(e);

		if(!element || controls == false)
		{
			return false;
		}
		var expandedItem = $("#"+controls+"_e");
		var collapsedItem = $("#"+controls+"_c");

		if(expandedItem.length && collapsedItem.length)
		{
			// Expanding
			if(expandedItem.is(":hidden"))
			{
				expandedItem.toggle("fast");
				collapsedItem.toggle("fast");
				this.saveCollapsed(controls);
			}
			// Collapsing
			else
			{
				expandedItem.toggle("fast");
				collapsedItem.toggle("fast");
				this.saveCollapsed(controls, 1);
			}
		}
		else if(expandedItem.length && !collapsedItem.length)
		{
			// Expanding
			if(expandedItem.is(":hidden"))
			{
				expandedItem.toggle("fast");
				element.attr("src", element.attr("src").replace(/collapse_collapsed\.(gif|jpg|jpeg|bmp|png)$/i, "collapse.$1"))
									.attr("alt", "[-]")
									.attr("title", "[-]");
				element.parent().parent('td').removeClass('tcat_collapse_collapsed');
				element.parent().parent('.thead').removeClass('thead_collapsed');
				this.saveCollapsed(controls);
			}
			// Collapsing
			else
			{
				expandedItem.toggle("fast");
				element.attr("src", element.attr("src").replace(/collapse\.(gif|jpg|jpeg|bmp|png)$/i, "collapse_collapsed.$1"))
									.attr("alt", "[+]")
									.attr("title", "[+]");
				element.parent().parent('td').addClass('tcat_collapse_collapsed');
				element.parent().parent('.thead').addClass('thead_collapsed');
				this.saveCollapsed(controls, 1);
			}
		}
		return true;
	},

	saveCollapsed: function(id, add)
	{
		var saved = [];
		var newCollapsed = [];
		var collapsed = Cookie.get('collapsed');

		if(collapsed)
		{
			saved = collapsed.split("|");

			$.each(saved, function(intIndex, objValue)
			{
				if(objValue != id && objValue != "")
				{
					newCollapsed[newCollapsed.length] = objValue;
				}
			});
		}

		if(add == 1)
		{
			newCollapsed[newCollapsed.length] = id;
		}
		Cookie.set('collapsed', newCollapsed.join("|"));
	}
};

/* Lang this! */
var lang = {

};

/* add keepelement to jquery-modal plugin */
(function($) {
	if(typeof $.modal != 'undefined')
	{
		$.modal.defaults.keepelement = false;

		$.modal.prototype.oldCloseFunction = $.modal.prototype.close;
		$.modal.prototype.close = function()
		{
			this.oldCloseFunction();

			// Deletes the element (multi-modal feature: e.g. when you click on multiple report buttons, you will want to see different content for each)
			if(!this.options.keepelement)
			{
				this.$elm.remove();
			}
		};
	}
})(jQuery);


MyBB.init();
