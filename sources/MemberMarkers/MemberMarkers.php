<?php
/**
 * @brief		Member Marker Content Item
 * @author		<a href='http://ipb.silvesterwebdesigns.com'>Stuart Silvester & Martin Aronsen</a>
 * @copyright	(c) 2015 Stuart Silvester & Martin Aronsen
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		20 Oct 2015
 * @version		3.0.2
 */

namespace IPS\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Status Update Model
 */
class _MemberMarkers extends \IPS\Content\Item implements \IPS\Content\Searchable
{
	/* !\IPS\Patterns\ActiveRecord */
	
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'membermap_members';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = '';
	
	/**
	 * @brief	[Content\Comment]	Icon
	 */
	public static $icon = 'map-marker';

	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[Content\Item]	Include the ability to search this content item in global site searches
	 */
	public static $includeInSiteSearch = FALSE;
	
	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array|string
	 */
	public static function basicDataColumns()
	{
		return '*';
	}
	
	/**
	 * Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		
		parent::delete();
	}
	
	/* !\IPS\Content\Item */

	/**
	 * @brief	Title
	 */
	public static $title = 'membermap_markers';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'member_id';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'date'			=> 'marker_date',
		'author'		=> 'membmer_id',
	);
	
	/**
	 * @brief	Application
	 */
	public static $application = 'membermap';
	
	/**
	 * @brief	Module
	 */
	public static $module = 'membermap';
	
	/**
	 * @brief	Language prefix for forms
	 */
	public static $formLangPrefix = 'membermap_';
	

	
	/**
	 * Get Title
	 *
	 * @return	string
	 */
	public function get_title()
	{
		return strip_tags( $this->mapped('content') );
	}
	
	/**
	 * Get container ID for search index
	 *
	 * @return	int
	 */
	public function searchIndexContainer()
	{
		return $this->member_id;
	}
	
	/**
	 * Get mapped value
	 *
	 * @param	string	$key	date,content,ip_address,first
	 * @return	mixed
	 */
	public function mapped( $key )
	{
		if ( $key === 'title' )
		{
			/* We do not want the content (which is mapped to title in $databaseColumnMap) to be added into core_search_index.index_title as the field is only varchar(255) */
			return mb_substr( $this->title, 0, 85 );
		}
		
		return parent::mapped( $key );
	}

	/**
	 * Can a given member create a status update?
	 *
	 * @param \IPS\Member $member
	 * @return bool
	 */
	public static function canCreateFromCreateMenu( \IPS\Member $member = null)
	{
		if ( !$member )
		{
			$member = \IPS\Member::loggedIn();
		}

		/* Can we access the module? */
		if ( !parent::canCreate( $member, NULL, FALSE ) )
		{
			return FALSE;
		}

		/* We have to be logged in */
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		if ( !$member->pp_setting_count_comments or !\IPS\Settings::i()->profile_comments )
		{
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Can a given member create this type of content?
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @param	bool		$showError	If TRUE, rather than returning a boolean value, will display an error
	 * @return	bool
	 */
	public static function canCreate( \IPS\Member $member, \IPS\Node\Model $container=NULL, $showError=FALSE )
	{
		$profileOwner = isset( \IPS\Request::i()->id ) ? \IPS\Member::load( \IPS\Request::i()->id ) : \IPS\Member::loggedIn();
		
		/* Can we access the module? */
		if ( !parent::canCreate( $member, $container, $showError ) )
		{			
			return FALSE;
		}
		
		/* We have to be logged in */
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		if ( !\IPS\Settings::i()->profile_comments )
		{
			return FALSE;
		}
		
		return TRUE;
	}

	
	
	/**
	 * Process create/edit form
	 *
	 * @param	array				$values	Values from form
	 * @return	void
	 */
	public function processForm( $values )
	{
		parent::processForm( $values );
		
		/* Work out which profile we are posting too, but only if we are NOT coming from the Create menu or the status updates widget (neither of which allow posting to another profile */
		/* @todo The dependency on \IPS\Request here needs to be moved to the controller */
		$this->member_id = ( isset( \IPS\Request::i()->id ) AND ( isset( \IPS\Request::i()->controller ) AND \IPS\Request::i()->controller != 'ajaxcreate' ) ) ? \IPS\Request::i()->id : \IPS\Member::loggedIn()->member_id;
		
		if ( !$this->_new )
		{
			$oldContent = $this->content;
		}
		$this->content	= $values['status_content'];
		if ( !$this->_new )
		{
			$this->sendAfterEditNotifications( $oldContent );
		}		
	}

	
	/**
	 * Send notifications
	 *
	 * @return	void
	 */
	public function sendNotifications()
	{
		parent::sendNotifications();

		/* Notify when somebody comments on my profile */
		if( $this->author()->member_id != $this->member_id )	
		{
			$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'profile_comment', $this, array( $this ) );
			$member = \IPS\Member::load( $this->member_id );
			$notification->recipients->attach( $member );
			
			$notification->send();
		}

		/* Notify when a follower posts a status update */
		if ( $this->author()->member_id == $this->member_id )
		{
			$notification	= new \IPS\Notification( \IPS\Application::load( 'core' ), 'new_status', $this, array( $this ) );
			$followers		= \IPS\Member::load( $this->member_id )->followers( 3, array( 'immediate' ), $this->mapped('date'), NULL );

			if( is_array( $followers ) )
			{
				foreach( $followers AS $follower )
				{
					$notification->recipients->attach( \IPS\Member::load( $follower['follow_member_id'] ) );
				}
			}
			
			$notification->send();
		}
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
	
		/* Profile owner should always be able to delete */
		if ( $member->member_id == $this->member_id )
		{
			return TRUE;
		}
		
		return parent::canDelete( $member );
	}
	
	
	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'statuses', 'core' ), 'statusContentRows' );
	}
	
	/**
	 * Get HTML for search result display
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$authorData		Basic data about the author. Only includes columns returned by \IPS\Member::columnsForPhoto()
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	array		$reputationData	Array of people who have given reputation and the reputation they gave
	 * @param	int|NULL	$reviewRating	If this is a review, the rating
	 * @param	bool		$iPostedIn		If the user has posted in the item
	 * @param	string		$view			'expanded' or 'condensed'
	 * @param	bool		$asItem	Displaying results as items?
	 * @return	string
	 */
	public static function searchResult( array $indexData, array $authorData, array $itemData, array $containerData = NULL, array $reputationData, $reviewRating, $iPostedIn, $view, $asItem )
	{			
		$status = static::constructFromData( $itemData );
		$status->reputation = $reputationData;
		
		$profileOwner = isset( $itemData['profile'] ) ? $itemData['profile'] : NULL;
		$profileOwnerData = $profileOwner ?: $authorData;
		$status->_url = \IPS\Http\Url::internal( "app=core&module=members&controller=profile&id={$profileOwnerData['member_id']}&status={$itemData['status_id']}&type=status", 'front', 'profile', array( $profileOwnerData['members_seo_name'] ) );
		
		return \IPS\Theme::i()->getTemplate( 'statuses', 'core', 'front' )->statusContainer( $status, $authorData, $profileOwner, $view == 'condensed' );
	}

	/**
	 * Get number of comments to show per page
	 *
	 * @return int
	 */
	public static function getCommentsPerPage()
	{
		return static::$commentsPerPage;
	}
}