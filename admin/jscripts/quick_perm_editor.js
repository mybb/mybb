var QuickPermEditor = {

	init: function(id)
	{
		if(!$('#fields_enabled_'+id) || !$('#fields_disabled_'+id))
		{
			return;
		}
		if(!$('#fields_'+id))
		{
			return;
		}
		
		$("#fields_enabled_"+id).sortable({
			connectWith: "#fields_disabled_"+id,
			dropOnEmpty: true,
			update: function(event, ui) {
				QuickPermEditor.buildFieldsList(id);
			}
		}).disableSelection();
		
		$("#fields_disabled_"+id).sortable({
			connectWith: "#fields_enabled_"+id,
			dropOnEmpty: true,
			update: function(event, ui) {
				QuickPermEditor.buildFieldsList(id);
			}
		}).disableSelection();
	},

	buildFieldsList: function(id)
	{
		new_input = '';

		$('#fields_enabled_'+id).children().each(function() {
			var textid = $(this).attr('id').split("-");
		
			if(textid[1])
			{
				if(new_input)
				{
					new_input += ",";
				}
				new_input += textid[1];
			}
		});
		
		if($('#fields_'+id).val() != new_input)
		{
			if($('#default_permissions_'+id))
			{
				$('#default_permissions_'+id).attr('checked', false);
			}
		}
		
		$('#fields_'+id).val(new_input);

		if($('#fields_inherit_'+id))
		{
			$('#fields_inherit_'+id).val(0);
		}
	},
};