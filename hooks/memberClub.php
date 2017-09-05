//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class membermap_hook_memberClub extends _HOOK_CLASS_
{

	/**
	 * Get Node names and URLs
	 *
	 * @return	array
	 */
	public function nodes()
	{
		$return = call_user_func_array( 'parent::nodes', func_get_args() );

		if ( \IPS\Dispatcher::hasInstance() AND \IPS\Dispatcher::i()->controllerLocation == 'front' )
		{
			if ( \IPS\Settings::i()->membermap_clubs_showInClubHeader )
			{
				if( \IPS\Settings::i()->membermap_clubs == '*' OR \strstr( ','.\IPS\Settings::i()->membermap_clubs.',', ",{$this->id}," ) )
				{
					$return[] = array(
						'name'			=> \IPS\Member::loggedIn()->language()->addToStack('membermap_clubnavtitle'),
						'url'			=> \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=showmap&filter=showClub&clubId=' . $this->id, 'front', 'membermap' ),
						'node_class'	=> 'IPS\membermap\Markers\Groups',
						'node_id'		=> \IPS\membermap\Map::i()->getMemberGroupId(),
					);
				}
			}
		}

		return $return;
	}
}