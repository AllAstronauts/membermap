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
	private $colours = array( 
			'red', 'darkred', 'lightred', 'orange', 'beige', 'green', 'darkgreen', 'lightgreen', 'blue', 'darkblue', 'lightblue',
			'purple', 'darkpurple', 'pink', 'cadetblue', 'gray', 'lightgray', 'black', 'white'
	);

	/**
	 * Process Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member\Group		$group	Existing Group
	 * @return	void
	 */
	public function process( &$form, $group )
	{		
		$bgColour 	= $group->g_membermap_markerColour ? $group->g_membermap_markerColour : 'darkblue';

		/* Selected a valid colour? */
		$bgColour = in_array( $bgColour, $this->colours ) ? $bgColour : 'darkblue';

		foreach( $this->colours as $c )
		{
			$radioOpt[ $c ] = \IPS\Theme::i()->resource( "awesome-marker-icon-{$c}.png", "membermap", 'admin' );
		}

		$form->add( new \IPS\Helpers\Form\Radio( 'g_membermap_markerColour', $bgColour, TRUE, array(
			'options' => $radioOpt,
			'parse' => 'image',
			'descriptions' => array( 'white' => \IPS\Member::loggedIn()->language()->addToStack( 'group_pin_bg_colour_white' ) ) /* Just because white is difficult to see on the page */
		)));
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
		$bgColour 	= $values['g_membermap_markerColour'] ? $values['g_membermap_markerColour'] : 'darkblue';

		/* Selected a valid colour? */
		$bgColour = in_array( $bgColour, $this->colours ) ? $bgColour : 'darkblue';

		$group->g_membermap_markerColour = $bgColour;
	}
}