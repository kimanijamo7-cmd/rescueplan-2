import {login,signup,logout,getUser,handleAuthCallback,requestPasswordRecovery,updateUser,acceptInvite} from '@netlify/identity';
import './styles.css';
const root=document.querySelector('#app');
let me=null, notice='', recovery=false, inviteToken=null, renderVersion=0;
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const label=v=>String(v??'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());
const date=v=>v?new Date(v).toLocaleString():'—';
const badge=v=>`<span class="status status-${esc(v)}">${esc(label(v))}</span>`;
const options=(values,selected='')=>values.map(v=>`<option value="${esc(v)}" ${v===selected?'selected':''}>${esc(label(v))}</option>`).join('');
const empty=text=>`<p class="empty muted">${esc(text)}</p>`;
const table=(columns,rows)=>rows.length?`<div class="table-wrap"><table class="data"><thead><tr>${columns.map(c=>`<th>${esc(c)}</th>`).join('')}</tr></thead><tbody>${rows.map(row=>`<tr>${row.map(v=>`<td>${v}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`:empty('No records match this view.');
function route(){const [name,query='']=location.hash.slice(1).split('?');return {name:name||'dashboard',params:new URLSearchParams(query)};}
async function api(path,method='GET',data){
  await getUser(); // Refresh the Identity session before sending its cookie.
  const response=await fetch('/api/'+path,{method,credentials:'same-origin',headers:data?{'Content-Type':'application/json'}:{},body:data?JSON.stringify(data):undefined});
  if(response.status===401){me=null;location.hash='login';}
  if(!response.ok){let error;try{error=await response.json();}catch{error={};}throw new Error(error.error||'The request could not be completed. Please try again.');}
  return response.headers.get('content-type')?.includes('application/json')?response.json():response.blob();
}
async function download(path,name){const blob=await api(path),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}
function flash(text,error=false){let el=document.querySelector('#message');if(!el)return;el.hidden=false;el.className='flash '+(error?'flash-error':'flash-success');el.textContent=text;el.scrollIntoView({block:'nearest'});}
function formData(form){return Object.fromEntries(new FormData(form));}
function field(name,title,value='',type='text',extra=''){return `<div><label for="${name}">${title}</label><input id="${name}" name="${name}" type="${type}" value="${esc(value)}" ${extra}></div>`;}
const navItems=[['dashboard','Dashboard'],['beneficiaries','Beneficiaries'],['new','New record'],['duplicates','Duplicates'],['import','Import',true],['reports','Reports'],['users','Users'],['audit','Audit log']];
function shell(content,name){
  root.innerHTML=`${me?`<header class="topbar"><a class="brand" href="#dashboard"><span class="brand-mark">RP</span><span>RescuePlan Kenya</span></a><nav aria-label="Main navigation"><ul class="nav-links">${navItems.filter(n=>!n[2]||me.role==='administrator').map(n=>`<li><a ${name===n[0]?'aria-current="page" class="active"':''} href="#${n[0]}">${n[1]}</a></li>`).join('')}</ul></nav><div class="nav-user"><span>${esc(me.name)}</span><span class="chip">${esc(label(me.role))}</span><button class="btn btn-ghost btn-sm" id="logout">Log out</button></div></header>`:''}<main><div id="message" role="alert" ${notice?'':'hidden'} class="flash flash-success">${esc(notice)}</div>${content}</main><footer class="foot">RescuePlan Kenya · Beneficiary Record Deduplication System</footer>`;
  notice='';document.querySelector('#logout')?.addEventListener('click',async()=>{await logout();me=null;location.hash='login';});
  document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',async event=>{
    event.preventDefault();const button=form.querySelector('[type=submit]');if(button)button.disabled=true;
    try{await submit(form);}catch(error){flash(error.message,true);}finally{if(button)button.disabled=false;}
  }));
  document.querySelectorAll('[data-download]').forEach(button=>button.addEventListener('click',()=>download(button.dataset.download,button.dataset.filename).catch(e=>flash(e.message,true))));
}
function authPage(name){
  const mode=inviteToken?'invite':recovery?'reset':name;
  const title={signup:'Register as a field officer',forgot:'Reset your password',reset:'Choose a new password',invite:'Accept your invitation'}[mode]||'Beneficiary Deduplication System';
  shell(`<div class="auth-wrap"><section class="auth-card"><div class="auth-head"><div class="brand-mark big">RP</div><h1>${title}</h1><p class="muted small">RescuePlan Kenya · aid distribution integrity</p></div><form class="stack" data-action="${esc(mode)}">${mode==='signup'?field('fullName','Full name','','text','required maxlength="120" autocomplete="name"'):''}${!['reset','invite'].includes(mode)?field('email','Email address','','email','required autocomplete="email"'):''}${mode!=='forgot'?field('password','Password','','password',`required minlength="8" autocomplete="${mode==='login'?'current-password':'new-password'}"`):''}<button class="btn btn-primary full" type="submit">${{signup:'Create account',forgot:'Send reset link',reset:'Update password',invite:'Accept invitation'}[mode]||'Log in'}</button></form><p class="small muted">${mode==='login'?'<a href="#signup">Register as a field officer</a> · <a href="#forgot">Forgot password?</a>':'<a href="#login">Back to login</a>'}</p><p class="hint small">Staff accounts use email verification. Administrator access is assigned by your organisation.</p></section></div>`,name);
}
async function dashboard(){
  const d=await api('dashboard'),total=Object.values(d.counts).reduce((a,b)=>a+b,0);
  return `<div class="page-head"><div><h1>Distribution integrity dashboard</h1><p class="muted">Signed in as ${esc(me.name)} (${esc(label(me.role))}).</p></div><div class="page-actions"><a class="btn" href="#new">+ Register beneficiary</a><form data-action="scan"><button class="btn btn-primary" type="submit">Run Deduplication Scan</button></form></div></div><div class="cards">${[[total,'Total records',''],[d.counts.verified||0,'Verified unique','ok'],[d.counts.duplicate_flagged||0,'Duplicate flagged','danger'],[d.counts.pending_review||0,'Pending review','warn'],[d.counts.merged_inactive||0,'Merged / inactive',''],[d.pending,'Clusters to review','warn'],[d.households,'Household members covered','ok']].map(([n,t,c])=>`<div class="card ${c}"><span class="card-num">${n}</span><span class="card-label">${t}</span></div>`).join('')}</div><div class="grid-2"><section class="panel"><h2>Coverage by camp</h2>${table(['Camp / location','Records','Verified'],d.camps.map(c=>[esc(c.camp),c.count,c.verified]))}</section><section class="panel"><h2>Recent activity</h2>${d.recent.length?`<ul class="timeline">${d.recent.map(r=>`<li><strong>${esc(label(r.action))}</strong><span class="muted small">${esc(date(r.createdAt))}</span><div>${esc(r.details)}</div></li>`).join('')}</ul>`:empty('Activity appears here as your team registers beneficiaries.')}</section></div>`;
}
function filterForm(params,camps,action='filter',dates=false){return `<form class="filters" data-action="${action}">${!dates?field('q','Name or national ID',params.get('q')||'','','placeholder="Search records"'):''}<div><label for="camp">Camp / location</label><select id="camp" name="camp"><option value="">All camps</option>${options(camps,params.get('camp'))}</select></div>${dates?field('dateFrom','From date','','date')+field('dateTo','To date','','date'):`<div><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>${options(['pending_review','verified','duplicate_flagged','merged_inactive'],params.get('status'))}</select></div>`}<button class="btn btn-primary" type="submit">${dates?'Generate report':'Search'}</button></form>`;}
async function beneficiariesPage(params){
  const data=await api('beneficiaries?'+params);
  const duplicatesOnly=params.get('status')==='duplicate_flagged';
  const toggleParams=new URLSearchParams(params);
  toggleParams.delete('page');
  if(duplicatesOnly)toggleParams.delete('status');else toggleParams.set('status','duplicate_flagged');
  return `<div class="page-head"><div><h1>${duplicatesOnly?'Duplicate records':'Beneficiary register'}</h1><p class="muted">${data.total} matching records · 50 per page</p></div><div class="page-actions"><a class="btn ${duplicatesOnly?'btn-primary':''}" href="#beneficiaries?${esc(toggleParams)}">${duplicatesOnly?'Show all statuses':'Show duplicate records'}</a>${duplicatesOnly?'<a class="btn" href="#duplicates">Review duplicate groups</a>':''}<button class="btn" data-download="beneficiaries/export?${esc(params)}" data-filename="beneficiaries.csv">Export CSV</button><a class="btn btn-primary" href="#new">Register beneficiary</a></div></div><section class="panel">${filterForm(params,data.camps)}${duplicatesOnly?'<p class="hint">These records were flagged as possible duplicates by a deduplication scan. Any staff member can run a scan from the dashboard and review matching groups from the Duplicates page.</p>':''}${duplicatesOnly&&!data.records.length?empty('No flagged duplicate records match these filters.'):table(['ID','Full name','National ID','Household','Camp / location','Status',''],data.records.map(r=>[r.id,esc(r.fullName),esc(r.nationalId),r.householdSize,esc(r.campLocation),badge(r.status),`<a href="#new?id=${r.id}">${r.status==='merged_inactive'?'View':'Edit'}</a>`]))}<div class="page-actions">${data.page>1?`<a class="btn" href="#beneficiaries?${esc(new URLSearchParams({...Object.fromEntries(params),page:data.page-1}))}">Previous</a>`:''}${data.page*50<data.total?`<a class="btn" href="#beneficiaries?${esc(new URLSearchParams({...Object.fromEntries(params),page:data.page+1}))}">Next</a>`:''}</div></section>`;
}
async function recordPage(params){
  const id=params.get('id'),r=id?await api('beneficiaries/'+encodeURIComponent(id)):{},inactive=r.status==='merged_inactive';
  return `<div class="page-head"><div><h1>${id?'Edit record #'+esc(id):'Register a beneficiary'}</h1><p class="muted">Search existing records before registering a new beneficiary.</p></div><a class="btn" href="#beneficiaries">Back to register</a></div><section class="panel narrow"><form class="stack" data-action="record" data-id="${esc(id||'')}"><fieldset ${inactive?'disabled':''}>${field('fullName','Full name',r.fullName,'text','required maxlength="120" autocomplete="name"')}${field('nationalId','National ID',r.nationalId,'text','required pattern="[0-9]{7,9}" minlength="7" maxlength="9" inputmode="numeric" aria-describedby="id-hint"')}<p id="id-hint" class="hint small">Enter 7–9 digits. Leading zeroes are preserved.</p>${field('householdSize','Household size',r.householdSize,'number','required min="1" max="10000" step="1"')}${field('campLocation','Camp / location',r.campLocation,'text','required maxlength="120"')}${field('phone','Phone (optional)',r.phone,'tel','maxlength="30"')}<div><label for="notes">Notes (optional)</label><textarea id="notes" name="notes" maxlength="10000" rows="4">${esc(r.notes)}</textarea></div>${id?`<label class="checkbox"><input type="checkbox" name="markVerified"> Mark as verified unique</label>`:''}<button class="btn btn-primary" type="submit">${id?'Save changes':'Register beneficiary'}</button></fieldset>${inactive?'<p class="hint">This record was merged and is retained for the audit trail.</p>':''}</form></section>`;
}
async function duplicatesPage(){
  const groups=await api('duplicates');
  return `<div class="page-head"><div><h1>Duplicate review</h1><p class="muted">Phonetic blocking and name / ID similarity · threshold 88% · latest 100 clusters</p></div><form data-action="scan"><button class="btn btn-primary" type="submit">Run deduplication scan</button></form></div>${groups.length?groups.map(g=>`<section class="panel"><div class="page-head"><h2>Cluster #${g.id} · ${(g.score*100).toFixed(1)}% similarity</h2>${badge(g.status)}</div>${table(['Record','Name','National ID','Household','Camp / location','Status'],g.members.map(r=>[`<a href="#new?id=${r.id}">#${r.id}</a>`,esc(r.fullName),esc(r.nationalId),r.householdSize,esc(r.campLocation),badge(r.status)]))}${g.status==='pending'?`<form class="stack" data-action="resolve" data-id="${g.id}"><div class="form-grid"><div><label for="decision-${g.id}">Review decision</label><select id="decision-${g.id}" name="decision"><option value="confirmed_duplicate">Confirmed duplicate</option><option value="false_positive">Different people (false positive)</option></select></div><div><label for="keep-${g.id}">Record to keep if duplicate</label><select id="keep-${g.id}" name="keepId"><option value="">Select a record</option>${g.members.filter(r=>r.status!=='merged_inactive').map(r=>`<option value="${r.id}">#${r.id} · ${esc(r.fullName)}</option>`).join('')}</select></div></div><p class="hint small">Confirming a duplicate keeps the selected record and deactivates the other records. All records remain in the audit trail.</p><div><button class="btn btn-primary" type="submit">Save review decision</button></div></form>`:`<p class="muted small">Reviewed ${esc(date(g.reviewedAt))}${g.keepId?' · kept record #'+g.keepId:''}</p>`}</section>`).join(''):empty('No duplicate clusters yet. Run a scan after registering or importing beneficiaries.')}`;
}
function importPage(){return `<div class="page-head"><div><h1>Batch import</h1><p class="muted">Upload a CSV or TSV file. All rows are validated before any are saved.</p></div></div><section class="panel narrow"><form class="stack" data-action="import"><div><label for="file">CSV or TSV file</label><input type="file" id="file" name="file" accept=".csv,.tsv,.txt" required></div><p class="hint">Required columns: <code>full_name</code>, <code>national_id</code>, <code>household_size</code>, <code>camp_location</code>. Optional: <code>phone</code>, <code>notes</code>. Existing national IDs are skipped. Maximum 2,000 rows / 1.5 MB.</p><button class="btn btn-primary" type="submit">Validate and import</button></form><p><button id="template" class="btn">Download CSV template</button></p></section>`;}
const reportNames={deduplication_summary:'Deduplication summary',distribution_eligibility:'Distribution eligibility',anomaly:'Anomaly report'};
async function reportsPage(){
  const [history,data]=await Promise.all([api('reports'),api('beneficiaries')]);
  return `<div class="page-head"><div><h1>Reports</h1><p class="muted">Generate a snapshot and download it again at any time.</p></div></div><section class="panel"><form class="stack" data-action="report"><div><label for="type">Report type</label><select name="type" id="type">${Object.entries(reportNames).map(([v,n])=>`<option value="${v}">${n}</option>`).join('')}</select></div><div class="filters"><div><label for="camp">Camp / location</label><select id="camp" name="camp"><option value="">All camps</option>${options(data.camps)}</select></div>${field('dateFrom','Registered from','','date')}${field('dateTo','Registered to','','date')}<button class="btn btn-primary" type="submit">Generate report</button></div></form><div id="report-result"></div></section><section class="panel"><h2>Recent reports</h2>${table(['Report','Camp','Generated',''],history.map(r=>[esc(reportNames[r.type]||r.type),esc(r.camp||'All camps'),esc(date(r.createdAt)),`<button class="btn btn-sm" data-download="reports/${r.id}/download" data-filename="report-${r.id}.csv">Download CSV</button>`]))}</section>`;
}
async function usersPage(){
  const accounts=await api('users');
  return `<div class="page-head"><div><h1>Staff access</h1><p class="muted">Staff register with a verified email, then appear here after their first login.</p></div></div><section class="panel"><p>New staff can use <a href="#signup">the registration page</a>. New accounts receive the field officer role. Any active staff member can use the controls below to assign roles or deactivate an account.</p>${table(['Name','Email','Role','State','Access controls'],accounts.map(u=>[esc(u.name),esc(u.email),badge(u.role),u.active?'Active':'Deactivated',u.id===me.id?'Your account':`<form class="inline-form" data-action="user" data-id="${esc(u.id)}"><label class="sr-only" for="role-${esc(u.id)}">Role for ${esc(u.name)}</label><select id="role-${esc(u.id)}" name="role">${options(['field_officer','administrator'],u.role)}</select><label><input type="checkbox" name="active" ${u.active?'checked':''}> Active</label><button class="btn btn-sm" type="submit">Save</button></form>`]))}</section>`;
}
async function auditPage(params){const page=Math.max(1,Number(params.get('page'))||1),rows=await api('audit?page='+page);return `<div class="page-head"><div><h1>Audit log</h1><p class="muted">A permanent trail of registry changes · 100 entries per page</p></div><button class="btn" data-download="audit/export" data-filename="audit-log.csv">Export audit CSV</button></div><section class="panel">${table(['Time','User','Action','Details'],rows.map(r=>[esc(date(r.createdAt)),esc(r.name||'—'),esc(label(r.action)),esc(r.details)]))}<div class="page-actions">${page>1?`<a class="btn" href="#audit?page=${page-1}">Previous</a>`:''}${rows.length===100?`<a class="btn" href="#audit?page=${page+1}">Next</a>`:''}</div></section>`;}
async function render(){
  const version=++renderVersion,{name,params}=route();
  if(name==='logout'){try{await logout();}finally{me=null;location.hash='login';}return;}
  if(['login','signup','forgot'].includes(name)||recovery||inviteToken){authPage(name);return;}
  if(!me){shell('<p role="status">Checking your session…</p>',name);try{me=await api('me');}catch(e){if(version!==renderVersion)return;if(!me){location.hash='login';return;}shell(empty(e.message),name);return;}}
  if(version!==renderVersion)return;
  if(navItems.find(n=>n[0]===name)?.[2] && me.role!=='administrator'){shell('<h1>Administrator access required</h1><p>Contact your organisation’s administrator to access this page.</p>',name);return;}
  shell('<div class="panel loading" role="status">Loading your workspace…</div>',name);
  try{
    const content=await ({dashboard,beneficiaries:()=>beneficiariesPage(params),new:()=>recordPage(params),duplicates:duplicatesPage,import:importPage,reports:reportsPage,users:usersPage,audit:()=>auditPage(params)}[name]||(()=>'<h1>Page not found</h1><a href="#dashboard">Return to dashboard</a>'))();
    if(version!==renderVersion)return;shell(content,name);
    document.querySelector('#template')?.addEventListener('click',()=>{const url=URL.createObjectURL(new Blob(['full_name,national_id,household_size,camp_location,phone,notes\r\n'],{type:'text/csv'}));const a=document.createElement('a');a.href=url;a.download='beneficiary-template.csv';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);});
  }catch(e){if(version===renderVersion){shell('<section class="panel"><h1>Unable to load this page</h1><p>Please try again.</p><button class="btn" id="retry">Retry</button></section>',name);flash(e.message,true);document.querySelector('#retry').onclick=render;}}
}
async function submit(form){
  const data=formData(form),action=form.dataset.action,id=form.dataset.id;
  if(action==='login'){await login(data.email,data.password);me=await api('me');location.hash='dashboard';return;}
  if(action==='signup'){await signup(data.email,data.password,{full_name:data.fullName});flash('Account created. Check your email for a confirmation link before logging in.');form.reset();return;}
  if(action==='forgot'){await requestPasswordRecovery(data.email);flash('Check your inbox for a password reset link.');return;}
  if(action==='reset'||action==='invite'){if(inviteToken)await acceptInvite(inviteToken,data.password);else await updateUser({password:data.password});inviteToken=null;recovery=false;notice='Password saved.';location.hash='dashboard';await render();return;}
  if(action==='filter'){location.hash='beneficiaries?'+new URLSearchParams(data);return;}
  if(action==='record'){await api('beneficiaries'+(id?'/'+encodeURIComponent(id):''),id?'PUT':'POST',{...data,markVerified:form.elements.markVerified?.checked||false});notice=id?'Record updated.':'Beneficiary registered. Run a scan to check for possible duplicates.';location.hash='beneficiaries';return;}
  if(action==='scan'){const r=await api('duplicates/scan','POST',{});notice=`Scan complete: ${r.scanned} records checked, ${r.created} new clusters detected.`;await render();return;}
  if(action==='resolve'){if(data.decision==='confirmed_duplicate'&&!data.keepId)throw new Error('Select a record to keep.');await api(`duplicates/${id}/resolve`,'POST',data);notice='Review decision saved.';await render();return;}
  if(action==='import'){const file=form.elements.file.files[0];if(!file||file.size>1500000)throw new Error('Choose a file under 1.5 MB.');const r=await api('import','POST',{csv:await file.text()});notice=`Imported ${r.inserted} records; skipped ${r.skipped} existing national IDs.`;location.hash='beneficiaries';return;}
  if(action==='user'){await api('users/'+encodeURIComponent(id),'PUT',{role:data.role,active:form.elements.active.checked});notice='Staff access updated.';await render();return;}
  if(action==='report'){const r=await api('reports','POST',data);await render();document.querySelector('#report-result').innerHTML=`<h2>Report #${r.id}</h2>${table(r.payload.columns,r.payload.rows.map(row=>row.map(esc)))}<button id="generated-download" class="btn btn-primary">Download CSV</button>`;document.querySelector('#generated-download').onclick=()=>download(`reports/${r.id}/download`,`report-${r.id}.csv`).catch(e=>flash(e.message,true));flash('Report generated and saved.');}
}
window.addEventListener('hashchange',render);
window.addEventListener('unhandledrejection',event=>{event.preventDefault();flash('Something went wrong. Please reload the page and try again.',true);});
async function startApp() {
  try {
    const result = await handleAuthCallback();
    if (result?.type === 'recovery') recovery = true;
    if (result?.type === 'invite') inviteToken = result.token;
    if (result && !recovery && !inviteToken) {
      notice = 'Email verified. You can continue to your workspace.';
      history.replaceState(null, '', '/#dashboard');
    }
  } catch (error) {
    notice = error.message;
  }
  await render();
}
void startApp();
