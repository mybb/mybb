var Post = {
	init: function()
	{
		$(function()
		{
			Post.initAttachments();
		});
	},

	loadMultiQuoted: function()
	{
		if(use_xmlhttprequest == 1)
		{
			tid = document.input.tid.value;
			
			$.ajax(
			{
				url: 'xmlhttp.php?action=get_multiquoted&tid='+tid,
				type: 'get',
				complete: function (request, status)
				{
					Post.multiQuotedLoaded(request, status);
				}
			});
			
			return false;
		}
		else
		{
			return true;
		}
	},

	loadMultiQuotedAll: function()
	{
		if(use_xmlhttprequest == 1)
		{
			$.ajax(
			{
				url: 'xmlhttp.php?action=get_multiquoted&load_all=1',
				type: 'get',
				complete: function (request, status)
				{
					Post.multiQuotedLoaded(request, status);
				}
			});
			
			return false;
		}
		else
		{
			return true;
		}
	},

	multiQuotedLoaded: function(request)
	{
		var json = JSON.parse(request.responseText);
		if(typeof response == 'object')
		{
			if(json.hasOwnProperty("errors"))
			{
				$.each(json.errors, function(i, message)
				{
					$.jGrowl(lang.post_fetch_error + ' ' + message, {theme:'jgrowl_error'});
				});
				return false;
			}
		}

		var id = 'message';
		if(typeof MyBBEditor !== 'undefined' && MyBBEditor !== null)
		{
			MyBBEditor.insert(json.message);
		}
		else
		{
			if($('#' + id).value)
			{
				$('#' + id).value += "\n";
			}
			$('#' + id).val($('#' + id).val() + json.message);
		}
		
		$('#multiquote_unloaded').hide();
		document.input.quoted_ids.value = 'all';
	},
	
	clearMultiQuoted: function()
	{
		$('#multiquote_unloaded').hide();
		Cookie.unset('multiquote');
	},
	
	removeAttachment: function(aid)
	{
		MyBB.prompt(removeattach_confirm, {
			buttons:[
					{title: yes_confirm, value: true},
					{title: no_confirm, value: false}
			],
			submit: function(e,v,m,f){
				if(v == true)
				{
					document.input.attachmentaid.value = aid;
					document.input.attachmentact.value = "remove";
					
					var form = $('input[name^=\'rem\']').parents('form');

					if(use_xmlhttprequest != 1)
					{
						form.append('<input type="submit" id="rem_submit" class="hidden" />');
						$('#rem_submit').trigger('click');
						return  false;
					}

					$.ajax({
						type: 'POST',
						url: form.attr('action') + '&ajax=1',
						data: form.serialize(),
						success: function(data) {
							if(data.hasOwnProperty("errors"))
							{
								$.each(data.errors, function(i, message)
								{
									$.jGrowl(lang.post_fetch_error + ' ' + message, {theme:'jgrowl_error'});
								});
								return false;
							}
							else if (data.success)
							{
								$('#attachment_'+aid).hide(500, function()
								{
									var instance = MyBBEditor;
									if(typeof MyBBEditor === 'undefined') {
										instance = $('#message').sceditor('instance');
									}

									if(instance.sourceMode())
									{
										instance.setSourceEditorValue(instance.getSourceEditorValue(false).split('[attachment=' + aid + ']').join(''));
									} else {
										instance.setWysiwygEditorValue(instance.getWysiwygEditorValue(false).split('[attachment=' + aid + ']').join(''));
									}

									$(this).remove();
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

	attachmentAction: function(aid,action)
	{
		document.input.attachmentaid.value = aid;
		document.input.attachmentact.value = action;
	},

	initAttachments: function()
	{
		$('form').on('submit', Post.checkAttachments);
	},

	checkAttachments: function()
	{
		var files = $("input[type='file']");
		var file = files.get(0);
		if (!file)
		{
			return true;
		}

		if (file.files.length > php_max_file_uploads && php_max_file_uploads != 0)
		{
			alert(lang.attachment_too_many_files.replace('{1}', php_max_file_uploads));
			file.value="";
			return false;
		}

		var totalSize = 0;
		files.each(function()
		{
			for (var i = 0; i < this.files.length; i++)
			{
				totalSize += this.files[i].size;
			}
		});

		if (totalSize > php_max_upload_size && php_max_upload_size > 0)
		{
			var php_max_upload_size_pretty = (function (size) {
				var i = size == 0 ? 0 : Math.floor( Math.log(size) / Math.log(1024) );
				return ( size / Math.pow(1024, i) ).toFixed(2) * 1 + ' ' + [lang.size_b, lang.size_kb, lang.size_mb, lang.size_gb, lang.size_tb, lang.size_pb, lang.size_eb, lang.size_zb, lang.size_yb][i];
			})(php_max_upload_size);
			alert(lang.attachment_too_big_upload.replace('{1}', php_max_upload_size_pretty));
			file.value="";
			return false;
		}

		return true;
	}
};

Post.init();