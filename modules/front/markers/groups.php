<?php
/**
 * @brief       Member Map Groups
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       28 Feb 2016
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
 * Groups
 */
class _groups extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\membermap\Markers\Groups';

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=showmap', 'front', 'membermap' ), \IPS\Member::loggedIn()->language()->addToStack( 'module__membermap_membermap' ) );
		\IPS\Output::i()->breadcrumb = array_reverse( \IPS\Output::i()->breadcrumb );

		parent::execute();
	}

	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( isset( \IPS\Request::i()->id ) )
		{
			try
			{
				$this->_group( \IPS\membermap\Markers\Groups::loadAndCheckPerms( \IPS\Request::i()->id, 'view' ) );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2MM2/1', 404, '' );
			}
		}
		else
		{
			$this->_index();
		}
	}

	/**
	 * Show marker group
	 * 
	 * @param  \IPS\membermap\Markers\Group $group Group object
	 * @return void
	 */
	protected function _group( $group )
	{
		$_count = \IPS\membermap\Markers\Markers::getItemsWithPermission( array( array( \IPS\membermap\Markers\Markers::$databasePrefix . \IPS\membermap\Markers\Markers::$databaseColumnMap['container'] . '=?', $group->_id ) ), NULL, 1, 'read', NULL, 0, NULL, FALSE, FALSE, FALSE, TRUE );

		if( ! $_count )
		{
			/* Show a 'no files' template if there's nothing to display */
			$table = \IPS\Theme::i()->getTemplate( 'markers' )->noMarkers( $group );
		}
		else
		{
			/* Build table */
			$table = new \IPS\Helpers\Table\Content( '\IPS\membermap\Markers\Markers', $group->url(), NULL, $group );
			$table->classes = array( 'ipsDataList_large' );
			$table->title = \IPS\Member::loggedIn()->language()->pluralize(  \IPS\Member::loggedIn()->language()->get( 'group_markers_number' ), array( $_count ) );
			
			$filterOptions = array(
				'all'	=> 'all_markers',
			);

			$timeFrameOptions = array(
				'show_all'			=> 'show_all',
				'today'				=> 'today',
				'last_5_days'		=> 'last_5_days',
				'last_7_days'		=> 'last_7_days',
				'last_10_days'		=> 'last_10_days',
				'last_15_days'		=> 'last_15_days',
				'last_20_days'		=> 'last_20_days',
				'last_25_days'		=> 'last_25_days',
				'last_30_days'		=> 'last_30_days',
				'last_60_days'		=> 'last_60_days',
				'last_90_days'		=> 'last_90_days',
			);

			/* Are we a moderator? */
			if( \IPS\membermap\Markers\Markers::modPermission( 'unhide', NULL, $group ) )
			{
				$filterOptions['queued_markers']	= 'queued_markers';
			}

			$table->advancedSearch = array(
				'marker_type'	=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => $filterOptions ) ),
				'sort_direction'=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => array(
					'asc'			=> 'asc',
					'desc'			=> 'desc',
					) )
				),
				'marker_time_frame'	=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => $timeFrameOptions ) ),
			);
			$table->advancedSearchCallback = function( $table, $values )
			{
				/* Type */
				switch ( $values['marker_type'] )
				{
					case 'starter':
						$table->where[] = array( 'starter_id=?', \IPS\Member::loggedIn()->member_id );
						break;
					case 'queued_markers':
						$table->where[] = array( '(marker_open=0 OR marker_open=-1)' );
						break;
				}

				/* Cutoff */
				$days = NULL;
				
				if ( isset( $values['marker_time_frame'] ) )
				{
					switch ( $values['marker_time_frame'] )
					{
						case 'today':
							$days = 1;
							break;
						case 'last_5_days':
							$days = 5;
							break;
						case 'last_7_days':
							$days = 7;
							break;
						case 'last_10_days':
							$days = 10;
							break;
						case 'last_15_days':
							$days = 15;
							break;
						case 'last_20_days':
							$days = 20;
							break;
						case 'last_25_days':
							$days = 25;
							break;
						case 'last_30_days':
							$days = 30;
							break;
						case 'last_60_days':
							$days = 60;
							break;
						case 'last_90_days':
							$days = 90;
							break;
						case 'since_last_visit':
							$table->where[] = array( 'marker_updated>?', \IPS\Member::loggedIn()->last_visit );
							break;
					}
				}

				if ( $days !== NULL )
				{
					$table->where[] = array( 'marker_updated>?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $days . 'D' ) )->getTimestamp() );
				}
			};
		}

		/* Online User Location */
		$permissions = $group->permissions();
		\IPS\Session::i()->setLocation( $group->url(), explode( ",", $permissions['perm_view'] ), 'loc_membermap_viewing_group', array( "membermap_marker_group_{$group->id}" => TRUE ) );

		/* Show advanced search form */
		if ( isset( \IPS\Request::i()->advancedSearchForm ) )
		{
			\IPS\Output::i()->output = (string) $table;
			return;
		}

		/* Output */
		\IPS\Output::i()->title		= $group->_title;

		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'markers' )->group( $group, (string) $table );
	}

	/**
	 * Show marker group index
	 * 
	 * @return void
	 */
	protected function _index()
	{
		/* Online User Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=membermap&module=markers&controller=groups', 'front', 'markers' ), array(), 'loc_membermap_browsing_groups' );
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('membermap_marker_groups');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'markers' )->index();
	}
}