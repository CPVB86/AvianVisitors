<?php
defined( 'ABSPATH' ) || exit;

function backyard_index_page() {
	backyard_require_admin();
	echo '<div class="wrap"><h1>Backyard</h1><div class="backyard-summary-grid">';
	// Each module may supply its own summary without changing this page.
	foreach ( backyard_modules() as $module ) {
		if ( empty( $module['summary'] ) || ! is_callable( $module['summary'] ) ) {
			continue;
		}
		$summary = call_user_func( $module['summary'] );
		echo '<section class="backyard-card"><h2>' . esc_html( $module['title'] ) . '</h2><p>';
		if ( is_wp_error( $summary ) ) {
			echo 'Status tijdelijk niet beschikbaar.';
		} else {
			echo '<a href="' . esc_url( $summary['url'] ) . '">' . esc_html( $summary['text'] ) . '</a>';
		}
		echo '</p></section>';
	}
	echo '</div></div>';
}
