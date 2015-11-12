<?php
/**
 * @brief		Admin CP Group Form
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		12 Nov 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\membermap\extensions\core\GroupForm;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Admin CP Group Form
 */
class _membermap
{
	/**
	 * Process Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member\Group		$group	Existing Group
	 * @return	void
	 */
	public function process( &$form, $group )
	{		
		$form->add( new \IPS\Helpers\Form\YesNo( 'g_membermap_canAdd', $group->g_membermap_canAdd ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'g_membermap_canEdit', $group->g_membermap_canEdit ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'g_membermap_canDelete', $group->g_membermap_canDelete ) );
	}
	
	/**
	 * Save
	 *
	 * @param	array				$values	Values from form
	 * @param	\IPS\Member\Group	$group	The group
	 * @return	void
	 */
	public function save( $values, &$group )
	{
		$group->g_membermap_canAdd 	= $values['g_membermap_canAdd'];
		$group->g_membermap_canEdit 	= $values['g_membermap_canEdit'];
		$group->g_membermap_canDelete = $values['g_membermap_canDelete'];
	}
}