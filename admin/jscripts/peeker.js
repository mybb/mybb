/**
 * Peeker is a simple class which controls the visibility of something based on a value of a select form
 *
 * Usage:
 * var peeker = new Peeker( <id of the controlling select menu>, <id of the thing to show/hide>, <matching regexp to show the thing>);
 */

var Peeker = function(controller, domain, match, is_nodelist) {
	this.initialize(controller, domain, match, is_nodelist);
};

Peeker.prototype = {
	
    controller: null,
    domain: null,
    match: null,
    is_nodelist: null,
	
    /**
     * Checks the controller and shows/hide
     */
	check: function()
	{
		// Array
		if(this.is_nodelist)
		{
            // Find a match (if found show domain)
            for(i = 0; i < this.controller.length; i++)
            {
                if(this.controller.eq(i).prop('checked') && this.controller.eq(i).val().match(this.match))
                {
                    this.domain.show();
                    return;
                }
            }
            // Nothing found
            this.domain.hide();
		}
		else
		{
		    type = this.controller.val();
			if(typeof type == 'undefined') type = '';
    		if(type.match(this.match)) {
				this.domain.show();
			}
			else 
			{
				this.domain.hide();
			}
		}
	},
	
	/**
	 * Constructor
	 * @param string ID of the controlling select menu
	 * @param string ID of the thing to show/hide
	 * @param regexp If this regexp matches value of the select menu, then the 'thing' will be shown
	 * @param boolean Should be set to true for radio/checkboxes
	 */
	initialize: function(controller, domain, match, is_nodelist)
	{
        var handler = function(event){ event.data.check(event) };
        // Ugly code to differentiate initialization between nodelist and element
		if(is_nodelist)
		{
		    if(controller.length > 0 && domain)
		    {
    		    this.controller = controller;
                this.domain = domain;
                this.match = match;
                this.is_nodelist = is_nodelist;
                
                for(i = 0; i < controller.length; i++)
                {
           			if(controller.eq(i).attr("id") != null)
           			{
						controller.eq(i).bind('change', this, handler);
               			controller.eq(i).bind('click', this, handler);
           			}
                }
                this.check();
		    }
		}
	    else if(controller && domain)
		{
            this.controller = controller;
            this.domain = domain;
            this.match = match;
            this.is_nodelist = is_nodelist;
            
            controller.bind('change', this, handler);

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
	if($('#'+id))
	{
		cell = $('#'+id).find("td").first();
		label = cell.find("label").first();
		star = document.createElement("em");
		starText = document.createTextNode(" *");
		star.appendChild(starText);
		label.append(star);
	}
}