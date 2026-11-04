// @ts-nocheck
import {readFile,writeFile,mkdir} from 'node:fs/promises';
import {homedir} from 'node:os';
import {dirname,resolve} from 'node:path';
import {createHash,randomUUID} from 'node:crypto';
const args=process.argv.slice(2),command=args.shift();
const option=name=>{const i=args.indexOf('--'+name);return i<0?undefined:args[i+1];};
const configPath=process.env.CML_FUNNEL_CONFIG||`${homedir()}/.config/chinamudlogging-funnel/admin.json`;
const config=JSON.parse(await readFile(configPath,'utf8'));
if(!['https://www.chinamudlogging.com/api/funnel'].includes(config.baseUrl)&&!/^https:\/\/chinamudlogging-funnel\.[a-z0-9-]+\.workers\.dev$/.test(config.baseUrl))throw new Error('Untrusted service URL');
async function request(path,body){const r=await fetch(config.baseUrl+path,{method:body?'POST':'GET',headers:{Authorization:`Bearer ${config.adminKey}`,'Content-Type':'application/json'},...(body?{body:JSON.stringify(body)}:{})});const data=await r.json();if(!r.ok)throw new Error(`${r.status}: ${data.error}`);return data;}
let result;
if(command==='zoho-status')result=await request('/admin/zoho/status');
else if(command==='zoho-sync')result=await request('/admin/zoho/sync',{});
else if(command==='zoho-evidence')result=await request('/admin/zoho/evidence?inquiry='+encodeURIComponent(option('inquiry')||''));
else if(command==='report') {
 const params=new URLSearchParams();for(const k of ['from','to'])if(option(k))params.set(k,option(k));if(args.includes('--test'))params.set('test','1');
 result=await request('/admin/report?'+params);
 if(option('out')) {
  const out=resolve(option('out'));await mkdir(dirname(out),{recursive:true});await writeFile(out,JSON.stringify(result,null,2)+'\n',{mode:0o600});
  const escape=value=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const segmentRows=(result.segments||[]).map(s=>`<tr><td>${escape(s.source)}</td><td>${escape(s.landing_path)}</td><td>${s.visits}</td><td>${s.intent_sessions}</td><td>${s.received_inquiries}</td><td>${s.qualified_inquiries}</td><td>${s.pending_review}</td></tr>`).join('');
  const pct=n=>n===null?'—':(n*100).toFixed(1)+'%';
  const cards=[['访问会话',result.visits],['询盘意向会话',result.intent_sessions],['实际收件',result.received_inquiries],['有效询盘',result.qualified_inquiries],['待审核',result.pending_review]];
  const html=`<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>ChinaMudLogging 询盘漏斗</title><style>body{font:16px system-ui;background:#f2f5f7;color:#102839;margin:40px auto;max-width:1050px;padding:20px}section{display:flex;gap:16px;flex-wrap:wrap}article{background:white;padding:24px;border-radius:12px;min-width:130px}strong{display:block;font-size:36px}p{line-height:1.7}h1{font-size:30px}</style><h1>ChinaMudLogging · 询盘漏斗${result.cohort.isTest?' · 测试数据':''}</h1><p>${result.cohort.from.slice(0,10)} 至 ${new Date(Date.parse(result.cohort.until)-1).toISOString().slice(0,10)} · UTC · 按访问发生时间归属</p><section>${cards.map(([label,n])=>`<article>${label}<strong>${n}</strong></article>`).join('')}</section><h2>来源 × 落地页</h2><div style="overflow:auto"><table><thead><tr><th>来源</th><th>落地页</th><th>访问</th><th>意向会话</th><th>实际收件</th><th>有效</th><th>待审</th></tr></thead><tbody>${segmentRows}</tbody></table></div><p>Google 来源根据浏览器引荐域名识别，不关联个人查询词。历史 search 包含其他搜索引擎。GSC 使用太平洋时间，当前报表使用 UTC；历史点击不可直接与新埋点收件相除。</p><p>访问→意向 ${pct(result.rates.visit_to_intent)} · 意向→收件 ${pct(result.rates.intent_to_received)} · 收件→有效 ${pct(result.rates.received_to_qualified)}</p><p>准备邮件、点击按钮只算意向。实际收件必须有邮箱投递证据，有效询盘必须经业务审核。访问为 30 分钟匿名会话，不是独立人数。后续收件与审核会回填原访问时间段。</p><p>更新 ${result.generatedAt} · 原始 JSON 与本文件同目录。无分母时显示 —，不把未知写成 0%。</p>`;
  await writeFile(out.replace(/\.json$/,'')+'.html',html,{mode:0o600});
 }
} else if(command==='receipt') {
 if(!option('file'))throw new Error('receipt requires --file with operator-verified delivery evidence');
 const raw=await readFile(option('file'));const e=JSON.parse(raw);
 if(e.operatorVerified!==true||!e.referenceInMessage||e.referenceInMessage!==`CML-${e.inquiryId}`)throw new Error('Verify delivery log and reference in mailbox message before import');
 result=await request('/admin/receipt',{inquiryId:e.inquiryId,reference:e.referenceInMessage,messageId:e.messageId,deliveredAt:e.deliveredAt,evidenceSha256:createHash('sha256').update(raw).digest('hex'),source:e.source,recipient:e.recipient,status:e.status});
} else if(command==='qualify') {
 result=await request('/admin/qualify',{id:option('review-id')||randomUUID(),inquiryId:option('inquiry'),decision:option('decision'),reason:option('reason')});
} else if(command==='test-session') {
 result=await request('/admin/test-session',{path:'/',source:'direct'});
 if(option('out'))await writeFile(option('out'),JSON.stringify(result,null,2),{mode:0o600});
 result={...result,url:`https://www.chinamudlogging.com/?funnel_test=${result.id}.${result.token}`};
} else throw new Error('Commands: zoho-status, zoho-sync, zoho-evidence --inquiry UUID, report [--test --from YYYY-MM-DD --to YYYY-MM-DD --out FILE.json], receipt --file FILE, qualify --inquiry UUID --decision qualified|rejected --reason CODE, test-session [--out FILE]');
console.log(JSON.stringify(result,null,2));
