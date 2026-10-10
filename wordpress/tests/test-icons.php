<?php
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
require __DIR__ . '/test-review.php';
$html = backyard_icon_shortcode( array( 'name' => 'bird', 'label' => 'Vogel "test"', 'size' => '32', 'color' => '#2271b1' ) );
check( strpos( $html, '<svg ' ) === 0 && strpos( $html, 'width="32"' ) !== false && strpos( $html, 'stroke="#2271b1"' ) !== false, 'Render selected inline icon' );
check( strpos( $html, 'aria-label="Vogel &quot;test&quot;"' ) !== false, 'Accessible label is escaped' );
check( strpos( backyard_icon_shortcode(), 'aria-hidden="true"' ) !== false, 'Unlabelled icon is decorative' );
foreach ( array( '../secret', '<script>', 'missing-icon-name' ) as $name ) { check( backyard_icon_shortcode( array( 'name' => $name ) ) === '', 'Reject invalid/unknown icon' ); }
$html = backyard_icon_shortcode( array( 'size' => 999, 'stroke' => 99, 'color' => 'red" onload="alert(1)' ) );
check( strpos( $html, 'width="256"' ) !== false && strpos( $html, 'stroke-width="4"' ) !== false && strpos( $html, 'onload' ) === false, 'Bound dimensions and reject injected color' );
$tabs = apply_filters( 'elementor/icons_manager/additional_tabs', array() );
$tab = $tabs['backyard-lucide'];
check( $tab['prefix'] === 'by-lucide-' && $tab['displayPrefix'] === 'backyard-lucide' && strpos( $tab['url'], '/wp-content/plugins/backyard/' ) === 0, 'Elementor loads local scoped font' );
$dir = dirname( __DIR__ ) . '/backyard/assets/lucide/';
$list = json_decode( file_get_contents( $dir . 'elementor.json' ), true )['icons'];
$css = file_get_contents( $dir . 'lucide.css' );
foreach ( $list as $name ) { check( isset( backyard_lucide_icons()[ $name ] ) && strpos( $css, '.by-lucide-' . $name . '::before' ) !== false, 'SVG/picker/font names match' ); }
check( count( $list ) === 2134 && file_exists( $dir . 'lucide.woff2' ) && file_exists( $dir . 'LICENSE' ), 'Complete licensed local set' );
check( ! $requests, 'Icons never call an API' );
echo "Lucide shortcode and Elementor registration checks passed.\n";

ob_start(); backyard_manual_icons(); $browser = ob_get_clean();
check( substr_count( $browser, 'data-icon-name=' ) === 2134, 'Every bundled icon is browsable' );
check( strpos( $browser, 'title="bird"' ) !== false && strpos( $browser, 'data-copy="[backyard_icon name=&quot;bird&quot;]"' ) !== false, 'Tooltip and complete copy text' );
