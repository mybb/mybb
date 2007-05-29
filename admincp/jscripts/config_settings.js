var SettingType = {
	check: function()
	{
		type = $('type').value;
		if(type.match(/select|radio|checkbox|php/))
		{
			$('row_extra').style.display = '';
		}
		else
		{
			$('row_extra').style.display = 'none';
		}
	},
	
	init: function()
	{
		if($('type') && $('row_extra'))
		{
			Event.observe($('type'), "change", SettingType.check);
			SettingType.check();
			
			// Add a star to the extra row since the "extra" is required if the box is shown
			cell = $('row_extra').getElementsByTagName('td')[0];
			label = cell.getElementsByTagName('label')[0];
			star = document.createElement('em');
			starText = document.createTextNode(' *');
			star.appendChild(starText);
			label.appendChild(star);
		}
	}
};