// @ts-nocheck
// One-time loopback setup. Credentials never appear in stdout, argv or Git.
import {createServer} from 'node:http';
import {randomBytes} from 'node:crypto';
import {mkdir,writeFile} from 'node:fs/promises';
import {homedir} from 'node:os';
const nonce=randomBytes(24).toString('hex'),port=8876,path='/setup/'+nonce;
const dir=homedir()+'/.config/chinamudlogging-funnel';
let configured=false;
const server=createServer(async(req,res)=>{
 res.setHeader('Cache-Control','no-store');res.setHeader('Content-Security-Policy',"default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'");res.setHeader('X-Content-Type-Options','nosniff');
 if(req.headers.host!==`127.0.0.1:${port}`||req.url!==path){res.writeHead(404);return res.end('Not found');}
 if(req.method==='GET'){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(`<!doctype html><meta name="viewport" content="width=device-width"><title>Zoho read-only SMTP setup</title><style>body{max-width:660px;margin:50px auto;padding:24px;font:17px system-ui;line-height:1.6}input{display:block;width:100%;padding:10px;margin:8px 0 20px}button{padding:12px 20px}</style><h1>Zoho 组织日志只读同步</h1><p>仅为网站询盘配置组织 SMTP 日志读取。凭据写入本机受保护配置，不回显到网页。授权范围：ZohoMail.partner.organization.READ。</p><form method="post" autocomplete="off"><label>Client ID<input name="clientId" required autocomplete="off"></label><label>Client Secret<input type="password" name="clientSecret" required autocomplete="off"></label><label>Grant code<input type="password" name="code" required autocomplete="off"></label><button>验证并保存本机配置</button></form>`);}
 if(req.method!=='POST'||req.headers.origin!==`http://127.0.0.1:${port}`||configured){res.writeHead(403);return res.end('Forbidden');}
 try{
  let body='';for await(const chunk of req){body+=chunk;if(body.length>8192)throw new Error('body_too_large');}
  const form=new URLSearchParams(body);const clientId=form.get('clientId'),clientSecret=form.get('clientSecret'),code=form.get('code');if(!clientId||!clientSecret||!code)throw new Error('missing_fields');
  const r=await fetch('https://accounts.zoho.com/oauth/v2/token',{method:'POST',body:new URLSearchParams({grant_type:'authorization_code',client_id:clientId,client_secret:clientSecret,code}),signal:AbortSignal.timeout(15000)});const tokens=await r.json();if(!r.ok||!tokens.access_token||!tokens.refresh_token)throw new Error('token_exchange_failed');
  const o=await fetch('https://mail.zoho.com/api/organization',{headers:{Authorization:'Zoho-oauthtoken '+tokens.access_token},signal:AbortSignal.timeout(15000)});const org=await o.json();if(!o.ok||org.status?.code!==200||!org.data?.zoid)throw new Error('organization_lookup_failed');
  if(!org.data.domains?.includes('chinamudlogging.com'))throw new Error('wrong_organization');
  await mkdir(dir,{recursive:true,mode:0o700});
  const config={clientId,clientSecret,refreshToken:tokens.refresh_token,orgId:String(org.data.zoid),scope:'ZohoMail.partner.organization.READ'};
  await writeFile(dir+'/zoho.json',JSON.stringify(config),{mode:0o600});configured=true;
  res.setHeader('Content-Type','text/html; charset=utf-8');res.end('<!doctype html><meta charset="utf-8"><title>Zoho setup complete</title><h1>组织级只读授权已保存</h1><p>已核验组织包含 chinamudlogging.com；凭据未回显。可关闭此页面。</p>');
  console.log('Zoho organization verified; scoped credentials saved with mode 0600.');
 }catch(e){res.writeHead(400,{'Content-Type':'text/plain; charset=utf-8'});res.end('Setup failed: '+(e.message||'unknown').replace(/[^a-z_]/g,''));}
});
server.listen(port,'127.0.0.1',()=>console.log(`Open http://127.0.0.1:${port}${path}`));
setTimeout(()=>server.close(),30*60_000).unref();
