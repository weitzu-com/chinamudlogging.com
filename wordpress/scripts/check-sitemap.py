#!/usr/bin/env python3
import json,urllib.request,xml.etree.ElementTree as ET
from pathlib import Path
cache=Path.home()/'.cache/chinamudlogging-wordpress'
inventory=json.loads((cache/'results/server-inventory.json').read_text());bundle=json.loads((cache/'bundle/bundle.json').read_text())
base='https://www.chinamudlogging.com'
def fetch(url):
 with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'ChinaMudLogging-Acceptance/1.0'}),timeout=40) as r:return r.read().decode()
pending=[base+'/sitemap_index.xml'];urls=set();files=[]
while pending:
 url=pending.pop();root=ET.fromstring(fetch(url));files.append(url)
 locations=[x.text for x in root.iter() if x.tag=='{http://www.sitemaps.org/schemas/sitemap/0.9}loc']
 if root.tag.endswith('sitemapindex'):pending.extend(locations)
 else:urls.update(locations)
expected={x['url'] for x in inventory['urls']}
for c in bundle['categories']:
 for lang in bundle['languages']:expected.add(base+('' if lang=='en' else '/'+lang)+'/product-category/'+c['slug']+('' if lang=='en' else '-'+lang)+'/')
baseline_count=len(expected)
supplemental=json.loads((Path(__file__).resolve().parents[1]/'migration/live-supplemental-content.json').read_text())
for item in supplemental['published']:
 if not item['url'].startswith(base+'/product/'):raise ValueError('Invalid supplemental product URL')
 expected.add(item['url'])
robots=fetch(base+'/robots.txt');errors=[]
if expected-urls:errors.append('Published destinations missing from sitemap')
if urls-expected:errors.append('Unexpected destinations in sitemap')
if any(not x.startswith(base+'/') for x in urls):errors.append('Noncanonical sitemap host')
if any(line.strip().lower()=='disallow: /' for line in robots.splitlines()):errors.append('Robots disallows entire site')
if base+'/sitemap_index.xml' not in robots:errors.append('Robots sitemap pointer missing')
report={'migrationDestinations':baseline_count,'observedSupplementalDestinations':len(supplemental['published']),'checked':len(urls),'files':files,'errors':errors,'missing':sorted(expected-urls),'unexpected':sorted(urls-expected),'robots':robots}
(cache/'results/sitemap-results.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report))
if errors:raise SystemExit(1)
