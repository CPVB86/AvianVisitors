<?php
/**
 * Plugin Name: Backyard
 * Description: Modulaire presentatie en beheer via de Backyard API.
 * Version: 0.3.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: backyard
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/class-backyard-api-client.php';
require_once __DIR__ . '/includes/class-backyard-generator.php';
require_once __DIR__ . '/includes/shortcodes.php';

function backyard_activate() {
	add_option( 'backyard_api_base_url', BACKYARD_DEFAULT_API_URL );
	add_option( 'backyard_api_token', '', '', false );
}

function backyard_deactivate() {
	// No scheduled jobs or temporary data. Preserve settings on deactivation.
}

register_activation_hook( __FILE__, 'backyard_activate' );
register_deactivation_hook( __FILE__, 'backyard_deactivate' );

function backyard_modules() {
	static $modules = null;
	if ( null === $modules ) {
		$modules = array();
		foreach ( array( 'birds', 'bats', 'weather', 'garden' ) as $slug ) {
			$modules[ $slug ] = require __DIR__ . '/modules/' . $slug . '/' . $slug . '.php';
		}
	}
	return $modules;
}

add_action( 'plugins_loaded', 'backyard_modules' );
if ( is_admin() ) {
	require_once __DIR__ . '/admin/pages.php';
}
