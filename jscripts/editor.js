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
		this.sizes["xx-small"] = "XX Small";
		this.sizes["x-small"] = "X Small";
		this.sizes["small"] = "Small";
		this.sizes["medium"] = "Medium";
		this.sizes["x-large"] = "X Large";
		this.sizes["xx-large"] = "XX Large";

		// An array of colours to be shown.
		this.colors = new Array();
		this.colors["white"] = "White";
		this.colors["black"] = "Black";
		this.colors["red"] = "Red";
		this.colors["yellow"] = "Yellow";
		this.colors["pink"] = "Pink";
		this.colors["green"] = "Green";
		this.colors["orange"] = "Orange";
		this.colors["purple"] = "Purple";
		this.colors["blue"] = "Blue";
		this.colors["beige"] = "Beige";
		this.colors["brown"] = "Brown";
		this.colors["teal"] = "Teal";
		this.colors["navy"] = "Navy";
		this.colors["maroon"] = "Maroon";
		this.colors["limegreen"] = "Lime Green";

		// Here we get the ID of the textarea we're replacing and store it.
		this.textarea = textarea;
		
		this.options = options;
		
		// Only swap it over once the page has loaded (add event)
		Event.observe(window, "load", this.showEditor.bindAsEventListener(this));
	},
	
	showEditor: function()
	{
		// Assign the old textarea to a variable for later use.
		oldTextarea = $(this.textarea);

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
			w = this.options.height;
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

		// Create the font drop down.
		fontSelect = document.createElement("select");
		fontSelect.style.margin = "2px";
		fontSelect.options[fontSelect.options.length] = new Option("Font", "-");
		for(font in this.fonts)
		{
			fontSelect.options[fontSelect.options.length] = new Option(this.fonts[font], font);
		}
		Event.observe(fontSelect, "change", this.changeFont.bindAsEventListener(this));
		toolBar.appendChild(fontSelect);

		// Create the font size drop down.
		sizeSelect = document.createElement("select");
		sizeSelect.style.margin = "2px";
		sizeSelect.options[sizeSelect.options.length] = new Option("Text Size", "-");
		for(size in this.sizes)
		{
			sizeSelect.options[sizeSelect.options.length] = new Option(this.sizes[size], size);
		}
		Event.observe(sizeSelect, "change", this.changeSize.bindAsEventListener(this));
		toolBar.appendChild(sizeSelect);

		// Create the colour drop down.
		colorSelect = document.createElement("select");
		colorSelect.style.margin = "2px";
		colorSelect.options[colorSelect.options.length] = new Option("Text Color", "-");
		for(color in this.colors)
		{
			colorSelect.options[colorSelect.options.length] = new Option(this.colors[color], color);
			colorSelect.options[colorSelect.options.length-1].style.backgroundColor = color;
			colorSelect.options[colorSelect.options.length-1].style.color = color;
		}
		Event.observe(colorSelect, "change", this.changeColor.bindAsEventListener(this));
		toolBar.appendChild(colorSelect);
		// Append first toolbar to the editor
		editor.appendChild(toolBar);

		// Create the second toolbar.
		toolbar2 = document.createElement("div");
		toolbar2.style.height = "28px";
		toolbar2.style.position = "relative";

		// Create formatting section of second toolbar.
		formatting = document.createElement("div");
		formatting.style.position = "absolute";
		toolbar2.appendChild(formatting);

		// Insert toolbar buttons.
		this.insertStandardButton(formatting, "b", "images/codebuttons/bold.gif", "b", "", "Insert bold text.");
		this.insertStandardButton(formatting, "i", "images/codebuttons/italic.gif", "i", "", "Insert italic text.");
		this.insertStandardButton(formatting, "u", "images/codebuttons/underline.gif", "u", "", "Insert underlined text.");
		this.insertSeparator(formatting);
		this.insertStandardButton(formatting, "align_left", "images/codebuttons/align_left.gif", "align", "left", "Insert text aligned to the left.");
		this.insertStandardButton(formatting, "align_center", "images/codebuttons/align_center.gif", "align", "center", "Insert text aligned in the center.");
		this.insertStandardButton(formatting, "align_right", "images/codebuttons/align_right.gif", "align", "right", "Insert text aligned to the right.");
		this.insertStandardButton(formatting, "align_justify", "images/codebuttons/align_justify.gif", "align", "justify", "Insert justified text.");

		// Create insertable elements section of second toolbar.
		elements = document.createElement("div");
		elements.style.position = "absolute";
		elements.style.right = 0;

		toolbar2.appendChild(elements);
		this.insertStandardButton(elements, "list_num", "images/codebuttons/list_num.gif", "list", "1", "Insert a numbered list.");
		this.insertStandardButton(elements, "list_bullet", "images/codebuttons/list_bullet.gif", "list", "", "Insert a bulleted list.");
		this.insertSeparator(elements);
		this.insertStandardButton(elements, "img", "images/codebuttons/image.gif", "image", "", "Insert an image.");
		this.insertStandardButton(elements, "url", "images/codebuttons/link.gif", "url", "", "Insert a hyperlink.");
		this.insertStandardButton(elements, "email", "images/codebuttons/email.gif", "email", "", "Insert an email address.");
		this.insertSeparator(elements);
		this.insertStandardButton(elements, "quote", "images/codebuttons/quote.gif", "quote", "", "Insert quote.");
		this.insertStandardButton(elements, "code", "images/codebuttons/code.gif", "code", "", "Insert preformatted code.");
		this.insertStandardButton(elements, "php", "images/codebuttons/php.gif", "php", "", "Insert PHP syntax highlighted code.");

		// Append the second toolbar to the editor
		editor.appendChild(toolbar2);

		// Create our new text area
		areaContainer = document.createElement("div");

		// Set the width/height of the area
		if(MyBB.browser == "mozilla")
		{
			subtract = subtract2 = parseInt(editor.style.padding)*2;
		}
		else
		{
			subtract = subtract2 = 0;
		}
		areaContainer.style.height = parseInt(editor.style.height)-parseInt(toolBar.style.height)-parseInt(toolbar2.style.height)-subtract+"px";
		areaContainer.style.width = (parseInt(editor.style.width)-subtract2)+"px";
		// Create text area
		textInput = document.createElement("textarea");
		textInput.id = this.textarea;
		textInput.name = oldTextarea.name;
		textInput.style.height = areaContainer.style.height;
		textInput.style.width = areaContainer.style.width;
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
		}
		// Replace the old with the new
		oldTextarea.parentNode.replaceChild(editor, oldTextarea);
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
		if(!element || !element.insertText)
		{
			return false;
		}
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
		this.insertMyCode(element.insertText, element.insertExtra);
	},

	changeFont: function(e)
	{
		element = MyBB.eventElement(e);
		if(!element)
		{
			return false;
		}
		this.insertMyCode("font", element.options[element.selectedIndex].value);
	},

	changeSize: function(e)
	{
		element = MyBB.eventElement(e);
		if(!element)
		{
			return false;
		}
		this.insertMyCode("size", element.options[element.selectedIndex].value);
	},

	changeColor: function(e)
	{
		element = MyBB.eventElement(e);
		if(!element)
		{
			return false;
		}
		this.insertMyCode("color", element.options[element.selectedIndex].value);
	},

	insertList: function(type)
	{
		list = "";
		do
		{
			listItem = prompt("Enter a list item. Click cancel or leave blank to end the list.", "");
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
		url = prompt("Please enter the URL of the website.", "http://");
		if(url)
		{
			if(!selectedText)
			{
				title = prompt("Optionally, you can enter a title for the URL.", "");
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
		email = prompt("Please enter the email address you wish to insert.", "");
		if(email)
		{
			if(!selectedText)
			{
				title = prompt("Optionally, you can enter a title for the email address.", "");
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
		image = prompt("Please enter the remote URL of the image.", "http://");
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
				for(var i=0;i<this.openTags.length;i++)
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
					}
					else
					{
						DomLib.removeClass($(full_tag), "toolbar_clicked");
					}
				}
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
			if(select_end <= 2)
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
			var range = selection.createRange()
			if(ignore_selection != false)
			{
				selection.collapse;
			}
			if((selection.type == "Text" || selection.type == "None") && range != null && ignore_selection != true)
			{
				if(close_tag != "" && range.text.length > 0)
				{
					range.text = open_tag+range.text+close_tag;
				}
				else
				{
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
			if(select_end <= 2)
			{
				select_end = textarea.textLength;
			}
			var start = textarea.value.substring(0, select_start);
			var middle = textarea.value.substring(select_start, select_end);
			var end = textarea.value.substring(select_end, textarea.textLength);
			if(select_end - select_start > 0 && ignore_selection != true && close_tag != "")
			{
				middle = open_tag+middle+close_tag;
			}
			else
			{
				if(is_single)
				{
					is_closed = false;
				}
				middle = open_tag;
			}
			textarea.value = start+middle+end;
		}
		else
		{
			textarea.value += open_tag;
			if(is_single)
			{
				is_closed = false;
			}
		}
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
				if($(tag))
				{
					DomLib.removeClass($(tag), "toolbar_clicked");
				}
			}
		}
		$(this.textarea).focus();
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
			for(var i=0;i<smilies.length;i++)
			{
				var smilie = smilies[i];
				smilie.onclick = this.insertSmilie.bindAsEventListener(this);
				smilie.style.cursor = "pointer";
			}
		}
	},
	
	openGetMoreSmilies: function(editor)
	{
		MyBB.popupWindow('misc.php?action=smilies&amp;popup=true&amp;editor='+editor, 'sminsert', 240, 280);
		return false;
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