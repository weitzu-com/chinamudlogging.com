// @ts-nocheck
const target='sales@chinamudlogging.com';
const uuid='[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}';
const encoder=new TextEncoder();
const iso=ms=>new Date(ms).toISOString();
const changes=r=>Number(r.meta?.changes??r.changes??0);
export async function sha256(value){return [...new Uint8Array(await crypto.subtle.digest('SHA-256',encoder.encode(value)))].map(n=>n.toString(16).padStart(2,'0')).join('');}
const b64=bytes=>btoa(String.fromCharCode(...bytes));
const unb64=s=>Uint8Array.from(atob(s),c=>c.charCodeAt(0));
async function evidenceKey(env){if(!/^[a-f0-9]{64}$/.test(env.ZOHO_EVIDENCE_KEY||''))throw new Error('evidence_key_missing');return crypto.subtle.importKey('raw',Uint8Array.from(env.ZOHO_EVIDENCE_KEY.match(/../g),x=>parseInt(x,16)),'AES-GCM',false,['encrypt','decrypt']);}
export async function sealEvidence(env,value){const iv=crypto.getRandomValues(new Uint8Array(12));const data=await crypto.subtle.encrypt({name:'AES-GCM',iv,additionalData:encoder.encode('cml-zoho-evidence-v1')},await evidenceKey(env),encoder.encode(JSON.stringify(value)));return b64(iv)+'.'+b64(new Uint8Array(data));}
export async function openEvidence(env,value){const [iv,data]=value.split('.');const bytes=await crypto.subtle.decrypt({name:'AES-GCM',iv:unb64(iv),additionalData:encoder.encode('cml-zoho-evidence-v1')},await evidenceKey(env),unb64(data));return JSON.parse(new TextDecoder().decode(bytes));}
export function configured(env){return ['ZOHO_CLIENT_ID','ZOHO_CLIENT_SECRET','ZOHO_REFRESH_TOKEN','ZOHO_ORG_ID','ZOHO_EVIDENCE_KEY'].every(k=>typeof env[k]==='string'&&env[k].length>0)&&/^\d{5,25}$/.test(env.ZOHO_ORG_ID);}
export function normalizeLog(log){
 // Only an inbound, delivered mailbox event is proof. SMTP acceptance, a draft,
 // an outgoing message, moderation and an unknown status never qualify.
 if(log.mailType!=='in'||!Array.isArray(log.recipients))return null;
 const recipient=log.recipients.find(r=>String(r.rcpt).toLowerCase()===target&&['delivered','mail delivered'].includes(String(r.deliveryStatus).toLowerCase())&&r.deliveryType==='MAILBOX DELIVERY');
 if(!recipient)return null;
 const refs=[...new Set(String(log.from?.subject||'').match(new RegExp('CML-'+uuid,'gi'))||[])];
 if(refs.length!==1)return null;
 const reference=refs[0].toLowerCase().replace('cml-','CML-');const ms=Number(recipient.timeMillis),messageId=log.from?.msgId;
 if(!Number.isSafeInteger(ms)||ms<=0||ms>Date.now()+300000||typeof messageId!=='string'||!/^<[^<>\s]{3,250}>$/.test(messageId))return null;
 return {source:'zoho_smtp_api',inquiryId:reference.slice(4),reference,messageId,deliveredAt:iso(ms),mailType:log.mailType,recipient:target,deliveryStatus:recipient.deliveryStatus,deliveryType:recipient.deliveryType,isSpam:String(recipient.isSpam||'unknown'),transactionId:String(log.TransactionID||'').slice(0,200)};
}
async function jsonResponse(response,label){
 if(!response.ok)throw new Error(label+'_http_'+response.status);
 const text=await response.text();if(text.length>2_000_000)throw new Error(label+'_oversized');let result;try{result=JSON.parse(text);}catch{throw new Error(label+'_invalid_json');}
 if(label==='oauth'&&result.error)throw new Error('oauth_rejected');
 if(label==='logs'&&result.status?.code!==200)throw new Error('logs_api_rejected');return result;
}
export async function accessToken(env,fetcher=fetch){
 const body=new URLSearchParams({grant_type:'refresh_token',client_id:env.ZOHO_CLIENT_ID,client_secret:env.ZOHO_CLIENT_SECRET,refresh_token:env.ZOHO_REFRESH_TOKEN});
 const result=await jsonResponse(await fetcher('https://accounts.zoho.com/oauth/v2/token',{method:'POST',body,signal:AbortSignal.timeout(10000)}),'oauth');
 if(typeof result.access_token!=='string'||!result.access_token)throw new Error('oauth_token_missing');return result.access_token;
}
export async function importLog(env,log){
 const evidence=normalizeLog(log);if(!evidence)return {outcome:'ignored'};
 const intent=await env.DB.prepare('SELECT * FROM intents WHERE id=?').bind(evidence.inquiryId).first();
 if(!intent||intent.kind==='phone_click'||evidence.deliveredAt<intent.created_at)return {outcome:'ignored'};
 const messageHash=await sha256(evidence.messageId),evidenceHash=await sha256(JSON.stringify(evidence));
 const eventHash=await sha256(`${messageHash}:${evidence.inquiryId}:${evidence.deliveredAt}:${evidence.transactionId}`);
 const previous=await env.DB.prepare('SELECT outcome FROM zoho_delivery_evidence WHERE event_hash=?').bind(eventHash).first();
 if(previous)return {outcome:'duplicate',eventHash};
 const old=await env.DB.prepare('SELECT * FROM receipts WHERE inquiry_id=? OR message_hash=?').bind(intent.id,messageHash).first();
 const now=iso(Date.now());let outcome=old?(old.inquiry_id===intent.id?'duplicate':'conflict'):'inserted';
 const encrypted=await sealEvidence(env,evidence);
 // Independent UNIQUE constraints protect both manual/API races and replay.
 await env.DB.batch([
  env.DB.prepare('INSERT OR IGNORE INTO receipts VALUES (?,?,?,?,?,?)').bind(intent.id,messageHash,evidence.deliveredAt,now,evidenceHash,'zoho_delivery_log'),
  env.DB.prepare('INSERT OR IGNORE INTO zoho_delivery_evidence VALUES (?,?,?,?,?,?,?)').bind(eventHash,intent.id,messageHash,evidenceHash,encrypted,now,outcome)
 ]);
 const stored=await env.DB.prepare('SELECT message_hash FROM receipts WHERE inquiry_id=?').bind(intent.id).first();
 if(stored?.message_hash!==messageHash)outcome=old?.inquiry_id===intent.id?'duplicate':'conflict';
 return {outcome,eventHash};
}
export async function syncZoho(env,{fetcher=fetch,now=Date.now()}={}){
 if(!configured(env))return {ok:false,status:'not_configured'};
 const owner=crypto.randomUUID();const start=Math.max(now-24*3600_000,Number(env.ZOHO_SYNC_START_MS)||0);
 await env.DB.prepare("INSERT OR IGNORE INTO zoho_sync_state (id,cursor_ms) VALUES ('smtp',?)").bind(start).run();
 const locked=await env.DB.prepare("UPDATE zoho_sync_state SET lease_owner=?,lease_until=?,last_attempt=? WHERE id='smtp' AND lease_until<=?").bind(owner,now+15*60_000,iso(now),now).run();
 if(!changes(locked))return {ok:false,status:'already_running'};
 const counts={scanned:0,matched:0,inserted:0,duplicates:0,ignored:0};
 try{
  const state=await env.DB.prepare("SELECT cursor_ms FROM zoho_sync_state WHERE id='smtp'").first();
  // Overlap absorbs late vendor logs. Cursor moves only after every page succeeds.
  const from=Math.max(Number(env.ZOHO_SYNC_START_MS)||0,state.cursor_ms-24*3600_000),until=now;
  const token=await accessToken(env,fetcher);let pageKey='',prevKey='',complete=false;
  for(let page=0;page<10;page++){
   const response=await jsonResponse(await fetcher(`https://mail.zoho.com/api/organization/${env.ZOHO_ORG_ID}/smtplogs`,{method:'POST',headers:{Authorization:'Zoho-oauthtoken '+token,'Content-Type':'application/json'},body:JSON.stringify({fromDateTime:from,toDateTime:until,searchCriteria:'toAddr',searchKey:target,limit:100,isNext:page>0,isPrevious:false,pageKey,prevKey,isReverseSearch:true}),signal:AbortSignal.timeout(10000)}),'logs');
   const logs=response.data?.response;if(!Array.isArray(logs))throw new Error('logs_missing_records');
   for(const log of logs){counts.scanned++;const r=await importLog(env,log);if(r.outcome==='ignored')counts.ignored++;else{counts.matched++;if(r.outcome==='inserted')counts.inserted++;else if(r.outcome==='duplicate')counts.duplicates++;else throw new Error('message_attribution_conflict');}}
   const next=response.data.hnxt===true||response.data.hnxt==='true';
   if(!next){complete=true;break;}
   const key=response.data.pagekey;if(typeof key!=='string'||!key||key===pageKey)throw new Error('logs_invalid_pagination');pageKey=key;prevKey=String(response.data.pagePrevKey||'');
  }
  if(!complete)throw new Error('logs_pagination_limit');
  await env.DB.prepare("UPDATE zoho_sync_state SET cursor_ms=?,last_success=?,last_error=NULL,scanned=?,matched=?,inserted=?,duplicates=?,ignored=? WHERE id='smtp' AND lease_owner=?").bind(until,iso(Date.now()),counts.scanned,counts.matched,counts.inserted,counts.duplicates,counts.ignored,owner).run();
  return {ok:true,status:'synced',...counts};
 }catch(e){
  // Never log vendor bodies, access/refresh tokens or email subjects.
  const code=/^(oauth|logs|evidence|message)_[a-z0-9_]+$/.test(e.message||'')?e.message:'sync_failed';
  await env.DB.prepare("UPDATE zoho_sync_state SET last_error=? WHERE id='smtp' AND lease_owner=?").bind(code,owner).run();return {ok:false,status:code,...counts};
 }finally{await env.DB.prepare("UPDATE zoho_sync_state SET lease_owner=NULL,lease_until=0 WHERE id='smtp' AND lease_owner=?").bind(owner).run();}
}
export async function syncStatus(env){const state=await env.DB.prepare("SELECT cursor_ms,last_attempt,last_success,last_error,scanned,matched,inserted,duplicates,ignored,lease_until FROM zoho_sync_state WHERE id='smtp'").first();const stale=!state?.last_success||Date.now()-Date.parse(state.last_success)>15*60_000;return {configured:configured(env),intervalMinutes:5,stale,...state};}
export async function evidenceFor(env,inquiryId){const rows=await env.DB.prepare('SELECT encrypted_evidence,evidence_hash,observed_at,outcome FROM zoho_delivery_evidence WHERE inquiry_id=? ORDER BY observed_at DESC LIMIT 50').bind(inquiryId).all();return Promise.all(rows.results.map(async r=>({evidence:await openEvidence(env,r.encrypted_evidence),sha256:r.evidence_hash,observedAt:r.observed_at,outcome:r.outcome})));}
