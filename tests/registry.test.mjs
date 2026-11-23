import {test} from 'node:test';
import assert from 'node:assert/strict';
import {similarity,pipeline,validateRecord,csv} from '../netlify/server/dedup.mjs';
test('Jaro-Winkler scores known name variants and handles empty strings',()=>{
  assert.equal(similarity('MARTHA','MARHTA'),.9611);
  assert.equal(similarity('','a'),0);
  assert.equal(similarity(' Amina ','amina'),1);
});
test('dedup blocks by camp, fuses transitive candidates, and retains strongest score',()=>{
  const records=[{id:1,fullName:'Amina Wanyama',nationalId:'73450219',campLocation:'Kisumu'}, {id:2,fullName:'Amina Wanyama',nationalId:'73450218',campLocation:'Kisumu'}, {id:3,fullName:'Amina Wanyama',nationalId:'73450217',campLocation:'Kisumu'}, {id:4,fullName:'Amina Wanyama',nationalId:'73450219',campLocation:'Other camp'}];
  const clusters=pipeline(records);assert.equal(clusters.length,1);assert.deepEqual(clusters[0].ids,[1,2,3]);assert.ok(clusters[0].score>=.88);
});
test('record validation preserves national ID leading zeroes and rejects unsafe fields',()=>{
  const record={fullName:' Amina Wanyama ',nationalId:'01234567',householdSize:'5',campLocation:'Kisumu'};
  assert.equal(validateRecord(record).nationalId,'01234567');assert.equal(validateRecord(record).fullName,'Amina Wanyama');
  for(const invalid of [{householdSize:0},{householdSize:1.5},{nationalId:'abc'},{nationalId:'123'},{fullName:''},{campLocation:''}]) assert.throws(()=>validateRecord({...record,...invalid}));
});
test('CSV exports quote data, preserve multiline cells, and neutralize formulas',()=>{
  const output=csv(['Name','Notes'],[['Amina, Wanyama','a"b\nc'],['=HYPERLINK("x")','@SUM(1)']]);
  assert.ok(output.startsWith('\uFEFF'));assert.ok(output.includes('"Amina, Wanyama"'));assert.ok(output.includes('"a""b\nc"'));assert.ok(output.includes('"\'=HYPERLINK'));assert.ok(output.includes('"\'@SUM(1)"'));
});
