<?php
/**
 * @brief		Custom Markers Controller
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
		/* Build form */
		$form = new \IPS\Helpers\Form( NULL, 'import' );
		if ( isset( \IPS\Request::i()->id ) )
		{
			$group = \IPS\membermap\Markers\Groups::load( intval( \IPS\Request::i()->id ) );

			if ( $group->type == 'member' )
			{
				\IPS\Output::i()->error( 'generic_error', '1', 403, '' );
			}

			$form->hiddenValues['id'] = \IPS\Request::i()->id;
		}
		else
		{
			\IPS\Output::i()->error( 'generic_error', '2', 403, '' );
		}

		$form->add( new \IPS\Helpers\Form\Upload( 'import_upload', NULL, TRUE, array( 'allowedFileTypes' => array( 'kml' ), 'temporary' => TRUE ) ) );
		$activeTabContents = $form;
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Already installed? */
			try
			{
				$xml = \IPS\Xml\SimpleXML::loadFile( $values['import_upload'] );
			}
			catch ( \InvalidArgumentException $e ) 
			{
				\IPS\Output::i()->error( 'xml_upload_invalid', '3', 403, '' );
			}

				$markers = array();
			foreach( $xml->Document->Folder as $folder )
			{
				if( ! isset( $folder->Placemark ) )
				{
					continue;
				}

				$folderName = $folder->Name;

				foreach( $folder->Placemark as $placemark )
				{
					if ( ! isset( $placemark->Point ) )
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
					);
				}
			}

			debug( count( $markers ), $markers );
		}
		
		/* Display */
		\IPS\Output::i()->output = $form;
	}
}