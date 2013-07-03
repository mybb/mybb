$(function () {
	var tabContainers = $("[id^=tab_]");

    $("ul#tabs a").click(function () {
        tabContainers.hide().filter(this.hash).show();

        $("ul#tabs a").removeClass("active");
        $(this).addClass("active");

        return false;
    }).filter(":first").click();
});