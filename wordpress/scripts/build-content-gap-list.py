"""Build a document-request inventory from an explicitly supplied read-only export."""
import argparse,csv,json,re
from collections import Counter
from pathlib import Path
parser=argparse.ArgumentParser();parser.add_argument('inventory',type=Path);parser.add_argument('--out',type=Path,required=True);args=parser.parse_args()
root=Path(__file__).resolve().parents[1];copy=json.loads((root/'extensions/cml-content-depth.json').read_text());data=json.loads(args.inventory.read_text());args.out.mkdir(parents=True,exist_ok=True)
rows=[];counts=Counter();samples=[]
for item in data:
 specs={x['label']:x['raw'] for x in item.get('specifications',[]) if isinstance(x,dict) and 'label' in x and 'raw' in x}
 source=specs.get('Source URL',item.get('sourceUrl','')).strip().rstrip('.')
 name=specs.get('Name','');sku=item.get('sku','');component=any(x in name for x in ['电路板','支架','固定架','安装架','模块'])
 has_priority=item.get('priorityGuide',False) and not component
 role='';profile={}
 if not has_priority and sku.strip() and re.fullmatch(r'https://emall\.epec\.com/productd/\d+/\d+',source):
  for key,r in copy['profiles'].items():
   if any(n in name for n in r['names']):role=key;profile=r;break
 if role:state='source identity and role-specific document gaps';request='; '.join(profile['missing']);samples.append({'id':item['id'],'slug':item['slug'],'role':role,'name':name,'sku':sku})
 elif item['slug'] in copy['legacy']:state='unknown-model identification guide';request='; '.join(copy['legacy'][item['slug']]['missing'])
 elif item.get('existingGuide') or has_priority or item['slug'] in ['mud-logging-unit','sls-mud-logging-unit','surface-logging-systems']:state='existing guide retained';request='Review the exact offered model/version against current datasheet, drawings and applicable order-specific documents'
 elif 'manual-enrichment-draft' in item['slug']:state='separate manual-enrichment record; not transferred';request='Confirm source file, exact model applicability and editorial approval; do not transfer category figures to a different product'
 elif item.get('sourceId') and item.get('rawWords',0)>=200:state='existing detailed source retained';request='Revalidate the current offered version and matched documents; no new performance claim made'
 else:state='pending source/model confirmation';request='Confirm item function and engineering model identity before adding technical content; obtain model-matched datasheet, drawing and interface information'
 counts[state]+=1
 links=sorted(set(re.findall(r'https?://[^\s<>"\']+\.pdf(?:\?[^\s<>"\']*)?',item.get('content',''),re.I)))
 rows.append({'id':item['id'],'url':item['url'],'title':item['title'],'catalogue_designation_verbatim':sku,'source_name_verbatim':name,'source_reference':source,'available_source_fields':'; '.join(specs),'raw_source_words':item.get('rawWords',0),'treatment':state,'role':role,'existing_pdf_links_to_review':'; '.join(links),'documents_or_identity_to_confirm':request,'approval_status':'No new performance or certification verified in this pass; check exact assembly/order documents','source_fingerprint':item['fingerprint']})
with (args.out/'待补资料清单_20261105.csv').open('w',encoding='utf-8-sig',newline='') as f:
 w=csv.DictWriter(f,fieldnames=list(rows[0]));w.writeheader();w.writerows(rows)
summary={'englishRecords':len(data),'treatmentCounts':dict(counts),'reclassifiedComponents':sum(1 for x in data if x.get('priorityGuide') and any(n in next((s['raw'] for s in x.get('specifications',[]) if s.get('label')=='Name'),'') for n in ['电路板','支架','固定架','安装架','模块'])),'profileCounts':dict(Counter(x['role'] for x in samples)),'targets':samples}
(args.out/'content-treatment-summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({k:v for k,v in summary.items() if k!='targets'},ensure_ascii=False))
