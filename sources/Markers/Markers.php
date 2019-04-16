<?php
/**
 * @brief       Map Markers Modal
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
 * @brief Block Model
 */
class _Markers extends \IPS\Content\Item implements 
\IPS\Content\Permissions, 
\IPS\Content\Searchable, 
\IPS\Content\Hideable, 
\IPS\Content\MetaData,
\IPS\Content\Featurable,
\IPS\Content\Embeddable,
\IPS\Content\ReadMarkers
{
	use \IPS\Content\Reactable, \IPS\Content\Reportable;

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
		'container'				=> 'parent_id',
		'author'				=> 'member_id',
		'title'					=> 'name',
		'content'				=> 'description',
		'date'					=> 'added',
		'updated'				=> 'updated',
		'approved'				=> 'open',
		'approved_by'			=> 'approver',
		'approved_date'			=> 'approvedon',
		'num_comments'			=> 'comments',
		'unapproved_comments'	=> 'queued_comments',
		'hidden_comments'		=> 'hidden_comments',
		'num_reviews'			=> 'reviews',
		'unapproved_reviews'	=> 'queued_reviews',
		'hidden_reviews'		=> 'hidden_reviews',
		'rating'				=> 'rating',
		'last_comment'			=> 'last_comment',
		'last_review'			=> 'last_review',
		'meta_data'				=> 'meta_data',
		'featured'				=> 'featured',
	);

	/**
	 * @brief       Node Class
	 */
	public static $containerNodeClass = 'IPS\membermap\Markers\Groups';


	/**
	 * @brief	Comment Class
	 */
	public static $commentClass = 'IPS\membermap\Markers\Comment';

	/**
	 * @brief	Review Class
	 */
	public static $reviewClass = 'IPS\membermap\Markers\Review';

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


	public $recacheJson = 1;
	
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
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		$return = parent::basicDataColumns();
		$return[] = 'marker_description';
		$return[] = 'marker_location';
		return $return;
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
	 * Set name
	 *
	 * @param	string	$name	Name
	 * @return	void
	 */
	public function set_name( $name )
	{
		$this->_data['name'] 		= $name;
		$this->_data['name_seo'] 	= \IPS\Http\Url\Friendly::seoTitle( $name );
	}

	/**
	 * Get SEO name
	 *
	 * @return	string
	 */
	public function get_name_seo()
	{
		if( !$this->_data['name_seo'] )
		{
			$this->name_seo	= \IPS\Http\Url\Friendly::seoTitle( $this->name );
			$this->save();
		}

		return $this->_data['name_seo'] ?: \IPS\Http\Url\Friendly::seoTitle( $this->name );
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
	    $latSec = floor( ( $lat - $latDeg - $latMin / 60 ) * 1e3 * 3600 ) / 1e3;

	    $lngDeg = floor( $lng );
	    $lngMin = floor( ( $lng - $lngDeg ) * 60 );
	    $lngSec = floor( ( $lng - $lngDeg - $lngMin / 60 ) * 1e3 * 3600 ) / 1e3;

	    return "{$NS} {$latDeg}&deg; {$latMin}' {$latSec}'' &nbsp; {$EW} {$lngDeg}&deg; {$lngMin}' {$lngSec}''";
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
	 * Returns the content
	 *
	 * @return	string
	 */
	public function content()
	{
		if ( $this->container()->type == 'member' )
		{
			if ( \IPS\Settings::i()->membermap_hideMarkerContent ) 
			{
				return "";
			}
		}

		return $this->mapped('content');
	}

	/**
	 * Returns the location
	 *
	 * @return	string
	 */
	public function get_locationToDisplay()
	{
		if ( $this->container()->type == 'member' )
		{
			if ( \IPS\Settings::i()->membermap_hideMarkerContent ) 
			{
				return "";
			}
		}

		return $this->data['location'];
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
				'key'         => 'Membermap',
				'autoSaveKey' => 'custom-markers-' . ( $item ? $item->id : 'new' ),
				'attachIds'	  => ( $item ) ? array( $item->id ) : NULL ) );

		$return['container'] = new \IPS\Helpers\Form\Node( 'marker_parent_id', ( ( $item AND $item->parent_id ) ? $item->parent_id : ( $container ? $container->id : 0 ) ), TRUE, array(
			'class'				=> '\IPS\membermap\Markers\Groups',
			'permissionCheck' 	=> 'add',
			'subnodes'			=> false,
			'where'				=> array( "group_type != 'member'" ),
		) );


		$return['location'] = new \IPS\Helpers\Form\Text( 'marker_location', $item ? $item->location : '', FALSE, array(), NULL, NULL, NULL, 'marker_location' );

		$return['lat'] = new \IPS\Helpers\Form\Number( 'marker_lat', $item ? $item->lat : 0, TRUE, array( 'min' => -90, 'max' => 90, 'decimals' => TRUE ), NULL, NULL, NULL, 'marker_lat' );
		$return['lon'] = new \IPS\Helpers\Form\Number( 'marker_lon', $item ? $item->lon : 0, TRUE, array( 'min' => -180, 'max' => 180, 'decimals' => TRUE ), NULL, NULL, NULL, 'marker_lon' );

		if ( $item AND $item->parent_id AND $item->container()->type == 'member' )
		{
			unset( $return['title'] );
			unset( $return['container'] );
		}


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

				$this->{$key} = $values[ $val ];
			}
		}

		parent::processForm( $values );
	}

	/**
	 * Can delete?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canDelete( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* Can we delete our own content? */
		if ( $member->member_id == $this->author()->member_id AND $member->group['g_membermap_delete_own'] )
		{
			return TRUE;
		}
		
		/* What about this? */
		try
		{
			return static::modPermission( 'delete', $member, $this->container() );
		}
		catch ( \BadMethodCallException $e )
		{
			return $member->modPermission( "can_delete_content" );
		}

		/* Member Map does not honor the "can delete own content" group setting anymore.
		 * That's why we don't return parent::canDelete() */
		
		return FALSE;
	}

	/**
	 * Can edit?
	 * Authors can always edit their own markers
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEdit( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id > 0 AND $member->member_id == $this->author()->member_id ) or parent::canEdit( $member );
	}

	/**
	 * Should new items be moderated?
	 *
	 * @param	\IPS\Member		$member		The member posting
	 * @param	\IPS\Node\Model	$container	The container
	 * @param	bool			$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public static function moderateNewItems( \IPS\Member $member, \IPS\Node\Model $container = NULL, $considerPostBeforeRegistering = FALSE )
	{
		if ( $container and $container->moderate and !$member->group['g_avoid_q'] )
		{
			return TRUE;
		}
		
		return parent::moderateNewItems( $member, $container, $considerPostBeforeRegistering );
	}

	/**
	 * Should new comments be moderated?
	 *
	 * @param	\IPS\Member	$member							The member posting
	 * @param	bool		$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public function moderateNewComments( \IPS\Member $member, $considerPostBeforeRegistering = FALSE )
	{
		return ( $this->container()->comment_moderate and !$member->group['g_avoid_q'] ) or parent::moderateNewComments( $member, $considerPostBeforeRegistering );
	}

	/**
	 * Should new reviews be moderated?
	 *
	 * @param	\IPS\Member	$member							The member posting
	 * @param	bool		$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public function moderateNewReviews( \IPS\Member $member, $considerPostBeforeRegistering = FALSE )
	{
		return ( $this->container()->review_moderate and !$member->group['g_avoid_q'] ) or parent::moderateNewReviews( $member, $considerPostBeforeRegistering );
	}

	/**
	 * Are comments supported by this class?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for or NULL to not check permission
	 * @param	\IPS\Node\Model|NULL	$container	The container to check in, or NULL for any container
	 * @return	bool
	 */
	public static function supportsComments( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		if( $container !== NULL )
		{
			return parent::supportsComments() and $container->allow_comments AND ( !$member or $container->can( 'read', $member ) );
		}
		else
		{
			return parent::supportsComments() and ( !$member or \IPS\membermap\Markers\Groups::countWhere( 'read', $member, array( 'group_allow_comments=1' ) ) );
		}
	}

	/**
	 * Are reviews supported by this class?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for or NULL to not check permission
	 * @param	\IPS\Node\Model|NULL	$container	The container to check in, or NULL for any container
	 * @return	bool
	 */
	public static function supportsReviews( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		if( $container !== NULL )
		{
			return parent::supportsReviews() and $container->allow_reviews AND ( !$member or $container->can( 'read', $member ) );
		}
		else
		{
			return parent::supportsReviews() and ( !$member or \IPS\membermap\Markers\Groups::countWhere( 'read', $member, array( 'group_allow_reviews=1' ) ) );
		}
	}

	/**
	 * Can comment?
	 *
	 * @param	\IPS\Member\NULL	$member							The member (NULL for currently logged in member)
	 * @param	bool				$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 */
	public function canComment( $member=NULL, $considerPostBeforeRegistering = TRUE )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return parent::canComment( $member, $considerPostBeforeRegistering ) and $this->container()->allow_comments;
	}

	/**
	 * Can review?
	 *
	 * @param	\IPS\Member\NULL	$member							The member (NULL for currently logged in member)
	 * @param	bool				$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 */
	public function canReview( $member=NULL, $considerPostBeforeRegistering = TRUE )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return parent::canReview( $member, $considerPostBeforeRegistering ) and $this->container()->allow_reviews;
	}

	/**
	 * Get available comment/review tabs
	 *
	 * @return	array
	 */
	public function commentReviewTabs()
	{
		$tabs = array();
		if ( $this->container()->allow_reviews )
		{
			$tabs['reviews'] = \IPS\Member::loggedIn()->language()->addToStack( 'marker_review_count', TRUE, array( 'pluralize' => array( $this->mapped('num_reviews') ) ) );
		}
		if ( $this->container()->allow_comments )
		{
			$tabs['comments'] = \IPS\Member::loggedIn()->language()->addToStack( 'marker_comment_count', TRUE, array( 'pluralize' => array( $this->mapped('num_comments') ) ) );
		}

		return $tabs;
	}

	/**
	 * Get comment/review output
	 *
	 * @param	string	$tab	Active tab
	 * @return	string
	 */
	public function commentReviews( $tab )
	{
		if ( $tab === 'reviews' and $this->container()->allow_reviews )
		{
			return \IPS\Theme::i()->getTemplate( 'markers', 'membermap' )->reviews( $this );
		}
		elseif( $tab === 'comments' and $this->container()->allow_comments )
		{
			return \IPS\Theme::i()->getTemplate( 'markers', 'membermap' )->comments( $this );
		}
		
		return '';
	}

	/**
	 * Get URL for last comment page
	 *
	 * @return	\IPS\Http\Url
	 */
	public function lastCommentPageUrl()
	{
		return parent::lastCommentPageUrl()->setQueryString( 'tab', 'comments' );
	}
	
	/**
	 * Get URL for last review page
	 *
	 * @return	\IPS\Http\Url
	 */
	public function lastReviewPageUrl()
	{
		return parent::lastCommentPageUrl()->setQueryString( 'tab', 'reviews' );
	}

	/**
	 * Supported Meta Data Types
	 *
	 * @return	array
	 */
	public static function supportedMetaDataTypes()
	{
		return array( 'core_FeaturedComments', 'core_ContentMessages' );
	}

	/**
	 * Reaction Type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		return 'marker_id';
	}

	/** 
	 * Get embed image
	 *
	 * @return  \IPS\File
	 */
	public function get_embedimage()
	{
		if ( ! $this->_data['embedimage'] )
		{
			$apiKey 	= \IPS\membermap\Application::getApiKeys( 'mapquest' );

			$url 		= \IPS\Http\Url::external( "https://www.mapquestapi.com/staticmap/v5/map?key={$apiKey}&locations={$this->lat},{$this->lon}&size=1100,500" );
			$response 	= $url->request()->get();

			$image 		= \IPS\File::create( 'membermap_MarkerStaticMap', 'membermap-' . $this->id . '-staticmap.png', $response, NULL, TRUE, NULL, FALSE );

			$this->embedimage = (string) $image;

			$this->recacheJson = 0;
			$this->save();
			$this->recacheJson = 1;
		}

		return $this->_data['embedimage'];
	}

	/**
	 * Returns the content images
	 *
	 * @param	int|null	$limit	Number of attachments to fetch, or NULL for all
	 *
	 * @return	array|NULL	If array, then array( 'core_Attachment' => 'month_x/foo.gif', ... );
	 * @throws	\BadMethodCallException
	 */
	public function contentImages( $limit = NULL )
	{
		$attachments = parent::contentImages( $limit ) ?: array();

		$attachments[] = array( 'membermap_MarkerStaticMap' => $this->embedimage );
		
		
		return \count( $attachments ) ? $attachments : NULL;
	}

	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		return \IPS\Theme::i()->getTemplate( 'global', 'membermap' )->embedMarker( $this, $this->url()->setQueryString( $params ) );
	}

	/**
	 * Should posting this increment the poster's post count?
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @return	void
	 */
	public static function incrementPostCount( \IPS\Node\Model $container = NULL )
	{
		return FALSE;
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
		foreach( \IPS\Db::i()->select( '*', static::$databaseTable, array( static::$databasePrefix . 'parent_id=?', \intval( $groupId ) ), static::$databasePrefix . 'name ASC' ) as $child )
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
		/* Clear the cached static map image */
		if ( isset( $this->_data['embedimage'] ) AND $this->_data['embedimage'] != "" AND ( isset( $this->changed['lat'] ) OR isset( $this->changed['lon'] ) ) )
		{
			\IPS\File::get( 'membermap_MarkerStaticMap', $this->_data['embedimage'] )->delete();

			$this->embedimage = NULL;
		}

		parent::save();

		$this->container()->recacheJson = 0;
		$this->container()->setLastComment();
		$this->container()->save();
		$this->container()->recacheJson = 1;

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
		parent::delete();

		$this->container()->setLastComment();
		$this->container()->save();

		\IPS\membermap\Map::i()->invalidateJsonCache();
	}
}