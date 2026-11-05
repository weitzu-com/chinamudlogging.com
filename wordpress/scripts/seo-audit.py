#!/usr/bin/env python3
"""Check rendered metadata for every imported published record; no browser automation."""
import base64,concurrent.futures,json,urllib.request
from html.parser import HTMLParser
from pathlib import Path
cache=Path.home()/'.cache/chinamudlogging-wordpress'
inventory=json.loads((cache/'results/server-inventory.json').read_text())
auth=json.loads((cache/'preview-access.json').read_text())
headers={'User-Agent':'ChinaMudLogging-Acceptance/1.0'}
if 'preview.' in inventory['home']:headers['Authorization']='Basic '+base64.b64encode((auth['user']+':'+auth['password']).encode()).decode()
class Head(HTMLParser):
 def __init__(self):super().__init__();self.canon=[];self.h1=0;self.lang='';self.rtl=False;self.alternates=[];self.description=''
 def handle_starttag(self,t,a):
  a=dict(a)
  if t=='html':self.lang=a.get('lang','');self.rtl=a.get('dir')=='rtl'
  if t=='h1':self.h1+=1
  if t=='link' and a.get('rel')=='canonical':self.canon.append(a.get('href'))
  if t=='link' and a.get('hreflang'):self.alternates.append({'language':a['hreflang'],'url':a.get('href')})
  if t=='meta' and a.get('name')=='description':self.description=a.get('content','')
def inspect(row):
 try:
  with urllib.request.urlopen(urllib.request.Request(row['url'],headers=headers),timeout=40) as response:body=response.read().decode()
  head=Head();head.feed(body)
  errors=[]
  if head.canon!=[row['url']]:errors.append('canonical')
  if head.h1!=1:errors.append('h1')
  if not head.lang.startswith(row['language']):errors.append('language')
  if head.rtl!=(row['language']=='ar'):errors.append('direction')
  if not head.description:errors.append('description')
  if row['language']!='en' and not any(x['language'].startswith('en') for x in head.alternates):errors.append('English alternate')
  if any(not x['url'].startswith(inventory['home']+'/') for x in head.alternates):errors.append('alternate host')
  return {**row,'errors':errors,'alternates':head.alternates}
 except Exception as error:return {**row,'errors':[str(error)]}
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:results=list(pool.map(inspect,inventory['urls']))
failed=[r for r in results if r['errors']]
(cache/'results/seo-results.json').write_text(json.dumps({'checked':len(results),'failed':failed,'results':results},indent=2,ensure_ascii=False)+'\n')
print(json.dumps({'checked':len(results),'failed':failed},ensure_ascii=False))
if failed:raise SystemExit(1)
