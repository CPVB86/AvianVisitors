<?php
define( 'BACKYARD_TEST_BOOTSTRAP_ONLY', true );
require __DIR__ . '/test-review.php';
$html = backyard_manual_parameter_text( 'text, url, image of link.', backyard_parameter_choices( 'output', array( 'text', 'url', 'image', 'link' ) ) );
foreach ( array( 'text', 'url', 'image', 'link' ) as $value ) {
	check( strpos( $html, 'data-copy="output=&quot;' . $value . '&quot;"' ) !== false, 'Copy complete output attribute' );
}
check( substr_count( $html, 'backyard-copy-value' ) === 4, 'Exactly four inline choices' );
check( backyard_manual_parameter_text( '<example>' ) === '&lt;example&gt;', 'Plain descriptions remain escaped' );
$html = backyard_manual_parameter_text( 'first_seen', backyard_parameter_choices( 'field', array( 'first_seen', 'first_time' ) ) );
check( substr_count( $html, 'backyard-copy-value' ) === 2 && strpos( $html, 'backyard-parameter-choices' ) !== false, 'Unmentioned choices remain accessible' );
foreach ( backyard_shortcode_docs() as $entry ) {
	foreach ( $entry['parameter_choices'] ?? array() as $row => $choices ) {
		$html = backyard_manual_parameter_text( $entry['parameters'][ $row ], $choices );
		foreach ( $choices as $copy ) { check( strpos( $html, 'data-copy="' . esc_attr( $copy ) . '"' ) !== false, 'All registered choices rendered' ); }
	}
}
echo "Manual parameter copy checks passed.\n";
