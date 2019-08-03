<?php
/**
 * @brief       Markers Submit Controller
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       5 Mar 2016
 * @version     -storm_version-
 */

namespace IPS\membermap\modules\front\markers;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Submit Event Controller
 */
class _submit extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute(): void
	{
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=showmap', 'front', 'membermap' ), \IPS\Member::loggedIn()->language()->addToStack( 'module__membermap_membermap' ) );
		\IPS\Output::i()->breadcrumb = array_reverse( \IPS\Output::i()->breadcrumb );

		/* Get enabled maps */
		$defaultMaps = \IPS\membermap\Application::getEnabledMaps();

		/* Load JS and CSS */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/leaflet.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-ui.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet-providers.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet.awesome-markers.js', 'membermap', 'interface' ) );

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.css', 'membermap', 'global' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'jquery-ui.css', 'membermap', 'global' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'membermap.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.awesome-markers.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.markercluster.css', 'membermap' ) );


		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_markers.js', 'membermap', 'front' ) );

		\IPS\Output::i()->jsVars['membermap_defaultMaps'] = $defaultMaps;


		parent::execute();
	}

	/**
	 * Submit Event
	 *
	 * @return	void
	 */
	protected function manage(): void
	{
		$group = NULL;
		if ( isset( \IPS\Request::i()->group ) )
		{
			try
			{
				$group = \IPS\membermap\Markers\Groups::loadAndCheckPerms( \IPS\Request::i()->group );
				\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=membermap&module=groups&controller=groups&id=' . $group->_id, 'front', 'markers_group', $group->name_seo ), $group->_title );
			}
			catch ( \OutOfRangeException $e ) { }
		}

		$form = \IPS\membermap\Markers\Markers::create( $group );
		
		if ( \IPS\membermap\Markers\Markers::moderateNewItems( \IPS\Member::loggedIn() ) )
		{
			$form = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->modQueueMessage( \IPS\Member::loggedIn()->warnings( 5, NULL, 'mq' ), \IPS\Member::loggedIn()->mod_posts ) . $form;
		}

		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'membermap_submit_a_marker' );
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'membermap_submit_a_marker' ) );

		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'submit' )->submitPage( $form->customTemplate( array( \call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'submit', 'membermap' ) ), 'submitForm' ) ) );
	}
}