var Post = {
	init: function () {
		$(function () {
			Post.fileInput = $("input[type='file']");
			Post.form = Post.fileInput.parents('form');

			Post.form.on('submit', Post.checkAttachments);
			Post.fileInput.on('change', Post.addAttachments);
		});
	},

	loadMultiQuoted: function () {
		if (use_xmlhttprequest == 1) {
			tid = document.input.tid.value;

			$.ajax({
				url: 'xmlhttp.php?action=get_multiquoted&tid=' + tid,
				type: 'get',
				complete: function (request, status) {
					Post.multiQuotedLoaded(request, status);
				}
			});

			return false;
		} else {
			return true;
		}
	},

	loadMultiQuotedAll: function () {
		if (use_xmlhttprequest == 1) {
			$.ajax({
				url: 'xmlhttp.php?action=get_multiquoted&load_all=1',
				type: 'get',
				complete: function (request, status) {
					Post.multiQuotedLoaded(request, status);
				}
			});

			return false;
		} else {
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
		} else {
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
					Post.attachmentAction(aid, 'remove');

					if (use_xmlhttprequest != 1) {
						Post.form.append('<input type="submit" id="rem_submit" class="hidden" />');
						$('#rem_submit').trigger('click');
						return false;
					}

					$.ajax({
						type: 'POST',
						url: Post.form.attr('action') + '&ajax=1',
						data: Post.form.serialize(),
						success: function (data) {
							if (data.hasOwnProperty("errors")) {
								$.each(data.errors, function (i, message) {
									$.jGrowl(message, { theme: 'jgrowl_error' });
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
									Post.regenAttachbuttons();
								});
							}
							Post.attachmentAction('', '');
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

	getCommonFiles: function () {
		var files = Post.fileInput.prop('files');
		if (files.length) {
			var names = $.map(files, function (val) {
				return val.name;
			});
			return $.grep(Post.getAttachments(), function (i) {
				return $.inArray(i, names) > -1;
			});
		} else {
			return [];
		}
	},

	addAttachments: function () {
		Post.checkAttachments();

		if (Post.fileInput.prop('files').length) {
			var common = Post.getCommonFiles();
			if (common.length) {
				common = '<ul><li>' + common.join('</li><li>') + '</li></ul>';
				MyBB.prompt(lang.update_confirm.replace("{1}", common), {
					buttons: [
						{ title: yes_confirm, value: true },
						{ title: no_confirm, value: false }
					],
					submit: function (e, v, m, f) {
						if (v == true) {
							Post.form.append('<input type="hidden" class="temp_input" name="updateconfirmed" value="1" />');
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
			Post.form.append('<input type="hidden" class="temp_input" name="' + type + '" value="1" />');
			var formData = new FormData($(Post.form)[0]);

			$.ajax({
				type: 'POST',
				url: Post.form.attr('action') + '&ajax=1',
				data: formData,
				async: true,
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
					// Append new attachment data
					if (data.hasOwnProperty("success")) {
						$.each(data.success, function (i, message) {
							if ($('#attachment_' + message[0]).length) {
								$('#attachment_' + message[0]).remove();
							}
							Post.fileInput.parents('tbody').append(data.template
								.replace(/\{1\}/g, message[0])
								.replace('{2}', message[1])
								.replace('{3}', message[2])
								.replace('{4}', message[3]));
						});
					}

					Post.fileInput.val('');
					Post.regenAttachbuttons();
				}
			});
			$('.temp_input').remove();
		}
	},

	regenAttachbuttons: function () {
		var button = $('input[name=newattachment]').length ? 'newattachment' : 'updateattachment';
		button = $("input[name=" + button + "]").clone();

		if (Post.getAttachments().length) {
			if (!$('input[name=updateattachment]').length) {
				var updateButton = button.clone()
					.prop('name', 'updateattachment')
					.prop('value', lang.update_attachment)
					.prop('tabindex', '12');
				$("input[name='newattachment']").before(updateButton).before('&nbsp;');
			}
		} else {
			$('input[name=updateattachment]').remove();
		}

		if (Post.getAttachments().length < mybb_max_file_uploads) {
			if (!$('input[name=newattachment]').length) {
				var newButton = button.clone()
					.prop('name', 'newattachment')
					.prop('value', lang.add_attachment)
					.prop('tabindex', '13');
				$("input[name='updateattachment']").after(newButton).after('&nbsp;');
			}
		} else {
			$('input[name=newattachment]').remove();
		}
	},

	checkAttachments: function (e) {
		var submitter = ($.type(e) === 'undefined') ? '' : e.originalEvent.submitter.name;
		var file = Post.fileInput[0];
		if (!file) {
			return true;
		}

		if (!file.files.length && (submitter == 'newattachment' || submitter == 'updateattachment')) {
			$.jGrowl(lang.attachment_missing, { theme: 'jgrowl_error' });
			return false;
		}

		if (mybb_max_file_uploads != 0) {
			var common = Post.getCommonFiles().length;
			var moreAllowed = (mybb_max_file_uploads - (Post.getAttachments().length - common));
			if (moreAllowed <= 0) {
				$.jGrowl(lang.error_maxattachpost.replace('{1}', mybb_max_file_uploads), { theme: 'jgrowl_error' });
				file.value = '';
				return false;
			} else if (file.files.length > moreAllowed) {
				$.jGrowl(lang.attachment_max_allowed_files.replace('{1}', (moreAllowed - common)), { theme: 'jgrowl_error' });
				file.value = '';
				return false;
			}
		}

		if (file.files.length > php_max_file_uploads && php_max_file_uploads != 0) {
			$.jGrowl(lang.attachment_too_many_files.replace('{1}', php_max_file_uploads), { theme: 'jgrowl_error' });
			file.value = '';
			return false;
		}

		var totalSize = 0;
		Post.fileInput.each(function () {
			for (var i = 0; i < this.files.length; i++) {
				totalSize += (this.files[i].size || this.files[i].fileSize);
			}
		});

		if (totalSize > php_max_upload_size && php_max_upload_size > 0) {
			var php_max_upload_size_pretty = Math.round(php_max_upload_size / 1e4) / 1e2;
			$.jGrowl(lang.attachment_too_big_upload.replace('{1}', php_max_upload_size_pretty), { theme: 'jgrowl_error' });
			file.value = '';
			return false;
		}

		return true;
	},
	fileInput: $(),
	form: $()
};

Post.init();