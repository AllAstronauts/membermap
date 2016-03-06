<?php
/**
 * @brief		Custom Markers
 * @author		<a href='http://ipb.silvesterwebdesigns.com'>Stuart Silvester & Martin Aronsen</a>
 * @copyright	(c) 2015 Stuart Silvester & Martin Aronsen
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		20 Oct 2015
 * @version		3.0.0
 */

namespace IPS\membermap\Markers;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief Block Model
 */
class _Markers extends \IPS\Content\Item implements \IPS\Content\Permissions, \IPS\Content\Searchable, \IPS\Content\ReportCenter, \IPS\Content\Hideable
{
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief       Application
	 */
	public static $application = 'membermap';

	/**
	 * @brief       Module
	 */
	public static $module = 'membermap';


	protected static $defaultValues = NULL;

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'membermap_markers';

	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'marker_';

	/**
	 * @brief       Database Column Map
	 */
	public static $databaseColumnMap = array(
		'container'		=> 'parent_id',
		'author'		=> 'member_id',
		'title'			=> 'name',
		'content'		=> 'description',
		'date'			=> 'added',
		'updated'		=> 'updated',
		'approved'		=> 'open',
		'approved_by'	=> 'approver',
		'approved_date'	=> 'approvedon',
	);



	/**
	 * @brief       Node Class
	 */
	public static $containerNodeClass = 'IPS\membermap\Markers\Groups';

	/**
	 * @brief	Form Lang Prefix
	 */
	public static $formLangPrefix = 'marker_';


	/**
	 * @brief	[Node] Node Title
	 */
	public static $title = 'membermap_marker';


	/**
	 * @brief	Icon
	 */
	public static $icon = 'map-marker';


	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'membermap-marker';

	/**
	 * @brief	[Node] ACP Restrictions
	 * @code
	 	array(
	 		'app'		=> 'core',				// The application key which holds the restrictrions
	 		'module'	=> 'foo',				// The module key which holds the restrictions
	 		'map'		=> array(				// [Optional] The key for each restriction - can alternatively use "prefix"
	 			'add'			=> 'foo_add',
	 			'edit'			=> 'foo_edit',
	 			'permissions'	=> 'foo_perms',
	 			'delete'		=> 'foo_delete'
	 		),
	 		'all'		=> 'foo_manage',		// [Optional] The key to use for any restriction not provided in the map (only needed if not providing all 4)
	 		'prefix'	=> 'foo_',				// [Optional] Rather than specifying each  key in the map, you can specify a prefix, and it will automatically look for restrictions with the key "[prefix]_add/edit/permissions/delete"
	 * @endcode
	 */
	protected static $restrictions = array(
		'app'		=> 'membermap',
		'module'	=> 'membermap',
		'prefix' 	=> 'markers',
		'all'		=> 'markers_manage',
		'map'		=> array(				
	 			'add'			=> 'markers_add',
	 			'edit'			=> 'markers_edit',
	 			'delete'		=> 'markers_delete'
	 		),
	);

	/**
	 * @brief	Cached URLs
	 */
	protected $_url	= array();
	
	/**
	 * @brief	URL Base
	 */
	public static $urlBase = 'app=membermap&module=markers&controller=markers&id=';
	
	/**
	 * @brief	URL Base
	 */
	public static $urlTemplate = 'markers_marker';
	
