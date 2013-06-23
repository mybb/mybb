var MyBB = {
	init: function()
	{
		this.detectBrowser();
		$(function() {
			MyBB.pageLoaded();
		});

		return true;
	},

	pageLoaded: function()
	{
		expandables.init();

		// Initialise "initial focus" field if we have one
		initialfocus = $("input.initial_focus");
		if(initialfocus.length > 0)
		{
			initialfocus.focus();
		}

		if(typeof(use_xmlhttprequest) != "undefined" && use_xmlhttprequest == 1)
		{
			mark_read_imgs = $(".trow1 img.ajax_mark_read");
			mark_read_imgs.each(function() {
				var element = $(this);
				if(element.attr("src").match("off.png") || element.attr("src").match("offlock.png") || (element.attr("title") && element.attr("title") == lang.no_new_posts)) return;

				element.click(function() {
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
	},

	detectBrowser: function()
	{
		this.useragent = navigator.userAgent.toLowerCase();
		this.useragent_version = parseInt(navigator.appVersion);

		if(navigator.product == "Gecko" && navigator.vendor.indexOf("Apple Computer") != -1)
		{
			this.browser = "safari";
		}
		else if(this.useragent.indexOf("chrome") != -1)
		{
			this.browser = "chrome";
		}
		else if(navigator.product == "Gecko")
		{
			this.browser = "mozilla";
		}
		else if(this.useragent.indexOf("opera") != -1)
		{
			this.browser = "opera";
		}
		else if(this.useragent.indexOf("konqueror") != -1)
		{
			this.browser = "konqueror";
		}
		else if(this.useragent.indexOf("msie") != -1)
		{
			this.browser = "ie";
		}
		else if(this.useragent.indexOf("compatible") == -1 && this.useragent.indexOf("mozilla") != -1)
		{
			this.browser = "netscape";
		}

		if(this.useragent.indexOf("win") != -1)
		{
			this.os = "win";
		}
		else if(this.useragent.indexOf("mac") != -1)
		{
			this.os = "mac";
		}
		else if(this.useragent.indexOf("linux") != -1)
		{
			this.os = "linux";
		}
	},

	deleteEvent: function(eid)
	{
		confirmReturn = confirm(deleteevent_confirm);

		if(confirmReturn == true)
		{
			var form = $("<form />", { method: "post", action: "calendar.php", style: "display: none;" });

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
				form.append($("<input />",
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
		confirmReturn = confirm(delete_reputation_confirm);

		if(confirmReturn == true)
		{
			var form = $("<form />", { method: "post", action: "reputation.php?action=delete", style: "display: none;" });

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
		element = $(event);
		if(!element)
		{
			return false;
		}
		var fid = element.attr("id").replace("mark_read_", "");
		if(!fid)
		{
			return false;
		}

		$.ajax({
		  url: 'misc.php?action=markread&fid='+fid+'&ajax=1&my_post_key='+my_post_key,
		  async: true,
            success: function (request) {
                MyBB.forumMarkedRead(fid, request);
            }
		});
	},

	forumMarkedRead: function(fid, request)
	{
		if(request == 1) {
			$("#mark_read_"+fid).attr("src", $("#mark_read_"+fid).attr("src").replace("on.png", "off.png"));
			$("#mark_read_"+fid).css("cursor", "default");
			$("#mark_read_"+fid).attr("title", lang.no_new_posts);
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
			$.ajax({
			  url: 'misc.php?action=dstswitch&ajax=1',
			  async: true,
			  method: 'post',
	            error: function (request) {
	                if(use_xmlhttprequest != 1)
	                {
						var form = $("<form />", { method: "post", action: "misc.php", style: "display: none;" });

						form.append($("<input />",
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

		$.ajax({
		  type: 'post',
		  url: 'private.php?action=dismiss_notice',
		  data: { ajax: 1, my_post_key: my_post_key },
		  async: true
		});
		pm_notice.remove();
		return false;
	},

	quickLogin: function()
	{
		var quick_login = $("#header").find("span#quick_login");
		if(quick_login)
		{
			var form = $("<form/>", { method: "post", action: "member.php" });
			form.append(
			    $("<input/>",
			    	{
						name: "action",
						type: "hidden",
						value: "do_login"
			        }
			     )
			);

			if(document.location.href)
			{
				form.append(
				    $("<input/>",
				    	{
							name: "url",
							type: "hidden",
							value: this.HTMLchars(document.location.href)
				        }
				     )
				);
			}

			form.append(
			    $("<input/>",
			    	{
						name: "quick_login",
						type: "hidden",
						value: "1"
			        }
			     )
			);

			form.append(
			    $("<input/>",
			    	{
					name: "quick_username",
					id: "quick_login_username",
					type: "text",
					value: lang.username,
					"class": "textbox",
					onfocus: "if(this.value == '"+lang.username+"') { this.value=''; }",
					onblur: "if(this.value == '') { this.value='"+lang.username+"'; }"
			        }
			     )
			).append("&nbsp;");

			form.append(
			    $("<input/>",
			    	{
					name: "quick_password",
					id: "quick_login_password",
					type: "password",
					value: lang.password,
					"class": "textbox",
					onfocus: "if(this.value == '"+lang.password+"') { this.value=''; }",
					onblur: "if(this.value == '') { this.value='"+lang.password+"'; }"
			        }
			     )
			).append("&nbsp;");

			form.append(
			    $("<input/>",
			    	{
						name: "submit",
						type: "submit",
						value: lang.login,
						"class": "button"
			        }
			     )
			);

			var span = $("<span/>", { "class": "remember_me" }).append(
			    $("<input/>",
			    	{
						name: "quick_remember",
						id: "quick_login_remember",
						type: "checkbox",
						value: "yes",
						"class": "checkbox"
			        }
			     )
			);

			$(span).append($("<label/>", { "for": "quick_login_remember" }).html(lang.remember_me));
			$(form).append(span);

			$(form).append(lang.lost_password+lang.register_url);

			quick_login.html('');
			quick_login.append(form);

			$("#quick_login_remember").attr("checked", "checked");
			$("#quick_login_username").focus();
		}

		return false;
	}
}

var expandables = {

	init: function()
	{
		var expanders = $("div.expcolimage img.expander");
		if(expanders.length)
		{
			expanders.each(function() {
        		var expander = $(this);
				if(expander.attr("id") == false)
				{
					return;
				}

				expander.click(function() {
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
				element.attr("src", element.attr("src").replace("collapse_collapsed.gif", "collapse.gif"))
									.element.attr("alt", "[-]")
									.element.attr("title", "[-]");
				this.saveCollapsed(controls);
			}
			else
			{
				expandedItem.toggle("fast");
				element.attr("src", element.attr("src").replace("collapse.gif", "collapse_collapsed.gif"))
									.element.attr("alt", "[+]")
									.element.attr("title", "[+]");
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

			$.each(saved, function(intIndex, objValue){
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

/**
 * jGrowl 1.2.12
 *
 * Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 *
 * Written by Stan Lemon <stosh1985@gmail.com>
 * Last updated: 2013.02.14
 */
(function($) {
	/** Compatibility holdover for 1.9 to check IE6 **/
	var $ie6 = (function(){
		return false === $.support.boxModel && $.support.objectAll && $.support.leadingWhitespace;
	})();

	/** jGrowl Wrapper - Establish a base jGrowl Container for compatibility with older releases. **/
	$.jGrowl = function( m , o ) {
		// To maintain compatibility with older version that only supported one instance we'll create the base container.
		if ( $('#jGrowl').size() == 0 )
			$('<div id="jGrowl"></div>').addClass( (o && o.position) ? o.position : $.jGrowl.defaults.position ).appendTo('body');

		// Create a notification on the container.
		$('#jGrowl').jGrowl(m,o);
	};


	/** Raise jGrowl Notification on a jGrowl Container **/
	$.fn.jGrowl = function( m , o ) {
		if ( $.isFunction(this.each) ) {
			var args = arguments;

			return this.each(function() {
				/** Create a jGrowl Instance on the Container if it does not exist **/
				if ( $(this).data('jGrowl.instance') == undefined ) {
					$(this).data('jGrowl.instance', $.extend( new $.fn.jGrowl(), { notifications: [], element: null, interval: null } ));
					$(this).data('jGrowl.instance').startup( this );
				}

				/** Optionally call jGrowl instance methods, or just raise a normal notification **/
				if ( $.isFunction($(this).data('jGrowl.instance')[m]) ) {
					$(this).data('jGrowl.instance')[m].apply( $(this).data('jGrowl.instance') , $.makeArray(args).slice(1) );
				} else {
					$(this).data('jGrowl.instance').create( m , o );
				}
			});
		};
	};

	$.extend( $.fn.jGrowl.prototype , {

		/** Default JGrowl Settings **/
		defaults: {
			pool:				0,
			header:				'',
			group:				'',
			sticky:				false,
			position: 			'top-right',
			glue:				'after',
			theme:				'default',
			themeState:			'highlight',
			corners:			'10px',
			check:				250,
			life:				3000,
			closeDuration: 		'normal',
			openDuration: 		'normal',
			easing: 			'swing',
			closer: 			true,
			closeTemplate: 		'&times;',
			closerTemplate: 	'<div>[ close all ]</div>',
			log:				function() {},
			beforeOpen:			function() {},
			afterOpen:			function() {},
			open:				function() {},
			beforeClose: 		function() {},
			close:				function() {},
			animateOpen: 		{
				opacity:	 'show'
			},
			animateClose: 		{
				opacity:	 'hide'
			}
		},

		notifications: [],

		/** jGrowl Container Node **/
		element:	 null,

		/** Interval Function **/
		interval:   null,

		/** Create a Notification **/
		create:	 function( message , o ) {
			var o = $.extend({}, this.defaults, o);

			/* To keep backward compatibility with 1.24 and earlier, honor 'speed' if the user has set it */
			if (typeof o.speed !== 'undefined') {
				o.openDuration = o.speed;
				o.closeDuration = o.speed;
			}

			this.notifications.push({ message: message , options: o });

			o.log.apply( this.element , [this.element,message,o] );
		},

		render:		 function( notification ) {
			var self = this;
			var message = notification.message;
			var o = notification.options;

			// Support for jQuery theme-states, if this is not used it displays a widget header
			o.themeState = (o.themeState == '') ? '' : 'ui-state-' + o.themeState;

			var notification = $('<div/>')
				.addClass('jGrowl-notification ' + o.themeState + ' ui-corner-all' + ((o.group != undefined && o.group != '') ? ' ' + o.group : ''))
				.append($('<div/>').addClass('jGrowl-close').html(o.closeTemplate))
				.append($('<div/>').addClass('jGrowl-header').html(o.header))
				.append($('<div/>').addClass('jGrowl-message').html(message))
				.data("jGrowl", o).addClass(o.theme).children('div.jGrowl-close').bind("click.jGrowl", function() {
					$(this).parent().trigger('jGrowl.beforeClose');
				})
				.parent();


			/** Notification Actions **/
			$(notification).bind("mouseover.jGrowl", function() {
				$('div.jGrowl-notification', self.element).data("jGrowl.pause", true);
			}).bind("mouseout.jGrowl", function() {
				$('div.jGrowl-notification', self.element).data("jGrowl.pause", false);
			}).bind('jGrowl.beforeOpen', function() {
				if ( o.beforeOpen.apply( notification , [notification,message,o,self.element] ) !== false ) {
					$(this).trigger('jGrowl.open');
				}
			}).bind('jGrowl.open', function() {
				if ( o.open.apply( notification , [notification,message,o,self.element] ) !== false ) {
					if ( o.glue == 'after' ) {
						$('div.jGrowl-notification:last', self.element).after(notification);
					} else {
						$('div.jGrowl-notification:first', self.element).before(notification);
					}

					$(this).animate(o.animateOpen, o.openDuration, o.easing, function() {
						// Fixes some anti-aliasing issues with IE filters.
						if ($.support.opacity === false)
							this.style.removeAttribute('filter');

						if ( $(this).data("jGrowl") !== null ) // Happens when a notification is closing before it's open.
							$(this).data("jGrowl").created = new Date();

						$(this).trigger('jGrowl.afterOpen');
					});
				}
			}).bind('jGrowl.afterOpen', function() {
				o.afterOpen.apply( notification , [notification,message,o,self.element] );
			}).bind('jGrowl.beforeClose', function() {
				if ( o.beforeClose.apply( notification , [notification,message,o,self.element] ) !== false )
					$(this).trigger('jGrowl.close');
			}).bind('jGrowl.close', function() {
				// Pause the notification, lest during the course of animation another close event gets called.
				$(this).data('jGrowl.pause', true);
				$(this).animate(o.animateClose, o.closeDuration, o.easing, function() {
					if ( $.isFunction(o.close) ) {
						if ( o.close.apply( notification , [notification,message,o,self.element] ) !== false )
							$(this).remove();
					} else {
						$(this).remove();
					}
				});
			}).trigger('jGrowl.beforeOpen');

			/** Optional Corners Plugin **/
			if ( o.corners != '' && $.fn.corner != undefined ) $(notification).corner( o.corners );

			/** Add a Global Closer if more than one notification exists **/
			if ( $('div.jGrowl-notification:parent', self.element).size() > 1 &&
				 $('div.jGrowl-closer', self.element).size() == 0 && this.defaults.closer !== false ) {
				$(this.defaults.closerTemplate).addClass('jGrowl-closer ' + this.defaults.themeState + ' ui-corner-all').addClass(this.defaults.theme)
					.appendTo(self.element).animate(this.defaults.animateOpen, this.defaults.speed, this.defaults.easing)
					.bind("click.jGrowl", function() {
						$(this).siblings().trigger("jGrowl.beforeClose");

						if ( $.isFunction( self.defaults.closer ) ) {
							self.defaults.closer.apply( $(this).parent()[0] , [$(this).parent()[0]] );
						}
					});
			};
		},

		/** Update the jGrowl Container, removing old jGrowl notifications **/
		update:	 function() {
			$(this.element).find('div.jGrowl-notification:parent').each( function() {
				if ( $(this).data("jGrowl") != undefined && $(this).data("jGrowl").created !== undefined &&
					 ($(this).data("jGrowl").created.getTime() + parseInt($(this).data("jGrowl").life))  < (new Date()).getTime() &&
					 $(this).data("jGrowl").sticky !== true &&
					 ($(this).data("jGrowl.pause") == undefined || $(this).data("jGrowl.pause") !== true) ) {

					// Pause the notification, lest during the course of animation another close event gets called.
					$(this).trigger('jGrowl.beforeClose');
				}
			});

			if ( this.notifications.length > 0 &&
				 (this.defaults.pool == 0 || $(this.element).find('div.jGrowl-notification:parent').size() < this.defaults.pool) )
				this.render( this.notifications.shift() );

			if ( $(this.element).find('div.jGrowl-notification:parent').size() < 2 ) {
				$(this.element).find('div.jGrowl-closer').animate(this.defaults.animateClose, this.defaults.speed, this.defaults.easing, function() {
					$(this).remove();
				});
			}
		},

		/** Setup the jGrowl Notification Container **/
		startup:	function(e) {
			this.element = $(e).addClass('jGrowl').append('<div class="jGrowl-notification"></div>');
			this.interval = setInterval( function() {
				$(e).data('jGrowl.instance').update();
			}, parseInt(this.defaults.check));

			if ($ie6) {
				$(this.element).addClass('ie6');
			}
		},

		/** Shutdown jGrowl, removing it and clearing the interval **/
		shutdown:   function() {
			$(this.element).removeClass('jGrowl')
				.find('div.jGrowl-notification').trigger('jGrowl.close')
				.parent().empty()
		},

		close:	 function() {
			$(this.element).find('div.jGrowl-notification').each(function(){
				$(this).trigger('jGrowl.beforeClose');
			});
		}
	});

	/** Reference the Defaults Object for compatibility with older versions of jGrowl **/
	$.jGrowl.defaults = $.fn.jGrowl.prototype.defaults;

})(jQuery);

/* Lang this! */
var lang = {

};

MyBB.init();