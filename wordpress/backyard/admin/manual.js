/* Clipboard interaction is confined to the admin manual; no API requests. */
document.querySelectorAll('.backyard-copy-shortcode').forEach((button) => {
	button.addEventListener('click', async () => {
		const code = button.querySelector('code');
		const status = button.closest('.backyard-shortcode-tile').querySelector('.backyard-copy-status');
		status.textContent = '';
		let copied = false;
		try {
			await navigator.clipboard.writeText(code.textContent);
			copied = true;
		} catch (error) {
			// Fallback for HTTP admin pages or browsers without Clipboard API access.
			const input = document.createElement('textarea');
			input.value = code.textContent;
			input.readOnly = true;
			input.style.position = 'fixed';
			input.style.opacity = '0';
			document.body.appendChild(input);
			input.select();
			try {
				copied = document.execCommand('copy');
			} catch (fallbackError) {
				copied = false;
			} finally {
				input.remove();
				button.focus();
			}
		}
		status.textContent = copied
			? 'Gekopieerd naar het klembord.'
			: 'Kopiëren is geblokkeerd. Selecteer de code en kopieer deze handmatig.';
	});
});
