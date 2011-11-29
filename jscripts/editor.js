var messageEditor = Class.create();

messageEditor.prototype = {
	openTags: new Array(),
	toolbarHeight: 0,
	currentTheme: '',
	themePath: '',
	openDropDownMenu: null,

	setTheme: function(theme)
	{
		if(this.currentTheme != '' || $('editorTheme')) {
			$('editorTheme').remove();
		}

		var stylesheet = document.createElement('link');
		stylesheet.setAttribute('rel', 'stylesheet');
		stylesheet.setAttribute('type', 'text/css');
		stylesheet.setAttribute('href', this.baseURL + 'editor_themes/'+theme+'/stylesheet.css');
		document.getElementsByTagName('head')[0].appendChild(stylesheet);
		this.currentTheme = theme;
		this.themePath = this.baseURL + 'editor_themes/'+theme;
	},

	initialize: function(textarea, options)
	{
		// Sorry Konqueror, but due to a browser bug out of control with textarea values
		// you do not get to use the fancy editor.

		if(MyBB.browser == "konqueror" || (typeof(mybb_editor_disabled) != "undefined" && mybb_editor_disabled == true))
		{
			return false;
		}

		// Establish the base path to this javascript file
		$$('script').each(function(script) {
			if(script.src && script.src.indexOf('editor.js') != -1) {
				this.baseURL = script.src.replace(/editor\.js(.*?)$/, '');
			}
		}, this);

		this.options = options;

		if(this.options)
		{
			if(!this.options.lang)
			{
				return false;
			}

			if(!this.options.rtl)
			{
				this.options.rtl = 0;
			}
		}

		if(this.options && this.options.theme)
		{
			this.setTheme(this.options.theme);
		}
		else
		{
			this.setTheme('default');
		}

		// Defines an array of fonts to be shown in the font drop down.
		this.fonts = new Object();
		this.fonts["Arial"] = "Arial";
		this.fonts["Courier"] = "Courier";
		this.fonts["Impact"] = "Impact";
		this.fonts["Tahoma"] = "Tahoma";
		this.fonts["Times New Roman"] = "Times New Roman";
		this.fonts["Trebuchet MS"] = "Trebuchet MS";
		this.fonts["Verdana"] = "Verdana";

		// An array of font sizes to be shown.
		this.sizes = new Object();
		this.sizes["xx-small"] = this.options.lang.size_xx_small;
		this.sizes["x-small"] = this.options.lang.size_x_small;
		this.sizes["small"] = this.options.lang.size_small;
		this.sizes["medium"] = this.options.lang.size_medium;
		this.sizes["large"] = this.options.lang.size_large;
		this.sizes["x-large"] = this.options.lang.size_x_large;
		this.sizes["xx-large"] = this.options.lang.size_xx_large;

		// An array of colours to be shown.
		this.colors = new Object();
		this.colors[1] = "#800000";
		this.colors[2] = "#8B4513";
		this.colors[3] = "#006400";
		this.colors[4] = "#2F4F4F";
		this.colors[5] = "#000080";
		this.colors[6] = "#4B0082";
		this.colors[7] = "#800080";
		this.colors[8] = "#000000";
		this.colors[9] = "#FF0000";
		this.colors[10] = "#DAA520";
		this.colors[11] = "#6B8E23";
		this.colors[12] = "#708090";
		this.colors[13] = "#0000CD";
		this.colors[14] = "#483D8B";
		this.colors[15] = "#C71585";
		this.colors[16] = "#696969";
		this.colors[17] = "#FF4500";
		this.colors[18] = "#FFA500";
		this.colors[19] = "#808000";
		this.colors[20] = "#4682B4";
		this.colors[21] = "#1E90FF";
		this.colors[22] = "#9400D3";
		this.colors[23] = "#FF1493";
		this.colors[24] = "#A9A9A9";
		this.colors[25] = "#FF6347";
		this.colors[26] = "#FFD700";
		this.colors[27] = "#32CD32";
		this.colors[28] = "#87CEEB";
		this.colors[29] = "#00BFFF";
		this.colors[30] = "#9370DB";
		this.colors[31] = "#FF69B4";
		this.colors[32] = "#DCDCDC";
		this.colors[33] = "#FFDAB9";
		this.colors[34] = "#FFFFE0";
		this.colors[35] = "#98FB98";
		this.colors[36] = "#E0FFFF";
		this.colors[37] = "#87CEFA";
		this.colors[38] = "#E6E6FA";
		this.colors[39] = "#DDA0DD";
		this.colors[40] = "#FFFFFF";
		
		// An array of video services to be shown (youtube, vimeo, etc) 
		this.videos = new Object();
		this.videos["dailymotion"] = this.options.lang.video_dailymotion;
		this.videos["metacafe"] = this.options.lang.video_metacafe;
		this.videos["myspacetv"] = this.options.lang.video_myspacetv;
		this.videos["vimeo"] = this.options.lang.video_vimeo;
		this.videos["yahoo"] = this.options.lang.video_yahoo;
		this.videos["youtube"] = this.options.lang.video_youtube;

		// Here we get the ID of the textarea we're replacing and store it.
		this.textarea = textarea;

		// Only swap it over once the page has loaded (add event)
		if(MyBB.page_loaded == 1)
		{
			this.showEditor();
		}
		else
		{
			Event.observe(document, "dom:loaded", this.showEditor.bindAsEventListener(this));
		}
	},

	showEditor: function()
	{
		// Assign the old textarea to a variable for later use.
		oldTextarea = $(this.textarea);

		// Now this.textarea becomes the new textarea ID
		this.textarea += "_new";

		// Begin the creation of our new editor.

		this.editor = document.createElement("div");
		this.editor.style.position = "relative";
		this.editor.style.display = "none";
		this.editor.className = "messageEditor";

		// Append the new editor
		oldTextarea.parentNode.insertBefore(this.editor, oldTextarea);

		// Determine the overall height and width - messy, but works
		w = oldTextarea.getDimensions().width+"px";
		if(!w || parseInt(w) < 400)
		{
			w = "400px";
		}
		if(this.options && this.options.height)
		{
			h = this.options.height;
		}
		else if(oldTextarea.offsetHeight)
		{
			h = oldTextarea.offsetHeight+"px";
		}
		else if(oldTextarea.clientHeight)
		{
			h = oldTextarea.clientHeight+"px";
		}
		else if(oldTextarea.style.height)
		{
			h = oldTextarea.style.height;
		}
		else
		{
			h = "400px";
		}
		this.editor.style.width = w;
		this.editor.style.height = h;

		this.createToolbarContainer('top');

		this.createToolbar('closetags', {
			container: 'top',
			alignment: 'right',
			items: [
				{type: 'button', name: 'close_tags', insert: 'zzzz', sprite: 'close_tags', width: 80, style: {visibility: 'hidden'}}
			]
		});
		this.createToolbar('topformatting', {
			container: 'top',
			items: [
				{type: 'dropdown', name: 'font', insert: 'font', title: this.options.lang.font, options: this.fonts},
				{type: 'dropdown', name: 'size', insert: 'size', title: this.options.lang.size, options: this.sizes},
				{type: 'button', name: 'color', insert: 'color', dropdown: true, color_select: true, image: 'color.gif', draw_option: this.drawColorOption, options: this.colors}
			]
		});

		this.createToolbarContainer('bottom');

		this.createToolbar('insertables', {
			container: 'bottom',
			alignment: 'right',
			items: [
				{type: 'button', name: 'list_num', sprite: 'list_num', insert: 'list', extra: 1, title: this.options.lang.title_numlist},
				{type: 'button', name: 'list_bullet', sprite: 'list_bullet', insert: 'list', title: this.options.lang.title_bulletlist},
				{type: 'separator'},
				{type: 'button', name: 'img', sprite: 'image', insert: 'image', extra: 1, title: this.options.lang.title_image},
				{type: 'button', name: 'url', sprite: 'link', insert: 'url', title: this.options.lang.title_hyperlink},
				{type: 'button', name: 'email', sprite: 'email', insert: 'email', extra: 1, title: this.options.lang.title_email},
				{type: 'separator'},
				{type: 'button', name: 'quote', sprite: 'quote', insert: 'quote', title: this.options.lang.title_quote},
				{type: 'button', name: 'code', sprite: 'code', insert: 'code', title: this.options.lang.title_code},
				{type: 'button', name: 'php', sprite: 'php', insert: 'php', title: this.options.lang.title_php},
				{type: 'button', name: 'video', insert: 'video', image: 'television.gif', dropdown: true, title: this.options.lang.title_video, options: this.videos}
			]
		});
		this.createToolbar('formatting', {
			container: 'bottom',
			items: [
				{type: 'button', name: 'b', sprite: 'bold', insert: 'b', title: this.options.lang.title_bold},
				{type: 'button', name: 'i', sprite: 'italic', insert: 'i', title: this.options.lang.title_italic},
				{type: 'button', name: 'u', sprite: 'underline', insert: 'u', title: this.options.lang.title_underline},
				{type: 'separator'},
				{type: 'button', name: 'align_left', sprite: 'align_left', insert: 'align', extra: 'left', title: this.options.lang.title_left},
				{type: 'button', name: 'align_center', sprite: 'align_center', insert: 'align', extra: 'center', title: this.options.lang.title_center},
				{type: 'button', name: 'align_right', sprite: 'align_right', insert: 'align', extra: 'right', title: this.options.lang.title_right},
				{type: 'button', name: 'align_justify', sprite: 'align_justify', insert: 'align', extra: 'justify', title: this.options.lang.title_justify}
			]
		});

		// Create our new text area
		areaContainer = document.createElement("div");
		areaContainer.style.clear = "both";

		// Set the width/height of the area
		subtract = 20;
		subtract2 = 12;
		areaContainer.style.height = parseInt(Element.getDimensions(this.editor).height)-this.toolbarHeight-subtract+"px";
		areaContainer.style.width = parseInt(Element.getDimensions(this.editor).width)-subtract2+"px";

		// Create text area
		textInput = document.createElement("textarea");
		textInput.setAttribute("cols", oldTextarea.getAttribute("cols"));
		textInput.setAttribute("rows", oldTextarea.getAttribute("rows"));
		textInput.id = this.textarea;
		textInput.name = oldTextarea.name+"_new";
		textInput.style.height = parseInt(areaContainer.style.height)+"px";
		textInput.style.width = parseInt(areaContainer.style.width)+"px";

		if(oldTextarea.value != '')
		{
			textInput.value = oldTextarea.value;
		}

		if(oldTextarea.tabIndex)
		{
			textInput.tabIndex = oldTextarea.tabIndex;
		}

		areaContainer.appendChild(textInput);
		this.editor.appendChild(areaContainer);

		if(oldTextarea.form)
		{
			Event.observe(oldTextarea.form, "submit", this.closeTags.bindAsEventListener(this));
			Event.observe(oldTextarea.form, "submit", this.updateOldArea.bindAsEventListener(this));
		}

		// Hide the old editor
		oldTextarea.style.visibility = "hidden";
		oldTextarea.style.position = "absolute";
		oldTextarea.style.top = "-1000px";
		oldTextarea.id += "_old";
		this.oldTextarea = oldTextarea;

		this.editor.style.display = "";
		Event.observe(textInput, "keyup", this.updateOldArea.bindAsEventListener(this));

		if(MyBB.browser == 'ie') {
			Event.observe($(this.textarea), 'focus', function() {
				this.trackingCaret = true;
			}.bindAsEventListener(this));
			Event.observe($(this.textarea), 'blur', function() {
				this.trackingCaret = false;
			}.bindAsEventListener(this));
			Event.observe($(this.textarea), 'mousedown', function() {
				this.trackingCaret = true;
				this.storeCaret();
			}.bindAsEventListener(this));
		}

		Event.observe(textInput, "blur", this.updateOldArea.bindAsEventListener(this));
	},

	drawColorOption: function(option)
	{
		var item = document.createElement('li');
		item.extra = option.value;
		item.className = 'editor_dropdown_color_item';
		item.innerHTML = '<a style="background-color: '+option.value+'"></a>';
		return item;
	},

	createToolbarContainer: function(name)
	{
		if($('editor_toolbar_container_'+name)) return;

		var container = document.createElement("div");
		container.id = 'editor_toolbar_container_'+name;
		container.className = 'toolbar_container';

		this.editor.appendChild(container);

		this.toolbarHeight += 28;

		return container;
	},

	createToolbar: function(name, options)
	{
		if(typeof(options.container) == 'undefined')
		{
			options.container = this.createToolbarContainer('auto_'+name);
		}
		else {
			options.container = $('editor_toolbar_container_'+options.container);
			if(!options.container) return;
		}

		if($('editor_toolbar_'+name)) return;

		var toolbar = document.createElement('div');
		toolbar.id = 'editor_toolbar_'+name;
		toolbar.className = 'toolbar';

		var clear = document.createElement('br');
		clear.style.clear = 'both';
		toolbar.appendChild(clear);

		if(options.alignment && options.alignment == 'right') {
			toolbar.className += ' float_right';
		}
		options.container.appendChild(toolbar);
		if(typeof(options.items) == 'object') {
			for(var i = 0; i < options.items.length; ++i) {
				this.addToolbarItem(toolbar, options.items[i]);
			}
		}
		// add closing item
		if(toolbar.lastChild.previousSibling)
			toolbar.lastChild.previousSibling.className += ' toolbar_button_group_last';
	},
	
	setElementState: function(element, state) {
		element.addClassName('toolbar_'+state);
		
		if(element.hasClassName('toolbar_button_group_first')) {
			if(state == 'clicked') {
				append = 'toolbar_clicked';
			}
			else if(state == 'hover') {
				append = 'toolbar_hover';
			}
			append += '_button_group_first';
			element.addClassName(append);
		}
		
		if(element.hasClassName('toolbar_button_group_last')) {
			if(state == 'clicked') {
				append = 'toolbar_clicked';
			}
			else if(state == 'hover') {
				append = 'toolbar_hover';
			}
			append += '_button_group_last';
			element.addClassName(append);
		}
	},
	
	removeElementState: function(element, state)
	{
		element.removeClassName('toolbar_'+state);
		
		if(element.hasClassName('toolbar_button_group_first')) {
			if(state == 'clicked') {
				append = 'toolbar_clicked';
			}
			else if(state == 'hover') {
				append = 'toolbar_hover';
			}
			append += '_button_group_first';
			element.removeClassName(append);
		}
		
		if(element.hasClassName('toolbar_button_group_last')) {
			if(state == 'clicked') {
				append = 'toolbar_clicked';
			}
			else if(state == 'hover') {
				append = 'toolbar_hover';
			}
			append += '_button_group_last';
			element.removeClassName(append);
		}	
	},

	dropDownMenuItemClick: function(e)
	{
		this.restartEditorSelection();
		element = Event.element(e);

		if(!element)
			return;
		
		if(!element.extra)
			element = element.up('li');
		
		var mnu = element.up('ul');
		var dropdown = this.getElementToolbarItem(mnu);
		var label = dropdown.down('.editor_dropdown_label');

		if(!dropdown.insertText || (dropdown.insertText != "video" && mnu.activeItem && mnu.activeItem == element))
			return;
		
		mnu.lastItemValue = element.extra;

		if(this.getSelectedText($(this.textarea)))
		{
			this.setDropDownMenuActiveItem(dropdown, 0);
		}
		else
		{
			if(label)
			{
				label.innerHTML = element.innerHTML;
				label.style.overflow = 'hidden';
			}
			var sel_color = dropdown.down('.editor_button_color_selected')
			if(sel_color)
			{
				sel_color.style.backgroundColor = element.extra;
				var use_default = dropdown.down('.editor_dropdown_color_item_default');
				if(use_default) use_default.style.display = '';
			}
			mnu.activeItem = element;
			element.addClassName('editor_dropdown_menu_item_active');
		}

		this.insertMyCode(dropdown.insertText, element.extra);
		this.hideOpenDropDownMenu();
		Event.stop(e);
	},

	setDropDownMenuActiveItem: function(element, index)
	{
		if(element == null)
		{
			return;
		}
		var mnu = element.down('ul');
		var label = element.down('.editor_dropdown_label');

		if(mnu.activeItem)
		{
			mnu.activeItem.removeClassName('editor_dropdown_menu_item_active');
			mnu.activeItem = null;
		}

		if(index > 0)
		{
			var item = mnu.childNodes[index];
			if(!item) return;
			mnu.activeItem = item;
			if(label)
			{
				label.innerHTML = item.innerHTML;
			}

			var sel_color = element.down('.editor_dropdown_color_selected')
			if(sel_color)
			{
				sel_color.style.backgroundColor = item.style.backgroundColor;
				mnu.lastItemValue = item.insertExtra;
				var use_default = element.down('.editor_dropdown_color_item_default');
				if(use_default) use_default.style.display = '';
			}
			item.addClassName('editor_dropdown_menu_item_active');
		}
		else
		{
			if(label)
			{
				label.innerHTML = mnu.childNodes[0].innerHTML;
			}

			var sel_color = element.down('.editor_button_color_selected')
			if(sel_color)
			{
				//sel_color.style.backgroundColor = '';
				var use_default = element.down('.editor_dropdown_color_item_default');
				if(use_default) use_default.style.display = 'none';
			}
			this.removeElementState(element, 'clicked');
		}
	},

	createDropDownMenu: function(options)
	{
		var dropdown = document.createElement('div');
		dropdown.itemType = options.type;
		if(options.image || options.sprite)
			dropdown.className = 'toolbar_dropdown_image';
		else
			dropdown.className = 'toolbar_dropdown';

		dropdown.className += ' editor_dropdown toolbar_dropdown_'+options.name;
		dropdown.id = 'editor_item_'+options.name;

		Event.observe(dropdown, 'mouseover', function()
		{
			this.storeCaret();
			dropdown.addClassName('toolbar_dropdown_over');
		}.bindAsEventListener(this));
		Event.observe(dropdown, 'mouseout', function()
		{
			this.storeCaret();
			dropdown.removeClassName('toolbar_dropdown_over');
		}.bindAsEventListener(this));
		dropdown.insertText = options.insert;

		// create the dropdown label container
		var label = document.createElement('div');
		label.className = 'editor_dropdown_label';
		if(options.title)
		{
			label.innerHTML = options.title;
		}
		else
		{
			label.innerHTML = '&nbsp;';
		}
		dropdown.appendChild(label)

		// create the arrow
		var arrow = document.createElement('div');
		arrow.className = 'editor_dropdown_arrow';
		dropdown.appendChild(arrow);

		// create the menu item container
		var mnu = this.buildDropDownMenu(options);

		Event.observe(dropdown, 'click', this.toggleDropDownMenu.bindAsEventListener(this));
		dropdown.appendChild(mnu);
		return dropdown;
	},

	buildDropDownMenu: function(options)
	{
		var mnu = document.createElement('ul');
		mnu.className = 'editor_dropdown_menu';
		mnu.style.display = 'none';

		// create the first item
		if(options.title)
		{
			var item = document.createElement('li');
			item.className = 'editor_dropdown_menu_title';
			item.innerHTML = options.title;
			mnu.appendChild(item);
			Event.observe(item, 'click', function()
			{
				if(mnu.activeItem)
				{
					this.restartEditorSelection();
					this.insertMyCode(dropdown.insertText, '-');
				}
				this.setDropDownMenuActiveItem(dropdown, 0);
			}.bindAsEventListener(this));
		}
		
		$H(options.options).each(function(option)
		{
			if(options.draw_option)
			{
				item = options.draw_option(option)
			}
			else
			{
				var item = document.createElement('li');
				item.innerHTML = option.value;

				var content = document.createElement('span');
				item.appendChild(content);
				item.extra = option.key;
			}
			Event.observe(item, 'click', this.dropDownMenuItemClick.bindAsEventListener(this));
			Event.observe(item, 'mouseover', function()
			{
				item.addClassName('editor_dropdown_menu_item_over');
			});
			Event.observe(item, 'mouseout', function()
			{
				item.removeClassName('editor_dropdown_menu_item_over');
			});
			mnu.appendChild(item);
		}, this);
		return mnu;
	},

	toggleDropDownMenu: function(e)
	{
		element = Event.element(e);
		if(!element)
			return;
		if(!element.itemType)
			element = this.getElementToolbarItem(element);
		
		var mnu = $(element).down('ul');
		
		// This menu is already open, close it
		if(mnu.style.display != 'none')
		{
			mnu.style.display = 'none';
			element.removeClassName('editor_dropdown_menu_open');
			this.removeElementState(element, 'clicked');
			this.openDropDownMenu = null;
			Event.stopObserving(document, 'click', this.hideOpenDropDownMenu.bindAsEventListener(this));
		}
		// Opening this menu
		else
		{
			// If a menu is already open, close it first
			this.showDropDownMenu(mnu);
		}
		this.removeElementState(element, 'clicked');
		Event.stop(e);
	},

	showDropDownMenu: function(mnu)
	{
		this.hideOpenDropDownMenu();
		mnu.style.display = '';
		element = this.getElementToolbarItem(mnu);
		element.addClassName('editor_dropdown_menu_open');
		this.setElementState(element, 'clicked');
		this.openDropDownMenu = mnu;
		Event.observe(document, 'click', this.hideOpenDropDownMenu.bindAsEventListener(this));
	},

	hideOpenDropDownMenu: function()
	{
		if(!this.openDropDownMenu) return;
		this.openDropDownMenu.style.display = 'none';
		this.getElementToolbarItem(this.openDropDownMenu).removeClassName('editor_dropdown_menu_open');
		var dropDown = this.getElementToolbarItem(this.openDropDownMenu);
		this.removeElementState(this.openDropDownMenu.parentNode, 'clicked');
		this.removeElementState(element, 'clicked');
		this.openDropDownMenu = null;
		Event.stopObserving(document, 'click', this.hideOpenDropDownMenu.bindAsEventListener(this));
	},

	getElementToolbarItem: function(elem)
	{
		var parent = elem;
		do {
			if(parent.insertText) return parent;
			parent = parent.parentNode;
		} while($(parent));

		return false;
	},

	storeCaret: function()
	{
		if(MyBB.browser != 'ie' || !this.trackingCaret)
		{
			return;
		}
		
		// Internet explorer errors if you try and select an element... so just handle that by try catch
		try {
			var range = document.selection.createRange();
			var range_all = document.body.createTextRange();
			range_all.moveToElementText($(this.textarea));
			for(var sel_start = 0; range_all.compareEndPoints('StartToStart', range) < 0; sel_start++)
				range_all.moveStart('character', 1);

			var range_all = document.body.createTextRange();
			range_all.moveToElementText($(this.textarea));
			for(var sel_end = 0; range_all.compareEndPoints('StartToEnd', range) < 0; sel_end++)
				range_all.moveStart('character', 1);

			this.lastCaretS = sel_start;
			this.lastCaretE = sel_end;
		} catch(e) { }
	},

	restartEditorSelection: function()
	{
		if(MyBB.browser != 'ie')
		{
			return;
		}

		var range = $(this.textarea).createTextRange();
		range.collapse(true);
		range.moveStart('character', this.lastCaretS);
		range.moveEnd('character', this.lastCaretE - this.lastCaretS);
		range.select();
	},

	addToolbarItem: function(toolbar, options)
	{
		if(typeof(toolbar) == 'string')
		{
			toolbar = $('editor_toolbar_'+toolbar);
		}

		if(!$(toolbar)) return;

		// Does this item already exist?
		if($('editor_item_'+options.name)) return;

		insert_first_class = false;

		// Is this the first item? childnodes = 1 (closing br) or lastchild.previousSibling = sep
		if(toolbar.childNodes.length == 1 || (toolbar.lastChild.previousSibling && toolbar.lastChild.previousSibling.className.indexOf('toolbar_sep') > -1 || (toolbar.lastChild.previousSibling.className.indexOf('editor_dropdown') > -1 && options.type != 'dropdown')))
		{
			insert_first_class = true;
		}

		if(options.type == "dropdown")
		{
			var dropdown = this.createDropDownMenu(options);
			if(dropdown)
				toolbar.insertBefore(dropdown, toolbar.lastChild);

			if(insert_first_class == true)
				dropdown.className += ' toolbar_dropdown_group_first';
		}
		else if(options.type == 'button')
		{
			var button = this.createToolbarButton(options)
			toolbar.insertBefore(button, toolbar.lastChild);

			if(insert_first_class == true)
				button.className += ' toolbar_button_group_first';
		}
		else if(options.type == 'separator')
		{
			if(toolbar.lastChild.previousSibling && !$(toolbar.lastChild.previousSibling).hasClassName('toolbar_dropdown'))
			{
				toolbar.lastChild.previousSibling.className += ' toolbar_button_group_last';
			}
			var separator = document.createElement("span");
			separator.itemType = options.type;
			separator.className = "toolbar_sep";
			toolbar.insertBefore(separator, toolbar.lastChild);
		}
	},

	createToolbarButton: function(options)
	{
		var button = document.createElement('span');
		button.itemType = options.type;
		button.id = 'editor_item_'+options.name;
		if(typeof(options.title) != 'undefined')
		{
			button.title = options.title;
		}
		button.className = 'toolbar_button toolbar_normal toolbar_button_'+options.name;

		if(typeof(options.style) == 'object')
		{
			$H(options.style).each(function(item) {
				eval('button.style.'+item.key+' = "'+item.value+'";');
			});
		}
		button.insertText = options.insert;
		button.insertExtra = '';
		if(typeof(options.extra) != 'undefined')
			button.insertExtra = options.extra;
		
		if(typeof(options.sprite) != 'undefined')
		{
			var img = document.createElement('span');
			img.className = 'toolbar_sprite toolbar_sprite_'+options.sprite;
		}
		else
		{
			var img = document.createElement('img');
			img.src = this.themePath + "/images/" + options.image;
		}
		button.appendChild(img);

		if(options.dropdown)
		{
			if(options.color_select == true)
			{
				var sel = document.createElement('em');
				sel.className = 'editor_button_color_selected';
				button.appendChild(sel);
			}
			// create the arrow
			var arrow = document.createElement('u');
			arrow.className = 'toolbar_button_arrow';
			button.appendChild(arrow);
			button.className += ' toolbar_button_with_arrow';
		}

		var end = document.createElement('strong');
		button.appendChild(end);

		// Create the actual drop down menu
		if(options.dropdown)
		{
			// create the menu item container
			var mnu = this.buildDropDownMenu(options);

			Event.observe(arrow, 'click', this.toggleDropDownMenu.bindAsEventListener(this));
			Event.observe(button, 'click', this.toggleDropDownMenu.bindAsEventListener(this));
			Event.observe(arrow, 'mouseover', function(e)
			{
				elem = Event.element(e);
				if(!elem) return;
				elem.parentNode.addClassName('toolbar_button_over_arrow');
			});
			Event.observe(arrow, 'mouseout', function(e)
			{
				elem = Event.element(e);
				if(!elem) return;
				elem.parentNode.removeClassName('toolbar_button_over_arrow');
			});
			button.appendChild(mnu);
			button.dropdown = true;
			button.menu = mnu;
		}

		// Does this button have enabled/disabled states?
		if(options.disabled_img || options.disabled_sprite)
		{
			button.disable = function()
			{
				if(button.disabled == true) return;

				if(options.disabled_sprite)
				{
					img.removeClassName('toolbar_sprite_'+options.sprite);
					img.addClassName('toolbar_sprite_disabled_'+options.disabled_sprite);
				}
				else
					img.src = this.themePath + '/images/' + options.disabled_img;

				button.disabled = true;
			};

			button.enable = function()
			{
				if(!button.disabled) return;

				if(options.disabled_sprite)
				{
					img.removeClassName('toolbar_sprite_disabled_'+options.disabled_sprite);
					img.addClassName('toolbar_sprite_'+options.sprite);
				}
				else
					img.src = this.themePath + '/images/' + options.image;

				button.enabled = true;
			};

			if(options.disabled && options.disabled == true)
			{
				button.disable();
				button.disabled = true;
			}
			else
				button.disabled = false;
		}

		Event.observe(button, "mouseover", this.toolbarItemHover.bindAsEventListener(this));
		Event.observe(button, "mouseout", this.toolbarItemOut.bindAsEventListener(this));

		if(!options.dropdown)
		{
			// Dropdown event listener is above...
			Event.observe(button, "click", this.toolbarItemClick.bindAsEventListener(this));
		}
		return button;
	},

	updateOldArea: function(e)
	{
		this.oldTextarea.value = $(this.textarea).value;
	},

	toolbarItemOut: function(e)
	{
		this.storeCaret();
		element = Event.element(e);

		if(!element)
			return false;

		if(!element.itemType)
			element = 	this.getElementToolbarItem(element);

		if(element.disabled)
			return;

		if(typeof(element.insertText) != 'undefined')
		{
			if(element.insertExtra)
			{
				insertCode = element.insertText+"_"+element.insertExtra;
			}
			else
			{
				insertCode = element.insertText;
			}

			if(this.openTags.indexOf(insertCode) != -1 || element.className.indexOf('editor_dropdown_menu_open') > -1)
			{
				this.setElementState(element, 'clicked');
			}
		}
		this.removeElementState(element, 'hover');
	},

	toolbarItemHover: function(e)
	{
		this.storeCaret();
		element = Event.element(e);
		if(!element)
			return false;

		if(!element.itemType)
			element = this.getElementToolbarItem(element);

		if(element.disabled)
			return;

		if(!element.className || element.className.indexOf('toolbar_clicked') == -1)
			this.setElementState(element, 'hover');
	},

	toolbarItemClick: function(e)
	{
		element = Event.element(e);

		if(!element)
			return false;

		if(!element.itemType)
			element = this.getElementToolbarItem(element);

		if(element.disabled)
			return;

		if(element.dropdown && element.menu)
		{
			if(typeof(element.menu.activeItem) != "undefined")
			{
				Event.stop(e);
				if(!element.menu.lastItemValue)
				{
					this.showDropDownMenu(element.menu);
				}
				else
				{
					this.insertMyCode(element.insertText, element.menu.lastItemValue);
				}

				return;
			}
		}

		if(element.id == "editor_item_close_tags")
		{
			this.closeTags();
		}
		else
		{
			if(typeof(element.insertExtra) != 'undefined')
				this.insertMyCode(element.insertText, element.insertExtra);
			else
				this.insertMyCode(element.insertText);
		}
	},

	insertList: function(type)
	{
		list = "";

		do
		{
			listItem = prompt(this.options.lang.enter_list_item, "");

			if(listItem != "" && listItem != null)
			{
				list = list+"[*]"+listItem+"\n";
			}
		}
		while(listItem != "" && listItem != null);

		if(list == "")
		{
			return false;
		}

		if(type)
		{
			list = "[list="+type+"]\n"+list;
		}
		else
		{
			list = "[list]\n"+list;
		}

		list = list+"[/list]\n";
		this.performInsert(list, "", true, false);
	},

	insertURL: function()
	{
		selectedText = this.getSelectedText($(this.textarea));
		url = prompt(this.options.lang.enter_url, "http://");

		if(url)
		{
			if(!selectedText)
			{
				title = prompt(this.options.lang.enter_url_title, "");
			}
			else
			{
				title = selectedText;
			}

			if(title)
			{
				this.performInsert("[url="+url+"]"+title+"[/url]", "", true, false);
			}
			else
			{
				this.performInsert("[url]"+url+"[/url]", "", true, false);
			}
		}
	},

	insertEmail: function()
	{
		selectedText = this.getSelectedText($(this.textarea));
		email = prompt(this.options.lang.enter_email, "");

		if(email)
		{
			if(!selectedText)
			{
				title = prompt(this.options.lang.enter_email_title, "");
			}
			else
			{
				title = selectedText;
			}

			if(title)
			{
				this.performInsert("[email="+email+"]"+title+"[/email]", "", true, false);
			}
			else
			{
				this.performInsert("[email]"+email+"[/email]", "", true, false);
			}
		}
	},

	insertIMG: function()
	{
		image = prompt(this.options.lang.enter_image, "http://");

		if(image)
		{
			this.performInsert("[img]"+image+"[/img]", "", true);
		}
	},
	
	insertVideo: function(type)
	{
		selectedText = this.getSelectedText($(this.textarea));

		if(!selectedText)
		{
			url = prompt(this.options.lang.enter_video_url, "http://");
		}
		else
		{
			url = selectedText;
		}

		if(url)
		{
			this.performInsert("[video="+type+"]"+url+"[/video]", "", true, false);
		}
		this.setDropDownMenuActiveItem($('editor_item_video'), 0);
	},

	insertMyCode: function(code, extra)
	{
		this.restartEditorSelection();

		switch(code)
		{
			case "list":
				this.insertList(extra);
				break;
			case "url":
				this.insertURL();
				break;
			case "image":
				this.insertIMG();
				break;
			case "email":
				this.insertEmail();
				break;
			case "video":
				this.insertVideo(extra);
				break;
			default:
				var already_open = false;
				var no_insert = false;
				if(extra)
				{
					var full_tag = code+"_"+extra;
				}
				else
				{
					var full_tag = code;
				}

				var newTags = new Array();
				this.openTags.each(function(tag)
				{
					exploded_tag = tag.split("_");
					if(exploded_tag[0] == code)
					{
						already_open = true;
						this.performInsert("[/"+exploded_tag[0]+"]", "", false);
						var elem = $('editor_item_'+exploded_tag[0]);

						if(elem)
						{
							this.removeElementState(elem, 'clicked');
						}

						if(elem && (elem.itemType == "dropdown" || elem.dropdown || elem.menu))
						{
							this.setDropDownMenuActiveItem(elem, 0);
						}

						if(tag == full_tag)
						{
							no_insert = true;
						}
					}
					else
					{
						newTags[newTags.length] = tag;
					}
				}.bind(this));

				this.openTags = newTags;
				var do_insert = false;

				if(extra != "" && extra != "-" && no_insert == false)
				{
					start_tag = "["+code+"="+extra+"]";
					end_tag = "[/"+code+"]";
					do_insert = true;
				}
				else if(!extra && already_open == false)
				{
					start_tag = "["+code+"]";
					end_tag = "[/"+code+"]";
					do_insert = true;
				}

				if(do_insert == true)
				{
					if(!this.performInsert(start_tag, end_tag, true))
					{
						this.openTags.push(full_tag);
						$('editor_item_close_tags').style.visibility = '';
					}
					else if($('editor_item_'+full_tag))
					{
						this.removeElementState($('editor_item_'+full_tag), 'clicked');
					}
					else if($('editor_item_'+code))
					{
						elem = $('editor_item_'+code);
						if(elem.type == "dropdown" || elem.dropdown || elem.menu)
							this.setDropDownMenuActiveItem($('editor_item_'+start_tag), 0);
					}
				}
		}

		if(this.openTags.length == 0)
		{
			$('editor_item_close_tags').style.visibility = 'hidden';
		}
	},

	getSelectedText: function(element)
	{
		element.focus();
		if(document.selection)
		{
			var selection = document.selection;
			var range = selection.createRange();

			if((selection.type == "Text" || selection.type == "None") && range != null)
			{
				return range.text;
			}
		}
		else if(element.selectionEnd)
		{
			var select_start = element.selectionStart;
			var select_end = element.selectionEnd;
			if(select_end <= 0)
			{
				select_end = element.textLength;
			}
			var start = element.value.substring(0, select_start);
			var middle = element.value.substring(select_start, select_end);
			return middle;
		}
	},

	performInsert: function(open_tag, close_tag, is_single, ignore_selection)
	{
		var is_closed = true;

		if(!ignore_selection)
		{
			var ignore_selection = false;
		}

		if(!close_tag)
		{
			var close_tag = "";
		}
		var textarea = $(this.textarea);
		textarea.focus();

		if(document.selection)
		{
			var selection = document.selection;
			var range = selection.createRange();

			if(ignore_selection != false)
			{
				selection.collapse;
			}

			if((selection.type == "Text" || selection.type == "None") && range != null && ignore_selection != true)
			{
				if(close_tag != "" && range.text.length > 0)
				{
					var keep_selected = true;
					range.text = open_tag+range.text+close_tag;
				}
				else
				{
					var keep_selected = false;

					if(is_single)
					{
						is_closed = false;
					}
					range.text = open_tag;
				}
				range.select();
			}
			else
			{
				textarea.value += open_tag;
			}
		}
		else if(typeof(textarea.selectionEnd) != 'undefined')
		{
			var select_start = textarea.selectionStart;
			var select_end = textarea.selectionEnd;
			var scroll_top = textarea.scrollTop;

			var start = textarea.value.substring(0, select_start);
			var middle = textarea.value.substring(select_start, select_end);
			var end = textarea.value.substring(select_end, textarea.textLength);

			if(select_end - select_start > 0 && ignore_selection != true && close_tag != "")
			{
				var keep_selected = true;
				middle = open_tag+middle+close_tag;
			}
			else
			{
				var keep_selected = false;
				if(is_single)
				{
					is_closed = false;
				}
				middle = open_tag;
			}

			textarea.value = start+middle+end;

			if(keep_selected == true && ignore_selection != true)
			{
				textarea.selectionStart = select_start;
				textarea.selectionEnd = select_start + middle.length;
			}
			else if(ignore_selection != true)
			{
				textarea.selectionStart = select_start + middle.length;
				textarea.selectionEnd = textarea.selectionStart;
			}
			textarea.scrollTop = scroll_top;
		}
		else
		{
			textarea.value += open_tag;

			if(is_single)
			{
				is_closed = false;
			}
		}
		this.updateOldArea();
		textarea.focus();
		this.trackingCaret = true;
		this.storeCaret();
		this.trackingCaret = false;		
		return is_closed;
	},

	closeTags: function()
	{
		if(this.openTags[0])
		{
			while(this.openTags[0])
			{
				tag = this.openTags.pop();
				exploded_tag = tag.split("_");
				this.performInsert("[/"+exploded_tag[0]+"]", "", false);

				if($('editor_item_'+exploded_tag[0]))
				{
					tag = $('editor_item_'+exploded_tag[0]);
				}
				else
				{
					tag = $('editor_item_'+tag);
				}
				if(tag)
				{
					if(tag.itemType == "dropdown" || tag.dropdown || tag.menu)
					{
						this.setDropDownMenuActiveItem(tag, 0);
					}
					else
					{
						this.removeElementState(tag, 'clicked');
					}
				}
			}
		}
		$(this.textarea).focus();
		$('editor_item_close_tags').style.visibility = 'hidden';
		this.openTags = new Array();
	},

	bindSmilieInserter: function(id)
	{
		if(!$(id))
		{
			return false;
		}

		var smilies = $(id).select('.smilie');

		if(smilies.length > 0)
		{
			smilies.each(function(smilie)
			{
				smilie.onclick = this.insertSmilie.bindAsEventListener(this);
				smilie.style.cursor = "pointer";
			}.bind(this));
		}
	},

	openGetMoreSmilies: function(editor)
	{
		MyBB.popupWindow('misc.php?action=smilies&popup=true&editor='+editor, 'sminsert', 240, 280);
	},

	insertSmilie: function(e)
	{
		element = Event.element(e);

		if(!element || !element.alt)
		{
			return false;
		}
		this.performInsert(element.alt, "", true, false);
	},

	insertAttachment: function(aid)
	{
		this.performInsert("[attachment="+aid+"]", "", true, false);
	}
};