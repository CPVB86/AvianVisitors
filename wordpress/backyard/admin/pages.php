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
	if ( 'backyard_page_backyard-manual' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'backyard-manual', plugins_url( 'manual.css', __FILE__ ), array(), '0.2.2' );
	wp_enqueue_script( 'backyard-manual', plugins_url( 'manual.js', __FILE__ ), array(), '0.2.2', true );
} );

function backyard_require_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( 'Je hebt geen toegang tot deze pagina.' ) );
	}
}

function backyard_settings_page() {
	backyard_require_admin();
	echo '<div class="wrap"><h1>Backyard</h1><p>De Raspberry Pi/API is de bron van waarheid. WordPress leest en presenteert gegevens.</p>';
	settings_errors();
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['backyard_test_connection'] ) ) {
		check_admin_referer( 'backyard_test_connection' );
		$result = ( new Backyard_API_Client() )->health();
		$class  = is_wp_error( $result ) ? 'notice notice-error' : 'notice notice-success';
		$text   = is_wp_error( $result ) ? $result->get_error_message() : 'API bereikbaar — database ok.';
		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $text ) . '</p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
	settings_fields( 'backyard' );
	do_settings_sections( 'backyard' );
	submit_button( 'Instellingen opslaan' );
	echo '</form><h2>Verbinding testen</h2><p>Sla wijzigingen eerst op. De test gebruikt de opgeslagen URL en vraagt /api/health op.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=backyard' ) ) . '">';
	wp_nonce_field( 'backyard_test_connection' );
	submit_button( 'Test verbinding', 'secondary', 'backyard_test_connection' );
	echo '</form></div>';
}

function backyard_manual_page() {
	backyard_require_admin();
	echo '<div class="wrap"><h1>Backyard handleiding</h1><p>Stel onder Backyard → Instellingen de API base URL in, sla deze op en klik op Test verbinding.</p>';
	echo '<p>Een privé-adres zoals 192.168.1.31 werkt alleen als de WordPress-server dat netwerk kan bereiken.</p>';
	backyard_manual_commands();
	echo '<h2>Shortcodes</h2>';
	$entries = backyard_shortcode_docs();
	if ( ! $entries ) {
		echo '<p>Er zijn nog geen publieke shortcodes beschikbaar.</p>';
	} else {
		echo '<p>Klik op een shortcode om deze naar het klembord te kopiëren.</p>';
	}
	echo '<div class="backyard-shortcode-grid">';
	foreach ( $entries as $entry ) {
		echo '<section class="backyard-shortcode-tile backyard-copy-item"><h3>' . esc_html( $entry['module'] ) . '</h3>';
		echo '<button type="button" class="backyard-copy-shortcode" aria-label="' . esc_attr( 'Kopieer shortcode ' . $entry['shortcode'] ) . '"><code>' . esc_html( $entry['shortcode'] ) . '</code><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>';
		echo '<p class="backyard-copy-status" role="status" aria-live="polite"></p>';
		echo '<p>' . esc_html( $entry['description'] ) . '</p><dl>';
		foreach ( $entry['parameters'] as $name => $description ) {
			echo '<dt><code>' . esc_html( $name ) . '</code></dt><dd>' . esc_html( $description ) . '</dd>';
		}
		echo '</dl></section>';
	}
	echo '</div></div>';
}
