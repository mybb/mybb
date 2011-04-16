/**
 * @author Ryan Johnson <ryan@livepipe.net>
 * @copyright 2007 LivePipe LLC
 * @package Object.Event
 * @license MIT
 * @url http://livepipe.net/projects/object_event/
 * @version 1.0.0
 */

Object.Event = {
	extend: function(object){
		object._objectEventSetup = function(event_name){
			this._observers = this._observers || {};
			this._observers[event_name] = this._observers[event_name] || [];
		};
		object.observe = function(event_name,observer){
			if(typeof(event_name) == 'string' && typeof(observer) != 'undefined'){
				this._objectEventSetup(event_name);
				if(!this._observers[event_name].include(observer))
					this._observers[event_name].push(observer);
			}else
				for(var e in event_name)
					this.observe(e,event_name[e]);
		};
		object.stopObserving = function(event_name,observer){
			this._objectEventSetup(event_name);
			this._observers[event_name] = this._observers[event_name].without(observer);
		};
		object.notify = function(event_name){
			this._objectEventSetup(event_name);
			var collected_return_values = [];
			var args = $A(arguments).slice(1);
			try{
				for(var i = 0; i < this._observers[event_name].length; ++i)
					collected_return_values.push(this._observers[event_name][i].apply(this._observers[event_name][i],args) || null);
			}catch(e){
				if(e == $break)
					return false;
				else
					throw e;
			}
			return collected_return_values;
		};
		if(object.prototype){
			object.prototype._objectEventSetup = object._objectEventSetup;
			object.prototype.observe = object.observe;
			object.prototype.stopObserving = object.stopObserving;
			object.prototype.notify = function(event_name){
				if(object.notify){
					var args = $A(arguments).slice(1);
					args.unshift(this);
					args.unshift(event_name);
					object.notify.apply(object,args);
				}
				this._objectEventSetup(event_name);
				var args = $A(arguments).slice(1);
				var collected_return_values = [];
				try{
					if(this.options && this.options[event_name] && typeof(this.options[event_name]) == 'function')
						collected_return_values.push(this.options[event_name].apply(this,args) || null);
					for(var i = 0; i < this._observers[event_name].length; ++i)
						collected_return_values.push(this._observers[event_name][i].apply(this._observers[event_name][i],args) || null);
				}catch(e){
					if(e == $break)
						return false;
					else
						throw e;
				}
				return collected_return_values;
			};;
		}
	}
};

/**
 * @author Ryan Johnson <ryan@livepipe.net>
 * @copyright 2007 LivePipe LLC
 * @package Control.Tabs
 * @license MIT
 * @url http://livepipe.net/projects/control_tabs/
 * @version 2.1.1
 */

if(typeof(Control) == 'undefined')
	var Control = {};
Control.Tabs = Class.create();
Object.extend(Control.Tabs,{
	instances: [],
	findByTabId: function(id){
		return Control.Tabs.instances.find(function(tab){
			return tab.links.find(function(link){
				return link.key == id;
			});
		});
	}
});
Object.extend(Control.Tabs.prototype,{
	initialize: function(tab_list_container,options){
		this.activeContainer = false;
		this.activeLink = false;
		this.containers = $H({});
		this.links = [];
		Control.Tabs.instances.push(this);
		this.options = {
			beforeChange: Prototype.emptyFunction,
			afterChange: Prototype.emptyFunction,
			hover: false,
			linkSelector: 'li a',
			setClassOnContainer: false,
			activeClassName: 'active',
			defaultTab: 'first',
			autoLinkExternal: true,
			targetRegExp: /#(.+)$/,
			showFunction: Element.show,
			hideFunction: Element.hide
		};
		Object.extend(this.options,options || {});
		(typeof(this.options.linkSelector == 'string')
			? $(tab_list_container).getElementsBySelector(this.options.linkSelector)
			: this.options.linkSelector($(tab_list_container))
		).findAll(function(link){
			return (/^#/).exec(link.href.replace(window.location.href.split('#')[0],''));
		}).each(function(link){
			this.addTab(link);
		}.bind(this));
		this.containers.values().each(this.options.hideFunction);
		if(this.options.defaultTab == 'first')
			this.setActiveTab(this.links.first());
		else if(this.options.defaultTab == 'last')
			this.setActiveTab(this.links.last());
		else
			this.setActiveTab(this.options.defaultTab);
		var targets = this.options.targetRegExp.exec(window.location);
		if(targets && targets[1]){
			targets[1].split(',').each(function(target){
				this.links.each(function(target,link){
					if(link.key == target){
						this.setActiveTab(link);
						throw $break;
					}
				}.bind(this,target));
			}.bind(this));
		}
		if(this.options.autoLinkExternal){
			$A(document.getElementsByTagName('a')).each(function(a){
				if(!this.links.include(a)){
					var clean_href = a.href.replace(window.location.href.split('#')[0],'');
					if(clean_href.substring(0,1) == '#'){
						if(this.containers.keys().include(clean_href.substring(1))){
							$(a).observe('click',function(event,clean_href){
								this.setActiveTab(clean_href.substring(1));
							}.bindAsEventListener(this,clean_href));
						}
					}
				}
			}.bind(this));
		}
	},
	addTab: function(link){
		this.links.push(link);
		link.key = link.getAttribute('href').replace(window.location.href.split('#')[0],'').split('/').last().replace(/#/,'');
		this.containers.set(link.key, $(link.key));
		link[this.options.hover ? 'onmouseover' : 'onclick'] = function(link){
			if(window.event)
				Event.stop(window.event);
			this.setActiveTab(link);
			return false;
		}.bind(this,link);
	},
	setActiveTab: function(link){
		if(!link)
			return;
		if(typeof(link) == 'string'){
			this.links.each(function(_link){
				if(_link.key == link){
					this.setActiveTab(_link);
					throw $break;
				}
			}.bind(this));
		}else{
			this.notify('beforeChange',this.activeContainer);
			if(this.activeContainer)
				this.options.hideFunction(this.activeContainer);
			this.links.each(function(item){
				(this.options.setClassOnContainer ? $(item.parentNode) : item).removeClassName(this.options.activeClassName);
			}.bind(this));
			(this.options.setClassOnContainer ? $(link.parentNode) : link).addClassName(this.options.activeClassName);
			this.activeContainer = this.containers.get(link.key);
			this.activeLink = link;
			this.options.showFunction(this.containers.get(link.key));
			this.notify('afterChange',this.containers.get(link.key));
		}
	},
	next: function(){
		this.links.each(function(link,i){
			if(this.activeLink == link && this.links[i + 1]){
				this.setActiveTab(this.links[i + 1]);
				throw $break;
			}
		}.bind(this));
		return false;
	},
	previous: function(){
		this.links.each(function(link,i){
			if(this.activeLink == link && this.links[i - 1]){
				this.setActiveTab(this.links[i - 1]);
				throw $break;
			}
		}.bind(this));
		return false;
	},
	first: function(){
		this.setActiveTab(this.links.first());
		return false;
	},
	last: function(){
		this.setActiveTab(this.links.last());
		return false;
	},
	notify: function(event_name){
		try{
			if(this.options[event_name])
				return [this.options[event_name].apply(this.options[event_name],$A(arguments).slice(1))];
		}catch(e){
			if(e != $break)
				throw e;
			else
				return false;
		}
	}
});
if(typeof(Object.Event) != 'undefined')
	Object.Event.extend(Control.Tabs);