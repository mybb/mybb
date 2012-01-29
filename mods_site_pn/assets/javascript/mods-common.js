var defaultDialog = {
	autoOpen: false,
	modal: true,
	draggable: false,
	resizable: false
}

$(document).ready(function() {
	/**
	 * Delete link clicked
	 */
	$('a.triggerConfirmDelete').click(function() {
		triggerConfirmDelete(this);
		return false; 
	});

	/**
	 * Initialise standard dialog boxes
	 */
	$('.dialog').dialog(defaultDialog);

	/**
	 * Initialise standard buttons
	 */
	$('.button').button();
	
	/** 
	 * Only do certain things if we're on index - create project
	 */
	if ($('#locationforjs').text() == 'createproject')
	{
		// Show create project form
		$('#createproject').show();
	
		/**
		 * Check if we need to update the select boxes
		 */
		if ($('#primarycat :selected').val() != 0)
		{
			switchSubCategory($('#primarycat').val());
		}
	}
});

/**
 * Trigger delete confirmation dialog
 */
function triggerConfirmDelete(linkElement, options) {
	if(options == null) {
		var options = {}
	}

	if(options.dialogObject == null) {
		options.dialogObject = 'div.confirmDelete';
	}

	$(options.dialogObject).dialog({
		autoOpen: false,
		modal: true,
		draggable: false,
		resizable: false,
		buttons: {
			'Yes': function() {
				$(this).dialog('close');

				if(options.ajax) {
					$.ajax({
						url: $(linkElement).attr('href') + '/mode/json',
						type: 'post',
						data: {yes: 'Yes'},
						dataType: 'json',
						success: function(jsonData) {
							if(options.success) {
								options.success(jsonData);
							} else {
								processUserMessages(jsonData);
							}
						}
					});
				} else {
					form = document.createElement('form');
					form.setAttribute('method', 'post');
					form.setAttribute('action', $(linkElement).attr('href'));
					form.setAttribute('style', 'display: none');
					$('body').append(form);
					form.submit();
				}
			},
			'No': function() {
				$(this).dialog('close');
			}
		}
	});

	$(options.dialogObject).dialog('open');
}

/**
 * Process UserMessenger messages in a JSON object
 */
function processUserMessages(jsonData) {
	for(var i in jsonData) {
		triggerUserMessage(jsonData[i].userMessage, jsonData[i].messageClass);
	}
}

/**
 * Trigger UserMessenger message
 */
function triggerUserMessage(messageText, messageClass, keepExistingMessages) {
	// Hide existing messages unless explicitly told not to
	if(keepExistingMessages == null) {
		$('div.userMessages p').hide();
	}

	message = document.createElement('p');
	message.setAttribute('class', messageClass);
	message.setAttribute('style', 'display: none');
	$(message).text(messageText);
	$('div.userMessages').append(message);
	$(message).fadeIn('slow');
}

function slideIt(id)
{
	if ($(id).css('display') == 'none')
		$(id).slideDown("fast");
	else
		$(id).slideUp("fast");
}

function switchSubCategory(category)
{
	$('#invalid').hide();

	switch (category)
	{
		case 'plugins':
			if ($('#category_plugins').css('display') == 'none')
				$('#category_plugins').show();
				
			$('#category_themes').hide();
			$('#category_resources').hide();
			$('#category_graphics').hide();
		break;
		case 'themes':
			if ($('#category_themes').css('display') == 'none')
				$('#category_themes').show();
				
			$('#category_plugins').hide();
			$('#category_resources').hide();
			$('#category_graphics').hide();
		break;
		case 'resources':
			if ($('#category_resources').css('display') == 'none')
				$('#category_resources').show();
				
			$('#category_themes').hide();
			$('#category_plugins').hide();
			$('#category_graphics').hide();
		break;
		case 'graphics':
			if ($('#category_graphics').css('display') == 'none')
				$('#category_graphics').show();
				
			$('#category_themes').hide();
			$('#category_resources').hide();
			$('#category_plugins').hide();
		break;
	}
}
