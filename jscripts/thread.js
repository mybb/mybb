var Thread = {
	init: function()
	{
		$(document).ready(function(){
			Thread.quickEdit();
		});
	},
	
	quickEdit: function()
	{
		$('.post_body').each(function() {
		
			// Take pid out of the id attribute
			id = $(this).attr('id');
			pid = id.replace( /[^\d.]/g, '');

			$('#pid_' + pid).editable("xmlhttp.php?action=edit_post&do=update_post&pid=" + pid + '&my_post_key=' + my_post_key, {
				indicator : "<img src='images/spinner.gif'>",
				loadurl : "xmlhttp.php?action=edit_post&do=get_post&pid=" + pid,
				type : "textarea",
				submit : "OK",
				cancel : "Cancel",
				tooltip : "Click to edit...",
				event : "edit" + pid, // Triggered by the event "edit_[pid]"
				callback : function(values, settings) {
					values = JSON.parse(values);
					
					// Change html content
					$('#pid_' + pid).html(values.message);
					$('#edited_by_' + pid).html(values.editedmsg);
				}
			});
        });
		
		$('.quick_edit_button').each(function() {
			$(this).bind("click", function(e) {
				//e.stopPropagation();
				
				// Take pid out of the id attribute
				id = $(this).attr('id');
				pid = id.replace( /[^\d.]/g, '');
				
				// Force popup menu closure
				$('#edit_post_' + pid + '_popup').trigger('close_popup');
			
				// Trigger the edit event
				$('#pid_' + pid).trigger("edit" + pid);
			});
        });

		return false;
	},
};

Thread.init();