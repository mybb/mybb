var Post = {
	init: function () {
		$(function () {
			Post.fileInput = $("input[name='attachments[]']");
			Post.dropZone = $('#dropzone');
			Post.dropZone.find('div').text(lang.drop_files);
			Post.form = Post.fileInput.parents('form');

			Post.form.on('submit', Post.checkAttachments);
			Post.fileInput.on('change', Post.addAttachments);

			Post.dropZone.on('drag', function (e) {
				e.preventDefault();
			});

			Post.dropZone.on('dragstart',  function (e) {
				e.preventDefault();
				e.originalEvent.dataTransfer.setData('text/plain', '');
			});

			Post.dropZone.on('dragover dragenter', function (e) {
				e.preventDefault();
				$(this).addClass('activated').find('div').text(lang.upload_initiate);
			});

			Post.dropZone.on('dragleave dragend', function (e) {
				e.preventDefault();
				$(this).removeClass('activated').find('div').text(lang.drop_files);
			});

			Post.dropZone.on('click', function () {
				Post.fileInput.trigger('click');
			});

			Post.dropZone.on('drop', function (e) {
				e.preventDefault();
				$(this).removeClass('activated');
				var files = e.originalEvent.dataTransfer.files;
				Post.fileInput.prop('files', files).trigger('change');
			});

			Post.fileInput.parents().eq(1).hide();
			Post.dropZone.parents().eq(1).show();

			// prevent SCEditor from inserting [img] with data URI
			var $message = document.querySelector('#message');

			if ($message !== null) {
				new MutationObserver(function () {
					// run once #message is hidden by SCEditor, and the MyBBEditor instance becomes available

					if (typeof MyBBEditor !== 'undefined' && MyBBEditor !== null) {
						MyBBEditor.bind('valuechanged', function () {
							var oldValue = MyBBEditor.val();
							var newValue = oldValue.replace(/\[img]data:[a-z/]+;base64,[A-Za-z0-9+\/]+={0,2}\[\/img]/, '');

							if (oldValue !== newValue) {
								MyBBEditor.val(newValue);
							}
						});
					}
				}).observe($message, {attributes: true});
			}
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

									$(this).parent().find('.tcat>strong').text(data.usage);
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
				var list = document.createElement('ul');

				$.map(common, function (val) {
					var e = document.createElement('li');
					e.textContent = val;
					list.append(e);
				});

				MyBB.prompt(lang.update_confirm.replace("{1}", list.outerHTML), {
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
				xhr: function () {
					var x = $.ajaxSettings.xhr();
					x.upload.addEventListener("progress", function (e) {
						if (e.lengthComputable) {
							var completed = parseFloat((e.loaded / e.total) * 100).toFixed(2);
							$('#upload_bar').css('width', completed + '%');
							Post.dropZone.find('div').text(completed + '%');
							if (e.loaded === e.total) {
								$('#upload_bar').css('width', '0%');
								Post.dropZone.find('div').text(lang.drop_files);
							}
						}
					}, false);
					return x;
				},
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
							Post.fileInput.parents().eq(2).append(data.template
								.replace(/\{1\}/g, message[0])
								.replace('{2}', message[1])
								.replace('{3}', message[2])
								.replace('{4}', message[3]))
								.find('.tcat>strong').text(data.usage);
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
		var attachButton = $("input[name=newattachment], input[name=updateattachment]").eq(0).clone();

		if (Post.getAttachments().length) {
			if (!$('input[name=updateattachment]').length) {
				var updateButton = attachButton.clone()
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
				var newButton = attachButton.clone()
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
			if (moreAllowed < 0 || (!moreAllowed && file.files.length)) {
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
	dropZone: $(),
	form: $()
};

Post.init();