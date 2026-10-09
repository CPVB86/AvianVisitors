<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/commands.php';
require_once __DIR__ . '/index.php';

add_action( 'admin_menu', function () {
	add_menu_page( 'Backyard', 'Backyard', 'manage_options', 'backyard', 'backyard_birds_admin_page', 'dashicons-carrot' );
	foreach ( backyard_modules() as $slug => $module ) {
		add_submenu_page( 'backyard', $module['title'], $module['title'], 'manage_options', 'backyard-' . $slug, function () use ( $module ) {
			backyard_require_admin();
			if ( ! empty( $module['admin_page'] ) && is_callable( $module['admin_page'] ) ) {
				call_user_func( $module['admin_page'] );
				return;
			}
			echo '<div class="wrap"><h1>' . esc_html( $module['title'] ) . '</h1><p>' . esc_html( $module['description'] ) . '</p></div>';
		} );
	}
	add_submenu_page( 'backyard', 'Backyard instellingen', 'Instellingen', 'manage_options', 'backyard-settings', 'backyard_settings_page' );
	add_submenu_page( 'backyard', 'Backyard handleiding', 'Handleiding', 'manage_options', 'backyard-manual', 'backyard_manual_page' );
	remove_submenu_page( 'backyard', 'backyard' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, array( 'index.php', 'backyard_page_backyard-manual', 'toplevel_page_backyard', 'backyard_page_backyard-settings', 'backyard_page_backyard-birds' ), true ) ) {
		return;
	}
	wp_enqueue_style( 'backyard-manual', plugins_url( 'manual.css', __FILE__ ), array(), '0.4.0' );
	if ( in_array( $hook, array( 'toplevel_page_backyard', 'backyard_page_backyard-birds' ), true ) ) {
		wp_enqueue_script( 'backyard-review', plugins_url( 'review.js', __FILE__ ), array(), '0.4.0', true );
		wp_enqueue_script( 'backyard-corrections', plugins_url( 'corrections.js', __FILE__ ), array( 'backyard-review' ), '0.4.1', true );
		wp_localize_script( 'backyard-corrections', 'backyardCorrections', array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'backyard_corrections' ) ) );
	}
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
	echo '<section class="backyard-card"><h2>Pi Connection</h2>';
	if ( null !== $result ) {
		$class  = is_wp_error( $result ) ? 'notice notice-error' : 'notice notice-success';
		$text   = is_wp_error( $result ) ? $result->get_error_message() : 'API bereikbaar — database ok.';
		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $text ) . '</p></div>';
	}
	do_settings_sections( 'backyard' );
	echo '<p>Sla wijzigingen eerst op. De test gebruikt de opgeslagen verbinding.</p>';
	submit_button( 'Test verbinding', 'secondary', 'backyard_test_connection', true, array( 'form' => 'backyard-connection-test' ) );
	echo '</section>';
	echo '<section class="backyard-card"><h2>Samsung Frame</h2>';
	backyard_frame_period_field();
	echo '</section>';
	submit_button( 'Instellingen opslaan' );
	echo '</form>';
	// Separate form keeps testing independent of unsaved settings and their nonce.
	echo '<form id="backyard-connection-test" method="post" action="' . esc_url( admin_url( 'admin.php?page=backyard-settings' ) ) . '">';
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
			echo '<div class="backyard-command backyard-shortcode-row backyard-copy-item"><div><strong>' . esc_html( $entry['title'] ?? $entry['shortcode'] ) . '</strong><p>' . esc_html( $entry['description'] ) . '</p></div>';
			echo '<button type="button" class="backyard-copy-shortcode" aria-label="' . esc_attr( 'Kopieer shortcode ' . $entry['shortcode'] ) . '"><code>' . esc_html( $entry['shortcode'] ) . '</code><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>';
			echo '<p class="backyard-copy-status screen-reader-text" role="status" aria-live="polite"></p>';
			if ( $entry['parameters'] ) {
				echo '<details class="backyard-parameters"><summary>Parameters</summary><table class="backyard-parameter-table"><thead><tr><th scope="col">Parameter</th><th scope="col">Toelichting</th></tr></thead><tbody>';
				foreach ( $entry['parameters'] as $name => $description ) {
					echo '<tr><th scope="row"><code>' . esc_html( $name ) . '</code></th><td>' . esc_html( $description );
					if ( isset( $entry['parameter_examples'][ $name ] ) ) {
						$example = $entry['parameter_examples'][ $name ];
						echo '<div class="backyard-copy-item backyard-parameter-example"><span>Voorbeeld:</span> <button type="button" class="backyard-copy-shortcode" aria-label="' . esc_attr( 'Kopieer voorbeeld ' . $example ) . '"><code><em>' . esc_html( $example ) . '</em></code><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button><span class="backyard-copy-status screen-reader-text" role="status" aria-live="polite"></span></div>';
					}
					echo '</td></tr>';
				}
				echo '</tbody></table></details>';
			}
			echo '</div>';
		}
		echo '</section>';
	}
	echo '</div>';
}
