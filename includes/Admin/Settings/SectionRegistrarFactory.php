<?php
/**
 * Section Registrar Factory
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Admin\Settings;

use JTZL\Bot_Maze\Service\AbuseIpDbStatus;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Produces the default list of section registrars.
 *
 * Owns the set of concrete registrars so SettingsPage only depends on
 * the factory and the interface rather than every concrete class,
 * keeping SettingsPage's coupling in check.
 *
 * @since 1.0.0
 */
class SectionRegistrarFactory {

	/**
	 * Build the default set of section registrars in the order they
	 * should appear on the settings page.
	 *
	 * @param AbuseIpDbStatus $status Aggregate AbuseIPDB reporting status.
	 * @return SectionRegistrarInterface[]
	 * @since 1.0.0
	 */
	public static function create_default( AbuseIpDbStatus $status ): array {
		return array(
			new GeneralSectionRegistrar(),
			new TrapLinkSectionRegistrar(),
			new TrapMazeSectionRegistrar(),
			new ScoringSectionRegistrar(),
			new AbuseIpDbSectionRegistrar( $status ),
			new GeoSectionRegistrar(),
			new MaintenanceSectionRegistrar(),
			new RobotsSectionRegistrar(),
		);
	}
}
