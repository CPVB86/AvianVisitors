const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../backyard/admin/corrections.js'), 'utf8');
function run(readyState) {
    const handlers = {};
    let mounted = readyState !== 'loading', opened = false, requested = false;
    const node = { addEventListener() {}, replaceChildren() {}, pause() {}, elements: {} };
    node.querySelector = () => node;
    node.elements.species_mode = node;
    node.elements.decision = node;
    const dialog = { querySelector: () => node, addEventListener() {}, showModal() { opened = true; } };
    const document = { readyState, querySelector: () => mounted ? dialog : null, addEventListener(name, fn) { handlers[name] = fn; } };
    vm.runInNewContext(source, { document, URLSearchParams, backyardCorrections: { url: '/admin-ajax.php', nonce: 'test' },
        fetch() { requested = true; return new Promise(() => {}); } });
    if (!mounted) {
        assert.equal(handlers.click, undefined);
        mounted = true;
        handlers.DOMContentLoaded();
    }
    assert.equal(typeof handlers.click, 'function');
    handlers.click({ target: { closest: () => ({ dataset: { id: 'test-id' } }) } });
    assert.equal(opened, true);
    assert.equal(requested, true);
}
run('loading');
run('complete');
console.log('Editor opens and loads details with early or late script execution.');
