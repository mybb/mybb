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
			$(this).change(function() {
				var checkboxes = $(this).closest('form').find(':checkbox');
				if($(this).is(':checked')) {
					checkboxes.prop('checked', true);
				} else {
					checkboxes.removeAttr('checked');
				}
			});
		});

		// Initialise "initial focus" field if we have one
		var initialfocus = $(".initial_focus");
		if(initialfocus.length)
		{
			initialfocus.focus();
		}

		if(typeof(use_xmlhttprequest) != "undefined" && use_xmlhttprequest == 1)
		{
			mark_read_imgs = $(".ajax_mark_read");
			mark_read_imgs.each(function()
			{
				var element = $(this);
				if(element.hasClass('forum_off') || element.hasClass('forum_offlock') || element.hasClass('forum_offlink') || element.hasClass('subforum_minioff') || element.hasClass('subforum_miniofflock') || element.hasClass('subforum_miniofflink') || (element.attr("title") && element.attr("title") == lang.no_new_posts)) return;

				element.click(function()
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
					initialfocus.focus();
				}
			});

			$(document).on($.modal.CLOSE, function(event, modal) {
				$("body").css("overflow", "auto");
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

	deleteEvent: function(eid)
	{
		$.prompt(deleteevent_confirm, {
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
					form.submit();
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

	deleteReputation: function(uid, rid)
	{
		$.prompt(delete_reputation_confirm, {
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
					form.submit();
				}
			}
		});

		return false;
	},

	whoPosted: function(tid)
	{
		MyBB.popupWindow("/misc.php?action=whoposted&tid="+tid+"&modal=1");
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
		form.submit();
	},

	changeTheme: function()
	{
		form = $("#theme_select");
		if(!form.length)
		{
			return false;
		}
		form.submit();
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
						form.submit();
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
		$.prompt(announcement_quickdelete_confirm, {
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
		return $.cookie(name);
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
			domain: cookieDomain
		};

		return $.cookie(name, value, options);
	},

	unset: function(name)
	{
		name = cookiePrefix + name;

		options = {
			path: cookiePath,
			domain: cookieDomain
		};
		return $.removeCookie(name, options);
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

				expander.click(function()
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

MyBB.init();
