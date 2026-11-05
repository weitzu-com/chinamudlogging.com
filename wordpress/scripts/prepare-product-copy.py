#!/usr/bin/env python3
"""Prepare reviewable copy changes from a private, read-only product inventory."""
import argparse,json,html,re,hashlib
from pathlib import Path
root=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser();parser.add_argument('--inventory',type=Path,required=True);parser.add_argument('--output',type=Path,required=True)
parser.add_argument('--seo-inventory',type=Path,required=True)
args=parser.parse_args()
seo=json.loads(args.seo_inventory.read_text())
inventory=json.loads(args.inventory.read_text())
legacy=json.loads((root/'content-repairs/legacy-copy-20261105.json').read_text())
roles=json.loads((root/'content-repairs/catalogue-role-copy-20261105.json').read_text())
priority=json.loads((root/'extensions/cml-seo-priority.json').read_text())['equipment']
changes=[];audited=[]
for p in inventory['products']:
    if p['status']!='publish':continue
    reason='No placeholder pattern; preserve existing reviewed copy.';after=None;evidence={}
    if p['language']=='en' and str(p['id']) in legacy:
        title,excerpt=legacy[str(p['id'])]
        # The existing public excerpt filter has already reviewed these three entries.
        if p['slug'] in priority:excerpt=priority[p['slug']]['intro']
        after={'title':title,'excerpt':excerpt}
        evidence={'source_id':p['meta']['_cml_source_id'],'source_url':p['meta']['_cml_source_url'],'basis':'Retained record identity/body and existing source-bounded selection guidance. No new numerical specification.'}
        reason='Legacy slug/repetition, truncated technical extract, generic company boilerplate or title readability.'
    elif p['language']=='en' and 'source catalogue note:' in p['excerpt']:
        rows=p['meta']['_cml_specifications']
        fields={r['label']:r['raw'] for r in rows}
        name=fields['Name'].split(' / ')[0].strip().rstrip('.')
        assert name in roles,(p['id'],name)
        source=fields['Source URL'].strip().rstrip('.')
        assert re.fullmatch(r'https://emall\.epec\.com/productd/\d+/\d+',source)
        excerpt=roles[name]
        if 'Wireless ' in fields['Name'] and not excerpt.lower().startswith('wireless'):
            excerpt='Wireless '+excerpt[0].lower()+excerpt[1:]
        after={'title':p['title'],'excerpt':excerpt}
        evidence={'source_url':source,'retained_fields':fields,'basis':'Source item name establishes component role only. Catalogue designation/values remain unchanged; no performance or compatibility is inferred.'}
        reason='Imported source-catalogue-note template; replace with source-named role and item-specific selection checks.'
    if after and after=={'title':p['title'],'excerpt':p['excerpt']}:after=None
    audited.append({'id':p['id'],'language':p['language'],'title':p['title'],'changed':bool(after),'disposition':reason})
    if not after:continue
    protected={'status':p['status'],'slug':p['slug'],'content_sha256':hashlib.sha256(p['content'].encode()).hexdigest(),'language':p['language'],'translations':p['translations'],'families':p['families'],'thumbnail':p['thumbnail']['id'],'sku':p['meta']['_cml_sku'],'specifications':p['meta']['_cml_specifications'],'source_id':p['meta']['_cml_source_id'],'source_url':p['meta']['_cml_source_url'],'description_sha256':hashlib.sha256(p['meta']['_cml_description'].encode()).hexdigest()}
    before={'title':p['title'],'excerpt':p['excerpt'],**seo[str(p['id'])]}
    after['seo_description']=before['seo_description']
    after['seo_title']=before['seo_title']
    if before['seo_description'] in ['',p['excerpt'],p['slug']]:after['seo_description']=after['excerpt']
    if before['seo_title'] in [p['title'],p['slug']]:after['seo_title']=after['title']
    changes.append({'id':p['id'],'url':p['url'],'before':before,'after':after,'protected':protected,'reason':reason,'evidence':evidence})
args.output.parent.mkdir(parents=True,exist_ok=True)
args.output.write_text(json.dumps({'observed_at':inventory['observed_at'],'audited':audited,'changes':changes},ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'audited':len(audited),'changed':len(changes),'titles':sum(c['before']['title']!=c['after']['title'] for c in changes),'excerpts':sum(c['before']['excerpt']!=c['after']['excerpt'] for c in changes),'languages_changed':sorted({c['protected']['language'] for c in changes})}))
