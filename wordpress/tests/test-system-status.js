const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../backyard/assets/system-status.js'), 'utf8');
let now=100000, timer, observe;
const listeners = {};
function row(state, expires) {
    const label={textContent:'Onbekend'}, classes=new Set(['is-unknown']);
    return { dataset:{ status:state, expires:String(expires) }, label, classes,
        classList:{ remove(...names){names.forEach(n=>classes.delete(n));}, add(name){classes.add(name);} }, querySelector(){return label;} };
}
const rows=[row('online',160),row('offline',160),row('unknown',160),row('online',90)];
vm.runInNewContext(source, {
    Date:{now:()=>now}, document:{readyState:'complete',documentElement:{},querySelectorAll:()=>rows,addEventListener:(k,v)=>listeners[k]=v},
    window:{addEventListener:(k,v)=>listeners[k]=v}, clearTimeout(){}, setTimeout(fn){timer=fn;},
    MutationObserver:class{constructor(fn){observe=fn;} observe(){} }
});
assert.equal(rows[0].label.textContent,'Online'); assert(rows[0].classes.has('is-online'));
assert.equal(rows[1].label.textContent,'Offline'); assert(rows[1].classes.has('is-offline'));
assert.equal(rows[2].label.textContent,'Onbekend'); assert.equal(rows[3].label.textContent,'Onbekend');
now=160001; timer(); assert(rows.every(r=>r.label.textContent==='Onbekend')); assert(!rows[0].classes.has('is-online'));
const added=row('online',220); rows.push(added);
observe([{addedNodes:[{nodeType:1,matches:()=>true}]}]); assert.equal(added.label.textContent,'Online');
now=230000; listeners.visibilitychange(); listeners.pageshow(); assert.equal(added.label.textContent,'Onbekend');
console.log('Status freshness: green/red/grey, expiry, cached HTML, editor insertion and resumed tabs passed; no network APIs provided.');
