<?php
/**
 * @brief		Markers Submit Controller
 * @author		<a href='http://ipb.silvesterwebdesigns.com'>Stuart Silvester & Martin Aronsen</a>
 * @copyright	(c) 2015 Stuart Silvester & Martin Aronsen
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		5 Mar 2016
 * @version		3.0.3
 */

namespace IPS\membermap\modules\front\markers;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
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
	public function execute()
	{
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=membermap', 'front', 'membermap' ), \IPS\Member::loggedIn()->language()->addToStack( 'module__membermap_membermap' ) );
		\IPS\Output::i()->breadcrumb = array_reverse( \IPS\Output::i()->breadcrumb );
		parent::execute();
	}
	
	/**
	 * Submit Event
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'membermap_submit_a_marker' );
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'membermap_submit_a_marker' ) );
		
		$group = NULL;
		if ( isset( \IPS\Request::i()->group ) )
		{
			try
			{
				$group = \IPS\membermap\Markers\Groups::loadAndCheckPerms( \IPS\Request::i()->group );
			}
			catch ( \OutOfRangeException $e ) { }
		}

		$form = \IPS\membermap\Markers\Markers::create( $group );
		
		if ( \IPS\membermap\Markers\Markers::moderateNewItems( \IPS\Member::loggedIn() ) )
		{
			$form = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->modQueueMessage( \IPS\Member::loggedIn()->warnings( 5, NULL, 'mq' ), \IPS\Member::loggedIn()->mod_posts ) . $form;
		}

		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'submit' )->submitPage( $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'submit', 'membermap' ) ), 'submitForm' ) ) );
	}
}