var PopupMenu = Class.create();

PopupMenu.prototype = {

	initialize: function(id)
	{
		document.currentMenu = "";

		if(!$(id))
		{
			return false;
		}
		this.id = id;
		element = $(id);
		
		popupMenu = element.id+"_popup";
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
		if(document.currentMenu == this.id)
		{
			this.closeMenu(document.currentMenu);
			return false;
		}
		else if(document.currentMenu != "")
		{
			this.closeMenu(document.currentMenu);
		}
		
		offsetTop = offsetLeft = 0;
		element = $(this.id);
		do
		{
			offsetTop += element.offsetTop || 0;
			offsetLeft += element.offsetLeft || 0;
			element = element.offsetParent;
		} while(element);

		element = $(this.id);
		this.menu.style.position = "absolute";
		this.menu.style.zIndex = 10;
		this.menu.style.top = (offsetTop+element.offsetHeight-1)+"px";
		this.menu.style.left = offsetLeft+"px";
		
		if(this.menu.style.width)
		{
			menuWidth = parseInt(this.menu.style.width);
		}
		else
		{
			menuWidth = this.menu.offsetWidth;
		}
		
		if(offsetLeft+menuWidth >= document.body.clientWidth)
		{
			this.menu.style.width = (offsetLeft+element.offsetWidth-menuWidth-2)+"px";
			if(MyBB.browser == "ie")
			{
				this.menu.style.left = (parseInt(this.menu.style.left)-6)+"px";
			}
		}
		this.menu.style.display = "";
		document.currentMenu = element.id;
		document.onclick = this.closeMenu.bindAsEventListener(this);
		return false;
	},
	
	closeMenu: function()
	{
		menu = document.currentMenu;
		menu = $(menu+"_popup");
		
		menu.style.display = "none";
		document.currentMenu = "";
		document.onclick = null;
	}
};