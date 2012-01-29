$(document).ready(function() {
	// Hide inline validation messages by default
	// This lives in custom because it may not be desired behaviour for other sites using this framework
	$.each($('.inlineValidation'), function() {
		var childCount = 0;
		$.each($(this).children(), function() {
			childCount++;
		});

		if(childCount > 0) {
			$(this).show();
		}
	});
});
