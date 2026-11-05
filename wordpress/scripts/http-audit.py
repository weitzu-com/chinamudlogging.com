#!/usr/bin/env python3
import concurrent.futures,json,urllib.request
from pathlib import Path
BASE='http://127.0.0.1:9400'
cache=Path.home()/'.cache/chinamudlogging-wordpress'
bundle=json.loads((cache/'bundle/bundle.json').read_text())
paths=sorted({r['sourceUrl'] for r in bundle['records'] if r['language']=='en'} | {'/product-category/'+c['slug']+'/' for c in bundle['categories']})
def inspect(path):
    try:
        with urllib.request.urlopen(BASE+path,timeout=30) as response:
            body=response.read().decode(); final=response.geturl()
            return {'path':path,'status':response.status,'exactPath':final.rstrip('/')==(BASE+path).rstrip('/'),'fatalError':('Fatal error' in body or 'Warning:' in body)}
    except Exception as e:return {'path':path,'error':str(e)}
with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:results=list(pool.map(inspect,paths))
for source,target in bundle['redirects'].items():
    with urllib.request.urlopen(BASE+source,timeout=30) as response:
        results.append({'path':source,'status':response.status,'exactPath':response.geturl().rstrip('/')==(BASE+target).rstrip('/'),'fatalError':False,'redirect':True})
for lang in bundle['languages'][1:]:results.append(inspect('/'+lang+'/'))
failed=[r for r in results if r.get('status')!=200 or not r.get('exactPath') or r.get('fatalError')]
(cache/'results/http-results.json').write_text(json.dumps({'checked':len(results),'failed':failed,'results':results},indent=2)+'\n')
print(json.dumps({'checked':len(results),'failed':failed}))
if failed:raise SystemExit(1)
