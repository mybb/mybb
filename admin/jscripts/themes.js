/**
 * ThemeSelector loads various selectors' properties when they are select from
 * a list
 */

var ThemeSelector = (function() {
	/**
	 * @var shortcut
	 */
	var fn = encodeURIComponent;

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
	function ThemeSelector(url, saveUrl, selector, styleSheet, file, selectorForm, tid) {
		// verify input
		if (!url || !saveUrl || !selector || !styleSheet || !file || !selectorForm || !tid) {
			return;
		}

		this.url = url;
		this.saveUrl = saveUrl;
		this.selector = selector;
		this.selectorPrevOpt = this.selector.val();
		this.styleSheet = styleSheet;
		this.file = file;
		this.selectorForm = selectorForm;
		this.tid = tid;

		this.background = $("#css_bits\\[background\\]").val();
		this.width = $("#css_bits\\[width\\]").val();
		this.color = $("#css_bits\\[color\\]").val();
		this.extra = $("#css_bits\\[extra\\]").val();
		this.text_decoration = $("#css_bits\\[text_decoration\\]").val();
		this.font_family = $("#css_bits\\[font_family\\]").val();
		this.font_size = $("#css_bits\\[font_size\\]").val();
		this.font_style = $("#css_bits\\[font_style\\]").val();
		this.font_weight = $("#css_bits\\[font_weight\\]").val();

		$("#save").on('click', function(event) { $.proxy(this, 'save', event, true); } );
		$("#save_close").on('click', function(event) { $.proxy(this, 'saveClose', event); } );
		
		ThemeSelector.that = this; // I know this is cheating :D
		
		window.onbeforeunload = function(event) { saveCheck(event, false, ThemeSelector.that); }
		window.onunload = function(event) { save(false, false); }
		
		
		this.selector.on("change", $.proxy(this, 'updateSelector'));
		this.selectorForm.on("submit", $.proxy(this, 'updateSelector'));
    }

	/**
	 * prevents no-save warning messaging when saving
	 *
	 * @return void
	 */
	function saveClose(e) {
		this.isClosing = true;
	}

	/**
	 * updates the stylesheet info to match the current selection, checking
	 * first that work isn't lost
	 *
	 * @param  object the event
	 * @return void
	 */
	function updateSelector(e) {
		var postData;

		e.preventDefault()

		this.saveCheck(e, true, null);

		postData = "file=" + fn(this.file) + "&tid=" + fn(this.tid) + "&selector=" + fn(this.selector.val()) + "&my_post_key=" + fn(my_post_key);

		this.selectorGoText = $("#mini_spinner").html();
		$("#mini_spinner").html("&nbsp;<img src=\"" + this.miniSpinnerImage + "\" style=\"vertical-align: middle;\" alt=\"\" /> ");

		$.ajax({
			type: 'post',
			url: this.url,
			data: postData,
			complete: $.proxy(this, 'onComplete'),
		});
	}

	/**
	 * handles the AJAX return data
	 *
	 * @param  object the request
	 * @return true
	 */
	function onComplete(request) {
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
			this.styleSheet.html(request.responseText);
		}

		this.background = $("#css_bits\\[background\\]").val();
		this.width = $("#css_bits\\[width\\]").val();
		this.color = $("#css_bits\\[color\\]").val();
		this.extra = $("#css_bits\\[extra\\]").val();
		this.text_decoration = $("#css_bits\\[text_decoration\\]").val();
		this.font_family = $("#css_bits\\[font_family\\]").val();
		this.font_size = $("#css_bits\\[font_size\\]").val();
		this.font_style = $("#css_bits\\[font_style\\]").val();
		this.font_weight = $("#css_bits\\[font_weight\\]").val();

		if (saved) {
			$("#saved").html(saved);
			window.setTimeout(function() {
				$("#saved").html("");
			}, 30000);
		}

		$("#mini_spinner").html(this.selectorGoText);
		this.selectorGoText = '';

		return true;
	}

	/**
	 * check if anything has changed
	 *
	 * @param  object the event
	 * @param  bool true if AJAX, false if not
	 * @return true
	 */
	function saveCheck(e, isAjax, that) {

		if(that == null)
			that = this;
	
		if (that.isClosing == true) {
			return true;
		}

		if(e != null && isAjax == true)
			e.preventDefault();

		if (that.background != $("#css_bits\\[background\\]").val() ||
		    that.width != $("#css_bits\\[width\\]").val() ||
			that.color != $("#css_bits\\[color\\]").val() ||
			that.extra != $("#css_bits\\[extra\\]").val() ||
			that.text_decoration != $("#css_bits\\[text_decoration\\]").val() ||
			that.font_family != $("#css_bits\\[font_family\\]").val() ||
			that.font_size != $("#css_bits\\[font_size\\]").val() ||
			that.font_style != $("#css_bits\\[font_style\\]").val() ||
			that.font_weight != $("#css_bits\\[font_weight\\]").val()) {
			
			e.preventDefault();
			
			if(isAjax == false)
				return save_changes_lang_string;
			else
			{
				confirmReturn = confirm(save_changes_lang_string);
				if (confirmReturn == true) {
					that.save(false, isAjax);
					$.jGrowl('Saved');
				}
			}
		}
		else if(isAjax == true)
		{
			that.selectorPrevOpt = that.selector.val();
			return true;
		}
	}

	/**
	 * saves the selector info
	 *
	 * @param  object the event
	 * @param  bool true if AJAX, false if not
	 * @return true
	 */
	function save(e, isAjax) {
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

		postData = "css_bits=" + fn(jsArrayToPhpArray(cssBits)) + "&selector=" + fn(this.selectorPrevOpt) + "&file=" + fn(this.file) + "&tid=" + fn(this.tid) + "&my_post_key=" + fn(my_post_key) + "&serialized=1";

		if (isAjax == true) {
			postData += "&ajax=1";
		}

		this.isAjax = isAjax;

		if (isAjax == true) {
			completeMethod = 'onSaveComplete';
			$.jGrowl(lang.saving);
		}

		$.ajax({
			type: 'post',
			url: this.saveUrl,
			data: postData,
			complete: $.proxy(this, completeMethod),
		});
		return !isAjax;
	}

	/**
	 * handle errors, reset values and clean up
	 *
	 * @param  object the request
	 * @return true
	 */
	function onSaveComplete(request) {
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

		this.background = $("#css_bits\\[background\\]").val();
		this.width = $("#css_bits\\[width\\]").val();
		this.color = $("#css_bits\\[color\\]").val();
		this.extra = $("#css_bits\\[extra\\]").val();
		this.text_decoration = $("#css_bits\\[text_decoration\\]").val();
		this.font_family = $("#css_bits\\[font_family\\]").val();
		this.font_size = $("#css_bits\\[font_size\\]").val();
		this.font_style = $("#css_bits\\[font_style\\]").val();
		this.font_weight = $("#css_bits\\[font_weight\\]").val();

		return true;
	}

	/**
	 * handle leaving page save
	 *
	 * @param  object the request
	 * @return true
	 */
	function onUnloadSaveComplete(request) {
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
	}

	ThemeSelector.prototype = {
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
		font_weight: null,
		saveClose: saveClose,
		updateSelector: updateSelector,
		onComplete: onComplete,
		saveCheck: saveCheck,
		save: save,
		onSaveComplete: onSaveComplete,
		onUnloadSaveComplete: onUnloadSaveComplete,
	}

	return ThemeSelector;
})();

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
