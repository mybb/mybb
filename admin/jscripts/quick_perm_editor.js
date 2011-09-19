var QuickPermEditor = {

	init: function(id)
	{
		if(!$('fields_enabled_'+id) || !$('fields_disabled_'+id))
		{
			return;
		}
		if(!$('fields_'+id))
		{
			return;
		}

		Sortable.create("fields_enabled_"+id, {dropOnEmpty: true, containment:["fields_enabled_"+id,"fields_disabled_"+id], constraint: false, onUpdate: function() { QuickPermEditor.buildFieldsList(id)}});
		Sortable.create("fields_disabled_"+id, {dropOnEmpty: true, containment:["fields_enabled_"+id,"fields_disabled_"+id], constraint: false, onUpdate: function() { QuickPermEditor.buildFieldsList(id)}});
	},

	buildFieldsList: function(id)
	{
		new_input = '';
		var length = $('fields_enabled_'+id).childNodes.length;
		for(var i=0; i <= length; i++)
		{
			if($('fields_enabled_'+id).childNodes[i] && $('fields_enabled_'+id).childNodes[i].id)
			{
				var split_id = $('fields_enabled_'+id).childNodes[i].id.split("-");
				
				if(split_id[1])
				{
					if(new_input)
					{
						new_input += ",";
					}
					new_input += split_id[1];
				}
			}
		}
		if($('fields_'+id).value != new_input)
		{
			if($('default_permissions_'+id))
			{
				$('default_permissions_'+id).checked = false;
			}
		}
		$('fields_'+id).value = new_input;

		if($('fields_inherit_'+id))
		{
			$('fields_inherit_'+id).value = 0;
		}
	},
};