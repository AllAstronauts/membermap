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

namespace IPS\membermap\Custom;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief Block Model
 */
class _Markers extends \IPS\Node\Model
{
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'membermap_cmarkers';

	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'marker_';

	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';

	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array('name', 'description');

	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnParent = null;

	/**
	 * @brief	[Node] Parent Node ID Database Column
	 */
	public static $parentNodeColumnId = 'parent_id';

	/**
	 * @brief	[Node] Parent Node Class
	 */
	public static $parentNodeClass = 'IPS\membermap\Custom\Groups';

	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnOrder = 'name';

	/**
	 * @brief	[Node] Show forms modally?
	 */
	public static $modalForms = TRUE;

	/**
	 * @brief	[Node] Sortable?
	 */
	public static $nodeSortable = TRUE;

	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'membermap_marker';

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
	 * [Node] Get buttons to display in tree
	 * Example code explains return value
	 * @endcode
	 * @param	string	$url		Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		$buttons = parent::getButtons( $url, $subnode );

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
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_membermap.js', 'membermap', 'admin' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'membermap.css', 'membermap' ) );

		
		/* Get enabled maps */
		$defaultMaps = \IPS\membermap\Application::getEnabledMaps();
		\IPS\Output::i()->jsVars['membermap_defaultMaps'] = $defaultMaps;
		\IPS\Output::i()->jsVars['membermap_mapquestAPI'] = \IPS\membermap\Application::getApiKeys( 'mapquest' ); 

		if ( count( \IPS\membermap\Custom\Groups::roots() ) == 0 )
		{
			\IPS\Output::i()->error( 'membermap_error_noGroups', '', 403, '' );
		}

		if ( \IPS\Request::i()->id )
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'membermap_edit_marker' ) . ': ' . \IPS\Output::i()->title;
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('membermap_add_marker');
		}


		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.css', 'membermap', 'global' ) );

		$form->attributes['data-controller'] = 'membermap.admin.membermap.markerform';
		$form->attributes['id'] = 'membermap_add_marker';


		/* Build form */
		$form->add( new \IPS\Helpers\Form\Text( 'marker_name', $this->id ? $this->name : '', TRUE, array( 'maxLength' => 64 ) ) );

		$form->add( new \IPS\Helpers\Form\TextArea( 'marker_description', $this->id ? $this->description : '', FALSE, array( 'rows' => 3 ) ) );

		$form->add( new \IPS\Helpers\Form\Node( 'marker_parent_id', $this->parent_id ? $this->parent_id : 0, TRUE, array(
			'class'		=> '\IPS\membermap\Custom\Groups',
			'subnodes'	=> false,
		) ) );

		$form->add( new \IPS\Helpers\Form\Text( 'marker_location', $this->id ? $this->location : '', FALSE, array(), NULL, NULL, NULL, 'marker_location' ) );

		$form->add( new \IPS\Helpers\Form\Number( 'marker_lat', $this->id ? $this->lat : 0, TRUE, array( 'min' => -90, 'max' => 90, 'decimals' => TRUE ), NULL, NULL, NULL, 'marker_lat' ) );

		$form->add( new \IPS\Helpers\Form\Number( 'marker_lon', $this->id ? $this->lon : 0, TRUE, array( 'min' => -180, 'max' => 180, 'decimals' => TRUE ), NULL, NULL, NULL, 'marker_lon' ) );

		$form->addDummy( 'marker_addViaMap', '<button type="button" id="marker_addViaMap" role="button">' . \IPS\Member::loggedIn()->language()->addToStack( 'marker_addViaMap_button' ) . '</button>' );
	}

	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		$isNew = $this->_new;

		if ( !$this->id )
		{
			$this->save();
		}
		
		
		if ( isset( $values['marker_parent_id'] ) AND ( ! empty( $values['marker_parent_id'] ) OR $values['marker_parent_id'] === 0 ) )
		{
			$values['parent_id'] = $values['marker_parent_id']->id;
			unset( $values['marker_parent_id'] );
		}

		foreach( array( 'marker_description', 'marker_name', 'marker_location', 'marker_lat', 'marker_lon' ) as $val )
		{
			if ( isset( $values[ $val ] ) )
			{
				$key = str_replace( 'marker_', '', $val );

				$values[ $key ] = $values[ $val ];
				unset( $values[ $val ] );
			}
		}


		return $values;
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

		\IPS\membermap\Map::i()->recacheJsonFile();
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
