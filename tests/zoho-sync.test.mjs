// @ts-nocheck
import {test} from 'node:test';
import assert from 'node:assert/strict';
import {DatabaseSync} from 'node:sqlite';
import {readFileSync} from 'node:fs';
import {syncZoho,importLog,normalizeLog,evidenceFor,syncStatus,accessToken} from '../workers/funnel/zoho-sync.mjs';
function fixture(){
 const db=new DatabaseSync(':memory:');for(const file of ['schema.sql','zoho-sync.sql'])db.exec(readFileSync(new URL('../workers/funnel/'+file,import.meta.url),'utf8'));
 const env={ADMIN_KEY:'not-a-real-key'.repeat(4),ZOHO_EVIDENCE_KEY:'a'.repeat(64),ZOHO_CLIENT_ID:'client',ZOHO_CLIENT_SECRET:'secret',ZOHO_REFRESH_TOKEN:'refresh',ZOHO_ORG_ID:'1234567',DB:{prepare(sql){let args=[];return{bind(...p){args=p;return this;},async first(){return db.prepare(sql).get(...args)||null;},async all(){return {results:db.prepare(sql).all(...args)};},async run(){return db.prepare(sql).run(...args);}};},async batch(statements){db.exec('BEGIN');try{const result=[];for(const s of statements)result.push(await s.run());db.exec('COMMIT');return result;}catch(e){db.exec('ROLLBACK');throw e;}}}};
 const id=crypto.randomUUID(),sid=crypto.randomUUID(),now=Date.now();
 db.prepare('INSERT INTO sessions VALUES (?,?,?,?,?,?)').run(sid,new Date(now-10000).toISOString(),new Date(now+100000).toISOString(),'/','direct',1);
 db.prepare('INSERT INTO intents VALUES (?,?,?,?,?,?)').run(id,sid,crypto.randomUUID(),'email_prepare','/contact-us/',new Date(now-10000).toISOString());
 const log={mailType:'in',from:{subject:`[CML-${id}] test`,msgId:'<real-mailbox-message@example.invalid>',sender:'must-not-be-retained@example.invalid',clientIP:'192.0.2.1'},TransactionID:'vendor-transaction-123',recipients:[{rcpt:'sales@chinamudlogging.com',deliveryStatus:'delivered',deliveryType:'MAILBOX DELIVERY',timeMillis:now-1000,isSpam:'No'}]};
 return {db,env,id,log,now};
}
test('SMTP delivery evidence alone creates receipt, never qualification, with encrypted proof and replay protection',async()=>{
 const {db,env,id,log}=fixture();const first=await importLog(env,log);assert.equal(first.outcome,'inserted');
 assert.equal((await importLog(env,log)).outcome,'duplicate');assert.equal(db.prepare('SELECT count(*) n FROM receipts').get().n,1);assert.equal(db.prepare('SELECT count(*) n FROM qualifications').get().n,0);
 const raw=db.prepare('SELECT * FROM zoho_delivery_evidence').get();assert(!JSON.stringify(raw).includes('real-mailbox-message'));assert(!JSON.stringify(raw).includes('must-not-be-retained'));
 const proof=await evidenceFor(env,id);assert.equal(proof[0].evidence.messageId,log.from.msgId);assert.equal(proof[0].evidence.reference,'CML-'+id);assert.equal(proof[0].evidence.source,'zoho_smtp_api');assert(!('sender' in proof[0].evidence));
 const second=structuredClone(log);second.from.msgId='<second-message@example.invalid>';assert.equal((await importLog(env,second)).outcome,'duplicate');assert.equal(db.prepare('SELECT count(*) n FROM receipts').get().n,1);assert.equal(db.prepare('SELECT count(*) n FROM zoho_delivery_evidence').get().n,2);
});
test('drafts, outgoing mail, unknown status, pending delivery, absent/ambiguous references and wrong recipients are ignored',async()=>{
 const {env,log}=fixture();
 const mutate=[l=>l.mailType='out',l=>l.recipients[0].deliveryStatus='Accepted',l=>l.recipients[0].deliveryStatus='Mail Moderated',l=>l.recipients[0].deliveryType='SMTP ACCEPTANCE',l=>l.recipients[0].rcpt='any@cnps.com',l=>l.from.subject='Draft prepared',l=>l.from.subject+=' CML-'+crypto.randomUUID()];
 for(const change of mutate){const copy=structuredClone(log);change(copy);assert.equal(normalizeLog(copy),null);assert.equal((await importLog(env,copy)).outcome,'ignored');}
});
test('polling is recipient-scoped, paginated and overlapping; failures retain cursor; lease prevents concurrent runs',async()=>{
 const {db,env,log,now}=fixture();let calls=0;const requests=[];
 const fetcher=async(url,options)=>{
  if(url.includes('/oauth/'))return Response.json({access_token:'short-lived-access'});
  requests.push(JSON.parse(options.body));calls++;
  return Response.json({status:{code:200},data:{response:calls===1?[log]:[],hnxt:calls===1?'true':'false',pagekey:'next-page',pagePrevKey:'previous-page'}});
 };
 let r=await syncZoho(env,{fetcher,now});assert.equal(r.ok,true);assert.equal(r.inserted,1);assert.equal(requests.length,2);assert.equal(requests[0].searchCriteria,'toAddr');assert.equal(requests[0].searchKey,'sales@chinamudlogging.com');assert.equal(requests[1].isNext,true);
 const cursor=db.prepare('SELECT cursor_ms FROM zoho_sync_state').get().cursor_ms;
 r=await syncZoho(env,{now:now+10000,fetcher:async()=>Response.json({error:'invalid_grant',error_description:'SECRET MUST NOT LEAK'})});assert.equal(r.ok,false);assert.equal(db.prepare('SELECT cursor_ms FROM zoho_sync_state').get().cursor_ms,cursor);assert.equal((await syncStatus(env)).last_error,'oauth_rejected');
 db.prepare("UPDATE zoho_sync_state SET lease_until=? WHERE id='smtp'").run(Date.now()+100000);assert.equal((await syncZoho(env)).status,'already_running');
});

test('readonly OAuth token reuse avoids repeated refreshes and never caches a rejection',async()=>{
 const {env}=fixture();let calls=0;const value=crypto.randomUUID();
 const fetcher=async()=>{calls++;return Response.json({access_token:value,expires_in:3600});};
 assert.deepEqual(await Promise.all([accessToken(env,fetcher),accessToken(env,fetcher),accessToken(env,fetcher)]),Array(3).fill(value));
 assert.equal(calls,1);await accessToken({...env,ZOHO_REFRESH_TOKEN:crypto.randomUUID()},fetcher);assert.equal(calls,2);
 let failures=0;const rejected=async()=>{failures++;return Response.json({error:'invalid_grant'});};
 await assert.rejects(accessToken(env,rejected),/oauth_rejected/);await assert.rejects(accessToken(env,rejected),/oauth_rejected/);assert.equal(failures,2);
});
