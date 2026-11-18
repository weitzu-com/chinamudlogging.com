"""Apply explicit source-reviewed facts only to the exact reviewed source record."""
import copy

def apply_review(data, review):
    sources=review['sources']
    for rule_id,rule in review['rules'].items():
        for product_id,expected in rule['products'].items():
            product=data['products'][product_id]
            if product['fingerprint']!=expected:
                raise ValueError('Reviewed source changed: '+product_id)
            language=product['language']
            if rule.get('cite_existing'):
                for row in product['rows']: row['citations']=rule['sources']
            if 'designation' in rule:
                product['designation']=rule['designation'];product['designation_citations']=rule['sources']
            for old_label in rule.get('remove',{}).get(language,[]):
                found=[r for r in product['rows'] if r['label']==old_label]
                if len(found)!=1:raise ValueError('Expected one reviewed row: '+product_id+' '+old_label)
                product['rows'].remove(found[0])
            for index,fact in enumerate(rule.get('facts',[])):
                label=fact['labels'][language]
                row={'label':label,'value':fact['value'],'status':fact.get('status',''),'source':fact.get('source',fact['value']),
                     'citations':fact.get('sources',rule['sources']),'verification_id':rule_id+':'+str(index)}
                if fact.get('notes'):row['note']=fact['notes'][language]
                product['rows'].append(row)
            for note in rule.get('notes',[]):
                product['checks'].append(note[language])
    data['sources']=copy.deepcopy(sources)
    data['source_review']=review['revision']
    return data
