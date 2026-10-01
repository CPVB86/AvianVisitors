/* Clipboard interaction is confined to the admin manual; no API requests. */
document.querySelectorAll('.backyard-copy-shortcode').forEach((button) => {
	let highlightTimer;
	button.addEventListener('click', async () => {
		const code = button.querySelector('code');
		const status = button.closest('.backyard-copy-item').querySelector('.backyard-copy-status');
		status.textContent = '';
		status.classList.add('screen-reader-text');
		clearTimeout(highlightTimer);
		button.classList.remove('is-copied');
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
		if (copied) {
			// Restart the glow on repeated clicks; retain feedback for screen readers.
			void button.offsetWidth;
			button.classList.add('is-copied');
			status.textContent = 'Gekopieerd.';
			highlightTimer = setTimeout(() => button.classList.remove('is-copied'), 1200);
		} else {
			status.classList.remove('screen-reader-text');
			status.textContent = 'Kopiëren is geblokkeerd. Selecteer de code en kopieer deze handmatig.';
		}
	});
});
