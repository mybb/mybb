var ViewManager = {
	init: function()
	{
		if(!$('#fields_enabled') || !$('#fields_disabled'))
		{
			return;
		}

		if(!$('#fields_js'))
		{
			return;
		}
		
		$("#fields_enabled").sortable({
			connectWith: "#fields_disabled",
			dropOnEmpty: true,
			update: function(event, ui) {
				ViewManager.buildFieldsList();
			}
		}).disableSelection();
		
		$("#fields_disabled").sortable({
			connectWith: "#fields_enabled",
			dropOnEmpty: true,
			update: function(event, ui) {
				ViewManager.buildFieldsList();
			}
		}).disableSelection();
	},

	buildFieldsList: function()
	{
		new_input = '';
		$('#fields_enabled').children().each(function() {
			id = $(this).attr('id').split("-");
		
			if(id[1])
			{
				if(new_input)
				{
					new_input += ",";
				}
				new_input += id[1];
			}
		});
		$('#fields_js').val(new_input);
	}
};

$(function()
{
	ViewManager.init();
});