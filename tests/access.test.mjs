import {test, before, after} from 'node:test';
import assert from 'node:assert/strict';
import app from '../netlify/server/index.ts';
let server,base;
before(async()=>{server=app.listen(0,'127.0.0.1');await new Promise(resolve=>server.once('listening',resolve));base=`http://127.0.0.1:${server.address().port}`;});
after(()=>new Promise(resolve=>server.close(resolve)));
test('health route works without touching the database',async()=>{
  const response=await fetch(base+'/api/health');assert.equal(response.status,200);assert.deepEqual(await response.json(),{status:'ok'});assert.equal(response.headers.get('cache-control'),'no-store');
});
test('all private APIs require authentication',async()=>{
  for(const route of ['me','dashboard','beneficiaries','beneficiaries/export','duplicates','reports','users','audit','audit/export']){
    const response=await fetch(base+'/api/'+route);assert.equal(response.status,401,route);
  }
});
test('cross-origin writes and malformed origins are rejected before authentication',async()=>{
  for(const origin of ['https://unrelated.example','not a URL']){
    const response=await fetch(base+'/api/beneficiaries',{method:'POST',headers:{origin,'Content-Type':'application/json'},body:'{}'});assert.equal(response.status,403);
  }
});
test('same-origin writes still require authentication',async()=>{
  const response=await fetch(base+'/api/beneficiaries',{method:'POST',headers:{origin:base,'Content-Type':'application/json'},body:'{}'});assert.equal(response.status,401);
});
test('legacy PHP links redirect into the hosted application without serving source',async()=>{
  const response=await fetch(base+'/beneficiary_form.php?id=7',{redirect:'manual'});assert.equal(response.status,302);assert.equal(response.headers.get('location'),'/#new?id=7');
  for(const path of ['/includes/config.php','/install.php','/sql/schema.sql']){const response=await fetch(base+path);assert.equal(response.status,404);assert.ok(!(await response.text()).includes('<?php'));}
});
test('session verification uses the current site and preserves authentication failures',async()=>{
  const originalFetch=globalThis.fetch;
  const previousUrl=process.env.URL;
  process.env.URL='https://obsolete-site.example';
  try {
    for(const status of [401,403,500]) {
      let checked=false;
      globalThis.fetch=async(url,options)=>{
        if(String(url).startsWith(base)) return originalFetch(url,options);
        checked=true;
        assert.equal(String(url),'https://rescueplan.netlify.app/.netlify/identity/user');
        assert.equal(options.headers.Authorization,'Bearer invalid-test-session');
        return new Response('{}',{status});
      };
      const response=await originalFetch(base+'/api/me',{headers:{cookie:'nf_jwt=invalid-test-session'}});
      assert.equal(checked,true);
      assert.equal(response.status,status===500?503:401);
    }
  } finally {
    globalThis.fetch=originalFetch;
    if(previousUrl===undefined) delete process.env.URL; else process.env.URL=previousUrl;
  }
});
test('unconfirmed Identity accounts cannot access the registry',async()=>{
  const originalFetch=globalThis.fetch;
  try {
    globalThis.fetch=async()=>Response.json({id:'unconfirmed-test-user',confirmed_at:null});
    const response=await originalFetch(base+'/api/me',{headers:{cookie:'nf_jwt=unconfirmed-test-session'}});
    assert.equal(response.status,401);
    assert.match((await response.json()).error,/Confirm your email/);
  } finally {globalThis.fetch=originalFetch;}
});
