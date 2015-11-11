<?php


namespace IPS\membermap\modules\admin\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * markers
 */
class _markers extends \IPS\Node\Controller
{
	/**
	 * Node Class
	 */
	protected $nodeClass = '\IPS\membermap\Custom\Groups';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		
		parent::execute();
	}

	/**
	 * Get Root Buttons
	 *
	 * @return	array
	 */
	public function _getRootButtons()
	{
		$nodeClass = $this->nodeClass;
		$buttons   = array();


		$buttons['add_group'] = array(
			'icon'	=> 'group',
			'title'	=> 'membermap_add_group',
			'link'	=> \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=markers&do=form' ),
			'data'  => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('membermap_add_group') )
		);

		$buttons['add_marker'] = array(
			'icon'	=> 'map-marker',
			'title'	=> 'membermap_add_marker',
			'link'	=>  \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=markers&subnode=1&do=form' ),
			'data'  => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('membermap_add_marker') )
		);

		return $buttons;
	}

	/**
	 * Show the pages tree
	 *
	 * @return	string
	 */
	protected function manage()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'membermap.css', 'membermap' ) );
	
		$url = \IPS\Http\Url::internal( "app=membermap&module=membermap&controller=markers" );
		
		/* Display the table */
		\IPS\Output::i()->title  = \IPS\Member::loggedIn()->language()->addToStack('menu__membermap_membermap_markers');
		\IPS\Output::i()->output = new \IPS\Helpers\Tree\Tree( $url, 'menu__membermap_membermap_markers',
			/* Get Roots */
			function () use ( $url )
			{
				$data = \IPS\membermap\modules\admin\membermap\markers::getRowsForTree( 0 );
				$rows = array();

				foreach ( $data as $id => $row )
				{
					$rows[ $id ] = ( $row instanceof \IPS\membermap\Custom\Markers ) ? \IPS\membermap\modules\admin\membermap\markers::getMarkerRow( $row, $url ) : \IPS\membermap\modules\admin\membermap\markers::getGroupRow( $row, $url );
				}

				return $rows;
			},
			/* Get Row */
			function ( $id, $root ) use ( $url )
			{
				if ( $root )
				{
					return \IPS\membermap\modules\admin\membermap\markers::getGroupRow( \IPS\membermap\Custom\Groups::load( $id ), $url );
				}
				else
				{
					return \IPS\membermap\modules\admin\membermap\markers::getMarkerRow( \IPS\membermap\Custom\Markers::load( $id ), $url );
				}
			},
			/* Get Row Parent ID*/
			function ()
			{
				return NULL;
			},
			/* Get Children */
			function ( $id ) use ( $url )
			{
				$rows = array();
				$data = \IPS\membermap\modules\admin\membermap\markers::getRowsForTree( $id );

				if ( ! isset( \IPS\Request::i()->subnode ) )
				{
					foreach ( $data as $id => $row )
					{
						$rows[ $id ] = ( $row instanceof \IPS\membermap\Custom\Markers ) ? \IPS\membermap\modules\admin\membermap\markers::getMarkerRow( $row, $url ) : \IPS\membermap\modules\admin\membermap\markers::getGroupRow( $row, $url );
					}
				}
				return $rows;
			},
           array( $this, '_getRootButtons' ),
           TRUE,
           FALSE,
           FALSE
		);
	}



	/**
	 * Delete
	 *
	 * @return	void
	 */
	protected function delete()
	{
		if ( isset( \IPS\Request::i()->id ) )
		{
			\IPS\cms\Pages\Page::deleteCompiled( \IPS\Request::i()->id );
		}

		parent::delete();
	}


	/**
	 * Return HTML for a marker row
	 *
	 * @param   array   $row	Row data
	 * @param	object	$url	\IPS\Http\Url object
	 * @return	string	HTML
	 */
	public static function getMarkerRow( $marker, $url )
	{
		return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row( 
			$url, 
			$marker->id, 
			$marker->name, 
			false, 
			$marker->getButtons( \IPS\Http\url::internal('app=membermap&module=membermap&controller=markers'), true ), 
			$marker->location, 
			'map-marker', 
			NULL, 
			FALSE, 
			NULL, 
			NULL, 
			NULL, 
			FALSE, 
			FALSE, 
			FALSE 
		);
	}

	/**
	 * Return HTML for a group row
	 *
	 * @param   array   $row	Row data
	 * @param	object	$url	\IPS\Http\Url object
	 * @return	string	HTML
	 */
	public static function getGroupRow( $group, $url )
	{
		return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row( 
			$url, 
			$group->id, 
			$group->name, 
			true, 
			$group->getButtons( \IPS\Http\url::internal('app=membermap&module=membermap&controller=markers') ),  
			"", 
			'folder-o', 
			NULL 
		);
	}

	/**
	 * Fetch rows of groups/markers
	 *
	 * @param int $groupId		Parent ID to fetch from
	 */
	public static function getRowsForTree( $groupId=0 )
	{
		try
		{
			if ( $groupId === 0 )
			{
				$folders = \IPS\membermap\Custom\Groups::roots();
			}
			else
			{
				$folders = \IPS\membermap\Custom\Groups::load( $groupId )->children( NULL, NULL, FALSE );
			}
		}
		catch( \OutOfRangeException $ex )
		{
			$folders = array();
		}

		$markers   = \IPS\membermap\Custom\Markers::getChildren( $groupId );

		return \IPS\membermap\Custom\Groups::munge( $folders, $markers );
	}
}