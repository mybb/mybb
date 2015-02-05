(function ($) {
	'use strict';

	$.sceditor.plugins.undo = function () {
		var base = this;
		var editor;
		var charChangedCount = 0;
		var previousValue;

		var undoLimit  = 50;
		var redoStates = [];
		var undoStates = [];
		var ignoreNextValueChanged = false;

		/**
		 * Sets the editor to the specified state.
		 *
		 * @param  {Object} state
		 * @private
		 */
		var applyState = function (state) {
			ignoreNextValueChanged = true;

			previousValue = state.value;

			editor.sourceMode(state.sourceMode);
			editor.val(state.value, false);
			editor.focus();

			if (state.sourceMode) {
				editor.sourceEditorCaret(state.caret);
			} else {
				editor.getRangeHelper().restoreRange();
			}

			ignoreNextValueChanged = false;
		};


		/**
		 * Caluclates the number of characters that have changed
		 * between two strings.
		 *
		 * @param {String} strA
		 * @param {String} strB
		 * @return {String}
		 * @private
		 */
		var simpleDiff = function (strA, strB) {
			var start, end, aLenDiff, bLenDiff,
				aLength = strA.length,
				bLength = strB.length,
				length  = Math.max(aLength, bLength);

			// Calculate the start
			for (start = 0; start < length; start++) {
				if (strA[start] !== strB[start]) {
					break;
				}
			}

			// Calculate the end
			aLenDiff = aLength < bLength ? bLength - aLength : 0;
			bLenDiff = bLength < aLength ? aLength - bLength : 0;

			for (end = length - 1; end >= 0; end--) {
				if (strA[end - aLenDiff] !== strB[end - bLenDiff]) {
					break;
				}
			}

			return (end - start) + 1;
		};

		base.init = function () {
			// The this variable will be set to the instance of the editor
			// calling it, hence why the plugins "this" is saved to the base
			// variable.
			editor = this;

			undoLimit = editor.undoLimit || undoLimit;

			// addShortcut is the easiest way to add handlers to specific
			// shortcuts
			editor.addShortcut('ctrl+z', base.undo);
			editor.addShortcut('ctrl+shift+z', base.redo);
			editor.addShortcut('ctrl+y', base.redo);
		};

		base.undo = function () {
			var state = undoStates.pop();
			var rawEditorValue = editor.val(null, false);

			if (state && !redoStates.length && rawEditorValue === state.value) {
				state = undoStates.pop();
			}

			if (state) {
				if (!redoStates.length) {
					redoStates.push({
						'caret': editor.sourceEditorCaret(),
						'sourceMode': editor.sourceMode(),
						'value': rawEditorValue
					});
				}

				redoStates.push(state);
				applyState(state);
			}

			return false;
		};

		base.redo = function () {
			var state = redoStates.pop();

			if (!undoStates.length) {
				undoStates.push(state);
				state = redoStates.pop();
			}

			if (state) {
				undoStates.push(state);
				applyState(state);
			}

			return false;
		};

		base.signalReady = function () {
			var rawEditorValue = editor.val(null, false);

			// Store the initial value as the last value
			previousValue = rawEditorValue;

			undoStates.push({
				'caret': this.sourceEditorCaret(),
				'sourceMode': this.sourceMode(),
				'value': rawEditorValue
			});
		};

		/**
		 * Handle the valueChanged signal.
		 *
		 * e.rawValue will either be the raw HTML from the WYSIWYG editor with
		 * the rangeHelper range markers inserted, or it will be the raw value
		 * of the source editor (BBCode or HTML depening on plugins).
		 * @return {void}
		 */
		base.signalValuechangedEvent = function (e) {
			var rawEditorValue = e.rawValue;

			if (undoLimit > 0 && undoStates.length > undoLimit) {
				undoStates.shift();
			}

			if (ignoreNextValueChanged || previousValue === rawEditorValue) {
				return;
			}

			// Value has changed so remove all redo states
			redoStates.length = 0;
			charChangedCount += simpleDiff(previousValue, rawEditorValue);

			if (charChangedCount < 20) {
				return;
			} else if (charChangedCount < 50 &&
// ??
				!e.rawValue[e.rawValue.length - 1].match(/\s/g)) {
				return;
			}

			undoStates.push({
				'caret': editor.sourceEditorCaret(),
				'sourceMode': editor.sourceMode(),
				'value': rawEditorValue
			});

			charChangedCount = 0;
			previousValue = rawEditorValue;
		};
	};
}(jQuery));
