(() => {
    const dialog = document.querySelector('#backyard-correction');
    if (!dialog) return;
    const form = dialog.querySelector('form'), fields = form.elements;
    const message = dialog.querySelector('.correction-message');
    let current, id, requestId, pending = null, busy = false;
    const labels = { auto_accepted: 'Automatisch geaccepteerd', human_confirmed: 'Handmatig bevestigd', human_rejected: 'Handmatig afgewezen' };
    async function api(operation, values = {}) {
        const body = new URLSearchParams({ action: 'backyard_correction', nonce: backyardCorrections.nonce, operation, ...values });
        let response, result;
        try {
            response = await fetch(backyardCorrections.url, { method: 'POST', credentials: 'same-origin', body });
            result = await response.json();
        } catch (_) { throw new Error('Verbinding onderbroken. Bij opnieuw opslaan wordt exact hetzelfde verzoek gebruikt.'); }
        if (!response.ok || !result.success) {
            const error = new Error(result.data?.message || 'Actie niet bevestigd.');
            error.status = response.status;
            throw error;
        }
        return result.data;
    }
    function display(data) {
        current = data.observation; requestId = data.request_id; pending = null;
        const detail = dialog.querySelector('.correction-detail');
        detail.replaceChildren();
        const lines = [
            ['BirdNET oorspronkelijk', `${current.original_name} — ${current.original_scientific_name}`],
            ['Huidige soort / identiteit', `${current.scientific_name}${current.identity ? ' / Otje' : ''}`],
            ['Confidence', `${(Number(current.confidence) * 100).toFixed(1)}%`],
            ['Datum', current.date], ['Status', labels[current.status] || current.status]
        ];
        lines.forEach(([label, value]) => { const p = document.createElement('p'); p.textContent = `${label}: ${value}`; detail.append(p); });
        const audio = dialog.querySelector('audio'); audio.pause(); audio.removeAttribute('src'); audio.hidden = !current.audio;
        if (current.audio) audio.src = current.audio;
        fields.decision.value = current.status === 'human_rejected' ? 'reject' : 'confirm';
        Array.from(fields.decision.options).forEach(option => { option.disabled = !current.actions.includes(option.value); });
        fields.identity.value = current.identity || '';
        fields.species_mode.value = 'keep'; fields.species_query.value = ''; fields.species.replaceChildren(); fields.note.value = '';
        dialog.querySelector('.correction-search').hidden = true;
        form.querySelector('fieldset').disabled = false;
        fields.identity.disabled = fields.decision.value === 'reject';
    }
    async function load() {
        if (busy) return;
        busy = true; message.textContent = 'Waarneming ophalen…';
        try { display(await api('load', { id })); message.textContent = ''; }
        catch (error) { message.textContent = error.message; }
        finally { busy = false; }
    }
    document.addEventListener('click', (event) => {
        const button = event.target.closest('.backyard-edit');
        if (!button || busy) return;
        id = button.dataset.id; current = null; pending = null;
        dialog.querySelector('.correction-detail').replaceChildren();
        form.querySelector('fieldset').disabled = true;
        dialog.showModal(); load();
    });
    fields.species_mode.addEventListener('change', () => { dialog.querySelector('.correction-search').hidden = fields.species_mode.value !== 'choose'; });
    fields.decision.addEventListener('change', () => { fields.identity.disabled = fields.decision.value === 'reject'; });
    dialog.querySelector('.correction-find').addEventListener('click', async () => {
        if (busy) return;
        busy = true; message.textContent = 'Soorten zoeken…';
        fields.species.replaceChildren();
        try {
            const rows = await api('search', { q: fields.species_query.value });
            rows.forEach(row => { const option = document.createElement('option'); option.value = row.scientific_name; option.textContent = `${row.name} — ${row.scientific_name}`; fields.species.append(option); });
            message.textContent = rows.length ? 'Kies de juiste soort uit de catalogus.' : 'Geen soorten gevonden.';
        } catch (error) { message.textContent = error.message; }
        finally { busy = false; }
    });
    dialog.querySelector('.correction-close').addEventListener('click', () => { if (!busy) dialog.close(); });
    dialog.querySelector('.correction-reload').addEventListener('click', load);
    dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
    dialog.addEventListener('close', () => { dialog.querySelector('audio').pause(); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !current) return;
        if (!pending) {
            pending = { action: fields.decision.value, expected_status: current.status, expected_version: current.review_version,
                request_id: requestId, identity_override: fields.decision.value === 'reject' ? null : (fields.identity.value || null), note: fields.note.value };
            if (fields.species_mode.value === 'original') pending.scientific_name_override = null;
            if (fields.species_mode.value === 'choose') {
                if (!fields.species.value) { pending = null; message.textContent = 'Zoek en selecteer eerst een soort.'; return; }
                pending.scientific_name_override = fields.species.value;
            }
        }
        busy = true; form.querySelector('fieldset').disabled = true; message.textContent = 'Correctie opslaan…';
        let saved = false;
        try {
            const result = await api('save', { id, payload: JSON.stringify(pending) });
            saved = true; display(result);
            // Replace only the current admin content; do not reload the whole page.
            const response = await fetch(window.location.href, { credentials: 'same-origin', cache: 'no-store' });
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = page.querySelector('#wpbody-content > .wrap');
            const existing = document.querySelector('#wpbody-content > .wrap');
            if (!response.ok || !updated || !existing) throw new Error('Lijst kon niet worden vernieuwd.');
            existing.replaceWith(updated); window.backyardReviewInit(updated);
            const snapshot = await api('refresh');
            document.dispatchEvent(new CustomEvent('backyard:observations-updated', { detail: snapshot }));
            const summary = document.createElement('p');
            summary.textContent = `Actuele Backyard-totalen: ${snapshot.stats.all_time_observation_count} geldige waarnemingen, ${snapshot.stats.all_time_species_count} soorten. Review: ${snapshot.counts.review}; Otje: ${snapshot.counts.otje}.`;
            dialog.querySelector('.correction-detail').append(summary);
            message.textContent = 'Correctie opgeslagen. Waarneming, lijst, tellers en API-statistieken/Atlas opnieuw opgehaald.';
        } catch (error) {
            message.textContent = saved ? `Correctie opgeslagen, maar verversen niet volledig gelukt: ${error.message}` : error.message;
            if (!saved && error.status === 422) { pending = null; requestId = crypto.randomUUID(); form.querySelector('fieldset').disabled = false; }
            // Keep payload/UUID for uncertain retries; 409 requires an explicit reload.
            if (!saved && error.status === 409) current = null;
        } finally { busy = false; }
    });
})();
