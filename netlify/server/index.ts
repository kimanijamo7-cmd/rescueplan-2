import express, { type Request, type Response, type NextFunction } from 'express';
import { getContext } from '@netlify/server';
import { eq, ne, and, or, ilike, desc, asc, inArray, sql, gte, lte } from 'drizzle-orm';
import { parse } from 'csv-parse/sync';
import { db } from '../../db/index.ts';
import { users, beneficiaries, clusters, members, auditLog, reports } from '../../db/schema.ts';
// @ts-expect-error Plain JavaScript module is also exercised by node:test.
import { pipeline, validateRecord, csv } from './dedup.mjs';

const app = express();
app.disable('x-powered-by');
app.use(express.json({limit:'2mb'}));
app.use('/api', (req,res,next) => {
  res.set('Cache-Control','no-store');
  if(!['GET','HEAD'].includes(req.method)) {
    const origin=req.get('origin');
    let allowed = false;
    try { allowed = !!origin && new URL(origin).host === req.get('host'); } catch {}
    if(!allowed) return res.status(403).json({error:'Request origin is not allowed.'});
    if(!req.is('application/json')) return res.status(415).json({error:'Use a JSON request.'});
  }
  next();
});
app.get('/api/health', (_req,res)=>res.json({status:'ok'}));

