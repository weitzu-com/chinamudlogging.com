// Reuse this site's existing readonly Zoho delivery-log authorization.
// Credentials and vendor response bodies are never printed or archived.
import {readFile,writeFile} from 'node:fs/promises';
import {homedir} from 'node:os';
import {accessToken} from '../../workers/funnel/zoho-sync.mjs';
const cache=homedir()+'/.cache/chinamudlogging-wordpress';
const expected=[];
for(const name of ['staging-inquiries-local.json','inquiries-server.json'])for(const r of JSON.parse(await readFile(cache+'/results/'+name,'utf8')))expected.push({language:r.language,receipt:r.receipt});
const allowed=new Set(expected.map(r=>r.receipt));
const extra=process.argv[2]==='--receipt'?process.argv[3]:null;
if(extra){if(!/^[a-f0-9-]{36}$/.test(extra))throw new Error('Invalid test receipt');expected.push({language:'en-live',receipt:extra});allowed.add(extra);}
const c=JSON.parse(await readFile(homedir()+'/.config/chinamudlogging-funnel/zoho.json','utf8'));
const env={ZOHO_CLIENT_ID:c.clientId,ZOHO_CLIENT_SECRET:c.clientSecret,ZOHO_REFRESH_TOKEN:c.refreshToken};
const token=await accessToken(env);
const evidence=[];let pageKey='',prevKey='',complete=false;
for(let page=0;page<10;page++){
 const response=await fetch(`https://mail.zoho.com/api/organization/${c.orgId}/smtplogs`,{method:'POST',headers:{Authorization:'Zoho-oauthtoken '+token,'Content-Type':'application/json'},body:JSON.stringify({fromDateTime:Date.UTC(2026,10,4,6,0),toDateTime:Date.now(),searchCriteria:'toAddr',searchKey:'sales@chinamudlogging.com',limit:100,isNext:page>0,isPrevious:false,pageKey,prevKey,isReverseSearch:true}),signal:AbortSignal.timeout(15000)});
 if(!response.ok)throw new Error('Zoho delivery-log request failed: '+response.status);
 const data=await response.json();if(data.status?.code!==200||!Array.isArray(data.data?.response))throw new Error('Zoho delivery-log response invalid');
 for(const log of data.data.response){
  const text=String(log.from?.subject||'')+' '+String(log.from?.msgId||'');
  const receipt=[...allowed].find(id=>text.includes(id));if(!receipt||log.mailType!=='in')continue;
  for(const r of log.recipients||[])if(r.rcpt?.toLowerCase()==='sales@chinamudlogging.com')evidence.push({receipt,messageId:log.from.msgId,deliveredAt:new Date(Number(r.timeMillis)).toISOString(),status:r.deliveryStatus,deliveryType:r.deliveryType,isSpam:r.isSpam||'unknown'});
 }
 if(data.data.hnxt!==true&&data.data.hnxt!=='true'){complete=true;break;}
 pageKey=data.data.pagekey;prevKey=String(data.data.pagePrevKey||'');if(!pageKey)throw new Error('Zoho pagination incomplete');
}
if(!complete)throw new Error('Zoho pagination limit reached');
const results=expected.map(r=>({...r,evidence:evidence.filter(e=>e.receipt===r.receipt),delivered:evidence.some(e=>e.receipt===r.receipt&&['delivered','mail delivered'].includes(String(e.status).toLowerCase())&&e.deliveryType==='MAILBOX DELIVERY')}));
const report={checkedAt:new Date().toISOString(),source:'existing site readonly Zoho SMTP delivery-log API',results,passed:results.filter(r=>r.delivered).length,total:results.length};
await writeFile(cache+'/results/mail-delivery'+(extra?'-live':'')+'.json',JSON.stringify(report,null,2)+'\n',{mode:0o600});
console.log(JSON.stringify({passed:report.passed,total:report.total,pending:results.filter(r=>!r.delivered).map(r=>r.language)}));
if(report.passed!==report.total)process.exitCode=1;
