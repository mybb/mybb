var inlineEditor = {
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
				indicator: "<img src='images/spinner.gif'>",
				type: "text",
				submit: '',
				cancel: '',
				tooltip: "(Click and hold to edit)",
				onblur: "submit",
				event: "hold"+tid,
				callback: function(values, settings)
				{
					values = JSON.parse(values);
					if(values.errors)
					{
						$.each(values.errors, function(i, message)
						{
							$.jGrowl('There was an error performing the update. '+message);
						});
						$('#tid_' + tid).html($('#tid_' + tid + '_temp').html());
					}
					else
					{
						// Change subject
						$('#tid_' + tid).html('<a href="showthread.php?tid=' + tid + '">' + values.subject + '</a>');
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

				setTimeout(function()
				{
					$('#tid_' + tid).clone().attr('id','tid_' + tid + '_temp').css('display','none!important').appendTo("body");
					$('#tid_' + tid).trigger("hold" + tid);
					$('#tid_' + tid + ' input').width('98%');
				}, 700);
			});

			$(this).bind('mouseup mouseleave', function()
			{
				// Clear all time outs
				var wid = window.setTimeout(function() {}, 0);

				while(wid--)
				{
					window.clearTimeout(wid); // will do nothing if no timeout with id is present
				}
			});
        });

		return false;
	}
};

inlineEditor.init();