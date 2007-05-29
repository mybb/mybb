var Peeker = Class.create();

Peeker.prototype = {
	
    controller: "",
    domain: "",
    match: "",
	
	check: function()
	{
		type = $(this.controller).value;
		$(this.domain).style.display = (type.match(this.match)) ? '' : 'none';
	},
	
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