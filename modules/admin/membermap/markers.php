<?php
/**
 * @brief       Marker Groups Controller
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
		$id = isset( \IPS\Request::i()->id ) ? \intval( \IPS\Request::i()->id ) : 0;

		/* Build form */
		$form = new \IPS\Helpers\Form( NULL, 'import' );
		if ( isset( \IPS\Request::i()->id ) )
		{
			$group = \IPS\membermap\Markers\Groups::load( \intval( \IPS\Request::i()->id ) );

			if ( $group->type == 'member' )
			{
				\IPS\Output::i()->error( 'generic_error', '1MM4/1', 403, '' );
			}
		}

		$form->add( new \IPS\Helpers\Form\Upload( 'import_upload', NULL, TRUE, array( 'allowedFileTypes' => array( 'kml', 'kmz' ), 'temporary' => TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'import_creategroups', FALSE, FALSE, array( 'togglesOff' => array( 'import_group' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'import_group', $id ?: 0, FALSE, array(
			'class'				=> '\IPS\membermap\Markers\Groups',
			'permissionCheck' 	=> 'add',
			'subnodes'			=> false,
			'where'				=> array( 'group_type != ?', 'member' ),
		), NULL, NULL, NULL, 'import_group' ) );


		if ( $values = $form->values() )
		{
			/* If this is a KMZ file we need to unzip it first */
			try
			{
				if ( mb_substr( $values['import_upload'], -4 ) !== '.kmz' )
				{
					/* If rename fails on a significant number of customer's servers, we might have to consider using
						move_uploaded_file into uploads and rename in there */
					rename( $values['import_upload'], $values['import_upload'] . ".kmz" );
					
					$values['import_upload'] .= ".kmz";
				}

				/* Test the phar */
				$_xml = new \PharData( $values['import_upload'], \Phar::CURRENT_AS_FILEINFO | \Phar::KEY_AS_FILENAME );

				foreach ( new \RecursiveIteratorIterator( $_xml ) as $file )
				{
					if ( mb_substr( $file->getFileName(), -4 ) === '.kml' )
					{
						$kmlFile = $file->getPathName();
						break;
					}
				}
			}
			catch( \UnexpectedValueException $e )
			{
				/* Nope, it's not a KMZ file */
				$kmlFile = $values['import_upload'];
			}
			catch( \PharException $e )
			{
				$form->error 				= \IPS\Member::loggedIn()->language()->addToStack( 'xml_upload_invalid' );
				\IPS\Output::i()->output 	= $form;
				return;
			}

			/* Try loading the KML */
			try
			{	
				$kml = \IPS\Xml\SimpleXML::loadFile( $kmlFile );
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

			$this->_parseKml( $markers, ( $kml->Document ? $kml->Document : $kml ) , '' );

			foreach( $markers as $folder )
			{
				if ( \count( $folder['markers'] ) > 0 )
				{
					/* Create a new group per "folder" */
					if ( $values['import_creategroups'] == TRUE )
					{
						if ( $groupOrder === NULL )
						{
							$groupOrder = \IPS\Db::i()->select( array( "MAX( `group_position` ) as position" ), 'membermap_markers_groups' )->first();
						}

						$groupOrder = $groupOrder + 1;

						$group 						= new \IPS\membermap\Markers\Groups;
						$group->name 				= $folder['name'];
						$group->name_seo 			= \IPS\Http\Url\Friendly::seoTitle( $group->name );
						$group->type 				= 'custom';
						$group->pin_colour 			= '#FFFFFF';
						$group->pin_bg_colour 		= 'red';
						$group->pin_icon 			= 'fa-globe';
						$group->position 			= $groupOrder;

						$group->save();

						// Set default permissions
						/* Add in permissions */
						$groups	= array_filter( iterator_to_array( \IPS\Db::i()->select( 'g_id', 'core_groups' ) ), function( $groupId ) 
						{
							if( $groupId == \IPS\Settings::i()->guest_group )
							{
								return FALSE;
							}

							return TRUE;
						});

						$default = implode( ',', $groups );

						\IPS\Db::i()->insert( 'core_permission_index', array(
							 'app'			=> 'membermap',
							 'perm_type'	=> 'membermap',
							 'perm_type_id'	=> $group->id,
							 'perm_view'	=> '*', # view
							 'perm_2'		=> '*', # read
							 'perm_3'		=> $default, # add
							 'perm_4'		=> $default, # comment
							 'perm_5'		=> $default, # review
						) );

						\IPS\Lang::saveCustom( 'membermap', "membermap_marker_group_{$group->id}", trim( $group->name ) );
						\IPS\Lang::saveCustom( 'membermap', "membermap_marker_group_{$group->id}_JS", trim( $group->name ), 1 );


						// Add group id to all elements of the array
						array_walk( $folder['markers'], function( &$v, $k ) use ( $group )
						{
							$v['marker_parent_id'] = $group->id;
						} );

					}
					elseif ( $values['import_group'] )
					{
						array_walk( $folder['markers'], function( &$v, $k ) use ( $values )
						{
							$v['marker_parent_id'] = $values['import_group']->id;
						} );

						$group = $values['import_group'];
					}

					/* Split the group into smaller chunks to prevent too large insert queries */
					foreach( array_chunk( $folder['markers'], 100, TRUE ) as $chunks )
					{
						\IPS\Db::i()->insert( 'membermap_markers', $chunks );
					}

					\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\membermap\Markers\Markers', 'container' => $group->id ), 5, 'container' );

					$group->setLastComment();
					$group->save();
					
					$imported	+= \count( $folder['markers'] );
				}
			}
			
			\IPS\membermap\Map::i()->invalidateJsonCache();

			$message = \IPS\Member::loggedIn()->language()->addToStack( 'membermap_import_thumbup', FALSE, array( 'sprintf' => array( $imported ) ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=membermap&module=membermap&controller=markers" ), $message );
		}
		
		/* Display */
		\IPS\Output::i()->output = $form;
	}

	protected function _parseKml( &$markers, $_folder, $prevFolderName='' )
	{
		if ( isset( $_folder->Folder ) )
		{
			foreach( $_folder->Folder as $folder )
			{
				$folderName = isset( $folder->name ) ? (string) $folder->name : '';

				if ( isset( $folder->Folder ) AND ! isset( $folder->Placemark ) AND ( \is_object( $folder->Folder ) OR \is_array( $folder->Folder ) ) )
				{
					$this->_parseKml( $markers, $folder, $folderName );
				}
				else
				{
					if (  $folder->Placemark  )
					{
						$prevFolderName = $prevFolderName ?: $folderName;

						$this->_parseKmlPlacemark( $markers, $folder, $prevFolderName );
					}
				}
			}
		}
		else if ( $_folder->Placemark )
		{
			$this->_parseKmlPlacemark( $markers, $_folder, 'New group' );
		}
	}

	protected function _parseKmlPlacemark( &$markers, $folder, $prevFolderName )
	{
		foreach( $folder->Placemark as $placemark )
		{
			if ( ! isset( $placemark->Point->coordinates ) )
			{
				continue;
			}

			if ( ! isset( $markers[ \substr( md5( $prevFolderName ), 0, 10 ) ] ) )
			{
				$markers[ \substr( md5( $prevFolderName ), 0, 10 ) ]  = array( 'name' => $prevFolderName, 'markers' => array() );
			}

			list( $lon, $lat, $elev ) = explode( ',', $placemark->Point->coordinates );

			$markers[ \substr( md5( $prevFolderName ), 0, 10 ) ]['markers'][] = array(
				'marker_name'			=> (string) $placemark->name,
				'marker_name_seo'		=> \IPS\Http\Url\Friendly::seoTitle( (string) $placemark->name ),
				'marker_description'	=> (string) $placemark->description,
				'marker_lat'			=> $lat,
				'marker_lon'			=> $lon,
				'marker_member_id'		=> \IPS\Member::loggedIn()->member_id,
				'marker_added'			=> time(),
				'marker_open'			=> 1,
			);
		}
	}
}