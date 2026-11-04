// @ts-nocheck
import {test} from 'node:test';
import assert from 'node:assert/strict';
import {DatabaseSync} from 'node:sqlite';
import {readFileSync} from 'node:fs';
import worker from '../workers/funnel/index.mjs';
function fixture(){
 const db=new DatabaseSync(':memory:');db.exec(readFileSync(new URL('../workers/funnel/schema.sql',import.meta.url),'utf8'));
 const env={ADMIN_KEY:'test-only-secret-'.repeat(4),DB:{prepare(sql){let params=[];return {bind(...p){params=p;return this;},async first(){return db.prepare(sql).get(...params)||null;},async all(){return {results:db.prepare(sql).all(...params)};},async run(){return db.prepare(sql).run(...params);}};}}};
 async function call(path,body,admin=false){const method=body===undefined?'GET':'POST';return worker.fetch(new Request('https://example.com/api/funnel'+path,{method,headers:{origin:'https://www.chinamudlogging.com','user-agent':'Mozilla/5.0','content-type':'application/json',...(admin?{authorization:'Bearer '+env.ADMIN_KEY}:{})},...(body===undefined?{}:{body:JSON.stringify(body)})}),env);}
 return {db,env,call};
}
test('intent never becomes received or qualified without separate verified evidence',async()=>{
 const {call,db}=fixture();const s=await(await call('/session',{path:'/',source:'search'})).json();const ticket={sessionId:s.id,token:s.token};
 const event={...ticket,id:crypto.randomUUID(),kind:'page_view',path:'/'};
 await call('/event',event);await call('/event',event);
 const request={...ticket,requestId:crypto.randomUUID(),kind:'email_prepare',path:'/contact-us/'};
 const i=await(await call('/intent',request)).json();assert.deepEqual(await(await call('/intent',request)).json(),i);
 let r=await(await call('/admin/report',undefined,true)).json();assert.equal(r.visits,1);assert.equal(r.page_views,1);assert.equal(r.intents,1);assert.equal(r.received_inquiries,0);assert.equal(r.qualified_inquiries,0);
 const review={id:crypto.randomUUID(),inquiryId:i.inquiryId,decision:'qualified',reason:'application_fit'};
 assert.equal((await call('/admin/qualify',review,true)).status,409);
 const evidence={inquiryId:i.inquiryId,reference:i.reference,messageId:'<real-mail-id@example.com>',deliveredAt:new Date(Date.now()+1).toISOString(),evidenceSha256:'a'.repeat(64),source:'zoho_delivery_log',recipient:'sales@chinamudlogging.com',status:'Delivered'};
 assert.equal((await call('/admin/receipt',evidence)).status,401);
 assert.equal((await call('/admin/receipt',{...evidence,status:'Prepared'},true)).status,400);
 assert.equal((await call('/admin/receipt',evidence,true)).status,201);
 assert.equal((await call('/admin/receipt',evidence,true)).status,200);
 r=await(await call('/admin/report',undefined,true)).json();assert.equal(r.received_inquiries,1);assert.equal(r.qualified_inquiries,0);assert.equal(r.pending_review,1);
 assert.equal((await call('/admin/qualify',review,true)).status,201);await call('/admin/qualify',review,true);
 r=await(await call('/admin/report',undefined,true)).json();assert.equal(r.qualified_inquiries,1);assert.equal(db.prepare('SELECT count(*) n FROM qualifications').get().n,1);
 await call('/admin/qualify',{...review,id:crypto.randomUUID(),decision:'rejected',reason:'not_relevant'},true);
 r=await(await call('/admin/report',undefined,true)).json();assert.equal(r.qualified_inquiries,0);assert.equal(db.prepare('SELECT count(*) n FROM qualifications').get().n,2);
 const second=await(await call('/intent',{...request,requestId:crypto.randomUUID()})).json();assert.equal((await call('/admin/receipt',{...evidence,inquiryId:second.inquiryId,reference:second.reference},true)).status,409);
});
test('test cohorts, authentication, privacy allowlists, expiry and referential integrity',async()=>{
 const {call,db,env}=fixture();assert.equal((await call('/admin/report')).status,401);
 assert.equal((await call('/session',{path:'/',source:'direct',isTest:true})).status,400);
 assert.equal((await call('/session',{path:'/someone@example.com/',source:'direct'})).status,400);
 assert.equal((await call('/session',{path:'/',source:'direct',email:'private@example.com'})).status,400);
 const s=await(await call('/admin/test-session',{path:'/',source:'direct'},true)).json();
 const ticket={sessionId:s.id,token:s.token};await call('/event',{...ticket,id:crypto.randomUUID(),kind:'page_view',path:'/'});
 assert.equal((await call('/event',{...ticket,id:crypto.randomUUID(),kind:'received',path:'/'})).status,400);
 assert.equal((await call('/intent',{...ticket,token:'0'.repeat(64),requestId:crypto.randomUUID(),kind:'email_prepare',path:'/'})).status,401);
 assert.equal((await call('/admin/report?test=1',undefined,true)).status,200);
 const normal=await(await call('/admin/report',undefined,true)).json();const testOnly=await(await call('/admin/report?test=1',undefined,true)).json();assert.equal(normal.visits,0);assert.equal(testOnly.visits,1);assert.equal(normal.rates.visit_to_intent,null);
 const req=new Request('https://example.com/api/funnel/session',{method:'POST',headers:{origin:'https://evil.example','content-type':'application/json'},body:'{}'});assert.equal((await worker.fetch(req,env)).status,403);
 db.prepare('UPDATE sessions SET expires_at=? WHERE id=?').run('2000-01-01T00:00:00.000Z',s.id);
 assert.equal((await call('/intent',{...ticket,requestId:crypto.randomUUID(),kind:'email_prepare',path:'/'})).status,401);
 assert.throws(()=>db.prepare('INSERT INTO receipts VALUES (?,?,?,?,?,?)').run(crypto.randomUUID(),'a','b','c','d','zoho_delivery_log'));
 db.prepare('UPDATE sessions SET created_at=?').run('2000-01-01T00:00:00.000Z');await worker.scheduled({cron:'15 2 * * *'},env);assert.equal(db.prepare('SELECT count(*) n FROM events').get().n,0);
});
