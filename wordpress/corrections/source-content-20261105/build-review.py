#!/usr/bin/env python3
"""Build reviewed English corrections from private, retained source snapshots."""
import argparse
import hashlib
import html
import json
import pathlib
import re
import urllib.parse

ROOT=pathlib.Path(__file__).resolve().parent
TERMS={
 '电扭矩传感电路板':'Electric torque sensor circuit board','电导率传感电路板':'Conductivity sensor circuit board',
 '无线数据采集传输模块':'Wireless data acquisition and transmission module','无线数据采集处理模块':'Wireless data acquisition and processing module',
 '超声波液位传感器':'Ultrasonic level sensor','反应池及压力传感器':'Reaction cell and pressure sensor',
 '硫化氢传感器':'Hydrogen sulfide sensor','一氧化碳传感器':'Carbon monoxide sensor','二氧化碳传感器':'Carbon dioxide sensor',
 '可燃气体传感器':'Combustible gas sensor','电导率传感器':'Conductivity sensor','泵冲传感器':'Pump-stroke sensor',
 '温度密度电导浮球体积':'Temperature / density / conductivity / float / volume',
 'EXPLORER综合录井仪':'EXPLORER integrated mud logging unit','无线传感器配件':'Wireless sensor accessories',
 '录井仪配件':'Mud logging instrument accessories','传感器配件':'Sensor accessories',
 '泵冲支架':'Pump-stroke sensor bracket','泵冲转速':'Pump-stroke / rotational-speed sensing',
 '绞车传感器':'Drawworks sensor','临近开关':'Proximity-switch','安装支架':'Mounting bracket','隔离串口':'Isolated serial interface',
 '防雷击保护':'Lightning protection','无线无腔':'Wireless, cavityless','半导体':'Semiconductor',
 '电化学':'Electrochemical','有线':'Wired','无线':'Wireless','光电':'Photoelectric','红外':'Infrared',
 '支架':'Bracket','夹子':'Clamp','安装盘':'Mounting plate','4路':'4-channel'
}
MODELS={818,842,1084,1099,1120,1126}
SPECIAL={
 860:['The catalogue designation describes pump-stroke / rotational-speed sensing. It is not a confirmed manufacturer model; bracket dimensions and supported sensor variants require confirmation.'],
 930:['The source uses the word semiconductor and lists 100 ppm and 1 ppm without separate parameter labels. Confirm the sensing principle and whether the values refer to range, resolution or another quantity.'],
 963:['The source contains 304 / clamp / mounting plate. The material grade, clamp size and mounting dimensions have not been independently confirmed.'],
 969:['The source lists temperature / density / conductivity / float / volume as category wording. This is not a verified compatibility list; identify the sensor and mounting drawing before ordering.'],
 993:['The catalogue describes four channels and lightning protection. Interface standards, isolation ratings and surge-test levels require the applicable documentation.'],
 1042:['100LEL is retained exactly as supplier shorthand. A percent sign, gas calibration basis or measurement range has not been inferred from that token.'],
 1120:['PMS103 8N1 is retained as the catalogue model/designation text. Baud rate, communication protocol and the supplied interface configuration require confirmation.'],
 1126:['The source names a reaction cell and pressure sensor. Confirm whether the quotation covers a cell, sensor or assembly, and obtain the applicable pressure rating and connection details.'],
 1183:['The catalogue lists infrared, 5 %, 24 V and 1 % FS without a complete parameter table. Confirm the concentration basis, voltage function and stated error conditions.'],
 1234:['400r is retained as an unresolved source token. It has not been expanded to revolutions per minute, strokes per minute or a maximum counting rate.'],
 1249:['100p is preserved with its source spelling. It has not been silently converted to ppm; confirm its meaning and how the separate 2 ppm notation is used.'],
 1402:['The source explicitly contains >70 °C. The comparator is preserved; this is not converted into a maximum or minimum operating-temperature rating.']
}

def digest(value):
    raw=value if isinstance(value,str) else json.dumps(value,ensure_ascii=False,sort_keys=True,separators=(',',':'))
    return hashlib.sha256(raw.encode()).hexdigest()

