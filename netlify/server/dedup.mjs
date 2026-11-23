export function soundex(word) {
  word = word.toUpperCase().replace(/[^A-Z]/g, ''); if (!word) return '';
  const codes = {B:1,F:3,P:1,V:1,C:2,G:2,J:2,K:2,Q:2,S:2,X:2,Z:2,D:3,T:3,L:4,M:5,N:5,R:6};
  let result=word[0], previous=codes[word[0]];
  for (const letter of word.slice(1)) { const code=codes[letter]; if (code && code!==previous) result+=code; if (!'HW'.includes(letter)) previous=code; }
  return (result+'000').slice(0,4);
}
export function similarity(a,b) {
  a=a.trim().toLowerCase(); b=b.trim().toLowerCase(); if(a===b) return 1; if(!a || !b) return 0;
  const window=Math.max(0,Math.floor(Math.max(a.length,b.length)/2)-1), fa=Array(a.length).fill(false), fb=Array(b.length).fill(false); let matches=0;
  for(let i=0;i<a.length;i++) for(let j=Math.max(0,i-window);j<Math.min(i+window+1,b.length);j++) if(!fb[j] && a[i]===b[j]) {fa[i]=true;fb[j]=true;matches++;break;}
  if(!matches) return 0;
  const aa=[...a].filter((_,i)=>fa[i]), bb=[...b].filter((_,i)=>fb[i]);
  const transpositions=Math.floor(aa.filter((x,i)=>x!==bb[i]).length/2);
  const jaro=(matches/a.length+matches/b.length+(matches-transpositions)/matches)/3;
  let prefix=0;while(prefix<Math.min(4,a.length,b.length) && a[prefix]===b[prefix]) prefix++;
  return +(jaro+prefix*.1*(1-jaro)).toFixed(4);
}
export function pipeline(records,threshold=.88) {
  const parent=new Map(records.map(r=>[r.id,r.id])), blocks=new Map(), edges=[];
  function find(id) { while(parent.get(id)!==id) {parent.set(id,parent.get(parent.get(id)));id=parent.get(id);} return id; }
  for(const r of records) {const key=soundex(r.fullName.trim().split(/\s+/).at(-1))+'::'+r.campLocation.trim().toLowerCase(); if(!blocks.has(key)) blocks.set(key,[]);blocks.get(key).push(r);}
  for(const block of blocks.values()) for(let i=0;i<block.length;i++) for(let j=i+1;j<block.length;j++) {
    const a=block[i],b=block[j],score=+(similarity(a.fullName,b.fullName)*.45+similarity(a.nationalId,b.nationalId)*.55).toFixed(4);
    if(score>=threshold) {parent.set(find(b.id),find(a.id));edges.push({a:a.id,score});}
  }
  const groups=new Map();for(const r of records) {const root=find(r.id);if(!groups.has(root)) groups.set(root,[]);groups.get(root).push(r.id);}
  return [...groups.entries()].filter(([,ids])=>ids.length>1).map(([root,ids])=>({ids:ids.sort((a,b)=>a-b),score:Math.max(...edges.filter(e=>find(e.a)===root).map(e=>e.score))})).sort((a,b)=>b.score-a.score);
}
export function validateRecord(input) {
  const result={fullName:String(input.fullName??'').trim(),nationalId:String(input.nationalId??'').trim(),householdSize:Number(input.householdSize),campLocation:String(input.campLocation??'').trim(),phone:String(input.phone??'').trim(),notes:String(input.notes??'').trim()};
  if(!result.fullName || result.fullName.length>120) throw new Error('Full name is required and must be at most 120 characters.');
  if(!/^\d{7,9}$/.test(result.nationalId)) throw new Error('National ID must be 7–9 digits.');
  if(!Number.isInteger(result.householdSize) || result.householdSize<1 || result.householdSize>10000) throw new Error('Household size must be a whole number between 1 and 10,000.');
  if(!result.campLocation || result.campLocation.length>120) throw new Error('Camp / location is required and must be at most 120 characters.');
  if(result.phone.length>30 || result.notes.length>10000) throw new Error('Phone or notes are too long.');
  return result;
}
export function csv(columns,rows) {
  return '\uFEFF'+[columns,...rows].map(row=>row.map(value=>{let v=String(value??'');if(/^[\s]*[=+@-]/.test(v)) v="'"+v;return '"'+v.replaceAll('"','""')+'"';}).join(',')).join('\r\n');
}
