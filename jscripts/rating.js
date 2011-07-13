var Rating = {
	init: function()
	{
		var rating_elements = $$('.star_rating');
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
					var element_id = element.href.replace(/.*\?(.*)/, "$1").match(/tid=(.*)&(.*)&/)[1];
					element.title = $('current_rating_'+element_id).innerHTML;
				});
			}
		});
	},
	
	build_forumdisplay: function(tid, options)
	{
		if(!$('rating_thread_'+tid))
		{
			return;
		}
		var list = document.getElementById('rating_thread_'+tid);
		list.className = 'star_rating' + options.extra_class;
		
		list_classes = new Array();
		list_classes[1] = 'one_star';
		list_classes[2] = 'two_stars';
		list_classes[3] = 'three_stars';
		list_classes[4] = 'four_stars';
		list_classes[5] = 'five_stars';

		for(var i = 1; i <= 5; i++)
		{
			var list_element = document.createElement('li');
			var list_element_a = document.createElement('a');
			list_element_a.className = list_classes[i];
			list_element_a.title = lang.stars[i];
			list_element_a.href = './ratethread.php?tid='+tid+'&rating='+i+'&my_post_key='+my_post_key;
			list_element_a.innerHTML = i;
			list_element.appendChild(list_element_a);
			list.appendChild(list_element);
		}
	},

	add_rating: function(parameterString)
	{
		this.spinner = new ActivityIndicator('body', {image: imagepath + "/spinner_big.gif"});
		var element_id = parameterString.match(/tid=(.*)&(.*)&/)[1];
		new Ajax.Request('ratethread.php?ajax=1&my_post_key='+my_post_key, {
			method: 'post',
			postBody: parameterString,
			onComplete: function(request) { Rating.rating_added(request, element_id); }
		});
		document.body.style.cursor = 'wait';
		return false;
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
			if(!$('success_rating_' + element_id))
			{
				var success = document.createElement('span');
				var element = $('rating_thread_' + element_id);
				element.parentNode.insertBefore(success, element.nextSibling);
				element.removeClassName('star_rating_notrated');
			}
			else
			{
				var success = $('success_rating_' + element_id);
			}
			success.className = 'star_rating_success';
			success.id = 'success_rating_' + element_id;
			success.innerHTML = request.responseText.match(/<success>(.*)<\/success>/)[1];

			if(request.responseText.match(/<average>(.*)<\/average>/))
			{
				$('current_rating_' + element_id).innerHTML = request.responseText.match(/<average>(.*)<\/average>/)[1];
			}

			var rating_elements = $$('.star_rating');
			rating_elements.each(function(rating_element) {
				var elements = Element.getElementsBySelector(rating_element, 'li a');
				elements.each(function(element) {
					if(element.id == "rating_thread_" + element_id) {
						element.onclick = function() { return false; };
						element.style.cursor = 'default';
						element.title = $('current_rating_'+element_id).innerHTML;
					}
				});
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

if(use_xmlhttprequest == 1)
{
	Event.observe(document, 'dom:loaded', Rating.init);
}