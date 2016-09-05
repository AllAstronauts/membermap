<?php

$lang = array(
	/* Various strings */
	'__app_membermap'			=> "Member Map",
	'frontnavigation_membermap'	=> "Member Map",
	'__indefart_membermap_marker'	=> "a Member Map Marker",
	'__defart_membermap_marker'		=> "Member Map Marker",
	'membermap_rebuilding_cache'	=> "Rebuilding Member Map Cache",

	/* ACP Module/Menu titles */
	'menu__membermap_membermap' => "Member Map",
	'menu__membermap_membermap_markers' 	=> 'Markers Groups',
	'menu__membermap_membermap_mapmanager' 	=> "Map Manager",
	'menu__membermap_membermap_settings' 	=> "Settings",

	'module__membermap_membermap' 	=> "Member Map",
	'module__membermap_markers' 	=> "Marker Groups",
	'group__membermap_membermap' 	=> "Member Map",

	'membermap_group'				=> "Marker Groups",

	/* ACP Restrictions */
	'r__mapmanager' 			=> "Map Manager",
	'r__mapmanager_manage' 		=> "Can manage Map Manager?",
	'r__markers' 				=> "Marker Groups",
	'r__markers_manage' 		=> "Can view Custom Markers?",
	'r__markers_add' 			=> "Can add Custom Markers?",
	'r__markers_edit' 			=> "Can edit Custom Markers?",
	'r__markers_delete' 		=> "Can delete Custom Markers?",
	'r__markers_permissions' 	=> "Can manage permissions?",

	'membermap_marker_groups' 	=> "Marker Groups",
	'membermap_groups' 			=> "Groups",

	/* Front-end marker view */
	'membermap_marker_info' 		=> "Marker Information",
	'membermap_marker_author' 		=> "Added by",
	'membermap_marker_date' 		=> "Submitted",
	'membermap_marker_updated' 		=> "Updated",
	'membermap_marker_location' 	=> "Location",
	'membermap_marker_coordinates' 	=> "Coordinates",
	'group_markers_number' 			=> "{# [1:marker][?:markers]}",
	'group_markers_number_noCount' 	=> "{!# [1:marker][?:markers]}",
	'marker_actions' 				=> "Marker Actions",
	'report_marker' 				=> "Report this marker",
	'membermap_marker' 				=> "Member Map Marker",
	'membermap_marker_pl'			=> "Markers",
	'membermap_marker_pl_lc'		=> "markers",
	'membermap_markers_markers_pl' 	=> "Member Map",
	'membermap_view_this_marker' 	=> "View this marker: %s",
	'membermap_submit_a_marker' 	=> "Add Marker",
	'membermap_edit_a_marker' 		=> "Edit Marker",
	'membermap_no_markers_in_cat'	=> "There are no markers in this group",
	'membermap_submit_first_marker' => "Why don't you submit the first one?",
	'membermap_pending_approval'	=> "This marker is not yet approved and is currently only visible to staff.",
	'membermap_delete_title'		=> "Delete this marker",
	'membermap_approve_title'		=> "Approve this marker",


	/* Session Location */
	'loc_membermap_viewing_membermap' => "Viewing the Member Map",


	/* ACP Settings */	
	'map_settings' 					=> "Map Settings",
	'api_settings' 					=> "API Keys",
	'membermap_enable_clustering' 	=> "Enable Marker Clustering?",
	'membermap_bbox_location' 		=> "Define Forced Bounding Box",
	'membermap_bbox_location_desc' 	=> "Use the field above to search for a location that will always be in center of your map. The map will only focus on this area, regardless of markers outside of it. Try a few of the results from the search untill you find one that suits your needs.",
	'membermap_mapQuestAPI' 		=> "MapQuest API Key",
	'membermap_mapQuestAPI_desc' 	=> "Sign up for your own personal API Key at <a href='https://developer.mapquest.com/' target='_blank'>MapQuest</a>. This is required for the map to work.",
	'membermap_bbox_zoom' 			=> "Default Zoom Level",
	'membermap_bbox_zoom_desc' 		=> "Only affective when a bounding box is defined.",
	'membermap_groupByMemberGroup'	=> "Group member markers by member group?",
	'membermap_autoUpdate'			=> "Profile location synchronisation",
	'membermap_monitorLocationField' => "Enable profile sync?",
	'membermap_profileLocationField' => "Select profile field",
	'membermap_profileLocationField_desc' => "Select the custom profile field where members enter their location",
	'membermap_monitorLocationField_groupPerm' => "Enable for",
	'membermap_syncLocationField'	=> "Import members without a map marker?",
	'membermap_syncLocationField_desc'	=> "This will import members that have a location set in the profile, but not one in the map. This will turn itself off once all members are processed.",


	/* ACP Marker Group Settings */
	'g_membermap_canAdd' 			=> "Can add location to map",
	'g_membermap_canEdit' 			=> "Can edit their location",
	'g_membermap_canDelete' 		=> "Can delete their location",
	'g_membermap_markerColour' 		=> "Marker Colour",
	'g_membermap_markerColour_desc' => "The members own marker will always be green",
	'group_marker_example' 			=> "Marker Preview",
	'group_name' 					=> "Marker Group Name",
	'group_pin_icon' 				=> "Marker Icon",
	'group_pin_icon_desc' 			=> "Choose any icon from the <a href='http://fontawesome.io/icons/' target='_blank'>Font Awesome icon set</a>, input the full name in the box above, i.e. `fa-map-marker`",
	'group_pin_colour' 				=> "Marker Icon Colour",
	'group_pin_bg_colour' 			=> "Marker Background Colour",
	'group_pin_bg_colour_white' 	=> "White",
	'group_moderate' 				=> "Markers must be approved?",
	'membermap_import'				=> "Import Markers",
	'membermap_add_group' 			=> "Add Custom Marker Group",
	'import_upload'					=> "Marker .kml file",
	'import_creategroups'			=> "Create a new group",
	'import_creategroups_desc'		=> "Import markers to an existing group, or create a new group for each \"Folder\" in the .kml file",
	'membermap_error_no_id_no_create'	=> "You have to either chose to create a new group, or select an existing group to import markers to",
	'membermap_import_thumbup'			=> "Successfully imported %d markers",


	/* ACP Map Manager */
	'membermap_mapmanager_activeMaps' 		=> "Active Maps",
	'membermap_mapmanager_availMaps' 		=> "Available Maps",
	'membermap_mapmanager_activeOverlays' 	=> "Active Overlays",
	'membermap_mapmanager_availOverlays' 	=> "Available Overlays",
	'membermap_mapmanager_preview' 			=> "A preview of (almost) all maps and overlays can be seen here",

	/* Permissions */
	'perm_membermap_perm__label'		=> '',
	'perm_membermap_perm__view'			=> 'See Group',
	'perm_membermap_perm__read'			=> "View Markers",
	'perm_membermap_perm__add'			=> "Add Markers",


	/* Front-end showmap */
	'membermap_adminTools' 				=> "Admin Tools",
	'membermap_button_addLocation' 		=> "Add Location",
	'membermap_button_editLocation' 	=> "Update Location",
	'membermap_current_location' 		=> "Use Current Location",
	'membermap_geolocation_desc' 		=> "This will use a feature in your browser to detect your current location using GPS, Cellphone triangulation, Wifi, Router, or IP address",
	'membermap_add_marker' 				=> "Add Custom Marker",
	'membermap_edit_marker' 			=> "Edit Custom Marker",
	'membermap_form_location' 			=> "Search for your location",
	'membermap_form_placeholder' 		=> "Enter your address / city / county / country, you can be as specific as you like",
	'membermap_button_myLocation' 		=> "My Location",
	'membermap_showing_markers'			=> "Showing <span>0</span> markers",
	'membermap_view_fullsize' 			=> "View fullsize map",
	'membermap_goHome' 					=> "Go to my location",
	'membermap_browse_markers'			=> "Browse Markers",


	/* Front-end errors */
	'membermap_error_cantEdit' 	=> "You are not allowed to edit your location.",
	'membermap_error_cantAdd' 	=> "You are not allowed to add a location.",
	'membermap_error_noGroups' 	=> "You need to create a Custom Marker Group first.",
	'membermap_only_one_marker' => "You can only have one member marker. <a href='%s'>Edit your existing one.</a>",
	'membermap_noAPI_admin'		=> "As of Member Map v3.1 a personal <a href='https://developer.mapquest.com/' target='_blank'>MapQuest API key</a> is required. Go to ACP > Community > Member Map > Settings to add yours. (This message is only visible to administrators)",

	/* Front-end marker form */
	'marker_title' 				=> "Marker Title",
	'marker_description' 		=> "Marker Description",
	'marker_lat' 				=> "Latitude (N)",
	'marker_location' 			=> "Location",
	'marker_location_desc' 		=> "Enter a location above to search for one, enter the latitude and longitude manually below, or select the location in the map",
	'marker_lon' 				=> "Longitude (E)",
	'marker_parent_id' 			=> "Marker Group",
	'marker_submit'				=> "Save Marker",

	/* Front-end admin tools */
	'membermap_rebuildBrowserCache' 		=> "Rebuild Browser Cache",
	'membermap_rebuildServerCache' 			=> "Rebuild Server Cache (Last Update: %s)",
	'membermap_rebuildServerCache_notSet' 	=> "Rebuild Server Cache (Not created yet)",
);
