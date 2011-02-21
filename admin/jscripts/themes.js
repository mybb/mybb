var ThemeSelector = Class.create();

ThemeSelector.prototype = {

    url: null,
	save_url: null,
    selector: null,
    stylesheet: null,
	file: null,
	selector_form: null,
	tid: null,
    spinnerImage: "../images/spinner_big.gif",
	miniSpinnerImage: "../images/spinner.gif",
	isajax: false,
	specific_count: 0,
	selector_go: null,
	selector_prev_option: null,
	is_closing: false,
	
	background: null,
	width: null,
	color: null,
	extra: null,
	text_decoration: null,
	font_family: null,
	font_size: null,
	font_style: null,
	font_weight: null,
    
    initialize: function(url, save_url, selector, stylesheet, file, selector_form, tid)
    {
		if(url && save_url && selector && stylesheet && file && selector_form && tid)
        {
            this.url = url;
			this.save_url = save_url;
            this.selector = selector;
			this.selector_prev_option = this.selector.value;
            this.stylesheet = stylesheet;
			this.file = file;
			this.selector_form = selector_form;
			this.tid = tid;
			
			this.background = $("css_bits[background]").value;
			this.width = $("css_bits[width]").value;
			this.color = $("css_bits[color]").value;
			this.extra = $("css_bits[extra]").value;
			this.text_decoration = $("css_bits[text_decoration]").value;
			this.font_family = $("css_bits[font_family]").value;
			this.font_size = $("css_bits[font_size]").value;
			this.font_style = $("css_bits[font_style]").value;
			this.font_weight = $("css_bits[font_weight]").value;
			
			Event.observe(window, "unload", this.saveCheck.bindAsEventListener(this, false));
			Event.observe($("save"), "click", this.save.bindAsEventListener(this, true));
			Event.observe($("save_close"), "click", this.saveClose.bindAsEventListener(this));
			Event.observe(this.selector, "change", this.updateSelector.bindAsEventListener(this));
			Event.observe(this.selector_form, "submit", this.updateSelector.bindAsEventListener(this));
		}
		else if(url)
		{
			for(i=0; i < url; ++i)
			{
				Event.observe($("delete_img_"+i), "click", this.removeAttachmentBox.bindAsEventListener(this, i));
			}
			
			this.specific_count = url;
			Event.observe($("new_specific_file"), "click", this.addAttachmentBox.bindAsEventListener(this));
		}
    },
	
	saveClose: function(e)
	{
		this.is_closing = true;
	},
    
    updateSelector: function(e)
    {
        Event.stop(e);
		
		this.saveCheck(e, true);
		
        postData = "file="+encodeURIComponent(this.file)+"&tid="+encodeURIComponent(this.tid)+"&selector="+encodeURIComponent(this.selector.value)+"&my_post_key="+encodeURIComponent(my_post_key);
		
		this.selector_go = $("mini_spinner").innerHTML;
		$("mini_spinner").innerHTML = "&nbsp;<img src=\""+this.miniSpinnerImage+"\" style=\"vertical-align: middle;\" alt=\"\" /> ";
		
        new Ajax.Request(this.url, {
            method: 'post',
            postBody: postData,
            onComplete: this.onComplete.bind(this)
        });
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
			
			alert('There was an error fetching the test results.\n\n'+message[1]);
		}
		else if(request.responseText)
		{
			if($("saved").innerHTML)
			{
				var saved = $("saved").innerHTML;
			}
			this.stylesheet.innerHTML = request.responseText;
		}
		
		this.background = $("css_bits[background]").value;
		this.width = $("css_bits[width]").value;
		this.color = $("css_bits[color]").value;
		this.extra = $("css_bits[extra]").value;
		this.text_decoration = $("css_bits[text_decoration]").value;
		this.font_family = $("css_bits[font_family]").value;
		this.font_size = $("css_bits[font_size]").value;
		this.font_style = $("css_bits[font_style]").value;
		this.font_weight = $("css_bits[font_weight]").value;
		
		if(saved)
		{
			$("saved").innerHTML = saved;
			window.setTimeout("$(\"saved\").innerHTML = \"\";", 30000);
		}
		
		$("mini_spinner").innerHTML = this.selector_go;
		this.selector_go = '';

		return true;
	},
	
	saveCheck: function(e, isajax)
    {
		if(this.is_closing == true)
		{
			return true;
		}
		
		if(this.background != $("css_bits[background]").value || this.width != $("css_bits[width]").value || this.color != $("css_bits[color]").value || this.extra != $("css_bits[extra]").value || this.text_decoration != $("css_bits[text_decoration]").value || this.font_family != $("css_bits[font_family]").value || this.font_size != $("css_bits[font_size]").value || this.font_style != $("css_bits[font_style]").value || this.font_weight != $("css_bits[font_weight]").value)
		{
			confirmReturn = confirm(save_changes_lang_string);
			if(confirmReturn == true)
			{
				this.save(false, isajax);
				alert('Saved');
			}
		}
		this.selector_prev_option = this.selector.value;
		return true;
    },
	
	save: function(e, isajax)
    {
		if(e)
		{
        	Event.stop(e);
		}
		
		var css_bits = {
			'background': $('css_bits[background]').value,
			'width': $('css_bits[width]').value,
			'color': $('css_bits[color]').value,
			'extra': $('css_bits[extra]').value,
			'text_decoration': $('css_bits[text_decoration]').value,
			'font_family': $('css_bits[font_family]').value,
			'font_size': $('css_bits[font_size]').value,
			'font_style': $('css_bits[font_style]').value,
			'font_weight': $('css_bits[font_weight]').value
		};
		
		postData = "css_bits="+encodeURIComponent(js_array_to_php_array(css_bits))+"&selector="+encodeURIComponent(this.selector_prev_option)+"&file="+encodeURIComponent(this.file)+"&tid="+encodeURIComponent(this.tid)+"&my_post_key="+encodeURIComponent(my_post_key)+"&serialized=1";
		
		if(isajax == true)
		{
			postData += "&ajax=1";
		}
		
		this.isajax = isajax;
		
		if(isajax == true)
		{
			this.spinner2 = new ActivityIndicator("body", {image: this.spinnerImage});
		}
		
		if(isajax == true)
		{
			new Ajax.Request(this.save_url, {
				method: 'post',
				postBody: postData,
				onComplete: this.onSaveComplete.bind(this)
			});
		}
		else
		{
			new Ajax.Request(this.save_url, {
				method: 'post',
				postBody: postData,
				onComplete: this.onUnloadSaveComplete.bind(this)
			});
		}
    },
    
	onSaveComplete: function(request)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/))
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);

			if(!message[1])
			{
				message[1] = "An unknown error occurred.";
			}
			
			alert('There was an error fetching the test results.\n\n'+message[1]);
			return false;
		}
		else if(request.responseText)
		{
			$("saved").innerHTML = " (Saved @ "+Date()+")";
			if($("ajax_alert"))
			{
				$("ajax_alert").innerHTML = '';
				$("ajax_alert").hide();
			}
		}
		
		this.background = $("css_bits[background]").value;
		this.width = $("css_bits[width]").value;
		this.color = $("css_bits[color]").value;
		this.extra = $("css_bits[extra]").value;
		this.text_decoration = $("css_bits[text_decoration]").value;
		this.font_family = $("css_bits[font_family]").value;
		this.font_size = $("css_bits[font_size]").value;
		this.font_style = $("css_bits[font_style]").value;
		this.font_weight = $("css_bits[font_weight]").value;

		this.spinner2.destroy();

		return true;
	},
	
	onUnloadSaveComplete: function(request)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/))
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);

			if(!message[1])
			{
				message[1] = "An unknown error occurred.";
			}
			
			alert('There was an error fetching the test results.\n\n'+message[1]);
			return false;
		}
		
		return true;
	},
	
	addAttachmentBox: function(e)
	{
		Event.stop(e);
		
		var next_count = Number(this.specific_count) + 1;
		
		var contents = "<div id=\"attached_form_"+this.specific_count+"\"><div class=\"border_wrapper\">\n<table class=\"general form_container \" cellspacing=\"0\">\n<tbody>\n<tr class=\"first\">\n<td class=\"first\"><div class=\"form_row\"><span style=\"float: right;\"><a href=\"\" id=\"delete_img_"+this.specific_count+"\"><img src=\"styles/default/images/icons/cross.gif\" alt=\""+delete_lang_string+"\" title=\""+delete_lang_string+"\" /></a></span>"+file_lang_string+" &nbsp;<input type=\"text\" name=\"attached_"+this.specific_count+"\" value=\"\" class=\"text_input\" style=\"width: 200px;\" id=\"attached_"+this.specific_count+"\" /></div>\n</td>\n</tr>\n<tr class=\"last alt_row\">\n<td class=\"first\"><div class=\"form_row\"><dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">\n<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_"+this.specific_count+"\" value=\"0\" checked=\"checked\" class=\"action_"+this.specific_count+"s_check\" onclick=\"checkAction('action_"+this.specific_count+"');\" style=\"vertical-align: middle;\" /> "+globally_lang_string+"</label></dt>\n<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_"+this.specific_count+"\" value=\"1\"  class=\"action_"+this.specific_count+"s_check\" onclick=\"checkAction('action_"+this.specific_count+"');\" style=\"vertical-align: middle;\" /> "+specific_actions_lang_string+"</label></dt>\n<dd style=\"margin-top: 4px;\" id=\"action_"+this.specific_count+"_1\" class=\"action_"+this.specific_count+"s\">\n<small class=\"description\">"+specific_actions_desc_lang_string+"</small>\n<table cellpadding=\"4\">\n<tr>\n<td><input type=\"text\" name=\"action_list_"+this.specific_count+"\" value=\"\" class=\"text_input\" style=\"width: 190px;\" id=\"action_list_"+this.specific_count+"\" /></td>\n</tr>\n</table>\n</dd>\n</dl></div>\n</td>\n</tr>\n</tbody>\n</table>\n</div></div><div id=\"attach_box_"+next_count+"\"></div>\n";
		
		if(!$("attach_box_"+this.specific_count))
		{
			$("attach_1").innerHTML = contents;
		}
		else
		{
			$("attach_box_"+this.specific_count).innerHTML = contents;
		}
		
		checkAction('action_'+this.specific_count);
		

		if($("attached_form_"+this.specific_count))
		{
			Event.observe($("delete_img_"+this.specific_count), "click", this.removeAttachmentBox.bindAsEventListener(this, this.specific_count));
		}
		
		++this.specific_count;
	},
	
	removeAttachmentBox: function(e, count)
	{
		Event.stop(e);
		
		confirmReturn = confirm(delete_confirm_lang_string);

		if(confirmReturn == true)
		{
			Element.remove($("attached_form_"+count));
		}
		
	}
}

function js_array_to_php_array(a)
{
    var a_php = "";
    var total = 0;
    for(var key in a)
    {
        ++total;
        a_php = a_php + "s:" +
                String(key).length + ":\"" + String(key) + "\";s:" +
                String(a[key]).length + ":\"" + String(a[key]) + "\";";
    }
    a_php = "a:" + total + ":{" + a_php + "}";
    return a_php;
}