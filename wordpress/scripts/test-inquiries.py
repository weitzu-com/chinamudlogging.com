#!/usr/bin/env python3
"""Owner-authorized staging receipt tests; never contains real customer data."""
import argparse,base64,json,re,uuid,urllib.request
from pathlib import Path
parser=argparse.ArgumentParser();parser.add_argument('--languages',default='en,zh,es,ru');parser.add_argument('--run-label',default='local');args=parser.parse_args()
if not re.fullmatch(r'[a-z0-9-]+',args.run_label):raise SystemExit('Invalid run label')
cache=Path.home()/'.cache/chinamudlogging-wordpress';auth=json.loads((cache/'preview-access.json').read_text())
base='https://preview.chinamudlogging.com'
headers={'Authorization':'Basic '+base64.b64encode((auth['user']+':'+auth['password']).encode()).decode(),'User-Agent':'ChinaMudLogging-Acceptance/1.0','Origin':base}
output=cache/('results/staging-inquiries-'+args.run_label+'.json');results=json.loads(output.read_text()) if output.exists() else []
for lang in args.languages.split(','):
 if any(r['language']==lang and r.get('saved') for r in results):continue
 with urllib.request.urlopen(urllib.request.Request(base+'/wp-json/cml/v1/form-token',headers=headers),timeout=30) as response:nonce=json.load(response)['nonce']
 payload={'nonce':nonce,'request_id':str(uuid.uuid4()),'language':lang,'name':'WEBSITE ACCEPTANCE TEST','email':'test@example.invalid','company':'TEST ONLY','country':'TEST ONLY','product':'CML acceptance '+lang,'message':'TEST ONLY — authorized seven-language website receipt verification. No customer data.','source':'/contact-us/?test_marker=must-not-be-saved','consent':True,'website':''}
 request=urllib.request.Request(base+'/wp-json/cml/v1/inquiries',data=json.dumps(payload).encode(),headers={**headers,'Content-Type':'application/json'})
 with urllib.request.urlopen(request,timeout=40) as response:
  data=json.load(response);row={'language':lang,'status':response.status,**data}
 with urllib.request.urlopen(request,timeout=40) as response:
  duplicate=json.load(response);row['duplicateSameReceipt']=response.status==200 and duplicate['receipt']==data['receipt']
 results.append(row);output.write_text(json.dumps(results,indent=2)+'\n');print(json.dumps(row),flush=True)
