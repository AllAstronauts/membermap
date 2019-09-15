<?php
/**
 * @brief       Marker Comment Model
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       13 Aug 2017
 * @version     -storm_version-
 */

namespace IPS\membermap\Markers;

if( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER[ 'SERVER_PROTOCOL' ] ) ? $_SERVER[ 'SERVER_PROTOCOL' ] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class _Comment extends \IPS\Content\Comment implements \IPS\Content\EditHistory, \IPS\Content\Hideable, \IPS\Content\Searchable, \IPS\Content\Embeddable
{
	use \IPS\Content\Reactable, \IPS\Content\Reportable;

	/**
	 * @brief    [ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief    [Content\Comment]    Item Class
	 */
	public static $itemClass = 'IPS\membermap\Markers\Markers';

	/**
	 * @brief    [ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';

	/**
	 * @brief    [ActiveRecord] Database table
	 */
	public static $databaseTable = 'membermap_comments';

	/**
	 * @brief    [ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'comment_';

	/**
	 * @brief   Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'				=> 'marker_id',
		'author'			=> 'mid',
		'author_name'		=> 'author',
		'content'			=> 'text',
		'date'				=> 'date',
		'ip_address'		=> 'ip_address',
		'edit_time'			=> 'edit_time',
		'edit_member_name'	=> 'edit_name',
		'edit_show'			=> 'append_edit',
		'approved'			=> 'open'
	);

	/**
	 * @brief    Application
	 */
	public static $application = 'membermap';

	/**
	 * @brief    Title
	 */
	public static $title = 'membermap_markers_comment';

	/**
	 * @brief    Icon
	*/
	public static $icon = 'map';

	/**
	 * @brief	Reputation Type
	 */
	public static $reputationType = 'comment_id';

	/**
	 * @brief    [Content]    Key for hide reasons
	 */
	public static $hideLogKey = 'membermap-comment';

	
	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'tables', 'core' ), 'commentRows' );
	}

	/**
	 * Reaction Type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		return 'comment_id';
	}

	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		return \IPS\Theme::i()->getTemplate( 'global', 'membermap' )->embedComment( $this, $this->item(), $this->url()->setQueryString( $params ) );
	}
}