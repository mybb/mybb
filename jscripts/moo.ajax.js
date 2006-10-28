// Based on prototype's ajax class
// To be used with prototype.lite, moofx.mad4milk.net.

ajax = Class.create();
ajax.prototype = {
	initialize: function(url, options) 
	{
		this.transport = this.getTransport();
		this.postBody = options.postBody || '';
		this.method = options.method || 'post';
		this.onComplete = options.onComplete || null;
		this.update = $(options.update) || null;
		this.request(url);
	},

	request: function(url) 
	{
		this.transport.open(this.method, url, true);
		this.transport.onreadystatechange = this.onStateChange.bind(this);
		if(this.method == 'post') 
		{
			this.transport.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
			if(this.transport.overrideMimeType) 
			{
				this.transport.setRequestHeader('Connection', 'close');
			}
		}
		this.transport.send(this.postBody);
	},

	onStateChange: function()
	{
		if (this.transport.readyState == 4 && this.transport.status == 200)
		{
			if (this.onComplete) 
			{
				setTimeout(function(){this.onComplete(this.transport);}.bind(this), 10);
			}
			
			if (this.update)
			{
				setTimeout(function(){this.update.innerHTML = this.transport.responseText;}.bind(this), 10);
			}
			this.transport.onreadystatechange = function(){};
		}
	},

	getTransport: function() 
	{
		if(window.XMLHttpRequest) 
		{
			return new XMLHttpRequest(); 
		}
		else if(window.ActiveXObject)
		{
			try { 
				req = new ActiveXObject('Msxml2.XMLHTTP.4.0'); 
			}
			
			catch(e) {
			
				try {
					req = new ActiveXObject('Microsoft.XMLHTTP'); 
				}
				
				catch(e) {
					req = false; 
				}
			}
			return req; 
		}
		else 
		{	
			return false; 
		}
	}
};