<?php
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
require __DIR__ . '/test-review.php';
function get_current_user_id() { return 42; }
$payload = array( 'action' => 'confirm', 'expected_status' => 'human_confirmed', 'expected_version' => 2,
	'request_id' => '5a71e10e-3de9-4a37-9d36-82d87e325bf9', 'identity_override' => null, 'note' => 'Koolmees', 'actor' => 'forged' );
$result = backyard_birds_correction_payload( $payload );
check( ! array_key_exists( 'scientific_name_override', $result ) && $result['actor'] === 'wordpress:42' && $result['request_id'] === $payload['request_id'], 'Preserve omission, UUID and trusted actor' );
foreach ( array( null, 'Parus major' ) as $value ) {
	check( backyard_birds_correction_payload( $payload + array( 'scientific_name_override' => $value ) )['scientific_name_override'] === $value, 'Distinguish restoring original from catalog selection' );
}
$row = array( 'id' => $payload['request_id'], 'domain' => 'bird', 'review_version' => 3, 'status' => 'human_confirmed', 'scientific_name' => 'Gallus gallus', 'common_name' => 'Red Junglefowl', 'timestamp' => '2026-10-08T12:00:00Z', 'confidence' => .9, 'effective_identity' => array( 'scientific_name' => 'Parus major' ), 'audio_url' => 'http://private/token', 'audio_available' => false );
$display = backyard_birds_correction_projection( $row );
check( $display['original_scientific_name'] === 'Gallus gallus' && $display['scientific_name'] === 'Parus major' && strpos( json_encode( $display ), 'private' ) === false, 'Separate evidence and effective species without private URL' );
echo "Correction payload/projection smoke checks passed.\n";
