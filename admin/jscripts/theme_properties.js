/**
 * functions for stylesheet file/color attachments
 */

var themeProperties = (function() {
	/**
	 * @var number the total attached files for this stylesheet
	 */
	var attachedCount = 0;

	/**
	 * attach event handlers
	 *
	 * @return void
	 */
	function init() {
		for (var i = 0; i < attachedCount; ++i) {
			$("#delete_img_" + i).on('click', removeAttachmentBox);
		}
		$("#new_specific_file").on('click', addAttachmentBox);
	}

	/**
	 * allow external setup
	 *
	 * @param  string the count at load time
	 * @return void
	 */
	function setup(count) {
		attachedCount = count || 0;
	}

	/**
	 * create a new blank attachment box
	 *
	 * @param  object the event
	 * @return void
	 */
	function addAttachmentBox(e) {
		e.preventDefault();

		var next_count = Number(attachedCount) + 1,
		contents = "<div id=\"attached_form_" + attachedCount + "\"><div class=\"border_wrapper\">\n<table class=\"general form_container \" cellspacing=\"0\">\n<tbody>\n<tr class=\"first\">\n<td class=\"first\"><div class=\"form_row\"><span style=\"float: right;\"><a href=\"\" id=\"delete_img_" + attachedCount + "\"><img src=\"styles/default/images/icons/cross.png\" alt=\"" + delete_lang_string + "\" title=\"" + delete_lang_string + "\" /></a></span>" + file_lang_string + " &nbsp;<input type=\"text\" name=\"attached_" + attachedCount + "\" value=\"\" class=\"text_input\" style=\"width: 200px;\" id=\"attached_" + attachedCount + "\" /></div>\n</td>\n</tr>\n<tr class=\"last alt_row\">\n<td class=\"first\"><div class=\"form_row\"><dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">\n<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_" + attachedCount + "\" value=\"0\" checked=\"checked\" class=\"action_" + attachedCount + "s_check\" onclick=\"checkAction('action_" + attachedCount + "');\" style=\"vertical-align: middle;\" /> " + globally_lang_string + "</label></dt>\n<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_" + attachedCount + "\" value=\"1\"  class=\"action_" + attachedCount + "s_check\" onclick=\"checkAction('action_" + attachedCount + "');\" style=\"vertical-align: middle;\" /> " + specific_actions_lang_string + "</label></dt>\n<dd style=\"margin-top: 4px;\" id=\"action_" + attachedCount + "_1\" class=\"action_" + attachedCount + "s\">\n<small class=\"description\">" + specific_actions_desc_lang_string + "</small>\n<table cellpadding=\"4\">\n<tr>\n<td><input type=\"text\" name=\"action_list_" + attachedCount + "\" value=\"\" class=\"text_input\" style=\"width: 190px;\" id=\"action_list_" + attachedCount + "\" /></td>\n</tr>\n</table>\n</dd>\n</dl></div>\n</td>\n</tr>\n</tbody>\n</table>\n</div></div><div id=\"attach_box_" + next_count + "\"></div>\n";

		// if this is the first attachment, create the first
		if (!$("#attach_box_" + attachedCount).attr('id')) {
			$("#attach_1").html(contents).show();
		} else {
			$("#attach_box_" + attachedCount).html(contents).show();
		}

		checkAction('action_' + attachedCount);

		if ($("#attached_form_" + attachedCount)) {
			$("#delete_img_" + attachedCount).on('click', removeAttachmentBox);
		}
		++attachedCount;
	}

	/**
	 * remove an entire attachment box
	 *
	 * @param  object the event
	 * @return void
	 */
	function removeAttachmentBox(e) {
		var idArray, id;

		idArray = e.currentTarget.id.split('_');
		if (!idArray.length) {
			return;
		}
		id = idArray[idArray.length - 1];
		e.preventDefault();

		if (confirm(delete_confirm_lang_string) == true) {
			$("#attached_form_" + id).remove();
		}
	}

	$(init);

	// the only public method
	return {
		setup: setup,
	};
})();
