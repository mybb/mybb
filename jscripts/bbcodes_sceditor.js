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
			'Vimeo': {
				'match': /vimeo.com\/(\d+)($|\/)/,
				'url': '//player.vimeo.com/video/',
				'html': '<iframe src="{url}" width="500" height="281" frameborder="0" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>'
			},
			'Youtube': {
				'match': /(?:v=|v\/|embed\/|youtu\.be\/)(.{11})/,
				'url': '//www.youtube-nocookie.com/embed/',
				'html': '<iframe width="560" height="315" src="{url}" frameborder="0" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>'
			},
			'Twitch': {
				'match': /twitch\.tv\/(?:[\w+_-]+)\/v\/(\d+)/,
				'url': '//player.twitch.tv/?video=v',
				'html': '<iframe src="{url}" frameborder="0" scrolling="no" height="378" width="620" data-mybb-vt="{type}" data-mybb-vsrc="{src}"></iframe>'
			}
		}
	};

	for (var i in mybbCmd.video) {
		var item = mybbCmd.video[i];
		$.sceditor.defaultOptions.allowedIframeUrls.push(item.url);
	}

	// Add custom MyBB CSS
	$('<style type="text/css">' +
		'.sceditor-dropdown { text-align: ' + ($('body').css('direction') === 'rtl' ? 'right' : 'left') + '; }' +
		'</style>').appendTo('body');

	// Update editor to use align= as alignment
	$.sceditor.formats.bbcode
		.set('align', {
			html: function (element, attrs, content) {
				return '<div align="' + ($.sceditor.escapeEntities(attrs.defaultattr) || 'left') + '">' + content + '</div>';
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
			isInline: false,
			skipLastLineBreak: true,
			breakStart: true,
			breakAfter: true,
		})
		.set('ul', {
			format: '[list]{0}[/list]',
			isInline: false,
			skipLastLineBreak: true,
			breakStart: true,
			breakAfter: true,
		})
		.set('ol', {
			format: function ($elm, content) {
				var type = ($($elm).attr('type') === 'a' ? 'a' : '1');

				return '[list=' + type + ']' + content + '[/list]';
			},
			isInline: false,
			skipLastLineBreak: true,
			breakStart: true,
			breakAfter: true,
		})
		.set('li', {
			format: '[*]{0}',
			isInline: false,
			skipLastLineBreak: true,
		})
		.set('*', {
			html: '<li>{0}</li>',
			isInline: false,
			excludeClosing: true,
			skipLastLineBreak: true,
			breakAfter: false,
		});

	$.sceditor.command
		.set('bulletlist', {
			txtExec: function (caller, selected) {
				var content = '';

				$.each(selected.split(/\r?\n/), function () {
					content += (content ? '\n' : '') +
						'[*]' + this;
				});

				this.insertText('[list]\n' + content + '\n[/list]');
			}
		})
		.set('orderedlist', {
			txtExec: function (caller, selected) {
				var content = '';

				$.each(selected.split(/\r?\n/), function () {
					content += (content ? '\n' : '') +
						'[*]' + this;
				});

				this.insertText('[list=1]\n' + content + '\n[/list]');
			}
		});

	// Update size tag to use xx-small-xx-large instead of 1-7
	$.sceditor.formats.bbcode.set('size', {
		format: function ($elm, content) {
			var fontsize = 1,
				scefontsize = $($elm).data('scefontsize'),
				parsed = parseInt(scefontsize, 10),
				size = parseInt($($elm).attr('size'), 10),
				iframe = $('.sceditor-container iframe'),
				editor_body = $('body', iframe.contents());

			if ($($elm).css('font-size') == editor_body.css('font-size')) {
				// Eliminate redundant [size] tags for unformatted text.
				// Part of the fix for the browser-dependent bug of issue #4184.
				// Also fixes the browser-dependent bug described here:
				//   <https://community.mybb.com/thread-229726.html>
				fontsize = -1;
			} else if (!isNaN(size) && size >= 1 && size <= mybbCmd.fsStr.length) {
				fontsize = mybbCmd.fsStr[size - 1];
			} else if ($.inArray(scefontsize, mybbCmd.fsStr) !== -1) {
				fontsize = scefontsize;
			} else if (!isNaN(parsed)) {
				fontsize = parsed;
			}

			return fontsize != -1 ? '[size=' + fontsize + ']' + content + '[/size]' : content;
		},
		html: function (token, attrs, content) {
			var size = 0,
				units = "",
				parsed = parseInt(attrs.defaultattr, 10);
			if (!isNaN(parsed)) {
				size = attrs.defaultattr;
				if (size < 1) {
					size = 1;
				} else if (size > 50) {
					size = 50;
				}
				units = "pt";
			} else {
				var fsStrPos = $.inArray(attrs.defaultattr, mybbCmd.fsStr);
				if (fsStrPos !== -1) {
					size = attrs.defaultattr;
				}
			}
			return '<font data-scefontsize="' + $.sceditor.escapeEntities(attrs.defaultattr) + '" style="font-size: ' + size + units + ';">' + content + '</font>';
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
				content.append($('<a class="sceditor-fontsize-option" data-size="' + i + '" href="#"><font style="font-size: ' + mybbCmd.fsStr[i-1] + '">' + i + '</font></a>').on('click', clickFunc));

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
				data += ' data-pid="' + $.sceditor.escapeEntities(attrs.pid) + '"';

			if (attrs.dateline)
				data += ' data-dateline="' + $.sceditor.escapeEntities(attrs.dateline) + '"';

			if (typeof attrs.defaultattr !== "undefined")
				content = '<cite>' + $.sceditor.escapeEntities(attrs.defaultattr).replace(/ /g, '&nbsp;') + '</cite>' + content;

			return '<blockquote' + data + '>' + content + '</blockquote>';
		},
		quoteType: function (val, name) {
			var quoteChar = val.indexOf('"') !== -1 ? "'" : '"';

			return quoteChar + val + quoteChar;
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

			var iframe = $('.sceditor-container iframe');
			var editor_body = $('body', iframe.contents());

			if (typeof font == 'string' && font != '' && font != 'defaultattr'
			    &&
			    // Eliminate redundant [font] tags for unformatted text.
			    // Part of the fix for the browser-dependent bug of issue #4184.
			    font != editor_body.css('font-family')) {
				font = font.trim();
				// Strip all-enclosing double quotes from fonts so long as
				// they are the only double quotes present...
				if (font[0] == '"' && font[font.length-1] == '"' && (font.match(/"/g) || []).length == 2) {
					font = font.substr(1, font.length-2);
				}
				// ...and then replace any other occurrence(s) of double quotes
				// in fonts with single quotes.
				// This is the client-side aspect of the fix for
				// the browser-independent bug of issue #4182.
				font = font.replace(/"/g, "'");
				return '[font=' + font + ']' + content + '[/font]';
			} else {
				return content;
			}
		},
		html: function (token, attrs, content) {
			if (typeof attrs.defaultattr == 'string' && attrs.defaultattr != '' && attrs.defaultattr != '{defaultattr}') {
				return '<font face="' +
					$.sceditor.escapeEntities(attrs.defaultattr) +
					'">' + content + '</font>';
			} else {
				return content;
			}
		}
	});

	$.sceditor.formats.bbcode.set('color', {
		format: function (element, content) {
			var color, defaultColor;

			var iframe = $('.sceditor-container iframe');
			var editor_body = $('body', iframe.contents());

			if (element.nodeName.toLowerCase() != 'font' || !(color = $(element).attr('color'))) {
				color = $(element).css('color');
			}

			color = _normaliseColour(color);
			defaultColor = _normaliseColour(editor_body.css('color'));

			// Eliminate redundant [color] tags for unformatted text.
			// Part of the fix for the browser-dependent bug of issue #4184.
			return color != defaultColor
			         ? '[color=' + color + ']' + content + '[/color]'
				 : content;
		},
		html: function (token, attrs, content) {
			return '<font color="' +
				$.sceditor.escapeEntities(_normaliseColour(attrs.defaultattr), true) +
				'">' + content + '</font>';
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
				'<textarea type="text" id="php"></textarea>' +
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
				'<textarea type="text" id="code"></textarea>' +
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
			if (typeof params !== "undefined") {
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
				'<input type="text" id="videourl" placeholder="http://" />' +
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

	// Update image command to support MyBB syntax
	$.sceditor.formats.bbcode.set('img', {
		format: function (element, content) {
			if ($(element).data('sceditor-emoticon'))
				return content;

			var url = $(element).attr('src'),
				width = $(element).attr('width'),
				height = $(element).attr('height'),
				align = $(element).data('scealign');

			var attrs = width !== undefined && height !== undefined && width > 0 && height > 0
				? '=' + width + 'x' + height
				: ''
			;

			if (align === 'left' || align === 'right')
				attrs += ' align='+align

			return '[img' + attrs + ']' + url + '[/img]';
		},
		html: function (token, attrs, content) {
			var	width, height, match,
				align = attrs.align,
				attribs = '';

			// handle [img=340x240]url[/img]
			if (attrs.defaultattr) {
				match = attrs.defaultattr.split(/x/i);

				width  = match[0];
				height = (match.length === 2 ? match[1] : match[0]);

				if (width !== undefined && height !== undefined && width > 0 && height > 0) {
					attribs +=
						' width="' + $.sceditor.escapeEntities(width, true) + '"' +
						' height="' + $.sceditor.escapeEntities(height, true) + '"';
				}
			}

			if (align === 'left' || align === 'right')
				attribs += ' style="float: ' + align + '" data-scealign="' + align + '"';

			return '<img' + attribs +
				' src="' + $.sceditor.escapeUriScheme(content) + '" />';
		}
	})

	$.sceditor.command.set('image', {
		_dropDown: function (editor, caller) {
			var $content;

			$content = $(
				'<div>' +
				'<div>' +
				'<label for="image">' + editor._('URL') + ':</label> ' +
				'<input type="text" id="image" placeholder="https://" />' +
				'</div>' +
				'<div>' +
				'<label for="width">' + editor._('Width (optional)') + ':</label> ' +
				'<input type="text" id="width" size="2" />' +
				'</div>' +
				'<div>' +
				'<label for="height">' + editor._('Height (optional)') + ':</label> ' +
				'<input type="text" id="height" size="2" />' +
				'</div>' +
				'<div>' +
				'<input type="button" class="button" value="' + editor._('Insert') + '" />' +
				'</div>' +
				'</div>'
			);

			$content.find('.button').on('click', function (e) {
				var url = $content.find('#image').val(),
					width = $content.find('#width').val(),
					height = $content.find('#height').val()
				;

				var attrs = width !== undefined && height !== undefined && width > 0 && height > 0
					? '=' + width + 'x' + height
					: ''
				;

				if (url)
					editor.insert('[img' + attrs + ']' + url + '[/img]');

				editor.closeDropDown(true);
				e.preventDefault();
			});

			editor.createDropDown(caller, 'insertimage', $content.get(0));
		},
		exec: function (caller) {
			$.sceditor.command.get('image')._dropDown(this, caller);
		},
		txtExec: function (caller) {
			$.sceditor.command.get('image')._dropDown(this, caller);
		},
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

	/**
	 * Converts a number 0-255 to hex.
	 *
	 * Will return 00 if number is not a valid number.
	 *
	 * Copied from the SCEditor's src/formats/bbcode.js file
	 * where it is unfortunately defined as private.
	 *
	 * @param  {any} number
	 * @return {string}
	 */
	function toHex(number) {
		number = parseInt(number, 10);

		if (isNaN(number)) {
			return '00';
		}

		number = Math.max(0, Math.min(number, 255)).toString(16);

		return number.length < 2 ? '0' + number : number;
	}

	/**
	 * Normalises a CSS colour to hex #xxxxxx format
	 *
	 * Copied from the SCEditor's src/formats/bbcode.js file
	 * where it is unfortunately defined as private.
	 *
	 * @param  {string} colorStr
	 * @return {string}
	 */
	function _normaliseColour(colorStr) {
		var match;

		colorStr = colorStr || '#000';

		// rgb(n,n,n);
		if ((match = colorStr.match(/rgb\((\d{1,3}),\s*?(\d{1,3}),\s*?(\d{1,3})\)/i))) {
			return '#' +
				toHex(match[1]) +
				toHex(match[2]) +
				toHex(match[3]);
		}

		// rgba(n,n,n,f.p);
		if ((match = colorStr.match(/rgba\((\d{1,3}),\s*?(\d{1,3}),\s*?(\d{1,3}),\s*?(\d*\.?\d+\s*)\)/i))) {
			return '#' +
			toHex(match[1]) +
			toHex(match[2]) +
			toHex(match[3]);
		}

		// expand shorthand
		if ((match = colorStr.match(/#([0-f])([0-f])([0-f])\s*?$/i))) {
			return '#' +
				match[1] + match[1] +
				match[2] + match[2] +
				match[3] + match[3];
		}

		return colorStr;
	}

});
