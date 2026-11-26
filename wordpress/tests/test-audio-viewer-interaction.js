const assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm');
class Element {
    constructor() { this.events = {}; this.children = []; this.dataset = {}; this.hidden = false; this.value = '0'; }
    addEventListener(type, fn) { this.events[type] = fn; }
    append(child) { child.parent = this; this.children.push(child); }
    setAttribute() {}
    focus() { this.focused = true; }
    showModal() { this.open = true; }
    close() { this.open = false; this.events.close(); }
    replaceChildren(...children) { this.children = children; this.value = '0'; }
    add(option) { this.children.push(option); }
}
(async () => {
    const nodes = Object.fromEntries(['.backyard-audio-load','.backyard-audio-message','audio','canvas','.backyard-audio-display','.backyard-audio-placeholder','.backyard-audio-frequency','.backyard-audio-zoom','.backyard-audio-caption'].map(key => [key,new Element()]));
    const draws = [], ctx = new Proxy({}, {get: (_, name) => name === 'drawImage' ? (...args) => draws.push(args) : () => {}, set: () => true});
    nodes.canvas.getContext = () => ctx;
    const audio = nodes.audio; audio.paused = true; audio.currentTime = 0;
    audio.play = () => { audio.paused = false; return Promise.resolve(); };
    const box = new Element(); box.dataset.url = 'https://wp.test/admin-post.php?action=backyard_audio_view'; box.isConnected = true;
    box.querySelector = selector => nodes[selector];
    let requests = 0;
    const document = {body:{}, querySelectorAll:()=>[box], createElement:()=>new Element()};
    const context = { document, URL, Option:class{constructor(label,value){this.label=label;this.value=value;}},
        Image:class{constructor(){this.width=256;this.height=256;}set src(value){this.onload();}},
        MutationObserver:class{observe(){}}, fetch:async()=>{requests++;return {ok:true,json:async()=>({duration_seconds:10,time_expansion:1,frequency_max_hz:24000,waveform:[0,.1],spectrogram_png:'test',gain_db:0})};} };
    vm.runInNewContext(fs.readFileSync('wordpress/backyard/assets/audio-viewer.js','utf8'), context);
    assert.equal(requests,0,'Initialization must not fetch a preview');
    assert.equal(audio.src,undefined,'No audio request before loading');
    await nodes['.backyard-audio-load'].events.click();
    assert.equal(requests,1);
    assert.equal(nodes['.backyard-audio-placeholder'].hidden,true);
    const frequency = nodes['.backyard-audio-frequency'];
    assert.deepEqual(frequency.children.map(o=>o.value), ['0',4000,8000,12000]);
    frequency.value='4000'; frequency.events.change();
    const crop=draws.at(-1);
    assert.equal(crop[2],256-256/6); assert.equal(crop[4],256/6);
    audio.currentTime=3.25; audio.paused=false;
    nodes['.backyard-audio-zoom'].events.click();
    const dialog=box.children[0];
    assert.equal(dialog.open,true); assert.equal(nodes['.backyard-audio-display'].parent,dialog);
    dialog.close();
    assert.equal(nodes['.backyard-audio-display'].parent,box);
    assert.equal(audio.currentTime,3.25); assert.equal(audio.paused,false);
    assert.equal(requests,1,'Enlargement reuses loaded metadata');
    console.log('Lazy load, frequency crop, enlargement and player continuity checks passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
