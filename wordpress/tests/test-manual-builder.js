const assert = require('node:assert/strict');
const fs = require('node:fs'), vm = require('node:vm'), path = require('node:path');
const context = {document:{querySelector:()=>null}};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname,'../backyard/admin/manual-builder.js'),'utf8'),context);
const config={speciesFields:['name','last_seen','perched','wikipedia_url','count'],statsFields:['total_observations','last_activity','today_species']};
const defaults={module:'birds',type:'last',field:'name',period:'all',rank:'1',identity:'',species:'',format:'',fallback:'',output:'text'};
const resolve = a=>context.backyardBuilderResolve({...defaults,...a},config);
assert.equal(resolve({type:'stats',field:'last_seen',period:'today'}).code,'[backyard_data module="birds" type="stats" field="last_activity" period="today"]');
assert.equal(resolve({field:'perched'}).a.output,'image');
assert.equal(resolve({field:'wikipedia_url',output:'image'}).a.output,'url');
assert.equal(resolve({output:'image'}).a.output,'text');
assert.equal(resolve({module:'bats',identity:'otje'}).a.identity,'');
assert.equal(resolve({identity:'otje',species:'Gallus gallus',rank:'9'}).a.species,'');
assert.equal(resolve({identity:'otje',rank:'9'}).a.rank,'1');
assert.equal(resolve({type:'species'}).code,'');
assert(resolve({type:'species',species:'Erithacus rubecula'}).code.includes('species="Erithacus rubecula"'));
assert.equal(resolve({fallback:'"] [injected]'}).code,'');
assert.equal(resolve({type:'stats',field:'today_species',period:'7d'}).a.period,'today');
assert(!resolve({format:'Y'}).code.includes('format='));
assert(resolve({field:'last_seen',format:'Y'}).code.includes('format="Y"'));
for(const module of ['birds','bats']) for(const type of ['last','most','first','rarest','random','species','stats']) for(const field of [...config.speciesFields,...config.statsFields]) {
 const result=resolve({module,type,field,species:'Parus major',output:'image',identity:'otje'});
 assert((type==='stats'?config.statsFields:config.speciesFields).includes(result.a.field));
 assert(result.outputs.includes(result.a.output));
 assert(!(result.a.identity && result.a.species));
}
console.log('Builder selection, dependent options and safe serialization checks passed.');
