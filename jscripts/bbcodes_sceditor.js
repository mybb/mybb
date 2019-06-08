$(function ($) {
	'use strict';

	var mybbCmd = {
		align: ['left', 'center', 'right', 'justify'],
		fsStr: ['xx-small', 'x-small', 'small', 'medium', 'large', 'x-large', 'xx-large'],
		fSize: [9, 12, 15, 17, 23, 31],
		video: {
			'Dailymotion': {
				'match': /(dailymotion\.com\/video\/|dai\.ly\/)([^\/]+)/,
				'url': '//www.dailymotion.com/embed/video/',
				'html': '<iframe frameborder="0" width="480" height="270" src="{url}" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>'
			},
			'Facebook': {
				'match': /facebook\.com\/(?:photo.php\?v=|video\/video.php\?v=|video\/embed\?video_id=|v\/?)(\d+)/,
				'url': 'https://www.facebook.com/video/embed?video_id=',
				'html': '<iframe src="{url}" width="625" height="350" frameborder="0" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>'
			},
			'Liveleak': {
				'match': /liveleak\.com\/(?:view\?[a-z]=)([^\/]+)/,
				'url': 'http://www.liveleak.com/ll_embed?i=',
				'html': '<iframe width="500" height="300" src="{url}" frameborder="0" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>'
			},
			'MetaCafe': {
				'match': /metacafe\.com\/watch\/([^\/]+)/,
				'url': 'http://www.metacafe.com/embed/',
				'html': '<iframe src="{url}" width="440" height="248" frameborder=0 data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>'
			},
			'Mixer': {
				'match': /mixer\.com\/([^\/]+)/,
				'url': '//mixer.com/embed/player/',
				'html': '<iframe allowfullscreen="true" src="{url}" width="620" height="349" frameborder="0" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>'
			},
			'Vemio': {
				'match': /vimeo.com\/(\d+)($|\/)/,
				'url': '//player.vimeo.com/video/',
				'html': '<iframe src="{url}" width="500" height="281" frameborder="0" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>'
			},
			'Youtube': {
				'match': /(?:v=|v\/|embed\/|youtu\.be\/)(.{11})/,
				'url': '//www.youtube.com/embed/',
				'html': '<iframe width="560" height="315" src="{url}" frameborder="0" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>'
			},
			'Twitch': {
				'match': /twitch\.tv\/(?:[\w+_-]+)\/v\/(\d+)/,
				'url': '//player.twitch.tv/?video=v',
				'html': '<iframe src="{url}" frameborder="0" scrolling="no" height="378" width="620" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>'
			}
		}
	};

	// Add custom MyBB CSS
	$('<style type="text/css">' +
		'.sceditor-dropdown { text-align: ' + ($('body').css('direction') === 'rtl' ? 'right' : 'left') + '; }' +
		'</style>').appendTo('body');

	// Update editor to use align= as alignment
	$.sceditor.formats.bbcode
		.set('align', {
			html: function (element, attrs, content) {
				return '<div align="' + (attrs.defaultattr || 'left') + '">' + content + '</div>';
			},
			isInline: false
		});
	$.each(mybbCmd.align, function (i, val) {
		$.sceditor.formats.bbcode.set(val, {
			format: '[align=' + val + ']{0}[/align]'
		});
		$.sceditor.command
			.set(val, {
				txtExec: ['[align=' + val + ']', '[/align]']
			});
	});

	// Update font to support MyBB's BBCode dialect
	$.sceditor.formats.bbcode
		.set('list', {
			html: function (element, attrs, content) {
				var type = (attrs.defaultattr === '1' ? 'ol' : 'ul');

				if (attrs.defaultattr === 'a')
					type = 'ol type="a"';

				return '<' + type + '>' + content + '</' + type + '>';
			},

			breakAfter: false
		})
		.set('ul', {
			format: '[list]{0}[/list]'
		})
		.set('ol', {
			format: function ($elm, content) {
				var type = ($($elm).attr('type') === 'a' ? 'a' : '1');

				return '[list=' + type + ']' + content + '[/list]';
			}
		})
		.set('li', {
			format: '[*]{0}',
			excludeClosing: true
		})
		.set('*', {
			excludeClosing: true,
			isInline: true
		});

	$.sceditor.command
		.set('bulletlist', {
			txtExec: ['[list]\n[*]', '\n[/list]']
		})
		.set('orderedlist', {
			txtExec: ['[list=1]\n[*]', '\n[/list]']
		});

	// Update size tag to use xx-small-xx-large instead of 1-7
	$.sceditor.formats.bbcode.set('size', {
		format: function ($elm, content) {
			var fontSize,
				size = $($elm).attr('size');

			if (!size) {
				fontSize = $($elm).css('fontSize');
				// Most browsers return px value but IE returns 1-7
				if (fontSize.indexOf('px') > -1) {
					// convert size to an int
					fontSize = parseInt(fontSize);
					size = 1;
					$.each(mybbCmd.fSize, function (i, val) {
						if (fontSize > val) size = i + 2;
					});
				} else {
					size = (~~fontSize) + 1;
				}
				size = (size >= 7) ? mybbCmd.fsStr[6] : ((size <= 1) ? mybbCmd.fsStr[0] : mybbCmd.fsStr[size - 1]);
			} else {
				size = mybbCmd.fsStr[size - 1];
			}
			return '[size=' + size + ']' + content + '[/size]';
		},
		html: function (token, attrs, content) {
			var size = $.inArray(attrs.defaultattr, mybbCmd.fsStr) + 1;
			if (!isNaN(attrs.defaultattr)) {
				size = attrs.defaultattr;
				if (size > 7)
					size = 7;
				if (size < 1)
					size = 1;
			}
			if (size < 0) {
				size = 0;
			}
			return '<font data-scefontsize="' + attrs.defaultattr + '" size="' + size + '">' + content + '</font>';
		}
	});

	$.sceditor.command.set('size', {
		_dropDown: function (editor, caller, callback) {
			var content = $('<div />'),
				clickFunc = function (e) {
					callback($(this).data('size'));
					editor.closeDropDown(true);
					e.preventDefault();
				};

			for (var i = 1; i <= 7; i++)
				content.append($('<a class="sceditor-fontsize-option" data-size="' + i + '" href="#"><font size="' + i + '">' + i + '</font></a>').on('click', clickFunc));

			editor.createDropDown(caller, 'fontsize-picker', content.get(0));
		},
		exec: function (caller) {
			var editor = this;

			$.sceditor.command.get('size')._dropDown(
				editor,
				caller,
				function (fontSize) {
					editor.execCommand('fontsize', fontSize);
				}
			);
		},
		txtExec: function (caller) {
			var editor = this;

			$.sceditor.command.get('size')._dropDown(
				editor,
				caller,
				function (size) {
					size = (~~size);
					size = (size > 7) ? 7 : ((size < 1) ? 1 : size);
					editor.insertText('[size=' + mybbCmd.fsStr[size - 1] + ']', '[/size]');
				}
			);
		}
	});

	// Update quote to support pid and dateline
	$.sceditor.formats.bbcode.set('quote', {
		format: function (element, content) {
			var author = '',
				$elm = $(element),
				$cite = $elm.children('cite').first();
			$cite.html($cite.text());

			if ($cite.length === 1 || $elm.data('author')) {
				author = $cite.text() || $elm.data('author');

				$elm.data('author', author);
				$cite.remove();

				content = this.elementToBbcode(element);
				author = '=' + author.replace(/(^\s+|\s+$)/g, '');

				$elm.prepend($cite);
			}

			if ($elm.data('pid'))
				author += " pid='" + $elm.data('pid') + "'";

			if ($elm.data('dateline'))
				author += " dateline='" + $elm.data('dateline') + "'";

			return '[quote' + author + ']' + content + '[/quote]';
		},
		html: function (token, attrs, content) {
			var data = '';

			if (attrs.pid)
				data += ' data-pid="' + attrs.pid + '"';

			if (attrs.dateline)
				data += ' data-dateline="' + attrs.dateline + '"';

			if (typeof attrs.defaultattr !== "undefined")
				content = '<cite>' + attrs.defaultattr.replace(/ /g, '&nbsp;') + '</cite>' + content;

			return '<blockquote' + data + '>' + content + '</blockquote>';
		},
		quoteType: function (val, name) {
			return "'" + val.replace("'", "\\'") + "'";
		},
		breakStart: true,
		breakEnd: true
	});

	// Update font tag to allow limiting to only first in stack
	$.sceditor.formats.bbcode.set('font', {
		format: function (element, content) {
			var font;
			if (element.nodeName.toLowerCase() !== 'font' || !(font = $(element).attr('face')))
				font = $(element).css('font-family');


			if (typeof font == 'string' && font != '' && font != 'defaultattr') {
				return '[font=' + this.stripQuotes(font) + ']' + content + '[/font]';
			} else {
				return content;
			}
		},
		html: function (token, attrs, content) {
			if (typeof attrs.defaultattr == 'string' && attrs.defaultattr != '' && attrs.defaultattr != '{defaultattr}') {
				return '<font face="' +
					attrs.defaultattr +
					'">' + content + '</font>';
			} else {
				return content;
			}
		}
	});

	// Add MyBB PHP command
	$.sceditor.formats.bbcode.set('php', {
		allowsEmpty: true,
		isInline: false,
		allowedChildren: ['#', '#newline'],
		format: '[php]{0}[/php]',
		html: '<code class="phpcodeblock">{0}</code>'
	});

	$.sceditor.command.set("php", {
		_dropDown: function (editor, caller) {
			var $content;

			$content = $(
				'<div>' +
				'<div>' +
				'<label for="php">' + editor._('PHP') + ':</label> ' +
				'<textarea type="text" id="php" />' +
				'</div>' +
				'<div><input type="button" class="button" value="' + editor._('Insert') + '" /></div>' +
				'</div>'
			);

			setTimeout(function () {
				$content.find('#php').trigger('focus');
			}, 100);

			$content.find('.button').on('click', function (e) {
				var val = $content.find('#php').val(),
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

			editor.createDropDown(caller, 'insertphp', $content.get(0));
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

	// Update code to support PHP
	$.sceditor.formats.bbcode.set('code', {
		allowsEmpty: true,
		tags: {
			code: null
		},
		isInline: false,
		allowedChildren: ['#', '#newline'],
		format: function (element, content) {
			if ($(element).hasClass('phpcodeblock')) {
				return '[php]' + content + '[/php]';
			}
			return '[code]' + content + '[/code]';
		},
		html: '<code>{0}</code>'
	});

	$.sceditor.command.set("code", {
		_dropDown: function (editor, caller) {
			var $content;

			$content = $(
				'<div>' +
				'<div>' +
				'<label for="code">' + editor._('Code') + ':</label> ' +
				'<textarea type="text" id="code" />' +
				'</div>' +
				'<div><input type="button" class="button" value="' + editor._('Insert') + '" /></div>' +
				'</div>'
			);

			setTimeout(function () {
				$content.find('#code').trigger('focus');
			}, 100);

			$content.find('.button').on('click', function (e) {
				var val = $content.find('#code').val(),
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

			editor.createDropDown(caller, 'insertcode', $content.get(0));
		},
		exec: function (caller) {
			if ($.trim(this.getRangeHelper().selectedRange())) {
				this.insert('[code]', '[/code]');
				return;
			}
			$.sceditor.command.get('code')._dropDown(this, caller);
		},
		txtExec: ['[code]', '[/code]'],
	});

	// Update email to support description
	$.sceditor.command.set('email', {
		_dropDown: function (editor, caller) {
			var $content;

			$content = $(
				'<div>' +
				'<div>' +
				'<label for="email">' + editor._('E-mail:') + '</label> ' +
				'<input type="text" id="email" />' +
				'</div>' +
				'<div>' +
				'<label for="des">' + editor._('Description (optional):') + '</label> ' +
				'<input type="text" id="des" />' +
				'</div>' +
				'<div><input type="button" class="button" value="' + editor._('Insert') + '" /></div>' +
				'</div>'
			);

			$content.find('.button').on('click', function (e) {
				var val = $content.find('#email').val(),
					description = $content.find('#des').val();

				if (val) {
					// needed for IE to reset the last range
					$(editor).trigger('focus');

					if (!editor.getRangeHelper().selectedHtml() || description) {
						if (!description)
							description = val;

						editor.insert('[email=' + val + ']' + description + '[/email]');
					} else
						editor.execCommand('createlink', 'mailto:' + val);
				}

				editor.closeDropDown(true);
				e.preventDefault();
			});

			editor.createDropDown(caller, 'insertemail', $content.get(0));
		},
		exec: function (caller) {
			$.sceditor.command.get('email')._dropDown(this, caller);
		}
	});

	// Add MyBB video command
	$.sceditor.formats.bbcode.set('video', {
		allowsEmpty: true,
		allowedChildren: ['#', '#newline'],
		tags: {
			iframe: {
				'data-mybb-vt': null
			}
		},
		format: function ($element, content) {
			return '[video=' + $($element).data('mybb-vt') + ']' + $($element).data('mybb-vsrc') + '[/video]';
		},
		html: function (token, attrs, content) {
			var params = mybbCmd.video[Object.keys(mybbCmd.video).find(key => key.toLowerCase() === attrs.defaultattr)];
			var matches, url;
			var n = (attrs.defaultattr == 'dailymotion') ? 2 : 1;
			if (params['html']) {
				matches = content.match(params['match']);
				url = matches ? params['url'] + matches[n] : false;
			}
			if (url) {
				return params['html'].replace('{url}', url).replace('{src}', content).replace('{type}', attrs.defaultattr);
			}
			return $.sceditor.escapeEntities(token.val + content + (token.closing ? token.closing.val : ''));
		}
	});

	$.sceditor.command.set('video', {
		_dropDown: function (editor, caller) {
			var $content, videourl, videotype, videoOpts;

			$.each(mybbCmd.video, function (provider, data) {
				videoOpts += '<option value="' + provider.toLowerCase() + '">' + editor._(provider) + '</option>';
			});
			$content = $(
				'<div>' +
				'<div>' +
				'<label for="videotype">' + editor._('Video Type:') + '</label> ' +
				'<select id="videotype">' + videoOpts + '</select>' +
				'</div>' +
				'<div>' +
				'<label for="link">' + editor._('Video URL:') + '</label> ' +
				'<input type="text" id="videourl" value="http://" />' +
				'</div>' +
				'<div><input type="button" class="button" value="' + editor._('Insert') + '" /></div>' +
				'</div>'
			);

			$content.find('.button').on('click', function (e) {
				videourl = $content.find('#videourl').val();
				videotype = $content.find('#videotype').val();

				if (videourl !== '' && videourl !== 'http://')
					editor.insert('[video=' + videotype + ']' + videourl + '[/video]');

				editor.closeDropDown(true);
				e.preventDefault();
			});

			editor.createDropDown(caller, 'insertvideo', $content.get(0));
		},
		exec: function (caller) {
			$.sceditor.command.get('video')._dropDown(this, caller);
		},
		txtExec: function (caller) {
			$.sceditor.command.get('video')._dropDown(this, caller);
		},
		tooltip: 'Insert a video'
	});

	// Remove last bits of table, superscript/subscript, youtube and ltr/rtl support
	$.sceditor.command
		.remove('table').remove('subscript').remove('superscript').remove('youtube').remove('ltr').remove('rtl');

	$.sceditor.formats.bbcode
		.remove('table').remove('tr').remove('th').remove('td').remove('sub').remove('sup').remove('youtube').remove('ltr').remove('rtl');

	// Remove code and quote if in partial mode
	if (partialmode) {
		$.sceditor.formats.bbcode.remove('code').remove('php').remove('quote').remove('video').remove('img');
		$.sceditor.command
			.set('image', {
				exec: function (caller) {
					var editor = this,
						content = $(this._('<form><div><label for="link">{0}</label> <input type="text" id="image" value="http://" /></div>' +
							'<div><label for="width">{1}</label> <input type="text" id="width" size="2" /></div>' +
							'<div><label for="height">{2}</label> <input type="text" id="height" size="2" /></div></form>',
							this._("URL:"),
							this._("Width (optional):"),
							this._("Height (optional):")
						))
						.submit(function () {
							return false;
						});

					content.append($(this._('<div><input type="button" class="button" value="Insert" /></div>',
						this._("Insert")
					)).on('click', function (e) {
						var $form = $(this).parent('form'),
							val = $form.find('#image').val(),
							width = $form.find('#width').val(),
							height = $form.find('#height').val(),
							attrs = '';

						if (width && height) {
							attrs = '=' + width + 'x' + height;
						}

						if (val && val !== 'http://') {
							editor.wysiwygEditorInsertHtml('[img' + attrs + ']' + val + '[/img]');
						}

						editor.closeDropDown(true);
						e.preventDefault();
					}));

					editor.createDropDown(caller, 'insertimage', content.get(0));
				}
			})
			.set('quote', {
				exec: function () {
					this.insert('[quote]', '[/quote]');
				}
			});
	}

	// Fix url code
	$.sceditor.formats.bbcode.set('url', {
		html: function (token, attrs, content) {

			if (!attrs.defaultattr)
				attrs.defaultattr = content;

			return '<a href="' + $.sceditor.escapeUriScheme($.sceditor.escapeEntities(attrs.defaultattr)) + '">' + content + '</a>';
		}
	});
});