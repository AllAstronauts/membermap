<?php
/**
 * @brief		Marker Groups Controller
 * @author		<a href='http://ipb.silvesterwebdesigns.com'>Stuart Silvester & Martin Aronsen</a>
 * @copyright	(c) 2015 Stuart Silvester & Martin Aronsen
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		20 Oct 2015
 * @version		3.0.1
 */

namespace IPS\membermap\modules\admin\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * markers
 */
class _markers extends \IPS\Node\Controller
{
	/**
	 * Node Class
	 */
	protected $nodeClass = '\IPS\membermap\Markers\Groups';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'markers_manage' );

		parent::execute();
	}

	/**
	 * Get Root Buttons
	 *
	 * @return	array
	 */
	public function _getRootButtons()
	{
		$nodeClass = $this->nodeClass;
		$buttons   = array();

		$buttons['import'] = array(
			'icon'	=> 'upload',
			'title'	=> 'membermap_import',
			'link'	=> \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=markers&do=import' ),
			'data'  => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('membermap_import') )
		);

		$buttons['add_group'] = array(
			'icon'	=> 'group',
			'title'	=> 'membermap_add_group',
			'link'	=> \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=markers&do=form' ),
			'data'  => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('membermap_add_group') )
		);

		return $buttons;
	}

	/**
	 * Show the pages tree
	 *
	 * @return	string
	 */
	protected function manage()
	{
		/* Javascript & CSS */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_membermap.js', 'membermap', 'admin' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'membermap.css', 'membermap' ) );
		
		return parent::manage();
	}

	public function import()
	{
		$id = isset( \IPS\Request::i()->id ) ? intval( \IPS\Request::i()->id ) : 0;

		/* Build form */
		$form = new \IPS\Helpers\Form( NULL, 'import' );
		if ( isset( \IPS\Request::i()->id ) )
		{
			$group = \IPS\membermap\Markers\Groups::load( intval( \IPS\Request::i()->id ) );

			if ( $group->type == 'member' )
			{
				\IPS\Output::i()->error( 'generic_error', '1MM4/1', 403, '' );
			}
		}

		$form->add( new \IPS\Helpers\Form\Upload( 'import_upload', NULL, TRUE, array( 'allowedFileTypes' => array( 'kml' ), 'temporary' => TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'import_creategroups', FALSE, FALSE, array( 'togglesOff' => array( 'import_group' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'import_group', $id ?: 0, FALSE, array(
			'class'				=> '\IPS\membermap\Markers\Groups',
			'permissionCheck' 	=> 'add',
			'subnodes'			=> false,
			'where'				=> array( 'group_type != ?', 'member' ),
		), NULL, NULL, NULL, 'import_group' ) );


		if ( $values = $form->values() )
		{
			try
			{
				$xml = \IPS\Xml\SimpleXML::loadFile( $values['import_upload'] );
			}
			catch ( \InvalidArgumentException $e ) 
			{
				$form->error 				= \IPS\Member::loggedIn()->language()->addToStack( 'xml_upload_invalid' );
				\IPS\Output::i()->output 	= $form;
				return;
			}

			/* No group selected, and don't create groups?! */
			if ( $values['import_creategroups'] == FALSE AND ! $values['import_group'] )
			{
				$form->error 				= \IPS\Member::loggedIn()->language()->addToStack( 'membermap_error_no_id_no_create' );
				\IPS\Output::i()->output 	= $form;
				return;
			}

			$markers 	= array();
			$groupOrder = NULL;
			$imported	= 0;

			foreach( $xml->Document->Folder as $folder )
			{
				if( ! isset( $folder->Placemark ) )
				{
					continue;
				}

				$folderName = (string) $folder->name;

				foreach( $folder->Placemark as $placemark )
				{
					if ( ! isset( $placemark->Point->coordinates ) )
					{
						continue;
					}

					list( $lon, $lat, $elev ) = explode( ',', $placemark->Point->coordinates );

					$markers[] = array(
						'marker_name'			=> (string) $placemark->name,
						'marker_name_seo'		=> \IPS\Http\Url::seoTitle( (string) $placemark->name ),
						'marker_description'	=> (string) $placemark->description,
						'marker_lat'			=> $lat,
						'marker_lon'			=> $lon,
						'marker_member_id'		=> \IPS\Member::loggedIn()->member_id,
						'marker_added'			=> time(),
						'marker_open'			=> 1,
						'marker_parent_id'		=> isset( $values['import_group'] ) ? $values['import_group']->id : NULL,
					);
				}

				/* Create a new group per "folder" */
				if ( $values['import_creategroups'] == TRUE AND count( $markers ) > 0 )
				{
					if ( $groupOrder === NULL )
					{
						$groupOrder = \IPS\Db::i()->select( array( "MAX( `group_position` ) as position" ), 'membermap_markers_groups' )->first();
					}

					$groupOrder = $groupOrder + 1;

					$group 						= new \IPS\membermap\Markers\Groups;
					$group->name 				= $folderName;
					$group->name_seo 			= \IPS\Http\Url::seoTitle( $folderName );
					$group->type 				= 'custom';
					$group->pin_colour 			= '#FFFFFF';
					$group->pin_bg_colour 		= 'red';
					$group->pin_icon 			= 'fa-globe';
					$group->position 			= $groupOrder;

					$group->save();

					\IPS\Lang::saveCustom( 'membermap', "membermap_marker_group_{$group->id}", trim( $folderName ) );
					\IPS\Lang::saveCustom( 'membermap', "membermap_marker_group_{$group->id}_JS", trim( $folderName ), 1 );

					// Add group id to all elements of the array
					array_walk( $markers, function( &$v, $k ) use ( $group )
					{
						$v['marker_parent_id'] = $group->id;
					} );

					// Insert
					\IPS\Db::i()->insert( 'membermap_markers', $markers );

					$group->setLastComment();
					$group->save();

					// Set default permissions
					$perms = $group->permissions();
					\IPS\Db::i()->update( 'core_permission_index', array(
						'perm_view'	 => '*',
						'perm_2'	 => '*',  #read
						'perm_3'     => \IPS\Settings::i()->admin_group,  #add
					    'perm_4'     => \IPS\Settings::i()->admin_group,  #edit
					), array( 'perm_id=?', $perms['perm_id'] ) );

					// Reset
					$imported	+= count( $markers );
					$markers 	= array();
				}
			}

			/* If we still got markers here, it's all pushed to one group, probably */
			if ( is_array( $markers ) AND count( $markers ) > 0 )
			{
				\IPS\Db::i()->insert( 'membermap_markers', $markers );

				$group = $values['import_group'];
				$group->setLastComment();
				$group->save();
				
				$imported	+= count( $markers );
			}
			
			\IPS\membermap\Map::i()->invalidateJsonCache();

			$message = \IPS\Member::loggedIn()->language()->addToStack( 'membermap_import_thumbup', FALSE, array( 'sprintf' => array( $imported ) ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=membermap&module=membermap&controller=markers" ), $message );
		}
		
		/* Display */
		\IPS\Output::i()->output = $form;
	}
}