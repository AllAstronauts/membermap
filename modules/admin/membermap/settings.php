<?php


namespace IPS\membermap\modules\admin\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * settings
 */
class _settings extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'settings_manage' );
		parent::execute();
	}

	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__tripreport_tripreport_settings');

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-ui.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'jquery-ui.css', 'membermap', 'global' ) );

		$form = new \IPS\Helpers\Form;
		$form->attributes['data-controller'] 	= 'membermap.admin.membermap.settings';
		$form->attributes['id'] 				= 'membermap_form_settings';

		$form->addHeader('map_settings');

		$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_enable_clustering', \IPS\Settings::i()->membermap_enable_clustering ) );
		$form->add( new \IPS\Helpers\Form\Text( 'membermap_bbox_location', \IPS\Settings::i()->membermap_bbox_location, FALSE, array(), NULL, NULL, NULL, 'membermap_bbox_location' ) );

		$form->hiddenValues['membermap_bbox'] = \IPS\Settings::i()->membermap_bbox;
		



		if ( $values = $form->values() )
		{
			$values['membermap_bbox'] = \IPS\Request::i()->membermap_bbox;

			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplogs__tripreport_settings' );
		}
		

		\IPS\Output::i()->output = $form;
	}
	
	// Create new methods with the same name as the 'do' parameter which should execute it
}