var MyModal = Class.create();

MyModal.prototype = {
	initialize: function(options)
	{
		this.options = {
			overlay: 50,
			overlayCss: {},
			containerCss: {},
			close: true,
			closeTitle: 'Close',
			onOpen: null,
			onShow: null,
			onClose: null,
			onBeforeClose: null,
			type: 'string',
			width: '630',
			buttons: '',
			title: '',
			formId: 'modal_form',
		};
		
		Object.extend(this.options, options || {});
		
		this.generateModal();
	},
	
	displayModal: function(data)
	{
		// handle ACP session timeout and other error
		if(data == "<error>login</error>")
		{
			window.location = "./index.php";
			return;
		}
		
		this.hideLoader();
		modalContent = '';
		
		// Doesn't work in IE6 or below
		modalContent = '<div id="ModalTopLeftCorner"></div><div id="ModalTopBorder"></div><div id="ModalTopRightCorner"></div><div id="ModalLeftBorder"></div><div id="ModalRightBorder"></div><div id="ModalBottomLeftCorner"></div><div id="ModalBottomRightCorner"></div><div id="ModalBottomBorder"></div>';
		
		if(data.indexOf('ModalTitle') > 0 && data.indexOf('ModalContent') > 0)
		{
			modalContent += '<div id="ModalContentContainer">'+data+'</div>';
		}
		else
		{
			modalContent += '<div id="ModalContentContainer"><div class="ModalTitle">'+this.options.title+'</div><div class="ModalContent">'+data+'</div><div class="ModalButtonRow">'+this.options.buttons+'</div></div>';
		}

		// Doesn't work in IE6 or below
		cssPosition = 'fixed';
		
		var container = document.createElement('div');
		container.id = 'ModalContainer';
		container.className = 'ModalContainer';
		container.style.width = this.options.width+'px';
		container.style.position = cssPosition;
		container.style.zIndex = 3100;
		container.style.marginLeft = '-'+(this.options.width/2)+'px';
		
		var modalData = document.createElement('div');
		modalData.className = 'modalData';
		modalData.innerHTML = modalContent;
		container.appendChild(modalData);
		
		// Insert into the body
		owner = document.getElementsByTagName("body").item(0);
		owner.appendChild(container);

		// Observe the escape buttons!
		Event.observe("modalClose", 'click', this.close.bind(this));
		Event.observe("modalCancel", 'click', this.close.bind(this));
		Event.observe("modalSubmit", 'click', this.submit.bind(this));

		if(MyBB.browser == "ie" || MyBB.browser == "opera" || MyBB.browser == "safari" || MyBB.browser == "chrome")
		{
			var scripts = data.extractScripts();
			scripts.each(function(script)
			{
				eval(script);
			});
		}
		else
		{
			data.evalScripts();
		}
		
		$('ModalContainer').show();
	},
	
	showLoader: function()
	{
		this.loader = document.createElement('div');
		this.loader.id = 'ModalLoadingIndicator';
		
		// Insert into the body
		Element.insert(document.body, { 'after': this.loader });
	},
	
	showOverlayLoader: function()
	{
		this.overlayLoader = document.createElement('div');
		this.overlayLoader.id = 'ModalOverlay';
		this.overlayLoader.className = 'ModalOverlay';
		this.overlayLoader.style.height = '100%';
		this.overlayLoader.style.width = '100%';
		this.overlayLoader.style.position = 'fixed';
		this.overlayLoader.style.left = 0;
		this.overlayLoader.style.top = 0;
		this.overlayLoader.style.zIndex = 3000;

		Element.insert(document.body, { 'after': this.overlayLoader });

		// Insert into the body
		// Opacity
		if(MyBB.browser != "ie")
		{
			$("ModalOverlay").style.opacity = this.options.overlay / 100;
		}
		else
		{
			// IE 8 and IE 9 does this nicely - IE 7 ignores
			$("ModalOverlay").setStyle(
			{
				opacity: this.options.overlay / 100
			});
		}

		this.showLoader();
	},
	
	hideOverlayLoader: function()
	{
		$('ModalLoadingIndicator').remove();
		$('ModalOverlay').remove();
	},
	
	hideLoader: function()
	{
		$('ModalLoadingIndicator').remove();
	},
	
	generateModal: function()
	{
		this.showOverlayLoader();
		Event.observe(document, 'keypress', this.closeListener.bindAsEventListener(this));

		if(this.options.type == 'ajax')
		{
			new Ajax.Request(this.options.url, {
				method: 'get',
				parameters: { time: new Date().getTime() },
				onComplete: function(request) { this.displayModal(request.responseText); }.bind(this)
			});
		}
		else
		{
			this.displayModal(this.options.data);
		}
	},

	submit: function(e)
	{
		Event.stop(e);
		
		this.showOverlayLoader();
		var postData = $(this.options.formId).serialize();
		this.postData = $(this.options.formId).serialize(true);
		
		new Ajax.Request(this.options.url, {
            method: 'post',
            postBody: postData+"&ajax=1&time=" + new Date().getTime(),
            onComplete: this.onComplete.bind(this),
        });
	},
	
	onComplete: function(request)
	{
		var data = request.responseText;
		
		if(MyBB.browser == "ie" || MyBB.browser == "opera" || MyBB.browser == "safari" || MyBB.browser == "chrome")
		{
			var scripts = data.extractScripts();
			scripts.each(function(script)
			{
				eval(script);
			});
		}
		else
		{
			data.evalScripts();
		}
		
		this.hideOverlayLoader();
		this.close();
	},
	
	closeListener: function(e)
	{
		if(e.keyCode == 27)
		{
			this.close();
		}

		return false;
	},
	
	close: function(e)
	{
		if(e)
		{
			Event.stop(e);
		}
		
		$('ModalContainer').remove();
		if($('ModalLoadingIndicator'))
		{
			$('ModalLoadingIndicator').remove();
		}

		// ModalContentContainer
		if($('ModalOverlay'))
		{
			$('ModalOverlay').remove();
		}
	}
};
