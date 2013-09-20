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

/*
    A simple jQuery modal (http://github.com/kylefox/jquery-modal)
    Version 0.5.4
*/
(function($) {

  var current = null;

  $.modal = function(el, options) {
    $.modal.close(); // Close any open modals.
    var remove, target;
    this.$body = $('body');
    this.options = $.extend({}, $.modal.defaults, options);
    this.options.doFade = !isNaN(parseInt(this.options.fadeDuration, 10));
    if (el.is('a')) {
      target = el.attr('href');
      //Select element by id from href
      if (/^#/.test(target)) {
        this.$elm = $(target);
        if (this.$elm.length !== 1) return null;
        this.open();
      //AJAX
      } else {
        this.$elm = $('<div>');
        this.$body.append(this.$elm);
        remove = function(event, modal) { modal.elm.remove(); };
        this.showSpinner();
        el.trigger($.modal.AJAX_SEND);
        $.get(target).done(function(html) {
          if (!current) return;
          el.trigger($.modal.AJAX_SUCCESS);
          current.$elm.empty().append(html).on($.modal.CLOSE, remove);
          current.hideSpinner();
          current.open();
          el.trigger($.modal.AJAX_COMPLETE);
        }).fail(function() {
          el.trigger($.modal.AJAX_FAIL);
          current.hideSpinner();
          el.trigger($.modal.AJAX_COMPLETE);
        });
      }
    } else {
      this.$elm = el;
      this.open();
    }
  };

  $.modal.prototype = {
    constructor: $.modal,

    open: function() {
      var m = this;
      if(this.options.doFade) {
        this.block();
        setTimeout(function() {
          m.show();
        }, this.options.fadeDuration * this.options.fadeDelay);
      } else {
        this.block();
        this.show();
      }
      if (this.options.escapeClose) {
        $(document).on('keydown.modal', function(event) {
          if (event.which == 27) $.modal.close();
        });
      }
      if (this.options.clickClose) this.blocker.click($.modal.close);
    },

    close: function() {
      this.unblock();
      this.hide();
      $(document).off('keydown.modal');
    },

    block: function() {
      var initialOpacity = this.options.doFade ? 0 : this.options.opacity;
      this.$elm.trigger($.modal.BEFORE_BLOCK, [this._ctx()]);
      this.blocker = $('<div class="jquery-modal blocker"></div>').css({
        top: 0, right: 0, bottom: 0, left: 0,
        width: "100%", height: "100%",
        position: "fixed",
        zIndex: this.options.zIndex,
        background: this.options.overlay,
        opacity: initialOpacity
      });
      this.$body.append(this.blocker);
      if(this.options.doFade) {
        this.blocker.animate({opacity: this.options.opacity}, this.options.fadeDuration);
      }
      this.$elm.trigger($.modal.BLOCK, [this._ctx()]);
    },

    unblock: function() {
      if(this.options.doFade) {
        this.blocker.fadeOut(this.options.fadeDuration, function() {
          this.remove();
        });
      } else {
        this.blocker.remove();
      }
    },

    show: function() {
      this.$elm.trigger($.modal.BEFORE_OPEN, [this._ctx()]);
      if (this.options.showClose) {
        this.closeButton = $('<a href="#close-modal" rel="modal:close" class="close-modal">' + this.options.closeText + '</a>');
        this.$elm.append(this.closeButton);
      }
      this.$elm.addClass(this.options.modalClass + ' current');
      this.center();
      if(this.options.doFade) {
        this.$elm.fadeIn(this.options.fadeDuration);
      } else {
        this.$elm.show();
      }
      this.$elm.trigger($.modal.OPEN, [this._ctx()]);
    },

    hide: function() {
      this.$elm.trigger($.modal.BEFORE_CLOSE, [this._ctx()]);
      if (this.closeButton) this.closeButton.remove();
      this.$elm.removeClass('current');

      if(this.options.doFade) {
        this.$elm.fadeOut(this.options.fadeDuration);
      } else {
        this.$elm.hide();
      }
      this.$elm.trigger($.modal.CLOSE, [this._ctx()]);
    },

    showSpinner: function() {
      if (!this.options.showSpinner) return;
      this.spinner = this.spinner || $('<div class="' + this.options.modalClass + '-spinner"></div>')
        .append(this.options.spinnerHtml);
      this.$body.append(this.spinner);
      this.spinner.show();
    },

    hideSpinner: function() {
      if (this.spinner) this.spinner.remove();
    },

    center: function() {
      this.$elm.css({
        position: 'fixed',
        top: "50%",
        left: "50%",
        marginTop: - (this.$elm.outerHeight() / 2),
        marginLeft: - (this.$elm.outerWidth() / 2),
        zIndex: this.options.zIndex + 1
      });
    },

    //Return context for custom events
    _ctx: function() {
      return { elm: this.$elm, blocker: this.blocker, options: this.options };
    }
  };

  //resize is alias for center for now
  $.modal.prototype.resize = $.modal.prototype.center;

  $.modal.close = function(event) {
    if (!current) return;
    if (event) event.preventDefault();
    current.close();
    var that = current.$elm;
    current = null;
    return that;
  };

  $.modal.resize = function() {
    if (!current) return;
    current.resize();
  };

  $.modal.defaults = {
    overlay: "#000",
    opacity: 0.75,
    zIndex: 1,
    escapeClose: true,
    clickClose: true,
    closeText: 'Close',
    modalClass: "modal",
    spinnerHtml: null,
    showSpinner: true,
    showClose: true,
    fadeDuration: null,   // Number of milliseconds the fade animation takes.
    fadeDelay: 1.0        // Point during the overlay's fade-in that the modal begins to fade in (.5 = 50%, 1.5 = 150%, etc.)
  };

  // Event constants
  $.modal.BEFORE_BLOCK = 'modal:before-block';
  $.modal.BLOCK = 'modal:block';
  $.modal.BEFORE_OPEN = 'modal:before-open';
  $.modal.OPEN = 'modal:open';
  $.modal.BEFORE_CLOSE = 'modal:before-close';
  $.modal.CLOSE = 'modal:close';
  $.modal.AJAX_SEND = 'modal:ajax:send';
  $.modal.AJAX_SUCCESS = 'modal:ajax:success';
  $.modal.AJAX_FAIL = 'modal:ajax:fail';
  $.modal.AJAX_COMPLETE = 'modal:ajax:complete';

  $.fn.modal = function(options){
    if (this.length === 1) {
      current = new $.modal(this, options);
    }
    return this;
  };

  // Automatically bind links with rel="modal:close" to, well, close the modal.
  $(document).on('click.modal', 'a[rel="modal:close"]', $.modal.close);
  $(document).on('click.modal', 'a[rel="modal:open"]', function(event) {
    event.preventDefault();
    $(this).modal();
  });
})(jQuery);

/*
	Conversion of 1.6.x popup_menu.js
*/
(function($){
	var currentPopup = '';
	var PopupMenu = function(el)
	{
		var el = $(el);
		var popup = this;
		var popup_menu = $("#" + el.attr('id') + "_popup");

		this.open = function(e)
		{
			event.preventDefault();

			if(currentPopup != '')
			{
				popup.close();
			}

			var offset = el.offset();
			offset.top += el.outerHeight();

			popup_menu.css({
				display: 'block',
				position: 'absolute',
				top: offset.top,
				left: offset.left
			});

			currentPopup = popup_menu;
			$(document).mousedown(popup.close);
		}
		this.close = function(e)
		{
			if(e)
			{
				if($(e.target).parents().index(currentPopup) != -1)
				{
					return false;
				}
			}

			$(currentPopup).css({ display: 'none' });
			currentPopup = '';
		}
	}
	$.fn.popupMenu = function(el)
	{
		return this.each(function()
		{
			alert($(this).attr("id"));
			var popup = new PopupMenu(this);
			$(this).click(popup.open);
		});
	}
})(jQuery);