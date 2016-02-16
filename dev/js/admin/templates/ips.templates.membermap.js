ips.templates.set( 'mapManager.listItem', "\
	<li data-provider='{{name}}' data-type='{{type}}' data-order='{{order}}'>\
		<div class='cMenuManager_leaf'>\
			<h3 class='cMenuManager_leafTitle'>\
				{{name}}\
				<span class='ipsPos_right defaultProvider'>\
					<span class='ipsBadge ipsBadge_positive'>{{#lang}}membermap_defaultProvider{{/lang}}</span>\
				</span>\
			</h3>\
		</div>\
	</li>\
");