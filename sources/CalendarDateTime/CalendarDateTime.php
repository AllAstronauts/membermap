<?php
/**
 * @brief       Override Calendar Date function to make it work with queues ran through a cron job.
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       01 Oct 2018
 * @version     -storm_version-
 */

namespace IPS\membermap;


class _CalendarDateTime extends \IPS\calendar\Date
{
	/**
	 * Always return the default language.
	 *
	 * @param	\IPS\Lang|\IPS\Member|NULL	$formatter	Value we are using to determine how to format the result
	 * @return	\IPS\Lang
	 */
	protected static function determineLanguage( $formatter=NULL )
	{
		return \IPS\Lang::load( \IPS\Lang::defaultLanguage() );
	}
}