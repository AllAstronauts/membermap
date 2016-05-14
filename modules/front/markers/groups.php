<?php


namespace IPS\membermap\modules\front\markers;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
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
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=membermap', 'front', 'membermap' ), \IPS\Member::loggedIn()->language()->addToStack( 'module__membermap_membermap' ) );
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
		}

		/* Online User Location */
		$permissions = $group->permissions();
		\IPS\Session::i()->setLocation( $group->url(), explode( ",", $permissions['perm_view'] ), 'loc_membermap_viewing_group', array( "membermap_group_{$group->id}" => TRUE ) );

		/* Output */
		\IPS\Output::i()->title		= $group->_title;

		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'markers' )->group( $group, (string) $table );
	}

	protected function _index()
	{
		/* Online User Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=membermap&module=markers&controller=groups', 'front', 'markers' ), array(), 'loc_membermap_browsing_groups' );
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('membermap_marker_groups');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'markers' )->index();
	}
	
	// Create new methods with the same name as the 'do' parameter which should execute it
}