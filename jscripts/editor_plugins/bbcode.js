/**
 * SCEditor BBCode Plugin
 * http://www.sceditor.com/
 *
 * Copyright (C) 2011-2013, Sam Clarke (samclarke.com)
 *
 * SCEditor is licensed under the MIT license:
 *	http://www.opensource.org/licenses/mit-license.php
 *
 * @fileoverview SCEditor BBCode Plugin
 * @author Sam Clarke
 * @requires jQuery
 */

// ==ClosureCompiler==
// @output_file_name bbcode.min.js
// @compilation_level SIMPLE_OPTIMIZATIONS
// ==/ClosureCompiler==

/*jshint smarttabs: true, jquery: true, eqnull:true, curly: false */
/*global prompt: true*/

(function($, window, document) {
	'use strict';

	/**
	 * SCEditor BBCode parser class
	 *
	 * @param {Object} options
	 * @class BBCodeParser
	 * @name jQuery.sceditor.BBCodeParser
	 * @since v1.4.0
	 */
	$.sceditor.BBCodeParser = function(options) {
		// make sure this is not being called as a function
		if(!(this instanceof $.sceditor.BBCodeParser))
			return new $.sceditor.BBCodeParser(options);

		var base = this;

		// Private methods
		var	init,
			tokenizeTag,
			tokenizeAttrs,
			parseTokens,
			normaliseNewLines,
			fixNesting,
			isChildAllowed,
			removeEmpty,
			fixChildren,
			convertToHTML,
			convertToBBCode,
			hasTag,
			quote,
			lower,
			last;

		/**
		 * Enum of valid token types
		 * @type {Object}
		 * @private
		 */
		var tokenType = {
			open:    'open',
			content: 'content',
			newline: 'newline',
			close:   'close'
		};

		/**
		 * Tokenize token class
		 *
		 * @param  {String} type The type of token this is, should be one of tokenType
		 * @param  {String} name The name of this token
		 * @param  {String} val The originally matched string
		 * @param  {Array} attrs Any attributes. Only set on tokenType.open tokens
		 * @param  {Array} children Any children of this token
		 * @param  {TokenizeToken} closing This tokens closing tag. Only set on tokenType.open tokens
		 * @class TokenizeToken
		 * @name TokenizeToken
		 * @memberOf jQuery.sceditor.BBCodeParser.prototype
		 */
		var TokenizeToken = function(type, name, val, attrs, children, closing) {
			var base      = this;
			base.type     = type;
			base.name     = name;
			base.val      = val;
			base.attrs    = attrs || {};
			base.children = children || [];
			base.closing  = closing || null;
		};

		// Declaring methods via prototype instead of in the constructor
		// to reduce memory usage as there could be a lot or these
		// objects created.
		TokenizeToken.prototype = {
			/** @lends jQuery.sceditor.BBCodeParser.prototype.TokenizeToken */
			/**
			 * Clones this token
			 * @param  {Bool} includeChildren If to include the children in the clone. Defaults to false.
			 * @return {TokenizeToken}
			 */
			clone: function(includeChildren) {
				var base = this;
				return new TokenizeToken(
					base.type,
					base.name,
					base.val,
					base.attrs,
					includeChildren ? base.children : [],
					base.closing ? base.closing.clone() : null
				);
			},
			/**
			 * Splits this token at the specified child
			 * @param  {TokenizeToken|Int} splitAt The child to split at or the index of the child
			 * @return {TokenizeToken} The right half of the split token or null if failed
			 */
			splitAt: function(splitAt) {
				var	clone,
					base          = this,
					splitAtLength = 0,
					childrenLen   = base.children.length;

				if(typeof object !== 'number')
					splitAt = $.inArray(splitAt, base.children);

				if(splitAt < 0 || splitAt > childrenLen)
					return null;

				// Work out how many items are on the right side of the split
				// to pass to splice()
				while(childrenLen--)
				{
					if(childrenLen >= splitAt)
						splitAtLength++;
					else
						childrenLen = 0;
				}

				clone          = base.clone();
				clone.children = base.children.splice(splitAt, splitAtLength);
				return clone;
			}
		};


		init = function() {
			base.opts    = $.extend({}, $.sceditor.BBCodeParser.defaults, options);
			base.bbcodes = $.sceditor.plugins.bbcode.bbcodes;
		};

		/**
		 * Takes a BBCode string and splits it into open, content and close tags.
		 *
		 * It does no checking to verify a tag has a matching open or closing tag
		 * or if the tag is valid child of any tag before it. For that the tokens
		 * should be passed to the parse function.
		 *
		 * @param {String} str
		 * @return {Array}
		 * @memberOf jQuery.sceditor.BBCodeParser.prototype
		 */
		base.tokenize = function(str) {
			var	matches, type, i,
				toks   = [],
				tokens = [
					// Close must come before open as they are
					// the same except close has a / at the start.
					{
						type: 'close',
						regex: /^\[\/[^\[\]]+\]/
					},
					{
						type: 'open',
						regex: /^\[[^\[\]]+\]/
					},
					{
						type: 'newline',
						regex: /^(\r\n|\r|\n)/
					},
					{
						type: 'content',
						regex: /^([^\[\r\n]+|\[)/
					}
				];

			tokens.reverse();

			strloop:
			while(str.length)
			{
				i = tokens.length;
				while(i--)
				{
					type = tokens[i].type;

					// Check if the string matches any of the tokens
					if(!(matches = str.match(tokens[i].regex)) || !matches[0])
						continue;

					// Add the match to the tokens list
					toks.push(tokenizeTag(type, matches[0]));

					// Remove the match from the string
					str = str.substr(matches[0].length);

					// The token has been added so start again
					continue strloop;
				}

				// If there is anything left in the string which doesn't match
				// any of the tokens then just assume it's content and add it.
				if(str.length)
					toks.push(tokenizeTag(tokenType.content, str));

				str = '';
			}

			return toks;
		};

		/**
		 * Extracts the name an params from a tag
		 *
		 * @param {Object} token
		 * @return {Object}
		 * @private
		 */
		tokenizeTag = function(type, val) {
			var matches, attrs, name;

			// Extract the name and attributes from opening tags and
			// just the name from closing tags.
			if(type === 'open' && (matches = val.match(/\[([^\]\s=]+)(?:([^\]]+))?\]/)))
			{
				name = lower(matches[1]);

				if(matches[2] && (matches[2] = $.trim(matches[2])))
					attrs = tokenizeAttrs(matches[2]);
			}
			else if(type === 'close' && (matches = val.match(/\[\/([^\[\]]+)\]/)))
				name = lower(matches[1]);
			else if(type === 'newline')
				name = '#newline';

			// Treat all tokens without a name and all unknown BBCodes as content
			if(!name || (type === 'open' || type === 'close') && !$.sceditor.plugins.bbcode.bbcodes[name])
			{
				type = 'content';
				name = '#';
			}

			return new TokenizeToken(type, name, val, attrs);
		};

		/**
		 * Extracts the individual attributes from a string containing
		 * all the attributes.
		 *
		 * @param {String} attrs
		 * @return {Array} Assoc array of attributes
		 * @private
		 */
		tokenizeAttrs = function(attrs) {
			var	matches,
				/*
				([^\s=]+)					Anything that's not a space or equals
				=						Equals =
				(?:
					(?:
						(["'])				The opening quote
						(
							(?:\\\2|[^\2])*?	Anything that isn't the unescaped opening quote
						)
						\2				The opening quote again which will now close the string
					)
						|				If not a quoted string then match
					(
						(?:.(?!\s\S+=))*.?		Anything that isn't part of [space][non-space][=] which would be a new attribute
					)
				)
				*/
				atribsRegex = /([^\s=]+)=(?:(?:(["'])((?:\\\2|[^\2])*?)\2)|((?:.(?!\s\S+=))*.))/g,
				unquote     = $.sceditor.plugins.bbcode.stripQuotes,
				ret         = {};

			// if only one attribute then remove the = from the start and strip any quotes
			if(attrs.charAt(0) === '=' && attrs.indexOf('=', 1) < 0)
				ret.defaultattr = unquote(attrs.substr(1));
			else
			{
				if(attrs.charAt(0) === '=')
					attrs = 'defaultattr' + attrs;

				// No need to strip quotes here, the regex will do that.
				while((matches = atribsRegex.exec(attrs)))
					ret[lower(matches[1])] = unquote(matches[3]) || matches[4];
			}

			return ret;
		};

		/**
		 * Parses a string into an array of BBCodes
		 *
		 * @param {String} str
		 * @param {Bool} preserveNewLines If to preserve all new lines, not strip any based on the passed formatting options
		 * @return {Array} Array of BBCode objects
		 * @memberOf jQuery.sceditor.BBCodeParser.prototype
		 */
		base.parse = function(str, preserveNewLines) {
			var ret = parseTokens(base.tokenize(str));

			if(base.opts.fixInvalidChildren)
				fixChildren(ret);

			if(base.opts.removeEmptyTags)
				removeEmpty(ret);

			if(base.opts.fixInvalidNesting)
				fixNesting(ret);

			normaliseNewLines(ret, null, preserveNewLines);

			if(base.opts.removeEmptyTags)
				removeEmpty(ret);

			return ret;
		};

		/**
		 * Checks if an array of TokenizeToken's contains the
		 * specified token.
		 *
		 * Checks the tokens name and type match another tokens
		 * name and type in the array.
		 *
		 * @param  {string}    name
		 * @param  {tokenType} type
		 * @param  {Array}     arr
		 * @return {Boolean}
		 * @private
		 */
		hasTag = function(name, type, arr) {
			var i = arr.length;

			while(i--)
				if(arr[i].type === type && arr[i].name === name)
					return true;

			return false;
		};

		/**
		 * Checks if the child tag is allowed as one
		 * of the parent tags children.
		 *
		 * @param  {TokenizeToken}  parent
		 * @param  {TokenizeToken}  child
		 * @return {Boolean}
		 * @private
		 */
		isChildAllowed = function(parent, child) {
			var	bbcode          = parent ? base.bbcodes[parent.name] : null,
				allowedChildren = bbcode ? bbcode.allowedChildren : null;

			if(!base.opts.fixInvalidChildren || !allowedChildren)
				return true;

			if(allowedChildren && $.inArray(child.name || '#', allowedChildren) < 0)
				return false;

			return true;
		};

// TODO: Tidy this parseTokens() function up a bit.
		/**
		 * Parses an array of tokens created by tokenize()
		 *
		 * @param  {Array} toks
		 * @return {Array} Parsed tokens
		 * @see tokenize()
		 * @private
		 */
		parseTokens = function(toks) {
			var	token, bbcode, curTok, clone, i, previous, next,
				cloned     = [],
				output     = [],
				openTags   = [],
				/**
				 * Returns the currently open tag or undefined
				 * @return {TokenizeToken}
				 */
				currentOpenTag = function() {
					return last(openTags);
				},
				/**
				 * Adds a tag to either the current tags children
				 * or to the output array.
				 * @param {TokenizeToken} token
				 * @private
				 */
				addTag = function(token) {
					if(currentOpenTag())
						currentOpenTag().children.push(token);
					else
						output.push(token);
				},
				/**
				 * Checks if this tag closes the current tag
				 * @param  {String} name
				 * @return {Void}
				 */
				closesCurrentTag = function(name) {
					return currentOpenTag() &&
						(bbcode = base.bbcodes[currentOpenTag().name]) &&
						bbcode.closedBy &&
						$.inArray(name, bbcode.closedBy) > -1;
				};

			while((token = toks.shift()))
			{
				next = toks[0];

				switch(token.type)
				{
					case tokenType.open:
						// Check it this closes a parent, i.e. for lists [*]one [*]two
						if(closesCurrentTag(token.name))
							openTags.pop();

						addTag(token);
						bbcode = base.bbcodes[token.name];

						// If this tag is not self closing and it has a closing tag then it is open and has children so
						// add it to the list of open tags. If has the closedBy property then it is closed by other tags
						// so include everything as it's children until one of those tags is reached.
						if((!bbcode || !bbcode.isSelfClosing) && (bbcode.closedBy || hasTag(token.name, tokenType.close, toks)))
							openTags.push(token);
						else if(!bbcode || !bbcode.isSelfClosing)
							token.type = tokenType.content;
						break;

					case tokenType.close:
						// check if this closes the current tag, e.g. [/list] would close an open [*]
						if(currentOpenTag() && token.name !== currentOpenTag().name && closesCurrentTag('/' + token.name))
							openTags.pop();

						// If this is closing the currently open tag just pop the close
						// tag off the open tags array
						if(currentOpenTag() && token.name === currentOpenTag().name)
						{
							currentOpenTag().closing = token;
							openTags.pop();
						}
						// If this is closing an open tag that is the parent of the current
						// tag then clone all the tags including the current one until
						// reaching the parent that is being closed. Close the parent and then
						// add the clones back in.
						else if(hasTag(token.name, tokenType.open, openTags))
						{
							// Remove the tag from the open tags
							while((curTok = openTags.pop()))
							{
								// If it's the tag that is being closed then
								// discard it and break the loop.
								if(curTok.name === token.name)
								{
									curTok.closing = token;
									break;
								}

								// Otherwise clone this tag and then add any
								// previously cloned tags as it's children
								clone = curTok.clone();

								if(cloned.length > 1)
									clone.children.push(last(cloned));

								cloned.push(clone);
							}

							// Add the last cloned child to the now current tag
							// (the parent of the tag which was being closed)
							addTag(last(cloned));

							// Add all the cloned tags to the open tags list
							i = cloned.length;
							while(i--)
								openTags.push(cloned[i]);

							cloned.length = 0;
						}
						// This tag is closing nothing so treat it as content
						else
						{
							token.type = tokenType.content;
							addTag(token);
						}
						break;

					case tokenType.newline:
						// handle things like
						//     [*]list\nitem\n[*]list1
						// where it should come out as
						//     [*]list\nitem[/*]\n[*]list1[/*]
						// instead of
						//     [*]list\nitem\n[/*][*]list1[/*]
						if(currentOpenTag() && next && closesCurrentTag((next.type === tokenType.close ? '/' : '') + next.name))
						{
							// skip if the next tag is the closing tag for the option tag, i.e. [/*]
							if(!(next.type === tokenType.close && next.name === currentOpenTag().name))
							{
								bbcode = base.bbcodes[currentOpenTag().name];

								if(bbcode && bbcode.breakAfter)
									openTags.pop();
								else if(bbcode && bbcode.isInline === false && base.opts.breakAfterBlock && bbcode.breakAfter !== false)
									openTags.pop();
							}
						}

						addTag(token);
						break;

					default: // content
						addTag(token);
						break;
				}

				previous = token;
			}

			return output;
		};

		/**
		 * Normalise all new lines
		 *
		 * Removes any formatting new lines from the BBCode
		 * leaving only content ones. I.e. for a list:
		 *
		 * [list]
		 * [*] list item one
		 * with a line break
		 * [*] list item two
		 * [/list]
		 *
		 * would become
		 *
		 * [list] [*] list item one
		 * with a line break [*] list item two [/list]
		 *
		 * Which makes it easier to convert to HTML or add
		 * the formatting new lines back in when converting
		 * back to BBCode
		 *
		 * @param  {Array} children
		 * @param  {TokenizeToken} parent
		 * @param  {Bool} onlyRemoveBreakAfter
		 * @return {void}
		 */
		normaliseNewLines = function(children, parent, onlyRemoveBreakAfter) {
			var	token, left, right, parentBBCode, bbcode,
				removedBreakEnd, removedBreakBefore, remove,
				childrenLength = children.length,
				i              = childrenLength;

			if(parent)
				parentBBCode = base.bbcodes[parent.name];

			while(i--)
			{
				if(!(token = children[i]))
					continue;

				if(token.type === tokenType.newline)
				{
					left   = i > 0 ? children[i - 1] : null;
					right  = i < childrenLength - 1 ? children[i+1] : null;
					remove = false;

					// Handle the start and end new lines e.g. [tag]\n and \n[/tag]
					if(!onlyRemoveBreakAfter && parentBBCode && parentBBCode.isSelfClosing !== true)
					{
						// First child of parent so must be opening line break (breakStartBlock, breakStart) e.g. [tag]\n
						if(!left)
						{
							if(parentBBCode.isInline === false && base.opts.breakStartBlock && parentBBCode.breakStart !== false)
								remove = true;

							if(parentBBCode.breakStart)
								remove = true;
						}
						// Last child of parent so must be end line break (breakEndBlock, breakEnd) e.g. \n[/tag]
						// remove last line break (breakEndBlock, breakEnd)
						else if (!removedBreakEnd && !right)
						{
							if(parentBBCode.isInline === false && base.opts.breakEndBlock && parentBBCode.breakEnd !== false)
								remove = true;

							if(parentBBCode.breakEnd)
								remove = true;

							removedBreakEnd = remove;
						}
					}

					if(left && left.type === tokenType.open)
					{
						if((bbcode = base.bbcodes[left.name]))
						{
							if(!onlyRemoveBreakAfter)
							{
								if(bbcode.isInline === false && base.opts.breakAfterBlock && bbcode.breakAfter !== false)
									remove = true;

								if(bbcode.breakAfter)
									remove = true;
							}
							else if(bbcode.isInline === false)
								remove = true;
						}
					}

					if(!onlyRemoveBreakAfter && !removedBreakBefore && right && right.type === tokenType.open)
					{
						if((bbcode = base.bbcodes[right.name]))
						{
							if(bbcode.isInline === false && base.opts.breakBeforeBlock && bbcode.breakBefore !== false)
								remove = true;

							if(bbcode.breakBefore)
								remove = true;

							removedBreakBefore = remove;

							if(remove)
							{
								children.splice(i, 1);
								continue;
							}
						}
					}

					if(remove)
						children.splice(i, 1);

					// reset double removedBreakBefore removal protection.
					// This is needed for cases like \n\n[\tag] where
					// only 1 \n should be removed but without this they both
					// would be.
					removedBreakBefore = false;
				}
				else if(token.type === tokenType.open)
					normaliseNewLines(token.children, token, onlyRemoveBreakAfter);
			}
		};

		/**
		 * Fixes any invalid nesting.
		 *
		 * If it is a block level element inside 1 or more inline elements
		 * then those inline elements will be split at the point where the
		 * block level is and the block level element placed between the split
		 * parts. i.e.
		 *     [inline]textA[blocklevel]textB[/blocklevel]textC[/inline]
		 * Will become:
		 *     [inline]textA[/inline][blocklevel]textB[/blocklevel][inline]textC[/inline]
		 *
		 * @param {Array} children
		 * @param {Array} [parents] Null if there is no parents
		 * @param {Array} [insideInline] Boolean, if inside an inline element
		 * @param {Array} [rootArr] Root array if there is one
		 * @return {Array}
		 * @private
		 */
		fixNesting = function(children, parents, insideInline, rootArr) {
			var	token, i, parent, parentIndex, parentParentChildren, right,
				isInline = function(token) {
					var bbcode = base.bbcodes[token.name];

					return !bbcode || bbcode.isInline !== false;
				};

			parents = parents || [];
			rootArr = rootArr || children;

			// this must check length each time as the length
			// can change as tokens are moved around to fix the nesting.
			for(i=0; i<children.length; i++)
			{
				if(!(token = children[i]) || token.type !== tokenType.open)
					continue;

				if(!isInline(token) && insideInline)
				{
					// if this is a blocklevel element inside an inline one then split
					// the parent at the block level element
					parent               = last(parents);
					right                = parent.splitAt(token);
					parentParentChildren = parents.length > 1 ? parents[parents.length - 2].children : rootArr;

					if((parentIndex = $.inArray(parent, parentParentChildren)) > -1)
					{
						// remove the block level token from the right side of the split
						// inline element
						right.children.splice($.inArray(token, right.children), 1);

						// insert the block level token and the right side after the left
						// side of the inline token
						parentParentChildren.splice(parentIndex+1, 0, token, right);

						// return to parents loop as the children have now increased
						return;
					}

				}

				parents.push(token);
				fixNesting(token.children, parents, insideInline || isInline(token), rootArr);
				parents.pop(token);
			}
		};

		/**
		 * Fixes any invalid children.
		 *
		 * If it is an element which isn't allowed as a child of it's parent
		 * then it will be converted to content of the parent element. i.e.
		 *     [code]Code [b]only[/b] allows text.[/code]
		 * Will become:
		 *     <code>Code [b]only[/b] allows text.</code>
		 * Instead of:
		 *     <code>Code <b>only</b> allows text.</code>
		 *
		 * @param {Array} children
		 * @param {Array} [parent] Null if there is no parents
		 * @private
		 */
		fixChildren = function(children, parent) {
			var	token, args,
				i = children.length;

			while(i--)
			{
				if(!(token = children[i]))
					continue;

				if(!isChildAllowed(parent, token))
				{
					// if it is not then convert it to text and see if it
					// is allowed
					token.name = null;
					token.type = tokenType.content;

					if(isChildAllowed(parent, token))
					{
						args = [i+1, 0].concat(token.children);

						if(token.closing)
						{
							token.closing.name = null;
							token.closing.type = tokenType.content;
							args.push(token.closing);
						}

						i += args.length - 1;
						Array.prototype.splice.apply(children, args);
					}
					else
						parent.children.splice(i, 1);
				}

				if(token.type === tokenType.open)
					fixChildren(token.children, token);
			}
		};

		/**
		 * Removes any empty BBCodes which are not allowed to be empty.
		 *
		 * @param {Array} tokens
		 * @private
		 */
		removeEmpty = function(tokens) {
			var	token, bbcode, isTokenWhiteSpace,
				i = tokens.length;

			/**
			 * Checks if all children are whitespace or not
			 * @private
			 */
			isTokenWhiteSpace = function(children) {
				var j = children.length;

				while(j--)
				{
					if(children[j].type === tokenType.open)
						return false;

					if(children[j].type === tokenType.close)
						return false;

					if(children[j].type === tokenType.content && children[j].val && /\S|\u00A0/.test(children[j].val))
						return false;
				}

				return true;
			};

			while(i--)
			{
				// only tags can be empty, content can't be empty. So skip anything that isn't a tag.
				if(!(token = tokens[i]) || token.type !== tokenType.open)
					continue;

				bbcode = base.bbcodes[token.name];

				// remove any empty children of this tag first so that if they are all
				// removed this one doesn't think it's not empty.
				removeEmpty(token.children);

				if(isTokenWhiteSpace(token.children) && bbcode && !bbcode.isSelfClosing && !bbcode.allowsEmpty)
					tokens.splice.apply(tokens, $.merge([i, 1], token.children));
			}
		};

		/**
		 * Converts a BBCode string to HTML
		 * @param {String} str
		 * @param {Bool}   preserveNewLines If to preserve all new lines, not strip any based on the passed formatting options
		 * @return {String}
		 * @memberOf jQuery.sceditor.BBCodeParser.prototype
		 */
		base.toHTML = function(str, preserveNewLines) {
			return convertToHTML(base.parse(str, preserveNewLines), true);
		};

		/**
		 * @private
		 */
		convertToHTML = function(tokens, isRoot) {
			var	token, bbcode, content, html, needsBlockWrap, blockWrapOpen,
				isInline, lastChild,
				ret = [];

			isInline = function(bbcode) {
				return (!bbcode || (typeof bbcode.isHtmlInline !== 'undefined' ? bbcode.isHtmlInline : bbcode.isInline)) !== false;
			};

			while(tokens.length > 0)
			{
				if(!(token = tokens.shift()))
					continue;

				if(token.type === tokenType.open)
				{
					lastChild      = token.children[token.children.length - 1] || {};
					bbcode         = base.bbcodes[token.name];
					needsBlockWrap = isRoot && isInline(bbcode);
					content        = convertToHTML(token.children, false);

					if(bbcode && bbcode.html)
					{
						// Only add a line break to the end if this is blocklevel and the last child wasn't block-level
						if(!isInline(bbcode) && isInline(base.bbcodes[lastChild.name]) && !bbcode.isPreFormatted && !bbcode.skipLastLineBreak)
						{
							// Add placeholder br to end of block level elements in all browsers apart from IE < 9 which
							// handle new lines differently and doesn't need one.
							if(!$.sceditor.ie)
								content += '<br />';
						}

						if($.isFunction(bbcode.html))
							html = bbcode.html.call(base, token, token.attrs, content);
						else
							html = $.sceditor.plugins.bbcode.formatString(bbcode.html, content);
					}
					else
						html = token.val + content + (token.closing ? token.closing.val : '');
				}
				else if(token.type === tokenType.newline)
				{
					if(!isRoot)
					{
						ret.push('<br />');
						continue;
					}

					// If not already in a block wrap then start a new block
					if(!blockWrapOpen)
					{
						ret.push('<div>');

						// If it's an empty DIV and compatibility mode is below IE8 then
						// we must add a non-breaking space to the div otherwise the div
						// will be collapsed. Adding a BR works but when you press enter
						// to make a newline it suddenly goes back to the normal IE div
						// behaviour and creates two lines, one for the newline and one
						// for the BR. I'm sure there must be a better fix but I've yet to
						// find one.
						// Cannot do zoom: 1; or set a height on the div to fix it as that
						// causes resize handles to be added to the div when it's clicked on/
						if((document.documentMode && document.documentMode < 8) || $.sceditor.ie < 8)
							ret.push('\u00a0');
					}

					// Putting BR in a div in IE causes it to do a double line break.
					if(!$.sceditor.ie)
						ret.push('<br />');

					// Normally the div acts as a line-break with by moving whatever comes
					// after onto a new line.
					// If this is the last token, add an extra line-break so it shows as
					// there will be nothing after it.
					if(!tokens.length)
						ret.push('<br />');

					ret.push('</div>\n');
					blockWrapOpen = false;
					continue;
				}
				else // content
				{
					needsBlockWrap = isRoot;
					html           = $.sceditor.escapeEntities(token.val);
				}

				if(needsBlockWrap && !blockWrapOpen)
				{
					ret.push('<div>');
					blockWrapOpen = true;
				}
				else if(!needsBlockWrap && blockWrapOpen)
				{
					ret.push('</div>\n');
					blockWrapOpen = false;
				}

				ret.push(html);
			}

			if(blockWrapOpen)
				ret.push('</div>\n');

			return ret.join('');
		};

		/**
		 * Takes a BBCode string, parses it then converts it back to BBCode.
		 *
		 * This will auto fix the BBCode and format it with the specified options.
		 *
		 * @param {String} str
		 * @param {Bool} preserveNewLines If to preserve all new lines, not strip any based on the passed formatting options
		 * @return {String}
		 * @memberOf jQuery.sceditor.BBCodeParser.prototype
		 */
		base.toBBCode = function(str, preserveNewLines) {
			return convertToBBCode(base.parse(str, preserveNewLines));
		};

		/**
		 * Converts parsed tokens back into BBCode with the
		 * formatting specified in the options and with any
		 * fixes specified.
		 *
		 * @param  {Array} toks Array of parsed tokens from base.parse()
		 * @return {String}
		 * @private
		 */
		convertToBBCode = function(toks) {
			var	token, attr, bbcode, isBlock, isSelfClosing, quoteType,
				breakBefore, breakStart, breakEnd, breakAfter,
				// Create an array of strings which are joined together
				// before being returned as this is faster in slow browsers.
				// (Old versions of IE).
				ret = [];

			while(toks.length > 0)
			{
				if(!(token = toks.shift()))
					continue;

				bbcode        = base.bbcodes[token.name];
				isBlock       = !(!bbcode || bbcode.isInline !== false);
				isSelfClosing = bbcode && bbcode.isSelfClosing;
				breakBefore   = ((isBlock && base.opts.breakBeforeBlock && bbcode.breakBefore !== false) || (bbcode && bbcode.breakBefore));
				breakStart    = ((isBlock && !isSelfClosing && base.opts.breakStartBlock && bbcode.breakStart !== false) || (bbcode && bbcode.breakStart));
				breakEnd      = ((isBlock && base.opts.breakEndBlock && bbcode.breakEnd !== false) || (bbcode && bbcode.breakEnd));
				breakAfter    = ((isBlock && base.opts.breakAfterBlock && bbcode.breakAfter !== false) || (bbcode && bbcode.breakAfter));
				quoteType     = (bbcode ? bbcode.quoteType : null) || base.opts.quoteType || $.sceditor.BBCodeParser.QuoteType.auto;

				if(!bbcode && token.type === tokenType.open)
				{
					ret.push(token.val);

					if(token.children)
						ret.push(convertToBBCode(token.children));

					if(token.closing)
						ret.push(token.closing.val);
				}
				else if(token.type === tokenType.open)
				{
					if(breakBefore)
						ret.push('\n');

					// Convert the tag and it's attributes to BBCode
					ret.push('[' + token.name);
					if(token.attrs)
					{
						if(token.attrs.defaultattr)
						{
							ret.push('=' + quote(token.attrs.defaultattr, quoteType, 'defaultattr'));
							delete token.attrs.defaultattr;
						}

						for(attr in token.attrs)
							if(token.attrs.hasOwnProperty(attr))
								ret.push(' ' + attr + '=' + quote(token.attrs[attr], quoteType, attr));
					}
					ret.push(']');

					if(breakStart)
						ret.push('\n');

					// Convert the tags children to BBCode
					if(token.children)
						ret.push(convertToBBCode(token.children));

					// add closing tag if not self closing
					if(!isSelfClosing && !bbcode.excludeClosing)
					{
						if(breakEnd)
							ret.push('\n');

						ret.push('[/' + token.name + ']');
					}

					if(breakAfter)
						ret.push('\n');

					// preserve whatever was recognised as the closing tag if
					// it is a self closing tag
					if(token.closing && isSelfClosing)
						ret.push(token.closing.val);
				}
				else
					ret.push(token.val);
			}

			return ret.join('');
		};

		/**
		 * Quotes an attribute
		 *
		 * @param {String} str
		 * @param {$.sceditor.BBCodeParser.QuoteType} quoteType
		 * @param {String} name
		 * @return {String}
		 * @private
		 */
		quote = function(str, quoteType, name) {
			var	QuoteTypes  = $.sceditor.BBCodeParser.QuoteType,
				needsQuotes = /\s|=/.test(str);

			if($.isFunction(quoteType))
				return quoteType(str, name);

			if(quoteType === QuoteTypes.never || (quoteType === QuoteTypes.auto && !needsQuotes))
				return str;

			return '"' + str.replace('\\', '\\\\').replace('"', '\\"') + '"';
		};

		/**
		 * Returns the last element of an array or null
		 *
		 * @param {Array} arr
		 * @return {Object} Last element
		 * @private
		 */
		last = function(arr) {
			if(arr.length)
				return arr[arr.length - 1];

			return null;
		};

		/**
		 * Converts a string to lowercase.
		 *
		 * @param {String} str
		 * @return {String} Lowercase version of str
		 * @private
		 */
		lower = function(str) {
			return str.toLowerCase();
		};

		init();
	};

	/**
	 * Quote type
	 * @type {Object}
	 * @class QuoteType
	 * @name jQuery.sceditor.BBCodeParser.QuoteType
	 * @since v1.4.0
	 */
	$.sceditor.BBCodeParser.QuoteType = {
		/** @lends jQuery.sceditor.BBCodeParser.QuoteType */
		/**
		 * Always quote the attribute value
		 * @type {Number}
		 */
		always: 1,

		/**
		 * Never quote the attributes value
		 * @type {Number}
		 */
		never: 2,

		/**
		 * Only quote the attributes value when it contains spaces to equals
		 * @type {Number}
		 */
		auto: 3
	};

	/**
	 * Default BBCode parser options
	 * @type {Object}
	 */
	$.sceditor.BBCodeParser.defaults = {
		/**
		 * If to add a new line before block level elements
		 * @type {Boolean}
		 */
		breakBeforeBlock: false,

		/**
		 * If to add a new line after the start of block level elements
		 * @type {Boolean}
		 */
		breakStartBlock: false,

		/**
		 * If to add a new line before the end of block level elements
		 * @type {Boolean}
		 */
		breakEndBlock: false,

		/**
		 * If to add a new line after block level elements
		 * @type {Boolean}
		 */
		breakAfterBlock: true,

		/**
		 * If to remove empty tags
		 * @type {Boolean}
		 */
		removeEmptyTags: true,

		/**
		 * If to fix invalid nesting, i.e. block level elements inside inline elements.
		 * @type {Boolean}
		 */
		fixInvalidNesting: true,

		/**
		 * If to fix invalid children. i.e. A tag which is inside a parent that doesn't allow that type of tag.
		 * @type {Boolean}
		 */
		fixInvalidChildren: true,

		/**
		 * Attribute quote type
		 * @type {$.sceditor.BBCodeParser.QuoteType}
		 * @since 1.4.1
		 */
		quoteType: $.sceditor.BBCodeParser.QuoteType.auto
	};

	/**
	 * Deprecated, use $.sceditor.plugins.bbcode
	 *
	 * @class sceditorBBCodePlugin
	 * @name jQuery.sceditor.sceditorBBCodePlugin
	 * @deprecated
	 */
	$.sceditorBBCodePlugin =
	/**
	 * BBCode plugin for SCEditor
	 *
	 * @class bbcode
	 * @name jQuery.sceditor.plugins.bbcode
	 * @since 1.4.1
	 */
	$.sceditor.plugins.bbcode = function() {
		var base = this;

		/**
		 * Private methods
		 * @private
		 */
		var	buildBbcodeCache,
			handleStyles,
			handleTags,
			formatString,
			getStyle,
			mergeSourceModeCommands,
			removeFirstLastDiv;

		formatString     = $.sceditor.plugins.bbcode.formatString;
		base.bbcodes     = $.sceditor.plugins.bbcode.bbcodes;
		base.stripQuotes = $.sceditor.plugins.bbcode.stripQuotes;

		/**
		 * cache of all the tags pointing to their bbcodes to enable
		 * faster lookup of which bbcode a tag should have
		 * @private
		 */
		var tagsToBbcodes = {};

		/**
		 * Same as tagsToBbcodes but instead of HTML tags it's styles
		 * @private
		 */
		var stylesToBbcodes = {};

		/**
		 * Allowed children of specific HTML tags. Empty array if no
		 * children other than text nodes are allowed
		 * @private
		 */
		var validChildren = {
			ul: ['li', 'ol', 'ul'],
			ol: ['li', 'ol', 'ul'],
			table: ['tr'],
			tr: ['td', 'th'],
			code: ['br', 'p', 'div']
		};

		/**
		 * Cache of CamelCase versions of CSS properties
		 * @type {Object}
		 */
		var propertyCache = {};


		/**
		 * Initializer
		 * @private
		 */
		base.init = function() {
			base.opts = this.opts;

			// build the BBCode cache
			buildBbcodeCache();
			mergeSourceModeCommands(this);

			// Add BBCode helper methods
			this.toBBCode   = base.signalToSource;
			this.fromBBCode = base.signalToWysiwyg;
		};

		mergeSourceModeCommands = function(editor) {
			var getCommand = $.sceditor.command.get;

			var merge = {
				bold: { txtExec: ['[b]', '[/b]'] },
				italic: { txtExec: ['[i]', '[/i]'] },
				underline: { txtExec: ['[u]', '[/u]'] },
				strike: { txtExec: ['[s]', '[/s]'] },
				subscript: { txtExec: ['[sub]', '[/sub]'] },
				superscript: { txtExec: ['[sup]', '[/sup]'] },
				left: { txtExec: ['[left]', '[/left]'] },
				center: { txtExec: ['[center]', '[/center]'] },
				right: { txtExec: ['[right]', '[/right]'] },
				justify: { txtExec: ['[justify]', '[/justify]'] },
				font: {
					txtExec: function(caller) {
						var editor = this;

						getCommand('font')._dropDown(
							editor,
							caller,
							function(fontName) {
								editor.insertText('[font='+fontName+']', '[/font]');
							}
						);
					}
				},
				size: {
					txtExec: function(caller) {
						var editor = this;

						getCommand('size')._dropDown(
							editor,
							caller,
							function(fontSize) {
								editor.insertText('[size='+fontSize+']', '[/size]');
							}
						);
					}
				},
				color: {
					txtExec: function(caller) {
						var editor = this;

						getCommand('color')._dropDown(
							editor,
							caller,
							function(color) {
								editor.insertText('[color='+color+']', '[/color]');
							}
						);
					}
				},
				bulletlist: {
					txtExec: function(caller, selected) {
						var content = '';

						$.each(selected.split(/\r?\n/), function() {
							content += (content ? '\n' : '') + '[li]' + this + '[/li]';
						});

						editor.insertText('[ul]\n' + content + '\n[/ul]');
					}
				},
				orderedlist: {
					txtExec: function(caller, selected) {
						var content = '';

						$.each(selected.split(/\r?\n/), function() {
							content += (content ? '\n' : '') + '[li]' + this + '[/li]';
						});

						$.sceditor.plugins.bbcode.bbcode.get('');

						editor.insertText('[ol]\n' + content + '\n[/ol]');
					}
				},
				table: { txtExec: ['[table][tr][td]', '[/td][/tr][/table]'] },
				horizontalrule: { txtExec: ['[hr]'] },
				code: { txtExec: ['[code]', '[/code]'] },
				image: {
					txtExec: function(caller, selected) {
						var url = prompt(this._('Enter the image URL:'), selected);

						if(url)
							this.insertText('[img]' + url + '[/img]');
					}
				},
				email: {
					txtExec: function(caller, selected) {
						var	display = selected && selected.indexOf('@') > -1 ? null : selected,
							email	= prompt(this._('Enter the e-mail address:'), (display ? '' : selected)),
							text	= prompt(this._('Enter the displayed text:'), display || email) || email;

						if(email)
							this.insertText('[email=' + email + ']' + text + '[/email]');
					}
				},
				link: {
					txtExec: function(caller, selected) {
						var	display = selected && selected.indexOf('http://') > -1 ? null : selected,
							url	= prompt(this._('Enter URL:'), (display ? 'http://' : selected)),
							text	= prompt(this._('Enter the displayed text:'), display || url) || url;

						if(url)
							this.insertText('[url=' + url + ']' + text + '[/url]');
					}
				},
				quote: { txtExec: ['[quote]', '[/quote]'] },
				youtube: {
					txtExec: function(caller) {
						var editor = this;

						getCommand('youtube')._dropDown(
							editor,
							caller,
							function(id) {
								editor.insertText('[youtube]' + id + '[/youtube]');
							}
						);
					}
				},
				rtl: { txtExec: ['[rtl]', '[/rtl]'] },
				ltr: { txtExec: ['[ltr]', '[/ltr]'] }
			};

			editor.commands = $.extend(true, {}, merge, editor.commands);
		};

		/**
		 * Populates tagsToBbcodes and stylesToBbcodes to enable faster lookups
		 *
		 * @private
		 */
		buildBbcodeCache = function() {
			$.each(base.bbcodes, function(bbcode) {
				if(base.bbcodes[bbcode].tags)
					$.each(base.bbcodes[bbcode].tags, function(tag, values) {
						var isBlock = base.bbcodes[bbcode].isInline === false;
						tagsToBbcodes[tag] = (tagsToBbcodes[tag] || {});
						tagsToBbcodes[tag][isBlock] = (tagsToBbcodes[tag][isBlock] || {});
						tagsToBbcodes[tag][isBlock][bbcode] = values;
					});

				if(base.bbcodes[bbcode].styles)
					$.each(base.bbcodes[bbcode].styles, function(style, values) {
						var isBlock = base.bbcodes[bbcode].isInline === false;
						stylesToBbcodes[isBlock] = (stylesToBbcodes[isBlock] || {});
						stylesToBbcodes[isBlock][style] = (stylesToBbcodes[isBlock][style] || {});
						stylesToBbcodes[isBlock][style][bbcode] = values;
					});
			});
		};

		/**
		 * Gets the value of a style property on the passed element
		 * @private
		 */
		getStyle = function(element, property) {
			var	$elm, ret, dir, textAlign, name,
				style = element.style;

			if(!style)
				return null;

			if(!propertyCache[property])
				propertyCache[property] = $.camelCase(property);

			name = propertyCache[property];

			// add exception for align
			if('text-align' === property)
			{
				$elm      = $(element);
				dir       = style.direction;
				textAlign = style[name] || $elm.css(property);

				if($elm.parent().css(property) !== textAlign &&
					$elm.css('display') === 'block' && !$elm.is('hr') && !$elm.is('th'))
					ret = textAlign;

				// IE changes text-align to the same as the current direction so skip unless overridden by user
				if(dir && ret && ((/right/i.test(ret) && dir === 'rtl') || (/left/i.test(ret) && dir === 'ltr')))
					return null;

				return ret;
			}

			return style[name];
		};

		/**
		 * Checks if any bbcode styles match the elements styles
		 *
		 * @return string Content with any matching bbcode tags wrapped around it.
		 * @private
		 */
		handleStyles = function($element, content, blockLevel) {
			var	elementPropVal;

			// convert blockLevel to boolean
			blockLevel = !!blockLevel;

			if(!stylesToBbcodes[blockLevel])
				return content;

			$.each(stylesToBbcodes[blockLevel], function(property, bbcodes) {
				elementPropVal = getStyle($element[0], property);

				// if the parent has the same style use that instead of this one
				// so you don't end up with [i]parent[i]child[/i][/i]
				if(!elementPropVal || getStyle($element.parent()[0], property) === elementPropVal)
					return;

				$.each(bbcodes, function(bbcode, values) {
					if(!values || $.inArray(elementPropVal.toString(), values) > -1)
					{
						if($.isFunction(base.bbcodes[bbcode].format))
							content = base.bbcodes[bbcode].format.call(base, $element, content);
						else
							content = formatString(base.bbcodes[bbcode].format, content);
					}
				});
			});

			return content;
		};

		/**
		 * Handles a HTML tag and finds any matching bbcodes
		 *
		 * @param {jQuery} element The element to convert
		 * @param {String} content The Tags text content
		 * @param {Bool} blockLevel If to convert block level tags
		 * @return {String} Content with any matching bbcode tags wrapped around it.
		 * @private
		 */
		handleTags = function($element, content, blockLevel) {
			var	convertBBCode,
				element = $element[0],
				tag     = element.nodeName.toLowerCase();

			// convert blockLevel to boolean
			blockLevel = !!blockLevel;

			if(tagsToBbcodes[tag] && tagsToBbcodes[tag][blockLevel]) {
				// loop all bbcodes for this tag
				$.each(tagsToBbcodes[tag][blockLevel], function(bbcode, bbcodeAttribs) {
					// if the bbcode requires any attributes then check this has
					// all needed
					if(bbcodeAttribs)
					{
						convertBBCode = false;

						// loop all the bbcode attribs
						$.each(bbcodeAttribs, function(attrib, values) {
							// if the $element has the bbcodes attribute and the bbcode attribute
							// has values check one of the values matches
							if(!$element.attr(attrib) || (values && $.inArray($element.attr(attrib), values) < 0))
								return;

							// break this loop as we have matched this bbcode
							convertBBCode = true;
							return false;
						});

						if(!convertBBCode)
							return;
					}

					if($.isFunction(base.bbcodes[bbcode].format))
						content = base.bbcodes[bbcode].format.call(base, $element, content);
					else
						content = formatString(base.bbcodes[bbcode].format, content);
				});
			}

			if(blockLevel && (!$.sceditor.dom.isInline(element, true) || tag === 'br'))
			{
				var	parent		    = element.parentNode,
					parentLastChild = parent.lastChild,
					previousSibling = element.previousSibling,
					parentIsInline	= $.sceditor.dom.isInline(parent, true);

				// skips selection makers and other ignored items
				while(previousSibling && $(previousSibling).hasClass('sceditor-ignore'))
					previousSibling = previousSibling.previousSibling;

				while($(parentLastChild).hasClass('sceditor-ignore'))
					parentLastChild = parentLastChild.previousSibling;

				// If this is
				//	A br/block element inside an inline element.
				//	The last block level as the last block level is collapsed.
				//	Is an li element.
				//	Is IE and the tag is BR. IE never collapses BR's
				if(parentIsInline || parentLastChild !== element || tag === 'li' || (tag === 'br' && $.sceditor.ie))
					content += '\n';

				// Check for <div>text<div>This needs a newline prepended</div></div>
				if('br' !== tag && previousSibling && previousSibling.nodeName.toLowerCase() !== 'br' && $.sceditor.dom.isInline(previousSibling, true))
					content = '\n' + content;
			}

			return content;
		};

		/**
		 * Converts HTML to BBCode
		 * @param string	html	Html string, this function ignores this, it works off domBody
		 * @param HtmlElement	$body	Editors dom body object to convert
		 * @return string BBCode which has been converted from HTML
		 * @memberOf jQuery.plugins.bbcode.prototype
		 */
		base.signalToSource = function(html, $body) {
			var	$tmpContainer, bbcode,
				parser = new $.sceditor.BBCodeParser(base.opts.parserOptions);

			if(!$body)
			{
				if(typeof html === 'string')
				{
					$tmpContainer = $('<div />').css('visibility', 'hidden').appendTo(document.body).html(html);
					$body = $tmpContainer;
				}
				else
					$body = $(html);
			}

			if(!$body || !$body.jquery)
				return '';

			$.sceditor.dom.removeWhiteSpace($body[0]);
			bbcode = base.elementToBbcode($body);

			if($tmpContainer)
				$tmpContainer.remove();

			bbcode = parser.toBBCode(bbcode, true);

			if(base.opts.bbcodeTrim)
				bbcode = $.trim(bbcode);

			return bbcode;
		};

		/**
		 * Converts a HTML dom element to BBCode starting from
		 * the innermost element and working backwards
		 *
		 * @private
		 * @param HtmlElement	element		The element to convert to BBCode
		 * @param array		vChildren	Valid child tags allowed
		 * @return string BBCode
		 * @memberOf jQuery.plugins.bbcode.prototype
		 */
		base.elementToBbcode = function($element) {
			return (function toBBCode(node, vChildren) {
				var ret = '';
// TODO: Move to BBCode class?
				$.sceditor.dom.traverse(node, function(node) {
					var	$node        = $(node),
						curTag       = '',
						nodeType     = node.nodeType,
						tag          = node.nodeName.toLowerCase(),
						vChild       = validChildren[tag],
						firstChild   = node.firstChild,
						isValidChild = true;

					if(typeof vChildren === 'object')
					{
						isValidChild = $.inArray(tag, vChildren) > -1;

						// Emoticons should always be converted
						if($node.is('img') && $node.data('sceditor-emoticon'))
							isValidChild = true;

						// if this tag is one of the parents allowed children
						// then set this tags allowed children to whatever it allows,
						// otherwise set to what the parent allows
						if(!isValidChild)
							vChild = vChildren;
					}

					// 3 = text and 1 = element
					if(nodeType !== 3 && nodeType !== 1)
						return;

					if(nodeType === 1)
					{
						// skip ignored elements
						if($node.hasClass('sceditor-ignore'))
							return;

						// skip empty nlf elements (new lines automatically added after block level elements like quotes)
						if($node.hasClass('sceditor-nlf'))
						{
							if(!firstChild || (!$.sceditor.ie && node.childNodes.length === 1 && /br/i.test(firstChild.nodeName)))
							{
								return;
							}
						}

						// don't loop inside iframes
						if(tag !== 'iframe')
							curTag = toBBCode(node, vChild);

// TODO: isValidChild is no longer needed. Should use valid children bbcodes instead by
// creating BBCode tokens like the parser.
						if(isValidChild)
						{
							// code tags should skip most styles
							if(tag !== 'code')
							{
								// handle inline bbcodes
								curTag = handleStyles($node, curTag);
								curTag = handleTags($node, curTag);

								// handle blocklevel bbcodes
								curTag = handleStyles($node, curTag, true);
							}

							ret += handleTags($node, curTag, true);
						}
						else
							ret += curTag;
					}
					else if(node.wholeText && (!node.previousSibling || node.previousSibling.nodeType !== 3))
					{
// TODO:This should check for CSS white-space, should pass it in the function to reduce css lookups which are SLOW!
						if($node.parents('code').length === 0)
							ret += node.wholeText.replace(/ +/g, " ");
						else
							ret += node.wholeText;
					}
					else if(!node.wholeText)
						ret += node.nodeValue;
				}, false, true);

				return ret;
			}($element[0]));
		};

		/**
		 * Converts BBCode to HTML
		 *
		 * @param {String} text
		 * @param {Bool} asFragment
		 * @return {String} HTML
		 * @memberOf jQuery.plugins.bbcode.prototype
		 */
		base.signalToWysiwyg = function(text, asFragment) {
			var	parser = new $.sceditor.BBCodeParser(base.opts.parserOptions),
				html   = parser.toHTML(base.opts.bbcodeTrim ? $.trim(text) : text);

			return asFragment ? removeFirstLastDiv(html) : html;
		};

		/**
		 * Removes the first and last divs from the HTML.
		 *
		 * This is needed for pasting
		 * @param  {String} html
		 * @return {String}
		 * @private
		 */
		removeFirstLastDiv = function(html) {
			var	node, next, removeDiv,
				$output = $('<div />').hide().appendTo(document.body),
				output  = $output[0];

			removeDiv = function(node, isFirst) {
				// Don't remove divs that have styling
				if($.sceditor.dom.hasStyling(node))
					return;

				if($.sceditor.ie || (node.childNodes.length !== 1 || !$(node.firstChild).is('br')))
				{
					while((next = node.firstChild))
						output.insertBefore(next, node);
				}

				if(isFirst)
				{
					var lastChild = output.lastChild;

					if(node !== lastChild && $(lastChild).is('div') && node.nextSibling === lastChild)
						output.insertBefore(document.createElement('br'), node);
				}

				output.removeChild(node);
			};

			output.innerHTML = html.replace(/<\/div>\n/g, '</div>');

			if((node = output.firstChild) && $(node).is('div'))
				removeDiv(node, true);

			if((node = output.lastChild) && $(node).is('div'))
				removeDiv(node);

			output = output.innerHTML;
			$output.remove();

			return output;
		};
	};

	/**
	 * Removes any leading or trailing quotes ('")
	 *
	 * @return string
	 * @since v1.4.0
	 */
	$.sceditor.plugins.bbcode.stripQuotes = function(str) {
		return str ? str.replace(/\\(.)/g, '$1').replace(/^(["'])(.*?)\1$/, '$2') : str;
	};

	/**
	 * Formats a string replacing {0}, {1}, {2}, ect. with
	 * the params provided
	 *
	 * @param {String} str The string to format
	 * @param {string} args... The strings to replace
	 * @return {String}
	 * @since v1.4.0
	 */
	$.sceditor.plugins.bbcode.formatString = function() {
		var args = arguments;
		return args[0].replace(/\{(\d+)\}/g, function(str, p1) {
			return typeof args[p1-0+1] !== 'undefined' ?
				args[p1-0+1] :
				'{' + p1 + '}';
		});
	};

	/**
	 * Converts CSS RGB and hex shorthand into hex
	 *
	 * @since v1.4.0
	 * @param {String} color
	 * @return {String}
	 */
	var normaliseColour = $.sceditor.plugins.bbcode.normaliseColour = function(color) {
		var m, toHex;

		toHex = function (n) {
			n = parseInt(n, 10);

			if(isNaN(n))
				return '00';

			n = Math.max(0, Math.min(n, 255)).toString(16);

			return n.length < 2 ? '0' + n : n;
		};

		color = color || '#000';

		// rgb(n,n,n);
		if((m = color.match(/rgb\((\d{1,3}),\s*?(\d{1,3}),\s*?(\d{1,3})\)/i)))
			return '#' + toHex(m[1]) + toHex(m[2]-0) + toHex(m[3]-0);

		// expand shorthand
		if((m = color.match(/#([0-f])([0-f])([0-f])\s*?$/i)))
			return '#' + m[1] + m[1] + m[2] + m[2] + m[3] + m[3];

		return color;
	};

	$.sceditor.plugins.bbcode.bbcodes = {
		// START_COMMAND: Bold
		b: {
			tags: {
				b: null,
				strong: null
			},
			styles: {
				// 401 is for FF 3.5
				'font-weight': ['bold', 'bolder', '401', '700', '800', '900']
			},
			format: '[b]{0}[/b]',
			html: '<strong>{0}</strong>'
		},
		// END_COMMAND

		// START_COMMAND: Italic
		i: {
			tags: {
				i: null,
				em: null
			},
			styles: {
				'font-style': ['italic', 'oblique']
			},
			format: "[i]{0}[/i]",
			html: '<em>{0}</em>'
		},
		// END_COMMAND

		// START_COMMAND: Underline
		u: {
			tags: {
				u: null
			},
			styles: {
				'text-decoration': ['underline']
			},
			format: '[u]{0}[/u]',
			html: '<u>{0}</u>'
		},
		// END_COMMAND

		// START_COMMAND: Strikethrough
		s: {
			tags: {
				s: null,
				strike: null
			},
			styles: {
				'text-decoration': ['line-through']
			},
			format: '[s]{0}[/s]',
			html: '<s>{0}</s>'
		},
		// END_COMMAND

		// START_COMMAND: Subscript
		sub: {
			tags: {
				sub: null
			},
			format: '[sub]{0}[/sub]',
			html: '<sub>{0}</sub>'
		},
		// END_COMMAND

		// START_COMMAND: Superscript
		sup: {
			tags: {
				sup: null
			},
			format: '[sup]{0}[/sup]',
			html: '<sup>{0}</sup>'
		},
		// END_COMMAND

		// START_COMMAND: Font
		font: {
			tags: {
				font: {
					face: null
				}
			},
			styles: {
				'font-family': null
			},
			quoteType: $.sceditor.BBCodeParser.QuoteType.never,
			format: function(element, content) {
				var font;

				if(element[0].nodeName.toLowerCase() !== 'font' || !(font = element.attr('face')))
					font = element.css('font-family');

				return '[font=' + this.stripQuotes(font) + ']' + content + '[/font]';
			},
			html: function(token, attrs, content) {
				return '<font face="' + attrs.defaultattr + '">' + content + '</font>';
			}
		},
		// END_COMMAND

		// START_COMMAND: Size
		size: {
			tags: {
				font: {
					size: null
				}
			},
			styles: {
				'font-size': null
			},
			format: function(element, content) {
				var	fontSize = element.attr('size'),
					size     = 1;

				if(!fontSize)
					fontSize = element.css('fontSize');

				// Most browsers return px value but IE returns 1-7
				if(fontSize.indexOf('px') > -1) {
					// convert size to an int
					fontSize = fontSize.replace('px', '') - 0;

					if(fontSize > 12)
						size = 2;
					if(fontSize > 15)
						size = 3;
					if(fontSize > 17)
						size = 4;
					if(fontSize > 23)
						size = 5;
					if(fontSize > 31)
						size = 6;
					if(fontSize > 47)
						size = 7;
				}
				else
					size = fontSize;

				return '[size=' + size + ']' + content + '[/size]';
			},
			html: function(token, attrs, content) {
				return '<font size="' + attrs.defaultattr + '">' + content + '</font>';
			}
		},
		// END_COMMAND

		// START_COMMAND: Color
		color: {
			tags: {
				font: {
					color: null
				}
			},
			styles: {
				color: null
			},
			quoteType: $.sceditor.BBCodeParser.QuoteType.never,
			format: function($element, content) {
				var	color,
					element = $element[0];

				if(element.nodeName.toLowerCase() !== 'font' || !(color = $element.attr('color')))
					color = element.style.color || $element.css('color');

				return '[color=' + normaliseColour(color) + ']' + content + '[/color]';
			},
			html: function(token, attrs, content) {
				return '<font color="' + normaliseColour(attrs.defaultattr) + '">' + content + '</font>';
			}
		},
		// END_COMMAND

		// START_COMMAND: Lists
		ul: {
			tags: {
				ul: null
			},
			breakStart: true,
			isInline: false,
			skipLastLineBreak: true,
			format: '[ul]{0}[/ul]',
			html: '<ul>{0}</ul>'
		},
		list: {
			breakStart: true,
			isInline: false,
			skipLastLineBreak: true,
			html: '<ul>{0}</ul>'
		},
		ol: {
			tags: {
				ol: null
			},
			breakStart: true,
			isInline: false,
			skipLastLineBreak: true,
			format: '[ol]{0}[/ol]',
			html: '<ol>{0}</ol>'
		},
		li: {
			tags: {
				li: null
			},
			isInline: false,
			closedBy: ['/ul', '/ol', '/list', '*', 'li'],
			format: '[li]{0}[/li]',
			html: '<li>{0}</li>'
		},
		'*': {
			isInline: false,
			closedBy: ['/ul', '/ol', '/list', '*', 'li'],
			html: '<li>{0}</li>'
		},
		// END_COMMAND

		// START_COMMAND: Table
		table: {
			tags: {
				table: null
			},
			isInline: false,
			isHtmlInline: true,
			skipLastLineBreak: true,
			format: '[table]{0}[/table]',
			html: '<table>{0}</table>'
		},
		tr: {
			tags: {
				tr: null
			},
			isInline: false,
			skipLastLineBreak: true,
			format: '[tr]{0}[/tr]',
			html: '<tr>{0}</tr>'
		},
		th: {
			tags: {
				th: null
			},
			allowsEmpty: true,
			isInline: false,
			format: '[th]{0}[/th]',
			html: '<th>{0}</th>'
		},
		td: {
			tags: {
				td: null
			},
			allowsEmpty: true,
			isInline: false,
			format: '[td]{0}[/td]',
			html: '<td>{0}</td>'
		},
		// END_COMMAND

		// START_COMMAND: Emoticons
		emoticon: {
			allowsEmpty: true,
			tags: {
				img: {
					src: null,
					'data-sceditor-emoticon': null
				}
			},
			format: function(element, content) {
				return element.data('sceditor-emoticon') + content;
			},
			html: '{0}'
		},
		// END_COMMAND

		// START_COMMAND: Horizontal Rule
		hr: {
			tags: {
				hr: null
			},
			allowsEmpty: true,
			isSelfClosing: true,
			isInline: false,
			format: '[hr]{0}',
			html: '<hr />'
		},
		// END_COMMAND

		// START_COMMAND: Image
		img: {
			allowsEmpty: true,
			tags: {
				img: {
					src: null
				}
			},
			quoteType: $.sceditor.BBCodeParser.QuoteType.never,
			format: function($element, content) {
				var	w, h,
					attribs   = '',
					element   = $element[0],
					style     = function(name) {
						return element.style ? element.style[name] : null;
					};

				// check if this is an emoticon image
				if($element.attr('data-sceditor-emoticon'))
					return content;

				w = $element.attr('width') || style('width');
				h = $element.attr('height') || style('height');

				// only add width and height if one is specified
				if((element.complete && (w || h)) || (w && h))
					attribs = "=" + $element.width() + "x" + $element.height();

				return '[img' + attribs + ']' + $element.attr('src') + '[/img]';
			},
			html: function(token, attrs, content) {
				var	parts,
					attribs = '';

				// handle [img width=340 height=240]url[/img]
				if(typeof attrs.width !== "undefined")
					attribs += ' width="' + attrs.width + '"';
				if(typeof attrs.height !== "undefined")
					attribs += ' height="' + attrs.height + '"';

				// handle [img=340x240]url[/img]
				if(attrs.defaultattr) {
					parts = attrs.defaultattr.split(/x/i);

					attribs = ' width="' + parts[0] + '"' +
						' height="' + (parts.length === 2 ? parts[1] : parts[0]) + '"';
				}

				return '<img' + attribs + ' src="' + content + '" />';
			}
		},
		// END_COMMAND

		// START_COMMAND: URL
		url: {
			allowsEmpty: true,
			tags: {
				a: {
					href: null
				}
			},
			quoteType: $.sceditor.BBCodeParser.QuoteType.never,
			format: function(element, content) {
				var url = element.attr('href');

				// make sure this link is not an e-mail, if it is return e-mail BBCode
				if(url.substr(0, 7) === 'mailto:')
					return '[email="' + url.substr(7) + '"]' + content + '[/email]';

				return '[url=' + decodeURI(url) + ']' + content + '[/url]';
			},
			html: function(token, attrs, content) {
				return '<a href="' + encodeURI(attrs.defaultattr || content) + '">' + content + '</a>';
			}
		},
		// END_COMMAND

		// START_COMMAND: E-mail
		email: {
			quoteType: $.sceditor.BBCodeParser.QuoteType.never,
			html: function(token, attrs, content) {
				return '<a href="mailto:' + (attrs.defaultattr || content) + '">' + content + '</a>';
			}
		},
		// END_COMMAND

		// START_COMMAND: Quote
		quote: {
			tags: {
				blockquote: null
			},
			isInline: false,
			quoteType: $.sceditor.BBCodeParser.QuoteType.never,
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

				return '[quote' + author + ']' + content + '[/quote]';
			},
			html: function(token, attrs, content) {
				if(attrs.defaultattr)
					content = '<cite>' + attrs.defaultattr + '</cite>' + content;

				return '<blockquote>' + content + '</blockquote>';
			}
		},
		// END_COMMAND

		// START_COMMAND: Code
		code: {
			tags: {
				code: null
			},
			isInline: false,
			allowedChildren: ['#', '#newline'],
			format: '[code]{0}[/code]',
			html: '<code>{0}</code>'
		},
		// END_COMMAND


		// START_COMMAND: Left
		left: {
			styles: {
				'text-align': ['left', '-webkit-left', '-moz-left', '-khtml-left']
			},
			isInline: false,
			format: '[left]{0}[/left]',
			html: '<div align="left">{0}</div>'
		},
		// END_COMMAND

		// START_COMMAND: Centre
		center: {
			styles: {
				'text-align': ['center', '-webkit-center', '-moz-center', '-khtml-center']
			},
			isInline: false,
			format: '[center]{0}[/center]',
			html: '<div align="center">{0}</div>'
		},
		// END_COMMAND

		// START_COMMAND: Right
		right: {
			styles: {
				'text-align': ['right', '-webkit-right', '-moz-right', '-khtml-right']
			},
			isInline: false,
			format: '[right]{0}[/right]',
			html: '<div align="right">{0}</div>'
		},
		// END_COMMAND

		// START_COMMAND: Justify
		justify: {
			styles: {
				'text-align': ['justify', '-webkit-justify', '-moz-justify', '-khtml-justify']
			},
			isInline: false,
			format: '[justify]{0}[/justify]',
			html: '<div align="justify">{0}</div>'
		},
		// END_COMMAND

		// START_COMMAND: YouTube
		youtube: {
			allowsEmpty: true,
			tags: {
				iframe: {
					'data-youtube-id': null
				}
			},
			format: function(element, content) {
				element = element.attr('data-youtube-id');

				return element ? '[youtube]' + element + '[/youtube]' : content;
			},
			html: '<iframe width="560" height="315" src="http://www.youtube.com/embed/{0}?wmode=opaque' +
				'" data-youtube-id="{0}" frameborder="0" allowfullscreen></iframe>'
		},
		// END_COMMAND


		// START_COMMAND: Rtl
		rtl: {
			styles: {
				'direction': ['rtl']
			},
			format: '[rtl]{0}[/rtl]',
			html: '<div style="direction: rtl">{0}</div>'
		},
		// END_COMMAND

		// START_COMMAND: Ltr
		ltr: {
			styles: {
				'direction': ['ltr']
			},
			format: '[ltr]{0}[/ltr]',
			html: '<div style="direction: ltr">{0}</div>'
		},
		// END_COMMAND

		// this is here so that commands above can be removed
		// without having to remove the , after the last one.
		// Needed for IE.
		ignore: {}
	};

	/**
	 * Static BBCode helper class
	 * @class command
	 * @name jQuery.plugins.bbcode.bbcode
	 */
	$.sceditor.plugins.bbcode.bbcode =
	/** @lends jQuery.plugins.bbcode.bbcode */
	{
		/**
		 * Gets a BBCode
		 *
		 * @param {String} name
		 * @return {Object|null}
		 * @since v1.3.5
		 */
		get: function(name) {
			return $.sceditor.plugins.bbcode.bbcodes[name] || null;
		},

		/**
		 * <p>Adds a BBCode to the parser or updates an existing
		 * BBCode if a BBCode with the specified name already exists.</p>
		 *
		 * @param {String} name
		 * @param {Object} bbcode
		 * @return {this|false} Returns false if name or bbcode is false
		 * @since v1.3.5
		 */
		set: function(name, bbcode) {
			if(!name || !bbcode)
				return false;

			// merge any existing command properties
			bbcode        = $.extend($.sceditor.plugins.bbcode.bbcodes[name] || {}, bbcode);
			bbcode.remove = function() { $.sceditor.plugins.bbcode.bbcode.remove(name); };

			$.sceditor.plugins.bbcode.bbcodes[name] = bbcode;

			return this;
		},

		/**
		 * Renames a BBCode
		 *
		 * This does not change the format or HTML handling, those must be
		 * changed manually.
		 *
		 * @param  {String} name    [description]
		 * @param  {String} newName [description]
		 * @return {this|false}
		 * @since v1.4.0
		 */
		rename: function(name, newName) {
			if (this.hasOwnProperty(name))
			{
				this[newName] = this[name];
				this.remove(name);
			}
			else
				return false;

			return this;
		},

		/**
		 * Removes a BBCode
		 *
		 * @param {String} name
		 * @return {this}
		 * @since v1.3.5
		 */
		remove: function(name) {
			if($.sceditor.plugins.bbcode.bbcodes[name])
				delete $.sceditor.plugins.bbcode.bbcodes[name];

			return this;
		}
	};

	/**
	 * Deprecated, use plugins: option instead. I.e.:
	 *
	 * $('textarea').sceditor({
	 *      plugins: 'bbcode'
	 * });
	 *
	 * @deprecated
	 */
	$.fn.sceditorBBCodePlugin = function (options) {
		options = options || {};

		if($.isPlainObject(options))
			options.plugins = (options.plugins ? options.plugins : '') + 'bbcode' ;

		return this.sceditor(options);
	};
})(jQuery, window, document);
