#!/usr/bin/env python3
"""Join the frozen source ledger to actual HTTP acceptance, without private inquiries."""
import csv,json
from pathlib import Path
from urllib.parse import urlsplit
root=Path(__file__).resolve().parents[1]/'migration'
cache=Path.home()/'.cache/chinamudlogging-wordpress'
bundle=json.loads((cache/'bundle/bundle.json').read_text())
inventory=json.loads((cache/'results/server-inventory.json').read_text())
audit=json.loads((cache/'results/http-results.json').read_text())
checks={r['path']:r for r in audit['results']}
def accepted(path):
 r=checks.get(path,{})
 return 'passed' if r.get('status')==200 and r.get('correctPath') and not r.get('metadataErrors') and not r.get('error') else 'needs-review'
with (root/'content-acceptance.csv').open('w') as f:
 writer=csv.writer(f,lineterminator="\n");writer.writerow(['source_id','language','target_url','content_review','HTTP_SEO_acceptance','environment'])
 for r in bundle['records']:
  candidates=[x for x in inventory['urls'] if x['language']==r['language'] and x['type']==r['type'] and (urlsplit(x['url']).path.rstrip('/').endswith('/'+r['slug']) or r.get('pageKey')=='home' and urlsplit(x['url']).path==('/' if r['language']=='en' else '/'+r['language']+'/'))]
  if len(candidates)!=1:raise RuntimeError('Ambiguous content destination: '+r['sourceId']+':'+r['language'])
  url=candidates[0]['url'];writer.writerow([r['sourceId'],r['language'],url,r['review'],accepted(urlsplit(url).path),inventory['home']])
 for c in bundle['categories']:
  for lang in bundle['languages']:
   path=('' if lang=='en' else '/'+lang)+'/product-category/'+c['slug']+('' if lang=='en' else '-'+lang)+'/'
   writer.writerow(['family:'+c['slug'],lang,inventory['home']+path,'source-preserved' if lang=='en' else 'approved',accepted(path),inventory['home']])
media_map=json.loads((cache/'results/media-map.json').read_text())
assets={m['path']:m for m in bundle['media']}
with (root/'media-ledger.csv').open('w') as f:
 writer=csv.writer(f,lineterminator="\n");writer.writerow(['source_path','target_url','source_sha256','bytes','HTTP_acceptance'])
 for source,target in sorted(media_map.items()):
  original=source if source in assets else '/images/source/'+source.removeprefix('/wp-content/uploads/')
  asset=assets[original];writer.writerow([source,target,asset['sha256'],asset['bytes'],accepted(source)])
attachment_file=cache/'bundle/original-wordpress-extraction/attachments.jsonl'
if attachment_file.exists():
 archive=json.loads((cache/'bundle/source-archive.json').read_text())
 fonts={str(x['sourceAttachmentId']):x for x in archive.get('retiredThemeFonts',[])}
 with (root/'original-media-disposition.csv').open('w') as f:
  writer=csv.writer(f,lineterminator='\n');writer.writerow(['source_attachment_id','original_path','type','disposition','target','sha256'])
  for line in attachment_file.read_text().splitlines():
   if not line.lstrip().startswith('{'):continue
   a=json.loads(line);source='/images/source/'+a['attached_file']
   if str(a['ID']) in fonts:
    font=fonts[str(a['ID'])];writer.writerow([a['ID'],font['sourcePath'],a['post_mime_type'],'private archive; retired theme font','private:retired-theme-fonts/'+a['attached_file'],font['sha256']])
   else:writer.writerow([a['ID'],'/wp-content/uploads/'+a['attached_file'],a['post_mime_type'],'public media with 301',media_map[source],assets[source]['sha256']])
source=json.loads((cache/'bundle/live-url-audit.json').read_text())
(root/'source-url-audit.json').write_text(json.dumps([{k:x[k] for k in ['path','status','finalUrl','sha256']} for x in source],indent=2)+'\n')
print('Acceptance ledger: 181 records, 35 localized families and '+str(len(media_map))+' media paths.')
