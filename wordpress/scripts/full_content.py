"""Reviewed full-product content shared by package preparation and scoped release."""
import collections, hashlib, html, json, re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
DIRECTORY=ROOT/'wordpress/localization'
LANGS=['zh','es','ru','ar','fr','de']
TITLES={
 'zh':['原始资料全文','选型前需确认','完整英文来源','原始校验数据表','膜片间距','出口','入口','密度','差压','输出电流','水柱高度'],
 'es':['Documentación completa del catálogo','Confirmaciones para la selección','Fuente completa en inglés','Tablas de calibración originales','Separación entre diafragmas','salida','entrada','Densidad','Presión diferencial','Corriente de salida','Altura de la columna de agua'],
 'ru':['Полное описание из исходного каталога','Что уточнить при подборе','Полный источник на английском','Исходные калибровочные таблицы','Расстояние между мембранами','выход','вход','Плотность','Перепад давления','Выходной ток','Высота водяного столба'],
 'ar':['النص الكامل لبيانات الكتالوج','نقاط يجب تأكيدها قبل الاختيار','المصدر الكامل باللغة الإنجليزية','جداول المعايرة الأصلية','المسافة بين الغشائين','المخرج','المدخل','الكثافة','الضغط التفاضلي','تيار الخرج','ارتفاع عمود الماء'],
 'fr':['Description complète du catalogue','Points à confirmer pour la sélection','Source complète en anglais','Tableaux d’étalonnage d’origine','Écartement des membranes','sortie','entrée','Masse volumique','Pression différentielle','Courant de sortie','Hauteur de colonne d’eau'],
 'de':['Vollständige Beschreibung aus dem Katalog','Vor der Auswahl zu bestätigen','Vollständige englische Quelle','Originale Kalibriertabellen','Membranabstand','Auslass','Einlass','Dichte','Differenzdruck','Ausgangsstrom','Wassersäulenhöhe']}
def digest(value):return hashlib.sha256(json.dumps(value,ensure_ascii=False,sort_keys=True).encode()).hexdigest()
def text(segment):return segment.get('reviewedText',segment['translation'])
def load_approved():
    file=DIRECTORY/'review.json'
    if not file.exists():return {}
    review=json.loads(file.read_text());data=json.loads((DIRECTORY/'full-translations.json').read_text())
    if review.get('status')!='approved':return {}
    expected=set(review['records'])
    if len(expected)!=60 or expected!=set(data):raise ValueError('Full translation review must cover exactly 60 records')
    for key,row in data.items():
        if digest(row)!=review['records'][key]['translationSHA256']:raise ValueError('Translation changed after review: '+key)
    return data

def numeric_tokens(value):
    value=value.translate(str.maketrans('٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹','01234567890123456789'))
    value=value.replace('³','3').replace('²','2')
    return collections.Counter(x.replace(',','.') for x in re.findall(r'\d+(?:[.,]\d+)*',value))

def segment_errors(segment):
    source=segment['input'];target=text(segment);errors=[]
    if not target.strip():errors.append('empty')
    if ''.join(''.join(segment['chunks']).split())!=''.join(source.split()):errors.append('source_chunk_coverage')
    if hashlib.sha256(segment['source'].encode()).hexdigest()!=segment['sourceSHA256']:errors.append('source_hash')
    original=numeric_tokens(source);actual=numeric_tokens(target)
    missing=original-actual;extra=actual-original
    written={'one':'1','two':'2','three':'3','first':'1','second':'2','third':'3'}
    allowance=collections.Counter()
    for word,number in written.items():allowance[number]+=len(re.findall(r'\b'+word+r'\b',source,re.I))
    if missing or extra-allowance:errors.append('numeric_values')
    for marker in ['≤','≥','±','<','>']:
        if source.count(marker)!=target.count(marker):errors.append('comparison:'+marker)
    if re.search(r'QZX|XZQ|⟪⟪⟪|⟫⟫⟫|CMLTOKEN',target,re.I):errors.append('placeholder')
    return sorted(set(errors))

def inline(value):
    codes=json.loads((DIRECTORY/'glossary.json').read_text())['protectedCodes']
    parts=r'|'.join(re.escape(x) for x in sorted(codes,key=len,reverse=True))
    unit=r'(?:r\s*/\s*min|ms/cm|g/cm3|VDC|%RH|％FS|%FS|％|%|℃|°C|°F|kHz|Hz|kPa|pA|mA|mm|cm|nm|min|CFM|Grms|KG|kg|dB|V|W|w|m|s)'
    quantity=r'(?:[<>≤≥±+－−-]\s*)?\d+(?:[.,]\d+)?(?:\s*[×x~～/:-]\s*[+－−-]?\d+(?:[.,]\d+)?)*(?:\s*'+unit+r'(?![A-Za-z]))?'
    pattern=re.compile(r'(?<![A-Za-z])(?:'+parts+r')(?![A-Za-z])|'+quantity)
    result=[];start=0
    for match in pattern.finditer(value):
        result.extend([html.escape(value[start:match.start()]),'<span dir="ltr">'+html.escape(match.group())+'</span>']);start=match.end()
    return ''.join(result)+html.escape(value[start:])
