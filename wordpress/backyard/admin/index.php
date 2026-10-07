<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_dashboard_setup', function () {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget( 'backyard_status', 'Backyard', 'backyard_index_page' );
		wp_add_dashboard_widget( 'backyard_otje', '🐔 Potentiële Otjes', 'backyard_birds_otje_dashboard' );
	}
} );

function backyard_index_page() {
	backyard_require_admin();
	// Native dashboard widget: WordPress provides positioning and Screen Options.
	foreach ( backyard_modules() as $module ) {
		if ( empty( $module['summary'] ) || ! is_callable( $module['summary'] ) ) {
			continue;
		}
		$summary = call_user_func( $module['summary'] );
		echo '<p><strong>' . esc_html( $module['title'] ) . '</strong> — ';
		if ( is_wp_error( $summary ) ) {
			echo 'Status tijdelijk niet beschikbaar.';
		} else {
			echo '<a href="' . esc_url( $summary['url'] ) . '">' . esc_html( $summary['text'] ) . '</a>';
		}
		echo '</p>';
	}
}