// Identity validates every session remotely; an unsigned cookie or user metadata never grants access.
app.use('/api', async (req:Request,res:Response,next:NextFunction)=>{
  try {
    const token=req.headers.cookie?.split(';').map(s=>s.trim()).find(s=>s.startsWith('nf_jwt='))?.slice(7);
    if(!token) return res.status(401).json({error:'Please log in to continue.'});
    // Use platform site metadata so renamed sites and previews verify against
    // this project's Identity instance, rather than an obsolete deployment URL.
    let siteUrl='https://rescueplan.netlify.app';
    try { siteUrl=getContext().site.url || siteUrl; } catch { /* Direct local/test requests have no platform context. */ }
    const identityUrl=new URL('/.netlify/identity/user',siteUrl);
    identityUrl.protocol='https:';
    const response=await fetch(identityUrl,{headers:{Authorization:`Bearer ${decodeURIComponent(token)}`},signal:AbortSignal.timeout(8000)});
    if(!response.ok) return res.status(response.status===401||response.status===403?401:503).json({error:response.status===401||response.status===403?'Your session has expired. Please log in again.':'Login verification is temporarily unavailable.'});
    const identity=await response.json();
    if(!identity.id || !identity.confirmed_at) return res.status(401).json({error:'Confirm your email before logging in.'});
    const roles=identity.app_metadata?.roles || [];
    const identityAdmin=Array.isArray(roles) && (roles.includes('admin') || roles.includes('administrator'));
    await db.insert(users).values({id:identity.id,email:identity.email||'',name:String(identity.user_metadata?.full_name||identity.email||'Staff member').slice(0,120)}).onConflictDoNothing();
    const [account]=await db.select().from(users).where(eq(users.id,identity.id));
    if(!account.active) return res.status(403).json({error:'Your account is deactivated. Contact your administrator.'});
    res.locals.user={...account,role:identityAdmin?'administrator':account.role};
    next();
  } catch {res.status(503).json({error:'The service is temporarily unavailable. Please try again shortly.'});}
});
const admin=(req:Request,res:Response,next:NextFunction)=>res.locals.user.role==='administrator'?next():res.status(403).json({error:'Administrator access is required.'});
const audit=(tx:any,uid:string,action:string,details:string)=>tx.insert(auditLog).values({userId:uid,action,details});
const positiveId=(value:unknown)=>{const id=Number(value);if(!Number.isSafeInteger(id)||id<1) throw Object.assign(new Error('Invalid record ID.'),{status:400});return id;};
app.get('/api/me',(_req,res)=>res.json(res.locals.user));
app.get('/api/dashboard',async(_req,res)=>{
  const rows=await db.select({status:beneficiaries.status,count:sql<number>`count(*)::int`}).from(beneficiaries).groupBy(beneficiaries.status);
  const camps=await db.select({camp:beneficiaries.campLocation,count:sql<number>`count(*)::int`,verified:sql<number>`count(*) filter (where ${beneficiaries.status} = 'verified')::int`}).from(beneficiaries).groupBy(beneficiaries.campLocation).orderBy(asc(beneficiaries.campLocation));
  const [stats]=await db.select({households:sql<number>`coalesce(sum(${beneficiaries.householdSize}),0)::int`}).from(beneficiaries).where(eq(beneficiaries.status,'verified'));
  const [pending]=await db.select({count:sql<number>`count(*)::int`}).from(clusters).where(eq(clusters.status,'pending'));
  const recent=await db.select().from(auditLog).orderBy(desc(auditLog.id)).limit(8);
  res.json({counts:Object.fromEntries(rows.map(r=>[r.status,r.count])),camps,households:stats.households,pending:pending.count,recent});
});
function filters(query:any) {
  const conditions=[];
  const q=String(query.q||'').trim().slice(0,120),camp=String(query.camp||'').slice(0,120),status=String(query.status||'');
  if(q) conditions.push(or(ilike(beneficiaries.fullName,`%${q}%`),ilike(beneficiaries.nationalId,`%${q}%`))!);
  if(camp) conditions.push(eq(beneficiaries.campLocation,camp));
  if(['verified','pending_review','duplicate_flagged','merged_inactive'].includes(status)) conditions.push(eq(beneficiaries.status,status));
  for(const [key,fn] of [['dateFrom',gte],['dateTo',lte]] as const) if(query[key]) {
    const date=String(query[key]);if(!/^\d{4}-\d{2}-\d{2}$/.test(date)||Number.isNaN(Date.parse(date))) throw Object.assign(new Error('Use a valid date.'),{status:400});
    conditions.push(fn(beneficiaries.createdAt,new Date(date+(key==='dateTo'?'T23:59:59.999Z':'T00:00:00Z'))));
  }
  if(query.dateFrom && query.dateTo && query.dateFrom>query.dateTo) throw Object.assign(new Error('The start date must be before the end date.'),{status:400});
  return and(...conditions);
}
app.get('/api/beneficiaries',async(req,res)=>{
  const page=Math.max(1,Math.min(100000,Number(req.query.page)||1));
  const where=filters(req.query);
  const records=await db.select().from(beneficiaries).where(where).orderBy(desc(beneficiaries.id)).limit(50).offset((page-1)*50);
  const [total]=await db.select({count:sql<number>`count(*)::int`}).from(beneficiaries).where(where);
  const camps=await db.selectDistinct({name:beneficiaries.campLocation}).from(beneficiaries).orderBy(asc(beneficiaries.campLocation));
  res.json({records,total:total.count,page,camps:camps.map(c=>c.name)});
});
app.get('/api/beneficiaries/export',async(req,res)=>{
  const records=await db.select().from(beneficiaries).where(filters(req.query)).orderBy(asc(beneficiaries.id));
  await audit(db,res.locals.user.id,'BENEFICIARIES_EXPORTED',`Exported ${records.length} records`);
  res.type('text/csv').attachment('beneficiaries.csv').send(csv(['ID','Full name','National ID','Household size','Camp / location','Phone','Status','Notes'],records.map(r=>[r.id,r.fullName,r.nationalId,r.householdSize,r.campLocation,r.phone,r.status,r.notes])));
});
app.get('/api/beneficiaries/:id',async(req,res)=>{
  const [record]=await db.select().from(beneficiaries).where(eq(beneficiaries.id,positiveId(req.params.id)));
  if(!record) return res.status(404).json({error:'Beneficiary record not found.'});res.json(record);
});
app.post('/api/beneficiaries',async(req,res)=>{
  let value;try {value=validateRecord(req.body);}catch(e:any){return res.status(400).json({error:e.message});}
  const result=await db.transaction(async tx=>{
    const [record]=await tx.insert(beneficiaries).values({...value,registeredBy:res.locals.user.id}).returning();
    await audit(tx,res.locals.user.id,'BENEFICIARY_CREATED',`Registered beneficiary #${record.id}`);return record;
  });res.status(201).json(result);
});
app.put('/api/beneficiaries/:id',async(req,res)=>{
  const id=positiveId(req.params.id);let value;try {value=validateRecord(req.body);}catch(e:any){return res.status(400).json({error:e.message});}
  const result=await db.transaction(async tx=>{
    const [stored]=await tx.select().from(beneficiaries).where(eq(beneficiaries.id,id)).for('update');
    if(!stored) throw Object.assign(new Error('Beneficiary record not found.'),{status:404});
    if(stored.status==='merged_inactive') throw Object.assign(new Error('Merged records cannot be edited.'),{status:409});
    const [pending]=await tx.select({id:members.id}).from(members).innerJoin(clusters,eq(members.clusterId,clusters.id)).where(and(eq(members.beneficiaryId,id),eq(clusters.status,'pending'))).limit(1);
    if(req.body.markVerified && pending) throw Object.assign(new Error('Resolve the pending duplicate review before verifying this record.'),{status:409});
    const [record]=await tx.update(beneficiaries).set({...value,status:req.body.markVerified?'verified':stored.status,updatedAt:new Date()}).where(eq(beneficiaries.id,id)).returning();
    await audit(tx,res.locals.user.id,'BENEFICIARY_UPDATED',`Updated beneficiary #${id}`);return record;
  });res.json(result);
});
app.post('/api/import',admin,async(req,res)=>{
  const raw=req.body.csv;if(typeof raw!=='string' || raw.length>1500000) return res.status(400).json({error:'Choose a CSV or TSV file under 1.5 MB.'});
  let records:any[];
  try {
    const aliases:Record<string,string>={name:'full_name',nationalid:'national_id',id_number:'national_id',household:'household_size',camp:'camp_location',location:'camp_location'};
    const parsed=parse(raw,{bom:true,columns:(headers:string[])=>headers.map(h=>{const key=h.trim().toLowerCase().replace(/[ -]/g,'_');return aliases[key]||aliases[key.replaceAll('_','')]||key;}),skip_empty_lines:true,trim:true,delimiter:raw.split('\n')[0].includes('\t')?'\t':','});
    if(!parsed.length || parsed.length>2000) throw new Error('Import between 1 and 2,000 rows at a time.');
    const seen=new Set();
    records=parsed.map((r:any,index:number)=>{try {const value=validateRecord({fullName:r.full_name,nationalId:r.national_id,householdSize:r.household_size,campLocation:r.camp_location,phone:r.phone,notes:r.notes});if(seen.has(value.nationalId)) throw new Error('National ID repeats in this file.');seen.add(value.nationalId);return value;}catch(e:any){throw new Error(`Row ${index+2}: ${e.message}`);}});
  } catch(e:any) {return res.status(400).json({error:e.message || 'The file is not valid CSV.'});}
  const inserted=await db.transaction(async tx=>{
    const rows=await tx.insert(beneficiaries).values(records.map(r=>({...r,registeredBy:res.locals.user.id}))).onConflictDoNothing({target:beneficiaries.nationalId}).returning({id:beneficiaries.id});
    await audit(tx,res.locals.user.id,'BATCH_IMPORT',`Imported ${rows.length} records; skipped ${records.length-rows.length} existing national IDs`);return rows.length;
  });res.json({inserted,skipped:records.length-inserted});
});
app.get('/api/duplicates',async(_req,res)=>{
  const groups=await db.select().from(clusters).orderBy(desc(clusters.id)).limit(100);
  const links=groups.length?await db.select({clusterId:members.clusterId,record:beneficiaries}).from(members).innerJoin(beneficiaries,eq(members.beneficiaryId,beneficiaries.id)).where(inArray(members.clusterId,groups.map(g=>g.id))):[];
  res.json(groups.map(g=>({...g,members:links.filter(m=>m.clusterId===g.id).map(m=>m.record)})));
});
app.post('/api/duplicates/scan',async(_req,res)=>{
  const result=await db.transaction(async tx=>{
    // Serialize scans and resolutions so overlapping clusters cannot corrupt statuses.
    await tx.execute(sql`select pg_advisory_xact_lock(482731)`);
    const records=await tx.select().from(beneficiaries).where(ne(beneficiaries.status,'merged_inactive')).limit(5001);
    if(records.length>5000) throw Object.assign(new Error('This interactive scan supports up to 5,000 active records.'),{status:422});
    const candidates=pipeline(records);let created=0;
    for(const candidate of candidates) {
      const [group]=await tx.insert(clusters).values({signature:candidate.ids.join('-'),score:candidate.score}).onConflictDoNothing().returning();
      if(!group) continue;
      await tx.insert(members).values(candidate.ids.map((id:number)=>({clusterId:group.id,beneficiaryId:id})));
      await tx.update(beneficiaries).set({status:'duplicate_flagged',updatedAt:new Date()}).where(inArray(beneficiaries.id,candidate.ids));created++;
    }
    await audit(tx,res.locals.user.id,'DEDUP_SCAN_RUN',`${records.length} records scanned; ${created} new clusters detected at threshold 0.88`);return {scanned:records.length,created};
  });res.json(result);
});
app.post('/api/duplicates/:id/resolve',async(req,res)=>{
  const id=positiveId(req.params.id),decision=req.body.decision;
  if(!['confirmed_duplicate','false_positive'].includes(decision)) return res.status(400).json({error:'Choose a valid review decision.'});
  await db.transaction(async tx=>{
    await tx.execute(sql`select pg_advisory_xact_lock(482731)`);
    const [group]=await tx.select().from(clusters).where(eq(clusters.id,id)).for('update');
    if(!group || group.status!=='pending') throw Object.assign(new Error('This cluster is missing or already resolved.'),{status:409});
    const links=await tx.select().from(members).where(eq(members.clusterId,id));const ids=links.map(l=>l.beneficiaryId);
    const keep=Number(req.body.keepId);
    if(decision==='confirmed_duplicate' && !ids.includes(keep)) throw Object.assign(new Error('Select the record to keep.'),{status:400});
    if(decision==='confirmed_duplicate') {
      const [kept]=await tx.select().from(beneficiaries).where(eq(beneficiaries.id,keep));
      if(kept.status==='merged_inactive') throw Object.assign(new Error('The selected record was already merged. Keep an active record.'),{status:409});
    }
    await tx.update(clusters).set({status:decision,keepId:decision==='confirmed_duplicate'?keep:null,reviewedBy:res.locals.user.id,reviewedAt:new Date()}).where(eq(clusters.id,id));
    for(const memberId of ids) {
      const [record]=await tx.select().from(beneficiaries).where(eq(beneficiaries.id,memberId));
      if(record.status==='merged_inactive') continue;
      const [other]=await tx.select({id:members.id}).from(members).innerJoin(clusters,eq(members.clusterId,clusters.id)).where(and(eq(members.beneficiaryId,memberId),eq(clusters.status,'pending'))).limit(1);
      const status=decision==='confirmed_duplicate' && memberId!==keep?'merged_inactive':other?'duplicate_flagged':'verified';
      await tx.update(beneficiaries).set({status,updatedAt:new Date()}).where(eq(beneficiaries.id,memberId));
    }
    await audit(tx,res.locals.user.id,decision==='confirmed_duplicate'?'CLUSTER_CONFIRMED':'CLUSTER_FALSE_POSITIVE',`Reviewed cluster #${id}${decision==='confirmed_duplicate'?`; kept #${keep}`:''}`);
  });res.json({ok:true});
});
app.get('/api/reports',async(_req,res)=>res.json(await db.select({id:reports.id,type:reports.type,camp:reports.camp,dateFrom:reports.dateFrom,dateTo:reports.dateTo,createdAt:reports.createdAt}).from(reports).orderBy(desc(reports.id)).limit(30)));
app.post('/api/reports',async(req,res)=>{
  const type=String(req.body.type),where=filters(req.body);
  if(!['deduplication_summary','distribution_eligibility','anomaly'].includes(type)) return res.status(400).json({error:'Choose a valid report type.'});
  let columns:string[],rows:(string|number)[][];
  if(type==='distribution_eligibility') {
    columns=['Camp / location','Verified beneficiaries','Household members'];
    const data=await db.select({camp:beneficiaries.campLocation,count:sql<number>`count(*)::int`,total:sql<number>`sum(${beneficiaries.householdSize})::int`}).from(beneficiaries).where(and(where,eq(beneficiaries.status,'verified'))).groupBy(beneficiaries.campLocation).orderBy(asc(beneficiaries.campLocation));
    rows=data.map(r=>[r.camp,r.count,r.total]);
  } else if(type==='anomaly') {
    columns=['ID','Full name','National ID','Camp / location','Status','Cluster','Similarity'];
    const data=await db.select({record:beneficiaries,clusterId:clusters.id,score:clusters.score}).from(beneficiaries).innerJoin(members,eq(members.beneficiaryId,beneficiaries.id)).innerJoin(clusters,eq(clusters.id,members.clusterId)).where(and(where,eq(clusters.status,'pending')));
    rows=data.map(r=>[r.record.id,r.record.fullName,r.record.nationalId,r.record.campLocation,r.record.status,r.clusterId,r.score]);
  } else {
    columns=['Metric','Value'];
    const data=await db.select({status:beneficiaries.status,count:sql<number>`count(*)::int`}).from(beneficiaries).where(where).groupBy(beneficiaries.status);
    rows=[['Total records',data.reduce((s,r)=>s+r.count,0)],...data.map(r=>[r.status,r.count] as (string|number)[])];
    const groups=await db.selectDistinct({id:clusters.id,status:clusters.status}).from(clusters).innerJoin(members,eq(members.clusterId,clusters.id)).innerJoin(beneficiaries,eq(members.beneficiaryId,beneficiaries.id)).where(where);
    for(const status of ['pending','confirmed_duplicate','false_positive']) rows.push([`Clusters: ${status}`,groups.filter(g=>g.status===status).length]);
  }
  const [report]=await db.transaction(async tx=>{
    const inserted=await tx.insert(reports).values({userId:res.locals.user.id,type,camp:String(req.body.camp||''),dateFrom:String(req.body.dateFrom||''),dateTo:String(req.body.dateTo||''),payload:{columns,rows}}).returning();
    await audit(tx,res.locals.user.id,'REPORT_GENERATED',`Generated ${type} report #${inserted[0].id}`);return inserted;
  });res.status(201).json(report);
});
app.get('/api/reports/:id/download',async(req,res)=>{
  const [report]=await db.select().from(reports).where(eq(reports.id,positiveId(req.params.id)));
  if(!report) return res.status(404).json({error:'Report not found.'});
  res.type('text/csv').attachment(`report-${report.id}.csv`).send(csv(report.payload.columns,report.payload.rows));
});
app.get('/api/users',async(_req,res)=>res.json(await db.select().from(users).orderBy(asc(users.name))));
app.put('/api/users/:id',async(req,res)=>{
  const id=String(req.params.id);if(id===res.locals.user.id) return res.status(400).json({error:'You cannot change your own role or deactivate your own account.'});
  const role=req.body.role,active=req.body.active;
  if(!['administrator','field_officer'].includes(role)||typeof active!=='boolean') return res.status(400).json({error:'Choose a valid role and account state.'});
  await db.transaction(async tx=>{
    const result=await tx.update(users).set({role,active}).where(eq(users.id,id)).returning();
    if(!result.length) throw Object.assign(new Error('User not found.'),{status:404});
    await audit(tx,res.locals.user.id,'USER_UPDATED',`Updated access for ${result[0].email}`);
  });res.json({ok:true});
});
app.get('/api/audit',async(req,res)=>{
  const page=Math.max(1,Math.min(100000,Number(req.query.page)||1));
  res.json(await db.select({id:auditLog.id,action:auditLog.action,details:auditLog.details,createdAt:auditLog.createdAt,name:users.name}).from(auditLog).leftJoin(users,eq(users.id,auditLog.userId)).orderBy(desc(auditLog.id)).limit(100).offset((page-1)*100));
});
app.get('/api/audit/export',async(_req,res)=>{
  const rows=await db.select({id:auditLog.id,action:auditLog.action,details:auditLog.details,createdAt:auditLog.createdAt,name:users.name}).from(auditLog).leftJoin(users,eq(users.id,auditLog.userId)).orderBy(desc(auditLog.id));
  await audit(db,res.locals.user.id,'AUDIT_EXPORTED',`Exported ${rows.length} audit entries`);
  res.type('text/csv').attachment('audit-log.csv').send(csv(['ID','Action','Details','User','Time'],rows.map(r=>[r.id,r.action,r.details,r.name,r.createdAt.toISOString()])));
});
app.use('/api',(_req,res)=>res.status(404).json({error:'Endpoint not found.'}));
const legacy:Record<string,string>={'index.php':'dashboard','login.php':'login','signup.php':'signup','beneficiaries.php':'beneficiaries','beneficiary_form.php':'new','duplicates.php':'duplicates','reports.php':'reports','users.php':'users','audit_log.php':'audit','import.php':'import','logout.php':'logout'};
app.get('/{*path}',(req,res)=>{
  const path=req.path.slice(1);
  if(path in legacy) return res.redirect(302,`/#${legacy[path]}${path==='beneficiary_form.php'&&req.query.id?'?id='+encodeURIComponent(String(req.query.id)):''}`);
  res.status(404).type('html').send('<h1>Page not found</h1><p><a href="/">Return to RescuePlan Kenya</a></p>');
});
app.use((error:any,_req:Request,res:Response,_next:NextFunction)=>{
  if(error.code==='23505' || error.cause?.code==='23505') return res.status(409).json({error:'A beneficiary with that national ID already exists. Search records before registering.'});
  const status=error.status || 500;
  res.status(status).json({error:status<500?error.message:'We could not complete this request. Please try again shortly.'});
});
export default app;
