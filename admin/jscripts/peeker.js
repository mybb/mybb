/**
 * Peeker controls the visibility of an element based on the value of an input
 *
 * Examples:
 * new Peeker($('#myController'), $('#myDomain'), 1, false);
 * new Peeker($('.myControllerNode'), $('#myDomain, #myDomain2'), 1, true);
 * new Peeker($('#myController'), $('#nestedPeeker'), /works/, false);
 * new Peeker($('#nestedPeeker'), $('#nestedPeekerChild'), /\d+/, false);
 */

var Peeker = (function() {
	/**
	 * Constructor
	 *
	 * @param string ID of the controlling select menu
	 * @param string ID of the thing to show/hide
	 * @param regexp If this regexp matches value of the select menu, then the 'thing' will be shown
	 * @param boolean Should be set to true for radio/checkboxes
	 */
	function Peeker(controller, domain, match, isNodelist) {
		var fn;

		// verify input
		if (!controller ||
		    (isNodelist && controller.length <= 0) ||
			!domain) {
			return;
		}
		this.controller = controller;
		this.domain = domain;
		this.match = match;
		this.isNodelist = isNodelist;

		// create a context-bound copy of the function
		fn = $.proxy(this.check, this);

		if (isNodelist) {
			// attach event handlers to the inputs in the node list
			this.controller.each(function(i, el) {
				el = $(el);
				el.on('click change', fn);
			});
		} else {
			this.controller.on('change', fn);
		}
		this.check();
	}

	/**
	 * Checks the controller and shows/hide
	 *
	 * @return void
	 */
	function check() {
		var type = '', show = false, regex = this.match;

		if (this.isNodelist) {
			this.controller.each(function(i, el) {
				if ($(el).is(':visible') && el.checked &&
				    el.value.match(regex)) {
					show = true;
					return false;
				}
			});
			this.domain[show ? 'show' : 'hide']();
		} else {
			type = this.controller.val() || '';
			this.domain[(type.match(regex) && this.controller.is(':visible')) ? 'show' : 'hide']();
		}
		
		$(this.domain).each(function() {
			$(this).find('input, textarea, select').each(function() {
				$(this).trigger('change');
			});
		});
	}

	Peeker.prototype = {
		controller: null,
		domain: null,
		match: null,
		isNodelist: null,
		check: check,
	};

	return Peeker;
})();

/**
 * Add a "required" asterisk to a FormContainer row
 * @param string ID of the row
 */
function add_star(id) {
	if (!$('#' + id).length) {
		return;
	}

	cell = $('#' + id).children('td')[0];
	label = $(cell).children('label')[0];
	star = $(document.createElement('em'));
	starText = $(document.createTextNode(' *'));
	star.append(starText);
	$(label).append(star);
}