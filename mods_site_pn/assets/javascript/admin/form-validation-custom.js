$(document).ready(function() {
	var requestUri = window.location.href.split('/');
	var controller = null;
	var itemId = 0;

	if(requestUri[4] != undefined) {
		controller = requestUri[4];
	}

	if(requestUri[7] != undefined) {
		itemId = requestUri[7];
	}

	$('input.validateFriendlyUrl').focusout(function() {
		var inputElement = $(this);
		var friendlyUrl = inputElement.attr('value');
		var sectionId = 0;

		if($('#tagSectionSelect') != undefined) {
			sectionId = $('#tagSectionSelect').attr('value');
		}

		validateFriendlyUrl(inputElement, friendlyUrl, controller, itemId, sectionId);
	});
});

// Validate a friendly URL
// Lives outside of document.ready as this can be called from other scripts
function validateFriendlyUrl(inputElement, friendlyUrl, controller, itemId, sectionId) {
	if(sectionId == undefined) {
		sectionId = 0;
	}

	var validRegex = /^[a-zA-Z0-9\-]+$/;

	if(!friendlyUrl.match(validRegex)) {
		setInvalid(inputElement, 'Friendly URL is not valid');
	} else {
		$.ajax({
			url: '/admin/ajax/validateFriendlyUrl',
			type: 'post',
			data: {
				friendlyUrl: friendlyUrl,
				controller: controller,
				itemId: itemId,
				sectionId: sectionId
			},
			dataType: 'json',
			success: function(jsonData) {
				if(jsonData.available == true) {
					setValid(inputElement, 'Friendly URL is available');
				} else {
					setInvalid(inputElement, 'Friendly URL is not available.');
				}
			}
		});
	}
}
