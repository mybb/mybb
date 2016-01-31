/**
 * jGrowl 1.4.0
 *
 * Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 *
 * Written by Stan Lemon <stosh1985@gmail.com>
 * Last updated: 2014.04.18
 */
(function($) {
	/** jGrowl Wrapper - Establish a base jGrowl Container for compatibility with older releases. **/
	$.jGrowl = function( m , o ) {
		// To maintain compatibility with older version that only supported one instance we'll create the base container.
		if ( $('#jGrowl').size() === 0 )
			$('<div id="jGrowl"></div>').addClass( (o && o.position) ? o.position : $.jGrowl.defaults.position ).appendTo('body');

		// Create a notification on the container.
		$('#jGrowl').jGrowl(m,o);
	};


	/** Raise jGrowl Notification on a jGrowl Container **/
	$.fn.jGrowl = function( m , o ) {
		// Short hand for passing in just an object to this method
		if ( o === undefined && $.isPlainObject(m) ) {
			o = m;
			m = o.message;
		}

		if ( $.isFunction(this.each) ) {
			var args = arguments;

			return this.each(function() {
				/** Create a jGrowl Instance on the Container if it does not exist **/
				if ( $(this).data('jGrowl.instance') === undefined ) {
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
		}
	};

	$.extend( $.fn.jGrowl.prototype , {

		/** Default JGrowl Settings **/
		defaults: {
			pool:				0,
			header:				'',
			group:				'',
			sticky:				false,
			position:			'top-right',
			glue:				'after',
			theme:				'default',
			themeState:			'highlight',
			corners:			'10px',
			check:				250,
			life:				3000,
			closeDuration:		'normal',
			openDuration:		'normal',
			easing:				'swing',
			closer:				true,
			closeTemplate:		'&times;',
			closerTemplate:		'<div>[ close all ]</div>',
			log:				function() {},
			beforeOpen:			function() {},
			afterOpen:			function() {},
			open:				function() {},
			beforeClose:		function() {},
			close:				function() {},
			click:				function() {},
			animateOpen:		{
				opacity:		'show'
			},
			animateClose:		{
				opacity:		'hide'
			}
		},

		notifications: [],

		/** jGrowl Container Node **/
		element:				null,

		/** Interval Function **/
		interval:				null,

		/** Create a Notification **/
		create: function( message , options ) {
			var o = $.extend({}, this.defaults, options);

			/* To keep backward compatibility with 1.24 and earlier, honor 'speed' if the user has set it */
			if (typeof o.speed !== 'undefined') {
				o.openDuration = o.speed;
				o.closeDuration = o.speed;
			}

			this.notifications.push({ message: message , options: o });

			o.log.apply( this.element , [this.element,message,o] );
		},

		render: function( n ) {
			var self = this;
			var message = n.message;
			var o = n.options;

			// Support for jQuery theme-states, if this is not used it displays a widget header
			o.themeState = (o.themeState === '') ? '' : 'ui-state-' + o.themeState;

			var notification = $('<div/>')
				.addClass('jGrowl-notification alert ' + o.themeState + ' ui-corner-all' + ((o.group !== undefined && o.group !== '') ? ' ' + o.group : ''))
				.append($('<button/>').addClass('jGrowl-close').html(o.closeTemplate))
				.append($('<div/>').addClass('jGrowl-header').html(o.header))
				.append($('<div/>').addClass('jGrowl-message').html(message))
				.data("jGrowl", o).addClass(o.theme).children('.jGrowl-close').bind("click.jGrowl", function() {
					$(this).parent().trigger('jGrowl.beforeClose');
					return false;
				})
				.parent();


			/** Notification Actions **/
			$(notification).bind("mouseover.jGrowl", function() {
				$('.jGrowl-notification', self.element).data("jGrowl.pause", true);
			}).bind("mouseout.jGrowl", function() {
				$('.jGrowl-notification', self.element).data("jGrowl.pause", false);
			}).bind('jGrowl.beforeOpen', function() {
				if ( o.beforeOpen.apply( notification , [notification,message,o,self.element] ) !== false ) {
					$(this).trigger('jGrowl.open');
				}
			}).bind('jGrowl.open', function() {
				if ( o.open.apply( notification , [notification,message,o,self.element] ) !== false ) {
					if ( o.glue == 'after' ) {
						$('.jGrowl-notification:last', self.element).after(notification);
					} else {
						$('.jGrowl-notification:first', self.element).before(notification);
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
			}).bind('click', function() {
				o.click.apply( notification, [notification.message,o,self.element] );
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
			if ( o.corners !== '' && $.fn.corner !== undefined ) $(notification).corner( o.corners );

			/** Add a Global Closer if more than one notification exists **/
			if ($('.jGrowl-notification:parent', self.element).size() > 1 &&
				$('.jGrowl-closer', self.element).size() === 0 && this.defaults.closer !== false ) {
				$(this.defaults.closerTemplate).addClass('jGrowl-closer ' + this.defaults.themeState + ' ui-corner-all').addClass(this.defaults.theme)
					.appendTo(self.element).animate(this.defaults.animateOpen, this.defaults.speed, this.defaults.easing)
					.bind("click.jGrowl", function() {
						$(this).siblings().trigger("jGrowl.beforeClose");

						if ( $.isFunction( self.defaults.closer ) ) {
							self.defaults.closer.apply( $(this).parent()[0] , [$(this).parent()[0]] );
						}
					});
			}
		},

		/** Update the jGrowl Container, removing old jGrowl notifications **/
		update: function() {
			$(this.element).find('.jGrowl-notification:parent').each( function() {
				if ($(this).data("jGrowl") !== undefined && $(this).data("jGrowl").created !== undefined &&
					($(this).data("jGrowl").created.getTime() + parseInt($(this).data("jGrowl").life, 10))  < (new Date()).getTime() &&
					$(this).data("jGrowl").sticky !== true &&
					($(this).data("jGrowl.pause") === undefined || $(this).data("jGrowl.pause") !== true) ) {

					// Pause the notification, lest during the course of animation another close event gets called.
					$(this).trigger('jGrowl.beforeClose');
				}
			});

			if (this.notifications.length > 0 &&
				(this.defaults.pool === 0 || $(this.element).find('.jGrowl-notification:parent').size() < this.defaults.pool) )
				this.render( this.notifications.shift() );

			if ($(this.element).find('.jGrowl-notification:parent').size() < 2 ) {
				$(this.element).find('.jGrowl-closer').animate(this.defaults.animateClose, this.defaults.speed, this.defaults.easing, function() {
					$(this).remove();
				});
			}
		},

		/** Setup the jGrowl Notification Container **/
		startup: function(e) {
			this.element = $(e).addClass('jGrowl').append('<div class="jGrowl-notification"></div>');
			this.interval = setInterval( function() {
				$(e).data('jGrowl.instance').update();
			}, parseInt(this.defaults.check, 10));
		},

		/** Shutdown jGrowl, removing it and clearing the interval **/
		shutdown: function() {
			$(this.element).removeClass('jGrowl')
				.find('.jGrowl-notification').trigger('jGrowl.close')
				.parent().empty()
			;

			clearInterval(this.interval);
		},

		close: function() {
			$(this.element).find('.jGrowl-notification').each(function(){
				$(this).trigger('jGrowl.beforeClose');
			});
		}
	});

	/** Reference the Defaults Object for compatibility with older versions of jGrowl **/
	$.jGrowl.defaults = $.fn.jGrowl.prototype.defaults;

})(jQuery);

/*
    A simple jQuery modal (http://github.com/kylefox/jquery-modal)
    Version 0.5.5
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
	  // Deletes the element (multi-modal feature: e.g. when you click on multiple report buttons, you will want to see different content for each)
	  if (!this.options.keepelement)
		this.$elm.remove();
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
          $(this).remove();
        });
      } else {
        this.blocker.remove();
      }
    },

    show: function() {
      this.$elm.trigger($.modal.BEFORE_OPEN, [this._ctx()]);
      if (this.options.showClose) {
        this.closeButton = $('<a href="#close-modal" rel="modal:close" class="close-modal ' + this.options.closeClass + '">' + this.options.closeText + '</a>');
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

  // Returns if there currently is an active modal
  $.modal.isActive = function () {
    return current ? true : false;
  }

  $.modal.defaults = {
    overlay: "#000",
    opacity: 0.75,
    zIndex: 1,
    escapeClose: true,
    clickClose: true,
    closeText: 'Close',
    closeClass: '',
    modalClass: "modal",
    spinnerHtml: null,
    showSpinner: true,
    showClose: true,
    fadeDuration: null,   // Number of milliseconds the fade animation takes.
    fadeDelay: 1.0,        // Point during the overlay's fade-in that the modal begins to fade in (.5 = 50%, 1.5 = 150%, etc.)
	keepelement: false // Added by Pirata Nervo: this allows modal elements to be kept on closing when the HTML is present in the same template as the jQuery code (e.g. login modal)
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
	var current_popup = '';
	var PopupMenu = function(el, close_in_popupmenu)
	{
		var el = $(el);
		var popup = this;
		var popup_menu = $("#" + el.attr('id') + "_popup");
		if(typeof close_in_popupmenu == 'undefined')
		{
			var close_in_popupmenu = true;
		}
		// Opening Popup
		this.open = function(e)
		{
			e.preventDefault();

			if(popup_menu.is(':visible'))
			{
				popup.close();
				return;
			}

			// Setup popup menu
			var offset = el.offset();
			offset.top += el.outerHeight();

			// We only adjust if it goes out of the page (?)
			if((el.offset().left + popup_menu.outerWidth()) > $(window).width())
				var adjust = popup_menu.outerWidth() - el.outerWidth();
			else
				var adjust = 0;

			popup_menu.css({
				position: 'absolute',
				top: offset.top,
				left: offset.left-adjust
			});

			popup_menu.show();

			// Closes the popup if we click outside the button (this doesn't seem to work properly - couldn't find any solutions that actually did - if we click the first item on the menu)
			// Credits: http://stackoverflow.com/questions/1160880/detect-click-outside-element
			$('body, .popup_item').bind('click.close_popup', function(e) {
				if(close_in_popupmenu)
				{
					if($(e.target).closest("#" + el.attr('id')).length == 0) {
						popup.close();
					}
				}
				else
				{
					if($(e.target).closest("#" + el.attr('id')).length == 0 && $(e.target).closest("#" + el.attr('id') + '_popup').length == 0) {
						popup.close();
					}
				}
			});
		}
		this.close = function(e)
		{
			popup_menu.hide();
		}
	}
	$.fn.popupMenu = function(close_in_popupmenu)
	{
		return this.each(function()
		{
			var popup = new PopupMenu(this, close_in_popupmenu);
			$(this).click(popup.open);
		});
	}
})(jQuery);

/*! jQuery-Impromptu - v6.0.0 - 2014-12-27
* http://trentrichardson.com/Impromptu
* Copyright (c) 2014 Trent Richardson; Licensed MIT */
(function(root, factory) {
	if (typeof define === 'function' && define.amd) {
		define(['jquery'], factory);
	} else {
		factory(root.jQuery);
	}
}(this, function($) {
	'use strict';

	// ########################################################################
	// Base object
	// ########################################################################

	/**
	* Imp - Impromptu object - passing no params will not open, only return the instance
	* @param message String/Object - String of html or Object of states
	* @param options Object - Options to set the prompt
	* @return Imp - the instance of this Impromptu object
	*/
	var Imp = function(message, options){
		var t = this;
		t.id = Imp.count++;

		Imp.lifo.push(t);

		if(message){
			t.open(message, options);
		}
		return t;
	};

	// ########################################################################
	// static properties and methods
	// ########################################################################

	/**
	* defaults - the default options
	*/
	Imp.defaults = {
		prefix:'jqi',
		classes: {
			box: '',
			fade: '',
			prompt: '',
			form: '',
			close: '',
			title: '',
			message: '',
			buttons: '',
			button: '',
			defaultButton: ''
		},
		title: '',
		closeText: '&times;',
		buttons: {
			Ok: true
		},
		loaded: function(e){},
		submit: function(e,v,m,f){},
		close: function(e,v,m,f){},
		statechanging: function(e, from, to){},
		statechanged: function(e, to){},
		opacity: 0.6,
		zIndex: 999,
		overlayspeed: 'slow',
		promptspeed: 'fast',
		show: 'fadeIn',
		hide: 'fadeOut',
		focus: 0,
		defaultButton: 0,
		useiframe: false,
		top: '15%',
		position: {
			container: null,
			x: null,
			y: null,
			arrow: null,
			width: null
		},
		persistent: true,
		timeout: 0,
		states: {},
		state: {
			name: null,
			title: '',
			html: '',
			buttons: {
				Ok: true
			},
			focus: 0,
			defaultButton: 0,
			position: {
				container: null,
				x: null,
				y: null,
				arrow: null,
				width: null
			},
			submit: function(e,v,m,f){
				return true;
			}
		}
	};

	/**
	* setDefaults - Sets the default options
	* @param o Object - Options to set as defaults
	* @return void
	*/
	Imp.setDefaults = function(o) {
		Imp.defaults = $.extend({}, Imp.defaults, o);
	};

	/**
	* setStateDefaults - Sets the default options for a state
	* @param o Object - Options to set as defaults
	* @return void
	*/
	Imp.setStateDefaults = function(o) {
		Imp.defaults.state = $.extend({}, Imp.defaults.state, o);
	};

	/**
	* @var Int - A counter used to provide a unique ID for new prompts
	*/
	Imp.count = 0;

	/**
	* @var Array - An array of Impromptu intances in a LIFO queue (last in first out)
	*/
	Imp.lifo = [];

	/**
	* getLast - get the last element from the queue (doesn't pop, just returns)
	* @return Imp - the instance of this Impromptu object or false if queue is empty
	*/
	Imp.getLast = function(){
		var l = Imp.lifo.length;
		return (l > 0)? Imp.lifo[l-1] : false;
	};

	/**
	* removeFromStack - remove an element from the lifo stack by its id
	* @param id int - id of the instance to remove
	* @return api - The api of the element removed from the stack or void
	*/
	Imp.removeFromStack = function(id){
		for(var i=Imp.lifo.length-1; i>=0; i--){
			if(Imp.lifo[i].id === id){
				return Imp.lifo.splice(i,1)[0];
			}
		}
	};

	// ########################################################################
	// extend our object instance properties and methods
	// ########################################################################
	Imp.prototype = {

		/**
		* @var Int - A unique id, simply an autoincremented number
		*/
		id: null,

		/**
		* open - Opens the prompt
		* @param message String/Object - String of html or Object of states
		* @param options Object - Options to set the prompt
		* @return Imp - the instance of this Impromptu object
		*/
		open: function(message, options) {
			var t = this;

			t.options = $.extend({},Imp.defaults,options);

			// Be sure any previous timeouts are destroyed
			if(t.timeout){
				clearTimeout(t.timeout);
			}
			t.timeout = false;

			var opts = t.options,
				$body = $(document.body),
				$window = $(window);

			//build the box and fade
			var msgbox = '<div class="'+ opts.prefix +'box '+ opts.classes.box +'">';
			if(opts.useiframe && ($('object, applet').length > 0)) {
				msgbox += '<iframe src="javascript:false;" style="display:block;position:absolute;z-index:-1;" class="'+ opts.prefix +'fade '+ opts.classes.fade +'"></iframe>';
			} else {
				msgbox += '<div class="'+ opts.prefix +'fade '+ opts.classes.fade +'"></div>';
			}
			msgbox += '<div class="'+ opts.prefix +' '+ opts.classes.prompt +'">'+
						'<form action="javascript:false;" onsubmit="return false;" class="'+ opts.prefix +'form '+ opts.classes.form +'">'+
							'<div class="'+ opts.prefix +'close '+ opts.classes.close +'">'+ opts.closeText +'</div>'+
							'<div class="'+ opts.prefix +'states"></div>'+
						'</form>'+
					'</div>'+
				'</div>';

			t.jqib = $(msgbox).appendTo($body);
			t.jqi = t.jqib.children('.'+ opts.prefix);
			t.jqif = t.jqib.children('.'+ opts.prefix +'fade');

			//if a string was passed, convert to a single state
			if(message.constructor === String){
				message = {
					state0: {
						title: opts.title,
						html: message,
						buttons: opts.buttons,
						position: opts.position,
						focus: opts.focus,
						defaultButton: opts.defaultButton,
						submit: opts.submit
					}
				};
			}

			//build the states
			t.options.states = {};
			var k,v;
			for(k in message){
				v = $.extend({},Imp.defaults.state,{name:k},message[k]);
				t.addState(v.name, v);

				if(t.currentStateName === ''){
					t.currentStateName = v.name;
				}
			}

			//Events
			t.jqi.on('click', '.'+ opts.prefix +'buttons button', function(e){
				var $t = $(this),
					$state = $t.parents('.'+ opts.prefix +'state'),
					stateobj = t.options.states[$state.data('jqi-name')],
					msg = $state.children('.'+ opts.prefix +'message'),
					clicked = stateobj.buttons[$t.text()] || stateobj.buttons[$t.html()],
					forminputs = {};

				// if for some reason we couldn't get the value
				if(clicked === undefined){
					for(var i in stateobj.buttons){
						if(stateobj.buttons[i].title === $t.text() || stateobj.buttons[i].title === $t.html()){
							clicked = stateobj.buttons[i].value;
						}
					}
				}

				//collect all form element values from all states.
				$.each(t.jqi.children('form').serializeArray(),function(i,obj){
					if (forminputs[obj.name] === undefined) {
						forminputs[obj.name] = obj.value;
					} else if (typeof forminputs[obj.name] === Array || typeof forminputs[obj.name] === 'object') {
						forminputs[obj.name].push(obj.value);
					} else {
						forminputs[obj.name] = [forminputs[obj.name],obj.value];
					}
				});

				// trigger an event
				var promptsubmite = new $.Event('impromptu:submit');
				promptsubmite.stateName = stateobj.name;
				promptsubmite.state = $state;
				$state.trigger(promptsubmite, [clicked, msg, forminputs]);

				if(!promptsubmite.isDefaultPrevented()){
					t.close(true, clicked,msg,forminputs);
				}
			});

			// if the fade is clicked blink the prompt
			var fadeClicked = function(){
				if(opts.persistent){
					var offset = (opts.top.toString().indexOf('%') >= 0? ($window.height()*(parseInt(opts.top,10)/100)) : parseInt(opts.top,10)),
						top = parseInt(t.jqi.css('top').replace('px',''),10) - offset;

					//$window.scrollTop(top);
					$('html,body').animate({ scrollTop: top }, 'fast', function(){
						var i = 0;
						t.jqib.addClass(opts.prefix +'warning');
						var intervalid = setInterval(function(){
							t.jqib.toggleClass(opts.prefix +'warning');
							if(i++ > 1){
								clearInterval(intervalid);
								t.jqib.removeClass(opts.prefix +'warning');
							}
						}, 100);
					});
				}
				else {
					t.close(true);
				}
			};

			// listen for esc or tab keys
			var keyDownEventHandler = function(e){
				var key = (window.event) ? event.keyCode : e.keyCode;

				//escape key closes
				if(key === 27) {
					fadeClicked();
				}

				//enter key pressed trigger the default button if its not on it, ignore if it is a textarea
				if(key === 13){
					var $defBtn = t.getCurrentState().find('.'+ opts.prefix +'defaultbutton');
					var $tgt = $(e.target);

					if($tgt.is('textarea,.'+opts.prefix+'button') === false && $defBtn.length > 0){
						e.preventDefault();
						$defBtn.click();
					}
				}

				//constrain tabs, tabs should iterate through the state and not leave
				if (key === 9){
					var $inputels = $('input,select,textarea,button',t.getCurrentState());
					var fwd = !e.shiftKey && e.target === $inputels[$inputels.length-1];
					var back = e.shiftKey && e.target === $inputels[0];
					if (fwd || back) {
						setTimeout(function(){
							if (!$inputels){
								return;
							}
							var el = $inputels[back===true ? $inputels.length-1 : 0];

							if (el){
								el.focus();
							}
						},10);
						return false;
					}
				}
			};

			t.position();
			t.style();

			// store copy of the window resize function for interal use only
			t._windowResize = function(e){
				t.position(e);
			};
			$window.resize({ animate: false }, t._windowResize);

			t.jqif.click(fadeClicked);
			t.jqi.find('.'+ opts.prefix +'close').click(function(){ t.close(); });
			t.jqib.on("keydown",keyDownEventHandler)
						.on('impromptu:loaded', opts.loaded)
						.on('impromptu:close', opts.close)
						.on('impromptu:statechanging', opts.statechanging)
						.on('impromptu:statechanged', opts.statechanged);

			// Show it
			t.jqif[opts.show](opts.overlayspeed);
			t.jqi[opts.show](opts.promptspeed, function(){

				var $firstState = t.jqi.find('.'+ opts.prefix +'states .'+ opts.prefix +'state').eq(0);
				t.goToState($firstState.data('jqi-name'));

				t.jqib.trigger('impromptu:loaded');
			});

			// Timeout
			if(opts.timeout > 0){
				t.timeout = setTimeout(function(){ t.close(true); },opts.timeout);
			}

			return t;
		},

		/**
		* close - Closes the prompt
		* @param callback Function - called when the transition is complete
		* @param clicked String - value of the button clicked (only used internally)
		* @param msg jQuery - The state message body (only used internally)
		* @param forvals Object - key/value pairs of all form field names and values (only used internally)
		* @return Imp - the instance of this Impromptu object
		*/
		close: function(callCallback, clicked, msg, formvals){
			var t = this;
			Imp.removeFromStack(t.id);

			if(t.timeout){
				clearTimeout(t.timeout);
				t.timeout = false;
			}

			if(t.jqib){
				t.jqib[t.options.hide]('fast',function(){
					
					t.jqib.trigger('impromptu:close', [clicked,msg,formvals]);
					
					t.jqib.remove();
					
					$(window).off('resize', t._windowResize);

					if(typeof callCallback === 'function'){
						callCallback();
					}
				});
			}
			t.currentStateName = "";

			return t;
		},

		/**
		* addState - Injects a state into the prompt
		* @param statename String - Name of the state
		* @param stateobj Object - options for the state
		* @param afterState String - selector of the state to insert after
		* @return jQuery - the newly created state
		*/
		addState: function(statename, stateobj, afterState) {
			var t = this,
				state = '',
				$state = null,
				arrow = '',
				title = '',
				opts = t.options,
				$jqistates = $('.'+ opts.prefix +'states'),
				buttons = [],
				showHtml,defbtn,k,v,l,i=0;

			stateobj = $.extend({},Imp.defaults.state, {name:statename}, stateobj);

			if(stateobj.position.arrow !== null){
				arrow = '<div class="'+ opts.prefix + 'arrow '+ opts.prefix + 'arrow'+ stateobj.position.arrow +'"></div>';
			}
			if(stateobj.title && stateobj.title !== ''){
				title = '<div class="lead '+ opts.prefix + 'title '+ opts.classes.title +'">'+  stateobj.title +'</div>';
			}

			showHtml = stateobj.html;
			if (typeof stateobj.html === 'function') {
				showHtml = 'Error: html function must return text';
			}

			state += '<div class="'+ opts.prefix + 'state" data-jqi-name="'+ statename +'" style="display:none;">'+
						arrow + title +
						'<div class="'+ opts.prefix +'message '+ opts.classes.message +'">' + showHtml +'</div>'+
						'<div class="'+ opts.prefix +'buttons '+ opts.classes.buttons +'"'+ ($.isEmptyObject(stateobj.buttons)? 'style="display:none;"':'') +'>';

			// state buttons may be in object or array, lets convert objects to arrays
			if($.isArray(stateobj.buttons)){
				buttons = stateobj.buttons;
			}
			else if($.isPlainObject(stateobj.buttons)){
				for(k in stateobj.buttons){
					if(stateobj.buttons.hasOwnProperty(k)){
						buttons.push({ title: k, value: stateobj.buttons[k] });
					}
				}
			}

			// iterate over each button and create them
			for(i=0, l=buttons.length; i<l; i++){
				v = buttons[i],
				defbtn = stateobj.focus === i || (isNaN(stateobj.focus) && stateobj.defaultButton === i) ? (opts.prefix + 'defaultbutton ' + opts.classes.defaultButton) : '';

				state += '<button class="'+ opts.classes.button +' '+ opts.prefix + 'button '+ defbtn;

				if(typeof v.classes !== "undefined"){
					state += ' '+ ($.isArray(v.classes)? v.classes.join(' ') : v.classes) + ' ';
				}

				state += '" name="' + opts.prefix + '_' + statename + '_button' + v.title.replace(/[^a-z0-9]+/gi,'') + '" value="' + v.value + '">' + v.title + '</button>';
			}
			
			state += '</div></div>';

			$state = $(state);

			$state.on('impromptu:submit', stateobj.submit);

			if(afterState !== undefined){
				$jqistates.find('[data-jqi-name="'+afterState+'"]').after($state);
			}
			else{
				$jqistates.append($state);
			}

			t.options.states[statename] = stateobj;

			return $state;
		},

		/**
		* removeState - Removes a state from the prompt
		* @param state String - Name of the state
		* @param newState String - Name of the state to transition to
		* @return Boolean - returns true on success, false on failure
		*/
		removeState: function(state, newState) {
			var t = this,
				$state = t.getState(state),
				rm = function(){ $state.remove(); };

			if($state.length === 0){
				return false;
			}

			// transition away from it before deleting
			if($state.css('display') !== 'none'){
				if(newState !== undefined && t.getState(newState).length > 0){
					t.goToState(newState, false, rm);
				}
				else if($state.next().length > 0){
					t.nextState(rm);
				}
				else if($state.prev().length > 0){
					t.prevState(rm);
				}
				else{
					t.close();
				}
			}
			else{
				$state.slideUp('slow', rm);
			}

			return true;
		},

		/**
		* getApi - Get the api, so you can extract it from $.prompt stack
		* @return jQuery - the prompt
		*/
		getApi: function() {
			return this;
		},

		/**
		* getBox - Get the box containing fade and prompt
		* @return jQuery - the prompt
		*/
		getBox: function() {
			return this.jqib;
		},

		/**
		* getPrompt - Get the prompt
		* @return jQuery - the prompt
		*/
		getPrompt: function() {
			return this.jqi;
		},

		/**
		* getState - Get the state by its name
		* @param statename String - Name of the state
		* @return jQuery - the state
		*/
		getState: function(statename) {
			return this.jqi.find('[data-jqi-name="'+ statename +'"]');
		},

		/**
		* getCurrentState - Get the current visible state
		* @return jQuery - the current visible state
		*/
		getCurrentState: function() {
			return this.getState(this.getCurrentStateName());
		},

		/**
		* getCurrentStateName - Get the name of the current visible state/substate
		* @return String - the current visible state's name
		*/
		getCurrentStateName: function() {
			return this.currentStateName;
		},

		/**
		* position - Repositions the prompt (Used internally)
		* @return void
		*/
		position: function(e){
			var t = this,
				restoreFx = $.fx.off,
				$state = t.getCurrentState(),
				stateObj = t.options.states[$state.data('jqi-name')],
				pos = stateObj? stateObj.position : undefined,
				$window = $(window),
				bodyHeight = document.body.scrollHeight, //$(document.body).outerHeight(true),
				windowHeight = $(window).height(),
				documentHeight = $(document).height(),
				height = bodyHeight > windowHeight ? bodyHeight : windowHeight,
				top = parseInt($window.scrollTop(),10) + (t.options.top.toString().indexOf('%') >= 0?
						(windowHeight*(parseInt(t.options.top,10)/100)) : parseInt(t.options.top,10));

			// when resizing the window turn off animation
			if(e !== undefined && e.data.animate === false){
				$.fx.off = true;
			}

			t.jqib.css({
				position: "absolute",
				height: height,
				width: "100%",
				top: 0,
				left: 0,
				right: 0,
				bottom: 0
			});
			t.jqif.css({
				position: "fixed",
				height: height,
				width: "100%",
				top: 0,
				left: 0,
				right: 0,
				bottom: 0
			});

			// tour positioning
			if(pos && pos.container){
				var offset = $(pos.container).offset();

				if($.isPlainObject(offset) && offset.top !== undefined){
					t.jqi.css({
						position: "absolute"
					});
					t.jqi.animate({
						top: offset.top + pos.y,
						left: offset.left + pos.x,
						marginLeft: 0,
						width: (pos.width !== undefined)? pos.width : null
					});
					top = (offset.top + pos.y) - (t.options.top.toString().indexOf('%') >= 0? (windowHeight*(parseInt(t.options.top,10)/100)) : parseInt(t.options.top,10));
					$('html,body').animate({ scrollTop: top }, 'slow', 'swing', function(){});
				}
			}
			// custom state width animation
			else if(pos && pos.width){
				t.jqi.css({
						position: "absolute",
						left: '50%'
					});
				t.jqi.animate({
						top: pos.y || top,
						left: pos.x || '50%',
						marginLeft: ((pos.width/2)*-1),
						width: pos.width
					});
			}
			// standard prompt positioning
			else{
				t.jqi.css({
					position: "absolute",
					top: top,
					left: '50%',//$window.width()/2,
					marginLeft: ((t.jqi.outerWidth(false)/2)*-1)
				});
			}

			// restore fx settings
			if(e !== undefined && e.data.animate === false){
				$.fx.off = restoreFx;
			}
		},

		/**
		* style - Restyles the prompt (Used internally)
		* @return void
		*/
		style: function(){
			var t = this;
			
			t.jqif.css({
				zIndex: t.options.zIndex,
				display: "none",
				opacity: t.options.opacity
			});
			t.jqi.css({
				zIndex: t.options.zIndex+1,
				display: "none"
			});
			t.jqib.css({
				zIndex: t.options.zIndex
			});
		},

		/**
		* goToState - Goto the specified state
		* @param state String - name of the state to transition to
		* @param subState Boolean - true to be a sub state within the currently open state
		* @param callback Function - called when the transition is complete
		* @return jQuery - the newly active state
		*/
		goToState: function(state, subState, callback) {
			var t = this,
				$jqi = t.jqi,
				jqiopts = t.options,
				$state = t.getState(state),
				stateobj = jqiopts.states[$state.data('jqi-name')],
				promptstatechanginge = new $.Event('impromptu:statechanging'),
				opts = t.options;

			if(stateobj !== undefined){


				if (typeof stateobj.html === 'function') {
					var contentLaterFunc = stateobj.html;
					$state.find('.' + opts.prefix +'message ').html(contentLaterFunc());
				}

				// subState can be ommitted
				if(typeof subState === 'function'){
					callback = subState;
					subState = false;
				}

				t.jqib.trigger(promptstatechanginge, [t.getCurrentStateName(), state]);

				if(!promptstatechanginge.isDefaultPrevented() && $state.length > 0){
					t.jqi.find('.'+ opts.prefix +'parentstate').removeClass(opts.prefix +'parentstate');

					if(subState){ // hide any open substates
						// get rid of any substates
						t.jqi.find('.'+ opts.prefix +'substate').not($state)
							.slideUp(jqiopts.promptspeed)
							.removeClass('.'+ opts.prefix +'substate')
							.find('.'+ opts.prefix +'arrow').hide();

						// add parent state class so it can be visible, but blocked
						t.jqi.find('.'+ opts.prefix +'state:visible').addClass(opts.prefix +'parentstate');

						// add substate class so we know it will be smaller
						$state.addClass(opts.prefix +'substate');
					}
					else{ // hide any open states
						t.jqi.find('.'+ opts.prefix +'state').not($state)
							.slideUp(jqiopts.promptspeed)
							.find('.'+ opts.prefix +'arrow').hide();
					}
					t.currentStateName = stateobj.name;

					$state.slideDown(jqiopts.promptspeed,function(){
						var $t = $(this);

						// if focus is a selector, find it, else its button index
						if(typeof(stateobj.focus) === 'string'){
							$t.find(stateobj.focus).eq(0).focus();
						}
						else{
							$t.find('.'+ opts.prefix +'defaultbutton').focus();
						}

						$t.find('.'+ opts.prefix +'arrow').show(jqiopts.promptspeed);

						if (typeof callback === 'function'){
							t.jqib.on('impromptu:statechanged', callback);
						}
						t.jqib.trigger('impromptu:statechanged', [state]);
						if (typeof callback === 'function'){
							t.jqib.off('impromptu:statechanged', callback);
						}
					});
					if(!subState){
						t.position();
					}
				} // end isDefaultPrevented()	
			}// end stateobj !== undefined

			return $state;
		},

		/**
		* nextState - Transition to the next state
		* @param callback Function - called when the transition is complete
		* @return jQuery - the newly active state
		*/
		nextState: function(callback) {
			var t = this,
				$next = t.getCurrentState().next();
			if($next.length > 0){
				t.goToState( $next.data('jqi-name'), callback );
			}
			return $next;
		},

		/**
		* prevState - Transition to the previous state
		* @param callback Function - called when the transition is complete
		* @return jQuery - the newly active state
		*/
		prevState: function(callback) {
			var t = this,
				$prev = t.getCurrentState().prev();
			if($prev.length > 0){
				t.goToState( $prev.data('jqi-name'), callback );
			}
			return $prev;
		}

	};

	// ########################################################################
	// $.prompt will manage a queue of Impromptu instances
	// ########################################################################

	/**
	* $.prompt create a new Impromptu instance and push it on the stack of instances
	* @param message String/Object - String of html or Object of states
	* @param options Object - Options to set the prompt
	* @return jQuery - the jQuery object of the prompt within the modal
	*/
	$.prompt = function(message, options){
		var api = new Imp(message, options);
		return api.jqi;
	};

	/**
	* Copy over static methods
	*/
	$.each(Imp, function(k,v){
		$.prompt[k] = v;
	});

	/**
	* Create a proxy for accessing all instance methods. The close method pops from queue.
	*/
	$.each(Imp.prototype, function(k,v){
		$.prompt[k] = function(){
			var api = Imp.getLast(); // always use the last instance on the stack

			if(api && typeof api[k] === "function"){
				return api[k].apply(api, arguments);
			}
		};
	});

	// ########################################################################
	// jQuery Plugin and public access
	// ########################################################################

	/**
	* Enable using $('.selector').prompt({});
	* This will grab the html within the prompt as the prompt message
	*/
	$.fn.prompt = function(options){
		if(options === undefined){
			options = {};
		}
		if(options.withDataAndEvents === undefined){
			options.withDataAndEvents = false;
		}

		$.prompt($(this).clone(options.withDataAndEvents).html(),options);
	};

	/**
	* Export it as Impromptu and $.prompt
	* Can be used from here forth as new Impromptu(states, opts)
	*/
	window.Impromptu = Imp;

}));

/*!
 * jQuery Cookie Plugin v1.4.1
 * https://github.com/carhartl/jquery-cookie
 *
 * Copyright 2013 Klaus Hartl
 * Released under the MIT license
 */
(function (factory) {
	if (typeof define === 'function' && define.amd) {
		// AMD
		define(['jquery'], factory);
	} else if (typeof exports === 'object') {
		// CommonJS
		factory(require('jquery'));
	} else {
		// Browser globals
		factory(jQuery);
	}
}(function ($) {

	var pluses = /\+/g;

	function encode(s) {
		return config.raw ? s : encodeURIComponent(s);
	}

	function decode(s) {
		return config.raw ? s : decodeURIComponent(s);
	}

	function stringifyCookieValue(value) {
		return encode(config.json ? JSON.stringify(value) : String(value));
	}

	function parseCookieValue(s) {
		if (s.indexOf('"') === 0) {
			// This is a quoted cookie as according to RFC2068, unescape...
			s = s.slice(1, -1).replace(/\\"/g, '"').replace(/\\\\/g, '\\');
		}

		try {
			// Replace server-side written pluses with spaces.
			// If we can't decode the cookie, ignore it, it's unusable.
			// If we can't parse the cookie, ignore it, it's unusable.
			s = decodeURIComponent(s.replace(pluses, ' '));
			return config.json ? JSON.parse(s) : s;
		} catch(e) {}
	}

	function read(s, converter) {
		var value = config.raw ? s : parseCookieValue(s);
		return $.isFunction(converter) ? converter(value) : value;
	}

	var config = $.cookie = function (key, value, options) {

		// Write

		if (value !== undefined && !$.isFunction(value)) {
			options = $.extend({}, config.defaults, options);

			if (typeof options.expires === 'number') {
				var days = options.expires, t = options.expires = new Date();
				t.setTime(+t + days * 864e+5);
			}

			return (document.cookie = [
				encode(key), '=', stringifyCookieValue(value),
				options.expires ? '; expires=' + options.expires.toUTCString() : '', // use expires attribute, max-age is not supported by IE
				options.path    ? '; path=' + options.path : '',
				options.domain  ? '; domain=' + options.domain : '',
				options.secure  ? '; secure' : ''
			].join(''));
		}

		// Read

		var result = key ? undefined : {};

		// To prevent the for loop in the first place assign an empty array
		// in case there are no cookies at all. Also prevents odd result when
		// calling $.cookie().
		var cookies = document.cookie ? document.cookie.split('; ') : [];

		for (var i = 0, l = cookies.length; i < l; i++) {
			var parts = cookies[i].split('=');
			var name = decode(parts.shift());
			var cookie = parts.join('=');

			if (key && key === name) {
				// If second argument (value) is a function it's a converter...
				result = read(cookie, value);
				break;
			}

			// Prevent storing a cookie that we couldn't decode.
			if (!key && (cookie = read(cookie)) !== undefined) {
				result[name] = cookie;
			}
		}

		return result;
	};

	config.defaults = {};

	$.removeCookie = function (key, options) {
		if ($.cookie(key) === undefined) {
			return false;
		}

		// Must not alter options, thus extending a fresh object...
		$.cookie(key, '', $.extend({}, options, { expires: -1 }));
		return !$.cookie(key);
	};

}));