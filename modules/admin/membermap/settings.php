<?php
/**
 * @brief       Settings Controller
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       20 Oct 2015
 * @version     -storm_version-
 */

namespace IPS\membermap\modules\admin\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
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
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__membermap_membermap_settings');

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-ui.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_membermap.js', 'membermap', 'admin' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'jquery-ui.css', 'membermap', 'global' ) );

		$form = new \IPS\Helpers\Form;

		/* API Key */
		$form->addTab( 'membermap_settings_tab_general' );
		$form->addHeader('api_settings');
		$form->add( new \IPS\Helpers\Form\Text( 'membermap_mapQuestAPI', \IPS\Settings::i()->membermap_mapQuestAPI, TRUE, array(), NULL, NULL, NULL, 'membermap_mapQuestAPI' ) );

		$countries = array( '*' => "membermap_noRestriction" );;
		foreach( \IPS\GeoLocation::$countries as $c )
		{
			$countries[ $c ] = "country-{$c}";
		}
		
		$form->add( 
			new \IPS\Helpers\Form\Select( 'membermap_restrictCountries', 
				\IPS\Settings::i()->membermap_restrictCountries != '' ? 
					( \IPS\Settings::i()->membermap_restrictCountries === '*' ? '*' : explode( ",", \IPS\Settings::i()->membermap_restrictCountries ) ) 
					: '*', 
				FALSE, array( 'options' => $countries, 'multiple' => TRUE ) 
			) 
		);

		if ( ! empty( \IPS\Settings::i()->membermap_mapQuestAPI ) )
		{
			/* Map Settings */
			$form->attributes['data-controller'] 	= 'membermap.admin.membermap.settings';
			$form->attributes['id'] 				= 'membermap_form_settings';

			$form->addHeader('map_settings');
			$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_groupByMemberGroup', \IPS\Settings::i()->membermap_groupByMemberGroup ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_enable_clustering', \IPS\Settings::i()->membermap_enable_clustering ) );
			$form->hiddenValues['membermap_bbox'] = \IPS\Settings::i()->membermap_bbox;
			$form->add( new \IPS\Helpers\Form\Text( 'membermap_bbox_location', \IPS\Settings::i()->membermap_bbox_location, FALSE, array(), NULL, NULL, NULL, 'membermap_bbox_location' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'membermap_bbox_zoom', \intval( \IPS\Settings::i()->membermap_bbox_zoom ), FALSE, array( 'min' => 1, 'max' => 18 ) ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_showNightAndDay', \IPS\Settings::i()->membermap_showNightAndDay ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_showMemberList', \IPS\Settings::i()->membermap_showMemberList ) );


			/* Profile Synchronization */
			$form->addTab( 'membermap_settings_tab_profile' );
			$form->addHeader( 'membermap_autoUpdate' );

			$profileFields = array( '' => ' -- ' . \IPS\Member::loggedIn()->language()->addToStack( 'membermap_profileLocationField' ) . ' -- ' );
			foreach ( \IPS\core\ProfileFields\Field::fieldData() as $group => $fields )
			{
				foreach ( $fields as $id => $field )
				{
					$field = \IPS\core\ProfileFields\Field::constructFromData( $field )->buildHelper();
					
					$profileFields[ 'core_pfieldgroups_' . $group ][ $id ] = $field->name;
				}
			}

			$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_monitorLocationField', \IPS\Settings::i()->membermap_monitorLocationField, FALSE, 
				array( 'togglesOn' => array( 'membermap_profileLocationField', 'membermap_monitorLocationField_groupPerm', 'membermap_syncLocationField' ) ) 
			) );

			$value = \IPS\Settings::i()->membermap_profileLocationField ? explode( ',', \IPS\Settings::i()->membermap_profileLocationField ) : NULL;
			$value = \is_array( $value ) ? array_map( 'intval', $value ) : $value;

			$form->add( new \IPS\Helpers\Form\Stack( 
				'membermap_profileLocationField',
				$value, 
				FALSE, array( 'stackFieldType' => 'Select', 'options' => $profileFields ), NULL, NULL, NULL, 'membermap_profileLocationField' 
			) );

			$form->add( new \IPS\Helpers\Form\Select(
				'membermap_monitorLocationField_groupPerm',
				\IPS\Settings::i()->membermap_monitorLocationField_groupPerm != '' ? ( \IPS\Settings::i()->membermap_monitorLocationField_groupPerm === '*' ? '*' : explode( ",", \IPS\Settings::i()->membermap_monitorLocationField_groupPerm ) ) : '*',
				FALSE,array( 'options' => \IPS\Member\Group::groups(), 'multiple' => TRUE, 'parse' => 'normal', 'unlimited' => '*', 'unlimitedLang' => 'all' ), NULL, NULL, NULL, 'membermap_monitorLocationField_groupPerm'
			) );

			$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_syncLocationField', \IPS\Settings::i()->membermap_syncLocationField, FALSE, array(), NULL, NULL, NULL, 'membermap_syncLocationField' ) );


			/* Get from extensions */
			$extensions = \IPS\Application::allExtensions( 'membermap', 'Mapmarkers', FALSE );

			foreach ( $extensions as $k => $class )
			{
				if ( method_exists( $class, 'getSettings' ) )
				{	
					$class->getSettings( $form );
				}
			}

		}

		if ( $values = $form->values( TRUE ) )
		{
			$values['membermap_bbox'] = \IPS\Request::i()->membermap_bbox;

			if ( empty( $values['membermap_bbox_location'] ) )
			{
				$values['membermap_bbox'] = "";
			}

			\IPS\DB::i()->update( 'core_tasks', array( 'enabled' => isset( $values['membermap_syncLocationField'] ) AND $values['membermap_syncLocationField'] ? 1 : 0 ), array( '`key`=?', 'locationSync' ) );

			if ( mb_strpos( $values['membermap_restrictCountries'], '*' ) !== FALSE AND mb_strlen( $values['membermap_restrictCountries'] ) >= 3 )
			{
				$values['membermap_restrictCountries'] = str_replace( '*,', '', $values['membermap_restrictCountries'] );
			}

			$form->saveAsSettings( $values );
			\IPS\membermap\Map::i()->invalidateJsonCache();
			
			\IPS\Session::i()->log( 'acplogs__membermap_settings' );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=membermap&module=membermap&controller=settings" ), 'saved' );
		}
		
		\IPS\Output::i()->output = $form;
	}

	protected function mapquestSearch()
	{
		$location 	= \IPS\Request::i()->q;
		$data 		= array();
		
		if ( $location )
		{
			$apiKey = \IPS\membermap\Application::getApiKeys( 'mapquest' );
			try
			{
				$data = \IPS\Http\Url::external( "https://open.mapquestapi.com/nominatim/v1/search.php?key={$apiKey}&format=json&q=" . urlencode( $location ) )->request( 15 )->get()->decodeJson();
			}
			catch( \Exception $e ) 
			{
				\IPS\Log::log( $e, 'membermap' );
			}
		}
		
		\IPS\Output::i()->json( $data );	
	}

	/**
	 * Re-process all members
	 * @return void
	 */
	protected function resetMemberSync()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete( 'membermap_resetmembersync', 'membermap_resetmembersync_desc', 'continue' );

		\IPS\Db::i()->update( 'core_members', "membermap_location_synced=0" );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=membermap&module=membermap&controller=settings" ), 'saved' );
	}
}