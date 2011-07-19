/**
 * Peeker is a simple class which controls the visibility of something based on a value of a select form
 *
 * Usage:
 * var peeker = new Peeker( <id of the controlling select menu>, <id of the thing to show/hide>, <matching regexp to show the thing>);
 */

var Peeker = Class.create();
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
                if(this.controller[i].checked && this.controller[i].value.match(this.match))
                {
                    Element.show(this.domain);
                    return;
                }
            }
            // Nothing found
            Element.hide(this.domain);
		}
		else
		{
		    type = this.controller.value;
    		this.domain.style.display = (type.match(this.match)) ? '' : 'none';
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
           			if(controller[i].getAttribute("id") != null)
           			{
               			Event.observe(controller[i], "change", this.check.bindAsEventListener(this));
              			Event.observe(controller[i], "click", this.check.bindAsEventListener(this));
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
            
            Event.observe(controller, "change", this.check.bindAsEventListener(this));
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