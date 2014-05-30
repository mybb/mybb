var Report = {
	init: function()
	{
		$(document).ready(function(){
		});
	},

	reportPost: function(pid)
	{
		MyBB.popupWindow("/report.php?type=post&pid="+pid);
	},
	
	reportUser: function(pid)
	{
		MyBB.popupWindow("/report.php?type=profile&pid="+pid);
	},

	reportReputation: function(pid)
	{
		MyBB.popupWindow("/report.php?type=reputation&pid="+pid);
	},
	
	submitReport: function(pid)
	{
		// Get form, serialize it and send it
		var datastring = $(".reportData_"+pid).serialize();
		$.ajax({
			type: "POST",
			url: "report.php",
			data: datastring,
			dataType: "html",
			success: function(data) {
				// Replace modal HTML
				$('.modal_'+pid).fadeOut('slow', function() {
					$('.modal_'+pid).html(data);
					$('.modal_'+pid).fadeIn('slow');
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