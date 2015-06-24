// This was taken from the SCEditor plugin for MyBB

$(document).ready(function($) {
	'use strict';

	var $document = $(document);


	/***********************
	 * Add custom MyBB CSS *
	 ***********************/
	$('<style type="text/css">' +
		'.sceditor-dropdown { text-align: ' + ($('body').css('direction') === 'rtl' ? 'right' :'left') + '; }' +
	'</style>').appendTo('body');



	/********************************************
	 * Update editor to use align= as alignment *
	 ********************************************/
	$.sceditor.plugins.bbcode.bbcode
		.set('align', {
			html: function(element, attrs, content) {
				return '<div align="' + (attrs.defaultattr || 'left') + '">' + content + '</div>';
			},
			isInline: false
		})



	/***********************************************************
	 * Update size tag to use xx-small-xx-large instead of 1-7 *
	 ***********************************************************/
	$.sceditor.command.set('size', {
		_dropDown: function(editor, caller, callback) {
			var	content   = $('<div />'),
				clickFunc = function (e) {
					callback($(this).data('size'));
					editor.closeDropDown(true);
					e.preventDefault();
				};

			for (var i=1; i <= 7; i++)
				content.append($('<a class="sceditor-fontsize-option" data-size="' + i + '" href="#"><font size="' + i + '">' + i + '</font></a>').click(clickFunc));

			editor.createDropDown(caller, 'fontsize-picker', content);
		},
		exec: function (caller) {
			var	editor = this,
				sizes = ['xx-small', 'x-small', 'small', 'medium', 'large', 'x-large', 'xx-large'];

			$.sceditor.command.get('size')._dropDown(
				editor,
				caller,
				function(fontSize) {
					editor.wysiwygEditorInsertHtml('<span data-scefontsize=' + sizes[fontSize-1] + ' style="font-size:' + sizes[fontSize-1] + '">', '</span>');
				}
			);
		}
	});



	/************************
	 * Add MyBB PHP command *
	 ************************/
	$.sceditor.plugins.bbcode.bbcode.set('php', {
		allowsEmpty: true,
		allowedChildren: ['#', '#newline'],
		format: '[php]{0}[/php]',
		html: function (token, attrs, content) {
			if (attrs.defaultattr) {
				return '[php='+ $.sceditor.escapeEntities(attrs.defaultattr) +']' + content + '[/php]';
			}

			return '<code class="phpcodeblock">' + content + '</code>';
		}
	});

	$.sceditor.command.set("php", {
		_dropDown: function (editor, caller) {
			var $content;

			$content = $(
				'<div>' +
					'<label for="php">' + editor._('PHP') + ':</label> ' +
					'<textarea type="text" id="php" />' +
				'</div>' +
				'<div><input type="button" class="button" value="' + editor._('Insert') + '" /></div>'
			);

			setTimeout(function() {
				$content.find('#php').focus();
			},100);

			$content.find('.button').click(function (e) {
				var	val = $content.find('#php').val(),
					before = '[php]',
					end = '[/php]';

				if (val) {
					before = before + val + end;
					end = null;
				}

				editor.insert(before, end);
				editor.closeDropDown(true);
				e.preventDefault();
			});

			editor.createDropDown(caller, 'insertphp', $content);
		},
		exec: function (caller) {
			if ($.trim(this.getRangeHelper().selectedRange())) {
				this.insert('[php]', '[/php]');
				return;
			}
			$.sceditor.command.get('php')._dropDown(this, caller);
		},
		txtExec: ['[php]', '[/php]'],
		tooltip: "PHP"
	});



	/******************************
	 * Update code to support PHP *
	 ******************************/
	$.sceditor.command.set("code", {
		_dropDown: function (editor, caller) {
			var $content;

			$content = $(
				'<div>' +
					'<label for="code">' + editor._('Code') + ':</label> ' +
					'<textarea type="text" id="code" />' +
				'</div>' +
				'<div><input type="button" class="button" value="' + editor._('Insert') + '" /></div>'
			);

			setTimeout(function() {
				$content.find('#code').focus();
			},100);

			$content.find('.button').click(function (e) {
				var	val = $content.find('#code').val(),
					before = '[code]',
					end = '[/code]';

				if (val) {
					before = before + val + end;
					end = null;
				}

				editor.insert(before, end);
				editor.closeDropDown(true);
				e.preventDefault();
			});

			editor.createDropDown(caller, 'insertcode', $content);
		},
		exec: function (caller) {
			if ($.trim(this.getRangeHelper().selectedRange())) {
				this.insert('[code]', '[/code]');
				return;
			}
			$.sceditor.command.get('code')._dropDown(this, caller);
		}
	});



	/***************************************
	 * Update email to support description *
	 ***************************************/
	$.sceditor.command.set('email', {
		_dropDown: function (editor, caller) {
			var $content;

			$content = $(
				'<div>' +
					'<label for="email">' + editor._('E-mail:') + '</label> ' +
					'<input type="text" id="email" />' +
				'</div>' +
				'<div>' +
					'<label for="des">' + editor._('Description (optional):') + '</label> ' +
					'<input type="text" id="des" />' +
				'</div>' +
				'<div><input type="button" class="button" value="' + editor._('Insert') + '" /></div>'
			);

			$content.find('.button').click(function (e) {
				var	val = $content.find('#email').val(),
					description = $content.find('#des').val();

				if(val) {
					// needed for IE to reset the last range
					editor.focus();

					if(!editor.getRangeHelper().selectedHtml() || description) {
						if(!description)
							description = val;

						editor.wysiwygEditorInsertHtml('<a href="' + 'mailto:' + val + '">' + description + '</a>');
					}
					else
						editor.execCommand('createlink', 'mailto:' + val);
					}

				editor.closeDropDown(true);
				e.preventDefault();
			});

			editor.createDropDown(caller, 'insertemail', $content);
		},
		exec: function (caller) {
			$.sceditor.command.get('email')._dropDown(this, caller);
		}
	});



	/**************************
	 * Add MyBB video command *
	 **************************/
	$.sceditor.plugins.bbcode.bbcode.set('video', {
		allowsEmpty: true,
		allowedChildren: ['#', '#newline'],
		tags: {
			iframe: {
				'data-mybb-vt': null
			}
		},
		format: function($element, content) {
			return '[video=' + $element.data('mybb-vt') + ']' + $element.data('mybb-vsrc') + '[/video]';
		},
		html: function(token, attrs, content) {
			var	matches, url,
				html = {
					dailymotion: '<iframe frameborder="0" width="480" height="270" src="{url}" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>',
					facebook: '<iframe src="{url}" width="625" height="350" frameborder="0" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>',
					liveleak: '<iframe width="500" height="300" src="{url}" frameborder="0" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>',
					metacafe: '<iframe src="{url}" width="440" height="248" frameborder=0 data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>',
					veoh: '<iframe src="{url}" width="410" height="341" frameborder="0" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>',
					vimeo: '<iframe src="{url}" width="500" height="281" frameborder="0" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>',
					youtube: '<iframe width="560" height="315" src="{url}" frameborder="0" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>'
				};

			if(html[attrs.defaultattr])
			{
				switch(attrs.defaultattr)
				{
					case 'dailymotion':
						matches = content.match(/dailymotion\.com\/video\/([^_]+)/);
						url     = matches ? 'http://www.dailymotion.com/embed/video/' + matches[1] : false;
						break;
					case 'facebook':
						matches = content.match(/facebook\.com\/(?:photo.php\?v=|video\/video.php\?v=|video\/embed\?video_id=|v\/?)(\d+)/);
						url     = matches ? 'https://www.facebook.com/video/embed?video_id=' + matches[1] : false;
						break;
					case 'liveleak':
						matches = content.match(/liveleak\.com\/(?:view\?i=)([^\/]+)/);
						url     = matches ? 'http://www.liveleak.com/ll_embed?i=' + matches[1] : false;
						break;
					case 'metacafe':
						matches = content.match(/metacafe\.com\/watch\/([^\/]+)/);
						url     = matches ? 'http://www.metacafe.com/embed/' + matches[1] : false;
						break;
					case 'veoh':
						matches = content.match(/veoh\.com\/watch\/([^\/]+)/);
						url     = matches ? '//www.veoh.com/swf/webplayer/WebPlayer.swf?videoAutoPlay=0&permalinkId=' + matches[1] : false;
						break;
					case 'vimeo':
						matches = content.match(/vimeo.com\/(\d+)($|\/)/);
						url     = matches ? '//player.vimeo.com/video/' + matches[1] : false;
						break;
					case 'youtube':
						matches = content.match(/(?:v=|v\/|embed\/|youtu\.be\/)(.{11})/);
						url     = matches ? '//www.youtube.com/embed/' + matches[1] : false;
						break;
				}

				if(url)
				{
					return html[attrs.defaultattr]
						.replace('{url}', url)
						.replace('{src}', content)
						.replace('{type}', attrs.defaultattr);
				}
			}

			return token.val + content + (token.closing ? token.closing.val : '');
		}
	});

	$.sceditor.command.set('video', {
		_dropDown: function (editor, caller) {
			var $content, videourl, videotype;

			// Excludes MySpace TV and Yahoo Video as I couldn't actually find them. Maybe they are gone now?
			$content = $(
				'<div>' +
					'<label for="videotype">' + editor._('Video Type:') + '</label> ' +
					'<select id="videotype">' +
						'<option value="dailymotion">' + editor._('Dailymotion') + '</option>' +
						'<option value="facebook">' + editor._('Facebook') + '</option>' +
						'<option value="liveleak">' + editor._('LiveLeak') + '</option>' +
						'<option value="metacafe">' + editor._('MetaCafe') + '</option>' +
						'<option value="veoh">' + editor._('Veoh') + '</option>' +
						'<option value="vimeo">' + editor._('Vimeo') + '</option>' +
						'<option value="youtube">' + editor._('Youtube') + '</option>' +
					'</select>'+
				'</div>' +
				'<div>' +
					'<label for="link">' + editor._('Video URL:') + '</label> ' +
					'<input type="text" id="videourl" value="http://" />' +
				'</div>' +
				'<div><input type="button" class="button" value="' + editor._('Insert') + '" /></div>'
			);

			$content.find('.button').click(function (e) {
				videourl  = $content.find('#videourl').val();
				videotype = $content.find('#videotype').val();

				if (videourl !== '' && videourl !== 'http://')
					editor.insert('[video=' + videotype + ']' + videourl + '[/video]');

				editor.closeDropDown(true);
				e.preventDefault();
			});

			editor.createDropDown(caller, 'insertvideo', $content);
		},
		exec: function (caller) {
			$.sceditor.command.get('video')._dropDown(this, caller);
		},
		txtExec: function (caller) {
			$.sceditor.command.get('video')._dropDown(this, caller);
		},
		tooltip: 'Insert a video'
	});



	/*************************************
	 * Remove last bits of table, superscript/subscript, youtube and ltr/rtl support *
	 *************************************/
	$.sceditor.command
	.remove('table').remove('subscript').remove('superscript').remove('youtube').remove('ltr').remove('rtl');

	$.sceditor.plugins.bbcode.bbcode
	.remove('table').remove('tr').remove('th').remove('td').remove('sub').remove('sup').remove('youtube').remove('ltr').remove('rtl');



	/********************************************
	 * Remove code and quote if in partial mode *
	 ********************************************/
	if(partialmode) {
		$.sceditor.plugins.bbcode.bbcode.remove('code').remove('php').remove('quote').remove('video').remove('img');
		$.sceditor.command
			.set('image', {
				exec:  function (caller) {
					var	editor  = this,
						content = $(this._('<form><div><label for="link">{0}</label> <input type="text" id="image" value="http://" /></div>' +
							'<div><label for="width">{1}</label> <input type="text" id="width" size="2" /></div>' +
							'<div><label for="height">{2}</label> <input type="text" id="height" size="2" /></div></form>',
								this._("URL:"),
								this._("Width (optional):"),
								this._("Height (optional):")
							))
						.submit(function () {return false;});

					content.append($(this._('<div><input type="button" class="button" value="Insert" /></div>',
							this._("Insert")
						)).click(function (e) {
						var	$form = $(this).parent('form'),
							val = $form.find('#image').val(),
							width = $form.find('#width').val(),
							height = $form.find('#height').val(),
							attrs = '';

						if(width && height) {
							attrs = '=' + width + 'x' + height;
						}

						if(val && val !== 'http://') {
							editor.wysiwygEditorInsertHtml('[img' + attrs + ']' + val + '[/img]');
						}

						editor.closeDropDown(true);
						e.preventDefault();
					}));

					editor.createDropDown(caller, 'insertimage', content);
				}
			})
			.set('quote', {
				exec: function() {
					this.insert('[quote]', '[/quote]');
				}
			});
	}



	/****************
	 * Fix url code *
	 ****************/
	$.sceditor.plugins.bbcode.bbcode.set('url', {
			html: function(token, attrs, content) {

				if(!attrs.defaultattr)
					attrs.defaultattr = content;

				return '<a href="' + $.sceditor.escapeUriScheme($.sceditor.escapeEntities(attrs.defaultattr)) + '">' + content + '</a>';
			}
	});
});