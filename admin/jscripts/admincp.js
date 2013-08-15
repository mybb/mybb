var AdminCP = {
	init: function()
	{
	},

	deleteConfirmation: function(element, message)
	{
		if(!element) return false;
		confirmReturn = confirm(message);
		if(confirmReturn == true)
		{
			form = $("<form />", { method: "post", action: element.href, style: "display: none;" });
			$("body").append(form);
			form.submit();
		}
		return false;
	}
};

$(function()
{
	AdminCP.init();
});