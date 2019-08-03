<?php
/**
 * @brief       File Storage Extension: MarkerStaticMap
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       13 Aug 2017
 * @version     -storm_version-
 */

namespace IPS\membermap\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: MarkerStaticMap
 */
class _MarkerStaticMap
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count(): int
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'membermap_markers', 'marker_embedimage IS NOT NULL' )->first();
	}
	
	/**
	 * Move stored files
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @param	int			$storageConfiguration	New storage configuration ID
	 * @param	int|NULL	$oldConfiguration		Old storage configuration ID
	 * @throws	\UnderflowException					When file record doesn't exist. Indicating there are no more files to move
	 * @return	void|int							An offset integer to use on the next cycle, or nothing
	 */
	public function move( $offset, $storageConfiguration, $oldConfiguration=NULL )
	{
		$marker = \IPS\membermap\Markers\Markers::constructFromData( \IPS\Db::i()->select( '*', 'membermap_markers', 'marker_embedimage IS NOT NULL', 'marker_id', array( $offset, 1 ) )->first() );
		
		try
		{
			$marker->embedimage = \IPS\File::get( $oldConfiguration ?: 'membermap_MarkerStaticMap', $marker->embedimage )->move( $storageConfiguration );
			$marker->save();
		}
		catch( \Exception $e )
		{
			/* Any issues are logged */
		}
	}

	/**
	 * Check if a file is valid
	 *
	 * @param	string	$file		The file path to check
	 * @return	bool
	 */
	public function isValidFile( $file ): bool
	{
		try
		{
			\IPS\Db::i()->select( 'id', 'membermap_markers', array( 'marker_embedimage=?', $file ) )->first();
			return TRUE;
		}
		catch ( \UnderflowException $e )
		{
			return FALSE;
		}
	}

	/**
	 * Delete all stored files
	 *
	 * @return	void
	 */
	public function delete(): void
	{
		foreach( \IPS\Db::i()->select( '*', 'membermap_markers', "marker_embedimage IS NOT NULL" ) as $marker )
		{
			try
			{
				\IPS\File::get( 'membermap_MarkerStaticMap', $marker['marker_embedimage'] )->delete();
			}
			catch( \Exception $e ){}
		}
	}
}