$(function () {
	/*var tabContainers = $("[id^=tab_]");

    $("ul#tabs a").click(function () {
        tabContainers.hide().filter(this.hash).show();

        $("ul#tabs a").removeClass("active");
        $(this).addClass("active");

        return false;
    }).filter(":first").click();*/
	
	
	
	$('ul.tabs').each(function(){
		var $activeTab, $activeContent, $links = $(this).find('a');

		$activeTab = $($links.filter('[href="'+location.hash+'"]')[0] || $links[0]);
		$activeTab.addClass('active');
		$activeContent = $($activeTab.attr('href'));

		// Hide the remaining content
		$links.not($activeTab).each(function () {
			$($(this).attr('href')).hide();
		});

		// Tab functionality
		$(this).on('click', 'a', function(e){

			$activeTab.removeClass('active');
			$activeContent.hide();

			$activeTab = $(this);
			$activeContent = $($(this).attr('href'));

			$activeTab.addClass('active');
			$activeContent.show();

			e.preventDefault();
		});
	});
	
	
});