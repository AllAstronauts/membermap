//<?php

class membermap_hook_profileMap extends _HOOK_CLASS_
{

/* !Hook Data - DO NOT REMOVE */
public static function hookData() {
 return array_merge_recursive( array (
  'profile' => 
  array (
    0 => 
    array (
      'selector' => '#elProfileInfoColumn > div.ipsPad',
      'type' => 'add_inside_end',
      'content' => '{{ $memberMarker = FALSE; try { $memberMarker = \IPS\membermap\Map::i()->getMarkerByMember( $member->member_id ); } catch( \Exception $ex ){ } }}
      {{if settings.membermap_showProfileMap AND $memberMarker AND $memberMarker !== false AND \count( $memberMarker ) AND \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( "membermap", "membermap" ) )}}
	{template="profileMap" group="map" app="membermap" params="$member"}
{{endif}}',
    ),
  ),
), parent::hookData() );
}
/* End Hook Data */






}