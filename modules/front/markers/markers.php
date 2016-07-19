<?php


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

		\IPS\Output::i()->breadcrumb[] = array( $this->marker->container()->url(), $this->marker->container()->_title );

		\IPS\Output::i()->breadcrumb[] = array( '', $this->marker->_title );


		/* Load JS */
		\IPS\membermap\Application::getJsForMarkerForm();
		
		parent::execute();
	}

	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Display */
		\IPS\Output::i()->title		= $this->marker->_title . ' - ' . $this->marker->container()->_title;
		\IPS\Output::i()->sidebar['sticky'] = TRUE;
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'markers' )->viewMarker( $this->marker );

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