ips.templates.set( 'mapManager.listItem', "\
	<li data-provider='{{name}}' data-type='{{type}}' data-order='{{order}}'>\
		<div class='cMenuManager_leaf'>\
			<h3 class='cMenuManager_leafTitle'>\
				{{name}}\
				{{#noHTTPS}}<span data-ipsTooltip title='{{#lang}}membermap_noHTTPS_desc{{/lang}}' class='ipsBadge ipsBadge_negative'>{{#lang}}membermap_noHTTPS{{/lang}}</span>{{/noHTTPS}}\
				<span class='ipsPos_right defaultProvider'>\
					<span class='ipsBadge ipsBadge_positive'>{{#lang}}membermap_defaultProvider{{/lang}}</span>\
				</span>\
			</h3>\
		</div>\
	</li>\
");