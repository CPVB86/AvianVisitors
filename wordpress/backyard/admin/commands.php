<?php
defined( 'ABSPATH' ) || exit;

function backyard_manual_commands() {
	$ssh = 'ssh cpvb86@192.168.1.31';
	$status = 'cd ~/Backyard && sudo .venv/bin/python -m operations.status';
	$logs = 'sudo journalctl -u backyard-detector -f';
	// Ask in the terminal, never embed the saved WordPress token in documentation.
	$auth = ' -Headers @{ Authorization = "Bearer " + [System.Net.NetworkCredential]::new("", (Read-Host "API-token" -AsSecureString)).Password }';
	$public_test = 'Invoke-RestMethod https://backyard.tail99c3bd.ts.net/api/health' . $auth;
	echo '<section class="backyard-card"><h2>Powershell en Pi CMD</h2>';
	echo '<div class="backyard-commands">';
	foreach ( array(
		array( 'SSH verbinden · Windows', $ssh ),
		array( 'Backyard status · Pi na SSH', $status ),
		array( 'Live detectorlog · Pi na SSH', $logs ),
		array( 'Publieke API-test · Windows', $public_test ),
	) as $command ) {
		backyard_manual_command( $command[0], $command[1] );
	}
	echo '</div><details class="backyard-command-details"><summary>Meer commando’s · Windows en Raspberry Pi</summary>';
	echo '<h3>PowerShell (Windows)</h3><div class="backyard-commands">';
	foreach ( array(
		'Pi pingen' => 'ping 192.168.1.31',
		'Lokale API testen' => 'Invoke-RestMethod http://192.168.1.31:8010/api/health' . $auth,
		'Pi-status vanaf Windows' => $ssh . ' -t "' . $status . '"',
		'Services vanaf Windows' => $ssh . ' "systemctl status backyard-api backyard-detector --no-pager"',
	) as $label => $command ) {
		backyard_manual_command( $label, $command );
	}
	echo '</div><h3>Raspberry Pi (na SSH)</h3><p>Services worden via systemd beheerd: start geen tweede Uvicorn-proces handmatig. Herstarten onderbreekt kort de betreffende service. Stop live logs met Ctrl+C.</p><div class="backyard-commands">';
	foreach ( array(
		'Uitgebreide status + database' => $status . ' --json --check-db',
		'API-status' => 'systemctl status backyard-api --no-pager',
		'Detectorstatus' => 'systemctl status backyard-detector --no-pager',
		'Beide herstarten' => 'sudo systemctl restart backyard-api backyard-detector',
		'API herstarten' => 'sudo systemctl restart backyard-api',
		'Detector herstarten' => 'sudo systemctl restart backyard-detector',
		'Live API-log' => 'sudo journalctl -u backyard-api -f',
		'Laatste detectorlogs' => 'sudo journalctl -u backyard-detector -n 50 --no-pager',
		'Tailscale-status' => 'tailscale status',
		'Funnel-status' => 'tailscale funnel status',
		'Git-versie' => 'cd ~/Backyard && git log -1 --oneline',
		'Nieuwe versie ophalen · huidige trackingbranch' => 'cd ~/Backyard && git pull --ff-only',
		'Temperatuur' => 'vcgencmd measure_temp',
		'Schijfruimte' => 'df -h /',
	) as $label => $command ) {
		backyard_manual_command( $label, $command );
	}
	echo '</div></details></section>';
}

function backyard_manual_command( $label, $command ) {
	echo '<div class="backyard-command backyard-copy-item"><strong>' . esc_html( $label ) . '</strong>';
	echo '<button type="button" class="backyard-copy-shortcode" aria-label="' . esc_attr( 'Kopieer: ' . $label ) . '"><code>' . esc_html( $command ) . '</code><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>';
	echo '<p class="backyard-copy-status screen-reader-text" role="status" aria-live="polite"></p></div>';
}