	/**
	 * @brief	SEO Title Column
	 */
	public static $seoTitleColumn = 'name_seo';
	
	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'markers', 'membermap' ), 'rows' );
	}

	/**
	 * Get sortable name
	 *
	 * @return	string
	 */
	public function getSortableName()
	{
		return $this->name;
	}

	/**
	 * [Node] Get Title
	 *
	 * @return	string|null
	 */
	protected function get__title()
	{
		return $this->name;
	}

	/**
	 * Convert latLng to DMS (degrees, minutes, seconds)
	 * 
	 * @return string
	 */
	protected function get__latLngToDMS()
	{
		$lat = $this->_data['lat'];
		$lng = $this->_data['lon'];

		$NS = ( $lat >= 0 ) ? 'N' : 'S';
		$EW = ( $lng >= 0 ) ? 'E' : 'W';

		$lat 	= abs( $lat );
	    $lng 	= abs( $lng );
	    $latDeg = floor( $lat );
	    $latMin = floor( ( $lat - $latDeg ) * 60 );
	    $latSec = round( ( $lat - $latDeg - $latMin / 60 ) * 1e3 * 3600 ) / 1e3;
	    $lngDeg = floor( $lng );
	    $lngMin = floor( ( $lng - $lngDeg ) * 60 );
	    $lngSec = floor( ( $lng - $lngDeg - $lngMin / 60 ) * 1e3 * 3600 ) / 1e3;

	    return "{$NS} {$latDeg}&deg; {$latMin}' {$latSec}'' &nbsp; {$EW} {$lngDeg}&deg; {$lngMin}' {$lngMin}''";
	}

	/**
	 * [Node] Get buttons to display in tree
	 * Example code explains return value
	 * @endcode
	 * @param	string	$url		Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url )
	{
		if( $this->canEdit() )
		{
			$buttons['edit'] = array(
				'icon'	=> 'pencil',
				'title'	=> 'edit',
				'link'	=> $url->setQueryString( array( 'do' => 'form', 'id' => $this->id ) ),
				'data'	=> array(),
				'hotkey'=> 'e return'
				);
		}
		
		if( $this->canDelete() )
		{
			$buttons['delete'] = array(
				'icon'	=> 'times-circle',
				'title'	=> 'delete',
				'link'	=> $url->setQueryString( array( 'do' => 'delete', 'id' => $this->id, 'deleteNode' => 1 ) ),
				'data' 	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('delete') ),
				'hotkey'=> 'd'
			);
		}

		if ( isset( $buttons['add'] ) )
		{
			unset( $buttons['add']['data'] );
		}

		if ( isset( $buttons['edit'] ) )
		{
			unset( $buttons['edit']['data'] );
		}

		if ( isset( $buttons['copy'] ) )
		{
			unset( $buttons['copy'] );
		}

		return $buttons;
	}

	/**
	 * [Node] Get Node Description
	 *
	 * @return	string|null
	 */
	protected function get_description()
	{
		return isset( $this->_data['description'] ) ? $this->_data['description'] : NULL;
	}
	
	/**
	 * Get elements for add/edit form
	 *
	 * @param	\IPS\Content\Item|NULL	$item		The current item if editing or NULL if creating
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @return	array
	 */
	public static function formElements( $item=NULL, \IPS\Node\Model $container=NULL )
	{
		$return = parent::formElements( $item, $container );

		$return['content'] = new \IPS\Helpers\Form\Editor( 'marker_description', $item ? $item->description : '', FALSE, array(
				'app'         => 'membermap',
				'key'         => 'markers',
				'autoSaveKey' => 'custom-markers-' . ( $item ? $item->id : 'new' ),
				'attachIds'	  => ( $item ) ? array( $item->id ) : NULL ) );

		$return['container'] = new \IPS\Helpers\Form\Node( 'marker_parent_id', ( ( $item AND $item->parent_id ) ? $item->parent_id : ( $container ? $container->id : 0 ) ), TRUE, array(
			'class'		=> '\IPS\membermap\Markers\Groups',
			'permissionCheck' => 'add',
			'subnodes'	=> false,
		) );

		$return['location'] = new \IPS\Helpers\Form\Text( 'marker_location', $item ? $item->location : '', FALSE, array(), NULL, NULL, NULL, 'marker_location' );

		$return['lat'] = new \IPS\Helpers\Form\Number( 'marker_lat', $item ? $item->lat : 0, TRUE, array( 'min' => -90, 'max' => 90, 'decimals' => TRUE ), NULL, NULL, NULL, 'marker_lat' );
		$return['lon'] = new \IPS\Helpers\Form\Number( 'marker_lon', $item ? $item->lon : 0, TRUE, array( 'min' => -180, 'max' => 180, 'decimals' => TRUE ), NULL, NULL, NULL, 'marker_lon' );

		return $return;
	}

	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function processForm( $values )
	{
		parent::processForm( $values );

		$isNew = $this->_new;

		if ( !$this->id )
		{
			$this->save();

			\IPS\File::claimAttachments( 'custom-markers-new', $this->id );
		}

		
		if ( isset( $values['marker_parent_id'] ) AND ( ! empty( $values['marker_parent_id'] ) OR $values['marker_parent_id'] === 0 ) )
		{
			$this->parent_id = $values['marker_parent_id']->id;
		}

		foreach( array( 'marker_description', 'marker_name', 'marker_location', 'marker_lat', 'marker_lon' ) as $val )
		{
			if ( isset( $values[ $val ] ) )
			{
				$key = str_replace( 'marker_', '', $val );

				$this->$key = $values[ $val ];
			}
		}

		/* Update Category */
		$this->container()->setLastMarker( $this );
		$this->container()->save();
	}

	/**
	 * Should new items be moderated?
	 *
	 * @param	\IPS\Member		$member		The member posting
	 * @param	\IPS\Node\Model	$container	The container
	 * @return	bool
	 */
	public static function moderateNewItems( \IPS\Member $member, \IPS\Node\Model $container = NULL )
	{
		if ( $container and $container->moderate and !$member->group['g_avoid_q'] )
		{
			return TRUE;
		}
		
		return parent::moderateNewItems( $member, $container );
	}

	/**
	 * Get all children of a specific group.
	 *
	 * @param	INT 	$groupId		Group ID to fetch children from
	 * @return	array
	 */
	public static function getChildren( $groupId=0 )
	{
		$children = array();
		foreach( \IPS\Db::i()->select( '*', static::$databaseTable, array( static::$databasePrefix . 'parent_id=?', intval( $groupId ) ), static::$databasePrefix . 'name ASC' ) as $child )
		{
			$children[ $child[ static::$databasePrefix . static::$databaseColumnId ] ] = static::load( $child[ static::$databasePrefix . static::$databaseColumnId ] );
		}
	
		return $children;
	}

	/**
	 * Save data
	 *
	 * @return void
	 */
	public function save()
	{
		parent::save();

		//\IPS\membermap\Map::i()->recacheJsonFile();
	}

	/**
	 * Delete data
	 *
	 * @return void
	 */
	public function delete()
	{
		parent::delete();

		\IPS\membermap\Map::i()->recacheJsonFile();
	}
}
