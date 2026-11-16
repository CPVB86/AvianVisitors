function backyardAudioShortcode(module, id) {
    return ['birds','bats'].includes(module) && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/.test(id)
        ? `[backyard_audio module="${module}" observation_id="${id}"]` : '';
}
document.querySelectorAll('.backyard-audio-builder').forEach(box => {
    const update = () => {
        const code = backyardAudioShortcode(box.querySelector('.audio-module').value, box.querySelector('.audio-id').value.trim());
        const button = box.querySelector('.backyard-copy-shortcode');
        button.disabled = !code; button.dataset.copy = code;
        box.querySelector('.audio-shortcode').textContent = code || 'Vul een geldig observation-ID in.';
    };
    box.addEventListener('input', update);
    box.addEventListener('change', update);
});