def units(text):
    text=text.replace('℃','°C').replace('＞','>').replace('＜','<')
    text=re.sub(r'(\d(?:\.\d+)?(?:-\d+(?:\.\d+)?)?)\s*(mS/cm|ppm|%FS|%|°C|V|m)(?![A-Za-z])',lambda m:m[1]+' '+('% FS' if m[2]=='%FS' else m[2]),text)
    text=re.sub(r'(mS/cm|ppm|% FS|°C|V|m)(?=\d)',r'\1 ',text)
    text=re.sub(r'(?<=\d)-(?=\d)','–',text)
    return re.sub(r'\s+',' ',text).strip()

def translate(text):
    for source in sorted(TERMS,key=len,reverse=True):text=text.replace(source,TERMS[source]+' ')
    text=re.sub(r'\s*/\s*(?!cm)',' / ',text)
    text=units(text)
    if re.search('[\u4e00-\u9fff]',text):raise ValueError('Unreviewed source term: '+text)
    return text

def p(text):return '<!-- wp:paragraph --><p>'+html.escape(text)+'</p><!-- /wp:paragraph -->'
def heading(text):return '<!-- wp:heading --><h2 class="wp-block-heading">'+html.escape(text)+'</h2><!-- /wp:heading -->'
def link(url,text):return '<!-- wp:paragraph --><p><a href="'+html.escape(url,quote=True)+'">'+html.escape(text)+'</a></p><!-- /wp:paragraph -->'
def table(rows):
    body=''.join('<tr><th scope="row">'+html.escape(r['label'])+'</th><td>'+html.escape(r['raw'])+'</td></tr>'for r in rows)
    return '<!-- wp:table --><figure class="wp-block-table"><table><tbody>'+body+'</tbody></table></figure><!-- /wp:table -->'
def row(label,raw,value='',unit=''):return {'label':label,'raw':raw,'value':value,'unit':unit}

def quantity_rows(designation):
    labels={'V':'Voltage notation (function unconfirmed)','m':'Distance notation','mS/cm':'Conductivity notation','ppm':'Concentration notation (parameter unconfirmed)','% FS':'Full-scale percentage notation','%':'Percentage notation (basis unconfirmed)','°C':'Temperature source notation'}
    found=[]
    for m in re.finditer(r'([<>≤≥]?\s*\d+(?:\.\d+)?(?:–\d+(?:\.\d+)?)?)\s+(mS/cm|ppm|% FS|%|°C|V|m)(?![A-Za-z])',designation):
        value=m[1].strip();unit=m[2]
        found.append(row(labels[unit],value+' '+unit,value,unit))
    return found

def expected(post):
    if 'frozenExpected' in post:return post['frozenExpected']
    fields={k:post[k] for k in ['title','content','excerpt']}
    fields.update({k:post['meta'][k] for k in ['_cml_sku','_cml_specifications','_cml_description','_yoast_wpseo_title','_yoast_wpseo_metadesc','_cml_source_url']})
    return {'modifiedAt':post['modifiedAt'],'publishedAt':post['publishedAt'],'fieldSHA256':{k:digest(v)for k,v in fields.items()}}

