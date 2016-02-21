<?php
/**
 * @brief		Custom Marker Groups
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
 * @brief	Folder Model
 */
class _Groups extends \IPS\Node\Model
{
	/**
	 * Munge different record types
	 *
	 *
	 * @return  array
	 */
	public static function munge()
	{
		$rows = array();
		$args = func_get_args();
	
		foreach( $args as $arg )
		{
			foreach( $arg as $id => $obj )
			{
				$rows[ $obj->getSortableName() . '_' . $obj::$databaseTable . '_' . $obj->id  ] = $obj;
			}
		}
	
		ksort( $rows );
	
		return $rows;
	}
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'membermap_cmarkers_groups';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'group_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array('group_name');
	
	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnParent = null;
	
	/**
	 * @brief	[Node] Parent ID Root Value
	 * @note	This normally doesn't need changing though some legacy areas use -1 to indicate a root node
	 */
	public static $databaseColumnParentRootValue = 0;
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'name';
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'membermap_group';
	
	/**
	 * @brief	[Node] Subnode class
	 */
	public static $subnodeClass = 'IPS\membermap\Custom\Markers';
	
	/**
	 * @brief	[Node] Show forms modally?
	 */
	public static $modalForms = TRUE;
	

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
	 * [Node] Get Title
	 *
	 * @return	string|null
	 */
	protected function get__title()
	{
		return $this->name;
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
		$return  = array();
		
		if ( isset( $buttons['copy'] ) )
		{
			unset( $buttons['copy'] );
		}
		
		if ( isset( $buttons['add'] ) )
		{
			$buttons['add_page'] = array(
					'icon'	=> 'plus-circle',
					'title'	=> 'membermap_add_marker',
					'link'	=> $url->setQueryString( array( 'subnode' => 1, 'do' => 'form', 'parent' => $this->_id ) ),
					'data'  => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('membermap_add_marker') )
			);
		}
		
		/* Re-arrange */
		if ( isset( $buttons['edit'] ) )
		{
			$return['edit'] = $buttons['edit'];
		}
		
		if ( isset( $buttons['add_page'] ) )
		{
			$return['add_page'] = $buttons['add_page'];
		}
			
		if ( isset( $buttons['delete'] ) )
		{
			$return['delete'] = $buttons['delete'];
		}	
		
		return $return;
	}
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->attributes['data-controller'] = 'membermap.admin.membermap.groupform';
		
		/* Build form */
		$form->add( new \IPS\Helpers\Form\Text( 'group_name', $this->id ? $this->name : '', TRUE, array( 'maxLength' => 64 ), function( $val )
		{
			try
			{
				$test = \IPS\membermap\Custom\Groups::load( $val, 'name' );

				if ( ! empty( \IPS\Request::i()->id ) and $test->id != \IPS\Request::i()->id )
				{
					throw new \InvalidArgumentException('membermap_group_name_in_use');
				}
			}
			catch ( \OutOfRangeException $e )
			{
				return true;
			}
		}));

		$radioOpt = array();
		$colours = array( 
			'red', 'darkred', 'lightred', 'orange', 'beige', 'green', 'darkgreen', 'lightgreen', 'blue', 'darkblue', 'lightblue',
			'purple', 'darkpurple', 'pink', 'cadetblue', 'gray', 'lightgray', 'black', 'white'
		);

		$icon 		= $this->id ? $this->pin_icon : 'fa-globe';
		$iconColour 	= $this->id ? $this->pin_colour : '#FFFFFF';
		$bgColour 	= $this->id ? $this->pin_bg_colour : 'red';

		/* Selected a valid colour? */
		$bgColour = in_array( $bgColour, $colours ) ? $bgColour : 'red';

		foreach( $colours as $c )
		{
			$radioOpt[ $c ] = \IPS\Theme::i()->resource( "awesome-marker-icon-{$c}.png", "membermap", 'admin' );
		}

		$form->add( new \IPS\Helpers\Form\Text( 'group_pin_icon', $icon, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Color( 'group_pin_colour', $iconColour, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'group_pin_bg_colour', $bgColour, TRUE, array(
			'options' => $radioOpt,
			'parse' => 'image',
			'descriptions' => array( 'white' => 'White' ) /* Just because white is difficult to see on the page */
		)));

		$form->addDummy( 'group_marker_example', "<span class='awesome-marker awesome-marker-icon-{$bgColour}' id='markerExample'><i class='fa fa-fw {$icon}' style='color: {$iconColour}'></i></span>" );
	}

	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if ( !$this->id )
		{
			$this->save();
		}
		
		foreach( array( 'group_name', 'group_pin_icon', 'group_pin_colour', 'group_pin_bg_colour' ) as $val )
		{
			if( isset( $values[ $val ] ) )
			{
				$key = str_replace( 'group_', '', $val );
				$values[ $key ] = $values[ $val ];
				unset( $values[ $val ] );
			}
		}
		return $values;
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