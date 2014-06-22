var ViewManager = {
	init: function()
	{
		if(!$('fields_enabled') || !$('fields_disabled'))
		{
			return;
		}

		if(!$('fields_js'))
		{
			return;
		}

		Sortable.create("fields_enabled", {dropOnEmpty: true, containment:["fields_enabled","fields_disabled"], constraint: false, onChange: ViewManager.buildFieldsList});
		Sortable.create("fields_disabled", {dropOnEmpty: true, containment:["fields_enabled","fields_disabled"], constraint: false, onChange: ViewManager.buildFieldsList});
	},

	buildFieldsList: function()
	{
		new_input = '';
		for(var i=0; i <= $('fields_enabled').childNodes.length; i++)
		{
			if($('fields_enabled').childNodes[i] && $('fields_enabled').childNodes[i].id)
			{
				id = $('fields_enabled').childNodes[i].id.split("-");
				
				if(id[1])
				{
					if(new_input)
					{
						new_input += ",";
					}
					new_input += id[1];
				}
			}
		}
		$('fields_js').value = new_input;
	}
};

Event.observe(window, 'load', ViewManager.init);