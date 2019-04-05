var Rating = {
	init: function()
	{
		var rating_elements = $(".star_rating");
		rating_elements.each(function()
		{
			var rating_element = $(this);
			var elements = rating_element.find("li a");
			if(rating_element.hasClass("star_rating_notrated"))
			{
				elements.each(function()
				{
					var element = $(this);
					element.on('click', function()
					{
						var parameterString = element.attr("href").replace(/.*\?(.*)/, "$1");
						return Rating.add_rating(parameterString);
					});
				});
			}
			else
			{
				elements.each(function()
				{
					var element = $(this);
					element.attr("onclick", "return false;");
					element.css("cursor", "default");
					var element_id = element.attr("href").replace(/.*\?(.*)/, "$1").match(/tid=(.*)&(.*)&/)[1];
					element.attr("title", $("#current_rating_"+element_id).text());
				});
			}
		});
	},

	build_forumdisplay: function(tid, options)
	{
		var list = $("#rating_thread_"+tid);
		if(!list.length)
		{
			return;
		}
		
		list.addClass("star_rating")
			.addClass(options.extra_class);

		list_classes = new Array();
		list_classes[1] = 'one_star';
		list_classes[2] = 'two_stars';
		list_classes[3] = 'three_stars';
		list_classes[4] = 'four_stars';
		list_classes[5] = 'five_stars';

		for(var i = 1; i <= 5; i++)
		{
			var list_element = $("<li></li>");
			var list_element_a = $("<a></a>");
			list_element_a.addClass(list_classes[i])
						  .attr("title", lang.stars[i])
						  .attr("href", "./ratethread.php?tid="+tid+"&rating="+i+"&my_post_key="+my_post_key)
			              .html(i);
			list_element.append(list_element_a);
			list.append(list_element);
		}
	},

	add_rating: function(parameterString)
	{
		var tid = parameterString.match(/tid=(.*)&(.*)&/)[1];
		var rating = parameterString.match(/rating=(.*)&(.*)/)[1];
		$.ajax(
		{
			url: 'ratethread.php?ajax=1&my_post_key='+my_post_key+'&tid='+tid+'&rating='+rating,
			async: true,
			method: 'post',
			dataType: 'json',
	        complete: function (request)
	        {
	        	Rating.rating_added(request, tid);
	        }
		});
		return false;
	},

	rating_added: function(request, element_id)
	{
		var json = JSON.parse(request.responseText);
		if(json.hasOwnProperty("errors"))
		{
			$.each(json.errors, function(i, error)
			{
				$.jGrowl(lang.ratings_update_error + ' ' + error, {theme:'jgrowl_error'});
			});
		}
		else if(json.hasOwnProperty("success"))
		{
			var element = $("#rating_thread_"+element_id);
			element.parent().before(element.next());
			element.removeClass("star_rating_notrated");

			$.jGrowl(json.success, {theme:'jgrowl_success'});
			if(json.hasOwnProperty("average"))
			{
				$("#current_rating_"+element_id).html(json.average);
			}

			var rating_elements = $(".star_rating");
			rating_elements.each(function()
			{
				var rating_element = $(this);
				var elements = rating_element.find("li a");
				if(rating_element.hasClass('star_rating_notrated'))
				{
					elements.each(function()
					{
						var element = $(this);
						if(element.attr("id") == "rating_thread_" + element_id)
						{
							element.attr("onclick", "return false;")
								   .css("cursor", "default")
							       .attr("title", $("#current_rating_"+element_id).text());
						}
					});
				}
			});
			$("#current_rating_"+element_id).css("width", json.width+"%");
		}
	}
};

if(use_xmlhttprequest == 1)
{
	$(function()
	{
		Rating.init();
	});
}