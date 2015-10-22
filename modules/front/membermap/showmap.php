<?php


namespace IPS\membermap\modules\front\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * showmap
 */
class _showmap extends \IPS\Dispatcher\Controller
{
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
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$markers = array();


		if ( ! is_file ( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-index.json' ) OR \IPS\Request::i()->rebuildCache === '1' )
		{
			\IPS\membermap\Map::i()->recacheJsonFile();
		}

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_leaflet.js', 'membermap', 'front' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_main.js', 'membermap', 'front' ) );

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, array( '//netdna.bootstrapcdn.com/font-awesome/3.2.1/css/font-awesome.css' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'membermap.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'jquery-ui.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'Control.FullScreen.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'Control.Loading.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.awesome-markers.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.contextmenu.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'MarkerCluster.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'showLoading.css', 'membermap' ) );

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( '__app_membermap' );
		
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
        \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'map' )->showMap( json_encode( $markers ) );

        if ( ! \IPS\Request::i()->embed )
        {
			\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=membermap', 'front', 'membermap' ), array(), 'loc_membermap_viewing_membermap' );
		}

        $is_supmod		= \IPS\Member::loggedIn()->modPermission() ?: 0;
        $member_id		= \IPS\Member::loggedIn()->member_id ?: 0;
        \IPS\Output::i()->endBodyCode .= <<<EOF
		<script type='text/javascript'>
			ips.setSetting( 'is_supmod', {$is_supmod} );
			ips.setSetting( 'member_id', {$member_id} );

			ips.membermap.initMap();
		</script>
EOF;

	}
	
	// Create new methods with the same name as the 'do' parameter which should execute it
}
