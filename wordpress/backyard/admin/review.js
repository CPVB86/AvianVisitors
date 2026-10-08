document.querySelectorAll('.backyard-bulk-review').forEach((form) => {
    const rows = Array.from(form.querySelectorAll('input[name="observation_ids[]"]'));
    const all = form.querySelector('.backyard-select-all');
    const buttons = Array.from(form.querySelectorAll('button[name="decision"]'));
    let last = null;
    function update() {
        const selected = rows.filter((row) => row.checked);
        all.checked = rows.length > 0 && selected.length === rows.length;
        all.indeterminate = selected.length > 0 && selected.length < rows.length;
        form.querySelector('.backyard-selection-count').textContent = `${selected.length} geselecteerd`;
        buttons.forEach((button) => {
            button.disabled = !selected.length || (button.value === 'otje' && selected.some((row) => row.dataset.otje !== '1'));
        });
    }
    rows.forEach((row, index) => row.addEventListener('click', (event) => {
        if (event.shiftKey && last !== null) {
            rows.slice(Math.min(last, index), Math.max(last, index) + 1).forEach((item) => { item.checked = row.checked; });
        }
        last = index;
        update();
    }));
    all.addEventListener('change', () => {
        rows.forEach((row) => { row.checked = all.checked; });
        last = null;
        update();
    });
    form.addEventListener('submit', (event) => {
        if (!rows.some((row) => row.checked) || form.dataset.submitting === '1') { event.preventDefault(); return; }
        // Keep submit button enabled so its decision is included in the native POST.
        form.dataset.submitting = '1';
    });
    window.addEventListener('pageshow', () => { delete form.dataset.submitting; update(); });
    update();
});

document.querySelectorAll('.backyard-review-image').forEach((img) => {
        const fallback = () => {
            if (!img.dataset.fallback) return;
            const url = img.dataset.fallback;
            delete img.dataset.fallback;
            img.src = url;
        };
        img.addEventListener('error', fallback);
        if (img.complete && img.naturalWidth === 0) fallback();
    });
