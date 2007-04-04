/**
* @author Ryan Johnson <ryan@livepipe.net>
* @copyright 2007 LivePipe LLC
* @package Control.Tabs
* @license MIT
* @url http://livepipe.net/projects/control_tabs/
* @version 1.5.2
*/

if(typeof(Control) == "undefined")
	var Control = {};
Control.Tabs = Class.create();
Object.extend(Control.Tabs,{
	tabs: $A([]),
	responders: $A([]),
	addResponder: function(responder){
		Control.Tabs.responders.push(responder);
	},
	removeResponder: function(responder){
		Control.Tabs.responders = Control.Tabs.responders.without(responder);
	},
	notifyResponders: function(event_name,argument_one,argument_two){
		Control.Tabs.responders.each(function(responder){
			if(responder[event_name])
				responder[event_name](argument_one,argument_two);
		});
	},
	findByTabId: function(id){
		return this.tabs.find(function(tab){
			return tab.links.find(function(link){
				return link.key == id;
			});
		});
	}
});
Object.extend(Control.Tabs.prototype,{
	activeContainer: false,
	initialize: function(tab_set,options){
		Control.Tabs.tabs.push(this);
		tab_set = $(tab_set);
		this.options = $H({
			beforeChange: Prototype.emptyFunction,
			afterChange: Prototype.emptyFunction,
			linkSelector: 'li a',
			activeClassName: 'active',
			defaultTab: 'first',
			autoLinkExternal: true
		}).merge(options || {});
		this.containers = $H({});
		this.links = (typeof(this.options.linkSelector == "string")
			? tab_set.getElementsBySelector(this.options.linkSelector)
			: this.options.linkSelector(tab_set)
		).findAll(function(link){return (/^#/).exec(link.href.replace(window.location.href.split('#')[0],''));});
		this.links.each(function(link){
			link.key = $A(link.getAttribute('href').replace(window.location.href.split('#')[0],'').split('/')).last().replace(/#/,'');
			this.containers[link.key] = $(link.key);
			link.onclick = function(link){
				this.setActiveTab(link);
				return false;
			}.bind(this,link);
		}.bind(this));
		if(this.options.defaultTab == 'first')
			this.setActiveTab(this.links.first());
		else if(this.options.defaultTab == 'last')
			this.setActiveTab(this.links.last());
		else
			this.setActiveTab(this.options.defaultTab);
		target_regexp = /#(.+)$/;
		targets = target_regexp.exec(window.location);
		if(targets && targets[1]){
			$A(targets[1].split(',')).each(function(target){
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
					clean_href = a.href.replace(window.location.href.split('#')[0],'');
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
	setActiveTab: function(link){
		if(typeof(link) == "undefined" || link == false)
			return;
		if(typeof(link) == "string"){
			this.links.each(function(_link){
				if(_link.key == link){
					this.setActiveTab(_link);
					throw $break;
				}
			}.bind(this));
		}else{
			this.containers.each(function(item){
				item[1].hide();
			});			
			this.links.each(function(item){
				item.removeClassName(this.options.activeClassName);
			}.bind(this));
			link.addClassName(this.options.activeClassName);
			this.options.beforeChange(this,this.activeContainer);
			Control.Tabs.notifyResponders('beforeChange',this,this.activeContainer);
			this.activeContainer = this.containers[link.key];
			this.containers[link.key].show();
			this.options.afterChange(this,this.containers[link.key]);
			Control.Tabs.notifyResponders('afterChange',this,this.containers[link.key]);
		}
	}
});