def paragraph(value):return '<!-- wp:paragraph --><p>'+inline(value)+'</p><!-- /wp:paragraph -->'
def heading(value,level=2):return '<!-- wp:heading '+('' if level==2 else '{"level":3} ')+'--><h'+str(level)+' class="wp-block-heading">'+html.escape(value)+'</h'+str(level)+'><!-- /wp:heading -->'

def calibration_tables(lang):
    labels=TITLES[lang];body=heading(labels[3]);density=['0.96','1.32','1.68','2.04','2.40','2.76'];current=['4.00','7.20','10.40','13.60','16.80','20.00']
    tables=[('45cm',labels[5],labels[8]+' (kPa)',['4.32','5.94','7.56','9.18','10.80','12.42']),('60cm',labels[6],labels[8]+' (kPa)',['5.76','7.92','10.08','12.24','14.40','16.56']),('45cm',labels[5],labels[10]+' (mm)',['430','600','750','920','1080','1240']),('60cm',labels[6],labels[10]+' (mm)',['580','790','1010','1220','1440','1650'])]
    for spacing,position,label,values in tables:
        body+=heading(labels[4]+' '+spacing+' — '+position+' — '+label,3)
        body+='<!-- wp:table --><figure class="wp-block-table"><table><thead><tr>'+''.join('<th scope="col">'+html.escape(x)+'</th>' for x in [labels[7]+' (g/cm3)',label,labels[9]+' (mA)'])+'</tr></thead><tbody>'
        for i,value in enumerate(values):body+='<tr>'+''.join('<td><span dir="ltr">'+x+'</span></td>' for x in [density[i],value,current[i]])+'</tr>'
        body+='</tbody></table></figure><!-- /wp:table -->'
    return body

def render(product,lang,row):
    if row['sourceSHA256']!=digest(product):raise ValueError('English source changed: '+str(product['id']))
    segments={s['key']:s for s in row['segments']};expected={'note.common','note.product'}
    body=paragraph(text(segments['note.common']))
    for si,section in enumerate(product['sections']):
        key=f's{si}.heading';expected.add(key);body+=heading(text(segments[key]))
        for pi,original in enumerate(section['paragraphs']):
            key=f's{si}.p{pi}';expected.add(key);segment=segments[key]
            if segment['source']!=original:raise ValueError('Paragraph source changed')
            errors=segment_errors(segment)
            if errors:raise ValueError(str(product['id'])+':'+lang+':'+key+':'+','.join(errors))
            value=text(segment)
            if re.fullmatch(r'[A-Z &-]{5,}',original.strip()):body+=heading(value,3)
            else:body+=paragraph(value)
    if set(segments)!=expected:raise ValueError('Missing or extra source paragraph')
    if product['id']==251:body+=calibration_tables(lang)
    body+=heading(TITLES[lang][1])+paragraph(text(segments['note.product']))
    body+='<!-- wp:paragraph --><p><a href="'+html.escape(product['legacyUrl'])+'">'+TITLES[lang][2]+'</a></p><!-- /wp:paragraph -->'
    for download in product.get('downloads',[]):body+='<!-- wp:paragraph --><p><a href="'+html.escape(download['href'])+'">'+html.escape(download['label'])+'</a></p><!-- /wp:paragraph -->'
    return body

def localized_specs(product,row,source_specs,existing_specs=None):
    pairs={}
    for segment in row['segments']:
        a=re.split('[:：]',segment['source'],maxsplit=1);b=re.split('[:：]',text(segment),maxsplit=1)
        if len(a)==2 and len(b)==2:pairs[a[0].strip()]=b[0].strip()
    result=[{**s,'label':pairs.get(s['label'],s['label'])} for s in source_specs]
    priority=json.loads((ROOT/'wordpress/migration/priority-localizations.json').read_text())
    for original in existing_specs or []:
        spec={**original,'label':priority['labels'][row['language']].get(original['label'],original['label'])}
        if not any(s['label']==spec['label'] and s['raw']==spec['raw'] for s in result):result.append(spec)
    return result
