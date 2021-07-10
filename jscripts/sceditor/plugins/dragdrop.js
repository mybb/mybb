/**
 * SCEditor Drag and Drop Plugin
 * http://www.sceditor.com/
 *
 * Copyright (C) 2017, Sam Clarke (samclarke.com)
 *
 * SCEditor is licensed under the MIT license:
 *	http://www.opensource.org/licenses/mit-license.php
 *
 * @author Sam Clarke
 */
(function (sceditor) {
	'use strict';

	/**
	 * Place holder GIF shown while image is loading.
	 * @type {string}
	 * @private
	 */
	var loadingGif = 'data:image/gif;base64,R0lGODlhlgBkAPABAH19ffb29iH5BAAK' +
		'AAAAIf4aQ3JlYXRlZCB3aXRoIGFqYXhsb2FkLmluZm8AIf8LTkVUU0NBUEUyLjADAQA' +
		'AACwAAAAAlgBkAAAC1YyPqcvtD6OctNqLs968+w+G4kiW5omm6sq27gvH8kzX9o3n+s' +
		'73/g8MCofEovGITCqXzKbzCY1Kp9Sq9YrNarfcrvcLDovH5LL5jE6r1+y2+w2Py+f0u' +
		'v2OvwD2fP6iD/gH6Pc2GIhg2JeQSNjGuLf4GMlYKIloefAIUEl52ZmJyaY5mUhqyFnq' +
		'mQr6KRoaMKp66hbLumpQ69oK+5qrOyg4a6qYV2x8jJysvMzc7PwMHS09TV1tfY2drb3' +
		'N3e39DR4uPk5ebn6Onq6+zt7u/g4fL99UAAAh+QQACgAAACwAAAAAlgBkAIEAAAB9fX' +
		'329vYAAAAC3JSPqcvtD6OctNqLs968+w+G4kiW5omm6sq27gvH8kzX9o3n+s73/g8MC' +
		'ofEovGITCqXzKbzCY1Kp9Sq9YrNarfcrvcLDovH5LL5jE6r1+y2+w2Py+f0uv2OvwD2' +
		'fP4iABgY+CcoCNeHuJdQyLjIaOiWiOj4CEhZ+SbZd/nI2RipqYhQOThKGpAZCuBZyAr' +
		'ZprpqSupaCqtaazmLCRqai7rb2av5W5wqSShcm8fc7PwMHS09TV1tfY2drb3N3e39DR' +
		'4uPk5ebn6Onq6+zt7u/g4fLz9PX29/j5/vVAAAIfkEAAoAAAAsAAAAAJYAZACBAAAAf' +
		'X199vb2AAAAAuCUj6nL7Q+jnLTai7PevPsPhuJIluaJpurKtu4Lx/JM1/aN5/rO9/4P' +
		'DAqHxKLxiEwql8ym8wmNSqfUqvWKzWq33K73Cw6Lx+Sy+YxOq9fstvsNj8vn9Lr9jr8' +
		'E9nz+AgAYGLjQVwhXiJgguAiYgGjo9tinyCjoKLn3hpmJUGmJsBmguUnpCXCJOZraaX' +
		'oKShoJe9DqehCqKlnqiZobuzrbyvuIO8xqKpxIPKlwrPCbBx0tPU1dbX2Nna29zd3t/' +
		'Q0eLj5OXm5+jp6uvs7e7v4OHy8/T19vf4+fr7/P379UAAAh+QQACgAAACwAAAAAlgBk' +
		'AIEAAAB9fX329vYAAAAC4JSPqcvtD6OctNqLs968+w+G4kiW5omm6sq27gvH8kzX9o3' +
		'n+s73/g8MCofEovGITCqXzKbzCY1Kp9Sq9YrNarfcrvcLDovH5LL5jE6r1+y2+w2Py+' +
		'f0uv2OvwT2fP6iD7gAMEhICAeImIAYiFDoOPi22KcouZfw6BhZGUBZeYlp6LbJiTD6C' +
		'Qqg6Vm6eQqqKtkZ24iaKtrKunpQa9tmmju7Wwu7KFtMi3oYDMzompkHHS09TV1tfY2d' +
		'rb3N3e39DR4uPk5ebn6Onq6+zt7u/g4fLz9PX29/j5+vv8/f31QAADs=';

	/**
	 * Basic check for browser support
	 * @type {boolean}
	 * @private
	 */
	var isSupported = typeof window.FileReader !== 'undefined';
	var base64DataUri = /data:[^;]+;base64,/i;

	function base64DataUriToBlob(url) {
		// 5 is length of "data:" prefix
		var mime = url.substr(5, url.indexOf(';') - 5);
		var data = atob(url.substr(url.indexOf(',') + 1));
		/* global Uint8Array */
		var binary = new Uint8Array(data.length);

		for (var i = 0; i < data.length; i++) {
			binary[i] = data[i].charCodeAt(0);
		}

		try {
			return new Blob([binary], { type: mime });
		} catch (e) {
			return null;
		}
	}

	sceditor.plugins.dragdrop = function () {
		if (!isSupported) {
			return;
		}

		var base = this;
		var	opts;
		var editor;
		var handleFile;
		var container;
		var cover;
		var placeholderId = 0;


		function hideCover() {
			cover.style.display = 'none';
			container.className = container.className.replace(/(^| )dnd( |$)/g, '');
		}

		function showCover() {
			if (cover.style.display === 'none') {
				cover.style.display = 'block';
				container.className += ' dnd';
			}
		}

		function isAllowed(file) {
			// FF sets type to application/x-moz-file until it has been dropped
			if (file.type !== 'application/x-moz-file' && opts.allowedTypes &&
				opts.allowedTypes.indexOf(file.type) < 0) {
				return false;
			}

			return opts.isAllowed ? opts.isAllowed(file) : true;
		};

		function createHolder(toReplace) {
			var placeholder = document.createElement('img');
			placeholder.src = loadingGif;
			placeholder.className = 'sceditor-ignore';
			placeholder.id = 'sce-dragdrop-' + placeholderId++;

			function replace(html) {
				var node = editor
					.getBody()
					.ownerDocument
					.getElementById(placeholder.id);

				if (node) {
					if (typeof html === 'string') {
						node.insertAdjacentHTML('afterend', html);
					}

					node.parentNode.removeChild(node);
				}
			}

			return function () {
				if (toReplace) {
					toReplace.parentNode.replaceChild(placeholder, toReplace);
				} else {
					editor.wysiwygEditorInsertHtml(placeholder.outerHTML);
				}

				return {
					insert: function (html) {
						replace(html);
					},
					cancel: replace
				};
			};
		}

		function handleDragOver(e) {
			var dt    = e.dataTransfer;
			var files = dt.files.length || !dt.items ? dt.files : dt.items;

			for (var i = 0; i < files.length; i++) {
				// Dragging a string should be left to default
				if (files[i].kind === 'string') {
					return;
				}
			}

			showCover();
			e.preventDefault();
		}

		function handleDrop(e) {
			var dt    = e.dataTransfer;
			var files = dt.files.length || !dt.items ? dt.files : dt.items;

			hideCover();

			for (var i = 0; i < files.length; i++) {
				// Dragging a string should be left to default
				if (files[i].kind === 'string') {
					return;
				}

				if (isAllowed(files[i])) {
					handleFile(files[i], createHolder());
				}
			}

			e.preventDefault();
		}

		base.signalReady = function () {
			editor = this;
			opts = editor.opts.dragdrop || {};
			handleFile = opts.handleFile;

			container = editor.getContentAreaContainer().parentNode;

			cover = container.appendChild(sceditor.dom.parseHTML(
				'<div class="sceditor-dnd-cover" style="display: none">' +
					'<p>' + editor._('Drop files here') + '</p>' +
				'</div>'
			).firstChild);

			container.addEventListener('dragover', handleDragOver);
			container.addEventListener('dragleave', hideCover);
			container.addEventListener('dragend', hideCover);
			container.addEventListener('drop', handleDrop);

			editor.getBody().addEventListener('dragover', handleDragOver);
			editor.getBody().addEventListener('drop', hideCover);
		};

		base.signalPasteHtml = function (paste) {
			if (!('handlePaste' in opts) || opts.handlePaste) {
				var div = document.createElement('div');
				div.innerHTML = paste.val;

				var images = div.querySelectorAll('img');
				for (var i = 0; i < images.length; i++) {
					var image = images[i];

					if (base64DataUri.test(image.src)) {
						var file = base64DataUriToBlob(image.src);
						if (file && isAllowed(file)) {
							handleFile(file, createHolder(image));
						} else {
							image.parentNode.removeChild(image);
						}
					}
				}

				paste.val = div.innerHTML;
			}
		};
	};
})(sceditor);
