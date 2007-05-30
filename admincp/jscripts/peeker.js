/**
 * Peeker is a simple class which controls the visibility of something based on a value of a select form
 *
 * Usage:
 * var peeker = new Peeker( <id of the controlling select menu>, <id of the thing to show/hide>, <matching regexp to show the thing>);
 */

var Peeker = Class.create();
Peeker.prototype = {
	
    controller: "",
    domain: "",
    match: "",
	
    /**
     * Checks the controller and shows/hide
     */
	check: function()
	{
		type = $(this.controller).value;
		$(this.domain).style.display = (type.match(this.match)) ? '' : 'none';
	},
	
	/**
	 * Constructor
	 * @param string ID of the controlling select menu
	 * @param string ID of the thing to show/hide
	 * @param regexp If this regexp matches value of the select menu, then the 'thing' will be shown
	 */
	initialize: function(controller, domain, match)
	{
		if($(controller) && $(domain))
		{
            this.controller = controller;
            this.domain = domain;
            this.match = match;
            
			Event.observe($(controller), "change", this.check.bindAsEventListener(this));
            this.check();
		}
	}
};

/**
 * Add a "required" asterisk to a FormContainer row
 * @param string ID of the row
 */
var add_star = function(id)
{
	if($(id))
	{
		cell = $(id).getElementsByTagName("td")[0];
		label = cell.getElementsByTagName("label")[0];
		star = document.createElement("em");
		starText = document.createTextNode(" *");
		star.appendChild(starText);
		label.appendChild(star);
	}
}