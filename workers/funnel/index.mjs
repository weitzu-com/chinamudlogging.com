// @ts-nocheck
import products from '../../src/data/products.json' with { type: 'json' };
import categories from '../../src/data/categories.json' with { type: 'json' };
import articles from '../../src/data/articles.json' with { type: 'json' };
import {syncZoho,syncStatus,evidenceFor} from './zoho-sync.mjs';
const paths = new Set(['/', '/about-us/', '/services/', '/product/', '/blog/', '/contact-us/', '/privacy/', ...products.map(p=>`/product/${p.slug}/`), ...categories.map(p=>`/product-category/${p.slug}/`), ...articles.map(p=>`/${p.slug}/`)]);
const origins = new Set(['https://www.chinamudlogging.com', 'https://chinamudlogging.com']);
const uuid = /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/;
const hex = /^[a-f0-9]{64}$/;
const enc = new TextEncoder();
const headers = {'Content-Type':'application/json','Cache-Control':'no-store','X-Content-Type-Options':'nosniff','X-Robots-Tag':'noindex, nofollow'};
class Problem extends Error { constructor(status, message) {super(message); this.status=status;} }
const must = (v,message='Invalid request',status=400)=>{if(!v)throw new Problem(status,message);};
const json = (data,status=200)=>new Response(JSON.stringify(data),{status,headers});
export async function digest(value) {return [...new Uint8Array(await crypto.subtle.digest('SHA-256',enc.encode(value)))].map(b=>b.toString(16).padStart(2,'0')).join('');}
async function sign(value,secret) {const k=await crypto.subtle.importKey('raw',enc.encode(secret),{name:'HMAC',hash:'SHA-256'},false,['sign']);return [...new Uint8Array(await crypto.subtle.sign('HMAC',k,enc.encode(value)))].map(b=>b.toString(16).padStart(2,'0')).join('');}
async function equal(a,b) {const x=await digest(a),y=await digest(b);let diff=0;for(let i=0;i<x.length;i++)diff|=x.charCodeAt(i)^y.charCodeAt(i);return diff===0;}
async function body(req,allowed) {
 must(req.headers.get('content-type')?.split(';')[0]==='application/json','JSON required',415);
 const reader=req.body?.getReader();must(reader,'Body required');let size=0;const chunks=[];
 while(true){const r=await reader.read();if(r.done)break;size+=r.value.length;if(size>4096){await reader.cancel();throw new Problem(413,'Body too large');}chunks.push(r.value);}
 const bytes=new Uint8Array(size);let at=0;for(const c of chunks){bytes.set(c,at);at+=c.length;}
 let b;try{b=JSON.parse(new TextDecoder().decode(bytes));}catch{throw new Problem(400,'Invalid JSON');}
 must(b&&typeof b==='object'&&!Array.isArray(b));must(Object.keys(b).every(k=>allowed.includes(k)),'Unsupported field');return b;
}
const validPath=p=>typeof p==='string'&&paths.has(p);
async function session(env,b) {
 must(uuid.test(b.sessionId||'')&&hex.test(b.token||''),'Invalid session',401);
 const s=await env.DB.prepare('SELECT * FROM sessions WHERE id=?').bind(b.sessionId).first();
 must(s&&s.expires_at>new Date().toISOString(),'Session expired',401);
 must(await equal(b.token,await sign(`${s.id}:${s.expires_at}`,env.ADMIN_KEY)),'Invalid session',401);return s;
}
async function createSession(env,b,isTest) {
 must(validPath(b.path),'Unknown page');const now=new Date();const s={id:crypto.randomUUID(),expiresAt:new Date(now.getTime()+30*60*1000).toISOString()};
 const source=['direct','search','social','other'].includes(b.source)?b.source:'other';
 await env.DB.prepare('INSERT INTO sessions VALUES (?,?,?,?,?,?)').bind(s.id,now.toISOString(),s.expiresAt,b.path,source,isTest?1:0).run();
 return {...s,token:await sign(`${s.id}:${s.expiresAt}`,env.ADMIN_KEY),isTest};
}
function dateRange(url) {
 const today=new Date();const from=url.searchParams.get('from')||new Date(today.getTime()-27*864e5).toISOString().slice(0,10);const to=url.searchParams.get('to')||today.toISOString().slice(0,10);
 must(/^\d{4}-\d{2}-\d{2}$/.test(from)&&/^\d{4}-\d{2}-\d{2}$/.test(to),'Invalid dates');const a=new Date(`${from}T00:00:00.000Z`),z=new Date(`${to}T00:00:00.000Z`);must(Number.isFinite(+a)&&Number.isFinite(+z)&&a<=z&&z-a<90*864e5,'Range must be 1–90 days');must(a.toISOString().startsWith(from)&&z.toISOString().startsWith(to),'Invalid dates');return {from:a.toISOString(),until:new Date(+z+864e5).toISOString(),to};
}
async function report(env,url) {
 const range=dateRange(url),test=url.searchParams.get('test')==='1'?1:0;
 const cohort="SELECT * FROM sessions s WHERE is_test=? AND created_at>=? AND created_at<? AND EXISTS (SELECT 1 FROM events e WHERE e.session_id=s.id AND e.kind='page_view')";const args=[test,range.from,range.until];
 const counts=await env.DB.prepare(`WITH cohort AS (${cohort}) SELECT
 (SELECT COUNT(*) FROM cohort) AS visits,
 (SELECT COUNT(*) FROM events e JOIN cohort s ON s.id=e.session_id WHERE kind='page_view') AS page_views,
 (SELECT COUNT(*) FROM cohort s WHERE EXISTS(SELECT 1 FROM intents i WHERE i.session_id=s.id)) AS intent_sessions,
 (SELECT COUNT(*) FROM intents i JOIN cohort s ON s.id=i.session_id) AS intents,
 (SELECT COUNT(*) FROM receipts r JOIN intents i ON i.id=r.inquiry_id JOIN cohort s ON s.id=i.session_id) AS received_inquiries,
 (SELECT COUNT(*) FROM cohort s WHERE EXISTS(SELECT 1 FROM intents i JOIN receipts r ON r.inquiry_id=i.id WHERE i.session_id=s.id)) AS received_sessions,
 (SELECT COUNT(*) FROM receipts r JOIN intents i ON i.id=r.inquiry_id JOIN cohort s ON s.id=i.session_id WHERE (SELECT decision FROM qualifications q WHERE q.inquiry_id=i.id ORDER BY sequence DESC LIMIT 1)='qualified') AS qualified_inquiries,
 (SELECT COUNT(*) FROM cohort s WHERE EXISTS(SELECT 1 FROM intents i JOIN receipts r ON r.inquiry_id=i.id WHERE i.session_id=s.id AND (SELECT decision FROM qualifications q WHERE q.inquiry_id=i.id ORDER BY sequence DESC LIMIT 1)='qualified')) AS qualified_sessions,
 (SELECT COUNT(*) FROM receipts r JOIN intents i ON i.id=r.inquiry_id JOIN cohort s ON s.id=i.session_id WHERE NOT EXISTS(SELECT 1 FROM qualifications q WHERE q.inquiry_id=i.id)) AS pending_review`).bind(...args).first();
 const sources=await env.DB.prepare(`WITH cohort AS (${cohort}) SELECT source, COUNT(*) AS visits FROM cohort GROUP BY source`).bind(...args).all();
 const rate=(n,d)=>d?Number((n/d).toFixed(4)):null;
 return {version:1,generatedAt:new Date().toISOString(),timezone:'UTC',cohort:{from:range.from,until:range.until,isTest:!!test},...counts,rates:{visit_to_intent:rate(counts.intent_sessions,counts.visits),intent_to_received:rate(counts.received_sessions,counts.intent_sessions),received_to_qualified:rate(counts.qualified_inquiries,counts.received_inquiries)},sources:sources.results,limitations:['Visits are anonymous 30-minute browser sessions, not people.','Email preparation and clicks are intent only.','Receipts require delivered Zoho mailbox evidence (SMTP API or verified manual import); qualification requires human review.','Counts are attributed to the visit cohort; later receipts can change earlier cohorts.','Ad blockers, privacy choices and unreferenced emails create gaps.']};
}
export default {
 async fetch(req,env) {
  try {
   const url=new URL(req.url),p=url.pathname.replace(/^\/api\/funnel/,'');
   if(p==='/health'&&req.method==='GET')return json({ok:true,version:1});
   must(typeof env.ADMIN_KEY==='string'&&env.ADMIN_KEY.length>=32,'Not configured',503);
   if(p.startsWith('/admin/')) {
    const key=req.headers.get('authorization')||'';must(await equal(key,`Bearer ${env.ADMIN_KEY}`),'Unauthorized',401);
    if(p==='/admin/zoho/status'&&req.method==='GET')return json(await syncStatus(env));
    if(p==='/admin/zoho/sync'&&req.method==='POST'){await body(req,[]);const result=await syncZoho(env);return json(result,result.ok?200:503);}
    if(p==='/admin/zoho/evidence'&&req.method==='GET'){const id=url.searchParams.get('inquiry');must(uuid.test(id||''),'Invalid inquiry');return json({records:await evidenceFor(env,id)});}
    if(p==='/admin/report'&&req.method==='GET')return json(await report(env,url));
    if(p==='/admin/test-session'&&req.method==='POST'){const b=await body(req,['path','source']);return json(await createSession(env,b,true),201);}
    if(p==='/admin/receipt'&&req.method==='POST') {
     const b=await body(req,['inquiryId','reference','messageId','deliveredAt','evidenceSha256','source','recipient','status']);
     must(uuid.test(b.inquiryId||'')&&b.reference===`CML-${b.inquiryId}`,'Reference must match inquiry');
     must(b.source==='zoho_delivery_log'&&b.recipient==='sales@chinamudlogging.com'&&b.status==='Delivered','Mailbox delivery evidence required');
     must(typeof b.messageId==='string'&&/^<[^<>\s]{3,250}>$/.test(b.messageId)&&hex.test(b.evidenceSha256||''),'Invalid evidence');
     const delivered=new Date(b.deliveredAt);must(Number.isFinite(+delivered)&&delivered.toISOString()===b.deliveredAt&&+delivered<=Date.now()+300000,'Invalid delivery time');
     const i=await env.DB.prepare('SELECT * FROM intents WHERE id=?').bind(b.inquiryId).first();must(i&&i.kind!=='phone_click','Email intent required',409);must(b.deliveredAt>=i.created_at,'Receipt predates intent',409);
     const hash=await digest(b.messageId);const existing=await env.DB.prepare('SELECT * FROM receipts WHERE inquiry_id=? OR message_hash=?').bind(i.id,hash).first();
     if(existing){must(existing.inquiry_id===i.id&&existing.message_hash===hash,'Message already attributed',409);return json({ok:true,deduplicated:true});}
     await env.DB.prepare('INSERT INTO receipts VALUES (?,?,?,?,?,?)').bind(i.id,hash,b.deliveredAt,new Date().toISOString(),b.evidenceSha256,b.source).run();return json({ok:true},201);
    }
    if(p==='/admin/qualify'&&req.method==='POST') {
     const b=await body(req,['id','inquiryId','decision','reason']);must(uuid.test(b.id||'')&&uuid.test(b.inquiryId||''));
     must(['qualified','rejected'].includes(b.decision)&&['application_fit','commercial_requirement','not_relevant','spam','test_fixture'].includes(b.reason),'Invalid review');
     must(b.reason==='test_fixture'||(b.decision==='qualified'?['application_fit','commercial_requirement']:['not_relevant','spam']).includes(b.reason),'Reason does not match decision');
     const r=await env.DB.prepare('SELECT s.is_test FROM receipts r JOIN intents i ON i.id=r.inquiry_id JOIN sessions s ON s.id=i.session_id WHERE r.inquiry_id=?').bind(b.inquiryId).first();must(r,'Receipt required before qualification',409);must(b.reason!=='test_fixture'||r.is_test===1,'Test reason requires test record');
     const old=await env.DB.prepare('SELECT * FROM qualifications WHERE id=?').bind(b.id).first();if(old){must(old.inquiry_id===b.inquiryId&&old.decision===b.decision&&old.reason===b.reason,'Review id conflict',409);return json({ok:true,deduplicated:true});}
     await env.DB.prepare('INSERT INTO qualifications SELECT ?,?,?,?,?,COALESCE(MAX(sequence),0)+1 FROM qualifications').bind(b.id,b.inquiryId,b.decision,b.reason,new Date().toISOString()).run();return json({ok:true},201);
    }
    throw new Problem(404,'Not found');
   }
   must(req.method==='POST','Method not allowed',405);
   must(origins.has(req.headers.get('origin')),'Origin not allowed',403);
   must(!/bot|crawler|spider|headless|preview|curl|python|wget/i.test(req.headers.get('user-agent')||''),'Automated traffic excluded',403);
   if(env.RATE_LIMITER){const key=await sign(req.headers.get('cf-connecting-ip')||'unknown',env.ADMIN_KEY);const r=await env.RATE_LIMITER.limit({key});must(r.success,'Rate limited',429);}
   if(p==='/session') {const b=await body(req,['path','source']);return json(await createSession(env,b,false),201);}
   if(p==='/event') {
    const b=await body(req,['sessionId','token','id','kind','path']);const s=await session(env,b);
    must(uuid.test(b.id||'')&&['page_view','product_view','contact_view'].includes(b.kind)&&validPath(b.path));
    must(b.kind!=='product_view'||b.path.startsWith('/product/')&&b.path!=='/product/');must(b.kind!=='contact_view'||b.path==='/contact-us/');
    const count=await env.DB.prepare('SELECT COUNT(*) AS n FROM events WHERE session_id=?').bind(s.id).first();must(count.n<120,'Session event limit',429);
    await env.DB.prepare('INSERT OR IGNORE INTO events VALUES (?,?,?,?,?)').bind(b.id,s.id,b.kind,b.path,new Date().toISOString()).run();return json({ok:true});
   }
   if(p==='/intent') {
    const b=await body(req,['sessionId','token','requestId','kind','path']);const s=await session(env,b);
    must(uuid.test(b.requestId||'')&&['email_prepare','email_click','phone_click'].includes(b.kind)&&validPath(b.path));
    const count=await env.DB.prepare('SELECT COUNT(*) AS n FROM intents WHERE session_id=?').bind(s.id).first();must(count.n<20,'Session intent limit',429);
    await env.DB.prepare('INSERT OR IGNORE INTO intents VALUES (?,?,?,?,?,?)').bind(crypto.randomUUID(),s.id,b.requestId,b.kind,b.path,new Date().toISOString()).run();
    const i=await env.DB.prepare('SELECT id FROM intents WHERE session_id=? AND request_id=?').bind(s.id,b.requestId).first();return json({inquiryId:i.id,reference:`CML-${i.id}`},201);
   }
   throw new Problem(404,'Not found');
  }catch(e){return json({error:e instanceof Problem?e.message:'Service unavailable'},e instanceof Problem?e.status:503);}
 },
 async scheduled(event,env) {
  if(event.cron==='15 2 * * *')await env.DB.prepare('DELETE FROM sessions WHERE created_at < ?').bind(new Date(Date.now()-90*864e5).toISOString()).run();
  else await syncZoho(env);
 }
};
