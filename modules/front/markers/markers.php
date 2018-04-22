<?php
/**
 * @brief       Member Map Markers
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       28 Feb 2016
 * @version     -storm_version-
 */

namespace IPS\membermap\modules\front\markers;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * markers
 */
class _markers extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\membermap\Markers\Markers';

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		try
		{
			$this->marker = \IPS\membermap\Markers\Markers::loadAndCheckPerms( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2MM1/1', 404, '' );
		}

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
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.control.fullscreen.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.control.loading.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.awesome-markers.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.contextmenu.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.markercluster.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'jq.showloading.css', 'membermap' ) );


		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_markers.js', 'membermap', 'front' ) );

		\IPS\Output::i()->jsVars['membermap_defaultMaps'] = $defaultMaps;
		
		parent::execute();
	}

	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Init */
		parent::manage();

		/* Sort out comments and reviews */
		$tabs = $this->marker->commentReviewTabs();
		$_tabs = array_keys( $tabs );
		$tab = isset( \IPS\Request::i()->tab ) ? \IPS\Request::i()->tab : array_shift( $_tabs );
		$activeTabContents = $this->marker->commentReviews( $tab );
		
		if ( count( $tabs ) > 1 )
		{
			$commentsAndReviews = count( $tabs ) ? \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $tab, $activeTabContents, $this->marker->url(), 'tab', FALSE, TRUE ) : NULL;
		}
		else
		{
			$commentsAndReviews = $activeTabContents;
		}

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}

		if( $this->marker->container()->allow_reviews )
		{
			\IPS\Output::i()->jsonLd['marker']['aggregateRating'] = array(
				'@type'			=> 'AggregateRating',
				'reviewCount'	=> $this->marker->mapped( 'num_reviews' ),
				'ratingValue'	=> $this->marker->averageReviewRating(),
				'bestRating'	=> \IPS\Settings::i()->reviews_rating_out_of,
			);

			\IPS\Output::i()->jsonLd['marker']['interactionStatistic'][]	= array(
				'@type'					=> 'InteractionCounter',
				'interactionType'		=> "http://schema.org/ReviewAction",
				'userInteractionCount'	=> $this->marker->mapped( 'num_reviews' ),
			);
		}

		if( $this->marker->container()->allow_comments )
		{
			\IPS\Output::i()->jsonLd['marker']['interactionStatistic'][] = array(
				'@type'					=> 'InteractionCounter',
				'interactionType'		=> "http://schema.org/CommentAction",
				'userInteractionCount'	=> $this->marker->mapped( 'num_comments' )
			);

			\IPS\Output::i()->jsonLd['marker']['commentCount'] = $this->marker->mapped( 'num_comments' );
		}


		\IPS\Session::i()->setLocation( $this->marker->url(), $this->marker->onlineListPermissions(), 'loc_membermap_viewing_marker', array( $this->marker->title => FALSE ) );

		/* Display */
		\IPS\Output::i()->title		= $this->marker->_title . ' - ' . $this->marker->container()->_title;
		\IPS\Output::i()->sidebar['sticky'] = TRUE;
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'markers' )->viewMarker( $this->marker, $commentsAndReviews );

	}
	
	/**
	 * Show edit form
	 * 
	 * @return void
	 */
	protected function edit()
	{
		if ( !$this->marker->canEdit() and !\IPS\Request::i()->form_submitted ) // We check if the form has been submitted to prevent the user loosing their content
		{
			\IPS\Output::i()->error( 'edit_no_perm_err', '2MM1/2', 403, '' );
		}

		$form = $this->marker->buildEditForm();

		if ( $values = $form->values() )
		{
			if ( $this->marker->canEdit() )
			{				
				$this->marker->processForm( $values );

				/* Old custom markers did not store the author ID, update them now to the current member */
				if( $this->marker->member_id == 0 )
				{
					$this->marker->member_id = \IPS\Member::loggedIn()->member_id;
				}

				$this->marker->updated = time();
				$this->marker->save();
				$this->marker->processAfterEdit( $values );
	
				\IPS\Output::i()->redirect( $this->marker->url() );
			}
			else
			{
				$form->error = \IPS\Member::loggedIn()->language()->addToStack('edit_no_perm_err');
			}
		}

		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'membermap_edit_a_marker' );
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'membermap_edit_a_marker' ) );

		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'submit' )->submitPage( $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'submit', 'membermap' ) ), 'submitForm' ) ) );
	}
}