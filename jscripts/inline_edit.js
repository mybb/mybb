var inlineEditor = {
	timeouts: [],

	init: function()
	{
		$(document).ready(function()
		{
			inlineEditor.bindSubjects();
		});
	},

	bindSubjects: function()
	{
		$('.subject_editable').each(function()
		{
			// Take tid out of the id attribute
			id = $(this).attr('id');
			tid = id.replace( /[^\d.]/g, '');

			$(this).editable("xmlhttp.php?action=edit_subject&my_post_key=" + my_post_key + "&tid=" + tid,
			{
				indicator: spinner,
				type: "text",
				submit: '',
				cancel: '',
				tooltip: lang.inline_edit_description,
				onblur: "submit",
				event: "hold"+tid,
				callback: function(values, settings)
				{
					id = $(this).attr('id');
					tid = id.replace( /[^\d.]/g, '');

					values = JSON.parse(values);
					if(typeof values == 'object')
					{
						if(values.hasOwnProperty("errors"))
						{
							$.each(values.errors, function(i, message)
							{
								$.jGrowl(lang.post_fetch_error + ' ' + message);
							});
							$(this).html($('#tid_' + tid + '_temp').html());
						}
						else
						{
							// Change subject
							$(this).html(values.subject);
						}
					}
					
					$('#tid_' + tid + '_temp').remove();
				},
				data: function(value, settings)
				{
					return $(value).text();
				}
			});

			// Hold event
			$(this).bind("mousedown", function(e)
			{
				// Take tid out of the id attribute
				id = $(this).attr('id');
				tid = id.replace( /[^\d.]/g, '');
				
				// We may click again in the textbox and we'd be adding a new (invalid) clone - we don't want that!
				if(!$('#tid_' + tid + '_temp').length)
					$(this).clone().attr('id','tid_' + tid + '_temp').hide().appendTo("body");

				inlineEditor.timeouts[tid] = setTimeout(inlineEditor.jeditableTimeout, 700, tid);
			});

			$(this).bind('mouseup mouseleave', function()
			{
				window.clearTimeout(inlineEditor.timeouts[tid]);
			});
        });

		return false;
	},
	
	jeditableTimeout : function(tid)
	{
		$('#tid_' + tid).trigger("hold" + tid);
		$('#tid_' + tid + ' input').width('98%');
	}
};

inlineEditor.init();