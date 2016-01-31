$(function()
{
	$('ul.tabs').each(function()
	{
		if($(this).data('rendered'))
		{
			return;
		}

		$(this).data('rendered', 'yes');

		var activeTab, activeContent, links = $(this).find('a');

		activeTab = $(links.filter('[href="'+location.hash+'"]')[0] || links[0]);
		activeTab.addClass('active');
		activeContent = $(activeTab.attr('href'));

		// Hide the remaining content
		links.not(activeTab).each(function()
		{
			$($(this).attr('href')).hide();
		});

		// Tab functionality
		$(this).on('click', 'a', function(e)
		{
			activeTab.removeClass('active');
			activeContent.hide();

			activeTab = $(this);
			activeContent = $($(this).attr('href'));

			// update address bar
			window.location.hash = $(this).attr('href');

			activeTab.addClass('active');
			activeContent.show();

			e.preventDefault();
		});
	});

});