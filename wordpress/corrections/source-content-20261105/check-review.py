"""Independent acceptance of scope, source identity, numeric notation and reviewed output."""
import hashlib
from html.parser import HTMLParser
import json
import pathlib
import re
import urllib.parse

root=pathlib.Path(__file__).resolve().parent
inputs=json.loads((root/'review-inputs.json').read_text());plan=json.loads((root/'reviewed-manifest.json').read_text())
sources={x['id']:x for x in inputs['sources']};posts={x['id']:x for x in inputs['posts']};count=0
def check(value,label):
    global count
    if not value:raise AssertionError(label)
    count+=1
def numbers(value):return re.findall(r'\d+(?:\.\d+)?',value)
class Text(HTMLParser):
    def __init__(self):super().__init__();self.text=[];self.links=[]
    def handle_data(self,data):self.text.append(data)
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='a':self.links.append(a.get('href',''))

check(len(plan['records'])==len(posts)==36,'36 unique targets')
check(len(sources)==32,'32 primary supplier records')
for item in plan['records']:
    before=posts[item['id']];after=item['after'];parser=Text();parser.feed(after['content'])
    check(item['url']==before['url'] and item['slug']==before['slug'] and item['language']=='en','Stable existing English route')
    check(item['expected']==before['frozenExpected'],'Precondition hashes preserved')
    check(not re.search('[\u4e00-\u9fff]',json.dumps(after,ensure_ascii=False)),'English output without Chinese residue')
    check(bool(after['title']) and bool(after['excerpt']) and len(parser.links)>=2,'Useful title, summary and source/navigation links')
    check(not any(k in after['meta'] for k in ['_cml_range','_cml_signal','_cml_interface','_cml_weight']),'No inferred independent performance fields')
    if item['type']=='cml_product':
        source=sources[item['id']];raw=source['fields']['规格型号'];designation=after['meta']['_cml_catalogue_designation']
        check(numbers(raw)==numbers(designation),'No numeric loss, insertion or precision change')
        check('±' not in designation,'No invented symmetric error bound')
        check(after['meta']['_cml_source_url']==source['expectedSource'] and source['expectedSource'] in parser.links,'Working source reference retained')
        check(bool(re.fullmatch(r'https://emall\.epec\.com/productd/141652/\d+',source['expectedSource'])),'Primary-source URL scope')
        check(bool(after['unconfirmedItems']) and 'Unconfirmed items' in after['content'],'Unconfirmed items visible')
        if any(token in raw for token in ['Ex','EX','iaT','ia J']):
            check('not verified certification' in after['content'],'Source markings not sold as certification')
        for token in ['400r','100p','100LEL','EXiaIIcT1T6','ExiaIIBT4','EXiaIIBT4','iaT4','8N1']:
            if re.search(r'(?<![A-Za-z0-9])'+re.escape(token)+r'(?![A-Za-z0-9])',raw):check(token in designation,'Exact unresolved token retained: '+token)
        for row in after['meta']['_cml_specifications']:
            check(set(row)=={'label','raw','value','unit'},'Structured field contract')
            if row['value']:
                check(all(n in numbers(raw) for n in numbers(row['value'])),'Typed values come from source notation')
                check(row['unit'] in ['V','m','mS/cm','ppm','%','% FS','°C'],'Reviewed unit whitelist')
    else:
        words=' '.join(parser.text).split();check(120<=len(words)<=310,'Article has a complete, focused answer')
        check('easier for finding' not in after['content'] and 'more technically advanced' not in after['content'],'Unsupported legacy claims removed')
        check(after['source']['originalSourceId']==before['meta']['_cml_source_id'],'Original article identity retained')
check(sum(bool(x['after']['meta']['_cml_sku']) for x in plan['records'] if x['type']=='cml_product')==6,'Six supplier model texts; descriptors are not manufactured SKUs')
records={x['id']:x for x in plan['records']}
check('>70 °C' in records[1402]['after']['meta']['_cml_catalogue_designation'],'Temperature comparator retained')
check(records[1378]['after']['meta']['_cml_specifications'][3]['raw']=='300 mS/cm','No invented zero-to-value range')
check('100LEL' in records[1042]['after']['meta']['_cml_catalogue_designation'] and '100 % LEL' not in records[1042]['after']['content'],'No inferred percent LEL scale')
print(json.dumps({'passed':True,'assertions':count,'articles':4,'products':32}))
