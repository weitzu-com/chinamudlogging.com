#!/usr/bin/env python3
"""Public seven-language keyword/category and empty-state acceptance."""
import concurrent.futures,json,urllib.request
from pathlib import Path
from urllib.parse import urlencode
from html.parser import HTMLParser
cache=Path.home()/'.cache/chinamudlogging-wordpress'
bundle=json.loads((cache/'bundle/bundle.json').read_text())
strings=json.loads((Path(__file__).resolve().parents[1]/'plugin/cml-core/strings.json').read_text())
product=next(r for r in bundle['records'] if r['sourceId']=='product:245' and r['language']=='en')
class Page(HTMLParser):
 def __init__(self):super().__init__();self.links=[];self.selected=[]
 def handle_starttag(self,t,a):
  a=dict(a)
  if t=='a':self.links.append(a.get('href'))
  if t=='option' and 'selected' in a:self.selected.append(a.get('value'))
def inspect(lang):
 base='https://www.chinamudlogging.com';prefix='' if lang=='en' else '/'+lang;suffix='' if lang=='en' else '-'+lang
 catalogue=prefix+'/product'+suffix+'/'
 family=product['category']+suffix
 expected=base+prefix+'/product/'+product['slug']+suffix+'/'
 errors=[]
 for kind,path in [('search',catalogue+'?'+urlencode({'q':'8D08','family':family})),('empty',catalogue+'?q=CML-no-such-product-20261104'),('family',prefix+'/product-category/'+family+'/')]:
  try:
   with urllib.request.urlopen(urllib.request.Request(base+path,headers={'User-Agent':'ChinaMudLogging-Acceptance/1.0'}),timeout=40) as r:body=r.read().decode()
   page=Page();page.feed(body)
   if kind=='search' and expected not in page.links:errors.append('search target missing')
   if kind=='empty' and strings[lang]['no_results'] not in body:errors.append('empty-state missing')
   if kind=='family' and family not in page.selected:errors.append('category search context not selected')
  except Exception as e:errors.append(kind+': '+str(e))
 return {'language':lang,'errors':errors}
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:results=list(pool.map(inspect,bundle['languages']))
(cache/'results/catalogue-results.json').write_text(json.dumps(results,indent=2)+'\n')
failed=[r for r in results if r['errors']];print(json.dumps({'languages':7,'scenarios':21,'failed':failed}))
if failed:raise SystemExit(1)
