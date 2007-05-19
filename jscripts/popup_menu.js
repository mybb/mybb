var PopupMenu = Class.create();

PopupMenu.prototype = {
	initialize: function(id, options)
	{
		document.currentMenu = "";

		if(!$(id))
		{
			return false;
		}

		this.id = id;
		var element = $(id);

		var popupMenu = element.id+"_popup";
		if(!$(popupMenu))
		{
			return false;
		}

		this.menu = $(popupMenu);
		this.menu.style.display = "none";
		this.options = options;
		Event.observe(element, "click", this.openMenu.bindAsEventListener(this));
	},

	openMenu: function(e)
	{
		if(
			typeof this.options != 'undefined' && typeof this.options.ajax != 'undefined' &&
			(this.options.update == false && this.menu.innerHTML != "") == false
		)
		{
			if(this.options.ActivityIndicator == true)
			{
				this.spinner = new ActivityIndicator(this.menu, {image: "images/spinner.gif"});
			}
			else if(this.options.ImageIndicator == true)
			{
				this.old_src = $(this.id+"_image").src;
				$(this.id+"_image").src = "images/spinner.gif";
			}
			new Ajax.Request(this.options.ajax, {method: 'get', onComplete: this.onComplete.bindAsEventListener(this)});
		}

		Event.stop(e);
		Event.element(e).blur();
		if(document.currentMenu == this.id)
		{
			this.closeMenu();
			return false;
		}
		else if(document.currentMenu != "")
		{
			this.closeMenu();
		}

		offsetTop = offsetLeft = 0;
		var element = $(this.id);
		var width = Event.element(e).offsetWidth;
		do
		{
			offsetTop += element.offsetTop || 0;
			offsetLeft += element.offsetLeft || 0;
			element = element.offsetParent;
		} while(element);

		element = $(this.id);
		this.menu.style.position = "absolute";
		this.menu.style.zIndex = 100;
		this.menu.style.top = (offsetTop+element.offsetHeight-1)+"px";

		// Bad browser detection - yes, only choice - yes.
		if(MyBB.browser == "opera" || MyBB.browser == "safari")
		{
			this.menu.style.top = (parseInt(this.menu.style.top)-2)+"px";
		}

		this.menu.style.left = offsetLeft+"px";
		this.menu.style.visibility = 'hidden';
		this.menu.style.display = '';

		if(this.menu.style.width)
		{
			menuWidth = parseInt(this.menu.style.width);
		}
		else
		{
			menuWidth = this.menu.offsetWidth;
		}

		pageSize = DomLib.getPageSize();
		if(offsetLeft+menuWidth >= pageSize[0])
		{
			this.menu.style.left = (offsetLeft+width-menuWidth)+"px";
			if(MyBB.browser == "ie")
			{
				this.menu.style.left = (parseInt(this.menu.style.left)-2)+"px";
			}
		}
		//this.menu.style.display = '';	
		this.menu.style.visibility = 'visible';

		document.currentMenu = element.id;
		Event.observe(document, 'click', this.closeMenu.bindAsEventListener(this));
	},
	
	onComplete: function(request)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/))
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);

			if(!message[1])
			{
				message[1] = "An unknown error occurred.";
			}

			alert(message[1]);
		}
		else if(request.responseText)
		{
			this.menu.innerHTML = request.responseText;
		}

		if(this.options.ActivityIndicator == true)
		{
			this.spinner.destroy();
			this.spinner = '';
		}
		else if(this.options.ImageIndicator == true)
		{
			$(this.id+"_image").src = this.old_src;
		}
	},

	closeMenu: function()
	{	
		menu = document.currentMenu;
		if(document.currentMenu)
		{
			menu = $(menu+"_popup");
			menu.style.display = "none";
		}
		document.currentMenu = "";
		Event.stopObserving(document, 'click', this.closeMenu.bindAsEventListener(this));
	}
};