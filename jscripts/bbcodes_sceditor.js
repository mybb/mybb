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
		.set('center', { format: '[align=center]{0}[/align]' })
		.set('left', { format: '[align=left]{0}[/align]' })
		.set('right', { format: '[align=right]{0}[/align]' })
		.set('justify', { format: '[align=justify]{0}[/align]' });

	$.sceditor.command
		.set('center', { txtExec: ['[align=center]', '[/align]'] })
		.set('left', { txtExec: ['[align=left]', '[/align]'] })
		.set('right', { txtExec: ['[align=right]', '[/align]'] })
		.set('justify', { txtExec: ['[align=justify]', '[/align]'] });



	/************************************************
	 * Update font to support MyBB's BBCode dialect *
	 ************************************************/
	$.sceditor.plugins.bbcode.bbcode
		.set('list', {
			html: function(element, attrs, content) {
				var type = (attrs.defaultattr === '1' ? 'ol' : 'ul');

				if(attrs.defaultattr === 'a')
					type = 'ol type="a"';

				return '<' + type + '>' + content + '</' + type + '>';
			},

			breakAfter: false
		})
		.set('ul', { format: '[list]{0}[/list]' })
		.set('ol', {
			format: function($elm, content) {
				var type = ($elm.attr('type') === 'a' ? 'a' : '1');

				return '[list=' + type + ']' + content + '[/list]';
			}
		})
		.set('li', { format: '[*]{0}', excludeClosing: true })
		.set('*', { excludeClosing: true, isInline: false });

	$.sceditor.command
		.set('bulletlist', { txtExec: ['[list]\n[*]', '\n[/list]'] })
		.set('orderedlist', { txtExec: ['[list=1]\n[*]', '\n[/list]'] });



	/***********************************************************
	 * Update size tag to use xx-small-xx-large instead of 1-7 *
	 ***********************************************************/
	$.sceditor.plugins.bbcode.bbcode.set('size', {
		format: function($elm, content) {
			var	fontSize,
				sizes = ['xx-small', 'x-small', 'small', 'medium', 'large', 'x-large', 'xx-large'],
				size  = $elm.data('scefontsize');

			if(!size)
			{
				fontSize = $elm.css('fontSize');

				// Most browsers return px value but IE returns 1-7
				if(fontSize.indexOf('px') > -1) {
					// convert size to an int
					fontSize = fontSize.replace('px', '') - 0;
					size     = 1;

					if(fontSize > 9)
						size = 2;
					if(fontSize > 12)
						size = 3;
					if(fontSize > 15)
						size = 4;
					if(fontSize > 17)
						size = 5;
					if(fontSize > 23)
						size = 6;
					if(fontSize > 31)
						size = 7;
				}
				else
					size = (~~fontSize) + 1;

				if(size > 7)
					size = 7;
				if(size < 1)
					size = 1;

				size = sizes[size-1];
			}

			return '[size=' + size + ']' + content + '[/size]';
		},
		html: function(token, attrs, content) {
			return '<span data-scefontsize="' + attrs.defaultattr + '" style="font-size:' + attrs.defaultattr + '">' + content + '</span>';
		}
	});

	$.sceditor.command.set('size', {
		_dropDown: function(editor, caller, callback) {
			var	content   = $('<div />'),
				clickFunc = function (e) {
					callback($(this).data('size'));
					editor.closeDropDown(true);
					e.preventDefault();
				};

			for (var i=1; i < 7; i++)
				content.append($('<a class="sceditor-fontsize-option" data-size="' + i + '" href="#"><font size="' + i + '">' + i + '</font></a>').click(clickFunc));

			editor.createDropDown(caller, 'fontsize-picker', content);
		},
		txtExec: function(caller) {
			var	editor = this,
				sizes = ['xx-small', 'x-small', 'small', 'medium', 'large', 'x-large', 'xx-large'];

			$.sceditor.command.get('size')._dropDown(
				editor,
				caller,
				function(size) {
					size = (~~size);
					size = (size > 7) ? 7 : ( (size < 1) ? 1 : size );

					editor.insertText('[size=' + sizes[size] + ']', '[/size]');
				}
			);
		}
	});



	/********************************************
	 * Update quote to support pid and dateline *
	 ********************************************/
	$.sceditor.plugins.bbcode.bbcode.set('quote', {
		format: function(element, content) {
			var	author = '',
				$elm  = $(element),
				$cite = $elm.children('cite').first();

			if($cite.length === 1 || $elm.data('author'))
			{
				author = $cite.text() || $elm.data('author');

				$elm.data('author', author);
				$cite.remove();

				content	= this.elementToBbcode($(element));
				author  = '=' + author;

				$elm.prepend($cite);
			}

			if($elm.data('pid'))
				author += " pid='" + $elm.data('pid') + "'";

			if($elm.data('dateline'))
				author += " dateline='" + $elm.data('dateline') + "'";

			return '[quote' + author + ']' + content + '[/quote]';
		},
		html: function(token, attrs, content) {
			var data = '';

			if(attrs.pid)
				data += ' data-pid="' + attrs.pid + '"';

			if(attrs.dateline)
				data += ' data-dateline="' + attrs.dateline + '"';

			if(typeof attrs.defaultattr !== "undefined")
				content = '<cite>' + attrs.defaultattr + '</cite>' + content;

			return '<blockquote' + data + '>' + content + '</blockquote>';
		},
		quoteType: function(val, name) {
			return "'" + val.replace("'", "\\'") + "'";
		},
		breakStart: true,
		breakEnd: true
	});



	/************************************************************
	 * Update font tag to allow limiting to only first in stack *
	 ************************************************************/
	$.sceditor.plugins.bbcode.bbcode.set('font', {
		format: function(element, content) {
			var font;

			if(element[0].nodeName.toLowerCase() !== 'font' || !(font = element.attr('face')))
				font = element.css('font-family');

			return '[font=' + this.stripQuotes(font) + ']' + content + '[/font]';
		}
	});



	/************************
	 * Add MyBB PHP command *
	 ************************/
	$.sceditor.command.set('php', {
		exec: function() {
			this.wysiwygEditorInsertHtml('<code class="phpcodeblock">', '</code>');
		},
		txtExec: ['[php]', '[/php]'],
		tooltip: "PHP"
	});
	
	$.sceditor.plugins.bbcode.bbcode.set('php', {
		isInline: false,
		allowedChildren: ['#', '#newline'],
		format: '[php]{0}[/php]',
		html: '<code class="phpcodeblock">{0}</code>'
	});



	/******************************
	 * Update code to support PHP *
	 ******************************/
	$.sceditor.plugins.bbcode.bbcode.set('code', {
		tags: {
			code: null
		},
		isInline: false,
		allowedChildren: ['#', '#newline'],
		format: function (element, content) {
			if ($(element[0]).hasClass('phpcodeblock')) {
				return '[php]' + content + '[/php]';
			}
			return '[code]' + content + '[/code]';
		},
		html: '<code>{0}</code>'
	});



	/**************************
	 * Add MyBB video command *
	 **************************/
	$.sceditor.plugins.bbcode.bbcode.set('video', {
		allowsEmpty: true,
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
						'<option value="dailymotion">Dailymotion</option>' +
						'<option value="metacafe">MetaCafe</option>' +
						'<option value="veoh">Veoh</option>' +
						'<option value="vimeo">Vimeo</option>' +
						'<option value="youtube">Youtube</option>' +
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
	 * Remove last bits of table support *
	 *************************************/
	$.sceditor.command.remove('table');
	$.sceditor.plugins.bbcode.bbcode.remove('table')
					.remove('tr')
					.remove('th')
					.remove('td');



	/********************************************
	 * Remove code and quote if in partial mode *
	 ********************************************/
	if(partialmode) {
		$.sceditor.plugins.bbcode.bbcode.remove('code').remove('php').remove('quote').remove('video').remove('img');
		$.sceditor.command
			.set('code', {
				exec: function() {
					this.insert('[code]', '[/code]');
				}
			})
			.set('php', {
				exec: function() {
					this.insert('[php]', '[/php]');
				}
			})
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
});

