/* Clipboard interaction is confined to the admin manual; no API requests. */
document.querySelectorAll('.backyard-copy-shortcode').forEach((button) => {
	let highlightTimer;
	button.addEventListener('click', async () => {
		const code = button.querySelector('code');
		const copyText = button.dataset.copy ?? code.textContent;
		const status = button.closest('.backyard-copy-item').querySelector('.backyard-copy-status');
		status.textContent = '';
		status.classList.add('screen-reader-text');
		clearTimeout(highlightTimer);
		button.classList.remove('is-copied');
		let copied = false;
		try {
			await navigator.clipboard.writeText(copyText);
			copied = true;
		} catch (error) {
			// Fallback for HTTP admin pages or browsers without Clipboard API access.
			const input = document.createElement('textarea');
			input.value = copyText;
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

/* Local filtering; no remote requests or dependency on the Lucide website. */
const iconBrowser = document.querySelector('.backyard-icon-browser');
if (iconBrowser) {
	const search = iconBrowser.querySelector('input[type="search"]');
	const tiles = [...iconBrowser.querySelectorAll('[data-icon-name]')];
	const counter = iconBrowser.querySelector('.backyard-icon-count');
	const empty = iconBrowser.querySelector('.backyard-icon-empty');
	search.addEventListener('input', () => {
		const terms = search.value.toLowerCase().trim().split(/[\s_-]+/).filter(Boolean);
		let count = 0;
		tiles.forEach(tile => {
			const matches = terms.every(term => tile.dataset.iconName.includes(term));
			tile.hidden = !matches;
			if (matches) count++;
		});
		counter.textContent = `${count} van ${tiles.length} iconen`;
		empty.hidden = count !== 0;
	});
}
