<?php
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
require __DIR__ . '/test-review.php';
$mode = $argv[1] ?? 'success';
if ( 'invalid' === $mode || 'denied' === $mode || 'disabled' === $mode ) {
	$allowed = 'denied' !== $mode;
	$value = 'invalid' === $mode ? 'invalid' : ( 'disabled' === $mode ? null : '1h' );
	check( backyard_save_frame_period( $value ) === $option && ! $requests, 'Invalid, disabled or unauthorized field does not call Pi' );
	check( backyard_save_frame_period( '24h' ) === $option && ! $requests, 'Second Settings API sanitization still does not call Pi' );
} elseif ( 'failure' === $mode ) {
	reply( 503, '{}' );
	check( backyard_save_frame_period( '7d' ) === $option && $GLOBALS['setting_error'], 'Failed Pi save preserves previous setting and reports failure' );
} else {
	reply( 200, '{"period":"all"}' );
	check( backyard_save_frame_period( 'all' ) === 'all', 'Persist confirmed Pi setting' );
	check( backyard_save_frame_period( 'all' ) === 'all' && count( $requests ) === 1, 'Double sanitization does not repeat the write' );
	check( $requests[0][0] === $option . '/api/avian-collage/settings'
		&& $requests[0][1]['headers']['Authorization'] === 'Bearer ' . $token
		&& json_decode( $requests[0][1]['body'], true ) === array( 'period' => 'all' ), 'Use authenticated existing client and period contract' );
	ob_start(); backyard_frame_period_field(); $html = ob_get_clean();
	check( strpos( $html, 'value="all" selected' ) !== false && strpos( $html, $token ) === false, 'Display Pi value without secrets' );
}
echo "Frame period $mode checks passed.\n";
