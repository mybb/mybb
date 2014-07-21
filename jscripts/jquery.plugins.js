/**
 * jGrowl 1.3.0
 *
 * Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 *
 * Written by Stan Lemon <stosh1985@gmail.com>
 * Last updated: 2014.04.18
 */
(function($) {
	/** Compatibility holdover for 1.9 to check IE6 **/
	var $ie6 = (function(){
		return false === $.support.boxModel && $.support.objectAll && $.support.leadingWhitespace;
	})();

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
				.addClass('jGrowl-notification ' + o.themeState + ' ui-corner-all' + ((o.group !== undefined && o.group !== '') ? ' ' + o.group : ''))
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
			if ( o.corners !== '' && $.fn.corner !== undefined ) $(notification).corner( o.corners );

			/** Add a Global Closer if more than one notification exists **/
			if ($('div.jGrowl-notification:parent', self.element).size() > 1 &&
				$('div.jGrowl-closer', self.element).size() === 0 && this.defaults.closer !== false ) {
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
			$(this.element).find('div.jGrowl-notification:parent').each( function() {
				if ($(this).data("jGrowl") !== undefined && $(this).data("jGrowl").created !== undefined &&
					($(this).data("jGrowl").created.getTime() + parseInt($(this).data("jGrowl").life, 10))  < (new Date()).getTime() &&
					$(this).data("jGrowl").sticky !== true &&
					($(this).data("jGrowl.pause") === undefined || $(this).data("jGrowl.pause") !== true) ) {

					// Pause the notification, lest during the course of animation another close event gets called.
					$(this).trigger('jGrowl.beforeClose');
				}
			});

			if (this.notifications.length > 0 &&
				(this.defaults.pool === 0 || $(this.element).find('div.jGrowl-notification:parent').size() < this.defaults.pool) )
				this.render( this.notifications.shift() );

			if ($(this.element).find('div.jGrowl-notification:parent').size() < 2 ) {
				$(this.element).find('div.jGrowl-closer').animate(this.defaults.animateClose, this.defaults.speed, this.defaults.easing, function() {
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

			if ($ie6) {
				$(this.element).addClass('ie6');
			}
		},

		/** Shutdown jGrowl, removing it and clearing the interval **/
		shutdown: function() {
			$(this.element).removeClass('jGrowl')
				.find('div.jGrowl-notification').trigger('jGrowl.close')
				.parent().empty()
			;

			clearInterval(this.interval);
		},

		close: function() {
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

/*! jQuery-Impromptu - v5.2.4 - 2014-05-26
* http://trentrichardson.com/Impromptu
* Copyright (c) 2014 Trent Richardson; Licensed MIT */
(function($) {
	"use strict";

	/**
	* setDefaults - Sets the default options
	* @param message String/Object - String of html or Object of states
	* @param options Object - Options to set the prompt
	* @return jQuery - container with overlay and prompt 
	*/
	$.prompt = function(message, options) {
		// only for backwards compat, to be removed in future version
		if(options !== undefined && options.classes !== undefined && typeof options.classes === 'string'){
			options = { box: options.classes };
		}

		$.prompt.options = $.extend({},$.prompt.defaults,options);
		$.prompt.currentPrefix = $.prompt.options.prefix;
		
		// Be sure any previous timeouts are destroyed
		if($.prompt.timeout){
			clearTimeout($.prompt.timeout);
		}
		$.prompt.timeout = false;

		var opts = $.prompt.options,
			$body = $(document.body),
			$window = $(window);
					
		//build the box and fade
		var msgbox = '<div class="'+ $.prompt.options.prefix +'box '+ opts.classes.box +'">';
		if(opts.useiframe && ($('object, applet').length > 0)) {
			msgbox += '<iframe src="javascript:false;" style="display:block;position:absolute;z-index:-1;" class="'+ opts.prefix +'fade '+ opts.classes.fade +'"></iframe>';
		} else {
			msgbox +='<div class="'+ opts.prefix +'fade '+ opts.classes.fade +'"></div>';
		}
		msgbox += '<div class="'+ opts.prefix +' '+ opts.classes.prompt +'">'+
					'<form action="javascript:false;" onsubmit="return false;" class="'+ opts.prefix +'form '+ opts.classes.form +'">'+
						'<div class="'+ opts.prefix +'close '+ opts.classes.close +'">'+ opts.closeText +'</div>'+
						'<div class="'+ opts.prefix +'states"></div>'+
					'</form>'+
				'</div>'+
			'</div>';

		$.prompt.jqib = $(msgbox).appendTo($body);
		$.prompt.jqi = $.prompt.jqib.children('.'+ opts.prefix);//.data('jqi',opts);
		$.prompt.jqif = $.prompt.jqib.children('.'+ opts.prefix +'fade');

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
		$.prompt.options.states = {};
		var k,v;
		for(k in message){
			v = $.extend({},$.prompt.defaults.state,{name:k},message[k]);
			$.prompt.addState(v.name, v);

			if($.prompt.currentStateName === ''){
				$.prompt.currentStateName = v.name;
			}
		}

		//Events
		$.prompt.jqi.on('click', '.'+ opts.prefix +'buttons button', function(e){
			var $t = $(this),
				$state = $t.parents('.'+ opts.prefix +'state'),
				stateobj = $.prompt.options.states[$state.data('jqi-name')],
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
			$.each($.prompt.jqi.children('form').serializeArray(),function(i,obj){
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
				$.prompt.close(true, clicked,msg,forminputs);
			}
		});

		// if the fade is clicked blink the prompt
		var fadeClicked = function(){
			if(opts.persistent){
				var offset = (opts.top.toString().indexOf('%') >= 0? ($window.height()*(parseInt(opts.top,10)/100)) : parseInt(opts.top,10)),
					top = parseInt($.prompt.jqi.css('top').replace('px',''),10) - offset;

				//$window.scrollTop(top);
				$('html,body').animate({ scrollTop: top }, 'fast', function(){
					var i = 0;
					$.prompt.jqib.addClass(opts.prefix +'warning');
					var intervalid = setInterval(function(){
						$.prompt.jqib.toggleClass(opts.prefix +'warning');
						if(i++ > 1){
							clearInterval(intervalid);
							$.prompt.jqib.removeClass(opts.prefix +'warning');
						}
					}, 100);
				});
			}
			else {
				$.prompt.close(true);
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
				var $defBtn = $.prompt.getCurrentState().find('.'+ opts.prefix +'defaultbutton');
				var $tgt = $(e.target);
				
				if($tgt.is('textarea,.'+opts.prefix+'button') === false && $defBtn.length > 0){
					e.preventDefault();
					$defBtn.click();
				}
			}

			//constrain tabs, tabs should iterate through the state and not leave
			if (key === 9){
				var $inputels = $('input,select,textarea,button',$.prompt.getCurrentState());
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
		
		$.prompt.position();
		$.prompt.style();
		
		$.prompt.jqif.click(fadeClicked);
		$window.resize({animate:false}, $.prompt.position);
		$.prompt.jqi.find('.'+ opts.prefix +'close').click($.prompt.close);
		$.prompt.jqib.on("keydown",keyDownEventHandler)
					.on('impromptu:loaded', opts.loaded)
					.on('impromptu:close', opts.close)
					.on('impromptu:statechanging', opts.statechanging)
					.on('impromptu:statechanged', opts.statechanged);

		// Show it
		$.prompt.jqif[opts.show](opts.overlayspeed);
		$.prompt.jqi[opts.show](opts.promptspeed, function(){

			var $firstState = $.prompt.jqi.find('.'+ opts.prefix +'states .'+ opts.prefix +'state').eq(0);
			$.prompt.goToState($firstState.data('jqi-name'));

			$.prompt.jqib.trigger('impromptu:loaded');
		});
		
		// Timeout
		if(opts.timeout > 0){
			$.prompt.timeout = setTimeout(function(){ $.prompt.close(true); },opts.timeout);
		}

		return $.prompt.jqib;
	};
	
	$.prompt.defaults = {
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
	* currentPrefix String - At any time this show be the prefix 
	* of the current prompt ex: "jqi"
	*/
	$.prompt.currentPrefix = $.prompt.defaults.prefix;
	
	/**
	* currentStateName String - At any time this is the current state
	* of the current prompt ex: "state0"
	*/
	$.prompt.currentStateName = "";
		
	/**
	* setDefaults - Sets the default options
	* @param o Object - Options to set as defaults
	* @return void
	*/
	$.prompt.setDefaults = function(o) {
		$.prompt.defaults = $.extend({}, $.prompt.defaults, o);
	};
	
	/**
	* setStateDefaults - Sets the default options for a state
	* @param o Object - Options to set as defaults
	* @return void
	*/
	$.prompt.setStateDefaults = function(o) {
		$.prompt.defaults.state = $.extend({}, $.prompt.defaults.state, o);
	};

	/**
	* position - Repositions the prompt (Used internally)
	* @return void
	*/
	$.prompt.position = function(e){
		var restoreFx = $.fx.off,
			$state = $.prompt.getCurrentState(),
			stateObj = $.prompt.options.states[$state.data('jqi-name')],
			pos = stateObj? stateObj.position : undefined,
			$window = $(window),
			bodyHeight = document.body.scrollHeight, //$(document.body).outerHeight(true),
			windowHeight = $(window).height(),
			documentHeight = $(document).height(),
			height = bodyHeight > windowHeight ? bodyHeight : windowHeight,
			top = parseInt($window.scrollTop(),10) + ($.prompt.options.top.toString().indexOf('%') >= 0? 
					(windowHeight*(parseInt($.prompt.options.top,10)/100)) : parseInt($.prompt.options.top,10));

		// when resizing the window turn off animation
		if(e !== undefined && e.data.animate === false){
			$.fx.off = true;
		}
		
		$.prompt.jqib.css({
			position: "absolute",
			height: height,
			width: "100%",
			top: 0,
			left: 0,
			right: 0,
			bottom: 0
		});
		$.prompt.jqif.css({
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
				$.prompt.jqi.css({
					position: "absolute"
				});
				$.prompt.jqi.animate({
					top: offset.top + pos.y,
					left: offset.left + pos.x,
					marginLeft: 0,
					width: (pos.width !== undefined)? pos.width : null
				});
				top = (offset.top + pos.y) - ($.prompt.options.top.toString().indexOf('%') >= 0? (windowHeight*(parseInt($.prompt.options.top,10)/100)) : parseInt($.prompt.options.top,10));
				$('html,body').animate({ scrollTop: top }, 'slow', 'swing', function(){});
			}
		}
		// custom state width animation
		else if(pos && pos.width){
			$.prompt.jqi.css({
					position: "absolute",
					left: '50%'
				});
			$.prompt.jqi.animate({
					top: pos.y || top,
					left: pos.x || '50%',
					marginLeft: ((pos.width/2)*-1),
					width: pos.width
				});
		}
		// standard prompt positioning
		else{
			$.prompt.jqi.css({
				position: "absolute",
				top: top,
				left: '50%',//$window.width()/2,
				marginLeft: (($.prompt.jqi.outerWidth(false)/2)*-1)
			});
		}

		// restore fx settings
		if(e !== undefined && e.data.animate === false){
			$.fx.off = restoreFx;
		}
	};
	
	/**
	* style - Restyles the prompt (Used internally)
	* @return void
	*/
	$.prompt.style = function(){
		$.prompt.jqif.css({
			zIndex: $.prompt.options.zIndex,
			display: "none",
			opacity: $.prompt.options.opacity
		});
		$.prompt.jqi.css({
			zIndex: $.prompt.options.zIndex+1,
			display: "none"
		});
		$.prompt.jqib.css({
			zIndex: $.prompt.options.zIndex
		});
	};

	/**
	* get - Get the prompt
	* @return jQuery - the prompt
	*/
	$.prompt.get = function(state) {
		return $('.'+ $.prompt.currentPrefix);
	};

	/**
	* addState - Injects a state into the prompt
	* @param statename String - Name of the state
	* @param stateobj Object - options for the state
	* @param afterState String - selector of the state to insert after
	* @return jQuery - the newly created state
	*/
	$.prompt.addState = function(statename, stateobj, afterState) {
		var state = "",
			$state = null,
			arrow = "",
			title = "",
			opts = $.prompt.options,
			$jqistates = $('.'+ $.prompt.currentPrefix +'states'),
			defbtn,k,v,i=0;

		stateobj = $.extend({},$.prompt.defaults.state, {name:statename}, stateobj);

		if(stateobj.position.arrow !== null){
			arrow = '<div class="'+ opts.prefix + 'arrow '+ opts.prefix + 'arrow'+ stateobj.position.arrow +'"></div>';
		}
		if(stateobj.title && stateobj.title !== ''){
			title = '<div class="lead '+ opts.prefix + 'title '+ opts.classes.title +'">'+  stateobj.title +'</div>';
		}
		state += '<div id="'+ opts.prefix +'state_'+ statename +'" class="'+ opts.prefix + 'state" data-jqi-name="'+ statename +'" style="display:none;">'+ 
					arrow + title +
					'<div class="'+ opts.prefix +'message '+ opts.classes.message +'">' + stateobj.html +'</div>'+
					'<div class="'+ opts.prefix +'buttons '+ opts.classes.buttons +'"'+ ($.isEmptyObject(stateobj.buttons)? 'style="display:none;"':'') +'>';
		
		for(k in stateobj.buttons){
			v = stateobj.buttons[k],
			defbtn = stateobj.focus === i || (isNaN(stateobj.focus) && stateobj.defaultButton === i) ? ($.prompt.currentPrefix + 'defaultbutton ' + opts.classes.defaultButton) : '';

			if(typeof v === 'object'){
				state += '<button class="'+ opts.classes.button +' '+ $.prompt.currentPrefix + 'button '+ defbtn;
				
				if(typeof v.classes !== "undefined"){
					state += ' '+ ($.isArray(v.classes)? v.classes.join(' ') : v.classes) + ' ';
				}
				
				state += '" name="' + opts.prefix + '_' + statename + '_button' + v.title.replace(/[^a-z0-9]+/gi,'') + '" id="' + opts.prefix + '_' + statename + '_button' + v.title.replace(/[^a-z0-9]+/gi,'') + '" value="' + v.value + '">' + v.title + '</button>';
				
			} else {
				state += '<button class="'+ $.prompt.currentPrefix + 'button '+ opts.classes.button +' '+ defbtn +'" name="' + opts.prefix + '_' + statename + '_button' + k.replace(/[^a-z0-9]+/gi,'') + '" id="' + opts.prefix +  '_' + statename + '_button' + k.replace(/[^a-z0-9]+/gi,'') + '" value="' + v + '">' + k + '</button>';
				
			}
			i++;
		}
		state += '</div></div>';
		
		$state = $(state);

		$state.on('impromptu:submit', stateobj.submit);

		if(afterState !== undefined){
			$jqistates.find('#'+ $.prompt.currentPrefix +'state_'+ afterState).after($state);
		}
		else{
			$jqistates.append($state);
		}

		$.prompt.options.states[statename] = stateobj;

		return $state;
	};
	
	/**
	* removeState - Removes a state from the prompt
	* @param state String - Name of the state
	* @param newState String - Name of the state to transition to
	* @return Boolean - returns true on success, false on failure
	*/
	$.prompt.removeState = function(state, newState) {
		var $state = $.prompt.getState(state),
			rm = function(){ $state.remove(); };

		if($state.length === 0){
			return false;
		}
		
		// transition away from it before deleting
		if($state.css('display') !== 'none'){
			if(newState !== undefined && $.prompt.getState(newState).length > 0){
				$.prompt.goToState(newState, false, rm);
			}
			else if($state.next().length > 0){
				$.prompt.nextState(rm);
			}
			else if($state.prev().length > 0){
				$.prompt.prevState(rm);
			}
			else{
				$.prompt.close();
			}
		}
		else{
			$state.slideUp('slow', rm);
		}

		return true;
	};

	/**
	* getState - Get the state by its name
	* @param state String - Name of the state
	* @return jQuery - the state
	*/
	$.prompt.getState = function(state) {
		return $('#'+ $.prompt.currentPrefix +'state_'+ state);
	};
	$.prompt.getStateContent = function(state) {
		return $.prompt.getState(state);
	};
	
	/**
	* getCurrentState - Get the current visible state
	* @return jQuery - the current visible state
	*/
	$.prompt.getCurrentState = function() {
		return $.prompt.getState($.prompt.getCurrentStateName());
	};
		
	/**
	* getCurrentStateName - Get the name of the current visible state
	* @return String - the current visible state's name
	*/
	$.prompt.getCurrentStateName = function() {
		return $.prompt.currentStateName;
	};
	
	/**
	* goToState - Goto the specified state
	* @param state String - name of the state to transition to
	* @param subState Boolean - true to be a sub state within the currently open state
	* @param callback Function - called when the transition is complete
	* @return jQuery - the newly active state
	*/	
	$.prompt.goToState = function(state, subState, callback) {
		var $jqi = $.prompt.get(),
			jqiopts = $.prompt.options,
			$state = $.prompt.getState(state),
			stateobj = jqiopts.states[$state.data('jqi-name')],
			promptstatechanginge = new $.Event('impromptu:statechanging');

		// subState can be ommitted
		if(typeof subState === 'function'){
			callback = subState;
			subState = false;
		}

		$.prompt.jqib.trigger(promptstatechanginge, [$.prompt.getCurrentStateName(), state]);
		
		if(!promptstatechanginge.isDefaultPrevented() && $state.length > 0){
			$.prompt.jqi.find('.'+ $.prompt.currentPrefix +'parentstate').removeClass($.prompt.currentPrefix +'parentstate');

			if(subState){ // hide any open substates
				// get rid of any substates
				$.prompt.jqi.find('.'+ $.prompt.currentPrefix +'substate').not($state)
					.slideUp(jqiopts.promptspeed)
					.removeClass('.'+ $.prompt.currentPrefix +'substate')
					.find('.'+ $.prompt.currentPrefix +'arrow').hide();

				// add parent state class so it can be visible, but blocked
				$.prompt.jqi.find('.'+ $.prompt.currentPrefix +'state:visible').addClass($.prompt.currentPrefix +'parentstate');

				// add substate class so we know it will be smaller
				$state.addClass($.prompt.currentPrefix +'substate');
			}
			else{ // hide any open states
				$.prompt.jqi.find('.'+ $.prompt.currentPrefix +'state').not($state)
					.slideUp(jqiopts.promptspeed)
					.find('.'+ $.prompt.currentPrefix +'arrow').hide();
			}
			$.prompt.currentStateName = stateobj.name;

			$state.slideDown(jqiopts.promptspeed,function(){
				var $t = $(this);

				// if focus is a selector, find it, else its button index
				if(typeof(stateobj.focus) === 'string'){
					$t.find(stateobj.focus).eq(0).focus();
				}
				else{
					$t.find('.'+ $.prompt.currentPrefix +'defaultbutton').focus();
				}

				$t.find('.'+ $.prompt.currentPrefix +'arrow').show(jqiopts.promptspeed);
				
				if (typeof callback === 'function'){
					$.prompt.jqib.on('impromptu:statechanged', callback);
				}
				$.prompt.jqib.trigger('impromptu:statechanged', [state]);
				if (typeof callback === 'function'){
					$.prompt.jqib.off('impromptu:statechanged', callback);
				}
			});
			if(!subState){
				$.prompt.position();
			}
		}
		return $state;
	};

	/**
	* nextState - Transition to the next state
	* @param callback Function - called when the transition is complete
	* @return jQuery - the newly active state
	*/	
	$.prompt.nextState = function(callback) {
		var $next = $('#'+ $.prompt.currentPrefix +'state_'+ $.prompt.getCurrentStateName()).next();
		if($next.length > 0){
			$.prompt.goToState( $next.attr('id').replace($.prompt.currentPrefix +'state_',''), callback );
		}
		return $next;
	};
	
	/**
	* prevState - Transition to the previous state
	* @param callback Function - called when the transition is complete
	* @return jQuery - the newly active state
	*/	
	$.prompt.prevState = function(callback) {
		var $prev = $('#'+ $.prompt.currentPrefix +'state_'+ $.prompt.getCurrentStateName()).prev();
		if($prev.length > 0){
			$.prompt.goToState( $prev.attr('id').replace($.prompt.currentPrefix +'state_',''), callback );
		}
		return $prev;
	};
	
	/**
	* close - Closes the prompt
	* @param callback Function - called when the transition is complete
	* @param clicked String - value of the button clicked (only used internally)
	* @param msg jQuery - The state message body (only used internally)
	* @param forvals Object - key/value pairs of all form field names and values (only used internally)
	* @return jQuery - the newly active state
	*/	
	$.prompt.close = function(callCallback, clicked, msg, formvals){
		if($.prompt.timeout){
			clearTimeout($.prompt.timeout);
			$.prompt.timeout = false;
		}

		if($.prompt.jqib){
			$.prompt.jqib.fadeOut('fast',function(){

				$.prompt.jqib.trigger('impromptu:close', [clicked,msg,formvals]);
				
				$.prompt.jqib.remove();
				
				$(window).off('resize',$.prompt.position);
			});
		}
		$.prompt.currentStateName = "";
	};
	
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
	
})(jQuery);

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