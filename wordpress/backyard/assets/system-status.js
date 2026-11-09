/* Local freshness guard only: no requests, polling or credentials. */
(() => {
    const labels = { online: 'Online', offline: 'Offline', unknown: 'Onbekend' };
    let timer;
    function refresh() {
        clearTimeout(timer);
        const now = Date.now();
        let next = Infinity;
        document.querySelectorAll('.backyard-system-status[data-status]').forEach(node => {
            const expires = Number(node.dataset.expires) * 1000;
            const fresh = Number.isFinite(expires) && expires > now && expires <= now + 65000;
            let state = node.dataset.status;
            if (!Object.prototype.hasOwnProperty.call(labels, state)) state = 'unknown';
            if ((expires > 0 && !fresh) || (state === 'online' && !fresh)) state = 'unknown';
            node.classList.remove('is-online', 'is-offline', 'is-unknown');
            node.classList.add('is-' + state);
            const label = node.querySelector('.backyard-status-value');
            if (label && label.textContent !== labels[state]) label.textContent = labels[state];
            if (fresh) next = Math.min(next, expires - now);
        });
        if (Number.isFinite(next)) timer = setTimeout(refresh, next + 10);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refresh, { once: true });
    else refresh();
    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('pageshow', refresh);
    // Elementor inserts/re-renders widget markup in its editor after page load.
    new MutationObserver(records => {
        if (records.some(record => [...record.addedNodes].some(node => node.nodeType === 1 &&
            (node.matches('.backyard-system-status') || node.querySelector('.backyard-system-status'))))) refresh();
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
