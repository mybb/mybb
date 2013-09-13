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

		// Initialise "initial focus" field if we have one
		var initialfocus = $("input.initial_focus");
		if(initialfocus.length > 0)
		{
			initialfocus.focus();
		}

		if(typeof(use_xmlhttprequest) != "undefined" && use_xmlhttprequest == 1)
		{
			mark_read_imgs = $("img.ajax_mark_read");
			mark_read_imgs.each(function()
			{
				var element = $(this);
				if(element.attr("src").match("off.png") || element.attr("src").match("offlock.png") || (element.attr("title") && element.attr("title") == lang.no_new_posts)) return;

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

		$(document).on($.modal.OPEN, function(event, modal) {
			$("body").css("overflow", "hidden");
		});

		$(document).on($.modal.CLOSE, function(event, modal) {
			$("body").css("overflow", "auto");
		});
	},

	popupWindow: function(url, options)
	{
		if(!options) options = {}
		$.get(rootpath + url, function(html)
		{
			$(html).appendTo('body').modal(options);
		});
	},

	deleteEvent: function(eid)
	{
		confirmReturn = confirm(deleteevent_confirm);

		if(confirmReturn == true)
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
					value: "do_editevent"
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
	},

	deleteReputation: function(uid, rid)
	{
		var confirmReturn = confirm(delete_reputation_confirm);

		if(confirmReturn == true)
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
	},

	markForumRead: function(event)
	{
		var element = $(event);
		if(!element)
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
			$("#mark_read_"+fid).attr("src", $("#mark_read_"+fid).attr("src").replace("on.png", "off.png"))
								.css("cursor", "default")
								.attr("title", lang.no_new_posts);
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
		if(!form)
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

	dismissPMNotice: function()
	{
		var pm_notice = $("#content").find("div#pm_notice");
		if(!pm_notice)
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
			url: 'private.php?action=dismiss_notice',
			data: { ajax: 1, my_post_key: my_post_key },
			async: true
		});
		pm_notice.remove();
		return false;
	}
}

var expandables = {

	init: function()
	{
		var expanders = $("div.expcolimage img.expander");
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
			if(expandedItem.is(":hidden"))
			{
				expandedItem.toggle("fast");
				collapsedItem.toggle("fast");
				this.saveCollapsed(controls);
			}
			else
			{
				expandedItem.toggle("fast");
				collapsedItem.toggle("fast");
				this.saveCollapsed(controls, 1);
			}
		}
		else if(expandedItem.length && !collapsedItem.length)
		{
			if(expandedItem.is(":hidden"))
			{
				expandedItem.toggle("fast");
				element.attr("src", element.attr("src").replace("collapse_collapsed.png", "collapse.png"))
									.attr("alt", "[-]")
									.attr("title", "[-]");
				this.saveCollapsed(controls);
			}
			else
			{
				expandedItem.toggle("fast");
				element.attr("src", element.attr("src").replace("collapse.png", "collapse_collapsed.png"))
									.attr("alt", "[+]")
									.attr("title", "[+]");
				this.saveCollapsed(controls, 1);
			}
		}
		return true;
	},

	saveCollapsed: function(id, add)
	{
		var saved = [];
		var newCollapsed = [];
		var collapsed = $.cookie('collapsed');

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
		$.cookie('collapsed', newCollapsed.join("|"));
	}
};

/*!
 * jQuery Cookie Plugin v1.3.1
 * https://github.com/carhartl/jquery-cookie
 *
 * Copyright 2013 Klaus Hartl
 * Released under the MIT license
 */
(function (factory) {
	if (typeof define === 'function' && define.amd) {
		// AMD. Register as anonymous module.
		define(['jquery'], factory);
	} else {
		// Browser globals.
		factory(jQuery);
	}
}(function ($) {

	var pluses = /\+/g;

	function raw(s) {
		return s;
	}

	function decoded(s) {
		return decodeURIComponent(s.replace(pluses, ' '));
	}

	function converted(s) {
		if (s.indexOf('"') === 0) {
			// This is a quoted cookie as according to RFC2068, unescape
			s = s.slice(1, -1).replace(/\\"/g, '"').replace(/\\\\/g, '\\');
		}
		try {
			return config.json ? JSON.parse(s) : s;
		} catch(er) {}
	}

	var config = $.cookie = function (key, value, options) {

		// write
		if (value !== undefined) {
			options = $.extend({}, config.defaults, options);

			if (typeof options.expires === 'number') {
				var days = options.expires, t = options.expires = new Date();
				t.setDate(t.getDate() + days);
			}

			value = config.json ? JSON.stringify(value) : String(value);

			return (document.cookie = [
				config.raw ? key : encodeURIComponent(key),
				'=',
				config.raw ? value : encodeURIComponent(value),
				options.expires ? '; expires=' + options.expires.toUTCString() : '', // use expires attribute, max-age is not supported by IE
				options.path    ? '; path=' + options.path : '',
				options.domain  ? '; domain=' + options.domain : '',
				options.secure  ? '; secure' : ''
			].join(''));
		}

		// read
		var decode = config.raw ? raw : decoded;
		var cookies = document.cookie.split('; ');
		var result = key ? undefined : {};
		for (var i = 0, l = cookies.length; i < l; i++) {
			var parts = cookies[i].split('=');
			var name = decode(parts.shift());
			var cookie = decode(parts.join('='));

			if (key && key === name) {
				result = converted(cookie);
				break;
			}

			if (!key) {
				result[name] = converted(cookie);
			}
		}

		return result;
	};

	config.defaults = {};

	$.removeCookie = function (key, options) {
		if ($.cookie(key) !== undefined) {
			// Must not alter options, thus extending a fresh object...
			$.cookie(key, '', $.extend({}, options, { expires: -1 }));
			return true;
		}
		return false;
	};

}));

/* Lang this! */
var lang = {

};

MyBB.init();