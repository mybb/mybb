var Rating = {
	init: function()
	{
		var rating_elements = document.getElementsByClassName('star_rating');
		rating_elements.each(function(rating_element) {
			var elements = Element.getElementsBySelector(rating_element, 'li a');
			if(Element.hasClassName(rating_element, 'star_rating_notrated'))
			{
				elements.each(function(element) {
					element.onclick = function() {
						var parameterString = this.href.replace(/.*\?(.*)/, "$1");
						return Rating.add_rating(parameterString);
					};
				});
			}
			else
			{
				elements.each(function(element) {
					element.onclick = function() { return false; };
					element.style.cursor = 'default';
				});
			}
		});
	},

	add_rating: function(parameterString)
	{
		if(use_xmlhttprequest == 'yes')
		{
			this.spinner = new ActivityIndicator('body', {image: imagepath + "/spinner.gif"});
			var element_id = parameterString.match(/tid=(.*)&/)[1];
			new Ajax.Request('ratethread.php?ajax=1', {
				method: 'post',
				postBody: parameterString,
				onComplete: function(request) { Rating.rating_added(request, element_id); }
			});
			document.body.style.cursor = 'wait';
			return false;
		}
		else
		{
			return true;
		}
	},

	rating_added: function(request, element_id)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/))
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);
			if(!message[1])
			{
				message[1] = 'An unknown error occurred.';
			}
			if(this.spinner)
			{
				this.spinner.destroy();
				this.spinner = '';
			}
			document.body.style.cursor = 'default';
			alert('There was an error performing the update.\n\n'+message[1]);
		}
		else if(request.responseText.match(/<success>(.*)<\/success>/))
		{
			var success = document.createElement('span');
			success.className = 'star_rating_success';
			success.id = 'success_rating_' + element_id;
			success.innerHTML = request.responseText.match(/<success>(.*)<\/success>/)[1];
			var element = $('rating_thread_' + element_id);
			element.parentNode.insertBefore(success, element.nextSibling);
			element.removeClassName('star_rating_notrated');

			var rating_elements = Element.getElementsBySelector(element, 'li a');
			rating_elements.each(function(element) {
				element.onclick = function() { return false; };
				element.style.cursor = 'default';
			});
			window.setTimeout("Element.remove('success_rating_" + element_id + "')", 5000);
			document.body.style.cursor = 'default';
			$('current_rating_' + element_id).style.width = request.responseText.match(/<width>(.*)<\/width>/)[1]+"%";
		}

		if(this.spinner)
		{
			this.spinner.destroy();
			this.spinner = '';
		}
	}
};

Event.observe(window, 'load', Rating.init);