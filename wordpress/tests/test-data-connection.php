<?php
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
require __DIR__ . '/test-review.php';
$case = $argv[1] ?? '500';
if ( 'ok' === $case ) {
	reply( 200, '{"module":"birds","period":"all","species":[],"stats":{"total_observations":0},"rankings":{}}' );
} else {
	reply( (int) $case, '{"detail":"SECRET private URL token"}' );
}
$status = backyard_data_connection_status();
check( strpos( $status, 'SECRET' ) === false && strpos( $status, 'private' ) === false, 'No response data leaked' );
check( strpos( $status, 'ok' === $case ? '0 geaccepteerde' : 'HTTP ' . $case ) !== false, 'Distinguish empty data from API status' );
$allowed = false;
try { backyard_data_connection_status(); throw new RuntimeException( 'Missing access control' ); } catch ( RuntimeException $e ) { check( $e->getMessage() === 'denied', 'Admin diagnostic requires capability' ); }
echo "Data diagnostic $case passed.\n";
