//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class membermap_hook_calendarEvent extends _HOOK_CLASS_
{
	/**
	 * Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		call_user_func_array( 'parent::save', func_get_args() );

		if ( ! \IPS\Settings::i()->membermap_calendarExt )
		{
			return;
		}

		/* Rebuild the cache if any event with a location is changed */
		if ( $this->location !== NULL )
		{
			\IPS\membermap\Map::invalidateJsonCache();
		}
	}

}
