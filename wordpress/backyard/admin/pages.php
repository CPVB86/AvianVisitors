<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/commands.php';

add_action( 'admin_menu', function () {
	add_menu_page( 'Backyard', 'Backyard', 'manage_options', 'backyard', 'backyard_settings_page', 'dashicons-carrot' );
	foreach ( backyard_modules() as $slug => $module ) {
		add_submenu_page( 'backyard', $module['title'], $module['title'], 'manage_options', 'backyard-' . $slug, function () use ( $module ) {
			backyard_require_admin();
			echo '<div class="wrap"><h1>' . esc_html( $module['title'] ) . '</h1><p>' . esc_html( $module['description'] ) . '</p></div>';
		} );
	}
	// Replace WordPress's automatically inserted parent entry, after the modules.
	remove_submenu_page( 'backyard', 'backyard' );
	add_submenu_page( 'backyard', 'Backyard instellingen', 'Instellingen', 'manage_options', 'backyard', 'backyard_settings_page' );
	add_submenu_page( 'backyard', 'Backyard handleiding', 'Handleiding', 'manage_options', 'backyard-manual', 'backyard_manual_page' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, array( 'backyard_page_backyard-manual', 'toplevel_page_backyard' ), true ) ) {
		return;
	}
	wp_enqueue_style( 'backyard-manual', plugins_url( 'manual.css', __FILE__ ), array(), '0.2.3' );
	if ( 'backyard_page_backyard-manual' === $hook ) {
		wp_enqueue_script( 'backyard-manual', plugins_url( 'manual.js', __FILE__ ), array(), '0.2.3', true );
	}
} );

function backyard_require_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( 'Je hebt geen toegang tot deze pagina.' ) );
	}
}

function backyard_settings_page() {
	backyard_require_admin();
	echo '<div class="wrap"><h1>Instellingen</h1>';
	settings_errors();
	$result = null;
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['backyard_test_connection'] ) ) {
		check_admin_referer( 'backyard_test_connection' );
		$result = ( new Backyard_API_Client() )->health();
	}
	echo '<form id="backyard-settings" method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
	settings_fields( 'backyard' );
	echo '<section class="backyard-card"><h2>PI Connection</h2>';
	if ( null !== $result ) {
		$class  = is_wp_error( $result ) ? 'notice notice-error' : 'notice notice-success';
		$text   = is_wp_error( $result ) ? $result->get_error_message() : 'API bereikbaar — database ok.';
		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $text ) . '</p></div>';
	}
	do_settings_sections( 'backyard' );
	echo '<p>Sla wijzigingen eerst op. De test gebruikt de opgeslagen verbinding.</p>';
	submit_button( 'Test verbinding', 'secondary', 'backyard_test_connection', true, array( 'form' => 'backyard-connection-test' ) );
	echo '</section>';
	submit_button( 'Instellingen opslaan' );
	echo '</form>';
	// Separate form keeps testing independent of unsaved settings and their nonce.
	echo '<form id="backyard-connection-test" method="post" action="' . esc_url( admin_url( 'admin.php?page=backyard' ) ) . '">';
	wp_nonce_field( 'backyard_test_connection' );
	echo '</form></div>';
}

function backyard_manual_page() {
	backyard_require_admin();
	echo '<div class="wrap"><h1>Handleiding</h1>';
	backyard_manual_commands();
	$entries = backyard_shortcode_docs();
	if ( ! $entries ) {
		echo '<p>Er zijn nog geen publieke shortcodes beschikbaar.</p>';
	}
	$modules = array();
	foreach ( $entries as $entry ) {
		$modules[ $entry['module'] ][] = $entry;
	}
	foreach ( $modules as $module => $shortcodes ) {
		echo '<section class="backyard-card"><h2>' . esc_html( $module ) . '</h2>';
		foreach ( $shortcodes as $entry ) {
			echo '<div class="backyard-command backyard-shortcode-row backyard-copy-item"><div><strong>' . esc_html( $entry['title'] ?? $entry['shortcode'] ) . '</strong><p>' . esc_html( $entry['description'] ) . '</p>';
			if ( $entry['parameters'] ) {
				echo '<details class="backyard-parameters"><summary>Parameters</summary><dl>';
				foreach ( $entry['parameters'] as $name => $description ) {
					echo '<dt><code>' . esc_html( $name ) . '</code></dt><dd>' . esc_html( $description ) . '</dd>';
				}
				echo '</dl></details>';
			}
			echo '</div>';
			echo '<button type="button" class="backyard-copy-shortcode" aria-label="' . esc_attr( 'Kopieer shortcode ' . $entry['shortcode'] ) . '"><code>' . esc_html( $entry['shortcode'] ) . '</code><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>';
			echo '<p class="backyard-copy-status screen-reader-text" role="status" aria-live="polite"></p></div>';
		}
		echo '</section>';
	}
	echo '</div>';
}
