var Rating = {
	init: function()
	{
		var rating_elements = Element.getElementsBySelector(document, 'ul.star_rating li a');
		rating_elements.each(function(element) {
			element.onclick = function() {
				var parameterString = this.href.replace(/.*\?(.*)/, "$1");
				return Rating.add_rating(parameterString);
			};
		});
	},

	add_rating: function(parameterString)
	{
		if(use_xmlhttprequest == "yes")
		{
			this.spinner = new ActivityIndicator("body", {image: imagepath + "/spinner_big.gif"});
			var element_id = parameterString.match(/tid=(.*)&/)[1];
			new Ajax.Request('ratethread.php?ajax=1', {
				method: 'post',
				postBody: parameterString,
				onComplete: function(request) { Rating.rating_added(request, element_id); }
			});
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
				message[1] = "An unknown error occurred.";
			}
			if(this.spinner)
			{
				this.spinner.destroy();
				this.spinner = '';
			}
			alert('There was an error performing the update.\n\n'+message[1]);
		}
		else if(request.responseText.match(/<success>(.*)<\/success>/))
		{
			var success = document.createElement('span');
			success.className = "star_rating_success";
			success.id = "success_rating_" + element_id;
			success.innerHTML = request.responseText.match(/<success>(.*)<\/success>/)[1];
			var element = $("rating_thread_" + element_id);
			element.parentNode.insertBefore(success, element.nextSibling);
			Element.removeClassName(element, "star_rating_notrated");
			var rating_elements = Element.getElementsBySelector(element, 'li a');
			rating_elements.each(function(element) {
				element.onclick = function() { return false; };
				element.style.cursor = 'default';
			});
			window.setTimeout("Element.hide('success_rating_" + element_id + "')", 5000);
			$("current_rating_" + element_id).style.width = request.responseText.match(/<width>(.*)<\/width>/)[1]+"%";
		}

		if(this.spinner)
		{
			this.spinner.destroy();
			this.spinner = '';
		}
	}
};

Event.observe(window, 'load', Rating.init);