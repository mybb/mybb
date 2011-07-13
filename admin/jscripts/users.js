var Users = {
	last_value: '',
	cached_users: '',

	init: function()
	{
	}
};

Event.observe(window, 'load', Users.init);