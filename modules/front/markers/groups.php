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
				\IPS\Output::i()->error( 'node_error', '2D175/1', 404, '' );
			}
		}
		else
		{
			$this->_index();
		}
	}

	protected function _group( $group )
	{
		/* Build table */
		$table = new \IPS\Helpers\Table\Content( '\IPS\membermap\Markers\Markers', $group->url(), NULL, $group );
		$table->classes = array( 'ipsDataList_large' );

		\IPS\Output::i()->output	= $table;
	}

	protected function _index()
	{
		/* Online User Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=membermap&module=markers&controller=groups', 'front', 'markers' ), array(), 'loc_classifieds_browsing_categories' );
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('membermap');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'markers' )->index();
	}
	
	// Create new methods with the same name as the 'do' parameter which should execute it
}