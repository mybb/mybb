/**
 * SCEditor Paragraph Formatting Plugin
 * http://www.sceditor.com/
 *
 * Copyright (C) 2011-2013, Sam Clarke (samclarke.com)
 *
 * SCEditor is licensed under the MIT license:
 *	http://www.opensource.org/licenses/mit-license.php
 *
 * @fileoverview SCEditor Paragraph Formatting Plugin
 * @author Sam Clarke
 * @requires jQuery
 */

// ==ClosureCompiler==
// @output_file_name format.min.js
// @compilation_level SIMPLE_OPTIMIZATIONS
// ==/ClosureCompiler==

/*jshint smarttabs: true, scripturl: true, jquery: true, devel:true, eqnull:true, curly: false */

(function($) {
	'use strict';

	$.sceditor.plugins.format = function() {
		var base = this;

		/**
		 * Default tags
		 * @type {Object}
		 * @private
		 */
		var tags = {
			p: 'Paragraph',
			h1: 'Heading 1',
			h2: 'Heading 2',
			h3: 'Heading 3',
			h4: 'Heading 4',
			h5: 'Heading 5',
			h6: 'Heading 6',
			address: 'Address',
			pre: 'Preformatted Text'
		};

		/**
		 * Private functions
		 * @private
		 */
		var	insertTag,
			formatCmd;


		base.init = function() {
			var	opts  = this.opts,
				pOpts = opts.paragraphformat;

			// Don't enable if the BBCode plugin is enabled.
			if($.sceditor.plugins.bbcode && opts.plugins && opts.plugins.indexOf('bbcode') > -1)
				return;

			if(pOpts)
			{
				if(pOpts.tags)
					tags = pOpts.tags;

				if(pOpts.excludeTags)
				{
					$.each(pOpts.excludeTags, function(idx, val) {
						delete tags[val];
					});
				}
			}

			if(!this.commands.format)
			{
				this.commands.format = {
					exec: formatCmd,
					txtExec: formatCmd,
					tooltip: "Format Paragraph"
				};
			}

			if(opts.toolbar === $.sceditor.defaultOptions.toolbar)
				opts.toolbar = opts.toolbar.replace(',color,', ',color,format,');
		};

		/**
		 * Inserts the specified tag into the editor
		 * @param  {sceditor} editor
		 * @param  {string} tag
		 * @private
		 */
		insertTag = function(editor, tag) {
			if(editor.sourceMode())
				editor.insert('<' + tag + '>', '</' + tag + '>');
			else
				editor.execCommand('formatblock', '<' + tag + '>');

		};

		/**
		 * Function for the exec and txtExec properties
		 * @param  {node} caller
		 * @private
		 */
		formatCmd = function(caller) {
			var	editor   = this,
				$content = $("<div />");

			$.each(tags, function(tag, val) {
				$('<a class="sceditor-option" href="#">' + (val.name || val) + '</a>')
					.click(function() {
						editor.closeDropDown(true);

						if(val.exec)
							val.exec(editor);
						else
							insertTag(editor, tag);

						return false;
					})
					.appendTo($content);
			});

			editor.createDropDown(caller, "format", $content);
		};
	};
})(jQuery);