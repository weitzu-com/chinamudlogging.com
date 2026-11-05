#!/usr/bin/env python3
"""Apply explicit editorial decisions, without assigning publication approval."""
import importlib.util,json,re,sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parent))
from full_content import DIRECTORY,digest
sp=importlib.util.spec_from_file_location('draft',Path(__file__).with_name('full-translations.py'));draft=importlib.util.module_from_spec(sp);sp.loader.exec_module(draft)
copy=json.loads((DIRECTORY/'editorial-copy.json').read_text());labels=json.loads((DIRECTORY/'parameter-labels.json').read_text())
overrides=json.loads((DIRECTORY/'overrides.json').read_text()) if (DIRECTORY/'overrides.json').exists() else {}
data=json.loads((DIRECTORY/'full-translations.json').read_text())
shipping=dict(zip(copy['languages'],['原资料说明 CNPS 可通过快递、空运或海运交付；具体方式以订单约定为准。','Según el catálogo, CNPS puede entregar por mensajería urgente, vía aérea o marítima; confirme las condiciones del pedido.','По каталогу CNPS может организовать экспресс-, авиа- или морскую доставку; условия уточняются в заказе.','يذكر الكتالوج أن CNPS يمكنها الشحن بالبريد السريع أو جواً أو بحراً؛ تُؤكد الشروط في الطلب.','Le catalogue indique une livraison CNPS par messagerie express, avion ou mer ; modalités à confirmer à la commande.','Laut Katalog kann CNPS per Express, Luft- oder Seefracht liefern; maßgeblich sind die Auftragsbedingungen.']))
words={
'zh':{'插穗':'岩屑','冻钻头':'卡钻','失井':'井漏','发射机':'变送器','发射器':'变送器','泵行程':'泵冲','综合测井仪':'综合录井仪','测井系统':'录井系统','白带':'乳白色','游览':'偏离','原理principle':'原理','temperature:':'温度：','range:':'范围：','current:':'电流：','humidity:':'湿度：','supply:':'供电：','PRODUCTSPECIFICATION':'产品规格','WORKINGPRINCIPLE':'工作原理','(1）Remove':'(1）拆下','(2）Press':'(2）按','(3）The':'(3）','(1）The':'(1）','(2）The':'(2）','(4）When':'(4）当','1.The':'1.','2.The':'2.','Can根据':'可根据','模糊':'含糊'},
'es':{'esquejes':'recortes de perforación','congelación de la broca':'atasco de la sarta','curvas logarítmicas':'curvas de registro','equipo maderero':'equipo de registro','aceite de seda':'silk oil','aceite de plomo':'lead oil','azul leucorrea':'azul blanquecino','la mantequilla':'la grasa lubricante','registro de lodo':'registro de lodos','(1）Remove':'(1) Retire','(2）Press':'(2) Siga','(3）The':'(3)','(1）The':'(1)','(2）The':'(2)','(4）When':'(4) Cuando','1.The':'1.','2.The':'2.','temperature:':'temperatura:','range:':'rango:','current:':'corriente:','humidity:':'humedad:','supply:':'alimentación:','degree>':'grado>','Density（':'Densidad（','Export densidad':'densidad de salida','Entrance densidad':'densidad de entrada','Can se':'se'},
'ru':{'черенки':'буровой шлам','замерзание долота':'прихват бурильной колонны','замораживание долота':'прихват бурильной колонны','шелковое масло':'silk oil','свинцовое масло':'lead oil','бели':'беловатый оттенок','сливочное масло':'консистентная смазка','(1）Remove':'(1) Снимите','(2）Press':'(2) Согласно','(3）The':'(3)','(1）The':'(1)','(2）The':'(2)','(4）When':'(4) Когда','1.The':'1.','2.The':'2.','temperature:':'температура:','range:':'диапазон:','current:':'ток:','humidity:':'влажность:','supply:':'питание:','Density（':'Плотность（','Export ':'выходная ','Entrance ':'входная ','Can ':'Можно '},
'ar':{'الزبدة':'شحم التزييت','زيت الحرير':'silk oil','زيت الرصاص':'lead oil','الثر الأبيض':'أبيض مائل للزرقة','تجميد المثقاب':'انحشار سلسلة الحفر','تجميد لقمة الحفر':'انحشار سلسلة الحفر','(1）Remove':'(1) أزل','(2）Press':'(2) وفقاً لـ','(3）The':'(3)','(1）The':'(1)','(2）The':'(2)','(4）When':'(4) عندما','1.The':'1.','2.The':'2.','temperature:':'درجة الحرارة:','range:':'النطاق:','current:':'التيار:','humidity:':'الرطوبة:','supply:':'التغذية:','Density（':'الكثافة（','Export ':'المخرج ','Entrance ':'المدخل ','Can ':'يمكن '},
'fr':{'boutures':'déblais de forage','gel du trépan':'coincement de la garniture','congélation du trépan':'coincement de la garniture','courbes logarithmiques':'courbes de diagraphie','huile de soie':'silk oil','huile de plomb':'lead oil','leucorrhée':'blanchâtre','le beurre':'la graisse lubrifiante','(1）Remove':'(1) Retirer','(2）Press':'(2) Suivre','(3）The':'(3)','(1）The':'(1)','(2）The':'(2)','(4）When':'(4) Lorsque','1.The':'1.','2.The':'2.','temperature:':'température :','range:':'plage :','current:':'courant :','humidity:':'humidité :','supply:':'alimentation :','degree>':'de séparation >','Density（':'Masse volumique（','Export ':'sortie ','Entrance ':'entrée ','Can ':'Peut '},
'de':{'Stecklinge':'Bohrklein','Einfrieren des Bohrers':'Festwerden des Bohrstrangs','Einfrieren des Bohrmeißels':'Festwerden des Bohrstrangs','logarithmischen Kurven':'Bohrlochmesskurven','Seidenöl':'silk oil','Bleiöl':'lead oil','Leukorrhoe':'weißlicher Farbton','Butter':'Schmierfett','(1）Remove':'(1) Entfernen','(2）Press':'(2) Gemäß','(3）The':'(3)','(1）The':'(1)','(2）The':'(2)','(4）When':'(4) Wenn','1.The':'1.','2.The':'2.','temperature:':'Temperatur:','range:':'Bereich:','current:':'Strom:','humidity:':'Feuchte:','supply:':'Versorgung:','Density（':'Dichte（','Export ':'Auslass ','Entrance ':'Einlass ','Can ':'Kann '}}
for key,row in data.items():
    lang=row['language'];pid=str(row['sourceId'])
    for segment in row['segments']:
        value=segment['translation'];source=segment['source'];compact=''.join(source.split())
        for h,translations in copy['headings'].items():
            if compact==''.join(h.split()):value=translations[lang];break
        parts=re.split('[:：]',source,maxsplit=1)
        if len(parts)==2 and parts[0].strip() in labels:
            raw=parts[1].strip()
            if len(raw)<100 and not re.search(r'[a-z]{4}',raw):value=labels[parts[0].strip()][lang]+': '+raw
        for old,new in words[lang].items():value=value.replace(old,new)
        value=value.replace('lodoss','lodos')
        if source.startswith('CNPS can ship'):value=shipping[lang]
        value=overrides.get(key,{}).get(segment['key'],value)
        segment['reviewedText']=value
    for kind in ['common','product']:
        note=draft.NOTES['common' if kind=='common' else pid]
        row['segments'].append({'key':'note.'+kind,'source':note,'input':note,'sourceSHA256':draft.sha(note),'inputSHA256':draft.sha(note),'chunks':draft.chunks(note),'translation':copy['common'][lang] if kind=='common' else copy['notes'][pid][lang],'reviewedText':copy['common'][lang] if kind=='common' else copy['notes'][pid][lang]}) if not any(s['key']=='note.'+kind for s in row['segments']) else None
(DIRECTORY/'full-translations.json').write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
print('Editorial decisions applied to',len(data),'pending full translations; no publication approval issued.')
