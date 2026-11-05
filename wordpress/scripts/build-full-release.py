#!/usr/bin/env python3
"""Build only the 60 approved replacements; retain both English and old-body gates."""
import hashlib,json,sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parent))
from full_content import ROOT,load_approved,render,localized_specs,digest
from prepare import source_specifications
cache=Path.home()/'.cache/chinamudlogging-wordpress';output=cache/'full-translation'
baseline=json.loads((output/'baseline-bundle.json').read_text());media=json.loads((cache/'results/media-map.json').read_text())
rows={r['sourceId']+':'+r['language']:r for r in baseline['records']}
products={p['id']:p for p in json.loads((ROOT/'src/data/products.json').read_text())};data=load_approved()
if len(data)!=60:raise ValueError('Exactly 60 approved translations required')
def deployed_body(value):
    for source,target in media.items():value=value.replace('"'+source+'"','"'+target+'"')
    return value
replacements=[]
for key,row in data.items():
    pid=row['sourceId'];lang=row['language'];product=products[pid];source_key='product:'+str(pid)
    previous=rows[source_key+':'+lang];english=rows[source_key+':en']
    content=deployed_body(render(product,lang,row))
    replacements.append({'key':source_key+':'+lang,'sourceKey':source_key+':en','language':lang,'slug':previous['slug'],'sourceSHA256':row['sourceSHA256'],'translationSHA256':digest(row),'expectedEnglishContent':deployed_body(english['content']),'expectedEnglishContentSHA256':hashlib.sha256(deployed_body(english['content']).encode()).hexdigest(),'expectedPreviousContent':deployed_body(previous['content']),'expectedPreviousContentSHA256':hashlib.sha256(deployed_body(previous['content']).encode()).hexdigest(),'content':content,'contentSHA256':hashlib.sha256(content.encode()).hexdigest(),'specifications':localized_specs(product,row,source_specifications(product),previous['fields']['specifications'])})
manifest={'schemaVersion':1,'batch':'full-translations-v1','reviewSHA256':hashlib.sha256((ROOT/'wordpress/localization/review.json').read_bytes()).hexdigest(),'records':replacements}
(output/'publish.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'records':len(replacements),'structuredSpecificationRows':sum(len(r['specifications']) for r in replacements),'output':str(output/'publish.json')}))
