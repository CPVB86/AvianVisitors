const assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm');
const context = {document:{querySelectorAll:()=>[], body:{}}, MutationObserver:class {observe(){}}};
vm.createContext(context);
vm.runInContext(fs.readFileSync('wordpress/backyard/assets/audio-viewer.js','utf8'),context);
assert.equal(context.backyardAudioOriginalTime(30,10,10),3);
assert.equal(context.backyardAudioOriginalTime(3,1,10),3);
assert.equal(context.backyardAudioOriginalTime(200,10,10),10);
vm.runInContext(fs.readFileSync('wordpress/backyard/admin/audio-builder.js','utf8'),context);
const id='12345678-1234-4234-8234-123456789abc';
assert.equal(context.backyardAudioShortcode('bats',id),`[backyard_audio module="bats" observation_id="${id}"]`);
assert.equal(context.backyardAudioShortcode('birds','"bad"]'),'');
assert.equal(context.backyardAudioShortcode('other',id),'');
console.log('Audio cursor timing and shortcode builder checks passed.');
assert.match(context.backyardAudioShortcode('birds',id,'no'), /spectrogram="no"/);

assert.equal(context.backyardAudioColorCaption({}), 'Kleur: -90 tot 0 dBFS.');
assert.equal(context.backyardAudioColorCaption({spectrogram_dbfs_min:-92,spectrogram_dbfs_max:-32,spectrogram_scale:'adaptive'}),
    'Kleur: -92 tot -32 dBFS. Contrast per opname; lichtgeel = sterk signaal.');
console.log('Spectrogram scale caption checks passed.');
