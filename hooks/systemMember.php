//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class membermap_hook_systemMember extends _HOOK_CLASS_
{
	/**
	 * Process any posst made before registering
	 *
	 * @param	string	$secret	The secret, if just created (necessary for avoiding R/W separation issues if no validation is required)
	 * @return	void
	 */
	protected function _processPostBeforeRegistering( $secret = NULL )
	{
		/*
			Member Map is only allowed to store one item per member, but there is currently not an easy way to determine 
			if a guest have created more than one before completing the registration. We will therefor keep the last one, and delete the others.
		 */
		$where = $secret ? array( 'member=? OR secret=?', $this->member_id, $secret ) : array( 'member=?', $this->member_id );
		
		$items = array();

		foreach ( \IPS\Db::i()->select( '*', 'core_post_before_registering', $where, 'id ASC' ) as $row )
		{
			if ( $row['class'] == 'IPS\membermap\Markers\Markers' )
			{
				$items[] = $row['id'];
			}
		}

		if ( \count( $items ) > 1 )
		{
			$itemToKeep = \array_pop( $items );

			$marker = \IPS\membermap\Markers\Markers::load( $itemToKeep );
			$marker->name = $this->name;
			$marker->save();

			\IPS\Content\Search\Index::i()->index( $marker );

			if ( \is_array( $items ) AND \count( $items ) )
			{
				foreach( $items as $item )
				{
					try
					{
						\IPS\membermap\Markers\Markers::load( $item )->delete();
					}
					catch( \Exception $e ) {}
				}
			}
		}

		return parent::_processPostBeforeRegistering( $secret );
	}
}