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
		element.onclick = this.openMenu.bindAsEventListener(this);
	},
	
	openMenu: function(e)
	{
		Event.stop(e);
		if(document.currentMenu && document.currentMenu == this.id)
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
		do
		{
			offsetTop += element.offsetTop || 0;
			offsetLeft += element.offsetLeft || 0;
			element = element.offsetParent;
			if(element)
			{
				if(Element.getStyle(element, 'position') == 'relative' || Element.getStyle(element, 'position') == 'absolute') break;
			}
		} while(element);
		offsetTopReal = offsetTop;
		offsetLeftReal = offsetLeft;
		if(element) // will be true if we broke off the last loop
		{
			// calculate the true top/left position relative to page borders (this is used for checking whether the popup menu will be displayed within the page)
			do
			{
				offsetTopReal += element.offsetTop || 0;
				offsetLeftReal += element.offsetLeft || 0;
			} while(element = element.offsetParent);
		}
		element = $(this.id);
		element.blur();
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
		this.menu.style.display = 'block';
		if(this.menu.style.width)
		{
			menuWidth = parseInt(this.menu.style.width);
		}
		else
		{
			menuWidth = this.menu.offsetWidth;
		}
		pageSize = DomLib.getPageSize();
		if(offsetLeftReal+menuWidth >= pageSize[0])
		{
			this.menu.style.left = (offsetLeft-menuWidth+element.offsetWidth)+"px";
		}
		this.menu.style.display = 'block';	
		this.menu.style.visibility = 'visible';

		document.currentMenu = element.id;
		Event.observe(document, 'click', this.closeMenu.bindAsEventListener(this));
	},
	
	closeMenu: function()
	{
		if(!document.currentMenu)
		{
			return;
		}
		var menu = document.currentMenu;
		menu = $(menu+"_popup");
		menu.style.display = "none";
		document.currentMenu = "";
		document.onclick = function() { };
	}
};