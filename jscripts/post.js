var Post = {
	init: function () {
		$(function () {
			Post.initAttachments();
		});
	},

	loadMultiQuoted: function () {
		if (use_xmlhttprequest == 1) {
			tid = document.input.tid.value;

			$.ajax(
				{
					url: 'xmlhttp.php?action=get_multiquoted&tid=' + tid,
					type: 'get',
					complete: function (request, status) {
						Post.multiQuotedLoaded(request, status);
					}
				});

			return false;
		}
		else {
			return true;
		}
	},

	loadMultiQuotedAll: function () {
		if (use_xmlhttprequest == 1) {
			$.ajax(
				{
					url: 'xmlhttp.php?action=get_multiquoted&load_all=1',
					type: 'get',
					complete: function (request, status) {
						Post.multiQuotedLoaded(request, status);
					}
				});

			return false;
		}
		else {
			return true;
		}
	},

	multiQuotedLoaded: function (request) {
		var json = JSON.parse(request.responseText);
		if (typeof response == 'object') {
			if (json.hasOwnProperty("errors")) {
				$.each(json.errors, function (i, message) {
					$.jGrowl(lang.post_fetch_error + ' ' + message, { theme: 'jgrowl_error' });
				});
				return false;
			}
		}

		var id = 'message';
		if (typeof MyBBEditor !== 'undefined' && MyBBEditor !== null) {
			MyBBEditor.insert(json.message);
		}
		else {
			if ($('#' + id).value) {
				$('#' + id).value += "\n";
			}
			$('#' + id).val($('#' + id).val() + json.message);
		}

		$('#multiquote_unloaded').hide();
		document.input.quoted_ids.value = 'all';
	},

	clearMultiQuoted: function () {
		$('#multiquote_unloaded').hide();
		Cookie.unset('multiquote');
	},

	removeAttachment: function (aid) {
		MyBB.prompt(removeattach_confirm, {
			buttons: [
				{ title: yes_confirm, value: true },
				{ title: no_confirm, value: false }
			],
			submit: function (e, v, m, f) {
				if (v == true) {
					document.input.attachmentaid.value = aid;
					document.input.attachmentact.value = "remove";

					var form = $('input[name^=\'rem\']').parents('form');

					if (use_xmlhttprequest != 1) {
						form.append('<input type="submit" id="rem_submit" class="hidden" />');
						$('#rem_submit').trigger('click');
						return false;
					}

					$.ajax({
						type: 'POST',
						url: form.attr('action') + '&ajax=1',
						data: form.serialize(),
						success: function (data) {
							if (data.hasOwnProperty("errors")) {
								$.each(data.errors, function (i, message) {
									$.jGrowl(lang.post_fetch_error + ' ' + message, { theme: 'jgrowl_error' });
								});
								return false;
							} else if (data.success) {
								$('#attachment_' + aid).hide(500, function () {
									var instance = MyBBEditor;
									if (typeof MyBBEditor === 'undefined') {
										instance = $('#message').sceditor('instance');
									}

									if (instance.sourceMode()) {
										instance.setSourceEditorValue(instance.getSourceEditorValue(false).split('[attachment=' + aid + ']').join(''));
									} else {
										instance.setWysiwygEditorValue(instance.getWysiwygEditorValue(false).split('[attachment=' + aid + ']').join(''));
									}

									$(this).remove();

									if (!Post.getAttachments().length) {
										$('input[name=updateattachment]').remove();
									}
								});
							}
							document.input.attachmentaid.value = '';
							document.input.attachmentact.value = '';
						}
					});
				}
			}
		});

		return false;
	},

	attachmentAction: function (aid, action) {
		document.input.attachmentaid.value = aid;
		document.input.attachmentact.value = action;
	},

	getAttachments: function () {
		var attached = [];
		$('.attachment_filename').each(function () {
			attached.push($(this).text());
		});
		return attached;
	},

	initAttachments: function () {
		$("input[type='file']").parents('form').on('submit', Post.checkAttachments);
		$("input[type='file']").on('change', Post.addAttachments);
	},

	addAttachments: function () {
		var files = $("input[type='file']");
		var file = files.get(0);

		if (file.files.length) {
			var names = $.map(file.files, function (val) { return val.name; });
			var common = $.grep(Post.getAttachments(), function (i) {
				return $.inArray(i, names) > -1;
			});

			if (common.length) {
				common = '<ul><li>' + common.join('</li><li>') + '</li></ul>';
				lang.update_confirm = "The following file(s) are already attached and will be updated / replaced with the newly selected one(s). {1} Are you sure?";
				MyBB.prompt(lang.update_confirm.replace("{1}", common), {
					buttons: [
						{ title: yes_confirm, value: true },
						{ title: no_confirm, value: false }
					],
					submit: function (e, v, m, f) {
						if (v == true) {
							var form = $("input[type='file']").parents('form');
							form.append('<input type="hidden" class="temp_input" name="updateconfirmed" value="1" />');
							Post.uploadAttachments('updateattachment');
						}
					}
				});
			} else {
				Post.uploadAttachments('newattachment');
			}
		}
		return false;
	},

	uploadAttachments: function (type) {
		if (use_xmlhttprequest != 1) {
			$("input[name='" + type + "']").trigger('click');
		} else {
			var form = $("input[type='file']").parents('form');
			form.append('<input type="hidden" class="temp_input" name="' + type + '" value="1" />');
			var formData = new FormData($(form)[0]);

			$.ajax({
				type: 'POST',
				url: form.attr('action') + '&ajax=1',
				data: formData,
				async: false,
				cache: false,
				contentType: false,
				enctype: 'multipart/form-data',
				processData: false,
				success: function (data) {
					if (data.hasOwnProperty("errors")) {
						$.each(data.errors, function (i, message) {
							$.jGrowl(message, { theme: 'jgrowl_error' });
						});
					}
					// TODO : PROCESS SUCCESS DATA AND MANIPULATE DOM

					$("input[type='file']").val('');
				}
			});
			$('.temp_input').remove();
		}
	},

	checkAttachments: function (e) {
		var submitter = e.originalEvent.submitter.name;
		var files = $("input[type='file']");
		var file = files.get(0);
		if (!file) {
			return true;
		}

		if (!file.files.length && (submitter == 'newattachment' || submitter == 'updateattachment')) {
			$.jGrowl(lang.attachment_missing, { theme: 'jgrowl_error' });
			return false;
		}

		var maxAllowed = Math.min.apply(null, [mybb_max_file_uploads, php_max_file_uploads].filter(Boolean));
		if(isFinite(maxAllowed) && file.files.length > maxAllowed) {
			$.jGrowl(lang.attachment_too_many_files.replace('{1}', maxAllowed), { theme: 'jgrowl_error' });
			file.value = '';
			return false;
		}

		var totalSize = 0;
		files.each(function () {
			for (var i = 0; i < this.files.length; i++) {
				totalSize += (this.files[i].size || this.files[i].fileSize);
			}
		});

		if (totalSize > php_max_upload_size && php_max_upload_size > 0) {
			var php_max_upload_size_pretty = Math.round(php_max_upload_size / 1e4) / 1e2;
			$.jGrowl(lang.attachment_too_big_upload.replace('{1}', php_max_upload_size_pretty), { theme: 'jgrowl_error' });
			file.value = "";
			return false;
		}

		return true;
	}
};

Post.init();