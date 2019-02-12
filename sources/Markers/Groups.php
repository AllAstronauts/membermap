<?php
/**
 * @brief       Marker Groups Modal
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       27 Feb 2016
 * @version     -storm_version-
 */

namespace IPS\membermap\Markers;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Folder Model
 */
class _Groups extends \IPS\Node\Model implements \IPS\Node\Permissions
{	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'membermap_markers_groups';
	
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
	public static $databaseColumnOrder = 'position';
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'membermap_group';

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'membermap_marker_group_';
	
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
	 			'delete'		=> 'markers_delete',
	 			'permissions'	=> 'markers_permissions'
	 		),
	);

	/**
	 * @brief	[Node] App for permission index
	 */
	public static $permApp = 'membermap';
	
	/**
	 * @brief	[Node] Type for permission index
	 */
	public static $permType = 'membermap';
	
	/**
	 * @brief	The map of permission columns
	 */
	public static $permissionMap = array(
		'view'				=> 'view', /* This is actually the 'add' permission, since 'view' is required, and everyone can view any markers */
		'read'				=> 2,
		'add'				=> 3,
		'reply'				=> 4,
		'review'			=> 5,
	);
	
	/**
	 * @brief	[Node] Prefix string that is automatically prepended to permission matrix language strings
	 */
	public static $permissionLangPrefix = 'perm_membermap_';


	/**
	 * @brief	[Node] Moderator Permission
	 */
	public static $modPerm = 'membermap_markers_groups';
	
	/**
	 * @brief	Content Item Class
	 */
	public static $contentItemClass = 'IPS\membermap\Markers\Markers';

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;
	
	/**
	 * @brief	URL Base
	 */
	public static $urlBase = 'app=membermap&module=markers&controller=groups&id=';
	
	/**
	 * @brief	URL Base
	 */
	public static $urlTemplate = 'markers_group';
	
	/**
	 * @brief	SEO Title Column
	 */
	public static $seoTitleColumn = 'name_seo';


	public $recacheJson = 1;
	
	/**
	 * [Node] Get Title
	 *
	 * @return	string|null
	 */
	protected function get__title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( "membermap_marker_group_{$this->id}" );
	}

	/**
	 * Get template for node tables
	 *
	 * @return	callable
	 */
	public static function nodeTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'markers', 'membermap' ), 'groupRow' );
	}

	/**
	 * Get last comment time
	 *
	 * @note	This should return the last comment time for this node only, not for children nodes
	 * @return	\IPS\DateTime|NULL
	 */
	public function getLastCommentTime()
	{
		return $this->last_marker_date ? \IPS\DateTime::ts( $this->last_marker_date ) : NULL;
	}

	/**
	 * Get latest file information
	 *
	 * @return	\IPS\membermap\Markers\Markers|NULL
	 */
	public function lastMarker()
	{
		if( !$this->last_marker_id )
		{
			return NULL;
		}

		try
		{
			$latestMarker = \IPS\membermap\Markers\Markers::load( $this->last_marker_id );
		}
		catch ( \OutOfRangeException $e )
		{
			$latestMarker = NULL;
		}

		return $latestMarker;
	}

	/**
	 * Set last file data
	 *
	 * @param	\IPS\downloads\File|NULL	$file	The latest file or NULL to work it out
	 * @return	void
	 */
	public function setlastMarker( \IPS\membermap\Markers\Markers $marker=NULL )
	{
		if( $marker === NULL )
		{
			try
			{
				$marker	= \IPS\membermap\Markers\Markers::constructFromData( \IPS\Db::i()->select( '*', 'membermap_markers', array( 'marker_parent_id=? AND marker_open=1', $this->id ), 'marker_updated DESC, marker_added DESC', 1 )->first() );
			}
			catch ( \UnderflowException $e )
			{
				$this->last_marker_id	= 0;
				$this->last_marker_date	= 0;
				return;
			}
		}
	
		$this->last_marker_id	= $marker->id;
		$this->last_marker_date	= $marker->updated > $marker->added ? $marker->updated : $marker->added;
	}
	
	/**
	 * Set last comment
	 *
	 * @param	\IPS\Content\Comment|NULL	$comment	The latest comment or NULL to work it out
	 * @return	int
	 * @note	We actually want to set the last file info, not the last comment, so we ignore $comment
	 */
	public function setLastComment( \IPS\Content\Comment $comment=NULL )
	{
		$this->setlastMarker();
	}

	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @return	int
	 */
	protected function get__unapprovedItems()
	{
		return $this->queued_items;
	}

	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @param	int	$val	Unapproved Items
	 * @return	void
	 */
	protected function set__unapprovedItems( $val )
	{
		$this->queued_items = $val;
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
		
		if ( isset( $buttons['copy'] ) )
		{
			unset( $buttons['copy'] );
		}
		
		if ( isset( $buttons['empty'] ) )
		{
			unset( $buttons['empty'] );
		}
		
		/* The member marker group is protected, can't be deleted */
		if ( isset( $buttons['delete'] ) AND $this->protected )
		{
			unset( $buttons['delete'] );
		}

		if ( $this->type == 'custom' AND isset( $buttons['edit'] ) )
		{
			$buttons['import'] = array(
				'icon'	=> 'upload',
				'title'	=> 'import',
				'link'	=> $url->setQueryString( array( 'do' => 'import', 'id' => $this->_id ) ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('import') )
			);
		}

		
		return $buttons;
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
		$form->add( new \IPS\Helpers\Form\Translatable( 'group_name', NULL, TRUE, array( 'app' => 'membermap', 'key' => ( $this->id ? "membermap_marker_group_{$this->id}" : NULL ) ) ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'group_moderate', $this->id ? $this->moderate : FALSE, FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'group_allow_comments', $this->id ? $this->allow_comments : TRUE, FALSE, array( 'togglesOn' => array( 'group_comment_moderate' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'group_comment_moderate', $this->id ? $this->comment_moderate : FALSE, FALSE, array(), NULL, NULL, NULL, 'group_comment_moderate' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'group_allow_reviews', $this->id ? $this->allow_reviews : FALSE, FALSE, array( 'togglesOn' => array( 'group_review_moderate' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'group_review_moderate', $this->id ? $this->review_moderate : FALSE, FALSE, array(), NULL, NULL, NULL, 'group_review_moderate' ) );

		if( $this->_new OR $this->type == 'custom' )
		{
			$radioOpt = array();
			$colours = array( 
				'red', 'darkred', 'lightred', 'orange', 'beige', 'green', 'darkgreen', 'lightgreen', 'blue', 'darkblue', 'lightblue',
				'purple', 'darkpurple', 'pink', 'cadetblue', 'gray', 'lightgray', 'black', 'white'
			);

			$icon 		= ( $this->id AND $this->pin_icon ) ? $this->pin_icon : 'fa-globe';
			$iconColour = ( $this->id AND $this->pin_colour ) ? $this->pin_colour : '#FFFFFF';
			$bgColour 	= ( $this->id AND $this->pin_bg_colour ) ? $this->pin_bg_colour : 'red';

			/* Selected a valid colour? */
			$bgColour = \in_array( $bgColour, $colours ) ? $bgColour : 'red';

			foreach( $colours as $c )
			{
				$radioOpt[ $c ] = \IPS\Theme::i()->resource( "awesome-marker-icon-{$c}.png", "membermap", 'admin' );
			}

			$form->add( new \IPS\Helpers\Form\Text( 'group_pin_icon', $icon, TRUE ) );
			$form->add( new \IPS\Helpers\Form\Color( 'group_pin_colour', $iconColour, TRUE ) );
			$form->add( new \IPS\Helpers\Form\Radio( 'group_pin_bg_colour', $bgColour, TRUE, array(
				'options' => $radioOpt,
				'parse' => 'image',
				'descriptions' => array( 'white' => \IPS\Member::loggedIn()->language()->addToStack( 'group_pin_bg_colour_white' ) ) /* Just because white is difficult to see on the page */
			)));

			$form->addDummy( 'group_marker_example', "<span class='awesome-marker awesome-marker-icon-{$bgColour}' id='markerExample'><i class='fa fa-fw {$icon}' style='color: {$iconColour}'></i></span>" );
		}
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

		if ( isset( $values['group_name'] ) )
		{
			\IPS\Lang::saveCustom( 'membermap', "membermap_marker_group_{$this->id}", $values['group_name'] );
			\IPS\Lang::saveCustom( 'membermap', "membermap_marker_group_{$this->id}_JS", $values['group_name'], 1 );
			
			$this->name_seo = \IPS\Http\Url\Friendly::seoTitle( $values['group_name'][ \IPS\Lang::defaultLanguage() ] );
			
			unset( $values['group_name'] );
		}
		
		foreach( array( 'group_pin_icon', 'group_pin_colour', 'group_pin_bg_colour' ) as $val )
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
	 * Check permissions
	 *
	 * @param	mixed								$permission		A key which has a value in static::$permissionMap['view'] matching a column ID in core_permission_index
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$member			The member or group to check (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\OutOfBoundsException	If $permission does not exist in static::$permissionMap
	 */
	public function can( $permission, $member=NULL )
	{
		$parent = parent::can( $permission, $member );

		if( $parent === TRUE )
		{
			$_member = $member ?: \IPS\Member::loggedIn();

			if ( $this->type == 'member' AND $permission == 'add' )
			{
				$existing = \IPS\membermap\Map::i()->getMarkerByMember( $_member->member_id, FALSE );
				
				return isset( $existing ) AND $existing !== FALSE ? FALSE : TRUE;
			}
		}

		return $parent;
	}

	/**
	 * Save data
	 *
	 * @return void
	 */
	public function save()
	{
		parent::save();

		if ( $this->recacheJson )
		{
			\IPS\membermap\Map::i()->invalidateJsonCache();
		}
	}

	/**
	 * Delete data
	 *
	 * @return void
	 */
	public function delete()
	{
		/* Can't delete a protected group */
		if ( $this->protected )
		{
			throw new \DomainException( 'No no no no no' );
		}

		parent::delete();
		
		if ( $this->recacheJson )
		{
			\IPS\membermap\Map::i()->invalidateJsonCache();
		}
	}

	/**
	 * Get the total amount of unapproved items
	 *
	 * @return 	int 	Number of unapproved items
	 */
	public static function getTotalUnapprovedCount()
	{
		$unapproved = 0;

		foreach( static::roots() as $group )
		{
			if ( \IPS\membermap\Markers\Markers::modPermission( 'unhide', NULL, $group ) )
			{
				$unapproved += $group->_unapprovedItems;
			}
		}

		return $unapproved;
	}

	/**
	 * Set the permission index permissions to a specific club
	 *
	 * @param	\IPS\Member\Club	$club	The club
	 * @return  void
	 */
	public function setPermissionsToClub( \IPS\Member\Club $club )
	{
		// Deliberately do nothing, Blog handles permissions differently
	}
}