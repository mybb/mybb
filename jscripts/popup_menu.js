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
		this.menu.hide();
		this.options = options;
		Event.observe(element, "click", this.openMenu.bindAsEventListener(this));
	},

	openMenu: function(e)
	{
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

		var offset = Position.positionedOffset($(this.id));
		alert(offset);
		var width = Event.element(e).offsetWidth;

		element = $(this.id);
		this.menu.style.position = "absolute";
		this.menu.style.zIndex = 100;
		this.menu.style.top = (offset[1]+element.offsetHeight-1)+"px";

		// Bad browser detection - yes, only choice - yes.
		if(MyBB.browser == "opera" || MyBB.browser == "safari")
		{
			this.menu.style.top = (parseInt(this.menu.style.top)-2)+"px";
		}

		this.menu.style.left = offset[0]+"px";
		this.menu.style.visibility = 'hidden';
		this.menu.show();

		if(this.menu.style.width)
		{
			menuWidth = parseInt(this.menu.style.width);
		}
		else
		{
			menuWidth = this.menu.offsetWidth;
		}

		pageSize = DomLib.getPageSize();
		if(offset[0]+menuWidth >= pageSize[0])
		{
			this.menu.style.left = (offset[0]+width-menuWidth)+"px";
			if(MyBB.browser == "ie")
			{
				this.menu.style.left = (parseInt(this.menu.style.left)-2)+"px";
			}
		}
		this.menu.style.visibility = 'visible';

		document.currentMenu = element.id;
		Event.observe(document, 'click', this.closeMenu.bindAsEventListener(this));
	},
	
	closeMenu: function()
	{
		menu = document.currentMenu;
		if(document.currentMenu)
		{
			menu = $(menu+"_popup");
			menu.hide();
		}
		document.currentMenu = '';
		Event.stopObserving(document, 'click', this.closeMenu.bindAsEventListener(this));
	}
};