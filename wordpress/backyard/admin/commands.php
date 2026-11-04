<?php
defined( 'ABSPATH' ) || exit;

function backyard_manual_commands() {
	$ssh = 'ssh cpvb86@192.168.1.31';
	$status = 'cd ~/Backyard && sudo .venv/bin/python -m operations.status';
	$logs = 'sudo journalctl -u backyard-birds -u backyard-bats -f';
	// Ask in the terminal, never embed the saved WordPress token in documentation.
	$auth = ' -Headers @{ Authorization = "Bearer " + [System.Net.NetworkCredential]::new("", (Read-Host "API-token" -AsSecureString)).Password }';
	$public_test = 'Invoke-RestMethod https://backyard.tail99c3bd.ts.net/api/health' . $auth;
	echo '<section class="backyard-card"><h2>Powershell en Pi CMD</h2>';
	$remote = 'byremote' === ( $_GET['cmd_host'] ?? '' );
	echo '<nav id="backyard-commands" class="nav-tab-wrapper" aria-label="Commandos per computer">';
	foreach ( array( 'backyard' => 'Backyard', 'byremote' => 'BYRemote' ) as $key => $label ) {
		$active = $remote === ( 'byremote' === $key );
		$url = add_query_arg( array( 'page' => 'backyard-manual', 'cmd_host' => $key ), admin_url( 'admin.php' ) ) . '#backyard-commands';
		echo '<a class="nav-tab' . ( $active ? ' nav-tab-active' : '' ) . '" ' . ( $active ? 'aria-current="page" ' : '' ) . 'href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
	if ( $remote ) {
		echo '<p>BYRemote neemt beide microfoons op en streamt hun audio naar Backyard.</p><div class="backyard-commands">';
		backyard_manual_command( 'SSH verbinden · Windows', 'ssh cpvb86@192.168.1.105' );
		backyard_manual_command( 'Microfoonservices · na SSH', 'systemctl --no-pager status byremote-audio@birds byremote-audio@bats' );
		backyard_manual_command( 'Live microfoonlogs · na SSH', 'sudo journalctl -u byremote-audio@birds -u byremote-audio@bats -f' );
		echo '</div></section>';
		return;
	}
	echo '<p>Op Backyard verwerkt één BirdNET-Go-instantie beide streams. De vogel- en vleermuisbridge importeren waarnemingen en audio in de Backyard API. WordPress leest uit die API.</p>';
	echo '<div class="backyard-commands">';
	foreach ( array(
		array( 'SSH verbinden · Windows', $ssh ),
		array( 'Naar Backyard-map · Pi na SSH', 'cd ~/Backyard' ),
		array( 'Backyard status · Pi na SSH', $status ),
		array( 'Live importlogs · Pi na SSH', $logs ),
		array( 'Backyard- en OpenAI-tokens tonen · Pi na SSH', "sudo sed -n -e '/^BACKYARD_API_TOKEN=/p' -e '/^OPENAI_API_KEY=/p' /etc/backyard/backyard.env" ),
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
		'Services vanaf Windows' => $ssh . ' "systemctl status backyard-api backyard-birdnet-go backyard-birds backyard-bats --no-pager"',
		'Laatste drie waarnemingen · Windows' => '(Invoke-RestMethod "http://192.168.1.31:8010/api/observations?limit=3"' . $auth . ') | Select-Object timestamp,domain,scientific_name,status,classification,audio_available',
	) as $label => $command ) {
		backyard_manual_command( $label, $command );
	}
	echo '</div><h3>Raspberry Pi (na SSH)</h3><p>Services worden via systemd beheerd: start geen tweede Uvicorn-proces handmatig. Herstarten onderbreekt kort de betreffende service. Stop live logs met Ctrl+C.</p><div class="backyard-commands">';
	foreach ( array(
		'Uitgebreide status + database' => $status . ' --json --check-db',
		'API-status' => 'systemctl status backyard-api --no-pager',
		'BirdNET-Go en bridges' => 'systemctl status backyard-birdnet-go backyard-birds backyard-bats --no-pager',
		'API herstarten' => 'sudo systemctl restart backyard-api',
		'Bridges herstarten' => 'sudo systemctl restart backyard-birds backyard-bats',
		'Live API-log' => 'sudo journalctl -u backyard-api -f',
		'Laatste importlogs' => 'sudo journalctl -u backyard-birds -u backyard-bats -n 50 --no-pager',
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
