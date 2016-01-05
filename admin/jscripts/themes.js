/**
 * ThemeSelector loads various selectors' properties when they are select from
 * a list
 */

var ThemeSelector = {

	/**
	 * Constructor
	 *
	 * @param  string the address to the load script
	 * @param  string the address to the save script
	 * @param  object the select element
	 * @param  object the stylesheet info div
	 * @param  string the stylesheet file name
	 * @param  object the form element
	 * @param  number the theme id
	 * @return void
	 */
	init: function(url, saveUrl, selector, styleSheet, file, selectorForm, tid) {
		// verify input
		if (!url || !saveUrl || !selector || !styleSheet || !file || !selectorForm || !tid) {
			return;
		}

		ThemeSelector.url = url;
		ThemeSelector.saveUrl = saveUrl;
		ThemeSelector.selector = selector;
		ThemeSelector.selectorPrevOpt = ThemeSelector.selector.val();
		ThemeSelector.styleSheet = styleSheet;
		ThemeSelector.file = file;
		ThemeSelector.selectorForm = selectorForm;
		ThemeSelector.tid = tid;

		ThemeSelector.background = $("#css_bits\\[background\\]").val();
		ThemeSelector.width = $("#css_bits\\[width\\]").val();
		ThemeSelector.color = $("#css_bits\\[color\\]").val();
		ThemeSelector.extra = $("#css_bits\\[extra\\]").val();
		ThemeSelector.text_decoration = $("#css_bits\\[text_decoration\\]").val();
		ThemeSelector.font_family = $("#css_bits\\[font_family\\]").val();
		ThemeSelector.font_size = $("#css_bits\\[font_size\\]").val();
		ThemeSelector.font_style = $("#css_bits\\[font_style\\]").val();
		ThemeSelector.font_weight = $("#css_bits\\[font_weight\\]").val();

		$("#save").on('click', function(event) { ThemeSelector.save(event, true); } );
		$("#save_close").on('click', function(event) { ThemeSelector.saveClose(event); } );
		

		$(window).on('beforeunload', function(event){
			if(ThemeSelector.isChanged())
			{
				return ' ';
			}
		});

		
		
		ThemeSelector.selector.on("change", ThemeSelector.updateSelector);
		ThemeSelector.selectorForm.on("submit", ThemeSelector.updateSelector);
    },

	/**
	 * prevents no-save warning messaging when saving
	 *
	 * @return void
	 */
	saveClose: function(e) {
		ThemeSelector.isClosing = true;
	},

	/**
	 * updates the stylesheet info to match the current selection, checking
	 * first that work isn't lost
	 *
	 * @param  object the event
	 * @return void
	 */
	updateSelector: function(e) {
		var postData;

		e.preventDefault()

		ThemeSelector.saveCheck(e, true);

		postData = "file=" + encodeURIComponent(ThemeSelector.file) + "&tid=" + encodeURIComponent(ThemeSelector.tid) + "&selector=" + encodeURIComponent(ThemeSelector.selector.val()) + "&my_post_key=" + encodeURIComponent(my_post_key);

		ThemeSelector.selectorGoText = $("#mini_spinner").html();
		$("#mini_spinner").html("&nbsp;<img src=\"" + ThemeSelector.miniSpinnerImage + "\" style=\"vertical-align: middle;\" alt=\"\" /> ");

		$.ajax({
			type: 'post',
			url: ThemeSelector.url,
			data: postData,
			complete: ThemeSelector.onComplete,
		});
	},

	/**
	 * handles the AJAX return data
	 *
	 * @param  object the request
	 * @return true
	 */
	onComplete: function(request) {
		var message, saved;

		if (request.responseText.match(/<error>(.*)<\/error>/)) {
			message = request.responseText.match(/<error>(.*)<\/error>/);

			if (!message[1]) {
				message[1] = lang.unknown_error;
			}
			$.jGrowl(lang.theme_info_fetch_error + '\n\n' + message[1]);
		} else if(request.responseText) {
			if ($("#saved").html()) {
				saved = $("#saved").html();
			}
			ThemeSelector.styleSheet.html(request.responseText);
		}

		ThemeSelector.background = $("#css_bits\\[background\\]").val();
		ThemeSelector.width = $("#css_bits\\[width\\]").val();
		ThemeSelector.color = $("#css_bits\\[color\\]").val();
		ThemeSelector.extra = $("#css_bits\\[extra\\]").val();
		ThemeSelector.text_decoration = $("#css_bits\\[text_decoration\\]").val();
		ThemeSelector.font_family = $("#css_bits\\[font_family\\]").val();
		ThemeSelector.font_size = $("#css_bits\\[font_size\\]").val();
		ThemeSelector.font_style = $("#css_bits\\[font_style\\]").val();
		ThemeSelector.font_weight = $("#css_bits\\[font_weight\\]").val();

		if (saved) {
			$("#saved").html(saved);
			window.setTimeout(function() {
				$("#saved").html("");
			}, 30000);
		}

		$("#mini_spinner").html(ThemeSelector.selectorGoText);
		ThemeSelector.selectorGoText = '';

		return true;
	},

	isChanged: function()
	{
		return (ThemeSelector.background != $("#css_bits\\[background\\]").val() ||
				ThemeSelector.width != $("#css_bits\\[width\\]").val() ||
				ThemeSelector.color != $("#css_bits\\[color\\]").val() ||
				ThemeSelector.extra != $("#css_bits\\[extra\\]").val() ||
				ThemeSelector.text_decoration != $("#css_bits\\[text_decoration\\]").val() ||
				ThemeSelector.font_family != $("#css_bits\\[font_family\\]").val() ||
				ThemeSelector.font_size != $("#css_bits\\[font_size\\]").val() ||
				ThemeSelector.font_style != $("#css_bits\\[font_style\\]").val() ||
				ThemeSelector.font_weight != $("#css_bits\\[font_weight\\]").val());
	},

	/**
	 * check if anything has changed
	 *
	 * @param  object the event
	 * @param  bool true if AJAX, false if not
	 * @return true
	 */
	saveCheck: function(e, isAjax) {

	
		if (ThemeSelector.isClosing == true) {
			return true;
		}

		if(e != null && isAjax == true)
			e.preventDefault();

		if (ThemeSelector.isChanged()) {
			
			e.preventDefault();
			
			if(isAjax == false)
				return save_changes_lang_string;
			else
			{
				confirmReturn = confirm(save_changes_lang_string);
				if (confirmReturn == true) {
					ThemeSelector.save(false, isAjax);
					$.jGrowl('Saved');
				}
			}
		}
		else if(isAjax == true)
		{
			ThemeSelector.selectorPrevOpt = ThemeSelector.selector.val();
			return true;
		}
	},

	/**
	 * saves the selector info
	 *
	 * @param  object the event
	 * @param  bool true if AJAX, false if not
	 * @return true
	 */
	save: function(e, isAjax) {
		var cssBits, postData, completeMethod = 'onUnloadSaveComplete';

		if (e) {
			e.preventDefault();
		}

		cssBits = {
			'background': $('#css_bits\\[background\\]').val(),
			'width': $('#css_bits\\[width\\]').val(),
			'color': $('#css_bits\\[color\\]').val(),
			'extra': $('#css_bits\\[extra\\]').val(),
			'text_decoration': $('#css_bits\\[text_decoration\\]').val(),
			'font_family': $('#css_bits\\[font_family\\]').val(),
			'font_size': $('#css_bits\\[font_size\\]').val(),
			'font_style': $('#css_bits\\[font_style\\]').val(),
			'font_weight': $('#css_bits\\[font_weight\\]').val()
		};

		postData = "css_bits=" + encodeURIComponent(jsArrayToPhpArray(cssBits)) + "&selector=" + encodeURIComponent(ThemeSelector.selectorPrevOpt) + "&file=" + encodeURIComponent(ThemeSelector.file) + "&tid=" + encodeURIComponent(ThemeSelector.tid) + "&my_post_key=" + encodeURIComponent(my_post_key) + "&serialized=1";

		if (isAjax == true) {
			postData += "&ajax=1";
		}

		ThemeSelector.isAjax = isAjax;

		if (isAjax == true) {
			completeMethod = 'onSaveComplete';
			$.jGrowl(lang.saving);
		}

		$.ajax({
			type: 'post',
			url: ThemeSelector.saveUrl,
			data: postData,
			complete: ThemeSelector[completeMethod],
		});
		return !isAjax;
	},

	/**
	 * handle errors, reset values and clean up
	 *
	 * @param  object the request
	 * @return true
	 */
	onSaveComplete: function(request) {
		var message;

		if (request.responseText.match(/<error>(.*)<\/error>/)) {
			message = request.responseText.match(/<error>(.*)<\/error>/);

			if (!message[1]) {
				message[1] = lang.unkown_error;
			}
			$.jGrowl(lang.theme_info_save_error + '\n\n' + message[1]);
			return false;
		} else if(request.responseText) {
			$("#saved").html(" (" + lang.saved + " @ "+ Date() + ")");
			if ($("#ajax_alert")) {
				$("#ajax_alert").html('').hide();
			}
		}

		ThemeSelector.background = $("#css_bits\\[background\\]").val();
		ThemeSelector.width = $("#css_bits\\[width\\]").val();
		ThemeSelector.color = $("#css_bits\\[color\\]").val();
		ThemeSelector.extra = $("#css_bits\\[extra\\]").val();
		ThemeSelector.text_decoration = $("#css_bits\\[text_decoration\\]").val();
		ThemeSelector.font_family = $("#css_bits\\[font_family\\]").val();
		ThemeSelector.font_size = $("#css_bits\\[font_size\\]").val();
		ThemeSelector.font_style = $("#css_bits\\[font_style\\]").val();
		ThemeSelector.font_weight = $("#css_bits\\[font_weight\\]").val();

		return true;
	},

	/**
	 * handle leaving page save
	 *
	 * @param  object the request
	 * @return true
	 */
	onUnloadSaveComplete: function(request) {
		var message;

		if (request.responseText.match(/<error>(.*)<\/error>/)) {
			message = request.responseText.match(/<error>(.*)<\/error>/);

			if (!message[1]) {
				message[1] = lang.unkown_error;
			}
			$.jGrowl(lang.theme_info_save_error + '\n\n' + message[1]);
			return false;
		}
		return true;
	},

	url: null,
	saveUrl: null,
	selector: null,
	styleSheet: null,
	file: null,
	selectorForm: null,
	tid: null,
	miniSpinnerImage: "../images/spinner.gif",
	isAjax: false,
	specific_count: 0,
	selectorGoText: null,
	selectorPrevOpt: null,
	isClosing: false,
	background: null,
	width: null,
	color: null,
	extra: null,
	text_decoration: null,
	font_family: null,
	font_size: null,
	font_style: null,
	font_weight: null
};

/**
 * converts a JS object to a JSON of a PHP associative array
 *
 * @param  array the JS array
 * @return string the JSON
 */
function jsArrayToPhpArray(a) {
    var a_php = "", total = 0;

    for (var key in a) {
        ++total;
        a_php += "s:" +
		String(key).length + ":\"" +
		String(key) + "\";s:" +
		String(a[key]).length +
		":\"" + String(a[key]) + "\";";
    }
    a_php = "a:" + total + ":{" + a_php + "}";
    return a_php;
}
