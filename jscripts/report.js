var Report = {
	init: function()
	{
		$(function(){
		});
	},

	reportPost: function(pid)
	{
		MyBB.popupWindow("/report.php?modal=1&type=post&pid="+pid);
	},
	
	reportUser: function(pid)
	{
		MyBB.popupWindow("/report.php?modal=1&type=profile&pid="+pid);
	},

	reportReputation: function(pid)
	{
		MyBB.popupWindow("/report.php?modal=1&type=reputation&pid="+pid);
	},
	
	submitReport: function(pid)
	{
		// Get form, serialize it and send it
		var datastring = $(".reportData_"+pid).serialize();
		$.ajax({
			type: "POST",
			url: "report.php?modal=1",
			data: datastring,
			dataType: "html",
			success: function(data) {
				// Replace modal HTML
				$('.modal_'+pid).fadeOut('slow', function() {
					$('.modal_'+pid).html(data);
					$('.modal_'+pid).fadeIn('slow');
					$('.modal').fadeIn('slow');
				});
			},
			error: function(){
				  alert(lang.unknown_error);
			}
		});

		return false;
	}
};

Report.init();