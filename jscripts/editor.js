var messageEditor = Class.create();

messageEditor.prototype = {
	openTags: new Array(),

	initialize: function(textarea, options)
	{
		// Sorry Konqueror, but due to a browser bug out of control with textarea values
		// you do not get to use the fancy editor.
		if(MyBB.browse == "konqueror")
		{
			return false;
		}
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
		
		// Defines an array of fonts to be shown in the font drop down.
		this.fonts = new Array();
		this.fonts["Arial"] = "Arial";
		this.fonts["Courier"] = "Courier";
		this.fonts["Impact"] = "Impact";
		this.fonts["Tahoma"] = "Tahoma";
		this.fonts["Times New Roman"] = "Times New Roman";
		this.fonts["Trebuchet MS"] = "Trebuchet MS";
		this.fonts["Verdana"] = "Verdana";

		// An array of font sizes to be shown.
		this.sizes = new Array();
		this.sizes["xx-small"] = this.options.lang.size_xx_small;
		this.sizes["x-small"] = this.options.lang.size_x_small;
		this.sizes["small"] = this.options.lang.size_small;
		this.sizes["medium"] = this.options.lang.size_medium;
		this.sizes["large"] = this.options.lang.size_large;
		this.sizes["x-large"] = this.options.lang.size_x_large;
		this.sizes["xx-large"] = this.options.lang.size_xx_large;

		// An array of colours to be shown.
		this.colors = new Array();
		this.colors["#ffffff"] = this.options.lang.color_white;
		this.colors["#000000"] = this.options.lang.color_black;
		this.colors["#FF0000"] = this.options.lang.color_red;
		this.colors["#FFFF00"] = this.options.lang.color_yellow;
		this.colors["#FFC0CB"] = this.options.lang.color_pink;
		this.colors["#008000"] = this.options.lang.color_green;
		this.colors["#FFA500"] = this.options.lang.color_orange;
		this.colors["#800080"] = this.options.lang.color_purple;
		this.colors["#0000FF"] = this.options.lang.color_blue;
		this.colors["#F5F5DC"] = this.options.lang.color_beige;
		this.colors["#A52A2A"] = this.options.lang.color_brown;
		this.colors["#008080"] = this.options.lang.color_teal;
		this.colors["#000080"] = this.options.lang.color_navy;
		this.colors["#800000"] = this.options.lang.color_maroon;
		this.colors["#32CD32"] = this.options.lang.color_limegreen; 

		// Here we get the ID of the textarea we're replacing and store it.
		this.textarea = textarea;
		
		// Only swap it over once the page has loaded (add event)
		Event.observe(window, "load", this.showEditor.bindAsEventListener(this));
	},
	
	showEditor: function()
	{
		// Assign the old textarea to a variable for later use.
		oldTextarea = $(this.textarea);

		// Now this.textarea becomes the new textarea ID
		this.textarea += "_new";

		// Begin the creation of our new editor.

		editor = document.createElement("div");
		editor.style.position = "relative";
		editor.className = "editor";

		// Determine the overall height and width - messy, but works
		if(this.options && this.options.width)
		{
			w = this.options.width;
		}
		else if(oldTextarea.style.width)
		{
			w = oldTextarea.style.width;
		}
		else if(oldTextarea.clientWidth)
		{
			w = oldTextarea.clientWidth+"px";
		}
		else
		{
			w = "560px";
		}
		
		if(this.options && this.options.height)
		{
			h = this.options.height;
		}
		else if(oldTextarea.style.height)
		{
			h = oldTextarea.style.height;
		}
		else if(oldTextarea.clientHeight)
		{
			h = oldTextarea.clientHeight+"px";
		}
		else
		{
			h = "400px";
		}
		editor.style.width = w;
		editor.style.height = h;
		editor.style.padding = "3px";

		// Create the first toolbar
		toolBar = document.createElement("div");
		toolBar.style.height = "26px";
		toolBar.style.position = "relative";
		
		// Create text font/color/size toolbar
		textFormatting = document.createElement("div");
		textFormatting.style.position = "absolute";
		textFormatting.style.width = "100%";

		
		if(this.options.rtl == 1)
		{
			textFormatting.style.right = 0;
		}
		else
		{
			textFormatting.style.left = 0;
		}
		toolBar.appendChild(textFormatting);

		// Create the font drop down.
		fontSelect = document.createElement("select");
		fontSelect.style.margin = "2px";
		fontSelect.id = "font";
		fontSelect.options[fontSelect.options.length] = new Option(this.options.lang.font, "-");
		
		for(font in this.fonts)
		{
			fontSelect.options[fontSelect.options.length] = new Option(this.fonts[font], font);
		}
		Event.observe(fontSelect, "change", this.changeFont.bindAsEventListener(this));
		textFormatting.appendChild(fontSelect);

		// Create the font size drop down.
		sizeSelect = document.createElement("select");
		sizeSelect.style.margin = "2px";
		sizeSelect.id = "size";
		sizeSelect.options[sizeSelect.options.length] = new Option(this.options.lang.size, "-");
		
		for(size in this.sizes)
		{
			sizeSelect.options[sizeSelect.options.length] = new Option(this.sizes[size], size);
		}
		Event.observe(sizeSelect, "change", this.changeSize.bindAsEventListener(this));
		textFormatting.appendChild(sizeSelect);

		// Create the colour drop down.
		colorSelect = document.createElement("select");
		colorSelect.style.margin = "2px";
		colorSelect.id = "color";
		colorSelect.options[colorSelect.options.length] = new Option(this.options.lang.color, "-");
		
		for(color in this.colors)
		{
			colorSelect.options[colorSelect.options.length] = new Option(this.colors[color], color);
			colorSelect.options[colorSelect.options.length-1].style.backgroundColor = color;
			colorSelect.options[colorSelect.options.length-1].style.color = color;
		}
		Event.observe(colorSelect, "change", this.changeColor.bindAsEventListener(this));
		textFormatting.appendChild(colorSelect);
		
		// Create close tags button
		closeBar = document.createElement("div");
		closeBar.style.position = "absolute";
		
		if(this.options.rtl == 1)
		{
			closeBar.style.left = 0;
		}
		else
		{
			closeBar.style.right = 0;
		}
		
		var closeButton = document.createElement("img");
		closeButton.id = "close_tags";
		closeButton.src = "images/codebuttons/close_tags.gif";
		closeButton.title = "";
		closeButton.className = "toolbar_normal";
		closeButton.height = 22;
		closeButton.width = 80;
		closeButton.style.margin = "2px";
		closeButton.style.visibility = 'hidden';
		Event.observe(closeButton, "mouseover", this.toolbarItemHover.bindAsEventListener(this));
		Event.observe(closeButton, "mouseout", this.toolbarItemOut.bindAsEventListener(this));
		Event.observe(closeButton, "click", this.toolbarItemClick.bindAsEventListener(this));
		closeBar.appendChild(closeButton);
		toolBar.appendChild(closeBar);
	
		// Append first toolbar to the editor
		editor.appendChild(toolBar);

		// Create the second toolbar.
		toolbar2 = document.createElement("div");
		toolbar2.style.height = "28px";
		toolbar2.style.position = "relative";

		// Create formatting section of second toolbar.
		formatting = document.createElement("div");
		formatting.style.position = "absolute";
		formatting.style.width = "100%";
		formatting.style.whiteSpace = "nowrap";
		
		if(this.options.rtl == 1)
		{
			formatting.style.right = 0;
		}
		else
		{
			formatting.style.left = 0;
		}
		toolbar2.appendChild(formatting);

		// Insert toolbar buttons.
		this.insertStandardButton(formatting, "b", "images/codebuttons/bold.gif", "b", "", this.options.lang.title_bold);
		this.insertStandardButton(formatting, "i", "images/codebuttons/italic.gif", "i", "", this.options.lang.title_italic);
		this.insertStandardButton(formatting, "u", "images/codebuttons/underline.gif", "u", "", this.options.lang.title_underline);
		this.insertSeparator(formatting);
		this.insertStandardButton(formatting, "align_left", "images/codebuttons/align_left.gif", "align", "left", this.options.lang.title_left);
		this.insertStandardButton(formatting, "align_center", "images/codebuttons/align_center.gif", "align", "center", this.options.lang.title_center);
		this.insertStandardButton(formatting, "align_right", "images/codebuttons/align_right.gif", "align", "right", this.options.lang.title_right);
		this.insertStandardButton(formatting, "align_justify", "images/codebuttons/align_justify.gif", "align", "justify", this.options.lang.title_justify);

		// Create insertable elements section of second toolbar.
		elements = document.createElement("div");
		elements.style.position = "absolute";
		
		if(this.options.rtl == 1)
		{
			elements.style.left = 0;
		}
		else
		{
			elements.style.right = 0;
		}

		toolbar2.appendChild(elements);
		this.insertStandardButton(elements, "list_num", "images/codebuttons/list_num.gif", "list", "1", this.options.lang.title_numlist);
		this.insertStandardButton(elements, "list_bullet", "images/codebuttons/list_bullet.gif", "list", "", this.options.lang.title_bulletlist);
		this.insertSeparator(elements);
		this.insertStandardButton(elements, "img", "images/codebuttons/image.gif", "image", "", this.options.lang.title_image);
		this.insertStandardButton(elements, "url", "images/codebuttons/link.gif", "url", "", this.options.lang.title_hyperlink);
		this.insertStandardButton(elements, "email", "images/codebuttons/email.gif", "email", "", this.options.lang.title_email);
		this.insertSeparator(elements);
		this.insertStandardButton(elements, "quote", "images/codebuttons/quote.gif", "quote", "", this.options.lang.title_quote);
		this.insertStandardButton(elements, "code", "images/codebuttons/code.gif", "code", "", this.options.lang.title_code);
		this.insertStandardButton(elements, "php", "images/codebuttons/php.gif", "php", "", this.options.lang.title_php);

		// Append the second toolbar to the editor
		editor.appendChild(toolbar2);

		// Create our new text area
		areaContainer = document.createElement("div");
		areaContainer.style.clear = "both";

		// Set the width/height of the area
		subtract = subtract2 = 0;
		if(MyBB.browser != "ie" || (MyBB.browser == "ie" && MyBB.useragent.indexOf('msie 7.') != -1))
		{
			subtract = subtract2 = 8;
		}
		areaContainer.style.height = parseInt(editor.style.height)-parseInt(toolBar.style.height)-parseInt(toolbar2.style.height)-subtract+"px";
		areaContainer.style.width = parseInt(editor.style.width)-subtract2+"px";
		
		// Create text area
		textInput = document.createElement("textarea");
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
		editor.appendChild(areaContainer);
		
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

		// Append the new editor
		oldTextarea.parentNode.insertBefore(editor, oldTextarea);

		Event.observe(textInput, "keyup", this.updateOldArea.bindAsEventListener(this));
		Event.observe(textInput, "blur", this.updateOldArea.bindAsEventListener(this));
	},
	
	updateOldArea: function(e)
	{
		this.oldTextarea.value = $(this.textarea).value;
	},


	insertStandardButton: function(into, id, src, insertText, insertExtra, alt)
	{
		var button = document.createElement("img");
		button.id = id;
		button.src = src;
		button.alt = alt;
		button.title = alt;
		button.insertText = insertText;
		button.insertExtra = insertExtra;
		button.className = "toolbar_normal";
		button.height = 22;
		button.width = 23;
		button.style.margin = "2px";
		Event.observe(button, "mouseover", this.toolbarItemHover.bindAsEventListener(this));
		Event.observe(button, "mouseout", this.toolbarItemOut.bindAsEventListener(this));
		Event.observe(button, "click", this.toolbarItemClick.bindAsEventListener(this));
		into.appendChild(button);
	},

	insertSeparator: function(into)
	{
		var separator = document.createElement("img");
		separator.style.margin = "2px";
		separator.src = "images/codebuttons/sep.gif";
		separator.style.verticalAlign = "top";
		separator.className = "toolbar_sep";
		into.appendChild(separator);
	},

	toolbarItemOut: function(e)
	{
		element = MyBB.eventElement(e);
		
		if(!element)
		{
			return false;
		}
		
		if(element.insertText)
		{
			if(element.insertExtra)
			{
				insertCode = element.insertText+"_"+element.insertExtra;
			}
			else
			{
				insertCode = element.insertText;
			}
			
			if(MyBB.inArray(insertCode, this.openTags))
			{
				DomLib.addClass(element, "toolbar_clicked");
			}
		}
		DomLib.removeClass(element, "toolbar_hover");
	},

	toolbarItemHover: function(e)
	{
		element = MyBB.eventElement(e);
		
		if(!element)
		{
			return false;
		}
		
		DomLib.addClass(element, "toolbar_hover");
	},


	toolbarItemClick: function(e)
	{
		element = MyBB.eventElement(e);
		
		if(!element)
		{
			return false;
		}
		
		if(element.id == "close_tags")
		{
			this.closeTags();
		}
		else
		{
			this.insertMyCode(element.insertText, element.insertExtra);
		}
	},

	changeFont: function(e)
	{
		element = MyBB.eventElement(e);
		
		if(!element)
		{
			return false;
		}
		
		this.insertMyCode("font", element.options[element.selectedIndex].value);
		
		if(this.getSelectedText($(this.textarea)))
		{
			element.selectedIndex = 0;
		}
	},

	changeSize: function(e)
	{
		element = MyBB.eventElement(e);
		
		if(!element)
		{
			return false;
		}
		
		this.insertMyCode("size", element.options[element.selectedIndex].value);
		
		if(this.getSelectedText($(this.textarea)))
		{
			element.selectedIndex = 0;
		}
	},

	changeColor: function(e)
	{
		element = MyBB.eventElement(e);
		
		if(!element)
		{
			return false;
		}
		
		this.insertMyCode("color", element.options[element.selectedIndex].value);
		
		if(this.getSelectedText($(this.textarea)))
		{
			element.selectedIndex = 0;
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

	insertMyCode: function(code, extra)
	{	
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
				for(var i=0; i< this.openTags.length; ++i)
				{
					if(this.openTags[i])
					{
						exploded_tag = this.openTags[i].split("_");
						
						if(exploded_tag[0] == code)
						{
							already_open = true;
							this.performInsert("[/"+exploded_tag[0]+"]", "", false);
							
							if($(this.openTags[i]))
							{
								$(this.openTags[i]).className = "toolbar_normal";
							}

							if($(exploded_tag[0]) && $(exploded_tag[0]).type == "select-one")
							{
								$(exploded_tag[0]).selectedIndex = 0;
							}					
							
							if(this.openTags[i] == full_tag)
							{
								no_insert = true;
							}
						}
						else
						{
							newTags[newTags.length] = this.openTags[i];
						}
					}
				}
				
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
						MyBB.arrayPush(this.openTags, full_tag);
						$('close_tags').style.visibility = '';
					}
					else if($(full_tag))
					{
						DomLib.removeClass($(full_tag), "toolbar_clicked");
					}
					else if($(code) && $(code).type == "select-one")
					{
						$(code).selectedIndex = 0;
					}
					
				}
		}
		
		if(this.openTags.length == 0) 
 		{ 
			$('close_tags').style.visibility = 'hidden'; 
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
		else if(textarea.selectionEnd)
		{
			var select_start = textarea.selectionStart;
			var select_end = textarea.selectionEnd;
			var scroll_top = textarea.scrollTop;
			
			if(select_end <= 2)
			{
				select_end = textarea.textLength;
			}
			
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
		return is_closed;
	},

	closeTags: function()
	{
		if(this.openTags[0])
		{
			while(this.openTags[0])
			{
				tag = MyBB.arrayPop(this.openTags);
				exploded_tag = tag.split("_");
				this.performInsert("[/"+exploded_tag[0]+"]", "", false);
				if($(exploded_tag[0]))
				{
					tag = $(exploded_tag[0]);
				}
				if($(tag))
				{
					if(tag.type == "select-one")
					{
						tag.selectedIndex = 0;
					}
					else
					{
						DomLib.removeClass($(tag), "toolbar_clicked");
					}
				}
				
			}
		}
		$(this.textarea).focus();
		$('close_tags').style.visibility = 'hidden';
		this.openTags = new Array();
	},

	setToolbarItemState: function(id, state)
	{
		element = $(id);
		if(element && element != null)
		{
			element.className = "toolbar_"+state;
		}
	},
	bindSmilieInserter: function(id)
	{
		if(!$(id))
		{
			return false;
		}
		
		smilies = DomLib.getElementsByClassName($(id), "img", "smilie");
		
		if(smilies.length > 0)
		{
			for(var i=0; i < smilies.length; ++i)
			{
				var smilie = smilies[i];
				smilie.onclick = this.insertSmilie.bindAsEventListener(this);
				smilie.style.cursor = "pointer";
			}
		}
	},
	
	openGetMoreSmilies: function(editor)
	{
		MyBB.popupWindow('misc.php?action=smilies&popup=true&editor='+editor, 'sminsert', 240, 280);
	},

	insertSmilie: function(e)
	{
		element = MyBB.eventElement(e);
		
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