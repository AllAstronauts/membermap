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
      'selector' => '#elProfileInfoColumn > div.ipsAreaBackground_light.ipsPad',
      'type' => 'add_inside_end',
      'content' => '{{if $memberMarker = \IPS\\\membermap\Map::i()->getMarkerByMember( $member->member_id ) !== false AND count( $memberMarker )}}
	{template="profileMap" group="map" app="membermap" params="$member"}
{{endif}}',
    ),
  ),
), parent::hookData() );
}
/* End Hook Data */






}