def product(post,source):
    fields=source['fields'];name=translate(fields['名称']);designation=translate(fields['规格型号']);category=translate(fields['说明'])
    title=units(post['title'])
    if post['id']==1099:title='SCW-53E Wireless Data Acquisition and Processing Module for Mud Logging'
    if post['id']==1126:title='2T04D05 Reaction Cell and Pressure Sensor Accessory for Mud Logging'
    if post['id']==1249:title='Wireless Electrochemical 100p 8.4 V 2 ppm Hydrogen Sulfide Sensor for Mud Logging'
    sku=fields['规格型号'] if post['id']in MODELS else ''
    specs=[row('Item name',name),row('Supplier catalogue model' if sku else 'Supplier catalogue designation',designation),row('Supplier category note',category)]
    specs+=quantity_rows(designation)
    notes=[]
    if not sku:notes.append('The catalogue designation combines descriptive or configuration text; a unique manufacturer model/SKU has not been confirmed.')
    else:notes.append('The model text is verified against the supplier catalogue. Confirm the exact supplied variant and nameplate before ordering.')
    notes+=SPECIAL.get(post['id'],[])
    if re.search(r'(?:Ex|EX|iaT|\bia\b)',fields['规格型号']):notes.append('Hazardous-area strings are copied as source markings, not verified certification. Confirm the applicable certificate, equipment variant, protection concept and temperature classification.')
    if re.search(r'\bJ\b',designation):notes.append('J is retained as supplier shorthand; its meaning has not been expanded into an interface, approval or other technical claim.')
    if '%FS' in fields['规格型号']:notes.append('FS denotes full scale in the displayed notation. No ± sign, error-band definition or calibration condition is added to the source percentage.')
    if 'mS/cm' in fields['规格型号'] and '0-' not in fields['规格型号'].split('mS/cm')[0]:notes.append('The source lists a single conductivity value. A zero-to-value measurement range has not been inferred.')
    notes.append('Only the reviewed catalogue fields are represented here. Confirm voltage functions, wiring, measurement basis, mechanical fit, calibration and required documents for the quoted configuration.')
    url=source['expectedSource']
    parts=[p('Supplier catalogue entry for '+name.lower()+'. The source designation is '+designation+'.'),heading('Reviewed catalogue information'),table(specs),heading('Unconfirmed items and selection notes')]
    parts += [p(n)for n in notes]
    parts += [heading('Source and documentation'),link(url,'EPEC supplier catalogue — original source'),p('Source fields checked on 2026-11-05. English wording and unit spacing are editorial normalizations; source numbers, comparators and marking strings are retained.'),link('/contact-us/?product='+urllib.parse.quote(title,safe=''),'Request the exact configuration and documentation')]
    description=name+'; '+designation+'. Confirm the model, interfaces and documentation for the quoted configuration.'
    return {'title':title,'content':'\n'.join(parts),'excerpt':description,'meta':{'_cml_sku':sku,'_cml_specifications':specs,'_cml_description':description,'_yoast_wpseo_title':'','_yoast_wpseo_metadesc':description,'_cml_source_url':url,'_cml_catalogue_designation':designation},'source':{'url':url,'observedAt':source['observedAt'],'sha256':source['sha256'],'factsSHA256':digest({'url':url,'fields':fields})},'unconfirmedItems':notes}

def article(post,copy):
    parts=[]
    for section in copy['sections']:
        parts.append(heading(section['heading']));parts += [p(s)for s in section['paragraphs']]
    parts.append(heading('Sources and related reading'));parts += [link(x['url'],x['text'])for x in copy['links']]
    return {'title':copy['title'],'content':'\n'.join(parts),'excerpt':copy['description'],'meta':{'_cml_sku':post['meta']['_cml_sku'],'_cml_specifications':post['meta']['_cml_specifications'],'_cml_description':copy['description'],'_yoast_wpseo_title':'','_yoast_wpseo_metadesc':copy['description'],'_cml_source_url':post['meta']['_cml_source_url']},'source':{'originalSourceId':post['meta']['_cml_source_id'],'references':copy['links']},'unconfirmedItems':copy['reviewNotes']}

def build(source_dir,output):
    if source_dir:
        posts=json.loads((source_dir/'selected-before.json').read_text())
        sources={r['id']:r for r in json.loads((source_dir/'epec-live-sources.json').read_text())['results']}
    else:
        frozen=json.loads((ROOT/'review-inputs.json').read_text());posts=frozen['posts'];sources={r['id']:r for r in frozen['sources']}
    copies=json.loads((ROOT/'articles.json').read_text());records=[]
    for post in posts:
        after=article(post,copies[str(post['id'])]) if post['type']=='post' else product(post,sources[post['id']])
        records.append({'id':post['id'],'language':'en','type':post['type'],'slug':post['slug'],'url':post['url'],'expected':expected(post),'after':after,'afterSHA256':digest(after)})
    if len(records)!=36 or sum(x['type']=='post'for x in records)!=4:raise ValueError('Scope mismatch')
    output.write_text(json.dumps({'batch':'source-content-20261105','site':'https://www.chinamudlogging.com','records':records},ensure_ascii=False,indent=2)+'\n')
    print(json.dumps({'records':len(records),'articles':4,'products':32,'skuRetained':sum(bool(r['after']['meta']['_cml_sku'])for r in records if r['type']=='cml_product')}))

if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--source-dir',type=pathlib.Path);parser.add_argument('--out',type=pathlib.Path,default=ROOT/'reviewed-manifest.json');args=parser.parse_args();build(args.source_dir,args.out)
